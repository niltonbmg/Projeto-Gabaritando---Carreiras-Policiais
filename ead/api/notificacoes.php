<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/../includes/notificacoes.php';

auth_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $usuarioId = auth_user_id();
    $acao = (string)($_POST['acao'] ?? '');

    if ($acao === 'marcar_todas') {
        gab_notif_marcar_todas($pdo, $usuarioId);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === 'marcar_lida') {
        gab_notif_marcar_lida(
            $pdo,
            $usuarioId,
            trim((string)($_POST['tipo'] ?? '')),
            (int)($_POST['referencia_id'] ?? 0)
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[Notificações] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível atualizar as notificações.'], JSON_UNESCAPED_UNICODE);
}
