<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aksi berisiko yang wajib beralasan (nonaktifkan pelanggan, ajukan/tolak void, tolak pengeluaran, ...).
 * Otorisasi lewat middleware can:... pada route-nya.
 */
class ReasonRequest extends FormRequest
{
    public const MIN_LENGTH = 10;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:' . self::MIN_LENGTH, 'max:1000'],
            'submission_token' => ['nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['reason' => 'alasan'];
    }

    public function messages(): array
    {
        return ['reason.min' => 'Alasan wajib diisi minimal ' . self::MIN_LENGTH . ' karakter.'];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
