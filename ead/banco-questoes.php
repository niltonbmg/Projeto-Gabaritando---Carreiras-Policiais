<?php
// banco-questoes.php
// Tela do aluno: banco de questões com filtros, cortes de alternativas, resposta automática e solução.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authFile = __DIR__ . '/api/authenticador.php';
if (is_file($authFile)) {
    require_once $authFile;
}

if (function_exists('auth_require_login')) {
    auth_require_login();
}

$recompensasLib = __DIR__ . '/includes/recompensas-lib.php';
if (is_file($recompensasLib)) {
    require_once $recompensasLib;
}

$cursoAcessoLib = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoLib)) {
    require_once $cursoAcessoLib;
}

if (!function_exists('bq_esc')) {
    function bq_esc(mixed $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bq_redirect')) {
    function bq_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('bq_usuario_id')) {
    function bq_usuario_id(): int
    {
        if (function_exists('auth_user_id')) {
            $id = (int)auth_user_id();
            if ($id > 0) {
                return $id;
            }
        }

        if (!empty($_SESSION['usuario']) && is_array($_SESSION['usuario']) && !empty($_SESSION['usuario']['id'])) {
            return (int)$_SESSION['usuario']['id'];
        }

        foreach (['usuario_id', 'id_usuario', 'user_id', 'cliente_id', 'aluno_id'] as $chave) {
            if (!empty($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        return 0;
    }
}

if (!function_exists('bq_load_env')) {
    function bq_load_env(string $path): array
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

if (!function_exists('bq_db')) {
    function bq_db(): PDO
    {
        static $pdoLocal = null;
        if ($pdoLocal instanceof PDO) {
            return $pdoLocal;
        }

        $conexao = __DIR__ . '/api/conexao.php';
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

        $env = bq_load_env(__DIR__ . '/.env');
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

if (!function_exists('bq_try_exec')) {
    function bq_try_exec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (Throwable $ignored) {
            // Não quebra a página se índice/constraint já existir.
        }
    }
}

if (!function_exists('bq_bootstrap')) {
    function bq_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS banco_questoes_respostas_usuario (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id INT UNSIGNED NOT NULL,
            questao_id INT UNSIGNED NOT NULL,
            opcao_id INT UNSIGNED DEFAULT NULL,
            correta TINYINT(1) DEFAULT NULL,
            opcoes_cortadas TEXT NULL,
            visualizou_solucao TINYINT(1) NOT NULL DEFAULT 0,
            respondido_em DATETIME NULL DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bq_usuario_questao (usuario_id, questao_id),
            KEY idx_bq_respostas_usuario (usuario_id),
            KEY idx_bq_respostas_questao (questao_id),
            KEY idx_bq_respostas_correta (correta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Compatibilidade com bancos antigos: curso_id virou acesso interno da questão.
        bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN curso_id INT UNSIGNED DEFAULT NULL AFTER id");
        bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN concurso_passado_id INT UNSIGNED DEFAULT NULL AFTER curso_id");
        bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_curso (curso_id)");
        bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_concurso_passado (concurso_passado_id)");
        bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD CONSTRAINT fk_admin_banco_questoes_curso FOREIGN KEY (curso_id) REFERENCES admin_cursos(id) ON DELETE SET NULL");

        bq_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_concursos_passados (
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
            KEY idx_admin_concursos_passados_banca (banca_id),
            KEY idx_admin_concursos_passados_status (status),
            KEY idx_admin_concursos_passados_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        bq_try_exec($pdo, "CREATE TABLE IF NOT EXISTS admin_banco_questao_acessos (
            questao_id INT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (questao_id, curso_id),
            KEY idx_admin_bq_acessos_curso (curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        bq_try_exec($pdo, "INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id)
            SELECT id, curso_id FROM admin_banco_questoes WHERE curso_id IS NOT NULL AND curso_id > 0");
    }
}

if (!function_exists('bq_table_exists')) {
    function bq_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
            $stmt->execute([':table_name' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('bq_cursos_usuario')) {
    function bq_cursos_usuario(PDO $pdo, int $usuarioId): array
    {
        if (!bq_table_exists($pdo, 'admin_cursos')) {
            return [];
        }

        try {
            $where = "COALESCE(status, 'ativo') = 'ativo'";
            $params = [];

            if (function_exists('gab_curso_acesso_sql')) {
                [$filtroAcesso, $paramsAcesso] = gab_curso_acesso_sql($pdo, $usuarioId, 'id', 'bq_lista_curso');
                if ($filtroAcesso !== '') {
                    $where .= ' ' . $filtroAcesso;
                    $params = $paramsAcesso;
                }
            }

            $stmt = $pdo->prepare("SELECT id, nome FROM admin_cursos WHERE {$where} ORDER BY ordem ASC, nome ASC, id ASC");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}


if (!function_exists('bq_acesso_sql')) {
    function bq_acesso_sql(PDO $pdo, int $usuarioId, string $alias = 'q', string $prefixo = 'bq_acesso'): array
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
}

if (!function_exists('bq_usuario_pode_ver_questao')) {
    function bq_usuario_pode_ver_questao(PDO $pdo, int $usuarioId, int $questaoId): bool
    {
        [$filtroAcesso, $paramsAcesso] = bq_acesso_sql($pdo, $usuarioId, 'q', 'bq_post_acesso');
        $stmt = $pdo->prepare("SELECT q.id FROM admin_banco_questoes q WHERE q.id = :id AND q.status = 'ativo' {$filtroAcesso} LIMIT 1");
        $stmt->execute(array_merge([':id' => $questaoId], $paramsAcesso));
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('bq_opcoes_cortadas_array')) {
    function bq_opcoes_cortadas_array(mixed $valor): array
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return [];
        }

        $ids = array_filter(array_map(static fn($id): int => (int)$id, explode(',', $valor)), static fn(int $id): bool => $id > 0);
        return array_values(array_unique($ids));
    }
}

if (!function_exists('bq_opcoes_cortadas_string')) {
    function bq_opcoes_cortadas_string(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        sort($ids);
        return implode(',', $ids);
    }
}

if (!function_exists('bq_ensure_resposta')) {
    function bq_ensure_resposta(PDO $pdo, int $usuarioId, int $questaoId): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO banco_questoes_respostas_usuario (usuario_id, questao_id) VALUES (:usuario_id, :questao_id)');
        $stmt->execute([':usuario_id' => $usuarioId, ':questao_id' => $questaoId]);
    }
}

if (!function_exists('bq_json')) {
    function bq_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$usuarioId = bq_usuario_id();
if ($usuarioId <= 0) {
    bq_redirect('login.php?redirect=' . rawurlencode('banco-questoes.php'));
}

$erroPagina = '';
$pdo = null;
try {
    $pdo = bq_db();
    bq_bootstrap($pdo);
} catch (Throwable $e) {
    $erroPagina = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'concluir_revisao' && $pdo instanceof PDO) {
    require_once __DIR__ . '/includes/missao-lib.php';
    meta_concluir_revisao($pdo, (int)$usuarioId, (int)($_POST['item_id'] ?? 0), (int)($_POST['rodada'] ?? 0));
    missao_limpar_cache((int)$usuarioId);
    $prox = missao_proximo_passo($pdo, (int)$usuarioId, true);
    header('Location: ' . ($prox['url'] ?? 'meu-plano.php') . (strpos((string)($prox['url'] ?? ''), '?') !== false ? '&' : '?') . 'ok=revisao');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if (!$pdo instanceof PDO) {
        bq_json(['ok' => false, 'mensagem' => 'Banco indisponível.'], 500);
    }

    $acao = (string)$_POST['acao'];
    $questaoId = max(0, (int)($_POST['questao_id'] ?? 0));

    try {
        if ($questaoId <= 0) {
            throw new RuntimeException('Questão inválida.');
        }

        if (!bq_usuario_pode_ver_questao($pdo, $usuarioId, $questaoId)) {
            throw new RuntimeException('Questão não liberada para seu acesso.');
        }

        bq_ensure_resposta($pdo, $usuarioId, $questaoId);

        if ($acao === 'salvar_resposta') {
            $opcaoId = max(0, (int)($_POST['opcao_id'] ?? 0));
            if ($opcaoId <= 0) {
                throw new RuntimeException('Opção inválida.');
            }

            $stmtOpcao = $pdo->prepare('SELECT id, correta FROM admin_banco_questao_opcoes WHERE id = :opcao_id AND questao_id = :questao_id LIMIT 1');
            $stmtOpcao->execute([':opcao_id' => $opcaoId, ':questao_id' => $questaoId]);
            $opcao = $stmtOpcao->fetch();
            if (!$opcao) {
                throw new RuntimeException('Opção não encontrada.');
            }

            $correta = (int)$opcao['correta'] === 1 ? 1 : 0;
            $stmtUpdate = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET opcao_id = :opcao_id, correta = :correta, respondido_em = NOW(), atualizado_em = NOW() WHERE usuario_id = :usuario_id AND questao_id = :questao_id LIMIT 1');
            $stmtUpdate->execute([
                ':opcao_id' => $opcaoId,
                ':correta' => $correta,
                ':usuario_id' => $usuarioId,
                ':questao_id' => $questaoId,
            ]);

            if (function_exists('recompensas_registrar_banco_primeira_resposta')) {
                try {
                    recompensas_registrar_banco_primeira_resposta($pdo, $usuarioId, $questaoId, $correta);
                } catch (Throwable $ignored) {
                    // Não quebra a resposta da questão se o XP falhar.
                }
            }

            $stmtCorreta = $pdo->prepare('SELECT id, letra FROM admin_banco_questao_opcoes WHERE questao_id = :questao_id AND correta = 1 ORDER BY ordem ASC, id ASC LIMIT 1');
            $stmtCorreta->execute([':questao_id' => $questaoId]);
            $opcaoCorreta = $stmtCorreta->fetch() ?: ['id' => 0, 'letra' => ''];

            // Ciclo no automático: quantas já foram neste assunto (para o contador e a liberação).
            $cicloResp = null;
            try {
                $stT = $pdo->prepare('SELECT materia_topico_id FROM admin_banco_questoes WHERE id = :q');
                $stT->execute([':q' => $questaoId]);
                $topResp = (int)$stT->fetchColumn();
                if ($topResp > 0) {
                    $stT = $pdo->prepare('SELECT COUNT(*) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u AND q.materia_topico_id = :t');
                    $stT->execute([':u' => $usuarioId, ':t' => $topResp]);
                    $cicloResp = ['topico_id' => $topResp, 'feitas' => (int)$stT->fetchColumn()];
                    // Desempenho e evolução: o assunto, a matéria e o geral (para o aluno ver que está avançando).
                    require_once __DIR__ . '/includes/meta-lib.php';
                    $cfgD = meta_config($pdo);
                    $stD = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(r.correta),0) a FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u AND q.materia_topico_id = :t');
                    $stD->execute([':u' => $usuarioId, ':t' => $topResp]);
                    $dA = $stD->fetch();
                    $nivA = meta_nivel((int)$dA['n'], (int)$dA['a'], $cfgD);
                    $stM = $pdo->prepare('SELECT m.nome, COUNT(*) n, COALESCE(SUM(r.correta),0) a FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id
                                           JOIN admin_materias m ON m.id = q.materia_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u AND q.materia_id = (SELECT materia_id FROM admin_banco_questoes WHERE id = :q) GROUP BY m.nome');
                    $stM->execute([':u' => $usuarioId, ':q' => $questaoId]);
                    $dM = $stM->fetch() ?: ['nome' => '', 'n' => 0, 'a' => 0];
                    $stG = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(correta),0) a, COALESCE(SUM(respondido_em >= NOW() - INTERVAL 7 DAY),0) n7, COALESCE(SUM(correta AND respondido_em >= NOW() - INTERVAL 7 DAY),0) a7 FROM banco_questoes_respostas_usuario WHERE opcao_id IS NOT NULL AND usuario_id = :u');
                    $stG->execute([':u' => $usuarioId]);
                    $dG = $stG->fetch();
                    $pct = static fn($a, $n): int => (int)$n > 0 ? (int)round((int)$a * 100 / (int)$n) : 0;
                    $antesN = (int)$dG['n'] - (int)$dG['n7'];
                    $cicloResp['desempenho'] = [
                        'assunto' => ['acertos' => (int)$dA['a'], 'feitas' => (int)$dA['n'], 'pct' => $pct($dA['a'], $dA['n']), 'nivel' => $nivA['nome'], 'nivel_n' => $nivA['nivel'], 'proximo_nivel' => $nivA['proximo_nome'], 'faltam_proximo' => $nivA['faltam_proximo'], 'proximo_acerto' => $nivA['proximo_acerto']],
                        'materia' => ['nome' => (string)$dM['nome'], 'pct' => $pct($dM['a'], $dM['n']), 'feitas' => (int)$dM['n']],
                        'geral' => ['pct' => $pct($dG['a'], $dG['n']), 'feitas' => (int)$dG['n'], 'semana_pct' => $pct($dG['a7'], $dG['n7']), 'semana_feitas' => (int)$dG['n7'],
                                    'antes_pct' => $antesN > 0 ? $pct((int)$dG['a'] - (int)$dG['a7'], $antesN) : null],
                    ];
                }
                unset($_SESSION['gab_missao_' . (int)$usuarioId]);
                if ($cicloResp && is_file(__DIR__ . '/includes/missao-lib.php')) {
                    require_once __DIR__ . '/includes/missao-lib.php';
                    $prox = missao_proximo_passo($pdo, (int)$usuarioId, true);
                    $cicloResp['proximo'] = $prox ? ['url' => $prox['url'], 'acao' => $prox['acao'], 'assunto' => $prox['assunto'], 'topico_missao' => $prox['tipo'] === 'questoes' ? $prox['url'] : ''] : null;
                }
            } catch (Throwable $ignored) {
            }

            bq_json([
                'ok' => true,
                'ciclo' => $cicloResp,
                'questao_id' => $questaoId,
                'opcao_id' => $opcaoId,
                'correta' => $correta === 1,
                'opcao_correta_id' => (int)$opcaoCorreta['id'],
                'opcao_correta_letra' => (string)$opcaoCorreta['letra'],
                'mensagem' => $correta === 1 ? 'Resposta correta salva.' : 'Resposta salva. Confira o gabarito na solução.',
            ]);
        }

        if ($acao === 'salvar_corte') {
            $opcaoId = max(0, (int)($_POST['opcao_id'] ?? 0));
            $cortada = (int)($_POST['cortada'] ?? 0) === 1;
            if ($opcaoId <= 0) {
                throw new RuntimeException('Opção inválida.');
            }

            $stmtOpcao = $pdo->prepare('SELECT id FROM admin_banco_questao_opcoes WHERE id = :opcao_id AND questao_id = :questao_id LIMIT 1');
            $stmtOpcao->execute([':opcao_id' => $opcaoId, ':questao_id' => $questaoId]);
            if (!$stmtOpcao->fetchColumn()) {
                throw new RuntimeException('Opção não encontrada.');
            }

            $stmtAtual = $pdo->prepare('SELECT opcoes_cortadas FROM banco_questoes_respostas_usuario WHERE usuario_id = :usuario_id AND questao_id = :questao_id LIMIT 1');
            $stmtAtual->execute([':usuario_id' => $usuarioId, ':questao_id' => $questaoId]);
            $ids = bq_opcoes_cortadas_array($stmtAtual->fetchColumn());

            if ($cortada && !in_array($opcaoId, $ids, true)) {
                $ids[] = $opcaoId;
            }
            if (!$cortada) {
                $ids = array_values(array_filter($ids, static fn(int $id): bool => $id !== $opcaoId));
            }

            $stmtUpdate = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET opcoes_cortadas = :opcoes_cortadas, atualizado_em = NOW() WHERE usuario_id = :usuario_id AND questao_id = :questao_id LIMIT 1');
            $stmtUpdate->execute([
                ':opcoes_cortadas' => bq_opcoes_cortadas_string($ids),
                ':usuario_id' => $usuarioId,
                ':questao_id' => $questaoId,
            ]);

            bq_json(['ok' => true, 'questao_id' => $questaoId, 'opcao_id' => $opcaoId, 'cortada' => $cortada]);
        }

        if ($acao === 'salvar_solucao') {
            $visivel = (int)($_POST['visivel'] ?? 1) === 1 ? 1 : 0;

            if ($visivel === 1) {
                $stmtRespondida = $pdo->prepare('SELECT opcao_id FROM banco_questoes_respostas_usuario WHERE usuario_id = :usuario_id AND questao_id = :questao_id LIMIT 1');
                $stmtRespondida->execute([':usuario_id' => $usuarioId, ':questao_id' => $questaoId]);
                $opcaoRespondida = (int)($stmtRespondida->fetchColumn() ?: 0);
                if ($opcaoRespondida <= 0) {
                    throw new RuntimeException('Escolha uma alternativa antes de ver a solução.');
                }
            }

            $stmtUpdate = $pdo->prepare('UPDATE banco_questoes_respostas_usuario SET visualizou_solucao = :visivel, atualizado_em = NOW() WHERE usuario_id = :usuario_id AND questao_id = :questao_id LIMIT 1');
            $stmtUpdate->execute([
                ':visivel' => $visivel,
                ':usuario_id' => $usuarioId,
                ':questao_id' => $questaoId,
            ]);

            bq_json(['ok' => true, 'questao_id' => $questaoId, 'visivel' => $visivel === 1]);
        }

        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $e) {
        bq_json(['ok' => false, 'mensagem' => $e->getMessage()], 400);
    }
}

$concursoPassadoId = max(0, (int)($_GET['concurso_passado_id'] ?? 0));
// Ciclo na tela da aula: as questões do assunto em JSON (mesmo filtro de acesso do banco). Responde por 'salvar_resposta'.
if (isset($_GET['ciclo_json']) && $pdo instanceof PDO && $usuarioId > 0) {
    try {
        $tq = max(0, (int)($_GET['topico_id'] ?? 0));
        require_once __DIR__ . '/includes/meta-lib.php';
        $cfgC = meta_config($pdo);
        $minC = max(1, (int)($cfgC['min_questoes'] ?? 5));
        $metaC = max($minC, (int)($cfgC['nivel4_questoes'] ?? 50)); // meta do assunto (dominar): 50
        [$filtroC, $paramsC] = bq_acesso_sql($pdo, $usuarioId, 'q', 'bq_ciclo');
        $stC = $pdo->prepare("SELECT q.id, q.enunciado, q.explicacao, (r.opcao_id IS NOT NULL) respondida
                                FROM admin_banco_questoes q LEFT JOIN banco_questoes_respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = :u
                               WHERE q.status = 'ativo' AND q.materia_topico_id = :t {$filtroC}
                                 AND EXISTS (SELECT 1 FROM admin_banco_questao_opcoes o WHERE o.questao_id = q.id AND o.correta = 1)
                               ORDER BY (r.opcao_id IS NOT NULL), q.id LIMIT 300");
        $stC->execute(array_merge([':u' => $usuarioId, ':t' => $tq], $paramsC));
        $todas = $stC->fetchAll();
        $feitas = count(array_filter($todas, static fn($x) => (int)$x['respondida'] === 1));
        $naoFeitas = array_values(array_filter($todas, static fn($x) => (int)$x['respondida'] === 0));
        // Primeiro o mínimo para liberar; depois, se o aluno quiser, lotes de 10 rumo à meta.
        $lista = array_slice($naoFeitas, 0, !empty($_GET['mais']) || $feitas >= $minC ? 10 : max(1, $minC - $feitas));
        $stO = $pdo->prepare('SELECT id, letra, texto FROM admin_banco_questao_opcoes WHERE questao_id = :q ORDER BY ordem, letra, id');
        $saida = [];
        foreach ($lista as $q) {
            $stO->execute([':q' => (int)$q['id']]);
            $saida[] = ['id' => (int)$q['id'], 'enunciado' => (string)$q['enunciado'], 'explicacao' => (string)$q['explicacao'], 'opcoes' => $stO->fetchAll(PDO::FETCH_ASSOC)];
        }
        bq_json(['ok' => true, 'min' => min($minC, count($todas)), 'meta' => min($metaC, count($todas)), 'meta_ideal' => $metaC, 'disponiveis' => count($todas), 'feitas' => $feitas, 'questoes' => $saida]);
    } catch (Throwable $e) {
        bq_json(['ok' => false, 'erro' => 'Não foi possível carregar as questões.'], 400);
    }
}
$topicoId = max(0, (int)($_GET['topico_id'] ?? 0));
$bancaId = max(0, (int)($_GET['banca_id'] ?? 0));
$ano = max(0, (int)($_GET['ano'] ?? 0));
$statusFiltro = (string)($_GET['status'] ?? '');
$perPage = (int)($_GET['por_pagina'] ?? 10);
$perPage = in_array($perPage, [10, 30, 50], true) ? $perPage : 10;
$page = max(1, (int)($_GET['pagina'] ?? 1));

$concursosPassados = [];
$topicos = [];
$bancas = [];
$anos = [];
$questoes = [];
$opcoesPorQuestao = [];
$totalQuestoes = 0;
$totalPaginas = 1;

try {
    if ($pdo instanceof PDO && bq_table_exists($pdo, 'admin_banco_questoes')) {
        $concursosPassados = $pdo->query("SELECT id, titulo, ano FROM admin_concursos_passados WHERE status = 'ativo' ORDER BY ordem ASC, ano DESC, titulo ASC")->fetchAll();
        $topicos = $pdo->query("SELECT t.*, m.nome AS materia_nome FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id WHERE t.status = 'ativo' AND m.status = 'ativo' ORDER BY m.ordem ASC, m.nome ASC, t.ordem ASC, t.titulo ASC")->fetchAll();
        $bancas = $pdo->query("SELECT * FROM admin_bancas WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();
        $anos = $pdo->query("SELECT DISTINCT ano FROM admin_banco_questoes WHERE status = 'ativo' AND ano IS NOT NULL AND ano > 0 ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["q.status = 'ativo'"];
        $params = [':usuario_id_join' => $usuarioId];

        [$filtroAcesso, $paramsAcesso] = bq_acesso_sql($pdo, $usuarioId, 'q', 'bq_curso_acesso');
        if ($filtroAcesso !== '') {
            $where[] = preg_replace('/^\s*AND\s+/i', '', trim($filtroAcesso));
            $params = array_merge($params, $paramsAcesso);
        }

        if ($concursoPassadoId > 0) {
            $where[] = 'q.concurso_passado_id = :concurso_passado_id';
            $params[':concurso_passado_id'] = $concursoPassadoId;
        }

        if ($topicoId > 0) {
            $where[] = 'q.materia_topico_id = :topico_id';
            $params[':topico_id'] = $topicoId;
        }

        if ($bancaId > 0) {
            $where[] = 'q.banca_id = :banca_id';
            $params[':banca_id'] = $bancaId;
        }

        if ($ano > 0) {
            $where[] = 'q.ano = :ano';
            $params[':ano'] = $ano;
        }

        if ($statusFiltro === 'respondidas') {
            $where[] = 'r.opcao_id IS NOT NULL';
        } elseif ($statusFiltro === 'nao_respondidas') {
            $where[] = 'r.opcao_id IS NULL';
        } elseif ($statusFiltro === 'certas') {
            $where[] = 'r.correta = 1';
        } elseif ($statusFiltro === 'erradas') {
            $where[] = 'r.opcao_id IS NOT NULL AND r.correta = 0';
        } elseif ($statusFiltro === 'solucao_vista') {
            $where[] = 'r.visualizou_solucao = 1';
        }

        $whereSql = implode(' AND ', $where);
        $joinSql = "
            FROM admin_banco_questoes q
            LEFT JOIN admin_cursos c ON c.id = q.curso_id
            LEFT JOIN admin_concursos_passados cp ON cp.id = q.concurso_passado_id
            INNER JOIN admin_materias m ON m.id = q.materia_id
            INNER JOIN admin_bancas b ON b.id = q.banca_id
            LEFT JOIN admin_materia_topicos mt ON mt.id = q.materia_topico_id
            LEFT JOIN banco_questoes_respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = :usuario_id_join
            WHERE {$whereSql}
        ";

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) ' . $joinSql);
        foreach ($params as $key => $value) {
            $stmtTotal->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtTotal->execute();
        $totalQuestoes = (int)$stmtTotal->fetchColumn();
        $totalPaginas = max(1, (int)ceil($totalQuestoes / $perPage));
        $page = min($page, $totalPaginas);
        $offset = ($page - 1) * $perPage;

        $sqlQuestoes = "
            SELECT
                q.*,
                c.nome AS curso_nome,
                cp.titulo AS concurso_passado_titulo,
                cp.ano AS concurso_passado_ano,
                m.nome AS materia_nome,
                b.nome AS banca_nome,
                b.sigla AS banca_sigla,
                mt.titulo AS materia_topico_titulo,
                r.opcao_id AS resposta_opcao_id,
                r.correta AS resposta_correta,
                r.opcoes_cortadas,
                r.visualizou_solucao
            {$joinSql}
            ORDER BY q.ordem ASC, q.id ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmtQuestoes = $pdo->prepare($sqlQuestoes);
        foreach ($params as $key => $value) {
            $stmtQuestoes->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtQuestoes->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmtQuestoes->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtQuestoes->execute();
        $questoes = $stmtQuestoes->fetchAll();

        $ids = array_map(static fn(array $q): int => (int)$q['id'], $questoes);
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtOpcoes = $pdo->prepare("SELECT * FROM admin_banco_questao_opcoes WHERE questao_id IN ({$placeholders}) ORDER BY questao_id ASC, ordem ASC, id ASC");
            $stmtOpcoes->execute($ids);
            foreach ($stmtOpcoes->fetchAll() as $opcao) {
                $opcoesPorQuestao[(int)$opcao['questao_id']][] = $opcao;
            }
        }
    } elseif ($pdo instanceof PDO) {
        $erroPagina = 'As tabelas do banco de questões ainda não existem. Cadastre as questões pelo painel admin ou importe o SQL.';
    }
} catch (Throwable $e) {
    $erroPagina = $e->getMessage();
}

function bq_build_url(array $changes = []): string
{
    $params = $_GET;
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);
    return 'banco-questoes.php' . ($query !== '' ? '?' . $query : '');
}

function bq_status_label(?string $status): string
{
    return match ($status) {
        'respondidas' => 'Respondidas',
        'nao_respondidas' => 'Não respondidas',
        'certas' => 'Certas',
        'erradas' => 'Erradas',
        'solucao_vista' => 'Solução vista',
        default => 'Status',
    };
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
    <title>Banco de Questões | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#c98f0e;
            --page:#f5f5f6;
            --panel:#fbfbfb;
            --line:#e7e8ee;
            --text:#060b18;
            --muted:#7b8190;
            --green:#35c96f;
            --red:#ff6b6b;
            --shadow:0 12px 28px rgba(15,23,42,.05);
        }
        *{box-sizing:border-box;margin:0;padding:0}
        html,body{width:100%;min-height:100%}
        body{font-family:'Ubuntu',Arial,sans-serif;background:var(--page);color:var(--text)}
        a{text-decoration:none;color:inherit}
        button,select{font:inherit}
        button{cursor:pointer}
        .page-wrap{width:100%;max-width:1240px;margin:30px auto 0;padding:0 0 42px}
        .bq-card{background:var(--panel);border-radius:8px 8px 0 0;box-shadow:var(--shadow);overflow:hidden}
        .bq-title{min-height:80px;background:var(--yellow);color:#fff;display:flex;align-items:center;justify-content:center;text-align:center;font-size:2rem;font-weight:700;letter-spacing:.02em}
        .bq-body{padding:24px 30px 28px;background:#fbfbfb;min-height:520px}
        .filters{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:0 0 26px}
        .filters-label{font-size:1.02rem;color:#050505;margin-right:2px;white-space:nowrap}
        .select-wrap{position:relative;min-width:150px}
        .select-wrap.is-wide{min-width:265px}
        .select-wrap.is-mid{min-width:205px}
        .filter-select{appearance:none;width:100%;height:40px;border:0;border-radius:8px;background:var(--yellow);color:#fff;padding:0 38px 0 13px;font-size:.95rem;font-weight:500;outline:none}
        .select-wrap::after{content:'⌄';position:absolute;right:14px;top:50%;transform:translateY(-57%);color:#fff;font-size:1.25rem;pointer-events:none;line-height:1}
        .question{padding:10px 0 28px;margin-bottom:24px;border-bottom:1px solid rgba(250,181,40,.55)}
        .question:last-of-type{margin-bottom:0}
        .question-meta{color:#a6a8af;font-size:.82rem;font-weight:700;margin:0 0 12px;line-height:1.45}
        .question-text{font-size:1.08rem;line-height:1.45;color:#000;margin:0 0 24px;white-space:pre-line;max-width:1135px}
        .options{display:grid;gap:14px;margin:0 0 26px;max-width:1120px}
        .option-row{display:grid;grid-template-columns:24px 35px 1fr;gap:10px;align-items:center;min-height:42px;transition:opacity .18s ease}
        .cut-btn{width:24px;height:24px;border:0;background:transparent;color:#ff7676;display:flex;align-items:center;justify-content:center;padding:0}
        .cut-btn svg{width:22px;height:22px;stroke-width:2.2}
        .option-letter{width:32px;height:32px;border:1px solid #1f2937;border-radius:999px;background:#fff;color:#111;display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:500;transition:background .18s ease,border-color .18s ease,color .18s ease}
        .option-text{border:0;background:transparent;text-align:left;color:#000;font-size:1rem;line-height:1.35;padding:5px 0;min-height:32px;display:flex;align-items:center}
        .option-row.is-cut .option-letter,.option-row.is-cut .option-text{opacity:.28;text-decoration:line-through;text-decoration-thickness:2px}
        .option-row.is-selected.is-wrong .option-letter{background:var(--red);border-color:var(--red);color:#111}
        .option-row.is-selected.is-correct .option-letter,.option-row.is-answer .option-letter{background:var(--green);border-color:var(--green);color:#061b0c}
        .question-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 8px}
        .solution-btn{min-height:38px;border:0;border-radius:7px;background:var(--yellow);color:#fff;font-weight:700;padding:0 15px;display:inline-flex;align-items:center;gap:8px}
        .solution-btn.is-open{background:transparent;color:var(--yellow);border:1px solid var(--yellow)}
        .solution-btn svg{width:18px;height:18px}.duvidas{font-size:.82rem;color:#f00000;text-decoration:underline;margin-left:3px}
        .save-msg{font-size:.84rem;color:#6b7280;font-weight:700;min-height:18px}
        .solution-box{display:none;max-width:1100px;margin:18px 0 0;color:#000;font-size:1rem;line-height:1.45;white-space:pre-line}
        .solution-box.is-open{display:block}.solution-box h3{font-size:1.25rem;margin:0 0 9px}.solution-box strong{font-weight:700}
        .empty-box{min-height:230px;display:flex;align-items:center;justify-content:center;text-align:center;color:#6b7280;padding:28px}.empty-box h2{font-size:1.2rem;color:#111;margin-bottom:8px}.empty-box p{line-height:1.45}.alert{border:1px solid #fecaca;background:#fff1f2;color:#b91c1c;border-radius:9px;padding:13px 14px;font-weight:700;margin-bottom:18px;line-height:1.35}
        .pagination{display:grid;grid-template-columns:1fr auto 1fr;align-items:end;gap:18px;margin:18px 0 0}.pager-center{text-align:center}.pager-title{font-size:.75rem;color:#344054;text-transform:uppercase;margin:0 0 8px}.pager-controls{display:flex;align-items:center;justify-content:center;gap:12px}.pager-btn{width:39px;height:39px;border:0;background:var(--yellow);color:#fff;display:flex;align-items:center;justify-content:center}.pager-btn.is-disabled{opacity:.45;pointer-events:none}.pager-number{min-width:55px;height:39px;border-bottom:1px solid var(--yellow);display:flex;align-items:center;justify-content:center;color:#111}.pager-divider{height:39px;width:1px;background:var(--yellow);opacity:.85}.per-page{justify-self:end;display:flex;align-items:center;gap:10px}.per-page a{width:40px;height:40px;border:1px solid #e1e3e8;display:flex;align-items:center;justify-content:center;color:var(--yellow-dark);background:#fff}.per-page a.is-active{background:var(--yellow);border-color:var(--yellow);color:#fff;font-weight:700}.per-page span{font-size:.98rem;color:#111}
        @media(max-width:980px){.page-wrap{padding:0 14px 34px;margin-top:22px}.bq-title{min-height:70px;font-size:1.65rem}.bq-body{padding:18px 16px 24px}.filters{gap:8px}.select-wrap,.select-wrap.is-wide,.select-wrap.is-mid{min-width:calc(50% - 5px)}.filters-label{width:100%;margin-bottom:2px}.pagination{grid-template-columns:1fr;justify-items:center}.per-page{justify-self:center}}
.bqc{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px;padding:14px 16px;border-radius:14px;background:#17130a;border:1px solid rgba(251,185,40,.45);color:#f3f3f4}
        .bqc > div:first-child{display:grid;gap:4px;min-width:0;flex:1}
        .bqc small{color:#fbb928;font-weight:700;font-size:12px;letter-spacing:.06em;text-transform:uppercase}
        .bqc strong{font-size:16px}
        .bqc span{font-size:14px;color:#d4d4d8}
        .bqc-barra{position:relative;display:block;height:8px;border-radius:99px;background:rgba(255,255,255,.14);max-width:420px;margin-top:4px}
        .bqc-barra i{display:block;height:100%;border-radius:99px;background:#fbb928;transition:width .4s}
        .bqc-barra b{position:absolute;top:-3px;width:2px;height:14px;background:#fff}
        .bqc-bt{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;background:#fbb928;color:#111;font:inherit;font-weight:700;font-size:14px;text-decoration:none;cursor:pointer}
        .bqc.liberou{border-color:#22c55e;background:#0f2a1a}
        .bqc-ok{margin:0 0 12px;padding:12px 14px;border-radius:12px;background:#0f2a1a;color:#4ade80;font-weight:700}
        @media(max-width:640px){.page-wrap{padding:0 12px 28px;margin-top:18px}.bq-title{min-height:60px;font-size:1.1rem}.bq-body{padding:15px 12px 22px}.select-wrap,.select-wrap.is-wide,.select-wrap.is-mid{min-width:100%}.option-row{grid-template-columns:24px 35px 1fr;gap:8px}.question-text{font-size:1rem}.option-text{font-size:.95rem}.pager-controls{gap:8px}.per-page{gap:6px;flex-wrap:wrap;justify-content:center}}
    
.bqd{margin:12px 0;padding:16px;border-radius:16px;background:#161618;border:1px solid #3a3a3e;display:grid;gap:10px}
.bqd-tit{font-size:17px;color:#fbb928}
.bqd-g{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.bqd-g>div{background:#1e1e21;border-radius:12px;padding:10px 12px;display:grid}
.bqd-g span{font-size:12px;color:#a3a3ab;text-transform:uppercase}.bqd-g b{font-size:26px}.bqd-g small{font-size:12px;color:#c9c9cf}
.bqd-p{margin:0;font-size:14px;color:#e5e5e7}.bqd-p a{color:#fbb928}
</style>
</head>
<body>
    <?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>

    <main class="page-wrap">
        <section class="bq-card" aria-label="Banco de questões">
            <header class="bq-title">Banco de Questões</header>
            <div class="bq-body">
                <?php
                $cicloBanner = null;
                if ($topicoId > 0 && $pdo instanceof PDO) {
                    try {
                        require_once __DIR__ . '/includes/missao-lib.php';
                        $cfgB = meta_config($pdo);
                        $stB = $pdo->prepare('SELECT mt.titulo, (SELECT COUNT(*) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u AND q.materia_topico_id = mt.id) feitas FROM admin_materia_topicos mt WHERE mt.id = :t');
                        $stB->execute([':u' => (int)$usuarioId, ':t' => $topicoId]);
                        $rowB = $stB->fetch();
                        if ($rowB) {
                            $cicloBanner = ['assunto' => (string)$rowB['titulo'], 'feitas' => (int)$rowB['feitas'], 'min' => max(1, (int)($cfgB['min_questoes'] ?? 5)), 'meta' => max(1, (int)($cfgB['nivel4_questoes'] ?? 50))];
                        }
                    } catch (Throwable $e) {
                        $cicloBanner = null;
                    }
                }
                $revisaoParam = preg_match('/^(\d+)-(\d)$/', (string)($_GET['revisao'] ?? ''), $mRev) ? [(int)$mRev[1], (int)$mRev[2]] : null;
                ?>
                <?php if (($_GET['ok'] ?? '') === 'revisao'): ?><div class="bqc-ok" role="status">🔁 Revisão concluída! Seguimos para o próximo passo da sua missão.</div><?php endif; ?>
                <?php if ($revisaoParam && $cicloBanner): ?>
                <div class="bqc bqc-rev" role="region" aria-label="Revisão">
                    <div><small>🔁 Passo 3 · Revisão <?php echo $revisaoParam[1]; ?>ª</small><strong><?php echo bq_esc($cicloBanner['assunto']); ?></strong><span>Resolva de 3 a 5 questões (pode reler suas marcações da aula) e toque em concluir.</span></div>
                    <form method="post"><input type="hidden" name="acao" value="concluir_revisao"><input type="hidden" name="item_id" value="<?php echo $revisaoParam[0]; ?>"><input type="hidden" name="rodada" value="<?php echo $revisaoParam[1]; ?>"><button type="submit" class="bqc-bt">✓ Concluí a revisão</button></form>
                </div>
                <?php elseif ($cicloBanner): $fb = $cicloBanner; ?>
                <div class="bqc" id="bqCiclo" data-topico="<?php echo $topicoId; ?>" data-min="<?php echo $fb['min']; ?>" data-meta="<?php echo $fb['meta']; ?>" data-feitas="<?php echo $fb['feitas']; ?>" role="region" aria-label="Seu progresso no assunto">
                    <div><small>✅ Passo 2 · Questões comentadas</small><strong><?php echo bq_esc($fb['assunto']); ?></strong>
                    <span id="bqCicloTxt"></span>
                    <span class="bqc-barra" aria-hidden="true"><i id="bqCicloBarra"></i><b style="left:<?php echo (int)round($fb['min'] * 100 / $fb['meta']); ?>%"></b></span></div>
                    <div id="bqCicloAcao" aria-live="polite"></div>
                </div>
                <?php endif; ?>
                <?php if ($erroPagina !== ''): ?>
                    <div class="alert"><?php echo bq_esc($erroPagina); ?></div>
                <?php endif; ?>

                <form class="filters" method="get" id="formFiltros">
                    <span class="filters-label">Filtrar por:</span>
                    <label class="select-wrap is-wide">
                        <select class="filter-select" name="concurso_passado_id" aria-label="Concurso passado">
                            <option value="0">Concurso passado</option>
                            <?php foreach ($concursosPassados as $concurso): ?>
                                <option value="<?php echo (int)$concurso['id']; ?>" <?php echo $concursoPassadoId === (int)$concurso['id'] ? 'selected' : ''; ?>><?php echo bq_esc((string)$concurso['titulo']); ?><?php echo (int)($concurso['ano'] ?? 0) > 0 ? ' • ' . (int)$concurso['ano'] : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="select-wrap is-wide">
                        <select class="filter-select" name="topico_id" id="topicoSelect" aria-label="Tópicos">
                            <option value="0">Tópicos</option>
                            <?php foreach ($topicos as $topico): ?>
                                <option value="<?php echo (int)$topico['id']; ?>" <?php echo $topicoId === (int)$topico['id'] ? 'selected' : ''; ?>><?php echo bq_esc($topico['materia_nome'] . ' - ' . $topico['titulo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="select-wrap is-mid">
                        <select class="filter-select" name="banca_id" aria-label="Banca">
                            <option value="0">Banca</option>
                            <?php foreach ($bancas as $banca): ?>
                                <?php $bancaLabel = trim((string)($banca['sigla'] ?? '')) !== '' ? (string)$banca['sigla'] . ' - ' . (string)$banca['nome'] : (string)$banca['nome']; ?>
                                <option value="<?php echo (int)$banca['id']; ?>" <?php echo $bancaId === (int)$banca['id'] ? 'selected' : ''; ?>><?php echo bq_esc($bancaLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="select-wrap">
                        <select class="filter-select" name="ano" aria-label="Ano">
                            <option value="0">Ano</option>
                            <?php foreach ($anos as $anoItem): ?>
                                <option value="<?php echo (int)$anoItem; ?>" <?php echo $ano === (int)$anoItem ? 'selected' : ''; ?>><?php echo (int)$anoItem; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="select-wrap">
                        <select class="filter-select" name="status" aria-label="Status">
                            <option value="">Status</option>
                            <option value="respondidas" <?php echo $statusFiltro === 'respondidas' ? 'selected' : ''; ?>>Respondidas</option>
                            <option value="nao_respondidas" <?php echo $statusFiltro === 'nao_respondidas' ? 'selected' : ''; ?>>Não respondidas</option>
                            <option value="certas" <?php echo $statusFiltro === 'certas' ? 'selected' : ''; ?>>Certas</option>
                            <option value="erradas" <?php echo $statusFiltro === 'erradas' ? 'selected' : ''; ?>>Erradas</option>
                            <option value="solucao_vista" <?php echo $statusFiltro === 'solucao_vista' ? 'selected' : ''; ?>>Solução vista</option>
                        </select>
                    </label>
                    <input type="hidden" name="pagina" value="1">
                    <input type="hidden" name="por_pagina" value="<?php echo (int)$perPage; ?>">
                </form>

                <?php if (!$questoes && $erroPagina === ''): ?>
                    <div class="empty-box">
                        <div>
                            <h2>Nenhuma questão encontrada</h2>
                            <p>Cadastre questões no painel administrativo, vincule ao acesso correto ou altere os filtros selecionados.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($questoes as $questao): ?>
                    <?php
                        $qid = (int)$questao['id'];
                        $opcoes = $opcoesPorQuestao[$qid] ?? [];
                        $respondidaOpcaoId = (int)($questao['resposta_opcao_id'] ?? 0);
                        $respostaCorreta = isset($questao['resposta_correta']) ? (int)$questao['resposta_correta'] : null;
                        $cortadas = bq_opcoes_cortadas_array($questao['opcoes_cortadas'] ?? '');
                        $solucaoAberta = $respondidaOpcaoId > 0 && (int)($questao['visualizou_solucao'] ?? 0) === 1;
                        $corretaId = 0;
                        $corretaLetra = '';
                        foreach ($opcoes as $opcaoBusca) {
                            if ((int)$opcaoBusca['correta'] === 1) {
                                $corretaId = (int)$opcaoBusca['id'];
                                $corretaLetra = (string)$opcaoBusca['letra'];
                                break;
                            }
                        }
                        $metaPartes = [];
                        $concursoNome = trim((string)($questao['concurso_passado_titulo'] ?? ''));
                        if ($concursoNome !== '') {
                            $labelConcurso = $concursoNome;
                            if (!empty($questao['concurso_passado_ano'])) {
                                $labelConcurso .= ' • ' . (int)$questao['concurso_passado_ano'];
                            }
                            $metaPartes[] = $labelConcurso;
                        }
                        $bancaNome = trim((string)(($questao['banca_sigla'] ?? '') ?: ($questao['banca_nome'] ?? '')));
                        if ($bancaNome !== '') {
                            $metaPartes[] = 'Banca: ' . $bancaNome;
                        }
                        $materiaLinha = (string)($questao['materia_nome'] ?? '');
                        if (!empty($questao['materia_topico_titulo'])) {
                            $materiaLinha .= ' - ' . (string)$questao['materia_topico_titulo'];
                        }
                        if ($materiaLinha !== '') {
                            $metaPartes[] = $materiaLinha;
                        }
                        if (!empty($questao['ano'])) {
                            $metaPartes[] = (string)(int)$questao['ano'];
                        }
                        $topicoLinha = implode(' • ', $metaPartes);
                    ?>
                    <article class="question" data-question-id="<?php echo $qid; ?>" data-correct-option="<?php echo $corretaId; ?>" data-answered="<?php echo $respondidaOpcaoId > 0 ? '1' : '0'; ?>">
                        <div class="question-meta"><?php echo bq_esc($topicoLinha); ?></div>
                        <div class="question-text"><?php echo nl2br(bq_esc($questao['enunciado'] ?? '')); ?></div>

                        <div class="options" aria-label="Alternativas da questão">
                            <?php foreach ($opcoes as $opcao): ?>
                                <?php
                                    $opcaoId = (int)$opcao['id'];
                                    $isCortada = in_array($opcaoId, $cortadas, true);
                                    $isSelected = $respondidaOpcaoId === $opcaoId;
                                    $isCorrectAnswer = $corretaId === $opcaoId;
                                    $classes = ['option-row'];
                                    if ($isCortada) { $classes[] = 'is-cut'; }
                                    if ($isSelected) { $classes[] = 'is-selected'; }
                                    if ($isSelected && $respostaCorreta === 1) { $classes[] = 'is-correct'; }
                                    if ($isSelected && $respostaCorreta === 0) { $classes[] = 'is-wrong'; }
                                    if ($respondidaOpcaoId > 0 && !$isSelected && $isCorrectAnswer) { $classes[] = 'is-answer'; }
                                ?>
                                <div class="<?php echo implode(' ', $classes); ?>" data-option-id="<?php echo $opcaoId; ?>">
                                    <button type="button" class="cut-btn" aria-label="Cortar alternativa <?php echo bq_esc($opcao['letra']); ?>"><i data-lucide="scissors"></i></button>
                                    <button type="button" class="option-letter" aria-label="Selecionar alternativa <?php echo bq_esc($opcao['letra']); ?>"><?php echo bq_esc($opcao['letra']); ?></button>
                                    <button type="button" class="option-text"><?php echo nl2br(bq_esc($opcao['texto'] ?? '')); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="question-actions">
                            <button type="button" class="solution-btn<?php echo $solucaoAberta ? ' is-open' : ''; ?>">
                                <i data-lucide="<?php echo $solucaoAberta ? 'eye-off' : 'eye'; ?>"></i>
                                <span><?php echo $solucaoAberta ? 'Esconder solução' : 'Mostrar solução'; ?></span>
                            </button>
                            <a class="duvidas" href="forum-duvidas.php?questao_id=<?php echo $qid; ?>">Dúvidas</a>
                            <span class="save-msg" aria-live="polite"></span>
                        </div>

                        <div class="solution-box<?php echo $solucaoAberta ? ' is-open' : ''; ?>">
                            <h3>Resolução</h3>
                            <p><strong>GABARITO:</strong> <?php echo bq_esc($corretaLetra !== '' ? $corretaLetra : '-'); ?></p>
                            <?php if (trim((string)($questao['explicacao'] ?? '')) !== ''): ?>
                                <p><strong>COMENTÁRIO:</strong> <?php echo nl2br(bq_esc($questao['explicacao'])); ?></p>
                            <?php else: ?>
                                <p><strong>COMENTÁRIO:</strong> Explicação ainda não cadastrada para esta questão.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if ($questoes): ?>
                    <footer class="pagination" aria-label="Paginação das questões">
                        <div></div>
                        <div class="pager-center">
                            <div class="pager-title">Você está na página</div>
                            <div class="pager-controls">
                                <a class="pager-btn<?php echo $page <= 1 ? ' is-disabled' : ''; ?>" href="<?php echo bq_esc(bq_build_url(['pagina' => max(1, $page - 1)])); ?>" aria-label="Página anterior"><i data-lucide="chevron-left"></i></a>
                                <span class="pager-number"><?php echo (int)$page; ?></span>
                                <span class="pager-divider" aria-hidden="true"></span>
                                <span class="pager-number"><?php echo (int)$totalPaginas; ?></span>
                                <a class="pager-btn<?php echo $page >= $totalPaginas ? ' is-disabled' : ''; ?>" href="<?php echo bq_esc(bq_build_url(['pagina' => min($totalPaginas, $page + 1)])); ?>" aria-label="Próxima página"><i data-lucide="chevron-right"></i></a>
                            </div>
                        </div>
                        <nav class="per-page" aria-label="Questões por página">
                            <?php foreach ([10, 30, 50] as $pp): ?>
                                <a href="<?php echo bq_esc(bq_build_url(['por_pagina' => $pp, 'pagina' => 1])); ?>" class="<?php echo $perPage === $pp ? 'is-active' : ''; ?>"><?php echo $pp; ?></a>
                            <?php endforeach; ?>
                            <span>por página</span>
                        </nav>
                    </footer>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        (function(){
            if (window.lucide) window.lucide.createIcons();

            const form = document.getElementById('formFiltros');

            if (form) {
                form.querySelectorAll('select').forEach(function(select){
                    select.addEventListener('change', function(){
                        form.submit();
                    });
                });
            }

            async function postAction(data){
                const endpoint = window.location.pathname || 'banco-questoes.php';
                const response = await fetch(endpoint, {
                    method:'POST',
                    headers:{
                        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With':'XMLHttpRequest',
                        'Accept':'application/json'
                    },
                    body:new URLSearchParams(data).toString(),
                    credentials:'same-origin',
                    redirect:'follow'
                });

                const contentType = response.headers.get('content-type') || '';
                const text = await response.text();

                if (!contentType.includes('application/json')) {
                    throw new Error('Não foi possível salvar. Atualize a página e tente novamente.');
                }

                try {
                    return JSON.parse(text);
                } catch (error) {
                    throw new Error('Resposta inválida do servidor.');
                }
            }

            function setMsg(question, text, erro){
                const msg = question.querySelector('.save-msg');
                if (!msg) return;
                msg.textContent = text || '';
                msg.style.color = erro ? '#b91c1c' : '#6b7280';
                if (text) {
                    clearTimeout(msg._timer);
                    msg._timer = setTimeout(function(){ msg.textContent = ''; }, 2600);
                }
            }

            document.querySelectorAll('.question').forEach(function(question){
                const questionId = question.dataset.questionId;
                const correctOption = question.dataset.correctOption;

                question.querySelectorAll('.cut-btn').forEach(function(btn){
                    btn.addEventListener('click', async function(){
                        const row = btn.closest('.option-row');
                        if (!row) return;
                        const willCut = !row.classList.contains('is-cut');
                        row.classList.toggle('is-cut', willCut);
                        setMsg(question, 'Salvando...', false);
                        try {
                            const json = await postAction({acao:'salvar_corte', questao_id:questionId, opcao_id:row.dataset.optionId, cortada:willCut ? '1' : '0'});
                            if (!json.ok) throw new Error(json.mensagem || 'Erro ao salvar.');
                            setMsg(question, willCut ? 'Alternativa cortada.' : 'Corte removido.', false);
                        } catch (e) {
                            row.classList.toggle('is-cut', !willCut);
                            setMsg(question, e.message || 'Erro ao salvar.', true);
                        }
                    });
                });

                question.querySelectorAll('.option-letter,.option-text').forEach(function(btn){
                    btn.addEventListener('click', async function(){
                        const row = btn.closest('.option-row');
                        if (!row) return;
                        setMsg(question, 'Salvando resposta...', false);
                        try {
                            const json = await postAction({acao:'salvar_resposta', questao_id:questionId, opcao_id:row.dataset.optionId});
                            if (!json.ok) throw new Error(json.mensagem || 'Erro ao salvar resposta.');
                            question.dataset.answered = '1';
                            question.querySelectorAll('.option-row').forEach(function(item){
                                item.classList.remove('is-selected','is-correct','is-wrong','is-answer');
                                if (item.dataset.optionId === String(json.opcao_correta_id)) {
                                    item.classList.add('is-answer');
                                }
                            });
                            row.classList.add('is-selected');
                            row.classList.toggle('is-correct', !!json.correta);
                            row.classList.toggle('is-wrong', !json.correta);
                            setMsg(question, json.correta ? 'Resposta correta salva.' : 'Resposta salva. Veja o gabarito.', false);
                        } catch (e) {
                            setMsg(question, e.message || 'Erro ao salvar resposta.', true);
                        }
                    });
                });

                const solutionBtn = question.querySelector('.solution-btn');
                const solutionBox = question.querySelector('.solution-box');
                if (solutionBtn && solutionBox) {
                    solutionBtn.addEventListener('click', async function(){
                        if (question.dataset.answered !== '1' && !question.querySelector('.option-row.is-selected')) {
                            setMsg(question, 'Escolha uma alternativa antes de ver a solução.', true);
                            return;
                        }

                        const willOpen = !solutionBox.classList.contains('is-open');
                        solutionBox.classList.toggle('is-open', willOpen);
                        solutionBtn.classList.toggle('is-open', willOpen);
                        solutionBtn.innerHTML = willOpen ? '<i data-lucide="eye-off"></i><span>Esconder solução</span>' : '<i data-lucide="eye"></i><span>Mostrar solução</span>';
                        if (window.lucide) window.lucide.createIcons();

                        try {
                            const json = await postAction({acao:'salvar_solucao', questao_id:questionId, visivel:willOpen ? '1' : '0'});
                            if (!json.ok) throw new Error(json.mensagem || 'Erro ao salvar solução.');
                        } catch (e) {
                            solutionBox.classList.toggle('is-open', !willOpen);
                            solutionBtn.classList.toggle('is-open', !willOpen);
                            solutionBtn.innerHTML = !willOpen ? '<i data-lucide="eye-off"></i><span>Esconder solução</span>' : '<i data-lucide="eye"></i><span>Mostrar solução</span>';
                            if (window.lucide) window.lucide.createIcons();
                            setMsg(question, e.message || 'Erro ao salvar solução.', true);
                        }
                    });
                }
            });
        })();
    </script>
<script>
// Contador do ciclo: "x de 5 para liberar" e comemoração ao liberar o próximo assunto.
(function () {
    var box = document.getElementById('bqCiclo');
    if (!box) { return; }
    var min = +box.dataset.min, meta = +box.dataset.meta, feitas = +box.dataset.feitas, topico = box.dataset.topico;
    var txt = document.getElementById('bqCicloTxt'), barra = document.getElementById('bqCicloBarra'), acao = document.getElementById('bqCicloAcao');
    function pintar() {
        txt.textContent = feitas < min ? feitas + ' de ' + min + ' para liberar o próximo assunto · meta ' + meta : feitas + ' de ' + meta + ' para dominar o assunto · próximo assunto liberado ✓';
        barra.style.width = Math.min(100, feitas * 100 / meta) + '%';
        box.classList.toggle('liberou', feitas >= min);
    }
    pintar();
    // Desempenho e evolução depois das questões do ciclo
    function mostrarDesempenho(d, primeiraVez) {
        var el = document.getElementById('bqDesempenho');
        if (!el) { el = document.createElement('div'); el.id = 'bqDesempenho'; el.className = 'bqd'; el.setAttribute('role', 'status'); box.insertAdjacentElement('afterend', el); }
        var a = d.assunto, m = d.materia, g = d.geral;
        var cor = function (p) { return p >= 70 ? '#4ade80' : (p >= 50 ? '#fbb928' : '#ff8a80'); };
        var evol = g.antes_pct === null ? 'Primeira semana: sua base está sendo formada.' : (g.semana_pct >= g.antes_pct ? '📈 Nesta semana você acertou ' + g.semana_pct + '%, contra ' + g.antes_pct + '% antes. Evoluindo!' : '📉 Nesta semana ' + g.semana_pct + '% (antes ' + g.antes_pct + '%). Revise os erros nos Flashcards.');
        var dica = a.pct >= 70 ? 'Mandou bem! Siga para o próximo assunto.' : 'Antes de seguir, revise os erros: eles já estão nos seus Flashcards.';
        el.innerHTML = '<strong class="bqd-tit">' + (primeiraVez ? '🎯 Missão cumprida neste assunto!' : '📊 Seu desempenho') + '</strong>'
            + '<div class="bqd-g">'
            + '<div><span>Neste assunto</span><b style="color:' + cor(a.pct) + '">' + a.pct + '%</b><small>' + a.acertos + ' de ' + a.feitas + ' · nível ' + a.nivel + '</small></div>'
            + (m.nome ? '<div><span>' + m.nome + '</span><b style="color:' + cor(m.pct) + '">' + m.pct + '%</b><small>' + m.feitas + ' questões na matéria</small></div>' : '')
            + '<div><span>Geral</span><b style="color:' + cor(g.pct) + '">' + g.pct + '%</b><small>' + g.feitas + ' questões no total</small></div></div>'
            + '<p class="bqd-p">' + evol + '</p>'
            + (a.proximo_nivel ? '<p class="bqd-p">⬆ Próximo nível neste assunto: <b>' + a.proximo_nivel + '</b> (mais ' + a.faltam_proximo + ' questões com ' + (a.proximo_acerto || 0) + '% de acerto).</p>' : '<p class="bqd-p">🏆 Assunto dominado!</p>')
            + '<p class="bqd-p">' + dica + ' <a href="flashcards.php">Flashcards</a> · <a href="meu-plano.php">Minha evolução</a></p>';
    }
    var _fetch = window.fetch;
    window.fetch = function () {
        return _fetch.apply(this, arguments).then(function (r) {
            try {
                r.clone().json().then(function (j) {
                    if (!j || !j.ciclo || String(j.ciclo.topico_id) !== String(topico)) { return; }
                    var antes = feitas; feitas = j.ciclo.feitas; pintar();
                    if (j.ciclo.desempenho && feitas >= min) { mostrarDesempenho(j.ciclo.desempenho, antes < min); }
                    if (antes < min && feitas >= min) {
                        var p = j.ciclo.proximo;
                        acao.innerHTML = '<strong style="color:#4ade80">🔓 Próximo assunto liberado!</strong> ' + (p ? '<a class="bqc-bt" href="' + p.url + '">▶ ' + (p.acao || 'Continuar') + (p.assunto ? ': ' + p.assunto.replace(/[<>&"]/g, '') : '') + '</a>' : '');
                        var a = acao.querySelector('a'); if (a) { a.focus(); }
                        var msb = document.querySelector('.msb');
                        if (msb && p) { msb.querySelector('.msb-txt span').textContent = '🔓 Liberado! Próximo: ' + p.assunto; msb.querySelector('.msb-bt').setAttribute('href', p.url); }
                    }
                }).catch(function () {});
            } catch (e) {}
            return r;
        });
    };
})();
</script>
</body>
</html>
