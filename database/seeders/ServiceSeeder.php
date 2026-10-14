<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $newServices = [
            [
                'name'        => 'Mesin Cuci',
                'slug'        => 'mesin-cuci',
                'category'    => 'Self Service',
                'unit'        => 'Mesin',
                'price'       => 10000,
                'estimate'    => '1 Jam',
                'status'      => 'tersedia',
                'description' => 'Pakaian maks 6KG/mesin, sprei/selimut tipis maks 3KG/mesin, bedcover maks 1 pcs/mesin',
            ],
            [
                'name'        => 'Mesin Pengering',
                'slug'        => 'mesin-pengering',
                'category'    => 'Self Service',
                'unit'        => 'Mesin',
                'price'       => 20000,
                'estimate'    => '1 Jam',
                'status'      => 'tersedia',
                'description' => 'Proses pengeringan mesin',
            ],
            [
                'name'        => 'Cuci + Kering',
                'slug'        => 'cuci-kering-self',
                'category'    => 'Self Service',
                'unit'        => 'Mesin',
                'price'       => 30000,
                'estimate'    => '2 Jam',
                'status'      => 'tersedia',
                'description' => 'Paket cuci dan pengeringan self-service',
            ],
            [
                'name'        => 'Mesin Cuci Besar Max 12KG (Cuci + Kering)',
                'slug'        => 'mesin-cuci-besar-12kg',
                'category'    => 'Self Service',
                'unit'        => 'Mesin',
                'price'       => 50000,
                'estimate'    => '2 Jam',
                'status'      => 'tersedia',
                'description' => 'Kapasitas besar hingga 12kg, termasuk cuci + kering. Bedcover maks 1 pcs/mesin',
            ],
            [
                'name'        => 'Cuci Saja (tanpa sabun)',
                'slug'        => 'cuci-saja',
                'category'    => 'Non-Self Service',
                'unit'        => 'Mesin',
                'price'       => 15000,
                'estimate'    => '1 Hari',
                'status'      => 'tersedia',
                'description' => 'Layanan titip cuci saja tanpa sabun',
            ],
            [
                'name'        => 'Cuci Kering (+sabun)',
                'slug'        => 'cuci-kering-titip',
                'category'    => 'Non-Self Service',
                'unit'        => 'Mesin',
                'price'       => 45000,
                'estimate'    => '1-2 Hari',
                'status'      => 'tersedia',
                'description' => 'Layanan titip cuci + kering dengan sabun',
            ],
            [
                'name'        => 'Cuci Lipat (+sabun)',
                'slug'        => 'cuci-lipat',
                'category'    => 'Non-Self Service',
                'unit'        => 'Mesin',
                'price'       => 50000,
                'estimate'    => '1-2 Hari',
                'status'      => 'tersedia',
                'description' => 'Layanan titip cuci + kering + lipat dengan sabun',
            ],
        ];

        // 1. Seed / Update 7 layanan utama
        $activeSlugs = [];
        foreach ($newServices as $data) {
            $activeSlugs[] = $data['slug'];
            Service::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        // 2. Tangani layanan lama yang slug-nya tidak ada dalam daftar aktif
        $obsoleteServices = Service::whereNotIn('slug', $activeSlugs)->get();
        foreach ($obsoleteServices as $oldService) {
            $usedInOrders = Order::where('service_id', $oldService->id)->exists();

            if ($usedInOrders) {
                // Jika sudah dipakai order: nonaktifkan (status = tidak_tersedia)
                $oldService->update(['status' => 'tidak_tersedia']);
            } else {
                // Jika belum pernah dipakai order: hapus permanen
                $oldService->delete();
            }
        }
    }
}
