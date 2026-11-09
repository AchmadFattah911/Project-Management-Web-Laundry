<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\FinancialEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Support\BusinessCalendar;
use App\Support\Rupiah;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya penulis ledger (financial_entries). Dipanggil di dalam transaksi DB yang sama dengan
 * kejadian sumbernya. Idempoten: UNIQUE(entry_type, source_type, source_id) membuat pemanggilan ulang
 * (retry, webhook ganda, backfill) mengembalikan baris yang sudah ada.
 *
 * Pengakuan:
 *   sale          saat pembayaran berstatus paid (amount positif)
 *   gateway_fee   bersama sale online lewat Midtrans (estimasi dari config, negatif)
 *   void_reversal saat void disetujui (negatif, tanggal persetujuan)
 *   refund        saat refund selesai (negatif, tanggal selesai)
 *   expense       saat pengeluaran disetujui (negatif, tanggal pengeluaran)
 */
class LedgerService
{
    public function __construct(protected ClosedPeriodService $periods) {}

    /**
     * Penjualan dari pembayaran yang lunas, plus estimasi biaya gateway untuk pembayaran Midtrans.
     */
    public function recordSale(Payment $payment, ?int $shiftId = null): FinancialEntry
    {
        if ($payment->status !== Payment::STATUS_PAID) {
            throw new LogicException("Payment #{$payment->id} belum lunas, tidak bisa dicatat sebagai penjualan.");
        }

        $occurredAt = $payment->paid_at ?? now();
        [$checkoutId, $orderId, $channel] = $this->ownerOf($payment);

        $sale = $this->append([
            'entry_type'     => FinancialEntry::TYPE_SALE,
            'channel'        => $channel,
            'source_type'    => FinancialEntry::SOURCE_PAYMENT,
            'source_id'      => $payment->id,
            'amount'         => Rupiah::parse($payment->amount),
            'payment_method' => self::paymentMethodOf($payment),
            'occurred_at'    => $occurredAt,
            // Uang yang sudah masuk tidak bisa ditolak: jika tanggalnya sudah tutup buku, diakui di periode berjalan
            'business_date'  => $this->openDateFor($occurredAt),
            'shift_id'       => $shiftId ?? ($payment->meta['shift_id'] ?? null),
            'checkout_id'    => $checkoutId,
            'order_id'       => $orderId,
            'created_by'     => $payment->meta['confirmed_by'] ?? null,
        ]);

        $fee = $this->gatewayFeeFor($payment);

        if ($fee > 0) {
            $this->append([
                'entry_type'     => FinancialEntry::TYPE_GATEWAY_FEE,
                'source_type'    => FinancialEntry::SOURCE_PAYMENT,
                'amount'         => -$fee,
            ] + array_intersect_key($sale->getAttributes(), array_flip([
                'channel', 'source_id', 'payment_method', 'occurred_at', 'business_date', 'checkout_id', 'order_id',
            ])));
        }

        return $sale;
    }

    /**
     * Estimasi biaya gateway (integer rupiah) untuk pembayaran Midtrans: flat + round(amount × bps / 10000).
     */
    public function gatewayFeeFor(Payment $payment): int
    {
        if ($payment->gateway !== 'midtrans') {
            return 0;
        }

        $fees = config('finance.gateway_fees', []);
        $rule = $fees[$payment->payment_type] ?? $fees['default'] ?? ['flat' => 0, 'bps' => 0];
        $amount = Rupiah::parse($payment->amount);

        return (int) $rule['flat'] + intdiv($amount * (int) $rule['bps'] + 5000, 10000);
    }

    /**
     * Metode bayar untuk laporan: payment_type gateway (qris, gopay, bank_transfer, ...) atau metode kasir.
     */
    public static function paymentMethodOf(Payment $payment): string
    {
        return $payment->payment_type
            ?: ($payment->meta['outlet_method'] ?? null)
            ?: (string) $payment->method;
    }

    /**
     * Tambah satu baris ledger. Baris dengan sumber yang sama sudah ada → baris itu dikembalikan (idempoten).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): FinancialEntry
    {
        $key = [
            'entry_type'  => $attributes['entry_type'],
            'source_type' => $attributes['source_type'],
            'source_id'   => $attributes['source_id'],
        ];

        if ($existing = FinancialEntry::where($key)->first()) {
            return $existing;
        }

        try {
            // Savepoint: bentrok UNIQUE dari request paralel tidak menggagalkan transaksi pemanggil
            return DB::transaction(fn () => FinancialEntry::create($attributes));
        } catch (UniqueConstraintViolationException) {
            return FinancialEntry::where($key)->firstOrFail();
        }
    }

    /**
     * Tanggal bisnis kejadian; jika periodenya sudah ditutup, dipindah ke tanggal bisnis hari ini.
     */
    public function openDateFor(CarbonInterface $moment): string
    {
        $date = BusinessCalendar::dateOf($moment);

        return $this->periods->isClosed($date) ? BusinessCalendar::today() : $date;
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: string} checkout_id, order_id, channel
     */
    protected function ownerOf(Payment $payment): array
    {
        if ($payment->checkout_id) {
            $channel = Checkout::whereKey($payment->checkout_id)->value('channel');

            return [$payment->checkout_id, null, $channel ?? FinancialEntry::CHANNEL_ONLINE];
        }

        $channel = Order::whereKey($payment->order_id)->value('channel');

        return [null, $payment->order_id, $channel ?? FinancialEntry::CHANNEL_OUTLET];
    }
}
