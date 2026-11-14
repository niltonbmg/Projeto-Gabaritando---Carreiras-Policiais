<?php
// includes/meta-lib.php
// Motor do Método META: Plano-Mestre por curso, trava de etapas e níveis por assunto.
//
// Regras (editáveis em meta_config):
//   - O aluno segue os assuntos do Plano-Mestre na ordem.
//   - Em cada assunto: 1) Teoria mínima  2) Questões comentadas.
//   - O próximo assunto só abre quando o atual tem o mínimo de questões resolvidas (a teoria é recomendada) e
//     pelo menos `min_questoes` (padrão 5) questões resolvidas.
//   - Nível do assunto pelas questões resolvidas e % de acerto:
//       1 Iniciante 5 · 2 Praticante 15 (60%) · 3 Avançado 30 (70%) · 4 Domínio 50 (80%).
//   - Admin pode liberar exceção para um aluno (fica registrado).
declare(strict_types=1);

const META_ETAPA_TEORIA = 'teoria';
const META_ETAPA_QUESTOES = 'questoes';
const META_ETAPA_CONCLUIDO = 'concluido';
const META_ETAPA_BLOQUEADO = 'bloqueado';

if (!function_exists('meta_bootstrap')) {
    function meta_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_config (
            chave VARCHAR(60) NOT NULL PRIMARY KEY,
            valor VARCHAR(255) NOT NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_planos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            curso_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            status ENUM('rascunho','aprovado') NOT NULL DEFAULT 'rascunho',
            aprovado_em DATETIME NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_meta_planos_curso (curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_plano_itens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            plano_id INT UNSIGNED NOT NULL,
            ordem INT NOT NULL DEFAULT 0,
            materia_id INT UNSIGNED NULL,
            topico_id INT UNSIGNED NULL,
            titulo VARCHAR(180) NOT NULL,
            aula_id INT UNSIGNED NULL,
            peso DECIMAL(5,2) NOT NULL DEFAULT 1.00,
            horas DECIMAL(6,2) NOT NULL DEFAULT 1.00,
            meta_questoes INT UNSIGNED NOT NULL DEFAULT 50,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_meta_itens_plano (plano_id, ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_aluno_itens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario_id BIGINT UNSIGNED NOT NULL,
            plano_item_id INT UNSIGNED NOT NULL,
            teoria_concluida_em DATETIME NULL,
            liberado_por_admin_em DATETIME NULL,
            liberado_motivo VARCHAR(255) NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_meta_aluno_item (usuario_id, plano_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $padroes = [
            'min_questoes' => '5',
            'nivel2_questoes' => '15', 'nivel2_acerto' => '60',
            'nivel3_questoes' => '30', 'nivel3_acerto' => '70',
            'nivel4_questoes' => '50', 'nivel4_acerto' => '80',
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO meta_config (chave, valor) VALUES (:c, :v)');
        foreach ($padroes as $c => $v) {
            $stmt->execute([':c' => $c, ':v' => $v]);
        }
        $feito = true;
    }
}

if (!function_exists('meta_config')) {
    /** @return array<string,int> */
    function meta_config(PDO $pdo, bool $recarregar = false): array
    {
        static $cfg = null; // lida uma vez por visita (era lida 5 vezes por página)
        if ($cfg === null || $recarregar) {
            $cfg = [];
            foreach ($pdo->query('SELECT chave, valor FROM meta_config') as $r) {
                $cfg[(string)$r['chave']] = (int)$r['valor'];
            }
        }
        return $cfg;
    }
}

if (!function_exists('meta_niveis')) {
    /** Tabela de níveis montada a partir da configuração. */
    function meta_niveis(array $cfg): array
    {
        return [
            1 => ['nome' => 'Iniciante', 'questoes' => $cfg['min_questoes'] ?? 5, 'acerto' => 0],
            2 => ['nome' => 'Praticante', 'questoes' => $cfg['nivel2_questoes'] ?? 15, 'acerto' => $cfg['nivel2_acerto'] ?? 60],
            3 => ['nome' => 'Avançado', 'questoes' => $cfg['nivel3_questoes'] ?? 30, 'acerto' => $cfg['nivel3_acerto'] ?? 70],
            4 => ['nome' => 'Domínio', 'questoes' => $cfg['nivel4_questoes'] ?? 50, 'acerto' => $cfg['nivel4_acerto'] ?? 80],
        ];
    }
}

if (!function_exists('meta_nivel')) {
    /**
     * Nível do assunto (0 a 4) pelas questões resolvidas e % de acerto.
     * Também devolve quanto falta para o próximo nível.
     */
    function meta_nivel(int $resolvidas, int $acertos, array $cfg): array
    {
        $niveis = meta_niveis($cfg);
        $pct = $resolvidas > 0 ? (int)round($acertos * 100 / $resolvidas) : 0;
        $nivel = 0;
        foreach ($niveis as $n => $req) {
            if ($resolvidas >= $req['questoes'] && $pct >= $req['acerto']) {
                $nivel = $n;
            }
        }
        $proximo = $niveis[$nivel + 1] ?? null;
        $faltam = $proximo ? max(0, $proximo['questoes'] - $resolvidas) : 0;
        $meta = $niveis[4]['questoes'];

        return [
            'nivel' => $nivel,
            'nome' => $nivel > 0 ? $niveis[$nivel]['nome'] : 'Não iniciado',
            'resolvidas' => $resolvidas,
            'acertos' => $acertos,
            'percentual' => $pct,
            'faltam_proximo' => $faltam,
            'proximo_acerto' => $proximo['acerto'] ?? null,
            'proximo_nome' => $proximo['nome'] ?? null,
            'progresso_meta' => $meta > 0 ? min(100, (int)round($resolvidas * 100 / $meta)) : 0,
        ];
    }
}

if (!function_exists('meta_plano_do_curso')) {
    /** Plano aprovado do curso (o mais recente). */
    function meta_plano_do_curso(PDO $pdo, int $cursoId): ?array
    {
        static $memo = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && PHP_SAPI !== 'cli' && array_key_exists($cursoId, $memo)) {
            return $memo[$cursoId];
        }
        return $memo[$cursoId] = meta_plano_do_curso_banco($pdo, $cursoId);
    }

    function meta_plano_do_curso_banco(PDO $pdo, int $cursoId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM meta_planos WHERE curso_id = :c AND status = 'aprovado' ORDER BY aprovado_em DESC, id DESC LIMIT 1");
        $stmt->execute([':c' => $cursoId]);
        return $stmt->fetch() ?: null;
    }
}

if (!function_exists('meta_estado_aluno')) {
    /**
     * Estado do aluno no plano: cada assunto com etapa, nível e trava.
     * Retorna ['plano'=>..., 'itens'=>[...], 'atual'=>item|null, 'nivel_geral'=>float, 'dominio_pct'=>int].
     */
    function meta_estado_aluno(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        // Numa página (GET) o estado não muda: calcula uma vez só (era calculado 3 vezes).
        static $memo = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || PHP_SAPI === 'cli') {
            return meta_estado_aluno_calc($pdo, $usuarioId, $cursoId);
        }
        return $memo[$usuarioId . ':' . $cursoId] ??= meta_estado_aluno_calc($pdo, $usuarioId, $cursoId);
    }

    function meta_estado_aluno_calc(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        meta_bootstrap($pdo);
        $cfg = meta_config($pdo);
        $minQ = max(1, $cfg['min_questoes'] ?? 5);
        $plano = meta_plano_do_curso($pdo, $cursoId);
        $qtdDisp = "(SELECT COUNT(*) FROM admin_banco_questoes qd WHERE qd.status = 'ativo' AND qd.materia_topico_id = %s
                       AND EXISTS (SELECT 1 FROM admin_banco_questao_opcoes od WHERE od.questao_id = qd.id AND od.correta = 1)) AS disponiveis";
        if (!$plano) {
            // Sem Plano-Mestre aprovado: o ciclo segue a ORDEM DAS AULAS do curso (um item por assunto do edital),
            // para a trava, a missão e as questões funcionarem igual. Ids a partir de 3.000.000.000 = itens implícitos (sem plano).
            $stI = $pdo->prepare("SELECT (3000000000 + a.id) AS id, a.id AS aula_id, a.topico_id, a.materia_id, COALESCE(mt.titulo, a.titulo) AS titulo, mt.titulo AS topico_nome, m.nome AS materia_nome,
                        a.titulo AS aula_titulo, a.modulo_id AS aula_modulo_id, a.curso_id AS aula_curso_id, 1 AS peso, 'media' AS complexidade, NULL AS tempo_ajustado_min,
                        NULL AS teoria_concluida_em, NULL AS liberado_por_admin_em, NULL AS liberado_motivo,
                        (SELECT COUNT(*) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u1 AND q.materia_topico_id = a.topico_id) AS resolvidas,
                        (SELECT COALESCE(SUM(r.correta), 0) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.usuario_id = :u2 AND q.materia_topico_id = a.topico_id) AS acertos,
                        " . sprintf($qtdDisp, 'a.topico_id') . "
                   FROM admin_curso_aulas a LEFT JOIN admin_curso_modulos mo ON mo.id = a.modulo_id
                   LEFT JOIN admin_materia_topicos mt ON mt.id = a.topico_id LEFT JOIN admin_materias m ON m.id = a.materia_id
                  WHERE a.curso_id = :c AND a.topico_id IS NOT NULL AND a.topico_id > 0
                  ORDER BY COALESCE(mo.ordem, 0), mo.id, a.ordem, a.id");
            $stI->execute([':u1' => $usuarioId, ':u2' => $usuarioId, ':c' => $cursoId]);
            $linhas = [];
            $vistos = [];
            foreach ($stI->fetchAll() as $l) {
                if (!isset($vistos[(int)$l['topico_id']])) { // primeira aula de cada assunto
                    $vistos[(int)$l['topico_id']] = true;
                    $linhas[] = $l;
                }
            }
            if (!$linhas) {
                return ['plano' => null, 'itens' => [], 'atual' => null, 'atuais' => [], 'nivel_geral' => 0.0, 'dominio_pct' => 0, 'config' => $cfg];
            }
            $plano = ['id' => 0, 'curso_id' => $cursoId, 'status' => 'aprovado', 'implicito' => true, 'titulo' => 'Ordem das aulas do curso'];
        } else {
        $stmt = $pdo->prepare("SELECT i.*, mt.titulo AS topico_nome, m.nome AS materia_nome,
                    ai.teoria_concluida_em, ai.liberado_por_admin_em, ai.liberado_motivo,
                    a.titulo AS aula_titulo, a.modulo_id AS aula_modulo_id, a.curso_id AS aula_curso_id,
                    (SELECT COUNT(*) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id
                      WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u1 AND i.topico_id IS NOT NULL AND q.materia_topico_id = i.topico_id) AS resolvidas,
                    (SELECT COALESCE(SUM(r.correta), 0) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id
                      WHERE r.usuario_id = :u2 AND i.topico_id IS NOT NULL AND q.materia_topico_id = i.topico_id) AS acertos,
                    " . sprintf($qtdDisp, 'i.topico_id') . "
               FROM meta_plano_itens i
               LEFT JOIN admin_materia_topicos mt ON mt.id = i.topico_id
               LEFT JOIN admin_materias m ON m.id = i.materia_id
               LEFT JOIN admin_curso_aulas a ON a.id = i.aula_id
               LEFT JOIN meta_aluno_itens ai ON ai.plano_item_id = i.id AND ai.usuario_id = :u3
              WHERE i.plano_id = :p
              ORDER BY i.ordem ASC, i.id ASC");
        $stmt->execute([':u1' => $usuarioId, ':u2' => $usuarioId, ':u3' => $usuarioId, ':p' => (int)$plano['id']]);
        $linhas = $stmt->fetchAll();
        }
        // Ciclo automático: aula marcada como assistida na página da aula já conta como teoria concluída.
        try {
            $stA = $pdo->prepare('SELECT aula_id, MIN(COALESCE(assistido_em, NOW())) FROM aulas_progresso WHERE usuario_id = :u AND assistido = 1 GROUP BY aula_id');
            $stA->execute([':u' => $usuarioId]);
            $assistidas = $stA->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($linhas as &$lin) {
                if (empty($lin['teoria_concluida_em']) && !empty($lin['aula_id']) && isset($assistidas[(int)$lin['aula_id']])) {
                    $lin['teoria_concluida_em'] = $assistidas[(int)$lin['aula_id']];
                }
            }
            unset($lin);
        } catch (Throwable $e) {
            // tabela de progresso ainda não existe: segue só com a marcação do plano
        }

        $itens = [];
        // Ciclo de matérias: a trava do Método META vale dentro de cada matéria.
        // Cada matéria tem o seu assunto em andamento; as matérias andam juntas.
        $cumpridoPorMateria = [];
        $atual = null;
        $atuais = [];
        $somaNiveis = 0;
        $dominados = 0;
        foreach ($linhas as $l) {
            $nivel = meta_nivel((int)$l['resolvidas'], (int)$l['acertos'], $cfg);
            $teoriaOk = !empty($l['teoria_concluida_em']) || empty($l['aula_id']);
            $liberadoAdmin = !empty($l['liberado_por_admin_em']);
            // Assunto com menos questões no banco do que o mínimo: exige as que existem (ninguém fica preso).
            $minItem = min($minQ, max(0, (int)($l['disponiveis'] ?? $minQ)));
            // Quem libera o próximo assunto são as questões; a aula é recomendada, não obrigatória.
            $cumprido = $liberadoAdmin || (int)$l['resolvidas'] >= $minItem;

            if ($liberadoAdmin) {
                // Exceção do mentor: o assunto fica aberto e conta como cumprido.
                $etapa = META_ETAPA_CONCLUIDO;
            } elseif (!($cumpridoPorMateria[(int)($l['materia_id'] ?? 0)] ?? true)) {
                $etapa = META_ETAPA_BLOQUEADO;
            } elseif ($cumprido) {
                $etapa = META_ETAPA_CONCLUIDO;
            } elseif (!$teoriaOk) {
                $etapa = META_ETAPA_TEORIA;
            } else {
                $etapa = META_ETAPA_QUESTOES;
            }

            $item = $l + [
                'etapa' => $etapa,
                'nivel_info' => $nivel,
                'teoria_ok' => $teoriaOk,
                'faltam_minimo' => max(0, $minItem - (int)$l['resolvidas']),
                'min_item' => $minItem,
                'liberado_admin' => $liberadoAdmin,
            ];
            $itens[] = $item;
            if ($etapa === META_ETAPA_TEORIA || $etapa === META_ETAPA_QUESTOES) {
                $atual = $atual ?? $item;
                $atuais[(int)($l['materia_id'] ?? 0)] = $atuais[(int)($l['materia_id'] ?? 0)] ?? $item;
            }
            $somaNiveis += $nivel['nivel'];
            if ($nivel['nivel'] >= 4) {
                $dominados++;
            }
            $mid = (int)($l['materia_id'] ?? 0);
            $cumpridoPorMateria[$mid] = ($cumpridoPorMateria[$mid] ?? true) && $cumprido;
        }

        $total = count($itens);
        return [
            'plano' => $plano,
            'itens' => $itens,
            'atual' => $atual,
            'atuais' => array_values($atuais),
            'nivel_geral' => $total ? round($somaNiveis / $total, 1) : 0.0,
            'dominio_pct' => $total ? (int)round($dominados * 100 / $total) : 0,
            'concluidos' => count(array_filter($itens, static fn(array $i): bool => $i['etapa'] === META_ETAPA_CONCLUIDO)),
            'config' => $cfg,
        ];
    }
}

if (!function_exists('meta_concluir_teoria')) {
    /** Aluno marca a teoria como concluída. Só vale para o assunto liberado. */
    function meta_concluir_teoria(PDO $pdo, int $usuarioId, int $cursoId, int $planoItemId): void
    {
        $estado = meta_estado_aluno($pdo, $usuarioId, $cursoId);
        foreach ($estado['itens'] as $item) {
            if ((int)$item['id'] === $planoItemId) {
                if ($item['etapa'] === META_ETAPA_BLOQUEADO) {
                    throw new RuntimeException('Este assunto ainda está bloqueado. Conclua o anterior primeiro.');
                }
                $stmt = $pdo->prepare('INSERT INTO meta_aluno_itens (usuario_id, plano_item_id, teoria_concluida_em) VALUES (:u, :i, NOW())
                                       ON DUPLICATE KEY UPDATE teoria_concluida_em = COALESCE(teoria_concluida_em, NOW())');
                $stmt->execute([':u' => $usuarioId, ':i' => $planoItemId]);
                return;
            }
        }
        throw new RuntimeException('Assunto não encontrado no seu plano.');
    }
}

if (!function_exists('meta_liberar_excecao')) {
    /** Mentor libera um assunto para o aluno, pulando a trava, com motivo registrado. */
    function meta_liberar_excecao(PDO $pdo, int $usuarioId, int $planoItemId, string $motivo): void
    {
        $motivo = trim($motivo) !== '' ? trim($motivo) : 'Liberado pelo mentor';
        $stmt = $pdo->prepare('INSERT INTO meta_aluno_itens (usuario_id, plano_item_id, liberado_por_admin_em, liberado_motivo) VALUES (:u, :i, NOW(), :m)
                               ON DUPLICATE KEY UPDATE liberado_por_admin_em = NOW(), liberado_motivo = :m2');
        $stmt->execute([':u' => $usuarioId, ':i' => $planoItemId, ':m' => $motivo, ':m2' => $motivo]);
    }
}

if (!function_exists('meta_curso_do_aluno')) {
    /** Primeiro curso ativo do aluno que tenha plano aprovado (ou o pedido, se for dele). */
    function meta_curso_do_aluno(PDO $pdo, int $usuarioId, int $cursoPedido = 0): int
    {
        $stmt = $pdo->prepare("SELECT uc.curso_id FROM usuario_cursos uc
                                 JOIN meta_planos p ON p.curso_id = uc.curso_id AND p.status = 'aprovado'
                                WHERE uc.usuario_id = :u AND uc.status IN ('ativo','pago','aprovado','liberado','matriculado')
                                ORDER BY (uc.curso_id = :pedido) DESC, uc.id ASC LIMIT 1");
        if ($cursoPedido <= 0) {
            // O cargo escolhido em "Meu concurso" define o curso (o edital é a base).
            try {
                $esc = $pdo->prepare('SELECT cg.curso_id FROM aluno_concurso ac JOIN concurso_cargos cg ON cg.id = ac.cargo_id WHERE ac.usuario_id = :u');
                $esc->execute([':u' => $usuarioId]);
                $cursoPedido = (int)$esc->fetchColumn();
            } catch (Throwable $e) {
            }
        }
        $stmt->execute([':u' => $usuarioId, ':pedido' => $cursoPedido]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('meta_desempenho_materias')) {
    /**
     * Ranking Individual do aluno: acerto por matéria (todas as questões que ele
     * resolveu: ciclo de estudo e Desafio do Dia), da mais forte para a mais fraca.
     * Também devolve o fator de ajuste do cronograma de cada matéria:
     *   acerto < 60% (com 10+ questões)  -> 1,3  (mais tempo onde ele erra)
     *   acerto >= 80% (com 10+ questões) -> 0,85 (manutenção do que já domina)
     */
    function meta_desempenho_materias(PDO $pdo, int $usuarioId): array
    {
        // Questões do banco + perguntas das paradas da Videoaula IA (as duas contam).
        try {
            $st = $pdo->prepare('SELECT t.materia_id, m.nome, COUNT(*) total, SUM(t.correta) acertos FROM (
                                    SELECT q.materia_id, r.correta FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.usuario_id = :u
                                    UNION ALL
                                    SELECT a.materia_id, v.correta FROM aula_videoaula_respostas v JOIN admin_curso_aulas a ON a.id = v.aula_id WHERE v.usuario_id = :u2
                                  ) t LEFT JOIN admin_materias m ON m.id = t.materia_id
                                  WHERE t.materia_id IS NOT NULL GROUP BY t.materia_id, m.nome');
            $st->execute([':u' => $usuarioId, ':u2' => $usuarioId]);
        } catch (PDOException $e) {
            $st = $pdo->prepare('SELECT q.materia_id, m.nome, COUNT(*) total, SUM(r.correta) acertos
                                   FROM banco_questoes_respostas_usuario r
                                   JOIN admin_banco_questoes q ON q.id = r.questao_id
                                   LEFT JOIN admin_materias m ON m.id = q.materia_id
                                  WHERE r.usuario_id = :u AND q.materia_id IS NOT NULL
                                  GROUP BY q.materia_id, m.nome');
            $st->execute([':u' => $usuarioId]);
        }
        $lista = [];
        foreach ($st->fetchAll() as $r) {
            $total = (int)$r['total'];
            $pct = $total ? (int)round((int)$r['acertos'] * 100 / $total) : 0;
            $fator = 1.0;
            $motivo = 'poucas questões ainda (mínimo 10 para ajustar)';
            if ($total >= 10) {
                if ($pct < 60) {
                    $fator = 1.3;
                    $motivo = 'acerto abaixo de 60%: +30% de tempo para reforçar';
                } elseif ($pct >= 80) {
                    $fator = 0.85;
                    $motivo = 'acerto de 80% ou mais: tempo de manutenção';
                } else {
                    $motivo = 'acerto entre 60% e 79%: ritmo normal';
                }
            }
            $lista[(int)$r['materia_id']] = ['materia_id' => (int)$r['materia_id'], 'materia' => (string)($r['nome'] ?? 'Sem matéria'), 'total' => $total, 'acertos' => (int)$r['acertos'], 'pct' => $pct, 'fator' => $fator, 'motivo' => $motivo];
        }
        uasort($lista, static fn(array $a, array $b): int => [$b['pct'], $b['total']] <=> [$a['pct'], $a['total']]);
        return $lista;
    }
}
