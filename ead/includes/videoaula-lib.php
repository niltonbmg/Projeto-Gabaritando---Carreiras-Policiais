<?php
// includes/videoaula-lib.php
// Videoaula IA: para aulas que só têm PDF (ou sem vídeo), a IA lê o material e
// monta 6 a 10 slides com narração do Antigão (a IA do Projeto Gabaritando) + paradas com pergunta.
// O aluno vê como um vídeo (slides trocando sincronizados com a voz), mas é
// página + áudio: leve e sem custo (mesma voz do podcast, Gemini TTS).
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';

if (!function_exists('videoaula_bootstrap')) {
    function videoaula_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_videoaulas (
            aula_id INT UNSIGNED NOT NULL PRIMARY KEY,
            status ENUM('gerando','pronto','erro') NOT NULL DEFAULT 'gerando',
            roteiro MEDIUMTEXT NULL,
            partes TINYINT UNSIGNED NOT NULL DEFAULT 0,
            partes_ok TINYINT UNSIGNED NOT NULL DEFAULT 0,
            audios TEXT NULL,
            erro VARCHAR(500) NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_videoaula_respostas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            aula_id INT UNSIGNED NOT NULL,
            parada TINYINT UNSIGNED NOT NULL,
            correta TINYINT(1) NOT NULL DEFAULT 0,
            respondido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_vr (usuario_id, aula_id, parada)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function videoaula_pasta(): string
    {
        $dir = dirname(__DIR__) . '/uploads/videoaulas';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /** Material da aula para a videoaula: aqui o PDF é a fonte principal, então sempre vai junto. */
    function videoaula_material(PDO $pdo, int $aulaId): array
    {
        try {
            $aula = podcast_material_da_aula($pdo, $aulaId);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Esta aula ainda não tem material (PDF ou apostila) para gerar a videoaula.');
        }
        if ($aula['pdf_base64'] === '' && trim((string)$aula['pdf_arquivo']) !== '' && !preg_match('~^https?://~i', (string)$aula['pdf_arquivo'])) {
            $caminho = realpath(dirname(__DIR__) . '/' . ltrim(preg_replace('~^/?ead/~', '', (string)$aula['pdf_arquivo']), '/'));
            $raiz = realpath(dirname(__DIR__) . '/uploads');
            if ($caminho && $raiz && strpos($caminho, $raiz) === 0 && is_file($caminho) && filesize($caminho) <= 8 * 1024 * 1024) {
                $aula['pdf_base64'] = base64_encode((string)file_get_contents($caminho));
            }
        }
        return $aula;
    }

    /** Passo 1: a IA monta slides, narração e paradas. */
    function videoaula_iniciar(PDO $pdo, int $aulaId, bool $refazer = false): array
    {
        $pdo = podcast_reconectar($pdo);
        videoaula_bootstrap($pdo);
        $atual = videoaula_linha($pdo, $aulaId);
        if (!$refazer && $atual && $atual['status'] === 'pronto') {
            return videoaula_status($pdo, $aulaId);
        }
        if (!$refazer && $atual && $atual['status'] === 'gerando' && $atual['roteiro'] && strtotime((string)$atual['atualizado_em']) > time() - 300) {
            return ['status' => 'gerando', 'partes' => (int)$atual['partes'], 'partes_ok' => (int)$atual['partes_ok']];
        }
        // Mesmo material em outro curso já tem videoaula pronta no padrão novo: copia, sem gastar IA.
        require_once __DIR__ . '/reaproveitar-lib.php';
        if (!$refazer && ia_reaproveitar_videoaula($pdo, $aulaId)) {
            return videoaula_status($pdo, $aulaId);
        }

        $aula = videoaula_material($pdo, $aulaId);
        $prompt = "Você é professor e designer didático do Projeto Gabaritando, cursinho para carreiras policiais e militares da Bahia.\n"
            . "Monte uma VIDEOAULA COMPLETA de 10 a 15 minutos sobre a aula abaixo: slides + narração + paradas com pergunta. Explique com calma, como numa aula presencial: conceito, exemplo, contraexemplo e como a banca cobra.\n\n"
            . "DIDÁTICA (obrigatória)\n"
            . "- Slide 1: abertura. Narração começa com \"Fala, combatente! Aqui é o Antigão, do Projeto Gabaritando\" (o Antigão é a IA professora: veterano experiente e bem-humorado) e diz o que o aluno vai aprender e por que cai na prova.\n"
            . "- Do 2º ao penúltimo: um conceito por slide, do mais simples ao mais difícil. Título curto, 2 a 4 tópicos de no máximo 12 palavras, e um 'destaque' (macete, pegadinha da banca ou exemplo).\n"
            . "- Último slide: resumo em 3 tópicos e a chamada: resolver pelo menos 5 questões do assunto. Termine a narração com \"Fé na missão: a farda é questão de tempo!\".\n"
            . "- Narração: fala natural e PAUSADA de professor, 120 a 170 palavras por slide (cerca de 1 minuto falado), frases curtas, com pelo menos um exemplo concreto por slide, sem ler os tópicos palavra por palavra, números e datas por extenso. A narração é só o que o professor fala: nada de instruções, rubricas, marcações ou títulos entre colchetes.\n"
            . "- Entre 10 e 14 slides. Depois dos slides 4, 8 e 12 (se houver), uma PARADA: pergunta de múltipla escolha estilo banca, 4 alternativas, com explicação curta do porquê.\n"
            . "- Use SOMENTE o conteúdo do material (texto e PDF anexo). Não invente leis, números ou datas.\n"
            . "- Sempre \"Projeto Gabaritando\" por extenso; o aluno é \"combatente\".\n\n"
            . "VISUAL DE CADA SLIDE (campo \"visual\"): escolha o formato que MELHOR explica aquele conteúdo e varie ao longo da aula:\n"
            . "- conceito {\"tipo\":\"conceito\",\"termo\":\"...\",\"definicao\":\"...\"} para definições\n"
            . "- comparacao {\"tipo\":\"comparacao\",\"colunas\":[{\"titulo\":\"...\",\"itens\":[\"...\"]},{\"titulo\":\"...\",\"itens\":[\"...\"]}]} para X × Y\n"
            . "- passos {\"tipo\":\"passos\",\"itens\":[\"...\"]} para sequência/procedimento\n"
            . "- linha_do_tempo {\"tipo\":\"linha_do_tempo\",\"marcos\":[{\"rotulo\":\"1930\",\"texto\":\"...\"}]} para história/prazos (2 a 5 marcos)\n"
            . "- lei {\"tipo\":\"lei\",\"fonte\":\"CF, art. 5º, XI\",\"texto\":\"trecho literal\",\"grifo\":\"parte do texto que a banca cobra\"} só com texto que está no material\n"
            . "- pegadinha {\"tipo\":\"pegadinha\",\"errado\":\"como a banca escreve\",\"certo\":\"o correto e por quê\"}\n"
            . "- exemplo {\"tipo\":\"exemplo\",\"situacao\":\"...\",\"resolucao\":\"...\"}\n"
            . "- resumo {\"tipo\":\"resumo\",\"itens\":[\"...\"]} no último slide; lista {\"tipo\":\"lista\"} usa os tópicos\n"
            . "Em todo visual inclua \"pose\" do Antigão (explica|alerta|pensa|comemora) e \"balao\": frase curta dele (até 7 palavras). Textos curtos: itens até 10 palavras.\n"
            . "Responda SOMENTE em JSON:\n"
            . "{\"slides\":[{\"emoji\":\"📌\",\"titulo\":\"...\",\"topicos\":[\"...\"],\"destaque\":\"...\",\"visual\":{\"tipo\":\"...\",\"pose\":\"explica\",\"balao\":\"...\"},\"narracao\":\"...\"}],"
            . "\"paradas\":[{\"apos_slide\":3,\"pergunta\":\"...\",\"alternativas\":[\"...\",\"...\",\"...\",\"...\"],\"correta\":0,\"explicacao\":\"...\"}]}\n\n"
            . "CURSO: " . $aula['curso'] . "\nMATÉRIA: " . $aula['materia'] . "\nAULA: " . $aula['titulo'] . "\n\nMATERIAL DA AULA:\n" . $aula['texto'];
        $partes = [['text' => $prompt]];
        if ($aula['pdf_base64'] !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $aula['pdf_base64']]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, [
            'contents' => [['parts' => $partes]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.6],
        ], 150);
        $pdo = podcast_reconectar($pdo);
        $roteiro = videoaula_validar(json_decode((string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? ''), true));
        $roteiro['formato'] = 2; // padrão atual (10 a 15 min, narração calma 120–170 palavras por slide); a Auditoria de padrão usa esta marca
        require_once __DIR__ . '/texto-limpo-lib.php';
        array_walk_recursive($roteiro, static function (&$v): void { // slides e narração sem LaTeX (a voz leria "barra times")
            if (is_string($v)) {
                $v = gab_sem_latex($v);
            }
        });

        foreach (glob(videoaula_pasta() . '/aula-' . $aulaId . '-*') ?: [] as $velho) {
            @unlink($velho);
        }
        $pdo->prepare("INSERT INTO aula_videoaulas (aula_id, status, roteiro, partes, partes_ok, audios, erro) VALUES (:a, 'gerando', :r, :p, 0, '[]', NULL)
                       ON DUPLICATE KEY UPDATE status = 'gerando', roteiro = VALUES(roteiro), partes = VALUES(partes), partes_ok = 0, audios = '[]', erro = NULL")
            ->execute([':a' => $aulaId, ':r' => json_encode($roteiro, JSON_UNESCAPED_UNICODE), ':p' => count($roteiro['slides'])]);
        return ['status' => 'gerando', 'partes' => count($roteiro['slides']), 'partes_ok' => 0];
    }

    /** Visual do slide (formato escolhido pela IA): só os campos conhecidos, com tamanho limitado. */
    function videoaula_visual_limpo($v): ?array
    {
        if (!is_array($v)) {
            return null;
        }
        $t = (string)($v['tipo'] ?? '');
        $tipos = ['conceito', 'comparacao', 'passos', 'linha_do_tempo', 'lei', 'pegadinha', 'exemplo', 'resumo', 'lista'];
        if (!in_array($t, $tipos, true)) {
            return null;
        }
        $tx = static fn($x, int $n = 200): string => mb_substr(trim((string)$x), 0, $n);
        $lista = static fn($x, int $max = 5): array => array_values(array_filter(array_map(static fn($i) => mb_substr(trim((string)$i), 0, 140), array_slice((array)$x, 0, $max)), 'strlen'));
        $o = ['tipo' => $t, 'pose' => in_array($v['pose'] ?? '', ['explica', 'alerta', 'pensa', 'comemora'], true) ? $v['pose'] : ($t === 'pegadinha' ? 'alerta' : ($t === 'resumo' ? 'comemora' : 'explica')),
            'balao' => $tx($v['balao'] ?? '', 60)];
        switch ($t) {
            case 'conceito': $o += ['termo' => $tx($v['termo'] ?? '', 60), 'definicao' => $tx($v['definicao'] ?? '', 260)]; if ($o['termo'] === '') { return null; } break;
            case 'comparacao':
                $cols = [];
                foreach (array_slice((array)($v['colunas'] ?? []), 0, 2) as $c) {
                    $cols[] = ['titulo' => $tx($c['titulo'] ?? '', 60), 'itens' => $lista($c['itens'] ?? [], 4)];
                }
                if (count($cols) < 2) { return null; }
                $o['colunas'] = $cols; break;
            case 'passos': case 'resumo': $o['itens'] = $lista($v['itens'] ?? [], 5); if (!$o['itens']) { return null; } break;
            case 'linha_do_tempo':
                $o['marcos'] = [];
                foreach (array_slice((array)($v['marcos'] ?? []), 0, 5) as $m) {
                    if (trim((string)($m['rotulo'] ?? '')) !== '') { $o['marcos'][] = ['rotulo' => $tx($m['rotulo'], 20), 'texto' => $tx($m['texto'] ?? '', 90)]; }
                }
                if (count($o['marcos']) < 2) { return null; } break;
            case 'lei': $o += ['fonte' => $tx($v['fonte'] ?? '', 80), 'texto' => $tx($v['texto'] ?? '', 520), 'grifo' => $tx($v['grifo'] ?? '', 260)]; if ($o['texto'] === '') { return null; } break;
            case 'pegadinha': $o += ['errado' => $tx($v['errado'] ?? '', 200), 'certo' => $tx($v['certo'] ?? '', 260)]; if ($o['errado'] === '' || $o['certo'] === '') { return null; } break;
            case 'exemplo': $o += ['situacao' => $tx($v['situacao'] ?? '', 260), 'resolucao' => $tx($v['resolucao'] ?? '', 260)]; if ($o['situacao'] === '') { return null; } break;
        }
        return $o;
    }

    /**
     * Visual novo para videoaula já pronta, SEM regravar o áudio: a IA (só texto, barato) escolhe o formato de cada slide.
     * Chamado pela fila (tipo 'visual').
     */
    function videoaula_visual_enriquecer(PDO $pdo, int $aulaId): int
    {
        $v = videoaula_linha($pdo, $aulaId);
        if (!$v || $v['status'] !== 'pronto') {
            return 0;
        }
        $rot = json_decode((string)$v['roteiro'], true);
        $slides = (array)($rot['slides'] ?? []);
        if (!$slides || count(array_filter($slides, static fn($s) => !empty($s['visual']))) === count($slides)) {
            return 0;
        }
        $resumo = array_map(static fn($s, $i) => ['n' => $i + 1, 'titulo' => $s['titulo'] ?? '', 'topicos' => $s['topicos'] ?? [], 'destaque' => $s['destaque'] ?? '', 'fala' => mb_substr((string)($s['narracao'] ?? ''), 0, 700)], $slides, array_keys($slides));
        $prompt = "Você é designer didático do Projeto Gabaritando. Para CADA slide desta videoaula (o áudio já está gravado; não mude o conteúdo), escolha o formato visual que melhor explica o que é falado e preencha com o que JÁ está no slide e na fala.\n"
            . "Formatos: conceito{termo,definicao} · comparacao{colunas:[{titulo,itens[]},{titulo,itens[]}]} · passos{itens[]} · linha_do_tempo{marcos:[{rotulo,texto}]} · lei{fonte,texto,grifo} (só se a fala citar a lei) · pegadinha{errado,certo} · exemplo{situacao,resolucao} · resumo{itens[]} (último) · lista{} (usa os tópicos).\n"
            . "Em cada um: \"pose\" do Antigão (explica|alerta|pensa|comemora) e \"balao\" (até 7 palavras). Varie os formatos. Itens até 10 palavras.\n"
            . 'Responda SOMENTE em JSON: {"visuais":[{"n":1,"tipo":"...","pose":"...","balao":"..."}]}' . "\n\nSLIDES: " . json_encode($resumo, JSON_UNESCAPED_UNICODE);
        $r = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3]], 90);
        $j = json_decode((string)($r['candidates'][0]['content']['parts'][0]['text'] ?? ''), true);
        $n = 0;
        foreach ((array)($j['visuais'] ?? []) as $vis) {
            $i = (int)($vis['n'] ?? 0) - 1;
            if (isset($slides[$i]) && ($limpo = videoaula_visual_limpo($vis))) {
                $slides[$i]['visual'] = $limpo;
                $n++;
            }
        }
        foreach ($slides as $i => $s) {
            if (empty($s['visual'])) {
                $slides[$i]['visual'] = ['tipo' => $i === count($slides) - 1 ? 'resumo' : 'lista', 'pose' => $i === count($slides) - 1 ? 'comemora' : 'explica', 'balao' => '', 'itens' => $s['topicos'] ?? []];
            }
        }
        $rot['slides'] = $slides;
        require_once __DIR__ . '/texto-limpo-lib.php';
        array_walk_recursive($rot, static function (&$x): void { if (is_string($x)) { $x = gab_sem_latex($x); } });
        $pdo->prepare('UPDATE aula_videoaulas SET roteiro = :r WHERE aula_id = :a')->execute([':r' => json_encode($rot, JSON_UNESCAPED_UNICODE), ':a' => $aulaId]);
        return $n;
    }

    /** Põe na fila o visual novo das videoaulas prontas que ainda não têm. */
    function videoaula_visual_varrer(PDO $pdo, int $limite = 40): int
    {
        require_once __DIR__ . '/ia-fila-lib.php';
        $ids = $pdo->query("SELECT v.aula_id FROM aula_videoaulas v LEFT JOIN ia_fila f ON f.tipo = 'visual' AND f.aula_id = v.aula_id
                             WHERE v.status = 'pronto' AND v.roteiro NOT LIKE '%\"visual\"%' AND (f.id IS NULL OR f.status = 'feito') LIMIT " . (int)$limite)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            ia_fila_adicionar($pdo, 'visual', (int)$id);
        }
        return count($ids);
    }

    /** Confere e limpa o JSON da IA (nada vai para a tela sem passar aqui). */
    function videoaula_validar($d): array
    {
        $slides = [];
        foreach ((array)($d['slides'] ?? []) as $s) {
            $titulo = trim((string)($s['titulo'] ?? ''));
            $narr = trim((string)($s['narracao'] ?? ''));
            if ($titulo === '' || $narr === '') {
                continue;
            }
            $top = [];
            foreach (array_slice((array)($s['topicos'] ?? []), 0, 5) as $t) {
                if (trim((string)$t) !== '') {
                    $top[] = mb_substr(trim((string)$t), 0, 160);
                }
            }
            $slide = ['emoji' => mb_substr(trim((string)($s['emoji'] ?? '📌')), 0, 4) ?: '📌', 'titulo' => mb_substr($titulo, 0, 120), 'topicos' => $top,
                'destaque' => mb_substr(trim((string)($s['destaque'] ?? '')), 0, 240), 'narracao' => mb_substr($narr, 0, 2500)];
            if ($v = videoaula_visual_limpo($s['visual'] ?? null)) {
                $slide['visual'] = $v;
            }
            $slides[] = $slide;
        }
        if (count($slides) < 3) {
            throw new RuntimeException('A IA não montou slides suficientes. Tente de novo.');
        }
        $slides = array_slice($slides, 0, 16);
        $paradas = [];
        foreach ((array)($d['paradas'] ?? []) as $p) {
            $alt = array_values(array_filter(array_map(static fn($a) => mb_substr(trim((string)$a), 0, 300), (array)($p['alternativas'] ?? [])), 'strlen'));
            $c = (int)($p['correta'] ?? -1);
            $apos = max(1, min(count($slides) - 1, (int)($p['apos_slide'] ?? 0)));
            if (trim((string)($p['pergunta'] ?? '')) === '' || count($alt) < 2 || !isset($alt[$c])) {
                continue;
            }
            $paradas[] = ['apos_slide' => $apos, 'pergunta' => mb_substr(trim((string)$p['pergunta']), 0, 600), 'alternativas' => array_slice($alt, 0, 5),
                'correta' => $c, 'explicacao' => mb_substr(trim((string)($p['explicacao'] ?? '')), 0, 600)];
        }
        return ['slides' => $slides, 'paradas' => array_slice($paradas, 0, 5)];
    }

    function videoaula_linha(PDO $pdo, int $aulaId): ?array
    {
        $st = $pdo->prepare('SELECT * FROM aula_videoaulas WHERE aula_id = :a');
        $st->execute([':a' => $aulaId]);
        return $st->fetch() ?: null;
    }

    /** Passo 2: narração do slide $n (uma voz: o Antigão). */
    function videoaula_parte(PDO $pdo, int $aulaId, int $n): array
    {
        $pdo = podcast_reconectar($pdo);
        videoaula_bootstrap($pdo);
        $v = videoaula_linha($pdo, $aulaId);
        if (!$v || !$v['roteiro']) {
            throw new RuntimeException('Roteiro da videoaula não encontrado. Comece de novo.');
        }
        if ($v['status'] === 'pronto') {
            return videoaula_status($pdo, $aulaId);
        }
        $roteiro = json_decode((string)$v['roteiro'], true);
        $slide = $roteiro['slides'][$n] ?? null;
        if (!$slide) {
            throw new RuntimeException('Slide inválido.');
        }
        $audios = json_decode((string)($v['audios'] ?: '[]'), true) ?: [];
        if (empty($audios[$n])) {
            $resp = podcast_gemini(PODCAST_MODELO_VOZ, [
                'contents' => [['parts' => [['text' => 'Say slowly, calmly and clearly, like a patient teacher: ' . $slide['narracao']]]]],
                'generationConfig' => ['responseModalities' => ['AUDIO'], 'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => PODCAST_VOZ_SARGENTO]]]],
            ], 85);
            $pdo = podcast_reconectar($pdo);
            $pcm = '';
            foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $parte) {
                $pcm .= (string)base64_decode((string)($parte['inlineData']['data'] ?? ''), true);
            }
            if (strlen($pcm) < 1000) {
                throw new RuntimeException('A narração deste slide veio vazia. Tente de novo.');
            }
            $audios[$n] = videoaula_salvar_audio($aulaId, $n, $pcm);
            $v = videoaula_linha($pdo, $aulaId);
            $audios = (json_decode((string)($v['audios'] ?: '[]'), true) ?: []) + [$n => $audios[$n]];
        }
        $ok = count(array_filter($audios));
        $pronto = $ok >= count($roteiro['slides']);
        $pdo->prepare('UPDATE aula_videoaulas SET audios = :au, partes_ok = :ok, status = :s WHERE aula_id = :a')
            ->execute([':au' => json_encode($audios), ':ok' => $ok, ':s' => $pronto ? 'pronto' : 'gerando', ':a' => $aulaId]);
        return $pronto ? videoaula_status($pdo, $aulaId) : ['status' => 'gerando', 'partes' => count($roteiro['slides']), 'partes_ok' => $ok];
    }

    function videoaula_salvar_audio(int $aulaId, int $n, string $pcm): string
    {
        $cab = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, PODCAST_TAXA, PODCAST_TAXA * 2, 2, 16) . 'data' . pack('V', strlen($pcm));
        $nome = 'aula-' . $aulaId . '-' . substr(sha1($aulaId . '-' . $n . microtime()), 0, 8) . '-' . $n . '.wav';
        file_put_contents(videoaula_pasta() . '/' . $nome, $cab . $pcm);
        $url = 'uploads/videoaulas/' . $nome;
        if (function_exists('exec') && @exec('command -v ffmpeg') !== '') {
            $mp3 = substr($nome, 0, -4) . '.mp3';
            @exec('ffmpeg -loglevel error -y -i ' . escapeshellarg(videoaula_pasta() . '/' . $nome) . ' -b:a 64k ' . escapeshellarg(videoaula_pasta() . '/' . $mp3), $o, $rc);
            if ($rc === 0 && is_file(videoaula_pasta() . '/' . $mp3)) {
                @unlink(videoaula_pasta() . '/' . $nome);
                $url = 'uploads/videoaulas/' . $mp3;
            }
        }
        return $url;
    }

    /** Situação para a tela. Pronta: devolve slides, áudios e paradas (sem a resposta certa). */
    function videoaula_status(PDO $pdo, int $aulaId): array
    {
        videoaula_bootstrap($pdo);
        $v = videoaula_linha($pdo, $aulaId);
        if (!$v) {
            return ['status' => 'nenhum'];
        }
        $r = ['status' => $v['status'], 'partes' => (int)$v['partes'], 'partes_ok' => (int)$v['partes_ok'], 'erro' => (string)($v['erro'] ?? '')];
        try {
            $mt = $pdo->prepare('SELECT m.nome FROM admin_curso_aulas a LEFT JOIN admin_materias m ON m.id = a.materia_id WHERE a.id = :a');
            $mt->execute([':a' => $aulaId]);
            $r['materia'] = (string)$mt->fetchColumn();
        } catch (Throwable $e) {
            $r['materia'] = '';
        }
        if ($v['status'] === 'pronto') {
            $roteiro = json_decode((string)$v['roteiro'], true);
            $audios = json_decode((string)$v['audios'], true) ?: [];
            foreach ($roteiro['slides'] as $i => &$s) {
                $s['audio'] = (string)($audios[$i] ?? '');
                unset($s['narracao']);
            }
            unset($s);
            foreach ($roteiro['paradas'] as &$p) {
                unset($p['correta'], $p['explicacao']);
            }
            unset($p);
            $r += $roteiro;
        }
        return $r;
    }

    /** Resposta de uma parada: corrige, guarda (conta no Ranking Individual) e devolve a explicação. */
    function videoaula_responder(PDO $pdo, int $usuarioId, int $aulaId, int $parada, int $alternativa): array
    {
        videoaula_bootstrap($pdo);
        $v = videoaula_linha($pdo, $aulaId);
        $roteiro = $v ? json_decode((string)$v['roteiro'], true) : null;
        $p = $roteiro['paradas'][$parada] ?? null;
        if (!$p) {
            throw new RuntimeException('Pergunta não encontrada.');
        }
        $certa = $alternativa === (int)$p['correta'];
        // Só a primeira tentativa conta (vale o que o aluno sabia de verdade).
        $pdo->prepare('INSERT IGNORE INTO aula_videoaula_respostas (usuario_id, aula_id, parada, correta) VALUES (:u, :a, :p, :c)')
            ->execute([':u' => $usuarioId, ':a' => $aulaId, ':p' => $parada, ':c' => $certa ? 1 : 0]);
        return ['correta' => $certa, 'certa' => (int)$p['correta'], 'explicacao' => (string)$p['explicacao']];
    }
}
