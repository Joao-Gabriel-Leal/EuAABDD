<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\BillingService;
use App\Services\MemberCardService;
use Illuminate\Support\Facades\Auth;

class MemberCardController extends Controller
{
    public function show(string $token, BillingService $billingService, MemberCardService $cards)
    {
        abort_unless(Auth::user()?->hasInternalRole(), 403);

        $billingService->markOverdueInvoices();

        $member = Member::query()
            ->with([
                'plan',
                'dependents' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
                'invoices' => fn ($query) => $query->latest('due_date')->take(8),
            ])
            ->where('card_token', $token)
            ->first();

        return view('team.card-verification', [
            'member' => $member,
            'cardCode' => $member ? $cards->code($member) : null,
            'allowed' => $member?->cardAccessAllowed() ?? false,
            'blockReason' => $member?->cardBlockReason() ?? 'Carteirinha não encontrada ou token inválido.',
            'openInvoices' => $member?->invoices->whereIn('status', ['open', 'pending', 'awaiting_review', 'overdue']) ?? collect(),
        ]);
    }
}
