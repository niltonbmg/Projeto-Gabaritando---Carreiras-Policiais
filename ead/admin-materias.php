<?php
// admin-materias.php
// Painel administrativo para cadastrar matérias e tópicos usados no banco de questões.
// Modelo correto: sem subtópico.
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
        if ($acao === 'criar_materia' || $acao === 'editar_materia') {
            $materiaId = admin_bq_int($_POST['materia_id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $status = admin_bq_status($_POST['status'] ?? 'ativo');
            $ordem = (int)($_POST['ordem'] ?? 0);

            if ($nome === '') {
                throw new RuntimeException('Informe o nome da matéria.');
            }

            if ($acao === 'criar_materia') {
                $stmt = $pdo->prepare('INSERT INTO admin_materias (nome, descricao, status, ordem) VALUES (:nome, :descricao, :status, :ordem)');
                $stmt->execute([':nome' => $nome, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem]);
                admin_cursos_redirect('admin-materias.php?sucesso=materia_criada');
            }

            if ($materiaId <= 0) {
                throw new RuntimeException('Matéria inválida para edição.');
            }

            $stmt = $pdo->prepare('UPDATE admin_materias SET nome = :nome, descricao = :descricao, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([':nome' => $nome, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem, ':id' => $materiaId]);
            admin_cursos_redirect('admin-materias.php?sucesso=materia_atualizada');
        }

        if ($acao === 'excluir_materia') {
            $materiaId = admin_bq_int($_POST['materia_id'] ?? 0);
            if ($materiaId <= 0) {
                throw new RuntimeException('Matéria inválida.');
            }
            $stmt = $pdo->prepare('DELETE FROM admin_materias WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $materiaId]);
            admin_cursos_redirect('admin-materias.php?sucesso=materia_excluida');
        }

        if ($acao === 'criar_topico' || $acao === 'editar_topico') {
            $topicoId = admin_bq_int($_POST['topico_id'] ?? 0);
            $materiaId = admin_bq_int($_POST['materia_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $status = admin_bq_status($_POST['status'] ?? 'ativo');
            $ordem = (int)($_POST['ordem'] ?? 0);

            if ($materiaId <= 0) {
                throw new RuntimeException('Selecione a matéria do tópico.');
            }
            if ($titulo === '') {
                throw new RuntimeException('Informe o título do tópico.');
            }

            if ($acao === 'criar_topico') {
                $stmt = $pdo->prepare('INSERT INTO admin_materia_topicos (materia_id, parent_id, titulo, descricao, status, ordem) VALUES (:materia_id, NULL, :titulo, :descricao, :status, :ordem)');
                $stmt->execute([':materia_id' => $materiaId, ':titulo' => $titulo, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem]);
                admin_cursos_redirect('admin-materias.php?sucesso=topico_criado');
            }

            if ($topicoId <= 0) {
                throw new RuntimeException('Tópico inválido para edição.');
            }

            $stmt = $pdo->prepare('UPDATE admin_materia_topicos SET materia_id = :materia_id, parent_id = NULL, titulo = :titulo, descricao = :descricao, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([':materia_id' => $materiaId, ':titulo' => $titulo, ':descricao' => $descricao, ':status' => $status, ':ordem' => $ordem, ':id' => $topicoId]);
            admin_cursos_redirect('admin-materias.php?sucesso=topico_atualizado');
        }

        if ($acao === 'excluir_topico') {
            $topicoId = admin_bq_int($_POST['topico_id'] ?? 0);
            if ($topicoId <= 0) {
                throw new RuntimeException('Tópico inválido.');
            }
            $stmt = $pdo->prepare('DELETE FROM admin_materia_topicos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $topicoId]);
            admin_cursos_redirect('admin-materias.php?sucesso=topico_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'materia_criada' => 'Matéria criada com sucesso.',
    'materia_atualizada' => 'Matéria atualizada com sucesso.',
    'materia_excluida' => 'Matéria excluída com sucesso.',
    'topico_criado' => 'Tópico criado com sucesso.',
    'topico_atualizado' => 'Tópico atualizado com sucesso.',
    'topico_excluido' => 'Tópico excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$materias = [];
$topicos = [];
$totais = ['materias' => 0, 'topicos' => 0, 'questoes' => 0];
$materiaEditando = null;
$topicoEditando = null;
$modal = (string)($_GET['modal'] ?? '');
$editarMateriaId = admin_bq_int($_GET['editar_materia'] ?? $_GET['editar'] ?? 0);
$editarTopicoId = admin_bq_int($_GET['editar_topico'] ?? 0);

if ($pdo instanceof PDO) {
    try {
        $materias = $pdo->query("SELECT m.*, (SELECT COUNT(*) FROM admin_materia_topicos t WHERE t.materia_id = m.id) AS total_topicos, (SELECT COUNT(*) FROM admin_banco_questoes q WHERE q.materia_id = m.id) AS total_questoes FROM admin_materias m ORDER BY m.ordem ASC, m.nome ASC")->fetchAll();
        $topicos = $pdo->query("SELECT t.*, m.nome AS materia_nome, (SELECT COUNT(*) FROM admin_banco_questoes q WHERE q.materia_topico_id = t.id) AS total_questoes FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id ORDER BY m.nome ASC, t.ordem ASC, t.titulo ASC")->fetchAll();

        $totais['materias'] = count($materias);
        $totais['topicos'] = count($topicos);
        $totais['questoes'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_banco_questoes')->fetchColumn();

        if ($editarMateriaId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_materias WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarMateriaId]);
            $materiaEditando = $stmt->fetch() ?: null;
            if ($materiaEditando) {
                $modal = 'materia';
            }
        }

        if ($editarTopicoId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_materia_topicos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarTopicoId]);
            $topicoEditando = $stmt->fetch() ?: null;
            if ($topicoEditando) {
                $modal = 'topico';
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar matérias: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$abrirMateria = $modal === 'materia' || $modal === 'criar';
$abrirTopico = $modal === 'topico';
$materiaForm = $materiaEditando ?: ['id' => 0, 'nome' => '', 'descricao' => '', 'status' => 'ativo', 'ordem' => 0];
$topicoForm = $topicoEditando ?: ['id' => 0, 'materia_id' => 0, 'titulo' => '', 'descricao' => '', 'status' => 'ativo', 'ordem' => 0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Matérias | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .bq-pill{display:inline-flex;align-items:center;border-radius:999px;background:#fff7db;color:#8a6100;padding:6px 10px;font-size:.78rem;font-weight:700;line-height:1}.bq-topic-title{display:flex;align-items:center;gap:8px}.bq-topic-title svg{width:16px;height:16px;color:#98a2b3}.bq-muted{color:#667085;font-size:.85rem;font-weight:500}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Matérias</h1>
                    <p>Cadastre matérias e tópicos. Não existe subtópico nesse modelo.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-light" href="admin-materias.php?modal=topico"><i data-lucide="folder-plus" aria-hidden="true"></i>Criar tópico</a>
                    <a class="admin-btn admin-btn-primary" href="admin-materias.php?modal=materia"><i data-lucide="plus" aria-hidden="true"></i>Criar matéria</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Matérias</span><strong><?php echo (int)$totais['materias']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="library" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Tópicos</span><strong><?php echo (int)$totais['topicos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="folder" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Questões</span><strong><?php echo (int)$totais['questoes']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="circle-help" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Matérias cadastradas</h2><small>Gerencie as disciplinas do banco de questões</small></div>
                <?php if (!$materias): ?>
                    <div class="admin-empty"><div><i data-lucide="library" aria-hidden="true"></i><h3>Nenhuma matéria cadastrada</h3><p>Clique em criar matéria para começar.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Matéria</th><th>Tópicos</th><th>Questões</th><th>Status</th><th>Ordem</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($materias as $materia): ?>
                                    <tr>
                                        <td><strong><?php echo admin_esc((string)$materia['nome']); ?></strong><br><span><?php echo admin_esc(admin_cursos_texto_curto((string)($materia['descricao'] ?? ''), 90)); ?></span></td>
                                        <td><?php echo (int)$materia['total_topicos']; ?></td>
                                        <td><?php echo (int)$materia['total_questoes']; ?></td>
                                        <td><span class="admin-badge <?php echo admin_esc((string)$materia['status']); ?>"><?php echo admin_esc((string)$materia['status']); ?></span></td>
                                        <td><?php echo (int)$materia['ordem']; ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-materias.php?editar_materia=<?php echo (int)$materia['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir esta matéria? As questões e tópicos vinculados também serão removidos.');">
                                                    <input type="hidden" name="acao" value="excluir_materia">
                                                    <input type="hidden" name="materia_id" value="<?php echo (int)$materia['id']; ?>">
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

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Tópicos da matéria</h2><small>Esses tópicos aparecem no cadastro e nos filtros das questões</small></div>
                <?php if (!$topicos): ?>
                    <div class="admin-empty"><div><i data-lucide="folder" aria-hidden="true"></i><h3>Nenhum tópico cadastrado</h3><p>Crie uma matéria e depois adicione tópicos.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Tópico</th><th>Matéria</th><th>Questões</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($topicos as $topico): ?>
                                    <tr>
                                        <td><div class="bq-topic-title"><i data-lucide="folder" aria-hidden="true"></i><strong><?php echo admin_esc((string)$topico['titulo']); ?></strong></div><span><?php echo admin_esc(admin_cursos_texto_curto((string)($topico['descricao'] ?? ''), 90)); ?></span></td>
                                        <td><?php echo admin_esc((string)$topico['materia_nome']); ?></td>
                                        <td><?php echo (int)$topico['total_questoes']; ?></td>
                                        <td><span class="admin-badge <?php echo admin_esc((string)$topico['status']); ?>"><?php echo admin_esc((string)$topico['status']); ?></span></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-materias.php?editar_topico=<?php echo (int)$topico['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir este tópico? As questões vinculadas a ele ficarão sem tópico.');">
                                                    <input type="hidden" name="acao" value="excluir_topico">
                                                    <input type="hidden" name="topico_id" value="<?php echo (int)$topico['id']; ?>">
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

    <?php if ($abrirMateria): ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header"><div><h2><?php echo $materiaEditando ? 'Editar matéria' : 'Criar matéria'; ?></h2><p>Informe o nome, descrição, status e ordem de exibição.</p></div><a class="admin-modal-close" href="admin-materias.php"><i data-lucide="x" aria-hidden="true"></i></a></div>
                <form class="admin-modal-body" method="post">
                    <input type="hidden" name="acao" value="<?php echo $materiaEditando ? 'editar_materia' : 'criar_materia'; ?>">
                    <input type="hidden" name="materia_id" value="<?php echo (int)$materiaForm['id']; ?>">
                    <div class="admin-form-grid">
                        <div class="admin-form-field full"><label for="nome">Nome da matéria</label><input id="nome" name="nome" type="text" value="<?php echo admin_esc((string)$materiaForm['nome']); ?>" required></div>
                        <div class="admin-form-field full"><label for="descricao">Descrição</label><textarea id="descricao" name="descricao"><?php echo admin_esc((string)($materiaForm['descricao'] ?? '')); ?></textarea></div>
                        <div class="admin-form-field"><label for="status">Status</label><select id="status" name="status"><option value="ativo" <?php echo (string)$materiaForm['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo (string)$materiaForm['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                        <div class="admin-form-field"><label for="ordem">Ordem</label><input id="ordem" name="ordem" type="number" value="<?php echo (int)$materiaForm['ordem']; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-materias.php">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar</button></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($abrirTopico): ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header"><div><h2><?php echo $topicoEditando ? 'Editar tópico' : 'Criar tópico'; ?></h2><p>Escolha a matéria e cadastre o tópico.</p></div><a class="admin-modal-close" href="admin-materias.php"><i data-lucide="x" aria-hidden="true"></i></a></div>
                <form class="admin-modal-body" method="post">
                    <input type="hidden" name="acao" value="<?php echo $topicoEditando ? 'editar_topico' : 'criar_topico'; ?>">
                    <input type="hidden" name="topico_id" value="<?php echo (int)$topicoForm['id']; ?>">
                    <div class="admin-form-grid">
                        <div class="admin-form-field"><label for="topico_materia">Matéria</label><select id="topico_materia" name="materia_id" required><option value="">Selecione</option><?php foreach ($materias as $materia): ?><option value="<?php echo (int)$materia['id']; ?>" <?php echo (int)$topicoForm['materia_id'] === (int)$materia['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$materia['nome']); ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field full"><label for="topico_titulo">Título do tópico</label><input id="topico_titulo" name="titulo" type="text" value="<?php echo admin_esc((string)$topicoForm['titulo']); ?>" required></div>
                        <div class="admin-form-field full"><label for="topico_descricao">Descrição</label><textarea id="topico_descricao" name="descricao"><?php echo admin_esc((string)($topicoForm['descricao'] ?? '')); ?></textarea></div>
                        <div class="admin-form-field"><label for="topico_status">Status</label><select id="topico_status" name="status"><option value="ativo" <?php echo (string)$topicoForm['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo (string)$topicoForm['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                        <div class="admin-form-field"><label for="topico_ordem">Ordem</label><input id="topico_ordem" name="ordem" type="number" value="<?php echo (int)$topicoForm['ordem']; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-materias.php">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar</button></div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
