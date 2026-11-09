<?php
// menu-bar.php
// Menu lateral administrativo separado para ser usado nos arquivos do painel.
declare(strict_types=1);

$currentAdminPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$currentAdminSlug = preg_replace('/\.php$/', '', $currentAdminPage);

// Menu em grupos (ver includes/menu-bar.php). O grupo da página atual abre sozinho.
$adminMenuGrupos = [
    ['titulo' => '', 'itens' => [
        ['label' => 'Dashboard', 'href' => 'admin-dashboard.php', 'icon' => 'layout-dashboard', 'pages' => ['admin-dashboard.php', 'admin-dashboard']],
    ]],
    ['titulo' => 'Método META', 'itens' => [
        ['label' => 'Plano-Mestre', 'href' => 'admin-meta-plano.php', 'icon' => 'route', 'pages' => ['admin-meta-plano.php', 'admin-meta-plano']],
        ['label' => 'Cronograma (antigo)', 'href' => 'admin-cronograma.php', 'icon' => 'calendar-days', 'pages' => ['admin-cronograma.php', 'admin-cronograma']],
        ['label' => 'Recompensas', 'href' => 'admin-recompensas.php', 'icon' => 'trophy', 'pages' => ['admin-recompensas.php', 'admin-recompensas']],
    ]],
    ['titulo' => 'Conteúdo', 'itens' => [
        ['label' => 'Cadastro do concurso', 'href' => 'admin-editais.php', 'icon' => 'file-badge', 'pages' => ['admin-editais.php', 'admin-editais']],
        ['label' => 'Cursos e aulas', 'href' => 'admin-cursos.php', 'icon' => 'graduation-cap', 'pages' => ['admin-cursos.php', 'admin-cursos', 'admin-curso-modulos.php', 'admin-curso-modulos', 'admin-modulo-conteudos.php', 'admin-modulo-conteudos']],
        ['label' => 'Materiais', 'href' => 'admin-materiais.php', 'icon' => 'book-open', 'pages' => ['admin-materiais.php', 'admin-materiais']],
        ['label' => 'Confirmar classificação', 'href' => 'admin-classificacao.php', 'icon' => 'tags', 'pages' => ['admin-classificacao.php', 'admin-classificacao']],
        ['label' => 'Indicações', 'href' => 'admin-indicacoes.php', 'icon' => 'clapperboard', 'pages' => ['admin-indicacoes.php', 'admin-indicacoes']],
        ['label' => 'Matérias', 'href' => 'admin-materias.php', 'icon' => 'library', 'pages' => ['admin-materias.php', 'admin-materias']],
    ]],
    ['titulo' => 'Questões e provas', 'itens' => [
        ['label' => 'Banco de questões', 'href' => 'admin-banco-questoes.php', 'icon' => 'circle-help', 'pages' => ['admin-banco-questoes.php', 'admin-banco-questoes']],
        ['label' => 'Simulados', 'href' => 'admin-simulado.php', 'icon' => 'clipboard-check', 'pages' => ['admin-simulado.php', 'admin-simulado']],
        ['label' => 'Concursos passados', 'href' => 'admin-concursos-passados.php', 'icon' => 'archive', 'pages' => ['admin-concursos-passados.php', 'admin-concursos-passados']],
        ['label' => 'Bancas', 'href' => 'admin-bancas.php', 'icon' => 'building-2', 'pages' => ['admin-bancas.php', 'admin-bancas']],
    ]],
    ['titulo' => 'Alunos', 'itens' => [
        ['label' => 'Redação', 'href' => 'admin-redacao.php', 'icon' => 'pen-line', 'pages' => ['admin-redacao.php', 'admin-redacao']],
        ['label' => 'Desafio do dia', 'href' => 'admin-desafio.php', 'icon' => 'target', 'pages' => ['admin-desafio.php', 'admin-desafio']],
        ['label' => 'Usuários', 'href' => 'admin-usuarios.php', 'icon' => 'users', 'pages' => ['admin-usuarios.php', 'admin-usuarios']],
        ['label' => 'Mensagens', 'href' => 'admin-mensagens.php', 'icon' => 'message-square-text', 'pages' => ['admin-mensagens.php', 'admin-mensagens']],
        ['label' => 'Agenda', 'href' => 'admin-agenda.php', 'icon' => 'calendar-days', 'pages' => ['admin-agenda.php', 'admin-agenda']],
    ]],
    ['titulo' => 'Gestão', 'itens' => [
        ['label' => 'Vendas', 'href' => 'admin-dashboard.php#vendas', 'icon' => 'shopping-cart', 'pages' => []],
        ['label' => 'Produtos', 'href' => 'admin-dashboard.php#produtos', 'icon' => 'package', 'pages' => []],
        ['label' => 'Diagnóstico da IA', 'href' => 'admin-ia.php', 'icon' => 'sparkles', 'pages' => ['admin-ia.php', 'admin-ia']],
        ['label' => 'Configurações', 'href' => 'admin-configuracoes.php', 'icon' => 'settings', 'pages' => ['admin-configuracoes.php', 'admin-configuracoes']],
    ]],
];
// Lista plana (compatibilidade com quem ainda use $adminMenuItems).
$adminMenuItems = array_merge(...array_column($adminMenuGrupos, 'itens'));

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
    .admin-menu-grupo{margin-top:6px}
    .admin-menu-grupo>summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:10px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280}
    .admin-menu-grupo>summary::-webkit-details-marker{display:none}
    .admin-menu-grupo>summary::after{content:"▾";font-size:12px;transition:transform .2s}
    .admin-menu-grupo:not([open])>summary::after{transform:rotate(-90deg)}
    .admin-menu-grupo>summary:hover{background:rgba(0,0,0,.04)}
    .admin-menu-grupo>summary:focus-visible{outline:2px solid #fbb928;outline-offset:2px}
    @media(max-width:1024px){.admin-sidebar nav{display:flex;gap:6px;overflow-x:auto}.admin-menu-grupo{margin-top:0;flex:0 0 auto}.admin-menu-grupo>summary{white-space:nowrap}}
</style>

<aside class="admin-sidebar" aria-label="Menu administrativo">
    <div class="admin-sidebar-title">Painel</div>
    <nav>
        <?php foreach ($adminMenuGrupos as $grupo): ?>
            <?php
            $grupoAtivo = false;
            foreach ($grupo['itens'] as $it) {
                if (in_array($currentAdminPage, $it['pages'], true) || in_array((string)$currentAdminSlug, $it['pages'], true)) { $grupoAtivo = true; }
            }
            ?>
            <?php if ($grupo['titulo'] !== ''): ?><details class="admin-menu-grupo"<?php echo $grupoAtivo ? ' open' : ''; ?>><summary><?php echo admin_menu_esc($grupo['titulo']); ?></summary><?php endif; ?>
            <ul class="admin-menu">
                <?php foreach ($grupo['itens'] as $item): ?>
                    <?php $active = in_array($currentAdminPage, $item['pages'], true) || in_array((string)$currentAdminSlug, $item['pages'], true); ?>
                    <li>
                        <a class="admin-menu-link<?php echo $active ? ' active' : ''; ?>" href="<?php echo admin_menu_esc($item['href']); ?>">
                            <i data-lucide="<?php echo admin_menu_esc($item['icon']); ?>" aria-hidden="true"></i>
                            <span><?php echo admin_menu_esc($item['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($grupo['titulo'] !== ''): ?></details><?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
