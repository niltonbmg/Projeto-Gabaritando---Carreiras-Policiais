<?php
// recompensas.php
// Página do aluno com XP, ranking e estrutura de gamificação conectada ao banco.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authFile = __DIR__ . '/api/authenticador.php';
if (is_file($authFile)) {
    require_once $authFile;
}
if (function_exists('auth_require_login')) {
    auth_require_login();
}

$libRecompensas = __DIR__ . '/includes/recompensas-lib.php';
if (is_file($libRecompensas)) {
    require_once $libRecompensas;
}

if (!function_exists('esc')) {
    function esc(mixed $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('recompensas_usuario_atual_id')) {
    function recompensas_usuario_atual_id(): int
    {
        if (function_exists('auth_user_id')) {
            $id = (int)auth_user_id();
            if ($id > 0) {
                return $id;
            }
        }

        if (!empty($_SESSION['usuario']) && is_array($_SESSION['usuario']) && !empty($_SESSION['usuario']['id'])) {
            return (int)$_SESSION['usuario']['id'];
        }

        foreach (['usuario_id', 'id_usuario', 'user_id', 'cliente_id', 'aluno_id'] as $chave) {
            if (!empty($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        return 0;
    }
}

$usuarioId = recompensas_usuario_atual_id();
$periodo = (string)($_GET['periodo'] ?? 'sempre');
if (!in_array($periodo, ['sempre', 'mes', 'hoje'], true)) {
    $periodo = 'sempre';
}

$moedas = 0;
$strikes = 0;
$xpAcumulado = 0;
$xpDiario = 0;
$ranking = [];
$regras = [];
$erroRecompensas = '';

try {
    if (!function_exists('recompensas_db')) {
        throw new RuntimeException('Biblioteca de recompensas não encontrada.');
    }

    $pdo = recompensas_db();
    recompensas_bootstrap($pdo);
    recompensas_sincronizar_eventos($pdo, $usuarioId > 0 ? $usuarioId : null);

    $regras = recompensas_regras($pdo);
    $xpAcumulado = $usuarioId > 0 ? recompensas_xp_usuario($pdo, $usuarioId) : 0;
    $xpDiario = $usuarioId > 0 ? recompensas_xp_usuario($pdo, $usuarioId, 'hoje') : 0;
    $strikes = $usuarioId > 0 ? recompensas_streak_usuario($pdo, $usuarioId) : 0;
    $ranking = recompensas_ranking($pdo, $periodo, 10);
} catch (Throwable $e) {
    error_log('[Projeto Gabaritando] Erro recompensas.php: ' . $e->getMessage());
    $erroRecompensas = 'Não foi possível carregar as recompensas agora.';
}

$regrasAtivasComXp = array_values(array_filter($regras, static function (array $regra): bool {
    return (int)($regra['ativo'] ?? 0) === 1 && (int)($regra['xp'] ?? 0) > 0;
}));

$periodos = [
    'sempre' => 'Sempre',
    'mes' => 'Este Mês',
    'hoje' => 'Hoje',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recompensas | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{--amarelo:#ffb400;--amarelo-escuro:#d99600;--azul:#2f6dff;--azul-alerta:#3577ff;--fundo:#111214;--card:#292929;--card-escuro:#242424;--texto:#f5f5f5;--texto-fraco:#d8d8d8;--borda:#303030;--linha-gradiente:linear-gradient(90deg,#263146 0%,#326bff 100%)}
        *{margin:0;padding:0;box-sizing:border-box}html,body{width:100%;min-height:100%}body{min-height:100vh;background:var(--fundo);color:var(--texto);font-family:'Ubuntu',Arial,sans-serif;border-top:6px solid var(--amarelo)}a{text-decoration:none;color:inherit}button{border:0;font-family:inherit;cursor:pointer}.recompensas-page{width:100%;min-height:calc(100vh - 6px);padding:16px 20px 28px;background:var(--fundo)}.recompensas-shell{width:100%;max-width:1360px;margin:0 auto}.painel-resumo{width:100%;min-height:64px;background:var(--card);border-radius:8px;display:grid;grid-template-columns:1fr 1fr 1fr;align-items:center;gap:18px;padding:0 22px}.resumo-grupo{display:flex;align-items:center;gap:18px}.resumo-grupo--centro{justify-content:center}.resumo-grupo--direita{justify-content:flex-end}.resumo-item{display:inline-flex;align-items:center;gap:10px;color:#fff;font-size:1rem;font-weight:700;letter-spacing:.01em;white-space:nowrap}.resumo-item__icone{width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;color:var(--amarelo);flex:0 0 auto}.resumo-item__icone svg{width:21px;height:21px;stroke-width:2.6}.resumo-item__valor{color:#fff;font-weight:700}.resumo-item__xp{color:var(--azul);font-weight:700}.bloco{width:100%;background:var(--card);border-radius:8px;margin-top:20px;padding:20px}.bloco--progresso{min-height:109px}.bloco--loja{min-height:170px}.bloco__titulo{color:#fff;font-size:1.23rem;line-height:1.2;font-weight:700;text-transform:uppercase;letter-spacing:.01em;margin-bottom:10px}.alerta-gamificacao{width:100%;min-height:38px;border:1px solid rgba(47,109,255,.28);background:rgba(47,109,255,.13);border-radius:4px;display:flex;align-items:center;gap:9px;color:var(--azul-alerta);font-size:.98rem;font-weight:500;padding:8px 12px}.alerta-gamificacao.is-error{color:#ffb4ab;background:rgba(255,84,73,.12);border-color:rgba(255,84,73,.28)}.alerta-gamificacao svg{width:18px;height:18px;flex:0 0 auto;stroke-width:2.3}.regras-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:14px}.regra-card{background:#202020;border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:14px;display:flex;gap:12px;align-items:flex-start}.regra-icon{width:36px;height:36px;border-radius:10px;background:rgba(255,180,0,.12);color:var(--amarelo);display:flex;align-items:center;justify-content:center;flex:0 0 auto}.regra-icon svg{width:20px;height:20px}.regra-card strong{display:block;color:#fff;font-size:.92rem;margin-bottom:5px}.regra-card span{display:block;color:#c9c9c9;font-size:.84rem;line-height:1.35}.regra-card b{color:#2f6dff}.ranking-card{width:100%;margin-top:18px;background:var(--card);border-radius:4px 4px 0 0;padding:18px 20px 10px;overflow:hidden}.ranking-topo{width:100%;display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:12px}.ranking-titulo{color:#fff;font-size:1.16rem;font-weight:700;text-transform:uppercase;letter-spacing:.01em}.ranking-abas{width:268px;height:24px;display:grid;grid-template-columns:1fr 1fr 1fr;background:#d9d9d9;border-radius:999px;overflow:hidden;flex:0 0 auto}.ranking-aba{height:24px;display:flex;align-items:center;justify-content:center;background:transparent;color:#fff;font-size:.8rem;font-weight:700;line-height:1}.ranking-aba.is-active{background:var(--amarelo)}.ranking-lista{width:100%;display:grid;gap:10px}.ranking-linha{width:100%;min-height:44px;background:var(--linha-gradiente);display:grid;grid-template-columns:45px minmax(0,1fr) auto;align-items:center;gap:12px;padding:0 13px 0 10px}.ranking-pos{width:31px;height:31px;border-radius:999px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.82rem;font-weight:700}.ranking-nome{color:#fff;font-size:.88rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ranking-pontos{display:inline-flex;align-items:center;gap:14px;color:#fff;font-size:.82rem;font-weight:700;white-space:nowrap}.ranking-pontos svg{width:20px;height:20px;color:var(--amarelo);fill:var(--amarelo);stroke:var(--amarelo);stroke-width:1.7}.loja-vazia{width:100%;min-height:110px;display:flex;align-items:center;justify-content:center;text-align:center;color:#9a9a9a;font-size:.96rem;font-weight:500}.ranking-vazio{padding:22px;text-align:center;color:#bbb;background:#202020;border-radius:8px;font-weight:700}@media(max-width:900px){.recompensas-page{padding:14px 12px 24px}.painel-resumo{grid-template-columns:1fr;min-height:0;gap:10px;padding:16px}.resumo-grupo,.resumo-grupo--centro,.resumo-grupo--direita{justify-content:flex-start;flex-wrap:wrap}.bloco{padding:16px}.ranking-card{padding:16px 12px 10px}.ranking-topo{align-items:flex-start;flex-direction:column}.ranking-abas{width:100%;max-width:320px}.regras-grid{grid-template-columns:1fr}}@media(max-width:560px){.recompensas-page{padding:12px 8px 20px}.resumo-item{font-size:.92rem}.bloco__titulo,.ranking-titulo{font-size:1.04rem}.alerta-gamificacao{align-items:flex-start;font-size:.9rem;line-height:1.35}.ranking-linha{min-height:48px;grid-template-columns:36px minmax(0,1fr) auto;padding:0 10px;gap:8px}.ranking-nome{font-size:.82rem}.ranking-pontos{gap:8px;font-size:.78rem}}
    </style>
</head>
<body>
    <?php
    $barraSuperiorPath = __DIR__ . '/includes/barra-superior.php';
    if (is_file($barraSuperiorPath)) {
        include_once $barraSuperiorPath;
    } elseif (is_file(__DIR__ . '/barra-superior.php')) {
        include_once __DIR__ . '/barra-superior.php';
    }
    ?>

    <main class="recompensas-page">
        <div class="recompensas-shell">
            <section class="painel-resumo" aria-label="Resumo de recompensas">
                <div class="resumo-grupo">
                    <div class="resumo-item"><span class="resumo-item__icone" aria-hidden="true"><i data-lucide="coins"></i></span><span><span class="resumo-item__valor"><?php echo esc($moedas); ?></span> MOEDAS</span></div>
                    <div class="resumo-item"><span class="resumo-item__icone" aria-hidden="true"><i data-lucide="flame"></i></span><span><span class="resumo-item__valor"><?php echo esc($strikes); ?></span> STRIKES</span></div>
                </div>
                <div class="resumo-grupo resumo-grupo--centro"><div class="resumo-item"><span class="resumo-item__icone" aria-hidden="true"><i data-lucide="sparkles"></i></span><span>XP ACUMULADO&nbsp; <span class="resumo-item__xp"><?php echo esc($xpAcumulado); ?> XP</span></span></div></div>
                <div class="resumo-grupo resumo-grupo--direita"><div class="resumo-item"><span class="resumo-item__icone" aria-hidden="true"><i data-lucide="sparkles"></i></span><span>XP DIÁRIO&nbsp; <span class="resumo-item__xp"><?php echo esc($xpDiario); ?> XP</span></span></div></div>
            </section>

            <section class="bloco bloco--progresso" aria-label="Progresso">
                <h1 class="bloco__titulo">Progresso</h1>
                <?php if ($erroRecompensas !== ''): ?>
                    <div class="alerta-gamificacao is-error" role="status"><i data-lucide="alert-circle" aria-hidden="true"></i><span><?php echo esc($erroRecompensas); ?></span></div>
                <?php elseif (!$regrasAtivasComXp): ?>
                    <div class="alerta-gamificacao" role="status"><i data-lucide="info" aria-hidden="true"></i><span>Nenhuma regra de XP foi ativada pelo administrador. O ranking já está conectado ao banco, mas começa zerado.</span></div>
                <?php else: ?>
                    <div class="alerta-gamificacao" role="status"><i data-lucide="zap" aria-hidden="true"></i><span>Ganhe XP concluindo metas, respondendo questões e fazendo simulados. Só a primeira resposta/tentativa contabiliza.</span></div>
                    <div class="regras-grid">
                        <?php foreach ($regrasAtivasComXp as $regra): ?>
                            <article class="regra-card">
                                <span class="regra-icon"><i data-lucide="badge-check"></i></span>
                                <span><strong><?php echo esc($regra['titulo']); ?></strong><span><b><?php echo (int)$regra['xp']; ?> XP</b> — <?php echo esc($regra['descricao']); ?></span></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="bloco bloco--loja" aria-label="Loja">
                <h2 class="bloco__titulo">Loja</h2>
                <div class="loja-vazia" aria-live="polite">As recompensas da loja serão configuradas no admin.</div>
            </section>

            <section class="ranking-card" aria-label="Ranking dos alunos">
                <div class="ranking-topo">
                    <h2 class="ranking-titulo">Ranking (Top 10)</h2>
                    <div class="ranking-abas" role="tablist" aria-label="Período do ranking">
                        <?php foreach ($periodos as $key => $label): ?>
                            <a class="ranking-aba<?php echo $periodo === $key ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $periodo === $key ? 'true' : 'false'; ?>" href="recompensas.php?periodo=<?php echo esc($key); ?>"><?php echo esc($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ranking-lista">
                    <?php if (!$ranking): ?>
                        <div class="ranking-vazio">Nenhum aluno encontrado no banco.</div>
                    <?php else: ?>
                        <?php foreach ($ranking as $index => $aluno): ?>
                            <article class="ranking-linha">
                                <span class="ranking-pos"><?php echo $index + 1; ?>º</span>
                                <strong class="ranking-nome"><?php echo esc($aluno['nome'] ?: $aluno['email']); ?></strong>
                                <span class="ranking-pontos"><span><?php echo (int)$aluno['xp']; ?> XP</span><i data-lucide="star" aria-hidden="true"></i></span>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
