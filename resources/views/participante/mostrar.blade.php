@extends('layouts.participante')
@section('titulo', $votacao->titulo)

@section('conteudo')
<div class="card text-center">
    @if ($votacao->imagem)
        <img src="{{ $votacao->imagem }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="mx-auto mb-4 max-h-40 rounded-xl object-contain">
    @endif
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Votação</p>
    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $votacao->titulo }}</h1>
    @if ($votacao->descricao)
        <p class="mt-3 whitespace-pre-line text-slate-600">{{ $votacao->descricao }}</p>
    @endif

    <div class="mt-6">
        @if ($participacao)
            <p class="alert alert-ok" role="status">✓ Você já participou desta votação.</p>
            <a href="{{ route('votacao.comprovante', $votacao) }}" class="btn btn-secondary mt-4 w-full">Ver meu comprovante</a>
        @elseif ($votacao->aceitaVotos())
            @auth
                <a href="{{ route('votacao.votar', $votacao) }}" class="btn btn-primary w-full text-lg">Participar da votação</a>
            @else
                <a href="{{ route('auth.google', ['destino' => '/v/'.$votacao->public_id.'/votar']) }}" class="btn btn-primary w-full text-lg">@include('components.google-icon') Continuar com Google</a>
                <p class="mt-3 text-xs text-slate-500">Para participar, entre com sua conta Google.</p>
            @endauth
        @else
            <p class="alert alert-erro" role="status">
                @switch($votacao->status->value)
                    @case('AGENDADA') Esta votação ainda não começou.@if ($votacao->inicio_em) Início: {{ $votacao->inicio_em->format('d/m/Y H:i') }}.@endif @break
                    @case('ENCERRADA') Esta votação foi encerrada. @break
                    @case('CANCELADA') Esta votação foi cancelada. @break
                    @default Esta votação não está aberta no momento.
                @endswitch
            </p>
        @endif
    </div>
</div>
@endsection
