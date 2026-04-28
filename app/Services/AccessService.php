<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessService
{
    public function registerInvitationAccess(string $code, string $gate = 'Portaria principal'): AccessLog
    {
        $invitation = Invitation::with(['member.invoices', 'guest'])
            ->where('code', $code)
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'code' => 'Convite não encontrado.',
            ]);
        }

        $blockedReason = $this->blockedReason($invitation);

        return DB::transaction(function () use ($invitation, $gate, $blockedReason) {
            $log = AccessLog::create([
                'member_id' => $invitation->member_id,
                'guest_id' => $invitation->guest_id,
                'invitation_id' => $invitation->id,
                'person_name' => $invitation->guest?->name ?? $invitation->member->name,
                'person_type' => $invitation->guest_id ? 'convidado' : 'associado',
                'gate' => $gate,
                'status' => $blockedReason ? 'blocked: '.$blockedReason : 'allowed',
                'checked_at' => now(),
            ]);

            if (! $blockedReason) {
                $invitation->update([
                    'status' => 'used',
                    'used_at' => now(),
                ]);

                $invitation->guest?->update([
                    'status' => 'used',
                    'checked_in_at' => now(),
                ]);
            }

            return $log;
        });
    }

    private function blockedReason(Invitation $invitation): ?string
    {
        if ($invitation->status !== 'available') {
            return 'convite '.$invitation->status;
        }

        if ($invitation->valid_for->isPast() && ! $invitation->valid_for->isToday()) {
            return 'convite vencido';
        }

        $hasOverdue = $invitation->member->invoices
            ->whereIn('status', ['overdue'])
            ->isNotEmpty();

        return $hasOverdue ? 'associado inadimplente' : null;
    }
}
