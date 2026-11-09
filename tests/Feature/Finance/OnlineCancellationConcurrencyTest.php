<?php

namespace Tests\Feature\Finance;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Concerns\CreatesFinanceData;
use Tests\Concerns\CreatesLaundryData;
use Tests\Concerns\RunsParallelWorkers;
use Tests\TestCase;

/**
 * Klik "Batalkan Pesanan" 3x BERSAMAAN (3 proses PHP) → tetap 1 refund (lock checkout + UNIQUE refunds.checkout_id).
 */
class OnlineCancellationConcurrencyTest extends TestCase
{
    use DatabaseTruncation, CreatesLaundryData, CreatesFinanceData, RunsParallelWorkers;

    public function test_batal_tiga_kali_paralel_hanya_satu_refund(): void
    {
        $customer = $this->makeUser();
        // Transfer bank → refund manual: tidak ada HTTP ke Midtrans dari worker
        $checkout = $this->onlineCheckout($customer, $this->makeService(), payment: ['payment_type' => 'bank_transfer']);

        $responses = $this->runParallelWorkers('cancel_worker.php', array_fill(0, 3, [(string) $customer->id, $checkout->code]));

        $this->assertSame([302, 302, 302], $responses->pluck('http')->all());
        $this->assertSame(1, Refund::count());
        $this->assertSame(Checkout::REFUND_PROCESSING, $checkout->fresh()->refund_status);
        $this->assertSame([Order::STATUS_DIBATALKAN], $checkout->orders()->pluck('status')->unique()->all());
    }
}
