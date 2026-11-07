<?php
// propostas-redacao.php
// Tela visual com include da barra superior. Depois, a lista de propostas pode vir do banco de dados.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
function esc(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

$categorias = [
    'todas' => 'Filtrar por categoria...',
    'atualidades' => 'Atualidades',
    'educacao' => 'Educação',
    'seguranca-publica' => 'Segurança Pública',
    'meio-ambiente' => 'Meio Ambiente',
    'direitos-humanos' => 'Direitos Humanos',
];

$filtroTema = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$categoriaSelecionada = isset($_GET['categoria']) ? (string) $_GET['categoria'] : 'todas';

$propostas = [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propostas de Redação | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --propostas-amarelo:#fdbd08;
            --propostas-amarelo-escuro:#c99700;
            --propostas-fundo:#ffffff;
            --propostas-card:#f8f8f8;
            --propostas-texto:#111111;
            --propostas-cinza:#a8a8a8;
            --propostas-borda:#b7b7b7;
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
            background:var(--propostas-fundo);
            color:var(--propostas-texto);
        }

        button,
        input,
        select{
            font-family:inherit;
        }

        .propostas-page{
            width:100%;
            min-height:calc(100vh - 96px);
            background:var(--propostas-fundo);
            padding:35px 68px 60px;
        }

        .propostas-shell{
            width:100%;
            max-width:1230px;
            margin:0 auto;
        }

        .propostas-card{
            width:100%;
            min-height:218px;
            background:var(--propostas-card);
            border-radius:7px 7px 0 0;
            overflow:hidden;
        }

        .propostas-header{
            width:100%;
            height:80px;
            background:var(--propostas-amarelo);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0 20px;
        }

        .propostas-title{
            color:#ffffff;
            font-size:1.82rem;
            line-height:1.2;
            font-weight:700;
            text-align:center;
        }

        .propostas-filtros{
            width:100%;
            display:grid;
            grid-template-columns:minmax(0, 1fr) 288px;
            column-gap:10px;
            row-gap:12px;
            align-items:center;
            padding:20px 35px 64px;
        }

        .campo-busca,
        .campo-select{
            position:relative;
            width:100%;
            height:54px;
            display:block;
        }

        .campo-busca i,
        .campo-busca svg,
        .campo-busca .lucide,
        .campo-select::after{
            position:absolute;
            top:50%;
            transform:translateY(-50%);
            pointer-events:none;
        }

        .campo-busca i,
        .campo-busca svg,
        .campo-busca .lucide{
            left:17px;
            width:20px;
            height:20px;
            color:#111111;
            z-index:2;
        }

        .campo-busca input,
        .campo-select select{
            display:block;
            width:100%;
            height:54px;
            line-height:54px;
            border:1px solid var(--propostas-borda);
            border-radius:8px;
            background:#ffffff;
            color:#333333;
            font-size:1.04rem;
            font-weight:400;
            outline:none;
            box-shadow:none;
        }

        .campo-busca input{
            padding:0 18px 0 51px;
        }

        .campo-busca input::placeholder{
            color:#606060;
            opacity:1;
        }

        .campo-select select{
            appearance:none;
            -webkit-appearance:none;
            cursor:pointer;
            padding:0 48px 0 12px;
            color:var(--propostas-cinza);
            font-size:.98rem;
            font-weight:700;
        }

        .campo-select::after{
            content:"";
            right:21px;
            width:0;
            height:0;
            border-left:7px solid transparent;
            border-right:7px solid transparent;
            border-top:7px solid var(--propostas-amarelo);
        }

        .propostas-lista{
            width:100%;
            margin-top:26px;
            display:grid;
            gap:14px;
        }

        .proposta-item{
            width:100%;
            border:1px solid #eeeeee;
            border-radius:8px;
            background:#ffffff;
            padding:18px 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
        }

        .proposta-item__titulo{
            font-size:1rem;
            font-weight:700;
            color:#111111;
        }

        .proposta-item__categoria{
            font-size:.88rem;
            font-weight:700;
            color:var(--propostas-amarelo-escuro);
            white-space:nowrap;
        }

        .estado-vazio{
            width:100%;
            min-height:210px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            color:#777777;
            font-size:.98rem;
            font-weight:500;
        }

        @media(max-width:900px){
            .propostas-page{
                padding:26px 18px 44px;
            }

            .propostas-filtros{
                grid-template-columns:1fr;
                padding:20px 20px 44px;
            }

            .propostas-title{
                font-size:1.55rem;
            }
        }

        @media(max-width:520px){
            .propostas-page{
                padding:18px 10px 36px;
            }

            .propostas-card{
                border-radius:7px 7px 0 0;
            }

            .propostas-header{
                height:68px;
            }

            .propostas-title{
                font-size:1.34rem;
            }

            .propostas-filtros{
                padding:18px 12px 34px;
                gap:12px;
            }

            .campo-busca input,
            .campo-select select{
                height:52px;
                font-size:.95rem;
            }

            .campo-busca input{
                padding-left:46px;
            }

            .campo-busca i,
            .campo-busca svg,
            .campo-busca .lucide{
                left:15px;
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

    <main class="propostas-page">
        <div class="propostas-shell">
            <section class="propostas-card" aria-label="Propostas de redação">
                <header class="propostas-header">
                    <h1 class="propostas-title">Propostas de Redação</h1>
                </header>

                <form class="propostas-filtros" method="get" action="propostas-redacao.php">
                    <label class="campo-busca" for="q">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="<?php echo esc($filtroTema); ?>"
                            placeholder="Filtrar temas por enunciado..."
                            autocomplete="off"
                        >
                    </label>

                    <label class="campo-select" for="categoria">
                        <select id="categoria" name="categoria" aria-label="Filtrar por categoria" onchange="this.form.submit()">
                            <?php foreach ($categorias as $valor => $rotulo): ?>
                                <option value="<?php echo esc($valor); ?>" <?php echo $categoriaSelecionada === $valor ? 'selected' : ''; ?>>
                                    <?php echo esc($rotulo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </section>

            <?php if (!empty($propostas)): ?>
                <section class="propostas-lista" aria-label="Lista de propostas">
                    <?php foreach ($propostas as $proposta): ?>
                        <article class="proposta-item">
                            <h2 class="proposta-item__titulo"><?php echo esc((string) $proposta['titulo']); ?></h2>
                            <span class="proposta-item__categoria"><?php echo esc((string) $proposta['categoria']); ?></span>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>
