<?php
// admin-pdfs.php — Biblioteca de PDFs: todos os PDFs das aulas numa lista só (curso, aula, conferência)
// e os problemas: aula com nome de PDF sem arquivo, arquivo que sumiu do servidor, PDF suspeito e arquivo sem aula.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/revisao-aulas-lib.php';

$pdo = admin_cursos_db();
rev_bootstrap($pdo);
$e = static fn($s): string => admin_esc((string)$s);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'limpar_nome') {
    // aula com nome de PDF mas sem arquivo: tira o nome (o aluno deixa de ver um PDF que não abre)
    $st = $pdo->prepare("UPDATE admin_curso_aulas SET pdf_nome = '' WHERE id = :id AND COALESCE(TRIM(pdf_arquivo),'') = ''");
    $st->execute([':id' => (int)($_POST['aula_id'] ?? 0)]);
    $msg = $st->rowCount() ? 'Nome sem arquivo removido da aula.' : 'Nada a mudar.';
}

$aulas = $pdo->query("SELECT a.id, a.curso_id, a.modulo_id, a.titulo, a.pdf_arquivo, a.pdf_nome, c.nome curso, m.titulo modulo, r.status rev
                        FROM admin_curso_aulas a
                        LEFT JOIN admin_cursos c ON c.id = a.curso_id
                        LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                        LEFT JOIN aula_revisao r ON r.aula_id = a.id
                       WHERE COALESCE(TRIM(a.pdf_arquivo),'') <> '' OR COALESCE(TRIM(a.pdf_nome),'') <> ''
                       ORDER BY c.nome, m.ordem, a.ordem, a.id")->fetchAll(PDO::FETCH_ASSOC);

$porArquivo = [];
$semArquivo = [];
foreach ($aulas as $a) {
    $arq = trim((string)$a['pdf_arquivo']);
    if ($arq === '') {
        $semArquivo[] = $a;
        continue;
    }
    $porArquivo[$arq][] = $a;
}
$dir = __DIR__ . '/uploads/cursos/pdf';
$noDisco = is_dir($dir) ? array_values(array_filter(scandir($dir) ?: [], static fn($f) => preg_match('~\.pdf$~i', $f))) : [];
$usados = [];
foreach (array_keys($porArquivo) as $arq) {
    $usados[basename($arq)] = true;
}
$orfaos = array_values(array_filter($noDisco, static fn($f) => !isset($usados[$f])));

$linhas = [];
foreach ($porArquivo as $arq => $lista) {
    $existe = is_file(__DIR__ . '/' . ltrim($arq, '/'));
    $revs = array_unique(array_map(static fn($a) => (string)($a['rev'] ?: 'pendente'), $lista));
    $prob = [];
    if (!$existe) { $prob[] = 'arquivo não está no servidor'; }
    if (in_array('suspeita', $revs, true)) { $prob[] = 'PDF suspeito (não bate com a aula)'; }
    $linhas[] = ['arquivo' => $arq, 'nome' => (string)($lista[0]['pdf_nome'] ?: basename($arq)), 'aulas' => $lista, 'existe' => $existe,
        'tamanho' => $existe ? (int)filesize(__DIR__ . '/' . ltrim($arq, '/')) : 0, 'revs' => $revs, 'problemas' => $prob];
}

$filtro = (string)($_GET['f'] ?? 'todos');
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$casa = static function (array $l) use ($q): bool {
    if ($q === '') { return true; }
    $t = mb_strtolower($l['nome'] . ' ' . implode(' ', array_map(static fn($a) => $a['titulo'] . ' ' . $a['curso'], $l['aulas'])));
    return str_contains($t, $q);
};
$visiveis = array_values(array_filter($linhas, static fn($l) => $casa($l) && ($filtro !== 'problemas' || $l['problemas'])));
$nProb = count(array_filter($linhas, static fn($l) => $l['problemas'])) + count($semArquivo);
$selo = ['confere' => ['✅ confere', '#067647'], 'ok_manual' => ['✅ conferido por você', '#067647'], 'suspeita' => ['⚠️ suspeito', '#b42318'], 'pendente' => ['⏳ não conferido', '#6b7280']];
$linkAula = static fn(array $a): string => 'aulas.php?curso_id=' . (int)$a['curso_id'] . '&modulo_id=' . (int)$a['modulo_id'] . '&aula_id=' . (int)$a['id'];
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
$mb = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca de PDFs | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .bp-res{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.bp-res a{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;text-decoration:none;color:#111}
        .bp-res a.on{border-color:#fbb928;background:#fff8e6}.bp-res strong{font-size:22px;display:block}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-top:1px solid #f3f4f6;font-size:13px;vertical-align:top}
        .bp-prob{color:#b42318;font-size:12px}.bp-aula{display:block;margin:2px 0}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>📚 Biblioteca de PDFs</h1><p>Todos os PDFs das aulas numa lista só. Os arquivos ficam no servidor em <code>ead/uploads/cursos/pdf/</code>, cada um ligado à sua aula. Para trocar o PDF de uma aula, use a Revisão de aulas.</p></div>
        </section>
        <?php if ($msg !== ''): ?><div class="admin-alert admin-alert-success"><?php echo $e($msg); ?></div><?php endif; ?>
        <section class="admin-panel">
            <div class="bp-res">
                <a href="admin-pdfs.php" class="<?php echo $filtro === 'todos' ? 'on' : ''; ?>"><strong><?php echo count($linhas); ?></strong>PDFs em uso</a>
                <a href="admin-pdfs.php?f=problemas" class="<?php echo $filtro === 'problemas' ? 'on' : ''; ?>"><strong style="color:<?php echo $nProb ? '#b42318' : '#067647'; ?>"><?php echo $nProb; ?></strong>com problema</a>
                <a href="admin-pdfs.php?f=orfaos" class="<?php echo $filtro === 'orfaos' ? 'on' : ''; ?>"><strong><?php echo count($orfaos); ?></strong>arquivos sem aula</a>
                <a href="admin-revisao-aulas.php"><strong>🔎</strong>Revisão de aulas</a>
            </div>
            <form method="get" style="display:flex;gap:8px;margin-bottom:10px"><input type="hidden" name="f" value="<?php echo $e($filtro); ?>">
                <input name="q" value="<?php echo $e($_GET['q'] ?? ''); ?>" placeholder="Buscar por nome do PDF, aula ou curso" style="flex:1;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px">
                <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Buscar</button></form>

            <?php if ($filtro === 'orfaos'): ?>
                <p class="admin-form-hint">Arquivos que estão na pasta mas não estão ligados a nenhuma aula (sobras de trocas). Não apagamos sozinhos.</p>
                <table><tr><th>Arquivo</th><th>Tamanho</th></tr>
                <?php foreach ($orfaos as $f): if ($q !== '' && !str_contains(mb_strtolower($f), $q)) { continue; } ?>
                    <tr><td><a href="uploads/cursos/pdf/<?php echo rawurlencode($f); ?>" target="_blank"><?php echo $e($f); ?></a></td><td><?php echo $mb((int)filesize($dir . '/' . $f)); ?></td></tr>
                <?php endforeach; ?>
                </table>
            <?php else: ?>
                <?php if ($semArquivo && ($filtro === 'problemas' || $q === '')): ?>
                <h3 style="margin:6px 0">Aulas com nome de PDF, mas sem arquivo (<?php echo count($semArquivo); ?>)</h3>
                <table><tr><th>Nome que aparece</th><th>Aula</th><th></th></tr>
                <?php foreach ($semArquivo as $a): ?>
                    <tr><td><?php echo $e($a['pdf_nome']); ?></td><td><a href="<?php echo $e($linkAula($a)); ?>" target="_blank"><?php echo $e($a['titulo']); ?></a><br><small><?php echo $e($a['curso'] . ' · ' . $a['modulo']); ?></small></td>
                        <td><form method="post"><input type="hidden" name="acao" value="limpar_nome"><input type="hidden" name="aula_id" value="<?php echo (int)$a['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Tirar o nome</button></form>
                            <a class="admin-link-inline" href="admin-revisao-aulas.php">enviar PDF</a></td></tr>
                <?php endforeach; ?>
                </table>
                <?php endif; ?>
                <h3 style="margin:14px 0 6px">PDFs (<?php echo count($visiveis); ?>)</h3>
                <table><tr><th>PDF</th><th>Usado em</th><th>Conferência</th></tr>
                <?php foreach ($visiveis as $l): ?>
                    <tr>
                        <td><?php if ($l['existe']): ?><a href="<?php echo $e($l['arquivo']); ?>" target="_blank">📄 <?php echo $e($l['nome']); ?></a> <small>· <?php echo $mb($l['tamanho']); ?></small><?php else: ?>📄 <?php echo $e($l['nome']); ?><?php endif; ?>
                            <?php foreach ($l['problemas'] as $p): ?><div class="bp-prob">⚠️ <?php echo $e($p); ?></div><?php endforeach; ?></td>
                        <td><?php foreach ($l['aulas'] as $a): ?><a class="bp-aula" href="<?php echo $e($linkAula($a)); ?>" target="_blank"><?php echo $e($a['titulo']); ?></a><small><?php echo $e($a['curso']); ?></small><?php endforeach; ?></td>
                        <td><?php foreach ($l['revs'] as $r): $s = $selo[$r] ?? [$r, '#111']; ?><span style="color:<?php echo $s[1]; ?>"><?php echo $s[0]; ?></span><br><?php endforeach; ?></td>
                    </tr>
                <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
<script>if (window.lucide) { lucide.createIcons(); }</script>
</body>
</html>
