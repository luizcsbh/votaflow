@extends('layouts.admin')
@section('titulo', $votacao->titulo)

@php
    $s = $votacao->status->value;
    $acoes = [
        ['AGENDADA', 'Agendar', 'btn-secondary', in_array($s, ['RASCUNHO'])],
        ['ABERTA', 'Abrir votação', 'btn-primary', in_array($s, ['RASCUNHO', 'AGENDADA'])],
        ['ABERTA', 'Reabrir', 'btn-secondary', $s === 'ENCERRADA' && auth()->user()->can('reabrir', $votacao)],
        ['ENCERRADA', 'Encerrar', 'btn-secondary', $s === 'ABERTA'],
        ['CANCELADA', 'Cancelar', 'btn-danger', in_array($s, ['RASCUNHO', 'AGENDADA', 'ABERTA']) && auth()->user()->can('cancelar', $votacao)],
    ];
@endphp

@section('conteudo')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold">{{ $votacao->titulo }}</h1>
        <p class="mt-1 text-sm text-slate-600"><span class="badge {{ $votacao->status->cor() }}">{{ $votacao->status->rotulo() }}</span> · {{ $votacao->privacidade->rotulo() }} · <span class="font-mono">{{ $votacao->public_id }}</span></p>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach ($acoes as [$destino, $rotulo, $classe, $visivel])
            @if ($visivel)
                <form method="POST" action="{{ route('admin.votacoes.transicao', $votacao) }}"
                      @if ($destino === 'CANCELADA') onsubmit="return confirm('Cancelar esta votação? Esta ação não pode ser desfeita.')" @endif>
                    @csrf <input type="hidden" name="destino" value="{{ $destino }}">
                    <button class="btn {{ $classe }}">{{ $rotulo }}</button>
                </form>
            @endif
        @endforeach
        @can('update', $votacao)<a href="{{ route('admin.votacoes.edit', $votacao) }}" class="btn btn-secondary">Configurações</a>@endcan
        @if (in_array($s, ['ABERTA', 'ENCERRADA']))<a href="{{ route('admin.resultados.show', $votacao) }}" class="btn btn-secondary">Resultados</a>@endif
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-[1fr_20rem]">
    <div class="space-y-5">
        <section class="card">
            <h2 class="text-lg font-semibold">Resumo</h2>
            @if ($votacao->descricao)<p class="mt-2 whitespace-pre-line text-slate-600">{{ $votacao->descricao }}</p>@endif
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Início</dt><dd class="font-medium">{{ optional($votacao->inicio_em)->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Encerramento</dt><dd class="font-medium">{{ optional($votacao->fim_em)->format('d/m/Y H:i') ?? 'sem prazo' }}</dd></div>
                <div><dt class="text-slate-500">Participantes</dt><dd class="font-medium tabular-nums">{{ number_format($participantes, 0, ',', '.') }}@if($votacao->limite_participantes) / {{ number_format($votacao->limite_participantes, 0, ',', '.') }}@endif</dd></div>
                <div><dt class="text-slate-500">Alterar resposta</dt><dd class="font-medium">{{ $votacao->permite_alterar_resposta ? 'Permitido' : 'Não permitido' }}</dd></div>
            </dl>
            <p class="mt-3 text-sm text-slate-600">{{ $votacao->privacidade->descricao() }}</p>
        </section>

        <section class="card">
            <h2 class="text-lg font-semibold">Perguntas ({{ $votacao->perguntas->count() }})</h2>
            <ol class="mt-3 space-y-4">
                @foreach ($votacao->perguntas as $p)
                    <li>
                        <p class="font-medium">{{ $loop->iteration }}. {{ $p->titulo }} <span class="badge bg-slate-100 text-slate-700">{{ $p->tipo->rotulo() }}</span>@unless($p->obrigatoria) <span class="text-xs text-slate-500">(opcional)</span>@endunless</p>
                        <ul class="ml-5 mt-1 list-disc text-sm text-slate-600">@foreach ($p->alternativas as $a)<li>{{ $a->texto }}</li>@endforeach</ul>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <aside class="card h-fit text-center" x-data="{ copiado: false }">
        <h2 class="text-lg font-semibold">QR Code</h2>
        <div class="mx-auto mt-3 w-48 [&_svg]:h-auto [&_svg]:w-full" role="img" aria-label="QR Code da votação">{!! $qr !!}</div>
        <p class="mt-2 break-all text-xs text-slate-500">{{ $votacao->urlPublica() }}</p>
        <div class="mt-4 grid gap-2">
            <button type="button" class="btn btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($votacao->urlPublica())).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })">
                <span x-show="!copiado">Copiar link</span><span x-show="copiado" x-cloak>Link copiado ✓</span>
            </button>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.qrcode.download', $votacao) }}">Baixar QR Code</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.imprimir', $votacao) }}" target="_blank" rel="noopener">Versão para impressão</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.votacoes.qrcode', $votacao) }}">Ampliar</a>
            <button type="button" class="btn btn-secondary btn-sm" x-show="navigator.share" x-cloak @click="navigator.share({ title: @js($votacao->titulo), url: @js($votacao->urlPublica()) })">Compartilhar</button>
        </div>
    </aside>
</div>
@endsection
