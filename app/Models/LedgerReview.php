<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Temuan ledger:verify (status perlu_tinjau) untuk ditinjau admin; ledger-nya sendiri tidak diubah.
 */
class LedgerReview extends Model
{
    public const STATUS_OPEN = 'perlu_tinjau';
    public const STATUS_RESOLVED = 'selesai';

    protected $fillable = ['source_type', 'source_id', 'issue', 'detail', 'status', 'resolved_by', 'resolved_at'];

    protected $casts = [
        'detail'      => 'array',
        'resolved_at' => 'datetime',
    ];
}
