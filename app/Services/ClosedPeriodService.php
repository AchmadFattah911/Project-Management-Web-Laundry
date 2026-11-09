<?php

namespace App\Services;

use App\Exceptions\PeriodClosedException;
use App\Models\ClosedPeriod;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tutup buku per rentang tanggal bisnis. Void/refund/pengeluaran yang jatuh di periode terkunci ditolak;
 * koreksi atas transaksi lama selalu dicatat pada tanggal bisnis saat koreksi terjadi (periode berjalan).
 */
class ClosedPeriodService
{
    public function __construct(protected AuditLogger $audit) {}

    public function isClosed(string $businessDate): bool
    {
        return ClosedPeriod::where('period_start', '<=', $businessDate)
            ->where('period_end', '>=', $businessDate)
            ->exists();
    }

    /**
     * @throws PeriodClosedException
     */
    public function assertOpen(string $businessDate, string $what = 'Transaksi'): void
    {
        if ($this->isClosed($businessDate)) {
            throw new PeriodClosedException("{$what} pada tanggal {$businessDate} ditolak karena periode tersebut sudah ditutup buku.");
        }
    }

    public function close(string $start, string $end, User $actor, ?string $note = null): ClosedPeriod
    {
        $from = BusinessCalendar::parseDate($start);
        $to = BusinessCalendar::parseDate($end);

        if ($to->lessThan($from)) {
            throw new InvalidArgumentException('Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        if ($to->toDateString() > BusinessCalendar::today()) {
            throw new InvalidArgumentException('Periode yang belum terjadi tidak bisa ditutup buku.');
        }

        return DB::transaction(function () use ($from, $to, $actor, $note) {
            $period = ClosedPeriod::create([
                'period_start' => $from->toDateString(),
                'period_end'   => $to->toDateString(),
                'note'         => $note,
                'closed_by'    => $actor->id,
            ]);

            $this->audit->log('periode.close', $period, null, $period->only(['period_start', 'period_end', 'note']), $note, $actor);

            return $period;
        });
    }
}
