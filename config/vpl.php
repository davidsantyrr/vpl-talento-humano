<?php

return [
    // Lista de emails permitidos (separados por coma) como fallback cuando la API no devuelve roles
    'allowed_emails' => env('VPL_ALLOWED_EMAILS', ''),

    // Lista de dominios permitidos (separados por coma), e.g. vigiaplus.com
    'allowed_domains' => env('VPL_ALLOWED_DOMAINS', ''),
    // Filtros de catálogo por rol (coincidencias parciales en `categoria_produc`)
    'role_filters' => [
        'hseq' => env('VPL_FILTER_HSEQ', 'HSEQ,Seguridad,EPP,Elementos de Protección,Elementos EPP,Protección Personal'),
        'talento' => env('VPL_FILTER_TALENTO', 'Dotacion,Dotación,Dotacion Personal'),
    ],
];
