<?php
// Notificações do aluno: cursos, módulos, aulas e eventos da agenda.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_file(__DIR__ . '/../api/conexao.php')) {
    require_once __DIR__ . '/../api/conexao.php';
}

if (is_file(__DIR__ . '/curso-acesso.php')) {
    require_once __DIR__ . '/curso-acesso.php';
}

if (!function_exists('gab_notif_bootstrap')) {
    function gab_notif_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_agenda_eventos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            data_evento DATE NOT NULL,
            hora_inicio TIME NULL,
            hora_fim TIME NULL,
            local_evento VARCHAR(180) NULL,
            link_evento VARCHAR(700) NULL,
            cor VARCHAR(20) NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_agenda_curso (curso_id),
            KEY idx_agenda_data (data_evento),
            KEY idx_agenda_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_notificacoes_leituras (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(30) NOT NULL,
            referencia_id BIGINT UNSIGNED NOT NULL,
            lida_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_usuario_notificacao (usuario_id, tipo, referencia_id),
            KEY idx_notificacao_usuario (usuario_id, lida_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('gab_notif_cursos_permitidos')) {
    function gab_notif_cursos_permitidos(PDO $pdo, int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return ['restrito' => false, 'ids' => []];
        }

        try {
            if (function_exists('gab_curso_acesso_usuario_admin') && gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
                return ['restrito' => false, 'ids' => []];
            }

            if (function_exists('gab_curso_acesso_configurado') && function_exists('gab_curso_acesso_ids')) {
                return [
                    'restrito' => gab_curso_acesso_configurado($pdo, $usuarioId),
                    'ids' => gab_curso_acesso_ids($pdo, $usuarioId),
                ];
            }

            $stmtConfigurado = $pdo->prepare('SELECT 1 FROM usuario_cursos WHERE usuario_id = :usuario_id LIMIT 1');
            $stmtConfigurado->execute([':usuario_id' => $usuarioId]);
            $restrito = (bool)$stmtConfigurado->fetchColumn();

            $stmt = $pdo->prepare("SELECT DISTINCT curso_id
                FROM usuario_cursos
                WHERE usuario_id = :usuario_id
                  AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')");
            $stmt->execute([':usuario_id' => $usuarioId]);
            $ids = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
            return ['restrito' => $restrito, 'ids' => $ids];
        } catch (Throwable $e) {
            return ['restrito' => false, 'ids' => []];
        }
    }
}

if (!function_exists('gab_notif_filtro_curso')) {
    function gab_notif_filtro_curso(array $acesso, string $coluna, array &$params, string $prefixo): string
    {
        if (empty($acesso['restrito'])) {
            return '';
        }
        if (empty($acesso['ids'])) {
            return ' AND 1 = 0';
        }

        $placeholders = [];
        foreach (array_values($acesso['ids']) as $idx => $id) {
            $chave = ':' . $prefixo . '_' . $idx;
            $placeholders[] = $chave;
            $params[$chave] = (int)$id;
        }

        return ' AND ' . $coluna . ' IN (' . implode(',', $placeholders) . ')';
    }
}

if (!function_exists('gab_notif_data_inicial')) {
    function gab_notif_data_inicial(PDO $pdo, int $usuarioId): string
    {
        $limite = (new DateTimeImmutable('-180 days'))->format('Y-m-d H:i:s');
        if ($usuarioId <= 0) {
            return $limite;
        }

        try {
            $stmt = $pdo->prepare('SELECT created_at FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $usuarioId]);
            $criado = trim((string)$stmt->fetchColumn());
            if ($criado !== '' && strtotime($criado) !== false && strtotime($criado) > strtotime($limite)) {
                return date('Y-m-d H:i:s', strtotime($criado));
            }
        } catch (Throwable $e) {
            // Mantém a janela padrão.
        }

        return $limite;
    }
}

if (!function_exists('gab_notif_resumir')) {
    function gab_notif_resumir(?string $texto, int $limite = 108): string
    {
        $texto = trim((string)$texto);
        if ($texto === '') {
            return '';
        }
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }
        return rtrim(mb_substr($texto, 0, $limite - 3)) . '...';
    }
}

if (!function_exists('gab_notif_tempo')) {
    function gab_notif_tempo(?string $data): string
    {
        $timestamp = $data ? strtotime($data) : false;
        if ($timestamp === false) {
            return '';
        }

        $agora = time();
        $diferenca = max(0, $agora - $timestamp);
        if ($diferenca < 60) return 'agora';
        if ($diferenca < 3600) return 'há ' . max(1, (int)floor($diferenca / 60)) . ' min';
        if ($diferenca < 86400) return 'há ' . max(1, (int)floor($diferenca / 3600)) . ' h';
        if ($diferenca < 604800) return 'há ' . max(1, (int)floor($diferenca / 86400)) . ' dia' . ((int)floor($diferenca / 86400) === 1 ? '' : 's');
        return date('d/m/Y', $timestamp);
    }
}

if (!function_exists('gab_notif_buscar')) {
    function gab_notif_buscar(PDO $pdo, int $usuarioId, int $limite = 30): array
    {
        gab_notif_bootstrap($pdo);
        $limite = max(1, min(80, $limite));
        $desde = gab_notif_data_inicial($pdo, $usuarioId);
        $acesso = gab_notif_cursos_permitidos($pdo, $usuarioId);
        $itens = [];

        try {
            $stmt = $pdo->prepare("SELECT c.id, c.nome, c.descricao, c.criado_em
                FROM admin_cursos c
                WHERE c.status = 'ativo' AND c.criado_em >= :desde
                ORDER BY c.criado_em DESC LIMIT 60");
            $stmt->execute([':desde' => $desde]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int)$row['id'];
                $itens[] = [
                    'tipo' => 'curso',
                    'referencia_id' => $id,
                    'titulo' => 'Novo curso: ' . (string)$row['nome'],
                    'descricao' => gab_notif_resumir((string)($row['descricao'] ?? 'Um novo curso foi disponibilizado na plataforma.')),
                    'link' => 'curso-modulos.php?curso_id=' . $id,
                    'icone' => 'graduation-cap',
                    'data' => (string)$row['criado_em'],
                ];
            }
        } catch (Throwable $e) {
            // A barra continua funcionando mesmo em bases antigas.
        }

        try {
            $params = [':desde' => $desde];
            $filtro = gab_notif_filtro_curso($acesso, 'm.curso_id', $params, 'modcurso');
            $stmt = $pdo->prepare("SELECT m.id, m.curso_id, m.titulo, m.descricao, m.criado_em, c.nome AS curso_nome
                FROM admin_curso_modulos m
                INNER JOIN admin_cursos c ON c.id = m.curso_id AND c.status = 'ativo'
                WHERE m.criado_em >= :desde {$filtro}
                ORDER BY m.criado_em DESC LIMIT 60");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $itens[] = [
                    'tipo' => 'modulo',
                    'referencia_id' => (int)$row['id'],
                    'titulo' => 'Novo módulo: ' . (string)$row['titulo'],
                    'descricao' => gab_notif_resumir('Curso ' . (string)$row['curso_nome'] . '. ' . (string)($row['descricao'] ?? '')),
                    'link' => 'curso-modulos.php?curso_id=' . (int)$row['curso_id'] . '#modulo-' . (int)$row['id'],
                    'icone' => 'layers-3',
                    'data' => (string)$row['criado_em'],
                ];
            }
        } catch (Throwable $e) {
        }

        try {
            $params = [':desde' => $desde];
            $filtro = gab_notif_filtro_curso($acesso, 'a.curso_id', $params, 'aulacurso');
            $stmt = $pdo->prepare("SELECT a.id, a.curso_id, a.modulo_id, a.titulo, a.descricao, a.criado_em,
                       c.nome AS curso_nome, m.titulo AS modulo_nome
                FROM admin_curso_aulas a
                INNER JOIN admin_cursos c ON c.id = a.curso_id AND c.status = 'ativo'
                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                WHERE a.criado_em >= :desde {$filtro}
                ORDER BY a.criado_em DESC LIMIT 80");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $itens[] = [
                    'tipo' => 'aula',
                    'referencia_id' => (int)$row['id'],
                    'titulo' => 'Nova aula: ' . (string)$row['titulo'],
                    'descricao' => gab_notif_resumir((string)$row['curso_nome'] . (!empty($row['modulo_nome']) ? ' • ' . (string)$row['modulo_nome'] : '')),
                    'link' => 'aulas.php?curso_id=' . (int)$row['curso_id'] . '&modulo_id=' . (int)$row['modulo_id'] . '&aula_id=' . (int)$row['id'],
                    'icone' => 'play-circle',
                    'data' => (string)$row['criado_em'],
                ];
            }
        } catch (Throwable $e) {
        }

        try {
            $params = [':desde' => $desde];
            $filtro = gab_notif_filtro_curso($acesso, 'e.curso_id', $params, 'eventocurso');
            $stmt = $pdo->prepare("SELECT e.id, e.curso_id, e.titulo, e.descricao, e.data_evento, e.hora_inicio, e.criado_em, c.nome AS curso_nome
                FROM admin_agenda_eventos e
                INNER JOIN admin_cursos c ON c.id = e.curso_id AND c.status = 'ativo'
                WHERE e.status = 'ativo' AND e.criado_em >= :desde {$filtro}
                ORDER BY e.criado_em DESC LIMIT 60");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $dataEvento = date('d/m/Y', strtotime((string)$row['data_evento']));
                $hora = !empty($row['hora_inicio']) ? ' às ' . substr((string)$row['hora_inicio'], 0, 5) : '';
                $itens[] = [
                    'tipo' => 'evento',
                    'referencia_id' => (int)$row['id'],
                    'titulo' => 'Novo evento: ' . (string)$row['titulo'],
                    'descricao' => gab_notif_resumir((string)$row['curso_nome'] . ' • ' . $dataEvento . $hora),
                    'link' => 'agenda.php?data=' . urlencode((string)$row['data_evento']) . '&view=semana&curso_id=' . (int)$row['curso_id'] . '#evento-' . (int)$row['id'],
                    'icone' => 'calendar-plus',
                    'data' => (string)$row['criado_em'],
                ];
            }
        } catch (Throwable $e) {
        }

        usort($itens, static function (array $a, array $b): int {
            return strcmp((string)$b['data'], (string)$a['data']);
        });

        $leituras = [];
        if ($usuarioId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT tipo, referencia_id FROM usuario_notificacoes_leituras WHERE usuario_id = :usuario_id');
                $stmt->execute([':usuario_id' => $usuarioId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $leituras[(string)$row['tipo'] . ':' . (int)$row['referencia_id']] = true;
                }
            } catch (Throwable $e) {
            }
        }

        $itens = array_slice($itens, 0, $limite);
        foreach ($itens as &$item) {
            $chave = (string)$item['tipo'] . ':' . (int)$item['referencia_id'];
            $item['lida'] = isset($leituras[$chave]);
            $item['tempo'] = gab_notif_tempo((string)$item['data']);
        }
        unset($item);

        return $itens;
    }
}

if (!function_exists('gab_notif_marcar_lida')) {
    function gab_notif_marcar_lida(PDO $pdo, int $usuarioId, string $tipo, int $referenciaId): void
    {
        if ($usuarioId <= 0 || $referenciaId <= 0 || !in_array($tipo, ['curso', 'modulo', 'aula', 'evento'], true)) {
            return;
        }
        gab_notif_bootstrap($pdo);
        $stmt = $pdo->prepare("INSERT INTO usuario_notificacoes_leituras (usuario_id, tipo, referencia_id, lida_em)
            VALUES (:usuario_id, :tipo, :referencia_id, NOW())
            ON DUPLICATE KEY UPDATE lida_em = VALUES(lida_em)");
        $stmt->execute([':usuario_id' => $usuarioId, ':tipo' => $tipo, ':referencia_id' => $referenciaId]);
    }
}

if (!function_exists('gab_notif_marcar_todas')) {
    function gab_notif_marcar_todas(PDO $pdo, int $usuarioId): void
    {
        foreach (gab_notif_buscar($pdo, $usuarioId, 80) as $item) {
            gab_notif_marcar_lida($pdo, $usuarioId, (string)$item['tipo'], (int)$item['referencia_id']);
        }
    }
}
