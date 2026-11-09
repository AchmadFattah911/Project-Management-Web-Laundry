<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * Note: 'role' is intentionally excluded from fillable for security.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Nomor HP tersamar untuk daftar pilihan: cukup mengenali orangnya tanpa membuka nomor lengkapnya.
     */
    public function maskedPhone(): string
    {
        $phone = (string) $this->phone;

        return strlen($phone) > 8 ? substr($phone, 0, 4) . '****' . substr($phone, -4) : 'No telp -';
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isPegawai(): bool
    {
        return $this->role === UserRole::PEGAWAI;
    }

    public function isPelanggan(): bool
    {
        return $this->role === UserRole::PELANGGAN;
    }

    /**
     * Permission per aksi dari config/permissions.php. Akun nonaktif tidak punya permission apa pun.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $role = $this->role instanceof UserRole ? $this->role->value : (string) $this->role;

        return in_array($permission, config("permissions.roles.{$role}", []), true);
    }

    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    /**
     * Pelanggan yang dibuat di outlet tanpa akun website (email placeholder, tidak bisa login).
     */
    public function isWalkIn(): bool
    {
        return $this->created_via === 'outlet';
    }

    /**
     * Inisial nama (maks. 2 huruf) untuk foto profil bawaan, misal "Budi Santoso" -> "BS".
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('') ?: 'P';
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }
}
