# Deploy em produção

## Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` (HTTPS é forçado e cookies viram `Secure`).
- [ ] MySQL 8+ (InnoDB). `DB_*` via variáveis de ambiente / GitLab CI Variables — **nunca** no repositório.
- [ ] Redis: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`.
- [ ] `GOOGLE_CLIENT_ID/SECRET` e redirect URI de produção cadastrada no Google.
- [ ] `VOTAFLOW_ADMIN_EMAILS` com o(s) primeiro(s) admin(s).
- [ ] `TRUSTED_PROXIES` se houver load balancer/CDN.
- [ ] Worker: `php artisan queue:work --queue=broadcasts,default --tries=3` sob Supervisor/systemd.
- [ ] Cron: `* * * * * php /caminho/artisan schedule:run` (abre/encerra votações por data).
- [ ] Após cada deploy: `php artisan migrate --force && php artisan optimize && php artisan queue:restart`.
- [ ] Monitorar `GET /health`.

## Capacidade (para o cenário de 1.000+ votantes)

* **PHP-FPM**: `pm.max_children` ≈ (RAM disponível ÷ ~60 MB). 1.000 votantes em poucos minutos são ~10–20 req/s no pico: 1 servidor de 2–4 vCPU atende com folga.
* **MySQL**: `innodb_flush_log_at_trx_commit=1` (durabilidade do voto), `max_connections` ≥ `pm.max_children` × nº de servidores.
* **Opcache** ligado; `php artisan optimize` (config/route/view cache).
* **Wi-Fi do evento**: a rede local costuma ser o gargalo — prefira dados móveis/4G de reserva e CDN para os assets (`public/build`).

## Pipeline GitLab (`.gitlab-ci.yml`)

`lint` (`php -l`) → `test` (Unit/Feature, concorrência SQLite, **concorrência MySQL**) → `security` (composer audit, detecção de segredos, SAST/Dependency/Secret scanning) → `build` (assets + release) → `deploy` (manual, via SSH).
Variáveis (Settings → CI/CD → Variables, *masked/protected*): `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, `SSH_PRIVATE_KEY` (tipo *File*).

## Escalando para vários servidores

Load Balancer → N × (Nginx + PHP-FPM) → Redis (sessão/cache/fila) → MySQL. Nada crítico é gravado em disco local.
Rode **um** scheduler (`onOneServer` já protege) e quantos workers precisar.
