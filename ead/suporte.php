<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();

$barraSuperiorInclude = __DIR__ . '/includes/barra-superior.php';
if (!is_file($barraSuperiorInclude)) {
    $barraSuperiorInclude = __DIR__ . '/barra-superior.php';
}

$menuItens = [
    ['titulo' => 'Suporte', 'link' => 'suporte.php', 'externo' => false],
    ['titulo' => 'Configurações', 'link' => 'configuracoes.php', 'externo' => false],
];

function suporte_esc($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
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
    <title>Suporte | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{--yellow:#fab528;--bg:#121212;--surface:#202020;--line:rgba(255,255,255,.08);--text:#fff;--muted:#cfcfcf;}
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}a{text-decoration:none;color:inherit}
        .page{padding:34px 30px 44px;background:linear-gradient(180deg,#121212 0%,#151515 100%);min-height:calc(100vh - 96px)}
        .wrap{max-width:1120px;margin:0 auto}.head{margin-bottom:24px}.head h1{font-size:2rem;color:var(--yellow);font-weight:500;margin-bottom:9px}.head p{color:var(--muted);line-height:1.45}
        .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:24px;min-height:170px;display:flex;flex-direction:column;gap:14px}.icon{width:46px;height:46px;border-radius:13px;background:rgba(250,181,40,.16);display:flex;align-items:center;justify-content:center;color:var(--yellow)}.icon svg{width:24px;height:24px}.card h2{font-size:1.2rem}.card p{color:#fff;line-height:1.45;font-weight:500}.btn{margin-top:auto;min-height:38px;border-radius:7px;background:var(--yellow);color:#fff;font-weight:700;display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;width:max-content}
        @media(max-width:900px){.page{padding:26px 18px 36px}.grid{grid-template-columns:1fr}.head h1{font-size:1.75rem}.btn{width:100%}}
    </style>
</head>
<body>
    <?php if (is_file($barraSuperiorInclude)) { include $barraSuperiorInclude; } ?>

    <main class="page">
        <div class="wrap">
            <header class="head">
                <h1>Suporte</h1>
                <p>Escolha uma opção para tirar dúvidas, pedir ajuda ou falar com a equipe da plataforma.</p>
            </header>

            <section class="grid" aria-label="Opções de suporte">
                <article class="card">
                    <div class="icon"><i data-lucide="message-circle"></i></div>
                    <h2>Fórum de dúvidas</h2>
                    <p>Abra uma dúvida para acompanhar as respostas e interações dentro da plataforma.</p>
                    <a class="btn" href="forum-duvidas.php">Abrir fórum</a>
                </article>
                <article class="card">
                    <div class="icon"><i data-lucide="book-open-check"></i></div>
                    <h2>Materiais e aulas</h2>
                    <p>Acesse os materiais do curso e revise conteúdos quando precisar.</p>
                    <a class="btn" href="materiais.php">Ver materiais</a>
                </article>
                <article class="card">
                    <div class="icon"><i data-lucide="mail"></i></div>
                    <h2>Contato</h2>
                    <p>Use essa área para centralizar o atendimento ao aluno.</p>
                    <a class="btn" href="mailto:suporte@univixead.com.br">Enviar e-mail</a>
                </article>
            </section>
        </div>
    </main>

    <script>if(window.lucide)window.lucide.createIcons();</script>
</body>
</html>
