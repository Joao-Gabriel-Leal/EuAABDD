<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Member extends Model
{
    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'date',
        'cancelled_at' => 'date',
        'card_issued_at' => 'datetime',
        'card_revoked_at' => 'datetime',
        'address' => 'array',
        'imported_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Member $member): void {
            $member->card_token ??= (string) Str::uuid();
            $member->card_issued_at ??= now();
        });
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function dependents()
    {
        return $this->hasMany(Dependent::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function activeDependents()
    {
        return $this->dependents()->where('status', 'active');
    }

    public function monthlyAmount(): float
    {
        return $this->plan ? $this->plan->monthlyAmountForCategory($this->category) : 0;
    }

    public function dueDay(): int
    {
        return (int) ($this->billing_due_day ?: $this->plan?->monthly_due_day ?: 8);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Ativo',
            'pending_payment' => 'Aguardando pagamento',
            'blocked' => 'Bloqueado',
            'cancelled' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function hasOverdueInvoices(): bool
    {
        if ($this->relationLoaded('invoices')) {
            return $this->invoices->contains(fn (Invoice $invoice): bool => in_array($invoice->status, ['overdue'], true));
        }

        return $this->invoices()->where('status', 'overdue')->exists();
    }

    public function cardAccessAllowed(): bool
    {
        return $this->status === 'active'
            && is_null($this->card_revoked_at)
            && ! $this->hasOverdueInvoices();
    }

    public function cardBlockReason(): ?string
    {
        if ($this->card_revoked_at) {
            return 'Carteirinha revogada pela secretaria.';
        }

        if ($this->status !== 'active') {
            if ($this->status === 'pending_payment') {
                return 'Adesao aguardando pagamento da primeira mensalidade.';
            }

            return 'Associado '.$this->statusLabel().'.';
        }

        if ($this->hasOverdueInvoices()) {
            return 'Associado com cobrança vencida.';
        }

        return null;
    }
}
