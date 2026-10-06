<?php

namespace App\Providers;

use App\Services\EstruturaVotacao;
use App\Support\Vite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

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
        $flags = 'JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE';

        // Seguro dentro de atributos HTML e <script>: aspas, <, > e & saem como \uXXXX.
        Blade::directive('js', function ($expressao) use ($flags) {
            return "<?php echo json_encode({$expressao}, {$flags}); ?>";
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
