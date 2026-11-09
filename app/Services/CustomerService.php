<?php

namespace App\Services;

use App\Data\CustomerData;
use App\Enums\UserRole;
use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Data pelanggan untuk kasir. Pelanggan walk-in disimpan sebagai akun pelanggan (users) agar satu
 * pelanggan punya satu riwayat lintas saluran; akun walk-in memakai email placeholder dan password acak
 * sehingga tidak bisa dipakai login. Tidak ada hapus permanen: nonaktifkan atau gabungkan.
 */
class CustomerService
{
    public const WALK_IN_EMAIL_DOMAIN = 'pelanggan-outlet.laundrycoin.invalid';

    public function __construct(
        protected IdempotencyService $idempotency,
        protected AuditLogger $audit,
    ) {}

    /**
     * Pelanggan walk-in baru. Token form diproses 1x: submit ganda mengembalikan pelanggan yang sama.
     *
     * @return array{customer: User, replayed: bool}
     */
    public function create(CustomerData $data, User $actor, string $token): array
    {
        $result = $this->idempotency->run(
            key: 'pelanggan:' . $token,
            scope: 'customer-create',
            user: $actor,
            payload: $data->toArray(),
            callback: fn () => DB::transaction(function () use ($data, $actor) {
                $this->assertPhoneAvailable($data->phoneE164);

                $customer = new User([
                    'name'     => $data->name,
                    'email'    => 'walkin-' . Str::lower((string) Str::ulid()) . '@' . self::WALK_IN_EMAIL_DOMAIN,
                    'password' => Str::random(48),
                    'phone'    => $data->localPhone(),
                ]);
                $customer->forceFill([
                    'role'           => UserRole::PELANGGAN,
                    'phone_e164'     => $data->phoneE164,
                    'customer_notes' => $data->notes,
                    'created_via'    => 'outlet',
                    'is_active'      => true,
                ]);

                try {
                    $customer->save();
                } catch (UniqueConstraintViolationException) {
                    throw new InvalidArgumentException('Nomor HP ini sudah terdaftar untuk pelanggan lain.');
                }

                $this->audit->log('pelanggan.create', $customer, null, $this->snapshot($customer), null, $actor);

                return ['user_id' => $customer->id];
            }),
        );

        return ['customer' => User::findOrFail($result['response']['user_id']), 'replayed' => $result['replayed']];
    }

    public function update(User $customer, CustomerData $data, User $actor): User
    {
        $this->assertCustomer($customer);

        return DB::transaction(function () use ($customer, $data, $actor) {
            $locked = User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            $this->assertPhoneAvailable($data->phoneE164, $locked->id);

            $locked->forceFill([
                'name'           => $data->name,
                'phone'          => $data->localPhone(),
                'phone_e164'     => $data->phoneE164,
                'customer_notes' => $data->notes,
            ])->save();

            $this->audit->log('pelanggan.update', $locked, $before, $this->snapshot($locked), null, $actor);

            return $locked;
        });
    }

    public function setActive(User $customer, bool $active, User $actor, string $reason): User
    {
        $this->assertCustomer($customer);

        return DB::transaction(function () use ($customer, $active, $actor, $reason) {
            $locked = User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked);

            if ($locked->merged_into_id && $active) {
                throw new InvalidArgumentException('Akun hasil penggabungan tidak bisa diaktifkan kembali.');
            }

            $locked->forceFill(['is_active' => $active, 'deactivated_at' => $active ? null : now()])->save();

            $this->audit->log($active ? 'pelanggan.activate' : 'pelanggan.deactivate', $locked, $before, $this->snapshot($locked), $reason, $actor);

            return $locked;
        });
    }

    /**
     * Pindahkan seluruh transaksi akun ganda ke akun utama, lalu nonaktifkan akun ganda.
     *
     * @return array<string, int> jumlah baris yang dipindah per tabel
     */
    public function merge(User $primary, User $duplicate, User $actor, string $reason): array
    {
        $this->assertCustomer($primary);
        $this->assertCustomer($duplicate);

        if ($primary->is($duplicate)) {
            throw new InvalidArgumentException('Pilih dua pelanggan yang berbeda untuk digabungkan.');
        }

        return DB::transaction(function () use ($primary, $duplicate, $actor, $reason) {
            // Urutan lock konsisten (id kecil dulu) agar dua penggabungan paralel tidak deadlock
            $locked = User::whereKey([$primary->id, $duplicate->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $keep = $locked[$primary->id];
            $drop = $locked[$duplicate->id];

            if (! $drop->isActive() || ! $keep->isActive()) {
                throw new InvalidArgumentException('Kedua pelanggan harus berstatus aktif untuk digabungkan.');
            }

            $before = ['primary' => $this->snapshot($keep), 'duplicate' => $this->snapshot($drop)];

            $moved = [
                'checkouts'  => Checkout::where('user_id', $drop->id)->update(['user_id' => $keep->id]),
                'orders'     => Order::where('user_id', $drop->id)->update(['user_id' => $keep->id]),
                'cart_items' => $this->moveCartItems($drop, $keep),
            ];

            // Nomor HP akun ganda dipindah ke akun utama jika akun utama belum punya (UNIQUE: kosongkan dulu)
            $phone = $drop->phone_e164;
            $drop->forceFill([
                'phone_e164'     => null,
                'is_active'      => false,
                'deactivated_at' => now(),
                'merged_into_id' => $keep->id,
            ])->save();

            if (! $keep->phone_e164 && $phone) {
                $keep->forceFill(['phone_e164' => $phone, 'phone' => '0' . substr($phone, 3)])->save();
            }

            $this->audit->log('pelanggan.merge', $keep, $before, [
                'primary'   => $this->snapshot($keep),
                'duplicate' => $this->snapshot($drop),
                'moved'     => $moved,
            ], $reason, $actor);

            return $moved;
        });
    }

    /**
     * UNIQUE(user_id, service_id, item_type): item yang sudah ada di keranjang akun utama tidak dipindah.
     */
    protected function moveCartItems(User $from, User $to): int
    {
        $existing = CartItem::where('user_id', $to->id)->get(['service_id', 'item_type'])
            ->map(fn ($item) => $item->service_id . '|' . $item->item_type);

        CartItem::where('user_id', $from->id)->get()
            ->filter(fn ($item) => $existing->contains($item->service_id . '|' . $item->item_type))
            ->each->delete();

        return CartItem::where('user_id', $from->id)->update(['user_id' => $to->id]);
    }

    protected function assertPhoneAvailable(string $phoneE164, ?int $exceptId = null): void
    {
        $owner = User::where('phone_e164', $phoneE164)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->first(['id', 'name']);

        if ($owner) {
            throw new InvalidArgumentException("Nomor HP ini sudah terdaftar atas nama {$owner->name}.");
        }
    }

    protected function assertCustomer(User $user): void
    {
        if (! $user->isPelanggan()) {
            throw new InvalidArgumentException('Data ini bukan akun pelanggan.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(User $user): array
    {
        return $user->only(['name', 'phone', 'phone_e164', 'customer_notes', 'is_active', 'created_via', 'merged_into_id']);
    }
}
