<?php

namespace App\Http\Requests\Panel;

/**
 * Pengajuan void: alasan wajib (min. 10 karakter) dan token form agar submit ganda tetap 1 pengajuan.
 */
class StoreVoidRequest extends ReasonRequest
{
    public function rules(): array
    {
        return ['submission_token' => ['required', 'uuid']] + parent::rules();
    }
}
