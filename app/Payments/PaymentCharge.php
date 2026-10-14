<?php

namespace App\Payments;

use Carbon\CarbonInterface;

/**
 * Hasil pembuatan tagihan dari gateway.
 */
final class PaymentCharge
{
    public function __construct(
        public readonly string $reference,
        public readonly string $checkoutUrl,
        public readonly ?CarbonInterface $expiresAt = null,
        public readonly array $meta = [],
    ) {}
}
