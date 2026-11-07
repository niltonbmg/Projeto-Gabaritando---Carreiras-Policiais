<?php
// Controle centralizado de acesso dos usuários aos cursos.
declare(strict_types=1);

if (!function_exists('gab_curso_acesso_bootstrap')) {
    function gab_curso_acesso_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cursos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            status ENUM('ativo','pago','aprovado','liberado','concluido','matriculado','inativo','cancelado') NOT NULL DEFAULT 'ativo',
            origem VARCHAR(60) DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_usuario_cursos_usuario_curso (usuario_id, curso_id),
            KEY idx_usuario_cursos_usuario (usuario_id),
            KEY idx_usuario_cursos_curso (curso_id),
            KEY idx_usuario_cursos_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('gab_curso_acesso_usuario_admin')) {
    function gab_curso_acesso_usuario_admin(PDO $pdo, int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            $tipo = mb_strtolower(trim((string)$stmt->fetchColumn()), 'UTF-8');
            return in_array($tipo, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gab_curso_acesso_configurado')) {
    function gab_curso_acesso_configurado(PDO $pdo, int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        try {
            gab_curso_acesso_bootstrap($pdo);
            $stmt = $pdo->prepare("SELECT 1 FROM usuario_cursos WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$usuarioId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gab_curso_acesso_ids')) {
    function gab_curso_acesso_ids(PDO $pdo, int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        try {
            gab_curso_acesso_bootstrap($pdo);
            $stmt = $pdo->prepare("SELECT DISTINCT curso_id
                FROM usuario_cursos
                WHERE usuario_id = ?
                  AND curso_id > 0
                  AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')");
            $stmt->execute([$usuarioId]);
            return array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
        } catch (Throwable $e) {
            return [];
        }
    }
}


if (!function_exists('gab_curso_acesso_marcar_usuario_sem_cursos')) {
    /**
     * Marca um usuário recém-cadastrado como já configurado, porém sem nenhum curso liberado.
     *
     * Isso evita o comportamento antigo de liberar todos os cursos quando não existe nenhuma
     * linha em usuario_cursos. O registro curso_id = 0 funciona como marcador interno e não
     * aparece para o aluno nem para a lista de cursos liberados.
     */
    function gab_curso_acesso_marcar_usuario_sem_cursos(PDO $pdo, int $usuarioId, string $origem = 'cadastro_sem_curso'): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        try {
            gab_curso_acesso_bootstrap($pdo);

            $stmtMarcador = $pdo->prepare("INSERT INTO usuario_cursos
                (usuario_id, curso_id, status, origem, criado_em)
                VALUES (:usuario_id, 0, 'inativo', :origem, NOW())
                ON DUPLICATE KEY UPDATE
                    status = 'inativo',
                    origem = VALUES(origem),
                    atualizado_em = NOW()");
            $stmtMarcador->execute([
                ':usuario_id' => $usuarioId,
                ':origem' => $origem,
            ]);

            try {
                $stmtTabelaCursos = $pdo->query("SHOW TABLES LIKE 'admin_cursos'");
                if ($stmtTabelaCursos && $stmtTabelaCursos->fetchColumn()) {
                    $stmtCursosInativos = $pdo->prepare("INSERT INTO usuario_cursos
                        (usuario_id, curso_id, status, origem, criado_em)
                        SELECT :usuario_id, c.id, 'inativo', :origem, NOW()
                        FROM admin_cursos c
                        WHERE c.id > 0
                        ON DUPLICATE KEY UPDATE
                            atualizado_em = atualizado_em");
                    $stmtCursosInativos->execute([
                        ':usuario_id' => $usuarioId,
                        ':origem' => $origem,
                    ]);
                }
            } catch (Throwable $e) {
                // O marcador curso_id = 0 já garante que o usuário não comece com acesso aos cursos.
            }

            return true;
        } catch (Throwable $e) {
            error_log('[Gabaritando] Falha ao iniciar usuário sem cursos: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('gab_usuario_tem_acesso_curso')) {
    function gab_usuario_tem_acesso_curso(PDO $pdo, int $usuarioId, int $cursoId): bool
    {
        if ($usuarioId <= 0 || $cursoId <= 0) {
            return false;
        }

        if (gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
            return true;
        }

        try {
            gab_curso_acesso_bootstrap($pdo);

            // Compatibilidade com usuários antigos: enquanto o acesso nunca tiver sido
            // configurado pelo admin, o comportamento anterior permanece liberado.
            if (!gab_curso_acesso_configurado($pdo, $usuarioId)) {
                return true;
            }

            $stmt = $pdo->prepare("SELECT 1
                FROM usuario_cursos
                WHERE usuario_id = ?
                  AND curso_id > 0
                  AND curso_id = ?
                  AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')
                LIMIT 1");
            $stmt->execute([$usuarioId, $cursoId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Não bloqueia a plataforma inteira se o banco estiver temporariamente indisponível.
            return true;
        }
    }
}

if (!function_exists('gab_curso_acesso_sql')) {
    /**
     * Retorna o SQL e os parâmetros para filtrar uma coluna de curso.
     * Quando o usuário ainda não possui configuração, não aplica filtro (compatibilidade).
     */
    function gab_curso_acesso_sql(PDO $pdo, int $usuarioId, string $coluna, string $prefixo = 'curso_acesso'): array
    {
        if ($usuarioId <= 0 || gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
            return ['', []];
        }

        if (!gab_curso_acesso_configurado($pdo, $usuarioId)) {
            return ['', []];
        }

        $ids = gab_curso_acesso_ids($pdo, $usuarioId);
        if (!$ids) {
            return [' AND 1 = 0', []];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $indice => $id) {
            $chave = ':' . $prefixo . '_' . $indice;
            $placeholders[] = $chave;
            $params[$chave] = $id;
        }

        return [' AND ' . $coluna . ' IN (' . implode(',', $placeholders) . ')', $params];
    }
}
