<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Votacao;
use App\Services\VotoService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rastreia a aplicação como cada perfil: abre as telas, extrai TODOS os <a href> e <form action>
 * e confere que cada destino interno responde (2xx, ou redirect que termina em 2xx) e que cada
 * formulário aponta para uma rota/método que existe. Links para fora do site só precisam ser https.
 *
 * Limite conhecido: links montados em JavaScript (Alpine) não existem no HTML estático e não são rastreados.
 */
class LinksTest extends VotacaoTestCase
{
    /** @var Votacao */
    private $votacao;

    /** @var Votacao */
    private $rascunho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->votacao = $this->votacaoAberta(['titulo' => 'Votação de teste de links']);
        $this->rascunho = Votacao::factory()->comPerguntas()->create(['titulo' => 'Rascunho de links']);

        // Uma participação existente, para que o comprovante exista para o participante.
        $this->votante = $this->participante();
        app(VotoService::class)->registrar($this->votacao, $this->votante, $this->respostasValidas($this->votacao));
    }

    /** @var User */
    private $votante;

    /** Telas de entrada de cada perfil (o crawler descobre o resto pelos links). */
    private function sementes(): array
    {
        $id = $this->votacao->public_id;

        // Operador só enxerga as próprias votações (as dos outros dão 403 de propósito).
        $operador = User::factory()->operador()->create();
        $propria = $this->votacaoAberta(['created_by' => $operador->id]);
        $propriaRascunho = Votacao::factory()->comPerguntas()->create(['created_by' => $operador->id]);

        return [
            'visitante' => [null, ['/', '/login', "/v/{$id}", "/v/{$id}/votar", '/health', '/up']],
            'participante' => ['votante', ['/', "/v/{$id}", "/v/{$id}/votar", "/v/{$id}/comprovante"]],
            'operador' => [$operador, $this->telasAdmin($propria, $propriaRascunho)],
            'admin' => [User::factory()->admin()->create(), $this->telasAdmin($this->votacao, $this->rascunho)],
        ];
    }

    private function telasAdmin(Votacao $votacao, Votacao $rascunho): array
    {
        $id = $votacao->public_id;
        $rid = $rascunho->public_id;

        return [
            '/admin', '/admin/votacoes', '/admin/votacoes/criar',
            "/admin/votacoes/{$id}", "/admin/votacoes/{$rid}", "/admin/votacoes/{$rid}/editar",
            "/admin/votacoes/{$id}/qrcode", "/admin/votacoes/{$id}/imprimir",
            '/admin/resultados', "/admin/resultados/{$id}", "/admin/resultados/{$id}/dados",
        ];
    }

    public function test_todos_os_links_de_cada_perfil_abrem(): void
    {
        $problemas = [];
        $totalLinks = 0;

        foreach ($this->sementes() as $perfil => [$usuario, $telas]) {
            $this->flushSession();
            if ($usuario === 'votante') {
                $usuario = $this->votante;
            }
            if ($usuario) {
                $this->actingAs($usuario);
            }

            $fila = $telas;
            $vistos = [];
            while ($fila) {
                $caminho = array_shift($fila);
                if (isset($vistos[$caminho]) || count($vistos) > 80) {
                    continue;
                }
                $vistos[$caminho] = true;

                $resposta = $this->abrir($caminho);
                if ($resposta['status'] !== 200) {
                    $problemas[] = "[{$perfil}] {$caminho} => HTTP {$resposta['status']}";

                    continue;
                }
                if (strpos($resposta['tipo'], 'html') === false) {
                    continue;
                }

                [$links, $forms] = $this->extrair($resposta['corpo']);
                foreach ($links as $href) {
                    $totalLinks++;
                    $destino = $this->normalizar($href);
                    if ($destino === null) {
                        continue;
                    }
                    if ($destino === false) {
                        if (stripos($href, 'https://') !== 0) {
                            $problemas[] = "[{$perfil}] link externo sem https em {$caminho}: {$href}";
                        }

                        continue;
                    }
                    $fila[] = $destino;
                }
                foreach ($forms as [$metodo, $action]) {
                    $destino = $this->normalizar($action);
                    if ($destino && ! $this->rotaExiste($destino, $metodo)) {
                        $problemas[] = "[{$perfil}] formulário em {$caminho} aponta para rota inexistente: {$metodo} {$destino}";
                    }
                }
            }
        }

        $this->assertGreaterThan(30, $totalLinks, 'o crawler deveria encontrar dezenas de links');
        $this->assertSame([], array_values(array_unique($problemas)), "Links quebrados:\n".implode("\n", array_unique($problemas)));
    }

    /** Toda rota GET sem parâmetros do sistema responde para o admin (exceto o callback do Google, que exige o provedor). */
    public function test_todas_as_rotas_get_sem_parametros_respondem(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $falhas = [];

        foreach (app('router')->getRoutes()->getRoutes() as $rota) {
            if (! in_array('GET', $rota->methods(), true) || strpos($rota->uri(), '{') !== false) {
                continue;
            }
            if (in_array($rota->uri(), ['auth/google', 'auth/google/callback', 'dev/login'], true)) {
                continue;
            }
            $r = $this->abrir('/'.ltrim($rota->uri(), '/'));
            if ($r['status'] !== 200) {
                $falhas[] = "/{$rota->uri()} => HTTP {$r['status']}";
            }
        }

        $this->assertSame([], $falhas, implode("\n", $falhas));
    }

    public function test_botao_de_login_aponta_para_o_google(): void
    {
        $this->get('/auth/google')->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $this->get('/auth/google')->headers->get('Location'));
    }

    // ── auxiliares ───────────────────────────────────────────────────────────

    /** GET seguindo redirects internos (máx. 5). Devolve status final, tipo e corpo. */
    private function abrir(string $caminho): array
    {
        for ($i = 0; $i < 5; $i++) {
            $r = $this->get($caminho);
            $status = $r->getStatusCode();
            if ($status >= 300 && $status < 400) {
                $local = $r->headers->get('Location');
                $proximo = $this->normalizar((string) $local);
                if ($proximo === false && stripos((string) $local, 'https://') === 0) {
                    // Redirect para provedor externo (ex.: Google): destino https é o esperado.
                    return ['status' => 200, 'tipo' => '', 'corpo' => ''];
                }
                if (! $proximo) {
                    return ['status' => $status, 'tipo' => '', 'corpo' => ''];
                }
                $caminho = $proximo;

                continue;
            }

            return [
                'status' => $status,
                'tipo' => (string) $r->headers->get('Content-Type'),
                'corpo' => $r instanceof \Illuminate\Testing\TestResponse ? (string) $r->getContent() : '',
            ];
        }

        return ['status' => 310, 'tipo' => '', 'corpo' => '']; // loop de redirects
    }

    /** @return array{0: string[], 1: array<int, array{0: string, 1: string}>} */
    private function extrair(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        $links = [];
        foreach ($xp->query('//a[@href]') as $a) {
            $links[] = trim($a->getAttribute('href'));
        }
        $forms = [];
        foreach ($xp->query('//form[@action]') as $f) {
            $metodo = strtoupper($f->getAttribute('method') ?: 'GET');
            $spoof = $xp->query('.//input[@name="_method"]', $f)->item(0);
            if ($spoof) {
                $metodo = strtoupper($spoof->getAttribute('value'));
            }
            $forms[] = [$metodo, trim($f->getAttribute('action'))];
        }

        return [$links, $forms];
    }

    /**
     * Caminho interno ("/x?y=1") para links do próprio site; false para externos; null para ignorar
     * (âncoras, javascript:, mailto:, logout por link, vazio).
     *
     * @return string|false|null
     */
    private function normalizar(string $url)
    {
        if ($url === '' || $url[0] === '#' || preg_match('/^(javascript|mailto|tel):/i', $url)) {
            return null;
        }
        $p = parse_url($url);
        if (! empty($p['host']) && $p['host'] !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return false;
        }
        $caminho = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');

        return $caminho === '' ? '/' : $caminho;
    }

    private function rotaExiste(string $caminho, string $metodo): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($caminho, $metodo));

            return true;
        } catch (NotFoundHttpException | MethodNotAllowedHttpException $e) {
            return false;
        }
    }
}
