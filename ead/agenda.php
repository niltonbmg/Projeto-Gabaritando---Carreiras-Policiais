<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/includes/notificacoes.php';

if (!function_exists('agenda_esc')) {
    function agenda_esc(?string $texto): string
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('agenda_data_segura')) {
    function agenda_data_segura(?string $data): DateTimeImmutable
    {
        if ($data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
            if ($obj instanceof DateTimeImmutable && $obj->format('Y-m-d') === $data) return $obj;
        }
        return new DateTimeImmutable('today');
    }
}

if (!function_exists('agenda_domingo')) {
    function agenda_domingo(DateTimeImmutable $data): DateTimeImmutable
    {
        return $data->modify('-' . (int)$data->format('w') . ' days');
    }
}

if (!function_exists('agenda_url')) {
    function agenda_url(DateTimeImmutable $data, string $view, int $cursoId = 0): string
    {
        $url = 'agenda.php?data=' . rawurlencode($data->format('Y-m-d')) . '&view=' . rawurlencode($view);
        if ($cursoId > 0) $url .= '&curso_id=' . $cursoId;
        return $url;
    }
}

if (!function_exists('agenda_periodo')) {
    function agenda_periodo(DateTimeImmutable $inicio, DateTimeImmutable $fim): string
    {
        $meses = [1 => 'jan.', 'fev.', 'mar.', 'abr.', 'mai.', 'jun.', 'jul.', 'ago.', 'set.', 'out.', 'nov.', 'dez.'];
        return $meses[(int)$inicio->format('n')] . ' ' . (int)$inicio->format('d') . ' - ' . $meses[(int)$fim->format('n')] . ' ' . (int)$fim->format('d') . ', ' . $fim->format('Y');
    }
}

if (!function_exists('agenda_mes_nome')) {
    function agenda_mes_nome(int $mes): string
    {
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        return $meses[$mes] ?? '';
    }
}

if (!function_exists('agenda_data_extenso')) {
    function agenda_data_extenso(?string $data): string
    {
        $obj = agenda_data_segura($data);
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return $dias[(int)$obj->format('w')] . ', ' . (int)$obj->format('d') . ' de ' . $meses[(int)$obj->format('n')] . ' de ' . $obj->format('Y');
    }
}

if (!function_exists('agenda_horario_evento')) {
    function agenda_horario_evento(?string $inicio, ?string $fim): string
    {
        $inicio = trim((string)$inicio);
        $fim = trim((string)$fim);
        if ($inicio === '') return 'Horário livre';
        $texto = substr($inicio, 0, 5);
        if ($fim !== '') $texto .= ' às ' . substr($fim, 0, 5);
        return $texto;
    }
}

if (!function_exists('agenda_link_seguro')) {
    function agenda_link_seguro(?string $link): string
    {
        $link = trim((string)$link);
        if ($link === '') return '';
        if (preg_match('/^https?:\/\//i', $link) && filter_var($link, FILTER_VALIDATE_URL)) return $link;
        if (!str_contains($link, ':') && preg_match('/^[a-zA-Z0-9_\-\.\/\?=&%#]+$/', $link)) return $link;
        return '';
    }
}

$usuarioId = auth_user_id();
$view = (string)($_GET['view'] ?? 'semana');
if (!in_array($view, ['semana', 'mes'], true)) $view = 'semana';
$dataSelecionada = agenda_data_segura($_GET['data'] ?? null);
$hoje = new DateTimeImmutable('today');
$cursoId = max(0, (int)($_GET['curso_id'] ?? 0));

if (isset($_GET['hoje'])) $dataSelecionada = $hoje;

if ($view === 'mes') {
    $inicioMes = $dataSelecionada->modify('first day of this month');
    $fimMes = $dataSelecionada->modify('last day of this month');
    $inicioPeriodo = agenda_domingo($inicioMes);
    $fimPeriodo = $fimMes->modify('+' . (6 - (int)$fimMes->format('w')) . ' days');
    $anterior = $dataSelecionada->modify('-1 month');
    $proximo = $dataSelecionada->modify('+1 month');
    $rotuloPeriodo = agenda_mes_nome((int)$dataSelecionada->format('n')) . ' de ' . $dataSelecionada->format('Y');
} else {
    $inicioPeriodo = agenda_domingo($dataSelecionada);
    $fimPeriodo = $inicioPeriodo->modify('+6 days');
    $anterior = $inicioPeriodo->modify('-7 days');
    $proximo = $inicioPeriodo->modify('+7 days');
    $rotuloPeriodo = agenda_periodo($inicioPeriodo, $fimPeriodo);
}

$diasPeriodo = [];
$cursor = $inicioPeriodo;
while ($cursor <= $fimPeriodo) {
    $diasPeriodo[] = $cursor;
    $cursor = $cursor->modify('+1 day');
}

$nomesSemana = ['dom.', 'seg.', 'ter.', 'qua.', 'qui.', 'sex.', 'sáb.'];
$eventosPorData = [];
$eventosDetalhes = [];
$cursosFiltro = [];
$totalEventos = 0;
$erroAgenda = '';

try {
    $pdo = db();
    gab_notif_bootstrap($pdo);
    $acesso = gab_notif_cursos_permitidos($pdo, $usuarioId);

    $paramsCursos = [];
    $filtroCursos = '';
    if (!empty($acesso['restrito']) && !empty($acesso['ids'])) {
        $ph = [];
        foreach (array_values($acesso['ids']) as $idx => $id) {
            $key = ':curso_filtro_' . $idx;
            $ph[] = $key;
            $paramsCursos[$key] = (int)$id;
        }
        $filtroCursos = ' AND id IN (' . implode(',', $ph) . ')';
        if ($cursoId > 0 && !in_array($cursoId, array_map('intval', $acesso['ids']), true)) $cursoId = 0;
    }

    $stmtCursos = $pdo->prepare("SELECT id, nome FROM admin_cursos WHERE status = 'ativo' {$filtroCursos} ORDER BY ordem ASC, nome ASC");
    $stmtCursos->execute($paramsCursos);
    $cursosFiltro = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

    $params = [
        ':inicio' => $inicioPeriodo->format('Y-m-d'),
        ':fim' => $fimPeriodo->format('Y-m-d'),
    ];
    $filtroAcesso = '';
    if (!empty($acesso['restrito']) && !empty($acesso['ids'])) {
        $ph = [];
        foreach (array_values($acesso['ids']) as $idx => $id) {
            $key = ':acesso_' . $idx;
            $ph[] = $key;
            $params[$key] = (int)$id;
        }
        $filtroAcesso = ' AND e.curso_id IN (' . implode(',', $ph) . ')';
    }
    $filtroCursoSelecionado = '';
    if ($cursoId > 0) {
        $filtroCursoSelecionado = ' AND e.curso_id = :curso_id';
        $params[':curso_id'] = $cursoId;
    }

    $stmt = $pdo->prepare("SELECT e.*, c.nome AS curso_nome
        FROM admin_agenda_eventos e
        INNER JOIN admin_cursos c ON c.id = e.curso_id AND c.status = 'ativo'
        WHERE e.status = 'ativo'
          AND e.data_evento BETWEEN :inicio AND :fim
          {$filtroAcesso}{$filtroCursoSelecionado}
        ORDER BY e.data_evento ASC, COALESCE(e.hora_inicio, '23:59:59') ASC, e.id ASC");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $evento) {
        $chave = (string)$evento['data_evento'];
        $eventosPorData[$chave][] = $evento;

        $eventoId = (int)($evento['id'] ?? 0);
        $linkSeguro = agenda_link_seguro((string)($evento['link_evento'] ?? ''));
        $eventosDetalhes[(string)$eventoId] = [
            'id' => $eventoId,
            'titulo' => trim((string)($evento['titulo'] ?? 'Evento')),
            'curso' => trim((string)($evento['curso_nome'] ?? 'Curso')),
            'descricao' => trim((string)($evento['descricao'] ?? '')),
            'data' => agenda_data_extenso((string)($evento['data_evento'] ?? '')),
            'horario' => agenda_horario_evento($evento['hora_inicio'] ?? null, $evento['hora_fim'] ?? null),
            'local' => trim((string)($evento['local_evento'] ?? '')),
            'link' => $linkSeguro,
            'externo' => $linkSeguro !== '' && (bool)preg_match('/^https?:\/\//i', $linkSeguro),
        ];
        $totalEventos++;
    }
} catch (Throwable $e) {
    error_log('[Agenda] ' . $e->getMessage());
    $erroAgenda = 'Não foi possível carregar os eventos agora.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/ead/favicon.png">
    <title>Agenda | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root{--yellow:#fab528;--yellow-dark:#d89a08;--page:#f5f5f6;--panel:#fff;--text:#111827;--muted:#667085;--line:#d9d9d9}
        *{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;min-height:100%}body{font-family:'Ubuntu',Arial,sans-serif;background:var(--page);color:var(--text)}a{text-decoration:none;color:inherit}button,select{font-family:inherit}
        .agenda-page{width:100%;min-height:calc(100vh - 96px);background:var(--page);padding:15px 12px 34px}.agenda-card{width:100%;max-width:1228px;margin:0 auto;background:#fafafa;border-radius:7px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .agenda-titlebar{min-height:80px;background:var(--yellow);display:flex;align-items:center;justify-content:center;position:relative;color:#fff}.agenda-titlebar h1{font-size:2rem;font-weight:700;letter-spacing:.01em}.agenda-titlebar-icon{position:absolute;right:28px;top:50%;transform:translateY(-50%);color:#fff}.agenda-titlebar-icon svg{width:28px;height:28px;stroke-width:2.8}
        .agenda-body{padding:22px 35px 28px;background:#fafafa}.agenda-toolbar{display:grid;grid-template-columns:330px 1fr 310px;gap:24px;align-items:center;margin-bottom:20px}
        .course-filter{display:grid;gap:6px}.course-filter label{display:flex;align-items:center;gap:8px;font-size:1.02rem;color:#111}.course-filter label svg{width:18px;height:18px;color:var(--yellow-dark)}.course-filter select{width:100%;height:34px;border:1px solid #d0d5dd;border-radius:8px;background:#fff;color:#344054;padding:0 34px 0 10px;font-weight:700;outline:none}
        .period-nav{display:flex;align-items:center;justify-content:center;gap:22px;font-size:1rem;color:#111}.period-nav a{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;color:var(--yellow)}.period-nav svg{width:21px;height:21px}.period-label{min-width:230px;text-align:center;font-weight:500;letter-spacing:.02em}.calendar-icon{color:var(--yellow);display:inline-flex}.calendar-icon svg{width:25px;height:25px}
        .view-toggle{display:grid;grid-template-columns:1fr 1fr 1fr;background:#d3d3d3;border-radius:999px;overflow:hidden}.view-toggle a{min-height:25px;display:flex;align-items:center;justify-content:center;font-size:.86rem;font-weight:700;color:#fff}.view-toggle .today-link,.view-toggle .active{background:var(--yellow);color:#fff}
        .agenda-summary{display:flex;align-items:center;gap:9px;color:#667085;font-size:.88rem;font-weight:700;margin-bottom:14px}.agenda-summary svg{width:18px;height:18px;color:var(--yellow-dark)}
        .week-head{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid var(--line);border-bottom:0;border-radius:7px 7px 0 0;overflow:hidden;background:#f7f7f7}.week-head div{min-height:66px;display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:1px solid var(--line);font-weight:700;color:#222}.week-head div:last-child{border-right:0}.week-head small{font-size:.96rem}.week-head strong{font-size:1.36rem;margin-top:3px}
        .calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);border-left:1px solid var(--line);border-top:1px solid var(--line)}.day-cell{min-height:145px;background:#fbfbfb;border-right:1px solid var(--line);border-bottom:1px solid var(--line);padding:10px;position:relative}.agenda-card.is-week .day-cell{min-height:335px}.day-cell.is-today{box-shadow:inset 0 0 0 2px rgba(250,181,40,.65)}.day-cell.is-other-month{background:#f2f2f2;opacity:.74}.day-list{display:grid;gap:10px}
        .event-card{position:relative;display:block;border:0;width:100%;text-align:left;border-radius:4px;padding:10px;color:#fff;min-height:92px;background:#2468b5;overflow:hidden;cursor:pointer;transition:transform .16s ease,filter .16s ease,box-shadow .16s ease;font-family:inherit}.event-card:hover{transform:translateY(-1px);filter:brightness(.96)}.event-card:focus-visible{outline:3px solid rgba(250,181,40,.48);outline-offset:3px;box-shadow:0 8px 18px rgba(15,23,42,.2)}.event-card.event-color-1{background:#21b9c4}.event-card.event-color-2{background:#12b886}.event-card.event-color-3{background:#111}.event-card.event-color-4{background:#2468b5}.event-card.event-color-5{background:#7c3aed}.event-card.event-color-6{background:#d97706}.event-card.event-color-7{background:#dc2626}.event-card.event-color-8{background:#0891b2}
        .event-card strong{display:block;font-size:.82rem;line-height:1.22;text-transform:uppercase;color:#fff;margin-bottom:9px;padding-right:22px;word-break:break-word}.event-course{display:flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;opacity:.95;margin-bottom:8px}.event-course svg{width:13px;height:13px}.event-meta{display:flex;align-items:center;justify-content:space-between;gap:7px;color:#fff;font-size:.72rem;font-weight:700}.event-meta span{display:inline-flex;align-items:center;gap:4px;min-width:0}.event-meta svg{width:13px;height:13px;flex:0 0 auto}.event-meta em{font-style:normal;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.event-bell{position:absolute;right:8px;top:9px}.event-bell svg{width:17px;height:17px;color:#fff}
        .empty-period{min-height:70px;display:flex;align-items:center;justify-content:center;text-align:center;color:#667085;border:1px dashed #cfd4dc;border-radius:12px;background:#fff;margin:0 0 18px;padding:18px}.empty-period strong{display:block;color:#111827;margin-bottom:5px}.agenda-error{background:#fff1f0;color:#b42318;border:1px solid #fecdca;border-radius:10px;padding:13px 15px;margin:0 0 18px;font-weight:700}
        body.agenda-modal-open{overflow:hidden}.event-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .18s ease,visibility .18s ease}.event-modal.is-open{opacity:1;visibility:visible;pointer-events:auto}.event-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.68);backdrop-filter:blur(3px)}.event-modal__dialog{position:relative;width:min(620px,100%);max-height:min(760px,calc(100vh - 40px));overflow:auto;background:#fff;border-radius:18px;box-shadow:0 28px 80px rgba(0,0,0,.32);transform:translateY(12px) scale(.985);transition:transform .18s ease}.event-modal.is-open .event-modal__dialog{transform:translateY(0) scale(1)}.event-modal__head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:24px 24px 19px;background:linear-gradient(135deg,#fab528,#e4a116);color:#fff}.event-modal__heading{min-width:0}.event-modal__eyebrow{display:flex;align-items:center;gap:7px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;opacity:.92}.event-modal__eyebrow svg{width:16px;height:16px}.event-modal__title{font-size:1.45rem;line-height:1.2;word-break:break-word}.event-modal__close{width:40px;height:40px;flex:0 0 auto;border:1px solid rgba(255,255,255,.38);border-radius:10px;background:rgba(255,255,255,.15);color:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.event-modal__close:hover{background:rgba(255,255,255,.24)}.event-modal__close svg{width:21px;height:21px}.event-modal__body{padding:22px 24px 24px}.event-modal__meta{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:20px}.event-modal__meta-item{display:flex;align-items:flex-start;gap:10px;min-height:64px;padding:12px;border:1px solid #eaecf0;border-radius:12px;background:#f9fafb}.event-modal__meta-item svg{width:19px;height:19px;flex:0 0 auto;color:#d89a08;margin-top:1px}.event-modal__meta-item span{display:grid;gap:3px;min-width:0}.event-modal__meta-item small{font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:#667085;font-weight:700}.event-modal__meta-item strong{font-size:.9rem;color:#111827;line-height:1.35;word-break:break-word}.event-modal__description{border-top:1px solid #eaecf0;padding-top:19px}.event-modal__description h3{display:flex;align-items:center;gap:8px;font-size:1rem;margin-bottom:10px}.event-modal__description h3 svg{width:19px;height:19px;color:#d89a08}.event-modal__description p{color:#475467;line-height:1.65;white-space:pre-wrap;word-break:break-word}.event-modal__description p.is-empty{font-style:italic;color:#98a2b3}.event-modal__actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:23px}.event-modal__button{min-height:43px;border-radius:10px;padding:0 16px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:.92rem;font-weight:700;cursor:pointer;border:1px solid #d0d5dd;background:#fff;color:#344054}.event-modal__button:hover{background:#f9fafb}.event-modal__button--primary{border-color:#fab528;background:#fab528;color:#111827}.event-modal__button--primary:hover{background:#e9a91e}.event-modal__button svg{width:18px;height:18px}.event-modal__button.is-hidden{display:none}
        @media(max-width:980px){.agenda-page{padding:12px}.agenda-titlebar{min-height:70px}.agenda-titlebar h1{font-size:1.55rem}.agenda-titlebar-icon{right:18px}.agenda-body{padding:18px 14px 24px}.agenda-toolbar{grid-template-columns:1fr;gap:16px}.period-label{min-width:0;font-size:.95rem}.period-nav{gap:10px}.week-head{display:none}.calendar-grid{grid-template-columns:1fr;border:0;gap:10px}.day-cell,.agenda-card.is-week .day-cell{min-height:auto;border:1px solid var(--line);border-radius:10px;background:#fff}.day-cell::before{content:attr(data-dia);display:block;font-weight:700;color:#111;margin-bottom:10px}.view-toggle{max-width:310px;margin:0 auto;width:100%}.event-card{min-height:84px}}
        @media(max-width:520px){.agenda-body{padding-left:10px;padding-right:10px}.agenda-toolbar{gap:14px}.period-nav{flex-wrap:wrap}.period-label{order:-1;width:100%;font-weight:700}.event-modal{padding:10px;align-items:flex-end}.event-modal__dialog{width:100%;max-height:calc(100dvh - 10px);border-radius:18px 18px 0 0}.event-modal__head{padding:20px 18px 16px}.event-modal__title{font-size:1.22rem}.event-modal__body{padding:18px}.event-modal__meta{grid-template-columns:1fr;gap:8px}.event-modal__meta-item{min-height:56px}.event-modal__actions{display:grid;grid-template-columns:1fr}.event-modal__button{width:100%}}
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/barra-superior.php'; ?>
    <main class="agenda-page">
        <section class="agenda-card is-<?php echo agenda_esc($view); ?>" aria-label="Agenda de eventos">
            <header class="agenda-titlebar"><h1>Agenda</h1><span class="agenda-titlebar-icon"><i data-lucide="calendar-days"></i></span></header>
            <div class="agenda-body">
                <?php if ($erroAgenda !== ''): ?><div class="agenda-error"><?php echo agenda_esc($erroAgenda); ?></div><?php endif; ?>

                <div class="agenda-toolbar">
                    <form class="course-filter" method="get" id="agendaCourseFilter">
                        <input type="hidden" name="data" value="<?php echo agenda_esc($dataSelecionada->format('Y-m-d')); ?>">
                        <input type="hidden" name="view" value="<?php echo agenda_esc($view); ?>">
                        <label for="cursoAgenda"><i data-lucide="graduation-cap"></i>Curso</label>
                        <select id="cursoAgenda" name="curso_id" onchange="this.form.submit()">
                            <option value="0">Todos os cursos</option>
                            <?php foreach ($cursosFiltro as $curso): ?><option value="<?php echo (int)$curso['id']; ?>" <?php echo $cursoId === (int)$curso['id'] ? 'selected' : ''; ?>><?php echo agenda_esc((string)$curso['nome']); ?></option><?php endforeach; ?>
                        </select>
                    </form>

                    <nav class="period-nav" aria-label="Navegação da agenda">
                        <a href="<?php echo agenda_esc(agenda_url($anterior, $view, $cursoId)); ?>" aria-label="Período anterior"><i data-lucide="chevron-left"></i></a>
                        <span class="period-label"><?php echo agenda_esc($rotuloPeriodo); ?></span>
                        <a href="<?php echo agenda_esc(agenda_url($proximo, $view, $cursoId)); ?>" aria-label="Próximo período"><i data-lucide="chevron-right"></i></a>
                        <span class="calendar-icon"><i data-lucide="calendar-range"></i></span>
                    </nav>

                    <div class="view-toggle" aria-label="Visualização">
                        <a class="today-link" href="<?php echo agenda_esc(agenda_url($hoje, 'semana', $cursoId)); ?>">Hoje</a>
                        <a class="<?php echo $view === 'semana' ? 'active' : ''; ?>" href="<?php echo agenda_esc(agenda_url($dataSelecionada, 'semana', $cursoId)); ?>">Semana</a>
                        <a class="<?php echo $view === 'mes' ? 'active' : ''; ?>" href="<?php echo agenda_esc(agenda_url($dataSelecionada, 'mes', $cursoId)); ?>">Mês</a>
                    </div>
                </div>

                <div class="agenda-summary"><i data-lucide="bell-ring"></i><?php echo $totalEventos; ?> evento<?php echo $totalEventos === 1 ? '' : 's'; ?> neste período</div>

                <?php if ($totalEventos === 0): ?><div class="empty-period"><div><strong>Nenhum evento cadastrado para este período.</strong><span>Quando o administrador criar um evento vinculado ao seu curso, ele aparecerá aqui e no sino de notificações.</span></div></div><?php endif; ?>

                <div class="week-head" aria-hidden="true">
                    <?php for ($i = 0; $i < 7; $i++): $dataCabecalho = $inicioPeriodo->modify('+' . $i . ' days'); ?>
                        <div><small><?php echo agenda_esc($nomesSemana[$i]); ?></small><strong><?php echo (int)$dataCabecalho->format('d'); ?></strong></div>
                    <?php endfor; ?>
                </div>

                <div class="calendar-grid">
                    <?php foreach ($diasPeriodo as $indiceDia => $dataDia):
                        $chave = $dataDia->format('Y-m-d');
                        $eventosDia = $eventosPorData[$chave] ?? [];
                        $isToday = $chave === $hoje->format('Y-m-d');
                        $outroMes = $view === 'mes' && $dataDia->format('m') !== $dataSelecionada->format('m');
                        $diaSemana = (int)$dataDia->format('w');
                    ?>
                        <article class="day-cell<?php echo $isToday ? ' is-today' : ''; ?><?php echo $outroMes ? ' is-other-month' : ''; ?>" data-dia="<?php echo agenda_esc($nomesSemana[$diaSemana] . ' ' . (int)$dataDia->format('d')); ?>">
                            <div class="day-list">
                                <?php foreach ($eventosDia as $indiceEvento => $evento):
                                    $cor = trim((string)($evento['cor'] ?? ''));
                                    $classe = 'event-color-' . (($indiceDia + $indiceEvento) % 8 + 1);
                                    $hora = !empty($evento['hora_inicio']) ? substr((string)$evento['hora_inicio'], 0, 5) : 'Horário livre';
                                    $local = trim((string)($evento['local_evento'] ?? '')) ?: 'Evento do curso';
                                ?>
                                    <button type="button" id="evento-<?php echo (int)$evento['id']; ?>" class="event-card <?php echo agenda_esc($classe); ?>" data-evento-id="<?php echo (int)$evento['id']; ?>" aria-haspopup="dialog" aria-controls="agendaEventModal" <?php echo $cor !== '' ? 'style="background:' . agenda_esc($cor) . '"' : ''; ?>>
                                        <span class="event-bell"><i data-lucide="bell"></i></span>
                                        <strong><?php echo agenda_esc((string)$evento['titulo']); ?></strong>
                                        <span class="event-course"><i data-lucide="graduation-cap"></i><?php echo agenda_esc((string)$evento['curso_nome']); ?></span>
                                        <span class="event-meta"><span><i data-lucide="clock-3"></i><?php echo agenda_esc($hora); ?></span><span><i data-lucide="map-pin"></i><em><?php echo agenda_esc($local); ?></em></span></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <div class="event-modal" id="agendaEventModal" aria-hidden="true">
        <div class="event-modal__backdrop" data-agenda-modal-close></div>
        <section class="event-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="agendaModalTitle" tabindex="-1">
            <header class="event-modal__head">
                <div class="event-modal__heading">
                    <span class="event-modal__eyebrow"><i data-lucide="calendar-check-2"></i>Detalhes do evento</span>
                    <h2 class="event-modal__title" id="agendaModalTitle">Evento</h2>
                </div>
                <button type="button" class="event-modal__close" data-agenda-modal-close aria-label="Fechar detalhes"><i data-lucide="x"></i></button>
            </header>
            <div class="event-modal__body">
                <div class="event-modal__meta">
                    <div class="event-modal__meta-item"><i data-lucide="graduation-cap"></i><span><small>Curso</small><strong id="agendaModalCurso">—</strong></span></div>
                    <div class="event-modal__meta-item"><i data-lucide="calendar-days"></i><span><small>Data</small><strong id="agendaModalData">—</strong></span></div>
                    <div class="event-modal__meta-item"><i data-lucide="clock-3"></i><span><small>Horário</small><strong id="agendaModalHorario">—</strong></span></div>
                    <div class="event-modal__meta-item"><i data-lucide="map-pin"></i><span><small>Local</small><strong id="agendaModalLocal">—</strong></span></div>
                </div>
                <div class="event-modal__description">
                    <h3><i data-lucide="notebook-tabs"></i>Descrição e orientações</h3>
                    <p id="agendaModalDescricao">—</p>
                </div>
                <div class="event-modal__actions">
                    <button type="button" class="event-modal__button" data-agenda-modal-close><i data-lucide="x"></i>Fechar</button>
                    <a class="event-modal__button event-modal__button--primary is-hidden" id="agendaModalLink" href="#"><i data-lucide="external-link"></i>Ir para o evento</a>
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="agendaEventData"><?php echo json_encode($eventosDetalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script>
        if (window.lucide) window.lucide.createIcons();

        (function () {
            const modal = document.getElementById('agendaEventModal');
            const dialog = modal ? modal.querySelector('.event-modal__dialog') : null;
            const dadosEl = document.getElementById('agendaEventData');
            let dados = {};
            let ultimoGatilho = null;

            try {
                dados = dadosEl ? JSON.parse(dadosEl.textContent || '{}') : {};
            } catch (erro) {
                dados = {};
            }

            function preencher(id, valor) {
                const campo = document.getElementById(id);
                if (campo) campo.textContent = valor || '—';
            }

            function abrirModal(eventoId, gatilho) {
                if (!modal || !dialog) return;
                const evento = dados[String(eventoId)];
                if (!evento) return;

                ultimoGatilho = gatilho || null;
                preencher('agendaModalTitle', evento.titulo);
                preencher('agendaModalCurso', evento.curso);
                preencher('agendaModalData', evento.data);
                preencher('agendaModalHorario', evento.horario);
                preencher('agendaModalLocal', evento.local || 'Não informado');

                const descricao = document.getElementById('agendaModalDescricao');
                if (descricao) {
                    const texto = String(evento.descricao || '').trim();
                    descricao.textContent = texto || 'Nenhuma orientação adicional foi cadastrada para este evento.';
                    descricao.classList.toggle('is-empty', texto === '');
                }

                const link = document.getElementById('agendaModalLink');
                if (link) {
                    const url = String(evento.link || '').trim();
                    link.classList.toggle('is-hidden', url === '');
                    link.setAttribute('href', url || '#');
                    if (url && evento.externo) {
                        link.setAttribute('target', '_blank');
                        link.setAttribute('rel', 'noopener');
                    } else {
                        link.removeAttribute('target');
                        link.removeAttribute('rel');
                    }
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('agenda-modal-open');
                window.setTimeout(function () {
                    const fechar = modal.querySelector('.event-modal__close');
                    if (fechar) fechar.focus();
                    else dialog.focus();
                }, 30);
            }

            function fecharModal() {
                if (!modal || !modal.classList.contains('is-open')) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('agenda-modal-open');
                if (ultimoGatilho && typeof ultimoGatilho.focus === 'function') ultimoGatilho.focus();
                ultimoGatilho = null;
            }

            document.querySelectorAll('[data-evento-id]').forEach(function (card) {
                card.addEventListener('click', function () {
                    abrirModal(card.getAttribute('data-evento-id'), card);
                });
            });

            document.querySelectorAll('[data-agenda-modal-close]').forEach(function (botao) {
                botao.addEventListener('click', fecharModal);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') fecharModal();
            });
        })();
    </script>
</body>
</html>
