<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function convertedMember()
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new' => 'Nova',
            'analysis' => 'Em análise',
            'approved' => 'Aprovada',
            'rejected' => 'Reprovada',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function signatureStatusLabel(): string
    {
        return match ($this->signature_status) {
            'pending' => 'Pendente',
            'pending_president_signature' => 'Aguardando presidente',
            'signed' => 'Assinada',
            default => ucfirst(str_replace('_', ' ', (string) $this->signature_status)),
        };
    }
}
