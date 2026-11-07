<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/boas-vindas.php';
require_once __DIR__ . '/../includes/curso-acesso.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_out(false, ['message' => 'Método inválido. Envie POST com nome, email, telefone e senha.'], 405);
    }

    $data = body_json();
    $nome = trim((string)($data['nome'] ?? $data['name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $telefone = trim((string)($data['telefone'] ?? $data['phone'] ?? ''));
    $senha = (string)($data['senha'] ?? $data['password'] ?? '');

    if ($nome === '' || mb_strlen($nome) < 3) {
        json_out(false, ['message' => 'Informe seu nome completo.'], 422);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(false, ['message' => 'Informe um e-mail válido.'], 422);
    }
    if ($telefone === '') {
        json_out(false, ['message' => 'Informe seu telefone.'], 422);
    }
    if (strlen($senha) < 6) {
        json_out(false, ['message' => 'A senha precisa ter pelo menos 6 caracteres.'], 422);
    }

    gab_boas_vindas_bootstrap($pdo);

    if (!table_exists($pdo, 'usuarios')) {
        json_out(false, ['message' => 'Tabela usuarios não encontrada.'], 500);
    }

    $cols = table_cols($pdo, 'usuarios');
    $nomeCol = first_col($cols, ['nome', 'name', 'usuario']);
    $emailCol = first_col($cols, ['email', 'usuario_email']);
    $telefoneCol = first_col($cols, ['telefone', 'phone', 'celular', 'whatsapp']);
    $senhaCol = first_col($cols, ['senha_hash', 'password_hash', 'senha', 'password', 'senha_usuario']);
    $tipoCol = first_col($cols, ['tipo', 'perfil', 'role', 'nivel', 'acesso']);
    $statusCol = first_col($cols, ['status']);
    $ativoCol = first_col($cols, ['ativo', 'active']);
    $createdCol = first_col($cols, ['created_at', 'criado_em', 'data_criacao']);
    $updatedCol = first_col($cols, ['updated_at', 'atualizado_em']);
    $boasVindasCol = first_col($cols, ['boas_vindas_visualizada']);

    if (!$nomeCol || !$emailCol || !$senhaCol) {
        json_out(false, ['message' => 'A tabela usuarios precisa ter nome, email e senha.'], 500);
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(TRIM(`{$emailCol}`)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        json_out(false, ['message' => 'Este e-mail já está cadastrado. Use Entrar.'], 409);
    }

    $insert = [];
    $params = [];

    $insert[$nomeCol] = $nome;
    $insert[$emailCol] = $email;
    $insert[$senhaCol] = password_hash($senha, PASSWORD_DEFAULT);

    if ($telefoneCol) {
        $insert[$telefoneCol] = $telefone;
    }
    if ($tipoCol) {
        $insert[$tipoCol] = 'cliente';
    }
    if ($statusCol) {
        $insert[$statusCol] = 'ativo';
    }
    if ($ativoCol) {
        $insert[$ativoCol] = 1;
    }
    if ($boasVindasCol) {
        $insert[$boasVindasCol] = 0;
    }

    $nowColumns = [];
    if ($createdCol) {
        $nowColumns[$createdCol] = 'NOW()';
    }
    if ($updatedCol) {
        $nowColumns[$updatedCol] = 'NOW()';
    }

    $fields = [];
    $marks = [];
    foreach ($insert as $col => $value) {
        $fields[] = '`' . str_replace('`', '``', $col) . '`';
        $marks[] = '?';
        $params[] = $value;
    }
    foreach ($nowColumns as $col => $expr) {
        $fields[] = '`' . str_replace('`', '``', $col) . '`';
        $marks[] = $expr;
    }

    $sql = 'INSERT INTO usuarios (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $marks) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $userId = (int)$pdo->lastInsertId();
    if ($userId <= 0) {
        json_out(false, ['message' => 'Não foi possível criar o usuário.'], 500);
    }

    // Cadastro normal do app também inicia sem cursos. O admin libera depois.
    if (function_exists('gab_curso_acesso_marcar_usuario_sem_cursos')) {
        gab_curso_acesso_marcar_usuario_sem_cursos($pdo, $userId, 'cadastro_app');
    }

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_out(false, ['message' => 'Usuário criado, mas não foi possível carregar os dados.'], 500);
    }

    require_app_tokens_table($pdo);

    $token = bin2hex(random_bytes(32));
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $stmt = $pdo->prepare('INSERT INTO app_tokens (user_id, token, user_agent, ip_address, expires_at, created_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())');
    $stmt->execute([$userId, $token, $userAgent, $ipAddress]);

    json_out(true, [
        'message' => 'Conta criada com sucesso.',
        'token' => $token,
        'user' => normalize_user($pdo, $user),
    ], 201);
} catch (Throwable $e) {
    error_log('[API Mobile] Erro no cadastro.php: ' . $e->getMessage());

    $payload = ['message' => 'Erro interno ao criar conta.'];
    if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
        $payload['debug'] = $e->getMessage();
    }

    json_out(false, $payload, 500);
}
