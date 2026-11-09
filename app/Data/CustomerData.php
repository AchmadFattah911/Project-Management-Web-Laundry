<?php

namespace App\Data;

use App\Support\PhoneNumber;

/**
 * Input data pelanggan yang sudah tervalidasi; nomor HP selalu dalam bentuk ternormalisasi (+62…).
 */
final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $phoneE164,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        $notes = trim((string) ($validated['notes'] ?? ''));

        return new self(
            name: trim((string) $validated['name']),
            phoneE164: PhoneNumber::normalize((string) $validated['phone']),
            notes: $notes === '' ? null : $notes,
        );
    }

    /** Nomor lokal (0812…) untuk kolom users.phone yang dipakai fitur lama. */
    public function localPhone(): string
    {
        return '0' . substr($this->phoneE164, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'phone' => $this->phoneE164, 'notes' => $this->notes];
    }
}
