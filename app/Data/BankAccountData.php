<?php

namespace App\Data;

/**
 * Rekening tujuan refund manual yang diisi pelanggan.
 */
final readonly class BankAccountData
{
    public function __construct(
        public string $bankName,
        public string $accountNumber,
        public string $accountHolder,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): ?self
    {
        if (empty($validated['bank_name'])) {
            return null;
        }

        return new self(
            bankName: trim((string) $validated['bank_name']),
            accountNumber: preg_replace('/\D/', '', (string) $validated['account_number']),
            accountHolder: trim((string) $validated['account_holder']),
        );
    }
}
