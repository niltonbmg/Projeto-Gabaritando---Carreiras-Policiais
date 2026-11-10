<?php
// forum-duvidas.php
// Fórum de dúvidas conectado ao banco: tópicos gerais + tópicos criados dentro das aulas.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('esc')) {
    function esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('forum_env')) {
    function forum_env(string $path): array
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

if (!function_exists('forum_db')) {
    function forum_db(): PDO
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

        $env = forum_env(__DIR__ . '/.env');
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

if (!function_exists('forum_usuario_id')) {
    function forum_usuario_id(): int
    {
        $candidatos = [
            $_SESSION['usuario_id'] ?? null,
            $_SESSION['user_id'] ?? null,
            $_SESSION['id_usuario'] ?? null,
            $_SESSION['id'] ?? null,
            $_SESSION['usuario']['id'] ?? null,
            $_SESSION['user']['id'] ?? null,
        ];

        foreach ($candidatos as $valor) {
            if (is_numeric($valor) && (int)$valor > 0) {
                return (int)$valor;
            }
        }

        return 0;
    }
}

if (!function_exists('forum_usuario_nome')) {
    function forum_usuario_nome(): string
    {
        $candidatos = [
            $_SESSION['usuario']['nome'] ?? null,
            $_SESSION['user']['nome'] ?? null,
            $_SESSION['nome_usuario'] ?? null,
            $_SESSION['usuario_nome'] ?? null,
            $_SESSION['nome'] ?? null,
        ];

        foreach ($candidatos as $valor) {
            $valor = trim((string)$valor);
            if ($valor !== '') {
                return $valor;
            }
        }

        return 'Aluno';
    }
}

if (!function_exists('forum_tabela_existe')) {
    function forum_tabela_existe(PDO $pdo, string $tabela): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela
            " );
            $stmt->execute([':tabela' => $tabela]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('forum_garantir_tabelas')) {
    function forum_garantir_tabelas(PDO $pdo): void
    {
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS forum_duvidas_topicos (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                autor_nome VARCHAR(160) DEFAULT NULL,\n                titulo VARCHAR(180) NOT NULL,\n                mensagem TEXT DEFAULT NULL,\n                categoria VARCHAR(180) NOT NULL DEFAULT 'Outro',\n                privado TINYINT(1) NOT NULL DEFAULT 0,\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_forum_duvidas_usuario (usuario_id),\n                KEY idx_forum_duvidas_status_data (status, criado_em),\n                KEY idx_forum_duvidas_categoria (categoria),\n                KEY idx_forum_duvidas_privado (privado)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS forum_duvidas_respostas (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                topico_id BIGINT UNSIGNED NOT NULL,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                autor_nome VARCHAR(160) DEFAULT NULL,\n                mensagem TEXT NOT NULL,\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_forum_respostas_topico (topico_id),\n                KEY idx_forum_respostas_usuario (usuario_id),\n                KEY idx_forum_respostas_status (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");
    }
}

$usuarioId = forum_usuario_id();
$usuarioNome = forum_usuario_nome();
$filtroAtual = $_GET['filtro'] ?? 'todos';
$ordemAtual = $_GET['ordem'] ?? 'recentes';
$somenteMeusTopicos = isset($_GET['meus_topicos']);
$somenteParticipei = isset($_GET['participei']);
$erroBanco = '';
$topicos = [];
$categoriasFormulario = ['Outro'];

if (!in_array($filtroAtual, ['todos', 'privados', 'abertos'], true)) {
    $filtroAtual = 'todos';
}

if (!in_array($ordemAtual, ['recentes', 'antigos', 'respondidos'], true)) {
    $ordemAtual = 'recentes';
}

try {
    $pdo = forum_db();
    forum_garantir_tabelas($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'novo_topico') {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $categoria = trim((string)($_POST['categoria'] ?? 'Outro'));
        $mensagem = trim((string)($_POST['mensagem'] ?? ''));
        $privado = isset($_POST['privado']) ? 1 : 0;

        if ($titulo !== '') {
            if ($categoria === '') {
                $categoria = 'Outro';
            }

            $stmtNovo = $pdo->prepare("\n                INSERT INTO forum_duvidas_topicos\n                    (usuario_id, autor_nome, titulo, mensagem, categoria, privado, status, criado_em)\n                VALUES\n                    (:usuario_id, :autor_nome, :titulo, :mensagem, :categoria, :privado, 'ativo', NOW())\n            ");
            $stmtNovo->execute([
                ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                ':autor_nome' => $usuarioNome,
                ':titulo' => $titulo,
                ':mensagem' => $mensagem,
                ':categoria' => $categoria,
                ':privado' => $privado,
            ]);

            header('Location: forum-duvidas.php');
            exit;
        }
    }

    if (forum_tabela_existe($pdo, 'admin_curso_aulas')) {
        $stmtCategorias = $pdo->query("\n            SELECT titulo\n            FROM admin_curso_aulas\n            WHERE titulo IS NOT NULL AND titulo <> ''\n            ORDER BY titulo ASC\n            LIMIT 80\n        ");
        foreach ($stmtCategorias->fetchAll() as $cat) {
            $label = 'Aula: ' . trim((string)($cat['titulo'] ?? ''));
            if ($label !== 'Aula:' && !in_array($label, $categoriasFormulario, true)) {
                $categoriasFormulario[] = $label;
            }
        }
    }

    $where = ["t.status = 'ativo'"];
    $params = [];

    if ($filtroAtual === 'privados') {
        $where[] = 't.privado = 1';
    } elseif ($filtroAtual === 'abertos') {
        $where[] = 't.privado = 0';
    }

    if ($somenteMeusTopicos && $usuarioId > 0) {
        $where[] = 't.usuario_id = :meu_usuario_id';
        $params[':meu_usuario_id'] = $usuarioId;
    }

    if ($somenteParticipei && $usuarioId > 0) {
        $where[] = "(t.usuario_id = :participei_usuario_id OR EXISTS (SELECT 1 FROM forum_duvidas_respostas frp WHERE frp.topico_id = t.id AND frp.usuario_id = :participei_usuario_id AND frp.status = 'ativo'))";
        $params[':participei_usuario_id'] = $usuarioId;
    }

    $orderBy = $ordemAtual === 'antigos' ? 't.criado_em ASC, t.id ASC' : 't.criado_em DESC, t.id DESC';
    if ($ordemAtual === 'respondidos') {
        $orderBy = 'respostas_total DESC, t.criado_em DESC, t.id DESC';
    }

    $sql = "\n        SELECT\n            t.*,\n            COALESCE(NULLIF(u.nome, ''), NULLIF(t.autor_nome, ''), 'Aluno') AS autor_nome_final,\n            (SELECT COUNT(*) FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo') AS respostas_total,\n            (SELECT fr.mensagem FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultima_resposta,\n            (SELECT COALESCE(NULLIF(fr.autor_nome, ''), 'Admin') FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultimo_autor,\n            (SELECT fr.criado_em FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultima_resposta_em\n        FROM forum_duvidas_topicos t\n        LEFT JOIN usuarios u ON u.id = t.usuario_id\n        WHERE " . implode(' AND ', $where) . "\n        ORDER BY {$orderBy}\n        LIMIT 100\n    ";
    $stmtTopicos = $pdo->prepare($sql);
    $stmtTopicos->execute($params);

    foreach ($stmtTopicos->fetchAll() as $row) {
        $respostas = (int)($row['respostas_total'] ?? 0);
        $criadoTimestamp = strtotime((string)($row['criado_em'] ?? '')) ?: time();
        $categoria = trim((string)($row['categoria'] ?? 'Outro'));

        $topicos[] = [
            'titulo' => (string)$row['titulo'],
            'autor' => (string)$row['autor_nome_final'],
            'data' => date('d/m/Y H:i', $criadoTimestamp),
            'timestamp' => $criadoTimestamp,
            'respostas_total' => $respostas,
            'respostas' => $respostas > 0 ? ($respostas . ($respostas === 1 ? ' resposta' : ' respostas')) : 'Nenhuma resposta',
            'tipo' => ((int)$row['privado'] === 1) ? 'Privado' : '',
            'icone' => ((int)$row['privado'] === 1) ? 'users-round' : '',
            'aula' => str_starts_with($categoria, 'Aula:') ? $categoria : '',
            'aula_link' => 'aulas.php',
            'categoria' => str_starts_with($categoria, 'Aula:') ? '' : $categoria,
            'destaque' => false,
            'ultima_resposta' => (string)($row['ultima_resposta'] ?? ''),
            'ultimo_autor' => (string)($row['ultimo_autor'] ?? ''),
            'ultima_resposta_em' => (string)($row['ultima_resposta_em'] ?? ''),
        ];
    }

    if (forum_tabela_existe($pdo, 'aulas_forum_topicos')) {
        $whereAula = ["aft.status = 'ativo'"];
        $paramsAula = [];

        if ($filtroAtual === 'privados') {
            $whereAula[] = "aft.visibilidade = 'monitores'";
        } elseif ($filtroAtual === 'abertos') {
            $whereAula[] = "aft.visibilidade <> 'monitores'";
        }

        if ($somenteMeusTopicos && $usuarioId > 0) {
            $whereAula[] = 'aft.usuario_id = :meu_usuario_id_aula';
            $paramsAula[':meu_usuario_id_aula'] = $usuarioId;
        }

        if ($somenteParticipei && $usuarioId > 0) {
            $whereAula[] = 'aft.usuario_id = :participei_usuario_id_aula';
            $paramsAula[':participei_usuario_id_aula'] = $usuarioId;
        }

        $sqlAulas = "\n            SELECT\n                aft.*,\n                COALESCE(NULLIF(u.nome, ''), 'Aluno') AS autor_nome_final,\n                c.nome AS curso_nome,\n                m.titulo AS modulo_titulo,\n                a.titulo AS aula_titulo,\n                (SELECT COUNT(*) FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo') AS respostas_total,\n                (SELECT ar.mensagem FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultima_resposta,\n                (SELECT COALESCE(NULLIF(ar.autor_nome, ''), 'Admin') FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultimo_autor,\n                (SELECT ar.criado_em FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultima_resposta_em\n            FROM aulas_forum_topicos aft\n            LEFT JOIN usuarios u ON u.id = aft.usuario_id\n            LEFT JOIN admin_cursos c ON c.id = aft.curso_id\n            LEFT JOIN admin_curso_modulos m ON m.id = aft.modulo_id\n            LEFT JOIN admin_curso_aulas a ON a.id = aft.aula_id\n            WHERE " . implode(' AND ', $whereAula) . "\n            ORDER BY aft.criado_em DESC, aft.id DESC\n            LIMIT 100\n        ";
        $stmtAulas = $pdo->prepare($sqlAulas);
        $stmtAulas->execute($paramsAula);

        foreach ($stmtAulas->fetchAll() as $row) {
            $criadoTimestamp = strtotime((string)($row['criado_em'] ?? '')) ?: time();
            $aulaTitulo = trim((string)($row['aula_titulo'] ?? ''));
            $moduloTitulo = trim((string)($row['modulo_titulo'] ?? ''));
            $labelAula = 'Aula: ' . ($aulaTitulo !== '' ? $aulaTitulo : ($moduloTitulo !== '' ? $moduloTitulo : 'Conteúdo'));
            $cursoId = (int)($row['curso_id'] ?? 0);
            $moduloId = (int)($row['modulo_id'] ?? 0);
            $aulaId = (int)($row['aula_id'] ?? 0);
            $linkAula = 'aulas.php';
            if ($cursoId > 0 && $moduloId > 0) {
                $linkAula .= '?curso_id=' . $cursoId . '&modulo_id=' . $moduloId;
                if ($aulaId > 0) {
                    $linkAula .= '&aula_id=' . $aulaId;
                }
            }

            $respostasAula = (int)($row['respostas_total'] ?? 0);
            $topicos[] = [
                'titulo' => (string)$row['titulo'],
                'autor' => (string)$row['autor_nome_final'],
                'data' => date('d/m/Y H:i', $criadoTimestamp),
                'timestamp' => $criadoTimestamp,
                'respostas_total' => $respostasAula,
                'respostas' => $respostasAula > 0 ? ($respostasAula . ($respostasAula === 1 ? ' resposta' : ' respostas')) : 'Nenhuma resposta',
                'tipo' => ((string)($row['visibilidade'] ?? '') === 'monitores') ? 'Privado' : '',
                'icone' => ((string)($row['visibilidade'] ?? '') === 'monitores') ? 'users-round' : '',
                'aula' => $labelAula,
                'aula_link' => $linkAula,
                'categoria' => '',
                'destaque' => false,
                'ultima_resposta' => (string)($row['ultima_resposta'] ?? ''),
                'ultimo_autor' => (string)($row['ultimo_autor'] ?? ''),
                'ultima_resposta_em' => (string)($row['ultima_resposta_em'] ?? ''),
            ];
        }
    }

    usort($topicos, function (array $a, array $b) use ($ordemAtual): int {
        if ($ordemAtual === 'antigos') {
            return ($a['timestamp'] <=> $b['timestamp']);
        }

        if ($ordemAtual === 'respondidos') {
            $cmp = ((int)$b['respostas_total'] <=> (int)$a['respostas_total']);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return ($b['timestamp'] <=> $a['timestamp']);
    });
} catch (Throwable $e) {
    $erroBanco = $e->getMessage();
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
    <title>Fórum de dúvidas | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --forum-yellow:#f7be14;
            --forum-yellow-strong:#fdbd08;
            --forum-bg:#0b0b0c;
            --forum-card:#272727;
            --forum-card-2:#292929;
            --forum-card-hover:#303030;
            --forum-text:#ffffff;
            --forum-muted:#aeb4c2;
            --forum-line:#3d4048;
            --forum-blue:#2773ff;
        }

        *{
            box-sizing:border-box;
        }

        html,
        body{
            width:100%;
            min-height:100%;
        }

        body{
            margin:0;
            min-height:100vh;
            background:var(--forum-bg);
            color:var(--forum-text);
            font-family:'Ubuntu', Arial, sans-serif;
        }

        a{
            color:inherit;
            text-decoration:none;
        }

        button,
        input,
        select,
        textarea{
            font-family:inherit;
        }

        .forum-page{
            width:100%;
            min-height:calc(100vh - 96px);
            background:var(--forum-bg);
            padding:30px 30px 36px;
        }

        .forum-toolbar{
            width:100%;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin-bottom:16px;
        }

        .forum-filters{
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:14px;
        }

        .forum-filter-title{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#fff;
            font-size:1rem;
            font-weight:700;
            line-height:1;
            white-space:nowrap;
        }

        .forum-filter-title svg,
        .forum-filter-title i{
            width:18px;
            height:18px;
            color:#fff;
        }

        .forum-select-wrap{
            position:relative;
            min-width:114px;
        }

        .forum-select-wrap.is-wide{
            min-width:172px;
        }

        .forum-select{
            width:100%;
            height:38px;
            appearance:none;
            -webkit-appearance:none;
            border:1px solid #3b3f4b;
            border-radius:5px;
            background:#0d0d10;
            color:#fff;
            outline:none;
            padding:0 40px 0 14px;
            font-size:1rem;
            font-weight:500;
            cursor:pointer;
        }

        .forum-select-wrap::after{
            content:"";
            position:absolute;
            right:15px;
            top:50%;
            width:9px;
            height:9px;
            border-right:2px solid #8d929e;
            border-bottom:2px solid #8d929e;
            transform:translateY(-65%) rotate(45deg);
            pointer-events:none;
        }

        .forum-check{
            display:inline-flex;
            align-items:center;
            gap:9px;
            color:#fff;
            font-size:1rem;
            font-weight:500;
            cursor:pointer;
            user-select:none;
            white-space:nowrap;
        }

        .forum-check input{
            width:20px;
            height:20px;
            margin:0;
            appearance:none;
            -webkit-appearance:none;
            border:1px solid #555b66;
            border-radius:3px;
            background:#111217;
            position:relative;
            cursor:pointer;
        }

        .forum-check input:checked{
            border-color:var(--forum-yellow);
            background:var(--forum-yellow);
        }

        .forum-check input:checked::after{
            content:"";
            position:absolute;
            left:6px;
            top:3px;
            width:5px;
            height:10px;
            border-right:2px solid #111;
            border-bottom:2px solid #111;
            transform:rotate(45deg);
        }

        .new-topic-btn{
            height:38px;
            border:0;
            border-radius:5px;
            background:var(--forum-yellow);
            color:#fff;
            padding:0 18px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-size:1rem;
            font-weight:500;
            cursor:pointer;
            white-space:nowrap;
            transition:filter .18s ease, transform .18s ease;
        }

        .new-topic-btn:hover{
            filter:brightness(.98);
            transform:translateY(-1px);
        }

        .new-topic-btn svg,
        .new-topic-btn i{
            width:19px;
            height:19px;
            stroke-width:2.2;
        }



        .forum-alert{
            width:100%;
            margin:0 0 18px;
            padding:14px 16px;
            border-radius:8px;
            background:rgba(247,190,20,.12);
            border:1px solid rgba(247,190,20,.35);
            color:#fff;
            font-size:.95rem;
            line-height:1.4;
        }

        .forum-empty{
            width:100%;
            min-height:130px;
            background:var(--forum-card);
            border-radius:7px;
            padding:28px 20px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:10px;
            text-align:center;
            color:#fff;
        }

        .forum-empty i,
        .forum-empty svg{
            width:34px;
            height:34px;
            color:var(--forum-yellow);
        }

        .forum-empty p{
            margin:0;
            color:var(--forum-muted);
            font-size:.98rem;
        }

        .forum-list{
            display:flex;
            flex-direction:column;
            gap:19px;
        }

        .topic-card{
            width:100%;
            min-height:114px;
            background:var(--forum-card);
            border-radius:7px;
            padding:16px 20px 15px;
            display:grid;
            grid-template-columns:1fr auto;
            column-gap:22px;
            transition:background .16s ease;
        }

        .topic-card:hover{
            background:var(--forum-card-hover);
        }

        .topic-card.is-highlight{
            min-height:75px;
            align-items:center;
            margin-top:0;
        }

        .topic-main{
            min-width:0;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            gap:24px;
        }

        .topic-head{
            display:flex;
            align-items:center;
            gap:11px;
            min-width:0;
        }

        .topic-badge{
            height:25px;
            min-width:73px;
            padding:0 10px;
            border-radius:4px;
            background:rgba(39, 115, 255, .16);
            color:var(--forum-blue);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            font-size:.9rem;
            font-weight:500;
            line-height:1;
            flex:0 0 auto;
        }

        .topic-badge svg,
        .topic-badge i{
            width:15px;
            height:15px;
        }

        .topic-fire{
            width:53px;
            height:35px;
            border-radius:4px;
            background:rgba(247, 190, 20, .21);
            color:var(--forum-yellow);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
        }

        .topic-fire svg,
        .topic-fire i{
            width:20px;
            height:20px;
            fill:currentColor;
        }

        .topic-title{
            margin:0;
            color:#fff;
            font-size:1rem;
            line-height:1.35;
            font-weight:700;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .topic-meta{
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:24px;
            color:#fff;
            font-size:1rem;
            line-height:1.2;
            font-weight:700;
        }

        .topic-answer{
            color:#f3f116;
            font-weight:700;
        }


        .topic-last-reply{
            margin-top:10px;
            border:1px solid rgba(250,181,40,.22);
            border-radius:8px;
            background:rgba(250,181,40,.08);
            padding:10px 12px;
            color:#fff;
            font-size:.92rem;
            line-height:1.45;
        }

        .topic-last-reply strong{
            display:flex;
            align-items:center;
            gap:7px;
            color:var(--forum-yellow);
            font-size:.86rem;
            margin-bottom:5px;
        }

        .topic-last-reply svg,
        .topic-last-reply i{
            width:16px;
            height:16px;
        }

        .topic-side{
            display:flex;
            align-items:flex-start;
            justify-content:flex-end;
            min-width:260px;
            padding-top:4px;
        }

        .topic-category{
            display:inline-flex;
            align-items:center;
            justify-content:flex-end;
            gap:11px;
            color:#fff;
            font-size:.95rem;
            line-height:1.25;
            font-weight:700;
            white-space:nowrap;
            text-align:right;
        }

        .topic-category.is-link{
            color:#ffff23;
            text-decoration:underline;
            text-underline-offset:2px;
        }

        .topic-category::before{
            content:"";
            width:20px;
            height:20px;
            border-radius:999px;
            background:var(--forum-yellow);
            display:inline-block;
            flex:0 0 auto;
        }

        .forum-pagination{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:20px;
            margin-top:26px;
            color:#aeb4c2;
            font-size:1rem;
            font-weight:500;
        }

        .page-controls{
            display:flex;
            align-items:center;
            gap:13px;
        }

        .page-icon{
            width:18px;
            height:18px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:#737986;
            border:0;
            background:transparent;
            padding:0;
        }

        .page-current{
            width:40px;
            height:40px;
            border-radius:999px;
            border:0;
            background:rgba(247, 190, 20, .18);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:.95rem;
            font-weight:500;
        }

        .page-size{
            width:83px;
            min-width:83px;
        }

        .page-size .forum-select{
            height:38px;
            font-size:1.05rem;
        }

        .modal-backdrop{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.68);
            display:none;
            align-items:center;
            justify-content:center;
            padding:18px;
            z-index:120;
        }

        .modal-backdrop.is-open{
            display:flex;
        }

        .topic-modal{
            width:min(560px, 100%);
            border-radius:12px;
            background:#171719;
            border:1px solid rgba(255,255,255,.08);
            box-shadow:0 24px 60px rgba(0,0,0,.55);
            overflow:hidden;
        }

        .topic-modal__head{
            min-height:68px;
            padding:0 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            border-bottom:1px solid rgba(255,255,255,.08);
            background:#1f1f22;
        }

        .topic-modal__title{
            margin:0;
            font-size:1.15rem;
            font-weight:700;
            color:#fff;
        }

        .topic-modal__close{
            width:38px;
            height:38px;
            border:0;
            border-radius:10px;
            background:rgba(255,255,255,.06);
            color:#fff;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        .topic-modal__close svg,
        .topic-modal__close i{
            width:22px;
            height:22px;
        }

        .topic-modal__body{
            padding:20px;
            display:grid;
            gap:15px;
        }

        .modal-field{
            display:grid;
            gap:8px;
        }

        .modal-field label{
            color:#fff;
            font-size:.96rem;
            font-weight:700;
        }

        .modal-field input,
        .modal-field select,
        .modal-field textarea{
            width:100%;
            border:1px solid #383c46;
            border-radius:8px;
            background:#0e0e11;
            color:#fff;
            outline:none;
            font-size:1rem;
            padding:0 13px;
        }

        .modal-field input,
        .modal-field select{
            height:46px;
        }

        .modal-field textarea{
            min-height:116px;
            resize:vertical;
            padding-top:12px;
        }

        .modal-actions{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:12px;
            padding:0 20px 20px;
        }

        .modal-btn{
            height:42px;
            border:0;
            border-radius:7px;
            padding:0 16px;
            font-size:.98rem;
            font-weight:700;
            cursor:pointer;
        }

        .modal-btn.is-cancel{
            background:#2c2c30;
            color:#fff;
        }

        .modal-btn.is-save{
            background:var(--forum-yellow);
            color:#fff;
        }

        @media(max-width:960px){
            .forum-page{
                padding:22px 16px 28px;
            }

            .forum-toolbar{
                align-items:flex-start;
                flex-direction:column;
            }

            .new-topic-btn{
                align-self:flex-end;
            }

            .topic-card{
                grid-template-columns:1fr;
                row-gap:18px;
            }

            .topic-side{
                min-width:0;
                justify-content:flex-start;
                padding-top:0;
            }

            .topic-category{
                justify-content:flex-start;
                text-align:left;
                white-space:normal;
            }
        }

        @media(max-width:640px){
            .forum-page{
                min-height:calc(100vh - 80px);
                padding:18px 12px 26px;
            }

            .forum-filters{
                gap:10px;
                width:100%;
            }

            .forum-filter-title{
                width:100%;
            }

            .forum-select-wrap,
            .forum-select-wrap.is-wide{
                width:calc(50% - 5px);
                min-width:0;
            }

            .forum-check{
                width:100%;
                font-size:.94rem;
            }

            .new-topic-btn{
                width:100%;
                align-self:stretch;
            }

            .topic-card{
                min-height:auto;
                padding:15px 14px;
                gap:16px;
            }

            .topic-head{
                align-items:flex-start;
                flex-wrap:wrap;
            }

            .topic-title{
                width:100%;
                white-space:normal;
                font-size:.98rem;
            }

            .topic-meta{
                gap:9px 16px;
                font-size:.92rem;
            }

            .topic-main{
                gap:18px;
            }

            .topic-card.is-highlight{
                min-height:auto;
            }

            .forum-pagination{
                flex-wrap:wrap;
                gap:14px;
            }

            .modal-actions{
                flex-direction:column-reverse;
            }

            .modal-btn{
                width:100%;
            }
        }
    </style>
</head>
<body>
    <?php
    $barraSuperiorPath = __DIR__ . '/includes/barra-superior.php';
    if (is_file($barraSuperiorPath)) {
        include_once $barraSuperiorPath;
    } elseif (is_file(__DIR__ . '/barra-superior.php')) {
        include_once __DIR__ . '/barra-superior.php';
    }
    ?>

    <main class="forum-page">
        <?php if ($erroBanco !== ''): ?>
            <div class="forum-alert">Não foi possível carregar o fórum no banco: <?php echo esc($erroBanco); ?></div>
        <?php endif; ?>

        <form class="forum-toolbar" method="get" action="forum-duvidas.php">
            <div class="forum-filters">
                <span class="forum-filter-title">
                    <i data-lucide="funnel"></i>
                    Filtros:
                </span>

                <span class="forum-select-wrap">
                    <select class="forum-select" name="filtro" aria-label="Filtrar tópicos">
                        <option value="todos" <?php echo $filtroAtual === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="privados" <?php echo $filtroAtual === 'privados' ? 'selected' : ''; ?>>Privados</option>
                        <option value="abertos" <?php echo $filtroAtual === 'abertos' ? 'selected' : ''; ?>>Abertos</option>
                    </select>
                </span>

                <span class="forum-select-wrap is-wide">
                    <select class="forum-select" name="ordem" aria-label="Ordenar tópicos">
                        <option value="recentes" <?php echo $ordemAtual === 'recentes' ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="antigos" <?php echo $ordemAtual === 'antigos' ? 'selected' : ''; ?>>Mais antigos</option>
                        <option value="respondidos" <?php echo $ordemAtual === 'respondidos' ? 'selected' : ''; ?>>Respondidos</option>
                    </select>
                </span>

                <label class="forum-check">
                    <input type="checkbox" name="meus_topicos" value="1" <?php echo $somenteMeusTopicos ? 'checked' : ''; ?>>
                    <span>Somente meus tópicos</span>
                </label>

                <label class="forum-check">
                    <input type="checkbox" name="participei" value="1" <?php echo $somenteParticipei ? 'checked' : ''; ?>>
                    <span>Somente tópicos que participei</span>
                </label>
            </div>

            <button class="new-topic-btn" type="button" id="abrirNovoTopico">
                <i data-lucide="plus"></i>
                Novo tópico
            </button>
        </form>

        <section class="forum-list" aria-label="Lista de tópicos do fórum">
            <?php if (!$topicos): ?>
                <div class="forum-empty">
                    <i data-lucide="message-circle"></i>
                    <p>Nenhum tópico encontrado. Clique em Novo tópico para cadastrar a primeira dúvida.</p>
                </div>
            <?php else: ?>
                <?php foreach ($topicos as $topico): ?>
                    <article class="topic-card <?php echo !empty($topico['destaque']) ? 'is-highlight' : ''; ?>">
                        <div class="topic-main">
                            <div class="topic-head">
                                <?php if (!empty($topico['tipo'])): ?>
                                    <span class="topic-badge">
                                        <i data-lucide="<?php echo esc((string) $topico['icone']); ?>"></i>
                                        <?php echo esc((string) $topico['tipo']); ?>
                                    </span>
                                <?php elseif (!empty($topico['destaque'])): ?>
                                    <span class="topic-fire">
                                        <i data-lucide="flame"></i>
                                    </span>
                                <?php endif; ?>

                                <h2 class="topic-title"><?php echo esc((string) $topico['titulo']); ?></h2>
                            </div>

                            <?php if (empty($topico['destaque'])): ?>
                                <div class="topic-meta">
                                    <span><?php echo esc((string) $topico['autor']); ?></span>
                                    <span><?php echo esc((string) $topico['data']); ?></span>
                                    <span class="topic-answer"><?php echo esc((string) $topico['respostas']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($topico['ultima_resposta'])): ?>
                                <div class="topic-last-reply">
                                    <strong><i data-lucide="reply"></i>Resposta de <?php echo esc((string)($topico['ultimo_autor'] ?: 'Admin')); ?><?php echo !empty($topico['ultima_resposta_em']) ? ' • ' . esc(date('d/m/Y H:i', strtotime((string)$topico['ultima_resposta_em']) ?: time())) : ''; ?></strong>
                                    <p><?php echo nl2br(esc((string)$topico['ultima_resposta'])); ?></p>
                                </div>
                            <?php endif; ?>

                        </div>

                        <div class="topic-side">
                            <?php if (!empty($topico['aula'])): ?>
                                <a class="topic-category is-link" href="<?php echo esc((string)($topico['aula_link'] ?? 'aulas.php')); ?>"><?php echo esc((string) $topico['aula']); ?></a>
                            <?php elseif (!empty($topico['categoria'])): ?>
                                <span class="topic-category"><?php echo esc((string) $topico['categoria']); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <nav class="forum-pagination" aria-label="Paginação do fórum">
            <span><?php echo count($topicos) > 0 ? '1 - ' . count($topicos) : '0'; ?> de <?php echo count($topicos); ?></span>
            <span class="page-controls" aria-hidden="true">
                <span class="page-icon">«</span>
                <span class="page-icon">‹</span>
                <span class="page-current">1</span>
                <span class="page-icon">›</span>
                <span class="page-icon">»</span>
            </span>
            <span class="forum-select-wrap page-size">
                <select class="forum-select" aria-label="Itens por página">
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                </select>
            </span>
        </nav>
    </main>

    <div class="modal-backdrop" id="novoTopicoModal" aria-hidden="true">
        <form class="topic-modal" method="post" action="forum-duvidas.php" role="dialog" aria-modal="true" aria-labelledby="novoTopicoTitulo">
            <input type="hidden" name="acao" value="novo_topico">

            <div class="topic-modal__head">
                <h2 class="topic-modal__title" id="novoTopicoTitulo">Novo tópico</h2>
                <button class="topic-modal__close" type="button" id="fecharNovoTopico" aria-label="Fechar popup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="topic-modal__body">
                <div class="modal-field">
                    <label for="titulo">Título do tópico</label>
                    <input type="text" name="titulo" id="titulo" placeholder="Digite o título da dúvida" required>
                </div>

                <div class="modal-field">
                    <label for="categoria">Categoria</label>
                    <select name="categoria" id="categoria">
                        <?php foreach ($categoriasFormulario as $categoriaOpcao): ?>
                            <option value="<?php echo esc($categoriaOpcao); ?>"><?php echo esc($categoriaOpcao); ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array('Dúvida: Direito Processual Penal Militar', $categoriasFormulario, true)): ?>
                            <option value="Dúvida: Direito Processual Penal Militar">Dúvida: Direito Processual Penal Militar</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="modal-field">
                    <label for="mensagem">Mensagem</label>
                    <textarea name="mensagem" id="mensagem" placeholder="Escreva sua dúvida"></textarea>
                </div>

                <label class="forum-check">
                    <input type="checkbox" name="privado" value="1">
                    <span>Marcar como privado</span>
                </label>
            </div>

            <div class="modal-actions">
                <button class="modal-btn is-cancel" type="button" id="cancelarNovoTopico">Cancelar</button>
                <button class="modal-btn is-save" type="submit">Publicar tópico</button>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <script>
        (function () {
            function criarIcones() {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            criarIcones();
            if (!window.lucide) {
                const scriptLucideExistente = document.querySelector('script[src*="lucide"]');
                if (scriptLucideExistente) {
                    scriptLucideExistente.addEventListener('load', criarIcones, { once: true });
                }
            }

            const modal = document.getElementById('novoTopicoModal');
            const abrir = document.getElementById('abrirNovoTopico');
            const fechar = document.getElementById('fecharNovoTopico');
            const cancelar = document.getElementById('cancelarNovoTopico');
            const titulo = document.getElementById('titulo');

            function abrirModal() {
                if (!modal) return;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                setTimeout(function () {
                    if (titulo) titulo.focus();
                }, 80);
            }

            function fecharModal() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            if (abrir) abrir.addEventListener('click', abrirModal);
            if (fechar) fechar.addEventListener('click', fecharModal);
            if (cancelar) cancelar.addEventListener('click', fecharModal);
            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) fecharModal();
                });
            }
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') fecharModal();
            });
        })();
    </script>
</body>
</html>
