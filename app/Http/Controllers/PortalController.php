<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Services\BillingService;
use App\Services\InvitationService;
use App\Services\MemberCardService;
use App\Services\ReservationService;
use App\Support\BrazilianMasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    public function dashboard(BillingService $billingService, MemberCardService $cards)
    {
        $billingService->markOverdueInvoices();

        $member = Auth::user()->member()->with([
            'plan',
            'dependents',
            'invoices' => fn ($query) => $query->latest('due_date'),
            'reservations.space',
            'reservations.guests',
            'invitations' => fn ($query) => $query->with('guest')->latest('valid_for')->latest(),
        ])->firstOrFail();

        return view('portal.dashboard', [
            'member' => $member,
            'spaces' => ReservableSpace::where('is_active', true)->get(),
            'cardCode' => $cards->code($member),
            'cardQrCode' => $cards->qrCodeDataUri($member),
        ]);
    }

    public function reserve(Request $request, ReservationService $reservations)
    {
        $member = Auth::user()->member;
        $this->authorizeActiveMember($member);

        $data = $request->validate([
            'reservable_space_id' => ['required', 'exists:reservable_spaces,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $space = ReservableSpace::findOrFail($data['reservable_space_id']);
        $reservation = $reservations->createReservation($member, $space, $data['reservation_date']);

        return back()->with('portal_status', 'Reserva criada com agenda bloqueada e cobranca real vinculada: '.$reservation->invoice->number.'.');
    }

    public function addGuest(Request $request, Reservation $reservation, ReservationService $reservations)
    {
        $this->authorizeMemberReservation($reservation);
        $this->authorizeActiveMember(Auth::user()->member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf'] ?? null);

        $guest = $reservations->addGuest($reservation, $data);

        return back()->with('portal_status', $guest->is_extra
            ? 'Convidado extra adicionado, convite gerado e valor somado a cobranca da reserva.'
            : 'Convidado adicionado dentro da cota do associado.');
    }

    public function uploadPaymentProof(Request $request, Invoice $invoice, BillingService $billingService)
    {
        abort_unless($invoice->member_id === Auth::user()->member_id, 403);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:80'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $billingService->uploadProof($invoice, $request->file('proof'), $data['payment_method'], $data['notes'] ?? null);

        return back()->with('portal_status', 'Comprovante recebido. O financeiro fara a baixa manual e confirmara a cobranca.');
    }

    public function payDemo(Invoice $invoice, BillingService $billingService)
    {
        abort_unless($invoice->member_id === Auth::user()->member_id, 403);
        abort_unless($invoice->isPayable(), 403);

        $billingService->recordManualPayment($invoice, [
            'amount' => $invoice->amount,
            'method' => 'QR App AABB Demo',
            'paid_at' => now()->toDateString(),
            'manual_reference' => 'DEMO-'.now()->format('YmdHis'),
            'notes' => 'Pagamento simulado pelo portal do associado.',
        ]);

        return back()->with('portal_status', 'Pagamento demo confirmado. Carteirinha liberada para acesso ao clube.');
    }

    public function createInvitation(Request $request, InvitationService $invitations)
    {
        $this->authorizeActiveMember(Auth::user()->member);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => [
                'nullable',
                'string',
                'max:30',
                fn (string $attribute, mixed $value, \Closure $fail) => BrazilianMasks::hasCpfLength($value) ?: $fail('Informe um CPF com 11 numeros.'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'valid_for' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $data['cpf'] = BrazilianMasks::formatCpf($data['cpf'] ?? null);

        $invitation = $invitations->createClubInvitation(Auth::user()->member, $data);

        return back()->with('portal_status', $invitation->is_extra
            ? 'Convite excedente gerado com cobranca vinculada. Ele sera liberado apos baixa do financeiro.'
            : 'Convite gerado dentro da cota mensal: '.$invitation->code.'.');
    }

    private function authorizeMemberReservation(Reservation $reservation): void
    {
        abort_unless($reservation->member_id === Auth::user()->member_id, 403);
    }

    private function authorizeActiveMember($member): void
    {
        abort_unless($member?->status === 'active', 403);
    }
}
