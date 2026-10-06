<?php

namespace Tests\Feature;

use App\Enums\StatusVotacao;
use App\Models\Auditoria;
use App\Models\User;
use App\Models\Votacao;
use App\Services\AuditoriaService;

class AdminVotacaoTest extends VotacaoTestCase
{
    private function payload(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Eleição 2026',
            'descricao' => 'Descrição',
            'privacidade' => 'anonima',
            'permite_alterar_resposta' => '1',
            'inicio_em' => now()->addDay()->format('Y-m-d\TH:i'),
            'fim_em' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'perguntas' => [
                ['tipo' => 'escolha_unica', 'titulo' => 'Quem?', 'obrigatoria' => '1', 'alternativas' => ['A', 'B', '']],
                ['tipo' => 'sim_nao', 'titulo' => 'Concorda?', 'obrigatoria' => '0'],
                ['tipo' => 'escala', 'titulo' => 'Nota?', 'obrigatoria' => '1'],
                ['tipo' => 'multipla_escolha', 'titulo' => 'Quais?', 'obrigatoria' => '0', 'alternativas' => ['X', 'Y', 'Z']],
            ],
        ], $extra);
    }

    public function test_participante_nao_acessa_o_painel(): void
    {
        $this->actingAs($this->participante())->get('/admin')->assertForbidden();
        $this->actingAs($this->participante())->get('/admin/votacoes')->assertForbidden();
        $this->actingAs($this->participante())->post('/admin/votacoes', $this->payload())->assertForbidden();
    }

    public function test_visitante_vai_para_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_admin_ve_dashboard_e_menu(): void
    {
        $this->votacaoAberta();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()->assertSee('Votações criadas')->assertSee('Auditoria')->assertSee('Usuários');
    }

    public function test_admin_cria_votacao_com_todos_os_tipos(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload())->assertRedirect();

        $v = Votacao::firstOrFail();
        $this->assertSame(StatusVotacao::Rascunho(), $v->status);
        $this->assertSame('anonima', $v->privacidade->value);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $v->public_id);
        $this->assertCount(4, $v->perguntas);
        $this->assertSame(['A', 'B'], $v->perguntas[0]->alternativas->pluck('texto')->all(), 'alternativa vazia é descartada');
        $this->assertSame(['Sim', 'Não'], $v->perguntas[1]->alternativas->pluck('texto')->all());
        $this->assertSame(['1', '2', '3', '4', '5'], $v->perguntas[2]->alternativas->pluck('texto')->all());
        $this->assertSame([1, 2, 3, 4], $v->perguntas->pluck('ordem')->all());
        $this->assertDatabaseHas('auditorias', ['acao' => 'CRIAR_VOTACAO', 'usuario_id' => $admin->id, 'votacao_id' => $v->id]);
    }

    public function test_validacoes_da_criacao(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload(['titulo' => '', 'perguntas' => []]))->assertSessionHasErrors(['titulo', 'perguntas']);
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload(['perguntas' => [['tipo' => 'escolha_unica', 'titulo' => 'X', 'alternativas' => ['só uma']]]]))->assertSessionHasErrors('perguntas.0.alternativas');
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload(['fim_em' => now()->subDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('fim_em');
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload(['imagem' => 'javascript:alert(1)']))->assertSessionHasErrors('imagem');
        $this->actingAs($admin)->post('/admin/votacoes', $this->payload(['privacidade' => 'xyz']))->assertSessionHasErrors('privacidade');
        $this->assertSame(0, Votacao::count());
    }

    public function test_ciclo_de_vida_rascunho_agendada_aberta_encerrada(): void
    {
        $admin = User::factory()->admin()->create();
        $v = Votacao::factory()->comPerguntas()->create(['inicio_em' => now()->addDay(), 'fim_em' => now()->addDays(2)]);

        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'AGENDADA'])->assertSessionHas('ok');
        $this->assertSame(StatusVotacao::Agendada(), $v->fresh()->status);

        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'ABERTA'])->assertSessionHas('ok');
        $this->assertSame(StatusVotacao::Aberta(), $v->fresh()->status);
        $this->assertTrue($v->fresh()->inicio_em->lte(now()), 'abertura manual antecipa o início');

        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'ENCERRADA'])->assertSessionHas('ok');
        $this->assertSame(StatusVotacao::Encerrada(), $v->fresh()->status);

        foreach (['ABRIR_VOTACAO', 'ENCERRAR_VOTACAO'] as $acao) {
            $this->assertDatabaseHas('auditorias', ['acao' => $acao, 'votacao_id' => $v->id]);
        }
    }

    public function test_transicao_invalida_e_recusada(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta();

        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'RASCUNHO'])->assertSessionHas('erro');
        $this->assertSame(StatusVotacao::Aberta(), $v->fresh()->status);
    }

    public function test_nao_abre_votacao_sem_perguntas(): void
    {
        $admin = User::factory()->admin()->create();
        $v = Votacao::factory()->create();
        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'ABERTA'])->assertSessionHas('erro');
        $this->assertSame(StatusVotacao::Rascunho(), $v->fresh()->status);
    }

    public function test_agendar_exige_data_de_inicio(): void
    {
        $admin = User::factory()->admin()->create();
        $v = Votacao::factory()->comPerguntas()->create();
        $this->actingAs($admin)->post("/admin/votacoes/{$v->public_id}/status", ['destino' => 'AGENDADA'])->assertSessionHas('erro');
    }

    public function test_cancelada_nao_reabre_e_encerrada_so_admin_reabre(): void
    {
        $admin = User::factory()->admin()->create();
        $op = User::factory()->operador()->create();

        $c = Votacao::factory()->comPerguntas()->create(['status' => StatusVotacao::Cancelada(), 'created_by' => $op->id]);
        $this->actingAs($admin)->post("/admin/votacoes/{$c->public_id}/status", ['destino' => 'ABERTA'])->assertSessionHas('erro');

        $e = Votacao::factory()->comPerguntas()->create(['status' => StatusVotacao::Encerrada(), 'created_by' => $op->id, 'fim_em' => now()->addDay()]);
        $this->actingAs($op)->post("/admin/votacoes/{$e->public_id}/status", ['destino' => 'ABERTA'])->assertForbidden();
        $this->actingAs($admin)->post("/admin/votacoes/{$e->public_id}/status", ['destino' => 'ABERTA'])->assertSessionHas('ok');
    }

    public function test_operador_so_ve_e_conduz_as_proprias_votacoes_e_nao_cancela(): void
    {
        $op = User::factory()->operador()->create();
        $minha = Votacao::factory()->comPerguntas()->create(['created_by' => $op->id]);
        $alheia = Votacao::factory()->comPerguntas()->create();

        $this->actingAs($op)->get('/admin/votacoes')->assertOk()->assertSee($minha->titulo)->assertDontSee($alheia->titulo);
        $this->actingAs($op)->get("/admin/votacoes/{$alheia->public_id}")->assertForbidden();
        $this->actingAs($op)->get("/admin/resultados/{$alheia->public_id}")->assertForbidden();
        $this->actingAs($op)->post("/admin/votacoes/{$minha->public_id}/status", ['destino' => 'CANCELADA'])->assertForbidden();
        $this->actingAs($op)->post("/admin/votacoes/{$minha->public_id}/status", ['destino' => 'ABERTA'])->assertSessionHas('ok');
        $this->actingAs($op)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($op)->get('/admin/auditoria')->assertForbidden();
    }

    public function test_edicao_estrutural_bloqueada_com_votacao_aberta_mas_fim_pode_ser_ajustado(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta();
        $perguntasAntes = $v->perguntas()->pluck('id')->all();
        $novoFim = now()->addDays(5)->startOfMinute();

        $this->actingAs($admin)->put("/admin/votacoes/{$v->public_id}", ['titulo' => 'HACK', 'fim_em' => $novoFim->format('Y-m-d\TH:i'), 'perguntas' => [['tipo' => 'sim_nao', 'titulo' => 'x']]])->assertRedirect();

        $v->refresh();
        $this->assertNotSame('HACK', $v->titulo);
        $this->assertSame($perguntasAntes, $v->perguntas()->pluck('id')->all(), 'perguntas intactas');
        $this->assertTrue($v->fim_em->equalTo($novoFim));
    }

    public function test_limite_nao_pode_ficar_abaixo_dos_participantes_atuais(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta(['limite_participantes' => 10, 'participantes_count' => 5]);
        $this->actingAs($admin)->put("/admin/votacoes/{$v->public_id}", ['limite_participantes' => 3, 'fim_em' => $v->fim_em->format('Y-m-d\TH:i')])->assertSessionHas('erro');
        $this->assertSame(10, $v->fresh()->limite_participantes);
    }

    public function test_edicao_de_rascunho_recria_perguntas(): void
    {
        $admin = User::factory()->admin()->create();
        $v = Votacao::factory()->comPerguntas()->create();
        $this->actingAs($admin)->put("/admin/votacoes/{$v->public_id}", $this->payload(['titulo' => 'Novo título']))->assertRedirect();
        $v->refresh();
        $this->assertSame('Novo título', $v->titulo);
        $this->assertCount(4, $v->perguntas);
        $this->assertDatabaseHas('auditorias', ['acao' => 'EDITAR_VOTACAO', 'votacao_id' => $v->id]);
    }

    public function test_qr_code_aponta_para_url_publica_e_pode_ser_baixado(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta();

        $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}")->assertOk()->assertSee('<svg', false)->assertSee(url("/v/{$v->public_id}"));
        $r = $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}/qrcode/download");
        $r->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $r->getContent());
        $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}/imprimir")->assertOk()->assertSee($v->titulo);
    }

    public function test_exportacao_somente_admin_e_gera_auditoria_e_neutraliza_csv_injection(): void
    {
        $admin = User::factory()->admin()->create();
        $op = User::factory()->operador()->create();
        $v = Votacao::factory()->comPerguntas()->aberta()->create(['created_by' => $op->id]);
        $v->perguntas[0]->update(['titulo' => '=HYPERLINK("http://x")']);

        $this->actingAs($op)->get("/admin/resultados/{$v->public_id}/exportar")->assertForbidden();
        $r = $this->actingAs($admin)->get("/admin/resultados/{$v->public_id}/exportar")->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $r->streamedContent());
        $this->assertDatabaseHas('auditorias', ['acao' => 'EXPORTAR_RESULTADOS', 'votacao_id' => $v->id]);
    }

    public function test_gestao_de_usuarios_pelo_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/usuarios', ['email' => 'Novo@Exemplo.com', 'role' => 'operador'])->assertSessionHas('ok');
        $novo = User::where('email', 'novo@exemplo.com')->firstOrFail();
        $this->assertNull($novo->google_id);

        $this->actingAs($admin)->put("/admin/usuarios/{$novo->id}", ['role' => 'admin'])->assertSessionHas('ok');
        $this->assertTrue($novo->fresh()->isAdmin());
        $this->actingAs($admin)->put("/admin/usuarios/{$admin->id}", ['role' => 'participante'])->assertForbidden(); // não rebaixa a si mesmo
        $this->actingAs($admin)->post('/admin/usuarios', ['email' => 'x', 'role' => 'root'])->assertSessionHasErrors(['email', 'role']);
        $this->assertGreaterThanOrEqual(2, Auditoria::where('acao', 'ALTERAR_CONFIGURACAO')->count());
    }

    public function test_auditoria_nao_grava_segredos(): void
    {
        app(AuditoriaService::class)->registrar('TESTE', null, null, ['token' => 'abc', 'ok' => 1, 'aninhado' => ['password' => 'x', 'y' => 2]]);
        $dados = Auditoria::where('acao', 'TESTE')->firstOrFail()->dados;
        $this->assertSame(['ok' => 1, 'aninhado' => ['y' => 2]], $dados);
    }

    public function test_telas_do_painel_renderizam(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta();
        foreach (['/admin', '/admin/votacoes', '/admin/votacoes/criar', "/admin/votacoes/{$v->public_id}", "/admin/votacoes/{$v->public_id}/editar", "/admin/votacoes/{$v->public_id}/qrcode", '/admin/resultados', "/admin/resultados/{$v->public_id}", "/admin/resultados/{$v->public_id}/dados", '/admin/usuarios', '/admin/auditoria', '/admin/configuracoes', '/admin/votacoes?status=aberta'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
