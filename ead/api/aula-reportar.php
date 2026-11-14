<?php
// api/aula-reportar.php — aluno reporta problema na aula (entra na tela Revisão de aulas).
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/conexao.php';
require_once dirname(__DIR__) . '/includes/revisao-aulas-lib.php';

$uid = function_exists('auth_user_id') ? auth_user_id() : 0;
$aulaId = (int)($_POST['aula_id'] ?? 0);
if ($uid <= 0 || $aulaId <= 0 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../login.php');
    exit;
}
$pdo = db();
rev_bootstrap($pdo);
$tipo = in_array($_POST['tipo'] ?? '', ['pdf_errado', 'video', 'conteudo', 'outro'], true) ? (string)$_POST['tipo'] : 'outro';
// No máximo 1 reporte aberto por aluno por aula (evita enxurrada).
$ja = $pdo->prepare("SELECT 1 FROM aula_reportes WHERE aula_id = :a AND usuario_id = :u AND status = 'aberto'");
$ja->execute([':a' => $aulaId, ':u' => $uid]);
if (!$ja->fetchColumn()) {
    $pdo->prepare('INSERT INTO aula_reportes (aula_id, usuario_id, tipo, detalhe) VALUES (:a, :u, :t, :d)')
        ->execute([':a' => $aulaId, ':u' => $uid, ':t' => $tipo, ':d' => mb_substr(trim((string)($_POST['detalhe'] ?? '')), 0, 500)]);
}
$st = $pdo->prepare('SELECT curso_id, modulo_id FROM admin_curso_aulas WHERE id = :id');
$st->execute([':id' => $aulaId]);
$a = $st->fetch() ?: ['curso_id' => 0, 'modulo_id' => 0];
header('Location: ../aulas.php?curso_id=' . (int)$a['curso_id'] . '&modulo_id=' . (int)$a['modulo_id'] . '&aula_id=' . $aulaId . '&reportado=1');
exit;
