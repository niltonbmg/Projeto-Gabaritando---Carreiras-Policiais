<?php
// admin-inicio.php — "Comece aqui": um painel só, em linguagem simples.
//  1) Como está a plataforma (sinal verde/amarelo/vermelho)
//  2) O que VOCÊ precisa fazer agora (em ordem, com um botão cada)
//  3) O que a IA está fazendo sozinha (só acompanhar)
//  4) Saneamento da plataforma, passo a passo
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
require_once __DIR__ . '/includes/ia-consumo-lib.php';
require_once __DIR__ . '/includes/revisao-aulas-lib.php';
require_once __DIR__ . '/includes/padrao-lib.php';

$pdo = admin_cursos_db();
ia_fila_bootstrap($pdo);
rev_bootstrap($pdo);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = (string)($_POST['acao'] ?? '');
        if ($acao === 'limpar_repetidas') {
            $n = 0;
            foreach (padrao_provisorias_repetidas($pdo) as $p) {
                padrao_trocar_provisoria($pdo, $p['provisoria']['id'], $p['real']['id']);
                $n++;
            }
            $msg = "✅ $n aula(s) provisória(s) removidas. O progresso dos alunos passou para a aula real.";
        } elseif ($acao === 'titulos') {
            $msg = '✅ ' . padrao_corrigir($pdo, 'padrao_titulos') . ' título(s) agora seguem o nome do edital.';
        }
    } catch (Throwable $e) {
        $msg = '⚠️ ' . $e->getMessage();
    }
}

$num = static fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$seguro = static function (callable $f, $padrao = 0) {
    try {
        return $f();
    } catch (Throwable $e) {
        return $padrao;
    }
};

// ---------- números ----------
$roboArq = __DIR__ . '/uploads/.ia-robo-ultima';
$roboMin = is_file($roboArq) ? (int)floor((time() - filemtime($roboArq)) / 60) : null;
$uso = podcast_uso_resumo();
$ctl = ia_controle();
$aud = padrao_auditar($pdo);
$pares = padrao_provisorias_repetidas($pdo);
$pdf = $aud['pdf'];
$pdfConf = (int)($pdf['confere'] ?? 0) + (int)($pdf['ok_manual'] ?? 0);
$pdfSusp = (int)($pdf['suspeita'] ?? 0);
$pdfPend = (int)($pdf['pendente'] ?? 0);
$pdfTotal = $pdfConf + $pdfSusp + $pdfPend;
$reportes = $seguro(fn() => $num("SELECT COUNT(DISTINCT aula_id) FROM aula_reportes WHERE status = 'aberto'"));
$redAtrasadas = $seguro(fn() => $num("SELECT COUNT(*) FROM redacoes WHERE status <> 'corrigida' AND enviada_em < NOW() - INTERVAL 72 HOUR"));
$redFila = $seguro(fn() => $num("SELECT COUNT(*) FROM redacoes WHERE status <> 'corrigida'"));
$lgpdAbertos = $seguro(fn() => $num("SELECT COUNT(*) FROM lgpd_solicitacoes WHERE status = 'aberta'"));
$lgpdPerto = $seguro(fn() => $num("SELECT COUNT(*) FROM lgpd_solicitacoes WHERE status = 'aberta' AND criado_em < NOW() - INTERVAL 10 DAY"));
$semResposta = $seguro(fn() => $num("SELECT COUNT(*) FROM forum_duvidas_topicos t WHERE t.status = 'ativo' AND NOT EXISTS (SELECT 1 FROM forum_duvidas_respostas r WHERE r.topico_id = t.id)")
    + $num("SELECT COUNT(*) FROM aulas_forum_topicos t WHERE t.status = 'ativo' AND NOT EXISTS (SELECT 1 FROM aulas_forum_respostas r WHERE r.topico_id = t.id)"));
$fila = [];
foreach ($pdo->query("SELECT tipo, IF(status = 'erro' AND erro LIKE 'Aguardando%', 'espera', status) s, COUNT(*) n FROM ia_fila GROUP BY tipo, s") as $r) {
    $fila[$r['tipo']][$r['s']] = (int)$r['n'];
}
$feitasHoje = $seguro(fn() => $num("SELECT COUNT(DISTINCT execucao) FROM ia_consumo WHERE criado_em >= CURDATE() AND ok = 1"));
$alunosAtivos = $seguro(fn() => $num("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'"));
$desafioHoje = $seguro(fn() => $num("SELECT COUNT(*) FROM desafio_tentativas t JOIN desafios d ON d.id = t.desafio_id WHERE d.data = CURDATE() AND t.finalizado_em IS NOT NULL"));

// ---------- o que fazer agora (ordem = urgência) ----------
$tarefas = [];
if ($roboMin === null || $roboMin > 15) {
    $tarefas[] = ['🔴', 'O robô da IA está parado', $roboMin === null ? 'Ele ainda não rodou nenhuma vez com a versão nova.' : "Última vez há $roboMin minutos.",
        'Sem ele, a IA só trabalha com esta página aberta. No hPanel: Avançado → Cron Jobs → "a cada 5 minutos" e cole: /usr/bin/php ' . __DIR__ . '/ia-robo.php', 'saude.php', 'Ver Saúde'];
}
if ($lgpdPerto) {
    $tarefas[] = ['🔴', "$lgpdPerto pedido(s) de aluno sobre dados pessoais perto do prazo", 'A LGPD dá 15 dias para responder.', 'Responda para evitar problema legal.', 'admin-lgpd.php', 'Responder'];
}
if ($redAtrasadas) {
    $tarefas[] = ['🟠', "$redAtrasadas redação(ões) esperando há mais de 3 dias", 'O aluno está aguardando a nota.', 'Peça ao professor de redação para corrigir.', 'correcao-redacao.php', 'Abrir correção'];
}
if ($reportes) {
    $tarefas[] = ['🟠', "$reportes aula(s) com problema reportado por aluno", 'Alunos avisaram de PDF errado, vídeo ou conteúdo.', 'Veja e resolva em um clique.', 'admin-revisao-aulas.php', 'Ver reportes'];
}
if ($pdfSusp) {
    $tarefas[] = ['🟠', "$pdfSusp aula(s) com PDF de outro assunto", 'A IA conferiu e o PDF não bate com a aula. Ele já está escondido dos alunos.', 'Escolha: trocar o PDF ou deixar o Antigão gerar a aula pelo edital.', 'admin-revisao-aulas.php', 'Decidir'];
}
if ($pares) {
    $tarefas[] = ['🟡', count($pares) . ' aula(s) provisória(s) repetindo aula real', 'O Antigão criou aulas provisórias para assuntos que já tinham aula de verdade.', 'Um clique remove as provisórias e passa o progresso dos alunos para a real.', 'form:limpar_repetidas', 'Limpar agora'];
}
if ($aud['titulos']) {
    $tarefas[] = ['🟡', count($aud['titulos']) . ' título(s) fora do padrão do edital', 'Ex.: "1.Globalização…" vira "1. Globalização…", com o nome do assunto no edital.', 'Só muda títulos que já falam do mesmo assunto. Não usa IA.', 'form:titulos', 'Padronizar'];
}
if ($aud['assunto_divergente'] ?? []) {
    $tarefas[] = ['🟡', count($aud['assunto_divergente']) . ' aula(s) com assunto que pode estar errado', 'O título fala de um assunto e a aula está ligada a outro no edital.', 'Confira na tela de Padrão e ajuste o assunto da aula.', 'admin-padrao.php', 'Conferir'];
}
if ($semResposta) {
    $tarefas[] = ['🟢', "$semResposta dúvida(s) de aluno sem resposta", 'Perguntas no fórum que ninguém respondeu ainda.', 'Responder rápido mantém o aluno engajado.', 'admin-mensagens.php', 'Responder'];
}
$urgentes = count(array_filter($tarefas, static fn($t) => $t[0] === '🔴'));
$sinal = $urgentes ? ['🔴', 'Precisa de você agora', '#b42318'] : ($tarefas ? ['🟡', 'Está funcionando — há tarefas para você', '#b54708'] : ['🟢', 'Tudo em ordem', '#067647']);

// ---------- saneamento passo a passo ----------
$pct = static fn(int $feito, int $total): int => $total > 0 ? (int)round($feito / $total * 100) : 100;
$vidFeitas = (int)($fila['videoaula']['feito'] ?? 0);
$vidTotal = $vidFeitas + (int)($fila['videoaula']['pendente'] ?? 0) + (int)($fila['videoaula']['espera'] ?? 0) + (int)($fila['videoaula']['erro'] ?? 0);
$passos = [
    ['Conferir se cada PDF é do assunto da aula', $pdfConf, $pdfTotal, 'a IA confere sozinha; você só decide os suspeitos'],
    ['Tirar aulas provisórias repetidas', 0, count($pares), count($pares) ? 'botão "Limpar agora" acima' : 'feito'],
    ['Títulos pelo nome do edital', $aud['total'] - count($aud['titulos']), $aud['total'], count($aud['titulos']) ? 'botão "Padronizar" acima' : 'feito'],
    ['Toda aula com teoria escrita', $aud['total'] - count($aud['sem_teoria']) - count($aud['sem_material']), $aud['total'], 'a IA escreve a partir do vídeo/PDF (cota de texto, rende bem)'],
    ['Videoaulas do Antigão prontas', $vidFeitas, max($vidTotal, $vidFeitas), 'a IA grava ~1 a 2 por dia no plano grátis, começando pelas primeiras aulas'],
];
$tipoNome = ['videoaula' => '🎬 Videoaulas', 'aula_ia' => '🤖 Teorias do Antigão', 'apostila' => '📄 Teorias a partir do material', 'conferir_pdf' => '🔎 Conferência de PDF', 'questoes' => '📚 Questões', 'podcast' => '🎧 Podcasts', 'redacao' => '✍️ Leituras de redação'];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comece aqui | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .ci-sinal{display:flex;gap:14px;align-items:center;background:#fff;border:1px solid #eef0f3;border-radius:14px;padding:16px 18px}
        .ci-sinal .bola{font-size:40px;line-height:1}.ci-sinal h2{margin:0;font-size:22px}.ci-sinal p{margin:2px 0 0;color:#4b5563}
        .ci-t{display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center;background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:14px 16px;margin:10px 0}
        .ci-t .n{font-size:26px}.ci-t h3{margin:0;font-size:16px}.ci-t p{margin:3px 0 0;font-size:13px;color:#4b5563}.ci-t .como{color:#111;font-weight:500}
        .ci-passo{display:grid;grid-template-columns:28px 1fr 70px;gap:10px;align-items:center;padding:10px 0;border-top:1px solid #f0f1f3}
        .ci-passo .barra{height:10px;background:#eef0f3;border-radius:6px;overflow:hidden;margin-top:6px}.ci-passo .barra i{display:block;height:100%;background:#067647}
        .ci-passo small{color:#6b7280}.ci-pct{font-weight:700;text-align:right}
        .ci-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}
        .ci-box{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:12px 14px}.ci-box strong{display:block;font-size:22px}.ci-box span{font-size:12px;color:#6b7280;text-transform:uppercase}
        .ci-ok{color:#067647;font-weight:700}
        @media (max-width:640px){.ci-t{grid-template-columns:auto 1fr}.ci-t .bt{grid-column:1/-1}}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>🏠 Comece aqui</h1><p>Tudo o que importa numa página só: o que você precisa fazer e o que a plataforma já está fazendo sozinha.</p></div>
        </section>
        <?php if ($msg !== ''): ?><div class="admin-alert <?php echo str_starts_with($msg, '✅') ? 'admin-alert-success' : 'admin-alert-error'; ?>"><?php echo admin_esc($msg); ?></div><?php endif; ?>

        <section class="ci-sinal">
            <div class="bola"><?php echo $sinal[0]; ?></div>
            <div><h2 style="color:<?php echo $sinal[2]; ?>"><?php echo $sinal[1]; ?></h2>
                <p><?php echo $tarefas ? count($tarefas) . ' tarefa(s) para você, em ordem de importância.' : 'Nada pendente. A IA segue trabalhando sozinha.'; ?></p></div>
        </section>

        <section class="admin-panel">
            <h2>1. O que você precisa fazer</h2>
            <?php if (!$tarefas): ?><p class="ci-ok">✓ Nenhuma tarefa agora. Pode acompanhar abaixo.</p><?php endif; ?>
            <?php foreach ($tarefas as [$cor, $titulo, $oque, $como, $link, $bt]): ?>
                <div class="ci-t">
                    <div class="n"><?php echo $cor; ?></div>
                    <div><h3><?php echo admin_esc($titulo); ?></h3><p><?php echo admin_esc($oque); ?></p><p class="como">👉 <?php echo admin_esc($como); ?></p></div>
                    <div class="bt">
                        <?php if (str_starts_with($link, 'form:')): ?>
                            <form method="post" onsubmit="return confirm('<?php echo admin_esc($titulo); ?>: confirmar?');"><input type="hidden" name="acao" value="<?php echo admin_esc(substr($link, 5)); ?>"><button class="admin-btn admin-btn-primary" type="submit"><?php echo admin_esc($bt); ?></button></form>
                        <?php else: ?>
                            <a class="admin-btn admin-btn-primary" href="<?php echo admin_esc($link); ?>"><?php echo admin_esc($bt); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="admin-panel">
            <h2>2. O que a IA está fazendo sozinha</h2>
            <p style="font-size:14px;color:#4b5563;margin-top:0">Você não precisa fazer nada aqui, só acompanhar. Plano <?php echo ia_consumo_config()['plano'] === 'pago' ? 'pago' : '<strong>grátis</strong> (nada é cobrado)'; ?>. A cota do Google renova todo dia às <?php echo admin_esc(substr((string)$uso['renova'], -5)); ?>.</p>
            <div class="ci-grid">
                <div class="ci-box"><span>Feito hoje</span><strong><?php echo (int)$feitasHoje; ?></strong><small>tarefas da IA concluídas</small></div>
                <div class="ci-box"><span>Robô</span><strong><?php echo $roboMin === null ? '—' : ($roboMin <= 15 ? '🟢 rodando' : '🔴 parado'); ?></strong><small><?php echo $roboMin === null ? 'sem registro ainda' : "última vez há $roboMin min"; ?></small></div>
                <?php foreach (['texto' => '✍️ Texto', 'voz' => '🎙 Voz', 'busca' => '🔎 Pesquisa'] as $k => $nome): $teto = (int)$ctl['teto'][$k]; ?>
                    <div class="ci-box"><span><?php echo $nome; ?> hoje</span><strong><?php echo (int)$uso[$k]['hoje']; ?><?php echo $teto ? ' / ' . $teto : ''; ?></strong>
                        <small><?php echo $uso[$k]['bloqueado'] ? '⛔ acabou por hoje' : ($teto && $uso[$k]['hoje'] >= $teto ? '⛔ chegou no seu teto' : '✅ disponível'); ?></small></div>
                <?php endforeach; ?>
            </div>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;font-size:14px">
                <tr style="text-align:left;color:#6b7280"><th style="padding:6px">Tarefa</th><th>Ligada?</th><th>Prontas</th><th>Na fila</th><th>Esperando revisão</th></tr>
                <?php foreach ($tipoNome as $t => $nome): $f = $fila[$t] ?? []; if (!$f && empty($ctl['ligados'][$t])) { continue; } ?>
                    <tr style="border-top:1px solid #f0f1f3"><td style="padding:6px"><?php echo $nome; ?></td><td><?php echo ($ctl['ligados'][$t] ?? true) ? '✅' : '⛔ desligada'; ?></td>
                        <td><?php echo (int)($f['feito'] ?? 0); ?></td><td><?php echo (int)($f['pendente'] ?? 0); ?></td><td><?php echo (int)($f['espera'] ?? 0) ?: ''; ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p style="font-size:13px;margin-bottom:0"><a href="admin-consumo-ia.php#controle">🔒 Controle rígido</a>: escolha o que a IA pode fazer e o máximo por dia.</p>
        </section>

        <section class="admin-panel">
            <h2>3. Saneamento da plataforma</h2>
            <p style="font-size:14px;color:#4b5563;margin-top:0">Deixar todo o conteúdo antigo no padrão. Hoje: <strong><?php echo $aud['ok_pct']; ?>%</strong> das <?php echo (int)$aud['total']; ?> aulas já estão 100% no padrão.</p>
            <?php foreach ($passos as $i => [$nome, $feito, $total, $obs]): $p = $nome === 'Tirar aulas provisórias repetidas' ? ($total ? 0 : 100) : $pct($feito, $total); ?>
                <div class="ci-passo">
                    <div><?php echo $p >= 100 ? '✅' : ($i + 1) . '.'; ?></div>
                    <div><strong><?php echo admin_esc($nome); ?></strong> <small>· <?php echo admin_esc($obs); ?></small><div class="barra"><i style="width:<?php echo $p; ?>%"></i></div></div>
                    <div class="ci-pct"><?php echo $p; ?>%</div>
                </div>
            <?php endforeach; ?>
            <p style="font-size:13px;margin-bottom:0">Detalhes em <a href="admin-padrao.php">Padrão da plataforma</a> e <a href="admin-revisao-aulas.php">Revisão de aulas</a>.</p>
        </section>

        <section class="admin-panel">
            <h2>4. Seus alunos hoje</h2>
            <div class="ci-grid">
                <div class="ci-box"><span>Alunos ativos</span><strong><?php echo $alunosAtivos; ?></strong></div>
                <div class="ci-box"><span>Fizeram o Desafio hoje</span><strong><?php echo $desafioHoje; ?></strong></div>
                <div class="ci-box"><span>Redações na fila</span><strong><?php echo $redFila; ?></strong></div>
                <div class="ci-box"><span>Dúvidas sem resposta</span><strong><?php echo $semResposta; ?></strong></div>
                <div class="ci-box"><span>Pedidos LGPD abertos</span><strong><?php echo $lgpdAbertos; ?></strong></div>
            </div>
        </section>
    </main>
</div>
<script>window.lucide && lucide.createIcons();</script>
</body>
</html>
