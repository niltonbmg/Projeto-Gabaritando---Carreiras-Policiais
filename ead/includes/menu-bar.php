<?php
// menu-bar.php
// Menu lateral administrativo separado para ser usado nos arquivos do painel.
declare(strict_types=1);

$currentAdminPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$currentAdminSlug = preg_replace('/\.php$/', '', $currentAdminPage);

$adminMenuItems = [
    ['label' => 'Dashboard', 'href' => 'admin-dashboard.php', 'icon' => 'layout-dashboard', 'pages' => ['admin-dashboard.php', 'admin-dashboard']],
    ['label' => 'Curso', 'href' => 'admin-cursos.php', 'icon' => 'graduation-cap', 'pages' => ['admin-cursos.php', 'admin-cursos', 'admin-curso-modulos.php', 'admin-curso-modulos', 'admin-modulo-conteudos.php', 'admin-modulo-conteudos']],
    ['label' => 'Materiais', 'href' => 'admin-materiais.php', 'icon' => 'book-open', 'pages' => ['admin-materiais.php', 'admin-materiais']],
    ['label' => 'Simulados', 'href' => 'admin-simulado.php', 'icon' => 'clipboard-check', 'pages' => ['admin-simulado.php', 'admin-simulado']],
    ['label' => 'Plano-Mestre META', 'href' => 'admin-meta-plano.php', 'icon' => 'route', 'pages' => ['admin-meta-plano.php', 'admin-meta-plano']],
    ['label' => 'Cronograma', 'href' => 'admin-cronograma.php', 'icon' => 'calendar-days', 'pages' => ['admin-cronograma.php', 'admin-cronograma']],
    ['label' => 'Recompensas', 'href' => 'admin-recompensas.php', 'icon' => 'trophy', 'pages' => ['admin-recompensas.php', 'admin-recompensas']],
    ['label' => 'Banco de questões', 'href' => 'admin-banco-questoes.php', 'icon' => 'circle-help', 'pages' => ['admin-banco-questoes.php', 'admin-banco-questoes']],
    ['label' => 'Concurso Passado', 'href' => 'admin-concursos-passados.php', 'icon' => 'archive', 'pages' => ['admin-concursos-passados.php', 'admin-concursos-passados']],
    ['label' => 'Bancas', 'href' => 'admin-bancas.php', 'icon' => 'building-2', 'pages' => ['admin-bancas.php', 'admin-bancas']],
    ['label' => 'Matérias', 'href' => 'admin-materias.php', 'icon' => 'library', 'pages' => ['admin-materias.php', 'admin-materias']],
    ['label' => 'Agenda', 'href' => 'admin-agenda.php', 'icon' => 'calendar-days', 'pages' => ['admin-agenda.php', 'admin-agenda']],
    ['label' => 'Vendas', 'href' => 'admin-dashboard.php#vendas', 'icon' => 'shopping-cart', 'pages' => []],
    ['label' => 'Usuários', 'href' => 'admin-usuarios.php', 'icon' => 'users', 'pages' => ['admin-usuarios.php', 'admin-usuarios']],
    ['label' => 'Mensagens', 'href' => 'admin-mensagens.php', 'icon' => 'message-square-text', 'pages' => ['admin-mensagens.php', 'admin-mensagens']],
    ['label' => 'Produtos', 'href' => 'admin-dashboard.php#produtos', 'icon' => 'package', 'pages' => []],
    ['label' => 'Configurações', 'href' => 'admin-configuracoes.php', 'icon' => 'settings', 'pages' => ['admin-configuracoes.php', 'admin-configuracoes']],
];

if (!function_exists('admin_menu_esc')) {
    function admin_menu_esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}
?>
<style>
    :root{
        --admin-dash-preto:#111111;
        --admin-dash-amarelo:#fdbd08;
        --admin-dash-amarelo-escuro:#d89d00;
        --admin-dash-fundo:#f3f4f6;
        --admin-dash-card:#ffffff;
        --admin-dash-texto:#1f2937;
        --admin-dash-muted:#667085;
        --admin-dash-borda:#e4e7ec;
    }

    .admin-dashboard-shell{
        width:100%;
        min-height:calc(100vh - 78px);
        display:flex;
        align-items:stretch;
        background:var(--admin-dash-fundo);
    }

    .admin-sidebar,
    .admin-sidebar *{
        box-sizing:border-box;
        font-family:'Ubuntu', Arial, sans-serif;
    }

    .admin-sidebar{
        width:248px;
        background:#ffffff;
        border-right:1px solid var(--admin-dash-borda);
        padding:24px 14px;
        flex:0 0 248px;
        box-shadow:4px 0 18px rgba(15,23,42,.035);
    }

    .admin-sidebar-title{
        color:var(--admin-dash-muted);
        font-size:.76rem;
        font-weight:700;
        text-transform:uppercase;
        letter-spacing:.08em;
        padding:0 14px;
        margin-bottom:12px;
    }

    .admin-menu{
        width:100%;
        display:grid;
        gap:8px;
        list-style:none;
        margin:0;
        padding:0;
    }

    .admin-menu-link{
        width:100%;
        min-height:46px;
        border-radius:12px;
        text-decoration:none;
        color:#344054;
        display:flex;
        align-items:center;
        gap:12px;
        padding:0 14px;
        font-size:.96rem;
        font-weight:700;
        transition:background .2s ease, color .2s ease, transform .2s ease;
    }

    .admin-menu-link svg{
        width:20px;
        height:20px;
        color:#98a2b3;
        stroke-width:2.3;
        flex:0 0 auto;
        transition:color .2s ease;
    }

    .admin-menu-link:hover,
    .admin-menu-link.active{
        background:#fff7db;
        color:var(--admin-dash-preto);
        transform:translateX(2px);
    }

    .admin-menu-link:hover svg,
    .admin-menu-link.active svg{
        color:var(--admin-dash-amarelo-escuro);
    }

    @media(max-width:1024px){
        .admin-dashboard-shell{
            flex-direction:column;
        }

        .admin-sidebar{
            width:100%;
            flex:0 0 auto;
            border-right:0;
            border-bottom:1px solid var(--admin-dash-borda);
            padding:14px 16px;
            overflow-x:auto;
        }

        .admin-sidebar-title{
            display:none;
        }

        .admin-menu{
            display:flex;
            gap:10px;
            min-width:max-content;
        }

        .admin-menu-link{
            width:auto;
            min-width:max-content;
            padding:0 15px;
        }

        .admin-menu-link:hover,
        .admin-menu-link.active{
            transform:none;
        }
    }
</style>

<aside class="admin-sidebar" aria-label="Menu administrativo">
    <div class="admin-sidebar-title">Painel</div>
    <nav>
        <ul class="admin-menu">
            <?php foreach ($adminMenuItems as $item): ?>
                <?php $active = in_array($currentAdminPage, $item['pages'], true) || in_array((string)$currentAdminSlug, $item['pages'], true); ?>
                <li>
                    <a class="admin-menu-link<?php echo $active ? ' active' : ''; ?>" href="<?php echo admin_menu_esc($item['href']); ?>">
                        <i data-lucide="<?php echo admin_menu_esc($item['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo admin_menu_esc($item['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
