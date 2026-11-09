<?php
// admin-ia.php
// Diagnóstico da IA (Gemini): chave, modelos da conta e teste rápido de texto e voz.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
@set_time_limit(120);

$temChave = gab_env('GEMINI_API_KEY', '') !== '';
$modelos = ['texto' => [], 'voz' => [], 'erro' => ''];
$teste = null;

if ($temChave) {
    $modelos = podcast_modelos_disponiveis(isset($_GET['atualizar']) || isset($_POST['testar']));
}

if ($temChave && ($_POST['testar'] ?? '') !== '') {
    $teste = ['texto' => '', 'texto_ms' => 0, 'voz' => '', 'voz_ms' => 0, 'erro' => ''];
    try {
        $t0 = microtime(true);
        $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => 'Responda só com a frase: Fala, combatente! A IA do Gabaritando está ligada.']]]]], 60);
        $teste['texto'] = trim((string)($r['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        $teste['texto_ms'] = (int)((microtime(true) - $t0) * 1000);

        $t0 = microtime(true);
        $pcm = podcast_tts([['quem' => 'Sargento', 'texto' => 'Fala, combatente! Aqui é o Antigão, teste de voz do Projeto Gabaritando.'], ['quem' => 'Ana', 'texto' => 'Recruta Zero Um na escuta, Antigão!']]);
        if ($pcm) {
            $wav = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, PODCAST_TAXA, PODCAST_TAXA * 2, 2, 16) . 'data' . pack('V', strlen($pcm)) . $pcm;
            $teste['voz'] = 'data:audio/wav;base64,' . base64_encode($wav);
        }
        $teste['voz_ms'] = (int)((microtime(true) - $t0) * 1000);
    } catch (Throwable $e) {
        $teste['erro'] = $e->getMessage();
    }
}

$filaMsg = '';
$pdoFila = null;
try {
    $pdoFila = db();
    ia_fila_bootstrap($pdoFila);
    if (($_POST['fila'] ?? '') === 'todas') {
        $filaMsg = ia_fila_todas_as_aulas($pdoFila) . ' aula(s) colocadas na fila. O robô faz tudo em segundo plano.';
    } elseif (($_POST['fila'] ?? '') === 'refazer_podcasts') {
        podcast_bootstrap($pdoFila);
        $ids = $pdoFila->query('SELECT aula_id FROM aula_podcasts')->fetchAll(PDO::FETCH_COLUMN);
        $pdoFila->exec('DELETE FROM aula_podcasts');
        foreach ($ids as $idAula) {
            ia_fila_adicionar($pdoFila, 'podcast', (int)$idAula);
        }
        $filaMsg = count($ids) . ' podcast(s) na fila para refazer com o Antigão e o Recruta 01.';
    } elseif (($_POST['fila'] ?? '') === 'videoaulas') {
        videoaula_bootstrap($pdoFila);
        $ids = $pdoFila->query("SELECT a.id FROM admin_curso_aulas a WHERE COALESCE(TRIM(a.video_url), '') = '' AND COALESCE(TRIM(a.pdf_arquivo), '') <> ''
                                 AND NOT EXISTS (SELECT 1 FROM aula_videoaulas v WHERE v.aula_id = a.id AND v.status = 'pronto')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $idAula) {
            ia_fila_adicionar($pdoFila, 'videoaula', (int)$idAula);
        }
        $filaMsg = count($ids) . ' aula(s) só com PDF colocadas na fila da Videoaula IA.';
    } elseif (($_POST['fila'] ?? '') === 'processar') {
        $r = ia_fila_processar_um($pdoFila);
        $filaMsg = $r === null ? 'A fila está vazia.' : ($r['ok'] ? 'Feito: ' . $r['tipo'] . ' da aula ' . $r['aula_id'] . '.' : 'Erro em ' . $r['tipo'] . ' da aula ' . $r['aula_id'] . ': ' . $r['erro']);
        $pdoFila = podcast_reconectar($pdoFila);
    }
    $fila = ia_fila_resumo($pdoFila);
} catch (Throwable $e) {
    $fila = null;
    $filaMsg = 'Erro na fila: ' . $e->getMessage();
}
$caminhoRobo = __DIR__ . '/ia-robo.php';

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico da IA | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>.ia-ok{color:#17803d;font-weight:700}.ia-erro{color:#c0392b;font-weight:700}.ia-lista{display:flex;flex-wrap:wrap;gap:6px}.ia-lista code{background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:13px}</style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header"><div class="admin-page-heading"><h1>Diagnóstico da IA</h1><p>Confere a chave do Gemini, os modelos da sua conta e faz um teste rápido de texto e voz.</p></div></section>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>1. Chave</h2></div>
            <?php if ($temChave): ?>
                <p class="ia-ok">✓ GEMINI_API_KEY encontrada no .env.</p>
            <?php else: ?>
                <p class="ia-erro">✗ Chave não encontrada.</p>
                <p>Abra <strong>public_html/ead/.env</strong> e acrescente uma linha: <code>GEMINI_API_KEY=sua_chave</code> (sem espaços e sem aspas). Salve e recarregue esta página.</p>
            <?php endif; ?>
        </section>

        <?php if ($temChave): ?>
        <section class="admin-panel">
            <div class="admin-panel-title"><h2>2. Modelos disponíveis na sua conta</h2><a class="admin-btn admin-btn-light admin-btn-small" href="admin-ia.php?atualizar=1">Atualizar lista</a></div>
            <?php if ($modelos['erro'] !== ''): ?>
                <p class="ia-erro">✗ <?php echo admin_esc($modelos['erro']); ?></p>
            <?php else: ?>
                <p><strong>Texto</strong> (roteiro, apostila, questões), em ordem de uso:</p>
                <div class="ia-lista"><?php foreach ($modelos['texto'] as $m): ?><code><?php echo admin_esc($m); ?></code><?php endforeach; ?><?php if (!$modelos['texto']): ?><span class="ia-erro">nenhum</span><?php endif; ?></div>
                <p style="margin-top:12px"><strong>Voz</strong> (podcast):</p>
                <div class="ia-lista"><?php foreach ($modelos['voz'] as $m): ?><code><?php echo admin_esc($m); ?></code><?php endforeach; ?><?php if (!$modelos['voz']): ?><span class="ia-erro">nenhum modelo de voz nesta conta</span><?php endif; ?></div>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>3. Teste rápido</h2></div>
            <form method="post"><input type="hidden" name="testar" value="1"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="play"></i>Testar texto e voz (cerca de 20 segundos)</button></form>
            <?php if ($teste): ?>
                <?php if ($teste['erro'] !== ''): ?><p class="ia-erro" style="margin-top:12px">✗ <?php echo admin_esc($teste['erro']); ?></p><?php endif; ?>
                <?php if ($teste['texto'] !== ''): ?><p class="ia-ok" style="margin-top:12px">✓ Texto em <?php echo number_format($teste['texto_ms'] / 1000, 1, ',', ''); ?>s: “<?php echo admin_esc($teste['texto']); ?>”</p><?php endif; ?>
                <?php if ($teste['voz'] !== ''): ?><p class="ia-ok">✓ Voz em <?php echo number_format($teste['voz_ms'] / 1000, 1, ',', ''); ?>s:</p><audio controls src="<?php echo $teste['voz']; ?>"></audio><?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="admin-panel">
            <div class="admin-panel-title"><h2>4. Automação: fila da IA</h2></div>
            <p>Cada aula salva com "✨ Gerar com a IA" entra na fila. O robô faz a descrição/apostila e depois o podcast, sozinho.</p>
            <?php if ($filaMsg !== ''): ?><p class="<?php echo stripos($filaMsg, 'erro') !== false ? 'ia-erro' : 'ia-ok'; ?>"><?php echo admin_esc($filaMsg); ?></p><?php endif; ?>
            <?php if ($fila): ?>
                <p><strong><?php echo (int)$fila['pendente']; ?></strong> na fila · <strong><?php echo (int)$fila['feito']; ?></strong> feitos · <strong><?php echo (int)$fila['erro']; ?></strong> com erro</p>
                <?php foreach ($fila['ultimos_erros'] as $er): ?><p class="ia-erro" style="font-weight:400">✗ <?php echo admin_esc($er['tipo'] . ' · ' . ($er['titulo'] ?? ('aula ' . $er['aula_id'])) . ': ' . (string)$er['erro']); ?></p><?php endforeach; ?>
            <?php endif; ?>
            <div class="admin-table-actions" style="justify-content:flex-start;margin:10px 0">
                <form method="post" onsubmit="return confirm('Colocar na fila todas as aulas que ainda não passaram pela IA?');"><input type="hidden" name="fila" value="todas"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="wand-2"></i>Gerar para todas as aulas</button></form>
                <form method="post" onsubmit="return confirm('Gerar a Videoaula IA (slides + narração + perguntas) para todas as aulas que só têm PDF?');"><input type="hidden" name="fila" value="videoaulas"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="clapperboard"></i>Gerar Videoaulas IA (aulas só com PDF)</button></form>
                <form method="post" onsubmit="return confirm('Refazer TODOS os podcasts já gerados com o novo roteiro (Antigão e Recruta 01)? Os atuais saem do ar até o robô refazer cada um.');"><input type="hidden" name="fila" value="refazer_podcasts"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="mic"></i>Refazer todos os podcasts (novo roteiro)</button></form>
                <form method="post"><input type="hidden" name="fila" value="processar"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="play"></i>Processar 1 agora</button></form>
            </div>
            <p><strong>Ligar o robô (uma vez só):</strong> Hostinger → seu site → <strong>Avançado → Cron Jobs</strong> → tipo <strong>PHP</strong> → a cada <strong>5 minutos</strong> → comando:</p>
            <p><code><?php echo admin_esc($caminhoRobo); ?></code></p>
        </section>
        <?php endif; ?>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
