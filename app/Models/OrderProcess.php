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

    public function pegawai()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Alias untuk kompatibilitas jika dipanggil
    public function operator()
    {
        return $this->pegawai();
    }
}
