<?php
// admin-mensagens.php
// Central de mensagens do admin: tópicos do fórum geral + tópicos criados dentro das aulas.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/api/conexao.php';

if (!function_exists('admin_msg_esc')) {
    function admin_msg_esc($texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_msg_admin_nome')) {
    function admin_msg_admin_nome(): string
    {
        $admin = function_exists('auth_admin_user') ? auth_admin_user() : [];
        $nome = trim((string)($admin['nome'] ?? $admin['login'] ?? 'Admin'));
        return $nome !== '' ? $nome : 'Admin';
    }
}

if (!function_exists('admin_msg_data')) {
    function admin_msg_data(?string $data): string
    {
        $data = trim((string)$data);
        if ($data === '') {
            return '-';
        }

        $ts = strtotime($data);
        return $ts ? date('d/m/Y H:i', $ts) : $data;
    }
}

if (!function_exists('admin_msg_texto_curto')) {
    function admin_msg_texto_curto(string $texto, int $limite = 260): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
        if ($texto === '') {
            return '-';
        }

        if (function_exists('mb_strlen') && mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '...';
        }

        if (!function_exists('mb_strlen') && strlen($texto) > $limite) {
            return substr($texto, 0, $limite) . '...';
        }

        return $texto;
    }
}

if (!function_exists('admin_msg_tabela_existe')) {
    function admin_msg_tabela_existe(PDO $pdo, string $tabela): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela");
            $stmt->execute([':tabela' => $tabela]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_msg_garantir_tabelas')) {
    function admin_msg_garantir_tabelas(PDO $pdo): void
    {
        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS forum_duvidas_topicos (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                autor_nome VARCHAR(160) DEFAULT NULL,\n                titulo VARCHAR(180) NOT NULL,\n                mensagem TEXT DEFAULT NULL,\n                categoria VARCHAR(180) NOT NULL DEFAULT 'Outro',\n                privado TINYINT(1) NOT NULL DEFAULT 0,\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_forum_duvidas_usuario (usuario_id),\n                KEY idx_forum_duvidas_status_data (status, criado_em),\n                KEY idx_forum_duvidas_categoria (categoria),\n                KEY idx_forum_duvidas_privado (privado)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS forum_duvidas_respostas (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                topico_id BIGINT UNSIGNED NOT NULL,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                autor_nome VARCHAR(160) DEFAULT NULL,\n                mensagem TEXT NOT NULL,\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_forum_respostas_topico (topico_id),\n                KEY idx_forum_respostas_usuario (usuario_id),\n                KEY idx_forum_respostas_status (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS aulas_forum_topicos (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                curso_id INT UNSIGNED NOT NULL,\n                modulo_id INT UNSIGNED NOT NULL,\n                aula_id INT UNSIGNED NOT NULL,\n                titulo VARCHAR(180) NOT NULL,\n                texto TEXT NOT NULL,\n                visibilidade ENUM('todos','monitores') NOT NULL DEFAULT 'todos',\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_aulas_forum_topicos_aula (aula_id),\n                KEY idx_aulas_forum_topicos_modulo (modulo_id),\n                KEY idx_aulas_forum_topicos_usuario (usuario_id)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");

        $pdo->exec("\n            CREATE TABLE IF NOT EXISTS aulas_forum_respostas (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n                topico_id BIGINT UNSIGNED NOT NULL,\n                usuario_id BIGINT UNSIGNED DEFAULT NULL,\n                autor_nome VARCHAR(160) DEFAULT NULL,\n                mensagem TEXT NOT NULL,\n                status ENUM('ativo','oculto') NOT NULL DEFAULT 'ativo',\n                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n                PRIMARY KEY (id),\n                KEY idx_aulas_forum_respostas_topico (topico_id),\n                KEY idx_aulas_forum_respostas_usuario (usuario_id),\n                KEY idx_aulas_forum_respostas_status (status)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n        ");
    }
}

$topbarInclude = __DIR__ . '/includes/admin-topbar.php';
$menuBarInclude = __DIR__ . '/includes/menu-bar.php';
$adminNome = admin_msg_admin_nome();
$erro = '';
$sucesso = '';
$mensagens = [];
$stats = [
    'total' => 0,
    'sem_resposta' => 0,
    'forum' => 0,
    'aulas' => 0,
];

$origemFiltro = (string)($_GET['origem'] ?? 'todos');
$statusFiltro = (string)($_GET['status'] ?? 'todos');
$busca = trim((string)($_GET['busca'] ?? ''));

if (!in_array($origemFiltro, ['todos', 'forum', 'aulas'], true)) {
    $origemFiltro = 'todos';
}

if (!in_array($statusFiltro, ['todos', 'sem_resposta', 'respondidos', 'privados'], true)) {
    $statusFiltro = 'todos';
}

try {
    $pdo = db();
    admin_msg_garantir_tabelas($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'responder') {
        $origemPost = (string)($_POST['origem'] ?? '');
        $topicoId = (int)($_POST['topico_id'] ?? 0);
        $resposta = trim((string)($_POST['resposta'] ?? ''));

        if (!in_array($origemPost, ['forum', 'aulas'], true) || $topicoId <= 0) {
            throw new RuntimeException('Tópico inválido para resposta.');
        }

        if ($resposta === '') {
            throw new RuntimeException('Digite a resposta antes de enviar.');
        }

        if ($origemPost === 'forum') {
            $stmtExiste = $pdo->prepare("SELECT id FROM forum_duvidas_topicos WHERE id = :id LIMIT 1");
            $stmtExiste->execute([':id' => $topicoId]);
            if (!$stmtExiste->fetchColumn()) {
                throw new RuntimeException('Tópico do fórum não encontrado.');
            }

            $stmt = $pdo->prepare("\n                INSERT INTO forum_duvidas_respostas (topico_id, usuario_id, autor_nome, mensagem, status, criado_em)\n                VALUES (:topico_id, NULL, :autor_nome, :mensagem, 'ativo', NOW())\n            ");
            $stmt->execute([
                ':topico_id' => $topicoId,
                ':autor_nome' => $adminNome,
                ':mensagem' => $resposta,
            ]);
        } else {
            $stmtExiste = $pdo->prepare("SELECT id FROM aulas_forum_topicos WHERE id = :id LIMIT 1");
            $stmtExiste->execute([':id' => $topicoId]);
            if (!$stmtExiste->fetchColumn()) {
                throw new RuntimeException('Tópico da aula não encontrado.');
            }

            $stmt = $pdo->prepare("\n                INSERT INTO aulas_forum_respostas (topico_id, usuario_id, autor_nome, mensagem, status, criado_em)\n                VALUES (:topico_id, NULL, :autor_nome, :mensagem, 'ativo', NOW())\n            ");
            $stmt->execute([
                ':topico_id' => $topicoId,
                ':autor_nome' => $adminNome,
                ':mensagem' => $resposta,
            ]);
        }

        $query = $_GET;
        $query['respondido'] = '1';
        header('Location: admin-mensagens?' . http_build_query($query));
        exit;
    }

    if (isset($_GET['respondido'])) {
        $sucesso = 'Resposta enviada com sucesso.';
    }

    $forumRows = [];
    if ($origemFiltro === 'todos' || $origemFiltro === 'forum') {
        $whereForum = ["t.status = 'ativo'"];
        $paramsForum = [];

        if ($statusFiltro === 'privados') {
            $whereForum[] = 't.privado = 1';
        } elseif ($statusFiltro === 'sem_resposta') {
            $whereForum[] = "(SELECT COUNT(*) FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo') = 0";
        } elseif ($statusFiltro === 'respondidos') {
            $whereForum[] = "(SELECT COUNT(*) FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo') > 0";
        }

        if ($busca !== '') {
            $whereForum[] = '(t.titulo LIKE :busca_forum1 OR t.mensagem LIKE :busca_forum2 OR t.categoria LIKE :busca_forum3 OR u.nome LIKE :busca_forum4 OR t.autor_nome LIKE :busca_forum5)';
            foreach (range(1, 5) as $__n) { $paramsForum[':busca_forum' . $__n] = '%' . $busca . '%'; } // parâmetro único por coluna (EMULATE_PREPARES=false)
        }

        $stmtForum = $pdo->prepare("\n            SELECT\n                'forum' AS origem,\n                t.id,\n                t.usuario_id,\n                COALESCE(NULLIF(u.nome, ''), NULLIF(t.autor_nome, ''), 'Aluno') AS autor_nome,\n                t.titulo,\n                COALESCE(t.mensagem, '') AS mensagem,\n                t.categoria AS contexto,\n                t.privado,\n                t.criado_em,\n                (SELECT COUNT(*) FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo') AS respostas_total,\n                (SELECT fr.mensagem FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultima_resposta,\n                (SELECT fr.autor_nome FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultimo_autor,\n                (SELECT fr.criado_em FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo' ORDER BY fr.criado_em DESC, fr.id DESC LIMIT 1) AS ultima_resposta_em\n            FROM forum_duvidas_topicos t\n            LEFT JOIN usuarios u ON u.id = t.usuario_id\n            WHERE " . implode(' AND ', $whereForum) . "\n            ORDER BY t.criado_em DESC, t.id DESC\n            LIMIT 120\n        ");
        $stmtForum->execute($paramsForum);
        $forumRows = $stmtForum->fetchAll();
    }

    $aulaRows = [];
    if ($origemFiltro === 'todos' || $origemFiltro === 'aulas') {
        $whereAulas = ["aft.status = 'ativo'"];
        $paramsAulas = [];

        if ($statusFiltro === 'privados') {
            $whereAulas[] = "aft.visibilidade = 'monitores'";
        } elseif ($statusFiltro === 'sem_resposta') {
            $whereAulas[] = "(SELECT COUNT(*) FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo') = 0";
        } elseif ($statusFiltro === 'respondidos') {
            $whereAulas[] = "(SELECT COUNT(*) FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo') > 0";
        }

        if ($busca !== '') {
            $whereAulas[] = '(aft.titulo LIKE :busca_aulas1 OR aft.texto LIKE :busca_aulas2 OR u.nome LIKE :busca_aulas3 OR c.nome LIKE :busca_aulas4 OR m.titulo LIKE :busca_aulas5 OR a.titulo LIKE :busca_aulas6)';
            foreach (range(1, 6) as $__n) { $paramsAulas[':busca_aulas' . $__n] = '%' . $busca . '%'; } // parâmetro único por coluna (EMULATE_PREPARES=false)
        }

        $stmtAulas = $pdo->prepare("\n            SELECT\n                'aulas' AS origem,\n                aft.id,\n                aft.usuario_id,\n                COALESCE(NULLIF(u.nome, ''), 'Aluno') AS autor_nome,\n                aft.titulo,\n                aft.texto AS mensagem,\n                CONCAT(COALESCE(c.nome, 'Curso'), ' • ', COALESCE(m.titulo, 'Módulo'), ' • ', COALESCE(a.titulo, 'Aula')) AS contexto,\n                CASE WHEN aft.visibilidade = 'monitores' THEN 1 ELSE 0 END AS privado,\n                aft.criado_em,\n                aft.curso_id,\n                aft.modulo_id,\n                aft.aula_id,\n                (SELECT COUNT(*) FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo') AS respostas_total,\n                (SELECT ar.mensagem FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultima_resposta,\n                (SELECT ar.autor_nome FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultimo_autor,\n                (SELECT ar.criado_em FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo' ORDER BY ar.criado_em DESC, ar.id DESC LIMIT 1) AS ultima_resposta_em\n            FROM aulas_forum_topicos aft\n            LEFT JOIN usuarios u ON u.id = aft.usuario_id\n            LEFT JOIN admin_cursos c ON c.id = aft.curso_id\n            LEFT JOIN admin_curso_modulos m ON m.id = aft.modulo_id\n            LEFT JOIN admin_curso_aulas a ON a.id = aft.aula_id\n            WHERE " . implode(' AND ', $whereAulas) . "\n            ORDER BY aft.criado_em DESC, aft.id DESC\n            LIMIT 120\n        ");
        $stmtAulas->execute($paramsAulas);
        $aulaRows = $stmtAulas->fetchAll();
    }

    $mensagens = array_merge($forumRows, $aulaRows);
    usort($mensagens, static function (array $a, array $b): int {
        $ta = strtotime((string)($a['criado_em'] ?? '')) ?: 0;
        $tb = strtotime((string)($b['criado_em'] ?? '')) ?: 0;
        return $tb <=> $ta;
    });

    $stmtTotalForum = $pdo->query("SELECT COUNT(*) FROM forum_duvidas_topicos WHERE status = 'ativo'");
    $stats['forum'] = (int)$stmtTotalForum->fetchColumn();

    $stmtTotalAulas = $pdo->query("SELECT COUNT(*) FROM aulas_forum_topicos WHERE status = 'ativo'");
    $stats['aulas'] = (int)$stmtTotalAulas->fetchColumn();

    $stats['total'] = $stats['forum'] + $stats['aulas'];

    $semForum = (int)$pdo->query("SELECT COUNT(*) FROM forum_duvidas_topicos t WHERE t.status = 'ativo' AND (SELECT COUNT(*) FROM forum_duvidas_respostas fr WHERE fr.topico_id = t.id AND fr.status = 'ativo') = 0")->fetchColumn();
    $semAulas = (int)$pdo->query("SELECT COUNT(*) FROM aulas_forum_topicos aft WHERE aft.status = 'ativo' AND (SELECT COUNT(*) FROM aulas_forum_respostas ar WHERE ar.topico_id = aft.id AND ar.status = 'ativo') = 0")->fetchColumn();
    $stats['sem_resposta'] = $semForum + $semAulas;
} catch (Throwable $e) {
    $erro = $e->getMessage();
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
    <title>Mensagens | Admin Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{
            --admin-preto:#111111;
            --admin-amarelo:#fdbd08;
            --admin-amarelo-escuro:#d99a00;
            --admin-fundo:#f3f4f6;
            --admin-card:#ffffff;
            --admin-texto:#1f2937;
            --admin-muted:#667085;
            --admin-borda:#e4e7ec;
            --admin-sombra:0 10px 28px rgba(15,23,42,.08);
            --green:#12b76a;
            --red:#f04438;
            --blue:#2563eb;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html,body{width:100%;min-height:100%}
        body{min-height:100vh;background:var(--admin-fundo);color:var(--admin-texto);font-family:'Ubuntu',Arial,sans-serif}
        a{text-decoration:none;color:inherit}
        button,input,select,textarea{font-family:inherit}

        .admin-content{flex:1;min-width:0;padding:28px 34px 42px}
        .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}
        .page-title{display:flex;align-items:center;gap:13px}
        .page-title__icon{width:48px;height:48px;border-radius:14px;background:#fff7db;color:var(--admin-amarelo-escuro);display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(251,189,8,.12)}
        .page-title__icon svg,.page-title__icon i{width:24px;height:24px}
        .page-title h1{font-size:1.8rem;line-height:1.15;margin-bottom:5px;color:#111827}
        .page-title p{color:var(--admin-muted);font-weight:500;font-size:.96rem}
        .head-action{height:42px;border:0;border-radius:9px;background:var(--admin-amarelo);color:#111827;padding:0 16px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:9px;white-space:nowrap}
        .head-action svg,.head-action i{width:18px;height:18px}

        .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
        .stat-card{background:#fff;border:1px solid var(--admin-borda);border-radius:15px;padding:17px 18px;box-shadow:var(--admin-sombra);display:flex;align-items:center;gap:13px}
        .stat-icon{width:42px;height:42px;border-radius:13px;background:#fff7db;color:var(--admin-amarelo-escuro);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
        .stat-icon svg,.stat-icon i{width:21px;height:21px}
        .stat-card strong{display:block;font-size:1.45rem;color:#111827;line-height:1}
        .stat-card span{display:block;margin-top:5px;color:var(--admin-muted);font-size:.88rem;font-weight:700}

        .filters-card{background:#fff;border:1px solid var(--admin-borda);border-radius:16px;padding:16px;box-shadow:var(--admin-sombra);margin-bottom:18px}
        .filters-form{display:grid;grid-template-columns:1.1fr 170px 190px auto;gap:12px;align-items:end}
        .field{display:grid;gap:7px}
        .field label{font-size:.82rem;color:#344054;font-weight:700}
        .input,.select,.textarea{width:100%;border:1px solid #d0d5dd;border-radius:9px;background:#fff;color:#111827;outline:none;font-weight:500;transition:border-color .18s ease, box-shadow .18s ease}
        .input,.select{height:43px;padding:0 12px}
        .textarea{min-height:96px;padding:12px 13px;resize:vertical;line-height:1.45}
        .input:focus,.select:focus,.textarea:focus{border-color:var(--admin-amarelo);box-shadow:0 0 0 3px rgba(253,189,8,.16)}
        .btn{height:43px;border:0;border-radius:9px;background:var(--admin-amarelo);color:#111827;font-weight:700;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;white-space:nowrap}
        .btn.is-light{background:#fff;border:1px solid #d0d5dd;color:#344054}
        .btn svg,.btn i{width:18px;height:18px}

        .alert{border-radius:12px;padding:14px 16px;margin-bottom:16px;font-weight:700;line-height:1.4}
        .alert.is-error{background:#fff1f0;border:1px solid #fecdca;color:#b42318}
        .alert.is-success{background:#ecfdf3;border:1px solid #abefc6;color:#067647}

        .messages-list{display:grid;gap:16px}
        .message-card{background:#fff;border:1px solid var(--admin-borda);border-radius:18px;box-shadow:var(--admin-sombra);overflow:hidden}
        .message-main{padding:18px 20px 16px;display:grid;gap:13px}
        .message-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
        .message-heading{min-width:0}
        .message-badges{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
        .badge{min-height:26px;border-radius:999px;padding:0 10px;display:inline-flex;align-items:center;gap:7px;font-size:.78rem;font-weight:700;line-height:1}
        .badge svg,.badge i{width:14px;height:14px}
        .badge.is-forum{background:#eff6ff;color:#1d4ed8}
        .badge.is-aula{background:#fff7db;color:#a15c00}
        .badge.is-private{background:#fff1f0;color:#b42318}
        .badge.is-ok{background:#ecfdf3;color:#067647}
        .badge.is-wait{background:#fef3f2;color:#b42318}
        .message-title{font-size:1.07rem;line-height:1.3;color:#111827;margin:0;word-break:break-word}
        .message-meta{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;color:#667085;font-size:.85rem;font-weight:700}
        .message-meta span{display:inline-flex;align-items:center;gap:6px}
        .message-meta svg,.message-meta i{width:15px;height:15px;color:#98a2b3}
        .message-context{min-width:220px;max-width:360px;text-align:right;color:#667085;font-size:.84rem;font-weight:700;line-height:1.35}
        .message-text{background:#f9fafb;border:1px solid #edf0f3;border-radius:12px;padding:13px 14px;color:#344054;line-height:1.5;font-size:.95rem;white-space:pre-wrap;word-break:break-word}
        .last-reply{border-left:4px solid var(--admin-amarelo);background:#fffaf0;border-radius:12px;padding:12px 14px;color:#344054;display:grid;gap:6px}
        .last-reply strong{font-size:.88rem;color:#111827;display:flex;align-items:center;gap:7px}
        .last-reply strong svg,.last-reply strong i{width:16px;height:16px;color:var(--admin-amarelo-escuro)}
        .last-reply p{font-size:.92rem;line-height:1.45;margin:0;white-space:pre-wrap}
        .reply-form{border-top:1px solid var(--admin-borda);padding:15px 20px 18px;background:#fbfcfe;display:grid;gap:10px}
        .reply-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .reply-hint{display:flex;align-items:center;gap:7px;color:#667085;font-size:.84rem;font-weight:700}
        .reply-hint svg,.reply-hint i{width:16px;height:16px;color:#98a2b3}
        .empty-card{background:#fff;border:1px dashed #d0d5dd;border-radius:18px;padding:36px 20px;text-align:center;color:#667085;display:grid;justify-items:center;gap:10px}
        .empty-card svg,.empty-card i{width:42px;height:42px;color:var(--admin-amarelo-escuro)}
        .empty-card strong{color:#111827;font-size:1.1rem}

        @media(max-width:1100px){
            .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .filters-form{grid-template-columns:1fr 1fr}
            .filters-form .btn{width:100%}
        }
        @media(max-width:760px){
            .admin-content{padding:22px 16px 34px}
            .page-head{flex-direction:column}
            .head-action{width:100%}
            .stats-grid{grid-template-columns:1fr}
            .filters-form{grid-template-columns:1fr}
            .message-top{flex-direction:column}
            .message-context{max-width:none;min-width:0;text-align:left}
            .reply-actions{align-items:stretch;flex-direction:column}
            .reply-actions .btn{width:100%}
        }
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include_once $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include_once $menuBarInclude; } ?>

        <main class="admin-content">
            <header class="page-head">
                <div class="page-title">
                    <span class="page-title__icon"><i data-lucide="messages-square"></i></span>
                    <div>
                        <h1>Mensagens</h1>
                        <p>Responda dúvidas criadas pelos alunos no fórum e nos tópicos das aulas.</p>
                    </div>
                </div>
                <a class="head-action" href="forum-duvidas.php" target="_blank" rel="noopener">
                    <i data-lucide="external-link"></i>
                    Ver fórum do aluno
                </a>
            </header>

            <?php if ($erro !== ''): ?>
                <div class="alert is-error"><?php echo admin_msg_esc($erro); ?></div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="alert is-success"><?php echo admin_msg_esc($sucesso); ?></div>
            <?php endif; ?>

            <section class="stats-grid" aria-label="Resumo das mensagens">
                <article class="stat-card">
                    <span class="stat-icon"><i data-lucide="inbox"></i></span>
                    <div><strong><?php echo (int)$stats['total']; ?></strong><span>Total de tópicos</span></div>
                </article>
                <article class="stat-card">
                    <span class="stat-icon"><i data-lucide="message-circle-warning"></i></span>
                    <div><strong><?php echo (int)$stats['sem_resposta']; ?></strong><span>Sem resposta</span></div>
                </article>
                <article class="stat-card">
                    <span class="stat-icon"><i data-lucide="message-square-text"></i></span>
                    <div><strong><?php echo (int)$stats['forum']; ?></strong><span>Fórum geral</span></div>
                </article>
                <article class="stat-card">
                    <span class="stat-icon"><i data-lucide="play-square"></i></span>
                    <div><strong><?php echo (int)$stats['aulas']; ?></strong><span>Tópicos das aulas</span></div>
                </article>
            </section>

            <section class="filters-card" aria-label="Filtros de mensagens">
                <form class="filters-form" method="get" action="admin-mensagens.php">
                    <div class="field">
                        <label for="busca">Buscar mensagem</label>
                        <input class="input" id="busca" name="busca" type="search" value="<?php echo admin_msg_esc($busca); ?>" placeholder="Aluno, título, mensagem, aula, curso...">
                    </div>

                    <div class="field">
                        <label for="origem">Origem</label>
                        <select class="select" id="origem" name="origem">
                            <option value="todos" <?php echo $origemFiltro === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <option value="forum" <?php echo $origemFiltro === 'forum' ? 'selected' : ''; ?>>Fórum geral</option>
                            <option value="aulas" <?php echo $origemFiltro === 'aulas' ? 'selected' : ''; ?>>Tópicos das aulas</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select class="select" id="status" name="status">
                            <option value="todos" <?php echo $statusFiltro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="sem_resposta" <?php echo $statusFiltro === 'sem_resposta' ? 'selected' : ''; ?>>Sem resposta</option>
                            <option value="respondidos" <?php echo $statusFiltro === 'respondidos' ? 'selected' : ''; ?>>Respondidos</option>
                            <option value="privados" <?php echo $statusFiltro === 'privados' ? 'selected' : ''; ?>>Privados</option>
                        </select>
                    </div>

                    <button class="btn" type="submit"><i data-lucide="search"></i>Filtrar</button>
                </form>
            </section>

            <section class="messages-list" aria-label="Lista de mensagens dos alunos">
                <?php if (!$mensagens): ?>
                    <div class="empty-card">
                        <i data-lucide="message-circle-off"></i>
                        <strong>Nenhuma mensagem encontrada</strong>
                        <p>Quando o aluno criar tópico no fórum ou dentro da aula, vai aparecer aqui para o admin responder.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($mensagens as $item): ?>
                        <?php
                            $origem = (string)($item['origem'] ?? 'forum');
                            $respostasTotal = (int)($item['respostas_total'] ?? 0);
                            $privado = (int)($item['privado'] ?? 0) === 1;
                            $aulaLink = '#';
                            if ($origem === 'aulas') {
                                $cursoId = (int)($item['curso_id'] ?? 0);
                                $moduloId = (int)($item['modulo_id'] ?? 0);
                                $aulaId = (int)($item['aula_id'] ?? 0);
                                if ($cursoId > 0 && $moduloId > 0) {
                                    $aulaLink = 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . ($aulaId > 0 ? '&aula_id=' . $aulaId : '');
                                }
                            }
                        ?>
                        <article class="message-card">
                            <div class="message-main">
                                <div class="message-top">
                                    <div class="message-heading">
                                        <div class="message-badges">
                                            <?php if ($origem === 'aulas'): ?>
                                                <span class="badge is-aula"><i data-lucide="play-square"></i>Tópico da aula</span>
                                            <?php else: ?>
                                                <span class="badge is-forum"><i data-lucide="messages-square"></i>Fórum geral</span>
                                            <?php endif; ?>

                                            <?php if ($privado): ?>
                                                <span class="badge is-private"><i data-lucide="lock"></i>Privado</span>
                                            <?php endif; ?>

                                            <?php if ($respostasTotal > 0): ?>
                                                <span class="badge is-ok"><i data-lucide="check-circle-2"></i><?php echo $respostasTotal; ?> resposta<?php echo $respostasTotal === 1 ? '' : 's'; ?></span>
                                            <?php else: ?>
                                                <span class="badge is-wait"><i data-lucide="circle-alert"></i>Sem resposta</span>
                                            <?php endif; ?>
                                        </div>
                                        <h2 class="message-title"><?php echo admin_msg_esc((string)($item['titulo'] ?? 'Sem título')); ?></h2>
                                        <div class="message-meta">
                                            <span><i data-lucide="user"></i><?php echo admin_msg_esc((string)($item['autor_nome'] ?? 'Aluno')); ?></span>
                                            <span><i data-lucide="clock"></i><?php echo admin_msg_esc(admin_msg_data((string)($item['criado_em'] ?? ''))); ?></span>
                                        </div>
                                    </div>

                                    <div class="message-context">
                                        <?php if ($origem === 'aulas' && $aulaLink !== '#'): ?>
                                            <a href="<?php echo admin_msg_esc($aulaLink); ?>" target="_blank" rel="noopener"><?php echo admin_msg_esc((string)($item['contexto'] ?? 'Aula')); ?></a>
                                        <?php else: ?>
                                            <?php echo admin_msg_esc((string)($item['contexto'] ?? 'Fórum')); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="message-text"><?php echo nl2br(admin_msg_esc((string)($item['mensagem'] ?? ''))); ?></div>

                                <?php if (trim((string)($item['ultima_resposta'] ?? '')) !== ''): ?>
                                    <div class="last-reply">
                                        <strong><i data-lucide="reply"></i>Última resposta de <?php echo admin_msg_esc((string)($item['ultimo_autor'] ?? 'Admin')); ?> • <?php echo admin_msg_esc(admin_msg_data((string)($item['ultima_resposta_em'] ?? ''))); ?></strong>
                                        <p><?php echo nl2br(admin_msg_esc(admin_msg_texto_curto((string)$item['ultima_resposta'], 460))); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form class="reply-form" method="post" action="">
                                <input type="hidden" name="acao" value="responder">
                                <input type="hidden" name="origem" value="<?php echo admin_msg_esc($origem); ?>">
                                <input type="hidden" name="topico_id" value="<?php echo (int)($item['id'] ?? 0); ?>">

                                <div class="field">
                                    <label for="resposta_<?php echo admin_msg_esc($origem . '_' . (string)($item['id'] ?? 0)); ?>">Responder como admin</label>
                                    <textarea class="textarea" id="resposta_<?php echo admin_msg_esc($origem . '_' . (string)($item['id'] ?? 0)); ?>" name="resposta" placeholder="Digite a resposta para o aluno..." required></textarea>
                                </div>

                                <div class="reply-actions">
                                    <span class="reply-hint"><i data-lucide="send"></i>A resposta fica salva no banco e vinculada a este tópico.</span>
                                    <button class="btn" type="submit"><i data-lucide="reply"></i>Enviar resposta</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        if (window.lucide) {
            window.lucide.createIcons();
        }
    </script>
</body>
</html>
