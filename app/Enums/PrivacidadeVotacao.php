<?php

namespace App\Enums;

/**
 * Modos de privacidade (documentação completa em docs/PRIVACIDADE.md).
 *
 * @method static self Identificada()        Resposta vinculada à participação; admin pode consultar quem votou em quê.
 * @method static self ParcialmenteAnonima() Vínculo gravado, mas relatórios só mostram agregados.
 * @method static self Anonima()             Nenhum vínculo é gravado entre participante e resposta.
 */
final class PrivacidadeVotacao extends Enum
{
    protected static function definicao(): array
    {
        return [
            'Identificada' => 'identificada',
            'ParcialmenteAnonima' => 'parcialmente_anonima',
            'Anonima' => 'anonima',
        ];
    }

    public function rotulo(): string
    {
        $m = [
            'identificada' => 'Identificada',
            'parcialmente_anonima' => 'Parcialmente anônima',
            'anonima' => 'Anônima',
        ];

        return $m[$this->value];
    }

    public function descricao(): string
    {
        $m = [
            'identificada' => 'O administrador pode ver quem votou em cada opção.',
            'parcialmente_anonima' => 'O vínculo é gravado, mas os relatórios exibem apenas totais.',
            'anonima' => 'Nenhum vínculo entre pessoa e resposta é gravado. Só se registra que a pessoa votou.',
        ];

        return $m[$this->value];
    }

    public function vinculaResposta(): bool
    {
        return $this !== self::Anonima();
    }
}
