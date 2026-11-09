<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosedPeriod extends Model
{
    protected $fillable = ['period_start', 'period_end', 'note', 'closed_by'];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
