<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusVotacao;
use App\Models\User;
use App\Models\Votacao;
use Illuminate\Database\Seeder;

/** Dados apenas para DESENVOLVIMENTO. Nunca rode em produção. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            if ($this->command) {
                $this->command->error('Seeder de desenvolvimento não roda em produção.');
            }

            return;
        }

        $admin = User::updateOrCreate(['email' => 'admin@votaflow.test'], ['name' => 'Admin Demo', 'role' => Role::Admin(), 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'operador@votaflow.test'], ['name' => 'Operador Demo', 'role' => Role::Operador(), 'email_verified_at' => now()]);

        Votacao::factory()->comPerguntas()->aberta()->create([
            'titulo' => 'Votação Escolar 2026',
            'descricao' => 'Sua opinião é importante! Escolha seu candidato e responda às perguntas.',
            'created_by' => $admin->id,
        ]);
        Votacao::factory()->comPerguntas()->create(['titulo' => 'Rascunho de exemplo', 'created_by' => $admin->id]);
        Votacao::factory()->comPerguntas()->create([
            'titulo' => 'Pesquisa anônima de clima', 'status' => StatusVotacao::Agendada(),
            'privacidade' => 'anonima', 'inicio_em' => now()->addDay(), 'fim_em' => now()->addDays(2), 'created_by' => $admin->id,
        ]);
    }
}
