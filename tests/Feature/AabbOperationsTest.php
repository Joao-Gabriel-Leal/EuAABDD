<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Mail\ClubInvitationMail;
use App\Mail\ReservationGuestSplitMail;
use App\Models\Announcement;
use App\Models\Benefit;
use App\Models\Dependent;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AabbOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_site_loads_with_club_positioning(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('AABB')
            ->assertSee('Quero me associar');
    }

    public function test_public_direct_signup_creates_pending_member_user_and_initial_invoice(): void
    {
        $plan = Plan::where('name', 'Efetivo')->firstOrFail();

        $this->post(route('proposal.store'), [
            'name' => 'Novo Associado Direto',
            'cpf' => '12345678901',
            'email' => 'novo.direto@aabb.demo',
            'phone' => '61999999999',
            'plan_id' => $plan->id,
            'category' => 'Familiar',
            'password' => 'senha-aabb-2026',
            'password_confirmation' => 'senha-aabb-2026',
        ])->assertRedirect(route('portal.dashboard'));

        $member = Member::where('email', 'novo.direto@aabb.demo')->firstOrFail();
        $memberUser = User::where('email', 'novo.direto@aabb.demo')->firstOrFail();
        $invoice = Invoice::where('member_id', $member->id)->where('type', 'membership_initial')->firstOrFail();

        $this->assertSame('pending_payment', $member->status);
        $this->assertSame('member', $memberUser->role);
        $this->assertSame($member->id, $memberUser->member_id);
        $this->assertSame('open', $invoice->status);
        $this->assertSame(239.0, (float) $invoice->amount);

        $this->assertDatabaseHas('members', [
            'email' => 'novo.direto@aabb.demo',
            'cpf' => '123.456.789-01',
            'phone' => '(61) 99999-9999',
        ]);

        $this->post(route('proposal.store'), [
            'name' => 'Duplicado CPF',
            'cpf' => '12345678901',
            'email' => 'duplicado@aabb.demo',
            'phone' => '61999999998',
            'plan_id' => $plan->id,
            'category' => 'Familiar',
            'password' => 'senha-aabb-2026',
            'password_confirmation' => 'senha-aabb-2026',
        ])->assertSessionHasErrors(['cpf']);

        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();

        $this->actingAs($portaria)
            ->get(route('member-card.verify', $member->card_token))
            ->assertStatus(200)
            ->assertSee('Acesso bloqueado')
            ->assertSee('Adesao aguardando pagamento');

        $this->actingAs($memberUser)
            ->post(route('portal.pay.demo', $invoice))
            ->assertRedirect();

        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);

        $this->actingAs($portaria)
            ->get(route('member-card.verify', $member->fresh()->card_token))
            ->assertStatus(200)
            ->assertSee('Acesso permitido');
    }

    public function test_member_reservation_blocks_schedule_conflicts_and_finance_confirms_it(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $date = now()->addDays(40)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
            ])
            ->assertRedirect();

        $reservation = Reservation::whereDate('reservation_date', $date)->firstOrFail();

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
            ])
            ->assertSessionHasErrors('reservation_date');

        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('team.invoices.pay', $reservation->invoice), [
                'amount' => $reservation->invoice->amount,
                'method' => 'QR App AABB',
                'paid_at' => now()->format('Y-m-d'),
                'manual_reference' => 'TESTE-BAIXA',
            ])
            ->assertRedirect();

        $this->assertSame('paid', $reservation->invoice->fresh()->status);
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_reservation_guest_list_with_associate_payment_charges_and_releases_invites(): void
    {
        Mail::fake();
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'base_price' => 100,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);
        $date = now()->addDays(50)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
                'payment_mode' => 'associado_paga',
                'guests' => [
                    ['name' => 'Convidado Pago Um', 'cpf' => '10110110110', 'email' => 'pago1@email.demo'],
                    ['name' => 'Convidado Pago Dois', 'cpf' => '20220220220', 'email' => 'pago2@email.demo'],
                ],
            ])
            ->assertRedirect();

        $reservation = Reservation::with(['invoice', 'guests.invitation'])
            ->whereDate('reservation_date', $date)
            ->firstOrFail();

        $this->assertSame(128.0, (float) $reservation->invoice->amount);
        $this->assertSame(128.0, (float) $reservation->total_amount);
        $this->assertSame(2, $reservation->guests->count());
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->status === 'awaiting_payment'));
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->invitation->status === 'payment_pending'));
        Mail::assertNothingSent();

        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();
        $this->actingAs($portaria)
            ->post(route('team.access.register'), [
                'code' => $reservation->guests->first()->invitation->code,
                'gate' => 'Portaria reserva pendente',
            ])
            ->assertRedirect();

        $this->assertStringStartsWith('blocked:', AccessLog::latest('id')->firstOrFail()->status);

        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $this->actingAs($finance)
            ->post(route('team.invoices.pay', $reservation->invoice), [
                'amount' => $reservation->invoice->amount,
                'method' => 'QR App AABB',
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $reservation->refresh()->load(['guests.invitation']);

        $this->assertSame('confirmed', $reservation->status);
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->status === 'confirmed'));
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->invitation->status === 'available'));
        Mail::assertSent(ClubInvitationMail::class, 2);
    }

    public function test_reservation_rateio_creates_guest_invoices_and_emails(): void
    {
        Mail::fake();
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'base_price' => 200,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);
        $date = now()->addDays(55)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
                'payment_mode' => 'rateio_email',
                'guests' => [
                    ['name' => 'Rateio Um', 'cpf' => '30330330330', 'email' => 'rateio1@email.demo'],
                    ['name' => 'Rateio Dois', 'cpf' => '40440440440', 'email' => 'rateio2@email.demo'],
                ],
            ])
            ->assertRedirect();

        $reservation = Reservation::with(['invoice', 'guests.invitation.invoice'])
            ->whereDate('reservation_date', $date)
            ->firstOrFail();
        $guestInvoices = Invoice::where('member_id', $memberUser->member_id)
            ->where('type', 'reservation_guest')
            ->get();

        $this->assertSame(200.0, (float) $reservation->invoice->amount);
        $this->assertSame(228.0, (float) $reservation->total_amount);
        $this->assertCount(2, $guestInvoices);
        $this->assertTrue($guestInvoices->every(fn (Invoice $invoice): bool => (float) $invoice->amount === 14.0 && $invoice->status === 'open'));
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->invitation->status === 'payment_pending'));
        Mail::assertSent(ReservationGuestSplitMail::class, 2);

        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $this->actingAs($finance)
            ->post(route('team.invoices.pay', $reservation->invoice), [
                'amount' => $reservation->invoice->amount,
                'method' => 'QR App AABB',
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $reservation->refresh()->load(['guests.invitation.invoice']);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertTrue($reservation->guests->every(fn (Guest $guest): bool => $guest->invitation->status === 'payment_pending'));

        $firstGuestInvoice = $reservation->guests->first()->invitation->invoice;
        $this->actingAs($finance)
            ->post(route('team.invoices.pay', $firstGuestInvoice), [
                'amount' => $firstGuestInvoice->amount,
                'method' => 'QR App AABB',
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $firstGuest = $reservation->guests->first()->fresh(['invitation']);
        $this->assertSame('confirmed', $firstGuest->status);
        $this->assertSame('available', $firstGuest->invitation->status);
    }

    public function test_reservation_rateio_requires_contact_and_capacity_is_enforced(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'capacity' => 1,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => now()->addDays(60)->format('Y-m-d'),
                'payment_mode' => 'rateio_email',
                'guests' => [
                    ['name' => 'Sem Contato'],
                ],
            ])
            ->assertSessionHasErrors('guests.0.contact');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => now()->addDays(61)->format('Y-m-d'),
                'payment_mode' => 'associado_paga',
                'guests' => [
                    ['name' => 'Convidado Um'],
                    ['name' => 'Convidado Dois'],
                ],
            ])
            ->assertSessionHasErrors('guests');
    }

    public function test_portal_dashboard_uses_clean_tab_workspace(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();

        $this->actingAs($memberUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Portal do associado')
            ->assertSee('member-card-flip', false)
            ->assertSee('data-tab-workspace', false)
            ->assertSee('data-default-tab="financeiro"', false)
            ->assertSee('data-tab-target="financeiro"', false)
            ->assertSee('data-tab-target="reservas"', false)
            ->assertSee('data-tab-target="convites"', false)
            ->assertSee('data-tab-target="familia"', false)
            ->assertSee('id="portal-panel-financeiro"', false)
            ->assertSee('id="portal-panel-reservas"', false)
            ->assertSee('id="portal-panel-convites"', false)
            ->assertSee('id="portal-panel-familia"', false)
            ->assertSee('role="tabpanel"', false);
    }

    public function test_portal_reservation_map_and_clean_reservation_modal_are_visible(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $reservation = Reservation::with('space')
            ->where('member_id', $memberUser->member_id)
            ->firstOrFail();

        $space = ReservableSpace::where('is_active', true)
            ->get()
            ->first(fn (ReservableSpace $space): bool => ($space->rules['reserva'] ?? true) !== false);

        $this->actingAs($memberUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee($reservation->space->name)
            ->assertSee('data-tab-panel="reservas"', false)
            ->assertSee('data-space-map-pin', false)
            ->assertSee('data-space-id="'.$space->id.'"', false)
            ->assertSee('reservation-map-legend__number', false)
            ->assertSee('data-guest-price="'.$space->guestPrice().'"', false)
            ->assertSee('data-reservation-list="future"', false)
            ->assertSee('data-open-reservation-modal="'.$reservation->id.'"', false)
            ->assertSee('data-reservation-modal="'.$reservation->id.'"', false)
            ->assertSee('Abrir reserva')
            ->assertSee(route('portal.reservation-guests.store', $reservation), false)
            ->assertSee(route('portal.reservation-guests.template'), false)
            ->assertSee('Importar CSV')
            ->assertDontSee('data-reservation-guest-list', false)
            ->assertDontSee('Salvar convidado');
    }

    public function test_reservation_guest_modal_uses_visual_status_tones(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $member = $memberUser->member()->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $date = now()->addDays(99)->toDateString();

        $reservationInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-RES-TONE',
            'type' => 'reservation',
            'description' => 'Reserva para teste de tons',
            'amount' => 200,
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'issued_at' => now(),
            'metadata' => ['pagamento' => 'associado_paga'],
        ]);
        $reservation = Reservation::create([
            'member_id' => $member->id,
            'reservable_space_id' => $space->id,
            'invoice_id' => $reservationInvoice->id,
            'reservation_date' => $date,
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'confirmed',
            'total_amount' => 200,
            'guest_quota' => 4,
            'confirmed_at' => now(),
        ]);

        $successGuest = Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $member->id,
            'name' => 'Convidado Liberado',
            'status' => 'confirmed',
            'amount' => 14,
            'invitation_code' => 'AABB-TONE-SUCCESS',
        ]);
        Invitation::create([
            'member_id' => $member->id,
            'guest_id' => $successGuest->id,
            'invoice_id' => $reservationInvoice->id,
            'type' => 'reservation_guest',
            'code' => 'AABB-TONE-SUCCESS',
            'valid_for' => $date,
            'status' => 'available',
            'amount' => 14,
        ]);

        $pendingInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-RAT-TONE',
            'type' => 'reservation_guest',
            'description' => 'Rateio pendente para teste',
            'amount' => 14,
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'open',
            'issued_at' => now(),
        ]);
        $pendingGuest = Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $member->id,
            'name' => 'Convidado Pendente',
            'status' => 'awaiting_payment',
            'amount' => 14,
            'invitation_code' => 'AABB-TONE-WARNING',
        ]);
        Invitation::create([
            'member_id' => $member->id,
            'guest_id' => $pendingGuest->id,
            'invoice_id' => $pendingInvoice->id,
            'type' => 'reservation_guest',
            'code' => 'AABB-TONE-WARNING',
            'valid_for' => $date,
            'status' => 'payment_pending',
            'amount' => 14,
        ]);

        $usedGuest = Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $member->id,
            'name' => 'Convidado Usado',
            'status' => 'used',
            'amount' => 14,
            'invitation_code' => 'AABB-TONE-NEUTRAL',
            'checked_in_at' => now(),
        ]);
        Invitation::create([
            'member_id' => $member->id,
            'guest_id' => $usedGuest->id,
            'type' => 'reservation_guest',
            'code' => 'AABB-TONE-NEUTRAL',
            'valid_for' => $date,
            'status' => 'used',
            'amount' => 14,
            'used_at' => now(),
        ]);

        $dangerGuest = Guest::create([
            'reservation_id' => $reservation->id,
            'member_id' => $member->id,
            'name' => 'Convidado Estranho',
            'status' => 'sync_error',
            'amount' => 14,
            'invitation_code' => 'AABB-TONE-DANGER',
        ]);
        Invitation::create([
            'member_id' => $member->id,
            'guest_id' => $dangerGuest->id,
            'type' => 'reservation_guest',
            'code' => 'AABB-TONE-DANGER',
            'valid_for' => $date,
            'status' => 'cancelled',
            'amount' => 14,
        ]);

        $this->actingAs($memberUser)
            ->get(route('portal.dashboard').'#reservas')
            ->assertOk()
            ->assertSee('reservation-status-badge reservation-status-badge--success">Liberado', false)
            ->assertSee('reservation-status-detail reservation-status-detail--success">Codigo AABB-TONE-SUCCESS | Disponivel', false)
            ->assertSee('reservation-status-badge reservation-status-badge--warning">Aguardando pagamento', false)
            ->assertSee('reservation-status-detail reservation-status-detail--warning">AABB-RAT-TONE | Aberta', false)
            ->assertSee('reservation-status-badge reservation-status-badge--neutral">Usado', false)
            ->assertSee('reservation-status-detail reservation-status-detail--neutral">Codigo AABB-TONE-NEUTRAL | Usado', false)
            ->assertSee('reservation-status-badge reservation-status-badge--danger">Sync error', false)
            ->assertSee('reservation-status-detail reservation-status-detail--danger">Codigo AABB-TONE-DANGER | Cancelado', false);
    }

    public function test_portal_reservations_show_future_cards_and_keep_past_collapsed(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $member = $memberUser->member()->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();

        $futureInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-RES-FUTURE',
            'type' => 'reservation',
            'description' => 'Reserva futura teste',
            'amount' => 120,
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'open',
            'issued_at' => now(),
            'metadata' => ['pagamento' => 'associado_paga'],
        ]);
        $futureReservation = Reservation::create([
            'member_id' => $member->id,
            'reservable_space_id' => $space->id,
            'invoice_id' => $futureInvoice->id,
            'reservation_date' => now()->addDays(90)->toDateString(),
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'pending_payment',
            'total_amount' => 120,
            'guest_quota' => 4,
        ]);

        $pastInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-RES-PAST',
            'type' => 'reservation',
            'description' => 'Reserva passada teste',
            'amount' => 120,
            'due_date' => now()->subDays(6)->toDateString(),
            'status' => 'paid',
            'paid_at' => now()->subDays(5)->toDateString(),
            'issued_at' => now()->subDays(8),
            'metadata' => ['pagamento' => 'associado_paga'],
        ]);
        $pastReservation = Reservation::create([
            'member_id' => $member->id,
            'reservable_space_id' => $space->id,
            'invoice_id' => $pastInvoice->id,
            'reservation_date' => now()->subDays(4)->toDateString(),
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'confirmed',
            'total_amount' => 120,
            'guest_quota' => 4,
        ]);

        $cancelledReservation = Reservation::create([
            'member_id' => $member->id,
            'reservable_space_id' => $space->id,
            'reservation_date' => now()->addDays(91)->toDateString(),
            'starts_at' => '12:00',
            'ends_at' => '18:00',
            'status' => 'cancelled',
            'total_amount' => 120,
            'guest_quota' => 4,
        ]);

        $response = $this->actingAs($memberUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('data-reservation-list="future"', false)
            ->assertSee('data-past-reservations hidden', false)
            ->assertSee('Ver passadas')
            ->assertSee('data-open-reservation-modal="'.$futureReservation->id.'"', false)
            ->assertSee('data-open-reservation-modal="'.$pastReservation->id.'"', false)
            ->assertDontSee('data-open-reservation-modal="'.$cancelledReservation->id.'"', false);

        $content = $response->getContent();
        $futureList = substr($content, strpos($content, 'data-reservation-list="future"'), strpos($content, 'data-past-reservations hidden') - strpos($content, 'data-reservation-list="future"'));

        $this->assertStringContainsString('data-open-reservation-modal="'.$futureReservation->id.'"', $futureList);
        $this->assertStringNotContainsString('data-open-reservation-modal="'.$pastReservation->id.'"', $futureList);
    }

    public function test_reservation_guest_csv_template_and_import_detects_email_and_phone(): void
    {
        Mail::fake();
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'capacity' => 10,
            'base_price' => 200,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);
        $date = now()->addDays(70)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->get(route('portal.reservation-guests.template'))
            ->assertOk()
            ->assertSee('nome,cpf,contato');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
                'payment_mode' => 'rateio_email',
            ])
            ->assertRedirect();

        $reservation = Reservation::with('invoice')
            ->where('member_id', $memberUser->member_id)
            ->whereDate('reservation_date', $date)
            ->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'convidados.csv',
            "nome,cpf,contato\nConvidado Email,50550550550,rateio-import@email.demo\nConvidado Celular,,61999999999\n"
        );

        $this->actingAs($memberUser)
            ->post(route('portal.reservation-guests.import', $reservation), [
                'guest_file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('portal_status');

        $reservation->refresh()->load(['invoice', 'guests.invitation.invoice']);
        $emailGuest = $reservation->guests->firstWhere('name', 'Convidado Email');
        $phoneGuest = $reservation->guests->firstWhere('name', 'Convidado Celular');

        $this->assertSame(2, $reservation->guests->count());
        $this->assertSame('email', $emailGuest->contact_channel);
        $this->assertSame('rateio-import@email.demo', $emailGuest->email);
        $this->assertSame('phone', $phoneGuest->contact_channel);
        $this->assertSame('(61) 99999-9999', $phoneGuest->phone);
        $this->assertSame('phone', $phoneGuest->invitation->delivery_channel);
        $this->assertNotNull($phoneGuest->invitation->whatsappUrl());
        $this->assertSame(228.0, (float) $reservation->total_amount);
        $this->assertSame(200.0, (float) $reservation->invoice->amount);
        $this->assertCount(2, Invoice::where('member_id', $memberUser->member_id)->where('type', 'reservation_guest')->get());
        Mail::assertSent(ReservationGuestSplitMail::class, 1);
    }

    public function test_reservation_guest_csv_import_blocks_capacity_overflow(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'capacity' => 1,
            'base_price' => 120,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);
        $date = now()->addDays(71)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
                'payment_mode' => 'associado_paga',
            ])
            ->assertRedirect();

        $reservation = Reservation::where('member_id', $memberUser->member_id)
            ->whereDate('reservation_date', $date)
            ->firstOrFail();
        $file = UploadedFile::fake()->createWithContent(
            'convidados.csv',
            "nome,cpf,contato\nConvidado Um,,61999999991\nConvidado Dois,,61999999992\n"
        );

        $this->actingAs($memberUser)
            ->post(route('portal.reservation-guests.import', $reservation), [
                'guest_file' => $file,
            ])
            ->assertSessionHasErrors('guests');

        $this->assertSame(0, $reservation->guests()->count());
    }

    public function test_member_can_edit_and_remove_reservation_guest_before_use(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $space = ReservableSpace::where('type', 'churrasqueira')->firstOrFail();
        $space->update([
            'base_price' => 120,
            'rules' => $space->mergeOperationalRules(['guest_price' => 14]),
        ]);
        $date = now()->addDays(72)->format('Y-m-d');

        $this->actingAs($memberUser)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $date,
                'payment_mode' => 'associado_paga',
            ])
            ->assertRedirect();

        $reservation = Reservation::with('invoice')
            ->where('member_id', $memberUser->member_id)
            ->whereDate('reservation_date', $date)
            ->firstOrFail();

        $this->actingAs($memberUser)
            ->post(route('portal.reservation-guests.store', $reservation), [
                'name' => 'Convidado Editavel',
                'cpf' => '60660660660',
                'contact' => '61999999988',
            ])
            ->assertRedirect();

        $guest = Guest::where('reservation_id', $reservation->id)->where('name', 'Convidado Editavel')->firstOrFail();
        $this->assertSame(134.0, (float) $reservation->fresh()->total_amount);
        $this->assertSame(134.0, (float) $reservation->invoice->fresh()->amount);

        $this->actingAs($memberUser)
            ->patch(route('portal.reservation-guests.update', [$reservation, $guest]), [
                'name' => 'Convidado Atualizado',
                'cpf' => '60660660661',
                'contact' => 'atualizado@email.demo',
            ])
            ->assertRedirect();

        $guest->refresh();
        $this->assertSame('Convidado Atualizado', $guest->name);
        $this->assertSame('email', $guest->contact_channel);
        $this->assertSame('atualizado@email.demo', $guest->email);

        $this->actingAs($memberUser)
            ->delete(route('portal.reservation-guests.destroy', [$reservation, $guest]))
            ->assertRedirect();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
        $this->assertSame(120.0, (float) $reservation->fresh()->total_amount);
        $this->assertSame(120.0, (float) $reservation->invoice->fresh()->amount);
    }

    public function test_member_can_register_dependents_from_portal_and_monthly_billing_includes_extra_fee(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $member = $memberUser->member()->with('plan')->firstOrFail();
        $member->plan->update([
            'included_dependents' => $member->activeDependents()->count() + 1,
            'dependent_extra_price' => 45,
        ]);
        $member->load('plan');

        $this->actingAs($memberUser)
            ->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee('data-tab-target="familia"', false)
            ->assertSee('Dependentes')
            ->assertSee('Cadastrar dependente');

        $this->actingAs($memberUser)
            ->post(route('portal.dependents.store'), [
                'name' => 'Dependente Cortesia',
                'cpf' => '12312312312',
                'birthdate' => now()->subYears(8)->format('Y-m-d'),
                'relationship' => 'Filho(a)',
            ])
            ->assertRedirect()
            ->assertSessionHas('portal_status');

        $freeDependent = Dependent::where('member_id', $member->id)
            ->where('name', 'Dependente Cortesia')
            ->firstOrFail();

        $this->assertSame('123.123.123-12', $freeDependent->cpf);
        $this->assertTrue($freeDependent->is_free);
        $this->assertSame(0.0, (float) $freeDependent->monthly_fee);
        $this->assertSame('active', $freeDependent->status);
        $this->assertSame('allowed', $freeDependent->access_status);

        $this->actingAs($memberUser)
            ->post(route('portal.dependents.store'), [
                'name' => 'Dependente Taxado',
                'cpf' => '12312312313',
                'birthdate' => now()->subYears(6)->format('Y-m-d'),
                'relationship' => 'Filho(a)',
            ])
            ->assertRedirect();

        $extraDependent = Dependent::where('member_id', $member->id)
            ->where('name', 'Dependente Taxado')
            ->firstOrFail();

        $this->assertFalse($extraDependent->is_free);
        $this->assertSame((float) $member->plan->dependent_extra_price, (float) $extraDependent->monthly_fee);

        $this->actingAs($memberUser)
            ->post(route('portal.dependents.store'), [
                'name' => 'CPF Repetido',
                'cpf' => $member->cpf,
            ])
            ->assertSessionHasErrors('cpf');

        $pendingMember = Member::create([
            'plan_id' => $member->plan_id,
            'membership_code' => 'AABB-PEND-TESTE',
            'name' => 'Associado Pendente Teste',
            'cpf' => '987.654.321-00',
            'email' => 'pendente.dependente@aabb.demo',
            'status' => 'pending_payment',
            'category' => 'Familiar',
            'billing_due_day' => $member->plan->monthly_due_day,
            'membership_type' => 'associate',
            'joined_at' => now(),
        ]);
        $pendingUser = User::factory()->create([
            'name' => $pendingMember->name,
            'email' => $pendingMember->email,
            'role' => 'member',
            'member_id' => $pendingMember->id,
        ]);

        $this->actingAs($pendingUser)
            ->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee('Disponivel apos pagamento da primeira mensalidade.')
            ->assertDontSee('Cadastrar dependente');

        $this->actingAs($pendingUser)
            ->post(route('portal.dependents.store'), [
                'name' => 'Dependente Bloqueado',
            ])
            ->assertForbidden();

        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $this->actingAs($finance)
            ->post(route('team.billing.monthly'), ['month' => 7, 'year' => 2026])
            ->assertRedirect();

        $invoice = Invoice::where('member_id', $member->id)
            ->where('type', 'monthly')
            ->whereDate('billing_month', '2026-07-01')
            ->firstOrFail();
        $expectedAmount = $member->monthlyAmount() + (float) $extraDependent->monthly_fee;

        $this->assertSame($expectedAmount, (float) $invoice->amount);
        $this->assertSame((float) $extraDependent->monthly_fee, (float) $invoice->metadata['valor_dependentes']);
    }

    public function test_monthly_billing_generation_is_idempotent(): void
    {
        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();

        $this->actingAs($finance)
            ->post(route('team.billing.monthly'), ['month' => 6, 'year' => 2026])
            ->assertRedirect();

        $firstCount = Invoice::where('type', 'monthly')
            ->whereDate('billing_month', '2026-06-01')
            ->count();

        $this->actingAs($finance)
            ->post(route('team.billing.monthly'), ['month' => 6, 'year' => 2026])
            ->assertRedirect();

        $this->assertSame($firstCount, Invoice::where('type', 'monthly')->whereDate('billing_month', '2026-06-01')->count());
        $this->assertSame(Member::where('status', 'active')->count(), $firstCount);
    }

    public function test_club_invitation_quota_creates_extra_charge_and_access_log(): void
    {
        Mail::fake();
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $reservationInvitation = Invitation::with('guest.reservation.space')
            ->where('member_id', $memberUser->member_id)
            ->where('type', 'reservation_guest')
            ->firstOrFail();

        $this->actingAs($memberUser)
            ->post(route('portal.invitations.store'), [
                'name' => 'Convidado com email',
                'cpf' => '000.000.000-01',
                'email' => 'convidado@email.demo',
                'valid_for' => now()->addDay()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $emailedInvitation = Invitation::where('sent_to_email', 'convidado@email.demo')->firstOrFail();
        $this->assertSame('convidado@email.demo', $emailedInvitation->guest->email);
        $this->assertNotNull($emailedInvitation->emailed_at);
        Mail::assertSentCount(1);

        $this->actingAs($memberUser)
            ->get(route('portal.dashboard'))
            ->assertStatus(200)
            ->assertSee($emailedInvitation->code)
            ->assertSee($reservationInvitation->code)
            ->assertSee('Reserva: '.$reservationInvitation->guest->reservation->space->name)
            ->assertSee('1/4')
            ->assertSee('Copiar codigo')
            ->assertSee('Compartilhar');

        for ($i = 2; $i <= 5; $i++) {
            $this->actingAs($memberUser)
                ->post(route('portal.invitations.store'), [
                    'name' => 'Convidado '.$i,
                    'cpf' => '000.000.000-0'.$i,
                    'valid_for' => now()->addDay()->format('Y-m-d'),
                ])
                ->assertRedirect();
        }

        $extra = Invitation::where('type', 'club_access')->where('is_extra', true)->firstOrFail();

        $this->assertSame('extra_pending', $extra->status);
        $this->assertNotNull($extra->invoice_id);
        $this->assertSame('open', $extra->invoice->status);

        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $this->actingAs($finance)->post(route('team.invoices.pay', $extra->invoice), [
            'amount' => $extra->invoice->amount,
            'method' => 'Boleto BRB',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();
        $this->actingAs($portaria)
            ->post(route('team.access.register'), [
                'code' => $extra->fresh()->code,
                'gate' => 'Portaria teste',
            ])
            ->assertRedirect();

        $this->assertSame('used', $extra->fresh()->status);
        $this->assertTrue(AccessLog::where('invitation_id', $extra->id)->where('status', 'allowed')->exists());
    }

    public function test_secretariat_imports_members_and_stock_movement_updates_balance(): void
    {
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();
        $csv = "nome;cpf;email;telefone;plano;categoria;dependente;cpf_dependente\n";
        $csv .= "Teste Importado;999.888.777-66;importado@aabb.demo;(61) 99999-0000;Efetivo;Familiar;Filho Importado;999.888.777-00\n";

        $this->actingAs($secretaria)
            ->post(route('team.members.import'), [
                'file' => UploadedFile::fake()->createWithContent('associados.csv', $csv),
            ])
            ->assertRedirect();

        $member = Member::where('cpf', '999.888.777-66')->firstOrFail();
        $this->assertSame('Teste Importado', $member->name);
        $this->assertTrue($member->dependents()->where('name', 'Filho Importado')->exists());

        $product = Product::firstOrFail();
        $initial = $product->quantity;

        $this->actingAs(User::where('email', 'equipe@aabb.demo')->firstOrFail())
            ->post(route('team.stock.move', $product), [
                'type' => 'exit',
                'quantity' => 2,
                'reason' => 'Teste automatizado',
            ])
            ->assertRedirect();

        $this->assertSame($initial - 2, $product->fresh()->quantity);
    }

    public function test_secretariat_can_manage_manual_proposals_from_team_dashboard(): void
    {
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();
        $plan = Plan::where('name', 'Comunitario')->firstOrFail();

        $this->actingAs($secretaria)
            ->post(route('team.proposals.store'), [
                'plan_id' => $plan->id,
                'name' => 'Familia Manual Teste',
                'cpf' => '77766655544',
                'email' => 'manual.proposta@aabb.demo',
                'phone' => '61999998888',
                'status' => 'analysis',
                'signature_status' => 'pending',
                'notes' => 'Cadastro manual pelo painel da equipe.',
            ])
            ->assertRedirect();

        $proposal = Proposal::where('email', 'manual.proposta@aabb.demo')->firstOrFail();

        $this->assertSame('777.666.555-44', $proposal->cpf);
        $this->assertSame('(61) 99999-8888', $proposal->phone);

        $this->actingAs($secretaria)
            ->put(route('team.proposals.update', $proposal), [
                'plan_id' => $plan->id,
                'name' => 'Familia Manual Atualizada',
                'cpf' => '777.666.555-44',
                'email' => 'manual.proposta@aabb.demo',
                'phone' => '(61) 99999-8888',
                'status' => 'analysis',
                'signature_status' => 'pending',
                'notes' => 'Atualizada pelo painel da equipe.',
            ])
            ->assertRedirect(route('team.dashboard', ['proposal' => $proposal->id]).'#secretaria');

        $this->assertSame('Familia Manual Atualizada', $proposal->fresh()->name);

        $this->actingAs($secretaria)
            ->patch(route('team.proposals.approve', $proposal))
            ->assertRedirect(route('team.dashboard', ['proposal' => $proposal->id]).'#secretaria');

        $proposal = $proposal->fresh();
        $member = Member::where('cpf', '777.666.555-44')->firstOrFail();

        $this->assertSame('approved', $proposal->status);
        $this->assertSame($member->id, $proposal->converted_member_id);
        $this->assertSame('active', $member->status);

        $this->actingAs($secretaria)
            ->patch(route('team.proposals.sign', $proposal))
            ->assertRedirect(route('team.dashboard', ['proposal' => $proposal->id]).'#secretaria');

        $this->assertSame('signed', $proposal->fresh()->signature_status);
    }

    public function test_manual_proposal_form_is_collapsed_until_create_or_edit_is_requested(): void
    {
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();
        $proposal = Proposal::firstOrFail();

        $this->actingAs($secretaria)
            ->get(route('team.dashboard').'#secretaria')
            ->assertOk()
            ->assertSee('Nova proposta manual')
            ->assertDontSee('name="_team_form" value="proposal"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['create' => 'proposal']).'#secretaria')
            ->assertOk()
            ->assertSee('name="_team_form" value="proposal"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['proposal' => $proposal->id]).'#secretaria')
            ->assertOk()
            ->assertSee('Editar proposta manual')
            ->assertSee('name="_team_form" value="proposal"', false);
    }

    public function test_secretariat_can_manage_announcements_and_benefits_from_team_dashboard(): void
    {
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();

        $this->actingAs($secretaria)
            ->post(route('team.announcements.store'), [
                'title' => 'Comunicado Teste Equipe',
                'category' => 'Comunicado',
                'summary' => 'Resumo do comunicado pelo painel da equipe.',
                'body' => 'Conteudo completo do comunicado.',
                'image_url' => 'https://example.com/comunicado.jpg',
                'published_at' => '2026-12-24',
                'is_featured' => '1',
            ])
            ->assertRedirect();

        $announcement = Announcement::where('title', 'Comunicado Teste Equipe')->firstOrFail();

        $this->assertSame('comunicado-teste-equipe', $announcement->slug);
        $this->assertTrue($announcement->is_featured);

        $this->actingAs($secretaria)
            ->put(route('team.announcements.update', $announcement), [
                'title' => 'Comunicado Teste Editado',
                'slug' => 'comunicado-teste-equipe',
                'category' => 'Evento',
                'summary' => 'Resumo editado pelo painel da equipe.',
                'body' => 'Conteudo editado.',
                'image_url' => 'https://example.com/comunicado-editado.jpg',
                'published_at' => '2026-12-24',
            ])
            ->assertRedirect(route('team.dashboard', ['announcement' => $announcement->id]).'#conteudo');

        $this->get('/')
            ->assertOk()
            ->assertSee('Comunicado Teste Editado');

        $this->actingAs($secretaria)
            ->post(route('team.benefits.store'), [
                'title' => 'Beneficio Teste Equipe',
                'category' => 'Acesso',
                'description' => 'Beneficio cadastrado direto na tela da equipe.',
                'icon' => 'ticket',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $benefit = Benefit::where('title', 'Beneficio Teste Equipe')->firstOrFail();

        $this->get('/')
            ->assertOk()
            ->assertSee('Beneficio Teste Equipe');

        $this->actingAs($secretaria)
            ->put(route('team.benefits.update', $benefit), [
                'title' => 'Beneficio Teste Editado',
                'category' => 'Acesso',
                'description' => 'Beneficio editado direto na tela da equipe.',
                'icon' => 'ticket',
                'is_active' => '1',
            ])
            ->assertRedirect(route('team.dashboard', ['benefit' => $benefit->id]).'#conteudo');

        $this->assertSame('Beneficio Teste Editado', $benefit->fresh()->title);

        $this->actingAs($secretaria)
            ->patch(route('team.benefits.toggle', $benefit))
            ->assertRedirect(route('team.dashboard').'#conteudo');

        $this->assertFalse($benefit->fresh()->is_active);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Beneficio Teste Editado');
    }

    public function test_content_forms_are_collapsed_until_create_or_edit_is_requested(): void
    {
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();
        $announcement = Announcement::firstOrFail();
        $benefit = Benefit::firstOrFail();

        $this->actingAs($secretaria)
            ->get(route('team.dashboard').'#conteudo')
            ->assertOk()
            ->assertSee('Novo comunicado')
            ->assertSee('Novo beneficio')
            ->assertDontSee('name="_team_form" value="announcement"', false)
            ->assertDontSee('name="_team_form" value="benefit"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['create' => 'announcement']).'#conteudo')
            ->assertOk()
            ->assertSee('name="_team_form" value="announcement"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['announcement' => $announcement->id]).'#conteudo')
            ->assertOk()
            ->assertSee('Editar comunicado')
            ->assertSee('name="_team_form" value="announcement"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['create' => 'benefit']).'#conteudo')
            ->assertOk()
            ->assertSee('name="_team_form" value="benefit"', false);

        $this->actingAs($secretaria)
            ->get(route('team.dashboard', ['benefit' => $benefit->id]).'#conteudo')
            ->assertOk()
            ->assertSee('Editar beneficio')
            ->assertSee('name="_team_form" value="benefit"', false);
    }

    public function test_non_secretariat_users_cannot_use_team_content_and_proposal_forms(): void
    {
        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $plan = Plan::firstOrFail();

        $this->actingAs($finance)
            ->post(route('team.proposals.store'), [
                'plan_id' => $plan->id,
                'name' => 'Bloqueado',
                'status' => 'analysis',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('team.announcements.store'), [
                'title' => 'Comunicado bloqueado',
                'category' => 'Comunicado',
                'summary' => 'Sem permissao.',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('team.benefits.store'), [
                'title' => 'Beneficio bloqueado',
                'category' => 'Clube',
                'description' => 'Sem permissao.',
            ])
            ->assertForbidden();
    }

    public function test_professional_stock_tracks_qr_costs_and_audit_log(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();
        $product = Product::firstOrFail();

        $this->assertNotNull($product->sku);
        $this->assertNotNull($product->qr_token);

        $this->get(route('team.stock.product.show', $product->qr_token))
            ->assertRedirect('/login');

        $this->actingAs($team)
            ->get(route('team.stock.product.show', $product->qr_token))
            ->assertStatus(200)
            ->assertSee($product->name)
            ->assertSee('QR Code do produto');

        $initial = $product->quantity;

        $this->actingAs($team)
            ->post(route('team.stock.move', $product), [
                'type' => 'entry',
                'quantity' => 5,
                'unit_cost' => 10.50,
                'reason' => 'Compra automatizada',
            ])
            ->assertRedirect();

        $movement = StockMovement::where('product_id', $product->id)->latest('id')->firstOrFail();

        $this->assertSame($initial + 5, $product->fresh()->quantity);
        $this->assertSame($initial, $movement->quantity_before);
        $this->assertSame($initial + 5, $movement->quantity_after);
        $this->assertSame($team->id, $movement->created_by_user_id);
        $this->assertSame('52.50', $movement->total_cost);

        $this->actingAs($team)
            ->post(route('team.stock.move', $product), [
                'type' => 'loss',
                'quantity' => 9999,
                'reason' => 'Teste bloqueio',
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_access_validation_returns_json_without_page_reload(): void
    {
        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();
        $member = Member::where('status', 'active')->firstOrFail();
        $guest = Guest::create([
            'member_id' => $member->id,
            'name' => 'Convidado JSON',
            'status' => 'invited',
            'invitation_code' => 'AABB-JSON-TEST',
        ]);
        $invitation = Invitation::create([
            'member_id' => $member->id,
            'guest_id' => $guest->id,
            'type' => 'club_access',
            'code' => 'AABB-JSON-TEST',
            'valid_for' => today(),
            'status' => 'available',
        ]);

        $this->actingAs($portaria)
            ->postJson(route('team.access.register'), [
                'code' => $invitation->code,
                'gate' => 'Portaria JSON',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('log.status', 'allowed');

        $this->assertSame('used', $invitation->fresh()->status);

        $this->actingAs($portaria)
            ->postJson(route('team.access.register'), [
                'code' => 'AABB-INEXISTENTE',
                'gate' => 'Portaria JSON',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_reservation_availability_endpoint_marks_reserved_days(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();
        $reservation = Reservation::with('space')->whereNotIn('status', ['cancelled', 'rejected'])->firstOrFail();
        $date = $reservation->reservation_date->format('Y-m-d');

        $response = $this->actingAs($team)
            ->getJson(route('reservations.availability', [
                'space_id' => $reservation->space->id,
                'month' => $reservation->reservation_date->format('Y-m'),
                'date' => $date,
            ]))
            ->assertOk()
            ->assertJsonPath('space.id', $reservation->space->id)
            ->assertJsonStructure(['days', 'slots', 'selectedReservations']);

        $day = collect($response->json('days'))->firstWhere('date', $date);

        $this->assertTrue($day['isBlocked']);
        $this->assertFalse($response->json('slots.0.available'));
        $this->assertNotEmpty($response->json('selectedReservations'));
    }

    public function test_team_metrics_default_to_current_month_and_marks_snapshot_cards(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-05 10:00:00'));

        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();

        $response = $this->actingAs($team)
            ->get('/equipe')
            ->assertStatus(200)
            ->assertSee('name="metrics_from"', false)
            ->assertSee('value="2026-05-01"', false)
            ->assertSee('name="metrics_to"', false)
            ->assertSee('value="2026-05-31"', false);

        $metricsHtml = $this->metricsCardsHtml($response->getContent());

        $this->assertStringContainsString('01/05/2026 a 31/05/2026', $metricsHtml);
        $this->assertSame(2, substr_count($metricsHtml, 'Situação atual'));
    }

    public function test_team_metrics_custom_period_filters_received_amount_by_received_at(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();
        $member = Member::firstOrFail();

        $inRangeInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-TEST-METRICS-IN',
            'type' => 'monthly',
            'description' => 'Mensalidade teste no periodo',
            'amount' => 123.45,
            'due_date' => '2026-02-10',
            'status' => 'paid',
            'paid_at' => '2026-02-12',
            'payment_method' => 'QR App AABB',
        ]);
        $outRangeInvoice = Invoice::create([
            'member_id' => $member->id,
            'number' => 'AABB-TEST-METRICS-OUT',
            'type' => 'monthly',
            'description' => 'Mensalidade teste fora do periodo',
            'amount' => 987.65,
            'due_date' => '2026-03-10',
            'status' => 'paid',
            'paid_at' => '2026-03-12',
            'payment_method' => 'QR App AABB',
        ]);

        Payment::create([
            'invoice_id' => $inRangeInvoice->id,
            'amount' => 123.45,
            'method' => 'QR App AABB',
            'status' => 'paid',
            'transaction_code' => 'METRICS-IN',
            'paid_at' => '2026-02-12 09:00:00',
            'received_at' => '2026-02-12 10:00:00',
        ]);
        Payment::create([
            'invoice_id' => $outRangeInvoice->id,
            'amount' => 987.65,
            'method' => 'QR App AABB',
            'status' => 'paid',
            'transaction_code' => 'METRICS-OUT',
            'paid_at' => '2026-03-12 09:00:00',
            'received_at' => '2026-03-12 10:00:00',
        ]);

        $response = $this->actingAs($team)
            ->get('/equipe?metrics_from=2026-02-01&metrics_to=2026-02-28')
            ->assertStatus(200)
            ->assertSee('value="2026-02-01"', false)
            ->assertSee('value="2026-02-28"', false);

        $metricsHtml = $this->metricsCardsHtml($response->getContent());

        $this->assertStringContainsString('01/02/2026 a 28/02/2026', $metricsHtml);
        $this->assertStringContainsString('R$ 123,45', $metricsHtml);
        $this->assertStringNotContainsString('R$ 987,65', $metricsHtml);
    }

    public function test_team_panel_uses_internal_tabs_and_legacy_admin_redirects_to_team(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();

        $this->get('/admin')
            ->assertRedirect('/equipe');

        $this->get('/gestao')
            ->assertRedirect('/equipe');

        $this->get('/gestao/cobrancas')
            ->assertRedirect('/equipe#financeiro');

        $this->actingAs($team)
            ->get('/equipe')
            ->assertStatus(200)
            ->assertSee('data-team-tabs', false)
            ->assertSee('Visão geral')
            ->assertSee('Secretaria')
            ->assertSee('Financeiro')
            ->assertSee('Reservas e Convites')
            ->assertSee('Portaria')
            ->assertSee('Estoque')
            ->assertSee('Conteúdo')
            ->assertDontSee('/gestao', false);
    }

    public function test_team_panel_respects_role_specific_tabs(): void
    {
        $finance = User::where('email', 'financeiro@aabb.demo')->firstOrFail();
        $secretaria = User::where('email', 'secretaria@aabb.demo')->firstOrFail();
        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();

        $this->actingAs($finance)
            ->get('/equipe')
            ->assertStatus(200)
            ->assertSee('Receita')
            ->assertDontSee('Base social');

        $this->actingAs($secretaria)
            ->get('/equipe')
            ->assertStatus(200)
            ->assertSee('Base social')
            ->assertDontSee('Receita');

        $this->actingAs($portaria)
            ->get('/equipe')
            ->assertStatus(200)
            ->assertSee('Acesso')
            ->assertDontSee('Receita');
    }

    public function test_member_card_has_qr_flip_and_internal_validation(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $member = $memberUser->member()->firstOrFail();

        $this->get(route('member-card.verify', $member->card_token))
            ->assertRedirect('/login');

        $this->actingAs($memberUser)
            ->get('/portal')
            ->assertStatus(200)
            ->assertSee('member-card-flip', false)
            ->assertSee('data:image', false)
            ->assertSee($member->membership_code);

        $this->actingAs($memberUser)
            ->get(route('member-card.verify', $member->card_token))
            ->assertForbidden();

        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();

        $this->actingAs($portaria)
            ->get(route('member-card.verify', $member->card_token))
            ->assertStatus(200)
            ->assertSee($member->name)
            ->assertSee('Acesso permitido');

        $this->actingAs($portaria)
            ->get(route('member-card.verify', 'token-invalido'))
            ->assertStatus(200)
            ->assertSee('Carteirinha inválida');
    }

    private function metricsCardsHtml(string $content): string
    {
        $start = strpos($content, '<section class="team-metrics team-metrics--inside">');
        $this->assertNotFalse($start);

        $end = strpos($content, '</section>', $start);
        $this->assertNotFalse($end);

        return substr($content, $start, $end - $start);
    }
}
