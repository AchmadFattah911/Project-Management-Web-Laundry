<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Tanggal bisnis dalam zona waktu outlet (config laundry.timezone). Waktu disimpan UTC; pengelompokan
 * harian/mingguan/bulanan selalu memakai tanggal bisnis. Minggu = ISO (Senin s.d. Minggu).
 */
final class BusinessCalendar
{
    public const PERIODS = ['harian', 'mingguan', 'bulanan'];

    public static function timezone(): string
    {
        return (string) config('laundry.timezone', 'Asia/Jakarta');
    }

    /** Tanggal bisnis (Y-m-d) dari sebuah waktu. */
    public static function dateOf(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(self::timezone())->toDateString();
    }

    public static function today(): string
    {
        return self::dateOf(now());
    }

    /** Tanggal (Y-m-d) di zona bisnis sebagai CarbonImmutable tengah malam. */
    public static function parseDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());

        if (! $parsed || $parsed->toDateString() !== $date) {
            throw new InvalidArgumentException("Tanggal tidak valid: {$date}");
        }

        return $parsed;
    }

    /**
     * Rentang tanggal bisnis [awal, akhir] (inklusif) untuk periode yang memuat $date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $period, CarbonImmutable $date): array
    {
        return match ($period) {
            'harian'   => [$date->startOfDay(), $date->startOfDay()],
            'mingguan' => [$date->startOfWeek(CarbonInterface::MONDAY)->startOfDay(), $date->endOfWeek(CarbonInterface::SUNDAY)->startOfDay()],
            'bulanan'  => [$date->startOfMonth()->startOfDay(), $date->endOfMonth()->startOfDay()],
            default    => throw new InvalidArgumentException("Periode tidak dikenal: {$period}"),
        };
    }

    /** Awal bucket (Y-m-d) tempat sebuah tanggal bisnis jatuh. */
    public static function bucketStart(string $bucket, CarbonImmutable $date): string
    {
        return match ($bucket) {
            'harian'   => $date->toDateString(),
            'mingguan' => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            'bulanan'  => $date->startOfMonth()->toDateString(),
            default    => throw new InvalidArgumentException("Bucket tidak dikenal: {$bucket}"),
        };
    }
}
