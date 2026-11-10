<?php
// aulas.php
// VERSÃO FINAL COM AVALIAÇÃO POR ESTRELAS, BUSCA DO MÓDULO, PROGRESSO, CHECK VERDE E TÓPICOS FUNCIONANDO.
// Página de aula do aluno: carrega somente os conteúdos do módulo selecionado.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
require_once __DIR__ . '/includes/aula-descricao-lib.php';
require_once __DIR__ . '/includes/indicacoes-lib.php';
auth_require_login();
$barraSuperiorInclude = __DIR__ . '/includes/barra-superior.php';
if (!is_file($barraSuperiorInclude)) {
    $barraSuperiorInclude = __DIR__ . '/barra-superior.php';
}

$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}

if (!function_exists('esc')) {
    function esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('aulas_env')) {
    function aulas_env(string $path): array
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
            if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);
            $env[trim($chave)] = trim($valor, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }
}

if (!function_exists('aulas_db')) {
    function aulas_db(): PDO
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

        $env = aulas_env(__DIR__ . '/.env');
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

if (!function_exists('aulas_youtube_id')) {
    function aulas_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/|youtu\.be/)([a-zA-Z0-9_-]{6,20})~i', $url, $match)) {
            return (string)$match[1];
        }

        $partes = parse_url($url);
        if (!$partes || empty($partes['host'])) {
            return '';
        }

        $host = strtolower((string)$partes['host']);
        $hostEhYoutube = $host === 'youtu.be'
            || $host === 'www.youtu.be'
            || $host === 'youtube.com'
            || $host === 'www.youtube.com'
            || str_ends_with($host, '.youtube.com')
            || $host === 'youtube-nocookie.com'
            || $host === 'www.youtube-nocookie.com'
            || str_ends_with($host, '.youtube-nocookie.com');

        if (!$hostEhYoutube) {
            return '';
        }

        $query = [];
        parse_str((string)($partes['query'] ?? ''), $query);
        $videoId = isset($query['v']) && is_string($query['v']) ? $query['v'] : '';

        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$videoId);
        return is_string($videoId) && preg_match('/^[a-zA-Z0-9_-]{6,20}$/', $videoId)
            ? $videoId
            : '';
    }
}

if (!function_exists('aulas_youtube_embed')) {
    function aulas_youtube_embed(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $videoId = aulas_youtube_id($url);
        if ($videoId !== '') {
            return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId)
                . '?autoplay=0&controls=0&disablekb=1&fs=0&iv_load_policy=3&playsinline=1&rel=0';
        }

        return $url;
    }
}

if (!function_exists('aulas_pdf_do_item')) {
    function aulas_pdf_do_item(array $aula): array
    {
        $arquivo = trim((string)($aula['pdf_arquivo'] ?? ''));
        $nome = trim((string)($aula['pdf_nome'] ?? ''));

        if ($arquivo === '') {
            $arquivo = trim((string)($aula['material_arquivo'] ?? ''));
            $nome = trim((string)($aula['material_nome'] ?? ''));
        }

        if ($arquivo === '') {
            return ['arquivo' => '', 'nome' => ''];
        }

        if ($nome === '') {
            $nome = basename($arquivo);
        }

        return ['arquivo' => $arquivo, 'nome' => $nome];
    }
}

if (!function_exists('aulas_pdf_chave')) {
    function aulas_pdf_chave(string $arquivo): string
    {
        return sha1(trim($arquivo));
    }
}

if (!function_exists('aulas_duracao_segundos')) {
    function aulas_duracao_segundos(string $valor): int
    {
        $texto = trim(function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor));
        if ($texto === '') {
            return 0;
        }

        $texto = str_replace(',', '.', $texto);
        $segundos = 0.0;

        if (preg_match('/(\d+(?:\.\d+)?)\s*(h|hora|horas)/u', $texto, $match)) {
            $segundos += (float)$match[1] * 3600;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(m|min|minuto|minutos)/u', $texto, $match)) {
            $segundos += (float)$match[1] * 60;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(s|seg|segundo|segundos)/u', $texto, $match)) {
            $segundos += (float)$match[1];
        }

        if ($segundos <= 0 && preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $texto, $match)) {
            if (isset($match[3]) && $match[3] !== '') {
                $segundos = ((int)$match[1] * 3600) + ((int)$match[2] * 60) + (int)$match[3];
            } else {
                $segundos = ((int)$match[1] * 60) + (int)$match[2];
            }
        }

        if ($segundos <= 0 && is_numeric($texto)) {
            // Mantém o mesmo padrão da tela principal: número isolado representa horas.
            $segundos = (float)$texto * 3600;
        }

        return max(0, (int)round($segundos));
    }
}

if (!function_exists('aulas_formatar_duracao_resumo')) {
    function aulas_formatar_duracao_resumo(int $segundos): string
    {
        $segundos = max(0, $segundos);
        if ($segundos <= 0) {
            return 'Não informado';
        }

        $horas = intdiv($segundos, 3600);
        $minutos = (int)ceil(($segundos % 3600) / 60);
        if ($minutos >= 60) {
            $horas++;
            $minutos = 0;
        }

        if ($horas > 0 && $minutos > 0) {
            return $horas . 'h ' . $minutos . 'min';
        }
        if ($horas > 0) {
            return $horas . 'h';
        }
        return max(1, $minutos) . 'min';
    }
}

if (!function_exists('aulas_garantir_coluna')) {
    function aulas_garantir_coluna(PDO $pdo, string $tabela, string $coluna, string $definicao): void
    {
        $permitido = static fn(string $nome): bool => (bool)preg_match('/^[a-zA-Z0-9_]+$/', $nome);
        if (!$permitido($tabela) || !$permitido($coluna)) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabela
              AND COLUMN_NAME = :coluna
        ");
        $stmt->execute([':tabela' => $tabela, ':coluna' => $coluna]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    }
}

if (!function_exists('aulas_json')) {
    function aulas_json(array $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('aulas_usuario_id')) {
    function aulas_usuario_id(): int
    {
        $chavesDiretas = ['usuario_id', 'user_id', 'id_usuario', 'cliente_id', 'aluno_id', 'id'];
        foreach ($chavesDiretas as $chave) {
            if (isset($_SESSION[$chave]) && (int)$_SESSION[$chave] > 0) {
                return (int)$_SESSION[$chave];
            }
        }

        $chavesObjetos = ['usuario', 'user', 'cliente', 'aluno'];
        foreach ($chavesObjetos as $chave) {
            if (isset($_SESSION[$chave]) && is_array($_SESSION[$chave]) && isset($_SESSION[$chave]['id']) && (int)$_SESSION[$chave]['id'] > 0) {
                return (int)$_SESSION[$chave]['id'];
            }
        }

        return 0;
    }
}

if (!function_exists('aulas_limitar_texto')) {
    function aulas_limitar_texto(string $texto, int $limite): string
    {
        $texto = trim($texto);
        if ($limite <= 0) {
            return $texto;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $limite, 'UTF-8');
        }

        return substr($texto, 0, $limite);
    }
}

if (!function_exists('aulas_garantir_tabelas')) {
    function aulas_garantir_tabelas(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS aulas_progresso (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                curso_id INT UNSIGNED NOT NULL,
                modulo_id INT UNSIGNED NOT NULL,
                aula_id INT UNSIGNED NOT NULL,
                assistido TINYINT(1) NOT NULL DEFAULT 0,
                video_posicao_segundos INT UNSIGNED NOT NULL DEFAULT 0,
                video_duracao_segundos INT UNSIGNED NOT NULL DEFAULT 0,
                video_percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                video_iniciado_em DATETIME DEFAULT NULL,
                video_atualizado_em DATETIME DEFAULT NULL,
                assistido_em DATETIME DEFAULT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_aulas_progresso_usuario_aula (usuario_id, aula_id),
                KEY idx_aulas_progresso_usuario_modulo (usuario_id, modulo_id),
                KEY idx_aulas_progresso_aula (aula_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        aulas_garantir_coluna($pdo, 'aulas_progresso', 'video_posicao_segundos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER assistido');
        aulas_garantir_coluna($pdo, 'aulas_progresso', 'video_duracao_segundos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_posicao_segundos');
        aulas_garantir_coluna($pdo, 'aulas_progresso', 'video_percentual', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER video_duracao_segundos');
        aulas_garantir_coluna($pdo, 'aulas_progresso', 'video_iniciado_em', 'DATETIME DEFAULT NULL AFTER video_percentual');
        aulas_garantir_coluna($pdo, 'aulas_progresso', 'video_atualizado_em', 'DATETIME DEFAULT NULL AFTER video_iniciado_em');

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS aulas_forum_topicos (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED DEFAULT NULL,
                curso_id INT UNSIGNED NOT NULL,
                modulo_id INT UNSIGNED NOT NULL,
                aula_id INT UNSIGNED NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                texto TEXT NOT NULL,
                visibilidade ENUM('todos','monitores') NOT NULL DEFAULT 'todos',
                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_aulas_forum_topicos_aula (aula_id),
                KEY idx_aulas_forum_topicos_modulo (modulo_id),
                KEY idx_aulas_forum_topicos_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS aulas_forum_respostas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                topico_id BIGINT UNSIGNED NOT NULL,
                usuario_id BIGINT UNSIGNED DEFAULT NULL,
                autor_nome VARCHAR(160) DEFAULT NULL,
                mensagem TEXT NOT NULL,
                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_aulas_forum_respostas_topico (topico_id),
                KEY idx_aulas_forum_respostas_usuario (usuario_id),
                KEY idx_aulas_forum_respostas_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS aulas_avaliacoes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                curso_id INT UNSIGNED NOT NULL,
                modulo_id INT UNSIGNED NOT NULL,
                aula_id INT UNSIGNED NOT NULL,
                nota TINYINT UNSIGNED NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_aulas_avaliacoes_usuario_aula (usuario_id, aula_id),
                KEY idx_aulas_avaliacoes_aula (aula_id),
                KEY idx_aulas_avaliacoes_modulo (modulo_id),
                KEY idx_aulas_avaliacoes_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS aulas_pdf_progresso (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id BIGINT UNSIGNED NOT NULL,
                curso_id INT UNSIGNED NOT NULL,
                modulo_id INT UNSIGNED NOT NULL,
                aula_id INT UNSIGNED NOT NULL,
                pdf_chave CHAR(40) NOT NULL,
                pagina INT UNSIGNED NOT NULL DEFAULT 1,
                total_paginas INT UNSIGNED NOT NULL DEFAULT 0,
                zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_aulas_pdf_progresso_usuario_pdf (usuario_id, aula_id, pdf_chave),
                KEY idx_aulas_pdf_progresso_usuario (usuario_id),
                KEY idx_aulas_pdf_progresso_aula (aula_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('aulas_validar_aula')) {
    function aulas_validar_aula(PDO $pdo, int $cursoId, int $moduloId, int $aulaId): bool
    {
        $stmt = $pdo->prepare("
            SELECT id
            FROM admin_curso_aulas
            WHERE id = :aula_id AND curso_id = :curso_id AND modulo_id = :modulo_id
            LIMIT 1
        ");
        $stmt->execute([
            ':aula_id' => $aulaId,
            ':curso_id' => $cursoId,
            ':modulo_id' => $moduloId,
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('aulas_calcular_progresso')) {
    function aulas_calcular_progresso(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                a.id AS aula_id,
                COALESCE(a.video_url, '') AS video_url,
                COALESCE(p.assistido, 0) AS assistido,
                COALESCE(p.video_posicao_segundos, 0) AS video_posicao_segundos,
                COALESCE(p.video_duracao_segundos, 0) AS video_duracao_segundos,
                COALESCE(p.video_percentual, 0) AS video_percentual
            FROM admin_curso_aulas a
            LEFT JOIN aulas_progresso p
              ON p.aula_id = a.id
             AND p.usuario_id = :usuario_id
            WHERE a.curso_id = :curso_id
              AND a.modulo_id = :modulo_id
            ORDER BY a.ordem ASC, a.id ASC
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':curso_id' => $cursoId,
            ':modulo_id' => $moduloId,
        ]);

        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($linhas);
        $assistidas = [];
        $percentuais = [];
        $somaFracoes = 0.0;

        foreach ($linhas as $linha) {
            $id = (int)($linha['aula_id'] ?? 0);
            $assistido = (int)($linha['assistido'] ?? 0) === 1;
            $temVideo = trim((string)($linha['video_url'] ?? '')) !== '';
            $posicao = max(0, (int)($linha['video_posicao_segundos'] ?? 0));
            $duracao = max(0, (int)($linha['video_duracao_segundos'] ?? 0));
            $percentualSalvo = max(0.0, min(100.0, (float)($linha['video_percentual'] ?? 0)));

            if ($assistido) {
                $percentualAula = 100.0;
                $assistidas[$id] = true;
            } elseif ($temVideo) {
                $percentualCalculado = $duracao > 0 ? ($posicao / $duracao) * 100 : 0.0;
                $percentualAula = max($percentualSalvo, $percentualCalculado);
                $percentualAula = max(0.0, min(99.99, $percentualAula));
            } else {
                $percentualAula = 0.0;
            }

            $percentuais[$id] = round($percentualAula, 2);
            $somaFracoes += $percentualAula / 100;
        }

        $percentualModulo = $total > 0 ? (int)round(($somaFracoes / $total) * 100) : 0;
        return [
            'total' => $total,
            'assistidas' => $assistidas,
            'percentuais' => $percentuais,
            'soma_fracoes' => $somaFracoes,
            'percentual' => max(0, min(100, $percentualModulo)),
        ];
    }
}

if (!function_exists('aulas_obter_avaliacao')) {
    function aulas_obter_avaliacao(PDO $pdo, int $usuarioId, int $cursoId, int $moduloId, int $aulaId): array
    {
        $minhaNota = 0;

        if ($usuarioId > 0) {
            $stmtMinha = $pdo->prepare("
                SELECT nota
                FROM aulas_avaliacoes
                WHERE usuario_id = :usuario_id
                  AND curso_id = :curso_id
                  AND modulo_id = :modulo_id
                  AND aula_id = :aula_id
                LIMIT 1
            ");
            $stmtMinha->execute([
                ':usuario_id' => $usuarioId,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
            ]);
            $minhaNota = (int)($stmtMinha->fetchColumn() ?: 0);
        }

        $stmtMedia = $pdo->prepare("
            SELECT COUNT(*) AS total, COALESCE(AVG(nota), 0) AS media
            FROM aulas_avaliacoes
            WHERE curso_id = :curso_id
              AND modulo_id = :modulo_id
              AND aula_id = :aula_id
        ");
        $stmtMedia->execute([
            ':curso_id' => $cursoId,
            ':modulo_id' => $moduloId,
            ':aula_id' => $aulaId,
        ]);
        $media = $stmtMedia->fetch() ?: ['total' => 0, 'media' => 0];

        return [
            'minha_nota' => max(0, min(5, $minhaNota)),
            'media' => round((float)($media['media'] ?? 0), 1),
            'total' => (int)($media['total'] ?? 0),
        ];
    }
}

if (!function_exists('aulas_formatar_data')) {
    function aulas_formatar_data(?string $data): string
    {
        if (!$data) {
            return '';
        }

        $timestamp = strtotime($data);
        if (!$timestamp) {
            return '';
        }

        return date('d/m/Y H:i', $timestamp);
    }
}

$cursoId = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?: 0;
$moduloId = filter_input(INPUT_GET, 'modulo_id', FILTER_VALIDATE_INT) ?: 0;
$aulaId = filter_input(INPUT_GET, 'aula_id', FILTER_VALIDATE_INT) ?: 0;

$acaoAulas = trim((string)($_POST['acao'] ?? ''));
if ($acaoAulas !== '') {
    try {
        if ($cursoId <= 0 || $moduloId <= 0 || $aulaId <= 0) {
            aulas_json(['ok' => false, 'mensagem' => 'Curso, módulo ou aula não informado.'], 400);
        }

        $pdoAjax = aulas_db();
        aulas_garantir_tabelas($pdoAjax);

        if (!aulas_validar_aula($pdoAjax, $cursoId, $moduloId, $aulaId)) {
            aulas_json(['ok' => false, 'mensagem' => 'Aula não encontrada para esse módulo.'], 404);
        }

        $usuarioIdAjax = aulas_usuario_id();
        if ($usuarioIdAjax <= 0) {
            aulas_json([
                'ok' => false,
                'login_required' => true,
                'login_url' => 'login.php',
                'mensagem' => 'Faça login para continuar.',
            ], 401);
        }
        if (function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdoAjax, $usuarioIdAjax, $cursoId)) {
            aulas_json(['ok' => false, 'mensagem' => 'Este curso não está liberado para o seu usuário.'], 403);
        }

        if ($acaoAulas === 'salvar_video_progresso') {
            $posicaoRecebida = filter_var($_POST['posicao'] ?? 0, FILTER_VALIDATE_FLOAT);
            $duracaoRecebida = filter_var($_POST['duracao'] ?? 0, FILTER_VALIDATE_FLOAT);
            $finalizado = filter_var($_POST['finalizado'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $posicao = max(0, (int)floor((float)($posicaoRecebida ?: 0)));
            $duracao = max(0, (int)round((float)($duracaoRecebida ?: 0)));

            $stmtVideoAula = $pdoAjax->prepare("
                SELECT video_url
                FROM admin_curso_aulas
                WHERE id = :aula_id AND curso_id = :curso_id AND modulo_id = :modulo_id
                LIMIT 1
            ");
            $stmtVideoAula->execute([
                ':aula_id' => $aulaId,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
            ]);
            $videoUrlAula = trim((string)($stmtVideoAula->fetchColumn() ?: ''));
            if ($videoUrlAula === '' || aulas_youtube_id($videoUrlAula) === '') {
                aulas_json(['ok' => false, 'mensagem' => 'Esta aula não possui vídeo do YouTube rastreável.'], 422);
            }

            $stmtAtual = $pdoAjax->prepare("
                SELECT assistido, video_posicao_segundos, video_duracao_segundos
                FROM aulas_progresso
                WHERE usuario_id = :usuario_id AND aula_id = :aula_id
                LIMIT 1
            ");
            $stmtAtual->execute([':usuario_id' => $usuarioIdAjax, ':aula_id' => $aulaId]);
            $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC) ?: [];

            $assistidoAnterior = (int)($atual['assistido'] ?? 0) === 1;
            $posicaoAnterior = max(0, (int)($atual['video_posicao_segundos'] ?? 0));
            $duracaoAnterior = max(0, (int)($atual['video_duracao_segundos'] ?? 0));
            $duracao = max($duracao, $duracaoAnterior);
            if ($duracao > 0) {
                $posicao = min($posicao, $duracao);
            }

            // Só avança a frente contínua do vídeo. Um salto grande para a frente não conta como conteúdo assistido.
            $toleranciaSegundos = 25;
            $novaPosicao = $posicaoAnterior;
            if ($assistidoAnterior) {
                $novaPosicao = $duracao > 0 ? $duracao : max($posicaoAnterior, $posicao);
            } elseif ($posicaoAnterior <= 0 && $posicao <= $toleranciaSegundos) {
                $novaPosicao = max($posicaoAnterior, $posicao);
            } elseif ($posicao <= ($posicaoAnterior + $toleranciaSegundos)) {
                $novaPosicao = max($posicaoAnterior, $posicao);
            }

            $limiteConclusao = $duracao > 0 ? max(1, $duracao - 3) : PHP_INT_MAX;
            $chegouAoFim = $duracao > 0
                && $novaPosicao >= $limiteConclusao
                && ($finalizado || $posicao >= $limiteConclusao);
            if ($chegouAoFim) {
                $novaPosicao = $duracao;
            }

            $novoAssistido = $assistidoAnterior || $chegouAoFim;
            $percentualVideo = $duracao > 0 ? min(100.0, ($novaPosicao / $duracao) * 100) : 0.0;
            if ($novoAssistido) {
                $percentualVideo = 100.0;
            }

            $stmtSalvar = $pdoAjax->prepare("
                INSERT INTO aulas_progresso (
                    usuario_id, curso_id, modulo_id, aula_id, assistido,
                    video_posicao_segundos, video_duracao_segundos, video_percentual,
                    video_iniciado_em, video_atualizado_em, assistido_em, criado_em
                ) VALUES (
                    :usuario_id, :curso_id, :modulo_id, :aula_id, :assistido,
                    :video_posicao, :video_duracao, :video_percentual,
                    NOW(), NOW(), :assistido_em, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    curso_id = VALUES(curso_id),
                    modulo_id = VALUES(modulo_id),
                    assistido = VALUES(assistido),
                    video_posicao_segundos = GREATEST(video_posicao_segundos, VALUES(video_posicao_segundos)),
                    video_duracao_segundos = GREATEST(video_duracao_segundos, VALUES(video_duracao_segundos)),
                    video_percentual = GREATEST(video_percentual, VALUES(video_percentual)),
                    video_iniciado_em = COALESCE(video_iniciado_em, NOW()),
                    video_atualizado_em = NOW(),
                    assistido_em = CASE
                        WHEN assistido_em IS NOT NULL THEN assistido_em
                        WHEN VALUES(assistido) = 1 THEN NOW()
                        ELSE NULL
                    END,
                    atualizado_em = NOW()
            ");
            $stmtSalvar->execute([
                ':usuario_id' => $usuarioIdAjax,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
                ':assistido' => $novoAssistido ? 1 : 0,
                ':video_posicao' => $novaPosicao,
                ':video_duracao' => $duracao,
                ':video_percentual' => number_format($percentualVideo, 2, '.', ''),
                ':assistido_em' => $novoAssistido ? date('Y-m-d H:i:s') : null,
            ]);

            $dadosProgresso = aulas_calcular_progresso($pdoAjax, $usuarioIdAjax, $cursoId, $moduloId);
            aulas_json([
                'ok' => true,
                'aula_id' => $aulaId,
                'posicao' => $novaPosicao,
                'duracao' => $duracao,
                'percentual_video' => round($percentualVideo, 2),
                'assistida' => $novoAssistido,
                'progresso' => $dadosProgresso['percentual'],
                'assistidas' => array_map('intval', array_keys($dadosProgresso['assistidas'])),
            ]);
        }

        if ($acaoAulas === 'marcar_assistido') {
            unset($_SESSION['gab_missao_' . (int)$usuarioIdAjax]);
            $stmtTipoAula = $pdoAjax->prepare("
                SELECT video_url
                FROM admin_curso_aulas
                WHERE id = :aula_id AND curso_id = :curso_id AND modulo_id = :modulo_id
                LIMIT 1
            ");
            $stmtTipoAula->execute([
                ':aula_id' => $aulaId,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
            ]);
            $videoUrlTipo = trim((string)($stmtTipoAula->fetchColumn() ?: ''));
            if ($videoUrlTipo !== '' && aulas_youtube_id($videoUrlTipo) !== '') {
                $stmtConcluida = $pdoAjax->prepare("
                    SELECT assistido
                    FROM aulas_progresso
                    WHERE usuario_id = :usuario_id AND aula_id = :aula_id
                    LIMIT 1
                ");
                $stmtConcluida->execute([':usuario_id' => $usuarioIdAjax, ':aula_id' => $aulaId]);
                if ((int)($stmtConcluida->fetchColumn() ?: 0) !== 1) {
                    aulas_json(['ok' => false, 'mensagem' => 'Assista ao vídeo do início ao fim para concluir esta aula.'], 422);
                }
            }

            $stmt = $pdoAjax->prepare("
                INSERT INTO aulas_progresso (usuario_id, curso_id, modulo_id, aula_id, assistido, assistido_em, criado_em)
                VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    assistido = 1,
                    assistido_em = IF(assistido_em IS NULL, NOW(), assistido_em),
                    atualizado_em = NOW()
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioIdAjax,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
            ]);

            $dadosProgresso = aulas_calcular_progresso($pdoAjax, $usuarioIdAjax, $cursoId, $moduloId);
            aulas_json([
                'ok' => true,
                'mensagem' => 'Aula marcada como assistida.',
                'aula_id' => $aulaId,
                'progresso' => $dadosProgresso['percentual'],
                'assistidas' => array_map('intval', array_keys($dadosProgresso['assistidas'])),
            ]);
        }

        if ($acaoAulas === 'salvar_pdf_progresso') {
            $pagina = filter_var($_POST['pagina'] ?? 1, FILTER_VALIDATE_INT);
            $totalPaginas = filter_var($_POST['total_paginas'] ?? 0, FILTER_VALIDATE_INT);
            $zoom = filter_var($_POST['zoom'] ?? 1, FILTER_VALIDATE_FLOAT);
            $pdfChaveRecebida = preg_replace('/[^a-f0-9]/i', '', (string)($_POST['pdf_chave'] ?? ''));

            $pagina = max(1, (int)($pagina ?: 1));
            $totalPaginas = max(0, (int)($totalPaginas ?: 0));
            $zoom = max(0.50, min(3.00, (float)($zoom ?: 1)));

            $stmtPdfAula = $pdoAjax->prepare("
                SELECT pdf_arquivo, pdf_nome, material_arquivo, material_nome
                FROM admin_curso_aulas
                WHERE id = :aula_id AND curso_id = :curso_id AND modulo_id = :modulo_id
                LIMIT 1
            ");
            $stmtPdfAula->execute([
                ':aula_id' => $aulaId,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
            ]);
            $pdfDaAula = aulas_pdf_do_item($stmtPdfAula->fetch() ?: []);
            $pdfChaveCorreta = $pdfDaAula['arquivo'] !== '' ? aulas_pdf_chave($pdfDaAula['arquivo']) : '';

            if ($pdfChaveCorreta === '' || !hash_equals($pdfChaveCorreta, $pdfChaveRecebida)) {
                aulas_json(['ok' => false, 'mensagem' => 'PDF inválido para esta aula.'], 422);
            }

            if ($totalPaginas > 0) {
                $pagina = min($pagina, $totalPaginas);
            }

            $stmt = $pdoAjax->prepare("
                INSERT INTO aulas_pdf_progresso
                    (usuario_id, curso_id, modulo_id, aula_id, pdf_chave, pagina, total_paginas, zoom, criado_em)
                VALUES
                    (:usuario_id, :curso_id, :modulo_id, :aula_id, :pdf_chave, :pagina, :total_paginas, :zoom, NOW())
                ON DUPLICATE KEY UPDATE
                    pagina = VALUES(pagina),
                    total_paginas = VALUES(total_paginas),
                    zoom = VALUES(zoom),
                    atualizado_em = NOW()
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioIdAjax,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
                ':pdf_chave' => $pdfChaveCorreta,
                ':pagina' => $pagina,
                ':total_paginas' => $totalPaginas,
                ':zoom' => number_format($zoom, 2, '.', ''),
            ]);

            aulas_json([
                'ok' => true,
                'pagina' => $pagina,
                'total_paginas' => $totalPaginas,
                'zoom' => $zoom,
            ]);
        }

        if ($acaoAulas === 'avaliar_aula') {
            $nota = filter_var($_POST['nota'] ?? 0, FILTER_VALIDATE_INT);
            $nota = $nota ? (int)$nota : 0;

            if ($nota < 1 || $nota > 5) {
                aulas_json(['ok' => false, 'mensagem' => 'Escolha uma avaliação de 1 a 5 estrelas.'], 422);
            }

            $stmt = $pdoAjax->prepare("
                INSERT INTO aulas_avaliacoes (usuario_id, curso_id, modulo_id, aula_id, nota, criado_em)
                VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, :nota, NOW())
                ON DUPLICATE KEY UPDATE
                    nota = VALUES(nota),
                    atualizado_em = NOW()
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioIdAjax,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
                ':nota' => $nota,
            ]);

            $avaliacao = aulas_obter_avaliacao($pdoAjax, $usuarioIdAjax, $cursoId, $moduloId, $aulaId);
            aulas_json([
                'ok' => true,
                'mensagem' => 'Avaliação salva com sucesso.',
                'nota' => $nota,
                'media' => $avaliacao['media'],
                'total' => $avaliacao['total'],
            ]);
        }

        if ($acaoAulas === 'criar_topico') {
            $titulo = aulas_limitar_texto((string)($_POST['titulo'] ?? ''), 180);
            $texto = aulas_limitar_texto((string)($_POST['texto'] ?? ''), 5000);
            $visibilidade = (string)($_POST['visibilidade'] ?? 'todos');
            if (!in_array($visibilidade, ['todos', 'monitores'], true)) {
                $visibilidade = 'todos';
            }

            if ($titulo === '' || $texto === '') {
                aulas_json(['ok' => false, 'mensagem' => 'Preencha o título e o texto do tópico.'], 422);
            }

            $stmt = $pdoAjax->prepare("
                INSERT INTO aulas_forum_topicos (usuario_id, curso_id, modulo_id, aula_id, titulo, texto, visibilidade, status, criado_em)
                VALUES (:usuario_id, :curso_id, :modulo_id, :aula_id, :titulo, :texto, :visibilidade, 'ativo', NOW())
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioIdAjax,
                ':curso_id' => $cursoId,
                ':modulo_id' => $moduloId,
                ':aula_id' => $aulaId,
                ':titulo' => $titulo,
                ':texto' => $texto,
                ':visibilidade' => $visibilidade,
            ]);

            $topicoId = (int)$pdoAjax->lastInsertId();
            $stmtTopico = $pdoAjax->prepare("
                SELECT t.*, COALESCE(u.nome, 'Aluno') AS usuario_nome
                FROM aulas_forum_topicos t
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                WHERE t.id = :id
                LIMIT 1
            ");
            $stmtTopico->execute([':id' => $topicoId]);
            $topico = $stmtTopico->fetch() ?: [];

            aulas_json([
                'ok' => true,
                'mensagem' => 'Tópico criado com sucesso.',
                'topico' => [
                    'id' => $topicoId,
                    'titulo' => (string)($topico['titulo'] ?? $titulo),
                    'texto' => (string)($topico['texto'] ?? $texto),
                    'visibilidade' => (string)($topico['visibilidade'] ?? $visibilidade),
                    'usuario_nome' => (string)($topico['usuario_nome'] ?? 'Aluno'),
                    'criado_em' => aulas_formatar_data((string)($topico['criado_em'] ?? date('Y-m-d H:i:s'))),
                ],
            ]);
        }

        aulas_json(['ok' => false, 'mensagem' => 'Ação não reconhecida.'], 400);
    } catch (Throwable $e) {
        aulas_json(['ok' => false, 'mensagem' => $e->getMessage()], 500);
    }
}

$curso = null;
$modulo = null;
$aulas = [];
$aulaAtual = null;
$aulaAtualIndex = 0;
$erroPagina = '';
$progresso = 0;
$usuarioId = aulas_usuario_id();
$aulasAssistidas = [];
$topicos = [];
$respostasPorTopico = [];
$avaliacaoAtual = ['minha_nota' => 0, 'media' => 0, 'total' => 0];
$pdfProgressoAtual = ['pagina' => 1, 'total_paginas' => 0, 'zoom' => 1.0];
$dadosProgresso = ['total' => 0, 'assistidas' => [], 'percentuais' => [], 'soma_fracoes' => 0.0, 'percentual' => 0];
$videoProgressoAtual = ['posicao' => 0, 'duracao' => 0, 'percentual' => 0.0, 'assistido' => false];

try {
    if ($cursoId <= 0 || $moduloId <= 0) {
        throw new RuntimeException('Curso ou módulo não informado. Volte para o dashboard e selecione um módulo.');
    }

    $pdo = aulas_db();
    aulas_garantir_tabelas($pdo);

    if (function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoId)) {
        throw new RuntimeException('Este curso não está liberado para o seu usuário.');
    }

    $stmtCurso = $pdo->prepare("
        SELECT c.*
        FROM admin_cursos c
        INNER JOIN admin_curso_modulos m ON m.curso_id = c.id
        WHERE c.id = :curso_id AND m.id = :modulo_id AND c.status = 'ativo'
        LIMIT 1
    ");
    $stmtCurso->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
    $curso = $stmtCurso->fetch() ?: null;

    if (!$curso) {
        throw new RuntimeException('Curso não encontrado ou indisponível.');
    }

    $stmtModulo = $pdo->prepare("
        SELECT *
        FROM admin_curso_modulos
        WHERE id = :modulo_id AND curso_id = :curso_id
        LIMIT 1
    ");
    $stmtModulo->execute([':modulo_id' => $moduloId, ':curso_id' => $cursoId]);
    $modulo = $stmtModulo->fetch() ?: null;

    if (!$modulo) {
        throw new RuntimeException('Módulo não encontrado para esse curso.');
    }

    $stmtAulas = $pdo->prepare("
        SELECT *
        FROM admin_curso_aulas
        WHERE curso_id = :curso_id AND modulo_id = :modulo_id
        ORDER BY ordem ASC, id ASC
    ");
    $stmtAulas->execute([':curso_id' => $cursoId, ':modulo_id' => $moduloId]);
    $aulas = $stmtAulas->fetchAll();

    if ($aulas) {
        foreach ($aulas as $index => $aula) {
            if ($aulaId > 0 && (int)$aula['id'] === $aulaId) {
                $aulaAtual = $aula;
                $aulaAtualIndex = $index;
                break;
            }
        }

        if (!$aulaAtual) {
            $aulaAtual = $aulas[0];
            $aulaAtualIndex = 0;
        }
    }


    if ($aulas && $usuarioId > 0) {
        $dadosProgresso = aulas_calcular_progresso($pdo, $usuarioId, $cursoId, $moduloId);
        $aulasAssistidas = $dadosProgresso['assistidas'];
        $progresso = $dadosProgresso['percentual'];
    }

    if ($aulaAtual) {
        if ($usuarioId > 0) {
            $stmtVideoProgresso = $pdo->prepare("
                SELECT assistido, video_posicao_segundos, video_duracao_segundos, video_percentual
                FROM aulas_progresso
                WHERE usuario_id = :usuario_id AND aula_id = :aula_id
                LIMIT 1
            ");
            $stmtVideoProgresso->execute([
                ':usuario_id' => $usuarioId,
                ':aula_id' => (int)$aulaAtual['id'],
            ]);
            $linhaVideoProgresso = $stmtVideoProgresso->fetch(PDO::FETCH_ASSOC) ?: [];
            $videoProgressoAtual = [
                'posicao' => max(0, (int)($linhaVideoProgresso['video_posicao_segundos'] ?? 0)),
                'duracao' => max(0, (int)($linhaVideoProgresso['video_duracao_segundos'] ?? 0)),
                'percentual' => max(0.0, min(100.0, (float)($linhaVideoProgresso['video_percentual'] ?? 0))),
                'assistido' => (int)($linhaVideoProgresso['assistido'] ?? 0) === 1,
            ];
        }

        $stmtTopicos = $pdo->prepare("
            SELECT t.*, COALESCE(u.nome, 'Aluno') AS usuario_nome
            FROM aulas_forum_topicos t
            LEFT JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.curso_id = :curso_id
              AND t.modulo_id = :modulo_id
              AND t.aula_id = :aula_id
              AND t.status = 'ativo'
            ORDER BY t.criado_em DESC, t.id DESC
            LIMIT 20
        ");
        $stmtTopicos->execute([
            ':curso_id' => $cursoId,
            ':modulo_id' => $moduloId,
            ':aula_id' => (int)$aulaAtual['id'],
        ]);
        $topicos = $stmtTopicos->fetchAll();

        if ($topicos) {
            $idsTopicos = array_values(array_filter(array_map(static fn($t): int => (int)($t['id'] ?? 0), $topicos)));
            if ($idsTopicos) {
                $placeholders = implode(',', array_fill(0, count($idsTopicos), '?'));
                $stmtRespostas = $pdo->prepare("
                    SELECT r.*
                    FROM aulas_forum_respostas r
                    WHERE r.status = 'ativo'
                      AND r.topico_id IN ($placeholders)
                    ORDER BY r.criado_em ASC, r.id ASC
                ");
                $stmtRespostas->execute($idsTopicos);
                foreach ($stmtRespostas->fetchAll(PDO::FETCH_ASSOC) as $respostaTopico) {
                    $tid = (int)($respostaTopico['topico_id'] ?? 0);
                    if ($tid > 0) {
                        $respostasPorTopico[$tid][] = $respostaTopico;
                    }
                }
            }
        }

        $avaliacaoAtual = aulas_obter_avaliacao($pdo, $usuarioId, $cursoId, $moduloId, (int)$aulaAtual['id']);

        $pdfDaAulaAtual = aulas_pdf_do_item($aulaAtual);
        if ($usuarioId > 0 && $pdfDaAulaAtual['arquivo'] !== '') {
            $stmtPdfProgresso = $pdo->prepare("
                SELECT pagina, total_paginas, zoom
                FROM aulas_pdf_progresso
                WHERE usuario_id = :usuario_id
                  AND aula_id = :aula_id
                  AND pdf_chave = :pdf_chave
                LIMIT 1
            ");
            $stmtPdfProgresso->execute([
                ':usuario_id' => $usuarioId,
                ':aula_id' => (int)$aulaAtual['id'],
                ':pdf_chave' => aulas_pdf_chave($pdfDaAulaAtual['arquivo']),
            ]);
            $progressoEncontrado = $stmtPdfProgresso->fetch();
            if ($progressoEncontrado) {
                $pdfProgressoAtual = [
                    'pagina' => max(1, (int)($progressoEncontrado['pagina'] ?? 1)),
                    'total_paginas' => max(0, (int)($progressoEncontrado['total_paginas'] ?? 0)),
                    'zoom' => max(0.50, min(3.00, (float)($progressoEncontrado['zoom'] ?? 1))),
                ];
            }
        }
    }
} catch (Throwable $e) {
    $erroPagina = $e->getMessage();
}

$cursoTitulo = (string)($curso['nome'] ?? 'Gabaritando');
$moduloTitulo = (string)($modulo['titulo'] ?? 'Módulo');
$totalAulas = count($aulas);
$aulaAtualAssistida = $aulaAtual ? isset($aulasAssistidas[(int)$aulaAtual['id']]) : false;
$minhaNotaAula = (int)($avaliacaoAtual['minha_nota'] ?? 0);
$mediaNotaAula = (float)($avaliacaoAtual['media'] ?? 0);
$totalAvaliacoesAula = (int)($avaliacaoAtual['total'] ?? 0);
$pdfAtual = $aulaAtual ? aulas_pdf_do_item($aulaAtual) : ['arquivo' => '', 'nome' => ''];
$videoDuracaoAtual = $aulaAtual ? trim((string)($aulaAtual['duracao'] ?? '')) : '';
$pdfDuracaoAtual = $aulaAtual ? trim((string)($aulaAtual['pdf_duracao'] ?? '')) : '';
$videoDuracaoSegundosManual = aulas_duracao_segundos($videoDuracaoAtual);
$pdfDuracaoSegundos = aulas_duracao_segundos($pdfDuracaoAtual);
$pdfChaveAtual = $pdfAtual['arquivo'] !== '' ? aulas_pdf_chave($pdfAtual['arquivo']) : '';
$videoUrlAtual = $aulaAtual ? trim((string)($aulaAtual['video_url'] ?? '')) : '';
$youtubeVideoId = $videoUrlAtual !== '' ? aulas_youtube_id($videoUrlAtual) : '';
$videoRastreavel = $youtubeVideoId !== '';
$videoAtual = $videoUrlAtual !== '' ? aulas_youtube_embed($videoUrlAtual) : '';
$videoDuracaoSegundosConhecida = $videoDuracaoSegundosManual > 0
    ? $videoDuracaoSegundosManual
    : max(0, (int)($videoProgressoAtual['duracao'] ?? 0));
$conteudoDuracaoSegundos = $videoDuracaoSegundosConhecida + $pdfDuracaoSegundos;
$conteudoDuracaoTexto = $conteudoDuracaoSegundos > 0
    ? aulas_formatar_duracao_resumo($conteudoDuracaoSegundos)
    : (($videoUrlAtual !== '' && $videoDuracaoSegundosConhecida <= 0) ? 'Carregando...' : 'Não informado');
$aulaTitulo = (string)($aulaAtual['titulo'] ?? 'Nenhuma aula cadastrada');
$aulaDescricao = trim((string)($aulaAtual['descricao'] ?? ''));
if ($aulaDescricao === '') {
    $aulaDescricao = '-';
}

$linkModulo = 'curso-modulos.php?curso_id=' . $cursoId;
$linkBaseAula = 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId;
$linkAnterior = '';
$linkProximo = '';

if ($aulas) {
    if (isset($aulas[$aulaAtualIndex - 1])) {
        $linkAnterior = $linkBaseAula . '&aula_id=' . (int)$aulas[$aulaAtualIndex - 1]['id'];
    }

    if (isset($aulas[$aulaAtualIndex + 1])) {
        $linkProximo = $linkBaseAula . '&aula_id=' . (int)$aulas[$aulaAtualIndex + 1]['id'];
    }
}

// Método META: trava de verdade. A aula de um assunto bloqueado não abre até o aluno
// cumprir o mínimo de questões do assunto anterior da mesma matéria (mentor/admin passam direto).
$travaMeta = null;
try {
    $ehAdminAula = strtolower((string)($_SESSION['usuario_tipo'] ?? '')) === 'admin' || !empty($_SESSION['admin_usuario_id']);
    if ($aulaAtual && !$ehAdminAula && $usuarioId > 0 && function_exists('db') && is_file(__DIR__ . '/includes/meta-lib.php')) {
        require_once __DIR__ . '/includes/meta-lib.php';
        $pdoTrava = db();
        meta_bootstrap($pdoTrava);
        $estadoTrava = meta_estado_aluno($pdoTrava, (int)$usuarioId, (int)$cursoId);
        $stTop = $pdoTrava->prepare('SELECT topico_id FROM admin_curso_aulas WHERE id = :id');
        $stTop->execute([':id' => (int)$aulaAtual['id']]);
        $topicoAula = (int)$stTop->fetchColumn();
        foreach ($estadoTrava['itens'] as $itT) {
            $mesma = (int)($itT['aula_id'] ?? 0) === (int)$aulaAtual['id'] || ($topicoAula > 0 && (int)($itT['topico_id'] ?? 0) === $topicoAula);
            if ($mesma && $itT['etapa'] === META_ETAPA_BLOQUEADO) {
                $falta = null;
                foreach ($estadoTrava['atuais'] as $at) {
                    if ((int)($at['materia_id'] ?? 0) === (int)($itT['materia_id'] ?? 0)) { $falta = $at; }
                }
                $travaMeta = ['assunto' => (string)$itT['titulo'], 'falta' => $falta, 'min' => (int)($estadoTrava['config']['min_questoes'] ?? 5)];
                break;
            }
        }
    }
} catch (Throwable $e) {
    $travaMeta = null;
}
if ($travaMeta) {
    $f = $travaMeta['falta'];
    $feitasT = $f ? (int)$f['nivel_info']['resolvidas'] : 0;
    $teoriaT = $f ? !empty($f['teoria_ok']) : false;
    $linkAulaT = ($f && !empty($f['aula_id'])) ? 'aulas.php?curso_id=' . (int)$f['aula_curso_id'] . '&modulo_id=' . (int)$f['aula_modulo_id'] . '&aula_id=' . (int)$f['aula_id'] : 'meu-plano.php';
    $linkQT = ($f && !empty($f['topico_id'])) ? 'banco-questoes.php?topico_id=' . (int)$f['topico_id'] : 'meu-plano.php';
    $eT = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Aula bloqueada | Projeto Gabaritando</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
<style>body{margin:0;background:#0c0c0d;color:#f3f3f4;font-family:'Ubuntu',system-ui,sans-serif}.tv{max-width:620px;margin:40px auto;padding:0 16px}.tv-card{background:#161618;border:1px solid #4a3a10;border-radius:18px;padding:24px;display:grid;gap:14px;text-align:center}.tv-card h1{font-size:24px;margin:0}.tv-card p{margin:0;color:#c9c9cf;line-height:1.5}.tv-card strong{color:#fbb928}.tv-bar{height:10px;border-radius:99px;background:#2a2a2e;overflow:hidden}.tv-bar i{display:block;height:100%;background:#fbb928}.tv-bt{display:inline-block;border-radius:12px;padding:12px 18px;background:#fbb928;color:#111;font-weight:700;text-decoration:none}.tv-bt.l{background:transparent;color:#f3f3f4;border:1px solid #2a2a2e}.tv-bt:focus-visible{outline:2px solid #fff;outline-offset:2px}</style></head><body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="tv"><section class="tv-card" aria-labelledby="tvT">
    <div style="font-size:44px" aria-hidden="true">🔒</div>
    <h1 id="tvT"><?php echo $eT($travaMeta['assunto']); ?></h1>
    <?php if ($f): ?>
        <p><strong>Antigão:</strong> calma, combatente! Esta aula abre quando você fechar o assunto anterior da matéria: <strong><?php echo $eT($f['titulo']); ?></strong>.</p>
        <p><?php echo $teoriaT ? '✅ Teoria concluída' : '📘 Falta a teoria'; ?> · ✅ <?php echo min($feitasT, $travaMeta['min']); ?> de <?php echo $travaMeta['min']; ?> questões</p>
        <div class="tv-bar" role="progressbar" aria-valuenow="<?php echo min($feitasT, $travaMeta['min']); ?>" aria-valuemin="0" aria-valuemax="<?php echo $travaMeta['min']; ?>"><i style="width:<?php echo (int)round(min($feitasT, $travaMeta['min']) * 100 / max(1, $travaMeta['min'])); ?>%"></i></div>
        <div><a class="tv-bt" href="<?php echo $eT($teoriaT ? $linkQT : $linkAulaT); ?>">▶ <?php echo $teoriaT ? 'Resolver as questões que faltam' : 'Ir para a aula de ' . $eT($f['titulo']); ?></a></div>
    <?php else: ?>
        <p>Esta aula abre quando você cumprir o assunto anterior da matéria (teoria + <?php echo $travaMeta['min']; ?> questões).</p>
        <div><a class="tv-bt" href="meu-plano.php">▶ Ver minha missão</a></div>
    <?php endif; ?>
    <p style="font-size:13px">No Método META, cada assunto só abre com a base do anterior. É isso que faz o conteúdo ficar na cabeça até a prova.</p>
    <div><a class="tv-bt l" href="<?php echo $eT($linkModulo); ?>">← Voltar às aulas</a></div>
</section></main></body></html><?php
    exit;
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
    <title><?php echo esc($aulaTitulo); ?> | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#c98f0e;
            --black:#050505;
            --page:#080808;
            --panel:#0d0d0d;
            --panel-2:#111111;
            --line:rgba(255,255,255,.10);
            --text:#ffffff;
            --muted:#b9b9b9;
            --shadow:0 16px 35px rgba(0,0,0,.35);
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html,
        body{
            width:100%;
            min-height:100%;
        }

        body{
            font-family:'Ubuntu',sans-serif;
            background:var(--page);
            color:var(--text);
        }

        a{
            text-decoration:none;
            color:inherit;
        }

        button{
            font-family:inherit;
        }

        img,
        iframe{
            display:block;
            max-width:100%;
        }

        .aulas-page{
            width:100%;
            min-height:calc(100vh - 96px);
            background:#080808;
            padding:18px 20px 32px;
        }

        .aulas-layout{
            width:100%;
            max-width:1360px;
            margin:0 auto;
            display:grid;
            grid-template-columns:minmax(0, 1fr) 330px;
            gap:28px;
            align-items:start;
        }

        .aulas-main{
            min-width:0;
        }

        .breadcrumb{
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:8px;
            color:#fff;
            font-size:.98rem;
            font-weight:700;
            line-height:1.3;
            margin:0 0 18px;
            text-transform:uppercase;
        }

        .breadcrumb__sep{
            color:var(--yellow);
            font-weight:700;
        }

        .player-card{
            width:100%;
            background:#000;
            box-shadow:var(--shadow);
        }

        .video-frame{
            position:relative;
            width:100%;
            aspect-ratio:16 / 9;
            background:#000;
            overflow:hidden;
        }

        .video-frame iframe{
            width:100%;
            height:100%;
            border:0;
            background:#000;
        }

        .g-player{
            position:relative;
            width:100%;
            height:100%;
            overflow:hidden;
            background:#000;
            color:#fff;
            outline:none;
            user-select:none;
            -webkit-user-select:none;
            touch-action:manipulation;
        }

        .g-player__stage,
        .g-player__youtube,
        .g-player__poster,
        .g-player__shade,
        .g-player__loading,
        .g-player__ended{
            position:absolute;
            inset:0;
        }

        .g-player__stage{
            z-index:1;
            overflow:hidden;
            background:#000;
        }

        .g-player__youtube{
            /* Mesmo método usado pelo player de referência: o iframe ocupa
               o dobro da altura e fica centralizado verticalmente. Assim o
               próprio YouTube cria faixas fora da área visível e somente o
               quadro real do vídeo permanece dentro do player. */
            inset:0;
            width:100%;
            height:100%;
            overflow:hidden;
            pointer-events:none;
            background:#000;
        }

        .g-player__youtube > iframe,
        .g-player__youtube iframe{
            position:absolute !important;
            left:0 !important;
            top:-50% !important;
            width:100% !important;
            height:200% !important;
            max-width:none !important;
            border:0 !important;
            pointer-events:none !important;
        }

        .g-player__poster{
            z-index:3;
            background-position:center;
            background-repeat:no-repeat;
            background-size:cover;
            transition:opacity .22s ease, visibility .22s ease;
        }

        .g-player.is-started .g-player__poster{
            opacity:0;
            visibility:hidden;
        }

        .g-player__shade,
        .g-player__brand-cover,
        .g-player__native-cover{
            display:none !important;
        }

        .g-player__center{
            position:absolute;
            z-index:8;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%);
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .g-player__big-play{
            width:78px;
            height:58px;
            border:0;
            border-radius:14px;
            background:var(--yellow);
            color:#050505;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            box-shadow:0 12px 30px rgba(0,0,0,.38);
            transition:transform .18s ease, opacity .18s ease, background .18s ease;
        }

        .g-player__big-play:hover{
            transform:scale(1.05);
            background:#ffc94b;
        }

        .g-player__big-play i,
        .g-player__big-play svg{
            width:34px;
            height:34px;
            fill:currentColor;
        }

        .g-player.is-playing .g-player__big-play,
        .g-player.is-buffering .g-player__big-play,
        .g-player.is-ended .g-player__big-play{
            opacity:0;
            pointer-events:none;
        }

        .g-player.is-playing.is-center-feedback .g-player__big-play{
            opacity:1;
            pointer-events:none;
        }

        .g-player__loading{
            z-index:9;
            display:flex;
            align-items:center;
            justify-content:center;
            opacity:0;
            visibility:hidden;
            pointer-events:none;
            transition:opacity .15s ease;
        }

        .g-player.is-buffering .g-player__loading{
            opacity:1;
            visibility:visible;
        }

        .g-player__spinner{
            width:46px;
            height:46px;
            border-radius:50%;
            border:4px solid rgba(255,255,255,.28);
            border-top-color:var(--yellow);
            animation:gPlayerSpin .8s linear infinite;
        }

        @keyframes gPlayerSpin{
            to{ transform:rotate(360deg); }
        }

        .g-player__ended{
            z-index:12;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:14px;
            background:#050505;
            opacity:0;
            visibility:hidden;
            pointer-events:none;
            transition:opacity .18s ease, visibility .18s ease;
        }

        .g-player.is-ended .g-player__ended{
            opacity:1;
            visibility:visible;
            pointer-events:auto;
        }

        .g-player__ended strong{
            font-size:1.08rem;
        }

        .g-player__replay{
            height:46px;
            padding:0 20px;
            border:0;
            border-radius:10px;
            background:var(--yellow);
            color:#050505;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            font-weight:700;
            cursor:pointer;
        }

        .g-player__replay i,
        .g-player__replay svg{
            width:20px;
            height:20px;
        }

        .g-player__controls{
            position:absolute;
            z-index:15;
            left:0;
            right:0;
            bottom:0;
            padding:22px 14px 14px;
            opacity:1;
            transform:translateY(0);
            transition:opacity .2s ease, transform .2s ease;
        }

        .g-player__controls::before{
            content:"";
            position:absolute;
            left:0;
            right:0;
            top:-44px;
            bottom:0;
            z-index:0;
            pointer-events:none;
            background:linear-gradient(to top, rgba(0,0,0,.96) 0%, rgba(0,0,0,.82) 48%, rgba(0,0,0,.46) 72%, rgba(0,0,0,0) 100%);
            box-shadow:0 -24px 46px rgba(0,0,0,.82);
        }

        .g-player.is-playing.is-controls-hidden .g-player__controls{
            opacity:0;
            transform:translateY(12px);
            pointer-events:none;
        }

        .g-player__timeline-wrap{
            position:relative;
            z-index:1;
            width:100%;
            height:18px;
            display:flex;
            align-items:center;
            cursor:pointer;
        }

        .g-player__range{
            --range-progress:0%;
            appearance:none;
            -webkit-appearance:none;
            width:100%;
            height:4px;
            border:0;
            border-radius:999px;
            outline:none;
            background:linear-gradient(to right, var(--yellow) 0 var(--range-progress), rgba(255,255,255,.34) var(--range-progress) 100%);
            cursor:pointer;
        }

        .g-player__range::-webkit-slider-thumb{
            appearance:none;
            -webkit-appearance:none;
            width:14px;
            height:14px;
            border-radius:50%;
            border:0;
            background:var(--yellow);
            box-shadow:0 0 0 3px rgba(250,181,40,.18);
        }

        .g-player__range::-moz-range-thumb{
            width:14px;
            height:14px;
            border-radius:50%;
            border:0;
            background:var(--yellow);
            box-shadow:0 0 0 3px rgba(250,181,40,.18);
        }

        .g-player__bar{
            position:relative;
            z-index:1;
            min-height:40px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
        }

        .g-player__group{
            display:flex;
            align-items:center;
            gap:5px;
            min-width:0;
        }

        .g-player__button{
            width:36px;
            height:36px;
            border:0;
            border-radius:8px;
            background:transparent;
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            transition:background .16s ease, color .16s ease;
            flex:0 0 auto;
        }

        .g-player__button:hover,
        .g-player__button:focus-visible{
            background:rgba(255,255,255,.13);
            color:var(--yellow);
            outline:none;
        }

        .g-player__button i,
        .g-player__button svg{
            width:22px;
            height:22px;
        }

        .g-player__time{
            font-size:.92rem;
            font-weight:500;
            color:#fff;
            white-space:nowrap;
            text-shadow:0 1px 3px rgba(0,0,0,.9);
            padding:0 4px;
        }

        .g-player__speed{
            width:auto;
            min-width:44px;
            padding:0 7px;
            font-weight:700;
            font-size:.88rem;
        }

        .g-player__volume{
            width:82px;
            height:4px;
        }

        .g-player__tap-layer{
            position:absolute;
            z-index:7;
            inset:0;
            cursor:pointer;
        }

        .g-player__controls,
        .g-player__center,
        .g-player__ended{
            cursor:default;
        }

        .g-player:not(.is-ready) .g-player__controls{
            pointer-events:none;
            opacity:.45;
        }

        @media(max-width:740px){
            .g-player__controls{
                padding:18px 8px 9px;
            }

            .g-player__controls::before{
                top:-34px;
            }

            .g-player__bar{
                gap:4px;
            }

            .g-player__button{
                width:34px;
                height:34px;
            }

            .g-player__skip,
            .g-player__volume-wrap{
                display:none;
            }

            .g-player__time{
                font-size:.78rem;
                padding:0 2px;
            }

            .g-player__big-play{
                width:66px;
                height:50px;
            }

            .g-player__brand-cover{
                width:105px;
                height:58px;
            }
        }

        .aula-duration-summary{
            display:flex;
            align-items:stretch;
            flex-wrap:wrap;
            gap:10px;
            margin:12px 0 0;
        }

        .aula-duration-item{
            flex:1 1 190px;
            min-width:0;
            min-height:46px;
            border:1px solid rgba(250,181,40,.26);
            border-radius:12px;
            background:rgba(250,181,40,.075);
            display:flex;
            align-items:center;
            gap:10px;
            padding:9px 13px;
            color:#fff;
        }

        .aula-duration-item__icon{
            width:31px;
            height:31px;
            border-radius:9px;
            background:var(--yellow);
            color:#090909;
            display:flex;
            align-items:center;
            justify-content:center;
            flex:0 0 31px;
        }

        .aula-duration-item__icon i,
        .aula-duration-item__icon svg{
            width:17px;
            height:17px;
        }

        .aula-duration-item__text{
            display:flex;
            flex-direction:column;
            gap:1px;
            line-height:1.15;
        }

        .aula-duration-item__text span{
            color:rgba(255,255,255,.63);
            font-size:.72rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.035em;
        }

        .aula-duration-item__text strong{
            color:#fff;
            font-size:.9rem;
            font-weight:800;
        }

        .aula-actions{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            flex-wrap:wrap;
            gap:8px 10px;
            padding:22px 0 0;
        }

        .rating-box{
            display:inline-flex;
            align-items:center;
            gap:8px;
            margin-right:8px;
            min-height:31px;
        }

        .stars{
            display:inline-flex;
            align-items:center;
            gap:2px;
            color:var(--yellow);
            font-size:1.33rem;
            letter-spacing:0;
            line-height:1;
        }

        .star-btn{
            width:20px;
            height:24px;
            border:0;
            background:transparent;
            color:var(--yellow);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:0;
            font-size:1.32rem;
            line-height:1;
            cursor:pointer;
            transition:transform .15s ease, filter .15s ease;
        }

        .star-btn:hover,
        .star-btn:focus-visible{
            transform:translateY(-1px) scale(1.08);
            outline:none;
            filter:brightness(1.08);
        }

        .star-btn:disabled{
            cursor:not-allowed;
            opacity:.55;
            transform:none;
        }

        .rating-status{
            color:rgba(255,255,255,.72);
            font-size:.78rem;
            font-weight:700;
            white-space:nowrap;
        }

        .rating-box.is-saving .rating-status{
            color:var(--yellow);
        }

        .action-btn{
            min-height:31px;
            border:0;
            border-radius:999px;
            background:var(--yellow);
            color:#fff;
            padding:0 17px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-size:.95rem;
            font-weight:500;
            cursor:pointer;
            transition:filter .18s ease, transform .18s ease;
        }

        .action-btn:hover{
            filter:brightness(.96);
            transform:translateY(-1px);
        }

        .action-btn svg,
        .action-btn i{
            width:18px;
            height:18px;
        }

        .aulas-sidebar{
            min-width:0;
            padding-top:30px;
        }

        .course-head{
            display:grid;
            grid-template-columns:92px 1fr auto;
            gap:18px;
            align-items:center;
            margin-bottom:22px;
        }

        .progress-ring{
            width:90px;
            height:90px;
            border-radius:999px;
            border:9px solid #252525;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-size:1rem;
            font-weight:700;
            background:#0d0d0d;
        }

        .course-info{
            min-width:0;
        }

        .course-info__title{
            color:#fff;
            font-size:1.35rem;
            font-weight:700;
            margin:0 0 8px;
        }

        .course-info__line{
            color:#fff;
            font-size:.98rem;
            font-weight:700;
            margin:0 0 9px;
        }

        .course-info__muted{
            color:#fff;
            font-size:.95rem;
            font-weight:700;
            margin:0;
        }

        .sidebar-search{
            width:34px;
            height:34px;
            border:0;
            background:transparent;
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
        }

        .sidebar-search svg,
        .sidebar-search i{
            width:25px;
            height:25px;
        }

        .lesson-search-wrap{
            display:none;
            margin:0 0 18px;
        }

        .lesson-search-wrap.is-open{
            display:block;
        }

        .lesson-search-box{
            width:100%;
            min-height:42px;
            border:1px solid rgba(255,255,255,.10);
            border-radius:999px;
            background:#111;
            display:flex;
            align-items:center;
            gap:10px;
            padding:0 13px;
            color:#fff;
            box-shadow:0 10px 22px rgba(0,0,0,.20);
        }

        .lesson-search-box i,
        .lesson-search-box svg{
            width:18px;
            height:18px;
            color:var(--yellow);
            flex:0 0 auto;
        }

        .lesson-search-input{
            min-width:0;
            width:100%;
            height:40px;
            border:0;
            outline:0;
            background:transparent;
            color:#fff;
            font-family:'Ubuntu',sans-serif;
            font-size:.92rem;
            font-weight:700;
        }

        .lesson-search-input::placeholder{
            color:rgba(255,255,255,.50);
            font-weight:700;
        }

        .lesson-search-clear{
            width:26px;
            height:26px;
            border:0;
            border-radius:999px;
            background:rgba(255,255,255,.08);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:1.15rem;
            line-height:1;
            cursor:pointer;
            flex:0 0 26px;
        }

        .lesson-search-empty{
            display:none;
            margin:10px 0 0;
            padding:10px 12px;
            border-radius:10px;
            background:#141414;
            color:rgba(255,255,255,.70);
            font-size:.86rem;
            font-weight:700;
            text-align:center;
        }

        .lesson-search-empty.is-visible{
            display:block;
        }

        .lesson-item.is-filter-hidden{
            display:none !important;
        }

        .lesson-list{
            position:relative;
            padding:0 0 0 43px;
        }

        .lesson-list::before{
            content:"";
            position:absolute;
            left:55px;
            top:18px;
            bottom:0;
            width:1px;
            background:#262626;
        }

        .lesson-item{
            position:relative;
            min-height:74px;
            display:flex;
            align-items:flex-start;
            padding:0 0 24px 34px;
            color:#fff;
            font-size:.98rem;
            font-weight:700;
            line-height:1.15;
            text-transform:uppercase;
        }

        .lesson-item:last-child{
            padding-bottom:0;
        }

        .lesson-dot{
            position:absolute;
            left:-4px;
            top:0;
            width:32px;
            height:32px;
            border-radius:999px;
            background:#1f1f1f;
            border:4px solid #232323;
            z-index:1;
        }

        .lesson-item.is-active{
            color:var(--yellow);
        }

        .lesson-item.is-active .lesson-dot{
            width:12px;
            height:12px;
            left:6px;
            top:9px;
            background:#080808;
            border:2px solid var(--yellow);
        }

        .aula-extra{
            border-top:1px solid rgba(255,255,255,.06);
            margin-top:28px;
            padding:25px 15px 0;
        }


        .content-section{
            max-width:860px;
            color:#fff;
            margin-top:8px;
        }

        .content-section h2{
            font-size:1.08rem;
            line-height:1.25;
            margin:0 0 13px;
            font-weight:700;
        }

        .content-section p{
            color:#fff;
            font-size:1rem;
            line-height:1.45;
            margin:0 0 22px;
        }

        .forum-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            flex-wrap:wrap;
            margin-top:10px;
        }

        .forum-head h2{
            margin:0;
        }


        .topics-list{
            width:100%;
            display:grid;
            gap:12px;
            margin-top:18px;
        }

        .topic-card,
        .topics-empty{
            width:100%;
            border:1px solid rgba(255,255,255,.08);
            border-radius:12px;
            background:#111;
            padding:14px 16px;
            color:#fff;
        }

        .topic-card__head{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            margin-bottom:9px;
        }

        .topic-card__title{
            font-size:1rem;
            line-height:1.25;
            font-weight:700;
            color:#fff;
        }

        .topic-card__meta,
        .topics-empty{
            color:rgba(255,255,255,.64);
            font-size:.86rem;
            line-height:1.35;
        }

        .topic-card__text{
            color:#ededed;
            font-size:.94rem;
            line-height:1.4;
            margin:0 0 11px;
            white-space:pre-line;
        }

        .topic-card__badge{
            display:inline-flex;
            align-items:center;
            min-height:24px;
            border-radius:999px;
            background:rgba(250,181,40,.14);
            color:var(--yellow);
            padding:0 10px;
            font-size:.78rem;
            font-weight:700;
        }


        .topic-replies{
            display:grid;
            gap:8px;
            margin:12px 0 10px;
        }

        .topic-reply{
            border:1px solid rgba(250,181,40,.18);
            border-radius:10px;
            background:rgba(250,181,40,.08);
            padding:10px 12px;
        }

        .topic-reply__meta{
            display:flex;
            align-items:center;
            gap:7px;
            color:var(--yellow);
            font-size:.82rem;
            font-weight:700;
            margin-bottom:5px;
        }

        .topic-reply__meta svg,
        .topic-reply__meta i{
            width:15px;
            height:15px;
        }

        .topic-reply__text{
            color:#fff;
            font-size:.92rem;
            line-height:1.4;
        }

        .progress-ring{
            position:relative;
            border:0;
            background:conic-gradient(var(--yellow) calc(var(--progress, 0) * 1%), #252525 0);
            overflow:hidden;
        }

        .progress-ring::before{
            content:"";
            position:absolute;
            inset:9px;
            border-radius:999px;
            background:#0d0d0d;
            z-index:0;
        }

        .progress-ring span{
            position:relative;
            z-index:1;
        }

        .lesson-item.is-watched .lesson-dot,
        .lesson-item.is-active.is-watched .lesson-dot{
            width:32px;
            height:32px;
            left:-4px;
            top:0;
            transform:none;
            background:#16a765;
            border:4px solid #16a765;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .lesson-item.is-watched .lesson-check{
            width:15px;
            height:15px;
            color:#fff;
            stroke-width:4;
        }

        .action-btn.is-watched{
            background:#16a765;
        }

        .action-btn:disabled{
            opacity:.55;
            cursor:not-allowed;
            transform:none;
        }

        .topic-btn{
            min-height:30px;
            border:0;
            border-radius:4px;
            background:var(--yellow);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            padding:0 12px;
            font-size:.98rem;
            font-weight:500;
            cursor:pointer;
        }

        .topic-btn svg,
        .topic-btn i{
            width:18px;
            height:18px;
        }

        .topic-modal{
            position:fixed;
            inset:0;
            z-index:9999;
            display:none;
            align-items:center;
            justify-content:center;
            padding:22px;
            background:rgba(0,0,0,.62);
        }

        .topic-modal.is-open{
            display:flex;
        }

        .topic-modal__dialog{
            width:min(790px, 100%);
            max-height:calc(100vh - 34px);
            overflow:auto;
            background:#f7f7f7;
            color:#111;
            box-shadow:0 22px 60px rgba(0,0,0,.45);
        }

        .topic-modal__head{
            position:relative;
            min-height:80px;
            padding:18px 68px 18px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:var(--yellow);
            color:#fff;
        }

        .topic-modal__head h3{
            margin:0;
            font-size:1.85rem;
            line-height:1.15;
            font-weight:700;
            text-align:center;
        }

        .topic-modal__close{
            position:absolute;
            right:54px;
            top:50%;
            transform:translateY(-50%);
            width:34px;
            height:34px;
            border:0;
            background:transparent;
            color:#fff;
            font-size:2rem;
            line-height:1;
            cursor:pointer;
        }

        .topic-modal__body{
            padding:40px 78px 32px;
        }

        .topic-input{
            width:100%;
            height:52px;
            border:1px solid #bfbfbf;
            border-radius:9px;
            background:#fff;
            color:#111;
            font-size:1rem;
            padding:0 16px;
            outline:none;
            margin-bottom:20px;
        }

        .topic-input::placeholder{
            color:#999;
        }

        .topic-editor{
            width:100%;
            min-height:240px;
            border:1px solid #cfcfcf;
            background:#fff;
            margin-bottom:18px;
        }

        .topic-editor__toolbar{
            min-height:40px;
            display:flex;
            align-items:center;
            gap:12px;
            padding:0 16px;
            border-bottom:1px solid #d8d8d8;
            color:#444;
            font-size:.94rem;
            font-weight:700;
        }

        .topic-editor__toolbar span{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:12px;
        }

        .topic-editor__text{
            width:100%;
            min-height:198px;
            border:0;
            resize:vertical;
            outline:none;
            background:#fff;
            color:#111;
            padding:15px 16px;
            font-size:.95rem;
            line-height:1.45;
            font-style:italic;
        }

        .topic-editor__text::placeholder{
            color:#444;
        }

        .topic-options{
            display:grid;
            gap:18px;
            margin:0 0 24px;
        }

        .topic-option{
            display:flex;
            align-items:center;
            gap:12px;
            color:#000;
            font-size:1.22rem;
            cursor:pointer;
        }

        .topic-option input{
            position:absolute;
            opacity:0;
            pointer-events:none;
        }

        .topic-option__dot{
            width:25px;
            height:25px;
            border-radius:999px;
            background:#c9c9c9;
            flex:0 0 25px;
        }

        .topic-option input:checked + .topic-option__dot{
            background:var(--yellow);
        }

        .topic-modal__submit{
            width:200px;
            height:44px;
            border:0;
            border-radius:4px;
            background:var(--yellow);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto;
            font-size:.95rem;
            font-weight:700;
            cursor:pointer;
        }

        body.topic-modal-open{
            overflow:hidden;
        }

        @media(max-width:1100px){
            .aulas-layout{
                grid-template-columns:1fr;
                gap:24px;
            }

            .aulas-sidebar{
                padding-top:0;
                background:#0d0d0d;
                border:1px solid rgba(255,255,255,.08);
                border-radius:14px;
                padding:18px;
            }

            .course-head{
                grid-template-columns:78px 1fr auto;
                margin-bottom:18px;
            }

            .progress-ring{
                width:76px;
                height:76px;
                border-width:8px;
            }

            .lesson-list{
                padding-left:0;
                display:grid;
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap:10px;
            }

            .lesson-list::before{
                display:none;
            }

            .lesson-item{
                min-height:auto;
                padding:12px 12px 12px 42px;
                background:#141414;
                border:1px solid rgba(255,255,255,.08);
                border-radius:10px;
                font-size:.92rem;
            }

            .lesson-dot,
            .lesson-item.is-active .lesson-dot{
                top:50%;
                transform:translateY(-50%);
            }

            .lesson-dot{
                left:13px;
            }

            .lesson-item.is-active .lesson-dot{
                left:23px;
            }

            .lesson-item.is-watched .lesson-dot,
            .lesson-item.is-active.is-watched .lesson-dot{
                width:25px;
                height:25px;
                left:13px;
                top:50%;
                transform:translateY(-50%);
                border-width:3px;
            }

            .lesson-item.is-watched .lesson-check{
                width:13px;
                height:13px;
            }

            .aula-actions{
                justify-content:center;
            }
        }

        @media(max-width:740px){
            .aulas-page{
                min-height:calc(100vh - 80px);
                padding:14px 12px 28px;
            }

            .breadcrumb{
                font-size:.78rem;
                gap:6px;
                margin-bottom:12px;
            }

            .video-frame{
                aspect-ratio:16 / 9;
                border-radius:0;
            }

            .aula-actions{
                justify-content:center;
                padding-top:16px;
                gap:9px;
            }

            .rating-box{
                width:100%;
                justify-content:center;
                margin-right:0;
                flex-wrap:wrap;
            }

            .stars{
                justify-content:center;
                font-size:1.18rem;
            }

            .rating-status{
                width:100%;
                text-align:center;
            }

            .action-btn{
                min-height:36px;
                padding:0 14px;
                font-size:.88rem;
            }

            .action-btn--wide{
                width:100%;
            }

            .aulas-sidebar{
                padding:14px;
                border-radius:12px;
            }

            .course-head{
                grid-template-columns:64px 1fr 30px;
                gap:12px;
                align-items:center;
            }

            .progress-ring{
                width:62px;
                height:62px;
                border-width:7px;
                font-size:.84rem;
            }


            .progress-ring::before{
                inset:7px;
            }

            .course-info__title{
                font-size:1.1rem;
                margin-bottom:5px;
            }

            .course-info__line,
            .course-info__muted{
                font-size:.82rem;
                margin-bottom:5px;
            }

            .sidebar-search{
                width:30px;
                height:30px;
            }

            .sidebar-search svg,
            .sidebar-search i{
                width:21px;
                height:21px;
            }

            .lesson-list{
                grid-template-columns:1fr;
                gap:8px;
            }

            .lesson-item{
                min-height:44px;
                padding:12px 11px 12px 40px;
                align-items:center;
                overflow:hidden;
                font-size:.84rem;
                line-height:1.22;
                border-radius:9px;
            }

            .lesson-item > span:last-child{
                display:block;
                width:100%;
                min-width:0;
            }

            .lesson-dot{
                width:15px;
                height:15px;
                left:13px;
                top:50%;
                border-width:2px;
                transform:translateY(-50%);
            }

            .lesson-item.is-active .lesson-dot{
                width:12px;
                height:12px;
                left:15px;
                top:50%;
                transform:translateY(-50%);
            }

            .lesson-item.is-watched .lesson-dot,
            .lesson-item.is-active.is-watched .lesson-dot{
                width:22px;
                height:22px;
                left:10px;
                top:50%;
                border-width:2px;
                transform:translateY(-50%);
            }

            .lesson-item.is-watched .lesson-check{
                width:12px;
                height:12px;
            }

            .aula-extra{
                margin-top:20px;
                padding:18px 0 0;
            }

            .content-section{
                margin-top:4px;
            }

            .content-section h2{
                font-size:1rem;
                margin-bottom:10px;
            }

            .content-section p{
                font-size:.95rem;
                margin-bottom:20px;
            }

            .forum-head{
                align-items:stretch;
                gap:12px;
            }

            .topic-btn{
                width:100%;
                min-height:36px;
            }

            .topic-modal{
                padding:12px;
                align-items:flex-start;
            }

            .topic-modal__dialog{
                max-height:calc(100vh - 24px);
            }

            .topic-modal__head{
                min-height:64px;
                padding:14px 52px;
            }

            .topic-modal__head h3{
                font-size:1.38rem;
            }

            .topic-modal__close{
                right:18px;
                font-size:1.8rem;
            }

            .topic-modal__body{
                padding:24px 18px 24px;
            }

            .topic-editor{
                min-height:210px;
            }

            .topic-editor__toolbar{
                gap:9px;
                padding:0 12px;
                flex-wrap:wrap;
            }

            .topic-editor__text{
                min-height:165px;
            }

            .topic-option{
                font-size:1rem;
            }

            .topic-modal__submit{
                width:100%;
            }
        }

        @media(max-width:430px){
            .aulas-page{
                padding-left:10px;
                padding-right:10px;
            }

            .breadcrumb{
                font-size:.72rem;
            }

            .action-btn{
                width:100%;
            }

            .action-btn--nav{
                width:calc(50% - 5px);
            }
        }


        .video-empty{
            width:100%;
            height:100%;
            min-height:420px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            background:#050505;
            border:1px solid rgba(255,255,255,.08);
            color:#fff;
            padding:28px;
        }

        .video-empty div{
            max-width:520px;
        }

        .video-empty i{
            width:44px;
            height:44px;
            color:var(--yellow);
            margin:0 auto 14px;
        }

        .video-empty h2{
            font-size:1.35rem;
            margin:0 0 8px;
        }

        .video-empty p{
            color:var(--muted);
            line-height:1.45;
        }

        .pdf-box{
            margin-top:18px;
            display:inline-flex;
            align-items:center;
            gap:10px;
            min-height:42px;
            border-radius:8px;
            padding:10px 14px;
            background:var(--yellow);
            color:#fff;
            font-size:.95rem;
            font-weight:700;
        }

        .pdf-box i{
            width:19px;
            height:19px;
        }


        .aula-pdf-card{
            margin:18px 0 4px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            width:100%;
            max-width:720px;
            border:1px solid rgba(250,181,40,.34);
            border-radius:14px;
            background:rgba(250,181,40,.08);
            padding:16px;
        }

        .aula-pdf-info{
            display:flex;
            align-items:center;
            gap:13px;
            min-width:0;
        }

        .aula-pdf-icon{
            width:44px;
            height:44px;
            border-radius:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex:0 0 44px;
            background:var(--yellow);
            color:#111;
        }

        .aula-pdf-icon i,
        .aula-pdf-icon svg{
            width:22px;
            height:22px;
        }

        .aula-pdf-text{
            min-width:0;
        }

        .aula-pdf-label{
            display:block;
            color:rgba(255,255,255,.68);
            font-size:.78rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.04em;
            margin-bottom:4px;
        }

        .aula-pdf-name{
            display:block;
            color:#fff;
            font-size:1rem;
            font-weight:800;
            line-height:1.25;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .aula-pdf-download{
            min-height:40px;
            border:0;
            border-radius:999px;
            background:var(--yellow);
            color:#111;
            padding:0 16px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-family:inherit;
            font-size:.92rem;
            font-weight:800;
            flex:0 0 auto;
            cursor:pointer;
            transition:filter .18s ease, transform .18s ease, opacity .18s ease;
        }

        .aula-pdf-download:disabled,
        .aula-pdf-download.is-loading{
            opacity:.7;
            cursor:wait;
            transform:none;
        }

        .aula-pdf-download:hover{
            filter:brightness(.97);
            transform:translateY(-1px);
        }

        .aula-pdf-download i,
        .aula-pdf-download svg{
            width:18px;
            height:18px;
        }

        .aula-pdf-actions{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:9px;
            flex:0 0 auto;
        }

        .aula-pdf-view{
            min-height:40px;
            border:1px solid rgba(250,181,40,.52);
            border-radius:999px;
            background:transparent;
            color:var(--yellow);
            padding:0 15px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-family:inherit;
            font-size:.92rem;
            font-weight:800;
            cursor:pointer;
            transition:background .18s ease, color .18s ease, transform .18s ease;
        }

        .aula-pdf-view:hover{
            background:rgba(250,181,40,.13);
            color:#ffc84f;
            transform:translateY(-1px);
        }

        .aula-pdf-view i,
        .aula-pdf-view svg{
            width:18px;
            height:18px;
        }

        body.pdf-viewer-open{
            overflow:hidden;
        }

        .aula-somente-pdf{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:24px;background:radial-gradient(120% 120% at 0% 0%,#2a2108 0%,#111 55%,#0b0b0b 100%);color:#f3f3f4;text-align:left}
        .aula-somente-pdf__conteudo{max-width:560px;display:grid;gap:12px}
        .aula-somente-pdf__selo{display:inline-flex;align-items:center;gap:6px;justify-self:start;padding:6px 12px;border-radius:999px;background:rgba(251,185,40,.14);color:#fbb928;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
        .aula-somente-pdf__selo svg,.aula-somente-pdf__tempo svg,.aula-somente-pdf__botao svg{width:16px;height:16px}
        .aula-somente-pdf h2{font-size:clamp(20px,3vw,28px);line-height:1.2}
        .aula-somente-pdf p{color:#c9c9cf;font-size:15px}
        .aula-somente-pdf__tempo{display:flex;align-items:center;gap:6px;font-size:14px}
        .aula-somente-pdf__botao{justify-self:start;display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:12px 18px;background:#fbb928;color:#111;font:inherit;font-weight:700;cursor:pointer}
        .aula-somente-pdf__botao:focus-visible{outline:2px solid #fff;outline-offset:3px}
        @media(max-width:760px){.video-frame:has(.aula-somente-pdf){aspect-ratio:auto!important;overflow:visible}.aula-somente-pdf{position:relative;inset:auto;padding:24px 20px}}
.ciclo{margin:18px 0;padding:18px;border-radius:16px;background:linear-gradient(135deg,rgba(251,185,40,.10),rgba(255,255,255,.02));border:1px solid rgba(251,185,40,.35);color:#f3f3f4}
        .ciclo-topo h2{font-size:18px;margin:0 0 6px}
        .ciclo-topo p{margin:0;font-size:14px;color:#c9c9cf}
        .ciclo-topo strong{color:#fbb928}
        .ciclo-passos{list-style:none;margin:16px 0 0;padding:0;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .ciclo-passos li{display:flex;gap:10px;padding:12px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);opacity:.75}
        .ciclo-passos li.agora{opacity:1;border-color:#fbb928;background:rgba(251,185,40,.10);box-shadow:0 0 0 1px #fbb928 inset}
        .ciclo-passos li.feito{opacity:1}
        .ciclo-passos li > div{display:grid;gap:5px;min-width:0}
        .ciclo-passos strong{font-size:15px}
        .ciclo-passos small{font-size:12.5px;color:#c9c9cf;line-height:1.4}
        .ciclo-passos em{font-style:normal;font-size:12.5px;font-weight:700;color:#fbb928}
        .ciclo-passos li.feito em{color:#4ade80}
        .ciclo-n{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;background:rgba(255,255,255,.12)}
        .ciclo-passos li.agora .ciclo-n{background:#fbb928;color:#111}
        .ciclo-passos li.feito .ciclo-n{background:#22c55e;color:#fff}
        .ciclo-barra{position:relative;display:block;height:8px;border-radius:99px;background:rgba(255,255,255,.12)}
        .ciclo-barra i{display:block;height:100%;border-radius:99px;background:#fbb928}
        .ciclo-barra b{position:absolute;top:-3px;width:2px;height:14px;background:#fff}
        .ciclo-bt{justify-self:start;margin-top:2px;border-radius:10px;padding:8px 12px;background:#fbb928;color:#111;font-weight:700;font-size:13px;text-decoration:none}
        .ciclo-prox{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:12px 0;padding:14px 16px;border-radius:14px;background:#0f2a1a;border:1px solid #22c55e;color:#e8fbe9;font-size:14.5px}
        .ciclo-prox strong{color:#4ade80}
        .ciclo-mentoria{margin:12px 0 0;font-size:13px;color:#c9c9cf}
        @media(max-width:1000px){.ciclo-passos{grid-template-columns:1fr 1fr}}
        @media(max-width:560px){.ciclo-passos{grid-template-columns:1fr}}
        .ad-flutua{position:fixed;z-index:60;display:inline-flex;align-items:center;gap:6px;border:0;border-radius:999px;padding:9px 14px;background:#fbb928;color:#111;font:inherit;font-size:14px;font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,.45);cursor:pointer}
        .ad-flutua[hidden]{display:none}
        .pdf-marca-area{position:relative;display:inline-block;line-height:0}
        .pdf-marca-camada{position:absolute;inset:0;pointer-events:none}
        .pdf-marca-camada.ativa{pointer-events:auto;cursor:crosshair;touch-action:none}
        .pdf-marca{position:absolute;background:rgba(251,213,40,.42);mix-blend-mode:multiply;border-radius:2px}
        .pdf-marca-camada.ativa .pdf-marca{cursor:pointer;outline:1px dashed rgba(180,120,0,.8)}
        #pdfMarcarBtn[aria-pressed="true"]{background:#fbb928;color:#111}
        .pdf-marca-dica{position:absolute;left:50%;top:10px;transform:translateX(-50%);z-index:5;background:#111;color:#fbb928;border:1px solid #fbb928;border-radius:999px;padding:6px 12px;font-size:13px;line-height:1.3;white-space:nowrap}
        .pdf-marca-dica[hidden]{display:none}
        .aula-somente-pdf__acoes{display:flex;flex-wrap:wrap;gap:10px}
        .aula-somente-pdf__botao--sec{background:transparent;color:#fbb928;border:1px solid rgba(251,185,40,.6)}
        .va-gerando{display:grid;gap:6px;font-size:13px}.va-gerando[hidden]{display:none}
        .va-gerando__barra{display:block;height:8px;border-radius:99px;background:rgba(255,255,255,.12);overflow:hidden}
        .va-gerando__barra i{display:block;height:100%;width:5%;background:#fbb928;transition:width .4s}
        .aula-somente-pdf[hidden],.va[hidden],.va-parada[hidden]{display:none!important}
        .va{position:absolute;inset:0;z-index:3;display:grid;grid-template-rows:1fr auto;background:radial-gradient(120% 120% at 0% 0%,#2a2108 0%,#111 55%,#0b0b0b 100%);color:#f5f5f6;overflow:hidden}
        .va:fullscreen{border-radius:0}
        .va-logo{position:absolute;top:14px;right:16px;height:42px;width:auto;z-index:2;opacity:.95;pointer-events:none}
        .va-palco{position:relative;display:flex;align-items:center;padding:28px clamp(18px,5vw,56px);min-height:0}
        .va-slide{display:grid;gap:14px;width:100%;max-width:860px;animation:vaEntra .5s ease}
        .va-slide__emoji{font-size:clamp(28px,4vw,44px);line-height:1}
        .va-slide h3{font-size:clamp(20px,3.2vw,34px);line-height:1.15;color:#fff;margin:0;padding-right:70px}
        .va-slide ul{margin:0;padding:0;list-style:none;display:grid;gap:8px}
        .va-slide li{font-size:clamp(14px,1.9vw,20px);line-height:1.35;padding-left:22px;position:relative;opacity:0;animation:vaEntra .45s ease forwards}
        .va-slide li::before{content:"";position:absolute;left:0;top:.5em;width:10px;height:10px;border-radius:3px;background:#fbb928}
        .va-slide__dest{border-left:4px solid #fbb928;background:rgba(251,185,40,.12);border-radius:8px;padding:10px 14px;font-size:clamp(13px,1.7vw,17px);opacity:0;animation:vaEntra .45s ease forwards}
        .va-abertura{display:grid;justify-items:center;text-align:center;gap:14px;width:100%}
        .va-abertura img{height:clamp(70px,14vw,130px);width:auto}
        .va-abertura h3{padding:0}
        .va-abertura small{color:#fbb928;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        @keyframes vaEntra{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        .va-barra{display:flex;align-items:center;gap:6px;padding:8px 10px;background:rgba(0,0,0,.55)}
        .va-bt{border:0;background:transparent;color:#fff;font:inherit;font-size:16px;min-width:36px;min-height:36px;border-radius:8px;cursor:pointer}
        .va-bt:hover{background:rgba(255,255,255,.12)}.va-bt:focus-visible{outline:2px solid #fbb928}
        .va-vel{font-size:13px;font-weight:700}
        .va-prog{flex:1;display:flex;gap:4px;min-width:60px}
        .va-prog span{flex:1;height:6px;border-radius:99px;background:rgba(255,255,255,.18);overflow:hidden}
        .va-prog span i{display:block;height:100%;width:0;background:#fbb928}
        .va-prog span.feito i{width:100%}
        .va-cont{font-size:12px;color:#c9c9cf;white-space:nowrap}
        .va-parada{position:absolute;inset:0;z-index:4;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(10,10,10,.92);overflow:auto}
        .va-parada__box{width:100%;max-width:640px;display:grid;gap:10px}
        .va-parada__selo{color:#fbb928;font-weight:700;font-size:12px;letter-spacing:.08em;text-transform:uppercase}
        .va-parada__box p{margin:0;font-size:clamp(15px,2vw,19px);color:#fff}
        .va-alt{text-align:left;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.04);color:#fff;border-radius:10px;padding:10px 12px;font:inherit;font-size:15px;cursor:pointer}
        .va-alt:hover:not(:disabled){border-color:#fbb928}
        .va-alt.certa{border-color:#22c55e;background:rgba(34,197,94,.16)}
        .va-alt.errada{border-color:#ef4444;background:rgba(239,68,68,.16)}
        .va-exp{border-radius:10px;padding:10px 12px;background:rgba(251,185,40,.12);font-size:14px;color:#f3f3f4}
        .va-continuar{justify-self:start;border:0;border-radius:10px;padding:10px 16px;background:#fbb928;color:#111;font:inherit;font-weight:700;cursor:pointer}
        @media(max-width:760px){.video-frame:has(.va:not([hidden])){aspect-ratio:auto!important}.va{position:relative;min-height:440px}.va-logo{height:30px;top:10px;right:10px}.va-barra{flex-wrap:wrap}.va-prog{order:9;flex-basis:100%}}
        <?php echo indicacoes_css(); ?>
        .pod [hidden],.pod[hidden]{display:none!important}
        .pod{display:grid;gap:10px;padding:14px;border-radius:14px;background:linear-gradient(135deg,rgba(251,185,40,.16),rgba(251,185,40,.04));border:1px solid rgba(251,185,40,.35)}
        .pod-btn{justify-self:start;display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:12px 16px;background:#fbb928;color:#111;font:inherit;font-weight:700;cursor:pointer}
        .pod-btn:disabled{opacity:.6;cursor:wait}
        .pod-btn:focus-visible,.pod-vel button:focus-visible{outline:2px solid #fff;outline-offset:2px}
        .pod-progresso{display:grid;gap:6px;font-size:13px}
        .pod-barra{height:8px;border-radius:99px;background:rgba(255,255,255,.12);overflow:hidden}
        .pod-barra i{display:block;height:100%;width:5%;background:#fbb928;transition:width .4s}
        .pod-player{display:grid;gap:8px}
        .pod-player audio{width:100%}
        .pod-vel{display:flex;gap:6px}
        .pod-vel button{border:1px solid rgba(255,255,255,.2);background:transparent;color:inherit;border-radius:8px;padding:5px 10px;font:inherit;font-size:13px;cursor:pointer}
        .pod-vel button[aria-pressed="true"]{background:#fbb928;color:#111;border-color:#fbb928}
        /* Material da aula (descrição interativa) */
        .ad{display:grid;gap:12px;margin-bottom:28px;--ad-fs:16px}
        .ad-barra{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px}
        .ad-barra h2{margin:0}
        .ad-ferramentas{display:flex;flex-wrap:wrap;gap:6px}
        .ad-btn{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:inherit;border-radius:9px;padding:7px 11px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
        .ad-btn:hover{background:rgba(255,255,255,.1)}
        .ad-btn:focus-visible{outline:2px solid #fbb928;outline-offset:2px}
        .ad-marca-ico{width:14px;height:14px;border-radius:3px;background:#fde047}
        .ad-dica{font-size:13px;opacity:.7;margin:0}
        .ad-texto{font-size:var(--ad-fs);line-height:1.65;max-width:72ch}
        .ad-texto p{margin:0 0 12px}
        .ad-texto ul,.ad-texto ol{margin:0 0 14px;padding-left:22px;display:grid;gap:6px}
        .ad-texto h2,.ad-texto h3{margin:18px 0 8px;line-height:1.25}
        .ad-texto h4{margin:16px 0 6px;color:#fbb928;font-size:.95em;text-transform:uppercase;letter-spacing:.04em}
        .ad-texto strong{color:#fff}
        .ad-texto table{width:100%;border-collapse:collapse;margin:8px 0 16px;font-size:.92em;display:block;overflow-x:auto}
        .ad-texto th,.ad-texto td{border:1px solid rgba(255,255,255,.14);padding:8px 10px;text-align:left;vertical-align:top}
        .ad-texto th{background:rgba(251,185,40,.12)}
        .ad-intro{padding:14px 16px;border-radius:12px;background:rgba(251,185,40,.08);border:1px solid rgba(251,185,40,.25);margin-bottom:14px}
        .ad-abrir{margin-left:auto;font-size:12px;font-weight:500;opacity:.65;white-space:nowrap}
        .ad-secao[open] .ad-abrir{display:none}
        .ad-grupo{margin:18px 0 10px;font-size:1.05em;color:#fbb928}
        .ad-secao{border:1px solid rgba(255,255,255,.1);border-radius:12px;margin-bottom:10px;overflow:hidden}
        .ad-secao>summary{cursor:pointer;list-style:none;padding:12px 16px;font-weight:700;background:rgba(255,255,255,.04);display:flex;justify-content:space-between;align-items:center;gap:10px}
        .ad-secao>summary::-webkit-details-marker{display:none}
        .ad-secao>summary::after{content:"▾";transition:transform .2s}
        .ad-secao:not([open])>summary::after{transform:rotate(-90deg)}
        .ad-corpo{padding:12px 16px 4px}
        .ad-bizus{border-color:rgba(251,185,40,.5)}
        .ad-bizus>summary{background:rgba(251,185,40,.14);color:#fbb928}
        .ad-bizu{border-left:4px solid #fbb928;background:rgba(251,185,40,.07);padding:10px 12px;border-radius:0 10px 10px 0;margin-bottom:10px}
        .ad-texto mark.ad-m{background:#fde047;color:#111;border-radius:3px;padding:0 1px;cursor:pointer}
        .ad-status{font-size:12px;opacity:.7;min-height:1em}
        @media (prefers-reduced-motion:reduce){.ad-secao>summary::after{transition:none}}
        .pdf-viewer-modal{
            position:fixed;
            inset:0;
            z-index:500;
            display:flex;
            opacity:0;
            visibility:hidden;
            pointer-events:none;
            background:#0a0a0a;
            transition:opacity .18s ease, visibility .18s ease;
        }

        .pdf-viewer-modal.is-open{
            opacity:1;
            visibility:visible;
            pointer-events:auto;
        }

        .pdf-viewer-shell{
            width:100%;
            height:100dvh;
            display:flex;
            flex-direction:column;
            background:#161616;
        }

        .pdf-viewer-head{
            min-height:62px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            padding:9px 14px;
            background:var(--yellow);
            color:#111;
            box-shadow:0 3px 12px rgba(0,0,0,.28);
            z-index:2;
        }

        .pdf-viewer-title{
            min-width:0;
            display:flex;
            align-items:center;
            gap:10px;
            font-weight:800;
        }

        .pdf-viewer-title i,
        .pdf-viewer-title svg{
            width:21px;
            height:21px;
            flex:0 0 auto;
        }

        .pdf-viewer-title span{
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .pdf-viewer-toolbar{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            flex-wrap:wrap;
            gap:7px;
            flex:0 0 auto;
        }

        .pdf-tool-btn,
        .pdf-tool-link{
            width:38px;
            height:38px;
            border:0;
            border-radius:9px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:rgba(0,0,0,.12);
            color:#111;
            cursor:pointer;
            transition:background .16s ease, transform .16s ease;
        }

        .pdf-tool-btn:hover,
        .pdf-tool-link:hover{
            background:rgba(0,0,0,.21);
            transform:translateY(-1px);
        }

        .pdf-tool-btn:disabled{
            opacity:.38;
            cursor:not-allowed;
            transform:none;
        }

        .pdf-tool-link:disabled,
        .pdf-tool-link.is-loading{
            opacity:.55;
            cursor:wait;
            transform:none;
        }

        .pdf-tool-btn i,
        .pdf-tool-btn svg,
        .pdf-tool-link i,
        .pdf-tool-link svg{
            width:19px;
            height:19px;
        }

        .pdf-page-control{
            height:38px;
            border-radius:9px;
            background:rgba(255,255,255,.48);
            display:flex;
            align-items:center;
            gap:5px;
            padding:0 9px;
            font-size:.87rem;
            font-weight:800;
            white-space:nowrap;
        }

        .pdf-page-control input{
            width:48px;
            height:29px;
            border:1px solid rgba(0,0,0,.18);
            border-radius:6px;
            background:#fff;
            color:#111;
            text-align:center;
            font:inherit;
            outline:none;
        }

        .pdf-page-control input:focus{
            border-color:#111;
            box-shadow:0 0 0 2px rgba(0,0,0,.12);
        }

        .pdf-zoom-label{
            min-width:50px;
            text-align:center;
            font-size:.84rem;
            font-weight:800;
        }

        .pdf-viewer-body{
            position:relative;
            flex:1 1 auto;
            min-height:0;
            overflow:auto;
            padding:26px 18px 36px;
            background:#2b2b2b;
            overscroll-behavior:contain;
        }

        .pdf-canvas-wrap{
            width:max-content;
            min-width:100%;
            min-height:100%;
            display:flex;
            align-items:flex-start;
            justify-content:center;
        }

        #pdfViewerCanvas{
            display:block;
            background:#fff;
            box-shadow:0 10px 28px rgba(0,0,0,.45);
        }

        .pdf-viewer-status{
            position:absolute;
            inset:0;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
            background:rgba(43,43,43,.94);
            color:#fff;
            text-align:center;
            z-index:1;
        }

        .pdf-viewer-status[hidden]{
            display:none;
        }

        .pdf-viewer-status__box{
            max-width:460px;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:12px;
            line-height:1.45;
        }

        .pdf-viewer-spinner{
            width:38px;
            height:38px;
            border:4px solid rgba(255,255,255,.2);
            border-top-color:var(--yellow);
            border-radius:50%;
            animation:pdf-spin .8s linear infinite;
        }

        @keyframes pdf-spin{
            to{ transform:rotate(360deg); }
        }

        .pdf-viewer-close{
            flex:0 0 38px;
        }

        @media(max-width:760px){
            .aula-pdf-card{
                align-items:flex-start;
                flex-direction:column;
                padding:13px;
            }

            .aula-pdf-info{
                width:100%;
            }

            .aula-pdf-actions{
                width:100%;
            }

            .aula-pdf-view,
            .aula-pdf-download{
                flex:1 1 0;
                min-width:0;
                padding:0 11px;
            }

            .pdf-viewer-head{
                min-height:auto;
                display:grid;
                grid-template-columns:minmax(0, 1fr) 36px;
                grid-template-areas:
                    "title close"
                    "toolbar toolbar";
                align-items:center;
                gap:8px;
                padding:8px;
                position:relative;
            }

            .pdf-viewer-title{
                grid-area:title;
                width:100%;
                min-width:0;
                min-height:36px;
                gap:8px;
            }

            .pdf-viewer-title i,
            .pdf-viewer-title svg{
                width:19px;
                height:19px;
            }

            .pdf-viewer-title span{
                font-size:.9rem;
            }

            .pdf-viewer-close{
                grid-area:close;
                width:36px;
                height:36px;
                flex:0 0 36px;
                border:1px solid rgba(0,0,0,.25);
                background:rgba(0,0,0,.10);
            }

            .pdf-viewer-toolbar{
                grid-area:toolbar;
                width:100%;
                min-width:0;
                display:grid;
                grid-template-columns:36px minmax(86px, 1fr) 36px 36px 36px 36px;
                align-items:center;
                justify-content:stretch;
                gap:6px;
                overflow:visible;
                padding:0;
            }

            .pdf-viewer-body{
                padding:10px 4px 22px;
                overflow:auto;
                -webkit-overflow-scrolling:touch;
            }

            .pdf-canvas-wrap{
                min-width:100%;
                padding:0;
            }

            .pdf-tool-btn,
            .pdf-tool-link{
                flex:0 0 36px;
                width:36px;
                height:36px;
                border-radius:8px;
            }

            .pdf-page-control{
                width:100%;
                min-width:0;
                height:36px;
                justify-content:center;
                padding:0 6px;
                gap:4px;
            }

            .pdf-page-control input{
                width:42px;
                height:28px;
            }

            .pdf-zoom-label{
                display:none;
            }

            #pdfViewerCanvas{
                max-width:none;
                box-shadow:0 6px 18px rgba(0,0,0,.38);
            }
        }

        @media(max-width:390px){
            .pdf-viewer-head{
                grid-template-columns:minmax(0, 1fr) 34px;
                gap:6px;
                padding:7px 6px;
            }

            .pdf-viewer-toolbar{
                grid-template-columns:34px minmax(78px, 1fr) 34px 34px 34px 34px;
                gap:4px;
            }

            .pdf-tool-btn,
            .pdf-tool-link,
            .pdf-viewer-close{
                width:34px;
                height:34px;
                flex-basis:34px;
            }

            .pdf-page-control{
                height:34px;
                padding:0 4px;
                font-size:.8rem;
            }

            .pdf-page-control input{
                width:38px;
                height:26px;
            }
        }

        .aula-pdf-empty{
            margin:16px 0 4px;
            max-width:720px;
            border:1px dashed rgba(255,255,255,.14);
            border-radius:14px;
            padding:14px 16px;
            display:flex;
            align-items:center;
            gap:10px;
            color:rgba(255,255,255,.65);
            font-size:.92rem;
            font-weight:700;
        }

        .aula-pdf-empty i,
        .aula-pdf-empty svg{
            width:18px;
            height:18px;
            color:var(--yellow);
        }

        .lesson-item.is-disabled{
            opacity:.72;
            pointer-events:none;
        }

        .lesson-meta{
            display:block;
            margin-top:7px;
            color:rgba(255,255,255,.62);
            font-size:.78rem;
            font-weight:700;
            text-transform:none;
        }

        .lesson-item.is-active .lesson-meta{
            color:rgba(250,181,40,.8);
        }

        .aula-actions .action-btn.is-disabled{
            opacity:.5;
            pointer-events:none;
        }

        @media(max-width:740px){
            .video-empty{
                min-height:240px;
            }
        }

    </style>
</head>
<body>
    <?php if (is_file($barraSuperiorInclude)) { include_once $barraSuperiorInclude; } ?>

    <main class="aulas-page">
        <div class="aulas-layout">
            <section class="aulas-main" aria-label="Aula atual">
                <nav class="breadcrumb" aria-label="Caminho da aula">
                    <a href="dashboard.php">Cursos</a>
                    <span class="breadcrumb__sep">›</span>
                    <a href="<?php echo esc($linkModulo); ?>"><?php echo esc($cursoTitulo); ?></a>
                    <span class="breadcrumb__sep">›</span>
                    <a href="<?php echo esc($linkBaseAula); ?>"><?php echo esc($moduloTitulo); ?></a>
                    <?php if ($aulaAtual): ?>
                        <span class="breadcrumb__sep">›</span>
                        <span><?php echo esc($aulaTitulo); ?></span>
                    <?php endif; ?>
                </nav>

                <div class="player-card">
                    <div class="video-frame">
                        <?php if ($erroPagina !== ''): ?>
                            <div class="video-empty">
                                <div>
                                    <i data-lucide="alert-circle" aria-hidden="true"></i>
                                    <h2>Não foi possível carregar a aula</h2>
                                    <p><?php echo esc($erroPagina); ?></p>
                                </div>
                            </div>
                        <?php elseif (!$aulaAtual): ?>
                            <div class="video-empty">
                                <div>
                                    <i data-lucide="play-square" aria-hidden="true"></i>
                                    <h2>Nenhum conteúdo cadastrado</h2>
                                    <p>Esse módulo ainda não tem videoaulas cadastradas no painel administrativo.</p>
                                </div>
                            </div>
                        <?php elseif ($youtubeVideoId !== ''): ?>
                            <div
                                class="g-player"
                                id="gabaritandoVideoPlayer"
                                data-video-id="<?php echo esc($youtubeVideoId); ?>"
                                tabindex="0"
                                role="region"
                                aria-label="Player da aula <?php echo esc($aulaTitulo); ?>">
                                <div class="g-player__stage" aria-hidden="true">
                                    <div class="g-player__youtube">
                                        <div id="gabaritandoYoutubePlayer"></div>
                                    </div>
                                </div>

                                <div
                                    class="g-player__poster"
                                    style="background-image:url('https://i.ytimg.com/vi/<?php echo rawurlencode($youtubeVideoId); ?>/maxresdefault.jpg'),url('https://i.ytimg.com/vi/<?php echo rawurlencode($youtubeVideoId); ?>/hqdefault.jpg')"
                                    aria-hidden="true"></div>
                                <div class="g-player__tap-layer" id="gPlayerTapLayer" aria-hidden="true"></div>

                                <div class="g-player__center">
                                    <button type="button" class="g-player__big-play" id="gPlayerBigPlay" aria-label="Reproduzir aula">
                                        <i data-lucide="play"></i>
                                    </button>
                                </div>

                                <div class="g-player__loading" aria-hidden="true">
                                    <span class="g-player__spinner"></span>
                                </div>

                                <div class="g-player__ended" id="gPlayerEnded" aria-hidden="true">
                                    <strong>Aula finalizada</strong>
                                    <button type="button" class="g-player__replay" id="gPlayerReplay">
                                        <i data-lucide="rotate-ccw"></i>
                                        <span>Assistir novamente</span>
                                    </button>
                                </div>

                                <div class="g-player__controls" id="gPlayerControls">
                                    <div class="g-player__timeline-wrap">
                                        <input
                                            type="range"
                                            class="g-player__range g-player__timeline"
                                            id="gPlayerTimeline"
                                            min="0"
                                            max="100"
                                            step="0.1"
                                            value="0"
                                            aria-label="Progresso do vídeo">
                                    </div>

                                    <div class="g-player__bar">
                                        <div class="g-player__group">
                                            <button type="button" class="g-player__button" id="gPlayerPlay" aria-label="Reproduzir">
                                                <i data-lucide="play"></i>
                                            </button>
                                            <button type="button" class="g-player__button g-player__skip" id="gPlayerBack" aria-label="Voltar 10 segundos">
                                                <i data-lucide="rotate-ccw"></i>
                                            </button>
                                            <button type="button" class="g-player__button g-player__skip" id="gPlayerForward" aria-label="Avançar 10 segundos">
                                                <i data-lucide="rotate-cw"></i>
                                            </button>
                                            <span class="g-player__time"><span id="gPlayerCurrent">00:00</span> / <span id="gPlayerDuration">00:00</span></span>
                                        </div>

                                        <div class="g-player__group">
                                            <button type="button" class="g-player__button g-player__speed" id="gPlayerSpeed" aria-label="Alterar velocidade">1x</button>
                                            <div class="g-player__group g-player__volume-wrap">
                                                <button type="button" class="g-player__button" id="gPlayerMute" aria-label="Desativar som">
                                                    <i data-lucide="volume-2"></i>
                                                </button>
                                                <input
                                                    type="range"
                                                    class="g-player__range g-player__volume"
                                                    id="gPlayerVolume"
                                                    min="0"
                                                    max="100"
                                                    step="1"
                                                    value="100"
                                                    aria-label="Volume">
                                            </div>
                                            <button type="button" class="g-player__button" id="gPlayerFullscreen" aria-label="Tela cheia">
                                                <i data-lucide="maximize"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($videoAtual !== ''): ?>
                            <iframe
                                src="<?php echo esc($videoAtual); ?>"
                                title="<?php echo esc($aulaTitulo); ?>"
                                allow="autoplay; encrypted-media; picture-in-picture"
                                referrerpolicy="strict-origin-when-cross-origin"
                                allowfullscreen>
                            </iframe>
                        <?php elseif ($pdfAtual['arquivo'] !== '' || ($aulaAtual && $aulaDescricao !== '-')): $soTexto = $pdfAtual['arquivo'] === ''; ?>
                            <?php // Aula sem vídeo (só PDF, ou aula do Antigão só com a teoria): videoaula do Antigão + leitura. ?>
                            <div class="aula-somente-pdf">
                                <div class="aula-somente-pdf__conteudo">
                                    <span class="aula-somente-pdf__selo"><i data-lucide="book-open-text" aria-hidden="true"></i><?php echo $soTexto ? 'Aula do Antigão' : 'Teoria mínima em PDF'; ?></span>
                                    <h2><?php echo esc($aulaTitulo); ?></h2>
                                    <p>Esta aula é de leitura. Leia o material com atenção, faça suas marcações e depois siga para as questões.</p>
                                    <?php if (!empty($aulaAtual['pdf_duracao'])): ?><p class="aula-somente-pdf__tempo"><i data-lucide="clock-3" aria-hidden="true"></i>Tempo estimado de leitura: <?php echo esc((string)$aulaAtual['pdf_duracao']); ?></p><?php endif; ?>
                                    <div class="aula-somente-pdf__acoes">
                                        <button type="button" class="aula-somente-pdf__botao" id="vaAbrir"><span aria-hidden="true">▶</span>Assistir à videoaula do Antigão</button>
                                        <button type="button" class="aula-somente-pdf__botao aula-somente-pdf__botao--sec" onclick="var b=document.getElementById('abrirPdfAula'); if(b){b.click();}else{var t=document.getElementById('aulaDescricao'); if(t){t.scrollIntoView({behavior:'smooth'});}}">
                                            <i data-lucide="file-text" aria-hidden="true"></i><?php echo $soTexto ? 'Ler a teoria' : 'Ler o material'; ?>
                                        </button>
                                    </div>
                                    <p class="va-gerando" id="vaGerando" hidden role="status" aria-live="polite"><span class="va-gerando__barra"><i id="vaGerandoBarra"></i></span><span id="vaGerandoMsg">Preparando a videoaula…</span></p>
                                </div>
                            </div>
                            <div class="va" id="videoaulaIA" data-aula-id="<?php echo (int)($aulaAtual['id'] ?? 0); ?>" hidden>
                                <img class="va-logo" src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="Projeto Gabaritando">
                                <div class="va-palco" id="vaPalco" aria-live="polite"></div>
                                <div class="va-parada" id="vaParada" hidden role="dialog" aria-modal="false" aria-labelledby="vaParadaPerg"></div>
                                <div class="va-barra">
                                    <button type="button" class="va-bt" id="vaPlay" aria-label="Reproduzir">▶</button>
                                    <button type="button" class="va-bt" id="vaAnt" aria-label="Slide anterior">⏮</button>
                                    <button type="button" class="va-bt" id="vaProx" aria-label="Próximo slide">⏭</button>
                                    <div class="va-prog" id="vaProg" aria-hidden="true"></div>
                                    <span class="va-cont" id="vaCont"></span>
                                    <button type="button" class="va-bt va-vel" id="vaVel" aria-label="Velocidade">1x</button>
                                    <button type="button" class="va-bt" id="vaTela" aria-label="Tela cheia">⛶</button>
                                    <button type="button" class="va-bt" id="vaFechar" aria-label="Fechar videoaula">✕</button>
                                </div>
                                <audio id="vaAudio" preload="auto"></audio>
                            </div>
                        <?php else: ?>
                            <div class="video-empty">
                                <div>
                                    <i data-lucide="file-x" aria-hidden="true"></i>
                                    <h2>Conteúdo em preparação</h2>
                                    <p>O material desta aula será liberado em breve.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($aulaAtual && ($videoUrlAtual !== '' || $pdfAtual['arquivo'] !== '')): ?>
                    <div class="aula-duration-summary" aria-label="Tempos deste conteúdo">
                        <?php if ($videoUrlAtual !== ''): ?>
                            <div class="aula-duration-item">
                                <span class="aula-duration-item__icon"><i data-lucide="clock-3" aria-hidden="true"></i></span>
                                <span class="aula-duration-item__text">
                                    <span>Tempo do vídeo</span>
                                    <strong id="aulaVideoDurationValue" data-manual-duration="<?php echo $videoDuracaoAtual !== '' ? '1' : '0'; ?>"><?php echo esc($videoDuracaoAtual !== '' ? $videoDuracaoAtual : 'Carregando...'); ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($pdfAtual['arquivo'] !== ''): ?>
                            <div class="aula-duration-item">
                                <span class="aula-duration-item__icon"><i data-lucide="file-clock" aria-hidden="true"></i></span>
                                <span class="aula-duration-item__text">
                                    <span>Tempo do PDF</span>
                                    <strong><?php echo esc($pdfDuracaoAtual !== '' ? $pdfDuracaoAtual : 'Não informado'); ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="aula-duration-item">
                            <span class="aula-duration-item__icon"><i data-lucide="timer" aria-hidden="true"></i></span>
                            <span class="aula-duration-item__text">
                                <span>Tempo total</span>
                                <strong
                                    id="aulaContentDurationValue"
                                    data-video-seconds="<?php echo (int)$videoDuracaoSegundosConhecida; ?>"
                                    data-pdf-seconds="<?php echo (int)$pdfDuracaoSegundos; ?>"
                                    data-video-dynamic="<?php echo ($videoUrlAtual !== '' && $videoDuracaoSegundosManual <= 0) ? '1' : '0'; ?>"><?php echo esc($conteudoDuracaoTexto); ?></strong>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                // Método META: o ciclo do aluno neste assunto, com o passo atual em destaque.
                $ciclo = null;
                try {
                    if ($aulaAtual && function_exists('db')) {
                        require_once __DIR__ . '/includes/meta-lib.php';
                        $pdoCiclo = db();
                        meta_bootstrap($pdoCiclo);
                        $cfgCiclo = meta_config($pdoCiclo);
                        $stC = $pdoCiclo->prepare('SELECT topico_id FROM admin_curso_aulas WHERE id = :id');
                        $stC->execute([':id' => (int)$aulaAtual['id']]);
                        $topicoCiclo = (int)$stC->fetchColumn();
                        $resC = 0; $aceC = 0;
                        if ($topicoCiclo > 0) {
                            $stC = $pdoCiclo->prepare('SELECT COUNT(*), COALESCE(SUM(r.correta),0) FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.usuario_id = :u AND q.materia_topico_id = :t');
                            $stC->execute([':u' => (int)$usuarioId, ':t' => $topicoCiclo]);
                            [$resC, $aceC] = array_map('intval', $stC->fetch(PDO::FETCH_NUM));
                        }
                        $revC = 0;
                        try {
                            $stC = $pdoCiclo->prepare('SELECT COUNT(*) FROM meta_aluno_revisoes r JOIN meta_plano_itens i ON i.id = r.plano_item_id WHERE r.usuario_id = :u AND i.aula_id = :a');
                            $stC->execute([':u' => (int)$usuarioId, ':a' => (int)$aulaAtual['id']]);
                            $revC = (int)$stC->fetchColumn();
                        } catch (Throwable $e) {
                        }
                        $minC = max(1, (int)($cfgCiclo['min_questoes'] ?? 5));
                        $metaC = max($minC, (int)($cfgCiclo['nivel4_questoes'] ?? 50));
                        $ciclo = ['teoria' => $aulaAtualAssistida, 'res' => $resC, 'pct' => $resC ? (int)round($aceC * 100 / $resC) : 0, 'min' => $minC, 'meta' => $metaC,
                            'nivel' => meta_nivel($resC, $aceC, $cfgCiclo), 'rev' => min(3, $revC), 'topico' => $topicoCiclo];
                        $ciclo['passo'] = !$ciclo['teoria'] ? 1 : ($resC < $minC ? 2 : ($revC < 3 ? 3 : 4));
                    }
                } catch (Throwable $e) {
                    $ciclo = null;
                }
                ?>
                <?php if ($ciclo): ?><script>window.gabCiclo = <?php echo json_encode(['topico' => (int)$ciclo['topico'], 'min' => (int)$ciclo['min'], 'res' => (int)$ciclo['res']]); ?>;</script><?php endif; ?>
                <?php if ($ciclo): $cp = $ciclo['passo']; ?>
                <section class="ciclo" aria-labelledby="cicloTitulo">
                    <div class="ciclo-topo">
                        <h2 id="cicloTitulo">🎯 Método META · seu ciclo neste assunto</h2>
                        <p>Teoria mínima → Questões comentadas → Revisões → Simulado e Redação, sempre com mentoria. <strong>O próximo assunto só abre com <?php echo (int)$ciclo['min']; ?> questões resolvidas.</strong></p>
                    </div>
                    <ol class="ciclo-passos">
                        <li class="<?php echo $cp > 1 ? 'feito' : 'agora'; ?>">
                            <span class="ciclo-n"><?php echo $cp > 1 ? '✓' : '1'; ?></span>
                            <div><strong>Teoria mínima</strong><small>Escolha um jeito: videoaula do professor, videoaula do Antigão ou o PDF com marcações. O podcast é extra.</small>
                            <em><?php echo $cp > 1 ? 'Concluída' : 'Ao terminar, toque em “Marcar como assistido”.'; ?></em></div>
                        </li>
                        <li class="<?php echo $cp > 2 ? 'feito' : ($cp === 2 ? 'agora' : ''); ?>">
                            <span class="ciclo-n"><?php echo $cp > 2 ? '✓' : '2'; ?></span>
                            <div><strong>Questões comentadas</strong><small>Mínimo de <?php echo (int)$ciclo['min']; ?> para avançar · meta de <?php echo (int)$ciclo['meta']; ?> para dominar o assunto.</small>
                            <span class="ciclo-barra" aria-hidden="true"><i style="width:<?php echo min(100, (int)round($ciclo['res'] * 100 / $ciclo['meta'])); ?>%"></i><b style="left:<?php echo (int)round($ciclo['min'] * 100 / $ciclo['meta']); ?>%"></b></span>
                            <em><?php echo (int)$ciclo['res']; ?>/<?php echo (int)$ciclo['meta']; ?> resolvidas<?php if ($ciclo['res']): ?> · <?php echo (int)$ciclo['pct']; ?>% de acerto<?php endif; ?> · nível <?php echo esc((string)($ciclo['nivel']['nome'] ?? '—')); ?></em>
                            <?php if ($ciclo['topico'] > 0 && $cp >= 2): ?><a class="ciclo-bt" href="banco-questoes.php?topico_id=<?php echo (int)$ciclo['topico']; ?>">Resolver questões</a><?php endif; ?></div>
                        </li>
                        <li class="<?php echo $cp > 3 ? 'feito' : ($cp === 3 ? 'agora' : ''); ?>">
                            <span class="ciclo-n"><?php echo $cp > 3 ? '✓' : '3'; ?></span>
                            <div><strong>Revisões</strong><small>1 dia, 7 dias e 30 dias depois: aparecem sozinhas no seu cronograma.</small>
                            <em><?php echo (int)$ciclo['rev']; ?> de 3 feitas</em></div>
                        </li>
                        <li class="<?php echo $cp === 4 ? 'agora' : ''; ?>">
                            <span class="ciclo-n">4</span>
                            <div><strong>Simulado e Redação</strong><small>Toda semana, no dia marcado no seu cronograma: medem se o assunto ficou de verdade.</small></div>
                        </li>
                    </ol>
                    <p class="ciclo-mentoria">🎖 <strong>Mentoria semanal:</strong> seu mentor acompanha esses números e ajusta seu plano. O Ranking Individual também ajusta o tempo de cada matéria.</p>
                </section>
                <?php endif; ?>

                <div class="aula-actions" aria-label="Ações da aula">
                    <div class="rating-box" id="aulaRating" data-rating="<?php echo (int)$minhaNotaAula; ?>" data-media="<?php echo esc((string)$mediaNotaAula); ?>" data-total="<?php echo (int)$totalAvaliacoesAula; ?>">
                        <div class="stars" role="radiogroup" aria-label="Avaliação da aula">
                            <?php for ($estrela = 1; $estrela <= 5; $estrela++): ?>
                                <button type="button" class="star-btn<?php echo $estrela <= $minhaNotaAula ? ' is-active' : ''; ?>" data-star="<?php echo $estrela; ?>" aria-label="Avaliar com <?php echo $estrela; ?> <?php echo $estrela === 1 ? 'estrela' : 'estrelas'; ?>" aria-checked="<?php echo $estrela === $minhaNotaAula ? 'true' : 'false'; ?>" <?php echo (!$aulaAtual || $erroPagina !== '') ? 'disabled' : ''; ?>><?php echo $estrela <= $minhaNotaAula ? '★' : '☆'; ?></button>
                            <?php endfor; ?>
                        </div>
                        <small class="rating-status" id="ratingStatus"><?php if ($minhaNotaAula > 0): ?>Sua avaliação: <?php echo $minhaNotaAula; ?>/5<?php elseif ($totalAvaliacoesAula > 0): ?>Média <?php echo esc(number_format($mediaNotaAula, 1, ',', '.')); ?>/5<?php else: ?>Avalie a aula<?php endif; ?></small>
                    </div>
                    <button
                        type="button"
                        class="action-btn action-btn--wide<?php echo $aulaAtualAssistida ? ' is-watched' : ''; ?>"
                        id="marcarAssistido"
                        data-video-required="<?php echo $videoRastreavel ? '1' : '0'; ?>"
                        <?php echo (!$aulaAtual || $erroPagina !== '' || ($videoRastreavel && !$aulaAtualAssistida)) ? 'disabled' : ''; ?>><?php if ($aulaAtualAssistida): ?><i data-lucide="check"></i>Assistido<?php elseif ($videoRastreavel): ?><i data-lucide="play-circle"></i>Assista ao vídeo até o fim<?php else: ?>Marcar como assistido<?php endif; ?></button>
                    <a href="<?php echo esc($linkAnterior !== '' ? $linkAnterior : '#'); ?>" class="action-btn action-btn--nav<?php echo $linkAnterior === '' ? ' is-disabled' : ''; ?>" aria-label="Aula anterior"><i data-lucide="chevron-left"></i>Anterior</a>
                    <a href="<?php echo esc($linkProximo !== '' ? $linkProximo : '#'); ?>" class="action-btn action-btn--nav<?php echo $linkProximo === '' ? ' is-disabled' : ''; ?>" aria-label="Próxima aula"><i data-lucide="chevron-right"></i>Próximo</a>
                </div>

                <div class="aula-extra">
                    <div class="content-section">
                        <?php $aulaDescricaoHtml = aula_descricao_html($aulaDescricao); ?>
                        <?php if ($aulaAtual && ($aulaDescricaoHtml !== '' || $pdfAtual['arquivo'] !== '')): ?>
                        <div class="pod" style="margin-bottom:20px" id="aulaPodcast" data-aula-id="<?php echo (int)($aulaAtual['id'] ?? 0); ?>">
                                <button type="button" class="pod-btn" id="podBtn"><span aria-hidden="true">🎧</span><span id="podBtnTxt">Extra · Ouvir o podcast: Antigão e Recruta 01</span></button>
                                <div class="pod-progresso" id="podProg" hidden><div class="pod-barra"><i id="podBarra"></i></div><span id="podMsg">Preparando o roteiro…</span></div>
                                <div class="pod-player" id="podPlayer" hidden>
                                    <audio id="podAudio" controls preload="metadata"></audio>
                                    <div class="pod-vel" role="group" aria-label="Velocidade"><button type="button" data-v="1">1x</button><button type="button" data-v="1.25">1,25x</button><button type="button" data-v="1.5">1,5x</button></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($aulaDescricaoHtml !== ''): ?>
                        <section class="ad" id="aulaDescricao" data-aula-id="<?php echo (int)($aulaAtual['id'] ?? 0); ?>" aria-label="Material da aula">
                            <div class="ad-barra">
                                <h2>Material da aula</h2>
                                <div class="ad-ferramentas" role="toolbar" aria-label="Ferramentas de estudo">
                                    <button type="button" class="ad-btn" data-ad="marcar" title="Selecione um trecho e clique para marcar"><span class="ad-marca-ico"></span>Marcar</button>
                                    <button type="button" class="ad-btn" data-ad="limpar" title="Remover todas as suas marcações desta aula">Limpar marcações</button>
                                    <button type="button" class="ad-btn" data-ad="menor" aria-label="Diminuir letra">A−</button>
                                    <button type="button" class="ad-btn" data-ad="maior" aria-label="Aumentar letra">A+</button>
                                    <button type="button" class="ad-btn" data-ad="recolher">Abrir tudo</button>
                                </div>
                            </div>
                            <p class="ad-dica">Dica: selecione um trecho com o dedo ou o mouse: o botão <strong>Marcar</strong> aparece junto do texto. Suas marcações ficam salvas na sua conta. Toque numa marcação para removê-la.</p>
                            <div class="ad-texto" id="aulaDescricaoTexto"><?php echo $aulaDescricaoHtml; ?></div>
                            <span class="ad-status" id="aulaDescricaoStatus" role="status" aria-live="polite"></span>
                        </section>
                        <?php endif; ?>

                        <?php
                        $indicacoesAula = [];
                        try {
                            if ($aulaAtual && function_exists('db')) {
                                $stIndTop = db()->prepare('SELECT topico_id FROM admin_curso_aulas WHERE id = :id');
                                $stIndTop->execute([':id' => (int)$aulaAtual['id']]);
                                $indicacoesAula = indicacoes_do_topico(db(), (int)$stIndTop->fetchColumn());
                            }
                        } catch (Throwable $e) {
                            $indicacoesAula = [];
                        }
                        echo indicacoes_html($indicacoesAula);
                        ?>

                        <h2>PDF da aula</h2>
                        <?php if ($pdfAtual['arquivo'] !== ''): ?>
                            <div class="aula-pdf-card">
                                <div class="aula-pdf-info">
                                    <span class="aula-pdf-icon"><i data-lucide="file-text" aria-hidden="true"></i></span>
                                    <span class="aula-pdf-text">
                                        <span class="aula-pdf-label">Material para download</span>
                                        <span class="aula-pdf-name"><?php echo esc($pdfAtual['nome']); ?></span>
                                    </span>
                                </div>
                                <div class="aula-pdf-actions">
                                    <button
                                        type="button"
                                        class="aula-pdf-view"
                                        id="abrirPdfAula"
                                        data-pdf-url="<?php echo esc($pdfAtual['arquivo']); ?>"
                                        data-pdf-name="<?php echo esc($pdfAtual['nome']); ?>"
                                        data-pdf-key="<?php echo esc($pdfChaveAtual); ?>">
                                        <i data-lucide="eye" aria-hidden="true"></i>
                                        Ver PDF
                                    </button>
                                    <button
                                        type="button"
                                        class="aula-pdf-download pdf-download-watermarked"
                                        data-pdf-url="<?php echo esc($pdfAtual['arquivo']); ?>"
                                        data-pdf-name="<?php echo esc($pdfAtual['nome']); ?>"
                                        aria-label="Baixar PDF com marca d'água">
                                        <i data-lucide="download" aria-hidden="true"></i>
                                        <span class="pdf-download-label">Baixar PDF</span>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="aula-pdf-empty"><i data-lucide="file-x" aria-hidden="true"></i>Nenhum PDF cadastrado para esta aula.</div>
                        <?php endif; ?>

                        <div class="forum-head">
                            <h2>Tópicos no fórum</h2>
                            <button type="button" class="topic-btn" id="abrirTopico" aria-haspopup="dialog" aria-controls="modalTopico"><i data-lucide="plus"></i>Novo Tópico</button>
                        </div>

                        <div class="topics-list" id="topicosLista" aria-live="polite">
                            <?php if (!$topicos): ?>
                                <div class="topics-empty" id="topicosVazio">Nenhum tópico criado para essa aula ainda.</div>
                            <?php else: ?>
                                <?php foreach ($topicos as $topico): ?>
                                    <?php
                                        $visibilidadeTopico = (string)($topico['visibilidade'] ?? 'todos');
                                        $visibilidadeLabel = $visibilidadeTopico === 'monitores' ? 'Apenas monitores' : 'Todos';
                                    ?>
                                    <article class="topic-card">
                                        <header class="topic-card__head">
                                            <strong class="topic-card__title"><?php echo esc((string)$topico['titulo']); ?></strong>
                                            <small class="topic-card__meta"><?php echo esc((string)($topico['usuario_nome'] ?? 'Aluno')); ?> • <?php echo esc(aulas_formatar_data((string)($topico['criado_em'] ?? ''))); ?></small>
                                        </header>
                                        <p class="topic-card__text"><?php echo nl2br(esc((string)$topico['texto'])); ?></p>

                                        <?php $respostasDoTopico = $respostasPorTopico[(int)($topico['id'] ?? 0)] ?? []; ?>
                                        <?php if ($respostasDoTopico): ?>
                                            <div class="topic-replies">
                                                <?php foreach ($respostasDoTopico as $respostaTopico): ?>
                                                    <div class="topic-reply">
                                                        <div class="topic-reply__meta"><i data-lucide="reply"></i><?php echo esc((string)($respostaTopico['autor_nome'] ?: 'Admin')); ?> • <?php echo esc(aulas_formatar_data((string)($respostaTopico['criado_em'] ?? ''))); ?></div>
                                                        <div class="topic-reply__text"><?php echo nl2br(esc((string)($respostaTopico['mensagem'] ?? ''))); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="topic-card__badge"><?php echo esc($visibilidadeLabel); ?></span>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="aulas-sidebar" aria-label="Lista de aulas">
                <header class="course-head">
                    <div class="progress-ring" style="--progress:<?php echo (int)$progresso; ?>"><span id="progressPercent"><?php echo (int)$progresso; ?>%</span></div>

                    <div class="course-info">
                        <h1 class="course-info__title"><?php echo esc($cursoTitulo); ?></h1>
                        <p class="course-info__line"><?php echo esc($moduloTitulo); ?></p>
                        <p class="course-info__muted"><?php echo (int)$totalAulas; ?> <?php echo $totalAulas === 1 ? 'aula' : 'aulas'; ?></p>
                    </div>

                    <button type="button" class="sidebar-search" id="abrirPesquisaAulas" aria-label="Pesquisar aulas" aria-expanded="false" aria-controls="lessonSearchWrap"><i data-lucide="search"></i></button>
                </header>

                <div class="lesson-search-wrap" id="lessonSearchWrap" aria-hidden="true">
                    <div class="lesson-search-box">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input type="search" class="lesson-search-input" id="lessonSearchInput" placeholder="Pesquisar vídeo deste módulo" autocomplete="off">
                        <button type="button" class="lesson-search-clear" id="lessonSearchClear" aria-label="Limpar pesquisa">×</button>
                    </div>
                    <div class="lesson-search-empty" id="lessonSearchEmpty">Nenhuma aula encontrada nesse módulo.</div>
                </div>

                <nav class="lesson-list" aria-label="Conteúdos do módulo">
                    <?php if (!$aulas): ?>
                        <a href="#" class="lesson-item is-disabled">
                            <span class="lesson-dot" aria-hidden="true"></span>
                            <span>NENHUMA AULA CADASTRADA</span>
                        </a>
                    <?php else: ?>
                        <?php foreach ($aulas as $item): ?>
                            <?php
                                $itemId = (int)$item['id'];
                                $ativo = $aulaAtual && $itemId === (int)$aulaAtual['id'];
                                $assistido = isset($aulasAssistidas[$itemId]);
                                $duracaoItem = trim((string)($item['duracao'] ?? ''));
                            ?>
                            <a href="<?php echo esc($linkBaseAula . '&aula_id=' . $itemId); ?>" class="lesson-item<?php echo $ativo ? ' is-active' : ''; ?><?php echo $assistido ? ' is-watched' : ''; ?>" data-aula-id="<?php echo $itemId; ?>">
                                <span class="lesson-dot" aria-hidden="true"><?php if ($assistido): ?><i data-lucide="check" class="lesson-check"></i><?php endif; ?></span>
                                <span>
                                    <?php echo esc((string)$item['titulo']); ?>
                                    <?php if ($duracaoItem !== ''): ?>
                                        <small class="lesson-meta"><?php echo esc($duracaoItem); ?></small>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </nav>
            </aside>
        </div>
    </main>

    <div class="topic-modal" id="modalTopico" aria-hidden="true">
        <div class="topic-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTopicoTitulo">
            <header class="topic-modal__head">
                <h3 id="modalTopicoTitulo">Criar novo tópico</h3>
                <button type="button" class="topic-modal__close" id="fecharTopico" aria-label="Fechar popup">×</button>
            </header>

            <form class="topic-modal__body" id="formTopico">
                <input type="text" class="topic-input" name="titulo" placeholder="Título" autocomplete="off" required>

                <div class="topic-editor" aria-label="Editor do tópico">
                    <div class="topic-editor__toolbar" aria-hidden="true">
                        <span>B</span>
                        <span><em>I</em></span>
                        <span><u>U</u></span>
                        <span><s>S</s></span>
                        <span>☷</span>
                        <span>☰</span>
                        <span>A</span>
                        <span>A</span>
                        <span>🔗</span>
                        <span>▣</span>
                    </div>
                    <textarea class="topic-editor__text" name="texto" placeholder="Insira o texto aqui..." required></textarea>
                </div>

                <div class="topic-options" role="radiogroup" aria-label="Visibilidade do tópico">
                    <label class="topic-option">
                        <input type="radio" name="visibilidade" value="todos" checked>
                        <span class="topic-option__dot" aria-hidden="true"></span>
                        <span>Postar para todos</span>
                    </label>
                    <label class="topic-option">
                        <input type="radio" name="visibilidade" value="monitores">
                        <span class="topic-option__dot" aria-hidden="true"></span>
                        <span>Postar apenas para monitores</span>
                    </label>
                </div>

                <button type="submit" class="topic-modal__submit">Criar tópico</button>
            </form>
        </div>
    </div>

    <div class="pdf-viewer-modal" id="pdfViewerModal" aria-hidden="true">
        <section class="pdf-viewer-shell" role="dialog" aria-modal="true" aria-labelledby="pdfViewerTitle">
            <header class="pdf-viewer-head">
                <div class="pdf-viewer-title">
                    <i data-lucide="file-text" aria-hidden="true"></i>
                    <span id="pdfViewerTitle">PDF da aula</span>
                </div>

                <div class="pdf-viewer-toolbar" aria-label="Controles do PDF">
                    <button type="button" class="pdf-tool-btn" id="pdfPaginaAnterior" aria-label="Página anterior"><i data-lucide="chevron-left"></i></button>
                    <label class="pdf-page-control" aria-label="Página atual">
                        <input type="number" id="pdfPaginaAtual" min="1" value="1" inputmode="numeric">
                        <span>de <strong id="pdfTotalPaginas">0</strong></span>
                    </label>
                    <button type="button" class="pdf-tool-btn" id="pdfProximaPagina" aria-label="Próxima página"><i data-lucide="chevron-right"></i></button>
                    <button type="button" class="pdf-tool-btn" id="pdfMenosZoom" aria-label="Diminuir zoom"><i data-lucide="zoom-out"></i></button>
                    <span class="pdf-zoom-label" id="pdfZoomLabel">100%</span>
                    <button type="button" class="pdf-tool-btn" id="pdfMaisZoom" aria-label="Aumentar zoom"><i data-lucide="zoom-in"></i></button>
                    <button type="button" class="pdf-tool-btn" id="pdfMarcarBtn" aria-pressed="false" title="Marca-texto: arraste sobre o trecho" aria-label="Marca-texto">🖍</button>
                    <button type="button" class="pdf-tool-link pdf-download-watermarked" id="pdfViewerDownload" aria-label="Baixar PDF com marca d'água" title="Baixar PDF com marca d'água"><i data-lucide="download"></i></button>
                </div>

                <button type="button" class="pdf-tool-btn pdf-viewer-close" id="fecharPdfAula" aria-label="Fechar PDF"><i data-lucide="x"></i></button>
            </header>

            <div class="pdf-viewer-body" id="pdfViewerBody">
                <div class="pdf-canvas-wrap">
                    <div class="pdf-marca-area"><canvas id="pdfViewerCanvas"></canvas><div class="pdf-marca-camada" id="pdfMarcaCamada" aria-label="Marcações desta página"></div></div>
                </div>
                <p class="pdf-marca-dica" id="pdfMarcaDica" hidden>🖍 Arraste sobre o trecho para marcar · toque numa marcação para apagar</p>
                <div class="pdf-viewer-status" id="pdfViewerStatus">
                    <div class="pdf-viewer-status__box">
                        <span class="pdf-viewer-spinner" aria-hidden="true"></span>
                        <strong id="pdfViewerStatusText">Carregando PDF...</strong>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
    <script>
        window.aulasConfig = <?php echo json_encode([
            'cursoId' => $cursoId,
            'moduloId' => $moduloId,
            'aulaId' => $aulaAtual ? (int)$aulaAtual['id'] : 0,
            'youtubeVideoId' => $youtubeVideoId,
            'loginUrl' => 'login.php',
            'progresso' => (int)$progresso,
            'assistida' => $aulaAtualAssistida,
            'videoRastreavel' => $videoRastreavel,
            'videoProgresso' => [
                'posicao' => (int)($videoProgressoAtual['posicao'] ?? 0),
                'duracao' => (int)($videoProgressoAtual['duracao'] ?? 0),
                'percentual' => (float)($videoProgressoAtual['percentual'] ?? 0),
            ],
            'moduloProgresso' => [
                'totalAulas' => (int)($dadosProgresso['total'] ?? count($aulas)),
                'somaFracoes' => (float)($dadosProgresso['soma_fracoes'] ?? 0),
                'percentualAulaAtual' => $aulaAtual ? (float)($dadosProgresso['percentuais'][(int)$aulaAtual['id']] ?? ($aulaAtualAssistida ? 100 : 0)) : 0,
            ],
            'duracoes' => [
                'videoManualSegundos' => (int)$videoDuracaoSegundosManual,
                'pdfSegundos' => (int)$pdfDuracaoSegundos,
            ],
            'pdf' => [
                'url' => $pdfAtual['arquivo'],
                'nome' => $pdfAtual['nome'],
                'chave' => $pdfChaveAtual,
                'pagina' => (int)($pdfProgressoAtual['pagina'] ?? 1),
                'totalPaginas' => (int)($pdfProgressoAtual['total_paginas'] ?? 0),
                'zoom' => (float)($pdfProgressoAtual['zoom'] ?? 1),
                'storageKey' => $pdfChaveAtual !== '' ? 'gabaritando_pdf_' . $usuarioId . '_' . (int)($aulaAtual['id'] ?? 0) . '_' . $pdfChaveAtual : '',
            ],
            'avaliacao' => [
                'minhaNota' => (int)$minhaNotaAula,
                'media' => (float)$mediaNotaAula,
                'total' => (int)$totalAvaliacoesAula,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        (function criarIconesDaPagina(){
            window.aulasRenderizarIcones = function(){
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            };

            if (window.lucide) {
                window.aulasRenderizarIcones();
                return;
            }

            window.addEventListener('load', function(){
                setTimeout(window.aulasRenderizarIcones, 250);
            });
        })();


        (function iniciarPlayerPersonalizado(){
            const root = document.getElementById('gabaritandoVideoPlayer');
            if (!root) {
                return;
            }

            const videoId = String(root.dataset.videoId || '').trim();
            if (!videoId) {
                return;
            }

            const botaoCentral = document.getElementById('gPlayerBigPlay');
            const botaoPlay = document.getElementById('gPlayerPlay');
            const botaoVoltar = document.getElementById('gPlayerBack');
            const botaoAvancar = document.getElementById('gPlayerForward');
            const botaoMudo = document.getElementById('gPlayerMute');
            const botaoVelocidade = document.getElementById('gPlayerSpeed');
            const botaoTelaCheia = document.getElementById('gPlayerFullscreen');
            const botaoRepetir = document.getElementById('gPlayerReplay');
            const camadaClique = document.getElementById('gPlayerTapLayer');
            const controles = document.getElementById('gPlayerControls');
            const linhaTempo = document.getElementById('gPlayerTimeline');
            const controleVolume = document.getElementById('gPlayerVolume');
            const tempoAtual = document.getElementById('gPlayerCurrent');
            const tempoTotal = document.getElementById('gPlayerDuration');
            const telaFinal = document.getElementById('gPlayerEnded');
            const resumoDuracaoVideo = document.getElementById('aulaVideoDurationValue');
            const resumoTemDuracaoManual = resumoDuracaoVideo && resumoDuracaoVideo.dataset.manualDuration === '1';
            const resumoDuracaoConteudo = document.getElementById('aulaContentDurationValue');
            const configPagina = window.aulasConfig || {};
            const configVideo = configPagina.videoProgresso || {};
            const configModulo = configPagina.moduloProgresso || {};
            const btnAssistidoVideo = document.getElementById('marcarAssistido');

            let player = null;
            let pronto = false;
            let arrastandoTempo = false;
            let intervaloTempo = null;
            let temporizadorControles = null;
            let temporizadorFeedbackCentral = null;
            let temporizadorSalvarVideo = null;
            let envioVideoEmAndamento = false;
            let finalizacaoVideoPendente = false;
            let ultimoVolume = 100;
            let progressoContinuo = Math.max(0, Number(configVideo.posicao) || 0);
            let progressoEnviado = progressoContinuo;
            let duracaoConhecida = Math.max(0, Number(configVideo.duracao) || 0);
            let videoConcluido = Boolean(configPagina.assistida);
            const totalAulasModulo = Math.max(0, Number(configModulo.totalAulas) || 0);
            const fracaoAtualInicial = Math.max(0, Math.min(1, (Number(configModulo.percentualAulaAtual) || 0) / 100));
            const somaFracoesSemAtual = Math.max(0, (Number(configModulo.somaFracoes) || 0) - fracaoAtualInicial);

            function renderizarIcones(){
                if (window.aulasRenderizarIcones) {
                    window.aulasRenderizarIcones();
                } else if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            function trocarIcone(botao, icone, label){
                if (!botao) {
                    return;
                }

                botao.innerHTML = '<i data-lucide="' + icone + '"></i>';
                if (label) {
                    botao.setAttribute('aria-label', label);
                }
                renderizarIcones();
            }

            function formatarTempo(segundos){
                segundos = Math.max(0, Math.floor(Number(segundos) || 0));
                const horas = Math.floor(segundos / 3600);
                const minutos = Math.floor((segundos % 3600) / 60);
                const resto = segundos % 60;

                if (horas > 0) {
                    return String(horas).padStart(2, '0') + ':'
                        + String(minutos).padStart(2, '0') + ':'
                        + String(resto).padStart(2, '0');
                }

                return String(minutos).padStart(2, '0') + ':' + String(resto).padStart(2, '0');
            }

            function formatarDuracaoResumo(segundos){
                segundos = Math.max(0, Math.round(Number(segundos) || 0));
                if (segundos <= 0) {
                    return 'Não informado';
                }

                let horas = Math.floor(segundos / 3600);
                let minutos = Math.ceil((segundos % 3600) / 60);
                if (minutos >= 60) {
                    horas += 1;
                    minutos = 0;
                }
                if (horas > 0 && minutos > 0) {
                    return horas + 'h ' + minutos + 'min';
                }
                if (horas > 0) {
                    return horas + 'h';
                }
                return Math.max(1, minutos) + 'min';
            }

            function atualizarResumoConteudo(duracaoVideo){
                if (!resumoDuracaoConteudo) {
                    return;
                }

                const pdfSegundos = Math.max(0, Number(resumoDuracaoConteudo.dataset.pdfSeconds) || 0);
                const videoDinamico = resumoDuracaoConteudo.dataset.videoDynamic === '1';
                let videoSegundos = Math.max(0, Number(resumoDuracaoConteudo.dataset.videoSeconds) || 0);
                if (videoDinamico && duracaoVideo > 0) {
                    videoSegundos = duracaoVideo;
                    resumoDuracaoConteudo.dataset.videoSeconds = String(Math.round(duracaoVideo));
                }
                resumoDuracaoConteudo.textContent = formatarDuracaoResumo(videoSegundos + pdfSegundos);
            }

            function atualizarProgressoModuloVisual(percentualVideo){
                const percentualAula = videoConcluido ? 100 : Math.max(0, Math.min(99.99, Number(percentualVideo) || 0));
                const percentualModulo = totalAulasModulo > 0
                    ? Math.round(((somaFracoesSemAtual + (percentualAula / 100)) / totalAulasModulo) * 100)
                    : 0;
                const valor = Math.max(0, Math.min(100, percentualModulo));
                const texto = document.getElementById('progressPercent');
                const ring = document.querySelector('.progress-ring');
                if (texto) {
                    texto.textContent = valor + '%';
                }
                if (ring) {
                    ring.style.setProperty('--progress', valor);
                }
            }

            function marcarAulaConcluidaVisualmente(){
                videoConcluido = true;
                progressoContinuo = Math.max(progressoContinuo, duracaoConhecida);
                if (btnAssistidoVideo) {
                    btnAssistidoVideo.classList.add('is-watched');
                    btnAssistidoVideo.disabled = true;
                    btnAssistidoVideo.innerHTML = '<i data-lucide="check"></i>Assistido';
                }
                const item = document.querySelector('.lesson-item[data-aula-id="' + String(configPagina.aulaId || '') + '"]');
                if (item) {
                    item.classList.add('is-watched');
                    const dot = item.querySelector('.lesson-dot');
                    if (dot && !dot.querySelector('.lesson-check')) {
                        dot.innerHTML = '<i data-lucide="check" class="lesson-check"></i>';
                    }
                }
                if (window.aulasRenderizarIcones) {
                    window.aulasRenderizarIcones();
                }
                atualizarProgressoModuloVisual(100);
            }

            function atualizarFaixa(campo, percentual){
                if (!campo) {
                    return;
                }

                const valor = Math.max(0, Math.min(100, Number(percentual) || 0));
                campo.style.setProperty('--range-progress', valor + '%');
            }

            function obterEstado(){
                if (!pronto || !player || typeof player.getPlayerState !== 'function') {
                    return -2;
                }
                return player.getPlayerState();
            }

            function estaReproduzindo(){
                return obterEstado() === 1;
            }

            function mostrarControles(){
                root.classList.remove('is-controls-hidden');
                clearTimeout(temporizadorControles);

                if (estaReproduzindo()) {
                    temporizadorControles = setTimeout(function(){
                        if (!root.matches(':hover') && !root.contains(document.activeElement)) {
                            root.classList.add('is-controls-hidden');
                        }
                    }, 2200);
                }
            }

            function agendarOcultacao(){
                clearTimeout(temporizadorControles);
                if (!estaReproduzindo()) {
                    root.classList.remove('is-controls-hidden');
                    return;
                }

                temporizadorControles = setTimeout(function(){
                    root.classList.add('is-controls-hidden');
                }, 1800);
            }

            function salvarProgressoVideo(finalizado, usarBeacon){
                if (!configPagina.videoRastreavel || !configPagina.aulaId || !pronto || !player) {
                    return;
                }

                const duracaoPlayer = Number(player.getDuration ? player.getDuration() : 0) || duracaoConhecida;
                duracaoConhecida = Math.max(duracaoConhecida, duracaoPlayer);
                const posicaoEnviar = Math.max(0, Math.min(duracaoConhecida || progressoContinuo, progressoContinuo));

                if (!finalizado && posicaoEnviar <= progressoEnviado + 1) {
                    return;
                }

                const dados = new URLSearchParams();
                dados.set('acao', 'salvar_video_progresso');
                dados.set('posicao', String(Math.floor(posicaoEnviar)));
                dados.set('duracao', String(Math.round(duracaoConhecida)));
                dados.set('finalizado', finalizado ? '1' : '0');
                // Sempre envia os IDs reais da aula aberta. Quando a página era acessada
                // sem aula_id na URL, o servidor recusava o salvamento e o progresso voltava a 0.
                const endpoint = window.location.pathname
                    + '?curso_id=' + encodeURIComponent(configPagina.cursoId || 0)
                    + '&modulo_id=' + encodeURIComponent(configPagina.moduloId || 0)
                    + '&aula_id=' + encodeURIComponent(configPagina.aulaId || 0);

                if (usarBeacon && navigator.sendBeacon) {
                    navigator.sendBeacon(endpoint, new Blob([dados.toString()], {type:'application/x-www-form-urlencoded;charset=UTF-8'}));
                    progressoEnviado = Math.max(progressoEnviado, posicaoEnviar);
                    return;
                }

                if (envioVideoEmAndamento) {
                    if (finalizado) {
                        finalizacaoVideoPendente = true;
                    }
                    return;
                }
                envioVideoEmAndamento = true;
                fetch(endpoint, {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                    body:dados.toString(),
                    credentials:'same-origin'
                }).then(function(resposta){
                    return resposta.json().then(function(data){
                        if (!resposta.ok || !data.ok) {
                            throw new Error(data.mensagem || 'Não foi possível salvar o progresso do vídeo.');
                        }
                        return data;
                    });
                }).then(function(data){
                    progressoEnviado = Math.max(progressoEnviado, Number(data.posicao) || 0);
                    progressoContinuo = Math.max(progressoContinuo, Number(data.posicao) || 0);
                    duracaoConhecida = Math.max(duracaoConhecida, Number(data.duracao) || 0);
                    if (data.assistida) {
                        marcarAulaConcluidaVisualmente();
                    } else if (typeof data.progresso !== 'undefined') {
                        const texto = document.getElementById('progressPercent');
                        const ring = document.querySelector('.progress-ring');
                        const valor = Math.max(0, Math.min(100, parseInt(data.progresso, 10) || 0));
                        if (texto) texto.textContent = valor + '%';
                        if (ring) ring.style.setProperty('--progress', valor);
                    }
                }).catch(function(erro){
                    // Mantém a reprodução normal e tenta salvar novamente no próximo intervalo.
                    // O progresso só é considerado enviado depois da confirmação do banco.
                    if (window.console && typeof window.console.warn === 'function') {
                        window.console.warn('[Aulas] Não foi possível salvar o progresso do vídeo:', erro);
                    }
                }).finally(function(){
                    envioVideoEmAndamento = false;
                    if (finalizacaoVideoPendente) {
                        finalizacaoVideoPendente = false;
                        salvarProgressoVideo(true, false);
                    }
                });
            }

            function agendarSalvamentoVideo(){
                if (temporizadorSalvarVideo) {
                    return;
                }
                temporizadorSalvarVideo = setTimeout(function(){
                    temporizadorSalvarVideo = null;
                    salvarProgressoVideo(false, false);
                }, 1200);
            }

            function atualizarTempo(){
                if (!pronto || !player) {
                    return;
                }

                const atual = Number(player.getCurrentTime ? player.getCurrentTime() : 0) || 0;
                const duracao = Number(player.getDuration ? player.getDuration() : 0) || 0;
                if (duracao > 0) {
                    duracaoConhecida = Math.max(duracaoConhecida, duracao);
                    atualizarResumoConteudo(duracao);
                }

                if (estaReproduzindo() && !videoConcluido) {
                    const toleranciaLocal = 5;
                    if (atual <= (progressoContinuo + toleranciaLocal)) {
                        progressoContinuo = Math.max(progressoContinuo, atual);
                    }
                    const percentualContinuo = duracaoConhecida > 0 ? (progressoContinuo / duracaoConhecida) * 100 : 0;
                    atualizarProgressoModuloVisual(percentualContinuo);
                    if (progressoContinuo >= progressoEnviado + 4) {
                        agendarSalvamentoVideo();
                    }
                }

                if (tempoAtual) {
                    tempoAtual.textContent = formatarTempo(atual);
                }
                if (tempoTotal) {
                    tempoTotal.textContent = formatarTempo(duracao);
                }
                if (resumoDuracaoVideo && !resumoTemDuracaoManual && duracao > 0) {
                    resumoDuracaoVideo.textContent = formatarTempo(duracao);
                }

                if (linhaTempo && !arrastandoTempo) {
                    const percentual = duracao > 0 ? (atual / duracao) * 100 : 0;
                    linhaTempo.value = String(percentual);
                    atualizarFaixa(linhaTempo, percentual);
                }
            }

            function iniciarAtualizacaoTempo(){
                clearInterval(intervaloTempo);
                atualizarTempo();
                intervaloTempo = setInterval(atualizarTempo, 350);
            }

            function pararAtualizacaoTempo(){
                clearInterval(intervaloTempo);
                intervaloTempo = null;
                atualizarTempo();
            }

            function reproduzirOuPausar(){
                if (!pronto || !player) {
                    return;
                }

                root.classList.remove('is-ended');
                if (telaFinal) {
                    telaFinal.setAttribute('aria-hidden', 'true');
                }

                if (estaReproduzindo()) {
                    player.pauseVideo();
                } else {
                    player.playVideo();
                }
            }

            function alterarTempo(segundos){
                if (!pronto || !player || typeof player.seekTo !== 'function') {
                    return;
                }

                const atual = Number(player.getCurrentTime ? player.getCurrentTime() : 0) || 0;
                const duracao = Number(player.getDuration ? player.getDuration() : 0) || 0;
                const destino = Math.max(0, duracao > 0 ? Math.min(duracao, atual + segundos) : atual + segundos);
                player.seekTo(destino, true);
                atualizarTempo();
                mostrarControles();
            }

            function atualizarSom(){
                if (!pronto || !player) {
                    return;
                }

                const mudo = typeof player.isMuted === 'function' ? player.isMuted() : false;
                const volume = mudo ? 0 : (Number(player.getVolume ? player.getVolume() : 100) || 0);

                if (controleVolume) {
                    controleVolume.value = String(volume);
                    atualizarFaixa(controleVolume, volume);
                }

                if (mudo || volume === 0) {
                    trocarIcone(botaoMudo, 'volume-x', 'Ativar som');
                } else if (volume < 50) {
                    trocarIcone(botaoMudo, 'volume-1', 'Desativar som');
                } else {
                    trocarIcone(botaoMudo, 'volume-2', 'Desativar som');
                }
            }

            function alternarMudo(){
                if (!pronto || !player) {
                    return;
                }

                const mudo = typeof player.isMuted === 'function' ? player.isMuted() : false;
                const volume = Number(player.getVolume ? player.getVolume() : 100) || 0;

                if (mudo || volume === 0) {
                    player.unMute();
                    player.setVolume(Math.max(1, ultimoVolume || 100));
                } else {
                    ultimoVolume = volume;
                    player.mute();
                }

                atualizarSom();
                mostrarControles();
            }

            function alterarVelocidade(){
                if (!pronto || !player || typeof player.setPlaybackRate !== 'function') {
                    return;
                }

                const preferencias = [1, 1.25, 1.5, 1.75, 2, 0.75];
                const disponiveis = typeof player.getAvailablePlaybackRates === 'function'
                    ? player.getAvailablePlaybackRates().map(Number)
                    : preferencias;
                const opcoes = preferencias.filter(function(taxa){
                    return disponiveis.includes(taxa);
                });

                if (!opcoes.length) {
                    return;
                }

                const atual = Number(player.getPlaybackRate ? player.getPlaybackRate() : 1) || 1;
                const indice = opcoes.indexOf(atual);
                const proxima = opcoes[(indice + 1) % opcoes.length];
                player.setPlaybackRate(proxima);
                if (botaoVelocidade) {
                    botaoVelocidade.textContent = String(proxima).replace('.', ',') + 'x';
                }
                mostrarControles();
            }

            function alternarTelaCheia(){
                const elementoTelaCheia = document.fullscreenElement || document.webkitFullscreenElement;
                if (elementoTelaCheia) {
                    const sair = document.exitFullscreen || document.webkitExitFullscreen;
                    if (sair) {
                        sair.call(document);
                    }
                    return;
                }

                const entrar = root.requestFullscreen || root.webkitRequestFullscreen;
                if (entrar) {
                    entrar.call(root);
                }
            }

            function atualizarTelaCheia(){
                const ativo = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
                root.classList.toggle('is-fullscreen', ativo);
                trocarIcone(
                    botaoTelaCheia,
                    ativo ? 'minimize' : 'maximize',
                    ativo ? 'Sair da tela cheia' : 'Tela cheia'
                );
                mostrarControles();
            }

            function mostrarFeedbackCentral(icone, manterVisivel){
                if (temporizadorFeedbackCentral) {
                    window.clearTimeout(temporizadorFeedbackCentral);
                    temporizadorFeedbackCentral = null;
                }

                trocarIcone(botaoCentral, icone, icone === 'pause' ? 'Pausar' : 'Reproduzir');
                root.classList.add('is-center-feedback');

                if (!manterVisivel) {
                    temporizadorFeedbackCentral = window.setTimeout(function(){
                        root.classList.remove('is-center-feedback');
                    }, 720);
                }
            }

            function aoMudarEstado(evento){
                const estado = Number(evento && evento.data);
                root.classList.toggle('is-playing', estado === 1);
                root.classList.toggle('is-buffering', estado === 3);

                if (estado === 1) {
                    root.classList.add('is-started');
                    root.classList.remove('is-ended');
                    if (telaFinal) {
                        telaFinal.setAttribute('aria-hidden', 'true');
                    }
                    trocarIcone(botaoPlay, 'pause', 'Pausar');
                    mostrarFeedbackCentral('pause', false);
                    iniciarAtualizacaoTempo();
                    agendarOcultacao();
                    return;
                }

                trocarIcone(botaoPlay, 'play', 'Reproduzir');
                root.classList.remove('is-controls-hidden', 'is-center-feedback');

                if (estado === 0) {
                    if (duracaoConhecida > 0 && progressoContinuo >= Math.max(1, duracaoConhecida - 4)) {
                        progressoContinuo = duracaoConhecida;
                    }
                    salvarProgressoVideo(true, false);
                    pararAtualizacaoTempo();
                    root.classList.remove('is-center-feedback');
                    root.classList.add('is-ended');
                    if (telaFinal) {
                        telaFinal.setAttribute('aria-hidden', 'false');
                    }
                    return;
                }

                if (estado === 2) {
                    salvarProgressoVideo(false, false);
                    mostrarFeedbackCentral('play', true);
                    pararAtualizacaoTempo();
                    return;
                }

                if (estado === 5 || estado === -1) {
                    root.classList.remove('is-center-feedback');
                    trocarIcone(botaoCentral, 'play', 'Reproduzir');
                    pararAtualizacaoTempo();
                }
            }

            function aoPlayerPronto(evento){
                player = evento.target;
                pronto = true;
                root.classList.add('is-ready');
                root.classList.remove('is-buffering');

                if (controleVolume) {
                    const volumeInicial = Number(controleVolume.value) || 100;
                    player.setVolume(volumeInicial);
                    atualizarFaixa(controleVolume, volumeInicial);
                }

                const duracaoPlayer = Number(player.getDuration ? player.getDuration() : 0) || 0;
                if (duracaoPlayer > 0) {
                    duracaoConhecida = Math.max(duracaoConhecida, duracaoPlayer);
                    atualizarResumoConteudo(duracaoPlayer);
                }
                if (videoConcluido) {
                    marcarAulaConcluidaVisualmente();
                } else {
                    const percentualInicial = duracaoConhecida > 0 ? (progressoContinuo / duracaoConhecida) * 100 : (Number(configVideo.percentual) || 0);
                    atualizarProgressoModuloVisual(percentualInicial);
                }

                atualizarSom();
                atualizarTempo();
                renderizarIcones();
            }

            function aoPlayerErro(){
                pronto = false;
                root.classList.remove('is-buffering', 'is-playing');
                root.classList.add('is-ended');
                if (telaFinal) {
                    telaFinal.setAttribute('aria-hidden', 'false');
                    const tituloErro = telaFinal.querySelector('strong');
                    const textoBotao = botaoRepetir ? botaoRepetir.querySelector('span') : null;
                    if (tituloErro) {
                        tituloErro.textContent = 'Não foi possível reproduzir esta aula';
                    }
                    if (textoBotao) {
                        textoBotao.textContent = 'Tentar novamente';
                    }
                }
            }

            function criarPlayer(){
                if (!window.YT || !window.YT.Player || player) {
                    return;
                }

                player = new window.YT.Player('gabaritandoYoutubePlayer', {
                    host:window.location.protocol + '//www.youtube.com',
                    videoId:videoId,
                    width:'1920',
                    height:'1080',
                    playerVars:{
                        autoplay:0,
                        modestbranding:1,
                        rel:0,
                        showinfo:0,
                        controls:0,
                        disablekb:1,
                        enablejsapi:1,
                        fs:0,
                        iv_load_policy:3,
                        playsinline:1,
                        origin:window.location.origin
                    },
                    events:{
                        onReady:aoPlayerPronto,
                        onStateChange:aoMudarEstado,
                        onError:aoPlayerErro
                    }
                });
            }

            function carregarApiPlayer(){
                if (window.YT && window.YT.Player) {
                    criarPlayer();
                    return;
                }

                const callbackAnterior = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = function(){
                    if (typeof callbackAnterior === 'function') {
                        callbackAnterior();
                    }
                    criarPlayer();
                };

                if (!document.getElementById('gabaritandoPlayerApi')) {
                    const script = document.createElement('script');
                    script.id = 'gabaritandoPlayerApi';
                    script.src = 'https://www.youtube.com/iframe_api';
                    script.async = true;
                    document.head.appendChild(script);
                }
            }

            [botaoCentral, botaoPlay, camadaClique].forEach(function(elemento){
                if (elemento) {
                    elemento.addEventListener('click', reproduzirOuPausar);
                }
            });

            if (botaoVoltar) {
                botaoVoltar.addEventListener('click', function(){ alterarTempo(-10); });
            }
            if (botaoAvancar) {
                botaoAvancar.addEventListener('click', function(){ alterarTempo(10); });
            }
            if (botaoMudo) {
                botaoMudo.addEventListener('click', alternarMudo);
            }
            if (botaoVelocidade) {
                botaoVelocidade.addEventListener('click', alterarVelocidade);
            }
            if (botaoTelaCheia) {
                botaoTelaCheia.addEventListener('click', alternarTelaCheia);
            }
            if (botaoRepetir) {
                botaoRepetir.addEventListener('click', function(){
                    if (!pronto || !player) {
                        window.location.reload();
                        return;
                    }
                    root.classList.remove('is-ended');
                    player.seekTo(0, true);
                    player.playVideo();
                });
            }

            if (linhaTempo) {
                linhaTempo.addEventListener('input', function(){
                    arrastandoTempo = true;
                    atualizarFaixa(linhaTempo, linhaTempo.value);
                    const duracao = pronto && player && player.getDuration ? Number(player.getDuration()) || 0 : 0;
                    if (tempoAtual && duracao > 0) {
                        tempoAtual.textContent = formatarTempo(duracao * (Number(linhaTempo.value) / 100));
                    }
                });

                linhaTempo.addEventListener('change', function(){
                    if (pronto && player) {
                        const duracao = Number(player.getDuration ? player.getDuration() : 0) || 0;
                        player.seekTo(duracao * (Number(linhaTempo.value) / 100), true);
                    }
                    arrastandoTempo = false;
                    atualizarTempo();
                    mostrarControles();
                });
            }

            if (controleVolume) {
                controleVolume.addEventListener('input', function(){
                    if (!pronto || !player) {
                        return;
                    }
                    const volume = Math.max(0, Math.min(100, Number(controleVolume.value) || 0));
                    atualizarFaixa(controleVolume, volume);
                    ultimoVolume = volume > 0 ? volume : ultimoVolume;
                    player.unMute();
                    player.setVolume(volume);
                    if (volume === 0) {
                        player.mute();
                    }
                    atualizarSom();
                    mostrarControles();
                });
            }

            if (controles) {
                controles.addEventListener('click', function(evento){
                    evento.stopPropagation();
                });
                controles.addEventListener('mousemove', mostrarControles);
                controles.addEventListener('touchstart', mostrarControles, {passive:true});
            }

            root.addEventListener('mousemove', mostrarControles);
            root.addEventListener('mouseenter', mostrarControles);
            root.addEventListener('mouseleave', agendarOcultacao);
            root.addEventListener('touchstart', mostrarControles, {passive:true});
            root.addEventListener('focusin', mostrarControles);

            root.addEventListener('keydown', function(evento){
                const alvo = evento.target;
                if (alvo && alvo.tagName === 'INPUT') {
                    return;
                }

                if (evento.code === 'Space' || evento.key === 'k') {
                    evento.preventDefault();
                    reproduzirOuPausar();
                } else if (evento.key === 'ArrowLeft') {
                    evento.preventDefault();
                    alterarTempo(-10);
                } else if (evento.key === 'ArrowRight') {
                    evento.preventDefault();
                    alterarTempo(10);
                } else if (evento.key.toLowerCase() === 'm') {
                    evento.preventDefault();
                    alternarMudo();
                } else if (evento.key.toLowerCase() === 'f') {
                    evento.preventDefault();
                    alternarTelaCheia();
                }
            });

            document.addEventListener('fullscreenchange', atualizarTelaCheia);
            document.addEventListener('webkitfullscreenchange', atualizarTelaCheia);

            atualizarFaixa(linhaTempo, 0);
            atualizarFaixa(controleVolume, controleVolume ? controleVolume.value : 100);
            renderizarIcones();
            document.addEventListener('visibilitychange', function(){
                if (document.visibilityState === 'hidden') {
                    salvarProgressoVideo(false, true);
                }
            });
            window.addEventListener('pagehide', function(){
                salvarProgressoVideo(false, true);
            });

            carregarApiPlayer();
        })();

        (function controlarLeitorPdf(){
            const config = window.aulasConfig || {};
            const pdfConfig = config.pdf || {};
            const abrir = document.getElementById('abrirPdfAula');
            const modal = document.getElementById('pdfViewerModal');
            const fechar = document.getElementById('fecharPdfAula');
            const titulo = document.getElementById('pdfViewerTitle');
            const canvas = document.getElementById('pdfViewerCanvas');
            const corpo = document.getElementById('pdfViewerBody');
            const status = document.getElementById('pdfViewerStatus');
            const statusTexto = document.getElementById('pdfViewerStatusText');
            const paginaCampo = document.getElementById('pdfPaginaAtual');
            const totalTexto = document.getElementById('pdfTotalPaginas');
            const anterior = document.getElementById('pdfPaginaAnterior');
            const proxima = document.getElementById('pdfProximaPagina');
            const menosZoom = document.getElementById('pdfMenosZoom');
            const maisZoom = document.getElementById('pdfMaisZoom');
            const zoomLabel = document.getElementById('pdfZoomLabel');
            const download = document.getElementById('pdfViewerDownload');
            const downloadsMarcados = Array.from(document.querySelectorAll('.pdf-download-watermarked'));
            const logoMarcaDaguaUrl = 'img/logo.png';
            const textoMarcaDagua = 'VENDA NÃO AUTORIZADA';

            if (!abrir || !modal || !canvas || !corpo) {
                return;
            }

            const pdfUrl = String(abrir.dataset.pdfUrl || pdfConfig.url || '').trim();
            const pdfNome = String(abrir.dataset.pdfName || pdfConfig.nome || 'PDF da aula').trim();
            const pdfChave = String(abrir.dataset.pdfKey || pdfConfig.chave || '').trim();
            const storageKey = String(pdfConfig.storageKey || ('gabaritando_pdf_' + pdfChave));
            const endpoint = window.location.pathname
                + '?curso_id=' + encodeURIComponent(config.cursoId || 0)
                + '&modulo_id=' + encodeURIComponent(config.moduloId || 0)
                + '&aula_id=' + encodeURIComponent(config.aulaId || 0);

            let documento = null;
            let paginaAtual = Math.max(1, Number(pdfConfig.pagina) || 1);
            let totalPaginas = Math.max(0, Number(pdfConfig.totalPaginas) || 0);
            let zoom = Math.max(.5, Math.min(3, Number(pdfConfig.zoom) || 1));
            let renderizando = false;
            let renderPendente = false;
            let tarefaRender = null;
            let modalAberto = false;
            let salvarTimer = null;
            let ultimoFoco = null;
            let resizeTimer = null;

            function lerLocal(){
                if (!storageKey) return null;
                try {
                    const valor = JSON.parse(localStorage.getItem(storageKey) || 'null');
                    return valor && typeof valor === 'object' ? valor : null;
                } catch (erro) {
                    return null;
                }
            }

            function salvarLocal(){
                if (!storageKey) return;
                try {
                    localStorage.setItem(storageKey, JSON.stringify({
                        pagina:paginaAtual,
                        totalPaginas:totalPaginas,
                        zoom:zoom,
                        atualizadoEm:Date.now()
                    }));
                } catch (erro) {
                    // O banco continua sendo usado mesmo se o navegador bloquear o armazenamento local.
                }
            }

            function salvarServidor(imediato){
                if (!pdfChave || !config.aulaId) return;
                clearTimeout(salvarTimer);

                const executar = function(){
                    const dados = new FormData();
                    dados.append('acao', 'salvar_pdf_progresso');
                    dados.append('pdf_chave', pdfChave);
                    dados.append('pagina', String(paginaAtual));
                    dados.append('total_paginas', String(totalPaginas));
                    dados.append('zoom', String(zoom));

                    fetch(endpoint, {
                        method:'POST',
                        body:dados,
                        credentials:'same-origin',
                        headers:{'X-Requested-With':'XMLHttpRequest'},
                        keepalive:true
                    }).catch(function(){
                        // O progresso já ficou salvo localmente; uma falha de rede não interrompe a leitura.
                    });
                };

                if (imediato) {
                    executar();
                } else {
                    salvarTimer = setTimeout(executar, 450);
                }
            }

            function salvarProgresso(imediato){
                salvarLocal();
                salvarServidor(Boolean(imediato));
            }

            function mostrarStatus(mensagem, erro){
                if (!status || !statusTexto) return;
                status.hidden = false;
                statusTexto.textContent = mensagem;
                const spinner = status.querySelector('.pdf-viewer-spinner');
                if (spinner) spinner.style.display = erro ? 'none' : '';
            }

            function ocultarStatus(){
                if (status) status.hidden = true;
            }

            function atualizarControles(){
                if (paginaCampo) {
                    paginaCampo.value = String(paginaAtual);
                    paginaCampo.max = String(Math.max(1, totalPaginas));
                }
                if (totalTexto) totalTexto.textContent = String(totalPaginas || 0);
                if (anterior) anterior.disabled = paginaAtual <= 1;
                if (proxima) proxima.disabled = totalPaginas <= 0 || paginaAtual >= totalPaginas;
                if (zoomLabel) zoomLabel.textContent = Math.round(zoom * 100) + '%';
                if (menosZoom) menosZoom.disabled = zoom <= .5;
                if (maisZoom) maisZoom.disabled = zoom >= 3;
            }

            async function renderizarPagina(){
                if (!documento || renderizando) {
                    renderPendente = true;
                    return;
                }

                renderizando = true;
                renderPendente = false;
                paginaAtual = Math.max(1, Math.min(totalPaginas || 1, paginaAtual));
                atualizarControles();
                mostrarStatus('Carregando página ' + paginaAtual + '...', false);

                try {
                    if (tarefaRender && typeof tarefaRender.cancel === 'function') {
                        try { tarefaRender.cancel(); } catch (erro) {}
                    }

                    const pagina = await documento.getPage(paginaAtual);
                    const viewportBase = pagina.getViewport({scale:1});
                    const modoMobile = window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
                    const margemHorizontal = modoMobile ? 10 : 34;
                    const larguraDisponivel = Math.max(240, corpo.clientWidth - margemHorizontal);
                    const escalaResponsiva = Math.min(zoom, larguraDisponivel / viewportBase.width);
                    const viewport = pagina.getViewport({scale:Math.max(.25, escalaResponsiva)});
                    const proporcaoTela = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
                    const contexto = canvas.getContext('2d', {alpha:false});

                    canvas.width = Math.floor(viewport.width * proporcaoTela);
                    canvas.height = Math.floor(viewport.height * proporcaoTela);
                    canvas.style.width = Math.floor(viewport.width) + 'px';
                    canvas.style.height = Math.floor(viewport.height) + 'px';

                    tarefaRender = pagina.render({
                        canvasContext:contexto,
                        viewport:viewport,
                        transform:proporcaoTela === 1 ? null : [proporcaoTela, 0, 0, proporcaoTela, 0, 0]
                    });
                    await tarefaRender.promise;
                    ocultarStatus();
                    document.dispatchEvent(new CustomEvent('gab:pdf-pagina', {detail: {pagina: paginaAtual}}));
                    corpo.scrollTop = 0;
                    corpo.scrollLeft = 0;
                    salvarProgresso(false);
                } catch (erro) {
                    if (!erro || erro.name !== 'RenderingCancelledException') {
                        mostrarStatus('Não foi possível exibir esta página do PDF.', true);
                    }
                } finally {
                    renderizando = false;
                    tarefaRender = null;
                    if (renderPendente) {
                        renderPendente = false;
                        renderizarPagina();
                    }
                }
            }

            async function carregarDocumento(){
                if (documento) {
                    renderizarPagina();
                    return;
                }

                if (!window.pdfjsLib || typeof window.pdfjsLib.getDocument !== 'function') {
                    mostrarStatus('O leitor de PDF não conseguiu carregar. Atualize a página e tente novamente.', true);
                    return;
                }

                mostrarStatus('Carregando PDF...', false);
                try {
                    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    documento = await window.pdfjsLib.getDocument({url:pdfUrl}).promise;
                    totalPaginas = Number(documento.numPages) || 0;
                    paginaAtual = Math.max(1, Math.min(totalPaginas || 1, paginaAtual));
                    atualizarControles();
                    renderizarPagina();
                } catch (erro) {
                    mostrarStatus('Não foi possível abrir este PDF dentro da página.', true);
                }
            }

            function abrirModal(){
                if (!pdfUrl) return;

                const local = lerLocal();
                if (local) {
                    const paginaLocal = Math.max(1, Number(local.pagina) || 1);
                    const zoomLocal = Math.max(.5, Math.min(3, Number(local.zoom) || 1));
                    if (!pdfConfig.pagina || paginaLocal !== 1) paginaAtual = paginaLocal;
                    zoom = zoomLocal;
                }

                ultimoFoco = document.activeElement;
                modalAberto = true;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('pdf-viewer-open');
                if (titulo) titulo.textContent = pdfNome || 'PDF da aula';
                if (download) {
                    download.dataset.pdfUrl = pdfUrl;
                    download.dataset.pdfName = pdfNome || 'material.pdf';
                }
                atualizarControles();
                carregarDocumento();
                setTimeout(function(){ if (fechar) fechar.focus(); }, 70);
            }

            function fecharModal(){
                if (!modalAberto) return;
                salvarProgresso(true);
                modalAberto = false;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('pdf-viewer-open');
                if (ultimoFoco && typeof ultimoFoco.focus === 'function') ultimoFoco.focus();
            }

            function irParaPagina(novaPagina){
                if (!documento) return;
                const destino = Math.max(1, Math.min(totalPaginas, Number(novaPagina) || 1));
                if (destino === paginaAtual) {
                    atualizarControles();
                    return;
                }
                paginaAtual = destino;
                renderizarPagina();
            }

            function alterarZoom(delta){
                zoom = Math.max(.5, Math.min(3, Math.round((zoom + delta) * 100) / 100));
                atualizarControles();
                renderizarPagina();
            }

            function nomePdfSeguro(nome){
                let arquivo = String(nome || 'material.pdf').trim();
                arquivo = arquivo.replace(/[\\/:*?"<>|]+/g, '-');
                if (!/\.pdf$/i.test(arquivo)) arquivo += '.pdf';
                return arquivo;
            }

            function atualizarEstadoDownload(botao, carregando){
                if (!botao) return;
                botao.disabled = Boolean(carregando);
                botao.classList.toggle('is-loading', Boolean(carregando));
                const label = botao.querySelector('.pdf-download-label');
                if (label) label.textContent = carregando ? 'Preparando...' : 'Baixar PDF';
                botao.setAttribute('aria-busy', carregando ? 'true' : 'false');
            }

            async function carregarLogoMarcaDagua(pdfDoc){
                const respostaLogo = await fetch(logoMarcaDaguaUrl, {
                    credentials:'same-origin',
                    cache:'force-cache'
                });
                if (!respostaLogo.ok) throw new Error('Logo não encontrada.');

                const bytesLogo = await respostaLogo.arrayBuffer();
                const tipo = String(respostaLogo.headers.get('content-type') || '').toLowerCase();

                if (tipo.includes('jpeg') || tipo.includes('jpg') || /\.jpe?g(?:$|\?)/i.test(logoMarcaDaguaUrl)) {
                    return pdfDoc.embedJpg(bytesLogo);
                }

                return pdfDoc.embedPng(bytesLogo);
            }

            async function baixarPdfComMarcaDagua(botao){
                const url = String(botao.dataset.pdfUrl || pdfUrl || '').trim();
                const nome = nomePdfSeguro(botao.dataset.pdfName || pdfNome);
                if (!url || botao.disabled) return;

                if (!window.PDFLib || !window.PDFLib.PDFDocument) {
                    window.alert('O gerador da marca d\'água não carregou. Atualize a página e tente novamente.');
                    return;
                }

                atualizarEstadoDownload(botao, true);

                try {
                    const respostaPdf = await fetch(url, {
                        credentials:'same-origin',
                        cache:'no-store'
                    });
                    if (!respostaPdf.ok) throw new Error('Não foi possível carregar o PDF.');

                    const bytesPdf = await respostaPdf.arrayBuffer();
                    const {PDFDocument, StandardFonts, rgb, degrees} = window.PDFLib;
                    const pdfMarcado = await PDFDocument.load(bytesPdf);
                    const fonte = await pdfMarcado.embedFont(StandardFonts.HelveticaBold);
                    const logo = await carregarLogoMarcaDagua(pdfMarcado);
                    const dimensoesLogo = logo.scale(1);

                    pdfMarcado.getPages().forEach(function(pagina){
                        const tamanho = pagina.getSize();
                        const largura = tamanho.width;
                        const altura = tamanho.height;

                        const larguraLogo = Math.min(largura * .30, 190);
                        const alturaLogo = dimensoesLogo.height * (larguraLogo / dimensoesLogo.width);
                        pagina.drawImage(logo, {
                            x:(largura - larguraLogo) / 2,
                            y:(altura - alturaLogo) / 2 + (altura * .08),
                            width:larguraLogo,
                            height:alturaLogo,
                            opacity:.12
                        });

                        const tamanhoTexto = Math.max(22, Math.min(42, largura / 13));
                        const larguraTexto = fonte.widthOfTextAtSize(textoMarcaDagua, tamanhoTexto);
                        pagina.drawText(textoMarcaDagua, {
                            x:(largura - larguraTexto) / 2,
                            y:altura * .40,
                            size:tamanhoTexto,
                            font:fonte,
                            color:rgb(.12, .12, .12),
                            opacity:.18,
                            rotate:degrees(-28)
                        });

                        const rodape = 'PROJETO GABARITANDO - VENDA NÃO AUTORIZADA';
                        const tamanhoRodape = Math.max(8, Math.min(11, largura / 52));
                        const larguraRodape = fonte.widthOfTextAtSize(rodape, tamanhoRodape);
                        pagina.drawText(rodape, {
                            x:Math.max(16, (largura - larguraRodape) / 2),
                            y:14,
                            size:tamanhoRodape,
                            font:fonte,
                            color:rgb(.16, .16, .16),
                            opacity:.48
                        });
                    });

                    const bytesMarcados = await pdfMarcado.save();
                    const blob = new Blob([bytesMarcados], {type:'application/pdf'});
                    const urlTemporaria = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = urlTemporaria;
                    link.download = nome;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function(){ URL.revokeObjectURL(urlTemporaria); }, 2500);
                } catch (erro) {
                    console.error(erro);
                    window.alert('Não foi possível gerar o PDF com a marca d\'água. Tente novamente.');
                } finally {
                    atualizarEstadoDownload(botao, false);
                }
            }

            downloadsMarcados.forEach(function(botao){
                if (!botao.dataset.pdfUrl) botao.dataset.pdfUrl = pdfUrl;
                if (!botao.dataset.pdfName) botao.dataset.pdfName = pdfNome || 'material.pdf';
                botao.addEventListener('click', function(){ baixarPdfComMarcaDagua(botao); });
            });

            abrir.addEventListener('click', abrirModal);
            if (fechar) fechar.addEventListener('click', fecharModal);
            if (anterior) anterior.addEventListener('click', function(){ irParaPagina(paginaAtual - 1); });
            if (proxima) proxima.addEventListener('click', function(){ irParaPagina(paginaAtual + 1); });
            if (menosZoom) menosZoom.addEventListener('click', function(){ alterarZoom(-.15); });
            if (maisZoom) maisZoom.addEventListener('click', function(){ alterarZoom(.15); });

            if (paginaCampo) {
                paginaCampo.addEventListener('change', function(){ irParaPagina(paginaCampo.value); });
                paginaCampo.addEventListener('keydown', function(evento){
                    if (evento.key === 'Enter') {
                        evento.preventDefault();
                        irParaPagina(paginaCampo.value);
                    }
                });
            }

            modal.addEventListener('click', function(evento){
                if (evento.target === modal) fecharModal();
            });

            document.addEventListener('keydown', function(evento){
                if (!modalAberto) return;
                if (evento.key === 'Escape') {
                    evento.preventDefault();
                    fecharModal();
                } else if (evento.key === 'ArrowLeft' || evento.key === 'PageUp') {
                    evento.preventDefault();
                    irParaPagina(paginaAtual - 1);
                } else if (evento.key === 'ArrowRight' || evento.key === 'PageDown') {
                    evento.preventDefault();
                    irParaPagina(paginaAtual + 1);
                } else if (evento.key === '+' || evento.key === '=') {
                    evento.preventDefault();
                    alterarZoom(.15);
                } else if (evento.key === '-') {
                    evento.preventDefault();
                    alterarZoom(-.15);
                }
            });

            window.addEventListener('resize', function(){
                if (!modalAberto || !documento) return;
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(renderizarPagina, 180);
            });

            window.addEventListener('beforeunload', function(){
                if (modalAberto) salvarProgresso(true);
            });

            atualizarControles();
        })();

        (function aulasAjax(){
            const config = window.aulasConfig || {};
            const endpoint = window.location.pathname + '?curso_id=' + encodeURIComponent(config.cursoId || 0) + '&modulo_id=' + encodeURIComponent(config.moduloId || 0) + '&aula_id=' + encodeURIComponent(config.aulaId || 0);

            function escapeHtml(valor){
                return String(valor || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function nl2br(valor){
                return escapeHtml(valor).replace(/\n/g, '<br>');
            }

            function enviar(dados){
                const formData = new FormData();
                Object.keys(dados).forEach(function(chave){
                    formData.append(chave, dados[chave]);
                });

                return fetch(endpoint, {
                    method:'POST',
                    body:formData,
                    credentials:'same-origin',
                    headers:{'X-Requested-With':'XMLHttpRequest'}
                }).then(async function(response){
                    let data = null;
                    try {
                        data = await response.json();
                    } catch (erro) {
                        throw new Error('Não foi possível ler a resposta do servidor.');
                    }

                    if (!response.ok || !data.ok) {
                        if (data && data.login_required) {
                            const destino = (data.login_url || config.loginUrl || 'login.php') + '?redirect=' + encodeURIComponent(window.location.href);
                            window.location.href = destino;
                            return data;
                        }

                        throw new Error((data && data.mensagem) ? data.mensagem : 'Não foi possível concluir a ação.');
                    }

                    return data;
                });
            }

            function textoAvaliacao(nota, media, total){
                nota = parseInt(nota, 10) || 0;
                media = parseFloat(media || 0) || 0;
                total = parseInt(total, 10) || 0;

                if (nota > 0) {
                    return 'Sua avaliação: ' + nota + '/5';
                }

                if (total > 0) {
                    return 'Média ' + media.toFixed(1).replace('.', ',') + '/5';
                }

                return 'Avalie a aula';
            }

            function atualizarEstrelas(valor){
                const rating = document.getElementById('aulaRating');
                if (!rating) {
                    return;
                }

                valor = parseInt(valor, 10) || 0;
                rating.dataset.rating = String(valor);
                rating.querySelectorAll('.star-btn').forEach(function(botao){
                    const estrela = parseInt(botao.dataset.star, 10) || 0;
                    const ativa = estrela <= valor;
                    botao.classList.toggle('is-active', ativa);
                    botao.textContent = ativa ? '★' : '☆';
                    botao.setAttribute('aria-checked', estrela === valor ? 'true' : 'false');
                });
            }

            function inicializarAvaliacao(){
                const rating = document.getElementById('aulaRating');
                const status = document.getElementById('ratingStatus');
                if (!rating) {
                    return;
                }

                const botoes = Array.from(rating.querySelectorAll('.star-btn'));
                const notaInicial = parseInt(rating.dataset.rating || '0', 10) || 0;
                atualizarEstrelas(notaInicial);

                botoes.forEach(function(botao){
                    const nota = parseInt(botao.dataset.star, 10) || 0;

                    botao.addEventListener('mouseenter', function(){
                        if (!botao.disabled) {
                            atualizarEstrelas(nota);
                        }
                    });

                    botao.addEventListener('focus', function(){
                        if (!botao.disabled) {
                            atualizarEstrelas(nota);
                        }
                    });

                    botao.addEventListener('click', function(){
                        if (!config.aulaId || botao.disabled) {
                            return;
                        }

                        rating.classList.add('is-saving');
                        botoes.forEach(function(item){ item.disabled = true; });
                        if (status) {
                            status.textContent = 'Salvando avaliação...';
                        }

                        enviar({acao:'avaliar_aula', nota:nota}).then(function(data){
                            rating.dataset.media = String(data.media || 0);
                            rating.dataset.total = String(data.total || 0);
                            atualizarEstrelas(data.nota || nota);
                            if (status) {
                                status.textContent = textoAvaliacao(data.nota || nota, data.media || 0, data.total || 0);
                            }
                        }).catch(function(erro){
                            alert(erro.message || 'Erro ao salvar avaliação.');
                            atualizarEstrelas(parseInt(rating.dataset.rating || '0', 10) || 0);
                            if (status) {
                                status.textContent = textoAvaliacao(parseInt(rating.dataset.rating || '0', 10) || 0, rating.dataset.media || 0, rating.dataset.total || 0);
                            }
                        }).finally(function(){
                            rating.classList.remove('is-saving');
                            botoes.forEach(function(item){ item.disabled = false; });
                        });
                    });
                });

                rating.addEventListener('mouseleave', function(){
                    atualizarEstrelas(parseInt(rating.dataset.rating || '0', 10) || 0);
                });
            }

            inicializarAvaliacao();

            function marcarVisualmenteAssistida(aulaId){
                const item = document.querySelector('.lesson-item[data-aula-id="' + aulaId + '"]');
                if (!item) {
                    return;
                }

                item.classList.add('is-watched');
                const dot = item.querySelector('.lesson-dot');
                if (dot && !dot.querySelector('.lesson-check')) {
                    dot.innerHTML = '<i data-lucide="check" class="lesson-check"></i>';
                }

                if (window.aulasRenderizarIcones) {
                    window.aulasRenderizarIcones();
                }
            }

            function atualizarProgresso(percentual){
                const valor = parseInt(percentual, 10) || 0;
                const texto = document.getElementById('progressPercent');
                const ring = document.querySelector('.progress-ring');

                if (texto) {
                    texto.textContent = valor + '%';
                }

                if (ring) {
                    ring.style.setProperty('--progress', valor);
                }
            }

            const btnAssistido = document.getElementById('marcarAssistido');
            if (btnAssistido && btnAssistido.dataset.videoRequired !== '1') {
                btnAssistido.addEventListener('click', function(){
                    if (!config.aulaId || btnAssistido.disabled) {
                        return;
                    }

                    const textoOriginal = btnAssistido.innerHTML;
                    btnAssistido.disabled = true;
                    btnAssistido.textContent = 'Salvando...';

                    enviar({acao:'marcar_assistido'}).then(function(data){
                        btnAssistido.classList.add('is-watched');
                        btnAssistido.innerHTML = '<i data-lucide="check"></i>Assistido';
                        marcarVisualmenteAssistida(data.aula_id || config.aulaId);
                        // Ciclo no automático: teoria feita -> já leva para as questões do assunto.
                        (function(){
                            var c = window.gabCiclo || {};
                            if (!c.topico || document.getElementById('cicloProximo')) { return; }
                            var faltam = Math.max(0, (c.min || 5) - (c.res || 0));
                            var box = document.createElement('div');
                            box.id = 'cicloProximo'; box.className = 'ciclo-prox'; box.setAttribute('role', 'status');
                            box.innerHTML = '<strong>✓ Teoria concluída!</strong> <span>' + (faltam > 0 ? 'Passo 2: resolva ' + faltam + ' questões para liberar o próximo assunto.' : 'Passo 2: continue nas questões até a meta de 50.') + '</span>'
                                + '<a class="ciclo-bt" href="banco-questoes.php?topico_id=' + encodeURIComponent(c.topico) + '">✅ Resolver questões agora</a>';
                            btnAssistido.closest('.aula-actions').insertAdjacentElement('afterend', box);
                            var cicloSec = document.querySelector('.ciclo-passos li'); if (cicloSec) { cicloSec.className = 'feito'; }
                            box.querySelector('a').focus();
                        })();
                        atualizarProgresso(data.progresso || 0);
                        if (window.aulasRenderizarIcones) {
                            window.aulasRenderizarIcones();
                        }
                    }).catch(function(erro){
                        alert(erro.message || 'Erro ao marcar aula como assistida.');
                        btnAssistido.innerHTML = textoOriginal;
                    }).finally(function(){
                        btnAssistido.disabled = false;
                    });
                });
            }

            (function controlarPesquisaAulas(){
                const botaoPesquisa = document.getElementById('abrirPesquisaAulas');
                const painelPesquisa = document.getElementById('lessonSearchWrap');
                const campoPesquisa = document.getElementById('lessonSearchInput');
                const botaoLimpar = document.getElementById('lessonSearchClear');
                const vazioPesquisa = document.getElementById('lessonSearchEmpty');
                const itens = Array.from(document.querySelectorAll('.lesson-list .lesson-item:not(.is-disabled)'));

                if (!botaoPesquisa || !painelPesquisa || !campoPesquisa || !itens.length) {
                    return;
                }

                function normalizar(texto){
                    return String(texto || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .trim();
                }

                function filtrarAulas(){
                    const termo = normalizar(campoPesquisa.value);
                    let encontrados = 0;

                    itens.forEach(function(item){
                        const texto = normalizar(item.textContent);
                        const mostrar = termo === '' || texto.includes(termo);
                        item.classList.toggle('is-filter-hidden', !mostrar);
                        if (mostrar) {
                            encontrados++;
                        }
                    });

                    if (vazioPesquisa) {
                        vazioPesquisa.classList.toggle('is-visible', termo !== '' && encontrados === 0);
                    }
                }

                function abrirPesquisa(){
                    painelPesquisa.classList.add('is-open');
                    painelPesquisa.setAttribute('aria-hidden', 'false');
                    botaoPesquisa.setAttribute('aria-expanded', 'true');
                    setTimeout(function(){ campoPesquisa.focus(); }, 60);
                }

                function fecharPesquisa(){
                    painelPesquisa.classList.remove('is-open');
                    painelPesquisa.setAttribute('aria-hidden', 'true');
                    botaoPesquisa.setAttribute('aria-expanded', 'false');
                    campoPesquisa.value = '';
                    filtrarAulas();
                }

                botaoPesquisa.addEventListener('click', function(){
                    if (painelPesquisa.classList.contains('is-open')) {
                        fecharPesquisa();
                        return;
                    }

                    abrirPesquisa();
                });

                campoPesquisa.addEventListener('input', filtrarAulas);

                if (botaoLimpar) {
                    botaoLimpar.addEventListener('click', function(){
                        campoPesquisa.value = '';
                        filtrarAulas();
                        campoPesquisa.focus();
                    });
                }

                document.addEventListener('keydown', function(event){
                    if (event.key === 'Escape' && painelPesquisa.classList.contains('is-open')) {
                        fecharPesquisa();
                        botaoPesquisa.focus();
                    }
                });
            })();

            window.aulasCriarTopico = function(form, fecharModal){
                if (!config.aulaId) {
                    alert('Abra uma aula antes de criar um tópico.');
                    return;
                }

                const botao = form.querySelector('button[type="submit"]');
                const titulo = form.querySelector('[name="titulo"]');
                const texto = form.querySelector('[name="texto"]');
                const visibilidade = form.querySelector('[name="visibilidade"]:checked');
                const textoBotao = botao ? botao.textContent : '';

                if (!titulo || !texto || titulo.value.trim() === '' || texto.value.trim() === '') {
                    alert('Preencha o título e o texto do tópico.');
                    return;
                }

                if (botao) {
                    botao.disabled = true;
                    botao.textContent = 'Criando...';
                }

                enviar({
                    acao:'criar_topico',
                    titulo:titulo.value,
                    texto:texto.value,
                    visibilidade:visibilidade ? visibilidade.value : 'todos'
                }).then(function(data){
                    const lista = document.getElementById('topicosLista');
                    const vazio = document.getElementById('topicosVazio');
                    const topico = data.topico || {};
                    const label = topico.visibilidade === 'monitores' ? 'Apenas monitores' : 'Todos';

                    if (vazio) {
                        vazio.remove();
                    }

                    if (lista) {
                        const card = document.createElement('article');
                        card.className = 'topic-card';
                        card.innerHTML = '' +
                            '<header class="topic-card__head">' +
                                '<strong class="topic-card__title">' + escapeHtml(topico.titulo) + '</strong>' +
                                '<small class="topic-card__meta">' + escapeHtml(topico.usuario_nome || 'Aluno') + ' • ' + escapeHtml(topico.criado_em || 'Agora') + '</small>' +
                            '</header>' +
                            '<p class="topic-card__text">' + nl2br(topico.texto) + '</p>' +
                            '<span class="topic-card__badge">' + escapeHtml(label) + '</span>';
                        lista.prepend(card);
                    }

                    form.reset();
                    if (typeof fecharModal === 'function') {
                        fecharModal();
                    }
                }).catch(function(erro){
                    alert(erro.message || 'Erro ao criar tópico.');
                }).finally(function(){
                    if (botao) {
                        botao.disabled = false;
                        botao.textContent = textoBotao || 'Criar tópico';
                    }
                });
            };
        })();

        (function controlarPopupTopico(){
            const abrir = document.getElementById('abrirTopico');
            const modal = document.getElementById('modalTopico');
            const fechar = document.getElementById('fecharTopico');
            const form = document.getElementById('formTopico');

            if (!abrir || !modal || !fechar || !form) {
                return;
            }

            function abrirModal(){
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('topic-modal-open');

                const primeiroCampo = modal.querySelector('input[name="titulo"]');
                if (primeiroCampo) {
                    setTimeout(function(){ primeiroCampo.focus(); }, 80);
                }
            }

            function fecharModal(){
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('topic-modal-open');
                abrir.focus();
            }

            abrir.addEventListener('click', abrirModal);
            fechar.addEventListener('click', fecharModal);

            modal.addEventListener('click', function(event){
                if (event.target === modal) {
                    fecharModal();
                }
            });

            document.addEventListener('keydown', function(event){
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    fecharModal();
                }
            });

            form.addEventListener('submit', function(event){
                event.preventDefault();
                if (window.aulasCriarTopico) {
                    window.aulasCriarTopico(form, fecharModal);
                }
            });
        })();
    </script>

<script>
// Material da aula: marca-texto salvo na conta, letra maior/menor, recolher seções.
(function () {
    var raiz = document.getElementById('aulaDescricao');
    if (!raiz) { return; }
    var texto = document.getElementById('aulaDescricaoTexto');
    var status = document.getElementById('aulaDescricaoStatus');
    var aulaId = raiz.getAttribute('data-aula-id');
    var marcas = [];
    var tamanho = 16;
    try { tamanho = parseInt(localStorage.getItem('gab_ad_fs') || '16', 10) || 16; } catch (e) {}
    raiz.style.setProperty('--ad-fs', tamanho + 'px');

    function avisar(m) { status.textContent = m; if (m) { setTimeout(function () { if (status.textContent === m) { status.textContent = ''; } }, 2500); } }

    // Envolve a n-ésima ocorrência do trecho t em <mark>, atravessando tags.
    function aplicar(t, n) {
        var nos = [], w = document.createTreeWalker(texto, NodeFilter.SHOW_TEXT, null), no, tudo = '';
        while ((no = w.nextNode())) { nos.push({no: no, ini: tudo.length}); tudo += no.nodeValue; }
        var pos = -1, k = 0;
        do { pos = tudo.indexOf(t, pos + 1); k++; } while (pos !== -1 && k <= n);
        if (pos === -1) { return false; }
        var fim = pos + t.length;
        nos.forEach(function (it) {
            var a = it.ini, b = it.ini + it.no.nodeValue.length;
            if (b <= pos || a >= fim || !it.no.nodeValue.trim()) { return; }
            var r = document.createRange();
            r.setStart(it.no, Math.max(0, pos - a));
            r.setEnd(it.no, Math.min(it.no.nodeValue.length, fim - a));
            var m = document.createElement('mark');
            m.className = 'ad-m'; m.setAttribute('data-t', t); m.setAttribute('data-n', n);
            m.title = 'Toque para remover a marcação';
            r.surroundContents(m);
        });
        return true;
    }
    function ocorrencia(t, antesDe) {
        var r = document.createRange(); r.setStart(texto, 0); r.setEnd(antesDe.startContainer, antesDe.startOffset);
        var antes = r.toString(), n = 0, p = -1;
        while ((p = antes.indexOf(t, p + 1)) !== -1) { n++; }
        return n;
    }
    function salvar() {
        fetch('api/aula-marcacoes.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, credentials: 'same-origin', body: JSON.stringify({aula_id: aulaId, marcacoes: marcas})})
            .then(function (r) { return r.json(); })
            .then(function (j) { avisar(j.ok ? 'Marcações salvas.' : (j.erro || 'Não foi possível salvar.')); })
            .catch(function () { avisar('Sem conexão: a marcação aparece agora, mas pode não ficar salva.'); });
    }
    function remover(t, n) {
        marcas = marcas.filter(function (m) { return !(m.t === t && m.n === n); });
        texto.querySelectorAll('mark.ad-m').forEach(function (m) {
            if (m.getAttribute('data-t') === t && +m.getAttribute('data-n') === n) { m.replaceWith.apply(m, Array.from(m.childNodes)); }
        });
        texto.normalize(); salvar();
    }

    raiz.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-ad]');
        var mk = ev.target.closest('mark.ad-m');
        if (mk) { remover(mk.getAttribute('data-t'), +mk.getAttribute('data-n')); return; }
        if (!b) { return; }
        var acao = b.getAttribute('data-ad');
        if (acao === 'marcar') {
            var sel = window.getSelection();
            if (!sel || sel.isCollapsed || !texto.contains(sel.anchorNode) || !texto.contains(sel.focusNode)) { avisar('Primeiro selecione um trecho do material.'); return; }
            var t = sel.toString().replace(/\s+$/,'').replace(/^\s+/,'');
            if (t.length < 2) { avisar('Selecione um trecho maior.'); return; }
            var rg = sel.getRangeAt(0), n = ocorrencia(t, rg);
            sel.removeAllRanges();
            if (aplicar(t, n)) { marcas.push({t: t, n: n}); salvar(); }
        } else if (acao === 'limpar') {
            marcas = []; texto.querySelectorAll('mark.ad-m').forEach(function (m) { m.replaceWith.apply(m, Array.from(m.childNodes)); }); texto.normalize(); salvar();
        } else if (acao === 'maior' || acao === 'menor') {
            tamanho = Math.max(14, Math.min(22, tamanho + (acao === 'maior' ? 1 : -1)));
            raiz.style.setProperty('--ad-fs', tamanho + 'px');
            try { localStorage.setItem('gab_ad_fs', String(tamanho)); } catch (e) {}
        } else if (acao === 'recolher') {
            var abrir = b.textContent.indexOf('Abrir') === 0;
            texto.querySelectorAll('.ad-abrir').forEach(function (x) { x.textContent = abrir ? 'Toque para fechar' : 'Toque para abrir'; });
            texto.querySelectorAll('details.ad-secao').forEach(function (d) { d.open = abrir; });
            b.textContent = abrir ? 'Recolher tudo' : 'Abrir tudo';
        }
    });

    // Botão "Marcar" flutuante: aparece colado no trecho selecionado, onde quer que o aluno esteja na tela.
    var flut = document.createElement('button');
    flut.type = 'button'; flut.className = 'ad-flutua'; flut.setAttribute('data-ad', 'marcar'); flut.hidden = true;
    flut.innerHTML = '<span class="ad-marca-ico"></span>Marcar';
    raiz.appendChild(flut);
    flut.addEventListener('mousedown', function (e) { e.preventDefault(); });
    flut.addEventListener('click', function () { flut.hidden = true; });
    var flutTimer = null;
    function posicionarFlutuante() {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || !sel.rangeCount || !texto.contains(sel.anchorNode) || !texto.contains(sel.focusNode) || sel.toString().trim().length < 2) { flut.hidden = true; return; }
        var r = sel.getRangeAt(0).getBoundingClientRect();
        flut.hidden = false;
        var w = flut.offsetWidth || 100, emCima = r.top > 70;
        // No celular o menu do sistema (copiar/colar) fica em cima da seleção: o botão vai embaixo.
        if ('ontouchstart' in window) { emCima = false; }
        flut.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2)) + 'px';
        flut.style.top = (emCima ? r.top - 48 : Math.min(window.innerHeight - 52, r.bottom + 12)) + 'px';
    }
    document.addEventListener('selectionchange', function () { clearTimeout(flutTimer); flutTimer = setTimeout(posicionarFlutuante, 180); });
    window.addEventListener('scroll', function () { if (!flut.hidden) { posicionarFlutuante(); } }, {passive: true});

    texto.querySelectorAll('details.ad-secao').forEach(function (d) { d.addEventListener('toggle', function () { var x = d.querySelector('.ad-abrir'); if (x) { x.textContent = 'Toque para abrir'; } }); });
    fetch('api/aula-marcacoes.php?aula_id=' + encodeURIComponent(aulaId), {credentials: 'same-origin'})
        .then(function (r) { return r.json(); })
        .then(function (j) { (j.marcacoes || []).forEach(function (m) { if (aplicar(m.t, m.n)) { marcas.push(m); } }); })
        .catch(function () {});
})();
</script>

<script>
// Podcast da aula: gera no primeiro clique (em partes) e guarda para todos.
(function () {
    var raiz = document.getElementById('aulaPodcast');
    if (!raiz) { return; }
    var aulaId = raiz.getAttribute('data-aula-id');
    var btn = document.getElementById('podBtn'), txt = document.getElementById('podBtnTxt');
    var prog = document.getElementById('podProg'), barra = document.getElementById('podBarra'), msg = document.getElementById('podMsg');
    var player = document.getElementById('podPlayer'), audio = document.getElementById('podAudio');
    var relogio = null, inicio = 0, base = '';
    function contar(t) { base = t; inicio = Date.now(); clearInterval(relogio); relogio = setInterval(function () { msg.textContent = base + ' ' + Math.round((Date.now() - inicio) / 1000) + 's'; }, 1000); msg.textContent = base; }
    function api(corpo) {
        var ctl = window.AbortController ? new AbortController() : null;
        var limite = setTimeout(function () { if (ctl) { ctl.abort(); } }, 150000);
        return fetch('api/podcast.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(corpo), signal: ctl ? ctl.signal : undefined})
            .then(function (r) {
                return r.text().then(function (t) {
                    var j; try { j = JSON.parse(t); } catch (e) { throw new Error(r.status >= 500 ? 'O servidor demorou demais ou falhou (erro ' + r.status + ').' : 'Resposta inesperada do servidor.'); }
                    if (!r.ok) { throw new Error(j.erro || 'Erro ao gerar.'); }
                    return j;
                });
            })
            .catch(function (e) { if (e.name === 'AbortError') { throw new Error('Demorou mais de 2 minutos e meio.'); } throw e; })
            .finally(function () { clearTimeout(limite); });
    }
    function mostrar(url) {
        prog.hidden = true; btn.hidden = true; player.hidden = false;
        audio.src = url;
        if ('mediaSession' in navigator) { navigator.mediaSession.metadata = new MediaMetadata({title: document.title.replace(/ \|.*$/, ''), artist: 'Podcast Gabaritando'}); }
    }
    function partes(total, feitas) {
        if (feitas >= total) { return; }
        barra.style.width = Math.round(10 + 90 * feitas / total) + '%';
        contar('Gravando o áudio: parte ' + (feitas + 1) + ' de ' + total + ' (só na primeira vez; depois fica pronto para todos)…');
        return api({aula_id: aulaId, acao: 'parte', n: feitas}).then(function (j) {
            if (j.status === 'pronto') { clearInterval(relogio); barra.style.width = '100%'; mostrar(j.url); audio.play().catch(function () {}); return; }
            return partes(j.partes, j.partes_ok);
        });
    }
    function situacao() { return fetch('api/podcast.php?aula_id=' + encodeURIComponent(aulaId), {credentials: 'same-origin'}).then(function (r) { return r.json(); }); }
    var recuperacoes = 0;
    // Se algo falhar no caminho (ex.: a hospedagem cortou a resposta), confere a
    // situação real: pronto -> mostra o player; ainda gerando -> continua de onde parou.
    function recuperar(e) {
        return situacao().then(function (s) {
            if (s.status === 'pronto' && s.url) { clearInterval(relogio); barra.style.width = '100%'; mostrar(s.url); return; }
            if (s.status === 'gerando' && s.partes > 0 && recuperacoes < 4) { recuperacoes++; return new Promise(function (ok) { setTimeout(ok, 4000); }).then(function () { return partes(s.partes, s.partes_ok).catch(recuperar); }); }
            throw e;
        }).catch(function () {
            clearInterval(relogio); msg.textContent = (e && e.message ? e.message : 'Não foi possível gerar agora.') + ' Toque no botão para tentar de novo.'; btn.disabled = false; txt.textContent = 'Tentar de novo';
        });
    }
    function comecar() {
        btn.disabled = true; txt.textContent = 'Preparando…'; prog.hidden = false; contar('Escrevendo o roteiro da conversa…'); barra.style.width = '5%'; recuperacoes = 0;
        api({aula_id: aulaId, acao: 'iniciar'}).then(function (j) {
            if (j.status === 'pronto') { return situacao().then(function (s) { mostrar(s.url); }); }
            return partes(j.partes, j.partes_ok || 0);
        }).catch(recuperar);
    }
    btn.addEventListener('click', comecar);
    raiz.querySelectorAll('.pod-vel button').forEach(function (b) {
        b.setAttribute('aria-pressed', b.getAttribute('data-v') === '1' ? 'true' : 'false');
        b.addEventListener('click', function () { audio.playbackRate = parseFloat(b.getAttribute('data-v')); raiz.querySelectorAll('.pod-vel button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); });
    });
    fetch('api/podcast.php?aula_id=' + encodeURIComponent(aulaId), {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (s) {
        if (s.status === 'pronto' && s.url) { btn.hidden = true; player.hidden = false; audio.src = s.url; return; }
        // Alguém já está gerando (ou a página foi recarregada no meio): continua sozinho.
        if (s.status === 'gerando' && s.partes > 0) { btn.disabled = true; txt.textContent = 'Preparando…'; prog.hidden = false; barra.style.width = Math.round(10 + 90 * s.partes_ok / s.partes) + '%'; partes(s.partes, s.partes_ok).catch(recuperar); }
    }).catch(function () {});
})();
</script>
<script>
// Videoaula IA: slides sincronizados com a narração + paradas com pergunta.
(function () {
    var va = document.getElementById('videoaulaIA'), abrir = document.getElementById('vaAbrir');
    if (!va || !abrir) { return; }
    var aulaId = va.getAttribute('data-aula-id'), dados = null, idx = 0, tocando = false, feitas = {}, vel = 1;
    var palco = document.getElementById('vaPalco'), audio = document.getElementById('vaAudio'), parada = document.getElementById('vaParada');
    var btPlay = document.getElementById('vaPlay'), prog = document.getElementById('vaProg'), cont = document.getElementById('vaCont');
    var ger = document.getElementById('vaGerando'), gerBarra = document.getElementById('vaGerandoBarra'), gerMsg = document.getElementById('vaGerandoMsg');
    var logo = va.querySelector('.va-logo');
    function api(corpo) {
        return fetch('api/videoaula.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(corpo)})
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) { throw new Error(j.erro || 'Erro ao gerar a videoaula.'); } return j; }); });
    }
    function situacao() { return fetch('api/videoaula.php?aula_id=' + encodeURIComponent(aulaId), {credentials: 'same-origin'}).then(function (r) { return r.json(); }); }
    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function progresso(ok, total, msg) { ger.hidden = false; gerBarra.style.width = Math.max(5, Math.round(ok * 100 / Math.max(1, total))) + '%'; gerMsg.textContent = msg; }
    function gerar() {
        abrir.disabled = true;
        progresso(0, 1, 'O Antigão está lendo o PDF e montando os slides… (cerca de 1 minuto)');
        return api({aula_id: aulaId, acao: 'iniciar'}).then(function proximo(r) {
            if (r.status === 'pronto') { return situacao(); }
            var total = r.partes || 1, ok = r.partes_ok || 0;
            progresso(ok, total, 'Gravando a narração: slide ' + Math.min(ok + 1, total) + ' de ' + total + '…');
            return api({aula_id: aulaId, acao: 'parte', n: ok}).then(proximo);
        });
    }
    abrir.addEventListener('click', function () {
        if (dados) { mostrar(); return; }
        abrir.disabled = true;
        situacao().then(function (s) {
            if (s.status === 'pronto') { return s; }
            return gerar();
        }).then(function (s) {
            if (!s || s.status !== 'pronto') { throw new Error('A videoaula ainda não ficou pronta. Tente de novo em instantes.'); }
            dados = s; ger.hidden = true; mostrar();
        }).catch(function (e) {
            ger.hidden = false; gerBarra.style.width = '0'; gerMsg.textContent = e.message + ' Enquanto isso, leia o material.';
        }).then(function () { abrir.disabled = false; });
    });
    var intro = document.querySelector('.aula-somente-pdf');
    function mostrar() {
        va.hidden = false; if (intro) { intro.hidden = true; }
        va.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        prog.innerHTML = dados.slides.map(function () { return '<span><i></i></span>'; }).join('');
        idx = 0; ir(0, true);
    }
    function marcarProgresso() {
        Array.prototype.forEach.call(prog.children, function (el, i) { el.className = i < idx ? 'feito' : ''; if (i !== idx) { el.firstChild.style.width = ''; } });
        cont.textContent = (idx + 1) + '/' + dados.slides.length;
    }
    function desenhar() {
        var sl = dados.slides[idx], n = (sl.topicos || []).length + (sl.destaque ? 1 : 0);
        var passo = Math.max(1.2, (audio.duration && isFinite(audio.duration) ? audio.duration / vel : 8) / (n + 1.5));
        var itens = (sl.topicos || []).map(function (t, i) { return '<li style="animation-delay:' + ((i + 1) * passo).toFixed(1) + 's">' + esc(t) + '</li>'; }).join('');
        var dest = sl.destaque ? '<div class="va-slide__dest" style="animation-delay:' + (((sl.topicos || []).length + 1) * passo).toFixed(1) + 's">💡 ' + esc(sl.destaque) + '</div>' : '';
        if (idx === 0) {
            palco.innerHTML = '<div class="va-slide va-abertura"><img src="' + esc(logo.getAttribute('src')) + '" alt="Projeto Gabaritando"><small>Projeto Gabaritando · Videoaula com o Antigão</small><h3>' + esc(sl.titulo) + '</h3><ul>' + itens + '</ul>' + dest + '</div>';
        } else {
            palco.innerHTML = '<div class="va-slide"><div class="va-slide__emoji" aria-hidden="true">' + esc(sl.emoji) + '</div><h3>' + esc(sl.titulo) + '</h3><ul>' + itens + '</ul>' + dest + '</div>';
        }
        if (!tocando) { palco.querySelectorAll('li,.va-slide__dest').forEach(function (el) { el.style.animationDelay = '0s'; }); }
    }
    function ir(n, comecar) {
        idx = Math.max(0, Math.min(dados.slides.length - 1, n));
        parada.hidden = true;
        marcarProgresso();
        audio.src = dados.slides[idx].audio; audio.playbackRate = vel;
        var desenhou = false;
        var feito = function () { if (!desenhou) { desenhou = true; desenhar(); } };
        audio.onloadedmetadata = feito; setTimeout(feito, 700);
        if (comecar) { tocar(); }
    }
    function tocar() { tocando = true; btPlay.textContent = '⏸'; btPlay.setAttribute('aria-label', 'Pausar'); audio.playbackRate = vel; audio.play().catch(function () { pausar(); }); }
    function pausar() { tocando = false; btPlay.textContent = '▶'; btPlay.setAttribute('aria-label', 'Reproduzir'); audio.pause(); }
    audio.addEventListener('timeupdate', function () {
        var el = prog.children[idx]; if (el && audio.duration) { el.firstChild.style.width = (audio.currentTime * 100 / audio.duration) + '%'; }
    });
    audio.addEventListener('ended', function () {
        var p = -1;
        (dados.paradas || []).forEach(function (q, i) { if (q.apos_slide === idx + 1 && !feitas[i]) { p = i; } });
        if (p >= 0) { mostrarParada(p); return; }
        if (idx < dados.slides.length - 1) { ir(idx + 1, true); } else { fim(); }
    });
    function mostrarParada(i) {
        pausar();
        var q = dados.paradas[i];
        parada.innerHTML = '<div class="va-parada__box"><span class="va-parada__selo">⏸ Parada do combatente · responda para continuar</span><p id="vaParadaPerg">' + esc(q.pergunta) + '</p>'
            + q.alternativas.map(function (a, k) { return '<button type="button" class="va-alt" data-k="' + k + '">' + String.fromCharCode(65 + k) + ') ' + esc(a) + '</button>'; }).join('') + '<div id="vaParadaFim"></div></div>';
        parada.hidden = false;
        var primeiro = parada.querySelector('.va-alt'); if (primeiro) { primeiro.focus(); }
        parada.querySelectorAll('.va-alt').forEach(function (b) {
            b.addEventListener('click', function () {
                var k = parseInt(b.getAttribute('data-k'), 10);
                parada.querySelectorAll('.va-alt').forEach(function (x) { x.disabled = true; });
                api({aula_id: aulaId, acao: 'responder', parada: i, alt: k}).then(function (r) {
                    feitas[i] = true;
                    parada.querySelector('[data-k="' + r.certa + '"]').classList.add('certa');
                    if (!r.correta) { b.classList.add('errada'); }
                    document.getElementById('vaParadaFim').innerHTML = '<div class="va-exp"><strong>' + (r.correta ? '✅ Isso, combatente!' : '❌ Quase! Veja o porquê:') + '</strong> ' + esc(r.explicacao) + '</div><button type="button" class="va-continuar" id="vaContinuar">Continuar a aula ▶</button>';
                    var c = document.getElementById('vaContinuar'); c.focus();
                    c.addEventListener('click', function () { if (idx < dados.slides.length - 1) { ir(idx + 1, true); } else { fim(); } });
                }).catch(function (e) { parada.querySelectorAll('.va-alt').forEach(function (x) { x.disabled = false; }); alert(e.message); });
            });
        });
    }
    function fim() {
        pausar(); idx = dados.slides.length - 1; marcarProgresso(); prog.lastChild.className = 'feito';
        parada.innerHTML = '<div class="va-parada__box" style="text-align:center;justify-items:center"><img src="' + esc(logo.getAttribute('src')) + '" alt="Projeto Gabaritando" style="height:70px;width:auto"><p><strong>Videoaula concluída! 🎯</strong></p><p style="font-size:15px;color:#c9c9cf">Agora leia o material e resolva pelo menos 5 questões do assunto. Fé na missão: a farda é questão de tempo!</p><button type="button" class="va-continuar" id="vaRever">↺ Assistir de novo</button></div>';
        parada.hidden = false;
        document.getElementById('vaRever').addEventListener('click', function () { feitas = {}; ir(0, true); });
    }
    btPlay.addEventListener('click', function () { if (!parada.hidden) { return; } tocando ? pausar() : tocar(); });
    document.getElementById('vaAnt').addEventListener('click', function () { ir(idx - 1, tocando); });
    document.getElementById('vaProx').addEventListener('click', function () { ir(idx + 1, tocando); });
    document.getElementById('vaVel').addEventListener('click', function () {
        var l = [1, 1.25, 1.5, 2]; vel = l[(l.indexOf(vel) + 1) % l.length]; audio.playbackRate = vel; this.textContent = String(vel).replace('.', ',') + 'x';
    });
    document.getElementById('vaTela').addEventListener('click', function () {
        if (document.fullscreenElement) { document.exitFullscreen(); } else if (va.requestFullscreen) { va.requestFullscreen().catch(function () {}); }
    });
    document.getElementById('vaFechar').addEventListener('click', function () { pausar(); if (document.fullscreenElement) { document.exitFullscreen(); } va.hidden = true; if (intro) { intro.hidden = false; } abrir.focus(); });
    va.addEventListener('keydown', function (e) { if (e.key === ' ' && e.target === va) { e.preventDefault(); btPlay.click(); } });
})();
</script>
<script>
// Marca-texto no PDF: o aluno arrasta sobre o trecho; as marcações ficam salvas na conta (por página).
(function () {
    var camada = document.getElementById('pdfMarcaCamada'), bt = document.getElementById('pdfMarcarBtn'), dica = document.getElementById('pdfMarcaDica');
    var cfg = window.aulasConfig || {}, aulaId = cfg.aulaId || 0;
    if (!camada || !bt || !aulaId) { return; }
    var marcas = [], pagina = 1, ativo = false, inicio = null, atual = null;
    function salvar() {
        fetch('api/aula-marcacoes.php?tipo=pdf', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({aula_id: aulaId, marcacoes: marcas})}).catch(function () {});
    }
    function desenhar() {
        camada.innerHTML = '';
        marcas.forEach(function (m, i) {
            if (m.p !== pagina) { return; }
            var d = document.createElement('div');
            d.className = 'pdf-marca'; d.setAttribute('data-i', i);
            d.style.left = (m.x * 100) + '%'; d.style.top = (m.y * 100) + '%'; d.style.width = (m.w * 100) + '%'; d.style.height = (m.h * 100) + '%';
            camada.appendChild(d);
        });
    }
    function ponto(e) { var r = camada.getBoundingClientRect(); return {x: Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), y: Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))}; }
    bt.addEventListener('click', function () {
        ativo = !ativo; bt.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        camada.classList.toggle('ativa', ativo); dica.hidden = !ativo;
    });
    camada.addEventListener('pointerdown', function (e) {
        if (!ativo) { return; }
        var alvo = e.target.closest('.pdf-marca');
        if (alvo) { marcas.splice(+alvo.getAttribute('data-i'), 1); desenhar(); salvar(); return; }
        e.preventDefault(); camada.setPointerCapture(e.pointerId);
        inicio = ponto(e);
        atual = document.createElement('div'); atual.className = 'pdf-marca'; camada.appendChild(atual);
    });
    camada.addEventListener('pointermove', function (e) {
        if (!inicio || !atual) { return; }
        var p = ponto(e), x = Math.min(p.x, inicio.x), y = Math.min(p.y, inicio.y);
        atual.style.left = x * 100 + '%'; atual.style.top = y * 100 + '%';
        atual.style.width = Math.abs(p.x - inicio.x) * 100 + '%'; atual.style.height = Math.max(Math.abs(p.y - inicio.y), .012) * 100 + '%';
    });
    function terminar(e) {
        if (!inicio) { return; }
        var p = ponto(e), w = Math.abs(p.x - inicio.x), h = Math.max(Math.abs(p.y - inicio.y), .012);
        if (w > .01) {
            marcas.push({p: pagina, x: +Math.min(p.x, inicio.x).toFixed(4), y: +(Math.abs(p.y - inicio.y) < .012 ? inicio.y - .006 : Math.min(p.y, inicio.y)).toFixed(4), w: +w.toFixed(4), h: +h.toFixed(4)});
            salvar();
        }
        inicio = null; atual = null; desenhar();
    }
    camada.addEventListener('pointerup', terminar);
    camada.addEventListener('pointercancel', function () { inicio = null; atual = null; desenhar(); });
    document.addEventListener('gab:pdf-pagina', function (e) { pagina = e.detail.pagina; desenhar(); });
    fetch('api/aula-marcacoes.php?tipo=pdf&aula_id=' + encodeURIComponent(aulaId), {credentials: 'same-origin'})
        .then(function (r) { return r.json(); }).then(function (j) { marcas = j.marcacoes || []; desenhar(); }).catch(function () {});
})();
</script>
</body>
</html>
