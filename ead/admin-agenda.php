<?php
// Cadastro administrativo dos eventos exibidos na agenda do aluno.
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/notificacoes.php';

$erro = '';
$sucesso = '';
$pdo = null;
$eventoEdicao = null;
$modal = (string)($_GET['modal'] ?? '');

if (!function_exists('admin_agenda_data')) {
    function admin_agenda_data(string $valor): string
    {
        $valor = trim($valor);
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if (!$data || $data->format('Y-m-d') !== $valor) {
            throw new RuntimeException('Informe uma data válida para o evento.');
        }
        return $valor;
    }
}

if (!function_exists('admin_agenda_hora')) {
    function admin_agenda_hora(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') return null;
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $valor)) {
            throw new RuntimeException('Informe um horário válido.');
        }
        return $valor . ':00';
    }
}

if (!function_exists('admin_agenda_cor')) {
    function admin_agenda_cor(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') return null;
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            throw new RuntimeException('A cor do evento é inválida.');
        }
        return strtolower($valor);
    }
}

if (!function_exists('admin_agenda_link_seguro')) {
    function admin_agenda_link_seguro(?string $link): string
    {
        $link = trim((string)$link);
        if ($link === '') return '';
        if (preg_match('/^https?:\/\//i', $link) && filter_var($link, FILTER_VALIDATE_URL)) return $link;
        if (!str_contains($link, ':') && preg_match('/^[a-zA-Z0-9_\-\.\/\?=&%#]+$/', $link)) return $link;
        return '';
    }
}

try {
    $pdo = admin_cursos_db();
    admin_cursos_bootstrap($pdo);
    gab_notif_bootstrap($pdo);
} catch (Throwable $e) {
    $erro = 'Erro ao preparar a agenda: ' . $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar_evento') {
            $eventoId = admin_cursos_int($_POST['evento_id'] ?? 0);
            $cursoId = admin_cursos_int($_POST['curso_id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $dataEvento = admin_agenda_data((string)($_POST['data_evento'] ?? ''));
            $horaInicio = admin_agenda_hora((string)($_POST['hora_inicio'] ?? ''));
            $horaFim = admin_agenda_hora((string)($_POST['hora_fim'] ?? ''));
            $localEvento = trim((string)($_POST['local_evento'] ?? ''));
            $linkEvento = trim((string)($_POST['link_evento'] ?? ''));
            $cor = admin_agenda_cor((string)($_POST['cor'] ?? ''));
            $status = in_array((string)($_POST['status'] ?? 'ativo'), ['ativo', 'inativo'], true)
                ? (string)$_POST['status']
                : 'ativo';

            if ($cursoId <= 0) throw new RuntimeException('Selecione o curso vinculado ao evento.');
            if ($titulo === '') throw new RuntimeException('Informe o título do evento.');
            if (mb_strlen($titulo) > 180) throw new RuntimeException('O título deve ter no máximo 180 caracteres.');
            if ($horaInicio !== null && $horaFim !== null && strcmp($horaFim, $horaInicio) <= 0) {
                throw new RuntimeException('O horário final deve ser depois do horário inicial.');
            }
            if ($linkEvento !== '' && admin_agenda_link_seguro($linkEvento) === '') {
                throw new RuntimeException('Informe um link HTTP/HTTPS válido ou uma página interna.');
            }

            $stmtCurso = $pdo->prepare("SELECT id FROM admin_cursos WHERE id = :id AND status = 'ativo' LIMIT 1");
            $stmtCurso->execute([':id' => $cursoId]);
            if (!$stmtCurso->fetch()) throw new RuntimeException('Curso não encontrado ou inativo.');

            $dados = [
                ':curso_id' => $cursoId,
                ':titulo' => $titulo,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':data_evento' => $dataEvento,
                ':hora_inicio' => $horaInicio,
                ':hora_fim' => $horaFim,
                ':local_evento' => $localEvento !== '' ? $localEvento : null,
                ':link_evento' => $linkEvento !== '' ? $linkEvento : null,
                ':cor' => $cor,
                ':status' => $status,
            ];

            if ($eventoId > 0) {
                $dados[':id'] = $eventoId;
                $stmt = $pdo->prepare('UPDATE admin_agenda_eventos SET curso_id = :curso_id, titulo = :titulo, descricao = :descricao, data_evento = :data_evento, hora_inicio = :hora_inicio, hora_fim = :hora_fim, local_evento = :local_evento, link_evento = :link_evento, cor = :cor, status = :status WHERE id = :id LIMIT 1');
                $stmt->execute($dados);
                header('Location: admin-agenda.php?sucesso=evento_atualizado');
                exit;
            }

            $stmt = $pdo->prepare('INSERT INTO admin_agenda_eventos (curso_id, titulo, descricao, data_evento, hora_inicio, hora_fim, local_evento, link_evento, cor, status) VALUES (:curso_id, :titulo, :descricao, :data_evento, :hora_inicio, :hora_fim, :local_evento, :link_evento, :cor, :status)');
            $stmt->execute($dados);
            header('Location: admin-agenda.php?sucesso=evento_criado');
            exit;
        }

        if ($acao === 'excluir_evento') {
            $eventoId = admin_cursos_int($_POST['evento_id'] ?? 0);
            if ($eventoId <= 0) throw new RuntimeException('Evento inválido.');
            $stmt = $pdo->prepare('DELETE FROM admin_agenda_eventos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $eventoId]);
            header('Location: admin-agenda.php?sucesso=evento_excluido');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$mensagens = [
    'evento_criado' => 'Evento criado e enviado para a agenda dos alunos.',
    'evento_atualizado' => 'Evento atualizado com sucesso.',
    'evento_excluido' => 'Evento excluído da agenda.',
];
$sucessoChave = (string)($_GET['sucesso'] ?? '');
if ($sucessoChave !== '' && isset($mensagens[$sucessoChave])) $sucesso = $mensagens[$sucessoChave];

$cursos = [];
$eventos = [];
$totais = ['ativos' => 0, 'proximos' => 0, 'hoje' => 0];

if ($pdo instanceof PDO) {
    try {
        $cursos = $pdo->query("SELECT id, nome FROM admin_cursos WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);
        $eventos = $pdo->query("SELECT e.*, c.nome AS curso_nome
            FROM admin_agenda_eventos e
            INNER JOIN admin_cursos c ON c.id = e.curso_id
            ORDER BY e.data_evento DESC, COALESCE(e.hora_inicio, '23:59:59') DESC, e.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $totais['ativos'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_agenda_eventos WHERE status = 'ativo'")->fetchColumn();
        $totais['proximos'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_agenda_eventos WHERE status = 'ativo' AND data_evento >= CURDATE()")->fetchColumn();
        $totais['hoje'] = (int)$pdo->query("SELECT COUNT(*) FROM admin_agenda_eventos WHERE status = 'ativo' AND data_evento = CURDATE()")->fetchColumn();

        $editarId = admin_cursos_int($_GET['editar'] ?? 0);
        if ($editarId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_agenda_eventos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $editarId]);
            $eventoEdicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($eventoEdicao) $modal = 'editar';
        }
    } catch (Throwable $e) {
        if ($erro === '') $erro = 'Não foi possível carregar os eventos: ' . $e->getMessage();
    }
}

$mostrarFormulario = $modal === 'criar' || ($modal === 'editar' && is_array($eventoEdicao));
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/ead/favicon.png">
    <title>Admin Agenda | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .agenda-form-card{padding:0;margin-bottom:20px}.agenda-form-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:20px 22px;border-bottom:1px solid #eef0f3}.agenda-form-head h2{font-size:1.2rem;color:#111827;margin-bottom:5px}.agenda-form-head p{color:#667085;font-size:.92rem;line-height:1.45}.agenda-form-body{padding:22px}.agenda-color-row{display:flex;align-items:center;gap:10px}.agenda-color-row input[type=color]{width:52px;height:44px;padding:4px;border:1px solid #d0d5dd;border-radius:9px;background:#fff}.agenda-course{display:flex;align-items:center;gap:8px;font-weight:700;color:#344054}.agenda-course svg{width:17px;height:17px;color:#d89d00}.agenda-date{display:grid;gap:4px}.agenda-date strong{color:#111827}.agenda-date small{color:#667085}.agenda-title-cell{display:grid;gap:5px}.agenda-title-cell strong{color:#111827}.agenda-title-cell span{max-width:330px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#667085;font-size:.84rem}.agenda-dot{width:12px;height:12px;border-radius:50%;display:inline-block;border:2px solid rgba(0,0,0,.08);margin-right:7px;vertical-align:-1px}.agenda-status{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700}.agenda-status.ativo{background:#ecfdf3;color:#067647}.agenda-status.inativo{background:#f2f4f7;color:#667085}.agenda-actions{display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}.agenda-delete{display:inline}.agenda-empty{margin-top:18px}.agenda-link-preview{color:#175cd3;font-size:.82rem;max-width:260px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}@media(max-width:760px){.agenda-form-head{display:grid}.agenda-form-body{padding:18px}.agenda-actions{justify-content:flex-start}}
    </style>
</head>
<body>
    <?php if (is_file($topbarInclude)) include $topbarInclude; ?>
    <div class="admin-dashboard-shell">
        <?php if (is_file($menuBarInclude)) include $menuBarInclude; ?>
        <main class="admin-dashboard-main">
            <section class="admin-page-header">
                <div class="admin-page-heading">
                    <h1>Agenda</h1>
                    <p>Crie eventos vinculados a um curso. O aluno recebe a notificação no sino e visualiza o evento na agenda.</p>
                </div>
                <div class="admin-page-actions">
                    <a class="admin-btn admin-btn-light" href="agenda.php" target="_blank"><i data-lucide="external-link"></i>Ver agenda do aluno</a>
                    <a class="admin-btn admin-btn-primary" href="admin-agenda.php?modal=criar"><i data-lucide="calendar-plus"></i>Novo evento</a>
                </div>
            </section>

            <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
            <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card"><div><span>Eventos ativos</span><strong><?php echo $totais['ativos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="calendar-days"></i></div></article>
                <article class="admin-stat-card"><div><span>Próximos eventos</span><strong><?php echo $totais['proximos']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="clock-3"></i></div></article>
                <article class="admin-stat-card"><div><span>Eventos hoje</span><strong><?php echo $totais['hoje']; ?></strong></div><div class="admin-stat-icon"><i data-lucide="bell-ring"></i></div></article>
            </section>

            <?php if ($mostrarFormulario): ?>
                <section class="admin-card agenda-form-card">
                    <div class="agenda-form-head">
                        <div><h2><?php echo $eventoEdicao ? 'Editar evento' : 'Criar evento'; ?></h2><p>Defina curso, data, horário, local e link opcional.</p></div>
                        <a class="admin-btn admin-btn-light admin-btn-small" href="admin-agenda.php"><i data-lucide="x"></i>Fechar</a>
                    </div>
                    <div class="agenda-form-body">
                        <form method="post" class="admin-form-grid">
                            <input type="hidden" name="acao" value="salvar_evento">
                            <input type="hidden" name="evento_id" value="<?php echo (int)($eventoEdicao['id'] ?? 0); ?>">

                            <div class="admin-form-field">
                                <label for="curso_id">Curso vinculado</label>
                                <select id="curso_id" name="curso_id" required>
                                    <option value="">Selecione o curso</option>
                                    <?php foreach ($cursos as $curso): ?>
                                        <option value="<?php echo (int)$curso['id']; ?>" <?php echo (int)($eventoEdicao['curso_id'] ?? 0) === (int)$curso['id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$curso['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="admin-form-field">
                                <label for="titulo">Título do evento</label>
                                <input id="titulo" name="titulo" maxlength="180" required value="<?php echo admin_esc((string)($eventoEdicao['titulo'] ?? '')); ?>" placeholder="Ex.: Aula ao vivo de revisão">
                            </div>

                            <div class="admin-form-field">
                                <label for="data_evento">Data</label>
                                <input id="data_evento" type="date" name="data_evento" required value="<?php echo admin_esc((string)($eventoEdicao['data_evento'] ?? date('Y-m-d'))); ?>">
                            </div>

                            <div class="admin-form-field">
                                <label>Horário</label>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                    <input type="time" name="hora_inicio" value="<?php echo admin_esc(substr((string)($eventoEdicao['hora_inicio'] ?? ''), 0, 5)); ?>" aria-label="Hora inicial">
                                    <input type="time" name="hora_fim" value="<?php echo admin_esc(substr((string)($eventoEdicao['hora_fim'] ?? ''), 0, 5)); ?>" aria-label="Hora final">
                                </div>
                            </div>

                            <div class="admin-form-field">
                                <label for="local_evento">Local ou plataforma</label>
                                <input id="local_evento" name="local_evento" maxlength="180" value="<?php echo admin_esc((string)($eventoEdicao['local_evento'] ?? '')); ?>" placeholder="Ex.: Google Meet, auditório, sala virtual">
                            </div>

                            <div class="admin-form-field">
                                <label for="link_evento">Link do evento</label>
                                <input id="link_evento" name="link_evento" value="<?php echo admin_esc((string)($eventoEdicao['link_evento'] ?? '')); ?>" placeholder="https://... ou uma página interna">
                            </div>

                            <div class="admin-form-field">
                                <label for="cor">Cor do cartão</label>
                                <div class="agenda-color-row">
                                    <input id="cor" type="color" name="cor" value="<?php echo admin_esc((string)($eventoEdicao['cor'] ?? '#2468b5')); ?>">
                                    <span class="admin-form-hint">Use uma cor diferente para destacar o evento.</span>
                                </div>
                            </div>

                            <div class="admin-form-field">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="ativo" <?php echo (($eventoEdicao['status'] ?? 'ativo') === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="inativo" <?php echo (($eventoEdicao['status'] ?? '') === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>

                            <div class="admin-form-field full">
                                <label for="descricao">Descrição</label>
                                <textarea id="descricao" name="descricao" placeholder="Explique o objetivo do evento e as orientações para os alunos."><?php echo admin_esc((string)($eventoEdicao['descricao'] ?? '')); ?></textarea>
                            </div>

                            <div class="admin-form-actions">
                                <a class="admin-btn admin-btn-light" href="admin-agenda.php">Cancelar</a>
                                <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i><?php echo $eventoEdicao ? 'Salvar alterações' : 'Criar evento'; ?></button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Eventos cadastrados</h2><small><?php echo count($eventos); ?> registro(s)</small></div>
                <?php if (!$eventos): ?>
                    <div class="admin-empty agenda-empty"><div><i data-lucide="calendar-x"></i><h3>Nenhum evento cadastrado</h3><p>Crie o primeiro evento para ele aparecer na agenda dos alunos.</p></div></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Evento</th><th>Curso</th><th>Data e horário</th><th>Local / link</th><th>Status</th><th style="text-align:right">Ações</th></tr></thead>
                            <tbody>
                            <?php foreach ($eventos as $evento): ?>
                                <tr>
                                    <td><div class="agenda-title-cell"><strong><span class="agenda-dot" style="background:<?php echo admin_esc((string)($evento['cor'] ?: '#2468b5')); ?>"></span><?php echo admin_esc((string)$evento['titulo']); ?></strong><span><?php echo admin_esc(admin_cursos_texto_curto((string)($evento['descricao'] ?? ''), 100)); ?></span></div></td>
                                    <td><span class="agenda-course"><i data-lucide="graduation-cap"></i><?php echo admin_esc((string)$evento['curso_nome']); ?></span></td>
                                    <td><div class="agenda-date"><strong><?php echo date('d/m/Y', strtotime((string)$evento['data_evento'])); ?></strong><small><?php echo !empty($evento['hora_inicio']) ? substr((string)$evento['hora_inicio'], 0, 5) : 'Sem horário'; ?><?php echo !empty($evento['hora_fim']) ? ' às ' . substr((string)$evento['hora_fim'], 0, 5) : ''; ?></small></div></td>
                                    <td>
                                        <strong style="display:block;color:#344054;margin-bottom:4px"><?php echo admin_esc((string)($evento['local_evento'] ?: 'Não informado')); ?></strong>
                                        <?php $linkSeguroAdmin = admin_agenda_link_seguro((string)($evento['link_evento'] ?? '')); ?><?php if ($linkSeguroAdmin !== ''): ?><a class="agenda-link-preview" href="<?php echo admin_esc($linkSeguroAdmin); ?>" target="_blank" rel="noopener"><?php echo admin_esc($linkSeguroAdmin); ?></a><?php endif; ?>
                                    </td>
                                    <td><span class="agenda-status <?php echo admin_esc((string)$evento['status']); ?>"><?php echo $evento['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?></span></td>
                                    <td><div class="agenda-actions"><a class="admin-btn admin-btn-light admin-btn-small" href="admin-agenda.php?editar=<?php echo (int)$evento['id']; ?>"><i data-lucide="pencil"></i>Editar</a><form class="agenda-delete" method="post" onsubmit="return confirm('Excluir este evento da agenda?');"><input type="hidden" name="acao" value="excluir_evento"><input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>"><button class="admin-btn admin-btn-danger admin-btn-small" type="submit"><i data-lucide="trash-2"></i>Excluir</button></form></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php admin_cursos_scripts(); ?>
</body>
</html>
