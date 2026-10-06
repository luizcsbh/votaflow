<?php

namespace App\Services;

use App\Events\ParticipacaoRegistrada;
use App\Exceptions\VotacaoException;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Votacao;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VotoService
{
    /** @var EstruturaVotacao */
    private $estrutura;

    /** @var AuditoriaService */
    private $auditoria;

    public function __construct(EstruturaVotacao $estrutura, AuditoriaService $auditoria)
    {
        $this->estrutura = $estrutura;
        $this->auditoria = $auditoria;
    }

    /**
     * Valida as respostas contra a estrutura da votação e devolve a forma normalizada
     * [pergunta_id => [alternativa_id, ...]]. Nada do que vem do frontend é confiado.
     *
     * @param  array<int|string, mixed>  $entrada
     * @return array<int, list<int>>
     */
    public function validarRespostas(Votacao $votacao, array $entrada): array
    {
        $normalizadas = [];
        $erros = [];

        foreach ($this->estrutura->obter($votacao) as $pergunta) {
            $pid = $pergunta['id'];
            $bruto = $entrada[$pid] ?? $entrada[(string) $pid] ?? null;
            $lista = is_array($bruto) ? array_values($bruto) : (($bruto === null || $bruto === '') ? [] : [$bruto]);

            if ($lista === []) {
                if ($pergunta['obrigatoria']) {
                    $erros[$pid] = 'Esta pergunta é obrigatória.';
                }

                continue;
            }

            if (! $pergunta['multipla'] && count($lista) > 1) {
                $erros[$pid] = 'Selecione apenas uma opção.';

                continue;
            }

            $validas = array_column($pergunta['alternativas'], 'id');
            $ids = [];
            foreach ($lista as $item) {
                if (! is_scalar($item) || ! ctype_digit((string) $item) || ! in_array((int) $item, $validas, true)) {
                    $erros[$pid] = 'Opção inválida.';

                    continue 2;
                }
                $ids[(int) $item] = (int) $item; // deduplica
            }

            $normalizadas[$pid] = array_values($ids);
        }

        // Rejeita perguntas que não pertencem a esta votação (manipulação de IDs).
        $conhecidas = array_column($this->estrutura->obter($votacao), 'id');
        foreach (array_keys($entrada) as $pid) {
            if (! in_array((int) $pid, $conhecidas, true)) {
                $erros[$pid] = 'Pergunta inválida.';
            }
        }

        if ($erros) {
            throw VotacaoException::respostasInvalidas($erros);
        }

        return $normalizadas;
    }

    /**
     * Registra a participação e todas as respostas em UMA transação (tudo ou nada).
     * A duplicidade é impedida pela constraint UNIQUE(votacao_id, usuario_id) — não por um "if".
     *
     * @param  array<int|string, mixed>  $entrada
     */
    public function registrar(Votacao $votacao, User $usuario, array $entrada, ?\DateTimeInterface $iniciadoEm = null): Participacao
    {
        $respostas = $this->validarRespostas($votacao, $entrada);

        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            try {
                $participacao = DB::transaction(function () use ($votacao, $usuario, $respostas, $iniciadoEm) {
                    // Releitura barata (sem lock) do estado atual: a votação pode ter fechado enquanto a pessoa respondia.
                    $atual = Votacao::query()->find($votacao->id);
                    if (! $atual || ! $atual->aceitaVotos()) {
                        throw VotacaoException::fechada();
                    }

                    $participacao = Participacao::create([
                        'votacao_id' => $atual->id,
                        'usuario_id' => $usuario->id,
                        'protocolo' => Participacao::gerarProtocolo(),
                        'iniciado_em' => $iniciadoEm ?? now(),
                        'finalizado_em' => now(),
                    ]);

                    $vinculo = $atual->privacidade->vinculaResposta() ? $participacao->id : null;
                    $agora = now();
                    $linhas = [];
                    foreach ($respostas as $perguntaId => $alternativas) {
                        foreach ($alternativas as $alternativaId) {
                            $linhas[] = [
                                'participacao_id' => $vinculo,
                                'pergunta_id' => $perguntaId,
                                'alternativa_id' => $alternativaId,
                                'created_at' => $agora,
                                'updated_at' => $agora,
                            ];
                        }
                    }
                    if ($linhas) {
                        DB::table('respostas')->insert($linhas);
                    }

                    // Limite de participantes: incremento condicional e atômico, no fim da transação
                    // para manter o lock da linha o mais curto possível.
                    if ($atual->limite_participantes !== null) {
                        $afetadas = DB::table('votacoes')
                            ->where('id', $atual->id)
                            ->whereColumn('participantes_count', '<', 'limite_participantes')
                            ->increment('participantes_count');
                        if ($afetadas === 0) {
                            throw VotacaoException::limiteAtingido();
                        }
                    }

                    return $participacao;
                }, 3); // 3 tentativas automáticas em caso de deadlock

                break;
            } catch (QueryException $e) {
                if (! self::violacaoDeUnicidade($e)) {
                    throw $e;
                }
                if (Participacao::where('votacao_id', $votacao->id)->where('usuario_id', $usuario->id)->exists()) {
                    throw VotacaoException::jaVotou();
                }
                // Colisão (improvável) do protocolo aleatório: tenta de novo com outro código.
                if ($tentativa === 3) {
                    throw $e;
                }
            }
        }

        // Fora do caminho crítico: auditoria + evento (broadcast/cache em fila).
        $this->auditoria->registrar('VOTO_REGISTRADO', $usuario, $votacao, ['protocolo' => $participacao->protocolo]);
        ParticipacaoRegistrada::dispatch($votacao->public_id, $votacao->id);

        return $participacao;
    }

    /**
     * Violação de UNIQUE em qualquer driver (Laravel 8 não tem UniqueConstraintViolationException):
     * SQLSTATE 23000 (MySQL/SQLite) ou 23505 (PostgreSQL) com mensagem de duplicidade.
     */
    public static function violacaoDeUnicidade(QueryException $e): bool
    {
        $estado = (string) $e->getCode();
        if (! in_array($estado, ['23000', '23505'], true)) {
            return false;
        }
        $msg = strtolower($e->getMessage());

        return strpos($msg, 'unique') !== false || strpos($msg, 'duplicate') !== false;
    }

    public function jaParticipou(Votacao $votacao, User $usuario): ?Participacao
    {
        return Participacao::where('votacao_id', $votacao->id)->where('usuario_id', $usuario->id)->first();
    }
}
