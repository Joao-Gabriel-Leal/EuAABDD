<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Support\BrazilianMasks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private readonly BillingService $billingService) {}

    public function createReservation(Member $member, ReservableSpace $space, string $date): Reservation
    {
        $reservationDate = CarbonImmutable::parse($date)->startOfDay();

        if ($reservationDate->isPast()) {
            throw ValidationException::withMessages([
                'reservation_date' => 'Escolha uma data futura para a reserva.',
            ]);
        }

        $this->assertAvailable($space, $reservationDate);

        return DB::transaction(function () use ($member, $space, $reservationDate) {
            $status = $space->base_price > 0 ? 'open' : 'paid';

            $invoice = Invoice::create([
                'member_id' => $member->id,
                'number' => $this->billingService->nextNumber('RES', $member),
                'type' => 'reservation',
                'description' => 'Reserva '.$space->name.' em '.$reservationDate->format('d/m/Y'),
                'amount' => $space->base_price,
                'due_date' => now()->addDays(2)->toDateString(),
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'payment_method' => 'Boleto BRB / QR App',
                'issued_at' => now(),
                'metadata' => [
                    'space' => $space->name,
                    'pagamento' => 'associado_responsavel',
                    'meios_previstos' => ['boleto_brb', 'qr_app', 'cartao_presencial'],
                ],
            ]);

            $reservation = Reservation::create([
                'member_id' => $member->id,
                'reservable_space_id' => $space->id,
                'invoice_id' => $invoice->id,
                'reservation_date' => $reservationDate->toDateString(),
                'starts_at' => $space->rules['starts_at'] ?? '12:00',
                'ends_at' => $space->rules['ends_at'] ?? '18:00',
                'status' => $status === 'paid' ? 'confirmed' : 'pending_payment',
                'total_amount' => $space->base_price,
                'guest_quota' => (int) ($space->rules['included_guests'] ?? 4),
                'confirmed_at' => $status === 'paid' ? now() : null,
                'notes' => 'Reserva criada pelo portal do associado.',
            ]);

            $invoice->update([
                'source_type' => Reservation::class,
                'source_id' => $reservation->id,
            ]);

            return $reservation;
        });
    }

    public function addGuest(Reservation $reservation, array $data): Guest
    {
        return DB::transaction(function () use ($reservation, $data) {
            $reservation->loadMissing(['member.plan', 'invoice']);
            $usedGuests = $reservation->guests()->count();
            $isExtra = $usedGuests >= $reservation->guest_quota;
            $amount = $isExtra ? (float) $reservation->member->plan->extra_guest_price : 0;
            $code = 'AABB-'.Str::upper(Str::random(8));

            $guest = Guest::create([
                'reservation_id' => $reservation->id,
                'member_id' => $reservation->member_id,
                'name' => $data['name'],
                'cpf' => BrazilianMasks::formatCpf($data['cpf'] ?? null),
                'is_extra' => $isExtra,
                'amount' => $amount,
                'status' => $isExtra ? 'awaiting_payment' : 'confirmed',
                'invitation_code' => $code,
            ]);

            $invoice = $reservation->invoice;

            if ($isExtra && $invoice) {
                $invoice->increment('amount', $amount);
                $reservation->increment('total_amount', $amount);
            }

            $reservation->member->invitations()->create([
                'guest_id' => $guest->id,
                'invoice_id' => $isExtra ? $invoice?->id : null,
                'type' => 'reservation_guest',
                'code' => $code,
                'valid_for' => $reservation->reservation_date,
                'status' => $isExtra ? 'extra_pending' : 'available',
                'is_extra' => $isExtra,
                'amount' => $amount,
            ]);

            return $guest;
        });
    }

    public function assertAvailable(ReservableSpace $space, CarbonImmutable $date): void
    {
        $alreadyReserved = Reservation::query()
            ->where('reservable_space_id', $space->id)
            ->whereDate('reservation_date', $date->toDateString())
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->exists();

        if ($alreadyReserved) {
            throw ValidationException::withMessages([
                'reservation_date' => 'Este espaço já possui reserva ativa nessa data.',
            ]);
        }
    }
}
