<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    public function dashboard()
    {
        $member = Auth::user()->member()->with([
            'plan',
            'dependents',
            'invoices' => fn ($query) => $query->latest('due_date'),
            'reservations.space',
            'reservations.guests',
            'invitations',
        ])->firstOrFail();

        return view('portal.dashboard', [
            'member' => $member,
            'spaces' => ReservableSpace::where('is_active', true)->get(),
        ]);
    }

    public function reserve(Request $request)
    {
        $member = Auth::user()->member;
        $data = $request->validate([
            'reservable_space_id' => ['required', 'exists:reservable_spaces,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $space = ReservableSpace::findOrFail($data['reservable_space_id']);
        $invoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-RES-'.now()->format('His').'-'.$member->id,
            'type' => 'reservation',
            'description' => 'Reserva '.$space->name,
            'amount' => $space->base_price,
            'due_date' => now()->addDays(2),
            'status' => $space->base_price > 0 ? 'pending' : 'paid',
            'payment_method' => 'Pix/QR simulado',
            'metadata' => ['space' => $space->name],
        ]);

        Reservation::create([
            'member_id' => $member->id,
            'reservable_space_id' => $space->id,
            'invoice_id' => $invoice->id,
            'reservation_date' => $data['reservation_date'],
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => $space->base_price > 0 ? 'pending_payment' : 'confirmed',
            'total_amount' => $space->base_price,
        ]);

        return back()->with('portal_status', 'Reserva criada e vinculada a cobranca no financeiro.');
    }

    public function addGuest(Request $request, Reservation $reservation)
    {
        $this->authorizeMemberReservation($reservation);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:30'],
        ]);

        $member = Auth::user()->member;
        $usedGuests = $reservation->guests()->count();
        $isExtra = $usedGuests >= $member->plan->included_guests;
        $amount = $isExtra ? $member->plan->extra_guest_price : 0;

        Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $member->id,
            'name' => $data['name'],
            'cpf' => $data['cpf'] ?? null,
            'is_extra' => $isExtra,
            'amount' => $amount,
            'status' => $isExtra ? 'awaiting_payment' : 'confirmed',
        ]);

        if ($isExtra && $reservation->invoice) {
            $reservation->invoice->increment('amount', $amount);
            $reservation->increment('total_amount', $amount);
        }

        return back()->with('portal_status', $isExtra
            ? 'Convidado extra adicionado e valor somado a cobranca da reserva.'
            : 'Convidado adicionado dentro da cota do associado.');
    }

    public function pay(Invoice $invoice)
    {
        abort_unless($invoice->member_id === Auth::user()->member_id, 403);

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $invoice->payment_method ?: 'Pix/QR simulado',
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'method' => $invoice->payment_method,
            'status' => 'paid',
            'transaction_code' => 'SIM-'.Str::upper(Str::random(8)),
            'paid_at' => now(),
        ]);

        return back()->with('portal_status', 'Pagamento simulado registrado com sucesso.');
    }

    private function authorizeMemberReservation(Reservation $reservation): void
    {
        abort_unless($reservation->member_id === Auth::user()->member_id, 403);
    }
}
