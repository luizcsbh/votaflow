<?php

namespace Tests\Concurrency;

use App\Exceptions\VotacaoException;
use App\Models\Participacao;
use App\Models\Resposta;
use App\Models\User;
use App\Models\Votacao;
use App\Services\ResultadoService;
use App\Services\VotoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Concorrência REAL: processos filhos (pcntl_fork) votando ao mesmo tempo contra um banco em arquivo.
 *
 * Por padrão simula 100 votantes. Para 500 ou 1000:
 *   VOTAFLOW_CONC_USUARIOS=1000 php artisan test --testsuite=Concurrency
 * Para validar com MySQL (recomendado antes de produção), aponte DB_CONNECTION/DB_* para um banco de teste
 * e use VOTAFLOW_CONC_DB=env (o teste usa a conexão do .env em vez do SQLite temporário).
 */
class ConcorrenciaTest extends TestCase
{
    private string $dbFile = '';

    private string $dirResultados = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl indisponível.');
        }

        if (getenv('VOTAFLOW_CONC_DB') !== 'env') {
            $this->dbFile = sys_get_temp_dir().'/votaflow-conc-'.getmypid().'.sqlite';
            @unlink($this->dbFile);
            touch($this->dbFile);
            // SQLite: WAL + espera por lock. Obs.: no SQLite uma transação "deferred" que começa lendo e depois escreve
            // pode falhar na hora com SQLITE_BUSY (snapshot desatualizado) — limitação do SQLite, inexistente no MySQL/InnoDB.
            // Por isso os filhos repetem a tentativa nesse caso (ver tentarRegistrar); os invariantes continuam valendo.
            config(['database.connections.sqlite.database' => $this->dbFile,
                'database.connections.sqlite.busy_timeout' => 60000,
                'database.connections.sqlite.journal_mode' => 'WAL']);
            DB::purge('sqlite');
        }
        config(['cache.default' => 'file', 'cache.stores.file.path' => sys_get_temp_dir().'/votaflow-cache-'.getmypid()]);

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->dirResultados = sys_get_temp_dir().'/votaflow-res-'.getmypid();
        @mkdir($this->dirResultados);
    }

    protected function tearDown(): void
    {
        if ($this->dbFile) {
            DB::disconnect();
            foreach ([$this->dbFile, $this->dbFile.'-wal', $this->dbFile.'-shm'] as $f) {
                @unlink($f);
            }
        }
        foreach (glob($this->dirResultados.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dirResultados);
        parent::tearDown();
    }

    private function votacao(?int $limite = null): Votacao
    {
        return Votacao::factory()->comPerguntas()->aberta()->create(['limite_participantes' => $limite]);
    }

    private function respostas(Votacao $v, int $opcao): array
    {
        $v->load('perguntas.alternativas');
        [$p1, $p2, $p3] = $v->perguntas->all();

        return [$p1->id => [$p1->alternativas[$opcao % 3]->id], $p2->id => [$p2->alternativas[0]->id, $p2->alternativas[1]->id], $p3->id => [$p3->alternativas[0]->id]];
    }

    /**
     * Dispara N processos filhos que esperam uma "largada" comum e votam juntos.
     *
     * @param  list<array{0:int,1:array}>  $tarefas  [usuario_id, respostas]
     * @return array<int, string> resultado por tarefa: OK | JA_VOTOU | LIMITE | FECHADA | ERRO:...
     */
    private function dispararVotos(Votacao $v, array $tarefas, int $lote = 100): array
    {
        $resultados = [];
        $largada = $this->dirResultados.'/largada';

        foreach (array_chunk($tarefas, $lote, true) as $grupo) {
            @unlink($largada);
            DB::disconnect(); // nenhuma conexão aberta atravessa o fork
            $pids = [];

            foreach ($grupo as $idx => [$usuarioId, $respostas]) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('fork falhou');
                }
                if ($pid === 0) { // ── filho ──
                    $saida = 'ERRO';
                    try {
                        while (! file_exists($largada)) {
                            usleep(500);
                        }
                        DB::purge();
                        $user = User::findOrFail($usuarioId);
                        $this->tentarRegistrar($v, $user, $respostas);
                        $saida = 'OK';
                    } catch (VotacaoException $e) {
                        $saida = strtoupper($e->codigo === 'ja_votou' ? 'JA_VOTOU' : ($e->codigo === 'limite' ? 'LIMITE' : ($e->codigo === 'fechada' ? 'FECHADA' : 'INVALIDA')));
                    } catch (\Throwable $e) {
                        $saida = 'ERRO:'.get_class($e).':'.mb_substr($e->getMessage(), 0, 200);
                    }
                    file_put_contents($this->dirResultados."/$idx", $saida);
                    posix_kill(posix_getpid(), SIGKILL); // sai sem executar shutdown do PHPUnit
                }
                $pids[] = $pid;
            }

            usleep(300_000); // todos os filhos prontos na linha de largada
            touch($largada);
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }
            foreach (array_keys($grupo) as $idx) {
                $resultados[$idx] = (string) @file_get_contents($this->dirResultados."/$idx");
            }
            DB::reconnect();
        }

        return $resultados;
    }

    /** Repete apenas em "database is locked" (artefato do SQLite). Qualquer outro erro é propagado. */
    private function tentarRegistrar(Votacao $v, User $user, array $respostas): void
    {
        for ($i = 1; ; $i++) {
            try {
                app(VotoService::class)->registrar($v->fresh(), $user, $respostas);

                return;
            } catch (QueryException $e) {
                if ($i >= 400 || strpos($e->getMessage(), 'database is locked') === false) {
                    throw $e;
                }
                usleep(random_int(1000, 15000));
            }
        }
    }

    private function usuarios(int $n): array
    {
        return User::factory()->count($n)->create()->pluck('id')->all();
    }

    public function test_mesmo_usuario_enviando_o_voto_em_paralelo_gera_uma_unica_participacao(): void
    {
        $v = $this->votacao();
        $u = User::factory()->create();
        $tarefas = [];
        foreach (range(0, 29) as $i) {
            $tarefas[$i] = [$u->id, $this->respostas($v, $i)];
        }

        $res = $this->dispararVotos($v, $tarefas, 30);
        $contagem = array_count_values($res);

        $this->assertSame(1, Participacao::where('votacao_id', $v->id)->where('usuario_id', $u->id)->count(), json_encode($contagem));
        $this->assertSame(1, $contagem['OK'] ?? 0, json_encode($contagem));
        $this->assertSame(29, $contagem['JA_VOTOU'] ?? 0, json_encode($contagem));
        $this->assertSame(4, Resposta::count(), 'só as respostas do voto vencedor existem (1 + 2 + 1)');
    }

    public function test_n_usuarios_votando_simultaneamente_sem_perda_nem_duplicidade(): void
    {
        $n = (int) (getenv('VOTAFLOW_CONC_USUARIOS') ?: 100);
        $v = $this->votacao();
        $ids = $this->usuarios($n);
        $tarefas = [];
        foreach ($ids as $i => $id) {
            $tarefas[$i] = [$id, $this->respostas($v, $i)];
        }

        $inicio = microtime(true);
        $res = $this->dispararVotos($v, $tarefas);
        $dur = round(microtime(true) - $inicio, 2);
        $contagem = array_count_values($res);

        $this->assertSame($n, $contagem['OK'] ?? 0, "esperado $n OK em {$dur}s: ".json_encode($contagem));
        $this->assertSame($n, Participacao::where('votacao_id', $v->id)->count(), 'nenhum voto perdido');
        $this->assertSame($n, Participacao::where('votacao_id', $v->id)->distinct()->count('usuario_id'), 'nenhuma participação duplicada');
        $this->assertSame($n, Participacao::distinct()->count('protocolo'), 'protocolos únicos');
        $this->assertSame($n * 4, Resposta::count(), 'cada voto gravou exatamente 4 respostas (1+2+1)');

        // Integridade: nenhuma participação "pela metade".
        $semResposta = DB::table('participacoes')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('respostas')->whereColumn('respostas.participacao_id', 'participacoes.id'))->count();
        $this->assertSame(0, $semResposta);

        // Resultados consistentes com os votos enviados.
        $r = app(ResultadoService::class)->calcular($v);
        $this->assertSame($n, $r['participantes']);
        $esperado = array_count_values(array_map(fn ($i) => $i % 3, array_keys($ids)));
        foreach ($r['perguntas'][0]['alternativas'] as $k => $alt) {
            $this->assertSame($esperado[$k] ?? 0, $alt['total'], 'contagem da alternativa '.$alt['texto']);
        }
        $this->assertSame($n, $r['perguntas'][2]['total_respostas']);

        fwrite(STDERR, sprintf("\n[concorrência] %d votos simultâneos em %.2fs (%.0f votos/s)\n", $n, $dur, $n / max($dur, 0.001)));
    }

    public function test_limite_de_participantes_sob_concorrencia_nunca_e_excedido(): void
    {
        $limite = 20;
        $v = $this->votacao($limite);
        $ids = $this->usuarios(60);
        $tarefas = [];
        foreach ($ids as $i => $id) {
            $tarefas[$i] = [$id, $this->respostas($v, $i)];
        }

        $res = $this->dispararVotos($v, $tarefas, 60);
        $c = array_count_values($res);

        $this->assertSame($limite, $c['OK'] ?? 0, json_encode($c));
        $this->assertSame(40, $c['LIMITE'] ?? 0, json_encode($c));
        $this->assertSame($limite, Participacao::count());
        $this->assertSame($limite * 4, Resposta::count(), 'votos recusados foram desfeitos por completo');
        $this->assertSame($limite, $v->fresh()->participantes_count);
    }

    public function test_votacao_anonima_sob_concorrencia_nao_vincula_nenhuma_resposta(): void
    {
        $v = Votacao::factory()->comPerguntas()->aberta()->anonima()->create();
        $ids = $this->usuarios(50);
        $tarefas = [];
        foreach ($ids as $i => $id) {
            $tarefas[$i] = [$id, $this->respostas($v, $i)];
        }
        $res = $this->dispararVotos($v, $tarefas, 50);

        $this->assertSame(50, array_count_values($res)['OK'] ?? 0, json_encode(array_count_values($res)));
        $this->assertSame(50, Participacao::count());
        $this->assertSame(200, Resposta::count());
        $this->assertSame(0, Resposta::whereNotNull('participacao_id')->count());
    }
}
