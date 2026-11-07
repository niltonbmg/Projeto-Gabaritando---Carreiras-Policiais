<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_out(false, ['message' => 'Método inválido. Envie POST com login/email e senha.'], 405);
    }

    $data = body_json();
    $login = trim((string)($data['login'] ?? $data['email'] ?? ''));
    $senha = (string)($data['senha'] ?? $data['password'] ?? '');

    if ($login === '' || $senha === '') {
        json_out(false, ['message' => 'Informe login e senha.'], 422);
    }

    if (!table_exists($pdo, 'usuarios')) {
        json_out(false, ['message' => 'Tabela usuarios não encontrada.'], 500);
    }

    $cols = table_cols($pdo, 'usuarios');

    $loginPossiveis = ['email', 'login', 'usuario', 'username', 'apelido', 'nome', 'nome_usuario'];
    $loginCols = [];
    foreach ($loginPossiveis as $col) {
        if (isset($cols[$col])) {
            $loginCols[] = $col;
        }
    }

    $senhaCol = first_col($cols, ['senha_hash', 'password_hash', 'senha', 'password', 'senha_usuario']);

    if (!$loginCols || !$senhaCol) {
        json_out(false, ['message' => 'A tabela usuarios precisa ter campo de login/e-mail e senha.'], 500);
    }

    $where = [];
    $params = [];
    $loginCompacto = api_compact_login($login);

    foreach ($loginCols as $col) {
        $safeCol = str_replace('`', '``', $col);

        // Busca normal: e-mail/usuário exatamente como está cadastrado.
        $where[] = "LOWER(TRIM(`{$safeCol}`)) = LOWER(TRIM(?))";
        $params[] = $login;

        // Busca compacta para telefone/CPF/usuário sem pontos, traços, espaços etc.
        // Removi o REPLACE de barra invertida porque em alguns MySQL ele quebra o SQL e causa erro 500.
        $where[] = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`{$safeCol}`), ' ', ''), '.', ''), '-', ''), '_', ''), '/', ''), '(', ''), ')', '')) = LOWER(?)";
        $params[] = $loginCompacto;
    }

    $sql = 'SELECT * FROM usuarios WHERE (' . implode(' OR ', $where) . ') LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_out(false, ['message' => 'Login ou senha inválidos.'], 401);
    }

    $hash = trim((string)($user[$senhaCol] ?? ''));
    $valid = false;

    if ($hash !== '') {
        $info = password_get_info($hash);
        if (!empty($info['algo'])) {
            $valid = password_verify($senha, $hash);
        }

        if (!$valid && hash_equals($hash, $senha)) {
            $valid = true;
        }
        if (!$valid && hash_equals(strtolower($hash), md5($senha))) {
            $valid = true;
        }
        if (!$valid && hash_equals(strtolower($hash), sha1($senha))) {
            $valid = true;
        }
        if (!$valid && strlen($hash) >= 13 && hash_equals($hash, crypt($senha, $hash))) {
            $valid = true;
        }
    }

    if (!$valid) {
        json_out(false, ['message' => 'Login ou senha inválidos.'], 401);
    }

    $normal = normalize_user($pdo, $user);
    if ($normal['bloqueado']) {
        json_out(false, ['message' => 'Usuário bloqueado. Fale com o administrador.'], 403);
    }

    require_app_tokens_table($pdo);

    $token = bin2hex(random_bytes(32));
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $stmt = $pdo->prepare('INSERT INTO app_tokens (user_id, token, user_agent, ip_address, expires_at, created_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())');
    $stmt->execute([(int)$user['id'], $token, $userAgent, $ipAddress]);

    if (isset($cols['ultimo_login'])) {
        try {
            $update = $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?');
            $update->execute([(int)$user['id']]);
        } catch (Throwable $e) {
            error_log('[API Mobile] Falha ao atualizar ultimo_login: ' . $e->getMessage());
        }
    }

    json_out(true, [
        'message' => 'Login realizado com sucesso.',
        'token' => $token,
        'user' => $normal,
    ]);
} catch (Throwable $e) {
    error_log('[API Mobile] Erro no login.php: ' . $e->getMessage());

    $payload = ['message' => 'Erro interno no login da API.'];
    if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
        $payload['debug'] = $e->getMessage();
    }

    json_out(false, $payload, 500);
}
