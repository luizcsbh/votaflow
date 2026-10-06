<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Votacao;

/**
 * ADMIN: tudo. OPERADOR: cria e conduz apenas as próprias votações (sem cancelar/reabrir/exportar).
 */
class VotacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->podeAcessarPainel();
    }

    public function view(User $user, Votacao $votacao): bool
    {
        return $user->isAdmin() || ($user->isOperador() && $votacao->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->podeAcessarPainel();
    }

    public function update(User $user, Votacao $votacao): bool
    {
        return $this->view($user, $votacao);
    }

    public function abrir(User $user, Votacao $votacao): bool
    {
        return $this->view($user, $votacao);
    }

    public function encerrar(User $user, Votacao $votacao): bool
    {
        return $this->view($user, $votacao);
    }

    public function cancelar(User $user, Votacao $votacao): bool
    {
        return $user->isAdmin();
    }

    public function reabrir(User $user, Votacao $votacao): bool
    {
        return $user->isAdmin();
    }

    public function verResultados(User $user, Votacao $votacao): bool
    {
        return $this->view($user, $votacao);
    }

    public function exportar(User $user, Votacao $votacao): bool
    {
        return $user->isAdmin();
    }
}
