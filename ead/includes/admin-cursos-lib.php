<?php
// includes/admin-cursos-lib.php
// PHP 8.2 - Funções compartilhadas do painel de cursos.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_file(__DIR__ . '/../api/conexao.php')) {
    require_once __DIR__ . '/../api/conexao.php';
}

if (!function_exists('admin_esc')) {
    function admin_esc(?string $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_cursos_load_env')) {
    function admin_cursos_load_env(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $vars = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key !== '') {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }
}

if (!function_exists('admin_cursos_db')) {
    function admin_cursos_db(): PDO
    {
        if (function_exists('db')) {
            $db = db();
            if ($db instanceof PDO) {
                return $db;
            }
        }

        static $pdoLocal = null;
        if ($pdoLocal instanceof PDO) {
            return $pdoLocal;
        }

        global $pdo, $conn, $mysqli;
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdoLocal = $pdo;
            return $pdoLocal;
        }

        $env = admin_cursos_load_env(__DIR__ . '/../.env');
        $host = $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $port = $env['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $name = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? getenv('DB_DATABASE') ?: getenv('DB_NAME');
        $user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? getenv('DB_USERNAME') ?: getenv('DB_USER');
        $pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? getenv('DB_PASSWORD') ?: getenv('DB_PASS');
        $charset = $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
        $timezone = $env['DB_TIMEZONE'] ?? getenv('DB_TIMEZONE') ?: '-03:00';

        if (!$name || !$user) {
            throw new RuntimeException('Configure o arquivo .env ou api/conexao.php com os dados do banco.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdoLocal = new PDO($dsn, (string)$user, (string)$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdoLocal->exec('SET NAMES ' . $charset);
        $pdoLocal->exec('SET time_zone = ' . $pdoLocal->quote($timezone));

        return $pdoLocal;
    }
}

if (!function_exists('admin_cursos_has_column')) {
    function admin_cursos_has_column(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute([':table_name' => $table, ':column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('admin_cursos_ensure_column')) {
    function admin_cursos_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!admin_cursos_has_column($pdo, $table, $column)) {
            $safeTable = str_replace('`', '', $table);
            $safeColumn = str_replace('`', '', $column);
            $pdo->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        }
    }
}

if (!function_exists('admin_cursos_bootstrap')) {
    function admin_cursos_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cursos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            capa VARCHAR(255) NULL,
            capa_mobile VARCHAR(255) NULL,
            capa_pagina VARCHAR(255) NULL,
            capa_pagina_mobile VARCHAR(255) NULL,
            horas_curso DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            horas_video DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            horas_pdf DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_cursos_status (status),
            INDEX idx_admin_cursos_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_curso_modulos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            capa VARCHAR(255) NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_curso_modulos_curso (curso_id),
            CONSTRAINT fk_admin_curso_modulos_curso
                FOREIGN KEY (curso_id) REFERENCES admin_cursos(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_curso_aulas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NOT NULL,
            modulo_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            video_url VARCHAR(700) NULL,
            pdf_arquivo VARCHAR(255) NULL,
            pdf_nome VARCHAR(255) NULL,
            duracao VARCHAR(40) NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_curso_aulas_curso (curso_id),
            INDEX idx_admin_curso_aulas_modulo (modulo_id),
            CONSTRAINT fk_admin_curso_aulas_curso
                FOREIGN KEY (curso_id) REFERENCES admin_cursos(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_curso_aulas_modulo
                FOREIGN KEY (modulo_id) REFERENCES admin_curso_modulos(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        admin_cursos_ensure_column($pdo, 'admin_cursos', 'capa', 'VARCHAR(255) NULL AFTER descricao');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'capa_mobile', 'VARCHAR(255) NULL AFTER capa');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'capa_pagina', 'VARCHAR(255) NULL AFTER capa_mobile');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'capa_pagina_mobile', 'VARCHAR(255) NULL AFTER capa_pagina');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'horas_curso', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER capa_pagina_mobile');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'horas_video', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER horas_curso');
        admin_cursos_ensure_column($pdo, 'admin_cursos', 'horas_pdf', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER horas_video');
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_materias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_materias_status (status),
            KEY idx_admin_materias_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_materia_topicos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            materia_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_materia_topicos_materia (materia_id),
            KEY idx_admin_materia_topicos_status (status),
            KEY idx_admin_materia_topicos_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        admin_cursos_ensure_column($pdo, 'admin_curso_modulos', 'capa', 'VARCHAR(255) NULL AFTER descricao');
        admin_cursos_ensure_column($pdo, 'admin_curso_modulos', 'materia_id', 'INT UNSIGNED NULL AFTER capa');
        admin_cursos_ensure_column($pdo, 'admin_curso_modulos', 'topico_id', 'INT UNSIGNED NULL AFTER materia_id');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'video_url', 'VARCHAR(700) NULL AFTER descricao');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'materia_id', 'INT UNSIGNED NULL AFTER modulo_id');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'topico_id', 'INT UNSIGNED NULL AFTER materia_id');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'pdf_arquivo', 'VARCHAR(255) NULL AFTER video_url');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'pdf_nome', 'VARCHAR(255) NULL AFTER pdf_arquivo');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'duracao', 'VARCHAR(40) NULL AFTER pdf_nome');
        admin_cursos_ensure_column($pdo, 'admin_curso_aulas', 'pdf_duracao', 'VARCHAR(40) NULL AFTER duracao');

        try { $pdo->exec('ALTER TABLE admin_curso_modulos ADD INDEX idx_admin_modulos_materia_topico (materia_id, topico_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_curso_aulas ADD INDEX idx_admin_aulas_materia_topico (materia_id, topico_id)'); } catch (Throwable $e) { }

        if (admin_cursos_has_column($pdo, 'admin_cursos', 'carga_horaria_total')) {
            $pdo->exec('UPDATE admin_cursos SET horas_curso = carga_horaria_total WHERE horas_curso = 0 AND carga_horaria_total IS NOT NULL');
        }
    }
}

if (!function_exists('admin_cursos_redirect')) {
    function admin_cursos_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('admin_cursos_int')) {
    function admin_cursos_int(mixed $value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('admin_cursos_decimal')) {
    function admin_cursos_decimal(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '0.00';
        }

        $value = str_replace(' ', '', $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        $number = max(0, (float)$value);
        return number_format($number, 2, '.', '');
    }
}

if (!function_exists('admin_cursos_horas')) {
    function admin_cursos_horas(mixed $value): string
    {
        $number = (float)$value;
        if ($number <= 0) {
            return '0h';
        }

        $formatted = number_format($number, 2, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');
        return $formatted . 'h';
    }
}

if (!function_exists('admin_cursos_texto_curto')) {
    function admin_cursos_texto_curto(?string $texto, int $limite = 120): string
    {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return 'Sem descrição cadastrada.';
        }

        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite) . '...';
    }
}

if (!function_exists('admin_cursos_upload_image')) {
    function admin_cursos_upload_image(string $field, string $oldPath = ''): string
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

        $uploadDir = __DIR__ . '/../uploads/cursos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Não foi possível criar a pasta uploads/cursos.');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $destino = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new RuntimeException('Não foi possível salvar a imagem enviada.');
        }

        return 'uploads/cursos/' . $filename;
    }
}

if (!function_exists('admin_cursos_upload_pdf')) {
    function admin_cursos_upload_pdf(string $field, string $oldPath = '', string $oldName = ''): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => $oldPath, 'name' => $oldName];
        }

        if (($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Não foi possível enviar o PDF. Tente novamente.');
        }

        $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
        $originalName = (string)($_FILES[$field]['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new RuntimeException('Envie somente PDF no material do conteúdo.');
        }

        $uploadDir = __DIR__ . '/../uploads/cursos/pdf';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Não foi possível criar a pasta uploads/cursos/pdf.');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.pdf';
        $destino = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new RuntimeException('Não foi possível salvar o PDF enviado.');
        }

        return ['path' => 'uploads/cursos/pdf/' . $filename, 'name' => $originalName];
    }
}

if (!function_exists('admin_cursos_get_topbar')) {
    function admin_cursos_get_topbar(): string
    {
        $topbarInclude = __DIR__ . '/admin-topbar.php';
        if (!is_file($topbarInclude)) {
            $topbarInclude = __DIR__ . '/../admin-topbar.php';
        }
        return $topbarInclude;
    }
}

if (!function_exists('admin_cursos_get_menubar')) {
    function admin_cursos_get_menubar(): string
    {
        $menuBarInclude = __DIR__ . '/menu-bar.php';
        if (!is_file($menuBarInclude)) {
            $menuBarInclude = __DIR__ . '/../menu-bar.php';
        }
        return $menuBarInclude;
    }
}

if (!function_exists('admin_curso_capa_html')) {
    function admin_curso_capa_html(?string $path, string $alt, string $classe = 'admin-cover-img'): string
    {
        $path = trim((string)$path);
        if ($path !== '') {
            return '<img class="' . admin_esc($classe) . '" src="' . admin_esc($path) . '" alt="' . admin_esc($alt) . '">';
        }

        return '<div class="' . admin_esc($classe) . ' admin-cover-placeholder"><i data-lucide="image" aria-hidden="true"></i></div>';
    }
}

if (!function_exists('admin_cursos_assets')) {
    function admin_cursos_assets(): void
    {
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
                --admin-dash-sombra:0 10px 28px rgba(15,23,42,.08);
                --admin-dash-sombra-suave:0 4px 16px rgba(15,23,42,.06);
                --admin-green:#079455;
                --admin-red:#d92d20;
            }
            *{margin:0;padding:0;box-sizing:border-box}
            html,body{width:100%;min-height:100%}
            body{min-height:100vh;font-family:'Ubuntu',Arial,sans-serif;background:var(--admin-dash-fundo);color:var(--admin-dash-texto)}
            a{color:inherit;text-decoration:none}
            button,input,select,textarea{font-family:inherit}
            button{cursor:pointer}
            .admin-dashboard-main{width:100%;min-width:0;padding:34px 38px 48px}
            .admin-page-header{width:100%;display:flex;align-items:flex-start;justify-content:space-between;gap:22px;margin-bottom:22px}
            .admin-page-heading h1{color:#111827;font-size:1.72rem;font-weight:700;line-height:1.15;margin-bottom:7px}
            .admin-page-heading p{color:var(--admin-dash-muted);font-size:.98rem;font-weight:500;max-width:820px;line-height:1.5}
            .admin-page-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap}
            .admin-btn{border:0;min-height:44px;border-radius:10px;padding:0 17px;display:inline-flex;align-items:center;justify-content:center;gap:9px;font-size:.94rem;font-weight:700;transition:transform .2s ease,box-shadow .2s ease,background .2s ease;white-space:nowrap}
            .admin-btn svg{width:19px;height:19px;stroke-width:2.4}
            .admin-btn-primary{background:var(--admin-dash-amarelo);color:#111111;box-shadow:0 8px 18px rgba(253,189,8,.22)}
            .admin-btn-primary:hover{background:#efad00;transform:translateY(-1px)}
            .admin-btn-light{background:#ffffff;color:#344054;border:1px solid var(--admin-dash-borda)}
            .admin-btn-light:hover{background:#f9fafb;transform:translateY(-1px)}
            .admin-btn-danger{background:#fff1f0;color:var(--admin-red);border:1px solid #fecdca}
            .admin-btn-danger:hover{background:#ffe5e2;transform:translateY(-1px)}
            .admin-btn-small{min-height:36px;border-radius:8px;padding:0 12px;font-size:.86rem}
            .admin-alert{width:100%;border-radius:10px;padding:13px 15px;margin-bottom:18px;font-size:.94rem;font-weight:700;display:flex;align-items:center;gap:10px}
            .admin-alert svg{width:19px;height:19px;stroke-width:2.5;flex:0 0 auto}
            .admin-alert-error{background:#fff1f0;border:1px solid #fecdca;color:#b42318}
            .admin-alert-success{background:#ecfdf3;border:1px solid #abefc6;color:#067647}
            .admin-stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-bottom:20px}
            .admin-stat-card{min-height:116px;background:#ffffff;border:1px solid rgba(228,231,236,.9);border-radius:10px;box-shadow:var(--admin-dash-sombra-suave);padding:20px;display:flex;align-items:center;justify-content:space-between;gap:18px}
            .admin-stat-card span{display:block;color:#667085;font-size:.9rem;font-weight:700;margin-bottom:8px}
            .admin-stat-card strong{display:block;color:#111827;font-size:2rem;font-weight:700;line-height:1}
            .admin-stat-icon{width:46px;height:46px;border-radius:13px;background:#fff7db;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
            .admin-stat-icon svg{width:23px;height:23px;color:var(--admin-dash-amarelo-escuro);stroke-width:2.5}
            .admin-panel{background:#ffffff;border:1px solid rgba(228,231,236,.9);border-radius:10px;box-shadow:var(--admin-dash-sombra);padding:22px;min-width:0;margin-top:18px}
            .admin-panel-title{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px}
            .admin-panel-title h2{font-size:1.08rem;color:#111827;font-weight:700}
            .admin-panel-title small{color:#667085;font-size:.84rem;font-weight:700}
            .admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
            .admin-card{background:#ffffff;border:1px solid rgba(228,231,236,.9);border-radius:10px;box-shadow:var(--admin-dash-sombra-suave);overflow:hidden;min-width:0;position:relative}
            .admin-card-cover{position:relative;height:166px;background:#f8fafc;overflow:hidden}
            .admin-cover-img{width:100%;height:100%;object-fit:cover;display:block}
            .admin-cover-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#98a2b3}
            .admin-cover-placeholder svg{width:34px;height:34px;stroke-width:2.2}
            .admin-card-body{padding:18px}
            .admin-card-body h3{font-size:1.04rem;color:#111827;font-weight:700;line-height:1.25;margin-bottom:8px}
            .admin-card-body p{color:#667085;font-size:.9rem;font-weight:500;line-height:1.5;min-height:42px}
            .admin-card-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:15px;color:#667085;font-size:.82rem;font-weight:700}
            .admin-card-meta span{display:inline-flex;align-items:center;gap:6px}.admin-card-meta svg{width:16px;height:16px;stroke-width:2.4;color:#98a2b3}
            .admin-card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:16px;flex-wrap:wrap}
            .admin-badge{display:inline-flex;align-items:center;border-radius:999px;background:#f2f4f7;color:#475467;padding:5px 9px;font-size:.76rem;font-weight:700;line-height:1}.admin-badge.ativo{background:#ecfdf3;color:#067647}.admin-badge.inativo{background:#f2f4f7;color:#667085}
            .admin-actions{position:absolute;top:12px;right:12px;z-index:5}.admin-three-btn{width:38px;height:38px;border:1px solid rgba(255,255,255,.65);border-radius:10px;background:rgba(17,17,17,.72);color:#ffffff;display:flex;align-items:center;justify-content:center}.admin-three-btn svg{width:19px;height:19px;stroke-width:2.6}.admin-dropdown{position:absolute;top:45px;right:0;min-width:190px;background:#ffffff;border:1px solid #e4e7ec;box-shadow:0 16px 32px rgba(15,23,42,.14);border-radius:10px;padding:7px;display:none}.admin-actions.open .admin-dropdown{display:grid;gap:4px}.admin-dropdown a,.admin-dropdown button{border:0;background:transparent;width:100%;min-height:36px;border-radius:8px;display:flex;align-items:center;gap:9px;padding:0 10px;color:#344054;font-size:.9rem;font-weight:700;text-align:left}.admin-dropdown a:hover,.admin-dropdown button:hover{background:#f9fafb}.admin-dropdown svg{width:17px;height:17px;stroke-width:2.4}.admin-dropdown .danger{color:var(--admin-red)}
            .admin-empty{background:#ffffff;border:1px dashed #d0d5dd;border-radius:10px;min-height:230px;display:flex;align-items:center;justify-content:center;text-align:center;padding:28px;color:#667085;box-shadow:var(--admin-dash-sombra-suave);grid-column:1/-1}.admin-empty svg{width:44px;height:44px;color:#d0d5dd;stroke-width:2;margin-bottom:12px}.admin-empty h3{color:#111827;font-size:1.05rem;margin-bottom:7px}.admin-empty p{font-weight:500;line-height:1.5}
            .admin-table-wrap{width:100%;overflow-x:auto;border:1px solid #eef0f3;border-radius:10px;background:#fff}.admin-table{width:100%;border-collapse:collapse;min-width:860px}.admin-table th,.admin-table td{padding:14px 16px;border-bottom:1px solid #eef0f3;text-align:left;vertical-align:middle}.admin-table th{font-size:.8rem;color:#667085;text-transform:uppercase;letter-spacing:.05em;background:#f9fafb;font-weight:700}.admin-table td{font-size:.92rem;color:#344054;font-weight:500}.admin-table tr:last-child td{border-bottom:0}.admin-row-title{display:flex;align-items:center;gap:12px;min-width:0}.admin-row-cover{width:74px;height:48px;border-radius:8px;overflow:hidden;background:#f8fafc;border:1px solid #eef0f3;flex:0 0 auto}.admin-row-cover .admin-cover-placeholder svg{width:22px;height:22px}.admin-row-title strong{display:block;color:#111827;font-size:.95rem;margin-bottom:3px}.admin-row-title span{display:block;color:#667085;font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px}.admin-table-actions{display:flex;align-items:center;gap:8px;justify-content:flex-end;flex-wrap:wrap}.admin-link-inline{color:#111827;font-weight:700;text-decoration:underline;text-decoration-color:#fdbd08;text-underline-offset:3px}
            .admin-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.admin-form-field{display:grid;gap:7px;min-width:0}.admin-form-field.full{grid-column:1/-1}.admin-form-field label{color:#344054;font-size:.88rem;font-weight:700}.admin-form-field input,.admin-form-field textarea,.admin-form-field select{width:100%;border:1px solid #d0d5dd;background:#ffffff;color:#111827;border-radius:9px;min-height:44px;outline:none;padding:0 12px;font-size:.94rem;font-weight:500;box-shadow:0 1px 2px rgba(16,24,40,.04)}.admin-form-field textarea{min-height:118px;resize:vertical;padding-top:12px;line-height:1.5}.admin-form-field input[type="file"]{padding:10px 12px;height:auto;min-height:44px}.admin-form-field input:focus,.admin-form-field textarea:focus,.admin-form-field select:focus{border-color:#fdbd08;box-shadow:0 0 0 3px rgba(253,189,8,.16)}.admin-form-hint{font-size:.78rem;color:#667085;font-weight:500;line-height:1.4}.admin-form-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:8px;flex-wrap:wrap}
            .admin-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;display:flex;align-items:center;justify-content:center;padding:24px;overflow-y:auto}.admin-modal{width:min(760px,100%);background:#ffffff;border-radius:16px;box-shadow:0 24px 72px rgba(15,23,42,.28);overflow:hidden}.admin-modal-header{padding:20px 24px;border-bottom:1px solid #eef0f3;display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.admin-modal-header h2{font-size:1.25rem;color:#111827;margin-bottom:6px}.admin-modal-header p{color:#667085;font-size:.92rem;font-weight:500;line-height:1.5}.admin-modal-close{width:38px;height:38px;border-radius:10px;border:1px solid #e4e7ec;display:flex;align-items:center;justify-content:center;color:#667085;flex:0 0 auto}.admin-modal-close svg{width:20px;height:20px;stroke-width:2.5}.admin-modal-body{padding:24px}
            .admin-breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:18px;color:#667085;font-size:.88rem;font-weight:700}.admin-breadcrumb a{color:#344054}.admin-breadcrumb svg{width:15px;height:15px;stroke-width:2.5;color:#98a2b3}
            @media(max-width:1180px){.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-stats-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
            @media(max-width:760px){.admin-dashboard-main{padding:28px 20px 42px}.admin-page-header{display:grid}.admin-page-actions{justify-content:flex-start}.admin-grid,.admin-stats-grid{grid-template-columns:1fr}.admin-form-grid{grid-template-columns:1fr}.admin-form-field.full{grid-column:auto}.admin-form-actions{grid-column:auto;justify-content:flex-start}.admin-modal-backdrop{align-items:flex-start;padding:12px}.admin-modal-header,.admin-modal-body{padding:18px}.admin-table{min-width:760px}}
            @media(max-width:520px){.admin-dashboard-main{padding:22px 12px 34px}.admin-page-heading h1{font-size:1.45rem}.admin-panel{padding:18px}.admin-stat-card strong{font-size:1.8rem}}
        </style>
        <?php
    }
}

if (!function_exists('admin_cursos_scripts')) {
    function admin_cursos_scripts(): void
    {
        ?>
        <script>
            document.addEventListener('click', function(event) {
                var btn = event.target.closest('[data-admin-actions-btn]');
                document.querySelectorAll('.admin-actions.open').forEach(function(menu) {
                    if (!btn || menu !== btn.closest('.admin-actions')) {
                        menu.classList.remove('open');
                    }
                });
                if (btn) {
                    event.preventDefault();
                    var wrap = btn.closest('.admin-actions');
                    if (wrap) wrap.classList.toggle('open');
                }
            });
            if (window.lucide) window.lucide.createIcons();
        </script>
        <?php
    }
}
