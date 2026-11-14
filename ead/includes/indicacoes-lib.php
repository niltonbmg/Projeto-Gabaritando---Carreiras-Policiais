<?php
// includes/indicacoes-lib.php
// "Aprenda de outro jeito": indicações por assunto (livros, filmes,
// documentários, séries, jogos, podcasts, vídeos). A ideia é repetir o
// conteúdo por fontes diferentes, não ler a mesma coisa várias vezes.
declare(strict_types=1);

const INDICACAO_TIPOS = [
    'filme' => ['nome' => 'Filme', 'icone' => '🎬'],
    'serie' => ['nome' => 'Série', 'icone' => '📺'],
    'documentario' => ['nome' => 'Documentário', 'icone' => '🎥'],
    'livro' => ['nome' => 'Livro', 'icone' => '📚'],
    'podcast' => ['nome' => 'Podcast', 'icone' => '🎧'],
    'jogo' => ['nome' => 'Jogo', 'icone' => '🎮'],
    'video' => ['nome' => 'Vídeo', 'icone' => '▶️'],
    'site' => ['nome' => 'Site/Museu virtual', 'icone' => '🌐'],
];

if (!function_exists('indicacoes_bootstrap')) {
    function indicacoes_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS assunto_indicacoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            topico_id INT UNSIGNED NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            ano SMALLINT UNSIGNED NULL,
            onde VARCHAR(200) NULL,
            link VARCHAR(500) NULL,
            por_que VARCHAR(400) NULL,
            status ENUM('sugerida','aprovada') NOT NULL DEFAULT 'aprovada',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_indicacoes_topico (topico_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('indicacoes_do_topico')) {
    function indicacoes_do_topico(PDO $pdo, int $topicoId, bool $soAprovadas = true): array
    {
        if ($topicoId <= 0) {
            return [];
        }
        indicacoes_bootstrap($pdo);
        $sql = 'SELECT * FROM assunto_indicacoes WHERE topico_id = :t' . ($soAprovadas ? " AND status = 'aprovada'" : '') . ' ORDER BY ordem, FIELD(tipo, "filme","serie","documentario","livro","podcast","jogo","video","site"), id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':t' => $topicoId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('indicacao_link_seguro')) {
    function indicacao_link_seguro(string $url): string
    {
        $url = trim($url);
        return preg_match('~^https?://~i', $url) ? $url : '';
    }
}

if (!function_exists('indicacoes_html')) {
    /** Bloco "Aprenda de outro jeito" para a área do aluno. */
    function indicacoes_html(array $lista): string
    {
        if (!$lista) {
            return '';
        }
        $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $html = '<section class="ind" aria-label="Aprenda de outro jeito"><h2>Aprenda de outro jeito</h2>'
            . '<p class="ind-sub">Rever o assunto por outra fonte fixa mais do que reler a mesma apostila.</p><div class="ind-grade">';
        foreach ($lista as $i) {
            $tipo = INDICACAO_TIPOS[$i['tipo']] ?? ['nome' => 'Indicação', 'icone' => '⭐'];
            $link = indicacao_link_seguro((string)($i['link'] ?? ''));
            $meta = trim($tipo['nome'] . (!empty($i['ano']) ? ' · ' . (int)$i['ano'] : '') . (!empty($i['onde']) ? ' · ' . $i['onde'] : ''));
            $html .= '<article class="ind-card"><div class="ind-ico" aria-hidden="true">' . $tipo['icone'] . '</div><div class="ind-txt">'
                . '<small>' . $e($meta) . '</small><strong>' . $e((string)$i['titulo']) . '</strong>'
                . (!empty($i['por_que']) ? '<p>' . $e((string)$i['por_que']) . '</p>' : '')
                . ($link !== '' ? '<a href="' . $e($link) . '" target="_blank" rel="noopener">Abrir</a>' : '')
                . '</div></article>';
        }
        return $html . '</div></section>';
    }
}

if (!function_exists('indicacoes_css')) {
    function indicacoes_css(): string
    {
        return '.ind{display:grid;gap:10px;margin:8px 0 28px}.ind h2{margin:0}.ind-sub{margin:0;font-size:13px;opacity:.7}'
            . '.ind-grade{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}'
            . '.ind-card{display:flex;gap:12px;padding:12px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(255,255,255,.03);min-width:0}'
            . '.ind-ico{font-size:26px;line-height:1}.ind-txt{display:grid;gap:4px;min-width:0}'
            . '.ind-txt small{font-size:12px;opacity:.7;text-transform:uppercase;letter-spacing:.04em}'
            . '.ind-txt strong{font-size:15px;overflow-wrap:anywhere}.ind-txt p{margin:0;font-size:13px;opacity:.85}'
            . '.ind-txt a{justify-self:start;font-size:13px;font-weight:700;color:#fbb928}';
    }
}
