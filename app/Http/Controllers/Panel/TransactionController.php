<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Http\Requests\Panel\SettleOutletTransactionRequest;
use App\Http\Requests\Panel\StoreOutletTransactionRequest;
use App\Http\Requests\Panel\UpdateOutletTransactionRequest;
use App\Models\Checkout;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\OutletTransactionService;
use App\Services\TransactionDirectory;
use App\Support\TransactionTimeline;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Halaman transaksi kasir dengan tab [Outlet] [Aplikasi]. Otorisasi per aksi di route (can:...) dan
 * CheckoutPolicy::viewInPanel untuk detail; logika bisnis di OutletTransactionService.
 */
class TransactionController extends Controller
{
    use AuthorizesRequests, HandlesServiceErrors;

    public function __construct(
        protected OutletTransactionService $transactions,
        protected TransactionDirectory $directory,
        protected OrderService $orders,
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab') === TransactionDirectory::TAB_APP ? TransactionDirectory::TAB_APP : TransactionDirectory::TAB_OUTLET;
        $audit = $tab === TransactionDirectory::TAB_APP && $request->boolean('audit');

        Gate::authorize(match (true) {
            $audit                                => 'transaksi.audit_pembayaran.view',
            $tab === TransactionDirectory::TAB_APP => 'transaksi.online.view',
            default                               => 'transaksi.outlet.view',
        });

        $filters = $request->only(['search', 'from', 'to', 'status', 'method']);

        return view('panel.transaksi.index', [
            'tab'          => $tab,
            'audit'        => $audit,
            'filters'      => $filters,
            'transactions' => $this->directory->paginate($tab, $filters, $audit),
        ]);
    }

    public function create()
    {
        return view('panel.transaksi.create', ['services' => $this->directory->sellableServices()]);
    }

    public function store(StoreOutletTransactionRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $result = $this->transactions->create($request->toData(), $request->user(), $request->validated('submission_token'));
            $code = $result['checkout']->code;

            $message = match (true) {
                $result['replayed'] => "Transaksi {$code} sudah tersimpan sebelumnya, tidak dibuat ulang.",
                $result['change'] > 0 => "Transaksi {$code} tersimpan. Kembalian Rp " . number_format($result['change'], 0, ',', '.') . '.',
                default => "Transaksi {$code} tersimpan.",
            };

            return redirect()->route('panel.transaksi.show', $result['checkout'])->with('success', $message);
        });
    }

    public function show(Checkout $checkout)
    {
        $this->authorize('viewInPanel', $checkout);

        $checkout->load(['user', 'creator', 'orders.service', 'orders.statusLogs', 'payments', 'voidRequests.requester', 'voidRequests.decider', 'statusLogs', 'shift']);

        return view('panel.transaksi.show', [
            'checkout' => $checkout,
            'timeline' => TransactionTimeline::for($checkout),
        ]);
    }

    public function receipt(Checkout $checkout)
    {
        $this->authorize('viewInPanel', $checkout);

        $checkout->load(['user', 'creator', 'orders', 'payments', 'voidRequests']);

        return view('panel.transaksi.struk', ['checkout' => $checkout]);
    }

    public function update(UpdateOutletTransactionRequest $request, Checkout $checkout)
    {
        $this->authorize('viewInPanel', $checkout);

        return $this->attempt(function () use ($request, $checkout) {
            $this->transactions->update($checkout, $request->validated('qty'), $request->validated('notes'), $request->user());

            return back()->with('success', "Transaksi {$checkout->code} diperbarui.");
        }, 'ubah-transaksi');
    }

    public function settle(SettleOutletTransactionRequest $request, Checkout $checkout)
    {
        $this->authorize('viewInPanel', $checkout);

        return $this->attempt(function () use ($request, $checkout) {
            $payment = $this->transactions->settle(
                $checkout,
                $request->validated('payment_method'),
                $request->validated('cash_received'),
                $request->validated('payment_reference'),
                $request->user(),
                $request->validated('submission_token'),
            );
            $change = (int) ($payment->meta['change'] ?? 0);

            return back()->with('success', "Transaksi {$checkout->code} lunas." . ($change > 0 ? ' Kembalian Rp ' . number_format($change, 0, ',', '.') . '.' : ''));
        }, 'lunasi-transaksi');
    }

    /**
     * Memajukan status pengerjaan item (kedua tab). Aturan maju/mundur di OrderStateMachine.
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        if ($order->checkout) {
            $this->authorize('viewInPanel', $order->checkout);
        }

        $validated = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(function () use ($order, $validated, $request) {
            $this->orders->updateStatus($order, $validated['status'], $request->user(), $validated['note'] ?? null);

            return back()->with('success', "Status pesanan {$order->order_code} diperbarui.");
        });
    }
}
