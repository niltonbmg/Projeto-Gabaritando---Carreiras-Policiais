<?php
// api/edital-preview.php
// Pré-visualização rápida para o mentor (abre numa janela por cima do cadastro):
//   ?tipo=aula&id=N        -> teoria/apostila da aula, situação da videoaula e do podcast
//   ?tipo=questoes&topico=N -> até 5 questões do assunto com gabarito, comentário e fonte
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/admin-auth.php';
if (!auth_admin_is_logged()) {
    http_response_code(401);
    exit('Faça login no painel.');
}
require_once __DIR__ . '/../includes/admin-cursos-lib.php';
require_once __DIR__ . '/../includes/edital-jobs-lib.php';
require_once __DIR__ . '/../includes/aula-descricao-lib.php';
header('Content-Type: text/html; charset=utf-8');
$pdo = admin_cursos_db();
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$tipo = (string)($_GET['tipo'] ?? '');

// Ações rápidas do mentor a partir da pré-visualização.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($acao === 'refazer_aula') {
        $st = $pdo->prepare('SELECT video_url, pdf_arquivo FROM admin_curso_aulas WHERE id = :id');
        $st->execute([':id' => $id]);
        $a = $st->fetch();
        if ($a) {
            try { $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :id')->execute([':id' => $id]); } catch (Throwable $x) {}
            try { $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :id')->execute([':id' => $id]); } catch (Throwable $x) {}
            $semMaterial = trim((string)$a['video_url']) === '' && trim((string)$a['pdf_arquivo']) === '';
            ia_fila_adicionar($pdo, $semMaterial ? 'aula_ia' : 'apostila', $id);
            exit('<p>🔁 Pedido feito: a IA vai refazer a teoria, a videoaula e o podcast desta aula (acompanhe na Central da IA).</p>');
        }
    }
    if ($acao === 'apagar_questao') {
        $pdo->prepare("UPDATE admin_banco_questoes SET status = 'inativo' WHERE id = :id")->execute([':id' => $id]);
        exit('ok');
    }
    exit('<p>Nada feito.</p>');
}

if ($tipo === 'aula') {
    $st = $pdo->prepare('SELECT a.*, c.nome AS curso FROM admin_curso_aulas a LEFT JOIN admin_cursos c ON c.id = a.curso_id WHERE a.id = :id');
    $st->execute([':id' => (int)($_GET['id'] ?? 0)]);
    $a = $st->fetch();
    if (!$a) {
        exit('<p>Aula não encontrada.</p>');
    }
    $va = $pdo->prepare('SELECT status, partes, partes_ok, roteiro FROM aula_videoaulas WHERE aula_id = :id');
    try { $va->execute([':id' => (int)$a['id']]); $va = $va->fetch(); } catch (Throwable $x) { $va = null; }
    $pod = $pdo->prepare('SELECT status, arquivo FROM aula_podcasts WHERE aula_id = :id');
    try { $pod->execute([':id' => (int)$a['id']]); $pod = $pod->fetch(); } catch (Throwable $x) { $pod = null; }
    $slides = $va && $va['roteiro'] ? (json_decode((string)$va['roteiro'], true)['slides'] ?? []) : [];
    echo '<h2>' . $e($a['titulo']) . '</h2><p class="pv-sub">' . $e($a['curso']) . '</p>';
    echo '<div class="pv-status">';
    echo '<span>' . (trim((string)$a['video_url']) !== '' ? '🎥 Vídeo do professor' : (trim((string)$a['pdf_arquivo']) !== '' ? '📄 PDF do professor' : '🤖 Aula do Antigão')) . '</span>';
    echo '<span>🎬 Videoaula: ' . ($va ? ($va['status'] === 'pronto' ? 'pronta (' . count($slides) . ' slides)' : 'gerando ' . (int)$va['partes_ok'] . '/' . (int)$va['partes']) : 'na fila') . '</span>';
    echo '<span>🎧 Podcast: ' . ($pod ? ($pod['status'] === 'pronto' ? 'pronto' : 'gerando') : 'na fila') . '</span></div>';
    echo '<p style="display:flex;flex-wrap:wrap;gap:8px"><button type="button" class="pv-bt pv-bt-l" data-pv-acao="refazer_aula" data-id="' . (int)$a['id'] . '">🔁 Pedir para a IA refazer esta aula</button></p>';
    echo '<p><a class="pv-bt" target="_blank" href="aulas.php?curso_id=' . (int)$a['curso_id'] . '&modulo_id=' . (int)$a['modulo_id'] . '&aula_id=' . (int)$a['id'] . '">▶ Abrir como o aluno vê (videoaula, podcast, PDF)</a></p>';
    if ($slides) {
        $audios = $va && $va['status'] === 'pronto' ? (json_decode((string)($pdo->query('SELECT audios FROM aula_videoaulas WHERE aula_id = ' . (int)$a['id'])->fetchColumn() ?: '[]'), true) ?: []) : [];
        echo '<h3>🎬 Videoaula do Antigão' . ($audios ? ' · ouça cada slide aqui mesmo' : '') . '</h3><ol class="pv-slides">';
        foreach ($slides as $i => $sl) {
            echo '<li><strong>' . $e(($sl['emoji'] ?? '') . ' ' . ($sl['titulo'] ?? '')) . '</strong> — ' . $e(implode(' · ', (array)($sl['topicos'] ?? [])));
            if (!empty($sl['destaque'])) {
                echo '<br><small>💡 ' . $e($sl['destaque']) . '</small>';
            }
            if (!empty($audios[$i])) {
                echo '<br><audio controls preload="none" style="width:100%;max-width:420px;margin-top:4px" src="' . $e($audios[$i]) . '"></audio>';
            }
            echo '</li>';
        }
        echo '</ol>';
    }
    $html = trim((string)$a['descricao']);
    echo '<h3>Teoria / apostila</h3>' . ($html !== '' ? '<div class="pv-texto">' . (function_exists('aula_desc_sanitizar_html') ? aula_desc_sanitizar_html($html) : strip_tags($html, '<p><h2><h3><h4><ul><ol><li><strong><em><table><tr><td><th><br>')) . '</div>' : '<p>Ainda sendo escrita pela IA.</p>');
    exit;
}

if ($tipo === 'questoes') {
    $st = $pdo->prepare("SELECT q.id, q.enunciado, q.explicacao, q.dificuldade, q.ano, b.nome AS banca FROM admin_banco_questoes q LEFT JOIN admin_bancas b ON b.id = q.banca_id
                          WHERE q.materia_topico_id = :t AND q.status = 'ativo' ORDER BY q.id DESC LIMIT 5");
    $st->execute([':t' => (int)($_GET['topico'] ?? 0)]);
    $qs = $st->fetchAll();
    $tot = $pdo->prepare("SELECT COUNT(*) FROM admin_banco_questoes WHERE materia_topico_id = :t AND status = 'ativo'");
    $tot->execute([':t' => (int)($_GET['topico'] ?? 0)]);
    echo '<h2>Questões do assunto</h2><p class="pv-sub">' . (int)$tot->fetchColumn() . ' no banco · mostrando as 5 mais recentes</p>';
    if (!$qs) {
        exit('<p>Ainda não há questões. A IA está pesquisando (veja a Central da IA).</p>');
    }
    $op = $pdo->prepare('SELECT letra, texto, correta FROM admin_banco_questao_opcoes WHERE questao_id = :q ORDER BY ordem, id');
    foreach ($qs as $n => $q) {
        $op->execute([':q' => (int)$q['id']]);
        $ehIa = str_contains((string)$q['explicacao'], 'Questão inédita do Antigão');
        echo '<article class="pv-q"><p class="pv-tag">' . ($ehIa ? '🤖 Criada pelo Antigão' : '📌 Real') . ' · ' . $e($q['dificuldade']) . ($q['banca'] ? ' · ' . $e($q['banca']) : '') . ($q['ano'] ? ' · ' . (int)$q['ano'] : '') . '</p>';
        echo '<p><strong>' . ($n + 1) . '.</strong> ' . nl2br($e($q['enunciado'])) . '</p><ul>';
        foreach ($op->fetchAll() as $o) {
            echo '<li class="' . ($o['correta'] ? 'pv-certa' : '') . '">' . $e($o['letra']) . ') ' . $e($o['texto']) . ($o['correta'] ? ' ✅' : '') . '</li>';
        }
        echo '</ul><p class="pv-com">💬 ' . $e($q['explicacao']) . '</p><p><button type="button" class="pv-bt pv-bt-l" data-pv-acao="apagar_questao" data-id="' . (int)$q['id'] . '">🗑 Apagar esta questão</button></p></article>';
    }
    exit;
}
echo '<p>Nada para mostrar.</p>';
