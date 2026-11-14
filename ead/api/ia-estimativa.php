<?php
// api/ia-estimativa.php — quanto uma ação vai gastar ANTES de executar (só admin).
// Resposta: {ia:false} quando a ação não usa IA; senão o detalhamento para a confirmação.
declare(strict_types=1);

require_once __DIR__ . '/admin-auth.php';
auth_admin_require_login();
require_once dirname(__DIR__) . '/includes/admin-cursos-lib.php';
require_once dirname(__DIR__) . '/includes/podcast-lib.php';
require_once dirname(__DIR__) . '/includes/ia-consumo-lib.php';
header('Content-Type: application/json; charset=utf-8');
session_write_close(); // só lê: não segura a sessão do admin

$pdo = admin_cursos_db();
$acao = (string)($_GET['acao'] ?? '');
$concursoId = (int)($_GET['concurso_id'] ?? 0);
$n = max(0, (int)($_GET['n'] ?? 0));

$cursosDoConcurso = static function () use ($pdo, $concursoId): array {
    $st = $pdo->prepare('SELECT DISTINCT curso_id FROM concurso_cargos WHERE concurso_id = :c AND curso_id IS NOT NULL');
    $st->execute([':c' => $concursoId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) ?: [0];
};

// ação da tela => lista de [tipo de gasto, quantidade]
$partes = null;
try {
    switch ($acao) {
        case 'ler_edital': case 'buscar_prova': case 'sugerir_existentes': case 'enviar_questoes': case 'sugerir_plano':
            $partes = [['edital', 1]];
            break;
        case 'sugerir_aulas':
            $partes = [['edital', max(1, (int)ceil($n / 4))]];
            break;
        case 'gerar_hacks': case 'gerar_vertical':
            $st = $pdo->prepare('SELECT COUNT(*) FROM concurso_cargos WHERE concurso_id = :c');
            $st->execute([':c' => $concursoId]);
            $partes = [['edital', !empty($_GET['cargo_id']) ? 1 : max(1, (int)$st->fetchColumn())]];
            break;
        case 'aula_item':
            $partes = [['aula_ia', 1], ['videoaula', 1]];
            break;
        case 'questoes_item':
            $partes = [['questoes', 1]];
            break;
        case 'completar_questoes':
            require_once dirname(__DIR__) . '/includes/edital-hack-lib.php';
            $q = 0;
            foreach (hack_questoes_situacao($pdo, $concursoId)['itens'] as $it) {
                $q += $it['questoes'] < 10 ? 1 : 0;
            }
            $partes = [['questoes', $q]];
            break;
        case 'gerar_faltantes':
            require_once dirname(__DIR__) . '/includes/edital-lib.php';
            $q = 0;
            foreach (edital_andamento($pdo, $concursoId) as $an) {
                if (!empty($an['cargo']['curso_id'])) {
                    foreach ($an['itens'] as $it) {
                        $q += $it['aulas'] ? 0 : 1;
                    }
                }
            }
            $partes = [['aula_ia', $q], ['videoaula', $q]];
            break;
        case 'refazer_formato':
            $q = (int)$pdo->query('SELECT COUNT(*) FROM admin_curso_aulas a JOIN aula_videoaulas v ON v.aula_id = a.id WHERE a.curso_id IN (' . implode(',', $cursosDoConcurso()) . ')')->fetchColumn();
            $partes = [['videoaula', $q]];
            break;
        // admin-ia.php (campo "fila")
        case 'todas':
            $q = (int)$pdo->query("SELECT COUNT(*) FROM admin_curso_aulas a WHERE NOT EXISTS (SELECT 1 FROM ia_fila f WHERE f.aula_id = a.id AND f.tipo = 'apostila' AND f.status = 'feito')")->fetchColumn();
            $partes = [['apostila', $q], ['podcast', $q]];
            break;
        case 'refazer_podcasts':
            $partes = [['podcast', (int)$pdo->query('SELECT COUNT(*) FROM aula_podcasts')->fetchColumn()]];
            break;
        case 'videoaulas':
            $q = (int)$pdo->query("SELECT COUNT(*) FROM admin_curso_aulas a WHERE COALESCE(TRIM(a.video_url), '') = '' AND COALESCE(TRIM(a.pdf_arquivo), '') <> ''
                                    AND NOT EXISTS (SELECT 1 FROM aula_videoaulas v WHERE v.aula_id = a.id AND v.status = 'pronto')")->fetchColumn();
            $partes = [['videoaula', $q]];
            break;
        case 'refazer_aula': // prévia: refazer uma videoaula
            $partes = [['videoaula', 1]];
            break;
        // admin-revisao-aulas.php
        case 'sem_pdf':
            $partes = [['aula_ia', 1], ['videoaula', 1]];
            break;
        case 'trocar':
            $partes = [['videoaula', 1]];
            break;
        case 'reconferir':
            $partes = [['conferir_pdf', 1]];
            break;
        // admin-padrao.php (Auditoria de padrão): conta na hora o que cada correção vai mandar para a IA
        case 'padrao_teoria': case 'padrao_antigao': case 'padrao_videoaulas': case 'padrao_antigao_refazer': case 'padrao_podcasts': case 'padrao_assunto':
            require_once dirname(__DIR__) . '/includes/padrao-lib.php';
            $aud = padrao_auditar($pdo, (int)($_GET['curso_id'] ?? 0));
            $partes = [
                'padrao_teoria' => [['apostila', count($aud['sem_teoria'])], ['podcast', count($aud['sem_teoria'])]],
                'padrao_antigao' => [['aula_ia', count($aud['sem_material'])], ['videoaula', count($aud['sem_material'])], ['podcast', count($aud['sem_material'])]],
                'padrao_videoaulas' => [['videoaula', count($aud['videoaula_antiga'])]],
                'padrao_antigao_refazer' => [['aula_ia', count($aud['antigao_antigo'])], ['videoaula', count($aud['antigao_antigo'])], ['podcast', count($aud['antigao_antigo'])]],
                'padrao_podcasts' => [['podcast', count($aud['podcast_antigo'])]],
                'padrao_assunto' => [['classificar', count($aud['sem_assunto']) + (int)($aud['questoes']['sem_assunto'] ?? 0)]],
            ][$acao];
            break;
        case 'conferir_todas':
            $partes = [['conferir_pdf', $n]];
            break;
    }
} catch (Throwable $e) {
    $partes = [['outro', 1]];
}
if ($partes === null) {
    echo json_encode(['ia' => false]);
    exit;
}

$tot = ['tokens' => 0, 'brl' => 0.0, 'brl_min' => 0.0, 'brl_max' => 0.0, 'texto' => 0, 'voz' => 0, 'busca' => 0];
$linhas = [];
$ultima = null;
foreach ($partes as [$tipo, $qtd]) {
    if ($qtd <= 0) {
        continue;
    }
    $e = ia_consumo_estimar($pdo, $tipo, $qtd);
    $ultima = $e;
    $linhas[] = $qtd . '× ' . $e['acao'] . ': ' . ia_consumo_brl($e['brl_min']) . ' a ' . ia_consumo_brl($e['brl_max']) . ' (' . $e['base'] . ')';
    $tot['tokens'] += $e['tokens'];
    $tot['brl'] += $e['brl'];
    $tot['brl_min'] += $e['brl_min'];
    $tot['brl_max'] += $e['brl_max'];
    $tot['texto'] += $e['chamadas_texto'];
    $tot['voz'] += $e['chamadas_voz'];
    $tot['busca'] += $e['chamadas_busca'];
}
$cfg = ia_consumo_config();
$mes = ia_consumo_mes_brl($pdo);
$uso = podcast_uso_resumo();
$gratis = [];
foreach (['texto' => 'texto', 'voz' => 'voz', 'busca' => 'pesquisa'] as $k => $nome) {
    if ($tot[$k] > 0) {
        $lim = $uso[$k]['limite'] ?? null;
        $gratis[] = $tot[$k] . ' chamada(s) de ' . $nome . ($lim ? ' (restam ' . max(0, $lim - (int)$uso[$k]['hoje']) . ' grátis hoje)' : '');
    }
}
echo json_encode([
    'ia' => true,
    'gratis' => $cfg['plano'] !== 'pago',
    'vazio' => !$linhas,
    'linhas' => $linhas,
    'tokens' => $tot['tokens'],
    'chamadas' => $gratis,
    'brl' => round($tot['brl'], 4), 'brl_min' => round($tot['brl_min'], 4), 'brl_max' => round($tot['brl_max'], 4),
    'mes_brl' => $mes, 'limite' => (float)$cfg['limite_mensal_brl'],
    'estoura' => $cfg['plano'] === 'pago' && (float)$cfg['limite_mensal_brl'] > 0 && $mes + $tot['brl_max'] > (float)$cfg['limite_mensal_brl'],
    'renova' => (string)($uso['renova'] ?? ''),
], JSON_UNESCAPED_UNICODE);
