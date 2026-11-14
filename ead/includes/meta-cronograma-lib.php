<?php
// includes/meta-cronograma-lib.php
// Cronograma individual do Método META.
//
// Como funciona:
//   - O aluno informa horas por dia, dias da semana, início e data da prova.
//   - O sistema distribui os assuntos do Plano-Mestre (na ordem, com as horas
//     de cada um) nos dias disponíveis, sempre a partir de HOJE: o que ficou
//     para trás é empurrado para frente automaticamente (sem acumular atraso
//     escondido).
//   - Cada assunto vira duas tarefas: Teoria mínima e Questões.
//   - Assuntos já liberados geram revisões espaçadas em +1, +7 e +30 dias
//     depois da conclusão da teoria.
//   - Compara o ritmo com a data da prova e com o plano original
//     (calculado a partir da data de início) para mostrar atraso.
declare(strict_types=1);

require_once __DIR__ . '/meta-lib.php';
require_once __DIR__ . '/meta-tempo-lib.php';

const META_REVISOES_DIAS = [1, 7, 30];
const META_REVISAO_MINUTOS = 20;
const META_FRACAO_TEORIA = 0.4;
const META_BLOCO_MIN = 60; // máximo de minutos seguidos da mesma matéria

if (!function_exists('meta_cronograma_bootstrap')) {
    function meta_cronograma_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        meta_bootstrap($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_aluno_config (
            usuario_id BIGINT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            horas_dia DECIMAL(4,2) NOT NULL DEFAULT 3.00,
            dias_semana VARCHAR(120) NOT NULL DEFAULT '1,2,3,4,5,6',
            data_inicio DATE NOT NULL,
            data_prova DATE NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Corrige instalações antigas: 20 caracteres não cabiam "1:3,2:3,...,0:2".
        $col = $pdo->query("SHOW COLUMNS FROM meta_aluno_config LIKE 'dias_semana'")->fetch();
        if ($col && preg_match('/varchar\((\d+)\)/i', (string)$col['Type'], $m) && (int)$m[1] < 120) {
            $pdo->exec("ALTER TABLE meta_aluno_config MODIFY dias_semana VARCHAR(120) NOT NULL DEFAULT '1,2,3,4,5,6'");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_aluno_revisoes (
            usuario_id BIGINT UNSIGNED NOT NULL,
            plano_item_id INT UNSIGNED NOT NULL,
            rodada TINYINT UNSIGNED NOT NULL,
            feita_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, plano_item_id, rodada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('meta_concluir_revisao')) {
    function meta_concluir_revisao(PDO $pdo, int $usuarioId, int $planoItemId, int $rodada): void
    {
        if ($rodada < 1 || $rodada > count(META_REVISOES_DIAS)) {
            throw new RuntimeException('Revisão inválida.');
        }
        $pdo->prepare('INSERT IGNORE INTO meta_aluno_revisoes (usuario_id, plano_item_id, rodada) VALUES (:u, :i, :r)')
            ->execute([':u' => $usuarioId, ':i' => $planoItemId, ':r' => $rodada]);
    }
}

if (!function_exists('meta_revisoes_feitas')) {
    /** @return array<string,bool> chave "itemId:rodada" */
    function meta_revisoes_feitas(PDO $pdo, int $usuarioId): array
    {
        $stmt = $pdo->prepare('SELECT plano_item_id, rodada FROM meta_aluno_revisoes WHERE usuario_id = :u');
        $stmt->execute([':u' => $usuarioId]);
        $feitas = [];
        $implicitas = [];
        foreach ($stmt->fetchAll() as $r) {
            $feitas[(int)$r['plano_item_id'] . ':' . (int)$r['rodada']] = true;
            if ((int)$r['plano_item_id'] >= 3000000000) {
                $implicitas[(int)$r['plano_item_id'] - 3000000000][] = (int)$r['rodada'];
            }
        }
        // Revisões feitas no plano inicial (ids 3.000.000.000 + aula) continuam valendo quando o Plano-Mestre é aprovado.
        if ($implicitas) {
            try {
                $st = $pdo->query('SELECT i.id, i.aula_id FROM meta_plano_itens i JOIN meta_planos p ON p.id = i.plano_id AND p.status = \'aprovado\' WHERE i.aula_id IN (' . implode(',', array_map('intval', array_keys($implicitas))) . ')');
                foreach ($st->fetchAll() as $it) {
                    foreach ($implicitas[(int)$it['aula_id']] ?? [] as $rod) {
                        $feitas[(int)$it['id'] . ':' . $rod] = true;
                    }
                }
            } catch (Throwable $e) {
            }
        }
        return $feitas;
    }
}

if (!function_exists('meta_cronograma_config')) {
    function meta_cronograma_config(PDO $pdo, int $usuarioId, int $cursoId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM meta_aluno_config WHERE usuario_id = :u AND curso_id = :c');
        $stmt->execute([':u' => $usuarioId, ':c' => $cursoId]);
        $cfg = $stmt->fetch();
        if (!$cfg) {
            return null;
        }
        // dias_semana: "1:3,2:3,6:5" = dia da semana (0=dom) : horas daquele dia.
        // Formato antigo "1,2,3" usa horas_dia para todos.
        $minutos = [];
        foreach (explode(',', (string)$cfg['dias_semana']) as $parte) {
            [$d, $h] = array_pad(explode(':', trim($parte)), 2, (string)$cfg['horas_dia']);
            $d = (int)$d;
            $m = (int)round((float)$h * 60);
            if ($d >= 0 && $d <= 6 && $m > 0) {
                $minutos[$d] = $m;
            }
        }
        if (!$minutos) {
            $minutos = array_fill_keys([1, 2, 3, 4, 5, 6], 180);
        }
        $cfg['minutos'] = $minutos;
        $cfg['dias'] = array_keys($minutos);
        $cfg['horas_semana'] = array_sum($minutos) / 60;
        return $cfg;
    }
}

if (!function_exists('meta_cronograma_salvar_config')) {
    function meta_cronograma_salvar_config(PDO $pdo, int $usuarioId, int $cursoId, array $dados): void
    {
        // $dados['horas'][dia] = horas daquele dia (0 = não estuda).
        $pares = [];
        $soma = 0.0;
        foreach ((array)($dados['horas'] ?? []) as $d => $h) {
            $d = (int)$d;
            $h = (float)str_replace(',', '.', (string)$h);
            if ($d < 0 || $d > 6 || $h <= 0) {
                continue;
            }
            if ($h > 16) {
                throw new RuntimeException('No máximo 16 horas de estudo em um dia.');
            }
            $pares[$d] = $d . ':' . rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');
            $soma += $h;
        }
        if (!$pares) {
            throw new RuntimeException('Informe as horas de pelo menos um dia da semana.');
        }
        ksort($pares);
        $horas = $soma / count($pares);
        $dias = array_values($pares);
        $inicio = (string)($dados['data_inicio'] ?? '') ?: date('Y-m-d');
        $prova = trim((string)($dados['data_prova'] ?? ''));
        foreach ([$inicio, $prova] as $d) {
            if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                throw new RuntimeException('Data inválida.');
            }
        }
        if ($prova !== '' && $prova <= $inicio) {
            throw new RuntimeException('A data da prova precisa ser depois da data de início.');
        }
        $stmt = $pdo->prepare('INSERT INTO meta_aluno_config (usuario_id, curso_id, horas_dia, dias_semana, data_inicio, data_prova)
                               VALUES (:u, :c, :h, :d, :i, :p)
                               ON DUPLICATE KEY UPDATE horas_dia = VALUES(horas_dia), dias_semana = VALUES(dias_semana), data_inicio = VALUES(data_inicio), data_prova = VALUES(data_prova)');
        $stmt->execute([':u' => $usuarioId, ':c' => $cursoId, ':h' => $horas, ':d' => implode(',', $dias), ':i' => $inicio, ':p' => $prova !== '' ? $prova : null]);
    }
}

if (!function_exists('meta_cronograma_minutos_item')) {
    /** Minutos de teoria e de questões de um assunto, pelas horas do Plano-Mestre. */
    function meta_cronograma_minutos_item(array $item): array
    {
        // Tempo calculado (vídeo + PDF + questões); ver meta-tempo-lib.php.
        if (isset($item['tempo'])) {
            return ['teoria' => max(10, meta_arred5((float)$item['tempo']['teoria'])), 'questoes' => max(15, meta_arred5((float)$item['tempo']['questoes']))];
        }
        $total = max(30, meta_arred5((float)$item['horas'] * 60));
        $teoria = meta_arred5($total * META_FRACAO_TEORIA);
        return ['teoria' => $teoria, 'questoes' => $total - $teoria];
    }
}

if (!function_exists('meta_cronograma_tarefas_pendentes')) {
    /** Fila de tarefas que faltam, na ordem do plano. */
    function meta_cronograma_tarefas_pendentes(array $estado): array
    {
        $fila = [];
        foreach ($estado['itens'] as $item) {
            if ($item['etapa'] === META_ETAPA_CONCLUIDO) {
                continue;
            }
            $min = meta_cronograma_minutos_item($item);
            if (!$item['teoria_ok']) {
                $fila[] = ['tipo' => 'teoria', 'item' => $item, 'minutos' => $min['teoria']];
            }
            $fila[] = ['tipo' => 'questoes', 'item' => $item, 'minutos' => $min['questoes']];
        }
        return $fila;
    }
}

if (!function_exists('meta_cronograma_revisoes')) {
    /** Revisões espaçadas (+1, +7, +30 dias) dos assuntos já liberados. Chave: data Y-m-d. */
    function meta_cronograma_revisoes(array $estado, array $feitas = []): array
    {
        $revisoes = [];
        foreach ($estado['itens'] as $item) {
            if ($item['etapa'] !== META_ETAPA_CONCLUIDO || empty($item['teoria_concluida_em'])) {
                continue;
            }
            if ((int)$item['nivel_info']['nivel'] >= 4) {
                continue; // assunto dominado: sem revisão obrigatória
            }
            $base = new DateTimeImmutable(substr((string)$item['teoria_concluida_em'], 0, 10));
            foreach (META_REVISOES_DIAS as $n => $dias) {
                if (isset($feitas[(int)$item['id'] . ':' . ($n + 1)])) {
                    continue;
                }
                $data = $base->modify('+' . $dias . ' day')->format('Y-m-d');
                $revisoes[$data][] = ['tipo' => 'revisao', 'item' => $item, 'minutos' => META_REVISAO_MINUTOS, 'rodada' => $n + 1];
            }
        }
        return $revisoes;
    }
}

if (!function_exists('meta_cronograma_distribuir')) {
    /**
     * Distribui a fila nos dias disponíveis a partir de $inicio.
     * Uma tarefa maior que o espaço do dia é dividida entre dias.
     * Retorna [data => [tarefas]] e a data em que a fila termina.
     */
    function meta_cronograma_distribuir(array $fila, array $cfg, DateTimeImmutable $inicio, array $revisoes, int $limiteDias = 400): array
    {
        // Revisão em dia sem estudo (ou já passada) vai para o próximo dia de estudo.
        $ajustadas = [];
        foreach ($revisoes as $data => $lista) {
            $d = new DateTimeImmutable($data);
            if ($d < $inicio) {
                $d = $inicio;
            }
            for ($k = 0; $k < 7 && !in_array((int)$d->format('w'), $cfg['dias'], true); $k++) {
                $d = $d->modify('+1 day');
            }
            foreach ($lista as $rev) {
                $ajustadas[$d->format('Y-m-d')][] = $rev;
            }
        }
        $revisoes = $ajustadas;

        // Ciclo de matérias: uma fila por matéria (na ordem do plano). Cada dia
        // alterna as matérias, dando mais tempo às de maior peso, em blocos de
        // até META_BLOCO_MIN minutos.
        $filas = [];
        $pesos = [];
        foreach ($fila as $t) {
            $mid = (int)($t['item']['materia_id'] ?? 0);
            $filas[$mid][] = $t;
            $pesos[$mid][] = max(0.1, (float)($t['item']['peso'] ?? 1));
        }
        $peso = [];
        foreach ($pesos as $mid => $l) {
            $peso[$mid] = array_sum($l) / count($l);
        }
        $usado = array_fill_keys(array_keys($filas), 0.0);
        $restanteTarefa = [];
        foreach ($filas as $mid => $l) {
            $restanteTarefa[$mid] = $l[0]['minutos'];
        }
        $eventos = $cfg['eventos'] ?? [];

        $agenda = [];
        $dia = $inicio;
        $fim = null;
        $semana0 = (int)$inicio->format('oW');
        for ($n = 0; $n < $limiteDias; $n++, $dia = $dia->modify('+1 day')) {
            $data = $dia->format('Y-m-d');
            $dow = (int)$dia->format('w');
            if (!in_array($dow, $cfg['dias'], true)) {
                continue;
            }
            $livre = (int)(floor((int)($cfg['minutos'][$dow] ?? 0) / 5) * 5); // dia em múltiplos de 5
            // Simulado e redação semanais (no dia definido pelo mentor).
            foreach ($eventos as $ev) {
                $semanas = max(1, (int)$ev['cada_semanas']);
                $numSemana = intdiv((int)$dia->format('U') - (int)$inicio->format('U'), 7 * 86400);
                if ($dow === (int)$ev['dia'] && $numSemana % $semanas === 0 && $livre >= 30) {
                    $min = min($livre, (int)$ev['minutos']);
                    $agenda[$data][] = ['tipo' => $ev['tipo'], 'minutos' => $min, 'item' => ['id' => 0, 'titulo' => $ev['titulo'], 'materia_nome' => $ev['rotulo'], 'materia_id' => 0]];
                    $livre -= $min;
                }
            }
            foreach ($revisoes[$data] ?? [] as $rev) {
                $agenda[$data][] = $rev;
                $livre -= $rev['minutos'];
            }
            $materiasHoje = [];
            while ($livre >= 15 && $filas) {
                // Matéria mais "atrasada" em relação ao seu peso; evita repetir a mesma no dia.
                $escolha = null;
                foreach ($filas as $mid => $l) {
                    $nota = $usado[$mid] / $peso[$mid] + (isset($materiasHoje[$mid]) ? 1e6 : 0);
                    if ($escolha === null || $nota < $escolhaNota) {
                        $escolha = $mid;
                        $escolhaNota = $nota;
                    }
                }
                $t = $filas[$escolha][0];
                $usar = (int)(floor(min($livre, $restanteTarefa[$escolha], META_BLOCO_MIN) / 5) * 5);
                if ($usar < 5) {
                    break;
                }
                $ultimo = isset($agenda[$data]) ? count($agenda[$data]) - 1 : -1;
                if ($ultimo >= 0 && ($agenda[$data][$ultimo]['tipo'] ?? '') === $t['tipo'] && (int)($agenda[$data][$ultimo]['item']['id'] ?? -1) === (int)$t['item']['id']) {
                    $agenda[$data][$ultimo]['minutos'] += $usar; // mesmo assunto em sequência: um cartão só
                    $agenda[$data][$ultimo]['parcial'] = $agenda[$data][$ultimo]['minutos'] < $t['minutos'];
                } else {
                    $parte = $t;
                    $parte['minutos'] = $usar;
                    $parte['parcial'] = $usar < $t['minutos'];
                    $agenda[$data][] = $parte;
                }
                $livre -= $usar;
                $usado[$escolha] += $usar;
                $restanteTarefa[$escolha] -= $usar;
                $materiasHoje[$escolha] = true;
                if ($restanteTarefa[$escolha] <= 0) {
                    array_shift($filas[$escolha]);
                    if ($filas[$escolha]) {
                        $restanteTarefa[$escolha] = $filas[$escolha][0]['minutos'];
                    } else {
                        unset($filas[$escolha]);
                    }
                }
                if (count($materiasHoje) >= count($filas) + (int)!isset($filas[$escolha])) {
                    $materiasHoje = []; // todas as matérias já passaram hoje: pode repetir
                }
            }
            if (!$filas && $fim === null) {
                $fim = $data;
            }
            if ($fim !== null && $n > 45) {
                break;
            }
        }
        return ['agenda' => $agenda, 'fim' => $fim];
    }
}

if (!function_exists('meta_cronograma_montar')) {
    /**
     * Cronograma do aluno a partir de hoje + diagnóstico de ritmo.
     */
    function meta_cronograma_montar(PDO $pdo, int $usuarioId, int $cursoId, ?DateTimeImmutable $hoje = null): array
    {
        meta_cronograma_bootstrap($pdo);
        $hoje = $hoje ?? new DateTimeImmutable('today');
        $estado = meta_estado_aluno($pdo, $usuarioId, $cursoId);
        $cfg = meta_cronograma_config($pdo, $usuarioId, $cursoId);
        if (!$cfg || !$estado['plano']) {
            return ['estado' => $estado, 'config' => $cfg, 'agenda' => [], 'fim' => null, 'atraso_dias' => 0, 'folga_dias' => null];
        }

        // Tempo real de cada assunto (vídeo, PDF, questões) antes de distribuir.
        meta_tempo_bootstrap($pdo);
        $cfgTempo = meta_config($pdo);
        $fatores = meta_tempo_fatores_aprendidos($pdo);
        // Ranking Individual: matéria fraca ganha peso (mais tempo no ciclo); forte, manutenção.
        $desempenho = meta_desempenho_materias($pdo, $usuarioId);
        foreach ($estado['itens'] as $k => $it) {
            $estado['itens'][$k]['tempo'] = meta_tempo_item($pdo, $it, $cfgTempo, $fatores);
            $f = $desempenho[(int)($it['materia_id'] ?? 0)]['fator'] ?? 1.0;
            $estado['itens'][$k]['peso'] = max(0.1, (float)($it['peso'] ?? 1)) * $f;
        }

        $revisoes = meta_cronograma_revisoes($estado, meta_revisoes_feitas($pdo, $usuarioId));

        // Simulado e redação fixos na semana (0 minutos = desligado).
        require_once __DIR__ . '/schema-cache.php';
        if (!gab_schema_ok('meta_cron_padroes_eventos', __FILE__)) {
            $ins = $pdo->prepare('INSERT IGNORE INTO meta_config (chave, valor) VALUES (:c, :v)');
            foreach (['simulado_dia' => '6', 'simulado_min' => '120', 'simulado_semanas' => '1', 'redacao_dia' => '3', 'redacao_min' => '60', 'redacao_semanas' => '1'] as $c => $v) {
                $ins->execute([':c' => $c, ':v' => $v]);
            }
            meta_config($pdo, true);
        }
        $cfgEv = meta_config($pdo);
        $cfg['eventos'] = [];
        if (($cfgEv['simulado_min'] ?? 0) > 0) {
            $cfg['eventos'][] = ['tipo' => 'simulado', 'dia' => $cfgEv['simulado_dia'] ?? 6, 'minutos' => $cfgEv['simulado_min'], 'cada_semanas' => $cfgEv['simulado_semanas'] ?? 1, 'titulo' => 'Simulado no tempo de prova', 'rotulo' => 'Simulado'];
        }
        require_once __DIR__ . '/provas-escritas-lib.php';
        if (($cfgEv['redacao_min'] ?? 0) > 0 && pe_aluno_tem($pdo, $usuarioId, 'redacao')) { // concurso sem redação: não entra no cronograma
            $cfg['eventos'][] = ['tipo' => 'redacao', 'dia' => $cfgEv['redacao_dia'] ?? 3, 'minutos' => $cfgEv['redacao_min'], 'cada_semanas' => $cfgEv['redacao_semanas'] ?? 1, 'titulo' => 'Redação da semana', 'rotulo' => 'Redação'];
        }
        if (pe_aluno_tem($pdo, $usuarioId, 'discursiva')) {
            $cfg['eventos'][] = ['tipo' => 'discursiva', 'dia' => 5, 'minutos' => 45, 'cada_semanas' => 1, 'titulo' => 'Treino de discursiva', 'rotulo' => 'Discursiva'];
        }
        $fila = meta_cronograma_tarefas_pendentes($estado);
        $real = meta_cronograma_distribuir($fila, $cfg, $hoje, $revisoes);
        if (is_file(__DIR__ . '/edital-hack-lib.php')) {
            require_once __DIR__ . '/edital-hack-lib.php';
            $real['agenda'] = hack_no_cronograma($pdo, $usuarioId, $real['agenda']);
        }

        // Plano ideal: tudo desde a data de início, sem nada feito.
        $filaIdeal = [];
        foreach ($estado['itens'] as $item) {
            $min = meta_cronograma_minutos_item($item);
            $filaIdeal[] = ['tipo' => 'teoria', 'item' => $item, 'minutos' => $min['teoria']];
            $filaIdeal[] = ['tipo' => 'questoes', 'item' => $item, 'minutos' => $min['questoes']];
        }
        $ideal = meta_cronograma_distribuir($filaIdeal, $cfg, new DateTimeImmutable((string)$cfg['data_inicio']), []);

        $atraso = 0;
        if ($real['fim'] && $ideal['fim'] && $real['fim'] > $ideal['fim']) {
            $atraso = (int)(new DateTimeImmutable($ideal['fim']))->diff(new DateTimeImmutable($real['fim']))->days;
        }
        $folga = null;
        if (!empty($cfg['data_prova']) && $real['fim']) {
            $d = (new DateTimeImmutable($real['fim']))->diff(new DateTimeImmutable((string)$cfg['data_prova']));
            $folga = $d->invert ? -$d->days : $d->days;
        }

        return [
            'estado' => $estado,
            'config' => $cfg,
            'agenda' => $real['agenda'],
            'fim' => $real['fim'],
            'fim_ideal' => $ideal['fim'],
            'atraso_dias' => $atraso,
            'folga_dias' => $folga,
            'desempenho' => $desempenho,
        ];
    }
}
