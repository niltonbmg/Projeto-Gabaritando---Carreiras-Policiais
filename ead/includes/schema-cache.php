<?php
// includes/schema-cache.php
// Desempenho: cada função *_bootstrap (cria/ajusta tabelas) roda UMA vez por versão do arquivo,
// não em toda visita. Quando você sobe um arquivo novo (data do arquivo muda), ela roda de novo uma vez.
declare(strict_types=1);

if (!function_exists('gab_schema_ok')) {
    function gab_schema_dir(): string
    {
        return dirname(__DIR__) . '/uploads/.schema';
    }

    /** true = já migrado nesta versão do arquivo (pode pular). false = rode e será marcado ao fim da requisição sem erro fatal. */
    function gab_schema_ok(string $funcao, string $arquivo): bool
    {
        static $pendentes = null;
        $marca = gab_schema_dir() . '/' . preg_replace('~[^a-z0-9_]~i', '', $funcao) . '-' . (int)@filemtime($arquivo);
        if (is_file($marca)) {
            return true;
        }
        if ($pendentes === null) {
            $pendentes = [];
            register_shutdown_function(static function () use (&$pendentes): void {
                $e = error_get_last();
                if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    return;
                }
                $dir = gab_schema_dir();
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                foreach ($pendentes as $m => $f) {
                    foreach (glob($dir . '/' . $f . '-*') ?: [] as $velha) {
                        @unlink($velha);
                    }
                    @touch($m);
                }
            });
        }
        $pendentes[$marca] = preg_replace('~[^a-z0-9_]~i', '', $funcao);
        return false;
    }
}
