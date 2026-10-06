<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\User;
use App\Models\Votacao;

class ApiV1Test extends VotacaoTestCase
{
    public function test_detalhe_publico_da_votacao(): void
    {
        $v = $this->votacaoAberta();
        $this->getJson("/api/v1/votacoes/{$v->public_id}")->assertOk()->assertJsonPath('data.aceita_votos', true)->assertJsonPath('data.status', 'ABERTA');
        $this->getJson('/api/v1/votacoes/NAOEXISTE')->assertNotFound();
        $r = Votacao::factory()->create();
        $this->getJson("/api/v1/votacoes/{$r->public_id}")->assertNotFound();
    }

    public function test_listagem_exige_autenticacao_e_painel(): void
    {
        $this->getJson('/api/v1/votacoes')->assertUnauthorized();
        $this->actingAs($this->participante())->getJson('/api/v1/votacoes')->assertForbidden();
        $this->votacaoAberta();
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/votacoes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_fluxo_participar_responder_finalizar(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();

        $this->postJson("/api/v1/votacoes/{$v->public_id}/participar")->assertUnauthorized();
        $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/participar")->assertOk()->assertJsonCount(3, 'perguntas');

        $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/responder", ['respostas' => []])->assertStatus(422);
        $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/responder", ['respostas' => $this->respostasValidas($v)])->assertOk()->assertJson(['valido' => true]);
        $this->assertSame(0, Participacao::count(), 'responder não grava');

        $r = $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/finalizar", ['respostas' => $this->respostasValidas($v)])->assertCreated();
        $this->assertSame(Participacao::first()->protocolo, $r->json('protocolo'));

        $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/finalizar", ['respostas' => $this->respostasValidas($v)])->assertStatus(409);
        $this->actingAs($u)->postJson("/api/v1/votacoes/{$v->public_id}/participar")->assertStatus(409);
    }

    public function test_finalizar_em_votacao_fechada_retorna_403(): void
    {
        $v = $this->votacaoAberta();
        $v->update(['status' => 'ENCERRADA']);
        $this->actingAs($this->participante())->postJson("/api/v1/votacoes/{$v->public_id}/finalizar", ['respostas' => $this->respostasValidas($v)])->assertForbidden();
    }

    public function test_resultados_via_api_somente_painel(): void
    {
        $v = $this->votacaoAberta();
        $this->actingAs(User::factory()->admin()->create())->getJson("/api/v1/votacoes/{$v->public_id}/resultados")->assertOk()->assertJsonStructure(['participantes', 'perguntas']);
    }
}
