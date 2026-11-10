<?php
// includes/admin-banco-questoes-lib.php
// Funções compartilhadas para Bancas, Matérias, Concurso Passado e Banco de Questões.
declare(strict_types=1);

require_once __DIR__ . '/admin-cursos-lib.php';

if (!function_exists('admin_bq_table_columns')) {
    function admin_bq_table_columns(PDO $pdo, string $table): array
    {
        try {
            $safe = str_replace('`', '``', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$safe}`");
            return array_map(static fn(array $row): string => (string)$row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('admin_bq_has_column')) {
    function admin_bq_has_column(PDO $pdo, string $table, string $column): bool
    {
        return in_array($column, admin_bq_table_columns($pdo, $table), true);
    }
}

if (!function_exists('admin_bq_try_exec')) {
    function admin_bq_try_exec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (Throwable $ignored) {
            // Evita quebrar a página se índice/constraint já existir em banco antigo.
        }
    }
}

if (!function_exists('admin_bq_bootstrap')) {
    function admin_bq_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_bancas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            sigla VARCHAR(40) NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_bancas_status (status),
            KEY idx_admin_bancas_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_concursos_passados (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(180) NOT NULL,
            banca_id INT UNSIGNED DEFAULT NULL,
            ano SMALLINT UNSIGNED DEFAULT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_concursos_passados_banca (banca_id),
            KEY idx_admin_concursos_passados_ano (ano),
            KEY idx_admin_concursos_passados_status (status),
            KEY idx_admin_concursos_passados_ordem (ordem),
            CONSTRAINT fk_admin_concursos_passados_banca
                FOREIGN KEY (banca_id) REFERENCES admin_bancas(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_materias (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_materias_status (status),
            KEY idx_admin_materias_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_materia_topicos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            materia_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_materia_topicos_materia (materia_id),
            KEY idx_admin_materia_topicos_status (status),
            KEY idx_admin_materia_topicos_ordem (ordem),
            CONSTRAINT fk_admin_materia_topicos_materia
                FOREIGN KEY (materia_id) REFERENCES admin_materias(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_banco_questoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED DEFAULT NULL,
            concurso_passado_id INT UNSIGNED DEFAULT NULL,
            banca_id INT UNSIGNED NOT NULL,
            materia_id INT UNSIGNED NOT NULL,
            topico_id INT UNSIGNED DEFAULT NULL,
            materia_topico_id INT UNSIGNED DEFAULT NULL,
            ano SMALLINT UNSIGNED DEFAULT NULL,
            enunciado TEXT NOT NULL,
            explicacao TEXT NULL,
            dificuldade ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_banco_questoes_curso (curso_id),
            KEY idx_admin_banco_questoes_concurso_passado (concurso_passado_id),
            KEY idx_admin_banco_questoes_banca (banca_id),
            KEY idx_admin_banco_questoes_materia (materia_id),
            KEY idx_admin_banco_questoes_materia_topico (materia_topico_id),
            KEY idx_admin_banco_questoes_ano (ano),
            KEY idx_admin_banco_questoes_status (status),
            KEY idx_admin_banco_questoes_ordem (ordem),
            CONSTRAINT fk_admin_banco_questoes_curso
                FOREIGN KEY (curso_id) REFERENCES admin_cursos(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_admin_banco_questoes_concurso_passado
                FOREIGN KEY (concurso_passado_id) REFERENCES admin_concursos_passados(id)
                ON DELETE SET NULL,
            CONSTRAINT fk_admin_banco_questoes_banca
                FOREIGN KEY (banca_id) REFERENCES admin_bancas(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_banco_questoes_materia
                FOREIGN KEY (materia_id) REFERENCES admin_materias(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_banco_questoes_materia_topico
                FOREIGN KEY (materia_topico_id) REFERENCES admin_materia_topicos(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_banco_questao_acessos (
            questao_id INT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (questao_id, curso_id),
            KEY idx_admin_bq_acessos_curso (curso_id),
            CONSTRAINT fk_admin_bq_acessos_questao
                FOREIGN KEY (questao_id) REFERENCES admin_banco_questoes(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_bq_acessos_curso
                FOREIGN KEY (curso_id) REFERENCES admin_cursos(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_banco_questao_opcoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            questao_id INT UNSIGNED NOT NULL,
            letra VARCHAR(8) NOT NULL,
            texto TEXT NOT NULL,
            correta TINYINT(1) NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_banco_questao_opcoes_questao (questao_id),
            KEY idx_admin_banco_questao_opcoes_correta (correta),
            CONSTRAINT fk_admin_banco_questao_opcoes_questao
                FOREIGN KEY (questao_id) REFERENCES admin_banco_questoes(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!admin_bq_has_column($pdo, 'admin_materia_topicos', 'parent_id')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_materia_topicos ADD COLUMN parent_id INT UNSIGNED DEFAULT NULL AFTER materia_id");
        }
        if (!admin_bq_has_column($pdo, 'admin_banco_questoes', 'curso_id')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN curso_id INT UNSIGNED DEFAULT NULL AFTER id");
        }
        if (!admin_bq_has_column($pdo, 'admin_banco_questoes', 'concurso_passado_id')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN concurso_passado_id INT UNSIGNED DEFAULT NULL AFTER curso_id");
        }
        if (!admin_bq_has_column($pdo, 'admin_banco_questoes', 'materia_topico_id')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN materia_topico_id INT UNSIGNED DEFAULT NULL AFTER topico_id");
        }
        if (!admin_bq_has_column($pdo, 'admin_banco_questoes', 'ano')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD COLUMN ano SMALLINT UNSIGNED DEFAULT NULL AFTER materia_topico_id");
        }
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_curso (curso_id)");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_concurso_passado (concurso_passado_id)");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_materia_topico (materia_topico_id)");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD INDEX idx_admin_banco_questoes_ano (ano)");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD CONSTRAINT fk_admin_banco_questoes_curso FOREIGN KEY (curso_id) REFERENCES admin_cursos(id) ON DELETE SET NULL");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD CONSTRAINT fk_admin_banco_questoes_concurso_passado FOREIGN KEY (concurso_passado_id) REFERENCES admin_concursos_passados(id) ON DELETE SET NULL");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_banco_questoes ADD CONSTRAINT fk_admin_banco_questoes_materia_topico FOREIGN KEY (materia_topico_id) REFERENCES admin_materia_topicos(id) ON DELETE SET NULL");

        // Migra o vínculo antigo curso_id para a nova tabela de acesso, sem perder regra antiga.
        admin_bq_try_exec($pdo, "INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id)
            SELECT id, curso_id FROM admin_banco_questoes WHERE curso_id IS NOT NULL AND curso_id > 0");

        // Correção do modelo: não usa tópico/subtópico de banca e não usa subtópico em matéria.
        admin_bq_try_exec($pdo, "UPDATE admin_banco_questoes SET topico_id = NULL WHERE topico_id IS NOT NULL");
        admin_bq_try_exec($pdo, "UPDATE admin_materia_topicos SET parent_id = NULL WHERE parent_id IS NOT NULL");
    }
}

if (!function_exists('admin_bq_int')) {
    function admin_bq_int(mixed $value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('admin_bq_ids')) {
    function admin_bq_ids(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn($v): int => max(0, (int)$v), $value), static fn(int $id): bool => $id > 0)));
        sort($ids);
        return $ids;
    }
}

if (!function_exists('admin_bq_status')) {
    function admin_bq_status(mixed $value): string
    {
        return in_array($value, ['ativo', 'inativo'], true) ? (string)$value : 'ativo';
    }
}

if (!function_exists('admin_bq_dificuldade')) {
    function admin_bq_dificuldade(mixed $value): string
    {
        return in_array($value, ['facil', 'medio', 'dificil'], true) ? (string)$value : 'medio';
    }
}

if (!function_exists('admin_bq_letra')) {
    function admin_bq_letra(int $index): string
    {
        $index = max(0, $index);
        $letras = '';
        do {
            $letras = chr(65 + ($index % 26)) . $letras;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $letras;
    }
}

if (!function_exists('admin_bq_format_date')) {
    function admin_bq_format_date(?string $data): string
    {
        if (!$data) {
            return '-';
        }
        try {
            return (new DateTime($data))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $data;
        }
    }
}

if (!function_exists('admin_bq_opcoes_da_questao')) {
    function admin_bq_opcoes_da_questao(PDO $pdo, int $questaoId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM admin_banco_questao_opcoes WHERE questao_id = :id ORDER BY ordem ASC, id ASC');
        $stmt->execute([':id' => $questaoId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('admin_bq_acessos_da_questao')) {
    function admin_bq_acessos_da_questao(PDO $pdo, int $questaoId): array
    {
        if ($questaoId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT curso_id FROM admin_banco_questao_acessos WHERE questao_id = :id ORDER BY curso_id ASC');
        $stmt->execute([':id' => $questaoId]);
        $ids = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0)));

        if (!$ids) {
            $stmtLegacy = $pdo->prepare('SELECT curso_id FROM admin_banco_questoes WHERE id = :id LIMIT 1');
            $stmtLegacy->execute([':id' => $questaoId]);
            $legacy = (int)$stmtLegacy->fetchColumn();
            if ($legacy > 0) {
                $ids[] = $legacy;
            }
        }

        return $ids;
    }
}

if (!function_exists('admin_bq_salvar_acessos')) {
    function admin_bq_salvar_acessos(PDO $pdo, int $questaoId, array $cursoIds): void
    {
        $cursoIds = admin_bq_ids($cursoIds);

        $pdo->prepare('DELETE FROM admin_banco_questao_acessos WHERE questao_id = :questao_id')->execute([':questao_id' => $questaoId]);

        if ($cursoIds) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO admin_banco_questao_acessos (questao_id, curso_id) VALUES (:questao_id, :curso_id)');
            foreach ($cursoIds as $cursoId) {
                $stmt->execute([':questao_id' => $questaoId, ':curso_id' => $cursoId]);
            }
        }

        $legacyCursoId = $cursoIds[0] ?? null;
        $stmtLegacy = $pdo->prepare('UPDATE admin_banco_questoes SET curso_id = :curso_id WHERE id = :id LIMIT 1');
        $stmtLegacy->bindValue(':id', $questaoId, PDO::PARAM_INT);
        if ($legacyCursoId !== null) {
            $stmtLegacy->bindValue(':curso_id', $legacyCursoId, PDO::PARAM_INT);
        } else {
            $stmtLegacy->bindValue(':curso_id', null, PDO::PARAM_NULL);
        }
        $stmtLegacy->execute();
    }
}

if (!function_exists('admin_bq_validar_opcoes')) {
    function admin_bq_validar_opcoes(array $post): array
    {
        $textos = $post['opcao_texto'] ?? [];
        $corretaIndex = isset($post['correta']) ? (int)$post['correta'] : -1;

        if (!is_array($textos)) {
            $textos = [];
        }

        $opcoes = [];
        foreach ($textos as $idx => $textoBruto) {
            $texto = trim((string)$textoBruto);
            if ($texto === '') {
                continue;
            }

            $opcoes[] = [
                'texto' => $texto,
                'correta' => ((int)$idx === $corretaIndex),
                'ordem' => count($opcoes) + 1,
                'letra' => admin_bq_letra(count($opcoes)),
            ];
        }

        if (count($opcoes) < 2) {
            throw new RuntimeException('Adicione pelo menos duas opções de resposta.');
        }

        $temCorreta = false;
        foreach ($opcoes as $opcao) {
            if ($opcao['correta']) {
                $temCorreta = true;
                break;
            }
        }

        if (!$temCorreta) {
            throw new RuntimeException('Selecione a resposta correta da questão.');
        }

        return $opcoes;
    }
}

if (!function_exists('admin_bq_salvar_opcoes')) {
    function admin_bq_salvar_opcoes(PDO $pdo, int $questaoId, array $opcoes): void
    {
        $pdo->prepare('DELETE FROM admin_banco_questao_opcoes WHERE questao_id = :questao_id')->execute([':questao_id' => $questaoId]);
        $stmt = $pdo->prepare('INSERT INTO admin_banco_questao_opcoes (questao_id, letra, texto, correta, ordem) VALUES (:questao_id, :letra, :texto, :correta, :ordem)');

        foreach ($opcoes as $opcao) {
            $stmt->execute([
                ':questao_id' => $questaoId,
                ':letra' => $opcao['letra'],
                ':texto' => $opcao['texto'],
                ':correta' => $opcao['correta'] ? 1 : 0,
                ':ordem' => (int)$opcao['ordem'],
            ]);
        }
    }
}

if (!function_exists('admin_bq_materia_topico_nome')) {
    function admin_bq_materia_topico_nome(array $topico): string
    {
        return (string)($topico['titulo'] ?? '');
    }
}

if (!function_exists('admin_bq_topico_nome')) {
    function admin_bq_topico_nome(array $topico): string
    {
        return (string)($topico['titulo'] ?? '');
    }
}
