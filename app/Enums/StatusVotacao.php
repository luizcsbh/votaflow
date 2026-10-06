<?php

namespace App\Enums;

/**
 * @method static self Rascunho()
 * @method static self Agendada()
 * @method static self Aberta()
 * @method static self Encerrada()
 * @method static self Cancelada()
 */
final class StatusVotacao extends Enum
{
    protected static function definicao(): array
    {
        return [
            'Rascunho' => 'RASCUNHO',
            'Agendada' => 'AGENDADA',
            'Aberta' => 'ABERTA',
            'Encerrada' => 'ENCERRADA',
            'Cancelada' => 'CANCELADA',
        ];
    }

    /** @return self[] Transições permitidas (qualquer outra exige ação administrativa nova). */
    public function transicoesPermitidas(): array
    {
        $m = [
            'RASCUNHO' => [self::Agendada(), self::Aberta(), self::Cancelada()],
            'AGENDADA' => [self::Rascunho(), self::Aberta(), self::Cancelada()],
            'ABERTA' => [self::Encerrada(), self::Cancelada()],
            'ENCERRADA' => [self::Aberta()], // reabertura explícita (somente admin)
            'CANCELADA' => [],
        ];

        return $m[$this->value];
    }

    public function podeIrPara(self $destino): bool
    {
        return in_array($destino, $this->transicoesPermitidas(), true);
    }

    /** Estrutura (perguntas/alternativas) só pode mudar enquanto não há votos possíveis. */
    public function permiteEdicaoEstrutural(): bool
    {
        return in_array($this, [self::Rascunho(), self::Agendada()], true);
    }

    public function rotulo(): string
    {
        $m = [
            'RASCUNHO' => 'Rascunho', 'AGENDADA' => 'Agendada', 'ABERTA' => 'Aberta',
            'ENCERRADA' => 'Encerrada', 'CANCELADA' => 'Cancelada',
        ];

        return $m[$this->value];
    }

    public function cor(): string
    {
        $m = [
            'RASCUNHO' => 'bg-slate-100 text-slate-700',
            'AGENDADA' => 'bg-amber-100 text-amber-800',
            'ABERTA' => 'bg-emerald-100 text-emerald-800',
            'ENCERRADA' => 'bg-indigo-100 text-indigo-800',
            'CANCELADA' => 'bg-rose-100 text-rose-800',
        ];

        return $m[$this->value];
    }
}
