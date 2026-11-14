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
    if (($e['acao'] ?? '') === 'prioridade') {
        ia_prioridade_salvar(['podcasts_pausados' => !empty($e['podcasts_pausados']), 'primeiro' => (string)($e['primeiro'] ?? 'videoaula')]);
        exit('{"ok":true}');
    }
    session_write_close(); // não trava as outras páginas do mentor enquanto a IA trabalha
    @ignore_user_abort(true);
    @set_time_limit(320);
    // Uma execução por vez (a mesma trava do robô do cron): nada é feito em dobro.
    $trava = fopen(__DIR__ . '/../uploads/.ia-robo.lock', 'c');
    if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
        echo '{"rodou":"ocupado"}';
        exit;
    }
    $inicio = time();
    $feitos = 0;
    // Trabalha até ~4 min por chamada: tarefas do cadastro primeiro, depois a fila (questões, aulas, vídeos).
    while (time() - $inicio < 230) {
        $job = job_rodar_proximo($pdo, $concursoId);
        $pdo = podcast_reconectar($pdo);
        if (!$job && !ia_fila_processar_um($pdo)) {
            break;
        }
        $pdo = podcast_reconectar($pdo);
        $feitos++;
    }
    flock($trava, LOCK_UN);
    echo json_encode(['rodou' => $feitos], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(job_painel($pdo, $concursoId), JSON_UNESCAPED_UNICODE);
