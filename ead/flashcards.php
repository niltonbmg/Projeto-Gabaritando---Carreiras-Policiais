<?php
// flashcards.php
// Revisão inteligente (Método META): cada questão que o aluno ERROU vira um cartão e volta
// em 1, 7, 30 e 90 dias até ele dominar. "Não lembrei" recomeça o ciclo.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';

const FC_INTERVALOS = [1, 7, 30, 90]; // dias depois de cada "Lembrei"

$uid = (int)auth_user_id();
$pdo = db();
require_once __DIR__ . '/includes/schema-cache.php';
if (!gab_schema_ok('flashcards_tabela', __FILE__)) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS flashcard_revisao (
        usuario_id BIGINT UNSIGNED NOT NULL, questao_id INT UNSIGNED NOT NULL, nivel TINYINT UNSIGNED NOT NULL DEFAULT 0,
        proxima_em DATE NOT NULL, revisoes INT UNSIGNED NOT NULL DEFAULT 0, atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (usuario_id, questao_id), KEY idx_fc_proxima (usuario_id, proxima_em)
    ) ENGINE=InnoDB');
}
function fc_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Toda questão errada entra no baralho (uma vez), para revisar a partir de hoje.
try {
    $pdo->prepare('INSERT IGNORE INTO flashcard_revisao (usuario_id, questao_id, nivel, proxima_em)
                   SELECT usuario_id, questao_id, 0, CURDATE() FROM banco_questoes_respostas_usuario WHERE usuario_id = :u AND correta = 0')->execute([':u' => $uid]);
} catch (Throwable $e) {
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $qid = (int)($_POST['questao_id'] ?? 0);
    $st = $pdo->prepare('SELECT nivel FROM flashcard_revisao WHERE usuario_id = :u AND questao_id = :q');
    $st->execute([':u' => $uid, ':q' => $qid]);
    $nivel = $st->fetchColumn();
    if ($nivel !== false) {
        $lembrei = ($_POST['r'] ?? '') === 'sim';
        $novo = $lembrei ? min(count(FC_INTERVALOS), (int)$nivel + 1) : 0;
        $dias = $lembrei ? FC_INTERVALOS[$novo - 1] : 1;
        $pdo->prepare('UPDATE flashcard_revisao SET nivel = :n, proxima_em = DATE_ADD(CURDATE(), INTERVAL :d DAY), revisoes = revisoes + 1 WHERE usuario_id = :u AND questao_id = :q')
            ->execute([':n' => $novo, ':d' => $dias, ':u' => $uid, ':q' => $qid]);
    }
    header('Location: flashcards.php' . (isset($_POST['materia']) && $_POST['materia'] !== '' ? '?materia=' . (int)$_POST['materia'] : ''));
    exit;
}

$materia = (int)($_GET['materia'] ?? 0);
$fm = $materia > 0 ? ' AND q.materia_id = ' . $materia : '';
$cont = $pdo->prepare("SELECT SUM(f.proxima_em <= CURDATE() AND f.nivel < :max) hoje, SUM(f.nivel > 0 AND f.nivel < :max2) andamento, SUM(f.nivel >= :max3) dominadas
                         FROM flashcard_revisao f JOIN admin_banco_questoes q ON q.id = f.questao_id WHERE f.usuario_id = :u $fm");
$cont->execute([':u' => $uid, ':max' => count(FC_INTERVALOS), ':max2' => count(FC_INTERVALOS), ':max3' => count(FC_INTERVALOS)]);
$c = $cont->fetch() ?: [];
$materias = $pdo->prepare("SELECT m.id, m.nome, SUM(f.proxima_em <= CURDATE() AND f.nivel < :max) hoje FROM flashcard_revisao f JOIN admin_banco_questoes q ON q.id = f.questao_id
                             JOIN admin_materias m ON m.id = q.materia_id WHERE f.usuario_id = :u GROUP BY m.id, m.nome ORDER BY hoje DESC, m.nome");
$materias->execute([':u' => $uid, ':max' => count(FC_INTERVALOS)]);
$materias = $materias->fetchAll();
$card = $pdo->prepare("SELECT f.questao_id, f.nivel, q.enunciado, q.explicacao, m.nome materia FROM flashcard_revisao f JOIN admin_banco_questoes q ON q.id = f.questao_id
                         LEFT JOIN admin_materias m ON m.id = q.materia_id WHERE f.usuario_id = :u AND f.proxima_em <= CURDATE() AND f.nivel < :max $fm
                         ORDER BY f.nivel, f.proxima_em, f.questao_id LIMIT 1");
$card->execute([':u' => $uid, ':max' => count(FC_INTERVALOS)]);
$card = $card->fetch();
$certa = '';
if ($card) {
    $o = $pdo->prepare('SELECT letra, texto FROM admin_banco_questao_opcoes WHERE questao_id = :q AND correta = 1 LIMIT 1');
    $o->execute([':q' => $card['questao_id']]);
    $oc = $o->fetch();
    $certa = $oc ? trim(($oc['letra'] ? $oc['letra'] . ') ' : '') . $oc['texto']) : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashcards | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a;--erro:#ff6b5b}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .fc{max-width:860px;margin:0 auto;padding:24px 16px 72px;display:grid;gap:16px}
        h1{font-size:clamp(22px,4vw,30px)}.sub{color:var(--suave);font-size:14px}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:16px}
        .nums{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.nums div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:12px;text-align:center}
        .nums strong{display:block;font-size:26px;color:var(--ouro)}.nums span{font-size:12px;color:var(--suave)}
        .mats{display:flex;flex-wrap:wrap;gap:8px}.mats a{border:1px solid var(--linha);border-radius:99px;padding:6px 12px;color:var(--texto);text-decoration:none;font-size:13px}
        .mats a.on{border-color:var(--ouro);color:var(--ouro)}
        .frente{font-size:16px;white-space:pre-line}.verso{display:none;margin-top:14px;border-top:1px dashed var(--linha);padding-top:14px}
        .verso.on{display:block}.certa{color:var(--ok);font-weight:700}
        .bts{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
        .bt{border:0;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--ouro);color:#111}
        .bt.nao{background:#3a1d1a;color:#ffb4a8}.bt.sim{background:#173d27;color:#b7f3cf}
        .tag{font-size:12px;color:var(--suave)}
    </style>
</head>
<body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="fc">
    <div>
        <h1>🃏 Flashcards · revisão inteligente</h1>
        <p class="sub">Cada questão que você erra vira um cartão. Ele volta em 1, 7, 30 e 90 dias. Lembrou? Ele vai mais para frente. Não lembrou? Recomeça amanhã. Assim o erro de hoje vira acerto na prova.</p>
    </div>
    <div class="nums">
        <div><strong><?php echo (int)($c['hoje'] ?? 0); ?></strong><span>para revisar hoje</span></div>
        <div><strong><?php echo (int)($c['andamento'] ?? 0); ?></strong><span>em fixação</span></div>
        <div><strong><?php echo (int)($c['dominadas'] ?? 0); ?></strong><span>dominadas ✅</span></div>
    </div>
    <?php if ($materias): ?>
    <div class="mats"><a href="flashcards.php" class="<?php echo $materia ? '' : 'on'; ?>">Todas</a>
        <?php foreach ($materias as $m): ?><a href="?materia=<?php echo (int)$m['id']; ?>" class="<?php echo $materia === (int)$m['id'] ? 'on' : ''; ?>"><?php echo fc_e($m['nome']); ?><?php echo (int)$m['hoje'] ? ' · ' . (int)$m['hoje'] : ''; ?></a><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($card): ?>
        <section class="card">
            <p class="tag"><?php echo fc_e($card['materia'] ?: 'Questão'); ?> · revisão <?php echo (int)$card['nivel'] + 1; ?> de <?php echo count(FC_INTERVALOS); ?></p>
            <div class="frente"><?php echo fc_e(trim(html_entity_decode(strip_tags((string)$card['enunciado']), ENT_QUOTES, 'UTF-8'))); ?></div>
            <div class="bts" id="mostrarBox"><button class="bt" type="button" onclick="document.getElementById('verso').classList.add('on');document.getElementById('mostrarBox').style.display='none'">Pensei na resposta · mostrar</button></div>
            <div class="verso" id="verso">
                <?php if ($certa !== ''): ?><p class="certa">✔ <?php echo fc_e(strip_tags($certa)); ?></p><?php endif; ?>
                <?php if (trim(strip_tags((string)$card['explicacao'])) !== ''): ?><p class="sub" style="margin-top:8px;white-space:pre-line"><?php echo fc_e(trim(html_entity_decode(strip_tags((string)$card['explicacao']), ENT_QUOTES, 'UTF-8'))); ?></p><?php endif; ?>
                <form method="post" class="bts">
                    <input type="hidden" name="questao_id" value="<?php echo (int)$card['questao_id']; ?>"><input type="hidden" name="materia" value="<?php echo $materia ?: ''; ?>">
                    <button class="bt nao" name="r" value="nao">✗ Não lembrei (volta amanhã)</button>
                    <button class="bt sim" name="r" value="sim">✓ Lembrei (próxima em <?php echo FC_INTERVALOS[min((int)$card['nivel'], count(FC_INTERVALOS) - 1)]; ?> dia<?php echo FC_INTERVALOS[min((int)$card['nivel'], count(FC_INTERVALOS) - 1)] > 1 ? 's' : ''; ?>)</button>
                </form>
            </div>
        </section>
    <?php else: ?>
        <section class="card"><p><strong>Nada para revisar agora. 👊</strong></p><p class="sub" style="margin-top:6px">Os cartões aparecem quando você erra questões no Banco de questões ou no Desafio do Dia. Siga a <a href="meu-plano.php" style="color:var(--ouro)">Missão do dia</a>.</p></section>
    <?php endif; ?>
</main>
</body>
</html>
