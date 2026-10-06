<?php

namespace App\Services;

use App\Enums\StatusVotacao;
use App\Models\Participacao;
use App\Models\Votacao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResultadoService
{
    /** @var EstruturaVotacao */
    private $estrutura;

    public function __construct(EstruturaVotacao $estrutura)
    {
        $this->estrutura = $estrutura;
    }

    public function totalParticipantes(int $votacaoId, bool $fresco = false): int
    {
        $chave = "votacao:{$votacaoId}:participantes";
        if ($fresco) {
            Cache::forget($chave);
        }

        return (int) Cache::remember($chave, now()->addSeconds(5), fn () => Participacao::where('votacao_id', $votacaoId)->count());
    }

    /**
     * Resultado agregado (nunca individual). Uma única consulta GROUP BY, em cache:
     * votações abertas por poucos segundos; encerradas por mais tempo.
     */
    public function paraVotacao(Votacao $votacao): array
    {
        $ttl = $votacao->status === StatusVotacao::Aberta() ? now()->addSeconds(5) : now()->addMinutes(30);
        $chave = sprintf('votacao:%d:resultado:%s:%d', $votacao->id, $votacao->status->value, $votacao->updated_at ? $votacao->updated_at->timestamp : 0);

        return Cache::remember($chave, $ttl, fn () => $this->calcular($votacao));
    }

    public function calcular(Votacao $votacao): array
    {
        $estrutura = $this->estrutura->obter($votacao);
        $perguntaIds = array_column($estrutura, 'id');

        $contagens = [];
        if ($perguntaIds) {
            $linhas = DB::table('respostas')
                ->select('pergunta_id', 'alternativa_id', DB::raw('COUNT(*) as total'))
                ->whereIn('pergunta_id', $perguntaIds)
                ->groupBy('pergunta_id', 'alternativa_id')
                ->get();
            foreach ($linhas as $l) {
                $contagens[$l->pergunta_id][$l->alternativa_id] = (int) $l->total;
            }
        }

        $participantes = Participacao::where('votacao_id', $votacao->id)->count();
        $totalVotos = 0;
        $perguntas = [];

        foreach ($estrutura as $p) {
            $totalPergunta = array_sum($contagens[$p['id']] ?? []);
            $totalVotos += $totalPergunta;
            $alternativas = [];
            foreach ($p['alternativas'] as $a) {
                $n = $contagens[$p['id']][$a['id']] ?? 0;
                $alternativas[] = [
                    'id' => $a['id'],
                    'texto' => $a['texto'],
                    'total' => $n,
                    'percentual' => $totalPergunta > 0 ? round($n / $totalPergunta * 100, 1) : 0.0,
                ];
            }
            $perguntas[] = [
                'id' => $p['id'],
                'titulo' => $p['titulo'],
                'tipo' => $p['tipo'],
                'total_respostas' => $totalPergunta,
                'alternativas' => $alternativas,
            ];
        }

        return [
            'participantes' => $participantes,
            'total_votos' => $totalVotos,
            'limite' => $votacao->limite_participantes,
            'taxa_participacao' => $votacao->limite_participantes
                ? round($participantes / $votacao->limite_participantes * 100, 1) : null,
            'perguntas' => $perguntas,
            'gerado_em' => now()->toIso8601String(),
        ];
    }
}
