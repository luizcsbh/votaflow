<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PerfilController extends Controller
{
    /** Perfil do usuário logado: foto e e-mail vêm do Google (gravados em users.avatar / users.email a cada login). */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('perfil', [
            'user' => $user,
            'votosRegistrados' => $user->participacoes()->whereNotNull('finalizado_em')->count(),
        ]);
    }
}
