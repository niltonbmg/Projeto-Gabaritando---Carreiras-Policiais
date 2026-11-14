<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$tokensOk = ensure_app_tokens_table($pdo);

json_out(true, [
    'message' => 'API Mobile Gabaritando online.',
    'version' => '1.0.1',
    'database' => 'conectado',
    'app_tokens' => $tokensOk ? 'ok' : 'pendente_sql',
    'endpoints' => [
        'login' => 'POST /ead/api_mobile/login.php',
        'me' => 'GET /ead/api_mobile/me.php',
        'data' => 'GET /ead/api_mobile/data.php?tipo=dashboard',
        'admin_usuarios' => 'GET/POST /ead/api_mobile/admin_usuarios.php',
        'logout' => 'POST /ead/api_mobile/logout.php',
    ],
]);
