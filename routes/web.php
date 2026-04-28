<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::post('/adesao', [PublicController::class, 'proposal'])->name('proposal.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/portal', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/portal/reservas', [PortalController::class, 'reserve'])->name('portal.reserve');
    Route::post('/portal/reservas/{reservation}/convidados', [PortalController::class, 'addGuest'])->name('portal.guests.store');
    Route::post('/portal/pagamentos/{invoice}', [PortalController::class, 'pay'])->name('portal.pay');

    Route::get('/equipe', [TeamController::class, 'dashboard'])->name('team.dashboard');
});
