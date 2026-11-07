<?php
// admin-meta-plano.php
// Plano-Mestre do Método META: a sequência de assuntos de cada curso,
// com peso, horas e a aula de teoria mínima de cada assunto.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/meta-lib.php';

$erro = '';
$sucesso = '';
$pdo = null;
$cursoId = admin_cursos_int($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
    meta_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro na conexão ou criação das tabelas: ' . $e->getMessage();
}

function meta_admin_plano_atual(PDO $pdo, int $cursoId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM meta_planos WHERE curso_id = :c ORDER BY id DESC LIMIT 1');
    $stmt->execute([':c' => $cursoId]);
    return $stmt->fetch() ?: null;
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $volta = 'admin-meta-plano.php?curso_id=' . $cursoId;
    try {
        if ($cursoId <= 0) {
            throw new RuntimeException('Escolha um curso.');
        }
        $plano = meta_admin_plano_atual($pdo, $cursoId);

        if ($acao === 'criar_plano') {
            if (!$plano) {
                $stmt = $pdo->prepare('SELECT nome FROM admin_cursos WHERE id = :c');
                $stmt->execute([':c' => $cursoId]);
                $nome = (string)($stmt->fetchColumn() ?: 'Curso');
                $pdo->prepare('INSERT INTO meta_planos (curso_id, titulo) VALUES (:c, :t)')->execute([':c' => $cursoId, ':t' => 'Plano-Mestre ' . $nome]);
            }
            admin_cursos_redirect($volta . '&sucesso=plano_criado');
        }

        if (!$plano) {
            throw new RuntimeException('Crie o plano primeiro.');
        }
        $planoId = (int)$plano['id'];

        if ($acao === 'gerar_dos_modulos') {
            // Cria um assunto por aula do curso que tenha tópico, na ordem dos módulos.
            $stmt = $pdo->prepare('SELECT a.id, a.titulo, a.materia_id, a.topico_id, a.pdf_duracao, a.duracao
                                     FROM admin_curso_aulas a JOIN admin_curso_modulos m ON m.id = a.modulo_id
                                    WHERE a.curso_id = :c ORDER BY m.ordem, m.id, a.ordem, a.id');
            $stmt->execute([':c' => $cursoId]);
            $existe = $pdo->prepare('SELECT COUNT(*) FROM meta_plano_itens WHERE plano_id = :p AND aula_id = :a');
            $ins = $pdo->prepare('INSERT INTO meta_plano_itens (plano_id, ordem, materia_id, topico_id, titulo, aula_id) VALUES (:p, :o, :m, :t, :ti, :a)');
            $ordem = (int)$pdo->query('SELECT COALESCE(MAX(ordem),0) FROM meta_plano_itens WHERE plano_id = ' . $planoId)->fetchColumn();
            $n = 0;
            foreach ($stmt->fetchAll() as $a) {
                $existe->execute([':p' => $planoId, ':a' => (int)$a['id']]);
                if ((int)$existe->fetchColumn() > 0) {
                    continue;
                }
                $ordem += 10;
                $ins->execute([':p' => $planoId, ':o' => $ordem, ':m' => $a['materia_id'], ':t' => $a['topico_id'], ':ti' => $a['titulo'], ':a' => $a['id']]);
                $n++;
            }
            admin_cursos_redirect($volta . '&sucesso=gerado&qtd=' . $n);
        }

        if ($acao === 'adicionar_item') {
            $topicoId = admin_cursos_int($_POST['topico_id'] ?? 0);
            $aulaId = admin_cursos_int($_POST['aula_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $materiaId = null;
            if ($topicoId > 0) {
                $stmt = $pdo->prepare('SELECT materia_id, titulo FROM admin_materia_topicos WHERE id = :t');
                $stmt->execute([':t' => $topicoId]);
                $t = $stmt->fetch();
                if ($t) {
                    $materiaId = (int)$t['materia_id'] ?: null;
                    if ($titulo === '') {
                        $titulo = (string)$t['titulo'];
                    }
                }
            }
            if ($titulo === '') {
                throw new RuntimeException('Informe o assunto (escolha um tópico ou digite um título).');
            }
            $ordem = (int)$pdo->query('SELECT COALESCE(MAX(ordem),0) FROM meta_plano_itens WHERE plano_id = ' . $planoId)->fetchColumn() + 10;
            $pdo->prepare('INSERT INTO meta_plano_itens (plano_id, ordem, materia_id, topico_id, titulo, aula_id, peso, horas) VALUES (:p, :o, :m, :t, :ti, :a, :pe, :h)')
                ->execute([
                    ':p' => $planoId, ':o' => $ordem, ':m' => $materiaId, ':t' => $topicoId ?: null, ':ti' => $titulo,
                    ':a' => $aulaId ?: null,
                    ':pe' => max(0.0, (float)str_replace(',', '.', (string)($_POST['peso'] ?? '1'))),
                    ':h' => max(0.0, (float)str_replace(',', '.', (string)($_POST['horas'] ?? '1'))),
                ]);
            admin_cursos_redirect($volta . '&sucesso=item_adicionado');
        }

        if ($acao === 'salvar_itens') {
            $stmt = $pdo->prepare('UPDATE meta_plano_itens SET ordem = :o, peso = :pe, horas = :h, topico_id = :t, aula_id = :a WHERE id = :id AND plano_id = :p');
            foreach ((array)($_POST['itens'] ?? []) as $id => $v) {
                $stmt->execute([
                    ':o' => (int)($v['ordem'] ?? 0),
                    ':pe' => max(0.0, (float)str_replace(',', '.', (string)($v['peso'] ?? '1'))),
                    ':h' => max(0.0, (float)str_replace(',', '.', (string)($v['horas'] ?? '1'))),
                    ':t' => admin_cursos_int($v['topico_id'] ?? 0) ?: null,
                    ':a' => admin_cursos_int($v['aula_id'] ?? 0) ?: null,
                    ':id' => (int)$id, ':p' => $planoId,
                ]);
            }
            admin_cursos_redirect($volta . '&sucesso=salvo');
        }

        if ($acao === 'remover_item') {
            $pdo->prepare('DELETE FROM meta_plano_itens WHERE id = :id AND plano_id = :p')->execute([':id' => admin_cursos_int($_POST['item_id'] ?? 0), ':p' => $planoId]);
            admin_cursos_redirect($volta . '&sucesso=removido');
        }

        if ($acao === 'aprovar') {
            $pdo->prepare("UPDATE meta_planos SET status = 'aprovado', aprovado_em = NOW() WHERE id = :p")->execute([':p' => $planoId]);
            admin_cursos_redirect($volta . '&sucesso=aprovado');
        }

        if ($acao === 'voltar_rascunho') {
            $pdo->prepare("UPDATE meta_planos SET status = 'rascunho' WHERE id = :p")->execute([':p' => $planoId]);
            admin_cursos_redirect($volta . '&sucesso=rascunho');
        }

        if ($acao === 'salvar_regras') {
            $stmt = $pdo->prepare('UPDATE meta_config SET valor = :v WHERE chave = :c');
            foreach (['min_questoes', 'nivel2_questoes', 'nivel2_acerto', 'nivel3_questoes', 'nivel3_acerto', 'nivel4_questoes', 'nivel4_acerto'] as $c) {
                if (isset($_POST[$c])) {
                    $stmt->execute([':v' => (string)max(0, (int)$_POST[$c]), ':c' => $c]);
                }
            }
            admin_cursos_redirect($volta . '&sucesso=regras');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagens = [
    'plano_criado' => 'Plano criado. Agora adicione os assuntos ou gere a partir dos módulos do curso.',
    'gerado' => (int)($_GET['qtd'] ?? 0) . ' assunto(s) criado(s) a partir das aulas do curso.',
    'item_adicionado' => 'Assunto adicionado ao plano.',
    'salvo' => 'Alterações salvas.',
    'removido' => 'Assunto removido do plano.',
    'aprovado' => 'Plano aprovado. Os alunos do curso já seguem esta sequência.',
    'rascunho' => 'Plano voltou para rascunho. Os alunos deixam de ver o plano até nova aprovação.',
    'regras' => 'Regras do método atualizadas.',
];
$sk = (string)($_GET['sucesso'] ?? '');
if (isset($mensagens[$sk])) {
    $sucesso = $mensagens[$sk];
}

$cursos = [];
$plano = null;
$itens = [];
$topicos = [];
$aulas = [];
$cfg = [];
if ($pdo instanceof PDO) {
    try {
        $cursos = $pdo->query("SELECT id, nome FROM admin_cursos ORDER BY ordem, nome")->fetchAll();
        if ($cursoId <= 0 && $cursos) {
            $cursoId = (int)$cursos[0]['id'];
        }
        $cfg = meta_config($pdo);
        $topicos = $pdo->query("SELECT t.id, t.titulo, m.nome AS materia FROM admin_materia_topicos t LEFT JOIN admin_materias m ON m.id = t.materia_id ORDER BY m.nome, t.ordem, t.titulo")->fetchAll();
        if ($cursoId > 0) {
            $plano = meta_admin_plano_atual($pdo, $cursoId);
            $stmt = $pdo->prepare('SELECT id, titulo FROM admin_curso_aulas WHERE curso_id = :c ORDER BY modulo_id, ordem, id');
            $stmt->execute([':c' => $cursoId]);
            $aulas = $stmt->fetchAll();
            if ($plano) {
                $stmt = $pdo->prepare('SELECT i.*, m.nome AS materia_nome FROM meta_plano_itens i LEFT JOIN admin_materias m ON m.id = i.materia_id WHERE i.plano_id = :p ORDER BY i.ordem, i.id');
                $stmt->execute([':p' => (int)$plano['id']]);
                $itens = $stmt->fetchAll();
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar o plano: ' . $e->getMessage();
    }
}
$totalHoras = array_sum(array_map(static fn(array $i): float => (float)$i['horas'], $itens));

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano-Mestre META | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .meta-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
        .meta-status.aprovado{background:#e7f7ec;color:#17803d}
        .meta-status.rascunho{background:#fff6dd;color:#8a6300}
        .meta-itens input,.meta-itens select{width:100%;min-width:0}
        .meta-itens td{vertical-align:middle}
        .meta-num{max-width:80px}
        .meta-regras{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
        .meta-resumo{display:flex;flex-wrap:wrap;gap:16px;color:#5b6170;font-size:14px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading">
                <h1>Plano-Mestre · Método META</h1>
                <p>A sequência de assuntos que todo aluno do curso segue. Cada assunto: teoria mínima, depois questões. O próximo só abre com <?php echo (int)($cfg['min_questoes'] ?? 5); ?> questões resolvidas.</p>
            </div>
            <form method="get" class="admin-page-actions">
                <label for="curso" class="admin-sr-only">Curso</label>
                <select id="curso" name="curso_id" onchange="this.form.submit()" class="admin-select">
                    <?php foreach ($cursos as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $cursoId ? 'selected' : ''; ?>><?php echo admin_esc((string)$c['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>

        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <?php if (!$cursos): ?>
            <div class="admin-empty"><div><i data-lucide="graduation-cap"></i><h3>Nenhum curso cadastrado</h3><p>Cadastre um curso em Curso para montar o plano.</p></div></div>
        <?php elseif (!$plano): ?>
            <section class="admin-panel">
                <div class="admin-empty"><div><i data-lucide="route"></i><h3>Este curso ainda não tem Plano-Mestre</h3><p>Crie o plano e gere os assuntos a partir das aulas já cadastradas.</p>
                    <form method="post"><input type="hidden" name="acao" value="criar_plano"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="plus"></i>Criar Plano-Mestre</button></form>
                </div></div>
            </section>
        <?php else: ?>
            <section class="admin-panel">
                <div class="admin-panel-title">
                    <h2><?php echo admin_esc((string)$plano['titulo']); ?> <span class="meta-status <?php echo admin_esc((string)$plano['status']); ?>"><?php echo $plano['status'] === 'aprovado' ? 'Aprovado · alunos seguindo' : 'Rascunho · alunos ainda não veem'; ?></span></h2>
                    <div class="admin-table-actions">
                        <form method="post"><input type="hidden" name="acao" value="gerar_dos_modulos"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="wand-2"></i>Gerar das aulas do curso</button></form>
                        <?php if ($plano['status'] === 'aprovado'): ?>
                            <form method="post"><input type="hidden" name="acao" value="voltar_rascunho"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="pencil"></i>Voltar a rascunho</button></form>
                        <?php else: ?>
                            <form method="post"><input type="hidden" name="acao" value="aprovar"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit" <?php echo $itens ? '' : 'disabled'; ?>><i data-lucide="check"></i>Aprovar plano</button></form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="meta-resumo"><span><strong><?php echo count($itens); ?></strong> assuntos</span><span><strong><?php echo number_format($totalHoras, 1, ',', '.'); ?></strong> horas planejadas</span></div>

                <?php if (!$itens): ?>
                    <div class="admin-empty"><div><i data-lucide="list-ordered"></i><h3>Nenhum assunto no plano</h3><p>Use "Gerar das aulas do curso" ou adicione um assunto abaixo.</p></div></div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="acao" value="salvar_itens"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                        <div class="admin-table-wrap">
                            <table class="admin-table meta-itens">
                                <thead><tr><th>Ordem</th><th>Assunto</th><th>Tópico (para contar questões)</th><th>Aula de teoria mínima</th><th>Peso</th><th>Horas</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($itens as $i): $id = (int)$i['id']; ?>
                                    <tr>
                                        <td><input class="meta-num" type="number" name="itens[<?php echo $id; ?>][ordem]" value="<?php echo (int)$i['ordem']; ?>" aria-label="Ordem"></td>
                                        <td><strong><?php echo admin_esc((string)$i['titulo']); ?></strong><br><span><?php echo admin_esc((string)($i['materia_nome'] ?? '')); ?></span></td>
                                        <td><select name="itens[<?php echo $id; ?>][topico_id]" aria-label="Tópico"><option value="0">— sem tópico —</option><?php foreach ($topicos as $t): ?><option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === (int)$i['topico_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$t['materia'] . ' › ' . (string)$t['titulo']); ?></option><?php endforeach; ?></select>
                                            <?php if (empty($i['topico_id'])): ?><span class="admin-form-hint">Sem tópico, as questões não contam.</span><?php endif; ?></td>
                                        <td><select name="itens[<?php echo $id; ?>][aula_id]" aria-label="Aula"><option value="0">— sem aula —</option><?php foreach ($aulas as $a): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo (int)$a['id'] === (int)$i['aula_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$a['titulo']); ?></option><?php endforeach; ?></select></td>
                                        <td><input class="meta-num" type="text" inputmode="decimal" name="itens[<?php echo $id; ?>][peso]" value="<?php echo admin_esc(rtrim(rtrim((string)$i['peso'], '0'), '.')); ?>" aria-label="Peso"></td>
                                        <td><input class="meta-num" type="text" inputmode="decimal" name="itens[<?php echo $id; ?>][horas]" value="<?php echo admin_esc(rtrim(rtrim((string)$i['horas'], '0'), '.')); ?>" aria-label="Horas"></td>
                                        <td><button class="admin-btn admin-btn-danger admin-btn-small" type="submit" form="rm-<?php echo $id; ?>" aria-label="Remover"><i data-lucide="trash-2"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i>Salvar alterações</button></div>
                    </form>
                    <?php foreach ($itens as $i): ?>
                        <form id="rm-<?php echo (int)$i['id']; ?>" method="post" onsubmit="return confirm('Remover este assunto do plano?');"><input type="hidden" name="acao" value="remover_item"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$i['id']; ?>"></form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Adicionar assunto</h2></div>
                <form method="post" class="admin-form-grid">
                    <input type="hidden" name="acao" value="adicionar_item"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                    <div class="admin-form-field"><label for="novo-topico">Tópico</label><select id="novo-topico" name="topico_id"><option value="0">— escolha —</option><?php foreach ($topicos as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo admin_esc((string)$t['materia'] . ' › ' . (string)$t['titulo']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="novo-titulo">Título (opcional)</label><input id="novo-titulo" type="text" name="titulo" placeholder="Usa o nome do tópico se vazio"></div>
                    <div class="admin-form-field"><label for="nova-aula">Aula de teoria mínima</label><select id="nova-aula" name="aula_id"><option value="0">— sem aula —</option><?php foreach ($aulas as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo admin_esc((string)$a['titulo']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="novo-peso">Peso</label><input id="novo-peso" type="text" inputmode="decimal" name="peso" value="1"></div>
                    <div class="admin-form-field"><label for="novas-horas">Horas</label><input id="novas-horas" type="text" inputmode="decimal" name="horas" value="1"></div>
                    <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="plus"></i>Adicionar</button></div>
                </form>
            </section>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-panel-title"><h2>Regras do método (valem para todos os cursos)</h2></div>
            <form method="post">
                <input type="hidden" name="acao" value="salvar_regras"><input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                <div class="meta-regras">
                    <div class="admin-form-field"><label for="r-min">Mínimo para avançar (nível 1)</label><input id="r-min" type="number" min="1" name="min_questoes" value="<?php echo (int)($cfg['min_questoes'] ?? 5); ?>"></div>
                    <div class="admin-form-field"><label for="r-n2q">Nível 2 · questões</label><input id="r-n2q" type="number" name="nivel2_questoes" value="<?php echo (int)($cfg['nivel2_questoes'] ?? 15); ?>"></div>
                    <div class="admin-form-field"><label for="r-n2a">Nível 2 · % acerto</label><input id="r-n2a" type="number" name="nivel2_acerto" value="<?php echo (int)($cfg['nivel2_acerto'] ?? 60); ?>"></div>
                    <div class="admin-form-field"><label for="r-n3q">Nível 3 · questões</label><input id="r-n3q" type="number" name="nivel3_questoes" value="<?php echo (int)($cfg['nivel3_questoes'] ?? 30); ?>"></div>
                    <div class="admin-form-field"><label for="r-n3a">Nível 3 · % acerto</label><input id="r-n3a" type="number" name="nivel3_acerto" value="<?php echo (int)($cfg['nivel3_acerto'] ?? 70); ?>"></div>
                    <div class="admin-form-field"><label for="r-n4q">Nível 4 (meta) · questões</label><input id="r-n4q" type="number" name="nivel4_questoes" value="<?php echo (int)($cfg['nivel4_questoes'] ?? 50); ?>"></div>
                    <div class="admin-form-field"><label for="r-n4a">Nível 4 · % acerto</label><input id="r-n4a" type="number" name="nivel4_acerto" value="<?php echo (int)($cfg['nivel4_acerto'] ?? 80); ?>"></div>
                </div>
                <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i>Salvar regras</button></div>
            </form>
        </section>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
