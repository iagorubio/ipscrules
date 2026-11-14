<?php

use App\Http\Controllers\Admin\RulebookController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:8,1');
});

Route::get('/invitacion/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('/invitacion/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [AskController::class, 'index'])->name('ask');
    Route::post('/preguntar', [AskController::class, 'store'])->middleware('throttle:12,1')->name('ask.store');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('users');
        Route::post('/usuarios/invitar', [UserController::class, 'invite'])->name('users.invite');
        Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/invitaciones/{invitation}', [UserController::class, 'revoke'])->name('invitations.revoke');
        Route::get('/reglamento', [RulebookController::class, 'index'])->name('rulebook');
    });
});
