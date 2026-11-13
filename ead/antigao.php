<?php
// antigao.php
// 🎖 Antigão tira-dúvidas DENTRO da plataforma: sabe o concurso, o cargo, a fase da jornada e onde o aluno erra.
// Limite por aluno por dia (controle de custo) e respeita o Controle rígido (tipo 'duvidas').
// LGPD: a pergunta vai para a IA do Google SEM nome/e-mail do aluno; o histórico fica só para o próprio aluno.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/podcast-lib.php';
require_once __DIR__ . '/includes/ia-consumo-lib.php';
require_once __DIR__ . '/includes/edital-hack-lib.php';

const ANTIGAO_LIMITE_DIA = 15; // perguntas por aluno por dia

$uid = (int)auth_user_id();
$pdo = db();
require_once __DIR__ . '/includes/schema-cache.php';
if (!gab_schema_ok('antigao_tabela', __FILE__)) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS antigao_conversas (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, usuario_id BIGINT UNSIGNED NOT NULL, quem ENUM('aluno','antigao') NOT NULL,
        texto TEXT NOT NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_ant (usuario_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ant_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$esc = hack_escolha_aluno($pdo, $uid);
$hojeQtd = static function () use ($pdo, $uid): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM antigao_conversas WHERE usuario_id = :u AND quem = 'aluno' AND criado_em >= CURDATE()");
    $st->execute([':u' => $uid]);
    return (int)$st->fetchColumn();
};
$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pergunta = trim(mb_substr((string)($_POST['pergunta'] ?? ''), 0, 1200));
    if (($_POST['acao'] ?? '') === 'limpar') {
        $pdo->prepare('DELETE FROM antigao_conversas WHERE usuario_id = :u')->execute([':u' => $uid]);
        header('Location: antigao.php');
        exit;
    }
    if ($pergunta === '') {
        $erro = 'Escreva sua dúvida.';
    } elseif ($hojeQtd() >= ANTIGAO_LIMITE_DIA) {
        $erro = 'Você já fez ' . ANTIGAO_LIMITE_DIA . ' perguntas hoje. Amanhã tem mais! Enquanto isso, poste no fórum de dúvidas: o mentor responde.';
    } elseif (!ia_controle_ligado('duvidas')) {
        $erro = 'O Antigão está em manutenção agora. Poste sua dúvida no fórum: o mentor responde.';
    } else {
        $pdo->prepare("INSERT INTO antigao_conversas (usuario_id, quem, texto) VALUES (:u, 'aluno', :t)")->execute([':u' => $uid, ':t' => $pergunta]);
        // Contexto: o concurso do aluno, matérias onde mais erra e as últimas mensagens (nada de nome/e-mail).
        $fracas = '';
        try {
            $st = $pdo->prepare('SELECT m.nome, SUM(r.correta = 0) erros, COUNT(*) total FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id
                                   JOIN admin_materias m ON m.id = q.materia_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u GROUP BY m.id, m.nome HAVING total >= 3 ORDER BY erros / total DESC LIMIT 3');
            $st->execute([':u' => $uid]);
            $fracas = implode(', ', array_map(static fn($x) => $x['nome'] . ' (' . round($x['erros'] / max(1, $x['total']) * 100) . '% de erro)', $st->fetchAll()));
        } catch (Throwable $e) {
        }
        $hist = $pdo->prepare('SELECT quem, texto FROM antigao_conversas WHERE usuario_id = :u ORDER BY id DESC LIMIT 8');
        $hist->execute([':u' => $uid]);
        $conteudo = [];
        foreach (array_reverse($hist->fetchAll()) as $m) {
            $conteudo[] = ['role' => $m['quem'] === 'aluno' ? 'user' : 'model', 'parts' => [['text' => $m['texto']]]];
        }
        $sistema = "Você é o ANTIGÃO, mentor veterano do Projeto Gabaritando, cursinho para carreiras policiais e militares. Chama o aluno de \"combatente\". Fala simples, direto, calmo e motivador.\n"
            . ($esc ? "O aluno estuda para: {$esc['concurso']} · cargo {$esc['cargo']}" . ($esc['data_prova'] ? ' · prova em ' . date('d/m/Y', strtotime((string)$esc['data_prova'])) : '') . ".\n" : "O aluno ainda não escolheu o concurso: no fim, sugira escolher em \"Meu concurso\".\n")
            . ($fracas !== '' ? "Matérias em que ele mais erra: $fracas.\n" : '')
            . "Regras: responda a dúvida de estudo com explicação curta, exemplo e uma pegadinha de prova quando couber. Seja fiel à lei e ao conteúdo consagrado; se não tiver certeza, diga e sugira confirmar com o mentor no fórum. "
            . "Nada de inventar datas do concurso: para novidades, mande ver o Radar em Meu concurso. Assuntos fora de estudo/concurso/rotina de estudo: recuse com gentileza. Termine, quando fizer sentido, com o próximo passo do método (questões do assunto, Missão do dia, Flashcards). Até 250 palavras.";
        try {
            ia_consumo_inicio('duvidas', $uid);
            $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['systemInstruction' => ['parts' => [['text' => $sistema]]], 'contents' => $conteudo, 'generationConfig' => ['temperature' => 0.5, 'maxOutputTokens' => 900]], 50);
            $resp = trim((string)($r['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            require_once __DIR__ . '/includes/texto-limpo-lib.php';
            $resp = gab_sem_latex($resp !== '' ? $resp : 'Combatente, não consegui responder agora. Tenta de novo em instantes ou posta no fórum.');
        } catch (Throwable $e) {
            $resp = 'Combatente, o Antigão está sem fôlego agora (limite do dia da IA). Poste sua dúvida no fórum de dúvidas que o mentor responde. Amanhã eu volto!';
        }
        $pdo->prepare("INSERT INTO antigao_conversas (usuario_id, quem, texto) VALUES (:u, 'antigao', :t)")->execute([':u' => $uid, ':t' => $resp]);
        header('Location: antigao.php#fim');
        exit;
    }
}
$msgs = $pdo->prepare('SELECT quem, texto, criado_em FROM antigao_conversas WHERE usuario_id = :u ORDER BY id DESC LIMIT 40');
$msgs->execute([':u' => $uid]);
$msgs = array_reverse($msgs->fetchAll());
$restam = max(0, ANTIGAO_LIMITE_DIA - $hojeQtd());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antigão | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.55}
        .an{max-width:820px;margin:0 auto;padding:24px 16px 72px;display:grid;gap:14px}
        h1{font-size:clamp(22px,4vw,30px)}.sub{color:var(--suave);font-size:14px}
        .msg{max-width:88%;padding:12px 14px;border-radius:16px;white-space:pre-line;font-size:15px}
        .aluno{justify-self:end;background:#2e2408;border:1px solid #5a4613}
        .antigao{justify-self:start;background:var(--card);border:1px solid var(--linha)}
        .antigao strong.q{color:var(--ouro);display:block;font-size:12px;margin-bottom:4px}
        form.envio{display:grid;gap:8px;position:sticky;bottom:0;background:var(--fundo);padding:8px 0}
        textarea{width:100%;min-height:80px;border-radius:12px;border:1px solid var(--linha);background:var(--card2);color:var(--texto);padding:12px;font:inherit}
        .bt{border:0;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--ouro);color:#111}
        .erro{background:#3a1d1a;color:#ffb4a8;border-radius:12px;padding:10px 14px}
        .linha{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        .link{background:none;border:0;color:var(--suave);font:inherit;font-size:13px;cursor:pointer;text-decoration:underline}
    </style>
</head>
<body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="an">
    <div>
        <h1>🎖 Antigão · tira-dúvidas</h1>
        <p class="sub"><?php echo $esc ? 'Ele já sabe que você estuda para <strong>' . ant_e($esc['concurso']) . '</strong> (' . ant_e($esc['cargo']) . ') e onde você mais erra.' : 'Escolha seu concurso em <a href="meu-concurso.php" style="color:var(--ouro)">Meu concurso</a> para o Antigão te ajudar no ponto certo.'; ?>
            Dúvida que precisa do mentor? Use o <a href="forum-duvidas.php" style="color:var(--ouro)">fórum</a>.</p>
    </div>
    <?php if (!$msgs): ?>
        <div class="msg antigao"><strong class="q">Antigão</strong>Fala, combatente! Manda sua dúvida de estudo: uma regra de crase, um artigo do estatuto, uma questão que você errou… Eu explico simples, com exemplo e com a pegadinha que a banca adora.</div>
    <?php endif; ?>
    <?php foreach ($msgs as $m): ?>
        <div class="msg <?php echo $m['quem']; ?>"><?php if ($m['quem'] === 'antigao'): ?><strong class="q">Antigão</strong><?php endif; ?><?php echo ant_e($m['texto']); ?></div>
    <?php endforeach; ?>
    <span id="fim"></span>
    <?php if ($erro !== ''): ?><div class="erro"><?php echo ant_e($erro); ?></div><?php endif; ?>
    <form method="post" class="envio" onsubmit="this.querySelector('.bt').disabled=true;this.querySelector('.bt').textContent='O Antigão está pensando…'">
        <textarea name="pergunta" maxlength="1200" placeholder="Ex.: Por que não tem crase em 'a partir de'?" required></textarea>
        <div class="linha"><button class="bt" type="submit">Perguntar ao Antigão</button><span class="sub"><?php echo $restam; ?> pergunta(s) restante(s) hoje</span></div>
    </form>
    <?php if ($msgs): ?><form method="post" onsubmit="return confirm('Apagar toda a conversa?');"><input type="hidden" name="acao" value="limpar"><button class="link" type="submit">Apagar minha conversa</button></form><?php endif; ?>
    <p class="sub" style="font-size:12px">🔒 Sua pergunta é respondida por inteligência artificial (Google), sem seu nome ou e-mail. Não escreva dados pessoais. <a href="privacidade.php" style="color:var(--ouro)">Privacidade</a>.</p>
</main>
</body>
</html>
