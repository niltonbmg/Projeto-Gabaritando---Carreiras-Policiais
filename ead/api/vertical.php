<?php
// api/vertical.php — POST {item_id, status:"estudado|revisado|dominado|"}: marca o assunto no edital verticalizado do aluno.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/../includes/edital-jobs-lib.php';
if (!auth_is_logged()) {
    http_response_code(401);
    exit('{"erro":"Faça login novamente."}');
}
try {
    $pdo = db();
    job_bootstrap($pdo);
    $e = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $item = (int)($e['item_id'] ?? 0);
    $st = (string)($e['status'] ?? '');
    if ($st === '') {
        $pdo->prepare('DELETE FROM aluno_vertical WHERE usuario_id = :u AND edital_item_id = :i')->execute([':u' => auth_user_id(), ':i' => $item]);
    } elseif (in_array($st, ['estudado', 'revisado', 'dominado'], true)) {
        $pdo->prepare('INSERT INTO aluno_vertical (usuario_id, edital_item_id, status) VALUES (:u, :i, :s) ON DUPLICATE KEY UPDATE status = VALUES(status)')->execute([':u' => auth_user_id(), ':i' => $item, ':s' => $st]);
    }
    echo '{"ok":true}';
} catch (Throwable $ex) {
    http_response_code(500);
    echo '{"erro":"Não foi possível salvar."}';
}
