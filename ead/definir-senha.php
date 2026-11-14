<?php
// definir-senha.php — Link do e-mail de boas-vindas (ou "Reenviar acesso"): o aluno cria a própria senha.
// O link vale 72 horas e só pode ser usado uma vez.
declare(strict_types=1);

require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/seguranca.php';
gab_seg_post_mesma_origem();
require_once __DIR__ . '/includes/pagamentos-lib.php';

$pdo = db();
pag_bootstrap($pdo);
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tok = preg_replace('~[^a-f0-9]~', '', (string)($_GET['t'] ?? $_POST['t'] ?? ''));
$st = $pdo->prepare('SELECT c.usuario_id, u.email, u.nome FROM senha_convites c JOIN usuarios u ON u.id = c.usuario_id WHERE c.token_hash = :h AND c.usado_em IS NULL AND c.expira_em > NOW()');
$st->execute([':h' => hash('sha256', $tok)]);
$conv = $tok !== '' ? $st->fetch(PDO::FETCH_ASSOC) : false;
$erro = '';
$feito = false;
if ($conv && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $s1 = (string)($_POST['senha'] ?? '');
    $s2 = (string)($_POST['senha2'] ?? '');
    if (mb_strlen($s1) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($s1 !== $s2) {
        $erro = 'As duas senhas não estão iguais.';
    } else {
        $cols = array_column($pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $hash = password_hash($s1, PASSWORD_DEFAULT);
        foreach (['senha_hash', 'senha', 'password_hash'] as $c) {
            if (in_array($c, $cols, true)) {
                $pdo->prepare("UPDATE usuarios SET `$c` = :h WHERE id = :u")->execute([':h' => $hash, ':u' => (int)$conv['usuario_id']]);
            }
        }
        $pdo->prepare('UPDATE senha_convites SET usado_em = NOW() WHERE token_hash = :h')->execute([':h' => hash('sha256', $tok)]);
        $feito = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar senha</title>
    <link rel="icon" type="image/png" href="/ead/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#0c0c0d;color:#f3f3f4;font-family:'Ubuntu',system-ui,sans-serif}.ds{max-width:440px;margin:40px auto;padding:0 16px}
        .c{background:#161618;border:1px solid #2a2a2e;border-radius:16px;padding:22px;display:grid;gap:12px}h1{font-size:22px;margin:0}p{margin:0;color:#a3a3ab}
        label{display:grid;gap:5px;font-size:14px}input{font:inherit;font-size:16px;padding:12px;border-radius:12px;border:1px solid #2a2a2e;background:#1e1e21;color:#f3f3f4}
        .bt{display:block;border:0;border-radius:14px;padding:14px;font:inherit;font-weight:700;font-size:16px;background:#fbb928;color:#111;text-align:center;text-decoration:none;cursor:pointer}
        .erro{background:#3a1d1a;color:#ffb4a8;border-radius:12px;padding:10px 12px}.ok{background:#0f2a1a;color:#4ade80;border-radius:12px;padding:12px}
    </style>
</head>
<body><main class="ds"><section class="c">
<?php if ($feito): ?>
    <div class="ok">✅ Senha criada! Agora é só entrar.</div>
    <a class="bt" href="login.php">Entrar na plataforma</a>
<?php elseif (!$conv): ?>
    <h1>Link vencido ou já usado</h1>
    <p>Peça um novo link ao suporte (eles têm o botão "Reenviar acesso"), ou use "Esqueci minha senha" no login.</p>
    <a class="bt" href="login.php">Ir para o login</a>
<?php else: ?>
    <h1>Criar sua senha</h1>
    <p>Login: <strong style="color:#f3f3f4"><?php echo $e($conv['email']); ?></strong></p>
    <?php if ($erro !== ''): ?><div class="erro" role="alert"><?php echo $e($erro); ?></div><?php endif; ?>
    <form method="post" style="display:grid;gap:12px"><input type="hidden" name="t" value="<?php echo $e($tok); ?>">
        <label>Nova senha (mínimo 8 caracteres)<input type="password" name="senha" autocomplete="new-password" minlength="8" required></label>
        <label>Repita a senha<input type="password" name="senha2" autocomplete="new-password" minlength="8" required></label>
        <button class="bt" type="submit">Salvar e entrar</button>
    </form>
<?php endif; ?>
</section></main></body></html>
