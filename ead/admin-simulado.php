<?php
// admin-simulado.php
// Painel administrativo para criar simulados, questões, alternativas e acompanhar resultados dos alunos.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-banco-questoes-lib.php';

if (!function_exists('admin_sim_int')) {
    function admin_sim_int(mixed $value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('admin_sim_percent')) {
    function admin_sim_percent(mixed $value): string
    {
        $numero = (float)$value;
        return rtrim(rtrim(number_format($numero, 2, ',', '.'), '0'), ',') . '%';
    }
}

if (!function_exists('admin_sim_tempo')) {
    function admin_sim_tempo(int $segundos): string
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

if (!function_exists('admin_sim_format_date')) {
    function admin_sim_format_date(?string $data): string
    {
        if (!$data) {
            return '-';
        }

        try {
            return (new DateTime($data))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $data;
        }
    }
}

if (!function_exists('admin_sim_bootstrap')) {
    function admin_sim_bootstrap(PDO $pdo): void
    {
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
            banco_questao_id INT UNSIGNED DEFAULT NULL,
            enunciado TEXT NOT NULL,
            explicacao TEXT NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_questoes_simulado (simulado_id),
            KEY idx_admin_simulado_questoes_banco (banco_questao_id),
            KEY idx_admin_simulado_questoes_ordem (ordem),
            CONSTRAINT fk_admin_simulado_questoes_simulado
                FOREIGN KEY (simulado_id) REFERENCES admin_simulados(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_admin_simulado_questoes_banco
                FOREIGN KEY (banco_questao_id) REFERENCES admin_banco_questoes(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!admin_bq_has_column($pdo, 'admin_simulado_questoes', 'banco_questao_id')) {
            admin_bq_try_exec($pdo, "ALTER TABLE admin_simulado_questoes ADD COLUMN banco_questao_id INT UNSIGNED DEFAULT NULL AFTER simulado_id");
        }
        admin_bq_try_exec($pdo, "ALTER TABLE admin_simulado_questoes ADD INDEX idx_admin_simulado_questoes_banco (banco_questao_id)");
        admin_bq_try_exec($pdo, "ALTER TABLE admin_simulado_questoes ADD CONSTRAINT fk_admin_simulado_questoes_banco FOREIGN KEY (banco_questao_id) REFERENCES admin_banco_questoes(id) ON DELETE SET NULL");

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
            KEY idx_admin_simulado_opcoes_correta (correta),
            CONSTRAINT fk_admin_simulado_opcoes_questao
                FOREIGN KEY (questao_id) REFERENCES admin_simulado_questoes(id)
                ON DELETE CASCADE
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
            KEY idx_admin_simulado_tentativas_percentual (percentual),
            CONSTRAINT fk_admin_simulado_tentativas_simulado
                FOREIGN KEY (simulado_id) REFERENCES admin_simulados(id)
                ON DELETE CASCADE
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
            KEY idx_admin_simulado_respostas_opcao (opcao_id),
            CONSTRAINT fk_admin_simulado_respostas_tentativa
                FOREIGN KEY (tentativa_id) REFERENCES admin_simulado_tentativas(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('admin_sim_letra')) {
    function admin_sim_letra(int $index): string
    {
        $index = max(0, $index);
        $letras = '';
        do {
            $letras = chr(65 + ($index % 26)) . $letras;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $letras;
    }
}

if (!function_exists('admin_sim_validar_payload')) {
    function admin_sim_validar_payload(array $post): array
    {
        $questoes = [];
        $chaves = $post['questoes_chave'] ?? [];

        if (!is_array($chaves)) {
            $chaves = [];
        }

        foreach ($chaves as $posicao => $chaveBruta) {
            $chave = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$chaveBruta);
            if ($chave === '') {
                continue;
            }

            $enunciado = trim((string)($post['questao_enunciado'][$chave] ?? ''));
            $explicacao = trim((string)($post['questao_explicacao'][$chave] ?? ''));
            $ordemQuestao = admin_sim_int($post['questao_ordem'][$chave] ?? ($posicao + 1));
            $bancoQuestaoId = admin_sim_int($post['questao_banco_id'][$chave] ?? 0);
            $opcoesTexto = $post['opcao_texto'][$chave] ?? [];
            $corretaIndex = isset($post['correta'][$chave]) ? (int)$post['correta'][$chave] : -1;

            if ($enunciado === '') {
                continue;
            }

            if (!is_array($opcoesTexto)) {
                $opcoesTexto = [];
            }

            $opcoes = [];
            foreach ($opcoesTexto as $idx => $textoOpcao) {
                $texto = trim((string)$textoOpcao);
                if ($texto === '') {
                    continue;
                }

                $opcoes[] = [
                    'texto' => $texto,
                    'correta' => ((int)$idx === $corretaIndex),
                    'ordem' => count($opcoes) + 1,
                    'letra' => admin_sim_letra(count($opcoes)),
                ];
            }

            if (count($opcoes) < 2) {
                throw new RuntimeException('Cada questão precisa ter pelo menos duas opções de resposta.');
            }

            $temCorreta = false;
            foreach ($opcoes as $opcao) {
                if ($opcao['correta']) {
                    $temCorreta = true;
                    break;
                }
            }

            if (!$temCorreta) {
                throw new RuntimeException('Selecione a resposta correta em todas as questões.');
            }

            $questoes[] = [
                'banco_questao_id' => $bancoQuestaoId > 0 ? $bancoQuestaoId : null,
                'enunciado' => $enunciado,
                'explicacao' => $explicacao,
                'ordem' => $ordemQuestao,
                'opcoes' => $opcoes,
            ];
        }

        if (!$questoes) {
            throw new RuntimeException('Adicione pelo menos uma questão ao simulado.');
        }

        return $questoes;
    }
}

if (!function_exists('admin_sim_salvar_questoes')) {
    function admin_sim_salvar_questoes(PDO $pdo, int $simuladoId, array $questoes): void
    {
        $pdo->prepare('DELETE FROM admin_simulado_questoes WHERE simulado_id = :simulado_id')->execute([':simulado_id' => $simuladoId]);

        $stmtQuestao = $pdo->prepare('INSERT INTO admin_simulado_questoes (simulado_id, banco_questao_id, enunciado, explicacao, ordem) VALUES (:simulado_id, :banco_questao_id, :enunciado, :explicacao, :ordem)');
        $stmtOpcao = $pdo->prepare('INSERT INTO admin_simulado_opcoes (questao_id, letra, texto, correta, ordem) VALUES (:questao_id, :letra, :texto, :correta, :ordem)');

        foreach ($questoes as $questao) {
            $stmtQuestao->execute([
                ':simulado_id' => $simuladoId,
                ':banco_questao_id' => !empty($questao['banco_questao_id']) ? (int)$questao['banco_questao_id'] : null,
                ':enunciado' => $questao['enunciado'],
                ':explicacao' => $questao['explicacao'],
                ':ordem' => (int)$questao['ordem'],
            ]);

            $questaoId = (int)$pdo->lastInsertId();
            foreach ($questao['opcoes'] as $opcao) {
                $stmtOpcao->execute([
                    ':questao_id' => $questaoId,
                    ':letra' => $opcao['letra'],
                    ':texto' => $opcao['texto'],
                    ':correta' => $opcao['correta'] ? 1 : 0,
                    ':ordem' => (int)$opcao['ordem'],
                ]);
            }
        }
    }
}

if (!function_exists('admin_sim_obter_estrutura')) {
    function admin_sim_obter_estrutura(PDO $pdo, int $simuladoId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM admin_simulado_questoes WHERE simulado_id = :id ORDER BY ordem ASC, id ASC');
        $stmt->execute([':id' => $simuladoId]);
        $questoes = $stmt->fetchAll();

        if (!$questoes) {
            return [];
        }

        $stmtOpcoes = $pdo->prepare('SELECT * FROM admin_simulado_opcoes WHERE questao_id = :id ORDER BY ordem ASC, id ASC');
        foreach ($questoes as &$questao) {
            $stmtOpcoes->execute([':id' => (int)$questao['id']]);
            $questao['opcoes'] = $stmtOpcoes->fetchAll();
        }
        unset($questao);

        return $questoes;
    }
}

$erro = '';
$sucesso = '';
$pdo = null;

try {
    $pdo = admin_cursos_db();
    admin_bq_bootstrap($pdo);
    admin_sim_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_simulado' || $acao === 'editar_simulado') {
            $simuladoId = admin_sim_int($_POST['simulado_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $instrucoes = trim((string)($_POST['instrucoes'] ?? ''));
            $cursoId = admin_sim_int($_POST['curso_id'] ?? 0);
            $tempoMinutos = admin_sim_int($_POST['tempo_minutos'] ?? 0);
            $status = in_array(($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true) ? (string)$_POST['status'] : 'ativo';
            $ordem = (int)($_POST['ordem'] ?? 0);
            $questoes = admin_sim_validar_payload($_POST);

            if ($titulo === '') {
                throw new RuntimeException('Informe o título do simulado.');
            }

            if ($acao === 'editar_simulado' && $simuladoId <= 0) {
                throw new RuntimeException('Simulado inválido para edição.');
            }

            $pdo->beginTransaction();

            if ($acao === 'criar_simulado') {
                $stmt = $pdo->prepare('INSERT INTO admin_simulados (curso_id, titulo, descricao, instrucoes, tempo_minutos, status, ordem) VALUES (:curso_id, :titulo, :descricao, :instrucoes, :tempo_minutos, :status, :ordem)');
                $stmt->execute([
                    ':curso_id' => $cursoId > 0 ? $cursoId : null,
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':instrucoes' => $instrucoes,
                    ':tempo_minutos' => $tempoMinutos,
                    ':status' => $status,
                    ':ordem' => $ordem,
                ]);
                $simuladoId = (int)$pdo->lastInsertId();
                $redirect = 'admin-simulado.php?sucesso=simulado_criado';
            } else {
                $stmt = $pdo->prepare('UPDATE admin_simulados SET curso_id = :curso_id, titulo = :titulo, descricao = :descricao, instrucoes = :instrucoes, tempo_minutos = :tempo_minutos, status = :status, ordem = :ordem WHERE id = :id LIMIT 1');
                $stmt->execute([
                    ':curso_id' => $cursoId > 0 ? $cursoId : null,
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':instrucoes' => $instrucoes,
                    ':tempo_minutos' => $tempoMinutos,
                    ':status' => $status,
                    ':ordem' => $ordem,
                    ':id' => $simuladoId,
                ]);
                $redirect = 'admin-simulado.php?sucesso=simulado_atualizado';
            }

            admin_sim_salvar_questoes($pdo, $simuladoId, $questoes);
            $pdo->commit();

            admin_cursos_redirect($redirect);
        }

        if ($acao === 'excluir_simulado') {
            $simuladoId = admin_sim_int($_POST['simulado_id'] ?? 0);
            if ($simuladoId <= 0) {
                throw new RuntimeException('Simulado inválido.');
            }

            $stmt = $pdo->prepare('DELETE FROM admin_simulados WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $simuladoId]);
            admin_cursos_redirect('admin-simulado.php?sucesso=simulado_excluido');
        }
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = $e->getMessage();
    }
}

$mensagensSucesso = [
    'simulado_criado' => 'Simulado criado com sucesso.',
    'simulado_atualizado' => 'Simulado atualizado com sucesso.',
    'simulado_excluido' => 'Simulado excluído com sucesso.',
];
$sucessoKey = (string)($_GET['sucesso'] ?? '');
if ($sucessoKey !== '' && isset($mensagensSucesso[$sucessoKey])) {
    $sucesso = $mensagensSucesso[$sucessoKey];
}

$cursos = [];
$simulados = [];
$totais = ['simulados' => 0, 'questoes' => 0, 'tentativas' => 0];
$simuladoEditando = null;
$questoesEditando = [];
$resultados = [];
$simuladoResultados = null;
$editarId = admin_sim_int($_GET['editar'] ?? 0);
$resultadosId = admin_sim_int($_GET['resultados'] ?? 0);
$abrirCriar = (string)($_GET['modal'] ?? '') === 'criar';
$bancasBanco = [];
$materiasBanco = [];
$topicosBanco = [];
$anosBanco = [];
$questoesBanco = [];

if ($pdo instanceof PDO) {
    try {
        $cursos = $pdo->query("SELECT id, nome FROM admin_cursos ORDER BY ordem ASC, nome ASC")->fetchAll();

        if ($abrirCriar || $editarId > 0) {
            $bancasBanco = $pdo->query("SELECT id, nome, sigla FROM admin_bancas WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();
            $materiasBanco = $pdo->query("SELECT id, nome FROM admin_materias WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll();
            $topicosBanco = $pdo->query("SELECT id, materia_id, titulo FROM admin_materia_topicos WHERE status = 'ativo' ORDER BY ordem ASC, titulo ASC")->fetchAll();
            $anosBanco = $pdo->query("SELECT DISTINCT ano FROM admin_banco_questoes WHERE status = 'ativo' AND ano IS NOT NULL ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);

            $stmtBanco = $pdo->query("SELECT q.id, q.banca_id, q.materia_id, q.materia_topico_id, q.ano, q.enunciado, q.explicacao, q.dificuldade,
                    b.nome AS banca_nome, b.sigla AS banca_sigla, m.nome AS materia_nome, mt.titulo AS topico_nome
                FROM admin_banco_questoes q
                INNER JOIN admin_bancas b ON b.id = q.banca_id
                INNER JOIN admin_materias m ON m.id = q.materia_id
                LEFT JOIN admin_materia_topicos mt ON mt.id = q.materia_topico_id
                WHERE q.status = 'ativo'
                ORDER BY m.nome ASC, mt.titulo ASC, q.ordem ASC, q.id DESC");
            $questoesBanco = $stmtBanco->fetchAll();

            if ($questoesBanco) {
                $idsBanco = array_map(static fn(array $q): int => (int)$q['id'], $questoesBanco);
                $marcadores = implode(',', array_fill(0, count($idsBanco), '?'));
                $stmtOpBanco = $pdo->prepare("SELECT questao_id, letra, texto, correta, ordem FROM admin_banco_questao_opcoes WHERE questao_id IN ({$marcadores}) ORDER BY questao_id ASC, ordem ASC, id ASC");
                $stmtOpBanco->execute($idsBanco);
                $opcoesPorQuestao = [];
                foreach ($stmtOpBanco->fetchAll() as $opcaoBanco) {
                    $opcoesPorQuestao[(int)$opcaoBanco['questao_id']][] = $opcaoBanco;
                }
                foreach ($questoesBanco as &$questaoBanco) {
                    $questaoBanco['opcoes'] = $opcoesPorQuestao[(int)$questaoBanco['id']] ?? [];
                }
                unset($questaoBanco);
            }
        }

        $stmt = $pdo->query("SELECT s.*, c.nome AS curso_nome,
            (SELECT COUNT(*) FROM admin_simulado_questoes q WHERE q.simulado_id = s.id) AS total_questoes,
            (SELECT COUNT(*) FROM admin_simulado_tentativas t WHERE t.simulado_id = s.id) AS total_tentativas,
            (SELECT AVG(t.percentual) FROM admin_simulado_tentativas t WHERE t.simulado_id = s.id) AS media_percentual
            FROM admin_simulados s
            LEFT JOIN admin_cursos c ON c.id = s.curso_id
            ORDER BY s.ordem ASC, s.id DESC");
        $simulados = $stmt->fetchAll();

        $totais['simulados'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_simulados')->fetchColumn();
        $totais['questoes'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_simulado_questoes')->fetchColumn();
        $totais['tentativas'] = (int)$pdo->query('SELECT COUNT(*) FROM admin_simulado_tentativas')->fetchColumn();

        if ($editarId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_simulados WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarId]);
            $simuladoEditando = $stmt->fetch() ?: null;
            if ($simuladoEditando) {
                $questoesEditando = admin_sim_obter_estrutura($pdo, $editarId);
            }
        }

        if ($resultadosId > 0) {
            $stmt = $pdo->prepare('SELECT s.*, c.nome AS curso_nome FROM admin_simulados s LEFT JOIN admin_cursos c ON c.id = s.curso_id WHERE s.id = :id LIMIT 1');
            $stmt->execute([':id' => $resultadosId]);
            $simuladoResultados = $stmt->fetch() ?: null;

            if ($simuladoResultados) {
                $stmt = $pdo->prepare("SELECT t.*, u.nome AS usuario_nome, u.email AS usuario_email
                    FROM admin_simulado_tentativas t
                    LEFT JOIN usuarios u ON u.id = t.usuario_id
                    WHERE t.simulado_id = :id
                    ORDER BY t.finalizado_em DESC, t.id DESC");
                $stmt->execute([':id' => $resultadosId]);
                $resultados = $stmt->fetchAll();
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar simulados: ' . $e->getMessage();
    }
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$formAberto = $abrirCriar || $simuladoEditando;
$formAcao = $simuladoEditando ? 'editar_simulado' : 'criar_simulado';
$formTitulo = $simuladoEditando ? 'Editar simulado' : 'Criar simulado';
$formDescricao = $simuladoEditando ? 'Atualize as informações e escolha questões do banco ou crie questões próprias.' : 'Monte o simulado usando questões já cadastradas no banco ou criando questões próprias.';
$dadosForm = $simuladoEditando ?: [
    'id' => 0,
    'curso_id' => null,
    'titulo' => '',
    'descricao' => '',
    'instrucoes' => '',
    'tempo_minutos' => 0,
    'status' => 'ativo',
    'ordem' => 0,
];
if (!$questoesEditando) {
    $questoesEditando = [[
        'id' => 0,
        'banco_questao_id' => null,
        'enunciado' => '',
        'explicacao' => '',
        'ordem' => 1,
        'opcoes' => [
            ['id' => 0, 'texto' => '', 'correta' => 1, 'letra' => 'A', 'ordem' => 1],
            ['id' => 0, 'texto' => '', 'correta' => 0, 'letra' => 'B', 'ordem' => 2],
            ['id' => 0, 'texto' => '', 'correta' => 0, 'letra' => 'C', 'ordem' => 3],
            ['id' => 0, 'texto' => '', 'correta' => 0, 'letra' => 'D', 'ordem' => 4],
        ],
    ]];
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
    <title>Admin Simulados | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .sim-modal-backdrop{align-items:flex-start !important;padding:16px !important;overflow-y:auto !important}
        .sim-modal{width:min(1220px,100%) !important;max-height:calc(100vh - 32px);display:flex;flex-direction:column;overflow:hidden}
        .sim-modal .admin-modal-body{overflow-y:auto;padding-bottom:30px}
        .sim-builder{display:grid;gap:18px;margin-top:18px}
        .sim-question{border:1px solid #e4e7ec;background:#fff;border-radius:14px;padding:18px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
        .sim-question[data-bank-id]:not([data-bank-id="0"]){border-color:#fdbd08;box-shadow:0 8px 22px rgba(253,189,8,.08)}
        .sim-question-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:14px}
        .sim-question-head strong{font-size:1rem;color:#111827;display:flex;align-items:center;gap:9px;flex-wrap:wrap}
        .sim-source-badge{display:inline-flex;align-items:center;gap:5px;min-height:25px;padding:0 9px;border-radius:999px;background:#fff7db;color:#8a6100;font-size:.72rem;font-weight:800}
        .sim-source-badge svg{width:14px;height:14px}
        .sim-remove-btn{border:1px solid #fee4e2;background:#fff;color:#b42318;border-radius:9px;min-height:36px;padding:0 12px;display:inline-flex;align-items:center;gap:7px;font-weight:700;cursor:pointer}
        .sim-remove-btn svg{width:16px;height:16px}
        .sim-options{display:grid;gap:10px;margin-top:12px}
        .sim-option{display:grid;grid-template-columns:46px minmax(0,1fr) auto;gap:10px;align-items:center}
        .sim-option input[type="text"]{width:100%;min-width:0}
        .sim-option-letter{width:42px;height:42px;border-radius:999px;background:#fff7db;color:#111827;display:flex;align-items:center;justify-content:center;font-weight:800;border:1px solid #fdbd08}
        .sim-correct{display:flex;align-items:center;gap:8px;color:#344054;font-size:.9rem;font-weight:700;white-space:nowrap}
        .sim-correct input{width:17px;height:17px;accent-color:#fdbd08}
        .sim-option-remove{border:0;background:transparent;color:#b42318;cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:9px;flex:0 0 36px}
        .sim-option-remove:hover{background:#fff1f0}
        .sim-option-tools{display:flex;align-items:center;gap:8px;min-width:max-content}
        .sim-add-line{display:flex;justify-content:flex-start;margin-top:12px}
        .sim-mini-btn{min-height:36px;border:1px solid #e4e7ec;background:#fff;color:#344054;border-radius:9px;padding:0 12px;display:inline-flex;align-items:center;gap:8px;font-weight:700;cursor:pointer}
        .sim-mini-btn svg{width:16px;height:16px}
        .sim-bottom-add{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:20px 0 4px}
        .sim-bottom-add .admin-btn{min-height:44px;padding-left:18px;padding-right:18px}
        .sim-form-divider{height:1px;background:#eef0f3;margin:10px 0}
        .sim-status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:28px;border-radius:999px;padding:0 10px;font-size:.8rem;font-weight:800}
        .sim-status-pill.ativo{background:#ecfdf3;color:#027a48}
        .sim-status-pill.inativo{background:#f2f4f7;color:#667085}
        .sim-course-badge{display:inline-flex;align-items:center;min-height:28px;border-radius:999px;background:#fff7db;color:#111827;padding:0 10px;font-size:.82rem;font-weight:800}
        .sim-results-title{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .sim-results-title h2{font-size:1.15rem;color:#111827}
        .sim-score{font-weight:900}
        .sim-score.good{color:#027a48}.sim-score.mid{color:#b54708}.sim-score.bad{color:#b42318}
        .sim-bank-panel{margin-top:18px;border:1px solid #f4d88b;background:#fffdf6;border-radius:14px;overflow:hidden}
        .sim-bank-panel-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 18px;background:#fff7db;border-bottom:1px solid #f4d88b}
        .sim-bank-panel-head h3{margin:0;color:#111827;font-size:1rem}
        .sim-bank-panel-head p{margin:4px 0 0;color:#667085;font-size:.84rem}
        .sim-bank-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:30px;padding:0 10px;border-radius:999px;background:#fdbd08;color:#111827;font-weight:900}
        .sim-bank-body{padding:16px 18px}
        .sim-bank-filters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
        .sim-bank-search{grid-column:span 2}
        .sim-bank-list{display:grid;gap:10px;max-height:390px;overflow:auto;margin-top:14px;padding-right:4px}
        .sim-bank-empty{padding:28px 16px;text-align:center;border:1px dashed #d0d5dd;border-radius:12px;color:#667085;background:#fff}
        .sim-bank-item{display:grid;grid-template-columns:24px minmax(0,1fr);gap:12px;align-items:flex-start;border:1px solid #e4e7ec;border-radius:12px;background:#fff;padding:13px;cursor:pointer;transition:.18s ease}
        .sim-bank-item:hover{border-color:#fdbd08;box-shadow:0 6px 16px rgba(15,23,42,.05)}
        .sim-bank-item.is-selected{border-color:#fdbd08;background:#fffaf0}
        .sim-bank-item input{width:18px;height:18px;margin-top:3px;accent-color:#fdbd08}
        .sim-bank-question{font-size:.92rem;font-weight:700;color:#111827;line-height:1.4}
        .sim-bank-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:8px}
        .sim-bank-tag{display:inline-flex;align-items:center;min-height:24px;padding:0 8px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:.72rem;font-weight:800}
        .sim-bank-tag.yellow{background:#fff7db;color:#8a6100}.sim-bank-tag.blue{background:#eff8ff;color:#175cd3}.sim-bank-tag.green{background:#ecfdf3;color:#067647}
        .sim-bank-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid #eef0f3}
        .sim-bank-actions-left,.sim-bank-actions-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .sim-bank-selected-text{font-size:.84rem;color:#475467;font-weight:700}
        @media(max-width:1040px){.sim-bank-filters{grid-template-columns:repeat(3,minmax(0,1fr))}.sim-bank-search{grid-column:span 3}}
        @media(max-width:960px){.sim-option{grid-template-columns:40px minmax(0,1fr)}.sim-option-tools{grid-column:2/-1;justify-content:space-between;min-width:0}.sim-correct{white-space:normal}}
        @media(max-width:760px){.sim-modal-backdrop{padding:10px !important}.sim-modal{max-height:calc(100vh - 20px)}.sim-question{padding:14px}.sim-question-head{align-items:flex-start}.sim-remove-btn span{display:none}.sim-bank-panel-head{align-items:flex-start}.sim-bank-body{padding:12px}.sim-bank-filters{grid-template-columns:1fr}.sim-bank-search{grid-column:auto}.sim-bank-list{max-height:330px}.sim-bank-actions,.sim-bank-actions-left,.sim-bank-actions-right{align-items:stretch}.sim-bank-actions-left,.sim-bank-actions-right{width:100%}.sim-bank-actions .admin-btn,.sim-bank-actions .sim-mini-btn{flex:1;justify-content:center}}

    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>

    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>

        <main class="admin-dashboard-main">
            <section class="admin-page-header" aria-label="Cabeçalho de simulados">
                <div class="admin-page-heading">
                    <h1>Simulados</h1>
                    <p>Crie provas com tempo limite, questões, alternativas e resposta correta. Quando o aluno finalizar, a correção e o resultado aparecem aqui.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-primary" href="admin-simulado.php?modal=criar"><i data-lucide="plus" aria-hidden="true"></i>Criar simulado</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?>
                <div class="admin-alert admin-alert-error"><i data-lucide="alert-circle" aria-hidden="true"></i><?php echo admin_esc($erro); ?></div>
            <?php endif; ?>
            <?php if ($sucesso !== ''): ?>
                <div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2" aria-hidden="true"></i><?php echo admin_esc($sucesso); ?></div>
            <?php endif; ?>

            <section class="admin-stats-grid" aria-label="Resumo dos simulados">
                <article class="admin-stat-card"><div><span>Simulados</span><strong><?php echo (int)$totais['simulados']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="clipboard-check" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Questões</span><strong><?php echo (int)$totais['questoes']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="list-checks" aria-hidden="true"></i></div></article>
                <article class="admin-stat-card"><div><span>Entregas</span><strong><?php echo (int)$totais['tentativas']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="send" aria-hidden="true"></i></div></article>
            </section>

            <?php if ($simuladoResultados): ?>
                <section class="admin-panel" aria-label="Resultados do simulado">
                    <div class="sim-results-title">
                        <div>
                            <h2>Resultados: <?php echo admin_esc((string)$simuladoResultados['titulo']); ?></h2>
                            <p class="admin-form-hint">Aqui aparece o tempo usado, acertos e porcentagem de cada aluno.</p>
                        </div>
                        <div class="admin-table-actions">
                            <a class="admin-btn admin-btn-light" href="simulado-pdf.php?simulado_id=<?php echo (int)$simuladoResultados['id']; ?>" target="_blank" rel="noopener"><i data-lucide="download" aria-hidden="true"></i>Baixar PDF</a>
                            <a class="admin-btn admin-btn-light" href="admin-simulado.php"><i data-lucide="arrow-left" aria-hidden="true"></i>Voltar</a>
                        </div>
                    </div>
                    <div style="height:18px"></div>
                    <?php if (!$resultados): ?>
                        <div class="admin-empty"><div><i data-lucide="inbox" aria-hidden="true"></i><h3>Nenhuma entrega ainda</h3><p>Quando o aluno finalizar o simulado, o resultado aparece aqui.</p></div></div>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Aluno</th><th>Tempo usado</th><th>Acertos</th><th>Porcentagem</th><th>Finalizado em</th></tr></thead>
                                <tbody>
                                    <?php foreach ($resultados as $resultado): ?>
                                        <?php $percentual = (float)$resultado['percentual']; ?>
                                        <tr>
                                            <td><strong><?php echo admin_esc((string)($resultado['usuario_nome'] ?: 'Aluno não identificado')); ?></strong><br><span><?php echo admin_esc((string)($resultado['usuario_email'] ?: '-')); ?></span></td>
                                            <td><?php echo admin_esc(admin_sim_tempo((int)$resultado['tempo_segundos'])); ?></td>
                                            <td><?php echo (int)$resultado['total_acertos']; ?> de <?php echo (int)$resultado['total_questoes']; ?></td>
                                            <td><span class="sim-score <?php echo $percentual >= 70 ? 'good' : ($percentual >= 50 ? 'mid' : 'bad'); ?>"><?php echo admin_esc(admin_sim_percent($percentual)); ?></span></td>
                                            <td><?php echo admin_esc(admin_sim_format_date((string)$resultado['finalizado_em'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="admin-panel" aria-label="Lista de simulados">
                <div class="admin-panel-head">
                    <div><h2>Simulados cadastrados</h2><p>Gerencie provas, tempo, curso vinculado e resultados.</p></div>
                </div>

                <?php if (!$simulados): ?>
                    <div class="admin-empty"><div><i data-lucide="clipboard-check" aria-hidden="true"></i><h3>Nenhum simulado cadastrado</h3><p>Clique em criar simulado para montar a primeira prova.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Simulado</th><th>Curso</th><th>Tempo</th><th>Questões</th><th>Entregas</th><th>Média</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($simulados as $simulado): ?>
                                    <?php $simuladoId = (int)$simulado['id']; ?>
                                    <tr>
                                        <td><strong><?php echo admin_esc((string)$simulado['titulo']); ?></strong><br><span><?php echo admin_esc(admin_cursos_texto_curto((string)($simulado['descricao'] ?? ''), 80)); ?></span></td>
                                        <td><span class="sim-course-badge"><?php echo $simulado['curso_id'] ? admin_esc((string)$simulado['curso_nome']) : 'Todos os cursos'; ?></span></td>
                                        <td><?php echo (int)$simulado['tempo_minutos'] > 0 ? (int)$simulado['tempo_minutos'] . ' min' : 'Sem limite'; ?></td>
                                        <td><?php echo (int)$simulado['total_questoes']; ?></td>
                                        <td><?php echo (int)$simulado['total_tentativas']; ?></td>
                                        <td><?php echo $simulado['media_percentual'] !== null ? admin_esc(admin_sim_percent($simulado['media_percentual'])) : '-'; ?></td>
                                        <td><span class="sim-status-pill <?php echo admin_esc((string)$simulado['status']); ?>"><?php echo admin_esc((string)$simulado['status']); ?></span></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a class="admin-btn admin-btn-light" href="admin-simulado.php?resultados=<?php echo $simuladoId; ?>"><i data-lucide="bar-chart-3" aria-hidden="true"></i>Resultados</a>
                                                <a class="admin-btn admin-btn-light" href="simulado-pdf.php?simulado_id=<?php echo $simuladoId; ?>" target="_blank" rel="noopener"><i data-lucide="download" aria-hidden="true"></i>PDF</a>
                                                <a class="admin-btn admin-btn-light" href="admin-simulado.php?editar=<?php echo $simuladoId; ?>"><i data-lucide="pencil" aria-hidden="true"></i>Editar</a>
                                                <form method="post" onsubmit="return confirm('Excluir este simulado e todos os resultados enviados?');">
                                                    <input type="hidden" name="acao" value="excluir_simulado">
                                                    <input type="hidden" name="simulado_id" value="<?php echo $simuladoId; ?>">
                                                    <button class="admin-btn admin-btn-danger" type="submit"><i data-lucide="trash-2" aria-hidden="true"></i>Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <?php if ($formAberto): ?>
        <div class="admin-modal-backdrop sim-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modalSimuladoTitulo">
            <div class="admin-modal sim-modal" style="width:min(1220px,100%);">
                <div class="admin-modal-header">
                    <div><h2 id="modalSimuladoTitulo"><?php echo admin_esc($formTitulo); ?></h2><p><?php echo admin_esc($formDescricao); ?></p></div>
                    <a class="admin-modal-close" href="admin-simulado.php" aria-label="Fechar"><i data-lucide="x" aria-hidden="true"></i></a>
                </div>
                <form class="admin-modal-body" method="post" id="formSimulado">
                    <input type="hidden" name="acao" value="<?php echo admin_esc($formAcao); ?>">
                    <input type="hidden" name="simulado_id" value="<?php echo (int)($dadosForm['id'] ?? 0); ?>">

                    <div class="admin-form-grid">
                        <div class="admin-form-field">
                            <label for="titulo">Título do simulado</label>
                            <input id="titulo" name="titulo" type="text" value="<?php echo admin_esc((string)($dadosForm['titulo'] ?? '')); ?>" required>
                        </div>
                        <div class="admin-form-field">
                            <label for="curso_id">Disponibilidade</label>
                            <select id="curso_id" name="curso_id">
                                <option value="0">Todos os cursos</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?php echo (int)$curso['id']; ?>" <?php echo (int)($dadosForm['curso_id'] ?? 0) === (int)$curso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$curso['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="admin-form-hint">Use “Todos” para liberar geral ou escolha um curso específico.</span>
                        </div>
                        <div class="admin-form-field">
                            <label for="tempo_minutos">Tempo da prova em minutos</label>
                            <input id="tempo_minutos" name="tempo_minutos" type="number" min="0" step="1" value="<?php echo (int)($dadosForm['tempo_minutos'] ?? 0); ?>">
                            <span class="admin-form-hint">Coloque 0 para deixar sem limite de tempo.</span>
                        </div>
                        <div class="admin-form-field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="ativo" <?php echo ($dadosForm['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($dadosForm['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label for="ordem">Ordem</label>
                            <input id="ordem" name="ordem" type="number" step="1" value="<?php echo (int)($dadosForm['ordem'] ?? 0); ?>">
                        </div>
                        <div class="admin-form-field full">
                            <label for="descricao">Descrição</label>
                            <textarea id="descricao" name="descricao" placeholder="Resumo do simulado"><?php echo admin_esc((string)($dadosForm['descricao'] ?? '')); ?></textarea>
                        </div>
                        <div class="admin-form-field full">
                            <label for="instrucoes">Instruções para o aluno</label>
                            <textarea id="instrucoes" name="instrucoes" placeholder="Exemplo: leia com atenção e marque apenas uma alternativa por questão."><?php echo admin_esc((string)($dadosForm['instrucoes'] ?? '')); ?></textarea>
                        </div>
                    </div>

                    <div class="sim-form-divider"></div>
                    <div class="admin-panel-head" style="padding:0;margin:0;">
                        <div><h2>Questões e respostas</h2><p>Escolha questões já cadastradas no banco ou crie questões próprias para este simulado.</p></div>
                    </div>

                    <section class="sim-bank-panel" id="simBankPanel" aria-label="Selecionar questões do banco">
                        <div class="sim-bank-panel-head">
                            <div>
                                <h3>Usar questões do banco de questões</h3>
                                <p>Filtre por matéria, tópico, banca, ano ou dificuldade e adicione várias questões de uma vez.</p>
                            </div>
                            <span class="sim-bank-count" id="simBancoTotalVisivel"><?php echo count($questoesBanco); ?></span>
                        </div>
                        <div class="sim-bank-body">
                            <div class="sim-bank-filters">
                                <div class="admin-form-field sim-bank-search">
                                    <label for="simBancoBusca">Buscar no enunciado</label>
                                    <input type="search" id="simBancoBusca" placeholder="Digite uma palavra da questão">
                                </div>
                                <div class="admin-form-field">
                                    <label for="simBancoMateria">Matéria</label>
                                    <select id="simBancoMateria">
                                        <option value="0">Todas as matérias</option>
                                        <?php foreach ($materiasBanco as $materiaBanco): ?>
                                            <option value="<?php echo (int)$materiaBanco['id']; ?>"><?php echo admin_esc((string)$materiaBanco['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-form-field">
                                    <label for="simBancoTopico">Tópico</label>
                                    <select id="simBancoTopico">
                                        <option value="0">Todos os tópicos</option>
                                        <?php foreach ($topicosBanco as $topicoBanco): ?>
                                            <option value="<?php echo (int)$topicoBanco['id']; ?>" data-materia-id="<?php echo (int)$topicoBanco['materia_id']; ?>"><?php echo admin_esc((string)$topicoBanco['titulo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-form-field">
                                    <label for="simBancoBanca">Banca</label>
                                    <select id="simBancoBanca">
                                        <option value="0">Todas as bancas</option>
                                        <?php foreach ($bancasBanco as $bancaBanco): ?>
                                            <option value="<?php echo (int)$bancaBanco['id']; ?>"><?php echo admin_esc(trim((string)($bancaBanco['sigla'] ?? '')) !== '' ? (string)$bancaBanco['sigla'] . ' - ' . (string)$bancaBanco['nome'] : (string)$bancaBanco['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-form-field">
                                    <label for="simBancoAno">Ano</label>
                                    <select id="simBancoAno">
                                        <option value="0">Todos os anos</option>
                                        <?php foreach ($anosBanco as $anoBanco): ?>
                                            <option value="<?php echo (int)$anoBanco; ?>"><?php echo (int)$anoBanco; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-form-field">
                                    <label for="simBancoDificuldade">Dificuldade</label>
                                    <select id="simBancoDificuldade">
                                        <option value="">Todas</option>
                                        <option value="facil">Fácil</option>
                                        <option value="medio">Médio</option>
                                        <option value="dificil">Difícil</option>
                                    </select>
                                </div>
                            </div>

                            <div class="sim-bank-list" id="simBancoLista"></div>

                            <div class="sim-bank-actions">
                                <div class="sim-bank-actions-left">
                                    <button type="button" class="sim-mini-btn" id="simSelecionarVisiveis"><i data-lucide="check-square" aria-hidden="true"></i>Selecionar visíveis</button>
                                    <button type="button" class="sim-mini-btn" id="simLimparSelecao"><i data-lucide="square" aria-hidden="true"></i>Limpar seleção</button>
                                </div>
                                <div class="sim-bank-actions-right">
                                    <span class="sim-bank-selected-text" id="simBancoSelecionadasTexto">0 questões selecionadas</span>
                                    <button type="button" class="admin-btn admin-btn-primary" id="simAdicionarSelecionadas"><i data-lucide="list-plus" aria-hidden="true"></i>Adicionar ao simulado</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <script type="application/json" id="simBancoQuestoesData"><?php echo json_encode($questoesBanco, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

                    <div class="sim-builder" id="questoesBuilder">
                        <?php foreach ($questoesEditando as $qIndex => $questao): ?>
                            <?php $qKey = 'q' . ($qIndex + 1); ?>
                            <?php $bancoQuestaoIdAtual = (int)($questao['banco_questao_id'] ?? 0); ?>
                            <div class="sim-question" data-question data-key="<?php echo admin_esc($qKey); ?>" data-bank-id="<?php echo $bancoQuestaoIdAtual; ?>">
                                <input type="hidden" name="questoes_chave[]" value="<?php echo admin_esc($qKey); ?>">
                                <input type="hidden" name="questao_banco_id[<?php echo admin_esc($qKey); ?>]" value="<?php echo $bancoQuestaoIdAtual; ?>">
                                <div class="sim-question-head">
                                    <strong>Questão <span data-question-number><?php echo $qIndex + 1; ?></span><?php if ($bancoQuestaoIdAtual > 0): ?><span class="sim-source-badge"><i data-lucide="database" aria-hidden="true"></i>Banco de questões</span><?php endif; ?></strong>
                                    <button type="button" class="sim-remove-btn" data-remove-question><i data-lucide="trash-2" aria-hidden="true"></i><span>Remover questão</span></button>
                                </div>
                                <div class="admin-form-grid">
                                    <div class="admin-form-field full">
                                        <label>Texto da questão</label>
                                        <textarea name="questao_enunciado[<?php echo admin_esc($qKey); ?>]" required><?php echo admin_esc((string)($questao['enunciado'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="admin-form-field full">
                                        <label>Comentário da revisão / explicação</label>
                                        <textarea name="questao_explicacao[<?php echo admin_esc($qKey); ?>]" placeholder="Opcional: explique por que a resposta está correta."><?php echo admin_esc((string)($questao['explicacao'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="admin-form-field">
                                        <label>Ordem da questão</label>
                                        <input type="number" name="questao_ordem[<?php echo admin_esc($qKey); ?>]" value="<?php echo (int)($questao['ordem'] ?? ($qIndex + 1)); ?>">
                                    </div>
                                </div>
                                <div class="sim-options" data-options>
                                    <?php $opcoes = $questao['opcoes'] ?? []; ?>
                                    <?php foreach ($opcoes as $oIndex => $opcao): ?>
                                        <div class="sim-option" data-option>
                                            <span class="sim-option-letter" data-option-letter><?php echo admin_esc(admin_sim_letra($oIndex)); ?></span>
                                            <input type="text" name="opcao_texto[<?php echo admin_esc($qKey); ?>][]" value="<?php echo admin_esc((string)($opcao['texto'] ?? '')); ?>" placeholder="Texto da opção" required>
                                            <div class="sim-option-tools">
                                                <label class="sim-correct"><input type="radio" name="correta[<?php echo admin_esc($qKey); ?>]" value="<?php echo $oIndex; ?>" <?php echo !empty($opcao['correta']) ? 'checked' : ''; ?>>Resposta correta</label>
                                                <button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="sim-add-line"><button type="button" class="sim-mini-btn" data-add-option><i data-lucide="plus" aria-hidden="true"></i>Adicionar opção</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="sim-bottom-add">
                        <button type="button" class="admin-btn admin-btn-primary" id="addQuestaoBottom"><i data-lucide="plus" aria-hidden="true"></i>Criar +1 questão</button>
                    </div>

                    <div class="admin-form-actions">
                        <a class="admin-btn admin-btn-light" href="admin-simulado.php">Cancelar</a>
                        <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save" aria-hidden="true"></i>Salvar simulado</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php admin_cursos_scripts(); ?>
    <script>
        (function(){
            const builder = document.getElementById('questoesBuilder');
            const addQuestao = document.getElementById('addQuestao');
            const addQuestaoBottom = document.getElementById('addQuestaoBottom');
            if (!builder) return;

            const bancoDataEl = document.getElementById('simBancoQuestoesData');
            let bancoQuestoes = [];
            try {
                bancoQuestoes = bancoDataEl ? JSON.parse(bancoDataEl.textContent || '[]') : [];
            } catch (error) {
                bancoQuestoes = [];
            }

            const bancoLista = document.getElementById('simBancoLista');
            const bancoBusca = document.getElementById('simBancoBusca');
            const bancoMateria = document.getElementById('simBancoMateria');
            const bancoTopico = document.getElementById('simBancoTopico');
            const bancoBanca = document.getElementById('simBancoBanca');
            const bancoAno = document.getElementById('simBancoAno');
            const bancoDificuldade = document.getElementById('simBancoDificuldade');
            const bancoTotalVisivel = document.getElementById('simBancoTotalVisivel');
            const bancoSelecionadasTexto = document.getElementById('simBancoSelecionadasTexto');
            const selecionarVisiveisBtn = document.getElementById('simSelecionarVisiveis');
            const limparSelecaoBtn = document.getElementById('simLimparSelecao');
            const adicionarSelecionadasBtn = document.getElementById('simAdicionarSelecionadas');
            const selecionadas = new Set();

            function letra(index){
                let s = '';
                index = Math.max(0, index);
                do { s = String.fromCharCode(65 + (index % 26)) + s; index = Math.floor(index / 26) - 1; } while(index >= 0);
                return s;
            }

            function uid(){
                return 'q' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
            }

            function esc(value){
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function normalizar(value){
                return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            function idsBancoAdicionados(){
                return new Set(
                    [...builder.querySelectorAll('[data-question][data-bank-id]')]
                        .map(el => Number(el.getAttribute('data-bank-id') || 0))
                        .filter(id => id > 0)
                );
            }

            function refresh(){
                const questions = [...builder.querySelectorAll('[data-question]')];
                questions.forEach((q, qi) => {
                    const num = q.querySelector('[data-question-number]');
                    if (num) num.textContent = String(qi + 1);
                    q.querySelectorAll('[data-option]').forEach((op, oi) => {
                        const l = op.querySelector('[data-option-letter]');
                        const radio = op.querySelector('input[type="radio"]');
                        if (l) l.textContent = letra(oi);
                        if (radio) radio.value = String(oi);
                    });
                    const ordem = q.querySelector('input[name^="questao_ordem"]');
                    if (ordem && (!ordem.value || ordem.value === '0')) ordem.value = String(qi + 1);
                });
                renderBanco();
                if (window.lucide) window.lucide.createIcons();
            }

            function optionHtml(key, checked, texto = ''){
                const question = builder.querySelector(`[data-question][data-key="${key}"]`);
                const optionsCount = question ? question.querySelectorAll('[data-option]').length : 0;
                return `<div class="sim-option" data-option>
                    <span class="sim-option-letter" data-option-letter>${letra(optionsCount)}</span>
                    <input type="text" name="opcao_texto[${key}][]" value="${esc(texto)}" placeholder="Texto da opção" required>
                    <div class="sim-option-tools">
                        <label class="sim-correct"><input type="radio" name="correta[${key}]" value="${optionsCount}" ${checked ? 'checked' : ''}>Resposta correta</label>
                        <button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button>
                    </div>
                </div>`;
            }

            function questionHtml(){
                const key = uid();
                const number = builder.querySelectorAll('[data-question]').length + 1;
                return `<div class="sim-question" data-question data-key="${key}" data-bank-id="0">
                    <input type="hidden" name="questoes_chave[]" value="${key}">
                    <input type="hidden" name="questao_banco_id[${key}]" value="0">
                    <div class="sim-question-head"><strong>Questão <span data-question-number>${number}</span></strong><button type="button" class="sim-remove-btn" data-remove-question><i data-lucide="trash-2" aria-hidden="true"></i><span>Remover questão</span></button></div>
                    <div class="admin-form-grid">
                        <div class="admin-form-field full"><label>Texto da questão</label><textarea name="questao_enunciado[${key}]" required></textarea></div>
                        <div class="admin-form-field full"><label>Comentário da revisão / explicação</label><textarea name="questao_explicacao[${key}]" placeholder="Opcional: explique por que a resposta está correta."></textarea></div>
                        <div class="admin-form-field"><label>Ordem da questão</label><input type="number" name="questao_ordem[${key}]" value="${number}"></div>
                    </div>
                    <div class="sim-options" data-options>
                        <div class="sim-option" data-option><span class="sim-option-letter" data-option-letter>A</span><input type="text" name="opcao_texto[${key}][]" value="" placeholder="Texto da opção" required><div class="sim-option-tools"><label class="sim-correct"><input type="radio" name="correta[${key}]" value="0" checked>Resposta correta</label><button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button></div></div>
                        <div class="sim-option" data-option><span class="sim-option-letter" data-option-letter>B</span><input type="text" name="opcao_texto[${key}][]" value="" placeholder="Texto da opção" required><div class="sim-option-tools"><label class="sim-correct"><input type="radio" name="correta[${key}]" value="1">Resposta correta</label><button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button></div></div>
                        <div class="sim-option" data-option><span class="sim-option-letter" data-option-letter>C</span><input type="text" name="opcao_texto[${key}][]" value="" placeholder="Texto da opção" required><div class="sim-option-tools"><label class="sim-correct"><input type="radio" name="correta[${key}]" value="2">Resposta correta</label><button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button></div></div>
                        <div class="sim-option" data-option><span class="sim-option-letter" data-option-letter>D</span><input type="text" name="opcao_texto[${key}][]" value="" placeholder="Texto da opção" required><div class="sim-option-tools"><label class="sim-correct"><input type="radio" name="correta[${key}]" value="3">Resposta correta</label><button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button></div></div>
                    </div>
                    <div class="sim-add-line"><button type="button" class="sim-mini-btn" data-add-option><i data-lucide="plus" aria-hidden="true"></i>Adicionar opção</button></div>
                </div>`;
            }

            function questionHtmlBanco(questao){
                const key = uid();
                const number = builder.querySelectorAll('[data-question]').length + 1;
                const opcoes = Array.isArray(questao.opcoes) ? questao.opcoes : [];
                let corretaIndex = opcoes.findIndex(opcao => Number(opcao.correta) === 1);
                if (corretaIndex < 0) corretaIndex = 0;
                const opcoesHtml = opcoes.map((opcao, index) => `<div class="sim-option" data-option>
                    <span class="sim-option-letter" data-option-letter>${letra(index)}</span>
                    <input type="text" name="opcao_texto[${key}][]" value="${esc(opcao.texto)}" placeholder="Texto da opção" required>
                    <div class="sim-option-tools">
                        <label class="sim-correct"><input type="radio" name="correta[${key}]" value="${index}" ${index === corretaIndex ? 'checked' : ''}>Resposta correta</label>
                        <button type="button" class="sim-option-remove" data-remove-option aria-label="Remover opção"><i data-lucide="x" aria-hidden="true"></i></button>
                    </div>
                </div>`).join('');

                return `<div class="sim-question" data-question data-key="${key}" data-bank-id="${Number(questao.id)}">
                    <input type="hidden" name="questoes_chave[]" value="${key}">
                    <input type="hidden" name="questao_banco_id[${key}]" value="${Number(questao.id)}">
                    <div class="sim-question-head">
                        <strong>Questão <span data-question-number>${number}</span><span class="sim-source-badge"><i data-lucide="database" aria-hidden="true"></i>Banco de questões</span></strong>
                        <button type="button" class="sim-remove-btn" data-remove-question><i data-lucide="trash-2" aria-hidden="true"></i><span>Remover questão</span></button>
                    </div>
                    <div class="admin-form-grid">
                        <div class="admin-form-field full"><label>Texto da questão</label><textarea name="questao_enunciado[${key}]" required>${esc(questao.enunciado)}</textarea></div>
                        <div class="admin-form-field full"><label>Comentário da revisão / explicação</label><textarea name="questao_explicacao[${key}]" placeholder="Opcional: explique por que a resposta está correta.">${esc(questao.explicacao || '')}</textarea></div>
                        <div class="admin-form-field"><label>Ordem da questão</label><input type="number" name="questao_ordem[${key}]" value="${number}"></div>
                    </div>
                    <div class="sim-options" data-options>${opcoesHtml}</div>
                    <div class="sim-add-line"><button type="button" class="sim-mini-btn" data-add-option><i data-lucide="plus" aria-hidden="true"></i>Adicionar opção</button></div>
                </div>`;
            }

            function adicionarQuestao(){
                builder.insertAdjacentHTML('beforeend', questionHtml());
                refresh();
                const novaQuestao = builder.querySelector('[data-question]:last-child');
                if (novaQuestao) {
                    novaQuestao.scrollIntoView({behavior:'smooth', block:'start'});
                    const campo = novaQuestao.querySelector('textarea');
                    if (campo) setTimeout(function(){ campo.focus(); }, 260);
                }
            }

            function removerQuestaoInicialVazia(){
                const perguntas = [...builder.querySelectorAll('[data-question]')];
                if (perguntas.length !== 1) return;
                const primeira = perguntas[0];
                const texto = primeira.querySelector('textarea[name^="questao_enunciado"]')?.value.trim() || '';
                const opcoesPreenchidas = [...primeira.querySelectorAll('input[name^="opcao_texto"]')].some(input => input.value.trim() !== '');
                if (texto === '' && !opcoesPreenchidas) primeira.remove();
            }

            function questoesFiltradas(){
                const termo = normalizar(bancoBusca?.value || '');
                const materiaId = Number(bancoMateria?.value || 0);
                const topicoId = Number(bancoTopico?.value || 0);
                const bancaId = Number(bancoBanca?.value || 0);
                const ano = Number(bancoAno?.value || 0);
                const dificuldade = bancoDificuldade?.value || '';

                return bancoQuestoes.filter(questao => {
                    if (materiaId > 0 && Number(questao.materia_id) !== materiaId) return false;
                    if (topicoId > 0 && Number(questao.materia_topico_id) !== topicoId) return false;
                    if (bancaId > 0 && Number(questao.banca_id) !== bancaId) return false;
                    if (ano > 0 && Number(questao.ano) !== ano) return false;
                    if (dificuldade && String(questao.dificuldade) !== dificuldade) return false;
                    if (termo) {
                        const alvo = normalizar([questao.enunciado, questao.explicacao, questao.materia_nome, questao.topico_nome, questao.banca_nome, questao.banca_sigla].join(' '));
                        if (!alvo.includes(termo)) return false;
                    }
                    return true;
                });
            }

            function atualizarTopicos(){
                if (!bancoTopico || !bancoMateria) return;
                const materiaId = Number(bancoMateria.value || 0);
                [...bancoTopico.options].forEach((option, index) => {
                    if (index === 0) return;
                    const pertence = materiaId === 0 || Number(option.dataset.materiaId || 0) === materiaId;
                    option.hidden = !pertence;
                    option.disabled = !pertence;
                });
                const selecionado = bancoTopico.selectedOptions[0];
                if (selecionado && selecionado.disabled) bancoTopico.value = '0';
            }

            function textoDificuldade(valor){
                if (valor === 'facil') return 'Fácil';
                if (valor === 'dificil') return 'Difícil';
                return 'Médio';
            }

            function renderBanco(){
                if (!bancoLista) return;
                const filtradas = questoesFiltradas();
                const adicionadas = idsBancoAdicionados();

                [...selecionadas].forEach(id => {
                    if (adicionadas.has(Number(id))) selecionadas.delete(Number(id));
                });

                if (bancoTotalVisivel) bancoTotalVisivel.textContent = String(filtradas.length);
                if (bancoSelecionadasTexto) bancoSelecionadasTexto.textContent = `${selecionadas.size} ${selecionadas.size === 1 ? 'questão selecionada' : 'questões selecionadas'}`;
                if (adicionarSelecionadasBtn) adicionarSelecionadasBtn.disabled = selecionadas.size === 0;

                if (!filtradas.length) {
                    bancoLista.innerHTML = '<div class="sim-bank-empty">Nenhuma questão encontrada com esses filtros.</div>';
                    return;
                }

                bancoLista.innerHTML = filtradas.map(questao => {
                    const id = Number(questao.id);
                    const adicionada = adicionadas.has(id);
                    const selecionada = selecionadas.has(id);
                    const banca = String(questao.banca_sigla || '').trim() || String(questao.banca_nome || '');
                    return `<label class="sim-bank-item ${selecionada ? 'is-selected' : ''} ${adicionada ? 'is-added' : ''}">
                        <input type="checkbox" data-bank-check value="${id}" ${selecionada ? 'checked' : ''} ${adicionada ? 'disabled' : ''}>
                        <span>
                            <span class="sim-bank-question">${esc(questao.enunciado)}</span>
                            <span class="sim-bank-meta">
                                <span class="sim-bank-tag yellow">${esc(questao.materia_nome || 'Sem matéria')}</span>
                                ${questao.topico_nome ? `<span class="sim-bank-tag">${esc(questao.topico_nome)}</span>` : ''}
                                <span class="sim-bank-tag blue">${esc(banca || 'Sem banca')}</span>
                                ${questao.ano ? `<span class="sim-bank-tag">${Number(questao.ano)}</span>` : ''}
                                <span class="sim-bank-tag green">${esc(textoDificuldade(questao.dificuldade))}</span>
                                <span class="sim-bank-tag">${Array.isArray(questao.opcoes) ? questao.opcoes.length : 0} alternativas</span>
                                ${adicionada ? '<span class="sim-bank-tag yellow">Já adicionada</span>' : ''}
                            </span>
                        </span>
                    </label>`;
                }).join('');

                if (window.lucide) window.lucide.createIcons();
            }

            function adicionarSelecionadas(){
                const adicionadas = idsBancoAdicionados();
                const paraAdicionar = bancoQuestoes.filter(questao => selecionadas.has(Number(questao.id)) && !adicionadas.has(Number(questao.id)));
                if (!paraAdicionar.length) return;

                const invalidas = paraAdicionar.filter(questao => !Array.isArray(questao.opcoes) || questao.opcoes.length < 2);
                if (invalidas.length) {
                    alert('Uma ou mais questões selecionadas não possuem alternativas suficientes no banco de questões.');
                    return;
                }

                removerQuestaoInicialVazia();
                const primeiraNovaId = Number(paraAdicionar[0].id);
                paraAdicionar.forEach(questao => builder.insertAdjacentHTML('beforeend', questionHtmlBanco(questao)));
                selecionadas.clear();
                refresh();

                const primeiraNova = builder.querySelector(`[data-question][data-bank-id="${primeiraNovaId}"]`);
                if (primeiraNova) primeiraNova.scrollIntoView({behavior:'smooth', block:'start'});
            }

            if (addQuestao) addQuestao.addEventListener('click', adicionarQuestao);
            if (addQuestaoBottom) addQuestaoBottom.addEventListener('click', adicionarQuestao);

            builder.addEventListener('click', function(event){
                const addOp = event.target.closest('[data-add-option]');
                if (addOp) {
                    const question = addOp.closest('[data-question]');
                    const key = question.getAttribute('data-key');
                    question.querySelector('[data-options]').insertAdjacentHTML('beforeend', optionHtml(key, false));
                    refresh();
                    return;
                }

                const removeOp = event.target.closest('[data-remove-option]');
                if (removeOp) {
                    const options = removeOp.closest('[data-options]');
                    if (options.querySelectorAll('[data-option]').length <= 2) {
                        alert('Cada questão precisa ter pelo menos duas opções.');
                        return;
                    }
                    const option = removeOp.closest('[data-option]');
                    const wasChecked = option.querySelector('input[type="radio"]')?.checked;
                    option.remove();
                    if (wasChecked) {
                        const firstRadio = options.querySelector('input[type="radio"]');
                        if (firstRadio) firstRadio.checked = true;
                    }
                    refresh();
                    return;
                }

                const removeQ = event.target.closest('[data-remove-question]');
                if (removeQ) {
                    if (builder.querySelectorAll('[data-question]').length <= 1) {
                        alert('O simulado precisa ter pelo menos uma questão.');
                        return;
                    }
                    removeQ.closest('[data-question]').remove();
                    refresh();
                }
            });

            if (bancoLista) {
                bancoLista.addEventListener('change', function(event){
                    const checkbox = event.target.closest('[data-bank-check]');
                    if (!checkbox) return;
                    const id = Number(checkbox.value);
                    if (checkbox.checked) selecionadas.add(id); else selecionadas.delete(id);
                    renderBanco();
                });
            }

            [bancoBusca, bancoTopico, bancoBanca, bancoAno, bancoDificuldade].forEach(campo => {
                if (!campo) return;
                campo.addEventListener(campo === bancoBusca ? 'input' : 'change', renderBanco);
            });

            if (bancoMateria) {
                bancoMateria.addEventListener('change', function(){
                    atualizarTopicos();
                    renderBanco();
                });
            }

            if (selecionarVisiveisBtn) {
                selecionarVisiveisBtn.addEventListener('click', function(){
                    const adicionadas = idsBancoAdicionados();
                    questoesFiltradas().forEach(questao => {
                        const id = Number(questao.id);
                        if (!adicionadas.has(id)) selecionadas.add(id);
                    });
                    renderBanco();
                });
            }

            if (limparSelecaoBtn) {
                limparSelecaoBtn.addEventListener('click', function(){
                    selecionadas.clear();
                    renderBanco();
                });
            }

            if (adicionarSelecionadasBtn) adicionarSelecionadasBtn.addEventListener('click', adicionarSelecionadas);

            atualizarTopicos();
            refresh();
        })();
    </script>
</body>
</html>
