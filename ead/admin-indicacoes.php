<?php
// admin-indicacoes.php
// Cadastro das indicações por assunto ("Aprenda de outro jeito").
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/indicacoes-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;
$topicoId = admin_cursos_int($_GET['topico_id'] ?? $_POST['topico_id'] ?? 0);

try {
    $pdo = admin_cursos_db();
    indicacoes_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($topicoId <= 0) {
            throw new RuntimeException('Escolha um assunto.');
        }
        if ($acao === 'adicionar') {
            $tipo = (string)($_POST['tipo'] ?? '');
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            if (!isset(INDICACAO_TIPOS[$tipo]) || $titulo === '') {
                throw new RuntimeException('Informe o tipo e o título.');
            }
            $link = trim((string)($_POST['link'] ?? ''));
            if ($link !== '' && indicacao_link_seguro($link) === '') {
                throw new RuntimeException('O link precisa começar com http:// ou https://');
            }
            $ano = (int)($_POST['ano'] ?? 0);
            $pdo->prepare('INSERT INTO assunto_indicacoes (topico_id, tipo, titulo, ano, onde, link, por_que) VALUES (:t, :ti, :tt, :a, :o, :l, :p)')
                ->execute([
                    ':t' => $topicoId, ':ti' => $tipo, ':tt' => mb_substr($titulo, 0, 200),
                    ':a' => $ano > 1000 && $ano < 2100 ? $ano : null,
                    ':o' => mb_substr(trim((string)($_POST['onde'] ?? '')), 0, 200) ?: null,
                    ':l' => $link ?: null,
                    ':p' => mb_substr(trim((string)($_POST['por_que'] ?? '')), 0, 400) ?: null,
                ]);
            admin_cursos_redirect('admin-indicacoes.php?topico_id=' . $topicoId . '&sucesso=1');
        }
        if ($acao === 'remover') {
            $pdo->prepare('DELETE FROM assunto_indicacoes WHERE id = :id AND topico_id = :t')->execute([':id' => admin_cursos_int($_POST['id'] ?? 0), ':t' => $topicoId]);
            admin_cursos_redirect('admin-indicacoes.php?topico_id=' . $topicoId . '&sucesso=2');
        }
        if ($acao === 'aprovar') {
            $pdo->prepare("UPDATE assunto_indicacoes SET status = 'aprovada' WHERE id = :id AND topico_id = :t")->execute([':id' => admin_cursos_int($_POST['id'] ?? 0), ':t' => $topicoId]);
            admin_cursos_redirect('admin-indicacoes.php?topico_id=' . $topicoId . '&sucesso=3');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}
$sucesso = ['1' => 'Indicação adicionada. Já aparece para os alunos nas aulas deste assunto.', '2' => 'Indicação removida.', '3' => 'Indicação aprovada.'][(string)($_GET['sucesso'] ?? '')] ?? '';

$topicos = [];
$lista = [];
$contagem = [];
if ($pdo instanceof PDO) {
    $topicos = $pdo->query("SELECT t.id, t.titulo, m.nome AS materia FROM admin_materia_topicos t LEFT JOIN admin_materias m ON m.id = t.materia_id ORDER BY m.nome, t.ordem, t.titulo")->fetchAll();
    foreach ($pdo->query('SELECT topico_id, COUNT(*) n FROM assunto_indicacoes GROUP BY topico_id') as $r) {
        $contagem[(int)$r['topico_id']] = (int)$r['n'];
    }
    if ($topicoId <= 0 && $topicos) {
        $topicoId = (int)$topicos[0]['id'];
    }
    $lista = indicacoes_do_topico($pdo, $topicoId, false);
}

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indicações por assunto | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading">
                <h1>Aprenda de outro jeito</h1>
                <p>Filmes, séries, documentários, livros, podcasts e jogos por assunto. Aparecem para o aluno embaixo de cada aula do assunto.</p>
            </div>
            <form method="get" class="admin-page-actions">
                <label for="topico" class="admin-sr-only">Assunto</label>
                <select id="topico" name="topico_id" onchange="this.form.submit()" class="admin-select">
                    <?php foreach ($topicos as $t): ?>
                        <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $topicoId ? 'selected' : ''; ?>><?php echo admin_esc((string)$t['materia'] . ' › ' . (string)$t['titulo'] . ' (' . ($contagem[(int)$t['id']] ?? 0) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>

        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>Indicações deste assunto</h2><small><?php echo count($lista); ?> cadastrada(s)</small></div>
            <?php if (!$lista): ?>
                <div class="admin-empty"><div><i data-lucide="clapperboard"></i><h3>Nenhuma indicação ainda</h3><p>Adicione abaixo. Em breve a IA vai sugerir indicações para você só aprovar.</p></div></div>
            <?php else: ?>
                <div class="admin-table-wrap"><table class="admin-table">
                    <thead><tr><th>Tipo</th><th>Título</th><th>Por que assistir/ler</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lista as $i): $tp = INDICACAO_TIPOS[$i['tipo']] ?? ['nome' => $i['tipo'], 'icone' => '']; ?>
                        <tr>
                            <td><?php echo $tp['icone'] . ' ' . admin_esc($tp['nome']); ?></td>
                            <td><strong><?php echo admin_esc((string)$i['titulo']); ?></strong><br><span><?php echo admin_esc(trim(((string)($i['ano'] ?? '')) . ' ' . (string)($i['onde'] ?? ''))); ?></span></td>
                            <td><span><?php echo admin_esc((string)($i['por_que'] ?? '')); ?></span></td>
                            <td><?php echo $i['status'] === 'aprovada' ? 'Visível' : 'Sugerida pela IA'; ?></td>
                            <td><div class="admin-table-actions">
                                <?php if ($i['status'] !== 'aprovada'): ?><form method="post"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="topico_id" value="<?php echo $topicoId; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Aprovar</button></form><?php endif; ?>
                                <form method="post" onsubmit="return confirm('Remover esta indicação?');"><input type="hidden" name="acao" value="remover"><input type="hidden" name="topico_id" value="<?php echo $topicoId; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>"><button class="admin-btn admin-btn-danger admin-btn-small" type="submit" aria-label="Remover"><i data-lucide="trash-2"></i></button></form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>Adicionar indicação</h2></div>
            <form method="post" class="admin-form-grid">
                <input type="hidden" name="acao" value="adicionar"><input type="hidden" name="topico_id" value="<?php echo $topicoId; ?>">
                <div class="admin-form-field"><label for="tipo">Tipo</label><select id="tipo" name="tipo"><?php foreach (INDICACAO_TIPOS as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo $t['icone'] . ' ' . $t['nome']; ?></option><?php endforeach; ?></select></div>
                <div class="admin-form-field"><label for="titulo">Título</label><input id="titulo" name="titulo" type="text" required placeholder="Ex: Desmundo"></div>
                <div class="admin-form-field"><label for="ano">Ano</label><input id="ano" name="ano" type="number" min="1000" max="2100" placeholder="2003"></div>
                <div class="admin-form-field"><label for="onde">Onde encontrar</label><input id="onde" name="onde" type="text" placeholder="Ex: YouTube, Netflix, Globoplay"></div>
                <div class="admin-form-field full"><label for="link">Link (opcional)</label><input id="link" name="link" type="url" placeholder="https://"></div>
                <div class="admin-form-field full"><label for="porque">Por que indicar (1 frase, ligada à prova)</label><input id="porque" name="por_que" type="text" maxlength="400" placeholder="Mostra a vida num engenho de açúcar no Brasil Colônia: ajuda a fixar plantation e escravidão."></div>
                <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="plus"></i>Adicionar</button></div>
            </form>
        </section>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
