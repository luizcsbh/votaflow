<?php

return [
    /*
    | E-mails que viram ADMIN automaticamente no primeiro login com Google (bootstrap do sistema).
    | Separados por vírgula em VOTAFLOW_ADMIN_EMAILS.
    */
    'admin_emails' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('VOTAFLOW_ADMIN_EMAILS', ''))
    ))),

    // Estimativa exibida ao participante: segundos por pergunta.
    'segundos_por_pergunta' => 20,

    /*
    | Endereço PÚBLICO usado nos links e no QR Code (ex.: https://votacao.minhaempresa.com.br ou a URL do
    | túnel de desenvolvimento). Celulares não abrem "localhost": se vazio, usa o host da requisição atual.
    */
    'public_url' => env('VOTAFLOW_PUBLIC_URL'),

    /*
    | Login de DESENVOLVIMENTO (sem Google), para testar no celular pela mesma Wi-Fi.
    | Só funciona se APP_ENV=local E VOTAFLOW_DEV_LOGIN=true. Em qualquer outro ambiente
    | as rotas nem são registradas. Nunca ligue em produção.
    */
    'dev_login' => (bool) env('VOTAFLOW_DEV_LOGIN', false) && env('APP_ENV') === 'local',

    // Nome exibido no cabeçalho das páginas.
    'nome' => env('VOTAFLOW_NOME', 'VotaFlow'),
];
