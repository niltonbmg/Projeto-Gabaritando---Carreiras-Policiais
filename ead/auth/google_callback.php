<?php
// auth/google_callback.php
require_once __DIR__ . '/config_google.php';
require_once __DIR__ . '/../api/conexao.php';
require_once __DIR__ . '/../includes/boas-vindas.php';

if (!isset($_GET['code'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        header('Location: ../login.php?erro=google_token');
        exit;
    }

    $client->setAccessToken($token['access_token']);

    $google_oauth = new Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();

    $google_id = trim((string)($google_account_info->id ?? ''));
    $email = trim((string)($google_account_info->email ?? ''));
    $nome = trim((string)($google_account_info->name ?? 'Usuário Hobbi'));

    if ($google_id === '' || $email === '') {
        header('Location: ../login.php?erro=google_dados');
        exit;
    }

    $pdo = db();
    gab_boas_vindas_bootstrap($pdo);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE google_id = :gid OR email = :email LIMIT 1");
    $stmt->execute([':gid' => $google_id, ':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $usuarioId = (int)$user['id'];

        $stmtUpd = $pdo->prepare("
            UPDATE usuarios
               SET google_id = :gid,
                   nome = CASE WHEN nome IS NULL OR nome = '' THEN :nome ELSE nome END,
                   status = 'ativo'
             WHERE id = :id
        ");
        $stmtUpd->execute([
            ':gid' => $google_id,
            ':nome' => $nome,
            ':id' => $usuarioId,
        ]);

        $stmtReload = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmtReload->execute([':id' => $usuarioId]);
        $user = $stmtReload->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO usuarios (nome, email, google_id, tipo, status, boas_vindas_visualizada, created_at)
            VALUES (:nome, :email, :gid, 'cliente', 'ativo', 0, NOW())
        ");
        $stmtIns->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':gid' => $google_id,
        ]);

        $usuarioId = (int)$pdo->lastInsertId();

        $stmtReload = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmtReload->execute([':id' => $usuarioId]);
        $user = $stmtReload->fetch(PDO::FETCH_ASSOC);
    }

    $stmtEnd = $pdo->prepare("SELECT * FROM enderecos WHERE usuario_id = :uid AND principal = 1 LIMIT 1");
    $stmtEnd->execute([':uid' => $usuarioId]);
    $endereco_principal = $stmtEnd->fetch(PDO::FETCH_ASSOC) ?: null;

    $pdo->commit();

    session_regenerate_id(true);

    $_SESSION['usuario_id']         = $usuarioId;
    $_SESSION['user_pgm_id_cus']    = (string)($user['pagarme_id_cus'] ?? '');
    $_SESSION['pagarme_id_addr']    = (string)($user['pagarme_id_addr'] ?? '');
    $_SESSION['usuario_nome']       = (string)($user['nome'] ?? $nome);
    $_SESSION['usuario_email']      = (string)($user['email'] ?? $email);
    $_SESSION['usuario_nascimento'] = (string)($user['data_nascimento'] ?? '');
    $_SESSION['usuario_tipo']       = (string)($user['tipo'] ?? 'cliente');
    $_SESSION['usuario_cpf']        = (string)($user['cpf'] ?? '');
    $_SESSION['telefone']           = (string)($user['telefone'] ?? '');
    $_SESSION['usuario_endereco']   = $endereco_principal;
    $_SESSION['usuario_cep']        = (string)($endereco_principal['cep'] ?? '');
    $_SESSION['usuario_cidade']     = (string)($endereco_principal['cidade'] ?? '');

    $_SESSION['hobbi_user'] = [
        'id'    => $usuarioId,
        'nome'  => (string)($user['nome'] ?? $nome),
        'email' => (string)($user['email'] ?? $email),
    ];
    $_SESSION['hobbi_user_nome'] = (string)($user['nome'] ?? $nome);

    unset($_SESSION['google_temp_data'], $_SESSION['temp_dados_google'], $_SESSION['google_cadastro_erro']);

    session_write_close();
    header('Location: ../perfil.php');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Google Login Error: ' . $e->getMessage());
    header('Location: ../login.php?erro=interno');
    exit;
}
