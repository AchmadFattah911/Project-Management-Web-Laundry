<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi mentah dari gateway (disimpan sebelum diverifikasi). Lihat MidtransNotificationService.
 */
class PaymentNotificationLog extends Model
{
    protected $table = 'payment_notifications';

    protected $fillable = [
        'gateway',
        'order_id',
        'transaction_id',
        'transaction_status',
        'fraud_status',
        'gross_amount',
        'signature_valid',
        'result',
        'payment_id',
        'ip',
        'raw_body',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed_at'    => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
