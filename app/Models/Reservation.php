<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reservation_date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function space()
    {
        return $this->belongsTo(ReservableSpace::class, 'reservable_space_id');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isBlockedForAgenda(): bool
    {
        return ! in_array($this->status, ['cancelled', 'rejected'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_payment' => 'Aguardando pagamento',
            'confirmed' => 'Confirmada',
            'cancelled' => 'Cancelada',
            'rejected' => 'Reprovada',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }
}
