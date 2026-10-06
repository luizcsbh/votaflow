<?php

namespace App\Http\Requests;

use App\Enums\PrivacidadeVotacao;
use App\Enums\TipoPergunta;
use App\Models\Votacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalvarVotacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $votacao = $this->route('votacao');

        return $votacao instanceof Votacao
            ? $this->user()->can('update', $votacao)
            : $this->user()->can('create', Votacao::class);
    }

    private function estrutural(): bool
    {
        $v = $this->route('votacao');

        return ! ($v instanceof Votacao) || $v->status->permiteEdicaoEstrutural();
    }

    public function rules(): array
    {
        $regras = [
            'inicio_em' => ['nullable', 'date'],
            'fim_em' => ['nullable', 'date', 'after:inicio_em'],
            'limite_participantes' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];

        if (! $this->estrutural()) {
            return $regras; // votação aberta: apenas fim/limite
        }

        return $regras + [
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'imagem' => ['nullable', 'url', 'regex:/^https:\/\//i', 'max:2048'],
            'privacidade' => ['required', Rule::in(PrivacidadeVotacao::valores())],
            'permite_alterar_resposta' => ['nullable', 'boolean'],
            'perguntas' => ['required', 'array', 'min:1', 'max:50'],
            'perguntas.*.tipo' => ['required', Rule::in(TipoPergunta::valores())],
            'perguntas.*.titulo' => ['required', 'string', 'max:300'],
            'perguntas.*.descricao' => ['nullable', 'string', 'max:1000'],
            'perguntas.*.obrigatoria' => ['nullable', 'boolean'],
            'perguntas.*.alternativas' => ['nullable', 'array', 'max:30'],
            'perguntas.*.alternativas.*' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach ((array) $this->input('perguntas', []) as $i => $p) {
                $tipo = TipoPergunta::tryFrom((string) ($p['tipo'] ?? ''));
                if ($tipo && $tipo->alternativasFixas() === null) {
                    $n = count(array_filter(array_map('trim', (array) ($p['alternativas'] ?? [])), fn ($t) => $t !== ''));
                    if ($n < 2) {
                        $v->errors()->add("perguntas.$i.alternativas", 'Informe ao menos 2 alternativas.');
                    }
                }
            }
        });
    }

    public function attributes(): array
    {
        return ['titulo' => 'título', 'descricao' => 'descrição', 'inicio_em' => 'início', 'fim_em' => 'encerramento', 'limite_participantes' => 'limite de participantes'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permite_alterar_resposta' => $this->boolean('permite_alterar_resposta'),
            'inicio_em' => $this->input('inicio_em') ?: null,
            'fim_em' => $this->input('fim_em') ?: null,
            'limite_participantes' => $this->input('limite_participantes') ?: null,
        ]);
    }
}
