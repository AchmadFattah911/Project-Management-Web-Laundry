<?php

namespace App\Http\Requests\Panel;

use App\Data\CustomerData;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tambah / ubah kontak pelanggan. Otorisasi lewat middleware can:pelanggan.create / can:pelanggan.update.
 * Keunikan nomor HP diperiksa ulang di CustomerService (dan dijaga UNIQUE phone_e164 di database).
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\pM\pN\s.,\'\-]+$/u'],
            'phone' => ['required', 'string', 'max:25', function (string $attribute, mixed $value, Closure $fail) {
                if (PhoneNumber::tryNormalize((string) $value) === null) {
                    $fail('Nomor HP tidak valid. Gunakan nomor seluler Indonesia, mis. 0812xxxxxxxx.');
                }
            }],
            'notes' => ['nullable', 'string', 'max:500'],
            // Hanya form tambah: 1 form = 1 pelanggan walau diklik berkali-kali
            'submission_token' => [$this->isMethod('post') ? 'required' : 'nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'phone' => 'nomor HP', 'notes' => 'catatan'];
    }

    public function toData(): CustomerData
    {
        return CustomerData::fromArray($this->validated());
    }
}
