<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoidRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    protected $fillable = [
        'checkout_id', 'requested_by', 'reason', 'status', 'amount', 'decided_by', 'decided_at', 'decision_note', 'submission_token',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'decided_at' => 'datetime',
    ];

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
