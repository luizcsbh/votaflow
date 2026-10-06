# PROMPT — SISTEMA DE VOTAÇÃO ONLINE

## 1. Objetivo

Criar um sistema web moderno de **votação online**, permitindo que várias pessoas participem simultaneamente de uma votação através de um **QR Code**.

O sistema deverá ser simples para o participante:

1. Escanear o QR Code.
2. Acessar a votação.
3. Entrar utilizando sua conta Google.
4. Visualizar as informações da votação.
5. Responder às perguntas.
6. Confirmar o voto.
7. Receber uma confirmação de participação.

O sistema deve ser preparado para suportar **muitos usuários votando simultaneamente**, sem perda de respostas, duplicidade de votos ou inconsistências nos resultados.

---

# 2. Stack tecnológica

Utilizar preferencialmente:

- Backend: Laravel
- PHP: versão estável suportada pelo Laravel utilizado
- Banco de dados: MySQL
- Frontend: Blade + Livewire/Alpine.js ou arquitetura equivalente
- CSS: Tailwind CSS
- Autenticação: Google OAuth 2.0
- QR Code: biblioteca confiável para geração de QR Codes
- Cache: Redis, quando disponível
- Filas: Laravel Queue
- Web Server: Apache ou Nginx
- Testes: PHPUnit/Pest
- CI/CD: GitLab CI/CD
- Containerização: opcional, mas estruturar o projeto para permitir Docker futuramente

A arquitetura deve ser organizada para permitir crescimento futuro.

---

# 3. Conceito do sistema

O sistema deverá possuir dois ambientes principais:

## 3.1. Área administrativa

Responsável pela criação e gerenciamento das votações.

## 3.2. Área do participante

Área extremamente simples, responsiva e intuitiva para responder à votação.

O participante não deve precisar navegar por menus complexos.

O fluxo principal deve ser:

```text
QR Code
   ↓
Página da votação
   ↓
Login com Google
   ↓
Confirmação de identidade
   ↓
Perguntas
   ↓
Confirmação das respostas
   ↓
Finalização
   ↓
Comprovante de participação
```

---

# 4. Votações

O administrador deverá conseguir criar uma votação contendo:

- título;
- descrição;
- imagem opcional;
- data/hora de início;
- data/hora de encerramento;
- status;
- identificação da votação;
- perguntas;
- alternativas;
- ordem das perguntas;
- obrigatoriedade da resposta;
- limite de participantes, opcional;
- permitir ou não alteração da resposta antes da finalização;
- permitir ou não voto anônimo nos relatórios.

Status possíveis:

```text
RASCUNHO
AGENDADA
ABERTA
ENCERRADA
CANCELADA
```

---

# 5. Tipos de perguntas

Criar arquitetura preparada para diferentes tipos de perguntas.

Inicialmente implementar:

### Escolha única

Exemplo:

```text
Qual candidato você prefere?

○ Candidato A
○ Candidato B
○ Candidato C
○ Branco
```

### Múltipla escolha

```text
Quais opções você considera importantes?

☐ Opção A
☐ Opção B
☐ Opção C
```

### Sim / Não

```text
Você concorda com a proposta?

○ Sim
○ Não
```

### Escala

Exemplo:

```text
Avalie de 1 a 5:

1  2  3  4  5
```

A arquitetura deve permitir adicionar novos tipos posteriormente sem necessidade de reescrever o sistema.

---

# 6. QR Code

Cada votação deverá possuir um QR Code exclusivo.

O administrador deverá conseguir:

- visualizar o QR Code;
- baixar o QR Code;
- imprimir o QR Code;
- copiar o link da votação;
- gerar uma versão adequada para impressão;
- compartilhar o link.

O QR Code deverá apontar para uma URL pública da votação.

Exemplo:

```text
https://votacao.exemplo.com/v/ABC123
```

Utilizar um identificador público seguro.

Não expor IDs sequenciais do banco de dados.

---

# 7. Login com Google

O participante deverá utilizar sua conta Google.

Implementar OAuth 2.0 de forma segura.

Após autenticar:

- obter nome;
- e-mail;
- identificador único fornecido pelo Google;
- foto, quando disponível.

Não confiar somente no e-mail para identificar o usuário.

Criar uma identificação interna própria.

Não armazenar tokens OAuth desnecessariamente.

Nunca armazenar senha do Google.

---

# 8. Controle de participação

Um usuário autenticado deve conseguir votar de acordo com as regras definidas para aquela votação.

Por padrão:

```text
1 usuário = 1 participação por votação
```

Criar restrição única no banco de dados para impedir votos duplicados.

Exemplo conceitual:

```text
UNIQUE(votacao_id, usuario_id)
```

Essa regra deve ser garantida pelo banco de dados e não apenas pelo frontend.

---

# 9. Fluxo do participante

## Etapa 1 — QR Code

Usuário escaneia o QR Code.

---

## Etapa 2 — Apresentação

Mostrar:

```text
[Logo]

Votação

Título da votação

Descrição

[ Participar da votação ]
```

Se o usuário não estiver autenticado:

```text
Continuar com Google
```

---

## Etapa 3 — Login

Mostrar uma interface simples:

```text
Participe da votação

Para participar, entre com sua conta Google.

[ Continuar com Google ]
```

---

## Etapa 4 — Início

Após autenticação:

```text
Olá, João!

Você está participando de:

Votação 2026

Tempo estimado: 2 minutos

[ Começar ]
```

---

# 10. Interface da votação

Criar uma interface extremamente simples.

Exemplo:

```text
Votação 2026

Pergunta 2 de 5

━━━━━━━━━━━━━━░░░░░░

Qual opção você prefere?

○ Opção A

○ Opção B

○ Opção C


[ Voltar ]       [ Próxima ]
```

Mostrar claramente o progresso.

Exemplo:

```text
Pergunta 3 de 10
30% concluído
```

No celular, os botões devem ser grandes e fáceis de tocar.

---

# 11. Confirmação final

Antes de registrar definitivamente o voto, apresentar:

```text
Confira suas respostas

Pergunta 1
Resposta: Opção A

Pergunta 2
Resposta: Sim

Pergunta 3
Resposta: Opção C

--------------------------------

Depois de confirmar, suas respostas
serão registradas.

[ Voltar e corrigir ]

[ CONFIRMAR VOTO ]
```

Após confirmação, o voto deve ser registrado de maneira transacional.

---

# 12. Confirmação de participação

Após votar:

```text
✓ Voto registrado!

Obrigado pela sua participação.

Sua participação foi registrada com sucesso.

Código de confirmação:

ABC8-X92K

[ Voltar para início ]
```

O código de confirmação deve ser aleatório e não revelar informações sensíveis.

---

# 13. Segurança da votação

Implementar obrigatoriamente:

- CSRF;
- proteção contra XSS;
- validação de todas as entradas;
- proteção contra SQL Injection;
- autorização por políticas;
- rate limiting;
- proteção contra brute force;
- sessões seguras;
- cookies Secure;
- cookies HttpOnly;
- SameSite adequado;
- HTTPS obrigatório em produção;
- validação OAuth;
- proteção contra replay;
- controle de acesso;
- logs de segurança;
- auditoria das ações administrativas.

Nunca confiar em dados enviados pelo frontend.

Toda operação importante deve ser validada no backend.

---

# 14. Concorrência

Este é um requisito crítico.

O sistema deverá suportar várias pessoas votando simultaneamente.

Exemplo:

```text
10 pessoas
100 pessoas
1.000 pessoas
5.000 pessoas
10.000 pessoas
```

O sistema deve ser desenvolvido pensando em concorrência.

Evitar:

- carregar todos os votos em memória;
- consultas desnecessárias;
- N+1 queries;
- bloqueios prolongados;
- operações síncronas desnecessárias;
- contadores atualizados de forma insegura.

Utilizar:

- índices adequados;
- transações;
- constraints;
- filas quando necessário;
- cache;
- paginação;
- consultas agregadas;
- processamento assíncrono para relatórios pesados.

---

# 15. Registro do voto

O processo de confirmação deverá ser transacional.

Conceito:

```text
BEGIN TRANSACTION

validar votação
validar participante
validar elegibilidade
verificar duplicidade
registrar participação
registrar respostas
gerar protocolo

COMMIT
```

Se ocorrer qualquer erro:

```text
ROLLBACK
```

Nunca permitir uma situação em que:

```text
participação = registrada
respostas = parcialmente registradas
```

---

# 16. Banco de dados

Criar estrutura organizada.

Tabelas sugeridas:

### users

Usuários autenticados.

Campos:

```text
id
name
email
google_id
avatar
email_verified_at
created_at
updated_at
```

### votacoes

```text
id
public_id
titulo
descricao
imagem
status
inicio_em
fim_em
created_by
created_at
updated_at
```

### perguntas

```text
id
votacao_id
tipo
titulo
descricao
ordem
obrigatoria
created_at
updated_at
```

### alternativas

```text
id
pergunta_id
texto
ordem
created_at
updated_at
```

### participacoes

```text
id
votacao_id
usuario_id
protocolo
iniciado_em
finalizado_em
created_at
updated_at
```

Criar:

```text
UNIQUE(votacao_id, usuario_id)
```

### respostas

```text
id
participacao_id
pergunta_id
alternativa_id
valor
created_at
updated_at
```

### auditorias

Registrar eventos importantes:

```text
id
usuario_id
votacao_id
acao
ip
user_agent
dados
created_at
```

Evitar registrar informações sensíveis desnecessariamente.

---

# 17. Privacidade

O sistema deve respeitar princípios de privacidade e LGPD.

Separar:

```text
IDENTIDADE DO PARTICIPANTE
```

de:

```text
RESPOSTA / VOTO
```

quando a configuração da votação exigir anonimato.

O administrador não deve conseguir visualizar indevidamente:

```text
João → votou na opção X
```

quando a votação estiver configurada como anônima.

Criar arquitetura que permita:

```text
Votação identificada
Votação parcialmente anônima
Votação anônima
```

Documentar claramente as diferenças.

---

# 18. Dashboard administrativo

Criar dashboard moderno.

Exibir:

```text
Votações

[ 12 ] Votações criadas
[ 3  ] Abertas
[ 7  ] Encerradas
[ 2  ] Agendadas
```

Para uma votação aberta:

```text
Votação: Eleição 2026

Participantes
████████████████░░░░  78%

1.245 participantes

Início: 09:00
Fim: 17:00

[ Abrir votação ]
[ QR Code ]
[ Resultados ]
[ Configurações ]
```

---

# 19. Resultados

Criar página de resultados.

Exemplo:

```text
RESULTADO

Candidato A
███████████████ 45%

Candidato B
██████████ 32%

Candidato C
███████ 23%
```

Exibir:

- total de participantes;
- total de votos;
- percentual;
- respostas por alternativa;
- perguntas;
- taxa de participação;
- evolução de participantes em tempo real, quando aplicável.

Resultados devem ser carregados de forma eficiente.

Não executar consultas pesadas a cada atualização da tela.

Utilizar cache quando apropriado.

---

# 20. Atualização em tempo real

Preparar arquitetura para atualização em tempo real.

Quando uma pessoa votar:

```text
Participantes: 1.241
```

poderá atualizar para:

```text
Participantes: 1.242
```

sem precisar recarregar toda a página.

Utilizar WebSockets/Broadcasting ou tecnologia equivalente quando necessário.

Essa funcionalidade deve ser desacoplada do processo principal de registro do voto.

---

# 21. Design / UX

Criar um layout moderno, limpo e profissional.

Prioridade:

```text
SIMPLICIDADE
CLAREZA
VELOCIDADE
ACESSIBILIDADE
```

Utilizar:

- cards;
- espaçamento adequado;
- tipografia moderna;
- ícones;
- feedback visual;
- animações discretas;
- barra de progresso;
- botões grandes;
- estados de carregamento;
- mensagens de erro claras.

Evitar excesso de informações.

---

# 22. Mobile First

A maior parte dos participantes poderá acessar pelo celular após escanear o QR Code.

Portanto:

```text
Mobile First
```

A interface deve funcionar perfeitamente em:

- Android;
- iPhone;
- tablets;
- notebooks;
- desktops.

O fluxo de votação deve ser confortável utilizando apenas uma mão no celular.

---

# 23. Acessibilidade

Implementar:

- contraste adequado;
- navegação por teclado;
- labels apropriados;
- foco visível;
- leitores de tela;
- textos claros;
- tamanho adequado dos elementos;
- não depender apenas de cores para transmitir informações.

Buscar compatibilidade com WCAG 2.1 AA.

---

# 24. Administração de usuários

Criar controle de acesso.

Perfis sugeridos:

```text
ADMIN
OPERADOR
PARTICIPANTE
```

O administrador poderá:

- criar votação;
- editar votação;
- abrir votação;
- encerrar votação;
- cancelar votação;
- visualizar resultados;
- gerar QR Code;
- gerenciar usuários administrativos.

O operador poderá ter permissões limitadas.

O participante não terá acesso ao painel administrativo.

Utilizar Laravel Policies/Gates e middleware.

---

# 25. Auditoria

Registrar ações administrativas:

```text
LOGIN
CRIAR_VOTACAO
EDITAR_VOTACAO
ABRIR_VOTACAO
ENCERRAR_VOTACAO
CANCELAR_VOTACAO
ALTERAR_CONFIGURACAO
EXPORTAR_RESULTADOS
```

Registrar:

- usuário;
- ação;
- data/hora;
- votação;
- IP;
- user agent;
- informações relevantes.

Não registrar senhas, tokens OAuth ou informações sensíveis.

---

# 26. Performance

O sistema deverá ser projetado para alta concorrência.

Implementar:

- índices de banco;
- cache;
- Redis quando disponível;
- filas;
- paginação;
- eager loading;
- consultas SQL eficientes;
- connection pooling quando aplicável;
- rate limiting;
- otimização de assets;
- compressão;
- lazy loading quando adequado.

Criar testes de carga para simular vários usuários votando ao mesmo tempo.

---

# 27. Testes

Criar testes automatizados.

## Testes unitários

Testar:

- criação de votação;
- abertura;
- encerramento;
- validação de perguntas;
- cálculo de resultados;
- regras de participação.

## Testes de feature

Testar:

```text
login
criação de votação
acesso via QR Code
participação
registro de voto
duplicidade
encerramento
resultados
permissões
```

## Testes de segurança

Testar:

```text
CSRF
XSS
SQL Injection
acesso não autorizado
duplicidade
manipulação de IDs
rate limiting
sessão
OAuth
```

## Testes de concorrência

Simular:

```text
100 usuários votando simultaneamente
500 usuários votando simultaneamente
1.000 usuários votando simultaneamente
```

Verificar:

- nenhum voto perdido;
- nenhuma resposta duplicada;
- nenhuma participação duplicada;
- integridade dos resultados;
- tempo de resposta;
- utilização de CPU;
- utilização de memória;
- banco de dados.

---

# 28. CI/CD GitLab

Criar pipeline GitLab CI/CD.

Etapas:

```text
lint
 ↓
test
 ↓
security
 ↓
build
 ↓
deploy
```

O pipeline deve executar automaticamente:

- instalação das dependências;
- testes;
- análise estática;
- verificações de segurança;
- migrations em ambiente apropriado;
- build dos assets;
- deploy.

Nunca colocar:

- senhas;
- tokens;
- credenciais;
- chaves privadas

diretamente no código.

Utilizar GitLab CI/CD Variables.

---

# 29. Docker

Estruturar o projeto para poder utilizar:

```text
PHP
MySQL
Redis
Nginx
Node
```

via Docker Compose.

Não tornar Docker obrigatório para desenvolvimento inicial, caso isso complique desnecessariamente o projeto.

---

# 30. API

Mesmo que o primeiro frontend utilize Blade/Livewire, organizar a aplicação para permitir uma API futura.

Exemplo:

```text
/api/v1/votacoes
/api/v1/votacoes/{public_id}
/api/v1/votacoes/{public_id}/participar
/api/v1/votacoes/{public_id}/responder
/api/v1/votacoes/{public_id}/finalizar
/api/v1/votacoes/{public_id}/resultados
```

Utilizar API Resources e validações.

---

# 31. Tratamento de erros

Nunca apresentar erros técnicos para o usuário.

Em caso de erro:

```text
Não foi possível registrar sua participação.

Verifique sua conexão e tente novamente.
```

Registrar detalhes técnicos nos logs.

Criar páginas:

```text
404
403
419
429
500
503
```

com layout consistente.

---

# 32. Estados da votação

Garantir que uma votação:

```text
RASCUNHO
↓
AGENDADA
↓
ABERTA
↓
ENCERRADA
```

não possa voltar para estados incompatíveis sem uma ação administrativa explícita.

Impedir respostas quando:

```text
votacao.status != ABERTA
```

ou quando estiver fora do período permitido.

Essa validação deve ocorrer no backend.

---

# 33. Proteção contra voto duplicado

Mesmo que dois dispositivos enviem simultaneamente:

```text
POST /votar
POST /votar
```

o sistema deverá garantir que apenas uma participação seja registrada.

Não depender somente de:

```php
if (!$participacao) {
    criar();
}
```

porque isso pode apresentar condição de corrida.

Utilizar:

- unique constraints;
- transactions;
- tratamento de exceções;
- locks quando necessários.

---

# 34. Escalabilidade

A arquitetura deverá permitir futuramente:

```text
1 servidor
      ↓
Load Balancer
      ↓
Servidor 1
Servidor 2
Servidor 3
      ↓
Redis
      ↓
MySQL
```

O sistema não deve depender de arquivos locais para armazenar estado crítico da votação.

Sessões, cache e arquivos devem poder ser externalizados.

---

# 35. Observabilidade

Preparar:

- logs estruturados;
- monitoramento de erros;
- métricas;
- health check;
- monitoramento de filas;
- monitoramento do banco;
- monitoramento de Redis;
- métricas de tempo de resposta.

Criar endpoint:

```text
/health
```

retornando o estado básico da aplicação.

---

# 36. Interface administrativa

Criar menu:

```text
Dashboard

Votações
 ├── Todas
 ├── Criar votação
 ├── Abertas
 ├── Agendadas
 └── Encerradas

Resultados

Usuários

Auditoria

Configurações
```

---

# 37. Interface do participante

Não apresentar o menu administrativo.

A interface deve ser focada exclusivamente na votação.

Exemplo visual:

```text
┌───────────────────────────────┐
│                               │
│          LOGO                 │
│                               │
│     Votação Escolar 2026      │
│                               │
│  Sua opinião é importante!    │
│                               │
│  ┌─────────────────────────┐  │
│  │  Continuar com Google   │  │
│  └─────────────────────────┘  │
│                               │
└───────────────────────────────┘
```

Durante a votação:

```text
┌───────────────────────────────┐
│ Votação 2026                  │
│                               │
│ Pergunta 2 de 5               │
│ ████████████░░░░░░            │
│                               │
│ Qual opção você prefere?      │
│                               │
│ ┌───────────────────────────┐ │
│ │ ○ Opção A                 │ │
│ └───────────────────────────┘ │
│                               │
│ ┌───────────────────────────┐ │
│ │ ○ Opção B                 │ │
│ └───────────────────────────┘ │
│                               │
│          [ Próxima ]           │
└───────────────────────────────┘
```

---

# 38. Princípios de desenvolvimento

Durante a implementação:

1. Não criar código desnecessariamente complexo.
2. Utilizar padrões do Laravel.
3. Aplicar SOLID quando fizer sentido.
4. Evitar duplicação.
5. Criar Services apenas quando houver responsabilidade real.
6. Utilizar Form Requests.
7. Utilizar Policies.
8. Utilizar Events/Listeners quando fizer sentido.
9. Criar Jobs para tarefas pesadas.
10. Criar testes para regras críticas.
11. Não colocar regra de negócio importante no JavaScript.
12. Validar tudo no backend.
13. Utilizar migrations versionadas.
14. Criar seeders para ambiente de desenvolvimento.
15. Documentar decisões arquiteturais importantes.

---

# 39. Critério principal de qualidade

O sistema deve funcionar muito bem no seguinte cenário:

```text
Um evento possui um QR Code.

1.000 pessoas escaneiam o QR Code.

As 1.000 pessoas entram na votação.

As 1.000 pessoas fazem login com Google.

As 1.000 pessoas respondem às perguntas.

As 1.000 pessoas confirmam o voto em períodos próximos.

O sistema registra corretamente todas as participações.

Nenhum voto é duplicado.

Nenhuma resposta é perdida.

Nenhum usuário consegue votar duas vezes.

Os resultados permanecem consistentes.
```

Esse cenário deve ser considerado requisito fundamental do projeto.

---

# 40. Entregáveis

Ao finalizar o desenvolvimento, entregar:

- sistema funcional;
- migrations;
- models;
- controllers;
- services;
- policies;
- middleware;
- views;
- componentes frontend;
- autenticação Google;
- geração de QR Code;
- dashboard;
- resultados;
- auditoria;
- testes automatizados;
- testes de concorrência;
- documentação;
- `.env.example`;
- Docker opcional;
- GitLab CI/CD;
- documentação de instalação;
- documentação de deploy;
- documentação de arquitetura.

---

# 41. Regra final

Antes de implementar qualquer funcionalidade:

1. analisar a arquitetura existente;
2. identificar impactos;
3. criar ou atualizar os testes;
4. implementar;
5. executar testes;
6. verificar segurança;
7. verificar performance;
8. verificar comportamento em concorrência;
9. verificar responsividade;
10. documentar quando necessário.

Priorizar sempre:

**SEGURANÇA + INTEGRIDADE DO VOTO + CONCORRÊNCIA + SIMPLICIDADE + USABILIDADE.**

O resultado final deve parecer um produto profissional de votação online, e não apenas um CRUD.

A experiência do participante deve ser extremamente simples:

**Escaneou → entrou com Google → respondeu → confirmou → pronto.**