<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Langkah 2 halaman pemesanan: data kontak & rencana antar cucian ke outlet.
 * Isi pesanan (layanan, jumlah, harga) TIDAK diterima dari form ini, melainkan dibaca dari keranjang di server.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "0812-3456 7890" → "081234567890"
        $this->merge([
            'contact_phone' => preg_replace('/[\s\-().]/', '', (string) $this->input('contact_phone')),
        ]);
    }

    public function rules(): array
    {
        $maxDays = (int) config('laundry.drop_off_max_days', 7);

        return [
            'contact_phone'    => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'drop_off_date'    => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:' . now()->addDays($maxDays)->toDateString()],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'agree'            => ['accepted'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        $maxDays = (int) config('laundry.drop_off_max_days', 7);

        return [
            'contact_phone.required'        => 'Nomor HP wajib diisi agar petugas bisa menghubungi Anda.',
            'contact_phone.regex'           => 'Nomor HP tidak valid. Gunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.',
            'drop_off_date.required'        => 'Pilih rencana tanggal antar cucian ke outlet.',
            'drop_off_date.date_format'     => 'Format tanggal tidak valid.',
            'drop_off_date.after_or_equal'  => 'Tanggal antar tidak boleh sebelum hari ini.',
            'drop_off_date.before_or_equal' => "Tanggal antar maksimal {$maxDays} hari dari hari ini.",
            'notes.max'                     => 'Catatan maksimal 1000 karakter.',
            'agree.accepted'                => 'Anda perlu menyetujui Aturan Penggunaan sebelum memesan.',
            'submission_token.required'     => 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.',
            'submission_token.uuid'         => 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.',
        ];
    }
}
