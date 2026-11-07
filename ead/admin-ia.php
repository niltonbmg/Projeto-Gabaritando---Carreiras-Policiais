<?php
// admin-ia.php
// Diagnóstico da IA (Gemini): chave, modelos da conta e teste rápido de texto e voz.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/podcast-lib.php';
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
        $r = podcast_gemini(PODCAST_MODELO_VOZ, [
            'contents' => [['parts' => [['text' => "Sargento: Fala, combatente! Teste de voz do Gabaritando.\nAna: Funcionou, Sargento!"]]]],
            'generationConfig' => ['responseModalities' => ['AUDIO'], 'speechConfig' => ['multiSpeakerVoiceConfig' => ['speakerVoiceConfigs' => [
                ['speaker' => 'Sargento', 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => PODCAST_VOZ_SARGENTO]]],
                ['speaker' => 'Ana', 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => PODCAST_VOZ_ANA]]],
            ]]]],
        ], 60);
        $pcm = base64_decode((string)($r['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? ''), true);
        if ($pcm) {
            $wav = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, PODCAST_TAXA, PODCAST_TAXA * 2, 2, 16) . 'data' . pack('V', strlen($pcm)) . $pcm;
            $teste['voz'] = 'data:audio/wav;base64,' . base64_encode($wav);
        }
        $teste['voz_ms'] = (int)((microtime(true) - $t0) * 1000);
    } catch (Throwable $e) {
        $teste['erro'] = $e->getMessage();
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
        <?php endif; ?>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
