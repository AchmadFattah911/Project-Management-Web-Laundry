<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'unit',
        'price',
        'estimate',
        'status',
        'description',
        'image',
        'features',
        'tag',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    /**
     * Scope untuk memfilter layanan yang aktif dan tersedia untuk dipesan.
     * Dipanggil via Service::aktif()
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'tersedia');
    }

    /**
     * Layanan yang boleh dipesan online oleh pelanggan.
     * Self Service (self laundry) hanya bisa dipakai langsung di outlet.
     * Dipanggil via Service::bisaDipesanOnline()
     */
    public function scopeBisaDipesanOnline(Builder $query): Builder
    {
        return $query->aktif()->where('category', 'Non-Self Service');
    }

    public function isSelfService(): bool
    {
        return strcasecmp((string) $this->category, 'Self Service') === 0;
    }

    /**
     * Tahapan pengerjaan oleh petugas untuk layanan Non-Self Service.
     * Cuci Saja → cuci; Cuci Kering → cuci, kering; Cuci Lipat → cuci, kering, lipat.
     * Self Service tidak punya tahapan (dikerjakan pelanggan sendiri).
     *
     * @return list<string>
     */
    public function processSteps(): array
    {
        if (strcasecmp((string) $this->category, 'Non-Self Service') !== 0) {
            return [];
        }

        $haystack = strtolower(($this->slug ?? '') . ' ' . ($this->name ?? ''));

        return match (true) {
            str_contains($haystack, 'lipat')  => ['cuci', 'kering', 'lipat'],
            str_contains($haystack, 'kering') => ['cuci', 'kering'],
            default                           => ['cuci'],
        };
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
