<?php
// includes/lgpd-pagina.php — moldura simples e pública (sem login) para Política e Termos.
function lgpd_pagina_inicio(string $titulo): void
{
    ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?> | Projeto Gabaritando</title>
<link rel="icon" type="image/png" href="/ead/favicon.png">
<style>
    :root{--ouro:#fbb928;--texto:#1f2937;--suave:#4b5563;--linha:#e5e7eb}
    *{box-sizing:border-box}body{margin:0;background:#f7f7f8;color:var(--texto);font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;line-height:1.65}
    header{background:#000;border-bottom:3px solid var(--ouro);padding:14px 16px;display:flex;align-items:center;gap:12px}header img{height:40px}header a{color:var(--ouro);margin-left:auto;font-weight:700;text-decoration:none}
    main{max-width:860px;margin:0 auto;padding:24px 16px 64px}
    h1{font-size:clamp(24px,4vw,32px);margin:0 0 4px}h2{font-size:20px;margin:28px 0 8px;padding-top:8px;border-top:1px solid var(--linha)}h3{font-size:16px;margin:16px 0 4px}
    p,li{font-size:16px}.sub{color:var(--suave);font-size:14px}
    table{width:100%;border-collapse:collapse;font-size:14.5px;margin:8px 0}th,td{border:1px solid var(--linha);padding:8px;text-align:left;vertical-align:top}th{background:#fff8e6}
    .caixa{background:#fff;border:1px solid var(--linha);border-left:4px solid var(--ouro);border-radius:10px;padding:12px 14px;margin:12px 0}
    a{color:#8a6300}
</style></head><body>
<header><img src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="Projeto Gabaritando"><a href="login.php">Entrar na plataforma</a></header>
<main><?php
}
function lgpd_pagina_fim(): void
{
    echo '<p class="sub" style="margin-top:32px">Veja também: <a href="privacidade.php">Política de Privacidade</a> · <a href="termos.php">Termos de Uso</a> · <a href="meus-dados.php">Meus dados (LGPD)</a></p></main></body></html>';
}
