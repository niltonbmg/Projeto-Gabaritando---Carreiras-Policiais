<?php
// redacoes.php
// Aluno: escolhe a proposta, envia a redação (digitada, foto ou PDF da folha) e acompanha a correção.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/redacao-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
@set_time_limit(150);

$usuarioId = (int)auth_user_id();
$pdo = db();
redacao_bootstrap($pdo);
$erro = '';
$ok = (string)($_GET['ok'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = redacao_enviar($pdo, $usuarioId, (int)($_POST['proposta_id'] ?? 0), (string)($_POST['texto'] ?? ''), $_FILES['arquivo'] ?? null);
        // A leitura do Antigão roda em segundo plano (o corretor só vê depois de dar a nota dele).
        ia_fila_adicionar($pdo, 'redacao', $id);
        header('Location: redacoes.php?ok=enviada');
        exit;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$propostas = $pdo->query('SELECT * FROM redacao_propostas WHERE ativa = 1 ORDER BY id DESC')->fetchAll();
$st = $pdo->prepare('SELECT r.*, p.titulo AS proposta FROM redacoes r LEFT JOIN redacao_propostas p ON p.id = r.proposta_id WHERE r.usuario_id = :u ORDER BY r.id DESC');
$st->execute([':u' => $usuarioId]);
$minhas = $st->fetchAll();
$pdo->prepare("UPDATE redacoes SET visto_aluno = 1 WHERE usuario_id = :u AND status = 'corrigida'")->execute([':u' => $usuarioId]);
$lim = redacao_limite($pdo, $usuarioId);
[$grade, $gradeOrigem] = redacao_grade_do_aluno($pdo, $usuarioId);
$propostaSel = (int)($_GET['proposta'] ?? ($propostas[0]['id'] ?? 0));

function rd_e($v): string
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
    <title>Redação | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a;--erro:#ff6b5b}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .rd{max-width:900px;margin:0 auto;padding:24px 16px 72px;display:grid;gap:18px}
        h1{font-size:clamp(22px,4vw,30px)}
        h2{font-size:18px;margin-bottom:10px}
        .sub{color:var(--suave);font-size:14px}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:16px}
        .alerta{padding:12px 14px;border-radius:12px;font-size:14px}
        .alerta.ok{background:#0f2a1a;color:var(--ok)}.alerta.erro{background:#3a1512;color:var(--erro)}
        .prop{display:grid;gap:8px}
        .prop label{display:flex;gap:10px;align-items:flex-start;padding:12px;border-radius:12px;background:var(--card2);border:1px solid var(--linha);cursor:pointer}
        .prop input:checked + div strong{color:var(--ouro)}
        .prop small{color:var(--suave);display:block}
        details.apoio{margin-top:6px;font-size:13.5px;color:#d4d4d8}
        textarea{width:100%;min-height:320px;border-radius:12px;border:1px solid var(--linha);background:#0f0f11;color:var(--texto);padding:14px;font:inherit;font-size:16px;line-height:1.7}
        .linhas{font-size:12.5px;color:var(--suave)}
        .ou{display:flex;align-items:center;gap:10px;color:var(--suave);font-size:13px;margin:10px 0}
        .ou::before,.ou::after{content:"";flex:1;height:1px;background:var(--linha)}
        input[type=file]{color:var(--texto);font:inherit}
        .bt{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--ouro);color:#111}
        .bt:disabled{opacity:.5;cursor:not-allowed}
        .bt:focus-visible{outline:2px solid #fff;outline-offset:2px}
        .grade{font-size:13px;color:var(--suave)}
        .minha{display:grid;gap:8px;padding:14px;border-radius:14px;background:var(--card2);border:1px solid var(--linha)}
        .minha .topo{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
        .st{font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;background:#2e2408;color:var(--ouro)}
        .st.corrigida{background:#0f2a1a;color:var(--ok)}
        .nota{font-size:28px;font-weight:700;color:var(--ouro)}
        .crit{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 12px;font-size:14px;padding:8px 0;border-top:1px solid var(--linha)}
        .crit small{grid-column:1/-1;color:var(--suave)}
        audio{width:100%}
    </style>
</head>
<body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="rd">
    <header>
        <h1>✍️ Redação</h1>
        <p class="sub">Escolha a proposta, escreva aqui ou mande a foto da folha (como na prova). Um professor corrige pelos critérios do seu edital e você é avisado no sininho.</p>
    </header>
    <?php if ($ok === 'enviada'): ?><div class="alerta ok" role="status">✅ Redação enviada! Agora é com o professor: prazo de até <?php echo REDACAO_PRAZO_HORAS; ?>h. Você recebe o aviso no sininho.</div><?php endif; ?>
    <?php if ($erro !== ''): ?><div class="alerta erro" role="alert"><?php echo rd_e($erro); ?></div><?php endif; ?>

    <section class="card" aria-labelledby="enviarTitulo">
        <h2 id="enviarTitulo">Enviar redação</h2>
        <?php if (!$lim['pode']): ?>
            <p class="sub">Você já enviou <?php echo (int)$lim['usadas']; ?> redação(ões) nos últimos 7 dias (limite: <?php echo (int)$lim['limite']; ?> por semana). Enquanto isso, revise a correção da última. 💪</p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <?php if ($propostas): ?>
            <fieldset class="prop"><legend class="sub" style="margin-bottom:6px">Proposta</legend>
                <?php foreach ($propostas as $p): ?>
                    <label><input type="radio" name="proposta_id" value="<?php echo (int)$p['id']; ?>" <?php echo (int)$p['id'] === $propostaSel ? 'checked' : ''; ?>>
                        <div><strong><?php echo rd_e($p['titulo']); ?></strong><small><?php echo nl2br(rd_e($p['tema'])); ?></small>
                        <?php if (trim((string)$p['texto_apoio']) !== ''): ?><details class="apoio"><summary>Textos de apoio</summary><?php echo nl2br(rd_e($p['texto_apoio'])); ?></details><?php endif; ?></div></label>
                <?php endforeach; ?>
            </fieldset>
            <?php else: ?><p class="sub">Ainda não há proposta cadastrada: envie com tema livre.</p><?php endif; ?>
            <p class="grade" style="margin:12px 0 6px">Será corrigida por: <strong><?php echo rd_e($gradeOrigem); ?></strong> · <?php echo rd_e(implode(' · ', array_map(static fn($g) => $g['nome'] . ' (' . redacao_fmt((float)$g['max']) . ')', $grade))); ?></p>
            <label for="texto" class="sub">Digite sua redação</label>
            <textarea id="texto" name="texto" placeholder="Título (se o edital pedir)&#10;&#10;Introdução..."></textarea>
            <div class="linhas" id="contaLinhas" aria-live="polite"></div>
            <div class="ou">ou</div>
            <label class="sub" for="arquivo">📷 Foto ou PDF da folha escrita à mão (até 10 MB)</label><br>
            <input id="arquivo" type="file" name="arquivo" accept="image/*,application/pdf" capture="environment">
            <div style="margin-top:14px"><button class="bt" type="submit">Enviar para correção</button></div>
        </form>
        <?php endif; ?>
    </section>

    <section aria-labelledby="minhasTitulo">
        <h2 id="minhasTitulo">Minhas redações</h2>
        <?php if (!$minhas): ?><p class="sub">Nenhuma ainda. A primeira é a mais importante: mande hoje!</p><?php endif; ?>
        <div style="display:grid;gap:10px">
        <?php foreach ($minhas as $m): $r = redacao_obter($pdo, (int)$m['id']); $max = redacao_max_total($r['grade']); ?>
            <article class="minha">
                <div class="topo"><strong><?php echo rd_e($m['proposta'] ?: 'Tema livre'); ?></strong><span class="st <?php echo $m['status'] === 'corrigida' ? 'corrigida' : ''; ?>"><?php echo $m['status'] === 'corrigida' ? 'Corrigida' : 'Em correção'; ?></span></div>
                <small class="sub">Enviada em <?php echo rd_e(date('d/m/Y H:i', strtotime((string)$m['enviada_em']))); ?></small>
                <?php if ($m['status'] === 'corrigida'): ?>
                    <div class="nota"><?php echo redacao_fmt((float)$m['nota']); ?> <small class="sub">de <?php echo redacao_fmt($max); ?></small></div>
                    <?php foreach (array_values($r['grade']) as $i => $g): $f = $r['final'][$i] ?? null; ?>
                        <div class="crit"><span><?php echo rd_e($g['nome']); ?></span><strong><?php echo redacao_fmt((float)($f['nota'] ?? 0)); ?>/<?php echo redacao_fmt((float)$g['max']); ?></strong><?php if (!empty($f['comentario'])): ?><small><?php echo rd_e($f['comentario']); ?></small><?php endif; ?></div>
                    <?php endforeach; ?>
                    <?php if (trim((string)$m['comentario']) !== ''): ?><p><strong>Professor:</strong> <?php echo nl2br(rd_e($m['comentario'])); ?></p><?php endif; ?>
                    <?php if (!empty($m['audio'])): ?><audio controls preload="none" src="<?php echo rd_e($m['audio']); ?>"></audio><?php endif; ?>
                <?php else: ?>
                    <p class="sub">O professor corrige em até <?php echo REDACAO_PRAZO_HORAS; ?>h.</p>
                <?php endif; ?>
                <?php if (!empty($m['arquivo'])): ?><a href="<?php echo rd_e($m['arquivo']); ?>" target="_blank" rel="noopener" style="color:var(--ouro);font-size:13px">Ver o que enviei</a><?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
</main>
<script>
(function () {
    var t = document.getElementById('texto'), c = document.getElementById('contaLinhas');
    if (!t) { return; }
    function contar() { var palavras = (t.value.trim().match(/\S+/g) || []).length; c.textContent = palavras ? palavras + ' palavras · cerca de ' + Math.ceil(palavras / 10) + ' linhas na folha' : ''; }
    t.addEventListener('input', contar);
})();
</script>
</body>
</html>
