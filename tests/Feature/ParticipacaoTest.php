<?php

namespace Tests\Feature;

use App\Enums\StatusVotacao;
use App\Models\Participacao;
use App\Models\Resposta;
use App\Models\Votacao;
use App\Services\VotoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class ParticipacaoTest extends VotacaoTestCase
{
    public function test_qr_code_mostra_pagina_publica_com_login_google(): void
    {
        $v = $this->votacaoAberta(['titulo' => 'Votação Escolar 2026']);

        $this->get("/v/{$v->public_id}")
            ->assertOk()
            ->assertSee('Votação Escolar 2026')
            ->assertSee('Continuar com Google')
            ->assertSee(urlencode('/v/'.$v->public_id.'/votar'), false);
    }

    public function test_visitante_nao_autenticado_e_redirecionado_ao_login_para_votar(): void
    {
        $v = $this->votacaoAberta();
        $this->get("/v/{$v->public_id}/votar")->assertRedirect(route('login'));
        $this->post("/v/{$v->public_id}/votar", [])->assertRedirect(route('login'));
    }

    public function test_rascunho_nao_e_publico(): void
    {
        $v = Votacao::factory()->comPerguntas()->create();
        $this->get("/v/{$v->public_id}")->assertNotFound();
    }

    public function test_id_sequencial_nao_funciona_na_url_publica(): void
    {
        $v = $this->votacaoAberta();
        $this->get('/v/'.$v->id)->assertNotFound();
    }

    public function test_fluxo_completo_registra_participacao_respostas_e_protocolo(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();
        $resp = $this->respostasValidas($v);

        $this->actingAs($u)->get("/v/{$v->public_id}/votar")->assertOk()->assertSee('Começar')->assertSee('Olá, '.$u->primeiroNome());

        $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => $resp])
            ->assertRedirect(route('votacao.comprovante', $v));

        $part = Participacao::where('votacao_id', $v->id)->where('usuario_id', $u->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $part->protocolo);
        $this->assertNotNull($part->finalizado_em);
        $this->assertSame(4, Resposta::where('participacao_id', $part->id)->count()); // 1 + 2 + 1

        $this->actingAs($u)->get("/v/{$v->public_id}/comprovante")->assertOk()->assertSee($part->protocolo)->assertSee('Voto registrado!');
    }

    public function test_segundo_voto_do_mesmo_usuario_e_bloqueado(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();
        $resp = $this->respostasValidas($v);

        $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => $resp]);
        $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v, 1)])
            ->assertRedirect(route('votacao.comprovante', $v));

        $this->assertSame(1, Participacao::count());
        $this->assertSame(4, Resposta::count(), 'respostas do segundo envio não podem ser gravadas');
        $this->actingAs($u)->get("/v/{$v->public_id}/votar")->assertRedirect(route('votacao.comprovante', $v));
    }

    public function test_constraint_unica_do_banco_impede_duplicidade_mesmo_sem_validacao_da_aplicacao(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();
        Participacao::create(['votacao_id' => $v->id, 'usuario_id' => $u->id, 'protocolo' => 'AAAA-BBBB']);

        $this->expectException(QueryException::class);
        Participacao::create(['votacao_id' => $v->id, 'usuario_id' => $u->id, 'protocolo' => 'CCCC-DDDD']);
    }

    public function test_nao_vota_quando_status_nao_e_aberta(): void
    {
        foreach ([StatusVotacao::Encerrada(), StatusVotacao::Cancelada(), StatusVotacao::Agendada()] as $status) {
            $v = Votacao::factory()->comPerguntas()->aberta()->create(['status' => $status]);
            $u = $this->participante();
            $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)])->assertRedirect(route('votacao.mostrar', $v));
            $this->assertSame(0, Participacao::where('votacao_id', $v->id)->count(), "status {$status->value}");
        }
    }

    public function test_nao_vota_fora_da_janela_de_tempo_mesmo_com_status_aberta(): void
    {
        $v = $this->votacaoAberta(['fim_em' => now()->subMinute()]);
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        $this->assertSame(0, Participacao::count());
    }

    public function test_pergunta_obrigatoria_sem_resposta_nao_registra_nada(): void
    {
        $v = $this->votacaoAberta();
        $resp = $this->respostasValidas($v);
        unset($resp[array_key_first($resp)]);

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $resp])->assertSessionHasErrors();
        $this->assertSame(0, Participacao::count());
        $this->assertSame(0, Resposta::count());
    }

    public function test_alternativa_de_outra_pergunta_ou_votacao_e_rejeitada(): void
    {
        $v = $this->votacaoAberta();
        $outra = $this->votacaoAberta();
        $resp = $this->respostasValidas($v);
        $outraAlt = $outra->load('perguntas.alternativas')->perguntas[0]->alternativas[0]->id;
        $resp[array_key_first($resp)] = [$outraAlt];

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $resp])->assertSessionHasErrors();
        $this->assertSame(0, Participacao::count());
    }

    public function test_pergunta_inexistente_ou_de_outra_votacao_e_rejeitada(): void
    {
        $v = $this->votacaoAberta();
        $outra = $this->votacaoAberta()->load('perguntas.alternativas');
        $resp = $this->respostasValidas($v) + [$outra->perguntas[0]->id => [$outra->perguntas[0]->alternativas[0]->id]];

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $resp])->assertSessionHasErrors();
        $this->assertSame(0, Participacao::count());
    }

    public function test_escolha_unica_nao_aceita_varias_opcoes(): void
    {
        $v = $this->votacaoAberta();
        $resp = $this->respostasValidas($v);
        $p1 = $v->perguntas[0];
        $resp[$p1->id] = [$p1->alternativas[0]->id, $p1->alternativas[1]->id];

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $resp])->assertSessionHasErrors();
        $this->assertSame(0, Participacao::count());
    }

    public function test_conteudo_nao_numerico_e_rejeitado(): void
    {
        $v = $this->votacaoAberta();
        $resp = $this->respostasValidas($v);
        $resp[$v->perguntas[0]->id] = ['1 OR 1=1'];

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $resp])->assertSessionHasErrors();
        $this->assertSame(0, Participacao::count());
    }

    public function test_votacao_anonima_nao_vincula_resposta_a_participacao(): void
    {
        $v = Votacao::factory()->comPerguntas()->aberta()->anonima()->create();
        $u = $this->participante();
        $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);

        $this->assertSame(1, Participacao::count(), 'a participação existe (anti-duplicidade)');
        $this->assertSame(4, Resposta::count());
        $this->assertSame(0, Resposta::whereNotNull('participacao_id')->count(), 'nenhuma resposta pode apontar para a pessoa');
    }

    public function test_votacao_identificada_vincula_resposta(): void
    {
        $v = $this->votacaoAberta();
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        $this->assertSame(4, Resposta::whereNotNull('participacao_id')->count());
    }

    public function test_limite_de_participantes_e_respeitado(): void
    {
        $v = $this->votacaoAberta(['limite_participantes' => 2]);
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        }
        $this->assertSame(2, Participacao::count());
        $this->assertSame(2, $v->fresh()->participantes_count);
        $this->assertSame(8, Resposta::count(), 'o 3º voto foi desfeito por inteiro (rollback)');
    }

    public function test_comprovante_de_outra_pessoa_nao_e_acessivel(): void
    {
        $v = $this->votacaoAberta();
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        $this->actingAs($this->participante())->get("/v/{$v->public_id}/comprovante")->assertNotFound();
    }

    public function test_rollback_total_se_ocorrer_erro_no_meio_do_registro(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();
        // Sabota a inserção das respostas: tabela removida => exceção DEPOIS de criar a participação.
        Schema::drop('respostas');

        try {
            app(VotoService::class)->registrar($v, $u, $this->respostasValidas($v));
            $this->fail('deveria lançar exceção');
        } catch (\Throwable $e) {
        }

        $this->assertSame(0, Participacao::count(), 'participação não pode ficar registrada sem as respostas');
    }

    public function test_erro_tecnico_nao_vaza_para_o_usuario(): void
    {
        $v = $this->votacaoAberta();
        Schema::drop('respostas');

        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)])
            ->assertSessionHas('erro', 'Não foi possível registrar sua participação. Verifique sua conexão e tente novamente.');
    }
}
