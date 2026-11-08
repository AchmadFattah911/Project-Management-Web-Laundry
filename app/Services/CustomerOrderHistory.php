<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat pesanan pelanggan sebagai kartu: 1 kartu = 1 transaksi (checkout keranjang),
 * pesanan tunggal lama / walk-in (tanpa checkout) tetap 1 kartu sendiri.
 *
 * Tab:
 *   belum_bayar  transaksi menunggu pembayaran; pesanan lama 'pending' yang belum dibayar
 *   diproses     transaksi lunas yang masih ada pesanan belum selesai; pesanan lama yang sedang dikerjakan
 *   selesai      transaksi lunas yang semua pesanannya selesai; pesanan lama selesai
 *   dibatalkan   transaksi dibatalkan / kedaluwarsa / gagal
 *
 * Semua query dibatasi user yang login (user_id dari sesi, tidak pernah dari request).
 */
class CustomerOrderHistory
{
    public const TABS = [
        'semua'       => 'Semua',
        'belum_bayar' => 'Belum Bayar',
        'diproses'    => 'Diproses',
        'selesai'     => 'Selesai',
        'dibatalkan'  => 'Dibatalkan',
    ];

    public static function normalizeTab(mixed $tab): string
    {
        return is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'semua';
    }

    /**
     * @return LengthAwarePaginator<int, Checkout|Order>
     */
    public function paginate(User $user, string $tab, int $perPage = 10): LengthAwarePaginator
    {
        $page = $this->cards($user, $tab)->paginate($perPage)->withQueryString();

        return $page->setCollection($this->hydrate(collect($page->items())));
    }

    /**
     * @return Collection<int, Checkout|Order>
     */
    public function latest(User $user, string $tab, int $limit): Collection
    {
        return $this->hydrate($this->cards($user, $tab)->limit($limit)->get());
    }

    /**
     * @return array<string, int>
     */
    public function counts(User $user): array
    {
        return collect(array_keys(self::TABS))
            ->mapWithKeys(fn ($tab) => [$tab => $this->checkouts($user, $tab)->count() + $this->standaloneOrders($user, $tab)->count()])
            ->all();
    }

    /**
     * Kelompok tab untuk satu kartu (dipakai label kartu).
     */
    public static function tabFor(Checkout|Order $card): string
    {
        if ($card instanceof Checkout) {
            return match (true) {
                $card->isAwaitingPayment() => 'belum_bayar',
                in_array($card->status, Checkout::CLOSED_UNPAID, true) => 'dibatalkan',
                $card->orders->isNotEmpty() && $card->orders->every(fn (Order $o) => $o->status === Order::STATUS_SELESAI) => 'selesai',
                default => 'diproses',
            };
        }

        return match (true) {
            $card->status === Order::STATUS_SELESAI => 'selesai',
            $card->status === Order::STATUS_PENDING && ! $card->isPaid() => 'belum_bayar',
            default => 'diproses',
        };
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * UNION transaksi + pesanan tanpa transaksi, urut terbaru.
     */
    protected function cards(User $user, string $tab): \Illuminate\Database\Query\Builder
    {
        $union = $this->checkouts($user, $tab)->toBase()
            ->select(['id', 'created_at', DB::raw("'checkout' as card_type")])
            ->unionAll($this->standaloneOrders($user, $tab)->toBase()->select(['id', 'created_at', DB::raw("'order' as card_type")]));

        return DB::query()->fromSub($union, 'cards')->orderByDesc('created_at')->orderByDesc('id');
    }

    protected function checkouts(User $user, string $tab): Builder
    {
        $unfinished = fn ($q) => $q->where('status', '!=', Order::STATUS_SELESAI);

        return Checkout::query()
            ->where('user_id', $user->id)
            ->when($tab === 'belum_bayar', fn ($q) => $q->where('status', Checkout::STATUS_AWAITING))
            ->when($tab === 'diproses', fn ($q) => $q->where('status', Checkout::STATUS_PAID)->whereHas('orders', $unfinished))
            ->when($tab === 'selesai', fn ($q) => $q->where('status', Checkout::STATUS_PAID)->whereHas('orders')->whereDoesntHave('orders', $unfinished))
            ->when($tab === 'dibatalkan', fn ($q) => $q->whereIn('status', Checkout::CLOSED_UNPAID));
    }

    protected function standaloneOrders(User $user, string $tab): Builder
    {
        $paid = fn ($q) => $q->where('status', Payment::STATUS_PAID);

        return Order::query()
            ->where('user_id', $user->id)
            ->whereNull('checkout_id')
            ->when($tab === 'belum_bayar', fn ($q) => $q->where('status', Order::STATUS_PENDING)->whereDoesntHave('payments', $paid))
            // Sudah dikerjakan dianggap berjalan walau tercatat belum lunas (data walk-in lama)
            ->when($tab === 'diproses', fn ($q) => $q->where(fn ($q) => $q
                ->whereIn('status', [Order::STATUS_DIPROSES, Order::STATUS_SIAP_DIAMBIL])
                ->orWhere(fn ($q) => $q->where('status', Order::STATUS_PENDING)->whereHas('payments', $paid))))
            ->when($tab === 'selesai', fn ($q) => $q->where('status', Order::STATUS_SELESAI))
            ->when($tab === 'dibatalkan', fn ($q) => $q->whereRaw('1 = 0'));
    }

    /**
     * Baris UNION (id + jenis) → model lengkap dengan relasi untuk kartu, urutan dipertahankan.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, Checkout|Order>
     */
    protected function hydrate(Collection $rows): Collection
    {
        $checkouts = Checkout::with(['orders.service', 'payments'])
            ->whereIn('id', $rows->where('card_type', 'checkout')->pluck('id'))
            ->get()
            ->keyBy('id');

        $orders = Order::with(['service', 'payments'])
            ->whereIn('id', $rows->where('card_type', 'order')->pluck('id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($row) => $row->card_type === 'checkout' ? $checkouts->get($row->id) : $orders->get($row->id))
            ->filter()
            ->values();
    }
}
