<?php
// includes/ia-classificar-lib.php
// Classificação automática do que é cadastrado (aulas, questões, materiais):
// a IA sugere curso, matéria, assunto do edital e dificuldade; o mentor confirma
// ou corrige. Cada confirmação/correção vira exemplo para as próximas
// sugestões ("IA treinada com o jeito do Projeto Gabaritando").
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';

const IA_CLASS_TIPOS = ['aula' => 'Aula', 'questao' => 'Questão', 'material' => 'Material'];

if (!function_exists('ia_class_bootstrap')) {
    function ia_class_bootstrap(PDO $pdo): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ia_classificacoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(20) NOT NULL,
            ref_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente_ia',
            amostra TEXT NULL,
            sugestao TEXT NULL,
            final TEXT NULL,
            confianca TINYINT UNSIGNED NULL,
            erro VARCHAR(500) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ia_class (tipo, ref_id),
            KEY idx_ia_class_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['materia_id', 'topico_id'] as $col) {
            if (!$pdo->query('SHOW COLUMNS FROM admin_material_arquivos LIKE ' . $pdo->quote($col))->fetch()) {
                $pdo->exec("ALTER TABLE admin_material_arquivos ADD COLUMN `$col` INT UNSIGNED NULL");
            }
        }
        $feito = true;
    }
}

if (!function_exists('ia_class_varrer')) {
    /** Põe na fila de classificação tudo que ainda não tem assunto e ainda não foi visto. */
    function ia_class_varrer(PDO $pdo): int
    {
        ia_class_bootstrap($pdo);
        $consultas = [
            'aula' => 'SELECT id FROM admin_curso_aulas WHERE topico_id IS NULL OR topico_id = 0',
            'questao' => 'SELECT id FROM admin_banco_questoes WHERE materia_topico_id IS NULL OR materia_topico_id = 0',
            'material' => 'SELECT id FROM admin_material_arquivos WHERE topico_id IS NULL OR topico_id = 0',
        ];
        $ins = $pdo->prepare("INSERT IGNORE INTO ia_classificacoes (tipo, ref_id) VALUES (:t, :r)");
        $n = 0;
        foreach ($consultas as $tipo => $sql) {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ins->execute([':t' => $tipo, ':r' => (int)$id]);
                $n += $ins->rowCount();
            }
        }
        return $n;
    }
}

if (!function_exists('ia_class_amostra')) {
    /** Texto que a IA lê para classificar o item. */
    function ia_class_amostra(PDO $pdo, string $tipo, int $id): array
    {
        $limpa = static fn(string $t): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)preg_replace('/<[^>]+>/', ' $0', $t)), ENT_QUOTES, 'UTF-8')));
        if ($tipo === 'aula') {
            $st = $pdo->prepare('SELECT a.titulo, a.descricao, a.curso_id, c.nome AS curso, m.titulo AS modulo FROM admin_curso_aulas a JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id WHERE a.id = :id');
            $st->execute([':id' => $id]);
            $r = $st->fetch();
            if (!$r) { return []; }
            return ['curso_fixo' => (int)$r['curso_id'], 'texto' => "AULA: {$r['titulo']}\nCURSO: {$r['curso']}\nMÓDULO: {$r['modulo']}\nCONTEÚDO: " . mb_substr($limpa((string)$r['descricao']), 0, 3000)];
        }
        if ($tipo === 'questao') {
            $st = $pdo->prepare('SELECT q.enunciado, q.explicacao, q.ano, b.nome AS banca FROM admin_banco_questoes q LEFT JOIN admin_bancas b ON b.id = q.banca_id WHERE q.id = :id');
            $st->execute([':id' => $id]);
            $r = $st->fetch();
            if (!$r) { return []; }
            $op = $pdo->prepare('SELECT letra, texto FROM admin_banco_questao_opcoes WHERE questao_id = :id ORDER BY ordem, id');
            $op->execute([':id' => $id]);
            $opcoes = implode("\n", array_map(static fn(array $o): string => $o['letra'] . ') ' . $limpa((string)$o['texto']), $op->fetchAll()));
            return ['curso_fixo' => 0, 'texto' => "QUESTÃO (banca: " . ($r['banca'] ?: '?') . ', ano: ' . ($r['ano'] ?: '?') . ")\n" . mb_substr($limpa((string)$r['enunciado']), 0, 2500) . "\n" . $opcoes];
        }
        $st = $pdo->prepare('SELECT a.titulo, a.arquivo_nome, p.titulo AS pasta, p.curso_id FROM admin_material_arquivos a LEFT JOIN admin_material_pastas p ON p.id = a.pasta_id WHERE a.id = :id');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        if (!$r) { return []; }
        return ['curso_fixo' => (int)($r['curso_id'] ?? 0), 'texto' => "MATERIAL: {$r['titulo']}\nARQUIVO: {$r['arquivo_nome']}\nPASTA: {$r['pasta']}"];
    }
}

if (!function_exists('ia_class_taxonomia')) {
    /** Cursos e edital (matérias > assuntos) com IDs, para a IA escolher. */
    function ia_class_taxonomia(PDO $pdo): array
    {
        $cursos = $pdo->query("SELECT id, nome FROM admin_cursos ORDER BY nome")->fetchAll();
        $materias = [];
        foreach ($pdo->query("SELECT m.id, m.nome, t.id AS tid, t.titulo FROM admin_materias m LEFT JOIN admin_materia_topicos t ON t.materia_id = m.id ORDER BY m.nome, t.ordem, t.titulo") as $l) {
            $materias[(int)$l['id']]['nome'] = (string)$l['nome'];
            if ($l['tid']) {
                $materias[(int)$l['id']]['assuntos'][(int)$l['tid']] = (string)$l['titulo'];
            }
        }
        return ['cursos' => $cursos, 'materias' => $materias];
    }
}

if (!function_exists('ia_class_exemplos')) {
    /** Últimas decisões do mentor: é com elas que a IA aprende o jeito da casa. */
    function ia_class_exemplos(PDO $pdo, int $limite = 20): string
    {
        $linhas = $pdo->query("SELECT amostra, final, status FROM ia_classificacoes WHERE status IN ('confirmada','corrigida') ORDER BY atualizado_em DESC LIMIT " . (int)$limite)->fetchAll();
        $txt = '';
        foreach ($linhas as $l) {
            $txt .= '- ' . mb_substr((string)$l['amostra'], 0, 220) . "\n  => " . $l['final'] . ($l['status'] === 'corrigida' ? ' (corrigido pelo mentor)' : '') . "\n";
        }
        return $txt;
    }
}

if (!function_exists('ia_class_processar_um')) {
    /** Classifica o próximo item pendente. Retorna null se não há nada. */
    function ia_class_processar_um(PDO $pdo): ?array
    {
        ia_class_bootstrap($pdo);
        $pdo = podcast_reconectar($pdo);
        $item = $pdo->query("SELECT * FROM ia_classificacoes WHERE status = 'pendente_ia' ORDER BY id LIMIT 1")->fetch();
        if (!$item) {
            return null;
        }
        $id = (int)$item['id'];
        try {
            $amostra = ia_class_amostra($pdo, (string)$item['tipo'], (int)$item['ref_id']);
            if (!$amostra) {
                $pdo->prepare("DELETE FROM ia_classificacoes WHERE id = :id")->execute([':id' => $id]);
                return ['id' => $id, 'ok' => true];
            }
            $tax = ia_class_taxonomia($pdo);
            $prompt = "Você classifica conteúdo do Projeto Gabaritando (cursinho para carreiras policiais e militares da Bahia).\n"
                . "Escolha, com base no EDITAL abaixo, a matéria e o assunto do item. Use SOMENTE IDs que existem na lista.\n"
                . "Se nenhum assunto servir, use topico_id null e sugira o nome em novo_assunto.\n"
                . ($amostra['curso_fixo'] ? "O curso já é conhecido (id {$amostra['curso_fixo']}); repita esse id.\n" : "Escolha também os cursos (editais) em que o item é útil.\n")
                . ($item['tipo'] === 'questao' ? "Dê a dificuldade: facil, medio ou dificil.\n" : '')
                . "Responda SOMENTE em JSON: {\"curso_ids\":[..],\"materia_id\":n|null,\"topico_id\":n|null,\"novo_assunto\":\"\"|null,\"dificuldade\":\"facil|medio|dificil|null\",\"confianca\":0-100,\"motivo\":\"1 frase\"}\n\n"
                . "CURSOS: " . json_encode($tax['cursos'], JSON_UNESCAPED_UNICODE) . "\n"
                . "EDITAL (matéria > assuntos): " . json_encode($tax['materias'], JSON_UNESCAPED_UNICODE) . "\n\n";
            $exemplos = ia_class_exemplos($pdo);
            if ($exemplos !== '') {
                $prompt .= "DECISÕES ANTERIORES DO MENTOR (siga este padrão):\n" . $exemplos . "\n";
            }
            $prompt .= "ITEM A CLASSIFICAR:\n" . $amostra['texto'];
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]], 90);
            $pdo = podcast_reconectar($pdo);
            $j = json_decode((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
            if (!is_array($j)) {
                throw new RuntimeException('A IA não devolveu uma classificação válida.');
            }
            // Só aceita IDs que existem de verdade.
            $mid = (int)($j['materia_id'] ?? 0);
            $tid = (int)($j['topico_id'] ?? 0);
            if ($mid && !isset($tax['materias'][$mid])) { $mid = 0; $tid = 0; }
            if ($tid && !isset($tax['materias'][$mid]['assuntos'][$tid])) { $tid = 0; }
            $idsCursos = array_column($tax['cursos'], 'id');
            $cursos = $amostra['curso_fixo'] ? [$amostra['curso_fixo']] : array_values(array_filter(array_map('intval', (array)($j['curso_ids'] ?? [])), static fn(int $c): bool => in_array($c, array_map('intval', $idsCursos), true)));
            $sug = ['curso_ids' => $cursos, 'materia_id' => $mid ?: null, 'topico_id' => $tid ?: null, 'novo_assunto' => trim((string)($j['novo_assunto'] ?? '')) ?: null,
                'dificuldade' => in_array($j['dificuldade'] ?? '', ['facil', 'medio', 'dificil'], true) ? $j['dificuldade'] : null, 'motivo' => mb_substr((string)($j['motivo'] ?? ''), 0, 300)];
            $pdo->prepare("UPDATE ia_classificacoes SET status = 'sugerida', amostra = :a, sugestao = :s, confianca = :c, erro = NULL WHERE id = :id")
                ->execute([':a' => mb_substr($amostra['texto'], 0, 4000), ':s' => json_encode($sug, JSON_UNESCAPED_UNICODE), ':c' => max(0, min(100, (int)($j['confianca'] ?? 0))), ':id' => $id]);
            return ['id' => $id, 'ok' => true];
        } catch (Throwable $e) {
            podcast_reconectar($pdo)->prepare("UPDATE ia_classificacoes SET status = 'erro', erro = :e WHERE id = :id")->execute([':e' => mb_substr($e->getMessage(), 0, 500), ':id' => $id]);
            return ['id' => $id, 'ok' => false, 'erro' => $e->getMessage()];
        }
    }
}

if (!function_exists('ia_class_aplicar')) {
    /**
     * Mentor confirmou (ou corrigiu): grava no item e guarda como exemplo.
     * $final: curso_ids[], materia_id, topico_id, dificuldade, novo_assunto (cria se pedido).
     */
    function ia_class_aplicar(PDO $pdo, int $classId, array $final, bool $corrigido): void
    {
        $st = $pdo->prepare('SELECT * FROM ia_classificacoes WHERE id = :id');
        $st->execute([':id' => $classId]);
        $c = $st->fetch();
        if (!$c) {
            throw new RuntimeException('Classificação não encontrada.');
        }
        $mid = (int)($final['materia_id'] ?? 0);
        $tid = (int)($final['topico_id'] ?? 0);
        $novo = trim((string)($final['novo_assunto'] ?? ''));
        if (!$tid && $mid && $novo !== '') {
            $pdo->prepare("INSERT INTO admin_materia_topicos (materia_id, titulo, status, ordem) VALUES (:m, :t, 'ativo', 999)")->execute([':m' => $mid, ':t' => mb_substr($novo, 0, 180)]);
            $tid = (int)$pdo->lastInsertId();
        }
        $dif = in_array($final['dificuldade'] ?? '', ['facil', 'medio', 'dificil'], true) ? $final['dificuldade'] : null;
        $cursos = array_values(array_filter(array_map('intval', (array)($final['curso_ids'] ?? []))));
        $ref = (int)$c['ref_id'];
        if ($c['tipo'] === 'aula') {
            $pdo->prepare('UPDATE admin_curso_aulas SET materia_id = :m, topico_id = :t WHERE id = :id')->execute([':m' => $mid ?: null, ':t' => $tid ?: null, ':id' => $ref]);
        } elseif ($c['tipo'] === 'questao') {
            $sql = 'UPDATE admin_banco_questoes SET materia_id = :m, materia_topico_id = :t' . ($dif ? ', dificuldade = :d' : '') . ($cursos ? ', curso_id = :c' : '') . ' WHERE id = :id';
            $p = [':m' => $mid ?: null, ':t' => $tid ?: null, ':id' => $ref];
            if ($dif) { $p[':d'] = $dif; }
            if ($cursos) { $p[':c'] = $cursos[0]; }
            $pdo->prepare($sql)->execute($p);
        } else {
            $pdo->prepare('UPDATE admin_material_arquivos SET materia_id = :m, topico_id = :t WHERE id = :id')->execute([':m' => $mid ?: null, ':t' => $tid ?: null, ':id' => $ref]);
        }
        $gravar = ['curso_ids' => $cursos, 'materia_id' => $mid ?: null, 'topico_id' => $tid ?: null, 'dificuldade' => $dif];
        $pdo->prepare('UPDATE ia_classificacoes SET status = :s, final = :f WHERE id = :id')
            ->execute([':s' => $corrigido ? 'corrigida' : 'confirmada', ':f' => json_encode($gravar, JSON_UNESCAPED_UNICODE), ':id' => $classId]);
    }
}

if (!function_exists('ia_class_estatisticas')) {
    /** Quanto a IA já acerta: confirmadas sem correção / total revisado. */
    function ia_class_estatisticas(PDO $pdo): array
    {
        ia_class_bootstrap($pdo);
        $r = ['pendente_ia' => 0, 'sugerida' => 0, 'confirmada' => 0, 'corrigida' => 0, 'erro' => 0];
        foreach ($pdo->query('SELECT status, COUNT(*) n FROM ia_classificacoes GROUP BY status') as $l) {
            $r[(string)$l['status']] = (int)$l['n'];
        }
        $rev = $r['confirmada'] + $r['corrigida'];
        $r['acerto'] = $rev ? (int)round($r['confirmada'] * 100 / $rev) : null;
        return $r;
    }
}
