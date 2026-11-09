<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pelunasan transaksi outlet "bayar saat ambil". Nominal selalu total transaksi dari server.
 */
class SettleOutletTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_reference' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('payment_reference'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        $isCash = $this->input('payment_method') === 'cash';

        return [
            'payment_method'    => ['required', Rule::in(array_keys(config('finance.outlet_methods', [])))],
            'cash_received'     => $isCash ? ['required', 'integer', 'min:1', 'max:100000000'] : ['exclude'],
            'payment_reference' => $isCash ? ['exclude'] : ['required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9\-\/.]*$/'],
            'submission_token'  => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_method' => 'metode bayar', 'cash_received' => 'uang diterima', 'payment_reference' => 'nomor bukti'];
    }
}
