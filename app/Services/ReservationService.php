<?php

namespace App\Services;

use App\Mail\ReservationGuestSplitMail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Support\BrazilianMasks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private readonly BillingService $billingService) {}

    public function createReservation(Member $member, ReservableSpace $space, string $date, array $guests = [], string $paymentMode = 'associado_paga'): Reservation
    {
        $reservationDate = CarbonImmutable::parse($date)->startOfDay();

        if (! in_array($paymentMode, ['associado_paga', 'rateio_email'], true)) {
            throw ValidationException::withMessages([
                'payment_mode' => 'Escolha uma forma valida de pagamento dos convidados.',
            ]);
        }

        $guests = $this->normalizeGuests($guests, $paymentMode === 'rateio_email');

        if ($reservationDate->isPast()) {
            throw ValidationException::withMessages([
                'reservation_date' => 'Escolha uma data futura para a reserva.',
            ]);
        }

        if (count($guests) > $space->capacity) {
            throw ValidationException::withMessages([
                'guests' => 'A lista de convidados nao pode ultrapassar a capacidade do espaco.',
            ]);
        }

        $this->assertAvailable($space, $reservationDate);

        return DB::transaction(function () use ($member, $space, $reservationDate, $guests, $paymentMode) {
            $guestPrice = $space->guestPrice();
            $guestTotal = count($guests) * $guestPrice;
            $basePrice = (float) $space->base_price;
            $memberInvoiceAmount = $paymentMode === 'associado_paga'
                ? $basePrice + $guestTotal
                : $basePrice;
            $status = $memberInvoiceAmount > 0 ? 'open' : 'paid';
            $reservationTotal = $basePrice + $guestTotal;

            $invoice = Invoice::create([
                'member_id' => $member->id,
                'number' => $this->billingService->nextNumber('RES', $member),
                'type' => 'reservation',
                'description' => 'Reserva '.$space->name.' em '.$reservationDate->format('d/m/Y'),
                'amount' => $memberInvoiceAmount,
                'due_date' => now()->addDays(2)->toDateString(),
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'payment_method' => 'Boleto Banco do Brasil / QR App',
                'issued_at' => now(),
                'metadata' => [
                    'space' => $space->name,
                    'pagamento' => $paymentMode,
                    'valor_aluguel' => $basePrice,
                    'valor_convidado' => $guestPrice,
                    'quantidade_convidados' => count($guests),
                    'valor_convidados' => $guestTotal,
                    'valor_total_reserva' => $reservationTotal,
                    'meios_previstos' => ['boleto_banco_do_brasil', 'qr_app', 'cartao_presencial'],
                ],
            ]);

            $reservation = Reservation::create([
                'member_id' => $member->id,
                'reservable_space_id' => $space->id,
                'invoice_id' => $invoice->id,
                'reservation_date' => $reservationDate->toDateString(),
                'starts_at' => $space->startsAt(),
                'ends_at' => $space->endsAt(),
                'status' => $status === 'paid' ? 'confirmed' : 'pending_payment',
                'total_amount' => $reservationTotal,
                'guest_quota' => $space->includedGuests(),
                'confirmed_at' => $status === 'paid' ? now() : null,
                'notes' => $paymentMode === 'rateio_email'
                    ? 'Reserva criada pelo portal com rateio dos convidados.'
                    : 'Reserva criada pelo portal com convidados pagos pelo associado.',
            ]);

            $invoice->update([
                'source_type' => Reservation::class,
                'source_id' => $reservation->id,
            ]);

            foreach ($guests as $guestData) {
                $this->createReservationGuest($reservation, $guestData, $paymentMode, $invoice, $guestPrice);
            }

            return $reservation->fresh(['invoice', 'guests.invitation.invoice']);
        });
    }

    public function addGuest(Reservation $reservation, array $data): Guest
    {
        return $this->addGuests($reservation, [$data])->first();
    }

    public function addGuests(Reservation $reservation, array $guests): Collection
    {
        return DB::transaction(function () use ($reservation, $guests) {
            $reservation->loadMissing(['member.plan', 'invoice', 'space', 'guests']);
            $this->assertGuestListEditable($reservation);

            if (! $reservation->invoice) {
                throw ValidationException::withMessages([
                    'reservation' => 'Esta reserva nao possui cobranca vinculada para atualizar a lista.',
                ]);
            }

            $guests = collect($this->normalizeGuests($guests, true));

            if ($guests->isEmpty()) {
                throw ValidationException::withMessages([
                    'guests' => 'Inclua ao menos um convidado.',
                ]);
            }

            if ($reservation->guests()->count() + $guests->count() > (int) $reservation->space->capacity) {
                throw ValidationException::withMessages([
                    'guests' => 'A lista de convidados ultrapassa a capacidade do espaco.',
                ]);
            }

            $paymentMode = $this->paymentMode($reservation);
            $amount = $reservation->space?->guestPrice() ?? ReservableSpace::DEFAULT_GUEST_PRICE;

            $this->assertGuestChargeCanChange($reservation, $paymentMode, $amount);

            $created = collect();
            foreach ($guests as $guestData) {
                $created->push($this->createReservationGuest($reservation, $guestData, $paymentMode, $reservation->invoice, $amount));
            }

            $this->applyGuestChargeDelta($reservation, $paymentMode, $amount * $created->count(), $created->count());

            return $created;
        });
    }

    public function updateGuest(Reservation $reservation, Guest $guest, array $data): Guest
    {
        return DB::transaction(function () use ($reservation, $guest, $data) {
            $reservation->loadMissing(['member', 'invoice', 'space']);
            $guest->loadMissing(['invitation.invoice']);
            $this->assertGuestBelongsToReservation($reservation, $guest);
            $this->assertGuestListEditable($reservation);
            $this->assertGuestCanBeChanged($guest);

            $normalized = $this->normalizeGuest($data, true);
            $oldEmail = $guest->email;

            $guest->update([
                'name' => $normalized['name'],
                'cpf' => $normalized['cpf'],
                'email' => $normalized['email'],
                'phone' => $normalized['phone'],
                'contact_channel' => $normalized['contact_channel'],
            ]);

            $invitation = $guest->invitation;
            if ($invitation) {
                $invitation->update([
                    'sent_to_email' => $normalized['contact_channel'] === 'email' ? $normalized['email'] : null,
                    'sent_to_phone' => $normalized['contact_channel'] === 'phone' ? $normalized['phone'] : null,
                    'delivery_channel' => $normalized['contact_channel'],
                ]);

                if ($invitation->invoice?->type === 'reservation_guest') {
                    $this->updateGuestInvoiceMetadata($invitation->invoice, $guest->fresh(), $reservation);
                }

                if (
                    $this->paymentMode($reservation) === 'rateio_email'
                    && $normalized['contact_channel'] === 'email'
                    && $invitation->invoice
                    && $invitation->invoice->status !== 'paid'
                    && ($oldEmail !== $normalized['email'] || ! $invitation->emailed_at)
                ) {
                    $this->sendRateioEmail($invitation->fresh(['guest', 'invoice', 'guest.reservation.space']));
                }
            }

            return $guest->fresh(['invitation.invoice']);
        });
    }

    public function deleteGuest(Reservation $reservation, Guest $guest): void
    {
        DB::transaction(function () use ($reservation, $guest) {
            $reservation->loadMissing(['member', 'invoice', 'space']);
            $guest->loadMissing(['invitation.invoice']);
            $this->assertGuestBelongsToReservation($reservation, $guest);
            $this->assertGuestListEditable($reservation);
            $this->assertGuestCanBeChanged($guest);

            $paymentMode = $this->paymentMode($reservation);
            $amount = (float) $guest->amount;
            $guestInvoice = $guest->invitation?->invoice;

            if ($guestInvoice?->status === 'paid') {
                throw ValidationException::withMessages([
                    'guest' => 'Este convidado ja possui cobranca paga e nao pode ser removido pelo portal.',
                ]);
            }

            $this->assertGuestChargeCanChange($reservation, $paymentMode, $amount);

            if ($guestInvoice && $guestInvoice->type === 'reservation_guest') {
                $guestInvoice->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
            }

            $guest->invitation?->update([
                'status' => 'cancelled',
            ]);

            $guest->delete();
            $this->applyGuestChargeDelta($reservation, $paymentMode, -$amount, -1);
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
                'reservation_date' => 'Este espaco ja possui reserva ativa nessa data.',
            ]);
        }
    }

    private function createReservationGuest(Reservation $reservation, array $data, string $paymentMode, Invoice $reservationInvoice, float $guestPrice): Guest
    {
        $code = $this->generateInvitationCode();
        $guestInvoice = null;
        $invoiceIsPaid = $reservationInvoice->status === 'paid';
        $hasIndividualCharge = $paymentMode === 'rateio_email' && $guestPrice > 0;

        $guest = Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $reservation->member_id,
            'name' => $data['name'],
            'cpf' => $data['cpf'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'contact_channel' => $data['contact_channel'],
            'is_extra' => $guestPrice > 0,
            'amount' => $guestPrice,
            'status' => ($hasIndividualCharge || ! $invoiceIsPaid) ? 'awaiting_payment' : 'confirmed',
            'invitation_code' => $code,
        ]);

        if ($hasIndividualCharge) {
            $guestInvoice = Invoice::create([
                'member_id' => $reservation->member_id,
                'number' => $this->billingService->nextNumber('RAT', $reservation->member),
                'type' => 'reservation_guest',
                'description' => 'Rateio de convidado '.$guest->name.' - '.$reservation->space->name.' em '.$reservation->reservation_date->format('d/m/Y'),
                'amount' => $guestPrice,
                'due_date' => now()->addDays(2)->toDateString(),
                'status' => 'open',
                'payment_method' => 'Boleto Banco do Brasil / QR App',
                'issued_at' => now(),
                'source_type' => Guest::class,
                'source_id' => $guest->id,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'guest_name' => $guest->name,
                    'guest_email' => $guest->email,
                    'guest_phone' => $guest->phone,
                    'contact_channel' => $guest->contact_channel,
                    'space' => $reservation->space->name,
                    'payment_mode' => 'rateio_email',
                ],
            ]);
        }

        $invitationInvoice = $hasIndividualCharge ? $guestInvoice : $reservationInvoice;
        $invitationStatus = ($hasIndividualCharge || ! $invoiceIsPaid) ? 'payment_pending' : 'available';

        $invitation = $reservation->member->invitations()->create([
            'guest_id' => $guest->id,
            'invoice_id' => $invitationInvoice?->id,
            'type' => 'reservation_guest',
            'code' => $code,
            'sent_to_email' => $guest->contact_channel === 'email' ? $guest->email : null,
            'sent_to_phone' => $guest->contact_channel === 'phone' ? $guest->phone : null,
            'delivery_channel' => $guest->contact_channel,
            'valid_for' => $reservation->reservation_date,
            'status' => $invitationStatus,
            'is_extra' => $guestPrice > 0,
            'amount' => $guestPrice,
        ]);

        if ($hasIndividualCharge && $guest->contact_channel === 'email' && $guest->email && $guestInvoice) {
            $this->sendRateioEmail($invitation->loadMissing(['guest', 'invoice', 'guest.reservation.space']));
        }

        return $guest;
    }

    private function normalizeGuests(array $guests, bool $requireContact = false): array
    {
        return collect($guests)
            ->filter(fn (array $guest): bool => filled($guest['name'] ?? null))
            ->values()
            ->map(fn (array $guest, int $index): array => $this->normalizeGuest($guest, $requireContact, 'guests.'.$index.'.contact'))
            ->values()
            ->all();
    }

    private function normalizeGuest(array $guest, bool $requireContact = false, string $contactAttribute = 'contact'): array
    {
        $name = trim((string) ($guest['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Informe o nome do convidado.',
            ]);
        }

        if (filled($guest['cpf'] ?? null) && ! BrazilianMasks::hasCpfLength($guest['cpf'])) {
            throw ValidationException::withMessages([
                'cpf' => 'Informe um CPF com 11 numeros.',
            ]);
        }

        $contact = $this->normalizeContact($guest, $requireContact, $contactAttribute);

        return [
            'name' => $name,
            'cpf' => BrazilianMasks::formatCpf($guest['cpf'] ?? null),
            'email' => $contact['email'],
            'phone' => $contact['phone'],
            'contact_channel' => $contact['contact_channel'],
        ];
    }

    private function normalizeContact(array $guest, bool $required, string $attribute): array
    {
        $contact = trim((string) ($guest['contact'] ?? ''));
        $email = blank($guest['email'] ?? null) ? null : Str::lower(trim((string) $guest['email']));
        $phone = blank($guest['phone'] ?? null) ? null : trim((string) $guest['phone']);

        if ($contact !== '') {
            $email = null;
            $phone = null;

            if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                $email = Str::lower($contact);
            } else {
                $phone = $this->normalizePhone($contact, $attribute);
            }
        }

        if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Informe um e-mail valido para o convidado.',
            ]);
        }

        if ($phone) {
            $phone = $this->normalizePhone($phone, 'phone');
        }

        $channel = $email ? 'email' : ($phone ? 'phone' : null);

        if ($required && ! $channel) {
            throw ValidationException::withMessages([
                $attribute => 'Informe e-mail ou celular com DDD para o convidado.',
            ]);
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'contact_channel' => $channel,
        ];
    }

    private function normalizePhone(string $value, string $attribute): string
    {
        $digits = BrazilianMasks::onlyDigits($value);

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $digits = substr($digits, 2);
        }

        if (! in_array(strlen($digits), [10, 11], true)) {
            throw ValidationException::withMessages([
                $attribute => 'Informe um celular com DDD ou um e-mail valido.',
            ]);
        }

        return BrazilianMasks::formatPhone($digits);
    }

    private function paymentMode(Reservation $reservation): string
    {
        return $reservation->invoice?->metadata['pagamento'] ?? 'associado_paga';
    }

    private function assertGuestBelongsToReservation(Reservation $reservation, Guest $guest): void
    {
        if ((int) $guest->reservation_id !== (int) $reservation->id) {
            throw ValidationException::withMessages([
                'guest' => 'Convidado nao pertence a esta reserva.',
            ]);
        }
    }

    private function assertGuestListEditable(Reservation $reservation): void
    {
        if (in_array($reservation->status, ['cancelled', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Esta reserva nao permite alteracao de convidados.',
            ]);
        }

        if ($reservation->reservation_date->isPast() && ! $reservation->reservation_date->isToday()) {
            throw ValidationException::withMessages([
                'reservation' => 'A lista so pode ser alterada antes da data da reserva.',
            ]);
        }
    }

    private function assertGuestCanBeChanged(Guest $guest): void
    {
        if ($guest->status === 'used' || $guest->checked_in_at || $guest->invitation?->status === 'used') {
            throw ValidationException::withMessages([
                'guest' => 'Convidados que ja passaram pela portaria nao podem ser alterados.',
            ]);
        }
    }

    private function assertGuestChargeCanChange(Reservation $reservation, string $paymentMode, float $amount): void
    {
        if ($paymentMode === 'associado_paga' && $amount > 0 && ! in_array($reservation->invoice?->status, ['open', 'pending', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Esta reserva ja teve pagamento analisado. Ajustes financeiros devem ser feitos pela equipe.',
            ]);
        }
    }

    private function applyGuestChargeDelta(Reservation $reservation, string $paymentMode, float $amountDelta, int $countDelta): void
    {
        $reservation->refresh();
        $reservation->forceFill([
            'total_amount' => max(0, (float) $reservation->total_amount + $amountDelta),
        ])->save();

        $invoice = $reservation->invoice;
        if (! $invoice) {
            return;
        }

        $metadata = $invoice->metadata ?? [];
        $metadata['quantidade_convidados'] = max(0, (int) ($metadata['quantidade_convidados'] ?? 0) + $countDelta);
        $metadata['valor_convidados'] = max(0, (float) ($metadata['valor_convidados'] ?? 0) + $amountDelta);
        $metadata['valor_total_reserva'] = max(0, (float) ($metadata['valor_total_reserva'] ?? (float) $reservation->total_amount) + $amountDelta);

        $updates = ['metadata' => $metadata];

        if ($paymentMode === 'associado_paga') {
            $updates['amount'] = max(0, (float) $invoice->amount + $amountDelta);
        }

        $invoice->update($updates);
    }

    private function updateGuestInvoiceMetadata(Invoice $invoice, Guest $guest, Reservation $reservation): void
    {
        $metadata = $invoice->metadata ?? [];
        $metadata['guest_name'] = $guest->name;
        $metadata['guest_email'] = $guest->email;
        $metadata['guest_phone'] = $guest->phone;
        $metadata['contact_channel'] = $guest->contact_channel;

        $invoice->update([
            'description' => 'Rateio de convidado '.$guest->name.' - '.$reservation->space->name.' em '.$reservation->reservation_date->format('d/m/Y'),
            'metadata' => $metadata,
        ]);
    }

    private function sendRateioEmail(Invitation $invitation): void
    {
        if (! $invitation->sent_to_email || ! $invitation->invoice) {
            return;
        }

        Mail::to($invitation->sent_to_email)->send(new ReservationGuestSplitMail($invitation->loadMissing(['guest', 'invoice', 'guest.reservation.space'])));
        $invitation->update(['emailed_at' => now()]);
    }

    private function generateInvitationCode(): string
    {
        do {
            $code = 'AABB-'.Str::upper(Str::random(8));
        } while (Invitation::where('code', $code)->exists());

        return $code;
    }
}
