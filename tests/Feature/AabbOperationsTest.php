<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ReservableSpace;
use App\Models\Reservation;
use App\Models\StockMovement;
use App\Models\User;
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
        $invitation = Invitation::where('status', 'available')->firstOrFail();

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
}
