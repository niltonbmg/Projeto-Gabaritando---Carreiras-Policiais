<?php
// cronograma.php
// Cronograma do aluno com visualização por semana e por mês, puxando atividades cadastradas pelo admin.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}

function cron_esc(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function cron_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function cron_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $safeTable = str_replace('`', '``', $table);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$safeTable}`");
        $cache[$table] = array_map(static fn(array $row): string => (string)$row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $cache[$table] = [];
    }

    return $cache[$table];
}


function cron_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!in_array($column, cron_columns($pdo, $table), true)) {
        $safeTable = str_replace('`', '', $table);
        $safeColumn = str_replace('`', '', $column);
        try {
            $pdo->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        } catch (Throwable $e) {
            // Não quebra a tela se o banco antigo já tiver variação da coluna.
        }
    }
}

function cron_try_exec(PDO $pdo, string $sql): void
{
    try { $pdo->exec($sql); } catch (Throwable $e) { }
}

function cron_ensure_tables(PDO $pdo): void
{
        require_once __DIR__ . '/includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cronogramas (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        curso_id INT UNSIGNED NOT NULL,
        titulo VARCHAR(190) NOT NULL,
        data_inicio DATE NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'ativo',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_admin_cronogramas_curso (curso_id),
        KEY idx_admin_cronogramas_data (data_inicio),
        KEY idx_admin_cronogramas_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cronograma_itens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cronograma_id INT UNSIGNED NULL,
        titulo VARCHAR(190) NOT NULL,
        materia VARCHAR(190) NULL,
        descricao TEXT NULL,
        curso_id INT UNSIGNED NULL,
        materia_id INT UNSIGNED NULL,
        topico_id INT UNSIGNED NULL,
        modulo_id INT UNSIGNED NULL,
        aula_id INT UNSIGNED NULL,
        data_estudo DATE NOT NULL,
        hora_inicio TIME NULL,
        duracao_minutos INT UNSIGNED NOT NULL DEFAULT 60,
        video_minutos INT UNSIGNED NOT NULL DEFAULT 0,
        pdf_minutos INT UNSIGNED NOT NULL DEFAULT 0,
        questoes_minutos INT UNSIGNED NOT NULL DEFAULT 0,
        ordem INT NOT NULL DEFAULT 0,
        cor VARCHAR(30) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'ativo',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_cronograma_data (data_estudo),
        KEY idx_cronograma_curso (curso_id),
        KEY idx_cronograma_materia (materia_id),
        KEY idx_cronograma_topico (topico_id),
        KEY idx_cronograma_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    cron_ensure_column($pdo, 'admin_cronograma_itens', 'cronograma_id', 'INT UNSIGNED NULL AFTER id');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'materia_id', 'INT UNSIGNED NULL AFTER curso_id');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'topico_id', 'INT UNSIGNED NULL AFTER materia_id');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'video_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER duracao_minutos');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'pdf_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'questoes_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos');
    cron_ensure_column($pdo, 'admin_cronograma_itens', 'ordem', 'INT NOT NULL DEFAULT 0 AFTER questoes_minutos');
    cron_try_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_admin (cronograma_id)');
    cron_try_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_materia (materia_id)');
    cron_try_exec($pdo, 'ALTER TABLE admin_cronograma_itens ADD INDEX idx_cronograma_topico (topico_id)');

    if (cron_table_exists($pdo, 'admin_metas_diarias')) {
        cron_ensure_column($pdo, 'admin_metas_diarias', 'video_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER tempo_minutos');
        cron_ensure_column($pdo, 'admin_metas_diarias', 'pdf_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER video_minutos');
        cron_ensure_column($pdo, 'admin_metas_diarias', 'questoes_minutos', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER pdf_minutos');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_cronograma_conclusoes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        cronograma_id INT UNSIGNED NOT NULL,
        usuario_id INT UNSIGNED NOT NULL,
        concluido TINYINT(1) NOT NULL DEFAULT 0,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_cronograma_usuario (cronograma_id, usuario_id),
        KEY idx_cronograma_usuario (usuario_id, concluido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario_metas_diarias_progresso (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        meta_id INT UNSIGNED NOT NULL,
        usuario_id INT UNSIGNED NOT NULL,
        data_referencia DATE NOT NULL,
        minutos_concluidos INT UNSIGNED NOT NULL DEFAULT 0,
        concluida TINYINT(1) NOT NULL DEFAULT 0,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_meta_usuario_dia (meta_id, usuario_id, data_referencia),
        KEY idx_meta_usuario (usuario_id, data_referencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function cron_formata_minutos(int $minutos): string
{
    $minutos = max(1, $minutos);
    $horas = intdiv($minutos, 60);
    $resto = $minutos % 60;

    if ($horas > 0 && $resto > 0) {
        return $horas . 'h ' . $resto . 'min';
    }

    if ($horas > 0) {
        return $horas . 'h';
    }

    return $minutos . 'min';
}

function cron_link_item(array $item): string
{
    global $pdo;

    $cursoId = (int)($item['curso_id'] ?? 0);
    $moduloId = (int)($item['modulo_id'] ?? 0);
    $aulaId = (int)($item['aula_id'] ?? 0);

    if ($cursoId > 0 && $moduloId > 0 && $aulaId > 0) {
        return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . $moduloId . '&aula_id=' . $aulaId;
    }

    if ($cursoId > 0 && $pdo instanceof PDO) {
        $termos = [];
        $titulo = trim((string)($item['titulo'] ?? ''));
        if ($titulo !== '') { $termos[] = $titulo; }

        $topicoId = (int)($item['topico_id'] ?? 0);
        if ($topicoId > 0) {
            try {
                $stmtTopico = $pdo->prepare('SELECT titulo FROM admin_materia_topicos WHERE id = ? LIMIT 1');
                $stmtTopico->execute([$topicoId]);
                $tituloTopico = trim((string)$stmtTopico->fetchColumn());
                if ($tituloTopico !== '') { $termos[] = $tituloTopico; }
            } catch (Throwable $e) { }
        }

        try {
            foreach (array_unique($termos) as $termo) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $termo) . '%';
                $stmt = $pdo->prepare("\n                    SELECT a.id AS aula_id, a.modulo_id\n                    FROM admin_curso_aulas a\n                    LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                    WHERE a.curso_id = ?\n                      AND (a.titulo LIKE ? OR a.descricao LIKE ? OR m.titulo LIKE ? OR m.descricao LIKE ?)\n                    ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                    LIMIT 1\n                ");
                $stmt->execute([$cursoId, $like, $like, $like, $like]);
                $aula = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($aula && (int)$aula['aula_id'] > 0) {
                    return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'];
                }
            }

            $stmt = $pdo->prepare("\n                SELECT a.id AS aula_id, a.modulo_id\n                FROM admin_curso_aulas a\n                LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id\n                WHERE a.curso_id = ?\n                ORDER BY m.ordem ASC, a.ordem ASC, a.id ASC\n                LIMIT 1\n            ");
            $stmt->execute([$cursoId]);
            $aula = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aula && (int)$aula['aula_id'] > 0) {
                return 'aulas.php?curso_id=' . $cursoId . '&modulo_id=' . (int)$aula['modulo_id'] . '&aula_id=' . (int)$aula['aula_id'];
            }
        } catch (Throwable $e) { }
    }

    if ($cursoId > 0) {
        return 'curso-modulos.php?curso_id=' . $cursoId;
    }

    return 'dashboard.php';
}

function cron_carregar_itens(PDO $pdo, int $usuarioId, string $inicio, string $fim, int $cursoId = 0): array
{
    cron_ensure_tables($pdo);

    $params = [
        ':usuario_id' => $usuarioId,
        ':inicio' => $inicio,
        ':fim' => $fim,
    ];

    $filtroCursoSelecionado = '';
    if ($cursoId > 0) {
        $filtroCursoSelecionado = ' AND c.curso_id = :curso_id_selecionado';
        $params[':curso_id_selecionado'] = $cursoId;
    }

    $filtroCurso = '';
    if ($cursoId <= 0 && $usuarioId > 0 && function_exists('gab_curso_acesso_configurado') && gab_curso_acesso_configurado($pdo, $usuarioId)) {
        $idsPermitidos = function_exists('gab_curso_acesso_ids') ? gab_curso_acesso_ids($pdo, $usuarioId) : [];
        if (!$idsPermitidos) {
            $filtroCurso = ' AND c.curso_id IS NULL';
        } else {
            $placeholders = [];
            foreach ($idsPermitidos as $idx => $cursoPermitidoId) {
                $chave = ':cron_acesso_' . $idx;
                $placeholders[] = $chave;
                $params[$chave] = (int)$cursoPermitidoId;
            }
            $filtroCurso = ' AND (c.curso_id IS NULL OR c.curso_id IN (' . implode(',', $placeholders) . '))';
        }
    }

    $sql = "
        SELECT
            c.*,
            COALESCE(u.concluido, 0) AS concluido
        FROM admin_cronograma_itens c
        LEFT JOIN usuario_cronograma_conclusoes u
            ON u.cronograma_id = c.id
           AND u.usuario_id = :usuario_id
        WHERE c.status = 'ativo'
          AND c.data_estudo BETWEEN :inicio AND :fim
          {$filtroCursoSelecionado}
          {$filtroCurso}
        ORDER BY c.data_estudo ASC, c.ordem ASC, c.hora_inicio ASC, c.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cron_domingo_da_semana(DateTimeImmutable $data): DateTimeImmutable
{
    $diaSemana = (int)$data->format('w');
    return $data->modify('-' . $diaSemana . ' days');
}

function cron_data_query(DateTimeImmutable $data, string $view, int $cursoId = 0): string
{
    $url = 'cronograma.php?view=' . urlencode($view) . '&data=' . urlencode($data->format('Y-m-d'));
    if ($cursoId > 0) {
        $url .= '&curso_id=' . $cursoId;
    }
    return $url;
}

function cron_mes_abrev(int $mes): string
{
    $nomes = [
        1 => 'jan.', 2 => 'fev.', 3 => 'mar.', 4 => 'abr.', 5 => 'mai.', 6 => 'jun.',
        7 => 'jul.', 8 => 'ago.', 9 => 'set.', 10 => 'out.', 11 => 'nov.', 12 => 'dez.'
    ];

    return $nomes[$mes] ?? '';
}

function cron_rotulo_periodo(DateTimeImmutable $inicio, DateTimeImmutable $fim): string
{
    $mesInicio = cron_mes_abrev((int)$inicio->format('n'));
    $mesFim = cron_mes_abrev((int)$fim->format('n'));

    return $mesInicio . ' ' . (int)$inicio->format('d') . ' - ' . $mesFim . ' ' . (int)$fim->format('d') . ', ' . $fim->format('Y');
}

function cron_primeira_data_disponivel(PDO $pdo, int $cursoId = 0): ?DateTimeImmutable
{
    try {
        cron_ensure_tables($pdo);
        $params = [];
        $filtroCurso = '';
        if ($cursoId > 0) {
            $filtroCurso = ' AND curso_id = :curso_id';
            $params[':curso_id'] = $cursoId;
        }

        $sqlProxima = "SELECT data_estudo FROM admin_cronograma_itens WHERE status = 'ativo' {$filtroCurso} AND data_estudo >= CURDATE() ORDER BY data_estudo ASC, ordem ASC, id ASC LIMIT 1";
        $stmt = $pdo->prepare($sqlProxima);
        $stmt->execute($params);
        $data = $stmt->fetchColumn();

        if (!$data) {
            $sqlQualquer = "SELECT data_estudo FROM admin_cronograma_itens WHERE status = 'ativo' {$filtroCurso} ORDER BY data_estudo DESC, ordem ASC, id ASC LIMIT 1";
            $stmt = $pdo->prepare($sqlQualquer);
            $stmt->execute($params);
            $data = $stmt->fetchColumn();
        }

        if ($data) {
            return DateTimeImmutable::createFromFormat('!Y-m-d', (string)$data) ?: null;
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

$usuarioId = function_exists('auth_user_id') ? auth_user_id() : 0;
$pdo = db();
cron_ensure_tables($pdo);
$cursoId = filter_input(INPUT_GET, 'curso_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id_curso', FILTER_VALIDATE_INT) ?: 0;

if ($cursoId > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, (int)$usuarioId, (int)$cursoId)) {
    $cursoId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'toggle_conclusao') {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    try {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $concluido = (int)($_POST['concluido'] ?? 0) === 1 ? 1 : 0;

        if ($itemId <= 0 || $usuarioId <= 0) {
            throw new RuntimeException('Item inválido.');
        }

        $stmt = $pdo->prepare("INSERT INTO usuario_cronograma_conclusoes (cronograma_id, usuario_id, concluido)
            VALUES (:cronograma_id, :usuario_id, :concluido)
            ON DUPLICATE KEY UPDATE concluido = VALUES(concluido), atualizado_em = NOW()");
        $stmt->execute([
            ':cronograma_id' => $itemId,
            ':usuario_id' => $usuarioId,
            ':concluido' => $concluido,
        ]);

        try {
            $stmtMeta = $pdo->prepare("
                SELECT m.id AS meta_id, COALESCE(m.data_meta, i.data_estudo) AS data_ref, CASE WHEN COALESCE(m.video_minutos,0)+COALESCE(m.pdf_minutos,0)+COALESCE(m.questoes_minutos,0) > 0 THEN COALESCE(m.video_minutos,0)+COALESCE(m.pdf_minutos,0)+COALESCE(m.questoes_minutos,0) ELSE COALESCE(m.tempo_minutos, i.duracao_minutos, 60) END AS tempo
                FROM admin_metas_diarias m
                INNER JOIN admin_cronograma_itens i ON i.id = m.cronograma_item_id
                WHERE m.cronograma_item_id = :item_id
                LIMIT 1
            " );
            $stmtMeta->execute([':item_id' => $itemId]);
            $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
            if ($meta && (int)$meta['meta_id'] > 0) {
                $stmtProg = $pdo->prepare("
                    INSERT INTO usuario_metas_diarias_progresso (meta_id, usuario_id, data_referencia, minutos_concluidos, concluida)
                    VALUES (:meta_id, :usuario_id, :data_ref, :minutos, :concluida)
                    ON DUPLICATE KEY UPDATE
                        minutos_concluidos = VALUES(minutos_concluidos),
                        concluida = VALUES(concluida),
                        atualizado_em = NOW()
                " );
                $stmtProg->execute([
                    ':meta_id' => (int)$meta['meta_id'],
                    ':usuario_id' => $usuarioId,
                    ':data_ref' => (string)$meta['data_ref'],
                    ':minutos' => $concluido ? (int)$meta['tempo'] : 0,
                    ':concluida' => $concluido,
                ]);
            }
        } catch (Throwable) {
            // A marcação do cronograma não deve quebrar se a meta ainda não existir.
        }

        echo json_encode(['ok' => true, 'concluido' => $concluido], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Não foi possível atualizar o cronograma.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$hoje = new DateTimeImmutable('today');
$view = (string)($_GET['view'] ?? 'semana');
if (!in_array($view, ['semana', 'mes'], true)) {
    $view = 'semana';
}

$dataParam = trim((string)($_GET['data'] ?? ''));
$dataSelecionada = $dataParam !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $dataParam) : false;

if (!$dataSelecionada) {
    $anoGet = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
    $mesGet = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);

    if ($anoGet && $mesGet) {
        $ano = $anoGet;
        $mes = $mesGet;

        if ($mes < 1 || $mes > 12) {
            $mes = (int)$hoje->format('m');
        }

        $dataSelecionada = DateTimeImmutable::createFromFormat('!Y-n-j', $ano . '-' . $mes . '-1') ?: $hoje;
    } else {
        $dataSelecionada = cron_primeira_data_disponivel($pdo, (int)$cursoId) ?: $hoje;
    }
}

$inicioPeriodo = cron_domingo_da_semana($dataSelecionada);
$fimPeriodo = $view === 'mes' ? $inicioPeriodo->modify('+27 days') : $inicioPeriodo->modify('+6 days');
$anterior = $view === 'mes' ? $inicioPeriodo->modify('-28 days') : $inicioPeriodo->modify('-7 days');
$proximo = $view === 'mes' ? $inicioPeriodo->modify('+28 days') : $inicioPeriodo->modify('+7 days');

$nomesSemana = ['dom.', 'seg.', 'ter.', 'qua.', 'qui.', 'sex.', 'sáb.'];

$itens = [];
$erroCronograma = '';
try {
    $itens = cron_carregar_itens($pdo, $usuarioId, $inicioPeriodo->format('Y-m-d'), $fimPeriodo->format('Y-m-d'), (int)$cursoId);
} catch (Throwable $e) {
    $erroCronograma = 'Não foi possível carregar o cronograma agora.';
}

$itensPorData = [];
$totalItens = count($itens);
$totalConcluidos = 0;
foreach ($itens as $item) {
    $data = (string)$item['data_estudo'];
    $itensPorData[$data][] = $item;

    if ((int)($item['concluido'] ?? 0) === 1) {
        $totalConcluidos++;
    }
}
$progresso = $totalItens > 0 ? (int)round(($totalConcluidos / $totalItens) * 100) : 0;

$diasPeriodo = [];
for ($i = 0; $i <= (int)$inicioPeriodo->diff($fimPeriodo)->format('%a'); $i++) {
    $diasPeriodo[] = $inicioPeriodo->modify('+' . $i . ' days');
}
$rotuloPeriodo = cron_rotulo_periodo($inicioPeriodo, $fimPeriodo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma | Projeto Gabaritando</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{
            --yellow:#fab528;
            --yellow-dark:#d89a08;
            --page:#f5f5f6;
            --panel:#ffffff;
            --text:#111827;
            --muted:#667085;
            --line:#d9d9d9;
            --soft:#eeeeee;
            --success:#12b76a;
            --dark:#000000;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html,body{width:100%;min-height:100%}
        body{font-family:'Ubuntu',Arial,sans-serif;background:var(--page);color:var(--text)}
        a{text-decoration:none;color:inherit}
        button,input{font-family:inherit}

        .cron-page{
            width:100%;
            min-height:calc(100vh - 96px);
            background:var(--page);
            padding:15px 12px 34px;
        }

        .cron-card{
            width:100%;
            max-width:1228px;
            margin:0 auto;
            background:#fafafa;
            border-radius:7px;
            overflow:hidden;
            box-shadow:0 10px 30px rgba(15,23,42,.06);
        }

        .cron-titlebar{
            min-height:80px;
            background:var(--yellow);
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            color:#fff;
        }

        .cron-titlebar h1{
            font-size:2rem;
            font-weight:700;
            letter-spacing:.01em;
        }

        .cron-settings{
            position:absolute;
            right:28px;
            top:50%;
            transform:translateY(-50%);
            color:#fff;
        }

        .cron-settings svg{
            width:28px;
            height:28px;
            stroke-width:2.8;
            fill:#fff;
        }

        .cron-body{
            padding:22px 35px 28px;
            background:#fafafa;
        }

        .cron-toolbar{
            display:grid;
            grid-template-columns:330px 1fr 310px;
            gap:24px;
            align-items:center;
            margin-bottom:20px;
        }

        .progress-label{
            display:flex;
            align-items:center;
            gap:9px;
            font-size:1.05rem;
            color:#111;
            margin-bottom:6px;
        }

        .progress-info{
            width:18px;
            height:18px;
            border-radius:999px;
            background:var(--yellow);
            color:#fff;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:.78rem;
            font-weight:700;
        }

        .progress-track{
            display:grid;
            grid-template-columns:1fr auto;
            gap:10px;
            align-items:center;
        }

        .progress-bar{
            height:7px;
            background:#d9d9d9;
            border-radius:999px;
            overflow:hidden;
        }

        .progress-bar span{
            display:block;
            height:100%;
            width:var(--progress);
            background:var(--yellow);
            border-radius:999px;
        }

        .progress-num{
            font-size:1rem;
            font-weight:700;
            color:#222;
        }

        .period-nav{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:22px;
            font-size:1rem;
            color:#111;
        }

        .period-nav a{
            width:30px;
            height:30px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:var(--yellow);
        }

        .period-nav svg{
            width:21px;
            height:21px;
        }

        .period-label{
            min-width:230px;
            text-align:center;
            font-weight:500;
            letter-spacing:.02em;
        }

        .calendar-icon{
            color:var(--yellow);
            display:inline-flex;
        }

        .calendar-icon svg{
            width:25px;
            height:25px;
        }

        .view-toggle{
            display:grid;
            grid-template-columns:1fr 1fr 1fr;
            background:#d3d3d3;
            border-radius:999px;
            overflow:hidden;
        }

        .view-toggle a{
            min-height:25px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:.86rem;
            font-weight:700;
            color:#fff;
        }

        .view-toggle .today-link,
        .view-toggle .active{
            background:var(--yellow);
            color:#fff;
        }

        .week-head{
            display:grid;
            grid-template-columns:repeat(7,1fr);
            border:1px solid var(--line);
            border-bottom:0;
            border-radius:7px 7px 0 0;
            overflow:hidden;
            background:#f7f7f7;
        }

        .week-head div{
            min-height:66px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            border-right:1px solid var(--line);
            font-weight:700;
            color:#222;
        }

        .week-head div:last-child{border-right:0}
        .week-head small{font-size:.96rem}
        .week-head strong{font-size:1.36rem;margin-top:3px}

        .calendar-grid{
            display:grid;
            grid-template-columns:repeat(7,1fr);
            border-left:1px solid var(--line);
            border-top:1px solid var(--line);
        }

        .day-cell{
            min-height:112px;
            background:#fbfbfb;
            border-right:1px solid var(--line);
            border-bottom:1px solid var(--line);
            padding:10px;
            position:relative;
        }

        .cron-card.is-week .day-cell{
            min-height:335px;
        }

        .day-cell.is-today{
            box-shadow:inset 0 0 0 2px rgba(250,181,40,.65);
        }

        .day-list{
            display:grid;
            gap:10px;
        }

        .task{
            position:relative;
            display:block;
            border:0;
            width:100%;
            text-align:left;
            border-radius:0;
            padding:10px 34px 10px 10px;
            color:#fff;
            min-height:84px;
            background:#21b9c4;
            overflow:hidden;
            cursor:pointer;
        }

        .cron-card.is-month .task{
            min-height:84px;
        }

        .task.task-color-1{background:#21b9c4}
        .task.task-color-2{background:#12d8b4}
        .task.task-color-3{background:#050505}
        .task.task-color-4{background:#2468b5}
        .task.task-color-5{background:#03cf30}
        .task.task-color-6{background:#d5edf0}
        .task.task-color-7{background:#edc1a5}
        .task.task-color-8{background:#ecc99f}
        .task.task-color-9{background:#e30613}
        .task.task-color-10{background:#ead7ee}

        .task strong{
            display:block;
            font-size:.82rem;
            line-height:1.18;
            text-transform:uppercase;
            margin-bottom:14px;
            color:#fff;
            word-break:break-word;
        }

        .task span{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:8px;
            font-size:.78rem;
            line-height:1.2;
            font-weight:700;
            color:#fff;
        }



        .task-times{
            display:flex!important;
            align-items:center;
            justify-content:flex-start!important;
            gap:7px;
            margin-top:7px;
            opacity:.95;
        }

        .task-times small{
            display:inline-flex;
            align-items:center;
            gap:3px;
            font-size:.68rem;
            color:#fff;
            font-weight:700;
        }

        .task-times svg{
            width:12px;
            height:12px;
        }

        .task em{
            font-style:normal;
            min-width:0;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }

        .task-check{
            position:absolute;
            right:9px;
            top:10px;
            width:20px;
            height:20px;
            border:2px solid rgba(0,0,0,.46);
            background:#f7f7f7;
            cursor:pointer;
            appearance:none;
            -webkit-appearance:none;
        }

        .task-check:checked{
            background:var(--yellow);
            border-color:#fff;
        }

        .task-check:checked::after{
            content:'';
            position:absolute;
            left:4px;
            top:1px;
            width:8px;
            height:12px;
            border-right:3px solid #fff;
            border-bottom:3px solid #fff;
            transform:rotate(45deg);
        }

        .empty-period{
            min-height:70px;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            color:#667085;
            border:1px dashed #cfd4dc;
            border-radius:12px;
            background:#fff;
            margin:0 0 18px;
            padding:18px;
        }

        .empty-period strong{
            display:block;
            color:#111827;
            margin-bottom:5px;
        }

        .cron-error{
            background:#fff1f0;
            color:#b42318;
            border:1px solid #fecdca;
            border-radius:10px;
            padding:13px 15px;
            margin:0 0 18px;
            font-weight:700;
        }

        .saving-toast{
            position:fixed;
            right:18px;
            bottom:18px;
            background:#111;
            color:#fff;
            border-radius:10px;
            padding:11px 14px;
            font-size:.9rem;
            font-weight:700;
            box-shadow:0 14px 30px rgba(0,0,0,.22);
            opacity:0;
            transform:translateY(8px);
            transition:.18s ease;
            z-index:999;
        }

        .saving-toast.show{
            opacity:1;
            transform:translateY(0);
        }

        @media(max-width:980px){
            .cron-page{padding:12px}
            .cron-titlebar{min-height:70px}
            .cron-titlebar h1{font-size:1.55rem}
            .cron-settings{right:18px}
            .cron-body{padding:18px 14px 24px}
            .cron-toolbar{grid-template-columns:1fr;gap:16px}
            .period-label{min-width:0;font-size:.95rem}
            .period-nav{gap:10px}
            .week-head{display:none}
            .calendar-grid{grid-template-columns:1fr;border:0;gap:10px}
            .day-cell,.cron-card.is-week .day-cell{min-height:auto;border:1px solid var(--line);border-radius:10px;background:#fff}
            .day-cell::before{content:attr(data-dia);display:block;font-weight:700;color:#111;margin-bottom:10px}
            .view-toggle{max-width:310px;margin:0 auto;width:100%}
            .task{min-height:74px}
        }

        @media(max-width:520px){
            .cron-body{padding-left:10px;padding-right:10px}
            .cron-toolbar{gap:14px}
            .period-nav{flex-wrap:wrap}
            .period-label{order:-1;width:100%;font-weight:700}
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/barra-superior.php'; ?>

    <main class="cron-page">
        <section class="cron-card is-<?php echo cron_esc($view); ?>" aria-label="Cronograma de estudos">
            <header class="cron-titlebar">
                <h1>Cronograma</h1>
                <span class="cron-settings" aria-hidden="true"><i data-lucide="settings"></i></span>
            </header>

            <div class="cron-body">
                <?php if ($erroCronograma !== ''): ?>
                    <div class="cron-error"><?php echo cron_esc($erroCronograma); ?></div>
                <?php endif; ?>

                <div class="cron-toolbar">
                    <div class="progress-box">
                        <div class="progress-label">Progresso <span class="progress-info">i</span></div>
                        <div class="progress-track">
                            <div class="progress-bar" style="--progress: <?php echo (int)$progresso; ?>%;"><span></span></div>
                            <strong class="progress-num"><?php echo (int)$progresso; ?>%</strong>
                        </div>
                    </div>

                    <nav class="period-nav" aria-label="Navegação do cronograma">
                        <a href="<?php echo cron_esc(cron_data_query($anterior, $view, (int)$cursoId)); ?>" aria-label="Período anterior"><i data-lucide="chevron-left"></i></a>
                        <span class="period-label"><?php echo cron_esc($rotuloPeriodo); ?></span>
                        <a href="<?php echo cron_esc(cron_data_query($proximo, $view, (int)$cursoId)); ?>" aria-label="Próximo período"><i data-lucide="chevron-right"></i></a>
                        <span class="calendar-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
                    </nav>

                    <div class="view-toggle" aria-label="Visualização">
                        <a class="today-link" href="<?php echo cron_esc(cron_data_query($hoje, 'semana', (int)$cursoId)); ?>">Hoje</a>
                        <a class="<?php echo $view === 'semana' ? 'active' : ''; ?>" href="<?php echo cron_esc(cron_data_query($inicioPeriodo, 'semana', (int)$cursoId)); ?>">Semana</a>
                        <a class="<?php echo $view === 'mes' ? 'active' : ''; ?>" href="<?php echo cron_esc(cron_data_query($inicioPeriodo, 'mes', (int)$cursoId)); ?>">Mês</a>
                    </div>
                </div>

                <?php if ($totalItens === 0): ?>
                    <div class="empty-period">
                        <div>
                            <strong>Nenhum cronograma cadastrado para este período.</strong>
                            <span>Quando o admin adicionar atividades nesta semana ou mês, elas aparecem aqui automaticamente.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="week-head" aria-hidden="true">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php $dataHead = $inicioPeriodo->modify('+' . $i . ' days'); ?>
                        <div>
                            <small><?php echo cron_esc($nomesSemana[$i]); ?></small>
                            <strong><?php echo (int)$dataHead->format('d'); ?></strong>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="calendar-grid">
                    <?php foreach ($diasPeriodo as $indexDia => $dataObj): ?>
                        <?php
                            $dataChave = $dataObj->format('Y-m-d');
                            $diaSemana = (int)$dataObj->format('w');
                            $itensDia = $itensPorData[$dataChave] ?? [];
                            $isToday = $dataChave === $hoje->format('Y-m-d');
                        ?>
                        <article class="day-cell<?php echo $isToday ? ' is-today' : ''; ?>" data-dia="<?php echo cron_esc($nomesSemana[$diaSemana] . ' ' . (int)$dataObj->format('d')); ?>">
                            <div class="day-list">
                                <?php foreach ($itensDia as $indiceItem => $item): ?>
                                    <?php
                                        $concluido = (int)($item['concluido'] ?? 0) === 1;
                                        $corCustom = trim((string)($item['cor'] ?? ''));
                                        $classeCor = 'task-color-' . (($indexDia + $indiceItem) % 10 + 1);
                                        $linkItem = cron_link_item($item);
                                    ?>
                                    <div
                                        class="task <?php echo cron_esc($classeCor); ?>"
                                        data-link="<?php echo cron_esc($linkItem); ?>"
                                        role="link"
                                        tabindex="0"
                                        style="<?php echo $corCustom !== '' ? 'background:' . cron_esc($corCustom) . ';' : ''; ?>">
                                        <strong><?php echo cron_esc((string)$item['titulo']); ?></strong>
                                        <span>
                                            <em><?php echo cron_esc((string)($item['materia'] ?? 'Estudo')); ?></em>
                                            <b><?php echo cron_esc(cron_formata_minutos((int)($item['duracao_minutos'] ?? 60))); ?></b>
                                        </span>
                                        <?php if (((int)($item['video_minutos'] ?? 0) + (int)($item['pdf_minutos'] ?? 0) + (int)($item['questoes_minutos'] ?? 0)) > 0): ?>
                                            <span class="task-times">
                                                <?php if ((int)($item['video_minutos'] ?? 0) > 0): ?><small><i data-lucide="play-circle" aria-hidden="true"></i><?php echo cron_esc(cron_formata_minutos((int)$item['video_minutos'])); ?></small><?php endif; ?>
                                                <?php if ((int)($item['pdf_minutos'] ?? 0) > 0): ?><small><i data-lucide="file-text" aria-hidden="true"></i><?php echo cron_esc(cron_formata_minutos((int)$item['pdf_minutos'])); ?></small><?php endif; ?>
                                                <?php if ((int)($item['questoes_minutos'] ?? 0) > 0): ?><small><i data-lucide="list-checks" aria-hidden="true"></i><?php echo cron_esc(cron_formata_minutos((int)$item['questoes_minutos'])); ?></small><?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <input class="task-check" type="checkbox" data-id="<?php echo (int)$item['id']; ?>" <?php echo $concluido ? 'checked' : ''; ?> aria-label="Marcar atividade como concluída">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <div class="saving-toast" id="savingToast">Cronograma atualizado</div>

    <script>
        if (window.lucide) window.lucide.createIcons();

        (function(){
            const toast = document.getElementById('savingToast');
            let toastTimer = null;

            function mostrarToast(texto){
                if (!toast) return;
                toast.textContent = texto;
                toast.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function(){ toast.classList.remove('show'); }, 1800);
            }

            document.querySelectorAll('.task').forEach(function(task){
                task.addEventListener('click', function(event){
                    if (event.target && event.target.classList.contains('task-check')) {
                        return;
                    }

                    const link = task.getAttribute('data-link');
                    if (link) {
                        window.location.href = link;
                    }
                });

                task.addEventListener('keydown', function(event){
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    const link = task.getAttribute('data-link');
                    if (link) {
                        window.location.href = link;
                    }
                });
            });

            document.querySelectorAll('.task-check').forEach(function(check){
                check.addEventListener('click', function(event){
                    event.stopPropagation();
                });

                check.addEventListener('change', function(event){
                    event.preventDefault();
                    event.stopPropagation();

                    const valorAnterior = !check.checked;
                    const dados = new FormData();
                    dados.append('acao', 'toggle_conclusao');
                    dados.append('item_id', check.dataset.id || '0');
                    dados.append('concluido', check.checked ? '1' : '0');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: dados,
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    }).then(function(resp){
                        return resp.json();
                    }).then(function(json){
                        if (!json || !json.ok) throw new Error('erro');
                        mostrarToast('Cronograma atualizado');
                    }).catch(function(){
                        check.checked = valorAnterior;
                        mostrarToast('Não foi possível salvar');
                    });
                });
            });
        })();
    </script>
</body>
</html>
