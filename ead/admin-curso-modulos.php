<?php
// admin-curso-modulos.php
// Módulo = somente organização do curso. Não tem vídeo e não tem PDF aqui.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;
$cursoId = admin_cursos_int($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

function admin_modulos_materia_topico_validos(PDO $pdo, int $materiaId, int $topicoId): bool
{
    if ($materiaId <= 0 || $topicoId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_materia_topicos WHERE id = ? AND materia_id = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$topicoId, $materiaId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($cursoId <= 0) {
            throw new RuntimeException('Curso inválido.');
        }

        if ($acao === 'criar_modulo') {
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $materiaId = admin_cursos_int($_POST['materia_id'] ?? 0);
            $topicoId = admin_cursos_int($_POST['topico_id'] ?? 0);
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);

            if ($titulo === '') {
                throw new RuntimeException('Informe o título do módulo.');
            }
            if ($materiaId <= 0) {
                throw new RuntimeException('Selecione a matéria desse módulo.');
            }
            if ($topicoId <= 0 || !admin_modulos_materia_topico_validos($pdo, $materiaId, $topicoId)) {
                throw new RuntimeException('Selecione um tópico válido da matéria.');
            }

            $capa = admin_cursos_upload_image('capa');
            $stmt = $pdo->prepare('INSERT INTO admin_curso_modulos (curso_id, titulo, descricao, capa, materia_id, topico_id, ordem) VALUES (:curso_id, :titulo, :descricao, :capa, :materia_id, :topico_id, :ordem)');
            $stmt->execute([':curso_id' => $cursoId, ':titulo' => $titulo, ':descricao' => $descricao, ':capa' => $capa, ':materia_id' => $materiaId, ':topico_id' => $topicoId, ':ordem' => $ordem]);

            admin_cursos_redirect('admin-curso-modulos.php?curso_id=' . $cursoId . '&sucesso=modulo_criado');
        }

        if ($acao === 'editar_modulo') {
            $moduloId = admin_cursos_int($_POST['modulo_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $materiaId = admin_cursos_int($_POST['materia_id'] ?? 0);
            $topicoId = admin_cursos_int($_POST['topico_id'] ?? 0);
            $ordem = admin_cursos_int($_POST['ordem'] ?? 0);

            if ($moduloId <= 0 || $titulo === '') {
                throw new RuntimeException('Informe os dados do módulo corretamente.');
            }
            if ($materiaId <= 0) {
                throw new RuntimeException('Selecione a matéria desse módulo.');
            }
            if ($topicoId <= 0 || !admin_modulos_materia_topico_validos($pdo, $materiaId, $topicoId)) {
                throw new RuntimeException('Selecione um tópico válido da matéria.');
            }

            $stmt = $pdo->prepare('SELECT capa FROM admin_curso_modulos WHERE id = :id AND curso_id = :curso_id LIMIT 1');
            $stmt->execute([':id' => $moduloId, ':curso_id' => $cursoId]);
            $moduloAtual = $stmt->fetch();
            if (!$moduloAtual) {
                throw new RuntimeException('Módulo não encontrado.');
            }

            $capa = admin_cursos_upload_image('capa', (string)($moduloAtual['capa'] ?? ''));
            $stmt = $pdo->prepare('UPDATE admin_curso_modulos SET titulo = :titulo, descricao = :descricao, capa = :capa, materia_id = :materia_id, topico_id = :topico_id, ordem = :ordem WHERE id = :id AND curso_id = :curso_id LIMIT 1');
            $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':capa' => $capa, ':materia_id' => $materiaId, ':topico_id' => $topicoId, ':ordem' => $ordem, ':id' => $moduloId, ':curso_id' => $cursoId]);

            $stmt = $pdo->prepare('UPDATE admin_curso_aulas SET materia_id = :materia_id, topico_id = :topico_id WHERE curso_id = :curso_id AND modulo_id = :modulo_id');
            $stmt->execute([':materia_id' => $materiaId, ':topico_id' => $topicoId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);

            admin_cursos_redirect('admin-curso-modulos.php?curso_id=' . $cursoId . '&sucesso=modulo_atualizado');
        }

        if ($acao === 'excluir_modulo') {
            $moduloId = admin_cursos_int($_POST['modulo_id'] ?? 0);
            if ($moduloId <= 0) {
                throw new RuntimeException('Módulo inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_curso_modulos WHERE id = :id AND curso_id = :curso_id LIMIT 1');
            $stmt->execute([':id' => $moduloId, ':curso_id' => $cursoId]);

            admin_cursos_redirect('admin-curso-modulos.php?curso_id=' . $cursoId . '&sucesso=modulo_excluido');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'modulo_criado' => 'Módulo criado com sucesso.',
    'modulo_atualizado' => 'Módulo atualizado com sucesso.',
    'modulo_excluido' => 'Módulo excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$curso = null;
$modulos = [];
$materias = [];
$topicosMateria = [];
$moduloEditando = null;
$totalModulos = 0;
$totalConteudos = 0;
$editarModuloId = admin_cursos_int($_GET['editar'] ?? 0);
$abrirCriar = (string)($_GET['modal'] ?? '') === 'criar';

if ($pdo instanceof PDO && $cursoId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM admin_cursos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $cursoId]);
        $curso = $stmt->fetch() ?: null;

        if ($curso) {
            $materias = $pdo->query("SELECT id, nome FROM admin_materias WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();

            $sqlTopicos = "SELECT t.id, t.materia_id, t.titulo, m.nome AS materia_nome FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id WHERE t.status = 'ativo'";
            if (admin_cursos_has_column($pdo, 'admin_materia_topicos', 'parent_id')) {
                $sqlTopicos .= " AND (t.parent_id IS NULL OR t.parent_id = 0)";
            }
            $sqlTopicos .= " ORDER BY m.ordem ASC, m.nome ASC, t.ordem ASC, t.titulo ASC";
            $topicosMateria = $pdo->query($sqlTopicos)->fetchAll();

            $stmt = $pdo->prepare('SELECT m.*, ma.nome AS materia_nome, t.titulo AS topico_nome, (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.modulo_id = m.id) AS total_conteudos FROM admin_curso_modulos m LEFT JOIN admin_materias ma ON ma.id = m.materia_id LEFT JOIN admin_materia_topicos t ON t.id = m.topico_id WHERE m.curso_id = :curso_id ORDER BY m.ordem ASC, m.id ASC');
            $stmt->execute([':curso_id' => $cursoId]);
            $modulos = $stmt->fetchAll();
            $totalModulos = count($modulos);

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_curso_aulas WHERE curso_id = :curso_id');
            $stmt->execute([':curso_id' => $cursoId]);
            $totalConteudos = (int)$stmt->fetchColumn();

            if ($editarModuloId > 0) {
                $stmt = $pdo->prepare('SELECT * FROM admin_curso_modulos WHERE id = :id AND curso_id = :curso_id LIMIT 1');
                $stmt->execute([':id' => $editarModuloId, ':curso_id' => $cursoId]);
                $moduloEditando = $stmt->fetch() ?: null;
            }
        } else {
            $erro = 'Curso não encontrado.';
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar módulos: ' . $e->getMessage();
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
    <title>Módulos do curso | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php admin_cursos_assets(); ?>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <div class="admin-breadcrumb"><a href="admin-cursos.php">Cursos</a><i data-lucide="chevron-right" aria-hidden="true"></i><span>Módulos</span></div>

            <section class="admin-page-header" aria-label="Cabeçalho de módulos">
                <div class="admin-page-heading">
                    <h1>Módulos</h1>
                    <p><?php echo $curso ? admin_esc((string)$curso['nome']) : 'Selecione um curso válido.'; ?>. Aqui é só para organizar o curso em módulos. Vídeo e PDF ficam na tela de conteúdos do módulo.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-light" href="admin-cursos.php"><i data-lucide="arrow-left" aria-hidden="true"></i>Voltar</a>
                    <?php if ($curso): ?><a class="admin-btn admin-btn-primary" href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>&modal=criar"><i data-lucide="plus" aria-hidden="true"></i>Criar módulo</a><?php endif; ?>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <?php if ($curso): ?>
                <section class="admin-stats-grid" aria-label="Resumo dos módulos">
                    <article class="admin-stat-card"><div><span>Módulos</span><strong><?php echo (int)$totalModulos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="layers-3" aria-hidden="true"></i></div></article>
                    <article class="admin-stat-card"><div><span>Conteúdos</span><strong><?php echo (int)$totalConteudos; ?></strong></div><div class="admin-stat-icon"><i data-lucide="play-square" aria-hidden="true"></i></div></article>
                    <article class="admin-stat-card"><div><span>Horas do curso</span><strong style="font-size:1.5rem"><?php echo admin_cursos_horas($curso['horas_curso'] ?? 0); ?></strong></div><div class="admin-stat-icon"><i data-lucide="clock" aria-hidden="true"></i></div></article>
                </section>

                <section class="admin-grid" aria-label="Módulos cadastrados">
                    <?php if (!$modulos): ?>
                        <div class="admin-empty"><div><i data-lucide="layers-3" aria-hidden="true"></i><h3>Nenhum módulo cadastrado</h3><p>Clique em criar módulo para organizar esse curso.</p></div></div>
                    <?php else: ?>
                        <?php foreach ($modulos as $modulo): ?>
                            <?php $moduloId = (int)$modulo['id']; ?>
                            <article class="admin-card">
                                <div class="admin-card-cover">
                                    <?php echo admin_curso_capa_html((string)($modulo['capa'] ?? ''), (string)$modulo['titulo']); ?>
                                    <div class="admin-actions">
                                        <button class="admin-three-btn" type="button" data-admin-actions-btn aria-label="Opções do módulo"><i data-lucide="more-vertical" aria-hidden="true"></i></button>
                                        <div class="admin-dropdown">
                                            <a href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>&editar=<?php echo $moduloId; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar módulo</a>
                                            <a href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>"><i data-lucide="play-square" aria-hidden="true"></i>Conteúdos</a>
                                            <form method="post" onsubmit="return confirm('Excluir este módulo e todos os conteúdos dele?');">
                                                <input type="hidden" name="acao" value="excluir_modulo"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="modulo_id" value="<?php echo $moduloId; ?>">
                                                <button class="danger" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-card-body">
                                    <h3><?php echo admin_esc((string)$modulo['titulo']); ?></h3>
                                    <p><?php echo admin_esc(admin_cursos_texto_curto((string)($modulo['descricao'] ?? ''), 120)); ?></p>
                                    <div class="admin-card-meta">
                                        <span><i data-lucide="book-open" aria-hidden="true"></i><?php echo admin_esc((string)($modulo['materia_nome'] ?? 'Sem matéria')); ?></span>
                                        <span><i data-lucide="bookmark-check" aria-hidden="true"></i><?php echo admin_esc((string)($modulo['topico_nome'] ?? 'Sem tópico')); ?></span>
                                    </div>
                                    <div class="admin-card-meta"><span><i data-lucide="play-square" aria-hidden="true"></i><?php echo (int)($modulo['total_conteudos'] ?? 0); ?> conteúdos</span><span><i data-lucide="arrow-up-down" aria-hidden="true"></i>Ordem <?php echo (int)$modulo['ordem']; ?></span></div>
                                    <div class="admin-card-footer"><a class="admin-btn admin-btn-primary admin-btn-small" href="admin-modulo-conteudos.php?curso_id=<?php echo $cursoId; ?>&modulo_id=<?php echo $moduloId; ?>"><i data-lucide="plus" aria-hidden="true"></i>Criar conteúdo</a></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($curso && ($abrirCriar || $moduloEditando)): ?>
        <?php $editando = is_array($moduloEditando); ?>
        <div class="admin-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal">
                <div class="admin-modal-header"><div><h2><?php echo $editando ? 'Editar módulo' : 'Criar módulo'; ?></h2><p>O módulo não recebe vídeo nem PDF. Ele serve só para organizar os conteúdos.</p></div><a class="admin-modal-close" href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a></div>
                <div class="admin-modal-body">
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="<?php echo $editando ? 'editar_modulo' : 'criar_modulo'; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                        <?php if ($editando): ?><input type="hidden" name="modulo_id" value="<?php echo (int)$moduloEditando['id']; ?>"><?php endif; ?>
                        <div class="admin-form-field full"><label>Título do módulo</label><input type="text" name="titulo" value="<?php echo $editando ? admin_esc((string)$moduloEditando['titulo']) : ''; ?>" required></div>
                        <div class="admin-form-field full"><label>Descrição do módulo</label><textarea name="descricao"><?php echo $editando ? admin_esc((string)($moduloEditando['descricao'] ?? '')) : ''; ?></textarea></div>
                        <div class="admin-form-field">
                            <label>Matéria do módulo</label>
                            <select name="materia_id" id="materiaModulo" required>
                                <option value="">Selecione a matéria</option>
                                <?php foreach ($materias as $materia): ?>
                                    <?php $selectedMateria = $editando && (int)($moduloEditando['materia_id'] ?? 0) === (int)$materia['id']; ?>
                                    <option value="<?php echo (int)$materia['id']; ?>" <?php echo $selectedMateria ? 'selected' : ''; ?>><?php echo admin_esc((string)$materia['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="admin-form-hint">Essa informação liga o módulo ao cronograma e ao progresso da meta diária.</span>
                        </div>
                        <div class="admin-form-field">
                            <label>Tópico da matéria</label>
                            <select name="topico_id" id="topicoModulo" required>
                                <option value="">Selecione o tópico</option>
                                <?php foreach ($topicosMateria as $topico): ?>
                                    <?php $selectedTopico = $editando && (int)($moduloEditando['topico_id'] ?? 0) === (int)$topico['id']; ?>
                                    <option value="<?php echo (int)$topico['id']; ?>" data-materia="<?php echo (int)$topico['materia_id']; ?>" <?php echo $selectedTopico ? 'selected' : ''; ?>><?php echo admin_esc((string)$topico['titulo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="admin-form-hint">O aluno só soma progresso na meta quando assistir aula desse tópico.</span>
                        </div>
                        <div class="admin-form-field"><label>Capa do módulo</label><input type="file" name="capa" accept="image/*"><?php if ($editando && !empty($moduloEditando['capa'])): ?><span class="admin-form-hint">Já tem capa cadastrada. Envie outra somente se quiser trocar.</span><?php endif; ?></div>
                        <div class="admin-form-field"><label>Ordem</label><input type="number" name="ordem" value="<?php echo $editando ? (int)$moduloEditando['ordem'] : 0; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-curso-modulos.php?curso_id=<?php echo $cursoId; ?>">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar módulo</button></div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        (function(){
            const materia = document.getElementById('materiaModulo');
            const topico = document.getElementById('topicoModulo');
            if (!materia || !topico) return;

            function filtrarTopicos(){
                const materiaId = materia.value;
                let selecionadoVisivel = false;
                topico.querySelectorAll('option').forEach(function(option){
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }
                    const mostrar = option.dataset.materia === materiaId;
                    option.hidden = !mostrar;
                    if (mostrar && option.selected) {
                        selecionadoVisivel = true;
                    }
                });
                if (!selecionadoVisivel) {
                    topico.value = '';
                }
            }

            materia.addEventListener('change', filtrarTopicos);
            filtrarTopicos();
        })();
    </script>
    <?php admin_cursos_scripts(); ?>
</body>
</html>
