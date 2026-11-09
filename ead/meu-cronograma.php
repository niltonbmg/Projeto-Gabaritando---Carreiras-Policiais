<?php
// meu-cronograma.php
// Cronograma individual do aluno (Método META): gerado a partir do Plano-Mestre,
// da disponibilidade do aluno e do que ele já cumpriu. Reorganiza sozinho.
// Visual: semana em colunas no computador (como o cronograma antigo que os
// alunos já conhecem), lista por dia no celular. Cor por matéria, caixinha
// de "feito" no cartão e cronômetro.
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
$semana = max(0, min(52, (int)($_GET['semana'] ?? 0)));

try {
    $pdo = db();
    meta_cronograma_bootstrap($pdo);
    $cursoId = meta_curso_do_aluno($pdo, $usuarioId, (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cursoId > 0) {
        $acao = (string)($_POST['acao'] ?? '');
        $volta = 'meu-cronograma.php?curso_id=' . $cursoId . '&semana=' . $semana;
        if ($acao === 'salvar_config') {
            meta_cronograma_salvar_config($pdo, $usuarioId, $cursoId, $_POST);
            header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=1');
            exit;
        }
        if ($acao === 'feito_teoria') {
            meta_concluir_teoria($pdo, $usuarioId, $cursoId, (int)($_POST['item_id'] ?? 0));
            header('Location: ' . $volta . '&ok=teoria');
            exit;
        }
        if ($acao === 'feito_revisao') {
            meta_concluir_revisao($pdo, $usuarioId, (int)($_POST['item_id'] ?? 0), (int)($_POST['rodada'] ?? 0));
            header('Location: ' . $volta . '&ok=revisao');
            exit;
        }
    }
    if ($cursoId > 0) {
        $cron = meta_cronograma_montar($pdo, $usuarioId, $cursoId);
    }
    $avisos = [
        '1' => 'Cronograma montado. Ele se reorganiza sozinho conforme você estuda.',
        'teoria' => 'Teoria marcada como feita. Agora são as questões.',
        'revisao' => 'Revisão feita.',
    ];
    $aviso = $avisos[(string)($_GET['ok'] ?? '')] ?? '';
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

/** Cor fixa por matéria, sempre com contraste para texto branco. */
function mc_cor_materia(int $materiaId): string
{
    $paleta = ['#1f6feb', '#16a34a', '#b45309', '#9333ea', '#dc2626', '#0e7490', '#be185d', '#4d7c0f', '#7c3aed', '#c2410c', '#0f766e', '#475569'];
    return $paleta[$materiaId > 0 ? $materiaId % count($paleta) : count($paleta) - 1];
}

$nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$nomesDiasLongos = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$rotulos = ['teoria' => 'Teoria mínima', 'questoes' => 'Questões', 'revisao' => 'Revisão', 'simulado' => 'Simulado', 'redacao' => 'Redação'];
$cfg = $cron['config'] ?? null;
$temPlano = $cron && $cron['estado']['plano'];
$mostrarForm = $temPlano && (!$cfg || $editar);
$hojeDt = new DateTimeImmutable('today');
$hoje = $hojeDt->format('Y-m-d');
$atualId = (int)($cron['estado']['atual']['id'] ?? 0);
$minQ = (int)($cron['estado']['config']['min_questoes'] ?? 5);

// Janela exibida: 7 dias a partir de hoje (ou das próximas semanas).
$inicioSemana = $hojeDt->modify('+' . (7 * $semana) . ' day');
$diasSemana = [];
for ($d = 0; $d < 7; $d++) {
    $diasSemana[] = $inicioSemana->modify('+' . $d . ' day');
}
$fimSemana = end($diasSemana);
$totalSemana = 0;
foreach ($diasSemana as $dia) {
    $totalSemana += array_sum(array_column($cron['agenda'][$dia->format('Y-m-d')] ?? [], 'minutos'));
}

/** Cartão de uma tarefa (igual no computador e no celular). */
function mc_cartao(array $t, int $cursoId, int $semana, int $atualId, int $minQ, array $rotulos, string $data, string $hoje): string
{
    $item = $t['item'];
    $cor = $t['tipo'] === 'simulado' ? '#111827' : ($t['tipo'] === 'redacao' ? '#7c2d12' : mc_cor_materia((int)($item['materia_id'] ?? 0)));
    $titulo = mc_e((string)$item['titulo']);
    $materia = mc_e((string)($item['materia_nome'] ?? ''));
    $etapa = mc_e(($rotulos[$t['tipo']] ?? '') . (!empty($t['rodada']) ? ' ' . (int)$t['rodada'] . 'ª' : '') . (!empty($t['parcial']) ? ' · parte' : ''));
    $tempo = mc_min((int)$t['minutos']);
    $id = (int)$item['id'];
    $podeAgora = $data <= $hoje;

    // Ação do cartão
    $acao = '';
    if ($t['tipo'] === 'teoria') {
        $link = !empty($item['aula_id']) ? 'aulas.php?curso_id=' . (int)$item['aula_curso_id'] . '&modulo_id=' . (int)$item['aula_modulo_id'] . '&aula_id=' . (int)$item['aula_id'] : '';
        $liberado = $id === $atualId;
        $acao .= $link !== '' && $liberado ? '<a class="mc-abrir" href="' . mc_e($link) . '" data-timer="' . $titulo . '">Abrir aula</a>' : '';
        $acao .= '<form method="post"><input type="hidden" name="acao" value="feito_teoria"><input type="hidden" name="curso_id" value="' . $cursoId . '"><input type="hidden" name="item_id" value="' . $id . '">'
            . '<button class="mc-check" type="submit" ' . ($liberado ? '' : 'disabled') . ' aria-label="Marcar teoria como feita: ' . $titulo . '" title="' . ($liberado ? 'Marcar como feito' : 'Faça os assuntos anteriores primeiro') . '"></button></form>';
    } elseif ($t['tipo'] === 'questoes') {
        $liberado = $id === $atualId && !empty($item['teoria_ok']);
        $feitas = min($minQ, (int)$item['nivel_info']['resolvidas']);
        $acao .= $liberado ? '<a class="mc-abrir" href="banco-questoes.php?topico_id=' . (int)$item['topico_id'] . '" data-timer="' . $titulo . '">Resolver</a>' : '';
        $acao .= '<span class="mc-contador" title="Marca sozinho ao chegar em ' . $minQ . ' questões">' . $feitas . '/' . $minQ . '</span>';
    } elseif ($t['tipo'] === 'simulado' || $t['tipo'] === 'redacao') {
        $acao .= '<a class="mc-abrir" href="' . ($t['tipo'] === 'simulado' ? 'simulados.php' : 'propostas-redacao.php') . '" data-timer="' . $titulo . '">' . ($t['tipo'] === 'simulado' ? 'Fazer simulado' : 'Escrever redação') . '</a>';
    } else {
        $acao .= '<form method="post"><input type="hidden" name="acao" value="feito_revisao"><input type="hidden" name="curso_id" value="' . $cursoId . '"><input type="hidden" name="item_id" value="' . $id . '"><input type="hidden" name="rodada" value="' . (int)$t['rodada'] . '">'
            . '<button class="mc-check" type="submit" ' . ($podeAgora ? '' : 'disabled') . ' aria-label="Marcar revisão como feita: ' . $titulo . '" title="' . ($podeAgora ? 'Marcar como feito' : 'Disponível no dia da revisão') . '"></button></form>';
    }

    return '<article class="mc-card mc-' . mc_e($t['tipo']) . '" style="--cor:' . $cor . '">'
        . '<div class="mc-card-topo"><span class="mc-etapa">' . $etapa . '</span><span class="mc-tempo">' . $tempo . '</span></div>'
        . '<strong class="mc-materia">' . $materia . '</strong>'
        . '<span class="mc-assunto">' . $titulo . '</span>'
        . '<div class="mc-acoes">' . $acao . '</div>'
        . '</article>';
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
            --ok:#3ccf7a; --alerta:#ff6b5b;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.4}
        .mc{max-width:1320px;margin:0 auto;padding:24px max(16px,env(safe-area-inset-left)) 96px max(16px,env(safe-area-inset-right));display:grid;gap:16px}
        .mc h1{font-size:clamp(22px,4vw,30px)}
        .mc-sub{color:var(--suave);font-size:14px}
        .mc-topo{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:12px}
        .mc-alerta{padding:12px 14px;border-radius:12px;background:var(--ouro-escuro);color:var(--ouro);font-size:14px}
        .mc-alerta.erro{background:#3a1512;color:var(--alerta)}
        .mc-alerta.ok{background:#0f2a1a;color:var(--ok)}
        .mc-ritmo{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .mc-ritmo div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:12px 14px;min-width:0}
        .mc-ritmo span{display:block;font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .mc-ritmo strong{display:block;font-size:18px;margin-top:2px;font-variant-numeric:tabular-nums}
        .mc-ritmo .ruim strong{color:var(--alerta)}
        .mc-ritmo .bom strong{color:var(--ok)}
        .mc-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:8px}
        .mc-nav a,.mc-nav span.off{display:grid;place-items:center;width:40px;height:40px;border-radius:10px;color:var(--ouro);text-decoration:none;font-size:22px}
        .mc-nav span.off{color:var(--linha)}
        .mc-nav a:hover{background:var(--card2)}
        .mc-nav div{text-align:center}
        .mc-nav strong{display:block;font-size:15px}
        .mc-nav small{color:var(--suave);font-size:12px}
        .mc-nav .hoje-link{width:auto;padding:0 12px;font-size:13px;font-weight:700;border:1px solid var(--linha)}
        /* Semana em colunas */
        .mc-semana-grade{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
        .mc-col{background:var(--card);border:1px solid var(--linha);border-radius:14px;min-height:220px;display:flex;flex-direction:column;min-width:0}
        .mc-col.hoje{border-color:var(--ouro);box-shadow:0 0 0 1px var(--ouro) inset}
        .mc-col.passado{opacity:.55}
        .mc-col header{padding:10px;text-align:center;border-bottom:1px solid var(--linha)}
        .mc-col header small{display:block;font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .mc-col header strong{font-size:20px;font-variant-numeric:tabular-nums}
        .mc-col.hoje header strong,.mc-col.hoje header small{color:var(--ouro)}
        .mc-col .lista{display:grid;gap:8px;padding:8px;align-content:start}
        .mc-col .vazio{color:var(--suave);font-size:12px;text-align:center;padding:16px 4px}
        .mc-col footer{margin-top:auto;padding:8px;font-size:12px;color:var(--suave);text-align:center;border-top:1px solid var(--linha);font-variant-numeric:tabular-nums}
        /* Cartão */
        .mc-card{background:var(--cor);color:#fff;border-radius:10px;padding:10px;display:grid;gap:4px;min-width:0;position:relative}
        .mc-card.mc-revisao{background:var(--card2);border:2px dashed var(--cor);color:var(--texto)}
        .mc-card-topo{display:flex;justify-content:space-between;gap:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
        .mc-etapa{background:rgba(0,0,0,.25);padding:2px 6px;border-radius:6px}
        .mc-revisao .mc-etapa{background:transparent;color:var(--cor);padding:0;filter:brightness(1.6)}
        .mc-tempo{font-variant-numeric:tabular-nums}
        .mc-materia{font-size:12px;text-transform:uppercase;letter-spacing:.03em;overflow-wrap:anywhere}
        .mc-assunto{font-size:14px;font-weight:500;overflow-wrap:anywhere}
        .mc-acoes{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:4px}
        .mc-acoes form{margin-left:auto}
        .mc-abrir{font-size:12px;font-weight:700;color:inherit;background:rgba(0,0,0,.28);padding:6px 8px;border-radius:8px;text-decoration:none}
        .mc-revisao .mc-abrir{background:var(--linha)}
        .mc-check{width:26px;height:26px;border-radius:7px;border:2px solid #fff;background:rgba(255,255,255,.12);cursor:pointer}
        .mc-revisao .mc-check{border-color:var(--texto)}
        .mc-check:hover:not(:disabled){background:rgba(255,255,255,.35)}
        .mc-check:disabled{opacity:.35;cursor:not-allowed}
        .mc-contador{margin-left:auto;font-size:12px;font-weight:700;background:rgba(0,0,0,.28);padding:5px 8px;border-radius:8px;font-variant-numeric:tabular-nums}
        .mc-check:focus-visible,.mc-abrir:focus-visible,.mc-nav a:focus-visible,.btn:focus-visible,input:focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
        /* Formulário */
        .btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
        .btn-ouro{background:var(--ouro);color:#111}
        .btn-linha{background:transparent;color:var(--texto);border:1px solid var(--linha)}
        .mc-form{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:20px;display:grid;gap:16px}
        .mc-form h2{font-size:18px}
        .mc-campos{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .mc-campo{display:grid;gap:6px;min-width:0}
        fieldset.mc-campo{border:0;padding:0;margin:0}
        .mc-campo label,.mc-campo legend{font-size:13px;color:var(--suave)}
        .mc-campo input{background:var(--card2);border:1px solid var(--linha);border-radius:10px;color:var(--texto);padding:10px 12px;font:inherit;width:100%;color-scheme:dark}
        .mc-dias-semana{display:flex;flex-wrap:wrap;gap:8px}
        .mc-dias-semana label{display:inline-flex;align-items:center;gap:6px;background:var(--card2);border:1px solid var(--linha);border-radius:999px;padding:8px 12px;font-size:14px;cursor:pointer;color:var(--texto)}
        .mc-dias-semana input{accent-color:#fbb928}
        .mc-rotina{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
        .mc-rotina-dia{display:grid;gap:8px;background:var(--card2);border:1px solid var(--linha);border-radius:12px;padding:12px;text-align:center;font-size:14px;color:var(--texto);cursor:text}
        .mc-rotina-campo{display:flex;align-items:center;justify-content:center;gap:4px;color:var(--suave)}
        .mc-rotina-dia input{width:64px;text-align:center;background:var(--fundo);border:1px solid var(--linha);border-radius:8px;color:var(--texto);padding:8px;font:inherit;font-size:16px;font-variant-numeric:tabular-nums}
        .mc-rotina-dia.folga{opacity:.55}
        .mc-abas{display:flex;gap:6px;background:var(--card);border:1px solid var(--linha);border-radius:12px;padding:4px;justify-self:start}
        .mc-abas a{padding:8px 14px;border-radius:9px;color:var(--suave);text-decoration:none;font-weight:700;font-size:14px}
        .mc-abas a[aria-current="page"]{background:var(--ouro);color:#111}
        .mc-vazio{background:var(--card);border:1px dashed var(--linha);border-radius:16px;padding:24px;text-align:center;color:var(--suave)}
        /* Cronômetro */
        .mc-timer{position:fixed;right:max(16px,env(safe-area-inset-right));bottom:max(16px,env(safe-area-inset-bottom));display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--linha);border-radius:999px;padding:8px 8px 8px 14px;box-shadow:0 8px 24px rgba(0,0,0,.45);z-index:20}
        .mc-timer strong{font-size:18px;font-variant-numeric:tabular-nums;min-width:74px}
        .mc-timer small{display:block;font-size:11px;color:var(--suave);max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .mc-timer button{width:40px;height:40px;border-radius:50%;border:0;background:var(--ouro);color:#111;font-weight:700;cursor:pointer;font-size:16px}
        .mc-timer button.zerar{background:var(--card2);color:var(--texto)}
        /* Celular: lista por dia */
        @media(max-width:900px){
            .mc-semana-grade{grid-template-columns:1fr}
            .mc-col{min-height:0}
            .mc-col header{display:flex;justify-content:space-between;align-items:baseline;text-align:left;padding:10px 14px}
            .mc-col header strong{font-size:16px}
            .mc-col footer{display:none}
            .mc-col.semnada{display:none}
            .mc-ritmo strong{font-size:15px}
            .mc-campos{grid-template-columns:1fr}
            .mc-timer{padding:6px 6px 6px 12px}.mc-timer small{display:none}.mc-timer strong{font-size:15px;min-width:64px}.mc-timer button{width:34px;height:34px}
            .mc-rotina{grid-template-columns:1fr}
            .mc-rotina-dia{grid-template-columns:1fr auto;align-items:center;text-align:left}
        }
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
            <p class="mc-sub">Montado para você pelo Método META. Se atrasar, ele se reorganiza sozinho a partir de hoje.</p>
        </div>
        <?php if ($temPlano && $cfg): ?>
            <nav class="mc-abas" aria-label="Seções do cronograma">
                <a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>" <?php echo $editar ? '' : 'aria-current="page"'; ?>>Minha semana</a>
                <a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&editar=1" <?php echo $editar ? 'aria-current="page"' : ''; ?>>Minha rotina</a>
            </nav>
        <?php endif; ?>
    </div>

    <?php if ($erro !== ''): ?><div class="mc-alerta erro" role="alert"><?php echo mc_e($erro); ?></div><?php endif; ?>
    <?php if ($aviso !== ''): ?><div class="mc-alerta ok" role="status"><?php echo mc_e($aviso); ?></div><?php endif; ?>

    <?php if (!$temPlano): ?>
        <div class="mc-vazio">Seu plano de estudos ainda está sendo preparado pelo mentor. Quando ele aprovar, você monta seu cronograma aqui.</div>
    <?php elseif ($mostrarForm): ?>
        <script>document.addEventListener('DOMContentLoaded',function(){var ins=document.querySelectorAll('.mc-rotina input'),out=document.getElementById('mcTotal');function calc(){var t=0;ins.forEach(function(i){var v=parseFloat(String(i.value).replace(',','.'))||0;t+=v;i.closest('.mc-rotina-dia').classList.toggle('folga',v<=0);});out.textContent='Total: '+String(t).replace('.',',')+' horas por semana';}ins.forEach(function(i){i.addEventListener('input',calc);});calc();});</script>
        <form class="mc-form" method="post">
            <h2><?php echo $cfg ? 'Minha rotina de estudos' : 'Primeiro, sua rotina de estudos'; ?></h2>
            <p class="mc-sub">Seja realista: um cronograma cumprido vale mais que um bonito. Ao salvar, o cronograma se reorganiza na hora.</p>
            <input type="hidden" name="acao" value="salvar_config"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <div class="mc-campos">
                <div class="mc-campo"><label for="inicio">Começo em</label><input id="inicio" name="data_inicio" type="date" required value="<?php echo mc_e((string)($cfg['data_inicio'] ?? $hoje)); ?>"></div>
                <div class="mc-campo"><label for="prova">Data da prova (se souber)</label><input id="prova" name="data_prova" type="date" value="<?php echo mc_e((string)($cfg['data_prova'] ?? '')); ?>"></div>
            </div>
            <fieldset class="mc-campo">
                <legend>Quantas horas você consegue estudar em cada dia? Deixe 0 no dia de descanso.</legend>
                <div class="mc-rotina">
                    <?php $minSel = $cfg['minutos'] ?? [1 => 180, 2 => 180, 3 => 180, 4 => 180, 5 => 180, 6 => 240]; foreach ([1, 2, 3, 4, 5, 6, 0] as $d): $h = isset($minSel[$d]) ? $minSel[$d] / 60 : 0; ?>
                        <label class="mc-rotina-dia" for="h<?php echo $d; ?>">
                            <span><?php echo $nomesDiasLongos[$d]; ?></span>
                            <span class="mc-rotina-campo"><input id="h<?php echo $d; ?>" name="horas[<?php echo $d; ?>]" type="number" min="0" max="16" step="0.5" inputmode="decimal" value="<?php echo mc_e(rtrim(rtrim(number_format((float)$h, 1, '.', ''), '0'), '.')); ?>"> h</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mc-sub" id="mcTotal" aria-live="polite"></p>
            </fieldset>
            <div><button class="btn btn-ouro" type="submit"><?php echo $cfg ? 'Salvar e reorganizar' : 'Montar meu cronograma'; ?></button></div>
        </form>
    <?php else: ?>
        <?php
        $folga = $cron['folga_dias'];
        $atraso = (int)$cron['atraso_dias'];
        $fim = $cron['fim'] ? new DateTimeImmutable($cron['fim']) : null;
        ?>
        <section class="mc-ritmo" aria-label="Seu ritmo">
            <div><span>Termina o plano</span><strong><?php echo $fim ? (int)$fim->format('d') . ' ' . $meses[(int)$fim->format('n')] : 'Concluído'; ?></strong></div>
            <div class="<?php echo $atraso > 0 ? 'ruim' : 'bom'; ?>"><span>Ritmo</span><strong><?php echo $atraso > 0 ? $atraso . ' dia(s) atrás' : 'Em dia'; ?></strong></div>
            <div class="<?php echo $folga === null ? '' : ($folga < 0 ? 'ruim' : 'bom'); ?>"><span>Até a prova</span><strong><?php echo $folga === null ? 'Sem data' : ($folga < 0 ? 'Faltam ' . abs($folga) . ' dias' : $folga . ' dias de folga'); ?></strong></div>
        </section>

        <?php if ($folga !== null && $folga < 0): ?>
            <div class="mc-alerta erro" role="alert">No ritmo atual você não termina o plano antes da prova. Aumente suas horas em "Minha rotina" ou fale com o mentor no próximo check-in.</div>
        <?php endif; ?>

        <?php $ajustes = array_filter((array)($cron['desempenho'] ?? []), static fn(array $d): bool => (float)$d['fator'] !== 1.0); if ($ajustes): ?>
            <div class="mc-alerta" role="note">📊 Ajustado pelo seu Ranking Individual: <?php echo mc_e(implode(' · ', array_map(static fn(array $d): string => $d['materia'] . ' ' . $d['pct'] . '% (' . ((float)$d['fator'] > 1 ? 'mais tempo' : 'manutenção') . ')', $ajustes))); ?>. <a href="desafio.php" style="color:inherit">Ver ranking</a></div>
        <?php endif; ?>
        <nav class="mc-nav" aria-label="Trocar de semana">
            <?php if ($semana > 0): ?><a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&semana=<?php echo $semana - 1; ?>" aria-label="Semana anterior">‹</a><?php else: ?><span class="off" aria-hidden="true">‹</span><?php endif; ?>
            <div>
                <strong><?php echo (int)$inicioSemana->format('d') . ' ' . $meses[(int)$inicioSemana->format('n')]; ?> – <?php echo (int)$fimSemana->format('d') . ' ' . $meses[(int)$fimSemana->format('n')]; ?></strong>
                <small><?php echo $semana === 0 ? 'Próximos 7 dias' : ($semana === 1 ? 'Semana seguinte' : 'Daqui a ' . $semana . ' semanas'); ?> · <?php echo mc_min((int)$totalSemana); ?> de estudo</small>
            </div>
            <?php if ($semana > 0): ?><a class="hoje-link" href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>">Hoje</a><?php endif; ?>
            <a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&semana=<?php echo $semana + 1; ?>" aria-label="Próxima semana">›</a>
        </nav>

        <section class="mc-semana-grade" aria-label="Semana">
            <?php foreach ($diasSemana as $dia): ?>
                <?php
                $data = $dia->format('Y-m-d');
                $tarefas = $cron['agenda'][$data] ?? [];
                $estuda = in_array((int)$dia->format('w'), $cfg['dias'], true);
                $classe = ($data === $hoje ? ' hoje' : '') . ($data < $hoje ? ' passado' : '') . (!$tarefas ? ' semnada' : '');
                ?>
                <div class="mc-col<?php echo $classe; ?>">
                    <header><small><?php echo $data === $hoje ? 'Hoje' : $nomesDias[(int)$dia->format('w')]; ?></small><strong><?php echo (int)$dia->format('d'); ?><span class="mc-sub" style="font-size:13px"> <?php echo $meses[(int)$dia->format('n')]; ?></span></strong></header>
                    <div class="lista">
                        <?php if (!$tarefas): ?>
                            <div class="vazio"><?php echo $data < $hoje ? '' : ($estuda ? 'Livre' : 'Descanso'); ?></div>
                        <?php else: ?>
                            <?php foreach ($tarefas as $t) { echo mc_cartao($t, $cursoId, $semana, $atualId, $minQ, $rotulos, $data, $hoje); } ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($tarefas): ?><footer><?php echo mc_min((int)array_sum(array_column($tarefas, 'minutos'))); ?></footer><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <p class="mc-sub">A caixinha marca a etapa como feita. As questões marcam sozinhas quando você chega em <?php echo $minQ; ?> resolvidas.</p>

        <div class="mc-timer" id="mcTimer" role="group" aria-label="Cronômetro de estudo">
            <div><strong id="mcTempo">00:00:00</strong><small id="mcTarefa">Cronômetro de estudo</small></div>
            <button type="button" id="mcPlay" aria-label="Iniciar cronômetro">▶</button>
            <button type="button" id="mcZerar" class="zerar" aria-label="Zerar cronômetro">↺</button>
        </div>
        <script>
        (function () {
            // Cronômetro simples, guardado neste navegador (continua se trocar de página).
            var chave = 'gab_cronometro', st = {inicio: null, acumulado: 0, tarefa: ''};
            try { st = Object.assign(st, JSON.parse(localStorage.getItem(chave) || '{}')); } catch (e) {}
            var elT = document.getElementById('mcTempo'), elN = document.getElementById('mcTarefa'), bP = document.getElementById('mcPlay'), bZ = document.getElementById('mcZerar');
            function salvar() { try { localStorage.setItem(chave, JSON.stringify(st)); } catch (e) {} }
            function total() { return st.acumulado + (st.inicio ? Date.now() - st.inicio : 0); }
            function pad(n) { return String(n).padStart(2, '0'); }
            function render() {
                var s = Math.floor(total() / 1000);
                elT.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s / 60) % 60) + ':' + pad(s % 60);
                elN.textContent = st.tarefa || 'Cronômetro de estudo';
                bP.textContent = st.inicio ? '❚❚' : '▶';
                bP.setAttribute('aria-label', st.inicio ? 'Pausar cronômetro' : 'Iniciar cronômetro');
            }
            function iniciar(tarefa) { if (tarefa) { st.tarefa = tarefa; } if (!st.inicio) { st.inicio = Date.now(); } salvar(); render(); }
            bP.addEventListener('click', function () { if (st.inicio) { st.acumulado = total(); st.inicio = null; salvar(); render(); } else { iniciar(); } });
            bZ.addEventListener('click', function () { st = {inicio: null, acumulado: 0, tarefa: ''}; salvar(); render(); });
            document.querySelectorAll('[data-timer]').forEach(function (a) { a.addEventListener('click', function () { st.acumulado = 0; st.inicio = null; iniciar(a.getAttribute('data-timer')); }); });
            render(); setInterval(render, 1000);
        })();
        </script>
    <?php endif; ?>
</main>
</body>
</html>
