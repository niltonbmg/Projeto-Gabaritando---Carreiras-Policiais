<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$admin = require_admin($pdo);
$adminId = (int)($admin['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!table_exists($pdo, 'usuarios')) {
    json_out(false, ['message' => 'Tabela usuarios não encontrada.'], 500);
}

$cols = table_cols($pdo, 'usuarios');

if ($method === 'GET') {
    $orderCol = first_col($cols, ['created_at', 'data_criacao', 'id']) ?: 'id';
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY `$orderCol` DESC LIMIT 300");
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $item = normalize_user($pdo, $row);
        foreach (['telefone', 'cpf', 'cnpj', 'created_at', 'data_criacao', 'ultimo_acesso'] as $extra) {
            if (isset($row[$extra])) $item[$extra] = $row[$extra];
        }
        $items[] = $item;
    }
    json_out(true, ['items' => $items]);
}

if ($method !== 'POST' && $method !== 'PATCH' && $method !== 'DELETE') {
    json_out(false, ['message' => 'Método inválido.'], 405);
}

$data = body_json();
$userId = (int)($data['user_id'] ?? $data['id'] ?? 0);
$action = trim((string)($data['action'] ?? ''));

if ($userId <= 0 || $action === '') {
    json_out(false, ['message' => 'Informe user_id e action.'], 422);
}

if ($userId === $adminId && in_array($action, ['bloquear', 'excluir', 'tipo'], true)) {
    json_out(false, ['message' => 'Você não pode bloquear, excluir ou rebaixar o próprio usuário logado.'], 422);
}

$exists = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
$exists->execute([$userId]);
if (!$exists->fetchColumn()) {
    json_out(false, ['message' => 'Usuário não encontrado.'], 404);
}

if ($action === 'bloquear' || $action === 'desbloquear') {
    $block = $action === 'bloquear';
    if (isset($cols['bloqueado'])) {
        $stmt = $pdo->prepare('UPDATE usuarios SET bloqueado = ? WHERE id = ?');
        $stmt->execute([$block ? 1 : 0, $userId]);
    } elseif (isset($cols['ativo'])) {
        $stmt = $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
        $stmt->execute([$block ? 0 : 1, $userId]);
    } elseif (isset($cols['status'])) {
        $stmt = $pdo->prepare('UPDATE usuarios SET status = ? WHERE id = ?');
        $stmt->execute([$block ? 'bloqueado' : 'ativo', $userId]);
    } else {
        json_out(false, ['message' => 'Crie uma coluna bloqueado ou ativo na tabela usuarios.'], 500);
    }
    json_out(true, ['message' => $block ? 'Usuário bloqueado.' : 'Usuário desbloqueado.']);
}

if ($action === 'senha') {
    $senha = trim((string)($data['senha'] ?? ''));
    if (strlen($senha) < 6) {
        json_out(false, ['message' => 'A senha precisa ter pelo menos 6 caracteres.'], 422);
    }
    $senhaCol = first_col($cols, ['senha', 'password', 'senha_hash', 'password_hash']);
    if (!$senhaCol) json_out(false, ['message' => 'Coluna de senha não encontrada.'], 500);
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE usuarios SET `$senhaCol` = ? WHERE id = ?");
    $stmt->execute([$hash, $userId]);
    json_out(true, ['message' => 'Senha alterada.']);
}

if ($action === 'tipo') {
    $tipo = trim((string)($data['tipo'] ?? 'cliente'));
    if (!in_array($tipo, ['admin', 'cliente'], true)) {
        json_out(false, ['message' => 'Tipo inválido. Use admin ou cliente.'], 422);
    }
    $typeCol = first_col($cols, ['tipo', 'perfil', 'role', 'nivel', 'acesso']);
    if ($typeCol) {
        $stmt = $pdo->prepare("UPDATE usuarios SET `$typeCol` = ? WHERE id = ?");
        $stmt->execute([$tipo, $userId]);
    } elseif (isset($cols['is_admin'])) {
        $stmt = $pdo->prepare('UPDATE usuarios SET is_admin = ? WHERE id = ?');
        $stmt->execute([$tipo === 'admin' ? 1 : 0, $userId]);
    } else {
        json_out(false, ['message' => 'Coluna tipo/perfil/role/is_admin não encontrada.'], 500);
    }
    json_out(true, ['message' => 'Tipo de usuário alterado.']);
}

if ($action === 'excluir') {
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
    $stmt->execute([$userId]);
    json_out(true, ['message' => 'Usuário excluído.']);
}

json_out(false, ['message' => 'Ação inválida.'], 422);
