@extends('layouts.admin')
@section('titulo', 'Votações')

@section('conteudo')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Votações @if($filtro)<span class="badge {{ $filtro->cor() }} align-middle">{{ $filtro->rotulo() }}</span>@endif</h1>
    <a href="{{ route('admin.votacoes.create') }}" class="btn btn-primary">Criar votação</a>
</div>

<div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr>
            <th scope="col" class="px-4 py-3">Título</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Período</th><th scope="col" class="px-4 py-3 text-right">Participantes</th><th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($votacoes as $v)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $v->titulo }}<div class="font-mono text-xs text-slate-500">{{ $v->public_id }}</div></td>
                <td class="px-4 py-3"><span class="badge {{ $v->status->cor() }}">{{ $v->status->rotulo() }}</span></td>
                <td class="px-4 py-3 text-slate-600">{{ optional($v->inicio_em)->format('d/m/Y H:i') ?? '—' }}<br>{{ optional($v->fim_em)->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($v->participacoes_count, 0, ',', '.') }}</td>
                <td class="px-4 py-3 text-right"><a class="text-brand-700 underline" href="{{ route('admin.votacoes.show', $v) }}">Abrir</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nenhuma votação encontrada.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $votacoes->links() }}</div>
@endsection
