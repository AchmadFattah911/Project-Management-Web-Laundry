<?php

namespace App\Data;

use App\Models\Checkout;
use InvalidArgumentException;

/**
 * Input transaksi outlet yang sudah tervalidasi. Tidak ada harga/total/saluran di sini:
 * harga selalu dari tarif layanan di server dan saluran selalu outlet.
 */
final readonly class OutletTransactionData
{
    /**
     * @param  list<array{service_id: int, qty: int, item_type: ?string, notes: ?string}>  $items
     */
    public function __construct(
        public ?int $customerId,
        public ?CustomerData $newCustomer,
        public array $items,
        public string $paymentTiming,
        public string $method,
        public ?int $cashReceived = null,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {
        if (($customerId === null) === ($newCustomer === null)) {
            throw new InvalidArgumentException('Pilih pelanggan yang sudah ada atau isi data pelanggan baru.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('Tambahkan minimal satu layanan.');
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        $newCustomer = empty($validated['customer_id'])
            ? CustomerData::fromArray(['name' => $validated['new_customer_name'] ?? '', 'phone' => $validated['new_customer_phone'] ?? '', 'notes' => null])
            : null;

        return new self(
            customerId: empty($validated['customer_id']) ? null : (int) $validated['customer_id'],
            newCustomer: $newCustomer,
            items: array_values(array_map(fn (array $item) => [
                'service_id' => (int) $item['service_id'],
                'qty'        => (int) $item['qty'],
                'item_type'  => $item['item_type'] ?? null,
                'notes'      => isset($item['notes']) && trim($item['notes']) !== '' ? trim($item['notes']) : null,
            ], $validated['items'])),
            paymentTiming: $validated['payment_timing'],
            method: $validated['payment_method'],
            cashReceived: isset($validated['cash_received']) ? (int) $validated['cash_received'] : null,
            reference: $validated['payment_reference'] ?? null,
            notes: isset($validated['notes']) && trim($validated['notes']) !== '' ? trim($validated['notes']) : null,
        );
    }

    public function paysNow(): bool
    {
        return $this->paymentTiming === Checkout::TIMING_NOW;
    }

    /**
     * Isi yang menentukan "request yang sama" untuk idempotency.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'customer_id'  => $this->customerId,
            'new_customer' => $this->newCustomer?->toArray(),
            'items'        => $this->items,
            'timing'       => $this->paymentTiming,
            'method'       => $this->method,
            'cash'         => $this->cashReceived,
            'reference'    => $this->reference,
            'notes'        => $this->notes,
        ];
    }
}
