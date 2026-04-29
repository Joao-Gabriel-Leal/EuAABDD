<?php

namespace Tests\Feature;

use App\Models\ReservableSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoginAndReservationSpaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_redirects_member_and_team_users_to_their_dashboards(): void
    {
        $memberUser = User::where('email', 'associado@aabb.demo')->firstOrFail();
        $teamUser = User::where('email', 'equipe@aabb.demo')->firstOrFail();

        $this->post(route('login.attempt'), [
            'email' => $memberUser->email,
            'password' => 'aabb2026',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($memberUser);

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->post(route('login.attempt'), [
            'email' => $teamUser->email,
            'password' => 'aabb2026',
        ])->assertRedirect(route('team.dashboard'));

        $this->assertAuthenticatedAs($teamUser);
    }

    public function test_login_csrf_expiration_redirects_back_to_login_with_friendly_message(): void
    {
        Route::middleware('web')->post('/_testing/login-token-mismatch', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        })->name('login.attempt');

        $this->from(route('login'))
            ->post('/_testing/login-token-mismatch', [
                'email' => 'associado@aabb.demo',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sua sessao expirou. Tente novamente.');
    }

    public function test_authenticated_html_post_csrf_expiration_redirects_back_with_friendly_message(): void
    {
        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();

        Route::middleware('web')->post('/_testing/team-token-mismatch', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        })->name('testing.team-token-mismatch');

        $this->actingAs($portaria)
            ->from(route('team.dashboard'))
            ->post('/_testing/team-token-mismatch')
            ->assertRedirect(route('team.dashboard'))
            ->assertSessionHas('error', 'Sua sessao expirou. Tente novamente.');
    }

    public function test_authenticated_json_post_keeps_419_status_on_csrf_expiration(): void
    {
        $portaria = User::where('email', 'portaria@aabb.demo')->firstOrFail();

        Route::middleware('web')->post('/_testing/team-token-mismatch-json', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        })->name('testing.team-token-mismatch-json');

        $this->actingAs($portaria)
            ->postJson('/_testing/team-token-mismatch-json')
            ->assertStatus(419);
    }

    public function test_forwarded_https_headers_are_trusted_for_proxied_requests(): void
    {
        Route::middleware('web')->get('/_testing/proxy-check', function (HttpRequest $request) {
            return response()->json([
                'secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
            ]);
        });

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
            'HTTP_X_FORWARDED_HOST' => 'demo.aabb.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->getJson('/_testing/proxy-check')
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'scheme' => 'https',
                'host' => 'demo.aabb.test',
            ]);
    }

    public function test_internal_team_can_create_space_from_team_dashboard_with_external_image(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();

        $response = $this->actingAs($team)
            ->post(route('team.spaces.store'), [
                'name' => 'Quadra de Areia Norte',
                'type' => 'quadra',
                'location' => 'Ala esportiva norte',
                'capacity' => 16,
                'base_price' => 210.50,
                'image_url' => 'https://example.com/quadra-norte.jpg',
                'starts_at' => '08:00',
                'ends_at' => '18:00',
                'included_guests' => 6,
                'is_active' => '1',
            ]);

        $space = ReservableSpace::where('name', 'Quadra de Areia Norte')->firstOrFail();

        $response->assertRedirect(route('team.dashboard', ['space' => $space->id]).'#reservas');
        $this->assertSame('quadra', $space->type);
        $this->assertSame('Ala esportiva norte', $space->location);
        $this->assertSame(16, $space->capacity);
        $this->assertSame('https://example.com/quadra-norte.jpg', $space->image_url);
        $this->assertTrue($space->is_active);
        $this->assertSame('08:00', $space->startsAt());
        $this->assertSame('18:00', $space->endsAt());
        $this->assertSame(6, $space->includedGuests());
    }

    public function test_space_updates_reflect_in_calendar_and_deactivation_preserves_history(): void
    {
        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();
        $member = User::where('email', 'associado@aabb.demo')->firstOrFail();

        $space = ReservableSpace::create([
            'name' => 'Churrasqueira Teste Historico',
            'type' => 'churrasqueira',
            'location' => 'Area de eventos',
            'capacity' => 24,
            'base_price' => 420,
            'image_url' => 'https://example.com/churrasqueira-historico.jpg',
            'rules' => [
                'starts_at' => '11:00',
                'ends_at' => '17:00',
                'included_guests' => 4,
            ],
            'is_active' => true,
        ]);

        $reservationDate = now()->addDays(45)->format('Y-m-d');

        $this->actingAs($member)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $reservationDate,
            ])
            ->assertRedirect();

        $this->actingAs($team)
            ->put(route('team.spaces.update', $space), [
                'name' => 'Churrasqueira Teste Historico',
                'type' => 'evento',
                'location' => 'Bosque principal',
                'capacity' => 40,
                'base_price' => 615.75,
                'image_url' => 'https://example.com/churrasqueira-historico-atualizada.jpg',
                'starts_at' => '09:00',
                'ends_at' => '21:30',
                'included_guests' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('team.dashboard', ['space' => $space->id]).'#reservas');

        $space = $space->fresh();

        $this->assertSame('evento', $space->type);
        $this->assertSame('Bosque principal', $space->location);
        $this->assertSame(40, $space->capacity);
        $this->assertSame('09:00', $space->startsAt());
        $this->assertSame('21:30', $space->endsAt());
        $this->assertSame(10, $space->includedGuests());

        $calendarDate = now()->addDays(47);

        $this->actingAs($team)
            ->getJson(route('reservations.availability', [
                'space_id' => $space->id,
                'month' => $calendarDate->format('Y-m'),
                'date' => $calendarDate->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertJsonPath('space.capacity', 40)
            ->assertJsonPath('slots.0.label', '09:00 as 21:30');

        $this->actingAs($team)
            ->patch(route('team.spaces.toggle', $space))
            ->assertRedirect(route('team.dashboard').'#reservas');

        $this->assertFalse($space->fresh()->is_active);

        $this->actingAs($team)
            ->getJson(route('reservations.availability', [
                'space_id' => $space->id,
                'month' => $calendarDate->format('Y-m'),
                'date' => $calendarDate->format('Y-m-d'),
            ]))
            ->assertNotFound();

        $this->actingAs($member)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Churrasqueira Teste Historico')
            ->assertDontSee('option value="'.$space->id.'"', false);

        $this->actingAs($member)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => now()->addDays(60)->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('reservable_space_id');
    }

    public function test_uploaded_space_image_generates_public_path_for_home_and_portal(): void
    {
        Storage::fake('public');

        $team = User::where('email', 'equipe@aabb.demo')->firstOrFail();
        $member = User::where('email', 'associado@aabb.demo')->firstOrFail();

        $this->actingAs($team)
            ->post(route('team.spaces.store'), [
                'name' => 'Quadra com Upload',
                'type' => 'quadra',
                'location' => 'Area coberta',
                'capacity' => 12,
                'base_price' => 95,
                'image_file' => UploadedFile::fake()->image('quadra-upload.png', 1200, 800),
                'starts_at' => '07:00',
                'ends_at' => '22:00',
                'included_guests' => 2,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $space = ReservableSpace::where('name', 'Quadra com Upload')->firstOrFail();
        $storedPath = ltrim(str_replace('/storage/', '', $space->image_url), '/');

        $this->assertStringStartsWith('/storage/reservable-spaces/', $space->image_url);
        Storage::disk('public')->assertExists($storedPath);

        $reservationDate = now()->addDays(70)->format('Y-m-d');

        $this->actingAs($member)
            ->post(route('portal.reserve'), [
                'reservable_space_id' => $space->id,
                'reservation_date' => $reservationDate,
            ])
            ->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertSee($space->image_url, false);

        $this->actingAs($member)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee($space->image_url, false);
    }
}
