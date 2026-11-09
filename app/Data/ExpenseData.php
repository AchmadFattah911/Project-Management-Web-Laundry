<?php

namespace App\Data;

use Illuminate\Http\UploadedFile;

final readonly class ExpenseData
{
    public function __construct(
        public int $categoryId,
        public int $amount,
        public string $expenseDate,
        public string $description,
        public UploadedFile $receipt,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            categoryId: (int) $validated['category_id'],
            amount: (int) $validated['amount'],
            expenseDate: (string) $validated['expense_date'],
            description: trim((string) $validated['description']),
            receipt: $validated['receipt'],
        );
    }
}
