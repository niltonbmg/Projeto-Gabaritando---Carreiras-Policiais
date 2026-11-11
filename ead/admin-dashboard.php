<?php
// admin-dashboard.php
// Tela principal do painel administrativo.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();


if (!function_exists('admin_esc')) {
    function admin_esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}

$adminAtual = auth_admin_user();
$adminLogin = (string)($adminAtual['nome'] ?? $adminAtual['login'] ?? 'Admin');

if (!function_exists('admin_dash_db')) {
    function admin_dash_db(): ?PDO
    {
        $conexao = __DIR__ . '/api/conexao.php';
        if (is_file($conexao)) {
            require_once $conexao;
        }
        if (function_exists('db')) {
            try {
                $pdo = db();
                return $pdo instanceof PDO ? $pdo : null;
            } catch (Throwable $e) {
                error_log('[Admin Dashboard] Falha ao conectar: ' . $e->getMessage());
            }
        }
        return null;
    }
}

if (!function_exists('admin_dash_table_count')) {
    function admin_dash_table_count(?PDO $pdo, string $table, string $where = '', array $params = []): int
    {
        if (!$pdo instanceof PDO) {
            return 0;
        }
        try {
            $safeTable = str_replace('`', '``', $table);
            $sql = "SELECT COUNT(*) FROM `{$safeTable}`" . ($where !== '' ? ' WHERE ' . $where : '');
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

$pdoDashboard = admin_dash_db();
$totalUsuarios = admin_dash_table_count($pdoDashboard, 'usuarios');
$totalUsuariosAtivos = admin_dash_table_count($pdoDashboard, 'usuarios', "status = 'ativo'");
$totalUsuariosBloqueados = admin_dash_table_count($pdoDashboard, 'usuarios', "status = 'bloqueado'");
$totalAdmins = admin_dash_table_count($pdoDashboard, 'usuarios', "tipo = 'admin'");
$totalMensagens = admin_dash_table_count($pdoDashboard, 'forum_duvidas_topicos') + admin_dash_table_count($pdoDashboard, 'aulas_forum_topicos');
$totalProdutos = admin_dash_table_count($pdoDashboard, 'admin_cursos');
$totalVendas = admin_dash_table_count($pdoDashboard, 'usuario_cursos');
$totalReceita = 'R$ 0,00';
$totalPedidosPendentes = 0;

$topbarInclude = __DIR__ . '/includes/admin-topbar.php';
if (!is_file($topbarInclude)) {
    $topbarInclude = __DIR__ . '/admin-topbar.php';
}

$menuBarInclude = __DIR__ . '/includes/menu-bar.php';
if (!is_file($menuBarInclude)) {
    $menuBarInclude = __DIR__ . '/menu-bar.php';
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
    <title>Dashboard Admin | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
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
            --admin-dash-sombra:0 10px 28px rgba(15,23,42,.08);
            --admin-dash-sombra-suave:0 4px 16px rgba(15,23,42,.06);
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
            background:var(--admin-dash-fundo);
            color:var(--admin-dash-texto);
        }

        button,
        input,
        select{
            font-family:inherit;
        }

        a{
            color:inherit;
        }

        .admin-dashboard-shell{
            width:100%;
            min-height:calc(100vh - 78px);
            display:flex;
            align-items:stretch;
            background:var(--admin-dash-fundo);
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

        .admin-dashboard-main{
            width:100%;
            min-width:0;
            padding:34px 38px 48px;
        }

        .admin-dashboard-header{
            width:100%;
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:22px;
            margin-bottom:28px;
        }

        .admin-dashboard-heading h1{
            color:#111827;
            font-size:1.72rem;
            font-weight:700;
            line-height:1.15;
            margin-bottom:7px;
        }

        .admin-dashboard-heading p{
            color:var(--admin-dash-muted);
            font-size:.98rem;
            font-weight:500;
        }

        .admin-dashboard-filtros{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:12px;
            flex-wrap:wrap;
        }

        .admin-select-wrap{
            position:relative;
            min-width:190px;
        }

        .admin-select-wrap select{
            width:100%;
            height:42px;
            border:1px solid #d0d5dd;
            border-radius:7px;
            background:#ffffff;
            color:#344054;
            font-size:.94rem;
            font-weight:500;
            outline:none;
            appearance:none;
            padding:0 42px 0 14px;
            box-shadow:0 1px 2px rgba(16,24,40,.04);
        }

        .admin-select-wrap svg{
            position:absolute;
            top:50%;
            right:14px;
            width:18px;
            height:18px;
            transform:translateY(-50%);
            color:#98a2b3;
            pointer-events:none;
            stroke-width:2.7;
        }

        .admin-resumo-grid{
            display:grid;
            grid-template-columns:repeat(3, minmax(0, 1fr));
            gap:18px;
            margin-bottom:20px;
        }

        .admin-card-numero{
            min-height:148px;
            background:var(--admin-dash-card);
            border:1px solid rgba(228,231,236,.88);
            border-radius:10px;
            box-shadow:var(--admin-dash-sombra-suave);
            padding:24px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            text-decoration:none;
        }

        .admin-card-link{
            transition:transform .18s ease, box-shadow .18s ease;
        }

        .admin-card-link:hover{
            transform:translateY(-2px);
            box-shadow:var(--admin-dash-sombra);
        }

        .admin-card-topo{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
        }

        .admin-card-icone{
            width:43px;
            height:43px;
            border-radius:12px;
            background:#fff7db;
            display:flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
        }

        .admin-card-icone svg{
            width:22px;
            height:22px;
            color:var(--admin-dash-amarelo-escuro);
            stroke-width:2.5;
        }

        .admin-card-label{
            color:#667085;
            font-size:.93rem;
            font-weight:700;
            line-height:1.3;
            text-align:right;
        }

        .admin-card-numero strong{
            color:#111827;
            font-size:2.08rem;
            font-weight:700;
            line-height:1;
            display:block;
            margin-top:20px;
        }

        .admin-card-numero span{
            color:#667085;
            font-size:.9rem;
            font-weight:500;
            display:block;
            margin-top:8px;
        }

        .admin-conteudo-grid{
            width:100%;
            display:grid;
            grid-template-columns:minmax(0, 1.35fr) minmax(320px, .65fr);
            gap:18px;
        }

        .admin-painel-card{
            background:#ffffff;
            border:1px solid rgba(228,231,236,.9);
            border-radius:10px;
            box-shadow:var(--admin-dash-sombra);
            padding:24px;
        }

        .admin-painel-titulo{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            margin-bottom:22px;
        }

        .admin-painel-titulo h2{
            color:#111827;
            font-size:1.08rem;
            font-weight:700;
        }

        .admin-painel-titulo small{
            color:var(--admin-dash-muted);
            font-size:.86rem;
            font-weight:700;
        }

        .admin-grafico-placeholder{
            width:100%;
            height:240px;
            border-radius:10px;
            background:linear-gradient(180deg, #ffffff 0%, #fbfbfc 100%);
            border:1px solid #eef0f3;
            position:relative;
            overflow:hidden;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .admin-grafico-linha{
            position:absolute;
            left:34px;
            right:34px;
            height:1px;
            background:#edf0f4;
        }

        .admin-grafico-linha:nth-child(1){ top:52px; }
        .admin-grafico-linha:nth-child(2){ top:118px; }
        .admin-grafico-linha:nth-child(3){ top:184px; }

        .admin-grafico-base{
            position:absolute;
            left:48px;
            right:48px;
            bottom:52px;
            height:4px;
            border-radius:999px;
            background:linear-gradient(90deg, var(--admin-dash-amarelo) 0%, #7c3aed 74%, transparent 74%);
        }

        .admin-grafico-placeholder p{
            color:#98a2b3;
            font-size:.94rem;
            font-weight:700;
            margin-top:96px;
        }

        .admin-lista-rapida{
            display:grid;
            gap:12px;
        }

        .admin-lista-item{
            min-height:64px;
            border:1px solid #eef0f3;
            border-radius:10px;
            background:#ffffff;
            padding:13px 14px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
        }

        .admin-lista-item-info{
            display:flex;
            align-items:center;
            gap:12px;
            min-width:0;
        }

        .admin-lista-item-icone{
            width:38px;
            height:38px;
            border-radius:10px;
            background:#f8fafc;
            display:flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
        }

        .admin-lista-item-icone svg{
            width:20px;
            height:20px;
            color:#98a2b3;
            stroke-width:2.4;
        }

        .admin-lista-item h3{
            color:#344054;
            font-size:.94rem;
            font-weight:700;
            margin-bottom:3px;
        }

        .admin-lista-item p{
            color:#98a2b3;
            font-size:.82rem;
            font-weight:500;
        }

        .admin-lista-item strong{
            color:#111827;
            font-size:1rem;
            font-weight:700;
            white-space:nowrap;
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

            .admin-dashboard-main{
                padding:28px 20px 42px;
            }

            .admin-conteudo-grid{
                grid-template-columns:1fr;
            }
        }

        @media(max-width:760px){
            .admin-dashboard-header{
                display:grid;
                gap:18px;
            }

            .admin-dashboard-filtros{
                justify-content:flex-start;
            }

            .admin-select-wrap{
                width:100%;
                min-width:0;
            }

            .admin-resumo-grid{
                grid-template-columns:1fr;
            }
        }

        @media(max-width:520px){
            .admin-dashboard-main{
                padding:22px 12px 34px;
            }

            .admin-dashboard-heading h1{
                font-size:1.45rem;
            }

            .admin-card-numero,
            .admin-painel-card{
                padding:18px;
            }

            .admin-card-numero strong{
                font-size:1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-dashboard-header" aria-label="Cabeçalho do dashboard">
                <div class="admin-dashboard-heading">
                    <h1>Dashboard</h1>
                    <p>Bem-vindo, <?php echo admin_esc($adminLogin); ?>. Acompanhe os principais números do painel.</p>
                </div>

                <div class="admin-dashboard-filtros" aria-label="Filtros do painel">
                    <label class="admin-select-wrap">
                        <select aria-label="Período">
                            <option>Hoje</option>
                            <option>Últimos 7 dias</option>
                            <option>Últimos 30 dias</option>
                            <option>Tempo todo</option>
                        </select>
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
                    </label>

                    <label class="admin-select-wrap">
                        <select aria-label="Filtro de conteúdo">
                            <option>Todos os produtos</option>
                            <option>Produtos ativos</option>
                            <option>Produtos pendentes</option>
                        </select>
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
                    </label>
                </div>
            </section>

            <section class="admin-resumo-grid" aria-label="Números principais">
                <article id="vendas" class="admin-card-numero">
                    <div class="admin-card-topo">
                        <div class="admin-card-icone"><i data-lucide="shopping-bag" aria-hidden="true"></i></div>
                        <div class="admin-card-label">Vendas</div>
                    </div>
                    <div>
                        <strong><?php echo (int)$totalVendas; ?></strong>
                        <span>Total de vendas registradas</span>
                    </div>
                </article>

                <a id="usuarios" class="admin-card-numero admin-card-link" href="admin-usuarios">
                    <div class="admin-card-topo">
                        <div class="admin-card-icone"><i data-lucide="users-round" aria-hidden="true"></i></div>
                        <div class="admin-card-label">Usuários</div>
                    </div>
                    <div>
                        <strong><?php echo (int)$totalUsuarios; ?></strong>
                        <span><?php echo (int)$totalUsuariosAtivos; ?> ativos · <?php echo (int)$totalUsuariosBloqueados; ?> bloqueados · <?php echo (int)$totalAdmins; ?> admins</span>
                    </div>
                </a>

                <a id="mensagens" class="admin-card-numero" href="admin-mensagens.php" style="text-decoration:none;color:inherit" title="Abrir mensagens dos alunos">
                    <div class="admin-card-topo">
                        <div class="admin-card-icone"><i data-lucide="messages-square" aria-hidden="true"></i></div>
                        <div class="admin-card-label">Mensagens</div>
                    </div>
                    <div>
                        <strong><?php echo (int)$totalMensagens; ?></strong>
                        <span>Mensagens recebidas</span>
                    </div>
                </a>
            </section>

            <section class="admin-conteudo-grid" aria-label="Conteúdo do dashboard">
                <article class="admin-painel-card">
                    <div class="admin-painel-titulo">
                        <h2>Resumo das vendas</h2>
                        <small><?php echo admin_esc($totalReceita); ?></small>
                    </div>

                    <div class="admin-grafico-placeholder" aria-label="Gráfico demonstrativo de vendas">
                        <span class="admin-grafico-linha" aria-hidden="true"></span>
                        <span class="admin-grafico-linha" aria-hidden="true"></span>
                        <span class="admin-grafico-linha" aria-hidden="true"></span>
                        <span class="admin-grafico-base" aria-hidden="true"></span>
                        <p>Sem vendas no período</p>
                    </div>
                </article>

                <article class="admin-painel-card">
                    <div class="admin-painel-titulo">
                        <h2>Atalhos rápidos</h2>
                        <small>Hoje</small>
                    </div>

                    <div class="admin-lista-rapida">
                        <div class="admin-lista-item">
                            <div class="admin-lista-item-info">
                                <div class="admin-lista-item-icone"><i data-lucide="package-check" aria-hidden="true"></i></div>
                                <div>
                                    <h3>Produtos</h3>
                                    <p>Total cadastrado</p>
                                </div>
                            </div>
                            <strong><?php echo (int)$totalProdutos; ?></strong>
                        </div>

                        <div class="admin-lista-item">
                            <div class="admin-lista-item-info">
                                <div class="admin-lista-item-icone"><i data-lucide="clock-3" aria-hidden="true"></i></div>
                                <div>
                                    <h3>Pedidos pendentes</h3>
                                    <p>Aguardando ação</p>
                                </div>
                            </div>
                            <strong><?php echo (int)$totalPedidosPendentes; ?></strong>
                        </div>

                        <a class="admin-lista-item" href="admin-mensagens.php" style="text-decoration:none;color:inherit">
                            <div class="admin-lista-item-info">
                                <div class="admin-lista-item-icone"><i data-lucide="mail" aria-hidden="true"></i></div>
                                <div>
                                    <h3>Mensagens</h3>
                                    <p>Novas mensagens</p>
                                </div>
                            </div>
                            <strong><?php echo (int)$totalMensagens; ?></strong>
                        </a>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>
