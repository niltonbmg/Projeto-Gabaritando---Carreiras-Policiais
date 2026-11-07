<?php
require_once 'config_google.php';

// Gera a URL de login do Google
$authUrl = $client->createAuthUrl();

// Redireciona o usuário para o Google
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;