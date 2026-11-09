<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shift kas kasir: saldo awal laci → tutup dengan hitung uang fisik → diverifikasi admin.
 */
class CashShift extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_LABELS = [
        self::STATUS_OPEN     => 'Buka',
        self::STATUS_CLOSED   => 'Ditutup',
        self::STATUS_VERIFIED => 'Terverifikasi',
    ];

    protected $fillable = [
        'user_id', 'status', 'opened_at', 'opening_balance', 'closed_at', 'expected_cash', 'counted_cash',
        'difference', 'denominations', 'close_note', 'verified_by', 'verified_at', 'verify_note', 'open_token', 'close_token',
    ];

    protected $casts = [
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'verified_at'     => 'datetime',
        'opening_balance' => 'integer',
        'expected_cash'   => 'integer',
        'counted_cash'    => 'integer',
        'difference'      => 'integer',
        'denominations'   => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function entries()
    {
        return $this->hasMany(FinancialEntry::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
