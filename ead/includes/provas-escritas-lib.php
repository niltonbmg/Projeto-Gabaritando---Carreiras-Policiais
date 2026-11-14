<?php
// includes/provas-escritas-lib.php — Redação e prova discursiva de cada cargo (lidas do edital, conferidas pelo mentor).
// Fica em concursos.edital_info['provas_escritas'] = [{cargo, redacao:{...}, discursiva:{...}}] e ['provas_escritas_conferido'].
// A plataforma usa isso para se adaptar: menu Redação/Discursiva, cronograma e correção pela grade do edital.
declare(strict_types=1);

if (!function_exists('pe_vazio')) {
    function pe_vazio(): array
    {
        return [
            'redacao' => ['tem' => null, 'tipo' => '', 'linhas_min' => null, 'linhas_max' => null, 'pontos' => null, 'nota_minima' => null, 'eliminatoria' => null, 'criterios' => [], 'observacao' => ''],
            'discursiva' => ['tem' => null, 'questoes' => null, 'materias' => [], 'formato' => '', 'linhas' => null, 'pontos' => null, 'nota_minima' => null, 'eliminatoria' => null, 'observacao' => ''],
        ];
    }

    /** Formato pedido à IA (o mesmo na leitura completa do edital e na releitura só desta parte). */
    function pe_formato_json(): string
    {
        return '"provas_escritas":[{"cargo":"nome exato do cargo","redacao":{"tem":true,"tipo":"ex.: texto dissertativo-argumentativo","linhas_min":20,"linhas_max":30,"pontos":20,"nota_minima":null,"eliminatoria":true,'
            . '"criterios":[{"nome":"critério como está no edital","max":0,"descricao":"o que é avaliado"}],"observacao":"1 frase"},'
            . '"discursiva":{"tem":false,"questoes":null,"materias":["matérias cobradas"],"formato":"ex.: questões abertas, estudo de caso, peça","linhas":null,"pontos":null,"nota_minima":null,"eliminatoria":null,"observacao":""}}]';
    }

    function pe_bool($v): ?bool
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            $v = mb_strtolower(trim($v));
            return in_array($v, ['1', 'sim', 'true', 'tem', 's'], true) ? true : (in_array($v, ['0', 'nao', 'não', 'false', 'n'], true) ? false : null);
        }
        return (bool)$v;
    }

    function pe_num($v): ?float
    {
        if ($v === null || $v === '' || !is_numeric(str_replace(',', '.', (string)$v))) {
            return null;
        }
        return (float)str_replace(',', '.', (string)$v);
    }

    /** Normaliza o que veio da IA ou do formulário. */
    function pe_normalizar(array $x): array
    {
        $b = pe_vazio();
        $r = (array)($x['redacao'] ?? []);
        $d = (array)($x['discursiva'] ?? []);
        $crit = [];
        foreach ((array)($r['criterios'] ?? []) as $c) {
            if (is_array($c) && trim((string)($c['nome'] ?? '')) !== '') {
                $crit[] = ['nome' => mb_substr(trim((string)$c['nome']), 0, 120), 'max' => (float)(pe_num($c['max'] ?? null) ?? 0), 'descricao' => mb_substr(trim((string)($c['descricao'] ?? '')), 0, 300)];
            }
        }
        $b['redacao'] = ['tem' => pe_bool($r['tem'] ?? null), 'tipo' => mb_substr(trim((string)($r['tipo'] ?? '')), 0, 120),
            'linhas_min' => pe_num($r['linhas_min'] ?? null), 'linhas_max' => pe_num($r['linhas_max'] ?? null), 'pontos' => pe_num($r['pontos'] ?? null),
            'nota_minima' => pe_num($r['nota_minima'] ?? null), 'eliminatoria' => pe_bool($r['eliminatoria'] ?? null), 'criterios' => $crit,
            'observacao' => mb_substr(trim((string)($r['observacao'] ?? '')), 0, 300)];
        $mats = is_array($d['materias'] ?? null) ? $d['materias'] : preg_split('~[;,\n]~', (string)($d['materias'] ?? ''));
        $b['discursiva'] = ['tem' => pe_bool($d['tem'] ?? null), 'questoes' => pe_num($d['questoes'] ?? null),
            'materias' => array_values(array_filter(array_map(static fn($m) => mb_substr(trim((string)$m), 0, 120), (array)$mats))),
            'formato' => mb_substr(trim((string)($d['formato'] ?? '')), 0, 160), 'linhas' => pe_num($d['linhas'] ?? null), 'pontos' => pe_num($d['pontos'] ?? null),
            'nota_minima' => pe_num($d['nota_minima'] ?? null), 'eliminatoria' => pe_bool($d['eliminatoria'] ?? null),
            'observacao' => mb_substr(trim((string)($d['observacao'] ?? '')), 0, 300)];
        return $b;
    }

    function pe_chave(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return trim(preg_replace('~[^a-z0-9]+~', ' ', $s));
    }

    /** Provas escritas de um cargo. Sem a leitura nova, usa o "redacao" antigo do edital (vale para todos os cargos). */
    function pe_do_cargo(array $info, string $cargo): array
    {
        $lista = (array)($info['provas_escritas'] ?? []);
        $k = pe_chave($cargo);
        $achado = null;
        foreach ($lista as $x) {
            $kc = pe_chave((string)($x['cargo'] ?? ''));
            if ($kc !== '' && ($kc === $k || str_contains($k, $kc) || str_contains($kc, $k))) {
                $achado = $x;
                break;
            }
        }
        if ($achado === null && count($lista) === 1 && pe_chave((string)($lista[0]['cargo'] ?? '')) === '') {
            $achado = $lista[0]; // a IA não separou por cargo: vale para todos
        }
        if ($achado !== null) {
            return pe_normalizar($achado);
        }
        $b = pe_vazio();
        if (isset($info['redacao']) && is_array($info['redacao'])) {
            $b = pe_normalizar(['redacao' => ['tem' => $info['redacao']['tem'] ?? null, 'criterios' => $info['redacao']['criterios'] ?? [], 'observacao' => $info['redacao']['criterio'] ?? '']]);
        }
        return $b;
    }

    /** Provas escritas do concurso/cargo que o aluno escolheu (null = aluno sem concurso escolhido). */
    function pe_do_aluno(PDO $pdo, int $usuarioId): ?array
    {
        static $memo = [];
        if (array_key_exists($usuarioId, $memo)) {
            return $memo[$usuarioId];
        }
        $memo[$usuarioId] = null;
        try {
            $st = $pdo->prepare('SELECT c.id, c.nome, c.edital_info, cg.nome cargo FROM aluno_concurso ac JOIN concursos c ON c.id = ac.concurso_id LEFT JOIN concurso_cargos cg ON cg.id = ac.cargo_id WHERE ac.usuario_id = :u LIMIT 1');
            $st->execute([':u' => $usuarioId]);
            $c = $st->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $info = json_decode((string)($c['edital_info'] ?? ''), true) ?: [];
                $memo[$usuarioId] = pe_do_cargo($info, (string)($c['cargo'] ?? '')) + ['concurso' => (string)$c['nome'], 'cargo' => (string)($c['cargo'] ?? '')];
            }
        } catch (Throwable $e) {
        }
        return $memo[$usuarioId];
    }

    /** Só esconde quando o edital diz com certeza que NÃO tem (na dúvida, mostra). */
    function pe_aluno_tem(PDO $pdo, int $usuarioId, string $qual): bool
    {
        $p = pe_do_aluno($pdo, $usuarioId);
        if ($p === null) {
            return $qual === 'redacao'; // sem concurso escolhido: redação aparece; discursiva não
        }
        $tem = $p[$qual]['tem'] ?? null;
        return $qual === 'redacao' ? $tem !== false : $tem === true;
    }

    /** IA relê só a parte de redação e discursiva do edital (1 pedido de texto). */
    function pe_ia_ler(string $texto, string $pdfBase64, array $cargos): array
    {
        $prompt = "Você é especialista em editais de concursos policiais e militares da Bahia e trabalha para o Projeto Gabaritando.\n"
            . "Leia o EDITAL e diga, PARA CADA CARGO, se existe REDAÇÃO e se existe PROVA DISCURSIVA (questões abertas, estudo de caso, peça), com os detalhes.\n"
            . "Cargos: " . json_encode($cargos, JSON_UNESCAPED_UNICODE) . ".\n"
            . "REGRAS: copie números exatamente como no edital. \"tem\": true só se o edital prevê; false se o edital descreve as provas e ela não está entre elas; null se o texto não permite saber. Nunca invente.\n"
            . "Responda SOMENTE em JSON: {" . pe_formato_json() . "}\n\n"
            . "EDITAL (texto):\n" . mb_substr($texto, 0, 80000);
        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 170);
        $j = edital_json_do_texto((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        $lista = [];
        foreach ((array)($j['provas_escritas'] ?? []) as $x) {
            if (is_array($x)) {
                $lista[] = ['cargo' => mb_substr(trim((string)($x['cargo'] ?? '')), 0, 180)] + pe_normalizar($x);
            }
        }
        if (!$lista) {
            throw new RuntimeException('A IA não conseguiu identificar redação e discursiva nesse edital. Preencha à mão no passo "Provas escritas".');
        }
        return $lista;
    }

    /** Grava a lista (por cargo) dentro de edital_info. $conferido = o mentor revisou. */
    function pe_salvar(PDO $pdo, int $concursoId, array $lista, bool $conferido): void
    {
        $st = $pdo->prepare('SELECT edital_info FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $info = json_decode((string)$st->fetchColumn(), true) ?: [];
        $info['provas_escritas'] = array_values($lista);
        $info['provas_escritas_conferido'] = $conferido;
        $pdo->prepare('UPDATE concursos SET edital_info = :i WHERE id = :id')->execute([':i' => json_encode($info, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
    }

    /** Frase curta para telas: "✍️ Redação dissertativa · 20 a 30 linhas · 20 pts · eliminatória". */
    function pe_resumo(array $p, string $qual): string
    {
        $x = $p[$qual] ?? [];
        if (($x['tem'] ?? null) === null) {
            return 'não identificado';
        }
        if ($x['tem'] === false) {
            return 'não tem';
        }
        $f = static fn($n) => $n === null ? '' : rtrim(rtrim(number_format((float)$n, 1, ',', ''), '0'), ',');
        $partes = [];
        if ($qual === 'redacao') {
            $partes[] = $x['tipo'] !== '' ? $x['tipo'] : 'tem';
            if ($x['linhas_min'] !== null || $x['linhas_max'] !== null) {
                $partes[] = trim(($x['linhas_min'] !== null ? $f($x['linhas_min']) : '') . ($x['linhas_min'] !== null && $x['linhas_max'] !== null ? ' a ' : '') . ($x['linhas_max'] !== null ? $f($x['linhas_max']) : '')) . ' linhas';
            }
        } else {
            $partes[] = ($x['questoes'] !== null ? $f($x['questoes']) . ' questão(ões)' : 'tem') . ($x['formato'] !== '' ? ' · ' . $x['formato'] : '');
            if ($x['materias']) {
                $partes[] = implode(', ', array_slice($x['materias'], 0, 4));
            }
        }
        if ($x['pontos'] !== null) {
            $partes[] = $f($x['pontos']) . ' pts';
        }
        if ($x['nota_minima'] !== null) {
            $partes[] = 'mínimo ' . $f($x['nota_minima']);
        }
        if ($x['eliminatoria'] === true) {
            $partes[] = 'eliminatória';
        }
        return implode(' · ', $partes);
    }
}

if (!function_exists('pe_criar_assuntos')) {
    /**
     * Quando o mentor confirma: cria no conteúdo programático as matérias "Redação" e "Prova discursiva"
     * (só para os cargos que têm), com os assuntos do treino. Assim entram no passo Aulas, no plano e no cronograma.
     * Não duplica: roda de novo sem problema. Retorna quantos assuntos novos.
     */
    function pe_criar_assuntos(PDO $pdo, int $concursoId): int
    {
        $st = $pdo->prepare('SELECT edital_info FROM concursos WHERE id = :id');
        $st->execute([':id' => $concursoId]);
        $info = json_decode((string)$st->fetchColumn(), true) ?: [];
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
        $cg->execute([':c' => $concursoId]);
        $cargos = $cg->fetchAll(PDO::FETCH_ASSOC);
        $com = ['redacao' => [], 'discursiva' => []];
        $tipoRed = '';
        $matDisc = [];
        foreach ($cargos as $c) {
            $p = pe_do_cargo($info, (string)$c['nome']);
            if (($p['redacao']['tem'] ?? null) === true) {
                $com['redacao'][] = (int)$c['id'];
                $tipoRed = $tipoRed ?: (string)$p['redacao']['tipo'];
            }
            if (($p['discursiva']['tem'] ?? null) === true) {
                $com['discursiva'][] = (int)$c['id'];
                $matDisc = array_merge($matDisc, (array)$p['discursiva']['materias']);
            }
        }
        $blocos = [];
        if ($com['redacao']) {
            $blocos[] = ['Redação', $com['redacao'], [
                'Como a banca corrige a redação: critérios do edital',
                'Estrutura do texto ' . ($tipoRed !== '' ? mb_strtolower($tipoRed) : 'dissertativo-argumentativo'),
                'Introdução: contextualização e tese',
                'Desenvolvimento: argumentos e repertório',
                'Conclusão e proposta de intervenção',
                'Coesão e coerência na redação',
                'Erros de gramática que mais tiram pontos na redação',
                'Temas prováveis: segurança pública e cidadania',
            ]];
        }
        if ($com['discursiva']) {
            $assuntosD = ['Como responder questão discursiva: comando, espelho e critérios', 'Estrutura da resposta e controle de linhas'];
            foreach (array_unique(array_filter($matDisc)) as $m) {
                $assuntosD[] = 'Discursiva de ' . $m;
            }
            $blocos[] = ['Prova discursiva', $com['discursiva'], $assuntosD];
        }
        $ordem = (int)$pdo->query('SELECT COALESCE(MAX(ordem), 0) FROM edital_itens WHERE concurso_id = ' . $concursoId)->fetchColumn();
        $n = 0;
        foreach ($blocos as [$materia, $ids, $assuntos]) {
            $sm = $pdo->prepare('SELECT id FROM admin_materias WHERE nome = :n LIMIT 1');
            $sm->execute([':n' => $materia]);
            $mid = (int)$sm->fetchColumn();
            if (!$mid) {
                $pdo->prepare("INSERT INTO admin_materias (nome, status, ordem) VALUES (:n, 'ativo', 0)")->execute([':n' => $materia]);
                $mid = (int)$pdo->lastInsertId();
            }
            // todos os cargos têm = vale para todos (cargos vazio); senão, só os ids desses cargos
            $cargosTxt = count($ids) === count($cargos) ? '' : implode(',', $ids);
            foreach ($assuntos as $i => $a) {
                $st = $pdo->prepare('SELECT id FROM admin_materia_topicos WHERE materia_id = :m AND titulo = :t LIMIT 1');
                $st->execute([':m' => $mid, ':t' => $a]);
                $tid = (int)$st->fetchColumn();
                if (!$tid) {
                    $pdo->prepare("INSERT INTO admin_materia_topicos (materia_id, titulo, status, ordem) VALUES (:m, :t, 'ativo', :o)")->execute([':m' => $mid, ':t' => mb_substr($a, 0, 180), ':o' => $i + 1]);
                    $tid = (int)$pdo->lastInsertId();
                }
                $ex = $pdo->prepare('SELECT id FROM edital_itens WHERE concurso_id = :c AND topico_id = :t');
                $ex->execute([':c' => $concursoId, ':t' => $tid]);
                if ($ex->fetchColumn()) {
                    $pdo->prepare('UPDATE edital_itens SET cargos = :g WHERE concurso_id = :c AND topico_id = :t')->execute([':g' => $cargosTxt, ':c' => $concursoId, ':t' => $tid]);
                    continue;
                }
                $pdo->prepare('INSERT INTO edital_itens (concurso_id, materia_id, topico_id, cargos, ordem) VALUES (:c, :m, :t, :g, :o)')
                    ->execute([':c' => $concursoId, ':m' => $mid, ':t' => $tid, ':g' => $cargosTxt, ':o' => ++$ordem]);
                $n++;
            }
        }
        return $n;
    }
}
