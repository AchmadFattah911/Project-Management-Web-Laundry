<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Request dengan idempotency key yang tidak boleh diproses (lagi).
 */
class IdempotencyException extends RuntimeException
{
    /** Key yang sama sedang diproses request lain (klik ganda / request paralel). */
    public const IN_PROGRESS = 'in_progress';

    /** Key yang sama dipakai untuk isi request yang berbeda / milik user lain. */
    public const MISMATCH = 'mismatch';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS, 'Pembayaran Anda sedang diproses. Tunggu beberapa detik lalu muat ulang halaman.');
    }

    public static function mismatch(): self
    {
        return new self(self::MISMATCH, 'Kode permintaan pembayaran sudah dipakai untuk transaksi lain. Muat ulang halaman lalu coba lagi.');
    }

    public function httpStatus(): int
    {
        return $this->reason === self::IN_PROGRESS ? 409 : 422;
    }
}
