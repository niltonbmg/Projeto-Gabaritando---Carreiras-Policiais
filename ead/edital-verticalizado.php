<?php
// edital-verticalizado.php
// Edital verticalizado do cargo: por disciplina, cada assunto com complexidade, peso na prova,
// custo-benefício, horas e dica do Antigão. O aluno marca estudado/revisado/dominado.
// "Baixar PDF" gera a versão para impressão com a logo do Projeto Gabaritando.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/edital-jobs-lib.php';

$ehAdmin = !empty($_SESSION['admin_usuario_id']) || strtolower((string)($_SESSION['usuario_tipo'] ?? '')) === 'admin';
if (!$ehAdmin) {
    auth_require_login();
}
$pdo = db();
job_bootstrap($pdo);
$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$concursoId = (int)($_GET['concurso_id'] ?? 0);
$cargoId = (int)($_GET['cargo_id'] ?? 0);
if (!$concursoId || !$cargoId) {
    $esc = $usuarioId ? hack_escolha_aluno($pdo, $usuarioId) : null;
    if (!$esc) {
        header('Location: meu-concurso.php');
        exit;
    }
    $concursoId = (int)$esc['concurso_id'];
    $cargoId = (int)$esc['cargo_id'];
}
$st = $pdo->prepare('SELECT c.*, cg.nome AS cargo FROM concursos c JOIN concurso_cargos cg ON cg.concurso_id = c.id AND cg.id = :g WHERE c.id = :c');
$st->execute([':c' => $concursoId, ':g' => $cargoId]);
$conc = $st->fetch();
if (!$conc) {
    http_response_code(404);
    exit('Concurso não encontrado.');
}
$v = (json_decode((string)($conc['verticalizado'] ?? ''), true) ?: [])[(string)$cargoId] ?? null;
$marcas = [];
if ($usuarioId) {
    $stM = $pdo->prepare('SELECT edital_item_id, status FROM aluno_vertical WHERE usuario_id = :u');
    $stM->execute([':u' => $usuarioId]);
    $marcas = $stM->fetchAll(PDO::FETCH_KEY_PAIR);
}
$totalAss = 0;
$totalHoras = 0.0;
$feitos = 0;
foreach ((array)($v['disciplinas'] ?? []) as $d) {
    foreach ($d['assuntos'] as $a) {
        $totalAss++;
        $totalHoras += (float)$a['horas'];
        $feitos += isset($marcas[$a['id']]) ? 1 : 0;
    }
}
function ev_e($x): string
{
    return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
}
function ev_pontos(int $n, string $cor): string
{
    $h = '';
    for ($i = 1; $i <= 5; $i++) {
        $h .= '<i class="pt' . ($i <= $n ? ' on' : '') . '" style="--c:' . $cor . '"></i>';
    }
    return '<span class="pts" role="img" aria-label="' . $n . ' de 5">' . $h . '<b>' . $n . '/5</b></span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edital verticalizado · <?php echo ev_e($conc['cargo']); ?> | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.45}
        .ev{max-width:1100px;margin:0 auto;padding:20px 16px 70px;display:grid;gap:16px}
        .cab{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between}
        .cab img{height:54px}
        h1{font-size:clamp(20px,3.5vw,28px)}
        .sub{color:var(--suave);font-size:14px}
        .bt{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;background:var(--ouro);color:#111;text-decoration:none}
        .bt.linha{background:transparent;color:var(--texto);border:1px solid var(--linha)}
        .bt:focus-visible,select:focus-visible,.st button:focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
        .resumo{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .resumo div{background:var(--card);border:1px solid var(--linha);border-radius:12px;padding:10px 12px}
        .resumo span{display:block;font-size:11.5px;color:var(--suave);text-transform:uppercase;letter-spacing:.05em}
        .resumo strong{font-size:20px}
        .orient{background:linear-gradient(135deg,#221a06,#161618 60%);border:1px solid #4a3a10;border-radius:14px;padding:14px 16px}
        .orient h2{font-size:16px;margin-bottom:6px;color:var(--ouro)}
        .orient li{margin-left:18px;font-size:14.5px;margin-top:4px}
        .ctrl{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:14px}
        select{background:var(--card2);color:var(--texto);border:1px solid var(--linha);border-radius:8px;padding:8px;font:inherit}
        .disc{background:var(--card);border:1px solid var(--linha);border-radius:14px;overflow:hidden}
        .disc header{padding:12px 14px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;border-bottom:1px solid var(--linha)}
        .disc h2{font-size:17px}
        .disc header p{font-size:13.5px;color:#d4d4d8;flex-basis:100%}
        table{width:100%;border-collapse:collapse;font-size:14px}
        th{font-size:11.5px;color:var(--suave);text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:8px 10px}
        td{padding:9px 10px;border-top:1px solid var(--linha);vertical-align:top}
        td small{display:block;color:var(--suave);font-size:12.5px;margin-top:2px}
        .pts{display:inline-flex;gap:3px;align-items:center}.pts b{font-size:11.5px;color:var(--suave);margin-left:4px;font-weight:500}
        .pt{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.15)}
        .pt.on{background:var(--c)}
        .cb{font-weight:700;color:var(--ouro)}
        .st{display:flex;gap:4px;flex-wrap:wrap}
        .st button{border:1px solid var(--linha);background:transparent;color:var(--suave);border-radius:8px;padding:4px 8px;font:inherit;font-size:12px;cursor:pointer}
        .st button[aria-pressed="true"]{background:var(--ok);color:#06210f;border-color:var(--ok);font-weight:700}
        .print-only{display:none}
        @media(max-width:760px){.resumo{grid-template-columns:1fr 1fr}thead{display:none}tr{display:grid;grid-template-columns:1fr 1fr;padding:8px 0;border-top:1px solid var(--linha)}td{border:0;padding:4px 10px}td:first-child,td:last-child{grid-column:1/-1}}
        @media print{
            @page{margin:14mm}
            body{background:#fff;color:#111;font-size:11px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .ev{padding:0;max-width:none}
            .no-print,.st,.ctrl{display:none!important}
            .print-only{display:block}
            .disc,.resumo div,.orient{background:#fff;border-color:#ccc;break-inside:auto}
            .disc header{border-color:#ccc}
            td,th{border-color:#ddd;color:#111}
            td small,.sub,.resumo span,.disc header p{color:#444}
            .orient h2,.cb{color:#8a6300}
            .pt{background:#e5e5e5}
            .marca{position:fixed;top:45%;left:0;right:0;text-align:center;transform:rotate(-30deg);font-size:64px;font-weight:700;color:rgba(251,185,40,.10);z-index:-1}
            .rodape{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:9px;color:#666}
        }
    </style>
</head>
<body>
<?php if (!$ehAdmin && is_file(__DIR__ . '/includes/barra-superior.php')): ?><div class="no-print"><?php include_once __DIR__ . '/includes/barra-superior.php'; ?></div><?php endif; ?>
<div class="print-only marca" aria-hidden="true">PROJETO GABARITANDO</div>
<div class="print-only rodape">Edital verticalizado · Projeto Gabaritando · material do aluno, venda proibida</div>
<main class="ev">
    <div class="cab">
        <div style="display:flex;gap:14px;align-items:center">
            <img src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="Projeto Gabaritando">
            <div><h1>📋 Edital verticalizado</h1><p class="sub"><?php echo ev_e($conc['cargo']); ?> · <?php echo ev_e($conc['nome']); ?> · <?php echo $conc['situacao'] === 'aberto' ? 'edital aberto' : 'com base no ' . ev_e($conc['base_edital'] ?: 'último edital'); ?></p></div>
        </div>
        <div class="no-print" style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="bt linha" href="meu-concurso.php">← Meu concurso</a>
            <button class="bt" type="button" onclick="window.print()">⬇ Baixar PDF</button>
        </div>
    </div>
    <?php if (!$v): ?>
        <p class="sub">O edital verticalizado deste cargo ainda está sendo preparado pela equipe.</p>
    <?php else: ?>
    <section class="resumo" aria-label="Resumo">
        <div><span>Disciplinas</span><strong><?php echo count($v['disciplinas']); ?></strong></div>
        <div><span>Assuntos</span><strong><?php echo $totalAss; ?></strong></div>
        <div><span>Horas estimadas</span><strong><?php echo rtrim(rtrim(number_format($totalHoras, 1, ',', ''), '0'), ','); ?>h</strong></div>
        <div><span>Você já marcou</span><strong id="contaFeitos"><?php echo $feitos; ?></strong></div>
    </section>
    <?php if (!empty($v['orientacoes'])): ?>
    <section class="orient"><h2>🎯 Antigão: melhor custo-benefício para a prova</h2><ul><?php foreach ($v['orientacoes'] as $o): ?><li><?php echo ev_e($o); ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <div class="ctrl no-print"><label for="ordem">Ordenar assuntos por</label>
        <select id="ordem"><option value="edital">ordem do edital</option><option value="cb">melhor custo-benefício</option><option value="peso">mais cobrado</option><option value="cx">mais fácil primeiro</option></select>
        <span class="sub">· Complexidade, peso e custo-benefício vão de 1 a 5.</span></div>
    <?php foreach ($v['disciplinas'] as $mat => $d): $nAss = count($d['assuntos']); $h = array_sum(array_column($d['assuntos'], 'horas')); ?>
        <section class="disc" aria-label="<?php echo ev_e($mat); ?>">
            <header><h2><?php echo ev_e($mat); ?></h2><span class="sub"><?php echo $nAss; ?> assunto(s) · ~<?php echo rtrim(rtrim(number_format((float)$h, 1, ',', ''), '0'), ','); ?>h · complexidade média <?php echo number_format(array_sum(array_column($d['assuntos'], 'complexidade')) / max(1, $nAss), 1, ',', ''); ?></span>
                <?php if (!empty($d['estrategia'])): ?><p>💡 <?php echo ev_e($d['estrategia']); ?></p><?php endif; ?></header>
            <table><thead><tr><th>Assunto</th><th>Complexidade</th><th>Peso na prova</th><th>Custo-benefício</th><th>Horas</th><th class="no-print">Meu status</th></tr></thead>
            <tbody>
            <?php foreach ($d['assuntos'] as $k => $a): $m = $marcas[$a['id']] ?? ''; ?>
                <tr data-ordem="<?php echo $k; ?>" data-cb="<?php echo (int)$a['custo_beneficio']; ?>" data-peso="<?php echo (int)$a['peso']; ?>" data-cx="<?php echo (int)$a['complexidade']; ?>">
                    <td><strong><?php echo ev_e($a['assunto']); ?></strong><?php if (!empty($a['questoes'])): ?><small><?php echo (int)$a['questoes']; ?> questão(ões) na última prova</small><?php endif; ?><?php if (!empty($a['dica'])): ?><small>💡 <?php echo ev_e($a['dica']); ?></small><?php endif; ?></td>
                    <td><?php echo ev_pontos((int)$a['complexidade'], '#ff8a5b'); ?></td>
                    <td><?php echo ev_pontos((int)$a['peso'], '#60a5fa'); ?></td>
                    <td><?php echo ev_pontos((int)$a['custo_beneficio'], '#fbb928'); ?><?php if ((int)$a['custo_beneficio'] >= 5): ?> <span class="cb">TOP</span><?php endif; ?></td>
                    <td><?php echo rtrim(rtrim(number_format((float)$a['horas'], 1, ',', ''), '0'), ','); ?>h</td>
                    <td class="no-print"><div class="st" role="group" aria-label="Status de <?php echo ev_e($a['assunto']); ?>" data-item="<?php echo (int)$a['id']; ?>">
                        <?php foreach (['estudado' => 'Estudei', 'revisado' => 'Revisei', 'dominado' => 'Dominei'] as $sv => $sl): ?><button type="button" data-s="<?php echo $sv; ?>" aria-pressed="<?php echo $m === $sv ? 'true' : 'false'; ?>"><?php echo $sl; ?></button><?php endforeach; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </section>
    <?php endforeach; ?>
    <?php endif; ?>
</main>
<script>
(function () {
    var conta = document.getElementById('contaFeitos');
    document.querySelectorAll('.st').forEach(function (g) {
        g.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) { return; }
            var novo = b.getAttribute('aria-pressed') === 'true' ? '' : b.dataset.s;
            var antes = !!g.querySelector('[aria-pressed="true"]');
            g.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b && novo ? 'true' : 'false'); });
            if (conta) { conta.textContent = +conta.textContent + ((novo ? 1 : 0) - (antes ? 1 : 0)); }
            fetch('api/vertical.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({item_id: +g.dataset.item, status: novo})});
        });
    });
    var sel = document.getElementById('ordem');
    if (sel) {
        sel.addEventListener('change', function () {
            var k = sel.value;
            document.querySelectorAll('.disc tbody').forEach(function (tb) {
                Array.from(tb.rows).sort(function (a, b) {
                    if (k === 'edital') { return a.dataset.ordem - b.dataset.ordem; }
                    if (k === 'cx') { return a.dataset.cx - b.dataset.cx; }
                    return b.dataset[k] - a.dataset[k];
                }).forEach(function (r) { tb.appendChild(r); });
            });
        });
    }
})();
</script>
</body>
</html>
