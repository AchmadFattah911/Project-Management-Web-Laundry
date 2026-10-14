<?php

namespace App\Services;

use App\Exceptions\IdempotencyException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Menjamin satu idempotency key hanya diproses SATU kali.
 *
 * Alur run():
 * 1. Klaim key dengan INSERT (UNIQUE(key) di database = sumber kebenaran, aman untuk request paralel).
 * 2. Jika key sudah ada:
 *    - completed  → kembalikan hasil yang tersimpan, callback TIDAK dijalankan lagi (replay).
 *    - processing → tolak (request lain sedang memproses key ini).
 *    - isi request / user / scope berbeda → tolak (key tidak boleh dipakai untuk hal lain).
 *    - failed, atau processing yang macet melewati lock → boleh diambil alih & dicoba ulang.
 * 3. Jalankan callback, simpan hasilnya (completed) atau tandai failed jika melempar exception.
 */
class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $payload  isi request yang menentukan "request yang sama"
     * @param  Closure(): array<string, mixed>  $callback  hasilnya disimpan untuk replay (boleh berisi 'payment_id')
     * @return array{response: array<string, mixed>, replayed: bool}
     *
     * @throws IdempotencyException
     */
    public function run(string $key, string $scope, ?User $user, array $payload, Closure $callback): array
    {
        $hash = $this->hash($scope, $payload);

        $record = $this->claim($key, $scope, $user, $hash);

        if ($record === null) {
            $existing = IdempotencyKey::where('key', $key)->firstOrFail();

            return ['response' => $existing->response ?? [], 'replayed' => true];
        }

        try {
            $response = $callback();
        } catch (Throwable $e) {
            $record->update([
                'status'       => IdempotencyKey::STATUS_FAILED,
                'response'     => ['error' => $e->getMessage()],
                'locked_until' => null,
            ]);

            throw $e;
        }

        $record->update([
            'status'       => IdempotencyKey::STATUS_COMPLETED,
            'payment_id'   => $response['payment_id'] ?? null,
            'response'     => $response,
            'locked_until' => null,
        ]);

        return ['response' => $response, 'replayed' => false];
    }

    /**
     * Mengklaim key untuk diproses. null = key sudah selesai diproses sebelumnya (replay).
     *
     * @throws IdempotencyException
     */
    protected function claim(string $key, string $scope, ?User $user, string $hash): ?IdempotencyKey
    {
        $lockedUntil = now()->addSeconds((int) config('payment.idempotency.lock_seconds', 60));

        try {
            return IdempotencyKey::create([
                'key'          => $key,
                'user_id'      => $user?->id,
                'scope'        => $scope,
                'request_hash' => $hash,
                'status'       => IdempotencyKey::STATUS_PROCESSING,
                'locked_until' => $lockedUntil,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Key sudah pernah datang: lanjut ke pemeriksaan di bawah
        }

        $existing = IdempotencyKey::where('key', $key)->firstOrFail();

        $sameOwner = (int) $existing->user_id === (int) $user?->id;
        if (! $sameOwner || $existing->scope !== $scope || ! hash_equals($existing->request_hash, $hash)) {
            throw IdempotencyException::mismatch();
        }

        if ($existing->status === IdempotencyKey::STATUS_COMPLETED) {
            return null;
        }

        // Ambil alih secara atomik: hanya 1 request yang berhasil meng-update baris ini
        $takenOver = IdempotencyKey::whereKey($existing->id)
            ->where(function ($q) {
                $q->where('status', IdempotencyKey::STATUS_FAILED)
                    ->orWhere(fn ($q) => $q->where('status', IdempotencyKey::STATUS_PROCESSING)->where('locked_until', '<', now()));
            })
            ->update([
                'status'       => IdempotencyKey::STATUS_PROCESSING,
                'locked_until' => $lockedUntil,
                'updated_at'   => now(),
            ]);

        if ($takenOver === 0) {
            throw IdempotencyException::inProgress();
        }

        return $existing->fresh();
    }

    protected function hash(string $scope, array $payload): string
    {
        ksort($payload);

        return hash('sha256', $scope . '|' . json_encode($payload));
    }
}
