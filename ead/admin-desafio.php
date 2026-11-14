<?php
// admin-desafio.php
// Desafio do Dia no painel: questões de hoje, mensagem para o WhatsApp,
// rankings, prêmio do mês e ganhador.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/desafio-lib.php';
@set_time_limit(120);
date_default_timezone_set('America/Bahia');

$pdo = admin_cursos_db();
desafio_bootstrap($pdo);
$hoje = date('Y-m-d');
$mes = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['mes'] ?? '')) ? (string)$_GET['mes'] : substr($hoje, 0, 7);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = (string)($_POST['acao'] ?? '');
        if ($acao === 'refazer') {
            desafio_gerar($pdo, $hoje, true);
            $sucesso = 'Desafio de hoje refeito com novas questões.';
        } elseif ($acao === 'premio') {
            $pdo->prepare('INSERT INTO desafio_premios (mes, premio) VALUES (:m, :p) ON DUPLICATE KEY UPDATE premio = VALUES(premio)')
                ->execute([':m' => $mes, ':p' => mb_substr(trim((string)($_POST['premio'] ?? '')), 0, 500)]);
            $sucesso = 'Prêmio do mês salvo. Já aparece para os alunos e na mensagem do WhatsApp.';
        } elseif ($acao === 'ganhador') {
            $top = desafio_ranking_mes($pdo, $mes, 1);
            if (!$top) {
                throw new RuntimeException('Ninguém participou dos desafios neste mês.');
            }
            $pdo->prepare('INSERT INTO desafio_premios (mes, ganhador_id, definido_em) VALUES (:m, :g, NOW()) ON DUPLICATE KEY UPDATE ganhador_id = VALUES(ganhador_id), definido_em = NOW()')
                ->execute([':m' => $mes, ':g' => (int)$top[0]['usuario_id']]);
            $sucesso = 'Ganhador do mês definido: ' . $top[0]['nome'] . '.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$desafio = null;
try {
    $desafio = desafio_gerar($pdo, $hoje);
} catch (Throwable $e) {
    $erro = $erro ?: $e->getMessage();
}
$questoes = $desafio ? desafio_questoes($pdo, $desafio) : [];
$msgWhats = $desafio ? desafio_mensagem_whatsapp($pdo, $desafio) : '';
$rankDia = $desafio ? desafio_ranking_dia($pdo, (int)$desafio['id'], 50) : [];
$rankMes = desafio_ranking_mes($pdo, $mes, 50);
$premio = desafio_premio($pdo, $mes);
$participantes = $desafio ? (int)$pdo->query('SELECT COUNT(*) FROM desafio_tentativas WHERE finalizado_em IS NOT NULL AND desafio_id = ' . (int)$desafio['id'])->fetchColumn() : 0;
$abertos = $desafio ? (int)$pdo->query('SELECT COUNT(*) FROM desafio_tentativas WHERE finalizado_em IS NULL AND desafio_id = ' . (int)$desafio['id'])->fetchColumn() : 0;

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desafio do Dia | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .dz-grade{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:16px}
        .dz-whats{width:100%;min-height:300px;font-family:inherit;font-size:14px;line-height:1.5;white-space:pre-wrap}
        .dz-nums{display:flex;flex-wrap:wrap;gap:12px}
        .dz-nums div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:10px 14px}
        .dz-nums strong{display:block;font-size:22px}
        .dz-nums span{font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em}
        .dz-q{font-size:13px;padding:6px 0;border-top:1px solid #f3f4f6}
        .dz-q b{color:#8a6300}
        .dz-r{display:grid;grid-template-columns:36px 1fr auto auto;gap:8px;padding:6px 0;border-top:1px solid #f3f4f6;font-size:14px}
        @media(max-width:1000px){.dz-grade{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>🎯 Desafio do Dia</h1><p>20 questões por dia, escolhidas pela IA, com ranking geral. Só as questões do desafio contam no ranking; as do ciclo de estudo ficam no histórico do aluno.</p></div>
            <div class="admin-page-actions"><a class="admin-btn admin-btn-light" href="desafio.php" target="_blank" rel="noopener"><i data-lucide="external-link"></i>Ver como aluno</a></div>
        </section>
        <?php if ($hoje < DESAFIO_INICIO): ?><div class="admin-alert admin-alert-success"><i data-lucide="calendar"></i>Estreia para os alunos em <?php echo date('d/m/Y', strtotime(DESAFIO_INICIO)); ?>. Até lá, esta tela é só pré-visualização (os alunos veem o aviso de estreia).</div><?php endif; ?>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <section class="admin-panel">
            <div class="dz-nums">
                <div><span>Concluíram hoje</span><strong><?php echo $participantes; ?></strong></div>
                <div><span>Começaram e não terminaram</span><strong><?php echo $abertos; ?></strong></div>
                <div><span>Questões de hoje</span><strong><?php echo count($questoes); ?></strong></div>
                <div><span>Escolhidas por</span><strong><?php echo $desafio && $desafio['escolhido_por'] === 'ia' ? 'IA' : 'Regras'; ?></strong></div>
            </div>
        </section>

        <div class="dz-grade">
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>📲 Mensagem para o grupo</h2><button class="admin-btn admin-btn-primary admin-btn-small" type="button" id="copiar"><i data-lucide="copy"></i>Copiar para o WhatsApp</button></div>
                <textarea class="dz-whats" id="msgWhats" readonly aria-label="Mensagem do WhatsApp"><?php echo admin_esc($msgWhats); ?></textarea>
                <p class="admin-form-hint" id="copiado" role="status" aria-live="polite">No WhatsApp, os asteriscos viram negrito.</p>
            </section>
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Questões de hoje</h2><form method="post" onsubmit="return confirm('Trocar as questões de hoje? Quem já fez continua com o resultado.');"><input type="hidden" name="acao" value="refazer"><button class="admin-btn admin-btn-light admin-btn-small" type="submit"><i data-lucide="refresh-cw"></i>Trocar questões</button></form></div>
                <?php foreach ($questoes as $n => $q): ?>
                    <div class="dz-q"><b><?php echo $n + 1; ?>.</b> <?php echo admin_esc((string)($q['materia'] ?? '')); ?> · <?php echo admin_esc((string)($q['dificuldade'] ?? '')); ?> — <?php echo admin_esc(mb_substr(trim(strip_tags((string)$q['enunciado'])), 0, 110)); ?>…</div>
                <?php endforeach; ?>
            </section>
        </div>

        <div class="dz-grade">
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>🏆 Ranking Geral · hoje</h2></div>
                <?php if (!$rankDia): ?><p class="admin-form-hint">Ninguém terminou ainda.</p><?php endif; ?>
                <?php foreach ($rankDia as $k => $r): ?><div class="dz-r"><strong><?php echo $k + 1; ?>º</strong><span><?php echo admin_esc((string)$r['nome']); ?></span><strong><?php echo (int)$r['acertos']; ?>/<?php echo (int)$r['total']; ?></strong><span><?php echo gmdate('i:s', (int)$r['tempo_seg']); ?></span></div><?php endforeach; ?>
            </section>
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>🏆 Ranking Geral · mês</h2>
                    <form method="get"><input type="month" name="mes" value="<?php echo admin_esc($mes); ?>" onchange="this.form.submit()" aria-label="Mês"></form></div>
                <form method="post" class="admin-form-grid" style="margin-bottom:12px">
                    <input type="hidden" name="acao" value="premio">
                    <div class="admin-form-field full"><label for="premio">🎁 Prêmio do mês</label><input id="premio" name="premio" value="<?php echo admin_esc((string)($premio['premio'] ?? '')); ?>" placeholder="Ex.: Camiseta Projeto Gabaritando + 1 mentoria individual"></div>
                    <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Salvar prêmio</button></div>
                </form>
                <?php if (!empty($premio['ganhador'])): ?><p><strong>👑 Ganhador definido:</strong> <?php echo admin_esc((string)$premio['ganhador']); ?></p><?php endif; ?>
                <?php foreach ($rankMes as $k => $r): ?><div class="dz-r"><strong><?php echo $k + 1; ?>º</strong><span><?php echo admin_esc((string)$r['nome']); ?></span><strong><?php echo (int)$r['acertos']; ?> acertos</strong><span><?php echo (int)$r['dias']; ?> dia(s)</span></div><?php endforeach; ?>
                <?php if ($rankMes): ?><form method="post" style="margin-top:12px" onsubmit="return confirm('Definir o 1º colocado como ganhador do mês?');"><input type="hidden" name="acao" value="ganhador"><button class="admin-btn admin-btn-light admin-btn-small" type="submit"><i data-lucide="crown"></i>Definir ganhador do mês (1º colocado)</button></form><?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script>
document.getElementById('copiar').addEventListener('click', function () {
    var t = document.getElementById('msgWhats'), aviso = document.getElementById('copiado');
    var ok = function () { aviso.textContent = 'Copiado! Agora é só colar no grupo.'; };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t.value).then(ok).catch(function () { t.select(); document.execCommand('copy'); ok(); });
    } else { t.select(); document.execCommand('copy'); ok(); }
});
</script>
<?php admin_cursos_scripts(); ?>
</body>
</html>
