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
            . "Assuntos curtos e claros (ex.: \"Crase\", \"Concordância verbal\"), sem numeração. Não invente nada que não esteja no texto.\n"
            . "Responda SOMENTE em JSON: {\"materias\":[{\"materia\":\"...\",\"cargos\":[\"todos\"],\"assuntos\":[\"...\"]}]}\n\n"
            . "CONTEÚDO PROGRAMÁTICO:\n" . mb_substr($texto, 0, 60000);
        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 140);
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
    function edital_salvar(PDO $pdo, int $concursoId, array $materias): int
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
        $insItem = $pdo->prepare('INSERT INTO edital_itens (concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:c, :m, :t, :g, :o)
                                  ON DUPLICATE KEY UPDATE cargos = VALUES(cargos), ordem = VALUES(ordem)');
        $ordem = 0;
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
        $aulas = $pdo->prepare('SELECT id, titulo, video_url, pdf_arquivo FROM admin_curso_aulas WHERE curso_id = :curso AND topico_id = :t ORDER BY id');
        $res = [];
        foreach ($cargos->fetchAll() as $cg) {
            $lista = [];
            $cobertos = 0;
            foreach ($itens as $it) {
                $ids = array_filter(array_map('intval', explode(',', (string)$it['cargos'])));
                if ($ids && !in_array((int)$cg['id'], $ids, true)) {
                    continue; // assunto de outro cargo
                }
                $doItem = [];
                if (!empty($cg['curso_id'])) {
                    $aulas->execute([':curso' => (int)$cg['curso_id'], ':t' => (int)$it['topico_id']]);
                    $doItem = $aulas->fetchAll();
                }
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
            $dup = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE curso_id = :c AND video_url = :v LIMIT 1');
            $dup->execute([':c' => $cursoId, ':v' => $url]);
            if ($ja = $dup->fetchColumn()) {
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
                ia_fila_adicionar($pdo, 'apostila', $aid);
            }
        }
        return $ids;
    }
}
