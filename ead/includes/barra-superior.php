<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logo = $logo ?? 'img/logo.png';

$usuarioAtual = function_exists('auth_user') ? auth_user() : (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) ? $_SESSION['usuario'] : null);
$usuarioLogado = is_array($usuarioAtual) && !empty($usuarioAtual['id']);
$nomeUsuario = $usuarioLogado && !empty($usuarioAtual['nome'])
    ? (string) $usuarioAtual['nome']
    : 'Visitante';

$tipoUsuarioAtual = strtolower(trim((string)(
    $usuarioAtual['tipo']
    ?? $_SESSION['usuario_tipo']
    ?? $_SESSION['admin_tipo']
    ?? ''
)));
$usuarioEhAdmin = $usuarioLogado && in_array($tipoUsuarioAtual, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true);

$cursoContextoId = 0;
foreach (['curso_id', 'id_curso', 'id'] as $cursoContextoChave) {
    if (isset($_GET[$cursoContextoChave]) && (int)$_GET[$cursoContextoChave] > 0) {
        $cursoContextoId = (int)$_GET[$cursoContextoChave];
        break;
    }
}
$cursoContextoQuery = $cursoContextoId > 0 ? '?curso_id=' . $cursoContextoId : '';

$menuItens = $menuItens ?? [
    ['titulo' => '▶ Missão do dia', 'link' => 'meu-plano.php', 'externo' => false],
    ['titulo' => '🕵️ Meu concurso · Hackeando o edital', 'link' => 'meu-concurso.php', 'externo' => false],
    ['titulo' => 'Como funciona o método', 'link' => 'meu-plano.php?tour=1', 'externo' => false],
    ['titulo' => '🎯 Desafio do dia · Ranking', 'link' => 'desafio.php', 'externo' => false],
    ['titulo' => 'Materiais', 'link' => 'materiais.php' . $cursoContextoQuery, 'externo' => false],
    ['titulo' => 'Agenda', 'link' => 'agenda.php', 'externo' => false],
    ['titulo' => 'Simulados', 'link' => 'simulados.php' . $cursoContextoQuery, 'externo' => false],
    ['titulo' => 'Banco de questões', 'link' => 'banco-questoes.php' . $cursoContextoQuery, 'externo' => false],
    ['titulo' => 'Dashboard de questões', 'link' => 'dashboard-questoes.php', 'externo' => false],
    ['titulo' => 'Cronograma', 'link' => 'meu-cronograma.php', 'externo' => false],
    ['titulo' => 'Fórum de dúvidas', 'link' => 'forum-duvidas.php', 'externo' => false],
    ['titulo' => 'Propostas de redação', 'link' => 'propostas-redacao.php', 'externo' => false],
    ['titulo' => 'Redações', 'link' => 'redacoes.php', 'externo' => false],
    ['titulo' => 'Cadernos', 'link' => 'cadernos.php', 'externo' => false],
    ['titulo' => 'Flashcards', 'link' => 'flashcards.php', 'externo' => false],
    ['titulo' => 'Recompensas', 'link' => 'recompensas.php', 'externo' => false],
    ['titulo' => 'QUEM SOMOS', 'link' => 'https://www.instagram.com/projeto.gabaritando/', 'externo' => true],
    ['titulo' => 'ANTIGÃO IA', 'link' => 'https://typebotapi.adscompany.app.br/antigao-38a8ulb', 'externo' => true],
];

$notificacoesTopbar = [];
$totalNotificacoesNaoLidas = 0;
$missaoTopo = null;
$mostrarTourMetodo = false;
if ($usuarioLogado) {
    try {
        require_once __DIR__ . '/notificacoes.php';
        if (function_exists('db') && function_exists('gab_notif_buscar')) {
            $notificacoesTopbar = gab_notif_buscar(db(), (int)$usuarioAtual['id'], 30);
            foreach ($notificacoesTopbar as $notificacaoTopbar) {
                if (empty($notificacaoTopbar['lida'])) {
                    $totalNotificacoesNaoLidas++;
                }
            }
        }
        // Ciclo no automático: próximo passo + lembrete do Antigão no sininho.
        require_once __DIR__ . '/missao-lib.php';
        if (function_exists('db') && !$usuarioEhAdmin) {
            $missaoTopo = missao_proximo_passo(db(), (int)$usuarioAtual['id']);
            $lembreteMissao = missao_lembrete($missaoTopo);
            if ($lembreteMissao) {
                array_unshift($notificacoesTopbar, $lembreteMissao);
                $totalNotificacoesNaoLidas++;
            }
            $mostrarTourMetodo = !missao_viu_tour(db(), (int)$usuarioAtual['id']) || isset($_GET['tour']);
        }
    } catch (Throwable $e) {
        error_log('[Barra superior - notificações] ' . $e->getMessage());
        $notificacoesTopbar = [];
        $totalNotificacoesNaoLidas = 0;
    }
}
?>
<style>
    :root{
        --yellow: #fab528;
        --yellow-dark: #c98f0e;
        --black: #000000;
        --line: rgba(255,255,255,0.08);
    }

    .topbar{
        height: 96px;
        background: var(--black);
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding: 0 18px 0 6px;
        position: sticky;
        top: 0;
        z-index: 50;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }

    .topbar__logo img{
        width: 88px;
        height: auto;
        max-height: 78px;
        object-fit: contain;
        display: block;
    }

    .topbar__actions{
        display:flex;
        align-items:center;
        gap: 14px;
    }

    .user-pill{
        background: rgba(250, 181, 40, 0.18);
        color: #fff;
        border: 1px solid rgba(250, 181, 40, 0.25);
        height: 42px;
        padding: 0 14px;
        border-radius: 999px;
        display:inline-flex;
        align-items:center;
        gap: 10px;
        font-family:inherit;
        font-size: .98rem;
        font-weight: 500;
        white-space: nowrap;
        appearance:none;
        -webkit-appearance:none;
    }

    .user-pill i,
    .user-pill svg{
        width: 18px;
        height: 18px;
        color: var(--yellow);
        flex:0 0 auto;
    }

    .user-menu{
        position:relative;
        display:inline-flex;
        align-items:center;
    }

    .user-pill{
        cursor:pointer;
        border:1px solid rgba(250, 181, 40, 0.25);
        outline:none;
    }

    .user-pill:focus-visible{
        box-shadow:0 0 0 3px rgba(250,181,40,.26);
    }

    .user-dropdown{
        position:absolute;
        top:calc(100% + 10px);
        right:0;
        min-width:190px;
        background:#111111;
        border:1px solid rgba(255,255,255,.10);
        border-radius:12px;
        box-shadow:0 16px 32px rgba(0,0,0,.32);
        padding:8px;
        opacity:0;
        visibility:hidden;
        transform:translateY(-6px);
        transition:opacity .18s ease, transform .18s ease, visibility .18s ease;
        z-index:90;
    }

    .user-dropdown.is-open{
        opacity:1;
        visibility:visible;
        transform:translateY(0);
    }

    .user-dropdown::before{
        content:"";
        position:absolute;
        top:-6px;
        right:22px;
        width:12px;
        height:12px;
        background:#111111;
        border-left:1px solid rgba(255,255,255,.10);
        border-top:1px solid rgba(255,255,255,.10);
        transform:rotate(45deg);
    }

    .user-dropdown__link{
        position:relative;
        z-index:1;
        min-height:42px;
        width:100%;
        border-radius:9px;
        display:flex;
        align-items:center;
        gap:10px;
        color:#ffffff;
        font-size:.94rem;
        font-weight:700;
        text-decoration:none;
        padding:0 12px;
        transition:background .18s ease, color .18s ease;
    }

    .user-dropdown__link:hover{
        background:rgba(250,181,40,.16);
        color:var(--yellow);
    }

    .user-dropdown__link i,
    .user-dropdown__link svg{
        width:18px;
        height:18px;
        color:var(--yellow);
        flex:0 0 auto;
    }

    .notification-menu{
        position:relative;
        display:inline-flex;
        align-items:center;
    }

    .notification-btn{
        position:relative;
        width:42px;
        height:42px;
        border-radius:12px;
        border:1px solid rgba(255,255,255,.10);
        background:rgba(255,255,255,.04);
        color:#fff;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        transition:background .18s ease,border-color .18s ease;
    }

    .notification-btn:hover,
    .notification-btn[aria-expanded="true"]{
        background:rgba(250,181,40,.16);
        border-color:rgba(250,181,40,.34);
    }

    .notification-btn i,
    .notification-btn svg{
        width:21px;
        height:21px;
        color:var(--yellow);
    }

    .notification-count{
        position:absolute;
        top:-6px;
        right:-6px;
        min-width:20px;
        height:20px;
        padding:0 5px;
        border-radius:999px;
        background:#ef4444;
        color:#fff;
        border:2px solid #000;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:.68rem;
        line-height:1;
        font-weight:800;
    }

    .notification-count[hidden]{display:none;}

    .notification-dropdown{
        position:absolute;
        top:calc(100% + 11px);
        right:0;
        width:min(390px,calc(100vw - 24px));
        max-height:min(560px,calc(100vh - 115px));
        background:#111;
        border:1px solid rgba(255,255,255,.10);
        border-radius:14px;
        box-shadow:0 20px 48px rgba(0,0,0,.40);
        overflow:hidden;
        opacity:0;
        visibility:hidden;
        transform:translateY(-7px);
        transition:opacity .18s ease,transform .18s ease,visibility .18s ease;
        z-index:95;
    }

    .notification-dropdown.is-open{
        opacity:1;
        visibility:visible;
        transform:translateY(0);
    }

    .notification-head{
        min-height:58px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding:0 15px 0 18px;
        border-bottom:1px solid rgba(255,255,255,.09);
    }

    .notification-head strong{
        color:#fff;
        font-size:1rem;
    }

    .notification-read-all{
        border:0;
        background:transparent;
        color:var(--yellow);
        font-size:.8rem;
        font-weight:700;
        cursor:pointer;
        padding:8px 0;
    }

    .notification-list{
        max-height:430px;
        overflow-y:auto;
    }

    .notification-item{
        position:relative;
        display:grid;
        grid-template-columns:38px minmax(0,1fr);
        gap:11px;
        padding:13px 15px;
        color:#fff;
        text-decoration:none;
        border-bottom:1px solid rgba(255,255,255,.07);
        transition:background .18s ease;
    }

    .notification-item:hover{background:rgba(255,255,255,.06);}
    .notification-item.is-unread{background:rgba(250,181,40,.09);}
    .notification-item.is-unread::after{
        content:"";
        position:absolute;
        top:18px;
        right:12px;
        width:7px;
        height:7px;
        border-radius:50%;
        background:var(--yellow);
    }

    .notification-icon{
        width:38px;
        height:38px;
        border-radius:11px;
        background:rgba(250,181,40,.16);
        display:flex;
        align-items:center;
        justify-content:center;
    }

    .notification-icon svg{width:19px;height:19px;color:var(--yellow);}
    .notification-content{min-width:0;padding-right:10px;}
    .notification-content strong{display:block;font-size:.88rem;line-height:1.3;color:#fff;margin-bottom:4px;}
    .notification-content span{display:block;color:rgba(255,255,255,.70);font-size:.79rem;line-height:1.35;}
    .notification-content small{display:block;color:var(--yellow);font-size:.72rem;font-weight:700;margin-top:6px;}

    .notification-empty{
        min-height:150px;
        padding:26px 18px;
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        gap:9px;
        text-align:center;
        color:rgba(255,255,255,.66);
        font-size:.86rem;
    }
    .notification-empty svg{width:30px;height:30px;color:rgba(250,181,40,.72);}

    .notification-footer{
        display:flex;
        align-items:center;
        justify-content:center;
        min-height:46px;
        color:var(--yellow);
        font-size:.85rem;
        font-weight:700;
        text-decoration:none;
        background:rgba(255,255,255,.025);
    }

    .menu-btn{
        width: 42px;
        height: 42px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,.08);
        background: rgba(255,255,255,.03);
        color: #fff;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        transition: .2s ease;
    }

    .menu-btn:hover{
        background: rgba(255,255,255,.08);
    }

    .menu-btn i,
    .menu-btn svg{
        width: 22px;
        height: 22px;
    }

    .menu-backdrop{
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.42);
        opacity:0;
        pointer-events:none;
        transition: opacity .22s ease;
        z-index: 65;
    }

    .menu-backdrop.is-open{
        opacity:1;
        pointer-events:auto;
    }

    .side-menu{
        position: fixed;
        top:0;
        right:0;
        width: min(252px, 86vw);
        height: 100dvh;
        background: var(--yellow);
        color:#fff;
        z-index: 80;
        transform: translateX(102%);
        transition: transform .24s ease;
        box-shadow: -16px 0 34px rgba(0,0,0,.28);
        overflow-y:auto;
        border-left: 1px solid rgba(0,0,0,.12);
    }

    .side-menu.is-open{
        transform: translateX(0);
    }

    .side-menu__head{
        height: 72px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        padding: 0 15px 0 22px;
        border-bottom: 1px solid rgba(0,0,0,.16);
        background: rgba(0,0,0,.03);
    }

    .side-menu__title{
        font-weight:700;
        font-size:1rem;
        letter-spacing:.02em;
        text-transform:uppercase;
    }

    .side-menu__close{
        width:38px;
        height:38px;
        border:0;
        border-radius:10px;
        background: rgba(0,0,0,.08);
        color:#fff;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
    }

    .side-menu__close i,
    .side-menu__close svg{
        width:22px;
        height:22px;
    }

    .side-menu__list{
        list-style:none;
        padding:0;
        margin:0;
    }

    .side-menu__link{
        min-height:46px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding: 0 22px;
        border-bottom: 1px solid rgba(0,0,0,.16);
        color:#fff;
        font-weight:700;
        font-size:1rem;
        line-height:1.15;
        text-shadow: 0 1px 0 rgba(0,0,0,.07);
        transition: background .18s ease;
        text-decoration:none;
    }

    .side-menu__link:hover{
        background: rgba(0,0,0,.10);
    }

    .side-menu__link i,
    .side-menu__link svg{
        width:20px;
        height:20px;
        flex:0 0 auto;
    }


    .side-menu__account{
        padding:14px 12px 18px;
        border-top:1px solid rgba(0,0,0,.18);
    }

    .side-menu__link--account{
        border-radius:12px;
        border-bottom:0;
        background:rgba(0,0,0,.10);
    }

    .side-menu__link--account:hover{
        background:rgba(0,0,0,.16);
    }

    .side-menu__account .side-menu__link + .side-menu__link{
        margin-top:8px;
    }

    @media (max-width: 980px){
        .topbar{
            height: 86px;
            padding: 0 14px 0 4px;
        }

        .topbar__logo img{
            width: 72px;
            max-height: 60px;
        }

        .user-pill{
            max-width: 180px;
            overflow:hidden;
            text-overflow:ellipsis;
        }
    }

    @media (max-width: 640px){
        .topbar{
            height: 80px;
        }

        .topbar__actions{
            gap: 10px;
        }

        .user-pill{
            padding: 0 12px;
            height: 40px;
            font-size: .92rem;
            max-width: 148px;
        }

        .user-pill .user-text{
            display:none;
        }

        .notification-btn{width:40px;height:40px;}
        .notification-dropdown{right:-52px;width:min(360px,calc(100vw - 16px));}

        .side-menu{
            width: 78vw;
        }
    }
</style>

<header class="topbar">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
    <a href="dashboard.php" class="topbar__logo" aria-label="Projeto Gabaritando">
        <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Projeto Gabaritando">
    </a>

    <div class="topbar__actions">
        <div class="user-menu">
            <button type="button" class="user-pill" id="userMenuButton" aria-haspopup="true" aria-expanded="false" title="<?php echo $usuarioLogado ? 'Logado como ' . htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') : 'Usuário não logado'; ?>">
                <i data-lucide="user-round"></i>
                <span class="user-text"><?php echo $usuarioLogado ? htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') : 'Não logado'; ?></span>
                <i data-lucide="chevron-down"></i>
            </button>

            <div class="user-dropdown" id="userDropdown" aria-label="Opções da conta">
                <?php if ($usuarioLogado): ?>
                    <?php if ($usuarioEhAdmin): ?>
                        <a class="user-dropdown__link" href="admin-dashboard.php">
                            <i data-lucide="shield-check"></i>
                            <span>Ir para admin</span>
                        </a>
                    <?php endif; ?>
                    <a class="user-dropdown__link" href="logout.php">
                        <i data-lucide="log-out"></i>
                        <span>Sair da conta</span>
                    </a>
                <?php else: ?>
                    <a class="user-dropdown__link" href="login.php">
                        <i data-lucide="log-in"></i>
                        <span>Entrar</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($usuarioLogado): ?>
            <div class="notification-menu">
                <button type="button" class="notification-btn" id="notificationButton" aria-label="Notificações" aria-haspopup="true" aria-expanded="false">
                    <i data-lucide="bell"></i>
                    <span class="notification-count" id="notificationCount"<?php echo $totalNotificacoesNaoLidas <= 0 ? ' hidden' : ''; ?>><?php echo $totalNotificacoesNaoLidas > 99 ? '99+' : (int)$totalNotificacoesNaoLidas; ?></span>
                </button>

                <div class="notification-dropdown" id="notificationDropdown" aria-label="Lista de notificações">
                    <div class="notification-head">
                        <strong>Notificações</strong>
                        <?php if ($totalNotificacoesNaoLidas > 0): ?>
                            <button type="button" class="notification-read-all" id="notificationReadAll">Marcar todas como lidas</button>
                        <?php endif; ?>
                    </div>

                    <div class="notification-list" id="notificationList">
                        <?php if (!$notificacoesTopbar): ?>
                            <div class="notification-empty">
                                <i data-lucide="bell-off"></i>
                                <span>Nenhuma novidade por enquanto.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notificacoesTopbar as $notificacaoTopbar): ?>
                                <a
                                    class="notification-item<?php echo empty($notificacaoTopbar['lida']) ? ' is-unread' : ''; ?>"
                                    href="<?php echo htmlspecialchars((string)$notificacaoTopbar['link'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notification-type="<?php echo htmlspecialchars((string)$notificacaoTopbar['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-notification-id="<?php echo (int)$notificacaoTopbar['referencia_id']; ?>">
                                    <span class="notification-icon"><i data-lucide="<?php echo htmlspecialchars((string)$notificacaoTopbar['icone'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                    <span class="notification-content">
                                        <strong><?php echo htmlspecialchars((string)$notificacaoTopbar['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if (!empty($notificacaoTopbar['descricao'])): ?><span><?php echo htmlspecialchars((string)$notificacaoTopbar['descricao'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                        <small><?php echo htmlspecialchars((string)$notificacaoTopbar['tempo'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a class="notification-footer" href="agenda.php">Abrir agenda</a>
                </div>
            </div>
        <?php endif; ?>

        <button type="button" class="menu-btn" aria-label="Abrir menu">
            <i data-lucide="menu"></i>
        </button>
    </div>
</header>
<?php if (!empty($missaoTopo) && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'login.php'): $mt = $missaoTopo; ?>
<style>
    .msb{position:sticky;top:0;z-index:40;display:flex;align-items:center;gap:12px;padding:8px max(16px,env(safe-area-inset-left));background:#17130a;border-bottom:1px solid rgba(251,185,40,.35);color:#f3f3f4;font-family:'Ubuntu',system-ui,sans-serif}
    .msb-txt{display:grid;min-width:0;flex:1;line-height:1.25}
    .msb-txt small{font-size:11px;color:#fbb928;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
    .msb-txt span{font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .msb-bolas{display:inline-flex;gap:5px}
    .msb-bolas i{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.18)}
    .msb-bolas i.feito{background:#22c55e}
    .msb-bolas i.agora{background:#fbb928;box-shadow:0 0 0 3px rgba(251,185,40,.3)}
    .msb-bt{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:9px 14px;background:#fbb928;color:#111;font-weight:700;font-size:14px;text-decoration:none;white-space:nowrap}
    .msb-bt:focus-visible{outline:2px solid #fff;outline-offset:2px}
    @media(max-width:600px){.msb{gap:8px;padding:8px 12px}.msb-bolas{display:none}.msb-bt{padding:9px 12px;font-size:13px}}
</style>
<nav class="msb" aria-label="Sua missão">
    <?php echo missao_bolinhas((int)$mt['passo']); ?>
    <div class="msb-txt">
        <small><?php echo MISSAO_ICONES[$mt['tipo']] ?? '🎯'; ?> Passo <?php echo (int)$mt['passo']; ?> de 4 · <?php echo htmlspecialchars(MISSAO_PASSOS[(int)$mt['passo']] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
        <span><strong style="color:#fbb928">Antigão:</strong> <?php echo htmlspecialchars($mt['frase'], ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <a class="msb-bt" href="<?php echo htmlspecialchars($mt['url'], ENT_QUOTES, 'UTF-8'); ?>">▶ Continuar</a>
</nav>
<?php endif; ?>
<?php if (!empty($mostrarTourMetodo)): ?>
<style>
    .tour{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.78);font-family:'Ubuntu',system-ui,sans-serif}
    .tour-box{width:100%;max-width:520px;background:#141416;border:1px solid rgba(251,185,40,.45);border-radius:20px;padding:24px;color:#f3f3f4;display:grid;gap:14px;text-align:center}
    .tour-box img{height:64px;width:auto;justify-self:center}
    .tour-box h2{font-size:22px;margin:0}
    .tour-box p{margin:0;color:#c9c9cf;font-size:15.5px;line-height:1.5}
    .tour-box p strong{color:#fbb928}
    .tour-ico{font-size:46px;line-height:1}
    .tour-pontos{display:flex;justify-content:center;gap:6px}
    .tour-pontos i{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.2)}
    .tour-pontos i.on{background:#fbb928}
    .tour-acoes{display:flex;justify-content:space-between;gap:10px}
    .tour-acoes button{border:0;border-radius:12px;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
    .tour-pular{background:transparent;color:#a3a3ab}
    .tour-prox{background:#fbb928;color:#111;flex:1}
</style>
<div class="tour" id="tourMetodo" role="dialog" aria-modal="true" aria-labelledby="tourTitulo">
    <div class="tour-box">
        <img src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="Projeto Gabaritando">
        <div class="tour-ico" id="tourIco" aria-hidden="true">🎖</div>
        <h2 id="tourTitulo"></h2>
        <p id="tourTexto"></p>
        <div class="tour-pontos" id="tourPontos" aria-hidden="true"></div>
        <div class="tour-acoes"><button type="button" class="tour-pular" id="tourPular">Pular</button><button type="button" class="tour-prox" id="tourProx">Próximo</button></div>
    </div>
</div>
<script>
(function () {
    var telas = [
        ['🎖', 'Fala, combatente! Aqui é o Antigão.', 'Eu vou te guiar até a farda. Aqui o estudo segue <strong>um ciclo de 4 passos</strong>, sempre na mesma ordem. Você não precisa decorar nada: é só apertar <strong>▶ Continuar</strong>, no topo de qualquer página.'],
        ['📘', '1. Teoria mínima', 'Escolha <strong>um</strong> jeito: a videoaula do professor, a minha videoaula ou o PDF (com marca-texto). O podcast é um <strong>extra</strong> para revisar no ônibus. Terminou? Marque <strong>assistido</strong>: eu já te levo para o passo 2.'],
        ['✅', '2. Questões comentadas', 'Resolva <strong>no mínimo 5 questões</strong> do assunto para liberar o próximo. A meta para dominar é <strong>50</strong>. Errou? Leia o comentário: é aí que você aprende.'],
        ['🔁', '3. Revisões', 'Eu marco revisões rápidas <strong>1, 7 e 30 dias depois</strong>. São 10 minutos que fazem você não esquecer na prova.'],
        ['📝', '4. Simulado e Redação', 'Toda semana tem simulado no tempo de prova e redação. E toda semana seu mentor confere seus números e ajusta seu plano.<br><br><strong>Fé na missão: a farda é questão de tempo!</strong>']
    ];
    var i = 0, el = document.getElementById('tourMetodo');
    function mostrar() {
        document.getElementById('tourIco').textContent = telas[i][0];
        document.getElementById('tourTitulo').textContent = telas[i][1];
        document.getElementById('tourTexto').innerHTML = telas[i][2];
        document.getElementById('tourPontos').innerHTML = telas.map(function (_, k) { return '<i class="' + (k === i ? 'on' : '') + '"></i>'; }).join('');
        document.getElementById('tourProx').textContent = i === telas.length - 1 ? 'Começar minha missão ▶' : 'Próximo';
        document.getElementById('tourProx').focus();
    }
    function fechar(irParaMissao) {
        fetch('api/missao.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({acao: 'tour_visto'})}).catch(function () {});
        el.remove();
        var bt = document.querySelector('.msb-bt');
        if (irParaMissao && bt) { location.href = bt.getAttribute('href'); }
    }
    document.getElementById('tourProx').addEventListener('click', function () { if (i < telas.length - 1) { i++; mostrar(); } else { fechar(true); } });
    document.getElementById('tourPular').addEventListener('click', function () { fechar(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.getElementById('tourMetodo')) { fechar(false); } });
    mostrar();
})();
</script>
<?php endif; ?>

<div class="menu-backdrop" id="menuBackdrop" aria-hidden="true"></div>
<aside class="side-menu" id="sideMenu" aria-hidden="true" aria-label="Menu lateral">
    <div class="side-menu__head">
        <span class="side-menu__title">Menu</span>
        <button type="button" class="side-menu__close" id="closeMenu" aria-label="Fechar menu">
            <i data-lucide="x"></i>
        </button>
    </div>
    <ul class="side-menu__list">
        <?php foreach ($menuItens as $item): ?>
            <li>
                <a class="side-menu__link" href="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $item['externo'] ? ' target="_blank" rel="noopener"' : ''; ?>>
                    <span><?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($item['externo']): ?>
                        <i data-lucide="external-link"></i>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li class="side-menu__account">
            <?php if ($usuarioLogado): ?>
                <?php if ($usuarioEhAdmin): ?>
                    <a class="side-menu__link side-menu__link--account" href="admin-dashboard.php">
                        <span>Ir para admin</span>
                        <i data-lucide="shield-check"></i>
                    </a>
                <?php endif; ?>
                <a class="side-menu__link side-menu__link--account" href="logout.php">
                    <span>Sair da conta</span>
                    <i data-lucide="log-out"></i>
                </a>
            <?php else: ?>
                <a class="side-menu__link side-menu__link--account" href="login.php">
                    <span>Entrar</span>
                    <i data-lucide="log-in"></i>
                </a>
            <?php endif; ?>
        </li>
    </ul>
</aside>

<script>
    (function () {
        function iniciarBarraSuperior() {
            if (window.lucide) {
                window.lucide.createIcons();
            }

            const menuBtn = document.querySelector('.menu-btn');
            const sideMenu = document.getElementById('sideMenu');
            const menuBackdrop = document.getElementById('menuBackdrop');
            const closeMenu = document.getElementById('closeMenu');
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            const notificationButton = document.getElementById('notificationButton');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationReadAll = document.getElementById('notificationReadAll');

            function fecharDropdownUsuario() {
                if (!userDropdown || !userMenuButton) return;
                userDropdown.classList.remove('is-open');
                userMenuButton.setAttribute('aria-expanded', 'false');
            }

            function alternarDropdownUsuario(event) {
                event.stopPropagation();
                if (!userDropdown || !userMenuButton) return;
                fecharDropdownNotificacoes();
                const abriu = !userDropdown.classList.contains('is-open');
                userDropdown.classList.toggle('is-open', abriu);
                userMenuButton.setAttribute('aria-expanded', abriu ? 'true' : 'false');
            }

            function fecharDropdownNotificacoes() {
                if (!notificationDropdown || !notificationButton) return;
                notificationDropdown.classList.remove('is-open');
                notificationButton.setAttribute('aria-expanded', 'false');
            }

            function alternarDropdownNotificacoes(event) {
                event.stopPropagation();
                if (!notificationDropdown || !notificationButton) return;
                fecharDropdownUsuario();
                const abriu = !notificationDropdown.classList.contains('is-open');
                notificationDropdown.classList.toggle('is-open', abriu);
                notificationButton.setAttribute('aria-expanded', abriu ? 'true' : 'false');
            }

            function atualizarContadorNotificacoes() {
                if (!notificationCount) return;
                const total = document.querySelectorAll('.notification-item.is-unread').length;
                notificationCount.hidden = total <= 0;
                notificationCount.textContent = total > 99 ? '99+' : String(total);
                if (notificationReadAll && total <= 0) notificationReadAll.hidden = true;
            }

            function marcarNotificacao(tipo, referenciaId) {
                const dados = new FormData();
                dados.append('acao', 'marcar_lida');
                dados.append('tipo', tipo || '');
                dados.append('referencia_id', referenciaId || '0');
                return fetch('api/notificacoes.php', {method:'POST', body:dados, keepalive:true});
            }

            function abrirMenu() {
                fecharDropdownUsuario();
                fecharDropdownNotificacoes();
                if (!sideMenu || !menuBackdrop) return;
                sideMenu.classList.add('is-open');
                menuBackdrop.classList.add('is-open');
                sideMenu.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function fecharMenu() {
                if (!sideMenu || !menuBackdrop) return;
                sideMenu.classList.remove('is-open');
                menuBackdrop.classList.remove('is-open');
                sideMenu.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            if (userMenuButton && !userMenuButton.dataset.barraSuperiorReady) {
                userMenuButton.addEventListener('click', alternarDropdownUsuario);
                userMenuButton.dataset.barraSuperiorReady = '1';
            }

            if (notificationButton && !notificationButton.dataset.barraSuperiorReady) {
                notificationButton.addEventListener('click', alternarDropdownNotificacoes);
                notificationButton.dataset.barraSuperiorReady = '1';
            }

            document.querySelectorAll('.notification-item').forEach(function(item) {
                if (item.dataset.notificationReady) return;
                item.addEventListener('click', function() {
                    if (!item.classList.contains('is-unread')) return;
                    item.classList.remove('is-unread');
                    atualizarContadorNotificacoes();
                    marcarNotificacao(item.dataset.notificationType || '', item.dataset.notificationId || '0').catch(function(){});
                });
                item.dataset.notificationReady = '1';
            });

            if (notificationReadAll && !notificationReadAll.dataset.notificationReady) {
                notificationReadAll.addEventListener('click', function(event) {
                    event.stopPropagation();
                    document.querySelectorAll('.notification-item.is-unread').forEach(function(item) { item.classList.remove('is-unread'); });
                    atualizarContadorNotificacoes();
                    const dados = new FormData();
                    dados.append('acao', 'marcar_todas');
                    fetch('api/notificacoes.php', {method:'POST', body:dados}).catch(function(){});
                });
                notificationReadAll.dataset.notificationReady = '1';
            }

            if (!document.body.dataset.barraSuperiorContaReady) {
                document.addEventListener('click', function(event) {
                    if (userDropdown && userMenuButton && !userDropdown.contains(event.target) && !userMenuButton.contains(event.target)) {
                        fecharDropdownUsuario();
                    }
                    if (notificationDropdown && notificationButton && !notificationDropdown.contains(event.target) && !notificationButton.contains(event.target)) {
                        fecharDropdownNotificacoes();
                    }
                });
                document.body.dataset.barraSuperiorContaReady = '1';
            }

            if (menuBtn && !menuBtn.dataset.barraSuperiorReady) {
                menuBtn.addEventListener('click', abrirMenu);
                menuBtn.dataset.barraSuperiorReady = '1';
            }
            if (closeMenu && !closeMenu.dataset.barraSuperiorReady) {
                closeMenu.addEventListener('click', fecharMenu);
                closeMenu.dataset.barraSuperiorReady = '1';
            }
            if (menuBackdrop && !menuBackdrop.dataset.barraSuperiorReady) {
                menuBackdrop.addEventListener('click', fecharMenu);
                menuBackdrop.dataset.barraSuperiorReady = '1';
            }
            if (!document.body.dataset.barraSuperiorEscReady) {
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        fecharMenu();
                        fecharDropdownUsuario();
                        fecharDropdownNotificacoes();
                    }
                });
                document.body.dataset.barraSuperiorEscReady = '1';
            }
        }

        if (window.lucide) {
            iniciarBarraSuperior();
            return;
        }

        const scriptLucideExistente = document.querySelector('script[src*="lucide"]');
        if (scriptLucideExistente) {
            scriptLucideExistente.addEventListener('load', iniciarBarraSuperior, { once: true });
            setTimeout(iniciarBarraSuperior, 600);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/lucide@latest';
        script.onload = iniciarBarraSuperior;
        document.head.appendChild(script);
    })();
</script>
