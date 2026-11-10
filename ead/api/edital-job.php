<?php
// api/edital-job.php
// GET  ?concurso_id=N          -> painel ao vivo (tarefas da IA e progresso das questões)
// POST {concurso_id, acao:"rodar"} -> executa a próxima tarefa em segundo plano (a resposta pode demorar;
//                                    se a conexão cair, o PHP continua trabalhando)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/admin-auth.php';
if (!auth_admin_is_logged()) {
    http_response_code(401);
    exit('{"erro":"Faça login no painel."}');
}
require_once __DIR__ . '/../includes/admin-cursos-lib.php';
require_once __DIR__ . '/../includes/edital-jobs-lib.php';

$pdo = admin_cursos_db();
job_bootstrap($pdo);
$concursoId = (int)($_GET['concurso_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $e = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $concursoId = (int)($e['concurso_id'] ?? 0);
    session_write_close(); // não trava as outras páginas do mentor enquanto a IA trabalha
    @ignore_user_abort(true);
    @set_time_limit(320);
    $job = job_rodar_proximo($pdo, $concursoId);
    if (!$job) {
        // Sem tarefa do cadastro: adianta a fila do robô (questões, aulas do Antigão...).
        $pdo = podcast_reconectar($pdo);
        $r = ia_fila_processar_um($pdo);
        echo json_encode(['rodou' => $r ? 'fila' : 'nada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['rodou' => 'job', 'job' => ['id' => (int)$job['id'], 'status' => $job['status'], 'mensagem' => $job['mensagem']]], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(job_painel($pdo, $concursoId), JSON_UNESCAPED_UNICODE);
