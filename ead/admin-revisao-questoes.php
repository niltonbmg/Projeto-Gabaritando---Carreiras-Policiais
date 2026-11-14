<?php
// admin-revisao-questoes.php — Revisão rápida das questões que entram "em revisão" (inéditas da IA, enviadas e importadas).
// O aluno só vê depois de aprovada. Aprovar em lote, editar, descartar; selo "possível 2 respostas" da conferência.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/edital-hack-lib.php';

$pdo = admin_cursos_db();
hack_questoes_bootstrap($pdo);
$e = static fn($s): string => admin_esc((string)$s);
$msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    if ($acao === 'aprovar_sem_alerta') {
        $n = $pdo->exec("UPDATE admin_banco_questoes SET status = 'ativo', atualizado_em = NOW() WHERE status = 'revisao' AND (revisao_alerta IS NULL OR revisao_alerta = '')");
        $msg = "✅ $n questão(ões) sem alerta aprovadas: já aparecem para os alunos.";
    } elseif (($acao === 'aprovar' || $acao === 'descartar') && $ids) {
        $in = implode(',', $ids);
        $n = $pdo->exec("UPDATE admin_banco_questoes SET status = '" . ($acao === 'aprovar' ? 'ativo' : 'inativo') . "', atualizado_em = NOW() WHERE status = 'revisao' AND id IN ($in)");
        $msg = $acao === 'aprovar' ? "✅ $n questão(ões) aprovadas." : "🗑 $n questão(ões) descartadas (ficam guardadas como inativas, para não voltarem repetidas).";
    } elseif ($acao === 'salvar') {
        $id = (int)($_POST['id'] ?? 0);
        $alts = array_values(array_filter(array_map('trim', (array)($_POST['alt'] ?? [])), 'strlen'));
        $cor = (int)($_POST['correta'] ?? -1);
        if (trim((string)($_POST['enunciado'] ?? '')) === '' || count($alts) < 2 || !isset($alts[$cor])) {
            $msg = '⚠️ Preencha o enunciado, pelo menos 2 alternativas e marque a correta.';
        } else {
            $pdo->prepare("UPDATE admin_banco_questoes SET enunciado = :e, explicacao = :x, revisao_alerta = NULL, status = :s, atualizado_em = NOW() WHERE id = :id")
                ->execute([':e' => trim((string)$_POST['enunciado']), ':x' => trim((string)($_POST['explicacao'] ?? '')), ':s' => !empty($_POST['aprovar']) ? 'ativo' : 'revisao', ':id' => $id]);
            $pdo->prepare('DELETE FROM admin_banco_questao_opcoes WHERE questao_id = :q')->execute([':q' => $id]);
            $ins = $pdo->prepare('INSERT INTO admin_banco_questao_opcoes (questao_id, letra, texto, correta, ordem, criado_em) VALUES (:q, :l, :t, :c, :o, NOW())');
            foreach ($alts as $k => $a) {
                $ins->execute([':q' => $id, ':l' => chr(65 + $k), ':t' => $a, ':c' => $k === $cor ? 1 : 0, ':o' => $k + 1]);
            }
            $msg = !empty($_POST['aprovar']) ? '✅ Questão corrigida e aprovada.' : '💾 Questão corrigida (continua em revisão).';
        }
    }
}
$filtro = in_array($_GET['f'] ?? '', ['alerta', 'gerada', 'enviada'], true) ? (string)$_GET['f'] : '';
$editar = (int)($_GET['editar'] ?? 0);
$where = "q.status = 'revisao'" . ($filtro === 'alerta' ? " AND q.revisao_alerta IS NOT NULL AND q.revisao_alerta <> ''" : ($filtro === 'gerada' ? " AND q.origem_tipo = 'gerada'" : ($filtro === 'enviada' ? " AND q.origem_tipo = 'enviada'" : '')));
$lista = $pdo->query("SELECT q.*, m.nome materia, t.titulo assunto, b.nome banca FROM admin_banco_questoes q LEFT JOIN admin_materias m ON m.id = q.materia_id
                       LEFT JOIN admin_materia_topicos t ON t.id = q.materia_topico_id LEFT JOIN admin_bancas b ON b.id = q.banca_id
                      WHERE $where ORDER BY (q.revisao_alerta IS NOT NULL AND q.revisao_alerta <> '') DESC, q.id LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
$tot = $pdo->query("SELECT COUNT(*) total, SUM(revisao_alerta IS NOT NULL AND revisao_alerta <> '') alerta, SUM(origem_tipo = 'gerada') gerada, SUM(origem_tipo = 'enviada') enviada FROM admin_banco_questoes WHERE status = 'revisao'")->fetch(PDO::FETCH_ASSOC);
$stO = $pdo->prepare('SELECT letra, texto, correta FROM admin_banco_questao_opcoes WHERE questao_id = :q ORDER BY ordem, letra');
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisão de questões | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .rq-res{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.rq-res a{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;text-decoration:none;color:#111}.rq-res a.on{border-color:#fbb928;background:#fff8e6}.rq-res strong{display:block;font-size:20px}
        .rq{border:1px solid #e5e7eb;border-radius:12px;padding:12px 14px;margin-bottom:10px;background:#fff}.rq.alerta{border-color:#f97066;background:#fffbfa}
        .rq-top{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12px;color:#6b7280;margin-bottom:6px}.rq-tag{border-radius:999px;padding:2px 8px;background:#f3f4f6;color:#111}
        .rq-selo{border-radius:999px;padding:2px 8px;background:#fee4e2;color:#b42318;font-weight:700}
        .rq ol{margin:6px 0 6px 18px;font-size:14px}.rq li.certa{color:#067647;font-weight:700}.rq-com{font-size:13px;color:#4b5563}
        .rq-ed textarea,.rq-ed input[type=text]{width:100%;font:inherit;padding:6px 8px;border:1px solid #d0d5dd;border-radius:8px}
        .rq-barra{position:sticky;bottom:0;background:#fff;border-top:1px solid #e5e7eb;padding:10px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header"><div class="admin-page-heading"><h1>📝 Revisão de questões</h1><p>Inéditas da IA e questões enviadas/importadas entram aqui. O aluno só vê depois que você aprova. Questões reais achadas em provas entram direto.</p></div></section>
        <?php if ($msg !== ''): ?><div class="admin-alert admin-alert-success"><?php echo $e($msg); ?></div><?php endif; ?>
        <section class="admin-panel">
            <div class="rq-res">
                <a href="admin-revisao-questoes.php" class="<?php echo $filtro === '' ? 'on' : ''; ?>"><strong><?php echo (int)$tot['total']; ?></strong>em revisão</a>
                <a href="admin-revisao-questoes.php?f=alerta" class="<?php echo $filtro === 'alerta' ? 'on' : ''; ?>"><strong style="color:#b42318"><?php echo (int)$tot['alerta']; ?></strong>⚠️ com alerta</a>
                <a href="admin-revisao-questoes.php?f=gerada" class="<?php echo $filtro === 'gerada' ? 'on' : ''; ?>"><strong><?php echo (int)$tot['gerada']; ?></strong>🤖 inéditas</a>
                <a href="admin-revisao-questoes.php?f=enviada" class="<?php echo $filtro === 'enviada' ? 'on' : ''; ?>"><strong><?php echo (int)$tot['enviada']; ?></strong>📤 enviadas/importadas</a>
            </div>
            <?php if ((int)$tot['total'] > (int)$tot['alerta']): ?>
            <form method="post" onsubmit="return confirm('Aprovar todas as questões em revisão SEM alerta?');" style="margin-bottom:12px"><input type="hidden" name="acao" value="aprovar_sem_alerta"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">✅ Aprovar todas sem alerta (<?php echo (int)$tot['total'] - (int)$tot['alerta']; ?>)</button></form>
            <?php endif; ?>
            <?php if (!$lista): ?><p class="admin-form-hint">Nada para revisar aqui. 👍</p><?php endif; ?>
            <form method="post" id="rqLote">
            <?php foreach ($lista as $q): $stO->execute([':q' => (int)$q['id']]); $ops = $stO->fetchAll(PDO::FETCH_ASSOC); $al = trim((string)$q['revisao_alerta']); ?>
                <div class="rq <?php echo $al !== '' ? 'alerta' : ''; ?>" id="q<?php echo (int)$q['id']; ?>">
                    <div class="rq-top">
                        <label><input type="checkbox" name="ids[]" value="<?php echo (int)$q['id']; ?>" class="rq-ck"> nº <?php echo (int)$q['id']; ?></label>
                        <span class="rq-tag"><?php echo $q['origem_tipo'] === 'gerada' ? '🤖 inédita' : '📤 enviada/importada'; ?></span>
                        <span><?php echo $e(($q['materia'] ?? '') . ' › ' . ($q['assunto'] ?? 'sem assunto')); ?></span>
                        <span><?php echo $e(trim(($q['banca'] ?? '') . ' ' . ($q['ano'] ?? ''))); ?></span>
                        <?php if ($al !== ''): ?><span class="rq-selo" title="<?php echo $e($al); ?>">⚠️ possível 2 respostas</span><?php endif; ?>
                        <a href="admin-revisao-questoes.php?<?php echo $filtro !== '' ? 'f=' . $e($filtro) . '&' : ''; ?>editar=<?php echo (int)$q['id']; ?>#q<?php echo (int)$q['id']; ?>" style="margin-left:auto">✏️ editar</a>
                    </div>
                    <?php if ($editar === (int)$q['id']): ?>
                        </form>
                        <form method="post" class="rq-ed" style="display:grid;gap:6px"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
                            <?php if ($al !== ''): ?><p class="rq-selo" style="display:block">⚠️ <?php echo $e($al); ?></p><?php endif; ?>
                            <textarea name="enunciado" rows="4"><?php echo $e($q['enunciado']); ?></textarea>
                            <?php for ($k = 0; $k < max(5, count($ops)); $k++): $o = $ops[$k] ?? ['texto' => '', 'correta' => 0]; ?>
                                <label style="display:flex;gap:6px;align-items:center"><input type="radio" name="correta" value="<?php echo $k; ?>" <?php echo (int)$o['correta'] ? 'checked' : ''; ?> title="correta"> <?php echo chr(65 + $k); ?>) <input type="text" name="alt[]" value="<?php echo $e($o['texto']); ?>"></label>
                            <?php endfor; ?>
                            <textarea name="explicacao" rows="3" placeholder="Comentário"><?php echo $e($q['explicacao']); ?></textarea>
                            <div style="display:flex;gap:8px"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="aprovar" value="1">Salvar e aprovar</button><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Só salvar</button><a class="admin-btn admin-btn-light admin-btn-small" href="admin-revisao-questoes.php<?php echo $filtro !== '' ? '?f=' . $e($filtro) : ''; ?>">Cancelar</a></div>
                        </form>
                        <form method="post" id="rqLote2">
                    <?php else: ?>
                        <div style="white-space:pre-line;font-size:15px"><?php echo $e($q['enunciado']); ?></div>
                        <ol type="A"><?php foreach ($ops as $o): ?><li class="<?php echo (int)$o['correta'] ? 'certa' : ''; ?>"><?php echo $e($o['texto']); ?><?php echo (int)$o['correta'] ? ' ✓' : ''; ?></li><?php endforeach; ?></ol>
                        <?php if ($al !== ''): ?><p class="rq-com" style="color:#b42318">⚠️ <?php echo $e($al); ?></p><?php endif; ?>
                        <details class="rq-com"><summary style="cursor:pointer">comentário</summary><?php echo $e($q['explicacao']); ?></details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($lista && !$editar): ?>
                <div class="rq-barra">
                    <label><input type="checkbox" onclick="document.querySelectorAll('.rq-ck').forEach(c => c.checked = this.checked)"> marcar todas desta página</label>
                    <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="acao" value="aprovar">✅ Aprovar marcadas</button>
                    <button class="admin-btn admin-btn-light admin-btn-small" type="submit" name="acao" value="descartar" onclick="return confirm('Descartar as marcadas?');">🗑 Descartar marcadas</button>
                    <small class="admin-form-hint">Mostra 40 por vez: as com alerta primeiro.</small>
                </div>
            <?php endif; ?>
            </form>
        </section>
    </main>
</div>
<script>if (window.lucide) { lucide.createIcons(); }</script>
</body>
</html>
