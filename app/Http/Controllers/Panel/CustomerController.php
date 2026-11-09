<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\HandlesServiceErrors;
use App\Http\Requests\Panel\CustomerRequest;
use App\Http\Requests\Panel\MergeCustomerRequest;
use App\Http\Requests\Panel\ReasonRequest;
use App\Models\User;
use App\Services\CustomerDirectory;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data pelanggan untuk kasir (pegawai & admin). Otorisasi per aksi lewat middleware can:pelanggan.* di route.
 */
class CustomerController extends Controller
{
    use HandlesServiceErrors;

    public function __construct(
        protected CustomerService $customers,
        protected CustomerDirectory $directory,
    ) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), CustomerDirectory::STATUSES, true) ? $request->query('status') : 'aktif';

        return view('panel.pelanggan.index', [
            'customers' => $this->directory->paginate($request->query('search'), $status),
            'search'    => (string) $request->query('search', ''),
            'status'    => $status,
        ]);
    }

    /** Pencarian cepat untuk form transaksi outlet. */
    public function search(Request $request): JsonResponse
    {
        return response()->json($this->directory->quickSearch($request->query('q'))->map(fn (User $u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'phone' => $u->phone,
            'walk_in' => $u->isWalkIn(),
        ]));
    }

    public function store(CustomerRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $result = $this->customers->create($request->toData(), $request->user(), $request->validated('submission_token'));
            $customer = $result['customer'];

            return redirect()->route('panel.pelanggan.show', $customer)->with('success', $result['replayed']
                ? "Pelanggan {$customer->name} sudah tersimpan sebelumnya, tidak dibuat ulang."
                : "Pelanggan {$customer->name} berhasil ditambahkan.");
        }, 'tambah-pelanggan');
    }

    public function show(User $customer)
    {
        return view('panel.pelanggan.show', ['customer' => $customer] + $this->directory->history($customer));
    }

    public function update(CustomerRequest $request, User $customer)
    {
        return $this->attempt(function () use ($request, $customer) {
            $this->customers->update($customer, $request->toData(), $request->user());

            return back()->with('success', 'Kontak pelanggan berhasil diperbarui.');
        }, 'ubah-pelanggan');
    }

    public function deactivate(ReasonRequest $request, User $customer)
    {
        return $this->attempt(function () use ($request, $customer) {
            $this->customers->setActive($customer, false, $request->user(), $request->reason());

            return back()->with('success', "Pelanggan {$customer->name} dinonaktifkan.");
        }, 'nonaktif-pelanggan');
    }

    public function activate(ReasonRequest $request, User $customer)
    {
        return $this->attempt(function () use ($request, $customer) {
            $this->customers->setActive($customer, true, $request->user(), $request->reason());

            return back()->with('success', "Pelanggan {$customer->name} diaktifkan kembali.");
        });
    }

    public function merge(MergeCustomerRequest $request)
    {
        return $this->attempt(function () use ($request) {
            $primary = User::findOrFail($request->validated('primary_id'));
            $moved = $this->customers->merge($primary, User::findOrFail($request->validated('duplicate_id')), $request->user(), $request->reason());

            return redirect()->route('panel.pelanggan.show', $primary)->with('success',
                "Pelanggan digabungkan: {$moved['checkouts']} transaksi dan {$moved['orders']} pesanan dipindahkan ke {$primary->name}.");
        }, 'gabung-pelanggan');
    }
}
