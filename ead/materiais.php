<?php
declare(strict_types=1);
// SIMULADOS_NA_PASTA_MATERIAIS_CORRIGIDO_FINAL_2026_05_22

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}
$logo = 'img/logo.png';

$usuarioLogado = isset($_SESSION['usuario']) && is_array($_SESSION['usuario']);
$nomeUsuario = $usuarioLogado && !empty($_SESSION['usuario']['nome'])
    ? (string) $_SESSION['usuario']['nome']
    : 'Visitante';

$menuItens = [
    ['titulo' => 'Materiais', 'link' => 'materiais.php', 'externo' => false],
    ['titulo' => 'Agenda', 'link' => 'agenda.php', 'externo' => false],
    ['titulo' => 'Simulados', 'link' => 'simulados.php', 'externo' => false],
    ['titulo' => 'Banco de questões', 'link' => 'banco-questoes.php', 'externo' => false],
    ['titulo' => 'Dashboard de questões', 'link' => 'dashboard-questoes.php', 'externo' => false],
    ['titulo' => 'Cronograma', 'link' => 'cronograma.php', 'externo' => false],
    ['titulo' => 'Fórum de dúvidas', 'link' => 'forum-duvidas.php', 'externo' => false],
    ['titulo' => 'Propostas de redação', 'link' => 'propostas-redacao.php', 'externo' => false],
    ['titulo' => 'Redações', 'link' => 'redacoes.php', 'externo' => false],
    ['titulo' => 'Cadernos', 'link' => 'cadernos.php', 'externo' => false],
    ['titulo' => 'Flashcards', 'link' => 'flashcards.php', 'externo' => false],
    ['titulo' => 'Recompensas', 'link' => 'recompensas.php', 'externo' => false],
    ['titulo' => 'QUEM SOMOS', 'link' => 'https://www.instagram.com/projeto.gabaritando/', 'externo' => true],
    ['titulo' => 'ANTIGÃO IA', 'link' => 'https://typebotapi.adscompany.app.br/antigao-38a8ulb', 'externo' => true],
];

if (!function_exists('materiais_esc')) {
    function materiais_esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('materiais_lower')) {
    function materiais_lower(string $texto): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
    }
}

if (!function_exists('materiais_env')) {
    function materiais_env(string $path): array
    {
        $env = [];
        if (!is_file($path)) {
            return $env;
        }

        $linhas = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$linhas) {
            return $env;
        }

        foreach ($linhas as $linha) {
            $linha = trim((string)$linha);
            if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);
            $env[trim($chave)] = trim($valor, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }
}

if (!function_exists('materiais_db')) {
    function materiais_db(): PDO
    {
        $conexaoInclude = __DIR__ . '/api/conexao.php';
        if (is_file($conexaoInclude)) {
            require_once $conexaoInclude;
        }

        if (function_exists('db')) {
            $db = db();
            if ($db instanceof PDO) {
                return $db;
            }
        }

        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            return $pdo;
        }

        $env = materiais_env(__DIR__ . '/.env');
        $host = $env['DB_HOST'] ?? $env['MYSQL_HOST'] ?? 'localhost';
        $porta = $env['DB_PORT'] ?? $env['MYSQL_PORT'] ?? '3306';
        $banco = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? $env['MYSQL_DATABASE'] ?? '';
        $usuario = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? $env['MYSQL_USER'] ?? '';
        $senha = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? $env['MYSQL_PASSWORD'] ?? '';

        if ($banco === '' || $usuario === '') {
            throw new RuntimeException('Conexão com o banco não encontrada. Confira api/conexao.php ou .env.');
        }

        $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";
        return new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

if (!function_exists('materiais_nome_arquivo')) {
    function materiais_nome_arquivo(string $arquivo, string $nome = ''): string
    {
        $nome = trim($nome);
        if ($nome !== '') {
            return $nome;
        }

        $basename = basename(parse_url($arquivo, PHP_URL_PATH) ?: $arquivo);
        return $basename !== '' ? $basename : 'Arquivo do conteúdo';
    }
}

if (!function_exists('materiais_arquivos_da_aula')) {
    function materiais_arquivos_da_aula(array $aula): array
    {
        $arquivos = [];
        $vistos = [];

        $campos = [
            ['arquivo' => 'pdf_arquivo', 'nome' => 'pdf_nome', 'tipo' => 'PDF'],
            ['arquivo' => 'material_arquivo', 'nome' => 'material_nome', 'tipo' => 'Material'],
        ];

        foreach ($campos as $campo) {
            $arquivo = trim((string)($aula[$campo['arquivo']] ?? ''));
            if ($arquivo === '' || isset($vistos[$arquivo])) {
                continue;
            }

            $vistos[$arquivo] = true;
            $arquivos[] = [
                'titulo' => materiais_nome_arquivo($arquivo, (string)($aula[$campo['nome']] ?? '')),
                'arquivo' => $arquivo,
                'tipo' => $campo['tipo'],
            ];
        }

        return $arquivos;
    }
}

if (!function_exists('materiais_bootstrap_admin_materiais')) {
    function materiais_bootstrap_admin_materiais(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_material_pastas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED NULL,
            titulo VARCHAR(180) NOT NULL,
            descricao TEXT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_pastas_curso (curso_id),
            INDEX idx_admin_material_pastas_status (status),
            INDEX idx_admin_material_pastas_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_material_arquivos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pasta_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(190) NOT NULL,
            arquivo VARCHAR(255) NOT NULL,
            arquivo_nome VARCHAR(255) NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_admin_material_arquivos_pasta (pasta_id),
            INDEX idx_admin_material_arquivos_status (status),
            INDEX idx_admin_material_arquivos_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}



if (!function_exists('materiais_usuario_id')) {
    function materiais_usuario_id(): int
    {
        foreach (['usuario_id', 'user_id', 'id_usuario', 'cliente_id', 'aluno_id', 'id'] as $chave) {
            if (isset($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        foreach (['usuario', 'user', 'cliente', 'aluno', 'hobbi_user'] as $chave) {
            if (isset($_SESSION[$chave]) && is_array($_SESSION[$chave]) && isset($_SESSION[$chave]['id']) && (int)$_SESSION[$chave]['id'] > 0) {
                return (int)$_SESSION[$chave]['id'];
            }
        }

        return 0;
    }
}

if (!function_exists('materiais_bootstrap_simulados')) {
    function materiais_bootstrap_simulados(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulados (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED DEFAULT NULL,
            titulo VARCHAR(190) NOT NULL,
            descricao TEXT NULL,
            instrucoes TEXT NULL,
            tempo_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulados_curso (curso_id),
            KEY idx_admin_simulados_status (status),
            KEY idx_admin_simulados_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_questoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            simulado_id INT UNSIGNED NOT NULL,
            enunciado TEXT NOT NULL,
            explicacao TEXT NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_questoes_simulado (simulado_id),
            KEY idx_admin_simulado_questoes_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

if (!function_exists('materiais_cursos_ativos')) {
    function materiais_cursos_ativos(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT id FROM admin_cursos WHERE status = 'ativo'");
            $ids = [];
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            return array_values($ids);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('materiais_cursos_do_usuario')) {
    function materiais_cursos_do_usuario(PDO $pdo, int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return materiais_cursos_ativos($pdo);
        }

        if (function_exists('gab_curso_acesso_usuario_admin') && gab_curso_acesso_usuario_admin($pdo, $usuarioId)) {
            return materiais_cursos_ativos($pdo);
        }

        if (function_exists('gab_curso_acesso_configurado') && function_exists('gab_curso_acesso_ids')) {
            if (gab_curso_acesso_configurado($pdo, $usuarioId)) {
                return gab_curso_acesso_ids($pdo, $usuarioId);
            }
            return materiais_cursos_ativos($pdo);
        }

        try {
            $stmtConfigurado = $pdo->prepare('SELECT 1 FROM usuario_cursos WHERE usuario_id = ? LIMIT 1');
            $stmtConfigurado->execute([$usuarioId]);
            $configurado = (bool)$stmtConfigurado->fetchColumn();

            $stmt = $pdo->prepare("SELECT DISTINCT curso_id
                FROM usuario_cursos
                WHERE usuario_id = :usuario_id
                  AND status IN ('ativo','pago','aprovado','liberado','concluido','matriculado')");
            $stmt->execute([':usuario_id' => $usuarioId]);
            $ids = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
            if ($configurado) {
                return $ids;
            }
        } catch (Throwable $e) {
            // Mantém compatibilidade se a tabela ainda não estiver disponível.
        }

        return materiais_cursos_ativos($pdo);
    }
}

if (!function_exists('materiais_carregar_simulados')) {
    function materiais_carregar_simulados(PDO $pdo, int $usuarioId): array
    {
        $cursosPermitidos = materiais_cursos_do_usuario($pdo, $usuarioId);
        $params = [];
        $filtroCurso = "(s.curso_id IS NULL OR s.curso_id = 0)";

        if ($cursosPermitidos) {
            $placeholders = [];
            foreach ($cursosPermitidos as $idx => $cursoPermitidoId) {
                $chave = ':curso_id_' . $idx;
                $placeholders[] = $chave;
                $params[$chave] = (int)$cursoPermitidoId;
            }
            $filtroCurso = "((s.curso_id IS NULL OR s.curso_id = 0) OR s.curso_id IN (" . implode(',', $placeholders) . "))";
        }

        $sql = "
            SELECT
                s.id,
                s.curso_id,
                s.titulo,
                s.descricao,
                s.tempo_minutos,
                s.ordem,
                c.nome AS curso_nome,
                (SELECT COUNT(*) FROM admin_simulado_questoes q WHERE q.simulado_id = s.id) AS total_questoes
            FROM admin_simulados s
            LEFT JOIN admin_cursos c ON c.id = s.curso_id
            WHERE s.status = 'ativo' AND {$filtroCurso}
            ORDER BY (s.curso_id IS NOT NULL AND s.curso_id <> 0) ASC, s.ordem ASC, s.id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('materiais_meta_simulado')) {
    function materiais_meta_simulado(array $simulado): string
    {
        $partes = [];
        $partes[] = !empty($simulado['curso_id']) ? (string)($simulado['curso_nome'] ?? 'Curso específico') : 'Todos os cursos';
        $questoes = (int)($simulado['total_questoes'] ?? 0);
        $partes[] = $questoes . ($questoes === 1 ? ' questão' : ' questões');
        $tempo = (int)($simulado['tempo_minutos'] ?? 0);
        $partes[] = $tempo > 0 ? $tempo . ' min' : 'Sem limite';
        return implode(' · ', $partes);
    }
}

if (!function_exists('materiais_carregar_pastas')) {
    function materiais_carregar_pastas(PDO $pdo, ?int $cursoId = null): array
    {
        if ($cursoId !== null && $cursoId > 0) {
            $stmt = $pdo->prepare("\n                SELECT p.id, p.curso_id, p.titulo, p.descricao, COUNT(a.id) AS total_pdfs\n                FROM admin_material_pastas p\n                LEFT JOIN admin_material_arquivos a ON a.pasta_id = p.id AND a.status = 'ativo'\n                WHERE p.status = 'ativo' AND p.curso_id = :curso_id\n                GROUP BY p.id, p.curso_id, p.titulo, p.descricao\n                ORDER BY p.ordem ASC, p.id DESC\n            ");
            $stmt->execute([':curso_id' => $cursoId]);
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query("\n            SELECT p.id, p.curso_id, p.titulo, p.descricao, COUNT(a.id) AS total_pdfs\n            FROM admin_material_pastas p\n            LEFT JOIN admin_material_arquivos a ON a.pasta_id = p.id AND a.status = 'ativo'\n            WHERE p.status = 'ativo' AND p.curso_id IS NULL\n            GROUP BY p.id, p.curso_id, p.titulo, p.descricao\n            ORDER BY p.ordem ASC, p.id DESC\n        ");
        return $stmt ? $stmt->fetchAll() : [];
    }
}

if (!function_exists('materiais_arquivos_da_pasta')) {
    function materiais_arquivos_da_pasta(PDO $pdo, int $pastaId): array
    {
        $stmt = $pdo->prepare("\n            SELECT titulo, arquivo, arquivo_nome, 'PDF' AS tipo\n            FROM admin_material_arquivos\n            WHERE pasta_id = :pasta_id AND status = 'ativo'\n            ORDER BY ordem ASC, id DESC\n        ");
        $stmt->execute([':pasta_id' => $pastaId]);
        $arquivos = [];
        foreach ($stmt->fetchAll() as $arquivo) {
            $arquivos[] = [
                'titulo' => materiais_nome_arquivo((string)$arquivo['arquivo'], (string)($arquivo['titulo'] ?: $arquivo['arquivo_nome'])),
                'arquivo' => (string)$arquivo['arquivo'],
                'tipo' => 'PDF',
            ];
        }
        return $arquivos;
    }
}

$cursoId = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?: 0;
$moduloId = filter_input(INPUT_GET, 'modulo_id', FILTER_VALIDATE_INT) ?: 0;
$aulaId = filter_input(INPUT_GET, 'aula_id', FILTER_VALIDATE_INT) ?: 0;
$pastaId = filter_input(INPUT_GET, 'pasta_id', FILTER_VALIDATE_INT) ?: 0;
$tipo = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['tipo'] ?? ''));
$usuarioId = materiais_usuario_id();

$nivel = 'cursos';
$cursos = [];
$modulos = [];
$aulas = [];
$arquivos = [];
$pastas = [];
$simuladosDisponiveis = [];
$pastaAtual = null;
$cursoAtual = null;
$moduloAtual = null;
$aulaAtual = null;
$backUrl = '';
$emptyMessage = '';
$erroPagina = '';

try {
    $pdo = materiais_db();
    materiais_bootstrap_admin_materiais($pdo);
    materiais_bootstrap_simulados($pdo);

    if ($cursoId > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoId)) {
        throw new RuntimeException('Este curso não está liberado para o seu usuário.');
    }

    $simuladosDisponiveis = materiais_carregar_simulados($pdo, $usuarioId);

    if ($cursoId > 0) {
        $stmtCurso = $pdo->prepare("SELECT id, nome, descricao FROM admin_cursos WHERE id = :id AND status = 'ativo' LIMIT 1");
        $stmtCurso->execute([':id' => $cursoId]);
        $cursoAtual = $stmtCurso->fetch();

        if (!$cursoAtual) {
            throw new RuntimeException('Curso não encontrado ou indisponível.');
        }
    }

    if ($cursoAtual && $moduloId > 0) {
        $stmtModulo = $pdo->prepare("\n            SELECT id, curso_id, titulo, descricao\n            FROM admin_curso_modulos\n            WHERE id = :id AND curso_id = :curso_id\n            LIMIT 1\n        ");
        $stmtModulo->execute([':id' => $moduloId, ':curso_id' => $cursoId]);
        $moduloAtual = $stmtModulo->fetch();

        if (!$moduloAtual) {
            throw new RuntimeException('Módulo não encontrado para este curso.');
        }
    }


    if ($tipo === 'simulados') {
        $nivel = 'simulados';
        $backUrl = 'materiais.php';
        $emptyMessage = 'Nenhum simulado disponível para o seu acesso.';
    } elseif ($pastaId > 0) {
        $stmtPasta = $pdo->prepare("\n            SELECT p.*, c.nome AS curso_nome, c.descricao AS curso_descricao\n            FROM admin_material_pastas p\n            LEFT JOIN admin_cursos c ON c.id = p.curso_id\n            WHERE p.id = :id AND p.status = 'ativo'\n            LIMIT 1\n        ");
        $stmtPasta->execute([':id' => $pastaId]);
        $pastaAtual = $stmtPasta->fetch();

        if (!$pastaAtual) {
            throw new RuntimeException('Pasta de materiais não encontrada ou indisponível.');
        }

        if (!$cursoAtual && !empty($pastaAtual['curso_id'])) {
            $cursoAtual = [
                'id' => (int)$pastaAtual['curso_id'],
                'nome' => (string)($pastaAtual['curso_nome'] ?? 'Curso'),
                'descricao' => (string)($pastaAtual['curso_descricao'] ?? ''),
            ];
            $cursoId = (int)$pastaAtual['curso_id'];
        }

        if (!empty($pastaAtual['curso_id']) && $cursoId > 0 && (int)$pastaAtual['curso_id'] !== $cursoId) {
            throw new RuntimeException('Essa pasta não pertence ao curso informado.');
        }

        $nivel = 'pasta_arquivos';
        $arquivos = materiais_arquivos_da_pasta($pdo, (int)$pastaAtual['id']);
        $backUrl = !empty($pastaAtual['curso_id']) || $cursoId > 0 ? 'materiais.php?curso_id=' . (int)$cursoId : 'materiais.php';
        $emptyMessage = 'Nenhum PDF cadastrado nesta pasta.';
    } elseif ($cursoAtual && $moduloAtual && $aulaId > 0) {
        $stmtAula = $pdo->prepare("\n            SELECT id, curso_id, modulo_id, titulo, descricao, pdf_arquivo, pdf_nome, material_arquivo, material_nome\n            FROM admin_curso_aulas\n            WHERE id = :id AND curso_id = :curso_id AND modulo_id = :modulo_id\n            LIMIT 1\n        ");
        $stmtAula->execute([':id' => $aulaId, ':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        $aulaAtual = $stmtAula->fetch();

        if (!$aulaAtual) {
            throw new RuntimeException('Videoaula não encontrada para este módulo.');
        }

        $nivel = 'arquivos';
        $arquivos = materiais_arquivos_da_aula($aulaAtual);
        $backUrl = 'materiais.php?curso_id=' . (int)$cursoId . '&modulo_id=' . (int)$moduloId;
        $emptyMessage = 'Nenhum PDF, simulado ou material cadastrado nesta videoaula.';
    } elseif ($cursoAtual && $moduloAtual) {
        $nivel = 'aulas';
        $stmtAulas = $pdo->prepare("\n            SELECT\n                a.id,\n                a.titulo,\n                a.descricao,\n                a.ordem,\n                CASE\n                    WHEN COALESCE(NULLIF(a.pdf_arquivo, ''), NULLIF(a.material_arquivo, '')) IS NULL THEN 0\n                    ELSE 1\n                END AS tem_arquivo\n            FROM admin_curso_aulas a\n            WHERE a.curso_id = :curso_id AND a.modulo_id = :modulo_id\n            ORDER BY a.ordem ASC, a.id ASC\n        ");
        $stmtAulas->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
        $aulas = $stmtAulas->fetchAll();
        $backUrl = 'materiais.php?curso_id=' . (int)$cursoId;
        $emptyMessage = 'Nenhuma videoaula cadastrada neste módulo.';
    } elseif ($cursoAtual) {
        $nivel = 'modulos';
        $stmtModulos = $pdo->prepare("\n            SELECT m.id, m.titulo, m.descricao, m.ordem, COUNT(a.id) AS total_aulas\n            FROM admin_curso_modulos m\n            LEFT JOIN admin_curso_aulas a ON a.modulo_id = m.id\n            WHERE m.curso_id = :curso_id\n            GROUP BY m.id, m.titulo, m.descricao, m.ordem\n            ORDER BY m.ordem ASC, m.id ASC\n        ");
        $stmtModulos->execute([':curso_id' => $cursoId]);
        $modulos = $stmtModulos->fetchAll();
        $pastas = materiais_carregar_pastas($pdo, (int)$cursoId);
        $backUrl = 'materiais.php';
        $emptyMessage = 'Nenhum módulo ou pasta de material cadastrado neste curso.';
    } else {
        $nivel = 'cursos';
        $filtroAcessoCursos = '';
        $paramsAcessoCursos = [];
        if (function_exists('gab_curso_acesso_sql')) {
            [$filtroAcessoCursos, $paramsAcessoCursos] = gab_curso_acesso_sql($pdo, $usuarioId, 'c.id', 'materiais_curso');
        }
        $stmtCursos = $pdo->prepare("
            SELECT c.id, c.nome, c.descricao, COUNT(m.id) AS total_modulos
            FROM admin_cursos c
            LEFT JOIN admin_curso_modulos m ON m.curso_id = c.id
            WHERE c.status = 'ativo'
            {$filtroAcessoCursos}
            GROUP BY c.id, c.nome, c.descricao
            ORDER BY c.ordem ASC, c.id DESC
        ");
        $stmtCursos->execute($paramsAcessoCursos);
        $cursos = $stmtCursos->fetchAll();
        $pastas = materiais_carregar_pastas($pdo, null);
        $emptyMessage = 'Nenhum curso, material geral ou simulado cadastrado.';
    }
} catch (Throwable $e) {
    $erroPagina = 'Não foi possível carregar os materiais agora: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiais | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#c98f0e;
            --black:#000000;
            --page:#f5f5f6;
            --panel:#fafafa;
            --text:#111827;
            --muted:#6b7280;
            --line:rgba(0,0,0,.08);
            --white:#ffffff;
            --shadow:0 10px 25px rgba(0,0,0,.05);
        }

        *{margin:0;padding:0;box-sizing:border-box;}
        html,body{width:100%;min-height:100%;}
        body{font-family:'Ubuntu',sans-serif;background:var(--page);color:var(--text);}
        a{text-decoration:none;color:inherit;}
        button,input{font-family:inherit;}
        img{display:block;max-width:100%;}

        .topbar{
            height:98px;
            background:var(--black);
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 56px 0 36px;
            position:sticky;
            top:0;
            z-index:50;
            border-bottom:1px solid rgba(0,0,0,.08);
        }

        .topbar__logo img{
            width:86px;
            height:auto;
            max-height:82px;
            object-fit:contain;
        }

        .topbar__actions{
            display:flex;
            align-items:center;
            gap:22px;
        }

        .user-pill{
            min-width:70px;
            height:34px;
            padding:0 12px;
            border-radius:999px;
            background:rgba(255,255,255,.12);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            border:0;
            cursor:pointer;
        }

        .user-pill i{width:18px;height:18px;}
        .menu-btn{
            width:38px;
            height:38px;
            border:0;
            background:transparent;
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
        }
        .menu-btn i{width:26px;height:26px;}

        .menu-backdrop{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.42);
            opacity:0;
            pointer-events:none;
            transition:opacity .22s ease;
            z-index:65;
        }
        .menu-backdrop.is-open{opacity:1;pointer-events:auto;}

        .side-menu{
            position:fixed;
            top:0;
            right:0;
            width:min(252px,86vw);
            height:100dvh;
            background:var(--yellow);
            color:#fff;
            z-index:80;
            transform:translateX(102%);
            transition:transform .24s ease;
            box-shadow:-16px 0 34px rgba(0,0,0,.28);
            overflow-y:auto;
            border-left:1px solid rgba(0,0,0,.12);
        }
        .side-menu.is-open{transform:translateX(0);}
        .side-menu__head{
            height:72px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:0 15px 0 22px;
            border-bottom:1px solid rgba(0,0,0,.16);
            background:rgba(0,0,0,.03);
        }
        .side-menu__title{font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:.02em;}
        .side-menu__close{
            width:38px;height:38px;border:0;border-radius:10px;background:rgba(0,0,0,.08);color:#fff;
            display:inline-flex;align-items:center;justify-content:center;cursor:pointer;
        }
        .side-menu__close i{width:22px;height:22px;}
        .side-menu__list{list-style:none;margin:0;padding:0;}
        .side-menu__link{
            min-height:46px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding:0 22px;
            border-bottom:1px solid rgba(0,0,0,.16);
            color:#fff;
            font-weight:700;
            font-size:1rem;
            line-height:1.15;
            text-shadow:0 1px 0 rgba(0,0,0,.07);
            transition:background .18s ease;
        }
        .side-menu__link:hover{background:rgba(0,0,0,.10);}
        .side-menu__link i{width:20px;height:20px;flex:0 0 auto;}

        .page-wrap{
            width:100%;
            max-width:1226px;
            margin:34px auto 0;
            padding:0 0 42px;
        }

        .materials-card{
            background:var(--panel);
            border-radius:7px 7px 0 0;
            overflow:hidden;
            box-shadow:var(--shadow);
        }

        .materials-title{
            height:80px;
            background:var(--yellow);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:2rem;
            font-weight:700;
            letter-spacing:.01em;
            border-radius:7px 7px 0 0;
        }

        .materials-body{
            min-height:382px;
            padding:26px 34px 48px;
            background:#fafafa;
        }

        .breadcrumb{
            display:flex;
            align-items:center;
            gap:10px;
            min-height:26px;
            margin-bottom:28px;
            color:#0f172a;
            font-size:1rem;
        }
        .breadcrumb i{width:18px;height:18px;}
        .breadcrumb .home{color:var(--yellow);fill:var(--yellow);}
        .breadcrumb a,.breadcrumb span{display:inline-flex;align-items:center;gap:8px;}
        .breadcrumb .current{color:#e0a100;}

        .search-row{
            display:flex;
            align-items:center;
            gap:18px;
            margin-bottom:34px;
            color:#111827;
        }
        .search-row i{width:23px;height:23px;}
        .search-input{
            width:100%;
            border:0;
            outline:0;
            background:transparent;
            color:#111827;
            font-size:1.15rem;
            line-height:1.4;
        }
        .search-input::placeholder{color:#6b7280;}

        .back-link{
            display:inline-flex;
            align-items:center;
            gap:12px;
            margin:0 0 18px 18px;
            color:#d89100;
            font-size:1rem;
        }
        .back-link i{width:18px;height:18px;}

        .items-list{
            display:grid;
            gap:16px;
        }

        .item-row{
            min-height:62px;
            background:#fff;
            border-radius:8px;
            display:flex;
            align-items:center;
            gap:14px;
            padding:0 24px;
            color:#111827;
            box-shadow:0 4px 14px rgba(0,0,0,.025);
            transition:transform .15s ease, box-shadow .15s ease;
        }
        .item-row:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,0,0,.045);}
        .item-row__icon{color:var(--yellow);width:22px;height:22px;flex:0 0 auto;fill:var(--yellow);}
        .item-row__text{font-size:1rem;letter-spacing:.04em;color:#d89100;white-space:normal;}

        .file-row{
            justify-content:space-between;
            gap:18px;
            padding-right:28px;
        }
        .file-row__left{
            display:flex;
            align-items:center;
            gap:14px;
            min-width:0;
        }
        .file-row__left i{
            width:21px;
            height:21px;
            color:#000;
            flex:0 0 auto;
        }
        .file-row__title{
            font-size:1.08rem;
            letter-spacing:.05em;
            color:#000;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .file-row__actions{
            display:flex;
            align-items:center;
            gap:18px;
            color:var(--yellow);
            flex:0 0 auto;
        }
        .file-row__actions a{
            color:var(--yellow);
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
        .file-row__actions i{width:19px;height:19px;}

        .empty-state{
            background:#fff;
            border-radius:8px;
            padding:30px 24px;
            color:#6b7280;
            text-align:center;
            font-size:1rem;
        }

        @media(max-width:980px){
            .topbar{height:86px;padding:0 28px 0 22px;}
            .topbar__logo img{width:76px;max-height:70px;}
            .page-wrap{margin:24px auto 0;padding:0 14px 32px;}
            .materials-title{height:72px;font-size:1.75rem;}
            .materials-body{padding:24px 22px 40px;}
        }

        @media(max-width:640px){
            .topbar{height:80px;padding:0 18px 0 12px;}
            .topbar__logo img{width:70px;}
            .topbar__actions{gap:12px;}
            .user-pill{min-width:58px;height:32px;padding:0 10px;}
            .menu-btn{width:34px;height:34px;}
            .menu-btn i{width:24px;height:24px;}
            .page-wrap{margin:18px auto 0;padding:0 10px 28px;}
            .materials-title{height:64px;font-size:1.45rem;}
            .materials-body{padding:22px 14px 34px;}
            .breadcrumb{gap:8px;margin-bottom:24px;font-size:.92rem;flex-wrap:wrap;}
            .search-row{gap:13px;margin-bottom:26px;}
            .search-input{font-size:1rem;}
            .item-row{min-height:58px;padding:0 18px;}
            .item-row__text{font-size:.95rem;letter-spacing:.03em;}
            .file-row{align-items:flex-start;padding:16px 16px;}
            .file-row__title{white-space:normal;font-size:.96rem;line-height:1.35;}
            .file-row__actions{gap:14px;padding-top:1px;}
            .side-menu{width:78vw;}
        }

        .item-row__content{display:grid;gap:3px;min-width:0;}
        .item-row__meta{font-size:.84rem;color:#6b7280;letter-spacing:0;text-transform:none;}
        .item-row.folder-row{justify-content:flex-start;}
        .item-row.folder-row .item-row__text{font-weight:700;}
        .breadcrumb .breadcrumb-link{color:#d89100;}
        .breadcrumb .breadcrumb-current{color:#e0a100;font-weight:700;}
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/barra-superior.php'; ?>

    <main class="page-wrap">
        <section class="materials-card" aria-label="Materiais">
            <header class="materials-title">Materiais</header>

            <div class="materials-body">
                <nav class="breadcrumb" aria-label="Navegação dos materiais">
                    <i data-lucide="chevron-right"></i>
                    <a href="materiais.php" aria-label="Início dos materiais"><i class="home" data-lucide="home"></i></a>

                    <?php if ($cursoAtual): ?>
                        <i data-lucide="chevron-right"></i>
                        <?php if ($moduloAtual || $aulaAtual): ?>
                            <a class="breadcrumb-link" href="materiais.php?curso_id=<?php echo (int)$cursoAtual['id']; ?>"><?php echo materiais_esc($cursoAtual['nome']); ?></a>
                        <?php else: ?>
                            <span class="breadcrumb-current"><?php echo materiais_esc($cursoAtual['nome']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($moduloAtual): ?>
                        <i data-lucide="chevron-right"></i>
                        <?php if ($aulaAtual): ?>
                            <a class="breadcrumb-link" href="materiais.php?curso_id=<?php echo (int)$cursoAtual['id']; ?>&modulo_id=<?php echo (int)$moduloAtual['id']; ?>"><?php echo materiais_esc($moduloAtual['titulo']); ?></a>
                        <?php else: ?>
                            <span class="breadcrumb-current"><?php echo materiais_esc($moduloAtual['titulo']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($aulaAtual): ?>
                        <i data-lucide="chevron-right"></i>
                        <span class="breadcrumb-current"><?php echo materiais_esc($aulaAtual['titulo']); ?></span>
                    <?php endif; ?>

                    <?php if ($pastaAtual): ?>
                        <i data-lucide="chevron-right"></i>
                        <span class="breadcrumb-current"><?php echo materiais_esc($pastaAtual['titulo']); ?></span>
                    <?php endif; ?>

                    <?php if ($nivel === 'simulados'): ?>
                        <i data-lucide="chevron-right"></i>
                        <span class="breadcrumb-current">Simulados</span>
                    <?php endif; ?>
                </nav>

                <div class="search-row">
                    <i data-lucide="search"></i>
                    <input type="search" id="searchInput" class="search-input" placeholder="Buscar material ou simulado..." autocomplete="off">
                </div>

                <?php if ($backUrl !== ''): ?>
                    <a href="<?php echo materiais_esc($backUrl); ?>" class="back-link"><i data-lucide="chevron-left"></i> Diretório anterior</a>
                <?php endif; ?>

                <div class="items-list" id="itemsList">
                    <?php if ($erroPagina !== ''): ?>
                        <div class="empty-state"><?php echo materiais_esc($erroPagina); ?></div>
                    <?php elseif ($nivel === 'cursos'): ?>
                        <?php if (!empty($pastas) || !empty($simuladosDisponiveis) || !empty($cursos)): ?>
                            <?php foreach ($pastas as $pasta): ?>
                                <?php
                                    $totalPdfs = (int)($pasta['total_pdfs'] ?? 0);
                                    $metaPasta = 'Material geral · ' . $totalPdfs . ($totalPdfs === 1 ? ' PDF' : ' PDFs');
                                    $busca = materiais_lower((string)$pasta['titulo'] . ' ' . (string)($pasta['descricao'] ?? '') . ' material geral pdf');
                                ?>
                                <a href="materiais.php?pasta_id=<?php echo (int)$pasta['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder-open"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc($pasta['titulo']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaPasta); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>

                            <?php if (!empty($simuladosDisponiveis)): ?>
                                <?php
                                    $totalSimulados = count($simuladosDisponiveis);
                                    $metaSimulados = $totalSimulados . ($totalSimulados === 1 ? ' simulado disponível' : ' simulados disponíveis');
                                    $busca = materiais_lower('simulados provas questões revisão exercícios');
                                ?>
                                <a href="materiais.php?tipo=simulados" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder-check"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text">Simulados</span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaSimulados); ?></span>
                                    </span>
                                </a>
                            <?php endif; ?>

                            <?php foreach ($cursos as $curso): ?>
                                <?php
                                    $totalModulos = (int)($curso['total_modulos'] ?? 0);
                                    $metaCurso = $totalModulos . ($totalModulos === 1 ? ' módulo' : ' módulos');
                                    $busca = materiais_lower((string)$curso['nome'] . ' ' . (string)($curso['descricao'] ?? ''));
                                ?>
                                <a href="materiais.php?curso_id=<?php echo (int)$curso['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc($curso['nome']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaCurso); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state"><?php echo materiais_esc($emptyMessage); ?></div>
                        <?php endif; ?>
                    <?php elseif ($nivel === 'modulos'): ?>
                        <?php if (!empty($pastas) || !empty($modulos)): ?>
                            <?php foreach ($pastas as $pasta): ?>
                                <?php
                                    $totalPdfs = (int)($pasta['total_pdfs'] ?? 0);
                                    $isGeral = empty($pasta['curso_id']);
                                    $metaPasta = ($isGeral ? 'Material geral' : 'Material do curso') . ' · ' . $totalPdfs . ($totalPdfs === 1 ? ' PDF' : ' PDFs');
                                    $busca = materiais_lower((string)$pasta['titulo'] . ' ' . (string)($pasta['descricao'] ?? '') . ' pdf material');
                                ?>
                                <a href="materiais.php?curso_id=<?php echo (int)$cursoAtual['id']; ?>&pasta_id=<?php echo (int)$pasta['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder-open"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc($pasta['titulo']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaPasta); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>

                            <?php foreach ($modulos as $modulo): ?>
                                <?php
                                    $totalAulas = (int)($modulo['total_aulas'] ?? 0);
                                    $metaModulo = $totalAulas . ($totalAulas === 1 ? ' videoaula' : ' videoaulas');
                                    $busca = materiais_lower((string)$modulo['titulo'] . ' ' . (string)($modulo['descricao'] ?? ''));
                                ?>
                                <a href="materiais.php?curso_id=<?php echo (int)$cursoAtual['id']; ?>&modulo_id=<?php echo (int)$modulo['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc($modulo['titulo']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaModulo); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state"><?php echo materiais_esc($emptyMessage); ?></div>
                        <?php endif; ?>
                    <?php elseif ($nivel === 'aulas'): ?>
                        <?php if (!empty($aulas)): ?>
                            <?php foreach ($aulas as $aula): ?>
                                <?php
                                    $temArquivo = (int)($aula['tem_arquivo'] ?? 0) > 0;
                                    $metaAula = $temArquivo ? 'Documentos disponíveis' : 'Pasta vazia';
                                    $busca = materiais_lower((string)$aula['titulo'] . ' ' . (string)($aula['descricao'] ?? ''));
                                ?>
                                <a href="materiais.php?curso_id=<?php echo (int)$cursoAtual['id']; ?>&modulo_id=<?php echo (int)$moduloAtual['id']; ?>&aula_id=<?php echo (int)$aula['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="folder"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc($aula['titulo']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaAula); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state"><?php echo materiais_esc($emptyMessage); ?></div>
                        <?php endif; ?>
                    <?php elseif ($nivel === 'simulados'): ?>
                        <?php if (!empty($simuladosDisponiveis)): ?>
                            <?php foreach ($simuladosDisponiveis as $simulado): ?>
                                <?php
                                    $metaSimulado = materiais_meta_simulado($simulado);
                                    $busca = materiais_lower((string)$simulado['titulo'] . ' ' . (string)($simulado['descricao'] ?? '') . ' ' . $metaSimulado . ' simulado prova questão');
                                ?>
                                <a href="simulados.php?simulado_id=<?php echo (int)$simulado['id']; ?>" class="item-row folder-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <i class="item-row__icon" data-lucide="clipboard-check"></i>
                                    <span class="item-row__content">
                                        <span class="item-row__text"><?php echo materiais_esc((string)$simulado['titulo']); ?></span>
                                        <span class="item-row__meta"><?php echo materiais_esc($metaSimulado); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state"><?php echo materiais_esc($emptyMessage); ?></div>
                        <?php endif; ?>
                    <?php elseif ($nivel === 'arquivos' || $nivel === 'pasta_arquivos'): ?>
                        <?php if (!empty($arquivos)): ?>
                            <?php foreach ($arquivos as $arquivo): ?>
                                <?php $busca = materiais_lower((string)$arquivo['titulo'] . ' ' . (string)$arquivo['tipo']); ?>
                                <div class="item-row file-row searchable-item" data-search="<?php echo materiais_esc($busca); ?>">
                                    <div class="file-row__left">
                                        <i data-lucide="file-text"></i>
                                        <span class="file-row__title"><?php echo materiais_esc($arquivo['titulo']); ?></span>
                                    </div>
                                    <div class="file-row__actions">
                                        <a href="<?php echo materiais_esc($arquivo['arquivo']); ?>" target="_blank" rel="noopener" aria-label="Visualizar arquivo"><i data-lucide="eye"></i></a>
                                        <a href="<?php echo materiais_esc($arquivo['arquivo']); ?>" download aria-label="Baixar arquivo"><i data-lucide="download"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state"><?php echo materiais_esc($emptyMessage); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }

        const menuBtn = document.querySelector('.menu-btn');
        const sideMenu = document.getElementById('sideMenu');
        const menuBackdrop = document.getElementById('menuBackdrop');
        const closeMenu = document.getElementById('closeMenu');

        function abrirMenu() {
            if (!sideMenu || !menuBackdrop) return;
            sideMenu.classList.add('is-open');
            menuBackdrop.classList.add('is-open');
            sideMenu.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function fecharMenu() {
            if (!sideMenu || !menuBackdrop) return;
            sideMenu.classList.remove('is-open');
            menuBackdrop.classList.remove('is-open');
            sideMenu.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (menuBtn) menuBtn.addEventListener('click', abrirMenu);
        if (closeMenu) closeMenu.addEventListener('click', fecharMenu);
        if (menuBackdrop) menuBackdrop.addEventListener('click', fecharMenu);
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') fecharMenu();
        });

        const searchInput = document.getElementById('searchInput');
        const searchableItems = Array.from(document.querySelectorAll('.searchable-item'));
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const termo = searchInput.value.trim().toLowerCase();
                searchableItems.forEach(function (item) {
                    const alvo = item.getAttribute('data-search') || item.textContent.toLowerCase();
                    item.style.display = alvo.includes(termo) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>
