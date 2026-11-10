<?php
// cadastro.php
// Cadastro normal do Projeto Gabaritando. Sem Google e sem Hobbi.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function cadastro_safe_redirect(string $redirect, string $default = 'dashboard.php'): string
{
    $redirect = trim(rawurldecode($redirect));
    $default = trim($default) !== '' ? $default : 'dashboard.php';

    for ($i = 0; $i < 6; $i++) {
        $limpo = ltrim($redirect, '/');
        $path = (string)(parse_url($limpo, PHP_URL_PATH) ?: $limpo);
        $pathNormalizado = mb_strtolower(trim($path), 'UTF-8');
        $pathNormalizado = preg_replace('/\.php$/i', '', $pathNormalizado) ?: $pathNormalizado;

        if (in_array($pathNormalizado, ['login', 'cadastro'], true)) {
            return $default;
        }

        if (preg_match('/[?&]redirect=([^&]+)/i', $redirect, $match)) {
            $proximo = trim(rawurldecode((string)$match[1]));
            if ($proximo !== '' && $proximo !== $redirect) {
                $redirect = $proximo;
                continue;
            }
        }

        break;
    }

    $redirect = str_replace('\\', '/', $redirect);

    if ($redirect === '' || strpos($redirect, '://') !== false || str_starts_with($redirect, '//')) {
        return $default;
    }

    $redirect = ltrim($redirect, '/');
    $path = (string)(parse_url($redirect, PHP_URL_PATH) ?: $redirect);
    $pathNormalizado = mb_strtolower(trim($path), 'UTF-8');
    $pathNormalizado = preg_replace('/\.php$/i', '', $pathNormalizado) ?: $pathNormalizado;

    if ($pathNormalizado === '' || in_array($pathNormalizado, ['login', 'cadastro'], true)) {
        return $default;
    }

    return $redirect;
}

$erro = (string)($_SESSION['cadastro_erro'] ?? '');
$old = $_SESSION['cadastro_old'] ?? [];
$redirect = cadastro_safe_redirect((string)($_GET['redirect'] ?? $old['redirect'] ?? 'dashboard.php'));

function old_value(string $key): string
{
    global $old;
    return e((string)($old[$key] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#d99a00;
            --black:#080808;
            --bg:#f4f5f7;
            --panel:#f8f9fb;
            --field:#eaf0fb;
            --border:#cfd6e2;
            --text:#111827;
            --muted:#667085;
            --error:#b42318;
            --error-bg:#fff1f0;
            --error-border:#fecdca;
            --shadow:0 18px 45px rgba(15,23,42,.12);
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html,body{width:100%;min-height:100%}
        body{min-height:100vh;font-family:'Ubuntu',Arial,sans-serif;background:var(--panel);color:var(--text)}
        a{text-decoration:none;color:inherit}
        button,input{font:inherit}

        .topbar{width:100%;height:70px;background:var(--black);display:flex;align-items:center;justify-content:center;padding:0 32px}
        .topbar-link{display:flex;align-items:center;justify-content:center;height:100%}
        .topbar-logo{display:block;max-height:55px;width:auto;object-fit:contain}

        .register-page{width:100%;min-height:calc(100vh - 70px);display:flex;align-items:center;justify-content:center;background:var(--panel);padding:38px 18px;overflow:auto}
        .register-card{width:100%;max-width:620px;background:#fff;border:1px solid var(--border);border-radius:18px;padding:34px 34px 30px;box-shadow:var(--shadow)}
        .title-row{display:flex;align-items:center;gap:12px;margin-bottom:8px}
        .title-row svg{width:27px;height:27px;color:#1f2937;stroke-width:2.4}
        h1{font-size:2rem;line-height:1.15;font-weight:700;color:#0f172a;letter-spacing:.01em}
        .subtitle{color:var(--muted);font-size:.96rem;line-height:1.45;margin:0 0 24px 39px}

        .alert{width:100%;border:1px solid var(--error-border);background:var(--error-bg);color:var(--error);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:.94rem;font-weight:700;line-height:1.35}
        .form{display:grid;gap:16px;width:100%}
        .field{display:grid;gap:8px;width:100%}
        .field label{font-size:.95rem;color:#111827;font-weight:500}
        .input{width:100%;height:45px;border:1px solid var(--border);border-radius:7px;background:var(--field);color:#111827;outline:none;padding:0 16px;transition:border-color .18s ease, box-shadow .18s ease, background .18s ease}
        .input:focus{background:#fff;border-color:var(--yellow);box-shadow:0 0 0 3px rgba(250,181,40,.18)}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .password-wrap{position:relative;width:100%}
        .password-wrap .input{padding-right:48px}
        .password-toggle{position:absolute;right:11px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;background:transparent;color:#667085;display:flex;align-items:center;justify-content:center;cursor:pointer}
        .password-toggle svg{width:20px;height:20px}
        .check{display:flex;align-items:flex-start;gap:10px;color:#475467;font-size:.91rem;line-height:1.4;margin-top:2px}
        .check input{margin-top:3px;accent-color:var(--yellow)}
        .submit-btn{width:100%;height:48px;border:0;border-radius:7px;background:var(--yellow);color:#000;font-weight:700;display:flex;align-items:center;justify-content:center;gap:12px;cursor:pointer;margin-top:2px;transition:filter .18s ease, transform .18s ease}
        .submit-btn:hover{filter:brightness(.97)}
        .submit-btn:active{transform:translateY(1px)}
        .submit-btn svg{width:20px;height:20px;stroke-width:2.6}
        .signin{margin-top:22px;text-align:center;color:#374151;font-size:.94rem}
        .signin a{color:var(--yellow-dark);font-weight:700}
        .signin a:hover{text-decoration:underline}

        @media(max-width:900px){
            .topbar{height:64px;padding:0 18px}.topbar-logo{max-height:50px}
            .register-page{min-height:calc(100vh - 64px);padding:28px 14px;background:var(--panel)}
            .register-card{max-width:100%;padding:28px 18px 24px;border-radius:15px}
            h1{font-size:1.72rem}.subtitle{margin-left:0}
        }

        @media(max-width:620px){
            .grid-2{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="topbar-link" href="dashboard" aria-label="Projeto Gabaritando">
            <img class="topbar-logo" src="img/logo.png" alt="Projeto Gabaritando">
        </a>
    </header>

    <main class="register-page">
        <section class="register-card">
            <div class="title-row">
                <i data-lucide="user-plus"></i>
                <h1>Criar conta</h1>
            </div>
            <p class="subtitle">Preencha seus dados para acessar a plataforma.</p>

            <?php if ($erro !== ''): ?>
                <div class="alert"><?php echo e($erro); ?></div>
            <?php endif; ?>

            <form class="form" action="api/processa_cadastro" method="post" autocomplete="on">
                <div class="field">
                    <label for="nome">Nome completo</label>
                    <input class="input" id="nome" name="nome" type="text" required value="<?php echo old_value('nome'); ?>" placeholder="Nome e sobrenome">
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input class="input" id="email" name="email" type="email" required value="<?php echo old_value('email'); ?>" placeholder="seuemail@exemplo.com">
                    </div>

                    <div class="field">
                        <label for="telefone">Telefone</label>
                        <input class="input" id="telefone" name="telefone" type="tel" value="<?php echo old_value('telefone'); ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label for="senha">Senha</label>
                        <div class="password-wrap">
                            <input class="input" id="senha" name="senha" type="password" required minlength="6" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="password-toggle" data-target="senha" aria-label="Mostrar senha"><i data-lucide="eye"></i></button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="senha_confirmacao">Confirmar senha</label>
                        <div class="password-wrap">
                            <input class="input" id="senha_confirmacao" name="senha_confirmacao" type="password" required minlength="6" placeholder="Repita a senha">
                            <button type="button" class="password-toggle" data-target="senha_confirmacao" aria-label="Mostrar senha"><i data-lucide="eye"></i></button>
                        </div>
                    </div>
                </div>

                <label class="check">
                    <input type="checkbox" name="aceite_termos" required>
                    <span>Li e aceito os <a href="termos.php" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a> da plataforma.</span>
                </label>

                <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                <button class="submit-btn" type="submit"><i data-lucide="arrow-right"></i><span>Criar conta</span></button>
            </form>

            <p class="signin">Já tem conta? <a href="login">Entrar</a></p>
        </section>
    </main>

    <script>
        if (window.lucide) window.lucide.createIcons();
        document.querySelectorAll('.password-toggle').forEach(function(btn){
            btn.addEventListener('click',function(){
                const input=document.getElementById(btn.dataset.target);
                if(!input)return;
                const show=input.type==='password';
                input.type=show?'text':'password';
                btn.innerHTML=show?'<i data-lucide="eye-off"></i>':'<i data-lucide="eye"></i>';
                if(window.lucide)window.lucide.createIcons();
            });
        });
    </script>
</body>
</html>
<?php unset($_SESSION['cadastro_erro'], $_SESSION['cadastro_old']); ?>
