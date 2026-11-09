<?php
// desafio.php
// Desafio de Questões do Dia: o aluno resolve as 20 questões (uma tentativa,
// cronometrada), vê o gabarito comentado, o ranking do dia, do mês e o prêmio.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/desafio-lib.php';
require_once __DIR__ . '/includes/meta-lib.php';

date_default_timezone_set('America/Bahia');
$usuarioId = auth_user_id();
$hoje = date('Y-m-d');
$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['dia'] ?? '')) ? (string)$_GET['dia'] : $hoje;
if ($dia > $hoje) {
    $dia = $hoje;
}
$erro = '';
$desafio = null;
$tentativa = null;
$questoes = [];

try {
    $pdo = db();
    desafio_bootstrap($pdo);
    if ($hoje < DESAFIO_INICIO) {
        $desafio = null;
    } elseif ($dia === $hoje) {
        $desafio = desafio_gerar($pdo, $hoje);
    } else {
        $st = $pdo->prepare('SELECT * FROM desafios WHERE data = :d');
        $st->execute([':d' => $dia]);
        $desafio = $st->fetch() ?: null;
    }
    if ($desafio) {
        $questoes = desafio_questoes($pdo, $desafio);
        $st = $pdo->prepare('SELECT * FROM desafio_tentativas WHERE desafio_id = :d AND usuario_id = :u');
        $st->execute([':d' => (int)$desafio['id'], ':u' => $usuarioId]);
        $tentativa = $st->fetch() ?: null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dia === $hoje && empty($tentativa['finalizado_em'])) {
            $tentativa = desafio_finalizar($pdo, $desafio, $usuarioId, array_map('intval', (array)($_POST['q'] ?? [])));
            header('Location: desafio.php?dia=' . $dia . '#resultado');
            exit;
        }
        // Começa a contar o tempo quando o aluno abre o desafio de hoje.
        if ($dia === $hoje && !$tentativa) {
            $tentativa = desafio_iniciar($pdo, (int)$desafio['id'], $usuarioId);
        }
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

function dz_e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function dz_tempo(?int $s): string
{
    $s = (int)$s;
    return $s >= 3600 ? sprintf('%dh%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

$finalizado = $tentativa && !empty($tentativa['finalizado_em']);
$podeResponder = $desafio && $dia === $hoje && !$finalizado;
$respostas = $finalizado ? (array)json_decode((string)$tentativa['respostas'], true) : [];
$rankDia = $desafio && isset($pdo) ? desafio_ranking_dia($pdo, (int)$desafio['id'], 30) : [];
$mes = substr($dia, 0, 7);
$rankMes = isset($pdo) ? desafio_ranking_mes($pdo, $mes, 10) : [];
$premio = isset($pdo) ? desafio_premio($pdo, $mes) : [];
$mesAnterior = (new DateTimeImmutable($mes . '-01'))->modify('-1 month')->format('Y-m');
$premioAnterior = isset($pdo) ? desafio_premio($pdo, $mesAnterior) : [];
$minhaPosicao = null;
foreach ($rankDia as $k => $r) {
    if ((int)$r['usuario_id'] === $usuarioId) {
        $minhaPosicao = $k + 1;
    }
}
// Ranking Individual: matérias do aluno (forte -> fraca) e histórico no desafio.
$individual = [];
$historico = [];
if (isset($pdo)) {
    try {
        $individual = meta_desempenho_materias($pdo, $usuarioId);
        $st = $pdo->prepare('SELECT d.data, t.acertos, t.total, t.tempo_seg,
                                    (SELECT COUNT(*) + 1 FROM desafio_tentativas o WHERE o.desafio_id = t.desafio_id AND o.finalizado_em IS NOT NULL
                                      AND (o.acertos > t.acertos OR (o.acertos = t.acertos AND o.tempo_seg < t.tempo_seg))) AS posicao
                               FROM desafio_tentativas t JOIN desafios d ON d.id = t.desafio_id
                              WHERE t.usuario_id = :u AND t.finalizado_em IS NOT NULL ORDER BY d.data DESC LIMIT 10');
        $st->execute([':u' => $usuarioId]);
        $historico = $st->fetchAll();
    } catch (Throwable $e) {
    }
}
$meses = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desafio do Dia e Ranking Geral | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a;--erro:#ff6b5b}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .dz{max-width:860px;margin:0 auto;padding:24px max(16px,env(safe-area-inset-left)) 80px max(16px,env(safe-area-inset-right));display:grid;gap:18px}
        .dz-topo{background:linear-gradient(140deg,#2b2108,#161618 60%);border:1px solid #4a3a10;border-radius:18px;padding:20px;display:grid;gap:6px}
        .dz-topo small{color:var(--ouro);font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:12px}
        .dz-topo h1{font-size:clamp(22px,4vw,30px)}
        .dz-topo p{color:var(--suave);font-size:14px}
        .dz-alerta{padding:12px 14px;border-radius:12px;background:#3a1512;color:var(--erro)}
        .dz-q{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:16px;display:grid;gap:10px}
        .dz-q header{display:flex;justify-content:space-between;gap:8px;font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.05em}
        .dz-enun{font-size:16px;line-height:1.6;max-width:72ch;white-space:pre-line}
        .dz-op{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid var(--linha);border-radius:12px;cursor:pointer;background:var(--card2)}
        .dz-op input{margin-top:4px;accent-color:#fbb928;width:18px;height:18px;flex:0 0 auto}
        .dz-op:has(input:checked){border-color:var(--ouro)}
        .dz-op.certa{border-color:var(--ok);background:#0f2a1a}
        .dz-op.errada{border-color:var(--erro);background:#3a1512}
        .dz-exp{font-size:14px;color:#d4d4d8;background:#111113;border-left:3px solid var(--ouro);padding:10px 12px;border-radius:0 10px 10px 0;white-space:pre-line}
        .dz-btn{display:inline-flex;justify-content:center;align-items:center;gap:8px;border:0;border-radius:12px;padding:14px 18px;background:var(--ouro);color:#111;font:inherit;font-weight:700;font-size:16px;cursor:pointer;text-decoration:none}
        .dz-btn:focus-visible,.dz-op:focus-within{outline:2px solid var(--ouro);outline-offset:2px}
        .dz-res{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .dz-res div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:14px;text-align:center}
        .dz-res strong{display:block;font-size:26px;font-variant-numeric:tabular-nums}
        .dz-res span{font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .dz-rank{background:var(--card);border:1px solid var(--linha);border-radius:16px;overflow:hidden}
        .dz-rank h2{font-size:16px;padding:14px 16px;border-bottom:1px solid var(--linha)}
        .dz-linha{display:grid;grid-template-columns:40px 1fr auto auto;gap:10px;padding:10px 16px;border-bottom:1px solid var(--linha);font-variant-numeric:tabular-nums;align-items:center}
        .dz-linha:last-child{border-bottom:0}
        .dz-linha.eu{background:#2b2108}
        .dz-linha span{color:var(--suave);font-size:13px}
        .dz-premio{background:linear-gradient(140deg,#1d2a12,#161618 60%);border:1px solid #2f5f2b;border-radius:16px;padding:16px;display:grid;gap:4px}
        .dz-premio strong{color:var(--ok)}
        .dz-vazio{color:var(--suave);padding:16px;text-align:center}
        @media(max-width:560px){.dz-res strong{font-size:20px}}
    </style>
</head>
<body>
<?php $b = __DIR__ . '/includes/barra-superior.php'; if (is_file($b)) { include_once $b; } ?>
<main class="dz">
    <section class="dz-topo">
        <small>Desafio do dia · <?php echo dz_e((new DateTimeImmutable($dia))->format('d/m/Y')); ?></small>
        <h1>🎯 20 questões. Uma tentativa. Ranking geral.</h1>
        <p><?php echo $desafio && $desafio['chamada'] ? dz_e((string)$desafio['chamada']) : 'Fala, combatente! Resolva com atenção: o tempo conta no desempate.'; ?></p>
    </section>

    <?php if ($erro !== ''): ?><div class="dz-alerta" role="alert"><?php echo dz_e($erro); ?></div><?php endif; ?>
    <?php if ($hoje < DESAFIO_INICIO): ?><div class="dz-vazio">🎯 O Desafio do Dia começa em <strong><?php echo date('d/m', strtotime(DESAFIO_INICIO)); ?></strong>! 20 questões por dia, Ranking Geral e prêmio para o campeão do mês. Prepare-se, combatente!</div>
    <?php elseif (!$desafio && $erro === ''): ?><div class="dz-vazio">Não houve desafio neste dia.</div><?php endif; ?>

    <?php if ($podeResponder): ?>
        <form method="post" id="formDesafio">
            <div style="display:grid;gap:14px">
            <?php foreach ($questoes as $n => $q): ?>
                <article class="dz-q" aria-labelledby="enun-<?php echo (int)$q['id']; ?>">
                    <header><span>Questão <?php echo $n + 1; ?> de <?php echo count($questoes); ?></span><span><?php echo dz_e((string)($q['materia'] ?? '')); ?></span></header>
                    <div class="dz-enun" id="enun-<?php echo (int)$q['id']; ?>"><?php echo dz_e(trim(html_entity_decode(strip_tags((string)$q['enunciado']), ENT_QUOTES, 'UTF-8'))); ?></div>
                    <?php foreach ($q['opcoes'] as $o): ?>
                        <label class="dz-op"><input type="radio" name="q[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)$o['id']; ?>"><span><strong><?php echo dz_e((string)$o['letra']); ?>)</strong> <?php echo dz_e(trim(html_entity_decode(strip_tags((string)$o['texto']), ENT_QUOTES, 'UTF-8'))); ?></span></label>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
            </div>
            <p style="color:var(--suave);font-size:13px;margin:14px 0">Questões em branco contam como erro. Depois de enviar, não dá para refazer.</p>
            <button class="dz-btn" type="submit" style="width:100%">Enviar respostas</button>
        </form>
        <script>
        document.getElementById('formDesafio').addEventListener('submit', function (e) {
            var total = <?php echo count($questoes); ?>, marcadas = this.querySelectorAll('input[type=radio]:checked').length;
            if (marcadas < total && !this.dataset.ok) {
                e.preventDefault();
                var aviso = document.getElementById('avisoBranco');
                if (!aviso) { aviso = document.createElement('p'); aviso.id = 'avisoBranco'; aviso.className = 'dz-alerta'; aviso.setAttribute('role', 'alert'); this.insertBefore(aviso, this.lastElementChild); }
                aviso.textContent = 'Faltam ' + (total - marcadas) + ' questão(ões). Toque em "Enviar respostas" de novo para enviar assim mesmo.';
                this.dataset.ok = '1';
            }
        });
        </script>
    <?php elseif ($desafio): ?>
        <?php if ($finalizado): ?>
            <section class="dz-res" id="resultado" aria-label="Seu resultado">
                <div><span>Acertos</span><strong><?php echo (int)$tentativa['acertos']; ?>/<?php echo (int)$tentativa['total']; ?></strong></div>
                <div><span>Tempo</span><strong><?php echo dz_tempo((int)$tentativa['tempo_seg']); ?></strong></div>
                <div><span>Posição hoje</span><strong><?php echo $minhaPosicao ? $minhaPosicao . 'º' : '—'; ?></strong></div>
            </section>
        <?php elseif ($dia !== $hoje): ?>
            <div class="dz-vazio">Este desafio já encerrou. <a href="desafio.php" style="color:var(--ouro)">Ir para o desafio de hoje</a></div>
        <?php endif; ?>

        <section class="dz-rank" aria-label="Ranking do dia">
            <h2>🏆 Ranking Geral · hoje</h2>
            <?php if (!$rankDia): ?><div class="dz-vazio">Ninguém terminou ainda. Seja o primeiro!</div><?php endif; ?>
            <?php foreach ($rankDia as $k => $r): ?>
                <div class="dz-linha <?php echo (int)$r['usuario_id'] === $usuarioId ? 'eu' : ''; ?>"><strong><?php echo $k < 3 ? ['🥇', '🥈', '🥉'][$k] : ($k + 1) . 'º'; ?></strong><div><?php echo dz_e(desafio_nome_publico((string)$r['nome'])); ?></div><strong><?php echo (int)$r['acertos']; ?>/<?php echo (int)$r['total']; ?></strong><span><?php echo dz_tempo((int)$r['tempo_seg']); ?></span></div>
            <?php endforeach; ?>
        </section>

        <?php if ($finalizado): ?>
            <h2 style="font-size:18px">Gabarito comentado</h2>
            <?php foreach ($questoes as $n => $q): $minha = (int)($respostas[(int)$q['id']] ?? 0); ?>
                <article class="dz-q">
                    <header><span>Questão <?php echo $n + 1; ?> · <?php echo dz_e((string)($q['materia'] ?? '')); ?></span><span><?php echo $minha === 0 ? 'Em branco' : ''; ?></span></header>
                    <div class="dz-enun"><?php echo dz_e(trim(html_entity_decode(strip_tags((string)$q['enunciado']), ENT_QUOTES, 'UTF-8'))); ?></div>
                    <?php foreach ($q['opcoes'] as $o): $classe = (int)$o['correta'] ? 'certa' : ((int)$o['id'] === $minha ? 'errada' : ''); ?>
                        <div class="dz-op <?php echo $classe; ?>"><span><?php echo (int)$o['correta'] ? '✅' : ((int)$o['id'] === $minha ? '❌' : '•'); ?></span><span><strong><?php echo dz_e((string)$o['letra']); ?>)</strong> <?php echo dz_e(trim(html_entity_decode(strip_tags((string)$o['texto']), ENT_QUOTES, 'UTF-8'))); ?></span></div>
                    <?php endforeach; ?>
                    <?php if (trim(strip_tags((string)$q['explicacao'])) !== ''): ?><div class="dz-exp">💡 <?php echo dz_e(trim(html_entity_decode(strip_tags((string)$q['explicacao']), ENT_QUOTES, 'UTF-8'))); ?></div><?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <section class="dz-rank" aria-label="Meu Ranking Individual">
        <h2>📊 Meu Ranking Individual · suas matérias</h2>
        <?php if (!$individual): ?><div class="dz-vazio">Resolva questões (no desafio ou no seu cronograma) para ver suas matérias mais fortes e mais fracas.</div><?php endif; ?>
        <?php foreach (array_values($individual) as $k => $m): ?>
            <div class="dz-linha"><strong><?php echo $k + 1; ?>º</strong><div><?php echo dz_e($m['materia']); ?><br><span><?php echo dz_e($m['motivo']); ?></span></div><strong><?php echo (int)$m['pct']; ?>%</strong><span><?php echo (int)$m['total']; ?> questões</span></div>
        <?php endforeach; ?>
        <?php if ($individual): ?><div class="dz-vazio" style="text-align:left">💡 Seu cronograma usa este ranking: as matérias em que você erra mais ganham mais tempo, e as que você domina ficam em manutenção.</div><?php endif; ?>
        <?php if ($historico): ?>
            <h2 style="border-top:1px solid var(--linha)">Sua evolução no desafio</h2>
            <?php foreach ($historico as $h): ?><div class="dz-linha"><strong><?php echo (int)$h['posicao']; ?>º</strong><div><?php echo dz_e((new DateTimeImmutable((string)$h['data']))->format('d/m')); ?></div><strong><?php echo (int)$h['acertos']; ?>/<?php echo (int)$h['total']; ?></strong><span><?php echo dz_tempo((int)$h['tempo_seg']); ?></span></div><?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="dz-premio" aria-label="Prêmio do mês">
        <span>🎁 Prêmio de <?php echo $meses[(int)substr($mes, 5, 2)]; ?></span>
        <strong><?php echo !empty($premio['premio']) ? dz_e((string)$premio['premio']) : 'Em breve o mentor anuncia o brinde do mês.'; ?></strong>
        <?php if (!empty($premioAnterior['ganhador'])): ?><span>👑 Ganhador de <?php echo $meses[(int)substr($mesAnterior, 5, 2)]; ?>: <?php echo dz_e(desafio_nome_publico((string)$premioAnterior['ganhador'])); ?></span><?php endif; ?>
    </section>

    <section class="dz-rank" aria-label="Ranking do mês">
        <h2>🏆 Ranking Geral · mês (soma dos acertos)</h2>
        <?php if (!$rankMes): ?><div class="dz-vazio">O ranking do mês aparece quando os primeiros desafios forem concluídos.</div><?php endif; ?>
        <?php foreach ($rankMes as $k => $r): ?>
            <div class="dz-linha <?php echo (int)$r['usuario_id'] === $usuarioId ? 'eu' : ''; ?>"><strong><?php echo $k < 3 ? ['🥇', '🥈', '🥉'][$k] : ($k + 1) . 'º'; ?></strong><div><?php echo dz_e(desafio_nome_publico((string)$r['nome'])); ?></div><strong><?php echo (int)$r['acertos']; ?> acertos</strong><span><?php echo (int)$r['dias']; ?> dia(s)</span></div>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
