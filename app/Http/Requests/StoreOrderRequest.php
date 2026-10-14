<?php

namespace App\Http\Requests;

use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * Total/harga TIDAK boleh diterima dari input.
     */
    public function rules(): array
    {
        return [
            'service_id'     => ['required', 'exists:services,id'],
            'machine_qty'    => ['required', 'integer', 'min:1', 'max:20'],
            'item_type'      => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'customer_name'  => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:25'],
            'user_id'        => ['nullable', 'exists:users,id'],
            // Metode pembayaran yang dicatat petugas kasir
            'payment_method' => ['nullable', 'string', Rule::in(PaymentService::OFFLINE_METHODS)],
            'is_paid'        => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation logic untuk walk-in customer:
     * Wajib salah satu (customer_name atau customer_phone) jika user_id kosong.
     */
    public function withValidator($validator): void
    {
        // Dipakai kasir admin/pegawai. Pelanggan memesan lewat keranjang (/pesan, AddToCartRequest).
        $validator->after(function ($validator) {
            $hasUserId = !empty($this->user_id);
            $hasWalkInName = !empty(trim((string)$this->customer_name));
            $hasWalkInPhone = !empty(trim((string)$this->customer_phone));

            if (!$hasUserId && !$hasWalkInName && !$hasWalkInPhone) {
                $validator->errors()->add('customer_name', 'Nama pelanggan atau nomor telepon wajib diisi untuk pemesanan walk-in (jika user_id kosong).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'service_id.required'  => 'Silakan pilih layanan laundry.',
            'service_id.exists'    => 'Layanan yang dipilih tidak valid.',
            'machine_qty.required' => 'Jumlah mesin wajib diisi.',
            'machine_qty.integer'  => 'Jumlah mesin harus berupa angka.',
            'machine_qty.min'      => 'Jumlah mesin minimal 1 unit.',
            'machine_qty.max'      => 'Jumlah mesin maksimal 20 unit per pesanan.',
        ];
    }
}
