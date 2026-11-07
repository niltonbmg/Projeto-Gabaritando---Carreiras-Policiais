<?php
// ia-robo.php
// Robô da IA: processa a fila (apostilas e podcasts) sem ninguém com a tela aberta.
// Hostinger -> Avançado -> Cron Jobs -> a cada 5 minutos:
//     /usr/bin/php /home/<usuario>/public_html/ead/ia-robo.php
// Pela web (para teste), só com a senha do robô: ia-robo.php?chave=ROBO_CHAVE (do .env).
declare(strict_types=1);

require_once __DIR__ . '/includes/ia-fila-lib.php';

$viaCli = PHP_SAPI === 'cli';
if (!$viaCli) {
    $chave = gab_env('ROBO_CHAVE', '');
    if ($chave === '' || !hash_equals($chave, (string)($_GET['chave'] ?? ''))) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$limite = $viaCli ? 270 : 100; // segundos de trabalho por execução
@set_time_limit($limite + 60);
@ignore_user_abort(true);

// Uma execução por vez.
$trava = fopen(__DIR__ . '/uploads/.ia-robo.lock', 'c');
if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
    echo "Outra execução do robô ainda está trabalhando.\n";
    exit;
}

$inicio = time();
$pdo = db();
$feitos = 0;
while (time() - $inicio < $limite - 60) { // deixa margem para o item em andamento
    $r = ia_fila_processar_um($pdo);
    $pdo = podcast_reconectar($pdo);
    if ($r === null) {
        break;
    }
    $feitos++;
    echo date('H:i:s') . ' ' . $r['tipo'] . ' aula ' . $r['aula_id'] . ': ' . ($r['ok'] ? 'ok' : 'erro: ' . $r['erro']) . "\n";
}
echo $feitos === 0 ? "Fila vazia.\n" : "Itens processados: $feitos\n";
flock($trava, LOCK_UN);
