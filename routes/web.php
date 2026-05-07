<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberCardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::post('/adesao', [PublicController::class, 'proposal'])->name('proposal.store');

$teamTabFromLegacyPath = function (string $path): string {
    $segment = explode('/', $path)[0] ?? '';

    return [
        'associados' => 'secretaria',
        'dependentes' => 'secretaria',
        'propostas' => 'secretaria',
        'documentos' => 'secretaria',
        'importacoes' => 'secretaria',
        'cobrancas' => 'financeiro',
        'pagamentos' => 'financeiro',
        'itens-de-cobranca' => 'financeiro',
        'fluxo-de-caixa' => 'financeiro',
        'espacos' => 'reservas',
        'reservas' => 'reservas',
        'convites' => 'reservas',
        'convidados' => 'reservas',
        'validar-carteirinha' => 'portaria',
        'acessos' => 'portaria',
        'produtos' => 'estoque',
        'movimentacoes' => 'estoque',
        'comunicados' => 'conteudo',
        'beneficios' => 'conteudo',
    ][$segment] ?? 'visao-geral';
};

Route::redirect('/admin', '/equipe');
Route::redirect('/gestao', '/equipe');
Route::get('/admin/{any}', fn () => redirect('/equipe'))->where('any', '.*');
Route::get('/gestao/{any}', fn (string $any) => redirect('/equipe#'.$teamTabFromLegacyPath($any)))->where('any', '.*');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/portal', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/portal/dependentes', [PortalController::class, 'storeDependent'])->name('portal.dependents.store');
    Route::post('/portal/reservas', [PortalController::class, 'reserve'])->name('portal.reserve');
    Route::get('/portal/reservas/convidados/modelo', [PortalController::class, 'downloadReservationGuestTemplate'])->name('portal.reservation-guests.template');
    Route::post('/portal/reservas/{reservation}/convidados', [PortalController::class, 'addReservationGuest'])->name('portal.reservation-guests.store');
    Route::post('/portal/reservas/{reservation}/convidados/importar', [PortalController::class, 'importReservationGuests'])->name('portal.reservation-guests.import');
    Route::patch('/portal/reservas/{reservation}/convidados/{guest}', [PortalController::class, 'updateReservationGuest'])->name('portal.reservation-guests.update');
    Route::delete('/portal/reservas/{reservation}/convidados/{guest}', [PortalController::class, 'deleteReservationGuest'])->name('portal.reservation-guests.destroy');
    Route::post('/portal/convites', [PortalController::class, 'createInvitation'])->name('portal.invitations.store');
    Route::post('/portal/pagamentos/{invoice}', [PortalController::class, 'uploadPaymentProof'])->name('portal.pay');
    Route::post('/portal/pagamentos/{invoice}/demo', [PortalController::class, 'payDemo'])->name('portal.pay.demo');
    Route::get('/reservas/disponibilidade', [TeamController::class, 'reservationAvailability'])->name('reservations.availability');

    Route::get('/equipe', [TeamController::class, 'dashboard'])->name('team.dashboard');
    Route::get('/equipe/estoque/produtos/{token}', [TeamController::class, 'showStockProduct'])->name('team.stock.product.show');
    Route::get('/carteirinha/validar/{token}', [MemberCardController::class, 'show'])->name('member-card.verify');
    Route::post('/equipe/reservas/mapa', [TeamController::class, 'uploadReservationMap'])->name('team.reservation-map.store');
    Route::post('/equipe/espacos/tipos', [TeamController::class, 'storeReservationSpaceType'])->name('team.space-types.store');
    Route::put('/equipe/espacos/tipos/{spaceType}', [TeamController::class, 'updateReservationSpaceType'])->name('team.space-types.update');
    Route::patch('/equipe/espacos/tipos/{spaceType}/status', [TeamController::class, 'toggleReservationSpaceType'])->name('team.space-types.toggle');
    Route::post('/equipe/espacos', [TeamController::class, 'storeReservationSpace'])->name('team.spaces.store');
    Route::put('/equipe/espacos/{space}', [TeamController::class, 'updateReservationSpace'])->name('team.spaces.update');
    Route::patch('/equipe/espacos/{space}/status', [TeamController::class, 'toggleReservationSpace'])->name('team.spaces.toggle');
    Route::post('/equipe/faturamento/mensalidades', [TeamController::class, 'generateMonthlyInvoices'])->name('team.billing.monthly');
    Route::post('/equipe/faturas/{invoice}/baixa', [TeamController::class, 'markInvoicePaid'])->name('team.invoices.pay');
    Route::post('/equipe/importar-associados', [TeamController::class, 'importMembers'])->name('team.members.import');
    Route::post('/equipe/propostas', [TeamController::class, 'storeProposal'])->name('team.proposals.store');
    Route::put('/equipe/propostas/{proposal}', [TeamController::class, 'updateProposal'])->name('team.proposals.update');
    Route::patch('/equipe/propostas/{proposal}/aprovar', [TeamController::class, 'approveProposal'])->name('team.proposals.approve');
    Route::patch('/equipe/propostas/{proposal}/assinar', [TeamController::class, 'signProposal'])->name('team.proposals.sign');
    Route::post('/equipe/comunicados', [TeamController::class, 'storeAnnouncement'])->name('team.announcements.store');
    Route::put('/equipe/comunicados/{announcement}', [TeamController::class, 'updateAnnouncement'])->name('team.announcements.update');
    Route::post('/equipe/beneficios', [TeamController::class, 'storeBenefit'])->name('team.benefits.store');
    Route::put('/equipe/beneficios/{benefit}', [TeamController::class, 'updateBenefit'])->name('team.benefits.update');
    Route::patch('/equipe/beneficios/{benefit}/status', [TeamController::class, 'toggleBenefit'])->name('team.benefits.toggle');
    Route::post('/equipe/estoque/{product}/movimento', [TeamController::class, 'moveStock'])->name('team.stock.move');
    Route::post('/equipe/acesso/registrar', [TeamController::class, 'registerAccess'])->name('team.access.register');
});
