<?php
declare(strict_types=1);

// Proxy seguro para imagens do app Flutter Web.
// O Chrome rodando em localhost precisa de CORS para carregar imagens do domínio do site.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cross-Origin-Resource-Policy: cross-origin');
header('Cache-Control: public, max-age=86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$src = trim((string)($_GET['src'] ?? ''));
$src = rawurldecode($src);
$src = str_replace('\\', '/', $src);

if ($src === '') {
    http_response_code(404);
    exit;
}

// Se vier URL completa do mesmo site, pega só o caminho.
if (preg_match('~^https?://~i', $src)) {
    $parts = parse_url($src);
    $src = (string)($parts['path'] ?? '');
}

// Normaliza caminhos comuns: /ead/uploads/... => uploads/...
$src = ltrim($src, '/');
if (str_starts_with($src, 'ead/')) {
    $src = substr($src, 4);
}
if (str_starts_with($src, './')) {
    $src = substr($src, 2);
}

// Bloqueia traversal e só permite arquivos públicos do projeto.
if ($src === '' || str_contains($src, '..') || !preg_match('~^(uploads/|img/)~', $src)) {
    http_response_code(403);
    exit;
}

$eadRoot = dirname(__DIR__); // /public_html/ead
$file = $eadRoot . '/' . $src;
$realRoot = realpath($eadRoot);
$realFile = realpath($file);

if (!$realRoot || !$realFile || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
];

if (!isset($types[$ext])) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($realFile));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realFile)) . ' GMT');
readfile($realFile);
