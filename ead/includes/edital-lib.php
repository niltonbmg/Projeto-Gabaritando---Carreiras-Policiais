<?php
// includes/edital-lib.php
// Concurso -> cargos -> edital (matérias e assuntos por cargo) -> aulas.
// A IA lê o conteúdo programático e monta o edital; depois, para cada link de
// aula do YouTube, sugere o assunto do edital e os cargos. O mentor confirma.
// O andamento mostra, por cargo, quais assuntos do edital já têm aula.
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';

if (!function_exists('edital_bootstrap')) {
    function edital_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS concursos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(180) NOT NULL,
            banca_id INT UNSIGNED NULL,
            situacao ENUM('aberto','previsto','encerrado') NOT NULL DEFAULT 'previsto',
            base_edital VARCHAR(180) NULL,
            data_prova DATE NULL,
            edital_url VARCHAR(500) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS concurso_cargos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concurso_id INT UNSIGNED NOT NULL,
            nome VARCHAR(180) NOT NULL,
            curso_id INT UNSIGNED NULL,
            KEY idx_cargos_concurso (concurso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS edital_itens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concurso_id INT UNSIGNED NOT NULL,
            materia_id INT UNSIGNED NOT NULL,
            topico_id INT UNSIGNED NOT NULL,
            cargos VARCHAR(255) NOT NULL DEFAULT '',
            ordem INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_edital_item (concurso_id, topico_id),
            KEY idx_edital_concurso (concurso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $feito = true;
    }
}

if (!function_exists('edital_ia_ler')) {
    /**
     * IA lê o conteúdo programático (texto colado e/ou PDF) e devolve
     * [{materia, cargos:[nomes]|["todos"], assuntos:[...]}].
     */
    function edital_ia_ler(string $texto, string $pdfBase64, array $cargos): array
    {
        $prompt = "Você organiza editais de concursos policiais e militares da Bahia para o Projeto Gabaritando.\n"
            . "Leia o CONTEÚDO PROGRAMÁTICO e liste as matérias e, dentro de cada uma, os assuntos, na ordem do edital.\n"
            . "Cargos deste concurso: " . json_encode($cargos, JSON_UNESCAPED_UNICODE) . ".\n"
            . "Quando o edital separar conteúdo por cargo, informe em \"cargos\" os nomes exatos dos cargos daquela matéria; se valer para todos, use [\"todos\"].\n"
            . "LISTE TODOS os itens e subitens do conteúdo de cada matéria, um a um, na ordem: NÃO resuma, NÃO junte itens, NÃO pule nenhum (se o edital tem 25 itens numa matéria, devolva 25). "
            . "Use o texto do item do edital (pode encurtar só palavras repetidas), sem a numeração. Não invente nada que não esteja no texto. Leia o edital INTEIRO, inclusive anexos.\n"
            . "Responda SOMENTE em JSON: {\"materias\":[{\"materia\":\"...\",\"cargos\":[\"todos\"],\"assuntos\":[\"...\"]}]}\n\n"
            . "CONTEÚDO PROGRAMÁTICO:\n" . mb_substr($texto, 0, 120000);
        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1, 'maxOutputTokens' => 32000]], 170);
        $j = json_decode((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
        $lista = [];
        foreach ((array)($j['materias'] ?? []) as $m) {
            $nome = trim((string)($m['materia'] ?? ''));
            $assuntos = array_values(array_filter(array_map(static fn($a): string => trim((string)$a), (array)($m['assuntos'] ?? []))));
            if ($nome !== '' && $assuntos) {
                $lista[] = ['materia' => $nome, 'cargos' => array_values(array_map('strval', (array)($m['cargos'] ?? ['todos']))), 'assuntos' => $assuntos];
            }
        }
        if (!$lista) {
            throw new RuntimeException('A IA não conseguiu ler matérias e assuntos desse texto. Confira se colou o conteúdo programático.');
        }
        return $lista;
    }
}

if (!function_exists('edital_normalizar')) {
    function edital_normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t), 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return (string)preg_replace('/[^a-z0-9]+/', ' ', $t);
    }
}

if (!function_exists('edital_salvar')) {
    /**
     * Grava o edital confirmado. Reaproveita matérias e assuntos que já existem
     * com o mesmo nome (sem acento/maiúscula), cria os que faltam.
     */
    /** Acrescenta assuntos ao conteúdo já cadastrado (depois dos atuais, sem mexer na ordem deles). */
    function edital_acrescentar(PDO $pdo, int $concursoId, array $materias): int
    {
        $st = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM edital_itens WHERE concurso_id = :c');
        $st->execute([':c' => $concursoId]);
        return edital_salvar($pdo, $concursoId, $materias, (int)$st->fetchColumn(), true);
    }

    function edital_salvar(PDO $pdo, int $concursoId, array $materias, int $ordemBase = 0, bool $soNovos = false): int
    {
        $cargosDb = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c');
        $cargosDb->execute([':c' => $concursoId]);
        $mapaCargos = [];
        foreach ($cargosDb->fetchAll() as $c) {
            $mapaCargos[edital_normalizar((string)$c['nome'])] = (int)$c['id'];
        }
        $matExist = [];
        foreach ($pdo->query('SELECT id, nome FROM admin_materias') as $m) {
            $matExist[edital_normalizar((string)$m['nome'])] = (int)$m['id'];
        }
        $insMat = $pdo->prepare("INSERT INTO admin_materias (nome, status, ordem) VALUES (:n, 'ativo', 0)");
        $selTop = $pdo->prepare('SELECT id, titulo FROM admin_materia_topicos WHERE materia_id = :m');
        $insTop = $pdo->prepare("INSERT INTO admin_materia_topicos (materia_id, titulo, status, ordem) VALUES (:m, :t, 'ativo', :o)");
        $insItem = $pdo->prepare($soNovos ? 'INSERT IGNORE INTO edital_itens (concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:c, :m, :t, :g, :o)'
            : 'INSERT INTO edital_itens (concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:c, :m, :t, :g, :o)
                                  ON DUPLICATE KEY UPDATE cargos = VALUES(cargos), ordem = VALUES(ordem)');
        $ordem = $ordemBase;
        $n = 0;
        foreach ($materias as $m) {
            $chave = edital_normalizar((string)$m['materia']);
            if (!isset($matExist[$chave])) {
                $insMat->execute([':n' => mb_substr((string)$m['materia'], 0, 180)]);
                $matExist[$chave] = (int)$pdo->lastInsertId();
            }
            $mid = $matExist[$chave];
            $selTop->execute([':m' => $mid]);
            $topExist = [];
            foreach ($selTop->fetchAll() as $t) {
                $topExist[edital_normalizar((string)$t['titulo'])] = (int)$t['id'];
            }
            $cargos = [];
            foreach ((array)$m['cargos'] as $cn) {
                if (edital_normalizar((string)$cn) !== 'todos' && isset($mapaCargos[edital_normalizar((string)$cn)])) {
                    $cargos[] = $mapaCargos[edital_normalizar((string)$cn)];
                }
            }
            foreach ((array)$m['assuntos'] as $i => $assunto) {
                $k = edital_normalizar((string)$assunto);
                if ($k === '') {
                    continue;
                }
                if (!isset($topExist[$k])) {
                    $insTop->execute([':m' => $mid, ':t' => mb_substr((string)$assunto, 0, 180), ':o' => $i + 1]);
                    $topExist[$k] = (int)$pdo->lastInsertId();
                }
                $insItem->execute([':c' => $concursoId, ':m' => $mid, ':t' => $topExist[$k], ':g' => implode(',', $cargos), ':o' => ++$ordem]);
                $n++;
            }
        }
        return $n;
    }
}

if (!function_exists('edital_itens')) {
    /** Itens do edital com matéria, assunto e cargos. */
    function edital_itens(PDO $pdo, int $concursoId): array
    {
        $st = $pdo->prepare('SELECT e.*, m.nome AS materia, t.titulo AS assunto FROM edital_itens e
                               JOIN admin_materias m ON m.id = e.materia_id JOIN admin_materia_topicos t ON t.id = e.topico_id
                              WHERE e.concurso_id = :c ORDER BY e.ordem, e.id');
        $st->execute([':c' => $concursoId]);
        return $st->fetchAll();
    }
}

if (!function_exists('edital_andamento')) {
    /**
     * Por cargo: cada assunto do edital e as aulas que já existem no curso do cargo.
     * @return array<int,array{cargo:array,itens:array,cobertos:int,total:int}>
     */
    function edital_andamento(PDO $pdo, int $concursoId): array
    {
        $cargos = $pdo->prepare('SELECT * FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
        $cargos->execute([':c' => $concursoId]);
        $itens = edital_itens($pdo, $concursoId);
        $res = [];
        foreach ($cargos->fetchAll() as $cg) {
            // Todas as aulas do curso do cargo de uma vez (antes: uma consulta por assunto).
            $porTopico = [];
            if (!empty($cg['curso_id'])) {
                $aulas = $pdo->prepare('SELECT id, curso_id, modulo_id, topico_id, titulo, video_url, pdf_arquivo FROM admin_curso_aulas WHERE curso_id = :curso ORDER BY ordem, id');
                $aulas->execute([':curso' => (int)$cg['curso_id']]);
                foreach ($aulas->fetchAll() as $a) {
                    $porTopico[(int)$a['topico_id']][] = $a;
                }
            }
            $lista = [];
            $cobertos = 0;
            foreach ($itens as $it) {
                $ids = array_filter(array_map('intval', explode(',', (string)$it['cargos'])));
                if ($ids && !in_array((int)$cg['id'], $ids, true)) {
                    continue; // assunto de outro cargo
                }
                $doItem = $porTopico[(int)$it['topico_id']] ?? [];
                $cobertos += $doItem ? 1 : 0;
                $lista[] = $it + ['aulas' => $doItem];
            }
            $res[] = ['cargo' => $cg, 'itens' => $lista, 'cobertos' => $cobertos, 'total' => count($lista)];
        }
        return $res;
    }
}

if (!function_exists('edital_youtube_titulo')) {
    /** Título público do vídeo (oEmbed, sem chave). Funciona também com vídeo não listado. */
    function edital_youtube_titulo(string $url): string
    {
        if (!preg_match('~(youtube\.com|youtu\.be)~i', $url)) {
            return '';
        }
        $ch = curl_init('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($url));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12]);
        $r = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        return trim((string)($r['title'] ?? ''));
    }
}

if (!function_exists('edital_ia_sugerir_aula')) {
    /**
     * Para um link de aula: a IA sugere o assunto do edital e os cargos.
     * Retorna {edital_item_id, cargos:[ids], titulo, confianca, motivo}.
     */
    function edital_ia_sugerir_aula(PDO $pdo, int $concursoId, string $url, string $tituloInformado = ''): array
    {
        $itens = edital_itens($pdo, $concursoId);
        if (!$itens) {
            throw new RuntimeException('Cadastre o edital deste concurso antes das aulas.');
        }
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll();
        $titulo = $tituloInformado !== '' ? $tituloInformado : edital_youtube_titulo($url);
        $lista = array_map(static fn(array $i): array => ['id' => (int)$i['id'], 'assunto' => $i['materia'] . ' › ' . $i['assunto'], 'cargos' => (string)$i['cargos']], $itens);
        $prompt = "Você cadastra aulas do Projeto Gabaritando. Identifique a qual ASSUNTO DO EDITAL esta aula pertence e para quais CARGOS ela serve.\n"
            . "Use SOMENTE um id da lista de assuntos. Em cargos, use ids da lista de cargos (os cargos do item do edital, se ele tiver).\n"
            . "Responda SOMENTE em JSON: {\"edital_item_id\":n,\"cargos\":[ids],\"titulo\":\"título curto e claro da aula\",\"confianca\":0-100,\"motivo\":\"1 frase\"}\n\n"
            . "CARGOS: " . json_encode($cargos, JSON_UNESCAPED_UNICODE) . "\n"
            . "ASSUNTOS DO EDITAL (cargos vazios = todos): " . json_encode($lista, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "AULA: título \"" . $titulo . "\", link " . $url;
        $partes = [['text' => $prompt]];
        $comVideo = $titulo === '' && preg_match('~(youtube\.com|youtu\.be)~i', $url);
        if ($comVideo) {
            $partes[] = ['fileData' => ['fileUri' => $url]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 120);
        $j = json_decode((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''), true) ?: [];
        $idsItens = array_column($lista, 'id');
        $item = (int)($j['edital_item_id'] ?? 0);
        $idsCargos = array_map('intval', array_column($cargos, 'id'));
        return [
            'edital_item_id' => in_array($item, $idsItens, true) ? $item : 0,
            'cargos' => array_values(array_intersect(array_map('intval', (array)($j['cargos'] ?? [])), $idsCargos)) ?: $idsCargos,
            'titulo' => trim((string)($j['titulo'] ?? '')) ?: $titulo,
            'confianca' => max(0, min(100, (int)($j['confianca'] ?? 0))),
            'motivo' => mb_substr((string)($j['motivo'] ?? ''), 0, 300),
            'titulo_youtube' => $titulo,
        ];
    }
}

if (!function_exists('edital_criar_aula')) {
    /**
     * Cria a aula no curso de cada cargo, no módulo da matéria (cria o módulo se
     * não existir) e põe na fila da IA (apostila + podcast). Retorna os IDs.
     */
    function edital_criar_aula(PDO $pdo, int $editalItemId, array $cargoIds, string $titulo, string $url): array
    {
        $st = $pdo->prepare('SELECT e.*, m.nome AS materia FROM edital_itens e JOIN admin_materias m ON m.id = e.materia_id WHERE e.id = :id');
        $st->execute([':id' => $editalItemId]);
        $item = $st->fetch();
        if (!$item) {
            throw new RuntimeException('Assunto do edital não encontrado.');
        }
        $ids = [];
        foreach ($cargoIds as $cid) {
            $c = $pdo->prepare('SELECT curso_id, nome FROM concurso_cargos WHERE id = :id');
            $c->execute([':id' => (int)$cid]);
            $cargo = $c->fetch();
            if (!$cargo || empty($cargo['curso_id'])) {
                throw new RuntimeException('O cargo "' . ($cargo['nome'] ?? $cid) . '" não está ligado a um curso. Ligue o cargo a um curso no passo 1.');
            }
            $cursoId = (int)$cargo['curso_id'];
            $mod = $pdo->prepare('SELECT id FROM admin_curso_modulos WHERE curso_id = :c AND materia_id = :m ORDER BY id LIMIT 1');
            $mod->execute([':c' => $cursoId, ':m' => (int)$item['materia_id']]);
            $moduloId = (int)($mod->fetchColumn() ?: 0);
            if (!$moduloId) {
                $pdo->prepare('INSERT INTO admin_curso_modulos (curso_id, titulo, materia_id, ordem) VALUES (:c, :t, :m, 999)')
                    ->execute([':c' => $cursoId, ':t' => $item['materia'], ':m' => (int)$item['materia_id']]);
                $moduloId = (int)$pdo->lastInsertId();
            }
            $dup = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE curso_id = :c AND video_url = :v AND topico_id = :t LIMIT 1');
            $dup->execute([':c' => $cursoId, ':v' => $url, ':t' => (int)$item['topico_id']]);
            if ($url !== '' && ($ja = $dup->fetchColumn())) {
                $ids[] = (int)$ja;
                continue; // a mesma aula já está neste curso
            }
            $ordem = $pdo->prepare('SELECT COALESCE(MAX(ordem),0)+1 FROM admin_curso_aulas WHERE modulo_id = :m');
            $ordem->execute([':m' => $moduloId]);
            $pdo->prepare('INSERT INTO admin_curso_aulas (curso_id, modulo_id, materia_id, topico_id, titulo, video_url, ordem) VALUES (:c, :mo, :ma, :t, :ti, :v, :o)')
                ->execute([':c' => $cursoId, ':mo' => $moduloId, ':ma' => (int)$item['materia_id'], ':t' => (int)$item['topico_id'], ':ti' => mb_substr($titulo, 0, 180), ':v' => $url, ':o' => (int)$ordem->fetchColumn()]);
            $ids[] = (int)$pdo->lastInsertId();
        }
        if (function_exists('ia_fila_adicionar')) {
            foreach ($ids as $aid) {
                if ($url !== '') {
                    ia_fila_adicionar($pdo, 'apostila', $aid);
                }
            }
        }
        return $ids;
    }
}

// ---------------------------------------------------------------------------
// Plano-Mestre automático a partir do edital do cargo.
// ---------------------------------------------------------------------------

if (!function_exists('edital_plano_bootstrap')) {
    function edital_plano_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        foreach (['justificativa' => 'TEXT NULL', 'recorrencia' => 'DECIMAL(5,2) NULL'] as $col => $def) {
            if (!$pdo->query('SHOW COLUMNS FROM meta_plano_itens LIKE ' . $pdo->quote($col))->fetch()) {
                $pdo->exec("ALTER TABLE meta_plano_itens ADD COLUMN `$col` $def");
            }
        }
        foreach (['ultima_prova' => 'MEDIUMTEXT NULL'] as $col => $def) {
            if (!$pdo->query('SHOW COLUMNS FROM concursos LIKE ' . $pdo->quote($col))->fetch()) {
                $pdo->exec("ALTER TABLE concursos ADD COLUMN `$col` $def");
            }
        }
    }
}

if (!function_exists('edital_json_do_texto')) {
    /** Extrai o primeiro objeto JSON de um texto (respostas com busca não vêm em modo JSON). */
    function edital_json_do_texto(string $t): ?array
    {
        $t = (string)preg_replace('~^```(?:json)?|```$~m', '', $t);
        $ini = strpos($t, '{');
        $fim = strrpos($t, '}');
        if ($ini === false || $fim === false) {
            return null;
        }
        $j = json_decode(substr($t, $ini, $fim - $ini + 1), true);
        return is_array($j) ? $j : null;
    }
}

if (!function_exists('edital_ultima_prova')) {
    /**
     * Distribuição de questões da última prova por assunto do edital.
     * Fonte, em ordem: PDF da prova enviado pelo mentor > busca na internet (Gemini + Google).
     * Retorna {encontrada, fonte, ano, banca, itens:{edital_item_id: questoes}, observacao}.
     */
    function edital_ultima_prova(PDO $pdo, int $concursoId, string $pdfBase64 = ''): array
    {
        $st = $pdo->prepare('SELECT c.*, b.nome AS banca FROM concursos c LEFT JOIN admin_bancas b ON b.id = c.banca_id WHERE c.id = :id');
        $st->execute([':id' => $concursoId]);
        $c = $st->fetch();
        $cg = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE concurso_id = :c');
        $cg->execute([':c' => $concursoId]);
        $cargos = implode(', ', $cg->fetchAll(PDO::FETCH_COLUMN));
        $itens = array_map(static fn(array $i): array => ['id' => (int)$i['id'], 'assunto' => $i['materia'] . ' › ' . $i['assunto']], edital_itens($pdo, $concursoId));

        $pedido = "Concurso: {$c['nome']} (cargos: $cargos; banca: " . ($c['banca'] ?: 'não informada') . ")"
            . ($c['base_edital'] ? "; edital de referência: {$c['base_edital']}" : '') . ".\n"
            . "Quero a DISTRIBUIÇÃO DE QUESTÕES DA ÚLTIMA PROVA OBJETIVA aplicada para este concurso, por assunto do edital abaixo.\n"
            . "Para cada assunto, quantas questões caíram (0 se nenhuma). Não invente: se não tiver a prova, diga encontrada=false.\n"
            . "Responda SOMENTE em JSON: {\"encontrada\":true|false,\"ano\":2022,\"banca\":\"...\",\"fonte\":\"link ou nome do documento\",\"itens\":[{\"id\":n,\"questoes\":n}],\"observacao\":\"1 frase\"}\n\n"
            . "ASSUNTOS DO EDITAL: " . json_encode($itens, JSON_UNESCAPED_UNICODE);
        if ($pdfBase64 !== '') {
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => "A prova está no PDF anexo.\n" . $pedido], ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]]]]]], 150);
        } else {
            // Busca na internet (Grounding com Google Search).
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => "Pesquise na internet a prova mais recente deste concurso (site da banca, Diário Oficial ou PCI Concursos).\n" . $pedido]]]], 'tools' => [['google_search' => new stdClass()]]], 150);
        }
        $texto = '';
        foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $p) {
            $texto .= (string)($p['text'] ?? '');
        }
        $j = edital_json_do_texto($texto) ?? ['encontrada' => false];
        $ids = array_column($itens, 'id');
        $dist = [];
        foreach ((array)($j['itens'] ?? []) as $i) {
            if (in_array((int)($i['id'] ?? 0), $ids, true)) {
                $dist[(int)$i['id']] = max(0, (int)($i['questoes'] ?? 0));
            }
        }
        $res = [
            'encontrada' => !empty($j['encontrada']) && array_sum($dist) > 0,
            'fonte' => $pdfBase64 !== '' ? 'PDF da prova enviado pelo mentor' : mb_substr((string)($j['fonte'] ?? ''), 0, 300),
            'ano' => (int)($j['ano'] ?? 0) ?: null,
            'banca' => mb_substr((string)($j['banca'] ?? ''), 0, 120),
            'itens' => $dist,
            'observacao' => mb_substr((string)($j['observacao'] ?? ''), 0, 300),
        ];
        $pdo = podcast_reconectar($pdo);
        $pdo->prepare('UPDATE concursos SET ultima_prova = :u WHERE id = :id')->execute([':u' => json_encode($res, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
        return $res;
    }
}

if (!function_exists('edital_banco_por_assunto')) {
    /** No banco de questões da plataforma: quantidade e % de questões difíceis por assunto. */
    function edital_banco_por_assunto(PDO $pdo, array $topicoIds): array
    {
        if (!$topicoIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $topicoIds));
        $res = [];
        foreach ($pdo->query("SELECT materia_topico_id t, COUNT(*) n, SUM(dificuldade = 'dificil') d, SUM(concurso_passado_id IS NOT NULL) reais FROM admin_banco_questoes WHERE materia_topico_id IN ($in) GROUP BY materia_topico_id") as $r) {
            $res[(int)$r['t']] = ['total' => (int)$r['n'], 'dificeis_pct' => $r['n'] ? (int)round($r['d'] * 100 / $r['n']) : 0, 'reais' => (int)$r['reais']];
        }
        return $res;
    }
}

if (!function_exists('edital_sugerir_plano')) {
    /**
     * IA monta o Plano-Mestre do cargo (rascunho) com ordem didática, peso,
     * complexidade e justificativa de cada assunto. Retorna o id do plano.
     */
    function edital_sugerir_plano(PDO $pdo, int $concursoId, int $cargoId, array $prova): int
    {
        edital_plano_bootstrap($pdo);
        $cg = $pdo->prepare('SELECT * FROM concurso_cargos WHERE id = :id AND concurso_id = :c');
        $cg->execute([':id' => $cargoId, ':c' => $concursoId]);
        $cargo = $cg->fetch();
        if (!$cargo || empty($cargo['curso_id'])) {
            throw new RuntimeException('Ligue o cargo a um curso antes de gerar o Plano-Mestre.');
        }
        $itens = array_values(array_filter(edital_itens($pdo, $concursoId), static function (array $i) use ($cargoId): bool {
            $ids = array_filter(array_map('intval', explode(',', (string)$i['cargos'])));
            return !$ids || in_array($cargoId, $ids, true);
        }));
        if (!$itens) {
            throw new RuntimeException('Este cargo não tem assuntos no edital.');
        }
        $banco = edital_banco_por_assunto($pdo, array_column($itens, 'topico_id'));
        $totalProva = array_sum($prova['itens'] ?? []);
        $dados = [];
        foreach ($itens as $i) {
            $q = (int)(($prova['itens'] ?? [])[(int)$i['id']] ?? 0);
            $b = $banco[(int)$i['topico_id']] ?? ['total' => 0, 'dificeis_pct' => 0, 'reais' => 0];
            $dados[] = ['id' => (int)$i['id'], 'materia' => $i['materia'], 'assunto' => $i['assunto'],
                'questoes_ultima_prova' => $q, 'recorrencia_pct' => $totalProva ? round($q * 100 / $totalProva, 1) : null,
                'banco_questoes' => $b['total'], 'banco_dificeis_pct' => $b['dificeis_pct']];
        }
        $prompt = "Você é o coordenador pedagógico do Projeto Gabaritando (Método META: teoria mínima, questões comentadas, simulados e redação, com acompanhamento).\n"
            . "Monte o PLANO-MESTRE do cargo \"{$cargo['nome']}\" com TODOS os assuntos abaixo.\n"
            . "Para cada assunto defina:\n"
            . "- ordem: sequência didática DENTRO da matéria (pré-requisitos antes: ex. classes de palavras antes de concordância; princípios antes de crimes em espécie). As matérias andam em paralelo no cronograma, então só a ordem dentro de cada matéria importa.\n"
            . "- peso: 1 (pouco cobrado), 2 (médio) ou 3 (muito cobrado), pela recorrência na última prova; sem dado de prova, use a importância típica no concurso.\n"
            . "- complexidade: facil, media, dificil ou muito_dificil, pelo conteúdo e pela dificuldade das questões.\n"
            . "- justificativa: 1 a 2 frases DIDÁTICAS para o mentor: por que nesta posição, por que este peso e esta complexidade, citando os números quando houver.\n"
            . ($prova['encontrada'] ?? false ? "Última prova: " . ($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '') . " (fonte: " . ($prova['fonte'] ?? '') . ").\n" : "Não há distribuição da última prova: diga isso na justificativa quando o peso for estimado.\n")
            . "Responda SOMENTE em JSON: {\"itens\":[{\"id\":n,\"ordem\":n,\"peso\":1|2|3,\"complexidade\":\"...\",\"justificativa\":\"...\"}]}\n\n"
            . "ASSUNTOS (com dados): " . json_encode($dados, JSON_UNESCAPED_UNICODE);
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2]], 150);
        $pdo = podcast_reconectar($pdo);
        $j = json_decode((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
        $porId = [];
        foreach ((array)($j['itens'] ?? []) as $r) {
            $porId[(int)($r['id'] ?? 0)] = $r;
        }
        if (!$porId) {
            throw new RuntimeException('A IA não devolveu o plano. Tente de novo.');
        }

        // Novo rascunho do Plano-Mestre do curso do cargo.
        $cursoId = (int)$cargo['curso_id'];
        $pdo->prepare("INSERT INTO meta_planos (curso_id, titulo, status) VALUES (:c, :t, 'rascunho')")
            ->execute([':c' => $cursoId, ':t' => 'Plano-Mestre ' . $cargo['nome'] . ' (sugerido pela IA em ' . date('d/m/Y') . ')']);
        $planoId = (int)$pdo->lastInsertId();
        $aula = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE curso_id = :c AND topico_id = :t ORDER BY ordem, id LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO meta_plano_itens (plano_id, ordem, materia_id, topico_id, titulo, aula_id, peso, complexidade, justificativa, recorrencia)
                              VALUES (:p, :o, :m, :t, :ti, :a, :pe, :cx, :j, :r)');
        $materiaOrdem = [];
        foreach ($itens as $k => $i) {
            $r = $porId[(int)$i['id']] ?? [];
            $materiaOrdem[$i['materia']] = $materiaOrdem[$i['materia']] ?? count($materiaOrdem);
            $aula->execute([':c' => $cursoId, ':t' => (int)$i['topico_id']]);
            $cx = in_array($r['complexidade'] ?? '', ['facil', 'media', 'dificil', 'muito_dificil'], true) ? $r['complexidade'] : 'media';
            $rec = $dados[$k]['recorrencia_pct'];
            $ins->execute([
                ':p' => $planoId,
                ':o' => $materiaOrdem[$i['materia']] * 1000 + (int)($r['ordem'] ?? ($k + 1)),
                ':m' => (int)$i['materia_id'], ':t' => (int)$i['topico_id'], ':ti' => $i['assunto'],
                ':a' => ((int)$aula->fetchColumn()) ?: null,
                ':pe' => max(1, min(3, (int)($r['peso'] ?? 1))), ':cx' => $cx,
                ':j' => mb_substr((string)($r['justificativa'] ?? ''), 0, 1000),
                ':r' => $rec,
            ]);
        }
        return $planoId;
    }
}

if (!function_exists('edital_parse_texto')) {
    /**
     * Lê o conteúdo programático colado do edital, sem IA: "MATÉRIA:" em maiúsculas abre a matéria;
     * "1. … 2. …" separa os assuntos (os subitens "1.1." ficam dentro do assunto). Retorna [['materia','assuntos'=>[]]].
     */
    function edital_parse_texto(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $up = 'A-ZÁÀÂÃÉÊÍÓÔÕÚÇ';
        preg_match_all('~(?<![\p{L}])([' . $up . '][' . $up . ' ,\-–]{3,}):~u', $texto, $m, PREG_OFFSET_CAPTURE);
        $saida = [];
        $n = count($m[1]);
        for ($i = 0; $i < $n; $i++) {
            $nome = trim(preg_replace('~^.*\b(CONHECIMENTOS (GERAIS|ESPEC[IÍ]FICOS)|ANEXO [IVX]+)\b~u', '', $m[1][$i][0]), " -–,");
            if (mb_strlen($nome) < 4) {
                continue;
            }
            $ini = $m[0][$i][1] + strlen($m[0][$i][0]);
            $fim = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($texto);
            $corpo = trim(preg_replace('~\s+~u', ' ', substr($texto, $ini, $fim - $ini)));
            $corpo = preg_replace('~\s*(CONHECIMENTOS (GERAIS|ESPEC[IÍ]FICOS))\s*$~u', '', $corpo);
            // Numeração: "3." = assunto; "1.1." = subtópico do assunto de cima. Não confunde "9.455" (lei) nem "5.0" (versão).
            preg_match_all('~(?<![\d\p{L}])(?<!\d\.)(\d{1,2})(?:\.(\d{1,2}))?\s*\.(?!\d)\s*~u', $corpo, $nm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            $itens = [];
            foreach ($nm as $k => $tok) {
                $ini2 = $tok[0][1] + strlen($tok[0][0]);
                $fim2 = isset($nm[$k + 1]) ? $nm[$k + 1][0][1] : strlen($corpo);
                $txt = trim(substr($corpo, $ini2, $fim2 - $ini2), " .;");
                if ($txt === '') {
                    continue;
                }
                if (isset($tok[2]) && $tok[2][0] !== '' && $itens) {
                    $itens[count($itens) - 1]['subs'][] = $txt; // subtópico do assunto de cima
                } else {
                    $itens[] = ['txt' => $txt, 'subs' => []];
                }
            }
            if (!$itens && $corpo !== '') {
                $itens[] = ['txt' => trim($corpo, ' .;'), 'subs' => []];
            }
            $assuntos = [];
            foreach ($itens as $it) {
                if (!$it['subs']) {
                    $assuntos[] = mb_substr($it['txt'], 0, 180);
                    continue;
                }
                $pai = trim(preg_replace('~[:.]\s*$~u', '', $it['txt']));
                $pai = preg_replace('~^Constitui[çc][ãa]o da Rep[úu]blica Federativa do Brasil$~iu', 'Constituição Federal', $pai);
                foreach ($it['subs'] as $sub) {
                    $assuntos[] = mb_substr($pai . ' › ' . $sub, 0, 180);
                }
            }
            if ($assuntos) {
                $nomeBonito = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE);
                $nomeBonito = preg_replace_callback('~\b(De|Da|Do|Das|Dos|E)\b~u', static fn($x) => mb_strtolower($x[1]), $nomeBonito);
                $saida[] = ['materia' => $nomeBonito, 'assuntos' => $assuntos];
            }
        }
        return $saida;
    }
}

if (!function_exists('edital_ordenar_aulas')) {
    /**
     * As aulas de cada módulo dos cursos do concurso seguem a ORDEM DO EDITAL (edital_itens.ordem do assunto).
     * Aulas sem assunto do edital ficam depois, na ordem em que estavam. Partes do mesmo assunto mantêm a ordem entre si.
     */
    function edital_ordenar_aulas(PDO $pdo, int $concursoId): int
    {
        $pos = [];
        $st = $pdo->prepare('SELECT topico_id, ordem FROM edital_itens WHERE concurso_id = :c');
        $st->execute([':c' => $concursoId]);
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $t => $o) {
            $pos[(int)$t] = (int)$o;
        }
        $cg = $pdo->prepare('SELECT DISTINCT curso_id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
        $cg->execute([':c' => $concursoId]);
        $up = $pdo->prepare('UPDATE admin_curso_aulas SET ordem = :o WHERE id = :id');
        $n = 0;
        foreach ($cg->fetchAll(PDO::FETCH_COLUMN) as $curso) {
            $au = $pdo->prepare('SELECT id, modulo_id, topico_id, ordem FROM admin_curso_aulas WHERE curso_id = :c ORDER BY modulo_id, ordem, id');
            $au->execute([':c' => (int)$curso]);
            $porMod = [];
            foreach ($au->fetchAll(PDO::FETCH_ASSOC) as $k => $a) {
                $a['_k'] = $k;
                $porMod[(int)$a['modulo_id']][] = $a;
            }
            foreach ($porMod as $lista) {
                usort($lista, static function ($x, $y) use ($pos) {
                    $px = $pos[(int)$x['topico_id']] ?? PHP_INT_MAX;
                    $py = $pos[(int)$y['topico_id']] ?? PHP_INT_MAX;
                    return $px <=> $py ?: $x['_k'] <=> $y['_k'];
                });
                foreach ($lista as $i => $a) {
                    if ((int)$a['ordem'] !== $i + 1) {
                        $up->execute([':o' => $i + 1, ':id' => (int)$a['id']]);
                        $n++;
                    }
                }
            }
        }
        return $n;
    }

    /** Reordena os assuntos de cada matéria pela ordem em que aparecem no edital (lista de ids na ordem). */
    function edital_ordenar_itens(PDO $pdo, int $concursoId, array $idsNaOrdem): void
    {
        $idsNaOrdem = array_values(array_unique(array_map('intval', $idsNaOrdem)));
        if (!$idsNaOrdem) {
            return;
        }
        $st = $pdo->prepare('SELECT id, materia_id, ordem FROM edital_itens WHERE concurso_id = :c ORDER BY ordem, id');
        $st->execute([':c' => $concursoId]);
        $porMat = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $it) {
            $porMat[(int)$it['materia_id']][] = $it;
        }
        $rank = array_flip($idsNaOrdem);
        $up = $pdo->prepare('UPDATE edital_itens SET ordem = :o WHERE id = :id');
        // matérias ficam onde estavam (pela primeira posição); dentro de cada uma, a ordem do edital; numeração 1..N sem repetir
        // matéria que está no texto colado segue a posição do edital; as outras ficam depois, na ordem que tinham
        $chaveMat = static function (array $l) use ($rank): array {
            $r = array_filter(array_map(static fn($x) => $rank[(int)$x['id']] ?? null, $l), static fn($v) => $v !== null);
            return $r ? [0, min($r)] : [1, min(array_column($l, 'ordem'))];
        };
        $matsNoTexto = count(array_filter($porMat, static fn($l) => $chaveMat($l)[0] === 0));
        if ($matsNoTexto >= 3) { // anexo inteiro colado: as matérias também seguem o edital
            uasort($porMat, static fn($a, $b) => $chaveMat($a) <=> $chaveMat($b));
        } else { // uma ou duas matérias: só reordena dentro delas, as matérias ficam onde estavam
            uasort($porMat, static fn($a, $b) => min(array_column($a, 'ordem')) <=> min(array_column($b, 'ordem')));
        }
        $n = 0;
        foreach ($porMat as $lista) {
            usort($lista, static fn($a, $b) => ($rank[(int)$a['id']] ?? PHP_INT_MAX) <=> ($rank[(int)$b['id']] ?? PHP_INT_MAX) ?: (int)$a['ordem'] <=> (int)$b['ordem']);
            foreach ($lista as $it) {
                $up->execute([':o' => ++$n, ':id' => (int)$it['id']]);
            }
        }
    }
}

if (!function_exists('edital_mesmo_assunto')) {
    /** Mesmo assunto? Rigoroso: "Pai › filho" só bate com o mesmo filho (e pai parecido); uma palavra em comum não basta. */
    function edital_mesmo_assunto(string $a, string $b): bool
    {
        $chave = static fn(string $t): string => trim(edital_normalizar(preg_replace('~\(.*?\)~u', '', $t)));
        $palavras = static function (string $t) use ($chave): array {
            $t = preg_replace('~\b(dos|das|do|da|de|e|a|o|os|as|em|no|na|para|com|sobre)\b~', ' ', $chave($t));
            return array_values(array_unique(array_filter(explode(' ', $t), static fn($p) => strlen($p) >= 4)));
        };
        $parecido = static function (string $x0, string $y0) use ($chave, $palavras): bool {
            if ($chave($x0) === '' || $chave($y0) === '') {
                return false;
            }
            if ($chave($x0) === $chave($y0)) {
                return true;
            }
            $x = $palavras($x0);
            $y = $palavras($y0);
            $c = count(array_intersect($x, $y));
            return $x && $y && $c >= 2 && $c / max(count($x), count($y)) >= 0.75;
        };
        $partes = static function (string $t): array {
            $p = array_map('trim', explode('›', $t));
            return count($p) > 1 ? [implode(' › ', array_slice($p, 0, -1)), end($p)] : ['', $t];
        };
        [$pa, $fa] = $partes($a);
        [$pb, $fb] = $partes($b);
        if ($pa !== '' || $pb !== '') {
            return $parecido($fa, $fb) && ($pa === '' || $pb === '' || $parecido($pa, $pb));
        }
        return $parecido($a, $b);
    }

    /** O pai de "Pai › filho" ('' se não tiver). */
    function edital_pai(string $t): string
    {
        $p = array_map('trim', explode('›', $t));
        return count($p) > 1 ? implode(' › ', array_slice($p, 0, -1)) : '';
    }
}
