<?php

namespace App\Models;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu transaksi pembayaran pelanggan yang mengelompokkan beberapa order dari keranjang.
 * Setiap order tetap 1 layanan (alur kerja pegawai tidak berubah), tetapi dibayar sekali di sini.
 */
class Checkout extends Model
{
    public const STATUS_AWAITING = 'menunggu_pembayaran';
    public const STATUS_PAID = 'lunas';
    public const STATUS_FAILED = 'gagal';
    public const STATUS_EXPIRED = 'kadaluarsa';

    /** Alasan transaksi masih/berhenti menunggu pembayaran, ditampilkan ke pelanggan. */
    public const PENDING_REASONS = ['menunggu_transfer', 'popup_ditutup', 'ditolak', 'kedaluwarsa'];

    protected $fillable = [
        'code',
        'user_id',
        'total_amount',
        'status',
        'payment_deadline',
        'paid_at',
        'pending_reason',
        'contact_phone',
        'drop_off_date',
        'notes',
        'submission_token',
    ];

    protected $casts = [
        'total_amount'     => 'decimal:2',
        'drop_off_date'    => 'date',
        'payment_deadline' => 'datetime',
        'paid_at'          => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Total tagihan dalam integer rupiah (nilai ini yang dikirim ke gateway sebagai gross_amount).
     */
    public function totalRupiah(): int
    {
        return Rupiah::parse($this->total_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Pembayaran yang ditampilkan di UI: yang lunas jika ada, selain itu percobaan terbaru.
     */
    public function paymentForDisplay(): ?Payment
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return $payments->firstWhere('status', Payment::STATUS_PAID)
            ?? $payments->sortByDesc('id')->first();
    }
}
