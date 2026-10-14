<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use MassPrunable;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key',
        'user_id',
        'scope',
        'request_hash',
        'status',
        'payment_id',
        'response',
        'locked_until',
    ];

    protected $casts = [
        'response'     => 'array',
        'locked_until' => 'datetime',
    ];

    /**
     * Key lama dihapus otomatis lewat `php artisan model:prune` (dijadwalkan harian di routes/console.php).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('payment.idempotency.retention_days', 30)));
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
