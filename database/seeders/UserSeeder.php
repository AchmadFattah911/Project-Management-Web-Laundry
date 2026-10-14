<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Administrator',
                'email'    => 'admin@laundrycoin.com',
                'role'     => UserRole::ADMIN,
                'password' => 'password',
                'phone'    => '081234567890',
            ],
            [
                'name'     => 'Pegawai Laundry',
                'email'    => 'pegawai@laundrycoin.com',
                'role'     => UserRole::PEGAWAI,
                'password' => 'password',
                'phone'    => '081234567891',
            ],
            [
                'name'     => 'Pelanggan Setia',
                'email'    => 'pelanggan@laundrycoin.com',
                'role'     => UserRole::PELANGGAN,
                'password' => 'password',
                'phone'    => '081234567892',
            ],
        ];

        foreach ($users as $item) {
            // updateOrCreate berdasarkan email unik
            $user = User::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name'     => $item['name'],
                    'password' => Hash::make($item['password']),
                    'phone'    => $item['phone'],
                ]
            );

            // Set role secara langsung (karena role tidak ada di $fillable demi keamanan)
            $user->role = $item['role'];
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
