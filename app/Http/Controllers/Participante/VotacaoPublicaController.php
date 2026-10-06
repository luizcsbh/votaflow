<?php

namespace App\Http\Controllers\Participante;

use App\Enums\StatusVotacao;
use App\Exceptions\VotacaoException;
use App\Http\Controllers\Controller;
use App\Models\Votacao;
use App\Services\EstruturaVotacao;
use App\Services\VotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VotacaoPublicaController extends Controller
{
    /** @var VotoService */
    private $votos;

    /** @var EstruturaVotacao */
    private $estrutura;

    public function __construct(VotoService $votos, EstruturaVotacao $estrutura)
    {
        $this->votos = $votos;
        $this->estrutura = $estrutura;
    }

    /** Etapa 2 — apresentação (aberta a qualquer visitante que tenha o QR Code). */
    public function mostrar(Votacao $votacao)
    {
        abort_if($votacao->status === StatusVotacao::Rascunho(), 404);

        return view('participante.mostrar', [
            'votacao' => $votacao,
            'participacao' => auth()->check() ? $this->votos->jaParticipou($votacao, auth()->user()) : null,
        ]);
    }

    /** Etapas 4–11 — interface de votação (requer login). */
    public function votar(Votacao $votacao)
    {
        abort_if($votacao->status === StatusVotacao::Rascunho(), 404);

        if ($this->votos->jaParticipou($votacao, auth()->user())) {
            return redirect()->route('votacao.comprovante', $votacao);
        }
        if (! $votacao->aceitaVotos()) {
            return redirect()->route('votacao.mostrar', $votacao);
        }

        $perguntas = $this->estrutura->obter($votacao);

        return view('participante.votar', [
            'votacao' => $votacao,
            'perguntas' => $perguntas,
            'minutos' => max(1, (int) ceil(count($perguntas) * config('votaflow.segundos_por_pergunta') / 60)),
            'iniciadoEm' => now()->toIso8601String(),
        ]);
    }

    /** Confirmação final: registra tudo em uma transação. */
    public function enviar(Request $request, Votacao $votacao)
    {
        $dados = $request->validate([
            'respostas' => ['nullable', 'array', 'max:200'],
            'iniciado_em' => ['nullable', 'date'],
        ]);

        try {
            $this->votos->registrar($votacao, $request->user(), $dados['respostas'] ?? [], isset($dados['iniciado_em']) ? Carbon::parse($dados['iniciado_em']) : null);
        } catch (VotacaoException $e) {
            if ($e->codigo === VotacaoException::JA_VOTOU) {
                return redirect()->route('votacao.comprovante', $votacao);
            }
            if ($e->codigo === VotacaoException::INVALIDA) {
                return back()->withErrors($e->erros ?: ['respostas' => $e->getMessage()])->with('erro', $e->getMessage());
            }

            return redirect()->route('votacao.mostrar', $votacao)->with('erro', $e->getMessage());
        } catch (\Throwable $e) {
            report($e); // detalhe técnico vai para o log; o usuário vê mensagem amigável

            return back()->with('erro', 'Não foi possível registrar sua participação. Verifique sua conexão e tente novamente.');
        }

        return redirect()->route('votacao.comprovante', $votacao)->with('recem_votou', true);
    }

    public function comprovante(Request $request, Votacao $votacao)
    {
        $participacao = $this->votos->jaParticipou($votacao, $request->user());
        abort_unless($participacao, 404); // só o próprio participante vê o seu comprovante

        return view('participante.comprovante', ['votacao' => $votacao, 'participacao' => $participacao]);
    }
}
