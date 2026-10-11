<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category',
        'price',
        'estimate',
        'status',
        'description',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
