<?php
// Unir aulas duplicadas (cópias criadas pelo mapa do edital) dentro do mesmo módulo.
// A aula com mais conteúdo e progresso fica; a outra recebe o progresso/anotações e vai para um curso-arquivo inativo (não é apagada).
declare(strict_types=1);

require_once __DIR__ . '/edital-lib.php';

if (!function_exists('aulas_dup_bootstrap')) {
    function aulas_dup_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        // Guarda de onde veio a aula escondida (dá para voltar à mão, se precisar).
        $pdo->exec('CREATE TABLE IF NOT EXISTS aulas_unidas (
            aula_id INT UNSIGNED NOT NULL PRIMARY KEY,
            ficou_aula_id INT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            modulo_id INT UNSIGNED NOT NULL,
            unida_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
    }

    /** "Parte 2", "Aula 3"...: número da parte (0 = sem parte). Partes diferentes nunca são a mesma aula. */
    function aulas_dup_parte(string $titulo): int
    {
        return preg_match('~\b(?:parte|aula|pt\.?)\s*(\d{1,2})\b~iu', $titulo, $m) ? (int)$m[1] : 0;
    }

    /** Título comparável: tira só a numeração inicial ("7.", "7.1."), acentos, maiúsculas e espaços extras. O resto (01, 02, Parte 2) tem que bater. */
    function aulas_dup_chave(string $titulo): string
    {
        $t = preg_replace('~^\s*\d{1,3}(\.\d{1,3})*\.?\s*[-–)]?\s*~u', '', $titulo);
        $t = mb_strtolower((string)$t, 'UTF-8');
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c']);
        return trim((string)preg_replace('~\s+~u', ' ', $t));
    }

    /** Pontos de conteúdo da aula (vídeo, PDF, material, videoaula/podcast da IA, descrição). */
    function aulas_dup_conteudo(PDO $pdo, array $a): int
    {
        $p = (trim((string)$a['video_url']) !== '' || trim((string)$a['video_arquivo']) !== '' ? 4 : 0)
            + (trim((string)$a['pdf_arquivo']) !== '' ? 3 : 0)
            + (trim((string)$a['material_arquivo']) !== '' ? 1 : 0)
            + (mb_strlen(trim(strip_tags((string)$a['descricao']))) > 200 ? 1 : 0);
        foreach (['aula_videoaulas' => 2, 'aula_podcasts' => 1] as $t => $pts) {
            try {
                $st = $pdo->prepare("SELECT 1 FROM $t WHERE aula_id = :a LIMIT 1");
                $st->execute([':a' => (int)$a['id']]);
                $p += $st->fetchColumn() ? $pts : 0;
            } catch (Throwable $e) {
            }
        }
        return $p;
    }

    /** Quantos alunos têm progresso nesta aula (vídeo ou PDF). */
    function aulas_dup_progresso(PDO $pdo, int $aulaId): int
    {
        $n = 0;
        foreach (['aulas_progresso', 'aulas_pdf_progresso'] as $t) {
            try {
                $st = $pdo->prepare("SELECT COUNT(DISTINCT usuario_id) FROM $t WHERE aula_id = :a");
                $st->execute([':a' => $aulaId]);
                $n = max($n, (int)$st->fetchColumn());
            } catch (Throwable $e) {
            }
        }
        return $n;
    }

    /**
     * Grupos de aulas duplicadas nos cursos dados: mesmo módulo, mesma parte e mesmo título (sem numeração),
     * ou um título contido no outro ("Brasil Colônia" × "Brasil Colônia (1530-1815): Capitanias…").
     * Cada grupo: ['curso', 'modulo', 'fica' => aula, 'saem' => [aulas]]; cada aula com conteudo e progresso.
     */
    function aulas_dup_grupos(PDO $pdo, array $cursos): array
    {
        $cursos = array_values(array_filter(array_map('intval', $cursos)));
        if (!$cursos) {
            return [];
        }
        $in = implode(',', $cursos);
        $rows = $pdo->query("SELECT a.*, c.nome AS curso_nome, m.titulo AS modulo_titulo FROM admin_curso_aulas a
                               LEFT JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                              WHERE a.curso_id IN ($in) AND a.modulo_id > 0 ORDER BY a.curso_id, a.modulo_id, a.ordem, a.id")->fetchAll(PDO::FETCH_ASSOC);
        $porMod = [];
        foreach ($rows as $r) {
            $porMod[(int)$r['modulo_id']][] = $r;
        }
        $grupos = [];
        foreach ($porMod as $lista) {
            $n = count($lista);
            $pai = range(0, $n - 1);
            $raiz = static function (int $i) use (&$pai): int {
                while ($pai[$i] !== $i) {
                    $i = $pai[$i] = $pai[$pai[$i]];
                }
                return $i;
            };
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $lista[$i];
                    $b = $lista[$j];
                    if (aulas_dup_chave((string)$a['titulo']) === aulas_dup_chave((string)$b['titulo'])) { // só título igual: na dúvida, não une
                        $pai[$raiz($j)] = $raiz($i);
                    }
                }
            }
            $junta = [];
            foreach ($lista as $i => $r) {
                $junta[$raiz($i)][] = $r;
            }
            foreach ($junta as $g) {
                if (count($g) < 2) {
                    continue;
                }
                foreach ($g as $k => $r) {
                    $g[$k]['conteudo'] = aulas_dup_conteudo($pdo, $r);
                    $g[$k]['progresso'] = aulas_dup_progresso($pdo, (int)$r['id']);
                }
                // Fica: mais conteúdo, depois mais progresso, depois a mais antiga.
                usort($g, static fn($x, $y) => [$y['conteudo'], $y['progresso'], (int)$x['id']] <=> [$x['conteudo'], $x['progresso'], (int)$y['id']]);
                $curso = (string)$g[0]['curso_nome'];
                $modulo = (string)$g[0]['modulo_titulo'];
                $g = array_map(static fn($r) => array_intersect_key($r, array_flip(['id', 'titulo', 'topico_id', 'conteudo', 'progresso'])), $g);
                $fica = array_shift($g);
                $grupos[] = ['curso' => $curso, 'modulo' => $modulo, 'fica' => $fica, 'saem' => $g];
            }
        }
        return $grupos;
    }

    /** Curso inativo (aluno não vê) que guarda as aulas escondidas: [curso_id, modulo_id]. */
    function aulas_dup_arquivo(PDO $pdo): array
    {
        $nome = 'Arquivo: aulas repetidas unidas (não apagar)';
        $st = $pdo->prepare('SELECT id FROM admin_cursos WHERE nome = :n LIMIT 1');
        $st->execute([':n' => $nome]);
        $cid = (int)$st->fetchColumn();
        if ($cid <= 0) {
            $pdo->prepare("INSERT INTO admin_cursos (nome, descricao, status, ordem) VALUES (:n, 'Aulas escondidas por \"Unir aulas duplicadas\". Alunos não veem este curso.', 'inativo', 9999)")->execute([':n' => $nome]);
            $cid = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("UPDATE admin_cursos SET status = 'inativo' WHERE id = :c")->execute([':c' => $cid]);
        $st = $pdo->prepare('SELECT id FROM admin_curso_modulos WHERE curso_id = :c ORDER BY id LIMIT 1');
        $st->execute([':c' => $cid]);
        $mid = (int)$st->fetchColumn();
        if ($mid <= 0) {
            $pdo->prepare("INSERT INTO admin_curso_modulos (curso_id, titulo, ordem) VALUES (:c, 'Aulas escondidas', 1)")->execute([':c' => $cid]);
            $mid = (int)$pdo->lastInsertId();
        }
        return [$cid, $mid];
    }

    /** Une uma aula na outra: move progresso, anotações e respostas; esconde a duplicada (sai do curso e do módulo). */
    function aulas_dup_unir(PDO $pdo, int $saiId, int $ficaId): void
    {
        aulas_dup_bootstrap($pdo);
        $st = $pdo->prepare('SELECT curso_id, modulo_id FROM admin_curso_aulas WHERE id = :a');
        $st->execute([':a' => $saiId]);
        $orig = $st->fetch(PDO::FETCH_ASSOC);
        [$arqCurso, $arqMod] = aulas_dup_arquivo($pdo);
        if (!$orig || (int)$orig['curso_id'] === $arqCurso) {
            return;
        }
        // Do aluno: passa para a aula que fica (se ele já tem registro lá, o dela vale).
        foreach (['aulas_progresso', 'aulas_pdf_progresso', 'aula_marcacoes', 'aula_marcacoes_pdf', 'aula_videoaula_respostas', 'aulas_avaliacoes',
                  'aulas_forum_topicos', 'aula_reportes', 'admin_metas_diarias', 'admin_cronograma_itens', 'meta_plano_itens'] as $t) {
            try {
                $pdo->prepare("UPDATE IGNORE $t SET aula_id = :f WHERE aula_id = :s")->execute([':f' => $ficaId, ':s' => $saiId]);
            } catch (Throwable $e) {
            }
        }
        // Conteúdo gerado: só passa se a aula que fica ainda não tem.
        foreach (['aula_videoaulas', 'aula_podcasts', 'aula_revisao', 'aula_transcricoes', 'padrao_assunto_ok'] as $t) {
            try {
                $tem = $pdo->prepare("SELECT 1 FROM $t WHERE aula_id = :f LIMIT 1");
                $tem->execute([':f' => $ficaId]);
                if (!$tem->fetchColumn()) {
                    $pdo->prepare("UPDATE IGNORE $t SET aula_id = :f WHERE aula_id = :s")->execute([':f' => $ficaId, ':s' => $saiId]);
                }
            } catch (Throwable $e) {
            }
        }
        $pdo->prepare('REPLACE INTO aulas_unidas (aula_id, ficou_aula_id, curso_id, modulo_id) VALUES (:a, :f, :c, :m)')
            ->execute([':a' => $saiId, ':f' => $ficaId, ':c' => (int)$orig['curso_id'], ':m' => (int)$orig['modulo_id']]);
        $pdo->prepare('UPDATE admin_curso_aulas SET curso_id = :c, modulo_id = :m, atualizado_em = NOW() WHERE id = :a')->execute([':c' => $arqCurso, ':m' => $arqMod, ':a' => $saiId]);
    }

    /** Simular (só lista) ou aplicar. */
    function aulas_dup_executar(PDO $pdo, array $cursos, bool $aplicar): array
    {
        $grupos = aulas_dup_grupos($pdo, $cursos);
        $res = ['grupos' => $grupos, 'unidas' => 0];
        if (!$aplicar) {
            return $res;
        }
        aulas_dup_bootstrap($pdo); // fora da transação (CREATE TABLE fecha transação no MySQL)
        aulas_dup_arquivo($pdo);
        $pdo->beginTransaction();
        try {
            foreach ($grupos as $g) {
                foreach ($g['saem'] as $s) {
                    aulas_dup_unir($pdo, (int)$s['id'], (int)$g['fica']['id']);
                    $res['unidas']++;
                }
            }
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $res;
    }
}
