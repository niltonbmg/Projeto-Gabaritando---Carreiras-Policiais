<?php
// admin-concursos-passados.php
// Painel administrativo para cadastrar de qual concurso/cargo passado a questão foi tirada.
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
        if ($acao === 'criar_concurso' || $acao === 'editar_concurso') {
            $concursoId = admin_bq_int($_POST['concurso_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $bancaId = admin_bq_int($_POST['banca_id'] ?? 0);
            $ano = admin_bq_int($_POST['ano'] ?? 0);
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $status = admin_bq_status($_POST['status'] ?? 'ativo');
            $ordem = (int)($_POST['ordem'] ?? 0);

            if ($titulo === '') {
                throw new RuntimeException('Informe o nome do concurso passado.');
            }
            if ($ano > 0 && ($ano < 1900 || $ano > 2100)) {
                throw new RuntimeException('Informe um ano válido.');
            }
            if ($bancaId > 0) {
                $stmtBanca = $pdo->prepare('SELECT COUNT(*) FROM admin_bancas WHERE id = :id');
                $stmtBanca->execute([':id' => $bancaId]);
                if ((int)$stmtBanca->fetchColumn() === 0) {
                    throw new RuntimeException('Banca inválida para o concurso passado.');
                }
            }

            if ($acao === 'criar_concurso') {
                $stmt = $pdo->prepare('INSERT INTO admin_concursos_passados (titulo, banca_id, ano, descricao, status, ordem) VALUES (:titulo, :banca_id, :ano, :descricao, :status, :ordem)');
                $stmt->execute([
                    ':titulo' => $titulo,
                    ':banca_id' => $bancaId > 0 ? $bancaId : null,
                    ':ano' => $ano > 0 ? $ano : null,
                    ':descricao' => $descricao,
                    ':status' => $status,
                    ':ordem' => $ordem,
                ]);
                admin_cursos_redirect('admin-concursos-passados.php?sucesso=concurso_criado');
            }

            if ($concursoId <= 0) {
                throw new RuntimeException('Concurso passado inválido para edição.');
            }

            $stmt = $pdo->prepare('UPDATE admin_concursos_passados SET titulo = :titulo, banca_id = :banca_id, ano = :ano, descricao = :descricao, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([
                ':titulo' => $titulo,
                ':banca_id' => $bancaId > 0 ? $bancaId : null,
                ':ano' => $ano > 0 ? $ano : null,
                ':descricao' => $descricao,
                ':status' => $status,
                ':ordem' => $ordem,
                ':id' => $concursoId,
            ]);
            admin_cursos_redirect('admin-concursos-passados.php?sucesso=concurso_atualizado');
        }

        if ($acao === 'excluir_concurso') {
            $concursoId = admin_bq_int($_POST['concurso_id'] ?? 0);
            if ($concursoId <= 0) {
                throw new RuntimeException('Concurso passado inválido.');
            }
            $stmt = $pdo->prepare('DELETE FROM admin_concursos_passados WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $concursoId]);
            admin_cursos_redirect('admin-concursos-passados.php?sucesso=concurso_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'concurso_criado' => 'Concurso passado criado com sucesso.',
    'concurso_atualizado' => 'Concurso passado atualizado com sucesso.',
    'concurso_excluido' => 'Concurso passado excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$bancas = [];
$concursos = [];
$totais = ['concursos' => 0, 'ativos' => 0, 'questoes' => 0];
$concursoEditando = null;
$modal = (string)($_GET['modal'] ?? '');
$editarConcursoId = admin_bq_int($_GET['editar'] ?? $_GET['editar_concurso'] ?? 0);

if ($pdo instanceof PDO) {
    try {
        $bancas = $pdo->query("SELECT id, nome, sigla FROM admin_bancas ORDER BY ordem ASC, nome ASC")->fetchAll();
        $concursos = $pdo->query("SELECT cp.*, b.nome AS banca_nome, b.sigla AS banca_sigla,
            (SELECT COUNT(*) FROM admin_banco_questoes q WHERE q.concurso_passado_id = cp.id) AS total_questoes
            FROM admin_concursos_passados cp
            LEFT JOIN admin_bancas b ON b.id = cp.banca_id
            ORDER BY cp.ordem ASC, cp.ano DESC, cp.titulo ASC")->fetchAll();
        $totais['concursos'] = count($concursos);
        $totais['ativos'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_concursos_passados WHERE status = 'ativo'")->fetchColumn();
        $totais['questoes'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_banco_questoes')->fetchColumn();

        if ($editarConcursoId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_concursos_passados WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarConcursoId]);
            $concursoEditando = $stmt->fetch() ?: null;
            if ($concursoEditando) {
                $modal = 'concurso';
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar concursos passados: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$abrirConcurso = $modal === 'concurso' || $modal === 'criar';
$concursoForm = $concursoEditando ?: ['id' => 0, 'titulo' => '', 'banca_id' => 0, 'ano' => '', 'descricao' => '', 'status' => 'ativo', 'ordem' => 0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Concurso Passado | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php admin_cursos_assets(); ?>
    <style>.bq-pill{display:inline-flex;align-items:center;border-radius:999px;background:#fff7db;color:#8a6100;padding:6px 10px;font-size:.78rem;font-weight:700;line-height:1}.bq-muted{color:#667085;font-size:.85rem;font-weight:500}</style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Concurso Passado</h1>
                    <p>Cadastre de onde a questão foi tirada. Esse nome aparece no Banco de Questões para o aluno.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-concursos-passados.php?modal=concurso"><i data-lucide="plus" aria-hidden="true"></i>Criar concurso passado</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Concursos</span><strong><?php echo (int)$totais['concursos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="archive" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Ativos</span><strong><?php echo (int)$totais['ativos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="check-circle-2" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Questões</span><strong><?php echo (int)$totais['questoes']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="circle-help" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Concursos passados cadastrados</h2><small>Gerencie a origem das questões</small></div>
                <?php if (!$concursos): ?>
                    <div class="admin-empty"><div><i data-lucide="archive" aria-hidden="true"></i><h3>Nenhum concurso passado cadastrado</h3><p>Clique em criar concurso passado para começar.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Concurso passado</th><th>Banca</th><th>Ano</th><th>Questões</th><th>Status</th><th>Ordem</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($concursos as $concurso): ?>
                                    <tr>
                                        <td><strong><?php echo admin_esc((string)$concurso['titulo']); ?></strong><br><span><?php echo admin_esc(admin_cursos_texto_curto((string)($concurso['descricao'] ?? ''), 90)); ?></span></td>
                                        <td><?php $bancaLabel = trim((string)(($concurso['banca_sigla'] ?? '') ?: ($concurso['banca_nome'] ?? ''))); echo $bancaLabel !== '' ? '<span class="bq-pill">' . admin_esc($bancaLabel) . '</span>' : '<span class="bq-muted">Sem banca</span>'; ?></td>
                                        <td><?php echo (int)($concurso['ano'] ?? 0) > 0 ? (int)$concurso['ano'] : '-'; ?></td>
                                        <td><?php echo (int)$concurso['total_questoes']; ?></td>
                                        <td><span class="admin-badge <?php echo admin_esc((string)$concurso['status']); ?>"><?php echo admin_esc((string)$concurso['status']); ?></span></td>
                                        <td><?php echo (int)$concurso['ordem']; ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-concursos-passados.php?editar=<?php echo (int)$concurso['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir este concurso passado? As questões vinculadas ficarão sem concurso passado.');">
                                                    <input type="hidden" name="acao" value="excluir_concurso">
                                                    <input type="hidden" name="concurso_id" value="<?php echo (int)$concurso['id']; ?>">
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

    <?php if ($abrirConcurso): ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header">
                    <div><h2><?php echo (int)$concursoForm['id'] > 0 ? 'Editar concurso passado' : 'Criar concurso passado'; ?></h2><p>Informe o concurso/cargo antigo de onde a questão foi retirada.</p></div>
                    <a class="admin-modal-close" href="admin-concursos-passados.php"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <form class="admin-modal-body admin-form-grid" method="post">
                    <input type="hidden" name="acao" value="<?php echo (int)$concursoForm['id'] > 0 ? 'editar_concurso' : 'criar_concurso'; ?>">
                    <input type="hidden" name="concurso_id" value="<?php echo (int)$concursoForm['id']; ?>">
                    <div class="admin-form-field full"><label for="titulo">Nome do concurso passado</label><input id="titulo" name="titulo" type="text" value="<?php echo admin_esc((string)$concursoForm['titulo']); ?>" required placeholder="Ex.: Prefeitura de Rio Novo - Agente Administrativo"></div>
                    <div class="admin-form-field"><label for="banca_id">Banca</label><select id="banca_id" name="banca_id"><option value="0">Sem banca</option><?php foreach ($bancas as $banca): ?><option value="<?php echo (int)$banca['id']; ?>" <?php echo (int)($concursoForm['banca_id'] ?? 0) === (int)$banca['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$banca['nome']); ?><?php echo trim((string)($banca['sigla'] ?? '')) !== '' ? ' / ' . admin_esc((string)$banca['sigla']) : ''; ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="ano">Ano</label><input id="ano" name="ano" type="number" min="1900" max="2100" value="<?php echo (int)($concursoForm['ano'] ?? 0) > 0 ? (int)$concursoForm['ano'] : ''; ?>" placeholder="Ex.: 2024"></div>
                    <div class="admin-form-field full"><label for="descricao">Observação</label><textarea id="descricao" name="descricao" placeholder="Opcional"><?php echo admin_esc((string)($concursoForm['descricao'] ?? '')); ?></textarea></div>
                    <div class="admin-form-field"><label for="status">Status</label><select id="status" name="status"><option value="ativo" <?php echo (string)$concursoForm['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo (string)$concursoForm['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                    <div class="admin-form-field"><label for="ordem">Ordem</label><input id="ordem" name="ordem" type="number" value="<?php echo (int)$concursoForm['ordem']; ?>"></div>
                    <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-concursos-passados.php">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
