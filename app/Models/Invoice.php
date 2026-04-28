<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'billing_month' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['open', 'overdue', 'awaiting_review', 'pending'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Aberta',
            'paid' => 'Paga',
            'overdue' => 'Vencida',
            'awaiting_review' => 'Comprovante em análise',
            'cancelled' => 'Cancelada',
            'pending' => 'Aberta',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
