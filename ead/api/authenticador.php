<?php
// api/authenticador.php
// Autenticador reutilizável do Projeto Gabaritando.
// Quando este arquivo é incluído nas páginas, ele só disponibiliza as funções.
// Quando é chamado diretamente por POST, ele processa o login normal.
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguranca.php';
gab_seg_post_mesma_origem();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';

if (!function_exists('gab_redirect')) {
    function gab_redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('gab_safe_redirect')) {
    function gab_safe_redirect(string $redirect, string $default = '../meu-plano.php'): string
    {
        $redirect = trim($redirect);

        if ($redirect === '' || strpos($redirect, '://') !== false || str_starts_with($redirect, '//')) {
            return $default;
        }

        if (str_contains($redirect, "\n") || str_contains($redirect, "\r")) {
            return $default;
        }

        if (str_starts_with($redirect, '/')) {
            return $redirect;
        }

        if (str_starts_with($redirect, '../')) {
            return $redirect;
        }

        return '../' . ltrim($redirect, '/');
    }
}

if (!function_exists('gab_login_user')) {
    function gab_login_user(array $user): void
    {
        session_regenerate_id(true);

        $usuario = [
            'id' => (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 0),
            'nome' => (string)($user['nome'] ?? $user['nome_usuario'] ?? $user['usuario'] ?? 'Usuário'),
            'email' => (string)($user['email'] ?? ''),
            'tipo' => (string)($user['tipo'] ?? 'cliente'),
        ];

        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
    }
}

if (!function_exists('auth_user_id')) {
    function auth_user_id(): int
    {
        if (!empty($_SESSION['usuario']) && is_array($_SESSION['usuario']) && !empty($_SESSION['usuario']['id'])) {
            return (int)$_SESSION['usuario']['id'];
        }

        foreach (['usuario_id', 'id_usuario', 'user_id', 'cliente_id', 'aluno_id'] as $chave) {
            if (!empty($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        foreach (['hobbi_user', 'user', 'cliente', 'aluno'] as $chave) {
            if (!empty($_SESSION[$chave]) && is_array($_SESSION[$chave]) && !empty($_SESSION[$chave]['id'])) {
                return (int)$_SESSION[$chave]['id'];
            }
        }

        return 0;
    }
}

if (!function_exists('auth_is_logged')) {
    function auth_is_logged(): bool
    {
        return auth_user_id() > 0;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        if (!auth_is_logged()) {
            return null;
        }

        if (!empty($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
            return $_SESSION['usuario'];
        }

        $id = auth_user_id();
        $nome = (string)($_SESSION['usuario_nome'] ?? $_SESSION['hobbi_user_nome'] ?? 'Usuário');
        $email = (string)($_SESSION['usuario_email'] ?? '');
        $tipo = (string)($_SESSION['usuario_tipo'] ?? 'cliente');

        $_SESSION['usuario'] = [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'tipo' => $tipo,
        ];

        return $_SESSION['usuario'];
    }
}

if (!function_exists('auth_login_url')) {
    function auth_login_url(): string
    {
        $current = (string)($_SERVER['REQUEST_URI'] ?? '/dashboard.php');
        if ($current === '' || str_contains($current, '://')) {
            $current = '/dashboard.php';
        }

        return 'login.php?redirect=' . rawurlencode($current);
    }
}

if (!function_exists('auth_require_login')) {
    function auth_require_login(): void
    {
        if (auth_is_logged()) {
            return;
        }

        gab_redirect(auth_login_url());
    }
}

$executadoDiretamente = isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__;

if (!$executadoDiretamente) {
    return;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    gab_redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gab_redirect('../login.php');
}

$email = strtolower(trim((string)($_POST['email'] ?? $_POST['login'] ?? '')));
$senha = (string)($_POST['senha'] ?? $_POST['password'] ?? '');
$redirect = (string)($_POST['redirect'] ?? 'meu-plano.php');

if ($email === '' || $senha === '') {
    gab_redirect('../login.php?erro=campos&email=' . urlencode($email));
}
if (gab_seg_login_bloqueado() > 0) {
    gab_redirect('../login.php?erro=login&email=' . urlencode($email));
}

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, senha, tipo, status FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $senhaBanco = (string)($user['senha_hash'] ?? $user['senha'] ?? '');
    $senhaOk = false;

    if ($senhaBanco !== '') {
        $senhaOk = password_verify($senha, $senhaBanco)
            || hash_equals($senhaBanco, $senha)
            || hash_equals(strtolower($senhaBanco), md5($senha))
            || hash_equals(strtolower($senhaBanco), sha1($senha));
    }

    if (!$user || !$senhaOk) {
        gab_seg_login_erro();
        gab_redirect('../login.php?erro=login&email=' . urlencode($email));
    }

    if (($user['status'] ?? 'ativo') !== 'ativo') {
        gab_redirect('../login.php?erro=bloqueado&email=' . urlencode($email));
    }

    gab_login_user($user);

    try {
        $update = $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id LIMIT 1');
        $update->execute([':id' => (int)$user['id']]);
    } catch (Throwable $ignored) {
        // O login não deve quebrar se a coluna não existir em algum banco antigo.
    }

    gab_redirect(gab_safe_redirect($redirect));
} catch (Throwable $e) {
    error_log('[Projeto Gabaritando] Erro no login: ' . $e->getMessage());
    gab_redirect('../login.php?erro=interno&email=' . urlencode($email));
}
