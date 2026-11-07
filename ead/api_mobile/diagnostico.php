<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function diagnostico_tabelas(PDO $pdo): array
{
    $tabelas = [];
    try {
        $stmt = $pdo->query('SHOW TABLES');
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
            if (isset($row[0])) {
                $tabelas[] = (string)$row[0];
            }
        }
        sort($tabelas);
    } catch (Throwable $e) {
        $tabelas[] = 'erro_ao_listar_tabelas: ' . $e->getMessage();
    }
    return $tabelas;
}

function diagnostico_existe_por_lista(array $tabelas, string $nome): bool
{
    foreach ($tabelas as $tabela) {
        if (strtolower((string)$tabela) === strtolower($nome)) {
            return true;
        }
    }
    return false;
}

$databaseAtual = null;
$erroDatabase = null;
try {
    $databaseAtual = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    $erroDatabase = $e->getMessage();
}

// Ao abrir o diagnóstico, já tentamos criar/ajustar app_tokens automaticamente.
$appTokensCriadoOuAjustado = ensure_app_tokens_table($pdo);

$tabelas = diagnostico_tabelas($pdo);
$usuariosExiste = diagnostico_existe_por_lista($tabelas, 'usuarios');
$appTokensExiste = diagnostico_existe_por_lista($tabelas, 'app_tokens');

$tabelasParecidas = [];
foreach ($tabelas as $tabela) {
    if (is_string($tabela) && (stripos($tabela, 'token') !== false || stripos($tabela, 'usuario') !== false || stripos($tabela, 'user') !== false)) {
        $tabelasParecidas[] = $tabela;
    }
}

json_out(true, [
    'message' => 'Diagnóstico da API Mobile Gabaritando.',
    'database_atual_da_api' => $databaseAtual,
    'erro_database' => $erroDatabase,
    'usuarios' => $usuariosExiste ? 'existe' : 'nao_encontrada',
    'usuarios_colunas' => $usuariosExiste ? array_keys(table_cols($pdo, 'usuarios')) : [],
    'app_tokens' => $appTokensExiste ? 'existe' : 'nao_encontrada',
    'app_tokens_criado_ou_ajustado' => $appTokensCriadoOuAjustado ? 'sim' : 'nao',
    'app_tokens_colunas' => $appTokensExiste ? array_keys(table_cols($pdo, 'app_tokens')) : [],
    'tabelas_parecidas' => $tabelasParecidas,
    'total_tabelas_no_banco' => count($tabelas),
    'observacao' => 'Se database_atual_da_api estiver certo e usuarios/app_tokens aparecerem existe, a API esta pronta para testar o login do app.',
]);
