<?php
// admin-recompensas.php
// Configuração de XP/recompensas do Projeto Gabaritando.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

$lib = __DIR__ . '/includes/recompensas-lib.php';
if (is_file($lib)) {
    require_once $lib;
}

if (!function_exists('admin_rec_esc')) {
    function admin_rec_esc(mixed $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_rec_redirect')) {
    function admin_rec_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

$erro = '';
$sucesso = '';
$regras = [];
$ranking = [];
$totais = ['usuarios' => 0, 'eventos' => 0, 'xp' => 0];

try {
    if (!function_exists('recompensas_db')) {
        throw new RuntimeException('Biblioteca de recompensas não encontrada.');
    }

    $pdo = recompensas_db();
    recompensas_bootstrap($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_regras') {
        $xp = $_POST['xp'] ?? [];
        $ativo = $_POST['ativo'] ?? [];

        if (!is_array($xp)) {
            $xp = [];
        }
        if (!is_array($ativo)) {
            $ativo = [];
        }

        $stmt = $pdo->prepare('UPDATE admin_recompensas_regras SET xp = :xp, ativo = :ativo, atualizado_em = NOW() WHERE codigo = :codigo LIMIT 1');
        foreach ($xp as $codigo => $valor) {
            $codigo = trim((string)$codigo);
            $pontos = max(0, (int)$valor);
            $stmt->execute([
                ':xp' => $pontos,
                ':ativo' => isset($ativo[$codigo]) ? 1 : 0,
                ':codigo' => $codigo,
            ]);
        }

        recompensas_sincronizar_eventos($pdo, null);
        admin_rec_redirect('admin-recompensas.php?sucesso=1');
    }

    if ((string)($_GET['sucesso'] ?? '') === '1') {
        $sucesso = 'Regras de XP atualizadas com sucesso.';
    }

    recompensas_sincronizar_eventos($pdo, null);
    $regras = array_values(recompensas_regras($pdo));
    $ranking = recompensas_ranking($pdo, 'sempre', 10);

    $totais['usuarios'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE COALESCE(status, 'ativo') = 'ativo'")->fetchColumn();
    $totais['eventos'] = (int)$pdo->query('SELECT COUNT(*) FROM usuario_xp_eventos')->fetchColumn();
    $totais['xp'] = (int)$pdo->query('SELECT COALESCE(SUM(xp), 0) FROM usuario_xp_eventos')->fetchColumn();
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$topbarInclude = __DIR__ . '/includes/admin-topbar.php';
$menuBarInclude = __DIR__ . '/includes/menu-bar.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recompensas | Admin Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--preto:#111;--amarelo:#fdbd08;--amarelo-escuro:#d89d00;--fundo:#f3f4f6;--card:#fff;--texto:#1f2937;--muted:#667085;--borda:#e4e7ec;--erro:#b42318;--sucesso:#047857}*{box-sizing:border-box}body{margin:0;background:var(--fundo);font-family:'Ubuntu',Arial,sans-serif;color:var(--texto)}button,input{font:inherit}.admin-dashboard-main{width:100%;padding:28px;min-width:0}.admin-page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.admin-page-heading h1{margin:0 0 6px;font-size:2rem;color:#111827}.admin-page-heading p{margin:0;color:var(--muted);font-weight:500}.admin-btn{min-height:42px;border:0;border-radius:10px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:9px;font-weight:700;text-decoration:none;cursor:pointer}.admin-btn svg{width:18px;height:18px}.admin-btn-primary{background:var(--amarelo);color:#111}.admin-alert{border-radius:12px;padding:13px 14px;margin:0 0 16px;display:flex;align-items:center;gap:10px;font-weight:700}.admin-alert svg{width:19px;height:19px}.admin-alert-error{background:#fff1f0;border:1px solid #fecdca;color:var(--erro)}.admin-alert-success{background:#ecfdf3;border:1px solid #abefc6;color:var(--sucesso)}.admin-stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:18px}.admin-stat-card{background:#fff;border:1px solid var(--borda);border-radius:16px;padding:18px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 12px 30px rgba(15,23,42,.04)}.admin-stat-card span{display:block;color:var(--muted);font-size:.88rem;font-weight:700;margin-bottom:8px}.admin-stat-card strong{font-size:2rem}.admin-stat-icon{width:46px;height:46px;border-radius:14px;background:#fff7db;color:var(--amarelo-escuro);display:flex;align-items:center;justify-content:center}.admin-stat-icon svg{width:23px;height:23px}.admin-panel{background:#fff;border:1px solid var(--borda);border-radius:16px;padding:20px;margin-bottom:18px;box-shadow:0 12px 30px rgba(15,23,42,.04)}.admin-panel-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.admin-panel-title h2{margin:0 0 5px;font-size:1.25rem}.admin-panel-title small{color:var(--muted);font-weight:600}.rules-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.rule-card{border:1px solid var(--borda);border-radius:14px;padding:16px;background:#fcfcfd;display:grid;grid-template-columns:1fr 150px;gap:14px;align-items:start}.rule-card h3{margin:0 0 6px;font-size:1rem}.rule-card p{margin:0;color:var(--muted);font-size:.88rem;line-height:1.45}.rule-inputs{display:grid;gap:10px}.field label{display:block;color:#344054;font-size:.82rem;font-weight:700;margin-bottom:6px}.field input[type=number]{width:100%;height:42px;border:1px solid #d0d5dd;border-radius:10px;padding:0 12px;outline:none}.check{display:flex;align-items:center;gap:8px;color:#344054;font-size:.88rem;font-weight:700}.check input{accent-color:var(--amarelo)}.ranking-list{display:grid;gap:10px}.ranking-row{min-height:44px;border-radius:10px;background:#f9fafb;border:1px solid var(--borda);display:grid;grid-template-columns:45px 1fr auto;align-items:center;gap:12px;padding:0 14px}.ranking-row strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ranking-row span{color:var(--muted);font-weight:700}.ranking-pos{width:30px;height:30px;border-radius:999px;background:#fff7db;color:#a15c00;display:flex;align-items:center;justify-content:center;font-weight:700}.admin-help{background:#111827;color:#fff;border-radius:16px;padding:18px;display:grid;gap:9px}.admin-help h2{margin:0;font-size:1.1rem}.admin-help p{margin:0;color:#d0d5dd;line-height:1.45}.admin-help b{color:var(--amarelo)}@media(max-width:1024px){.admin-dashboard-main{padding:20px}.admin-stats-grid,.rules-grid{grid-template-columns:1fr}.rule-card{grid-template-columns:1fr}.admin-page-header{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.admin-dashboard-main{padding:14px}.admin-page-heading h1{font-size:1.55rem}.admin-panel{padding:15px}.admin-stats-grid{gap:10px}.admin-stat-card strong{font-size:1.5rem}.ranking-row{grid-template-columns:36px minmax(0,1fr) auto;padding:0 10px}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Recompensas</h1>
                    <p>Defina quanto XP o aluno ganha ao concluir metas, responder questões e finalizar simulados.</p>
                </div>
                <button class="admin-btn admin-btn-primary" type="submit" form="formRegras"><i data-lucide="save" aria-hidden="true"></i>Salvar regras</button>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_rec_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_rec_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Usuários ativos</span><strong><?php echo (int)$totais['usuarios']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="users" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Eventos de XP</span><strong><?php echo (int)$totais['eventos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="sparkles" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>XP distribuído</span><strong><?php echo (int)$totais['xp']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="trophy" aria-hidden="true"></i></div></article>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><div><h2>Regras de XP</h2><small>Deixe 0 para começar tudo zerado. Quando definir pontos, o ranking atualiza automaticamente.</small></div></div>
                <form id="formRegras" method="post">
                    <input type="hidden" name="acao" value="salvar_regras">
                    <div class="rules-grid">
                        <?php foreach ($regras as $regra): ?>
                            <?php $codigo = (string)$regra['codigo']; ?>
                            <article class="rule-card">
                                <div>
                                    <h3><?php echo admin_rec_esc($regra['titulo']); ?></h3>
                                    <p><?php echo admin_rec_esc($regra['descricao']); ?></p>
                                </div>
                                <div class="rule-inputs">
                                    <div class="field">
                                        <label for="xp_<?php echo admin_rec_esc($codigo); ?>">XP</label>
                                        <input id="xp_<?php echo admin_rec_esc($codigo); ?>" type="number" min="0" step="1" name="xp[<?php echo admin_rec_esc($codigo); ?>]" value="<?php echo (int)$regra['xp']; ?>">
                                    </div>
                                    <label class="check"><input type="checkbox" name="ativo[<?php echo admin_rec_esc($codigo); ?>]" value="1" <?php echo (int)$regra['ativo'] === 1 ? 'checked' : ''; ?>> Ativo</label>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </form>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><div><h2>Ranking atual</h2><small>Top 10 por XP acumulado.</small></div></div>
                <div class="ranking-list">
                    <?php if (!$ranking): ?>
                        <div class="admin-alert admin-alert-error"><i data-lucide="info" aria-hidden="true"></i>Nenhum usuário encontrado.</div>
                    <?php else: ?>
                        <?php foreach ($ranking as $i => $aluno): ?>
                            <article class="ranking-row">
                                <div class="ranking-pos"><?php echo $i + 1; ?>º</div>
                                <strong><?php echo admin_rec_esc($aluno['nome'] ?: $aluno['email']); ?></strong>
                                <span><?php echo (int)$aluno['xp']; ?> XP</span>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-help">
                <h2>Como está funcionando</h2>
                <p><b>Meta diária:</b> quando o aluno conclui uma meta do cronograma, ganha o XP definido aqui.</p>
                <p><b>Banco de questões:</b> só a primeira resposta daquela questão contabiliza. Se ele corrigir depois, não ganha de novo.</p>
                <p><b>Simulado:</b> só o primeiro envio daquele simulado contabiliza. Refazer ou corrigir não duplica XP.</p>
            </section>
        </main>
    </div>
    <script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
