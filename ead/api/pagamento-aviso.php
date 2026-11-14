<?php
// api/pagamento-aviso.php — o Asaas avisa aqui quando um pagamento é confirmado, vence ou é estornado.
// Confere a senha do aviso (cabeçalho asaas-access-token), não aplica o mesmo aviso duas vezes,
// libera ou bloqueia o curso e responde 200 rápido (o Asaas reenvia se não receber 200).
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once dirname(__DIR__) . '/includes/pagamentos-lib.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}
try {
    $pdo = db();
    $cfg = pag_config($pdo);
    $recebido = (string)($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');
    if (empty($cfg['aviso_senha']) || !hash_equals((string)$cfg['aviso_senha'], $recebido)) {
        http_response_code(401);
        echo '{"ok":false,"erro":"nao autorizado"}';
        exit;
    }
    $j = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($j)) {
        http_response_code(400);
        echo '{"ok":false,"erro":"corpo invalido"}';
        exit;
    }
    $feito = pag_processar_aviso($pdo, $j);
    echo json_encode(['ok' => true, 'resultado' => $feito]);
} catch (Throwable $e) {
    error_log('pagamento-aviso: ' . $e->getMessage());
    http_response_code(500); // o Asaas tenta de novo depois
    echo '{"ok":false}';
}
