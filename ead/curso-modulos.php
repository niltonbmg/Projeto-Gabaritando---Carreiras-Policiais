<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
$barraSuperiorInclude = __DIR__ . '/includes/barra-superior.php';
if (!is_file($barraSuperiorInclude)) {
    $barraSuperiorInclude = __DIR__ . '/barra-superior.php';
}

$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}

if (!function_exists('curso_modulos_esc')) {
    function curso_modulos_esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('curso_modulos_env')) {
    function curso_modulos_env(string $path): array
    {
        $env = [];
        if (!is_file($path)) {
            return $env;
        }

        $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$linhas) {
            return $env;
        }

        foreach ($linhas as $linha) {
            $linha = trim((string)$linha);
            if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);
            $env[trim($chave)] = trim($valor, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }
}

if (!function_exists('curso_modulos_db')) {
    function curso_modulos_db(): PDO
    {
        $conexaoInclude = __DIR__ . '/api/conexao.php';
        if (is_file($conexaoInclude)) {
            require_once $conexaoInclude;
        }

        if (function_exists('db')) {
            return db();
        }

        $env = curso_modulos_env(__DIR__ . '/.env');
        $host = $env['DB_HOST'] ?? $env['MYSQL_HOST'] ?? 'localhost';
        $porta = $env['DB_PORT'] ?? $env['MYSQL_PORT'] ?? '3306';
        $banco = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? $env['MYSQL_DATABASE'] ?? '';
        $usuario = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? $env['MYSQL_USER'] ?? '';
        $senha = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? $env['MYSQL_PASSWORD'] ?? '';

        if ($banco === '' || $usuario === '') {
            throw new RuntimeException('Conexão com o banco não encontrada. Confira api/conexao.php ou .env.');
        }

        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";
        return new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

if (!function_exists('curso_modulos_formata_horas')) {
    function curso_modulos_formata_horas($valor): string
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

if (!function_exists('curso_modulos_parse_duracao')) {
    function curso_modulos_parse_duracao($valor): float
    {
        $texto = trim(mb_strtolower((string)$valor, 'UTF-8'));
        if ($texto === '') {
            return 0.0;
        }

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
}

if (!function_exists('curso_modulos_formata_tempo_modulo')) {
    function curso_modulos_formata_tempo_modulo(float $horas, int $totalAulas): string
    {
        if ($horas > 0) {
            return curso_modulos_formata_horas($horas);
        }

        if ($totalAulas > 0) {
            return $totalAulas . ($totalAulas === 1 ? ' aula' : ' aulas');
        }

        return '0h';
    }
}


if (!function_exists('curso_modulos_formata_minutos')) {
    function curso_modulos_formata_minutos(int $minutos): string
    {
        if ($minutos <= 0) {
            return '0min';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        if ($horas > 0 && $resto > 0) {
            return $horas . 'h ' . $resto . 'min';
        }

        if ($horas > 0) {
            return $horas . 'h';
        }

        return $minutos . 'min';
    }
}

if (!function_exists('curso_modulos_usuario_id')) {
    function curso_modulos_usuario_id(): int
    {
        if (function_exists('auth_user_id')) {
            $id = (int)auth_user_id();
            if ($id > 0) {
                return $id;
            }
        }

        if (isset($_SESSION['usuario']['id'])) {
            return (int)$_SESSION['usuario']['id'];
        }

        foreach (['usuario_id', 'id_usuario', 'user_id'] as $chave) {
            if (isset($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        return 0;
    }
}

if (!function_exists('curso_modulos_table_exists')) {
    function curso_modulos_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $cache[$table] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

if (!function_exists('curso_modulos_columns')) {
    function curso_modulos_columns(PDO $pdo, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $safeTable = str_replace('`', '``', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`");
            $cache[$table] = array_map(static fn(array $row): string => (string)$row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }
}

if (!function_exists('curso_modulos_ensure_metas_tables')) {
    function curso_modulos_ensure_metas_tables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cronograma_itens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cronograma_id INT UNSIGNED NULL,
            curso_id INT UNSIGNED NULL,
            materia_id INT UNSIGNED NULL,
            topico_id INT UNSIGNED NULL,
            modulo_id INT UNSIGNED NULL,
            aula_id INT UNSIGNED NULL,
            titulo VARCHAR(190) NOT NULL,
            materia VARCHAR(190) NULL,
            descricao TEXT NULL,
            data_estudo DATE NOT NULL,
            hora_inicio TIME NULL,
            duracao_minutos INT UNSIGNED NOT NULL DEFAULT 60,
            video_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            pdf_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            questoes_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            cor VARCHAR(30) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cronograma_data (data_estudo),
            KEY idx_cronograma_curso (curso_id),
            KEY idx_cronograma_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $colunasCronograma = [
            'cronograma_id' => 'INT UNSIGNED NULL AFTER id',
            'curso_id' => 'INT UNSIGNED NULL AFTER cronograma_id',
            'materia_id' => 'INT UNSIGNED NULL AFTER curso_id',
            'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
            'modulo_id' => 'INT UNSIGNED NULL AFTER topico_id',
            'aula_id' => 'INT UNSIGNED NULL AFTER modulo_id',
            'materia' => 'VARCHAR(190) NULL AFTER titulo',
            'descricao' => 'TEXT NULL AFTER materia',
            'hora_inicio' => 'TIME NULL AFTER data_estudo',
            'duracao_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 60 AFTER hora_inicio',
            'video_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER duracao_minutos',
            'pdf_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos',
            'questoes_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos',
            'ordem' => 'INT NOT NULL DEFAULT 0 AFTER questoes_minutos',
            'cor' => 'VARCHAR(30) NULL AFTER ordem',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'ativo' AFTER cor",
            'criado_em' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status',
        ];
        foreach ($colunasCronograma as $coluna => $definicao) {
            if (!in_array($coluna, curso_modulos_columns($pdo, 'admin_cronograma_itens'), true)) {
                try { $pdo->exec("ALTER TABLE admin_cronograma_itens ADD COLUMN {$coluna} {$definicao}"); } catch (Throwable $e) { }
            }
        }
        try { $pdo->exec('ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_admin (cronograma_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_materia (materia_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_topico (topico_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_ordem (ordem)'); } catch (Throwable $e) { }

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_materias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_materias_status (status)
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
            KEY idx_admin_materia_topicos_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'admin_curso_modulos' => [
                'materia_id' => 'INT UNSIGNED NULL AFTER capa',
                'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
            ],
            'admin_curso_aulas' => [
                'materia_id' => 'INT UNSIGNED NULL AFTER modulo_id',
                'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
                'pdf_duracao' => 'VARCHAR(40) NULL AFTER duracao',
            ],
        ] as $tabela => $colunasExtras) {
            if (curso_modulos_table_exists($pdo, $tabela)) {
                $colunasAtuais = curso_modulos_columns($pdo, $tabela);
                foreach ($colunasExtras as $colunaExtra => $definicaoExtra) {
                    if (!in_array($colunaExtra, $colunasAtuais, true)) {
                        try { $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$colunaExtra} {$definicaoExtra}"); } catch (Throwable $e) { }
                    }
                }
            }
        }
        try { $pdo->exec('ALTER TABLE admin_curso_modulos ADD INDEX idx_modulos_materia_topico (materia_id, topico_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_curso_aulas ADD INDEX idx_aulas_materia_topico (materia_id, topico_id)'); } catch (Throwable $e) { }

        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cronograma_conclusoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id INT UNSIGNED NOT NULL,
            cronograma_id INT UNSIGNED NOT NULL,
            concluido TINYINT(1) NOT NULL DEFAULT 1,
            concluido_em DATETIME NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_usuario_cronograma (usuario_id, cronograma_id),
            KEY idx_usuario_cronograma_usuario (usuario_id),
            KEY idx_usuario_cronograma_item (cronograma_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_metas_diarias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(190) NOT NULL,
            materia VARCHAR(190) NULL,
            descricao TEXT NULL,
            curso_id INT UNSIGNED NULL,
            modulo_id INT UNSIGNED NULL,
            aula_id INT UNSIGNED NULL,
            usuario_id INT UNSIGNED NULL,
            tempo_minutos INT UNSIGNED NOT NULL DEFAULT 60,
            video_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            pdf_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            questoes_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            data_meta DATE NULL,
            video_link VARCHAR(255) NULL,
            banco_questoes_link VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_metas_data (data_meta),
            KEY idx_admin_metas_curso (curso_id),
            KEY idx_admin_metas_usuario (usuario_id),
            KEY idx_admin_metas_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_metas_diarias_progresso (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            meta_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            data_referencia DATE NOT NULL,
            minutos_concluidos INT UNSIGNED NOT NULL DEFAULT 0,
            concluida TINYINT(1) NOT NULL DEFAULT 0,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_meta_usuario_dia (meta_id, usuario_id, data_referencia),
            KEY idx_meta_usuario (usuario_id, data_referencia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $colunasMetas = [
            'cronograma_item_id' => 'INT UNSIGNED NULL AFTER id',
            'materia' => 'VARCHAR(190) NULL AFTER titulo',
            'materia_id' => 'INT UNSIGNED NULL AFTER materia',
            'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
            'descricao' => 'TEXT NULL AFTER topico_id',
            'curso_id' => 'INT UNSIGNED NULL AFTER descricao',
            'modulo_id' => 'INT UNSIGNED NULL AFTER curso_id',
            'aula_id' => 'INT UNSIGNED NULL AFTER modulo_id',
            'usuario_id' => 'INT UNSIGNED NULL AFTER aula_id',
            'tempo_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 60 AFTER usuario_id',
            'video_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER tempo_minutos',
            'pdf_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos',
            'questoes_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos',
            'data_meta' => 'DATE NULL AFTER questoes_minutos',
            'video_link' => 'VARCHAR(255) NULL AFTER data_meta',
            'banco_questoes_link' => 'VARCHAR(255) NULL AFTER video_link',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'ativo' AFTER banco_questoes_link",
            'criado_em' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status',
        ];
        foreach ($colunasMetas as $coluna => $definicao) {
            if (!in_array($coluna, curso_modulos_columns($pdo, 'admin_metas_diarias'), true)) {
                try { $pdo->exec("ALTER TABLE admin_metas_diarias ADD COLUMN {$coluna} {$definicao}"); } catch (Throwable $e) { }
            }
        }

        try { $pdo->exec('ALTER TABLE admin_metas_diarias ADD INDEX idx_admin_metas_topico (topico_id)'); } catch (Throwable $e) { }
        try { $pdo->exec('ALTER TABLE admin_metas_diarias ADD INDEX idx_admin_metas_item (cronograma_item_id)'); } catch (Throwable $e) { }
    }
}


if (!function_exists('curso_modulos_minutos_assistidos_no_dia')) {
    function curso_modulos_minutos_assistidos_no_dia(PDO $pdo, int $usuarioId, int $cursoId, string $data): int
    {
        if ($usuarioId <= 0 || $cursoId <= 0 || !curso_modulos_table_exists($pdo, 'aulas_progresso')) {
            return 0;
        }

        try {
            $sql = "
                SELECT a.duracao
                FROM aulas_progresso p
                LEFT JOIN admin_curso_aulas a ON a.id = p.aula_id
                WHERE p.usuario_id = :usuario_id
                  AND p.curso_id = :curso_id
                  AND p.assistido = 1
                  AND DATE(COALESCE(p.assistido_em, p.criado_em)) = :data_ref
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':curso_id' => $cursoId,
                ':data_ref' => $data,
            ]);

            $minutos = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $aula) {
                $horas = curso_modulos_parse_duracao((string)($aula['duracao'] ?? ''));
                if ($horas > 0) {
                    $minutos += (int)round($horas * 60);
                } else {
                    $minutos += 60;
                }
            }

            return max(0, $minutos);
        } catch (Throwable $e) {
            return 0;
        }
    }
}


if (!function_exists('curso_modulos_conteudo_stats_meta')) {
    function curso_modulos_conteudo_stats_meta(PDO $pdo, int $cursoId, array $meta): array
    {
        $materiaId = (int)($meta['materia_id'] ?? 0);
        $topicoId = (int)($meta['topico_id'] ?? 0);
        $moduloId = (int)($meta['modulo_id'] ?? 0);
        $aulaId = (int)($meta['aula_id'] ?? 0);

        $where = ['a.curso_id = :curso_id'];
        $params = [':curso_id' => $cursoId];

        if ($aulaId > 0) {
            $where[] = 'a.id = :aula_id';
            $params[':aula_id'] = $aulaId;
        } elseif ($moduloId > 0) {
            $where[] = 'a.modulo_id = :modulo_id';
            $params[':modulo_id'] = $moduloId;
        } else {
            if ($materiaId > 0) {
                $where[] = 'COALESCE(a.materia_id, m.materia_id) = :materia_id';
                $params[':materia_id'] = $materiaId;
            }
            if ($topicoId > 0) {
                $where[] = 'COALESCE(a.topico_id, m.topico_id) = :topico_id';
                $params[':topico_id'] = $topicoId;
            }
        }

        $stats = [
            'video_minutos' => 0,
            'pdf_minutos' => 0,
            'questoes_minutos' => 0,
            'pdf_total' => 0,
            'aulas_total' => 0,
            'primeira_aula_id' => 0,
            'primeiro_modulo_id' => 0,
        ];

        try {
            $sql = "
                SELECT a.id, a.modulo_id, a.duracao, a.pdf_duracao, a.video_url, a.pdf_arquivo
                FROM admin_curso_aulas a
                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $aula) {
                $stats['aulas_total']++;
                if ($stats['primeira_aula_id'] <= 0) {
                    $stats['primeira_aula_id'] = (int)$aula['id'];
                    $stats['primeiro_modulo_id'] = (int)$aula['modulo_id'];
                }

                if (trim((string)($aula['video_url'] ?? '')) !== '') {
                    $min = (int)round(curso_modulos_parse_duracao((string)($aula['duracao'] ?? '')) * 60);
                    $stats['video_minutos'] += max(0, $min);
                }

                if (trim((string)($aula['pdf_arquivo'] ?? '')) !== '') {
                    $stats['pdf_total']++;
                    $pdfMin = (int)round(curso_modulos_parse_duracao((string)($aula['pdf_duracao'] ?? '')) * 60);
                    $stats['pdf_minutos'] += max(0, $pdfMin);
                }
            }
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Erro ao calcular tempos da meta: ' . $e->getMessage());
        }

        return $stats;
    }
}

if (!function_exists('curso_modulos_minutos_assistidos_meta')) {
    function curso_modulos_minutos_assistidos_meta(PDO $pdo, int $usuarioId, int $cursoId, array $meta, string $data): int
    {
        if ($usuarioId <= 0 || $cursoId <= 0 || !curso_modulos_table_exists($pdo, 'aulas_progresso')) {
            return 0;
        }

        $materiaId = (int)($meta['materia_id'] ?? 0);
        $topicoId = (int)($meta['topico_id'] ?? 0);
        $moduloId = (int)($meta['modulo_id'] ?? 0);
        $aulaId = (int)($meta['aula_id'] ?? 0);

        $where = [
            'p.usuario_id = :usuario_id',
            'p.curso_id = :curso_id',
            'p.assistido = 1',
            'DATE(COALESCE(p.assistido_em, p.criado_em)) = :data_ref',
        ];
        $params = [
            ':usuario_id' => $usuarioId,
            ':curso_id' => $cursoId,
            ':data_ref' => $data,
        ];

        if ($aulaId > 0) {
            $where[] = 'p.aula_id = :aula_id';
            $params[':aula_id'] = $aulaId;
        } elseif ($moduloId > 0) {
            $where[] = 'a.modulo_id = :modulo_id';
            $params[':modulo_id'] = $moduloId;
        } else {
            if ($materiaId > 0) {
                $where[] = 'COALESCE(a.materia_id, m.materia_id) = :materia_id';
                $params[':materia_id'] = $materiaId;
            }
            if ($topicoId > 0) {
                $where[] = 'COALESCE(a.topico_id, m.topico_id) = :topico_id';
                $params[':topico_id'] = $topicoId;
            }
        }

        try {
            $sql = "
                SELECT DISTINCT a.id, a.duracao
                FROM aulas_progresso p
                INNER JOIN admin_curso_aulas a ON a.id = p.aula_id
                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                WHERE " . implode(' AND ', $where) . "
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $minutos = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $aula) {
                $duracao = (int)round(curso_modulos_parse_duracao((string)($aula['duracao'] ?? '')) * 60);
                $minutos += $duracao > 0 ? $duracao : 60;
            }

            return max(0, $minutos);
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Erro ao calcular progresso da meta por tópico: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('curso_modulos_link_aula_para_meta')) {
    function curso_modulos_link_aula_para_meta(PDO $pdo, int $cursoId, array $meta): string
    {
        $moduloId = (int)($meta['modulo_id'] ?? 0);
        $aulaId = (int)($meta['aula_id'] ?? 0);
        if ($cursoId > 0 && $moduloId > 0 && $aulaId > 0) {
            return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . '&aula_id=' . $aulaId;
        }

        $stats = curso_modulos_conteudo_stats_meta($pdo, $cursoId, $meta);
        if ((int)$stats['primeira_aula_id'] > 0 && (int)$stats['primeiro_modulo_id'] > 0) {
            return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$stats['primeiro_modulo_id'] . '&aula_id=' . (int)$stats['primeira_aula_id'];
        }

        $termos = [];
        $topicoId = (int)($meta['topico_id'] ?? 0);
        $titulo = trim((string)($meta['titulo'] ?? ''));

        if ($topicoId > 0 && curso_modulos_table_exists($pdo, 'admin_materia_topicos')) {
            try {
                $stmtTopico = $pdo->prepare('SELECT titulo FROM admin_materia_topicos WHERE id = ? LIMIT 1');
                $stmtTopico->execute([$topicoId]);
                $tituloTopico = trim((string)$stmtTopico->fetchColumn());
                if ($tituloTopico !== '') {
                    $termos[] = $tituloTopico;
                }
            } catch (Throwable $e) {
                // Se não conseguir ler o tópico, continua tentando pelo título da meta.
            }
        }

        if ($titulo !== '') {
            $termos[] = $titulo;
        }

        try {
            foreach (array_unique($termos) as $termo) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termo) . '%';
                $stmt = $pdo->prepare("\n                    SELECT a.id AS aula_id, a.modulo_id\n                    FROM admin_curso_aulas a\n                    LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                    WHERE a.curso_id = ?\n                      AND (a.titulo LIKE ? OR a.descricao LIKE ? OR m.titulo LIKE ? OR m.descricao LIKE ?)\n                    ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                    LIMIT 1\n                ");
                $stmt->execute([$cursoId, $like, $like, $like, $like]);
                $aula = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($aula && (int)$aula['aula_id'] > 0) {
                    return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'];
                }
            }

            $stmt = $pdo->prepare("\n                SELECT a.id AS aula_id, a.modulo_id\n                FROM admin_curso_aulas a\n                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                WHERE a.curso_id = ?\n                ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                LIMIT 1\n            ");
            $stmt->execute([$cursoId]);
            $aula = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aula && (int)$aula['aula_id'] > 0) {
                return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'];
            }
        } catch (Throwable $e) {
            // Não quebra a página se não conseguir montar o link do vídeo.
        }

        return 'curso-modulos.php?curso_id=' . $cursoId;
    }
}

if (!function_exists('curso_modulos_data_br_curta')) {
    function curso_modulos_data_br_curta(string $data): string
    {
        $data = trim($data);
        if ($data === '') {
            return '';
        }

        try {
            $dt = new DateTime($data);
            return $dt->format('d/m/Y');
        } catch (Throwable $e) {
            return $data;
        }
    }
}

if (!function_exists('curso_modulos_aula_assistida_meta')) {
    function curso_modulos_aula_assistida_meta(PDO $pdo, int $usuarioId, int $aulaId, string $data): bool
    {
        if ($usuarioId <= 0 || $aulaId <= 0 || !curso_modulos_table_exists($pdo, 'aulas_progresso')) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM aulas_progresso\n                WHERE usuario_id = ?\n                  AND aula_id = ?\n                  AND assistido = 1\n                  AND DATE(COALESCE(assistido_em, criado_em)) = ?\n            ");
            $stmt->execute([$usuarioId, $aulaId, $data]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('curso_modulos_montar_meta_card')) {
    function curso_modulos_montar_meta_card(PDO $pdo, int $usuarioId, int $cursoId, array $row, string $origem = 'cronograma'): array
    {
        $videoConfigurado = max(0, (int)($row['video_minutos'] ?? 0));
        $pdfConfigurado = max(0, (int)($row['pdf_minutos'] ?? 0));
        $questoesConfigurado = max(0, (int)($row['questoes_minutos'] ?? 0));
        $tempoMeta = max(1, (int)($row['duracao_minutos'] ?? $row['tempo_minutos'] ?? 60));
        if (($videoConfigurado + $pdfConfigurado + $questoesConfigurado) > 0) {
            $tempoMeta = max(1, $videoConfigurado + $pdfConfigurado + $questoesConfigurado);
        }
        $dataReferencia = (string)($row['data_estudo'] ?? $row['data_meta'] ?? date('Y-m-d'));
        if ($dataReferencia === '') {
            $dataReferencia = date('Y-m-d');
        }

        $concluido = ((int)($row['concluido'] ?? 0) === 1) || ((int)($row['meta_concluida'] ?? 0) === 1);
        $minutosFeitos = max(0, (int)($row['minutos_concluidos'] ?? 0));
        $conteudoStats = curso_modulos_conteudo_stats_meta($pdo, $cursoId, $row);
        $videoTempo = $videoConfigurado > 0 ? $videoConfigurado : (int)$conteudoStats['video_minutos'];
        $pdfTempo = $pdfConfigurado > 0 ? $pdfConfigurado : (int)$conteudoStats['pdf_minutos'];
        $questoesTempo = $questoesConfigurado > 0 ? $questoesConfigurado : (int)$conteudoStats['questoes_minutos'];
        $minutosFeitos = max($minutosFeitos, curso_modulos_minutos_assistidos_meta($pdo, $usuarioId, $cursoId, $row, $dataReferencia));

        $aulaId = (int)($row['aula_id'] ?? 0);
        if (!$concluido && $aulaId > 0 && curso_modulos_aula_assistida_meta($pdo, $usuarioId, $aulaId, $dataReferencia)) {
            $minutosFeitos = max($minutosFeitos, $tempoMeta);
            $concluido = true;
        }

        if ($concluido) {
            $minutosFeitos = max($minutosFeitos, $tempoMeta);
        }

        $percentual = min(100, (int)round(($minutosFeitos / $tempoMeta) * 100));

        $titulo = trim((string)($row['titulo'] ?? $row['topico_nome'] ?? 'Meta diária'));
        $materia = trim((string)($row['materia'] ?? $row['materia_nome'] ?? ''));
        $descricao = trim((string)($row['descricao'] ?? ''));

        $videoLink = trim((string)($row['video_link'] ?? ''));
        if ($videoLink === '' || str_contains($videoLink, 'curso-modulos.php')) {
            $videoLink = curso_modulos_link_aula_para_meta($pdo, $cursoId, $row);
        }

        $bancoLink = trim((string)($row['banco_questoes_link'] ?? ''));
        if ($bancoLink === '') {
            $materiaId = (int)($row['materia_id'] ?? 0);
            $topicoId = (int)($row['topico_id'] ?? 0);
            $bancoLink = 'banco-questoes.php?curso_id=' . $cursoId;
            if ($materiaId > 0) {
                $bancoLink .= '&materia_id=' . $materiaId;
            }
            if ($topicoId > 0) {
                $bancoLink .= '&topico_id=' . $topicoId;
            }
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'titulo' => $titulo !== '' ? $titulo : 'Meta diária',
            'materia' => $materia,
            'descricao' => $descricao,
            'data_meta' => $dataReferencia,
            'data_meta_label' => curso_modulos_data_br_curta($dataReferencia),
            'tempo' => curso_modulos_formata_minutos($tempoMeta),
            'feito' => curso_modulos_formata_minutos($minutosFeitos),
            'video_tempo' => curso_modulos_formata_minutos($videoTempo),
            'pdf_tempo' => curso_modulos_formata_minutos($pdfTempo),
            'questoes_tempo' => curso_modulos_formata_minutos($questoesTempo),
            'pdf_total' => (int)$conteudoStats['pdf_total'],
            'aulas_total' => (int)$conteudoStats['aulas_total'],
            'percentual' => $percentual,
            'video_link' => $videoLink,
            'banco_link' => $bancoLink,
            'concluida' => $concluido || $percentual >= 100,
            'origem' => $origem,
        ];
    }
}

if (!function_exists('curso_modulos_carregar_metas')) {
    function curso_modulos_carregar_metas(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        curso_modulos_ensure_metas_tables($pdo);

        $hoje = date('Y-m-d');
        $metas = [];

        // A meta diária vem do cronograma do curso. Em vez de prender em uma única data,
        // carrega as próximas metas a partir de hoje; assim, se o admin colocar 27 e 28,
        // as duas aparecem na área de metas e não some a do dia seguinte.
        if ($cursoId > 0 && curso_modulos_table_exists($pdo, 'admin_cronograma_itens')) {
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        i.*,
                        ma.nome AS materia_nome,
                        t.titulo AS topico_nome,
                        COALESCE(u.concluido, 0) AS concluido,
                        m.id AS meta_id,
                        m.video_link,
                        m.banco_questoes_link,
                        COALESCE(p.minutos_concluidos, 0) AS minutos_concluidos,
                        COALESCE(p.concluida, 0) AS meta_concluida
                    FROM admin_cronograma_itens i
                    LEFT JOIN admin_materias ma ON ma.id = i.materia_id
                    LEFT JOIN admin_materia_topicos t ON t.id = i.topico_id
                    LEFT JOIN usuario_cronograma_conclusoes u
                        ON u.cronograma_id = i.id
                       AND u.usuario_id = ?
                    LEFT JOIN admin_metas_diarias m
                        ON m.cronograma_item_id = i.id
                       AND m.status = 'ativo'
                    LEFT JOIN usuario_metas_diarias_progresso p
                        ON p.meta_id = m.id
                       AND p.usuario_id = ?
                       AND p.data_referencia = i.data_estudo
                    WHERE i.status = 'ativo'
                      AND i.curso_id = ?
                      AND i.data_estudo >= ?
                    ORDER BY i.data_estudo ASC, i.ordem ASC, i.hora_inicio ASC, i.id ASC
                    LIMIT 20
                ");
                $stmt->execute([$usuarioId, $usuarioId, $cursoId, $hoje]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $metas[] = curso_modulos_montar_meta_card($pdo, $usuarioId, $cursoId, $row, 'cronograma');
                }

                if (!$metas) {
                    $stmt = $pdo->prepare("
                        SELECT
                            i.*,
                            ma.nome AS materia_nome,
                            t.titulo AS topico_nome,
                            COALESCE(u.concluido, 0) AS concluido,
                            m.id AS meta_id,
                            m.video_link,
                            m.banco_questoes_link,
                            COALESCE(p.minutos_concluidos, 0) AS minutos_concluidos,
                            COALESCE(p.concluida, 0) AS meta_concluida
                        FROM admin_cronograma_itens i
                        LEFT JOIN admin_materias ma ON ma.id = i.materia_id
                        LEFT JOIN admin_materia_topicos t ON t.id = i.topico_id
                        LEFT JOIN usuario_cronograma_conclusoes u
                            ON u.cronograma_id = i.id
                           AND u.usuario_id = ?
                        LEFT JOIN admin_metas_diarias m
                            ON m.cronograma_item_id = i.id
                           AND m.status = 'ativo'
                        LEFT JOIN usuario_metas_diarias_progresso p
                            ON p.meta_id = m.id
                           AND p.usuario_id = ?
                           AND p.data_referencia = i.data_estudo
                        WHERE i.status = 'ativo'
                          AND i.curso_id = ?
                        ORDER BY i.data_estudo DESC, i.ordem ASC, i.hora_inicio ASC, i.id ASC
                        LIMIT 12
                    ");
                    $stmt->execute([$usuarioId, $usuarioId, $cursoId]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $metas[] = curso_modulos_montar_meta_card($pdo, $usuarioId, $cursoId, $row, 'cronograma');
                    }
                }
            } catch (Throwable $e) {
                error_log('[Projeto Gabaritando] Erro ao carregar metas pelo cronograma: ' . $e->getMessage());
            }
        }

        if ($metas) {
            return $metas;
        }

        // Fallback: caso exista meta antiga gravada diretamente em admin_metas_diarias.
        try {
            $stmt = $pdo->prepare("\n                SELECT\n                    m.*,\n                    COALESCE(p.minutos_concluidos, 0) AS minutos_concluidos,\n                    COALESCE(p.concluida, 0) AS meta_concluida\n                FROM admin_metas_diarias m\n                LEFT JOIN usuario_metas_diarias_progresso p\n                    ON p.meta_id = m.id\n                   AND p.usuario_id = ?\n                   AND p.data_referencia = COALESCE(m.data_meta, ?)\n                WHERE m.status = 'ativo'\n                  AND (m.usuario_id IS NULL OR m.usuario_id = 0 OR m.usuario_id = ?)\n                  AND (m.curso_id IS NULL OR m.curso_id = 0 OR m.curso_id = ?)\n                ORDER BY\n                    CASE\n                        WHEN COALESCE(m.data_meta, ?) = ? THEN 0\n                        WHEN COALESCE(m.data_meta, ?) > ? THEN 1\n                        ELSE 2\n                    END ASC,\n                    ABS(DATEDIFF(COALESCE(m.data_meta, ?), ?)) ASC,\n                    COALESCE(m.data_meta, ?) ASC,\n                    m.id ASC\n                LIMIT 8\n            ");
            $stmt->execute([$usuarioId, $hoje, $usuarioId, $cursoId, $hoje, $hoje, $hoje, $hoje, $hoje, $hoje, $hoje]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $metas[] = curso_modulos_montar_meta_card($pdo, $usuarioId, $cursoId, $row, 'meta');
            }
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Erro ao carregar metas antigas: ' . $e->getMessage());
        }

        return $metas;
    }
}

$cursoId = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$curso = null;
$modulos = [];
$metasCurso = [];
$erroMetasCurso = '';
$erroPagina = '';
$usuarioId = curso_modulos_usuario_id();

try {
    if ($cursoId <= 0) {
        throw new RuntimeException('Curso não informado.');
    }

    $pdo = curso_modulos_db();

    if (function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoId)) {
        throw new RuntimeException('Este curso não está liberado para o seu usuário.');
    }

    $cursoColumns = curso_modulos_columns($pdo, 'admin_cursos');
    $cursoSelect = ['id', 'nome', 'descricao', 'capa', 'horas_curso', 'horas_video', 'horas_pdf'];
    foreach (['capa_mobile', 'capa_pagina', 'capa_pagina_mobile'] as $optionalColumn) {
        if (in_array($optionalColumn, $cursoColumns, true)) {
            $cursoSelect[] = $optionalColumn;
        }
    }

    $cursoSelectSql = implode(', ', array_map(static function (string $column): string {
        return '`' . str_replace('`', '``', $column) . '`';
    }, array_unique($cursoSelect)));

    $stmtCurso = $pdo->prepare("
        SELECT {$cursoSelectSql}
        FROM admin_cursos
        WHERE id = :id AND status = 'ativo'
        LIMIT 1
    ");
    $stmtCurso->execute([':id' => $cursoId]);
    $curso = $stmtCurso->fetch();

    if (!$curso) {
        throw new RuntimeException('Curso não encontrado ou indisponível.');
    }

    $stmtModulos = $pdo->prepare("\n        SELECT\n            m.id,\n            m.curso_id,\n            m.titulo,\n            m.descricao,\n            m.capa,\n            m.ordem,\n            COUNT(a.id) AS total_aulas,\n            GROUP_CONCAT(COALESCE(a.duracao, '') SEPARATOR '||') AS duracoes\n        FROM admin_curso_modulos m\n        LEFT JOIN admin_curso_aulas a ON a.modulo_id = m.id\n        WHERE m.curso_id = :curso_id\n        GROUP BY m.id, m.curso_id, m.titulo, m.descricao, m.capa, m.ordem\n        ORDER BY m.ordem ASC, m.id ASC\n    ");
    $stmtModulos->execute([':curso_id' => $cursoId]);
    $modulosBanco = $stmtModulos->fetchAll();

    foreach ($modulosBanco as $modulo) {
        $duracaoTotal = 0.0;
        $duracoes = explode('||', (string)($modulo['duracoes'] ?? ''));
        foreach ($duracoes as $duracao) {
            $duracaoTotal += curso_modulos_parse_duracao($duracao);
        }

        $capaModulo = trim((string)($modulo['capa'] ?? ''));
        if ($capaModulo === '') {
            $capaModulo = trim((string)($curso['capa'] ?? ''));
        }
        if ($capaModulo === '') {
            $capaModulo = 'img/banner.png';
        }

        $totalAulas = (int)($modulo['total_aulas'] ?? 0);
        $modulos[] = [
            'id' => (int)$modulo['id'],
            'titulo' => (string)$modulo['titulo'],
            'imagem' => $capaModulo,
            'tempo' => curso_modulos_formata_tempo_modulo($duracaoTotal, $totalAulas),
            'total_aulas' => $totalAulas,
            'link' => 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$modulo['id'],
        ];
    }

    try {
        $metasCurso = curso_modulos_carregar_metas($pdo, $usuarioId, $cursoId);
    } catch (Throwable $metaErro) {
        error_log('[Projeto Gabaritando] Erro ao carregar metas em curso-modulos.php: ' . $metaErro->getMessage());
        $erroMetasCurso = 'Não foi possível carregar as metas diárias agora.';
    }
} catch (Throwable $e) {
    $erroPagina = $e->getMessage();
}

$capaCursoDesktop = trim((string)($curso['capa_pagina'] ?? ''));
if ($capaCursoDesktop === '') {
    $capaCursoDesktop = trim((string)($curso['capa'] ?? ''));
}
if ($capaCursoDesktop === '') {
    $capaCursoDesktop = 'img/banner.png';
}

$capaCursoMobile = trim((string)($curso['capa_pagina_mobile'] ?? ''));
if ($capaCursoMobile === '') {
    $capaCursoMobile = trim((string)($curso['capa_mobile'] ?? ''));
}
if ($capaCursoMobile === '') {
    $capaCursoMobile = $capaCursoDesktop;
}

$horasCurso = curso_modulos_formata_horas($curso['horas_curso'] ?? 0);
$horasVideo = curso_modulos_formata_horas($curso['horas_video'] ?? 0);
$horasPdf = curso_modulos_formata_horas($curso['horas_pdf'] ?? 0);

$conteudosCurso = [
    [
        'titulo' => 'Simulados',
        'subtitulo' => 'Próximos simulados',
        'texto' => 'Acesse os simulados.',
        'botao' => 'Ver Simulados',
        'link' => 'simulados.php',
        'centralizado' => true,
    ],
    [
        'titulo' => 'Materiais',
        'subtitulo' => '',
        'texto' => 'Acesse os materiais do curso.',
        'botao' => 'Acessar Materiais',
        'link' => 'materiais.php?curso_id=' . (int)$cursoId,
        'centralizado' => false,
    ],
    [
        'titulo' => 'Cronograma',
        'subtitulo' => '',
        'texto' => 'Veja o cronograma de estudos deste curso.',
        'botao' => 'Acessar Cronograma',
        'link' => 'cronograma.php?curso_id=' . (int)$cursoId,
        'centralizado' => false,
    ],
    [
        'titulo' => 'Banco de Questões',
        'subtitulo' => '',
        'texto' => 'Resolva as questões publicadas.',
        'botao' => 'Acessar Banco de Questões',
        'link' => 'banco-questoes.php?curso_id=' . (int)$cursoId,
        'centralizado' => false,
    ],
    [
        'titulo' => 'Redações',
        'subtitulo' => '',
        'texto' => 'Envie redações para correção.',
        'botao' => 'Acessar Redações',
        'link' => 'redacoes.php',
        'centralizado' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $curso ? curso_modulos_esc($curso['nome']) . ' | ' : ''; ?>Módulos do Curso</title>
    <?php if (is_file(__DIR__ . '/favicon.ico')): ?>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#c98f0e;
            --bg:#121212;
            --surface:#181818;
            --surface-2:#202020;
            --line:rgba(255,255,255,.08);
            --text:#ffffff;
            --muted:#cfcfcf;
            --shadow:0 14px 35px rgba(0,0,0,.28);
        }

        *{margin:0;padding:0;box-sizing:border-box;}
        html,body{width:100%;min-height:100%;}
        body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--text);}
        img{max-width:100%;display:block;}
        a{text-decoration:none;color:inherit;}

        .curso-hero{
            width:100%;
            min-height:430px;
            position:relative;
            display:flex;
            align-items:flex-end;
            background:
                linear-gradient(to bottom, rgba(0,0,0,.10), rgba(0,0,0,.84)),
                url('<?php echo curso_modulos_esc($capaCursoDesktop); ?>') center center / cover no-repeat;
            overflow:hidden;
        }

        .curso-hero::after{
            content:"";
            position:absolute;
            inset:0;
            background:radial-gradient(circle at 24% 40%, rgba(250,181,40,.11), rgba(0,0,0,0) 44%);
            pointer-events:none;
        }

        .curso-hero__content{
            position:relative;
            z-index:1;
            width:100%;
            padding:34px 34px 42px;
        }

        .voltar-dashboard{
            display:inline-flex;
            align-items:center;
            gap:8px;
            min-height:38px;
            padding:8px 13px;
            border-radius:999px;
            background:rgba(0,0,0,.48);
            border:1px solid rgba(255,255,255,.10);
            color:#fff;
            font-size:.94rem;
            font-weight:700;
            box-shadow:var(--shadow);
            margin-bottom:18px;
        }

        .voltar-dashboard i{width:18px;height:18px;color:var(--yellow);}

        .curso-hero__title{
            max-width:880px;
            color:#fff;
            font-size:3rem;
            line-height:1.05;
            font-weight:700;
            text-transform:uppercase;
            text-shadow:0 4px 18px rgba(0,0,0,.48);
            margin-bottom:18px;
        }

        .curso-hero__badges{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .curso-badge{
            min-height:36px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            background:rgba(0,0,0,.58);
            border:1px solid rgba(250,181,40,.26);
            color:#fff;
            font-size:.95rem;
            font-weight:700;
            box-shadow:0 10px 22px rgba(0,0,0,.22);
            backdrop-filter:blur(8px);
        }

        .curso-badge i{width:17px;height:17px;color:var(--yellow);}

        .section{
            padding:28px 30px 44px;
            background:linear-gradient(180deg, #121212 0%, #151515 100%);
        }

        .section__header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            margin-bottom:20px;
        }

        .section__title{
            font-size:2rem;
            font-weight:500;
            color:var(--yellow);
        }

        .section__counter{
            color:var(--muted);
            font-size:.98rem;
            font-weight:700;
        }

        .modulos{
            display:grid;
            grid-template-columns:repeat(5, minmax(220px, 1fr));
            gap:20px;
            overflow-x:auto;
            padding-bottom:6px;
        }

        .modulo-card{
            background:linear-gradient(180deg, #171717 0%, #111111 100%);
            border:1px solid var(--line);
            border-radius:16px;
            overflow:hidden;
            transition:transform .2s ease, border-color .2s ease, box-shadow .2s ease;
            box-shadow:0 10px 24px rgba(0,0,0,.16);
            min-width:220px;
        }

        .modulo-card:hover{
            transform:translateY(-4px);
            border-color:rgba(250,181,40,.24);
            box-shadow:0 16px 30px rgba(0,0,0,.28);
        }

        .modulo-card__thumb{
            height:350px;
            position:relative;
            overflow:hidden;
            background:#0f0f0f;
        }

        .modulo-card__thumb img{
            width:100%;
            height:100%;
            object-fit:cover;
            transform:scale(1.02);
        }

        .modulo-card__thumb::after{
            content:"";
            position:absolute;
            inset:0;
            background:linear-gradient(to top, rgba(0,0,0,.82) 0%, rgba(0,0,0,.14) 52%, rgba(0,0,0,.04) 100%);
        }

        .modulo-card__tempo{
            position:absolute;
            top:14px;
            right:14px;
            z-index:2;
            min-height:34px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            padding:7px 12px;
            border-radius:999px;
            background:rgba(0,0,0,.70);
            border:1px solid rgba(250,181,40,.28);
            color:#fff;
            font-size:.92rem;
            font-weight:700;
            box-shadow:0 10px 22px rgba(0,0,0,.24);
            backdrop-filter:blur(8px);
        }

        .modulo-card__tempo i{
            width:16px;
            height:16px;
            color:var(--yellow);
            stroke-width:2.5;
            flex:0 0 auto;
        }

        .modulo-card__label{
            position:absolute;
            left:14px;
            right:14px;
            bottom:14px;
            z-index:1;
            color:#fff;
            font-size:1.55rem;
            line-height:1.05;
            text-transform:uppercase;
            font-weight:700;
            text-shadow:0 2px 14px rgba(0,0,0,.55);
        }

        .modulo-card__footer{
            padding:14px 14px 16px;
            border-top:1px solid rgba(255,255,255,.05);
        }

        .modulo-card__footer span{
            display:block;
            font-size:1rem;
            color:#fff;
            line-height:1.35;
            min-height:42px;
        }

        .modulo-card__bar{
            height:10px;
            width:100%;
            background:#8ef06d;
            border-radius:0 0 16px 16px;
        }

        .empty-state{
            width:100%;
            min-height:180px;
            border:1px solid var(--line);
            border-radius:16px;
            background:#181818;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:10px;
            text-align:center;
            padding:28px 18px;
            color:#fff;
        }

        .empty-state i{width:36px;height:36px;color:var(--yellow);}
        .empty-state strong{font-size:1.12rem;font-weight:700;}
        .empty-state span{color:var(--muted);font-size:.95rem;max-width:620px;line-height:1.45;}

        .conteudos-curso{
            width:100%;
            max-width:none;
            margin:42px 0 0;
        }

        .conteudos-curso__header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            margin:0 0 18px;
        }

        .conteudos-curso__title{
            font-size:2rem;
            font-weight:500;
            color:var(--yellow);
        }

        .conteudos-curso__grid{
            display:grid;
            gap:20px;
        }

        .conteudo-panel{
            min-height:142px;
            background:#242424;
            border:1px solid rgba(255,255,255,.04);
            border-radius:9px;
            padding:23px 24px;
            display:grid;
            grid-template-columns:minmax(0, 1fr) auto;
            gap:28px;
            align-items:center;
            color:#fff;
        }

        .conteudo-panel__content{min-width:0;}

        .conteudo-panel__title{
            font-size:1.55rem;
            line-height:1.15;
            font-weight:700;
            margin:0 0 20px;
            color:#fff;
        }

        .conteudo-panel__subtitle{
            font-size:1rem;
            font-weight:700;
            margin:-4px 0 20px;
            color:#fff;
        }

        .conteudo-panel__text{
            color:#fff;
            font-size:1rem;
            font-weight:700;
            line-height:1.35;
            margin:0;
        }

        .conteudo-panel__text.centered{
            text-align:center;
        }

        .conteudo-panel__button{
            width:240px;
            min-height:40px;
            border-radius:5px;
            background:var(--yellow);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            padding:10px 18px;
            font-weight:700;
            font-size:.96rem;
            transition:filter .18s ease, transform .18s ease;
        }

        .conteudo-panel__button:hover{
            filter:brightness(.96);
            transform:translateY(-1px);
        }



        .metas-curso{
            width:100%;
            max-width:none;
            margin:42px 0 0;
            background:#242424;
            border:1px solid rgba(255,255,255,.04);
            border-radius:9px;
            padding:23px 24px;
            color:#fff;
        }

        .metas-curso__head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin-bottom:18px;
        }

        .metas-curso__title{
            font-size:1.55rem;
            line-height:1.15;
            font-weight:700;
            color:#fff;
            margin:0;
        }

        .metas-curso__empty{
            min-height:54px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            color:#fff;
            font-size:1rem;
            font-weight:700;
            margin:0;
        }

        .metas-curso__error{
            color:#ffd1d1;
            font-size:.95rem;
            font-weight:700;
            text-align:center;
            margin:0;
        }

        .metas-list{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:16px;
        }

        .meta-card{
            background:#1c1c1c;
            border:1px solid rgba(255,255,255,.07);
            border-radius:12px;
            padding:17px;
            box-shadow:0 12px 24px rgba(0,0,0,.16);
        }

        .meta-card__top{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:14px;
            margin-bottom:14px;
        }

        .meta-card__title{
            color:#fff;
            font-size:1.08rem;
            line-height:1.25;
            font-weight:700;
            margin:0;
        }

        .meta-card__materia{
            color:var(--yellow);
            font-size:.84rem;
            font-weight:700;
            margin:0 0 5px;
            text-transform:uppercase;
            letter-spacing:.02em;
        }

        .meta-card__badges{
            display:flex;
            flex-direction:column;
            align-items:flex-end;
            gap:7px;
            flex:0 0 auto;
        }

        .meta-card__date{
            color:#fff;
            font-size:.78rem;
            font-weight:700;
            white-space:nowrap;
            display:inline-flex;
            align-items:center;
            gap:6px;
            background:rgba(250,181,40,.12);
            border:1px solid rgba(250,181,40,.22);
            border-radius:999px;
            padding:5px 8px;
        }

        .meta-card__date i{width:14px;height:14px;color:var(--yellow);}

        .meta-card__tempo{
            color:#fff;
            font-size:.88rem;
            font-weight:700;
            white-space:nowrap;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }

        .meta-card__tempo i{width:16px;height:16px;color:var(--yellow);}

        .meta-card__details{
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:9px;
            margin:0 0 13px;
        }

        .meta-detail{
            min-height:38px;
            border:1px solid rgba(250,181,40,.18);
            background:#161616;
            border-radius:9px;
            display:flex;
            align-items:center;
            gap:9px;
            padding:8px 10px;
            color:#fff;
            font-size:.86rem;
            font-weight:700;
        }

        .meta-detail i{width:17px;height:17px;color:var(--yellow);flex:0 0 auto;}
        .meta-detail span{display:block;color:#bdbdbd;font-size:.72rem;font-weight:700;margin-bottom:1px;}
        .meta-detail strong{display:block;color:#fff;font-size:.86rem;}

        .meta-card__bar{
            width:100%;
            height:9px;
            border-radius:999px;
            overflow:hidden;
            background:#3a3a3a;
            margin:0 0 9px;
        }

        .meta-card__bar span{
            display:block;
            width:var(--progresso, 0%);
            height:100%;
            background:var(--yellow);
            border-radius:999px;
        }

        .meta-card__progress{
            color:#d8d8d8;
            font-size:.88rem;
            font-weight:700;
            margin:0 0 14px;
        }

        .meta-card__actions{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .meta-card__btn{
            min-height:38px;
            border-radius:6px;
            background:var(--yellow);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            padding:9px 14px;
            font-size:.9rem;
            font-weight:700;
            text-decoration:none;
            transition:filter .18s ease, transform .18s ease;
        }

        .meta-card__btn:hover{
            filter:brightness(.96);
            transform:translateY(-1px);
        }

        .meta-card__btn--outline{
            background:transparent;
            border:1px solid var(--yellow);
            color:var(--yellow);
        }

        .meta-card__btn i,
        .meta-card__btn svg{
            width:17px;
            height:17px;
        }

        @media(max-width:1200px){
            .modulos{grid-template-columns:repeat(4, minmax(220px, 1fr));}
        }

        @media(max-width:980px){
            .metas-list{grid-template-columns:1fr;}
            .curso-hero{min-height:330px;background-position:center;}
            .curso-hero__content{padding:24px 18px 30px;}
            .curso-hero__title{font-size:2.25rem;}
            .section{padding:24px 18px 34px;}
            .section__title{font-size:1.8rem;}
            .modulos{grid-template-columns:repeat(2, minmax(220px, 1fr));gap:16px;}
            .conteudo-panel{grid-template-columns:1fr;align-items:start;}
            .conteudo-panel__button{width:100%;}
            .meta-card__actions{align-items:stretch;}
            .meta-card__btn{width:100%;}
            .meta-card__details{grid-template-columns:1fr;}
        }

        @media(max-width:640px){
            .curso-hero{
                min-height:260px;
                background:
                    linear-gradient(to bottom, rgba(0,0,0,.10), rgba(0,0,0,.84)),
                    url('<?php echo curso_modulos_esc($capaCursoMobile); ?>') center center / cover no-repeat;
            }
            .curso-hero__title{font-size:1.75rem;}
            .section__header{align-items:flex-start;flex-direction:column;gap:6px;}
            .section__title{font-size:1.65rem;}
            .modulos{display:flex;gap:14px;overflow-x:auto;padding-bottom:10px;scroll-snap-type:x proximity;}
            .modulo-card{min-width:250px;scroll-snap-align:start;}
            .modulo-card__thumb{height:300px;}
            .modulo-card__label{font-size:1.28rem;}
            .curso-badge{font-size:.88rem;}
            .conteudos-curso{margin-top:32px;}
            .conteudos-curso__title{font-size:1.65rem;}
            .conteudo-panel{padding:20px 18px;gap:18px;}
            .conteudo-panel__title{font-size:1.35rem;margin-bottom:12px;}
            .conteudo-panel__subtitle{margin-bottom:12px;}
        }
    </style>
</head>
<body>
    <?php if (is_file($barraSuperiorInclude)) { include $barraSuperiorInclude; } ?>

    <?php if ($curso): ?>
        <section class="curso-hero">
            <div class="curso-hero__content">
                <a href="dashboard.php" class="voltar-dashboard">
                    <i data-lucide="arrow-left" aria-hidden="true"></i>
                    <span>Voltar ao dashboard</span>
                </a>

                <h1 class="curso-hero__title"><?php echo curso_modulos_esc($curso['nome']); ?></h1>

                <div class="curso-hero__badges" aria-label="Informações do curso">
                    <?php if ($horasCurso !== ''): ?>
                        <span class="curso-badge"><i data-lucide="clock-3" aria-hidden="true"></i><?php echo curso_modulos_esc($horasCurso); ?> de curso</span>
                    <?php endif; ?>
                    <?php if ($horasVideo !== ''): ?>
                        <span class="curso-badge"><i data-lucide="play-circle" aria-hidden="true"></i><?php echo curso_modulos_esc($horasVideo); ?> em vídeo</span>
                    <?php endif; ?>
                    <?php if ($horasPdf !== ''): ?>
                        <span class="curso-badge"><i data-lucide="file-text" aria-hidden="true"></i><?php echo curso_modulos_esc($horasPdf); ?> em PDF</span>
                    <?php endif; ?>
                    <span class="curso-badge"><i data-lucide="layers" aria-hidden="true"></i><?php echo count($modulos); ?> <?php echo count($modulos) === 1 ? 'módulo' : 'módulos'; ?></span>
                </div>
            </div>
        </section>

        <main class="section">
            <div class="section__header">
                <h2 class="section__title">Módulos</h2>
                <div class="section__counter"><?php echo count($modulos); ?> <?php echo count($modulos) === 1 ? 'módulo disponível' : 'módulos disponíveis'; ?></div>
            </div>

            <?php if (!empty($modulos)): ?>
                <div class="modulos">
                    <?php foreach ($modulos as $modulo): ?>
                        <a href="<?php echo curso_modulos_esc($modulo['link']); ?>" class="modulo-card">
                            <div class="modulo-card__thumb">
                                <img src="<?php echo curso_modulos_esc($modulo['imagem']); ?>" alt="<?php echo curso_modulos_esc($modulo['titulo']); ?>">
                                <div class="modulo-card__tempo"><i data-lucide="clock-3" aria-hidden="true"></i><span><?php echo curso_modulos_esc($modulo['tempo']); ?></span></div>
                                <div class="modulo-card__label"><?php echo curso_modulos_esc($modulo['titulo']); ?></div>
                            </div>
                            <div class="modulo-card__footer">
                                <span><?php echo curso_modulos_esc($modulo['titulo']); ?></span>
                            </div>
                            <div class="modulo-card__bar"></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="book-open-check" aria-hidden="true"></i>
                    <strong>Nenhum módulo cadastrado nesse curso.</strong>
                    <span>Quando os módulos forem criados no painel administrativo, eles vão aparecer aqui automaticamente.</span>
                </div>
            <?php endif; ?>

            <section class="metas-curso" aria-label="Metas diárias">
                <div class="metas-curso__head">
                    <h3 class="metas-curso__title">Metas diárias</h3>
                </div>

                <?php if ($erroMetasCurso !== ''): ?>
                    <p class="metas-curso__error"><?php echo curso_modulos_esc($erroMetasCurso); ?></p>
                <?php elseif (empty($metasCurso)): ?>
                    <p class="metas-curso__empty">Você ainda não tem nenhuma meta diária.</p>
                <?php else: ?>
                    <div class="metas-list">
                        <?php foreach ($metasCurso as $meta): ?>
                            <article class="meta-card">
                                <div class="meta-card__top">
                                    <div>
                                        <?php if ($meta['materia'] !== ''): ?>
                                            <p class="meta-card__materia"><?php echo curso_modulos_esc($meta['materia']); ?></p>
                                        <?php endif; ?>
                                        <h4 class="meta-card__title"><?php echo curso_modulos_esc($meta['titulo']); ?></h4>
                                    </div>
                                    <div class="meta-card__badges">
                                        <span class="meta-card__date"><i data-lucide="calendar-days" aria-hidden="true"></i><?php echo curso_modulos_esc((string)($meta['data_meta_label'] ?? '')); ?></span>
                                        <span class="meta-card__tempo"><i data-lucide="timer" aria-hidden="true"></i><?php echo curso_modulos_esc($meta['tempo']); ?></span>
                                    </div>
                                </div>

                                <div class="meta-card__details" aria-label="Tempos da meta">
                                    <div class="meta-detail"><i data-lucide="play-circle" aria-hidden="true"></i><div><span>Vídeo</span><strong><?php echo curso_modulos_esc($meta['video_tempo']); ?></strong></div></div>
                                    <div class="meta-detail"><i data-lucide="file-text" aria-hidden="true"></i><div><span>PDF/leitura</span><strong><?php echo curso_modulos_esc($meta['pdf_tempo']); ?></strong></div></div>
                                    <div class="meta-detail"><i data-lucide="list-checks" aria-hidden="true"></i><div><span>Questões</span><strong><?php echo curso_modulos_esc($meta['questoes_tempo']); ?></strong></div></div>
                                </div>

                                <div class="meta-card__bar" aria-label="Progresso <?php echo (int)$meta['percentual']; ?>%">
                                    <span style="--progresso: <?php echo (int)$meta['percentual']; ?>%;"></span>
                                </div>
                                <p class="meta-card__progress">
                                    <?php echo curso_modulos_esc($meta['feito']); ?> de <?php echo curso_modulos_esc($meta['tempo']); ?> concluído · <?php echo (int)$meta['percentual']; ?>%
                                </p>

                                <div class="meta-card__actions">
                                    <a class="meta-card__btn" href="<?php echo curso_modulos_esc($meta['video_link']); ?>">
                                        <i data-lucide="play-circle" aria-hidden="true"></i>
                                        <span>Ir para vídeo</span>
                                    </a>
                                    <a class="meta-card__btn meta-card__btn--outline" href="<?php echo curso_modulos_esc($meta['banco_link']); ?>">
                                        <i data-lucide="list-checks" aria-hidden="true"></i>
                                        <span>Banco de questões</span>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="conteudos-curso" aria-label="Conteúdos do curso">
                <div class="conteudos-curso__header">
                    <h2 class="conteudos-curso__title">Conteúdos</h2>
                </div>

                <div class="conteudos-curso__grid">
                    <?php foreach ($conteudosCurso as $card): ?>
                        <article class="conteudo-panel">
                            <div class="conteudo-panel__content">
                                <h3 class="conteudo-panel__title"><?php echo curso_modulos_esc($card['titulo']); ?></h3>
                                <?php if (!empty($card['subtitulo'])): ?>
                                    <p class="conteudo-panel__subtitle"><?php echo curso_modulos_esc($card['subtitulo']); ?></p>
                                <?php endif; ?>
                                <p class="conteudo-panel__text<?php echo !empty($card['centralizado']) ? ' centered' : ''; ?>"><?php echo curso_modulos_esc($card['texto']); ?></p>
                            </div>
                            <a href="<?php echo curso_modulos_esc($card['link']); ?>" class="conteudo-panel__button"><?php echo curso_modulos_esc($card['botao']); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    <?php else: ?>
        <main class="section">
            <div class="empty-state">
                <i data-lucide="triangle-alert" aria-hidden="true"></i>
                <strong>Não foi possível carregar esse curso.</strong>
                <span><?php echo curso_modulos_esc($erroPagina !== '' ? $erroPagina : 'Volte ao dashboard e tente abrir o curso novamente.'); ?></span>
                <a href="dashboard.php" class="curso-badge" style="margin-top:8px;">Voltar ao dashboard</a>
            </div>
        </main>
    <?php endif; ?>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>
