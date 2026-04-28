<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'entry' => 'Entrada',
            'exit' => 'Saida',
            'adjustment' => 'Ajuste',
            'loss' => 'Perda',
            default => ucfirst((string) $this->type),
        };
    }

    public function typeTone(): string
    {
        return match ($this->type) {
            'entry' => 'success',
            'adjustment' => 'info',
            'loss' => 'danger',
            'exit' => 'warning',
            default => 'muted',
        };
    }
}
