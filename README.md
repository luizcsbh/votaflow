# VotaFlow

Sistema web de **votação online via QR Code**: o participante escaneia, entra com Google, responde e confirma — *pronto*.
Projetado para **muitos votos simultâneos** sem perda, duplicidade ou inconsistência.

- PHP 7.4 · Laravel 8 · MySQL · Redis · Blade + Alpine.js · Tailwind CSS · Google OAuth 2.0
- Perguntas: escolha única, múltipla, Sim/Não, escala 1–5 (extensível)
- Privacidade por votação: **identificada**, **parcialmente anônima** ou **anônima** ([detalhes](docs/PRIVACIDADE.md))
- Painel: dashboard, QR Code (ver/baixar/imprimir/copiar), resultados (atualização automática), usuários, auditoria
- API `/api/v1`, `/health`, páginas de erro 403/404/419/429/500/503, GitLab CI/CD, Docker opcional

## Começar

```bash
composer setup && composer dev
```
Veja [docs/INSTALACAO.md](docs/INSTALACAO.md). Depois: [ARQUITETURA](docs/ARQUITETURA.md) · [PRIVACIDADE](docs/PRIVACIDADE.md) · [DEPLOY](docs/DEPLOY.md) · [TESTES](docs/TESTES.md).

## Garantias do voto (e onde estão)

| Garantia | Mecanismo | Teste |
|----------|-----------|-------|
| 1 pessoa = 1 participação | `UNIQUE(votacao_id, usuario_id)` + captura da exceção | `test_constraint_unica…`, `test_mesmo_usuario_…_em_paralelo…` |
| Tudo ou nada | `DB::transaction` (participação + respostas + contador) | `test_rollback_total_…` |
| Só vota quem pode | status ABERTA **e** janela de tempo, revalidados no backend dentro da transação | `test_nao_vota_…` |
| Nada vindo do front é confiável | alternativas/perguntas revalidadas contra a estrutura da votação | `test_alternativa_de_outra_…`, `test_pergunta_inexistente_…` |
| Limite de participantes | `UPDATE … WHERE count < limite` atômico | `test_limite_… (feature e concorrência)` |
| Anonimato real | `respostas.participacao_id = NULL` | `test_votacao_anonima_…` |
