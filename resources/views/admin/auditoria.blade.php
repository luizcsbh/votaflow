@extends('layouts.admin')
@section('titulo', 'Auditoria')

@section('conteudo')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Auditoria</h1>
    <form method="GET" class="flex items-center gap-2"><label class="text-sm" for="acao">Ação</label>
        <select class="input !min-h-9 !w-auto !py-1" id="acao" name="acao" onchange="this.form.submit()"><option value="">Todas</option>@foreach ($acoes as $a)<option value="{{ $a }}" @selected(request('acao') === $a)>{{ $a }}</option>@endforeach</select>
    </form>
</div>
<div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th scope="col" class="px-4 py-3">Quando</th><th scope="col" class="px-4 py-3">Ação</th><th scope="col" class="px-4 py-3">Usuário</th><th scope="col" class="px-4 py-3">Votação</th><th scope="col" class="px-4 py-3">IP</th><th scope="col" class="px-4 py-3">Dados</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($registros as $r)
            <tr>
                <td class="whitespace-nowrap px-4 py-3">{{ optional($r->created_at)->format('d/m/Y H:i:s') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $r->acao }}</td>
                <td class="px-4 py-3">{{ optional($r->usuario)->name ?? '—' }}</td>
                <td class="px-4 py-3">{{ optional($r->votacao)->titulo ?? '—' }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $r->ip }}</td>
                <td class="max-w-xs truncate px-4 py-3 font-mono text-xs" title="{{ json_encode($r->dados, JSON_UNESCAPED_UNICODE) }}">{{ $r->dados ? json_encode($r->dados, JSON_UNESCAPED_UNICODE) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhum registro.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $registros->links() }}</div>
@endsection
