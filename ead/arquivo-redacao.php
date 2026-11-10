<?php
// arquivo-redacao.php — entrega a foto/PDF/áudio de uma redação só ao dono, ao professor de redação ou ao admin.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/redacao-lib.php';

$uid = function_exists('auth_user_id') ? auth_user_id() : 0;
$prof = redacao_eh_professor();
if ($uid <= 0 && !$prof) {
    http_response_code(403);
    exit('Faça login.');
}
$st = db()->prepare('SELECT usuario_id, arquivo, audio FROM redacoes WHERE id = :id');
$st->execute([':id' => (int)($_GET['id'] ?? 0)]);
$r = $st->fetch();
if (!$r || (!$prof && (int)$r['usuario_id'] !== $uid)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$rel = (string)(($_GET['tipo'] ?? '') === 'audio' ? $r['audio'] : $r['arquivo']);
$base = realpath(__DIR__ . '/uploads/redacoes');
$caminho = $rel !== '' ? realpath(__DIR__ . '/' . $rel) : false;
if (!$base || !$caminho || !str_starts_with($caminho, $base . DIRECTORY_SEPARATOR) || !is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}
$tipos = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'mp3' => 'audio/mpeg', 'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav'];
header('Content-Type: ' . ($tipos[strtolower(pathinfo($caminho, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
readfile($caminho);
