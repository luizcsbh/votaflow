<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [];
        $inicio = microtime(true);

        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            report($e);
            $checks['database'] = 'falha';
        }

        try {
            Cache::put('health', 1, 10);
            $checks['cache'] = Cache::get('health') === 1 ? 'ok' : 'falha';
        } catch (\Throwable $e) {
            report($e);
            $checks['cache'] = 'falha';
        }

        try {
            if (config('queue.default') === 'database') {
                $checks['fila_pendente'] = DB::table(config('queue.connections.database.table', 'jobs'))->count();
            }
        } catch (\Throwable $e) {
            $checks['fila_pendente'] = 'indisponivel';
        }

        $ok = $checks['database'] === 'ok' && $checks['cache'] === 'ok';

        return response()->json([
            'status' => $ok ? 'ok' : 'degradado',
            'checks' => $checks,
            'tempo_ms' => round((microtime(true) - $inicio) * 1000, 1),
            'hora' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
