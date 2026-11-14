<?php
// meus-dados.php — Direitos do titular (LGPD art. 18): ver, baixar cópia, corrigir, excluir, privacidade no ranking.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/lgpd-lib.php';

$uid = (int)auth_user_id();
$pdo = db();
lgpd_bootstrap($pdo);
$msg = '';
$acao = (string)($_POST['acao'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($acao === 'aceitar' && !empty($_POST['aceite'])) {
        lgpd_registrar_aceite($pdo, $uid, 'aceite-plataforma');
        $volta = (string)($_POST['volta'] ?? 'meu-plano.php');
        header('Location: ' . (preg_match('~^[a-z0-9\-]+\.php(\?[^\s]*)?$~i', $volta) ? $volta : 'meu-plano.php'));
        exit;
    }
    if ($acao === 'exportar') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus-dados-projeto-gabaritando.json"');
        echo json_encode(lgpd_exportar($pdo, $uid), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($acao === 'ranking') {
        $pdo->prepare('INSERT INTO aluno_privacidade (usuario_id, ranking_publico) VALUES (:u, :r) ON DUPLICATE KEY UPDATE ranking_publico = VALUES(ranking_publico)')
            ->execute([':u' => $uid, ':r' => empty($_POST['ranking_publico']) ? 0 : 1]);
        $msg = 'Preferência salva.';
    }
    if ($acao === 'solicitar') {
        try {
            lgpd_solicitar($pdo, $uid, (string)($_POST['tipo'] ?? ''), (string)($_POST['detalhe'] ?? ''));
            $msg = 'Pedido registrado. Respondemos em até ' . LGPD_PRAZO_DIAS . ' dias pelo seu e-mail.';
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }
    }
}
$st = $pdo->prepare('SELECT nome, email, telefone, created_at FROM usuarios WHERE id = :u');
$st->execute([':u' => $uid]);
$eu = $st->fetch() ?: [];
$pedidos = $pdo->prepare('SELECT tipo, status, criado_em, resolvido_em, resposta FROM lgpd_solicitacoes WHERE usuario_id = :u ORDER BY id DESC');
$pedidos->execute([':u' => $uid]);
$pedidos = $pedidos->fetchAll();
$aceites = $pdo->prepare('SELECT versao, aceito_em FROM lgpd_consentimentos WHERE usuario_id = :u ORDER BY id DESC LIMIT 5');
$aceites->execute([':u' => $uid]);
$aceites = $aceites->fetchAll();
$rankPub = lgpd_ranking_publico($pdo, $uid);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tipos = ['acesso' => 'Quero saber tudo o que vocês têm sobre mim', 'correcao' => 'Corrigir um dado', 'exclusao' => 'Excluir minha conta e meus dados', 'revogacao' => 'Revogar um consentimento / revisar decisão automática', 'duvida' => 'Dúvida sobre privacidade'];
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meus dados | Projeto Gabaritando</title><link rel="icon" type="image/png" href="/ead/favicon.png">
<style>
    :root{--fundo:#0c0c0d;--card:#161618;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a}
    *{box-sizing:border-box}body{margin:0;background:var(--fundo);color:var(--texto);font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;line-height:1.5}
    .md{max-width:820px;margin:0 auto;padding:24px 16px 64px;display:grid;gap:16px}
    h1{font-size:26px;margin:0}h2{font-size:18px;margin:0 0 8px}.sub{color:var(--suave);font-size:14px}
    .card{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:16px}
    .bt{border:0;border-radius:10px;padding:10px 14px;background:var(--ouro);color:#111;font:inherit;font-weight:700;cursor:pointer}
    .bt:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid #fff;outline-offset:2px}
    select,textarea{width:100%;background:#0f0f11;color:var(--texto);border:1px solid var(--linha);border-radius:8px;padding:10px;font:inherit}
    .ok{background:#0f2a1a;color:var(--ok);border-radius:10px;padding:10px 12px}
    table{width:100%;border-collapse:collapse;font-size:14px}td,th{border-top:1px solid var(--linha);padding:6px;text-align:left}
    a{color:var(--ouro)}
</style></head><body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="md">
    <header><h1>🔒 Meus dados</h1><p class="sub">Seus direitos pela LGPD. Leia a <a href="privacidade.php">Política de Privacidade</a> e os <a href="termos.php">Termos de Uso</a>.</p></header>
    <?php if ($msg !== ''): ?><p class="ok" role="status"><?php echo $h($msg); ?></p><?php endif; ?>
    <section class="card"><h2>O que temos sobre você</h2>
        <p>Nome: <strong><?php echo $h($eu['nome'] ?? ''); ?></strong> · E-mail: <strong><?php echo $h($eu['email'] ?? ''); ?></strong> · Telefone: <?php echo $h($eu['telefone'] ?: '—'); ?></p>
        <p class="sub">Além disso: seu progresso nas aulas, respostas, redações, cronograma, desafios e marcações. Para corrigir nome ou telefone, use <a href="configuracoes.php">Configurações</a>.</p>
        <form method="post"><input type="hidden" name="acao" value="exportar"><button class="bt" type="submit">⬇ Baixar uma cópia de todos os meus dados</button></form>
    </section>
    <section class="card"><h2>Privacidade no Ranking</h2>
        <form method="post"><input type="hidden" name="acao" value="ranking">
            <label><input type="checkbox" name="ranking_publico" value="1" <?php echo $rankPub ? 'checked' : ''; ?>> Mostrar meu nome abreviado (ex.: “Maria S.”) no Ranking Geral do Desafio do Dia</label>
            <p class="sub">Desmarcado, você continua participando e vendo sua posição, mas aparece para os outros como “Combatente anônimo”.</p>
            <button class="bt" type="submit">Salvar</button></form>
    </section>
    <section class="card"><h2>Fazer um pedido</h2>
        <form method="post" style="display:grid;gap:8px"><input type="hidden" name="acao" value="solicitar">
            <label for="tipo">O que você precisa?</label><select id="tipo" name="tipo"><?php foreach ($tipos as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select>
            <label for="detalhe">Detalhes (opcional)</label><textarea id="detalhe" name="detalhe" rows="3"></textarea>
            <p class="sub">Respondemos em até <?php echo LGPD_PRAZO_DIAS; ?> dias. Na exclusão, anonimizamos a conta e apagamos redações e anotações; guardamos só o que a lei obriga (ex.: dados fiscais da compra).</p>
            <button class="bt" type="submit">Enviar pedido</button></form>
        <?php if ($pedidos): ?><table style="margin-top:12px"><tr><th>Pedido</th><th>Situação</th><th>Data</th><th>Resposta</th></tr>
            <?php foreach ($pedidos as $p): ?><tr><td><?php echo $h($tipos[$p['tipo']] ?? $p['tipo']); ?></td><td><?php echo $h(['aberta' => 'Em análise', 'concluida' => 'Concluído', 'negada' => 'Não atendido'][$p['status']] ?? $p['status']); ?></td><td><?php echo $h(date('d/m/Y', strtotime((string)$p['criado_em']))); ?></td><td><?php echo $h($p['resposta'] ?? ''); ?></td></tr><?php endforeach; ?></table><?php endif; ?>
    </section>
    <section class="card"><h2>Seus aceites</h2>
        <?php if ($aceites): foreach ($aceites as $a): ?><p class="sub">Política/Termos versão <?php echo $h(date('d/m/Y', strtotime((string)$a['versao']))); ?> aceitos em <?php echo $h(date('d/m/Y H:i', strtotime((string)$a['aceito_em']))); ?></p><?php endforeach; else: ?><p class="sub">Nenhum aceite registrado ainda.</p><?php endif; ?>
        <p class="sub">Encarregado de dados: <a href="mailto:<?php echo LGPD_EMAIL_ENCARREGADO; ?>"><?php echo LGPD_EMAIL_ENCARREGADO; ?></a></p>
    </section>
</main></body></html>
