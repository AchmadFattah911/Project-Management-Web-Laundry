<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case PEGAWAI = 'pegawai';
    case PELANGGAN = 'pelanggan';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::PEGAWAI => 'Pegawai Laundry',
            self::PELANGGAN => 'Pelanggan',
        };
    }
}
