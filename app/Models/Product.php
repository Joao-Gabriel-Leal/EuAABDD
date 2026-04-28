<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'minimum_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->sku ??= static::nextSku();
            $product->qr_token ??= (string) Str::uuid();
            $product->unit_cost ??= 0;
            $product->is_active ??= true;
        });
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isBelowMinimum(): bool
    {
        return $this->quantity < $this->minimum_quantity;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function stockValue(): float
    {
        return (float) $this->unit_cost * $this->quantity;
    }

    public function stockStatus(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->isOutOfStock()) {
            return 'empty';
        }

        return $this->isBelowMinimum() ? 'low' : 'ok';
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            'inactive' => 'Inativo',
            'empty' => 'Zerado',
            'low' => 'Abaixo do minimo',
            default => 'Saudavel',
        };
    }

    public function stockStatusTone(): string
    {
        return match ($this->stockStatus()) {
            'empty' => 'danger',
            'low' => 'warning',
            'inactive' => 'muted',
            default => 'success',
        };
    }

    private static function nextSku(): string
    {
        $next = (int) static::max('id') + 1;

        do {
            $sku = 'AABB-EST-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::where('sku', $sku)->exists());

        return $sku;
    }
}
