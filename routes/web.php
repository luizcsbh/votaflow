<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\Participante\VotacaoPublicaController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/up', fn () => response('OK', 200, ['Content-Type' => 'text/plain']))->name('up'); // liveness mínimo (sem banco)

Route::get('/', fn () => view('home'))->name('home');

// ── Autenticação (Google OAuth 2.0) ──────────────────────────────────────────
Route::middleware('throttle:login')->group(function () {
    Route::get('/login', [GoogleController::class, 'login'])->name('login');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});
Route::post('/logout', [GoogleController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/perfil', PerfilController::class)->middleware('auth')->name('perfil');

// ── Login de desenvolvimento: o controller responde 404 a menos que APP_ENV=local + VOTAFLOW_DEV_LOGIN=true ─
Route::get('/dev/login', [Auth\DevLoginController::class, 'index'])->name('dev.login');
Route::post('/dev/login', [Auth\DevLoginController::class, 'entrar'])->name('dev.login.entrar');

// ── Área do participante (URL pública do QR Code: /v/ABC123) ─────────────────
Route::prefix('v/{votacao}')->name('votacao.')->group(function () {
    Route::get('/', [VotacaoPublicaController::class, 'mostrar'])->name('mostrar');
    Route::middleware('auth')->group(function () {
        Route::get('/votar', [VotacaoPublicaController::class, 'votar'])->name('votar');
        Route::post('/votar', [VotacaoPublicaController::class, 'enviar'])->middleware('throttle:votar')->name('enviar');
        Route::get('/comprovante', [VotacaoPublicaController::class, 'comprovante'])->name('comprovante');
    });
});

// ── Área administrativa ──────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'painel', 'throttle:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('votacoes', [Admin\VotacaoController::class, 'index'])->name('votacoes.index');
    Route::get('votacoes/criar', [Admin\VotacaoController::class, 'create'])->name('votacoes.create');
    Route::post('votacoes', [Admin\VotacaoController::class, 'store'])->name('votacoes.store');
    Route::get('votacoes/{votacao}', [Admin\VotacaoController::class, 'show'])->name('votacoes.show');
    Route::get('votacoes/{votacao}/editar', [Admin\VotacaoController::class, 'edit'])->name('votacoes.edit');
    Route::put('votacoes/{votacao}', [Admin\VotacaoController::class, 'update'])->name('votacoes.update');
    Route::post('votacoes/{votacao}/status', [Admin\VotacaoController::class, 'transicao'])->name('votacoes.transicao');
    Route::get('votacoes/{votacao}/qrcode', [Admin\VotacaoController::class, 'qrcode'])->name('votacoes.qrcode');
    Route::get('votacoes/{votacao}/qrcode/download', [Admin\VotacaoController::class, 'qrcodeDownload'])->name('votacoes.qrcode.download');
    Route::get('votacoes/{votacao}/imprimir', [Admin\VotacaoController::class, 'imprimir'])->name('votacoes.imprimir');

    Route::get('resultados', [Admin\ResultadoController::class, 'index'])->name('resultados.index');
    Route::get('resultados/{votacao}', [Admin\ResultadoController::class, 'show'])->name('resultados.show');
    Route::get('resultados/{votacao}/dados', [Admin\ResultadoController::class, 'dados'])->name('resultados.dados');
    Route::get('resultados/{votacao}/exportar', [Admin\ResultadoController::class, 'exportar'])->name('resultados.exportar');

    Route::get('usuarios', [Admin\UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('usuarios', [Admin\UsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('usuarios/{usuario}', [Admin\UsuarioController::class, 'update'])->name('usuarios.update');

    Route::get('auditoria', [Admin\AuditoriaController::class, 'index'])->name('auditoria');
    Route::get('configuracoes', Admin\ConfiguracaoController::class)->name('configuracoes');
});
