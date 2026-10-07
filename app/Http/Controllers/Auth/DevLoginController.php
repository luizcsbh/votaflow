<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Login SEM Google, só para desenvolvimento local (ver config votaflow.dev_login).
 * Fora de APP_ENV=local com VOTAFLOW_DEV_LOGIN=true toda requisição aqui recebe 404 (checado no construtor).
 */
class DevLoginController extends Controller
{
    public function __construct()
    {
        abort_unless(config('votaflow.dev_login') && app()->environment('local'), 404);
    }

    public function index()
    {
        return view('auth.dev-login', [
            'usuarios' => User::query()->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'operador' THEN 1 ELSE 2 END")->orderBy('name')->limit(30)->get(),
        ]);
    }

    /** Entra como um usuário existente ou cria um participante novo ("Participante de teste N"). */
    public function entrar(Request $request): RedirectResponse
    {
        $dados = $request->validate(['usuario' => ['nullable', 'integer', 'exists:users,id']]);

        if (! empty($dados['usuario'])) {
            $user = User::findOrFail($dados['usuario']);
        } else {
            $n = User::where('email', 'like', 'teste%@votaflow.test')->count() + 1;
            $user = User::create([
                'name' => "Participante de teste {$n}",
                'email' => "teste{$n}-".Str::lower(Str::random(4)).'@votaflow.test',
                'google_id' => 'dev-'.Str::lower(Str::random(16)),
                'role' => Role::Participante(),
                'email_verified_at' => now(),
            ]);
        }

        $request->session()->regenerate();
        Auth::login($user);

        return redirect()->intended($user->podeAcessarPainel() ? route('admin.dashboard') : route('home'));
    }
}
