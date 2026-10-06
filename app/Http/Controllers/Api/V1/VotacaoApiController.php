<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusVotacao;
use App\Exceptions\VotacaoException;
use App\Http\Controllers\Controller;
use App\Http\Resources\VotacaoResource;
use App\Models\Votacao;
use App\Services\EstruturaVotacao;
use App\Services\ResultadoService;
use App\Services\VotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VotacaoApiController extends Controller
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

    public function index(Request $request)
    {
        $this->authorize('viewAny', Votacao::class);
        $q = Votacao::query()->latest();
        if (! $request->user()->isAdmin()) {
            $q->where('created_by', $request->user()->id);
        }

        return VotacaoResource::collection($q->paginate(20));
    }

    public function show(Votacao $votacao)
    {
        abort_if($votacao->status === StatusVotacao::Rascunho(), 404);

        return new VotacaoResource($votacao);
    }

    /** Elegibilidade + perguntas. */
    public function participar(Request $request, Votacao $votacao): JsonResponse
    {
        abort_if($votacao->status === StatusVotacao::Rascunho(), 404);
        if ($p = $this->votos->jaParticipou($votacao, $request->user())) {
            return response()->json(['message' => VotacaoException::jaVotou()->getMessage(), 'protocolo' => $p->protocolo], 409);
        }
        if (! $votacao->aceitaVotos()) {
            return response()->json(['message' => VotacaoException::fechada()->getMessage()], 403);
        }

        return response()->json(['votacao' => new VotacaoResource($votacao), 'perguntas' => $this->estrutura->obter($votacao)]);
    }

    /** Valida o rascunho de respostas sem gravar nada. */
    public function responder(Request $request, Votacao $votacao): JsonResponse
    {
        $dados = $request->validate(['respostas' => ['required', 'array', 'max:200']]);
        try {
            $this->votos->validarRespostas($votacao, $dados['respostas']);
        } catch (VotacaoException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->erros], 422);
        }

        return response()->json(['valido' => true]);
    }

    /** Registro definitivo e transacional. */
    public function finalizar(Request $request, Votacao $votacao): JsonResponse
    {
        $dados = $request->validate(['respostas' => ['required', 'array', 'max:200']]);

        try {
            $participacao = $this->votos->registrar($votacao, $request->user(), $dados['respostas']);
        } catch (VotacaoException $e) {
            $codigos = [VotacaoException::JA_VOTOU => 409, VotacaoException::INVALIDA => 422];
            $status = $codigos[$e->codigo] ?? 403;

            return response()->json(['message' => $e->getMessage(), 'errors' => $e->erros], $status);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não foi possível registrar sua participação. Tente novamente.'], 500);
        }

        return response()->json(['message' => 'Voto registrado!', 'protocolo' => $participacao->protocolo], 201);
    }

    public function resultados(Votacao $votacao, ResultadoService $resultados): JsonResponse
    {
        $this->authorize('verResultados', $votacao);

        return response()->json($resultados->paraVotacao($votacao));
    }
}
