<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'monthly_family' => 'decimal:2',
        'monthly_individual' => 'decimal:2',
        'monthly_under_30' => 'decimal:2',
        'monthly_special' => 'decimal:2',
        'extra_guest_price' => 'decimal:2',
        'dependent_extra_price' => 'decimal:2',
        'annual_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function monthlyAmountForCategory(?string $category): float
    {
        return (float) match ($category) {
            'Individual' => $this->monthly_individual,
            'Individual 30 Menos' => $this->monthly_under_30,
            'Especial' => $this->monthly_special,
            default => $this->monthly_family,
        };
    }
}
