<?php

namespace App\Http\Requests\Panel;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class MergeCustomerRequest extends ReasonRequest
{
    public function rules(): array
    {
        $customer = Rule::exists('users', 'id')->where('role', UserRole::PELANGGAN->value)->where('is_active', true);

        return parent::rules() + [
            'primary_id'   => ['required', 'integer', $customer],
            'duplicate_id' => ['required', 'integer', 'different:primary_id', $customer],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['primary_id' => 'pelanggan utama', 'duplicate_id' => 'pelanggan ganda'];
    }
}
