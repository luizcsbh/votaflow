@extends('layouts.base')

{{-- Layout mínimo: não depende de sessão/usuário, para funcionar mesmo com o app degradado. --}}
@section('corpo')
<main id="conteudo" class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
    <div class="card w-full text-center">
        <p class="text-5xl font-extrabold text-brand-600">@yield('codigo')</p>
        <h1 class="mt-3 text-2xl font-bold">@yield('titulo')</h1>
        <p class="mt-2 text-slate-600">@yield('mensagem')</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6 w-full">Voltar ao início</a>
    </div>
</main>
@endsection
