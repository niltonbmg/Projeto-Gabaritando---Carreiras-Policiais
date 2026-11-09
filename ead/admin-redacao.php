<?php
// admin-redacao.php
// Mentor/admin: propostas de redação, limite por semana e acompanhamento da correção.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/redacao-lib.php';

$pdo = admin_cursos_db();
redacao_bootstrap($pdo);
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = (string)($_POST['acao'] ?? '');
        if ($acao === 'proposta') {
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $tema = trim((string)($_POST['tema'] ?? ''));
            if ($titulo === '' || $tema === '') { throw new RuntimeException('Informe título e tema.'); }
            $pdo->prepare('INSERT INTO redacao_propostas (titulo, tema, texto_apoio) VALUES (:t, :m, :a)')->execute([':t' => $titulo, ':m' => $tema, ':a' => trim((string)($_POST['apoio'] ?? '')) ?: null]);
            $sucesso = 'Proposta publicada para os alunos.';
        } elseif ($acao === 'alternar') {
            $pdo->prepare('UPDATE redacao_propostas SET ativa = 1 - ativa WHERE id = :id')->execute([':id' => (int)$_POST['id']]);
            $sucesso = 'Proposta atualizada.';
        } elseif ($acao === 'limite') {
            $pdo->prepare("REPLACE INTO redacao_config (chave, valor) VALUES ('limite_semana', :v)")->execute([':v' => (string)max(0, (int)$_POST['limite'])]);
            $sucesso = 'Limite salvo.';
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$propostas = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM redacoes r WHERE r.proposta_id = p.id) AS envios FROM redacao_propostas p ORDER BY p.id DESC')->fetchAll();
$n = $pdo->query("SELECT SUM(status <> 'corrigida') fila, SUM(status <> 'corrigida' AND enviada_em < NOW() - INTERVAL " . REDACAO_PRAZO_HORAS . " HOUR) atrasadas,
                         SUM(status = 'corrigida' AND corrigida_em >= NOW() - INTERVAL 7 DAY) semana, AVG(CASE WHEN status = 'corrigida' THEN nota END) media FROM redacoes")->fetch();
$profs = $pdo->query("SELECT u.nome, COUNT(*) corrigidas, AVG(TIMESTAMPDIFF(HOUR, r.enviada_em, r.corrigida_em)) horas,
                             SUM(r.nota_origem = 'ia') usou_ia, AVG(ABS(r.prof_nota - r.ia_nota)) dif
                        FROM redacoes r JOIN usuarios u ON u.id = r.professor_id WHERE r.status = 'corrigida' GROUP BY u.id, u.nome ORDER BY corrigidas DESC")->fetchAll();
$limite = (int)redacao_config($pdo, 'limite_semana', '1');
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redação | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .rd-nums{display:flex;flex-wrap:wrap;gap:12px}
        .rd-nums div{background:#fff;border:1px solid #eef0f3;border-radius:12px;padding:10px 14px}
        .rd-nums strong{display:block;font-size:22px}.rd-nums span{font-size:12px;color:#6b7280;text-transform:uppercase}
        .rd-linha{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #f3f4f6}
        table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-top:1px solid #f3f4f6;font-size:14px}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading"><h1>✍️ Redação</h1><p>Propostas, limite de envio e acompanhamento da correção. Professores de redação: cadastre em Usuários com o tipo "Professor de redação".</p></div>
            <div class="admin-page-actions"><a class="admin-btn admin-btn-primary" href="correcao-redacao.php"><i data-lucide="pen-line"></i>Abrir fila de correção</a></div>
        </section>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><?php echo admin_esc($sucesso); ?></div><?php endif; ?>
        <section class="admin-panel"><div class="rd-nums">
            <div><span>Na fila</span><strong><?php echo (int)$n['fila']; ?></strong></div>
            <div><span>Atrasadas (+<?php echo REDACAO_PRAZO_HORAS; ?>h)</span><strong style="color:#b42318"><?php echo (int)$n['atrasadas']; ?></strong></div>
            <div><span>Corrigidas em 7 dias</span><strong><?php echo (int)$n['semana']; ?></strong></div>
            <div><span>Nota média</span><strong><?php echo redacao_fmt($n['media'] !== null ? (float)$n['media'] : null); ?></strong></div>
        </div></section>
        <section class="admin-panel"><div class="admin-panel-title"><h2>Professores</h2></div>
            <?php if (!$profs): ?><p class="admin-form-hint">Nenhuma correção ainda.</p><?php else: ?>
            <table><thead><tr><th>Professor</th><th>Corrigidas</th><th>Tempo médio</th><th>Diferença média p/ Antigão</th><th>Usou a nota da IA</th></tr></thead><tbody>
            <?php foreach ($profs as $p): ?><tr><td><?php echo admin_esc((string)$p['nome']); ?></td><td><?php echo (int)$p['corrigidas']; ?></td><td><?php echo (int)$p['horas']; ?>h</td><td><?php echo redacao_fmt($p['dif'] !== null ? (float)$p['dif'] : null); ?></td><td><?php echo (int)$p['usou_ia']; ?>x</td></tr><?php endforeach; ?>
            </tbody></table><?php endif; ?>
        </section>
        <section class="admin-panel"><div class="admin-panel-title"><h2>Nova proposta</h2></div>
            <form method="post" class="admin-form-grid"><input type="hidden" name="acao" value="proposta">
                <div class="admin-form-field full"><label for="titulo">Título</label><input id="titulo" name="titulo" required placeholder="Ex.: Segurança pública e tecnologia"></div>
                <div class="admin-form-field full"><label for="tema">Tema / comando da proposta</label><textarea id="tema" name="tema" rows="3" required></textarea></div>
                <div class="admin-form-field full"><label for="apoio">Textos de apoio (opcional)</label><textarea id="apoio" name="apoio" rows="4"></textarea></div>
                <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit">Publicar proposta</button></div>
            </form>
        </section>
        <section class="admin-panel"><div class="admin-panel-title"><h2>Propostas</h2>
            <form method="post" style="display:flex;gap:8px;align-items:center"><input type="hidden" name="acao" value="limite"><label for="limite" class="admin-form-hint">Redações por aluno por semana (0 = livre)</label><input id="limite" type="number" name="limite" min="0" value="<?php echo $limite; ?>" style="width:70px"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Salvar</button></form></div>
            <?php foreach ($propostas as $p): ?>
                <div class="rd-linha"><span><strong><?php echo admin_esc((string)$p['titulo']); ?></strong> · <?php echo (int)$p['envios']; ?> envio(s)</span><span class="admin-form-hint"><?php echo $p['ativa'] ? 'Visível para alunos' : 'Oculta'; ?></span>
                    <form method="post"><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit"><?php echo $p['ativa'] ? 'Ocultar' : 'Mostrar'; ?></button></form></div>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php admin_cursos_scripts(); ?>
</body>
</html>
