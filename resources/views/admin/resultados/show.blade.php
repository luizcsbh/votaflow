@extends('layouts.admin')
@section('titulo', 'Resultados — '.$votacao->titulo)

@section('conteudo')
<div x-data="resultados({ url: @js(route('admin.resultados.dados', $votacao)), inicial: @js($resultado), ao_vivo: @js($votacao->status->value === 'ABERTA') })">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Resultado</h1>
            <p class="text-slate-600">{{ $votacao->titulo }} · <span class="badge {{ $votacao->status->cor() }}">{{ $votacao->status->rotulo() }}</span></p>
            @if ($votacao->privacidade->value !== 'identificada')<p class="mt-1 text-xs text-slate-500">Votação {{ mb_strtolower($votacao->privacidade->rotulo()) }}: somente totais agregados são exibidos.</p>@endif
        </div>
        <div class="flex gap-2">
            @can('exportar', $votacao)<a class="btn btn-secondary btn-sm" href="{{ route('admin.resultados.exportar', $votacao) }}">Exportar CSV</a>@endcan
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.show', $votacao) }}">Voltar</a>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-live="polite">
        <div class="card !p-4"><p class="text-3xl font-bold tabular-nums" x-text="fmt(r.participantes)"></p><p class="text-sm text-slate-600">Participantes</p></div>
        <div class="card !p-4"><p class="text-3xl font-bold tabular-nums" x-text="fmt(r.total_votos)"></p><p class="text-sm text-slate-600">Votos (respostas)</p></div>
        <div class="card !p-4"><p class="text-3xl font-bold tabular-nums" x-text="r.taxa_participacao === null ? '—' : r.taxa_participacao + '%'"></p><p class="text-sm text-slate-600">Taxa de participação</p></div>
        <div class="card !p-4"><p class="text-3xl font-bold tabular-nums" x-text="r.perguntas.length"></p><p class="text-sm text-slate-600">Perguntas</p></div>
    </div>
    <p class="mt-2 text-xs text-slate-500" x-show="ao_vivo">Atualização automática a cada 5 s · última: <span x-text="atualizadoEm.toLocaleTimeString('pt-BR')"></span></p>

    <div class="mt-6 space-y-5">
        <template x-for="(p, i) in r.perguntas" :key="p.id">
            <section class="card">
                <h2 class="text-lg font-semibold" x-text="(i + 1) + '. ' + p.titulo"></h2>
                <p class="text-sm text-slate-500"><span x-text="fmt(p.total_respostas)"></span> <span x-text="p.total_respostas === 1 ? 'resposta' : 'respostas'"></span></p>
                <ul class="mt-4 space-y-4">
                    <template x-for="a in p.alternativas" :key="a.id">
                        <li>
                            <div class="flex justify-between gap-3 text-sm font-medium"><span x-text="a.texto"></span><span class="tabular-nums" x-text="a.percentual.toString().replace('.', ',') + '% (' + fmt(a.total) + ')'"></span></div>
                            <div class="mt-1 h-4 overflow-hidden rounded-full bg-slate-200" role="img" :aria-label="a.texto + ': ' + a.percentual + '%'"><div class="h-full rounded-full bg-brand-600 transition-all duration-500" :style="'width:' + a.percentual + '%'"></div></div>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </div>
</div>
@endsection
