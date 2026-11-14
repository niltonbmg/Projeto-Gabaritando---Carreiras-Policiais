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
require_once __DIR__ . '/includes/provas-escritas-lib.php';
require_once __DIR__ . '/includes/ia-consumo-lib.php';
@set_time_limit(320);

$pdo = admin_cursos_db();
hack_bootstrap($pdo);
job_bootstrap($pdo);
ia_fila_bootstrap($pdo);

const ED_PASSOS = ['concurso' => 'Concurso e cargos', 'edital' => 'Edital', 'escritas' => 'Provas escritas', 'prova' => 'Última prova', 'aulas' => 'Aulas', 'questoes' => 'Questões', 'plano' => 'Plano-Mestre', 'publicar' => 'Hackeando'];

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
            [$sa, $sq] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, 'edital', 'Concurso salvo.' . ($sa || $sq ? " Aproveitado da plataforma: $sa aula(s) e $sq questão(ões) dos mesmos assuntos." : '') . ' Próximo passo: o edital.');
        }

        if ($acao === 'copiar_modulos') {
            $cg = $pdo->prepare('SELECT curso_id, nome FROM concurso_cargos WHERE id = :id AND concurso_id = :c');
            $cg->execute([':id' => (int)($_POST['cargo_id'] ?? 0), ':c' => $concursoId]);
            $cargoC = $cg->fetch();
            if (!$cargoC || empty($cargoC['curso_id'])) { throw new RuntimeException('Escolha ou crie o curso do cargo primeiro.'); }
            if (empty($_POST['modulos'])) { throw new RuntimeException('Marque pelo menos uma matéria para copiar.'); }
            [$nm, $na] = job_copiar_modulos($pdo, (int)$cargoC['curso_id'], (array)$_POST['modulos']);
            ed_ir($concursoId, 'concurso', "Copiado para {$cargoC['nome']}: $nm matéria(s) com $na aula(s). O que faltar, a IA completa no passo 4.");
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
            [$sa, $sq] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, 'edital', $n . ' assuntos no conteúdo programático.' . ($sa || $sq ? " Já aproveitado da plataforma: $sa aula(s) e $sq questão(ões) dos mesmos assuntos." : ''));
        }
        // 2b · Provas escritas (redação e discursiva por cargo)
        if ($acao === 'ler_escritas') {
            $stC = $pdo->prepare('SELECT edital_pdf FROM concursos WHERE id = :id');
            $stC->execute([':id' => $concursoId]);
            $texto = trim((string)($_POST['texto'] ?? ''));
            if ($texto === '' && trim((string)$stC->fetchColumn()) === '') { throw new RuntimeException('Não há PDF do edital guardado. Cole o trecho do edital que fala das provas.'); }
            job_criar($pdo, $concursoId, 'ler_escritas', 'Identificando redação e prova discursiva no edital', ['texto' => mb_substr($texto, 0, 80000)]);
            ed_ir($concursoId, 'escritas', '🤖 A IA está lendo a parte de redação e discursiva do edital (1 pedido de texto). Quando terminar, confira aqui.');
        }
        if ($acao === 'salvar_escritas') {
            $lista = [];
            foreach ((array)($_POST['pe'] ?? []) as $x) {
                $x = (array)$x;
                $crit = [];
                foreach (preg_split('~\R~', (string)($x['redacao']['criterios_txt'] ?? '')) as $linha) {
                    $partes = array_map('trim', explode('|', $linha));
                    if (($partes[0] ?? '') !== '') { $crit[] = ['nome' => $partes[0], 'max' => $partes[1] ?? 0, 'descricao' => $partes[2] ?? '']; }
                }
                $x['redacao']['criterios'] = $crit;
                $lista[] = ['cargo' => (string)($x['cargo'] ?? '')] + pe_normalizar($x);
            }
            pe_salvar($pdo, $concursoId, $lista, true);
            $nPe = pe_criar_assuntos($pdo, $concursoId);
            [$saPe] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, 'escritas', '✅ Provas escritas conferidas. ' . ($nPe ? "$nPe assunto(s) de Redação/Discursiva entraram no conteúdo: veja no passo Aulas (para gerar as aulas, use \"A IA procura aulas pelo assunto\" ou \"A IA gera o que falta\"). " : '') . ($saPe ? "$saPe aula(s) já aproveitada(s) da plataforma. " : '') . 'Menu, cronograma e correção já seguem o edital.');
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
            // A aula do professor substitui a aula provisória do Antigão SÓ nos cursos destes cargos;
            // o plano e o progresso dos alunos passam para a aula nova.
            $cursosAlvo = [];
            $stC = $pdo->prepare('SELECT curso_id FROM concurso_cargos WHERE id = :id');
            foreach ($cargosItem as $cgId) { $stC->execute([':id' => $cgId]); $cursosAlvo[] = (int)$stC->fetchColumn(); }
            $novas = hack_aula_do_item($pdo, $itemId, $cargosItem, $titulo, $url, $pdf);
            $stP = $pdo->prepare("SELECT id, curso_id FROM admin_curso_aulas WHERE topico_id = :t AND curso_id IN (" . implode(',', array_map('intval', $cursosAlvo ?: [0])) . ") AND COALESCE(video_url,'') = '' AND COALESCE(pdf_arquivo,'') = '' AND descricao LIKE '%aula-ia-aviso%'");
            $stP->execute([':t' => (int)$item['topico_id']]);
            $novaPorCurso = [];
            $stN = $pdo->prepare('SELECT curso_id FROM admin_curso_aulas WHERE id = :id');
            foreach ($novas as $nid) { $stN->execute([':id' => $nid]); $novaPorCurso[(int)$stN->fetchColumn()] = $nid; }
            foreach ($stP->fetchAll() as $prov) {
                $nova = $novaPorCurso[(int)$prov['curso_id']] ?? 0;
                if (!$nova || $nova === (int)$prov['id']) { continue; }
                try { $pdo->prepare('UPDATE meta_plano_itens SET aula_id = :n WHERE aula_id = :o')->execute([':n' => $nova, ':o' => (int)$prov['id']]); } catch (Throwable $e) {}
                try { $pdo->prepare('UPDATE IGNORE aulas_progresso SET aula_id = :n WHERE aula_id = :o')->execute([':n' => $nova, ':o' => (int)$prov['id']]); } catch (Throwable $e) {}
                foreach (['aula_videoaulas', 'aula_podcasts'] as $tb) { try { $pdo->prepare("DELETE FROM $tb WHERE aula_id = :o")->execute([':o' => (int)$prov['id']]); } catch (Throwable $e) {} }
                $pdo->prepare("DELETE FROM ia_fila WHERE aula_id = :o AND tipo IN ('aula_ia','apostila','videoaula','podcast')")->execute([':o' => (int)$prov['id']]);
                $pdo->prepare('DELETE FROM admin_curso_aulas WHERE id = :o')->execute([':o' => (int)$prov['id']]);
            }
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
        if ($acao === 'aproveitar_cargos') {
            $n = job_aproveitar_entre_cargos($pdo, $concursoId, (int)($_POST['cargo_id'] ?? 0));
            ed_ir($concursoId, 'aulas', $n . ' aula(s) aproveitadas de outro cargo nos assuntos em comum (com vídeo, PDF, apostila, videoaula e podcast). Para o que é só deste cargo: "1. Procurar vídeos já gravados" e depois "3. A IA gera o que falta".');
        }
        if ($acao === 'conferir_conteudo') {
            job_criar($pdo, $concursoId, 'conferir_conteudo', 'A IA confere se faltou assunto do edital no cadastro', ['texto' => mb_substr(trim((string)($_POST['texto'] ?? '')), 0, 120000)]);
            ed_ir($concursoId, 'edital', '🤖 A IA está conferindo o edital inteiro contra o cadastro (1 pedido de texto). Os assuntos que faltarem aparecem aqui para você aprovar.');
        }
        if ($acao === 'ligar_cadastro_ia') {
            require_once __DIR__ . '/includes/ia-consumo-lib.php';
            $ctlE = ia_controle();
            $ctlE['ligados']['edital'] = true;
            ia_controle_salvar(['ligados' => $ctlE['ligados'], 'modo' => $ctlE['modo'], 'teto' => $ctlE['teto_manual']]);
            ed_ir($concursoId, (string)($_GET['aba'] ?? $_POST['aba'] ?? 'aulas'), '✅ Tarefas da IA do Cadastro ligadas. As que estão com ⏳ começam a andar assim que houver cota de texto (o robô roda a cada 5 minutos).');
        }
        if ($acao === 'editar_conteudo') {
            $nCargos = (int)$pdo->query('SELECT COUNT(*) FROM concurso_cargos WHERE concurso_id = ' . (int)$concursoId)->fetchColumn();
            $alt = 0; $rem = 0; $ren = 0;
            foreach ((array)($_POST['it'] ?? []) as $iid => $x) {
                $st = $pdo->prepare('SELECT e.*, t.titulo FROM edital_itens e JOIN admin_materia_topicos t ON t.id = e.topico_id WHERE e.id = :id AND e.concurso_id = :c');
                $st->execute([':id' => (int)$iid, ':c' => $concursoId]);
                if (!($it = $st->fetch())) { continue; }
                $cgs = array_values(array_unique(array_map('intval', (array)($x['cargos'] ?? []))));
                if (!empty($x['apagar']) || !$cgs) { // removido ou sem nenhum cargo: sai do edital deste concurso
                    $pdo->prepare('DELETE FROM edital_itens WHERE id = :id')->execute([':id' => (int)$iid]);
                    $rem++;
                    continue;
                }
                $novoCg = count($cgs) >= $nCargos ? '' : implode(',', $cgs);
                if ($novoCg !== (string)$it['cargos']) {
                    $pdo->prepare('UPDATE edital_itens SET cargos = :g WHERE id = :id')->execute([':g' => $novoCg, ':id' => (int)$iid]);
                    $alt++;
                }
                $tit = trim(mb_substr((string)($x['titulo'] ?? ''), 0, 180));
                if ($tit !== '' && $tit !== (string)$it['titulo']) {
                    $pdo->prepare('UPDATE admin_materia_topicos SET titulo = :t WHERE id = :id')->execute([':t' => $tit, ':id' => (int)$it['topico_id']]);
                    $ren++;
                }
            }
            [$saE] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, 'edital', "💾 Salvo: $alt assunto(s) com cargos ajustados, $ren renomeado(s), $rem removido(s)" . ($saE ? " · $saE aula(s) aproveitada(s) da plataforma" : '') . '. O passo Aulas já mostra assim.');
        }
        if ($acao === 'comparar_cargo') {
            $rc = job_comparar_cargo($pdo, $concursoId, (int)($_POST['cargo_id'] ?? 0), (string)($_POST['texto'] ?? ''));
            ed_ir($concursoId, 'edital', "Li {$rc['materias']} matéria(s) e {$rc['lidos']} assunto(s) do texto colado (sem IA). Faltam {$rc['novos']} assunto(s) novo(s) e {$rc['ligar']} assunto(s) que existem mas não estão ligados a este cargo. Confira abaixo.");
        }
        if ($acao === 'aceitar_faltando') {
            $falt = (array)hack_rascunho_get($pdo, $concursoId, 'faltando', []);
            $matT = (string)($_POST['materia_toda'] ?? '');
            $idx = ($_POST['um'] ?? '') !== '' ? [(int)$_POST['um']]
                : ($matT !== '' ? array_keys(array_filter($falt, static fn($x) => $x['materia'] === $matT))
                : (($_POST['tudo'] ?? '') === '1' ? array_keys($falt) : array_map('intval', (array)($_POST['f'] ?? []))));
            $porMat = [];
            $nLig = 0;
            foreach ($idx as $k) {
                if (isset($falt[$k]) && ($falt[$k]['tipo'] ?? '') === 'ligar') { // assunto já existe: só liga ao cargo
                    $stL = $pdo->prepare('SELECT cargos FROM edital_itens WHERE id = :id AND concurso_id = :c');
                    $stL->execute([':id' => (int)$falt[$k]['item_id'], ':c' => $concursoId]);
                    $ids = array_filter(array_map('intval', explode(',', (string)$stL->fetchColumn())));
                    if ($ids) {
                        $ids[] = (int)$falt[$k]['cargo_id'];
                        $nCargos = (int)$pdo->query('SELECT COUNT(*) FROM concurso_cargos WHERE concurso_id = ' . (int)$concursoId)->fetchColumn();
                        $ids = array_values(array_unique($ids));
                        $pdo->prepare('UPDATE edital_itens SET cargos = :g WHERE id = :id')->execute([':g' => count($ids) >= $nCargos ? '' : implode(',', $ids), ':id' => (int)$falt[$k]['item_id']]);
                    }
                    $nLig++;
                    unset($falt[$k]);
                    continue;
                }
                if (isset($falt[$k])) {
                    $chM = $falt[$k]['materia'] . '|' . implode(';', $falt[$k]['cargos']);
                    $porMat[$chM] = $porMat[$chM] ?? ['materia' => $falt[$k]['materia'], 'cargos' => $falt[$k]['cargos'], 'assuntos' => []];
                    $porMat[$chM]['assuntos'][] = $falt[$k]['assunto'];
                    unset($falt[$k]);
                }
            }
            $nF = edital_acrescentar($pdo, $concursoId, array_values($porMat));
            hack_rascunho_set($pdo, $concursoId, 'faltando', $falt ? $falt : null);
            [$saF] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, 'edital', "✅ $nF assunto(s) acrescentado(s) e $nLig ligado(s) ao cargo" . ($saF ? " · $saF aula(s) já aproveitada(s) da plataforma" : '') . '. Eles aparecem no passo Aulas.');
        }
        if ($acao === 'descartar_faltando') {
            $falt = (array)hack_rascunho_get($pdo, $concursoId, 'faltando', []);
            unset($falt[(int)($_POST['um'] ?? -1)]);
            hack_rascunho_set($pdo, $concursoId, 'faltando', $falt ? $falt : null);
            ed_ir($concursoId, 'edital', 'Sugestão descartada.');
        }
        if ($acao === 'parecidas_ia') {
            job_criar($pdo, $concursoId, 'parecidas_ia', 'A IA procura, pelo assunto, aulas prontas de outros cursos');
            ed_ir($concursoId, 'aulas', '🤖 A IA está procurando, pelo assunto, aulas prontas para os assuntos sem aula (1 pedido por cargo). O resultado aparece aqui para você confirmar.');
        }
        if ($acao === 'aceitar_parecidas') {
            $n = 0;
            $stA = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
            $escolhidas = ($_POST['par_um'] ?? '') !== '' ? [(string)$_POST['par_um']]
                : (($_POST['par_materia'] ?? '') !== '' ? (array)($_POST['par_mat'][(string)$_POST['par_materia']] ?? [])
                : (($_POST['tudo'] ?? '') === '1' ? (array)($_POST['par_todas'] ?? []) : (array)($_POST['par'] ?? [])));
            foreach ($escolhidas as $v) {
                [$aulaId, $itemId, $cursoDest] = array_map('intval', explode(':', (string)$v) + [0, 0, 0]);
                $stA->execute([':id' => $aulaId]);
                if (($a = $stA->fetch()) && $itemId && $cursoDest) {
                    $n += job_clonar_aula($pdo, $a, $itemId, $cursoDest, $concursoId) ? 1 : 0;
                }
            }
            ed_ir($concursoId, 'aulas', "✅ $n aula(s) aproveitada(s) de outros cursos, com vídeo, PDF, teoria e videoaula prontos (sem gastar IA).");
        }
        if ($acao === 'sincronizar') {
            [$sa, $sq] = job_sincronizar_concurso($pdo, $concursoId);
            ed_ir($concursoId, (string)($_POST['volta'] ?? 'aulas'), "🔄 Sincronizado com a plataforma: $sa aula(s) e $sq questão(ões) aproveitadas dos mesmos assuntos.");
        }
        if ($acao === 'refazer_formato') {
            $cg = $pdo->prepare('SELECT DISTINCT curso_id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
            $cg->execute([':c' => $concursoId]);
            $cIds = array_map('intval', $cg->fetchAll(PDO::FETCH_COLUMN)) ?: [0];
            $n = 0;
            foreach ($pdo->query('SELECT a.id, a.video_url, a.pdf_arquivo, a.descricao FROM admin_curso_aulas a JOIN aula_videoaulas v ON v.aula_id = a.id WHERE a.curso_id IN (' . implode(',', $cIds) . ')') as $a) {
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :id')->execute([':id' => (int)$a['id']]);
                $antigao = trim((string)$a['video_url']) === '' && trim((string)$a['pdf_arquivo']) === '' && str_contains((string)$a['descricao'], 'aula-ia-aviso');
                if ($antigao) {
                    try { $pdo->prepare('DELETE FROM aula_podcasts WHERE aula_id = :id')->execute([':id' => (int)$a['id']]); } catch (Throwable $e) {}
                }
                ia_fila_adicionar($pdo, $antigao ? 'aula_ia' : 'videoaula', (int)$a['id']);
                $n++;
            }
            ed_ir($concursoId, 'aulas', "🔁 $n aula(s) na fila para refazer no formato novo (videoaula de 10 a 15 min, ritmo calmo; assunto grande vira mais de uma aula).");
        }
        if ($acao === 'gerar_faltantes') {
            job_sincronizar_concurso($pdo, $concursoId); // aproveita o que já existe antes de gerar
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
            job_sincronizar_concurso($pdo, $concursoId); // libera as questões que já existem nos mesmos assuntos
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
        .ed-mini{display:block;height:5px;border-radius:99px;background:#eef0f3;overflow:hidden}.ed-mini i{display:block;height:100%;background:#16a34a}
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
                            <span class="ed-mini" role="progressbar" aria-valuenow="<?php echo (int)$e['pct']; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo admin_esc($v); ?>: <?php echo (int)$e['pct']; ?>%"><i style="width:<?php echo (int)$e['pct']; ?>%"></i></span><small style="font-weight:700"><?php echo (int)$e['pct']; ?>%</small>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <section class="admin-panel" id="centralIA" data-concurso="<?php echo $concursoId; ?>" aria-live="polite">
                <div class="admin-panel-title"><h2>🤖 Central da IA</h2><small id="ciaStatus">verificando…</small></div>
                <?php if (!ia_controle_ligado('edital')): ?>
                <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px;padding:10px 12px;background:#fef2f2;border:1px solid #fca5a5;border-radius:10px">
                    <input type="hidden" name="acao" value="ligar_cadastro_ia"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                    <span>⛔ <strong>As tarefas da IA do Cadastro estão desligadas</strong> (em Consumo e controle da IA). Tudo com ⏳ deste concurso fica parado até ligar. Cada tarefa gasta só 1 ou 2 pedidos da cota grátis.</span>
                    <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Ligar agora</button>
                </form>
                <?php endif; ?>
                <p id="ciaAtualizar" hidden class="admin-alert admin-alert-success" style="margin:0 0 8px">✅ A IA terminou uma tarefa. <button type="button" class="admin-btn admin-btn-light admin-btn-small" onclick="location.reload()">Atualizar a página</button> (salve o que está preenchendo antes)</p>
                <p id="ciaAgora" style="margin:4px 0 8px;font-size:15px"></p>
                <details style="margin-bottom:8px"><summary class="admin-form-hint" style="cursor:pointer">O que são essas tarefas?</summary>
                    <ul class="admin-form-hint" style="margin:6px 0 0 18px;display:grid;gap:3px">
                        <li><strong>🤖 Aulas do Antigão (teoria):</strong> o assunto não tinha aula; a IA escreve a teoria mínima (texto da aula).</li>
                        <li><strong>📄 Apostilas:</strong> a aula tem vídeo ou PDF do professor; a IA transforma em apostila resumida.</li>
                        <li><strong>🎬 Videoaulas do Antigão:</strong> slides narrados com perguntas, para aulas que não têm vídeo.</li>
                        <li><strong>🎧 Podcasts:</strong> áudio extra de cada aula (Antigão e Recruta 01).</li>
                        <li><strong>📚 Questões:</strong> para cada assunto, a IA busca questões reais e, se faltar, cria no nível da prova.</li>
                        <li><strong>Lista abaixo:</strong> tarefas do cadastro (ler edital, conferir prova, verticalizado, textos do Antigão).</li>
                    </ul>
                    <p class="admin-form-hint" style="margin-top:6px">A IA faz uma tarefa por vez. Com a página aberta ela trabalha sem parar; fechada, o robô continua a cada 5 minutos.</p>
                </details>
                <div style="border:1px solid #eef0f3;border-radius:12px;padding:10px 12px;margin-bottom:10px">
                    <div id="ciaUso"></div>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:8px;font-size:14px">
                        <label><input type="checkbox" id="ciaPodPausa"> ⏸ Pausar podcasts (extra) para sobrar voz às videoaulas</label>
                        <label>Gastar a cota primeiro com: <select id="ciaPrimeiro"><option value="videoaula">🎬 videoaulas</option><option value="questoes">📚 questões</option></select></label>
                        <span id="ciaPriOk" class="admin-form-hint" aria-live="polite"></span>
                    </div>
                </div>
                <div id="ciaAulas" class="admin-form-hint" style="display:grid;gap:2px;margin-bottom:6px"></div>
                <div id="ciaQuestoes" class="admin-form-hint"></div>
                <div class="ed-barra" style="height:8px;margin:6px 0" aria-hidden="true"><i id="ciaQBarra" style="width:0"></i></div>
                <div id="ciaConsumo" style="margin:6px 0;font-size:13px"></div>
                <div id="ciaRecentes" style="margin:8px 0"></div>
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
                                <select name="cargo_curso[<?php echo (int)$cg['id']; ?>]" aria-label="Curso do cargo <?php echo admin_esc((string)$cg['nome']); ?>"><option value="0">— escolha —</option><option value="novo" <?php echo empty($cg['curso_id']) ? 'selected' : ''; ?>>+ Curso novo (o que já existe na plataforma é aproveitado sozinho)</option><optgroup label="Usar um curso que já existe"><?php foreach ($cursos as $cu): ?><option value="<?php echo (int)$cu['id']; ?>" <?php echo (int)$cu['id'] === (int)$cg['curso_id'] ? 'selected' : ''; ?>><?php echo admin_esc((string)$cu['nome']); ?></option><?php endforeach; ?></optgroup></select></div>
                            <?php endforeach; ?>
                            <p class="admin-form-hint">Se já existe um curso pronto para o cargo, escolha ele. Se não existe, escolha "Curso novo": logo abaixo você copia as matérias que já estão prontas em outros cursos, e o resto a IA completa (ou você, manualmente).</p>
                            <input name="novo_cargo" placeholder="Adicionar cargo (ex.: Soldado Bombeiro)" style="margin-top:6px" aria-label="Novo cargo">
                        </div>
                        <div class="admin-form-actions" style="position:static"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="save"></i>Salvar e ir para o edital</button></div>
                    </form>
                </section>
                <?php $prontos = job_cursos_prontos($pdo); if ($prontos): foreach ($cargos as $cg): if (empty($cg['curso_id'])) { continue; }
                    $stQ = $pdo->prepare('SELECT COUNT(*) FROM admin_curso_aulas WHERE curso_id = :c'); $stQ->execute([':c' => (int)$cg['curso_id']]); $qtdAulas = (int)$stQ->fetchColumn(); ?>
                <details class="admin-panel"<?php echo $qtdAulas === 0 ? ' open' : ''; ?>>
                    <summary style="font-weight:700;cursor:pointer">📚 <?php echo admin_esc((string)$cg['nome']); ?>: copiar matérias de cursos prontos <?php echo $qtdAulas === 0 ? '(curso ainda vazio)' : '(' . $qtdAulas . ' aulas no curso)'; ?></summary>
                    <p class="admin-form-hint" style="margin:8px 0">Marque as matérias que já servem para este cargo. Elas são copiadas com aulas, apostilas, vídeos e PDFs. O que faltar, a IA completa no passo 4.</p>
                    <form method="post"><input type="hidden" name="acao" value="copiar_modulos"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$cg['id']; ?>">
                        <?php foreach ($prontos as $nomeCurso => $mods): if ((int)$mods[0]['curso_id'] === (int)$cg['curso_id']) { continue; } ?>
                            <fieldset style="border:1px solid #eef0f3;border-radius:10px;padding:8px 12px;margin:6px 0"><legend style="font-weight:700;padding:0 6px"><?php echo admin_esc((string)$nomeCurso); ?></legend>
                                <?php foreach ($mods as $m): ?><label style="display:inline-flex;gap:6px;align-items:center;margin:4px 14px 4px 0;font-size:14px"><input type="checkbox" name="modulos[]" value="<?php echo (int)$m['id']; ?>"> <?php echo admin_esc((string)$m['titulo']); ?> <small class="admin-form-hint">(<?php echo (int)$m['aulas']; ?> aulas)</small></label><?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">Copiar as matérias marcadas</button>
                    </form>
                </details>
                <?php endforeach; endif; ?>

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
                <?php $faltando = (array)hack_rascunho_get($pdo, $concursoId, 'faltando', []); ?>
                <section class="admin-panel" id="faltou">
                    <div class="admin-panel-title"><h2>🔍 Faltou algum assunto?</h2><small><?php echo count($itens); ?> assuntos cadastrados</small></div>
                    <p class="admin-form-hint">A IA relê o edital inteiro (com os anexos) e compara item por item com o que está cadastrado. Ela só sugere: nada entra sem você concordar. 1 pedido de texto.</p>
                    <form method="post" style="margin:6px 0 10px"><input type="hidden" name="acao" value="conferir_conteudo"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <?php if (empty($concurso['edital_pdf'])): ?><textarea name="texto" rows="3" style="width:100%" placeholder="Sem PDF guardado: cole aqui o conteúdo programático do edital"></textarea><?php endif; ?>
                        <button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>A IA confere se faltou assunto</button></form>
                    <details style="margin:4px 0 12px" <?php echo $faltando ? '' : 'open'; ?>><summary style="cursor:pointer;font-weight:700">📋 Colar o conteúdo de um cargo e comparar (sem IA, exato)</summary>
                        <form method="post" style="margin-top:8px;display:grid;gap:8px"><input type="hidden" name="acao" value="comparar_cargo"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                            <label>Cargo: <select name="cargo_id"><?php foreach ($cargos as $cgx): ?><option value="<?php echo (int)$cgx['id']; ?>"><?php echo admin_esc((string)$cgx['nome']); ?></option><?php endforeach; ?></select></label>
                            <textarea name="texto" rows="6" style="width:100%" placeholder="Cole aqui o anexo do conteúdo programático deste cargo, do jeito que está no edital (LÍNGUA PORTUGUESA: 1. … 2. …)" required></textarea>
                            <small class="admin-form-hint">A plataforma lê os nomes das matérias em maiúsculas e a numeração. "1.1." vira subtópico do item de cima. Mostra o que falta para este cargo; nada muda sem você aprovar.</small>
                            <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" style="justify-self:start">Comparar com o cadastro</button>
                        </form>
                    </details>
                    <?php if ($faltando): ?>
                    <form method="post"><input type="hidden" name="acao" value="aceitar_faltando"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <p style="margin:0 0 6px"><strong><?php echo count($faltando); ?> assunto(s) do edital que faltam no cadastro:</strong></p>
                        <div style="max-height:420px;overflow:auto;font-size:13px">
                        <?php $ultM = ''; foreach ($faltando as $k => $fx): ?>
                            <?php if ($ultM !== $fx['materia']): $ultM = $fx['materia']; ?><div style="margin-top:10px;font-weight:700;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?php echo admin_esc($fx['materia']); ?><?php $cgF = array_filter($fx['cargos'], static fn($c) => edital_normalizar((string)$c) !== 'todos'); echo $cgF ? ' <small style="color:#6b7280">(' . admin_esc(implode(', ', $cgF)) . ')</small>' : ''; ?>
                                <button type="submit" name="materia_toda" value="<?php echo admin_esc($fx['materia']); ?>" class="admin-btn admin-btn-light admin-btn-small" style="padding:2px 10px;min-height:0">✅ Aprovar matéria inteira (<?php echo count(array_filter($faltando, static fn($x) => $x['materia'] === $fx['materia'])); ?>)</button></div><?php endif; ?>
                            <div style="display:flex;gap:8px;align-items:center;padding:2px 0 2px 12px">
                                <input type="checkbox" class="f-ck" name="f[]" value="<?php echo (int)$k; ?>" checked>
                                <button type="submit" name="um" value="<?php echo (int)$k; ?>" class="admin-btn admin-btn-light admin-btn-small" style="padding:2px 8px;min-height:0">✓ Concordo</button>
                                <button type="submit" name="acao" value="descartar_faltando" formnovalidate onclick="this.form.querySelector('[name=um_d]')" class="admin-btn admin-btn-light admin-btn-small" style="padding:2px 8px;min-height:0" data-um="<?php echo (int)$k; ?>">✕</button>
                                <span><?php echo ($fx['tipo'] ?? '') === 'ligar' ? '<small style="background:#e0f2fe;color:#075985;border-radius:6px;padding:1px 6px">ligar ao ' . admin_esc(implode(', ', (array)$fx['cargos'])) . '</small> ' : (!empty($fx['cargos']) && edital_normalizar((string)$fx['cargos'][0]) !== 'todos' ? '<small style="background:#fef3c7;border-radius:6px;padding:1px 6px">novo · ' . admin_esc(implode(', ', (array)$fx['cargos'])) . '</small> ' : ''); ?><?php echo admin_esc($fx['assunto']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                            <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="tudo" value="1" onclick="return confirm('Aprovar TODOS os assuntos sugeridos, de todas as matérias?');">✅ Aprovar tudo (curso inteiro)</button>
                            <button class="admin-btn admin-btn-light admin-btn-small" type="submit">📋 Só os marcados</button>
                            <label style="font-size:13px;display:flex;gap:6px;align-items:center"><input type="checkbox" checked onclick="this.closest('form').querySelectorAll('.f-ck').forEach(c => c.checked = this.checked)"> marcar/desmarcar todos</label>
                        </div>
                    </form>
                    <script>document.querySelectorAll('#faltou [data-um]').forEach(function(b){b.addEventListener('click',function(){var h=document.createElement('input');h.type='hidden';h.name='um';h.value=b.getAttribute('data-um');b.form.appendChild(h);});});</script>
                    <?php endif; ?>
                </section>
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
                <section class="admin-panel" id="conteudo"><div class="admin-panel-title"><h2>Conteúdo programático por cargo</h2><small><?php echo count($itens); ?> assuntos no concurso</small></div>
                    <?php
                    $grupo = []; foreach ($itens as $it) { $grupo[edital_normalizar((string)$it['materia'])][] = $it; } // mesma matéria cadastrada 2x aparece junta
                    $doCargo = static function (array $it, int $cid): bool { $cs = array_filter(array_map('intval', explode(',', (string)$it['cargos']))); return !$cs || in_array($cid, $cs, true); };
                    ?>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:4px 0 12px">
                        <?php foreach ($cargos as $cgx): $n = count(array_filter($itens, static fn($it) => $doCargo($it, (int)$cgx['id']))); $nm = count(array_filter($grupo, static fn($l) => (bool)array_filter($l, static fn($it) => $doCargo($it, (int)$cgx['id'])))); ?>
                            <span style="background:#f3f4f6;border-radius:10px;padding:8px 12px;font-size:14px"><strong><?php echo admin_esc((string)$cgx['nome']); ?></strong>: <?php echo $nm; ?> matérias · <?php echo $n; ?> assuntos</span>
                        <?php endforeach; ?>
                    </div>
                    <p class="admin-form-hint">Abra a matéria para corrigir: marque em quais cargos cada assunto cai, renomeie ou remova. Salva uma matéria por vez. Remover tira o assunto do edital deste concurso (as aulas continuam no curso).</p>
                    <?php foreach ($grupo as $lista): $mat = (string)$lista[0]['materia']; ?>
                        <details class="ed-materia"><summary><span><?php echo admin_esc($mat); ?></span><small><?php foreach ($cargos as $cgx): ?><?php echo admin_esc((string)$cgx['nome']); ?>: <?php echo count(array_filter($lista, static fn($it) => $doCargo($it, (int)$cgx['id']))); ?> · <?php endforeach; ?><?php echo count($lista); ?> no total</small></summary>
                        <form method="post" style="margin:8px 0"><input type="hidden" name="acao" value="editar_conteudo"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                            <div style="overflow:auto"><table style="width:100%;font-size:13px;border-collapse:collapse">
                                <tr><th style="text-align:left">Assunto</th><?php foreach ($cargos as $cgx): ?><th style="white-space:nowrap"><?php echo admin_esc((string)$cgx['nome']); ?><br><label style="font-weight:400"><input type="checkbox" onclick="this.closest('table').querySelectorAll('.cg-<?php echo (int)$cgx['id']; ?>').forEach(c => c.checked = this.checked)"> todos</label></th><?php endforeach; ?><th>Remover</th></tr>
                                <?php foreach ($lista as $it): $iid = (int)$it['id']; ?>
                                <tr style="border-top:1px solid #f0f1f3">
                                    <td><input name="it[<?php echo $iid; ?>][titulo]" value="<?php echo admin_esc((string)$it['assunto']); ?>" style="width:100%;min-width:260px;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px"></td>
                                    <?php foreach ($cargos as $cgx): ?><td style="text-align:center"><input type="checkbox" class="cg-<?php echo (int)$cgx['id']; ?>" name="it[<?php echo $iid; ?>][cargos][]" value="<?php echo (int)$cgx['id']; ?>" <?php echo $doCargo($it, (int)$cgx['id']) ? 'checked' : ''; ?>></td><?php endforeach; ?>
                                    <td style="text-align:center"><input type="checkbox" name="it[<?php echo $iid; ?>][apagar]" value="1"></td>
                                </tr>
                                <?php endforeach; ?>
                            </table></div>
                            <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" style="margin-top:8px">💾 Salvar <?php echo admin_esc($mat); ?></button>
                        </form>
                        </details>
                    <?php endforeach; ?>
                    <a class="admin-btn admin-btn-primary" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=escritas">Próximo: provas escritas ▶</a>
                </section>
                <?php endif; ?>

            <?php elseif ($aba === 'escritas'): $infoE = json_decode((string)($concurso['edital_info'] ?? ''), true) ?: []; $confE = !empty($infoE['provas_escritas_conferido']); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>3. Provas escritas: redação e discursiva</h2><?php echo $confE ? '<span class="ed-salvo">✓ conferido por você</span>' : (!empty($infoE['provas_escritas']) ? '<span class="ed-salvo" style="color:#b54708">lido pela IA · falta conferir</span>' : ''); ?></div>
                    <p class="admin-form-hint">A plataforma se adapta a isto: sem redação, o menu Redação some para os alunos do cargo e o cronograma não marca redação; com redação, a correção usa a grade do edital; com discursiva, o aluno ganha o treino de discursiva com o Antigão.</p>
                    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin:8px 0 14px">
                        <input type="hidden" name="acao" value="ler_escritas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                        <?php if (empty($concurso['edital_pdf'])): ?><div class="admin-form-field" style="flex:1;min-width:260px"><label for="peTexto">Cole o trecho do edital que fala das provas (não há PDF guardado)</label><textarea id="peTexto" name="texto" rows="3"></textarea></div><?php endif; ?>
                        <button class="admin-btn admin-btn-light" type="submit"><i data-lucide="sparkles"></i>A IA identifica redação e discursiva (1 pedido)</button>
                    </form>
                    <form method="post"><input type="hidden" name="acao" value="salvar_escritas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                    <?php foreach ($cargos as $k => $cg): $pe = pe_do_cargo($infoE, (string)$cg['nome']); $rd = $pe['redacao']; $ds = $pe['discursiva'];
                        $sel = static fn(?bool $v, string $nome) => '<select name="' . $nome . '"><option value=""' . ($v === null ? ' selected' : '') . '>não sei</option><option value="1"' . ($v === true ? ' selected' : '') . '>sim</option><option value="0"' . ($v === false ? ' selected' : '') . '>não</option></select>';
                        $nm = static fn($v) => $v === null ? '' : rtrim(rtrim((string)$v, '0'), '.'); $b = 'pe[' . $k . ']'; ?>
                        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin-bottom:12px">
                            <input type="hidden" name="<?php echo $b; ?>[cargo]" value="<?php echo admin_esc((string)$cg['nome']); ?>">
                            <h3 style="margin:0 0 8px"><?php echo admin_esc((string)$cg['nome']); ?></h3>
                            <p style="margin:0 0 10px;font-size:13px">✍️ Redação: <strong><?php echo admin_esc(pe_resumo($pe, 'redacao')); ?></strong><br>📝 Discursiva: <strong><?php echo admin_esc(pe_resumo($pe, 'discursiva')); ?></strong></p>
                            <details><summary style="cursor:pointer">Conferir e corrigir</summary>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px;font-size:13px">
                                <label>Tem redação? <?php echo $sel($rd['tem'], $b . '[redacao][tem]'); ?></label>
                                <label>Tipo/gênero <input name="<?php echo $b; ?>[redacao][tipo]" value="<?php echo admin_esc($rd['tipo']); ?>" placeholder="dissertativo-argumentativo"></label>
                                <label>Linhas mín. <input name="<?php echo $b; ?>[redacao][linhas_min]" value="<?php echo $nm($rd['linhas_min']); ?>" size="4"></label>
                                <label>Linhas máx. <input name="<?php echo $b; ?>[redacao][linhas_max]" value="<?php echo $nm($rd['linhas_max']); ?>" size="4"></label>
                                <label>Pontos <input name="<?php echo $b; ?>[redacao][pontos]" value="<?php echo $nm($rd['pontos']); ?>" size="4"></label>
                                <label>Nota mínima <input name="<?php echo $b; ?>[redacao][nota_minima]" value="<?php echo $nm($rd['nota_minima']); ?>" size="4"></label>
                                <label>Eliminatória? <?php echo $sel($rd['eliminatoria'], $b . '[redacao][eliminatoria]'); ?></label>
                            </div>
                            <label style="display:block;font-size:13px;margin-top:8px">Critérios de correção (um por linha: nome | pontos | o que avalia)
                                <textarea name="<?php echo $b; ?>[redacao][criterios_txt]" rows="3" style="width:100%"><?php echo admin_esc(implode("\n", array_map(static fn($c) => $c['nome'] . ' | ' . $nm($c['max']) . ' | ' . $c['descricao'], $rd['criterios']))); ?></textarea></label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px;font-size:13px">
                                <label>Tem discursiva? <?php echo $sel($ds['tem'], $b . '[discursiva][tem]'); ?></label>
                                <label>Nº de questões <input name="<?php echo $b; ?>[discursiva][questoes]" value="<?php echo $nm($ds['questoes']); ?>" size="4"></label>
                                <label>Formato <input name="<?php echo $b; ?>[discursiva][formato]" value="<?php echo admin_esc($ds['formato']); ?>" placeholder="questões abertas"></label>
                                <label>Linhas por resposta <input name="<?php echo $b; ?>[discursiva][linhas]" value="<?php echo $nm($ds['linhas']); ?>" size="4"></label>
                                <label>Pontos <input name="<?php echo $b; ?>[discursiva][pontos]" value="<?php echo $nm($ds['pontos']); ?>" size="4"></label>
                                <label>Nota mínima <input name="<?php echo $b; ?>[discursiva][nota_minima]" value="<?php echo $nm($ds['nota_minima']); ?>" size="4"></label>
                                <label>Eliminatória? <?php echo $sel($ds['eliminatoria'], $b . '[discursiva][eliminatoria]'); ?></label>
                            </div>
                            <label style="display:block;font-size:13px;margin-top:8px">Matérias da discursiva (separe por ;)
                                <input name="<?php echo $b; ?>[discursiva][materias]" value="<?php echo admin_esc(implode('; ', $ds['materias'])); ?>" style="width:100%"></label>
                            </details>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($cargos): ?><button class="admin-btn admin-btn-primary" type="submit">✓ Está certo — confirmar provas escritas</button><?php else: ?><p>Cadastre os cargos no passo 1.</p><?php endif; ?>
                    </form>
                    <p class="admin-form-hint" style="margin-top:10px">Ao confirmar, as matérias <strong>Redação</strong> e <strong>Prova discursiva</strong> entram no conteúdo programático (só dos cargos que têm), com os assuntos do treino. Elas aparecem no passo Aulas, no Plano-Mestre e no cronograma do aluno.</p>
                    <p style="margin-top:12px"><a class="admin-btn admin-btn-light" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=prova">Próximo: última prova ▶</a></p>
                </section>

            <?php elseif ($aba === 'prova'): $prova = json_decode((string)($concurso['ultima_prova'] ?? ''), true); $provas = job_provas($concurso); ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>4. Prova anterior de cada cargo</h2></div>
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
                                    <?php if (!empty($pv['itens'])): $porMat = []; $nomeIt = []; foreach ($itens as $it) { $nomeIt[(int)$it['id']] = $it['materia']; } foreach ((array)$pv['itens'] as $iid => $qn) { if ($qn) { $porMat[$nomeIt[(int)$iid] ?? '?'] = ($porMat[$nomeIt[(int)$iid] ?? '?'] ?? 0) + (int)$qn; } } arsort($porMat); ?>
                                        <details><summary class="admin-form-hint" style="cursor:pointer">👁 Pré-visualizar: questões por matéria que a IA contou</summary><p class="admin-form-hint"><?php echo admin_esc(implode(' · ', array_map(static fn($m, $q) => "$m: $q", array_keys($porMat), $porMat))); ?></p></details>
                                    <?php endif; ?>
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
                    <div class="admin-panel-title"><h2>5. Aulas de cada assunto</h2></div>
                    <p style="margin:0 0 10px;padding:10px 12px;background:#fff8e6;border:1px solid #fbb928;border-radius:10px;font-size:14px">✏️ Falta matéria ou assunto em algum cargo? <a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=edital#faltou" style="font-weight:700">Corrigir o conteúdo do edital (colar o anexo do cargo)</a></p>
                    <p class="admin-form-hint" style="margin-bottom:10px">Três caminhos, nesta ordem: <strong>1)</strong> usar vídeos que já existem na plataforma (abaixo), <strong>2)</strong> cadastrar vídeo/PDF do professor no assunto, <strong>3)</strong> o que faltar, a IA gera (teoria mínima + videoaula do Antigão). Legenda: ✅ aula do professor · 🤖 aula do Antigão · ⬜ falta.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px">
                        <form method="post" onsubmit="return confirm('Refazer as videoaulas já prontas no formato novo (10 a 15 min, ritmo calmo)? As aulas do Antigão também têm a teoria refeita e podem virar mais de uma aula. Gasta cota de voz de novo.');"><input type="hidden" name="acao" value="refazer_formato"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light" type="submit">🔁 Refazer videoaulas no formato novo</button></form>
                        <form method="post"><input type="hidden" name="acao" value="sincronizar"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light" type="submit" title="Copia aulas e libera questões dos mesmos assuntos que já existem em qualquer curso">🔄 Sincronizar com a plataforma</button></form>
                        <form method="post"><input type="hidden" name="acao" value="parecidas_ia"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit" title="A IA lê os assuntos sem aula e procura, pelo conteúdo, aulas prontas em todos os cursos (1 pedido de texto por cargo)">🤖 A IA procura aulas pelo assunto</button></form>
                        <form method="post"><input type="hidden" name="acao" value="sugerir_existentes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-light" type="submit"><i data-lucide="search"></i>1. Procurar vídeos já gravados</button></form>
                        <form method="post" onsubmit="return confirm('A IA vai preparar a aula (teoria mínima + videoaula do Antigão) de todos os assuntos sem aula. Continuar?');"><input type="hidden" name="acao" value="gerar_faltantes"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><button class="admin-btn admin-btn-primary" type="submit"><i data-lucide="sparkles"></i>3. A IA gera o que falta</button></form>
                        <a class="admin-btn admin-btn-light" style="margin-left:auto" href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=questoes">Próximo: questões ▶</a>
                    </div>
                    <?php foreach ($andamento as $an): $pct = $an['total'] ? (int)round($an['cobertos'] * 100 / $an['total']) : 0;
                        $nProf = 0; $nIa = 0; foreach ($an['itens'] as $x) { if (array_filter($x['aulas'], static fn($a) => trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '')) { $nProf++; } elseif ($x['aulas']) { $nIa++; } } ?>
                        <div style="border:1px solid #eef0f3;border-radius:12px;padding:12px 14px;margin-top:12px">
                            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center"><h3 style="margin:0"><?php echo admin_esc((string)$an['cargo']['nome']); ?></h3><span><strong><?php echo $an['cobertos']; ?>/<?php echo $an['total']; ?></strong> com aula · ✅ <?php echo $nProf; ?> do professor · 🤖 <?php echo $nIa; ?> do Antigão · ⬜ <?php echo $an['total'] - $an['cobertos']; ?> faltam</span></div>
                            <div class="ed-barra" style="height:8px;margin:8px 0"><i style="width:<?php echo $pct; ?>%"></i></div>
                            <?php $aprov = !empty($an['cargo']['curso_id']) ? job_aproveitar_entre_cargos($pdo, $concursoId, (int)$an['cargo']['id'], true, $andamento) : 0; if ($aprov > 0): ?>
                                <form method="post" style="margin:4px 0 10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff8e6;border:1px solid #fbb928;border-radius:10px;padding:10px 12px"><input type="hidden" name="acao" value="aproveitar_cargos"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="cargo_id" value="<?php echo (int)$an['cargo']['id']; ?>">
                                    <span>💡 <strong><?php echo $aprov; ?> assunto(s)</strong> deste cargo já têm aula em outro curso da plataforma (mesmo assunto, de qualquer cargo ou concurso).</span>
                                    <button class="admin-btn admin-btn-primary admin-btn-small" type="submit">📋 Usar essas aulas aqui também</button></form>
                            <?php endif; ?>
                            <?php if (!isset($parecidas)) { $parecidas = []; try { foreach (job_aulas_parecidas($pdo, $concursoId) as $px) { $parecidas[$px['cargo_id']][] = $px; } } catch (Throwable $e) {} }
                            $parC = $parecidas[(int)$an['cargo']['id']] ?? []; if ($parC): ?>
                                <form method="post" style="margin:4px 0 10px;background:#f0f7ff;border:1px solid #93c5fd;border-radius:10px;padding:10px 12px">
                                    <input type="hidden" name="acao" value="aceitar_parecidas"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>">
                                    <p style="margin:0 0 6px">🤖 <strong>A IA achou aulas prontas pelo assunto</strong> para <?php echo count(array_unique(array_column($parC, 'item_id'))); ?> assunto(s) sem aula. Confira e desmarque o que não servir. Copiar não gasta IA: vídeo, PDF, teoria e videoaula vão prontos.</p>
                                    <div style="max-height:320px;overflow:auto;font-size:13px">
                                    <?php $ultimo = 0; $ultMat = ''; foreach ($parC as $px): ?>
                                        <?php if ($ultMat !== $px['materia']): $ultMat = $px['materia']; ?><div style="margin-top:10px;display:flex;gap:8px;align-items:center"><strong style="font-size:14px"><?php echo admin_esc($px['materia']); ?></strong><button type="submit" name="par_materia" value="<?php echo admin_esc($px['materia']); ?>" class="admin-btn admin-btn-light admin-btn-small" style="padding:2px 10px;min-height:0">✅ Aprovar matéria inteira</button></div><?php endif; ?>
                                        <?php if ($ultimo !== $px['item_id']): $ultimo = $px['item_id']; ?><div style="margin-top:8px;font-weight:700"><?php echo admin_esc($px['materia'] . ' › ' . $px['assunto']); ?></div><?php endif; ?>
                                        <?php $vPar = (int)$px['aula_id'] . ':' . (int)$px['item_id'] . ':' . (int)$px['curso_destino']; ?>
                                        <input type="hidden" name="par_todas[]" value="<?php echo $vPar; ?>"><input type="hidden" name="par_mat[<?php echo admin_esc($px['materia']); ?>][]" value="<?php echo $vPar; ?>">
                                        <label style="display:flex;gap:8px;align-items:flex-start;padding:2px 0 2px 12px"><input type="checkbox" class="par-ck" name="par[]" value="<?php echo $vPar; ?>" <?php echo $px['nota'] >= 80 ? 'checked' : ''; ?>>
                                            <button type="submit" name="par_um" value="<?php echo $vPar; ?>" class="admin-btn admin-btn-light admin-btn-small" style="padding:2px 8px;min-height:0" title="Concordar só com esta">✓ Usar esta</button>
                                            <span><?php echo admin_esc($px['aula']); ?> <small style="color:#6b7280">· <?php echo admin_esc($px['curso']); ?> · confiança <?php echo (int)$px['nota']; ?>%<?php echo !empty($px['motivo']) ? ' · ' . admin_esc($px['motivo']) : ''; ?></small></span></label>
                                    <?php endforeach; ?>
                                    </div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                                        <button class="admin-btn admin-btn-primary admin-btn-small" type="submit" name="tudo" value="1" onclick="return confirm('Copiar TODAS as aulas sugeridas pela IA para este cargo?');">✅ Aprovar tudo (curso inteiro)</button>
                                        <button class="admin-btn admin-btn-light admin-btn-small" type="submit">📋 Usar só as marcadas</button>
                                        <label style="font-size:13px;display:flex;gap:6px;align-items:center"><input type="checkbox" onclick="this.closest('form').querySelectorAll('.par-ck').forEach(c => c.checked = this.checked)"> marcar/desmarcar todas</label>
                                    </div>
                                </form>
                            <?php endif; ?>
                            <?php if (empty($an['cargo']['curso_id'])): ?>
                                <p class="ed-falta">Este cargo ainda não tem curso, então não dá para cadastrar aulas. <a href="admin-editais.php?concurso_id=<?php echo $concursoId; ?>&aba=concurso">Vá ao passo 1 e escolha "+ Criar curso novo para este cargo"</a>.</p>
                            <?php else: ?>
                            <?php $grupo = []; foreach ($an['itens'] as $it) { $grupo[$it['materia']][] = $it; } foreach ($grupo as $mat => $lista): $ok = count(array_filter($lista, static fn($x) => $x['aulas'])); ?>
                                <details class="ed-materia"><summary><span><?php echo $ok === count($lista) ? '✅' : '⬜'; ?> <?php echo admin_esc($mat); ?></span><small><?php echo $ok; ?>/<?php echo count($lista); ?> com aula</small></summary>
                                <?php foreach ($lista as $it): $prof = array_values(array_filter($it['aulas'], static fn($a) => trim((string)$a['video_url']) !== '' || trim((string)$a['pdf_arquivo']) !== '')); $a0 = $prof[0] ?? ($it['aulas'][0] ?? null); ?>
                                    <div class="ed-assunto"><span><?php echo $prof ? '✅' : ($it['aulas'] ? '🤖' : '⬜'); ?></span>
                                        <div><?php echo admin_esc((string)$it['assunto']); ?>
                                            <small><?php if ($a0): ?><?php echo admin_esc((string)$a0['titulo']); ?><?php if (!$prof): $vs = $vaStatus[(int)$a0['id']] ?? ''; ?> · 🎬 videoaula do Antigão: <?php echo $vs === 'pronto' ? 'pronta' : 'em preparo (Central da IA)'; ?><?php endif; ?> · <button type="button" class="admin-link-inline" style="background:none;border:0;padding:0;cursor:pointer;font:inherit" data-pv="aula" data-id="<?php echo (int)$a0['id']; ?>">👁 pré-visualizar</button> · <a class="admin-link-inline" target="_blank" href="aulas.php?curso_id=<?php echo (int)$a0['curso_id']; ?>&modulo_id=<?php echo (int)$a0['modulo_id']; ?>&aula_id=<?php echo (int)$a0['id']; ?>">▶ abrir como aluno</a><?php else: ?>sem aula<?php endif; ?></small></div>
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
                            <label class="ed-assunto" style="cursor:pointer"><input type="checkbox" name="e[]" value="<?php echo $i; ?>" checked><div><?php echo admin_esc((string)$e['aula']); ?><small>→ <?php echo admin_esc($nomeItem[(int)$e['item_id']] ?? ''); ?> · <?php echo admin_esc((string)$e['motivo']); ?> · <button type="button" class="admin-link-inline" style="background:none;border:0;padding:0;cursor:pointer;font:inherit" data-pv="aula" data-id="<?php echo (int)$e['aula_id']; ?>">👁 ver a aula</button></small></div><span class="ed-tag"><?php echo (int)$e['confianca']; ?>%</span></label>
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
                    <div class="admin-panel-title"><h2>6. Questões de cada assunto</h2><strong><?php echo $qs['ok']; ?>/<?php echo $qs['total']; ?> assuntos prontos (10+ questões) · <?php echo $totQ; ?> questões</strong></div>
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
                            <div class="ed-assunto"><span><?php echo $it['questoes'] >= 10 ? '✅' : '⬜'; ?></span><div><?php echo admin_esc((string)$it['assunto']); ?><small><?php echo (int)$it['questoes']; ?> questões<?php if ($it['questoes']): ?> · <button type="button" class="admin-link-inline" style="background:none;border:0;padding:0;cursor:pointer;font:inherit" data-pv="questoes" data-id="<?php echo (int)$it['topico_id']; ?>">👁 pré-visualizar questões</button><?php endif; ?></small></div>
                            <?php if ($it['questoes'] < 10): ?><form method="post"><input type="hidden" name="acao" value="questoes_item"><input type="hidden" name="concurso_id" value="<?php echo $concursoId; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>"><button class="admin-btn admin-btn-light admin-btn-small" type="submit">Fazer só este</button></form><?php endif; ?></div>
                        <?php endforeach; ?></details>
                    <?php endforeach; ?>
                </section>

            <?php elseif ($aba === 'plano'): ?>
                <section class="admin-panel">
                    <div class="admin-panel-title"><h2>7. Plano-Mestre por cargo</h2></div>
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
                    <div class="admin-panel-title"><h2>8. Hackeando o Edital e a Prova (o que o aluno vê)</h2>
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
    document.addEventListener('input', function (ev) { if (ev.target.closest && ev.target.closest('form')) { window.ciaSujo = true; } });
    function atualizar() {
        fetch('api/edital-job.php?concurso_id=' + c, {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (d) {
            var q = d.questoes || {};
            document.getElementById('ciaQuestoes').innerHTML = '📚 Questões: <strong>' + (q.assuntos_ok || 0) + '/' + (q.assuntos_total || 0) + '</strong> assuntos com 10+ · ' + (q.total_questoes || 0) + ' questões no banco · ' + (q.na_fila || 0) + ' assunto(s) na fila' + (q.erros ? ' · ' + q.erros + ' com erro' : '');
            document.getElementById('ciaQBarra').style.width = (q.assuntos_total ? Math.round(q.assuntos_ok * 100 / q.assuntos_total) : 0) + '%';
            document.getElementById('ciaJobs').innerHTML = (d.jobs || []).map(function (j) { return '<li>' + ico[j.status] + ' <strong>' + esc(j.rotulo) + '</strong>' + (j.mensagem ? ' · ' + esc(j.mensagem) : (j.status === 'rodando' ? ' · trabalhando…' : '')) + '</li>'; }).join('') || '<li class="admin-form-hint">Nenhuma tarefa agora.</li>';
            var nomes = {aula_ia: '🤖 Aulas do Antigão (teoria)', apostila: '📄 Apostilas', videoaula: '🎬 Videoaulas do Antigão', podcast: '🎧 Podcasts'}, filaPend = 0, linhas = '';
            Object.keys(nomes).forEach(function (t) { var f = (d.fila || {})[t]; if (!f) { return; } var feito = f.feito || 0, pend = f.pendente || 0, err = f.erro || 0, rev = f.revisao || 0, tot = feito + pend + err + rev; filaPend += pend;
                linhas += '<div>' + nomes[t] + ': <strong>' + feito + '/' + tot + '</strong> prontas' + (pend ? ' · ' + pend + ' na fila' : '') + (rev ? ' · ' + rev + ' esperando a conferência do PDF (<a href="admin-revisao-aulas.php">Revisão de aulas</a>)' : '') + (err ? ' · ' + err + ' com erro (tenta de novo sozinho)' : '') + '</div>'; });
            document.getElementById('ciaAulas').innerHTML = linhas;
            var rot = {aula_ia: '🤖 Teoria pronta', apostila: '📄 Apostila pronta', videoaula: '🎬 Videoaula pronta', questoes: '📚 Questões prontas'};
            if (d.consumo_mes) {
                var cm = d.consumo_mes, pct = cm.limite > 0 ? Math.round(cm.brl / cm.limite * 100) : 0;
                document.getElementById('ciaConsumo').innerHTML = (cm.gratis ? '💰 IA neste mês (plano grátis: <strong>nada cobrado</strong>; no pago custaria ' : '💰 Gasto real da IA neste mês: ') + '<strong>R$ ' + cm.brl.toFixed(cm.brl > 0 && cm.brl < 0.1 ? 3 : 2).replace('.', ',') + '</strong>'
                    + (cm.gratis ? ')' : '') + (!cm.gratis && cm.limite > 0 ? ' de R$ ' + cm.limite.toFixed(2).replace('.', ',') + ' (' + pct + '%)' + (pct >= 80 ? ' <strong style="color:#b42318">⚠️ perto do limite</strong>' : '') : ' · sem limite definido')
                    + ' · <a href="admin-consumo-ia.php">ver detalhes</a>';
            }
            document.getElementById('ciaRecentes').innerHTML = (d.recentes || []).length ? '<strong style="font-size:14px">Acabou de ficar pronto (clique para conferir):</strong><div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">' + d.recentes.map(function (r) { return '<button type="button" class="admin-btn admin-btn-light admin-btn-small" data-pv="' + r.pv + '" data-id="' + r.id + '"' + (r.consumo ? ' title="Gasto real: ' + esc(r.consumo) + '"' : '') + '>👁 ' + rot[r.tipo] + ': ' + esc(r.titulo) + (r.consumo ? ' <small style="opacity:.7">· ' + esc(r.consumo.split(' · ').pop()) + '</small>' : '') + '</button>'; }).join('') + '</div>' : '';
            var u = d.uso || {}, pr = d.prioridade || {}, fl = d.fila || {};
            var falta = function (t) { return ((fl[t] || {}).pendente || 0) + ((fl[t] || {}).erro || 0); };
            var vozFalta = falta('videoaula') * 8 + (pr.podcasts_pausados ? 0 : falta('podcast') * 4);
            var linhaUso = function (rot, x) { x = x || {}; return '<div>' + rot + ': <strong>' + (x.hoje || 0) + '</strong> chamadas hoje' + (x.bloqueado ? ' · <span style="color:#b42318;font-weight:700">⛔ cota grátis de hoje esgotada (desde ' + x.bloqueado_em + ') · volta ' + esc(u.renova || '') + '</span>' : (x.limite ? ' · limite grátis observado ≈ <strong>' + x.limite + '/dia</strong>' : ' · ✅ ainda tem cota hoje')) + '</div>'; };
            var prev = !vozFalta ? '' : u.voz && u.voz.limite ? '≈ <strong>' + Math.max(1, Math.ceil(vozFalta / u.voz.limite)) + ' dia(s)</strong> para terminar as videoaulas' + (pr.podcasts_pausados ? '' : ' e podcasts') + ' no plano grátis (' + vozFalta + ' falas na fila)' : (vozFalta ? vozFalta + ' falas de voz na fila; a previsão aparece quando o limite for medido' : '');
            document.getElementById('ciaUso').innerHTML = '<strong>📊 Uso da IA grátis (Google) · renova ' + esc(u.renova || '') + '</strong>'
                + '<div class="admin-form-hint" style="display:grid;gap:2px;margin-top:4px">' + linhaUso('✍️ Texto (teorias, questões criadas, leituras)', u.texto) + linhaUso('🎙️ Voz (videoaulas e podcasts)', u.voz) + linhaUso('🔎 Pesquisa na internet (questões reais, provas)', u.busca) + (prev ? '<div>⏱️ ' + prev + '</div>' : '') + '</div>'
;
            if (!window.ciaPriIni) {
                window.ciaPriIni = true;
                document.getElementById('ciaPodPausa').checked = !!pr.podcasts_pausados;
                document.getElementById('ciaPrimeiro').value = pr.primeiro === 'questoes' ? 'questoes' : 'videoaula';
                ['ciaPodPausa', 'ciaPrimeiro'].forEach(function (id) { document.getElementById(id).onchange = function () {
                    fetch('api/edital-job.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({acao: 'prioridade', podcasts_pausados: document.getElementById('ciaPodPausa').checked, primeiro: document.getElementById('ciaPrimeiro').value})})
                        .then(function () { document.getElementById('ciaPriOk').textContent = '✓ salvo'; });
                }; });
            }
            document.getElementById('ciaAgora').innerHTML = (d.agora ? '⚙️ <strong>Agora:</strong> ' + esc(d.agora) + '…' : '')
                + (d.pausa_voz ? '<br>⏸ <strong>Voz em pausa até ' + d.pausa_voz + '</strong>: o Google limita quantas falas grátis por dia. Videoaulas e podcasts continuam sozinhos depois; o resto segue normalmente.' : '')
                + (d.pausa_busca ? '<br>⏸ <strong>Questões aguardando a pesquisa na internet (volta ' + d.pausa_busca + ')</strong>: a IA não cria questão antes de procurar as reais.' : '')
                + (d.pausa_texto ? '<br>⏸ <strong>Texto em pausa até ' + d.pausa_texto + '</strong> (limite do Google): retoma sozinho.' : '');
            var ativos = (d.ativos || 0) + (q.na_fila || 0) + filaPend;
            if (ativosAntes !== null && (d.ativos || 0) < ativosAntes) {
                // Algo terminou: atualiza a página, MENOS se o mentor está preenchendo um formulário.
                if (window.ciaSujo || (document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName))) {
                    var av = document.getElementById('ciaAtualizar');
                    if (av) { av.hidden = false; }
                } else { location.reload(); return; }
            }
            ativosAntes = d.ativos || 0;
            // Só chama a IA se há algo que pode rodar agora (não insiste quando tudo espera a cota renovar).
            var tudoPausado = (d.pausa_voz || pr.podcasts_pausados) && !d.ativos && !(q.na_fila && !d.pausa_busca) && !(d.fila && ((d.fila.aula_ia || {}).pendente || (d.fila.apostila || {}).pendente));
            if (ativos && !tudoPausado && !d.pausa_texto) { rodar(); }
            document.getElementById('ciaStatus').textContent = !ativos ? 'Tudo em dia' : (tudoPausado || d.pausa_texto ? '⏸ Aguardando a cota do Google · ' + ativos + ' tarefa(s) na fila' : 'IA trabalhando · ' + ativos + ' tarefa(s) · pode navegar, ela continua');
        }).catch(function () {});
    }
    atualizar();
    setInterval(atualizar, 5000);
})();
</script>
<dialog id="pvDlg" style="margin:auto;max-width:860px;width:calc(100% - 32px);border:0;border-radius:16px;padding:0;box-shadow:0 20px 60px rgba(0,0,0,.35)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eef0f3;position:sticky;top:0;background:#fff"><strong>👁 Pré-visualização</strong><button type="button" class="admin-btn admin-btn-light admin-btn-small" onclick="document.getElementById('pvDlg').close()">Fechar ✕</button></div>
    <div id="pvCorpo" style="padding:16px;max-height:75vh;overflow:auto"></div>
</dialog>
<style>
    #pvCorpo h2{margin:0 0 4px;font-size:20px}#pvCorpo h3{margin:16px 0 6px;font-size:16px}.pv-sub{color:#6b7280;margin:0 0 8px}
    .pv-status{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}.pv-status span{background:#f3f4f6;border-radius:99px;padding:4px 10px;font-size:13px}
    .pv-bt-l{background:#fff!important;border:1px solid #e5e7eb!important;color:#111;cursor:pointer;font:inherit}
    .pv-bt{display:inline-block;background:#fbb928;color:#111;font-weight:700;border-radius:10px;padding:8px 12px;text-decoration:none}
    .pv-texto{border:1px solid #eef0f3;border-radius:10px;padding:12px;line-height:1.6}.pv-slides li{margin:4px 0}
    .pv-q{border:1px solid #eef0f3;border-radius:10px;padding:12px;margin:10px 0}.pv-q ul{margin:6px 0 6px 18px}.pv-certa{color:#15803d;font-weight:700}
    .pv-tag{font-size:12px;color:#6b7280;margin:0 0 6px}.pv-com{font-size:13.5px;background:#fffaf0;border-radius:8px;padding:8px;margin:6px 0 0}
</style>
<script>
document.addEventListener('click', function (ev) {
    var ac = ev.target.closest('[data-pv-acao]');
    if (ac) {
        var acao = ac.dataset.pvAcao;
        var executar = function () {
            var fd = new FormData(); fd.append('acao', acao); fd.append('id', ac.dataset.id);
            fetch('api/edital-preview.php', {method: 'POST', credentials: 'same-origin', body: fd}).then(function (r) { return r.text(); }).then(function (t) {
                if (acao === 'apagar_questao') { var art = ac.closest('article'); if (art) { art.innerHTML = '<p>🗑 Questão apagada. Para repor, use "Fazer só este" no assunto (passo 5).</p>'; } }
                else { ac.closest('p').innerHTML = t; }
            });
        };
        if (acao === 'apagar_questao') { if (confirm('Apagar esta questão do banco?')) { executar(); } return; }
        // Refazer usa IA: mostra o custo estimado antes.
        fetch('api/ia-estimativa.php?acao=refazer_aula', {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (e) {
            var f = function (v) { return 'R$ ' + v.toFixed(v > 0 && v < 0.1 ? 3 : 2).replace('.', ','); };
            var c = e && e.ia ? '\n\nCusto estimado: ' + f(e.brl) + ' (faixa ' + f(e.brl_min) + ' a ' + f(e.brl_max) + ')\n≈ ' + Number(e.tokens).toLocaleString('pt-BR') + ' tokens · ' + e.chamadas.join(' · ') : '';
            if (confirm('Pedir para a IA refazer esta aula (teoria, videoaula e podcast)?' + c)) { executar(); }
        }).catch(function () { if (confirm('Pedir para a IA refazer esta aula?')) { executar(); } });
        return;
    }
    var b = ev.target.closest('[data-pv]');
    if (!b) { return; }
    ev.preventDefault();
    var dlg = document.getElementById('pvDlg'), corpo = document.getElementById('pvCorpo');
    corpo.innerHTML = '<p>Carregando…</p>';
    if (dlg.showModal) { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    var url = 'api/edital-preview.php?tipo=' + b.dataset.pv + (b.dataset.pv === 'aula' ? '&id=' : '&topico=') + encodeURIComponent(b.dataset.id);
    fetch(url, {credentials: 'same-origin'}).then(function (r) { return r.text(); }).then(function (h) { corpo.innerHTML = h; }).catch(function () { corpo.innerHTML = '<p>Não foi possível carregar.</p>'; });
});
</script>
<?php admin_cursos_scripts(); ?>
<script src="assets/js/ia-custo.js?v=2" data-api="api/ia-estimativa.php"></script>
</body>
</html>
