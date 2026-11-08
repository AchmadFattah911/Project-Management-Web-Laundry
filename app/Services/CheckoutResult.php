<?php

namespace App\Services;

use App\Models\Checkout;

/**
 * Hasil submit form checkout. Submit ulang dengan token yang sama tidak pernah membuat transaksi baru:
 * - created  : transaksi baru dibuat
 * - replayed : token & isi sama dengan submit sebelumnya → transaksi yang sudah ada
 * - mismatch : token sama tetapi isi form berbeda → transaksi yang sudah ada, perubahan tidak disimpan
 */
final class CheckoutResult
{
    public const CREATED = 'created';
    public const REPLAYED = 'replayed';
    public const MISMATCH = 'mismatch';

    public function __construct(
        public readonly Checkout $checkout,
        public readonly string $outcome,
    ) {}

    public function wasCreated(): bool
    {
        return $this->outcome === self::CREATED;
    }
}
