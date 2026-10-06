<?php

namespace App\Enums;

/**
 * @method static self Admin()
 * @method static self Operador()
 * @method static self Participante()
 */
final class Role extends Enum
{
    protected static function definicao(): array
    {
        return ['Admin' => 'admin', 'Operador' => 'operador', 'Participante' => 'participante'];
    }

    public function rotulo(): string
    {
        $m = ['admin' => 'Administrador', 'operador' => 'Operador', 'participante' => 'Participante'];

        return $m[$this->value];
    }
}
