<?php

namespace App\Data;

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Filter laporan keuangan: saluran (gabungan/outlet/online), granularitas bucket (harian/mingguan/bulanan),
 * dan rentang tanggal bisnis. Tanpa rentang kustom: bulan berjalan (harian), 12 minggu terakhir (mingguan),
 * atau tahun berjalan (bulanan). Rentang selalu dibulatkan ke batas bucket (minggu = Senin s.d. Minggu).
 */
final readonly class ReportFilter
{
    public const CHANNELS = ['gabungan' => 'Gabungan', 'outlet' => 'Outlet', 'online' => 'Online'];

    public const MAX_BUCKETS = 400;

    public function __construct(
        public string $channel,
        public string $bucket,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public bool $custom,
    ) {}

    /**
     * @param  array<string, mixed>  $input  kanal, periode, dari, sampai
     */
    public static function fromArray(array $input): self
    {
        $channel = array_key_exists($input['kanal'] ?? '', self::CHANNELS) ? $input['kanal'] : 'gabungan';
        $bucket = in_array($input['periode'] ?? '', BusinessCalendar::PERIODS, true) ? $input['periode'] : 'harian';
        $today = BusinessCalendar::parseDate(BusinessCalendar::today());

        $custom = ! empty($input['dari']) && ! empty($input['sampai']);

        if ($custom) {
            $from = BusinessCalendar::parseDate((string) $input['dari']);
            $to = BusinessCalendar::parseDate((string) $input['sampai']);

            if ($to->lessThan($from)) {
                throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal awal.');
            }
        } else {
            [$from, $to] = match ($bucket) {
                'harian'   => [$today->startOfMonth(), $today->endOfMonth()->startOfDay()],
                'mingguan' => [$today->subWeeks(11), $today],
                'bulanan'  => [$today->startOfYear(), $today->endOfYear()->startOfDay()],
            };
        }

        // Bulatkan ke batas bucket agar bucket pertama/terakhir utuh
        $from = BusinessCalendar::parseDate(BusinessCalendar::bucketStart($bucket, $from));
        $to = match ($bucket) {
            'mingguan' => $to->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            'bulanan'  => $to->endOfMonth()->startOfDay(),
            default    => $to,
        };

        $filter = new self($channel, $bucket, $from, $to, $custom);

        if (count($filter->bucketKeys()) > self::MAX_BUCKETS) {
            throw new InvalidArgumentException('Rentang terlalu panjang untuk periode ini. Pilih periode mingguan/bulanan atau persempit tanggal.');
        }

        return $filter;
    }

    /**
     * Awal setiap bucket dalam rentang (Y-m-d), termasuk bucket tanpa transaksi.
     *
     * @return list<string>
     */
    public function bucketKeys(): array
    {
        $keys = [];
        $cursor = $this->from;

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $keys[] = $cursor->toDateString();
            $cursor = match ($this->bucket) {
                'harian'   => $cursor->addDay(),
                'mingguan' => $cursor->addWeek(),
                'bulanan'  => $cursor->addMonthNoOverflow(),
            };
        }

        return $keys;
    }

    public function bucketLabel(string $key): string
    {
        $date = BusinessCalendar::parseDate($key);

        return match ($this->bucket) {
            'harian'   => $date->locale('id')->translatedFormat('d M'),
            'mingguan' => $date->format('d/m') . '–' . $date->addDays(6)->format('d/m'),
            'bulanan'  => $date->locale('id')->translatedFormat('M Y'),
        };
    }

    /**
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return ['kanal' => $this->channel, 'periode' => $this->bucket, 'dari' => $this->from->toDateString(), 'sampai' => $this->to->toDateString()];
    }
}
