<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_for' => 'date',
        'is_extra' => 'boolean',
        'amount' => 'decimal:2',
        'emailed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Disponivel',
            'used' => 'Usado',
            'payment_pending' => 'Aguardando pagamento',
            'extra_pending' => 'Excedente pendente',
            'cancelled' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function shareText(): string
    {
        return "Convite AABB Brasilia para {$this->guest?->name}. Codigo: {$this->code}. Valido em {$this->valid_for->format('d/m/Y')}. Apresente este codigo na portaria.";
    }

    public function whatsappUrl(): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $this->sent_to_phone) ?? '';

        if ($phone === '') {
            return null;
        }

        if (! str_starts_with($phone, '55')) {
            $phone = '55'.$phone;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($this->shareText());
    }
}
