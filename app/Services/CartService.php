<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keranjang pelanggan sebelum checkout.
 *
 * Aturan yang dijaga di sini (bukan di frontend):
 * - Hanya layanan yang boleh dipesan online (Non-Self Service & tersedia).
 * - Harga tidak disimpan di keranjang, selalu dihitung dari tarif layanan saat ini.
 * - Batas jumlah mesin per layanan & jumlah baris keranjang.
 * - Tambah banyak layanan sekaligus bersifat atomic: semua masuk atau tidak ada yang masuk.
 */
class CartService
{
    public static function maxQty(): int
    {
        return (int) config('laundry.max_machine_qty', 20);
    }

    public static function maxLines(): int
    {
        return (int) config('laundry.cart_max_lines', 10);
    }

    /**
     * @return list<string>
     */
    public static function itemTypes(): array
    {
        return array_keys(config('laundry.item_types', []));
    }

    /**
     * Menambahkan beberapa layanan sekaligus dari satu form.
     *
     * @param  array<int|string, array{service_id: int|string, qty: int|string, item_type: string, notes?: ?string}>  $items
     * @return Collection<int, CartItem> item keranjang yang ditambah/diperbarui
     *
     * @throws ValidationException
     */
    public function addMany(User $user, array $items): Collection
    {
        $items = collect($items)->filter(fn ($item) => (int) ($item['qty'] ?? 0) > 0);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu layanan dengan jumlah mesin minimal 1.',
            ]);
        }

        return DB::transaction(function () use ($user, $items) {
            // Mengunci baris user = antrean per pelanggan, agar 2 request tambah keranjang bersamaan
            // tidak sama-sama lolos cek batas lalu melewati batas.
            User::whereKey($user->id)->lockForUpdate()->first();

            $cart = $user->cartItems()->get();
            $services = Service::bisaDipesanOnline()
                ->whereIn('id', $items->pluck('service_id'))
                ->get()
                ->keyBy('id');

            $errors = [];
            $merged = [];

            foreach ($items as $index => $item) {
                $service = $services->get((int) $item['service_id']);

                if (! $service) {
                    $errors["items.{$index}.service_id"] = 'Layanan ini tidak tersedia untuk dipesan online.';
                    continue;
                }

                // Layanan + jenis cucian yang sama di satu request digabung
                $lineKey = $service->id . '|' . $item['item_type'];
                $merged[$lineKey] ??= [
                    'index'     => $index,
                    'service'   => $service,
                    'item_type' => $item['item_type'],
                    'qty'       => 0,
                    'notes'     => null,
                ];
                $merged[$lineKey]['qty'] += (int) $item['qty'];
                $merged[$lineKey]['notes'] = $this->cleanNotes($item['notes'] ?? null) ?? $merged[$lineKey]['notes'];
            }

            $newLines = 0;
            $addedPerService = [];

            foreach ($merged as $lineKey => $line) {
                $service = $line['service'];
                $existing = $cart->first(fn (CartItem $c) => $c->service_id === $service->id && $c->item_type === $line['item_type']);

                if (! $existing) {
                    $newLines++;
                }

                $addedPerService[$service->id] = ($addedPerService[$service->id] ?? 0) + $line['qty'];
                $serviceTotal = $cart->where('service_id', $service->id)->sum('qty') + $addedPerService[$service->id];

                if ($serviceTotal > self::maxQty()) {
                    $errors["items.{$line['index']}.qty"] = "Total {$service->name} di keranjang maksimal " . self::maxQty() . ' mesin.';
                }
            }

            if ($cart->count() + $newLines > self::maxLines()) {
                $errors['items'] = 'Keranjang maksimal berisi ' . self::maxLines() . ' baris layanan. Selesaikan pesanan yang ada terlebih dahulu.';
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            return collect($merged)->map(function (array $line) use ($user, $cart) {
                $existing = $cart->first(fn (CartItem $c) => $c->service_id === $line['service']->id && $c->item_type === $line['item_type']);

                if ($existing) {
                    $existing->update([
                        'qty'   => $existing->qty + $line['qty'],
                        'notes' => $line['notes'] ?? $existing->notes,
                    ]);

                    return $existing;
                }

                return $user->cartItems()->create([
                    'service_id' => $line['service']->id,
                    'qty'        => $line['qty'],
                    'item_type'  => $line['item_type'],
                    'notes'      => $line['notes'],
                ]);
            })->values();
        });
    }

    /**
     * Mengubah jumlah mesin satu baris keranjang.
     *
     * @throws ValidationException
     */
    public function updateQty(CartItem $item, int $qty, ?string $notes = null): CartItem
    {
        if ($qty < 1 || $qty > self::maxQty()) {
            throw ValidationException::withMessages([
                'qty' => 'Jumlah mesin harus antara 1 sampai ' . self::maxQty() . ' unit.',
            ]);
        }

        return DB::transaction(function () use ($item, $qty, $notes) {
            User::whereKey($item->user_id)->lockForUpdate()->first();

            $otherQty = CartItem::where('user_id', $item->user_id)
                ->where('service_id', $item->service_id)
                ->whereKeyNot($item->id)
                ->sum('qty');

            if ($otherQty + $qty > self::maxQty()) {
                throw ValidationException::withMessages([
                    'qty' => 'Total layanan ini di keranjang maksimal ' . self::maxQty() . ' mesin.',
                ]);
            }

            $item->update([
                'qty'   => $qty,
                'notes' => $this->cleanNotes($notes) ?? $item->notes,
            ]);

            return $item;
        });
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(User $user): void
    {
        $user->cartItems()->delete();
    }

    /**
     * Ringkasan keranjang dengan harga terbaru dari server.
     *
     * @return array{items: Collection<int, CartItem>, orderable: Collection<int, CartItem>, unavailable: Collection<int, CartItem>, total: float, machine_count: int}
     */
    public function summary(User $user): array
    {
        $items = $user->cartItems()->with('service')->oldest('id')->get();
        $orderable = $items->filter->isOrderable()->values();

        return [
            'items'         => $items,
            'orderable'     => $orderable,
            'unavailable'   => $items->reject->isOrderable()->values(),
            'total'         => (float) $orderable->sum(fn (CartItem $item) => $item->subtotal()),
            'machine_count' => (int) $orderable->sum('qty'),
        ];
    }

    protected function cleanNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : mb_substr($notes, 0, 500);
    }
}
