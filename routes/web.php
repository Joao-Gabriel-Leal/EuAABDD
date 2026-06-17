<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberCardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TeamController;
use App\Support\Modules;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');

$hiddenModule = fn () => abort(404);
$moduleAction = fn (string $module, mixed $action): mixed => Modules::enabled($module) ? $action : $hiddenModule;

Route::post('/adesao', $moduleAction('public_signup', [PublicController::class, 'proposal']))->name('proposal.store');

$teamTabFromLegacyPath = function (string $path): ?string {
    $segment = explode('/', $path)[0] ?? '';

    return [
        'importacoes' => 'importacao',
        'cobrancas' => 'pagamentos',
        'pagamentos' => 'pagamentos',
        'espacos' => 'reservas',
        'reservas' => 'reservas',
        'convidados' => 'reservas',
    ][$segment] ?? null;
};

Route::redirect('/admin', '/equipe');
Route::redirect('/gestao', '/equipe');
Route::get('/admin/{any}', fn () => redirect('/equipe'))->where('any', '.*');
Route::get('/gestao/{any}', function (string $any) use ($teamTabFromLegacyPath) {
    $tab = $teamTabFromLegacyPath($any);

    abort_unless($tab, 404);

    return redirect('/equipe#'.$tab);
})->where('any', '.*');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () use ($moduleAction) {
    Route::get('/portal', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/portal/dependentes', $moduleAction('portal_dependents', [PortalController::class, 'storeDependent']))->name('portal.dependents.store');
    Route::post('/portal/reservas', [PortalController::class, 'reserve'])->name('portal.reserve');
    Route::get('/portal/reservas/convidados/modelo', [PortalController::class, 'downloadReservationGuestTemplate'])->name('portal.reservation-guests.template');
    Route::post('/portal/reservas/{reservation}/convidados', [PortalController::class, 'addReservationGuest'])->name('portal.reservation-guests.store');
    Route::post('/portal/reservas/{reservation}/convidados/importar', [PortalController::class, 'importReservationGuests'])->name('portal.reservation-guests.import');
    Route::patch('/portal/reservas/{reservation}/convidados/{guest}', [PortalController::class, 'updateReservationGuest'])->name('portal.reservation-guests.update');
    Route::delete('/portal/reservas/{reservation}/convidados/{guest}', [PortalController::class, 'deleteReservationGuest'])->name('portal.reservation-guests.destroy');
    Route::post('/portal/convites', $moduleAction('portal_club_invitations', [PortalController::class, 'createInvitation']))->name('portal.invitations.store');
    Route::post('/portal/pagamentos/{invoice}', [PortalController::class, 'uploadPaymentProof'])->name('portal.pay');
    Route::post('/portal/pagamentos/{invoice}/demo', [PortalController::class, 'payDemo'])->name('portal.pay.demo');
    Route::get('/reservas/disponibilidade', [TeamController::class, 'reservationAvailability'])->name('reservations.availability');

    Route::get('/equipe', [TeamController::class, 'dashboard'])->name('team.dashboard');
    Route::get('/equipe/estoque/produtos/{token}', $moduleAction('team_stock', [TeamController::class, 'showStockProduct']))->name('team.stock.product.show');
    Route::get('/carteirinha/validar/{token}', $moduleAction('member_card', [MemberCardController::class, 'show']))->name('member-card.verify');
    Route::post('/equipe/reservas/mapa', [TeamController::class, 'uploadReservationMap'])->name('team.reservation-map.store');
    Route::post('/equipe/espacos/tipos', [TeamController::class, 'storeReservationSpaceType'])->name('team.space-types.store');
    Route::put('/equipe/espacos/tipos/{spaceType}', [TeamController::class, 'updateReservationSpaceType'])->name('team.space-types.update');
    Route::patch('/equipe/espacos/tipos/{spaceType}/status', [TeamController::class, 'toggleReservationSpaceType'])->name('team.space-types.toggle');
    Route::post('/equipe/espacos', [TeamController::class, 'storeReservationSpace'])->name('team.spaces.store');
    Route::put('/equipe/espacos/{space}', [TeamController::class, 'updateReservationSpace'])->name('team.spaces.update');
    Route::patch('/equipe/espacos/{space}/status', [TeamController::class, 'toggleReservationSpace'])->name('team.spaces.toggle');
    Route::post('/equipe/faturamento/mensalidades', $moduleAction('team_finance_full', [TeamController::class, 'generateMonthlyInvoices']))->name('team.billing.monthly');
    Route::post('/equipe/faturas/{invoice}/baixa', [TeamController::class, 'markInvoicePaid'])->name('team.invoices.pay');
    Route::post('/equipe/importar-associados', [TeamController::class, 'importMembers'])->name('team.members.import');
    Route::post('/equipe/propostas', $moduleAction('team_secretariat_full', [TeamController::class, 'storeProposal']))->name('team.proposals.store');
    Route::put('/equipe/propostas/{proposal}', $moduleAction('team_secretariat_full', [TeamController::class, 'updateProposal']))->name('team.proposals.update');
    Route::patch('/equipe/propostas/{proposal}/aprovar', $moduleAction('team_secretariat_full', [TeamController::class, 'approveProposal']))->name('team.proposals.approve');
    Route::patch('/equipe/propostas/{proposal}/assinar', $moduleAction('team_secretariat_full', [TeamController::class, 'signProposal']))->name('team.proposals.sign');
    Route::post('/equipe/comunicados', $moduleAction('team_content', [TeamController::class, 'storeAnnouncement']))->name('team.announcements.store');
    Route::put('/equipe/comunicados/{announcement}', $moduleAction('team_content', [TeamController::class, 'updateAnnouncement']))->name('team.announcements.update');
    Route::post('/equipe/beneficios', $moduleAction('team_content', [TeamController::class, 'storeBenefit']))->name('team.benefits.store');
    Route::put('/equipe/beneficios/{benefit}', $moduleAction('team_content', [TeamController::class, 'updateBenefit']))->name('team.benefits.update');
    Route::patch('/equipe/beneficios/{benefit}/status', $moduleAction('team_content', [TeamController::class, 'toggleBenefit']))->name('team.benefits.toggle');
    Route::post('/equipe/estoque/{product}/movimento', $moduleAction('team_stock', [TeamController::class, 'moveStock']))->name('team.stock.move');
    Route::post('/equipe/acesso/registrar', $moduleAction('team_access', [TeamController::class, 'registerAccess']))->name('team.access.register');
});
