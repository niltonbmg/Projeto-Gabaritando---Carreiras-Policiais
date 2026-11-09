<?php
// api/aula-marcacoes.php
// Guarda e devolve as marcações (marca-texto) do aluno logado em uma aula.
// GET  ?aula_id=N           -> {"marcacoes":[{"t":"trecho","n":0}, ...]}
// POST {aula_id, marcacoes} -> {"ok":true}
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/authenticador.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../includes/aula-descricao-lib.php';

function aula_marc_resposta(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_is_logged()) {
    aula_marc_resposta(401, ['erro' => 'Faça login novamente.']);
}
$usuarioId = auth_user_id();

try {
    $pdo = db();
    aula_marcacoes_bootstrap($pdo);

    // Marcações no PDF: retângulos por página, em frações da página (0..1), valem em qualquer zoom.
    if (($_GET['tipo'] ?? '') === 'pdf') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_marcacoes_pdf (
            usuario_id INT UNSIGNED NOT NULL, aula_id INT UNSIGNED NOT NULL, dados MEDIUMTEXT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, aula_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $pdo->prepare('SELECT dados FROM aula_marcacoes_pdf WHERE usuario_id = :u AND aula_id = :a');
            $stmt->execute([':u' => $usuarioId, ':a' => (int)($_GET['aula_id'] ?? 0)]);
            $dados = json_decode((string)($stmt->fetchColumn() ?: '[]'), true);
            aula_marc_resposta(200, ['marcacoes' => is_array($dados) ? $dados : []]);
        }
        $entrada = json_decode((string)file_get_contents('php://input'), true);
        $aulaId = (int)($entrada['aula_id'] ?? 0);
        if ($aulaId <= 0) {
            aula_marc_resposta(400, ['erro' => 'Aula inválida.']);
        }
        $f = static fn($v): float => round(max(0.0, min(1.0, (float)$v)), 4);
        $limpo = [];
        foreach (array_slice(is_array($entrada['marcacoes'] ?? null) ? $entrada['marcacoes'] : [], 0, 1000) as $m) {
            $limpo[] = ['p' => max(1, (int)($m['p'] ?? 1)), 'x' => $f($m['x'] ?? 0), 'y' => $f($m['y'] ?? 0), 'w' => $f($m['w'] ?? 0), 'h' => $f($m['h'] ?? 0)];
        }
        $pdo->prepare('INSERT INTO aula_marcacoes_pdf (usuario_id, aula_id, dados) VALUES (:u, :a, :d) ON DUPLICATE KEY UPDATE dados = VALUES(dados)')
            ->execute([':u' => $usuarioId, ':a' => $aulaId, ':d' => json_encode($limpo)]);
        aula_marc_resposta(200, ['ok' => true, 'total' => count($limpo)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $aulaId = (int)($_GET['aula_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT dados FROM aula_marcacoes WHERE usuario_id = :u AND aula_id = :a');
        $stmt->execute([':u' => $usuarioId, ':a' => $aulaId]);
        $dados = json_decode((string)($stmt->fetchColumn() ?: '[]'), true);
        aula_marc_resposta(200, ['marcacoes' => is_array($dados) ? $dados : []]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $entrada = json_decode((string)file_get_contents('php://input'), true);
        $aulaId = (int)($entrada['aula_id'] ?? 0);
        $lista = is_array($entrada['marcacoes'] ?? null) ? $entrada['marcacoes'] : [];
        if ($aulaId <= 0) {
            aula_marc_resposta(400, ['erro' => 'Aula inválida.']);
        }
        // Limpa: no máximo 300 marcações, trechos de até 600 caracteres.
        $limpo = [];
        foreach (array_slice($lista, 0, 300) as $m) {
            $t = mb_substr(trim((string)($m['t'] ?? '')), 0, 600);
            if ($t !== '') {
                $limpo[] = ['t' => $t, 'n' => max(0, (int)($m['n'] ?? 0))];
            }
        }
        $stmt = $pdo->prepare('INSERT INTO aula_marcacoes (usuario_id, aula_id, dados) VALUES (:u, :a, :d) ON DUPLICATE KEY UPDATE dados = VALUES(dados)');
        $stmt->execute([':u' => $usuarioId, ':a' => $aulaId, ':d' => json_encode($limpo, JSON_UNESCAPED_UNICODE)]);
        aula_marc_resposta(200, ['ok' => true, 'total' => count($limpo)]);
    }

    aula_marc_resposta(405, ['erro' => 'Método não permitido.']);
} catch (Throwable $e) {
    aula_marc_resposta(500, ['erro' => 'Não foi possível salvar agora. Tente de novo.']);
}
