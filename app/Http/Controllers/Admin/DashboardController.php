<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusVotacao;
use App\Http\Controllers\Controller;
use App\Models\Votacao;
use App\Services\ResultadoService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(ResultadoService $resultados)
    {
        $user = auth()->user();
        $base = Votacao::query();
        if (! $user->isAdmin()) {
            $base->where('created_by', $user->id);
        }

        $porStatus = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        $abertas = (clone $base)->where('status', StatusVotacao::Aberta()->value)->orderBy('fim_em')->get()
            ->each(fn ($v) => $v->setAttribute('participantes', $resultados->totalParticipantes($v->id)));

        return view('admin.dashboard', [
            'total' => $porStatus->sum(),
            'porStatus' => $porStatus,
            'abertas' => $abertas,
        ]);
    }
}
