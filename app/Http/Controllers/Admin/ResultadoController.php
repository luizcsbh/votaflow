<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusVotacao;
use App\Http\Controllers\Controller;
use App\Models\Votacao;
use App\Services\AuditoriaService;
use App\Services\ResultadoService;
use Illuminate\Http\Request;

class ResultadoController extends Controller
{
    /** @var ResultadoService */
    private $resultados;

    public function __construct(ResultadoService $resultados)
    {
        $this->resultados = $resultados;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Votacao::class);
        $q = Votacao::query()->whereIn('status', [StatusVotacao::Aberta()->value, StatusVotacao::Encerrada()->value])->withCount('participacoes')->latest();
        if (! $request->user()->isAdmin()) {
            $q->where('created_by', $request->user()->id);
        }

        return view('admin.resultados.index', ['votacoes' => $q->paginate(15)]);
    }

    public function show(Votacao $votacao)
    {
        $this->authorize('verResultados', $votacao);

        return view('admin.resultados.show', ['votacao' => $votacao, 'resultado' => $this->resultados->paraVotacao($votacao)]);
    }

    /** JSON agregado (cache curto) usado pela atualização automática da tela. */
    public function dados(Votacao $votacao)
    {
        $this->authorize('verResultados', $votacao);

        return response()->json($this->resultados->paraVotacao($votacao));
    }

    public function exportar(Votacao $votacao, AuditoriaService $auditoria)
    {
        $this->authorize('exportar', $votacao);
        $r = $this->resultados->calcular($votacao);
        $auditoria->registrar('EXPORTAR_RESULTADOS', auth()->user(), $votacao);

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel abrir UTF-8
            fputcsv($out, ['Pergunta', 'Alternativa', 'Votos', 'Percentual'], ';');
            foreach ($r['perguntas'] as $p) {
                foreach ($p['alternativas'] as $a) {
                    // Prevenção de CSV injection: células iniciadas por = + - @ são neutralizadas.
                    $cel = fn ($t) => preg_match('/^[=+\-@]/', (string) $t) ? "'".$t : $t;
                    fputcsv($out, [$cel($p['titulo']), $cel($a['texto']), $a['total'], str_replace('.', ',', (string) $a['percentual']).'%'], ';');
                }
            }
            fclose($out);
        }, 'resultados-'.$votacao->public_id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
