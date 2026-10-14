<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Helper pembuat data uji (layanan, user per role, order) agar test tetap ringkas.
 */
trait CreatesLaundryData
{
    protected function makeUser(UserRole $role = UserRole::PELANGGAN, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, ...$attributes]);
    }

    protected function makeService(string $category = 'Non-Self Service', array $attributes = []): Service
    {
        $name = $attributes['name'] ?? ($category === 'Self Service' ? 'Mesin Cuci Uji' : 'Cuci Lipat Uji') . ' ' . Str::random(4);

        return Service::create([
            'name'     => $name,
            'slug'     => Str::slug($name),
            'category' => $category,
            'unit'     => 'Mesin',
            'price'    => 15000,
            'estimate' => '1 Hari',
            'status'   => 'tersedia',
            ...$attributes,
        ]);
    }

    protected function makeOrder(?User $user, Service $service, array $attributes = []): Order
    {
        return Order::create([
            'order_code'  => 'LC-' . strtoupper(Str::random(6)),
            'user_id'     => $user?->id,
            'service_id'  => $service->id,
            'machine_qty' => 1,
            'unit_price'  => $service->price,
            'total_price' => $service->price,
            'status'      => 'pending',
            ...$attributes,
        ]);
    }
}
