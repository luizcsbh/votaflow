@extends('layouts.base')

@section('corpo')
<div class="mx-auto flex min-h-screen max-w-xl flex-col px-4 py-6 sm:py-10">
    <header class="mb-6 flex items-center justify-between">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-brand-700">
            <span aria-hidden="true" class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white">✓</span>
            {{ config('votaflow.nome') }}
        </a>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg px-3 py-2 text-sm text-slate-600 underline-offset-2 hover:underline">Sair</button>
            </form>
        @endauth
    </header>

    <main id="conteudo" class="flex-1">
        @include('components.flash')
        @yield('conteudo')
    </main>

    <footer class="mt-10 text-center text-xs text-slate-500">Seu voto é registrado com segurança.</footer>
</div>
@endsection
