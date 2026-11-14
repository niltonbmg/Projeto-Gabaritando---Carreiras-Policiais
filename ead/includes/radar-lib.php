<?php
// includes/radar-lib.php
// 📡 Radar do Concurso: a IA vigia a internet pelo concurso de cada aluno (edital, cronograma, banca, convocações,
// decisões judiciais, nomeações) e mostra as novidades em "Meu concurso" e no sininho, sempre com a fonte.
//  - Só concursos que têm aluno escolhido; no máximo 1 pesquisa por concurso por dia (RADAR_HORAS).
//  - Usa a cota de "pesquisa" e respeita o Controle rígido (tipo 'radar' e teto diário).
//  - Nada inventado: só fatos com link da fonte; notícia repetida não entra de novo.
declare(strict_types=1);

const RADAR_HORAS = 24;

if (!function_exists('radar_bootstrap')) {
    function radar_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS concurso_noticias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concurso_id INT UNSIGNED NOT NULL,
            tipo VARCHAR(20) NOT NULL DEFAULT 'geral',
            titulo VARCHAR(255) NOT NULL,
            resumo TEXT NULL,
            o_que_fazer VARCHAR(400) NULL,
            fonte_url VARCHAR(600) NULL,
            fonte_nome VARCHAR(120) NULL,
            publicada_em DATE NULL,
            importante TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('publicada','oculta') NOT NULL DEFAULT 'publicada',
            chave CHAR(40) NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_radar (concurso_id, chave),
            KEY idx_radar (concurso_id, status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function radar_tipos(): array
    {
        return ['edital' => '📢 Edital', 'cronograma' => '📅 Datas', 'inscricao' => '📝 Inscrições', 'resultado' => '🏁 Resultado', 'convocacao' => '📣 Convocação',
            'judicial' => '⚖️ Justiça', 'nomeacao' => '🎖 Nomeação', 'banca' => '🏛 Banca', 'geral' => '📰 Notícia'];
    }

    /** Põe na fila o radar dos concursos que têm aluno e não foram pesquisados nas últimas RADAR_HORAS. */
    function radar_varrer(PDO $pdo): int
    {
        radar_bootstrap($pdo);
        require_once __DIR__ . '/ia-fila-lib.php';
        $ids = $pdo->query("SELECT DISTINCT ac.concurso_id FROM aluno_concurso ac
                             LEFT JOIN ia_fila f ON f.tipo = 'radar' AND f.aula_id = ac.concurso_id
                            WHERE f.id IS NULL OR (f.status <> 'pendente' AND f.atualizado_em < NOW() - INTERVAL " . RADAR_HORAS . " HOUR)")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            ia_fila_adicionar($pdo, 'radar', (int)$id);
        }
        return count($ids);
    }

    /** A pesquisa em si (chamada pela fila). Retorna quantas notícias novas entraram. */
    function radar_pesquisar(PDO $pdo, int $concursoId): int
    {
        radar_bootstrap($pdo);
        $st = $pdo->prepare('SELECT c.*, (SELECT GROUP_CONCAT(nome SEPARATOR ", ") FROM concurso_cargos WHERE concurso_id = c.id) cargos FROM concursos c WHERE c.id = :id');
        $st->execute([':id' => $concursoId]);
        $c = $st->fetch();
        if (!$c) {
            throw new RuntimeException('Concurso não existe mais.');
        }
        $info = json_decode((string)($c['edital_info'] ?? ''), true) ?: [];
        $banca = (string)($info['banca'] ?? '');
        $ja = $pdo->prepare('SELECT titulo FROM concurso_noticias WHERE concurso_id = :c ORDER BY id DESC LIMIT 15');
        $ja->execute([':c' => $concursoId]);
        $conhecidas = implode("\n- ", $ja->fetchAll(PDO::FETCH_COLUMN));
        $prompt = "Você é o RADAR do Projeto Gabaritando: acompanha um concurso para avisar os alunos de tudo que importa.\n"
            . "CONCURSO: {$c['nome']}\nCARGOS: {$c['cargos']}\n" . ($banca !== '' ? "BANCA: $banca\n" : '') . 'SITUAÇÃO NA PLATAFORMA: ' . ($c['situacao'] ?? '-') . "\n\n"
            . "Pesquise na internet (sites oficiais da corporação e do governo, Diário Oficial, site da banca, portais de concursos e de notícias sérios) as novidades dos ÚLTIMOS 60 DIAS sobre este concurso: "
            . "autorização, comissão, banca escolhida, edital publicado, datas (inscrição, prova, TAF, exames), retificações, resultados, convocações, decisões judiciais, nomeações, previsão de novo concurso.\n"
            . "Regras: só fatos com fonte (link); nada de boato ou opinião; se não houver novidade, devolva lista vazia; não repita estas já conhecidas:\n- " . ($conhecidas ?: '(nenhuma)') . "\n"
            . "Para cada novidade diga em uma frase o que o aluno deve fazer (ex.: 'Inscrições até 10/12: separe os documentos').\n"
            . 'Responda SOMENTE em JSON: {"noticias":[{"tipo":"edital|cronograma|inscricao|resultado|convocacao|judicial|nomeacao|banca|geral","titulo":"até 100 caracteres","resumo":"2 a 3 frases","o_que_fazer":"1 frase","fonte_url":"https://...","fonte_nome":"site","data":"AAAA-MM-DD ou vazio","importante":true|false}]}';
        $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'tools' => [['google_search' => new stdClass()]]], 150);
        $txt = (string)($r['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (preg_match('~\{[\s\S]*\}~', $txt, $m)) {
            $txt = $m[0];
        }
        $j = json_decode($txt, true);
        $n = 0;
        require_once __DIR__ . '/texto-limpo-lib.php';
        $ins = $pdo->prepare('INSERT IGNORE INTO concurso_noticias (concurso_id, tipo, titulo, resumo, o_que_fazer, fonte_url, fonte_nome, publicada_em, importante, chave)
                              VALUES (:c, :t, :ti, :r, :o, :u, :f, :d, :i, :k)');
        foreach ((array)($j['noticias'] ?? []) as $x) {
            $titulo = trim((string)($x['titulo'] ?? ''));
            $url = trim((string)($x['fonte_url'] ?? ''));
            if ($titulo === '' || !preg_match('~^https?://~i', $url)) {
                continue; // sem fonte não publica
            }
            $tipo = array_key_exists($x['tipo'] ?? '', radar_tipos()) ? $x['tipo'] : 'geral';
            $data = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($x['data'] ?? '')) ? $x['data'] : null;
            $chave = sha1(mb_strtolower(preg_replace('~[^\p{L}\p{N}]+~u', '', $titulo)) . '|' . parse_url($url, PHP_URL_HOST));
            $ins->execute([':c' => $concursoId, ':t' => $tipo, ':ti' => mb_substr(gab_sem_latex($titulo), 0, 255), ':r' => mb_substr(trim((string)($x['resumo'] ?? '')), 0, 1500),
                ':o' => mb_substr(trim((string)($x['o_que_fazer'] ?? '')), 0, 400), ':u' => mb_substr($url, 0, 600), ':f' => mb_substr(trim((string)($x['fonte_nome'] ?? (string)parse_url($url, PHP_URL_HOST))), 0, 120),
                ':d' => $data, ':i' => !empty($x['importante']) || in_array($tipo, ['edital', 'cronograma', 'inscricao', 'judicial', 'convocacao'], true) ? 1 : 0, ':k' => $chave]);
            $n += $ins->rowCount();
        }
        return $n;
    }

    function radar_noticias(PDO $pdo, int $concursoId, int $limite = 20): array
    {
        try {
            radar_bootstrap($pdo);
            $st = $pdo->prepare("SELECT * FROM concurso_noticias WHERE concurso_id = :c AND status = 'publicada' ORDER BY COALESCE(publicada_em, DATE(criado_em)) DESC, id DESC LIMIT " . (int)$limite);
            $st->execute([':c' => $concursoId]);
            return $st->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }

    function radar_ultima_busca(PDO $pdo, int $concursoId): ?string
    {
        try {
            $st = $pdo->prepare("SELECT atualizado_em FROM ia_fila WHERE tipo = 'radar' AND aula_id = :c AND status = 'feito'");
            $st->execute([':c' => $concursoId]);
            $v = $st->fetchColumn();
            return $v ? (string)$v : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
