# Privacidade do voto (LGPD)

A privacidade é definida **por votação** e não pode mudar depois que a votação sai de RASCUNHO/AGENDADA.

| Modo | O que é gravado | O administrador consegue ver "João → opção X"? | Quando usar |
|------|-----------------|:----:|-------------|
| **Identificada** | `respostas.participacao_id` aponta para a participação (que aponta para o usuário) | Sim, tecnicamente (via banco/consulta) | Enquetes internas, assembleias com voto nominal |
| **Parcialmente anônima** | Mesmo vínculo no banco | **Não pela aplicação**: telas e exportações mostram só totais agregados | Quando o vínculo precisa existir para auditoria técnica, mas não deve ser consultável no dia a dia |
| **Anônima** | `respostas.participacao_id = NULL`. Existe apenas o registro "esta pessoa votou" (necessário para impedir voto duplicado) e o protocolo aleatório | **Não**, nem tecnicamente: não há dado que ligue pessoa e resposta | Eleições, pesquisas de clima, qualquer voto secreto |

## Garantias na votação anônima

* A participação e as respostas são inseridas na mesma transação, mas **sem** a chave que as liga.
* O protocolo é aleatório (não derivado de pessoa/voto) e só prova participação — **não permite reconstituir o voto**.
* Resultados são `COUNT(*) GROUP BY alternativa`; nunca há leitura individual.
* Coberto por testes: `test_votacao_anonima_nao_vincula_resposta_a_participacao` e o teste de concorrência anônima.

## Limitações honestas

* Um administrador com **acesso direto ao banco** e a logs de requisição poderia tentar correlacionar horário do voto
  (`participacoes.created_at`) com horário das respostas em votações **muito pequenas**. Para votações anônimas de
  poucas pessoas considere: não registrar `created_at` em `respostas` (já é gravado em lote no mesmo instante, então a
  correlação só ajudaria entre votos simultâneos) e restringir acesso ao banco/logs.
* Modo "parcialmente anônima" é uma barreira de **aplicação**, não criptográfica.

## Dados pessoais tratados

Nome, e-mail, foto e `google_id` (identificador do Google). Não armazenamos senha nem tokens OAuth.
A auditoria registra IP e user-agent apenas de ações administrativas e do registro do voto (protocolo), nunca o conteúdo das respostas.
