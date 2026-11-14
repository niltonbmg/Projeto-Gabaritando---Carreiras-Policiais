<?php
// includes/cache-lib.php
// Cache curto em arquivo (hospedagem compartilhada não tem Redis/APCu garantido).
// Usado onde muitos alunos pedem a mesma coisa ao mesmo tempo: ranking, questões do desafio.
declare(strict_types=1);

if (!function_exists('gab_cache')) {
    function gab_cache_dir(): string
    {
        return dirname(__DIR__) . '/uploads/.cache';
    }

    /** Devolve o valor guardado (se tiver menos de $segundos) ou calcula com $fn e guarda. */
    function gab_cache(string $chave, int $segundos, callable $fn)
    {
        $arq = gab_cache_dir() . '/' . preg_replace('~[^a-z0-9_\-]~i', '_', $chave) . '.php';
        if (is_file($arq) && (time() - (int)@filemtime($arq)) < $segundos) {
            $v = @unserialize((string)@file_get_contents($arq), ['allowed_classes' => false]);
            if ($v !== false) {
                return $v;
            }
        }
        $v = $fn();
        if (!is_dir(gab_cache_dir())) {
            @mkdir(gab_cache_dir(), 0755, true);
        }
        $tmp = $arq . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, serialize($v)) !== false) {
            @rename($tmp, $arq); // troca atômica: ninguém lê arquivo pela metade
        }
        return $v;
    }

    function gab_cache_apagar(string $prefixo): void
    {
        foreach (glob(gab_cache_dir() . '/' . preg_replace('~[^a-z0-9_\-]~i', '_', $prefixo) . '*') ?: [] as $f) {
            @unlink($f);
        }
    }
}
