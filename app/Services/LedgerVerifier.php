<?php

namespace App\Services;

use App\Models\FinancialEntry;
use App\Models\LedgerReview;
use App\Models\Payment;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan ledger dengan data sumbernya. Setiap temuan = 1 selisih yang harus ditinjau;
 * hasil kosong berarti ledger cocok dengan sumber.
 */
class LedgerVerifier
{
    public function __construct(protected LedgerService $ledger) {}

    /**
     * @return list<array{source_type: string, source_id: int, issue: string, detail: array<string, mixed>}>
     */
    public function verify(): array
    {
        return array_merge(
            $this->payments(),
            $this->orphanSales(),
            $this->sourceRows(FinancialEntry::TYPE_VOID_REVERSAL, FinancialEntry::SOURCE_VOID, 'void_requests', fn ($q) => $q->where('status', 'approved'), 'amount'),
            $this->sourceRows(FinancialEntry::TYPE_REFUND, FinancialEntry::SOURCE_REFUND, 'refunds', fn ($q) => $q->where('status', 'selesai'), 'amount'),
            $this->sourceRows(FinancialEntry::TYPE_EXPENSE, FinancialEntry::SOURCE_EXPENSE, 'expenses', fn ($q) => $q->where('status', 'approved'), 'amount'),
        );
    }

    /**
     * Simpan temuan sebagai ledger_reviews berstatus perlu_tinjau (idempoten). Ledger tidak diubah.
     *
     * @param  list<array{source_type: string, source_id: int, issue: string, detail: array<string, mixed>}>  $issues
     */
    public function markForReview(array $issues): void
    {
        foreach ($issues as $issue) {
            LedgerReview::firstOrCreate(
                ['source_type' => $issue['source_type'], 'source_id' => $issue['source_id'], 'issue' => $issue['issue']],
                ['detail' => $issue['detail'], 'status' => LedgerReview::STATUS_OPEN],
            );
        }
    }

    /**
     * Setiap pembayaran lunas punya tepat 1 sale dengan nominal & saluran yang sama (+ biaya gateway Midtrans).
     */
    protected function payments(): array
    {
        $issues = [];

        $entries = FinancialEntry::where('source_type', FinancialEntry::SOURCE_PAYMENT)
            ->get(['entry_type', 'source_id', 'amount', 'channel'])
            ->groupBy('source_id');

        Payment::where('status', Payment::STATUS_PAID)
            ->with(['checkout:id,channel', 'order:id,channel'])
            ->lazyById(500)
            ->each(function (Payment $payment) use ($entries, &$issues) {
                $rows = $entries->get($payment->id, collect());
                $sale = $rows->firstWhere('entry_type', FinancialEntry::TYPE_SALE);
                $amount = Rupiah::parse($payment->amount);
                $channel = $payment->checkout?->channel ?? $payment->order?->channel;

                if (! $sale) {
                    $issues[] = $this->issue(FinancialEntry::SOURCE_PAYMENT, $payment->id, 'missing_sale', ['amount' => $amount]);

                    return;
                }

                if ($sale->amount !== $amount) {
                    $issues[] = $this->issue(FinancialEntry::SOURCE_PAYMENT, $payment->id, 'sale_amount_mismatch', ['payment' => $amount, 'ledger' => $sale->amount]);
                }

                if ($channel && $sale->channel !== $channel) {
                    $issues[] = $this->issue(FinancialEntry::SOURCE_PAYMENT, $payment->id, 'sale_channel_mismatch', ['source' => $channel, 'ledger' => $sale->channel]);
                }

                if ($this->ledger->gatewayFeeFor($payment) > 0 && ! $rows->firstWhere('entry_type', FinancialEntry::TYPE_GATEWAY_FEE)) {
                    $issues[] = $this->issue(FinancialEntry::SOURCE_PAYMENT, $payment->id, 'missing_gateway_fee', []);
                }
            });

        return $issues;
    }

    /**
     * Sale/biaya gateway yang pembayarannya tidak ada atau tidak (lagi) lunas.
     */
    protected function orphanSales(): array
    {
        return FinancialEntry::query()
            ->where('financial_entries.source_type', FinancialEntry::SOURCE_PAYMENT)
            ->leftJoin('payments', 'payments.id', '=', 'financial_entries.source_id')
            ->where(fn ($q) => $q->whereNull('payments.id')->orWhere('payments.status', '!=', Payment::STATUS_PAID))
            ->get(['financial_entries.source_id', 'financial_entries.entry_type', 'payments.status'])
            ->map(fn ($row) => $this->issue(FinancialEntry::SOURCE_PAYMENT, (int) $row->source_id, 'orphan_' . $row->entry_type, ['payment_status' => $row->status]))
            ->all();
    }

    /**
     * Baris sumber yang sudah final (disetujui/selesai) ⇔ tepat 1 entri ledger bernilai -amount.
     */
    protected function sourceRows(string $entryType, string $sourceType, string $table, \Closure $final, string $amountColumn): array
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        $issues = [];
        $ledger = FinancialEntry::where('entry_type', $entryType)->where('source_type', $sourceType)->pluck('amount', 'source_id');
        $sources = $final(DB::table($table))->pluck($amountColumn, 'id');

        foreach ($sources as $id => $amount) {
            if (! $ledger->has($id)) {
                $issues[] = $this->issue($sourceType, (int) $id, 'missing_' . $entryType, ['amount' => (int) $amount]);
            } elseif ((int) $ledger[$id] !== -(int) $amount) {
                $issues[] = $this->issue($sourceType, (int) $id, $entryType . '_amount_mismatch', ['source' => -(int) $amount, 'ledger' => (int) $ledger[$id]]);
            }
        }

        foreach ($ledger->keys()->diff($sources->keys()) as $id) {
            $issues[] = $this->issue($sourceType, (int) $id, 'orphan_' . $entryType, []);
        }

        return $issues;
    }

    protected function issue(string $sourceType, int $sourceId, string $issue, array $detail): array
    {
        return ['source_type' => $sourceType, 'source_id' => $sourceId, 'issue' => $issue, 'detail' => $detail];
    }
}
