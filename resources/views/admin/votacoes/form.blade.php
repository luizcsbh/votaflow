@extends('layouts.admin')
@php $edit = $votacao->exists; @endphp
@section('titulo', $edit ? 'Editar votação' : 'Criar votação')

@section('conteudo')
<h1 class="text-2xl font-bold">{{ $edit ? 'Editar votação' : 'Criar votação' }}</h1>

@if ($errors->any())
    <div class="alert alert-erro mt-4" role="alert">Corrija os campos destacados e tente novamente.</div>
@endif

<form method="POST" action="{{ $edit ? route('admin.votacoes.update', $votacao) : route('admin.votacoes.store') }}" class="mt-5 space-y-6"
      x-data="votacaoForm({ perguntas: @js($perguntasIniciais), tipos: @js($tipos) })">
    @csrf
    @if ($edit) @method('PUT') @endif

    @unless ($estrutural)
        <p class="alert alert-ok">Esta votação já está aberta: somente o encerramento e o limite de participantes podem ser ajustados.</p>
    @endunless

    @if ($estrutural)
    <section class="card space-y-4">
        <h2 class="text-lg font-semibold">Informações</h2>
        <div>
            <label class="label" for="titulo">Título *</label>
            <input class="input" id="titulo" name="titulo" value="{{ old('titulo', $votacao->titulo) }}" required maxlength="200">
            @error('titulo')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="descricao">Descrição</label>
            <textarea class="input" id="descricao" name="descricao" rows="3" maxlength="5000">{{ old('descricao', $votacao->descricao) }}</textarea>
            @error('descricao')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="imagem">Imagem (URL https, opcional)</label>
            <input class="input" id="imagem" name="imagem" type="url" value="{{ old('imagem', $votacao->imagem) }}" placeholder="https://…">
            @error('imagem')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </section>
    @endif

    <section class="card grid gap-4 sm:grid-cols-2">
        <h2 class="text-lg font-semibold sm:col-span-2">Período e limites</h2>
        <div>
            <label class="label" for="inicio_em">Início</label>
            <input class="input" id="inicio_em" type="datetime-local" name="inicio_em" value="{{ old('inicio_em', optional($votacao->inicio_em)->format('Y-m-d\TH:i')) }}" @disabled(! $estrutural)>
            @error('inicio_em')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="fim_em">Encerramento</label>
            <input class="input" id="fim_em" type="datetime-local" name="fim_em" value="{{ old('fim_em', optional($votacao->fim_em)->format('Y-m-d\TH:i')) }}">
            @error('fim_em')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="limite">Limite de participantes (opcional)</label>
            <input class="input" id="limite" type="number" min="1" name="limite_participantes" value="{{ old('limite_participantes', $votacao->limite_participantes) }}">
            @error('limite_participantes')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    @if ($estrutural)
    <section class="card space-y-4">
        <h2 class="text-lg font-semibold">Privacidade e regras</h2>
        <fieldset>
            <legend class="label">Privacidade do voto</legend>
            <div class="space-y-2">
                @foreach ($privacidades as $p)
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                        <input type="radio" class="mt-1 size-4" name="privacidade" value="{{ $p->value }}" @checked(old('privacidade', $votacao->privacidade->value) === $p->value)>
                        <span><span class="font-medium">{{ $p->rotulo() }}</span><br><span class="text-sm text-slate-600">{{ $p->descricao() }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('privacidade')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
        <label class="flex items-center gap-3">
            <input type="checkbox" class="size-5" name="permite_alterar_resposta" value="1" @checked(old('permite_alterar_resposta', $votacao->permite_alterar_resposta))>
            <span>Permitir alterar respostas antes da confirmação final</span>
        </label>
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Perguntas</h2>
            <button type="button" class="btn btn-secondary btn-sm" @click="novaPergunta()">+ Adicionar pergunta</button>
        </div>
        @error('perguntas')<p class="field-error">{{ $message }}</p>@enderror

        <template x-for="(p, i) in perguntas" :key="i">
            <div class="card space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold">Pergunta <span x-text="i + 1"></span></p>
                    <div class="flex gap-1">
                        <button type="button" class="btn btn-secondary btn-sm" @click="mover(i, -1)" :disabled="i === 0" aria-label="Mover para cima">↑</button>
                        <button type="button" class="btn btn-secondary btn-sm" @click="mover(i, 1)" :disabled="i === perguntas.length - 1" aria-label="Mover para baixo">↓</button>
                        <button type="button" class="btn btn-secondary btn-sm text-rose-700" @click="remover(i)">Remover</button>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="label" :for="'pt' + i">Enunciado *</label>
                        <input class="input" :id="'pt' + i" :name="'perguntas[' + i + '][titulo]'" x-model="p.titulo" required maxlength="300">
                    </div>
                    <div>
                        <label class="label" :for="'ptipo' + i">Tipo</label>
                        <select class="input" :id="'ptipo' + i" :name="'perguntas[' + i + '][tipo]'" x-model="p.tipo">
                            <template x-for="(t, chave) in tipos" :key="chave"><option :value="chave" x-text="t.rotulo" :selected="chave === p.tipo"></option></template>
                        </select>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" :name="'perguntas[' + i + '][obrigatoria]'" value="0">
                    <input type="checkbox" class="size-4" :name="'perguntas[' + i + '][obrigatoria]'" value="1" x-model="p.obrigatoria"> Resposta obrigatória
                </label>
                <div x-show="!fixa(p)" class="space-y-2">
                    <p class="label">Alternativas</p>
                    <template x-for="(alt, j) in p.alternativas" :key="j">
                        <div class="flex gap-2">
                            <input class="input" :name="'perguntas[' + i + '][alternativas][]'" x-model="p.alternativas[j]" :aria-label="'Alternativa ' + (j + 1)" maxlength="300">
                            <button type="button" class="btn btn-secondary btn-sm" @click="remAlt(p, j)" :disabled="p.alternativas.length <= 2" aria-label="Remover alternativa">✕</button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-secondary btn-sm" @click="addAlt(p)">+ Alternativa</button>
                </div>
                <p x-show="fixa(p)" class="text-sm text-slate-600">As alternativas deste tipo são geradas automaticamente (Sim/Não ou 1 a 5).</p>
            </div>
        </template>
        @if ($errors->has('perguntas.*') || collect($errors->keys())->contains(fn ($k) => strpos($k, 'perguntas.') === 0))
            <ul class="field-error list-disc pl-5">@foreach (collect($errors->getMessages())->filter(fn ($m, $k) => strpos($k, 'perguntas.') === 0)->flatten() as $m)<li>{{ $m }}</li>@endforeach</ul>
        @endif
    </section>
    @endif

    <div class="flex gap-3">
        <button class="btn btn-primary">{{ $edit ? 'Salvar alterações' : 'Criar rascunho' }}</button>
        <a href="{{ $edit ? route('admin.votacoes.show', $votacao) : route('admin.votacoes.index') }}" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
@endsection
