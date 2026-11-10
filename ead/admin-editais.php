<?php
// admin-editais.php
// Cadastro do concurso, passo a passo. O EDITAL é a base do curso.
//   1 Concurso e cargos  2 Edital (conteúdo programático + informações)  3 Última prova
//   4 Aulas por assunto (vídeo/PDF; o que faltar a IA gera)  5 Questões (enviar, pesquisar ou gerar)
//   6 Plano-Mestre por cargo  7 Hackeando para o aluno (textos do Antigão)
// Tudo é salvo no banco a cada passo: o mentor pausa e continua depois sem perder nada.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();

require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/edital-jobs-lib.php';
@set_time_limit(320);

$pdo = admin_cursos_db();
hack_bootstrap($pdo);
job_bootstrap($pdo);
ia_fila_bootstrap($pdo);

const ED_PASSOS = ['concurso' => 'Concurso e cargos', 'edital' => 'Edital', 'prova' => 'Última prova', 'aulas' => 'Aulas', 'questoes' => 'Questões', 'plano' => 'Plano-Mestre', 'publicar' => 'Hackeando'];

$concursoId = (int)($_GET['concurso_id'] ?? $_POST['concurso_id'] ?? 0);
$aba = (string)($_GET['aba'] ?? '');
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

function ed_pdf_enviado(string $campo, int $limiteMb = 15): string
{
    if (empty($_FILES[$campo]['tmp_name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
        return '';
    }
    if ((int)$_FILES[$campo]['size'] > $limiteMb * 1024 * 1024) {
        throw new RuntimeException('PDF acima de ' . $limiteMb . ' MB.');
    }
    return base64_encode((string)file_get_contents($_FILES[$campo]['tmp_name']));
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
            ed_ir($novo, 'concurso', 'Concurso criado. Ligue cada cargo a um curso (ou crie um curso novo) e salve.');
        }
        if ($concursoId <= 0) { throw new RuntimeException('Escolha um concurso.'); }

        // 1 · Concurso e cargos
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
                ed_ir($concursoId, 'concurso', 'Cargo adicionado. Ligue-o a um curso.');
            }
            ed_ir($concursoId, 'edital', 'Concurso salvo. Próximo passo: o edital.');
        }

        // 2 · Edital
        if ($acao === 'ler_edital') {
            $texto = trim((string)($_POST['conteudo'] ?? ''));
            $st = $pdo->prepare('SELECT edital_pdf FROM concursos WHERE id = :id');
            $st->execute([':id' => $concursoId]);
            $pdfUrl = (string)$st->fetchColumn();
            if (!empty($_FILES['pdf']['tmp_name']) && is_uploaded_file($_FILES['pdf']['tmp_name'])) {
                if ((int)$_FILES['pdf']['size'] > 15 * 1024 * 1024) { throw new RuntimeException('PDF acima de 15 MB.'); }
                $pdfUrl = hack_salvar_pdf_edital($pdo, $concursoId, $_FILES['pdf']['tmp_name']);
            }
            if ($texto === '' && $pdfUrl === '') { throw new RuntimeException('Envie o PDF do edital ou cole o texto.'); }
            $parte = ($_POST['parte'] ?? 'conteudo') === 'info' ? 'ler_info' : 'ler_conteudo';
            job_criar($pdo, $concursoId, $parte, $parte === 'ler_info' ? 'Lendo as informações do edital (vagas, salário, requisitos, etapas, datas)' : 'Lendo o conteúdo programático do edital', ['texto' => mb_substr($texto, 0, 80000)]);
            ed_ir($concursoId, 'edital', '🤖 A IA começou a ler o edital. Acompanhe na Central da IA: quando terminar, a página atualiza sozinha.');
        }
        if ($acao === 'salvar_rascunho_edital') {
            hack_rascunho_set($pdo, $concursoId, 'edital_texto', (string)($_POST['rascunho'] ?? ''));
            ed_ir($concursoId, 'edital', 'Rascunho salvo. Pode continuar depois.');
        }
        if ($acao === 'confirmar_edital') {
            $materias = ed_de_texto((string)($_POST['rascunho'] ?? ''));
            if (!$materias) { throw new RuntimeException('Nada para salvar. Use "## Matéria" e "- assunto".'); }
            $n = edital_salvar($pdo, $concursoId, $materias);
            hack_rascunho_set($pdo, $concursoId, 'edital_texto', null);
            ed_ir($concursoId, 'edital', $n . ' assuntos no conteúdo programático.');
        }
        if ($acao === 'salvar_info') {
            $j = json_decode((string)($_POST['info'] ?? ''), true);
            if (!is_array($j)) { throw new RuntimeException('O texto das informações precisa continuar em JSON válido.'); }
            $pdo->prepare('UPDATE concursos SET edital_info = :i WHERE id = :id')->execute([':i' => json_encode($j, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
            ed_ir($concursoId, 'edital', 'Informações do edital salvas.');
        }

        // 3 · Última prova
        if ($acao === 'enviar_prova') {
            $cargoId = (int)($_POST['cargo_id'] ?? 0);
            if (empty($_FILES['prova']['tmp_name']) || !is_uploaded_file($_FILES['prova']['tmp_name'])) { throw new RuntimeException('Escolha o PDF da prova.'); }
            if ((int)$_FILES['prova']['size'] > 15 * 1024 * 1024) { throw new RuntimeException('PDF acima de 15 MB.'); }
            job_salvar_prova_pdf($pdo, $concursoId, $cargoId, $_FILES['prova']['tmp_name']);
            job_criar($pdo, $concursoId, 'verificar_prova', 'Conferindo a prova de ' . ($_POST['cargo_nome'] ?? 'cargo'), ['cargo_id' => $cargoId]);
            ed_ir($concursoId, 'prova', '🤖 Prova recebida. A IA está conferindo se é a prova certa e contando as questões por assunto.');
        }
        if ($acao === 'buscar_prova') {
            job_criar($pdo, $concursoId, 'buscar_prova', 'Pesquisando a última prova na internet');
            ed_ir($concursoId, 'prova', '🤖 A IA está pesquisando a prova na internet.');
        }
        if ($acao === 'sem_prova') {
            $pdo->prepare("UPDATE concursos SET prova_status = 'sem_prova' WHERE id = :id")->execute([':id' => $concursoId]);
            ed_ir($concursoId, 'aulas', 'Ok: os pesos serão estimados pela IA.');
        }

        // 4 · Aulas por assunto
        if ($acao === 'aula_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $url = trim((string)($_POST['video'] ?? ''));
            $pdf = '';
            if (!empty($_FILES['pdf_aula']['tmp_name']) && is_uploaded_file($_FILES['pdf_aula']['tmp_name'])) {
                $dir = __DIR__ . '/uploads/cursos/pdf';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $nomeArq = 'edital-' . $itemId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.pdf';
                if (!move_uploaded_file($_FILES['pdf_aula']['tmp_name'], $dir . '/' . $nomeArq)) { throw new RuntimeException('Não consegui salvar o PDF.'); }
                $pdf = 'uploads/cursos/pdf/' . $nomeArq;
            }
            if ($url === '' && $pdf === '') { throw new RuntimeException('Cole o link do vídeo ou envie o PDF.'); }
            $it = $pdo->prepare('SELECT e.*, t.titulo AS assunto FROM edital_itens e JOIN admin_materia_topicos t ON t.id = e.topico_id WHERE e.id = :id AND e.concurso_id = :c');
            $it->execute([':id' => $itemId, ':c' => $concursoId]);
            $item = $it->fetch();
            if (!$item) { throw new RuntimeException('Assunto não encontrado.'); }
            $cg = $pdo->prepare('SELECT id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
            $cg->execute([':c' => $concursoId]);
            $ids = array_filter(array_map('intval', explode(',', (string)$item['cargos'])));
            $cargosItem = array_values(array_filter(array_map('intval', $cg->fetchAll(PDO::FETCH_COLUMN)), static fn($x) => !$ids || in_array($x, $ids, true)));
            if (!$cargosItem) { throw new RuntimeException('Ligue os cargos a um curso no passo 1.'); }
            // Substitui a aula provisória da IA, se houver.
            $titulo = trim((string)($_POST['titulo'] ?? '')) ?: ($url !== '' ? (edital_youtube_titulo($url) ?: $item['assunto']) : $item['assunto']);
            $pdo->prepare("DELETE FROM admin_curso_aulas WHERE topico_id = :t AND COALESCE(video_url,'') = '' AND COALESCE(pdf_arquivo,'') = '' AND titulo LIKE '%aula do Antigão%'")->execute([':t' => (int)$item['topico_id']]);
            hack_aula_do_item($pdo, $itemId, $cargosItem, $titulo, $url, $pdf);
            ed_ir($concursoId, 'aulas', 'Aula de "' . $item['assunto'] . '" cadastrada. A IA prepara apostila, podcast e (sem vídeo) a videoaula do Antigão.');
        }
        if ($acao === 'sugerir_aulas') {
            $linhas = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['links'] ?? '')))));
            foreach (array_chunk($linhas, 4) as $k => $lote) {
                job_criar($pdo, $concursoId, 'sugerir_links', 'Analisando links de vídeo (' . ($k * 4 + 1) . ' a ' . ($k * 4 + count($lote)) . ')', ['links' => $lote]);
            }
            ed_ir($concursoId, 'aulas', '🤖 ' . count($linhas) . ' link(s) na fila da IA. As sugestões aparecem abaixo conforme ficam prontas.');
        }
        if ($acao === 'sugerir_existentes') {
            hack_rascunho_set($pdo, $concursoId, 'existentes', null);
            job_criar($pdo, $concursoId, 'sugerir_existentes', 'Procurando vídeos já gravados na plataforma', ['lote' => 0]);
            ed_ir($concursoId, 'aulas', '🤖 A IA está comparando as aulas já gravadas com o edital.');
        }
        if ($acao === 'aceitar_existentes') {
            $sug = hack_rascunho_get($pdo, $concursoId, 'existentes', []);
            $marcadas = isset($_POST['todas']) ? array_keys($sug) : array_map('intval', (array)($_POST['e'] ?? []));
            $n = 0;
            foreach ($marcadas as $i) {
                if (isset($sug[$i])) { $n += job_aceitar_existente($pdo, $concursoId, (int)$sug[$i]['aula_id'], (int)$sug[$i]['item_id']); unset($sug[$i]); }
            }
            hack_rascunho_set($pdo, $concursoId, 'existentes', array_values($sug));
            ed_ir($concursoId, 'aulas', $n . ' aula(s) já gravadas ligadas ao edital.');
        }
        if ($acao === 'descartar_existentes') {
            hack_rascunho_set($pdo, $concursoId, 'existentes', null);
            ed_ir($concursoId, 'aulas');
        }
        if ($acao === 'cadastrar_aulas') {
            $sug = hack_rascunho_get($pdo, $concursoId, 'sugestoes', []);
            $criadas = 0;
            foreach ((array)($_POST['s'] ?? []) as $i => $v) {
                if (empty($v['usar']) || !isset($sug[(int)$i]) || (int)$v['item'] <= 0 || empty($v['cargos'])) { continue; }
                $criadas += count(edital_criar_aula($pdo, (int)$v['item'], array_map('intval', (array)$v['cargos']), trim((string)$v['titulo']), (string)$sug[(int)$i]['url']));
                unset($sug[(int)$i]);
            }
            hack_rascunho_set($pdo, $concursoId, 'sugestoes', array_values($sug));
            ed_ir($concursoId, 'aulas', $criadas . ' aula(s) cadastradas.' . ($sug ? ' Ainda há ' . count($sug) . ' sugestão(ões) sem assunto ou cargo.' : ''));
        }
        if ($acao === 'limpar_sugestoes') {
            hack_rascunho_set($pdo, $concursoId, 'sugestoes', null);
            ed_ir($concursoId, 'aulas');
        }
        if ($acao === 'gerar_faltantes') {
            $n = hack_gerar_faltantes($pdo, $concursoId);
            ed_ir($concursoId, 'aulas', $n . ' aula(s) do Antigão na fila: a IA escreve a teoria mínima e grava a videoaula. Quando a aula do professor chegar, é só cadastrar por cima.');
        }

        // 5 · Questões
        if ($acao === 'enviar_questoes') {
            $arq = '';
            if (!empty($_FILES['arquivo']['tmp_name']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
                $dir = __DIR__ . '/uploads/provas';
                if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                $arq = 'uploads/provas/questoes-' . $concursoId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.pdf';
                move_uploaded_file($_FILES['arquivo']['tmp_name'], __DIR__ . '/' . $arq);
            }
            job_criar($pdo, $concursoId, 'enviar_questoes', 'Separando por assunto as questões enviadas', ['arquivo' => $arq, 'texto' => mb_substr(trim((string)($_POST['texto'] ?? '')), 0, 60000)]);
            ed_ir($concursoId, 'questoes', '🤖 A IA está lendo as questões enviadas e separando por assunto.');
        }
        if ($acao === 'completar_questoes') {
            $n = 0;
            foreach (hack_questoes_situacao($pdo, $concursoId)['itens'] as $it) {
                if ($it['questoes'] < 10) { ia_fila_adicionar($pdo, 'questoes', (int)$it['id']); $n++; }
            }
            ed_ir($concursoId, 'questoes', $n . ' assunto(s) na fila: a IA pesquisa questões reais na internet e, se não achar, cria no nível da prova.');
        }
        if ($acao === 'questoes_item') {
            ia_fila_adicionar($pdo, 'questoes', (int)($_POST['item_id'] ?? 0));
            ed_ir($concursoId, 'questoes', '🤖 Assunto na fila: a IA pesquisa questões reais e, se faltar, cria no nível da prova.');
        }

        // 6 · Plano-Mestre
        if ($acao === 'sugerir_plano') {
            $cargoId = (int)($_POST['cargo_id'] ?? 0);
            job_criar($pdo, $concursoId, 'plano', 'Montando o Plano-Mestre · ' . ($_POST['cargo_nome'] ?? 'cargo'), ['cargo_id' => $cargoId]);
            ed_ir($concursoId, 'plano', '🤖 O Antigão está montando o Plano-Mestre. Quando terminar, aparece o botão "Abrir e aprovar".');
        }

        if ($acao === 'gerar_hacks' || $acao === 'gerar_vertical') {
            $cg = $pdo->prepare('SELECT id, nome FROM concurso_cargos WHERE concurso_id = :c');
            $cg->execute([':c' => $concursoId]);
            $alvo = (int)($_POST['cargo_id'] ?? 0);
            foreach ($cg->fetchAll() as $x) {
                if ($alvo && $alvo !== (int)$x['id']) { continue; }
                job_criar($pdo, $concursoId, 'verticalizado', 'Edital verticalizado · ' . $x['nome'], ['cargo_id' => (int)$x['id']]);
                if ($acao === 'gerar_hacks') { job_criar($pdo, $concursoId, 'hacks', 'Hackeando o Edital e a Prova · ' . $x['nome'], ['cargo_id' => (int)$x['id']]); }
            }
            ed_ir($concursoId, 'publicar', '🤖 O Antigão está escrevendo. Acompanhe na Central da IA.');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$concursos = $pdo->query('SELECT c.* FROM concursos c ORDER BY c.id DESC')->fetchAll();
$bancas = $pdo->query('SELECT id, nome FROM admin_bancas ORDER BY nome')->fetchAll();
$cursos = $pdo->query('SELECT id, nome FROM admin_cursos ORDER BY nome')->fetchAll();
$concurso = null;
$cargos = [];
$itens = [];
$prog = null;
if ($concursoId > 0) {
    $st = $pdo->prepare('SELECT * FROM concursos WHERE id = :id');
    $st->execute([':id' => $concursoId]);
    $concurso = $st->fetch() ?: null;
    if ($concurso) {
        $st = $pdo->prepare('SELECT * FROM concurso_cargos WHERE concurso_id = :c ORDER BY id');
        $st->execute([':c' => $concursoId]);
        $cargos = $st->fetchAll();
        $itens = edital_itens($pdo, $concursoId);
        $prog = hack_progresso($pdo, $concursoId);
        if (!isset(ED_PASSOS[$aba])) {
            $aba = $prog['proxima']; // abre direto onde parou
        }
    }
}
$nomeCargo = [];
foreach ($cargos as $c) { $nomeCargo[(int)$c['id']] = (string)$c['nome']; }
$situacoes = ['aberto' => 'Edital aberto', 'previsto' => 'Previsto (base: último edital)', 'encerrado' => 'Encerrado'];
$fila = ia_fila_resumo($pdo);

$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon" href="/ead/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro do concurso | Projeto Gabaritando</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .ed-prog{display:grid;gap:12px}
        .ed-prog-topo{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px}
        .ed-prog-topo h2{margin:0;font-size:18px}
        .ed-prog-topo small{color:#6b7280}
        .ed-barra{height:12px;background:#eef0f3;border-radius:99px;overflow:hidden}.ed-barra i{display:block;height:100%;background:linear-gradient(90deg,#fbb928,#16a34a);border-radius:99px;transition:width .4s}
        .ed-passos{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
        .ed-passos a{display:grid;gap:4px;padding:10px;border-radius:12px;background:#fff;border:1px solid #eef0f3;text-decoration:none;color:#374151;font-size:13px;min-width:0}
        .ed-passos a strong{display:flex;align-items:center;gap:6px;font-size:13.5px}
        .ed-passos a b{display:grid;place-items:center;flex:0 0 22px;height:22px;border-radius:50%;background:#eef0f3;font-size:12px}
        .ed-passos a small{color:#6b7280;font-size:11.5px;line-height:1.3;overflow-wrap:anywhere}
        .ed-passos a.ok b{background:#16a34a;color:#fff}
        .ed-passos a.ativo{border-color:#fbb928;background:#fff8e6;box-shadow:0 0 0 1px #fbb928 inset}
        .ed-passos a.ativo b{background:#fbb928;color:#111}
        .ed-salvo{font-size:12.5px;color:#16a34a;font-weight:700}
        .ed-tag{font-size:12px;font-weight:700;padding:3px 8px;border-radius:6px;background:#eef0f3;color:#374151}
        .ed-tag.aberto{background:#e7f7ec;color:#17803d}.ed-tag.previsto{background:#fff6dd;color:#8a6300}
        .ed-materia{border:1px solid #eef0f3;border-radius:12px;margin:10px 0;overflow:hidden}
        .ed-materia summary{padding:10px 14px;font-weight:700;cursor:pointer;display:flex;justify-content:space-between;gap:10px;background:#fafafa}
        .ed-assunto{display:grid;grid-template-columns:24px minmax(0,1fr) auto;gap:10px;padding:10px 14px;border-top:1px solid #f3f4f6;font-size:14px;align-items:center}
        .ed-assunto small{color:#6b7280;display:block}
        .ed-assunto form{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .ed-assunto input[type=url]{min-width:220px}
        .ed-falta{color:#b42318;font-weight:700}
        textarea.ed-mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;min-height:320px;width:100%}
        .ed-sug{display:grid;grid-template-columns:28px 1.2fr 1.4fr 1fr 60px;gap:10px;align-items:center;padding:10px;border-top:1px solid #f3f4f6}
        .ed-sug select,.ed-sug input[type=text]{width:100%}
        .ed-info{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}
        .ed-info div{border:1px solid #eef0f3;border-radius:10px;padding:10px;font-size:13.5px}
        .ed-info div b{display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
        .ed-opcoes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .ed-opcoes > div{border:1px solid #eef0f3;border-radius:12px;padding:14px;display:grid;gap:8px;align-content:start}
        .ed-opcoes h3{margin:0;font-size:15px}
        .ed-hack{border-left:4px solid #fbb928;background:#fffaf0;border-radius:8px;padding:10px 12px;margin:8px 0;font-size:14px}
        @media(max-width:1100px){.ed-passos{grid-template-columns:repeat(4,minmax(0,1fr))}.ed-opcoes{grid-template-columns:1fr}}
        @media(max-width:700px){.ed-passos{grid-template-columns:repeat(2,minmax(0,1fr))}.ed-sug,.ed-assunto{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header">
            <div class="admin-page-heading">
                <h1>Cadastro do concurso</h1>
                <p>O edital é a base do curso. Siga os passos: tudo fica salvo a cada passo, então dá para pausar e continuar depois.</p>
            </div>
            <form method="get" class="admin-page-actions">
                <select name="concurso_id" onchange="this.form.submit()" aria-label="Concurso">
                    <option value="0">— escolha o concurso —</option>
                    <?php foreach ($concursos as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$c['id'] === $concursoId ? 'selected' : ''; ?>><?php echo admin_esc((string)$c['nome']); ?></option><?php endforeach; ?>
                </select>
                <a class="admin-btn admin-btn-light" href="admin-editais.php"><i data-lucide="plus"></i>Novo</a>
            </form>
        </section>

        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error"><i data-lucide="alert-circle"></i><?php echo admin_esc($erro); ?></div><?php endif; ?>
        <?php if ($sucesso !== ''): ?><div class="admin-alert admin-alert-success"><i data-lucide="check-circle-2"></i><?php echo admin_esc($sucesso); ?></div><?php endif; ?>

        <?php if (!$concurso): ?>
            <?php if ($concursos): ?>
            <section class="admin-panel"><div class="admin-panel-title"><h2>Concursos em cadastro</h2></div>
                <?php foreach ($concursos as $c): $p = hack_progresso($pdo, (int)$c['id']); ?>
                    <div style="display:grid;grid-template-columns:minmax(0,1fr) 220px auto;gap:12px;align-items:center;padding:10px 0;border-top:1px solid #f3f4f6">
                        <div><strong><?php echo admin_esc((string)$c['nome']); ?></strong> <span class="ed-tag <?php echo admin_esc((string)$c['situacao']); ?>"><?php echo admin_esc($situacoes[$c['situacao']] ?? ''); ?></span><br><small>Próximo passo: <?php echo admin_esc(ED_PASSOS[$p['proxima']] ?? ''); ?></small></div>
                        <div class="ed-barra" role="progressbar" aria-valuenow="<?php echo $p['pct']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso do cadastro"><i style="width:<?php echo $p['pct']; ?>%"></i></div>
                        <a class="admin-btn admin-btn-primary admin-btn-small" href="admin-editais.php?concurso_id=<?php echo (int)$c['id']; ?>">▶ Continuar (<?php echo $p['pct']; ?>%)</a>
                    </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            <section class="admin-panel">
                <div class="admin-panel-title"><h2>Novo concurso</h2></div>
                <form method="post" class="admin-form-grid">
                    <input type="hidden" name="acao" value="criar_concurso">
                    <div class="admin-form-field"><label for="nome">Nome</label><input id="nome" name="nome" required placeholder="Ex.: PM e Bombeiros da Bahia 2026"></div>
                    <div class="admin-form-field"><label for="banca">Banca</label><select id="banca" name="banca_id"><option value="0">—</option><?php foreach ($bancas as $b): ?><option value="<?php echo (int)$b['id']; ?>"><?php echo admin_esc((string)$b['nome']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="sit">Situação</label><select id="sit" name="situacao"><?php foreach ($situacoes as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                    <div class="admin-form-field"><label for="base">Se previsto: edital usado como base</label><input id="base" name="base_edital" placeholder="Ex.: edital de 2022"></div>
                    <div class="admin-form-field"><label for="prova">Data da prova (se souber)</label><input id="prova" name="data_prova" type="date"></div>
                    <div class="admin-form-field full"><label for="cargos">Cargos (um por linha)</label><textarea id="cargos" name="cargos" rows="3" placeholder="Soldado PM&#10;Soldado Bombeiro"></textarea></div>
                    <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="plus"></i>Criar e começar</button></div>
                </form>
            </section>
        <?php else: ?>
            <section class="admin-panel ed-prog" aria-label="Progresso do cadastro">
                <div class="ed-prog-topo">
                    <h2><?php echo admin_esc((string)$concurso['nome']); ?> <span class="ed-tag <?php echo admin_esc((string)$concurso['situacao']); ?>"><?php echo admin_esc($situacoes[$concurso['situacao']] ?? ''); ?></span></h2>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <span class="ed-salvo">✓ Tudo salvo · pode sair e voltar</span>
                        <?php foreach ($cargos as $cgv): ?><a class="admin-btn admin-btn-light admin-btn-small" target="_blank" href="meu-concurso.php?ver_cargo=<?php echo (int)$cgv['id']; ?>">👁 Ver como o aluno · <?php echo admin_esc((string)$cgv['nome']); ?></a><?php endforeach; ?>
                        <?php if ($prog['proxima'] !== $aba): ?><a class="admin-btn admin-btn-primary admin-btn-small" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=<?php echo $prog['proxima']; ?>">▶ Continuar de onde parei</a><?php endif; ?>
                    </div>
                </div>
                <div class="ed-barra" role="progressbar" aria-valuenow="<?php echo $prog['pct']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Cadastro <?php echo $prog['pct']; ?>% concluído"><i style="width:<?php echo $prog['pct']; ?>%"></i></div>
                <small><?php echo $prog['pct']; ?>% concluído · <?php echo (int)$prog['feitas']; ?> de <?php echo count(ED_PASSOS); ?> passos<?php if (($fila['pendente'] ?? 0) > 0): ?> · 🤖 IA trabalhando em <?php echo (int)$fila['pendente']; ?> tarefa(s) em segundo plano<?php endif; ?></small>
                <nav class="ed-passos" aria-label="Passos">
                    <?php $n = 0; foreach (ED_PASSOS as $k => $v): $n++; $e = $prog['etapas'][$k]; ?>
                        <a class="<?php echo $e['ok'] ? 'ok' : ''; ?> <?php echo $aba === $k ? 'ativo' : ''; ?>" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=<?php echo $k; ?>" <?php echo $aba === $k ? 'aria-current="step"' : ''; ?>>
                            <strong><b><?php echo $e['ok'] ? '✓' : $n; ?></b><?php echo admin_esc($v); ?></strong><small><?php echo admin_esc($e['det']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <section class="admin-panel" id="centralIA" data-concurso="<?php echo $concursoId; ?>" aria-live="polite">
                <div class="admin-panel-title"><h2>🤖 Central da IA</h2><small id="ciaStatus">verificando…</small></div>
                <div id="ciaAulas" class="admin-form-hint" style="display:grid;gap:2px;margin-bottom:6px"></div>
                <div id="ciaQuestoes" class="admin-form-hint"></div>
                <div class="ed-barra" style="height:8px;margin:6px 0" aria-hidden="true"><i id="ciaQBarra" style="width:0"></i></div>
                <ul id="ciaJobs" style="list-style:none;padding:0;margin:8px 0 0;display:grid;gap:6px;font-size:14px"></ul>
            </section>

            <?php if ($aba === 'concurso'): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>1. Concurso e cargos</h2></div>
                    <form method="post" class="admin-form-grid">
                        <input type="hidden" name="acao" value="salvar_concurso"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <div class="admin-form-field"><label for="nome">Nome</label><input id="nome" name="nome" required value="<?php echo admin_esc((string)$concurso['nome']); ?>"></div>
                        <div class="admin-form-field"><label for="banca">Banca</label><select id="banca" name="banca_id"><option value="0">—</option><?php foreach ($bancas as $b): ?><option value="<?php echo (int)$b['id']; ?>" <?php echo (int)$b['id'] === (int)$concurso['banca_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$b['nome']); ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="sit">Situação</label><select id="sit" name="situacao"><?php foreach ($situacoes as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $concurso['situacao'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
                        <div class="admin-form-field"><label for="base">Se previsto: edital usado como base</label><input id="base" name="base_edital" value="<?php echo admin_esc((string)($concurso['base_edital'] ?? '')); ?>"></div>
                        <div class="admin-form-field"><label for="prova">Data da prova</label><input id="prova" name="data_prova" type="date" value="<?php echo admin_esc((string)($concurso['data_prova'] ?? '')); ?>"></div>
                        <div class="admin-form-field full"><label>Cargos e o curso de cada um</label>
                            <?php foreach ($cargos as $cg): ?>
                                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:6px"><strong style="min-width:180px"><?php echo admin_esc((string)$cg['nome']); ?></strong>
                                <select name="cargo_curso[<?php echo (int)$cg['id']; ?>]" aria-label="Curso do cargo <?php echo admin_esc((string)$cg['nome']); ?>"><option value="0">— sem curso —</option><option value="novo">+ Criar curso novo para este cargo</option><?php foreach ($cursos as $cu): ?><option value="<?php echo (int)$cu['id']; ?>" <?php echo (int)$cu['id'] === (int)$cg['curso_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$cu['nome']); ?></option><?php endforeach; ?></select></div>
                            <?php endforeach; ?>
                            <input name="novo_cargo" placeholder="Adicionar cargo (ex.: Soldado Bombeiro)" style="margin-top:6px" aria-label="Novo cargo">
                        </div>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i>Salvar e ir para o edital</button></div>
                    </form>
                </section>

            <?php elseif ($aba === 'edital'): $rascunho = (string)hack_rascunho_get($pdo, $concursoId, 'edital_texto', ''); $info = json_decode((string)($concurso['edital_info'] ?? ''), true); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>2. Edital <?php echo $concurso['situacao'] === 'aberto' ? '(aberto)' : '(base: ' . admin_esc((string)($concurso['base_edital'] ?: 'último edital')) . ')'; ?></h2>
                        <?php if (!empty($concurso['edital_pdf'])): ?><a class="admin-link-inline" href="<?php echo admin_esc((string)$concurso['edital_pdf']); ?>" target="_blank" rel="noopener">📄 PDF guardado</a><?php endif; ?></div>
                    <form method="post" enctype="multipart/form-data" class="admin-form-grid">
                        <input type="hidden" name="acao" value="ler_edital"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <div class="admin-form-field"><label for="pdf">PDF do edital (até 15 MB) <?php echo !empty($concurso['edital_pdf']) ? '· já guardado, só envie se for trocar' : ''; ?></label><input id="pdf" type="file" name="pdf" accept="application/pdf"></div>
                        <div class="admin-form-field full"><label for="conteudo">ou cole o texto do edital</label><textarea id="conteudo" name="conteudo" rows="4"></textarea></div>
                        <div class="admin-form-actions" style="position:static;display:flex;gap:8px;flex-wrap:wrap">
                            <button class="admin-btn admin-btn-primary" type="submit" name="parte" value="conteudo"><i data-lucide="sparkles"></i>A IA lê o conteúdo programático</button>
                            <button class="admin-btn admin-btn-light" type="submit" name="parte" value="info"><i data-lucide="sparkles"></i>A IA lê as informações (vagas, salário, requisitos, etapas, datas)</button>
                        </div>
                    </form>
                </section>
                <?php if ($rascunho !== ''): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>Confira o conteúdo programático</h2><span class="ed-salvo">rascunho salvo</span></div>
                    <p class="admin-form-hint">"## Matéria" abre uma matéria; entre colchetes, os cargos (separados por ;) quando a matéria não vale para todos. "- assunto" em cada linha.</p>
                    <form method="post"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <textarea class="ed-mono" name="rascunho" aria-label="Conteúdo programático"><?php echo admin_esc($rascunho); ?></textarea>
                        <div class="admin-form-actions" style="position:static;display:flex;gap:8px"><button class="admin-btn admin-btn-light" type="submit" name="acao" value="salvar_rascunho_edital"><i data-lucide="pause"></i>Salvar e continuar depois</button><button class="admin-btn admin-btn-primary" type="submit" name="acao" value="confirmar_edital"><i data-lucide="check"></i>Confirmar conteúdo programático</button></div>
                    </form>
                </section>
                <?php endif; ?>
                <?php if (is_array($info)): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>Informações do edital</h2></div>
                    <div class="ed-info">
                        <?php foreach ((array)($info['cargos'] ?? []) as $ci): ?>
                            <div><b><?php echo admin_esc((string)($ci['nome'] ?? 'Cargo')); ?></b>Vagas: <?php echo admin_esc((string)($ci['vagas'] ?? '—')); ?><br>Salário: <?php echo admin_esc((string)($ci['salario'] ?? '—')); ?><br>Escolaridade: <?php echo admin_esc((string)($ci['escolaridade'] ?? '—')); ?><br>Idade: <?php echo admin_esc((string)($ci['idade'] ?? '—')); ?> · Altura: <?php echo admin_esc((string)($ci['altura'] ?? '—')); ?></div>
                        <?php endforeach; ?>
                        <div><b>Inscrições</b><?php echo admin_esc(trim(($info['inscricoes']['inicio'] ?? '') . ' a ' . ($info['inscricoes']['fim'] ?? ''), ' a') ?: '—'); ?><br>Taxa: <?php echo admin_esc((string)($info['inscricoes']['taxa'] ?? '—')); ?></div>
                        <div><b>Prova objetiva</b><?php echo admin_esc((string)($info['prova_objetiva']['data'] ?? '—')); ?> · <?php echo admin_esc((string)($info['prova_objetiva']['questoes'] ?? '—')); ?> questões<br><?php echo admin_esc((string)($info['prova_objetiva']['criterio_aprovacao'] ?? '')); ?></div>
                        <div><b>Etapas</b><?php echo admin_esc(implode(' → ', array_map(static fn($e) => (string)($e['nome'] ?? ''), (array)($info['etapas'] ?? [])))) ?: '—'; ?></div>
                    </div>
                    <details style="margin-top:10px"><summary>Corrigir as informações (avançado)</summary>
                        <form method="post"><input type="hidden" name="acao" value="salvar_info"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                            <textarea class="ed-mono" name="info" aria-label="Informações do edital em JSON"><?php echo admin_esc(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                            <button class="admin-btn admin-btn-light admin-btn-small" type="submit">Salvar correção</button></form>
                    </details>
                </section>
                <?php endif; ?>
                <?php if ($itens): ?>
                <section class="admin-panel"><div class="admin-panel-title"><h2>Conteúdo programático confirmado</h2><small><?php echo count($itens); ?> assuntos</small></div>
                    <?php $grupo = []; foreach ($itens as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): ?>
                        <details class="ed-materia"><summary><span><?php echo admin_esc($mat); ?></span><small><?php echo count($lista); ?> assuntos</small></summary>
                        <?php foreach ($lista as $it): ?><div class="ed-assunto"><span>•</span><span><?php echo admin_esc((string)$it['assunto']); ?></span><small><?php $cs = array_filter(array_map('intval', explode(',', (string)$it['cargos']))); echo $cs ? admin_esc(implode(', ', array_map(static fn($i) => $nomeCargo[$i] ?? '', $cs))) : 'todos os cargos'; ?></small></div><?php endforeach; ?>
                        </details>
                    <?php endforeach; ?>
                    <a class="admin-btn admin-btn-primary" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=prova">Próximo: última prova ▶</a>
                </section>
                <?php endif; ?>

            <?php elseif ($aba === 'prova'): $prova = json_decode((string)($concurso['ultima_prova'] ?? ''), true); $provas = job_provas($concurso); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>3. Prova anterior de cada cargo</h2></div>
                    <p class="admin-form-hint">Envie o PDF da prova de <strong>cada cargo</strong>. A IA confere se é mesmo a prova certa e conta as questões por assunto. A prova fica disponível para os alunos com a marca d'água do Projeto Gabaritando.</p>
                    <?php foreach ($cargos as $cg): $pv = $provas[(string)$cg['id']] ?? null; ?>
                        <div style="display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px;align-items:center;padding:12px 0;border-top:1px solid #f3f4f6">
                            <strong><?php echo admin_esc((string)$cg['nome']); ?></strong>
                            <div style="display:grid;gap:6px">
                                <?php if ($pv): ?>
                                    <span><?php echo ($pv['status'] ?? '') === 'confere' ? '✅ Prova conferida' : (($pv['status'] ?? '') === 'nao_confere' ? '⚠️ A IA acha que NÃO é a prova certa' : '🤖 Conferindo…'); ?>
                                        <?php if (!empty($pv['motivo'])): ?> · <?php echo admin_esc((string)$pv['motivo']); ?><?php endif; ?>
                                        <?php if (($pv['status'] ?? '') === 'confere'): ?> · <?php echo admin_esc(trim(($pv['banca'] ?? '') . ' ' . ($pv['ano'] ?? ''))); ?> · <?php echo array_sum((array)($pv['itens'] ?? [])); ?> questões distribuídas<?php endif; ?>
                                        · <a class="admin-link-inline" href="<?php echo admin_esc((string)$pv['arquivo']); ?>" target="_blank" rel="noopener">ver PDF</a></span>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center"><input type="hidden" name="acao" value="enviar_prova"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$cg['id']; ?>"><input type="hidden" name="cargo_nome" value="<?php echo admin_esc((string)$cg['nome']); ?>">
                                    <input type="file" name="prova" accept="application/pdf" required aria-label="PDF da prova de <?php echo admin_esc((string)$cg['nome']); ?>">
                                    <button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><?php echo $pv ? 'Trocar e conferir' : 'Enviar e conferir'; ?></button></form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:center">
                        <form method="post"><input type="hidden" name="acao" value="buscar_prova"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit" <?php echo $itens ? '' : 'disabled'; ?>><i data-lucide="search"></i>Não tenho o PDF: a IA pesquisa na internet</button></form>
                        <form method="post"><input type="hidden" name="acao" value="sem_prova"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Não há prova anterior: pesos estimados</button></form>
                        <a class="admin-btn admin-btn-primary admin-btn-small" style="margin-left:auto" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=aulas">Próximo: aulas ▶</a>
                    </div>
                    <?php if (!empty($prova['encontrada'])): ?><p class="admin-form-hint" style="margin-top:8px">Base em uso no "Hackeando a Prova": <?php echo admin_esc(trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? ''))); ?> (<?php echo admin_esc((string)($prova['fonte'] ?? '')); ?>).</p><?php endif; ?>
                </section>

            <?php elseif ($aba === 'aulas'): $andamento = edital_andamento($pdo, $concursoId); $sugestoes = hack_rascunho_get($pdo, $concursoId, 'sugestoes', []); $links = (string)hack_rascunho_get($pdo, $concursoId, 'links_restantes', '');
                $vaStatus = [];
                try { foreach ($pdo->query("SELECT aula_id, status FROM aula_videoaulas") as $vr) { $vaStatus[(int)$vr['aula_id']] = (string)$vr['status']; } } catch (Throwable $e) {}
            ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>4. Aulas de cada assunto</h2></div>
                    <p class="admin-form-hint" style="margin-bottom:10px">Três caminhos, nesta ordem: <strong>1)</strong> usar vídeos que já existem na plataforma (abaixo), <strong>2)</strong> cadastrar vídeo/PDF do professor no assunto, <strong>3)</strong> o que faltar, a IA gera (teoria mínima + videoaula do Antigão). Legenda: ✅ aula do professor · 🤖 aula do Antigão · ⬜ falta.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px">
                        <form method="post"><input type="hidden" name="acao" value="sugerir_existentes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="search"></i>1. Procurar vídeos já gravados</button></form>
                        <form method="post" onsubmit="return confirm('A IA vai preparar a aula (teoria mínima + videoaula do Antigão) de todos os assuntos sem aula. Continuar?');"><input type="hidden" name="acao" value="gerar_faltantes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>3. A IA gera o que falta</button></form>
                        <a class="admin-btn admin-btn-light" style="margin-left:auto" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=questoes">Próximo: questões ▶</a>
                    </div>
                    <?php foreach ($andamento as $an): $pct = $an['total'] ? (int)round($an['cobertos'] * 100 / $an['total']) : 0;
                        $nProf = 0; $nIa = 0; foreach ($an['itens'] as $x) { if (array_filter($x['aulas'], static fn($a) => trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '')) { $nProf++; } elseif ($x['aulas']) { $nIa++; } } ?>
                        <div style="border:1px solid #eef0f3;border-radius:12px;padding:12px 14px;margin-top:12px">
                            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center"><h3 style="margin:0"><?php echo admin_esc((string)$an['cargo']['nome']); ?></h3><span><strong><?php echo $an['cobertos']; ?>/<?php echo $an['total']; ?></strong> com aula · ✅ <?php echo $nProf; ?> do professor · 🤖 <?php echo $nIa; ?> do Antigão · ⬜ <?php echo $an['total'] - $an['cobertos']; ?> faltam</span></div>
                            <div class="ed-barra" style="height:8px;margin:8px 0"><i style="width:<?php echo $pct; ?>%"></i></div>
                            <?php if (empty($an['cargo']['curso_id'])): ?>
                                <p class="ed-falta">Este cargo ainda não tem curso, então não dá para cadastrar aulas. <a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=concurso">Vá ao passo 1 e escolha "+ Criar curso novo para este cargo"</a>.</p>
                            <?php else: ?>
                            <?php $grupo = []; foreach ($an['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['aulas'])); ?>
                                <details class="ed-materia"><summary><span><?php echo $ok === count($lista) ? '✅' : '⬜'; ?> <?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?> com aula</small></summary>
                                <?php foreach ($lista as $it): $prof = array_values(array_filter($it['aulas'], static fn($a) => trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '')); $a0 = $prof[0] ?? ($it['aulas'][0] ?? null); ?>
                                    <div class="ed-assunto"><span><?php echo $prof ? '✅' : ($it['aulas'] ? '🤖' : '⬜'); ?></span>
                                        <div><?php echo admin_esc((string)$it['assunto']); ?>
                                            <small><?php if ($a0): ?><?php echo admin_esc((string)$a0['titulo']); ?><?php if (!$prof): $vs = $vaStatus[(int)$a0['id']] ?? ''; ?> · 🎬 videoaula do Antigão: <?php echo $vs === 'pronto' ? 'pronta' : 'em preparo (Central da IA)'; ?><?php endif; ?> · <a class="admin-link-inline" target="_blank" href="aulas.php?curso_id=<?php echo (int)$a0['curso_id']; ?>&modulo_id=<?php echo (int)$a0['modulo_id']; ?>&aula_id=<?php echo (int)$a0['id']; ?>">▶ ver como aluno</a><?php else: ?>sem aula<?php endif; ?></small></div>
                                        <details><summary class="admin-btn admin-btn-light admin-btn-small" style="list-style:none;cursor:pointer"><?php echo $prof ? 'Trocar' : '+ Vídeo/PDF do professor'; ?></summary>
                                            <form method="post" enctype="multipart/form-data" style="display:grid;gap:6px;margin-top:6px;min-width:260px"><input type="hidden" name="acao" value="aula_item"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>">
                                                <input type="url" name="video" placeholder="Link do vídeo (YouTube)" aria-label="Link do vídeo de <?php echo admin_esc((string)$it['assunto']); ?>">
                                                <label class="admin-form-hint">ou PDF: <input type="file" name="pdf_aula" accept="application/pdf"></label>
                                                <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Salvar</button></form></details>
                                    </div>
                                <?php endforeach; ?>
                                </details>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
                <?php $exist = hack_rascunho_get($pdo, $concursoId, 'existentes', []); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>🎬 Vídeos que já estão na plataforma</h2>
                        <form method="post"><input type="hidden" name="acao" value="sugerir_existentes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit"><i data-lucide="sparkles"></i><?php echo $exist ? 'Procurar de novo' : 'A IA procura vídeos já gravados'; ?></button></form></div>
                    <?php if (!$exist): ?><p class="admin-form-hint">A IA compara as aulas já gravadas (de todos os cursos) com o conteúdo programático e sugere onde usar cada uma.</p>
                    <?php else: ?>
                    <form method="post"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <?php $nomeItem = []; foreach ($itens as $it) { $nomeItem[(int)$it['id']] = $it['materia'] . ' › ' . $it['assunto']; } foreach ($exist as $i => $e): ?>
                            <label class="ed-assunto" style="cursor:pointer"><input type="checkbox" name="e[]" value="<?php echo $i; ?>" checked><div><?php echo admin_esc((string)$e['aula']); ?><small>→ <?php echo admin_esc($nomeItem[(int)$e['item_id']] ?? ''); ?> · <?php echo admin_esc((string)$e['motivo']); ?></small></div><span class="ed-tag"><?php echo (int)$e['confianca']; ?>%</span></label>
                        <?php endforeach; ?>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                            <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="acao" value="aceitar_existentes" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=todas value=1>')">✓ Aceitar todas (<?php echo count($exist); ?>)</button>
                            <button class="admin-btn admin-btn-light admin-btn-small" type="submit" name="acao" value="aceitar_existentes">Aceitar só as marcadas</button>
                            <button class="admin-btn admin-btn-light admin-btn-small" type="submit" name="acao" value="descartar_existentes">Descartar</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </section>
                <details class="admin-panel"<?php echo $sugestoes ? ' open' : ''; ?>>
                    <summary style="font-weight:700;cursor:pointer">Tem muitos vídeos? Cole vários links e a IA diz o assunto de cada um</summary>
                    <form method="post" style="margin-top:10px"><input type="hidden" name="acao" value="sugerir_aulas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <textarea name="links" rows="5" style="width:100%" aria-label="Links" placeholder="Um link por linha. Opcional: link | título"><?php echo admin_esc($links); ?></textarea>
                        <button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><i data-lucide="sparkles"></i>Sugerir assunto e cargo</button>
                    </form>
                    <?php if ($sugestoes): ?>
                    <form method="post"><input type="hidden" name="acao" value="cadastrar_aulas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <?php foreach ($sugestoes as $i => $s): ?>
                            <div class="ed-sug">
                                <input type="checkbox" name="s[<?php echo $i; ?>][usar]" value="1" checked aria-label="Cadastrar">
                                <div><input type="text" name="s[<?php echo $i; ?>][titulo]" value="<?php echo admin_esc((string)$s['titulo']); ?>" aria-label="Título"><small><a href="<?php echo admin_esc((string)$s['url']); ?>" target="_blank" rel="noopener">ver vídeo</a> · <?php echo admin_esc((string)$s['motivo']); ?></small></div>
                                <select name="s[<?php echo $i; ?>][item]" aria-label="Assunto"><option value="0">— assunto —</option><?php foreach ($itens as $it): ?><option value="<?php echo (int)$it['id']; ?>" <?php echo (int)$it['id'] === (int)$s['edital_item_id'] ? 'selected' : ''; ?>><?php echo admin_esc($it['materia'] . ' › ' . $it['assunto']); ?></option><?php endforeach; ?></select>
                                <div><?php foreach ($cargos as $cg): ?><label style="display:block;font-size:13px"><input type="checkbox" name="s[<?php echo $i; ?>][cargos][]" value="<?php echo (int)$cg['id']; ?>" <?php echo in_array((int)$cg['id'], (array)$s['cargos'], true) ? 'checked' : ''; ?>> <?php echo admin_esc((string)$cg['nome']); ?></label><?php endforeach; ?></div>
                                <span class="ed-tag"><?php echo (int)$s['confianca']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                        <div style="display:flex;gap:8px;margin-top:10px"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Cadastrar marcadas</button></div>
                    </form>
                    <form method="post"><input type="hidden" name="acao" value="limpar_sugestoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Descartar sugestões</button></form>
                    <?php endif; ?>
                </details>

            <?php elseif ($aba === 'questoes'): $qs = hack_questoes_situacao($pdo, $concursoId); $pctQ = $qs['total'] ? (int)round($qs['ok'] * 100 / $qs['total']) : 0; $totQ = array_sum(array_column($qs['itens'], 'questoes')); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>5. Questões de cada assunto</h2><strong><?php echo $qs['ok']; ?>/<?php echo $qs['total']; ?> assuntos prontos (10+ questões) · <?php echo $totQ; ?> questões</strong></div>
                    <div class="ed-barra" style="height:10px;margin-bottom:12px"><i style="width:<?php echo $pctQ; ?>%"></i></div>
                    <p class="admin-form-hint" style="margin-bottom:10px">O jeito mais simples: clique no botão amarelo. A IA faz assunto por assunto: <strong>pesquisa questões reais</strong> de provas anteriores (mesma banca primeiro) e, se não achar 10, <strong>cria no nível da prova</strong>. O andamento aparece na Central da IA, acima. Pode fechar a página: ela continua.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                        <form method="post"><input type="hidden" name="acao" value="completar_questoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>Completar todos os assuntos com a IA</button></form>
                        <details><summary class="admin-btn admin-btn-light" style="list-style:none;cursor:pointer">📤 Tenho questões para enviar</summary>
                            <form method="post" enctype="multipart/form-data" style="display:grid;gap:6px;margin-top:8px;min-width:300px"><input type="hidden" name="acao" value="enviar_questoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                                <label class="admin-form-hint">PDF (prova ou lista): <input type="file" name="arquivo" accept="application/pdf"></label><textarea name="texto" rows="3" placeholder="ou cole as questões aqui" aria-label="Questões em texto"></textarea>
                                <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Enviar: a IA separa por assunto</button></form></details>
                        <a class="admin-btn admin-btn-light" style="margin-left:auto" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=plano">Próximo: Plano-Mestre ▶</a>
                    </div>
                    <?php $grupo = []; foreach ($qs['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['questoes'] >= 10)); ?>
                        <details class="ed-materia"><summary><span><?php echo $ok === count($lista) ? '✅' : '⬜'; ?> <?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?> prontos · <?php echo array_sum(array_column($lista, 'questoes')); ?> questões</small></summary>
                        <?php foreach ($lista as $it): ?>
                            <div class="ed-assunto"><span><?php echo $it['questoes'] >= 10 ? '✅' : '⬜'; ?></span><div><?php echo admin_esc((string)$it['assunto']); ?><small><?php echo (int)$it['questoes']; ?> questões<?php if ($it['questoes']): ?> · <a class="admin-link-inline" target="_blank" href="banco-questoes.php?topico_id=<?php echo (int)$it['topico_id']; ?>">ver como aluno</a><?php endif; ?></small></div>
                            <?php if ($it['questoes'] < 10): ?><form method="post"><input type="hidden" name="acao" value="questoes_item"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Fazer só este</button></form><?php endif; ?></div>
                        <?php endforeach; ?></details>
                    <?php endforeach; ?>
                </section>

            <?php elseif ($aba === 'plano'): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>6. Plano-Mestre por cargo</h2></div>
                    <p class="admin-form-hint">A IA ordena os assuntos, dá peso e complexidade com base na última prova. Você confere a justificativa e aprova.</p>
                    <?php foreach ($cargos as $cg):
                        $ap = 0;
                        if (!empty($cg['curso_id'])) { $p = $pdo->prepare("SELECT status FROM meta_planos WHERE curso_id = :c ORDER BY (status = 'aprovado') DESC, id DESC LIMIT 1"); $p->execute([':c' => (int)$cg['curso_id']]); $ap = (string)$p->fetchColumn(); } ?>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid #f3f4f6">
                            <strong style="min-width:200px"><?php echo admin_esc((string)$cg['nome']); ?></strong>
                            <span class="ed-tag <?php echo $ap === 'aprovado' ? 'aberto' : 'previsto'; ?>"><?php echo $ap === 'aprovado' ? 'Plano aprovado' : ($ap ? 'Rascunho para aprovar' : 'Sem plano'); ?></span>
                            <form method="post"><input type="hidden" name="acao" value="sugerir_plano"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$cg['id']; ?>"><input type="hidden" name="cargo_nome" value="<?php echo admin_esc((string)$cg['nome']); ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit" <?php echo empty($cg['curso_id']) || !$itens ? 'disabled' : ''; ?>><i data-lucide="sparkles"></i><?php echo $ap ? 'Sugerir de novo' : 'Sugerir plano'; ?></button></form>
                            <?php if ($ap): ?><a class="admin-btn admin-btn-light admin-btn-small" href="admin-meta-plano.php?curso_id=<?php echo (int)$cg['curso_id']; ?>">Abrir e aprovar</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

            <?php else: $hacks = json_decode((string)($concurso['hacks'] ?? ''), true) ?: []; ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>7. Hackeando o Edital e a Prova (o que o aluno vê)</h2>
                        <form method="post"><input type="hidden" name="acao" value="gerar_hacks"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><i data-lucide="sparkles"></i><?php echo $hacks ? 'Refazer para todos os cargos' : 'Antigão escreve para todos os cargos'; ?></button></form></div>
                    <p class="admin-form-hint">Use depois do edital e das provas. O Antigão gera, para cada cargo, o <strong>edital verticalizado</strong> (assuntos por disciplina com complexidade, peso e custo-benefício) e os textos <strong>Hackeando o Edital/Prova</strong>. O aluno vê tudo em "Meu concurso" e baixa o verticalizado em PDF com a logo.</p>
                    <?php $vert = json_decode((string)($concurso['verticalizado'] ?? ''), true) ?: []; foreach ($cargos as $cg): $v = $vert[(string)$cg['id']] ?? null; ?>
                        <p>📋 <strong><?php echo admin_esc((string)$cg['nome']); ?></strong> · verticalizado: <?php echo $v ? 'pronto (' . admin_esc((string)$v['gerado_em']) . ') · <a class="admin-link-inline" target="_blank" href="edital-verticalizado.php?concurso_id=' . $concursoId . '&cargo_id=' . (int)$cg['id'] . '">ver / PDF</a>' : 'pendente'; ?></p>
                    <?php endforeach; ?>
                    <?php foreach ($cargos as $cg): $h = $hacks[(string)$cg['id']] ?? null; ?>
                        <details class="ed-materia"><summary><span><?php echo admin_esc((string)$cg['nome']); ?></span><small><?php echo $h ? 'pronto · ' . admin_esc((string)$h['gerado_em']) : 'pendente'; ?></small></summary>
                            <?php if ($h): ?><div style="padding:10px 14px"><p><em><?php echo admin_esc((string)$h['resumo_antigao']); ?></em></p>
                                <?php foreach (['hack_edital' => '🕵️ Hackeando o Edital', 'hack_prova' => '🎯 Hackeando a Prova'] as $k => $t): ?><h4><?php echo $t; ?></h4>
                                    <?php foreach ($h[$k] as $b): ?><div class="ed-hack"><strong><?php echo admin_esc($b['icone'] . ' ' . $b['titulo']); ?></strong><br><?php echo admin_esc($b['texto']); ?></div><?php endforeach; ?>
                                <?php endforeach; ?></div><?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<script>
// Central da IA: mostra as tarefas ao vivo, faz a fila andar e atualiza a página quando algo termina.
(function () {
    var box = document.getElementById('centralIA');
    if (!box) { return; }
    var c = box.dataset.concurso, rodando = false, ativosAntes = null, ico = {pendente: '⏳', rodando: '🤖', feito: '✅', erro: '⚠️'};
    function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }
    function rodar() {
        if (rodando) { return; }
        rodando = true;
        fetch('api/edital-job.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({concurso_id: c})})
            .catch(function () {}).then(function () { rodando = false; atualizar(); });
    }
    function atualizar() {
        fetch('api/edital-job.php?concurso_id=' + c, {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (d) {
            var q = d.questoes || {};
            document.getElementById('ciaQuestoes').innerHTML = '📚 Questões: <strong>' + (q.assuntos_ok || 0) + '/' + (q.assuntos_total || 0) + '</strong> assuntos com 10+ · ' + (q.total_questoes || 0) + ' questões no banco · ' + (q.na_fila || 0) + ' assunto(s) na fila' + (q.erros ? ' · ' + q.erros + ' com erro' : '');
            document.getElementById('ciaQBarra').style.width = (q.assuntos_total ? Math.round(q.assuntos_ok * 100 / q.assuntos_total) : 0) + '%';
            document.getElementById('ciaJobs').innerHTML = (d.jobs || []).map(function (j) { return '<li>' + ico[j.status] + ' <strong>' + esc(j.rotulo) + '</strong>' + (j.mensagem ? ' · ' + esc(j.mensagem) : (j.status === 'rodando' ? ' · trabalhando…' : '')) + '</li>'; }).join('') || '<li class="admin-form-hint">Nenhuma tarefa agora.</li>';
            var nomes = {aula_ia: '🤖 Aulas do Antigão (teoria)', apostila: '📄 Apostilas', videoaula: '🎬 Videoaulas do Antigão', podcast: '🎧 Podcasts'}, filaPend = 0, linhas = '';
            Object.keys(nomes).forEach(function (t) { var f = (d.fila || {})[t]; if (!f) { return; } var feito = f.feito || 0, pend = f.pendente || 0, err = f.erro || 0, tot = feito + pend + err; filaPend += pend;
                linhas += '<div>' + nomes[t] + ': <strong>' + feito + '/' + tot + '</strong> prontas' + (pend ? ' · ' + pend + ' na fila' : '') + (err ? ' · ' + err + ' com erro (tenta de novo sozinho)' : '') + '</div>'; });
            document.getElementById('ciaAulas').innerHTML = linhas;
            var ativos = (d.ativos || 0) + (q.na_fila || 0) + filaPend;
            document.getElementById('ciaStatus').textContent = ativos ? 'IA trabalhando · ' + ativos + ' tarefa(s) · pode navegar, ela continua' : 'Tudo em dia';
            if (ativosAntes !== null && (d.ativos || 0) < ativosAntes) { location.reload(); return; }
            ativosAntes = d.ativos || 0;
            if (ativos) { rodar(); }
        }).catch(function () {});
    }
    atualizar();
    setInterval(atualizar, 5000);
})();
</script>
<?php admin_cursos_scripts(); ?>
</body>
</html>
