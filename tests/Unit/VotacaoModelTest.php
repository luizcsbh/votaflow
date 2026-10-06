<?php

namespace Tests\Unit;

use App\Enums\StatusVotacao;
use App\Models\Participacao;
use App\Models\Votacao;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VotacaoModelTest extends TestCase
{
    public function test_public_id_e_aleatorio_e_sem_caracteres_ambiguos(): void
    {
        $ids = collect(range(1, 200))->map(fn () => Votacao::gerarPublicId());
        $this->assertCount(200, $ids->unique());
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $id);
        }
    }

    public function test_protocolo_tem_formato_e_nao_revela_informacao(): void
    {
        $p = Participacao::gerarProtocolo();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $p);
        $this->assertNotSame($p, Participacao::gerarProtocolo());
    }

    public function test_aceita_votos_somente_aberta_e_dentro_da_janela(): void
    {
        $agora = Carbon::parse('2026-10-06 12:00:00');
        $v = new Votacao(['status' => StatusVotacao::Aberta()]);
        $v->status = StatusVotacao::Aberta();
        $v->inicio_em = $agora->copy()->subHour();
        $v->fim_em = $agora->copy()->addHour();
        $this->assertTrue($v->aceitaVotos($agora));

        $v->fim_em = $agora->copy()->subMinute();           // passou do fim
        $this->assertFalse($v->aceitaVotos($agora));

        $v->fim_em = $agora->copy()->addHour();
        $v->inicio_em = $agora->copy()->addMinute();        // ainda não começou
        $this->assertFalse($v->aceitaVotos($agora));

        $v->inicio_em = $agora->copy()->subHour();
        foreach ([StatusVotacao::Rascunho(), StatusVotacao::Agendada(), StatusVotacao::Encerrada(), StatusVotacao::Cancelada()] as $st) {
            $v->status = $st;
            $this->assertFalse($v->aceitaVotos($agora), "status {$st->value}");
        }
    }
}
