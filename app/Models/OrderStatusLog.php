<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu perubahan status transaksi atau pesanan. Hanya ditulis oleh OrderStateMachine, tidak pernah diubah.
 */
class OrderStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'checkout_id',
        'order_id',
        'from_status',
        'to_status',
        'note',
        'actor_type',
        'actor_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
