<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Nomor HP Indonesia dalam format E.164 (+62…), dipakai sebagai kunci unik pelanggan.
 * "0812-3456-7890", "62812…", "+62 812…", "812…" → "+628123456789".
 */
final class PhoneNumber
{
    public static function normalize(?string $value): string
    {
        return self::tryNormalize($value)
            ?? throw new InvalidArgumentException('Nomor HP tidak valid. Gunakan nomor seluler Indonesia, mis. 0812xxxxxxxx.');
    }

    public static function tryNormalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        $local = match (true) {
            str_starts_with($digits, '62') => substr($digits, 2),
            str_starts_with($digits, '0')  => substr($digits, 1),
            default                        => $digits,
        };

        // Seluler Indonesia: 8xx, total 9-13 digit setelah kode negara
        if (! preg_match('/^8\d{8,12}$/', $local)) {
            return null;
        }

        return '+62' . $local;
    }

    /**
     * Untuk tampilan: "+628123456789" → "0812-3456-789"
     */
    public static function display(?string $e164): string
    {
        if (! $e164 || ! str_starts_with($e164, '+62')) {
            return (string) $e164;
        }

        $local = '0' . substr($e164, 3);

        return implode('-', str_split($local, 4));
    }
}
