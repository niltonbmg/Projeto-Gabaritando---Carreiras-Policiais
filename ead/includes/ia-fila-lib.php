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

if (!function_exists('ia_fila_bootstrap')) {
    function ia_fila_bootstrap(PDO $pdo): void
    {
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
        // Apostilas antes de podcasts; até 3 tentativas por item.
        $item = $pdo->query("SELECT * FROM ia_fila WHERE status = 'pendente' OR (status = 'erro' AND tentativas < 3 AND atualizado_em < NOW() - INTERVAL 20 MINUTE)
                              ORDER BY (tipo IN ('apostila','aula_ia')) DESC, id ASC LIMIT 1")->fetch();
        if (!$item) {
            return null;
        }
        $id = (int)$item['id'];
        $aulaId = (int)$item['aula_id'];
        $pdo->prepare('UPDATE ia_fila SET tentativas = tentativas + 1 WHERE id = :id')->execute([':id' => $id]);
        try {
            if ($item['tipo'] === 'apostila') {
                $stmt = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
                $stmt->execute([':id' => $aulaId]);
                $aula = $stmt->fetch();
                if (!$aula) {
                    throw new RuntimeException('Aula não existe mais.');
                }
                $html = apostila_gerar($pdo, (int)$aula['curso_id'], (int)$aula['modulo_id'], [
                    'titulo' => $aula['titulo'],
                    'descricao_atual' => $aula['descricao'],
                    'video_url' => $aula['video_url'],
                    'pdf_arquivo' => $aula['pdf_arquivo'],
                    'transcricao' => ia_transcricao_da_aula($pdo, $aulaId),
                    'timeout' => 170,
                ]);
                $pdo = podcast_reconectar($pdo);
                // Guarda a versão anterior antes de trocar.
                $pdo->prepare('INSERT INTO aula_descricao_historico (aula_id, descricao, origem) VALUES (:a, :d, :o)')->execute([':a' => $aulaId, ':d' => $aula['descricao'], ':o' => 'antes_da_ia']);
                $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d WHERE id = :id')->execute([':d' => $html, ':id' => $aulaId]);
                // Podcast antigo fica desatualizado: refaz com a apostila nova.
                $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :a')->execute([':a' => $aulaId]);
                ia_fila_adicionar($pdo, 'podcast', $aulaId);
                // Aula sem vídeo: a IA também monta a Videoaula IA (slides + narração + paradas).
                if (trim((string)$aula['video_url']) === '') {
                    $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $aulaId]);
                    ia_fila_adicionar($pdo, 'videoaula', $aulaId);
                }
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
            $pdo->prepare("UPDATE ia_fila SET status = 'feito', erro = NULL WHERE id = :id")->execute([':id' => $id]);
            return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => true];
        } catch (Throwable $e) {
            podcast_reconectar($pdo)->prepare("UPDATE ia_fila SET status = 'erro', erro = :e WHERE id = :id")->execute([':e' => mb_substr($e->getMessage(), 0, 500), ':id' => $id]);
            return ['tipo' => $item['tipo'], 'aula_id' => $aulaId, 'ok' => false, 'erro' => $e->getMessage()];
        }
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
