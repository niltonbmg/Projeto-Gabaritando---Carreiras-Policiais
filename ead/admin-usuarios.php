<?php
// admin-usuarios.php
// Gestão de usuários cadastrados no Projeto Gabaritando.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/boas-vindas.php';

if (!function_exists('admin_usuarios_e')) {
    function admin_usuarios_e($valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_usuarios_colunas')) {
    function admin_usuarios_colunas(PDO $pdo): array
    {
        static $colunas = null;
        if ($colunas !== null) {
            return $colunas;
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
            $colunas = array_map(static fn(array $row): string => (string)$row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            $colunas = [];
        }

        return $colunas;
    }
}

if (!function_exists('admin_usuarios_tem_coluna')) {
    function admin_usuarios_tem_coluna(array $colunas, string $coluna): bool
    {
        return in_array($coluna, $colunas, true);
    }
}

if (!function_exists('admin_usuarios_redirect')) {
    function admin_usuarios_redirect(array $params = []): void
    {
        $base = 'admin-usuarios';
        $queryAtual = $_GET;
        unset($queryAtual['msg'], $queryAtual['erro']);
        foreach ($params as $chave => $valor) {
            if ($valor === null) {
                unset($queryAtual[$chave]);
            } else {
                $queryAtual[$chave] = $valor;
            }
        }
        $query = http_build_query($queryAtual);
        header('Location: ' . $base . ($query !== '' ? '?' . $query : ''));
        exit;
    }
}

if (!function_exists('admin_usuarios_data')) {
    function admin_usuarios_data($valor): string
    {
        $valor = trim((string)$valor);
        if ($valor === '' || $valor === '0000-00-00 00:00:00' || $valor === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($valor);
        return $ts ? date('d/m/Y H:i', $ts) : $valor;
    }
}

if (!function_exists('admin_usuarios_count')) {
    function admin_usuarios_count(PDO $pdo, string $where = '', array $params = []): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios' . ($where !== '' ? ' WHERE ' . $where : '');
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('admin_usuarios_senha_coluna')) {
    function admin_usuarios_senha_coluna(array $colunas): string
    {
        foreach (['senha_hash', 'password_hash', 'senha', 'password', 'senha_usuario'] as $coluna) {
            if (admin_usuarios_tem_coluna($colunas, $coluna)) {
                return $coluna;
            }
        }
        return '';
    }
}

if (!function_exists('admin_usuarios_inserir_campo')) {
    function admin_usuarios_inserir_campo(array &$campos, array &$params, array $colunas, string $coluna, mixed $valor): void
    {
        if (admin_usuarios_tem_coluna($colunas, $coluna)) {
            $campos[$coluna] = ':' . $coluna;
            $params[':' . $coluna] = $valor;
        }
    }
}

if (!function_exists('admin_usuarios_table_count')) {
    function admin_usuarios_table_count(PDO $pdo, string $table, string $where = '', array $params = []): int
    {
        try {
            $safe = str_replace('`', '``', $table);
            $sql = "SELECT COUNT(*) FROM `{$safe}`" . ($where !== '' ? ' WHERE ' . $where : '');
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}


if (!function_exists('admin_usuarios_acesso_bootstrap')) {
    function admin_usuarios_acesso_bootstrap(PDO $pdo): void
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

if (!function_exists('admin_usuarios_listar_cursos')) {
    function admin_usuarios_listar_cursos(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT id, nome, status FROM admin_cursos ORDER BY ordem ASC, nome ASC, id ASC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_usuarios_normalizar_cursos')) {
    function admin_usuarios_normalizar_cursos(PDO $pdo, mixed $valor): array
    {
        $idsRecebidos = is_array($valor) ? $valor : [];
        $idsRecebidos = array_values(array_unique(array_filter(array_map('intval', $idsRecebidos), static fn(int $id): bool => $id > 0)));
        if (!$idsRecebidos) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsRecebidos), '?'));
        $stmt = $pdo->prepare("SELECT id FROM admin_cursos WHERE id IN ({$placeholders})");
        $stmt->execute($idsRecebidos);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}

if (!function_exists('admin_usuarios_cursos_selecionados')) {
    function admin_usuarios_cursos_selecionados(PDO $pdo, int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        try {
            admin_usuarios_acesso_bootstrap($pdo);
            $stmt = $pdo->prepare("SELECT curso_id FROM usuario_cursos
                WHERE usuario_id = ?
                  AND curso_id > 0
                  AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')
                ORDER BY curso_id ASC");
            $stmt->execute([$usuarioId]);
            return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_usuarios_salvar_acessos')) {
    function admin_usuarios_salvar_acessos(PDO $pdo, int $usuarioId, array $cursosSelecionados): void
    {
        if ($usuarioId <= 0) {
            throw new RuntimeException('Usuário inválido para configurar os cursos.');
        }

        $selecionados = array_fill_keys(array_map('intval', $cursosSelecionados), true);
        $todosCursos = admin_usuarios_listar_cursos($pdo);

        // Marcador interno: garante que o aluno pode ficar sem curso liberado
        // até o admin atribuir manualmente. Não aparece na lista de cursos.
        $stmtMarcador = $pdo->prepare("INSERT INTO usuario_cursos
            (usuario_id, curso_id, status, origem, criado_em)
            VALUES (:usuario_id, 0, 'inativo', 'admin', NOW())
            ON DUPLICATE KEY UPDATE
                status = 'inativo',
                origem = 'admin',
                atualizado_em = NOW()");
        $stmtMarcador->execute([':usuario_id' => $usuarioId]);

        $stmtSalvar = $pdo->prepare("INSERT INTO usuario_cursos
            (usuario_id, curso_id, status, origem, criado_em)
            VALUES (:usuario_id, :curso_id, :status, 'admin', NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                origem = 'admin',
                atualizado_em = NOW()");

        foreach ($todosCursos as $curso) {
            $cursoId = (int)($curso['id'] ?? 0);
            if ($cursoId <= 0) {
                continue;
            }
            $stmtSalvar->execute([
                ':usuario_id' => $usuarioId,
                ':curso_id' => $cursoId,
                ':status' => isset($selecionados[$cursoId]) ? 'liberado' : 'inativo',
            ]);
        }
    }
}

if (!function_exists('admin_usuarios_montar_where')) {
    function admin_usuarios_montar_where(array $colunas, string $busca, string $status, string $tipo): array
    {
        $where = [];
        $params = [];

        if ($busca !== '') {
            $buscaWhere = [];
            foreach (['nome', 'email', 'telefone'] as $coluna) {
                if (admin_usuarios_tem_coluna($colunas, $coluna)) {
                    $buscaWhere[] = "`{$coluna}` LIKE :busca";
                }
            }
            if ($buscaWhere) {
                $where[] = '(' . implode(' OR ', $buscaWhere) . ')';
                $params[':busca'] = '%' . $busca . '%';
            }
        }

        if ($status !== '' && admin_usuarios_tem_coluna($colunas, 'status')) {
            $where[] = '`status` = :status';
            $params[':status'] = $status;
        }

        if ($tipo !== '' && admin_usuarios_tem_coluna($colunas, 'tipo')) {
            $where[] = '`tipo` = :tipo';
            $params[':tipo'] = $tipo;
        }

        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }
}

$pdo = db();
gab_boas_vindas_bootstrap($pdo);
$colunas = admin_usuarios_colunas($pdo);
$adminAtual = auth_admin_user();
$adminId = (int)($adminAtual['id'] ?? 0);
admin_usuarios_acesso_bootstrap($pdo);

$mensagem = (string)($_GET['msg'] ?? '');
$erro = (string)($_GET['erro'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);

    try {
        if ($acao === 'criar_usuario') {
            $nomeNovo = trim((string)($_POST['nome'] ?? ''));
            $emailNovo = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
            $telefoneNovo = trim((string)($_POST['telefone'] ?? ''));
            $senhaNova = (string)($_POST['senha'] ?? '');
            $tipoNovo = (string)($_POST['tipo'] ?? 'cliente');
            $statusNovo = (string)($_POST['status'] ?? 'ativo');
            $cursosNovo = admin_usuarios_normalizar_cursos($pdo, $_POST['cursos'] ?? []);

            if ($nomeNovo === '') {
                throw new RuntimeException('Informe o nome do usuário.');
            }
            if ($emailNovo === '' || !filter_var($emailNovo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }
            if (strlen($senhaNova) < 6) {
                throw new RuntimeException('A senha precisa ter pelo menos 6 caracteres.');
            }
            if (!in_array($tipoNovo, ['cliente', 'admin'], true)) {
                $tipoNovo = 'cliente';
            }
            if (!in_array($statusNovo, ['ativo', 'inativo', 'bloqueado'], true)) {
                $statusNovo = 'ativo';
            }

            $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
            $stmtEmail->execute([$emailNovo]);
            if ($stmtEmail->fetch()) {
                throw new RuntimeException('Já existe usuário cadastrado com esse e-mail.');
            }

            $colunaSenha = admin_usuarios_senha_coluna($colunas);
            if ($colunaSenha === '') {
                throw new RuntimeException('Não encontrei coluna de senha na tabela usuarios.');
            }

            $campos = [];
            $paramsInsert = [];
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, 'nome', $nomeNovo);
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, 'email', $emailNovo);
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, 'telefone', $telefoneNovo);
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, 'tipo', $tipoNovo);
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, 'status', $statusNovo);
            admin_usuarios_inserir_campo(
                $campos,
                $paramsInsert,
                $colunas,
                'boas_vindas_visualizada',
                $tipoNovo === 'cliente' ? 0 : 1
            );
            admin_usuarios_inserir_campo($campos, $paramsInsert, $colunas, $colunaSenha, password_hash($senhaNova, PASSWORD_DEFAULT));
            if (admin_usuarios_tem_coluna($colunas, 'created_at')) {
                $campos['created_at'] = 'NOW()';
            }
            if (admin_usuarios_tem_coluna($colunas, 'updated_at')) {
                $campos['updated_at'] = 'NOW()';
            }
            if (!$campos) {
                throw new RuntimeException('Não foi possível montar os campos do cadastro.');
            }

            $pdo->beginTransaction();
            try {
                $sqlInsert = 'INSERT INTO usuarios (`' . implode('`, `', array_keys($campos)) . '`) VALUES (' . implode(', ', array_values($campos)) . ')';
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute($paramsInsert);
                $novoUsuarioId = (int)$pdo->lastInsertId();
                admin_usuarios_salvar_acessos($pdo, $novoUsuarioId, $cursosNovo);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            admin_usuarios_redirect(['modal' => null, 'editar' => null, 'msg' => 'Usuário criado e acessos aos cursos salvos com sucesso.']);
        }

        if ($usuarioId <= 0) {
            throw new RuntimeException('Usuário não informado.');
        }

        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        $usuarioAlvo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuarioAlvo) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        if ($acao === 'editar_usuario') {
            $nomeEditar = trim((string)($_POST['nome'] ?? ''));
            $emailEditar = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
            $telefoneEditar = trim((string)($_POST['telefone'] ?? ''));
            $tipoEditar = (string)($_POST['tipo'] ?? 'cliente');
            $statusEditar = (string)($_POST['status'] ?? 'ativo');
            $cursosEditar = admin_usuarios_normalizar_cursos($pdo, $_POST['cursos'] ?? []);

            if ($nomeEditar === '') {
                throw new RuntimeException('Informe o nome do usuário.');
            }
            if ($emailEditar === '' || !filter_var($emailEditar, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }
            if (!in_array($tipoEditar, ['cliente', 'admin'], true)) {
                $tipoEditar = 'cliente';
            }
            if (!in_array($statusEditar, ['ativo', 'inativo', 'bloqueado'], true)) {
                $statusEditar = 'ativo';
            }
            if ($usuarioId === $adminId && ($tipoEditar !== 'admin' || $statusEditar !== 'ativo')) {
                throw new RuntimeException('Você não pode remover o próprio acesso administrativo nem inativar a própria conta.');
            }

            $stmtEmail = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
            $stmtEmail->execute([$emailEditar, $usuarioId]);
            if ($stmtEmail->fetch()) {
                throw new RuntimeException('Já existe outro usuário cadastrado com esse e-mail.');
            }

            $sets = [];
            $paramsUpdate = [];
            foreach ([
                'nome' => $nomeEditar,
                'email' => $emailEditar,
                'telefone' => $telefoneEditar,
                'tipo' => $tipoEditar,
                'status' => $statusEditar,
            ] as $campo => $valor) {
                if (admin_usuarios_tem_coluna($colunas, $campo)) {
                    $sets[] = "`{$campo}` = :{$campo}";
                    $paramsUpdate[':' . $campo] = $valor;
                }
            }
            if (admin_usuarios_tem_coluna($colunas, 'updated_at')) {
                $sets[] = '`updated_at` = NOW()';
            }
            if (!$sets) {
                throw new RuntimeException('Não encontrei campos editáveis na tabela usuarios.');
            }
            $paramsUpdate[':usuario_id'] = $usuarioId;

            $pdo->beginTransaction();
            try {
                $stmtUpdate = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = :usuario_id LIMIT 1');
                $stmtUpdate->execute($paramsUpdate);
                admin_usuarios_salvar_acessos($pdo, $usuarioId, $cursosEditar);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            admin_usuarios_redirect(['editar' => null, 'ver' => null, 'senha' => null, 'msg' => 'Usuário e acessos aos cursos atualizados com sucesso.']);
        }

        if ($acao === 'bloquear') {
            if ($usuarioId === $adminId) {
                throw new RuntimeException('Você não pode bloquear o próprio usuário administrador.');
            }
            if (!admin_usuarios_tem_coluna($colunas, 'status')) {
                throw new RuntimeException('A tabela usuarios não tem coluna status.');
            }
            $pdo->prepare("UPDATE usuarios SET status = 'bloqueado' WHERE id = ? LIMIT 1")->execute([$usuarioId]);
            admin_usuarios_redirect(['msg' => 'Usuário bloqueado com sucesso.']);
        }

        if ($acao === 'desbloquear') {
            if (!admin_usuarios_tem_coluna($colunas, 'status')) {
                throw new RuntimeException('A tabela usuarios não tem coluna status.');
            }
            $pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ? LIMIT 1")->execute([$usuarioId]);
            admin_usuarios_redirect(['msg' => 'Usuário desbloqueado com sucesso.']);
        }

        if ($acao === 'excluir') {
            if ($usuarioId === $adminId) {
                throw new RuntimeException('Você não pode excluir o próprio usuário administrador.');
            }

            try {
                $pdo->prepare('DELETE FROM usuarios WHERE id = ? LIMIT 1')->execute([$usuarioId]);
                admin_usuarios_redirect(['msg' => 'Usuário excluído com sucesso.']);
            } catch (Throwable $e) {
                if (admin_usuarios_tem_coluna($colunas, 'status')) {
                    $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ? LIMIT 1")->execute([$usuarioId]);
                    admin_usuarios_redirect(['msg' => 'Usuário possui histórico vinculado e foi inativado.']);
                }
                throw $e;
            }
        }

        if ($acao === 'alterar_senha') {
            $nova = (string)($_POST['nova_senha'] ?? '');
            $confirma = (string)($_POST['confirma_senha'] ?? '');
            if (strlen($nova) < 6) {
                throw new RuntimeException('A nova senha precisa ter pelo menos 6 caracteres.');
            }
            if ($nova !== $confirma) {
                throw new RuntimeException('A confirmação de senha não confere.');
            }

            $colunaSenha = admin_usuarios_senha_coluna($colunas);
            if ($colunaSenha === '') {
                throw new RuntimeException('Não encontrei coluna de senha na tabela usuarios.');
            }

            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET `{$colunaSenha}` = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$hash, $usuarioId]);
            admin_usuarios_redirect(['senha' => null, 'msg' => 'Senha alterada com sucesso.']);
        }

        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $e) {
        admin_usuarios_redirect(['erro' => $e->getMessage()]);
    }
}

$busca = trim((string)($_GET['q'] ?? ''));
$statusFiltro = trim((string)($_GET['status'] ?? ''));
$tipoFiltro = trim((string)($_GET['tipo'] ?? ''));
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 12;

$allowedStatus = ['ativo', 'inativo', 'bloqueado'];
$allowedTipos = ['cliente', 'admin'];
if (!in_array($statusFiltro, $allowedStatus, true)) {
    $statusFiltro = '';
}
if (!in_array($tipoFiltro, $allowedTipos, true)) {
    $tipoFiltro = '';
}

[$whereSql, $params] = admin_usuarios_montar_where($colunas, $busca, $statusFiltro, $tipoFiltro);
$totalFiltrado = admin_usuarios_count($pdo, trim(str_replace(' WHERE ', '', $whereSql)), $params);
$totalPaginas = max(1, (int)ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}
$offset = ($pagina - 1) * $porPagina;

$camposBase = ['id'];
foreach (['nome', 'email', 'telefone', 'tipo', 'status', 'ultimo_login', 'created_at', 'updated_at'] as $campo) {
    if (admin_usuarios_tem_coluna($colunas, $campo)) {
        $camposBase[] = $campo;
    }
}
$camposSelect = implode(', ', array_map(static fn(string $campo): string => '`' . str_replace('`', '``', $campo) . '`', $camposBase));

$sqlUsuarios = "SELECT {$camposSelect} FROM usuarios{$whereSql} ORDER BY " . (admin_usuarios_tem_coluna($colunas, 'created_at') ? '`created_at` DESC, ' : '') . '`id` DESC LIMIT :limit OFFSET :offset';
$stmtUsuarios = $pdo->prepare($sqlUsuarios);
foreach ($params as $key => $value) {
    $stmtUsuarios->bindValue($key, $value);
}
$stmtUsuarios->bindValue(':limit', $porPagina, PDO::PARAM_INT);
$stmtUsuarios->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtUsuarios->execute();
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

$totalUsuarios = admin_usuarios_count($pdo);
$totalAtivos = admin_usuarios_tem_coluna($colunas, 'status') ? admin_usuarios_count($pdo, "`status` = 'ativo'") : 0;
$totalBloqueados = admin_usuarios_tem_coluna($colunas, 'status') ? admin_usuarios_count($pdo, "`status` = 'bloqueado'") : 0;
$totalAdmins = admin_usuarios_tem_coluna($colunas, 'tipo') ? admin_usuarios_count($pdo, "`tipo` = 'admin'") : 0;

admin_usuarios_acesso_bootstrap($pdo);
$cursosDisponiveis = admin_usuarios_listar_cursos($pdo);

$editarId = (int)($_GET['editar'] ?? 0);
$usuarioEditar = null;
$cursosSelecionadosEditar = [];
if ($editarId > 0) {
    $stmtEditar = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmtEditar->execute([$editarId]);
    $usuarioEditar = $stmtEditar->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($usuarioEditar) {
        $cursosSelecionadosEditar = admin_usuarios_cursos_selecionados($pdo, $editarId);
        $totalConfiguracoesEditar = admin_usuarios_table_count($pdo, 'usuario_cursos', 'usuario_id = ?', [$editarId]);
        $tipoEditarAtual = mb_strtolower(trim((string)($usuarioEditar['tipo'] ?? 'cliente')), 'UTF-8');
        if ($totalConfiguracoesEditar === 0 || in_array($tipoEditarAtual, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true)) {
            $cursosSelecionadosEditar = array_values(array_filter(array_map(static fn(array $curso): int => (int)($curso['id'] ?? 0), $cursosDisponiveis)));
        }
    }
}

$verId = (int)($_GET['ver'] ?? 0);
$usuarioDetalhe = null;
$usuarioMetricas = [];
$usuarioCursosDetalhe = [];
if ($verId > 0) {
    $stmtDetalhe = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmtDetalhe->execute([$verId]);
    $usuarioDetalhe = $stmtDetalhe->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($usuarioDetalhe) {
        $usuarioMetricas = [
            'Cursos liberados' => admin_usuarios_table_count($pdo, 'usuario_cursos', "usuario_id = ? AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')", [$verId]),
            'Metas concluídas' => admin_usuarios_table_count($pdo, 'usuario_cronograma_conclusoes', 'usuario_id = ? AND concluido = 1', [$verId]),
            'Tópicos no fórum' => admin_usuarios_table_count($pdo, 'forum_duvidas_topicos', 'usuario_id = ?', [$verId]),
            'XP acumulado' => admin_usuarios_table_count($pdo, 'usuario_xp_eventos', 'usuario_id = ?', [$verId]),
        ];
        try {
            $stmtCursosDetalhe = $pdo->prepare("SELECT c.nome
                FROM usuario_cursos uc
                INNER JOIN admin_cursos c ON c.id = uc.curso_id
                WHERE uc.usuario_id = ?
                  AND uc.status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')
                ORDER BY c.ordem ASC, c.nome ASC");
            $stmtCursosDetalhe->execute([$verId]);
            $usuarioCursosDetalhe = array_map('strval', $stmtCursosDetalhe->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            $usuarioCursosDetalhe = [];
        }

        $tipoDetalhe = mb_strtolower(trim((string)($usuarioDetalhe['tipo'] ?? 'cliente')), 'UTF-8');
        $totalConfiguracoesDetalhe = admin_usuarios_table_count($pdo, 'usuario_cursos', 'usuario_id = ?', [$verId]);
        if ($totalConfiguracoesDetalhe === 0 || in_array($tipoDetalhe, ['admin', 'administrador', 'adm', 'master', 'superadmin'], true)) {
            $usuarioCursosDetalhe = array_values(array_filter(array_map(static fn(array $curso): string => trim((string)($curso['nome'] ?? '')), $cursosDisponiveis)));
            $usuarioMetricas['Cursos liberados'] = count($usuarioCursosDetalhe);
        }
    }
}

$senhaId = (int)($_GET['senha'] ?? 0);
$usuarioSenha = null;
if ($senhaId > 0) {
    $stmtSenha = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE id = ? LIMIT 1');
    $stmtSenha->execute([$senhaId]);
    $usuarioSenha = $stmtSenha->fetch(PDO::FETCH_ASSOC) ?: null;
}

function admin_usuarios_url(array $extra = []): string
{
    $params = $_GET;
    unset($params['msg'], $params['erro']);
    foreach ($extra as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $query = http_build_query($params);
    return 'admin-usuarios' . ($query !== '' ? '?' . $query : '');
}

$topbarInclude = __DIR__ . '/includes/admin-topbar.php';
$menuBarInclude = __DIR__ . '/includes/menu-bar.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários | Admin Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--preto:#111111;--amarelo:#fdbd08;--amarelo-escuro:#d89d00;--fundo:#f3f4f6;--card:#fff;--texto:#1f2937;--muted:#667085;--borda:#e4e7ec;--sombra:0 10px 28px rgba(15,23,42,.08);--verde:#027a48;--vermelho:#b42318;}
        *{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;min-height:100%}body{font-family:'Ubuntu',Arial,sans-serif;background:var(--fundo);color:var(--texto)}a{text-decoration:none;color:inherit}button,input,select{font-family:inherit}.admin-dashboard-shell{width:100%;min-height:calc(100vh - 78px);display:flex;background:var(--fundo)}.admin-main{width:100%;min-width:0;padding:32px 36px 46px}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.page-head h1{font-size:1.75rem;line-height:1.1;color:#111827;margin-bottom:7px}.page-head p{color:var(--muted);font-weight:500}.btn{min-height:40px;border:0;border-radius:10px;background:var(--amarelo);color:#111827;font-weight:700;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;white-space:nowrap}.btn i,.btn svg{width:18px;height:18px;stroke-width:2.5}.btn:hover{filter:brightness(.97)}.btn-light{background:#fff;border:1px solid #d0d5dd;color:#344054}.btn-muted{background:#f8fafc;border:1px solid #d0d5dd;color:#344054}.btn-danger{background:#fff1f0;border:1px solid #fecdca;color:#b42318}.btn-small{min-height:34px;padding:0 10px;font-size:.82rem;border-radius:8px}.btn[disabled]{opacity:.45;cursor:not-allowed}.alert{border-radius:10px;padding:12px 14px;font-weight:700;margin-bottom:16px}.alert-ok{background:#ecfdf3;border:1px solid #abefc6;color:#027a48}.alert-erro{background:#fff1f0;border:1px solid #fecdca;color:#b42318}.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat-card{background:#fff;border:1px solid var(--borda);border-radius:14px;padding:18px;box-shadow:0 4px 16px rgba(15,23,42,.05)}.stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#667085;font-weight:700;font-size:.9rem}.stat-top i,.stat-top svg{color:var(--amarelo-escuro);width:21px;height:21px}.stat-number{font-size:1.8rem;font-weight:700;color:#111827;margin-top:12px}.panel{background:#fff;border:1px solid var(--borda);border-radius:14px;box-shadow:var(--sombra);padding:20px;margin-bottom:18px}.filters{display:grid;grid-template-columns:minmax(240px,1fr) 180px 160px auto;gap:12px;align-items:end}.field label{display:block;font-size:.87rem;font-weight:700;color:#344054;margin-bottom:7px}.input,.select{width:100%;height:42px;border:1px solid #d0d5dd;border-radius:9px;background:#fff;color:#111827;padding:0 13px;outline:none}.input:focus,.select:focus{border-color:var(--amarelo);box-shadow:0 0 0 3px rgba(253,189,8,.18)}.table-card{background:#fff;border:1px solid var(--borda);border-radius:14px;box-shadow:var(--sombra);overflow:hidden}.table-top{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #eef0f3}.table-top h2{font-size:1.05rem;color:#111827}.table-top span{color:#667085;font-weight:700;font-size:.88rem}.table-scroll{width:100%;overflow-x:auto}.users-table{width:100%;border-collapse:collapse;min-width:1040px}.users-table th,.users-table td{padding:14px 16px;border-bottom:1px solid #eef0f3;text-align:left;vertical-align:middle}.users-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;color:#667085;background:#f8fafc}.users-table td{font-size:.92rem;color:#344054}.users-table tr:last-child td{border-bottom:0}.user-main{display:flex;align-items:center;gap:12px;min-width:250px}.avatar{width:40px;height:40px;border-radius:999px;background:#fff7db;color:#b77900;display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 40px}.user-name{font-weight:700;color:#111827}.user-email{font-size:.84rem;color:#667085;margin-top:3px}.badge{display:inline-flex;align-items:center;gap:6px;min-height:26px;border-radius:999px;padding:0 9px;font-size:.78rem;font-weight:700}.badge-ativo{background:#ecfdf3;color:#027a48}.badge-bloqueado{background:#fff1f0;color:#b42318}.badge-inativo{background:#f2f4f7;color:#475467}.badge-admin{background:#fff7db;color:#b77900}.badge-cliente{background:#eff8ff;color:#175cd3}.actions{display:flex;align-items:center;gap:7px;flex-wrap:nowrap}.actions form{display:inline-flex}.empty{padding:38px;text-align:center;color:#667085;font-weight:700}.pagination{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:18px}.pagination a,.pagination span{min-width:38px;height:38px;border-radius:8px;border:1px solid #d0d5dd;display:flex;align-items:center;justify-content:center;background:#fff;font-weight:700;color:#344054}.pagination .active{background:var(--amarelo);border-color:var(--amarelo);color:#111827}.modal-back{position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}.modal{width:min(720px,100%);max-height:calc(100vh - 40px);overflow:auto;background:#fff;border-radius:16px;box-shadow:0 22px 70px rgba(0,0,0,.28);padding:24px}.modal h2{font-size:1.22rem;margin-bottom:8px;color:#111827}.modal p{color:#667085;font-size:.92rem;line-height:1.45;margin-bottom:18px}.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.info-line{border:1px solid #eef0f3;background:#f8fafc;border-radius:10px;padding:12px}.info-line span{display:block;font-size:.78rem;font-weight:700;color:#667085;margin-bottom:5px}.info-line strong{display:block;color:#111827;word-break:break-word}.modal-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:18px}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:14px}.metric{background:#fff7db;border:1px solid #f8d66d;border-radius:10px;padding:12px}.metric span{display:block;color:#8a6100;font-size:.76rem;font-weight:700}.metric strong{display:block;color:#111827;font-size:1.2rem;margin-top:4px}@media(max-width:1024px){.admin-dashboard-shell{flex-direction:column}.admin-main{padding:26px 18px 40px}.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.filters{grid-template-columns:1fr 1fr}.metrics{grid-template-columns:1fr 1fr}}@media(max-width:640px){.admin-main{padding:20px 12px 32px}.page-head{display:grid}.stats-grid,.filters,.modal-grid,.metrics{grid-template-columns:1fr}.panel{padding:16px}.table-top{display:grid}.modal-actions{display:grid}.modal-actions .btn{width:100%}}
    
        .modal-wide{width:min(920px,100%)}
        .access-section{margin-top:18px;border:1px solid #e4e7ec;border-radius:14px;overflow:hidden;background:#fff}
        .access-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e4e7ec}
        .access-head h3{font-size:1rem;color:#111827;margin:0}
        .access-head p{margin:4px 0 0;font-size:.82rem;color:#667085}
        .access-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .access-tools button{min-height:32px;border-radius:8px;border:1px solid #d0d5dd;background:#fff;color:#344054;font-weight:700;padding:0 10px;cursor:pointer}
        .access-tools button:hover{border-color:var(--amarelo);background:#fff9e8}
        .course-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:14px;max-height:310px;overflow:auto}
        .course-access-item{position:relative;display:flex;align-items:flex-start;gap:11px;border:1px solid #e4e7ec;border-radius:11px;padding:12px;background:#fff;cursor:pointer;transition:.18s ease}
        .course-access-item:hover{border-color:#f2c94c;background:#fffdf5}
        .course-access-item:has(input:checked){border-color:var(--amarelo);background:#fff8dc;box-shadow:0 0 0 2px rgba(253,189,8,.12)}
        .course-access-item input{width:18px;height:18px;margin-top:1px;accent-color:var(--amarelo);flex:0 0 auto}
        .course-access-copy{min-width:0}
        .course-access-name{display:block;font-size:.9rem;font-weight:700;color:#111827;line-height:1.25}
        .course-access-status{display:block;font-size:.75rem;color:#667085;margin-top:4px}
        .access-empty{grid-column:1/-1;padding:18px;text-align:center;color:#667085;font-weight:700}
        .detail-courses{margin-top:14px;border:1px solid #e4e7ec;border-radius:12px;padding:14px}
        .detail-courses h3{font-size:.95rem;margin-bottom:10px;color:#111827}
        .detail-course-list{display:flex;gap:7px;flex-wrap:wrap}
        .detail-course-pill{display:inline-flex;align-items:center;gap:6px;background:#fff7db;color:#8a6100;border:1px solid #f8d66d;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700}
        @media(max-width:640px){.course-access-grid{grid-template-columns:1fr;max-height:280px}.access-head{align-items:flex-start;flex-direction:column}.access-tools{width:100%}.access-tools button{flex:1}.modal-wide{padding:18px}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
        <main class="admin-main">
            <header class="page-head">
                <div>
                    <h1>Usuários</h1>
                    <p>Visualize, bloqueie, desbloqueie, exclua e altere a senha dos usuários cadastrados.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                    <a class="btn" href="<?php echo admin_usuarios_e(admin_usuarios_url(['modal' => 'criar', 'ver' => null, 'senha' => null, 'editar' => null])); ?>"><i data-lucide="user-plus"></i>Adicionar usuário</a>
                    <a class="btn btn-light" href="admin-dashboard"><i data-lucide="arrow-left"></i>Voltar ao dashboard</a>
                </div>
            </header>

            <?php if ($mensagem !== ''): ?><div class="alert alert-ok"><?php echo admin_usuarios_e($mensagem); ?></div><?php endif; ?>
            <?php if ($erro !== ''): ?><div class="alert alert-erro"><?php echo admin_usuarios_e($erro); ?></div><?php endif; ?>

            <section class="stats-grid" aria-label="Resumo dos usuários">
                <article class="stat-card"><div class="stat-top"><span>Total</span><i data-lucide="users"></i></div><div class="stat-number"><?php echo (int)$totalUsuarios; ?></div></article>
                <article class="stat-card"><div class="stat-top"><span>Ativos</span><i data-lucide="user-check"></i></div><div class="stat-number"><?php echo (int)$totalAtivos; ?></div></article>
                <article class="stat-card"><div class="stat-top"><span>Bloqueados</span><i data-lucide="user-x"></i></div><div class="stat-number"><?php echo (int)$totalBloqueados; ?></div></article>
                <article class="stat-card"><div class="stat-top"><span>Admins</span><i data-lucide="shield-check"></i></div><div class="stat-number"><?php echo (int)$totalAdmins; ?></div></article>
            </section>

            <section class="panel" aria-label="Filtros dos usuários">
                <form class="filters" method="get" action="admin-usuarios">
                    <div class="field"><label for="q">Buscar</label><input class="input" id="q" name="q" type="search" value="<?php echo admin_usuarios_e($busca); ?>" placeholder="Nome, e-mail ou telefone"></div>
                    <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Todos</option><option value="ativo" <?php echo $statusFiltro === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="bloqueado" <?php echo $statusFiltro === 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option><option value="inativo" <?php echo $statusFiltro === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                    <div class="field"><label for="tipo">Tipo</label><select class="select" id="tipo" name="tipo"><option value="">Todos</option><option value="cliente" <?php echo $tipoFiltro === 'cliente' ? 'selected' : ''; ?>>Cliente</option><option value="admin" <?php echo $tipoFiltro === 'admin' ? 'selected' : ''; ?>>Admin</option></select></div>
                    <button class="btn" type="submit"><i data-lucide="search"></i>Filtrar</button>
                </form>
            </section>

            <section class="table-card" aria-label="Lista de usuários cadastrados">
                <div class="table-top"><h2>Lista de usuários</h2><span><?php echo (int)$totalFiltrado; ?> encontrado(s)</span></div>
                <div class="table-scroll">
                    <table class="users-table">
                        <thead><tr><th>Usuário</th><th>Telefone</th><th>Tipo</th><th>Status</th><th>Último login</th><th>Criado em</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php if (!$usuarios): ?>
                            <tr><td class="empty" colspan="7">Nenhum usuário encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <?php
                                    $uid = (int)($usuario['id'] ?? 0);
                                    $nome = (string)($usuario['nome'] ?? 'Usuário');
                                    $email = (string)($usuario['email'] ?? '-');
                                    $telefone = (string)($usuario['telefone'] ?? '-');
                                    $tipo = (string)($usuario['tipo'] ?? 'cliente');
                                    $status = (string)($usuario['status'] ?? 'ativo');
                                    $inicial = mb_strtoupper(mb_substr($nome !== '' ? $nome : 'U', 0, 1, 'UTF-8'), 'UTF-8');
                                ?>
                                <tr>
                                    <td><div class="user-main"><div class="avatar"><?php echo admin_usuarios_e($inicial); ?></div><div><div class="user-name"><?php echo admin_usuarios_e($nome); ?></div><div class="user-email"><?php echo admin_usuarios_e($email); ?></div></div></div></td>
                                    <td><?php echo admin_usuarios_e($telefone !== '' ? $telefone : '-'); ?></td>
                                    <td><span class="badge <?php echo $tipo === 'admin' ? 'badge-admin' : 'badge-cliente'; ?>"><?php echo admin_usuarios_e(ucfirst($tipo)); ?></span></td>
                                    <td><span class="badge badge-<?php echo admin_usuarios_e($status); ?>"><?php echo admin_usuarios_e(ucfirst($status)); ?></span></td>
                                    <td><?php echo admin_usuarios_e(admin_usuarios_data($usuario['ultimo_login'] ?? '')); ?></td>
                                    <td><?php echo admin_usuarios_e(admin_usuarios_data($usuario['created_at'] ?? '')); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a class="btn btn-small btn-muted" href="<?php echo admin_usuarios_e(admin_usuarios_url(['editar' => $uid, 'ver' => null, 'senha' => null, 'modal' => null])); ?>"><i data-lucide="pencil"></i>Editar</a>
                                            <a class="btn btn-small btn-muted" href="<?php echo admin_usuarios_e(admin_usuarios_url(['ver' => $uid, 'senha' => null, 'editar' => null, 'modal' => null])); ?>"><i data-lucide="eye"></i>Ver</a>
                                            <a class="btn btn-small btn-muted" href="<?php echo admin_usuarios_e(admin_usuarios_url(['senha' => $uid, 'ver' => null, 'editar' => null, 'modal' => null])); ?>"><i data-lucide="key-round"></i>Senha</a>
                                            <?php if ($status === 'bloqueado'): ?>
                                                <form method="post" action="admin-usuarios"><input type="hidden" name="acao" value="desbloquear"><input type="hidden" name="usuario_id" value="<?php echo $uid; ?>"><button class="btn btn-small" type="submit"><i data-lucide="unlock"></i>Desbloquear</button></form>
                                            <?php else: ?>
                                                <form method="post" action="admin-usuarios" onsubmit="return confirm('Bloquear este usuário?');"><input type="hidden" name="acao" value="bloquear"><input type="hidden" name="usuario_id" value="<?php echo $uid; ?>"><button class="btn btn-small btn-muted" type="submit" <?php echo $uid === $adminId ? 'disabled' : ''; ?>><i data-lucide="ban"></i>Bloquear</button></form>
                                            <?php endif; ?>
                                            <form method="post" action="admin-usuarios" onsubmit="return confirm('Excluir este usuário? Se houver histórico, ele será inativado.');"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="usuario_id" value="<?php echo $uid; ?>"><button class="btn btn-small btn-danger" type="submit" <?php echo $uid === $adminId ? 'disabled' : ''; ?>><i data-lucide="trash-2"></i>Excluir</button></form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($totalPaginas > 1): ?>
                <nav class="pagination" aria-label="Paginação">
                    <?php if ($pagina > 1): ?><a href="<?php echo admin_usuarios_e(admin_usuarios_url(['pagina' => $pagina - 1])); ?>">‹</a><?php endif; ?>
                    <span class="active"><?php echo (int)$pagina; ?></span>
                    <span><?php echo (int)$totalPaginas; ?></span>
                    <?php if ($pagina < $totalPaginas): ?><a href="<?php echo admin_usuarios_e(admin_usuarios_url(['pagina' => $pagina + 1])); ?>">›</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </main>
    </div>

    <?php if ((string)($_GET['modal'] ?? '') === 'criar'): ?>
        <div class="modal-back" role="dialog" aria-modal="true" aria-labelledby="criarUsuarioTitulo">
            <form class="modal modal-wide" method="post" action="admin-usuarios">
                <h2 id="criarUsuarioTitulo">Adicionar usuário manualmente</h2>
                <p>Cadastre o aluno ou administrador diretamente pelo painel.</p>
                <input type="hidden" name="acao" value="criar_usuario">
                <div class="modal-grid">
                    <div class="field"><label for="novo_nome">Nome completo</label><input class="input" id="novo_nome" name="nome" type="text" required autocomplete="name" placeholder="Nome do usuário"></div>
                    <div class="field"><label for="novo_email">E-mail</label><input class="input" id="novo_email" name="email" type="email" required autocomplete="email" placeholder="email@exemplo.com"></div>
                    <div class="field"><label for="novo_telefone">Telefone</label><input class="input" id="novo_telefone" name="telefone" type="tel" autocomplete="tel" placeholder="(00) 00000-0000"></div>
                    <div class="field"><label for="nova_senha_criar">Senha inicial</label><input class="input" id="nova_senha_criar" name="senha" type="password" required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres"></div>
                    <div class="field"><label for="novo_tipo">Tipo</label><select class="select" id="novo_tipo" name="tipo"><option value="cliente">Cliente</option><option value="admin">Admin</option></select></div>
                    <div class="field"><label for="novo_status">Status</label><select class="select" id="novo_status" name="status"><option value="ativo">Ativo</option><option value="bloqueado">Bloqueado</option><option value="inativo">Inativo</option></select></div>
                </div>

                <section class="access-section" data-course-group>
                    <div class="access-head">
                        <div>
                            <h3>Acesso aos cursos</h3>
                            <p>Marque somente os cursos que este usuário poderá acessar.</p>
                        </div>
                        <div class="access-tools">
                            <button type="button" data-course-action="all">Adicionar todos</button>
                            <button type="button" data-course-action="none">Remover todos</button>
                        </div>
                    </div>
                    <div class="course-access-grid">
                        <?php if (!$cursosDisponiveis): ?>
                            <div class="access-empty">Nenhum curso cadastrado.</div>
                        <?php else: ?>
                            <?php foreach ($cursosDisponiveis as $curso): ?>
                                <label class="course-access-item">
                                    <input type="checkbox" name="cursos[]" value="<?php echo (int)$curso['id']; ?>">
                                    <span class="course-access-copy">
                                        <span class="course-access-name"><?php echo admin_usuarios_e($curso['nome'] ?? 'Curso'); ?></span>
                                        <span class="course-access-status"><?php echo ($curso['status'] ?? 'ativo') === 'ativo' ? 'Curso ativo' : 'Curso inativo'; ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="modal-actions"><a class="btn btn-light" href="<?php echo admin_usuarios_e(admin_usuarios_url(['modal' => null])); ?>">Cancelar</a><button class="btn" type="submit"><i data-lucide="save"></i>Criar usuário</button></div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($usuarioEditar): ?>
        <div class="modal-back" role="dialog" aria-modal="true" aria-labelledby="editarUsuarioTitulo">
            <form class="modal modal-wide" method="post" action="admin-usuarios">
                <h2 id="editarUsuarioTitulo">Editar usuário e acessos</h2>
                <p>Altere os dados do usuário e defina exatamente quais cursos ficarão liberados.</p>
                <input type="hidden" name="acao" value="editar_usuario">
                <input type="hidden" name="usuario_id" value="<?php echo (int)$usuarioEditar['id']; ?>">

                <div class="modal-grid">
                    <div class="field"><label for="editar_nome">Nome completo</label><input class="input" id="editar_nome" name="nome" type="text" required value="<?php echo admin_usuarios_e($usuarioEditar['nome'] ?? ''); ?>"></div>
                    <div class="field"><label for="editar_email">E-mail</label><input class="input" id="editar_email" name="email" type="email" required value="<?php echo admin_usuarios_e($usuarioEditar['email'] ?? ''); ?>"></div>
                    <div class="field"><label for="editar_telefone">Telefone</label><input class="input" id="editar_telefone" name="telefone" type="tel" value="<?php echo admin_usuarios_e($usuarioEditar['telefone'] ?? ''); ?>"></div>
                    <div class="field"><label for="editar_tipo">Tipo</label><select class="select" id="editar_tipo" name="tipo"><option value="cliente" <?php echo ($usuarioEditar['tipo'] ?? 'cliente') === 'cliente' ? 'selected' : ''; ?>>Cliente</option><option value="admin" <?php echo ($usuarioEditar['tipo'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option></select></div>
                    <div class="field"><label for="editar_status">Status</label><select class="select" id="editar_status" name="status"><option value="ativo" <?php echo ($usuarioEditar['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option><option value="bloqueado" <?php echo ($usuarioEditar['status'] ?? '') === 'bloqueado' ? 'selected' : ''; ?>>Bloqueado</option><option value="inativo" <?php echo ($usuarioEditar['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option></select></div>
                </div>

                <section class="access-section" data-course-group>
                    <div class="access-head">
                        <div>
                            <h3>Acesso aos cursos</h3>
                            <p>Você pode adicionar todos, remover todos ou escolher curso por curso.</p>
                        </div>
                        <div class="access-tools">
                            <button type="button" data-course-action="all">Adicionar todos</button>
                            <button type="button" data-course-action="none">Remover todos</button>
                        </div>
                    </div>
                    <div class="course-access-grid">
                        <?php if (!$cursosDisponiveis): ?>
                            <div class="access-empty">Nenhum curso cadastrado.</div>
                        <?php else: ?>
                            <?php foreach ($cursosDisponiveis as $curso): ?>
                                <?php $cursoMarcado = in_array((int)$curso['id'], $cursosSelecionadosEditar, true); ?>
                                <label class="course-access-item">
                                    <input type="checkbox" name="cursos[]" value="<?php echo (int)$curso['id']; ?>" <?php echo $cursoMarcado ? 'checked' : ''; ?>>
                                    <span class="course-access-copy">
                                        <span class="course-access-name"><?php echo admin_usuarios_e($curso['nome'] ?? 'Curso'); ?></span>
                                        <span class="course-access-status"><?php echo ($curso['status'] ?? 'ativo') === 'ativo' ? 'Curso ativo' : 'Curso inativo'; ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="modal-actions">
                    <a class="btn btn-light" href="<?php echo admin_usuarios_e(admin_usuarios_url(['editar' => null])); ?>">Cancelar</a>
                    <button class="btn" type="submit"><i data-lucide="save"></i>Salvar alterações</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($usuarioDetalhe): ?>
        <div class="modal-back" role="dialog" aria-modal="true" aria-labelledby="detalheTitulo">
            <div class="modal">
                <h2 id="detalheTitulo">Informações do usuário</h2>
                <p>Dados cadastrados e principais números do aluno.</p>
                <div class="modal-grid">
                    <div class="info-line"><span>ID</span><strong><?php echo (int)$usuarioDetalhe['id']; ?></strong></div>
                    <div class="info-line"><span>Nome</span><strong><?php echo admin_usuarios_e($usuarioDetalhe['nome'] ?? '-'); ?></strong></div>
                    <div class="info-line"><span>E-mail</span><strong><?php echo admin_usuarios_e($usuarioDetalhe['email'] ?? '-'); ?></strong></div>
                    <div class="info-line"><span>Telefone</span><strong><?php echo admin_usuarios_e($usuarioDetalhe['telefone'] ?? '-'); ?></strong></div>
                    <div class="info-line"><span>Tipo</span><strong><?php echo admin_usuarios_e($usuarioDetalhe['tipo'] ?? '-'); ?></strong></div>
                    <div class="info-line"><span>Status</span><strong><?php echo admin_usuarios_e($usuarioDetalhe['status'] ?? '-'); ?></strong></div>
                    <div class="info-line"><span>Último login</span><strong><?php echo admin_usuarios_e(admin_usuarios_data($usuarioDetalhe['ultimo_login'] ?? '')); ?></strong></div>
                    <div class="info-line"><span>Cadastrado em</span><strong><?php echo admin_usuarios_e(admin_usuarios_data($usuarioDetalhe['created_at'] ?? '')); ?></strong></div>
                </div>
                <div class="metrics">
                    <?php foreach ($usuarioMetricas as $label => $valor): ?>
                        <div class="metric"><span><?php echo admin_usuarios_e($label); ?></span><strong><?php echo (int)$valor; ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <section class="detail-courses">
                    <h3>Cursos liberados</h3>
                    <div class="detail-course-list">
                        <?php if (!$usuarioCursosDetalhe): ?>
                            <span class="detail-course-pill"><i data-lucide="circle-off"></i>Nenhum curso liberado</span>
                        <?php else: ?>
                            <?php foreach ($usuarioCursosDetalhe as $nomeCurso): ?>
                                <span class="detail-course-pill"><i data-lucide="book-open-check"></i><?php echo admin_usuarios_e($nomeCurso); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
                <div class="modal-actions"><a class="btn btn-light" href="<?php echo admin_usuarios_e(admin_usuarios_url(['ver' => null])); ?>">Fechar</a></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($usuarioSenha): ?>
        <div class="modal-back" role="dialog" aria-modal="true" aria-labelledby="senhaTitulo">
            <form class="modal" method="post" action="admin-usuarios">
                <h2 id="senhaTitulo">Alterar senha</h2>
                <p>Defina uma nova senha para <strong><?php echo admin_usuarios_e($usuarioSenha['nome'] ?? 'usuário'); ?></strong>.</p>
                <input type="hidden" name="acao" value="alterar_senha">
                <input type="hidden" name="usuario_id" value="<?php echo (int)$usuarioSenha['id']; ?>">
                <div class="field"><label for="nova_senha">Nova senha</label><input class="input" id="nova_senha" name="nova_senha" type="password" required minlength="6" autocomplete="new-password"></div>
                <div class="field" style="margin-top:12px;"><label for="confirma_senha">Confirmar senha</label><input class="input" id="confirma_senha" name="confirma_senha" type="password" required minlength="6" autocomplete="new-password"></div>
                <div class="modal-actions"><a class="btn btn-light" href="<?php echo admin_usuarios_e(admin_usuarios_url(['senha' => null])); ?>">Cancelar</a><button class="btn" type="submit"><i data-lucide="save"></i>Salvar senha</button></div>
            </form>
        </div>
    <?php endif; ?>

    <script>
        document.querySelectorAll('[data-course-group]').forEach(function (grupo) {
            grupo.querySelectorAll('[data-course-action]').forEach(function (botao) {
                botao.addEventListener('click', function () {
                    const marcar = botao.dataset.courseAction === 'all';
                    grupo.querySelectorAll('input[type="checkbox"][name="cursos[]"]').forEach(function (checkbox) {
                        checkbox.checked = marcar;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            });
        });

        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
