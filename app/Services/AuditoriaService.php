<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use App\Models\Votacao;
use Illuminate\Http\Request;

class AuditoriaService
{
    /** Chaves que jamais devem ser gravadas na auditoria. */
    private const PROIBIDAS = ['password', 'token', 'access_token', 'refresh_token', 'secret', 'authorization', 'cookie'];

    /** @var Request */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function registrar(string $acao, ?User $usuario = null, ?Votacao $votacao = null, array $dados = []): void
    {
        try {
            Auditoria::create([
                'usuario_id' => $usuario ? $usuario->getKey() : null,
                'votacao_id' => $votacao ? $votacao->getKey() : null,
                'acao' => $acao,
                'ip' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 300),
                'dados' => $this->limpar($dados) ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Falha de auditoria nunca pode derrubar a operação principal, mas é logada.
            report($e);
        }
    }

    private function limpar(array $dados): array
    {
        foreach ($dados as $k => $v) {
            if (in_array(strtolower((string) $k), self::PROIBIDAS, true)) {
                unset($dados[$k]);
            } elseif (is_array($v)) {
                $dados[$k] = $this->limpar($v);
            }
        }

        return $dados;
    }
}
