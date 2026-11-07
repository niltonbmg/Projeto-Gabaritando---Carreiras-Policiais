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

if (!function_exists('meta_cronograma_bootstrap')) {
    function meta_cronograma_bootstrap(PDO $pdo): void
    {
        meta_bootstrap($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta_aluno_config (
            usuario_id BIGINT UNSIGNED NOT NULL,
            curso_id INT UNSIGNED NOT NULL,
            horas_dia DECIMAL(4,2) NOT NULL DEFAULT 3.00,
            dias_semana VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6',
            data_inicio DATE NOT NULL,
            data_prova DATE NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id, curso_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
        foreach ($stmt->fetchAll() as $r) {
            $feitas[(int)$r['plano_item_id'] . ':' . (int)$r['rodada']] = true;
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
            return ['teoria' => max(10, (int)$item['tempo']['teoria']), 'questoes' => max(15, (int)$item['tempo']['questoes'])];
        }
        $total = max(30, (int)round((float)$item['horas'] * 60));
        $teoria = (int)round($total * META_FRACAO_TEORIA);
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

        $agenda = [];
        $dia = $inicio;
        $i = 0;
        $restante = $fila ? $fila[0]['minutos'] : 0;
        $fim = null;
        for ($n = 0; $n < $limiteDias; $n++, $dia = $dia->modify('+1 day')) {
            $data = $dia->format('Y-m-d');
            if (!in_array((int)$dia->format('w'), $cfg['dias'], true)) {
                continue;
            }
            $livre = (int)($cfg['minutos'][(int)$dia->format('w')] ?? 0);
            foreach ($revisoes[$data] ?? [] as $rev) {
                $agenda[$data][] = $rev;
                $livre -= $rev['minutos'];
            }
            while ($i < count($fila) && $livre >= 15) {
                $usar = min($livre, $restante);
                $t = $fila[$i];
                $t['minutos'] = $usar;
                $t['parcial'] = $usar < $fila[$i]['minutos'];
                $agenda[$data][] = $t;
                $livre -= $usar;
                $restante -= $usar;
                if ($restante <= 0) {
                    $i++;
                    $restante = $fila[$i]['minutos'] ?? 0;
                }
            }
            if ($i >= count($fila) && $fim === null) {
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
        foreach ($estado['itens'] as $k => $it) {
            $estado['itens'][$k]['tempo'] = meta_tempo_item($pdo, $it, $cfgTempo, $fatores);
        }

        $revisoes = meta_cronograma_revisoes($estado, meta_revisoes_feitas($pdo, $usuarioId));
        $fila = meta_cronograma_tarefas_pendentes($estado);
        $real = meta_cronograma_distribuir($fila, $cfg, $hoje, $revisoes);

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
        ];
    }
}
