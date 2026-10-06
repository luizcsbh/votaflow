@extends('layouts.admin')
@section('titulo', 'Dashboard')

@section('conteudo')
<h1 class="text-2xl font-bold">Votações</h1>

<div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([['Votações criadas', $total], ['Abertas', $porStatus['ABERTA'] ?? 0], ['Encerradas', $porStatus['ENCERRADA'] ?? 0], ['Agendadas', $porStatus['AGENDADA'] ?? 0]] as [$rotulo, $n])
        <div class="card !p-4">
            <p class="text-3xl font-bold tabular-nums">{{ number_format($n, 0, ',', '.') }}</p>
            <p class="text-sm text-slate-600">{{ $rotulo }}</p>
        </div>
    @endforeach
</div>

<h2 class="mt-8 text-lg font-semibold">Em andamento</h2>
<div class="mt-3 space-y-4">
    @forelse ($abertas as $v)
        @php $pct = $v->limite_participantes ? min(100, round($v->participantes / $v->limite_participantes * 100)) : null; @endphp
        <article class="card">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold">{{ $v->titulo }}</h3>
                    <p class="text-sm text-slate-500">Início: {{ optional($v->inicio_em)->format('d/m H:i') ?? '—' }} · Fim: {{ optional($v->fim_em)->format('d/m H:i') ?? 'sem prazo' }}</p>
                </div>
                <span class="badge {{ $v->status->cor() }}">{{ $v->status->rotulo() }}</span>
            </div>
            <p class="mt-4 text-sm font-medium">Participantes</p>
            @if ($pct !== null)
                <div class="mt-1 h-3 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Participação"><div class="h-full rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div>
                <p class="mt-1 text-sm text-slate-600">{{ $pct }}% — {{ number_format($v->participantes, 0, ',', '.') }} de {{ number_format($v->limite_participantes, 0, ',', '.') }}</p>
            @else
                <p class="text-2xl font-bold tabular-nums">{{ number_format($v->participantes, 0, ',', '.') }}</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.qrcode', $v) }}">QR Code</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.resultados.show', $v) }}">Resultados</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.show', $v) }}">Gerenciar</a>
            </div>
        </article>
    @empty
        <p class="card text-slate-600">Nenhuma votação aberta no momento. <a class="text-brand-700 underline" href="{{ route('admin.votacoes.create') }}">Criar votação</a></p>
    @endforelse
</div>
@endsection
