<?php

namespace App\Services;

use App\Models\Votacao;
use Illuminate\Support\Facades\Cache;

/**
 * Estrutura (perguntas + alternativas) em formato de array, em cache.
 * A chave inclui updated_at: qualquer edição estrutural invalida o cache sozinha.
 * Evita N+1 e consultas repetidas quando milhares de pessoas abrem a mesma votação.
 */
class EstruturaVotacao
{
    public function obter(Votacao $votacao): array
    {
        $chave = sprintf('votacao:%d:estrutura:%d', $votacao->id, $votacao->updated_at ? $votacao->updated_at->timestamp : 0);

        return Cache::remember($chave, now()->addHour(), function () use ($votacao) {
            return $votacao->perguntas()->with('alternativas')->get()->map(fn ($p) => [
                'id' => $p->id,
                'tipo' => $p->tipo->value,
                'titulo' => $p->titulo,
                'descricao' => $p->descricao,
                'obrigatoria' => $p->obrigatoria,
                'multipla' => $p->tipo->aceitaMultiplas(),
                'alternativas' => $p->alternativas->map(fn ($a) => ['id' => $a->id, 'texto' => $a->texto])->all(),
            ])->all();
        });
    }
}
