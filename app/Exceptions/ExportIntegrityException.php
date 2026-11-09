<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sheet Kontrol export menemukan selisih ≠ 0: file tidak diterbitkan.
 */
class ExportIntegrityException extends RuntimeException
{
    /**
     * @param  array<string, int>  $differences
     */
    public function __construct(string $message, public readonly array $differences = [])
    {
        parent::__construct($message);
    }
}
