<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah isi keranjang menjadi 1 transaksi (checkout) berisi beberapa order (1 order per layanan).
 *
 * - Semua dalam 1 transaksi DB: checkout, order, dan pengosongan keranjang sukses bersama atau gagal bersama.
 * - Harga dihitung ulang dari tarif layanan saat ini (lewat OrderService), tidak dari input.
 * - submission_token membuat klik "Buat Pesanan" ganda tetap menghasilkan 1 checkout.
 */
class CheckoutService
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * @param  array{contact_phone: string, drop_off_date: string, notes?: ?string, submission_token: string}  $detail
     *
     * @throws ValidationException
     */
    public function createFromCart(User $user, array $detail): Checkout
    {
        $token = $detail['submission_token'];

        try {
            return DB::transaction(function () use ($user, $detail, $token) {
                // Antrean per pelanggan: request ganda menunggu di sini lalu melihat checkout yang sudah dibuat
                User::whereKey($user->id)->lockForUpdate()->first();

                if ($existing = $this->findBySubmissionToken($user, $token)) {
                    return $existing;
                }

                $items = $user->cartItems()->with('service')->lockForUpdate()->oldest('id')->get();
                $orderable = $items->filter(fn (CartItem $item) => $item->isOrderable());

                if ($orderable->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => 'Keranjang kosong atau layanan di dalamnya sedang tidak tersedia.',
                    ]);
                }

                $checkout = Checkout::create([
                    'code'             => $this->generateCode(),
                    'user_id'          => $user->id,
                    'total_amount'     => 0,
                    'status'           => Checkout::STATUS_AWAITING,
                    'contact_phone'    => $detail['contact_phone'],
                    'drop_off_date'    => $detail['drop_off_date'],
                    'notes'            => $detail['notes'] ?? null,
                    'submission_token' => $token,
                ]);

                foreach ($orderable as $item) {
                    $this->orderService->create([
                        'checkout_id'    => $checkout->id,
                        'user_id'        => $user->id,
                        'service_id'     => $item->service_id,
                        'machine_qty'    => $item->qty,
                        'item_type'      => $item->itemTypeLabel(),
                        'notes'          => $item->notes,
                        'customer_name'  => $user->name,
                        'customer_phone' => $detail['contact_phone'],
                    ]);
                }

                $checkout->update(['total_amount' => $checkout->orders()->sum('total_price')]);

                // Hanya item yang ikut dipesan yang dihapus; item layanan nonaktif tetap terlihat di keranjang
                CartItem::whereKey($orderable->modelKeys())->delete();

                return $checkout;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Pengaman terakhir jika token yang sama lolos bersamaan: pakai checkout yang sudah tersimpan
            if ($existing = $this->findBySubmissionToken($user, $token)) {
                return $existing;
            }

            throw $e;
        }
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

    protected function generateCode(): string
    {
        do {
            $code = 'TRX-' . strtoupper(Str::random(8));
        } while (Checkout::where('code', $code)->exists());

        return $code;
    }
}
