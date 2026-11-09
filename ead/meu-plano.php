<?php
// meu-plano.php
// Tela do aluno no Método META: o que estudar agora, etapa a etapa, com trava.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/meta-lib.php';
require_once __DIR__ . '/includes/missao-lib.php';

$usuarioId = auth_user_id();
$erro = '';
$aviso = '';
$estado = ['plano' => null, 'itens' => [], 'atual' => null, 'nivel_geral' => 0, 'dominio_pct' => 0, 'concluidos' => 0, 'config' => []];
$cursoId = 0;
$missaoDia = null;

try {
    $pdo = db();
    meta_bootstrap($pdo);
    $cursoId = meta_curso_do_aluno($pdo, $usuarioId, (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'concluir_teoria' && $cursoId > 0) {
        meta_concluir_teoria($pdo, $usuarioId, $cursoId, (int)($_POST['item_id'] ?? 0));
        header('Location: meu-plano.php?curso_id=' . $cursoId . '&ok=teoria');
        exit;
    }
    if ($cursoId > 0) {
        $estado = meta_estado_aluno($pdo, $usuarioId, $cursoId);
        try {
            $missaoDia = missao_do_dia($pdo, $usuarioId, $cursoId);
        } catch (Throwable $e) {
            $missaoDia = null;
        }
    }
    if (($_GET['ok'] ?? '') === 'teoria') {
        $aviso = 'Teoria concluída. Agora resolva as questões para liberar o próximo assunto.';
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

function mp_e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$atual = $estado['atual'];
$cfg = $estado['config'];
$minQ = (int)($cfg['min_questoes'] ?? 5);
$metaQ = (int)($cfg['nivel4_questoes'] ?? 50);
$total = count($estado['itens']);
$medalhas = [0 => '·', 1 => '🥉', 2 => '🥈', 3 => '🥇', 4 => '💎'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missão do dia | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --fundo:#0c0c0d; --card:#161618; --card2:#1e1e21; --linha:#2a2a2e;
            --texto:#f3f3f4; --suave:#a3a3ab; --ouro:#fbb928; --ouro-escuro:#2e2408;
            --ok:#3ccf7a; --trava:#6b6b73; --alerta:#ff6b5b;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.45}
        .mp{max-width:880px;margin:0 auto;padding:24px max(16px,env(safe-area-inset-left)) 64px max(16px,env(safe-area-inset-right));display:grid;gap:20px}
        .mp h1{font-size:clamp(22px,4vw,30px)}
        .mp-sub{color:var(--suave);font-size:14px}
        .mp-alerta{padding:12px 14px;border-radius:12px;background:var(--ouro-escuro);color:var(--ouro);font-size:14px}
        .mp-alerta.erro{background:#3a1512;color:var(--alerta)}
        .mp-geral{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        .mp-geral div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:14px}
        .mp-geral strong{display:block;font-size:24px;font-variant-numeric:tabular-nums}
        .mp-geral span{font-size:12px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .mp-hoje{background:linear-gradient(160deg,#211a06,#161618 55%);border:1px solid #4a3a10;border-radius:18px;padding:20px;display:grid;gap:16px}
        .mp-hoje .rotulo{color:var(--ouro);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        .mp-hoje h2{font-size:clamp(20px,3.6vw,26px)}
        .mp-etapas{display:grid;gap:10px}
        .mp-etapa{display:flex;gap:12px;align-items:flex-start;background:var(--card2);border:1px solid var(--linha);border-radius:14px;padding:14px}
        .mp-etapa .n{flex:0 0 32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--linha);color:var(--suave)}
        .mp-etapa.ativa{border-color:var(--ouro)}
        .mp-etapa.ativa .n{background:var(--ouro);color:#111}
        .mp-etapa.feita .n{background:var(--ok);color:#06210f}
        .mp-etapa.travada{opacity:.55}
        .mp-etapa h3{font-size:16px}
        .mp-etapa p{font-size:14px;color:var(--suave)}
        .mp-etapa .acoes{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
        .btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
        .btn-ouro{background:var(--ouro);color:#111}
        .btn-linha{background:transparent;color:var(--texto);border:1px solid var(--linha)}
        .btn:focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
        .barra{height:8px;background:var(--linha);border-radius:99px;overflow:hidden}
        .barra i{display:block;height:100%;background:var(--ouro);border-radius:99px}
        .mp-lista{background:var(--card);border:1px solid var(--linha);border-radius:16px;overflow:hidden}
        .mp-lista h2{font-size:16px;padding:14px 16px;border-bottom:1px solid var(--linha)}
        .mp-item{display:grid;grid-template-columns:28px 1fr auto;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid var(--linha)}
        .mp-item:last-child{border-bottom:0}
        .mp-item .titulo{min-width:0}
        .mp-item .titulo strong{display:block;font-size:14px;overflow-wrap:anywhere}
        .mp-item .titulo span{font-size:12px;color:var(--suave)}
        .mp-item .nivel{font-size:12px;color:var(--suave);text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        .mp-item.bloqueado{color:var(--trava)}
        .mp-item.atual{background:#1d1808}
        .md{background:var(--card);border:1px solid var(--linha);border-radius:18px;padding:16px;display:grid;gap:12px}
        .md h2{font-size:17px}
        .md-lista{list-style:none;display:grid;gap:10px}
        .md-lista li{display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;background:var(--card2);border:1px solid var(--linha)}
        .md-lista li.agora{border-color:var(--ouro);background:#1d1808}
        .md-lista li > div{display:grid;gap:2px;flex:1;min-width:0}
        .md-lista small{font-size:12px;color:var(--ouro);font-weight:700}
        .md-lista strong{font-size:15px;overflow-wrap:anywhere}
        .md-lista span{font-size:12.5px;color:var(--suave)}
        .md-ico{font-size:24px;flex:0 0 32px;text-align:center}
        .md-fim{font-size:15px}.md-fim a{color:var(--ouro)}
        .mp-vazio{background:var(--card);border:1px dashed var(--linha);border-radius:16px;padding:24px;text-align:center;color:var(--suave)}
        @media(max-width:560px){.mp-geral{grid-template-columns:1fr 1fr 1fr}.mp-geral strong{font-size:20px}.mp-item{grid-template-columns:24px 1fr}.mp-item .nivel{grid-column:2;text-align:left}}
    </style>
</head>
<body>
<?php
$barraSuperiorPath = __DIR__ . '/includes/barra-superior.php';
if (is_file($barraSuperiorPath)) {
    include_once $barraSuperiorPath;
}
?>
<main class="mp">
    <header>
        <h1>🎯 Missão do dia</h1>
        <p class="mp-sub">Ciclo do Método META: 📘 Teoria → ✅ Questões (<?php echo $minQ; ?> para liberar, <?php echo $metaQ; ?> para dominar) → 🔁 Revisões → 📝 Simulado e Redação. Faça na ordem, de cima para baixo. <a href="meu-plano.php?tour=1" style="color:var(--ouro)">Como funciona?</a></p>
    </header>

    <?php if ($erro !== ''): ?><div class="mp-alerta erro" role="alert"><?php echo mp_e($erro); ?></div><?php endif; ?>
    <?php if ($aviso !== ''): ?><div class="mp-alerta" role="status"><?php echo mp_e($aviso); ?></div><?php endif; ?>

    <?php if (!$estado['plano']): ?>
        <div class="mp-vazio">Seu plano de estudos ainda está sendo preparado pelo mentor. Assim que ele aprovar, as tarefas aparecem aqui.</div>
    <?php else: ?>
        <section class="mp-geral" aria-label="Seu progresso">
            <div><span>Nível geral</span><strong><?php echo number_format((float)$estado['nivel_geral'], 1, ',', ''); ?></strong></div>
            <div><span>Assuntos liberados</span><strong><?php echo (int)$estado['concluidos']; ?>/<?php echo $total; ?></strong></div>
            <div><span>Edital dominado</span><strong><?php echo (int)$estado['dominio_pct']; ?>%</strong></div>
        </section>

        <?php if ($missaoDia !== null): $tarefasDia = $missaoDia['tarefas']; $proxM = $missaoDia['proximo']; ?>
        <section class="md" aria-labelledby="md-titulo">
            <h2 id="md-titulo">Hoje · <?php echo count($tarefasDia); ?> tarefa(s) · <?php echo (int)array_sum(array_column($tarefasDia, 'minutos')); ?> min</h2>
            <?php if (!$tarefasDia): ?>
                <p class="md-fim">🎖 <strong>Missão cumprida, combatente!</strong> Nada pendente para hoje. Quer ir além? Faça o <a href="desafio.php">Desafio do dia</a> ou suba o nível de um assunto.</p>
            <?php else: ?>
            <ol class="md-lista">
                <?php foreach ($tarefasDia as $k => $t): $it = $t['item']; $ehProx = $k === 0;
                    $url = '';
                    if ($t['tipo'] === 'teoria') { $url = missao_link_aula($it); }
                    elseif ($t['tipo'] === 'questoes') { $url = 'banco-questoes.php?topico_id=' . (int)$it['topico_id']; }
                    elseif ($t['tipo'] === 'revisao') { $url = 'banco-questoes.php?topico_id=' . (int)$it['topico_id'] . '&revisao=' . (int)$it['id'] . '-' . (int)$t['rodada']; }
                    elseif ($t['tipo'] === 'simulado') { $url = 'simulados.php'; }
                    elseif ($t['tipo'] === 'edital') { $url = 'meu-concurso.php'; }
                    elseif ($t['tipo'] === 'redacao') { $url = 'redacoes.php'; }
                    $nomeT = ['edital' => 'Hackeando o edital', 'teoria' => 'Teoria mínima', 'questoes' => 'Questões', 'revisao' => 'Revisão ' . (int)($t['rodada'] ?? 0) . 'ª', 'simulado' => 'Simulado', 'redacao' => 'Redação'][$t['tipo']] ?? '';
                    $det = $t['tipo'] === 'questoes' ? ' · ' . min($minQ, (int)$it['nivel_info']['resolvidas']) . '/' . $minQ . ' para liberar' : ''; ?>
                <li class="<?php echo $ehProx ? 'agora' : ''; ?>">
                    <span class="md-ico" aria-hidden="true"><?php echo MISSAO_ICONES[$t['tipo']] ?? '🎯'; ?></span>
                    <div><small><?php echo mp_e($nomeT); ?> · <?php echo (int)$t['minutos']; ?> min<?php echo mp_e($det); ?></small><strong><?php echo mp_e((string)$it['titulo']); ?></strong><span><?php echo mp_e((string)($it['materia_nome'] ?? '')); ?></span></div>
                    <?php if ($url !== ''): ?><a class="btn <?php echo $ehProx ? 'btn-ouro' : 'btn-linha'; ?>" href="<?php echo mp_e($url); ?>"><?php echo $ehProx ? '▶ Começar' : 'Abrir'; ?></a><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
        <?php elseif ($estado['plano']): ?>
            <div class="mp-alerta">Monte sua rotina em <a href="meu-cronograma.php" style="color:inherit;font-weight:700">Cronograma → Minha rotina</a> para a sua Missão do dia aparecer aqui.</div>
        <?php endif; ?>

        <?php if ($atual): ?>
            <?php
            $ni = $atual['nivel_info'];
            $aulaLink = !empty($atual['aula_id']) ? 'aulas.php?curso_id=' . (int)$atual['aula_curso_id'] . '&modulo_id=' . (int)$atual['aula_modulo_id'] . '&aula_id=' . (int)$atual['aula_id'] : '';
            $questoesLink = 'banco-questoes.php?topico_id=' . (int)$atual['topico_id'];
            $naTeoria = $atual['etapa'] === META_ETAPA_TEORIA;
            $feitas = min($minQ, (int)$ni['resolvidas']);
            ?>
            <section class="mp-hoje" aria-labelledby="hoje-titulo">
                <div>
                    <div class="rotulo"><?php echo mp_e((string)($atual['materia_nome'] ?? 'Assunto atual')); ?></div>
                    <h2 id="hoje-titulo"><?php echo mp_e((string)$atual['titulo']); ?></h2>
                </div>
                <div class="mp-etapas">
                    <div class="mp-etapa <?php echo $naTeoria ? 'ativa' : 'feita'; ?>">
                        <div class="n"><?php echo $naTeoria ? '1' : '✓'; ?></div>
                        <div>
                            <h3>Teoria mínima</h3>
                            <p><?php echo $naTeoria ? 'Videoaula (do professor ou do Antigão) ou PDF. Marcou “assistido” na aula, já conta.' : 'Concluída.'; ?></p>
                            <?php if ($naTeoria): ?>
                                <div class="acoes">
                                    <?php if ($aulaLink !== ''): ?><a class="btn btn-ouro" href="<?php echo mp_e($aulaLink); ?>">Abrir aula</a><?php endif; ?>
                                    <form method="post"><input type="hidden" name="acao" value="concluir_teoria"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$atual['id']; ?>"><button class="btn btn-linha" type="submit">Concluí a teoria</button></form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mp-etapa <?php echo $naTeoria ? 'travada' : 'ativa'; ?>">
                        <div class="n">2</div>
                        <div style="flex:1;min-width:0">
                            <h3>Questões comentadas</h3>
                            <?php if ($naTeoria): ?>
                                <p>Abre quando você concluir a teoria.</p>
                            <?php else: ?>
                                <p><?php echo $feitas; ?> de <?php echo $minQ; ?> para liberar o próximo assunto · <?php echo (int)$ni['percentual']; ?>% de acerto</p>
                                <div class="barra" style="margin-top:8px" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo $minQ; ?>" aria-valuenow="<?php echo $feitas; ?>"><i style="width:<?php echo (int)round($feitas * 100 / max(1, $minQ)); ?>%"></i></div>
                                <div class="acoes"><a class="btn btn-ouro" href="<?php echo mp_e($questoesLink); ?>">Resolver questões</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <div class="mp-vazio">Você liberou todos os assuntos do plano. Agora suba o nível de cada um até a meta de <?php echo $metaQ; ?> questões.</div>
        <?php endif; ?>

        <section class="mp-lista" aria-label="Assuntos do plano">
            <h2>Seu plano · <?php echo $total; ?> assuntos</h2>
            <?php foreach ($estado['itens'] as $item): ?>
                <?php
                $ni = $item['nivel_info'];
                $classe = $item['etapa'] === META_ETAPA_BLOQUEADO ? 'bloqueado' : ($atual && (int)$atual['id'] === (int)$item['id'] ? 'atual' : '');
                $icone = $item['etapa'] === META_ETAPA_BLOQUEADO ? '🔒' : $medalhas[(int)$ni['nivel']];
                ?>
                <div class="mp-item <?php echo $classe; ?>">
                    <div aria-hidden="true"><?php echo $icone; ?></div>
                    <div class="titulo">
                        <strong><?php echo mp_e((string)$item['titulo']); ?></strong>
                        <span><?php echo mp_e((string)($item['materia_nome'] ?? '')); ?><?php if (!empty($item['liberado_admin'])): ?> · liberado pelo mentor<?php endif; ?></span>
                        <?php if ($item['etapa'] !== META_ETAPA_BLOQUEADO): ?>
                            <div class="barra" style="margin-top:6px"><i style="width:<?php echo (int)$ni['progresso_meta']; ?>%"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="nivel">
                        <?php if ($item['etapa'] === META_ETAPA_BLOQUEADO): ?>
                            Bloqueado
                        <?php else: ?>
                            <?php echo mp_e((string)$ni['nome']); ?> · <?php echo (int)$ni['resolvidas']; ?>/<?php echo $metaQ; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
