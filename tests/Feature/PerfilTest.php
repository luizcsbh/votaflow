<?php

namespace Tests\Feature;

use App\Models\User;

class PerfilTest extends VotacaoTestCase
{
    public function test_visitante_e_redirecionado_para_login(): void
    {
        $this->get('/perfil')->assertRedirect(route('login'));
    }

    public function test_participante_ve_foto_e_email_do_google(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Souza',
            'email' => 'maria@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/abc123=s96-c',
        ]);

        $this->actingAs($user)->get('/perfil')
            ->assertOk()
            ->assertSee('maria@gmail.com')
            ->assertSee('Maria Souza')
            ->assertSee('https://lh3.googleusercontent.com/a/abc123=s256-c', false)
            ->assertSee('referrerpolicy="no-referrer"', false);
    }

    public function test_sem_foto_mostra_iniciais(): void
    {
        $user = User::factory()->create(['name' => 'João Pedro Lima', 'avatar' => null]);

        $this->actingAs($user)->get('/perfil')
            ->assertOk()
            ->assertSee('JP')
            ->assertDontSee('<img', false);
    }

    public function test_admin_ve_perfil_dentro_do_painel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/perfil')
            ->assertOk()
            ->assertSee('Menu principal')
            ->assertSee($admin->email);
    }
}
