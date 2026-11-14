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
require_once __DIR__ . '/includes/cronograma-cartao.php';
$erro = '';
$aviso = '';
$cron = null;
$cursoId = 0;
$editar = isset($_GET['editar']);
$topicos = isset($_GET['topicos']);
$vista = in_array($_GET['vista'] ?? '', ['hoje', 'semana', 'mes'], true) ? $_GET['vista'] : 'semana';
$hojeDt = new DateTimeImmutable('today');
$hoje = $hojeDt->format('Y-m-d');
$base = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? new DateTimeImmutable((string)$_GET['data']) : $hojeDt;
if ($base < $hojeDt) {
    $base = $hojeDt; // o cronograma é sempre de hoje em diante (o que passou foi reorganizado)
}

try {
    $pdo = db();
    meta_cronograma_bootstrap($pdo);
    $cursoId = meta_curso_do_aluno($pdo, $usuarioId, (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cursoId > 0) {
        $acao = (string)($_POST['acao'] ?? '');
        $volta = ($_POST['volta'] ?? '') === 'inicio' ? 'dashboard.php?ok=' : 'meu-cronograma.php?curso_id=' . $cursoId . '&ok=';
        $cfgAtual = meta_cronograma_config($pdo, $usuarioId, $cursoId);
        $aj = $cfgAtual['ajustes'] ?? ['dif' => [], 'fora' => [], 'feitos' => [], 'extra' => []];
        $tid = (string)(int)($_POST['topico_id'] ?? 0);
        if ($acao === 'salvar_config') {
            meta_cronograma_salvar_config($pdo, $usuarioId, $cursoId, $_POST);
            header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=1');
            exit;
        }
        if ($acao === 'feito_teoria') {
            meta_concluir_teoria($pdo, $usuarioId, $cursoId, (int)($_POST['item_id'] ?? 0));
            header('Location: ' . $volta . 'teoria');
            exit;
        }
        if ($acao === 'feito_revisao') {
            meta_concluir_revisao($pdo, $usuarioId, (int)($_POST['item_id'] ?? 0), (int)($_POST['rodada'] ?? 0));
            header('Location: ' . $volta . 'revisao');
            exit;
        }
        if ($cfgAtual && $acao === 'mais_tempo' && $tid !== '0') {
            $aj['extra'][$tid] = min(240, (int)($aj['extra'][$tid] ?? 0) + 30);
            meta_cronograma_salvar_ajustes($pdo, $usuarioId, $cursoId, $aj);
            header('Location: ' . $volta . 'tempo');
            exit;
        }
        if ($cfgAtual && $acao === 'assunto_feito' && $tid !== '0') {
            $aj['feitos'] = array_values(array_unique(array_merge((array)$aj['feitos'], [$tid])));
            meta_cronograma_salvar_ajustes($pdo, $usuarioId, $cursoId, $aj);
            header('Location: ' . $volta . 'feito');
            exit;
        }
        if ($cfgAtual && $acao === 'salvar_topicos') {
            $aj['dif'] = array_map(static fn($v) => max(1, min(5, (int)$v)), (array)($_POST['dif'] ?? []));
            $incl = array_map('strval', array_map('intval', (array)($_POST['incluir'] ?? [])));
            $aj['fora'] = array_values(array_diff(array_map('strval', array_map('intval', (array)($_POST['todos'] ?? []))), $incl));
            meta_cronograma_salvar_ajustes($pdo, $usuarioId, $cursoId, $aj);
            header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=topicos');
            exit;
        }
        if ($cfgAtual && $acao === 'recalcular') {
            // Recomeça a contagem a partir de hoje: o atraso zera e tudo que falta é redistribuído.
            $pdo->prepare('UPDATE meta_aluno_config SET data_inicio = CURDATE() WHERE usuario_id = :u AND curso_id = :c')->execute([':u' => $usuarioId, ':c' => $cursoId]);
            header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=recalculado');
            exit;
        }
        if ($acao === 'excluir') {
            meta_cronograma_excluir($pdo, $usuarioId, $cursoId);
            header('Location: meu-cronograma.php?curso_id=' . $cursoId . '&ok=excluido');
            exit;
        }
    }
    if ($cursoId > 0) {
        $cron = meta_cronograma_montar($pdo, $usuarioId, $cursoId);
    }
    $avisos = [
        '1' => 'Cronograma montado. Ele se reorganiza sozinho conforme você estuda.',
        'teoria' => 'Aula marcada como feita. Agora são as questões.',
        'revisao' => 'Revisão feita.',
        'tempo' => 'Mais 30 minutos para este assunto. O cronograma foi reorganizado.',
        'feito' => 'Assunto marcado como feito: saiu do seu cronograma.',
        'topicos' => 'Tópicos salvos. O cronograma foi reorganizado.',
        'recalculado' => 'Cronograma recalculado a partir de hoje.',
        'excluido' => 'Cronograma excluído. Suas aulas e questões continuam guardadas. Monte de novo quando quiser.',
    ];
    $aviso = $avisos[(string)($_GET['ok'] ?? '')] ?? '';
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$nomesDias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$nomesDiasLongos = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$cfg = $cron['config'] ?? null;
$temPlano = $cron && $cron['estado']['plano'];
$mostrarForm = $temPlano && (!$cfg || $editar);
$minQ = (int)($cron['estado']['config']['min_questoes'] ?? 5);
$revFeitas = $temPlano ? meta_revisoes_feitas($pdo, $usuarioId) : [];

// Janela exibida: Hoje (1 dia), Semana (7 dias) ou Mês (35 dias), a partir da data escolhida.
$nDias = ['hoje' => 1, 'semana' => 7, 'mes' => 35][$vista];
$passo = ['hoje' => '+1 day', 'semana' => '+7 day', 'mes' => '+35 day'][$vista];
$diasVista = [];
for ($d = 0; $d < $nDias; $d++) {
    $diasVista[] = $base->modify('+' . $d . ' day');
}
$fimVista = end($diasVista);
$anterior = $base->modify(str_replace('+', '-', $passo));
$anterior = $anterior < $hojeDt ? $hojeDt : $anterior;
$proxima = $base->modify($passo);
$urlBase = 'meu-cronograma.php?curso_id=' . $cursoId . '&vista=' . $vista;

// Progresso geral: assuntos concluídos do plano (com aula).
$totItens = 0;
$okItens = 0;
foreach ($cron['estado']['itens'] ?? [] as $it) {
    if (!empty($it['aula_id'])) {
        $totItens++;
        $okItens += $it['etapa'] === META_ETAPA_CONCLUIDO ? 1 : 0;
    }
}
$pctGeral = $totItens ? (int)round($okItens * 100 / $totItens) : 0;
$nBloco = 0;
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
        .mc-engr{position:relative}
        .mc-engr summary{list-style:none;cursor:pointer;width:40px;height:40px;display:grid;place-items:center;border:1px solid var(--linha);border-radius:10px;font-size:20px}
        .mc-engr summary::-webkit-details-marker{display:none}
        .mc-engr div{position:absolute;right:0;top:46px;z-index:30;background:var(--card);border:1px solid var(--linha);border-radius:12px;padding:6px;display:grid;min-width:210px;box-shadow:0 8px 24px rgba(0,0,0,.45)}
        .mc-engr a,.mc-engr button{display:block;text-align:left;padding:10px 12px;border-radius:8px;color:var(--texto);text-decoration:none;background:none;border:0;font:inherit;font-size:14px;cursor:pointer;width:100%}
        .mc-engr a:hover,.mc-engr button:hover{background:var(--card2)}
        .mc-engr form{margin:0}
        .mc-prog{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:10px 14px;display:grid;gap:6px}
        .mc-prog span{font-size:13px;color:var(--suave)}
        .mc-barra{height:10px;background:var(--card2);border-radius:999px;overflow:hidden}.mc-barra i{display:block;height:100%;background:var(--ok)}
        .mc-mes{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
        .mc-mes .mc-col{min-height:110px}
        .mc-mes .mc-card-abrir{padding:4px 0 4px 6px}.mc-mes .mc-materia{display:none}.mc-mes .mc-assunto{font-size:11px}
        .mc-mes .mc-check{width:18px;height:18px;font-size:11px}
        .mc-semana-grade.um{grid-template-columns:minmax(0,520px)}
        .mc-top{display:grid;gap:12px}
        .mc-mat{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:14px;display:grid;gap:8px}
        .mc-mat label{display:flex;gap:8px;align-items:flex-start;font-size:14px}
        .mc-mat input[type=range]{width:100%;accent-color:#fbb928}
        .mc-mat input[type=checkbox]{accent-color:#fbb928;margin-top:3px}
        .mc-nav input[type=date]{background:var(--card2);border:1px solid var(--linha);border-radius:8px;color:var(--texto);padding:6px;color-scheme:dark;font:inherit;font-size:13px}
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
            .mc-mes{grid-template-columns:1fr}.mc-mes .mc-col.semnada{display:none}.mc-mes .mc-materia{display:block}
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
<?php echo mc_estilo_cartao(); ?>
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
            <div style="display:flex;gap:8px;align-items:center">
            <nav class="mc-abas" aria-label="Vista">
                <?php foreach (['hoje' => 'Hoje', 'semana' => 'Semana', 'mes' => 'Mês'] as $v => $rot): ?><a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&vista=<?php echo $v; ?>" <?php echo !$editar && !$topicos && $vista === $v ? 'aria-current="page"' : ''; ?>><?php echo $rot; ?></a><?php endforeach; ?>
            </nav>
            <details class="mc-engr"><summary aria-label="Configurar cronograma">⚙</summary><div>
                <a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&topicos=1">Editar tópicos</a>
                <a href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>&editar=1">Editar rotina</a>
                <form method="post"><input type="hidden" name="acao" value="recalcular"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button type="submit">Recalcular</button></form>
                <form method="post" onsubmit="return confirm('Excluir o cronograma? Suas aulas, questões e revisões feitas continuam guardadas; só a rotina e os ajustes são apagados.');"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button type="submit" style="color:var(--alerta)">Excluir cronograma</button></form>
            </div></details>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($erro !== ''): ?><div class="mc-alerta erro" role="alert"><?php echo mc_e($erro); ?></div><?php endif; ?>
    <?php if ($aviso !== ''): ?><div class="mc-alerta ok" role="status"><?php echo mc_e($aviso); ?></div><?php endif; ?>

    <?php if ($temPlano && !empty($cron['estado']['plano']['implicito'])): ?><div class="mc-alerta" role="status">📋 <strong>Plano inicial:</strong> seu mentor vai ajustar em breve. O que você fizer agora continua valendo.</div><?php endif; ?>
    <?php if (!$temPlano): ?>
        <?php require_once __DIR__ . '/includes/meta-lib.php'; [$semTxt, $semLink, $semBt] = meta_motivo_sem_plano($pdo ?? db(), $usuarioId, (int)($cursoId ?? 0)); ?>
        <div class="mc-vazio"><?php echo mc_e($semTxt); ?><?php if ($semLink !== ''): ?><br><a class="mc-abrir" style="display:inline-block;margin-top:12px" href="<?php echo mc_e($semLink); ?>"><?php echo mc_e($semBt); ?></a><?php endif; ?></div>
    <?php elseif ($topicos && $cfg): ?>
        <?php $porMat = []; foreach ($cron['estado']['itens'] as $it) { if (!empty($it['aula_id'])) { $porMat[(int)$it['materia_id']]['nome'] = (string)($it['materia_nome'] ?? ''); $porMat[(int)$it['materia_id']]['itens'][(int)$it['topico_id']] = (string)$it['titulo']; } } ?>
        <form class="mc-top" method="post">
            <input type="hidden" name="acao" value="salvar_topicos"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <p class="mc-sub">Para cada matéria, diga sua dificuldade (mais difícil = mais tempo) e marque os assuntos que entram no seu cronograma. Só aparecem assuntos com aula pronta.</p>
            <?php foreach ($porMat as $mid => $m): $dv = (int)($cfg['ajustes']['dif'][(string)$mid] ?? 3); ?>
                <section class="mc-mat" style="border-left:4px solid <?php echo mc_cor_materia($mid); ?>">
                    <strong><?php echo mc_e($m['nome']); ?></strong>
                    <label style="display:grid;gap:4px">Sua dificuldade: <span><input type="range" name="dif[<?php echo $mid; ?>]" min="1" max="5" value="<?php echo $dv; ?>" aria-label="Dificuldade em <?php echo mc_e($m['nome']); ?>"></span><small class="mc-sub">fácil ← → difícil</small></label>
                    <?php foreach ($m['itens'] as $tid => $tt): ?>
                        <input type="hidden" name="todos[]" value="<?php echo $tid; ?>">
                        <label><input type="checkbox" name="incluir[]" value="<?php echo $tid; ?>" <?php echo in_array((string)$tid, array_map('strval', $cfg['ajustes']['fora']), true) ? '' : 'checked'; ?>> <?php echo mc_e($tt); ?></label>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
            <div><button class="btn btn-ouro" type="submit">Salvar e reorganizar</button> <a class="btn btn-linha" href="meu-cronograma.php?curso_id=<?php echo $cursoId; ?>">Cancelar</a></div>
        </form>
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
        <div class="mc-prog"><span>Progresso geral: <?php echo $okItens; ?> de <?php echo $totItens; ?> assuntos (<?php echo $pctGeral; ?>%)</span><div class="mc-barra"><i style="width:<?php echo $pctGeral; ?>%"></i></div></div>
        <nav class="mc-nav" aria-label="Trocar de período">
            <?php if ($base > $hojeDt): ?><a href="<?php echo $urlBase; ?>&data=<?php echo $anterior->format('Y-m-d'); ?>" aria-label="Anterior">‹</a><?php else: ?><span class="off" aria-hidden="true">‹</span><?php endif; ?>
            <div>
                <strong><?php echo (int)$base->format('d') . ' ' . $meses[(int)$base->format('n')]; ?><?php if ($nDias > 1): ?> – <?php echo (int)$fimVista->format('d') . ' ' . $meses[(int)$fimVista->format('n')]; ?><?php endif; ?></strong>
                <form method="get" style="display:inline"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="vista" value="<?php echo $vista; ?>"><input type="date" name="data" min="<?php echo $hoje; ?>" value="<?php echo $base->format('Y-m-d'); ?>" onchange="this.form.submit()" aria-label="Ir para a data"></form>
            </div>
            <?php if ($base > $hojeDt): ?><a class="hoje-link" href="<?php echo $urlBase; ?>">Hoje</a><?php endif; ?>
            <a href="<?php echo $urlBase; ?>&data=<?php echo $proxima->format('Y-m-d'); ?>" aria-label="Próximo">›</a>
        </nav>

        <section class="<?php echo $vista === 'mes' ? 'mc-mes' : 'mc-semana-grade' . ($vista === 'hoje' ? ' um' : ''); ?>" aria-label="Agenda">
            <?php foreach ($diasVista as $dia): ?>
                <?php
                $data = $dia->format('Y-m-d');
                $tarefas = $cron['agenda'][$data] ?? [];
                $estuda = in_array((int)$dia->format('w'), $cfg['dias'], true);
                $classe = ($data === $hoje ? ' hoje' : '') . (!$tarefas ? ' semnada' : '');
                ?>
                <div class="mc-col<?php echo $classe; ?>">
                    <header><small><?php echo $data === $hoje ? 'Hoje' : $nomesDias[(int)$dia->format('w')]; ?></small><strong><?php echo (int)$dia->format('d'); ?><span class="mc-sub" style="font-size:13px"> <?php echo $meses[(int)$dia->format('n')]; ?></span></strong></header>
                    <div class="lista">
                        <?php if (!$tarefas): ?>
                            <div class="vazio"><?php echo $estuda ? 'Livre' : 'Descanso'; ?></div>
                        <?php else: ?>
                            <?php foreach ($tarefas as $t) { echo mc_cartao($t, ['cursoId' => $cursoId, 'hoje' => $hoje, 'data' => $data, 'volta' => 'cronograma', 'revFeitas' => $revFeitas, 'minQ' => $minQ, 'n' => ++$nBloco]); } ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($tarefas && $vista !== 'mes'): ?><footer><?php echo mc_min((int)array_sum(array_column($tarefas, 'minutos'))); ?></footer><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php echo mc_script_janelas(); ?>
        <p class="mc-sub">Toque no bloco para ver os passos do assunto. A caixinha marca como concluído; as questões marcam sozinhas ao chegar no mínimo.</p>

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
