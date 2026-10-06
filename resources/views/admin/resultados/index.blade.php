@extends('layouts.admin')
@section('titulo', 'Resultados')

@section('conteudo')
<h1 class="text-2xl font-bold">Resultados</h1>
<div class="mt-5 space-y-3">
    @forelse ($votacoes as $v)
        <a href="{{ route('admin.resultados.show', $v) }}" class="card flex items-center justify-between gap-3 !p-4 hover:border-brand-500">
            <span><span class="font-semibold">{{ $v->titulo }}</span><br><span class="text-sm text-slate-500">{{ number_format($v->participacoes_count, 0, ',', '.') }} participantes</span></span>
            <span class="badge {{ $v->status->cor() }}">{{ $v->status->rotulo() }}</span>
        </a>
    @empty
        <p class="card text-slate-600">Ainda não há votações abertas ou encerradas.</p>
    @endforelse
</div>
<div class="mt-4">{{ $votacoes->links() }}</div>
@endsection
