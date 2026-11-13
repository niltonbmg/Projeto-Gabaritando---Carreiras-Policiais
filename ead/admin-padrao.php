<?php
// admin-padrao.php — Auditoria de padrão: o que ainda está no formato antigo e a correção em lote (com custo antes).
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
require_once __DIR__ . '/includes/padrao-lib.php';

$pdo = admin_cursos_db();
ia_fila_bootstrap($pdo);
$cursoId = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = (string)($_POST['acao'] ?? '');
        $n = padrao_corrigir($pdo, $acao, $cursoId, (array)($_POST['ids'] ?? []));
        $sucesso = [
            'padrao_titulos' => "$n título(s) padronizados.",
            'padrao_teoria' => "$n aula(s) na fila: a IA escreve a teoria a partir do vídeo/PDF (PDF só depois de conferido na Revisão).",
            'padrao_antigao' => "$n aula(s) na fila do Antigão: teoria pelo edital, depois videoaula e podcast.",
            'padrao_videoaulas' => "$n videoaula(s) na fila para refazer no formato novo (10 a 15 min).",
            'padrao_antigao_refazer' => "$n aula(s) do Antigão na fila para reescrever no formato novo (teoria, videoaula e podcast).",
            'padrao_podcasts' => "$n podcast(s) na fila para regravar com o Antigão e o Recruta Zero Um.",
            'padrao_assunto' => "$n item(ns) na classificação automática por assunto.",
        ][$acao] ?? 'Feito.';
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}
$aud = padrao_auditar($pdo, $cursoId);
$cursos = $pdo->query('SELECT id, nome FROM admin_cursos ORDER BY nome')->fetchAll();
$pri = ia_prioridade();
$e = static fn($s): string => admin_esc((string)$s);
$linkAula = static fn(array $a): string => 'aulas.php?curso_id=' . (int)$a['curso_id'] . '&modulo_id=' . (int)$a['modulo_id'] . '&aula_id=' . (int)$a['id'];
$cartoes = [
    ['antigao_antigo', '🤖 Aulas do Antigão no formato antigo', 'Teoria curta, sem divisão por foco. Reescreve no formato atual (1.200 a 2.000 palavras, assunto grande em 2 a 4 aulas) e grava videoaula e podcast novos.', 'padrao_antigao_refazer', 'Reescrever no formato novo'],
    ['podcast_antigo', '🎧 Podcasts antigos', 'Gravados antes do Antigão e do Recruta Zero Um. Regrava com os personagens atuais.', 'padrao_podcasts', 'Regravar podcasts'],
    ['videoaula_antiga', '🎬 Videoaulas do Antigão no formato antigo', 'Menos de ' . PADRAO_SLIDES_MIN . ' slides, narração curta e corrida, ou gravadas antes da correção em que a voz lia a instrução em inglês. Refaz no padrão novo: 10 a 15 minutos, 10 a 14 slides, fala calma com exemplos, perguntas no meio da aula, logomarca e fórmulas legíveis.', 'padrao_videoaulas', 'Refazer no padrão novo'],
    ['sem_teoria', '📄 Aulas sem teoria escrita', 'Têm vídeo ou PDF, mas o texto da aula é só um lembrete (ex.: "Vá direto para o PDF"). A IA escreve a teoria a partir do material.', 'padrao_teoria', 'Escrever as teorias'],
    ['sem_material', '🕳 Aulas vazias', 'Sem vídeo, sem PDF e sem teoria. O Antigão escreve pelo assunto do edital e grava a videoaula.', 'padrao_antigao', 'Antigão preencher'],
    ['sem_assunto', '🏷 Aulas sem assunto do edital', 'Sem assunto ligado, a aula não entra no cronograma nem na trava das 5 questões. A IA sugere o assunto.', 'padrao_assunto', 'Classificar'],
];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Padrão da plataforma | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .pd-nota{font-size:40px;font-weight:700}.pd-barra{height:12px;background:#eef0f3;border-radius:6px;overflow:hidden}.pd-barra i{display:block;height:100%;background:#067647}
        .pd-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px}
        .pd-card{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:8px}
        .pd-card h3{margin:0;font-size:16px}.pd-card .n{font-size:28px;font-weight:700}.pd-card p{margin:0;font-size:13px;color:#4b5563}
        .pd-card details{font-size:13px}.pd-card ul{margin:6px 0;padding-left:18px;max-height:180px;overflow:auto}
        .pd-ok{color:#067647}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:6px 8px;border-top:1px solid #f3f4f6;font-size:13px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>🧭 Padrão da plataforma</h1><p>Tudo o que ainda está no formato antigo, encontrado pelos próprios dados (sem gastar IA para descobrir). Cada correção mostra o custo antes e vai para a fila do robô.</p></div>
        </section>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><?php echo $e($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><?php echo $e($sucesso); ?></div><?php endif; ?>
        <section class="admin-panel">
            <form method="get">Curso: <select name="curso_id" onchange="this.form.submit()"><option value="0">Todos os cursos</option><?php foreach ($cursos as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $cursoId ? 'selected' : ''; ?>><?php echo $e($c['nome']); ?></option><?php endforeach; ?></select></form>
            <p style="margin:12px 0 4px">Aulas 100% no padrão: <span class="pd-nota"><?php echo $aud['ok_pct']; ?>%</span> de <?php echo (int)$aud['total']; ?> aulas</p>
            <div class="pd-barra"><i style="width:<?php echo $aud['ok_pct']; ?>%"></i></div>
            <?php if (!empty($pri['podcasts_pausados'])): ?><p style="font-size:13px;color:#b54708;margin-top:8px">⏸ Os podcasts estão pausados na Central da IA: os regravados entram na fila e saem quando você liberar.</p><?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="pd-grid">
            <?php foreach ($cartoes as [$k, $tit, $desc, $acao, $bt]): $lista = $aud[$k]; ?>
                <div class="pd-card">
                    <h3><?php echo $tit; ?></h3>
                    <div class="n <?php echo $lista ? '' : 'pd-ok'; ?>"><?php echo $lista ? count($lista) : '✓ 0'; ?></div>
                    <p><?php echo $e($desc); ?></p>
                    <?php if ($lista): ?>
                        <details><summary>Ver quais (<?php echo count($lista); ?>)</summary><ul>
                            <?php foreach (array_slice($lista, 0, 150) as $a): ?><li><a href="<?php echo $e($linkAula($a)); ?>" target="_blank"><?php echo $e($a['titulo']); ?></a> <small style="color:#6b7280">· <?php echo $e($a['curso']); ?></small></li><?php endforeach; ?>
                        </ul></details>
                        <form method="post"><input type="hidden" name="acao" value="<?php echo $acao; ?>"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><?php echo $e($bt); ?></button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
                <div class="pd-card">
                    <h3>🔎 PDFs conferidos</h3>
                    <?php $p = $aud['pdf']; $conf = (int)($p['confere'] ?? 0) + (int)($p['ok_manual'] ?? 0); $tot = $conf + (int)($p['suspeita'] ?? 0) + (int)($p['pendente'] ?? 0); ?>
                    <div class="n"><?php echo $conf; ?> / <?php echo $tot; ?></div>
                    <p><?php echo (int)($p['suspeita'] ?? 0); ?> suspeito(s) · <?php echo (int)($p['pendente'] ?? 0); ?> não conferido(s). A conferência roda sozinha; as decisões ficam na Revisão de aulas.</p>
                    <a class="admin-btn admin-btn-small" href="admin-revisao-aulas.php<?php echo $cursoId ? '?curso_id=' . $cursoId : ''; ?>">Abrir Revisão de aulas</a>
                </div>
                <div class="pd-card">
                    <h3>❓ Banco de questões</h3>
                    <?php $q = $aud['questoes']; ?>
                    <p><strong><?php echo (int)($q['total'] ?? 0); ?></strong> ativas · <strong><?php echo (int)($q['sem_comentario'] ?? 0); ?></strong> sem comentário · <strong><?php echo (int)($q['sem_gabarito'] ?? 0); ?></strong> sem gabarito · <strong><?php echo (int)($q['sem_assunto'] ?? 0); ?></strong> sem assunto · <strong><?php echo (int)($q['sem_dificuldade'] ?? 0); ?></strong> sem dificuldade</p>
                    <p>Sem gabarito, a questão não entra no Desafio nem conta na trava. Sem assunto, entra na classificação automática (cartão "sem assunto").</p>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <h2>✏️ Títulos fora do padrão (<?php echo count($aud['titulos']); ?>)</h2>
            <p style="font-size:13px;color:#4b5563">Padrão: o nome do assunto no edital, mantendo o número de ordem da aula (ex.: "2.1. Convenção Americana sobre Direitos Humanos"). Várias aulas do mesmo assunto viram "· Parte 1, 2…". Desmarque o que não quiser mudar. Não usa IA.</p>
            <?php if ($aud['titulos']): ?>
            <form method="post">
                <input type="hidden" name="acao" value="padrao_titulos"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                <div style="max-height:420px;overflow:auto"><table><tr><th><input type="checkbox" checked onclick="document.querySelectorAll('.pd-t').forEach(c => c.checked = this.checked)"></th><th>Como está</th><th>Como fica</th><th>Curso</th></tr>
                <?php foreach ($aud['titulos'] as $t): ?><tr><td><input class="pd-t" type="checkbox" name="ids[]" value="<?php echo (int)$t['id']; ?>" checked></td><td><?php echo $e($t['de']); ?></td><td><strong><?php echo $e($t['para']); ?></strong></td><td><?php echo $e($t['curso']); ?></td></tr><?php endforeach; ?>
                </table></div>
                <button class="admin-btn admin-btn-primary" type="submit" style="margin-top:10px">Aplicar nos marcados</button>
            </form>
            <?php else: ?><p class="pd-ok">✓ Todos os títulos no padrão.</p><?php endif; ?>
        </section>

        <section class="admin-panel">
            <h2>👯 Aulas repetidas no mesmo curso (<?php echo count($aud['duplicadas']); ?>)</h2>
            <p style="font-size:13px;color:#4b5563">Mesmo assunto cadastrado mais de uma vez (ex.: "Classes de palavras" e "5. Classes de palavras."). Confira e apague a sobra em Cursos &gt; Módulos. Não apagamos sozinhos para não perder progresso de aluno.</p>
            <?php foreach (array_slice($aud['duplicadas'], 0, 100) as $g): ?>
                <div style="border-top:1px solid #f0f1f3;padding:8px 0;font-size:13px"><strong><?php echo $e($g[0]['curso']); ?>:</strong>
                    <?php foreach ($g as $a): ?> <a href="<?php echo $e($linkAula($a)); ?>" target="_blank">#<?php echo (int)$a['id']; ?> <?php echo $e($a['titulo']); ?></a> <?php echo trim((string)$a['video_url']) !== '' ? '🎬' : ''; ?><?php echo trim((string)$a['pdf_arquivo']) !== '' ? '📄' : ''; ?> ·<?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$aud['duplicadas']): ?><p class="pd-ok">✓ Nenhuma aula repetida.</p><?php endif; ?>
        </section>
    </main>
</div>
<script>window.lucide && lucide.createIcons();</script>
<script src="assets/js/ia-custo.js?v=2" data-api="api/ia-estimativa.php"></script>
</body>
</html>
