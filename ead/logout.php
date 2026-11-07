<?php
// logout.php
// Encerra a sessão do usuário e redireciona para o login.

// Evita cache da página após sair
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Inicia a sessão apenas se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão
$_SESSION = [];

// Remove o cookie da sessão, se existir
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// Destrói a sessão
session_destroy();

// Remove também cookies comuns de login/lembrar, caso existam
$cookiesParaLimpar = [
    'remember_token',
    'usuario_id',
    'user_id',
    'login_token',
    'auth_token'
];

foreach ($cookiesParaLimpar as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 42000, '/');
    }
}

// Volta para o login
header('Location: login.php');
exit;
