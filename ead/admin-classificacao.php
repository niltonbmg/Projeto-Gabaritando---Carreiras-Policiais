<?php
// admin-classificacao.php
// Confirmar o que a IA sugeriu: curso, matéria, assunto do edital e dificuldade
// de aulas, questões e materiais. Cada confirmação/correção treina a IA.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/ia-classificar-lib.php';
@set_time_limit(150);

$erro = '';
$sucesso = '';
$pdo = admin_cursos_db();
ia_class_bootstrap($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'varrer') {
            $n = ia_class_varrer($pdo);
            $sucesso = $n . ' item(ns) novo(s) para a IA classificar. O robô faz em segundo plano; ou clique em "Classificar agora".';
        } elseif ($acao === 'classificar_agora') {
            $feitos = 0;
            $t0 = time();
            while (time() - $t0 < 80 && ($r = ia_class_processar_um($pdo)) !== null) {
                $pdo = podcast_reconectar($pdo);
                $feitos++;
            }
            $sucesso = $feitos . ' item(ns) classificado(s) agora.';
        } elseif ($acao === 'confirmar' || $acao === 'corrigir') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT sugestao FROM ia_classificacoes WHERE id = :id');
            $st->execute([':id' => $id]);
            $sug = json_decode((string)$st->fetchColumn(), true) ?: [];
            $final = [
                'curso_ids' => array_map('intval', (array)($_POST['curso_ids'] ?? [])),
                'materia_id' => (int)($_POST['materia_id'] ?? 0),
                'topico_id' => (int)($_POST['topico_id'] ?? 0),
                'dificuldade' => (string)($_POST['dificuldade'] ?? ''),
                'novo_assunto' => isset($_POST['criar_assunto']) ? (string)($sug['novo_assunto'] ?? '') : '',
            ];
            $mudou = $final['materia_id'] !== (int)($sug['materia_id'] ?? 0)
                || $final['topico_id'] !== (int)($sug['topico_id'] ?? 0)
                || ($final['dificuldade'] !== '' && $final['dificuldade'] !== (string)($sug['dificuldade'] ?? ''));
            ia_class_aplicar($pdo, $id, $final, $mudou);
            $sucesso = $mudou ? 'Corrigido. A IA vai aprender com essa correção.' : 'Confirmado.';
        } elseif ($acao === 'confirmar_lote') {
            $min = max(50, (int)($_POST['minimo'] ?? 90));
            $n = 0;
            foreach ($pdo->query("SELECT id, sugestao FROM ia_classificacoes WHERE status = 'sugerida' AND confianca >= " . $min)->fetchAll() as $l) {
                $s = json_decode((string)$l['sugestao'], true) ?: [];
                if (empty($s['topico_id'])) {
                    continue; // sem assunto definido: precisa de olho humano
                }
                ia_class_aplicar($pdo, (int)$l['id'], $s, false);
                $n++;
            }
            $sucesso = $n . ' sugestão(ões) com confiança ≥ ' . $min . '% confirmada(s).';
        } elseif ($acao === 'ignorar') {
            $pdo->prepare("UPDATE ia_classificacoes SET status = 'ignorada' WHERE id = :id")->execute([':id' => (int)($_POST['id'] ?? 0)]);
            $sucesso = 'Item ignorado.';
        } elseif ($acao === 'refazer_erros') {
            $pdo->exec("UPDATE ia_classificacoes SET status = 'pendente_ia', erro = NULL WHERE status = 'erro'");
            $sucesso = 'Itens com erro voltaram para a fila.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$stats = ia_class_estatisticas($pdo);
$tax = ia_class_taxonomia($pdo);
$sugeridas = $pdo->query("SELECT * FROM ia_classificacoes WHERE status = 'sugerida' ORDER BY confianca DESC, id LIMIT 40")->fetchAll();
$erros = $pdo->query("SELECT tipo, ref_id, erro FROM ia_classificacoes WHERE status = 'erro' ORDER BY atualizado_em DESC LIMIT 5")->fetchAll();

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar classificação | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .cl-stats{display:flex;flex-wrap:wrap;gap:12px}
        .cl-stats div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:12px 16px;min-width:130px}
        .cl-stats strong{display:block;font-size:22px;font-variant-numeric:tabular-nums}
        .cl-stats span{font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}
        .cl-item{border:1px solid #eef0f3;border-radius:14px;padding:14px;display:grid;gap:10px;margin-bottom:12px;background:#fff}
        .cl-topo{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center}
        .cl-tipo{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8a6300;background:#fff6dd;padding:3px 8px;border-radius:6px}
        .cl-conf{font-size:12px;font-weight:700;padding:3px 8px;border-radius:6px}
        .cl-conf.alta{background:#e7f7ec;color:#17803d}.cl-conf.media{background:#fff6dd;color:#8a6300}.cl-conf.baixa{background:#fde8e8;color:#b42318}
        .cl-amostra{font-size:13px;color:#374151;white-space:pre-line;max-height:110px;overflow:auto;background:#f9fafb;border-radius:8px;padding:8px 10px}
        .cl-motivo{font-size:13px;color:#6b7280}
        .cl-campos{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;align-items:end}
        .cl-campos select{width:100%}
        .cl-acoes{display:flex;flex-wrap:wrap;gap:8px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading">
                <h1>Confirmar classificação</h1>
                <p>A IA sugere curso, matéria, assunto do edital e dificuldade de cada aula, questão e material. Confirme ou corrija: cada correção ensina a IA.</p>
            </div>
            <div class="admin-page-actions">
                <form method="post"><input type="hidden" name="acao" value="varrer"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="scan-search"></i>Buscar itens sem assunto</button></form>
                <form method="post"><input type="hidden" name="acao" value="classificar_agora"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>Classificar agora</button></form>
            </div>
        </section>

        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <section class="admin-panel">
            <div class="cl-stats">
                <div><span>Na fila da IA</span><strong><?php echo (int)$stats['pendente_ia']; ?></strong></div>
                <div><span>Para você confirmar</span><strong><?php echo (int)$stats['sugerida']; ?></strong></div>
                <div><span>Confirmadas</span><strong><?php echo (int)$stats['confirmada']; ?></strong></div>
                <div><span>Corrigidas</span><strong><?php echo (int)$stats['corrigida']; ?></strong></div>
                <div><span>Acerto da IA</span><strong><?php echo $stats['acerto'] === null ? '—' : (int)$stats['acerto'] . '%'; ?></strong></div>
            </div>
            <?php if ($erros): ?>
                <div style="margin-top:12px">
                    <?php foreach ($erros as $er): ?><p style="color:#b42318;font-size:13px">✗ <?php echo admin_esc(IA_CLASS_TIPOS[$er['tipo']] . ' #' . $er['ref_id'] . ': ' . $er['erro']); ?></p><?php endforeach; ?>
                    <form method="post"><input type="hidden" name="acao" value="refazer_erros"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Tentar de novo os itens com erro</button></form>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>Sugestões para confirmar</h2>
                <?php if ($sugeridas): ?><form method="post" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="acao" value="confirmar_lote"><label for="min" style="font-size:13px">Confirmar todas com confiança ≥</label><select id="min" name="minimo"><option>95</option><option selected>90</option><option>80</option></select><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Confirmar em lote</button></form><?php endif; ?>
            </div>
            <?php if (!$sugeridas): ?>
                <div class="admin-empty"><div><i data-lucide="check-check"></i><h3>Nada para confirmar</h3><p>Clique em "Buscar itens sem assunto" e depois em "Classificar agora". O robô também faz isso sozinho a cada 5 minutos.</p></div></div>
            <?php endif; ?>
            <?php foreach ($sugeridas as $c): $s = json_decode((string)$c['sugestao'], true) ?: []; $conf = (int)$c['confianca']; ?>
                <form method="post" class="cl-item">
                    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                    <div class="cl-topo">
                        <span class="cl-tipo"><?php echo admin_esc(IA_CLASS_TIPOS[$c['tipo']] ?? $c['tipo']); ?> #<?php echo (int)$c['ref_id']; ?></span>
                        <span class="cl-conf <?php echo $conf >= 85 ? 'alta' : ($conf >= 60 ? 'media' : 'baixa'); ?>">Confiança da IA: <?php echo $conf; ?>%</span>
                    </div>
                    <div class="cl-amostra"><?php echo admin_esc(mb_substr((string)$c['amostra'], 0, 700)); ?></div>
                    <?php if (!empty($s['motivo'])): ?><div class="cl-motivo">💬 <?php echo admin_esc((string)$s['motivo']); ?></div><?php endif; ?>
                    <div class="cl-campos">
                        <?php if ($c['tipo'] === 'questao'): ?>
                        <div class="admin-form-field"><label>Curso (edital)</label><select name="curso_ids[]"><option value="0">—</option><?php foreach ($tax['cursos'] as $cu): ?><option value="<?php echo (int)$cu['id']; ?>" <?php echo in_array((int)$cu['id'], (array)($s['curso_ids'] ?? []), true) ? 'selected' : ''; ?>><?php echo admin_esc((string)$cu['nome']); ?></option><?php endforeach; ?></select></div>
                        <?php else: foreach ((array)($s['curso_ids'] ?? []) as $cid): ?><input type="hidden" name="curso_ids[]" value="<?php echo (int)$cid; ?>"><?php endforeach; endif; ?>
                        <div class="admin-form-field"><label>Matéria › Assunto do edital</label>
                            <select name="topico_id" onchange="this.form.materia_id.value=this.selectedOptions[0].dataset.m||0">
                                <option value="0" data-m="<?php echo (int)($s['materia_id'] ?? 0); ?>">— sem assunto —</option>
                                <?php foreach ($tax['materias'] as $mid => $m): foreach ((array)($m['assuntos'] ?? []) as $tid => $tt): ?>
                                    <option value="<?php echo (int)$tid; ?>" data-m="<?php echo (int)$mid; ?>" <?php echo (int)($s['topico_id'] ?? 0) === (int)$tid ? 'selected' : ''; ?>><?php echo admin_esc($m['nome'] . ' › ' . $tt); ?></option>
                                <?php endforeach; endforeach; ?>
                            </select>
                            <input type="hidden" name="materia_id" value="<?php echo (int)($s['materia_id'] ?? 0); ?>">
                        </div>
                        <?php if ($c['tipo'] === 'questao'): ?>
                        <div class="admin-form-field"><label>Dificuldade</label><select name="dificuldade"><?php foreach (['' => '—', 'facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'] as $dk => $dn): ?><option value="<?php echo $dk; ?>" <?php echo ($s['dificuldade'] ?? '') === $dk && $dk !== '' ? 'selected' : ''; ?>><?php echo $dn; ?></option><?php endforeach; ?></select></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($s['novo_assunto'])): ?><label style="font-size:13px"><input type="checkbox" name="criar_assunto" value="1"> Criar o assunto novo sugerido: <strong><?php echo admin_esc((string)$s['novo_assunto']); ?></strong> (na matéria escolhida)</label><?php endif; ?>
                    <div class="cl-acoes">
                        <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="acao" value="confirmar"><i data-lucide="check"></i>Confirmar / salvar</button>
                        <button class="admin-btn admin-btn-light admin-btn-small" type="submit" name="acao" value="ignorar">Ignorar</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
