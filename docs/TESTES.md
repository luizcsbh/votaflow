# Testes

| Suíte | Comando | O que cobre |
|-------|---------|-------------|
| Unit | `php artisan test --testsuite=Unit` | máquina de estados, janela de votação, IDs/protocolo |
| Feature | `php artisan test --testsuite=Feature` | login Google (mock), QR/participação, voto, duplicidade, encerramento, resultados, permissões, API, health, erros |
| Segurança | (dentro de Feature: `SegurancaTest`, `GoogleAuthTest`) | CSRF, XSS, SQL injection, manipulação de IDs, rate limit, sessão (regeneração/cookies), OAuth (state, e-mail verificado, open redirect), cabeçalhos |
| Concorrência | `php artisan test --testsuite=Concurrency` | processos paralelos votando de verdade |

## Concorrência

`VOTAFLOW_CONC_USUARIOS=100|500|1000 php artisan test --testsuite=Concurrency`

Verifica: nenhum voto perdido, nenhuma participação/resposta duplicada, protocolos únicos, nenhuma participação sem respostas,
contagem por alternativa idêntica à enviada, limite de participantes nunca excedido, votação anônima sem vínculo.

**Banco usado**: por padrão SQLite em arquivo (WAL). SQLite tem **um único escritor**; por isso a vazão medida ali
(poucos votos/s) **não representa o MySQL**. O que o teste prova com qualquer banco são os *invariantes de integridade*.
Para validar com MySQL (recomendado antes de produção e executado no job `mysql-integration` do CI):

```bash
DB_CONNECTION=mysql DB_HOST=… DB_DATABASE=votaflow_test … VOTAFLOW_CONC_DB=env VOTAFLOW_CONC_USUARIOS=500 \
  php artisan test --testsuite=Concurrency
```
> ⚠️ Esse modo roda `migrate:fresh` no banco apontado — use somente um banco de teste.

## Carga HTTP (CPU, memória, tempo de resposta)

`tests/load/k6-votacao.js` simula o pico (rampa até N usuários virtuais, 1 voto cada). Rode contra **staging**,
com sessões de teste pré-criadas (o login Google real não é automatizável) e acompanhe CPU/memória do PHP-FPM, MySQL e Redis
(`docker stats`, `htop`, `SHOW PROCESSLIST`, `redis-cli info`).
