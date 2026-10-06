<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $q = User::query()->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'operador' THEN 1 ELSE 2 END")->orderBy('name');
        if ($busca = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$busca}%")->orWhere('email', 'like', "%{$busca}%"));
        }

        return view('admin.usuarios.index', ['usuarios' => $q->paginate(20)->withQueryString(), 'roles' => Role::cases()]);
    }

    /** Pré-cadastra um e-mail com perfil; o vínculo com o Google acontece no primeiro login. */
    public function store(Request $request, AuditoriaService $auditoria)
    {
        $this->authorize('viewAny', User::class);
        $dados = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(Role::valores())],
        ]);
        $email = strtolower($dados['email']);
        $user = User::create(['email' => $email, 'name' => ($dados['name'] ?? null) ?: $email, 'role' => $dados['role']]);
        $auditoria->registrar('ALTERAR_CONFIGURACAO', $request->user(), null, ['usuario' => $user->id, 'role' => $dados['role'], 'tipo' => 'pre_cadastro']);

        return back()->with('ok', 'Usuário cadastrado. O acesso é liberado no primeiro login com Google.');
    }

    public function update(Request $request, User $usuario, AuditoriaService $auditoria)
    {
        $this->authorize('update', $usuario);
        $dados = $request->validate(['role' => ['required', Rule::in(Role::valores())]]);
        $anterior = $usuario->role->value;
        $usuario->update(['role' => $dados['role']]);
        $auditoria->registrar('ALTERAR_CONFIGURACAO', $request->user(), null, ['usuario' => $usuario->id, 'de' => $anterior, 'para' => $dados['role']]);

        return back()->with('ok', 'Perfil atualizado.');
    }
}
