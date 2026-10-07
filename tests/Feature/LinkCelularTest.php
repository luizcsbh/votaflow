<?php

namespace Tests\Feature;

use App\Models\User;

/** O link/QR Code precisa ser alcançável por um celular (não localhost, de preferência HTTPS). */
class LinkCelularTest extends VotacaoTestCase
{
    public function test_usa_o_endereco_publico_configurado_e_nao_o_host_da_requisicao(): void
    {
        config(['votaflow.public_url' => 'https://votacao.exemplo.com.br/']);
        $v = $this->votacaoAberta();

        $this->assertSame('https://votacao.exemplo.com.br/v/'.$v->public_id, $v->urlPublica());
        $this->assertTrue($v->linkAcessivelPorCelular());
        $this->assertTrue($v->linkUsaHttps());
    }

    public function test_sem_endereco_publico_usa_a_url_da_requisicao(): void
    {
        config(['votaflow.public_url' => null]);
        $v = $this->votacaoAberta();

        $this->assertSame(route('votacao.mostrar', $v->public_id), $v->urlPublica());
    }

    /** @dataProvider enderecosInacessiveis */
    public function test_enderecos_que_celular_nao_alcanca(string $base): void
    {
        config(['votaflow.public_url' => $base]);
        $this->assertFalse($this->votacaoAberta()->linkAcessivelPorCelular(), $base);
    }

    public function enderecosInacessiveis(): array
    {
        return [
            ['http://localhost:8000'], ['http://127.0.0.1:8000'], ['http://[::1]:8000'],
            ['http://192.168.0.15:8000'], ['http://10.0.0.5'], ['http://172.20.1.1'],
            ['http://votaflow.test'], ['http://meupc.local'],
        ];
    }

    public function test_dominio_publico_e_ip_publico_sao_acessiveis(): void
    {
        foreach (['https://abc.trycloudflare.com', 'https://votacao.empresa.com.br', 'https://203.0.113.10'] as $base) {
            config(['votaflow.public_url' => $base]);
            $this->assertTrue($this->votacaoAberta()->linkAcessivelPorCelular(), $base);
        }
    }

    public function test_painel_avisa_quando_o_link_e_localhost(): void
    {
        config(['votaflow.public_url' => 'http://localhost:8000']);
        $v = $this->votacaoAberta();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}")->assertOk()->assertSee('Celulares não vão abrir');
        $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}/qrcode")->assertOk()->assertSee('Celulares não vão abrir');
        $this->actingAs($admin)->get("/admin/votacoes/{$v->public_id}/imprimir")->assertOk()->assertSee('Celulares não vão abrir');
    }

    public function test_painel_avisa_quando_nao_e_https(): void
    {
        config(['votaflow.public_url' => 'http://203.0.113.10']);
        $v = $this->votacaoAberta();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/votacoes/{$v->public_id}")
            ->assertOk()->assertSee('login com Google não funciona')->assertDontSee('Celulares não vão abrir');
    }

    public function test_sem_aviso_quando_o_link_esta_ok(): void
    {
        config(['votaflow.public_url' => 'https://votacao.exemplo.com.br']);
        $v = $this->votacaoAberta();

        $this->actingAs(User::factory()->admin()->create())->get("/admin/votacoes/{$v->public_id}")
            ->assertOk()->assertDontSee('Celulares não vão abrir')->assertDontSee('login com Google não funciona')
            ->assertSee('https://votacao.exemplo.com.br/v/'.$v->public_id);
    }

    public function test_pagina_publica_do_qr_e_otimizada_para_celular(): void
    {
        $v = $this->votacaoAberta();
        $this->get("/v/{$v->public_id}")->assertOk()->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false);
    }
}
