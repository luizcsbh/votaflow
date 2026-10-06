<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Votacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class VotacaoTestCase extends TestCase
{
    use RefreshDatabase;

    protected function votacaoAberta(array $attrs = []): Votacao
    {
        return Votacao::factory()->comPerguntas()->aberta()->create($attrs);
    }

    /** Respostas válidas para a votação padrão da factory (3 perguntas). */
    protected function respostasValidas(Votacao $v, int $opcaoUnica = 0): array
    {
        $v->load('perguntas.alternativas');
        [$p1, $p2, $p3] = $v->perguntas->all();

        return [
            $p1->id => [$p1->alternativas[$opcaoUnica]->id],
            $p2->id => [$p2->alternativas[0]->id, $p2->alternativas[2]->id],
            $p3->id => [$p3->alternativas[0]->id],
        ];
    }

    protected function participante(): User
    {
        return User::factory()->create();
    }
}
