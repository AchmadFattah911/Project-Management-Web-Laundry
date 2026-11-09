<?php

namespace App\Support;

use App\Models\Checkout;
use App\Models\Payment;
use App\Models\VoidRequest;
use App\Services\LedgerService;
use App\Services\TransactionDirectory;

/**
 * Label & warna badge untuk transaksi di panel kasir (tanpa query: relasi sudah di-eager load).
 */
final class TransactionLabels
{
    /**
     * @return array{0: string, 1: string} [label, variant badge]
     */
    public static function status(Checkout $checkout): array
    {
        $voids = $checkout->voidRequests;

        return match (true) {
            $voids->contains('status', VoidRequest::STATUS_APPROVED) => ['Void', 'danger'],
            $voids->contains('status', VoidRequest::STATUS_PENDING)  => ['Void Diajukan', 'warning'],
            $checkout->isPaid()                                       => ['Lunas', 'success'],
            $checkout->isAwaitingPayment() && $checkout->isOutlet()   => ['Bayar Saat Ambil', 'warning'],
            default => [TransactionDirectory::AUDIT_STATUSES[$checkout->status] ?? ucfirst($checkout->status), $checkout->isAwaitingPayment() ? 'warning' : 'danger'],
        };
    }

    public static function method(Checkout $checkout): string
    {
        $paid = $checkout->payments->firstWhere('status', Payment::STATUS_PAID);

        if ($checkout->isOutlet()) {
            $key = $paid ? LedgerService::paymentMethodOf($paid) : $checkout->outlet_method;

            return config("finance.outlet_methods.{$key}.label", $key ?? '-');
        }

        $key = $paid ? LedgerService::paymentMethodOf($paid) : $checkout->payments->sortByDesc('id')->first()?->payment_type;

        return TransactionDirectory::APP_METHODS[$key] ?? ($key ?: '-');
    }

    public static function rupiah(int|string|null $value): string
    {
        return 'Rp ' . number_format(Rupiah::parse($value ?? 0), 0, ',', '.');
    }
}
