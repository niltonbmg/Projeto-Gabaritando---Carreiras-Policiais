<?php
// includes/texto-limpo-lib.php
// A IA às vezes escreve matemática em LaTeX ($n$, $3 \times 2 = 6$, x_1, \frac{a}{b}), que aparece cru para o aluno.
// gab_sem_latex() troca por texto legível (n, 3 × 2 = 6, x₁, a/b) sem mexer em valores como "R$ 10,00".
declare(strict_types=1);

if (!function_exists('gab_sem_latex')) {
    function gab_latex_trecho(string $m): string
    {
        $mapa = [
            '\\times' => '×', '\\cdot' => '·', '\\div' => '÷', '\\ldots' => '…', '\\cdots' => '…', '\\dots' => '…',
            '\\leq' => '≤', '\\geq' => '≥', '\\le' => '≤', '\\ge' => '≥', '\\neq' => '≠', '\\ne' => '≠', '\\approx' => '≈', '\\equiv' => '≡',
            '\\pm' => '±', '\\infty' => '∞', '\\Rightarrow' => '⇒', '\\rightarrow' => '→', '\\to' => '→', '\\Leftrightarrow' => '⇔', '\\leftarrow' => '←',
            '\\in' => '∈', '\\notin' => '∉', '\\subset' => '⊂', '\\cup' => '∪', '\\cap' => '∩', '\\emptyset' => '∅', '\\forall' => '∀', '\\exists' => '∃',
            '\\alpha' => 'α', '\\beta' => 'β', '\\gamma' => 'γ', '\\delta' => 'δ', '\\Delta' => 'Δ', '\\theta' => 'θ', '\\lambda' => 'λ', '\\mu' => 'μ',
            '\\pi' => 'π', '\\sigma' => 'σ', '\\Sigma' => 'Σ', '\\omega' => 'ω', '\\Omega' => 'Ω', '\\circ' => '°', '\\%' => '%', '\\$' => '$',
            '\\,' => ' ', '\;' => ' ', '\\!' => '', '\\quad' => ' ', '\\qquad' => ' ', '\\left' => '', '\\right' => '', '\\displaystyle' => '',
        ];
        // \frac{a}{b} / \dfrac / \tfrac  ->  a/b (com parênteses se tiver mais de um termo)
        for ($i = 0; $i < 5 && preg_match('~\\\\[dt]?frac~', $m); $i++) {
            $m = preg_replace_callback('~\\\\[dt]?frac\s*\{((?:[^{}]|\{[^{}]*\})*)\}\s*\{((?:[^{}]|\{[^{}]*\})*)\}~u', static function ($x) {
                $p = static fn(string $t): string => preg_match('~^[\p{L}\p{N},.!]+$~u', trim($t)) ? trim($t) : '(' . trim($t) . ')';
                return $p($x[1]) . '/' . $p($x[2]);
            }, $m);
        }
        $m = preg_replace('~\\\\binom\s*\{([^{}]*)\}\s*\{([^{}]*)\}~u', 'C($1, $2)', $m);
        $m = preg_replace('~\\\\sqrt\s*\[([^\]]*)\]\s*\{([^{}]*)\}~u', '$1√($2)', $m);
        $m = preg_replace_callback('~\\\\sqrt\s*\{([^{}]*)\}~u', static fn($x) => preg_match('~^[\p{L}\p{N}]+$~u', $x[1]) ? '√' . $x[1] : '√(' . $x[1] . ')', $m);
        $m = preg_replace('~\\\\(?:text|textbf|mathrm|mathbf|mathit|operatorname|textit)\s*\{([^{}]*)\}~u', '$1', $m);
        uksort($mapa, static fn($a, $b) => strlen($b) <=> strlen($a)); // comandos longos primeiro (\leq antes de \le)
        foreach ($mapa as $de => $para) {
            $m = preg_replace('~' . preg_quote($de, '~') . '(?![a-zA-Z])~u', $para, $m);
        }
        $sup = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹', '+' => '⁺', '-' => '⁻', 'n' => 'ⁿ', '°' => '°'];
        $sub = ['0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉', '+' => '₊', '-' => '₋', 'n' => 'ₙ', 'i' => 'ᵢ', 'k' => 'ₖ', 'p' => 'ₚ', ',' => ','];
        $conv = static function (string $t, array $tab, string $sinal): string {
            $t = trim($t);
            $ok = $t !== '' && preg_match('~^[' . preg_quote(implode('', array_keys($tab)), '~') . ']+$~u', $t);
            return $ok ? strtr($t, $tab) : $sinal . (mb_strlen($t) > 1 ? '(' . $t . ')' : $t);
        };
        $m = preg_replace_callback('~\^\s*\{([^{}]*)\}~u', static fn($x) => $conv($x[1], $sup, '^'), $m);
        $m = preg_replace_callback('~\^\s*([\p{L}\p{N}+\-°])~u', static fn($x) => $conv($x[1], $sup, '^'), $m);
        $m = preg_replace_callback('~_\s*\{([^{}]*)\}~u', static fn($x) => $conv($x[1], $sub, '_'), $m);
        $m = preg_replace_callback('~_\s*([\p{L}\p{N}])~u', static fn($x) => $conv($x[1], $sub, '_'), $m);
        $m = preg_replace('~\\\\([a-zA-Z]+)~', '$1', $m); // comando desconhecido: fica só o nome
        $m = str_replace(['{', '}'], '', $m);
        return preg_replace('~[ \t]{2,}~u', ' ', $m);
    }

    function gab_sem_latex(string $s): string
    {
        if ($s === '' || (strpos($s, '$') === false && strpos($s, '\\') === false)) {
            return $s;
        }
        $s = preg_replace_callback('~\$\$(.+?)\$\$~su', static fn($x) => gab_latex_trecho($x[1]), $s);
        $s = preg_replace_callback('~\\\\\[(.+?)\\\\\]~su', static fn($x) => gab_latex_trecho($x[1]), $s);
        $s = preg_replace_callback('~\\\\\((.+?)\\\\\)~su', static fn($x) => gab_latex_trecho($x[1]), $s);
        // $...$ de uma linha, que não seja dinheiro (R$, US$) e sem espaço logo após abrir / antes de fechar
        $s = preg_replace_callback('~(?<![\p{L}\\\\$])\$(?=\S)([^$\n]{1,300}?)(?<=\S)\$(?![\p{N}])~u', static function ($x) {
            return preg_match('~R\$|^\s*\d+([.,]\d+)?\s*$~u', $x[1]) && !preg_match('~[\\\\^_]~', $x[1]) && !preg_match('~^\d+$~', trim($x[1])) ? $x[0] : gab_latex_trecho($x[1]);
        }, $s);
        // comandos soltos fora de $ (a IA às vezes esquece os cifrões)
        if (preg_match('~\\\\(times|cdot|div|dots|ldots|leq|geq|neq|frac|sqrt|Rightarrow|rightarrow|approx|pm)\b~', $s)) {
            $s = preg_replace_callback('~\\\\(?:[dt]?frac\s*\{[^{}]*\}\s*\{[^{}]*\}|sqrt\s*\{[^{}]*\}|[a-zA-Z]+|[,;!])~u', static fn($x) => gab_latex_trecho($x[0]), $s);
        }
        return $s;
    }
}

if (!function_exists('gab_limpar_latex_existente')) {
    /**
     * Uma vez só (marca em uploads/.migracao-latex-v1): tira o LaTeX do que a IA já tinha gravado
     * — teoria/apostila das aulas, questões, alternativas e slides das videoaulas. Retorna quantos registros mudaram.
     */
    function gab_limpar_latex_existente(PDO $pdo): int
    {
        $marca = dirname(__DIR__) . '/uploads/.migracao-latex-v1';
        if (is_file($marca)) {
            return 0;
        }
        $n = 0;
        $tem = "(LOCATE(CHAR(92), %s) > 0 OR LOCATE('$', %s) > 0)";
        $alvos = [
            ['admin_curso_aulas', 'id', ['descricao']],
            ['admin_banco_questoes', 'id', ['enunciado', 'explicacao']],
            ['admin_banco_questao_opcoes', 'id', ['texto']],
        ];
        foreach ($alvos as [$tabela, $pk, $cols]) {
            try {
                $where = implode(' OR ', array_map(static fn($c) => sprintf($tem, $c, $c), $cols));
                $up = $pdo->prepare("UPDATE `$tabela` SET " . implode(', ', array_map(static fn($c) => "`$c` = :$c", $cols)) . " WHERE `$pk` = :id");
                foreach ($pdo->query("SELECT `$pk`, " . implode(', ', array_map(static fn($c) => "`$c`", $cols)) . " FROM `$tabela` WHERE $where")->fetchAll() as $r) {
                    $novo = [];
                    $mudou = false;
                    foreach ($cols as $c) {
                        $novo[":$c"] = gab_sem_latex((string)$r[$c]);
                        $mudou = $mudou || $novo[":$c"] !== (string)$r[$c];
                    }
                    if ($mudou) {
                        $up->execute($novo + [':id' => $r[$pk]]);
                        $n++;
                    }
                }
            } catch (Throwable $e) {
                error_log("limpar latex $tabela: " . $e->getMessage());
            }
        }
        try {
            $up = $pdo->prepare('UPDATE aula_videoaulas SET roteiro = :r WHERE aula_id = :a');
            foreach ($pdo->query("SELECT aula_id, roteiro FROM aula_videoaulas WHERE " . sprintf($tem, 'roteiro', 'roteiro'))->fetchAll() as $r) {
                $j = json_decode((string)$r['roteiro'], true);
                if (!is_array($j)) {
                    continue;
                }
                array_walk_recursive($j, static function (&$v): void {
                    if (is_string($v)) {
                        $v = gab_sem_latex($v);
                    }
                });
                $novo = json_encode($j, JSON_UNESCAPED_UNICODE);
                if ($novo !== $r['roteiro']) {
                    $up->execute([':r' => $novo, ':a' => $r['aula_id']]);
                    $n++;
                }
            }
        } catch (Throwable $e) {
            error_log('limpar latex videoaulas: ' . $e->getMessage());
        }
        @file_put_contents($marca, date('c') . " · $n registro(s)");
        return $n;
    }
}
