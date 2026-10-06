<?php

namespace Database\Factories;

use App\Enums\PrivacidadeVotacao;
use App\Enums\StatusVotacao;
use App\Enums\TipoPergunta;
use App\Models\User;
use App\Models\Votacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Votacao> */
class VotacaoFactory extends Factory
{
    protected $model = Votacao::class;

    public function definition(): array
    {
        return [
            'titulo' => 'Votação '.$this->faker->words(2, true),
            'descricao' => $this->faker->sentence(),
            'status' => StatusVotacao::Rascunho(),
            'privacidade' => PrivacidadeVotacao::Identificada(),
            'inicio_em' => null,
            'fim_em' => null,
            'permite_alterar_resposta' => true,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function aberta(): self
    {
        return $this->state(['status' => StatusVotacao::Aberta(), 'inicio_em' => now()->subHour(), 'fim_em' => now()->addHours(3)]);
    }

    public function anonima(): self
    {
        return $this->state(['privacidade' => PrivacidadeVotacao::Anonima()]);
    }

    /** Cria uma votação com 3 perguntas padrão: escolha única, múltipla e sim/não. */
    public function comPerguntas(): self
    {
        return $this->afterCreating(function (Votacao $v) {
            $p1 = $v->perguntas()->create(['tipo' => TipoPergunta::EscolhaUnica()->value, 'titulo' => 'Candidato preferido?', 'ordem' => 1, 'obrigatoria' => true]);
            foreach (['Candidato A', 'Candidato B', 'Branco'] as $i => $t) {
                $p1->alternativas()->create(['texto' => $t, 'ordem' => $i + 1]);
            }
            $p2 = $v->perguntas()->create(['tipo' => TipoPergunta::MultiplaEscolha()->value, 'titulo' => 'Quais opções são importantes?', 'ordem' => 2, 'obrigatoria' => false]);
            foreach (['Opção A', 'Opção B', 'Opção C'] as $i => $t) {
                $p2->alternativas()->create(['texto' => $t, 'ordem' => $i + 1]);
            }
            $p3 = $v->perguntas()->create(['tipo' => TipoPergunta::SimNao()->value, 'titulo' => 'Você concorda com a proposta?', 'ordem' => 3, 'obrigatoria' => true]);
            foreach (['Sim', 'Não'] as $i => $t) {
                $p3->alternativas()->create(['texto' => $t, 'ordem' => $i + 1]);
            }
            $v->touch();
        });
    }
}
