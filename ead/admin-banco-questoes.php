<?php
// admin-banco-questoes.php
// Painel administrativo para cadastrar questões por acesso, concurso passado, banca, matéria e tópico.
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
        if ($acao === 'criar_questao' || $acao === 'editar_questao') {
            $questaoId = admin_bq_int($_POST['questao_id'] ?? 0);
            $cursoIds = admin_bq_ids($_POST['curso_ids'] ?? ($_POST['curso_id'] ?? []));
            $concursoPassadoId = admin_bq_int($_POST['concurso_passado_id'] ?? 0);
            $bancaId = admin_bq_int($_POST['banca_id'] ?? 0);
            $materiaId = admin_bq_int($_POST['materia_id'] ?? 0);
            $materiaTopicoId = admin_bq_int($_POST['materia_topico_id'] ?? 0);
            $ano = admin_bq_int($_POST['ano'] ?? 0);
            $enunciado = trim((string)($_POST['enunciado'] ?? ''));
            $explicacao = trim((string)($_POST['explicacao'] ?? ''));
            $dificuldade = admin_bq_dificuldade($_POST['dificuldade'] ?? 'medio');
            $status = admin_bq_status($_POST['status'] ?? 'ativo');
            $ordem = (int)($_POST['ordem'] ?? 0);
            $opcoes = admin_bq_validar_opcoes($_POST);

            if (!$cursoIds) {
                throw new RuntimeException('Selecione pelo menos um acesso para a questão.');
            }
            foreach ($cursoIds as $cursoId) {
                $stmtCurso = $pdo->prepare('SELECT COUNT(*) FROM admin_cursos WHERE id = :id');
                $stmtCurso->execute([':id' => $cursoId]);
                if ((int)$stmtCurso->fetchColumn() === 0) {
                    throw new RuntimeException('Acesso inválido para a questão.');
                }
            }
            if ($concursoPassadoId > 0) {
                $stmtConcurso = $pdo->prepare('SELECT COUNT(*) FROM admin_concursos_passados WHERE id = :id');
                $stmtConcurso->execute([':id' => $concursoPassadoId]);
                if ((int)$stmtConcurso->fetchColumn() === 0) {
                    throw new RuntimeException('Concurso passado inválido para a questão.');
                }
            }
            if ($bancaId <= 0) {
                throw new RuntimeException('Selecione a banca da questão.');
            }
            if ($materiaId <= 0) {
                throw new RuntimeException('Selecione a matéria da questão.');
            }
            if ($enunciado === '') {
                throw new RuntimeException('Digite o enunciado da questão.');
            }
            if ($materiaTopicoId > 0) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_materia_topicos WHERE id = :id AND materia_id = :materia_id');
                $stmt->execute([':id' => $materiaTopicoId, ':materia_id' => $materiaId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    throw new RuntimeException('O tópico precisa pertencer à matéria selecionada.');
                }
            }
            if ($ano > 0 && ($ano < 1900 || $ano > 2100)) {
                throw new RuntimeException('Informe um ano válido para a questão.');
            }

            $legacyCursoId = $cursoIds[0] ?? null;
            $pdo->beginTransaction();

            if ($acao === 'criar_questao') {
                $stmt = $pdo->prepare('INSERT INTO admin_banco_questoes (curso_id, concurso_passado_id, banca_id, materia_id, materia_topico_id, ano, enunciado, explicacao, dificuldade, status, ordem) VALUES (:curso_id, :concurso_passado_id, :banca_id, :materia_id, :materia_topico_id, :ano, :enunciado, :explicacao, :dificuldade, :status, :ordem)');
                $stmt->execute([
                    ':curso_id' => $legacyCursoId,
                    ':concurso_passado_id' => $concursoPassadoId > 0 ? $concursoPassadoId : null,
                    ':banca_id' => $bancaId,
                    ':materia_id' => $materiaId,
                    ':materia_topico_id' => $materiaTopicoId > 0 ? $materiaTopicoId : null,
                    ':ano' => $ano > 0 ? $ano : null,
                    ':enunciado' => $enunciado,
                    ':explicacao' => $explicacao,
                    ':dificuldade' => $dificuldade,
                    ':status' => $status,
                    ':ordem' => $ordem,
                ]);
                $questaoId = (int)$pdo->lastInsertId();
                $redirect = 'admin-banco-questoes.php?sucesso=questao_criada';
            } else {
                if ($questaoId <= 0) {
                    throw new RuntimeException('Questão inválida para edição.');
                }

                $stmt = $pdo->prepare('UPDATE admin_banco_questoes SET curso_id = :curso_id, concurso_passado_id = :concurso_passado_id, banca_id = :banca_id, materia_id = :materia_id, topico_id = NULL, materia_topico_id = :materia_topico_id, ano = :ano, enunciado = :enunciado, explicacao = :explicacao, dificuldade = :dificuldade, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
                $stmt->execute([
                    ':curso_id' => $legacyCursoId,
                    ':concurso_passado_id' => $concursoPassadoId > 0 ? $concursoPassadoId : null,
                    ':banca_id' => $bancaId,
                    ':materia_id' => $materiaId,
                    ':materia_topico_id' => $materiaTopicoId > 0 ? $materiaTopicoId : null,
                    ':ano' => $ano > 0 ? $ano : null,
                    ':enunciado' => $enunciado,
                    ':explicacao' => $explicacao,
                    ':dificuldade' => $dificuldade,
                    ':status' => $status,
                    ':ordem' => $ordem,
                    ':id' => $questaoId,
                ]);
                $redirect = 'admin-banco-questoes.php?sucesso=questao_atualizada';
            }

            admin_bq_salvar_acessos($pdo, $questaoId, $cursoIds);
            admin_bq_salvar_opcoes($pdo, $questaoId, $opcoes);
            $pdo->commit();
            admin_cursos_redirect($redirect);
        }

        if ($acao === 'excluir_questao') {
            $questaoId = admin_bq_int($_POST['questao_id'] ?? 0);
            if ($questaoId <= 0) {
                throw new RuntimeException('Questão inválida.');
            }
            $stmt = $pdo->prepare('DELETE FROM admin_banco_questoes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $questaoId]);
            admin_cursos_redirect('admin-banco-questoes.php?sucesso=questao_excluida');
        }
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'questao_criada' => 'Questão criada com sucesso.',
    'questao_atualizada' => 'Questão atualizada com sucesso.',
    'questao_excluida' => 'Questão excluída com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$cursos = [];
$concursosPassados = [];
$bancas = [];
$materias = [];
$materiaTopicos = [];
$questoes = [];
$totais = ['questoes' => 0, 'acessos' => 0, 'concursos' => 0, 'bancas' => 0, 'materias' => 0, 'ativas' => 0];
$questaoEditando = null;
$opcoesEditando = [];
$acessosEditando = [];
$editarId = admin_bq_int($_GET['editar'] ?? 0);
$abrirCriar = (string)($_GET['modal'] ?? '') === 'criar';
$filtroAcesso = admin_bq_int($_GET['curso_id'] ?? $_GET['acesso_id'] ?? 0);
$filtroConcurso = admin_bq_int($_GET['concurso_passado_id'] ?? 0);
$filtroBanca = admin_bq_int($_GET['banca_id'] ?? 0);
$filtroMateria = admin_bq_int($_GET['materia_id'] ?? 0);
$filtroMateriaTopico = admin_bq_int($_GET['materia_topico_id'] ?? 0);
$filtroAno = admin_bq_int($_GET['ano'] ?? 0);
$filtroStatus = (string)($_GET['status'] ?? '');
$busca = trim((string)($_GET['q'] ?? ''));

if ($pdo instanceof PDO) {
    try {
        $cursos = $pdo->query("SELECT id, nome, status, ordem FROM admin_cursos ORDER BY ordem ASC, nome ASC, id ASC")->fetchAll();
        $concursosPassados = $pdo->query("SELECT cp.*, b.nome AS banca_nome, b.sigla AS banca_sigla FROM admin_concursos_passados cp LEFT JOIN admin_bancas b ON b.id = cp.banca_id ORDER BY cp.ordem ASC, cp.ano DESC, cp.titulo ASC")->fetchAll();
        $bancas = $pdo->query("SELECT * FROM admin_bancas ORDER BY ordem ASC, nome ASC")->fetchAll();
        $materias = $pdo->query("SELECT * FROM admin_materias ORDER BY ordem ASC, nome ASC")->fetchAll();
        $materiaTopicos = $pdo->query("SELECT t.*, m.nome AS materia_nome FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id ORDER BY m.nome ASC, t.ordem ASC, t.titulo ASC")->fetchAll();

        $where = [];
        $params = [];
        if ($filtroAcesso > 0) {
            $where[] = '(q.curso_id = :filtro_acesso_legacy OR EXISTS (SELECT 1 FROM admin_banco_questao_acessos qa_filter WHERE qa_filter.questao_id = q.id AND qa_filter.curso_id = :filtro_acesso_pivot))';
            $params[':filtro_acesso_legacy'] = $filtroAcesso;
            $params[':filtro_acesso_pivot'] = $filtroAcesso;
        }
        if ($filtroConcurso > 0) {
            $where[] = 'q.concurso_passado_id = :filtro_concurso';
            $params[':filtro_concurso'] = $filtroConcurso;
        }
        if ($filtroBanca > 0) {
            $where[] = 'q.banca_id = :filtro_banca';
            $params[':filtro_banca'] = $filtroBanca;
        }
        if ($filtroMateria > 0) {
            $where[] = 'q.materia_id = :filtro_materia';
            $params[':filtro_materia'] = $filtroMateria;
        }
        if ($filtroMateriaTopico > 0) {
            $where[] = 'q.materia_topico_id = :filtro_materia_topico';
            $params[':filtro_materia_topico'] = $filtroMateriaTopico;
        }
        if ($filtroAno > 0) {
            $where[] = 'q.ano = :filtro_ano';
            $params[':filtro_ano'] = $filtroAno;
        }
        if (in_array($filtroStatus, ['ativo', 'inativo'], true)) {
            $where[] = 'q.status = :filtro_status';
            $params[':filtro_status'] = $filtroStatus;
        }
        if ($busca !== '') {
            $where[] = '(q.enunciado LIKE :busca1 OR q.explicacao LIKE :busca2 OR cp.titulo LIKE :busca3)';
            foreach (range(1, 3) as $__n) { $params[':busca' . $__n] = '%' . $busca . '%'; } // parâmetro único por coluna (EMULATE_PREPARES=false)
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT q.*, c.nome AS curso_nome, cp.titulo AS concurso_passado_titulo, cp.ano AS concurso_passado_ano, b.nome AS banca_nome, b.sigla AS banca_sigla, m.nome AS materia_nome, mt.titulo AS materia_topico_titulo,
            (SELECT COUNT(*) FROM admin_banco_questao_opcoes o WHERE o.questao_id = q.id) AS total_opcoes,
            (SELECT GROUP_CONCAT(ca.nome ORDER BY ca.ordem ASC, ca.nome ASC SEPARATOR ', ') FROM admin_banco_questao_acessos qa INNER JOIN admin_cursos ca ON ca.id = qa.curso_id WHERE qa.questao_id = q.id) AS acesso_nomes
            FROM admin_banco_questoes q
            LEFT JOIN admin_cursos c ON c.id = q.curso_id
            LEFT JOIN admin_concursos_passados cp ON cp.id = q.concurso_passado_id
            INNER JOIN admin_bancas b ON b.id = q.banca_id
            INNER JOIN admin_materias m ON m.id = q.materia_id
            LEFT JOIN admin_materia_topicos mt ON mt.id = q.materia_topico_id
            {$whereSql}
            ORDER BY q.criado_em DESC, q.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $questoes = $stmt->fetchAll();

        $totais['questoes'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_banco_questoes')->fetchColumn();
        $totais['acessos'] = count($cursos);
        $totais['concursos'] = count($concursosPassados);
        $totais['bancas'] = count($bancas);
        $totais['materias'] = count($materias);
        $totais['ativas'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_banco_questoes WHERE status = 'ativo'")->fetchColumn();

        if ($editarId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_banco_questoes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarId]);
            $questaoEditando = $stmt->fetch() ?: null;
            if ($questaoEditando) {
                $opcoesEditando = admin_bq_opcoes_da_questao($pdo, $editarId);
                $acessosEditando = admin_bq_acessos_da_questao($pdo, $editarId);
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar dados: ' . $e->getMessage();
    }
}

if (!$opcoesEditando) {
    $opcoesEditando = [
        ['texto' => '', 'correta' => 1],
        ['texto' => '', 'correta' => 0],
        ['texto' => '', 'correta' => 0],
        ['texto' => '', 'correta' => 0],
    ];
}

$dadosForm = $questaoEditando ?: ['id' => 0, 'curso_id' => 0, 'concurso_passado_id' => 0, 'banca_id' => 0, 'materia_id' => 0, 'materia_topico_id' => null, 'ano' => '', 'enunciado' => '', 'explicacao' => '', 'dificuldade' => 'medio', 'status' => 'ativo', 'ordem' => 0];
if (!$acessosEditando && (int)($dadosForm['curso_id'] ?? 0) > 0) {
    $acessosEditando = [(int)$dadosForm['curso_id']];
}
$formAberto = $abrirCriar || (bool)$questaoEditando;
$formAcao = $questaoEditando ? 'editar_questao' : 'criar_questao';
$formTitulo = $questaoEditando ? 'Editar questão' : 'Criar questão';

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
    <title>Admin Banco de Questões | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .bq-modal-backdrop{align-items:flex-start !important;padding:16px !important;overflow-y:auto !important}.bq-modal{width:min(1120px,100%) !important;max-height:calc(100vh - 32px);display:flex;flex-direction:column;overflow:hidden}.bq-modal .admin-modal-body{overflow-y:auto;padding-bottom:30px}.bq-options{grid-column:1/-1;display:grid;gap:12px;margin-top:4px}.bq-option{display:grid;grid-template-columns:44px minmax(0,1fr) 120px 42px;gap:10px;align-items:center;background:#f9fafb;border:1px solid #eef0f3;border-radius:12px;padding:12px}.bq-letter{width:34px;height:34px;border-radius:999px;background:#fff7db;color:#8a6100;display:flex;align-items:center;justify-content:center;font-weight:700}.bq-correct{display:flex;align-items:center;gap:7px;color:#344054;font-size:.86rem;font-weight:700}.bq-correct input{accent-color:#fdbd08}.bq-remove{width:38px;height:38px;border:1px solid #fecdca;background:#fff1f0;color:#d92d20;border-radius:9px;display:flex;align-items:center;justify-content:center}.bq-remove svg{width:18px;height:18px}.bq-add-row{display:flex;justify-content:flex-end}.bq-tag{display:inline-flex;align-items:center;border-radius:999px;background:#f2f4f7;color:#475467;padding:5px 9px;font-size:.76rem;font-weight:700;margin:2px 0}.bq-tag.yellow{background:#fff7db;color:#8a6100}.bq-tag.green{background:#ecfdf3;color:#067647}.bq-tag.red{background:#fff1f0;color:#b42318}.bq-tag.blue{background:#eff8ff;color:#175cd3}.bq-filter-panel{margin-bottom:18px}.bq-filter-actions{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.bq-filter-actions .admin-btn{height:44px}.bq-question-text{max-width:430px}.bq-question-text strong{display:block;color:#111827;margin-bottom:4px}.bq-question-text span{display:block;color:#667085;font-size:.84rem;line-height:1.35}.bq-meta-small{color:#667085;font-size:.78rem;display:block;margin-top:4px}.bq-access-picker{display:grid;gap:12px;border:1px solid #e4e7ec;background:linear-gradient(180deg,#ffffff 0%,#fcfcfd 100%);border-radius:18px;padding:14px;box-shadow:0 10px 24px rgba(16,24,40,.06)}.bq-access-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.bq-access-search{position:relative;flex:1 1 320px;min-width:220px}.bq-access-search i,.bq-access-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:#667085;pointer-events:none;display:block}.bq-access-search input{width:100%;height:46px;border:1px solid #d0d5dd;border-radius:12px;padding:0 14px 0 42px;background:#fff;color:#101828;font:inherit}.bq-access-search input:focus{outline:none;border-color:#fdbd08;box-shadow:0 0 0 4px rgba(253,189,8,.14)}.bq-access-actions{display:flex;gap:8px;flex-wrap:wrap}.bq-access-btn{height:42px;border:1px solid #d0d5dd;background:#fff;color:#344054;border-radius:12px;padding:0 14px;font:inherit;font-weight:700;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.bq-access-btn:hover{border-color:#fdbd08;color:#8a6100;background:#fffaf0}.bq-access-summary{display:grid;gap:10px}.bq-access-counter{display:inline-flex;align-items:center;gap:8px;background:#111827;color:#fff;border-radius:999px;padding:7px 12px;font-size:.83rem;font-weight:700;width:max-content;max-width:100%}.bq-access-chips{display:flex;gap:8px;flex-wrap:wrap}.bq-access-chip{display:inline-flex;align-items:center;gap:6px;background:#fff7db;color:#8a6100;border:1px solid #f6d56b;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700;line-height:1.2}.bq-access-chip.muted{background:#f2f4f7;border-color:#eaecf0;color:#475467}.bq-access-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:280px;overflow:auto;padding-right:4px}.bq-access-card{position:relative;display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid #eaecf0;background:#fff;border-radius:16px;cursor:pointer;transition:.18s ease}.bq-access-card:hover{transform:translateY(-1px);border-color:#f6d56b;box-shadow:0 10px 20px rgba(16,24,40,.08)}.bq-access-card.is-selected{border-color:#fdbd08;background:linear-gradient(180deg,#fffdf5 0%,#fff7db 100%);box-shadow:0 10px 24px rgba(253,189,8,.16)}.bq-access-checkbox{position:absolute;opacity:0;pointer-events:none}.bq-access-card-check{width:22px;height:22px;border-radius:999px;border:2px solid #d0d5dd;background:#fff;display:inline-flex;align-items:center;justify-content:center;flex:none;margin-top:2px;transition:.18s ease}.bq-access-card-check i{width:14px;height:14px;opacity:0;color:#8a6100}.bq-access-card.is-selected .bq-access-card-check{border-color:#fdbd08;background:#fef0c7}.bq-access-card.is-selected .bq-access-card-check i{opacity:1}.bq-access-card-content{display:grid;gap:4px;min-width:0}.bq-access-card-content strong{color:#111827;font-size:.95rem;line-height:1.35}.bq-access-card-content small{color:#667085;font-size:.79rem;line-height:1.35}.bq-access-empty{padding:16px;border:1px dashed #d0d5dd;border-radius:14px;text-align:center;color:#667085;background:#fff}.bq-access-noresult{grid-column:1/-1;padding:18px;border:1px dashed #d0d5dd;border-radius:14px;text-align:center;color:#667085;background:#fff}.bq-access-hidden{display:none !important}@media(max-width:900px){.bq-access-list{grid-template-columns:1fr}}@media(max-width:760px){.bq-option{grid-template-columns:36px minmax(0,1fr);}.bq-correct,.bq-remove{grid-column:2}.bq-filter-actions{align-items:stretch}.bq-filter-actions .admin-btn{width:100%}.bq-access-toolbar,.bq-access-actions{align-items:stretch}.bq-access-actions{width:100%}.bq-access-btn{flex:1}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Banco de questões</h1>
                    <p>Cadastre a questão com o acesso liberado, o concurso passado de origem, banca, matéria, tópico, ano, alternativas e explicação.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-light" href="admin-concursos-passados.php?modal=concurso"><i data-lucide="archive" aria-hidden="true"></i>Novo concurso passado</a>
                    <a class="admin-btn admin-btn-light" href="admin-bancas.php?modal=banca"><i data-lucide="building-2" aria-hidden="true"></i>Nova banca</a>
                    <a class="admin-btn admin-btn-light" href="admin-materias.php?modal=criar"><i data-lucide="library" aria-hidden="true"></i>Nova matéria</a>
                    <a class="admin-btn admin-btn-primary" href="admin-banco-questoes.php?modal=criar"><i data-lucide="plus" aria-hidden="true"></i>Criar questão</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Questões</span><strong><?php echo (int)$totais['questoes']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="circle-help" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Acessos</span><strong><?php echo (int)$totais['acessos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="key-round" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Concursos passados</span><strong><?php echo (int)$totais['concursos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="archive" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Bancas</span><strong><?php echo (int)$totais['bancas']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="building-2" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel bq-filter-panel">
                <form class="admin-form-grid" method="get">
                    <div class="admin-form-field"><label for="q">Buscar</label><input id="q" name="q" type="text" value="<?php echo admin_esc($busca); ?>" placeholder="Texto, explicação ou concurso passado"></div>
                    <div class="admin-form-field"><label for="filtro_curso">Acesso</label><select id="filtro_curso" name="curso_id"><option value="0">Todos</option><?php foreach ($cursos as $curso): ?><option value="<?php echo (int)$curso['id']; ?>" <?php echo $filtroAcesso === (int)$curso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$curso['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="filtro_concurso">Concurso passado</label><select id="filtro_concurso" name="concurso_passado_id"><option value="0">Todos</option><?php foreach ($concursosPassados as $concurso): ?><option value="<?php echo (int)$concurso['id']; ?>" <?php echo $filtroConcurso === (int)$concurso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$concurso['titulo']); ?><?php echo (int)($concurso['ano'] ?? 0) > 0 ? ' • ' . (int)$concurso['ano'] : ''; ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="filtro_banca">Banca</label><select id="filtro_banca" name="banca_id"><option value="0">Todas</option><?php foreach ($bancas as $banca): ?><option value="<?php echo (int)$banca['id']; ?>" <?php echo $filtroBanca === (int)$banca['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$banca['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="filtro_materia">Matéria</label><select id="filtro_materia" name="materia_id"><option value="0">Todas</option><?php foreach ($materias as $materia): ?><option value="<?php echo (int)$materia['id']; ?>" <?php echo $filtroMateria === (int)$materia['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$materia['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="filtro_materia_topico">Tópico da matéria</label><select id="filtro_materia_topico" name="materia_topico_id"><option value="0">Todos</option><?php foreach ($materiaTopicos as $mtopico): ?><option data-materia="<?php echo (int)$mtopico['materia_id']; ?>" value="<?php echo (int)$mtopico['id']; ?>" <?php echo $filtroMateriaTopico === (int)$mtopico['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$mtopico['materia_nome'] . ' - ' . admin_bq_materia_topico_nome($mtopico)); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="filtro_ano">Ano</label><input id="filtro_ano" name="ano" type="number" min="1900" max="2100" value="<?php echo $filtroAno > 0 ? (int)$filtroAno : ''; ?>" placeholder="Ex.: 2024"></div>
                    <div class="admin-form-field"><label for="filtro_status">Status</label><select id="filtro_status" name="status"><option value="">Todos</option><option value="ativo" <?php echo $filtroStatus === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo $filtroStatus === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                    <div class="admin-form-actions bq-filter-actions"><a class="admin-btn admin-btn-light" href="admin-banco-questoes.php">Limpar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="search" aria-hidden="true"></i>Filtrar</button></div>
                </form>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Questões cadastradas</h2><small>Cadastro por acesso, concurso passado, banca, matéria e resposta correta</small></div>
                <?php if (!$cursos || !$bancas || !$materias): ?>
                    <div class="admin-empty"><div><i data-lucide="alert-circle" aria-hidden="true"></i><h3>Antes de criar questão</h3><p>Cadastre pelo menos um acesso/curso, uma banca e uma matéria.</p></div></div>
                <?php elseif (!$questoes): ?>
                    <div class="admin-empty"><div><i data-lucide="circle-help" aria-hidden="true"></i><h3>Nenhuma questão encontrada</h3><p>Clique em criar questão para cadastrar a primeira.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Questão</th><th>Concurso passado</th><th>Acesso</th><th>Ano</th><th>Banca</th><th>Matéria</th><th>Tópico</th><th>Opções</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($questoes as $questao): ?>
                                    <?php $acessosNomes = trim((string)($questao['acesso_nomes'] ?? '')); if ($acessosNomes === '') { $acessosNomes = trim((string)($questao['curso_nome'] ?? '')); } ?>
                                    <tr>
                                        <td><div class="bq-question-text"><strong><?php echo admin_esc(admin_cursos_texto_curto((string)$questao['enunciado'], 95)); ?></strong><span><?php echo admin_esc(admin_cursos_texto_curto((string)($questao['explicacao'] ?? ''), 80)); ?></span></div></td>
                                        <td><?php if ((string)($questao['concurso_passado_titulo'] ?? '') !== ''): ?><span class="bq-tag green"><?php echo admin_esc((string)$questao['concurso_passado_titulo']); ?></span><?php if ((int)($questao['concurso_passado_ano'] ?? 0) > 0): ?><span class="bq-meta-small"><?php echo (int)$questao['concurso_passado_ano']; ?></span><?php endif; ?><?php else: ?><span class="bq-tag red">Sem concurso passado</span><?php endif; ?></td>
                                        <td><?php echo $acessosNomes !== '' ? '<span class="bq-tag blue">' . admin_esc(admin_cursos_texto_curto($acessosNomes, 80)) . '</span>' : '<span class="bq-tag red">Sem acesso</span>'; ?></td>
                                        <td><?php echo (int)($questao['ano'] ?? 0) > 0 ? (int)$questao['ano'] : '-'; ?></td>
                                        <td><span class="bq-tag yellow"><?php echo admin_esc((string)$questao['banca_nome']); ?><?php echo (string)($questao['banca_sigla'] ?? '') !== '' ? ' / ' . admin_esc((string)$questao['banca_sigla']) : ''; ?></span></td>
                                        <td><span class="bq-tag blue"><?php echo admin_esc((string)$questao['materia_nome']); ?></span></td>
                                        <td><?php if ((string)($questao['materia_topico_titulo'] ?? '') !== ''): ?><span class="bq-tag blue"><?php echo admin_esc((string)$questao['materia_topico_titulo']); ?></span><?php else: ?>-<?php endif; ?></td>
                                        <td><?php echo (int)$questao['total_opcoes']; ?></td>
                                        <td><span class="admin-badge <?php echo admin_esc((string)$questao['status']); ?>"><?php echo admin_esc((string)$questao['status']); ?></span></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-banco-questoes.php?editar=<?php echo (int)$questao['id']; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir esta questão?');">
                                                    <input type="hidden" name="acao" value="excluir_questao">
                                                    <input type="hidden" name="questao_id" value="<?php echo (int)$questao['id']; ?>">
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

    <?php if ($formAberto): ?>
        <div class="admin-modal-backdrop bq-modal-backdrop" role="dialog" aria-modal="true">
            <div class="admin-modal bq-modal">
                <div class="admin-modal-header">
                    <div><h2><?php echo admin_esc($formTitulo); ?></h2><p>Selecione o acesso, concurso passado, banca, matéria e tópico; depois escreva o enunciado, alternativas, resposta correta e explicação.</p></div>
                    <a class="admin-modal-close" href="admin-banco-questoes.php"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <form class="admin-modal-body" method="post" id="formQuestao">
                    <input type="hidden" name="acao" value="<?php echo admin_esc($formAcao); ?>">
                    <input type="hidden" name="questao_id" value="<?php echo (int)$dadosForm['id']; ?>">

                    <div class="admin-form-grid">
                        <div class="admin-form-field full"><label for="curso_ids_search">Acesso</label><div class="bq-access-picker" data-access-picker><div class="bq-access-toolbar"><div class="bq-access-search"><i data-lucide="search" aria-hidden="true"></i><input id="curso_ids_search" type="text" placeholder="Buscar acesso por nome"></div><div class="bq-access-actions"><button class="bq-access-btn" type="button" data-select-all>Selecionar todos</button><button class="bq-access-btn" type="button" data-clear-all>Limpar</button></div></div><div class="bq-access-summary"><span class="bq-access-counter" data-access-counter>0 acessos selecionados</span><div class="bq-access-chips" data-access-chips></div></div><div class="bq-access-list" data-access-list><?php foreach ($cursos as $curso): ?><label class="bq-access-card <?php echo in_array((int)$curso['id'], $acessosEditando, true) ? 'is-selected' : ''; ?>" data-access-item data-search="<?php echo admin_esc(mb_strtolower((string)$curso['nome'], 'UTF-8')); ?>"><input class="bq-access-checkbox" type="checkbox" name="curso_ids[]" value="<?php echo (int)$curso['id']; ?>" data-access-checkbox data-access-label="<?php echo admin_esc((string)$curso['nome']); ?>" <?php echo in_array((int)$curso['id'], $acessosEditando, true) ? 'checked' : ''; ?>><span class="bq-access-card-check"><i data-lucide="check" aria-hidden="true"></i></span><span class="bq-access-card-content"><strong><?php echo admin_esc((string)$curso['nome']); ?></strong><small>Questão visível somente para alunos com este acesso.</small></span></label><?php endforeach; ?><div class="bq-access-noresult bq-access-hidden" data-access-empty>Nenhum acesso encontrado para essa busca.</div></div></div><small class="admin-form-hint">Selecione um ou mais acessos para liberar a questão aos alunos certos.</small></div>
                        <div class="admin-form-field"><label for="concurso_passado_id">Concurso passado</label><select id="concurso_passado_id" name="concurso_passado_id"><option value="0">Sem concurso passado</option><?php foreach ($concursosPassados as $concurso): ?><option value="<?php echo (int)$concurso['id']; ?>" <?php echo (int)($dadosForm['concurso_passado_id'] ?? 0) === (int)$concurso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$concurso['titulo']); ?><?php echo (int)($concurso['ano'] ?? 0) > 0 ? ' • ' . (int)$concurso['ano'] : ''; ?></option><?php endforeach; ?></select><small class="admin-form-hint">É de onde a questão foi tirada e aparece para o aluno.</small></div>
                        <div class="admin-form-field"><label for="banca_id">Banca</label><select id="banca_id" name="banca_id" required><option value="">Selecione</option><?php foreach ($bancas as $banca): ?><option value="<?php echo (int)$banca['id']; ?>" <?php echo (int)$dadosForm['banca_id'] === (int)$banca['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$banca['nome']); ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="materia_id">Matéria</label><select id="materia_id" name="materia_id" required><option value="">Selecione</option><?php foreach ($materias as $materia): ?><option value="<?php echo (int)$materia['id']; ?>" <?php echo (int)$dadosForm['materia_id'] === (int)$materia['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$materia['nome']); ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="materia_topico_id">Tópico da matéria</label><select id="materia_topico_id" name="materia_topico_id"><option value="0">Sem tópico da matéria</option><?php foreach ($materiaTopicos as $mtopico): ?><option data-materia="<?php echo (int)$mtopico['materia_id']; ?>" value="<?php echo (int)$mtopico['id']; ?>" <?php echo (int)($dadosForm['materia_topico_id'] ?? 0) === (int)$mtopico['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$mtopico['materia_nome'] . ' - ' . admin_bq_materia_topico_nome($mtopico)); ?></option><?php endforeach; ?></select><small class="admin-form-hint">A lista filtra conforme a matéria selecionada.</small></div>
                        <div class="admin-form-field"><label for="ano">Ano da questão</label><input id="ano" name="ano" type="number" min="1900" max="2100" value="<?php echo (int)($dadosForm['ano'] ?? 0) > 0 ? (int)$dadosForm['ano'] : ''; ?>" placeholder="Ex.: 2024"></div>
                        <div class="admin-form-field"><label for="dificuldade">Dificuldade</label><select id="dificuldade" name="dificuldade"><option value="facil" <?php echo (string)$dadosForm['dificuldade'] === 'facil' ? 'selected' : ''; ?>>Fácil</option><option value="medio" <?php echo (string)$dadosForm['dificuldade'] === 'medio' ? 'selected' : ''; ?>>Médio</option><option value="dificil" <?php echo (string)$dadosForm['dificuldade'] === 'dificil' ? 'selected' : ''; ?>>Difícil</option></select></div>
                        <div class="admin-form-field full"><label for="enunciado">Texto da questão / enunciado</label><textarea id="enunciado" name="enunciado" required><?php echo admin_esc((string)$dadosForm['enunciado']); ?></textarea></div>
                        <div class="admin-form-field full"><label for="explicacao">Explicação da resposta</label><textarea id="explicacao" name="explicacao" placeholder="Explique por que essa alternativa é a correta."><?php echo admin_esc((string)($dadosForm['explicacao'] ?? '')); ?></textarea></div>

                        <div class="bq-options" id="opcoesWrap">
                            <strong>Opções de resposta</strong>
                            <?php foreach ($opcoesEditando as $idx => $opcao): ?>
                                <div class="bq-option" data-option>
                                    <div class="bq-letter" data-letter><?php echo admin_esc(admin_bq_letra((int)$idx)); ?></div>
                                    <input type="text" name="opcao_texto[]" value="<?php echo admin_esc((string)$opcao['texto']); ?>" placeholder="Digite a alternativa" required>
                                    <label class="bq-correct"><input type="radio" name="correta" value="<?php echo (int)$idx; ?>" <?php echo (int)($opcao['correta'] ?? 0) === 1 ? 'checked' : ''; ?>>Correta</label>
                                    <button class="bq-remove" type="button" data-remove-option aria-label="Remover opção"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                                </div>
                            <?php endforeach; ?>
                            <div class="bq-add-row"><button class="admin-btn admin-btn-light" type="button" id="adicionarOpcao"><i data-lucide="plus" aria-hidden="true"></i>Adicionar opção</button></div>
                        </div>

                        <div class="admin-form-field"><label for="status">Status</label><select id="status" name="status"><option value="ativo" <?php echo (string)$dadosForm['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="inativo" <?php echo (string)$dadosForm['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                        <div class="admin-form-field"><label for="ordem">Ordem</label><input id="ordem" name="ordem" type="number" value="<?php echo (int)$dadosForm['ordem']; ?>"></div>
                        <div class="admin-form-actions"><a class="admin-btn admin-btn-light" href="admin-banco-questoes.php">Cancelar</a><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar questão</button></div>
                    </div>
                </form>
            </div>
        </div>
        <script>
            (function(){
                const materia = document.getElementById('materia_id');
                const materiaTopico = document.getElementById('materia_topico_id');
                function filtrarMateriaTopicos(){
                    if(!materia || !materiaTopico) return;
                    const id = materia.value;
                    materiaTopico.querySelectorAll('option[data-materia]').forEach(function(opt){
                        opt.hidden = id !== '' && opt.dataset.materia !== id;
                        if(opt.hidden && opt.selected) materiaTopico.value = '0';
                    });
                }
                if(materia){ materia.addEventListener('change', filtrarMateriaTopicos); filtrarMateriaTopicos(); }

                const wrap = document.getElementById('opcoesWrap');
                const add = document.getElementById('adicionarOpcao');
                function letra(index){
                    let n = index;
                    let s = '';
                    do { s = String.fromCharCode(65 + (n % 26)) + s; n = Math.floor(n / 26) - 1; } while(n >= 0);
                    return s;
                }
                function refresh(){
                    const rows = wrap.querySelectorAll('[data-option]');
                    rows.forEach(function(row, idx){
                        const l = row.querySelector('[data-letter]');
                        const r = row.querySelector('input[type="radio"]');
                        if(l) l.textContent = letra(idx);
                        if(r) r.value = String(idx);
                    });
                    if(window.lucide) window.lucide.createIcons();
                }
                function addRow(){
                    const row = document.createElement('div');
                    row.className = 'bq-option';
                    row.setAttribute('data-option','');
                    row.innerHTML = '<div class="bq-letter" data-letter></div><input type="text" name="opcao_texto[]" placeholder="Digite a alternativa" required><label class="bq-correct"><input type="radio" name="correta" value="0">Correta</label><button class="bq-remove" type="button" data-remove-option aria-label="Remover opção"><i data-lucide="trash-2" aria-hidden="true"></i></button>';
                    wrap.insertBefore(row, wrap.querySelector('.bq-add-row'));
                    refresh();
                    row.querySelector('input[type="text"]').focus();
                }
                if(add) add.addEventListener('click', addRow);
                if(wrap){
                    wrap.addEventListener('click', function(event){
                        const btn = event.target.closest('[data-remove-option]');
                        if(!btn) return;
                        const rows = wrap.querySelectorAll('[data-option]');
                        if(rows.length <= 2){ alert('A questão precisa de pelo menos duas opções.'); return; }
                        btn.closest('[data-option]').remove();
                        const checked = wrap.querySelector('input[type="radio"]:checked');
                        if(!checked){
                            const first = wrap.querySelector('input[type="radio"]');
                            if(first) first.checked = true;
                        }
                        refresh();
                    });
                    refresh();
                }

                const accessPicker = document.querySelector('[data-access-picker]');
                if(accessPicker){
                    const form = accessPicker.closest('form');
                    const search = accessPicker.querySelector('#curso_ids_search');
                    const list = accessPicker.querySelector('[data-access-list]');
                    const emptyState = accessPicker.querySelector('[data-access-empty]');
                    const counter = accessPicker.querySelector('[data-access-counter]');
                    const chips = accessPicker.querySelector('[data-access-chips]');
                    const selectAllBtn = accessPicker.querySelector('[data-select-all]');
                    const clearAllBtn = accessPicker.querySelector('[data-clear-all]');
                    const items = Array.from(accessPicker.querySelectorAll('[data-access-item]'));
                    const checkboxes = Array.from(accessPicker.querySelectorAll('[data-access-checkbox]'));

                    function normalizarTexto(valor){
                        return String(valor || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
                    }

                    function atualizarResumo(){
                        const selecionados = checkboxes.filter(function(cb){ return cb.checked; });
                        const total = selecionados.length;
                        counter.textContent = total === 1 ? '1 acesso selecionado' : total + ' acessos selecionados';
                        chips.innerHTML = '';
                        if(total === 0){
                            const vazio = document.createElement('span');
                            vazio.className = 'bq-access-chip muted';
                            vazio.textContent = 'Nenhum acesso selecionado';
                            chips.appendChild(vazio);
                        } else {
                            selecionados.slice(0, 4).forEach(function(cb){
                                const chip = document.createElement('span');
                                chip.className = 'bq-access-chip';
                                chip.textContent = cb.dataset.accessLabel || cb.value;
                                chips.appendChild(chip);
                            });
                            if(total > 4){
                                const extra = document.createElement('span');
                                extra.className = 'bq-access-chip muted';
                                extra.textContent = '+' + (total - 4) + ' selecionados';
                                chips.appendChild(extra);
                            }
                        }
                        items.forEach(function(item){
                            const checkbox = item.querySelector('[data-access-checkbox]');
                            item.classList.toggle('is-selected', !!(checkbox && checkbox.checked));
                        });
                        if(window.lucide) window.lucide.createIcons();
                    }

                    function aplicarBusca(){
                        const termo = normalizarTexto(search ? search.value : '');
                        let visiveis = 0;
                        items.forEach(function(item){
                            const base = normalizarTexto(item.getAttribute('data-search'));
                            const mostrar = termo === '' || base.indexOf(termo) !== -1;
                            item.classList.toggle('bq-access-hidden', !mostrar);
                            if(mostrar) visiveis += 1;
                        });
                        if(emptyState){
                            emptyState.classList.toggle('bq-access-hidden', visiveis > 0);
                        }
                    }

                    checkboxes.forEach(function(cb){
                        cb.addEventListener('change', atualizarResumo);
                    });
                    if(search){
                        search.addEventListener('input', aplicarBusca);
                    }
                    if(selectAllBtn){
                        selectAllBtn.addEventListener('click', function(){
                            checkboxes.forEach(function(cb){ cb.checked = true; });
                            atualizarResumo();
                        });
                    }
                    if(clearAllBtn){
                        clearAllBtn.addEventListener('click', function(){
                            checkboxes.forEach(function(cb){ cb.checked = false; });
                            atualizarResumo();
                        });
                    }
                    if(form){
                        form.addEventListener('submit', function(event){
                            const algumSelecionado = checkboxes.some(function(cb){ return cb.checked; });
                            if(!algumSelecionado){
                                event.preventDefault();
                                alert('Selecione pelo menos um acesso para a questão.');
                                if(search) search.focus();
                            }
                        });
                    }
                    aplicarBusca();
                    atualizarResumo();
                }
            })();
        </script>
    <?php endif; ?>

    <script>
        (function(){
            const filtroMateria = document.getElementById('filtro_materia');
            const filtroMateriaTopico = document.getElementById('filtro_materia_topico');
            if(!filtroMateria || !filtroMateriaTopico) return;
            function filtrar(){
                const id = filtroMateria.value;
                filtroMateriaTopico.querySelectorAll('option[data-materia]').forEach(function(opt){
                    opt.hidden = id !== '0' && id !== '' && opt.dataset.materia !== id;
                    if(opt.hidden && opt.selected) filtroMateriaTopico.value = '0';
                });
            }
            filtroMateria.addEventListener('change', filtrar);
            filtrar();
        })();
    </script>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
