<?php

namespace App\Models;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu transaksi pembayaran pelanggan yang mengelompokkan beberapa order dari keranjang.
 * Setiap order tetap 1 layanan (alur kerja pegawai tidak berubah), tetapi dibayar sekali di sini.
 */
class Checkout extends Model
{
    public const CHANNEL_ONLINE = 'online';
    public const CHANNEL_OUTLET = 'outlet';

    public const TIMING_NOW = 'now';
    public const TIMING_ON_PICKUP = 'on_pickup';

    public const STATUS_AWAITING = 'menunggu_pembayaran';
    public const STATUS_PAID = 'lunas';
    public const STATUS_FAILED = 'gagal';
    public const STATUS_EXPIRED = 'kadaluarsa';
    public const STATUS_CANCELLED = 'dibatalkan';

    /** Transaksi yang sudah berhenti tanpa dibayar: boleh "Pesan Lagi". */
    public const CLOSED_UNPAID = [self::STATUS_FAILED, self::STATUS_EXPIRED, self::STATUS_CANCELLED];

    /** Alasan transaksi masih/berhenti menunggu pembayaran, ditampilkan ke pelanggan. */
    public const PENDING_REASONS = ['menunggu_transfer', 'popup_ditutup', 'ditolak', 'kedaluwarsa'];

    protected $fillable = [
        'code',
        'channel',
        'user_id',
        'created_by',
        'payment_timing',
        'outlet_method',
        'shift_id',
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

    /** Petugas yang membuat transaksi outlet. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function voidRequests()
    {
        return $this->hasMany(VoidRequest::class)->latest('id');
    }

    public function isOutlet(): bool
    {
        return $this->channel === self::CHANNEL_OUTLET;
    }

    /** Void disetujui (transaksi asli tidak diubah; lihat void_requests & ledger void_reversal). */
    public function isVoided(): bool
    {
        return $this->relationLoaded('voidRequests')
            ? $this->voidRequests->contains('status', VoidRequest::STATUS_APPROVED)
            : $this->voidRequests()->where('status', VoidRequest::STATUS_APPROVED)->exists();
    }

    /** Transaksi outlet "bayar saat ambil": cucian boleh dikerjakan sebelum lunas, diambil setelah lunas. */
    public function allowsWorkBeforePayment(): bool
    {
        return $this->isOutlet() && $this->payment_timing === self::TIMING_ON_PICKUP && $this->isAwaitingPayment();
    }

    /**
     * Riwayat perubahan status (lihat OrderStateMachine), urut kronologis.
     */
    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('id');
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

    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_AWAITING;
    }

    /**
     * Batas bayar efektif. Transaksi lama (sebelum kolom payment_deadline ada) memakai created_at + masa bayar.
     */
    public function paymentDeadline(): Carbon
    {
        return $this->payment_deadline
            ?? $this->created_at->copy()->addMinutes((int) config('payment.expiry_minutes', 60));
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
