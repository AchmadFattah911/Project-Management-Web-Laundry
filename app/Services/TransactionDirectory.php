<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\VoidRequest;
use App\Support\BusinessCalendar;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Query halaman transaksi panel (tab Outlet / Aplikasi) dengan filter tanggal, status, metode, dan pencarian.
 *
 * Tab Aplikasi hanya menampilkan transaksi online yang LUNAS. Transaksi online yang belum/tidak lunas
 * hanya terlihat lewat mode "Audit pembayaran" (admin, read-only).
 */
class TransactionDirectory
{
    public const TAB_OUTLET = 'outlet';
    public const TAB_APP = 'aplikasi';

    public const OUTLET_STATUSES = [
        'menunggu'     => 'Belum Lunas',
        'lunas'        => 'Lunas',
        'void_pending' => 'Void Diajukan',
        'void'         => 'Void',
    ];

    public const AUDIT_STATUSES = [
        Checkout::STATUS_AWAITING  => 'Menunggu Pembayaran',
        Checkout::STATUS_EXPIRED   => 'Kedaluwarsa',
        Checkout::STATUS_FAILED    => 'Gagal',
        Checkout::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const APP_METHODS = [
        'bank_transfer' => 'Virtual Account', 'echannel' => 'Mandiri Bill', 'permata' => 'Permata VA',
        'qris' => 'QRIS', 'gopay' => 'GoPay', 'shopeepay' => 'ShopeePay', 'transfer' => 'Transfer', 'e-wallet' => 'E-Wallet',
    ];

    /**
     * @param  array{search?: ?string, from?: ?string, to?: ?string, status?: ?string, method?: ?string}  $filters
     */
    public function paginate(string $tab, array $filters, bool $audit = false, int $perPage = 15): LengthAwarePaginator
    {
        $query = Checkout::query()->with(['user:id,name,phone,phone_e164,created_via', 'orders', 'payments', 'voidRequests', 'creator:id,name']);

        if ($tab === self::TAB_OUTLET) {
            $this->outlet($query->where('channel', Checkout::CHANNEL_OUTLET), $filters);
        } elseif ($audit) {
            $query->where('channel', Checkout::CHANNEL_ONLINE)->where('status', '!=', Checkout::STATUS_PAID)
                ->when(isset(self::AUDIT_STATUSES[$filters['status'] ?? '']), fn ($q) => $q->where('status', $filters['status']));
        } else {
            $this->app($query->where('channel', Checkout::CHANNEL_ONLINE)->where('status', Checkout::STATUS_PAID), $filters);
        }

        $this->search($query, $filters['search'] ?? null);
        $this->dateRange($query, $filters['from'] ?? null, $filters['to'] ?? null);

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Layanan yang bisa dijual di kasir (harga tampil sebagai info; total tetap dihitung server).
     *
     * @return Collection<int, Service>
     */
    public function sellableServices(): Collection
    {
        return Service::where('status', 'tersedia')->orderBy('name')->get(['id', 'name', 'price', 'unit', 'category']);
    }

    protected function outlet(Builder $query, array $filters): void
    {
        $approved = fn ($q) => $q->where('status', VoidRequest::STATUS_APPROVED);

        match ($filters['status'] ?? null) {
            'menunggu'     => $query->where('status', Checkout::STATUS_AWAITING)->whereDoesntHave('voidRequests', $approved),
            'lunas'        => $query->where('status', Checkout::STATUS_PAID)->whereDoesntHave('voidRequests', $approved),
            'void_pending' => $query->whereHas('voidRequests', fn ($q) => $q->where('status', VoidRequest::STATUS_PENDING)),
            'void'         => $query->whereHas('voidRequests', $approved),
            default        => null,
        };

        $query->when(isset(config('finance.outlet_methods')[$filters['method'] ?? '']), fn ($q) => $q->where('outlet_method', $filters['method']));
    }

    protected function app(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;

        $query->when(in_array($status, Order::STATUSES, true) || $status === 'dibatalkan', fn ($q) => $q->whereHas('orders', fn ($o) => $o->where('status', $status)))
            ->when(isset(self::APP_METHODS[$filters['method'] ?? '']), fn ($q) => $q->whereHas('payments', fn ($p) => $p
                ->where('status', Payment::STATUS_PAID)
                ->where(fn ($p) => $p->where('payment_type', $filters['method'])->orWhere(fn ($p) => $p->whereNull('payment_type')->where('method', $filters['method'])))));
    }

    protected function search(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $phone = PhoneNumber::tryNormalize($search);

        $query->where(function ($q) use ($search, $phone) {
            $q->where('code', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")
                ->orWhereHas('orders', fn ($o) => $o->where('order_code', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->when($phone, fn ($u) => $u->orWhere('phone_e164', $phone)));
        });
    }

    /**
     * Filter tanggal = tanggal bisnis (zona outlet) saat transaksi dibuat.
     */
    protected function dateRange(Builder $query, ?string $from, ?string $to): void
    {
        foreach ([[$from, '>=', false], [$to, '<', true]] as [$date, $operator, $nextDay]) {
            if (! $date) {
                continue;
            }

            try {
                $day = BusinessCalendar::parseDate($date);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $query->where('created_at', $operator, ($nextDay ? $day->addDay() : $day)->utc());
        }
    }
}
