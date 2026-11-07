<?php
// includes/meta-tempo-lib.php
// Tempo de estudo de cada assunto do Plano-Mestre, calculado (não chutado):
//
//   Vídeo     = duração real do vídeo (o player grava ao ser assistido) ou a
//               duração cadastrada na aula.
//   PDF       = páginas do PDF × minutos por página × complexidade do assunto
//               × fator aprendido com o mentor.
//   Questões  = o resto, garantindo que as questões sejam pelo menos X% do
//               tempo do assunto (Método META) e nunca menos que o mínimo de
//               questões × minutos por questão.
//
// Aprende com o mentor: quando o mentor corrige o tempo de um assunto, a
// razão (tempo corrigido ÷ tempo calculado) é guardada por matéria. A média
// dessas correções vira um fator que ajusta os próximos cálculos da matéria.
declare(strict_types=1);

require_once __DIR__ . '/meta-lib.php';

const META_COMPLEXIDADES = [
    'facil' => ['nome' => 'Fácil', 'fator' => 0.8],
    'media' => ['nome' => 'Média', 'fator' => 1.0],
    'dificil' => ['nome' => 'Difícil', 'fator' => 1.3],
    'muito_dificil' => ['nome' => 'Muito difícil', 'fator' => 1.6],
];

if (!function_exists('meta_tempo_bootstrap')) {
    function meta_tempo_bootstrap(PDO $pdo): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        meta_bootstrap($pdo);
        $stmt = $pdo->prepare('INSERT IGNORE INTO meta_config (chave, valor) VALUES (:c, :v)');
        foreach (['min_por_pagina' => '3', 'min_por_questao' => '3', 'pct_questoes' => '55'] as $c => $v) {
            $stmt->execute([':c' => $c, ':v' => $v]);
        }
        foreach (['complexidade' => "VARCHAR(20) NOT NULL DEFAULT 'media'", 'tempo_ajustado_min' => 'INT UNSIGNED NULL'] as $col => $def) {
            $existe = $pdo->query("SHOW COLUMNS FROM meta_plano_itens LIKE " . $pdo->quote($col))->fetch();
            if (!$existe) {
                $pdo->exec("ALTER TABLE meta_plano_itens ADD COLUMN `$col` $def");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_pdf_paginas (
            arquivo VARCHAR(255) NOT NULL PRIMARY KEY,
            tamanho INT UNSIGNED NOT NULL,
            paginas INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_tempo_aprendizado (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            materia_id INT UNSIGNED NULL,
            plano_item_id INT UNSIGNED NOT NULL,
            calculado_min INT UNSIGNED NOT NULL,
            ajustado_min INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_aprend_materia (materia_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $feito = true;
    }
}

if (!function_exists('meta_tempo_texto_para_seg')) {
    /** "34:36", "1:02:10", "12 min", "1h 5min", "30min", "45s" -> segundos. */
    function meta_tempo_texto_para_seg(string $v): int
    {
        $v = mb_strtolower(trim($v));
        if ($v === '') {
            return 0;
        }
        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $v, $m)) {
            return isset($m[3]) && $m[3] !== '' ? (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] : (int)$m[1] * 60 + (int)$m[2];
        }
        $s = 0;
        if (preg_match('/(\d+)\s*h/', $v, $m)) { $s += (int)$m[1] * 3600; }
        if (preg_match('/(\d+)\s*m/', $v, $m)) { $s += (int)$m[1] * 60; }
        if (preg_match('/(\d+)\s*s/', $v, $m)) { $s += (int)$m[1]; }
        if ($s === 0 && preg_match('/^\d+$/', $v)) { $s = (int)$v * 60; }
        return $s;
    }
}

if (!function_exists('meta_tempo_paginas_pdf')) {
    /** Conta as páginas do PDF (com cache). Sem leitura possível: estima pelo tamanho. */
    function meta_tempo_paginas_pdf(PDO $pdo, string $arquivo): int
    {
        $arquivo = trim($arquivo);
        if ($arquivo === '' || preg_match('~^https?://~i', $arquivo)) {
            return 0;
        }
        $caminho = realpath(dirname(__DIR__) . '/' . ltrim(preg_replace('~^/?ead/~', '', $arquivo), '/'));
        $raiz = realpath(dirname(__DIR__) . '/uploads');
        if (!$caminho || !$raiz || strpos($caminho, $raiz) !== 0 || !is_file($caminho)) {
            return 0;
        }
        $tamanho = (int)filesize($caminho);
        $stmt = $pdo->prepare('SELECT paginas FROM meta_pdf_paginas WHERE arquivo = :a AND tamanho = :t');
        $stmt->execute([':a' => $arquivo, ':t' => $tamanho]);
        $cache = $stmt->fetchColumn();
        if ($cache !== false) {
            return (int)$cache;
        }
        $conteudo = (string)file_get_contents($caminho);
        $paginas = 0;
        // Árvore de páginas: o maior "/Count n" costuma ser o total.
        if (preg_match_all('~/Type\s*/Pages\b[^>]*?/Count\s+(\d+)~s', $conteudo, $m) || preg_match_all('~/Count\s+(\d+)[^>]*?/Type\s*/Pages\b~s', $conteudo, $m)) {
            $paginas = max(array_map('intval', $m[1]));
        }
        if ($paginas <= 0) {
            $paginas = preg_match_all('~/Type\s*/Page(?![s\w])~', $conteudo);
        }
        if ($paginas <= 0) {
            $paginas = max(1, (int)round($tamanho / 60000)); // estimativa: ~60 KB por página
        }
        $pdo->prepare('REPLACE INTO meta_pdf_paginas (arquivo, tamanho, paginas) VALUES (:a, :t, :p)')->execute([':a' => $arquivo, ':t' => $tamanho, ':p' => $paginas]);
        return $paginas;
    }
}

if (!function_exists('meta_tempo_fatores_aprendidos')) {
    /** @return array<int,array{fator:float,n:int}> por matéria (média das últimas 20 correções). */
    function meta_tempo_fatores_aprendidos(PDO $pdo): array
    {
        $fatores = [];
        $rows = $pdo->query('SELECT materia_id, calculado_min, ajustado_min FROM meta_tempo_aprendizado ORDER BY id DESC')->fetchAll();
        $grupos = [];
        foreach ($rows as $r) {
            $mid = (int)($r['materia_id'] ?? 0);
            if (count($grupos[$mid] ?? []) < 20 && (int)$r['calculado_min'] > 0) {
                $grupos[$mid][] = (int)$r['ajustado_min'] / (int)$r['calculado_min'];
            }
        }
        foreach ($grupos as $mid => $razoes) {
            $f = array_sum($razoes) / count($razoes);
            $fatores[$mid] = ['fator' => max(0.5, min(2.5, $f)), 'n' => count($razoes)];
        }
        return $fatores;
    }
}

if (!function_exists('meta_tempo_item')) {
    /**
     * Tempo do assunto em minutos: ['video','pdf','teoria','questoes','total','paginas','fonte_video','ajustado','calculado'].
     */
    function meta_tempo_item(PDO $pdo, array $item, array $cfg, array $fatores): array
    {
        $video = 0;
        $fonte = 'sem vídeo';
        $pdfArquivo = '';
        $pdfDuracaoTxt = '';
        if (!empty($item['aula_id'])) {
            $stmt = $pdo->prepare('SELECT a.video_url, a.duracao, a.pdf_arquivo, a.pdf_duracao,
                                          (SELECT MAX(p.video_duracao_segundos) FROM aulas_progresso p WHERE p.aula_id = a.id) AS real_seg
                                     FROM admin_curso_aulas a WHERE a.id = :id');
            try {
                $stmt->execute([':id' => (int)$item['aula_id']]);
                $a = $stmt->fetch() ?: [];
            } catch (Throwable $e) {
                // Sem a tabela de progresso ainda: usa só o cadastro.
                $stmt = $pdo->prepare('SELECT video_url, duracao, pdf_arquivo, pdf_duracao, 0 AS real_seg FROM admin_curso_aulas WHERE id = :id');
                $stmt->execute([':id' => (int)$item['aula_id']]);
                $a = $stmt->fetch() ?: [];
            }
            if ((int)($a['real_seg'] ?? 0) > 0) {
                $video = (int)ceil((int)$a['real_seg'] / 60);
                $fonte = 'duração real do vídeo';
            } elseif (($s = meta_tempo_texto_para_seg((string)($a['duracao'] ?? ''))) > 0) {
                $video = (int)ceil($s / 60);
                $fonte = 'duração cadastrada';
            } elseif (trim((string)($a['video_url'] ?? '')) !== '') {
                $fonte = 'vídeo sem duração (assista uma vez para medir)';
            }
            $pdfArquivo = (string)($a['pdf_arquivo'] ?? '');
            $pdfDuracaoTxt = (string)($a['pdf_duracao'] ?? '');
        }

        $complex = META_COMPLEXIDADES[$item['complexidade'] ?? 'media'] ?? META_COMPLEXIDADES['media'];
        $mid = (int)($item['materia_id'] ?? 0);
        $fatorAprendido = $fatores[$mid]['fator'] ?? 1.0;
        $paginas = meta_tempo_paginas_pdf($pdo, $pdfArquivo);
        $pdfBase = $paginas > 0
            ? $paginas * max(1, (int)($cfg['min_por_pagina'] ?? 3)) * $complex['fator']
            : meta_tempo_texto_para_seg($pdfDuracaoTxt) / 60 * $complex['fator'];
        $pct = max(10, min(90, (int)($cfg['pct_questoes'] ?? 55)));
        $minQuestoes = max(1, (int)($cfg['min_questoes'] ?? 5)) * max(1, (int)($cfg['min_por_questao'] ?? 3));

        // Base (sem o que foi aprendido): é contra ela que as correções do mentor são medidas.
        $questoesBase = max($minQuestoes, ($video + $pdfBase) * $pct / (100 - $pct));
        $base = (int)ceil($video + $pdfBase + $questoesBase);

        // Com o aprendizado: PDF e questões seguem o fator da matéria; o vídeo é fixo.
        $pdf = (int)ceil($pdfBase * $fatorAprendido);
        $teoria = $video + $pdf;
        $questoes = max($minQuestoes, (int)ceil($questoesBase * $fatorAprendido));
        $calculado = $teoria + $questoes;

        $ajustado = !empty($item['tempo_ajustado_min']) ? (int)$item['tempo_ajustado_min'] : null;
        if ($ajustado !== null && $ajustado > 0) {
            // Mentor definiu o total: mantém a teoria e ajusta as questões, sem cair do mínimo.
            $questoes = max($minQuestoes, $ajustado - $teoria);
        }

        return [
            'video' => $video, 'pdf' => $pdf, 'paginas' => $paginas, 'teoria' => $teoria,
            'questoes' => $questoes, 'total' => $teoria + $questoes, 'calculado' => $calculado, 'base' => $base,
            'ajustado' => $ajustado, 'fonte_video' => $fonte,
            'fator_aprendido' => $fatorAprendido, 'complexidade' => $complex['nome'],
        ];
    }
}

if (!function_exists('meta_tempo_registrar_ajuste')) {
    /** Mentor corrigiu o tempo: grava para o sistema aprender. */
    function meta_tempo_registrar_ajuste(PDO $pdo, array $item, int $calculadoMin, int $ajustadoMin): void
    {
        if ($calculadoMin <= 0 || $ajustadoMin <= 0 || $calculadoMin === $ajustadoMin) {
            return;
        }
        $pdo->prepare('INSERT INTO meta_tempo_aprendizado (materia_id, plano_item_id, calculado_min, ajustado_min) VALUES (:m, :i, :c, :a)')
            ->execute([':m' => $item['materia_id'] ?: null, ':i' => (int)$item['id'], ':c' => $calculadoMin, ':a' => $ajustadoMin]);
    }
}
