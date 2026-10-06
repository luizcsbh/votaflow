# Instalação (desenvolvimento)

Requisitos: PHP 7.4.33 (veja [PHP74.md](PHP74.md)) (extensões: pdo_sqlite ou pdo_mysql, mbstring, intl, pcntl para o teste de concorrência), Composer 2, Node 20+.

```bash
composer setup          # instala deps, cria .env, gera APP_KEY, migra + popula (seeders de DEV), compila assets
composer dev            # servidor + fila + agendador + Vite
```

## Login com Google (obrigatório para votar)

1. [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → *Create credentials → OAuth client ID → Web application*.
2. **Authorized redirect URI**: `http://localhost:8000/auth/google/callback` (e a URL de produção).
3. Preencha no `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` e `VOTAFLOW_ADMIN_EMAILS=seu@gmail.com`
   (esse e-mail vira **ADMIN** no primeiro login; depois cadastre operadores em *Usuários*).

## Docker (opcional)

```bash
cp .env.example .env && docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```
Sobe PHP-FPM, Nginx (`:8000`), MySQL 8.4, Redis, worker de fila e agendador.

## Comandos úteis

| Comando | Para quê |
|---------|----------|
| `composer test` | Unit + Feature (SQLite em memória) |
| `composer test:concurrency` | Votação simultânea real (processos paralelos) |
| `php artisan votaflow:sincronizar-status` | Abre/encerra votações por data (roda sozinho a cada minuto via `schedule:work`/cron) |
