<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;

/** Regressão: valores PHP injetados em atributos (@js) não podem fechar o atributo nem vazar texto. */
class AtributosHtmlTest extends VotacaoTestCase
{
    public function test_botoes_copiar_e_compartilhar_tem_atributo_integro_e_sem_texto_vazado(): void
    {
        $v = $this->votacaoAberta(['titulo' => 'Título com "aspas" e \'apóstrofo\' <b>']);
        $html = $this->actingAs(User::factory()->admin()->create())
            ->get("/admin/votacoes/{$v->public_id}")->assertOk()->getContent();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        // Nenhum resto de código JS pode aparecer como texto visível da página.
        $this->assertStringNotContainsString('copiado = true', (string) $dom->getElementsByTagName('body')->item(0)->textContent);

        $copiar = $xp->query('//button[contains(normalize-space(.), "Copiar link")]')->item(0);
        $this->assertNotNull($copiar, 'botão "Copiar link" não encontrado');
        $click = $copiar->getAttribute('@click');
        $this->assertStringContainsString('clipboard.writeText(', $click);
        $this->assertStringContainsString('setTimeout(() => copiado = false, 2000)', $click, 'o atributo @click foi cortado');
        $this->assertStringContainsString($v->public_id, $click);

        $compartilhar = $xp->query('//button[contains(normalize-space(.), "Compartilhar")]')->item(0);
        $this->assertNotNull($compartilhar);
        $this->assertStringContainsString('navigator.share(', $compartilhar->getAttribute('@click'));
    }

    public function test_x_data_do_formulario_e_do_wizard_chegam_inteiros(): void
    {
        $v = $this->votacaoAberta();
        $admin = User::factory()->admin()->create();

        $form = $this->actingAs($admin)->get('/admin/votacoes/criar')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/x-data="votacaoForm\(\{ perguntas: .*\}\)"/s', $form);

        $wizard = $this->actingAs($this->participante())->get("/v/{$v->public_id}/votar")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/x-data="wizard\(\{ perguntas: .*minutos: \d+ \}\)"/s', $wizard);
    }
}
