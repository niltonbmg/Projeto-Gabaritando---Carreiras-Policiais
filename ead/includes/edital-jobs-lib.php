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
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
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
        require_once __DIR__ . '/ia-consumo-lib.php';
        if (!ia_controle_ligado('edital')) {
            return null; // desligado no Controle rígido
        }
        job_bootstrap($pdo);
        // Tarefa "rodando" há mais de 6 min travou: volta para a fila (até 3 tentativas).
        $pdo->exec("UPDATE edital_jobs SET status = IF(tentativas >= 3, 'erro', 'pendente'), mensagem = IF(tentativas >= 3, 'A IA não respondeu a tempo. Tente de novo.', mensagem)
                    WHERE status = 'rodando' AND atualizado_em < NOW() - INTERVAL 6 MINUTE");
        if (function_exists('ia_fila_pausa_texto') && ia_fila_pausa_texto()) {
            return null; // cota do Google: espera
        }
        $pdo->exec("UPDATE edital_jobs SET status = 'pendente', mensagem = 'Aguardando a cota do Google renovar.' WHERE status = 'erro' AND mensagem LIKE '%Limite di%rio%'");
        $semBusca = function_exists('ia_fila_pausa_busca') && ia_fila_pausa_busca() ? " AND tipo NOT IN ('buscar_prova')" : '';
        $sql = "SELECT * FROM edital_jobs WHERE status = 'pendente'$semBusca" . ($concursoId ? ' AND concurso_id = ' . (int)$concursoId : '') . ' ORDER BY id LIMIT 1';
        $job = $pdo->query($sql)->fetch();
        if (!$job) {
            return null;
        }
        job_agora_gravar((string)$job['rotulo']);
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
            if (gemini_cota_pausar($e, $job['tipo'] === 'buscar_prova' ? 'busca' : 'texto')) {
                // Cota do Google, não erro da tarefa: volta para a fila e espera.
                $pdo->prepare("UPDATE edital_jobs SET status = 'pendente', tentativas = GREATEST(tentativas - 1, 0), mensagem = :m WHERE id = :id")->execute([':m' => 'Aguardando a cota do Google renovar.', ':id' => (int)$job['id']]);
                $job['status'] = 'pendente';
                $job['mensagem'] = 'Aguardando a cota do Google renovar.';
                return $job;
            }
            $pdo->prepare("UPDATE edital_jobs SET status = 'erro', mensagem = :m WHERE id = :id")->execute([':m' => mb_substr($e->getMessage(), 0, 600), ':id' => (int)$job['id']]);
            $job['status'] = 'erro';
            $job['mensagem'] = $e->getMessage();
        }
        return $job;
    }

    function job_executar(PDO $pdo, array $job): string
    {
        require_once __DIR__ . '/ia-consumo-lib.php';
        ia_consumo_inicio('edital', (int)$job['concurso_id']);
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
                $antes = json_decode((string)($conc['edital_info'] ?? ''), true) ?: [];
                if (!empty($antes['provas_escritas_conferido'])) { // o que o mentor já conferiu não é sobrescrito
                    $info['provas_escritas'] = $antes['provas_escritas'];
                    $info['provas_escritas_conferido'] = true;
                } elseif (!empty($info['provas_escritas'])) {
                    require_once __DIR__ . '/provas-escritas-lib.php';
                    $info['provas_escritas'] = array_map(static fn($x) => ['cargo' => (string)($x['cargo'] ?? '')] + pe_normalizar((array)$x), (array)$info['provas_escritas']);
                    $info['provas_escritas_conferido'] = false;
                }
                podcast_reconectar($pdo)->prepare('UPDATE concursos SET edital_info = :i WHERE id = :id')->execute([':i' => json_encode($info, JSON_UNESCAPED_UNICODE), ':id' => $c]);
                return 'Informações do edital lidas.';
            case 'conferir_conteudo':
                return job_conferir_conteudo($pdo, $c, (string)($p['texto'] ?? ''));
            case 'parecidas_ia':
                return job_parecidas_ia($pdo, $c);
            case 'ler_escritas':
                require_once __DIR__ . '/provas-escritas-lib.php';
                $lista = pe_ia_ler((string)($p['texto'] ?? ''), hack_pdf_base64($conc['edital_pdf'] ?? ''), $cargosNomes());
                pe_salvar(podcast_reconectar($pdo), $c, $lista, false);
                return 'Redação e discursiva identificadas para ' . count($lista) . ' cargo(s). Confira no passo "Provas escritas".';
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
                if (!empty($p['planilha'])) { // planilha: lotes de 30 linhas por pedido à IA (só separa por assunto)
                    $linhas = hack_planilha_linhas(dirname(__DIR__) . '/' . $p['planilha'], (string)$p['planilha']);
                    $n = 0;
                    foreach (array_chunk($linhas, 30) as $lote) {
                        $n += hack_questoes_enviadas(podcast_reconectar($pdo), $c, implode("\n", $lote), '');
                    }
                    return $n . ' questão(ões) da planilha separadas por assunto. Estão EM REVISÃO: aprove em Revisão de questões.';
                }
                $n = hack_questoes_enviadas($pdo, $c, (string)($p['texto'] ?? ''), hack_pdf_base64((string)($p['arquivo'] ?? '')));
                return $n . ' questão(ões) separadas por assunto. Estão EM REVISÃO: aprove em Revisão de questões.';
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
            $n += job_clonar_aula($pdo, $a, $itemId, $cursoId, $concursoId) ? 1 : 0;
        }
        return $n;
    }

    /**
     * Copia uma aula pronta para o curso de um cargo, no assunto do edital: vídeo, PDF, apostila,
     * videoaula e podcast já gravados vão junto (sem gastar IA). Não duplica. Retorna o id novo ou 0.
     */
    function job_clonar_aula(PDO $pdo, array $a, int $itemId, int $cursoId, int $concursoId): int
    {
        $item = $pdo->prepare('SELECT * FROM edital_itens WHERE id = :id');
        $item->execute([':id' => $itemId]);
        $item = $item->fetch();
        $dup = $pdo->prepare("SELECT id FROM admin_curso_aulas WHERE curso_id = :c AND topico_id = :t AND titulo = :ti AND COALESCE(video_url,'') = :v AND COALESCE(pdf_arquivo,'') = :p LIMIT 1");
        $dup->execute([':c' => $cursoId, ':t' => (int)$item['topico_id'], ':ti' => (string)$a['titulo'], ':v' => (string)$a['video_url'], ':p' => (string)$a['pdf_arquivo']]);
        if ($dup->fetchColumn()) {
            return 0;
        }
        $cargos = job_cargos_do_curso($pdo, $concursoId, $cursoId);
        if (!$cargos) {
            return 0;
        }
        // Só o curso deste cargo (um cargo basta para achar o curso e o módulo da matéria).
        $ids = edital_criar_aula($pdo, $itemId, [$cargos[0]], (string)$a['titulo'], (string)$a['video_url']);
        $novo = (int)($ids[0] ?? 0);
        if (!$novo) {
            return 0;
        }
        $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d, pdf_arquivo = :p, pdf_nome = :pn, duracao = :du, pdf_duracao = :pd WHERE id = :id')
            ->execute([':d' => $a['descricao'], ':p' => $a['pdf_arquivo'], ':pn' => $a['pdf_nome'], ':du' => $a['duracao'], ':pd' => $a['pdf_duracao'], ':id' => $novo]);
        try { $pdo->prepare('INSERT IGNORE INTO aula_videoaulas (aula_id, status, roteiro, partes, partes_ok, audios, erro) SELECT :n, status, roteiro, partes, partes_ok, audios, erro FROM aula_videoaulas WHERE aula_id = :o')->execute([':n' => $novo, ':o' => (int)$a['id']]); } catch (Throwable $e) {}
        try { $pdo->prepare('INSERT IGNORE INTO aula_podcasts (aula_id, status, roteiro, partes, partes_ok, arquivo, duracao_seg, erro) SELECT :n, status, roteiro, partes, partes_ok, arquivo, duracao_seg, erro FROM aula_podcasts WHERE aula_id = :o')->execute([':n' => $novo, ':o' => (int)$a['id']]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM ia_fila WHERE aula_id = :id AND tipo IN ('apostila','aula_ia')")->execute([':id' => $novo]);
        // O que a original ainda tem na fila, a cópia também tem.
        $fila = $pdo->prepare("SELECT tipo FROM ia_fila WHERE aula_id = :o AND status <> 'feito' AND tipo IN ('videoaula','podcast','aula_ia','apostila')");
        $fila->execute([':o' => (int)$a['id']]);
        foreach ($fila->fetchAll(PDO::FETCH_COLUMN) as $t) {
            ia_fila_adicionar($pdo, $t, $novo);
        }
        if (!$fila->rowCount() && trim((string)$a['descricao']) === '') {
            ia_fila_adicionar($pdo, trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '' ? 'apostila' : 'aula_ia', $novo);
        }
        return $novo;
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
        foreach ($pdo->query("SELECT f.tipo, IF(f.status = 'erro' AND f.erro LIKE 'Aguardando%', 'revisao', f.status) status, COUNT(*) n FROM ia_fila f JOIN admin_curso_aulas a ON a.id = f.aula_id
                               WHERE f.tipo IN ('aula_ia','apostila','videoaula','podcast') AND a.curso_id IN (" . implode(',', $cIds) . ") GROUP BY f.tipo, 2") as $r) {
            $porTipo[$r['tipo']][$r['status']] = (int)$r['n'];
        }
        // Últimos itens prontos (para o mentor pré-visualizar na hora).
        $recentes = [];
        foreach ($pdo->query("SELECT f.*, a.titulo FROM ia_fila f JOIN admin_curso_aulas a ON a.id = f.aula_id
                               WHERE f.status = 'feito' AND f.tipo IN ('aula_ia','apostila','videoaula') AND a.curso_id IN (" . implode(',', $cIds) . ") ORDER BY f.atualizado_em DESC LIMIT 6") as $r) {
            $recentes[] = ['tipo' => $r['tipo'], 'pv' => 'aula', 'id' => (int)$r['aula_id'], 'titulo' => (string)$r['titulo'], 'consumo' => (string)($r['consumo'] ?? '')];
        }
        foreach ($pdo->query("SELECT f.*, e.topico_id, t.titulo FROM ia_fila f JOIN edital_itens e ON e.id = f.aula_id JOIN admin_materia_topicos t ON t.id = e.topico_id
                               WHERE f.status = 'feito' AND f.tipo = 'questoes' AND e.concurso_id = " . (int)$concursoId . ' ORDER BY f.atualizado_em DESC LIMIT 4') as $r) {
            $recentes[] = ['tipo' => 'questoes', 'pv' => 'questoes', 'id' => (int)$r['topico_id'], 'titulo' => (string)$r['titulo'], 'consumo' => (string)($r['consumo'] ?? '')];
        }
        return [
            'recentes' => $recentes,
            'consumo_mes' => (static function () use ($pdo): array {
                require_once __DIR__ . '/ia-consumo-lib.php';
                $c = ia_consumo_config();
                return ['brl' => ia_consumo_mes_brl($pdo), 'limite' => (float)$c['limite_mensal_brl'], 'gratis' => $c['plano'] !== 'pago'];
            })(),
            'agora' => job_agora_ler(),
            'uso' => podcast_uso_resumo(),
            'plano_pago' => function_exists('ia_plano_pago') && ia_plano_pago(),
            'prioridade' => ia_prioridade(),
            'pausa_voz' => ia_fila_pausa() ? date('H:i', ia_fila_pausa()) : '',
            'pausa_texto' => ia_fila_pausa_texto() ? date('H:i', ia_fila_pausa_texto()) : '',
            'pausa_busca' => ia_fila_pausa_busca() ? date('d/m H:i', ia_fila_pausa_busca()) : '',
            'fila' => $porTipo,
            'jobs' => $jobs,
            'ativos' => count(array_filter($jobs, static fn($j) => in_array($j['status'], ['pendente', 'rodando'], true))),
            'questoes' => ['assuntos_ok' => $qs['ok'], 'assuntos_total' => $qs['total'], 'na_fila' => (int)($q['pendente'] ?? 0), 'feitos' => (int)($q['feito'] ?? 0), 'erros' => (int)($q['erro'] ?? 0),
                'total_questoes' => array_sum(array_column($qs['itens'], 'questoes'))],
        ];
    }

    // ---------------- Curso novo a partir de cursos prontos ----------------
    /** Copia matérias (módulos com as aulas, apostilas, vídeos e PDFs) de cursos prontos para o curso do cargo. */
    function job_copiar_modulos(PDO $pdo, int $cursoDestino, array $moduloIds): array
    {
        $colsM = array_values(array_diff($pdo->query('SHOW COLUMNS FROM admin_curso_modulos')->fetchAll(PDO::FETCH_COLUMN), ['id', 'curso_id', 'criado_em', 'atualizado_em']));
        $colsA = array_values(array_diff($pdo->query('SHOW COLUMNS FROM admin_curso_aulas')->fetchAll(PDO::FETCH_COLUMN), ['id', 'curso_id', 'modulo_id', 'criado_em', 'atualizado_em']));
        $lm = implode(',', array_map(static fn($c) => "`$c`", $colsM));
        $la = implode(',', array_map(static fn($c) => "`$c`", $colsA));
        $nm = 0;
        $na = 0;
        foreach (array_unique(array_map('intval', $moduloIds)) as $mid) {
            $st = $pdo->prepare('SELECT curso_id FROM admin_curso_modulos WHERE id = :id');
            $st->execute([':id' => $mid]);
            $origem = (int)$st->fetchColumn();
            if (!$origem || $origem === $cursoDestino) {
                continue;
            }
            $pdo->prepare("INSERT INTO admin_curso_modulos (curso_id, $lm, criado_em) SELECT :d, $lm, NOW() FROM admin_curso_modulos WHERE id = :id")->execute([':d' => $cursoDestino, ':id' => $mid]);
            $novo = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO admin_curso_aulas (curso_id, modulo_id, $la, criado_em) SELECT :d, :m, $la, NOW() FROM admin_curso_aulas WHERE modulo_id = :o");
            $ins->execute([':d' => $cursoDestino, ':m' => $novo, ':o' => $mid]);
            $na += $ins->rowCount();
            $nm++;
        }
        return [$nm, $na];
    }

    /** Cursos prontos (com aulas) e suas matérias, para escolher o que copiar. */
    function job_cursos_prontos(PDO $pdo, int $exceto = 0): array
    {
        $r = [];
        foreach ($pdo->query('SELECT m.id, m.titulo, m.curso_id, c.nome AS curso, (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.modulo_id = m.id) AS aulas
                               FROM admin_curso_modulos m JOIN admin_cursos c ON c.id = m.curso_id ORDER BY c.nome, m.ordem, m.id') as $m) {
            if ((int)$m['curso_id'] === $exceto || (int)$m['aulas'] === 0) {
                continue;
            }
            $r[$m['curso']][] = $m;
        }
        return $r;
    }

    // ---------------- "O que a IA está fazendo agora" ----------------
    function job_agora_gravar(string $texto): void
    {
        @file_put_contents(dirname(__DIR__) . '/uploads/.ia-agora.json', json_encode(['texto' => $texto, 'em' => time()], JSON_UNESCAPED_UNICODE));
    }

    function job_agora_ler(): string
    {
        $j = json_decode((string)@file_get_contents(dirname(__DIR__) . '/uploads/.ia-agora.json'), true);
        return ($j && ($j['em'] ?? 0) > time() - 360) ? (string)$j['texto'] : '';
    }

    // ---------------- Aproveitar aulas entre cargos ----------------
    /**
     * Para o cargo de destino: em cada assunto do edital que ele não tem aula e que outro cargo do
     * concurso já tem, copia a aula (vídeo, PDF, apostila, videoaula e podcast prontos). Retorna quantas.
     */
    function job_aproveitar_entre_cargos(PDO $pdo, int $concursoId, int $cargoDestino, bool $soContar = false, ?array $andamento = null): int
    {
        $destino = null;
        foreach ($andamento ?? edital_andamento($pdo, $concursoId) as $an) {
            if ((int)$an['cargo']['id'] === $cargoDestino) { $destino = $an; }
        }
        if (!$destino || empty($destino['cargo']['curso_id'])) {
            return 0;
        }
        $cursoDestino = (int)$destino['cargo']['curso_id'];
        $faltam = [];
        foreach ($destino['itens'] as $it) {
            if (!$it['aulas']) { $faltam[(int)$it['topico_id']] = (int)$it['id']; }
        }
        if (!$faltam) {
            return 0;
        }
        // De QUALQUER curso da plataforma, por assunto: escolhe o curso com aula do professor (senão o primeiro)
        // e traz TODAS as aulas daquele assunto nesse curso (as partes 1, 2, 3... juntas, na ordem).
        $porTopico = [];
        foreach ($pdo->query('SELECT * FROM admin_curso_aulas WHERE topico_id IN (' . implode(',', array_keys($faltam)) . ') AND curso_id <> ' . $cursoDestino . ' ORDER BY ordem, id') as $a) {
            $porTopico[(int)$a['topico_id']][(int)$a['curso_id']][] = $a;
        }
        $n = 0;
        foreach ($faltam as $topico => $itemId) {
            if (empty($porTopico[$topico])) { continue; }
            $melhor = null;
            foreach ($porTopico[$topico] as $lista) {
                $prof = (bool)array_filter($lista, static fn($x) => trim((string)$x['video_url']) !== '' || trim((string)$x['pdf_arquivo']) !== '');
                if ($melhor === null || ($prof && !$melhor['prof'])) { $melhor = ['prof' => $prof, 'aulas' => $lista]; }
            }
            $n++;
            if ($soContar) { continue; }
            foreach ($melhor['aulas'] as $a) {
                job_clonar_aula($pdo, $a, $itemId, $cursoDestino, $concursoId);
            }
        }
        return $n;
    }

    /**
     * A IA procura, PELO ASSUNTO, aulas prontas de outros cursos (vídeo ou PDF do professor) que sirvam aos assuntos
     * do edital que ainda não têm aula. Ela entende o conteúdo (ex.: "Física: Leis de Newton" serve para
     * "Ciências Naturais: dinâmica"), não só o nome igual. 1 pedido de texto por cargo. O mentor confirma antes de copiar.
     */
    function job_conferir_conteudo(PDO $pdo, int $concursoId, string $texto = ''): string
    {
        $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $conc = $st->fetch();
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll(PDO::FETCH_KEY_PAIR);
        $atual = [];
        foreach (edital_itens($pdo, $concursoId) as $it) {
            $atual[$it['materia']][] = $it['assunto'];
        }
        $pdf = hack_pdf_base64($conc['edital_pdf'] ?? '');
        if ($pdf === '' && trim($texto) === '') {
            throw new RuntimeException('Não há PDF do edital guardado. Envie o PDF no passo Edital.');
        }
        $prompt = "Você confere o CONTEÚDO PROGRAMÁTICO de um edital de concurso policial/militar para o Projeto Gabaritando.\n"
            . "Abaixo está o que JÁ FOI CADASTRADO (matéria → assuntos). Compare com o edital (PDF/texto) item por item, em TODAS as matérias e anexos, e liste SOMENTE os assuntos do edital que FALTAM no cadastro "
            . "(itens e subitens que não estão lá nem com outras palavras). Também liste matérias inteiras que faltam. Copie o texto do item como está no edital, sem numeração. Não invente.\n"
            . "Cargos: " . json_encode(array_values($cargos), JSON_UNESCAPED_UNICODE) . ". Em \"cargos\", os nomes exatos quando a matéria for só de alguns cargos; senão [\"todos\"].\n"
            . "Responda SOMENTE em JSON: {\"faltando\":[{\"materia\":\"nome da matéria (igual ao cadastro quando já existir)\",\"cargos\":[\"todos\"],\"assuntos\":[\"...\"]}],\"observacao\":\"1 frase\"}\n\n"
            . "JÁ CADASTRADO: " . json_encode($atual, JSON_UNESCAPED_UNICODE) . "\n\n" . (trim($texto) !== '' ? "EDITAL (texto):\n" . mb_substr($texto, 0, 120000) : '');
        $partes = [['text' => $prompt]];
        if ($pdf !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdf]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1, 'maxOutputTokens' => 32000]], 170);
        $pdo = podcast_reconectar($pdo);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?? [];
        $ja = [];
        foreach ($atual as $m => $as) {
            foreach ($as as $a) {
                $ja[edital_normalizar($m) . '|' . edital_normalizar($a)] = true;
            }
        }
        $lista = [];
        foreach ((array)($j['faltando'] ?? []) as $m) {
            $mat = trim((string)($m['materia'] ?? ''));
            foreach ((array)($m['assuntos'] ?? []) as $a) {
                $a = trim((string)$a);
                if ($mat === '' || $a === '' || isset($ja[edital_normalizar($mat) . '|' . edital_normalizar($a)])) {
                    continue;
                }
                $lista[] = ['materia' => mb_substr($mat, 0, 180), 'cargos' => array_values(array_map('strval', (array)($m['cargos'] ?? ['todos']))), 'assunto' => mb_substr($a, 0, 180)];
            }
        }
        hack_rascunho_set($pdo, $concursoId, 'faltando', $lista);
        return $lista ? 'A IA encontrou ' . count($lista) . ' assunto(s) do edital que faltavam no cadastro. Confira no passo Edital.' : 'A IA conferiu: nenhum assunto do edital faltando.';
    }

    /**
     * SEM IA: o mentor cola o conteúdo programático de UM cargo (do anexo do edital) e a plataforma compara com o cadastro.
     * Sugere: assunto novo (não existe) ou "ligar" (o assunto existe no concurso, mas não está ligado a este cargo).
     * Os subtópicos ("1.1.") viram assuntos próprios ("Constituição Federal › Dos princípios fundamentais").
     */
    function job_comparar_cargo(PDO $pdo, int $concursoId, int $cargoId, string $texto): array
    {
        require_once __DIR__ . '/padrao-lib.php';
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE id = :id AND concurso_id = :c');
        $cg->execute([':id' => $cargoId, ':c' => $concursoId]);
        $cargo = $cg->fetch(PDO::FETCH_ASSOC);
        if (!$cargo) {
            throw new RuntimeException('Escolha o cargo.');
        }
        $lido = edital_parse_texto($texto);
        if (!$lido) {
            throw new RuntimeException('Não reconheci matérias no texto. Cole a partir do nome da matéria em maiúsculas seguido de dois-pontos (ex.: "LÍNGUA PORTUGUESA:").');
        }
        $chave = static fn(string $t): string => edital_normalizar(preg_replace('~\(.*?\)~u', '', $t));
        $cad = []; // matéria normalizada -> itens
        foreach (edital_itens($pdo, $concursoId) as $it) {
            $cad[edital_normalizar((string)$it['materia'])][] = $it;
        }
        // Comparação rigorosa: "Pai › filho" só bate com o MESMO filho (e pai parecido); palavra solta em comum não basta.
        $palavras = static function (string $t) use ($chave): array {
            $t = preg_replace('~\b(dos|das|do|da|de|e|a|o|os|as|em|no|na|para|com|sobre)\b~', ' ', $chave($t));
            return array_values(array_unique(array_filter(explode(' ', $t), static fn($p) => strlen($p) >= 4)));
        };
        $parecido = static function (string $a, string $b) use ($chave, $palavras): bool {
            if ($chave($a) === '' || $chave($b) === '') {
                return false;
            }
            if ($chave($a) === $chave($b)) {
                return true;
            }
            $x = $palavras($a);
            $y = $palavras($b);
            $c = count(array_intersect($x, $y));
            return $x && $y && $c >= 2 && $c / max(count($x), count($y)) >= 0.75;
        };
        $partes = static function (string $t): array {
            $p = array_map('trim', explode('›', $t));
            return count($p) > 1 ? [implode(' › ', array_slice($p, 0, -1)), end($p)] : ['', $t];
        };
        $mesmo = static function (string $a, string $b) use ($parecido, $partes): bool {
            [$pa, $fa] = $partes($a);
            [$pb, $fb] = $partes($b);
            if ($pa !== '' || $pb !== '') {
                // subtópico: o filho tem de bater; se os dois têm pai, o pai também
                return $parecido($fa, $fb) && ($pa === '' || $pb === '' || $parecido($pa, $pb));
            }
            return $parecido($a, $b);
        };
        $lista = [];
        $total = 0;
        $ordemEdital = []; // ordem do edital colado: id do assunto existente ou "n:matéria|assunto" do novo
        foreach ($lido as $m) {
            $doCad = $cad[edital_normalizar($m['materia'])] ?? [];
            foreach ($m['assuntos'] as $a) {
                $total++;
                $achou = null;
                [$paiA] = $partes($a);
                if ($paiA !== '') { // o item "pai" cadastrado sozinho (ex.: "Constituição do Estado da Bahia") fica logo antes dos subtópicos dele
                    foreach ($doCad as $it) {
                        if (!str_contains((string)$it['assunto'], '›') && $parecido($paiA, (string)$it['assunto']) && !in_array((int)$it['id'], $ordemEdital, true)) {
                            $ordemEdital[] = (int)$it['id'];
                        }
                    }
                }
                foreach ($doCad as $it) {
                    if ($mesmo($a, (string)$it['assunto'])) {
                        $achou = $it;
                        break;
                    }
                }
                if ($achou === null) {
                    $lista[] = ['materia' => $m['materia'], 'cargos' => [$cargo['nome']], 'assunto' => $a, 'tipo' => 'novo'];
                    $ordemEdital[] = 'n:' . edital_normalizar($m['materia']) . '|' . edital_normalizar($a);
                    continue;
                }
                $ordemEdital[] = (int)$achou['id'];
                $ids = array_filter(array_map('intval', explode(',', (string)$achou['cargos'])));
                if ($ids && !in_array((int)$cargo['id'], $ids, true)) {
                    $lista[] = ['materia' => (string)$achou['materia'], 'cargos' => [$cargo['nome']], 'assunto' => (string)$achou['assunto'], 'tipo' => 'ligar', 'item_id' => (int)$achou['id'], 'cargo_id' => (int)$cargo['id']];
                }
            }
        }
        $antes = array_values(array_filter((array)hack_rascunho_get($pdo, $concursoId, 'faltando', []), static fn($x) => !in_array($cargo['nome'], (array)($x['cargos'] ?? []), true)));
        hack_rascunho_set($pdo, $concursoId, 'faltando', array_merge($antes, $lista) ?: null);
        hack_rascunho_set($pdo, $concursoId, 'ordem_edital', $ordemEdital);
        job_aplicar_ordem_edital($pdo, $concursoId); // o que já existe já fica na ordem do edital
        return ['lidos' => $total, 'materias' => count($lido), 'novos' => count(array_filter($lista, static fn($x) => $x['tipo'] === 'novo')), 'ligar' => count(array_filter($lista, static fn($x) => $x['tipo'] === 'ligar'))];
    }

    // ---------------- Mapa do edital (o caminho simples) ----------------
    /**
     * Prévia do mapa a partir dos anexos colados (um por cargo), sem IA e sem gravar nada no edital.
     * Retorna por cargo: matérias e assuntos na ordem, e quantos reaproveitam o que já existe.
     */
    function mapa_previa(PDO $pdo, int $concursoId, array $textos): array
    {
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll(PDO::FETCH_KEY_PAIR);
        $atuais = edital_itens($pdo, $concursoId);
        $saida = [];
        foreach ($cargos as $cid => $nome) {
            $txt = trim((string)($textos[$cid] ?? ''));
            if ($txt === '') {
                continue;
            }
            $lido = edital_parse_texto($txt);
            $mats = [];
            $nAss = 0;
            $nReap = 0;
            foreach ($lido as $m) {
                $lista = [];
                foreach ($m['assuntos'] as $a) {
                    $reap = false;
                    foreach ($atuais as $it) {
                        if (edital_normalizar((string)$it['materia']) === edital_normalizar($m['materia']) && edital_mesmo_assunto($a, (string)$it['assunto'])) {
                            $reap = true;
                            break;
                        }
                    }
                    $lista[] = ['assunto' => $a, 'reaproveita' => $reap];
                    $nAss++;
                    $nReap += $reap ? 1 : 0;
                }
                $mats[] = ['materia' => $m['materia'], 'assuntos' => $lista];
            }
            $saida[(int)$cid] = ['cargo' => (string)$nome, 'materias' => $mats, 'n_materias' => count($mats), 'n_assuntos' => $nAss, 'n_reaproveita' => $nReap];
        }
        return $saida;
    }

    /**
     * Usa o mapa: o edital do concurso passa a ser EXATAMENTE os anexos colados (por cargo, na ordem do edital).
     * Reaproveita assuntos iguais (aulas, questões e progresso continuam ligados); assunto antigo que saiu do edital
     * passa as aulas e questões dele para o assunto novo mais parecido (ex.: "Constituição da República…" →
     * "Constituição Federal › Dos princípios fundamentais"); se não houver, as aulas ficam no curso como estão.
     * Guarda uma cópia do mapa anterior para desfazer.
     */
    function mapa_usar(PDO $pdo, int $concursoId): array
    {
        $textos = (array)hack_rascunho_get($pdo, $concursoId, 'mapa_textos', []);
        $cg = $pdo->prepare('SELECT id, nome, curso_id FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll(PDO::FETCH_ASSOC);
        $idsCargos = array_map(static fn($c) => (int)$c['id'], $cargos);
        $semTexto = array_filter($cargos, static fn($c) => trim((string)($textos[(int)$c['id']] ?? '')) === '');
        if ($semTexto) {
            throw new RuntimeException('Cole o anexo de todos os cargos antes (falta: ' . implode(', ', array_column($semTexto, 'nome')) . ').');
        }
        $antes = edital_itens($pdo, $concursoId);
        hack_rascunho_set($pdo, $concursoId, 'mapa_backup', array_map(static fn($i) => ['id' => (int)$i['id'], 'materia_id' => (int)$i['materia_id'], 'topico_id' => (int)$i['topico_id'], 'cargos' => (string)$i['cargos'], 'ordem' => (int)$i['ordem']], $antes));

        // 1) sequência única (primeiro cargo inteiro; os assuntos só do outro cargo entram depois do vizinho de cima)
        $seq = [];   // chave => ['materia','assunto','cargos'=>[ids]]
        $ordem = []; // chaves na ordem
        foreach ($cargos as $c) {
            $ultimo = null;
            foreach (edital_parse_texto((string)$textos[(int)$c['id']]) as $m) {
                foreach ($m['assuntos'] as $a) {
                    $achou = null;
                    foreach ($seq as $k => $x) {
                        if (edital_normalizar($x['materia']) === edital_normalizar($m['materia']) && edital_mesmo_assunto($a, $x['assunto'])) {
                            $achou = $k;
                            break;
                        }
                    }
                    if ($achou === null) {
                        $achou = 'k' . count($seq);
                        $seq[$achou] = ['materia' => $m['materia'], 'assunto' => $a, 'cargos' => []];
                        $pos = $ultimo === null ? false : array_search($ultimo, $ordem, true);
                        if ($pos === false) {
                            // matéria nova para a sequência: entra no fim (ou depois da mesma matéria, se já houver)
                            $fimMat = null;
                            foreach ($ordem as $i => $k2) {
                                if (edital_normalizar($seq[$k2]['materia']) === edital_normalizar($m['materia'])) {
                                    $fimMat = $i;
                                }
                            }
                            $fimMat === null ? $ordem[] = $achou : array_splice($ordem, $fimMat + 1, 0, [$achou]);
                        } else {
                            array_splice($ordem, $pos + 1, 0, [$achou]);
                        }
                    }
                    $seq[$achou]['cargos'][] = (int)$c['id'];
                    $ultimo = $achou;
                }
            }
        }

        // 2) cada assunto do mapa: reaproveita o item igual do concurso; senão cria (reaproveitando matéria/assunto já cadastrados)
        $idsNaOrdem = [];
        $usados = [];
        $novos = 0;
        $reap = 0;
        $insMat = $pdo->prepare("INSERT INTO admin_materias (nome, status, ordem) VALUES (:n, 'ativo', 0)");
        foreach ($ordem as $k) {
            $x = $seq[$k];
            $cargosTxt = count(array_unique($x['cargos'])) >= count($idsCargos) ? '' : implode(',', array_unique($x['cargos']));
            $item = null;
            foreach ($antes as $it) {
                if (!isset($usados[(int)$it['id']]) && edital_normalizar((string)$it['materia']) === edital_normalizar($x['materia']) && edital_mesmo_assunto($x['assunto'], (string)$it['assunto'])) {
                    $item = $it;
                    break;
                }
            }
            if ($item) {
                $pdo->prepare('UPDATE edital_itens SET cargos = :g WHERE id = :id')->execute([':g' => $cargosTxt, ':id' => (int)$item['id']]);
                $usados[(int)$item['id']] = true;
                $idsNaOrdem[] = (int)$item['id'];
                $reap++;
                continue;
            }
            $sm = $pdo->prepare('SELECT id, nome FROM admin_materias');
            $sm->execute();
            $mid = 0;
            foreach ($sm->fetchAll(PDO::FETCH_ASSOC) as $mm) {
                if (trim(edital_normalizar((string)$mm['nome'])) === trim(edital_normalizar($x['materia']))) { $mid = (int)$mm['id']; break; }
            }
            if (!$mid) { $insMat->execute([':n' => mb_substr($x['materia'], 0, 180)]); $mid = (int)$pdo->lastInsertId(); }
            $st = $pdo->prepare('SELECT id, titulo FROM admin_materia_topicos WHERE materia_id = :m');
            $st->execute([':m' => $mid]);
            $tid = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
                if (trim(edital_normalizar((string)$t['titulo'])) === trim(edital_normalizar($x['assunto']))) { $tid = (int)$t['id']; break; }
            }
            if (!$tid) {
                $pdo->prepare("INSERT INTO admin_materia_topicos (materia_id, titulo, status, ordem) VALUES (:m, :t, 'ativo', 0)")->execute([':m' => $mid, ':t' => mb_substr($x['assunto'], 0, 180)]);
                $tid = (int)$pdo->lastInsertId();
            }
            $ex = $pdo->prepare('SELECT id FROM edital_itens WHERE concurso_id = :c AND topico_id = :t');
            $ex->execute([':c' => $concursoId, ':t' => $tid]);
            if ($idEx = (int)$ex->fetchColumn()) {
                $pdo->prepare('UPDATE edital_itens SET cargos = :g WHERE id = :id')->execute([':g' => $cargosTxt, ':id' => $idEx]);
                $usados[$idEx] = true;
                $idsNaOrdem[] = $idEx;
                continue;
            }
            $pdo->prepare('INSERT INTO edital_itens (concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:c, :m, :t, :g, 9999)')
                ->execute([':c' => $concursoId, ':m' => $mid, ':t' => $tid, ':g' => $cargosTxt]);
            $idNovo = (int)$pdo->lastInsertId();
            $usados[$idNovo] = true;
            $idsNaOrdem[] = $idNovo;
            $novos++;
        }

        // 3) o que saiu do edital: as aulas e questões vão para o assunto novo mais parecido; o item sai do mapa
        $cursos = array_values(array_filter(array_map(static fn($c) => (int)$c['curso_id'], $cargos)));
        $depois = [];
        foreach (edital_itens($pdo, $concursoId) as $it) {
            if (isset($usados[(int)$it['id']])) { $depois[] = $it; }
        }
        $sairam = 0;
        $movidas = 0;
        foreach ($antes as $it) {
            if (isset($usados[(int)$it['id']])) {
                continue;
            }
            if (in_array(trim(edital_normalizar((string)$it['materia'])), ['redacao', 'prova discursiva'], true)) {
                continue; // vêm do passo "Provas escritas", não do anexo: ficam
            }
            $alvo = null;
            foreach ($depois as $d) {
                if (edital_normalizar((string)$d['materia']) !== edital_normalizar((string)$it['materia'])) { continue; }
                $paiD = edital_pai((string)$d['assunto']);
                if (edital_mesmo_assunto((string)$it['assunto'], (string)$d['assunto']) || ($paiD !== '' && edital_mesmo_assunto((string)$it['assunto'], $paiD))
                    || ($paiD !== '' && str_contains(edital_normalizar((string)$it['assunto']), trim(edital_normalizar(preg_replace('~^Constitui[çc][ãa]o Federal$~u', 'constituicao da republica federativa do brasil', $paiD)))))) {
                    $alvo = $d;
                    break;
                }
            }
            if ($alvo && $cursos) {
                $in = implode(',', $cursos);
                $mv = $pdo->prepare("UPDATE admin_curso_aulas SET topico_id = :n WHERE topico_id = :o AND curso_id IN ($in)");
                $mv->execute([':n' => (int)$alvo['topico_id'], ':o' => (int)$it['topico_id']]);
                $movidas += $mv->rowCount();
                // questões: só se o assunto antigo não for usado em outro concurso
                $outro = $pdo->prepare('SELECT COUNT(*) FROM edital_itens WHERE topico_id = :t AND concurso_id <> :c');
                $outro->execute([':t' => (int)$it['topico_id'], ':c' => $concursoId]);
                if (!(int)$outro->fetchColumn()) {
                    $pdo->prepare('UPDATE admin_banco_questoes SET materia_topico_id = :n WHERE materia_topico_id = :o')->execute([':n' => (int)$alvo['topico_id'], ':o' => (int)$it['topico_id']]);
                }
            }
            $pdo->prepare('DELETE FROM edital_itens WHERE id = :id')->execute([':id' => (int)$it['id']]);
            $sairam++;
        }

        // 4) ordem do edital em tudo e aproveita aulas de outros cursos
        hack_rascunho_set($pdo, $concursoId, 'ordem_edital', $idsNaOrdem);
        edital_ordenar_itens($pdo, $concursoId, $idsNaOrdem);
        hack_rascunho_set($pdo, $concursoId, 'faltando', null);
        hack_rascunho_set($pdo, $concursoId, 'edital_texto', null); // rascunho antigo da IA não vale mais
        [$aul] = job_sincronizar_concurso($pdo, $concursoId);
        return ['assuntos' => count($ordem), 'reaproveitados' => $reap, 'novos' => $novos, 'sairam' => $sairam, 'aulas_movidas' => $movidas, 'aulas_aproveitadas' => $aul];
    }

    /** Volta o mapa anterior (cópia guardada ao usar o mapa). */
    function mapa_desfazer(PDO $pdo, int $concursoId): int
    {
        $bk = (array)hack_rascunho_get($pdo, $concursoId, 'mapa_backup', []);
        if (!$bk) {
            throw new RuntimeException('Não há cópia para desfazer.');
        }
        $pdo->prepare('DELETE FROM edital_itens WHERE concurso_id = :c')->execute([':c' => $concursoId]);
        $ins = $pdo->prepare('INSERT INTO edital_itens (id, concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:id, :c, :m, :t, :g, :o)');
        foreach ($bk as $b) {
            $ins->execute([':id' => $b['id'], ':c' => $concursoId, ':m' => $b['materia_id'], ':t' => $b['topico_id'], ':g' => $b['cargos'], ':o' => $b['ordem']]);
        }
        hack_rascunho_set($pdo, $concursoId, 'mapa_backup', null);
        edital_ordenar_aulas($pdo, $concursoId);
        return count($bk);
    }

    /** Aplica a ordem do último edital colado aos assuntos e às aulas dos cursos do concurso. */
    function job_aplicar_ordem_edital(PDO $pdo, int $concursoId): void
    {
        $ord = (array)hack_rascunho_get($pdo, $concursoId, 'ordem_edital', []);
        if ($ord) {
            $mapa = [];
            foreach (edital_itens($pdo, $concursoId) as $it) {
                $mapa['n:' . edital_normalizar((string)$it['materia']) . '|' . edital_normalizar((string)$it['assunto'])] = (int)$it['id'];
            }
            $ids = [];
            foreach ($ord as $o) {
                $id = is_int($o) ? $o : ($mapa[(string)$o] ?? 0);
                if ($id) {
                    $ids[] = $id;
                }
            }
            edital_ordenar_itens($pdo, $concursoId, $ids);
        }
        edital_ordenar_aulas($pdo, $concursoId);
    }

    function job_parecidas_ia(PDO $pdo, int $concursoId): string
    {
        $prontas = $pdo->query("SELECT a.id, a.curso_id, a.titulo, COALESCE(t.titulo,'') assunto, COALESCE(m.nome,'') materia, c.nome curso, a.video_url, a.pdf_arquivo
                                  FROM admin_curso_aulas a JOIN admin_cursos c ON c.id = a.curso_id
                                  LEFT JOIN admin_materia_topicos t ON t.id = a.topico_id LEFT JOIN admin_materias m ON m.id = a.materia_id
                                 WHERE COALESCE(TRIM(a.video_url),'') <> '' OR COALESCE(TRIM(a.pdf_arquivo),'') <> '' ORDER BY a.id")->fetchAll(PDO::FETCH_ASSOC);
        $porId = [];
        $unicas = []; // a mesma aula copiada em vários cursos vai uma vez só para a IA
        foreach ($prontas as $p) {
            $porId[(int)$p['id']] = $p;
            $k = mb_strtolower(trim((string)$p['titulo'])) . '|' . trim((string)$p['video_url']) . '|' . trim((string)$p['pdf_arquivo']);
            $unicas[$k] = $unicas[$k] ?? $p;
        }
        $sug = [];
        $chamadas = 0;
        foreach (edital_andamento($pdo, $concursoId) as $an) {
            $cursoDestino = (int)($an['cargo']['curso_id'] ?? 0);
            $faltam = array_values(array_filter($an['itens'], static fn($it) => !$it['aulas']));
            if (!$cursoDestino || !$faltam) {
                continue;
            }
            $cand = array_values(array_filter($unicas, static fn($p) => (int)$p['curso_id'] !== $cursoDestino));
            if (!$cand) {
                continue;
            }
            $prompt = "Você organiza o Projeto Gabaritando (concursos policiais e militares). Para cada ASSUNTO DO EDITAL sem aula, encontre na lista de AULAS PRONTAS as que ensinam esse assunto, "
                . "pelo CONTEÚDO (o nome pode ser diferente: ex.: \"Ciências Naturais › Dinâmica\" é servido por \"Física › Leis de Newton\"). Só indique quando a aula realmente cobrir o assunto; "
                . "pode indicar várias aulas (partes) para o mesmo assunto, na ordem certa. Se nenhuma servir, não liste o assunto.\n"
                . "Responda SOMENTE em JSON: {\"sugestoes\":[{\"item_id\":n,\"aulas\":[ids],\"confianca\":0-100,\"motivo\":\"curto\"}]}\n\n"
                . "ASSUNTOS SEM AULA (" . $an['cargo']['nome'] . "): " . json_encode(array_map(static fn($it) => ['item_id' => (int)$it['id'], 'assunto' => $it['materia'] . ' › ' . $it['assunto']], $faltam), JSON_UNESCAPED_UNICODE) . "\n\n"
                . "AULAS PRONTAS (id: matéria › assunto › título): " . implode("\n", array_map(static fn($p) => $p['id'] . ': ' . $p['materia'] . ' › ' . $p['assunto'] . ' › ' . $p['titulo'], $cand));
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => mb_substr($prompt, 0, 120000)]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 170);
            $chamadas++;
            $pdo = podcast_reconectar($pdo);
            $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')) ?? [];
            $itensOk = [];
            foreach ($faltam as $it) {
                $itensOk[(int)$it['id']] = $it;
            }
            foreach ((array)($j['sugestoes'] ?? []) as $sx) {
                $iid = (int)($sx['item_id'] ?? 0);
                if (!isset($itensOk[$iid]) || (int)($sx['confianca'] ?? 0) < 50) {
                    continue;
                }
                foreach (array_slice((array)($sx['aulas'] ?? []), 0, 6) as $aid) {
                    $aid = (int)$aid;
                    if (!isset($porId[$aid]) || (int)$porId[$aid]['curso_id'] === $cursoDestino) {
                        continue;
                    }
                    $sug[] = ['cargo' => (string)$an['cargo']['nome'], 'cargo_id' => (int)$an['cargo']['id'], 'curso_destino' => $cursoDestino, 'item_id' => $iid,
                        'materia' => (string)$itensOk[$iid]['materia'], 'assunto' => (string)$itensOk[$iid]['assunto'], 'aula_id' => $aid, 'aula' => (string)$porId[$aid]['titulo'],
                        'curso' => (string)$porId[$aid]['curso'], 'nota' => max(0, min(100, (int)$sx['confianca'])), 'motivo' => mb_substr((string)($sx['motivo'] ?? ''), 0, 200)];
                }
            }
        }
        hack_rascunho_set($pdo, $concursoId, 'parecidas', $sug);
        $nAss = count(array_unique(array_map(static fn($x) => $x['cargo_id'] . ':' . $x['item_id'], $sug)));
        return $chamadas ? "A IA achou aulas prontas para $nAss assunto(s) sem aula. Confira em Aulas de cada assunto." : 'Nenhum assunto sem aula para procurar.';
    }

    /** Sugestões da IA ainda válidas (some o que já ganhou aula). */
    function job_aulas_parecidas(PDO $pdo, int $concursoId): array
    {
        $sug = hack_rascunho_get($pdo, $concursoId, 'parecidas', []);
        if (!$sug) {
            return [];
        }
        $semAula = [];
        foreach (edital_andamento($pdo, $concursoId) as $an) {
            foreach ($an['itens'] as $it) {
                if (!$it['aulas']) {
                    $semAula[(int)$an['cargo']['id'] . ':' . (int)$it['id']] = true;
                }
            }
        }
        return array_values(array_filter((array)$sug, static fn($x) => isset($semAula[(int)$x['cargo_id'] . ':' . (int)$x['item_id']])));
    }

    /**
     * Sincroniza o cargo com o que já existe na plataforma (fluxo automático, vale para qualquer concurso):
     *  1) aulas do mesmo assunto em qualquer curso -> copiadas para o curso do cargo;
     *  2) questões do mesmo assunto -> liberadas para o curso do cargo.
     * Retorna [aulas, questões].
     */
    function job_sincronizar_cargo(PDO $pdo, int $concursoId, int $cargoId): array
    {
        $cg = $pdo->prepare('SELECT curso_id FROM concurso_cargos WHERE id = :id AND concurso_id = :c');
        $cg->execute([':id' => $cargoId, ':c' => $concursoId]);
        $curso = (int)$cg->fetchColumn();
        if (!$curso) {
            return [0, 0];
        }
        $aulas = job_aproveitar_entre_cargos($pdo, $concursoId, $cargoId);
        $topicos = array_map(static fn($i) => (int)$i['topico_id'], hack_itens_do_cargo($pdo, $concursoId, $cargoId)) ?: [0];
        $ins = $pdo->prepare('INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id) SELECT q.id, :c FROM admin_banco_questoes q
                               WHERE q.materia_topico_id IN (' . implode(',', $topicos) . ") AND q.status = 'ativo' AND (q.curso_id IS NULL OR q.curso_id <> :c2)");
        $ins->execute([':c' => $curso, ':c2' => $curso]);
        return [$aulas, $ins->rowCount()];
    }

    /** Sincroniza todos os cargos do concurso. */
    function job_sincronizar_concurso(PDO $pdo, int $concursoId): array
    {
        $tot = [0, 0];
        $cg = $pdo->prepare('SELECT id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
        $cg->execute([':c' => $concursoId]);
        foreach ($cg->fetchAll(PDO::FETCH_COLUMN) as $id) {
            [$a, $q] = job_sincronizar_cargo($pdo, $concursoId, (int)$id);
            $tot[0] += $a;
            $tot[1] += $q;
        }
        edital_ordenar_aulas($pdo, $concursoId); // aulas sempre na ordem do edital
        return $tot;
    }
}
