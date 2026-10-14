<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * Notifikasi status pembayaran dari gateway yang sudah diverifikasi & distandarkan.
 */
final class PaymentNotification
{
    public const ALLOWED_STATUSES = [
        Payment::STATUS_PENDING,
        Payment::STATUS_PAID,
        Payment::STATUS_FAILED,
        Payment::STATUS_EXPIRED,
    ];

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly int $amountInCents,
        public readonly array $raw = [],
        // ID unik notifikasi dari gateway; notifikasi dengan ID yang sama hanya diproses sekali
        public readonly ?string $eventId = null,
    ) {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidPaymentNotification("Status pembayaran '{$status}' tidak dikenali.");
        }
    }
}
