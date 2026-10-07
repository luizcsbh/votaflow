<?php

namespace App\Providers;

use App\Services\EstruturaVotacao;
use App\Support\Vite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EstruturaVotacao::class);
    }

    public function boot(): void
    {
        $this->registrarDiretivasBlade();

        // MySQL < 5.7.7 / MariaDB < 10.2.2 limitam índices a 767 bytes: 191 chars em utf8mb4.
        Builder::defaultStringLength(191); // estático: não abre conexão com o banco no boot

        if ($this->app->environment('production')) {
            URL::forceHttps(); // HTTPS obrigatório em produção
        }

        // IMPORTANTE: em eventos, centenas de pessoas compartilham o mesmo IP (Wi-Fi/NAT).
        // Por isso limites por IP são generosos; o limite estrito do voto é POR USUÁRIO.
        RateLimiter::for('votar', fn (Request $r) => Limit::perMinute(10)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(300)->by($r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
    }

    /**
     * Diretivas que o Laravel 8 ainda não traz (existem nativamente no 9+), mantendo as views idênticas.
     */
    private function registrarDiretivasBlade(): void
    {
        // Js::from() escapa aspas/<>/& e, para strings e objetos, devolve um literal seguro
        // dentro de atributos HTML (ex.: x-data="..." e @click="...").
        Blade::directive('js', function ($expressao) {
            return "<?php echo \\Illuminate\\Support\\Js::from({$expressao}); ?>";
        });
        Blade::directive('vite', function ($expressao) {
            return "<?php echo app('".Vite::class."')->tags({$expressao}); ?>";
        });
        foreach (['checked', 'selected', 'disabled'] as $atributo) {
            Blade::directive($atributo, function ($expressao) use ($atributo) {
                return "<?php if ({$expressao}) { echo '{$atributo}'; } ?>";
            });
        }
    }
}
