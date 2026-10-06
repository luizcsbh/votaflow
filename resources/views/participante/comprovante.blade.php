@extends('layouts.participante')
@section('titulo', 'Voto registrado')

@section('conteudo')
<div class="card text-center">
    <div class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700" aria-hidden="true">✓</div>
    <h1 class="mt-4 text-2xl font-bold" role="status">Voto registrado!</h1>
    <p class="mt-2 text-slate-600">Obrigado pela sua participação.<br>Sua participação foi registrada com sucesso.</p>

    <p class="mt-6 text-sm text-slate-500">Código de confirmação</p>
    <p class="mt-1 select-all rounded-xl bg-slate-100 py-3 font-mono text-3xl font-bold tracking-widest">{{ $participacao->protocolo }}</p>
    <p class="mt-2 text-xs text-slate-500">{{ $votacao->titulo }} · {{ optional($participacao->finalizado_em)->format('d/m/Y H:i') }}</p>

    <a href="{{ route('votacao.mostrar', $votacao) }}" class="btn btn-secondary mt-6 w-full">Voltar para o início</a>
</div>
@endsection
