<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;

class DevLoginTest extends VotacaoTestCase
{
    private function ligarDevLogin(): void
    {
        config(['votaflow.dev_login' => true]);
        $this->app['env'] = 'local';
    }

    public function test_desligado_por_padrao_responde_404(): void
    {
        $this->get('/dev/login')->assertNotFound();
        $this->post('/dev/login')->assertNotFound();
        $this->assertGuest();
    }

    public function test_em_producao_e_impossivel_mesmo_com_a_flag(): void
    {
        config(['votaflow.dev_login' => true]);
        $this->app['env'] = 'production';

        $this->get('/dev/login')->assertNotFound();
        $this->post('/dev/login')->assertNotFound();
        $this->assertGuest();
    }

    public function test_flag_so_vale_com_app_env_local(): void
    {
        // A config é derivada de APP_ENV=local E da flag; sob APP_ENV=testing fica desligada.
        $this->assertFalse(config('votaflow.dev_login'));
    }

    public function test_ligado_cria_participante_de_teste_e_loga(): void
    {
        $this->ligarDevLogin();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->get('/dev/login')->assertOk()->assertSee('Somente desenvolvimento');
        $this->post('/dev/login')->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertStringEndsWith('@votaflow.test', auth()->user()->email);
        $this->assertFalse(auth()->user()->podeAcessarPainel());
    }

    public function test_ligado_entra_como_admin_existente(): void
    {
        $this->ligarDevLogin();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $admin = User::factory()->admin()->create();

        $this->post('/dev/login', ['usuario' => $admin->id])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_exige_csrf(): void
    {
        $this->ligarDevLogin(); // APP_ENV=local: o CSRF não é dispensado como em testing
        $this->post('/dev/login')->assertStatus(419);
        $this->assertGuest();
    }

    public function test_rejeita_usuario_inexistente(): void
    {
        $this->ligarDevLogin();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->post('/dev/login', ['usuario' => 999999])->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_botao_so_aparece_no_login_com_dev_ligado(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('usuário de teste');
        config(['votaflow.dev_login' => true]);
        $this->get('/login')->assertOk()->assertSee('Entrar como usuário de teste');
    }

    public function test_com_dev_ligado_link_na_rede_local_vale_para_celular(): void
    {
        config(['votaflow.dev_login' => true, 'votaflow.public_url' => 'http://192.168.0.15:8000']);
        $v = $this->votacaoAberta();
        $this->assertTrue($v->linkAcessivelPorCelular());
        $this->assertTrue($v->linkUsaHttps());

        config(['votaflow.public_url' => 'http://localhost:8000']);
        $this->assertFalse($v->linkAcessivelPorCelular(), 'localhost nunca serve para celular');
    }
}
