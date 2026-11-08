<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rekap keuangan untuk audit pegawai. Sumber kebenarannya tabel payments berstatus paid
 * (berdasarkan paid_at), bukan tabel orders: yang dihitung adalah uang yang benar-benar masuk.
 *
 * Batas hari memakai zona waktu outlet (config laundry.report_timezone), bukan zona waktu aplikasi,
 * supaya pembayaran jam 06.00 WIB tidak tercatat di hari sebelumnya.
 */
class FinanceReportService
{
    public const PERIODS = [
        'harian'   => 'Harian',
        'mingguan' => 'Mingguan',
        'bulanan'  => 'Bulanan',
    ];

    public const CHANNELS = [
        'online' => 'Online',
        'kasir'  => 'Kasir Outlet',
        'semua'  => 'Semua',
    ];

    public const METHOD_LABELS = [
        'cash'     => 'Tunai',
        'transfer' => 'Transfer Bank',
        'e-wallet' => 'E-Wallet / QRIS',
    ];

    /**
     * Tempat uang berakhir per metode bayar, dan cara pegawai mencocokkannya dengan kondisi nyata.
     */
    public const DESTINATIONS = [
        'transfer' => ['label' => 'Rekening Bank', 'check' => 'Cocokkan dengan mutasi rekening'],
        'e-wallet' => ['label' => 'E-Wallet / QRIS', 'check' => 'Cocokkan dengan saldo merchant'],
        'cash'     => ['label' => 'Laci Kasir', 'check' => 'Hitung uang tunai di laci'],
    ];

    public static function timezone(): string
    {
        return (string) config('laundry.report_timezone');
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $period, CarbonImmutable $date, string $channel): array
    {
        [$start, $end] = $this->range($period, $date->locale('id'));

        $payments = $this->paidPayments($start, $end)
            ->filter(fn (Payment $payment) => $this->inChannel($payment, $channel));
        $staff = User::whereIn('id', $payments->map(fn (Payment $payment) => $payment->meta['confirmed_by'] ?? null)->filter()->unique())
            ->pluck('name', 'id');

        $transactions = $payments
            ->map(fn (Payment $payment) => $this->transaction($payment, $staff))
            ->values();

        $total = (float) $transactions->sum('amount');
        $refunds = $this->refunds($start, $end);

        return [
            'period'       => $period,
            'channel'      => $channel,
            'date'         => $date,
            'start'        => $start,
            'end'          => $end,
            'label'        => $this->label($period, $start, $end),
            'total'        => $total,
            'count'        => $transactions->count(),
            'average'      => $transactions->isEmpty() ? 0 : $total / $transactions->count(),
            'byDay'        => $this->byDay($transactions, $start, $end),
            'destinations' => $this->destinations($transactions, $channel, $total),
            'sources'      => $this->sources($transactions, $total),
            'cashiers'     => $this->cashiers($transactions, $total),
            'transactions' => $transactions,
            'mismatches'   => $transactions->filter(fn (array $row) => $row['difference'] != 0)->values(),
            // Pembayaran non-tunai tanpa bukti tidak bisa dicocokkan dengan mutasi
            'unproven'     => $transactions->filter(fn (array $row) => $row['method_key'] !== 'cash' && ! $row['reference'])->values(),
            'refunds'      => $refunds,
            'refundTotal'  => (float) $refunds->sum('amount'),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function range(string $period, CarbonImmutable $date): array
    {
        return match ($period) {
            'mingguan' => [$date->startOfWeek(CarbonInterface::MONDAY), $date->endOfWeek(CarbonInterface::SUNDAY)],
            'bulanan'  => [$date->startOfMonth(), $date->endOfMonth()],
            default    => [$date->startOfDay(), $date->endOfDay()],
        };
    }

    protected function label(string $period, CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($period) {
            'mingguan' => $start->translatedFormat('d M') . ' – ' . $end->translatedFormat('d M Y'),
            'bulanan'  => $start->translatedFormat('F Y'),
            default    => $start->translatedFormat('l, d F Y'),
        };
    }

    /**
     * @return Collection<int, Payment>
     */
    protected function paidPayments(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Payment::with(['order.service', 'order.user', 'checkout.orders.service', 'checkout.user'])
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', $this->storedRange($start, $end))
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * Uang yang masuk dua kali untuk transaksi yang sudah lunas. Bukan pendapatan, harus dikembalikan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function refunds(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Payment::with(['order.service', 'order.user', 'checkout.orders.service', 'checkout.user'])
            ->where('status', '!=', Payment::STATUS_PAID)
            ->where('meta->needs_refund', true)
            ->whereBetween('updated_at', $this->storedRange($start, $end))
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Payment $payment) => $this->transaction($payment));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function storedRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $timezone = config('app.timezone');

        return [$start->setTimezone($timezone), $end->setTimezone($timezone)];
    }

    protected function inChannel(Payment $payment, string $channel): bool
    {
        return match ($channel) {
            'online' => $payment->isOnline(),
            'kasir'  => ! $payment->isOnline(),
            default  => true,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function transaction(Payment $payment, ?Collection $staff = null): array
    {
        $orders = $payment->checkout_id
            ? ($payment->checkout?->orders ?? collect())
            : collect([$payment->order])->filter();

        $first = $orders->first();
        $amount = (float) $payment->amount;
        $ordersTotal = (float) $orders->sum('total_price');

        return [
            'code'       => $payment->checkout?->code ?? $first?->order_code ?? '#' . $payment->id,
            'time'       => ($payment->paid_at ?? $payment->updated_at)->setTimezone(self::timezone()),
            'customer'   => $first?->customer_name ?? $payment->checkout?->user?->name ?? $first?->user?->name ?? 'Walk-in',
            'services'   => $this->allocate($orders, $amount, $ordersTotal),
            'method'     => self::METHOD_LABELS[$payment->method] ?? (string) $payment->method,
            'method_key' => (string) $payment->method,
            'channel'    => self::CHANNELS[$payment->isOnline() ? 'online' : 'kasir'],
            'reference'  => $payment->reference,
            'cashier'    => $payment->isOnline() ? null : ($staff?->get($payment->meta['confirmed_by'] ?? null) ?? 'Tidak tercatat'),
            'received'   => $payment->meta['cash_received'] ?? null,
            'change'     => $payment->meta['change'] ?? null,
            'amount'     => $amount,
            'difference' => round($amount - $ordersTotal, 2),
        ];
    }

    /**
     * Semua hari dalam periode tetap muncul walau kosong, supaya hari tanpa pemasukan terlihat saat audit.
     *
     * @return list<array{date: CarbonImmutable, count: int, total: float}>
     */
    protected function byDay(Collection $transactions, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $grouped = $transactions->groupBy(fn (array $row) => $row['time']->format('Y-m-d'));
        $days = [];

        for ($day = $start->startOfDay(); $day <= $end; $day = $day->addDay()) {
            $rows = $grouped->get($day->format('Y-m-d'), collect());

            $days[] = ['date' => $day, 'count' => $rows->count(), 'total' => (float) $rows->sum('amount')];
        }

        return $days;
    }

    /**
     * Membagi uang yang benar-benar diterima ke tiap pesanan sesuai porsi nilainya.
     * Sisa pembulatan masuk ke pesanan terakhir, jadi jumlahnya selalu sama dengan uang yang diterima.
     *
     * @return list<array{name: string, amount: float}>
     */
    protected function allocate(Collection $orders, float $amount, float $ordersTotal): array
    {
        if ($orders->isEmpty()) {
            return [['name' => 'Tanpa pesanan', 'amount' => $amount]];
        }

        $remaining = $amount;
        $last = $orders->count() - 1;

        return $orders->values()->map(function ($order, int $index) use (&$remaining, $orders, $amount, $ordersTotal, $last) {
            $portion = $ordersTotal > 0 ? (float) $order->total_price / $ordersTotal : 1 / $orders->count();
            $allocated = $index === $last ? $remaining : round($amount * $portion, 2);
            $remaining = round($remaining - $allocated, 2);

            return ['name' => $order->service?->name ?? 'Layanan', 'amount' => $allocated];
        })->all();
    }

    /**
     * Uang masuk ke mana. Tempat tanpa pemasukan tetap tampil, supaya tetap ikut dicocokkan saat audit.
     * Tunai hanya ada di kasir, jadi tidak ditampilkan untuk saluran online.
     *
     * @return Collection<int, array{label: string, hint: string, total: float, share: int}>
     */
    protected function destinations(Collection $transactions, string $channel, float $total): Collection
    {
        return collect(array_keys(self::DESTINATIONS))
            ->reject(fn (string $method) => $channel === 'online' && $method === 'cash')
            ->merge($transactions->pluck('method_key'))
            ->unique()
            ->map(function (string $method) use ($transactions, $total) {
                $rows = $transactions->where('method_key', $method);
                $sum = (float) $rows->sum('amount');

                return [
                    'label' => self::DESTINATIONS[$method]['label'] ?? $method,
                    'hint'  => $rows->count() . ' transaksi. ' . (self::DESTINATIONS[$method]['check'] ?? 'Periksa manual'),
                    'total' => $sum,
                    'share' => $this->share($sum, $total),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Uang dari layanan apa. Memakai uang yang dibagi per pesanan (lihat allocate),
     * jadi totalnya selalu sama dengan Total Pemasukan.
     *
     * @return Collection<int, array{label: string, hint: string, total: float, share: int}>
     */
    protected function sources(Collection $transactions, float $total): Collection
    {
        return $transactions->flatMap(fn (array $row) => $row['services'])
            ->groupBy('name')
            ->map(function (Collection $rows, string $name) use ($total) {
                $sum = round((float) $rows->sum('amount'), 2);

                return [
                    'label' => $name,
                    'hint'  => $rows->count() . ' pesanan',
                    'total' => $sum,
                    'share' => $this->share($sum, $total),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Uang yang diterima tiap petugas. Tunai dipisah karena itu yang harus ada di laci kasir.
     *
     * @return Collection<int, array{label: string, hint: string, total: float, share: int}>
     */
    protected function cashiers(Collection $transactions, float $total): Collection
    {
        return $transactions->whereNotNull('cashier')
            ->groupBy('cashier')
            ->map(function (Collection $rows, string $name) use ($total) {
                $sum = (float) $rows->sum('amount');
                $cash = (float) $rows->where('method_key', 'cash')->sum('amount');

                return [
                    'label' => $name,
                    'hint'  => $rows->count() . ' transaksi. Tunai Rp ' . number_format($cash, 0, ',', '.'),
                    'total' => $sum,
                    'share' => $this->share($sum, $total),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    protected function share(float $part, float $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }
}
