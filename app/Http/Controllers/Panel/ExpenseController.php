<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Http\Requests\Panel\ReasonRequest;
use App\Http\Requests\Panel\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pengeluaran operasional: pegawai input (can:pengeluaran.create), admin setujui/tolak (can:pengeluaran.approve).
 */
class ExpenseController extends Controller
{
    use AuthorizesRequests, HandlesServiceErrors;

    public function __construct(protected ExpenseService $expenses) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'category', 'from', 'to']);

        return view('panel.pengeluaran.index', [
            'expenses'   => $this->expenses->paginate($request->user(), $filters),
            'categories' => $this->expenses->categories(),
            'filters'    => $filters,
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $this->expenses->create($request->toData(), $request->user(), $request->validated('submission_token'));

            return redirect()->route('panel.pengeluaran.index')->with('success', 'Pengeluaran tersimpan dan menunggu persetujuan admin.');
        }, 'tambah-pengeluaran');
    }

    /** Foto nota dari storage private, hanya untuk pembuat atau admin. */
    public function receipt(Expense $expense)
    {
        $this->authorize('view', $expense);

        $disk = Storage::disk((string) config('finance.expense.disk', 'local'));
        abort_unless($disk->exists($expense->receipt_path), 404);

        return $disk->response($expense->receipt_path, $expense->receipt_name, [
            'Content-Type'           => $expense->receipt_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    public function approve(Request $request, Expense $expense)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        return $this->attempt(function () use ($request, $expense, $note) {
            $this->expenses->approve($expense, $request->user(), $note);

            return back()->with('success', 'Pengeluaran disetujui dan dicatat di ledger.');
        });
    }

    public function reject(ReasonRequest $request, Expense $expense)
    {
        return $this->attempt(function () use ($request, $expense) {
            $this->expenses->reject($expense, $request->user(), $request->reason());

            return back()->with('success', 'Pengeluaran ditolak.');
        });
    }

    public function storeCategory(Request $request)
    {
        $name = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100']])['name'];

        return $this->attempt(function () use ($request, $name) {
            $this->expenses->addCategory($name, $request->user());

            return back()->with('success', "Kategori {$name} ditambahkan.");
        });
    }

    public function toggleCategory(Request $request, ExpenseCategory $category)
    {
        $this->expenses->toggleCategory($category, $request->user());

        return back()->with('success', "Kategori {$category->name} " . ($category->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }
}
