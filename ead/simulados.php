<?php
// simulados.php
// Tela do aluno para fazer simulados e receber revisão automática.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
$cursoAcessoInclude = __DIR__ . '/includes/curso-acesso.php';
if (is_file($cursoAcessoInclude)) {
    require_once $cursoAcessoInclude;
}
$recompensasLib = __DIR__ . '/includes/recompensas-lib.php';
if (is_file($recompensasLib)) {
    require_once $recompensasLib;
}

if (!function_exists('sim_esc')) {
    function sim_esc(?string $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_usuario_id')) {
    function sim_usuario_id(): int
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

if (!function_exists('sim_tempo')) {
    function sim_tempo(int $segundos): string
    {
        $segundos = max(0, $segundos);
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        $seg = $segundos % 60;

        if ($horas > 0) {
            return sprintf('%dh %02dm %02ds', $horas, $minutos, $seg);
        }

        return sprintf('%02dm %02ds', $minutos, $seg);
    }
}

if (!function_exists('sim_percent')) {
    function sim_percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%';
    }
}

if (!function_exists('sim_bootstrap')) {
    function sim_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/includes/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        admin_cursos_bootstrap($pdo);

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

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_opcoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            questao_id INT UNSIGNED NOT NULL,
            letra VARCHAR(8) NOT NULL,
            texto TEXT NOT NULL,
            correta TINYINT(1) NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_opcoes_questao (questao_id),
            KEY idx_admin_simulado_opcoes_correta (correta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_tentativas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            simulado_id INT UNSIGNED NOT NULL,
            usuario_id BIGINT UNSIGNED DEFAULT NULL,
            iniciado_em DATETIME NOT NULL,
            finalizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            tempo_segundos INT UNSIGNED NOT NULL DEFAULT 0,
            total_questoes INT UNSIGNED NOT NULL DEFAULT 0,
            total_acertos INT UNSIGNED NOT NULL DEFAULT 0,
            percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status ENUM('finalizado','em_revisao') NOT NULL DEFAULT 'finalizado',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_tentativas_simulado (simulado_id),
            KEY idx_admin_simulado_tentativas_usuario (usuario_id),
            KEY idx_admin_simulado_tentativas_percentual (percentual)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_respostas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tentativa_id BIGINT UNSIGNED NOT NULL,
            questao_id INT UNSIGNED NOT NULL,
            opcao_id INT UNSIGNED DEFAULT NULL,
            correta TINYINT(1) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_respostas_tentativa (tentativa_id),
            KEY idx_admin_simulado_respostas_questao (questao_id),
            KEY idx_admin_simulado_respostas_opcao (opcao_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('sim_carregar_prova')) {
    function sim_carregar_prova(PDO $pdo, int $simuladoId, bool $somenteAtivo = true): ?array
    {
        $sqlStatus = $somenteAtivo ? " AND s.status = 'ativo'" : '';
        $stmt = $pdo->prepare("SELECT s.*, c.nome AS curso_nome FROM admin_simulados s LEFT JOIN admin_cursos c ON c.id = s.curso_id WHERE s.id = :id {$sqlStatus} LIMIT 1");
        $stmt->execute([':id' => $simuladoId]);
        $simulado = $stmt->fetch();
        if (!$simulado) {
            return null;
        }

        $stmtQuestoes = $pdo->prepare('SELECT * FROM admin_simulado_questoes WHERE simulado_id = :id ORDER BY ordem ASC, id ASC');
        $stmtQuestoes->execute([':id' => $simuladoId]);
        $questoes = $stmtQuestoes->fetchAll();

        $stmtOpcoes = $pdo->prepare('SELECT * FROM admin_simulado_opcoes WHERE questao_id = :id ORDER BY ordem ASC, id ASC');
        foreach ($questoes as &$questao) {
            $stmtOpcoes->execute([':id' => (int)$questao['id']]);
            $questao['opcoes'] = $stmtOpcoes->fetchAll();
        }
        unset($questao);

        $simulado['questoes'] = $questoes;
        return $simulado;
    }
}

$pdo = null;
$erro = '';
$sucesso = '';
$simulados = [];
$simuladoAtual = null;
$revisao = null;
$usuarioId = sim_usuario_id();
$simuladoId = max(0, (int)($_GET['simulado_id'] ?? $_GET['prova_id'] ?? $_POST['simulado_id'] ?? 0));

try {
    $pdo = admin_cursos_db();
    sim_bootstrap($pdo);
    if (function_exists('recompensas_bootstrap')) {
        recompensas_bootstrap($pdo);
    }
} catch (Throwable $e) {
    $erro = 'Não foi possível carregar os simulados: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'finalizar_simulado') {
    try {
        $simuladoAtual = sim_carregar_prova($pdo, $simuladoId, true);
        if (!$simuladoAtual) {
            throw new RuntimeException('Simulado não encontrado ou indisponível.');
        }
        $cursoSimuladoId = (int)($simuladoAtual['curso_id'] ?? 0);
        if ($cursoSimuladoId > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, $cursoSimuladoId)) {
            throw new RuntimeException('Este simulado pertence a um curso que não está liberado para o seu usuário.');
        }

        $respostas = $_POST['resposta'] ?? [];
        if (!is_array($respostas)) {
            $respostas = [];
        }

        $inicioTimestamp = max(0, (int)($_POST['inicio_ts'] ?? time()));
        $agora = time();
        $tempoSegundos = max(0, $agora - $inicioTimestamp);
        $tempoMaximo = (int)$simuladoAtual['tempo_minutos'] * 60;
        if ($tempoMaximo > 0) {
            $tempoSegundos = min($tempoSegundos, $tempoMaximo);
        }

        $totalQuestoes = count($simuladoAtual['questoes']);
        $totalAcertos = 0;
        $detalhes = [];

        foreach ($simuladoAtual['questoes'] as $questao) {
            $questaoId = (int)$questao['id'];
            $opcaoMarcadaId = isset($respostas[$questaoId]) ? (int)$respostas[$questaoId] : 0;
            $opcaoMarcada = null;
            $opcaoCorreta = null;

            foreach ($questao['opcoes'] as $opcao) {
                if ((int)$opcao['id'] === $opcaoMarcadaId) {
                    $opcaoMarcada = $opcao;
                }
                if ((int)$opcao['correta'] === 1) {
                    $opcaoCorreta = $opcao;
                }
            }

            $acertou = $opcaoMarcada && (int)$opcaoMarcada['correta'] === 1;
            if ($acertou) {
                $totalAcertos++;
            }

            $detalhes[] = [
                'questao' => $questao,
                'marcada' => $opcaoMarcada,
                'correta' => $opcaoCorreta,
                'acertou' => $acertou,
            ];
        }

        $percentual = $totalQuestoes > 0 ? round(($totalAcertos / $totalQuestoes) * 100, 2) : 0;
        $iniciadoEm = date('Y-m-d H:i:s', $inicioTimestamp > 0 ? $inicioTimestamp : $agora);

        $pdo->beginTransaction();
        $stmtTentativa = $pdo->prepare('INSERT INTO admin_simulado_tentativas (simulado_id, usuario_id, iniciado_em, finalizado_em, tempo_segundos, total_questoes, total_acertos, percentual, status) VALUES (:simulado_id, :usuario_id, :iniciado_em, NOW(), :tempo_segundos, :total_questoes, :total_acertos, :percentual, :status)');
        $stmtTentativa->execute([
            ':simulado_id' => (int)$simuladoAtual['id'],
            ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            ':iniciado_em' => $iniciadoEm,
            ':tempo_segundos' => $tempoSegundos,
            ':total_questoes' => $totalQuestoes,
            ':total_acertos' => $totalAcertos,
            ':percentual' => number_format($percentual, 2, '.', ''),
            ':status' => 'finalizado',
        ]);
        $tentativaId = (int)$pdo->lastInsertId();

        $stmtResposta = $pdo->prepare('INSERT INTO admin_simulado_respostas (tentativa_id, questao_id, opcao_id, correta) VALUES (:tentativa_id, :questao_id, :opcao_id, :correta)');
        foreach ($detalhes as $detalhe) {
            $stmtResposta->execute([
                ':tentativa_id' => $tentativaId,
                ':questao_id' => (int)$detalhe['questao']['id'],
                ':opcao_id' => $detalhe['marcada'] ? (int)$detalhe['marcada']['id'] : null,
                ':correta' => $detalhe['acertou'] ? 1 : 0,
            ]);
        }
        if (function_exists('recompensas_registrar_simulado_primeira_tentativa')) {
            try {
                recompensas_registrar_simulado_primeira_tentativa($pdo, $usuarioId, (int)$simuladoAtual['id'], $totalAcertos);
            } catch (Throwable $ignored) {
                // Não quebra a revisão se o XP falhar.
            }
        }

        $pdo->commit();

        $revisao = [
            'tentativa_id' => $tentativaId,
            'total_questoes' => $totalQuestoes,
            'total_acertos' => $totalAcertos,
            'percentual' => $percentual,
            'tempo_segundos' => $tempoSegundos,
            'detalhes' => $detalhes,
        ];
        $sucesso = 'Simulado enviado para revisão. Resultado salvo para o administrador.';
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e->getMessage();
    }
}

if ($pdo instanceof PDO) {
    try {
        if (!$simuladoAtual && $simuladoId > 0) {
            $simuladoAtual = sim_carregar_prova($pdo, $simuladoId, true);
            if (!$simuladoAtual) {
                $erro = $erro ?: 'Simulado não encontrado ou indisponível.';
            } elseif ((int)($simuladoAtual['curso_id'] ?? 0) > 0 && function_exists('gab_usuario_tem_acesso_curso') && !gab_usuario_tem_acesso_curso($pdo, $usuarioId, (int)$simuladoAtual['curso_id'])) {
                $simuladoAtual = null;
                $erro = 'Este simulado pertence a um curso que não está liberado para o seu usuário.';
            }
        }

        $filtroAcessoSimulados = '';
        $paramsAcessoSimulados = [];
        if (function_exists('gab_curso_acesso_configurado') && gab_curso_acesso_configurado($pdo, $usuarioId)) {
            $idsSimulados = function_exists('gab_curso_acesso_ids') ? gab_curso_acesso_ids($pdo, $usuarioId) : [];
            if (!$idsSimulados) {
                $filtroAcessoSimulados = ' AND (s.curso_id IS NULL OR s.curso_id = 0)';
            } else {
                $phSimulados = [];
                foreach ($idsSimulados as $idx => $cursoPermitidoId) {
                    $chave = ':sim_curso_' . $idx;
                    $phSimulados[] = $chave;
                    $paramsAcessoSimulados[$chave] = (int)$cursoPermitidoId;
                }
                $filtroAcessoSimulados = ' AND ((s.curso_id IS NULL OR s.curso_id = 0) OR s.curso_id IN (' . implode(',', $phSimulados) . '))';
            }
        }

        $stmtSimulados = $pdo->prepare("SELECT s.*, c.nome AS curso_nome,
            (SELECT COUNT(*) FROM admin_simulado_questoes q WHERE q.simulado_id = s.id) AS total_questoes
            FROM admin_simulados s
            LEFT JOIN admin_cursos c ON c.id = s.curso_id
            WHERE s.status = 'ativo'
            {$filtroAcessoSimulados}
            ORDER BY s.ordem ASC, s.id DESC");
        $stmtSimulados->execute($paramsAcessoSimulados);
        $simulados = $stmtSimulados->fetchAll();
    } catch (Throwable $e) {
        $erro = $erro ?: 'Erro ao carregar simulados: ' . $e->getMessage();
    }
}

$inicioTs = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <link rel="apple-touch-icon" href="/ead/favicon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulados | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <style>
        :root{--yellow:#fab528;--yellow-dark:#c98f0e;--page:#f5f5f6;--panel:#fafafa;--text:#111827;--muted:#667085;--line:#e7e9ee;--success:#039855;--danger:#d92d20;--shadow:0 10px 25px rgba(0,0,0,.05)}*{margin:0;padding:0;box-sizing:border-box}html,body{width:100%;min-height:100%}body{font-family:'Ubuntu',sans-serif;background:var(--page);color:var(--text)}a{text-decoration:none;color:inherit}button,input,textarea{font-family:inherit}img{display:block;max-width:100%}.page-wrap{width:100%;max-width:1230px;margin:34px auto 0;padding:0 0 42px}.simulados-card{background:var(--panel);border-radius:7px 7px 0 0;overflow:hidden;box-shadow:var(--shadow)}.simulados-title{min-height:80px;background:var(--yellow);color:#fff;display:flex;align-items:center;justify-content:center;text-align:center;font-size:2rem;font-weight:700;letter-spacing:.01em;border-radius:7px 7px 0 0}.simulados-body{min-height:66px;padding:24px 34px 48px;background:#fafafa}.sim-alert{display:flex;align-items:center;gap:10px;border-radius:10px;padding:13px 15px;margin-bottom:18px;font-weight:700}.sim-alert svg{width:20px;height:20px}.sim-alert.error{background:#fff1f0;color:#b42318}.sim-alert.success{background:#ecfdf3;color:#027a48}.sim-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.sim-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.04);display:flex;flex-direction:column;gap:14px}.sim-card-icon{width:46px;height:46px;border-radius:14px;background:#fff7db;color:#111827;display:flex;align-items:center;justify-content:center}.sim-card-icon svg{width:23px;height:23px;color:var(--yellow-dark)}.sim-card h2{font-size:1.12rem;line-height:1.25}.sim-card p{color:var(--muted);font-size:.94rem;line-height:1.45}.sim-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.sim-badge{display:inline-flex;align-items:center;justify-content:center;min-height:28px;border-radius:999px;background:#f2f4f7;color:#344054;padding:0 10px;font-size:.82rem;font-weight:800}.sim-badge.yellow{background:#fff7db;color:#111827}.sim-btn{border:0;border-radius:10px;min-height:42px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--yellow);color:#fff;font-weight:800;cursor:pointer;text-decoration:none}.sim-btn svg{width:18px;height:18px}.sim-btn.light{background:#fff;color:#344054;border:1px solid #d0d5dd}.sim-header-row{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:22px}.sim-header-tools{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}.sim-header-row h1{font-size:1.55rem;margin-bottom:8px}.sim-header-row p{color:var(--muted);line-height:1.5}.sim-timer{position:sticky;top:10px;z-index:20;display:inline-flex;align-items:center;gap:9px;min-height:44px;border-radius:999px;background:#111;color:#fff;padding:0 16px;font-weight:900}.sim-timer svg{width:19px;height:19px;color:var(--yellow)}.question-list{display:grid;gap:18px}.question-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.04)}.question-card h2{font-size:1.05rem;line-height:1.5;margin-bottom:15px}.option-list{display:grid;gap:10px}.option-item{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:flex-start;border:1px solid var(--line);background:#fff;border-radius:12px;padding:12px;cursor:pointer}.option-item input{margin-top:12px;accent-color:var(--yellow)}.option-letter{width:36px;height:36px;border-radius:999px;background:#fff7db;border:1px solid var(--yellow);display:inline-flex;align-items:center;justify-content:center;font-weight:900;color:#111827}.option-text{line-height:1.45;color:#344054;font-weight:600}.sim-submit-row{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-top:22px}.review-head{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:20px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.review-stat{background:#f9fafb;border-radius:12px;padding:16px}.review-stat span{display:block;color:var(--muted);font-size:.86rem;font-weight:700;margin-bottom:6px}.review-stat strong{font-size:1.45rem}.review-option{border:1px solid var(--line);border-radius:12px;padding:12px;margin-top:10px}.review-option.correct{border-color:#a6f4c5;background:#ecfdf3}.review-option.wrong{border-color:#fecdca;background:#fff1f0}.review-label{display:inline-flex;align-items:center;gap:7px;font-size:.82rem;font-weight:900;margin-bottom:6px}.review-label.correct{color:#027a48}.review-label.wrong{color:#b42318}.question-explanation{margin-top:12px;background:#fff7db;border-radius:10px;padding:12px;color:#344054;line-height:1.5;font-weight:600}.empty{min-height:220px;display:flex;align-items:center;justify-content:center;text-align:center;background:#fff;border:1px dashed #d0d5dd;border-radius:14px;color:var(--muted);padding:24px}.empty svg{width:44px;height:44px;color:#d0d5dd;margin-bottom:12px}.empty h2{color:#111827;font-size:1.1rem;margin-bottom:8px}@media(max-width:980px){.page-wrap{margin:24px auto 0;padding:0 14px 32px}.simulados-title{min-height:72px;font-size:1.75rem}.simulados-body{padding:24px 22px 40px}.sim-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.review-head{grid-template-columns:1fr}}@media(max-width:640px){.sim-header-tools{width:100%;justify-content:stretch}.sim-header-tools .sim-btn{width:100%}.page-wrap{margin:25px auto 0;padding:0 14px 28px}.simulados-title{min-height:62px;font-size:1.2rem}.simulados-body{padding:21px 14px 32px}.sim-grid{grid-template-columns:1fr}.sim-header-row h1{font-size:1.25rem}.sim-timer{width:100%;justify-content:center}.question-card{padding:15px}.option-item{grid-template-columns:34px 1fr;padding:10px}.option-letter{width:30px;height:30px}.sim-submit-row{justify-content:stretch}.sim-btn{width:100%}}
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/barra-superior.php'; ?>

    <main class="page-wrap">
        <section class="simulados-card" aria-label="Simulados">
            <header class="simulados-title">Simulados</header>
            <div class="simulados-body">
                <?php if ($erro !== ''): ?>
                    <div class="sim-alert error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo sim_esc($erro); ?></div>
                <?php endif; ?>
                <?php if ($sucesso !== ''): ?>
                    <div class="sim-alert success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo sim_esc($sucesso); ?></div>
                <?php endif; ?>

                <?php if ($revisao && $simuladoAtual): ?>
                    <div class="sim-header-row">
                        <div>
                            <h1>Revisão: <?php echo sim_esc((string)$simuladoAtual['titulo']); ?></h1>
                            <p>Confira suas respostas. O resultado já foi enviado para o administrador.</p>
                        </div>
                        <div class="sim-header-tools">
                            <a href="simulado-pdf.php?simulado_id=<?php echo (int)$simuladoAtual['id']; ?>" class="sim-btn light" target="_blank" rel="noopener"><i data-lucide="download" aria-hidden="true"></i>Baixar PDF</a>
                            <a href="simulados.php" class="sim-btn light"><i data-lucide="arrow-left" aria-hidden="true"></i>Voltar aos simulados</a>
                        </div>
                    </div>

                    <div class="review-head">
                        <div class="review-stat"><span>Acertos</span><strong><?php echo (int)$revisao['total_acertos']; ?> / <?php echo (int)$revisao['total_questoes']; ?></strong></div>
                        <div class="review-stat"><span>Porcentagem</span><strong><?php echo sim_esc(sim_percent((float)$revisao['percentual'])); ?></strong></div>
                        <div class="review-stat"><span>Tempo usado</span><strong><?php echo sim_esc(sim_tempo((int)$revisao['tempo_segundos'])); ?></strong></div>
                    </div>

                    <div class="question-list">
                        <?php foreach ($revisao['detalhes'] as $idx => $detalhe): ?>
                            <?php $questao = $detalhe['questao']; ?>
                            <article class="question-card">
                                <h2><?php echo ($idx + 1); ?>. <?php echo nl2br(sim_esc((string)$questao['enunciado'])); ?></h2>

                                <?php if ($detalhe['marcada']): ?>
                                    <div class="review-option <?php echo $detalhe['acertou'] ? 'correct' : 'wrong'; ?>">
                                        <div class="review-label <?php echo $detalhe['acertou'] ? 'correct' : 'wrong'; ?>">
                                            <i data-lucide="<?php echo $detalhe['acertou'] ? 'check-circle-2' : 'x-circle'; ?>" aria-hidden="true"></i>
                                            Sua resposta
                                        </div>
                                        <div class="option-text"><strong><?php echo sim_esc((string)$detalhe['marcada']['letra']); ?>.</strong> <?php echo nl2br(sim_esc((string)$detalhe['marcada']['texto'])); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="review-option wrong"><div class="review-label wrong"><i data-lucide="x-circle" aria-hidden="true"></i>Você não marcou resposta</div></div>
                                <?php endif; ?>

                                <?php if (!$detalhe['acertou'] && $detalhe['correta']): ?>
                                    <div class="review-option correct">
                                        <div class="review-label correct"><i data-lucide="check-circle-2" aria-hidden="true"></i>Resposta correta</div>
                                        <div class="option-text"><strong><?php echo sim_esc((string)$detalhe['correta']['letra']); ?>.</strong> <?php echo nl2br(sim_esc((string)$detalhe['correta']['texto'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if (trim((string)($questao['explicacao'] ?? '')) !== ''): ?>
                                    <div class="question-explanation"><strong>Explicação:</strong><br><?php echo nl2br(sim_esc((string)$questao['explicacao'])); ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($simuladoAtual): ?>
                    <?php $questoes = $simuladoAtual['questoes'] ?? []; ?>
                    <div class="sim-header-row">
                        <div>
                            <h1><?php echo sim_esc((string)$simuladoAtual['titulo']); ?></h1>
                            <p><?php echo nl2br(sim_esc((string)($simuladoAtual['descricao'] ?: 'Responda as questões e envie para revisão.'))); ?></p>
                            <div class="sim-meta" style="margin-top:12px;">
                                <span class="sim-badge yellow"><?php echo $simuladoAtual['curso_id'] ? sim_esc((string)$simuladoAtual['curso_nome']) : 'Todos os cursos'; ?></span>
                                <span class="sim-badge"><?php echo count($questoes); ?> <?php echo count($questoes) === 1 ? 'questão' : 'questões'; ?></span>
                                <span class="sim-badge"><?php echo (int)$simuladoAtual['tempo_minutos'] > 0 ? (int)$simuladoAtual['tempo_minutos'] . ' min' : 'Sem limite de tempo'; ?></span>
                            </div>
                        </div>
                        <div class="sim-header-tools">
                            <a href="simulado-pdf.php?simulado_id=<?php echo (int)$simuladoAtual['id']; ?>" class="sim-btn light" target="_blank" rel="noopener"><i data-lucide="download" aria-hidden="true"></i>Baixar PDF</a>
                            <?php if ((int)$simuladoAtual['tempo_minutos'] > 0): ?>
                                <div class="sim-timer" data-timer-seconds="<?php echo (int)$simuladoAtual['tempo_minutos'] * 60; ?>"><i data-lucide="timer" aria-hidden="true"></i><span id="timerText"><?php echo (int)$simuladoAtual['tempo_minutos']; ?>:00</span></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (trim((string)($simuladoAtual['instrucoes'] ?? '')) !== ''): ?>
                        <div class="sim-alert success" style="background:#fff7db;color:#111827;"><i data-lucide="info" aria-hidden="true"></i><?php echo nl2br(sim_esc((string)$simuladoAtual['instrucoes'])); ?></div>
                    <?php endif; ?>

                    <?php if (!$questoes): ?>
                        <div class="empty"><div><i data-lucide="clipboard-x" aria-hidden="true"></i><h2>Simulado sem questões</h2><p>Esse simulado ainda não possui questões cadastradas.</p></div></div>
                    <?php else: ?>
                        <form method="post" id="formSimuladoAluno">
                            <input type="hidden" name="acao" value="finalizar_simulado">
                            <input type="hidden" name="simulado_id" value="<?php echo (int)$simuladoAtual['id']; ?>">
                            <input type="hidden" name="inicio_ts" value="<?php echo (int)$inicioTs; ?>">

                            <div class="question-list">
                                <?php foreach ($questoes as $idx => $questao): ?>
                                    <article class="question-card">
                                        <h2><?php echo ($idx + 1); ?>. <?php echo nl2br(sim_esc((string)$questao['enunciado'])); ?></h2>
                                        <div class="option-list" role="radiogroup" aria-label="Opções da questão <?php echo $idx + 1; ?>">
                                            <?php foreach (($questao['opcoes'] ?? []) as $opcao): ?>
                                                <label class="option-item">
                                                    <input type="radio" name="resposta[<?php echo (int)$questao['id']; ?>]" value="<?php echo (int)$opcao['id']; ?>">
                                                    <div>
                                                        <span class="option-letter"><?php echo sim_esc((string)$opcao['letra']); ?></span>
                                                        <span class="option-text"><?php echo nl2br(sim_esc((string)$opcao['texto'])); ?></span>
                                                    </div>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <div class="sim-submit-row">
                                <a class="sim-btn light" href="simulados.php"><i data-lucide="arrow-left" aria-hidden="true"></i>Cancelar</a>
                                <button class="sim-btn" type="submit"><i data-lucide="send" aria-hidden="true"></i>Enviar para revisão</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sim-header-row">
                        <div>
                            <h1>Escolha um simulado</h1>
                            <p>Selecione uma prova disponível para iniciar. Ao finalizar, o sistema mostra a revisão automática e envia o resultado para o administrador.</p>
                        </div>
                    </div>

                    <?php if (!$simulados): ?>
                        <div class="empty"><div><i data-lucide="clipboard-check" aria-hidden="true"></i><h2>Nenhum simulado disponível</h2><p>Assim que o administrador cadastrar um simulado ativo, ele aparece aqui.</p></div></div>
                    <?php else: ?>
                        <div class="sim-grid">
                            <?php foreach ($simulados as $simulado): ?>
                                <article class="sim-card">
                                    <div class="sim-card-icon"><i data-lucide="clipboard-check" aria-hidden="true"></i></div>
                                    <div>
                                        <h2><?php echo sim_esc((string)$simulado['titulo']); ?></h2>
                                        <p><?php echo sim_esc(admin_cursos_texto_curto((string)($simulado['descricao'] ?? ''), 110)); ?></p>
                                    </div>
                                    <div class="sim-meta">
                                        <span class="sim-badge yellow"><?php echo $simulado['curso_id'] ? sim_esc((string)$simulado['curso_nome']) : 'Todos os cursos'; ?></span>
                                        <span class="sim-badge"><?php echo (int)$simulado['total_questoes']; ?> questões</span>
                                        <span class="sim-badge"><?php echo (int)$simulado['tempo_minutos'] > 0 ? (int)$simulado['tempo_minutos'] . ' min' : 'Sem limite'; ?></span>
                                    </div>
                                    <a class="sim-btn" href="simulados.php?simulado_id=<?php echo (int)$simulado['id']; ?>"><i data-lucide="play" aria-hidden="true"></i>Iniciar simulado</a>
                                    <a class="sim-btn light" href="simulado-pdf.php?simulado_id=<?php echo (int)$simulado['id']; ?>" target="_blank" rel="noopener"><i data-lucide="download" aria-hidden="true"></i>Baixar PDF</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        if (window.lucide) window.lucide.createIcons();
        (function(){
            const timer = document.querySelector('[data-timer-seconds]');
            const text = document.getElementById('timerText');
            const form = document.getElementById('formSimuladoAluno');
            if (!timer || !text || !form) return;
            let seconds = parseInt(timer.getAttribute('data-timer-seconds') || '0', 10);
            function format(s){
                s = Math.max(0, s);
                const h = Math.floor(s / 3600);
                const m = Math.floor((s % 3600) / 60);
                const sec = s % 60;
                if (h > 0) return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
                return String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
            }
            text.textContent = format(seconds);
            const interval = setInterval(function(){
                seconds--;
                text.textContent = format(seconds);
                if (seconds <= 0) {
                    clearInterval(interval);
                    alert('Tempo encerrado. O simulado será enviado para revisão.');
                    form.submit();
                }
            }, 1000);
        })();
    </script>
</body>
</html>
