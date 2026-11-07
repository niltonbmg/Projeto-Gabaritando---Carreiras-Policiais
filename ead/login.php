<?php
// login.php — Projeto Gabaritando
// Login normal funcionando direto neste arquivo, com conexão ao banco e sessão do sistema.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function login_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function login_safe_redirect(string $redirect, string $default = 'dashboard.php'): string
{
    $redirect = trim(rawurldecode($redirect));
    $default = trim($default) !== '' ? $default : 'dashboard.php';

    // Evita loop tipo: login?redirect=dashboard?redirect=dashboard...
    // e também evita voltar para login/cadastro depois de autenticar.
    for ($i = 0; $i < 6; $i++) {
        $limpo = ltrim($redirect, '/');
        $path = (string)(parse_url($limpo, PHP_URL_PATH) ?: $limpo);
        $pathNormalizado = mb_strtolower(trim($path), 'UTF-8');
        $pathNormalizado = preg_replace('/\.php$/i', '', $pathNormalizado) ?: $pathNormalizado;

        if (in_array($pathNormalizado, ['login', 'cadastro'], true)) {
            return $default;
        }

        if (preg_match('/[?&]redirect=([^&]+)/i', $redirect, $match)) {
            $proximo = trim(rawurldecode((string)$match[1]));
            if ($proximo !== '' && $proximo !== $redirect) {
                $redirect = $proximo;
                continue;
            }
        }

        break;
    }

    $redirect = str_replace('\\', '/', $redirect);

    if ($redirect === '' || strpos($redirect, '://') !== false || str_starts_with($redirect, '//')) {
        return $default;
    }

    $redirect = ltrim($redirect, '/');
    $path = (string)(parse_url($redirect, PHP_URL_PATH) ?: $redirect);
    $pathNormalizado = mb_strtolower(trim($path), 'UTF-8');
    $pathNormalizado = preg_replace('/\.php$/i', '', $pathNormalizado) ?: $pathNormalizado;

    if ($pathNormalizado === '' || in_array($pathNormalizado, ['login', 'cadastro'], true)) {
        return $default;
    }

    return $redirect;
}

function login_load_env_file(string $path): array
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
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '') {
            $vars[$key] = $value;
        }
    }

    return $vars;
}

function login_env(string $key, string $default = ''): string
{
    static $env = null;

    if ($env === null) {
        $env = login_load_env_file(__DIR__ . '/.env');
    }

    // Prioridade para o .env da pasta /ead. Isso evita pegar variável antiga do servidor.
    if (isset($env[$key]) && $env[$key] !== '') {
        return (string)$env[$key];
    }

    $serverValue = getenv($key);
    if ($serverValue !== false && $serverValue !== '') {
        return (string)$serverValue;
    }

    return $default;
}

function login_host_candidates(string $host): array
{
    $hosts = [];
    foreach ([$host, '127.0.0.1', 'localhost'] as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && !in_array($candidate, $hosts, true)) {
            $hosts[] = $candidate;
        }
    }
    return $hosts;
}

function login_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Usa somente a conexão central de /ead/api/conexao.php.
    // Assim o login não pega dado antigo e não tenta host diferente escondido.
    $conexao = __DIR__ . '/api/conexao.php';
    if (!is_file($conexao)) {
        throw new RuntimeException('Arquivo api/conexao.php não encontrado.');
    }

    require_once $conexao;
    if (!function_exists('db')) {
        throw new RuntimeException('Função db() não encontrada em api/conexao.php.');
    }

    $pdoCentral = db();
    if (!$pdoCentral instanceof PDO) {
        throw new RuntimeException('A conexão central não retornou PDO.');
    }

    $pdo = $pdoCentral;
    return $pdo;
}

function login_columns(PDO $pdo, string $table): array
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

function login_has_column(array $columns, string $column): bool
{
    return in_array($column, $columns, true);
}

function login_compact_text(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace([' ', '.', '-', '_', '/', '\\'], '', $value);
    return $value;
}

function login_find_user(PDO $pdo, string $login): ?array
{
    $columns = login_columns($pdo, 'usuarios');
    if (!$columns) {
        return null;
    }

    $loginColumns = ['email', 'login', 'usuario', 'username', 'apelido', 'nome', 'nome_usuario'];
    $where = [];
    $params = [];

    foreach ($loginColumns as $column) {
        if (!login_has_column($columns, $column)) {
            continue;
        }

        $safeColumn = str_replace('`', '``', $column);
        $where[] = "LOWER(TRIM(`{$safeColumn}`)) = LOWER(TRIM(?))";
        $params[] = $login;

        // Também aceita PedroMok quando no banco estiver Pedro Mok.
        $where[] = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`{$safeColumn}`), ' ', ''), '.', ''), '-', ''), '_', ''), '/', '')) = LOWER(?)";
        $params[] = login_compact_text($login);
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

function login_password_matches(string $stored, string $password): bool
{
    $stored = trim($stored);
    if ($stored === '') {
        return false;
    }

    // Senha salva com password_hash / bcrypt / argon.
    if (password_verify($password, $stored)) {
        return true;
    }

    // Compatibilidade com bancos antigos.
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

function login_password_ok(array $user, string $password): bool
{
    $passwordColumns = ['senha_hash', 'password_hash', 'senha', 'password', 'senha_usuario'];

    foreach ($passwordColumns as $column) {
        if (isset($user[$column]) && login_password_matches((string)$user[$column], $password)) {
            return true;
        }
    }

    return false;
}

function login_user_is_active(array $user): bool
{
    if (!isset($user['status']) || (string)$user['status'] === '') {
        return true;
    }

    $status = mb_strtolower(trim((string)$user['status']), 'UTF-8');
    return in_array($status, ['ativo', '1', 'aprovado', 'active', 'sim'], true);
}

function login_create_session(array $user): void
{
    session_regenerate_id(true);

    $id = (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 0);
    $nome = (string)($user['nome'] ?? $user['nome_usuario'] ?? $user['usuario'] ?? $user['username'] ?? 'Usuário');
    $email = (string)($user['email'] ?? '');
    $tipo = (string)($user['tipo'] ?? $user['nivel'] ?? 'cliente');

    $_SESSION['usuario'] = [
        'id' => $id,
        'nome' => $nome,
        'email' => $email,
        'tipo' => $tipo,
    ];

    // Mantém compatibilidade com as páginas que já existem no sistema.
    $_SESSION['usuario_id'] = $id;
    $_SESSION['id_usuario'] = $id;
    $_SESSION['user_id'] = $id;
    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_email'] = $email;
    $_SESSION['usuario_tipo'] = $tipo;
}

function login_update_last_login(PDO $pdo, array $user): void
{
    $columns = login_columns($pdo, 'usuarios');
    $id = (int)($user['id'] ?? $user['usuario_id'] ?? $user['id_usuario'] ?? 0);

    if ($id <= 0) {
        return;
    }

    foreach (['ultimo_login', 'last_login', 'data_ultimo_login'] as $column) {
        if (!login_has_column($columns, $column)) {
            continue;
        }

        $safeColumn = str_replace('`', '``', $column);
        $stmt = $pdo->prepare("UPDATE usuarios SET `{$safeColumn}` = NOW() WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    login_redirect('login.php');
}

$erro = '';
$loginDigitado = (string)($_GET['login'] ?? $_GET['email'] ?? '');
$redirect = login_safe_redirect((string)($_GET['redirect'] ?? 'dashboard.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginDigitado = trim((string)($_POST['login'] ?? $_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? $_POST['password'] ?? '');
    $redirect = login_safe_redirect((string)($_POST['redirect'] ?? 'dashboard.php'));

    if ($loginDigitado === '' || $senha === '') {
        $erro = 'Preencha o login e a senha para continuar.';
    } else {
        try {
            $pdo = login_db();
            $user = login_find_user($pdo, $loginDigitado);

            if (!$user || !login_password_ok($user, $senha)) {
                $erro = 'Login ou senha inválidos. Confira seus dados e tente novamente.';
            } elseif (!login_user_is_active($user)) {
                $erro = 'Sua conta não está ativa. Entre em contato com o suporte.';
            } else {
                login_create_session($user);

                try {
                    login_update_last_login($pdo, $user);
                } catch (Throwable $ignored) {
                    // Não quebra o login se a coluna de último login não existir.
                }

                login_redirect(login_safe_redirect($redirect));
            }
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Erro no login.php: ' . $e->getMessage());
            $erro = 'Não foi possível entrar agora. Verifique a conexão com o banco de dados.';
        }
    }
}

$logoPath = is_file(__DIR__ . '/img/logo.png') ? 'img/logo.png' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#d99a00;
            --black:#080808;
            --bg:#f4f5f7;
            --panel:#f8f9fb;
            --field:#eaf0fb;
            --border:#cfd6e2;
            --text:#111827;
            --muted:#667085;
            --error:#b42318;
            --error-bg:#fff1f0;
            --error-border:#fecdca;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html,body{width:100%;min-height:100%}
        body{min-height:100vh;font-family:'Ubuntu',Arial,sans-serif;background:var(--bg);color:var(--text)}
        a{text-decoration:none;color:inherit}
        button,input{font:inherit}

        .topbar{width:100%;height:70px;background:var(--black);display:flex;align-items:center;justify-content:center;padding:0 32px}
        .topbar-link{display:flex;align-items:center;justify-content:center;height:100%}
        .topbar-logo{display:block;max-height:55px;width:auto;object-fit:contain}

        .login-page{width:100%;min-height:calc(100vh - 70px);display:flex;align-items:center;justify-content:center;background:var(--panel);padding:46px 18px;overflow:auto}
        .form-side{width:100%;display:flex;align-items:center;justify-content:center}
        .login-card{width:100%;max-width:520px;background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:34px 32px 30px;box-shadow:0 18px 45px rgba(15,23,42,.10)}
        .title-row{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:28px;text-align:center}
        .title-row svg{width:26px;height:26px;color:#1f2937;stroke-width:2.4}
        h1{font-size:2rem;line-height:1.15;font-weight:700;color:#0f172a;letter-spacing:.01em;text-align:center}

        .alert{width:100%;border:1px solid var(--error-border);background:var(--error-bg);color:var(--error);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:.94rem;font-weight:700;line-height:1.35}
        .form{display:grid;gap:18px;width:100%}
        .field{display:grid;gap:8px;width:100%}
        .field label{font-size:.95rem;color:#111827;font-weight:500}
        .input-wrap{position:relative;width:100%}
        .input{width:100%;height:43px;border:1px solid var(--border);border-radius:7px;background:var(--field);color:#111827;outline:none;padding:0 16px;transition:border-color .18s ease, box-shadow .18s ease, background .18s ease}
        .input:focus{background:#fff;border-color:var(--yellow);box-shadow:0 0 0 3px rgba(250,181,40,.18)}
        .input.password-input{padding-right:48px}
        .password-toggle{position:absolute;right:11px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;background:transparent;color:#667085;display:flex;align-items:center;justify-content:center;cursor:pointer}
        .password-toggle svg{width:20px;height:20px}

        .form-options{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:1px;font-size:.94rem}
        .remember{display:flex;align-items:center;gap:8px;color:#111827;white-space:nowrap}
        .remember input{width:14px;height:14px;accent-color:var(--yellow)}
        .forgot{color:var(--yellow-dark);font-size:.88rem;white-space:nowrap}
        .forgot:hover{text-decoration:underline}
        .submit-btn{width:100%;height:48px;border:0;border-radius:7px;background:var(--yellow);color:#000;font-weight:700;display:flex;align-items:center;justify-content:center;gap:12px;cursor:pointer;margin-top:2px;transition:filter .18s ease, transform .18s ease}
        .submit-btn:hover{filter:brightness(.97)}
        .submit-btn:active{transform:translateY(1px)}
        .submit-btn svg{width:20px;height:20px;stroke-width:2.6}

        .separator{display:flex;align-items:center;gap:12px;margin:28px 0;color:#667085;font-size:.82rem}
        .separator::before,.separator::after{content:'';height:1px;background:#e5e7eb;flex:1}
        .google-btn{width:100%;height:51px;border:1px solid #d0d5dd;border-radius:7px;background:#fff;color:#111827;font-weight:500;display:flex;align-items:center;justify-content:center;gap:12px;cursor:pointer}
        .google-btn:hover{box-shadow:0 8px 20px rgba(15,23,42,.08)}
        .google-icon{width:20px;height:20px;display:block}
        .signup{margin-top:25px;text-align:center;color:#374151;font-size:.94rem}
        .signup a{color:var(--yellow-dark);font-weight:700}
        .signup a:hover{text-decoration:underline}

        @media(max-width:900px){
            .topbar{height:64px;justify-content:center;padding:0 18px}.topbar-logo{max-height:50px}
            .login-page{min-height:calc(100vh - 64px);padding:28px 14px;background:var(--panel)}
            .form-side{width:100%;align-items:center;justify-content:center}
            .login-card{max-width:520px;padding:28px 20px}
            h1{font-size:1.72rem}
        }

        @media(max-width:480px){.login-card{border-radius:13px;padding:24px 16px}.form-options{align-items:flex-start;flex-direction:column;gap:10px}.forgot{white-space:normal}}
    </style>
</head>
<body>
    <header class="topbar">
        <a class="topbar-link" href="dashboard" aria-label="Projeto Gabaritando">
            <?php if ($logoPath !== ''): ?>
                <img class="topbar-logo" src="<?php echo e($logoPath); ?>" alt="Projeto Gabaritando">
            <?php endif; ?>
        </a>
    </header>

    <main class="login-page">
        <section class="form-side">
            <div class="login-card">
                <div class="title-row">
                    <i data-lucide="log-in"></i>
                    <h1>Entrar na sua conta</h1>
                </div>

                <?php if ($erro !== ''): ?>
                    <div class="alert"><?php echo e($erro); ?></div>
                <?php endif; ?>

                <form class="form" action="" method="post" autocomplete="on">
                    <div class="field">
                        <label for="login">E-mail ou login</label>
                        <input class="input" id="login" name="login" type="text" required value="<?php echo e($loginDigitado); ?>" placeholder="Digite seu e-mail ou usuário">
                    </div>

                    <div class="field">
                        <label for="senha">Senha</label>
                        <div class="input-wrap">
                            <input class="input password-input" id="senha" name="senha" type="password" required placeholder="••••••••">
                            <button class="password-toggle" type="button" id="togglePass" aria-label="Mostrar senha"><i data-lucide="eye"></i></button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember"><input type="checkbox" name="remember" value="1"><span>Lembrar de mim</span></label>
                        <a class="forgot" href="recuperar-senha">Esqueceu a senha?</a>
                    </div>

                    <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                    <button class="submit-btn" type="submit"><i data-lucide="arrow-right"></i><span>Entrar</span></button>
                </form>

                <?php /* Login com Google oculto temporariamente. Código mantido para reativar depois. ?>
                <div class="separator">ou</div>

                <form action="auth/google" method="post">
                    <button class="google-btn" type="submit">
                        <svg class="google-icon" viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#4285f4" d="M533.5 278.4c0-18.5-1.5-36.1-4.4-53.4H272v101h147.6c-6.4 34.7-25.7 64.1-54.8 83.8v69.5h88.6c51.9-47.8 81.1-118 81.1-200.9z"/>
                            <path fill="#34a853" d="M272 544.3c73.7 0 135.7-24.3 180.9-66.2l-88.6-69.5c-24.5 16.5-55.7 26.2-92.3 26.2-71 0-131.2-48-152.8-112.6H29.4v70.9C74.7 483.4 166 544.3 272 544.3z"/>
                            <path fill="#fbbc04" d="M119.2 326.2c-9.7-28.9-9.7-59.8 0-88.7v-70.9H29.4c-39.8 77.9-39.8 170.3 0 248.2l89.8-88.6z"/>
                            <path fill="#ea4335" d="M272 107.7c39.9-.6 77.8 14 106.8 40.2l80.1-80.1C409.4 24.6 344.2-1 272 0 166 0 74.7 60.9 29.4 149.5l89.8 70.9C140.8 155.7 201 107.7 272 107.7z"/>
                        </svg>
                        <span>Entrar com Google</span>
                    </button>
                </form>
                <?php */ ?>

                <p class="signup">Não tem uma conta? <a href="cadastro.php">Cadastre-se</a></p>
            </div>
        </section>
    </main>

    <script>
        if (window.lucide) window.lucide.createIcons();
        const togglePass = document.getElementById('togglePass');
        const senhaInput = document.getElementById('senha');
        if (togglePass && senhaInput) {
            togglePass.addEventListener('click', function () {
                const show = senhaInput.type === 'password';
                senhaInput.type = show ? 'text' : 'password';
                togglePass.innerHTML = show ? '<i data-lucide="eye-off"></i>' : '<i data-lucide="eye"></i>';
                if (window.lucide) window.lucide.createIcons();
            });
        }
    </script>
</body>
</html>
