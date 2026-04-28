<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipSignupService
{
    public function __construct(private readonly BillingService $billingService) {}

    public function create(array $data): array
    {
        $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);
        $category = $data['category'];
        $amount = $plan->monthlyAmountForCategory($category);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'category' => 'Esta categoria nao esta disponivel para o plano escolhido.',
            ]);
        }

        return DB::transaction(function () use ($data, $plan, $category, $amount) {
            $member = Member::create([
                'plan_id' => $plan->id,
                'membership_code' => $this->nextMembershipCode(),
                'name' => $data['name'],
                'cpf' => $data['cpf'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => 'pending_payment',
                'category' => $category,
                'billing_due_day' => $plan->monthly_due_day,
                'membership_type' => 'associate',
                'joined_at' => now(),
                'notes' => 'Adesao direta criada pelo site publico em '.now()->format('d/m/Y H:i').'. Acesso liberado apos pagamento inicial.',
            ]);

            $user = User::create([
                'name' => $member->name,
                'email' => $member->email,
                'password' => Hash::make($data['password']),
                'role' => 'member',
                'member_id' => $member->id,
            ]);

            $invoice = Invoice::create([
                'member_id' => $member->id,
                'type' => 'membership_initial',
                'billing_month' => CarbonImmutable::now()->startOfMonth()->toDateString(),
                'number' => $this->billingService->nextNumber('ADESAO', $member),
                'description' => 'Primeira mensalidade - adesao '.$plan->name.' '.$category,
                'amount' => $amount,
                'due_date' => today()->toDateString(),
                'status' => 'open',
                'payment_method' => 'QR App AABB / Boleto BRB',
                'issued_at' => now(),
                'metadata' => [
                    'origem' => 'adesao_publica',
                    'plano' => $plan->name,
                    'categoria' => $category,
                    'meios_previstos' => ['qr_app', 'boleto_brb', 'debito_brb'],
                    'liberacao' => 'apos_pagamento',
                ],
            ]);

            return compact('member', 'user', 'invoice');
        });
    }

    private function nextMembershipCode(): string
    {
        do {
            $code = 'AABB-'.str_pad((string) ((int) Member::max('id') + 1), 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(2));
        } while (Member::where('membership_code', $code)->exists());

        return $code;
    }
}
