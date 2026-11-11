<?php
// admin-topbar.php
// Barra superior administrativa com logo, notificações e saída da conta.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('auth_admin_url')) {
    require_once dirname(__DIR__) . '/api/admin-auth.php';
}

$adminNomeTopbar = (string)(
    $_SESSION['admin_nome']
    ?? $_SESSION['usuario_nome']
    ?? ($_SESSION['usuario']['nome'] ?? '')
    ?? 'Admin'
);
$adminTipoTopbar = strtolower(trim((string)(
    $_SESSION['usuario_tipo']
    ?? $_SESSION['admin_tipo']
    ?? ($_SESSION['usuario']['tipo'] ?? '')
    ?? ''
)));
$adminLogadoTopbar = !empty($_SESSION['admin_logado']) || in_array($adminTipoTopbar, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@0.469.0"></script>
<script src="<?php echo htmlspecialchars(auth_admin_url('assets/js/gabaritando-icons.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<style>
    :root{
        --admin-topbar-preto:#111111;
        --admin-topbar-amarelo:#fdbd08;
        --admin-topbar-branco:#ffffff;
        --admin-topbar-texto:#ffffff;
        --admin-topbar-muted:rgba(255,255,255,.78);
        --admin-topbar-hover:rgba(255,255,255,.10);
        --admin-topbar-borda:rgba(253,189,8,.42);
    }

    .admin-topbar,
    .admin-topbar *{
        box-sizing:border-box;
        font-family:'Ubuntu', Arial, sans-serif;
    }

    .admin-topbar{
        width:100%;
        min-height:74px;
        background:var(--admin-topbar-preto);
        color:var(--admin-topbar-texto);
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:0 34px;
        border-bottom:4px solid var(--admin-topbar-amarelo);
        position:relative;
        z-index:50;
    }

    .admin-topbar-logo-link{
        display:flex;
        align-items:center;
        justify-content:flex-start;
        text-decoration:none;
        min-width:0;
    }

    .admin-topbar-logo{
        display:block;
        max-width:214px;
        width:auto;
        height:46px;
        object-fit:contain;
    }

    .admin-topbar-actions{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:12px;
        flex:0 0 auto;
    }

    .admin-topbar-icon-btn,
    .admin-topbar-logout{
        min-height:42px;
        border:1px solid transparent;
        border-radius:10px;
        background:transparent;
        color:var(--admin-topbar-texto);
        display:flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        cursor:pointer;
        transition:background .2s ease, border-color .2s ease, transform .2s ease;
    }

    .admin-topbar-icon-btn{
        width:42px;
        position:relative;
    }

    .admin-topbar-icon-btn:hover,
    .admin-topbar-logout:hover{
        background:var(--admin-topbar-hover);
        border-color:var(--admin-topbar-borda);
        transform:translateY(-1px);
    }

    .admin-topbar-icon-btn svg,
    .admin-topbar-logout svg{
        width:21px;
        height:21px;
        stroke-width:2.4;
        color:var(--admin-topbar-amarelo);
    }

    .admin-topbar-notification-dot{
        position:absolute;
        top:9px;
        right:9px;
        width:8px;
        height:8px;
        border-radius:999px;
        background:var(--admin-topbar-amarelo);
        border:2px solid var(--admin-topbar-preto);
    }

    .admin-topbar-logout{
        gap:8px;
        padding:0 14px;
        font-size:.94rem;
        font-weight:700;
        line-height:1;
    }

    .admin-topbar-logout span{
        color:var(--admin-topbar-texto);
        white-space:nowrap;
    }

    .admin-user-menu{
        position:relative;
        display:inline-flex;
        align-items:center;
    }

    .admin-user-pill{
        min-height:42px;
        border:1px solid var(--admin-topbar-borda);
        border-radius:999px;
        background:rgba(253,189,8,.12);
        color:#ffffff;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:9px;
        padding:0 13px;
        cursor:pointer;
        font-size:.94rem;
        font-weight:700;
        line-height:1;
        transition:background .2s ease, transform .2s ease, border-color .2s ease;
        max-width:260px;
    }

    .admin-user-pill:hover,
    .admin-user-pill:focus-visible{
        background:rgba(253,189,8,.18);
        transform:translateY(-1px);
        outline:none;
    }

    .admin-user-pill svg{
        width:18px;
        height:18px;
        color:var(--admin-topbar-amarelo);
        flex:0 0 auto;
    }

    .admin-user-pill span{
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
        min-width:0;
    }

    .admin-user-dropdown{
        position:absolute;
        top:calc(100% + 10px);
        right:0;
        min-width:220px;
        background:#111111;
        border:1px solid rgba(255,255,255,.10);
        border-radius:12px;
        box-shadow:0 18px 36px rgba(0,0,0,.32);
        padding:8px;
        opacity:0;
        visibility:hidden;
        transform:translateY(-6px);
        transition:opacity .18s ease, transform .18s ease, visibility .18s ease;
        z-index:100;
    }

    .admin-user-dropdown.is-open{
        opacity:1;
        visibility:visible;
        transform:translateY(0);
    }

    .admin-user-dropdown::before{
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

    .admin-user-dropdown__link{
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

    .admin-user-dropdown__link:hover{
        background:rgba(253,189,8,.16);
        color:var(--admin-topbar-amarelo);
    }

    .admin-user-dropdown__link svg{
        width:18px;
        height:18px;
        color:var(--admin-topbar-amarelo);
        flex:0 0 auto;
    }

    @media(max-width:620px){
        .admin-topbar{
            min-height:66px;
            padding:0 14px;
        }

        .admin-topbar-logo{
            max-width:172px;
            height:40px;
        }

        .admin-topbar-actions{
            gap:8px;
        }

        .admin-topbar-icon-btn{
            width:40px;
            min-height:40px;
        }

        .admin-topbar-logout{
            width:40px;
            min-height:40px;
            padding:0;
        }

        .admin-user-pill{
            width:40px;
            min-height:40px;
            padding:0;
            border-radius:12px;
        }

        .admin-user-pill span,
        .admin-user-pill [data-lucide="chevron-down"]{
            display:none;
        }

        .admin-user-dropdown{
            right:-4px;
            min-width:210px;
        }

        .admin-topbar-logout span{
            display:none;
        }
    }
</style>

<header class="admin-topbar" aria-label="Barra superior administrativa">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
    <a class="admin-topbar-logo-link" href="<?php echo htmlspecialchars(auth_admin_url('admin-dashboard'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ir para o painel administrativo">
        <img class="admin-topbar-logo" src="<?php echo htmlspecialchars(auth_admin_url('img/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Projeto Gabaritando">
    </a>

    <div class="admin-topbar-actions">
        <a class="admin-topbar-icon-btn" href="<?php echo htmlspecialchars(auth_admin_url('admin-mensagens.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mensagens dos alunos" title="Mensagens dos alunos">
            <i data-lucide="bell" aria-hidden="true"></i>
            <span class="admin-topbar-notification-dot" aria-hidden="true"></span>
        </a>

        <div class="admin-user-menu">
            <button class="admin-user-pill" type="button" id="adminUserMenuButton" aria-haspopup="true" aria-expanded="false" title="<?php echo htmlspecialchars($adminNomeTopbar, ENT_QUOTES, 'UTF-8'); ?>">
                <i data-lucide="user-round" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($adminNomeTopbar, ENT_QUOTES, 'UTF-8'); ?></span>
                <i data-lucide="chevron-down" aria-hidden="true"></i>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown" aria-label="Opções da conta administrativa">
                <?php if ($adminLogadoTopbar): ?>
                    <a class="admin-user-dropdown__link" href="<?php echo htmlspecialchars(auth_admin_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">
                        <i data-lucide="graduation-cap" aria-hidden="true"></i>
                        <span>Ir para cliente</span>
                    </a>
                <?php endif; ?>
                <a class="admin-user-dropdown__link" href="<?php echo htmlspecialchars(auth_admin_url('admin-logout'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i data-lucide="log-out" aria-hidden="true"></i>
                    <span>Sair da conta</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    (function(){
        function iniciarAdminTopbar(){
            if (window.lucide) {
                window.lucide.createIcons();
            }

            const botao = document.getElementById('adminUserMenuButton');
            const dropdown = document.getElementById('adminUserDropdown');

            if (!botao || !dropdown || botao.dataset.adminDropdownReady) {
                return;
            }

            function fechar(){
                dropdown.classList.remove('is-open');
                botao.setAttribute('aria-expanded', 'false');
            }

            botao.addEventListener('click', function(event){
                event.stopPropagation();
                const abrir = !dropdown.classList.contains('is-open');
                dropdown.classList.toggle('is-open', abrir);
                botao.setAttribute('aria-expanded', abrir ? 'true' : 'false');
            });

            document.addEventListener('click', function(event){
                if (!dropdown.contains(event.target) && !botao.contains(event.target)) {
                    fechar();
                }
            });

            document.addEventListener('keydown', function(event){
                if (event.key === 'Escape') {
                    fechar();
                }
            });

            botao.dataset.adminDropdownReady = '1';
        }

        if (window.lucide) {
            iniciarAdminTopbar();
            return;
        }

        window.addEventListener('load', iniciarAdminTopbar);
    })();
</script>
