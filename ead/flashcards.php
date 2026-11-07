<?php
// flashcards.php
// Tela visual de Flashcards no mesmo padrão do Projeto Gabaritando, com include da barra superior.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
if (!function_exists('esc')) {
    function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}

$decks = [
    [
        'slug' => 'cards-errados-ou-dificeis',
        'nome' => 'Cards errados ou difíceis',
        'cards' => 0,
        'tag' => 'Recomendação',
        'recomendacao' => true,
    ],
    [
        'slug' => 'cards-medios',
        'nome' => 'Cards médios',
        'cards' => 0,
        'tag' => 'Recomendação',
        'recomendacao' => true,
    ],
    [
        'slug' => 'cards-faceis',
        'nome' => 'Cards fáceis',
        'cards' => 0,
        'tag' => 'Recomendação',
        'recomendacao' => true,
    ],
    [
        'slug' => 'tipologia-textual-e-generos-textuais',
        'nome' => 'Tipologia textual e gêneros textuais',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
    [
        'slug' => 'compreensao-e-interpretacao-de-textos',
        'nome' => 'Compreensão e interpretação de textos de gêneros variados.',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
    [
        'slug' => 'ortografia-oficial',
        'nome' => 'Ortografia oficial',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
    [
        'slug' => 'acentuacao-grafica',
        'nome' => 'Acentuação gráfica',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
    [
        'slug' => 'classes-de-palavras',
        'nome' => 'Classes de palavras',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
    [
        'slug' => 'uso-do-sinal-indicativo-de-crase',
        'nome' => 'Uso do sinal indicativo de crase',
        'cards' => 1,
        'tag' => 'LÍNGUA PORTUGUESA',
        'recomendacao' => false,
    ],
];

$deckSelecionadoSlug = isset($_GET['deck']) ? trim((string) $_GET['deck']) : '';
$deckSelecionado = null;

foreach ($decks as $deck) {
    if ($deck['slug'] === $deckSelecionadoSlug) {
        $deckSelecionado = $deck;
        break;
    }
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
    <title>Flashcards | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --flash-amarelo:#fdbd08;
            --flash-amarelo-escuro:#f0ab00;
            --flash-fundo:#f7f7f7;
            --flash-card:#ffffff;
            --flash-texto:#111111;
            --flash-cinza:#5f6368;
            --flash-borda:#d2d2d2;
            --flash-barra:#d8d8d8;
            --flash-sombra:0 8px 22px rgba(0,0,0,.05);
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
            color:var(--flash-texto);
            background:var(--flash-fundo);
        }

        a{
            color:inherit;
            text-decoration:none;
        }

        button,
        input,
        select,
        textarea{
            font-family:inherit;
        }

        button{
            border:0;
            cursor:pointer;
        }

        .flash-page{
            width:100%;
            min-height:calc(100vh - 90px);
            background:var(--flash-fundo);
            padding:31px 30px 42px;
        }

        .flash-shell{
            width:100%;
            max-width:1226px;
            margin:0 auto;
        }

        .flash-panel{
            width:100%;
            background:#fafafa;
            border-radius:8px 8px 0 0;
            overflow:hidden;
        }

        .flash-header{
            width:100%;
            min-height:81px;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
            background:var(--flash-amarelo);
        }

        .flash-header h1{
            color:#ffffff;
            font-size:1.92rem;
            line-height:1.15;
            font-weight:700;
            letter-spacing:.2px;
            text-align:center;
        }

        .flash-toolbar{
            width:100%;
            display:grid;
            grid-template-columns:auto minmax(200px, 200px) minmax(200px, 200px) 1fr auto;
            align-items:center;
            gap:15px;
            padding:20px 35px 20px;
        }

        .flash-toolbar__label{
            font-size:.98rem;
            font-weight:700;
            color:#111111;
            white-space:nowrap;
        }

        .flash-field,
        .flash-select{
            position:relative;
            width:100%;
            height:52px;
        }

        .flash-field input,
        .flash-select select{
            width:100%;
            height:52px;
            border:1px solid #bdbdbd;
            border-radius:8px;
            background:#ffffff;
            color:#6f6f6f;
            font-size:1rem;
            font-weight:400;
            outline:none;
            box-shadow:none;
        }

        .flash-field input{
            padding:0 16px 0 50px;
        }

        .flash-field .lucide{
            position:absolute;
            left:15px;
            top:50%;
            width:20px;
            height:20px;
            color:#000000;
            transform:translateY(-50%);
            pointer-events:none;
        }

        .flash-select select{
            appearance:none;
            -webkit-appearance:none;
            padding:0 48px 0 24px;
        }

        .flash-select::after{
            content:"";
            position:absolute;
            right:14px;
            top:50%;
            width:0;
            height:0;
            border-left:7px solid transparent;
            border-right:7px solid transparent;
            border-top:7px solid var(--flash-amarelo);
            transform:translateY(-45%);
            pointer-events:none;
        }

        .flash-create{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            min-width:171px;
            height:38px;
            padding:0 16px;
            border-radius:5px;
            background:var(--flash-amarelo-escuro);
            color:#ffffff;
            font-size:1rem;
            font-weight:500;
            transition:filter .18s ease, transform .18s ease;
        }

        .flash-create:hover{
            filter:brightness(.98);
            transform:translateY(-1px);
        }

        .flash-create .lucide{
            width:18px;
            height:18px;
        }

        .flash-decks{
            display:grid;
            gap:14px;
            width:100%;
            padding:17px 35px 34px;
        }

        .deck-card{
            min-height:83px;
            width:100%;
            display:grid;
            grid-template-columns:minmax(0, 1fr) 260px 122px;
            align-items:center;
            gap:18px;
            padding:17px 21px;
            border-radius:8px;
            background:var(--flash-card);
            box-shadow:var(--flash-sombra);
        }

        .deck-card__title{
            color:var(--flash-amarelo-escuro);
            font-size:1rem;
            line-height:1.32;
            font-weight:700;
            margin-bottom:14px;
        }

        .deck-card__count{
            color:#404852;
            font-size:.92rem;
            font-weight:700;
            line-height:1.1;
        }

        .deck-card__tag{
            display:flex;
            align-items:center;
            justify-content:flex-start;
            gap:10px;
            min-width:0;
            color:#3c4048;
            font-size:.88rem;
            font-weight:700;
            text-transform:none;
            white-space:nowrap;
        }

        .deck-card__tag--upper{
            text-transform:uppercase;
            font-size:.86rem;
        }

        .deck-card__dot{
            display:inline-block;
            width:20px;
            height:20px;
            flex:0 0 20px;
            border-radius:50%;
            background:var(--flash-amarelo-escuro);
        }

        .deck-card__actions{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:9px;
        }

        .deck-view{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:100px;
            height:25px;
            border-radius:999px;
            background:var(--flash-amarelo-escuro);
            color:#ffffff;
            font-size:.87rem;
            font-weight:700;
        }

        .deck-edit{
            width:31px;
            height:31px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:50%;
            background:#fff6d3;
            color:var(--flash-amarelo-escuro);
        }

        .deck-edit .lucide{
            width:17px;
            height:17px;
        }

        .deck-card--recommend .deck-card__actions{
            display:none;
        }

        .deck-card--recommend{
            grid-template-columns:minmax(0, 1fr) 168px;
        }

        .deck-card--recommend .deck-card__tag{
            justify-content:flex-start;
        }

        .flash-study{
            width:100%;
            min-height:calc(100vh - 130px);
            background:#fbfbfb;
            border-radius:8px;
            padding:48px 47px 68px;
        }

        .study-top{
            display:grid;
            grid-template-columns:190px 1fr 390px;
            align-items:center;
            gap:20px;
            margin-bottom:21px;
        }

        .study-back,
        .study-restart{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:var(--flash-amarelo-escuro);
            font-size:.98rem;
            font-weight:700;
            background:transparent;
        }

        .study-back .lucide,
        .study-restart .lucide{
            width:18px;
            height:18px;
        }

        .study-restart{
            justify-self:center;
        }

        .study-deck-name{
            justify-self:end;
            font-size:1rem;
            font-weight:400;
            color:#000000;
            text-align:right;
        }

        .study-deck-name strong{
            font-weight:700;
        }

        .study-progress{
            width:100%;
            display:flex;
            flex-direction:column;
            align-items:center;
            margin-top:6px;
        }

        .study-progress__title{
            font-size:1rem;
            font-weight:700;
            color:#000000;
            margin-bottom:22px;
        }

        .progress-circle{
            width:200px;
            height:200px;
            border:20px solid var(--flash-amarelo);
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#ffffff;
        }

        .progress-circle span{
            font-size:2.1rem;
            font-weight:700;
            letter-spacing:1px;
            color:#000000;
        }

        .study-stats-title{
            margin:22px 0 19px;
            color:#000000;
            text-align:center;
            font-size:1rem;
            font-weight:700;
        }

        .stats-list{
            width:100%;
            display:grid;
            gap:20px;
        }

        .stat-row{
            width:100%;
            display:grid;
            grid-template-columns:30px minmax(0, 1fr) 30px;
            align-items:center;
            gap:7px;
            color:#000000;
            font-size:1rem;
        }

        .stat-bar{
            width:100%;
            height:48px;
            border-radius:8px;
            background:var(--flash-barra);
            overflow:hidden;
        }

        .stat-fill{
            height:100%;
            width:0%;
            border-radius:8px;
            background:var(--flash-amarelo);
        }

        .flash-modal{
            position:fixed;
            inset:0;
            z-index:9999;
            display:none;
            align-items:flex-start;
            justify-content:center;
            padding:12px 10px 30px;
            background:rgba(0,0,0,.52);
            overflow:auto;
        }

        .flash-modal.is-open{
            display:flex;
        }

        .modal-box{
            width:min(790px, 100%);
            min-height:600px;
            background:#ffffff;
            border-radius:7px 7px 0 0;
            box-shadow:0 16px 46px rgba(0,0,0,.23);
            overflow:hidden;
        }

        .modal-header{
            position:relative;
            min-height:81px;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:18px 68px;
            background:var(--flash-amarelo);
        }

        .modal-header h2{
            color:#ffffff;
            font-size:2rem;
            line-height:1.12;
            font-weight:700;
            text-align:center;
        }

        .modal-close{
            position:absolute;
            right:62px;
            top:50%;
            width:28px;
            height:28px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:50%;
            background:transparent;
            color:#ffffff;
            transform:translateY(-50%);
        }

        .modal-close .lucide{
            width:22px;
            height:22px;
        }

        .modal-body{
            padding:39px 78px 44px;
        }

        .modal-field{
            display:block;
            width:100%;
            margin-bottom:20px;
        }

        .modal-field span,
        .modal-label{
            display:block;
            margin-bottom:9px;
            color:#0d1720;
            font-size:1.08rem;
            font-weight:400;
        }

        .modal-field input{
            width:100%;
            height:50px;
            border:1px solid #b9b9b9;
            border-radius:7px;
            padding:0 15px;
            background:#ffffff;
            color:#111111;
            font-size:1rem;
            outline:none;
        }

        .flashcards-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin:3px 0 24px;
        }

        .flashcards-actions{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .tabs{
            display:flex;
            align-items:center;
            height:24px;
            border-radius:999px;
            background:#d5d5d5;
            overflow:hidden;
        }

        .tab-btn{
            min-width:91px;
            height:24px;
            padding:0 16px;
            background:#d5d5d5;
            color:#ffffff;
            font-size:.84rem;
            font-weight:700;
            border-radius:999px;
        }

        .tab-btn.is-active{
            background:var(--flash-amarelo);
        }

        .icon-plus,
        .icon-trash{
            display:flex;
            align-items:center;
            justify-content:center;
            width:22px;
            height:22px;
            background:transparent;
            color:var(--flash-amarelo-escuro);
        }

        .icon-trash{
            color:#ff0000;
        }

        .icon-plus .lucide,
        .icon-trash .lucide{
            width:19px;
            height:19px;
        }

        .editor{
            width:100%;
            border:1px solid #c9c9c9;
            background:#ffffff;
        }

        .editor-toolbar{
            min-height:40px;
            display:flex;
            align-items:center;
            gap:15px;
            padding:0 16px;
            border-bottom:1px solid #d6d6d6;
            color:#4a4a4a;
        }

        .editor-toolbar button{
            background:transparent;
            color:#4a4a4a;
            font-weight:700;
            font-size:1rem;
            line-height:1;
        }

        .editor-toolbar .lucide{
            width:16px;
            height:16px;
        }

        .editor-area{
            display:none;
            width:100%;
            min-height:190px;
            border:0;
            padding:14px 15px;
            resize:vertical;
            outline:none;
            color:#111111;
            font-size:.98rem;
            font-style:italic;
            line-height:1.5;
        }

        .editor-area.is-active{
            display:block;
        }

        .modal-footer{
            display:flex;
            justify-content:flex-end;
            gap:12px;
            margin-top:25px;
        }

        .btn-secondary,
        .btn-primary{
            min-width:116px;
            height:39px;
            border-radius:6px;
            padding:0 18px;
            font-size:.96rem;
            font-weight:700;
        }

        .btn-secondary{
            background:#efefef;
            color:#333333;
        }

        .btn-primary{
            background:var(--flash-amarelo-escuro);
            color:#ffffff;
        }

        @media(max-width:980px){
            .flash-page{
                padding:22px 14px 36px;
            }

            .flash-toolbar{
                grid-template-columns:1fr 1fr;
                padding:18px;
                gap:12px;
            }

            .flash-toolbar__label{
                grid-column:1 / -1;
            }

            .flash-toolbar__spacer{
                display:none;
            }

            .flash-create{
                grid-column:1 / -1;
                justify-self:end;
            }

            .flash-decks{
                padding:12px 18px 26px;
            }

            .deck-card,
            .deck-card--recommend{
                grid-template-columns:1fr;
                gap:14px;
            }

            .deck-card__tag,
            .deck-card__actions{
                justify-content:flex-start;
            }

            .study-top{
                grid-template-columns:1fr;
            }

            .study-restart{
                justify-self:start;
            }

            .study-deck-name{
                justify-self:start;
                text-align:left;
            }

            .flash-study{
                padding:30px 18px 44px;
            }

            .modal-body{
                padding:30px 28px 38px;
            }

            .modal-close{
                right:24px;
            }
        }

        @media(max-width:620px){
            .flash-page{
                padding:16px 10px 30px;
            }

            .flash-header{
                min-height:68px;
            }

            .flash-header h1{
                font-size:1.52rem;
            }

            .flash-toolbar{
                grid-template-columns:1fr;
                padding:15px 12px 18px;
            }

            .flash-create{
                justify-self:stretch;
                width:100%;
            }

            .flash-decks{
                padding:10px 12px 22px;
            }

            .deck-card{
                padding:16px 17px;
            }

            .deck-card__actions{
                align-items:stretch;
            }

            .deck-view{
                min-width:120px;
            }

            .progress-circle{
                width:168px;
                height:168px;
                border-width:18px;
            }

            .progress-circle span{
                font-size:1.72rem;
            }

            .stat-row{
                grid-template-columns:23px 1fr 23px;
            }

            .stat-bar{
                height:42px;
            }

            .modal-box{
                min-height:auto;
            }

            .modal-header{
                min-height:72px;
                padding:16px 52px 16px 24px;
            }

            .modal-header h2{
                font-size:1.55rem;
            }

            .modal-close{
                right:18px;
            }

            .modal-body{
                padding:25px 18px 32px;
            }

            .flashcards-head{
                align-items:flex-start;
                flex-direction:column;
            }

            .tabs{
                width:100%;
            }

            .tab-btn{
                flex:1;
                min-width:0;
            }

            .editor-toolbar{
                gap:11px;
                flex-wrap:wrap;
                padding:10px 13px;
            }

            .editor-area{
                min-height:170px;
            }

            .modal-footer{
                flex-direction:column-reverse;
            }

            .btn-secondary,
            .btn-primary{
                width:100%;
            }
        }
    </style>
</head>
<body>
    <?php
    $barraSuperiorPaths = [
        __DIR__ . '/includes/barra-superior.php',
        __DIR__ . '/includes/barra_superior.php',
        __DIR__ . '/barra-superior.php',
    ];

    foreach ($barraSuperiorPaths as $barraSuperiorPath) {
        if (is_file($barraSuperiorPath)) {
            include_once $barraSuperiorPath;
            break;
        }
    }
    ?>

    <main class="flash-page">
        <div class="flash-shell">
            <?php if ($deckSelecionado !== null): ?>
                <section class="flash-study" aria-label="Resumo do deck">
                    <div class="study-top">
                        <a class="study-back" href="flashcards.php">
                            <i data-lucide="chevron-left" aria-hidden="true"></i>
                            Voltar
                        </a>

                        <button class="study-restart" type="button">
                            <i data-lucide="rotate-cw" aria-hidden="true"></i>
                            Reiniciar sessão
                        </button>

                        <p class="study-deck-name">
                            Deck: <strong><?php echo esc((string) $deckSelecionado['nome']); ?></strong>
                        </p>
                    </div>

                    <div class="study-progress">
                        <h1 class="study-progress__title">Progresso Atual</h1>
                        <div class="progress-circle" aria-label="Progresso atual de cem por cento">
                            <span>100%</span>
                        </div>
                    </div>

                    <h2 class="study-stats-title">Estatísticas por Questão</h2>

                    <div class="stats-list" aria-label="Estatísticas por questão">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <div class="stat-row">
                                <span><?php echo $i; ?></span>
                                <div class="stat-bar" aria-hidden="true">
                                    <div class="stat-fill"></div>
                                </div>
                                <span>0</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </section>
            <?php else: ?>
                <section class="flash-panel" aria-label="Lista de decks">
                    <header class="flash-header">
                        <h1>Lista de Decks</h1>
                    </header>

                    <div class="flash-toolbar">
                        <span class="flash-toolbar__label">Filtrar por:</span>

                        <label class="flash-select" for="tag">
                            <select id="tag" name="tag" aria-label="Filtrar por tag">
                                <option value="">Tag</option>
                                <option value="lingua-portuguesa">LÍNGUA PORTUGUESA</option>
                                <option value="recomendacao">Recomendação</option>
                            </select>
                        </label>

                        <label class="flash-field" for="nome">
                            <i data-lucide="search" aria-hidden="true"></i>
                            <input id="nome" name="nome" type="search" placeholder="Nome" autocomplete="off">
                        </label>

                        <span class="flash-toolbar__spacer"></span>

                        <button class="flash-create" type="button" data-open-modal>
                            <i data-lucide="plus" aria-hidden="true"></i>
                            Criar novo deck
                        </button>
                    </div>

                    <div class="flash-decks">
                        <?php foreach ($decks as $deck): ?>
                            <article class="deck-card <?php echo $deck['recomendacao'] ? 'deck-card--recommend' : ''; ?>">
                                <div class="deck-card__content">
                                    <h2 class="deck-card__title"><?php echo esc((string) $deck['nome']); ?></h2>
                                    <p class="deck-card__count"><?php echo (int) $deck['cards']; ?> card</p>
                                </div>

                                <div class="deck-card__tag <?php echo !$deck['recomendacao'] ? 'deck-card__tag--upper' : ''; ?>">
                                    <span class="deck-card__dot" aria-hidden="true"></span>
                                    <?php echo esc((string) $deck['tag']); ?>
                                </div>

                                <?php if (!$deck['recomendacao']): ?>
                                    <div class="deck-card__actions">
                                        <button class="deck-edit" type="button" data-open-modal aria-label="Editar deck <?php echo esc((string) $deck['nome']); ?>">
                                            <i data-lucide="pencil" aria-hidden="true"></i>
                                        </button>
                                        <a class="deck-view" href="flashcards.php?deck=<?php echo esc((string) $deck['slug']); ?>">Ver</a>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <div class="flash-modal" id="deckModal" aria-hidden="true">
        <section class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
            <header class="modal-header">
                <h2 id="modalTitulo">Editar Deck</h2>
                <button class="modal-close" type="button" data-close-modal aria-label="Fechar">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="modal-body">
                <label class="modal-field" for="deckNome">
                    <span>Nome do Deck</span>
                    <input id="deckNome" type="text" autocomplete="off">
                </label>

                <label class="modal-field" for="deckTag">
                    <span>Tag</span>
                    <input id="deckTag" type="text" autocomplete="off">
                </label>

                <div class="flashcards-head">
                    <span class="modal-label">Flashcards</span>

                    <div class="flashcards-actions">
                        <div class="tabs" role="tablist" aria-label="Alternar pergunta e resposta">
                            <button class="tab-btn is-active" type="button" data-tab="pergunta">Pergunta</button>
                            <button class="tab-btn" type="button" data-tab="resposta">Resposta</button>
                        </div>

                        <button class="icon-plus" type="button" aria-label="Adicionar flashcard">
                            <i data-lucide="plus" aria-hidden="true"></i>
                        </button>

                        <button class="icon-trash" type="button" aria-label="Excluir flashcard">
                            <i data-lucide="trash-2" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="editor">
                    <div class="editor-toolbar" aria-label="Ferramentas de edição">
                        <button type="button" aria-label="Negrito">B</button>
                        <button type="button" aria-label="Itálico"><em>I</em></button>
                        <button type="button" aria-label="Sublinhado"><u>U</u></button>
                        <button type="button" aria-label="Tachado"><s>S</s></button>
                        <button type="button" aria-label="Lista numerada"><i data-lucide="list-ordered" aria-hidden="true"></i></button>
                        <button type="button" aria-label="Lista"><i data-lucide="list" aria-hidden="true"></i></button>
                        <button type="button" aria-label="Cor do texto">A</button>
                        <button type="button" aria-label="Texto"><i data-lucide="type" aria-hidden="true"></i></button>
                        <button type="button" aria-label="Link"><i data-lucide="link" aria-hidden="true"></i></button>
                        <button type="button" aria-label="Imagem"><i data-lucide="image" aria-hidden="true"></i></button>
                    </div>

                    <textarea class="editor-area is-active" id="areaPergunta" placeholder="Insira o texto aqui..."></textarea>
                    <textarea class="editor-area" id="areaResposta" placeholder="Insira a resposta aqui..."></textarea>
                </div>

                <div class="modal-footer">
                    <button class="btn-secondary" type="button" data-close-modal>Cancelar</button>
                    <button class="btn-primary" type="button" data-close-modal>Salvar</button>
                </div>
            </div>
        </section>
    </div>

    <script>
        (function(){
            const modal = document.getElementById('deckModal');
            const openButtons = document.querySelectorAll('[data-open-modal]');
            const closeButtons = document.querySelectorAll('[data-close-modal]');
            const tabs = document.querySelectorAll('[data-tab]');
            const pergunta = document.getElementById('areaPergunta');
            const resposta = document.getElementById('areaResposta');

            function openModal(){
                if (!modal) return;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeModal(){
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            openButtons.forEach(function(button){
                button.addEventListener('click', openModal);
            });

            closeButtons.forEach(function(button){
                button.addEventListener('click', closeModal);
            });

            if (modal) {
                modal.addEventListener('click', function(event){
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }

            document.addEventListener('keydown', function(event){
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    tabs.forEach(function(item){
                        item.classList.remove('is-active');
                    });

                    tab.classList.add('is-active');

                    if (tab.dataset.tab === 'resposta') {
                        pergunta.classList.remove('is-active');
                        resposta.classList.add('is-active');
                    } else {
                        resposta.classList.remove('is-active');
                        pergunta.classList.add('is-active');
                    }
                });
            });

            if (window.lucide) {
                window.lucide.createIcons();
            }
        })();
    </script>
</body>
</html>
