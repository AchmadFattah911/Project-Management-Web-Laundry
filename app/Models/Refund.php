<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    public const METHOD_API = 'midtrans_api';
    public const METHOD_MANUAL = 'manual';

    public const STATUS_PROCESSING = 'diproses';
    public const STATUS_DONE = 'selesai';
    public const STATUS_FAILED = 'gagal';

    public const STATUS_LABELS = [
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_DONE       => 'Selesai',
        self::STATUS_FAILED     => 'Gagal',
    ];

    protected $fillable = [
        'checkout_id', 'payment_id', 'amount', 'method', 'status', 'refund_key', 'reason', 'bank_name', 'account_number',
        'account_holder', 'requested_by', 'processed_by', 'proof_path', 'note', 'attempts', 'last_error', 'gateway_response', 'completed_at',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'attempts'         => 'integer',
        'account_number'   => 'encrypted',
        'gateway_response' => 'array',
        'completed_at'     => 'datetime',
    ];

    protected $hidden = ['account_number'];

    public function checkout()
    {
        return $this->belongsTo(Checkout::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isApi(): bool
    {
        return $this->method === self::METHOD_API;
    }

    /** Nomor rekening tersamar untuk tampilan. */
    public function maskedAccount(): string
    {
        $number = (string) $this->account_number;

        return strlen($number) > 4 ? str_repeat('•', strlen($number) - 4) . substr($number, -4) : $number;
    }
}
