<?php
// includes/padrao-lib.php
// Auditoria de padrão: acha o conteúdo antigo fora do padrão da plataforma usando os dados que já temos
// (sem gastar IA para descobrir) e corrige em lote pela fila, com custo mostrado antes.
declare(strict_types=1);

const PADRAO_TEORIA_MIN = 300;      // caracteres de texto na teoria escrita da aula (abaixo disso é só "lembrete")
const PADRAO_SLIDES_MIN = 10;       // videoaula do Antigão no formato novo tem 10 a 14 slides
const PADRAO_ANTIGAO_PALAVRAS = 1000; // teoria do Antigão no formato novo tem 1.200 a 2.000 palavras

if (!function_exists('padrao_texto_len')) {
    function padrao_texto_len(?string $html): int
    {
        return mb_strlen(trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8'))));
    }

    /** Siglas e nomes que não podem virar minúsculas. */
    function padrao_siglas(): array
    {
        return ['PM', 'PMBA', 'BM', 'CBMBA', 'CBM', 'PC', 'PF', 'PRF', 'CF', 'CF/88', 'CTB', 'ECA', 'CPM', 'CPPM', 'CP', 'CPP', 'LEP', 'TI', 'PDF', 'ONU', 'EUA', 'STF', 'STJ', 'SUS',
            'IBGE', 'LGPD', 'CRFB', 'PIB', 'IDH', 'MS', 'SD', 'CFSD', 'CFOA', 'CFO', 'OAB', 'MP', 'DF', 'RH', 'URL', 'HTML', 'CPU', 'RAM', 'USB', 'WWW', 'IP', 'BA',
            'Brasil', 'Bahia', 'Salvador', 'Constituição', 'Estado', 'República', 'União', 'Federal', 'Português', 'Portugal', 'América', 'Europa', 'África', 'Nordeste',
            'Excel', 'Word', 'Windows', 'Linux', 'Internet', 'Google', 'Microsoft', 'PowerPoint', 'Outlook', 'Libreoffice', 'Writer', 'Calc'];
    }

    /**
     * Título no padrão: "7.2. Sintaxe - termos acessórios da oração"
     * (número com ponto e espaço; sem ponto no fim; sem TUDO MAIÚSCULO, mantendo siglas e nomes próprios).
     */
    function padrao_titulo(string $t): string
    {
        $t = trim(preg_replace('~\s+~u', ' ', $t));
        $num = '';
        if (preg_match('~^(\d+(?:\s*\.\s*\d+)*)\s*[.)\-–]?\s*(?=\S)~u', $t, $m) && !preg_match('~^\d+\s*\(~u', $t)) {
            $num = preg_replace('~\s+~', '', $m[1]) . '. ';
            $t = mb_substr($t, mb_strlen($m[0]));
        }
        $t = preg_replace('~\s*[-–]\s*~u', ' - ', $t);
        $t = preg_replace('~\s+([,;:])~u', '$1', $t);
        $t = rtrim($t, ". \t");
        $letras = preg_replace('~[^\p{L}]~u', '', $t);
        $maius = preg_replace('~[^\p{Lu}]~u', '', $t);
        if ($letras !== '' && mb_strlen($maius) / mb_strlen($letras) > 0.7) {
            $siglas = [];
            foreach (padrao_siglas() as $s) {
                $siglas[mb_strtoupper($s)] = $s;
            }
            $palavras = preg_split('~(\s+|(?<=[(/])|(?=[)/,:]))~u', mb_strtolower($t), -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($palavras as &$p) {
                $u = mb_strtoupper($p);
                if (isset($siglas[$u])) {
                    $p = $siglas[$u];
                } elseif (preg_match('~^(i|ii|iii|iv|v|vi|vii|viii|ix|x|xi|xii)$~', $p)) {
                    $p = $u; // algarismo romano
                }
            }
            unset($p);
            $t = implode('', $palavras);
        }
        // primeira letra maiúscula
        $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
        return $num . $t;
    }

    /** Chave para achar aulas repetidas: sem número, acento, pontuação e caixa. */
    function padrao_chave_titulo(string $t): string
    {
        $t = preg_replace('~^\s*\d+(?:\.\d+)*\s*[.)\-–]?\s*~u', '', mb_strtolower($t));
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return trim(preg_replace('~[^a-z0-9]+~', ' ', $t));
    }

    /** Fotografia do padrão (por curso ou geral). */
    function padrao_auditar(PDO $pdo, int $cursoId = 0): array
    {
        $f = $cursoId > 0 ? ' AND a.curso_id = ' . $cursoId : '';
        $aulas = $pdo->query("SELECT a.id, a.curso_id, a.modulo_id, a.titulo, a.descricao, a.video_url, a.pdf_arquivo, a.topico_id, c.nome curso
                                FROM admin_curso_aulas a LEFT JOIN admin_cursos c ON c.id = a.curso_id WHERE 1=1 $f ORDER BY c.nome, a.ordem, a.id")->fetchAll();
        $vid = [];
        try {
            foreach ($pdo->query("SELECT aula_id, status, roteiro FROM aula_videoaulas") as $v) {
                $j = json_decode((string)$v['roteiro'], true);
                $sl = (array)($j['slides'] ?? []);
                $palavras = 0;
                foreach ($sl as $s) {
                    $palavras += count(preg_split('~\s+~u', trim((string)($s['narracao'] ?? ''))) ?: []);
                }
                $vid[(int)$v['aula_id']] = ['status' => $v['status'], 'slides' => count($sl), 'novo' => (int)($j['formato'] ?? 0) >= 2,
                    'palavras_slide' => $sl ? (int)round($palavras / count($sl)) : 0];
            }
        } catch (Throwable $e) {
        }
        $pod = [];
        try {
            foreach ($pdo->query("SELECT aula_id, status, (roteiro LIKE '%Antig%' OR roteiro LIKE '%Zero Um%') novo FROM aula_podcasts") as $p) {
                $pod[(int)$p['aula_id']] = ['status' => $p['status'], 'novo' => (bool)$p['novo']];
            }
        } catch (Throwable $e) {
        }
        $fila = [];
        foreach ($pdo->query("SELECT tipo, aula_id FROM ia_fila WHERE status = 'pendente' OR (status = 'erro' AND erro LIKE 'Aguardando%')") as $q) {
            $fila[$q['tipo']][(int)$q['aula_id']] = true;
        }
        $r = ['titulos' => [], 'duplicadas' => [], 'sem_teoria' => [], 'sem_material' => [], 'videoaula_antiga' => [], 'antigao_antigo' => [], 'podcast_antigo' => [], 'sem_assunto' => [], 'total' => count($aulas)];
        $grupos = [];
        foreach ($aulas as $a) {
            $id = (int)$a['id'];
            $temVideo = trim((string)$a['video_url']) !== '';
            $temPdf = trim((string)$a['pdf_arquivo']) !== '';
            $teoria = padrao_texto_len((string)$a['descricao']);
            $novo = padrao_titulo((string)$a['titulo']);
            if ($novo !== trim((string)$a['titulo'])) {
                $r['titulos'][] = ['id' => $id, 'curso' => $a['curso'], 'de' => (string)$a['titulo'], 'para' => $novo];
            }
            $grupos[(int)$a['curso_id'] . '|' . padrao_chave_titulo((string)$a['titulo'])][] = $a;
            $naFila = isset($fila['apostila'][$id]) || isset($fila['aula_ia'][$id]);
            if (!$temVideo && !$temPdf && $teoria < PADRAO_TEORIA_MIN && !$naFila) {
                $r['sem_material'][] = $a;
            } elseif (($temVideo || $temPdf) && $teoria < PADRAO_TEORIA_MIN && !$naFila) {
                $r['sem_teoria'][] = $a;
            }
            // Videoaula do Antigão fora do padrão novo: sem a marca de formato E (poucos slides OU narração curta/rápida)
            if (isset($vid[$id]) && $vid[$id]['status'] === 'pronto' && !$vid[$id]['novo'] && !isset($fila['videoaula'][$id])
                && ($vid[$id]['slides'] < PADRAO_SLIDES_MIN || $vid[$id]['palavras_slide'] < 100)) {
                $r['videoaula_antiga'][] = $a + ['slides' => $vid[$id]['slides'], 'palavras_slide' => $vid[$id]['palavras_slide']];
            }
            // Aula do Antigão escrita no formato antigo (teoria curta, sem divisão por foco)
            if (strpos((string)$a['descricao'], 'aula-ia-aviso') !== false && !$temVideo && !$temPdf && !isset($fila['aula_ia'][$id])
                && str_word_count(strip_tags((string)$a['descricao']), 0, 'áàâãéêíóôõúçÁÀÂÃÉÊÍÓÔÕÚÇ') < PADRAO_ANTIGAO_PALAVRAS) {
                $r['antigao_antigo'][] = $a;
            }
            // Podcast gravado antes do Antigão e do Recruta Zero Um
            if (isset($pod[$id]) && $pod[$id]['status'] === 'pronto' && !$pod[$id]['novo'] && !isset($fila['podcast'][$id])) {
                $r['podcast_antigo'][] = $a;
            }
            if (empty($a['topico_id'])) {
                $r['sem_assunto'][] = $a;
            }
        }
        foreach ($grupos as $g) {
            if (count($g) > 1 && padrao_chave_titulo((string)$g[0]['titulo']) !== '') {
                $r['duplicadas'][] = $g;
            }
        }
        // Questões
        $q = $pdo->query("SELECT COUNT(*) total,
                                 SUM(COALESCE(CHAR_LENGTH(TRIM(explicacao)),0) < 40) sem_comentario,
                                 SUM(materia_topico_id IS NULL OR materia_topico_id = 0) sem_assunto,
                                 SUM(dificuldade IS NULL OR dificuldade = '') sem_dificuldade
                            FROM admin_banco_questoes WHERE status = 'ativo'")->fetch();
        $r['questoes'] = $q ?: [];
        $r['questoes']['sem_gabarito'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_banco_questoes q WHERE q.status = 'ativo'
                                                            AND NOT EXISTS (SELECT 1 FROM admin_banco_questao_opcoes o WHERE o.questao_id = q.id AND o.correta = 1)")->fetchColumn();
        try {
            require_once __DIR__ . '/revisao-aulas-lib.php';
            $sit = "CASE WHEN TRIM(COALESCE(a.pdf_arquivo,'')) = '' THEN 'sem_pdf' WHEN r.aula_id IS NULL OR r.pdf <> a.pdf_arquivo THEN 'pendente' ELSE r.status END";
            $r['pdf'] = $pdo->query("SELECT $sit s, COUNT(*) n FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id WHERE 1=1 $f GROUP BY s")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            $r['pdf'] = [];
        }
        // Nota geral: % de aulas sem nenhum problema
        $ruins = [];
        foreach (['titulos', 'sem_teoria', 'sem_material', 'videoaula_antiga', 'antigao_antigo', 'podcast_antigo', 'sem_assunto'] as $k) {
            foreach ($r[$k] as $x) {
                $ruins[(int)$x['id']] = true;
            }
        }
        $r['ok_pct'] = $r['total'] ? (int)round(($r['total'] - count($ruins)) / $r['total'] * 100) : 100;
        return $r;
    }

    /** Correções em lote. Retorna quantos itens foram para a fila / alterados. */
    function padrao_corrigir(PDO $pdo, string $acao, int $cursoId = 0, array $ids = []): int
    {
        require_once __DIR__ . '/ia-fila-lib.php';
        $aud = padrao_auditar($pdo, $cursoId);
        $n = 0;
        if ($acao === 'padrao_titulos') {
            $sel = array_flip(array_map('intval', $ids));
            $up = $pdo->prepare('UPDATE admin_curso_aulas SET titulo = :t WHERE id = :id');
            foreach ($aud['titulos'] as $t) {
                if (!$sel || isset($sel[$t['id']])) {
                    $up->execute([':t' => $t['para'], ':id' => $t['id']]);
                    $n++;
                }
            }
        } elseif ($acao === 'padrao_teoria') {
            foreach ($aud['sem_teoria'] as $a) {
                ia_fila_adicionar($pdo, 'apostila', (int)$a['id']); // a IA escreve a teoria a partir do vídeo/PDF (PDF só depois de conferido)
                $n++;
            }
        } elseif ($acao === 'padrao_antigao') {
            foreach ($aud['sem_material'] as $a) {
                ia_fila_adicionar($pdo, 'aula_ia', (int)$a['id']); // teoria pelo assunto do edital + videoaula
                $n++;
            }
        } elseif ($acao === 'padrao_videoaulas') {
            foreach ($aud['videoaula_antiga'] as $a) {
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :id')->execute([':id' => (int)$a['id']]);
                ia_fila_adicionar($pdo, 'videoaula', (int)$a['id']);
                $n++;
            }
        } elseif ($acao === 'padrao_antigao_refazer') {
            foreach ($aud['antigao_antigo'] as $a) {
                // teoria nova (1.200–2.000 palavras, dividida por foco) e, depois dela, videoaula e podcast novos
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :id')->execute([':id' => (int)$a['id']]);
                try { $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :id')->execute([':id' => (int)$a['id']]); } catch (Throwable $e) {}
                ia_fila_adicionar($pdo, 'aula_ia', (int)$a['id']);
                $n++;
            }
        } elseif ($acao === 'padrao_podcasts') {
            foreach ($aud['podcast_antigo'] as $a) {
                $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :id')->execute([':id' => (int)$a['id']]);
                ia_fila_adicionar($pdo, 'podcast', (int)$a['id']);
                $n++;
            }
        } elseif ($acao === 'padrao_assunto') {
            require_once __DIR__ . '/ia-classificar-lib.php';
            $n = ia_class_varrer($pdo);
        }
        return $n;
    }
}
