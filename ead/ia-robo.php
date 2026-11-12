<?php
// ia-robo.php
// Robô da IA: processa a fila (apostilas e podcasts) sem ninguém com a tela aberta.
// Hostinger -> Avançado -> Cron Jobs -> a cada 5 minutos:
//     /usr/bin/php /home/<usuario>/public_html/ead/ia-robo.php
// Pela web (para teste), só com a senha do robô: ia-robo.php?chave=ROBO_CHAVE (do .env).
declare(strict_types=1);

require_once __DIR__ . '/includes/ia-fila-lib.php';
require_once __DIR__ . '/includes/ia-classificar-lib.php';
require_once __DIR__ . '/includes/desafio-lib.php';

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
@touch(__DIR__ . '/uploads/.ia-robo-ultima'); // sinal de vida para a página Saúde da plataforma
$pdo = db();
// Desafio do Dia já pronto quando o primeiro aluno abrir (e a mensagem do WhatsApp também).
try {
    date_default_timezone_set('America/Bahia');
    if (date('Y-m-d') >= DESAFIO_INICIO) {
        desafio_gerar($pdo, date('Y-m-d'));
    }
    // Monta o de amanhã antes da meia-noite: na virada o servidor só entrega, não monta nada.
    if ((int)date('G') >= 22 && date('Y-m-d', strtotime('+1 day')) >= DESAFIO_INICIO) {
        desafio_gerar($pdo, date('Y-m-d', strtotime('+1 day')));
    }
} catch (Throwable $e) {
    echo 'Desafio do dia: ' . $e->getMessage() . "\n";
}
// Pico do Desafio do Dia (23:50 às 00:30): a IA espera, todo o processador fica para os alunos.
$hm = (int)date('Hi');
if ($hm >= 2350 || $hm < 30) {
    echo "Pausa do pico do Desafio do Dia (23:50 às 00:30).\n";
    exit;
}
// Uma vez só: fórmulas em LaTeX ($n$, \times) que a IA já tinha gravado viram texto legível.
try {
    require_once __DIR__ . '/includes/texto-limpo-lib.php';
    if ($n = gab_limpar_latex_existente($pdo)) {
        echo "Fórmulas corrigidas em $n registro(s)\n";
    }
} catch (Throwable $e) {
    echo 'Fórmulas: ' . $e->getMessage() . "\n";
}
// Radar do Concurso: 1 pesquisa por dia para cada concurso que tem aluno.
try {
    require_once __DIR__ . '/includes/radar-lib.php';
    if ($n = radar_varrer($pdo)) {
        echo "Radar na fila: $n concurso(s)\n";
    }
} catch (Throwable $e) {
    echo 'Radar: ' . $e->getMessage() . "\n";
}
// Visual novo (formatos + Antigão) para videoaulas já prontas, sem regravar áudio.
try {
    if ($n = videoaula_visual_varrer($pdo)) {
        echo "Visual novo na fila: $n videoaula(s)\n";
    }
} catch (Throwable $e) {
    echo 'Visual: ' . $e->getMessage() . "\n";
}
// Revisão: aula com PDF novo ou trocado entra na fila de conferência (barato, e destrava as videoaulas).
try {
    require_once __DIR__ . '/includes/revisao-aulas-lib.php';
    if ($n = rev_varrer($pdo)) {
        echo "Conferência de PDF na fila: $n aula(s)\n";
    }
} catch (Throwable $e) {
    echo 'Revisão: ' . $e->getMessage() . "\n";
}
$feitos = 0;
// Tarefas do cadastro do concurso (edital, provas, verticalizado...) que o mentor deixou na fila.
require_once __DIR__ . '/includes/edital-jobs-lib.php';
while (time() - $inicio < $limite - 120 && ($j = job_rodar_proximo($pdo)) !== null) {
    $pdo = podcast_reconectar($pdo);
    $feitos++;
    echo date('H:i:s') . ' cadastro: ' . $j['rotulo'] . ' -> ' . $j['status'] . "\n";
}
while (time() - $inicio < $limite - 60) { // deixa margem para o item em andamento
    $r = ia_fila_processar_um($pdo);
    $pdo = podcast_reconectar($pdo);
    if ($r === null) {
        break;
    }
    $feitos++;
    echo date('H:i:s') . ' ' . $r['tipo'] . ' aula ' . $r['aula_id'] . ': ' . ($r['ok'] ? 'ok' : 'erro: ' . $r['erro']) . "\n";
}
// Depois: classificação automática do que entrou sem assunto (aulas, questões, materiais).
$novos = ia_class_varrer($pdo);
if ($novos) {
    echo "Novos itens para classificar: $novos\n";
}
while (time() - $inicio < $limite - 30 && ($r = ia_class_processar_um($pdo)) !== null) {
    $pdo = podcast_reconectar($pdo);
    $feitos++;
    echo date('H:i:s') . ' classificação ' . $r['id'] . ': ' . ($r['ok'] ? 'sugerida' : 'erro: ' . $r['erro']) . "\n";
}
echo $feitos === 0 ? "Nada a fazer.\n" : "Itens processados: $feitos\n";
flock($trava, LOCK_UN);
