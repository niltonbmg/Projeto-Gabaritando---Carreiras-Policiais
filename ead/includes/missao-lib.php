<?php
// includes/missao-lib.php
// "Ciclo no automático": decide o PRÓXIMO PASSO do aluno no Método META
// e monta a Missão do dia. Usado pela barra "Continuar minha missão" (todas as
// páginas do aluno), por "Hoje você estuda", pelo banco de questões e pelos
// lembretes do Antigão no sininho.
//
// Prioridade do próximo passo:
//   1) revisão vencida (1, 7 ou 30 dias)  2) teoria do assunto da vez
//   3) questões até liberar (mínimo 5)     4) tudo liberado: simulado / desafio do dia
declare(strict_types=1);

require_once __DIR__ . '/meta-lib.php';
require_once __DIR__ . '/meta-cronograma-lib.php';

const MISSAO_ICONES = ['teoria' => '📘', 'questoes' => '✅', 'revisao' => '🔁', 'simulado' => '📝', 'redacao' => '✍️'];
const MISSAO_PASSOS = [1 => 'Teoria', 2 => 'Questões', 3 => 'Revisão', 4 => 'Simulado'];

if (!function_exists('missao_link_aula')) {
    function missao_link_aula(array $item): string
    {
        return !empty($item['aula_id'])
            ? 'aulas.php?curso_id=' . (int)$item['aula_curso_id'] . '&modulo_id=' . (int)$item['aula_modulo_id'] . '&aula_id=' . (int)$item['aula_id']
            : '';
    }

    /**
     * Próximo passo do aluno. null = sem plano ainda.
     * Cache curto na sessão para não pesar em cada página.
     */
    function missao_proximo_passo(PDO $pdo, int $usuarioId, bool $semCache = false): ?array
    {
        $chave = 'gab_missao_' . $usuarioId;
        if (!$semCache && isset($_SESSION[$chave]) && ($_SESSION[$chave]['em'] ?? 0) > time() - 45) {
            return $_SESSION[$chave]['dados'];
        }
        $dados = null;
        try {
            meta_bootstrap($pdo);
            meta_cronograma_bootstrap($pdo);
            $cursoId = meta_curso_do_aluno($pdo, $usuarioId, 0);
            $estado = $cursoId > 0 ? meta_estado_aluno($pdo, $usuarioId, $cursoId) : null;
            if ($estado && $estado['plano']) {
                $dados = missao_calcular($pdo, $usuarioId, $cursoId, $estado);
            }
        } catch (Throwable $e) {
            $dados = null;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$chave] = ['em' => time(), 'dados' => $dados];
        }
        return $dados;
    }

    function missao_limpar_cache(int $usuarioId): void
    {
        unset($_SESSION['gab_missao_' . $usuarioId]);
    }

    function missao_calcular(PDO $pdo, int $usuarioId, int $cursoId, array $estado): array
    {
        $cfg = $estado['config'];
        $minQ = max(1, (int)($cfg['min_questoes'] ?? 5));
        $hoje = date('Y-m-d');

        // 1) Revisão vencida
        $revisao = null;
        foreach (meta_cronograma_revisoes($estado, meta_revisoes_feitas($pdo, $usuarioId)) as $data => $lista) {
            if ($data <= $hoje && $lista) {
                $revisao = $lista[0];
                break;
            }
        }
        $atual = $estado['atual'];
        $base = ['curso_id' => $cursoId, 'min' => $minQ, 'meta' => (int)($cfg['nivel4_questoes'] ?? 50),
            'concluidos' => (int)($estado['concluidos'] ?? 0), 'total' => count($estado['itens'])];

        if ($revisao) {
            $it = $revisao['item'];
            return $base + ['passo' => 3, 'tipo' => 'revisao', 'item_id' => (int)$it['id'], 'rodada' => (int)$revisao['rodada'],
                'assunto' => (string)$it['titulo'], 'materia' => (string)($it['materia_nome'] ?? ''),
                'acao' => 'Fazer revisão ' . (int)$revisao['rodada'] . 'ª', 'url' => 'banco-questoes.php?topico_id=' . (int)$it['topico_id'] . '&revisao=' . (int)$it['id'] . '-' . (int)$revisao['rodada'],
                'frase' => 'Revisão de ' . $it['titulo'] . ': 10 minutos para não esquecer.', 'faltam' => 0, 'feitas' => (int)$it['nivel_info']['resolvidas']];
        }
        if ($atual) {
            $teoriaOk = !empty($atual['teoria_ok']);
            $feitas = (int)$atual['nivel_info']['resolvidas'];
            if (!$teoriaOk) {
                return $base + ['passo' => 1, 'tipo' => 'teoria', 'item_id' => (int)$atual['id'],
                    'assunto' => (string)$atual['titulo'], 'materia' => (string)($atual['materia_nome'] ?? ''),
                    'acao' => 'Assistir à aula', 'url' => missao_link_aula($atual) ?: 'meu-plano.php',
                    'frase' => 'Comece pela teoria mínima de ' . $atual['titulo'] . '.', 'faltam' => $minQ, 'feitas' => $feitas];
            }
            return $base + ['passo' => 2, 'tipo' => 'questoes', 'item_id' => (int)$atual['id'],
                'assunto' => (string)$atual['titulo'], 'materia' => (string)($atual['materia_nome'] ?? ''),
                'acao' => 'Resolver questões', 'url' => 'banco-questoes.php?topico_id=' . (int)$atual['topico_id'],
                'frase' => 'Faltam ' . max(0, $minQ - $feitas) . ' questões para liberar o próximo assunto.', 'faltam' => max(0, $minQ - $feitas), 'feitas' => $feitas];
        }
        return $base + ['passo' => 4, 'tipo' => 'simulado', 'item_id' => 0, 'assunto' => 'Plano liberado', 'materia' => '',
            'acao' => 'Fazer o Desafio do dia', 'url' => 'desafio.php',
            'frase' => 'Todos os assuntos liberados: agora é subir o nível até 50 questões em cada um.', 'faltam' => 0, 'feitas' => 0];
    }

    /** Missão do dia: as tarefas de hoje do cronograma, na ordem, com o que já foi feito. */
    function missao_do_dia(PDO $pdo, int $usuarioId, int $cursoId): array
    {
        $cron = meta_cronograma_montar($pdo, $usuarioId, $cursoId);
        // Partes da mesma tarefa no mesmo dia viram uma linha só (minutos somados).
        $tarefas = [];
        foreach ($cron['agenda'][date('Y-m-d')] ?? [] as $t) {
            $k = $t['tipo'] . ':' . (int)($t['item']['id'] ?? 0) . ':' . (int)($t['rodada'] ?? 0);
            if (isset($tarefas[$k])) {
                $tarefas[$k]['minutos'] += (int)$t['minutos'];
            } else {
                $tarefas[$k] = $t;
            }
        }
        $tarefas = array_values($tarefas);
        $proximo = missao_proximo_passo($pdo, $usuarioId, true);
        return ['tarefas' => $tarefas, 'config' => $cron['config'], 'estado' => $cron['estado'], 'proximo' => $proximo];
    }

    /** As 4 bolinhas do ciclo para o assunto da vez. */
    function missao_bolinhas(int $passo): string
    {
        $h = '<span class="msb-bolas" aria-label="Passo ' . $passo . ' de 4 do ciclo">';
        foreach (MISSAO_PASSOS as $n => $nome) {
            $c = $n < $passo ? 'feito' : ($n === $passo ? 'agora' : '');
            $h .= '<i class="' . $c . '" title="' . $n . '. ' . $nome . '"></i>';
        }
        return $h . '</span>';
    }

    /** Já viu a apresentação do método (Antigão, primeiro acesso)? */
    function missao_viu_tour(PDO $pdo, int $usuarioId): bool
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS meta_aluno_tour (usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $st = $pdo->prepare('SELECT 1 FROM meta_aluno_tour WHERE usuario_id = :u');
        $st->execute([':u' => $usuarioId]);
        return (bool)$st->fetchColumn();
    }

    function missao_marcar_tour(PDO $pdo, int $usuarioId): void
    {
        missao_viu_tour($pdo, $usuarioId);
        $pdo->prepare('INSERT IGNORE INTO meta_aluno_tour (usuario_id) VALUES (:u)')->execute([':u' => $usuarioId]);
    }

    /** Lembrete do Antigão para o sininho (calculado, não precisa de robô). */
    function missao_lembrete(?array $m): ?array
    {
        if (!$m) {
            return null;
        }
        $titulo = 'Antigão: ' . ($m['passo'] === 2 && $m['faltam'] > 0 ? 'faltam ' . $m['faltam'] . ' questões para liberar o próximo assunto'
            : ($m['passo'] === 1 ? 'sua próxima aula é ' . $m['assunto'] : ($m['passo'] === 3 ? 'hoje tem revisão de ' . $m['assunto'] : 'Desafio do dia te esperando')));
        return ['tipo' => 'missao', 'referencia_id' => (int)crc32(date('Y-m-d') . $titulo) & 0x7fffffff, 'titulo' => $titulo,
            'descricao' => 'Toque para continuar sua missão.', 'link' => $m['url'], 'icone' => 'target', 'lida' => false, 'tempo' => 'hoje', 'data' => date('Y-m-d H:i:s')];
    }
}
