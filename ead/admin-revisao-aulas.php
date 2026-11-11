<?php
// admin-revisao-aulas.php
// Revisão das aulas: a IA confere se o PDF é do assunto; o mentor decide os casos suspeitos e os reportes dos alunos.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
require_once __DIR__ . '/includes/revisao-aulas-lib.php';

$pdo = admin_cursos_db();
rev_bootstrap($pdo);
ia_fila_bootstrap($pdo);
$erro = '';
$sucesso = '';
$cursoId = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$verTodas = !empty($_GET['todas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = (string)($_POST['acao'] ?? '');
        $aulaId = (int)($_POST['aula_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
        $st->execute([':id' => $aulaId]);
        $aula = $st->fetch();
        if ($acao === 'conferir_todas') {
            $sucesso = rev_varrer($pdo, 5000) . ' aula(s) na fila de conferência. O robô confere em segundo plano (a cada 5 min).';
        } elseif (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        } elseif ($acao === 'ok') {
            rev_gravar($pdo, $aulaId, (string)$aula['pdf_arquivo'], 'ok_manual', null, 'Conferido pelo mentor.', 'mentor');
            $pdo->prepare("UPDATE aula_reportes SET status = 'resolvido' WHERE aula_id = :a AND tipo = 'pdf_errado'")->execute([':a' => $aulaId]);
            $sucesso = 'Marcada como certa. O que esperava a revisão (videoaula, apostila) voltou para a fila.';
        } elseif ($acao === 'reconferir') {
            $pdo->prepare('DELETE FROM aula_revisao WHERE aula_id = :a')->execute([':a' => $aulaId]);
            ia_fila_adicionar($pdo, 'conferir_pdf', $aulaId);
            $sucesso = 'A IA vai conferir de novo.';
        } elseif ($acao === 'trocar') {
            $novo = trim((string)($_POST['pdf_existente'] ?? ''));
            if (!empty($_FILES['pdf_novo']['tmp_name']) && is_uploaded_file($_FILES['pdf_novo']['tmp_name'])) {
                if (strtolower(pathinfo((string)$_FILES['pdf_novo']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                    throw new RuntimeException('Envie um arquivo PDF.');
                }
                $dir = __DIR__ . '/uploads/cursos/pdf';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $nomeArq = 'aula-' . $aulaId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.pdf';
                if (!move_uploaded_file($_FILES['pdf_novo']['tmp_name'], $dir . '/' . $nomeArq)) {
                    throw new RuntimeException('Não consegui salvar o PDF.');
                }
                $novo = 'uploads/cursos/pdf/' . $nomeArq;
                $nomeVisivel = preg_replace('~\.pdf$~i', '', (string)$_FILES['pdf_novo']['name']) . '.pdf';
            } else {
                $ok = $pdo->prepare('SELECT pdf_nome FROM admin_curso_aulas WHERE pdf_arquivo = :p LIMIT 1');
                $ok->execute([':p' => $novo]);
                $nomeVisivel = $ok->fetchColumn();
                if ($novo === '' || $nomeVisivel === false) {
                    throw new RuntimeException('Escolha um PDF da lista ou envie um arquivo.');
                }
                $nomeVisivel = (string)$nomeVisivel ?: basename($novo);
            }
            $pdo->prepare('UPDATE admin_curso_aulas SET pdf_arquivo = :p, pdf_nome = :n WHERE id = :id')->execute([':p' => $novo, ':n' => $nomeVisivel, ':id' => $aulaId]);
            $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $aulaId]);
            rev_gravar($pdo, $aulaId, $novo, 'ok_manual', null, 'PDF trocado pelo mentor.', 'mentor');
            if (trim((string)$aula['video_url']) === '') {
                ia_fila_adicionar($pdo, 'videoaula', $aulaId);
            }
            $pdo->prepare("UPDATE aula_reportes SET status = 'resolvido' WHERE aula_id = :a AND tipo = 'pdf_errado'")->execute([':a' => $aulaId]);
            $sucesso = 'PDF trocado.' . (trim((string)$aula['video_url']) === '' ? ' A videoaula do Antigão foi para a fila com o PDF certo.' : '');
        } elseif ($acao === 'sem_pdf') {
            $pdo->prepare("UPDATE admin_curso_aulas SET pdf_arquivo = '', pdf_nome = '' WHERE id = :id")->execute([':id' => $aulaId]);
            $pdo->prepare('DELETE FROM aula_revisao WHERE aula_id = :a')->execute([':a' => $aulaId]);
            $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $aulaId]);
            $pdo->prepare("DELETE FROM ia_fila WHERE aula_id = :a AND tipo IN ('videoaula','apostila','podcast') AND status = 'erro'")->execute([':a' => $aulaId]);
            if (trim((string)$aula['video_url']) === '') {
                ia_fila_adicionar($pdo, 'aula_ia', $aulaId);
            }
            $pdo->prepare("UPDATE aula_reportes SET status = 'resolvido' WHERE aula_id = :a AND tipo = 'pdf_errado'")->execute([':a' => $aulaId]);
            $sucesso = 'PDF retirado.' . (trim((string)$aula['video_url']) === '' ? ' O Antigão vai escrever a teoria e gravar a videoaula pelo assunto do edital.' : '');
        } elseif ($acao === 'resolver_reportes') {
            $pdo->prepare("UPDATE aula_reportes SET status = 'resolvido' WHERE aula_id = :a")->execute([':a' => $aulaId]);
            $sucesso = 'Reportes marcados como resolvidos.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$cursos = $pdo->query('SELECT id, nome FROM admin_cursos ORDER BY nome')->fetchAll();
$filtro = $cursoId > 0 ? ' AND a.curso_id = ' . $cursoId : '';
// situação calculada: o selo só vale para o PDF atual
$sit = "CASE WHEN TRIM(COALESCE(a.pdf_arquivo,'')) = '' THEN 'sem_pdf' WHEN r.aula_id IS NULL OR r.pdf <> a.pdf_arquivo THEN 'pendente' ELSE r.status END";
$cont = $pdo->query("SELECT $sit s, COUNT(*) n FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id WHERE 1=1 $filtro GROUP BY s")->fetchAll(PDO::FETCH_KEY_PAIR);
$reportesAbertos = (int)$pdo->query("SELECT COUNT(DISTINCT p.aula_id) FROM aula_reportes p JOIN admin_curso_aulas a ON a.id = p.aula_id WHERE p.status = 'aberto' $filtro")->fetchColumn();
$naFila = (int)$pdo->query("SELECT COUNT(*) FROM ia_fila WHERE tipo = 'conferir_pdf' AND status = 'pendente'")->fetchColumn();
$where = $verTodas ? '' : " AND ($sit IN ('suspeita','pendente') OR rp.n > 0)";
$lista = $pdo->query("SELECT a.id, a.curso_id, a.modulo_id, a.titulo, a.pdf_arquivo, a.pdf_nome, a.video_url, c.nome curso, m.nome materia, $sit situacao, r.assunto_pdf, r.motivo, r.por, COALESCE(rp.n,0) reportes, rp.tipos
                        FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id LEFT JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_materias m ON m.id = a.materia_id
                        LEFT JOIN (SELECT aula_id, COUNT(*) n, GROUP_CONCAT(DISTINCT tipo) tipos FROM aula_reportes WHERE status = 'aberto' GROUP BY aula_id) rp ON rp.aula_id = a.id
                       WHERE 1=1 $filtro $where
                       ORDER BY (rp.n > 0) DESC, FIELD($sit, 'suspeita','pendente','confere','ok_manual','sem_pdf'), c.nome, a.ordem, a.id LIMIT 300")->fetchAll();
$detReportes = [];
foreach ($pdo->query("SELECT aula_id, tipo, detalhe, criado_em FROM aula_reportes WHERE status = 'aberto' ORDER BY id DESC LIMIT 500") as $r) {
    $detReportes[(int)$r['aula_id']][] = $r;
}
$conferidas = (int)($cont['confere'] ?? 0) + (int)($cont['ok_manual'] ?? 0);
$comPdf = $conferidas + (int)($cont['suspeita'] ?? 0) + (int)($cont['pendente'] ?? 0);
$selo = ['suspeita' => ['⚠️ Suspeita', '#b42318'], 'pendente' => ['❓ Não conferida', '#6b7280'], 'confere' => ['✅ Confere (IA)', '#067647'], 'ok_manual' => ['✅ Conferida pelo mentor', '#067647'], 'sem_pdf' => ['— Sem PDF', '#6b7280']];
$tiposRep = ['pdf_errado' => 'PDF de outro assunto', 'video' => 'Vídeo com problema', 'conteudo' => 'Conteúdo errado', 'outro' => 'Outro'];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisão de aulas | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .rv-nums{display:flex;flex-wrap:wrap;gap:12px}.rv-nums div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:10px 14px}
        .rv-nums strong{display:block;font-size:22px}.rv-nums span{font-size:12px;color:#6b7280;text-transform:uppercase}
        .rv-barra{height:10px;background:#eef0f3;border-radius:6px;overflow:hidden;margin:10px 0 0}.rv-barra i{display:block;height:100%;background:#067647}
        .rv-item{border-top:1px solid #f0f1f3;padding:14px 0;display:grid;gap:8px}
        .rv-top{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.rv-selo{font-weight:700;font-size:13px}
        .rv-ia{background:#fafafa;border-radius:8px;padding:8px 10px;font-size:13px}
        .rv-acoes{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.rv-acoes form{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0}
        .rv-acoes select{max-width:280px;padding:5px;border-radius:6px;border:1px solid #d0d5dd}
        .rv-rep{background:#fff4e5;border-radius:8px;padding:8px 10px;font-size:13px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>🔎 Revisão de aulas</h1><p>A IA confere se o PDF de cada aula é do assunto certo. Videoaula, apostila e podcast só são gerados de aula conferida, e PDF suspeito fica escondido do aluno até você resolver.</p></div>
            <div class="admin-page-actions">
                <form method="post"><input type="hidden" name="acao" value="conferir_todas"><input type="hidden" name="n" value="<?php echo (int)($cont['pendente'] ?? 0); ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="scan-search"></i>Conferir todas as não conferidas</button></form>
            </div>
        </section>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><?php echo admin_esc($sucesso); ?></div><?php endif; ?>
        <section class="admin-panel">
            <form method="get" style="margin-bottom:12px">Curso: <select name="curso_id" onchange="this.form.submit()"><option value="0">Todos</option><?php foreach ($cursos as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $cursoId ? 'selected' : ''; ?>><?php echo admin_esc($c['nome']); ?></option><?php endforeach; ?></select>
                <label style="margin-left:12px"><input type="checkbox" name="todas" value="1" <?php echo $verTodas ? 'checked' : ''; ?> onchange="this.form.submit()"> mostrar também as conferidas</label></form>
            <div class="rv-nums">
                <div><span>Conferidas</span><strong style="color:#067647"><?php echo $conferidas; ?> / <?php echo $comPdf; ?></strong></div>
                <div><span>Suspeitas</span><strong style="color:#b42318"><?php echo (int)($cont['suspeita'] ?? 0); ?></strong></div>
                <div><span>Não conferidas</span><strong><?php echo (int)($cont['pendente'] ?? 0); ?></strong><small><?php echo $naFila; ?> na fila da IA</small></div>
                <div><span>Reportadas por alunos</span><strong style="color:#b54708"><?php echo $reportesAbertos; ?></strong></div>
                <div><span>Sem PDF</span><strong><?php echo (int)($cont['sem_pdf'] ?? 0); ?></strong></div>
            </div>
            <div class="rv-barra"><i style="width:<?php echo $comPdf ? (int)round($conferidas / $comPdf * 100) : 0; ?>%"></i></div>
        </section>
        <section class="admin-panel">
            <?php if (!$lista): ?><p>Nada pendente aqui. 👍</p><?php endif; ?>
            <?php foreach ($lista as $a): $s = $selo[$a['situacao']] ?? [$a['situacao'], '#111']; $sug = $a['situacao'] === 'suspeita' || $a['reportes'] ? rev_sugerir_pdfs($pdo, (string)$a['titulo'], (string)$a['materia']) : []; ?>
            <div class="rv-item">
                <div class="rv-top">
                    <span class="rv-selo" style="color:<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span>
                    <strong><?php echo admin_esc((string)$a['titulo']); ?></strong>
                    <span style="color:#6b7280;font-size:13px"><?php echo admin_esc((string)$a['curso']); ?><?php echo $a['materia'] ? ' · ' . admin_esc((string)$a['materia']) : ''; ?></span>
                    <a href="aulas.php?curso_id=<?php echo (int)$a['curso_id']; ?>&amp;modulo_id=<?php echo (int)$a['modulo_id']; ?>&amp;aula_id=<?php echo (int)$a['id']; ?>" target="_blank" style="font-size:13px">abrir aula</a>
                </div>
                <?php if (trim((string)$a['pdf_arquivo']) !== ''): ?>
                    <div style="font-size:13px">📄 PDF: <a href="<?php echo admin_esc((string)$a['pdf_arquivo']); ?>" target="_blank"><?php echo admin_esc((string)($a['pdf_nome'] ?: basename((string)$a['pdf_arquivo']))); ?></a></div>
                <?php endif; ?>
                <?php if ($a['assunto_pdf'] || $a['motivo']): ?>
                    <div class="rv-ia">🤖 <?php echo $a['assunto_pdf'] ? 'O PDF trata de: <strong>' . admin_esc((string)$a['assunto_pdf']) . '</strong>. ' : ''; ?><?php echo admin_esc((string)$a['motivo']); ?><?php echo $a['por'] === 'copia' ? ' <em>(mesmo PDF de outra aula igual)</em>' : ''; ?></div>
                <?php endif; ?>
                <?php if (!empty($detReportes[(int)$a['id']])): ?>
                    <div class="rv-rep">🙋 Reportado por <?php echo count($detReportes[(int)$a['id']]); ?> aluno(s):
                        <?php foreach (array_slice($detReportes[(int)$a['id']], 0, 5) as $r): ?><br>· <?php echo admin_esc($tiposRep[$r['tipo']] ?? $r['tipo']); ?><?php echo $r['detalhe'] ? ': “' . admin_esc((string)$r['detalhe']) . '”' : ''; ?> <small>(<?php echo date('d/m', strtotime((string)$r['criado_em'])); ?>)</small><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="rv-acoes">
                    <?php if (trim((string)$a['pdf_arquivo']) !== '' && !in_array($a['situacao'], ['confere', 'ok_manual'], true)): ?>
                        <form method="post"><input type="hidden" name="acao" value="ok"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-small">✅ Está certo</button></form>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="trocar"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                        <select name="pdf_existente"><option value="">Trocar por PDF já na plataforma…</option>
                            <?php foreach ($sug as $p): ?><option value="<?php echo admin_esc((string)$p['pdf']); ?>"><?php echo ($p['nota'] > 0 ? '★ ' : '') . admin_esc(basename((string)$p['pdf'])) . ($p['assunto'] ? ' — ' . admin_esc((string)$p['assunto']) : ''); ?></option><?php endforeach; ?>
                        </select>
                        <span style="font-size:12px">ou</span><input type="file" name="pdf_novo" accept="application/pdf" style="max-width:200px">
                        <button class="admin-btn admin-btn-small">Trocar PDF</button>
                    </form>
                    <?php if (trim((string)$a['pdf_arquivo']) !== ''): ?>
                        <form method="post" onsubmit="return confirm('Retirar o PDF desta aula? <?php echo trim((string)$a['video_url']) === '' ? 'O Antigão vai escrever a teoria e gravar a videoaula pelo assunto do edital.' : ''; ?>');"><input type="hidden" name="acao" value="sem_pdf"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-small admin-btn-light">Sem PDF<?php echo trim((string)$a['video_url']) === '' ? ': Antigão gera pelo edital' : ''; ?></button></form>
                        <form method="post"><input type="hidden" name="acao" value="reconferir"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-small admin-btn-light">🔁 IA conferir de novo</button></form>
                    <?php endif; ?>
                    <?php if ($a['reportes']): ?>
                        <form method="post"><input type="hidden" name="acao" value="resolver_reportes"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-small admin-btn-light">Reportes resolvidos</button></form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<script>window.lucide && lucide.createIcons();</script>
<script src="assets/js/ia-custo.js?v=1" data-api="api/ia-estimativa.php"></script>
</body>
</html>
