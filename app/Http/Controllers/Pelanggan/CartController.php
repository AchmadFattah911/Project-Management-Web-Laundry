<?php

namespace App\Http\Controllers\Pelanggan;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Models\CartItem;
use App\Models\Service;
use App\Services\CartService;
use Illuminate\Http\Request;

/**
 * Halaman pemesanan (/pesan) + aksi keranjang pelanggan.
 */
class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $services = Service::bisaDipesanOnline()->orderBy('price')->get();

        // ?layanan=<slug> dari tombol "Pesan Sekarang" di landing page: kartu layanan langsung tercentang
        $preselectedId = $request->filled('layanan')
            ? $services->firstWhere('slug', $request->query('layanan'))?->id
            : null;

        $cart = $this->cartService->summary($user);

        // Langkah 2 (detail & konfirmasi) hanya bisa dibuka jika ada layanan yang bisa dipesan di keranjang
        $step = 'pilih';
        if ($request->query('langkah') === 'detail') {
            if ($cart['orderable']->isEmpty()) {
                return redirect()->route('pelanggan.pesan.index')
                    ->with('error', 'Keranjang masih kosong. Tambahkan layanan terlebih dahulu.');
            }
            $step = 'detail';
        }

        return view('pelanggan.pesan.index', [
            'user'          => $user,
            'services'      => $services,
            'preselectedId' => $preselectedId,
            'cart'          => $cart,
            'itemTypes'     => config('laundry.item_types', []),
            'maxQty'        => CartService::maxQty(),
            'step'          => $step,
            'dropOffMax'    => now()->addDays((int) config('laundry.drop_off_max_days', 7)),
            'hours'         => config('laundry.operating_hours', []),
        ]);
    }

    public function store(AddToCartRequest $request)
    {
        $added = $this->cartService->addMany($request->user(), $request->validated('items'));

        return redirect()->route('pelanggan.pesan.index')
            ->with('success', $added->count() . ' layanan berhasil ditambahkan ke keranjang.')
            ->with('cart_highlight', $added->pluck('id')->all());
    }

    public function update(Request $request, int $item)
    {
        $cartItem = $this->findOwned($request, $item);

        $validated = $request->validate([
            'qty'   => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'qty.required' => 'Jumlah mesin wajib diisi.',
            'qty.integer'  => 'Jumlah mesin harus berupa angka.',
        ]);

        $this->cartService->updateQty($cartItem, (int) $validated['qty'], $validated['notes'] ?? null);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function destroy(Request $request, int $item)
    {
        $this->cartService->remove($this->findOwned($request, $item));

        return back()->with('success', 'Layanan dihapus dari keranjang.');
    }

    /**
     * Item keranjang milik orang lain dianggap tidak ada (404), bukan 403, agar ID tidak bisa ditebak.
     */
    protected function findOwned(Request $request, int $id): CartItem
    {
        return $request->user()->cartItems()->findOrFail($id);
    }
}
