<?php

namespace App\Models;

use App\Data\ReportFilter;
use Illuminate\Database\Eloquent\Model;

class FinanceExport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_QUEUED     => 'Dalam antrean',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_DONE       => 'Siap diunduh',
        self::STATUS_FAILED     => 'Gagal',
    ];

    protected $fillable = ['user_id', 'filters', 'status', 'path', 'rows', 'error', 'completed_at'];

    protected $casts = [
        'filters'      => 'array',
        'rows'         => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function filter(): ReportFilter
    {
        return ReportFilter::fromArray($this->filters);
    }

    public function fileName(): string
    {
        $f = $this->filters;

        return 'laporan-keuangan-' . ($f['kanal'] ?? 'gabungan') . '-' . ($f['dari'] ?? '') . '-sd-' . ($f['sampai'] ?? '') . '.xlsx';
    }
}
