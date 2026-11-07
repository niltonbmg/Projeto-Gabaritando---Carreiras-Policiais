<?php
// teste-conexao.php — apagar depois do teste.
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/api/conexao.php';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$cfg = gab_db_config();
$hosts = [];
foreach ([(string)$cfg['host'], 'localhost', '127.0.0.1'] as $host) {
    $host = trim($host);
    if ($host !== '' && !in_array($host, $hosts, true)) {
        $hosts[] = $host;
    }
}

function testar_conexao_mysql(string $host, bool $comBanco): array
{
    $cfg = gab_db_config();
    $database = $comBanco ? (string)$cfg['database'] : '';
    try {
        $pdo = gab_pdo_connect($host, $database);
        $row = $pdo->query('SELECT USER() AS usuario_conexao, CURRENT_USER() AS usuario_permissao, VERSION() AS versao')->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['ok' => true, 'msg' => 'OK', 'info' => $row];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => $e->getMessage(), 'info' => []];
    }
}

$senha = (string)$cfg['password'];
$senhaInfo = 'tamanho ' . strlen($senha) . ', começa com ' . substr($senha, 0, 1) . ' e termina com ' . substr($senha, -2);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Teste de conexão - Projeto Gabaritando</title>
<style>
body{font-family:Arial,sans-serif;padding:28px;color:#111;background:#fff}code{background:#f2f2f2;padding:2px 6px;border-radius:4px}.ok{color:#078a36;font-weight:700}.erro{color:#c00;font-weight:700}.box{border:1px solid #ddd;border-radius:10px;padding:18px;max-width:1050px}hr{border:0;border-top:1px solid #ddd;margin:18px 0}table{border-collapse:collapse;width:100%;margin-top:12px}th,td{border:1px solid #ddd;padding:10px;text-align:left;vertical-align:top}th{background:#f7f7f7}pre{white-space:pre-wrap;margin:0}.small{font-size:13px;color:#555}.warn{background:#fff8db;border:1px solid #f2d37a;padding:12px;border-radius:8px}.bad{background:#fff0f0;border:1px solid #ffb3b3;padding:12px;border-radius:8px}.good{background:#effaf2;border:1px solid #99d5a7;padding:12px;border-radius:8px}
</style>
</head>
<body>
<div class="box">
<h1>Teste de conexão - Projeto Gabaritando</h1>
<p><strong>Pasta do teste:</strong> <code><?= h(__DIR__) ?></code></p>
<p><strong>DB_HOST:</strong> <code><?= h($cfg['host']) ?></code></p>
<p><strong>DB_DATABASE:</strong> <code><?= h($cfg['database']) ?></code></p>
<p><strong>DB_USERNAME:</strong> <code><?= h($cfg['username']) ?></code></p>
<p><strong>Senha configurada:</strong> <code><?= h($senhaInfo) ?></code></p>
<hr>
<h2>Resultado das tentativas</h2>
<table>
<thead><tr><th>Teste</th><th>Status</th><th>Mensagem</th></tr></thead>
<tbody>
<?php
$algumOkComBanco = false;
$algumOkSemBanco = false;
foreach ($hosts as $host) {
    foreach ([false, true] as $comBanco) {
        $r = testar_conexao_mysql($host, $comBanco);
        if ($r['ok'] && $comBanco) { $algumOkComBanco = true; }
        if ($r['ok'] && !$comBanco) { $algumOkSemBanco = true; }
        $nome = $host . ($comBanco ? ' + banco selecionado' : ' sem selecionar banco');
        echo '<tr>';
        echo '<td><code>' . h($nome) . '</code></td>';
        echo '<td>' . ($r['ok'] ? '<span class="ok">OK</span>' : '<span class="erro">FALHOU</span>') . '</td>';
        if ($r['ok']) {
            echo '<td><pre>' . h(json_encode($r['info'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></td>';
        } else {
            echo '<td><pre>' . h($r['msg']) . '</pre></td>';
        }
        echo '</tr>';
    }
}
?>
</tbody>
</table>
<hr>
<?php if ($algumOkComBanco): ?>
    <div class="good"><strong>CONEXÃO OK.</strong> O banco conectou. Agora pode apagar este arquivo e testar o login.</div>
<?php elseif ($algumOkSemBanco): ?>
    <div class="warn"><strong>O usuário/senha conecta no MySQL, mas não entra no banco.</strong><br>Isso indica que o usuário <code><?= h($cfg['username']) ?></code> não está com permissão no banco <code><?= h($cfg['database']) ?></code>. No hPanel, vincule esse usuário ao banco e dê todas as permissões.</div>
<?php else: ?>
    <div class="bad"><strong>O MySQL recusou usuário/senha.</strong><br>Isso não é erro da pasta <code>/ead</code>. O PHP está tentando conectar com <code><?= h($cfg['username']) ?></code> e senha configurada no <code>.env</code>, mas o MySQL respondeu Access denied. Redefina a senha desse usuário MySQL no hPanel e coloque a mesma senha no arquivo <code>/ead/.env</code>.</div>
<?php endif; ?>
<hr>
<p><strong>Importante:</strong> depois que resolver, apague este arquivo do servidor.</p>
</div>
</body>
</html>
