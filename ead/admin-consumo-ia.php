<?php
// admin-consumo-ia.php — quanto a IA gastou de verdade (tokens que o Google cobra), por dia, tipo e curso,
// com o limite mensal e a tabela de preços editável.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-consumo-lib.php';

$pdo = admin_cursos_db();
ia_consumo_bootstrap($pdo);
$msg = '';
if (($_POST['acao'] ?? '') === 'controle') {
    $lig = [];
    foreach (ia_controle_tipos() as $t => $_) {
        $lig[$t] = !empty($_POST['lig'][$t]);
    }
    $modoNovo = in_array($_POST['modo'] ?? '', ['economico', 'normal', 'maximo'], true) ? $_POST['modo'] : 'normal';
    ia_controle_salvar(['ligados' => $lig, 'modo' => $modoNovo, 'teto' => ia_controle()['teto_manual']]);
    $msg = 'Controle salvo. Vale a partir da próxima tarefa.';
}
if (($_POST['acao'] ?? '') === 'config') {
    $c = ia_consumo_config();
    $c['plano'] = ($_POST['plano'] ?? '') === 'pago' ? 'pago' : 'gratis';
    $c['dolar'] = max(0.01, (float)str_replace(',', '.', (string)($_POST['dolar'] ?? $c['dolar'])));
    $c['limite_mensal_brl'] = max(0, (float)str_replace(',', '.', (string)($_POST['limite'] ?? 0)));
    $c['busca_por_chamada'] = max(0, (float)str_replace(',', '.', (string)($_POST['busca'] ?? 0)));
    foreach ($c['precos'] as $k => $p) {
        $c['precos'][$k]['entrada'] = max(0, (float)str_replace(',', '.', (string)($_POST['pe'][$k] ?? $p['entrada'])));
        $c['precos'][$k]['saida'] = max(0, (float)str_replace(',', '.', (string)($_POST['ps'][$k] ?? $p['saida'])));
    }
    ia_consumo_salvar_config($c);
    $msg = 'Configuração salva. Vale para as próximas chamadas (o histórico mantém o custo da época).';
}
$cfg = ia_consumo_config();
$dolar = (float)$cfg['dolar'];
$acoes = ia_consumo_acoes();
$brl = static fn($usd): string => ia_consumo_brl((float)$usd * $dolar);
$num = static fn($n): string => number_format((float)$n, 0, ',', '.');

$hoje = $pdo->query("SELECT COALESCE(SUM(custo_usd),0) u, COALESCE(SUM(tokens_entrada+tokens_saida),0) t, COUNT(*) c FROM ia_consumo WHERE criado_em >= CURDATE()")->fetch();
$semana = $pdo->query("SELECT COALESCE(SUM(custo_usd),0) u FROM ia_consumo WHERE criado_em >= CURDATE() - INTERVAL 6 DAY")->fetchColumn();
$mesUsd = (float)$pdo->query("SELECT COALESCE(SUM(custo_usd),0) FROM ia_consumo WHERE criado_em >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$lim = (float)$cfg['limite_mensal_brl'];
$pct = $lim > 0 ? min(100, (int)round($mesUsd * $dolar / $lim * 100)) : 0;
$porAcao = $pdo->query("SELECT acao, COUNT(DISTINCT execucao) execs, COUNT(*) chamadas, SUM(tokens_entrada) te, SUM(tokens_saida) ts, SUM(custo_usd) u
                          FROM ia_consumo WHERE criado_em >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY acao ORDER BY u DESC")->fetchAll();
$porCurso = $pdo->query("SELECT COALESCE(c.nome, '(sem curso)') curso, SUM(x.custo_usd) u, COUNT(DISTINCT x.execucao) execs
                           FROM ia_consumo x LEFT JOIN admin_curso_aulas a ON a.id = x.ref_id AND x.acao IN ('videoaula','podcast','apostila','aula_ia','conferir_pdf')
                           LEFT JOIN admin_cursos c ON c.id = a.curso_id
                          WHERE x.criado_em >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY curso ORDER BY u DESC LIMIT 15")->fetchAll();
$dias = $pdo->query("SELECT DATE(criado_em) d, SUM(custo_usd) u, SUM(cota='voz') voz, SUM(cota='texto') texto, SUM(cota='busca') busca FROM ia_consumo
                      WHERE criado_em >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(criado_em) ORDER BY d DESC")->fetchAll();
$ultimas = $pdo->query("SELECT execucao, acao, ref_id, MIN(criado_em) quando, COUNT(*) chamadas, SUM(tokens_entrada+tokens_saida) t, SUM(custo_usd) u
                          FROM ia_consumo GROUP BY execucao, acao, ref_id ORDER BY quando DESC LIMIT 20")->fetchAll();
$alunos = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consumo da IA | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .cs-nums{display:flex;flex-wrap:wrap;gap:12px}.cs-nums div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:10px 14px;min-width:150px}
        .cs-nums strong{display:block;font-size:22px}.cs-nums span{font-size:12px;color:#6b7280;text-transform:uppercase}
        .cs-barra{height:10px;background:#eef0f3;border-radius:6px;overflow:hidden;margin-top:6px}.cs-barra i{display:block;height:100%;background:#c9a227}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:7px 8px;border-top:1px solid #f3f4f6;font-size:14px}td.n,th.n{text-align:right}
        .cs-form input{width:90px;padding:5px;border:1px solid #d0d5dd;border-radius:6px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>💰 Consumo da IA</h1><p>Valores medidos com os tokens que o próprio Google informa em cada resposta. Os preços abaixo vêm da tabela oficial: confira em ai.google.dev/pricing e ajuste se mudar.</p></div>
        </section>
        <?php if ($msg !== ''): ?><div class="admin-alert admin-alert-success"><?php echo admin_esc($msg); ?></div><?php endif; ?>
        <?php if ($cfg['plano'] !== 'pago'): ?><div class="admin-alert" style="background:#eef6ff;border:1px solid #b2ddff;color:#175cd3">ℹ️ <strong>Plano grátis do Google: nada está sendo cobrado.</strong> Os valores abaixo mostram quanto <em>custaria</em> no plano pago, para você planejar. Quando ativar o pago, marque "Plano pago" lá embaixo e o limite mensal passa a valer.</div><?php endif; ?>
        <section class="admin-panel"><div class="cs-nums">
            <div><span>Hoje</span><strong><?php echo $brl($hoje['u']); ?></strong><small><?php echo $num($hoje['t']); ?> tokens · <?php echo (int)$hoje['c']; ?> chamadas</small></div>
            <div><span>Últimos 7 dias</span><strong><?php echo $brl($semana); ?></strong></div>
            <div><span>Este mês</span><strong><?php echo $brl($mesUsd); ?></strong>
                <?php if ($lim > 0): ?><small>de R$ <?php echo number_format($lim, 2, ',', '.'); ?> (<?php echo $pct; ?>%)</small><div class="cs-barra"><i style="width:<?php echo $pct; ?>%;<?php echo $pct >= 80 ? 'background:#b42318' : ''; ?>"></i></div><?php else: ?><small>sem limite definido</small><?php endif; ?></div>
            <div><span>Por aluno ativo (mês)</span><strong><?php echo $alunos ? $brl($mesUsd / $alunos) : '—'; ?></strong><small><?php echo $alunos; ?> alunos ativos</small></div>
        </div>
        <?php if ($lim > 0 && $pct >= 80): ?><p style="color:#b42318;font-weight:700;margin-top:10px">⚠️ <?php echo $pct >= 100 ? 'Limite do mês atingido: a IA está pausada. Aumente o limite abaixo para continuar.' : 'Mais de 80% do limite do mês já foi usado.'; ?></p><?php endif; ?>
        </section>

        <?php
        $ctl = ia_controle(); $usoHoje = podcast_uso_resumo(); $aprend = ia_controle_limite_aprendido(); $porTarefa = ia_controle_uso_por_tarefa();
        $nomesT = ia_controle_tipos() + ['desafio' => 'Desafio do Dia', 'outro' => 'Outros'];
        $nomesC = ['texto' => '✍️ Texto', 'voz' => '🎙 Voz', 'busca' => '🔎 Pesquisa'];
        // Previsão: tarefas na fila ÷ ritmo dos últimos 3 dias
        $prev = [];
        try {
            $pend = $pdo->query("SELECT tipo, COUNT(*) n FROM ia_fila WHERE status IN ('pendente','erro') GROUP BY tipo")->fetchAll(PDO::FETCH_KEY_PAIR);
            $ritmo = $pdo->query("SELECT acao, COUNT(DISTINCT execucao) / 3 r FROM ia_consumo WHERE ok = 1 AND criado_em >= NOW() - INTERVAL 3 DAY GROUP BY acao")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach (['conferir_pdf' => 'a conferência dos PDFs', 'videoaula' => 'as videoaulas', 'apostila' => 'as teorias', 'questoes' => 'as questões'] as $t => $n) {
                if (!empty($pend[$t])) {
                    $r = (float)($ritmo[$t] ?? 0);
                    $prev[] = $r > 0 ? $n . ' termina' . ($t === 'conferir_pdf' ? '' : 'm') . ' em ~' . max(1, (int)ceil($pend[$t] / $r)) . ' dia(s) (' . (int)$pend[$t] . ' na fila)' : $n . ': ' . (int)$pend[$t] . ' na fila, ainda sem ritmo medido';
                }
            }
        } catch (Throwable $e) {
        }
        $modos = ['economico' => ['🐢 Econômico', 'Usa metade da cota grátis. Sobra folga.'], 'normal' => ['⚖️ Normal', 'Usa toda a cota grátis. Recomendado.'], 'maximo' => ['🚀 Máximo', 'Sem teto. Só faz sentido no plano pago (no grátis, o Google corta e o robô só pausa).']];
        ?>
        <section class="admin-panel" id="controle">
            <h2>🎚 Quanto a IA pode trabalhar por dia</h2>
            <?php foreach (['texto', 'voz', 'busca'] as $c): $teto = (int)$ctl['teto'][$c]; ?>
                <p style="margin:4px 0;font-size:15px"><?php echo $nomesC[$c]; ?>: hoje <strong><?php echo (int)$usoHoje[$c]['hoje']; ?></strong> de <?php echo $teto > 0 ? '<strong>~' . $teto . '</strong>' : 'sem teto'; ?> usados<?php echo $usoHoje[$c]['bloqueado'] ? ' · <span style="color:#b54708">o Google cortou às ' . admin_esc($usoHoje[$c]['bloqueado_em']) . '</span>' : ''; ?></p>
            <?php endforeach; ?>
            <p style="margin:4px 0;font-size:13px;color:#4b5563">Renova às <?php echo admin_esc((string)$usoHoje['renova']); ?>.</p>
            <?php if ($porTarefa): ?><p style="margin:10px 0 4px">📊 Gastou mais com: <strong><?php echo admin_esc($nomesT[$porTarefa[0]['acao']] ?? $porTarefa[0]['acao']); ?></strong> (<?php echo (int)$porTarefa[0]['n']; ?>)<?php foreach (array_slice($porTarefa, 1, 3) as $pt): ?> · <?php echo admin_esc($nomesT[$pt['acao']] ?? $pt['acao']); ?> (<?php echo (int)$pt['n']; ?>)<?php endforeach; ?></p><?php endif; ?>
            <?php foreach ($prev as $p): ?><p style="margin:2px 0">⏳ No ritmo atual, <?php echo admin_esc($p); ?>.</p><?php endforeach; ?>
            <form method="post" style="margin-top:14px">
                <input type="hidden" name="acao" value="controle">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:10px">
                <?php foreach ($modos as $m => [$tit, $desc]): ?>
                    <label style="border:2px solid <?php echo $ctl['modo'] === $m ? '#fbb928' : '#e5e7eb'; ?>;border-radius:12px;padding:12px;cursor:pointer;background:<?php echo $ctl['modo'] === $m ? '#fffbeb' : '#fff'; ?>"><input type="radio" name="modo" value="<?php echo $m; ?>" <?php echo $ctl['modo'] === $m ? 'checked' : ''; ?> onchange="this.form.submit()"> <strong><?php echo $tit; ?></strong><br><small style="color:#4b5563"><?php echo $desc; ?></small></label>
                <?php endforeach; ?>
                </div>
                <p style="font-size:13px;color:#4b5563">A plataforma descobre sozinha o limite grátis do Google (e sobe aos poucos até achar). Nenhuma tarefa pode usar mais da metade da cota do dia, para uma não travar as outras. Tira-dúvidas do aluno, Desafio e redação nunca esperam.</p>
                <details style="margin:8px 0"><summary style="cursor:pointer">Ver detalhes</summary>
                    <p style="font-size:13px"><?php foreach (['texto', 'voz', 'busca'] as $c): ?><?php echo $nomesC[$c]; ?>: limite grátis <?php echo $aprend[$c]['medido'] ? 'medido' : 'em teste'; ?> ~<?php echo (int)$aprend[$c]['limite']; ?>/dia · <?php endforeach; ?></p>
                    <p style="font-size:13px"><?php foreach ($porTarefa as $pt): ?><?php echo admin_esc($nomesT[$pt['acao']] ?? $pt['acao']); ?> (<?php echo $pt['cota']; ?>): <?php echo (int)$pt['n']; ?> · <?php endforeach; ?></p>
                    <p style="font-size:13px;margin-top:10px"><strong>O que a IA pode fazer</strong> (desligado = não roda de jeito nenhum):</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:6px 16px;margin:8px 0">
                <?php foreach (ia_controle_tipos() as $t => $nome): ?>
                    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="lig[<?php echo $t; ?>]" value="1" <?php echo $ctl['ligados'][$t] ? 'checked' : ''; ?>> <?php echo admin_esc($nome); ?></label>
                <?php endforeach; ?>
                </div>
                </details>
                <button class="admin-btn admin-btn-primary" type="submit">Salvar</button>
            </form>
        </section>

        <section class="admin-panel">
            <h2>Por tipo de ação (este mês)</h2>
            <table><tr><th>Ação</th><th class="n">Execuções</th><th class="n">Chamadas</th><th class="n">Tokens entrada</th><th class="n">Tokens saída</th><th class="n">Custo</th><th class="n">Média por execução</th></tr>
            <?php foreach ($porAcao as $r): ?>
                <tr><td><?php echo admin_esc($acoes[$r['acao']] ?? $r['acao']); ?></td><td class="n"><?php echo (int)$r['execs']; ?></td><td class="n"><?php echo (int)$r['chamadas']; ?></td>
                    <td class="n"><?php echo $num($r['te']); ?></td><td class="n"><?php echo $num($r['ts']); ?></td><td class="n"><strong><?php echo $brl($r['u']); ?></strong></td><td class="n"><?php echo $brl((float)$r['u'] / max(1, (int)$r['execs'])); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$porAcao): ?><tr><td colspan="7">Nenhum gasto registrado neste mês ainda. A medição começa a partir desta atualização.</td></tr><?php endif; ?>
            </table>
        </section>

        <section class="admin-panel">
            <h2>Por curso (este mês)</h2>
            <table><tr><th>Curso</th><th class="n">Execuções</th><th class="n">Custo</th></tr>
            <?php foreach ($porCurso as $r): ?><tr><td><?php echo admin_esc((string)$r['curso']); ?></td><td class="n"><?php echo (int)$r['execs']; ?></td><td class="n"><?php echo $brl($r['u']); ?></td></tr><?php endforeach; ?>
            </table>
            <p style="font-size:12px;color:#6b7280">"(sem curso)" = ações que não são de uma aula: questões, redações, edital, desafio, classificação.</p>
        </section>

        <section class="admin-panel">
            <h2>Dia a dia (14 dias)</h2>
            <table><tr><th>Dia</th><th class="n">Texto</th><th class="n">Voz</th><th class="n">Pesquisa</th><th class="n">Custo</th></tr>
            <?php foreach ($dias as $r): ?><tr><td><?php echo date('d/m', strtotime((string)$r['d'])); ?></td><td class="n"><?php echo (int)$r['texto']; ?></td><td class="n"><?php echo (int)$r['voz']; ?></td><td class="n"><?php echo (int)$r['busca']; ?></td><td class="n"><?php echo $brl($r['u']); ?></td></tr><?php endforeach; ?>
            </table>
        </section>

        <section class="admin-panel">
            <h2>Últimas execuções</h2>
            <table><tr><th>Quando</th><th>Ação</th><th class="n">Ref.</th><th class="n">Chamadas</th><th class="n">Tokens</th><th class="n">Custo</th></tr>
            <?php foreach ($ultimas as $r): ?><tr><td><?php echo date('d/m H:i', strtotime((string)$r['quando'])); ?></td><td><?php echo admin_esc($acoes[$r['acao']] ?? $r['acao']); ?></td><td class="n"><?php echo (int)$r['ref_id'] ?: '—'; ?></td><td class="n"><?php echo (int)$r['chamadas']; ?></td><td class="n"><?php echo $num($r['t']); ?></td><td class="n"><?php echo $brl($r['u']); ?></td></tr><?php endforeach; ?>
            </table>
        </section>

        <section class="admin-panel">
            <h2>Limite, dólar e preços</h2>
            <form method="post" class="cs-form">
                <input type="hidden" name="acao" value="config">
                <p>Plano do Google: <label><input type="radio" name="plano" value="gratis" style="width:auto" <?php echo $cfg['plano'] !== 'pago' ? 'checked' : ''; ?>> Grátis (não cobra; valores são simulação)</label> <label><input type="radio" name="plano" value="pago" style="width:auto" <?php echo $cfg['plano'] === 'pago' ? 'checked' : ''; ?>> Pago (cobrança real)</label></p>
                <p>Limite de gasto por mês (R$, 0 = sem limite; vale no plano pago): <input name="limite" value="<?php echo admin_esc((string)$cfg['limite_mensal_brl']); ?>"> · Dólar (R$): <input name="dolar" value="<?php echo admin_esc((string)$cfg['dolar']); ?>"></p>
                <table><tr><th>Modelo (nome contém)</th><th>Tipo</th><th class="n">US$ por 1 milhão de tokens de entrada</th><th class="n">US$ por 1 milhão de tokens de saída</th></tr>
                <?php foreach ($cfg['precos'] as $k => $p): ?>
                    <tr><td><?php echo admin_esc($p['contem'] === '' ? '(qualquer outro)' : $p['contem']); ?></td><td><?php echo admin_esc((string)($p['rotulo'] ?? '')); ?></td>
                        <td class="n"><input name="pe[<?php echo $k; ?>]" value="<?php echo admin_esc((string)$p['entrada']); ?>"></td><td class="n"><input name="ps[<?php echo $k; ?>]" value="<?php echo admin_esc((string)$p['saida']); ?>"></td></tr>
                <?php endforeach; ?>
                </table>
                <p>Pesquisa no Google (US$ por chamada, além dos tokens): <input name="busca" value="<?php echo admin_esc((string)$cfg['busca_por_chamada']); ?>"> <small>No plano pago são 1.500 grátis por dia; depois, US$ 0,035 cada.</small></p>
                <button class="admin-btn admin-btn-primary" type="submit">Salvar</button>
            </form>
        </section>
    </main>
</div>
<script>window.lucide && lucide.createIcons();</script>
</body>
</html>
