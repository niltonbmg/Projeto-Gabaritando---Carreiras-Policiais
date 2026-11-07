<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/includes/boas-vindas.php';

$banner = 'img/banner.png';
$exibirBoasVindas = false;

$barraSuperiorInclude = __DIR__ . '/includes/barra-superior.php';
if (!is_file($barraSuperiorInclude)) {
    $barraSuperiorInclude = __DIR__ . '/barra-superior.php';
}

// No dashboard principal o menu fica mais simples.
// O menu completo permanece nas telas internas do curso.
$menuItens = [
    ['titulo' => 'Suporte', 'link' => 'suporte.php', 'externo' => false],
    ['titulo' => 'Configurações', 'link' => 'configuracoes.php', 'externo' => false],
];

$bannerMobile = 'img/banner-mobile.png';
if (!file_exists(__DIR__ . '/img/banner-mobile.png') && file_exists(__DIR__ . '/img/banner-mobile')) {
    $bannerMobile = 'img/banner-mobile';
}

$cursosDashboard = [];
$erroCursosDashboard = '';

if (!function_exists('dashboard_esc')) {
    function dashboard_esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboard_formata_horas')) {
    function dashboard_formata_horas($valor): string
    {
        $numero = (float)$valor;
        if ($numero <= 0) {
            return '';
        }

        $formatado = number_format($numero, 2, ',', '.');
        $formatado = rtrim(rtrim($formatado, '0'), ',');
        return $formatado . 'h';
    }
}


if (!function_exists('dashboard_config_bootstrap')) {
    function dashboard_config_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_configuracoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            chave VARCHAR(120) NOT NULL,
            valor TEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_admin_configuracoes_chave (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('dashboard_config_get')) {
    function dashboard_config_get(PDO $pdo, string $chave, string $default = ''): string
    {
        try {
            $stmt = $pdo->prepare('SELECT valor FROM admin_configuracoes WHERE chave = :chave LIMIT 1');
            $stmt->execute([':chave' => $chave]);
            $valor = $stmt->fetchColumn();
            return $valor !== false && $valor !== null && (string)$valor !== '' ? (string)$valor : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('dashboard_ensure_curso_cover_columns')) {
    function dashboard_ensure_curso_cover_columns(PDO $pdo): void
    {
        $colunas = [
            'capa_mobile' => 'VARCHAR(255) NULL AFTER capa',
            'capa_pagina' => 'VARCHAR(255) NULL AFTER capa_mobile',
            'capa_pagina_mobile' => 'VARCHAR(255) NULL AFTER capa_pagina',
        ];
        foreach ($colunas as $coluna => $definition) {
            try {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
                $stmt->execute([':table_name' => 'admin_cursos', ':column_name' => $coluna]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE admin_cursos ADD COLUMN {$coluna} {$definition}");
                }
            } catch (Throwable $e) {
                // Não quebra o dashboard se o banco bloquear ALTER TABLE.
            }
        }
    }
}

$conexaoInclude = __DIR__ . '/api/conexao.php';
if (is_file($conexaoInclude)) {
    require_once $conexaoInclude;
}

$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}

try {
    if (!function_exists('db')) {
        throw new RuntimeException('Arquivo de conexão api/conexao.php não carregado.');
    }

    $pdo = db();
    $usuarioIdDashboard = function_exists('auth_user_id') ? (int)auth_user_id() : (int)($_SESSION['usuario']['id'] ?? 0);

    gab_boas_vindas_bootstrap($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (string)($_POST['acao'] ?? '') === 'marcar_boas_vindas_visualizada') {
        header('Content-Type: application/json; charset=utf-8');
        $salvouBoasVindas = gab_boas_vindas_marcar_visualizada($pdo, $usuarioIdDashboard);
        http_response_code($salvouBoasVindas ? 200 : 500);
        echo json_encode([
            'ok' => $salvouBoasVindas,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $exibirBoasVindas = gab_boas_vindas_deve_exibir($pdo, $usuarioIdDashboard);

    $filtroAcessoDashboard = '';
    $paramsAcessoDashboard = [];
    if (function_exists('gab_curso_acesso_sql')) {
        [$filtroAcessoDashboard, $paramsAcessoDashboard] = gab_curso_acesso_sql($pdo, $usuarioIdDashboard, 'c.id', 'dashboard_curso');
    }
    dashboard_config_bootstrap($pdo);
    dashboard_ensure_curso_cover_columns($pdo);
    $banner = dashboard_config_get($pdo, 'dashboard_banner_pc', $banner);
    $bannerMobile = dashboard_config_get($pdo, 'dashboard_banner_mobile', $bannerMobile);

    $sqlCursos = "
        SELECT
            c.id,
            c.nome,
            c.descricao,
            c.capa,
            c.capa_mobile,
            c.horas_curso,
            c.horas_video,
            c.horas_pdf,
            COALESCE(m.total_modulos, 0) AS total_modulos,
            COALESCE(a.total_aulas, 0) AS total_aulas
        FROM admin_cursos c
        LEFT JOIN (
            SELECT curso_id, COUNT(*) AS total_modulos
            FROM admin_curso_modulos
            GROUP BY curso_id
        ) m ON m.curso_id = c.id
        LEFT JOIN (
            SELECT curso_id, COUNT(*) AS total_aulas
            FROM admin_curso_aulas
            GROUP BY curso_id
        ) a ON a.curso_id = c.id
        WHERE c.status = 'ativo'
        {$filtroAcessoDashboard}
        ORDER BY c.ordem ASC, c.id DESC
    ";

    $stmtCursos = $pdo->prepare($sqlCursos);
    $stmtCursos->execute($paramsAcessoDashboard);
    $cursosBanco = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cursosBanco as $curso) {
        $capaCurso = trim((string)($curso['capa'] ?? ''));
        if ($capaCurso === '') {
            $capaCurso = 'img/banner.png';
        }
        $capaCursoMobile = trim((string)($curso['capa_mobile'] ?? ''));
        if ($capaCursoMobile === '') {
            $capaCursoMobile = $capaCurso;
        }

        $infosCurso = [];
        $horasCurso = dashboard_formata_horas($curso['horas_curso'] ?? 0);

        if ($horasCurso !== '') {
            $infosCurso[] = $horasCurso . ' de curso';
        }

        $totalModulos = (int)($curso['total_modulos'] ?? 0);
        $totalAulas = (int)($curso['total_aulas'] ?? 0);
        if ($totalModulos > 0) {
            $infosCurso[] = $totalModulos . ($totalModulos === 1 ? ' módulo' : ' módulos');
        }
        if ($totalAulas > 0) {
            $infosCurso[] = $totalAulas . ($totalAulas === 1 ? ' aula' : ' aulas');
        }

        $cursosDashboard[] = [
            'id' => (int)$curso['id'],
            'titulo' => (string)$curso['nome'],
            'descricao' => (string)($curso['descricao'] ?? ''),
            'imagem' => $capaCurso,
            'imagem_mobile' => $capaCursoMobile,
            'link' => './curso-modulos.php?curso_id=' . (int)$curso['id'],
            'infos' => implode(' • ', $infosCurso),
        ];
    }
} catch (Throwable $e) {
    $erroCursosDashboard = 'Não foi possível carregar os cursos agora.';
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
    <title>Dashboard | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --yellow: #fab528;
            --yellow-dark: #c98f0e;
            --black: #000000;
            --bg: #121212;
            --surface: #181818;
            --surface-2: #202020;
            --line: rgba(255,255,255,0.08);
            --text: #ffffff;
            --muted: #cfcfcf;
            --shadow: 0 14px 35px rgba(0,0,0,.28);
            --radius: 16px;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html, body{
            width:100%;
            min-height:100%;
        }

        body{
            font-family:'Ubuntu',sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        img{
            max-width:100%;
            display:block;
        }

        a{
            text-decoration:none;
            color:inherit;
        }


        .hero{
            width:100%;
            min-height: 540px;
            position:relative;
            background:
                linear-gradient(to bottom, rgba(0,0,0,.12), rgba(0,0,0,.52)),
                url('<?php echo htmlspecialchars($banner, ENT_QUOTES, 'UTF-8'); ?>') center center / cover no-repeat;
            display:flex;
            align-items:flex-end;
        }

        .hero::after{
            content:"";
            position:absolute;
            inset:0;
            background: radial-gradient(circle at center, rgba(250,181,40,.04), rgba(0,0,0,0) 45%);
            pointer-events:none;
        }

        .hero__content{
            position:relative;
            z-index:1;
            width:100%;
            padding: 28px 34px 34px;
        }

        .hero__badge{
            display:inline-flex;
            align-items:center;
            gap:10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(0,0,0,.44);
            border: 1px solid rgba(255,255,255,.08);
            box-shadow: var(--shadow);
            font-size: 1rem;
        }

        .hero__badge i{
            width: 18px;
            height: 18px;
            color: var(--yellow);
        }

        .section{
            padding: 28px 30px 34px;
            background: linear-gradient(180deg, #121212 0%, #151515 100%);
        }

        .section__title{
            font-size: 2rem;
            margin-bottom: 20px;
            font-weight: 500;
            color: #fab528;
        }

        .modulos{
            display:grid;
            grid-template-columns: repeat(5, minmax(220px, 1fr));
            gap: 20px;
            overflow-x:auto;
            padding-bottom: 6px;
        }

        .curso-dashboard-card{
            background: linear-gradient(180deg, #171717 0%, #111111 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow:hidden;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
            box-shadow: 0 10px 24px rgba(0,0,0,.16);
            min-width: 220px;
        }

        .curso-dashboard-card:hover{
            transform: translateY(-4px);
            border-color: rgba(250,181,40,.24);
            box-shadow: 0 16px 30px rgba(0,0,0,.28);
        }

        .curso-dashboard-card__thumb{
            height: 350px;
            position:relative;
            overflow:hidden;
            background: #0f0f0f;
        }


        .curso-dashboard-card__thumb picture,
        .curso-dashboard-card__thumb img{
            width:100%;
            height:100%;
            object-fit:cover;
            transform: scale(1.02);
            display:block;
        }

        .curso-dashboard-card__thumb::after{
            content:"";
            position:absolute;
            inset:0;
            background: linear-gradient(to top, rgba(0,0,0,.78) 0%, rgba(0,0,0,.12) 48%, rgba(0,0,0,.04) 100%);
        }

        .curso-dashboard-card__label{
            position:absolute;
            left:14px;
            right:14px;
            bottom:14px;
            z-index:1;
            color:#fff;
            font-size: 1.55rem;
            line-height: 1.05;
            text-transform: uppercase;
            font-weight: 700;
            text-shadow: 0 2px 14px rgba(0,0,0,.55);
        }

        .curso-dashboard-card__footer{
            padding: 14px 14px 16px;
            border-top: 1px solid rgba(255,255,255,.05);
        }

        .curso-dashboard-card__footer span{
            display:block;
            font-size: 1rem;
            color: #fff;
            line-height: 1.35;
            min-height: 42px;
        }

        .curso-dashboard-card__footer small{
            display:block;
            color: var(--muted);
            font-size: .82rem;
            line-height: 1.35;
            margin-top: 8px;
        }

        .dashboard-empty-courses{
            width:100%;
            min-height: 160px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #181818;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap: 10px;
            text-align:center;
            padding: 28px 18px;
            color:#fff;
        }

        .dashboard-empty-courses i{
            width: 34px;
            height: 34px;
            color: var(--yellow);
        }

        .dashboard-empty-courses strong{
            font-size: 1.1rem;
            font-weight: 700;
        }

        .dashboard-empty-courses span{
            color: var(--muted);
            font-size: .95rem;
            max-width: 620px;
            line-height: 1.45;
        }

        .curso-dashboard-card__bar{
            height: 10px;
            width: 100%;
            background: #8ef06d;
            border-radius: 0 0 16px 16px;
        }




        .dashboard-panels{
            width:100%;
            max-width: 1220px;
            margin: 42px auto 0;
            display:grid;
            gap: 20px;
        }

        .dashboard-panel{
            min-height: 142px;
            background: #242424;
            border: 1px solid rgba(255,255,255,.04);
            border-radius: 9px;
            padding: 23px 24px;
            display:grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 28px;
            align-items:center;
            color:#fff;
        }

        .dashboard-panel__content{
            min-width:0;
        }

        .dashboard-panel__title{
            font-size: 1.55rem;
            line-height:1.15;
            font-weight:700;
            margin:0 0 20px;
            color:#fff;
        }

        .dashboard-panel__subtitle{
            font-size: 1rem;
            font-weight:700;
            margin: -4px 0 20px;
            color:#fff;
        }

        .dashboard-panel__text{
            color:#fff;
            font-size: 1rem;
            font-weight:700;
            line-height:1.35;
            margin:0;
        }

        .dashboard-panel__text.centered{
            text-align:center;
        }

        .dashboard-panel__button{
            width: 240px;
            min-height: 40px;
            border-radius: 5px;
            background: var(--yellow);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            padding: 10px 18px;
            font-weight:700;
            font-size:.96rem;
            transition: filter .18s ease, transform .18s ease;
        }

        .dashboard-panel__button:hover{
            filter: brightness(.96);
            transform: translateY(-1px);
        }

        .modal{
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.58);
            display:flex;
            align-items:center;
            justify-content:center;
            padding: 18px;
            z-index: 100;
        }

        .modal.hidden{
            display:none;
        }

        .modal__box{
            width:100%;
            max-width: 860px;
            background: #f3f3f4;
            border-radius: 22px;
            overflow:hidden;
            box-shadow: 0 28px 70px rgba(0,0,0,.42);
            border: 1px solid rgba(255,255,255,.12);
        }

        .modal__header{
            background: linear-gradient(135deg, #ffc63a 0%, #fab528 62%, #e1a110 100%);
            min-height: 88px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            padding: 22px 76px 22px 24px;
        }

        .modal__title{
            color:#fff;
            font-size: 2.3rem;
            font-weight: 700;
            text-align:center;
            text-shadow: 0 2px 10px rgba(0,0,0,.10);
        }

        .modal__close{
            position:absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            width:46px;
            height:46px;
            border-radius:14px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(0,0,0,.12);
            color: #fff;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        .modal__close i{
            width: 24px;
            height: 24px;
        }

        .modal__body{
            padding: 34px 36px 38px;
            text-align:center;
        }

        .modal__body p{
            color:#111;
            font-size: 1.12rem;
            margin-bottom: 18px;
            line-height: 1.45;
        }

        .video-wrap{
            position:relative;
            width:100%;
            aspect-ratio: 16 / 9;
            background:#000;
            border-radius: 18px;
            overflow:hidden;
            box-shadow: 0 18px 40px rgba(0,0,0,.18);
        }

        .video-wrap iframe{
            width:100%;
            height:100%;
            border:0;
        }

        @media (max-width: 1200px){
            .modulos{
                grid-template-columns: repeat(4, minmax(220px, 1fr));
            }
        }

        @media (max-width: 980px){            .hero{
                min-height: 360px;
                background-position: center;
            }

            .hero__content{
                padding: 18px 18px 24px;
            }

            .section{
                padding: 24px 18px 30px;
            }

            .section__title{
                font-size: 1.8rem;
            }

            .modulos{
                grid-template-columns: repeat(2, minmax(220px, 1fr));
                gap: 16px;
            }

            .modal__box{
                max-width: 92vw;
            }

            .dashboard-panel{
                grid-template-columns: 1fr;
                align-items:start;
            }

            .dashboard-panel__button{
                width: 230px;
                justify-self:end;
            }

            .modal__title{
                font-size: 1.9rem;
            }

            .modal__body{
                padding: 24px 18px 24px;
            }
        }

        @media (max-width: 640px){            .hero{
                min-height: 220px;
                background:
                    linear-gradient(to bottom, rgba(0,0,0,.12), rgba(0,0,0,.52)),
                    url('<?php echo htmlspecialchars($bannerMobile, ENT_QUOTES, 'UTF-8'); ?>') center center / cover no-repeat;
            }

            .hero__badge{
                font-size: .92rem;
                padding: 9px 12px;
            }

            .section__title{
                font-size: 1.65rem;
            }

            .modulos{
                display:flex;
                gap: 14px;
                overflow-x:auto;
                padding-bottom: 10px;
                scroll-snap-type:x proximity;
            }

            .curso-dashboard-card{
                min-width: 250px;
                scroll-snap-align:start;
            }

            .curso-dashboard-card__thumb{
                height: 300px;
            }

            .curso-dashboard-card__label{
                font-size: 1.28rem;
            }

            .modal{
                padding: 10px;
            }
            .dashboard-panels{
                margin-top: 28px;
                gap: 16px;
            }

            .dashboard-panel{
                min-height: auto;
                padding: 20px 18px;
            }

            .dashboard-panel__title{
                font-size: 1.35rem;
                margin-bottom: 14px;
            }

            .dashboard-panel__text.centered{
                text-align:left;
            }

            .dashboard-panel__button{
                width: 100%;
                justify-self:stretch;
            }

            .modal__header{
                min-height: auto;
                padding: 18px 60px 18px 18px;
            }

            .modal__title{
                font-size: 1.55rem;
            }

            .modal__close{
                width: 42px;
                height: 42px;
                right: 12px;
            }

            .modal__close i{
                width: 22px;
                height: 22px;
            }

            .modal__body{
                padding: 16px;
            }

            .modal__body p{
                font-size: .98rem;
                margin-bottom: 14px;
            }

            .video-wrap{
                border-radius: 14px;
            }
        }
    </style>
</head>
<body>
    <?php if (is_file($barraSuperiorInclude)) { include $barraSuperiorInclude; } ?>

    <section class="hero">
        <div class="hero__content">
            <div class="hero__badge">
                <i data-lucide="shield-check"></i>
                <span>Plataforma de preparação</span>
            </div>
        </div>
    </section>

    <!-- dashboard-fluxo: curso -> curso-modulos.php -> aulas -->
    <main class="section">
        <h2 class="section__title">Cursos</h2>

        <?php if (!empty($cursosDashboard)): ?>
            <div class="modulos">
                <?php foreach ($cursosDashboard as $curso): ?>
                    <a href="<?php echo dashboard_esc($curso['link']); ?>" class="curso-dashboard-card" data-open-curso-modulos="1" data-curso-id="<?php echo (int)$curso['id']; ?>" onclick="event.preventDefault(); event.stopPropagation(); window.location.href=this.href; return false;">
                        <div class="curso-dashboard-card__thumb">
                            <picture>
                                <source media="(max-width:640px)" srcset="<?php echo dashboard_esc($curso['imagem_mobile'] ?? $curso['imagem']); ?>">
                                <img src="<?php echo dashboard_esc($curso['imagem']); ?>" alt="<?php echo dashboard_esc($curso['titulo']); ?>">
                            </picture>
                            <div class="curso-dashboard-card__label"><?php echo dashboard_esc($curso['titulo']); ?></div>
                        </div>
                        <div class="curso-dashboard-card__footer">
                            <span><?php echo dashboard_esc($curso['titulo']); ?></span>
                            <?php if (!empty($curso['infos'])): ?>
                                <small><?php echo dashboard_esc($curso['infos']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="curso-dashboard-card__bar"></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty-courses">
                <i data-lucide="book-open-check" aria-hidden="true"></i>
                <strong>Nenhum curso liberado para sua conta ainda.</strong>
                <span><?php echo $erroCursosDashboard !== '' ? dashboard_esc($erroCursosDashboard) : 'Assim que o administrador atribuir um curso para você, ele aparecerá aqui automaticamente.'; ?></span>
            </div>
        <?php endif; ?>

        <!-- Os atalhos de conteúdo foram movidos para curso-modulos.php, abaixo dos módulos do curso. -->

    </main>

    <?php if ($exibirBoasVindas): ?>
    <div class="modal" id="welcomeModal">
        <div class="modal__box">
            <div class="modal__header">
                <h3 class="modal__title">Boas vindas!</h3>
                <button type="button" class="modal__close" id="closeModal" aria-label="Fechar popup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal__body">
                <p>Assista ao vídeo de boas vindas para conhecer melhor a plataforma.</p>

                <div class="video-wrap">
                    <iframe
                        width="560"
                        height="315"
                        src="https://www.youtube.com/embed/vvpdgJ5NIbQ?autoplay=1&mute=1&rel=0&modestbranding=1&playsinline=1"
                        title="Vídeo de boas-vindas"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }


        document.querySelectorAll('a[data-open-curso-modulos="1"]').forEach(function (card) {
            card.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                window.location.href = card.href;
            }, true);
        });


        const modal = document.getElementById('welcomeModal');
        const closeModal = document.getElementById('closeModal');
        let boasVindasJaSalva = false;

        function salvarBoasVindasVisualizada() {
            if (boasVindasJaSalva) return;
            boasVindasJaSalva = true;

            const dados = new URLSearchParams();
            dados.set('acao', 'marcar_boas_vindas_visualizada');

            fetch('dashboard.php', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: dados.toString()
            }).catch(function () {
                if (navigator.sendBeacon) {
                    const formulario = new FormData();
                    formulario.append('acao', 'marcar_boas_vindas_visualizada');
                    navigator.sendBeacon('dashboard.php', formulario);
                }
            });
        }

        function fecharModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            salvarBoasVindasVisualizada();

            const iframe = modal.querySelector('iframe');
            if (iframe) {
                iframe.src = 'about:blank';
            }
        }

        if (closeModal && modal) {
            closeModal.addEventListener('click', fecharModal);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    fecharModal();
                }
            });
        }
    </script>
</body>
</html>
