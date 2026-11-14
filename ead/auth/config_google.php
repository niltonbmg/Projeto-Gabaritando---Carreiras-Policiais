<?php
// auth/config_google.php

// 1. Configura a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/'); 
    session_start();
}

// Carrega o autoloader do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// --- NOVO: Carregar variáveis do .env ---
// O arquivo .env está na raiz, então usamos __DIR__ . '/../' para voltar uma pasta
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad(); // safeLoad evita erro fatal se o arquivo não existir (em produção)
} catch (Exception $e) {
    // Silencia ou loga erro se não conseguir ler o .env
}

// 2. Detecção automática da URL de Callback
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];

$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$currentDir = str_replace('\\', '/', $currentDir); 
$currentDir = rtrim($currentDir, '/') . '/';

define('GOOGLE_REDIRECT_URL', $protocol . $domain . $currentDir . 'google_callback.php');

// 3. Suas Chaves (Agora lendo do .env)
// Usamos o operador ?? '' para evitar erro caso a variável não exista
$clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? 'CLIENT_ID_NAO_CONFIGURADO';
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'SECRET_NAO_CONFIGURADO';

define('GOOGLE_CLIENT_ID', $clientId);
define('GOOGLE_CLIENT_SECRET', $clientSecret);

// Inicializa o Client
$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URL);
$client->addScope("email");
$client->addScope("profile");
