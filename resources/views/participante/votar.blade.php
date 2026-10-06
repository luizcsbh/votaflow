@extends('layouts.participante')
@section('titulo', $votacao->titulo)

@section('conteudo')
<div x-data="wizard({ perguntas: @js($perguntas), permiteVoltar: @js($votacao->permite_alterar_resposta), minutos: {{ $minutos }} })" x-cloak>
    <form method="POST" action="{{ route('votacao.enviar', $votacao) }}" x-ref="form">
        @csrf
        <input type="hidden" name="iniciado_em" value="{{ $iniciadoEm }}">
        <template x-for="par in pares" :key="par.pid + '-' + par.id">
            <input type="hidden" :name="'respostas[' + par.pid + '][]'" :value="par.id">
        </template>
    </form>

    @if (session('erro') || $errors->any())
        <div class="alert alert-erro mb-4" role="alert">{{ session('erro') ?? 'Confira suas respostas e tente novamente.' }}</div>
    @endif

    {{-- Etapa 4: início --}}
    <section x-show="etapa === 'intro'" class="card text-center">
        <h1 class="text-2xl font-bold" x-ref="titulo" tabindex="-1">Olá, {{ auth()->user()->primeiroNome() }}!</h1>
        <p class="mt-3 text-slate-600">Você está participando de:</p>
        <p class="mt-1 text-xl font-semibold">{{ $votacao->titulo }}</p>
        <p class="mt-4 text-slate-600">Tempo estimado: <strong>{{ $minutos }} {{ $minutos === 1 ? 'minuto' : 'minutos' }}</strong></p>
        <button type="button" class="btn btn-primary mt-6 w-full text-lg" @click="iniciar()">Começar</button>
    </section>

    {{-- Etapas 5–10: perguntas --}}
    <section x-show="indice !== null" class="space-y-5">
        <div>
            <div class="flex items-baseline justify-between text-sm font-medium text-slate-600" aria-live="polite">
                <span>Pergunta <span x-text="(indice ?? 0) + 1"></span> de <span x-text="total"></span></span>
                <span><span x-text="concluido"></span>% concluído</span>
            </div>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="concluido" aria-label="Progresso da votação">
                <div class="h-full rounded-full bg-brand-600 transition-all duration-300" :style="'width:' + concluido + '%'"></div>
            </div>
        </div>

        <template x-if="atual">
            <fieldset class="card">
                <legend class="sr-only" x-text="atual.titulo"></legend>
                <h2 class="text-xl font-bold leading-snug sm:text-2xl" x-ref="titulo" tabindex="-1" x-text="atual.titulo"></h2>
                <p class="mt-1 text-sm text-slate-600" x-show="atual.descricao" x-text="atual.descricao"></p>
                <p class="mt-1 text-sm text-slate-500" x-show="atual.multipla">Você pode marcar mais de uma opção.</p>
                <p class="mt-1 text-sm text-slate-500" x-show="!atual.obrigatoria">Opcional.</p>

                <div class="mt-5 space-y-3">
                    <template x-for="a in atual.alternativas" :key="atual.id + '-' + a.id">
                        <div>
                            <input class="peer sr-only" :type="atual.multipla ? 'checkbox' : 'radio'" :name="'q' + atual.id" :id="'a' + a.id"
                                   :checked="selecionado(atual, a.id)" @change="selecionar(atual, a.id)">
                            <label :for="'a' + a.id" class="opcao">
                                <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center border-2 border-slate-400 text-xs text-white"
                                      :class="[atual.multipla ? 'rounded-md' : 'rounded-full', selecionado(atual, a.id) ? 'border-brand-600 bg-brand-600' : '']"
                                      x-text="selecionado(atual, a.id) ? '✓' : ''"></span>
                                <span x-text="a.texto"></span>
                            </label>
                        </div>
                    </template>
                </div>
                <p class="field-error" role="alert" x-show="erro" x-text="erro"></p>
            </fieldset>
        </template>

        <div class="flex gap-3">
            <button type="button" class="btn btn-secondary flex-1" x-show="permiteVoltar && indice > 0" @click="voltar()">Voltar</button>
            <button type="button" class="btn btn-primary flex-1" @click="proxima()" x-text="indice === total - 1 ? 'Revisar' : 'Próxima'"></button>
        </div>
    </section>

    {{-- Etapa 11: confirmação --}}
    <section x-show="etapa === 'revisao'" class="space-y-5">
        <div class="card">
            <h2 class="text-xl font-bold" x-ref="titulo" tabindex="-1">Confira suas respostas</h2>
            <dl class="mt-4 divide-y divide-slate-200">
                <template x-for="(p, i) in perguntas" :key="p.id">
                    <div class="py-3">
                        <dt class="text-sm text-slate-500" x-text="'Pergunta ' + (i + 1) + ' — ' + p.titulo"></dt>
                        <dd class="mt-0.5 flex items-start justify-between gap-3 text-lg font-semibold">
                            <span x-text="textos(p)"></span>
                            <button type="button" class="shrink-0 text-sm font-medium text-brand-700 underline" x-show="permiteVoltar" @click="editar(i)">Alterar</button>
                        </dd>
                    </div>
                </template>
            </dl>
            <p class="mt-4 rounded-xl bg-slate-100 p-3 text-sm text-slate-700">Depois de confirmar, suas respostas serão registradas e <strong>não poderão ser alteradas</strong>.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row-reverse">
            <button type="button" class="btn btn-primary flex-1 text-lg" :disabled="enviando" @click="enviar()">
                <span x-show="!enviando">CONFIRMAR VOTO</span><span x-show="enviando">Registrando…</span>
            </button>
            <button type="button" class="btn btn-secondary flex-1" x-show="permiteVoltar" :disabled="enviando" @click="voltar()">Voltar e corrigir</button>
        </div>
    </section>
</div>
@endsection
