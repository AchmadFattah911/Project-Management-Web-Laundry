<?php

namespace App\Services;

use App\Data\ExpenseData;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Pengeluaran operasional. Foto nota disimpan di disk private (tidak bisa diakses publik, hanya lewat
 * route yang diotorisasi). Hanya pengeluaran yang disetujui admin masuk ledger (entry expense, negatif)
 * pada tanggal pengeluarannya; tanggal di periode tutup buku ditolak.
 */
class ExpenseService
{
    public function __construct(
        protected LedgerService $ledger,
        protected ClosedPeriodService $periods,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{status?: ?string, category?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function paginate(User $viewer, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'creator:id,name', 'decider:id,name'])
            ->when(! $viewer->hasPermission('pengeluaran.view_all'), fn ($q) => $q->where('created_by', $viewer->id))
            ->when(array_key_exists((string) ($filters['status'] ?? ''), Expense::STATUS_LABELS), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['category']), fn ($q) => $q->where('category_id', (int) $filters['category']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('expense_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->where('expense_date', '<=', $filters['to']))
            ->latest('expense_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ExpenseCategory>
     */
    public function categories(): \Illuminate\Support\Collection
    {
        return ExpenseCategory::orderBy('name')->get();
    }

    public function create(ExpenseData $data, User $actor, string $token): Expense
    {
        if ($existing = Expense::where('submission_token', $token)->first()) {
            return $existing;
        }

        $this->periods->assertOpen($data->expenseDate, 'Pengeluaran');

        if (! ExpenseCategory::whereKey($data->categoryId)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Kategori pengeluaran tidak tersedia.');
        }

        $disk = (string) config('finance.expense.disk', 'local');
        $path = $data->receipt->storeAs(
            'expenses/' . now()->format('Y/m'),
            Str::uuid() . '.' . strtolower($data->receipt->extension() ?: 'bin'),
            $disk,
        );

        try {
            return DB::transaction(function () use ($data, $actor, $token, $path) {
                $expense = Expense::create([
                    'category_id'      => $data->categoryId,
                    'amount'           => $data->amount,
                    'expense_date'     => $data->expenseDate,
                    'description'      => $data->description,
                    'receipt_path'     => $path,
                    'receipt_name'     => mb_substr($data->receipt->getClientOriginalName(), 0, 255),
                    'receipt_mime'     => $data->receipt->getMimeType(),
                    'status'           => Expense::STATUS_PENDING,
                    'created_by'       => $actor->id,
                    'submission_token' => $token,
                ]);

                $this->audit->log('pengeluaran.create', $expense, null, $expense->only(['category_id', 'amount', 'expense_date', 'description']), null, $actor);

                return $expense;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk($disk)->delete($path);

            return Expense::where('submission_token', $token)->firstOrFail();
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    public function approve(Expense $expense, User $admin, ?string $note = null): Expense
    {
        return DB::transaction(function () use ($expense, $admin, $note) {
            $locked = $this->lockPending($expense);
            $this->periods->assertOpen($locked->expense_date->toDateString(), 'Pengeluaran');

            $locked->update([
                'status'        => Expense::STATUS_APPROVED,
                'decided_by'    => $admin->id,
                'decided_at'    => now(),
                'decision_note' => $note,
            ]);

            $this->ledger->append([
                'entry_type'    => FinancialEntry::TYPE_EXPENSE,
                'channel'       => null,
                'source_type'   => FinancialEntry::SOURCE_EXPENSE,
                'source_id'     => $locked->id,
                'amount'        => -$locked->amount,
                'occurred_at'   => now(),
                'business_date' => $locked->expense_date->toDateString(),
                'created_by'    => $admin->id,
            ]);

            $this->audit->log('pengeluaran.approve', $locked, ['status' => Expense::STATUS_PENDING], ['status' => Expense::STATUS_APPROVED, 'amount' => $locked->amount], $note, $admin);

            return $locked;
        });
    }

    public function reject(Expense $expense, User $admin, string $note): Expense
    {
        return DB::transaction(function () use ($expense, $admin, $note) {
            $locked = $this->lockPending($expense);

            $locked->update([
                'status'        => Expense::STATUS_REJECTED,
                'decided_by'    => $admin->id,
                'decided_at'    => now(),
                'decision_note' => $note,
            ]);

            $this->audit->log('pengeluaran.reject', $locked, ['status' => Expense::STATUS_PENDING], ['status' => Expense::STATUS_REJECTED], $note, $admin);

            return $locked;
        });
    }

    public function addCategory(string $name, User $admin): ExpenseCategory
    {
        try {
            $category = ExpenseCategory::create(['name' => trim($name), 'is_active' => true]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Kategori dengan nama itu sudah ada.');
        }

        $this->audit->log('pengeluaran.kategori.create', $category, null, ['name' => $category->name], null, $admin);

        return $category;
    }

    public function toggleCategory(ExpenseCategory $category, User $admin): ExpenseCategory
    {
        $category->update(['is_active' => ! $category->is_active]);
        $this->audit->log('pengeluaran.kategori.update', $category, ['is_active' => ! $category->is_active], ['is_active' => $category->is_active], null, $admin);

        return $category;
    }

    protected function lockPending(Expense $expense): Expense
    {
        $locked = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== Expense::STATUS_PENDING) {
            throw new InvalidArgumentException('Pengeluaran ini sudah diputuskan.');
        }

        return $locked;
    }
}
