<?php
// api/admin-auth.php
// Autenticador das páginas administrativas.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguranca.php';
gab_seg_post_mesma_origem();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ADMIN_SESSION_LOGGED_KEY')) {
    define('ADMIN_SESSION_LOGGED_KEY', 'admin_logado');
}

if (!defined('ADMIN_SESSION_LOGIN_KEY')) {
    define('ADMIN_SESSION_LOGIN_KEY', 'admin_login');
}

if (!function_exists('auth_admin_no_cache')) {
    function auth_admin_no_cache(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }
    }
}

if (!function_exists('auth_admin_base_path')) {
    function auth_admin_base_path(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');

        if ($dir === '' || $dir === '/') {
            return '';
        }

        return '/' . trim($dir, '/');
    }
}

if (!function_exists('auth_admin_strip_base')) {
    function auth_admin_strip_base(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = auth_admin_base_path();
        $baseName = trim($base, '/');

        if ($base !== '') {
            while ($path === $base || str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base));
                $path = '/' . ltrim($path, '/');
            }
        }

        // Também remove repetições como ead/ead/ quando vierem de um redirect antigo.
        if ($baseName !== '') {
            $relative = ltrim($path, '/');
            while ($relative === $baseName || str_starts_with($relative, $baseName . '/')) {
                $relative = substr($relative, strlen($baseName));
                $relative = ltrim($relative, '/');
            }
            $path = '/' . $relative;
        }

        return ltrim($path, '/');
    }
}

if (!function_exists('auth_admin_url')) {
    function auth_admin_url(string $path = ''): string
    {
        $path = trim($path);
        if ($path === '') {
            return auth_admin_base_path() !== '' ? auth_admin_base_path() . '/' : '/';
        }

        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '//')) {
            return $path;
        }

        $fragment = '';
        if (str_contains($path, '#')) {
            [$path, $fragmentPart] = explode('#', $path, 2);
            $fragment = '#' . $fragmentPart;
        }

        $query = '';
        if (str_contains($path, '?')) {
            [$path, $queryPart] = explode('?', $path, 2);
            $query = '?' . $queryPart;
        }

        $relative = auth_admin_strip_base($path);
        $base = auth_admin_base_path();
        $url = ($base !== '' ? $base : '') . '/' . ltrim($relative, '/');
        $url = preg_replace('~/+~', '/', $url) ?: $url;

        return $url . $query . $fragment;
    }
}

if (!function_exists('auth_admin_redirect')) {
    function auth_admin_redirect(string $path): void
    {
        $location = str_starts_with($path, '/') ? $path : auth_admin_url($path);
        if (!headers_sent()) {
            header('Location: ' . $location);
        }
        exit;
    }
}


if (!function_exists('auth_admin_lower')) {
    function auth_admin_lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}

if (!function_exists('auth_admin_is_logged')) {
    function auth_admin_is_logged(): bool
    {
        $tipo = auth_admin_lower(trim((string)($_SESSION['usuario_tipo'] ?? $_SESSION['admin_tipo'] ?? '')));

        if (!empty($_SESSION[ADMIN_SESSION_LOGGED_KEY]) && (!empty($_SESSION['admin_usuario_id']) || !empty($_SESSION[ADMIN_SESSION_LOGIN_KEY]))) {
            return true;
        }

        return $tipo === 'admin' && (!empty($_SESSION['admin_usuario_id']) || !empty($_SESSION['usuario_id']));
    }
}

if (!function_exists('auth_admin_user')) {
    function auth_admin_user(): array
    {
        return [
            'id' => (int)($_SESSION['admin_usuario_id'] ?? $_SESSION['usuario_id'] ?? 0),
            'login' => (string)($_SESSION[ADMIN_SESSION_LOGIN_KEY] ?? $_SESSION['usuario_email'] ?? 'Admin'),
            'nome' => (string)($_SESSION['admin_nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION[ADMIN_SESSION_LOGIN_KEY] ?? 'Admin'),
            'email' => (string)($_SESSION['usuario_email'] ?? ''),
        ];
    }
}

if (!function_exists('auth_admin_current_uri')) {
    function auth_admin_current_uri(): string
    {
        $uri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));

        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($uri);
            if ($decoded === $uri) {
                break;
            }
            $uri = $decoded;
        }

        if ($uri === '' || strlen($uri) > 300 || str_contains($uri, '://') || str_starts_with($uri, '//')) {
            return 'admin-dashboard';
        }

        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return 'admin-dashboard';
        }

        $path = auth_admin_strip_base((string)($parts['path'] ?? ''));
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, 'redirect=')) {
            return 'admin-dashboard';
        }

        $pathLower = strtolower($path);
        $pathSemPhp = preg_replace('/\.php$/', '', $pathLower) ?: $pathLower;
        if (in_array($pathSemPhp, ['admin-wp', 'admin-logout', 'login', 'cadastro'], true)) {
            return 'admin-dashboard';
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return $path . $query;
    }
}

if (!function_exists('auth_admin_require_login')) {
    function auth_admin_require_login(): void
    {
        auth_admin_no_cache();

        if (auth_admin_is_logged()) {
            return;
        }

        $redirect = auth_admin_current_uri();
        auth_admin_redirect('admin-wp?redirect=' . rawurlencode($redirect));
    }
}
?>
