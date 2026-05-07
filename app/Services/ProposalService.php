<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProposalService
{
    public function approveAndConvert(Proposal $proposal, ?User $user = null): Member
    {
        if ($proposal->converted_member_id) {
            return $proposal->convertedMember;
        }

        if (! $proposal->cpf) {
            throw ValidationException::withMessages([
                'cpf' => 'Informe o CPF antes de converter a proposta.',
            ]);
        }

        if (Member::where('cpf', $proposal->cpf)->exists()) {
            throw ValidationException::withMessages([
                'cpf' => 'Este CPF ja pertence a um associado.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $user) {
            $member = Member::create([
                'plan_id' => $proposal->plan_id,
                'membership_code' => 'AABB-'.str_pad((string) (Member::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'name' => $proposal->name,
                'cpf' => $proposal->cpf,
                'email' => $proposal->email,
                'phone' => $proposal->phone,
                'status' => 'active',
                'category' => 'Familiar',
                'billing_due_day' => $proposal->plan?->monthly_due_day,
                'membership_type' => 'associate',
                'joined_at' => now(),
                'notes' => 'Convertido a partir da proposta #'.$proposal->id.' por '.($user?->name ?? 'sistema').'.',
            ]);

            $proposal->update([
                'status' => 'approved',
                'signature_status' => 'pending_president_signature',
                'approved_at' => now(),
                'converted_member_id' => $member->id,
            ]);

            return $member;
        });
    }
}
