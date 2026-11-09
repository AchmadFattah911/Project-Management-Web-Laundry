<?php

namespace Tests\Unit;

use App\Support\BusinessCalendar;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function validNumbers(): array
    {
        return [
            ['081234567890', '+6281234567890'],
            ['0812-3456-7890', '+6281234567890'],
            ['+62 812 3456 7890', '+6281234567890'],
            ['6281234567890', '+6281234567890'],
            ['81234567890', '+6281234567890'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_normalisasi_ke_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_nomor_tidak_valid_ditolak(): void
    {
        foreach (['', '12345', '0212345678', 'abc', '0812'] as $bad) {
            $this->assertNull(PhoneNumber::tryNormalize($bad), $bad);
        }

        $this->expectException(InvalidArgumentException::class);
        PhoneNumber::normalize('0812');
    }

    public function test_minggu_iso_senin_sampai_minggu(): void
    {
        // Rabu 11 Nov 2026 → Senin 9 s.d. Minggu 15
        [$start, $end] = BusinessCalendar::range('mingguan', CarbonImmutable::parse('2026-11-11', 'Asia/Jakarta'));
        $this->assertSame(['2026-11-09', '2026-11-15'], [$start->toDateString(), $end->toDateString()]);

        // Minggu 15 Nov masih minggu yang sama
        $this->assertSame('2026-11-09', BusinessCalendar::bucketStart('mingguan', CarbonImmutable::parse('2026-11-15', 'Asia/Jakarta')));
    }
}
