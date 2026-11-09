<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\FinancialEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kas harian per shift. Satu shift aktif per petugas (UNIQUE active_owner).
 * Uang seharusnya di laci = saldo awal + jumlah entri ledger tunai yang terikat ke shift
 * (penjualan tunai masuk, refund/void tunai keluar).
 */
class ShiftService
{
    public function __construct(protected AuditLogger $audit) {}

    public function activeFor(User $user): ?CashShift
    {
        return CashShift::where('user_id', $user->id)->where('status', CashShift::STATUS_OPEN)->first();
    }

    /**
     * Shift aktif petugas dengan lock baris (dipakai saat transaksi tunai dicatat ke shift).
     */
    public function lockActiveFor(User $user): CashShift
    {
        return CashShift::where('user_id', $user->id)->where('status', CashShift::STATUS_OPEN)->lockForUpdate()->first()
            ?? throw new InvalidArgumentException('Transaksi tunai wajib terikat ke shift kas. Buka shift terlebih dahulu di menu Kas & Shift.');
    }

    public function open(User $user, int $openingBalance, string $token): CashShift
    {
        if ($openingBalance < 0) {
            throw new InvalidArgumentException('Saldo awal laci tidak boleh negatif.');
        }

        if ($existing = CashShift::where('open_token', $token)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $openingBalance, $token) {
                $shift = CashShift::create([
                    'user_id'         => $user->id,
                    'status'          => CashShift::STATUS_OPEN,
                    'opened_at'       => now(),
                    'opening_balance' => $openingBalance,
                    'open_token'      => $token,
                ]);

                $this->audit->log('shift.open', $shift, null, ['opening_balance' => $openingBalance], null, $user);

                return $shift;
            });
        } catch (UniqueConstraintViolationException) {
            if ($existing = CashShift::where('open_token', $token)->first()) {
                return $existing;
            }

            throw new InvalidArgumentException('Anda masih punya shift yang belum ditutup. Tutup shift tersebut sebelum membuka shift baru.');
        }
    }

    /**
     * Ringkasan tunai shift dari ledger.
     *
     * @return array{opening: int, cash_in: int, cash_out: int, expected: int, non_cash: int, transactions: int}
     */
    public function summary(CashShift $shift): array
    {
        $rows = FinancialEntry::where('shift_id', $shift->id)
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash' AND amount > 0 THEN amount ELSE 0 END) AS cash_in")
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash' AND amount < 0 THEN amount ELSE 0 END) AS cash_out")
            ->selectRaw("SUM(CASE WHEN payment_method <> 'cash' AND entry_type = 'sale' THEN amount ELSE 0 END) AS non_cash")
            ->selectRaw("SUM(CASE WHEN entry_type = 'sale' THEN 1 ELSE 0 END) AS transactions")
            ->first();

        $cashIn = (int) ($rows->cash_in ?? 0);
        $cashOut = (int) ($rows->cash_out ?? 0);

        return [
            'opening'      => $shift->opening_balance,
            'cash_in'      => $cashIn,
            'cash_out'     => -$cashOut,
            'expected'     => $shift->opening_balance + $cashIn + $cashOut,
            'non_cash'     => (int) ($rows->non_cash ?? 0),
            'transactions' => (int) ($rows->transactions ?? 0),
        ];
    }

    /**
     * Tutup shift dengan uang fisik di laci, per pecahan (jumlah lembar/keping) atau total.
     *
     * @param  array<int|string, int>|null  $denominations  nominal => jumlah
     */
    public function close(CashShift $shift, User $actor, ?array $denominations, ?int $countedTotal, ?string $note, string $token): CashShift
    {
        return DB::transaction(function () use ($shift, $actor, $denominations, $countedTotal, $note, $token) {
            $locked = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            // Submit ganda form tutup shift: kembalikan hasil tutup yang sama
            if ($locked->close_token === $token) {
                return $locked;
            }

            if ((int) $locked->user_id !== (int) $actor->id) {
                throw new InvalidArgumentException('Shift hanya bisa ditutup oleh petugas pemiliknya.');
            }

            if (! $locked->isOpen()) {
                throw new InvalidArgumentException('Shift ini sudah ditutup.');
            }

            [$counted, $breakdown] = $this->countDrawer($denominations, $countedTotal);
            $expected = $this->summary($locked)['expected'];

            $locked->update([
                'status'        => CashShift::STATUS_CLOSED,
                'closed_at'     => now(),
                'expected_cash' => $expected,
                'counted_cash'  => $counted,
                'difference'    => $counted - $expected,
                'denominations' => $breakdown,
                'close_note'    => $note,
                'close_token'   => $token,
            ]);

            $this->audit->log('shift.close', $locked, ['status' => CashShift::STATUS_OPEN], $locked->only(['expected_cash', 'counted_cash', 'difference', 'denominations']), $note, $actor);

            return $locked;
        });
    }

    public function verify(CashShift $shift, User $admin, ?string $note): CashShift
    {
        return DB::transaction(function () use ($shift, $admin, $note) {
            $locked = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CashShift::STATUS_CLOSED) {
                throw new InvalidArgumentException($locked->isOpen() ? 'Shift masih buka, tutup dulu sebelum diverifikasi.' : 'Shift ini sudah diverifikasi.');
            }

            $locked->update([
                'status'      => CashShift::STATUS_VERIFIED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'verify_note' => $note,
            ]);

            $this->audit->log('shift.verify', $locked, ['status' => CashShift::STATUS_CLOSED], ['status' => CashShift::STATUS_VERIFIED, 'difference' => $locked->difference], $note, $admin);

            return $locked;
        });
    }

    /**
     * @return array{0: int, 1: array<string, int>|null}
     */
    protected function countDrawer(?array $denominations, ?int $countedTotal): array
    {
        $allowed = config('finance.denominations', []);
        $breakdown = [];
        $total = 0;

        foreach ($denominations ?? [] as $value => $count) {
            $value = (int) $value;
            $count = (int) $count;

            if ($count === 0) {
                continue;
            }

            if (! in_array($value, $allowed, true) || $count < 0) {
                throw new InvalidArgumentException('Pecahan uang tidak valid.');
            }

            $breakdown[(string) $value] = $count;
            $total += $value * $count;
        }

        if ($breakdown !== []) {
            return [$total, $breakdown];
        }

        if ($countedTotal === null || $countedTotal < 0) {
            throw new InvalidArgumentException('Isi jumlah uang fisik per pecahan atau totalnya.');
        }

        return [$countedTotal, null];
    }
}
