<?php
// includes/aula-descricao-lib.php
// Transforma a descrição da aula (texto puro ou HTML gerado pela IA) em um
// material de estudo organizado: títulos, listas, destaques e "Bizus".
// Também guarda as marcações (marca-texto) de cada aluno.
declare(strict_types=1);

if (!function_exists('aula_desc_e')) {
    function aula_desc_e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('aula_desc_sanitizar_html')) {
    /** Mantém só tags seguras de texto (para descrições já em HTML). */
    function aula_desc_sanitizar_html(string $html): string
    {
        $permitidas = '<h2><h3><h4><p><ul><ol><li><strong><b><em><i><u><mark><br><table><thead><tbody><tr><th><td><blockquote><hr>';
        $html = strip_tags($html, $permitidas);
        // Remove qualquer atributo (on*, style, href javascript etc.).
        $html = (string)preg_replace('/<(\w+)(\s[^>]*)?>/u', '<$1>', $html);
        return $html;
    }
}

if (!function_exists('aula_desc_eh_titulo')) {
    /** Linha toda em maiúsculas (com letras) e curta = título de seção. */
    function aula_desc_eh_titulo(string $linha): bool
    {
        $letras = preg_replace('/[^\p{L}]/u', '', $linha);
        return $letras !== '' && mb_strlen($linha) <= 90 && mb_strtoupper($linha, 'UTF-8') === $linha && mb_strlen((string)$letras) >= 4;
    }
}

if (!function_exists('aula_desc_inline')) {
    /** Escapa e aplica destaques simples: "Rótulo: texto" vira rótulo em negrito. */
    function aula_desc_inline(string $linha): string
    {
        $e = aula_desc_e($linha);
        if (preg_match('/^([^:]{2,60}):\s+(.+)$/u', $linha, $m)) {
            return '<strong>' . aula_desc_e($m[1]) . ':</strong> ' . aula_desc_e($m[2]);
        }
        return $e;
    }
}

if (!function_exists('aula_descricao_html')) {
    /**
     * Converte a descrição em HTML estruturado.
     * - Se já vier em HTML (apostila gerada), só sanitiza.
     * - Se for texto puro: MAIÚSCULAS viram seções (recolhíveis), "Rótulo:" sozinho
     *   vira subtítulo, linhas seguidas viram lista, a seção "BIZUS" vira cartões.
     */
    function aula_descricao_html(string $texto): string
    {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        if ($texto === '' || $texto === '-') {
            return '';
        }
        if (preg_match('/<(p|h2|h3|ul|li|table|strong)\b/i', $texto)) {
            return '<div class="ad-html">' . aula_desc_sanitizar_html($texto) . '</div>';
        }

        $blocos = [];          // [['tipo'=>'intro'|'secao', 'titulo'=>..., 'linhas'=>[...]]]
        $atual = ['tipo' => 'intro', 'titulo' => '', 'linhas' => []];
        foreach (explode("\n", $texto) as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                $atual['linhas'][] = '';
                continue;
            }
            if (aula_desc_eh_titulo($linha)) {
                if ($atual['linhas'] || $atual['titulo'] !== '') {
                    $blocos[] = $atual;
                }
                $atual = ['tipo' => 'secao', 'titulo' => $linha, 'linhas' => []];
                continue;
            }
            $atual['linhas'][] = $linha;
        }
        $blocos[] = $atual;

        $html = '';
        foreach ($blocos as $i => $b) {
            $ehBizu = $b['tipo'] === 'secao' && preg_match('/BIZU|PEGADINHA|DICA/u', $b['titulo']);
            $corpo = aula_desc_corpo($b['linhas'], $ehBizu);
            if ($b['tipo'] === 'intro') {
                $html .= $corpo !== '' ? '<div class="ad-intro">' . $corpo . '</div>' : '';
                continue;
            }
            $titulo = aula_desc_titulo($b['titulo']);
            if ($corpo === '') {
                // Título sem conteúdo próprio (ex.: "RESUMO TEÓRICO: ..."): vira cabeçalho do grupo.
                $html .= '<h3 class="ad-grupo">' . aula_desc_e($titulo) . '</h3>';
                continue;
            }
            $html .= '<details class="ad-secao' . ($ehBizu ? ' ad-bizus' : '') . '">'
                . '<summary><span>' . ($ehBizu ? '⚠️ ' : '') . aula_desc_e($titulo) . '</span><small class="ad-abrir">Toque para abrir</small></summary>'
                . '<div class="ad-corpo">' . $corpo . '</div></details>';
        }
        return $html;
    }
}

if (!function_exists('aula_desc_titulo')) {
    /** "SISTEMA DE CAPITANIAS" -> "Sistema de Capitanias" (preposições em minúsculo). */
    function aula_desc_titulo(string $t): string
    {
        $menores = ['a', 'à', 'ao', 'aos', 'as', 'às', 'da', 'das', 'de', 'do', 'dos', 'e', 'em', 'na', 'nas', 'no', 'nos', 'o', 'os', 'ou', 'para', 'por', 'com', 'sua', 'seu'];
        $palavras = explode(' ', mb_strtolower($t, 'UTF-8'));
        foreach ($palavras as $i => $p) {
            if ($i === 0 || !in_array($p, $menores, true)) {
                $palavras[$i] = mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($p, 1, null, 'UTF-8');
            }
        }
        return implode(' ', $palavras);
    }
}

if (!function_exists('aula_desc_corpo')) {
    function aula_desc_corpo(array $linhas, bool $comoBizu): string
    {
        // Agrupa em parágrafos separados por linha vazia; um grupo com várias
        // linhas vira lista. "Rótulo:" sozinho vira subtítulo.
        $grupos = [];
        $g = [];
        foreach ($linhas as $l) {
            if ($l === '') {
                if ($g) { $grupos[] = $g; $g = []; }
                continue;
            }
            if (preg_match('/^[^:]{2,70}:$/u', $l)) {
                if ($g) { $grupos[] = $g; $g = []; }
                $grupos[] = ['__sub' => rtrim($l, ':')];
                continue;
            }
            $g[] = $l;
        }
        if ($g) {
            $grupos[] = $g;
        }

        // Linhas soltas consecutivas (cada uma num grupo próprio) viram uma lista.
        $html = '';
        $lista = [];
        $fechar = static function () use (&$lista, &$html, $comoBizu): void {
            if (!$lista) {
                return;
            }
            if ($comoBizu) {
                foreach ($lista as $item) {
                    $html .= '<div class="ad-bizu">' . aula_desc_inline($item) . '</div>';
                }
            } elseif (count($lista) === 1) {
                $html .= '<p>' . aula_desc_inline($lista[0]) . '</p>';
            } else {
                $html .= '<ul>' . implode('', array_map(static fn(string $i): string => '<li>' . aula_desc_inline($i) . '</li>', $lista)) . '</ul>';
            }
            $lista = [];
        };
        foreach ($grupos as $grupo) {
            if (isset($grupo['__sub'])) {
                $fechar();
                $html .= '<h4>' . aula_desc_e($grupo['__sub']) . '</h4>';
                continue;
            }
            foreach ($grupo as $l) {
                $lista[] = $l;
            }
            if (count($grupo) > 1 || mb_strlen($grupo[0]) > 220) {
                $fechar();
            }
        }
        $fechar();
        return $html;
    }
}

if (!function_exists('aula_marcacoes_bootstrap')) {
    function aula_marcacoes_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_marcacoes (
            usuario_id BIGINT UNSIGNED NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            dados MEDIUMTEXT NOT NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, aula_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
