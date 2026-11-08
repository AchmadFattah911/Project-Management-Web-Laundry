<?php

namespace Tests\Unit;

use App\Support\Rupiah;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    public static function validAmounts(): array
    {
        return [
            'int'                 => [35000, 35000],
            'string bulat'        => ['35000', 35000],
            'decimal db'          => ['35000.00', 35000],
            'gross_amount satu 0' => ['35000.0', 35000],
            'float bulat'         => [35000.0, 35000],
            'spasi di tepi'       => [' 35000.00 ', 35000],
            'nol'                 => ['0.00', 0],
        ];
    }

    #[DataProvider('validAmounts')]
    public function test_parse_nominal_valid(int|string|float $input, int $expected): void
    {
        $this->assertSame($expected, Rupiah::parse($input));
    }

    public static function invalidAmounts(): array
    {
        return [
            'pecahan sen'    => ['35000.50'],
            'float pecahan'  => [35000.5],
            'negatif'        => ['-1000'],
            'int negatif'    => [-1],
            'bukan angka'    => ['abc'],
            'format ribuan'  => ['35.000'],
            'kosong'         => [''],
            'null'           => [null],
            'notasi ilmiah'  => ['3.5e4'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_parse_menolak_nominal_tidak_valid(int|string|float|null $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Rupiah::parse($input);
    }

    public function test_to_decimal(): void
    {
        $this->assertSame('35000.00', Rupiah::toDecimal(35000));
    }
}
