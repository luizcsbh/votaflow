<?php

namespace App\Enums;

/**
 * @method static self EscolhaUnica()
 * @method static self MultiplaEscolha()
 * @method static self SimNao()
 * @method static self Escala()
 */
final class TipoPergunta extends Enum
{
    protected static function definicao(): array
    {
        return [
            'EscolhaUnica' => 'escolha_unica',
            'MultiplaEscolha' => 'multipla_escolha',
            'SimNao' => 'sim_nao',
            'Escala' => 'escala',
        ];
    }

    public function rotulo(): string
    {
        $m = [
            'escolha_unica' => 'Escolha única',
            'multipla_escolha' => 'Múltipla escolha',
            'sim_nao' => 'Sim / Não',
            'escala' => 'Escala (1 a 5)',
        ];

        return $m[$this->value];
    }

    /** Tipos cujas alternativas são geradas automaticamente. */
    public function alternativasFixas(): ?array
    {
        if ($this === self::SimNao()) {
            return ['Sim', 'Não'];
        }
        if ($this === self::Escala()) {
            return ['1', '2', '3', '4', '5'];
        }

        return null;
    }

    public function aceitaMultiplas(): bool
    {
        return $this === self::MultiplaEscolha();
    }
}
