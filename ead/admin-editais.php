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
require_once __DIR__ . '/includes/edital-hack-lib.php';
@set_time_limit(320);

$pdo = admin_cursos_db();
hack_bootstrap($pdo);
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
            $pdf = hack_pdf_base64($pdfUrl);
            if ($texto === '' && $pdf === '') { throw new RuntimeException('Envie o PDF do edital ou cole o texto.'); }
            $cg = $pdo->prepare('SELECT nome FROM concurso_cargos WHERE concurso_id = :c');
            $cg->execute([':c' => $concursoId]);
            $nomesCargos = $cg->fetchAll(PDO::FETCH_COLUMN);
            if (($_POST['parte'] ?? 'conteudo') === 'info') {
                $info = hack_ia_info($texto, $pdf, $nomesCargos);
                $pdo = podcast_reconectar($pdo);
                $pdo->prepare('UPDATE concursos SET edital_info = :i WHERE id = :id')->execute([':i' => json_encode($info, JSON_UNESCAPED_UNICODE), ':id' => $concursoId]);
                ed_ir($concursoId, 'edital', 'Informações do edital lidas (vagas, salário, requisitos, etapas, datas). Confira abaixo.');
            }
            $lido = edital_ia_ler($texto, $pdf, $nomesCargos);
            $pdo = podcast_reconectar($pdo);
            hack_rascunho_set($pdo, $concursoId, 'edital_texto', ed_para_texto($lido));
            ed_ir($concursoId, 'edital', 'A IA leu o conteúdo programático. Confira, ajuste e confirme. (Fica salvo se você sair.)');
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
        if ($acao === 'buscar_prova') {
            $prova = edital_ultima_prova($pdo, $concursoId, ed_pdf_enviado('prova'));
            $pdo = podcast_reconectar($pdo);
            $pdo->prepare('UPDATE concursos SET prova_status = NULL WHERE id = :id')->execute([':id' => $concursoId]);
            ed_ir($concursoId, 'prova', !empty($prova['encontrada']) ? 'Última prova encontrada: ' . trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? '')) . '.' : 'Não encontrei a última prova. Envie o PDF ou marque "sem prova anterior".');
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
            $sug = hack_rascunho_get($pdo, $concursoId, 'sugestoes', []);
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
            hack_rascunho_set($pdo, $concursoId, 'sugestoes', $sug);
            hack_rascunho_set($pdo, $concursoId, 'links_restantes', $restantes ? implode("\n", $restantes) : null);
            ed_ir($concursoId, 'aulas', count($sug) . ' sugestão(ões) para conferir.' . ($restantes ? ' ' . count($restantes) . ' link(s) ficaram para a próxima rodada.' : ''));
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
            $n = hack_questoes_enviadas($pdo, $concursoId, trim((string)($_POST['texto'] ?? '')), ed_pdf_enviado('arquivo'));
            ed_ir($concursoId, 'questoes', $n . ' questão(ões) separadas por assunto e gravadas no banco.');
        }
        if ($acao === 'completar_questoes') {
            $n = 0;
            foreach (hack_questoes_situacao($pdo, $concursoId)['itens'] as $it) {
                if ($it['questoes'] < 10) { ia_fila_adicionar($pdo, 'questoes', (int)$it['id']); $n++; }
            }
            ed_ir($concursoId, 'questoes', $n . ' assunto(s) na fila: a IA pesquisa questões reais na internet e, se não achar, cria no nível da prova.');
        }
        if ($acao === 'questoes_item') {
            [$r, $g] = hack_questoes_do_item($pdo, (int)($_POST['item_id'] ?? 0));
            ed_ir($concursoId, 'questoes', "Feito: $r questão(ões) reais encontradas e $g criadas no nível da prova.");
        }

        // 6 · Plano-Mestre
        if ($acao === 'sugerir_plano') {
            $cargoId = (int)($_POST['cargo_id'] ?? 0);
            $st = $pdo->prepare('SELECT ultima_prova FROM concursos WHERE id = :id');
            $st->execute([':id' => $concursoId]);
            $prova = json_decode((string)$st->fetchColumn(), true) ?: ['encontrada' => false];
            edital_sugerir_plano($pdo, $concursoId, $cargoId, $prova);
            $cursoDoCargo = $pdo->prepare('SELECT curso_id FROM concurso_cargos WHERE id = :id');
            $cursoDoCargo->execute([':id' => $cargoId]);
            $_SESSION['edital_msg'] = 'Plano-Mestre sugerido (rascunho). Confira a justificativa de cada assunto e aprove. Depois volte ao cadastro do concurso pelo menu.';
            header('Location: admin-meta-plano.php?curso_id=' . (int)$cursoDoCargo->fetchColumn());
            exit;
        }

        // 7 · Hackeando para o aluno
        if ($acao === 'gerar_hacks') {
            $cg = $pdo->prepare('SELECT id FROM concurso_cargos WHERE concurso_id = :c');
            $cg->execute([':c' => $concursoId]);
            $alvo = (int)($_POST['cargo_id'] ?? 0);
            $n = 0;
            foreach ($cg->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                if ($alvo && $alvo !== (int)$cid) { continue; }
                hack_ia_gerar($pdo, $concursoId, (int)$cid);
                $pdo = podcast_reconectar($pdo);
                $n++;
            }
            ed_ir($concursoId, 'publicar', 'Antigão escreveu "Hackeando o Edital" e "Hackeando a Prova" para ' . $n . ' cargo(s). Já aparece para os alunos em "Meu concurso".');
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

            <?php elseif ($aba === 'prova'): $prova = json_decode((string)($concurso['ultima_prova'] ?? ''), true); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>3. Última prova (base do "Hackeando a Prova" e dos pesos do plano)</h2></div>
                    <?php if (!empty($prova['encontrada'])): ?>
                        <p>✅ <strong><?php echo admin_esc(trim(($prova['banca'] ?? '') . ' ' . ($prova['ano'] ?? ''))); ?></strong> · fonte: <?php echo admin_esc((string)($prova['fonte'] ?? '')); ?> · <?php echo array_sum((array)$prova['itens']); ?> questões distribuídas pelos assuntos.</p>
                    <?php elseif (($concurso['prova_status'] ?? '') === 'sem_prova'): ?>
                        <p>Marcado como <strong>sem prova anterior</strong>: os pesos serão estimados.</p>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                        <input type="hidden" name="acao" value="buscar_prova"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <label class="admin-form-hint">PDF da prova (opcional): <input type="file" name="prova" accept="application/pdf"></label>
                        <button class="admin-btn admin-btn-primary" type="submit" <?php echo $itens ? '' : 'disabled'; ?>><i data-lucide="search"></i>Sem PDF: a IA pesquisa na internet</button>
                    </form>
                    <form method="post" style="margin-top:10px"><input type="hidden" name="acao" value="sem_prova"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Não há prova anterior: seguir com pesos estimados</button></form>
                </section>

            <?php elseif ($aba === 'aulas'): $andamento = edital_andamento($pdo, $concursoId); $sugestoes = hack_rascunho_get($pdo, $concursoId, 'sugestoes', []); $links = (string)hack_rascunho_get($pdo, $concursoId, 'links_restantes', ''); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>4. Aulas por assunto do conteúdo programático</h2>
                        <form method="post" onsubmit="return confirm('A IA vai preparar a aula (teoria mínima + videoaula do Antigão) de todos os assuntos que ainda não têm aula. Continuar?');"><input type="hidden" name="acao" value="gerar_faltantes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><i data-lucide="sparkles"></i>A IA gera o que falta</button></form></div>
                    <p class="admin-form-hint">Em cada assunto: cole o link do vídeo e/ou envie o PDF. O que ficar sem aula, a IA gera (teoria mínima + videoaula do Antigão) e você troca quando a aula do professor chegar.</p>
                    <?php foreach ($andamento as $an): $pct = $an['total'] ? (int)round($an['cobertos'] * 100 / $an['total']) : 0; ?>
                        <h3 style="margin:14px 0 6px"><?php echo admin_esc((string)$an['cargo']['nome']); ?> · <?php echo $an['cobertos']; ?>/<?php echo $an['total']; ?> (<?php echo $pct; ?>%)</h3>
                        <?php if (empty($an['cargo']['curso_id'])): ?><p class="ed-falta">Cargo sem curso. <a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=concurso">Ligar no passo 1</a></p><?php endif; ?>
                        <?php $grupo = []; foreach ($an['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['aulas'])); ?>
                            <details class="ed-materia"<?php echo $ok < count($lista) ? ' open' : ''; ?>><summary><span><?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?></small></summary>
                            <?php foreach ($lista as $it): $temProf = array_filter($it['aulas'], static fn($a) => trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== ''); ?>
                                <div class="ed-assunto"><span><?php echo $temProf ? '✅' : ($it['aulas'] ? '🤖' : '⬜'); ?></span>
                                    <div><?php echo admin_esc((string)$it['assunto']); ?><small><?php echo $it['aulas'] ? admin_esc(implode(' · ', array_column($it['aulas'], 'titulo'))) : 'sem aula'; ?></small></div>
                                    <form method="post" enctype="multipart/form-data"><input type="hidden" name="acao" value="aula_item"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>">
                                        <input type="url" name="video" placeholder="Link do vídeo (opcional)" aria-label="Link do vídeo de <?php echo admin_esc((string)$it['assunto']); ?>">
                                        <input type="file" name="pdf_aula" accept="application/pdf" aria-label="PDF de <?php echo admin_esc((string)$it['assunto']); ?>">
                                        <button class="admin-btn admin-btn-light admin-btn-small" type="submit"><?php echo $temProf ? 'Adicionar' : 'Salvar'; ?></button></form>
                                </div>
                            <?php endforeach; ?>
                            </details>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
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

            <?php elseif ($aba === 'questoes'): $qs = hack_questoes_situacao($pdo, $concursoId); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>5. Questões por assunto</h2><strong><?php echo $qs['ok']; ?>/<?php echo $qs['total']; ?> assuntos com 10+ questões</strong></div>
                    <div class="ed-opcoes">
                        <div><h3>📤 Enviar questões</h3><p class="admin-form-hint">PDF de provas/listas ou texto colado. A IA separa por assunto, confere o gabarito e comenta.</p>
                            <form method="post" enctype="multipart/form-data" style="display:grid;gap:6px"><input type="hidden" name="acao" value="enviar_questoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                                <input type="file" name="arquivo" accept="application/pdf" aria-label="PDF de questões"><textarea name="texto" rows="3" placeholder="ou cole as questões" aria-label="Questões em texto"></textarea>
                                <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Enviar</button></form></div>
                        <div><h3>🔎 Pesquisar na internet</h3><p class="admin-form-hint">Para cada assunto com menos de 10: a IA busca <strong>questões reais</strong> de provas anteriores (mesma banca primeiro), com fonte.</p></div>
                        <div><h3>🤖 Gerar no nível da prova</h3><p class="admin-form-hint">Se a pesquisa não achar o suficiente, a IA cria questões inéditas no estilo e no nível da banca, marcadas como "Questão do Antigão".</p></div>
                    </div>
                    <form method="post" style="margin-top:12px"><input type="hidden" name="acao" value="completar_questoes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>Completar todos os assuntos (pesquisa → gera o que faltar)</button></form>
                    <?php $grupo = []; foreach ($qs['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['questoes'] >= 10)); ?>
                        <details class="ed-materia"<?php echo $ok < count($lista) ? ' open' : ''; ?>><summary><span><?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?></small></summary>
                        <?php foreach ($lista as $it): ?>
                            <div class="ed-assunto"><span><?php echo $it['questoes'] >= 10 ? '✅' : '⬜'; ?></span><div><?php echo admin_esc((string)$it['assunto']); ?><small><?php echo (int)$it['questoes']; ?> questões no banco</small></div>
                            <?php if ($it['questoes'] < 10): ?><form method="post"><input type="hidden" name="acao" value="questoes_item"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Completar agora</button></form><?php endif; ?></div>
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
                            <form method="post"><input type="hidden" name="acao" value="sugerir_plano"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$cg['id']; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit" <?php echo empty($cg['curso_id']) || !$itens ? 'disabled' : ''; ?>><i data-lucide="sparkles"></i><?php echo $ap ? 'Sugerir de novo' : 'Sugerir plano'; ?></button></form>
                            <?php if ($ap): ?><a class="admin-btn admin-btn-light admin-btn-small" href="admin-meta-plano.php?curso_id=<?php echo (int)$cg['curso_id']; ?>">Abrir e aprovar</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

            <?php else: $hacks = json_decode((string)($concurso['hacks'] ?? ''), true) ?: []; ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>7. Hackeando o Edital e a Prova (o que o aluno vê)</h2>
                        <form method="post"><input type="hidden" name="acao" value="gerar_hacks"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary admin-btn-small" type="submit"><i data-lucide="sparkles"></i><?php echo $hacks ? 'Refazer para todos os cargos' : 'Antigão escreve para todos os cargos'; ?></button></form></div>
                    <p class="admin-form-hint">Use depois do edital e da última prova. O aluno vê em "Meu concurso", ao escolher o cargo.</p>
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
<?php admin_cursos_scripts(); ?>
</body>
</html>
