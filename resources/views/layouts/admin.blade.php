@extends('layouts.base')

@php
    $item = fn ($rota, $rotulo, $ativo = null) => '<a href="'.e(route($rota)).'" class="flex items-center rounded-lg px-3 py-2 text-sm font-medium '.(request()->routeIs($ativo ?? $rota) ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-100').'" '.(request()->routeIs($ativo ?? $rota) ? 'aria-current="page"' : '').'>'.e($rotulo).'</a>';
@endphp

@section('corpo')
<div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]" x-data="{ menu: false }">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
        <span class="font-bold text-brand-700">{{ config('votaflow.nome') }}</span>
        <button type="button" class="btn btn-secondary btn-sm" @click="menu = !menu" :aria-expanded="menu" aria-controls="menu-admin">Menu</button>
    </header>

    <aside id="menu-admin" class="border-r border-slate-200 bg-white p-4 lg:block" :class="menu ? 'block' : 'hidden'">
        <a href="{{ route('admin.dashboard') }}" class="mb-6 hidden items-center gap-2 text-lg font-bold text-brand-700 lg:flex">
            <span aria-hidden="true" class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white">✓</span> {{ config('votaflow.nome') }}
        </a>
        <nav aria-label="Menu principal" class="space-y-1">
            {!! $item('admin.dashboard', 'Dashboard') !!}
            <p class="px-3 pt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Votações</p>
            <div class="ml-2 space-y-1 border-l border-slate-200 pl-2">
                <a href="{{ route('admin.votacoes.index') }}" class="block rounded-lg px-3 py-2 text-sm {{ request()->routeIs('admin.votacoes.index') && ! request('status') ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-700 hover:bg-slate-100' }}">Todas</a>
                <a href="{{ route('admin.votacoes.create') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Criar votação</a>
                <a href="{{ route('admin.votacoes.index', ['status' => 'aberta']) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Abertas</a>
                <a href="{{ route('admin.votacoes.index', ['status' => 'agendada']) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Agendadas</a>
                <a href="{{ route('admin.votacoes.index', ['status' => 'encerrada']) }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">Encerradas</a>
            </div>
            <div class="pt-3"></div>
            {!! $item('admin.resultados.index', 'Resultados', 'admin.resultados.*') !!}
            @can('viewAny', \App\Models\User::class)
                {!! $item('admin.usuarios.index', 'Usuários', 'admin.usuarios.*') !!}
                {!! $item('admin.auditoria', 'Auditoria') !!}
                {!! $item('admin.configuracoes', 'Configurações') !!}
            @endcan
        </nav>
        <div class="mt-8 border-t border-slate-200 pt-4 text-sm">
            <p class="font-medium">{{ auth()->user()->name }}</p>
            <p class="text-slate-500">{{ auth()->user()->role->rotulo() }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="text-brand-700 underline">Sair</button></form>
        </div>
    </aside>

    <main id="conteudo" class="min-w-0 p-4 sm:p-8">
        @include('components.flash')
        @yield('conteudo')
    </main>
</div>
@endsection
