<?php

namespace App\Services;

use App\Enums\PrivacidadeVotacao;
use App\Enums\StatusVotacao;
use App\Enums\TipoPergunta;
use App\Exceptions\VotacaoException;
use App\Models\User;
use App\Models\Votacao;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VotacaoService
{
    /** @var AuditoriaService */
    private $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /** @param array<string, mixed> $dados dados já validados por SalvarVotacaoRequest */
    public function criar(array $dados, User $autor): Votacao
    {
        $votacao = DB::transaction(function () use ($dados, $autor) {
            $votacao = new Votacao($this->camposVotacao($dados));
            $votacao->status = StatusVotacao::Rascunho();
            $votacao->created_by = $autor->id;
            $votacao->save();
            $this->sincronizarPerguntas($votacao, $dados['perguntas'] ?? []);

            return $votacao;
        });

        $this->auditoria->registrar('CRIAR_VOTACAO', $autor, $votacao, ['titulo' => $votacao->titulo]);

        return $votacao;
    }

    public function atualizar(Votacao $votacao, array $dados, User $autor): Votacao
    {
        DB::transaction(function () use ($votacao, $dados) {
            $travada = Votacao::query()->lockForUpdate()->findOrFail($votacao->id);

            if ($travada->status->permiteEdicaoEstrutural()) {
                $travada->fill($this->camposVotacao($dados))->save();
                $this->sincronizarPerguntas($travada, $dados['perguntas'] ?? []);
            } elseif ($travada->status === StatusVotacao::Aberta()) {
                // Votação aberta: só ajustes seguros que não alteram o significado dos votos já dados.
                $travada->fill(Arr::only($dados, ['fim_em', 'limite_participantes']));
                if ($travada->limite_participantes !== null && $travada->limite_participantes < $travada->participantes_count) {
                    throw new VotacaoException('O limite não pode ser menor que o número de participantes atual.', VotacaoException::EDICAO);
                }
                $travada->save();
            } else {
                throw new VotacaoException('Esta votação não pode mais ser editada.', VotacaoException::EDICAO);
            }
            $votacao->setRawAttributes($travada->getAttributes(), true);
        });

        $this->auditoria->registrar('EDITAR_VOTACAO', $autor, $votacao, ['titulo' => $votacao->titulo]);

        return $votacao->refresh();
    }

    public function transicionar(Votacao $votacao, StatusVotacao $destino, User $autor): Votacao
    {
        DB::transaction(function () use ($votacao, $destino) {
            $v = Votacao::query()->lockForUpdate()->findOrFail($votacao->id);

            if (! $v->status->podeIrPara($destino)) {
                throw new VotacaoException("Não é possível mudar de {$v->status->rotulo()} para {$destino->rotulo()}.", VotacaoException::TRANSICAO);
            }

            if ($destino === StatusVotacao::Agendada() && ! $v->inicio_em) {
                throw new VotacaoException('Defina a data de início para agendar.', VotacaoException::TRANSICAO);
            }
            if (in_array($destino, [StatusVotacao::Agendada(), StatusVotacao::Aberta()], true)) {
                if ($v->perguntas()->count() === 0) {
                    throw new VotacaoException('Adicione ao menos uma pergunta antes de publicar.', VotacaoException::TRANSICAO);
                }
                if ($v->perguntas()->whereDoesntHave('alternativas')->exists()) {
                    throw new VotacaoException('Toda pergunta precisa ter alternativas.', VotacaoException::TRANSICAO);
                }
            }
            if ($destino === StatusVotacao::Aberta()) {
                if ($v->fim_em && $v->fim_em->isPast()) {
                    throw new VotacaoException('A data de encerramento já passou. Ajuste-a antes de abrir.', VotacaoException::TRANSICAO);
                }
                if (! $v->inicio_em || $v->inicio_em->isFuture()) {
                    $v->inicio_em = now(); // abertura manual antecipa o início
                }
            }

            $v->status = $destino;
            $v->save();
            $votacao->setRawAttributes($v->getAttributes(), true);
        });

        $acoes = [
            StatusVotacao::Aberta()->value => 'ABRIR_VOTACAO',
            StatusVotacao::Encerrada()->value => 'ENCERRAR_VOTACAO',
            StatusVotacao::Cancelada()->value => 'CANCELAR_VOTACAO',
        ];
        $acao = $acoes[$destino->value] ?? 'ALTERAR_CONFIGURACAO';
        $this->auditoria->registrar($acao, $autor, $votacao, ['para' => $destino->value]);

        return $votacao;
    }

    /** Aplicado pelo agendador: abre/encerra automaticamente pelas datas. Retorna [abertas, encerradas]. */
    public function sincronizarPorData(): array
    {
        $abertas = Votacao::where('status', StatusVotacao::Agendada()->value)
            ->where('inicio_em', '<=', now())
            ->where(fn ($q) => $q->whereNull('fim_em')->orWhere('fim_em', '>', now()))
            ->update(['status' => StatusVotacao::Aberta()->value, 'updated_at' => now()]);

        $encerradas = Votacao::where('status', StatusVotacao::Aberta()->value)
            ->whereNotNull('fim_em')->where('fim_em', '<=', now())
            ->update(['status' => StatusVotacao::Encerrada()->value, 'updated_at' => now()]);

        return [$abertas, $encerradas];
    }

    private function camposVotacao(array $d): array
    {
        return [
            'titulo' => $d['titulo'],
            'descricao' => $d['descricao'] ?? null,
            'imagem' => $d['imagem'] ?? null,
            'privacidade' => $d['privacidade'] ?? PrivacidadeVotacao::Identificada()->value,
            'inicio_em' => $d['inicio_em'] ?? null,
            'fim_em' => $d['fim_em'] ?? null,
            'limite_participantes' => $d['limite_participantes'] ?? null,
            'permite_alterar_resposta' => (bool) ($d['permite_alterar_resposta'] ?? true),
        ];
    }

    /** Recria perguntas/alternativas (só chamado enquanto não há respostas possíveis). */
    private function sincronizarPerguntas(Votacao $votacao, array $perguntas): void
    {
        $votacao->perguntas()->delete();

        foreach (array_values($perguntas) as $i => $p) {
            $tipo = TipoPergunta::from($p['tipo']);
            $pergunta = $votacao->perguntas()->create([
                'tipo' => $tipo->value,
                'titulo' => $p['titulo'],
                'descricao' => $p['descricao'] ?? null,
                'ordem' => $i + 1,
                'obrigatoria' => (bool) ($p['obrigatoria'] ?? true),
            ]);

            $textos = $tipo->alternativasFixas()
                ?? array_values(array_filter(array_map('trim', $p['alternativas'] ?? []), fn ($t) => $t !== ''));

            foreach ($textos as $j => $texto) {
                $pergunta->alternativas()->create(['texto' => $texto, 'ordem' => $j + 1]);
            }
        }

        $votacao->touch(); // invalida o cache da estrutura
    }
}
