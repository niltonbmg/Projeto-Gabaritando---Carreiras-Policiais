<?php
// includes/recompensas-lib.php
// Estrutura central de XP/recompensas do Projeto Gabaritando.
declare(strict_types=1);

if (!function_exists('recompensas_load_env')) {
    function recompensas_load_env(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return $env;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }
}

if (!function_exists('recompensas_db')) {
    function recompensas_db(): PDO
    {
        static $pdoLocal = null;
        if ($pdoLocal instanceof PDO) {
            return $pdoLocal;
        }

        $root = dirname(__DIR__);
        $conexao = $root . '/api/conexao.php';
        if (is_file($conexao)) {
            require_once $conexao;
        }

        if (function_exists('db')) {
            $db = db();
            if ($db instanceof PDO) {
                $pdoLocal = $db;
                return $pdoLocal;
            }
        }

        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $pdoLocal = $pdo;
            return $pdoLocal;
        }

        $env = recompensas_load_env($root . '/.env');
        $host = $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $port = $env['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $name = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? getenv('DB_DATABASE') ?: getenv('DB_NAME');
        $user = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? getenv('DB_USERNAME') ?: getenv('DB_USER');
        $pass = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? getenv('DB_PASSWORD') ?: getenv('DB_PASS');
        $charset = $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
        $timezone = $env['DB_TIMEZONE'] ?? getenv('DB_TIMEZONE') ?: '-03:00';

        if (!$name || !$user) {
            throw new RuntimeException('Configuração do banco não encontrada. Confira api/conexao.php ou .env.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdoLocal = new PDO($dsn, (string)$user, (string)$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdoLocal->exec('SET NAMES ' . $charset);
        $pdoLocal->exec('SET time_zone = ' . $pdoLocal->quote((string)$timezone));

        return $pdoLocal;
    }
}

if (!function_exists('recompensas_try_exec')) {
    function recompensas_try_exec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (Throwable $ignored) {
            // Mantém compatibilidade com banco que já tem índice/coluna.
        }
    }
}

if (!function_exists('recompensas_table_exists')) {
    function recompensas_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('recompensas_bootstrap')) {
    function recompensas_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_recompensas_regras (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            codigo VARCHAR(80) NOT NULL,
            titulo VARCHAR(190) NOT NULL,
            descricao TEXT NULL,
            xp INT NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_admin_recompensas_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_xp_eventos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            origem_tipo VARCHAR(60) NOT NULL,
            origem_id BIGINT UNSIGNED NOT NULL,
            origem_resultado VARCHAR(60) NULL,
            quantidade INT NOT NULL DEFAULT 1,
            xp INT NOT NULL DEFAULT 0,
            descricao VARCHAR(255) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_usuario_xp_origem (usuario_id, origem_tipo, origem_id),
            KEY idx_usuario_xp_usuario (usuario_id),
            KEY idx_usuario_xp_criado (criado_em),
            KEY idx_usuario_xp_tipo (origem_tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $regras = [
            ['meta_concluida', 'Meta diária concluída', 'XP ganho quando o aluno conclui uma meta do cronograma.', 0],
            ['banco_questao_correta', 'Banco de questões: primeira resposta correta', 'XP ganho quando a primeira resposta do aluno em uma questão for correta.', 0],
            ['banco_questao_errada', 'Banco de questões: primeira resposta errada', 'XP ganho quando a primeira resposta do aluno em uma questão for errada.', 0],
            ['simulado_finalizado', 'Simulado: primeiro envio', 'XP ganho quando o aluno envia um simulado pela primeira vez.', 0],
            ['simulado_acerto', 'Simulado: acerto no primeiro envio', 'XP por cada acerto no primeiro envio do simulado.', 0],
            // Método META (valores iniciais; o mentor ajusta em Recompensas)
            ['aula_concluida', 'Aula concluída (teoria da missão)', 'XP quando o aluno marca a aula como assistida.', 10],
            ['desafio_participou', 'Desafio do Dia: participou', 'XP por terminar o Desafio do Dia.', 20],
            ['desafio_acerto', 'Desafio do Dia: cada acerto', 'XP por acerto no Desafio do Dia.', 2],
            ['redacao_enviada', 'Redação enviada', 'XP por enviar uma redação para correção.', 30],
        ];

        $stmt = $pdo->prepare("INSERT INTO admin_recompensas_regras (codigo, titulo, descricao, xp, ativo)
            VALUES (:codigo, :titulo, :descricao, :xp, 1)
            ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descricao = VALUES(descricao)"); // não sobrescreve o XP que o mentor definiu
        foreach ($regras as $regra) {
            $stmt->execute([
                ':codigo' => $regra[0],
                ':titulo' => $regra[1],
                ':descricao' => $regra[2],
                ':xp' => $regra[3],
            ]);
        }
    }
}

if (!function_exists('recompensas_regras')) {
    function recompensas_regras(PDO $pdo): array
    {
        recompensas_bootstrap($pdo);
        $rows = $pdo->query('SELECT * FROM admin_recompensas_regras ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['codigo']] = $row;
        }
        return $map;
    }
}

if (!function_exists('recompensas_pontos')) {
    function recompensas_pontos(PDO $pdo, string $codigo): int
    {
        $regras = recompensas_regras($pdo);
        $regra = $regras[$codigo] ?? null;
        if (!$regra || (int)($regra['ativo'] ?? 0) !== 1) {
            return 0;
        }
        return max(0, (int)($regra['xp'] ?? 0));
    }
}

if (!function_exists('recompensas_gravar_evento')) {
    function recompensas_gravar_evento(PDO $pdo, int $usuarioId, string $tipo, int $origemId, int $xp, string $descricao, ?string $resultado = null, int $quantidade = 1, bool $atualizarSeExistir = true): void
    {
        if ($usuarioId <= 0 || $origemId <= 0) {
            return;
        }

        recompensas_bootstrap($pdo);
        if ($atualizarSeExistir) {
            $sql = "INSERT INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao)
                VALUES (:usuario_id, :origem_tipo, :origem_id, :origem_resultado, :quantidade, :xp, :descricao)
                ON DUPLICATE KEY UPDATE xp = VALUES(xp), descricao = VALUES(descricao), quantidade = VALUES(quantidade), atualizado_em = NOW()";
        } else {
            $sql = "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao)
                VALUES (:usuario_id, :origem_tipo, :origem_id, :origem_resultado, :quantidade, :xp, :descricao)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':origem_tipo' => $tipo,
            ':origem_id' => $origemId,
            ':origem_resultado' => $resultado,
            ':quantidade' => max(0, $quantidade),
            ':xp' => max(0, $xp),
            ':descricao' => $descricao,
        ]);
    }
}

if (!function_exists('recompensas_remover_evento')) {
    function recompensas_remover_evento(PDO $pdo, int $usuarioId, string $tipo, int $origemId): void
    {
        if ($usuarioId <= 0 || $origemId <= 0 || !recompensas_table_exists($pdo, 'usuario_xp_eventos')) {
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM usuario_xp_eventos WHERE usuario_id = :usuario_id AND origem_tipo = :tipo AND origem_id = :origem_id LIMIT 1');
        $stmt->execute([':usuario_id' => $usuarioId, ':tipo' => $tipo, ':origem_id' => $origemId]);
    }
}

if (!function_exists('recompensas_registrar_meta_concluida')) {
    function recompensas_registrar_meta_concluida(PDO $pdo, int $usuarioId, int $cronogramaItemId): void
    {
        $xp = recompensas_pontos($pdo, 'meta_concluida');
        recompensas_gravar_evento($pdo, $usuarioId, 'meta_diaria', $cronogramaItemId, $xp, 'Meta diária concluída', 'concluida', 1, true);
    }
}

if (!function_exists('recompensas_registrar_banco_primeira_resposta')) {
    function recompensas_registrar_banco_primeira_resposta(PDO $pdo, int $usuarioId, int $questaoId, int $correta): void
    {
        $resultado = $correta === 1 ? 'correta' : 'errada';
        $codigo = $correta === 1 ? 'banco_questao_correta' : 'banco_questao_errada';
        $xp = recompensas_pontos($pdo, $codigo);

        // INSERT IGNORE garante que correção/segunda resposta não altera o XP da primeira resposta.
        recompensas_gravar_evento($pdo, $usuarioId, 'banco_questao', $questaoId, $xp, 'Primeira resposta no banco de questões', $resultado, 1, false);
    }
}

if (!function_exists('recompensas_registrar_simulado_primeira_tentativa')) {
    function recompensas_registrar_simulado_primeira_tentativa(PDO $pdo, int $usuarioId, int $simuladoId, int $totalAcertos): void
    {
        $xp = recompensas_pontos($pdo, 'simulado_finalizado') + (max(0, $totalAcertos) * recompensas_pontos($pdo, 'simulado_acerto'));

        // INSERT IGNORE garante que refazer/corrigir o simulado não contabiliza de novo.
        recompensas_gravar_evento($pdo, $usuarioId, 'simulado', $simuladoId, $xp, 'Primeiro envio de simulado', 'primeira_tentativa', max(0, $totalAcertos), false);
    }
}

if (!function_exists('recompensas_sincronizar_eventos')) {
    function recompensas_sincronizar_eventos(PDO $pdo, ?int $usuarioId = null): void
    {
        recompensas_bootstrap($pdo);
        $whereUsuario = $usuarioId && $usuarioId > 0 ? ' AND usuario_id = ' . (int)$usuarioId : '';

        // Metas concluídas do cronograma.
        if (recompensas_table_exists($pdo, 'usuario_cronograma_conclusoes')) {
            $xpMeta = recompensas_pontos($pdo, 'meta_concluida');
            $sql = "INSERT INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT usuario_id, 'meta_diaria', cronograma_id, 'concluida', 1, {$xpMeta}, 'Meta diária concluída', COALESCE(atualizado_em, NOW())
                FROM usuario_cronograma_conclusoes
                WHERE concluido = 1 {$whereUsuario}
                ON DUPLICATE KEY UPDATE xp = VALUES(xp), descricao = VALUES(descricao), atualizado_em = NOW()";
            recompensas_try_exec($pdo, $sql);
        }

        // Banco de questões: cria evento a partir da primeira resposta conhecida. Depois não altera resultado.
        if (recompensas_table_exists($pdo, 'banco_questoes_respostas_usuario')) {
            $xpCorreta = recompensas_pontos($pdo, 'banco_questao_correta');
            $xpErrada = recompensas_pontos($pdo, 'banco_questao_errada');
            $sql = "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT r.usuario_id, 'banco_questao', r.questao_id,
                    CASE WHEN r.correta = 1 THEN 'correta' ELSE 'errada' END,
                    1,
                    CASE WHEN r.correta = 1 THEN {$xpCorreta} ELSE {$xpErrada} END,
                    'Primeira resposta no banco de questões',
                    COALESCE(r.respondido_em, r.criado_em, NOW())
                FROM banco_questoes_respostas_usuario r
                WHERE r.opcao_id IS NOT NULL AND r.respondido_em IS NOT NULL {$whereUsuario}";
            recompensas_try_exec($pdo, $sql);
        }

        // Simulados: primeiro envio por simulado.
        if (recompensas_table_exists($pdo, 'admin_simulado_tentativas')) {
            $xpBase = recompensas_pontos($pdo, 'simulado_finalizado');
            $xpAcerto = recompensas_pontos($pdo, 'simulado_acerto');
            $sql = "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT t.usuario_id, 'simulado', t.simulado_id, 'primeira_tentativa', t.total_acertos,
                    ({$xpBase} + (t.total_acertos * {$xpAcerto})),
                    'Primeiro envio de simulado', COALESCE(t.finalizado_em, t.criado_em, NOW())
                FROM admin_simulado_tentativas t
                INNER JOIN (
                    SELECT usuario_id, simulado_id, MIN(id) AS primeira_id
                    FROM admin_simulado_tentativas
                    WHERE usuario_id IS NOT NULL AND usuario_id > 0 {$whereUsuario}
                    GROUP BY usuario_id, simulado_id
                ) p ON p.primeira_id = t.id";
            recompensas_try_exec($pdo, $sql);
        }

        // Método META: aula concluída, Desafio do Dia e redação enviada também dão XP.
        $xpAula = recompensas_pontos($pdo, 'aula_concluida');
        if ($xpAula > 0 && recompensas_table_exists($pdo, 'aulas_progresso')) {
            recompensas_try_exec($pdo, "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT usuario_id, 'aula', aula_id, 'assistida', 1, {$xpAula}, 'Aula concluída', COALESCE(assistido_em, NOW())
                FROM aulas_progresso WHERE assistido = 1 {$whereUsuario}");
        }
        $xpDes = recompensas_pontos($pdo, 'desafio_participou');
        $xpDesA = recompensas_pontos($pdo, 'desafio_acerto');
        if (($xpDes > 0 || $xpDesA > 0) && recompensas_table_exists($pdo, 'desafio_tentativas')) {
            recompensas_try_exec($pdo, "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT usuario_id, 'desafio', desafio_id, 'finalizado', acertos, ({$xpDes} + acertos * {$xpDesA}), 'Desafio do Dia', finalizado_em
                FROM desafio_tentativas WHERE finalizado_em IS NOT NULL {$whereUsuario}");
        }
        $xpRed = recompensas_pontos($pdo, 'redacao_enviada');
        if ($xpRed > 0 && recompensas_table_exists($pdo, 'redacoes')) {
            recompensas_try_exec($pdo, "INSERT IGNORE INTO usuario_xp_eventos (usuario_id, origem_tipo, origem_id, origem_resultado, quantidade, xp, descricao, criado_em)
                SELECT usuario_id, 'redacao', id, 'enviada', 1, {$xpRed}, 'Redação enviada', enviada_em FROM redacoes WHERE 1=1 {$whereUsuario}");
        }

        // Atualiza pontos quando o admin muda as regras, preservando o resultado da primeira resposta/tentativa.
        $xpMeta = recompensas_pontos($pdo, 'meta_concluida');
        $xpCorreta = recompensas_pontos($pdo, 'banco_questao_correta');
        $xpErrada = recompensas_pontos($pdo, 'banco_questao_errada');
        $xpBase = recompensas_pontos($pdo, 'simulado_finalizado');
        $xpAcerto = recompensas_pontos($pdo, 'simulado_acerto');
        $usuarioSql = $usuarioId && $usuarioId > 0 ? ' AND usuario_id = ' . (int)$usuarioId : '';

        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = {$xpMeta}, atualizado_em = NOW() WHERE origem_tipo = 'meta_diaria' {$usuarioSql}");
        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = CASE WHEN origem_resultado = 'correta' THEN {$xpCorreta} ELSE {$xpErrada} END, atualizado_em = NOW() WHERE origem_tipo = 'banco_questao' {$usuarioSql}");
        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = ({$xpBase} + (quantidade * {$xpAcerto})), atualizado_em = NOW() WHERE origem_tipo = 'simulado' {$usuarioSql}");
        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = {$xpAula} WHERE origem_tipo = 'aula' {$usuarioSql}");
        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = ({$xpDes} + quantidade * {$xpDesA}) WHERE origem_tipo = 'desafio' {$usuarioSql}");
        recompensas_try_exec($pdo, "UPDATE usuario_xp_eventos SET xp = {$xpRed} WHERE origem_tipo = 'redacao' {$usuarioSql}");
    }
}

if (!function_exists('recompensas_ranking')) {
    function recompensas_ranking(PDO $pdo, string $periodo = 'sempre', int $limit = 10): array
    {
        recompensas_bootstrap($pdo);
        $wherePeriodo = '';
        if ($periodo === 'hoje') {
            $wherePeriodo = 'AND DATE(e.criado_em) = CURDATE()';
        } elseif ($periodo === 'mes') {
            $wherePeriodo = 'AND YEAR(e.criado_em) = YEAR(CURDATE()) AND MONTH(e.criado_em) = MONTH(CURDATE())';
        }

        $sql = "SELECT u.id, u.nome, u.email, COALESCE(SUM(CASE WHEN e.id IS NOT NULL {$wherePeriodo} THEN e.xp ELSE 0 END), 0) AS xp
            FROM usuarios u
            LEFT JOIN usuario_xp_eventos e ON e.usuario_id = u.id
            WHERE COALESCE(u.status, 'ativo') = 'ativo'
            GROUP BY u.id, u.nome, u.email
            ORDER BY xp DESC, u.nome ASC
            LIMIT " . max(1, min(100, $limit));

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('recompensas_xp_usuario')) {
    function recompensas_xp_usuario(PDO $pdo, int $usuarioId, ?string $periodo = null): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }
        recompensas_bootstrap($pdo);

        $where = 'usuario_id = :usuario_id';
        if ($periodo === 'hoje') {
            $where .= ' AND DATE(criado_em) = CURDATE()';
        } elseif ($periodo === 'mes') {
            $where .= ' AND YEAR(criado_em) = YEAR(CURDATE()) AND MONTH(criado_em) = MONTH(CURDATE())';
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(xp), 0) FROM usuario_xp_eventos WHERE {$where}");
        $stmt->execute([':usuario_id' => $usuarioId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('recompensas_streak_usuario')) {
    function recompensas_streak_usuario(PDO $pdo, int $usuarioId): int
    {
        if ($usuarioId <= 0 || !recompensas_table_exists($pdo, 'usuario_cronograma_conclusoes')) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("SELECT DISTINCT DATE(atualizado_em) AS dia
                FROM usuario_cronograma_conclusoes
                WHERE usuario_id = :usuario_id AND concluido = 1
                ORDER BY dia DESC
                LIMIT 120");
            $stmt->execute([':usuario_id' => $usuarioId]);
            $dias = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return 0;
        }

        if (!$dias) {
            return 0;
        }

        $map = array_flip($dias);
        $base = new DateTimeImmutable('today');
        if (!isset($map[$base->format('Y-m-d')])) {
            $base = $base->modify('-1 day');
        }

        $streak = 0;
        for ($i = 0; $i < 120; $i++) {
            $dia = $base->modify('-' . $i . ' day')->format('Y-m-d');
            if (!isset($map[$dia])) {
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
