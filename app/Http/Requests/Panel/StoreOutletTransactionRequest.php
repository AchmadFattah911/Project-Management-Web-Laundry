<?php

namespace App\Http\Requests\Panel;

use App\Data\OutletTransactionData;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transaksi outlet baru. Tidak ada field harga/total/saluran: harga dari server, saluran selalu outlet
 * (field `channel` yang dikirim diabaikan karena tidak pernah masuk ke validated()).
 */
class StoreOutletTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_reference' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('payment_reference'))) ?: null,
            'items' => array_values(array_filter((array) $this->input('items', []), fn ($item) => is_array($item) && ! empty($item['service_id']))),
        ]);
    }

    public function rules(): array
    {
        $paysNow = $this->input('payment_timing') === Checkout::TIMING_NOW;
        $method = $this->input('payment_method');
        // Pelanggan lama dipilih → isian "tambah cepat pelanggan" diabaikan
        $newCustomer = $this->filled('customer_id') ? 'exclude' : 'required';

        return [
            'customer_id'        => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::PELANGGAN->value)->where('is_active', true)],
            'new_customer_name'  => [$newCustomer, 'string', 'min:2', 'max:100', 'regex:/^[\pL\pM\pN\s.,\'\-]+$/u'],
            'new_customer_phone' => [$newCustomer, 'string', 'max:25', function (string $a, mixed $value, Closure $fail) {
                if (PhoneNumber::tryNormalize((string) $value) === null) {
                    $fail('Nomor HP pelanggan baru tidak valid.');
                }
            }],
            'items'              => ['required', 'array', 'min:1', 'max:' . (int) config('laundry.cart_max_lines', 10)],
            'items.*.service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('status', 'tersedia')],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:' . (int) config('laundry.max_machine_qty', 20)],
            'items.*.item_type'  => ['nullable', 'string', Rule::in(array_keys(config('laundry.item_types', [])))],
            'items.*.notes'      => ['nullable', 'string', 'max:500'],
            'payment_timing'     => ['required', Rule::in([Checkout::TIMING_NOW, Checkout::TIMING_ON_PICKUP])],
            'payment_method'     => ['required', Rule::in(array_keys(config('finance.outlet_methods', [])))],
            'cash_received'      => $paysNow && $method === 'cash' ? ['required', 'integer', 'min:1', 'max:100000000'] : ['exclude'],
            'payment_reference'  => $paysNow && $method !== 'cash' ? ['required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9\-\/.]*$/'] : ['exclude'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'submission_token'   => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'new_customer_name'  => 'nama pelanggan baru',
            'new_customer_phone' => 'nomor HP pelanggan baru',
            'items'              => 'layanan',
            'items.*.service_id' => 'layanan',
            'items.*.qty'        => 'jumlah mesin',
            'payment_timing'     => 'waktu bayar',
            'payment_method'     => 'metode bayar',
            'cash_received'      => 'uang diterima',
            'payment_reference'  => 'nomor bukti',
        ];
    }

    public function toData(): OutletTransactionData
    {
        return OutletTransactionData::fromArray($this->validated());
    }
}
