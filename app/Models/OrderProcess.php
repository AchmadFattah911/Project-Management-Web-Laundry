<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProcess extends Model
{
    protected $fillable = [
        'order_id',
        'process',
        'status',
        'updated_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
