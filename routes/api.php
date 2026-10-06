<?php

use App\Http\Controllers\Api\V1\VotacaoApiController;
use Illuminate\Support\Facades\Route;

/*
| API v1. Primeira versão usa a sessão do mesmo domínio (cookie + X-XSRF-TOKEN) para
| autenticar o participante via Google. Um cliente mobile/SPA externo exigirá tokens
| (ex.: Laravel Sanctum) — ver docs/ARQUITETURA.md.
*/
Route::prefix('v1')->middleware(['web', 'throttle:api'])->name('api.v1.')->group(function () {
    Route::get('votacoes', [VotacaoApiController::class, 'index'])->middleware('auth')->name('votacoes.index');
    Route::get('votacoes/{votacao}', [VotacaoApiController::class, 'show'])->name('votacoes.show');

    Route::middleware('auth')->group(function () {
        Route::post('votacoes/{votacao}/participar', [VotacaoApiController::class, 'participar'])->name('votacoes.participar');
        Route::post('votacoes/{votacao}/responder', [VotacaoApiController::class, 'responder'])->name('votacoes.responder');
        Route::post('votacoes/{votacao}/finalizar', [VotacaoApiController::class, 'finalizar'])->middleware('throttle:votar')->name('votacoes.finalizar');
        Route::get('votacoes/{votacao}/resultados', [VotacaoApiController::class, 'resultados'])->name('votacoes.resultados');
    });
});
