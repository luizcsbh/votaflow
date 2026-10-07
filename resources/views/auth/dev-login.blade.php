@extends('layouts.participante')
@section('titulo', 'Login de desenvolvimento — '.config('votaflow.nome'))

@section('conteudo')
<div class="card">
    <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
        <strong>Somente desenvolvimento.</strong> Este login não usa Google e não existe fora de <code>APP_ENV=local</code>.
    </p>
    <h1 class="mt-4 text-2xl font-bold">Entrar como…</h1>

    <form method="POST" action="{{ route('dev.login.entrar') }}" class="mt-4">
        @csrf
        <button type="submit" class="btn btn-primary w-full">Novo participante de teste</button>
    </form>

    <ul class="mt-5 divide-y divide-slate-200">
        @foreach ($usuarios as $u)
            <li class="py-2">
                <form method="POST" action="{{ route('dev.login.entrar') }}" class="flex items-center justify-between gap-3">
                    @csrf
                    <input type="hidden" name="usuario" value="{{ $u->id }}">
                    <span class="min-w-0 text-left text-sm"><span class="block truncate font-medium">{{ $u->name }}</span><span class="block truncate text-xs text-slate-500">{{ $u->email }} · {{ $u->role->rotulo() }}</span></span>
                    <button type="submit" class="btn btn-secondary btn-sm shrink-0">Entrar</button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
@endsection
