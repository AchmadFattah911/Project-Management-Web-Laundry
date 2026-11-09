<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit transaksi outlet sebelum lunas: jumlah mesin per item + catatan. Harga tetap dari server.
 */
class UpdateOutletTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty'   => ['required', 'array', 'min:1'],
            'qty.*' => ['required', 'integer', 'min:1', 'max:' . (int) config('laundry.max_machine_qty', 20)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['qty.*' => 'jumlah mesin'];
    }
}
