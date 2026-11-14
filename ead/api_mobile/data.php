<?php
declare(strict_types=1);
@ini_set('default_socket_timeout', '30');
@ini_set('max_execution_time', '45');
if (function_exists('set_time_limit')) { @set_time_limit(45); }
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/curso-acesso.php';
if (is_file(__DIR__ . '/../includes/notificacoes.php')) {
    require_once __DIR__ . '/../includes/notificacoes.php';
}

try {
    $requestBody = (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') ? body_json() : [];
    $currentUser = auth_user($pdo);
    $current = normalize_user($pdo, $currentUser);
    $usuarioId = (int)($current['id'] ?? 0);
    $tipo = strtolower(trim((string)($_GET['tipo'] ?? ($requestBody['tipo'] ?? 'dashboard'))));

    $aliases = [
        'questoes' => 'banco_questoes',
        'banco-questoes' => 'banco_questoes',
        'forum' => 'forum_duvidas',
        'forum-duvidas' => 'forum_duvidas',
        'curso_modulo' => 'curso_modulos',
        'modulos' => 'curso_modulos',
        'aulas_modulo' => 'modulo_aulas',
        'aulas' => 'modulo_aulas',
        'aula' => 'aula_detalhe',
        'recompensa' => 'recompensas',
        'material' => 'materiais',
        'materiais_pdf' => 'materiais',
    ];
    $tipo = $aliases[$tipo] ?? $tipo;

    function api_data_public_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (preg_match('~^https?://~i', $path)) return $path;
        if (str_starts_with($path, '//')) return 'https:' . $path;

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') return $path;

        if ($path[0] === '/') {
            return $scheme . '://' . $host . $path;
        }

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/ead/api_mobile/data.php'));
        $siteBase = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
        if ($siteBase === '/' || $siteBase === '.') $siteBase = '';

        // Se a API estiver em /ead/api_mobile, arquivos como uploads/cursos/... ficam em /ead/uploads/cursos/...
        return $scheme . '://' . $host . $siteBase . '/' . ltrim($path, '/');
    }



    function api_data_normalize_asset_path(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') return '';

        if (preg_match('~^https?://~i', $path)) {
            $parts = parse_url($path);
            $path = (string)($parts['path'] ?? '');
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'ead/')) {
            $path = substr($path, 4);
        }
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        return $path;
    }

    function api_data_asset_exists(string $path): bool
    {
        $path = api_data_normalize_asset_path($path);
        if ($path === '' || str_contains($path, '..')) return false;
        if (!preg_match('~^(uploads/|img/)~', $path)) return false;
        $file = dirname(__DIR__) . '/' . $path;
        return is_file($file);
    }

    function api_data_image_proxy_url(?string $path): string
    {
        $path = api_data_normalize_asset_path($path);
        if ($path === '') return '';

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') return $path;

        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/ead/api_mobile/data.php'));
        $apiBase = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($apiBase === '/' || $apiBase === '.') $apiBase = '';

        $version = '';
        $file = dirname(__DIR__) . '/' . $path;
        if (is_file($file)) {
            $version = '&v=' . (string)filemtime($file);
        }

        return $scheme . '://' . $host . $apiBase . '/imagem.php?src=' . rawurlencode($path) . $version;
    }

    function api_data_text(mixed $value): string
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') return '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    function api_data_active_sql(string $alias, string $col = 'status'): string
    {
        $a = $alias !== '' ? '`' . str_replace('`', '``', $alias) . '`.' : '';
        $c = '`' . str_replace('`', '``', $col) . '`';
        return "COALESCE({$a}{$c}, 'ativo') = 'ativo'";
    }

    function api_data_count(PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[API Mobile data.php] count falhou: ' . $e->getMessage());
            return 0;
        }
    }

    function api_data_fetch_all(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    function api_data_has(PDO $pdo, string $table): bool
    {
        return table_exists($pdo, $table);
    }

    function api_data_schema_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
            $stmt->execute([':t' => trim($table, '`'), ':c' => trim($column, '`')]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $ignored) {
            return false;
        }
    }

    function api_data_schema_index_exists(PDO $pdo, string $table, string $index): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i");
            $stmt->execute([':t' => trim($table, '`'), ':i' => trim($index, '`')]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $ignored) {
            return false;
        }
    }

    function api_data_schema_constraint_exists(PDO $pdo, string $constraint): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :c");
            $stmt->execute([':c' => trim($constraint, '`')]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $ignored) {
            return false;
        }
    }

    function api_data_try_exec(PDO $pdo, string $sql): void
    {
        $clean = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);

        // Evita ALTER TABLE repetido em toda chamada da API. Isso diminui travamento/connection reset no Hostinger.
        if (preg_match('/^ALTER TABLE `?([^`\s]+)`? ADD COLUMN `?([^`\s]+)`?/i', $clean, $m)) {
            if (api_data_schema_column_exists($pdo, $m[1], $m[2])) return;
        }
        if (preg_match('/^ALTER TABLE `?([^`\s]+)`? ADD (?:UNIQUE )?(?:KEY|INDEX) `?([^`\s(]+)`?/i', $clean, $m)) {
            if (api_data_schema_index_exists($pdo, $m[1], $m[2])) return;
        }
        if (preg_match('/^ALTER TABLE `?([^`\s]+)`? ADD CONSTRAINT `?([^`\s]+)`?/i', $clean, $m)) {
            if (api_data_schema_constraint_exists($pdo, $m[2])) return;
        }

        try {
            $pdo->exec($sql);
        } catch (Throwable $ignored) {
            // Compatibilidade silenciosa para bancos antigos.
        }
    }

    function api_data_banco_questoes_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/../includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        if (!api_data_has($pdo, 'admin_banco_questoes')) return;
        api_data_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN curso_id INT UNSIGNED DEFAULT NULL AFTER id");
        api_data_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN concurso_passado_id INT UNSIGNED DEFAULT NULL AFTER curso_id");
        api_data_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_curso (curso_id)");
        api_data_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_concurso_passado (concurso_passado_id)");
        api_data_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD CONSTRAINT fk_admin_banco_questoes_curso FOREIGN KEY (curso_id) REFERENCES admin_cursos(id) ON DELETE SET NULL");

        api_data_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_concursos_passados (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(180) NOT NULL,
            banca_id INT UNSIGNED DEFAULT NULL,
            ano SMALLINT UNSIGNED DEFAULT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_concursos_passados_status (status),
            KEY idx_admin_concursos_passados_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        api_data_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_banco_questao_acessos (
            questao_id INT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (questao_id, curso_id),
            KEY idx_admin_bq_acessos_curso (curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        api_data_try_exec($pdo, "INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id)
            SELECT id, curso_id FROM admin_banco_questoes WHERE curso_id IS NOT NULL AND curso_id > 0");
    }


    function api_data_bq_resposta_table(PDO $pdo): void
    {
        if (!api_data_has($pdo, 'banco_questoes_respostas_usuario')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS banco_questoes_respostas_usuario (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                questao_id INT UNSIGNED NOT NULL,
                opcao_id INT UNSIGNED DEFAULT NULL,
                correta TINYINT(1) DEFAULT NULL,
                opcoes_cortadas TEXT NULL,
                visualizou_solucao TINYINT(1) NOT NULL DEFAULT 0,
                respondido_em DATETIME DEFAULT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_bq_usuario_questao (usuario_id, questao_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }
        api_data_try_exec($pdo, "ALTER TABLE banco_questoes_respostas_usuario ADD COLUMN opcoes_cortadas TEXT NULL AFTER correta");
        api_data_try_exec($pdo, "ALTER TABLE banco_questoes_respostas_usuario ADD COLUMN visualizou_solucao TINYINT(1) NOT NULL DEFAULT 0 AFTER opcoes_cortadas");
    }

    function api_data_bq_parse_ids(mixed $value): array
    {
        if (is_array($value)) {
            $ids = array_map('intval', $value);
        } else {
            $text = trim((string)$value);
            if ($text === '') return [];
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $ids = array_map('intval', $decoded);
            } else {
                $ids = array_map('intval', preg_split('/[^0-9]+/', $text) ?: []);
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }

    function api_data_bq_ensure_resposta(PDO $pdo, int $usuarioId, int $questaoId): array
    {
        api_data_bq_resposta_table($pdo);
        $rows = api_data_fetch_all($pdo, 'SELECT * FROM banco_questoes_respostas_usuario WHERE usuario_id = ? AND questao_id = ? LIMIT 1', [$usuarioId, $questaoId]);
        if ($rows) return $rows[0];
        $stmt = $pdo->prepare('INSERT INTO banco_questoes_respostas_usuario (usuario_id, questao_id, visualizou_solucao) VALUES (?, ?, 0)');
        $stmt->execute([$usuarioId, $questaoId]);
        $rows = api_data_fetch_all($pdo, 'SELECT * FROM banco_questoes_respostas_usuario WHERE id = ? LIMIT 1', [(int)$pdo->lastInsertId()]);
        return $rows[0] ?? ['usuario_id' => $usuarioId, 'questao_id' => $questaoId, 'opcoes_cortadas' => '', 'visualizou_solucao' => 0];
    }

    function api_data_bq_acesso_sql(PDO $pdo, int $usuarioId, string $alias = 'q', string $prefixo = 'api_bq_acesso'): array
    {
        if ($usuarioId <= 0) {
            return [' AND 1 = 0', []];
        }

        if (!function_exists('gab_curso_acesso_configurado') || !function_exists('gab_curso_acesso_ids')) {
            return ['', []];
        }

        if (function_exists('gab_curso_acesso_usuario_admin') && gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
            return ['', []];
        }

        if (!gab_curso_acesso_configurado($pdo, $usuarioId)) {
            return ['', []];
        }

        $ids = gab_curso_acesso_ids($pdo, $usuarioId);
        if (!$ids) {
            return [' AND 1 = 0', []];
        }

        $params = [];
        $legacy = [];
        $pivot = [];
        foreach (array_values($ids) as $idx => $id) {
            $kLegacy = ':' . $prefixo . '_legacy_' . $idx;
            $kPivot = ':' . $prefixo . '_pivot_' . $idx;
            $legacy[] = $kLegacy;
            $pivot[] = $kPivot;
            $params[$kLegacy] = (int)$id;
            $params[$kPivot] = (int)$id;
        }

        $aliasSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'q';
        $sql = " AND ({$aliasSafe}.curso_id IN (" . implode(',', $legacy) . ") OR EXISTS (SELECT 1 FROM admin_banco_questao_acessos qa_access WHERE qa_access.questao_id = {$aliasSafe}.id AND qa_access.curso_id IN (" . implode(',', $pivot) . ")))";
        return [$sql, $params];
    }

    function api_data_route_debug(PDO $pdo, string $tipo, array $items, ?string $table = null): array
    {
        if (!isset($_GET['debug']) || (string)$_GET['debug'] !== '1') {
            return [];
        }
        return [
            'debug' => [
                'tipo' => $tipo,
                'table' => $table,
                'items_count' => count($items),
                'database' => (string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
            ],
        ];
    }

    function api_data_cursos(PDO $pdo, int $usuarioId = 0): array
    {
        if (!api_data_has($pdo, 'admin_cursos')) return [];

        $filtroAcesso = '';
        $paramsAcesso = [];
        if (function_exists('gab_curso_acesso_sql')) {
            [$filtroAcesso, $paramsAcesso] = gab_curso_acesso_sql($pdo, $usuarioId, 'c.id', 'api_mobile_curso');
        }

        $rows = api_data_fetch_all($pdo, "
            SELECT
                c.*,
                (SELECT COUNT(*) FROM admin_curso_modulos m WHERE m.curso_id = c.id) AS total_modulos,
                (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.curso_id = c.id) AS total_aulas
            FROM admin_cursos c
            WHERE " . api_data_active_sql('c') . "
            {$filtroAcesso}
            ORDER BY c.ordem ASC, c.id DESC
            LIMIT 100
        ", $paramsAcesso);

        return array_map(static function(array $row): array {
            $titulo = api_data_text($row['nome'] ?? 'Curso');

            // Mesma imagem que o dashboard do site usa no card do curso: admin_cursos.capa.
            // No Flutter Web, imagem de outro domínio pode falhar sem CORS. Por isso usamos imagem.php como proxy.
            $capaRaw = api_data_normalize_asset_path((string)($row['capa'] ?? ''));
            if ($capaRaw === '') $capaRaw = 'img/banner.png';

            $capaMobileRaw = api_data_normalize_asset_path((string)($row['capa_mobile'] ?? ''));
            if ($capaMobileRaw === '') $capaMobileRaw = $capaRaw;

            $capaPaginaRaw = api_data_normalize_asset_path((string)($row['capa_pagina'] ?? ''));
            $capaPaginaMobileRaw = api_data_normalize_asset_path((string)($row['capa_pagina_mobile'] ?? ''));
            if ($capaPaginaMobileRaw === '') $capaPaginaMobileRaw = $capaPaginaRaw;

            $capaDireta = api_data_public_url($capaRaw);
            $capaMobileDireta = api_data_public_url($capaMobileRaw);
            $capaProxy = api_data_image_proxy_url($capaRaw);
            $capaMobileProxy = api_data_image_proxy_url($capaMobileRaw ?: $capaRaw);
            $capaPagina = api_data_image_proxy_url($capaPaginaRaw);
            $capaPaginaMobile = api_data_image_proxy_url($capaPaginaMobileRaw ?: $capaPaginaRaw);

            $row['titulo'] = $titulo;
            $row['descricao'] = api_data_text($row['descricao'] ?? 'Toque para acessar os módulos e aulas.');

            // Campos usados pelo app.
            $row['imagem_proxy_url'] = $capaProxy;
            $row['imagem_mobile_proxy_url'] = $capaMobileProxy ?: $capaProxy;
            $row['capa_url'] = $capaProxy;
            $row['capa_mobile_url'] = $capaMobileProxy ?: $capaProxy;
            $row['imagem_url'] = $capaProxy;
            $row['imagem_mobile_url'] = $capaMobileProxy ?: $capaProxy;
            $row['imagem'] = $capaProxy;
            $row['imagem_mobile'] = $capaMobileProxy ?: $capaProxy;

            // Campos de diagnóstico: mostram o caminho igual ao dashboard do site.
            $row['capa_original'] = $capaRaw;
            $row['capa_mobile_original'] = $capaMobileRaw;
            $row['imagem_direta_url'] = $capaDireta;
            $row['imagem_mobile_direta_url'] = $capaMobileDireta ?: $capaDireta;
            $row['imagem_existe_no_servidor'] = api_data_asset_exists($capaRaw);
            $row['capa_pagina_url'] = $capaPagina;
            $row['capa_pagina_mobile_url'] = $capaPaginaMobile ?: $capaPagina;
            $row['arquivo_url'] = api_data_public_url((string)($row['arquivo'] ?? ''));
            $row['total_modulos'] = (int)($row['total_modulos'] ?? 0);
            $row['total_aulas'] = (int)($row['total_aulas'] ?? 0);

            $infos = [];
            $horas = (float)($row['horas_curso'] ?? 0);
            if ($horas > 0) {
                $horasTexto = rtrim(rtrim(number_format($horas, 2, ',', '.'), '0'), ',');
                $infos[] = $horasTexto . 'h de curso';
            }
            if ($row['total_modulos'] > 0) {
                $infos[] = $row['total_modulos'] . ((int)$row['total_modulos'] === 1 ? ' módulo' : ' módulos');
            }
            if ($row['total_aulas'] > 0) {
                $infos[] = $row['total_aulas'] . ((int)$row['total_aulas'] === 1 ? ' aula' : ' aulas');
            }
            $row['infos'] = implode(' • ', $infos);

            return $row;
        }, $rows);
    }

    function api_data_parse_duracao(?string $valor): float
    {
        $texto = trim(mb_strtolower((string)$valor, 'UTF-8'));
        if ($texto === '') return 0.0;
        $texto = str_replace(',', '.', $texto);
        $horas = 0.0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*h/', $texto, $match)) {
            $horas += (float)$match[1];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(m|min|minuto|minutos)/', $texto, $match)) {
            $horas += ((float)$match[1]) / 60;
        }
        if ($horas <= 0 && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $texto, $match)) {
            $horas += (float)$match[1];
            $horas += ((float)$match[2]) / 60;
        }
        if ($horas <= 0 && is_numeric($texto)) {
            $horas = (float)$texto;
        }
        return $horas;
    }

    function api_data_format_horas(float $horas): string
    {
        if ($horas <= 0) return '';
        $texto = number_format($horas, 2, ',', '.');
        $texto = rtrim(rtrim($texto, '0'), ',');
        return $texto . 'h';
    }

    function api_data_format_minutos(int $minutos): string
    {
        if ($minutos <= 0) return '0min';
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;
        if ($horas > 0 && $resto > 0) return $horas . 'h ' . $resto . 'min';
        if ($horas > 0) return $horas . 'h';
        return $minutos . 'min';
    }

    function api_data_curso_modulos(PDO $pdo, int $cursoId): array
    {
        if ($cursoId <= 0 || !api_data_has($pdo, 'admin_curso_modulos')) return [];
        $rows = api_data_fetch_all($pdo, "
            SELECT
                m.*,
                c.nome AS curso_nome,
                c.capa AS curso_capa,
                c.capa_pagina AS curso_capa_pagina,
                mat.nome AS materia_nome,
                top.titulo AS topico_titulo,
                COUNT(a.id) AS total_aulas,
                GROUP_CONCAT(COALESCE(a.duracao, '') SEPARATOR '||') AS duracoes
            FROM admin_curso_modulos m
            LEFT JOIN admin_cursos c ON c.id = m.curso_id
            LEFT JOIN admin_curso_aulas a ON a.modulo_id = m.id
            LEFT JOIN admin_materias mat ON mat.id = m.materia_id
            LEFT JOIN admin_materia_topicos top ON top.id = m.topico_id
            WHERE m.curso_id = :curso_id
            GROUP BY m.id, m.curso_id, m.titulo, m.descricao, m.capa, m.materia_id, m.topico_id, m.video_url, m.video_arquivo, m.material_arquivo, m.material_nome, m.ordem, m.criado_em, m.atualizado_em, c.nome, c.capa, c.capa_pagina, mat.nome, top.titulo
            ORDER BY m.ordem ASC, m.id ASC
            LIMIT 200
        ", [':curso_id' => $cursoId]);

        return array_map(static function(array $row): array {
            $row['titulo'] = api_data_text($row['titulo'] ?? 'Módulo');
            $row['descricao'] = api_data_text($row['descricao'] ?? 'Toque para acessar as aulas.');

            $capaRaw = api_data_normalize_asset_path((string)($row['capa'] ?? ''));
            if ($capaRaw === '') $capaRaw = api_data_normalize_asset_path((string)($row['curso_capa'] ?? ''));
            if ($capaRaw === '') $capaRaw = api_data_normalize_asset_path((string)($row['curso_capa_pagina'] ?? ''));
            if ($capaRaw === '') $capaRaw = 'img/banner.png';

            $duracaoTotal = 0.0;
            foreach (explode('||', (string)($row['duracoes'] ?? '')) as $duracao) {
                $duracaoTotal += api_data_parse_duracao($duracao);
            }

            $row['capa_url'] = api_data_image_proxy_url($capaRaw);
            $row['imagem_url'] = $row['capa_url'];
            $row['imagem'] = $row['capa_url'];
            $row['capa_original'] = $capaRaw;
            $row['video_arquivo_url'] = api_data_public_url((string)($row['video_arquivo'] ?? ''));
            $row['material_arquivo_url'] = api_data_public_url((string)($row['material_arquivo'] ?? ''));
            $row['total_aulas'] = (int)($row['total_aulas'] ?? 0);
            $row['tempo'] = $duracaoTotal > 0 ? api_data_format_horas($duracaoTotal) : ($row['total_aulas'] . ((int)$row['total_aulas'] === 1 ? ' aula' : ' aulas'));
            return $row;
        }, $rows);
    }

    function api_data_curso_detalhe(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        if ($cursoId <= 0) {
            return [
                'curso' => null,
                'modulos' => [],
                'metas' => [],
                'conteudos' => [],
                'message' => 'Curso não informado.',
            ];
        }

        $curso = null;
        foreach (api_data_cursos($pdo, $usuarioId) as $item) {
            if ((int)($item['id'] ?? 0) === $cursoId) {
                $curso = $item;
                break;
            }
        }

        if (!$curso) {
            return [
                'curso' => null,
                'modulos' => [],
                'metas' => [],
                'conteudos' => [],
                'message' => 'Curso não encontrado ou inativo.',
            ];
        }

        $modulos = api_data_curso_modulos($pdo, $cursoId);
        $metas = array_values(array_filter(api_data_cronograma($pdo, $usuarioId), static function(array $item) use ($cursoId): bool {
            return (int)($item['curso_id'] ?? 0) === $cursoId;
        }));

        $conteudos = [
            [
                'titulo' => 'Cronograma',
                'descricao' => 'Veja o cronograma de estudos deste curso.',
                'apiType' => 'cronograma',
                'icone' => 'calendar_month',
            ],
            [
                'titulo' => 'Banco de Questões',
                'descricao' => 'Resolva as questões publicadas.',
                'apiType' => 'banco_questoes',
                'icone' => 'quiz',
            ],
            [
                'titulo' => 'Simulados',
                'descricao' => 'Acesse os simulados disponíveis para treino.',
                'apiType' => 'simulados',
                'icone' => 'assignment',
            ],
            [
                'titulo' => 'Fórum e Dúvidas',
                'descricao' => 'Tire dúvidas e acompanhe respostas.',
                'apiType' => 'forum_duvidas',
                'icone' => 'forum',
            ],
            [
                'titulo' => 'Recompensas',
                'descricao' => 'Acompanhe XP, metas e conquistas.',
                'apiType' => 'recompensas',
                'icone' => 'emoji_events',
            ],
        ];

        return [
            'curso' => $curso,
            'modulos' => $modulos,
            'metas' => array_slice($metas, 0, 20),
            'conteudos' => $conteudos,
        ];
    }

    function api_data_modulo_aulas(PDO $pdo, int $moduloId): array
    {
        if ($moduloId <= 0 || !api_data_has($pdo, 'admin_curso_aulas')) return [];
        $rows = api_data_fetch_all($pdo, "
            SELECT
                a.*,
                c.nome AS curso_nome,
                m.titulo AS modulo_titulo,
                mat.nome AS materia_nome,
                top.titulo AS topico_titulo
            FROM admin_curso_aulas a
            LEFT JOIN admin_cursos c ON c.id = a.curso_id
            LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
            LEFT JOIN admin_materias mat ON mat.id = a.materia_id
            LEFT JOIN admin_materia_topicos top ON top.id = a.topico_id
            WHERE a.modulo_id = :modulo_id
            ORDER BY a.ordem ASC, a.id ASC
            LIMIT 300
        ", [':modulo_id' => $moduloId]);

        return array_map(static function(array $row): array {
            $row['titulo'] = api_data_text($row['titulo'] ?? 'Aula');
            $row['descricao'] = api_data_text($row['descricao'] ?? 'Conteúdo da aula.');
            $row['pdf_arquivo_url'] = api_data_public_url((string)($row['pdf_arquivo'] ?? ''));
            $row['video_arquivo_url'] = api_data_public_url((string)($row['video_arquivo'] ?? ''));
            $row['material_arquivo_url'] = api_data_public_url((string)($row['material_arquivo'] ?? ''));
            return $row;
        }, $rows);
    }

    function api_data_cronograma_destino(PDO $pdo, array $row): array
    {
        $cursoId = (int)($row['curso_id'] ?? 0);
        $moduloId = (int)($row['modulo_id'] ?? 0);
        $aulaId = (int)($row['aula_id'] ?? 0);

        if ($cursoId > 0 && $moduloId > 0 && $aulaId > 0) {
            return ['curso_id' => $cursoId, 'modulo_id' => $moduloId, 'aula_id' => $aulaId];
        }

        if ($cursoId <= 0 || !api_data_has($pdo, 'admin_curso_aulas')) {
            return ['curso_id' => $cursoId, 'modulo_id' => $moduloId, 'aula_id' => $aulaId];
        }

        $termos = [];
        foreach (['titulo', 'aula_titulo', 'modulo_titulo', 'topico_titulo', 'materia', 'materia_nome'] as $key) {
            $valor = api_data_text($row[$key] ?? '');
            if ($valor !== '') $termos[] = $valor;
        }
        $termos = array_values(array_unique($termos));

        try {
            foreach ($termos as $termo) {
                if (mb_strlen($termo, 'UTF-8') < 3) continue;
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termo) . '%';
                $stmt = $pdo->prepare("\n                    SELECT a.id AS aula_id, a.modulo_id\n                    FROM admin_curso_aulas a\n                    LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                    WHERE a.curso_id = :curso_id\n                      AND (a.titulo LIKE :like1 OR a.descricao LIKE :like2 OR m.titulo LIKE :like3 OR m.descricao LIKE :like4)\n                    ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                    LIMIT 1\n                ");
                $stmt->execute([
                    ':curso_id' => $cursoId,
                    ':like1' => $like,
                    ':like2' => $like,
                    ':like3' => $like,
                    ':like4' => $like,
                ]);
                $aula = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($aula && (int)($aula['aula_id'] ?? 0) > 0) {
                    return [
                        'curso_id' => $cursoId,
                        'modulo_id' => (int)($aula['modulo_id'] ?? 0),
                        'aula_id' => (int)($aula['aula_id'] ?? 0),
                    ];
                }
            }

            $stmt = $pdo->prepare("\n                SELECT a.id AS aula_id, a.modulo_id\n                FROM admin_curso_aulas a\n                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                WHERE a.curso_id = :curso_id\n                ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                LIMIT 1\n            ");
            $stmt->execute([':curso_id' => $cursoId]);
            $aula = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aula && (int)($aula['aula_id'] ?? 0) > 0) {
                return [
                    'curso_id' => $cursoId,
                    'modulo_id' => (int)($aula['modulo_id'] ?? 0),
                    'aula_id' => (int)($aula['aula_id'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('[API Mobile cronograma destino] ' . $e->getMessage());
        }

        return ['curso_id' => $cursoId, 'modulo_id' => $moduloId, 'aula_id' => $aulaId];
    }

    function api_data_cronograma(PDO $pdo, int $usuarioId): array
    {
        if (!api_data_has($pdo, 'admin_cronograma_itens')) return [];

        if (!api_data_has($pdo, 'usuario_cronograma_conclusoes')) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cronograma_conclusoes (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    cronograma_id INT UNSIGNED NOT NULL,
                    usuario_id INT UNSIGNED NOT NULL,
                    concluido TINYINT(1) NOT NULL DEFAULT 0,
                    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_usuario_cronograma (cronograma_id, usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Throwable $e) { }
        }

        $rows = api_data_fetch_all($pdo, "
            SELECT
                i.*,
                c.nome AS curso_nome,
                cm.titulo AS modulo_titulo,
                ca.titulo AS aula_titulo,
                mat.nome AS materia_nome,
                top.titulo AS topico_titulo,
                COALESCE(uc.concluido, 0) AS concluido
            FROM admin_cronograma_itens i
            LEFT JOIN admin_cursos c ON c.id = i.curso_id
            LEFT JOIN admin_curso_modulos cm ON cm.id = i.modulo_id
            LEFT JOIN admin_curso_aulas ca ON ca.id = i.aula_id
            LEFT JOIN admin_materias mat ON mat.id = i.materia_id
            LEFT JOIN admin_materia_topicos top ON top.id = i.topico_id
            LEFT JOIN usuario_cronograma_conclusoes uc ON uc.cronograma_id = i.id AND uc.usuario_id = :usuario_id
            WHERE " . api_data_active_sql('i') . "
            ORDER BY i.data_estudo ASC, i.ordem ASC, i.hora_inicio ASC, i.id ASC
            LIMIT 500
        ", [':usuario_id' => $usuarioId]);

        return array_map(static function(array $row) use ($pdo): array {
            $row['titulo'] = api_data_text($row['titulo'] ?? 'Meta de estudo');
            $row['descricao'] = api_data_text($row['descricao'] ?? 'Atividade do cronograma.');
            $row['materia'] = api_data_text($row['materia_nome'] ?? ($row['materia'] ?? ''));
            $row['topico'] = api_data_text($row['topico_titulo'] ?? '');
            $row['modulo_titulo'] = api_data_text($row['modulo_titulo'] ?? '');
            $row['aula_titulo'] = api_data_text($row['aula_titulo'] ?? '');

            $tempoMinutos = (int)($row['duracao_minutos'] ?? $row['tempo_minutos'] ?? 0);
            $videoMinutos = (int)($row['video_minutos'] ?? 0);
            $pdfMinutos = (int)($row['pdf_minutos'] ?? 0);
            $questoesMinutos = (int)($row['questoes_minutos'] ?? 0);

            $row['tempo_minutos'] = $tempoMinutos;
            $row['tempo'] = api_data_format_minutos($tempoMinutos);
            $row['video_tempo'] = api_data_format_minutos($videoMinutos);
            $row['pdf_tempo'] = api_data_format_minutos($pdfMinutos);
            $row['questoes_tempo'] = api_data_format_minutos($questoesMinutos);
            $row['feito'] = ((int)($row['concluido'] ?? 0) === 1) ? api_data_format_minutos($tempoMinutos) : '0min';
            $row['percentual'] = ((int)($row['concluido'] ?? 0) === 1) ? 100 : 0;
            $row['progresso'] = $row['percentual'];
            $row['status_texto'] = ((int)($row['concluido'] ?? 0) === 1) ? 'Concluído' : 'Pendente';

            $destino = api_data_cronograma_destino($pdo, $row);
            $row['destino_curso_id'] = (int)($destino['curso_id'] ?? 0);
            $row['destino_modulo_id'] = (int)($destino['modulo_id'] ?? 0);
            $row['destino_aula_id'] = (int)($destino['aula_id'] ?? 0);
            $row['abrir_aula'] = $row['destino_curso_id'] > 0 && $row['destino_modulo_id'] > 0 && $row['destino_aula_id'] > 0;
            $row['acao_texto'] = $row['abrir_aula'] ? 'Ir para vídeo' : 'Ver curso';

            if (!empty($row['data_estudo'])) {
                $timestamp = strtotime((string)$row['data_estudo']);
                $row['data_iso'] = date('Y-m-d', $timestamp ?: time());
                $row['data_meta_label'] = $timestamp ? date('d/m/Y', $timestamp) : (string)$row['data_estudo'];
                $row['dia_semana'] = $timestamp ? (int)date('w', $timestamp) : null;
            }

            return $row;
        }, $rows);
    }

    function api_data_banco_questoes(PDO $pdo, int $usuarioId): array
    {
        if (!api_data_has($pdo, 'admin_banco_questoes')) return [];
        api_data_banco_questoes_bootstrap($pdo);

        [$filtroAcesso, $paramsAcesso] = api_data_bq_acesso_sql($pdo, $usuarioId, 'q', 'api_bq_curso');

        $rows = api_data_fetch_all($pdo, "
            SELECT
                q.*,
                c.nome AS curso_nome,
                cp.titulo AS concurso_passado_titulo,
                cp.ano AS concurso_passado_ano,
                m.nome AS materia_nome,
                b.nome AS banca_nome,
                b.sigla AS banca_sigla,
                mt.titulo AS materia_topico_titulo,
                r.opcao_id AS usuario_opcao_id,
                r.correta AS usuario_correta,
                r.visualizou_solucao,
                (SELECT COUNT(*) FROM admin_banco_questao_opcoes o WHERE o.questao_id = q.id) AS total_opcoes
            FROM admin_banco_questoes q
            LEFT JOIN admin_cursos c ON c.id = q.curso_id
            LEFT JOIN admin_concursos_passados cp ON cp.id = q.concurso_passado_id
            LEFT JOIN admin_materias m ON m.id = q.materia_id
            LEFT JOIN admin_bancas b ON b.id = q.banca_id
            LEFT JOIN admin_materia_topicos mt ON mt.id = q.materia_topico_id
            LEFT JOIN banco_questoes_respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = :usuario_id
            WHERE " . api_data_active_sql('q') . " {$filtroAcesso}
            ORDER BY q.ordem ASC, q.id DESC
            LIMIT 300
        ", array_merge([':usuario_id' => $usuarioId], $paramsAcesso));

        return array_map(static function(array $row): array {
            $row['titulo'] = 'Questão #' . (int)($row['id'] ?? 0);
            $row['descricao'] = api_data_text($row['enunciado'] ?? 'Questão do banco.');
            $row['categoria'] = api_data_text($row['materia_nome'] ?? '');
            $row['curso_nome'] = api_data_text($row['curso_nome'] ?? '');
            $row['acesso'] = $row['curso_nome'];
            $row['concurso_passado'] = api_data_text($row['concurso_passado_titulo'] ?? '');
            $row['concurso_passado_ano'] = (int)($row['concurso_passado_ano'] ?? 0);
            $row['cargo'] = $row['concurso_passado'];
            $row['banca'] = api_data_text(($row['banca_sigla'] ?? '') ?: ($row['banca_nome'] ?? ''));
            $row['materia'] = api_data_text($row['materia_nome'] ?? '');
            $row['topico'] = api_data_text($row['materia_topico_titulo'] ?? '');
            $row['respondida'] = !empty($row['usuario_opcao_id']);
            $row['status_texto'] = empty($row['usuario_opcao_id']) ? 'Não respondida' : ((int)($row['usuario_correta'] ?? 0) === 1 ? 'Correta' : 'Errada');
            return $row;
        }, $rows);
    }

    function api_data_simulados(PDO $pdo, int $usuarioId): array
    {
        if (!api_data_has($pdo, 'admin_simulados')) return [];
        $rows = api_data_fetch_all($pdo, "
            SELECT
                s.*,
                c.nome AS curso_nome,
                (SELECT COUNT(*) FROM admin_simulado_questoes q WHERE q.simulado_id = s.id) AS total_questoes,
                (SELECT COUNT(*) FROM admin_simulado_tentativas t WHERE t.simulado_id = s.id AND t.usuario_id = :usuario_count) AS minhas_tentativas,
                (SELECT MAX(t.percentual) FROM admin_simulado_tentativas t WHERE t.simulado_id = s.id AND t.usuario_id = :usuario_best) AS melhor_percentual
            FROM admin_simulados s
            LEFT JOIN admin_cursos c ON c.id = s.curso_id
            WHERE " . api_data_active_sql('s') . "
            ORDER BY s.ordem ASC, s.id DESC
            LIMIT 200
        ", [':usuario_count' => $usuarioId, ':usuario_best' => $usuarioId]);

        return array_map(static function(array $row): array {
            $row['titulo'] = api_data_text($row['titulo'] ?? 'Simulado');
            $row['descricao'] = api_data_text($row['descricao'] ?? ($row['instrucoes'] ?? 'Simulado disponível para treino.'));
            $row['categoria'] = api_data_text($row['curso_nome'] ?? '');
            $row['status_texto'] = ((int)($row['minhas_tentativas'] ?? 0) > 0) ? 'Já iniciado' : 'Não iniciado';
            $row['progresso'] = $row['melhor_percentual'] !== null ? (float)$row['melhor_percentual'] : null;
            return $row;
        }, $rows);
    }

    function api_data_forum_duvidas(PDO $pdo): array
    {
        $items = [];

        if (api_data_has($pdo, 'forum_duvidas_topicos')) {
            $rows = api_data_fetch_all($pdo, "
                SELECT
                    t.*,
                    COALESCE(NULLIF(u.nome, ''), NULLIF(t.autor_nome, ''), 'Aluno') AS autor_nome_final,
                    (SELECT COUNT(*) FROM forum_duvidas_respostas r WHERE r.topico_id = t.id AND r.status = 'ativo') AS respostas_total,
                    (SELECT r.mensagem FROM forum_duvidas_respostas r WHERE r.topico_id = t.id AND r.status = 'ativo' ORDER BY r.criado_em DESC, r.id DESC LIMIT 1) AS ultima_resposta
                FROM forum_duvidas_topicos t
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                WHERE " . api_data_active_sql('t') . "
                ORDER BY t.criado_em DESC, t.id DESC
                LIMIT 100
            ");
            foreach ($rows as $row) {
                $row['origem'] = 'forum';
                $row['titulo'] = api_data_text($row['titulo'] ?? 'Tópico');
                $row['descricao'] = api_data_text($row['mensagem'] ?? 'Dúvida enviada no fórum.');
                $row['autor_nome'] = api_data_text($row['autor_nome_final'] ?? 'Aluno');
                $row['respostas'] = (int)($row['respostas_total'] ?? 0);
                $items[] = $row;
            }
        }

        if (api_data_has($pdo, 'aulas_forum_topicos')) {
            $rows = api_data_fetch_all($pdo, "
                SELECT
                    t.*,
                    COALESCE(NULLIF(u.nome, ''), 'Aluno') AS autor_nome_final,
                    c.nome AS curso_nome,
                    m.titulo AS modulo_titulo,
                    a.titulo AS aula_titulo,
                    (SELECT COUNT(*) FROM aulas_forum_respostas r WHERE r.topico_id = t.id AND r.status = 'ativo') AS respostas_total,
                    (SELECT r.mensagem FROM aulas_forum_respostas r WHERE r.topico_id = t.id AND r.status = 'ativo' ORDER BY r.criado_em DESC, r.id DESC LIMIT 1) AS ultima_resposta
                FROM aulas_forum_topicos t
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                LEFT JOIN admin_cursos c ON c.id = t.curso_id
                LEFT JOIN admin_curso_modulos m ON m.id = t.modulo_id
                LEFT JOIN admin_curso_aulas a ON a.id = t.aula_id
                WHERE " . api_data_active_sql('t') . "
                ORDER BY t.criado_em DESC, t.id DESC
                LIMIT 100
            ");
            foreach ($rows as $row) {
                $row['origem'] = 'aula';
                $row['categoria'] = 'Aula: ' . api_data_text(($row['aula_titulo'] ?? '') ?: ($row['modulo_titulo'] ?? 'Conteúdo'));
                $row['titulo'] = api_data_text($row['titulo'] ?? 'Dúvida da aula');
                $row['descricao'] = api_data_text($row['texto'] ?? 'Dúvida enviada na aula.');
                $row['autor_nome'] = api_data_text($row['autor_nome_final'] ?? 'Aluno');
                $row['respostas'] = (int)($row['respostas_total'] ?? 0);
                $items[] = $row;
            }
        }

        usort($items, static function(array $a, array $b): int {
            return strcmp((string)($b['criado_em'] ?? ''), (string)($a['criado_em'] ?? ''));
        });

        return array_slice($items, 0, 200);
    }

    function api_data_recompensas(PDO $pdo, int $usuarioId): array
    {
        $items = [];

        $xpTotal = api_data_count($pdo, 'SELECT COALESCE(SUM(xp), 0) FROM usuario_xp_eventos WHERE usuario_id = ?', [$usuarioId]);
        $xpHoje = api_data_count($pdo, 'SELECT COALESCE(SUM(xp), 0) FROM usuario_xp_eventos WHERE usuario_id = ? AND DATE(criado_em) = CURDATE()', [$usuarioId]);
        $eventos = api_data_count($pdo, 'SELECT COUNT(*) FROM usuario_xp_eventos WHERE usuario_id = ?', [$usuarioId]);

        $items[] = [
            'id' => 'xp_resumo',
            'titulo' => 'Seu XP no app',
            'descricao' => 'Você tem ' . $xpTotal . ' XP acumulado. Hoje ganhou ' . $xpHoje . ' XP em ' . $eventos . ' evento(s).',
            'categoria' => 'Resumo',
            'xp' => $xpTotal,
            'xp_hoje' => $xpHoje,
            'eventos' => $eventos,
        ];

        if (api_data_has($pdo, 'admin_recompensas_regras')) {
            $regras = api_data_fetch_all($pdo, "
                SELECT *
                FROM admin_recompensas_regras
                WHERE COALESCE(ativo, 1) = 1
                ORDER BY id ASC
                LIMIT 100
            ");
            foreach ($regras as $row) {
                $row['titulo'] = api_data_text($row['titulo'] ?? 'Regra de recompensa');
                $row['descricao'] = api_data_text($row['descricao'] ?? 'Regra ativa de XP.');
                $row['categoria'] = 'Regra ativa';
                $items[] = $row;
            }
        }

        if (api_data_has($pdo, 'usuario_xp_eventos')) {
            $historico = api_data_fetch_all($pdo, "
                SELECT *
                FROM usuario_xp_eventos
                WHERE usuario_id = :usuario_id
                ORDER BY criado_em DESC, id DESC
                LIMIT 50
            ", [':usuario_id' => $usuarioId]);
            foreach ($historico as $row) {
                $row['titulo'] = api_data_text($row['descricao'] ?? 'Evento de XP');
                $row['descricao'] = 'Origem: ' . api_data_text($row['origem_tipo'] ?? '') . ' • Resultado: ' . api_data_text($row['origem_resultado'] ?? '');
                $row['categoria'] = 'Histórico';
                $items[] = $row;
            }
        }

        return $items;
    }


    function api_data_question_detail(PDO $pdo, int $usuarioId, int $questaoId): array
    {
        if ($questaoId <= 0 || !api_data_has($pdo, 'admin_banco_questoes')) {
            json_out(false, ['message' => 'Questão não encontrada.'], 404);
        }
        api_data_banco_questoes_bootstrap($pdo);

        [$filtroAcesso, $paramsAcesso] = api_data_bq_acesso_sql($pdo, $usuarioId, 'q', 'api_bq_det_curso');

        $rows = api_data_fetch_all($pdo, "
            SELECT q.*, c.nome AS curso_nome, cp.titulo AS concurso_passado_titulo, cp.ano AS concurso_passado_ano, m.nome AS materia_nome, b.nome AS banca_nome, b.sigla AS banca_sigla, mt.titulo AS materia_topico_titulo
            FROM admin_banco_questoes q
            LEFT JOIN admin_cursos c ON c.id = q.curso_id
            LEFT JOIN admin_concursos_passados cp ON cp.id = q.concurso_passado_id
            LEFT JOIN admin_materias m ON m.id = q.materia_id
            LEFT JOIN admin_bancas b ON b.id = q.banca_id
            LEFT JOIN admin_materia_topicos mt ON mt.id = q.materia_topico_id
            WHERE q.id = :id {$filtroAcesso}
            LIMIT 1
        ", array_merge([':id' => $questaoId], $paramsAcesso));
        if (!$rows) {
            json_out(false, ['message' => 'Questão não encontrada.'], 404);
        }
        $questao = $rows[0];
        $questao['titulo'] = 'Questão #' . (int)$questao['id'];
        $questao['descricao'] = api_data_text($questao['enunciado'] ?? '');
        $questao['curso_nome'] = api_data_text($questao['curso_nome'] ?? '');
        $questao['acesso'] = $questao['curso_nome'];
        $questao['concurso_passado'] = api_data_text($questao['concurso_passado_titulo'] ?? '');
        $questao['concurso_passado_ano'] = (int)($questao['concurso_passado_ano'] ?? 0);
        $questao['cargo'] = $questao['concurso_passado'];
        $questao['materia'] = api_data_text($questao['materia_nome'] ?? '');
        $questao['banca'] = api_data_text(($questao['banca_sigla'] ?? '') ?: ($questao['banca_nome'] ?? ''));
        $questao['topico'] = api_data_text($questao['materia_topico_titulo'] ?? '');

        $opcoes = [];
        $corretaId = 0;
        if (api_data_has($pdo, 'admin_banco_questao_opcoes')) {
            $opcoes = api_data_fetch_all($pdo, "
                SELECT id, questao_id, letra, texto, correta, ordem
                FROM admin_banco_questao_opcoes
                WHERE questao_id = :id
                ORDER BY ordem ASC, id ASC
            ", [':id' => $questaoId]);
            foreach ($opcoes as &$opcao) {
                $opcao['texto'] = api_data_text($opcao['texto'] ?? '');
                $opcao['correta'] = (int)($opcao['correta'] ?? 0);
                if ($opcao['correta'] === 1) {
                    $corretaId = (int)$opcao['id'];
                }
            }
            unset($opcao);
        }

        $resposta = null;
        if (api_data_has($pdo, 'banco_questoes_respostas_usuario')) {
            api_data_bq_resposta_table($pdo);
            $respostas = api_data_fetch_all($pdo, "
                SELECT *
                FROM banco_questoes_respostas_usuario
                WHERE usuario_id = :usuario_id AND questao_id = :questao_id
                LIMIT 1
            ", [':usuario_id' => $usuarioId, ':questao_id' => $questaoId]);
            $resposta = $respostas[0] ?? null;
            if ($resposta) {
                $resposta['opcoes_cortadas_ids'] = api_data_bq_parse_ids($resposta['opcoes_cortadas'] ?? '');
                $resposta['visualizou_solucao'] = (int)($resposta['visualizou_solucao'] ?? 0);
            }
        }

        return [
            'questao' => $questao,
            'opcoes' => $opcoes,
            'resposta' => $resposta,
            'correta_opcao_id' => $corretaId,
        ];
    }

    function api_data_answer_question(PDO $pdo, int $usuarioId, array $body): array
    {
        if (!api_data_has($pdo, 'admin_banco_questoes') || !api_data_has($pdo, 'admin_banco_questao_opcoes')) {
            json_out(false, ['message' => 'Banco de questões não está configurado.'], 500);
        }
        api_data_banco_questoes_bootstrap($pdo);
        api_data_bq_resposta_table($pdo);

        $questaoId = (int)($body['questao_id'] ?? 0);
        $opcaoId = (int)($body['opcao_id'] ?? 0);
        if ($questaoId <= 0 || $opcaoId <= 0) {
            json_out(false, ['message' => 'Selecione uma alternativa.'], 422);
        }

        [$filtroAcesso, $paramsAcesso] = api_data_bq_acesso_sql($pdo, $usuarioId, 'q', 'api_bq_resp_curso');
        $questoesAcesso = api_data_fetch_all($pdo, "SELECT q.id FROM admin_banco_questoes q WHERE q.id = :questao_id AND " . api_data_active_sql('q') . " {$filtroAcesso} LIMIT 1", array_merge([':questao_id' => $questaoId], $paramsAcesso));
        if (!$questoesAcesso) {
            json_out(false, ['message' => 'Questão não liberada para seu curso.'], 403);
        }

        $stmt = $pdo->prepare('SELECT correta FROM admin_banco_questao_opcoes WHERE id = ? AND questao_id = ? LIMIT 1');
        $stmt->execute([$opcaoId, $questaoId]);
        $correta = $stmt->fetchColumn();
        if ($correta === false) {
            json_out(false, ['message' => 'Alternativa inválida.'], 422);
        }
        $correta = (int)$correta === 1 ? 1 : 0;

        $stmt = $pdo->prepare('SELECT id FROM banco_questoes_respostas_usuario WHERE usuario_id = ? AND questao_id = ? LIMIT 1');
        $stmt->execute([$usuarioId, $questaoId]);
        $existing = (int)$stmt->fetchColumn();
        if ($existing > 0) {
            $stmt = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET opcao_id = ?, correta = ?, respondido_em = NOW(), atualizado_em = NOW() WHERE id = ?');
            $stmt->execute([$opcaoId, $correta, $existing]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO banco_questoes_respostas_usuario (usuario_id, questao_id, opcao_id, correta, visualizou_solucao, respondido_em) VALUES (?, ?, ?, ?, 0, NOW())');
            $stmt->execute([$usuarioId, $questaoId, $opcaoId, $correta]);
        }

        $stmt = $pdo->prepare('SELECT id FROM admin_banco_questao_opcoes WHERE questao_id = ? AND correta = 1 ORDER BY ordem ASC, id ASC LIMIT 1');
        $stmt->execute([$questaoId]);
        $corretaId = (int)$stmt->fetchColumn();

        return [
            'message' => $correta ? 'Resposta correta.' : 'Resposta incorreta.',
            'questao_id' => $questaoId,
            'opcao_id' => $opcaoId,
            'correta' => $correta === 1,
            'correta_opcao_id' => $corretaId,
        ];
    }


    function api_data_cut_question_option(PDO $pdo, int $usuarioId, array $body): array
    {
        if (!api_data_has($pdo, 'admin_banco_questoes') || !api_data_has($pdo, 'admin_banco_questao_opcoes')) {
            json_out(false, ['message' => 'Banco de questões não está configurado.'], 500);
        }
        api_data_banco_questoes_bootstrap($pdo);
        $questaoId = (int)($body['questao_id'] ?? 0);
        $opcaoId = (int)($body['opcao_id'] ?? 0);
        $cortada = (int)($body['cortada'] ?? 1) === 1;
        if ($questaoId <= 0 || $opcaoId <= 0) {
            json_out(false, ['message' => 'Alternativa inválida.'], 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM admin_banco_questao_opcoes WHERE id = ? AND questao_id = ? LIMIT 1');
        $stmt->execute([$opcaoId, $questaoId]);
        if (!$stmt->fetchColumn()) {
            json_out(false, ['message' => 'Alternativa inválida.'], 422);
        }
        $resposta = api_data_bq_ensure_resposta($pdo, $usuarioId, $questaoId);
        $ids = api_data_bq_parse_ids($resposta['opcoes_cortadas'] ?? '');
        if ($cortada && !in_array($opcaoId, $ids, true)) $ids[] = $opcaoId;
        if (!$cortada) $ids = array_values(array_filter($ids, static fn(int $id): bool => $id !== $opcaoId));
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        sort($ids);
        $stmt = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET opcoes_cortadas = ?, atualizado_em = NOW() WHERE usuario_id = ? AND questao_id = ?');
        $stmt->execute([implode(',', $ids), $usuarioId, $questaoId]);
        return ['message' => $cortada ? 'Alternativa cortada.' : 'Alternativa restaurada.', 'opcoes_cortadas_ids' => $ids, 'cortada' => $cortada];
    }

    function api_data_toggle_question_solution(PDO $pdo, int $usuarioId, array $body): array
    {
        if (!api_data_has($pdo, 'admin_banco_questoes')) {
            json_out(false, ['message' => 'Banco de questões não está configurado.'], 500);
        }
        api_data_banco_questoes_bootstrap($pdo);
        $questaoId = (int)($body['questao_id'] ?? 0);
        $visivel = (int)($body['visivel'] ?? 1) === 1 ? 1 : 0;
        if ($questaoId <= 0) {
            json_out(false, ['message' => 'Questão inválida.'], 422);
        }
        $resposta = api_data_bq_ensure_resposta($pdo, $usuarioId, $questaoId);
        if ((int)($resposta['opcao_id'] ?? 0) <= 0) {
            json_out(false, ['message' => 'Escolha uma alternativa antes de ver a solução.'], 422);
        }
        $stmt = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET visualizou_solucao = ?, atualizado_em = NOW() WHERE usuario_id = ? AND questao_id = ?');
        $stmt->execute([$visivel, $usuarioId, $questaoId]);
        return ['message' => $visivel ? 'Solução exibida.' : 'Solução escondida.', 'visivel' => $visivel === 1];
    }

    function api_data_mark_cronograma(PDO $pdo, int $usuarioId, array $body): array
    {
        if (!api_data_has($pdo, 'usuario_cronograma_conclusoes')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cronograma_conclusoes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                cronograma_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                concluido TINYINT(1) NOT NULL DEFAULT 0,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_usuario_cronograma (cronograma_id, usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $cronogramaId = (int)($body['cronograma_id'] ?? 0);
        $concluido = (int)($body['concluido'] ?? 1) === 1 ? 1 : 0;
        if ($cronogramaId <= 0) {
            json_out(false, ['message' => 'Meta do cronograma inválida.'], 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM usuario_cronograma_conclusoes WHERE cronograma_id = ? AND usuario_id = ? LIMIT 1');
        $stmt->execute([$cronogramaId, $usuarioId]);
        $existing = (int)$stmt->fetchColumn();
        if ($existing > 0) {
            $stmt = $pdo->prepare('UPDATE usuario_cronograma_conclusoes SET concluido = ?, atualizado_em = NOW() WHERE id = ?');
            $stmt->execute([$concluido, $existing]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO usuario_cronograma_conclusoes (cronograma_id, usuario_id, concluido) VALUES (?, ?, ?)');
            $stmt->execute([$cronogramaId, $usuarioId, $concluido]);
        }
        return ['message' => $concluido ? 'Meta concluída.' : 'Meta reaberta.', 'concluido' => $concluido === 1];
    }

    function api_data_simulado_detail(PDO $pdo, int $simuladoId): array
    {
        if ($simuladoId <= 0 || !api_data_has($pdo, 'admin_simulados')) {
            json_out(false, ['message' => 'Simulado não encontrado.'], 404);
        }
        $rows = api_data_fetch_all($pdo, "
            SELECT s.*, c.nome AS curso_nome
            FROM admin_simulados s
            LEFT JOIN admin_cursos c ON c.id = s.curso_id
            WHERE s.id = :id AND " . api_data_active_sql('s') . "
            LIMIT 1
        ", [':id' => $simuladoId]);
        if (!$rows) {
            json_out(false, ['message' => 'Simulado não encontrado.'], 404);
        }
        $simulado = $rows[0];
        $simulado['titulo'] = api_data_text($simulado['titulo'] ?? 'Simulado');
        $simulado['descricao'] = api_data_text($simulado['descricao'] ?? ($simulado['instrucoes'] ?? ''));

        $questoes = [];
        if (api_data_has($pdo, 'admin_simulado_questoes')) {
            $questoes = api_data_fetch_all($pdo, "
                SELECT * FROM admin_simulado_questoes
                WHERE simulado_id = :id
                ORDER BY ordem ASC, id ASC
            ", [':id' => $simuladoId]);
            if (api_data_has($pdo, 'admin_simulado_opcoes')) {
                $stmt = $pdo->prepare('SELECT id, questao_id, letra, texto, ordem FROM admin_simulado_opcoes WHERE questao_id = ? ORDER BY ordem ASC, id ASC');
                foreach ($questoes as &$questao) {
                    $questao['enunciado'] = api_data_text($questao['enunciado'] ?? '');
                    $questao['explicacao'] = api_data_text($questao['explicacao'] ?? '');
                    $stmt->execute([(int)$questao['id']]);
                    $opcoes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($opcoes as &$opcao) {
                        $opcao['texto'] = api_data_text($opcao['texto'] ?? '');
                    }
                    unset($opcao);
                    $questao['opcoes'] = $opcoes;
                }
                unset($questao);
            }
        }

        return ['simulado' => $simulado, 'questoes' => $questoes];
    }

    function api_data_finish_simulado(PDO $pdo, int $usuarioId, array $body): array
    {
        $simuladoId = (int)($body['simulado_id'] ?? 0);
        if ($simuladoId <= 0) {
            json_out(false, ['message' => 'Simulado inválido.'], 422);
        }
        $detail = api_data_simulado_detail($pdo, $simuladoId);
        $questoes = $detail['questoes'] ?? [];
        $respostasRaw = $body['respostas'] ?? [];
        if (is_string($respostasRaw)) {
            $decoded = json_decode($respostasRaw, true);
            $respostasRaw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($respostasRaw)) {
            $respostasRaw = [];
        }

        $totalQuestoes = count($questoes);
        $totalAcertos = 0;
        $revisao = [];
        $inicioTs = max(0, (int)($body['inicio_ts'] ?? time()));
        $tempoSegundos = max(0, time() - $inicioTs);

        if (!api_data_has($pdo, 'admin_simulado_tentativas')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_tentativas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                simulado_id INT UNSIGNED NOT NULL,
                usuario_id BIGINT UNSIGNED DEFAULT NULL,
                iniciado_em DATETIME NOT NULL,
                finalizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                tempo_segundos INT UNSIGNED NOT NULL DEFAULT 0,
                total_questoes INT UNSIGNED NOT NULL DEFAULT 0,
                total_acertos INT UNSIGNED NOT NULL DEFAULT 0,
                percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                status ENUM('finalizado','em_revisao') NOT NULL DEFAULT 'finalizado',
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        if (!api_data_has($pdo, 'admin_simulado_respostas')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_respostas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tentativa_id BIGINT UNSIGNED NOT NULL,
                questao_id INT UNSIGNED NOT NULL,
                opcao_id INT UNSIGNED DEFAULT NULL,
                correta TINYINT(1) NOT NULL DEFAULT 0,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $stmtCorreta = $pdo->prepare('SELECT id FROM admin_simulado_opcoes WHERE questao_id = ? AND correta = 1 ORDER BY ordem ASC, id ASC LIMIT 1');
        foreach ($questoes as $questao) {
            $qid = (int)($questao['id'] ?? 0);
            $opcaoId = (int)($respostasRaw[(string)$qid] ?? $respostasRaw[$qid] ?? 0);
            $stmtCorreta->execute([$qid]);
            $corretaId = (int)$stmtCorreta->fetchColumn();
            $acertou = $opcaoId > 0 && $corretaId > 0 && $opcaoId === $corretaId;
            if ($acertou) $totalAcertos++;
            $revisao[] = [
                'questao_id' => $qid,
                'opcao_id' => $opcaoId,
                'correta' => $acertou,
                'correta_opcao_id' => $corretaId,
            ];
        }
        $percentual = $totalQuestoes > 0 ? round(($totalAcertos / $totalQuestoes) * 100, 2) : 0;

        $stmt = $pdo->prepare('INSERT INTO admin_simulado_tentativas (simulado_id, usuario_id, iniciado_em, tempo_segundos, total_questoes, total_acertos, percentual, status) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, ?, ?, \'finalizado\')');
        $stmt->execute([$simuladoId, $usuarioId, $inicioTs ?: time(), $tempoSegundos, $totalQuestoes, $totalAcertos, $percentual]);
        $tentativaId = (int)$pdo->lastInsertId();

        $stmtResp = $pdo->prepare('INSERT INTO admin_simulado_respostas (tentativa_id, questao_id, opcao_id, correta) VALUES (?, ?, ?, ?)');
        foreach ($revisao as $r) {
            $stmtResp->execute([$tentativaId, (int)$r['questao_id'], (int)$r['opcao_id'] ?: null, $r['correta'] ? 1 : 0]);
        }

        return [
            'message' => 'Simulado finalizado.',
            'tentativa_id' => $tentativaId,
            'total_questoes' => $totalQuestoes,
            'total_acertos' => $totalAcertos,
            'percentual' => $percentual,
            'tempo_segundos' => $tempoSegundos,
            'revisao' => $revisao,
        ];
    }

    function api_data_forum_topic(PDO $pdo, int $id, string $origem): array
    {
        $origem = $origem === 'aula' ? 'aula' : 'forum';
        if ($id <= 0) {
            json_out(false, ['message' => 'Tópico inválido.'], 422);
        }

        if ($origem === 'aula') {
            if (!api_data_has($pdo, 'aulas_forum_topicos')) json_out(false, ['message' => 'Tópico não encontrado.'], 404);
            $rows = api_data_fetch_all($pdo, "
                SELECT t.*, COALESCE(NULLIF(u.nome, ''), 'Aluno') AS autor_nome, c.nome AS curso_nome, m.titulo AS modulo_titulo, a.titulo AS aula_titulo
                FROM aulas_forum_topicos t
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                LEFT JOIN admin_cursos c ON c.id = t.curso_id
                LEFT JOIN admin_curso_modulos m ON m.id = t.modulo_id
                LEFT JOIN admin_curso_aulas a ON a.id = t.aula_id
                WHERE t.id = :id AND " . api_data_active_sql('t') . "
                LIMIT 1
            ", [':id' => $id]);
            if (!$rows) json_out(false, ['message' => 'Tópico não encontrado.'], 404);
            $topico = $rows[0];
            $topico['mensagem'] = $topico['texto'] ?? '';
            $topico['categoria'] = 'Aula: ' . api_data_text(($topico['aula_titulo'] ?? '') ?: ($topico['modulo_titulo'] ?? 'Conteúdo'));
            $respostas = api_data_has($pdo, 'aulas_forum_respostas') ? api_data_fetch_all($pdo, "
                SELECT r.*, COALESCE(NULLIF(u.nome, ''), NULLIF(r.autor_nome, ''), 'Aluno') AS autor_nome
                FROM aulas_forum_respostas r
                LEFT JOIN usuarios u ON u.id = r.usuario_id
                WHERE r.topico_id = :id AND " . api_data_active_sql('r') . "
                ORDER BY r.criado_em ASC, r.id ASC
            ", [':id' => $id]) : [];
        } else {
            if (!api_data_has($pdo, 'forum_duvidas_topicos')) json_out(false, ['message' => 'Tópico não encontrado.'], 404);
            $rows = api_data_fetch_all($pdo, "
                SELECT t.*, COALESCE(NULLIF(u.nome, ''), NULLIF(t.autor_nome, ''), 'Aluno') AS autor_nome
                FROM forum_duvidas_topicos t
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                WHERE t.id = :id AND " . api_data_active_sql('t') . "
                LIMIT 1
            ", [':id' => $id]);
            if (!$rows) json_out(false, ['message' => 'Tópico não encontrado.'], 404);
            $topico = $rows[0];
            $respostas = api_data_has($pdo, 'forum_duvidas_respostas') ? api_data_fetch_all($pdo, "
                SELECT r.*, COALESCE(NULLIF(u.nome, ''), NULLIF(r.autor_nome, ''), 'Aluno') AS autor_nome
                FROM forum_duvidas_respostas r
                LEFT JOIN usuarios u ON u.id = r.usuario_id
                WHERE r.topico_id = :id AND " . api_data_active_sql('r') . "
                ORDER BY r.criado_em ASC, r.id ASC
            ", [':id' => $id]) : [];
        }

        $topico['origem'] = $origem;
        $topico['titulo'] = api_data_text($topico['titulo'] ?? 'Tópico');
        $topico['mensagem'] = api_data_text($topico['mensagem'] ?? '');
        foreach ($respostas as &$resposta) {
            $resposta['mensagem'] = api_data_text($resposta['mensagem'] ?? '');
        }
        unset($resposta);

        return ['topico' => $topico, 'respostas' => $respostas];
    }

    function api_data_create_forum_topic(PDO $pdo, int $usuarioId, array $current, array $body): array
    {
        if (!api_data_has($pdo, 'forum_duvidas_topicos')) {
            json_out(false, ['message' => 'Tabela do fórum não encontrada.'], 500);
        }
        $titulo = api_data_text($body['titulo'] ?? '');
        $mensagem = api_data_text($body['mensagem'] ?? '');
        $categoria = api_data_text($body['categoria'] ?? 'Geral');
        if ($titulo === '' || $mensagem === '') {
            json_out(false, ['message' => 'Informe título e mensagem.'], 422);
        }
        $autor = api_data_text($current['nome'] ?? $current['email'] ?? 'Aluno');
        $stmt = $pdo->prepare('INSERT INTO forum_duvidas_topicos (usuario_id, autor_nome, titulo, mensagem, categoria, privado, status) VALUES (?, ?, ?, ?, ?, 0, \'ativo\')');
        $stmt->execute([$usuarioId, $autor, $titulo, $mensagem, $categoria ?: 'Geral']);
        return ['message' => 'Tópico criado.', 'id' => (int)$pdo->lastInsertId(), 'origem' => 'forum'];
    }

    function api_data_reply_forum_topic(PDO $pdo, int $usuarioId, array $current, array $body): array
    {
        $tipoUsuario = strtolower(trim((string)($current['tipo'] ?? $current['perfil'] ?? $current['role'] ?? 'cliente')));
        if (!in_array($tipoUsuario, ['admin', 'administrador', 'adm', 'master', 'superadmin', '1'], true)) {
            json_out(false, ['message' => 'Somente o admin pode responder dúvidas.'], 403);
        }

        $topicoId = (int)($body['topico_id'] ?? 0);
        $origem = ((string)($body['origem'] ?? 'forum')) === 'aula' ? 'aula' : 'forum';
        $mensagem = api_data_text($body['mensagem'] ?? '');
        if ($topicoId <= 0 || $mensagem === '') {
            json_out(false, ['message' => 'Informe a resposta.'], 422);
        }
        $autor = api_data_text($current['nome'] ?? $current['email'] ?? 'Admin');
        if ($origem === 'aula') {
            if (!api_data_has($pdo, 'aulas_forum_respostas')) json_out(false, ['message' => 'Tabela de respostas não encontrada.'], 500);
            $stmt = $pdo->prepare('INSERT INTO aulas_forum_respostas (topico_id, usuario_id, autor_nome, mensagem, status) VALUES (?, ?, ?, ?, \'ativo\')');
        } else {
            if (!api_data_has($pdo, 'forum_duvidas_respostas')) json_out(false, ['message' => 'Tabela de respostas não encontrada.'], 500);
            $stmt = $pdo->prepare('INSERT INTO forum_duvidas_respostas (topico_id, usuario_id, autor_nome, mensagem, status) VALUES (?, ?, ?, ?, \'ativo\')');
        }
        $stmt->execute([$topicoId, $usuarioId, $autor, $mensagem]);
        return ['message' => 'Resposta enviada.', 'id' => (int)$pdo->lastInsertId()];
    }

    function api_data_youtube_embed(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~youtube\.com/embed/([a-zA-Z0-9_-]+)~', $url, $match)) {
            return 'https://www.youtube.com/embed/' . $match[1] . '?rel=0&modestbranding=1&playsinline=1';
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return $url;
        $host = strtolower((string)$parts['host']);
        $path = (string)($parts['path'] ?? '');
        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);
        $videoId = '';
        if (isset($query['v']) && is_string($query['v'])) {
            $videoId = $query['v'];
        } elseif (str_contains($host, 'youtu.be')) {
            $videoId = trim($path, '/');
        } elseif (preg_match('~/(shorts|live)/([a-zA-Z0-9_-]+)~', $path, $match)) {
            $videoId = $match[2];
        }
        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$videoId);
        if ($videoId !== '') {
            return 'https://www.youtube.com/embed/' . $videoId . '?rel=0&modestbranding=1&playsinline=1';
        }
        return $url;
    }

    function api_data_format_date(?string $date): string
    {
        $date = trim((string)$date);
        if ($date === '') return '';
        $ts = strtotime($date);
        return $ts ? date('d/m/Y H:i', $ts) : $date;
    }

    function api_data_ensure_aulas_tables(PDO $pdo): void
    {
        require_once __DIR__ . '/../includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS aulas_progresso (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            modulo_id INT UNSIGNED NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            assistido TINYINT(1) NOT NULL DEFAULT 1,
            assistido_em DATETIME DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_aulas_progresso_usuario_aula (usuario_id, aula_id),
            KEY idx_aulas_progresso_usuario_modulo (usuario_id, modulo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS aulas_avaliacoes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            modulo_id INT UNSIGNED NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            nota TINYINT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_aulas_avaliacoes_usuario_aula (usuario_id, aula_id),
            KEY idx_aulas_avaliacoes_aula (aula_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS aulas_forum_topicos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED DEFAULT NULL,
            curso_id INT UNSIGNED NOT NULL,
            modulo_id INT UNSIGNED NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            texto TEXT NOT NULL,
            visibilidade ENUM('todos','monitores') NOT NULL DEFAULT 'todos',
            status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aulas_forum_topicos_aula (aula_id),
            KEY idx_aulas_forum_topicos_modulo (modulo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS aulas_forum_respostas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topico_id BIGINT UNSIGNED NOT NULL,
            usuario_id BIGINT UNSIGNED DEFAULT NULL,
            autor_nome VARCHAR(160) DEFAULT NULL,
            mensagem TEXT NOT NULL,
            status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aulas_forum_respostas_topico (topico_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function api_data_aula_pdf(array $aula): array
    {
        $arquivo = trim((string)($aula['pdf_arquivo'] ?? ''));
        $nome = trim((string)($aula['pdf_nome'] ?? ''));
        if ($arquivo === '') {
            $arquivo = trim((string)($aula['material_arquivo'] ?? ''));
            $nome = trim((string)($aula['material_nome'] ?? ''));
        }
        if ($arquivo === '') return ['url' => '', 'nome' => ''];
        if ($nome === '') $nome = basename($arquivo);
        return ['url' => api_data_public_url($arquivo), 'nome' => $nome];
    }

    function api_data_map_aula(array $row, array $assistidas = [], array $notas = []): array
    {
        $videoRaw = trim((string)($row['video_url'] ?? ''));
        if ($videoRaw === '') $videoRaw = trim((string)($row['video_arquivo'] ?? ''));
        $videoUrl = $videoRaw;
        if ($videoUrl !== '' && !preg_match('~^https?://~i', $videoUrl) && !str_starts_with($videoUrl, '//')) {
            $videoUrl = api_data_public_url($videoUrl);
        }
        $pdf = api_data_aula_pdf($row);
        $videoTempo = api_data_text($row['duracao'] ?? ($row['video_duracao'] ?? ''));
        $pdfTempo = api_data_text($row['pdf_duracao'] ?? ($row['tempo_pdf'] ?? ''));
        $videoMin = (int)round(api_data_parse_duracao($videoTempo) * 60);
        $pdfMin = (int)round(api_data_parse_duracao($pdfTempo) * 60);
        $totalMin = max(0, $videoMin + $pdfMin);
        $id = (int)($row['id'] ?? 0);
        $row['titulo'] = api_data_text($row['titulo'] ?? 'Aula');
        $row['descricao'] = api_data_text($row['descricao'] ?? '');
        $row['video_url'] = $videoUrl;
        $row['video_embed_url'] = api_data_youtube_embed($videoUrl);
        $row['pdf_url'] = $pdf['url'];
        $row['pdf_nome'] = $pdf['nome'];
        $row['pdf_arquivo_url'] = $pdf['url'];
        $row['material_arquivo_url'] = $pdf['url'];
        $row['tempo_video'] = $videoTempo !== '' ? $videoTempo : ($videoMin > 0 ? api_data_format_minutos($videoMin) : '');
        $row['video_duracao'] = $row['tempo_video'];
        $row['tempo_pdf'] = $pdfTempo !== '' ? $pdfTempo : ($pdfMin > 0 ? api_data_format_minutos($pdfMin) : '');
        $row['pdf_duracao'] = $row['tempo_pdf'];
        $row['tempo_total'] = $totalMin > 0 ? api_data_format_minutos($totalMin) : '';
        $row['tempo_conteudo'] = $row['tempo_total'];
        $row['assistido'] = isset($assistidas[$id]);
        $row['minha_nota'] = (int)($notas[$id] ?? 0);
        return $row;
    }

    function api_data_aulas_assistidas(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId): array
    {
        if ($usuarioId <= 0 || !api_data_has($pdo, 'aulas_progresso')) return [];
        $stmt = $pdo->prepare("SELECT aula_id FROM aulas_progresso WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND modulo_id = :modulo_id AND assistido = 1");
        $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['aula_id']] = true;
        }
        return $out;
    }

    function api_data_aulas_notas(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId): array
    {
        if ($usuarioId <= 0 || !api_data_has($pdo, 'aulas_avaliacoes')) return [];
        $stmt = $pdo->prepare("SELECT aula_id, nota FROM aulas_avaliacoes WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND modulo_id = :modulo_id");
        $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['aula_id']] = (int)$row['nota'];
        }
        return $out;
    }

    function api_data_aula_avaliacao(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId, int $aulaId): array
    {
        $minhaNota = 0;
        if ($usuarioId > 0 && api_data_has($pdo, 'aulas_avaliacoes')) {
            $stmt = $pdo->prepare("SELECT nota FROM aulas_avaliacoes WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND modulo_id = :modulo_id AND aula_id = :aula_id LIMIT 1");
            $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId]);
            $minhaNota = (int)($stmt->fetchColumn() ?: 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(AVG(nota), 0) AS media FROM aulas_avaliacoes WHERE curso_id = :curso_id AND modulo_id = :modulo_id AND aula_id = :aula_id");
            $stmt->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'media' => 0];
            return ['minha_nota' => max(0, min(5, $minhaNota)), 'media' => round((float)($media['media'] ?? 0), 1), 'total' => (int)($media['total'] ?? 0)];
        }
        return ['minha_nota' => 0, 'media' => 0, 'total' => 0];
    }

    function api_data_aula_topicos(PDO $pdo, int $cursoId, int $moduloId, int $aulaId): array
    {
        if (!api_data_has($pdo, 'aulas_forum_topicos')) return [];
        $stmt = $pdo->prepare("SELECT t.*, COALESCE(u.nome, 'Aluno') AS usuario_nome FROM aulas_forum_topicos t LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE t.curso_id = :curso_id AND t.modulo_id = :modulo_id AND t.aula_id = :aula_id AND COALESCE(t.status, 'ativo') = 'ativo' ORDER BY t.criado_em DESC, t.id DESC LIMIT 30");
        $stmt->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId]);
        $topicos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$topicos) return [];
        $ids = array_values(array_filter(array_map(static fn($t): int => (int)($t['id'] ?? 0), $topicos)));
        $respostasPorTopico = [];
        if ($ids && api_data_has($pdo, 'aulas_forum_respostas')) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM aulas_forum_respostas WHERE COALESCE(status, 'ativo') = 'ativo' AND topico_id IN ($placeholders) ORDER BY criado_em ASC, id ASC");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resp) {
                $tid = (int)($resp['topico_id'] ?? 0);
                $resp['criado_em_formatado'] = api_data_format_date((string)($resp['criado_em'] ?? ''));
                $respostasPorTopico[$tid][] = $resp;
            }
        }
        return array_map(static function(array $topico) use ($respostasPorTopico): array {
            $id = (int)($topico['id'] ?? 0);
            $topico['titulo'] = api_data_text($topico['titulo'] ?? 'Tópico');
            $topico['texto'] = api_data_text($topico['texto'] ?? '');
            $topico['criado_em_formatado'] = api_data_format_date((string)($topico['criado_em'] ?? ''));
            $topico['respostas'] = $respostasPorTopico[$id] ?? [];
            return $topico;
        }, $topicos);
    }

    function api_data_aula_detalhe(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId, int $aulaId): array
    {
        api_data_ensure_aulas_tables($pdo);
        if ($cursoId <= 0 || $moduloId <= 0 || !api_data_has($pdo, 'admin_curso_aulas')) {
            json_out(false, ['message' => 'Curso ou módulo não informado.'], 422);
        }
        $stmtCurso = $pdo->prepare("SELECT * FROM admin_cursos WHERE id = :curso_id LIMIT 1");
        $stmtCurso->execute([':curso_id' => $cursoId]);
        $curso = $stmtCurso->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$curso) json_out(false, ['message' => 'Curso não encontrado.'], 404);
        $curso['titulo'] = api_data_text($curso['nome'] ?? 'Curso');
        $curso['capa_url'] = api_data_public_url((string)($curso['capa'] ?? ''));

        $stmtModulo = $pdo->prepare("SELECT * FROM admin_curso_modulos WHERE id = :modulo_id AND curso_id = :curso_id LIMIT 1");
        $stmtModulo->execute([':modulo_id' => $moduloId, ':curso_id' => $cursoId]);
        $modulo = $stmtModulo->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$modulo) json_out(false, ['message' => 'Módulo não encontrado.'], 404);
        $modulo['titulo'] = api_data_text($modulo['titulo'] ?? 'Módulo');

        $stmtAulas = $pdo->prepare("SELECT * FROM admin_curso_aulas WHERE curso_id = :curso_id AND modulo_id = :modulo_id ORDER BY ordem ASC, id ASC");
        $stmtAulas->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        $rows = $stmtAulas->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) json_out(false, ['message' => 'Esse módulo ainda não tem aulas cadastradas.'], 404);

        $assistidas = api_data_aulas_assistidas($pdo, $usuarioId, $cursoId, $moduloId);
        $notas = api_data_aulas_notas($pdo, $usuarioId, $cursoId, $moduloId);
        $aulas = array_map(static fn(array $row): array => api_data_map_aula($row, $assistidas, $notas), $rows);

        $idx = 0;
        foreach ($aulas as $i => $aula) {
            if ($aulaId > 0 && (int)($aula['id'] ?? 0) === $aulaId) { $idx = $i; break; }
        }
        $aulaAtual = $aulas[$idx] ?? $aulas[0];
        $aulaAtualId = (int)($aulaAtual['id'] ?? 0);
        $total = count($aulas);
        $percentual = $total > 0 ? (int)round((count($assistidas) / $total) * 100) : 0;

        return [
            'curso' => $curso,
            'modulo' => $modulo,
            'aula' => $aulaAtual,
            'aulas' => $aulas,
            'progresso' => $percentual,
            'assistidas' => array_map('intval', array_keys($assistidas)),
            'avaliacao' => api_data_aula_avaliacao($pdo, $usuarioId, $cursoId, $moduloId, $aulaAtualId),
            'topicos' => api_data_aula_topicos($pdo, $cursoId, $moduloId, $aulaAtualId),
            'anterior_aula_id' => isset($aulas[$idx - 1]) ? (int)$aulas[$idx - 1]['id'] : 0,
            'proximo_aula_id' => isset($aulas[$idx + 1]) ? (int)$aulas[$idx + 1]['id'] : 0,
        ];
    }

    function api_data_aula_action(PDO $pdo, int $usuarioId, array $body): void
    {
        api_data_ensure_aulas_tables($pdo);
        $acao = trim((string)($body['acao'] ?? ''));
        $cursoId = (int)($body['curso_id'] ?? 0);
        $moduloId = (int)($body['modulo_id'] ?? 0);
        $aulaId = (int)($body['aula_id'] ?? 0);
        if ($usuarioId <= 0) json_out(false, ['message' => 'Faça login novamente.'], 401);
        if ($cursoId <= 0 || $moduloId <= 0 || $aulaId <= 0) json_out(false, ['message' => 'Curso, módulo ou aula não informado.'], 422);

        $stmt = $pdo->prepare("SELECT id FROM admin_curso_aulas WHERE id = :aula_id AND curso_id = :curso_id AND modulo_id = :modulo_id LIMIT 1");
        $stmt->execute([':aula_id' => $aulaId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        if (!$stmt->fetchColumn()) json_out(false, ['message' => 'Aula não encontrada.'], 404);

        if ($acao === 'marcar_assistido') {
            $stmt = $pdo->prepare("INSERT INTO aulas_progresso (usuario_id, curso_id, modulo_id, aula_id, assistido, assistido_em, criado_em) VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE assistido = 1, assistido_em = IF(assistido_em IS NULL, NOW(), assistido_em), atualizado_em = NOW()");
            $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId]);
            $detalhe = api_data_aula_detalhe($pdo, $usuarioId, $cursoId, $moduloId, $aulaId);
            json_out(true, ['message' => 'Aula marcada como assistida.', 'aula_id' => $aulaId, 'progresso' => $detalhe['progresso'], 'assistidas' => $detalhe['assistidas']]);
        }

        if ($acao === 'avaliar_aula') {
            $nota = max(1, min(5, (int)($body['nota'] ?? 0)));
            $stmt = $pdo->prepare("INSERT INTO aulas_avaliacoes (usuario_id, curso_id, modulo_id, aula_id, nota, criado_em) VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, :nota, NOW()) ON DUPLICATE KEY UPDATE nota = VALUES(nota), atualizado_em = NOW()");
            $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId, ':nota' => $nota]);
            $avaliacao = api_data_aula_avaliacao($pdo, $usuarioId, $cursoId, $moduloId, $aulaId);
            json_out(true, ['message' => 'Avaliação salva.', 'nota' => $nota, 'media' => $avaliacao['media'], 'total' => $avaliacao['total']]);
        }

        if ($acao === 'criar_topico') {
            $tituloRaw = trim((string)($body['titulo'] ?? ''));
            $textoRaw = trim((string)($body['texto'] ?? ''));
            $titulo = function_exists('mb_substr') ? mb_substr($tituloRaw, 0, 180, 'UTF-8') : substr($tituloRaw, 0, 180);
            $texto = function_exists('mb_substr') ? mb_substr($textoRaw, 0, 5000, 'UTF-8') : substr($textoRaw, 0, 5000);
            $visibilidade = (string)($body['visibilidade'] ?? 'todos');
            if (!in_array($visibilidade, ['todos','monitores'], true)) $visibilidade = 'todos';
            if ($titulo === '' || $texto === '') json_out(false, ['message' => 'Preencha título e texto do tópico.'], 422);
            $stmt = $pdo->prepare("INSERT INTO aulas_forum_topicos (usuario_id, curso_id, modulo_id, aula_id, titulo, texto, visibilidade, status, criado_em) VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, :titulo, :texto, :visibilidade, 'ativo', NOW())");
            $stmt->execute([':usuario_id' => $usuarioId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId, ':aula_id' => $aulaId, ':titulo' => $titulo, ':texto' => $texto, ':visibilidade' => $visibilidade]);
            json_out(true, ['message' => 'Tópico criado.', 'topicos' => api_data_aula_topicos($pdo, $cursoId, $moduloId, $aulaId)]);
        }

        json_out(false, ['message' => 'Ação não reconhecida.'], 422);
    }


    function api_data_material_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/../includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        api_data_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_material_pastas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_pastas_curso (curso_id),
            INDEX idx_admin_material_pastas_status (status),
            INDEX idx_admin_material_pastas_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        api_data_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_material_arquivos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pasta_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(190) NOT NULL,
            arquivo VARCHAR(255) NOT NULL,
            arquivo_nome VARCHAR(255) NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_arquivos_pasta (pasta_id),
            INDEX idx_admin_material_arquivos_status (status),
            INDEX idx_admin_material_arquivos_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function api_data_material_nome_arquivo(string $arquivo, string $nome = ''): string
    {
        $nome = trim($nome);
        if ($nome !== '') return api_data_text($nome);
        $path = parse_url($arquivo, PHP_URL_PATH);
        $base = basename((string)($path ?: $arquivo));
        return $base !== '' ? $base : 'Arquivo do conteúdo';
    }

    function api_data_material_search_text(array $parts): string
    {
        $text = api_data_text(implode(' ', array_map(static fn($v): string => (string)($v ?? ''), $parts)));
        if (function_exists('mb_strtolower')) return mb_strtolower($text, 'UTF-8');
        return strtolower($text);
    }

    function api_data_material_file_item(int $id, string $titulo, string $arquivo, string $tipo = 'PDF', array $extra = []): array
    {
        $url = api_data_public_url($arquivo);
        $tituloFinal = api_data_material_nome_arquivo($arquivo, $titulo);
        return $extra + [
            'kind' => 'arquivo',
            'id' => $id,
            'titulo' => $tituloFinal,
            'subtitulo' => $tipo,
            'tipo' => $tipo,
            'arquivo' => $arquivo,
            'arquivo_url' => $url,
            'download_url' => $url,
            'search_text' => api_data_material_search_text([$tituloFinal, $tipo, $arquivo]),
        ];
    }

    function api_data_material_arquivos_da_aula(array $aula): array
    {
        $arquivos = [];
        $vistos = [];
        $campos = [
            ['arquivo' => 'pdf_arquivo', 'nome' => 'pdf_nome', 'tipo' => 'PDF'],
            ['arquivo' => 'material_arquivo', 'nome' => 'material_nome', 'tipo' => 'Material'],
        ];
        foreach ($campos as $campo) {
            $arquivo = trim((string)($aula[$campo['arquivo']] ?? ''));
            if ($arquivo === '' || isset($vistos[$arquivo])) continue;
            $vistos[$arquivo] = true;
            $arquivos[] = api_data_material_file_item(
                count($arquivos) + 1,
                (string)($aula[$campo['nome']] ?? ''),
                $arquivo,
                (string)$campo['tipo'],
                [
                    'curso_id' => (int)($aula['curso_id'] ?? 0),
                    'modulo_id' => (int)($aula['modulo_id'] ?? 0),
                    'aula_id' => (int)($aula['id'] ?? 0),
                ]
            );
        }
        return $arquivos;
    }

    function api_data_material_pastas(PDO $pdo, ?int $cursoId = null): array
    {
        api_data_material_bootstrap($pdo);
        if (!api_data_has($pdo, 'admin_material_pastas') || !api_data_has($pdo, 'admin_material_arquivos')) return [];

        if ($cursoId !== null && $cursoId > 0) {
            $rows = api_data_fetch_all($pdo, "
                SELECT p.id, p.curso_id, p.titulo, p.descricao, COUNT(a.id) AS total_pdfs
                FROM admin_material_pastas p
                LEFT JOIN admin_material_arquivos a ON a.pasta_id = p.id AND COALESCE(a.status, 'ativo') = 'ativo'
                WHERE COALESCE(p.status, 'ativo') = 'ativo' AND p.curso_id = :curso_id
                GROUP BY p.id, p.curso_id, p.titulo, p.descricao
                ORDER BY p.ordem ASC, p.id DESC
            ", [':curso_id' => $cursoId]);
        } else {
            $rows = api_data_fetch_all($pdo, "
                SELECT p.id, p.curso_id, p.titulo, p.descricao, COUNT(a.id) AS total_pdfs
                FROM admin_material_pastas p
                LEFT JOIN admin_material_arquivos a ON a.pasta_id = p.id AND COALESCE(a.status, 'ativo') = 'ativo'
                WHERE COALESCE(p.status, 'ativo') = 'ativo' AND p.curso_id IS NULL
                GROUP BY p.id, p.curso_id, p.titulo, p.descricao
                ORDER BY p.ordem ASC, p.id DESC
            ");
        }

        return array_map(static function(array $row): array {
            $total = (int)($row['total_pdfs'] ?? 0);
            $isGeral = empty($row['curso_id']);
            $meta = ($isGeral ? 'Material geral' : 'Material do curso') . ' · ' . $total . ($total === 1 ? ' PDF' : ' PDFs');
            return [
                'kind' => 'pasta',
                'id' => (int)($row['id'] ?? 0),
                'curso_id' => (int)($row['curso_id'] ?? 0),
                'titulo' => api_data_text($row['titulo'] ?? 'Pasta'),
                'descricao' => api_data_text($row['descricao'] ?? ''),
                'subtitulo' => $meta,
                'total_pdfs' => $total,
                'search_text' => api_data_material_search_text([$row['titulo'] ?? '', $row['descricao'] ?? '', 'pdf material pasta', $meta]),
            ];
        }, $rows);
    }

    function api_data_material_simulado_meta(array $simulado): string
    {
        $partes = [];
        $partes[] = !empty($simulado['curso_id']) ? (string)($simulado['curso_nome'] ?? 'Curso específico') : 'Todos os cursos';
        $questoes = (int)($simulado['total_questoes'] ?? 0);
        $partes[] = $questoes . ($questoes === 1 ? ' questão' : ' questões');
        $tempo = (int)($simulado['tempo_minutos'] ?? 0);
        $partes[] = $tempo > 0 ? $tempo . ' min' : 'Sem limite';
        return implode(' · ', $partes);
    }

    function api_data_materiais(PDO $pdo, int $usuarioId, array $params): array
    {
        api_data_material_bootstrap($pdo);

        $cursoId = (int)($params['curso_id'] ?? 0);
        $moduloId = (int)($params['modulo_id'] ?? 0);
        $aulaId = (int)($params['aula_id'] ?? 0);
        $pastaId = (int)($params['pasta_id'] ?? 0);
        $materialTipo = preg_replace('/[^a-z0-9_-]/i', '', (string)($params['material_tipo'] ?? $params['tipo_material'] ?? ''));

        if ($cursoId > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoId)) {
            return [
                'nivel' => 'bloqueado',
                'titulo' => 'Materiais',
                'items' => [],
                'empty_message' => 'Este curso não está liberado para o seu usuário.',
            ];
        }

        if ($materialTipo === 'simulados') {
            $simulados = api_data_simulados($pdo, $usuarioId);
            $items = array_map(static function(array $simulado): array {
                $meta = api_data_material_simulado_meta($simulado);
                return [
                    'kind' => 'simulado',
                    'id' => (int)($simulado['id'] ?? 0),
                    'curso_id' => (int)($simulado['curso_id'] ?? 0),
                    'titulo' => api_data_text($simulado['titulo'] ?? 'Simulado'),
                    'descricao' => api_data_text($simulado['descricao'] ?? ''),
                    'subtitulo' => $meta,
                    'tempo_minutos' => (int)($simulado['tempo_minutos'] ?? 0),
                    'total_questoes' => (int)($simulado['total_questoes'] ?? 0),
                    'search_text' => api_data_material_search_text([$simulado['titulo'] ?? '', $simulado['descricao'] ?? '', $meta, 'simulado prova questão']),
                ];
            }, $simulados);
            return [
                'nivel' => 'simulados',
                'titulo' => 'Simulados',
                'items' => array_values($items),
                'empty_message' => 'Nenhum simulado disponível para o seu acesso.',
            ];
        }

        if ($pastaId > 0) {
            if (!api_data_has($pdo, 'admin_material_pastas') || !api_data_has($pdo, 'admin_material_arquivos')) {
                return ['nivel' => 'pasta_arquivos', 'titulo' => 'Pasta', 'items' => [], 'empty_message' => 'Nenhum PDF cadastrado nesta pasta.'];
            }
            $rows = api_data_fetch_all($pdo, "
                SELECT p.*, c.nome AS curso_nome
                FROM admin_material_pastas p
                LEFT JOIN admin_cursos c ON c.id = p.curso_id
                WHERE p.id = :id AND COALESCE(p.status, 'ativo') = 'ativo'
                LIMIT 1
            ", [':id' => $pastaId]);
            $pasta = $rows[0] ?? null;
            if (!$pasta) {
                return ['nivel' => 'pasta_arquivos', 'titulo' => 'Pasta', 'items' => [], 'empty_message' => 'Pasta de materiais não encontrada ou indisponível.'];
            }
            $pastaCursoId = (int)($pasta['curso_id'] ?? 0);
            if ($pastaCursoId > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $pastaCursoId)) {
                return ['nivel' => 'pasta_arquivos', 'titulo' => api_data_text($pasta['titulo'] ?? 'Pasta'), 'items' => [], 'empty_message' => 'Este material não está liberado para o seu usuário.'];
            }
            $files = api_data_fetch_all($pdo, "
                SELECT id, pasta_id, titulo, arquivo, arquivo_nome
                FROM admin_material_arquivos
                WHERE pasta_id = :pasta_id AND COALESCE(status, 'ativo') = 'ativo'
                ORDER BY ordem ASC, id DESC
            ", [':pasta_id' => $pastaId]);
            $items = array_map(static function(array $file) use ($pastaCursoId): array {
                return api_data_material_file_item(
                    (int)($file['id'] ?? 0),
                    (string)(($file['titulo'] ?? '') ?: ($file['arquivo_nome'] ?? '')),
                    (string)($file['arquivo'] ?? ''),
                    'PDF',
                    ['curso_id' => $pastaCursoId, 'pasta_id' => (int)($file['pasta_id'] ?? 0)]
                );
            }, $files);
            return [
                'nivel' => 'pasta_arquivos',
                'titulo' => api_data_text($pasta['titulo'] ?? 'Pasta'),
                'curso_id' => $pastaCursoId,
                'items' => array_values($items),
                'empty_message' => 'Nenhum PDF cadastrado nesta pasta.',
            ];
        }

        if ($cursoId > 0 && $moduloId > 0 && $aulaId > 0) {
            if (!api_data_has($pdo, 'admin_curso_aulas')) {
                return ['nivel' => 'arquivos', 'titulo' => 'Videoaula', 'items' => [], 'empty_message' => 'Nenhum PDF, simulado ou material cadastrado nesta videoaula.'];
            }
            $rows = api_data_fetch_all($pdo, "
                SELECT id, curso_id, modulo_id, titulo, descricao, pdf_arquivo, pdf_nome, material_arquivo, material_nome
                FROM admin_curso_aulas
                WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id
                LIMIT 1
            ", [':id' => $aulaId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
            $aula = $rows[0] ?? null;
            if (!$aula) {
                return ['nivel' => 'arquivos', 'titulo' => 'Videoaula', 'items' => [], 'empty_message' => 'Videoaula não encontrada para este módulo.'];
            }
            return [
                'nivel' => 'arquivos',
                'titulo' => api_data_text($aula['titulo'] ?? 'Videoaula'),
                'curso_id' => $cursoId,
                'modulo_id' => $moduloId,
                'aula_id' => $aulaId,
                'items' => api_data_material_arquivos_da_aula($aula),
                'empty_message' => 'Nenhum PDF, simulado ou material cadastrado nesta videoaula.',
            ];
        }

        if ($cursoId > 0 && $moduloId > 0) {
            $moduloRows = api_data_fetch_all($pdo, "
                SELECT id, curso_id, titulo, descricao
                FROM admin_curso_modulos
                WHERE id = :id AND curso_id = :curso_id
                LIMIT 1
            ", [':id' => $moduloId, ':curso_id' => $cursoId]);
            $modulo = $moduloRows[0] ?? null;
            $aulas = api_data_fetch_all($pdo, "
                SELECT
                    a.id,
                    a.curso_id,
                    a.modulo_id,
                    a.titulo,
                    a.descricao,
                    a.ordem,
                    CASE WHEN COALESCE(NULLIF(a.pdf_arquivo, ''), NULLIF(a.material_arquivo, '')) IS NULL THEN 0 ELSE 1 END AS tem_arquivo
                FROM admin_curso_aulas a
                WHERE a.curso_id = :curso_id AND a.modulo_id = :modulo_id
                ORDER BY a.ordem ASC, a.id ASC
            ", [':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
            $items = array_map(static function(array $aula): array {
                $tem = (int)($aula['tem_arquivo'] ?? 0) > 0;
                $meta = $tem ? 'Documentos disponíveis' : 'Pasta vazia';
                return [
                    'kind' => 'aula',
                    'id' => (int)($aula['id'] ?? 0),
                    'curso_id' => (int)($aula['curso_id'] ?? 0),
                    'modulo_id' => (int)($aula['modulo_id'] ?? 0),
                    'titulo' => api_data_text($aula['titulo'] ?? 'Aula'),
                    'descricao' => api_data_text($aula['descricao'] ?? ''),
                    'subtitulo' => $meta,
                    'tem_arquivo' => $tem ? 1 : 0,
                    'search_text' => api_data_material_search_text([$aula['titulo'] ?? '', $aula['descricao'] ?? '', $meta]),
                ];
            }, $aulas);
            return [
                'nivel' => 'aulas',
                'titulo' => api_data_text($modulo['titulo'] ?? 'Módulo'),
                'curso_id' => $cursoId,
                'modulo_id' => $moduloId,
                'items' => array_values($items),
                'empty_message' => 'Nenhuma videoaula cadastrada neste módulo.',
            ];
        }

        if ($cursoId > 0) {
            $curso = null;
            foreach (api_data_cursos($pdo, $usuarioId) as $c) {
                if ((int)($c['id'] ?? 0) === $cursoId) { $curso = $c; break; }
            }
            if (!$curso) {
                return ['nivel' => 'modulos', 'titulo' => 'Curso', 'items' => [], 'empty_message' => 'Curso não encontrado ou indisponível.'];
            }
            $items = api_data_material_pastas($pdo, $cursoId);
            $modulos = api_data_fetch_all($pdo, "
                SELECT m.id, m.curso_id, m.titulo, m.descricao, m.ordem, COUNT(a.id) AS total_aulas
                FROM admin_curso_modulos m
                LEFT JOIN admin_curso_aulas a ON a.modulo_id = m.id
                WHERE m.curso_id = :curso_id
                GROUP BY m.id, m.curso_id, m.titulo, m.descricao, m.ordem
                ORDER BY m.ordem ASC, m.id ASC
            ", [':curso_id' => $cursoId]);
            foreach ($modulos as $modulo) {
                $total = (int)($modulo['total_aulas'] ?? 0);
                $meta = $total . ($total === 1 ? ' videoaula' : ' videoaulas');
                $items[] = [
                    'kind' => 'modulo',
                    'id' => (int)($modulo['id'] ?? 0),
                    'curso_id' => (int)($modulo['curso_id'] ?? $cursoId),
                    'titulo' => api_data_text($modulo['titulo'] ?? 'Módulo'),
                    'descricao' => api_data_text($modulo['descricao'] ?? ''),
                    'subtitulo' => $meta,
                    'total_aulas' => $total,
                    'search_text' => api_data_material_search_text([$modulo['titulo'] ?? '', $modulo['descricao'] ?? '', $meta]),
                ];
            }
            return [
                'nivel' => 'modulos',
                'titulo' => api_data_text($curso['titulo'] ?? $curso['nome'] ?? 'Curso'),
                'curso_id' => $cursoId,
                'items' => array_values($items),
                'empty_message' => 'Nenhum módulo ou pasta de material cadastrado neste curso.',
            ];
        }

        $items = api_data_material_pastas($pdo, null);
        $simulados = api_data_simulados($pdo, $usuarioId);
        if (count($simulados) > 0) {
            $total = count($simulados);
            $items[] = [
                'kind' => 'simulados_folder',
                'id' => 0,
                'titulo' => 'Simulados',
                'subtitulo' => $total . ($total === 1 ? ' simulado disponível' : ' simulados disponíveis'),
                'total_simulados' => $total,
                'search_text' => api_data_material_search_text(['simulados provas questões revisão exercícios']),
            ];
        }
        foreach (api_data_cursos($pdo, $usuarioId) as $curso) {
            $total = (int)($curso['total_modulos'] ?? 0);
            $meta = $total . ($total === 1 ? ' módulo' : ' módulos');
            $items[] = [
                'kind' => 'curso',
                'id' => (int)($curso['id'] ?? 0),
                'curso_id' => (int)($curso['id'] ?? 0),
                'titulo' => api_data_text($curso['titulo'] ?? $curso['nome'] ?? 'Curso'),
                'descricao' => api_data_text($curso['descricao'] ?? ''),
                'subtitulo' => $meta,
                'total_modulos' => $total,
                'search_text' => api_data_material_search_text([$curso['titulo'] ?? $curso['nome'] ?? '', $curso['descricao'] ?? '', $meta]),
            ];
        }
        return [
            'nivel' => 'cursos',
            'titulo' => 'Materiais',
            'items' => array_values($items),
            'empty_message' => 'Nenhum curso, material geral ou simulado cadastrado.',
        ];
    }


    function api_data_notificacoes(PDO $pdo, int $usuarioId): array
    {
        if (!function_exists('gab_notif_buscar')) {
            return [];
        }
        $items = gab_notif_buscar($pdo, $usuarioId, 30);
        if (!is_array($items)) {
            $items = [];
        }
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'tipo' => api_data_text($item['tipo'] ?? ''),
                'referencia_id' => (int)($item['referencia_id'] ?? 0),
                'titulo' => api_data_text($item['titulo'] ?? 'Notificação'),
                'descricao' => api_data_text($item['descricao'] ?? ''),
                'link' => (string)($item['link'] ?? ''),
                'icone' => api_data_text($item['icone'] ?? 'bell'),
                'data' => (string)($item['data'] ?? ''),
                'tempo' => api_data_text($item['tempo'] ?? ''),
                'lida' => !empty($item['lida']),
            ];
        }
        return $out;
    }

    function api_data_mark_notification(PDO $pdo, int $usuarioId, array $body): array
    {
        if (!function_exists('gab_notif_marcar_lida')) {
            return ['ok' => true];
        }
        $tipoNotificacao = trim((string)($body['notificacao_tipo'] ?? $body['notification_type'] ?? $body['notificacao'] ?? ''));
        $referenciaId = (int)($body['referencia_id'] ?? $body['reference_id'] ?? 0);
        gab_notif_marcar_lida($pdo, $usuarioId, $tipoNotificacao, $referenciaId);
        return ['ok' => true];
    }

    function api_data_mark_all_notifications(PDO $pdo, int $usuarioId): array
    {
        if (function_exists('gab_notif_marcar_todas')) {
            gab_notif_marcar_todas($pdo, $usuarioId);
        }
        return ['ok' => true];
    }


    function api_data_dashboard(PDO $pdo, int $usuarioId, array $current): array
    {
        $forumCount = 0;
        if (api_data_has($pdo, 'forum_duvidas_topicos')) {
            $forumCount += api_data_count($pdo, "SELECT COUNT(*) FROM forum_duvidas_topicos WHERE " . api_data_active_sql(''));
        }
        if (api_data_has($pdo, 'aulas_forum_topicos')) {
            $forumCount += api_data_count($pdo, "SELECT COUNT(*) FROM aulas_forum_topicos WHERE " . api_data_active_sql(''));
        }

        return [
            'user' => $current,
            'stats' => [
                'cursos' => count(api_data_cursos($pdo, $usuarioId)),
                'cronograma' => api_data_has($pdo, 'admin_cronograma_itens') ? api_data_count($pdo, "SELECT COUNT(*) FROM admin_cronograma_itens WHERE " . api_data_active_sql('')) : 0,
                'questoes' => api_data_has($pdo, 'admin_banco_questoes') ? api_data_count($pdo, "SELECT COUNT(*) FROM admin_banco_questoes WHERE " . api_data_active_sql('')) : 0,
                'simulados' => api_data_has($pdo, 'admin_simulados') ? api_data_count($pdo, "SELECT COUNT(*) FROM admin_simulados WHERE " . api_data_active_sql('')) : 0,
                'materiais' => api_data_has($pdo, 'admin_material_arquivos') ? api_data_count($pdo, "SELECT COUNT(*) FROM admin_material_arquivos WHERE " . api_data_active_sql('')) : 0,
                'forum' => $forumCount,
                'recompensas' => api_data_has($pdo, 'usuario_xp_eventos') ? api_data_count($pdo, 'SELECT COALESCE(SUM(xp), 0) FROM usuario_xp_eventos WHERE usuario_id = ?', [$usuarioId]) : 0,
            ],
        ];
    }


    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        $body = $requestBody;
        $acao = trim((string)($body['acao'] ?? ''));
        if (in_array($acao, ['marcar_assistido', 'avaliar_aula', 'criar_topico'], true)) {
            api_data_aula_action($pdo, $usuarioId, $body);
        }
        switch ($tipo) {
            case 'marcar_assistido':
            case 'avaliar_aula':
            case 'criar_topico':
                $body['acao'] = $tipo;
                api_data_aula_action($pdo, $usuarioId, $body);
                break;
            case 'cronograma_concluir':
                json_out(true, api_data_mark_cronograma($pdo, $usuarioId, $body));
                break;
            case 'questao_responder':
                json_out(true, api_data_answer_question($pdo, $usuarioId, $body));
                break;
            case 'questao_corte':
                json_out(true, api_data_cut_question_option($pdo, $usuarioId, $body));
                break;
            case 'questao_solucao':
                json_out(true, api_data_toggle_question_solution($pdo, $usuarioId, $body));
                break;
            case 'simulado_finalizar':
                json_out(true, api_data_finish_simulado($pdo, $usuarioId, $body));
                break;
            case 'forum_criar':
                json_out(true, api_data_create_forum_topic($pdo, $usuarioId, $current, $body));
                break;
            case 'forum_responder':
                json_out(true, api_data_reply_forum_topic($pdo, $usuarioId, $current, $body));
                break;
            case 'notificacao_lida':
                json_out(true, api_data_mark_notification($pdo, $usuarioId, $body));
                break;
            case 'notificacoes_lidas':
                json_out(true, api_data_mark_all_notifications($pdo, $usuarioId));
                break;
            default:
                json_out(false, ['message' => 'Ação inválida: ' . $tipo], 422);
        }
    }

    if ($tipo === 'dashboard') {
        json_out(true, api_data_dashboard($pdo, $usuarioId, $current));
    }

    $items = [];
    $table = null;

    switch ($tipo) {
        case 'curso_detalhe':
            json_out(true, api_data_curso_detalhe($pdo, $usuarioId, (int)($_GET['curso_id'] ?? 0)));
            break;

        case 'aula_detalhe':
            json_out(true, api_data_aula_detalhe($pdo, $usuarioId, (int)($_GET['curso_id'] ?? 0), (int)($_GET['modulo_id'] ?? 0), (int)($_GET['aula_id'] ?? 0)));
            break;

        case 'questao_detalhe':
            json_out(true, api_data_question_detail($pdo, $usuarioId, (int)($_GET['questao_id'] ?? $_GET['id'] ?? 0)));
            break;

        case 'simulado_detalhe':
            json_out(true, api_data_simulado_detail($pdo, (int)($_GET['simulado_id'] ?? $_GET['id'] ?? 0)));
            break;

        case 'forum_topico':
            json_out(true, api_data_forum_topic($pdo, (int)($_GET['id'] ?? $_GET['topico_id'] ?? 0), (string)($_GET['origem'] ?? 'forum')));
            break;

        case 'notificacoes':
            $items = api_data_notificacoes($pdo, $usuarioId);
            $totalNaoLidas = 0;
            foreach ($items as $item) {
                if (is_array($item) && empty($item['lida'])) {
                    $totalNaoLidas++;
                }
            }
            json_out(true, [
                'items' => array_values($items),
                'total_nao_lidas' => $totalNaoLidas,
            ]);
            break;

        case 'cursos':
            $items = api_data_cursos($pdo, $usuarioId);
            $table = 'admin_cursos';
            break;

        case 'curso_modulos':
            $cursoIdFiltro = (int)($_GET['curso_id'] ?? 0);
            if ($cursoIdFiltro > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoIdFiltro)) {
                $items = [];
            } else {
                $items = api_data_curso_modulos($pdo, $cursoIdFiltro);
            }
            $table = 'admin_curso_modulos';
            break;

        case 'modulo_aulas':
            $items = api_data_modulo_aulas($pdo, (int)($_GET['modulo_id'] ?? 0));
            $table = 'admin_curso_aulas';
            break;

        case 'cronograma':
            $items = api_data_cronograma($pdo, $usuarioId);
            $cursoFiltro = (int)($_GET['curso_id'] ?? 0);
            if ($cursoFiltro > 0) {
                $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['curso_id'] ?? 0) === $cursoFiltro));
            }
            $table = 'admin_cronograma_itens';
            break;

        case 'banco_questoes':
            $items = api_data_banco_questoes($pdo, $usuarioId);
            $cursoFiltro = (int)($_GET['curso_id'] ?? 0);
            if ($cursoFiltro > 0) {
                $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['curso_id'] ?? 0) === $cursoFiltro));
            }
            $table = 'admin_banco_questoes';
            break;

        case 'simulados':
            $items = api_data_simulados($pdo, $usuarioId);
            $cursoFiltro = (int)($_GET['curso_id'] ?? 0);
            if ($cursoFiltro > 0) {
                $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['curso_id'] ?? 0) === $cursoFiltro));
            }
            $table = 'admin_simulados';
            break;

        case 'materiais':
            json_out(true, api_data_materiais($pdo, $usuarioId, $_GET));
            break;

        case 'forum_duvidas':
            $items = api_data_forum_duvidas($pdo);
            $table = 'forum_duvidas_topicos/aulas_forum_topicos';
            break;

        case 'recompensas':
            $items = api_data_recompensas($pdo, $usuarioId);
            $table = 'admin_recompensas_regras/usuario_xp_eventos';
            break;

        default:
            json_out(false, [
                'message' => 'Tipo de lista inválido: ' . $tipo,
                'tipos_validos' => ['cursos', 'curso_detalhe', 'curso_modulos', 'modulo_aulas', 'aula_detalhe', 'cronograma', 'banco_questoes', 'questao_detalhe', 'questao_corte', 'questao_solucao', 'simulados', 'simulado_detalhe', 'materiais', 'forum_duvidas', 'forum_topico', 'recompensas', 'notificacoes'],
            ], 422);
    }

    $payload = [
        'items' => array_values($items),
        'table' => $table,
        'tipo' => $tipo,
    ] + api_data_route_debug($pdo, $tipo, $items, $table);

    json_out(true, $payload);
} catch (Throwable $e) {
    error_log('[API Mobile] Erro no data.php: ' . $e->getMessage());
    $payload = ['message' => 'Erro ao carregar dados do app.'];
    if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
        $payload['debug'] = $e->getMessage();
    }
    json_out(false, $payload, 500);
}
