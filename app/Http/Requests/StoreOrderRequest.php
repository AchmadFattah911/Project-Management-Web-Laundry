<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
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
     * Member terdaftar: nama dan nomor selalu dari akunnya, isian manual diabaikan
     * agar pesanan tidak bisa milik akun A tetapi tercatat atas nama B.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_phone'    => preg_replace('/[\s\-]/', '', (string) $this->input('customer_phone')) ?: null,
            'payment_reference' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('payment_reference'))) ?: null,
        ]);

        if ($this->filled('user_id')) {
            $this->merge(['customer_name' => null, 'customer_phone' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * Total/harga TIDAK boleh diterima dari input.
     */
    public function rules(): array
    {
        return [
            'service_id'     => ['required', Rule::exists('services', 'id')->where('status', 'tersedia')],
            'machine_qty'    => ['required', 'integer', 'min:1', 'max:20'],
            'item_type'      => ['nullable', 'string', Rule::in(array_keys(config('laundry.item_types', [])))],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'customer_name'  => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pM\pN\s.,\'\-]+$/u'],
            'customer_phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            // Hanya akun pelanggan: pesanan tidak boleh ditempelkan ke akun staf
            'user_id'        => ['nullable', Rule::exists('users', 'id')->where('role', UserRole::PELANGGAN->value)],
            // Bayar dulu baru pesan: pesanan kasir selalu tercatat lunas dengan metode ini
            'payment_method' => ['required', 'string', Rule::in(PaymentService::OFFLINE_METHODS)],
            'cash_received'  => ['exclude_unless:payment_method,cash', 'required', 'integer', 'min:1', 'max:100000000'],
            'payment_reference' => ['exclude_if:payment_method,cash', 'required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9\-\/.]*$/'],
            // 1 form = 1 token: kirim ulang atau klik ganda tidak membuat pesanan kedua
            'submission_token' => ['required', 'uuid'],
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
            'service_id.exists'    => 'Layanan yang dipilih tidak valid atau sedang tidak tersedia.',
            'user_id.exists'       => 'Pesanan hanya bisa dibuat untuk akun pelanggan.',
            'customer_name.regex'  => 'Nama pelanggan mengandung karakter yang tidak diizinkan.',
            'customer_phone.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'payment_method.required' => 'Pilih metode pembayaran. Pesanan harus dibayar saat dibuat.',
            'cash_received.required' => 'Isi uang yang diterima dari pelanggan.',
            'cash_received.integer'  => 'Uang diterima harus berupa angka bulat.',
            'cash_received.max'      => 'Uang diterima terlalu besar, periksa kembali nominalnya.',
            'payment_reference.required' => 'Nomor bukti transaksi wajib diisi untuk pembayaran non-tunai.',
            'payment_reference.regex'    => 'Nomor bukti hanya boleh berisi huruf, angka, tanda hubung, titik, dan garis miring.',
            'submission_token.required'  => 'Form kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            'submission_token.uuid'      => 'Form kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        ];
    }
}
