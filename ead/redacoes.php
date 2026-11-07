<?php
// redações.php
// Tela visual no mesmo padrão de propostas-redacao.php, com include da barra superior.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redações | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --redacoes-amarelo:#fdbd08;
            --redacoes-fundo:#ffffff;
            --redacoes-card:#f8f8f8;
            --redacoes-texto:#111111;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html,
        body{
            width:100%;
            min-height:100%;
        }

        body{
            min-height:100vh;
            font-family:'Ubuntu', Arial, sans-serif;
            background:var(--redacoes-fundo);
            color:var(--redacoes-texto);
        }

        .redacoes-page{
            width:100%;
            min-height:calc(100vh - 96px);
            background:var(--redacoes-fundo);
            padding:35px 68px 60px;
        }

        .redacoes-shell{
            width:100%;
            max-width:1230px;
            margin:0 auto;
        }

        .redacoes-card{
            width:100%;
            min-height:290px;
            background:var(--redacoes-card);
            border-radius:7px 7px 0 0;
            overflow:hidden;
        }

        .redacoes-header{
            width:100%;
            height:80px;
            background:var(--redacoes-amarelo);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0 20px;
        }

        .redacoes-title{
            color:#ffffff;
            font-size:1.82rem;
            line-height:1.2;
            font-weight:700;
            text-align:center;
        }

        .estado-vazio{
            width:100%;
            min-height:210px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            color:#777777;
            font-size:1.02rem;
            font-weight:500;
            padding:34px 18px;
        }

        @media(max-width:900px){
            .redacoes-page{
                padding:26px 18px 44px;
            }

            .redacoes-title{
                font-size:1.55rem;
            }
        }

        @media(max-width:520px){
            .redacoes-page{
                padding:18px 10px 36px;
            }

            .redacoes-card{
                border-radius:7px 7px 0 0;
                min-height:250px;
            }

            .redacoes-header{
                height:68px;
            }

            .redacoes-title{
                font-size:1.34rem;
            }

            .estado-vazio{
                min-height:182px;
                font-size:.96rem;
                padding:28px 14px;
            }
        }
    </style>
</head>
<body>
    <?php
    $barraSuperiorPath = __DIR__ . '/includes/barra-superior.php';
    if (is_file($barraSuperiorPath)) {
        include_once $barraSuperiorPath;
    } elseif (is_file(__DIR__ . '/barra-superior.php')) {
        include_once __DIR__ . '/barra-superior.php';
    }
    ?>

    <main class="redacoes-page">
        <div class="redacoes-shell">
            <section class="redacoes-card" aria-label="Redações">
                <header class="redacoes-header">
                    <h1 class="redacoes-title">Redações</h1>
                </header>

                <div class="estado-vazio" role="status">
                    Nenhuma redação disponível
                </div>
            </section>
        </div>
    </main>
</body>
</html>
