# Compatibilidade com PHP 7.4.33

O projeto foi portado de Laravel 12 / PHP 8.2 para **Laravel 8 / PHP 7.4.33**. Laravel 8 é a última linha do framework que suporta PHP 7.4.

## Riscos assumidos (leia antes de ir para produção)

* **PHP 7.4 está sem suporte de segurança desde nov/2022** e **Laravel 8 desde jan/2023**. Vulnerabilidades novas não recebem correção. Se o servidor puder ser atualizado, prefira PHP 8.2+ / Laravel 12 (versão anterior deste projeto).
* Pint e Larastan atuais exigem PHP 8.1+. O lint do CI virou `php -l` (só sintaxe).
* O CI e o `composer.json` fixam `platform.php = 7.4.33`, para o Composer escolher só pacotes compatíveis.

## O que mudou no código

| PHP 8 / Laravel 12 | PHP 7.4 / Laravel 8 |
|---|---|
| `enum` nativo | `App\Enums\Enum` (singletons; `StatusVotacao::Aberta()`; `from`/`tryFrom`/`cases`) + cast `App\Casts\EnumCast` |
| `Rule::enum()` | `Rule::in(Enum::valores())` |
| Promoção de construtor, `readonly`, `match`, `?->`, `str_*` | Propriedades explícitas, `if`/arrays, `optional()`, `strpos` |
| `UniqueConstraintViolationException` | `QueryException` + `VotoService::violacaoDeUnicidade()` (SQLSTATE 23000/23505) |
| `bootstrap/app.php` fluente | `Http/Kernel`, `Console/Kernel`, `Handler`, `RouteServiceProvider` clássicos |
| `@vite`, `@js`, `@checked`, `@selected`, `@disabled` | Diretivas equivalentes registradas em `AppServiceProvider` (`App\Support\Vite` lê `public/build/manifest.json`) |
| `url:https` | `url` + `regex:/^https:\/\//i` |
| `CACHE_STORE`, `BROADCAST_CONNECTION` | `CACHE_DRIVER`, `BROADCAST_DRIVER` |
| PHPUnit 11 | PHPUnit 9 (`phpunit.xml` no formato 9) |

A integridade do voto (UNIQUE no banco, transação única, contador atômico de limite) não mudou.

## Frontend

Continua Vite + Tailwind v4 + Alpine (Node 20+ no build). Em produção basta `npm run build` e servir `public/build`; o PHP não precisa de Node.
