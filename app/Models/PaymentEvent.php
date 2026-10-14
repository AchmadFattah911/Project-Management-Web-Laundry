<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan notifikasi gateway yang sudah diterapkan. UNIQUE(gateway, event_id) menjamin
 * notifikasi yang dikirim ulang oleh gateway hanya diproses satu kali.
 */
class PaymentEvent extends Model
{
    protected $fillable = [
        'gateway',
        'event_id',
        'payment_id',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
