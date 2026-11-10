<?php
// includes/edital-jobs-lib.php
// Tudo que a IA faz no cadastro do concurso roda EM SEGUNDO PLANO (sem erro 504):
// a página cria a tarefa, mostra o progresso ao vivo e continua quando termina.
//   ler_conteudo, ler_info, verificar_prova (por cargo), verticalizado (por cargo),
//   hacks (por cargo), sugerir_links, sugerir_existentes, questoes (por assunto).
declare(strict_types=1);

require_once __DIR__ . '/edital-hack-lib.php';

if (!function_exists('job_bootstrap')) {
    function job_bootstrap(PDO $pdo): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        hack_bootstrap($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS edital_jobs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concurso_id INT UNSIGNED NOT NULL,
            tipo VARCHAR(30) NOT NULL,
            rotulo VARCHAR(200) NOT NULL,
            params MEDIUMTEXT NULL,
            status ENUM('pendente','rodando','feito','erro') NOT NULL DEFAULT 'pendente',
            mensagem VARCHAR(600) NULL,
            tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_jobs (concurso_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['provas' => 'MEDIUMTEXT NULL', 'verticalizado' => 'MEDIUMTEXT NULL'] as $col => $def) {
            if (!$pdo->query('SHOW COLUMNS FROM concursos LIKE ' . $pdo->quote($col))->fetch()) {
                $pdo->exec("ALTER TABLE concursos ADD COLUMN `$col` $def");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS aluno_vertical (
            usuario_id BIGINT UNSIGNED NOT NULL,
            edital_item_id INT UNSIGNED NOT NULL,
            status ENUM('estudado','revisado','dominado') NOT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, edital_item_id)
        ) ENGINE=InnoDB");
        $feito = true;
    }

    function job_criar(PDO $pdo, int $concursoId, string $tipo, string $rotulo, array $params = []): int
    {
        job_bootstrap($pdo);
        // Não duplica a mesma tarefa que ainda está na fila.
        $st = $pdo->prepare("SELECT id FROM edital_jobs WHERE concurso_id = :c AND tipo = :t AND params = :p AND status IN ('pendente','rodando') LIMIT 1");
        $st->execute([':c' => $concursoId, ':t' => $tipo, ':p' => json_encode($params)]);
        if ($id = (int)$st->fetchColumn()) {
            return $id;
        }
        $pdo->prepare('INSERT INTO edital_jobs (concurso_id, tipo, rotulo, params) VALUES (:c, :t, :r, :p)')
            ->execute([':c' => $concursoId, ':t' => $tipo, ':r' => mb_substr($rotulo, 0, 200), ':p' => json_encode($params)]);
        return (int)$pdo->lastInsertId();
    }

    /** Pega a próxima tarefa (de um concurso ou de todos) e executa. Retorna o job ou null. */
    function job_rodar_proximo(PDO $pdo, int $concursoId = 0): ?array
    {
        job_bootstrap($pdo);
        // Tarefa "rodando" há mais de 6 min travou: volta para a fila (até 3 tentativas).
        $pdo->exec("UPDATE edital_jobs SET status = IF(tentativas >= 3, 'erro', 'pendente'), mensagem = IF(tentativas >= 3, 'A IA não respondeu a tempo. Tente de novo.', mensagem)
                    WHERE status = 'rodando' AND atualizado_em < NOW() - INTERVAL 6 MINUTE");
        $sql = "SELECT * FROM edital_jobs WHERE status = 'pendente'" . ($concursoId ? ' AND concurso_id = ' . (int)$concursoId : '') . ' ORDER BY id LIMIT 1';
        $job = $pdo->query($sql)->fetch();
        if (!$job) {
            return null;
        }
        $upd = $pdo->prepare("UPDATE edital_jobs SET status = 'rodando', tentativas = tentativas + 1 WHERE id = :id AND status = 'pendente'");
        $upd->execute([':id' => (int)$job['id']]);
        if ($upd->rowCount() === 0) {
            return null; // outra requisição pegou
        }
        try {
            $msg = job_executar($pdo, $job);
            $pdo = podcast_reconectar($pdo);
            $pdo->prepare("UPDATE edital_jobs SET status = 'feito', mensagem = :m WHERE id = :id")->execute([':m' => mb_substr($msg, 0, 600), ':id' => (int)$job['id']]);
            $job['status'] = 'feito';
            $job['mensagem'] = $msg;
        } catch (Throwable $e) {
            $pdo = podcast_reconectar($pdo);
            $pdo->prepare("UPDATE edital_jobs SET status = 'erro', mensagem = :m WHERE id = :id")->execute([':m' => mb_substr($e->getMessage(), 0, 600), ':id' => (int)$job['id']]);
            $job['status'] = 'erro';
            $job['mensagem'] = $e->getMessage();
        }
        return $job;
    }

    function job_executar(PDO $pdo, array $job): string
    {
        $c = (int)$job['concurso_id'];
        $p = json_decode((string)$job['params'], true) ?: [];
        $cargosNomes = static function () use ($pdo, $c): array {
            $st = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE concurso_id = :c');
            $st->execute([':c' => $c]);
            return $st->fetchAll(PDO::FETCH_COLUMN);
        };
        $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
        $st->execute([':id' => $c]);
        $conc = $st->fetch();
        if (!$conc) {
            throw new RuntimeException('Concurso não existe mais.');
        }
        switch ($job['tipo']) {
            case 'ler_conteudo':
                $lido = edital_ia_ler((string)($p['texto'] ?? ''), hack_pdf_base64($conc['edital_pdf'] ?? ''), $cargosNomes());
                $t = '';
                foreach ($lido as $m) {
                    $cg = array_filter((array)$m['cargos'], static fn($x) => edital_normalizar((string)$x) !== 'todos');
                    $t .= '## ' . $m['materia'] . ($cg ? ' [' . implode('; ', $cg) . ']' : '') . "\n" . implode("\n", array_map(static fn($a) => '- ' . $a, $m['assuntos'])) . "\n\n";
                }
                hack_rascunho_set(podcast_reconectar($pdo), $c, 'edital_texto', trim($t));
                return 'Conteúdo programático lido: ' . count($lido) . ' matérias. Confira e confirme.';
            case 'ler_info':
                $info = hack_ia_info((string)($p['texto'] ?? ''), hack_pdf_base64($conc['edital_pdf'] ?? ''), $cargosNomes());
                podcast_reconectar($pdo)->prepare('UPDATE concursos SET edital_info = :i WHERE id = :id')->execute([':i' => json_encode($info, JSON_UNESCAPED_UNICODE), ':id' => $c]);
                return 'Informações do edital lidas.';
            case 'verificar_prova':
                return job_verificar_prova($pdo, $conc, (int)$p['cargo_id']);
            case 'buscar_prova':
                $prova = edital_ultima_prova($pdo, $c, '');
                return !empty($prova['encontrada']) ? 'Última prova encontrada na internet: ' . trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '')) : 'A IA não encontrou a prova na internet. Envie o PDF de cada cargo.';
            case 'verticalizado':
                return job_verticalizado($pdo, $c, (int)$p['cargo_id']);
            case 'hacks':
                hack_ia_gerar($pdo, $c, (int)$p['cargo_id']);
                return 'Textos do Antigão prontos.';
            case 'sugerir_links':
                $sug = hack_rascunho_get($pdo, $c, 'sugestoes', []);
                foreach ((array)($p['links'] ?? []) as $l) {
                    [$url, $tit] = array_pad(array_map('trim', explode('|', (string)$l, 2)), 2, '');
                    if (preg_match('~^https?://~i', $url)) {
                        $sug[] = edital_ia_sugerir_aula($pdo, $c, $url, $tit) + ['url' => $url];
                        $pdo = podcast_reconectar($pdo);
                    }
                }
                hack_rascunho_set($pdo, $c, 'sugestoes', $sug);
                return count((array)($p['links'] ?? [])) . ' link(s) analisados.';
            case 'sugerir_existentes':
                return job_sugerir_existentes($pdo, $c, (int)($p['lote'] ?? 0));
            case 'enviar_questoes':
                $n = hack_questoes_enviadas($pdo, $c, (string)($p['texto'] ?? ''), hack_pdf_base64((string)($p['arquivo'] ?? '')));
                return $n . ' questão(ões) separadas por assunto e gravadas no banco.';
            case 'plano':
                $provas = job_provas($conc);
                $pv = $provas[(string)$p['cargo_id']] ?? null;
                $prova = ($pv && ($pv['status'] ?? '') === 'confere') ? ['encontrada' => true, 'fonte' => 'prova do cargo enviada pelo mentor', 'ano' => $pv['ano'] ?? null, 'banca' => $pv['banca'] ?? '', 'itens' => $pv['itens'] ?? []]
                    : (json_decode((string)($conc['ultima_prova'] ?? ''), true) ?: ['encontrada' => false]);
                edital_sugerir_plano($pdo, $c, (int)$p['cargo_id'], $prova);
                return 'Plano-Mestre sugerido (rascunho). Abra, confira a justificativa de cada assunto e aprove.';
            case 'questoes':
                [$r, $g] = hack_questoes_do_item($pdo, (int)$p['item_id']);
                return "$r reais encontradas, $g criadas no nível da prova.";
        }
        throw new RuntimeException('Tarefa desconhecida.');
    }

    // ---------------- Provas por cargo ----------------
    function job_provas(array $conc): array
    {
        return json_decode((string)($conc['provas'] ?? ''), true) ?: [];
    }

    function job_salvar_prova_pdf(PDO $pdo, int $concursoId, int $cargoId, string $tmp): string
    {
        $dir = dirname(__DIR__) . '/uploads/provas';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $nome = 'prova-' . $concursoId . '-' . $cargoId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.pdf';
        if (!move_uploaded_file($tmp, $dir . '/' . $nome)) {
            throw new RuntimeException('Não consegui guardar o PDF da prova.');
        }
        $st = $pdo->prepare('SELECT provas FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $provas = json_decode((string)$st->fetchColumn(), true) ?: [];
        $provas[(string)$cargoId] = ['arquivo' => 'uploads/provas/' . $nome, 'status' => 'conferindo', 'enviado_em' => date('Y-m-d H:i')];
        $pdo->prepare('UPDATE concursos SET provas = :p WHERE id = :id')->execute([':p' => json_encode($provas, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
        return 'uploads/provas/' . $nome;
    }

    /** A IA confere se o PDF é mesmo a prova objetiva daquele concurso/cargo e conta as questões por assunto. */
    function job_verificar_prova(PDO $pdo, array $conc, int $cargoId): string
    {
        $c = (int)$conc['id'];
        $provas = job_provas($conc);
        $pv = $provas[(string)$cargoId] ?? null;
        $cg = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE id = :id');
        $cg->execute([':id' => $cargoId]);
        $cargo = (string)$cg->fetchColumn();
        if (!$pv) {
            throw new RuntimeException('Envie o PDF da prova deste cargo.');
        }
        $pdf = hack_pdf_base64($pv['arquivo']);
        $itens = array_map(static fn($i) => ['id' => (int)$i['id'], 'assunto' => $i['materia'] . ' › ' . $i['assunto']], hack_itens_do_cargo($pdo, $c, $cargoId));
        $prompt = "Confira o PDF anexo. Ele deveria ser a PROVA OBJETIVA do concurso \"{$conc['nome']}\" para o cargo \"$cargo\""
            . ($conc['base_edital'] ? " (edital de referência: {$conc['base_edital']})" : '') . ".\n"
            . "1) Diga se confere (é prova objetiva? do concurso e cargo certos?). Se for de outro cargo, outro concurso, gabarito solto ou edital, confere=false e explique.\n"
            . "2) Se conferir, conte quantas questões caíram em cada assunto do edital abaixo.\n"
            . "Responda SOMENTE em JSON: {\"confere\":true,\"motivo\":\"1 frase\",\"concurso_no_pdf\":\"\",\"cargo_no_pdf\":\"\",\"ano\":2022,\"banca\":\"\",\"total_questoes\":60,\"itens\":[{\"id\":n,\"questoes\":n}]}\n\n"
            . "ASSUNTOS: " . json_encode($itens, JSON_UNESCAPED_UNICODE);
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt], ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdf]]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 170);
        $pdo = podcast_reconectar($pdo);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?? [];
        $dist = [];
        foreach ((array)($j['itens'] ?? []) as $i) {
            $dist[(int)($i['id'] ?? 0)] = max(0, (int)($i['questoes'] ?? 0));
        }
        $ok = !empty($j['confere']);
        $st = $pdo->prepare('SELECT provas FROM concursos WHERE id = :id');
        $st->execute([':id' => $c]);
        $provas = json_decode((string)$st->fetchColumn(), true) ?: [];
        $provas[(string)$cargoId] = array_merge($provas[(string)$cargoId] ?? [], [
            'status' => $ok ? 'confere' : 'nao_confere', 'motivo' => mb_substr((string)($j['motivo'] ?? ''), 0, 300),
            'ano' => (int)($j['ano'] ?? 0) ?: null, 'banca' => mb_substr((string)($j['banca'] ?? ''), 0, 120),
            'cargo_no_pdf' => mb_substr((string)($j['cargo_no_pdf'] ?? ''), 0, 120), 'total' => (int)($j['total_questoes'] ?? 0), 'itens' => $dist,
        ]);
        $pdo->prepare('UPDATE concursos SET provas = :p WHERE id = :id')->execute([':p' => json_encode($provas, JSON_UNESCAPED_UNICODE), ':id' => $c]);
        if ($ok && array_sum($dist) > 0) {
            // Também vale como "última prova" geral (Hackeando a Prova), somando os cargos já conferidos.
            $soma = [];
            foreach ($provas as $x) {
                if (($x['status'] ?? '') === 'confere') {
                    foreach ((array)($x['itens'] ?? []) as $k => $v) {
                        $soma[$k] = max($soma[$k] ?? 0, (int)$v);
                    }
                }
            }
            $pdo->prepare("UPDATE concursos SET ultima_prova = :u, prova_status = NULL WHERE id = :id")->execute([':u' => json_encode(['encontrada' => true, 'fonte' => 'PDF da prova enviado pelo mentor', 'ano' => $provas[(string)$cargoId]['ano'], 'banca' => $provas[(string)$cargoId]['banca'], 'itens' => $soma, 'observacao' => ''], JSON_UNESCAPED_UNICODE), ':id' => $c]);
        }
        return $ok ? "✅ Prova de $cargo conferida: " . trim(($j['banca'] ?? '') . ' ' . ($j['ano'] ?? '')) . ', ' . array_sum($dist) . ' questões distribuídas.' : "⚠️ A IA acha que este PDF NÃO é a prova de $cargo: " . ($j['motivo'] ?? '');
    }

    // ---------------- Edital verticalizado ----------------
    /** A IA monta o edital verticalizado do cargo: por disciplina, cada assunto com complexidade, peso e custo-benefício. */
    function job_verticalizado(PDO $pdo, int $concursoId, int $cargoId): string
    {
        $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $conc = $st->fetch();
        $itens = hack_itens_do_cargo($pdo, $concursoId, $cargoId);
        if (!$itens) {
            throw new RuntimeException('Confirme o conteúdo programático antes.');
        }
        $provas = job_provas($conc);
        $dist = (array)(($provas[(string)$cargoId]['itens'] ?? null) ?: (json_decode((string)($conc['ultima_prova'] ?? ''), true)['itens'] ?? []));
        $dados = array_map(static fn($i) => ['id' => (int)$i['id'], 'materia' => $i['materia'], 'assunto' => $i['assunto'], 'questoes_ultima_prova' => (int)($dist[(int)$i['id']] ?? ($dist[(string)$i['id']] ?? 0))], $itens);
        $prompt = "Você é o ANTIGÃO, estrategista do Projeto Gabaritando. Monte o EDITAL VERTICALIZADO do cargo com TODOS os assuntos abaixo.\n"
            . "Para cada assunto: complexidade de 1 (muito fácil) a 5 (muito difícil) para quem está começando; peso de 1 a 5 (quanto cai, use as questões da última prova; sem dado, a importância típica na banca); "
            . "custo_beneficio de 1 a 5 (quanto ponto rende por hora de estudo: alto peso + baixa complexidade = 5); horas estimadas de estudo (múltiplo de 0,5); e uma dica curta.\n"
            . "Por disciplina: um resumo de 1 a 2 frases de estratégia. Geral: 4 a 6 orientações de custo-benefício (onde começar, onde não perder tempo, o que garante a nota mínima).\n"
            . "Responda SOMENTE em JSON: {\"orientacoes\":[\"\"],\"disciplinas\":[{\"materia\":\"\",\"estrategia\":\"\",\"assuntos\":[{\"id\":n,\"complexidade\":1,\"peso\":1,\"custo_beneficio\":1,\"horas\":1.5,\"dica\":\"\"}]}]}\n\n"
            . "ASSUNTOS: " . json_encode($dados, JSON_UNESCAPED_UNICODE);
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2]], 170);
        $pdo = podcast_reconectar($pdo);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?? [];
        $porId = [];
        $estr = [];
        foreach ((array)($j['disciplinas'] ?? []) as $d) {
            $estr[(string)($d['materia'] ?? '')] = mb_substr((string)($d['estrategia'] ?? ''), 0, 400);
            foreach ((array)($d['assuntos'] ?? []) as $a) {
                $porId[(int)($a['id'] ?? 0)] = $a;
            }
        }
        if (!$porId) {
            throw new RuntimeException('A IA não montou o verticalizado. Tente de novo.');
        }
        $lim = static fn($v) => max(1, min(5, (int)$v));
        $disc = [];
        foreach ($dados as $d) {
            $a = $porId[$d['id']] ?? [];
            $disc[$d['materia']]['estrategia'] = $estr[$d['materia']] ?? '';
            $disc[$d['materia']]['assuntos'][] = ['id' => $d['id'], 'assunto' => $d['assunto'], 'questoes' => $d['questoes_ultima_prova'],
                'complexidade' => $lim($a['complexidade'] ?? 3), 'peso' => $lim($a['peso'] ?? 3), 'custo_beneficio' => $lim($a['custo_beneficio'] ?? 3),
                'horas' => max(0.5, round(((float)($a['horas'] ?? 2)) * 2) / 2), 'dica' => mb_substr((string)($a['dica'] ?? ''), 0, 300)];
        }
        $st = $pdo->prepare('SELECT verticalizado FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $todos = json_decode((string)$st->fetchColumn(), true) ?: [];
        $todos[(string)$cargoId] = ['gerado_em' => date('Y-m-d H:i'), 'orientacoes' => array_slice(array_map('strval', (array)($j['orientacoes'] ?? [])), 0, 8), 'disciplinas' => $disc];
        $pdo->prepare('UPDATE concursos SET verticalizado = :v WHERE id = :id')->execute([':v' => json_encode($todos, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
        return 'Edital verticalizado pronto: ' . count($disc) . ' disciplinas, ' . count($dados) . ' assuntos.';
    }

    // ---------------- Vídeos que já estão na plataforma ----------------
    /** A IA compara as aulas que já existem (todos os cursos) com os assuntos do edital e sugere ligações. */
    function job_sugerir_existentes(PDO $pdo, int $concursoId, int $lote): string
    {
        $itens = edital_itens($pdo, $concursoId);
        $topicos = array_map('intval', array_column($itens, 'topico_id'));
        $aulas = $pdo->query("SELECT a.id, a.titulo, c.nome AS curso, m.titulo AS modulo FROM admin_curso_aulas a JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                               WHERE (COALESCE(TRIM(a.video_url),'') <> '' OR COALESCE(TRIM(a.pdf_arquivo),'') <> '') ORDER BY a.id LIMIT 80 OFFSET " . (80 * $lote))->fetchAll();
        if (!$aulas) {
            return 'Nenhuma aula para comparar.';
        }
        $lista = array_map(static fn($i) => ['id' => (int)$i['id'], 'assunto' => $i['materia'] . ' › ' . $i['assunto']], $itens);
        $prompt = "Você organiza o Projeto Gabaritando. Para cada AULA abaixo (já gravada na plataforma), diga a qual ASSUNTO DO EDITAL ela serve. Só sugira quando o título deixar claro; se não servir a nenhum, ignore.\n"
            . "Responda SOMENTE em JSON: {\"sugestoes\":[{\"aula_id\":n,\"edital_item_id\":n,\"confianca\":0-100,\"motivo\":\"curto\"}]}\n\n"
            . "ASSUNTOS: " . json_encode($lista, JSON_UNESCAPED_UNICODE) . "\n\nAULAS (curso › módulo › título): " . json_encode(array_map(static fn($a) => ['id' => (int)$a['id'], 'aula' => $a['curso'] . ' › ' . $a['modulo'] . ' › ' . $a['titulo']], $aulas), JSON_UNESCAPED_UNICODE);
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 150);
        $pdo = podcast_reconectar($pdo);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?? [];
        $idsItens = array_map('intval', array_column($itens, 'id'));
        $porAula = [];
        foreach ($aulas as $a) {
            $porAula[(int)$a['id']] = $a;
        }
        $sug = hack_rascunho_get($pdo, $concursoId, 'existentes', []);
        $ja = array_map(static fn($s) => $s['aula_id'] . ':' . $s['item_id'], $sug);
        $n = 0;
        foreach ((array)($j['sugestoes'] ?? []) as $s) {
            $aid = (int)($s['aula_id'] ?? 0);
            $iid = (int)($s['edital_item_id'] ?? 0);
            if (!isset($porAula[$aid]) || !in_array($iid, $idsItens, true) || in_array($aid . ':' . $iid, $ja, true) || (int)($s['confianca'] ?? 0) < 50) {
                continue;
            }
            $sug[] = ['aula_id' => $aid, 'item_id' => $iid, 'aula' => $porAula[$aid]['curso'] . ' › ' . $porAula[$aid]['titulo'], 'confianca' => (int)$s['confianca'], 'motivo' => mb_substr((string)($s['motivo'] ?? ''), 0, 200)];
            $n++;
        }
        hack_rascunho_set($pdo, $concursoId, 'existentes', $sug);
        // Ainda tem aulas? agenda o próximo lote.
        $tot = (int)$pdo->query("SELECT COUNT(*) FROM admin_curso_aulas WHERE COALESCE(TRIM(video_url),'') <> '' OR COALESCE(TRIM(pdf_arquivo),'') <> ''")->fetchColumn();
        if (80 * ($lote + 1) < $tot) {
            job_criar($pdo, $concursoId, 'sugerir_existentes', 'Procurando vídeos já gravados (parte ' . ($lote + 2) . ')', ['lote' => $lote + 1]);
        }
        return $n . ' sugestão(ões) de vídeos já gravados.';
    }

    /** Aceita uma sugestão: a aula passa a valer para o assunto nos cursos dos cargos (copia se for de outro curso). */
    function job_aceitar_existente(PDO $pdo, int $concursoId, int $aulaId, int $itemId): int
    {
        $st = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
        $st->execute([':id' => $aulaId]);
        $a = $st->fetch();
        $it = $pdo->prepare('SELECT * FROM edital_itens WHERE id = :id AND concurso_id = :c');
        $it->execute([':id' => $itemId, ':c' => $concursoId]);
        $item = $it->fetch();
        if (!$a || !$item) {
            return 0;
        }
        $n = 0;
        foreach (hack_cursos_do_item($pdo, $item) as $cursoId) {
            if ((int)$a['curso_id'] === $cursoId) {
                $pdo->prepare('UPDATE admin_curso_aulas SET topico_id = :t, materia_id = :m WHERE id = :id')->execute([':t' => (int)$item['topico_id'], ':m' => (int)$item['materia_id'], ':id' => $aulaId]);
                $n++;
                continue;
            }
            $dup = $pdo->prepare("SELECT id FROM admin_curso_aulas WHERE curso_id = :c AND topico_id = :t AND COALESCE(video_url,'') = :v AND COALESCE(pdf_arquivo,'') = :p LIMIT 1");
            $dup->execute([':c' => $cursoId, ':t' => (int)$item['topico_id'], ':v' => (string)$a['video_url'], ':p' => (string)$a['pdf_arquivo']]);
            if ($dup->fetchColumn()) {
                continue;
            }
            // Cria no módulo da matéria do curso do cargo, levando apostila, vídeo e PDF (sem gastar IA de novo).
            $ids = edital_criar_aula($pdo, $itemId, array_values(array_filter(array_map('intval', explode(',', (string)$item['cargos'])))) ?: job_cargos_do_curso($pdo, $concursoId, $cursoId), (string)$a['titulo'], (string)$a['video_url']);
            foreach ($ids as $novo) {
                $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d, pdf_arquivo = :p WHERE id = :id AND curso_id = :c')->execute([':d' => $a['descricao'], ':p' => $a['pdf_arquivo'], ':id' => $novo, ':c' => $cursoId]);
            }
            $pdo->prepare("DELETE FROM ia_fila WHERE tipo = 'apostila' AND aula_id IN (" . implode(',', array_map('intval', $ids ?: [0])) . ')')->execute();
            $n++;
        }
        return $n;
    }

    function job_cargos_do_curso(PDO $pdo, int $concursoId, int $cursoId): array
    {
        $st = $pdo->prepare('SELECT id FROM concurso_cargos WHERE concurso_id = :c AND curso_id = :cu');
        $st->execute([':c' => $concursoId, ':cu' => $cursoId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    // ---------------- Acompanhamento (painel ao vivo) ----------------
    function job_painel(PDO $pdo, int $concursoId): array
    {
        job_bootstrap($pdo);
        $st = $pdo->prepare("SELECT id, tipo, rotulo, status, mensagem, atualizado_em FROM edital_jobs WHERE concurso_id = :c AND (status IN ('pendente','rodando') OR atualizado_em > NOW() - INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 40");
        $st->execute([':c' => $concursoId]);
        $jobs = $st->fetchAll();
        // Questões e aulas do Antigão (fila do robô) deste concurso.
        $itens = array_map('intval', array_column(edital_itens($pdo, $concursoId), 'id')) ?: [0];
        $q = $pdo->query("SELECT status, COUNT(*) n FROM ia_fila WHERE tipo = 'questoes' AND aula_id IN (" . implode(',', $itens) . ') GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        $qs = hack_questoes_situacao($pdo, $concursoId);
        // Aulas do Antigão, videoaulas e podcasts das aulas dos cursos deste concurso.
        $cursos = $pdo->prepare('SELECT DISTINCT curso_id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
        $cursos->execute([':c' => $concursoId]);
        $cIds = array_map('intval', $cursos->fetchAll(PDO::FETCH_COLUMN)) ?: [0];
        $porTipo = [];
        foreach ($pdo->query("SELECT f.tipo, f.status, COUNT(*) n FROM ia_fila f JOIN admin_curso_aulas a ON a.id = f.aula_id
                               WHERE f.tipo IN ('aula_ia','apostila','videoaula','podcast') AND a.curso_id IN (" . implode(',', $cIds) . ") GROUP BY f.tipo, f.status") as $r) {
            $porTipo[$r['tipo']][$r['status']] = (int)$r['n'];
        }
        return [
            'fila' => $porTipo,
            'jobs' => $jobs,
            'ativos' => count(array_filter($jobs, static fn($j) => in_array($j['status'], ['pendente', 'rodando'], true))),
            'questoes' => ['assuntos_ok' => $qs['ok'], 'assuntos_total' => $qs['total'], 'na_fila' => (int)($q['pendente'] ?? 0), 'feitos' => (int)($q['feito'] ?? 0), 'erros' => (int)($q['erro'] ?? 0),
                'total_questoes' => array_sum(array_column($qs['itens'], 'questoes'))],
        ];
    }
}
