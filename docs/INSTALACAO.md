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

## Testar no celular pela mesma Wi-Fi (sem túnel e sem Google)

1. No `.env`: `APP_ENV=local` e `VOTAFLOW_DEV_LOGIN=true`; deixe `VOTAFLOW_PUBLIC_URL` vazio. Rode `php artisan config:clear`.
2. `composer dev` (escuta em `0.0.0.0:8000`). Na primeira vez o Windows pergunta sobre o firewall: permita em **redes privadas**.
3. Descubra o IP do PC: `ipconfig` → *Endereço IPv4* (ex.: `192.168.0.15`). PC e celular precisam estar na **mesma Wi-Fi** (sem "isolamento de clientes").
4. No PC, abra `http://192.168.0.15:8000`, entre com **"Entrar como usuário de teste (dev)"** → *Admin* e abra o QR Code de uma votação aberta. O QR já sai com o IP do PC.
5. No celular, aponte a câmera para o QR Code (ou digite o link), toque em **"Entrar como usuário de teste"** → *Novo participante de teste*, responda e confirme.

O login de teste responde 404 fora de `APP_ENV=local`; nunca ligue em produção. Para validar o login real com Google no celular, use a seção seguinte.

## Testar no celular com Google (links e QR Code)

`localhost` e IPs de rede (192.168.x.x) **não funcionam** para o participante: o celular não os alcança e o Google não aceita
IP privado nem HTTP puro como redirecionamento. Use um endereço HTTPS público:

1. Suba o app: `composer dev` (já escuta em `0.0.0.0:8000`).
2. Abra um túnel HTTPS, por exemplo `cloudflared tunnel --url http://localhost:8000` ou `ngrok http 8000`. Anote a URL (`https://xxxx.trycloudflare.com`).
3. No `.env`: `APP_URL=<url>`, `VOTAFLOW_PUBLIC_URL=<url>`, `GOOGLE_REDIRECT_URI=<url>/auth/google/callback` e `TRUSTED_PROXIES=*`.
4. No Google Cloud Console, acrescente `<url>/auth/google/callback` às *URIs de redirecionamento autorizadas*.
5. `php artisan config:clear`, recarregue o painel: o aviso amarelo some quando o link é alcançável por celular.

Em produção basta `APP_URL=https://seu-dominio` (e opcionalmente `VOTAFLOW_PUBLIC_URL` se o painel for acessado por outro endereço).

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
