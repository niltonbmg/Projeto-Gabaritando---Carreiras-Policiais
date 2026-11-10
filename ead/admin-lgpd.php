<?php
// admin-lgpd.php
// Encarregado/admin: pedidos dos titulares (art. 18 LGPD) com prazo, exclusão por anonimização e aceites.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/lgpd-lib.php';

$pdo = admin_cursos_db();
lgpd_bootstrap($pdo);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM lgpd_solicitacoes WHERE id = :id');
        $st->execute([':id' => $id]);
        $sol = $st->fetch();
        if (!$sol) {
            throw new RuntimeException('Pedido não encontrado.');
        }
        $acao = (string)($_POST['acao'] ?? '');
        $resposta = trim((string)($_POST['resposta'] ?? ''));
        if ($acao === 'exportar') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="dados-titular-' . (int)$sol['usuario_id'] . '.json"');
            echo json_encode(lgpd_exportar($pdo, (int)$sol['usuario_id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($acao === 'anonimizar') {
            if ($sol['tipo'] !== 'exclusao' || ($_POST['confirmo'] ?? '') !== 'EXCLUIR') {
                throw new RuntimeException('Para excluir, digite EXCLUIR na confirmação.');
            }
            lgpd_anonimizar($pdo, (int)$sol['usuario_id']);
            $acao = 'concluir';
            $resposta = $resposta !== '' ? $resposta : 'Conta anonimizada e conteúdo pessoal apagado. Ficaram só registros que a lei obriga guardar.';
        }
        if (in_array($acao, ['concluir', 'negar'], true)) {
            if ($acao === 'negar' && $resposta === '') {
                throw new RuntimeException('Ao negar, explique o motivo ao titular (art. 18, §4º).');
            }
            $pdo->prepare('UPDATE lgpd_solicitacoes SET status = :s, resposta = :r, resolvido_em = NOW() WHERE id = :id')
                ->execute([':s' => $acao === 'negar' ? 'negada' : 'concluida', ':r' => $resposta, ':id' => $id]);
            $sucesso = 'Pedido #' . $id . ' atualizado. Avise o aluno pelo e-mail cadastrado.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$abertas = $pdo->query("SELECT s.*, u.nome, u.email, DATEDIFF(NOW(), s.criado_em) dias FROM lgpd_solicitacoes s LEFT JOIN usuarios u ON u.id = s.usuario_id WHERE s.status = 'aberta' ORDER BY s.criado_em")->fetchAll();
$fechadas = $pdo->query("SELECT s.*, u.nome FROM lgpd_solicitacoes s LEFT JOIN usuarios u ON u.id = s.usuario_id WHERE s.status <> 'aberta' ORDER BY s.resolvido_em DESC LIMIT 30")->fetchAll();
$aceites = (int)$pdo->query("SELECT COUNT(DISTINCT usuario_id) FROM lgpd_consentimentos WHERE versao = " . $pdo->quote(LGPD_VERSAO))->fetchColumn();
$alunos = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE status <> 'inativo'")->fetchColumn();
$anonimos = (int)$pdo->query('SELECT COUNT(*) FROM aluno_privacidade WHERE ranking_publico = 0')->fetchColumn();
$tipos = ['acesso' => 'Acesso / cópia', 'correcao' => 'Correção', 'exclusao' => 'Exclusão', 'revogacao' => 'Revogação / revisão', 'duvida' => 'Dúvida'];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGPD | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .lg-nums{display:flex;flex-wrap:wrap;gap:12px}
        .lg-nums div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:10px 14px}
        .lg-nums strong{display:block;font-size:22px}.lg-nums span{font-size:12px;color:#6b7280;text-transform:uppercase}
        .lg-ped{border-top:1px solid #f3f4f6;padding:12px 0}.lg-ped textarea{width:100%;min-height:60px}
        .lg-atraso{color:#b42318;font-weight:700}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-top:1px solid #f3f4f6;font-size:14px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>🔒 LGPD e privacidade</h1><p>Pedidos dos alunos sobre os próprios dados. Prazo legal de resposta: <?php echo LGPD_PRAZO_DIAS; ?> dias. Encarregado: <?php echo admin_esc(LGPD_EMAIL_ENCARREGADO); ?>.</p></div>
            <div class="admin-page-actions"><a class="admin-btn" href="privacidade.php" target="_blank">Política</a> <a class="admin-btn" href="termos.php" target="_blank">Termos</a></div>
        </section>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><?php echo admin_esc($sucesso); ?></div><?php endif; ?>
        <section class="admin-panel"><div class="lg-nums">
            <div><span>Pedidos abertos</span><strong><?php echo count($abertas); ?></strong></div>
            <div><span>Aceitaram a versão <?php echo admin_esc(LGPD_VERSAO); ?></span><strong><?php echo $aceites; ?> / <?php echo $alunos; ?></strong></div>
            <div><span>Fora do ranking público</span><strong><?php echo $anonimos; ?></strong></div>
        </div></section>
        <section class="admin-panel">
            <h2>Pedidos abertos</h2>
            <?php if (!$abertas): ?><p>Nenhum pedido aberto. 👍</p><?php endif; ?>
            <?php foreach ($abertas as $s): ?>
            <div class="lg-ped">
                <strong>#<?php echo (int)$s['id']; ?> · <?php echo admin_esc($tipos[$s['tipo']] ?? $s['tipo']); ?></strong> —
                <?php echo admin_esc((string)$s['nome']); ?> (<?php echo admin_esc((string)$s['email']); ?>) ·
                <span class="<?php echo (int)$s['dias'] >= LGPD_PRAZO_DIAS - 3 ? 'lg-atraso' : ''; ?>">há <?php echo (int)$s['dias']; ?> dia(s)</span>
                <?php if (trim((string)$s['detalhe']) !== ''): ?><p><?php echo nl2br(admin_esc((string)$s['detalhe'])); ?></p><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                    <textarea name="resposta" placeholder="Resposta ao aluno (o que foi feito)"></textarea>
                    <button class="admin-btn" name="acao" value="exportar" formnovalidate>⬇ Dados do aluno (JSON)</button>
                    <button class="admin-btn admin-btn-primary" name="acao" value="concluir">Concluído</button>
                    <button class="admin-btn" name="acao" value="negar">Negar (com motivo)</button>
                    <?php if ($s['tipo'] === 'exclusao'): ?>
                        <input name="confirmo" placeholder="Digite EXCLUIR" style="width:130px">
                        <button class="admin-btn" name="acao" value="anonimizar" style="color:#b42318">Excluir e anonimizar conta</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php endforeach; ?>
        </section>
        <section class="admin-panel">
            <h2>Últimos resolvidos</h2>
            <table><tr><th>#</th><th>Aluno</th><th>Tipo</th><th>Status</th><th>Resolvido</th></tr>
            <?php foreach ($fechadas as $s): ?>
                <tr><td><?php echo (int)$s['id']; ?></td><td><?php echo admin_esc((string)$s['nome']); ?></td><td><?php echo admin_esc($tipos[$s['tipo']] ?? $s['tipo']); ?></td><td><?php echo admin_esc($s['status']); ?></td><td><?php echo admin_esc((string)$s['resolvido_em']); ?></td></tr>
            <?php endforeach; ?>
            </table>
        </section>
    </main>
</div>
<script>window.lucide && lucide.createIcons();</script>
</body>
</html>
