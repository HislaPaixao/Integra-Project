<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serviços de Terceiros — Integra / NIR
    |--------------------------------------------------------------------------
    |
    | As credenciais e URLs de serviços externos são lidas do arquivo .env
    | para não ficarem expostas no controle de versão.
    |
    */

    // ─── AGHU ────────────────────────────────────────────────────────────────
    // Sistema de Gestão Hospitalar (fonte dos dados do paciente — HU01)
    'aghu' => [
        // true  → usa os pacientes de demonstração (database/data/aghu_pacientes.json)
        // false → consulta a API real do AGHU em base_url
        'mock'     => (bool) env('AGHU_MOCK', false),
        'base_url' => env('AGHU_BASE_URL') ?: 'https://aghu.hospital.gov.br/api',
        'token'    => env('AGHU_TOKEN'),
        'timeout'  => (int) env('AGHU_TIMEOUT', 5),
    ],

];
