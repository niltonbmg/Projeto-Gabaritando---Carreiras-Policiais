<?php
// api/missao.php — POST {acao:"tour_visto"}: o aluno já viu a apresentação do método.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/../includes/missao-lib.php';
if (!auth_is_logged()) {
    http_response_code(401);
    exit('{"erro":"Faça login novamente."}');
}
try {
    $e = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($e['acao'] ?? '') === 'tour_visto') {
        missao_marcar_tour(db(), (int)auth_user_id());
    }
    echo '{"ok":true}';
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['erro' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
}
