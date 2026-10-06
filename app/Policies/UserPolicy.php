<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $alvo): bool
    {
        return $user->isAdmin() && $user->id !== $alvo->id; // ninguém altera o próprio perfil de acesso
    }
}
