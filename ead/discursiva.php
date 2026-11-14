<?php
// discursiva.php — Treino de prova discursiva com o Antigão: ele cria a questão no formato do edital do aluno,
// o aluno responde e o Antigão corrige pelo critério do edital (nota, o que acertou, o que melhorar, resposta modelo).
// Custo: 1 pedido de texto para criar + 1 para corrigir. Limite por aluno por dia.
declare(strict_types=1);

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-consumo-lib.php';
require_once __DIR__ . '/includes/provas-escritas-lib.php';
require_once __DIR__ . '/includes/texto-limpo-lib.php';
require_once __DIR__ . '/includes/edital-lib.php'; // edital_json_do_texto

const DISC_LIMITE_DIA = 3; // questões novas por aluno por dia

$uid = (int)auth_user_id();
$pdo = db();
require_once __DIR__ . '/includes/schema-cache.php';
if (!gab_schema_ok('discursiva_tabela', __FILE__)) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS discursiva_treinos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id BIGINT UNSIGNED NOT NULL,
        materia VARCHAR(160) NOT NULL DEFAULT '',
        pergunta TEXT NOT NULL,
        linhas INT NULL,
        pontos DECIMAL(6,2) NULL,
        resposta MEDIUMTEXT NULL,
        correcao MEDIUMTEXT NULL,
        nota DECIMAL(6,2) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        corrigido_em DATETIME NULL,
        KEY idx_disc_usuario (usuario_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
final class DiscAviso extends RuntimeException {} // mensagens nossas, mostradas ao aluno como estão
function dc_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dc_n($v): string { return $v === null ? '' : rtrim(rtrim(number_format((float)$v, 1, ',', ''), '0'), ','); }

$pe = pe_do_aluno($pdo, $uid);
$disc = $pe['discursiva'] ?? pe_vazio()['discursiva'];
$materias = $disc['materias'];
if (!$materias) { // edital sem lista de matérias da discursiva: as matérias do concurso do aluno
    try {
        $st = $pdo->prepare('SELECT DISTINCT m.nome FROM aluno_concurso ac JOIN edital_itens ei ON ei.concurso_id = ac.concurso_id JOIN admin_materias m ON m.id = ei.materia_id WHERE ac.usuario_id = :u ORDER BY m.nome');
        $st->execute([':u' => $uid]);
        $materias = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
    }
}
$hojeQtd = static function () use ($pdo, $uid): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM discursiva_treinos WHERE usuario_id = :u AND criado_em >= CURDATE()');
    $st->execute([':u' => $uid]);
    return (int)$st->fetchColumn();
};
$contexto = static function () use ($pe, $disc): string {
    return ($pe ? "Concurso: {$pe['concurso']}" . ($pe['cargo'] ? " · cargo {$pe['cargo']}" : '') . ".\n" : '')
        . 'Prova discursiva do edital: ' . pe_resumo(['discursiva' => $disc], 'discursiva') . ".\n"
        . ($disc['observacao'] !== '' ? 'Observação do edital: ' . $disc['observacao'] . "\n" : '');
};
$texto = static function (array $r): string {
    $t = '';
    foreach ((array)($r['candidates'][0]['content']['parts'] ?? []) as $p) {
        $t .= (string)($p['text'] ?? '');
    }
    return $t;
};
$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'nova') {
            if ($hojeQtd() >= DISC_LIMITE_DIA) {
                throw new DiscAviso('Você já treinou ' . DISC_LIMITE_DIA . ' questões hoje. Amanhã tem mais, combatente!');
            }
            $materia = mb_substr(trim((string)($_POST['materia'] ?? '')), 0, 160);
            if (!ia_controle_ligado('discursiva')) {
                throw new DiscAviso('O treino de discursiva está pausado pelo mentor no momento.');
            }
            ia_consumo_inicio('discursiva', $uid);
            $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' =>
                "Você é o ANTIGÃO, mentor do Projeto Gabaritando (concursos policiais e militares da Bahia).\n" . $contexto()
                . "Crie UMA questão discursiva inédita de " . ($materia !== '' ? $materia : 'uma matéria do edital') . " no formato e no nível dessa prova (estilo da banca), com comando claro, como cairia de verdade.\n"
                . "Responda SOMENTE em JSON: {\"pergunta\":\"enunciado completo\",\"linhas\":" . (int)($disc['linhas'] ?? 15) . ",\"pontos\":" . (float)($disc['pontos'] ?? 10) . ",\"espelho\":\"o que a resposta precisa conter para nota máxima (para a correção)\"}"]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.8]], 60);
            $j = edital_json_do_texto($texto($r)) ?? [];
            $pergunta = trim((string)($j['pergunta'] ?? ''));
            if ($pergunta === '') {
                throw new DiscAviso('O Antigão não conseguiu criar a questão agora. Tente de novo em instantes.');
            }
            $pdo->prepare('INSERT INTO discursiva_treinos (usuario_id, materia, pergunta, linhas, pontos, correcao) VALUES (:u, :m, :p, :l, :pt, :c)')
                ->execute([':u' => $uid, ':m' => $materia, ':p' => gab_sem_latex($pergunta), ':l' => max(5, min(60, (int)($j['linhas'] ?? 15))), ':pt' => max(1, (float)($j['pontos'] ?? 10)),
                    ':c' => json_encode(['espelho' => (string)($j['espelho'] ?? '')], JSON_UNESCAPED_UNICODE)]);
            header('Location: discursiva.php?id=' . (int)$pdo->lastInsertId() . '#q');
            exit;
        }
        if ($acao === 'responder') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT * FROM discursiva_treinos WHERE id = :id AND usuario_id = :u');
            $st->execute([':id' => $id, ':u' => $uid]);
            $q = $st->fetch(PDO::FETCH_ASSOC);
            $resp = trim(mb_substr((string)($_POST['resposta'] ?? ''), 0, 8000));
            if (!$q || $q['nota'] !== null) {
                throw new DiscAviso('Questão não encontrada ou já corrigida.');
            }
            if (mb_strlen($resp) < 40) {
                throw new DiscAviso('Escreva a sua resposta completa antes de enviar (como na prova).');
            }
            $pdo->prepare('UPDATE discursiva_treinos SET resposta = :r WHERE id = :id')->execute([':r' => $resp, ':id' => $id]);
            $espelho = (string)((json_decode((string)$q['correcao'], true) ?: [])['espelho'] ?? '');
            ia_consumo_inicio('discursiva', $uid);
            $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' =>
                "Você é o ANTIGÃO, corretor experiente de provas discursivas de concursos policiais e militares da Bahia. Corrija com rigor de banca, mas explique simples e motivando (chame de \"combatente\").\n" . $contexto()
                . "QUESTÃO (vale " . dc_n($q['pontos']) . " pontos, até " . (int)$q['linhas'] . " linhas):\n" . $q['pergunta'] . "\n\nESPELHO: " . $espelho . "\n\nRESPOSTA DO ALUNO:\n" . $resp . "\n\n"
                . "Avalie conteúdo (acerto técnico e completude do espelho), estrutura e clareza, e língua portuguesa. Desconte se passar do limite de linhas.\n"
                . "Responda SOMENTE em JSON: {\"nota\":0,\"acertos\":[\"o que ficou bom\"],\"melhorar\":[\"o que faltou ou errou, específico\"],\"resposta_modelo\":\"uma resposta nota máxima, no tamanho da prova\",\"dica\":\"1 frase para a próxima\"}"]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2]], 90);
            $j = edital_json_do_texto($texto($r)) ?? [];
            if (!isset($j['nota'])) {
                throw new DiscAviso('Sua resposta ficou salva, mas o Antigão não conseguiu corrigir agora. Toque em "Corrigir" de novo mais tarde.');
            }
            $nota = max(0, min((float)$q['pontos'], (float)$j['nota']));
            $j['espelho'] = $espelho;
            foreach (['resposta_modelo', 'dica'] as $k) {
                $j[$k] = gab_sem_latex((string)($j[$k] ?? ''));
            }
            $pdo->prepare('UPDATE discursiva_treinos SET correcao = :c, nota = :n, corrigido_em = NOW() WHERE id = :id')
                ->execute([':c' => json_encode($j, JSON_UNESCAPED_UNICODE), ':n' => $nota, ':id' => $id]);
            header('Location: discursiva.php?id=' . $id . '#q');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e instanceof DiscAviso ? $e->getMessage() : 'O Antigão está sem fôlego agora (limite do dia da IA). Tente mais tarde: sua resposta fica salva.';
        error_log('discursiva: ' . $e->getMessage());
    }
}

$atual = null;
if (!empty($_GET['id'])) {
    $st = $pdo->prepare('SELECT * FROM discursiva_treinos WHERE id = :id AND usuario_id = :u');
    $st->execute([':id' => (int)$_GET['id'], ':u' => $uid]);
    $atual = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$hist = $pdo->prepare('SELECT id, materia, LEFT(pergunta, 120) p, nota, pontos, criado_em FROM discursiva_treinos WHERE usuario_id = :u ORDER BY id DESC LIMIT 20');
$hist->execute([':u' => $uid]);
$hist = $hist->fetchAll(PDO::FETCH_ASSOC);
$restam = max(0, DISC_LIMITE_DIA - $hojeQtd());
$corr = $atual ? (json_decode((string)$atual['correcao'], true) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discursiva | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#4ade80;--erro:#ff8a80}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.55}
        .dc{max-width:860px;margin:0 auto;padding:24px 16px 72px;display:grid;gap:14px}
        h1{font-size:clamp(22px,4vw,30px)}h2{font-size:18px;margin-bottom:8px}.sub{color:var(--suave);font-size:14px}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:16px}
        .edital{border-color:#4a3a10;background:#1c1708}
        select,textarea{width:100%;border-radius:12px;border:1px solid var(--linha);background:var(--card2);color:var(--texto);padding:12px;font:inherit}
        textarea{min-height:260px;line-height:1.9;background-image:repeating-linear-gradient(transparent,transparent 29.5px,#2c2c31 30px)}
        .bt{border:0;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--ouro);color:#111;text-decoration:none;display:inline-block}
        .bt:disabled{opacity:.5;cursor:default}
        .linha{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:space-between}
        .erro{background:#3a1d1a;color:#ffb4a8;border-radius:12px;padding:10px 14px}
        .pergunta{white-space:pre-line;font-size:16px}
        .nota{font-size:34px;font-weight:700;color:var(--ouro)}
        ul{padding-left:20px;display:grid;gap:4px}
        .ok li::marker{color:var(--ok)}.ruim li::marker{color:var(--erro)}
        .modelo{white-space:pre-line;background:var(--card2);border-radius:12px;padding:12px}
        .hist a{display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-top:1px solid var(--linha);color:var(--texto);text-decoration:none;font-size:14px}
        .hist a:hover{color:var(--ouro)}
        :focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
    </style>
</head>
<body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="dc">
    <div>
        <h1>📝 Discursiva · treino com o Antigão</h1>
        <p class="sub">O Antigão cria a questão no formato da sua prova, você responde como no dia e ele corrige pelo critério do edital, com nota, o que melhorar e uma resposta modelo.</p>
    </div>
    <div class="card edital">
        <?php if ($pe && ($disc['tem'] ?? null) === true): ?>
            📋 Na sua prova (<?php echo dc_e($pe['cargo'] ?: $pe['concurso']); ?>): <strong><?php echo dc_e(pe_resumo(['discursiva' => $disc], 'discursiva')); ?></strong>
        <?php elseif ($pe && ($disc['tem'] ?? null) === false): ?>
            ℹ️ O edital do seu concurso não tem prova discursiva. Pode treinar, mas o seu tempo rende mais nas questões objetivas.
        <?php else: ?>
            ℹ️ <?php echo $pe ? 'O edital do seu concurso ainda não foi conferido quanto à discursiva.' : 'Escolha seu concurso em <a href="meu-concurso.php" style="color:var(--ouro)">Meu concurso</a> para treinar no formato certo.'; ?>
        <?php endif; ?>
    </div>
    <?php if ($erro !== ''): ?><div class="erro" role="alert"><?php echo dc_e($erro); ?></div><?php endif; ?>

    <?php if ($atual): ?>
    <section class="card" id="q" aria-labelledby="qT">
        <h2 id="qT"><?php echo dc_e($atual['materia'] ?: 'Questão discursiva'); ?> · vale <?php echo dc_n($atual['pontos']); ?> pontos · até <?php echo (int)$atual['linhas']; ?> linhas</h2>
        <p class="pergunta"><?php echo dc_e($atual['pergunta']); ?></p>
    </section>
        <?php if ($atual['nota'] === null): ?>
        <form method="post" class="card" onsubmit="this.querySelector('.bt').disabled=true;this.querySelector('.bt').textContent='O Antigão está corrigindo…'">
            <input type="hidden" name="acao" value="responder"><input type="hidden" name="id" value="<?php echo (int)$atual['id']; ?>">
            <label for="resp"><strong>Sua resposta</strong> <span class="sub">(escreva como na folha da prova)</span></label>
            <textarea id="resp" name="resposta" maxlength="8000" required><?php echo dc_e((string)$atual['resposta']); ?></textarea>
            <div class="linha" style="margin-top:8px"><span class="sub" id="cont">0 linhas (~70 caracteres por linha)</span><button class="bt" type="submit">Enviar para o Antigão corrigir</button></div>
        </form>
        <script>(function(){var t=document.getElementById('resp'),c=document.getElementById('cont'),m=<?php echo (int)$atual['linhas']; ?>;function u(){var n=t.value.split('\n').reduce(function(s,l){return s+Math.max(1,Math.ceil(l.length/70));},0);if(!t.value)n=0;c.textContent=n+' de '+m+' linhas (~70 caracteres por linha)';c.style.color=n>m?'#ff8a80':'';}t.addEventListener('input',u);u();})();</script>
        <?php else: ?>
        <section class="card" aria-labelledby="cT">
            <div class="linha"><h2 id="cT">Correção do Antigão</h2><span class="nota"><?php echo dc_n($atual['nota']); ?> <small style="font-size:16px;color:var(--suave)">de <?php echo dc_n($atual['pontos']); ?></small></span></div>
            <?php if (!empty($corr['acertos'])): ?><p><strong>✅ O que ficou bom</strong></p><ul class="ok"><?php foreach ((array)$corr['acertos'] as $x): ?><li><?php echo dc_e($x); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (!empty($corr['melhorar'])): ?><p style="margin-top:10px"><strong>🎯 O que melhorar</strong></p><ul class="ruim"><?php foreach ((array)$corr['melhorar'] as $x): ?><li><?php echo dc_e($x); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if (!empty($corr['dica'])): ?><p style="margin-top:10px">💡 <?php echo dc_e($corr['dica']); ?></p><?php endif; ?>
            <details style="margin-top:12px"><summary style="cursor:pointer"><strong>Ver sua resposta</strong></summary><p class="modelo" style="margin-top:8px"><?php echo dc_e((string)$atual['resposta']); ?></p></details>
            <?php if (!empty($corr['resposta_modelo'])): ?><details style="margin-top:8px" open><summary style="cursor:pointer"><strong>📘 Resposta modelo (nota máxima)</strong></summary><p class="modelo" style="margin-top:8px"><?php echo dc_e($corr['resposta_modelo']); ?></p></details><?php endif; ?>
            <p class="sub" style="margin-top:10px">Correção feita por inteligência artificial para treino. A nota da banca pode variar.</p>
        </section>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" class="card" onsubmit="this.querySelector('.bt').disabled=true;this.querySelector('.bt').textContent='O Antigão está criando a questão…'">
        <input type="hidden" name="acao" value="nova">
        <h2>Nova questão</h2>
        <div class="linha">
            <select name="materia" aria-label="Matéria" style="flex:1;min-width:220px"><option value="">Qualquer matéria do edital</option><?php foreach ($materias as $m): ?><option><?php echo dc_e($m); ?></option><?php endforeach; ?></select>
            <button class="bt" type="submit" <?php echo $restam ? '' : 'disabled'; ?>>Antigão, manda uma questão</button>
        </div>
        <p class="sub" style="margin-top:6px"><?php echo $restam; ?> de <?php echo DISC_LIMITE_DIA; ?> questão(ões) restante(s) hoje.</p>
    </form>

    <?php if ($hist): ?>
    <section class="card hist" aria-labelledby="hT"><h2 id="hT">Seus treinos</h2>
        <?php foreach ($hist as $h): ?><a href="discursiva.php?id=<?php echo (int)$h['id']; ?>#q"><span><?php echo dc_e(($h['materia'] ? $h['materia'] . ' · ' : '') . $h['p']); ?>…</span><strong><?php echo $h['nota'] === null ? 'responder' : dc_n($h['nota']) . '/' . dc_n($h['pontos']); ?></strong></a><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <p class="sub" style="font-size:12px">🔒 Questões e correções feitas por inteligência artificial (Google), sem seu nome ou e-mail. <a href="privacidade.php" style="color:var(--ouro)">Privacidade</a>.</p>
</main>
</body>
</html>
