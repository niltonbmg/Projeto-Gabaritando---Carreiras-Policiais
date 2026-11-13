<?php
// includes/revisao-aulas-lib.php
// Revisão das aulas: nenhuma aula com PDF errado chega ao aluno nem vira base para o Antigão.
//  1) A IA confere se o PDF trata do assunto da aula (selo: confere / suspeita).
//  2) O mentor decide na tela "Revisão de aulas" (está certo / trocar PDF / sem PDF, Antigão gera pelo edital).
//  3) Travas: videoaula, apostila e podcast só são gerados de aula conferida; PDF suspeito some para o aluno.
//  4) O aluno pode reportar problema na aula; o reporte entra na revisão.
// A conferência vale para aquele PDF naquela aula: se trocar o PDF, confere de novo.
declare(strict_types=1);

const REV_ESPERA = 'Aguardando revisão do PDF';

if (!function_exists('rev_bootstrap')) {
    function rev_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_revisao (
            aula_id INT UNSIGNED NOT NULL PRIMARY KEY,
            pdf VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('pendente','confere','suspeita','ok_manual') NOT NULL DEFAULT 'pendente',
            assunto_pdf VARCHAR(255) NULL,
            motivo VARCHAR(600) NULL,
            por VARCHAR(20) NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_rev_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_reportes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            aula_id INT UNSIGNED NOT NULL,
            usuario_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(30) NOT NULL,
            detalhe VARCHAR(600) NULL,
            status ENUM('aberto','resolvido') NOT NULL DEFAULT 'aberto',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rep_aula (aula_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Situação da aula: sem_pdf | pendente | confere | suspeita | ok_manual (o selo só vale para o PDF atual). */
    function rev_status(PDO $pdo, int $aulaId, ?string $pdfAtual = null): array
    {
        rev_bootstrap($pdo);
        if ($pdfAtual === null) {
            $st = $pdo->prepare('SELECT pdf_arquivo FROM admin_curso_aulas WHERE id = :id');
            $st->execute([':id' => $aulaId]);
            $pdfAtual = (string)$st->fetchColumn();
        }
        if (trim($pdfAtual) === '') {
            return ['status' => 'sem_pdf'];
        }
        $st = $pdo->prepare('SELECT * FROM aula_revisao WHERE aula_id = :id');
        $st->execute([':id' => $aulaId]);
        $r = $st->fetch();
        if (!$r || $r['pdf'] !== $pdfAtual) {
            return ['status' => 'pendente'];
        }
        return $r;
    }

    function rev_liberada(PDO $pdo, int $aulaId, ?string $pdfAtual = null): bool
    {
        return in_array(rev_status($pdo, $aulaId, $pdfAtual)['status'], ['sem_pdf', 'confere', 'ok_manual'], true);
    }

    /** Põe na fila a conferência das aulas com PDF novo ou trocado (o robô chama a cada execução). */
    function rev_varrer(PDO $pdo, int $limite = 300): int
    {
        rev_bootstrap($pdo);
        require_once __DIR__ . '/ia-fila-lib.php';
        $ids = $pdo->query("SELECT a.id FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id
                             LEFT JOIN ia_fila f ON f.tipo = 'conferir_pdf' AND f.aula_id = a.id AND f.status = 'pendente'
                            WHERE TRIM(COALESCE(a.pdf_arquivo,'')) <> '' AND (r.aula_id IS NULL OR r.pdf <> a.pdf_arquivo) AND f.id IS NULL
                            LIMIT " . (int)$limite)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            ia_fila_adicionar($pdo, 'conferir_pdf', (int)$id);
        }
        // Videoaula esperando revisão numa aula SEM PDF e sem vídeo: não há material para conferir.
        // O Antigão escreve a teoria pelo assunto do edital (e grava a videoaula depois).
        $semPdf = $pdo->query("SELECT f.id, f.aula_id FROM ia_fila f JOIN admin_curso_aulas a ON a.id = f.aula_id
                                WHERE f.tipo = 'videoaula' AND f.status = 'erro' AND (f.erro LIKE 'Aguardando revisão do PDF%' OR f.erro LIKE 'Aguardando conferência do PDF%')
                                  AND TRIM(COALESCE(a.pdf_arquivo,'')) = '' AND TRIM(COALESCE(a.video_url,'')) = ''")->fetchAll();
        foreach ($semPdf as $f) {
            $pdo->prepare('DELETE FROM ia_fila WHERE id = :id')->execute([':id' => (int)$f['id']]);
            ia_fila_adicionar($pdo, 'aula_ia', (int)$f['aula_id']);
        }
        return count($ids) + count($semPdf);
    }

    function rev_gravar(PDO $pdo, int $aulaId, string $pdf, string $status, ?string $assunto, ?string $motivo, string $por): void
    {
        rev_bootstrap($pdo);
        $pdo->prepare('INSERT INTO aula_revisao (aula_id, pdf, status, assunto_pdf, motivo, por) VALUES (:a, :p, :s, :as, :m, :por)
                       ON DUPLICATE KEY UPDATE pdf = VALUES(pdf), status = VALUES(status), assunto_pdf = VALUES(assunto_pdf), motivo = VALUES(motivo), por = VALUES(por)')
            ->execute([':a' => $aulaId, ':p' => $pdf, ':s' => $status, ':as' => $assunto ? mb_substr($assunto, 0, 255) : null, ':m' => $motivo ? mb_substr($motivo, 0, 600) : null, ':por' => $por]);
        if (in_array($status, ['confere', 'ok_manual'], true)) {
            rev_liberar_espera($pdo, $aulaId);
        }
    }

    /** Aula liberada: o que estava esperando a revisão (videoaula, apostila, podcast) volta para a fila. */
    function rev_liberar_espera(PDO $pdo, int $aulaId): void
    {
        $pdo->prepare("UPDATE ia_fila SET status = 'pendente', tentativas = 0, erro = NULL
                        WHERE aula_id = :a AND tipo IN ('videoaula','apostila','podcast') AND status = 'erro'
                          AND (erro LIKE 'Aguardando revisão do PDF%' OR erro LIKE 'Aguardando conferência do PDF%')")
            ->execute([':a' => $aulaId]);
    }

    /** Segura a tarefa da IA até a aula ser conferida. true = segurou (não gerar agora). */
    function rev_segurar_tarefa(PDO $pdo, array $item): bool
    {
        if (!in_array($item['tipo'], ['videoaula', 'apostila', 'podcast'], true)) {
            return false;
        }
        $aulaId = (int)$item['aula_id'];
        $s = rev_status($pdo, $aulaId)['status'];
        if (in_array($s, ['sem_pdf', 'confere', 'ok_manual'], true)) {
            return false;
        }
        $pdo->prepare("UPDATE ia_fila SET status = 'erro', tentativas = 9, erro = :e WHERE id = :id")
            ->execute([':e' => REV_ESPERA . ($s === 'suspeita' ? ' (PDF suspeito: veja Revisão de aulas)' : ' (conferência na fila)'), ':id' => (int)$item['id']]);
        if ($s === 'pendente') {
            require_once __DIR__ . '/ia-fila-lib.php';
            ia_fila_adicionar($pdo, 'conferir_pdf', $aulaId);
        }
        return true;
    }

    /** Lê o PDF da aula (arquivo da plataforma ou link), até 15 MB. */
    function rev_pdf_base64(string $pdf): string
    {
        if (preg_match('~^https?://~i', $pdf)) {
            $ch = curl_init($pdf);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 40, CURLOPT_MAXFILESIZE => 15 * 1024 * 1024]);
            $b = curl_exec($ch);
            curl_close($ch);
            return is_string($b) && str_starts_with($b, '%PDF') ? base64_encode($b) : '';
        }
        $caminho = realpath(dirname(__DIR__) . '/' . ltrim(preg_replace('~^/?ead/~', '', $pdf), '/'));
        $raiz = realpath(dirname(__DIR__) . '/uploads');
        if ($caminho && $raiz && strpos($caminho, $raiz) === 0 && is_file($caminho) && filesize($caminho) <= 15 * 1024 * 1024) {
            return base64_encode((string)file_get_contents($caminho));
        }
        return '';
    }

    /** A IA confere se o PDF é do assunto da aula. Chamado pela fila (tipo conferir_pdf). */
    function rev_conferir(PDO $pdo, int $aulaId): array
    {
        rev_bootstrap($pdo);
        $st = $pdo->prepare('SELECT a.id, a.titulo, a.pdf_arquivo, m.nome AS materia, t.titulo AS assunto, mo.titulo AS modulo, c.nome AS curso
                               FROM admin_curso_aulas a LEFT JOIN admin_materias m ON m.id = a.materia_id LEFT JOIN admin_materia_topicos t ON t.id = a.topico_id
                               LEFT JOIN admin_curso_modulos mo ON mo.id = a.modulo_id LEFT JOIN admin_cursos c ON c.id = a.curso_id WHERE a.id = :id');
        $st->execute([':id' => $aulaId]);
        $a = $st->fetch();
        if (!$a) {
            throw new RuntimeException('Aula não existe mais.');
        }
        $pdf = trim((string)$a['pdf_arquivo']);
        if ($pdf === '') {
            return ['status' => 'sem_pdf'];
        }
        // Mesmo PDF numa aula de mesmo título (cópia em outro curso): herda o resultado, sem gastar IA.
        $h = $pdo->prepare("SELECT r.* FROM aula_revisao r JOIN admin_curso_aulas o ON o.id = r.aula_id
                             WHERE r.pdf = :p AND r.aula_id <> :id AND LOWER(TRIM(o.titulo)) = LOWER(TRIM(:t)) AND r.status IN ('confere','suspeita','ok_manual') LIMIT 1");
        $h->execute([':p' => $pdf, ':id' => $aulaId, ':t' => (string)$a['titulo']]);
        if ($o = $h->fetch()) {
            rev_gravar($pdo, $aulaId, $pdf, $o['status'] === 'ok_manual' ? 'ok_manual' : $o['status'], $o['assunto_pdf'], $o['motivo'], 'copia');
            return ['status' => $o['status'], 'herdado' => true];
        }
        $b64 = rev_pdf_base64($pdf);
        if ($b64 === '') {
            rev_gravar($pdo, $aulaId, $pdf, 'suspeita', null, 'Não consegui abrir o PDF (arquivo sumiu, link fora do ar ou maior que 15 MB). Confira manualmente.', 'ia');
            return ['status' => 'suspeita'];
        }
        $prompt = "Você é revisor de material de cursinho para concursos policiais. Confira se o PDF anexo é o material certo desta aula.\n"
            . 'CURSO: ' . $a['curso'] . "\nMATÉRIA: " . ($a['materia'] ?: '(não informada)') . "\nMÓDULO: " . ($a['modulo'] ?: '-') . "\nASSUNTO DO EDITAL: " . ($a['assunto'] ?: '-') . "\nTÍTULO DA AULA: " . $a['titulo'] . "\n\n"
            . "Regras: 'confere' se o PDF trata principalmente do assunto da aula (pode ter mais conteúdo junto, ex.: apostila da matéria inteira que inclui o assunto). "
            . "'suspeita' se o PDF é de outro assunto ou outra matéria, está em branco/ilegível ou é só capa.\n"
            . 'Responda SOMENTE em JSON: {"resultado":"confere|suspeita","assunto_do_pdf":"do que o PDF trata, em até 12 palavras","motivo":"uma frase curta"}';
        $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['inline_data' => ['mime_type' => 'application/pdf', 'data' => $b64]], ['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 90);
        $j = json_decode((string)($r['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
        $res = ($j['resultado'] ?? '') === 'confere' ? 'confere' : 'suspeita';
        rev_gravar($pdo, $aulaId, $pdf, $res, (string)($j['assunto_do_pdf'] ?? ''), (string)($j['motivo'] ?? 'A IA não deu motivo.'), 'ia');
        return ['status' => $res, 'assunto' => (string)($j['assunto_do_pdf'] ?? '')];
    }

    /** PDFs já existentes na plataforma, ordenados pelos que mais parecem com o assunto da aula. */
    function rev_sugerir_pdfs(PDO $pdo, string $titulo, string $materia, int $limite = 60): array
    {
        $palavras = array_filter(preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower($titulo . ' ' . $materia)) ?: [], static fn($p) => mb_strlen($p) >= 4);
        $lista = $pdo->query("SELECT a.pdf_arquivo pdf, MAX(r.assunto_pdf) assunto, MAX(r.status) st FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id AND r.pdf = a.pdf_arquivo
                               WHERE TRIM(COALESCE(a.pdf_arquivo,'')) <> '' GROUP BY a.pdf_arquivo")->fetchAll();
        foreach ($lista as &$l) {
            $alvo = mb_strtolower(basename((string)$l['pdf']) . ' ' . $l['assunto']);
            $l['nota'] = 0;
            foreach ($palavras as $p) {
                if (mb_strpos($alvo, $p) !== false) {
                    $l['nota']++;
                }
            }
        }
        unset($l);
                usort($lista, static fn($x, $y) => [$y['nota'], basename((string)$x['pdf'])] <=> [$x['nota'], basename((string)$y['pdf'])]); // parecidos primeiro, depois em ordem alfabética
        return array_slice($lista, 0, $limite);
    }
}

if (!function_exists('rev_sugestoes_troca')) {
    /** Nota de 0 a 1: quanto o assunto do PDF (dito pela IA) combina com o título/assunto de uma aula. */
    function rev_combina(string $assuntoPdf, string $tituloAula): float
    {
        require_once __DIR__ . '/padrao-lib.php';
        $pal = static function (string $t): array {
            $t = padrao_chave_titulo(preg_replace('~ · Parte \d+.*$~u', '', $t));
            $t = preg_replace('~\b(teoria|minima|exercicios|parte|aula|introducao|nocoes|conceitos|basicos|dos|das|para|com|sobre|brasil)\b~', ' ', $t);
            return array_values(array_unique(array_filter(explode(' ', $t), static fn($p) => strlen($p) >= 4)));
        };
        $x = $pal($assuntoPdf);
        $y = $pal($tituloAula);
        if (!$x || !$y) {
            return 0.0;
        }
        return count(array_intersect($x, $y)) / min(count($x), count($y));
    }

    /**
     * Troca inteligente: para cada aula com PDF suspeito, onde está o lugar certo desse PDF (outra aula do mesmo curso
     * cujo assunto bate com o que o PDF trata) e, se existir, qual PDF da plataforma é o certo para ela.
     * Só move para aula SEM PDF ou com PDF também suspeito (nunca sobrescreve PDF conferido ou ainda não conferido).
     */
    function rev_sugestoes_troca(PDO $pdo, int $cursoId = 0): array
    {
        rev_bootstrap($pdo);
        $f = $cursoId > 0 ? ' AND a.curso_id = ' . $cursoId : '';
        $aulas = $pdo->query("SELECT a.id, a.curso_id, a.titulo, a.pdf_arquivo, a.pdf_nome, a.video_url, t.titulo assunto, c.nome curso,
                                     CASE WHEN TRIM(COALESCE(a.pdf_arquivo,'')) = '' THEN 'sem_pdf' WHEN r.aula_id IS NULL OR r.pdf <> a.pdf_arquivo THEN 'pendente' ELSE r.status END situacao,
                                     r.assunto_pdf
                                FROM admin_curso_aulas a LEFT JOIN admin_materia_topicos t ON t.id = a.topico_id LEFT JOIN admin_cursos c ON c.id = a.curso_id
                                LEFT JOIN aula_revisao r ON r.aula_id = a.id WHERE 1=1 $f")->fetchAll();
        $porCurso = [];
        foreach ($aulas as $a) {
            $porCurso[(int)$a['curso_id']][] = $a;
        }
        $movimentos = [];
        $usadas = [];
        foreach ($aulas as $a) {
            if ($a['situacao'] !== 'suspeita' || trim((string)$a['assunto_pdf']) === '') {
                continue;
            }
            $melhor = null;
            $nota = 0.0;
            foreach ($porCurso[(int)$a['curso_id']] as $b) {
                if ((int)$b['id'] === (int)$a['id'] || isset($usadas[(int)$b['id']]) || !in_array($b['situacao'], ['sem_pdf', 'suspeita'], true)) {
                    continue;
                }
                $n = max(rev_combina((string)$a['assunto_pdf'], (string)$b['titulo']), rev_combina((string)$a['assunto_pdf'], (string)$b['assunto']));
                if ($n > $nota) {
                    $nota = $n;
                    $melhor = $b;
                }
            }
            if ($melhor && $nota >= 0.5) {
                $usadas[(int)$melhor['id']] = true;
                $movimentos[] = ['pdf' => (string)$a['pdf_arquivo'], 'pdf_nome' => (string)($a['pdf_nome'] ?: basename((string)$a['pdf_arquivo'])), 'assunto_pdf' => (string)$a['assunto_pdf'],
                    'de_id' => (int)$a['id'], 'de' => (string)$a['titulo'], 'para_id' => (int)$melhor['id'], 'para' => (string)$melhor['titulo'], 'curso' => (string)$a['curso'], 'nota' => round($nota, 2)];
            }
        }
        // Suspeitas que não recebem nenhum PDF pelas trocas: ficam sem PDF certo (mentor envia ou o Antigão gera pelo edital)
        $recebem = array_flip(array_column($movimentos, 'para_id'));
        $semCerto = [];
        foreach ($aulas as $a) {
            if ($a['situacao'] === 'suspeita' && !isset($recebem[(int)$a['id']])) {
                $semCerto[] = ['id' => (int)$a['id'], 'titulo' => (string)$a['titulo'], 'curso' => (string)$a['curso'], 'assunto_pdf' => (string)$a['assunto_pdf']];
            }
        }
        return ['movimentos' => $movimentos, 'sem_pdf_certo' => $semCerto];
    }

    /** Aplica as trocas sugeridas de uma vez. Retorna quantos PDFs foram para o lugar certo. */
    function rev_aplicar_trocas(PDO $pdo, int $cursoId = 0, array $somente = []): int
    {
        require_once __DIR__ . '/ia-fila-lib.php';
        $s = rev_sugestoes_troca($pdo, $cursoId);
        $sel = array_flip(array_map('intval', $somente));
        $aplicar = array_values(array_filter($s['movimentos'], static fn($m) => !$sel || isset($sel[$m['de_id']])));
        if (!$aplicar) {
            return 0;
        }
        $pdo->beginTransaction();
        try {
            $novoPdf = [];
            foreach ($aplicar as $m) {
                $novoPdf[$m['para_id']] = [$m['pdf'], $m['pdf_nome']];
            }
            // Quem entregou o PDF e não recebeu outro fica sem PDF (continua escondido do aluno até o mentor decidir).
            foreach ($aplicar as $m) {
                if (!isset($novoPdf[$m['de_id']])) {
                    $novoPdf[$m['de_id']] = ['', ''];
                }
            }
            $up = $pdo->prepare('UPDATE admin_curso_aulas SET pdf_arquivo = :p, pdf_nome = :n WHERE id = :id');
            foreach ($novoPdf as $aulaId => [$p, $n]) {
                $up->execute([':p' => $p, ':n' => $n, ':id' => $aulaId]);
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $aulaId]);
                $pdo->prepare('DELETE FROM aula_revisao WHERE aula_id = :a')->execute([':a' => $aulaId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        foreach ($novoPdf as $aulaId => [$p, $n]) {
            if ($p !== '') {
                rev_gravar($pdo, (int)$aulaId, $p, 'ok_manual', null, 'PDF movido para a aula certa (troca inteligente aprovada pelo mentor).', 'mentor');
                $st = $pdo->prepare("SELECT TRIM(COALESCE(video_url,'')) FROM admin_curso_aulas WHERE id = :id");
                $st->execute([':id' => $aulaId]);
                if ($st->fetchColumn() === '') {
                    ia_fila_adicionar($pdo, 'videoaula', (int)$aulaId);
                }
            }
        }
        return count($aplicar);
    }
}
