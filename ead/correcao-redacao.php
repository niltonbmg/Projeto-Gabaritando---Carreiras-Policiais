<?php
// correcao-redacao.php
// Professor de redação (e admin): fila de correção.
//   Passo 1: dá a nota nos critérios do edital SEM ver a sugestão da IA.
//   Passo 2: vê a sugestão do Antigão lado a lado e escolhe a nota final (a dele, a da IA ou ajustada).
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/redacao-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';

if (!redacao_eh_professor()) {
    if (function_exists('auth_is_logged') && auth_is_logged()) {
        http_response_code(403);
        exit('Esta área é do professor de redação.');
    }
    header('Location: login.php?redirect=' . rawurlencode('correcao-redacao.php'));
    exit;
}
$professorId = redacao_professor_id();
$pdo = db();
redacao_bootstrap($pdo);
$erro = '';
$msg = (string)($_GET['ok'] ?? '');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['acao'] ?? '') === 'avaliar') {
            redacao_avaliar($pdo, $id, $professorId, (array)($_POST['n'] ?? []));
            // Garante a leitura da IA para a comparação (se o robô ainda não fez, faz agora).
            $r = redacao_obter($pdo, $id);
            if (empty($r['ia'])) {
                try {
                    redacao_ia_ler($pdo, $id);
                } catch (Throwable $e) {
                    ia_fila_adicionar($pdo, 'redacao', $id);
                }
            }
            header('Location: correcao-redacao.php?id=' . $id);
            exit;
        }
        if (($_POST['acao'] ?? '') === 'publicar') {
            redacao_publicar($pdo, $id, $professorId, (string)($_POST['escolha'] ?? 'professor'), (array)($_POST['f'] ?? []), (string)($_POST['comentario'] ?? ''), $_FILES['audio'] ?? null);
            header('Location: correcao-redacao.php?ok=publicada');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$r = $id ? redacao_obter($pdo, $id) : null;
$fila = $pdo->query("SELECT r.id, r.status, r.enviada_em, r.professor_id, u.nome AS aluno, p.titulo AS proposta,
                            TIMESTAMPDIFF(HOUR, r.enviada_em, NOW()) AS horas
                       FROM redacoes r LEFT JOIN usuarios u ON u.id = r.usuario_id LEFT JOIN redacao_propostas p ON p.id = r.proposta_id
                      WHERE r.status <> 'corrigida' ORDER BY r.enviada_em ASC LIMIT 200")->fetchAll();
$st = $pdo->prepare("SELECT COUNT(*) FROM redacoes WHERE professor_id = :p AND status = 'corrigida' AND corrigida_em >= CURDATE() - INTERVAL 7 DAY");
$st->execute([':p' => $professorId]);
$feitasSemana = (int)$st->fetchColumn();

function cr_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção de redação | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#f4f5f7;--card:#fff;--linha:#e6e8ec;--texto:#111827;--suave:#6b7280;--ouro:#fbb928;--ok:#16a34a;--erro:#b42318}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .topo{background:#000;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:3px solid var(--ouro)}
        .topo img{height:36px}
        .topo a{color:var(--ouro);text-decoration:none;font-weight:700}
        .cr{max-width:1280px;margin:0 auto;padding:20px 16px 60px;display:grid;gap:16px}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:16px}
        h1{font-size:24px}h2{font-size:17px;margin-bottom:10px}
        .sub{color:var(--suave);font-size:14px}
        .alerta{padding:12px 14px;border-radius:10px;font-size:14px}.alerta.ok{background:#e7f7ec;color:var(--ok)}.alerta.erro{background:#fdecea;color:var(--erro)}
        .fila a{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;align-items:center;padding:10px 12px;border-top:1px solid var(--linha);color:inherit;text-decoration:none}
        .fila a:hover{background:#fafafa}
        .tag{font-size:12px;font-weight:700;padding:3px 9px;border-radius:99px;background:#eef0f3}
        .tag.atraso{background:#fdecea;color:var(--erro)}.tag.avaliada{background:#fff6dd;color:#8a6300}
        .dois{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);gap:16px;align-items:start}
        .texto{white-space:pre-wrap;font-size:16px;line-height:1.75;max-height:70vh;overflow:auto;border:1px solid var(--linha);border-radius:10px;padding:14px;background:#fffdf7}
        .arquivo img{max-width:100%;border-radius:10px;border:1px solid var(--linha)}
        .arquivo iframe{width:100%;height:70vh;border:1px solid var(--linha);border-radius:10px}
        .crit{display:grid;gap:6px;padding:12px 0;border-top:1px solid var(--linha)}
        .crit label{font-weight:700}
        .crit small{color:var(--suave)}
        .crit .linha{display:flex;gap:8px;align-items:center}
        .crit input[type=number]{width:90px;padding:8px;border:1px solid var(--linha);border-radius:8px;font:inherit}
        .crit input[type=text],textarea{width:100%;padding:8px;border:1px solid var(--linha);border-radius:8px;font:inherit}
        .bt{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:11px 16px;font:inherit;font-weight:700;cursor:pointer;background:var(--ouro);color:#111}
        .bt.linha{background:#fff;border:1px solid var(--linha)}
        .bt:focus-visible,input:focus-visible,textarea:focus-visible{outline:2px solid var(--ouro);outline-offset:1px}
        table{width:100%;border-collapse:collapse;font-size:14px}
        th,td{text-align:left;padding:8px;border-top:1px solid var(--linha);vertical-align:top}
        th{font-size:12px;color:var(--suave);text-transform:uppercase}
        .dif{font-weight:700}.dif.alta{color:var(--erro)}
        .escolha{display:grid;gap:8px;margin:12px 0}
        .escolha label{display:flex;gap:8px;align-items:center;padding:10px;border:1px solid var(--linha);border-radius:10px;cursor:pointer}
        @media(max-width:900px){.dois{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="topo"><img src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="Projeto Gabaritando"><span>✍️ Correção de redação</span><a href="logout.php">Sair</a></header>
<main class="cr">
    <?php if ($msg === 'publicada'): ?><div class="alerta ok" role="status">✅ Correção publicada. O aluno foi avisado.</div><?php endif; ?>
    <?php if ($erro !== ''): ?><div class="alerta erro" role="alert"><?php echo cr_e($erro); ?></div><?php endif; ?>

<?php if (!$r): ?>
    <div><h1>Fila de correção</h1><p class="sub"><?php echo count($fila); ?> redação(ões) aguardando · você corrigiu <?php echo $feitasSemana; ?> nos últimos 7 dias · prazo: <?php echo REDACAO_PRAZO_HORAS; ?>h</p></div>
    <section class="card fila" aria-label="Fila">
        <?php if (!$fila): ?><p class="sub">Nada na fila. 🎖</p><?php endif; ?>
        <?php foreach ($fila as $f): $atraso = (int)$f['horas'] >= REDACAO_PRAZO_HORAS; ?>
            <a href="correcao-redacao.php?id=<?php echo (int)$f['id']; ?>">
                <span><strong><?php echo cr_e($f['aluno'] ?: 'Aluno'); ?></strong> · <?php echo cr_e($f['proposta'] ?: 'Tema livre'); ?></span>
                <?php if ($f['status'] === 'avaliada'): ?><span class="tag avaliada">Falta escolher a nota final</span><?php else: ?><span></span><?php endif; ?>
                <span class="tag <?php echo $atraso ? 'atraso' : ''; ?>"><?php echo (int)$f['horas']; ?>h<?php echo $atraso ? ' · atrasada' : ''; ?></span>
            </a>
        <?php endforeach; ?>
    </section>
<?php else: $max = redacao_max_total($r['grade']); ?>
    <div><a href="correcao-redacao.php" class="sub">← Voltar à fila</a><h1><?php echo cr_e($r['aluno']); ?> · <?php echo cr_e($r['proposta'] ?: 'Tema livre'); ?></h1>
        <p class="sub">Critérios: <strong><?php echo cr_e($r['grade_origem'] ?: 'Grade padrão'); ?></strong> · nota máxima <?php echo redacao_fmt($max); ?></p></div>
    <div class="dois">
        <section class="card">
            <h2>Redação do aluno</h2>
            <?php if (trim((string)$r['proposta']) !== ''): ?><p class="sub" style="margin-bottom:8px"><?php echo cr_e($r['tema']); ?></p><?php endif; ?>
            <?php if (!empty($r['arquivo'])): $a = (string)$r['arquivo']; ?>
                <div class="arquivo"><?php if (str_ends_with($a, '.pdf')): ?><iframe src="arquivo-redacao.php?id=<?php echo (int)$r['id']; ?>" title="PDF da redação"></iframe><?php else: ?><img src="arquivo-redacao.php?id=<?php echo (int)$r['id']; ?>" alt="Foto da redação manuscrita"><?php endif; ?></div>
            <?php endif; ?>
            <?php if (trim((string)$r['texto']) !== '' && ($r['status'] !== 'enviada' || empty($r['arquivo']))): ?><div class="texto" style="margin-top:10px"><?php echo cr_e($r['texto']); ?></div><?php endif; ?>
        </section>

        <section class="card">
        <?php if ($r['status'] === 'enviada'): ?>
            <h2>1. Sua nota</h2>
            <p class="sub">Dê a sua nota em cada critério do edital. A sugestão do Antigão aparece <strong>depois</strong> que você enviar a sua.</p>
            <form method="post"><input type="hidden" name="acao" value="avaliar"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <?php foreach (array_values($r['grade']) as $i => $g): ?>
                    <div class="crit"><label for="n<?php echo $i; ?>"><?php echo cr_e($g['nome']); ?> <small>(0 a <?php echo redacao_fmt((float)$g['max']); ?>)</small></label>
                        <?php if (!empty($g['descricao'])): ?><small><?php echo cr_e($g['descricao']); ?></small><?php endif; ?>
                        <div class="linha"><input id="n<?php echo $i; ?>" type="number" name="n[<?php echo $i; ?>][nota]" min="0" max="<?php echo cr_e($g['max']); ?>" step="0.5" required>
                        <input type="text" name="n[<?php echo $i; ?>][comentario]" placeholder="Comentário para o aluno (opcional)" aria-label="Comentário sobre <?php echo cr_e($g['nome']); ?>"></div></div>
                <?php endforeach; ?>
                <button class="bt" type="submit">Enviar minha nota e ver a sugestão do Antigão</button>
            </form>
        <?php else: $ia = $r['ia']; ?>
            <h2>2. Compare e escolha a nota final</h2>
            <?php if (!$ia): ?><p class="alerta erro">A leitura do Antigão ainda não ficou pronta (o robô tenta de novo em alguns minutos). Você pode publicar a sua nota.</p><?php endif; ?>
            <table><thead><tr><th>Critério</th><th>Sua</th><th>Antigão</th><th>Final</th></tr></thead><tbody>
                <?php foreach (array_values($r['grade']) as $i => $g): $pn = (float)($r['prof'][$i]['nota'] ?? 0); $in = $ia['notas'][$i]['nota'] ?? null; $dif = $in !== null ? abs($pn - (float)$in) : 0; ?>
                    <tr><td><strong><?php echo cr_e($g['nome']); ?></strong><?php if (!empty($ia['notas'][$i]['justificativa'])): ?><br><small class="sub">🤖 <?php echo cr_e($ia['notas'][$i]['justificativa']); ?></small><?php endif; ?></td>
                        <td><?php echo redacao_fmt($pn); ?></td>
                        <td class="dif <?php echo $dif >= (float)$g['max'] * 0.25 ? 'alta' : ''; ?>"><?php echo $in === null ? '—' : redacao_fmt((float)$in); ?></td>
                        <td><input type="number" form="fPub" name="f[<?php echo $i; ?>][nota]" value="<?php echo cr_e($pn); ?>" min="0" max="<?php echo cr_e($g['max']); ?>" step="0.5" style="width:80px" aria-label="Nota final em <?php echo cr_e($g['nome']); ?>" oninput="document.getElementById('escAjustada').checked=true">
                            <input type="hidden" form="fPub" name="f[<?php echo $i; ?>][comentario]" value="<?php echo cr_e($r['prof'][$i]['comentario'] ?? ''); ?>"></td></tr>
                <?php endforeach; ?>
                <tr><td><strong>Total</strong></td><td><strong><?php echo redacao_fmt((float)$r['prof_nota']); ?></strong></td><td><strong><?php echo $ia ? redacao_fmt((float)$r['ia_nota']) : '—'; ?></strong></td><td></td></tr>
            </tbody></table>
            <?php if ($ia && (!empty($ia['pontos_fortes']) || !empty($ia['melhorar']))): ?>
                <p class="sub" style="margin-top:10px">🤖 Fortes: <?php echo cr_e(implode('; ', (array)($ia['pontos_fortes'] ?? []))); ?><br>🤖 Melhorar: <?php echo cr_e(implode('; ', (array)($ia['melhorar'] ?? []))); ?></p>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="fPub"><input type="hidden" name="acao" value="publicar"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <div class="escolha" role="radiogroup" aria-label="Qual nota enviar">
                    <label><input type="radio" name="escolha" value="professor" checked> Enviar a <strong>minha nota</strong> (<?php echo redacao_fmt((float)$r['prof_nota']); ?>)</label>
                    <?php if ($ia): ?><label><input type="radio" name="escolha" value="ia"> Enviar a <strong>nota do Antigão</strong> (<?php echo redacao_fmt((float)$r['ia_nota']); ?>)</label><?php endif; ?>
                    <label><input type="radio" name="escolha" value="ajustada" id="escAjustada"> Enviar a <strong>nota ajustada</strong> (coluna "Final")</label>
                </div>
                <label class="sub" for="comentario">Comentário geral para o aluno</label>
                <textarea id="comentario" name="comentario" rows="4"><?php echo cr_e($ia ? 'Pontos fortes: ' . implode('; ', (array)($ia['pontos_fortes'] ?? [])) . "\nPara melhorar: " . implode('; ', (array)($ia['melhorar'] ?? [])) : ''); ?></textarea>
                <p style="margin:8px 0"><label class="sub">🎙️ Áudio para o aluno (opcional): <input type="file" name="audio" accept="audio/*" capture></label></p>
                <button class="bt" type="submit">Publicar correção para o aluno</button>
            </form>
        <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
</main>
</body>
</html>
