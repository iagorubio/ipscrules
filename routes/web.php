<?php

use App\Http\Controllers\Admin\AskLogController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\ExamResultController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\RulebookController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\ExamController;
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

    Route::post('/consultas/{log}/discrepancia', [DisputeController::class, 'store'])->middleware('throttle:10,1')->name('disputes.store');

    Route::get('/examenes', [ExamController::class, 'index'])->name('exams');
    Route::post('/examenes', [ExamController::class, 'start'])->middleware('throttle:10,1')->name('exams.start');
    Route::get('/examenes/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('/examenes/{exam}', [ExamController::class, 'submit'])->middleware('throttle:20,1')->name('exams.submit');
    Route::post('/examenes/{exam}/corregir', [ExamController::class, 'regrade'])->middleware('throttle:6,1')->name('exams.regrade');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index'])->name('users');
        Route::post('/usuarios/invitar', [UserController::class, 'invite'])->middleware('throttle:20,1')->name('users.invite');
        Route::post('/invitaciones/{invitation}/reenviar', [UserController::class, 'resend'])->middleware('throttle:20,1')->name('invitations.resend');
        Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/invitaciones/{invitation}', [UserController::class, 'revoke'])->name('invitations.revoke');
        Route::get('/reglamento', [RulebookController::class, 'index'])->name('rulebook');
        Route::get('/consultas', [AskLogController::class, 'index'])->name('asklogs');
        Route::get('/discrepancias', [AdminDisputeController::class, 'index'])->name('disputes');
        Route::get('/discrepancias/exportar', [AdminDisputeController::class, 'export'])->name('disputes.export');
        Route::patch('/discrepancias/{dispute}', [AdminDisputeController::class, 'update'])->name('disputes.update');

        Route::get('/preguntas', [QuestionController::class, 'index'])->name('questions');
        Route::post('/preguntas/generar', [QuestionController::class, 'generate'])->middleware('throttle:6,1')->name('questions.generate');
        Route::get('/preguntas/{question}', [QuestionController::class, 'edit'])->name('questions.edit');
        Route::put('/preguntas/{question}', [QuestionController::class, 'update'])->name('questions.update');
        Route::patch('/preguntas/{question}/estado', [QuestionController::class, 'setStatus'])->name('questions.status');
        Route::delete('/preguntas/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('/examenes', [ExamResultController::class, 'index'])->name('exams');
        Route::get('/examenes/{exam}', [ExamResultController::class, 'show'])->name('exams.show');
        Route::patch('/examenes/{exam}/respuestas/{answer}', [ExamResultController::class, 'override'])->name('exams.override');
    });
});
