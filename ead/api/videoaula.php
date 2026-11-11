<?php
// api/videoaula.php
// GET  ?aula_id=N                               -> situação (pronta: slides, áudios e paradas)
// POST {aula_id, acao:"iniciar"}                -> IA monta slides e narração
// POST {aula_id, acao:"parte", n}               -> voz do slide n
// POST {aula_id, acao:"responder", parada, alt} -> corrige a parada e explica
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
@set_time_limit(180);

require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/../includes/videoaula-lib.php';

function videoaula_resposta(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_is_logged()) {
    videoaula_resposta(401, ['erro' => 'Faça login novamente.']);
}

try {
    $pdo = db();
    videoaula_bootstrap($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        videoaula_resposta(200, videoaula_status($pdo, (int)($_GET['aula_id'] ?? 0)));
    }
    $e = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $aulaId = (int)($e['aula_id'] ?? 0);
    if ($aulaId <= 0) {
        videoaula_resposta(400, ['erro' => 'Aula inválida.']);
    }
    // Gerar gasta a cota da IA: só quem tem acesso ao curso desta aula (ou admin).
    if (in_array((string)($e['acao'] ?? ''), ['iniciar', 'parte'], true)) {
        $uid = (int)auth_user_id();
        $stA = $pdo->prepare('SELECT curso_id FROM admin_curso_aulas WHERE id = :id');
        $stA->execute([':id' => $aulaId]);
        $cursoDaAula = (int)$stA->fetchColumn();
        if (!$cursoDaAula) {
            videoaula_resposta(404, ['erro' => 'Aula não encontrada.']);
        }
        $ehAdmin = strtolower((string)($_SESSION['usuario_tipo'] ?? '')) === 'admin' || !empty($_SESSION['admin_usuario_id']);
        if (!$ehAdmin && is_file(__DIR__ . '/../includes/curso-acesso.php')) {
            require_once __DIR__ . '/../includes/curso-acesso.php';
            if (function_exists('gab_curso_acesso_configurado') && gab_curso_acesso_configurado($pdo, $uid) && !in_array($cursoDaAula, array_map('intval', gab_curso_acesso_ids($pdo, $uid)), true)) {
                videoaula_resposta(403, ['erro' => 'Esta aula não faz parte dos seus cursos.']);
            }
        }
    }
    // Revisão: não gera videoaula de aula cujo PDF ainda não foi conferido (ou está suspeito).
    if ((string)($e['acao'] ?? '') === 'iniciar' && is_file(__DIR__ . '/../includes/revisao-aulas-lib.php')) {
        require_once __DIR__ . '/../includes/revisao-aulas-lib.php';
        if (!rev_liberada($pdo, $aulaId)) {
            require_once __DIR__ . '/../includes/ia-fila-lib.php';
            if (rev_status($pdo, $aulaId)['status'] === 'pendente') {
                ia_fila_adicionar($pdo, 'conferir_pdf', $aulaId);
            }
            videoaula_resposta(409, ['erro' => 'O material desta aula está em revisão pela equipe. A videoaula fica pronta logo depois.']);
        }
    }
    switch ((string)($e['acao'] ?? '')) {
        case 'iniciar':
            videoaula_resposta(200, videoaula_iniciar($pdo, $aulaId));
        case 'parte':
            videoaula_resposta(200, videoaula_parte($pdo, $aulaId, (int)($e['n'] ?? 0)));
        case 'responder':
            videoaula_resposta(200, videoaula_responder($pdo, (int)auth_user_id(), $aulaId, (int)($e['parada'] ?? -1), (int)($e['alt'] ?? -1)));
    }
    videoaula_resposta(400, ['erro' => 'Ação inválida.']);
} catch (Throwable $ex) {
    if (isset($pdo, $aulaId) && $aulaId > 0) {
        try {
            podcast_reconectar($pdo)->prepare('UPDATE aula_videoaulas SET erro = :e WHERE aula_id = :a')->execute([':e' => mb_substr($ex->getMessage(), 0, 500), ':a' => $aulaId]);
        } catch (Throwable $ignorar) {
        }
    }
    videoaula_resposta(500, ['erro' => $ex->getMessage()]);
}
