<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris ledger keuangan (append-only). Dibuat hanya oleh App\Services\LedgerService.
 */
class FinancialEntry extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    public const TYPE_SALE = 'sale';
    public const TYPE_VOID_REVERSAL = 'void_reversal';
    public const TYPE_REFUND = 'refund';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_GATEWAY_FEE = 'gateway_fee';

    public const TYPES = [self::TYPE_SALE, self::TYPE_VOID_REVERSAL, self::TYPE_REFUND, self::TYPE_EXPENSE, self::TYPE_GATEWAY_FEE];

    public const TYPE_LABELS = [
        self::TYPE_SALE          => 'Penjualan',
        self::TYPE_VOID_REVERSAL => 'Void',
        self::TYPE_REFUND        => 'Refund',
        self::TYPE_EXPENSE       => 'Pengeluaran',
        self::TYPE_GATEWAY_FEE   => 'Biaya Gateway (estimasi)',
    ];

    public const CHANNEL_OUTLET = 'outlet';
    public const CHANNEL_ONLINE = 'online';

    public const SOURCE_PAYMENT = 'payment';
    public const SOURCE_VOID = 'void_request';
    public const SOURCE_REFUND = 'refund';
    public const SOURCE_EXPENSE = 'expense';

    protected $fillable = [
        'entry_type',
        'channel',
        'source_type',
        'source_id',
        'amount',
        'payment_method',
        'occurred_at',
        'business_date',
        'shift_id',
        'checkout_id',
        'order_id',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'occurred_at'   => 'datetime',
        'business_date' => 'date',
    ];

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
