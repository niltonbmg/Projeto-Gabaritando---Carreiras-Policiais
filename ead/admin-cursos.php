<?php
// admin-cursos.php
// Curso = somente dados do curso. Módulos e conteúdos ficam em telas separadas.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

require_once __DIR__ . '/includes/curso-acesso.php';
if ($pdo instanceof PDO) {
    gab_prazo_bootstrap($pdo);
}
/** Grava o prazo de acesso padrão do curso (vitalício, X dias, X meses ou até uma data) e o link de renovação. */
function admin_cursos_salvar_prazo(PDO $pdo, int $cursoId): void
{
    $tipo = in_array($_POST['acesso_tipo'] ?? '', ['vitalicio', 'dias', 'meses', 'data'], true) ? (string)$_POST['acesso_tipo'] : 'vitalicio';
    $qtd = max(0, (int)($_POST['acesso_qtd'] ?? 0));
    $data = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($_POST['acesso_data'] ?? '')) ? (string)$_POST['acesso_data'] : null;
    if (($tipo === 'dias' || $tipo === 'meses') && $qtd <= 0) {
        throw new RuntimeException('Informe quantos ' . $tipo . ' de acesso.');
    }
    if ($tipo === 'data' && !$data) {
        throw new RuntimeException('Informe a data final do acesso.');
    }
    $url = trim((string)($_POST['renovar_url'] ?? ''));
    if ($url !== '' && !preg_match('~^https?://~i', $url)) {
        throw new RuntimeException('O link de renovação precisa começar com http:// ou https://');
    }
    $pdo->prepare('UPDATE admin_cursos SET acesso_tipo = :t, acesso_qtd = :q, acesso_data = :d, renovar_url = :u WHERE id = :id')
        ->execute([':t' => $tipo, ':q' => $qtd ?: null, ':d' => $tipo === 'data' ? $data : null, ':u' => $url !== '' ? mb_substr($url, 0, 500) : null, ':id' => $cursoId]);
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_curso') {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $horasCurso = admin_cursos_decimal($_POST['horas_curso'] ?? '0');
            $horasVideo = admin_cursos_decimal($_POST['horas_video'] ?? '0');
            $horasPdf = admin_cursos_decimal($_POST['horas_pdf'] ?? '0');
            $status = in_array(($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true) ? (string)$_POST['status'] : 'ativo';
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);

            if ($nome === '') {
                throw new RuntimeException('Informe o nome do curso.');
            }

            $capa = admin_cursos_upload_image('capa');
            $capaMobile = admin_cursos_upload_image('capa_mobile');
            $capaPagina = admin_cursos_upload_image('capa_pagina');
            $capaPaginaMobile = admin_cursos_upload_image('capa_pagina_mobile');
            $stmt = $pdo->prepare('INSERT INTO admin_cursos (nome, descricao, capa, capa_mobile, capa_pagina, capa_pagina_mobile, horas_curso, horas_video, horas_pdf, status, ordem) VALUES (:nome, :descricao, :capa, :capa_mobile, :capa_pagina, :capa_pagina_mobile, :horas_curso, :horas_video, :horas_pdf, :status, :ordem)');
            $stmt->execute([
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':capa' => $capa,
                ':capa_mobile' => $capaMobile,
                ':capa_pagina' => $capaPagina,
                ':capa_pagina_mobile' => $capaPaginaMobile,
                ':horas_curso' => $horasCurso,
                ':horas_video' => $horasVideo,
                ':horas_pdf' => $horasPdf,
                ':status' => $status,
                ':ordem' => $ordem,
            ]);
            admin_cursos_salvar_prazo($pdo, (int)$pdo->lastInsertId());

            admin_cursos_redirect('admin-cursos.php?sucesso=curso_criado');
        }

        if ($acao === 'editar_curso') {
            $cursoId = admin_cursos_int($_POST['curso_id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $horasCurso = admin_cursos_decimal($_POST['horas_curso'] ?? '0');
            $horasVideo = admin_cursos_decimal($_POST['horas_video'] ?? '0');
            $horasPdf = admin_cursos_decimal($_POST['horas_pdf'] ?? '0');
            $status = in_array(($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true) ? (string)$_POST['status'] : 'ativo';
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);

            if ($cursoId <= 0 || $nome === '') {
                throw new RuntimeException('Informe os dados do curso corretamente.');
            }

            $stmt = $pdo->prepare('SELECT capa, capa_mobile, capa_pagina, capa_pagina_mobile FROM admin_cursos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $cursoId]);
            $cursoAtual = $stmt->fetch();
            if (!$cursoAtual) {
                throw new RuntimeException('Curso não encontrado.');
            }

            $capa = admin_cursos_upload_image('capa', (string)($cursoAtual['capa'] ?? ''));
            $capaMobile = admin_cursos_upload_image('capa_mobile', (string)($cursoAtual['capa_mobile'] ?? ''));
            $capaPagina = admin_cursos_upload_image('capa_pagina', (string)($cursoAtual['capa_pagina'] ?? ''));
            $capaPaginaMobile = admin_cursos_upload_image('capa_pagina_mobile', (string)($cursoAtual['capa_pagina_mobile'] ?? ''));
            $stmt = $pdo->prepare('UPDATE admin_cursos SET nome = :nome, descricao = :descricao, capa = :capa, capa_mobile = :capa_mobile, capa_pagina = :capa_pagina, capa_pagina_mobile = :capa_pagina_mobile, horas_curso = :horas_curso, horas_video = :horas_video, horas_pdf = :horas_pdf, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
            $stmt->execute([
                ':nome' => $nome,
                ':descricao' => $descricao,
                ':capa' => $capa,
                ':capa_mobile' => $capaMobile,
                ':capa_pagina' => $capaPagina,
                ':capa_pagina_mobile' => $capaPaginaMobile,
                ':horas_curso' => $horasCurso,
                ':horas_video' => $horasVideo,
                ':horas_pdf' => $horasPdf,
                ':status' => $status,
                ':ordem' => $ordem,
                ':id' => $cursoId,
            ]);
            admin_cursos_salvar_prazo($pdo, $cursoId);

            admin_cursos_redirect('admin-cursos.php?sucesso=curso_atualizado');
        }

        if ($acao === 'excluir_curso') {
            $cursoId = admin_cursos_int($_POST['curso_id'] ?? 0);
            if ($cursoId <= 0) {
                throw new RuntimeException('Curso inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_cursos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $cursoId]);

            admin_cursos_redirect('admin-cursos.php?sucesso=curso_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'curso_criado' => 'Curso criado com sucesso.',
    'curso_atualizado' => 'Curso atualizado com sucesso.',
    'curso_excluido' => 'Curso excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$cursos = [];
$totalCursos = 0;
$totalModulos = 0;
$totalConteudos = 0;
$cursoEditando = null;
$editarCursoId = admin_cursos_int($_GET['editar'] ?? 0);
$abrirCriar = (string)($_GET['modal'] ?? '') === 'criar';

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM admin_curso_modulos m WHERE m.curso_id = c.id) AS total_modulos, (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.curso_id = c.id) AS total_conteudos FROM admin_cursos c ORDER BY c.ordem ASC, c.id DESC');
        $cursos = $stmt->fetchAll();
        $totalCursos = count($cursos);

        $totalModulos = (int)$pdo->query('SELECT COUNT(*) FROM admin_curso_modulos')->fetchColumn();
        $totalConteudos = (int)$pdo->query('SELECT COUNT(*) FROM admin_curso_aulas')->fetchColumn();

        if ($editarCursoId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_cursos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarCursoId]);
            $cursoEditando = $stmt->fetch() ?: null;
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar cursos: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos | Projeto Gabaritando</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/img/logo.png">
    <link rel="apple-touch-icon" href="/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .curso-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:2px}
        .curso-preview-card{position:relative;border:1px solid #e4e7ec;border-radius:13px;background:#f8fafc;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.04)}
        .curso-preview-card img{width:100%;height:150px;object-fit:cover;display:block;background:#f8fafc}
        .curso-preview-empty{height:150px;display:grid;place-items:center;background:#f8fafc;color:#98a2b3;text-align:center;padding:14px;font-weight:700;font-size:.86rem}
        .curso-preview-empty svg{width:26px;height:26px;margin-bottom:7px;color:#d0d5dd;stroke-width:2.4}
        .curso-preview-label{display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff;border-top:1px solid #e4e7ec;color:#344054;font-size:.86rem;font-weight:700}
        .curso-preview-label svg{width:17px;height:17px;color:#d89d00;stroke-width:2.5}
        .curso-preview-input{position:absolute;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none}
        .curso-preview-overlay{position:absolute;inset:0 0 41px;background:rgba(0,0,0,.56);display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity .18s ease, visibility .18s ease;cursor:pointer;color:#fff;font-weight:700;letter-spacing:.01em;text-align:center;padding:16px}
        .curso-preview-card:hover .curso-preview-overlay,.curso-preview-card:focus-within .curso-preview-overlay{opacity:1;visibility:visible}
        .curso-preview-overlay span{display:inline-flex;align-items:center;gap:9px;border:1px solid rgba(255,255,255,.45);border-radius:999px;background:rgba(250,181,40,.95);color:#111;padding:10px 14px;box-shadow:0 12px 28px rgba(0,0,0,.22)}
        .curso-preview-overlay svg{width:19px;height:19px;stroke-width:2.5}
        .curso-preview-card.is-selected{border-color:var(--admin-amarelo, #fdbd08);box-shadow:0 0 0 3px rgba(253,189,8,.18)}
        .curso-preview-note{margin-top:8px;color:#667085;font-size:.84rem;font-weight:700;line-height:1.35}
        @media(max-width:720px){.curso-preview-grid{grid-template-columns:1fr}.curso-preview-card img,.curso-preview-empty{height:135px}.curso-preview-overlay{opacity:1;visibility:visible;background:rgba(0,0,0,.36)}}

        /* Correção do modal de criar/editar curso: deixa tudo visível e o botão salvar sempre acessível. */
        .admin-modal-backdrop{
            align-items:flex-start !important;
            justify-content:center !important;
            padding:18px 18px !important;
            overflow-y:auto !important;
        }

        .admin-modal{
            width:min(980px, calc(100vw - 36px)) !important;
            max-height:calc(100vh - 36px) !important;
            display:flex !important;
            flex-direction:column !important;
            overflow:hidden !important;
        }

        .admin-modal-header{
            flex:0 0 auto !important;
        }

        .admin-modal-body{
            flex:1 1 auto !important;
            min-height:0 !important;
            overflow-y:auto !important;
            padding:24px 24px 0 !important;
            scrollbar-gutter:stable;
        }

        .admin-modal-body::-webkit-scrollbar{width:10px}
        .admin-modal-body::-webkit-scrollbar-track{background:#f1f3f6;border-radius:999px}
        .admin-modal-body::-webkit-scrollbar-thumb{background:#c6ccd6;border-radius:999px;border:2px solid #f1f3f6}

        .admin-modal-body .admin-form-grid{
            padding-bottom:0 !important;
        }

        .admin-modal-body .admin-form-actions{
            position:sticky !important;
            bottom:0 !important;
            z-index:20 !important;
            background:#fff !important;
            border-top:1px solid #eef0f3 !important;
            padding:16px 0 18px !important;
            margin-top:8px !important;
        }

        @media(max-width:760px){
            .admin-modal-backdrop{padding:10px !important;}
            .admin-modal{width:100% !important;max-height:calc(100vh - 20px) !important;}
            .admin-modal-body{padding:18px 18px 0 !important;}
            .admin-modal-body .admin-form-actions{justify-content:stretch !important;}
            .admin-modal-body .admin-form-actions .admin-btn{width:100%;}
        }
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header" aria-label="Cabeçalho de cursos">
                <div class="admin-page-heading">
                    <h1>Cursos</h1>
                    <p>Crie o curso com nome, descrição, capas e horas. Depois entre em módulos e, dentro de cada módulo, cadastre as videoaulas e PDFs.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-cursos.php?modal=criar"><i data-lucide="plus" aria-hidden="true"></i>Criar curso</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div>
            <?php endif; ?>
            <?php if ($sucesso !== ''): ?>
                <div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div>
            <?php endif; ?>

            <section class="admin-stats-grid" aria-label="Resumo dos cursos">
                <article class="admin-stat-card"><div><span>Cursos</span><strong><?php echo (int)$totalCursos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="graduation-cap" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Módulos</span><strong><?php echo (int)$totalModulos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="layers-3" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Conteúdos</span><strong><?php echo (int)$totalConteudos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="play-square" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-grid" aria-label="Cursos cadastrados">
                <?php if (!$cursos): ?>
                    <div class="admin-empty"><div><i data-lucide="graduation-cap" aria-hidden="true"></i><h3>Nenhum curso cadastrado</h3><p>Clique em criar curso para cadastrar o primeiro.</p></div></div>
                <?php else: ?>
                    <?php foreach ($cursos as $curso): ?>
                        <?php $cursoId = (int)$curso['id']; ?>
                        <article class="admin-card">
                            <div class="admin-card-cover">
                                <?php echo admin_curso_capa_html((string)($curso['capa'] ?? ''), (string)$curso['nome']); ?>
                                <div class="admin-actions">
                                    <button class="admin-three-btn" type="button" data-admin-actions-btn aria-label="Opções do curso"><i data-lucide="more-vertical" aria-hidden="true"></i></button>
                                    <div class="admin-dropdown">
                                        <a href="admin-cursos.php?editar=<?php echo $cursoId; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar curso</a>
                                        <a href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>"><i data-lucide="layers-3" aria-hidden="true"></i>Módulos</a>
                                        <form method="post" onsubmit="return confirm('Excluir este curso, módulos e conteúdos?');">
                                            <input type="hidden" name="acao" value="excluir_curso">
                                            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                            <button class="danger" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="admin-card-body">
                                <h3><?php echo admin_esc((string)$curso['nome']); ?></h3>
                                <p><?php echo admin_esc(admin_cursos_texto_curto((string)($curso['descricao'] ?? ''), 120)); ?></p>
                                <div class="admin-card-meta">
                                    <span><i data-lucide="clock" aria-hidden="true"></i><?php echo admin_cursos_horas($curso['horas_curso'] ?? 0); ?> curso</span>
                                    <span><i data-lucide="video" aria-hidden="true"></i><?php echo admin_cursos_horas($curso['horas_video'] ?? 0); ?> vídeo</span>
                                    <span><i data-lucide="file-text" aria-hidden="true"></i><?php echo admin_cursos_horas($curso['horas_pdf'] ?? 0); ?> PDF</span>
                                    <span><i data-lucide="layers-3" aria-hidden="true"></i><?php echo (int)($curso['total_modulos'] ?? 0); ?> módulos</span>
                                </div>
                                <div class="admin-card-footer">
                                    <span class="admin-badge <?php echo admin_esc((string)$curso['status']); ?>"><?php echo admin_esc(ucfirst((string)$curso['status'])); ?></span>
                                    <a class="admin-btn admin-btn-light admin-btn-small" href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>"><i data-lucide="arrow-right" aria-hidden="true"></i>Entrar</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php if ($abrirCriar || $cursoEditando): ?>
        <?php $editando = is_array($cursoEditando); ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header">
                    <div>
                        <h2><?php echo $editando ? 'Editar curso' : 'Criar curso'; ?></h2>
                        <p>Aqui é somente o cadastro do curso. Módulos e conteúdos ficam nas próximas telas.</p>
                    </div>
                    <a class="admin-modal-close" href="admin-cursos.php" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <div class="admin-modal-body">
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="<?php echo $editando ? 'editar_curso' : 'criar_curso'; ?>">
                        <?php if ($editando): ?><input type="hidden" name="curso_id" value="<?php echo (int)$cursoEditando['id']; ?>"><?php endif; ?>

                        <div class="admin-form-field full">
                            <label>Nome do curso</label>
                            <input type="text" name="nome" value="<?php echo $editando ? admin_esc((string)$cursoEditando['nome']) : ''; ?>" required>
                        </div>
                        <div class="admin-form-field full">
                            <label>Descrição</label>
                            <textarea name="descricao"><?php echo $editando ? admin_esc((string)($cursoEditando['descricao'] ?? '')) : ''; ?></textarea>
                        </div>
                        <?php
                            $capaCursoPc = $editando ? trim((string)($cursoEditando['capa'] ?? '')) : '';
                            $capaCursoMobile = $editando ? trim((string)($cursoEditando['capa_mobile'] ?? '')) : '';
                            $capaPaginaPc = $editando ? trim((string)($cursoEditando['capa_pagina'] ?? '')) : '';
                            $capaPaginaMobile = $editando ? trim((string)($cursoEditando['capa_pagina_mobile'] ?? '')) : '';
                        ?>
                        <div class="admin-form-field full">
                            <label>Capas atuais do curso</label>
                            <div class="curso-preview-grid">
                                <div class="curso-preview-card">
                                    <?php if ($capaCursoPc !== ''): ?>
                                        <img src="<?php echo admin_esc($capaCursoPc); ?>" alt="Capa atual do curso no computador">
                                    <?php else: ?>
                                        <div class="curso-preview-empty"><div><i data-lucide="image" aria-hidden="true"></i><br>Nenhuma capa PC cadastrada</div></div>
                                    <?php endif; ?>
                                    <input class="curso-preview-input" id="capa" type="file" name="capa" accept="image/*">
                                    <label class="curso-preview-overlay" for="capa"><span><i data-lucide="upload-cloud" aria-hidden="true"></i>Alterar capa</span></label>
                                    <div class="curso-preview-label"><i data-lucide="monitor" aria-hidden="true"></i>Card do curso - PC</div>
                                </div>
                                <div class="curso-preview-card">
                                    <?php if ($capaCursoMobile !== ''): ?>
                                        <img src="<?php echo admin_esc($capaCursoMobile); ?>" alt="Capa atual do curso no celular">
                                    <?php else: ?>
                                        <div class="curso-preview-empty"><div><i data-lucide="image" aria-hidden="true"></i><br>Nenhuma capa mobile cadastrada</div></div>
                                    <?php endif; ?>
                                    <input class="curso-preview-input" id="capa_mobile" type="file" name="capa_mobile" accept="image/*">
                                    <label class="curso-preview-overlay" for="capa_mobile"><span><i data-lucide="upload-cloud" aria-hidden="true"></i>Alterar capa</span></label>
                                    <div class="curso-preview-label"><i data-lucide="smartphone" aria-hidden="true"></i>Card do curso - Mobile</div>
                                </div>
                                <div class="curso-preview-card">
                                    <?php if ($capaPaginaPc !== ''): ?>
                                        <img src="<?php echo admin_esc($capaPaginaPc); ?>" alt="Capa atual da página do curso no computador">
                                    <?php else: ?>
                                        <div class="curso-preview-empty"><div><i data-lucide="image" aria-hidden="true"></i><br>Nenhuma capa da página PC cadastrada</div></div>
                                    <?php endif; ?>
                                    <input class="curso-preview-input" id="capa_pagina" type="file" name="capa_pagina" accept="image/*">
                                    <label class="curso-preview-overlay" for="capa_pagina"><span><i data-lucide="upload-cloud" aria-hidden="true"></i>Alterar capa</span></label>
                                    <div class="curso-preview-label"><i data-lucide="image" aria-hidden="true"></i>Página do curso - PC</div>
                                </div>
                                <div class="curso-preview-card">
                                    <?php if ($capaPaginaMobile !== ''): ?>
                                        <img src="<?php echo admin_esc($capaPaginaMobile); ?>" alt="Capa atual da página do curso no celular">
                                    <?php else: ?>
                                        <div class="curso-preview-empty"><div><i data-lucide="image" aria-hidden="true"></i><br>Nenhuma capa da página mobile cadastrada</div></div>
                                    <?php endif; ?>
                                    <input class="curso-preview-input" id="capa_pagina_mobile" type="file" name="capa_pagina_mobile" accept="image/*">
                                    <label class="curso-preview-overlay" for="capa_pagina_mobile"><span><i data-lucide="upload-cloud" aria-hidden="true"></i>Alterar capa</span></label>
                                    <div class="curso-preview-label"><i data-lucide="smartphone" aria-hidden="true"></i>Página do curso - Mobile</div>
                                </div>
                            </div>
                            <p class="curso-preview-note">Passe o mouse em cima de uma capa e clique em alterar capa. No celular, toque sobre a capa.</p>
                        </div>

                        <div class="admin-form-field">
                            <label>Status</label>
                            <select name="status">
                                <?php $statusAtual = $editando ? (string)$cursoEditando['status'] : 'ativo'; ?>
                                <option value="ativo" <?php echo $statusAtual === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo $statusAtual === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label>Horas de curso</label>
                            <input type="text" name="horas_curso" placeholder="Ex: 40" value="<?php echo $editando ? admin_esc((string)($cursoEditando['horas_curso'] ?? '0')) : ''; ?>">
                        </div>
                        <div class="admin-form-field">
                            <label>Horas em vídeo</label>
                            <input type="text" name="horas_video" placeholder="Ex: 25" value="<?php echo $editando ? admin_esc((string)($cursoEditando['horas_video'] ?? '0')) : ''; ?>">
                        </div>
                        <div class="admin-form-field">
                            <label>Horas em PDF</label>
                            <input type="text" name="horas_pdf" placeholder="Ex: 15" value="<?php echo $editando ? admin_esc((string)($cursoEditando['horas_pdf'] ?? '0')) : ''; ?>">
                        </div>
                        <?php $pz = $editando ? gab_prazo_regra($pdo, (int)$cursoEditando['id']) : ['tipo' => 'vitalicio', 'qtd' => 0, 'data' => '', 'renovar_url' => '']; ?>
                        <div class="admin-form-field">
                            <label for="acessoTipo">Prazo de acesso padrão</label>
                            <select name="acesso_tipo" id="acessoTipo" onchange="document.getElementById('acessoQtdBox').style.display=(this.value==='dias'||this.value==='meses')?'':'none';document.getElementById('acessoDataBox').style.display=this.value==='data'?'':'none'">
                                <option value="vitalicio" <?php echo $pz['tipo'] === 'vitalicio' ? 'selected' : ''; ?>>Vitalício</option>
                                <option value="dias" <?php echo $pz['tipo'] === 'dias' ? 'selected' : ''; ?>>X dias a partir da matrícula</option>
                                <option value="meses" <?php echo $pz['tipo'] === 'meses' ? 'selected' : ''; ?>>X meses a partir da matrícula</option>
                                <option value="data" <?php echo $pz['tipo'] === 'data' ? 'selected' : ''; ?>>Até uma data fixa (ex.: data da prova + 30 dias)</option>
                            </select>
                        </div>
                        <div class="admin-form-field" id="acessoQtdBox" style="<?php echo in_array($pz['tipo'], ['dias', 'meses'], true) ? '' : 'display:none'; ?>">
                            <label>Quantidade (dias ou meses)</label>
                            <input type="number" min="1" name="acesso_qtd" value="<?php echo $pz['qtd'] ?: ''; ?>" placeholder="Ex: 12">
                        </div>
                        <div class="admin-form-field" id="acessoDataBox" style="<?php echo $pz['tipo'] === 'data' ? '' : 'display:none'; ?>">
                            <label>Acesso até</label>
                            <input type="date" name="acesso_data" value="<?php echo admin_esc($pz['data']); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label>Link "Renovar" (página de venda)</label>
                            <input type="url" name="renovar_url" placeholder="https://..." value="<?php echo admin_esc($pz['renovar_url']); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label>Ordem</label>
                            <input type="number" name="ordem" value="<?php echo $editando ? (int)$cursoEditando['ordem'] : 0; ?>">
                        </div>
                        <div class="admin-form-actions">
                            <a class="admin-btn admin-btn-light" href="admin-cursos.php">Cancelar</a>
                            <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar curso</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>

    <script>
        document.querySelectorAll('.curso-preview-input').forEach(function(input){
            input.addEventListener('change', function(){
                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file || !file.type || !file.type.startsWith('image/')) return;
                const card = input.closest('.curso-preview-card');
                if (!card) return;
                const url = URL.createObjectURL(file);
                let img = card.querySelector('img');
                const empty = card.querySelector('.curso-preview-empty');
                if (!img) {
                    img = document.createElement('img');
                    img.alt = 'Nova capa selecionada';
                    card.insertBefore(img, card.firstChild);
                }
                img.src = url;
                if (empty) empty.remove();
                card.classList.add('is-selected');
                const overlay = card.querySelector('.curso-preview-overlay span');
                if (overlay) overlay.innerHTML = '<i data-lucide="check-circle-2" aria-hidden="true"></i>Capa selecionada';
                if (window.lucide) window.lucide.createIcons();
            });
        });
    </script>
</body>
</html>
