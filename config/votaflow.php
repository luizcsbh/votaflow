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

    // Nome exibido no cabeçalho das páginas.
    'nome' => env('VOTAFLOW_NOME', 'VotaFlow'),
];
