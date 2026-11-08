<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya tempat status transaksi (checkouts) dan pesanan (orders) boleh berubah.
 * Setiap perubahan dicatat di order_status_logs dalam transaksi DB yang sama.
 *
 * Transaksi (status pembayaran):
 *   menunggu_pembayaran → lunas       hanya dari gateway (webhook / rekonsiliasi) atau pelunasan manual admin
 *   menunggu_pembayaran → gagal       gateway
 *   menunggu_pembayaran → kadaluarsa  gateway / scheduler
 *   lunas, gagal, kadaluarsa = final
 *
 * Pesanan (status pengerjaan): pending → diproses → siap_diambil → selesai
 *   - hanya admin/pegawai, dan hanya setelah lunas;
 *   - maju boleh melompat; selesai = final;
 *   - mundur hanya admin dengan catatan wajib (koreksi salah input), termasuk dari selesai
 *     selama CORRECTION_WINDOW_HOURS sejak ditandai selesai.
 *
 * Perubahan ke status yang sama adalah no-op (tanpa log), sehingga notifikasi atau klik ganda aman.
 * Urutan lock: checkout → payment (dipakai juga oleh PaymentService) untuk mencegah deadlock.
 */
class OrderStateMachine
{
    public const ACTOR_PELANGGAN = 'pelanggan';
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_PEGAWAI = 'pegawai';
    public const ACTOR_WEBHOOK = 'webhook';
    public const ACTOR_RECONCILE = 'reconcile';
    public const ACTOR_SCHEDULER = 'scheduler';
    public const ACTOR_SYSTEM = 'system';

    /** to => siapa saja yang boleh memindahkan transaksi ke status itu (dari menunggu_pembayaran). */
    public const CHECKOUT_TRANSITIONS = [
        Checkout::STATUS_AWAITING => [
            Checkout::STATUS_PAID    => [self::ACTOR_WEBHOOK, self::ACTOR_RECONCILE, self::ACTOR_ADMIN],
            Checkout::STATUS_FAILED  => [self::ACTOR_WEBHOOK, self::ACTOR_RECONCILE],
            Checkout::STATUS_EXPIRED => [self::ACTOR_WEBHOOK, self::ACTOR_RECONCILE, self::ACTOR_SCHEDULER],
        ],
    ];

    public const ORDER_FINAL = [Order::STATUS_SELESAI];

    public const MIN_CORRECTION_NOTE = 5;

    /** Admin masih boleh mengoreksi pesanan selesai selama ini (jam) sejak ditandai selesai. */
    public const CORRECTION_WINDOW_HOURS = 24;

    // ─────────────────────────────────────────────────────────────
    //  Transaksi (checkout)
    // ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes  kolom tambahan yang ikut diubah (mis. pending_reason)
     * @return bool true jika status berubah, false jika sudah berstatus $to (no-op)
     *
     * @throws InvalidStatusTransition
     */
    public function transitionCheckout(Checkout $checkout, string $to, string $actorType, ?User $actor = null, ?string $note = null, array $attributes = []): bool
    {
        return DB::transaction(function () use ($checkout, $to, $actorType, $actor, $note, $attributes) {
            $locked = Checkout::whereKey($checkout->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return false;
            }

            $allowedActors = self::CHECKOUT_TRANSITIONS[$from][$to] ?? null;

            if ($allowedActors === null) {
                throw new InvalidStatusTransition("Transaksi {$locked->code} tidak bisa diubah dari '{$from}' ke '{$to}'.");
            }

            if (! in_array($actorType, $allowedActors, true)) {
                throw new InvalidStatusTransition("Status '{$to}' untuk transaksi {$locked->code} tidak boleh diset oleh {$actorType}.");
            }

            if ($to === Checkout::STATUS_PAID) {
                $attributes += ['paid_at' => now(), 'pending_reason' => null];
            }

            $locked->update(['status' => $to] + $attributes);
            $this->log($locked, $from, $to, $actorType, $actor, $note);

            $checkout->forceFill($locked->only(array_merge(['status'], array_keys($attributes))))->syncOriginal();

            return true;
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Pesanan (order)
    // ─────────────────────────────────────────────────────────────

    /**
     * @return bool true jika status berubah, false jika sudah berstatus $to (no-op)
     *
     * @throws InvalidStatusTransition
     */
    public function transitionOrder(Order $order, string $to, User $actor, ?string $note = null): bool
    {
        if (! in_array($to, Order::STATUSES, true)) {
            throw new InvalidStatusTransition("Status '{$to}' tidak valid.");
        }

        $actorType = self::actorTypeFor($actor);

        if (! in_array($actorType, [self::ACTOR_ADMIN, self::ACTOR_PEGAWAI], true)) {
            throw new InvalidStatusTransition('Hanya admin atau pegawai yang berwenang mengubah status pesanan.');
        }

        return DB::transaction(function () use ($order, $to, $actor, $actorType, $note) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return false;
            }

            // Selesai = final, kecuali koreksi admin dalam jendela waktu (selalu mundur, jadi catatan tetap wajib di bawah)
            if (in_array($from, self::ORDER_FINAL, true) && ! ($actorType === self::ACTOR_ADMIN && $this->withinCorrectionWindow($locked))) {
                throw new InvalidStatusTransition($actorType === self::ACTOR_ADMIN
                    ? "Pesanan {$locked->order_code} sudah selesai lebih dari " . self::CORRECTION_WINDOW_HOURS . ' jam, statusnya tidak bisa dikoreksi lagi.'
                    : "Pesanan {$locked->order_code} sudah selesai, statusnya tidak bisa diubah lagi.");
            }

            // Bayar dulu baru dikerjakan
            if (! $locked->isPaid()) {
                throw new InvalidStatusTransition("Pesanan {$locked->order_code} belum dibayar sehingga belum bisa dikerjakan.");
            }

            $note = $this->cleanNote($note);
            $isBackward = array_search($to, Order::STATUSES, true) < array_search($from, Order::STATUSES, true);

            if ($isBackward) {
                if ($actorType !== self::ACTOR_ADMIN) {
                    throw new InvalidStatusTransition('Status pesanan tidak bisa dimundurkan. Hubungi admin untuk koreksi.');
                }

                if (mb_strlen((string) $note) < self::MIN_CORRECTION_NOTE) {
                    throw new InvalidStatusTransition('Catatan koreksi wajib diisi (minimal ' . self::MIN_CORRECTION_NOTE . ' karakter) untuk memundurkan status pesanan.');
                }

                $note = 'Koreksi mundur: ' . $note;
            }

            $locked->update(['status' => $to]);
            $this->log($locked, $from, $to, $actorType, $actor, $note);

            $order->forceFill(['status' => $to])->syncOriginal();

            return true;
        });
    }

    /**
     * Waktu pesanan ditandai selesai = log terakhir ke 'selesai'. Pesanan lama tanpa log memakai updated_at
     * (perubahan terakhir pada baris pesanan, yang untuk pesanan selesai adalah saat ditandai selesai).
     */
    protected function withinCorrectionWindow(Order $order): bool
    {
        $completedAt = $order->statusLogs()->where('to_status', Order::STATUS_SELESAI)->reorder('id', 'desc')->value('created_at')
            ?? $order->updated_at;

        return $completedAt !== null
            && now()->lessThanOrEqualTo(Carbon::parse($completedAt)->addHours(self::CORRECTION_WINDOW_HOURS));
    }

    // ─────────────────────────────────────────────────────────────
    //  Pencatatan
    // ─────────────────────────────────────────────────────────────

    /**
     * Baris pertama di riwayat saat transaksi / pesanan dibuat (from_status = NULL).
     */
    public function recordCreated(Checkout|Order $subject, ?User $actor, ?string $note = null): void
    {
        $this->log($subject, null, $subject->status, self::actorTypeFor($actor), $actor, $note);
    }

    public static function actorTypeFor(?User $user): string
    {
        if (! $user) {
            return self::ACTOR_SYSTEM;
        }

        return match ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) {
            UserRole::ADMIN     => self::ACTOR_ADMIN,
            UserRole::PEGAWAI   => self::ACTOR_PEGAWAI,
            UserRole::PELANGGAN => self::ACTOR_PELANGGAN,
            default             => self::ACTOR_SYSTEM,
        };
    }

    protected function log(Checkout|Order $subject, ?string $from, string $to, string $actorType, ?User $actor, ?string $note): void
    {
        OrderStatusLog::create([
            'checkout_id' => $subject instanceof Checkout ? $subject->id : null,
            'order_id'    => $subject instanceof Order ? $subject->id : null,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => $this->cleanNote($note),
            'actor_type'  => $actorType,
            'actor_id'    => $actor?->id,
        ]);

        Log::info('status.transition', [
            $subject instanceof Checkout ? 'checkout_code' : 'order_code' => $subject instanceof Checkout ? $subject->code : $subject->order_code,
            'from'       => $from,
            'to'         => $to,
            'actor_type' => $actorType,
            'actor_id'   => $actor?->id,
        ]);
    }

    protected function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 500);
    }
}
