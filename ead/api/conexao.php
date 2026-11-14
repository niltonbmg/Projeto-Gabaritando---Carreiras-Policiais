<?php
// api/conexao.php
// Conexão principal do Projeto Gabaritando.
// Ajustado para rodar dentro de /ead e ler o .env local.
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once dirname(__DIR__) . '/includes/seguranca.php';
gab_seg_post_mesma_origem();

if (!function_exists('gab_starts_with')) {
    function gab_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('gab_load_env')) {
    function gab_load_env(string $path): array
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
            if ($line === '' || gab_starts_with($line, '#') || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($key !== '') {
                $vars[$key] = $value;
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        return $vars;
    }
}

if (!function_exists('gab_env')) {
    function gab_env(string $key, string $default = ''): string
    {
        static $env = null;
        if ($env === null) {
            $env = gab_load_env(__DIR__ . '/../.env');
        }

        if (isset($env[$key]) && $env[$key] !== '') {
            return (string)$env[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }

        return $default;
    }
}

if (!function_exists('gab_db_config')) {
    function gab_db_config(): array
    {
        return [
            'host' => gab_env('DB_HOST', 'localhost'),
            'port' => gab_env('DB_PORT', '3306'),
            'database' => gab_env('DB_DATABASE', 'u331992459_gabaritando'),
            'username' => gab_env('DB_USERNAME', 'u331992459_gabaritando'),
            'password' => gab_env('DB_PASSWORD', ''),
            'charset' => gab_env('DB_CHARSET', 'utf8mb4'),
            'timezone' => gab_env('DB_TIMEZONE', '-03:00'),
        ];
    }
}

if (!function_exists('gab_pdo_connect')) {
    function gab_pdo_connect(string $host, string $database = ''): PDO
    {
        $cfg = gab_db_config();
        $charset = (string)$cfg['charset'];
        $dsn = "mysql:host={$host};port={$cfg['port']};";
        if ($database !== '') {
            $dsn .= "dbname={$database};";
        }
        $dsn .= "charset={$charset}";

        $pdo = new PDO($dsn, (string)$cfg['username'], (string)$cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET NAMES {$charset}");
        $pdo->exec('SET time_zone = ' . $pdo->quote((string)$cfg['timezone']));

        return $pdo;
    }
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $cfg = gab_db_config();
        $host = (string)$cfg['host'];
        $database = (string)$cfg['database'];

        try {
            $pdo = gab_pdo_connect($host, $database);
            return $pdo;
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha conexão MySQL: ' . $e->getMessage());
            throw new RuntimeException('Falha na conexão com o banco de dados. Confira usuário, senha e permissões no hPanel.', 0, $e);
        }
    }
}
