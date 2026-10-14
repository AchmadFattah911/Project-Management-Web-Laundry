<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Order extends Model
{
    protected $fillable = [
        'order_code',
        'checkout_id',
        'user_id',
        'service_id',
        'customer_name',
        'customer_phone',
        'machine_qty',
        'unit_price',
        'total_price',
        'item_type',
        'notes',
        'status',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_SIAP_DIAMBIL = 'siap_diambil';
    public const STATUS_SELESAI = 'selesai';

    /**
     * Alur status pesanan: pending → diproses → siap_diambil → selesai.
     * "selesai" = sudah dilaundry DAN sudah diambil pelanggan.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DIPROSES,
        self::STATUS_SIAP_DIAMBIL,
        self::STATUS_SELESAI,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING      => 'Menunggu',
        self::STATUS_DIPROSES     => 'Diproses',
        self::STATUS_SIAP_DIAMBIL => 'Siap Diambil',
        self::STATUS_SELESAI      => 'Selesai',
    ];

    /**
     * Pengelompokan status untuk tab riwayat pelanggan (tidak ada status yang masuk dua tab).
     * Di proses = belum diambil (termasuk siap diambil), Selesai = sudah dilaundry & sudah diambil.
     */
    public const STATUS_GROUPS = [
        'diproses' => [self::STATUS_PENDING, self::STATUS_DIPROSES, self::STATUS_SIAP_DIAMBIL],
        'selesai'  => [self::STATUS_SELESAI],
    ];

    protected $casts = [
        'machine_qty' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Filter berdasarkan grup tab ('semua' / grup tidak dikenal = tanpa filter).
     * Dipanggil via Order::statusGroup('diproses')
     */
    public function scopeStatusGroup(Builder $query, ?string $group): Builder
    {
        $statuses = self::STATUS_GROUPS[$group] ?? null;

        return $statuses ? $query->whereIn('status', $statuses) : $query;
    }

    /**
     * Filter status pembayaran: 'paid' = lunas, 'pending' = belum lunas (termasuk gagal/kadaluarsa).
     * Order dari keranjang dianggap lunas jika tagihan checkout-nya lunas.
     * Dipanggil via Order::paymentStatus('paid')
     */
    public function scopePaymentStatus(Builder $query, ?string $status): Builder
    {
        $paid = fn ($p) => $p->where('status', Payment::STATUS_PAID);

        return match ($status) {
            'paid'    => $query->where(fn ($q) => $q->whereHas('payments', $paid)->orWhereHas('checkout.payments', $paid)),
            'pending' => $query->whereDoesntHave('payments', $paid)->whereDoesntHave('checkout.payments', $paid),
            default   => $query,
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function orderProcesses()
    {
        return $this->hasMany(OrderProcess::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Transaksi keranjang tempat order ini dibayar (null untuk order walk-in / order lama).
     */
    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * Percobaan pembayaran terbaru. Satu order bisa punya beberapa percobaan
     * (misal gagal/kadaluarsa lalu dibuat ulang), jadi yang ditampilkan selalu yang terakhir.
     */
    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function isPaid(): bool
    {
        // Order dari keranjang dibayar lewat tagihan checkout-nya
        if ($this->checkout_id) {
            return $this->checkoutPayments()->contains('status', Payment::STATUS_PAID);
        }

        if ($this->relationLoaded('payments')) {
            return $this->payments->contains('status', Payment::STATUS_PAID);
        }

        return $this->payments()->where('status', Payment::STATUS_PAID)->exists();
    }

    /**
     * Pembayaran yang ditampilkan di UI: yang lunas jika ada, selain itu percobaan terbaru.
     * Memakai relasi payments yang sudah di-eager load agar tidak menambah query per baris.
     */
    public function paymentForDisplay(): ?Payment
    {
        $payments = $this->checkout_id ? $this->checkoutPayments() : $this->payments;

        return $payments->firstWhere('status', Payment::STATUS_PAID)
            ?? $payments->sortByDesc('id')->first();
    }

    /**
     * Tagihan milik checkout order ini. Pakai eager load 'checkout.payments' di halaman daftar.
     */
    protected function checkoutPayments(): Collection
    {
        return $this->checkout?->payments ?? collect();
    }
}
