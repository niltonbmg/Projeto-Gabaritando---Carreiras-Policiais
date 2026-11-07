<?php
// api/processa_cadastro_google.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['google_temp_data'])) { header('Location: ../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../cadastro_finalizar.php'); exit; }

require __DIR__ . '/conexao.php';
require_once __DIR__ . '/../includes/boas-vindas.php';
$pdo = db();
gab_boas_vindas_bootstrap($pdo);

// --- FUNÇÃO DE VALIDAÇÃO DE CPF ---
function validaCPF($cpf) {
    $cpf = preg_replace( '/[^0-9]/is', '', $cpf );
    if (strlen($cpf) != 11) { return false; }
    if (preg_match('/(\d)\1{10}/', $cpf)) { return false; }
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) { return false; }
    }
    return true;
}
// ----------------------------------

$gData = $_SESSION['google_temp_data'];
$nome = $gData['nome'];
$email = $gData['email'];
$google_id = $gData['google_id'];

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$telefone = preg_replace('/\D/', '', $_POST['celular'] ?? '');
$cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
$nascimentoSQL = $_POST['nascimento'] ?? '';
// Novo: tipo e referencia do endereço
$tipo = trim($_POST['tipo'] ?? 'Residencial');
$referencia = trim($_POST['referencia'] ?? '');

// Função auxiliar para retornar erro E SALVAR DADOS
function returnError($msg) {
    $_SESSION['google_cadastro_erro'] = $msg;
    // Salva os dados do POST para repopular o form
    $_SESSION['temp_dados_google'] = $_POST;
    header('Location: ../cadastro_finalizar.php');
    exit;
}

// 1. Validação Básica
if (!$cpf || !$telefone || !$cep || !$nascimentoSQL) {
    returnError("Preencha todos os campos obrigatórios.");
}

// 2. Validação CPF Matemática
if (!validaCPF($cpf)) {
    returnError("O CPF informado é inválido.");
}

try {
    $pdo->beginTransaction();

    // 3. Validação Duplicidade CPF (Se existir, mas não for o mesmo usuário do email)
    // O select busca se tem algum ID com esse CPF
    $stmtCk = $pdo->prepare("SELECT id, email FROM usuarios WHERE cpf = :cpf LIMIT 1");
    $stmtCk->execute([':cpf' => $cpf]);
    $cpfOwner = $stmtCk->fetch(PDO::FETCH_ASSOC);

    // Se achou o CPF, precisamos ver se é da mesma pessoa (pelo email)
    if ($cpfOwner) {
        if ($cpfOwner['email'] !== $email) {
            // CPF pertence a outro email -> ERRO COM LINK
            returnError('Este CPF já está cadastrado em outra conta. <a href="login.php" class="font-bold underline text-red-800 hover:text-red-900">Faça login aqui</a>.');
        }
        // Se o CPF existe E o email é o mesmo, deixamos passar para o update abaixo (merge de conta)
    }

    $checkUser = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $checkUser->execute([':email' => $email]);
    $existingId = $checkUser->fetchColumn();

    if ($existingId) {
        $usuarioId = $existingId;
        $stmtUpdate = $pdo->prepare("
            UPDATE usuarios SET google_id = :gid, telefone = :tel, cpf = :cpf, data_nascimento = :nasc, status = 'ativo' 
            WHERE id = :id
        ");
        $stmtUpdate->execute([':gid' => $google_id, ':tel' => $telefone, ':cpf' => $cpf, ':nasc' => $nascimentoSQL, ':id' => $usuarioId]);
    } else {
        $stmtUser = $pdo->prepare("
            INSERT INTO usuarios (nome, email, google_id, telefone, cpf, data_nascimento, tipo, status, boas_vindas_visualizada, created_at)
            VALUES (:nome, :email, :gid, :tel, :cpf, :nasc, 'cliente', 'ativo', 0, NOW())
        ");
        $stmtUser->execute([':nome' => $nome, ':email' => $email, ':gid' => $google_id, ':tel' => $telefone, ':cpf' => $cpf, ':nasc' => $nascimentoSQL]);
        $usuarioId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("UPDATE enderecos SET principal = 0 WHERE usuario_id = ?")->execute([$usuarioId]);

    $stmtEnd = $pdo->prepare("
           INSERT INTO enderecos (usuario_id, tipo, cep, uf, cidade, bairro, logradouro, numero, complemento, referencia, titulo, destinatario, principal, created_at)
           VALUES (:uid, :tipo, :cep, :uf, :cidade, :bairro, :logr, :num, :comp, :referencia, :titulo, :destinatario, 1, NOW())
    ");
    $stmtEnd->execute([
           ':uid' => $usuarioId,
           ':tipo' => $tipo,
           ':cep' => $cep,
           ':uf' => $_POST['uf'],
           ':cidade' => $_POST['cidade'],
           ':bairro' => $_POST['bairro'],
           ':logr' => $_POST['logradouro'],
           ':num' => $_POST['numero'],
           ':comp' => $_POST['complemento'],
           ':referencia' => $referencia,
           ':titulo' => 'Endereço principal',
           ':destinatario' => $nome
    ]);

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_email'] = $email;
    $_SESSION['usuario_tipo'] = 'cliente';
    
    $_SESSION['hobbi_user'] = ['id' => $usuarioId, 'nome' => $nome, 'email' => $email];
    $_SESSION['hobbi_user_nome'] = $nome;

    unset($_SESSION['google_temp_data']);
    unset($_SESSION['temp_dados_google']); // Limpa dados temporários
    session_write_close();
    header('Location: ../index.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Erro cadastro Google: " . $e->getMessage());
    returnError("Erro interno ao finalizar cadastro. Tente novamente.");
}
