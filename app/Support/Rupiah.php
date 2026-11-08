<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Nominal uang dalam integer rupiah.
 *
 * Kolom uang di database bertipe decimal(…, 2) dan dibaca Eloquent sebagai string ("35000.00");
 * Midtrans juga mengirim gross_amount sebagai string. Semua perhitungan & perbandingan nominal
 * memakai integer dari sini, tidak pernah float. Rupiah tidak punya pecahan sen, jadi nilai
 * berpecahan dianggap data rusak dan ditolak, bukan dibulatkan diam-diam.
 */
final class Rupiah
{
    /**
     * "35000", "35000.00", "35000.0", 35000 → 35000
     *
     * @throws InvalidArgumentException jika bukan angka, negatif, atau punya pecahan sen
     */
    public static function parse(int|string|float|null $value): int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : throw new InvalidArgumentException("Nominal negatif tidak valid: {$value}");
        }

        if (is_float($value)) {
            // Float hanya diterima jika bilangan bulat persis (mis. hasil cast lama), lalu diproses sebagai string
            $value = floor($value) === $value ? number_format($value, 0, '.', '') : (string) $value;
        }

        $value = trim((string) $value);

        if (! preg_match('/^(\d{1,15})(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Nominal rupiah tidak valid: '{$value}'");
        }

        if (isset($m[2]) && (int) $m[2] !== 0) {
            throw new InvalidArgumentException("Nominal rupiah tidak boleh berpecahan sen: '{$value}'");
        }

        return (int) $m[1];
    }

    /**
     * Untuk disimpan ke kolom decimal: 35000 → "35000.00"
     */
    public static function toDecimal(int $amount): string
    {
        return $amount . '.00';
    }
}
