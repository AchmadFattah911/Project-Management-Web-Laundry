<?php

namespace App\Http\Requests;

use App\Services\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form pemesanan multi-layanan: items[n][service_id|qty|item_type|notes].
 * Ketersediaan layanan & batas keranjang dicek ulang di CartService (di dalam transaksi).
 */
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1', 'max:' . CartService::maxLines()],
            'items.*.service_id' => ['required', 'integer'],
            'items.*.qty'        => ['required', 'integer', 'min:0', 'max:' . CartService::maxQty()],
            'items.*.item_type'  => ['required', 'string', Rule::in(CartService::itemTypes())],
            'items.*.notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'             => 'Pilih minimal satu layanan.',
            'items.max'                  => 'Maksimal ' . CartService::maxLines() . ' layanan dalam satu kali tambah.',
            'items.*.service_id.required' => 'Layanan wajib dipilih.',
            'items.*.qty.required'       => 'Jumlah mesin wajib diisi.',
            'items.*.qty.integer'        => 'Jumlah mesin harus berupa angka.',
            'items.*.qty.min'            => 'Jumlah mesin tidak boleh negatif.',
            'items.*.qty.max'            => 'Jumlah mesin maksimal ' . CartService::maxQty() . ' unit.',
            'items.*.item_type.required' => 'Pilih jenis cucian.',
            'items.*.item_type.in'       => 'Jenis cucian tidak valid.',
            'items.*.notes.max'          => 'Catatan maksimal 500 karakter.',
        ];
    }
}
