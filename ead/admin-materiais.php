<?php
// admin-materiais.php
// Painel administrativo para criar pastas de materiais e adicionar PDFs por curso ou para todos os cursos.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';

if (!function_exists('admin_materiais_bootstrap')) {
    function admin_materiais_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        admin_cursos_bootstrap($pdo);

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_material_pastas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_pastas_curso (curso_id),
            INDEX idx_admin_material_pastas_status (status),
            INDEX idx_admin_material_pastas_ordem (ordem),
            CONSTRAINT fk_admin_material_pastas_curso
                FOREIGN KEY (curso_id) REFERENCES admin_cursos(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_material_arquivos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pasta_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(190) NOT NULL,
            arquivo VARCHAR(255) NOT NULL,
            arquivo_nome VARCHAR(255) NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_arquivos_pasta (pasta_id),
            INDEX idx_admin_material_arquivos_status (status),
            INDEX idx_admin_material_arquivos_ordem (ordem),
            CONSTRAINT fk_admin_material_arquivos_pasta
                FOREIGN KEY (pasta_id) REFERENCES admin_material_pastas(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('admin_materiais_upload_pdf')) {
    function admin_materiais_upload_pdf(string $field, string $oldPath = '', string $oldName = ''): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => $oldPath, 'name' => $oldName];
        }

        if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Não foi possível enviar o PDF. Tente novamente.');
        }

        $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
        $originalName = (string)($_FILES[$field]['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new RuntimeException('Envie somente arquivos PDF.');
        }

        $uploadDir = __DIR__ . '/uploads/materiais';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Não foi possível criar a pasta uploads/materiais.');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.pdf';
        $destino = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new RuntimeException('Não foi possível salvar o PDF enviado.');
        }

        return ['path' => 'uploads/materiais/' . $filename, 'name' => $originalName];
    }
}

if (!function_exists('admin_materiais_int')) {
    function admin_materiais_int(mixed $value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('admin_materiais_destino_texto')) {
    function admin_materiais_destino_texto(?array $pasta): string
    {
        if (!$pasta) {
            return 'Todos os cursos';
        }

        $cursoNome = trim((string)($pasta['curso_nome'] ?? ''));
        return $cursoNome !== '' ? 'Curso: ' . $cursoNome : 'Todos os cursos';
    }
}

$erro = '';
$sucesso = '';
$pdo = null;

try {
    $pdo = admin_cursos_db();
    admin_materiais_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_pasta' || $acao === 'editar_pasta') {
            $pastaId = admin_materiais_int($_POST['pasta_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $destino = (string)($_POST['destino'] ?? 'todos');
            $cursoId = $destino === 'curso' ? admin_materiais_int($_POST['curso_id'] ?? 0) : 0;
            $status = in_array(($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true) ? (string)$_POST['status'] : 'ativo';
            $ordem = admin_materiais_int($_POST['ordem'] ?? 0);

            if ($titulo === '') {
                throw new RuntimeException('Informe o nome da pasta de materiais.');
            }

            if ($destino === 'curso' && $cursoId <= 0) {
                throw new RuntimeException('Selecione o curso que terá acesso a essa pasta.');
            }

            $cursoSql = $cursoId > 0 ? $cursoId : null;

            if ($acao === 'criar_pasta') {
                $stmt = $pdo->prepare('INSERT INTO admin_material_pastas (curso_id, titulo, descricao, status, ordem) VALUES (:curso_id, :titulo, :descricao, :status, :ordem)');
                $stmt->execute([
                    ':curso_id' => $cursoSql,
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':status' => $status,
                    ':ordem' => $ordem,
                ]);
                admin_cursos_redirect('admin-materiais.php?sucesso=pasta_criada');
            }

            if ($pastaId <= 0) {
                throw new RuntimeException('Pasta inválida.');
            }

            $stmt = $pdo->prepare('UPDATE admin_material_pastas SET curso_id = :curso_id, titulo = :titulo, descricao = :descricao, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([
                ':curso_id' => $cursoSql,
                ':titulo' => $titulo,
                ':descricao' => $descricao,
                ':status' => $status,
                ':ordem' => $ordem,
                ':id' => $pastaId,
            ]);
            admin_cursos_redirect('admin-materiais.php?sucesso=pasta_atualizada');
        }

        if ($acao === 'excluir_pasta') {
            $pastaId = admin_materiais_int($_POST['pasta_id'] ?? 0);
            if ($pastaId <= 0) {
                throw new RuntimeException('Pasta inválida.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_material_pastas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pastaId]);
            admin_cursos_redirect('admin-materiais.php?sucesso=pasta_excluida');
        }

        if ($acao === 'adicionar_pdf' || $acao === 'editar_pdf') {
            $arquivoId = admin_materiais_int($_POST['arquivo_id'] ?? 0);
            $pastaId = admin_materiais_int($_POST['pasta_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $status = in_array(($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true) ? (string)$_POST['status'] : 'ativo';
            $ordem = admin_materiais_int($_POST['ordem'] ?? 0);

            if ($pastaId <= 0) {
                throw new RuntimeException('Selecione uma pasta válida.');
            }

            $stmt = $pdo->prepare('SELECT id FROM admin_material_pastas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pastaId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Pasta de material não encontrada.');
            }

            $oldPath = '';
            $oldName = '';
            if ($acao === 'editar_pdf') {
                if ($arquivoId <= 0) {
                    throw new RuntimeException('PDF inválido.');
                }
                $stmt = $pdo->prepare('SELECT * FROM admin_material_arquivos WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $arquivoId]);
                $arquivoAtual = $stmt->fetch();
                if (!$arquivoAtual) {
                    throw new RuntimeException('PDF não encontrado.');
                }
                $oldPath = (string)($arquivoAtual['arquivo'] ?? '');
                $oldName = (string)($arquivoAtual['arquivo_nome'] ?? '');
            }

            $upload = admin_materiais_upload_pdf('arquivo_pdf', $oldPath, $oldName);
            if ($upload['path'] === '') {
                throw new RuntimeException('Envie o PDF do material.');
            }

            if ($titulo === '') {
                $titulo = pathinfo((string)($upload['name'] ?: basename($upload['path'])), PATHINFO_FILENAME) ?: 'Material em PDF';
            }

            if ($acao === 'adicionar_pdf') {
                $stmt = $pdo->prepare('INSERT INTO admin_material_arquivos (pasta_id, titulo, arquivo, arquivo_nome, status, ordem) VALUES (:pasta_id, :titulo, :arquivo, :arquivo_nome, :status, :ordem)');
                $stmt->execute([
                    ':pasta_id' => $pastaId,
                    ':titulo' => $titulo,
                    ':arquivo' => $upload['path'],
                    ':arquivo_nome' => $upload['name'],
                    ':status' => $status,
                    ':ordem' => $ordem,
                ]);
                admin_cursos_redirect('admin-materiais.php?sucesso=pdf_adicionado');
            }

            $stmt = $pdo->prepare('UPDATE admin_material_arquivos SET pasta_id = :pasta_id, titulo = :titulo, arquivo = :arquivo, arquivo_nome = :arquivo_nome, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([
                ':pasta_id' => $pastaId,
                ':titulo' => $titulo,
                ':arquivo' => $upload['path'],
                ':arquivo_nome' => $upload['name'],
                ':status' => $status,
                ':ordem' => $ordem,
                ':id' => $arquivoId,
            ]);
            admin_cursos_redirect('admin-materiais.php?sucesso=pdf_atualizado');
        }

        if ($acao === 'excluir_pdf') {
            $arquivoId = admin_materiais_int($_POST['arquivo_id'] ?? 0);
            if ($arquivoId <= 0) {
                throw new RuntimeException('PDF inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_material_arquivos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $arquivoId]);
            admin_cursos_redirect('admin-materiais.php?sucesso=pdf_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'pasta_criada' => 'Pasta de materiais criada com sucesso.',
    'pasta_atualizada' => 'Pasta de materiais atualizada com sucesso.',
    'pasta_excluida' => 'Pasta de materiais excluída com sucesso.',
    'pdf_adicionado' => 'PDF adicionado com sucesso.',
    'pdf_atualizado' => 'PDF atualizado com sucesso.',
    'pdf_excluido' => 'PDF excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$cursos = [];
$pastas = [];
$arquivosPorPasta = [];
$totalPastas = 0;
$totalPdfs = 0;
$totalTodos = 0;
$totalCursosComMateriais = 0;
$pastaEditando = null;
$arquivoEditando = null;
$pastaAdicionarPdf = null;
$abrirCriarPasta = (string)($_GET['modal'] ?? '') === 'criar_pasta';
$editarPastaId = admin_materiais_int($_GET['editar_pasta'] ?? 0);
$adicionarPdfPastaId = admin_materiais_int($_GET['adicionar_pdf'] ?? 0);
$editarPdfId = admin_materiais_int($_GET['editar_pdf'] ?? 0);

if ($pdo instanceof PDO) {
    try {
        $stmtCursos = $pdo->query("SELECT id, nome FROM admin_cursos WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC");
        $cursos = $stmtCursos ? $stmtCursos->fetchAll() : [];

        $stmtPastas = $pdo->query("\n            SELECT p.*, c.nome AS curso_nome, COUNT(a.id) AS total_pdfs\n            FROM admin_material_pastas p\n            LEFT JOIN admin_cursos c ON c.id = p.curso_id\n            LEFT JOIN admin_material_arquivos a ON a.pasta_id = p.id\n            GROUP BY p.id, p.curso_id, p.titulo, p.descricao, p.status, p.ordem, p.criado_em, p.atualizado_em, c.nome\n            ORDER BY p.ordem ASC, p.id DESC\n        ");
        $pastas = $stmtPastas ? $stmtPastas->fetchAll() : [];
        $totalPastas = count($pastas);
        $totalPdfs = (int)$pdo->query('SELECT COUNT(*) FROM admin_material_arquivos')->fetchColumn();
        $totalTodos = (int)$pdo->query('SELECT COUNT(*) FROM admin_material_pastas WHERE curso_id IS NULL')->fetchColumn();
        $totalCursosComMateriais = (int)$pdo->query('SELECT COUNT(DISTINCT curso_id) FROM admin_material_pastas WHERE curso_id IS NOT NULL')->fetchColumn();

        if ($pastas) {
            $ids = array_map(static fn($p) => (int)$p['id'], $pastas);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtArquivos = $pdo->prepare("SELECT * FROM admin_material_arquivos WHERE pasta_id IN ({$placeholders}) ORDER BY ordem ASC, id DESC");
            $stmtArquivos->execute($ids);
            foreach ($stmtArquivos->fetchAll() as $arquivo) {
                $arquivosPorPasta[(int)$arquivo['pasta_id']][] = $arquivo;
            }
        }

        if ($editarPastaId > 0) {
            $stmt = $pdo->prepare('SELECT p.*, c.nome AS curso_nome FROM admin_material_pastas p LEFT JOIN admin_cursos c ON c.id = p.curso_id WHERE p.id = :id LIMIT 1');
            $stmt->execute([':id' => $editarPastaId]);
            $pastaEditando = $stmt->fetch() ?: null;
        }

        if ($adicionarPdfPastaId > 0) {
            $stmt = $pdo->prepare('SELECT p.*, c.nome AS curso_nome FROM admin_material_pastas p LEFT JOIN admin_cursos c ON c.id = p.curso_id WHERE p.id = :id LIMIT 1');
            $stmt->execute([':id' => $adicionarPdfPastaId]);
            $pastaAdicionarPdf = $stmt->fetch() ?: null;
        }

        if ($editarPdfId > 0) {
            $stmt = $pdo->prepare('SELECT a.*, p.titulo AS pasta_titulo FROM admin_material_arquivos a INNER JOIN admin_material_pastas p ON p.id = a.pasta_id WHERE a.id = :id LIMIT 1');
            $stmt->execute([':id' => $editarPdfId]);
            $arquivoEditando = $stmt->fetch() ?: null;
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar materiais: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiais | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .admin-material-folder-list{display:grid;gap:18px;margin-top:18px}
        .admin-material-folder{background:#fff;border:1px solid rgba(228,231,236,.9);border-radius:10px;box-shadow:var(--admin-dash-sombra-suave);overflow:hidden}
        .admin-material-folder__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:20px;border-bottom:1px solid #eef0f3;background:#fff}
        .admin-material-folder__title{display:flex;align-items:flex-start;gap:14px;min-width:0}
        .admin-material-folder__icon{width:46px;height:46px;border-radius:13px;background:#fff7db;color:var(--admin-dash-amarelo-escuro);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
        .admin-material-folder__icon svg{width:23px;height:23px;stroke-width:2.5}
        .admin-material-folder h3{font-size:1.1rem;color:#111827;font-weight:700;margin:0 0 7px;line-height:1.25}
        .admin-material-folder p{font-size:.9rem;color:#667085;font-weight:500;line-height:1.45;margin:0}
        .admin-material-folder__actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
        .admin-material-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}
        .admin-material-pdfs{display:grid;gap:0;padding:0 20px 18px}
        .admin-material-pdf-row{min-height:58px;display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid #f1f3f5;padding:11px 0}
        .admin-material-pdf-row:last-child{border-bottom:0}.admin-material-pdf-left{display:flex;align-items:center;gap:12px;min-width:0}.admin-material-pdf-left svg{width:20px;height:20px;color:#98a2b3;flex:0 0 auto}.admin-material-pdf-left strong{display:block;color:#111827;font-size:.94rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:480px}.admin-material-pdf-left span{display:block;color:#667085;font-size:.8rem;font-weight:600;margin-top:2px}.admin-material-pdf-actions{display:flex;align-items:center;gap:8px;flex:0 0 auto;flex-wrap:wrap}.admin-material-empty{padding:20px;color:#667085;font-size:.92rem;font-weight:600;background:#fcfcfd;border-top:1px solid #eef0f3}.admin-material-delete-form{display:inline-flex;margin:0}.admin-form-note{grid-column:1/-1;background:#fff7db;border:1px solid #f6d980;color:#8a6100;border-radius:10px;padding:12px 14px;font-size:.88rem;font-weight:700;line-height:1.45}.admin-form-field.is-hidden{display:none}
        @media(max-width:820px){.admin-material-folder__head{display:grid}.admin-material-folder__actions{justify-content:flex-start}.admin-material-pdf-row{align-items:flex-start;display:grid}.admin-material-pdf-actions{justify-content:flex-start}.admin-material-pdf-left strong{max-width:100%}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header" aria-label="Cabeçalho de materiais">
                <div class="admin-page-heading">
                    <h1>Materiais</h1>
                    <p>Crie pastas de materiais, escolha se o acesso é para todos os cursos ou para um curso específico e adicione os PDFs dentro de cada pasta.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-materiais.php?modal=criar_pasta"><i data-lucide="folder-plus" aria-hidden="true"></i>Criar pasta</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div>
            <?php endif; ?>
            <?php if ($sucesso !== ''): ?>
                <div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div>
            <?php endif; ?>

            <section class="admin-stats-grid" aria-label="Resumo dos materiais">
                <article class="admin-stat-card"><div><span>Pastas</span><strong><?php echo (int)$totalPastas; ?></strong></div><div class="admin-stat-icon"><i data-lucide="folder-open" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>PDFs</span><strong><?php echo (int)$totalPdfs; ?></strong></div><div class="admin-stat-icon"><i data-lucide="file-text" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Para todos</span><strong><?php echo (int)$totalTodos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="globe-2" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel" aria-label="Materiais cadastrados">
                <div class="admin-panel-title">
                    <div>
                        <h2>Materiais cadastrados</h2>
                        <small><?php echo (int)$totalCursosComMateriais; ?> curso(s) com pasta específica</small>
                    </div>
                    <a class="admin-btn admin-btn-light admin-btn-small" href="materiais.php" target="_blank" rel="noopener"><i data-lucide="external-link" aria-hidden="true"></i>Ver como aluno</a>
                </div>

                <?php if (!$pastas): ?>
                    <div class="admin-empty"><div><i data-lucide="folder-open" aria-hidden="true"></i><h3>Nenhuma pasta de materiais</h3><p>Clique em criar pasta para começar a organizar os PDFs.</p></div></div>
                <?php else: ?>
                    <div class="admin-material-folder-list">
                        <?php foreach ($pastas as $pasta): ?>
                            <?php
                                $pastaId = (int)$pasta['id'];
                                $arquivos = $arquivosPorPasta[$pastaId] ?? [];
                                $status = (string)($pasta['status'] ?? 'ativo');
                            ?>
                            <article class="admin-material-folder">
                                <header class="admin-material-folder__head">
                                    <div class="admin-material-folder__title">
                                        <span class="admin-material-folder__icon"><i data-lucide="folder" aria-hidden="true"></i></span>
                                        <div>
                                            <h3><?php echo admin_esc((string)$pasta['titulo']); ?></h3>
                                            <p><?php echo admin_esc(admin_cursos_texto_curto((string)($pasta['descricao'] ?? ''), 160)); ?></p>
                                            <div class="admin-material-meta">
                                                <span class="admin-badge <?php echo admin_esc($status); ?>"><?php echo $status === 'ativo' ? 'Ativo' : 'Inativo'; ?></span>
                                                <span class="admin-badge"><?php echo admin_esc(admin_materiais_destino_texto($pasta)); ?></span>
                                                <span class="admin-badge"><?php echo count($arquivos); ?> PDF<?php echo count($arquivos) === 1 ? '' : 's'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="admin-material-folder__actions">
                                        <a class="admin-btn admin-btn-primary admin-btn-small" href="admin-materiais.php?adicionar_pdf=<?php echo $pastaId; ?>"><i data-lucide="file-plus-2" aria-hidden="true"></i>Adicionar PDF</a>
                                        <a class="admin-btn admin-btn-light admin-btn-small" href="admin-materiais.php?editar_pasta=<?php echo $pastaId; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                        <form class="admin-material-delete-form" method="post" onsubmit="return confirm('Excluir esta pasta e todos os PDFs dela?');">
                                            <input type="hidden" name="acao" value="excluir_pasta">
                                            <input type="hidden" name="pasta_id" value="<?php echo $pastaId; ?>">
                                            <button class="admin-btn admin-btn-danger admin-btn-small" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                        </form>
                                    </div>
                                </header>

                                <?php if (!$arquivos): ?>
                                    <div class="admin-material-empty">Nenhum PDF adicionado nesta pasta ainda.</div>
                                <?php else: ?>
                                    <div class="admin-material-pdfs">
                                        <?php foreach ($arquivos as $arquivo): ?>
                                            <?php $arquivoStatus = (string)($arquivo['status'] ?? 'ativo'); ?>
                                            <div class="admin-material-pdf-row">
                                                <div class="admin-material-pdf-left">
                                                    <i data-lucide="file-text" aria-hidden="true"></i>
                                                    <div>
                                                        <strong><?php echo admin_esc((string)$arquivo['titulo']); ?></strong>
                                                        <span><?php echo admin_esc((string)($arquivo['arquivo_nome'] ?: basename((string)$arquivo['arquivo']))); ?> · <?php echo $arquivoStatus === 'ativo' ? 'Ativo' : 'Inativo'; ?></span>
                                                    </div>
                                                </div>
                                                <div class="admin-material-pdf-actions">
                                                    <a class="admin-btn admin-btn-light admin-btn-small" href="<?php echo admin_esc((string)$arquivo['arquivo']); ?>" target="_blank" rel="noopener"><i data-lucide="eye" aria-hidden="true"></i>Ver</a>
                                                    <a class="admin-btn admin-btn-light admin-btn-small" href="admin-materiais.php?editar_pdf=<?php echo (int)$arquivo['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                    <form class="admin-material-delete-form" method="post" onsubmit="return confirm('Excluir este PDF?');">
                                                        <input type="hidden" name="acao" value="excluir_pdf">
                                                        <input type="hidden" name="arquivo_id" value="<?php echo (int)$arquivo['id']; ?>">
                                                        <button class="admin-btn admin-btn-danger admin-btn-small" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php if ($abrirCriarPasta || $pastaEditando): ?>
        <?php
            $editando = is_array($pastaEditando);
            $pastaModal = $editando ? $pastaEditando : ['id' => 0, 'curso_id' => null, 'titulo' => '', 'descricao' => '', 'status' => 'ativo', 'ordem' => 0];
            $destinoAtual = !empty($pastaModal['curso_id']) ? 'curso' : 'todos';
        ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header">
                    <div>
                        <h2><?php echo $editando ? 'Editar pasta' : 'Criar pasta de materiais'; ?></h2>
                        <p>Escolha se a pasta aparece para todos ou somente para alunos de um curso específico.</p>
                    </div>
                    <a class="admin-modal-close" href="admin-materiais.php" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <div class="admin-modal-body">
                    <form method="post" class="admin-form-grid" id="formPastaMaterial">
                        <input type="hidden" name="acao" value="<?php echo $editando ? 'editar_pasta' : 'criar_pasta'; ?>">
                        <?php if ($editando): ?><input type="hidden" name="pasta_id" value="<?php echo (int)$pastaModal['id']; ?>"><?php endif; ?>

                        <div class="admin-form-field full">
                            <label>Nome da pasta</label>
                            <input type="text" name="titulo" value="<?php echo admin_esc((string)$pastaModal['titulo']); ?>" required>
                        </div>
                        <div class="admin-form-field full">
                            <label>Descrição</label>
                            <textarea name="descricao"><?php echo admin_esc((string)($pastaModal['descricao'] ?? '')); ?></textarea>
                        </div>
                        <div class="admin-form-field">
                            <label>Quem pode ver?</label>
                            <select name="destino" id="destinoMaterial">
                                <option value="todos" <?php echo $destinoAtual === 'todos' ? 'selected' : ''; ?>>Todos os cursos</option>
                                <option value="curso" <?php echo $destinoAtual === 'curso' ? 'selected' : ''; ?>>Curso específico</option>
                            </select>
                        </div>
                        <div class="admin-form-field" id="campoCursoMaterial">
                            <label>Curso</label>
                            <select name="curso_id">
                                <option value="0">Selecione o curso</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?php echo (int)$curso['id']; ?>" <?php echo (int)($pastaModal['curso_id'] ?? 0) === (int)$curso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$curso['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label>Status</label>
                            <select name="status">
                                <?php $statusAtual = (string)($pastaModal['status'] ?? 'ativo'); ?>
                                <option value="ativo" <?php echo $statusAtual === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $statusAtual === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label>Ordem</label>
                            <input type="number" name="ordem" value="<?php echo (int)($pastaModal['ordem'] ?? 0); ?>">
                        </div>
                        <div class="admin-form-actions">
                            <a class="admin-btn admin-btn-light" href="admin-materiais.php">Cancelar</a>
                            <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar pasta</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($pastaAdicionarPdf || $arquivoEditando): ?>
        <?php
            $editandoPdf = is_array($arquivoEditando);
            $pdfModal = $editandoPdf ? $arquivoEditando : ['id' => 0, 'pasta_id' => (int)($pastaAdicionarPdf['id'] ?? 0), 'titulo' => '', 'arquivo' => '', 'arquivo_nome' => '', 'status' => 'ativo', 'ordem' => 0];
        ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header">
                    <div>
                        <h2><?php echo $editandoPdf ? 'Editar PDF' : 'Adicionar PDF'; ?></h2>
                        <p><?php echo $editandoPdf ? 'Altere o título, status ou envie outro PDF.' : 'O PDF será liberado dentro da pasta escolhida.'; ?></p>
                    </div>
                    <a class="admin-modal-close" href="admin-materiais.php" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <div class="admin-modal-body">
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="<?php echo $editandoPdf ? 'editar_pdf' : 'adicionar_pdf'; ?>">
                        <?php if ($editandoPdf): ?><input type="hidden" name="arquivo_id" value="<?php echo (int)$pdfModal['id']; ?>"><?php endif; ?>

                        <div class="admin-form-field full">
                            <label>Pasta</label>
                            <select name="pasta_id" required>
                                <?php foreach ($pastas as $pasta): ?>
                                    <option value="<?php echo (int)$pasta['id']; ?>" <?php echo (int)$pdfModal['pasta_id'] === (int)$pasta['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$pasta['titulo'] . ' — ' . admin_materiais_destino_texto($pasta)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-field full">
                            <label>Título do PDF</label>
                            <input type="text" name="titulo" value="<?php echo admin_esc((string)$pdfModal['titulo']); ?>" placeholder="Ex: Apostila de Matemática">
                            <span class="admin-form-hint">Se deixar vazio, o sistema usa o nome do arquivo.</span>
                        </div>
                        <div class="admin-form-field full">
                            <label>Arquivo PDF</label>
                            <input type="file" name="arquivo_pdf" accept="application/pdf,.pdf" <?php echo $editandoPdf ? '' : 'required'; ?>>
                            <?php if ($editandoPdf && !empty($pdfModal['arquivo_nome'])): ?><span class="admin-form-hint">Atual: <?php echo admin_esc((string)$pdfModal['arquivo_nome']); ?>. Envie outro somente se quiser trocar.</span><?php endif; ?>
                        </div>
                        <div class="admin-form-field">
                            <label>Status</label>
                            <select name="status">
                                <?php $statusPdf = (string)($pdfModal['status'] ?? 'ativo'); ?>
                                <option value="ativo" <?php echo $statusPdf === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $statusPdf === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label>Ordem</label>
                            <input type="number" name="ordem" value="<?php echo (int)($pdfModal['ordem'] ?? 0); ?>">
                        </div>
                        <div class="admin-form-actions">
                            <a class="admin-btn admin-btn-light" href="admin-materiais.php">Cancelar</a>
                            <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
    <script>
        (function(){
            var destino = document.getElementById('destinoMaterial');
            var campo = document.getElementById('campoCursoMaterial');
            function atualizarCurso(){
                if (!destino || !campo) return;
                campo.classList.toggle('is-hidden', destino.value !== 'curso');
            }
            if (destino) {
                destino.addEventListener('change', atualizarCurso);
                atualizarCurso();
            }
        })();
    </script>
</body>
</html>
