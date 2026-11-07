<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$token = bearer_token();
if ($token !== '' && table_exists($pdo, 'app_tokens')) {
    $stmt = $pdo->prepare('UPDATE app_tokens SET revoked_at = NOW() WHERE token = ?');
    $stmt->execute([$token]);
}
json_out(true, ['message' => 'Sessão encerrada.']);
