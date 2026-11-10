<?php
// admin-cronograma.php
// Criação de cronograma por produto/curso, matéria, tópico, ordem e tempo de vídeo, PDF e questões.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-banco-questoes-lib.php';

if (!function_exists('admin_crono_column')) {
    function admin_crono_column(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
            $stmt->execute([':t' => $table, ':c' => $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('admin_crono_exec')) {
    function admin_crono_exec(PDO $pdo, string $sql): void
    {
        try { $pdo->exec($sql); } catch (Throwable $e) { /* banco antigo/índice existente */ }
    }
}

if (!function_exists('admin_crono_bootstrap')) {
    function admin_crono_bootstrap(PDO $pdo): void
    {
        admin_cursos_bootstrap($pdo);
        admin_bq_bootstrap($pdo);

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cronogramas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(190) NOT NULL,
            data_inicio DATE NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_cronogramas_curso (curso_id),
            KEY idx_admin_cronogramas_data (data_inicio),
            KEY idx_admin_cronogramas_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cronograma_itens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cronograma_id INT UNSIGNED NULL,
            curso_id INT UNSIGNED NULL,
            materia_id INT UNSIGNED NULL,
            topico_id INT UNSIGNED NULL,
            modulo_id INT UNSIGNED NULL,
            aula_id INT UNSIGNED NULL,
            titulo VARCHAR(190) NOT NULL,
            materia VARCHAR(190) NULL,
            descricao TEXT NULL,
            data_estudo DATE NOT NULL,
            hora_inicio TIME NULL,
            duracao_minutos INT UNSIGNED NOT NULL DEFAULT 60,
            video_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            pdf_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            questoes_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            cor VARCHAR(30) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cronograma_data (data_estudo),
            KEY idx_cronograma_curso (curso_id),
            KEY idx_cronograma_materia (materia_id),
            KEY idx_cronograma_topico (topico_id),
            KEY idx_cronograma_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $colunas = [
            'cronograma_id' => 'INT UNSIGNED NULL AFTER id',
            'curso_id' => 'INT UNSIGNED NULL AFTER cronograma_id',
            'materia_id' => 'INT UNSIGNED NULL AFTER curso_id',
            'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
            'modulo_id' => 'INT UNSIGNED NULL AFTER topico_id',
            'aula_id' => 'INT UNSIGNED NULL AFTER modulo_id',
            'hora_inicio' => 'TIME NULL AFTER data_estudo',
            'duracao_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 60 AFTER hora_inicio',
            'video_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER duracao_minutos',
            'pdf_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos',
            'questoes_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos',
            'ordem' => 'INT NOT NULL DEFAULT 0 AFTER questoes_minutos',
            'cor' => 'VARCHAR(30) NULL AFTER ordem',
        ];
        foreach ($colunas as $coluna => $def) {
            if (!admin_crono_column($pdo, 'admin_cronograma_itens', $coluna)) {
                admin_crono_exec($pdo, "ALTER TABLE admin_cronograma_itens ADD COLUMN {$coluna} {$def}");
            }
        }
        admin_crono_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_admin (cronograma_id)');
        admin_crono_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_materia (materia_id)');
        admin_crono_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_topico (topico_id)');
        admin_crono_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_ordem (ordem)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_metas_diarias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cronograma_item_id INT UNSIGNED NULL,
            titulo VARCHAR(190) NOT NULL,
            materia VARCHAR(190) NULL,
            materia_id INT UNSIGNED NULL,
            topico_id INT UNSIGNED NULL,
            descricao TEXT NULL,
            curso_id INT UNSIGNED NULL,
            modulo_id INT UNSIGNED NULL,
            aula_id INT UNSIGNED NULL,
            usuario_id INT UNSIGNED NULL,
            tempo_minutos INT UNSIGNED NOT NULL DEFAULT 60,
            video_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            pdf_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            questoes_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            data_meta DATE NULL,
            video_link VARCHAR(255) NULL,
            banco_questoes_link VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_metas_data (data_meta),
            KEY idx_admin_metas_curso (curso_id),
            KEY idx_admin_metas_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'cronograma_item_id' => 'INT UNSIGNED NULL AFTER id',
            'materia_id' => 'INT UNSIGNED NULL AFTER materia',
            'topico_id' => 'INT UNSIGNED NULL AFTER materia_id',
            'curso_id' => 'INT UNSIGNED NULL AFTER descricao',
            'modulo_id' => 'INT UNSIGNED NULL AFTER curso_id',
            'aula_id' => 'INT UNSIGNED NULL AFTER modulo_id',
            'usuario_id' => 'INT UNSIGNED NULL AFTER aula_id',
            'tempo_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 60 AFTER usuario_id',
            'video_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER tempo_minutos',
            'pdf_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos',
            'questoes_minutos' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos',
            'data_meta' => 'DATE NULL AFTER questoes_minutos',
            'video_link' => 'VARCHAR(255) NULL AFTER data_meta',
            'banco_questoes_link' => 'VARCHAR(255) NULL AFTER video_link',
        ] as $coluna => $def) {
            if (!admin_crono_column($pdo, 'admin_metas_diarias', $coluna)) {
                admin_crono_exec($pdo, "ALTER TABLE admin_metas_diarias ADD COLUMN {$coluna} {$def}");
            }
        }
        admin_crono_exec($pdo, 'ALTER TABLE admin_metas_diarias ADD INDEX idx_admin_metas_item (cronograma_item_id)');
        admin_crono_exec($pdo, 'ALTER TABLE admin_metas_diarias ADD INDEX idx_admin_metas_materia (materia_id)');
        admin_crono_exec($pdo, 'ALTER TABLE admin_metas_diarias ADD INDEX idx_admin_metas_topico (topico_id)');
    }
}

if (!function_exists('admin_crono_esc')) {
    function admin_crono_esc(?string $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_crono_int')) {
    function admin_crono_int(mixed $value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('admin_crono_minutos')) {
    function admin_crono_minutos(mixed $value): int
    {
        $minutos = (int)$value;
        if ($minutos < 5) { $minutos = 5; }
        if ($minutos > 1440) { $minutos = 1440; }
        return $minutos;
    }
}

if (!function_exists('admin_crono_minutos_ou_zero')) {
    function admin_crono_minutos_ou_zero(mixed $value): int
    {
        $minutos = (int)$value;
        if ($minutos < 0) { $minutos = 0; }
        if ($minutos > 1440) { $minutos = 1440; }
        return $minutos;
    }
}

if (!function_exists('admin_crono_data')) {
    function admin_crono_data(string $value): string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $dt ? $dt->format('Y-m-d') : date('Y-m-d');
    }
}

if (!function_exists('admin_crono_periodo')) {
    function admin_crono_periodo(?string $data): string
    {
        if (!$data) { return '-'; }
        try { return (new DateTimeImmutable($data))->format('d/m/Y'); } catch (Throwable) { return $data; }
    }
}



if (!function_exists('admin_crono_buscar_aula_para_topico')) {
    function admin_crono_buscar_aula_para_topico(PDO $pdo, int $cursoId, int $topicoId, string $tituloTopico): array
    {
        $resultado = ['modulo_id' => null, 'aula_id' => null, 'link' => 'curso-modulos.php?curso_id=' . $cursoId];
        if ($cursoId <= 0) {
            return $resultado;
        }

        $termos = [];
        $tituloTopico = trim($tituloTopico);
        if ($tituloTopico !== '') {
            $termos[] = $tituloTopico;
        }

        try {
            $stmtTopico = $pdo->prepare('SELECT titulo FROM admin_materia_topicos WHERE id = ? LIMIT 1');
            $stmtTopico->execute([$topicoId]);
            $tituloBanco = trim((string)$stmtTopico->fetchColumn());
            if ($tituloBanco !== '') {
                $termos[] = $tituloBanco;
            }
        } catch (Throwable $e) {
            // Sem quebrar o salvamento do cronograma.
        }

        foreach (array_unique($termos) as $termo) {
            try {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termo) . '%';
                $stmt = $pdo->prepare("\n                    SELECT a.id AS aula_id, a.modulo_id\n                    FROM admin_curso_aulas a\n                    LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                    WHERE a.curso_id = ?\n                      AND (a.titulo LIKE ? OR a.descricao LIKE ? OR m.titulo LIKE ? OR m.descricao LIKE ?)\n                    ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                    LIMIT 1\n                ");
                $stmt->execute([$cursoId, $like, $like, $like, $like]);
                $aula = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($aula && (int)$aula['aula_id'] > 0) {
                    return [
                        'modulo_id' => (int)$aula['modulo_id'],
                        'aula_id' => (int)$aula['aula_id'],
                        'link' => 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'],
                    ];
                }
            } catch (Throwable $e) {
                // Continua para o fallback.
            }
        }

        try {
            $stmt = $pdo->prepare("\n                SELECT a.id AS aula_id, a.modulo_id\n                FROM admin_curso_aulas a\n                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                WHERE a.curso_id = ?\n                ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                LIMIT 1\n            ");
            $stmt->execute([$cursoId]);
            $aula = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aula && (int)$aula['aula_id'] > 0) {
                return [
                    'modulo_id' => (int)$aula['modulo_id'],
                    'aula_id' => (int)$aula['aula_id'],
                    'link' => 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'],
                ];
            }
        } catch (Throwable $e) {
            // Se não encontrar aula, deixa o link do curso.
        }

        return $resultado;
    }
}

$pdo = null;
$erro = '';
$sucesso = '';

try {
    $pdo = admin_cursos_db();
    admin_crono_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro ao conectar no banco: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar_cronograma') {
            $cronogramaIdEditar = admin_crono_int($_POST['cronograma_id'] ?? 0);
            $cursoId = admin_crono_int($_POST['curso_id'] ?? 0);
            $dataInicio = admin_crono_data((string)($_POST['data_inicio'] ?? ''));
            $tituloCronograma = trim((string)($_POST['titulo'] ?? ''));
            $itensMateria = $_POST['item_materia_id'] ?? [];
            $itensTopico = $_POST['item_topico_id'] ?? [];
            $itensData = $_POST['item_data_estudo'] ?? [];
            $itensDuracao = $_POST['item_duracao_minutos'] ?? [];
            $itensVideo = $_POST['item_video_minutos'] ?? [];
            $itensPdf = $_POST['item_pdf_minutos'] ?? [];
            $itensQuestoes = $_POST['item_questoes_minutos'] ?? [];
            $itensOrdem = $_POST['item_ordem'] ?? [];

            if ($cursoId <= 0) {
                throw new RuntimeException('Selecione o produto/curso desse cronograma.');
            }

            $stmtCurso = $pdo->prepare("SELECT id, nome FROM admin_cursos WHERE id = :id LIMIT 1");
            $stmtCurso->execute([':id' => $cursoId]);
            $curso = $stmtCurso->fetch();
            if (!$curso) {
                throw new RuntimeException('Produto/curso não encontrado.');
            }

            if (!is_array($itensTopico) || count($itensTopico) === 0) {
                throw new RuntimeException('Selecione pelo menos um tópico para o cronograma.');
            }

            $topicosSelecionados = [];
            foreach ($itensTopico as $idx => $topicoRaw) {
                $topicoId = admin_crono_int($topicoRaw);
                $materiaId = admin_crono_int($itensMateria[$idx] ?? 0);
                if ($topicoId <= 0 || $materiaId <= 0) { continue; }

                $dataEstudo = admin_crono_data((string)($itensData[$idx] ?? $dataInicio));
                $videoMinutos = admin_crono_minutos_ou_zero($itensVideo[$idx] ?? 0);
                $pdfMinutos = admin_crono_minutos_ou_zero($itensPdf[$idx] ?? 0);
                $questoesMinutos = admin_crono_minutos_ou_zero($itensQuestoes[$idx] ?? 0);
                $duracao = $videoMinutos + $pdfMinutos + $questoesMinutos;
                if ($duracao <= 0) {
                    $duracao = admin_crono_minutos($itensDuracao[$idx] ?? 60);
                }
                $ordem = (int)($itensOrdem[$idx] ?? ($idx + 1));

                $topicosSelecionados[] = [
                    'materia_id' => $materiaId,
                    'topico_id' => $topicoId,
                    'data_estudo' => $dataEstudo,
                    'duracao' => $duracao,
                    'video_minutos' => $videoMinutos,
                    'pdf_minutos' => $pdfMinutos,
                    'questoes_minutos' => $questoesMinutos,
                    'ordem' => $ordem,
                ];
            }

            if (!$topicosSelecionados) {
                throw new RuntimeException('Selecione os tópicos corretamente antes de salvar.');
            }

            if ($tituloCronograma === '') {
                $tituloCronograma = 'Cronograma - ' . (string)$curso['nome'];
            }

            $pdo->beginTransaction();

            if ($cronogramaIdEditar > 0) {
                $stmtExiste = $pdo->prepare("SELECT id FROM admin_cronogramas WHERE id = :id AND status <> 'inativo' LIMIT 1");
                $stmtExiste->execute([':id' => $cronogramaIdEditar]);
                if (!$stmtExiste->fetch()) {
                    throw new RuntimeException('Cronograma não encontrado para edição.');
                }

                $stmt = $pdo->prepare('UPDATE admin_cronogramas SET curso_id = :curso_id, titulo = :titulo, data_inicio = :data_inicio, status = :status WHERE id = :id LIMIT 1');
                $stmt->execute([':curso_id' => $cursoId, ':titulo' => $tituloCronograma, ':data_inicio' => $dataInicio, ':status' => 'ativo', ':id' => $cronogramaIdEditar]);
                $cronogramaId = $cronogramaIdEditar;

                $stmt = $pdo->prepare("UPDATE admin_cronograma_itens SET status = 'inativo' WHERE cronograma_id = :id");
                $stmt->execute([':id' => $cronogramaId]);
                $stmt = $pdo->prepare("UPDATE admin_metas_diarias m INNER JOIN admin_cronograma_itens i ON i.id = m.cronograma_item_id SET m.status = 'inativo' WHERE i.cronograma_id = :id");
                $stmt->execute([':id' => $cronogramaId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO admin_cronogramas (curso_id, titulo, data_inicio, status) VALUES (:curso_id, :titulo, :data_inicio, :status)');
                $stmt->execute([':curso_id' => $cursoId, ':titulo' => $tituloCronograma, ':data_inicio' => $dataInicio, ':status' => 'ativo']);
                $cronogramaId = (int)$pdo->lastInsertId();
            }

            $stmtDados = $pdo->prepare('SELECT t.id AS topico_id, t.titulo AS topico_nome, m.id AS materia_id, m.nome AS materia_nome FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id WHERE t.id = :topico_id AND m.id = :materia_id LIMIT 1');
            $stmtItem = $pdo->prepare('INSERT INTO admin_cronograma_itens (cronograma_id, curso_id, materia_id, topico_id, modulo_id, aula_id, titulo, materia, descricao, data_estudo, duracao_minutos, video_minutos, pdf_minutos, questoes_minutos, ordem, status) VALUES (:cronograma_id, :curso_id, :materia_id, :topico_id, :modulo_id, :aula_id, :titulo, :materia, :descricao, :data_estudo, :duracao, :video_minutos, :pdf_minutos, :questoes_minutos, :ordem, :status)');
            $stmtMeta = $pdo->prepare('INSERT INTO admin_metas_diarias (cronograma_item_id, titulo, materia, materia_id, topico_id, descricao, curso_id, modulo_id, aula_id, tempo_minutos, video_minutos, pdf_minutos, questoes_minutos, data_meta, video_link, banco_questoes_link, status) VALUES (:cronograma_item_id, :titulo, :materia, :materia_id, :topico_id, :descricao, :curso_id, :modulo_id, :aula_id, :tempo, :video_minutos, :pdf_minutos, :questoes_minutos, :data_meta, :video_link, :banco_link, :status)');

            $itensInseridos = 0;
            foreach ($topicosSelecionados as $item) {
                $stmtDados->execute([':topico_id' => $item['topico_id'], ':materia_id' => $item['materia_id']]);
                $dados = $stmtDados->fetch();
                if (!$dados) { continue; }

                $tituloItem = (string)$dados['topico_nome'];
                $materiaNome = (string)$dados['materia_nome'];
                $descricao = 'Meta de estudo do cronograma: ' . $tituloCronograma;
                $aulaDestino = admin_crono_buscar_aula_para_topico($pdo, $cursoId, (int)$item['topico_id'], $tituloItem);

                $stmtItem->execute([
                    ':cronograma_id' => $cronogramaId,
                    ':curso_id' => $cursoId,
                    ':materia_id' => $item['materia_id'],
                    ':topico_id' => $item['topico_id'],
                    ':modulo_id' => $aulaDestino['modulo_id'],
                    ':aula_id' => $aulaDestino['aula_id'],
                    ':titulo' => $tituloItem,
                    ':materia' => $materiaNome,
                    ':descricao' => $descricao,
                    ':data_estudo' => $item['data_estudo'],
                    ':duracao' => $item['duracao'],
                    ':video_minutos' => $item['video_minutos'],
                    ':pdf_minutos' => $item['pdf_minutos'],
                    ':questoes_minutos' => $item['questoes_minutos'],
                    ':ordem' => $item['ordem'],
                    ':status' => 'ativo',
                ]);
                $itemId = (int)$pdo->lastInsertId();

                $itensInseridos++;

                $stmtMeta->execute([
                    ':cronograma_item_id' => $itemId,
                    ':titulo' => $tituloItem,
                    ':materia' => $materiaNome,
                    ':materia_id' => $item['materia_id'],
                    ':topico_id' => $item['topico_id'],
                    ':descricao' => $descricao,
                    ':curso_id' => $cursoId,
                    ':modulo_id' => $aulaDestino['modulo_id'],
                    ':aula_id' => $aulaDestino['aula_id'],
                    ':tempo' => $item['duracao'],
                    ':video_minutos' => $item['video_minutos'],
                    ':pdf_minutos' => $item['pdf_minutos'],
                    ':questoes_minutos' => $item['questoes_minutos'],
                    ':data_meta' => $item['data_estudo'],
                    ':video_link' => $aulaDestino['link'],
                    ':banco_link' => 'banco-questoes.php?materia_id=' . $item['materia_id'] . '&topico_id=' . $item['topico_id'],
                    ':status' => 'ativo',
                ]);
            }

            if ($itensInseridos <= 0) {
                throw new RuntimeException('Nenhum item foi salvo. Confira se as matérias e tópicos ainda existem no banco.');
            }

            $pdo->commit();
            admin_cursos_redirect('admin-cronograma.php?sucesso=' . ($cronogramaIdEditar > 0 ? 'atualizado' : 'criado'));
        }

        if ($acao === 'excluir_cronograma') {
            $cronogramaId = admin_crono_int($_POST['cronograma_id'] ?? 0);
            if ($cronogramaId <= 0) {
                throw new RuntimeException('Cronograma inválido.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE admin_cronogramas SET status = 'inativo' WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $cronogramaId]);
            $stmt = $pdo->prepare("UPDATE admin_cronograma_itens SET status = 'inativo' WHERE cronograma_id = :id");
            $stmt->execute([':id' => $cronogramaId]);
            $stmt = $pdo->prepare("UPDATE admin_metas_diarias m INNER JOIN admin_cronograma_itens i ON i.id = m.cronograma_item_id SET m.status = 'inativo' WHERE i.cronograma_id = :id");
            $stmt->execute([':id' => $cronogramaId]);
            $pdo->commit();
            admin_cursos_redirect('admin-cronograma.php?sucesso=excluido');
        }
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
        $erro = $e->getMessage();
    }
}

$mensagens = [
    'criado' => 'Cronograma criado com sucesso.',
    'atualizado' => 'Cronograma atualizado com sucesso.',
    'excluido' => 'Cronograma removido com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagens[$sucessoKey])) {
    $sucesso = $mensagens[$sucessoKey];
}

$cursos = [];
$materias = [];
$topicos = [];
$cronogramas = [];
$totais = ['cronogramas' => 0, 'itens' => 0, 'metas' => 0];

if ($pdo instanceof PDO) {
    try {
        $cursos = $pdo->query("SELECT id, nome FROM admin_cursos WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();
        $materias = $pdo->query("SELECT id, nome FROM admin_materias WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();
        $topicos = $pdo->query("SELECT t.id, t.materia_id, t.titulo, m.nome AS materia_nome FROM admin_materia_topicos t INNER JOIN admin_materias m ON m.id = t.materia_id WHERE t.status = 'ativo' AND m.status = 'ativo' ORDER BY m.nome ASC, t.ordem ASC, t.titulo ASC")->fetchAll();
        $cronogramas = $pdo->query("SELECT c.*, curso.nome AS curso_nome, COUNT(i.id) AS total_itens, COALESCE(SUM(i.duracao_minutos),0) AS total_minutos FROM admin_cronogramas c INNER JOIN admin_cursos curso ON curso.id = c.curso_id LEFT JOIN admin_cronograma_itens i ON i.cronograma_id = c.id AND i.status = 'ativo' WHERE c.status = 'ativo' GROUP BY c.id, curso.nome ORDER BY c.data_inicio DESC, c.id DESC")->fetchAll();
        $totais['cronogramas'] = count($cronogramas);
        $totais['itens'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_cronograma_itens WHERE status = 'ativo'")->fetchColumn();
        $totais['metas'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_metas_diarias WHERE status = 'ativo'")->fetchColumn();
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar dados: ' . $e->getMessage();
    }
}


$modalCronograma = (string)($_GET['modal'] ?? '');
$cronogramaEdicao = null;
$itensEdicao = [];
$materiasEdicaoIds = [];
$topicosEdicaoIds = [];
if ($pdo instanceof PDO && $modalCronograma === 'editar') {
    $editarId = admin_crono_int($_GET['id'] ?? 0);
    if ($editarId > 0) {
        try {
            $stmtEdit = $pdo->prepare("SELECT * FROM admin_cronogramas WHERE id = :id AND status <> 'inativo' LIMIT 1");
            $stmtEdit->execute([':id' => $editarId]);
            $cronogramaEdicao = $stmtEdit->fetch() ?: null;
            if ($cronogramaEdicao) {
                $stmtItensEdit = $pdo->prepare("SELECT * FROM admin_cronograma_itens WHERE cronograma_id = :id AND status = 'ativo' ORDER BY ordem ASC, data_estudo ASC, id ASC");
                $stmtItensEdit->execute([':id' => $editarId]);
                $itensEdicao = $stmtItensEdit->fetchAll();
                foreach ($itensEdicao as $itemEdicao) {
                    $materiaIdEdicao = (int)($itemEdicao['materia_id'] ?? 0);
                    $topicoIdEdicao = (int)($itemEdicao['topico_id'] ?? 0);
                    if ($materiaIdEdicao > 0) { $materiasEdicaoIds[$materiaIdEdicao] = $materiaIdEdicao; }
                    if ($topicoIdEdicao > 0) { $topicosEdicaoIds[$topicoIdEdicao] = $topicoIdEdicao; }
                }
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao carregar cronograma para edição: ' . $e->getMessage();
        }
    }
}
$mostrarFormularioCronograma = $modalCronograma === 'criar' || ($modalCronograma === 'editar' && $cronogramaEdicao);
$cronogramaFormTitulo = $cronogramaEdicao ? 'Editar cronograma' : 'Criar cronograma';
$cronogramaFormSubtitulo = $cronogramaEdicao ? 'Altere produto, data, matérias, tópicos, ordem e tempo.' : 'Selecione produto, data, matérias, tópicos, ordem e tempo.';
$cronogramaFormCursoId = (int)($cronogramaEdicao['curso_id'] ?? 0);
$cronogramaFormData = (string)($cronogramaEdicao['data_inicio'] ?? date('Y-m-d'));
$cronogramaFormNome = (string)($cronogramaEdicao['titulo'] ?? '');
$cronogramaInicialItens = array_map(static function(array $item): array {
    return [
        'materia_id' => (int)($item['materia_id'] ?? 0),
        'topico_id' => (int)($item['topico_id'] ?? 0),
        'data_estudo' => (string)($item['data_estudo'] ?? ''),
        'ordem' => (int)($item['ordem'] ?? 0),
        'duracao_minutos' => (int)($item['duracao_minutos'] ?? 0),
        'video_minutos' => (int)($item['video_minutos'] ?? 0),
        'pdf_minutos' => (int)($item['pdf_minutos'] ?? 0),
        'questoes_minutos' => (int)($item['questoes_minutos'] ?? 0),
    ];
}, $itensEdicao);

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$topicosJson = json_encode($topicos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
$materiasJson = json_encode($materias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
$cronogramaInicialJson = json_encode($cronogramaInicialItens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Cronograma | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .admin-card{padding:0;margin-bottom:20px}.admin-card-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid #eef0f3}.admin-card-header h2{font-size:1.2rem;color:#111827;margin:0 0 5px}.admin-card-header p{color:#667085;font-size:.92rem;font-weight:500;margin:0}.admin-card>.crono-wizard,.admin-card>.admin-table-wrap,.admin-card>.admin-empty{margin:20px 22px 22px}.admin-form-field>span{color:#344054;font-size:.88rem;font-weight:700}.admin-input{width:100%;border:1px solid #d0d5dd;background:#ffffff;color:#111827;border-radius:9px;min-height:44px;outline:none;padding:0 12px;font-size:.94rem;font-weight:500;box-shadow:0 1px 2px rgba(16,24,40,.04)}.admin-icon-btn{width:38px;height:38px;border:1px solid #e4e7ec;background:#fff;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#b42318;cursor:pointer}.admin-icon-btn svg{width:18px;height:18px}.admin-status{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700}.admin-status-active{background:#ecfdf3;color:#027a48}.crono-wizard{display:grid;gap:18px}.crono-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.crono-step{border:1px solid #e4e7ec;border-radius:14px;background:#fff;padding:13px 14px;font-weight:700;color:#667085;display:flex;gap:10px;align-items:center}.crono-step span{width:28px;height:28px;border-radius:50%;background:#f2f4f7;display:inline-flex;align-items:center;justify-content:center;color:#475467}.crono-step.active{border-color:#fdbd08;background:#fff7db;color:#111}.crono-step.active span{background:#fdbd08;color:#111}.crono-pane{display:none}.crono-pane.active{display:block}.crono-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.crono-checkgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.crono-check{display:flex;align-items:flex-start;gap:10px;border:1px solid #e4e7ec;border-radius:12px;padding:13px;background:#fff;cursor:pointer}.crono-check input{margin-top:3px;accent-color:#fdbd08}.crono-check strong{display:block;color:#111827}.crono-check small{display:block;color:#667085;margin-top:4px}.crono-topic-card{border:1px solid #e4e7ec;border-radius:14px;background:#fff;padding:14px;margin-bottom:12px}.crono-topic-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.crono-topic-head strong{color:#111827}.crono-topic-fields{display:grid;grid-template-columns:150px 150px 120px;gap:10px;margin-top:12px}.crono-order-list{display:grid;gap:10px}.crono-order-item{display:grid;grid-template-columns:82px minmax(0,1fr) 142px 110px 110px 120px 88px;gap:10px;align-items:center;border:1px solid #e4e7ec;border-radius:12px;background:#fff;padding:12px}.crono-order-tools{display:flex;gap:6px;justify-content:flex-end}.crono-move{width:38px;height:38px;border:1px solid #e4e7ec;background:#fff;border-radius:10px;cursor:pointer;color:#111827;display:inline-flex;align-items:center;justify-content:center}.crono-order-item strong{color:#111827}.crono-actions{display:flex;justify-content:space-between;gap:12px;margin-top:18px}.crono-empty{border:1px dashed #d0d5dd;border-radius:14px;padding:24px;text-align:center;color:#667085;font-weight:700}.crono-pill{display:inline-flex;border-radius:999px;background:#fff7db;color:#8a6100;padding:6px 10px;font-size:.78rem;font-weight:700}.crono-table-title{display:grid;gap:4px}.crono-table-title small{color:#667085}.crono-hidden{display:none!important}@media(max-width:900px){.admin-card-header{display:grid}.crono-grid,.crono-checkgrid,.crono-steps{grid-template-columns:1fr}.crono-topic-fields,.crono-order-item{grid-template-columns:1fr}.crono-order-tools{justify-content:flex-start}.crono-actions{flex-direction:column}.crono-actions .admin-btn{width:100%;justify-content:center}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Cronograma</h1>
                    <p>Crie cronogramas por produto/curso, selecionando matérias, tópicos, ordem e tempo de estudo.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-cronograma.php?modal=criar"><i data-lucide="calendar-plus" aria-hidden="true"></i>Criar cronograma</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_crono_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_crono_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><span>Cronogramas ativos</span><strong><?php echo (int)$totais['cronogramas']; ?></strong><small>por produto</small></article>
                <article class="admin-stat-card"><span>Itens cadastrados</span><strong><?php echo (int)$totais['itens']; ?></strong><small>matéria + tópico</small></article>
                <article class="admin-stat-card"><span>Metas geradas</span><strong><?php echo (int)$totais['metas']; ?></strong><small>aparecem para o aluno</small></article>
            </section>

            <?php if ($mostrarFormularioCronograma): ?>
                <section class="admin-card" id="criar-cronograma">
                    <div class="admin-card-header">
                        <div><h2><?php echo admin_crono_esc($cronogramaFormTitulo); ?></h2><p><?php echo admin_crono_esc($cronogramaFormSubtitulo); ?></p></div>
                        <a class="admin-btn admin-btn-light" href="admin-cronograma.php"><i data-lucide="x" aria-hidden="true"></i>Fechar</a>
                    </div>

                    <form method="post" class="crono-wizard" id="formCronograma">
                        <input type="hidden" name="acao" value="salvar_cronograma">
                        <input type="hidden" name="cronograma_id" value="<?php echo (int)($cronogramaEdicao['id'] ?? 0); ?>">
                        <div class="crono-steps" aria-label="Etapas do cronograma">
                            <div class="crono-step active" data-step-label="1"><span>1</span>Produto e data</div>
                            <div class="crono-step" data-step-label="2"><span>2</span>Matérias</div>
                            <div class="crono-step" data-step-label="3"><span>3</span>Selecionar tópicos</div>
                            <div class="crono-step" data-step-label="4"><span>4</span>Organizar ordem e tempo</div>
                        </div>

                        <div class="crono-pane active" data-step="1">
                            <div class="crono-grid">
                                <label class="admin-form-field"><span>Produto/curso do cronograma</span><select name="curso_id" id="cursoId" required><option value="">Selecione</option><?php foreach ($cursos as $curso): ?><option value="<?php echo (int)$curso['id']; ?>" <?php echo (int)$curso['id'] === $cronogramaFormCursoId ? 'selected' : ''; ?>><?php echo admin_crono_esc((string)$curso['nome']); ?></option><?php endforeach; ?></select></label>
                                <label class="admin-form-field"><span>Data inicial</span><input type="date" name="data_inicio" id="dataInicio" value="<?php echo admin_crono_esc($cronogramaFormData); ?>" required></label>
                                <label class="admin-form-field" style="grid-column:1/-1"><span>Título do cronograma</span><input type="text" name="titulo" value="<?php echo admin_crono_esc($cronogramaFormNome); ?>" placeholder="Ex: Cronograma PF - Maio"></label>
                            </div>
                        </div>

                        <div class="crono-pane" data-step="2">
                            <?php if (!$materias): ?>
                                <div class="crono-empty">Cadastre matérias em Admin &gt; Matérias antes de criar o cronograma.</div>
                            <?php else: ?>
                                <div class="crono-checkgrid">
                                    <?php foreach ($materias as $materia): ?>
                                        <label class="crono-check">
                                            <input type="checkbox" class="materia-check" value="<?php echo (int)$materia['id']; ?>" <?php echo isset($materiasEdicaoIds[(int)$materia['id']]) ? 'checked' : ''; ?>>
                                            <span><strong><?php echo admin_crono_esc((string)$materia['nome']); ?></strong><small>Selecionar tópicos dessa matéria</small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="crono-pane" data-step="3">
                            <div id="topicosSelecionaveis" class="crono-empty">Selecione as matérias na etapa anterior.</div>
                        </div>

                        <div class="crono-pane" data-step="4">
                            <p style="margin:0 0 12px;color:#667085;font-weight:700">Organize a sequência que o aluno vai estudar. Use as setas, ajuste a ordem, a data e o tempo de vídeo, PDF e questões de cada tópico.</p><div id="ordemSelecionada" class="crono-empty">Selecione os tópicos na etapa anterior.</div>
                            <div id="finalHiddenFields"></div>
                        </div>

                        <div class="crono-actions">
                            <button type="button" class="admin-btn admin-btn-light" id="btnVoltar"><i data-lucide="chevron-left" aria-hidden="true"></i>Voltar</button>
                            <button type="button" class="admin-btn admin-btn-primary" id="btnContinuar">Continuar<i data-lucide="chevron-right" aria-hidden="true"></i></button>
                            <button type="submit" class="admin-btn admin-btn-primary crono-hidden" id="btnSalvar"><i data-lucide="save" aria-hidden="true"></i><?php echo $cronogramaEdicao ? 'Atualizar cronograma' : 'Salvar cronograma'; ?></button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div><h2>Cronogramas cadastrados</h2><p>O usuário só vê o cronograma do produto/curso que ele tem liberado.</p></div>
                </div>

                <?php if (!$cronogramas): ?>
                    <div class="admin-empty"><i data-lucide="calendar-days" aria-hidden="true"></i><strong>Nenhum cronograma cadastrado.</strong><span>Clique em Criar cronograma para montar o plano de estudo.</span></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Cronograma</th><th>Produto/curso</th><th>Data inicial</th><th>Itens</th><th>Tempo total</th><th>Status</th><th>Ações</th></tr></thead>
                            <tbody>
                                <?php foreach ($cronogramas as $crono): ?>
                                    <?php $minutos = (int)($crono['total_minutos'] ?? 0); $tempo = $minutos >= 60 ? intdiv($minutos,60) . 'h' . ($minutos % 60 ? ' ' . ($minutos % 60) . 'min' : '') : $minutos . 'min'; ?>
                                    <tr>
                                        <td><div class="crono-table-title"><strong><?php echo admin_crono_esc((string)$crono['titulo']); ?></strong><small>ID #<?php echo (int)$crono['id']; ?></small></div></td>
                                        <td><?php echo admin_crono_esc((string)$crono['curso_nome']); ?></td>
                                        <td><?php echo admin_crono_esc(admin_crono_periodo((string)$crono['data_inicio'])); ?></td>
                                        <td><span class="crono-pill"><?php echo (int)$crono['total_itens']; ?> tópicos</span></td>
                                        <td><?php echo admin_crono_esc($tempo); ?></td>
                                        <td><span class="admin-status admin-status-active">Ativo</span></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <a class="admin-icon-btn" href="admin-cronograma.php?modal=editar&id=<?php echo (int)$crono['id']; ?>" title="Editar"><i data-lucide="pencil" aria-hidden="true"></i></a>
                                            <form method="post" onsubmit="return confirm('Remover este cronograma e ocultar as metas geradas?');">
                                                <input type="hidden" name="acao" value="excluir_cronograma">
                                                <input type="hidden" name="cronograma_id" value="<?php echo (int)$crono['id']; ?>">
                                                <button class="admin-icon-btn" type="submit" title="Excluir"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                                            </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        if (window.lucide) window.lucide.createIcons();
        const TOPICOS = <?php echo $topicosJson; ?>;
        const MATERIAS = <?php echo $materiasJson; ?>;
        const CRONOGRAMA_INICIAL = <?php echo $cronogramaInicialJson; ?>;
        (function(){
            const form = document.getElementById('formCronograma');
            if (!form) return;
            let step = 1;
            const panes = Array.from(document.querySelectorAll('.crono-pane'));
            const labels = Array.from(document.querySelectorAll('.crono-step'));
            const btnVoltar = document.getElementById('btnVoltar');
            const btnContinuar = document.getElementById('btnContinuar');
            const btnSalvar = document.getElementById('btnSalvar');
            const topicosBox = document.getElementById('topicosSelecionaveis');
            const ordemBox = document.getElementById('ordemSelecionada');
            const hiddenBox = document.getElementById('finalHiddenFields');

            function materiaNome(id){ const m = MATERIAS.find(x => Number(x.id) === Number(id)); return m ? m.nome : 'Matéria'; }
            function topicosDaMateria(id){ return TOPICOS.filter(t => Number(t.materia_id) === Number(id)); }
            function materiasMarcadas(){ return Array.from(document.querySelectorAll('.materia-check:checked')).map(i => Number(i.value)); }
            function diasDepois(data, offset){ const d = new Date(data + 'T00:00:00'); d.setDate(d.getDate() + offset); return d.toISOString().slice(0,10); }

            function render(){
                panes.forEach(p => p.classList.toggle('active', Number(p.dataset.step) === step));
                labels.forEach(l => l.classList.toggle('active', Number(l.dataset.stepLabel) === step));
                btnVoltar.style.visibility = step === 1 ? 'hidden' : 'visible';
                btnContinuar.classList.toggle('crono-hidden', step === 4);
                btnSalvar.classList.toggle('crono-hidden', step !== 4);
                if (window.lucide) window.lucide.createIcons();
            }

            function renderTopicos(){
                const materias = materiasMarcadas();
                if (!materias.length) {
                    topicosBox.className = 'crono-empty';
                    topicosBox.innerHTML = 'Selecione as matérias na etapa anterior.';
                    return;
                }
                let html = '';
                materias.forEach(materiaId => {
                    const topicos = topicosDaMateria(materiaId);
                    html += `<div class="crono-topic-card"><h3 style="margin:0 0 12px;color:#111827">${materiaNome(materiaId)}</h3>`;
                    if (!topicos.length) {
                        html += '<div class="crono-empty">Nenhum tópico cadastrado para essa matéria.</div>';
                    } else {
                        topicos.forEach(t => {
                            const marcado = CRONOGRAMA_INICIAL.some(item => Number(item.topico_id) === Number(t.id));
                            html += `<label class="crono-check" style="margin-bottom:10px"><input type="checkbox" class="topico-check" data-materia="${materiaId}" value="${t.id}" ${marcado ? 'checked' : ''}><span><strong>${t.titulo}</strong><small>${materiaNome(materiaId)}</small></span></label>`;
                        });
                    }
                    html += '</div>';
                });
                topicosBox.className = '';
                topicosBox.innerHTML = html;
            }

            function selecionados(){
                const lista = Array.from(document.querySelectorAll('.topico-check:checked')).map((input, idx) => {
                    const topico = TOPICOS.find(t => Number(t.id) === Number(input.value));
                    const inicial = CRONOGRAMA_INICIAL.find(row => Number(row.topico_id) === Number(input.value));
                    return {materia_id:Number(input.dataset.materia), topico_id:Number(input.value), topico: topico ? topico.titulo : 'Tópico', materia:materiaNome(input.dataset.materia), ordem: inicial ? Number(inicial.ordem || idx + 1) : idx + 1};
                });
                if (CRONOGRAMA_INICIAL.length) {
                    lista.sort((a,b) => Number(a.ordem || 0) - Number(b.ordem || 0));
                }
                return lista;
            }

            function renderOrdem(){
                const lista = selecionados();
                const dataInicio = document.getElementById('dataInicio').value || new Date().toISOString().slice(0,10);
                if (!lista.length) {
                    ordemBox.className = 'crono-empty';
                    ordemBox.innerHTML = 'Selecione os tópicos e tempos na etapa anterior.';
                    hiddenBox.innerHTML = '';
                    return;
                }
                ordemBox.className = 'crono-order-list';
                ordemBox.innerHTML = lista.map((item, idx) => {
                    const inicial = CRONOGRAMA_INICIAL.find(row => Number(row.topico_id) === Number(item.topico_id)) || {};
                    const ordem = inicial.ordem || (idx + 1);
                    const data = inicial.data_estudo || diasDepois(dataInicio, idx);
                    const video = inicial.video_minutos ?? 60;
                    const pdf = inicial.pdf_minutos ?? 0;
                    const questoes = inicial.questoes_minutos ?? 20;
                    return `
                    <div class="crono-order-item" data-materia="${item.materia_id}" data-topico="${item.topico_id}">
                        <label><small>Ordem</small><input class="admin-input crono-ordem" type="number" min="1" value="${ordem}" aria-label="Ordem"></label>
                        <div><strong>${item.materia}</strong><br><small>${item.topico}</small></div>
                        <label><small>Data</small><input class="admin-input crono-data" type="date" value="${data}" aria-label="Data"></label>
                        <label><small>Vídeo</small><input class="admin-input crono-video" type="number" min="0" max="1440" value="${video}" aria-label="Tempo de vídeo em minutos"></label>
                        <label><small>PDF</small><input class="admin-input crono-pdf" type="number" min="0" max="1440" value="${pdf}" aria-label="Tempo de PDF em minutos"></label>
                        <label><small>Questões</small><input class="admin-input crono-questoes" type="number" min="0" max="1440" value="${questoes}" aria-label="Tempo de questões em minutos"></label>
                        <div class="crono-order-tools"><button type="button" class="crono-move crono-up" title="Subir">↑</button><button type="button" class="crono-move crono-down" title="Descer">↓</button></div>
                    </div>`;
                }).join('');
                renumerarOrdem();
                montarHidden();
            }

            function renumerarOrdem(){
                Array.from(document.querySelectorAll('.crono-order-item')).forEach((row, idx) => {
                    const campo = row.querySelector('.crono-ordem');
                    if (campo) campo.value = String(idx + 1);
                });
            }

            function montarHidden(){
                hiddenBox.innerHTML = '';
                Array.from(document.querySelectorAll('.crono-order-item')).forEach(row => {
                    const materia = row.dataset.materia;
                    const topico = row.dataset.topico;
                    const ordem = row.querySelector('.crono-ordem').value || '1';
                    const data = row.querySelector('.crono-data').value || document.getElementById('dataInicio').value;
                    const video = row.querySelector('.crono-video').value || '0';
                    const pdf = row.querySelector('.crono-pdf').value || '0';
                    const questoes = row.querySelector('.crono-questoes').value || '0';
                    const total = Math.max(5, (parseInt(video, 10) || 0) + (parseInt(pdf, 10) || 0) + (parseInt(questoes, 10) || 0));
                    hiddenBox.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_materia_id[]" value="${materia}"><input type="hidden" name="item_topico_id[]" value="${topico}"><input type="hidden" name="item_ordem[]" value="${ordem}"><input type="hidden" name="item_data_estudo[]" value="${data}"><input type="hidden" name="item_duracao_minutos[]" value="${total}"><input type="hidden" name="item_video_minutos[]" value="${video}"><input type="hidden" name="item_pdf_minutos[]" value="${pdf}"><input type="hidden" name="item_questoes_minutos[]" value="${questoes}">`);
                });
            }

            btnContinuar.addEventListener('click', function(){
                if (step === 1) {
                    if (!document.getElementById('cursoId').value) { alert('Selecione o produto/curso.'); return; }
                    if (!document.getElementById('dataInicio').value) { alert('Selecione a data inicial.'); return; }
                }
                if (step === 2) {
                    if (!materiasMarcadas().length) { alert('Selecione pelo menos uma matéria.'); return; }
                    renderTopicos();
                }
                if (step === 3) {
                    if (!selecionados().length) { alert('Selecione pelo menos um tópico.'); return; }
                    renderOrdem();
                }
                step = Math.min(4, step + 1);
                render();
            });

            btnVoltar.addEventListener('click', function(){ step = Math.max(1, step - 1); render(); });
            ordemBox.addEventListener('input', montarHidden);
            ordemBox.addEventListener('click', function(event){
                const btn = event.target.closest('.crono-move');
                if (!btn) return;
                const row = btn.closest('.crono-order-item');
                if (!row) return;
                if (btn.classList.contains('crono-up') && row.previousElementSibling) {
                    row.parentNode.insertBefore(row, row.previousElementSibling);
                }
                if (btn.classList.contains('crono-down') && row.nextElementSibling) {
                    row.parentNode.insertBefore(row.nextElementSibling, row);
                }
                renumerarOrdem();
                montarHidden();
            });
            form.addEventListener('submit', function(){ renumerarOrdem(); montarHidden(); });
            if (CRONOGRAMA_INICIAL.length) {
                renderTopicos();
                renderOrdem();
                step = 4;
            }
            render();
        })();
    </script>
</body>
</html>
