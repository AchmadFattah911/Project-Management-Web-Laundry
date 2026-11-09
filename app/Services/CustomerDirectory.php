<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Query baca data pelanggan untuk panel kasir (pencarian, daftar, riwayat transaksi lintas saluran).
 */
class CustomerDirectory
{
    public const STATUSES = ['aktif', 'nonaktif', 'semua'];

    public function paginate(?string $search, string $status = 'aktif', int $perPage = 15): LengthAwarePaginator
    {
        return $this->search($search)
            ->when($status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->withCount(['checkouts', 'orders' => fn ($q) => $q->whereNull('checkout_id')])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Pencarian cepat untuk form transaksi (maks. 10 hasil, hanya pelanggan aktif).
     *
     * @return Collection<int, User>
     */
    public function quickSearch(?string $search): Collection
    {
        if (mb_strlen(trim((string) $search)) < 2) {
            return collect();
        }

        return $this->search($search)->where('is_active', true)->orderBy('name')->limit(10)
            ->get(['id', 'name', 'phone', 'phone_e164', 'created_via']);
    }

    /**
     * Riwayat transaksi pelanggan dari kedua saluran (header checkout) + pesanan tunggal lama.
     *
     * @return array{checkouts: LengthAwarePaginator, legacy: Collection<int, Order>, totals: array<string, int>}
     */
    public function history(User $customer, int $perPage = 10): array
    {
        $checkouts = Checkout::with('orders')
            ->where('user_id', $customer->id)
            ->latest('id')
            ->paginate($perPage, pageName: 'trx')
            ->withQueryString();

        $legacy = Order::with('payments')
            ->where('user_id', $customer->id)
            ->whereNull('checkout_id')
            ->latest('id')
            ->limit(50)
            ->get();

        $totals = Checkout::where('user_id', $customer->id)
            ->selectRaw('channel, COUNT(*) AS n')
            ->groupBy('channel')
            ->pluck('n', 'channel')
            ->map(fn ($n) => (int) $n)
            ->all();

        return ['checkouts' => $checkouts, 'legacy' => $legacy, 'totals' => $totals];
    }

    protected function search(?string $search): Builder
    {
        $search = trim((string) $search);
        $phone = PhoneNumber::tryNormalize($search);

        return User::query()
            ->where('role', UserRole::PELANGGAN->value)
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search, $phone) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->when($phone, fn ($q) => $q->orWhere('phone_e164', $phone));
            }));
    }
}
