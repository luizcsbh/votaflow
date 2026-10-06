<?php

namespace Tests\Unit;

use App\Enums\StatusVotacao as S;
use PHPUnit\Framework\TestCase;

class StatusVotacaoTest extends TestCase
{
    public function test_fluxo_normal_e_permitido(): void
    {
        $this->assertTrue(S::Rascunho()->podeIrPara(S::Agendada()));
        $this->assertTrue(S::Agendada()->podeIrPara(S::Aberta()));
        $this->assertTrue(S::Aberta()->podeIrPara(S::Encerrada()));
    }

    public function test_nao_volta_para_estados_incompativeis(): void
    {
        $this->assertFalse(S::Aberta()->podeIrPara(S::Rascunho()));
        $this->assertFalse(S::Aberta()->podeIrPara(S::Agendada()));
        $this->assertFalse(S::Encerrada()->podeIrPara(S::Rascunho()));
        $this->assertFalse(S::Encerrada()->podeIrPara(S::Agendada()));
    }

    public function test_cancelada_e_terminal(): void
    {
        foreach (S::cases() as $destino) {
            $this->assertFalse(S::Cancelada()->podeIrPara($destino));
        }
    }

    public function test_edicao_estrutural_somente_antes_de_abrir(): void
    {
        $this->assertTrue(S::Rascunho()->permiteEdicaoEstrutural());
        $this->assertTrue(S::Agendada()->permiteEdicaoEstrutural());
        $this->assertFalse(S::Aberta()->permiteEdicaoEstrutural());
        $this->assertFalse(S::Encerrada()->permiteEdicaoEstrutural());
    }
}
