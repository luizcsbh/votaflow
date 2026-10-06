<?php

namespace App\Console\Commands;

use App\Services\VotacaoService;
use Illuminate\Console\Command;

class SincronizarStatusVotacoes extends Command
{
    protected $signature = 'votaflow:sincronizar-status';

    protected $description = 'Abre votações agendadas e encerra as que passaram do fim.';

    public function handle(VotacaoService $service): int
    {
        [$abertas, $encerradas] = $service->sincronizarPorData();
        $this->info("Abertas: {$abertas} | Encerradas: {$encerradas}");

        return self::SUCCESS;
    }
}
