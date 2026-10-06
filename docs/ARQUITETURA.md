# Arquitetura do VotaFlow

## Visão geral

```
Participante ──QR──▶ /v/{public_id} ──Google OAuth──▶ wizard (Alpine) ──POST──▶ VotoService (transação)
Admin/Operador ───▶ /admin/*  (policies)                                          │
                                                       ┌──────────────────────────┤
                                            participacoes (UNIQUE votacao+usuario) + respostas
                                                       │ após COMMIT
                                      auditoria + ParticipacaoRegistrada (fila "broadcasts")
```

PHP 7.4 · Laravel 8 · Blade + Alpine.js · Tailwind v4 · Socialite (Google) · MySQL · Redis (cache/sessão/fila) · simple-qrcode (SVG).

## Decisões importantes (ADRs resumidos)

| # | Decisão | Motivo |
|---|---------|--------|
| 1 | **Blade + Alpine** em vez de Livewire | O wizard roda 100% no navegador e envia **um único POST** no fim. Menos requisições por votante (1.000 pessoas ⇒ 1.000 POSTs, não milhares de round-trips do Livewire). Nenhuma regra de negócio no JS. |
| 2 | **Participação só é criada na confirmação** | Não há "voto pela metade": respostas não ficam gravadas por etapa; tudo (participação + respostas) entra numa transação. |
| 3 | **Unicidade no banco** `UNIQUE(votacao_id, usuario_id)` | Corrida entre dois dispositivos é resolvida pelo banco; a aplicação captura `UniqueConstraintViolationException`. Nunca usamos `if (!exists) create`. |
| 4 | **Limite de participantes via `UPDATE … WHERE count < limite`** | Incremento atômico condicional (sem `SELECT` + `UPDATE`), feito no fim da transação para segurar o lock da linha o mínimo. Só é usado quando há limite. |
| 5 | **Estrutura da votação em cache** (`EstruturaVotacao`) | Milhares de votantes não geram milhares de consultas de perguntas/alternativas. A chave inclui `updated_at`, então editar a votação invalida o cache sozinho. |
| 6 | **Resultados por `GROUP BY` único + cache curto** | 1 consulta agregada (índice `respostas(pergunta_id, alternativa_id)`), 5 s de TTL com votação aberta, 30 min depois de encerrada. Nunca carrega votos em memória. |
| 7 | **Broadcast desacoplado** | `ParticipacaoRegistrada` é `ShouldBroadcast` na fila `broadcasts`; o voto nunca espera por WebSocket. Sem Reverb/Pusher instalado, o painel usa polling leve (5 s) sobre JSON em cache. |
| 8 | **Status validado no backend, a cada voto** | `Votacao::aceitaVotos()` confere status **e** janela de tempo dentro da transação. O agendador (`votaflow:sincronizar-status`, 1/min) apenas mantém o status coerente. |
| 9 | **IDs públicos aleatórios** (`public_id` 8 chars, alfabeto sem ambíguos) e protocolo aleatório | Sem IDs sequenciais expostos; protocolo não revela pessoa nem voto. |
| 10 | **Rate limit por usuário no voto, generoso por IP** | Em eventos centenas de pessoas dividem o mesmo IP (Wi-Fi/NAT). Limitar por IP bloquearia votantes legítimos. |

## Tipos de pergunta (extensível)

`App\Enums\TipoPergunta` define o tipo e, quando aplicável, alternativas fixas (Sim/Não, escala 1–5).
Escolha única e múltipla usam alternativas livres. Todas as respostas são normalizadas como
`[pergunta_id => [alternativa_id, …]]` e validadas por `VotoService::validarRespostas`.
**Para adicionar um tipo** (ex.: texto livre): (1) novo `case` no enum, (2) regra de validação em
`validarRespostas` (usa a coluna `respostas.valor`, já existente), (3) componente na view do wizard e nos resultados.
Nenhuma migration é necessária.

## Máquina de estados

```
RASCUNHO ─▶ AGENDADA ─▶ ABERTA ─▶ ENCERRADA
    │           │          │           │
    └───────────┴──────────┴─▶ CANCELADA (terminal)      ENCERRADA ─▶ ABERTA (reabertura: só ADMIN)
```
Definida em `StatusVotacao::transicoesPermitidas()`, aplicada por `VotacaoService::transicionar()` com `lockForUpdate`.
Edição estrutural (perguntas/alternativas) só em RASCUNHO/AGENDADA; em ABERTA só `fim_em` e limite.

## Perfis e permissões

| Ação | ADMIN | OPERADOR | PARTICIPANTE |
|------|:----:|:--------:|:------------:|
| Criar votação | ✔ | ✔ | — |
| Editar / abrir / encerrar / ver resultados / QR | ✔ (todas) | ✔ (só as próprias) | — |
| Cancelar, reabrir, exportar CSV | ✔ | — | — |
| Usuários, auditoria, configurações | ✔ | — | — |

Implementado com `VotacaoPolicy`, `UserPolicy` e o middleware `painel`.

## API v1

`/api/v1/votacoes[/{public_id}][/participar|/responder|/finalizar|/resultados]`. Usa a **sessão do mesmo domínio**
(cookie + `X-XSRF-TOKEN`). Para clientes externos (app mobile/SPA em outro domínio) adicione Laravel Sanctum
(tokens); os controllers e Resources já estão prontos para isso.

## Escalabilidade horizontal

Estado crítico **não** fica em arquivos locais: sessão, cache e fila em Redis (ou banco); QR é gerado sob demanda;
imagens são URLs externas. Atrás de load balancer defina `TRUSTED_PROXIES`. O agendador usa `onOneServer()`
(requer cache compartilhado, ex.: Redis).

## Observabilidade

* `GET /health` → JSON com estado do banco, cache e fila; HTTP 503 se banco ou cache falharem. Use em load balancer/uptime.
* `GET /up` → liveness mínimo (200 "OK", sem tocar no banco).
* Logs estruturados: configure `LOG_CHANNEL=stderr` + `LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter` (Docker/Kubernetes).
* Erros: integre Sentry/Flare via `report()` (todos os erros inesperados já passam por `report($e)`).
* Métricas recomendadas: latência p95 de `POST /v/*/votar`, tamanho da fila (`/health`), conexões MySQL, memória do Redis.
