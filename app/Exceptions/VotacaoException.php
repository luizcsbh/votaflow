<?php

namespace App\Exceptions;

use RuntimeException;

/** Erro de regra de negócio com mensagem segura para exibir ao usuário. */
class VotacaoException extends RuntimeException
{
    public const FECHADA = 'fechada';

    public const JA_VOTOU = 'ja_votou';

    public const LIMITE = 'limite';

    public const INVALIDA = 'invalida';

    public const TRANSICAO = 'transicao';

    public const EDICAO = 'edicao';

    /** @var string */
    public $codigo;

    /** @var array */
    public $erros;

    public function __construct(string $message, string $codigo = self::INVALIDA, array $erros = [])
    {
        parent::__construct($message);
        $this->codigo = $codigo;
        $this->erros = $erros;
    }

    public static function fechada(): self
    {
        return new self('Esta votação não está aberta para participação no momento.', self::FECHADA);
    }

    public static function jaVotou(): self
    {
        return new self('Você já participou desta votação.', self::JA_VOTOU);
    }

    public static function limiteAtingido(): self
    {
        return new self('O limite de participantes desta votação foi atingido.', self::LIMITE);
    }

    public static function respostasInvalidas(array $erros): self
    {
        return new self('Confira as respostas e tente novamente.', self::INVALIDA, $erros);
    }
}
