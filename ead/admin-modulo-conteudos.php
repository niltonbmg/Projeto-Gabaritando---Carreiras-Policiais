<?php
// admin-modulo-conteudos.php
// Conteúdo do módulo = aqui entra URL do vídeo e PDF, se tiver.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;
$cursoId = admin_cursos_int($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$moduloId = admin_cursos_int($_GET['modulo_id'] ?? $_POST['modulo_id'] ?? 0);

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($cursoId <= 0 || $moduloId <= 0) {
            throw new RuntimeException('Curso ou módulo inválido.');
        }

        if ($acao === 'criar_conteudo') {
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            $duracao = trim((string)($_POST['duracao'] ?? ''));
            $pdfDuracao = trim((string)($_POST['pdf_duracao'] ?? ''));
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);

            if ($titulo === '') {
                throw new RuntimeException('Informe o título do conteúdo.');
            }

            $stmtModuloTopico = $pdo->prepare('SELECT materia_id, topico_id FROM admin_curso_modulos WHERE id = :modulo_id AND curso_id = :curso_id LIMIT 1');
            $stmtModuloTopico->execute([':modulo_id' => $moduloId, ':curso_id' => $cursoId]);
            $moduloTopico = $stmtModuloTopico->fetch() ?: ['materia_id' => null, 'topico_id' => null];

            $pdf = admin_cursos_upload_pdf('pdf_arquivo');
            $stmt = $pdo->prepare('INSERT INTO admin_curso_aulas (curso_id, modulo_id, materia_id, topico_id, titulo, descricao, video_url, pdf_arquivo, pdf_nome, duracao, pdf_duracao, ordem) VALUES (:curso_id, :modulo_id, :materia_id, :topico_id, :titulo, :descricao, :video_url, :pdf_arquivo, :pdf_nome, :duracao, :pdf_duracao, :ordem)');
            $stmt->execute([
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':materia_id' => (int)($moduloTopico['materia_id'] ?? 0) ?: null,
                ':topico_id' => (int)($moduloTopico['topico_id'] ?? 0) ?: null,
                ':titulo' => $titulo,
                ':descricao' => $descricao,
                ':video_url' => $videoUrl,
                ':pdf_arquivo' => $pdf['path'],
                ':pdf_nome' => $pdf['name'],
                ':duracao' => $duracao,
                ':pdf_duracao' => $pdfDuracao,
                ':ordem' => $ordem,
            ]);

            admin_cursos_redirect('admin-modulo-conteudos.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . '&sucesso=conteudo_criado');
        }

        if ($acao === 'editar_conteudo') {
            $conteudoId = admin_cursos_int($_POST['conteudo_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $videoUrl = trim((string)($_POST['video_url'] ?? ''));
            $duracao = trim((string)($_POST['duracao'] ?? ''));
            $pdfDuracao = trim((string)($_POST['pdf_duracao'] ?? ''));
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);
            $removerPdf = isset($_POST['remover_pdf']);

            if ($conteudoId <= 0 || $titulo === '') {
                throw new RuntimeException('Informe os dados do conteúdo corretamente.');
            }

            $stmt = $pdo->prepare('SELECT pdf_arquivo, pdf_nome FROM admin_curso_aulas WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id LIMIT 1');
            $stmt->execute([':id' => $conteudoId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
            $conteudoAtual = $stmt->fetch();
            if (!$conteudoAtual) {
                throw new RuntimeException('Conteúdo não encontrado.');
            }

            $oldPdfPath = $removerPdf ? '' : (string)($conteudoAtual['pdf_arquivo'] ?? '');
            $oldPdfName = $removerPdf ? '' : (string)($conteudoAtual['pdf_nome'] ?? '');
            $pdf = admin_cursos_upload_pdf('pdf_arquivo', $oldPdfPath, $oldPdfName);

            $stmtModuloTopico = $pdo->prepare('SELECT materia_id, topico_id FROM admin_curso_modulos WHERE id = :modulo_id AND curso_id = :curso_id LIMIT 1');
            $stmtModuloTopico->execute([':modulo_id' => $moduloId, ':curso_id' => $cursoId]);
            $moduloTopico = $stmtModuloTopico->fetch() ?: ['materia_id' => null, 'topico_id' => null];

            $stmt = $pdo->prepare('UPDATE admin_curso_aulas SET titulo = :titulo, descricao = :descricao, video_url = :video_url, pdf_arquivo = :pdf_arquivo, pdf_nome = :pdf_nome, duracao = :duracao, pdf_duracao = :pdf_duracao, materia_id = :materia_id, topico_id = :topico_id, ordem = :ordem WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id LIMIT 1');
            $stmt->execute([
                ':titulo' => $titulo,
                ':descricao' => $descricao,
                ':video_url' => $videoUrl,
                ':pdf_arquivo' => $pdf['path'],
                ':pdf_nome' => $pdf['name'],
                ':duracao' => $duracao,
                ':pdf_duracao' => $pdfDuracao,
                ':materia_id' => (int)($moduloTopico['materia_id'] ?? 0) ?: null,
                ':topico_id' => (int)($moduloTopico['topico_id'] ?? 0) ?: null,
                ':ordem' => $ordem,
                ':id' => $conteudoId,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
            ]);

            admin_cursos_redirect('admin-modulo-conteudos.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . '&sucesso=conteudo_atualizado');
        }

        if ($acao === 'excluir_conteudo') {
            $conteudoId = admin_cursos_int($_POST['conteudo_id'] ?? 0);
            if ($conteudoId <= 0) {
                throw new RuntimeException('Conteúdo inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_curso_aulas WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id LIMIT 1');
            $stmt->execute([':id' => $conteudoId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);

            admin_cursos_redirect('admin-modulo-conteudos.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . '&sucesso=conteudo_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'conteudo_criado' => 'Conteúdo criado com sucesso.',
    'conteudo_atualizado' => 'Conteúdo atualizado com sucesso.',
    'conteudo_excluido' => 'Conteúdo excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$curso = null;
$modulo = null;
$conteudos = [];
$conteudoEditando = null;
$editarConteudoId = admin_cursos_int($_GET['editar'] ?? 0);
$abrirCriar = (string)($_GET['modal'] ?? '') === 'criar';

if ($pdo instanceof PDO && $cursoId > 0 && $moduloId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM admin_cursos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $cursoId]);
        $curso = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM admin_curso_modulos WHERE id = :id AND curso_id = :curso_id LIMIT 1');
        $stmt->execute([':id' => $moduloId, ':curso_id' => $cursoId]);
        $modulo = $stmt->fetch() ?: null;

        if ($curso && $modulo) {
            $stmt = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE curso_id = :curso_id AND modulo_id = :modulo_id ORDER BY ordem ASC, id ASC');
            $stmt->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
            $conteudos = $stmt->fetchAll();

            if ($editarConteudoId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id LIMIT 1');
                $stmt->execute([':id' => $editarConteudoId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
                $conteudoEditando = $stmt->fetch() ?: null;
            }
        } else {
            $erro = 'Curso ou módulo não encontrado.';
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar conteúdos: ' . $e->getMessage();
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
    <title>Conteúdos do módulo | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php admin_cursos_assets(); ?>

    <style>
        .conteudo-modal-backdrop{
            align-items:flex-start !important;
            justify-content:center !important;
            padding:14px 18px !important;
            overflow-y:auto !important;
        }
        .conteudo-modal{
            width:min(980px, 100%) !important;
            max-height:calc(100vh - 28px);
            display:flex;
            flex-direction:column;
            overflow:hidden !important;
        }
        .conteudo-modal .admin-modal-header{
            flex:0 0 auto;
        }
        .conteudo-modal .admin-modal-body{
            overflow-y:auto;
            padding-bottom:24px;
        }
        .conteudo-modal .admin-form-actions{
            position:sticky;
            bottom:-24px;
            background:#ffffff;
            border-top:1px solid #eef0f3;
            margin-top:10px;
            padding-top:16px;
            padding-bottom:4px;
            z-index:2;
        }
        @media(max-width:760px){
            .conteudo-modal-backdrop{padding:10px !important;}
            .conteudo-modal{max-height:calc(100vh - 20px);}
            .conteudo-modal .admin-modal-body{padding-bottom:20px;}
            .conteudo-modal .admin-form-actions{bottom:-20px;}
        }
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <div class="admin-breadcrumb"><a href="admin-cursos.php">Cursos</a><i data-lucide="chevron-right" aria-hidden="true"></i><a href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>">Módulos</a><i data-lucide="chevron-right" aria-hidden="true"></i><span>Conteúdos</span></div>

            <section class="admin-page-header" aria-label="Cabeçalho de conteúdos">
                <div class="admin-page-heading">
                    <h1>Conteúdos do módulo</h1>
                    <p><?php echo $modulo ? admin_esc((string)$modulo['titulo']) : 'Selecione um módulo válido.'; ?>. Aqui sim entra o link da videoaula e o PDF, se tiver.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-light" href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>"><i data-lucide="arrow-left" aria-hidden="true"></i>Voltar</a>
                    <?php if ($curso && $modulo): ?><a class="admin-btn admin-btn-primary" href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>&modal=criar"><i data-lucide="plus" aria-hidden="true"></i>Criar conteúdo</a><?php endif; ?>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <?php if ($curso && $modulo): ?>
                <section class="admin-panel" aria-label="Lista de conteúdos">
                    <div class="admin-panel-title"><h2>Videoaulas e PDFs</h2><small><?php echo count($conteudos); ?> cadastrado(s)</small></div>
                    <?php if (!$conteudos): ?>
                        <div class="admin-empty"><div><i data-lucide="play-square" aria-hidden="true"></i><h3>Nenhum conteúdo cadastrado</h3><p>Clique em criar conteúdo para cadastrar a URL do vídeo e o PDF, se tiver.</p></div></div>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Conteúdo</th><th>Vídeo</th><th>PDF</th><th>Vídeo</th><th>PDF/leitura</th><th>Ordem</th><th style="text-align:right">Ações</th></tr></thead>
                                <tbody>
                                <?php foreach ($conteudos as $conteudo): ?>
                                    <?php $conteudoId = (int)$conteudo['id']; ?>
                                    <tr>
                                        <td><strong><?php echo admin_esc((string)$conteudo['titulo']); ?></strong><br><span><?php echo admin_esc(admin_cursos_texto_curto((string)($conteudo['descricao'] ?? ''), 90)); ?></span></td>
                                        <td><?php if (!empty($conteudo['video_url'])): ?><a class="admin-link-inline" href="<?php echo admin_esc((string)$conteudo['video_url']); ?>" target="_blank" rel="noopener">Abrir vídeo</a><?php else: ?><span>Sem URL</span><?php endif; ?></td>
                                        <td><?php if (!empty($conteudo['pdf_arquivo'])): ?><a class="admin-link-inline" href="<?php echo admin_esc((string)$conteudo['pdf_arquivo']); ?>" target="_blank" rel="noopener"><?php echo admin_esc((string)($conteudo['pdf_nome'] ?: 'Abrir PDF')); ?></a><?php else: ?><span>Sem PDF</span><?php endif; ?></td>
                                        <td><?php echo admin_esc((string)($conteudo['duracao'] ?? '')); ?></td>
                                        <td><?php echo admin_esc((string)($conteudo['pdf_duracao'] ?? '')); ?></td>
                                        <td><?php echo (int)$conteudo['ordem']; ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light admin-btn-small" href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>&editar=<?php echo $conteudoId; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir este conteúdo?');">
                                                    <input type="hidden" name="acao" value="excluir_conteudo"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>"><input type="hidden" name="conteudo_id" value="<?php echo $conteudoId; ?>">
                                                    <button class="admin-btn admin-btn-danger admin-btn-small" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
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
            <?php endif; ?>
        </main>
    </div>

    <?php if ($curso && $modulo && ($abrirCriar || $conteudoEditando)): ?>
        <?php $editando = is_array($conteudoEditando); ?>
        <div class="admin-modal-backdrop conteudo-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal conteudo-modal">
                <div class="admin-modal-header"><div><h2><?php echo $editando ? 'Editar conteúdo' : 'Criar conteúdo'; ?></h2><p>Aqui vai a URL do vídeo/YouTube, PDF e os tempos de vídeo/leitura desse tópico.</p></div><a class="admin-modal-close" href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a></div>
                <div class="admin-modal-body">
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="<?php echo $editando ? 'editar_conteudo' : 'criar_conteudo'; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                        <?php if ($editando): ?><input type="hidden" name="conteudo_id" value="<?php echo (int)$conteudoEditando['id']; ?>"><?php endif; ?>
                        <div class="admin-form-field full"><label>Título da aula/conteúdo</label><input type="text" name="titulo" value="<?php echo $editando ? admin_esc((string)$conteudoEditando['titulo']) : ''; ?>" required></div>
                        <div class="admin-form-field full"><label>Descrição</label><textarea name="descricao"><?php echo $editando ? admin_esc((string)($conteudoEditando['descricao'] ?? '')) : ''; ?></textarea></div>
                        <div class="admin-form-field full"><label>URL do vídeo / YouTube</label><input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo $editando ? admin_esc((string)($conteudoEditando['video_url'] ?? '')) : ''; ?>"><span class="admin-form-hint">Vídeo é só por URL. Não tem upload de vídeo aqui.</span></div>
                        <div class="admin-form-field"><label>PDF do conteúdo, se tiver</label><input type="file" name="pdf_arquivo" accept="application/pdf,.pdf"><?php if ($editando && !empty($conteudoEditando['pdf_arquivo'])): ?><span class="admin-form-hint">PDF atual: <?php echo admin_esc((string)($conteudoEditando['pdf_nome'] ?: 'arquivo cadastrado')); ?></span><label class="admin-form-hint"><input type="checkbox" name="remover_pdf" value="1"> Remover PDF atual</label><?php endif; ?></div>
                        <div class="admin-form-field"><label>Tempo do vídeo</label><input type="text" name="duracao" placeholder="Ex: 12 min" value="<?php echo $editando ? admin_esc((string)($conteudoEditando['duracao'] ?? '')) : ''; ?>"><span class="admin-form-hint">Esse tempo soma no progresso da meta quando o aluno marca a aula como assistida.</span></div>
                        <div class="admin-form-field"><label>Tempo do PDF/leitura</label><input type="text" name="pdf_duracao" placeholder="Ex: 20 min" value="<?php echo $editando ? admin_esc((string)($conteudoEditando['pdf_duracao'] ?? '')) : ''; ?>"><span class="admin-form-hint">Use quando o conteúdo também tiver apostila/PDF.</span></div>
                        <div class="admin-form-field"><label>Ordem</label><input type="number" name="ordem" value="<?php echo $editando ? (int)$conteudoEditando['ordem'] : 0; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar conteúdo</button></div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
