<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tutup shift: uang fisik per pecahan (denominations[100000]=3, ...) atau total saja.
 */
class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'denominations' => array_filter((array) $this->input('denominations', []), fn ($count) => $count !== null && $count !== '' && (int) $count !== 0),
        ]);
    }

    public function rules(): array
    {
        $allowed = array_map('strval', config('finance.denominations', []));

        $byDenomination = $this->input('mode') === 'pecahan';

        return [
            'mode'             => ['required', Rule::in(['pecahan', 'total'])],
            'denominations'    => [$byDenomination ? 'required' : 'nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) use ($allowed) {
                foreach (array_keys((array) $value) as $key) {
                    if (! in_array((string) $key, $allowed, true)) {
                        $fail('Pecahan uang tidak dikenal.');
                    }
                }
            }],
            'denominations.*'  => ['integer', 'min:0', 'max:100000'],
            'counted_total'    => [$byDenomination ? 'nullable' : 'required', 'integer', 'min:0', 'max:1000000000'],
            'note'             => ['nullable', 'string', 'max:500'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['denominations' => 'jumlah per pecahan', 'counted_total' => 'total uang fisik'];
    }

    /** @return array<string, int>|null */
    public function denominations(): ?array
    {
        return $this->validated('mode') === 'pecahan' ? array_map('intval', $this->validated('denominations') ?? []) : null;
    }

    public function countedTotal(): ?int
    {
        return $this->validated('mode') === 'total' ? (int) $this->validated('counted_total') : null;
    }
}
