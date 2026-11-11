<?php
// api/manutencao.php
// Porta de manutenção para o assistente (Claude) ajudar no saneamento SEM senha do painel nem do banco.
//  - Só funciona com a chave MANUTENCAO_TOKEN do .env (cabeçalho Authorization: Bearer ...). Sem a chave no .env: desligada (404).
//  - Só CONTEÚDO e operação da IA: aulas, PDFs, filas, padrão, consumo, saúde. NUNCA dados de alunos (LGPD: minimização).
//  - Não apaga aulas, não mexe em usuários, não envia código. Toda chamada fica registrada (Saúde da plataforma > registro).
//  - Para desligar: apague MANUTENCAO_TOKEN do .env.
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$chave = (string)gab_env('MANUTENCAO_TOKEN', '');
// Cabeçalho próprio (X-Gab-Token): alguns servidores apagam o Authorization antes do PHP.
$enviada = (string)($_SERVER['HTTP_X_GAB_TOKEN'] ?? preg_replace('~^Bearer\s+~i', '', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')));
if (strlen($chave) < 32) {
    http_response_code(404); // desligada: a chave precisa ter pelo menos 32 caracteres
    exit('{"erro":"desligada"}');
}
if (!hash_equals($chave, $enviada)) {
    usleep(400000);
    http_response_code(401);
    exit('{"erro":"chave inválida"}');
}

require_once dirname(__DIR__) . '/includes/podcast-lib.php';
require_once dirname(__DIR__) . '/includes/ia-fila-lib.php';
require_once dirname(__DIR__) . '/includes/ia-consumo-lib.php';
require_once dirname(__DIR__) . '/includes/revisao-aulas-lib.php';
require_once dirname(__DIR__) . '/includes/padrao-lib.php';

$pdo = db();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$entrada = $metodo === 'POST' ? (json_decode((string)file_get_contents('php://input'), true) ?: $_POST) : $_GET;
$acao = (string)($entrada['acao'] ?? 'resumo');

function man_ok(array $d): void
{
    echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function man_log(string $acao, array $det): void
{
    $linha = date('Y-m-d H:i:s') . ' · ' . $acao . ' · ' . mb_substr(json_encode($det, JSON_UNESCAPED_UNICODE), 0, 300) . "\n";
    @file_put_contents(dirname(__DIR__) . '/uploads/.manutencao-registro.log', $linha, FILE_APPEND | LOCK_EX);
}

function man_aula_publica(array $a): array
{
    // Só conteúdo da aula.
    return array_intersect_key($a, array_flip(['id', 'curso_id', 'modulo_id', 'titulo', 'curso', 'materia', 'video_url', 'pdf_arquivo', 'pdf_nome', 'topico_id', 'slides', 'palavras_slide']));
}

if ($metodo === 'POST') {
    man_log($acao, array_diff_key($entrada, ['acao' => 1]));
}

try {
    switch ($acao) {
        // ---------------- leitura ----------------
        case 'resumo':
            $fila = $pdo->query("SELECT tipo, status, COUNT(*) n FROM ia_fila GROUP BY tipo, status")->fetchAll();
            $robo = dirname(__DIR__) . '/uploads/.ia-robo-ultima';
            $aud = padrao_auditar($pdo, (int)($entrada['curso_id'] ?? 0));
            man_ok([
                'agora' => date('c'),
                'robo_ultima_execucao_min' => is_file($robo) ? (int)floor((time() - filemtime($robo)) / 60) : null,
                'cota_google' => podcast_uso_resumo(),
                'pausas' => ['voz' => ia_fila_pausa() ?: 0, 'texto' => ia_fila_pausa_texto() ?: 0, 'busca' => ia_fila_pausa_busca() ?: 0],
                'prioridade' => ia_prioridade(),
                'fila' => $fila,
                'consumo_mes_brl' => ia_consumo_mes_brl($pdo),
                'config_consumo' => ia_consumo_config(),
                'padrao' => [
                    'aulas' => $aud['total'], 'ok_pct' => $aud['ok_pct'],
                    'antigao_antigo' => count($aud['antigao_antigo']), 'podcast_antigo' => count($aud['podcast_antigo']),
                    'videoaula_antiga' => count($aud['videoaula_antiga']), 'sem_teoria' => count($aud['sem_teoria']),
                    'sem_material' => count($aud['sem_material']), 'sem_assunto' => count($aud['sem_assunto']),
                    'titulos' => count($aud['titulos']), 'duplicadas' => count($aud['duplicadas']), 'pdf' => $aud['pdf'], 'questoes' => $aud['questoes'],
                ],
            ]);
            // no break
        case 'cursos':
            man_ok(['cursos' => $pdo->query('SELECT c.id, c.nome, (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.curso_id = c.id) aulas FROM admin_cursos c ORDER BY c.nome')->fetchAll()]);
            // no break
        case 'padrao_lista': // detalhes de uma categoria da auditoria
            $aud = padrao_auditar($pdo, (int)($entrada['curso_id'] ?? 0));
            $cat = (string)($entrada['categoria'] ?? 'titulos');
            if (!isset($aud[$cat]) || !is_array($aud[$cat])) {
                throw new RuntimeException('Categoria inválida.');
            }
            $lista = $cat === 'duplicadas' ? array_map(static fn($g) => array_map('man_aula_publica', $g), $aud[$cat])
                : ($cat === 'titulos' || $cat === 'questoes' || $cat === 'pdf' ? $aud[$cat] : array_map('man_aula_publica', $aud[$cat]));
            man_ok(['categoria' => $cat, 'itens' => array_slice($lista, 0, 500), 'total' => count($lista)]);
            // no break
        case 'revisao': // aulas com PDF suspeito ou não conferido
            $sit = "CASE WHEN TRIM(COALESCE(a.pdf_arquivo,'')) = '' THEN 'sem_pdf' WHEN r.aula_id IS NULL OR r.pdf <> a.pdf_arquivo THEN 'pendente' ELSE r.status END";
            $st = $pdo->prepare("SELECT a.id, a.curso_id, a.titulo, c.nome curso, m.nome materia, a.pdf_arquivo, a.pdf_nome, a.video_url, $sit situacao, r.assunto_pdf, r.motivo
                                   FROM admin_curso_aulas a LEFT JOIN aula_revisao r ON r.aula_id = a.id LEFT JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_materias m ON m.id = a.materia_id
                                  WHERE $sit = :s ORDER BY c.nome, a.id LIMIT 500");
            $st->execute([':s' => (string)($entrada['situacao'] ?? 'suspeita')]);
            man_ok(['aulas' => $st->fetchAll()]);
            // no break
        case 'aula': // conteúdo de uma aula (para conferir texto, PDF, videoaula)
            $st = $pdo->prepare('SELECT a.id, a.curso_id, a.modulo_id, a.titulo, a.descricao, a.video_url, a.pdf_arquivo, a.pdf_nome, a.topico_id, c.nome curso, m.nome materia, t.titulo assunto
                                   FROM admin_curso_aulas a LEFT JOIN admin_cursos c ON c.id = a.curso_id LEFT JOIN admin_materias m ON m.id = a.materia_id
                                   LEFT JOIN admin_materia_topicos t ON t.id = a.topico_id WHERE a.id = :id');
            $st->execute([':id' => (int)($entrada['id'] ?? 0)]);
            $a = $st->fetch();
            if (!$a) {
                throw new RuntimeException('Aula não encontrada.');
            }
            $a['descricao'] = mb_substr(trim(html_entity_decode(strip_tags((string)$a['descricao']), ENT_QUOTES, 'UTF-8')), 0, 4000);
            $v = $pdo->prepare('SELECT status, roteiro FROM aula_videoaulas WHERE aula_id = :id');
            $v->execute([':id' => $a['id']]);
            $vr = $v->fetch();
            $a['videoaula'] = $vr ? ['status' => $vr['status'], 'roteiro' => json_decode((string)$vr['roteiro'], true)] : null;
            $a['revisao'] = rev_status($pdo, (int)$a['id']);
            man_ok(['aula' => $a]);
            // no break
        case 'fila_erros':
            man_ok(['erros' => $pdo->query("SELECT f.id, f.tipo, f.aula_id, f.tentativas, f.erro, f.atualizado_em FROM ia_fila f WHERE f.status = 'erro' ORDER BY f.atualizado_em DESC LIMIT 200")->fetchAll()]);
            // no break
        case 'consumo':
            man_ok([
                'mes_brl' => ia_consumo_mes_brl($pdo),
                'por_acao' => $pdo->query("SELECT acao, COUNT(DISTINCT execucao) execucoes, SUM(tokens_entrada) entrada, SUM(tokens_saida) saida, ROUND(SUM(custo_usd),4) usd FROM ia_consumo WHERE criado_em >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY acao")->fetchAll(),
                'estimativas' => array_map(static fn($t) => ia_consumo_estimar($pdo, $t, 1), array_combine(['videoaula', 'podcast', 'aula_ia', 'apostila', 'conferir_pdf', 'questoes'], ['videoaula', 'podcast', 'aula_ia', 'apostila', 'conferir_pdf', 'questoes'])),
            ]);
            // no break
        case 'registro':
            $arq = dirname(__DIR__) . '/uploads/.manutencao-registro.log';
            man_ok(['linhas' => is_file($arq) ? array_slice(file($arq, FILE_IGNORE_NEW_LINES) ?: [], -100) : []]);
            // no break

        // ---------------- ações (só POST) ----------------
        case 'corrigir':
            if ($metodo !== 'POST') {
                throw new RuntimeException('Use POST.');
            }
            $tipo = (string)($entrada['tipo'] ?? '');
            if (!in_array($tipo, ['padrao_titulos', 'padrao_teoria', 'padrao_antigao', 'padrao_videoaulas', 'padrao_antigao_refazer', 'padrao_podcasts', 'padrao_assunto'], true)) {
                throw new RuntimeException('Correção inválida.');
            }
            man_ok(['feitos' => padrao_corrigir($pdo, $tipo, (int)($entrada['curso_id'] ?? 0), (array)($entrada['ids'] ?? []))]);
            // no break
        case 'conferir_pdfs':
            if ($metodo !== 'POST') {
                throw new RuntimeException('Use POST.');
            }
            man_ok(['na_fila' => rev_varrer($pdo, 5000)]);
            // no break
        case 'revisar': // decisão sobre o PDF de uma aula: ok | sem_pdf | reconferir | trocar (pdf_existente)
            if ($metodo !== 'POST') {
                throw new RuntimeException('Use POST.');
            }
            $id = (int)($entrada['aula_id'] ?? 0);
            $st = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id');
            $st->execute([':id' => $id]);
            $a = $st->fetch();
            if (!$a) {
                throw new RuntimeException('Aula não encontrada.');
            }
            $d = (string)($entrada['decisao'] ?? '');
            if ($d === 'ok') {
                rev_gravar($pdo, $id, (string)$a['pdf_arquivo'], 'ok_manual', null, 'Conferido pelo assistente a pedido do mentor.', 'mentor');
            } elseif ($d === 'reconferir') {
                $pdo->prepare('DELETE FROM aula_revisao WHERE aula_id = :a')->execute([':a' => $id]);
                ia_fila_adicionar($pdo, 'conferir_pdf', $id);
            } elseif ($d === 'sem_pdf') {
                $pdo->prepare("UPDATE admin_curso_aulas SET pdf_arquivo = '', pdf_nome = '' WHERE id = :id")->execute([':id' => $id]);
                $pdo->prepare('DELETE FROM aula_revisao WHERE aula_id = :a')->execute([':a' => $id]);
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $id]);
                $pdo->prepare("DELETE FROM ia_fila WHERE aula_id = :a AND tipo IN ('videoaula','apostila','podcast') AND status = 'erro'")->execute([':a' => $id]);
                if (trim((string)$a['video_url']) === '') {
                    ia_fila_adicionar($pdo, 'aula_ia', $id);
                }
            } elseif ($d === 'trocar') {
                $novo = (string)($entrada['pdf_existente'] ?? '');
                $ok = $pdo->prepare('SELECT pdf_nome FROM admin_curso_aulas WHERE pdf_arquivo = :p LIMIT 1');
                $ok->execute([':p' => $novo]);
                $nome = $ok->fetchColumn();
                if ($novo === '' || $nome === false) {
                    throw new RuntimeException('Só é possível trocar por um PDF que já está na plataforma.');
                }
                $pdo->prepare('UPDATE admin_curso_aulas SET pdf_arquivo = :p, pdf_nome = :n WHERE id = :id')->execute([':p' => $novo, ':n' => (string)$nome ?: basename($novo), ':id' => $id]);
                $pdo->prepare('DELETE FROM aula_videoaulas WHERE aula_id = :a')->execute([':a' => $id]);
                rev_gravar($pdo, $id, $novo, 'ok_manual', null, 'PDF trocado pelo assistente a pedido do mentor.', 'mentor');
                if (trim((string)$a['video_url']) === '') {
                    ia_fila_adicionar($pdo, 'videoaula', $id);
                }
            } else {
                throw new RuntimeException('Decisão inválida.');
            }
            man_ok(['aula_id' => $id, 'decisao' => $d]);
            // no break
        case 'controle': // GET mostra; POST salva {ligados:{tipo:bool}, teto:{texto,voz,busca}}
            if ($metodo === 'POST') {
                $atual = ia_controle();
                $lig = array_replace($atual['ligados'], array_map('boolval', array_intersect_key((array)($entrada['ligados'] ?? []), ia_controle_tipos())));
                $teto = array_replace($atual['teto'], array_map('intval', array_intersect_key((array)($entrada['teto'] ?? []), $atual['teto'])));
                ia_controle_salvar(['ligados' => $lig, 'teto' => $teto]);
            }
            man_ok(['controle' => ia_controle(), 'uso_hoje' => podcast_uso_resumo()]);
            // no break
        case 'titulos_edital': // GET = prévia; POST aplica (ids opcionais)
            $todos = padrao_titulos_edital($pdo, (int)($entrada['curso_id'] ?? 0));
            $lista = array_values(array_filter($todos, static fn($t) => $t['confere'])); // os divergentes só com ids explícitos
            if ($metodo !== 'POST') {
                man_ok(['total' => count($lista), 'itens' => array_slice($lista, 0, 600), 'assunto_divergente' => array_values(array_filter($todos, static fn($t) => !$t['confere']))]);
            }
            if (!empty($entrada['ids'])) {
                $lista = $todos;
            }
            $sel = array_flip(array_map('intval', (array)($entrada['ids'] ?? [])));
            $up = $pdo->prepare('UPDATE admin_curso_aulas SET titulo = :t WHERE id = :id');
            $n = 0;
            foreach ($lista as $t) {
                if (!$sel || isset($sel[$t['id']])) {
                    $up->execute([':t' => $t['para'], ':id' => $t['id']]);
                    $n++;
                }
            }
            man_ok(['alterados' => $n]);
            // no break
        case 'provisorias': // GET = pares provisória x real; POST troca (ids das provisórias opcionais)
            $pares = padrao_provisorias_repetidas($pdo, (int)($entrada['curso_id'] ?? 0));
            if ($metodo !== 'POST') {
                man_ok(['total' => count($pares), 'pares' => $pares]);
            }
            $sel = array_flip(array_map('intval', (array)($entrada['ids'] ?? [])));
            $n = 0;
            foreach ($pares as $p) {
                if (!$sel || isset($sel[$p['provisoria']['id']])) {
                    padrao_trocar_provisoria($pdo, $p['provisoria']['id'], $p['real']['id']);
                    $n++;
                }
            }
            man_ok(['removidas' => $n]);
            // no break
        case 'perfis': // carreira|nível|estado de cada curso (reaproveitamento só entre iguais). POST {curso_id, carreira, nivel, uf}
            require_once dirname(__DIR__) . '/includes/reaproveitar-lib.php';
            if ($metodo === 'POST') {
                curso_perfil($pdo, 0); // garante a tabela
                $pdo->prepare('REPLACE INTO curso_perfil (curso_id, carreira, nivel, uf) VALUES (:c, :ca, :n, :u)')
                    ->execute([':c' => (int)($entrada['curso_id'] ?? 0), ':ca' => mb_substr((string)($entrada['carreira'] ?? ''), 0, 20), ':n' => mb_substr((string)($entrada['nivel'] ?? ''), 0, 20), ':u' => strtoupper(mb_substr((string)($entrada['uf'] ?? ''), 0, 2))]);
            }
            $lista = [];
            foreach ($pdo->query('SELECT id, nome FROM admin_cursos ORDER BY nome') as $c) {
                $lista[] = ['curso_id' => (int)$c['id'], 'curso' => $c['nome'], 'perfil' => curso_perfil($pdo, (int)$c['id'])];
            }
            man_ok(['cursos' => $lista]);
            // no break
        case 'fila_tentar_de_novo': // erros comuns (não os que esperam revisão) voltam para a fila
            if ($metodo !== 'POST') {
                throw new RuntimeException('Use POST.');
            }
            $n = $pdo->exec("UPDATE ia_fila SET status = 'pendente', tentativas = 0, erro = NULL WHERE status = 'erro' AND (erro IS NULL OR erro NOT LIKE 'Aguardando%')");
            man_ok(['voltaram' => (int)$n]);
            // no break
        case 'prioridade':
            if ($metodo !== 'POST') {
                throw new RuntimeException('Use POST.');
            }
            $p = ia_prioridade();
            if (isset($entrada['podcasts_pausados'])) {
                $p['podcasts_pausados'] = (bool)$entrada['podcasts_pausados'];
            }
            if (in_array($entrada['primeiro'] ?? '', ['videoaula', 'questoes'], true)) {
                $p['primeiro'] = $entrada['primeiro'];
            }
            ia_prioridade_salvar($p);
            man_ok(['prioridade' => ia_prioridade()]);
            // no break
        default:
            throw new RuntimeException('Ação desconhecida.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
