<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah item keranjang yang dipilih menjadi 1 transaksi (checkout) berisi beberapa order (1 order per layanan).
 *
 * - Semua dalam 1 transaksi DB: checkout, order, catatan idempotency, dan pengosongan keranjang
 *   sukses bersama atau gagal bersama.
 * - Hanya item yang dipilih pelanggan yang dipesan; sisanya tetap di keranjang.
 * - No. HP diambil dari akun. Input hanya dipakai sekali untuk akun yang belum punya nomor.
 * - Harga dihitung ulang dari tarif layanan saat ini (lewat OrderService) dalam integer rupiah, tidak dari input.
 * - Idempotent per submission_token (UUID dari form):
 *     • request ganda untuk pelanggan yang sama antre di lock baris user, lalu melihat transaksi yang sudah dibuat;
 *     • token sama + isi sama → transaksi yang sama (replayed);
 *     • token sama + isi beda → transaksi yang sama, perubahan diabaikan (mismatch);
 *     • token milik pelanggan lain → ditolak.
 */
class CheckoutService
{
    public const IDEMPOTENCY_SCOPE = 'checkout';

    public function __construct(
        protected OrderService $orderService,
        protected OrderStateMachine $stateMachine
    ) {}

    /**
     * @param  array{cart_items: list<int|string>, contact_phone?: string, drop_off_date: string, notes?: ?string, submission_token: string}  $detail
     *
     * @throws ValidationException
     */
    public function createFromCart(User $user, array $detail): CheckoutResult
    {
        $token = $detail['submission_token'];

        try {
            return DB::transaction(function () use ($user, $detail, $token) {
                // Antrean per pelanggan: request ganda menunggu di sini lalu melihat checkout yang sudah dibuat
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                $phone = $lockedUser->phone ?: ($detail['contact_phone'] ?? null);
                $hash = $this->requestHash($lockedUser, $detail, $phone);

                if ($existing = $this->findBySubmissionToken($user, $token)) {
                    return $this->replay($existing, $token, $hash);
                }

                $selected = array_unique(array_map('intval', $detail['cart_items']));
                $items = $user->cartItems()->with('service')->whereKey($selected)->lockForUpdate()->oldest('id')->get();
                $orderable = $items->filter(fn (CartItem $item) => $item->isOrderable());

                if ($orderable->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => 'Keranjang kosong atau layanan di dalamnya sedang tidak tersedia.',
                    ]);
                }

                // Isi yang dibayar harus persis yang dikonfirmasi pelanggan, tidak boleh berkurang diam-diam
                if ($orderable->count() !== count($selected)) {
                    throw ValidationException::withMessages([
                        'cart' => 'Sebagian layanan yang dipilih sudah berubah atau tidak tersedia. Cek kembali keranjang Anda.',
                    ]);
                }

                if (! $lockedUser->phone) {
                    $lockedUser->update(['phone' => $phone]);
                }

                $checkout = Checkout::create([
                    'code'             => $this->generateCode(),
                    // Checkout dari keranjang website selalu online (bukan dari input)
                    'channel'          => Checkout::CHANNEL_ONLINE,
                    'user_id'          => $user->id,
                    'total_amount'     => 0,
                    'status'           => Checkout::STATUS_AWAITING,
                    'payment_deadline' => now()->addMinutes((int) config('payment.expiry_minutes', 60)),
                    'contact_phone'    => $phone,
                    'drop_off_date'    => $detail['drop_off_date'],
                    'notes'            => $detail['notes'] ?? null,
                    'submission_token' => $token,
                ]);

                $this->stateMachine->recordCreated($checkout, $lockedUser, 'Transaksi dibuat dari keranjang');

                $total = 0;

                foreach ($orderable as $item) {
                    $order = $this->orderService->create([
                        'checkout_id'    => $checkout->id,
                        'user_id'        => $user->id,
                        'service_id'     => $item->service_id,
                        'machine_qty'    => $item->qty,
                        'item_type'      => $item->itemTypeLabel(),
                        'notes'          => $item->notes,
                        'customer_name'  => $user->name,
                        'customer_phone' => $phone,
                    ], actor: $lockedUser);

                    $total += Rupiah::parse($order->total_price);
                }

                $checkout->update(['total_amount' => Rupiah::toDecimal($total)]);

                // Hanya item yang ikut dipesan yang dihapus; item lain tetap menunggu di keranjang
                CartItem::whereKey($orderable->modelKeys())->delete();

                // Dicatat di transaksi yang sama: tidak mungkin ada checkout tanpa catatan hash-nya
                IdempotencyKey::create([
                    'key'          => $this->idempotencyKey($token),
                    'user_id'      => $user->id,
                    'scope'        => self::IDEMPOTENCY_SCOPE,
                    'request_hash' => $hash,
                    'status'       => IdempotencyKey::STATUS_COMPLETED,
                    'response'     => ['checkout_code' => $checkout->code],
                ]);

                Log::info('checkout.created', [
                    'checkout_code'   => $checkout->code,
                    'idempotency_key' => $token,
                    'user_id'         => $user->id,
                    'total'           => $total,
                    'orders'          => $orderable->count(),
                ]);

                return new CheckoutResult($checkout, CheckoutResult::CREATED);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Pengaman terakhir jika token yang sama lolos bersamaan: pakai checkout yang sudah tersimpan
            if ($existing = $this->findBySubmissionToken($user, $token)) {
                return new CheckoutResult($existing, CheckoutResult::REPLAYED);
            }

            throw $e;
        }
    }

    protected function replay(Checkout $existing, string $token, string $hash): CheckoutResult
    {
        $record = IdempotencyKey::where('key', $this->idempotencyKey($token))->first();

        // Tanpa catatan (transaksi lama / sudah di-prune) dianggap request yang sama
        $sameRequest = $record === null || hash_equals($record->request_hash, $hash);

        Log::log($sameRequest ? 'info' : 'warning', $sameRequest ? 'checkout.replayed' : 'checkout.idempotency_mismatch', [
            'checkout_code'   => $existing->code,
            'idempotency_key' => $token,
            'user_id'         => $existing->user_id,
        ]);

        return new CheckoutResult($existing, $sameRequest ? CheckoutResult::REPLAYED : CheckoutResult::MISMATCH);
    }

    protected function findBySubmissionToken(User $user, string $token): ?Checkout
    {
        $checkout = Checkout::where('submission_token', $token)->first();

        if ($checkout && (int) $checkout->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'submission_token' => 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        return $checkout;
    }

    /**
     * SHA256(user_id + isi form yang dinormalisasi). Yang dihitung hanya field yang menentukan pesanan;
     * no. HP memakai nilai akhir (akun atau input pertama), sehingga kirim ulang setelah nomor
     * tersimpan ke akun tetap dianggap request yang sama.
     */
    protected function requestHash(User $user, array $detail, ?string $phone): string
    {
        $items = array_values(array_unique(array_map('intval', $detail['cart_items'])));
        sort($items);

        $notes = trim((string) ($detail['notes'] ?? ''));

        return hash('sha256', json_encode([
            'user_id'       => (int) $user->id,
            'cart_items'    => $items,
            'drop_off_date' => (string) $detail['drop_off_date'],
            'notes'         => $notes === '' ? null : $notes,
            'contact_phone' => $phone,
        ]));
    }

    protected function idempotencyKey(string $token): string
    {
        return self::IDEMPOTENCY_SCOPE . ':' . $token;
    }

    protected function generateCode(): string
    {
        do {
            $code = 'TRX-' . strtoupper(Str::random(8));
        } while (Checkout::where('code', $code)->exists());

        return $code;
    }
}
