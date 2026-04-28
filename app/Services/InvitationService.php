<?php

namespace App\Services;

use App\Mail\ClubInvitationMail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Support\BrazilianMasks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private readonly BillingService $billingService) {}

    public function createClubInvitation(Member $member, array $data): Invitation
    {
        $validFor = CarbonImmutable::parse($data['valid_for'] ?? today())->startOfDay();
        $usedInMonth = $member->invitations()
            ->where('type', 'club_access')
            ->whereBetween('valid_for', [$validFor->startOfMonth(), $validFor->endOfMonth()])
            ->whereNot('status', 'cancelled')
            ->count();

        $isExtra = $usedInMonth >= (int) $member->plan->included_guests;
        $amount = $isExtra ? (float) $member->plan->extra_guest_price : 0;

        return DB::transaction(function () use ($member, $data, $validFor, $isExtra, $amount) {
            $invoice = null;

            if ($isExtra) {
                $invoice = Invoice::create([
                    'member_id' => $member->id,
                    'number' => $this->billingService->nextNumber('CONV', $member),
                    'type' => 'invitation',
                    'description' => 'Convite excedente para '.$data['name'],
                    'amount' => $amount,
                    'due_date' => now()->addDays(2)->toDateString(),
                    'status' => 'open',
                    'payment_method' => 'Boleto BRB / QR App',
                    'issued_at' => now(),
                    'metadata' => ['regra' => 'excedente_cota_mensal'],
                ]);
            }

            $guest = Guest::create([
                'member_id' => $member->id,
                'name' => $data['name'],
                'cpf' => BrazilianMasks::formatCpf($data['cpf'] ?? null),
                'email' => $data['email'] ?? null,
                'is_extra' => $isExtra,
                'amount' => $amount,
                'status' => $isExtra ? 'awaiting_payment' : 'invited',
                'invitation_code' => 'AABB-'.Str::upper(Str::random(8)),
            ]);

            $invitation = Invitation::create([
                'member_id' => $member->id,
                'guest_id' => $guest->id,
                'invoice_id' => $invoice?->id,
                'type' => 'club_access',
                'code' => $guest->invitation_code,
                'sent_to_email' => $data['email'] ?? null,
                'valid_for' => $validFor,
                'status' => $isExtra ? 'extra_pending' : 'available',
                'is_extra' => $isExtra,
                'amount' => $amount,
            ]);

            if (! $isExtra && $invitation->sent_to_email) {
                $this->sendInvitationEmail($invitation);
            }

            return $invitation;
        });
    }

    private function sendInvitationEmail(Invitation $invitation): void
    {
        Mail::to($invitation->sent_to_email)->send(new ClubInvitationMail($invitation->loadMissing('guest')));

        $invitation->update(['emailed_at' => now()]);
    }
}
