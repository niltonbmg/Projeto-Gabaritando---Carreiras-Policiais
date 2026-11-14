<?php
// includes/ia-fila-lib.php
// Fila de trabalho da IA. Tudo que é demorado (apostila, podcast) entra aqui e
// é feito pelo robô (ia-robo.php, chamado pelo cron da Hostinger a cada 5 min),
// sem depender de alguém ficar com a tela aberta.
//
// Ordem por aula: 1) apostila (descrição)  2) podcast (já com a apostila nova).
declare(strict_types=1);

require_once __DIR__ . '/apostila-lib.php';
require_once __DIR__ . '/videoaula-lib.php';

// Cursos para os quais a IA gera conteúdo (CFSD: 13 = Soldado PM, 12 = Soldado BM).
if (!defined('IA_FILA_CURSOS')) {
    define('IA_FILA_CURSOS', [13, 12]);
}

if (!function_exists('ia_fila_bootstrap')) {
    function ia_fila_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ia_fila (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(20) NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            status ENUM('pendente','feito','erro') NOT NULL DEFAULT 'pendente',
            tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
            erro VARCHAR(500) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ia_fila (tipo, aula_id),
            KEY idx_ia_fila_status (status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_descricao_historico (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            aula_id INT UNSIGNED NOT NULL,
            descricao MEDIUMTEXT NULL,
            origem VARCHAR(30) NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_hist_aula (aula_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $pdo->exec('ALTER TABLE ia_fila ADD COLUMN consumo VARCHAR(160) NULL'); // gasto real da última execução
        } catch (Throwable $e) {
        }
        try {
            $pdo->exec('ALTER TABLE ia_fila ADD COLUMN prioridade TINYINT NOT NULL DEFAULT 0'); // 1 = um aluno está na aula agora
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('ia_fila_adicionar')) {
    /** Coloca (ou recoloca) a aula na fila. */
    function ia_fila_adicionar(PDO $pdo, string $tipo, int $aulaId): void
    {
        ia_fila_bootstrap($pdo);
        $pdo->prepare("INSERT INTO ia_fila (tipo, aula_id, status, tentativas, erro) VALUES (:t, :a, 'pendente', 0, NULL)
                       ON DUPLICATE KEY UPDATE status = 'pendente', tentativas = 0, erro = NULL")
            ->execute([':t' => $tipo, ':a' => $aulaId]);
    }
}

if (!function_exists('ia_fila_todas_as_aulas')) {
    /** "Gerar para todas": põe na fila todas as aulas ainda não feitas pela IA. */
    function ia_fila_todas_as_aulas(PDO $pdo, bool $refazerTudo = false): int
    {
        ia_fila_bootstrap($pdo);
        $sql = $refazerTudo
            ? 'SELECT id FROM admin_curso_aulas'
            : "SELECT a.id FROM admin_curso_aulas a WHERE NOT EXISTS (SELECT 1 FROM ia_fila f WHERE f.aula_id = a.id AND f.tipo = 'apostila' AND f.status = 'feito')";
        $n = 0;
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id) {
            ia_fila_adicionar($pdo, 'apostila', (int)$id);
            $n++;
        }
        return $n;
    }
}

if (!function_exists('ia_fila_processar_um')) {
    /** Faz o próximo trabalho da fila. Retorna o que fez, ou null se a fila está vazia. */
    function ia_fila_processar_um(PDO $pdo): ?array
    {
        ia_fila_bootstrap($pdo);
        videoaula_bootstrap($pdo);
        $pdo = podcast_reconectar($pdo);
        // Apostilas antes de podcasts; 1 tentativa + 1 nova tentativa por item (depois fica como erro).
        // Limite diário do Google atingido? Pausa só o que usa voz (videoaula/podcast) e segue com o resto.
        if (ia_fila_pausa_texto()) {
            return null; // cota de texto do Google: espera uns minutos
        }
        $pausa = ia_fila_pausa();
        $pri = ia_prioridade();
        $semVoz = $pausa ? " AND tipo NOT IN ('videoaula','podcast')" : ($pri['podcasts_pausados'] ? " AND tipo <> 'podcast'" : '');
        if (ia_fila_pausa_busca()) {
            $semVoz .= " AND tipo <> 'radar'"; // sem pesquisa na internet: o Radar espera; as questões seguem criando inéditas
        }
        // Controle rígido: tipos desligados pelo mentor nunca rodam (nem quando uma tarefa encadeia outra).
        require_once __DIR__ . '/ia-consumo-lib.php';
        foreach (ia_controle_desligados() as $tDesl) {
            if ($tDesl !== 'classificar' && $tDesl !== 'edital') {
                $semVoz .= ' AND tipo <> ' . $pdo->quote($tDesl);
            }
        }
        // Só gera para os cursos CFSD (13 = PM, 12 = BM); cursos-repositório nunca. Tarefas sem aula (questões, redação, radar) seguem.
        $soCursos = " AND (f.tipo IN ('questoes','redacao','radar') OR a.curso_id IN (" . implode(',', IA_FILA_CURSOS) . '))';
        // Gasto do mês passou de 80% do limite (Consumo da IA): a fila para; só a redação do aluno continua.
        $cfgC = ia_consumo_config();
        if ((float)$cfgC['limite_mensal_brl'] > 0 && ia_consumo_mes_brl($pdo) >= 0.8 * (float)$cfgC['limite_mensal_brl']) {
            $soCursos .= " AND f.tipo = 'redacao'";
        }
        $ordem = $pri['primeiro'] === 'questoes' ? "'redacao','conferir_pdf','radar','visual','apostila','aula_ia','questoes','videoaula','podcast'" : "'redacao','conferir_pdf','radar','visual','apostila','aula_ia','videoaula','questoes','podcast'";
        // Dentro de cada tipo, segue a ordem do curso (módulo e aula): a cota grátis vai primeiro para o que o aluno vê primeiro.
        // Divisão justa: tarefa que já usou metade da cota do dia cede a vez (se não houver outra, segue).
        $justo = '';
        foreach (ia_controle_tipos_no_limite() as $tJ) {
            $justo .= ' AND tipo <> ' . $pdo->quote($tJ);
        }
        foreach ($justo !== '' ? [$justo, ''] : [''] as $extraJ) {
        $item = $pdo->query("SELECT f.* FROM ia_fila f
                               LEFT JOIN admin_curso_aulas a ON a.id = f.aula_id AND f.tipo NOT IN ('questoes','redacao','radar')
                               LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                               LEFT JOIN (SELECT curso_id, COUNT(*) n FROM usuario_cursos WHERE status = 'ativo' GROUP BY curso_id) dem ON dem.curso_id = a.curso_id
                              WHERE (f.status = 'pendente' OR (f.status = 'erro' AND f.tentativas < 2 AND f.atualizado_em < NOW() - INTERVAL 20 MINUTE))" . str_replace('tipo', 'f.tipo', $semVoz . $extraJ) . $soCursos . "
                              ORDER BY FIELD(f.tipo, $ordem), f.prioridade DESC, COALESCE(dem.n, 0) DESC, COALESCE(m.ordem, 9999), COALESCE(a.ordem, 9999), f.id ASC LIMIT 1")->fetch(); // curso com mais alunos primeiro
            if ($item) {
                break;
            }
        }
        // Voz e texto são cotas separadas do Google: alterna para não deixar a voz parada enquanto há fila de texto.
        $arqGrupo = dirname(__DIR__) . '/uploads/.ia-ultimo-grupo';
        $vozTipos = ['videoaula', 'podcast'];
        if ($item && !in_array($item['tipo'], $vozTipos, true) && !$pausa && @file_get_contents($arqGrupo) === 'texto') {
            $vozItem = $pdo->query("SELECT f.* FROM ia_fila f
                                   LEFT JOIN admin_curso_aulas a ON a.id = f.aula_id
                                   LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                                   LEFT JOIN (SELECT curso_id, COUNT(*) n FROM usuario_cursos WHERE status = 'ativo' GROUP BY curso_id) dem ON dem.curso_id = a.curso_id
                                  WHERE f.tipo IN ('videoaula','podcast') AND (f.status = 'pendente' OR (f.status = 'erro' AND f.tentativas < 2 AND f.atualizado_em < NOW() - INTERVAL 20 MINUTE))" . str_replace('tipo', 'f.tipo', $semVoz) . $soCursos . "
                                  ORDER BY FIELD(f.tipo, 'videoaula', 'podcast'), COALESCE(dem.n, 0) DESC, COALESCE(m.ordem, 9999), COALESCE(a.ordem, 9999), f.id LIMIT 1")->fetch();
            if ($vozItem) {
                $item = $vozItem;
            }
        }
        if ($item) {
            @file_put_contents($arqGrupo, in_array($item['tipo'], $vozTipos, true) ? 'voz' : 'texto');
        }
        if (!$item) {
            return null;
        }
        $id = (int)$item['id'];
        $aulaId = (int)$item['aula_id'];
        // Trava da revisão: não gera videoaula/apostila/podcast de aula com PDF não conferido.
        require_once __DIR__ . '/revisao-aulas-lib.php';
        if (rev_segurar_tarefa($pdo, $item)) {
            return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => false, 'erro' => 'Aguardando a revisão do PDF da aula.'];
        }
        $pdo->prepare('UPDATE ia_fila SET tentativas = tentativas + 1 WHERE id = :id')->execute([':id' => $id]);
        require_once __DIR__ . '/ia-consumo-lib.php';
        $exec = ia_consumo_inicio((string)$item['tipo'], $aulaId); // todo gasto desta tarefa fica marcado nela
        // "O que a IA está fazendo agora" (Central da IA), em português simples.
        try {
            if ($item['tipo'] === 'questoes') {
                $stN = $pdo->prepare('SELECT t.titulo FROM edital_itens e JOIN admin_materia_topicos t ON t.id = e.topico_id WHERE e.id = :id');
            } elseif ($item['tipo'] === 'radar') {
                $stN = $pdo->prepare('SELECT nome FROM concursos WHERE id = :id');
            } else {
                $stN = $pdo->prepare('SELECT titulo FROM admin_curso_aulas WHERE id = :id');
            }
            $stN->execute([':id' => $aulaId]);
            $nomeN = (string)$stN->fetchColumn();
            $verbo = ['questoes' => 'Buscando/criando questões de', 'aula_ia' => 'Escrevendo a teoria da aula', 'apostila' => 'Montando a apostila da aula', 'videoaula' => 'Gravando a videoaula', 'podcast' => 'Gravando o podcast', 'redacao' => 'Lendo uma redação', 'conferir_pdf' => 'Conferindo se o PDF é da aula', 'radar' => 'Radar: buscando novidades do concurso', 'visual' => 'Dando o visual novo à videoaula'][$item['tipo']] ?? 'Trabalhando em';
            @file_put_contents(dirname(__DIR__) . '/uploads/.ia-agora.json', json_encode(['texto' => $verbo . ($nomeN !== '' ? ': ' . $nomeN : ''), 'em' => time()], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
        }
        try {
            if ($item['tipo'] === 'apostila') {
                $stmt = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
                $stmt->execute([':id' => $aulaId]);
                $aula = $stmt->fetch();
                if (!$aula) {
                    throw new RuntimeException('Aula não existe mais.');
                }
                require_once __DIR__ . '/reaproveitar-lib.php';
                $html = ia_reaproveitar_texto($pdo, $aulaId, 'apostila') ? null : apostila_gerar($pdo, (int)$aula['curso_id'], (int)$aula['modulo_id'], [
                    'titulo' => $aula['titulo'],
                    'descricao_atual' => $aula['descricao'],
                    'video_url' => $aula['video_url'],
                    'pdf_arquivo' => $aula['pdf_arquivo'],
                    'transcricao' => ia_transcricao_da_aula($pdo, $aulaId),
                    'timeout' => 170,
                ]);
                $pdo = podcast_reconectar($pdo);
                if ($html !== null) { // null = texto copiado de outra aula com o mesmo material
                    // Guarda a versão anterior antes de trocar.
                    $pdo->prepare('INSERT INTO aula_descricao_historico (aula_id, descricao, origem) VALUES (:a, :d, :o)')->execute([':a' => $aulaId, ':d' => $aula['descricao'], ':o' => 'antes_da_ia']);
                    require_once __DIR__ . '/texto-limpo-lib.php';
                    $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d WHERE id = :id')->execute([':d' => gab_sem_latex($html), ':id' => $aulaId]);
                }
                // Podcast antigo fica desatualizado: refaz com a apostila nova.
                $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :a')->execute([':a' => $aulaId]);
                ia_fila_adicionar($pdo, 'podcast', $aulaId);
                // Aula sem vídeo: a IA também monta a Videoaula IA (slides + narração + paradas).
                if (trim((string)$aula['video_url']) === '') {
                    $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $aulaId]);
                    ia_fila_adicionar($pdo, 'videoaula', $aulaId);
                }
            } elseif ($item['tipo'] === 'visual') {
                videoaula_visual_enriquecer($pdo, $aulaId); // visual novo sem regravar o áudio
            } elseif ($item['tipo'] === 'radar') {
                // aqui aula_id guarda o id do concurso
                require_once __DIR__ . '/radar-lib.php';
                radar_pesquisar($pdo, $aulaId);
            } elseif ($item['tipo'] === 'conferir_pdf') {
                rev_conferir($pdo, $aulaId);
            } elseif ($item['tipo'] === 'redacao') {
                // aqui aula_id guarda o id da redação: leitura do Antigão (escondida até o corretor dar a nota)
                require_once __DIR__ . '/redacao-lib.php';
                redacao_ia_ler($pdo, $aulaId);
            } elseif ($item['tipo'] === 'aula_ia') {
                require_once __DIR__ . '/edital-hack-lib.php';
                hack_aula_ia_processar($pdo, $aulaId);
            } elseif ($item['tipo'] === 'questoes') {
                // aqui aula_id guarda o id do assunto do edital (edital_itens.id)
                require_once __DIR__ . '/edital-hack-lib.php';
                hack_questoes_do_item($pdo, $aulaId);
            } elseif ($item['tipo'] === 'videoaula') {
                $r = videoaula_iniciar($pdo, $aulaId);
                for ($n = (int)($r['partes_ok'] ?? 0); ($r['status'] ?? '') !== 'pronto' && $n < (int)($r['partes'] ?? 0); $n++) {
                    $r = videoaula_parte($pdo, $aulaId, $n);
                    $pdo = podcast_reconectar($pdo);
                }
            } elseif ($item['tipo'] === 'podcast') {
                $r = podcast_iniciar($pdo, $aulaId);
                if (($r['status'] ?? '') !== 'pronto') {
                    $total = (int)($r['partes'] ?? 0);
                    for ($n = (int)($r['partes_ok'] ?? 0); $n < $total; $n++) {
                        $r = podcast_parte($pdo, $aulaId, $n);
                        $pdo = podcast_reconectar($pdo);
                    }
                }
            }
            $gasto = ia_consumo_da_execucao($pdo, $exec)['resumo'];
            if (!empty($GLOBALS['gab_reaproveitado']) && ia_consumo_da_execucao($pdo, $exec)['chamadas'] === 0) {
                $gasto = 'reaproveitada da aula ' . (int)$GLOBALS['gab_reaproveitado']['de'] . ' (mesmo material) · R$ 0';
            }
            unset($GLOBALS['gab_reaproveitado']);
            $pdo->prepare("UPDATE ia_fila SET status = 'feito', erro = NULL, consumo = :c WHERE id = :id")->execute([':c' => $gasto, ':id' => $id]);
            return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => true, 'consumo' => $gasto];
        } catch (Throwable $e) {
            if (gemini_cota_pausar($e, in_array($item['tipo'], ['videoaula', 'podcast'], true) ? 'voz' : 'texto')) {
                // Não é erro da tarefa: é a cota do Google. Volta para a fila sem gastar tentativa; a pausa certa já foi gravada.
                podcast_reconectar($pdo)->prepare("UPDATE ia_fila SET status = 'pendente', tentativas = GREATEST(tentativas - 1, 0), erro = NULL WHERE id = :id")->execute([':id' => $id]);
                return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => false, 'erro' => 'Limite do Google atingido: pausado e retoma sozinho.'];
            }
            podcast_reconectar($pdo)->prepare("UPDATE ia_fila SET status = 'erro', erro = :e WHERE id = :id")->execute([':e' => mb_substr($e->getMessage(), 0, 500), ':id' => $id]);
            return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => false, 'erro' => $e->getMessage()];
        }
    }
}

if (!function_exists('ia_fila_ler_pausa')) {
    /** Lê uma pausa de cota. No plano pago só vale pausa curta (429 de agora, até 2 min); pausa longa antiga é ignorada. */
    function ia_fila_ler_pausa(string $arq): int
    {
        require_once __DIR__ . '/ia-consumo-lib.php';
        ia_conferir_chave(); // chave nova no .env: limpa pausas e limites da chave antiga
        $ate = (int)@file_get_contents(dirname(__DIR__) . '/uploads/' . $arq);
        if ($ate > time() + 120 && ia_plano_pago()) {
            return 0;
        }
        return $ate;
    }
}

if (!function_exists('ia_fila_pausa')) {
    /** Até quando a voz (TTS) está pausada por limite diário do Google (0 = não está). */
    function ia_fila_pausa(): int
    {
        $ate = ia_fila_ler_pausa('.ia-pausa-voz');
        return $ate > time() ? $ate : 0;
    }

    function ia_fila_pausa_busca(): int
    {
        $ate = ia_fila_ler_pausa('.ia-pausa-busca');
        return $ate > time() ? $ate : 0;
    }

    function ia_fila_pausa_texto(): int
    {
        $ate = ia_fila_ler_pausa('.ia-pausa-texto');
        return $ate > time() ? $ate : 0;
    }
}

if (!function_exists('ia_transcricao_da_aula')) {
    function ia_transcricao_da_aula(PDO $pdo, int $aulaId): string
    {
        try {
            $stmt = $pdo->prepare('SELECT transcricao FROM aula_transcricoes WHERE aula_id = :a');
            $stmt->execute([':a' => $aulaId]);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('ia_fila_resumo')) {
    function ia_fila_resumo(PDO $pdo): array
    {
        ia_fila_bootstrap($pdo);
        $r = ['pendente' => 0, 'feito' => 0, 'erro' => 0];
        foreach ($pdo->query('SELECT status, COUNT(*) n FROM ia_fila GROUP BY status') as $l) {
            $r[(string)$l['status']] = (int)$l['n'];
        }
        $r['ultimos_erros'] = $pdo->query("SELECT f.tipo, f.aula_id, a.titulo, f.erro FROM ia_fila f LEFT JOIN admin_curso_aulas a ON a.id = f.aula_id WHERE f.status = 'erro' ORDER BY f.atualizado_em DESC LIMIT 5")->fetchAll();
        return $r;
    }
}
