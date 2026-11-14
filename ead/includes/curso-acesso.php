<?php
// Controle centralizado de acesso dos usuários aos cursos.
declare(strict_types=1);

if (!function_exists('gab_curso_acesso_bootstrap')) {
    function gab_curso_acesso_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
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

// ---------------- Prazo de acesso (Sprint 2A) ----------------
// usuario_cursos.data_fim: último dia de acesso (NULL = vitalício).
// admin_cursos: acesso_tipo (vitalicio | dias | meses | data), acesso_qtd, acesso_data, renovar_url.
if (!function_exists('gab_prazo_bootstrap')) {
    function gab_prazo_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        gab_curso_acesso_bootstrap($pdo);
        $cols = static function (string $t) use ($pdo): array {
            try {
                return array_map('strtolower', array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field'));
            } catch (Throwable $e) {
                return [];
            }
        };
        if (!in_array('data_fim', $cols('usuario_cursos'), true)) {
            $pdo->exec('ALTER TABLE usuario_cursos ADD COLUMN data_fim DATE NULL DEFAULT NULL');
        }
        $c = $cols('admin_cursos');
        if ($c) {
            foreach (['acesso_tipo' => "VARCHAR(10) NOT NULL DEFAULT 'vitalicio'", 'acesso_qtd' => 'INT UNSIGNED NULL', 'acesso_data' => 'DATE NULL', 'renovar_url' => 'VARCHAR(500) NULL'] as $col => $def) {
                if (!in_array($col, $c, true)) {
                    $pdo->exec("ALTER TABLE admin_cursos ADD COLUMN `$col` $def");
                }
            }
        }
    }

    /** Regra padrão do curso. */
    function gab_prazo_regra(PDO $pdo, int $cursoId): array
    {
        gab_prazo_bootstrap($pdo);
        $st = $pdo->prepare('SELECT acesso_tipo, acesso_qtd, acesso_data, renovar_url FROM admin_cursos WHERE id = :id');
        $st->execute([':id' => $cursoId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $tipo = in_array($r['acesso_tipo'] ?? '', ['vitalicio', 'dias', 'meses', 'data'], true) ? $r['acesso_tipo'] : 'vitalicio';
        return ['tipo' => $tipo, 'qtd' => (int)($r['acesso_qtd'] ?? 0), 'data' => (string)($r['acesso_data'] ?? ''), 'renovar_url' => trim((string)($r['renovar_url'] ?? ''))];
    }

    /** Último dia de acesso pela regra do curso, a partir da matrícula (Y-m-d) ou NULL = vitalício. */
    function gab_prazo_data_padrao(PDO $pdo, int $cursoId, ?string $inicio = null): ?string
    {
        $r = gab_prazo_regra($pdo, $cursoId);
        $base = new DateTimeImmutable($inicio ?: 'today');
        if ($r['tipo'] === 'dias' && $r['qtd'] > 0) {
            return $base->modify('+' . $r['qtd'] . ' days')->format('Y-m-d');
        }
        if ($r['tipo'] === 'meses' && $r['qtd'] > 0) {
            return $base->modify('+' . $r['qtd'] . ' months')->format('Y-m-d');
        }
        if ($r['tipo'] === 'data' && $r['data'] !== '') {
            return $r['data'];
        }
        return null;
    }

    /** Situação do acesso: ['data_fim' => ?Y-m-d, 'vencido' => bool, 'dias' => ?int restantes]. Admin nunca vence. */
    function gab_prazo_situacao(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        $vazio = ['data_fim' => null, 'vencido' => false, 'dias' => null];
        if ($usuarioId <= 0 || $cursoId <= 0 || gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
            return $vazio;
        }
        try {
            gab_prazo_bootstrap($pdo);
            $st = $pdo->prepare('SELECT data_fim FROM usuario_cursos WHERE usuario_id = :u AND curso_id = :c');
            $st->execute([':u' => $usuarioId, ':c' => $cursoId]);
            $fim = $st->fetchColumn();
            if (!$fim) {
                return $vazio;
            }
            $dias = (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable((string)$fim))->format('%r%a');
            return ['data_fim' => (string)$fim, 'vencido' => $dias < 0, 'dias' => $dias];
        } catch (Throwable $e) {
            return $vazio;
        }
    }

    /** Matrícula nova: aplica a data padrão do curso (se ainda não tiver data). */
    function gab_prazo_aplicar_padrao(PDO $pdo, int $usuarioId, int $cursoId): void
    {
        gab_prazo_bootstrap($pdo);
        $fim = gab_prazo_data_padrao($pdo, $cursoId);
        $pdo->prepare('UPDATE usuario_cursos SET data_fim = :f WHERE usuario_id = :u AND curso_id = :c AND data_fim IS NULL')
            ->execute([':f' => $fim, ':u' => $usuarioId, ':c' => $cursoId]);
    }

    /** Tela "Seu acesso venceu": painel e progresso continuam guardados. Encerra a página. */
    function gab_prazo_tela_vencido(PDO $pdo, int $cursoId, string $dataFim, string $nomeCurso = ''): void
    {
        $r = gab_prazo_regra($pdo, $cursoId);
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        if ($nomeCurso === '') {
            $stN = $pdo->prepare('SELECT nome FROM admin_cursos WHERE id = :id');
            $stN->execute([':id' => $cursoId]);
            $nomeCurso = (string)$stN->fetchColumn();
        }
        http_response_code(403);
        ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Acesso vencido | Projeto Gabaritando</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
<style>body{margin:0;background:#0c0c0d;color:#f3f3f4;font-family:'Ubuntu',system-ui,sans-serif}.pv{max-width:560px;margin:48px auto;padding:0 16px}.pv-c{background:#161618;border:1px solid #4a3a10;border-radius:18px;padding:26px;display:grid;gap:14px;text-align:center}.pv-c h1{margin:0;font-size:24px}.pv-c p{margin:0;color:#c9c9cf;line-height:1.55}.pv-bt{display:inline-block;border-radius:12px;padding:12px 18px;background:#fbb928;color:#111;font-weight:700;text-decoration:none;margin:4px}.pv-bt.l{background:transparent;color:#f3f3f4;border:1px solid #2a2a2e}</style></head><body>
<?php if (is_file(__DIR__ . '/barra-superior.php')) { include __DIR__ . '/barra-superior.php'; } ?>
<main class="pv"><section class="pv-c" aria-labelledby="pvT">
    <div style="font-size:44px" aria-hidden="true">⏳</div>
    <h1 id="pvT">Seu acesso venceu em <?php echo $e(date('d/m/Y', strtotime($dataFim))); ?></h1>
    <p><?php echo $nomeCurso !== '' ? 'Curso: <strong>' . $e($nomeCurso) . '</strong>. ' : ''; ?>Seu progresso, suas questões e suas anotações continuam guardados: ao renovar, você continua de onde parou.</p>
    <div><?php if ($r['renovar_url'] !== ''): ?><a class="pv-bt" href="<?php echo $e($r['renovar_url']); ?>" target="_blank" rel="noopener">Renovar meu acesso</a><?php endif; ?>
        <a class="pv-bt <?php echo $r['renovar_url'] !== '' ? 'l' : ''; ?>" href="suporte.php">Falar com o suporte</a>
        <a class="pv-bt l" href="dashboard.php">Voltar ao painel</a></div>
</section></main></body></html><?php
        exit;
    }
}

// ---------------- Cargo do aluno = cargo do curso comprado ----------------
if (!function_exists('gab_cargo_alinhar')) {
    /**
     * O cargo do aluno ("Meu concurso") sempre segue o curso que ele comprou/tem liberado.
     * Se o cargo atual aponta para um curso que ele não tem (ex.: comprou PM e está como BM), troca para
     * o cargo do curso liberado (preferindo o mesmo concurso; com $cursoId, usa esse curso). Sem curso ligado a cargo, não mexe.
     * Devolve o cargo_id final (0 se nada a fazer).
     */
    function gab_cargo_alinhar(PDO $pdo, int $usuarioId, int $cursoId = 0): int
    {
        static $feito = [];
        $chave = $usuarioId . ':' . $cursoId;
        if ($usuarioId <= 0 || isset($feito[$chave])) {
            return $feito[$chave] ?? 0;
        }
        $feito[$chave] = 0;
        try {
            $st = $pdo->prepare("SELECT curso_id FROM usuario_cursos WHERE usuario_id = :u AND status IN ('ativo','pago','aprovado','liberado','matriculado')");
            $st->execute([':u' => $usuarioId]);
            $meus = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            if ($cursoId > 0) {
                $meus = in_array($cursoId, $meus, true) ? [$cursoId] : $meus;
            }
            if (!$meus) {
                return 0;
            }
            $st = $pdo->prepare('SELECT ac.concurso_id, ac.cargo_id, cg.curso_id FROM aluno_concurso ac LEFT JOIN concurso_cargos cg ON cg.id = ac.cargo_id WHERE ac.usuario_id = :u');
            $st->execute([':u' => $usuarioId]);
            $atual = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($atual && in_array((int)$atual['curso_id'], $meus, true) && ($cursoId <= 0 || (int)$atual['curso_id'] === $cursoId)) {
                return $feito[$chave] = (int)$atual['cargo_id'];
            }
            $in = implode(',', $meus);
            $st = $pdo->prepare("SELECT id, concurso_id FROM concurso_cargos WHERE curso_id IN ($in)
                                  ORDER BY (concurso_id = :c) DESC, FIELD(curso_id, $in), id DESC LIMIT 1");
            $st->execute([':c' => (int)($atual['concurso_id'] ?? 0)]);
            $novo = $st->fetch(PDO::FETCH_ASSOC);
            if (!$novo) {
                return 0;
            }
            $pdo->prepare('INSERT INTO aluno_concurso (usuario_id, concurso_id, cargo_id) VALUES (:u, :c, :g) ON DUPLICATE KEY UPDATE concurso_id = VALUES(concurso_id), cargo_id = VALUES(cargo_id)')
                ->execute([':u' => $usuarioId, ':c' => (int)$novo['concurso_id'], ':g' => (int)$novo['id']]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['gab_missao_' . $usuarioId]);
            }
            return $feito[$chave] = (int)$novo['id'];
        } catch (Throwable $e) {
            return 0;
        }
    }
}
