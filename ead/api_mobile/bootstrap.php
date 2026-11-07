<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function json_out(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: [];
}

function table_exists(PDO $pdo, string $table): bool
{
    $table = str_replace('`', '', trim($table));
    if ($table === '') {
        return false;
    }

    try {
        // SHOW TABLES LIKE com parâmetro preparado falha em alguns servidores MySQL/MariaDB.
        // Por isso usamos INFORMATION_SCHEMA e, se necessário, fazemos fallback com SHOW TABLES.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (Throwable $e) {
        error_log('[API Mobile] Falha no information_schema para tabela ' . $table . ': ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->query('SHOW TABLES');
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
            if (isset($row[0]) && strtolower((string)$row[0]) === strtolower($table)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        error_log('[API Mobile] Falha ao verificar tabela ' . $table . ': ' . $e->getMessage());
    }

    return false;
}

function table_cols(PDO $pdo, string $table): array
{
    static $cache = [];
    $table = str_replace('`', '', trim($table));
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!table_exists($pdo, $table)) {
        return $cache[$table] = [];
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[(string)$row['Field']] = $row;
        }
        return $cache[$table] = $cols;
    } catch (Throwable $e) {
        error_log('[API Mobile] Falha ao listar colunas da tabela ' . $table . ': ' . $e->getMessage());
        return $cache[$table] = [];
    }
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = table_cols($pdo, $table);
    if (isset($cols[$column])) {
        return;
    }

    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $pdo->exec('ALTER TABLE `' . $safeTable . '` ADD COLUMN `' . $safeColumn . '` ' . $definition);
}

function first_col(array $cols, array $names): ?string
{
    foreach ($names as $name) {
        if (isset($cols[$name])) {
            return $name;
        }
    }
    return null;
}

function bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos((string)$header, 'Bearer ') === 0) {
        return trim(substr((string)$header, 7));
    }
    return trim((string)($_GET['token'] ?? ''));
}

function api_compact_login(string $value): string
{
    $value = strtolower(trim($value));
    return str_replace([' ', '.', '-', '_', '/', '\\', '(', ')'], '', $value);
}

function normalize_user(PDO $pdo, array $row): array
{
    $cols = table_cols($pdo, 'usuarios');
    $nameCol = first_col($cols, ['nome', 'name', 'usuario', 'login', 'email']);
    $emailCol = first_col($cols, ['email', 'usuario_email']);
    $typeCol = first_col($cols, ['tipo', 'perfil', 'role', 'nivel', 'acesso', 'grupo']);
    $blockedCol = first_col($cols, ['bloqueado', 'blocked']);
    $statusCol = first_col($cols, ['status']);
    $activeCol = first_col($cols, ['ativo', 'active']);

    $tipoRaw = $typeCol ? strtolower(trim((string)($row[$typeCol] ?? 'cliente'))) : 'cliente';
    $adminValues = ['admin', 'administrador', 'adm', '1', 'master', 'superadmin'];
    $tipo = in_array($tipoRaw, $adminValues, true) ? 'admin' : 'cliente';
    if (isset($row['is_admin']) && (int)$row['is_admin'] === 1) {
        $tipo = 'admin';
    }

    $bloqueado = false;
    if ($blockedCol) {
        $bloqueado = (int)($row[$blockedCol] ?? 0) === 1;
    }
    if ($statusCol) {
        $status = strtolower(trim((string)($row[$statusCol] ?? '')));
        if (in_array($status, ['0', 'bloqueado', 'inativo', 'suspenso', 'banido'], true)) {
            $bloqueado = true;
        }
    }
    if ($activeCol && (string)($row[$activeCol] ?? '') === '0') {
        $bloqueado = true;
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'nome' => $nameCol ? (string)($row[$nameCol] ?? 'Usuário') : 'Usuário',
        'email' => $emailCol ? (string)($row[$emailCol] ?? '') : '',
        'tipo' => $tipo,
        'bloqueado' => $bloqueado,
    ];
}

function ensure_app_tokens_table(PDO $pdo): bool
{
    try {
        if (!table_exists($pdo, 'app_tokens')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `app_tokens` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `token` VARCHAR(128) NOT NULL,
                `user_agent` VARCHAR(255) NULL,
                `ip_address` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `expires_at` DATETIME NOT NULL,
                `revoked_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `app_tokens_token_unique` (`token`),
                KEY `app_tokens_user_id_index` (`user_id`),
                KEY `app_tokens_expires_at_index` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!table_exists($pdo, 'app_tokens')) {
            return false;
        }

        // Garante compatibilidade caso a tabela tenha sido criada por um SQL antigo.
        add_column_if_missing($pdo, 'app_tokens', 'user_agent', 'VARCHAR(255) NULL AFTER `token`');
        add_column_if_missing($pdo, 'app_tokens', 'ip_address', 'VARCHAR(45) NULL AFTER `user_agent`');
        add_column_if_missing($pdo, 'app_tokens', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        add_column_if_missing($pdo, 'app_tokens', 'expires_at', 'DATETIME NOT NULL');
        add_column_if_missing($pdo, 'app_tokens', 'revoked_at', 'DATETIME NULL');

        return true;
    } catch (Throwable $e) {
        error_log('[API Mobile] Falha ao criar/ajustar app_tokens: ' . $e->getMessage());
        return false;
    }
}

function require_app_tokens_table(PDO $pdo): void
{
    if (!ensure_app_tokens_table($pdo)) {
        json_out(false, ['message' => 'Tabela app_tokens não existe. Importe o SQL app_tokens.sql no banco.'], 500);
    }
}

$connectionPaths = [
    __DIR__ . '/../api/conexao.php',
    __DIR__ . '/../conexao.php',
    __DIR__ . '/conexao.php',
];

$pdo = null;
$loadedConnection = false;
$connectionError = '';

foreach ($connectionPaths as $path) {
    if (!is_file($path)) {
        continue;
    }

    try {
        require_once $path;
        $loadedConnection = true;

        if ((!isset($pdo) || !$pdo instanceof PDO) && function_exists('db')) {
            $pdo = db();
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            break;
        }
    } catch (Throwable $e) {
        $connectionError = $e->getMessage();
        error_log('[API Mobile] Falha ao carregar conexão ' . $path . ': ' . $e->getMessage());
    }
}

if (!$loadedConnection || !$pdo instanceof PDO) {
    json_out(false, [
        'message' => 'Conexão com o banco não encontrada. Ajuste api_mobile/bootstrap.php ou ead/api/conexao.php.',
        'details' => $connectionError,
    ], 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function auth_user(PDO $pdo): array
{
    $token = bearer_token();
    if ($token === '') {
        json_out(false, ['message' => 'Token ausente. Faça login novamente.'], 401);
    }

    require_app_tokens_table($pdo);

    $stmt = $pdo->prepare('SELECT user_id FROM app_tokens WHERE token = ? AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $userId = (int)$stmt->fetchColumn();
    if ($userId <= 0) {
        json_out(false, ['message' => 'Sessão expirada. Entre novamente.'], 401);
    }

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_out(false, ['message' => 'Usuário não encontrado.'], 401);
    }

    $normal = normalize_user($pdo, $user);
    if ($normal['bloqueado']) {
        json_out(false, ['message' => 'Usuário bloqueado. Fale com o administrador.'], 403);
    }

    return $user;
}

function require_admin(PDO $pdo): array
{
    $user = auth_user($pdo);
    $normal = normalize_user($pdo, $user);
    if (($normal['tipo'] ?? 'cliente') !== 'admin') {
        json_out(false, ['message' => 'Acesso permitido apenas para administrador.'], 403);
    }
    return $user;
}
