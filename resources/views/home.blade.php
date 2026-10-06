@extends('layouts.participante')
@section('titulo', config('votaflow.nome'))

@section('conteudo')
<div class="card text-center">
    <h1 class="text-2xl font-bold">Bem-vindo(a)!</h1>
    <p class="mt-3 text-slate-600">Para participar de uma votação, escaneie o <strong>QR Code</strong> do evento com a câmera do seu celular.</p>
    @auth
        @if (auth()->user()->podeAcessarPainel())
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary mt-6 w-full">Ir para o painel</a>
        @endif
    @else
        <a href="{{ route('login') }}" class="btn btn-secondary mt-6 w-full">Sou organizador</a>
    @endauth
</div>
@endsection
