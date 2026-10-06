@extends('layouts.admin')
@section('titulo', 'Usuários')

@section('conteudo')
<h1 class="text-2xl font-bold">Usuários</h1>

<form method="POST" action="{{ route('admin.usuarios.store') }}" class="card mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_12rem_auto] sm:items-end">
    @csrf
    <div><label class="label" for="nu-email">E-mail Google *</label><input class="input" id="nu-email" type="email" name="email" value="{{ old('email') }}" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="nu-nome">Nome</label><input class="input" id="nu-nome" name="name" value="{{ old('name') }}"></div>
    <div><label class="label" for="nu-role">Perfil</label>
        <select class="input" id="nu-role" name="role">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', 'operador') === $r->value)>{{ $r->rotulo() }}</option>@endforeach</select>
    </div>
    <button class="btn btn-primary">Cadastrar</button>
</form>

<form method="GET" class="mt-5"><label class="sr-only" for="q">Buscar</label><input class="input max-w-sm" id="q" name="q" value="{{ request('q') }}" placeholder="Buscar por nome ou e-mail"></form>

<div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th scope="col" class="px-4 py-3">Nome</th><th scope="col" class="px-4 py-3">E-mail</th><th scope="col" class="px-4 py-3">Perfil</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($usuarios as $u)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $u->name }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $u->email }}@unless($u->google_id) <span class="badge bg-amber-100 text-amber-800">aguardando 1º login</span>@endunless</td>
                <td class="px-4 py-3">
                    @can('update', $u)
                        <form method="POST" action="{{ route('admin.usuarios.update', $u) }}" class="flex gap-2">@csrf @method('PUT')
                            <label class="sr-only" for="r{{ $u->id }}">Perfil de {{ $u->name }}</label>
                            <select class="input !min-h-9 !py-1" id="r{{ $u->id }}" name="role">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->rotulo() }}</option>@endforeach</select>
                            <button class="btn btn-secondary btn-sm">Salvar</button>
                        </form>
                    @else
                        {{ $u->role->rotulo() }}
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $usuarios->links() }}</div>
@endsection
