<?php
// includes/seguranca.php
// 1) Envio forjado (CSRF): formulário enviado a partir de OUTRO site é recusado.
//    Navegadores sempre mandam Origin/Referer em POST entre sites; chamadas servidor-a-servidor
//    (webhooks de pagamento, cron) não mandam e continuam funcionando.
// 2) Limite de tentativas de login por IP (bloqueia robôs testando senhas).
declare(strict_types=1);

if (!function_exists('gab_seg_post_mesma_origem')) {
    function gab_seg_post_mesma_origem(): void
    {
        if (PHP_SAPI === 'cli' || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }
        $host = strtolower(preg_replace('~:\d+$~', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        $veio = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($veio === '' || $veio === 'null') {
            $veio = (string)($_SERVER['HTTP_REFERER'] ?? '');
        }
        if ($veio === '' || $host === '') {
            return;
        }
        $hostVeio = strtolower((string)parse_url($veio, PHP_URL_HOST));
        $semWww = static fn(string $h): string => preg_replace('~^www\.~', '', $h);
        if ($hostVeio !== '' && $semWww($hostVeio) !== $semWww($host)) {
            http_response_code(403);
            exit('Envio recusado por segurança (veio de outro site). Volte à plataforma e tente de novo.');
        }
    }

    function gab_seg_login_arquivo(): string
    {
        $dir = dirname(__DIR__) . '/uploads/.cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/login-' . md5((string)($_SERVER['REMOTE_ADDR'] ?? 'x')) . '.json';
    }

    /** Minutos que faltam para liberar (0 = pode tentar). 10 erros em 15 min bloqueia 15 min. */
    function gab_seg_login_bloqueado(): int
    {
        $d = json_decode((string)@file_get_contents(gab_seg_login_arquivo()), true);
        $erros = array_filter((array)($d['erros'] ?? []), static fn($t): bool => (int)$t > time() - 900);
        if (count($erros) < 10) {
            return 0;
        }
        return max(1, (int)ceil((max($erros) + 900 - time()) / 60));
    }

    function gab_seg_login_erro(): void
    {
        $arq = gab_seg_login_arquivo();
        $d = json_decode((string)@file_get_contents($arq), true);
        $erros = array_values(array_filter((array)($d['erros'] ?? []), static fn($t): bool => (int)$t > time() - 900));
        $erros[] = time();
        @file_put_contents($arq, json_encode(['erros' => array_slice($erros, -20)]), LOCK_EX);
    }

    function gab_seg_login_ok(): void
    {
        @unlink(gab_seg_login_arquivo());
    }
}
