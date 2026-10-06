<?php

namespace App\Casts;

use App\Enums\Enum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/** Cast Eloquent para os enums de App\Enums (uso: `'status' => EnumCast::class.':'.StatusVotacao::class`). */
class EnumCast implements CastsAttributes
{
    /** @var class-string<Enum> */
    private $classe;

    public function __construct(string $classe)
    {
        $this->classe = $classe;
    }

    public function get($model, string $key, $value, array $attributes)
    {
        return $value === null ? null : ($this->classe)::from($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => $value instanceof Enum ? $value->value : ($this->classe)::from($value)->value];
    }
}
