<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrivacidadeVotacao;
use App\Enums\StatusVotacao;
use App\Enums\TipoPergunta;
use App\Exceptions\VotacaoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarVotacaoRequest;
use App\Models\Votacao;
use App\Services\ResultadoService;
use App\Services\VotacaoService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VotacaoController extends Controller
{
    /** @var VotacaoService */
    private $service;

    public function __construct(VotacaoService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Votacao::class);

        $query = Votacao::query()->withCount('participacoes')->latest();
        if (! $request->user()->isAdmin()) {
            $query->where('created_by', $request->user()->id);
        }
        $status = StatusVotacao::tryFrom(strtoupper((string) $request->query('status')));
        if ($status) {
            $query->where('status', $status->value);
        }

        return view('admin.votacoes.index', ['votacoes' => $query->paginate(15)->withQueryString(), 'filtro' => $status]);
    }

    public function create()
    {
        $this->authorize('create', Votacao::class);

        return view('admin.votacoes.form', $this->dadosFormulario(new Votacao(['permite_alterar_resposta' => true, 'privacidade' => PrivacidadeVotacao::Identificada()])));
    }

    public function store(SalvarVotacaoRequest $request)
    {
        $votacao = $this->service->criar($request->validated(), $request->user());

        return redirect()->route('admin.votacoes.show', $votacao)->with('ok', 'Votação criada como rascunho.');
    }

    public function show(Votacao $votacao)
    {
        $this->authorize('view', $votacao);
        $votacao->load('perguntas.alternativas');

        return view('admin.votacoes.show', [
            'votacao' => $votacao,
            'participantes' => app(ResultadoService::class)->totalParticipantes($votacao->id),
            'qr' => $this->svgQr($votacao),
        ]);
    }

    public function edit(Votacao $votacao)
    {
        $this->authorize('update', $votacao);
        $votacao->load('perguntas.alternativas');

        return view('admin.votacoes.form', $this->dadosFormulario($votacao));
    }

    public function update(SalvarVotacaoRequest $request, Votacao $votacao)
    {
        try {
            $this->service->atualizar($votacao, $request->validated(), $request->user());
        } catch (VotacaoException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('admin.votacoes.show', $votacao)->with('ok', 'Votação atualizada.');
    }

    public function transicao(Request $request, Votacao $votacao)
    {
        $destino = StatusVotacao::tryFrom((string) $request->input('destino'));
        abort_unless($destino, 422);

        // Cada ação possui sua própria permissão (cancelar/reabrir = somente admin).
        if ($destino === StatusVotacao::Cancelada()) {
            $habilidade = 'cancelar';
        } elseif ($destino === StatusVotacao::Aberta() && $votacao->status === StatusVotacao::Encerrada()) {
            $habilidade = 'reabrir';
        } elseif ($destino === StatusVotacao::Encerrada()) {
            $habilidade = 'encerrar';
        } else {
            $habilidade = 'abrir';
        }
        $this->authorize($habilidade, $votacao);

        try {
            $this->service->transicionar($votacao, $destino, $request->user());
        } catch (VotacaoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('ok', "Status alterado para {$destino->rotulo()}.");
    }

    public function qrcode(Votacao $votacao)
    {
        $this->authorize('view', $votacao);

        return view('admin.votacoes.qrcode', ['votacao' => $votacao, 'qr' => $this->svgQr($votacao, 360)]);
    }

    public function qrcodeDownload(Votacao $votacao)
    {
        $this->authorize('view', $votacao);

        return response($this->svgQr($votacao, 1024), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qrcode-'.$votacao->public_id.'.svg"',
        ]);
    }

    public function imprimir(Votacao $votacao)
    {
        $this->authorize('view', $votacao);

        return view('admin.votacoes.imprimir', ['votacao' => $votacao, 'qr' => $this->svgQr($votacao, 520)]);
    }

    private function svgQr(Votacao $votacao, int $tamanho = 240): string
    {
        return (string) QrCode::format('svg')->size($tamanho)->margin(1)->errorCorrection('M')->generate($votacao->urlPublica());
    }

    private function dadosFormulario(Votacao $votacao): array
    {
        $perguntas = $votacao->exists
            ? $votacao->perguntas->map(fn ($p) => [
                'tipo' => $p->tipo->value, 'titulo' => $p->titulo, 'descricao' => $p->descricao,
                'obrigatoria' => $p->obrigatoria,
                'alternativas' => $p->alternativas->pluck('texto')->all(),
            ])->all()
            : [];

        return [
            'votacao' => $votacao,
            'tipos' => collect(TipoPergunta::cases())->mapWithKeys(fn ($t) => [$t->value => ['rotulo' => $t->rotulo(), 'fixa' => $t->alternativasFixas() !== null]])->all(),
            'perguntasIniciais' => old('perguntas', $perguntas),
            'privacidades' => PrivacidadeVotacao::cases(),
            'estrutural' => ! $votacao->exists || $votacao->status->permiteEdicaoEstrutural(),
        ];
    }
}
