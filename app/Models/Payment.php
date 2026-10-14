<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const GATEWAY_MANUAL = 'manual';

    protected $fillable = [
        'order_id',
        'checkout_id',
        'amount',
        'method',
        'gateway',
        'reference',
        'idempotency_key',
        'checkout_url',
        'status',
        'paid_at',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'paid_at'    => 'datetime',
        'expires_at' => 'datetime',
        'meta'       => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    /**
     * Notifikasi gateway yang sudah diterapkan ke tagihan ini.
     */
    public function events()
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function needsRefund(): bool
    {
        return (bool) ($this->meta['needs_refund'] ?? false);
    }

    /**
     * Pemilik tagihan: pelanggan dari checkout (transaksi keranjang) atau dari order tunggal.
     */
    public function ownerId(): ?int
    {
        $userId = $this->checkout_id ? $this->checkout?->user_id : $this->order?->user_id;

        return $userId !== null ? (int) $userId : null;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Tagihan pending yang batas waktunya sudah lewat (walau webhook kadaluarsa belum masuk).
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Tagihan online yang masih bisa dibayar pelanggan.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isOverdue();
    }

    /**
     * Status untuk ditampilkan (pending yang lewat batas waktu tampil sebagai expired).
     */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? self::STATUS_EXPIRED : $this->status;
    }

    /**
     * Nominal dalam satuan sen agar perbandingan nominal tidak terkena error pembulatan float.
     */
    public function amountInCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }
}
