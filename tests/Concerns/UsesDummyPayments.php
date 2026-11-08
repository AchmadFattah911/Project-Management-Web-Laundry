<?php

namespace Tests\Concerns;

/**
 * Test untuk alur pembayaran LAMA (PAYMENT_ENABLED=false: gateway dummy, tombol lunas manual admin).
 * Mode ini dipaku di sini agar test tetap menguji alur lama walau suite dijalankan dengan
 * PAYMENT_ENABLED=true (alur Midtrans diuji di MidtransSnapPaymentTest / MidtransWebhookTest).
 *
 * Dipanggil otomatis oleh Laravel (setUp{NamaTrait}) setelah aplikasi test dibuat.
 */
trait UsesDummyPayments
{
    protected function setUpUsesDummyPayments(): void
    {
        config(['payment.enabled' => false, 'payment.default' => 'dummy']);
    }
}
