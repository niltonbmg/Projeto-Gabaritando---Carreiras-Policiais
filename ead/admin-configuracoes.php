<?php
// admin-configuracoes.php
// Configurações gerais da plataforma: capas do dashboard para PC e mobile.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;

if (!function_exists('admin_config_bootstrap')) {
    function admin_config_bootstrap(PDO $pdo): void
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

if (!function_exists('admin_config_get')) {
    function admin_config_get(PDO $pdo, string $chave, string $default = ''): string
    {
        $stmt = $pdo->prepare('SELECT valor FROM admin_configuracoes WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $chave]);
        $valor = $stmt->fetchColumn();
        return $valor !== false && $valor !== null ? (string)$valor : $default;
    }
}

if (!function_exists('admin_config_set')) {
    function admin_config_set(PDO $pdo, string $chave, string $valor): void
    {
        $stmt = $pdo->prepare("INSERT INTO admin_configuracoes (chave, valor) VALUES (:chave, :valor)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()");
        $stmt->execute([':chave' => $chave, ':valor' => $valor]);
    }
}

if (!function_exists('admin_config_upload_image')) {
    function admin_config_upload_image(string $field, string $oldPath = ''): string
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $oldPath;
        }

        if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Não foi possível enviar a imagem. Tente novamente.');
        }

        $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
        $originalName = (string)($_FILES[$field]['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Envie a capa em JPG, PNG, WEBP ou GIF.');
        }

        if (!@getimagesize($tmp)) {
            throw new RuntimeException('O arquivo enviado não parece ser uma imagem válida.');
        }

        $uploadDir = __DIR__ . '/uploads/configuracoes';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Não foi possível criar a pasta uploads/configuracoes.');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $destino = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new RuntimeException('Não foi possível salvar a imagem enviada.');
        }

        return 'uploads/configuracoes/' . $filename;
    }
}

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
    admin_config_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro ao carregar configurações: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bannerPcAtual = admin_config_get($pdo, 'dashboard_banner_pc', 'img/banner.png');
        $bannerMobileAtual = admin_config_get($pdo, 'dashboard_banner_mobile', 'img/banner-mobile.png');

        $bannerPc = admin_config_upload_image('dashboard_banner_pc', $bannerPcAtual);
        $bannerMobile = admin_config_upload_image('dashboard_banner_mobile', $bannerMobileAtual);

        admin_config_set($pdo, 'dashboard_banner_pc', $bannerPc);
        admin_config_set($pdo, 'dashboard_banner_mobile', $bannerMobile);

        admin_cursos_redirect('admin-configuracoes.php?sucesso=1');
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if ((string)($_GET['sucesso'] ?? '') === '1') {
    $sucesso = 'Configurações atualizadas com sucesso.';
}

$config = [
    'dashboard_banner_pc' => 'img/banner.png',
    'dashboard_banner_mobile' => 'img/banner-mobile.png',
];

if ($pdo instanceof PDO) {
    try {
        $config['dashboard_banner_pc'] = admin_config_get($pdo, 'dashboard_banner_pc', 'img/banner.png');
        $config['dashboard_banner_mobile'] = admin_config_get($pdo, 'dashboard_banner_mobile', 'img/banner-mobile.png');
    } catch (Throwable $e) {
        $erro = $erro !== '' ? $erro : 'Erro ao buscar configurações salvas.';
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .config-preview-grid{display:grid;grid-template-columns:1fr 320px;gap:18px;margin-top:10px}
        .config-preview{border:1px solid #e4e7ec;border-radius:12px;overflow:hidden;background:#f8fafc}
        .config-preview img{width:100%;height:230px;object-fit:cover;display:block;background:#f8fafc}
        .config-preview.mobile img{height:230px}
        .config-preview__label{display:flex;align-items:center;gap:8px;padding:11px 13px;background:#fff;color:#344054;font-weight:700;font-size:.9rem;border-top:1px solid #e4e7ec}
        .config-preview__label svg{width:17px;height:17px;color:#d89d00;stroke-width:2.5}
        @media(max-width:900px){.config-preview-grid{grid-template-columns:1fr}.config-preview.mobile{max-width:360px}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header" aria-label="Cabeçalho de configurações">
                <div class="admin-page-heading">
                    <h1>Configurações</h1>
                    <p>Configure as imagens principais da plataforma. A capa do dashboard tem versão para computador e versão para celular.</p>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div>
            <?php endif; ?>
            <?php if ($sucesso !== ''): ?>
                <div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div>
            <?php endif; ?>

            <section class="admin-panel" aria-label="Capas do dashboard">
                <div class="admin-panel-title">
                    <div>
                        <h2>Capas do dashboard</h2>
                        <small>Essas imagens aparecem na tela inicial depois do login.</small>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                    <div class="admin-form-field">
                        <label>Capa do dashboard - PC</label>
                        <input type="file" name="dashboard_banner_pc" accept="image/*">
                        <span class="admin-form-hint">Recomendado: imagem horizontal grande, exemplo 1920x700.</span>
                    </div>
                    <div class="admin-form-field">
                        <label>Capa do dashboard - Mobile</label>
                        <input type="file" name="dashboard_banner_mobile" accept="image/*">
                        <span class="admin-form-hint">Recomendado: imagem vertical ou quadrada, exemplo 900x900.</span>
                    </div>

                    <div class="admin-form-field full">
                        <label>Pré-visualização atual</label>
                        <div class="config-preview-grid">
                            <div class="config-preview">
                                <img src="<?php echo admin_esc($config['dashboard_banner_pc']); ?>" alt="Capa dashboard PC">
                                <div class="config-preview__label"><i data-lucide="monitor" aria-hidden="true"></i>Dashboard PC</div>
                            </div>
                            <div class="config-preview mobile">
                                <img src="<?php echo admin_esc($config['dashboard_banner_mobile']); ?>" alt="Capa dashboard mobile">
                                <div class="config-preview__label"><i data-lucide="smartphone" aria-hidden="true"></i>Dashboard Mobile</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-form-actions">
                        <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar configurações</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <?php admin_cursos_scripts(); ?>
</body>
</html>
