<?php
// admin-wp.php
// Tela de login administrativo: somente login e senha, sem cadastro.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/api/admin-auth.php';

if (!function_exists('esc')) {
    function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_login_safe_redirect')) {
    function admin_login_safe_redirect(string $redirect, string $default = 'admin-dashboard'): string
    {
        $redirect = trim($redirect);

        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($redirect);
            if ($decoded === $redirect) {
                break;
            }
            $redirect = $decoded;
        }

        if ($redirect === '' || strlen($redirect) > 240 || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
            return $default;
        }

        $parts = parse_url($redirect);
        if (!is_array($parts)) {
            return $default;
        }

        $path = auth_admin_strip_base((string)($parts['path'] ?? ''));
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($redirect, 'redirect=')) {
            return $default;
        }

        $pathLower = strtolower($path);
        $pathSemPhp = preg_replace('/\.php$/', '', $pathLower) ?: $pathLower;
        if (in_array($pathSemPhp, ['admin-wp', 'admin-logout', 'login', 'cadastro'], true)) {
            return $default;
        }

        // Mantém a URL amigável e impede ead/ead ao montar o Location.
        $path = preg_replace('/\.php$/i', '', $path) ?: $path;
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $path . $query;
    }
}

if (!function_exists('admin_login_location')) {
    function admin_login_location(string $redirect): string
    {
        return auth_admin_url(admin_login_safe_redirect($redirect));
    }
}

if (!function_exists('admin_login_is_logged')) {
    function admin_login_is_logged(): bool
    {
        $tipoAdmin = auth_admin_lower(trim((string)($_SESSION['usuario_tipo'] ?? $_SESSION['admin_tipo'] ?? ''))) === 'admin';

        if (!empty($_SESSION['admin_logado']) && (!empty($_SESSION['admin_usuario_id']) || !empty($_SESSION['admin_login']))) {
            return true;
        }

        return $tipoAdmin && (!empty($_SESSION['admin_usuario_id']) || !empty($_SESSION['usuario_id']));
    }
}

if (!function_exists('admin_login_columns')) {
    function admin_login_columns(PDO $pdo, string $table): array
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

if (!function_exists('admin_login_has_column')) {
    function admin_login_has_column(array $columns, string $column): bool
    {
        return in_array($column, $columns, true);
    }
}

if (!function_exists('admin_login_compact_text')) {
    function admin_login_compact_text(string $value): string
    {
        $value = auth_admin_lower(trim($value));
        return str_replace([' ', '.', '-', '_', '/', '\\'], '', $value);
    }
}

if (!function_exists('admin_login_db')) {
    function admin_login_db(): ?PDO
    {
        try {
            $conexao = __DIR__ . '/api/conexao.php';
            if (is_file($conexao)) {
                require_once $conexao;
            }

            if (function_exists('db')) {
                $pdo = db();
                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            }

            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                return $pdo;
            }
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao conectar login admin: ' . $e->getMessage());
        }

        return null;
    }
}

if (!function_exists('admin_login_find_user')) {
    function admin_login_find_user(PDO $pdo, string $login): ?array
    {
        $columns = admin_login_columns($pdo, 'usuarios');
        if (!$columns) {
            return null;
        }

        $loginColumns = ['email', 'login', 'usuario', 'username', 'apelido', 'nome', 'nome_usuario'];
        $where = [];
        $params = [];

        foreach ($loginColumns as $column) {
            if (!admin_login_has_column($columns, $column)) {
                continue;
            }

            $safeColumn = str_replace('`', '``', $column);
            $where[] = "LOWER(TRIM(`{$safeColumn}`)) = LOWER(TRIM(?))";
            $params[] = $login;
            $where[] = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`{$safeColumn}`), ' ', ''), '.', ''), '-', ''), '_', ''), '/', '')) = LOWER(?)";
            $params[] = admin_login_compact_text($login);
        }

        if (!$where) {
            return null;
        }

        $sql = 'SELECT * FROM usuarios WHERE (' . implode(' OR ', $where) . ') LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}

if (!function_exists('admin_login_password_matches')) {
    function admin_login_password_matches(string $stored, string $password): bool
    {
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }

        if (password_verify($password, $stored)) {
            return true;
        }

        if (hash_equals($stored, $password)) {
            return true;
        }

        if (hash_equals(strtolower($stored), md5($password))) {
            return true;
        }

        if (hash_equals(strtolower($stored), sha1($password))) {
            return true;
        }

        if (hash_equals($stored, crypt($password, $stored))) {
            return true;
        }

        return false;
    }
}

if (!function_exists('admin_login_user_password_ok')) {
    function admin_login_user_password_ok(array $user, string $password): bool
    {
        foreach (['senha_hash', 'password_hash', 'senha', 'password', 'senha_usuario'] as $column) {
            if (isset($user[$column]) && admin_login_password_matches((string)$user[$column], $password)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('admin_login_user_is_admin')) {
    function admin_login_user_is_admin(array $user): bool
    {
        foreach (['tipo', 'nivel', 'perfil', 'role', 'grupo'] as $column) {
            if (!isset($user[$column])) {
                continue;
            }

            $value = mb_strtolower(trim((string)$user[$column]), 'UTF-8');
            if (in_array($value, ['admin', 'administrador', 'adm', '1', 'master', 'superadmin'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('admin_login_user_is_active')) {
    function admin_login_user_is_active(array $user): bool
    {
        if (!isset($user['status']) || (string)$user['status'] === '') {
            return true;
        }

        $status = mb_strtolower(trim((string)$user['status']), 'UTF-8');
        return in_array($status, ['ativo', '1', 'aprovado', 'active', 'sim'], true);
    }
}

if (!function_exists('admin_login_db_has_admin')) {
    function admin_login_db_has_admin(PDO $pdo): bool
    {
        $columns = admin_login_columns($pdo, 'usuarios');
        $roleColumns = ['tipo', 'nivel', 'perfil', 'role', 'grupo'];
        $where = [];
        $params = [];

        foreach ($roleColumns as $column) {
            if (!admin_login_has_column($columns, $column)) {
                continue;
            }

            $safeColumn = str_replace('`', '``', $column);
            $where[] = "LOWER(TRIM(`{$safeColumn}`)) IN ('admin','administrador','adm','1','master','superadmin')";
        }

        if (!$where) {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM usuarios WHERE (' . implode(' OR ', $where) . ')';
        if (admin_login_has_column($columns, 'status')) {
            $sql .= " AND (status IS NULL OR status = '' OR LOWER(TRIM(status)) IN ('ativo','1','aprovado','active','sim'))";
        }

        try {
            return (int)$pdo->query($sql)->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao conferir admins: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('admin_login_first_active_user_id')) {
    function admin_login_first_active_user_id(PDO $pdo): int
    {
        $columns = admin_login_columns($pdo, 'usuarios');
        $sql = 'SELECT id FROM usuarios';
        if (admin_login_has_column($columns, 'status')) {
            $sql .= " WHERE status IS NULL OR status = '' OR LOWER(TRIM(status)) IN ('ativo','1','aprovado','active','sim')";
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';

        try {
            return (int)$pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao buscar primeiro usuário ativo: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('admin_login_can_auto_promote_owner')) {
    function admin_login_can_auto_promote_owner(PDO $pdo, array $user): bool
    {
        $id = (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        // Se ainda não existe nenhum admin no banco, libera o primeiro usuário ativo como dono inicial.
        // Isso evita travar o painel quando o banco tem só usuário cliente, como no dump enviado.
        if (admin_login_db_has_admin($pdo)) {
            return false;
        }

        return $id === admin_login_first_active_user_id($pdo);
    }
}

if (!function_exists('admin_login_promote_user_to_admin')) {
    function admin_login_promote_user_to_admin(PDO $pdo, array &$user): void
    {
        $id = (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $columns = admin_login_columns($pdo, 'usuarios');
        foreach (['tipo', 'nivel', 'perfil', 'role', 'grupo'] as $column) {
            if (!admin_login_has_column($columns, $column)) {
                continue;
            }

            try {
                $safeColumn = str_replace('`', '``', $column);
                $stmt = $pdo->prepare("UPDATE usuarios SET `{$safeColumn}` = 'admin' WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $user[$column] = 'admin';
                return;
            } catch (Throwable $e) {
                error_log('[Projeto Gabaritando] Falha ao promover usuário admin: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('admin_login_create_session')) {
    function admin_login_create_session(array $user, string $loginDigitado): void
    {
        session_regenerate_id(true);

        $id = (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 1);
        if ($id <= 0) {
            $id = 1;
        }

        $nome = (string)($user['nome'] ?? $user['nome_usuario'] ?? $user['usuario'] ?? $user['username'] ?? $loginDigitado);
        $email = (string)($user['email'] ?? '');

        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_usuario_id'] = $id;
        $_SESSION['admin_login'] = $loginDigitado;
        $_SESSION['admin_nome'] = $nome;
        $_SESSION['admin_login_em'] = date('Y-m-d H:i:s');

        $_SESSION['usuario_id'] = $id;
        $_SESSION['id_usuario'] = $id;
        $_SESSION['user_id'] = $id;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_email'] = $email;
        $_SESSION['usuario_tipo'] = 'admin';
        $_SESSION['usuario'] = [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'tipo' => 'admin',
        ];
    }
}

if (!function_exists('admin_login_config_ok')) {
    function admin_login_config_ok(string $loginDigitado, string $senhaDigitada, string $adminLogin, string $adminSenhaHash, string $adminSenhaTexto = ''): bool
    {
        $adminLogin = trim($adminLogin);
        $adminSenhaHash = trim($adminSenhaHash);
        $adminSenhaTexto = trim($adminSenhaTexto);

        if ($adminLogin === '') {
            return false;
        }

        if (!hash_equals($adminLogin, $loginDigitado)) {
            return false;
        }

        if ($adminSenhaHash !== '' && admin_login_password_matches($adminSenhaHash, $senhaDigitada)) {
            return true;
        }

        if ($adminSenhaTexto !== '' && hash_equals($adminSenhaTexto, $senhaDigitada)) {
            return true;
        }

        return false;
    }
}

$redirectPedido = admin_login_safe_redirect((string)($_GET['redirect'] ?? $_POST['redirect'] ?? 'admin-dashboard'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && admin_login_is_logged()) {
    header('Location: ' . admin_login_location($redirectPedido));
    exit;
}

$ADMIN_LOGIN = '';
$ADMIN_EMAIL = '';
$ADMIN_SENHA_HASH = '';
$ADMIN_SENHA = '';
$ADMIN_PASSWORD = '';

$configAdmin = __DIR__ . '/config/admin-login.php';
if (is_file($configAdmin)) {
    require $configAdmin;
}

$erroLogin = '';
$loginDigitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginDigitado = trim((string)($_POST['login'] ?? $_POST['email'] ?? ''));
    $senhaDigitada = (string)($_POST['senha'] ?? $_POST['password'] ?? '');

    if ($loginDigitado === '' || $senhaDigitada === '') {
        $erroLogin = 'Informe o login e a senha para continuar.';
    } else {
        $loginOk = false;
        $usuarioAdmin = null;

        $senhaTextoConfig = $ADMIN_SENHA !== '' ? (string)$ADMIN_SENHA : (string)$ADMIN_PASSWORD;

        if (admin_login_config_ok($loginDigitado, $senhaDigitada, (string)$ADMIN_LOGIN, (string)$ADMIN_SENHA_HASH, $senhaTextoConfig)
            || (($ADMIN_EMAIL ?? '') !== '' && admin_login_config_ok($loginDigitado, $senhaDigitada, (string)$ADMIN_EMAIL, (string)$ADMIN_SENHA_HASH, $senhaTextoConfig))) {
            $loginOk = true;
            $usuarioAdmin = [
                'id' => 1,
                'nome' => 'Admin',
                'email' => (string)($ADMIN_EMAIL !== '' ? $ADMIN_EMAIL : $ADMIN_LOGIN),
                'tipo' => 'admin',
            ];
        }

        if (!$loginOk) {
            $pdo = admin_login_db();
            if ($pdo instanceof PDO) {
                try {
                    $usuarioAdmin = admin_login_find_user($pdo, $loginDigitado);
                    if ($usuarioAdmin && admin_login_user_password_ok($usuarioAdmin, $senhaDigitada) && admin_login_user_is_active($usuarioAdmin)) {
                        if (admin_login_user_is_admin($usuarioAdmin)) {
                            $loginOk = true;
                        } elseif (admin_login_can_auto_promote_owner($pdo, $usuarioAdmin)) {
                            admin_login_promote_user_to_admin($pdo, $usuarioAdmin);
                            $loginOk = true;
                        }

                        if ($loginOk) {
                            try {
                                $cols = admin_login_columns($pdo, 'usuarios');
                                $id = (int)($usuarioAdmin['id'] ?? 0);
                                if ($id > 0 && admin_login_has_column($cols, 'ultimo_login')) {
                                    $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ? LIMIT 1');
                                    $stmt->execute([$id]);
                                }
                            } catch (Throwable $ignored) {
                                // Não quebra login por causa de coluna de último login.
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[Projeto Gabaritando] Erro no login admin pelo banco: ' . $e->getMessage());
                }
            }
        }

        if ($loginOk && is_array($usuarioAdmin)) {
            admin_login_create_session($usuarioAdmin, $loginDigitado);
            header('Location: ' . admin_login_location($redirectPedido));
            exit;
        }

        $erroLogin = 'Login ou senha inválidos. Confira login, senha e se o usuário está ativo. Se não existir nenhum admin no banco, o primeiro usuário ativo será liberado como admin automaticamente.';
    }
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
    <title>Login Admin | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="<?php echo esc(auth_admin_url('assets/js/gabaritando-icons.js')); ?>"></script>
    <style>
        :root{
            --admin-amarelo:#fdbd08;
            --admin-amarelo-escuro:#d89d00;
            --admin-preto:#111111;
            --admin-fundo:#ffffff;
            --admin-card:#f8f8f8;
            --admin-borda:#d8d8d8;
            --admin-texto:#111111;
            --admin-muted:#666666;
            --admin-erro:#b3261e;
            --admin-erro-bg:#fff1f0;
            --admin-erro-border:#ffb4ab;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html,
        body{
            width:100%;
            min-height:100%;
        }

        body{
            min-height:100vh;
            font-family:'Ubuntu', Arial, sans-serif;
            background:var(--admin-fundo);
            color:var(--admin-texto);
        }

        button,
        input{
            font-family:inherit;
        }

        .admin-page{
            width:100%;
            min-height:100vh;
            background:var(--admin-fundo);
            padding:42px 18px 60px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .admin-login-card{
            width:100%;
            max-width:520px;
            background:var(--admin-card);
            border-radius:8px 8px 0 0;
            overflow:hidden;
            box-shadow:0 18px 45px rgba(0,0,0,.18);
        }

        .admin-login-header{
            width:100%;
            min-height:104px;
            background:var(--admin-preto);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:22px 24px;
            border-bottom:4px solid var(--admin-amarelo);
        }

        .admin-logo{
            display:block;
            max-width:245px;
            width:70%;
            height:auto;
            object-fit:contain;
        }

        .admin-login-body{
            width:100%;
            padding:34px 36px 38px;
        }

        .admin-login-subtitle{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            color:var(--admin-preto);
            font-size:1rem;
            font-weight:700;
            margin-bottom:26px;
        }

        .admin-login-subtitle svg{
            width:20px;
            height:20px;
            color:var(--admin-amarelo);
            stroke-width:2.6;
        }

        .admin-form{
            width:100%;
            display:grid;
            gap:18px;
        }

        .admin-campo{
            width:100%;
            display:grid;
            gap:8px;
        }

        .admin-campo label{
            color:var(--admin-preto);
            font-size:1rem;
            font-weight:500;
        }

        .admin-input-wrap{
            position:relative;
            width:100%;
        }

        .admin-input-wrap > svg{
            position:absolute;
            top:50%;
            left:16px;
            width:20px;
            height:20px;
            transform:translateY(-50%);
            color:#111111;
            pointer-events:none;
            z-index:2;
        }

        .admin-input{
            width:100%;
            height:52px;
            border:1px solid var(--admin-borda);
            border-radius:8px;
            background:#ffffff;
            color:#222222;
            font-size:1rem;
            font-weight:400;
            outline:none;
            padding:0 16px 0 48px;
            transition:border-color .2s ease, box-shadow .2s ease;
        }

        .admin-input:focus{
            border-color:var(--admin-amarelo);
            box-shadow:0 0 0 3px rgba(253,189,8,.18);
        }

        .admin-input::placeholder{
            color:#888888;
            opacity:1;
        }

        .admin-password .admin-input{
            padding-right:52px;
        }

        .admin-toggle-senha{
            position:absolute;
            top:50%;
            right:10px;
            width:38px;
            height:38px;
            border:0;
            border-radius:8px;
            background:transparent;
            color:#222222;
            cursor:pointer;
            transform:translateY(-50%);
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .admin-toggle-senha:hover{
            background:#f1f1f1;
        }

        .admin-toggle-senha svg{
            width:19px;
            height:19px;
        }

        .admin-alerta{
            width:100%;
            min-height:44px;
            border:1px solid var(--admin-erro-border);
            border-radius:8px;
            background:var(--admin-erro-bg);
            color:var(--admin-erro);
            font-size:.94rem;
            font-weight:500;
            line-height:1.35;
            display:flex;
            align-items:center;
            gap:10px;
            padding:10px 12px;
        }

        .admin-alerta svg{
            width:19px;
            height:19px;
            flex:0 0 auto;
        }

        .admin-btn{
            width:100%;
            height:52px;
            border:0;
            border-radius:8px;
            background:var(--admin-amarelo);
            color:#ffffff;
            font-size:1.02rem;
            font-weight:700;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            margin-top:4px;
            transition:background .2s ease, transform .2s ease;
        }

        .admin-btn:hover{
            background:var(--admin-amarelo-escuro);
            transform:translateY(-1px);
        }

        .admin-btn svg{
            width:20px;
            height:20px;
            stroke-width:2.7;
        }

        .admin-sem-cadastro{
            text-align:center;
            color:var(--admin-muted);
            font-size:.9rem;
            font-weight:500;
            margin-top:20px;
        }

        @media(max-width:620px){
            .admin-page{
                padding:28px 12px 42px;
                min-height:100vh;
            }

            .admin-login-card{
                max-width:100%;
            }

            .admin-login-header{
                min-height:88px;
                padding:18px 16px;
            }

            .admin-logo{
                max-width:210px;
                width:76%;
            }

            .admin-login-body{
                padding:26px 18px 30px;
            }

            .admin-login-subtitle{
                font-size:.96rem;
                margin-bottom:22px;
            }

            .admin-input,
            .admin-btn{
                height:50px;
            }
        }
    </style>
</head>
<body>
    <main class="admin-page">
        <section class="admin-login-card" aria-label="Login administrativo">
            <header class="admin-login-header">
                <img class="admin-logo" src="img/logo2.png" alt="Projeto Gabaritando">
            </header>

            <div class="admin-login-body">
                <div class="admin-login-subtitle">
                    <i data-lucide="shield-check" aria-hidden="true"></i>
                    <span>Acesso administrativo</span>
                </div>

                <form class="admin-form" method="post" action="" autocomplete="off" novalidate>
                    <input type="hidden" name="redirect" value="<?php echo esc($redirectPedido); ?>">
                    <?php if ($erroLogin !== ''): ?>
                        <div class="admin-alerta" role="alert">
                            <i data-lucide="circle-alert" aria-hidden="true"></i>
                            <span><?php echo esc($erroLogin); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="admin-campo">
                        <label for="login">Login</label>
                        <div class="admin-input-wrap">
                            <i data-lucide="user" aria-hidden="true"></i>
                            <input
                                class="admin-input"
                                type="text"
                                id="login"
                                name="login"
                                value="<?php echo esc($loginDigitado); ?>"
                                placeholder="Digite o login"
                                required
                            >
                        </div>
                    </div>

                    <div class="admin-campo">
                        <label for="senha">Senha</label>
                        <div class="admin-input-wrap admin-password">
                            <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                            <input
                                class="admin-input"
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="Digite a senha"
                                required
                            >
                            <button class="admin-toggle-senha" type="button" aria-label="Mostrar ou ocultar senha" onclick="toggleSenhaAdmin()">
                                <i data-lucide="eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button class="admin-btn" type="submit">
                        <i data-lucide="log-in" aria-hidden="true"></i>
                        Entrar
                    </button>
                </form>

                <p class="admin-sem-cadastro">Área restrita. Não há cadastro nesta tela.</p>
            </div>
        </section>
    </main>

    <script>
        function toggleSenhaAdmin(){
            const campo = document.getElementById('senha');
            if (!campo) return;
            campo.type = campo.type === 'password' ? 'text' : 'password';
        }

        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>
