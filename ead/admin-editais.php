<?php
// admin-editais.php
// Ciclo de cadastro: 1) Concurso e cargos  2) Edital (IA lê o conteúdo
// programático)  3) Aulas (cola o link, a IA sugere assunto e cargos)
// 4) Andamento do edital por cargo.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/ia-fila-lib.php';
require_once __DIR__ . '/includes/edital-lib.php';
@set_time_limit(320);

$pdo = admin_cursos_db();
edital_bootstrap($pdo);
ia_fila_bootstrap($pdo);

$concursoId = (int)($_GET['concurso_id'] ?? $_POST['concurso_id'] ?? 0);
$aba = (string)($_GET['aba'] ?? 'andamento');
$erro = '';
$sucesso = (string)($_SESSION['edital_msg'] ?? '');
unset($_SESSION['edital_msg']);

function ed_ir(int $concursoId, string $aba, string $msg = ''): void
{
    if ($msg !== '') {
        $_SESSION['edital_msg'] = $msg;
    }
    header('Location: admin-editais.php?concurso_id=' . $concursoId . '&aba=' . $aba);
    exit;
}

/** Texto editável do edital: "## Matéria [cargo1; cargo2]" e "- assunto". */
function ed_para_texto(array $materias): string
{
    $t = '';
    foreach ($materias as $m) {
        $cargos = array_filter((array)$m['cargos'], static fn($c) => edital_normalizar((string)$c) !== 'todos');
        $t .= '## ' . $m['materia'] . ($cargos ? ' [' . implode('; ', $cargos) . ']' : '') . "\n";
        foreach ($m['assuntos'] as $a) {
            $t .= '- ' . $a . "\n";
        }
        $t .= "\n";
    }
    return trim($t);
}

function ed_de_texto(string $txt): array
{
    $res = [];
    $atual = null;
    foreach (preg_split('/\R/', $txt) as $l) {
        $l = trim($l);
        if (preg_match('/^##\s*(.+?)\s*(?:\[(.+)\])?$/u', $l, $m)) {
            if ($atual) { $res[] = $atual; }
            $atual = ['materia' => trim($m[1]), 'cargos' => isset($m[2]) ? array_map('trim', explode(';', $m[2])) : ['todos'], 'assuntos' => []];
        } elseif ($atual && preg_match('/^[-•*]\s*(.+)$/u', $l, $m)) {
            $atual['assuntos'][] = trim($m[1]);
        }
    }
    if ($atual) { $res[] = $atual; }
    return array_values(array_filter($res, static fn($m) => $m['assuntos']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'criar_concurso') {
            $nome = trim((string)($_POST['nome'] ?? ''));
            if ($nome === '') { throw new RuntimeException('Informe o nome do concurso.'); }
            $pdo->prepare('INSERT INTO concursos (nome, banca_id, situacao, base_edital, data_prova) VALUES (:n, :b, :s, :be, :d)')->execute([
                ':n' => $nome, ':b' => (int)($_POST['banca_id'] ?? 0) ?: null,
                ':s' => in_array($_POST['situacao'] ?? '', ['aberto', 'previsto', 'encerrado'], true) ? $_POST['situacao'] : 'previsto',
                ':be' => trim((string)($_POST['base_edital'] ?? '')) ?: null, ':d' => ($_POST['data_prova'] ?? '') ?: null,
            ]);
            $novo = (int)$pdo->lastInsertId();
            foreach (preg_split('/\R/', (string)($_POST['cargos'] ?? '')) as $cargo) {
                if (trim($cargo) !== '') {
                    $pdo->prepare('INSERT INTO concurso_cargos (concurso_id, nome) VALUES (:c, :n)')->execute([':c' => $novo, ':n' => trim($cargo)]);
                }
            }
            ed_ir($novo, 'concurso', 'Concurso criado. Agora ligue cada cargo ao curso da plataforma e cadastre o edital.');
        }
        if ($concursoId <= 0) { throw new RuntimeException('Escolha um concurso.'); }

        if ($acao === 'salvar_concurso') {
            $pdo->prepare('UPDATE concursos SET nome = :n, banca_id = :b, situacao = :s, base_edital = :be, data_prova = :d WHERE id = :id')->execute([
                ':n' => trim((string)$_POST['nome']), ':b' => (int)($_POST['banca_id'] ?? 0) ?: null,
                ':s' => in_array($_POST['situacao'] ?? '', ['aberto', 'previsto', 'encerrado'], true) ? $_POST['situacao'] : 'previsto',
                ':be' => trim((string)($_POST['base_edital'] ?? '')) ?: null, ':d' => ($_POST['data_prova'] ?? '') ?: null, ':id' => $concursoId,
            ]);
            foreach ((array)($_POST['cargo_curso'] ?? []) as $cid => $curso) {
                $curso = (string)$curso;
                if ($curso === 'novo') {
                    $nomeCargo = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE id = :id');
                    $nomeCargo->execute([':id' => (int)$cid]);
                    $pdo->prepare("INSERT INTO admin_cursos (nome, status, ordem) VALUES (:n, 'ativo', 0)")->execute([':n' => trim((string)$_POST['nome']) . ' - ' . $nomeCargo->fetchColumn()]);
                    $curso = (string)$pdo->lastInsertId();
                }
                $pdo->prepare('UPDATE concurso_cargos SET curso_id = :c WHERE id = :id AND concurso_id = :k')->execute([':c' => (int)$curso ?: null, ':id' => (int)$cid, ':k' => $concursoId]);
            }
            if (trim((string)($_POST['novo_cargo'] ?? '')) !== '') {
                $pdo->prepare('INSERT INTO concurso_cargos (concurso_id, nome) VALUES (:c, :n)')->execute([':c' => $concursoId, ':n' => trim((string)$_POST['novo_cargo'])]);
            }
            ed_ir($concursoId, 'concurso', 'Concurso salvo.');
        }

        if ($acao === 'ler_edital') {
            $texto = trim((string)($_POST['conteudo'] ?? ''));
            $pdf = '';
            if (!empty($_FILES['pdf']['tmp_name']) && is_uploaded_file($_FILES['pdf']['tmp_name'])) {
                if ((int)$_FILES['pdf']['size'] > 8 * 1024 * 1024) { throw new RuntimeException('PDF acima de 8 MB. Cole só o conteúdo programático.'); }
                $pdf = base64_encode((string)file_get_contents($_FILES['pdf']['tmp_name']));
            }
            if ($texto === '' && $pdf === '') { throw new RuntimeException('Cole o conteúdo programático ou envie o PDF do edital.'); }
            $cg = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE concurso_id = :c');
            $cg->execute([':c' => $concursoId]);
            $lido = edital_ia_ler($texto, $pdf, $cg->fetchAll(PDO::FETCH_COLUMN));
            $_SESSION['edital_rascunho'][$concursoId] = ed_para_texto($lido);
            ed_ir($concursoId, 'edital', 'A IA leu o edital. Confira, ajuste se precisar e clique em "Confirmar edital".');
        }
        if ($acao === 'confirmar_edital') {
            $materias = ed_de_texto((string)($_POST['rascunho'] ?? ''));
            if (!$materias) { throw new RuntimeException('Nada para salvar. Use o formato "## Matéria" e "- assunto".'); }
            $n = edital_salvar($pdo, $concursoId, $materias);
            unset($_SESSION['edital_rascunho'][$concursoId]);
            ed_ir($concursoId, 'aulas', $n . ' assuntos no edital. Agora cadastre as aulas: cole os links do YouTube.');
        }

        if ($acao === 'sugerir_aulas') {
            $linhas = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['links'] ?? '')))));
            $sug = $_SESSION['edital_sugestoes'][$concursoId] ?? [];
            $t0 = time();
            $restantes = [];
            foreach ($linhas as $l) {
                [$url, $tit] = array_pad(array_map('trim', explode('|', $l, 2)), 2, '');
                if (!preg_match('~^https?://~i', $url)) { continue; }
                if (time() - $t0 > 120) { $restantes[] = $l; continue; }
                $s = edital_ia_sugerir_aula($pdo, $concursoId, $url, $tit);
                $pdo = podcast_reconectar($pdo);
                $sug[] = $s + ['url' => $url];
            }
            $_SESSION['edital_sugestoes'][$concursoId] = $sug;
            $_SESSION['edital_links_restantes'][$concursoId] = implode("\n", $restantes);
            ed_ir($concursoId, 'aulas', count($sug) . ' sugestão(ões) prontas para conferir.' . ($restantes ? ' ' . count($restantes) . ' link(s) ficaram para a próxima rodada (clique de novo em "Sugerir").' : ''));
        }
        if ($acao === 'cadastrar_aulas') {
            $sug = $_SESSION['edital_sugestoes'][$concursoId] ?? [];
            $criadas = 0;
            $pendentes = [];
            foreach ((array)($_POST['s'] ?? []) as $i => $v) {
                if (empty($v['usar']) || !isset($sug[(int)$i])) { continue; }
                $titulo = trim((string)$v['titulo']);
                $sug[(int)$i]['titulo'] = $titulo;
                $sug[(int)$i]['edital_item_id'] = (int)$v['item'];
                $sug[(int)$i]['cargos'] = array_map('intval', (array)($v['cargos'] ?? []));
                if ((int)$v['item'] <= 0 || empty($v['cargos'])) {
                    $pendentes[] = $titulo; // fica na lista para escolher assunto/cargo
                    continue;
                }
                $ids = edital_criar_aula($pdo, (int)$v['item'], $sug[(int)$i]['cargos'], $titulo, (string)$sug[(int)$i]['url']);
                $criadas += count($ids);
                unset($sug[(int)$i]);
            }
            $_SESSION['edital_sugestoes'][$concursoId] = array_values($sug);
            $msg = $criadas . ' aula(s) cadastradas. A IA já vai gerar a apostila e o podcast de cada uma.';
            if ($pendentes) {
                $msg .= ' Faltou escolher assunto ou cargo em: ' . implode('; ', $pendentes) . '.';
                ed_ir($concursoId, 'aulas', $msg);
            }
            ed_ir($concursoId, 'andamento', $msg);
        }
        if ($acao === 'sugerir_plano') {
            edital_plano_bootstrap($pdo);
            $cargoId = (int)($_POST['cargo_id'] ?? 0);
            $pdf = '';
            if (!empty($_FILES['prova']['tmp_name']) && is_uploaded_file($_FILES['prova']['tmp_name'])) {
                if ((int)$_FILES['prova']['size'] > 8 * 1024 * 1024) { throw new RuntimeException('PDF da prova acima de 8 MB.'); }
                $pdf = base64_encode((string)file_get_contents($_FILES['prova']['tmp_name']));
            }
            // Usa a última prova já encontrada, a menos que venha um PDF novo.
            $st = $pdo->prepare('SELECT ultima_prova FROM concursos WHERE id = :id');
            $st->execute([':id' => $concursoId]);
            $prova = json_decode((string)$st->fetchColumn(), true);
            if ($pdf !== '' || !is_array($prova) || empty($prova['encontrada'])) {
                $prova = edital_ultima_prova($pdo, $concursoId, $pdf);
                $pdo = podcast_reconectar($pdo);
            }
            $planoId = edital_sugerir_plano($pdo, $concursoId, $cargoId, $prova);
            $cursoDoCargo = $pdo->prepare('SELECT curso_id FROM concurso_cargos WHERE id = :id');
            $cursoDoCargo->execute([':id' => $cargoId]);
            $_SESSION['edital_msg'] = 'Plano-Mestre sugerido (rascunho). '
                . (!empty($prova['encontrada']) ? 'Base: última prova ' . trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '')) . ' (' . ($prova['fonte'] ?? '') . ').' : 'Não encontrei a última prova: os pesos foram estimados. Envie o PDF da prova para refazer com a recorrência real.')
                . ' Confira a justificativa de cada assunto e aprove.';
            header('Location: admin-meta-plano.php?curso_id=' . (int)$cursoDoCargo->fetchColumn());
            exit;
        }
        if ($acao === 'limpar_sugestoes') {
            unset($_SESSION['edital_sugestoes'][$concursoId]);
            ed_ir($concursoId, 'aulas');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$concursos = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM edital_itens e WHERE e.concurso_id = c.id) AS itens FROM concursos c ORDER BY c.id DESC')->fetchAll();
$bancas = $pdo->query('SELECT id, nome FROM admin_bancas ORDER BY nome')->fetchAll();
$cursos = $pdo->query('SELECT id, nome FROM admin_cursos ORDER BY nome')->fetchAll();
$concurso = null;
$cargos = [];
$itens = [];
$andamento = [];
if ($concursoId > 0) {
    $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
    $st->execute([':id' => $concursoId]);
    $concurso = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT * FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
    $st->execute([':c' => $concursoId]);
    $cargos = $st->fetchAll();
    $itens = edital_itens($pdo, $concursoId);
    $andamento = edital_andamento($pdo, $concursoId);
}
$nomeCargo = [];
foreach ($cargos as $c) { $nomeCargo[(int)$c['id']] = (string)$c['nome']; }
$sugestoes = $_SESSION['edital_sugestoes'][$concursoId] ?? [];
$rascunho = $_SESSION['edital_rascunho'][$concursoId] ?? '';
$linksRestantes = $_SESSION['edital_links_restantes'][$concursoId] ?? '';
$situacoes = ['aberto' => 'Edital aberto', 'previsto' => 'Previsto (base: último edital)', 'encerrado' => 'Encerrado'];

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concursos e editais | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .ed-passos{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px}
        .ed-passos a{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:#fff;border:1px solid #eef0f3;text-decoration:none;color:#374151;font-weight:700;font-size:14px}
        .ed-passos a b{display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:#eef0f3;font-size:12px}
        .ed-passos a.ativo{border-color:#fbb928;background:#fff8e6}
        .ed-passos a.ativo b{background:#fbb928;color:#111}
        .ed-tag{font-size:12px;font-weight:700;padding:3px 8px;border-radius:6px;background:#eef0f3;color:#374151}
        .ed-tag.aberto{background:#e7f7ec;color:#17803d}.ed-tag.previsto{background:#fff6dd;color:#8a6300}
        .ed-barra{height:10px;background:#eef0f3;border-radius:99px;overflow:hidden}.ed-barra i{display:block;height:100%;background:#16a34a}
        .ed-materia{border:1px solid #eef0f3;border-radius:12px;margin:10px 0;overflow:hidden}
        .ed-materia summary{padding:10px 14px;font-weight:700;cursor:pointer;display:flex;justify-content:space-between;gap:10px;background:#fafafa}
        .ed-assunto{display:flex;gap:10px;padding:8px 14px;border-top:1px solid #f3f4f6;font-size:14px;align-items:baseline}
        .ed-assunto small{color:#6b7280}
        .ed-falta{color:#b42318;font-weight:700}
        textarea.ed-mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;min-height:320px}
        .ed-sug{display:grid;grid-template-columns:28px 1.2fr 1.4fr 1fr 80px;gap:10px;align-items:center;padding:10px;border-top:1px solid #f3f4f6}
        .ed-sug select,.ed-sug input[type=text]{width:100%}
        @media(max-width:900px){.ed-sug{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading">
                <h1>Concursos e editais</h1>
                <p>Cadastre o concurso, o edital e as aulas. A IA lê o edital, identifica o assunto e o cargo de cada aula e mostra o que falta gravar.</p>
            </div>
            <form method="get" class="admin-page-actions">
                <select name="concurso_id" onchange="this.form.submit()" aria-label="Concurso">
                    <option value="0">— escolha o concurso —</option>
                    <?php foreach ($concursos as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $concursoId ? 'selected' : ''; ?>><?php echo admin_esc((string)$c['nome']); ?></option><?php endforeach; ?>
                </select>
                <input type="hidden" name="aba" value="andamento">
            </form>
        </section>

        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <?php if (!$concurso): ?>
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Novo concurso</h2></div>
                <form method="post" class="admin-form-grid">
                    <input type="hidden" name="acao" value="criar_concurso">
                    <div class="admin-form-field"><label for="nome">Nome</label><input id="nome" name="nome" required placeholder="Ex.: PM e Bombeiros da Bahia 2026"></div>
                    <div class="admin-form-field"><label for="banca">Banca</label><select id="banca" name="banca_id"><option value="0">—</option><?php foreach ($bancas as $b): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo admin_esc((string)$b['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="sit">Situação</label><select id="sit" name="situacao"><?php foreach ($situacoes as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="base">Edital usado (se previsto)</label><input id="base" name="base_edital" placeholder="Ex.: edital de 2022"></div>
                    <div class="admin-form-field"><label for="prova">Data da prova (se souber)</label><input id="prova" name="data_prova" type="date"></div>
                    <div class="admin-form-field full"><label for="cargos">Cargos (um por linha)</label><textarea id="cargos" name="cargos" rows="3" placeholder="Soldado PM&#10;Soldado Bombeiro"></textarea></div>
                    <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="plus"></i>Criar concurso</button></div>
                </form>
            </section>
            <?php if ($concursos): ?>
            <section class="admin-panel"><div class="admin-panel-title"><h2>Concursos cadastrados</h2></div>
                <?php foreach ($concursos as $c): ?><p><a class="admin-link-inline" href="admin-editais.php?concurso_id=<?php echo (int)$c['id']; ?>"><?php echo admin_esc((string)$c['nome']); ?></a> <span class="ed-tag <?php echo admin_esc((string)$c['situacao']); ?>"><?php echo admin_esc($situacoes[$c['situacao']] ?? ''); ?></span> · <?php echo (int)$c['itens']; ?> assuntos no edital</p><?php endforeach; ?>
            </section>
            <?php endif; ?>
        <?php else: ?>
            <nav class="ed-passos" aria-label="Etapas">
                <?php foreach (['concurso' => 'Concurso e cargos', 'edital' => 'Edital', 'aulas' => 'Aulas', 'andamento' => 'Andamento'] as $k => $v): $n = array_search($k, ['concurso', 'edital', 'aulas', 'andamento'], true) + 1; ?>
                    <a class="<?php echo $aba === $k ? 'ativo' : ''; ?>" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=<?php echo $k; ?>"><b><?php echo $n; ?></b><?php echo $v; ?></a>
                <?php endforeach; ?>
                <a href="admin-editais.php" style="margin-left:auto"><i data-lucide="plus"></i>Novo concurso</a>
            </nav>

            <?php if ($aba === 'concurso'): ?>
                <section class="admin-panel">
                    <form method="post" class="admin-form-grid">
                        <input type="hidden" name="acao" value="salvar_concurso"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <div class="admin-form-field"><label for="nome">Nome</label><input id="nome" name="nome" required value="<?php echo admin_esc((string)$concurso['nome']); ?>"></div>
                        <div class="admin-form-field"><label for="banca">Banca</label><select id="banca" name="banca_id"><option value="0">—</option><?php foreach ($bancas as $b): ?><option value="<?php echo (int)$b['id']; ?>" <?php echo (int)$b['id'] === (int)$concurso['banca_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$b['nome']); ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="sit">Situação</label><select id="sit" name="situacao"><?php foreach ($situacoes as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $concurso['situacao'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="base">Edital usado (se previsto)</label><input id="base" name="base_edital" value="<?php echo admin_esc((string)($concurso['base_edital'] ?? '')); ?>"></div>
                        <div class="admin-form-field"><label for="prova">Data da prova</label><input id="prova" name="data_prova" type="date" value="<?php echo admin_esc((string)($concurso['data_prova'] ?? '')); ?>"></div>
                        <div class="admin-form-field full"><label>Cargos e o curso de cada um na plataforma</label>
                            <?php foreach ($cargos as $cg): ?>
                                <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px"><strong style="min-width:180px"><?php echo admin_esc((string)$cg['nome']); ?></strong>
                                <select name="cargo_curso[<?php echo (int)$cg['id']; ?>]" aria-label="Curso do cargo"><option value="0">— sem curso —</option><option value="novo">+ Criar curso novo para este cargo</option><?php foreach ($cursos as $cu): ?><option value="<?php echo (int)$cu['id']; ?>" <?php echo (int)$cu['id'] === (int)$cg['curso_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$cu['nome']); ?></option><?php endforeach; ?></select></div>
                            <?php endforeach; ?>
                            <input name="novo_cargo" placeholder="Adicionar cargo (ex.: Soldado Bombeiro)" style="margin-top:6px">
                        </div>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i>Salvar</button></div>
                    </form>
                </section>

            <?php elseif ($aba === 'edital'): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>1. A IA lê o edital</h2></div>
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="ler_edital"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <div class="admin-form-field full"><label for="conteudo">Cole o conteúdo programático (do edital aberto ou do último edital)</label><textarea id="conteudo" name="conteudo" rows="8" placeholder="LÍNGUA PORTUGUESA: 1. Compreensão e interpretação de textos..."></textarea></div>
                        <div class="admin-form-field"><label for="pdf">ou envie o PDF do edital (até 8 MB)</label><input id="pdf" type="file" name="pdf" accept="application/pdf"></div>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>Ler com a IA</button></div>
                    </form>
                </section>
                <?php if ($rascunho !== ''): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>2. Confira e confirme</h2></div>
                    <p class="admin-form-hint">"## Matéria" abre uma matéria; entre colchetes, os cargos (separados por ;) quando a matéria não vale para todos. "- assunto" em cada linha. Pode editar à vontade.</p>
                    <form method="post"><input type="hidden" name="acao" value="confirmar_edital"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <textarea class="ed-mono" name="rascunho" style="width:100%"><?php echo admin_esc($rascunho); ?></textarea>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="check"></i>Confirmar edital</button></div>
                    </form>
                </section>
                <?php endif; ?>
                <?php if ($itens): ?>
                <section class="admin-panel"><div class="admin-panel-title"><h2>Edital atual</h2><small><?php echo count($itens); ?> assuntos</small></div>
                    <?php $grupo = []; foreach ($itens as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): ?>
                        <details class="ed-materia"><summary><span><?php echo admin_esc($mat); ?></span><small><?php echo count($lista); ?> assuntos</small></summary>
                        <?php foreach ($lista as $it): ?><div class="ed-assunto"><span><?php echo admin_esc((string)$it['assunto']); ?></span><small><?php $cs = array_filter(array_map('intval', explode(',', (string)$it['cargos']))); echo $cs ? admin_esc(implode(', ', array_map(static fn($i) => $nomeCargo[$i] ?? '', $cs))) : 'todos os cargos'; ?></small></div><?php endforeach; ?>
                        </details>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

            <?php elseif ($aba === 'aulas'): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>Cole os links das aulas</h2></div>
                    <?php if (!$itens): ?><p class="admin-form-hint">Cadastre o edital antes (passo 2).</p><?php endif; ?>
                    <form method="post"><input type="hidden" name="acao" value="sugerir_aulas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <label for="links" class="admin-form-hint">Um link do YouTube por linha. Opcional: "link | título da aula".</label>
                        <textarea id="links" name="links" rows="6" style="width:100%" placeholder="https://www.youtube.com/watch?v=...&#10;https://youtu.be/... | Crase - teoria mínima"><?php echo admin_esc($linksRestantes); ?></textarea>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit" <?php echo $itens ? '' : 'disabled'; ?>><i data-lucide="sparkles"></i>Sugerir assunto e cargo com a IA</button></div>
                    </form>
                </section>
                <?php if ($sugestoes): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>Confira as sugestões</h2><form method="post"><input type="hidden" name="acao" value="limpar_sugestoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Limpar</button></form></div>
                    <form method="post"><input type="hidden" name="acao" value="cadastrar_aulas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <?php foreach ($sugestoes as $i => $s): ?>
                            <div class="ed-sug">
                                <input type="checkbox" name="s[<?php echo $i; ?>][usar]" value="1" checked aria-label="Cadastrar esta aula">
                                <div><input type="text" name="s[<?php echo $i; ?>][titulo]" value="<?php echo admin_esc((string)$s['titulo']); ?>" aria-label="Título"><small><a href="<?php echo admin_esc((string)$s['url']); ?>" target="_blank" rel="noopener">ver vídeo</a> · confiança <?php echo (int)$s['confianca']; ?>% · <?php echo admin_esc((string)$s['motivo']); ?></small></div>
                                <select name="s[<?php echo $i; ?>][item]" aria-label="Assunto do edital"><option value="0">— escolha o assunto —</option><?php foreach ($itens as $it): ?><option value="<?php echo (int)$it['id']; ?>" <?php echo (int)$it['id'] === (int)$s['edital_item_id'] ? 'selected' : ''; ?>><?php echo admin_esc($it['materia'] . ' › ' . $it['assunto']); ?></option><?php endforeach; ?></select>
                                <div><?php foreach ($cargos as $cg): ?><label style="display:block;font-size:13px"><input type="checkbox" name="s[<?php echo $i; ?>][cargos][]" value="<?php echo (int)$cg['id']; ?>" <?php echo in_array((int)$cg['id'], (array)$s['cargos'], true) ? 'checked' : ''; ?>> <?php echo admin_esc((string)$cg['nome']); ?></label><?php endforeach; ?></div>
                                <span class="ed-tag"><?php echo (int)$s['confianca']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="check"></i>Cadastrar as aulas marcadas</button></div>
                    </form>
                </section>
                <?php endif; ?>

            <?php else: ?>
                <?php if (!$itens): ?>
                    <section class="admin-panel"><div class="admin-empty"><div><i data-lucide="file-text"></i><h3>Este concurso ainda não tem edital</h3><p><a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=edital">Cadastre o edital</a> e a IA monta a lista de assuntos.</p></div></div></section>
                <?php endif; ?>
                <?php foreach ($andamento as $an): $pct = $an['total'] ? (int)round($an['cobertos'] * 100 / $an['total']) : 0; ?>
                    <section class="admin-panel">
                        <div class="admin-panel-title"><h2><?php echo admin_esc((string)$an['cargo']['nome']); ?></h2><strong><?php echo $an['cobertos']; ?>/<?php echo $an['total']; ?> assuntos com aula · <?php echo $pct; ?>%</strong></div>
                        <?php if (empty($an['cargo']['curso_id'])): ?><p class="ed-falta">Este cargo ainda não está ligado a um curso. <a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=concurso">Ligar agora</a></p><?php endif; ?>
                        <form method="post" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:10px 0">
                            <input type="hidden" name="acao" value="sugerir_plano"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$an['cargo']['id']; ?>">
                            <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" <?php echo empty($an['cargo']['curso_id']) ? 'disabled' : ''; ?>><i data-lucide="sparkles"></i>Sugerir Plano-Mestre deste cargo</button>
                            <label class="admin-form-hint" style="display:flex;gap:6px;align-items:center">PDF da última prova (opcional, se a IA não achar): <input type="file" name="prova" accept="application/pdf"></label>
                        </form>
                        <div class="ed-barra" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?php echo $pct; ?>%"></i></div>
                        <?php $grupo = []; foreach ($an['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['aulas'])); ?>
                            <details class="ed-materia"<?php echo $ok < count($lista) ? ' open' : ''; ?>><summary><span><?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?></small></summary>
                            <?php foreach ($lista as $it): ?>
                                <div class="ed-assunto"><span><?php echo $it['aulas'] ? '✅' : '⬜'; ?></span><span><?php echo admin_esc((string)$it['assunto']); ?></span>
                                <small><?php echo $it['aulas'] ? admin_esc(implode(' · ', array_column($it['aulas'], 'titulo'))) : '<span class="ed-falta">falta aula</span>'; ?></small></div>
                            <?php endforeach; ?>
                            </details>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
