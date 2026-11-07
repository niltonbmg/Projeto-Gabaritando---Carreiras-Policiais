<?php
// meu-cronograma.php
// Cronograma individual do aluno (Método META): gerado a partir do Plano-Mestre,
// da disponibilidade do aluno e do que ele já cumpriu. Reorganiza sozinho.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/meta-cronograma-lib.php';

$usuarioId = auth_user_id();
$erro = '';
$aviso = '';
$cron = null;
$cursoId = 0;
$editar = isset($_GET['editar']);

try {
    $pdo = db();
    meta_cronograma_bootstrap($pdo);
    $cursoId = meta_curso_do_aluno($pdo, $usuarioId, (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_config' && $cursoId > 0) {
        meta_cronograma_salvar_config($pdo, $usuarioId, $cursoId, $_POST);
        header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=1');
        exit;
    }
    if ($cursoId > 0) {
        $cron = meta_cronograma_montar($pdo, $usuarioId, $cursoId);
    }
    if (isset($_GET['ok'])) {
        $aviso = 'Cronograma montado. Ele se reorganiza sozinho conforme você estuda.';
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

function mc_e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function mc_min(int $m): string
{
    $h = intdiv($m, 60);
    $r = $m % 60;
    return $h > 0 ? ($h . 'h' . ($r > 0 ? sprintf('%02d', $r) : '')) : ($r . ' min');
}

$nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$rotulos = ['teoria' => 'Teoria mínima', 'questoes' => 'Questões', 'revisao' => 'Revisão'];
$cfg = $cron['config'] ?? null;
$temPlano = $cron && $cron['estado']['plano'];
$mostrarForm = $temPlano && (!$cfg || $editar);
$hoje = date('Y-m-d');
$diasMostrar = [];
if ($temPlano && $cfg && !$editar) {
    $diasMostrar = array_slice($cron['agenda'], 0, 14, true);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu cronograma | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --fundo:#0c0c0d; --card:#161618; --card2:#1e1e21; --linha:#2a2a2e;
            --texto:#f3f3f4; --suave:#a3a3ab; --ouro:#fbb928; --ouro-escuro:#2e2408;
            --ok:#3ccf7a; --alerta:#ff6b5b; --revisao:#7aa7ff;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.45}
        .mc{max-width:880px;margin:0 auto;padding:24px max(16px,env(safe-area-inset-left)) 64px max(16px,env(safe-area-inset-right));display:grid;gap:18px}
        .mc h1{font-size:clamp(22px,4vw,30px)}
        .mc-sub{color:var(--suave);font-size:14px}
        .mc-topo{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:12px}
        .mc-alerta{padding:12px 14px;border-radius:12px;background:var(--ouro-escuro);color:var(--ouro);font-size:14px}
        .mc-alerta.erro{background:#3a1512;color:var(--alerta)}
        .mc-alerta.ok{background:#0f2a1a;color:var(--ok)}
        .mc-ritmo{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        .mc-ritmo div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:14px;min-width:0}
        .mc-ritmo span{display:block;font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .mc-ritmo strong{display:block;font-size:20px;margin-top:4px;font-variant-numeric:tabular-nums}
        .mc-ritmo .ruim strong{color:var(--alerta)}
        .mc-ritmo .bom strong{color:var(--ok)}
        .mc-dias{display:grid;gap:12px}
        .mc-dia{background:var(--card);border:1px solid var(--linha);border-radius:16px;overflow:hidden}
        .mc-dia.hoje{border-color:var(--ouro)}
        .mc-dia header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--linha)}
        .mc-dia header strong{font-size:15px}
        .mc-dia header span{font-size:13px;color:var(--suave);font-variant-numeric:tabular-nums}
        .mc-dia.hoje header strong{color:var(--ouro)}
        .mc-tarefa{display:grid;grid-template-columns:6px 1fr auto;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid var(--linha)}
        .mc-tarefa:last-child{border-bottom:0}
        .mc-tarefa i{align-self:stretch;border-radius:4px;background:var(--ouro)}
        .mc-tarefa.questoes i{background:var(--ok)}
        .mc-tarefa.revisao i{background:var(--revisao)}
        .mc-tarefa .t{min-width:0}
        .mc-tarefa .t small{display:block;font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.05em}
        .mc-tarefa .t strong{display:block;font-size:14px;overflow-wrap:anywhere}
        .mc-tarefa .m{font-size:13px;color:var(--suave);font-variant-numeric:tabular-nums;white-space:nowrap}
        .btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
        .btn-ouro{background:var(--ouro);color:#111}
        .btn-linha{background:transparent;color:var(--texto);border:1px solid var(--linha)}
        .btn:focus-visible,input:focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
        .mc-form{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:20px;display:grid;gap:16px}
        .mc-form h2{font-size:18px}
        .mc-campos{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
        .mc-campo{display:grid;gap:6px;min-width:0}
        .mc-campo label,.mc-campo legend{font-size:13px;color:var(--suave)}
        .mc-campo input{background:var(--card2);border:1px solid var(--linha);border-radius:10px;color:var(--texto);padding:10px 12px;font:inherit;width:100%;color-scheme:dark}
        .mc-semana{display:flex;flex-wrap:wrap;gap:8px}
        fieldset.mc-campo{border:0;padding:0;margin:0}
        .mc-semana label{display:inline-flex;align-items:center;gap:6px;background:var(--card2);border:1px solid var(--linha);border-radius:999px;padding:8px 12px;font-size:14px;cursor:pointer;color:var(--texto)}
        .mc-semana input{accent-color:#fbb928}
        .mc-legenda{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;color:var(--suave)}
        .mc-legenda span::before{content:"";display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:6px;vertical-align:-1px;background:var(--c)}
        .mc-vazio{background:var(--card);border:1px dashed var(--linha);border-radius:16px;padding:24px;text-align:center;color:var(--suave)}
        @media(max-width:620px){.mc-ritmo{grid-template-columns:1fr 1fr 1fr}.mc-ritmo strong{font-size:16px}.mc-campos{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php
$barraSuperiorPath = __DIR__ . '/includes/barra-superior.php';
if (is_file($barraSuperiorPath)) {
    include_once $barraSuperiorPath;
}
?>
<main class="mc">
    <div class="mc-topo">
        <div>
            <h1>Meu cronograma</h1>
            <p class="mc-sub">Montado para você pelo Método META. Se atrasar, ele reorganiza sozinho a partir de hoje.</p>
        </div>
        <?php if ($temPlano && $cfg && !$editar): ?><a class="btn btn-linha" href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&editar=1">Ajustar disponibilidade</a><?php endif; ?>
    </div>

    <?php if ($erro !== ''): ?><div class="mc-alerta erro" role="alert"><?php echo mc_e($erro); ?></div><?php endif; ?>
    <?php if ($aviso !== ''): ?><div class="mc-alerta ok" role="status"><?php echo mc_e($aviso); ?></div><?php endif; ?>

    <?php if (!$temPlano): ?>
        <div class="mc-vazio">Seu plano de estudos ainda está sendo preparado pelo mentor. Quando ele aprovar, você monta seu cronograma aqui.</div>
    <?php elseif ($mostrarForm): ?>
        <form class="mc-form" method="post">
            <h2><?php echo $cfg ? 'Ajustar disponibilidade' : 'Vamos montar seu cronograma'; ?></h2>
            <p class="mc-sub">Seja realista. Um cronograma cumprido vale mais que um cronograma bonito.</p>
            <input type="hidden" name="acao" value="salvar_config"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <div class="mc-campos">
                <div class="mc-campo"><label for="horas">Horas por dia</label><input id="horas" name="horas_dia" type="number" min="0.5" max="16" step="0.5" required value="<?php echo mc_e((string)($cfg['horas_dia'] ?? '3')); ?>"></div>
                <div class="mc-campo"><label for="inicio">Começo em</label><input id="inicio" name="data_inicio" type="date" required value="<?php echo mc_e((string)($cfg['data_inicio'] ?? $hoje)); ?>"></div>
                <div class="mc-campo"><label for="prova">Data da prova (se souber)</label><input id="prova" name="data_prova" type="date" value="<?php echo mc_e((string)($cfg['data_prova'] ?? '')); ?>"></div>
            </div>
            <fieldset class="mc-campo">
                <legend>Dias em que você estuda</legend>
                <div class="mc-semana">
                    <?php $dSel = $cfg['dias'] ?? [1, 2, 3, 4, 5, 6]; foreach ([1, 2, 3, 4, 5, 6, 0] as $d): ?>
                        <label><input type="checkbox" name="dias[]" value="<?php echo $d; ?>" <?php echo in_array($d, $dSel, true) ? 'checked' : ''; ?>><?php echo $nomesDias[$d]; ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div><button class="btn btn-ouro" type="submit">Montar meu cronograma</button></div>
        </form>
    <?php else: ?>
        <?php
        $folga = $cron['folga_dias'];
        $atraso = (int)$cron['atraso_dias'];
        $fim = $cron['fim'] ? new DateTimeImmutable($cron['fim']) : null;
        ?>
        <section class="mc-ritmo" aria-label="Seu ritmo">
            <div><span>Termina o plano</span><strong><?php echo $fim ? (int)$fim->format('d') . ' ' . $meses[(int)$fim->format('n')] : '—'; ?></strong></div>
            <div class="<?php echo $atraso > 0 ? 'ruim' : 'bom'; ?>"><span>Ritmo</span><strong><?php echo $atraso > 0 ? $atraso . ' dia(s) atrás' : 'Em dia'; ?></strong></div>
            <div class="<?php echo $folga === null ? '' : ($folga < 0 ? 'ruim' : 'bom'); ?>"><span>Até a prova</span><strong><?php echo $folga === null ? 'Sem data' : ($folga < 0 ? 'Faltam ' . abs($folga) . ' dias' : $folga . ' dias de folga'); ?></strong></div>
        </section>

        <?php if ($folga !== null && $folga < 0): ?>
            <div class="mc-alerta erro" role="alert">No ritmo atual você não termina o plano antes da prova. Aumente as horas por dia ou fale com o mentor no próximo check-in.</div>
        <?php endif; ?>

        <div class="mc-legenda"><span style="--c:var(--ouro)">Teoria mínima</span><span style="--c:var(--ok)">Questões</span><span style="--c:var(--revisao)">Revisão</span></div>

        <?php if (!$diasMostrar): ?>
            <div class="mc-vazio">Você concluiu todos os assuntos do plano. Agora é subir o nível de cada um até a meta.</div>
        <?php endif; ?>
        <section class="mc-dias" aria-label="Próximos dias">
            <?php foreach ($diasMostrar as $data => $tarefas): ?>
                <?php $dt = new DateTimeImmutable($data); $totalMin = array_sum(array_column($tarefas, 'minutos')); ?>
                <article class="mc-dia <?php echo $data === $hoje ? 'hoje' : ''; ?>">
                    <header>
                        <strong><?php echo $data === $hoje ? 'Hoje' : $nomesDias[(int)$dt->format('w')]; ?>, <?php echo (int)$dt->format('d') . ' ' . $meses[(int)$dt->format('n')]; ?></strong>
                        <span><?php echo mc_min((int)$totalMin); ?></span>
                    </header>
                    <?php foreach ($tarefas as $t): ?>
                        <div class="mc-tarefa <?php echo mc_e($t['tipo']); ?>">
                            <i aria-hidden="true"></i>
                            <div class="t">
                                <small><?php echo mc_e($rotulos[$t['tipo']] ?? ''); ?><?php echo !empty($t['rodada']) ? ' ' . (int)$t['rodada'] . 'ª' : ''; ?><?php echo !empty($t['parcial']) ? ' · parte' : ''; ?></small>
                                <strong><?php echo mc_e((string)$t['item']['titulo']); ?></strong>
                            </div>
                            <div class="m"><?php echo mc_min((int)$t['minutos']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <p class="mc-sub">As tarefas de hoje você faz pela tela <a href="meu-plano.php?curso_id=<?php echo $cursoId; ?>" style="color:var(--ouro)">Hoje você estuda</a>, que já abre a aula e as questões certas.</p>
    <?php endif; ?>
</main>
</body>
</html>
