<?php

namespace Tests\Concerns;

use App\Models\CashShift;
use App\Models\Checkout;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\ShiftService;
use Illuminate\Support\Str;

/**
 * Helper data keuangan: shift kas, transaksi outlet lewat panel, transaksi online lunas.
 */
trait CreatesFinanceData
{
    protected function openShift(User $user, int $opening = 100000): CashShift
    {
        return app(ShiftService::class)->open($user, $opening, (string) Str::uuid());
    }

    /**
     * Payload form transaksi outlet (bisa ditimpa per test).
     */
    protected function outletPayload(User $customer, Service $service, array $overrides = []): array
    {
        return array_replace([
            'customer_id'      => $customer->id,
            'items'            => [['service_id' => $service->id, 'qty' => 2]],
            'payment_timing'   => 'now',
            'payment_method'   => 'cash',
            'cash_received'    => 50000,
            'submission_token' => (string) Str::uuid(),
        ], $overrides);
    }

    protected function createOutlet(User $actor, array $payload): Checkout
    {
        $this->actingAs($actor)->post(route('panel.transaksi.store'), $payload)->assertSessionHasNoErrors()->assertSessionMissing('error');

        return Checkout::where('submission_token', $payload['submission_token'])->firstOrFail();
    }

    /**
     * Transaksi online langsung di DB (status bebas). Jika lunas, pembayaran + ledger ikut dibuat.
     */
    protected function onlineCheckout(User $customer, Service $service, string $status = Checkout::STATUS_PAID, array $payment = []): Checkout
    {
        $checkout = Checkout::create([
            'code' => 'TRX-' . strtoupper(Str::random(8)), 'channel' => Checkout::CHANNEL_ONLINE, 'user_id' => $customer->id,
            'total_amount' => 30000, 'status' => $status, 'paid_at' => $status === Checkout::STATUS_PAID ? now() : null,
            'payment_deadline' => now()->addHour(), 'contact_phone' => '081234567890', 'submission_token' => (string) Str::uuid(),
        ]);

        $this->makeOrder($customer, $service, ['checkout_id' => $checkout->id, 'channel' => 'online', 'machine_qty' => 2, 'unit_price' => 15000, 'total_price' => 30000]);

        if ($status === Checkout::STATUS_PAID) {
            $paid = $checkout->payments()->create($payment + [
                'amount' => 30000, 'method' => 'e-wallet', 'gateway' => 'midtrans', 'payment_type' => 'qris',
                'reference' => $checkout->code . '-P1', 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            ]);
            app(LedgerService::class)->recordSale($paid);
        }

        return $checkout->fresh();
    }
}
