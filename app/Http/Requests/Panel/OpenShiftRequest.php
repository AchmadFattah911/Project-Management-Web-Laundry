<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_balance'  => ['required', 'integer', 'min:0', 'max:100000000'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['opening_balance' => 'saldo awal laci'];
    }
}
