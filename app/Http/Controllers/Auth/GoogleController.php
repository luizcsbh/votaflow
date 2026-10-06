<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /** @var AuditoriaService */
    private $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    public function login()
    {
        return view('auth.login');
    }

    /** Inicia OAuth 2.0 (Authorization Code + state anti-CSRF gerenciado pelo Socialite). */
    public function redirect(Request $request)
    {
        // Guarda o destino (apenas caminho relativo do próprio site: evita open redirect).
        $destino = $request->query('destino');
        if (is_string($destino) && strpos($destino, '/') === 0 && strpos($destino, '//') !== 0) {
            $request->session()->put('url.intended', $destino);
        }

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->with('erro', 'Não foi possível entrar com o Google. Tente novamente.');
        }

        $email = strtolower((string) $google->getEmail());
        $googleId = (string) $google->getId();
        $verificado = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        if ($googleId === '' || $email === '' || ! $verificado) {
            return redirect()->route('login')->with('erro', 'Sua conta Google precisa ter um e-mail verificado.');
        }

        $user = $this->localizarOuCriar($googleId, $email, (string) ($google->getName() ?: $email), $google->getAvatar());

        // Previne session fixation. Nenhum token OAuth é armazenado.
        $request->session()->regenerate();
        Auth::login($user);

        if ($user->podeAcessarPainel()) {
            $this->auditoria->registrar('LOGIN', $user);
        }

        $padrao = $user->podeAcessarPainel() ? route('admin.dashboard') : route('home');

        return redirect()->intended($padrao);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function localizarOuCriar(string $googleId, string $email, string $nome, ?string $avatar): User
    {
        // 1) Identificação principal: google_id (nunca só o e-mail).
        $user = User::where('google_id', $googleId)->first();

        // 2) Usuário pré-cadastrado por um admin (sem google_id ainda): vincula pelo e-mail verificado.
        if (! $user) {
            $user = User::where('email', $email)->whereNull('google_id')->first();
        }

        if (! $user) {
            $user = new User(['email' => $email, 'role' => in_array($email, config('votaflow.admin_emails'), true) ? Role::Admin() : Role::Participante()]);
        }

        $user->fill(['name' => $nome, 'avatar' => $avatar, 'google_id' => $googleId, 'email_verified_at' => $user->email_verified_at ?? now()]);
        // Se o e-mail mudou no Google, mantém o vínculo pelo google_id e atualiza o e-mail.
        if ($user->exists && $user->email !== $email && ! User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $user->email = $email;
        }
        $user->save();

        return $user;
    }
}
