<?php
// admin-vendas.php — Pedidos: situação de cada compra, filtros e "Reenviar acesso".
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/pagamentos-lib.php';

$pdo = admin_cursos_db();
pag_bootstrap($pdo);
$e = static fn($s): string => admin_esc((string)$s);
$brl = static fn($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'reenviar') {
    $okEnvio = pag_enviar_acesso($pdo, (int)($_POST['pedido_id'] ?? 0));
    $msg = $okEnvio ? 'Acesso reenviado: o aluno recebe um novo link para criar a senha (vale 72 horas).' : 'Não foi possível reenviar: o pedido ainda não foi pago.';
}
$status = in_array($_GET['status'] ?? '', ['aguardando', 'pago', 'vencido', 'cancelado', 'estornado'], true) ? (string)$_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$where = [];
$par = [];
if ($status !== '') { $where[] = 'o.status = :s'; $par[':s'] = $status; }
if ($q !== '') { $where[] = '(o.nome LIKE :q1 OR o.email LIKE :q2 OR o.cpf LIKE :q3)'; $par[':q1'] = $par[':q2'] = '%' . $q . '%'; $par[':q3'] = '%' . preg_replace('~\D~', '', $q) . '%'; }
$st = $pdo->prepare('SELECT o.*, p.nome produto FROM pedidos o JOIN venda_produtos p ON p.id = o.produto_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.id DESC LIMIT 300');
$st->execute($par);
$pedidos = $st->fetchAll(PDO::FETCH_ASSOC);
$tot = $pdo->query("SELECT status, COUNT(*) n, COALESCE(SUM(valor),0) v FROM pedidos GROUP BY status")->fetchAll(PDO::FETCH_UNIQUE);
$selo = ['aguardando' => ['⏳ aguardando', '#b54708'], 'pago' => ['✅ pago', '#067647'], 'vencido' => ['⌛ vencido', '#6b7280'], 'cancelado' => ['✕ cancelado', '#6b7280'], 'estornado' => ['↩ estornado', '#b42318']];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>.vd-res{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.vd-res a{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;text-decoration:none;color:#111}.vd-res a.on{border-color:#fbb928;background:#fff8e6}.vd-res strong{display:block;font-size:20px}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-top:1px solid #f3f4f6;font-size:13px;vertical-align:top}</style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header"><div class="admin-page-heading"><h1>📦 Pedidos</h1><p>Cada compra feita pelo link de venda. Pagamento confirmado libera o curso sozinho; estorno bloqueia.</p></div>
            <a class="admin-btn admin-btn-light" href="admin-pagamentos.php">💳 Receber pagamentos</a></section>
        <?php if ($msg !== ''): ?><div class="admin-alert admin-alert-success"><?php echo $e($msg); ?></div><?php endif; ?>
        <section class="admin-panel">
            <div class="vd-res">
                <a href="admin-vendas.php" class="<?php echo $status === '' ? 'on' : ''; ?>"><strong><?php echo array_sum(array_column($tot, 'n')); ?></strong>todos</a>
                <?php foreach ($selo as $k => [$t]): ?><a href="admin-vendas.php?status=<?php echo $k; ?>" class="<?php echo $status === $k ? 'on' : ''; ?>"><strong><?php echo (int)($tot[$k]['n'] ?? 0); ?></strong><?php echo $t; ?><?php echo $k === 'pago' ? ' · ' . $brl($tot[$k]['v'] ?? 0) : ''; ?></a><?php endforeach; ?>
            </div>
            <form method="get" style="display:flex;gap:8px;margin-bottom:10px"><input type="hidden" name="status" value="<?php echo $e($status); ?>"><input name="q" value="<?php echo $e($q); ?>" placeholder="Buscar por nome, e-mail ou CPF" style="flex:1;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Buscar</button></form>
            <div style="overflow:auto"><table><tr><th>Nº</th><th>Data</th><th>Comprador</th><th>Produto</th><th>Forma</th><th>Valor</th><th>Situação</th><th></th></tr>
            <?php foreach ($pedidos as $o): $s = $selo[$o['status']] ?? [$o['status'], '#111']; ?>
                <tr>
                    <td><?php echo (int)$o['id']; ?><?php echo $o['modo'] === 'teste' ? ' <small>🧪</small>' : ''; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime((string)$o['criado_em'])); ?></td>
                    <td><?php echo $e($o['nome']); ?><br><small><?php echo $e($o['email']); ?> · <?php echo $e(pag_mascarar_doc((string)$o['cpf'])); ?></small></td>
                    <td><?php echo $e($o['produto']); ?></td>
                    <td><?php echo $e(['pix' => 'Pix', 'cartao' => 'Cartão', 'boleto' => 'Boleto'][$o['forma']] ?? $o['forma']); ?><?php echo (int)$o['parcelas'] > 1 ? ' ' . (int)$o['parcelas'] . 'x' : ''; ?></td>
                    <td><?php echo $brl($o['valor']); ?><?php echo $o['cupom'] ? '<br><small>cupom ' . $e($o['cupom']) . '</small>' : ''; ?></td>
                    <td style="color:<?php echo $s[1]; ?>;font-weight:700"><?php echo $s[0]; ?><?php echo $o['pago_em'] ? '<br><small style="font-weight:400;color:#6b7280">' . date('d/m H:i', strtotime((string)$o['pago_em'])) . '</small>' : ''; ?></td>
                    <td><?php if ($o['status'] === 'pago'): ?><form method="post"><input type="hidden" name="acao" value="reenviar"><input type="hidden" name="pedido_id" value="<?php echo (int)$o['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit" title="<?php echo $o['acesso_enviado_em'] ? 'Último envio: ' . date('d/m H:i', strtotime((string)$o['acesso_enviado_em'])) : ''; ?>">✉️ Reenviar acesso</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pedidos): ?><tr><td colspan="8">Nenhum pedido ainda.</td></tr><?php endif; ?>
            </table></div>
        </section>
    </main>
</div>
<script>if (window.lucide) { lucide.createIcons(); }</script>
</body>
</html>
