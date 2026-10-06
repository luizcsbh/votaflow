<?php

namespace App\Enums;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Substituto de `enum` nativo (PHP 8.1) para PHP 7.4.
 *
 * Cada "case" é um singleton: `StatusVotacao::Aberta()` devolve sempre a mesma
 * instância, então `===` e `in_array(..., true)` funcionam como em enums reais.
 * Subclasses só declaram `definicao()` (nome do case => valor persistido).
 */
abstract class Enum implements JsonSerializable
{
    /** @var array<string, array<string, static>> */
    private static $instancias = [];

    /** @var string */
    public $name;

    /** @var string */
    public $value;

    private function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /** @return array<string, string> nome do case => valor */
    abstract protected static function definicao(): array;

    /** @return static[] */
    public static function cases(): array
    {
        $out = [];
        foreach (array_keys(static::definicao()) as $nome) {
            $out[] = static::instancia($nome);
        }

        return $out;
    }

    /** @return string[] */
    public static function valores(): array
    {
        return array_values(static::definicao());
    }

    public static function tryFrom($valor): ?self
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }
        $nome = array_search((string) $valor, static::definicao(), true);

        return $nome === false ? null : static::instancia($nome);
    }

    public static function from($valor): self
    {
        $e = static::tryFrom($valor);
        if ($e === null) {
            throw new InvalidArgumentException('Valor inválido para '.static::class.': '.(is_scalar($valor) ? $valor : gettype($valor)));
        }

        return $e;
    }

    /** `StatusVotacao::Aberta()` */
    public static function __callStatic($nome, $args)
    {
        if (! array_key_exists($nome, static::definicao())) {
            throw new \BadMethodCallException('Case inexistente: '.static::class.'::'.$nome);
        }

        return static::instancia($nome);
    }

    private static function instancia(string $nome): self
    {
        $classe = static::class;
        if (! isset(self::$instancias[$classe][$nome])) {
            self::$instancias[$classe][$nome] = new static($nome, static::definicao()[$nome]);
        }

        return self::$instancias[$classe][$nome];
    }

    public function jsonSerialize()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
