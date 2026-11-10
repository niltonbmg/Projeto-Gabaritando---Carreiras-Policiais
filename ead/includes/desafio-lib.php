<?php
// includes/desafio-lib.php
// Desafio de Questões do Dia do Projeto Gabaritando: 20 questões por dia,
// escolhidas pela IA (ou pelas mesmas regras sem IA), ranking do dia e do mês,
// ganhador do mês com brinde e mensagem pronta para o WhatsApp.
//
// Didática da escolha: mistura matérias (mais questões das matérias mais
// cobradas), ~30% fáceis / 50% médias / 20% difíceis, sem repetir questões
// dos últimos 30 dias.
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';

const DESAFIO_QTD = 20;
// Data de estreia: antes dela os alunos veem só o aviso (o admin pode testar).
const DESAFIO_INICIO = '2026-12-01';
const DESAFIO_MIX = ['facil' => 0.3, 'medio' => 0.5, 'dificil' => 0.2];

if (!function_exists('desafio_bootstrap')) {
    function desafio_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS desafios (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL UNIQUE,
            questoes TEXT NOT NULL,
            chamada VARCHAR(300) NULL,
            escolhido_por VARCHAR(20) NOT NULL DEFAULT 'regras',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS desafio_tentativas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            desafio_id INT UNSIGNED NOT NULL,
            usuario_id BIGINT UNSIGNED NOT NULL,
            iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finalizado_em DATETIME NULL,
            acertos TINYINT UNSIGNED NOT NULL DEFAULT 0,
            total TINYINT UNSIGNED NOT NULL DEFAULT 0,
            tempo_seg INT UNSIGNED NULL,
            respostas TEXT NULL,
            UNIQUE KEY uq_desafio_usuario (desafio_id, usuario_id),
            KEY idx_desafio_rank (desafio_id, finalizado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS desafio_premios (
            mes CHAR(7) NOT NULL PRIMARY KEY,
            premio VARCHAR(500) NULL,
            ganhador_id BIGINT UNSIGNED NULL,
            definido_em DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $feito = true;
    }
}

if (!function_exists('desafio_candidatas')) {
    /** Questões ativas, com alternativas, que não apareceram nos últimos 30 dias. */
    function desafio_candidatas(PDO $pdo, string $data): array
    {
        $recentes = [];
        $st = $pdo->prepare('SELECT questoes FROM desafios WHERE data >= DATE_SUB(:d, INTERVAL 30 DAY) AND data < :d2');
        $st->execute([':d' => $data, ':d2' => $data]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $j) {
            foreach ((array)json_decode((string)$j, true) as $id) {
                $recentes[(int)$id] = true;
            }
        }
        $lista = $pdo->query("SELECT q.id, q.materia_id, COALESCE(q.dificuldade, 'medio') AS dificuldade, LEFT(q.enunciado, 160) AS trecho, m.nome AS materia
                                FROM admin_banco_questoes q LEFT JOIN admin_materias m ON m.id = q.materia_id
                               WHERE q.status = 'ativo'
                                 AND EXISTS (SELECT 1 FROM admin_banco_questao_opcoes o WHERE o.questao_id = q.id AND o.correta = 1)")->fetchAll();
        return array_values(array_filter($lista, static fn(array $q): bool => !isset($recentes[(int)$q['id']])));
    }
}

if (!function_exists('desafio_escolher_por_regras')) {
    /** Escolha sem IA: proporção de dificuldade e rodízio entre matérias (pesadas pela recorrência). */
    function desafio_escolher_por_regras(array $cand, array $pesoMateria): array
    {
        shuffle($cand);
        $porDif = ['facil' => [], 'medio' => [], 'dificil' => []];
        foreach ($cand as $q) {
            $porDif[$q['dificuldade']][] = $q;
        }
        $escolhidas = [];
        $usadasMateria = [];
        foreach (DESAFIO_MIX as $dif => $frac) {
            $meta = (int)round(DESAFIO_QTD * $frac);
            // Ordena por "quem está mais atrás em relação ao peso".
            for ($k = 0; $k < $meta && $porDif[$dif]; $k++) {
                usort($porDif[$dif], static function (array $a, array $b) use ($usadasMateria, $pesoMateria): int {
                    $na = ($usadasMateria[(int)$a['materia_id']] ?? 0) / ($pesoMateria[(int)$a['materia_id']] ?? 1);
                    $nb = ($usadasMateria[(int)$b['materia_id']] ?? 0) / ($pesoMateria[(int)$b['materia_id']] ?? 1);
                    return $na <=> $nb;
                });
                $q = array_shift($porDif[$dif]);
                $escolhidas[] = (int)$q['id'];
                $usadasMateria[(int)$q['materia_id']] = ($usadasMateria[(int)$q['materia_id']] ?? 0) + 1;
            }
        }
        // Completa com o que sobrou se faltou alguma dificuldade.
        $resto = array_merge($porDif['medio'], $porDif['facil'], $porDif['dificil']);
        while (count($escolhidas) < DESAFIO_QTD && $resto) {
            $escolhidas[] = (int)array_shift($resto)['id'];
        }
        return $escolhidas;
    }
}

if (!function_exists('desafio_gerar')) {
    /** Cria o desafio do dia (se ainda não existe). Retorna a linha do desafio. */
    function desafio_gerar(PDO $pdo, string $data, bool $refazer = false): array
    {
        desafio_bootstrap($pdo);
        $st = $pdo->prepare('SELECT * FROM desafios WHERE data = :d');
        $st->execute([':d' => $data]);
        $existe = $st->fetch();
        if ($existe && !$refazer) {
            return $existe;
        }
        // Na virada vários alunos chegam juntos: só um monta, os outros esperam e usam o mesmo.
        $trava = 'gab_desafio_' . $data;
        $pdo->query('SELECT GET_LOCK(' . $pdo->quote($trava) . ', 45)')->fetchColumn();
        try {
            if (!$refazer) {
                $st->execute([':d' => $data]);
                if ($existe = $st->fetch()) {
                    return $existe;
                }
            }
            return desafio_gerar_montar($pdo, $data, $refazer, $st);
        } finally {
            try {
                $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($trava) . ')');
            } catch (Throwable $e) {
            }
        }
    }

    function desafio_gerar_montar(PDO $pdo, string $data, bool $refazer, PDOStatement $st): array
    {
        $cand = desafio_candidatas($pdo, $data);
        if (count($cand) < 5) {
            throw new RuntimeException('O banco tem poucas questões ativas com gabarito (' . count($cand) . '). Cadastre mais questões para o desafio.');
        }
        // Peso da matéria = quantos assuntos ela tem nos editais (proxy de recorrência).
        $pesoMateria = [];
        try {
            foreach ($pdo->query('SELECT materia_id, COUNT(*) n FROM edital_itens GROUP BY materia_id') as $r) {
                $pesoMateria[(int)$r['materia_id']] = max(1, (int)$r['n']);
            }
        } catch (Throwable $e) {
        }
        $ids = [];
        $chamada = null;
        $por = 'regras';
        if (gab_env('GEMINI_API_KEY', '') !== '') {
            try {
                shuffle($cand);
                $amostra = array_slice($cand, 0, 120);
                $prompt = "Você monta o DESAFIO DE QUESTÕES DO DIA do Projeto Gabaritando (carreiras policiais e militares da Bahia).\n"
                    . "Escolha exatamente " . min(DESAFIO_QTD, count($amostra)) . " questões da lista, seguindo a didática:\n"
                    . "- misture as matérias, com mais questões das matérias de maior peso (pesos: " . json_encode($pesoMateria) . ");\n"
                    . "- cerca de 30% fáceis, 50% médias e 20% difíceis;\n"
                    . "- evite duas questões quase iguais; comece com uma fácil para engajar.\n"
                    . "Escreva também uma CHAMADA curta e motivadora para o grupo de WhatsApp (até 140 caracteres, tom 'Fala, combatente!').\n"
                    . "Responda SOMENTE em JSON: {\"ids\":[...],\"chamada\":\"...\"}\n\n"
                    . 'QUESTÕES: ' . json_encode(array_map(static fn(array $q): array => ['id' => (int)$q['id'], 'materia' => $q['materia'], 'dif' => $q['dificuldade'], 'trecho' => $q['trecho']], $amostra), JSON_UNESCAPED_UNICODE);
                $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.6]], 60);
                $pdo = podcast_reconectar($pdo);
                $j = json_decode((string)($r['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
                $validos = array_flip(array_map('intval', array_column($amostra, 'id')));
                $ids = array_values(array_unique(array_filter(array_map('intval', (array)($j['ids'] ?? [])), static fn(int $i): bool => isset($validos[$i]))));
                $chamada = mb_substr(trim((string)($j['chamada'] ?? '')), 0, 300) ?: null;
                $por = count($ids) >= min(DESAFIO_QTD, count($amostra)) - 2 ? 'ia' : 'regras';
            } catch (Throwable $e) {
                $ids = [];
            }
        }
        if (count($ids) < min(DESAFIO_QTD, count($cand)) - 2) {
            $ids = desafio_escolher_por_regras($cand, $pesoMateria);
            $por = 'regras';
        }
        $ids = array_slice($ids, 0, DESAFIO_QTD);
        $pdo->prepare('INSERT INTO desafios (data, questoes, chamada, escolhido_por) VALUES (:d, :q, :c, :p)'
                       . ($refazer ? ' ON DUPLICATE KEY UPDATE questoes = VALUES(questoes), chamada = VALUES(chamada), escolhido_por = VALUES(escolhido_por)'
                                   : ' ON DUPLICATE KEY UPDATE id = id')) // sem "trocar questões" do admin, nunca sobrescreve
            ->execute([':d' => $data, ':q' => json_encode($ids), ':c' => $chamada, ':p' => $por]);
        $st->execute([':d' => $data]);
        return $st->fetch();
    }
}

if (!function_exists('desafio_questoes')) {
    /** Questões do desafio com alternativas, na ordem escolhida. */
    function desafio_questoes(PDO $pdo, array $desafio): array
    {
        // As 20 questões do dia são as mesmas para todos: lê do banco uma vez a cada 5 min.
        require_once __DIR__ . '/cache-lib.php';
        return gab_cache('desafio-q-' . (int)$desafio['id'] . '-' . md5((string)$desafio['questoes']), 300, static fn(): array => desafio_questoes_banco($pdo, $desafio));
    }

    function desafio_questoes_banco(PDO $pdo, array $desafio): array
    {
        $ids = array_map('intval', (array)json_decode((string)$desafio['questoes'], true));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $qs = [];
        foreach ($pdo->query("SELECT q.id, q.enunciado, q.explicacao, q.dificuldade, m.nome AS materia FROM admin_banco_questoes q LEFT JOIN admin_materias m ON m.id = q.materia_id WHERE q.id IN ($in)") as $q) {
            $qs[(int)$q['id']] = $q + ['opcoes' => []];
        }
        foreach ($pdo->query("SELECT id, questao_id, letra, texto, correta FROM admin_banco_questao_opcoes WHERE questao_id IN ($in) ORDER BY ordem, letra, id") as $o) {
            if (isset($qs[(int)$o['questao_id']])) {
                $qs[(int)$o['questao_id']]['opcoes'][] = $o;
            }
        }
        $ordenadas = [];
        foreach ($ids as $id) {
            if (isset($qs[$id])) {
                $ordenadas[] = $qs[$id];
            }
        }
        return $ordenadas;
    }
}

if (!function_exists('desafio_iniciar')) {
    /** Marca o início (o tempo conta a partir da primeira abertura). */
    function desafio_iniciar(PDO $pdo, int $desafioId, int $usuarioId): array
    {
        $pdo->prepare('INSERT IGNORE INTO desafio_tentativas (desafio_id, usuario_id) VALUES (:d, :u)')->execute([':d' => $desafioId, ':u' => $usuarioId]);
        $st = $pdo->prepare('SELECT * FROM desafio_tentativas WHERE desafio_id = :d AND usuario_id = :u');
        $st->execute([':d' => $desafioId, ':u' => $usuarioId]);
        return $st->fetch();
    }
}

if (!function_exists('desafio_finalizar')) {
    /**
     * Corrige, grava a tentativa e registra cada questão no histórico do aluno
     * (banco_questoes_respostas_usuario), para contar no cronograma e no mentor.
     */
    function desafio_finalizar(PDO $pdo, array $desafio, int $usuarioId, array $marcadas): array
    {
        $t = desafio_iniciar($pdo, (int)$desafio['id'], $usuarioId);
        if (!empty($t['finalizado_em'])) {
            return $t; // uma tentativa por dia
        }
        $qs = desafio_questoes($pdo, $desafio);
        $acertos = 0;
        $resp = [];
        $hist = $pdo->prepare('INSERT INTO banco_questoes_respostas_usuario (usuario_id, questao_id, opcao_id, correta, respondido_em) VALUES (:u, :q, :o, :c, NOW())
                               ON DUPLICATE KEY UPDATE opcao_id = VALUES(opcao_id), correta = VALUES(correta), respondido_em = NOW()');
        foreach ($qs as $q) {
            $escolhida = (int)($marcadas[(int)$q['id']] ?? 0);
            $correta = 0;
            $valida = false;
            foreach ($q['opcoes'] as $o) {
                if ((int)$o['id'] === $escolhida) {
                    $valida = true;
                    $correta = (int)$o['correta'];
                }
            }
            $acertos += $correta;
            $resp[(int)$q['id']] = $valida ? $escolhida : 0;
            if ($valida) {
                try {
                    $hist->execute([':u' => $usuarioId, ':q' => (int)$q['id'], ':o' => $escolhida, ':c' => $correta]);
                } catch (Throwable $e) {
                    // histórico é complementar; não impede o desafio
                }
            }
        }
        $tempo = max(1, time() - strtotime((string)$t['iniciado_em']));
        $pdo->prepare('UPDATE desafio_tentativas SET finalizado_em = NOW(), acertos = :a, total = :t, tempo_seg = :s, respostas = :r WHERE id = :id AND finalizado_em IS NULL')
            ->execute([':a' => $acertos, ':t' => count($qs), ':s' => $tempo, ':r' => json_encode($resp), ':id' => (int)$t['id']]);
        return desafio_iniciar($pdo, (int)$desafio['id'], $usuarioId);
    }
}

if (!function_exists('desafio_nome_publico')) {
    /** "Maria Souza Lima" -> "Maria S." (ranking sem expor o nome completo). */
    function desafio_nome_publico(string $nome, int $usuarioId = 0): string
    {
        // LGPD: o aluno pode escolher não aparecer pelo nome no ranking (Meus dados).
        if ($usuarioId > 0 && function_exists('db')) {
            require_once __DIR__ . '/lgpd-lib.php';
            if (!lgpd_ranking_publico(db(), $usuarioId)) {
                return 'Combatente anônimo';
            }
        }
        $p = preg_split('/\s+/', trim($nome)) ?: [];
        if (!$p || $p[0] === '') {
            return 'Combatente';
        }
        return $p[0] . (count($p) > 1 ? ' ' . mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '');
    }
}

if (!function_exists('desafio_ranking_dia')) {
    function desafio_ranking_dia(PDO $pdo, int $desafioId, int $limite = 50): array
    {
        // No pico (meia-noite), centenas de alunos pedem o mesmo ranking: recalcula no máximo a cada 30 s.
        require_once __DIR__ . '/cache-lib.php';
        return gab_cache('rank-dia-' . $desafioId . '-' . $limite, 30, static function () use ($pdo, $desafioId, $limite): array {
        $st = $pdo->prepare('SELECT t.usuario_id, t.acertos, t.total, t.tempo_seg, u.nome FROM desafio_tentativas t JOIN usuarios u ON u.id = t.usuario_id
                              WHERE t.desafio_id = :d AND t.finalizado_em IS NOT NULL ORDER BY t.acertos DESC, t.tempo_seg ASC, t.finalizado_em ASC LIMIT ' . (int)$limite);
        $st->execute([':d' => $desafioId]);
        return $st->fetchAll();
        });
    }
}

if (!function_exists('desafio_ranking_mes')) {
    /** Ranking do mês: soma de acertos; desempate por dias participados e menor tempo total. */
    function desafio_ranking_mes(PDO $pdo, string $mes, int $limite = 50): array
    {
        require_once __DIR__ . '/cache-lib.php';
        return gab_cache('rank-mes-' . $mes . '-' . $limite, 60, static function () use ($pdo, $mes, $limite): array {
        $st = $pdo->prepare("SELECT t.usuario_id, u.nome, SUM(t.acertos) acertos, COUNT(*) dias, SUM(t.tempo_seg) tempo
                               FROM desafio_tentativas t JOIN desafios d ON d.id = t.desafio_id JOIN usuarios u ON u.id = t.usuario_id
                              WHERE t.finalizado_em IS NOT NULL AND DATE_FORMAT(d.data, '%Y-%m') = :m
                              GROUP BY t.usuario_id, u.nome ORDER BY acertos DESC, dias DESC, tempo ASC LIMIT " . (int)$limite);
        $st->execute([':m' => $mes]);
        return $st->fetchAll();
        });
    }
}

if (!function_exists('desafio_premio')) {
    function desafio_premio(PDO $pdo, string $mes): array
    {
        $st = $pdo->prepare('SELECT p.*, u.nome AS ganhador FROM desafio_premios p LEFT JOIN usuarios u ON u.id = p.ganhador_id WHERE p.mes = :m');
        $st->execute([':m' => $mes]);
        return $st->fetch() ?: ['mes' => $mes, 'premio' => null, 'ganhador_id' => null, 'ganhador' => null];
    }
}

if (!function_exists('desafio_link')) {
    function desafio_link(string $data): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'projetogabaritando.com');
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $host === 'projetogabaritando.com' ? 'https' : 'http';
        return $esquema . '://' . $host . '/ead/desafio.php?dia=' . $data;
    }
}

if (!function_exists('desafio_mensagem_whatsapp')) {
    /** Texto pronto para colar no grupo dos alunos. */
    function desafio_mensagem_whatsapp(PDO $pdo, array $desafio): string
    {
        $data = (string)$desafio['data'];
        $ontem = (new DateTimeImmutable($data))->modify('-1 day')->format('Y-m-d');
        $st = $pdo->prepare('SELECT id FROM desafios WHERE data = :d');
        $st->execute([':d' => $ontem]);
        $idOntem = (int)$st->fetchColumn();
        $mes = substr($data, 0, 7);
        $premio = desafio_premio($pdo, $mes);
        $dt = new DateTimeImmutable($data);
        $msg = "🎯 *DESAFIO DO DIA – PROJETO GABARITANDO*\n📅 " . $dt->format('d/m') . "\n\n"
            . ($desafio['chamada'] ? $desafio['chamada'] . "\n\n" : "Fala, combatente! 20 questões te esperando. Bora pro ranking?\n\n")
            . "✅ 20 questões no padrão da sua prova\n⏱️ O tempo conta no desempate\n🏆 Ranking do dia e do mês\n\n"
            . "👉 " . desafio_link($data) . "\n";
        if ($idOntem) {
            $top = desafio_ranking_dia($pdo, $idOntem, 3);
            if ($top) {
                $msg .= "\n🥇🥈🥉 *Pódio de ontem*\n";
                foreach ($top as $k => $r) {
                    $msg .= ['🥇', '🥈', '🥉'][$k] . ' ' . desafio_nome_publico((string)$r['nome'], (int)($r['usuario_id'] ?? 0)) . ' – ' . (int)$r['acertos'] . '/' . (int)$r['total'] . "\n";
                }
            }
        }
        if (!empty($premio['premio'])) {
            $msg .= "\n🎁 *Prêmio do mês:* " . $premio['premio'] . "\n";
        }
        return $msg . "\nFé na missão: a farda é questão de tempo! 💪";
    }
}
