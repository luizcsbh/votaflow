<?php

namespace Tests\Feature;

use App\Enums\StatusVotacao;
use App\Events\ParticipacaoRegistrada;
use App\Models\Votacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class InfraestruturaTest extends VotacaoTestCase
{
    public function test_health_ok(): void
    {
        $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'cache' => 'ok']]);
    }

    public function test_health_retorna_503_quando_banco_cai(): void
    {
        DB::partialMock()->shouldReceive('select')->andThrow(new \PDOException('conexão recusada'));
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('status', 'degradado')->assertJsonPath('checks.database', 'falha');
    }

    public function test_paginas_de_erro_consistentes_e_sem_detalhe_tecnico(): void
    {
        $this->get('/rota-que-nao-existe')->assertNotFound()->assertSee('Página não encontrada')->assertSee('Voltar ao início');
        $this->actingAs($this->participante())->get('/admin')->assertForbidden()->assertSee('Acesso negado');
        foreach ([403, 404, 419, 429, 500, 503] as $codigo) {
            $this->assertStringContainsString((string) $codigo, view("errors.$codigo")->render());
        }
    }

    public function test_agendador_abre_e_encerra_por_data(): void
    {
        $agendada = Votacao::factory()->comPerguntas()->create(['status' => StatusVotacao::Agendada(), 'inicio_em' => now()->subMinute(), 'fim_em' => now()->addHour()]);
        $futura = Votacao::factory()->comPerguntas()->create(['status' => StatusVotacao::Agendada(), 'inicio_em' => now()->addHour()]);
        $vencida = Votacao::factory()->comPerguntas()->aberta()->create(['fim_em' => now()->subMinute()]);
        $rascunho = Votacao::factory()->comPerguntas()->create(['inicio_em' => now()->subDay()]);

        $this->artisan('votaflow:sincronizar-status')->assertSuccessful();

        $this->assertSame(StatusVotacao::Aberta(), $agendada->fresh()->status);
        $this->assertSame(StatusVotacao::Agendada(), $futura->fresh()->status);
        $this->assertSame(StatusVotacao::Encerrada(), $vencida->fresh()->status);
        $this->assertSame(StatusVotacao::Rascunho(), $rascunho->fresh()->status);
    }

    public function test_evento_de_participacao_e_disparado_apos_o_voto_e_nao_bloqueia_o_registro(): void
    {
        Event::fake([ParticipacaoRegistrada::class]);
        $v = $this->votacaoAberta();
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        Event::assertDispatched(ParticipacaoRegistrada::class, fn ($e) => $e->publicId === $v->public_id);
    }

    public function test_evento_transmite_no_canal_da_votacao_com_total(): void
    {
        $v = $this->votacaoAberta();
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        $e = new ParticipacaoRegistrada($v->public_id, $v->id);
        $this->assertSame('votacao.'.$v->public_id, $e->broadcastOn()[0]->name);
        $this->assertSame(['participantes' => 1], $e->broadcastWith());
    }

    public function test_home_e_pagina_de_login_renderizam(): void
    {
        $this->get('/')->assertOk()->assertSee('QR Code');
        $this->get('/login')->assertOk()->assertSee('Continuar com Google');
    }
}
