<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Participante nunca acessa o painel administrativo. */
class EnsurePainelAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && $request->user()->podeAcessarPainel(), 403);

        return $next($request);
    }
}
