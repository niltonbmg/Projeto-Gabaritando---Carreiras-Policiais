<?php
// Controle do vídeo de boas-vindas: aparece somente para contas novas
// e deixa de aparecer definitivamente após o usuário fechá-lo.
declare(strict_types=1);

if (!function_exists('gab_boas_vindas_bootstrap')) {
    function gab_boas_vindas_bootstrap(PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :tabela
                    AND COLUMN_NAME = :coluna'
            );
            $stmt->execute([
                ':tabela' => 'usuarios',
                ':coluna' => 'boas_vindas_visualizada',
            ]);

            if ((int)$stmt->fetchColumn() === 0) {
                // DEFAULT 1 garante que usuários que já existiam antes desta
                // atualização não recebam o vídeo novamente.
                $pdo->exec(
                    "ALTER TABLE usuarios
                     ADD COLUMN boas_vindas_visualizada TINYINT(1) NOT NULL DEFAULT 1"
                );
            }
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao preparar controle de boas-vindas: ' . $e->getMessage());
        }
    }
}

if (!function_exists('gab_boas_vindas_deve_exibir')) {
    function gab_boas_vindas_deve_exibir(PDO $pdo, int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        gab_boas_vindas_bootstrap($pdo);

        try {
            $stmt = $pdo->prepare(
                "SELECT boas_vindas_visualizada, tipo
                   FROM usuarios
                  WHERE id = :id
                  LIMIT 1"
            );
            $stmt->execute([':id' => $usuarioId]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return false;
            }

            // O vídeo é destinado ao aluno/cliente, não ao administrador.
            $tipo = strtolower(trim((string)($usuario['tipo'] ?? 'cliente')));
            if (in_array($tipo, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true)) {
                return false;
            }

            return (int)($usuario['boas_vindas_visualizada'] ?? 1) === 0;
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao consultar boas-vindas: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('gab_boas_vindas_marcar_visualizada')) {
    function gab_boas_vindas_marcar_visualizada(PDO $pdo, int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        gab_boas_vindas_bootstrap($pdo);

        try {
            $stmt = $pdo->prepare(
                'UPDATE usuarios
                    SET boas_vindas_visualizada = 1
                  WHERE id = :id'
            );
            return $stmt->execute([':id' => $usuarioId]);
        } catch (Throwable $e) {
            error_log('[Projeto Gabaritando] Falha ao salvar boas-vindas: ' . $e->getMessage());
            return false;
        }
    }
}
