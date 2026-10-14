<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Payments\InvalidPaymentNotification;
use App\Payments\PaymentCharge;
use App\Payments\PaymentNotification;

/**
 * Kontrak untuk setiap payment gateway (Dummy sekarang; Midtrans/Xendit/dll nanti).
 *
 * Controller & PaymentService hanya bergantung pada interface ini, sehingga mengganti
 * gateway cukup dengan membuat class baru + mendaftarkannya di config/payment.php.
 */
interface PaymentGateway
{
    /**
     * Nama gateway yang disimpan di kolom payments.gateway & dipakai di URL webhook.
     */
    public function name(): string;

    /**
     * Membuat tagihan di sisi gateway untuk pembayaran yang sudah tersimpan (status pending).
     * Harus mengembalikan reference unik + URL halaman pembayaran.
     *
     * $idempotencyKey WAJIB diteruskan ke gateway (mis. order_id Midtrans / header X-IDEMPOTENCY-KEY Xendit):
     * jika request dengan key yang sama dikirim ulang (timeout, retry), gateway mengembalikan tagihan yang sama,
     * bukan membuat tagihan kedua.
     */
    public function createCharge(Payment $payment, string $idempotencyKey): PaymentCharge;

    /**
     * Memverifikasi keaslian notifikasi (signature) lalu menerjemahkannya ke format standar.
     *
     * @param  array<string, string>  $headers  header request dengan key huruf kecil
     *
     * @throws InvalidPaymentNotification jika signature salah atau payload tidak valid
     */
    public function parseNotification(string $rawBody, array $headers): PaymentNotification;
}
