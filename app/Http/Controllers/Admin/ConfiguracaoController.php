<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class ConfiguracaoController extends Controller
{
    /** Visão somente-leitura do ambiente (sem expor segredos). */
    public function __invoke()
    {
        $this->authorize('viewAny', User::class);

        return view('admin.configuracoes', ['itens' => [
            'Ambiente' => app()->environment(),
            'HTTPS forçado' => app()->environment('production') ? 'Sim' : 'Não (apenas produção)',
            'Cache' => config('cache.default'),
            'Sessão' => config('session.driver'),
            'Fila' => config('queue.default'),
            'Broadcast' => config('broadcasting.default'),
            'Banco' => config('database.default'),
            'Fuso horário' => config('app.timezone'),
            'Login Google configurado' => config('services.google.client_id') ? 'Sim' : 'Não',
        ]]);
    }
}
