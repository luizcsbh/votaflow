<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Votacao;
use App\Services\ResultadoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResultadosTest extends VotacaoTestCase
{
    public function test_resultados_agregados_corretos(): void
    {
        $v = $this->votacaoAberta(['limite_participantes' => 10]);
        $v->load('perguntas.alternativas');
        $p1 = $v->perguntas[0];

        // 4 votos: A, A, B, Branco
        foreach ([0, 0, 1, 2] as $opcao) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v, $opcao)]);
        }

        $r = app(ResultadoService::class)->calcular($v->fresh());
        $this->assertSame(4, $r['participantes']);
        $this->assertSame(40.0, $r['taxa_participacao']);
        $alts = collect($r['perguntas'][0]['alternativas'])->keyBy('texto');
        $this->assertSame(2, $alts['Candidato A']['total']);
        $this->assertSame(50.0, $alts['Candidato A']['percentual']);
        $this->assertSame(25.0, $alts['Candidato B']['percentual']);
        $this->assertSame(25.0, $alts['Branco']['percentual']);
        $this->assertSame(4, $r['perguntas'][0]['total_respostas']);
        $this->assertSame(8, $r['perguntas'][1]['total_respostas'], 'múltipla escolha: 2 marcações × 4');
        $this->assertSame(16, $r['total_votos']);
    }

    public function test_resultados_funcionam_em_votacao_anonima(): void
    {
        $v = Votacao::factory()->comPerguntas()->aberta()->anonima()->create();
        foreach ([0, 0, 1] as $o) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v, $o)]);
        }
        $r = app(ResultadoService::class)->calcular($v);
        $this->assertSame(3, $r['participantes']);
        $this->assertSame(66.7, collect($r['perguntas'][0]['alternativas'])->firstWhere('texto', 'Candidato A')['percentual']);
    }

    public function test_votacao_sem_votos_tem_percentual_zero(): void
    {
        $v = $this->votacaoAberta();
        $r = app(ResultadoService::class)->calcular($v);
        $this->assertSame(0, $r['participantes']);
        $this->assertSame(0.0, $r['perguntas'][0]['alternativas'][0]['percentual']);
    }

    public function test_resultado_usa_numero_constante_de_consultas_sem_n_mais_1(): void
    {
        $v = $this->votacaoAberta();
        foreach (range(1, 5) as $i) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        }
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ResultadoService::class)->calcular($v->fresh());
        $n1 = count(DB::getQueryLog());

        foreach (range(1, 20) as $i) {
            $this->actingAs($this->participante())->post("/v/{$v->public_id}/votar", ['respostas' => $this->respostasValidas($v)]);
        }
        Cache::flush();
        DB::flushQueryLog();
        app(ResultadoService::class)->calcular($v->fresh());
        $n2 = count(DB::getQueryLog());

        $this->assertSame($n1, $n2, 'consultas não crescem com o número de votos');
        $this->assertLessThanOrEqual(5, $n2);
    }

    public function test_resultados_ficam_em_cache(): void
    {
        $v = $this->votacaoAberta();
        $svc = app(ResultadoService::class);
        $svc->paraVotacao($v);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $svc->paraVotacao($v);
        $this->assertCount(0, DB::getQueryLog(), 'segunda leitura vem do cache, sem SQL');
    }

    public function test_endpoint_json_de_resultados_para_o_painel(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->votacaoAberta();
        $this->actingAs($admin)->getJson("/admin/resultados/{$v->public_id}/dados")->assertOk()->assertJsonStructure(['participantes', 'total_votos', 'perguntas' => [['titulo', 'alternativas']]]);
    }
}
