<?php
// cadernos.php
// Bloco de notas do aluno com editor visual e salvamento por usuário.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
if (!function_exists('cad_esc')) {
    function cad_esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cad_env')) {
    function cad_env(string $path): array
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

if (!function_exists('cad_db')) {
    function cad_db(): PDO
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

        $env = cad_env(__DIR__ . '/.env');
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

if (!function_exists('cad_usuario_id')) {
    function cad_usuario_id(): int
    {
        $possiveis = [
            $_SESSION['usuario_id'] ?? null,
            $_SESSION['user_id'] ?? null,
            $_SESSION['id_usuario'] ?? null,
            $_SESSION['id'] ?? null,
            $_SESSION['usuario']['id'] ?? null,
            $_SESSION['usuario']['usuario_id'] ?? null,
        ];

        foreach ($possiveis as $valor) {
            if (is_numeric($valor) && (int)$valor > 0) {
                return (int)$valor;
            }
        }

        return 0;
    }
}

if (!function_exists('cad_limpar_html')) {
    function cad_limpar_html(string $html): string
    {
        $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|textarea|select|option)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|textarea|select|option)[^>]*\s*/?>#is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? '';

        $permitidos = '<p><br><div><span><b><strong><i><em><u><s><strike><ul><ol><li><blockquote><h1><h2><h3><h4><pre><code><font><small><big><sub><sup><hr><a>';
        return trim(strip_tags($html, $permitidos));
    }
}

if (!function_exists('cad_json')) {
    function cad_json(array $dados): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('cad_criar_tabela')) {
    function cad_criar_tabela(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cadernos_anotacoes` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `usuario_id` BIGINT UNSIGNED NOT NULL,
            `titulo` VARCHAR(190) NOT NULL DEFAULT 'Sem título',
            `conteudo` MEDIUMTEXT NULL,
            `status` ENUM('ativo','excluido') NOT NULL DEFAULT 'ativo',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_cadernos_usuario_status` (`usuario_id`, `status`),
            KEY `idx_cadernos_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

$usuarioId = cad_usuario_id();
$erroPagina = '';
$anotacoes = [];
$anotacaoAtual = null;
$pdo = null;

try {
    $pdo = cad_db();
    cad_criar_tabela($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = (string)($_POST['acao'] ?? '');

        if ($usuarioId <= 0) {
            cad_json(['ok' => false, 'mensagem' => 'Entre na sua conta para salvar suas anotações.']);
        }

        if ($acao === 'criar') {
            $titulo = trim((string)($_POST['titulo'] ?? 'Nova anotação'));
            if ($titulo === '') {
                $titulo = 'Nova anotação';
            }
            $titulo = mb_substr($titulo, 0, 190, 'UTF-8');

            $stmt = $pdo->prepare("INSERT INTO cadernos_anotacoes (usuario_id, titulo, conteudo, status) VALUES (:usuario_id, :titulo, :conteudo, 'ativo')");
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':titulo' => $titulo,
                ':conteudo' => '<p>Escreva sua anotação aqui...</p>',
            ]);

            cad_json(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'titulo' => $titulo, 'mensagem' => 'Anotação criada.']);
        }

        if ($acao === 'salvar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $conteudo = cad_limpar_html((string)($_POST['conteudo'] ?? ''));

            if ($id <= 0) {
                cad_json(['ok' => false, 'mensagem' => 'Anotação inválida.']);
            }

            if ($titulo === '') {
                $textoLimpo = trim(strip_tags($conteudo));
                $titulo = $textoLimpo !== '' ? mb_substr($textoLimpo, 0, 54, 'UTF-8') : 'Sem título';
            }
            $titulo = mb_substr($titulo, 0, 190, 'UTF-8');

            $stmt = $pdo->prepare("UPDATE cadernos_anotacoes SET titulo = :titulo, conteudo = :conteudo, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND usuario_id = :usuario_id AND status = 'ativo' LIMIT 1");
            $stmt->execute([
                ':titulo' => $titulo,
                ':conteudo' => $conteudo,
                ':id' => $id,
                ':usuario_id' => $usuarioId,
            ]);

            cad_json(['ok' => true, 'titulo' => $titulo, 'mensagem' => 'Anotação salva.']);
        }

        if ($acao === 'excluir') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            if ($id <= 0) {
                cad_json(['ok' => false, 'mensagem' => 'Anotação inválida.']);
            }

            $stmt = $pdo->prepare("UPDATE cadernos_anotacoes SET status = 'excluido', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND usuario_id = :usuario_id LIMIT 1");
            $stmt->execute([':id' => $id, ':usuario_id' => $usuarioId]);
            cad_json(['ok' => true, 'mensagem' => 'Anotação excluída.']);
        }

        cad_json(['ok' => false, 'mensagem' => 'Ação não encontrada.']);
    }

    if ($usuarioId > 0) {
        $stmt = $pdo->prepare("SELECT id, titulo, conteudo, created_at, updated_at FROM cadernos_anotacoes WHERE usuario_id = :usuario_id AND status = 'ativo' ORDER BY updated_at DESC, id DESC");
        $stmt->execute([':usuario_id' => $usuarioId]);
        $anotacoes = $stmt->fetchAll() ?: [];

        $idAtual = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        foreach ($anotacoes as $item) {
            if ($idAtual > 0 && (int)$item['id'] === $idAtual) {
                $anotacaoAtual = $item;
                break;
            }
        }

        if (!$anotacaoAtual && !empty($anotacoes)) {
            $anotacaoAtual = $anotacoes[0];
        }
    }
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        cad_json(['ok' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
    }
    $erroPagina = 'Não foi possível carregar o caderno agora: ' . $e->getMessage();
}

$barraSuperiorInclude = __DIR__ . '/includes/barra-superior.php';
if (!is_file($barraSuperiorInclude)) {
    $barraSuperiorInclude = __DIR__ . '/barra-superior.php';
}

$nomeUsuario = isset($_SESSION['usuario']['nome']) ? (string)$_SESSION['usuario']['nome'] : 'Aluno';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadernos | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#d69b12;
            --black:#000000;
            --page:#f5f5f6;
            --panel:#ffffff;
            --text:#111827;
            --muted:#6b7280;
            --line:rgba(0,0,0,.08);
            --soft:#fff8e7;
            --green:#22c55e;
            --red:#ef4444;
            --shadow:0 14px 34px rgba(0,0,0,.07);
        }

        *{margin:0;padding:0;box-sizing:border-box;}
        html,body{width:100%;min-height:100%;}
        body{font-family:'Ubuntu',sans-serif;background:var(--page);color:var(--text);}
        a{text-decoration:none;color:inherit;}
        button,input,select{font-family:inherit;}
        button{cursor:pointer;}

        .caderno-page{
            width:100%;
            max-width:1260px;
            margin:32px auto 0;
            padding:0 14px 42px;
        }

        .caderno-shell{
            background:#fafafa;
            border-radius:8px 8px 0 0;
            overflow:hidden;
            box-shadow:var(--shadow);
            border:1px solid rgba(0,0,0,.04);
        }

        .caderno-head{
            min-height:82px;
            background:var(--yellow);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            padding:18px 28px;
        }

        .caderno-head__title{
            display:flex;
            align-items:center;
            gap:13px;
            font-size:1.9rem;
            font-weight:700;
            line-height:1.15;
        }

        .caderno-head__title i,
        .caderno-head__title svg{width:31px;height:31px;}

        .caderno-head__sub{
            margin-top:4px;
            color:rgba(255,255,255,.88);
            font-size:.94rem;
            font-weight:500;
        }

        .novo-btn{
            min-height:43px;
            border:0;
            border-radius:999px;
            background:#000;
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            padding:0 18px;
            font-size:.98rem;
            font-weight:700;
            box-shadow:0 10px 20px rgba(0,0,0,.16);
        }
        .novo-btn i,.novo-btn svg{width:19px;height:19px;}

        .caderno-body{
            display:grid;
            grid-template-columns:326px minmax(0,1fr);
            min-height:620px;
            background:#fff;
        }

        .notes-sidebar{
            border-right:1px solid var(--line);
            background:#fbfbfb;
            padding:22px 18px 24px;
        }

        .notes-search{
            height:44px;
            border:1px solid rgba(0,0,0,.08);
            border-radius:999px;
            background:#fff;
            display:flex;
            align-items:center;
            gap:10px;
            padding:0 14px;
            margin-bottom:16px;
            box-shadow:0 6px 16px rgba(0,0,0,.025);
        }
        .notes-search i,.notes-search svg{width:18px;height:18px;color:var(--yellow-dark);}
        .notes-search input{
            width:100%;
            border:0;
            outline:0;
            background:transparent;
            font-size:.95rem;
            color:#111827;
        }
        .notes-search input::placeholder{color:#8b8b8b;}

        .notes-list{
            display:grid;
            gap:10px;
            max-height:545px;
            overflow:auto;
            padding-right:2px;
        }

        .note-card{
            width:100%;
            border:1px solid rgba(0,0,0,.07);
            background:#fff;
            border-radius:13px;
            text-align:left;
            padding:13px 14px;
            color:#111827;
            display:grid;
            gap:7px;
            transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }
        .note-card:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,0,0,.045);}
        .note-card.is-active{border-color:rgba(250,181,40,.75);box-shadow:0 0 0 3px rgba(250,181,40,.14);background:#fffdf8;}
        .note-card__title{font-size:.98rem;font-weight:700;color:#111827;line-height:1.22;word-break:break-word;}
        .note-card__preview{font-size:.83rem;color:var(--muted);line-height:1.34;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .note-card__date{display:flex;align-items:center;gap:6px;font-size:.78rem;color:#9a6a00;font-weight:700;}
        .note-card__date i,.note-card__date svg{width:13px;height:13px;}

        .editor-area{
            position:relative;
            background:linear-gradient(180deg,#fff 0%,#fff 65%,#fffaf0 100%);
            padding:24px 88px 28px 30px;
        }

        .editor-top{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin-bottom:18px;
        }

        .titulo-input{
            width:100%;
            border:0;
            outline:0;
            background:transparent;
            color:#111827;
            font-size:1.65rem;
            line-height:1.2;
            font-weight:700;
            padding:6px 0;
        }
        .titulo-input::placeholder{color:#bbbbbb;}

        .editor-status{
            flex:0 0 auto;
            min-width:128px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            min-height:32px;
            border-radius:999px;
            padding:0 12px;
            background:#f3f4f6;
            color:#6b7280;
            font-size:.82rem;
            font-weight:700;
            white-space:nowrap;
        }
        .editor-status i,.editor-status svg{width:15px;height:15px;}
        .editor-status.is-saved{background:rgba(34,197,94,.12);color:#15803d;}
        .editor-status.is-error{background:rgba(239,68,68,.10);color:#b91c1c;}

        .paper-wrap{
            display:grid;
            grid-template-columns:1fr 66px;
            gap:16px;
            align-items:start;
        }

        .paper{
            min-height:475px;
            border:1px solid rgba(0,0,0,.08);
            border-radius:18px;
            background:
                linear-gradient(to bottom, transparent 31px, rgba(250,181,40,.13) 32px),
                #fff;
            background-size:100% 32px;
            box-shadow:0 12px 28px rgba(0,0,0,.055);
            padding:26px 30px;
            outline:none;
            font-size:1.05rem;
            line-height:32px;
            color:#111827;
            overflow:auto;
        }
        .paper:empty:before{
            content:attr(data-placeholder);
            color:#9ca3af;
            font-style:italic;
        }
        .paper h1,.paper h2,.paper h3{line-height:1.25;margin:0 0 10px;}
        .paper p{margin:0 0 8px;}
        .paper ul,.paper ol{padding-left:24px;margin:0 0 8px;}
        .paper blockquote{border-left:4px solid var(--yellow);padding-left:14px;color:#4b5563;margin:8px 0;}

        .format-rail{
            display:grid;
            gap:10px;
            position:sticky;
            top:118px;
        }

        .format-card{
            background:#fff;
            border:1px solid rgba(0,0,0,.08);
            border-radius:18px;
            padding:10px;
            box-shadow:0 10px 24px rgba(0,0,0,.055);
            display:grid;
            gap:8px;
        }

        .format-btn{
            width:44px;
            height:40px;
            border:0;
            border-radius:12px;
            background:#f7f7f7;
            color:#111827;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:.96rem;
            font-weight:700;
            transition:background .18s ease, color .18s ease, transform .18s ease;
        }
        .format-btn:hover{transform:translateY(-1px);background:#fff2cf;color:#9a6a00;}
        .format-btn i,.format-btn svg{width:18px;height:18px;}

        .format-select{
            width:44px;
            height:40px;
            border:0;
            border-radius:12px;
            background:#f7f7f7;
            color:#111827;
            outline:none;
            font-size:.78rem;
            font-weight:700;
            text-align:center;
            padding:0 4px;
        }

        .save-row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:14px;
            margin-top:18px;
        }
        .save-info{font-size:.86rem;color:var(--muted);}
        .editor-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
        .salvar-btn,.excluir-btn{
            min-height:40px;
            border:0;
            border-radius:999px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            padding:0 16px;
            color:#fff;
            font-size:.92rem;
            font-weight:700;
        }
        .salvar-btn{background:var(--yellow);}
        .excluir-btn{background:#111827;}
        .salvar-btn i,.salvar-btn svg,.excluir-btn i,.excluir-btn svg{width:17px;height:17px;}

        .empty-editor{
            min-height:475px;
            border:1px dashed rgba(250,181,40,.65);
            border-radius:18px;
            background:#fffdf7;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            padding:34px;
            color:#6b7280;
        }
        .empty-editor__inner{max-width:460px;display:grid;justify-items:center;gap:12px;}
        .empty-editor i,.empty-editor svg{width:52px;height:52px;color:var(--yellow);}
        .empty-editor h2{font-size:1.35rem;color:#111827;margin:0;}
        .empty-editor p{line-height:1.45;margin:0;}

        .login-alert{
            background:#fff;
            border-radius:18px;
            box-shadow:var(--shadow);
            padding:42px 24px;
            text-align:center;
            display:grid;
            justify-items:center;
            gap:14px;
            color:#6b7280;
        }
        .login-alert i,.login-alert svg{width:54px;height:54px;color:var(--yellow);}
        .login-alert h1{color:#111827;font-size:1.45rem;}
        .login-alert a{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border-radius:999px;background:var(--yellow);color:#fff;padding:0 18px;font-weight:700;}

        @media(max-width:1040px){
            .caderno-body{grid-template-columns:285px minmax(0,1fr);}
            .editor-area{padding:22px 74px 26px 24px;}
            .paper{padding:24px 24px;}
        }

        @media(max-width:850px){
            .caderno-page{margin-top:22px;padding:0 10px 30px;}
            .caderno-head{min-height:auto;align-items:flex-start;flex-direction:column;padding:18px;}
            .caderno-head__title{font-size:1.45rem;}
            .novo-btn{width:100%;}
            .caderno-body{grid-template-columns:1fr;}
            .notes-sidebar{border-right:0;border-bottom:1px solid var(--line);}
            .notes-list{max-height:250px;}
            .editor-area{padding:20px 14px 24px;}
            .editor-top{align-items:stretch;flex-direction:column;gap:10px;}
            .editor-status{justify-content:flex-start;min-width:0;width:fit-content;}
            .paper-wrap{grid-template-columns:1fr;}
            .format-rail{position:static;display:block;}
            .format-card{display:flex;flex-wrap:wrap;border-radius:16px;}
            .format-btn,.format-select{width:42px;height:38px;}
            .titulo-input{font-size:1.35rem;}
            .paper{min-height:385px;padding:22px 18px;font-size:1rem;}
            .save-row{align-items:stretch;flex-direction:column;}
            .editor-actions{justify-content:stretch;}
            .salvar-btn,.excluir-btn{flex:1;}
        }

        @media(max-width:480px){
            .caderno-head__title{font-size:1.25rem;}
            .notes-sidebar{padding:16px 12px;}
            .paper{min-height:340px;line-height:30px;background-size:100% 30px;}
            .editor-actions{display:grid;grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
    <?php if (is_file($barraSuperiorInclude)) { include_once $barraSuperiorInclude; } ?>

    <main class="caderno-page">
        <?php if ($usuarioId <= 0): ?>
            <section class="login-alert">
                <i data-lucide="lock-keyhole"></i>
                <h1>Entre na sua conta para usar os cadernos</h1>
                <p>As anotações ficam salvas no seu usuário e aparecem só para você.</p>
                <a href="login.php">Ir para o login</a>
            </section>
        <?php else: ?>
            <section class="caderno-shell" aria-label="Cadernos do aluno">
                <header class="caderno-head">
                    <div>
                        <div class="caderno-head__title"><i data-lucide="notebook-tabs"></i> Cadernos</div>
                        <p class="caderno-head__sub">Olá, <?php echo cad_esc($nomeUsuario); ?>. Crie suas anotações em formato de bloco de notas.</p>
                    </div>

                    <button type="button" class="novo-btn" id="btnNovaAnotacao"><i data-lucide="plus"></i>Criar anotação</button>
                </header>

                <div class="caderno-body">
                    <aside class="notes-sidebar" aria-label="Lista de anotações">
                        <div class="notes-search">
                            <i data-lucide="search"></i>
                            <input type="search" id="buscaAnotacoes" placeholder="Buscar anotação..." autocomplete="off">
                        </div>

                        <div class="notes-list" id="listaAnotacoes">
                            <?php if ($erroPagina !== ''): ?>
                                <div class="note-card"><span class="note-card__title">Erro ao carregar</span><span class="note-card__preview"><?php echo cad_esc($erroPagina); ?></span></div>
                            <?php elseif (empty($anotacoes)): ?>
                                <div class="note-card" id="emptyListCard">
                                    <span class="note-card__title">Nenhuma anotação ainda</span>
                                    <span class="note-card__preview">Clique em criar anotação para começar seu caderno.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($anotacoes as $item): ?>
                                    <?php
                                        $ativo = $anotacaoAtual && (int)$anotacaoAtual['id'] === (int)$item['id'];
                                        $preview = trim(strip_tags((string)($item['conteudo'] ?? '')));
                                        if ($preview === '') {
                                            $preview = 'Sem conteúdo ainda.';
                                        }
                                        $data = !empty($item['updated_at']) ? date('d/m/Y H:i', strtotime((string)$item['updated_at'])) : '';
                                        $busca = strtolower((string)$item['titulo'] . ' ' . $preview);
                                    ?>
                                    <button type="button" class="note-card<?php echo $ativo ? ' is-active' : ''; ?>" data-id="<?php echo (int)$item['id']; ?>" data-title="<?php echo cad_esc($item['titulo']); ?>" data-content="<?php echo cad_esc((string)$item['conteudo']); ?>" data-search="<?php echo cad_esc($busca); ?>">
                                        <span class="note-card__title"><?php echo cad_esc($item['titulo']); ?></span>
                                        <span class="note-card__preview"><?php echo cad_esc($preview); ?></span>
                                        <span class="note-card__date"><i data-lucide="clock-3"></i><?php echo cad_esc($data); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </aside>

                    <section class="editor-area" aria-label="Editor de anotação">
                        <?php if ($anotacaoAtual): ?>
                            <input type="hidden" id="anotacaoId" value="<?php echo (int)$anotacaoAtual['id']; ?>">
                            <div class="editor-top">
                                <input type="text" class="titulo-input" id="tituloAnotacao" value="<?php echo cad_esc($anotacaoAtual['titulo']); ?>" placeholder="Título da anotação">
                                <span class="editor-status is-saved" id="statusEditor"><i data-lucide="check-circle-2"></i>Salvo</span>
                            </div>

                            <div class="paper-wrap">
                                <div class="paper" id="editorAnotacao" contenteditable="true" data-placeholder="Escreva sua anotação aqui..."><?php echo cad_limpar_html((string)$anotacaoAtual['conteudo']); ?></div>

                                <aside class="format-rail" aria-label="Ferramentas de texto">
                                    <div class="format-card">
                                        <button type="button" class="format-btn" data-cmd="bold" title="Negrito"><strong>B</strong></button>
                                        <button type="button" class="format-btn" data-cmd="italic" title="Itálico"><em>I</em></button>
                                        <button type="button" class="format-btn" data-cmd="underline" title="Sublinhado"><u>U</u></button>
                                        <button type="button" class="format-btn" data-cmd="removeFormat" title="Regular">Aa</button>
                                        <button type="button" class="format-btn" data-cmd="insertUnorderedList" title="Lista"><i data-lucide="list"></i></button>
                                        <button type="button" class="format-btn" data-cmd="insertOrderedList" title="Lista numerada"><i data-lucide="list-ordered"></i></button>
                                        <button type="button" class="format-btn" data-cmd="formatBlock" data-value="blockquote" title="Citação"><i data-lucide="quote"></i></button>
                                        <button type="button" class="format-btn" data-cmd="justifyLeft" title="Alinhar à esquerda"><i data-lucide="align-left"></i></button>
                                        <button type="button" class="format-btn" data-cmd="justifyCenter" title="Centralizar"><i data-lucide="align-center"></i></button>
                                        <select class="format-select" id="fontName" title="Fonte">
                                            <option value="Ubuntu">Ubuntu</option>
                                            <option value="Arial">Arial</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Verdana">Verdana</option>
                                        </select>
                                        <select class="format-select" id="fontSize" title="Tamanho">
                                            <option value="3">16</option>
                                            <option value="4">18</option>
                                            <option value="5">22</option>
                                            <option value="6">26</option>
                                        </select>
                                    </div>
                                </aside>
                            </div>

                            <div class="save-row">
                                <span class="save-info">Salvamento automático após digitar. Você também pode salvar manualmente.</span>
                                <div class="editor-actions">
                                    <button type="button" class="excluir-btn" id="btnExcluir"><i data-lucide="trash-2"></i>Excluir</button>
                                    <button type="button" class="salvar-btn" id="btnSalvar"><i data-lucide="save"></i>Salvar anotação</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-editor">
                                <div class="empty-editor__inner">
                                    <i data-lucide="notebook-pen"></i>
                                    <h2>Crie sua primeira anotação</h2>
                                    <p>Use o botão “Criar anotação” para abrir um bloco de notas com opções de negrito, regular, listas e fonte.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script>
        (function iniciarIcones(){
            if (window.lucide) {
                window.lucide.createIcons();
            }
        })();

        (function controlarCadernos(){
            const btnNova = document.getElementById('btnNovaAnotacao');
            const lista = document.getElementById('listaAnotacoes');
            const busca = document.getElementById('buscaAnotacoes');
            const editor = document.getElementById('editorAnotacao');
            const titulo = document.getElementById('tituloAnotacao');
            const inputId = document.getElementById('anotacaoId');
            const status = document.getElementById('statusEditor');
            const btnSalvar = document.getElementById('btnSalvar');
            const btnExcluir = document.getElementById('btnExcluir');
            let timerSalvar = null;
            let salvando = false;

            function postar(dados){
                return fetch('cadernos.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams(dados).toString()
                }).then(function(resposta){ return resposta.json(); });
            }

            function marcarStatus(tipo, texto, icone){
                if (!status) return;
                status.classList.remove('is-saved','is-error');
                if (tipo === 'saved') status.classList.add('is-saved');
                if (tipo === 'error') status.classList.add('is-error');
                status.innerHTML = '<i data-lucide="' + icone + '"></i>' + texto;
                if (window.lucide) window.lucide.createIcons();
            }

            function salvarAgora(){
                if (!editor || !titulo || !inputId || salvando) return;
                const id = inputId.value || '';
                if (!id) return;
                salvando = true;
                marcarStatus('', 'Salvando...', 'loader-2');

                postar({
                    acao: 'salvar',
                    id: id,
                    titulo: titulo.value,
                    conteudo: editor.innerHTML
                }).then(function(retorno){
                    salvando = false;
                    if (!retorno || !retorno.ok) {
                        marcarStatus('error', (retorno && retorno.mensagem) ? retorno.mensagem : 'Erro', 'alert-circle');
                        return;
                    }
                    marcarStatus('saved', 'Salvo', 'check-circle-2');

                    const card = lista ? lista.querySelector('.note-card[data-id="' + id + '"]') : null;
                    if (card) {
                        const novoTitulo = retorno.titulo || titulo.value || 'Sem título';
                        const preview = (editor.textContent || 'Sem conteúdo ainda.').trim();
                        card.dataset.title = novoTitulo;
                        card.dataset.content = editor.innerHTML;
                        card.dataset.search = (novoTitulo + ' ' + preview).toLowerCase();
                        const titleNode = card.querySelector('.note-card__title');
                        const previewNode = card.querySelector('.note-card__preview');
                        if (titleNode) titleNode.textContent = novoTitulo;
                        if (previewNode) previewNode.textContent = preview || 'Sem conteúdo ainda.';
                    }
                }).catch(function(){
                    salvando = false;
                    marcarStatus('error', 'Erro ao salvar', 'alert-circle');
                });
            }

            function agendarSalvar(){
                marcarStatus('', 'Editando...', 'pencil');
                clearTimeout(timerSalvar);
                timerSalvar = setTimeout(salvarAgora, 900);
            }

            if (btnNova) {
                btnNova.addEventListener('click', function(){
                    btnNova.disabled = true;
                    postar({acao: 'criar', titulo: 'Nova anotação'}).then(function(retorno){
                        if (!retorno || !retorno.ok) {
                            alert((retorno && retorno.mensagem) ? retorno.mensagem : 'Não foi possível criar a anotação.');
                            btnNova.disabled = false;
                            return;
                        }
                        window.location.href = 'cadernos.php?id=' + retorno.id;
                    }).catch(function(){
                        alert('Não foi possível criar a anotação.');
                        btnNova.disabled = false;
                    });
                });
            }

            if (lista) {
                lista.addEventListener('click', function(event){
                    const card = event.target.closest('.note-card[data-id]');
                    if (!card) return;
                    window.location.href = 'cadernos.php?id=' + card.dataset.id;
                });
            }

            if (busca && lista) {
                busca.addEventListener('input', function(){
                    const termo = busca.value.trim().toLowerCase();
                    const cards = lista.querySelectorAll('.note-card[data-id]');
                    cards.forEach(function(card){
                        const alvo = card.dataset.search || card.textContent.toLowerCase();
                        card.style.display = alvo.includes(termo) ? '' : 'none';
                    });
                });
            }

            if (editor) {
                editor.addEventListener('input', agendarSalvar);
                editor.addEventListener('paste', function(){ setTimeout(agendarSalvar, 80); });
            }
            if (titulo) {
                titulo.addEventListener('input', agendarSalvar);
            }
            if (btnSalvar) {
                btnSalvar.addEventListener('click', salvarAgora);
            }
            if (btnExcluir && inputId) {
                btnExcluir.addEventListener('click', function(){
                    if (!confirm('Excluir esta anotação?')) return;
                    postar({acao: 'excluir', id: inputId.value}).then(function(retorno){
                        if (!retorno || !retorno.ok) {
                            alert((retorno && retorno.mensagem) ? retorno.mensagem : 'Não foi possível excluir.');
                            return;
                        }
                        window.location.href = 'cadernos.php';
                    }).catch(function(){ alert('Não foi possível excluir.'); });
                });
            }

            document.querySelectorAll('.format-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    if (!editor) return;
                    editor.focus();
                    const cmd = btn.dataset.cmd;
                    const value = btn.dataset.value || null;
                    try {
                        document.execCommand(cmd, false, value);
                    } catch (e) {}
                    agendarSalvar();
                });
            });

            const fontName = document.getElementById('fontName');
            const fontSize = document.getElementById('fontSize');
            if (fontName) {
                fontName.addEventListener('change', function(){
                    if (!editor) return;
                    editor.focus();
                    document.execCommand('fontName', false, fontName.value);
                    agendarSalvar();
                });
            }
            if (fontSize) {
                fontSize.addEventListener('change', function(){
                    if (!editor) return;
                    editor.focus();
                    document.execCommand('fontSize', false, fontSize.value);
                    agendarSalvar();
                });
            }
        })();
    </script>
</body>
</html>
