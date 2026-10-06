<?php

namespace Tests\Feature;

use App\Models\Participacao;
use App\Models\User;
use App\Models\Votacao;

class SegurancaTest extends VotacaoTestCase
{
    public function test_post_sem_csrf_e_rejeitado_419(): void
    {
        $v = $this->votacaoAberta();
        $this->app['env'] = 'local'; // em 'testing' o Laravel desliga a verificação CSRF
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)])->assertStatus(419);
        $this->assertSame(0, Participacao::count());
    }

    public function test_post_de_logout_e_admin_tambem_exigem_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs(User::factory()->admin()->create())->post('/admin/votacoes', [])->assertStatus(419);
    }

    public function test_xss_titulo_e_descricao_sao_escapados(): void
    {
        $v = $this->votacaoAberta(['titulo' => '<script>alert(1)</script>', 'descricao' => '<img src=x onerror=alert(2)>']);
        $r = $this->get("/v/{$v->public_id}")->assertOk();
        $r->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror', false);
        $r->assertSee('&lt;script&gt;', false);
    }

    public function test_xss_nas_perguntas_do_wizard_e_no_painel(): void
    {
        $v = $this->votacaoAberta();
        $v->perguntas[0]->update(['titulo' => '</script><script>alert(3)</script>']);
        $v->touch();
        $r = $this->actingAs($this->participante())->get("/v/{$v->public_id}/votar")->assertOk();
        $this->assertStringNotContainsString('</script><script>alert(3)', $r->getContent());

        $r2 = $this->actingAs(User::factory()->admin()->create())->get("/admin/votacoes/{$v->public_id}")->assertOk();
        $this->assertStringNotContainsString('<script>alert(3)', $r2->getContent());
    }

    public function test_sql_injection_no_identificador_publico(): void
    {
        $this->votacaoAberta();
        $this->get('/v/'.urlencode("' OR '1'='1"))->assertNotFound();
        $this->get('/v/'.urlencode("x'; DROP TABLE votacoes;--"))->assertNotFound();
        $this->assertSame(1, Votacao::count());
    }

    public function test_sql_injection_na_busca_de_usuarios_e_filtros(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/usuarios?q='.urlencode("' OR 1=1 --"))->assertOk();
        $this->actingAs($admin)->get('/admin/votacoes?status='.urlencode("ABERTA' OR '1'='1"))->assertOk();
        $this->actingAs($admin)->get('/admin/auditoria?acao='.urlencode("x' OR 1=1"))->assertOk();
    }

    public function test_manipulacao_de_ids_nao_da_acesso_a_comprovante_ou_resultados(): void
    {
        $v = $this->votacaoAberta();
        $outro = $this->participante();
        $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        $this->actingAs($outro)->get("/v/{$v->public_id}/comprovante")->assertNotFound();
        $this->actingAs($outro)->get("/admin/resultados/{$v->public_id}")->assertForbidden();
        $this->actingAs($outro)->get("/api/v1/votacoes/{$v->public_id}/resultados")->assertForbidden();
    }

    public function test_rate_limit_do_voto_por_usuario(): void
    {
        $v = $this->votacaoAberta();
        $u = $this->participante();
        $status = [];
        foreach (range(1, 12) as $i) {
            $status[] = $this->actingAs($u)->post("/v/{$v->public_id}/votar", ['respostas' => []])->getStatusCode();
        }
        $this->assertContains(429, $status);
        $this->assertSame(429, end($status));
        $this->assertSame(302, $status[0]);
    }

    public function test_rate_limit_de_usuarios_diferentes_no_mesmo_ip_nao_se_afetam(): void
    {
        $v = $this->votacaoAberta();
        foreach (range(1, 15) as $i) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)])->assertStatus(302);
        }
        $this->assertSame(15, Participacao::count());
    }

    public function test_cabecalhos_de_seguranca(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy');
    }

    public function test_csp_e_hsts_em_producao(): void
    {
        $this->app['env'] = 'production';
        $r = $this->get('/');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertNotNull($r->headers->get('Strict-Transport-Security'));
    }

    public function test_cookies_de_sessao_seguros_por_padrao(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) env('APP_ENV') !== 'production' || config('session.secure'));
    }

    public function test_modelo_nao_expoe_google_id_em_serializacao(): void
    {
        $u = User::factory()->create(['google_id' => 'segredo123']);
        $this->assertArrayNotHasKey('google_id', $u->toArray());
    }

    public function test_api_nao_expoe_id_sequencial(): void
    {
        $v = $this->votacaoAberta();
        $json = $this->get("/api/v1/votacoes/{$v->public_id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('id', $json);
        $this->assertSame($v->public_id, $json['public_id']);
    }
}
