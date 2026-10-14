<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Item keranjang pelanggan. Harga sengaja tidak disimpan: selalu dibaca dari tarif layanan terbaru.
 */
class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'qty',
        'item_type',
        'notes',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Item masih bisa di-checkout jika layanannya aktif dan boleh dipesan online.
     */
    public function isOrderable(): bool
    {
        return $this->service !== null
            && $this->service->status === 'tersedia'
            && ! $this->service->isSelfService();
    }

    public function subtotal(): float
    {
        return (float) ($this->service?->price ?? 0) * $this->qty;
    }

    public function itemTypeLabel(): string
    {
        return config("laundry.item_types.{$this->item_type}.label", $this->item_type);
    }
}
