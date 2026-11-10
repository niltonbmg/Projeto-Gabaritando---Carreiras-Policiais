<?php
// api/processa_cadastro.php
// Cadastro normal do Projeto Gabaritando.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../includes/boas-vindas.php';
require_once __DIR__ . '/../includes/curso-acesso.php';

function gab_cadastro_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function gab_cadastro_back(string $erro, array $old = []): void
{
    unset($old['senha'], $old['senha_confirmacao'], $old['password']);
    $_SESSION['cadastro_erro'] = $erro;
    $_SESSION['cadastro_old'] = $old;
    gab_cadastro_redirect('../cadastro');
}

function gab_cadastro_safe_redirect(string $redirect, string $default = '../dashboard'): string
{
    $redirect = trim(rawurldecode($redirect));

    for ($i = 0; $i < 6; $i++) {
        if (preg_match('/[?&]redirect=([^&]+)/i', $redirect, $match)) {
            $proximo = trim(rawurldecode((string)$match[1]));
            if ($proximo !== '' && $proximo !== $redirect) {
                $redirect = $proximo;
                continue;
            }
        }
        break;
    }

    $redirect = str_replace('\\', '/', $redirect);

    if ($redirect === '' || strpos($redirect, '://') !== false || str_starts_with($redirect, '//')) {
        return $default;
    }

    $limpo = ltrim($redirect, '/');
    $path = (string)(parse_url($limpo, PHP_URL_PATH) ?: $limpo);
    $pathNormalizado = mb_strtolower(trim($path), 'UTF-8');
    $pathNormalizado = preg_replace('/\.php$/i', '', $pathNormalizado) ?: $pathNormalizado;

    if ($pathNormalizado === '' || in_array($pathNormalizado, ['login', 'cadastro', 'api/processa_cadastro'], true)) {
        return $default;
    }

    if (str_starts_with($redirect, '/')) {
        return $redirect;
    }

    if (str_starts_with($redirect, '../')) {
        return $redirect;
    }

    return '../' . ltrim($redirect, '/');
}

function gab_cadastro_columns(PDO $pdo, string $table): array
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

function gab_cadastro_has_column(array $columns, string $column): bool
{
    return in_array($column, $columns, true);
}

function gab_cadastro_login(array $user): void
{
    session_regenerate_id(true);

    $id = (int)($user['id'] ?? 0);
    $nome = (string)($user['nome'] ?? 'Usuário');
    $email = (string)($user['email'] ?? '');
    $tipo = (string)($user['tipo'] ?? 'cliente');

    $_SESSION['usuario'] = [
        'id' => $id,
        'nome' => $nome,
        'email' => $email,
        'tipo' => $tipo,
    ];

    // Compatibilidade com todas as páginas já criadas.
    $_SESSION['usuario_id'] = $id;
    $_SESSION['id_usuario'] = $id;
    $_SESSION['user_id'] = $id;
    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_email'] = $email;
    $_SESSION['usuario_tipo'] = $tipo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gab_cadastro_redirect('../cadastro');
}

$old = $_POST;
$nome = trim((string)($_POST['nome'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$telefone = trim((string)($_POST['telefone'] ?? $_POST['celular'] ?? ''));
$senha = (string)($_POST['senha'] ?? $_POST['password'] ?? '');
$senhaConfirmacao = (string)($_POST['senha_confirmacao'] ?? $_POST['confirmar_senha'] ?? '');
$redirect = (string)($_POST['redirect'] ?? 'dashboard');
$aceiteTermos = !empty($_POST['aceite_termos']);

if ($nome === '' || mb_strlen($nome, 'UTF-8') < 3) {
    gab_cadastro_back('Informe seu nome completo.', $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    gab_cadastro_back('Informe um e-mail válido.', $old);
}

if (strlen($senha) < 6) {
    gab_cadastro_back('A senha precisa ter no mínimo 6 caracteres.', $old);
}

if ($senha !== $senhaConfirmacao) {
    gab_cadastro_back('As senhas não conferem.', $old);
}

if (!$aceiteTermos) {
    gab_cadastro_back('Você precisa aceitar os termos para criar a conta.', $old);
}

try {
    $pdo = db();
    gab_boas_vindas_bootstrap($pdo);
    $columns = gab_cadastro_columns($pdo, 'usuarios');

    if (!$columns || !gab_cadastro_has_column($columns, 'email')) {
        gab_cadastro_back('Tabela de usuários não encontrada ou incompleta no banco de dados.', $old);
    }

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        gab_cadastro_back('Já existe uma conta cadastrada com esse e-mail.', $old);
    }

    $insert = [];
    $params = [];

    if (gab_cadastro_has_column($columns, 'nome')) {
        $insert['nome'] = ':nome';
        $params[':nome'] = $nome;
    }

    $insert['email'] = ':email';
    $params[':email'] = $email;

    if (gab_cadastro_has_column($columns, 'senha_hash')) {
        $insert['senha_hash'] = ':senha_hash';
        $params[':senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
    } elseif (gab_cadastro_has_column($columns, 'senha')) {
        $insert['senha'] = ':senha_hash';
        $params[':senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
    } elseif (gab_cadastro_has_column($columns, 'password_hash')) {
        $insert['password_hash'] = ':senha_hash';
        $params[':senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
    }

    if (gab_cadastro_has_column($columns, 'telefone')) {
        $insert['telefone'] = ':telefone';
        $params[':telefone'] = $telefone !== '' ? $telefone : null;
    }

    if (gab_cadastro_has_column($columns, 'tipo')) {
        $insert['tipo'] = ':tipo';
        $params[':tipo'] = 'cliente';
    }

    if (gab_cadastro_has_column($columns, 'status')) {
        $insert['status'] = ':status';
        $params[':status'] = 'ativo';
    }

    if (gab_cadastro_has_column($columns, 'boas_vindas_visualizada')) {
        $insert['boas_vindas_visualizada'] = ':boas_vindas_visualizada';
        $params[':boas_vindas_visualizada'] = 0;
    }

    if (!$insert) {
        gab_cadastro_back('Não foi possível identificar os campos da tabela de usuários.', $old);
    }

    $campos = '`' . implode('`, `', array_keys($insert)) . '`';
    $valores = implode(', ', array_values($insert));
    $stmt = $pdo->prepare("INSERT INTO usuarios ({$campos}) VALUES ({$valores})");
    $stmt->execute($params);

    $usuarioId = (int)$pdo->lastInsertId();

    // LGPD: guarda a prova do aceite dos Termos e da Política (versão, data, IP parcial).
    try {
        require_once dirname(__DIR__) . '/includes/lgpd-lib.php';
        lgpd_registrar_aceite($pdo, $usuarioId, 'cadastro');
    } catch (Throwable $e) {
        error_log('LGPD aceite cadastro: ' . $e->getMessage());
    }

    // Regra atual: cadastro normal começa sem nenhum curso liberado.
    // O admin atribui os cursos depois em admin-usuarios.php.
    if (function_exists('gab_curso_acesso_marcar_usuario_sem_cursos')) {
        gab_curso_acesso_marcar_usuario_sem_cursos($pdo, $usuarioId, 'cadastro_normal');
    }

    unset($_SESSION['cadastro_erro'], $_SESSION['cadastro_old']);

    gab_cadastro_login([
        'id' => $usuarioId,
        'nome' => $nome,
        'email' => $email,
        'tipo' => 'cliente',
    ]);

    gab_cadastro_redirect(gab_cadastro_safe_redirect($redirect));
} catch (Throwable $e) {
    error_log('[Projeto Gabaritando] Erro no cadastro: ' . $e->getMessage());
    gab_cadastro_back('Não foi possível criar sua conta agora. Verifique o banco de dados e tente novamente.', $old);
}
