<?php
// meu-concurso.php
// O aluno escolhe o concurso e o cargo e recebe TUDO do edital (aberto ou o anterior),
// com o Antigão explicando de forma estratégica: Hackeando o Edital, Hackeando a Prova
// e o Conteúdo programático (que é a base do curso). Visitar conta na missão do dia.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/authenticador.php';
auth_require_login();
require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/edital-jobs-lib.php';

$usuarioId = (int)auth_user_id();
$erro = '';
$pdo = db();
hack_bootstrap($pdo);
job_bootstrap($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'jornada') {
    hack_jornada_avancar($pdo, $usuarioId, (string)($_POST['etapa'] ?? 'preparacao'));
    header('Location: meu-concurso.php?jornada=ok');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'escolher') {
    $cargoId = (int)($_POST['cargo_id'] ?? 0);
    $st = $pdo->prepare('SELECT concurso_id FROM concurso_cargos WHERE id = :id');
    $st->execute([':id' => $cargoId]);
    $concId = (int)$st->fetchColumn();
    if ($concId > 0) {
        $pdo->prepare('INSERT INTO aluno_concurso (usuario_id, concurso_id, cargo_id) VALUES (:u, :c, :g) ON DUPLICATE KEY UPDATE concurso_id = VALUES(concurso_id), cargo_id = VALUES(cargo_id)')
            ->execute([':u' => $usuarioId, ':c' => $concId, ':g' => $cargoId]);
        unset($_SESSION['gab_missao_' . $usuarioId]);
    }
    header('Location: meu-concurso.php');
    exit;
}

$trocar = isset($_GET['trocar']);
$esc = $trocar ? null : hack_escolha_aluno($pdo, $usuarioId);
// Mentor/admin: "Ver como o aluno" de qualquer cargo, sem mudar nada.
$preview = 0;
$ehAdminMc = !empty($_SESSION['admin_usuario_id']) || strtolower((string)($_SESSION['usuario_tipo'] ?? '')) === 'admin';
if ($ehAdminMc && (int)($_GET['ver_cargo'] ?? 0) > 0) {
    $stP = $pdo->prepare('SELECT cg.concurso_id, cg.id AS cargo_id, c.nome AS concurso, c.situacao, c.base_edital, c.data_prova, cg.nome AS cargo, cg.curso_id FROM concurso_cargos cg JOIN concursos c ON c.id = cg.concurso_id WHERE cg.id = :g');
    $stP->execute([':g' => (int)$_GET['ver_cargo']]);
    $esc = $stP->fetch() ?: $esc;
    $preview = (int)$_GET['ver_cargo'];
}
$aba = in_array($_GET['aba'] ?? '', ['edital', 'prova', 'conteudo', 'radar'], true) ? $_GET['aba'] : 'edital';

$concursos = [];
if (!$esc) {
    foreach ($pdo->query("SELECT c.id, c.nome, c.situacao, c.base_edital, c.data_prova FROM concursos c WHERE c.situacao <> 'encerrado' ORDER BY (c.situacao = 'aberto') DESC, c.nome") as $c) {
        $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c ORDER BY nome');
        $cg->execute([':c' => (int)$c['id']]);
        $c['cargos'] = $cg->fetchAll();
        $concursos[] = $c;
    }
} else {
    if (!$preview) {
        hack_registrar_visita($pdo, $usuarioId);
    }
    $st = $pdo->prepare('SELECT c.*, b.nome AS banca_nome FROM concursos c LEFT JOIN admin_bancas b ON b.id = c.banca_id WHERE c.id = :id');
    $st->execute([':id' => (int)$esc['concurso_id']]);
    $conc = $st->fetch();
    $info = json_decode((string)($conc['edital_info'] ?? ''), true) ?: [];
    $hacks = (json_decode((string)($conc['hacks'] ?? ''), true) ?: [])[(string)$esc['cargo_id']] ?? null;
    $prova = json_decode((string)($conc['ultima_prova'] ?? ''), true) ?: [];
    $itens = hack_itens_do_cargo($pdo, (int)$esc['concurso_id'], (int)$esc['cargo_id']);
    $infoCargo = [];
    foreach ((array)($info['cargos'] ?? []) as $ci) {
        if (edital_normalizar((string)($ci['nome'] ?? '')) === edital_normalizar((string)$esc['cargo'])) {
            $infoCargo = $ci;
        }
    }
    if (!$infoCargo && count((array)($info['cargos'] ?? [])) === 1) {
        $infoCargo = $info['cargos'][0];
    }
    // Andamento do aluno por assunto (questões resolvidas) e link da aula.
    $resolvidas = [];
    $stR = $pdo->prepare('SELECT q.materia_topico_id t, COUNT(*) n FROM banco_questoes_respostas_usuario r JOIN admin_banco_questoes q ON q.id = r.questao_id WHERE r.opcao_id IS NOT NULL AND r.usuario_id = :u GROUP BY q.materia_topico_id');
    $stR->execute([':u' => $usuarioId]);
    foreach ($stR->fetchAll() as $r) {
        $resolvidas[(int)$r['t']] = (int)$r['n'];
    }
    $aulaDo = $pdo->prepare('SELECT id, curso_id, modulo_id FROM admin_curso_aulas WHERE curso_id = :c AND topico_id = :t ORDER BY ordem, id LIMIT 1');
    // Peso na prova por matéria (última prova).
    $porMateria = [];
    $totalProva = array_sum((array)($prova['itens'] ?? []));
    foreach ($itens as $it) {
        $q = (int)(($prova['itens'] ?? [])[(string)$it['id']] ?? (($prova['itens'] ?? [])[(int)$it['id']] ?? 0));
        $porMateria[$it['materia']] = ($porMateria[$it['materia']] ?? 0) + $q;
    }
    arsort($porMateria);
    $jornada = hack_jornada_etapas($info);
    $jornadaAtual = hack_jornada_atual($pdo, $usuarioId);
    $jIdx = max(0, (int)array_search($jornadaAtual, array_column($jornada, 'id'), true));
    $diasProva = !empty($conc['data_prova']) ? (int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable((string)$conc['data_prova']))->format('%r%a') : null;
}

function mc2_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function mc2_v($v): string
{
    return ($v === null || $v === '' || $v === []) ? '—' : mc2_e(is_array($v) ? implode(', ', $v) : $v);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="/ead/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu concurso | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#3ccf7a}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .mcz{max-width:960px;margin:0 auto;padding:24px 16px 72px;display:grid;gap:18px}
        h1{font-size:clamp(22px,4vw,30px)}
        .sub{color:var(--suave);font-size:14px}
        .tag{display:inline-block;font-size:12px;font-weight:700;padding:4px 10px;border-radius:99px;background:#2e2408;color:var(--ouro)}
        .tag.aberto{background:#0f2a1a;color:var(--ok)}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:16px}
        .esc-lista{display:grid;gap:12px}
        .esc-lista form{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
        .bt{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:10px;padding:10px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;background:var(--ouro);color:#111}
        .bt.linha{background:transparent;color:var(--texto);border:1px solid var(--linha)}
        .bt:focus-visible,.abas a:focus-visible{outline:2px solid var(--ouro);outline-offset:2px}
        .topo{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:12px}
        .antigao{display:flex;gap:12px;align-items:flex-start;background:linear-gradient(135deg,#221a06,#161618 60%);border:1px solid #4a3a10;border-radius:16px;padding:16px}
        .antigao img{height:44px;width:auto;flex:0 0 auto}
        .antigao p{font-size:15.5px}
        .antigao small{display:block;color:var(--ouro);font-weight:700;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px}
        .numeros{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .numeros div{background:var(--card);border:1px solid var(--linha);border-radius:14px;padding:12px}
        .numeros span{display:block;font-size:11.5px;color:var(--suave);text-transform:uppercase;letter-spacing:.06em}
        .numeros strong{font-size:18px;overflow-wrap:anywhere}
        .abas{display:flex;gap:6px;overflow-x:auto}
        .abas a{flex:0 0 auto;padding:10px 14px;border-radius:12px;background:var(--card);border:1px solid var(--linha);color:var(--texto);text-decoration:none;font-weight:700;font-size:14px}
        .abas a[aria-current="page"]{background:var(--ouro);color:#111;border-color:var(--ouro)}
        .hack{display:grid;gap:10px}
        .hack article{background:var(--card);border:1px solid var(--linha);border-left:4px solid var(--ouro);border-radius:12px;padding:14px}
        .hack h3{font-size:16px;margin-bottom:4px}
        .hack p{color:#d4d4d8;font-size:14.5px}
        .dados{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}
        .dados div{background:var(--card2);border:1px solid var(--linha);border-radius:12px;padding:12px;font-size:14px}
        .dados b{display:block;color:var(--ouro);font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
        .dados ul{padding-left:18px}
        .peso{display:grid;gap:8px}
        .peso div{display:grid;grid-template-columns:minmax(0,1fr) 120px 50px;gap:10px;align-items:center;font-size:14px}
        .peso i{display:block;height:8px;border-radius:99px;background:var(--ouro)}
        .materia{background:var(--card);border:1px solid var(--linha);border-radius:14px;overflow:hidden}
        .materia summary{padding:12px 14px;font-weight:700;cursor:pointer;display:flex;justify-content:space-between;gap:10px}
        .assunto{display:grid;grid-template-columns:24px minmax(0,1fr) auto;gap:10px;align-items:center;padding:10px 14px;border-top:1px solid var(--linha);font-size:14px}
        .assunto small{color:var(--suave);display:block}
        .assunto a{color:var(--ouro);font-weight:700;text-decoration:none;font-size:13px}
        .aviso{font-size:13px;color:var(--suave)}
        .jornada{display:grid;gap:12px}
        .j-lista{list-style:none;display:flex;gap:6px;overflow-x:auto;padding-bottom:4px}
        .j-lista li{flex:0 0 auto;display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:99px;background:var(--card2);border:1px solid var(--linha);font-size:12.5px;color:var(--suave)}
        .j-lista li span{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:var(--linha);font-size:11px;font-weight:700;color:var(--texto)}
        .j-lista li.feito span{background:#16a34a}
        .j-lista li.agora{border-color:var(--ouro);color:var(--texto);font-weight:700}
        .j-lista li.agora span{background:var(--ouro);color:#111}
        .j-fala{font-size:15px}.j-fala strong{color:var(--ouro)}
        @media(max-width:640px){.numeros{grid-template-columns:1fr 1fr}.peso div{grid-template-columns:minmax(0,1fr) 70px 40px}}
    </style>
</head>
<body>
<?php if (is_file(__DIR__ . '/includes/barra-superior.php')) { include_once __DIR__ . '/includes/barra-superior.php'; } ?>
<main class="mcz">
<?php if (!empty($preview)): ?><div class="card" role="note" style="border-color:#fbb928">👁 <strong>Pré-visualização do mentor</strong>: é exatamente o que o aluno de <?php echo mc2_e($esc['cargo'] ?? ''); ?> vê. Nada aqui é salvo.</div><?php endif; ?>
<?php if (!$esc): ?>
    <header><h1>🕵️ Escolha seu concurso</h1><p class="sub">Toque no cargo que você vai prestar. Seu curso, seu cronograma e tudo do edital passam a seguir esse cargo. Dá para trocar depois.</p></header>
    <div class="esc-lista">
        <?php if (!$concursos): ?><div class="card">Os concursos ainda estão sendo cadastrados pela equipe. Volte em breve.</div><?php endif; ?>
        <?php foreach ($concursos as $c): ?>
            <section class="card" aria-label="<?php echo mc2_e($c['nome']); ?>">
                <h2 style="font-size:18px"><?php echo mc2_e($c['nome']); ?></h2>
                <span class="tag <?php echo $c['situacao'] === 'aberto' ? 'aberto' : ''; ?>"><?php echo $c['situacao'] === 'aberto' ? 'Edital aberto' : 'Previsto · base: ' . mc2_e($c['base_edital'] ?: 'último edital'); ?></span>
                <form method="post"><input type="hidden" name="acao" value="escolher">
                    <?php foreach ($c['cargos'] as $cg): ?><button class="bt" type="submit" name="cargo_id" value="<?php echo (int)$cg['id']; ?>"><?php echo mc2_e($cg['nome']); ?></button><?php endforeach; ?>
                </form>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <header class="topo">
        <div>
            <h1><?php echo mc2_e($esc['cargo']); ?></h1>
            <p class="sub"><?php echo mc2_e($esc['concurso']); ?> · <span class="tag <?php echo $esc['situacao'] === 'aberto' ? 'aberto' : ''; ?>"><?php echo $esc['situacao'] === 'aberto' ? 'Edital aberto' : 'Edital previsto · tudo com base no ' . mc2_e($esc['base_edital'] ?: 'último edital'); ?></span></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="bt" href="edital-verticalizado.php<?php echo $preview ? '?concurso_id=' . (int)$esc['concurso_id'] . '&cargo_id=' . $preview : ''; ?>">📋 Edital verticalizado</a>
            <?php if (!empty($conc['edital_pdf'])): ?><button class="bt linha" type="button" data-pdf-marca="<?php echo mc2_e($conc['edital_pdf']); ?>" data-nome="Edital - <?php echo mc2_e($esc['concurso']); ?> - Projeto Gabaritando.pdf">📄 Baixar edital</button><?php endif; ?>
            <?php $provaCargo = (json_decode((string)($conc['provas'] ?? ''), true) ?: [])[(string)$esc['cargo_id']] ?? null; if ($provaCargo && ($provaCargo['status'] ?? '') === 'confere'): ?><button class="bt linha" type="button" data-pdf-marca="<?php echo mc2_e($provaCargo['arquivo']); ?>" data-nome="Prova <?php echo mc2_e(trim(($provaCargo['banca'] ?? '') . ' ' . ($provaCargo['ano'] ?? ''))); ?> - <?php echo mc2_e($esc['cargo']); ?> - Projeto Gabaritando.pdf">📝 Baixar prova anterior</button><?php endif; ?>
            <a class="bt linha" href="meu-concurso.php?trocar=1">Trocar cargo</a>
        </div>
    </header>

    <div class="antigao" role="note">
        <img src="img/logo.png" onerror="this.onerror=null;this.src='favicon.png'" alt="">
        <div><small>Antigão</small><p><?php echo mc2_e($hacks['resumo_antigao'] ?? 'Combatente, o edital é o mapa da missão. Aqui você encontra tudo o que precisa saber e onde estão os pontos da prova. Volte aqui a cada dois dias: está no seu cronograma.'); ?></p></div>
    </div>

    <section class="card jornada" aria-labelledby="jTitulo">
        <h2 id="jTitulo" style="font-size:17px">🎖 Sua jornada até a nomeação</h2>
        <ol class="j-lista"><?php foreach ($jornada as $k => $j): ?><li class="<?php echo $k < $jIdx ? 'feito' : ($k === $jIdx ? 'agora' : ''); ?>"><span><?php echo $k < $jIdx ? '✓' : $k + 1; ?></span><?php echo mc2_e($j['nome']); ?></li><?php endforeach; ?></ol>
        <p class="j-fala"><strong>Antigão:</strong> <?php echo mc2_e($jornada[$jIdx]['antigao']); ?></p>
        <?php if ($jIdx < count($jornada) - 1): ?>
        <form method="post"><input type="hidden" name="acao" value="jornada"><input type="hidden" name="etapa" value="<?php echo mc2_e($jornada[$jIdx + 1]['id']); ?>"><button class="bt" type="submit" onclick="return confirm('Avançar para: <?php echo mc2_e(addslashes($jornada[$jIdx + 1]['nome'])); ?>?');">Concluí “<?php echo mc2_e($jornada[$jIdx]['nome']); ?>” → <?php echo mc2_e($jornada[$jIdx + 1]['nome']); ?></button></form>
        <?php endif; ?>
    </section>

    <section class="numeros" aria-label="Números do concurso">
        <div><span>Vagas</span><strong><?php echo mc2_v($infoCargo['vagas'] ?? null); ?></strong></div>
        <div><span>Salário</span><strong><?php echo mc2_v($infoCargo['salario'] ?? null); ?></strong></div>
        <div><span>Prova</span><strong><?php echo $diasProva !== null ? ($diasProva >= 0 ? $diasProva . ' dias' : 'realizada') : mc2_v($info['prova_objetiva']['data'] ?? null); ?></strong></div>
        <div><span>Banca</span><strong><?php echo mc2_v($conc['banca_nome'] ?: ($info['banca'] ?? null)); ?></strong></div>
    </section>

    <nav class="abas" aria-label="Seções">
        <a href="?aba=edital<?php echo !empty($preview) ? '&amp;ver_cargo=' . (int)$preview : ''; ?>" <?php echo $aba === 'edital' ? 'aria-current="page"' : ''; ?>>🕵️ Hackeando o Edital</a>
        <a href="?aba=prova<?php echo !empty($preview) ? '&amp;ver_cargo=' . (int)$preview : ''; ?>" <?php echo $aba === 'prova' ? 'aria-current="page"' : ''; ?>>🎯 Hackeando a Prova</a>
        <a href="?aba=conteudo<?php echo !empty($preview) ? '&amp;ver_cargo=' . (int)$preview : ''; ?>" <?php echo $aba === 'conteudo' ? 'aria-current="page"' : ''; ?>>📚 Conteúdo programático</a>
        <?php require_once __DIR__ . '/includes/radar-lib.php'; $radar = radar_noticias($pdo, (int)$esc['concurso_id'], 30); $radarNovas = count(array_filter($radar, static fn($n) => strtotime((string)$n['criado_em']) > time() - 7 * 86400)); ?>
        <a href="?aba=radar<?php echo !empty($preview) ? '&amp;ver_cargo=' . (int)$preview : ''; ?>" <?php echo $aba === 'radar' ? 'aria-current="page"' : ''; ?>>📡 Radar<?php echo $radarNovas ? ' (' . $radarNovas . ' nova' . ($radarNovas > 1 ? 's' : '') . ')' : ''; ?></a>
    </nav>

    <?php if ($aba === 'radar'): $tiposR = radar_tipos(); $ultR = radar_ultima_busca($pdo, (int)$esc['concurso_id']); ?>
        <section class="card">
            <h2 style="font-size:18px">📡 Radar do seu concurso</h2>
            <p class="aviso">O Antigão vigia a internet todos os dias pelo seu concurso: edital, datas, banca, convocações, Justiça e nomeações. Sempre com a fonte, para você conferir.<?php echo $ultR ? ' Última busca: ' . mc2_e(date('d/m H:i', strtotime($ultR))) . '.' : ''; ?></p>
            <?php if (!$radar): ?><p style="margin-top:10px">Nenhuma novidade registrada ainda. Quando sair algo, aparece aqui e no seu sininho. Enquanto isso: foco na missão do dia, combatente!</p><?php endif; ?>
            <?php foreach ($radar as $n): ?>
                <article id="n<?php echo (int)$n['id']; ?>" style="border-top:1px solid rgba(255,255,255,.08);padding:12px 0">
                    <small><?php echo mc2_e($tiposR[$n['tipo']] ?? '📰'); ?><?php echo $n['publicada_em'] ? ' · ' . mc2_e(date('d/m/Y', strtotime((string)$n['publicada_em']))) : ''; ?><?php echo $n['importante'] ? ' · <strong style="color:#e8c55a">importante</strong>' : ''; ?></small>
                    <h3 style="font-size:16px;margin:4px 0"><?php echo mc2_e($n['titulo']); ?></h3>
                    <?php if ($n['resumo']): ?><p style="margin:0 0 4px"><?php echo mc2_e($n['resumo']); ?></p><?php endif; ?>
                    <?php if ($n['o_que_fazer']): ?><p style="margin:0 0 4px"><strong>👉 O que fazer:</strong> <?php echo mc2_e($n['o_que_fazer']); ?></p><?php endif; ?>
                    <small>Fonte: <a href="<?php echo mc2_e($n['fonte_url']); ?>" target="_blank" rel="noopener nofollow" style="color:#e8c55a"><?php echo mc2_e($n['fonte_nome'] ?: 'abrir'); ?></a></small>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($aba === 'edital'): ?>
        <?php if ($hacks): ?><div class="hack"><?php foreach ($hacks['hack_edital'] as $b): ?><article><h3><?php echo mc2_e($b['icone'] . ' ' . $b['titulo']); ?></h3><p><?php echo mc2_e($b['texto']); ?></p></article><?php endforeach; ?></div><?php endif; ?>
        <h2 style="font-size:18px">Tudo do edital<?php echo $esc['situacao'] !== 'aberto' ? ' (como foi no anterior)' : ''; ?></h2>
        <div class="dados">
            <div><b>Requisitos do cargo</b>Escolaridade: <?php echo mc2_v($infoCargo['escolaridade'] ?? null); ?><br>Idade: <?php echo mc2_v($infoCargo['idade'] ?? null); ?><br>Altura: <?php echo mc2_v($infoCargo['altura'] ?? null); ?><br>CNH: <?php echo mc2_v($infoCargo['cnh'] ?? null); ?>
                <?php if (!empty($infoCargo['outros_requisitos'])): ?><ul><?php foreach ((array)$infoCargo['outros_requisitos'] as $r): if (trim((string)$r) === '') { continue; } ?><li><?php echo mc2_e($r); ?></li><?php endforeach; ?></ul><?php endif; ?></div>
            <div><b>Inscrições</b><?php echo mc2_v(trim(($info['inscricoes']['inicio'] ?? '') . ' a ' . ($info['inscricoes']['fim'] ?? ''), ' a')); ?><br>Taxa: <?php echo mc2_v($info['inscricoes']['taxa'] ?? null); ?><br>Onde: <?php echo mc2_v($info['inscricoes']['onde'] ?? null); ?></div>
            <div><b>Prova objetiva</b>Data: <?php echo mc2_v($info['prova_objetiva']['data'] ?? null); ?><br>Questões: <?php echo mc2_v($info['prova_objetiva']['questoes'] ?? null); ?> · Duração: <?php echo mc2_v($info['prova_objetiva']['duracao'] ?? null); ?><br>Aprovação: <?php echo mc2_v($info['prova_objetiva']['criterio_aprovacao'] ?? null); ?><br>Errada anula certa? <?php echo mc2_v($info['prova_objetiva']['penaliza_erro'] ?? null); ?></div>
            <div><b>Redação</b><?php echo mc2_v(($info['redacao']['tem'] ?? null) === null ? null : (($info['redacao']['tem'] ?? false) ? 'Sim' : 'Não')); ?><br><?php echo mc2_v($info['redacao']['criterio'] ?? null); ?></div>
            <div><b>Etapas</b><ul><?php foreach ((array)($info['etapas'] ?? []) as $e): ?><li><strong><?php echo mc2_e($e['nome'] ?? ''); ?></strong> (<?php echo mc2_e($e['carater'] ?? ''); ?>) <?php echo mc2_e($e['resumo'] ?? ''); ?></li><?php endforeach; ?></ul><?php echo empty($info['etapas']) ? '—' : ''; ?></div>
            <div><b>TAF</b><ul><?php foreach ((array)($info['taf'] ?? []) as $t): if (($t['cargo'] ?? '') !== '' && edital_normalizar((string)$t['cargo']) !== edital_normalizar((string)$esc['cargo']) && stripos((string)$t['cargo'], 'todos') === false) { continue; } ?><li><?php echo mc2_e(trim(($t['sexo'] ?? '') . ' · ' . ($t['exercicio'] ?? '') . ': ' . ($t['minimo'] ?? ''), ' ·')); ?></li><?php endforeach; ?></ul><?php echo empty($info['taf']) ? '—' : ''; ?></div>
            <div><b>Datas importantes</b><ul><?php foreach ((array)($info['datas_importantes'] ?? []) as $d): ?><li><?php echo mc2_e(($d['data'] ?? '') . ' · ' . ($d['evento'] ?? '')); ?></li><?php endforeach; ?></ul><?php echo empty($info['datas_importantes']) ? '—' : ''; ?></div>
            <div><b>Outros</b>Vagas (detalhe): <?php echo mc2_v($infoCargo['vagas_detalhe'] ?? null); ?><br>Cotas: <?php echo mc2_v($info['cotas'] ?? null); ?><br>Validade: <?php echo mc2_v($info['validade'] ?? null); ?><br>Lotação: <?php echo mc2_v($info['lotacao'] ?? null); ?></div>
            <?php if (!empty($info['atencao'])): ?><div style="grid-column:1/-1;border-color:#7a2a20"><b style="color:#ff8a7a">⚠️ O que mais elimina candidatos</b><ul><?php foreach ((array)$info['atencao'] as $a): ?><li><?php echo mc2_e($a); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        </div>
        <?php if (!$info): ?><p class="aviso">As informações deste edital ainda estão sendo cadastradas pela equipe.</p><?php endif; ?>

    <?php elseif ($aba === 'prova'): ?>
        <?php if ($hacks): ?><div class="hack"><?php foreach ($hacks['hack_prova'] as $b): ?><article><h3><?php echo mc2_e($b['icone'] . ' ' . $b['titulo']); ?></h3><p><?php echo mc2_e($b['texto']); ?></p></article><?php endforeach; ?></div><?php endif; ?>
        <?php if ($totalProva > 0): $max = max($porMateria) ?: 1; ?>
            <section class="card"><h2 style="font-size:17px;margin-bottom:10px">Onde estão os pontos · última prova <?php echo mc2_e(trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? ''))); ?></h2>
                <div class="peso"><?php foreach ($porMateria as $m => $q): if (!$q) { continue; } ?><div><span><?php echo mc2_e($m); ?></span><i style="width:<?php echo (int)round($q * 100 / $max); ?>%" aria-hidden="true"></i><strong><?php echo (int)round($q * 100 / $totalProva); ?>%</strong></div><?php endforeach; ?></div>
                <p class="aviso" style="margin-top:8px">Fonte: <?php echo mc2_e($prova['fonte'] ?? ''); ?></p></section>
        <?php else: ?><p class="aviso">A distribuição da última prova ainda não está disponível para este concurso.</p><?php endif; ?>

    <?php else: ?>
        <p class="sub">O conteúdo programático é a base do seu curso: cada assunto tem aula e questões. ✅ = já liberou (<?php echo 5; ?> questões).</p>
        <?php $grupo = []; foreach ($itens as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = 0; foreach ($lista as $x) { $ok += ($resolvidas[(int)$x['topico_id']] ?? 0) >= 5 ? 1 : 0; } ?>
            <details class="materia"><summary><span><?php echo mc2_e($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?></small></summary>
                <?php foreach ($lista as $it): $n = $resolvidas[(int)$it['topico_id']] ?? 0; $aula = null; if (!empty($esc['curso_id'])) { $aulaDo->execute([':c' => (int)$esc['curso_id'], ':t' => (int)$it['topico_id']]); $aula = $aulaDo->fetch(); } ?>
                    <div class="assunto"><span><?php echo $n >= 5 ? '✅' : '⬜'; ?></span><div><?php echo mc2_e($it['assunto']); ?><small><?php echo $n; ?> questões resolvidas</small></div>
                        <span><?php if ($aula): ?><a href="aulas.php?curso_id=<?php echo (int)$aula['curso_id']; ?>&modulo_id=<?php echo (int)$aula['modulo_id']; ?>&aula_id=<?php echo (int)$aula['id']; ?>">Aula</a> · <?php endif; ?><a href="banco-questoes.php?topico_id=<?php echo (int)$it['topico_id']; ?>">Questões</a></span></div>
                <?php endforeach; ?>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
<script src="assets/js/gab-marca-dagua.js"></script>
</body>
</html>
