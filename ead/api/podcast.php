<?php
// api/podcast.php
// GET  ?aula_id=N                       -> situação do podcast da aula
// POST {aula_id, acao:"iniciar"}        -> gera o roteiro, devolve nº de partes
// POST {aula_id, acao:"parte", n}       -> gera o áudio da parte n (junta na última)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
@set_time_limit(180);
@ini_set('max_execution_time', '180');

require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/../includes/podcast-lib.php';

function podcast_resposta(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_is_logged()) {
    podcast_resposta(401, ['erro' => 'Faça login novamente.']);
}

try {
    $pdo = db();
    podcast_bootstrap($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        podcast_resposta(200, podcast_status($pdo, (int)($_GET['aula_id'] ?? 0)));
    }

    $entrada = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $aulaId = (int)($entrada['aula_id'] ?? 0);
    if ($aulaId <= 0) {
        podcast_resposta(400, ['erro' => 'Aula inválida.']);
    }
    $acao = (string)($entrada['acao'] ?? '');
    if ($acao === 'iniciar') {
        podcast_resposta(200, podcast_iniciar($pdo, $aulaId));
    }
    if ($acao === 'parte') {
        podcast_resposta(200, podcast_parte($pdo, $aulaId, (int)($entrada['n'] ?? 0)));
    }
    podcast_resposta(400, ['erro' => 'Ação inválida.']);
} catch (Throwable $e) {
    if (isset($pdo, $aulaId) && $pdo instanceof PDO && $aulaId > 0) {
        try {
            $pdo->prepare("UPDATE aula_podcasts SET erro = :e WHERE aula_id = :a")->execute([':e' => mb_substr($e->getMessage(), 0, 500), ':a' => $aulaId]);
        } catch (Throwable $ignorar) {
        }
    }
    podcast_resposta(500, ['erro' => $e->getMessage()]);
}
