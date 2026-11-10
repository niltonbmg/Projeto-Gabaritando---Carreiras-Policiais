<?php
// includes/edital-hack-lib.php
// O edital é a base do curso.
//  - A IA extrai TODAS as informações do edital (vagas, salário, requisitos, etapas,
//    datas, TAF...) por cargo.
//  - A IA (Antigão) escreve "Hackeando o Edital" e "Hackeando a Prova" para cada cargo,
//    de forma estratégica, com base no edital, no conteúdo programático e na última prova.
//  - Rascunhos do cadastro ficam no banco: o mentor pausa e continua depois sem perder nada.
//  - Assunto do conteúdo programático sem aula: a IA gera a aula (apostila + videoaula do Antigão).
//  - Aluno escolhe concurso e cargo; "Hackeando o edital" entra no cronograma a cada 2 dias.
declare(strict_types=1);

require_once __DIR__ . '/edital-lib.php';
require_once __DIR__ . '/ia-fila-lib.php';

const HACK_FORMATO_Q = '{"questoes":[{"enunciado":"texto completo","alternativas":["A","B","C","D","E"],"correta":0,"comentario":"por que a certa é certa e as outras erradas, 2 a 4 frases","dificuldade":"facil|medio|dificil","ano":2022,"fonte":"banca, órgão e ano da prova"}]}';

if (!function_exists('hack_bootstrap')) {
    function hack_bootstrap(PDO $pdo): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        edital_bootstrap($pdo);
        edital_plano_bootstrap($pdo);
        foreach (['edital_info' => 'MEDIUMTEXT NULL', 'edital_pdf' => 'VARCHAR(255) NULL', 'hacks' => 'MEDIUMTEXT NULL', 'prova_status' => "VARCHAR(20) NULL"] as $col => $def) {
            if (!$pdo->query('SHOW COLUMNS FROM concursos LIKE ' . $pdo->quote($col))->fetch()) {
                $pdo->exec("ALTER TABLE concursos ADD COLUMN `$col` $def");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS edital_rascunhos (
            concurso_id INT UNSIGNED NOT NULL,
            chave VARCHAR(40) NOT NULL,
            dados MEDIUMTEXT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (concurso_id, chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aluno_concurso (
            usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            concurso_id INT UNSIGNED NOT NULL,
            cargo_id INT UNSIGNED NOT NULL,
            escolhido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aluno_edital_visitas (
            usuario_id BIGINT UNSIGNED NOT NULL,
            dia DATE NOT NULL,
            PRIMARY KEY (usuario_id, dia)
        ) ENGINE=InnoDB");
        // Correções de dados antigos: rodam UMA vez (marca em uploads), não a cada página.
        $marca = dirname(__DIR__) . '/uploads/.migracao-edital-v2';
        if (!is_file($marca)) {
            try {
                hack_corrigir_geradas($pdo);
                // Títulos limpos: tira o "· aula do Antigão" das aulas criadas antes (o selo aparece dentro da aula).
                $pdo->exec("UPDATE admin_curso_aulas SET titulo = TRIM(REPLACE(titulo, ' · aula do Antigão', '')) WHERE titulo LIKE '% · aula do Antigão%'");
                // Itens que deram erro porque a cota estava escondida atrás de outro erro: voltam para a fila.
                $pdo->exec("UPDATE ia_fila SET status = 'pendente', tentativas = 0, erro = NULL WHERE status = 'erro' AND tipo IN ('videoaula','podcast','questoes')");
                @file_put_contents($marca, date('c'));
            } catch (Throwable $e) {
            }
        }
        $feito = true;
    }

    // ---------------- Rascunhos (pausar e continuar) ----------------
    function hack_rascunho_get(PDO $pdo, int $concursoId, string $chave, $padrao = null)
    {
        $st = $pdo->prepare('SELECT dados FROM edital_rascunhos WHERE concurso_id = :c AND chave = :k');
        $st->execute([':c' => $concursoId, ':k' => $chave]);
        $v = $st->fetchColumn();
        return $v === false ? $padrao : json_decode((string)$v, true);
    }

    function hack_rascunho_set(PDO $pdo, int $concursoId, string $chave, $dados): void
    {
        if ($dados === null || $dados === '' || $dados === []) {
            $pdo->prepare('DELETE FROM edital_rascunhos WHERE concurso_id = :c AND chave = :k')->execute([':c' => $concursoId, ':k' => $chave]);
            return;
        }
        $pdo->prepare('INSERT INTO edital_rascunhos (concurso_id, chave, dados) VALUES (:c, :k, :d) ON DUPLICATE KEY UPDATE dados = VALUES(dados)')
            ->execute([':c' => $concursoId, ':k' => $chave, ':d' => json_encode($dados, JSON_UNESCAPED_UNICODE)]);
    }

    // ---------------- PDF do edital guardado ----------------
    function hack_salvar_pdf_edital(PDO $pdo, int $concursoId, string $tmp): string
    {
        $dir = dirname(__DIR__) . '/uploads/editais';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $nome = 'edital-' . $concursoId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.pdf';
        if (!@move_uploaded_file($tmp, $dir . '/' . $nome) && !@copy($tmp, $dir . '/' . $nome)) {
            throw new RuntimeException('Não consegui guardar o PDF do edital.');
        }
        $url = 'uploads/editais/' . $nome;
        $pdo->prepare('UPDATE concursos SET edital_pdf = :p WHERE id = :id')->execute([':p' => $url, ':id' => $concursoId]);
        return $url;
    }

    function hack_pdf_base64(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        $caminho = realpath(dirname(__DIR__) . '/' . $url);
        $raiz = realpath(dirname(__DIR__) . '/uploads');
        if ($caminho && $raiz && strpos($caminho, $raiz) === 0 && is_file($caminho) && filesize($caminho) <= 15 * 1024 * 1024) {
            return base64_encode((string)file_get_contents($caminho));
        }
        return '';
    }

    // ---------------- IA: informações do edital ----------------
    /** Lê TODAS as informações do edital (não só o conteúdo programático). */
    function hack_ia_info(string $texto, string $pdfBase64, array $cargos): array
    {
        $prompt = "Você é especialista em editais de concursos policiais e militares da Bahia e trabalha para o Projeto Gabaritando.\n"
            . "Leia o EDITAL e extraia TODAS as informações que um candidato precisa saber, por cargo quando for diferente.\n"
            . "Cargos: " . json_encode($cargos, JSON_UNESCAPED_UNICODE) . ".\n"
            . "REGRAS: copie números e datas exatamente como no edital. O que não estiver no edital fica null (nunca invente).\n"
            . "Responda SOMENTE em JSON neste formato:\n"
            . '{"orgao":"","banca":"","ano":null,"inscricoes":{"inicio":null,"fim":null,"taxa":null,"onde":null},'
            . '"prova_objetiva":{"data":null,"duracao":null,"questoes":null,"alternativas":null,"criterio_aprovacao":null,"penaliza_erro":null},'
            . '"redacao":{"tem":null,"criterio":"resumo do critério de correção","criterios":[{"nome":"critério de correção como está no edital","max":0,"descricao":"o que é avaliado"}]},"etapas":[{"nome":"","carater":"eliminatório/classificatório","resumo":""}],'
            . '"taf":[{"cargo":"","sexo":"","exercicio":"","minimo":""}],'
            . '"cargos":[{"nome":"","vagas":null,"vagas_detalhe":null,"salario":null,"escolaridade":null,"idade":null,"altura":null,"cnh":null,"outros_requisitos":[""]}],'
            . '"cotas":null,"validade":null,"lotacao":null,"datas_importantes":[{"evento":"","data":""}],"atencao":["pontos do edital que mais eliminam candidatos"]}' . "\n\n"
            . "EDITAL (texto):\n" . mb_substr($texto, 0, 80000);
        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 170);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if (!$j) {
            throw new RuntimeException('A IA não conseguiu ler as informações do edital. Tente de novo.');
        }
        return $j;
    }

    // ---------------- IA: Hackeando o Edital / a Prova ----------------
    function hack_ia_gerar(PDO $pdo, int $concursoId, int $cargoId): array
    {
        hack_bootstrap($pdo);
        $st = $pdo->prepare('SELECT c.*, b.nome AS banca_nome FROM concursos c LEFT JOIN admin_bancas b ON b.id = c.banca_id WHERE c.id = :id');
        $st->execute([':id' => $concursoId]);
        $c = $st->fetch();
        $cg = $pdo->prepare('SELECT * FROM concurso_cargos WHERE id = :id AND concurso_id = :c');
        $cg->execute([':id' => $cargoId, ':c' => $concursoId]);
        $cargo = $cg->fetch();
        if (!$c || !$cargo) {
            throw new RuntimeException('Concurso ou cargo não encontrado.');
        }
        $itens = hack_itens_do_cargo($pdo, $concursoId, $cargoId);
        $prova = json_decode((string)($c['ultima_prova'] ?? ''), true) ?: [];
        $porMateria = [];
        foreach ($itens as $i) {
            $q = (int)(($prova['itens'] ?? [])[(int)$i['id']] ?? ($prova['itens'][(string)$i['id']] ?? 0));
            $porMateria[$i['materia']]['assuntos'][] = $i['assunto'] . ($q ? " ($q questões na última prova)" : '');
            $porMateria[$i['materia']]['questoes'] = ($porMateria[$i['materia']]['questoes'] ?? 0) + $q;
        }
        $prompt = "Você é o ANTIGÃO, a IA mentora do Projeto Gabaritando: veterano, estrategista, fala direto com o aluno (\"combatente\"), frases curtas, sem enrolação.\n"
            . "Escreva para o candidato ao cargo \"{$cargo['nome']}\" do concurso \"{$c['nome']}\".\n"
            . ($c['situacao'] === 'aberto' ? "O EDITAL ESTÁ ABERTO.\n" : "O edital AINDA NÃO SAIU: tudo é baseado no último edital (" . ($c['base_edital'] ?: 'anterior') . "). Deixe claro que pode mudar e que a preparação começa antes do edital.\n")
            . "Use SOMENTE os dados abaixo. Onde não houver dado, não invente número: dê a orientação estratégica geral.\n\n"
            . "1) HACKEANDO O EDITAL: 6 a 9 blocos curtos. O que mais importa no edital para ESTE cargo e o que fazer com isso: requisitos que eliminam (idade, altura, CNH, escolaridade), etapas e como se preparar para cada uma (inclusive TAF desde já), datas e prazos que não pode perder, salário e vagas como motivação, armadilhas do edital.\n"
            . "2) HACKEANDO A PROVA: 6 a 9 blocos. Onde estão os pontos: matérias e assuntos que mais caem (use os números), onde ganhar ponto rápido, estilo da banca, critério de aprovação/eliminação, gestão de tempo na prova, ordem para resolver, e o que estudar primeiro.\n"
            . "3) RESUMO_ANTIGAO: 2 a 3 frases de abertura motivadora e estratégica.\n"
            . "Cada bloco: {\"icone\":\"um emoji\",\"titulo\":\"curto\",\"texto\":\"2 a 4 frases\"}.\n"
            . "Responda SOMENTE em JSON: {\"resumo_antigao\":\"\",\"hack_edital\":[...],\"hack_prova\":[...]}\n\n"
            . "INFORMAÇÕES DO EDITAL: " . mb_substr((string)($c['edital_info'] ?? '{}'), 0, 20000) . "\n"
            . "BANCA: " . ($c['banca_nome'] ?: 'não informada') . " · DATA DA PROVA: " . ($c['data_prova'] ?: 'não definida') . "\n"
            . "ÚLTIMA PROVA: " . (!empty($prova['encontrada']) ? trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '')) . ' (fonte: ' . ($prova['fonte'] ?? '') . ')' : 'distribuição não disponível') . "\n"
            . "CONTEÚDO PROGRAMÁTICO DO CARGO (matéria: assuntos; total de questões na última prova): " . json_encode($porMateria, JSON_UNESCAPED_UNICODE);
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.5]], 150);
        $pdo = podcast_reconectar($pdo);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        $limpa = static function ($lista): array {
            $r = [];
            foreach ((array)$lista as $b) {
                $t = trim((string)($b['titulo'] ?? ''));
                $x = trim((string)($b['texto'] ?? ''));
                if ($t !== '' && $x !== '') {
                    $r[] = ['icone' => mb_substr((string)($b['icone'] ?? '📌'), 0, 4), 'titulo' => mb_substr($t, 0, 120), 'texto' => mb_substr($x, 0, 900)];
                }
            }
            return array_slice($r, 0, 12);
        };
        $res = ['resumo_antigao' => mb_substr(trim((string)($j['resumo_antigao'] ?? '')), 0, 600), 'hack_edital' => $limpa($j['hack_edital'] ?? []), 'hack_prova' => $limpa($j['hack_prova'] ?? []), 'gerado_em' => date('Y-m-d H:i')];
        if (!$res['hack_edital'] || !$res['hack_prova']) {
            throw new RuntimeException('A IA não montou os textos. Tente de novo.');
        }
        $hacks = json_decode((string)($c['hacks'] ?? ''), true) ?: [];
        $hacks[(string)$cargoId] = $res;
        $pdo->prepare('UPDATE concursos SET hacks = :h WHERE id = :id')->execute([':h' => json_encode($hacks, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
        return $res;
    }

    function hack_itens_do_cargo(PDO $pdo, int $concursoId, int $cargoId): array
    {
        return array_values(array_filter(edital_itens($pdo, $concursoId), static function (array $i) use ($cargoId): bool {
            $ids = array_filter(array_map('intval', explode(',', (string)$i['cargos'])));
            return !$ids || in_array($cargoId, $ids, true);
        }));
    }

    // ---------------- Aulas: PDF por assunto e geração pela IA ----------------
    /** Cria (ou completa) a aula do assunto em cada curso dos cargos, com PDF e/ou vídeo. */
    function hack_aula_do_item(PDO $pdo, int $editalItemId, array $cargoIds, string $titulo, string $url, string $pdf): array
    {
        $ids = edital_criar_aula($pdo, $editalItemId, $cargoIds, $titulo, $url);
        foreach ($ids as $aid) {
            if ($pdf !== '') {
                $pdo->prepare('UPDATE admin_curso_aulas SET pdf_arquivo = :p WHERE id = :id')->execute([':p' => $pdf, ':id' => $aid]);
            }
            ia_fila_adicionar($pdo, 'apostila', $aid); // apostila + podcast (+ videoaula do Antigão se não tiver vídeo)
        }
        return $ids;
    }

    /** Para cada assunto do cargo sem aula: cria a aula "gerada pela IA" e põe na fila. */
    function hack_gerar_faltantes(PDO $pdo, int $concursoId): int
    {
        $n = 0;
        foreach (edital_andamento($pdo, $concursoId) as $an) {
            if (empty($an['cargo']['curso_id'])) {
                continue;
            }
            foreach ($an['itens'] as $it) {
                if ($it['aulas']) {
                    continue;
                }
                $ids = edital_criar_aula($pdo, (int)$it['id'], [(int)$an['cargo']['id']], $it['assunto'], '');
                foreach ($ids as $aid) {
                    ia_fila_adicionar($pdo, 'aula_ia', $aid);
                    $n++;
                }
            }
        }
        return $n;
    }

    /** Robô: escreve a teoria mínima de uma aula sem material (só pelo assunto e o edital). */
    function hack_aula_ia_processar(PDO $pdo, int $aulaId): void
    {
        $st = $pdo->prepare('SELECT a.*, m.nome AS materia, t.titulo AS assunto, c.nome AS curso FROM admin_curso_aulas a
                               LEFT JOIN admin_materias m ON m.id = a.materia_id LEFT JOIN admin_materia_topicos t ON t.id = a.topico_id
                               LEFT JOIN admin_cursos c ON c.id = a.curso_id WHERE a.id = :id');
        $st->execute([':id' => $aulaId]);
        $a = $st->fetch();
        if (!$a) {
            throw new RuntimeException('Aula não existe mais.');
        }
        if (trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '') {
            ia_fila_adicionar($pdo, 'apostila', $aulaId); // ganhou material de verdade: segue o fluxo normal
            return;
        }
        // Assunto grande vira mais de uma aula (cada uma rende uma videoaula de 10 a 15 minutos).
        $foco = '';
        if (preg_match('~ · Parte \d+: (.+)$~u', (string)$a['titulo'], $mP)) {
            $foco = trim($mP[1]);
        } else {
            try {
                $plano = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => "Você planeja aulas do Projeto Gabaritando. Cada aula vira uma videoaula de 10 a 15 minutos.\n"
                    . "Matéria: {$a['materia']} · Assunto do edital: {$a['assunto']} · Curso: {$a['curso']}.\n"
                    . "Quantas aulas esse assunto precisa para ser bem ensinado para a prova (1 a 4)? Assunto curto = 1 aula. Assunto amplo (ex.: classes de palavras, crimes contra a administração) = 2 a 4, cada uma com um foco claro, do básico ao avançado.\n"
                    . "Responda SOMENTE em JSON: {\"partes\":[{\"foco\":\"título curto do foco da aula\"}]}"]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2]], 60);
                $pdo = podcast_reconectar($pdo);
                $partes = array_values(array_filter(array_map(static fn($x) => trim((string)($x['foco'] ?? '')), (array)(edital_json_do_texto((string)($plano['candidates'][0]['content']['parts'][0]['text'] ?? ''))['partes'] ?? []))));
            } catch (RuntimeException $e) {
                $partes = [];
            }
            $partes = array_slice($partes, 0, 4);
            if (count($partes) > 1) {
                $base = trim((string)$a['titulo']);
                $foco = $partes[0];
                // Abre espaço na ordem do módulo para as partes ficarem juntas, logo depois da Parte 1.
                $pdo->prepare('UPDATE admin_curso_aulas SET ordem = ordem + :n WHERE modulo_id = :m AND ordem > :o')->execute([':n' => count($partes) - 1, ':m' => (int)$a['modulo_id'], ':o' => (int)$a['ordem']]);
                $pdo->prepare('UPDATE admin_curso_aulas SET titulo = :t WHERE id = :id')->execute([':t' => mb_substr($base . ' · Parte 1: ' . $partes[0], 0, 180), ':id' => $aulaId]);
                foreach (array_slice($partes, 1) as $k => $f) {
                    $pdo->prepare('INSERT INTO admin_curso_aulas (curso_id, modulo_id, materia_id, topico_id, titulo, ordem, criado_em) VALUES (:c, :m, :ma, :t, :ti, :o, NOW())')
                        ->execute([':c' => (int)$a['curso_id'], ':m' => (int)$a['modulo_id'], ':ma' => (int)$a['materia_id'], ':t' => (int)$a['topico_id'], ':ti' => mb_substr($base . ' · Parte ' . ($k + 2) . ': ' . $f, 0, 180), ':o' => (int)$a['ordem'] + $k + 1]);
                    ia_fila_adicionar($pdo, 'aula_ia', (int)$pdo->lastInsertId());
                }
            }
        }
        $prompt = "Você é professor do Projeto Gabaritando (cursinho para carreiras policiais e militares da Bahia) e escreve a TEORIA MÍNIMA de uma aula que ainda não tem vídeo nem PDF.\n"
            . "Curso: {$a['curso']} · Matéria: {$a['materia']} · Assunto do edital: {$a['assunto']}" . ($foco !== '' ? " · FOCO DESTA AULA: $foco (as outras partes do assunto ficam para as outras aulas)" : '') . ".\n"
            . "Escreva em HTML simples (h2, h3, p, ul, li, strong, table), em português do Brasil, linguagem direta para o aluno (\"combatente\"):\n"
            . "1. <h2>O que cai na prova</h2>: em 2 frases, por que o assunto importa.\n"
            . "2. <h2>Teoria mínima</h2>: os conceitos essenciais, do simples ao difícil, com um exemplo para cada um.\n"
            . "3. <h2>Pegadinhas da banca</h2>: 3 a 5 pegadinhas clássicas com o macete para não cair.\n"
            . "4. <h2>Resumo para revisão</h2>: 5 a 8 tópicos curtos.\n"
            . "5. <h2>Sua missão</h2>: resolver pelo menos 5 questões do assunto.\n"
            . "Seja fiel ao conteúdo consagrado da matéria (leis citadas pelo nome e artigo apenas quando tiver certeza). Sempre \"Projeto Gabaritando\" por extenso. Entre 1200 e 2000 palavras, com bastante exemplo. Responda só o HTML.";
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.4]], 150);
        $pdo = podcast_reconectar($pdo);
        $html = '';
        foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $p) {
            $html .= (string)($p['text'] ?? '');
        }
        $html = trim((string)preg_replace('~^```(?:html)?\s*|\s*```$~m', '', $html));
        if (function_exists('aula_desc_sanitizar_html')) {
            $html = aula_desc_sanitizar_html($html);
        }
        if (mb_strlen(strip_tags($html)) < 300) {
            throw new RuntimeException('A IA devolveu um texto curto demais.');
        }
        $html = '<p class="aula-ia-aviso"><strong>🤖 Aula preparada pelo Antigão</strong> a partir do edital, enquanto a aula do professor não chega.</p>' . $html;
        $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d WHERE id = :id')->execute([':d' => $html, ':id' => $aulaId]);
        ia_fila_adicionar($pdo, 'videoaula', $aulaId);
        ia_fila_adicionar($pdo, 'podcast', $aulaId);
    }

    // ---------------- Progresso do cadastro (barra do mentor) ----------------
    /** Etapas do cadastro, cada uma com feito/total e um texto do que falta. */
    function hack_progresso(PDO $pdo, int $concursoId): array
    {
        hack_bootstrap($pdo);
        $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $c = $st->fetch();
        if (!$c) {
            return ['etapas' => [], 'pct' => 0, 'proxima' => 'concurso'];
        }
        $cg = $pdo->prepare('SELECT * FROM concurso_cargos WHERE concurso_id = :c');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll();
        $ligados = count(array_filter($cargos, static fn($x) => !empty($x['curso_id'])));
        $itens = edital_itens($pdo, $concursoId);
        $info = json_decode((string)($c['edital_info'] ?? ''), true);
        $prova = json_decode((string)($c['ultima_prova'] ?? ''), true);
        $andamento = edital_andamento($pdo, $concursoId);
        $cob = array_sum(array_column($andamento, 'cobertos'));
        $tot = array_sum(array_column($andamento, 'total'));
        $planos = 0;
        foreach ($cargos as $x) {
            if (!empty($x['curso_id'])) {
                $p = $pdo->prepare("SELECT COUNT(*) FROM meta_planos WHERE curso_id = :c AND status = 'aprovado'");
                $p->execute([':c' => (int)$x['curso_id']]);
                $planos += (int)$p->fetchColumn() > 0 ? 1 : 0;
            }
        }
        $hacks = json_decode((string)($c['hacks'] ?? ''), true) ?: [];
        $vert = json_decode((string)($c['verticalizado'] ?? ''), true) ?: [];
        $provasOk = count(array_filter(json_decode((string)($c['provas'] ?? ''), true) ?: [], static fn($x) => ($x['status'] ?? '') === 'confere'));
        $qs = hack_questoes_situacao($pdo, $concursoId);
        $rascEdital = hack_rascunho_get($pdo, $concursoId, 'edital_texto', '');
        $rascAulas = hack_rascunho_get($pdo, $concursoId, 'sugestoes', []);
        $e = [
            'concurso' => ['nome' => 'Concurso e cargos', 'ok' => $cargos && $ligados === count($cargos), 'det' => count($cargos) . ' cargo(s) · ' . $ligados . ' ligado(s) a curso'],
            'edital' => ['nome' => 'Edital', 'ok' => $itens && is_array($info), 'det' => ($itens ? count($itens) . ' assuntos' : 'conteúdo programático pendente') . ' · ' . (is_array($info) ? 'informações lidas' : 'informações pendentes') . ($rascEdital ? ' · rascunho salvo' : '')],
            'prova' => ['nome' => 'Última prova', 'ok' => $provasOk >= count($cargos) && $cargos || !empty($prova['encontrada']) || ($c['prova_status'] ?? '') === 'sem_prova', 'det' => !empty($prova['encontrada']) ? 'última prova: ' . trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '')) : (($c['prova_status'] ?? '') === 'sem_prova' ? 'sem prova anterior (pesos estimados)' : 'última prova pendente')],
            'aulas' => ['nome' => 'Aulas por assunto', 'ok' => $tot > 0 && $cob >= $tot, 'det' => $cob . '/' . $tot . ' assuntos com aula' . ($rascAulas ? ' · ' . count($rascAulas) . ' sugestão(ões) para conferir' : '')],
            'questoes' => ['nome' => 'Questões por assunto', 'ok' => $qs['total'] > 0 && $qs['ok'] >= $qs['total'], 'det' => $qs['ok'] . '/' . $qs['total'] . ' assuntos com 10+ questões'],
            'plano' => ['nome' => 'Plano-Mestre', 'ok' => $cargos && $planos >= count($cargos), 'det' => $planos . '/' . count($cargos) . ' cargo(s) com plano aprovado'],
            'publicar' => ['nome' => 'Hackeando para o aluno', 'ok' => $cargos && count($hacks) >= count($cargos) && count($vert) >= count($cargos), 'det' => count($vert) . '/' . count($cargos) . ' verticalizado(s) · ' . count($hacks) . '/' . count($cargos) . ' com textos do Antigão'],
        ];
        $nc = max(1, count($cargos));
        $pcts = [
            'concurso' => $cargos ? $ligados * 100 / $nc : 0,
            'edital' => ($itens ? 50 : 0) + (is_array($info) ? 50 : 0),
            'prova' => (!empty($prova['encontrada']) || ($c['prova_status'] ?? '') === 'sem_prova') ? 100 : $provasOk * 100 / $nc,
            'aulas' => $tot ? $cob * 100 / $tot : 0,
            'questoes' => $qs['total'] ? $qs['ok'] * 100 / $qs['total'] : 0,
            'plano' => $planos * 100 / $nc,
            'publicar' => (min(count($vert), $nc) + min(count($hacks), $nc)) * 50 / $nc,
        ];
        foreach ($e as $k => &$x) {
            $x['pct'] = $x['ok'] ? 100 : (int)min(99, round($pcts[$k] ?? 0));
        }
        unset($x);
        $feitas = count(array_filter($e, static fn($x) => $x['ok']));
        // Peso extra das aulas (é a etapa mais longa): conta a fração coberta.
        $pct = (int)round((($feitas - ($e['aulas']['ok'] ? 1 : 0)) + ($tot ? $cob / $tot : 0)) * 100 / count($e));
        $proxima = 'publicar';
        foreach ($e as $k => $x) {
            if (!$x['ok']) {
                $proxima = $k;
                break;
            }
        }
        return ['etapas' => $e, 'pct' => min(100, $pct), 'proxima' => $proxima, 'feitas' => $feitas];
    }

    // ---------------- Aluno ----------------
    function hack_escolha_aluno(PDO $pdo, int $usuarioId): ?array
    {
        hack_bootstrap($pdo);
        $st = $pdo->prepare('SELECT ac.*, c.nome AS concurso, c.situacao, c.base_edital, c.data_prova, cg.nome AS cargo, cg.curso_id
                               FROM aluno_concurso ac JOIN concursos c ON c.id = ac.concurso_id JOIN concurso_cargos cg ON cg.id = ac.cargo_id
                              WHERE ac.usuario_id = :u');
        $st->execute([':u' => $usuarioId]);
        return $st->fetch() ?: null;
    }

    function hack_registrar_visita(PDO $pdo, int $usuarioId): void
    {
        hack_bootstrap($pdo);
        $pdo->prepare('INSERT IGNORE INTO aluno_edital_visitas (usuario_id, dia) VALUES (:u, CURDATE())')->execute([':u' => $usuarioId]);
    }

    /**
     * Cronograma: "Hackeando o edital" (10 min) a cada 2 dias de estudo.
     * Hoje some se o aluno já visitou a página hoje.
     */
    function hack_no_cronograma(PDO $pdo, int $usuarioId, array $agenda): array
    {
        try {
            $esc = hack_escolha_aluno($pdo, $usuarioId);
        } catch (Throwable $e) {
            return $agenda;
        }
        if (!$esc) {
            return $agenda;
        }
        $st = $pdo->prepare('SELECT 1 FROM aluno_edital_visitas WHERE usuario_id = :u AND dia = CURDATE()');
        $st->execute([':u' => $usuarioId]);
        $visitouHoje = (bool)$st->fetchColumn();
        $hoje = date('Y-m-d');
        $dias = array_keys($agenda);
        sort($dias);
        $n = 0;
        foreach ($dias as $d) {
            if ($n++ % 2 !== 0 || ($d === $hoje && $visitouHoje)) {
                continue;
            }
            // Os 10 min saem da maior tarefa do dia, para não passar do tempo que o aluno tem.
            $maior = null;
            foreach ($agenda[$d] as $k => $t) {
                if (($t['tipo'] ?? '') !== 'edital' && (int)$t['minutos'] >= 20 && ($maior === null || (int)$t['minutos'] > (int)$agenda[$d][$maior]['minutos'])) {
                    $maior = $k;
                }
            }
            if ($maior === null) {
                continue; // dia curto demais: o edital fica para o próximo dia de estudo
            }
            $agenda[$d][$maior]['minutos'] -= 10;
            array_unshift($agenda[$d], ['tipo' => 'edital', 'minutos' => 10,
                'item' => ['id' => 0, 'titulo' => 'Hackeando o edital · ' . $esc['cargo'], 'materia_nome' => $esc['concurso'], 'materia_id' => 0, 'topico_id' => 0]]);
        }
        return $agenda;
    }
    // ---------------- Questões por assunto ----------------
    /** "Banca" própria das questões geradas: "Antigão · estilo <banca de referência>". */
    function hack_banca_estilo(PDO $pdo, ?int $bancaRef): int
    {
        $ref = '';
        if ($bancaRef) {
            $st = $pdo->prepare('SELECT nome FROM admin_bancas WHERE id = :id');
            $st->execute([':id' => $bancaRef]);
            $ref = (string)$st->fetchColumn();
        }
        $sigla = $ref !== '' && preg_match('~/\s*([A-Z]{2,})\s*$~', $ref, $m) ? $m[1] : $ref;
        $nome = 'Antigão · estilo ' . ($sigla !== '' && $sigla !== 'A definir' ? $sigla : 'da banca');
        $st = $pdo->prepare('SELECT id FROM admin_bancas WHERE nome = :n LIMIT 1');
        $st->execute([':n' => $nome]);
        if ($id = (int)$st->fetchColumn()) {
            return $id;
        }
        $pdo->prepare('INSERT INTO admin_bancas (nome, descricao, criado_em) VALUES (:n, :d, NOW())')->execute([':n' => $nome, ':d' => 'Questões inéditas criadas pelo Antigão (IA) no estilo da banca de referência. Não são questões da banca.']);
        return (int)$pdo->lastInsertId();
    }

    function hack_rotulo_gerada(PDO $pdo, ?int $bancaRef, ?string $data = null): string
    {
        $ref = 'da banca do concurso';
        if ($bancaRef) {
            $st = $pdo->prepare('SELECT nome FROM admin_bancas WHERE id = :id');
            $st->execute([':id' => $bancaRef]);
            $ref = (string)$st->fetchColumn() ?: $ref;
        }
        return '🤖 Questão inédita do Antigão, no estilo ' . ($ref === 'A definir' ? 'da banca do concurso' : $ref) . ', gerada em ' . date('d/m/Y', strtotime($data ?? 'now')) . '. Não é questão de prova.';
    }

    /** Corrige as questões geradas antes desta regra: tira banca e ano de prova, põe estilo e data de geração. */
    function hack_corrigir_geradas(PDO $pdo): void
    {
        $st = $pdo->query("SELECT q.id, q.banca_id, q.criado_em, q.explicacao FROM admin_banco_questoes q JOIN admin_bancas b ON b.id = q.banca_id
                            WHERE q.explicacao LIKE '%Questão inédita do Antigão, no nível da prova.%' AND b.nome NOT LIKE 'Antigão · estilo%' LIMIT 500");
        $upd = $pdo->prepare('UPDATE admin_banco_questoes SET banca_id = :b, ano = NULL, explicacao = :x WHERE id = :id');
        foreach ($st->fetchAll() as $q) {
            $x = str_replace('🤖 Questão inédita do Antigão, no nível da prova.', hack_rotulo_gerada($pdo, (int)$q['banca_id'], (string)$q['criado_em']), (string)$q['explicacao']);
            $upd->execute([':b' => hack_banca_estilo($pdo, (int)$q['banca_id']), ':x' => $x, ':id' => (int)$q['id']]);
        }
    }

    /** Cursos (dos cargos) que cobrem o assunto do edital. */
    function hack_cursos_do_item(PDO $pdo, array $item): array
    {
        $cg = $pdo->prepare('SELECT id, curso_id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
        $cg->execute([':c' => (int)$item['concurso_id']]);
        $ids = array_filter(array_map('intval', explode(',', (string)$item['cargos'])));
        $cursos = [];
        foreach ($cg->fetchAll() as $x) {
            if (!$ids || in_array((int)$x['id'], $ids, true)) {
                $cursos[] = (int)$x['curso_id'];
            }
        }
        return array_values(array_unique($cursos));
    }

    /** Grava questões no banco, ligadas ao assunto e liberadas para os cursos dos cargos. */
    function hack_gravar_questoes(PDO $pdo, array $item, array $questoes, ?int $bancaId, string $origem): int
    {
        $cursos = hack_cursos_do_item($pdo, $item);
        $bancaRef = $bancaId;
        if (!$bancaId) {
            // O banco de questões exige banca: usa "A definir" quando o concurso ainda não tem.
            $bancaId = (int)$pdo->query("SELECT id FROM admin_bancas WHERE nome = 'A definir' LIMIT 1")->fetchColumn();
            if (!$bancaId) {
                $pdo->exec("INSERT INTO admin_bancas (nome, criado_em) VALUES ('A definir', NOW())");
                $bancaId = (int)$pdo->lastInsertId();
            }
        }
        $n = 0;
        $insQ = $pdo->prepare("INSERT INTO admin_banco_questoes (curso_id, banca_id, materia_id, materia_topico_id, ano, enunciado, explicacao, dificuldade, status, ordem, criado_em)
                               VALUES (:c, :b, :m, :t, :a, :e, :x, :d, 'ativo', 0, NOW())");
        $insO = $pdo->prepare('INSERT INTO admin_banco_questao_opcoes (questao_id, letra, texto, correta, ordem, criado_em) VALUES (:q, :l, :t, :c, :o, NOW())');
        $insA = $pdo->prepare('INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id) VALUES (:q, :c)');
        $dup = $pdo->prepare('SELECT id FROM admin_banco_questoes WHERE materia_topico_id = :t AND LEFT(enunciado, 180) = LEFT(:e, 180) LIMIT 1');
        foreach ($questoes as $q) {
            $enun = trim((string)($q['enunciado'] ?? ''));
            $alts = array_values(array_filter(array_map(static fn($a) => trim((string)$a), (array)($q['alternativas'] ?? [])), 'strlen'));
            $cor = (int)($q['correta'] ?? -1);
            if ($enun === '' || count($alts) < 2 || !isset($alts[$cor])) {
                continue;
            }
            $dup->execute([':t' => (int)$item['topico_id'], ':e' => $enun]);
            if ($dup->fetchColumn()) {
                continue;
            }
            $fonte = trim((string)($q['fonte'] ?? ''));
            $exp = trim((string)($q['comentario'] ?? ''));
            $exp = ($origem === 'gerada' ? hack_rotulo_gerada($pdo, $bancaRef) . ' ' : ($fonte !== '' ? '📌 Fonte: ' . $fonte . '. ' : '')) . $exp;
            $dif = in_array($q['dificuldade'] ?? '', ['facil', 'medio', 'dificil'], true) ? $q['dificuldade'] : 'medio';
            // Gerada: não é da banca nem de um ano de prova. Vai na "banca" "Antigão · estilo X", sem ano.
            $insQ->execute([':c' => $cursos[0] ?? null, ':b' => $origem === 'gerada' ? hack_banca_estilo($pdo, $bancaRef) : $bancaId, ':m' => (int)$item['materia_id'], ':t' => (int)$item['topico_id'],
                ':a' => $origem === 'gerada' ? null : (((int)($q['ano'] ?? 0)) ?: null), ':e' => mb_substr($enun, 0, 6000), ':x' => mb_substr($exp, 0, 6000), ':d' => $dif]);
            $qid = (int)$pdo->lastInsertId();
            foreach ($alts as $k => $alt) {
                $insO->execute([':q' => $qid, ':l' => chr(65 + $k), ':t' => mb_substr($alt, 0, 3000), ':c' => $k === $cor ? 1 : 0, ':o' => $k + 1]);
            }
            foreach ($cursos as $cu) {
                $insA->execute([':q' => $qid, ':c' => $cu]);
            }
            $n++;
        }
        return $n;
    }

    function hack_questoes_json(array $resp): array
    {
        $txt = '';
        foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $p) {
            $txt .= (string)($p['text'] ?? '');
        }
        $j = edital_json_do_texto($txt) ?? [];
        return (array)($j['questoes'] ?? []);
    }


    /**
     * Questões de UM assunto: 1) pesquisa questões reais na internet (mesma banca primeiro);
     * 2) se achar poucas, gera questões inéditas no nível da prova. Retorna [reais, geradas].
     */
    function hack_questoes_do_item(PDO $pdo, int $editalItemId, int $meta = 10): array
    {
        $st = $pdo->prepare('SELECT e.*, m.nome AS materia, t.titulo AS assunto, c.nome AS concurso, c.banca_id, b.nome AS banca
                               FROM edital_itens e JOIN admin_materias m ON m.id = e.materia_id JOIN admin_materia_topicos t ON t.id = e.topico_id
                               JOIN concursos c ON c.id = e.concurso_id LEFT JOIN admin_bancas b ON b.id = c.banca_id WHERE e.id = :id');
        $st->execute([':id' => $editalItemId]);
        $it = $st->fetch();
        if (!$it) {
            throw new RuntimeException('Assunto do edital não encontrado.');
        }
        $ja = $pdo->prepare("SELECT COUNT(*) FROM admin_banco_questoes WHERE materia_topico_id = :t AND status = 'ativo'");
        $ja->execute([':t' => (int)$it['topico_id']]);
        $falta = $meta - (int)$ja->fetchColumn();
        if ($falta <= 0) {
            return [0, 0];
        }
        $banca = $it['banca'] ?: 'a banca do concurso';
        $base = "Concurso: {$it['concurso']} · Banca: $banca · Matéria: {$it['materia']} · Assunto: {$it['assunto']}.\n";
        // Cargos que este assunto atende (ex.: Soldado PM, Soldado Bombeiro) para buscar provas equivalentes em todo o Brasil.
        $cgN = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE concurso_id = :c');
        $cgN->execute([':c' => (int)$it['concurso_id']]);
        $cargosTxt = implode(', ', $cgN->fetchAll(PDO::FETCH_COLUMN)) ?: 'carreiras policiais e militares';
        // Somos especialistas: a busca vai do MESMO CARGO para os cargos CORRELATOS da mesma área, e só depois amplia.
        $nCargos = mb_strtolower($cargosTxt);
        $area = str_contains($nCargos, 'bombeir') ? 'bombeiros militares (CBM de todos os estados) e, em seguida, policiais militares'
            : (str_contains($nCargos, 'civil') || str_contains($nCargos, 'escriv') || str_contains($nCargos, 'investig') || str_contains($nCargos, 'delegad') ? 'Polícia Civil de todos os estados, Polícia Federal e Polícia Rodoviária Federal'
            : (str_contains($nCargos, 'penal') || str_contains($nCargos, 'agente penitenci') ? 'Polícia Penal e agentes penitenciários de todos os estados'
            : 'policiais militares (PM de todos os estados) e, em seguida, bombeiros militares, Polícia Civil, Polícia Penal, PRF e Forças Armadas (EsSA, EEAR, Marinha)'));
        $rodadas = [
            "provas do MESMO CARGO ($cargosTxt) aplicadas pela banca $banca em qualquer estado do Brasil",
            "provas do MESMO CARGO ($cargosTxt) em TODOS OS ESTADOS do Brasil, de qualquer banca",
            "provas de cargos CORRELATOS da mesma área: $area, de qualquer banca e estado",
            "provas de concursos públicos de nível médio da área de segurança pública e, por último, de nível médio em geral",
        ];
        $reais = 0;
        foreach ($rodadas as $onde) {
            $resta = $falta - $reais;
            if ($resta <= 0) {
                break;
            }
            try {
                $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => "Pesquise na internet QUESTÕES REAIS já aplicadas em $onde, sobre o assunto abaixo. Prefira as mais recentes e as que cobram o assunto do jeito que cai para esse cargo.\n" . $base
                    . "Traga até $resta questões objetivas COMPLETAS, copiadas fielmente, com o gabarito oficial e a fonte (banca, órgão, estado, ano). Só traga questões que você realmente encontrou; não invente e não repita. Se não encontrar, devolva lista vazia.\n"
                    . "Responda SOMENTE em JSON: " . HACK_FORMATO_Q]]]], 'tools' => [['google_search' => new stdClass()]]], 150);
                $pdo = podcast_reconectar($pdo);
                $reais += hack_gravar_questoes($pdo, $it, array_slice(hack_questoes_json($resp), 0, $resta), $it['banca_id'] ? (int)$it['banca_id'] : null, 'real');
            } catch (RuntimeException $e) {
                if ($e instanceof GeminiCotaException || str_contains($e->getMessage(), '(429)')) {
                    throw $e; // sem cota de pesquisa: o assunto espera; nada é criado antes de procurar as reais
                }
                $pdo = podcast_reconectar($pdo);
            }
        }
        $geradas = 0;
        if ($reais < $falta) {
            $n = $falta - $reais;
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => "Você é elaborador de provas da banca $banca, especialista no cargo ($cargosTxt). Crie $n questões INÉDITAS de múltipla escolha, no MESMO nível e estilo da prova deste cargo, com situações do dia a dia da profissão quando o assunto permitir.\n" . $base
                . "Regras: 5 alternativas (A a E) plausíveis, só uma correta, cobrindo pegadinhas clássicas do assunto; misture fácil, médio e difícil (30/50/20); conteúdo correto e atual; comentário didático.\n"
                . "Responda SOMENTE em JSON: " . HACK_FORMATO_Q]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.6]], 150);
            $pdo = podcast_reconectar($pdo);
            $geradas = hack_gravar_questoes($pdo, $it, hack_questoes_json($resp), $it['banca_id'] ? (int)$it['banca_id'] : null, 'gerada');
        }
        return [$reais, $geradas];
    }

    /** Questões enviadas pelo mentor (texto ou PDF): a IA separa por assunto do edital e grava. */
    function hack_questoes_enviadas(PDO $pdo, int $concursoId, string $texto, string $pdfBase64): int
    {
        $itens = edital_itens($pdo, $concursoId);
        if (!$itens) {
            throw new RuntimeException('Cadastre o edital antes das questões.');
        }
        $lista = array_map(static fn($i) => ['id' => (int)$i['id'], 'assunto' => $i['materia'] . ' › ' . $i['assunto']], $itens);
        $prompt = "Extraia TODAS as questões objetivas do material (texto e/ou PDF), com o gabarito (se houver gabarito no fim do material, use-o; se não houver, resolva com cuidado).\n"
            . "Para cada questão, diga a qual assunto do edital ela pertence (edital_item_id da lista) e escreva um comentário didático.\n"
            . "Responda SOMENTE em JSON: {\"questoes\":[{\"edital_item_id\":n,\"enunciado\":\"\",\"alternativas\":[\"\"],\"correta\":0,\"comentario\":\"\",\"dificuldade\":\"medio\",\"ano\":null,\"fonte\":\"\"}]}\n\n"
            . "ASSUNTOS DO EDITAL: " . json_encode($lista, JSON_UNESCAPED_UNICODE) . "\n\nMATERIAL:\n" . mb_substr($texto, 0, 60000);
        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 170);
        $pdo = podcast_reconectar($pdo);
        $porItem = [];
        foreach (hack_questoes_json($resp) as $q) {
            $porItem[(int)($q['edital_item_id'] ?? 0)][] = $q;
        }
        $st = $pdo->prepare('SELECT banca_id FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $banca = (int)$st->fetchColumn() ?: null;
        $n = 0;
        foreach ($itens as $it) {
            if (!empty($porItem[(int)$it['id']])) {
                $n += hack_gravar_questoes($pdo, $it, $porItem[(int)$it['id']], $banca, 'enviada');
            }
        }
        return $n;
    }

    /** Situação das questões por assunto do concurso. */
    function hack_questoes_situacao(PDO $pdo, int $concursoId, int $meta = 10): array
    {
        $r = ['total' => 0, 'ok' => 0, 'itens' => []];
        $itens = edital_itens($pdo, $concursoId);
        $cont = [];
        if ($itens) {
            // Uma consulta só (a Central da IA chama isto a cada poucos segundos).
            $ids = implode(',', array_map(static fn($i) => (int)$i['topico_id'], $itens));
            $cont = $pdo->query("SELECT materia_topico_id, COUNT(*) FROM admin_banco_questoes WHERE materia_topico_id IN ($ids) AND status = 'ativo' GROUP BY materia_topico_id")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        foreach ($itens as $it) {
            $n = (int)($cont[(int)$it['topico_id']] ?? 0);
            $r['total']++;
            $r['ok'] += $n >= $meta ? 1 : 0;
            $r['itens'][] = $it + ['questoes' => $n];
        }
        return $r;
    }
    // ---------------- Jornada até a nomeação (o Antigão acompanha cada etapa) ----------------
    /** Etapas da jornada do aluno, montadas com as etapas do edital do cargo. */
    function hack_jornada_etapas(array $info): array
    {
        $e = [
            ['id' => 'preparacao', 'nome' => 'Preparação', 'antigao' => 'Agora é ciclo todo dia: teoria, questões, revisão. Quem chega pronto no edital sai na frente.'],
            ['id' => 'inscricao', 'nome' => 'Inscrição', 'antigao' => 'Edital na rua! Faça a inscrição nos primeiros dias, confira o comprovante e guarde tudo. Não deixe para o último dia.'],
            ['id' => 'objetiva', 'nome' => 'Prova objetiva', 'antigao' => 'Reta final: simulados no tempo de prova e revisão do que mais cai. Na véspera, descanso e documento separado.'],
        ];
        foreach ((array)($info['etapas'] ?? []) as $et) {
            $nome = trim((string)($et['nome'] ?? ''));
            $n = edital_normalizar($nome);
            if ($nome === '' || str_contains($n, 'objetiva') || str_contains($n, 'inscri')) {
                continue;
            }
            $dica = str_contains($n, 'taf') || str_contains($n, 'fisic') ? 'TAF se ganha no treino de meses, não na semana. Siga a planilha e teste os índices do edital.'
                : (str_contains($n, 'reda') ? 'Redação toda semana, corrigida. Estrutura pronta na cabeça e leitura de temas de segurança pública.'
                : (str_contains($n, 'medic') || str_contains($n, 'saude') || str_contains($n, 'exame') ? 'Separe os exames com antecedência e confira a lista do edital item por item.'
                : (str_contains($n, 'psico') ? 'Durma bem, seja você mesmo e siga as instruções com calma.'
                : (str_contains($n, 'social') || str_contains($n, 'investig') || str_contains($n, 'document') ? 'Documentação completa e verdadeira. Junte certidões cedo: atraso aqui elimina.'
                : 'Leia no edital o que é cobrado nesta etapa e prepare-se com antecedência.'))));
            $e[] = ['id' => 'e_' . substr(md5($n), 0, 8), 'nome' => $nome, 'antigao' => $dica . (trim((string)($et['resumo'] ?? '')) !== '' ? ' Pelo edital: ' . trim((string)$et['resumo']) . '.' : '')];
        }
        $e[] = ['id' => 'resultado', 'nome' => 'Resultado e convocação', 'antigao' => 'Acompanhe o Diário Oficial todo dia. Convocação perdida é vaga perdida.'];
        $e[] = ['id' => 'formacao', 'nome' => 'Curso de formação', 'antigao' => 'Você chegou na tropa! Disciplina e dedicação no curso de formação: falta pouco.'];
        $e[] = ['id' => 'nomeacao', 'nome' => 'Nomeação', 'antigao' => 'NOMEADO! Missão cumprida, combatente. A farda era questão de tempo. Conta pra gente: você é exemplo para os próximos.'];
        return $e;
    }

    function hack_jornada_atual(PDO $pdo, int $usuarioId): string
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS aluno_jornada (usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, etapa VARCHAR(40) NOT NULL, atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $st = $pdo->prepare('SELECT etapa FROM aluno_jornada WHERE usuario_id = :u');
        $st->execute([':u' => $usuarioId]);
        return (string)($st->fetchColumn() ?: 'preparacao');
    }

    function hack_jornada_avancar(PDO $pdo, int $usuarioId, string $etapa): void
    {
        hack_jornada_atual($pdo, $usuarioId);
        $pdo->prepare('INSERT INTO aluno_jornada (usuario_id, etapa) VALUES (:u, :e) ON DUPLICATE KEY UPDATE etapa = VALUES(etapa)')->execute([':u' => $usuarioId, ':e' => mb_substr($etapa, 0, 40)]);
    }
}

