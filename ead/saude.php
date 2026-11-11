<?php
// saude.php — painel rápido do admin: está tudo funcionando?
declare(strict_types=1);
require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/cache-lib.php';
require_once __DIR__ . '/api/conexao.php';

$pdo = admin_cursos_db();
$itens = [];
$t = microtime(true);
try {
    $pdo->query('SELECT 1')->fetchColumn();
    $ms = (int)round((microtime(true) - $t) * 1000);
    $itens[] = ['Banco de dados', $ms < 50, "responde em {$ms} ms"];
} catch (Throwable $e) {
    $itens[] = ['Banco de dados', false, 'fora do ar'];
}
$robo = __DIR__ . '/uploads/.ia-robo-ultima';
if (!is_file($robo)) {
    $robo = __DIR__ . '/uploads/.ia-robo.lock';
}
$minRobo = is_file($robo) ? (int)floor((time() - filemtime($robo)) / 60) : -1;
$itens[] = ['Robô da IA (cron a cada 5 min)', $minRobo >= 0 && $minRobo <= 15, $minRobo < 0 ? 'nunca rodou' : "última execução há {$minRobo} min"];
try {
    $f = $pdo->query("SELECT SUM(status='pendente') p, SUM(status='erro') e FROM ia_fila")->fetch();
    $itens[] = ['Fila da IA', (int)$f['e'] < 10, (int)$f['p'] . ' na fila · ' . (int)$f['e'] . ' com erro'];
} catch (Throwable $e) {
}
foreach (['voz' => '.ia-pausa-voz', 'pesquisa' => '.ia-pausa-busca', 'texto' => '.ia-pausa-texto'] as $n => $arq) {
    $p = __DIR__ . '/uploads/' . $arq;
    $ativa = is_file($p) && (int)trim((string)@file_get_contents($p)) > time();
    $itens[] = ['Cota do Google · ' . $n, !$ativa, $ativa ? 'esgotada, volta às 05:00' : 'disponível'];
}
$livre = @disk_free_space(__DIR__);
// Em hospedagem compartilhada o servidor mostra o disco da máquina inteira; o limite real é o do plano.
$itens[] = ['Espaço em disco', true, ($livre !== false && $livre < 2e11) ? round($livre / 1e9, 1) . ' GB livres' : 'limite do plano: 50 GB (veja o uso no painel da Hostinger)'];
try {
    $hoje = date('Y-m-d');
    $d = $pdo->prepare('SELECT COUNT(*) FROM desafios WHERE data = :d');
    $d->execute([':d' => $hoje]);
    $itens[] = ['Desafio do Dia de hoje', true, (int)$d->fetchColumn() ? 'pronto' : 'ainda não montado (o robô monta)'];
} catch (Throwable $e) {
}
// Porta de manutenção (assistente): mostra o que a plataforma enxerga no .env, sem mostrar a chave.
$tok = gab_env('MANUTENCAO_TOKEN', '');
$envArq = __DIR__ . '/.env';
$itens[] = ['Porta de manutenção (assistente)', strlen($tok) >= 32,
    !is_file($envArq) ? 'não achei o arquivo ' . 'ead/.env' :
    ($tok === '' ? 'desligada: não há linha MANUTENCAO_TOKEN= no ead/.env' . (preg_match('~manuten~i', (string)@file_get_contents($envArq)) ? ' (existe algo parecido: confira se o nome está exatamente MANUTENCAO_TOKEN, maiúsculo, sem espaço antes do =)' : '') :
    (strlen($tok) < 32 ? 'desligada: a chave tem ' . strlen($tok) . ' caracteres; precisa de 32 ou mais' : 'ligada (chave com ' . strlen($tok) . ' caracteres)'))];
$itens[] = ['Senhas (.env) protegidas', true, 'bloqueado pelo .htaccess — confira abrindo /ead/.env (deve dar "Forbidden")'];
if (($_POST['acao'] ?? '') === 'limpar') {
    gab_cache_apagar('');
    foreach (glob(__DIR__ . '/uploads/.schema/*') ?: [] as $m) {
        @unlink($m);
    }
    $msg = 'Cache limpo. Na próxima visita a plataforma confere o banco de novo (uma vez).';
}
?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Saúde da plataforma</title>
<style>body{font:15px/1.5 system-ui,sans-serif;background:#f5f6f8;margin:0;padding:24px;color:#111}main{max-width:720px;margin:auto}
.it{display:flex;gap:12px;align-items:center;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;margin:8px 0}
.it b{flex:1}.ok{color:#067647}.ruim{color:#b42318}button{padding:8px 14px;border-radius:8px;border:1px solid #ccc;background:#fff;cursor:pointer}</style></head>
<body><main><h1>🩺 Saúde da plataforma</h1><p><a href="admin-dashboard.php">← Voltar ao painel</a></p>
<?php if (!empty($msg)): ?><p class="ok"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
<?php foreach ($itens as [$nome, $ok, $info]): ?>
<div class="it"><span class="<?php echo $ok ? 'ok' : 'ruim'; ?>"><?php echo $ok ? '●' : '▲'; ?></span><b><?php echo htmlspecialchars($nome); ?></b><span><?php echo htmlspecialchars($info); ?></span></div>
<?php endforeach; ?>
<form method="post" style="margin-top:16px"><input type="hidden" name="acao" value="limpar"><button>Limpar cache (use se algo parecer desatualizado depois de subir arquivos)</button></form>
</main></body></html>
