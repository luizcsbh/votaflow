@extends('layouts.admin')
@section('titulo', 'Configurações')

@section('conteudo')
<h1 class="text-2xl font-bold">Configurações</h1>
<p class="mt-1 text-sm text-slate-600">Visão somente leitura do ambiente. Alterações são feitas via variáveis de ambiente (.env / GitLab CI Variables).</p>
<dl class="card mt-5 divide-y divide-slate-100">
    @foreach ($itens as $k => $v)
        <div class="flex justify-between gap-4 py-2 text-sm"><dt class="text-slate-600">{{ $k }}</dt><dd class="font-medium">{{ $v }}</dd></div>
    @endforeach
</dl>
@endsection
