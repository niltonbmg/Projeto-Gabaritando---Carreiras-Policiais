<?php
// admin-bancas.php
// Painel administrativo para cadastrar bancas usadas no banco de questões.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-banco-questoes-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;

try {
    $pdo = admin_cursos_db();
    admin_bq_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro ao conectar no banco: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_banca' || $acao === 'editar_banca') {
            $bancaId = admin_bq_int($_POST['banca_id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $sigla = trim((string)($_POST['sigla'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $status = admin_bq_status($_POST['status'] ?? 'ativo');
            $ordem = (int)($_POST['ordem'] ?? 0);

            if ($nome === '') {
                throw new RuntimeException('Informe o nome da banca.');
            }

            if ($acao === 'criar_banca') {
                $stmt = $pdo->prepare('INSERT INTO admin_bancas (nome, sigla, descricao, status, ordem) VALUES (:nome, :sigla, :descricao, :status, :ordem)');
                $stmt->execute([':nome' => $nome, ':sigla' => $sigla, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem]);
                admin_cursos_redirect('admin-bancas.php?sucesso=banca_criada');
            }

            if ($bancaId <= 0) {
                throw new RuntimeException('Banca inválida para edição.');
            }

            $stmt = $pdo->prepare('UPDATE admin_bancas SET nome = :nome, sigla = :sigla, descricao = :descricao, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([':nome' => $nome, ':sigla' => $sigla, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem, ':id' => $bancaId]);
            admin_cursos_redirect('admin-bancas.php?sucesso=banca_atualizada');
        }

        if ($acao === 'excluir_banca') {
            $bancaId = admin_bq_int($_POST['banca_id'] ?? 0);
            if ($bancaId <= 0) {
                throw new RuntimeException('Banca inválida.');
            }
            $stmt = $pdo->prepare('DELETE FROM admin_bancas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $bancaId]);
            admin_cursos_redirect('admin-bancas.php?sucesso=banca_excluida');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'banca_criada' => 'Banca criada com sucesso.',
    'banca_atualizada' => 'Banca atualizada com sucesso.',
    'banca_excluida' => 'Banca excluída com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$bancas = [];
$totais = ['bancas' => 0, 'questoes' => 0, 'ativas' => 0];
$bancaEditando = null;
$modal = (string)($_GET['modal'] ?? '');
$editarBancaId = admin_bq_int($_GET['editar'] ?? $_GET['editar_banca'] ?? 0);

if ($pdo instanceof PDO) {
    try {
        $bancas = $pdo->query("SELECT b.*, (SELECT COUNT(*) FROM admin_banco_questoes q WHERE q.banca_id = b.id) AS total_questoes FROM admin_bancas b ORDER BY b.ordem ASC, b.nome ASC")->fetchAll();
        $totais['bancas'] = count($bancas);
        $totais['questoes'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_banco_questoes')->fetchColumn();
        $totais['ativas'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_bancas WHERE status = 'ativo'")->fetchColumn();

        if ($editarBancaId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_bancas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarBancaId]);
            $bancaEditando = $stmt->fetch() ?: null;
            if ($bancaEditando) {
                $modal = 'banca';
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar bancas: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$abrirBanca = $modal === 'banca' || $modal === 'criar';
$bancaForm = $bancaEditando ?: ['id' => 0, 'nome' => '', 'sigla' => '', 'descricao' => '', 'status' => 'ativo', 'ordem' => 0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Bancas | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .bq-pill{display:inline-flex;align-items:center;border-radius:999px;background:#fff7db;color:#8a6100;padding:6px 10px;font-size:.78rem;font-weight:700;line-height:1}.bq-muted{color:#667085;font-size:.85rem;font-weight:500}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Bancas</h1>
                    <p>Cadastre somente a banca organizadora. Tópicos ficam dentro de Matérias.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-bancas.php?modal=banca"><i data-lucide="plus" aria-hidden="true"></i>Criar banca</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Bancas</span><strong><?php echo (int)$totais['bancas']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="landmark" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Bancas ativas</span><strong><?php echo (int)$totais['ativas']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="check-circle-2" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Questões</span><strong><?php echo (int)$totais['questoes']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="circle-help" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Bancas cadastradas</h2><small>Gerencie as bancas usadas no cadastro das questões</small></div>
                <?php if (!$bancas): ?>
                    <div class="admin-empty"><div><i data-lucide="landmark" aria-hidden="true"></i><h3>Nenhuma banca cadastrada</h3><p>Clique em criar banca para começar.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Banca</th><th>Sigla</th><th>Questões</th><th>Status</th><th>Ordem</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($bancas as $banca): ?>
                                    <tr>
                                        <td><strong><?php echo admin_esc((string)$banca['nome']); ?></strong><br><span><?php echo admin_esc(admin_cursos_texto_curto((string)($banca['descricao'] ?? ''), 90)); ?></span></td>
                                        <td><?php echo $banca['sigla'] !== '' ? '<span class="bq-pill">' . admin_esc((string)$banca['sigla']) . '</span>' : '<span class="bq-muted">Sem sigla</span>'; ?></td>
                                        <td><?php echo (int)$banca['total_questoes']; ?></td>
                                        <td><span class="admin-badge <?php echo admin_esc((string)$banca['status']); ?>"><?php echo admin_esc((string)$banca['status']); ?></span></td>
                                        <td><?php echo (int)$banca['ordem']; ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-bancas.php?editar=<?php echo (int)$banca['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir esta banca? As questões vinculadas também serão removidas.');">
                                                    <input type="hidden" name="acao" value="excluir_banca">
                                                    <input type="hidden" name="banca_id" value="<?php echo (int)$banca['id']; ?>">
                                                    <button class="admin-btn admin-btn-danger" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php if ($abrirBanca): ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header"><div><h2><?php echo $bancaEditando ? 'Editar banca' : 'Criar banca'; ?></h2><p>Cadastre o nome, sigla e ordem da banca.</p></div><a class="admin-modal-close" href="admin-bancas.php"><i data-lucide="x" aria-hidden="true"></i></a></div>
                <form class="admin-modal-body" method="post">
                    <input type="hidden" name="acao" value="<?php echo $bancaEditando ? 'editar_banca' : 'criar_banca'; ?>">
                    <input type="hidden" name="banca_id" value="<?php echo (int)$bancaForm['id']; ?>">
                    <div class="admin-form-grid">
                        <div class="admin-form-field"><label for="banca_nome">Nome da banca</label><input id="banca_nome" name="nome" type="text" value="<?php echo admin_esc((string)$bancaForm['nome']); ?>" required></div>
                        <div class="admin-form-field"><label for="sigla">Sigla</label><input id="sigla" name="sigla" type="text" value="<?php echo admin_esc((string)($bancaForm['sigla'] ?? '')); ?>" placeholder="Ex.: CESPE"></div>
                        <div class="admin-form-field full"><label for="banca_descricao">Descrição</label><textarea id="banca_descricao" name="descricao"><?php echo admin_esc((string)($bancaForm['descricao'] ?? '')); ?></textarea></div>
                        <div class="admin-form-field"><label for="banca_status">Status</label><select id="banca_status" name="status"><option value="ativo" <?php echo (string)$bancaForm['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo (string)$bancaForm['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                        <div class="admin-form-field"><label for="banca_ordem">Ordem</label><input id="banca_ordem" name="ordem" type="number" value="<?php echo (int)$bancaForm['ordem']; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-bancas.php">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar</button></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
