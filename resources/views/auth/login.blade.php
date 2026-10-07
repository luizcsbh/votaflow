@extends('layouts.participante')
@section('titulo', 'Entrar — '.config('votaflow.nome'))

@section('conteudo')
<div class="card text-center">
    <h1 class="text-2xl font-bold">Entrar</h1>
    <p class="mt-3 text-slate-600">Para continuar, entre com sua conta Google.</p>
    <a href="{{ route('auth.google') }}" class="btn btn-primary mt-6 w-full">@include('components.google-icon') Continuar com Google</a>
    @if (config('votaflow.dev_login'))
        <a href="{{ route('dev.login') }}" class="btn btn-secondary mt-3 w-full">Entrar como usuário de teste (dev)</a>
    @endif
    <p class="mt-4 text-xs text-slate-500">Usamos apenas seu nome, e-mail e foto para identificar sua participação. Nunca vemos sua senha.</p>
</div>
@endsection
