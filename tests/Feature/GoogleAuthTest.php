<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fingirGoogle(string $id, string $email, bool $verificado = true, string $nome = 'João Silva'): void
    {
        $su = (new SocialiteUser)->setRaw(['email_verified' => $verificado])->map(['id' => $id, 'name' => $nome, 'email' => $email, 'avatar' => 'https://lh3.googleusercontent.com/a']);
        $su->user = ['email_verified' => $verificado];
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($su);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_redireciona_para_o_google(): void
    {
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 'sec', 'redirect' => 'http://localhost/auth/google/callback']]);
        $r = $this->get('/auth/google');
        $r->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $r->headers->get('Location'));
        $this->assertStringContainsString('state=', $r->headers->get('Location'), 'state anti-CSRF do OAuth');
    }

    public function test_primeiro_login_cria_participante_com_google_id_sem_guardar_tokens(): void
    {
        $this->fingirGoogle('1234567890', 'joao@exemplo.com');
        $this->get('/auth/google/callback')->assertRedirect(route('home'));

        $u = User::where('email', 'joao@exemplo.com')->firstOrFail();
        $this->assertSame('1234567890', $u->google_id);
        $this->assertSame(Role::Participante(), $u->role);
        $this->assertAuthenticatedAs($u);
        $this->assertNotContains('token', array_keys($u->getAttributes()));
        $this->assertNotContains('password', array_keys($u->getAttributes()));
    }

    public function test_email_nao_verificado_e_recusado(): void
    {
        $this->fingirGoogle('1', 'x@exemplo.com', false);
        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('erro');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_identifica_pelo_google_id_e_nao_so_pelo_email(): void
    {
        $existente = User::factory()->create(['google_id' => 'AAA', 'email' => 'a@exemplo.com']);

        // Outra conta Google (id diferente) que alega o mesmo e-mail NÃO assume a conta existente.
        $this->fingirGoogle('BBB', 'a@exemplo.com');
        $this->get('/auth/google/callback');

        $this->assertNotSame($existente->id, auth()->id() ?? 0);
    }

    public function test_mesma_conta_google_com_email_alterado_continua_sendo_o_mesmo_usuario(): void
    {
        $u = User::factory()->create(['google_id' => 'AAA', 'email' => 'velho@exemplo.com']);
        $this->fingirGoogle('AAA', 'novo@exemplo.com');
        $this->get('/auth/google/callback');
        $this->assertAuthenticatedAs($u);
        $this->assertSame('novo@exemplo.com', $u->fresh()->email);
        $this->assertSame(1, User::count());
    }

    public function test_email_configurado_como_admin_vira_admin_no_primeiro_login(): void
    {
        config(['votaflow.admin_emails' => ['chefe@exemplo.com']]);
        $this->fingirGoogle('9', 'Chefe@Exemplo.com');
        $this->get('/auth/google/callback')->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(User::first()->isAdmin());
        $this->assertDatabaseHas('auditorias', ['acao' => 'LOGIN']);
    }

    public function test_usuario_pre_cadastrado_e_vinculado_no_primeiro_login(): void
    {
        $pre = User::factory()->operador()->create(['google_id' => null, 'email' => 'op@exemplo.com']);
        $this->fingirGoogle('777', 'op@exemplo.com');
        $this->get('/auth/google/callback')->assertRedirect(route('admin.dashboard'));
        $this->assertSame('777', $pre->fresh()->google_id);
        $this->assertSame(Role::Operador(), $pre->fresh()->role);
    }

    public function test_apos_login_volta_para_a_votacao_do_qr_code(): void
    {
        $this->get('/auth/google?destino=/v/ABC23456/votar');
        $this->fingirGoogle('5', 'p@exemplo.com');
        $this->get('/auth/google/callback')->assertRedirect(url('/v/ABC23456/votar'));
    }

    public function test_destino_externo_e_ignorado_open_redirect(): void
    {
        config(['services.google' => ['client_id' => 'cid', 'client_secret' => 's', 'redirect' => 'http://localhost/cb']]);
        $this->get('/auth/google?destino=https://malicioso.example/x');
        $this->get('/auth/google?destino=//malicioso.example');
        $this->assertNull(session('url.intended'));
    }

    public function test_sessao_e_regenerada_no_login(): void
    {
        $this->get('/login');
        $antes = session()->getId();
        $this->fingirGoogle('6', 'q@exemplo.com');
        $this->get('/auth/google/callback');
        $this->assertNotSame($antes, session()->getId());
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
