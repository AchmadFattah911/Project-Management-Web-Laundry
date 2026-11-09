<?php

namespace App\Http\Requests;

use App\Data\BankAccountData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pembatalan transaksi online setelah bayar. Data rekening hanya dipakai jika refund harus manual
 * (VA / bank transfer); service yang memutuskan wajib atau tidaknya.
 */
class CancelPaidTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bank = $this->filled('bank_name') || $this->filled('account_number') || $this->filled('account_holder');

        return [
            'reason'         => ['nullable', 'string', 'max:500'],
            'bank_name'      => [$bank ? 'required' : 'nullable', 'string', 'min:2', 'max:100'],
            'account_number' => [$bank ? 'required' : 'nullable', 'string', 'regex:/^[0-9\s\-]{6,30}$/'],
            'account_holder' => [$bank ? 'required' : 'nullable', 'string', 'min:2', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['bank_name' => 'nama bank', 'account_number' => 'nomor rekening', 'account_holder' => 'nama pemilik rekening'];
    }

    public function bankAccount(): ?BankAccountData
    {
        return BankAccountData::fromArray($this->validated());
    }
}
