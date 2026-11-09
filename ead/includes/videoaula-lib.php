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

        $aula = videoaula_material($pdo, $aulaId);
        $prompt = "Você é professor e designer didático do Projeto Gabaritando, cursinho para carreiras policiais e militares da Bahia.\n"
            . "Monte uma VIDEOAULA CURTA (4 a 7 minutos) sobre a aula abaixo: slides + narração + paradas com pergunta.\n\n"
            . "DIDÁTICA (obrigatória)\n"
            . "- Slide 1: abertura. Narração começa com \"Fala, combatente! Aqui é o Antigão, do Projeto Gabaritando\" (o Antigão é a IA professora: veterano experiente e bem-humorado) e diz o que o aluno vai aprender e por que cai na prova.\n"
            . "- Do 2º ao penúltimo: um conceito por slide, do mais simples ao mais difícil. Título curto, 2 a 4 tópicos de no máximo 12 palavras, e um 'destaque' (macete, pegadinha da banca ou exemplo).\n"
            . "- Último slide: resumo em 3 tópicos e a chamada: resolver pelo menos 5 questões do assunto. Termine a narração com \"Fé na missão: a farda é questão de tempo!\".\n"
            . "- Narração: fala natural de professor, 40 a 90 palavras por slide, sem ler os tópicos palavra por palavra, números e datas por extenso.\n"
            . "- Entre 6 e 10 slides. Depois dos slides 3 e 6 (e do 9, se houver), uma PARADA: pergunta de múltipla escolha estilo banca, 4 alternativas, com explicação curta do porquê.\n"
            . "- Use SOMENTE o conteúdo do material (texto e PDF anexo). Não invente leis, números ou datas.\n"
            . "- Sempre \"Projeto Gabaritando\" por extenso; o aluno é \"combatente\".\n\n"
            . "Responda SOMENTE em JSON:\n"
            . "{\"slides\":[{\"emoji\":\"📌\",\"titulo\":\"...\",\"topicos\":[\"...\"],\"destaque\":\"...\",\"narracao\":\"...\"}],"
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

        foreach (glob(videoaula_pasta() . '/aula-' . $aulaId . '-*') ?: [] as $velho) {
            @unlink($velho);
        }
        $pdo->prepare("INSERT INTO aula_videoaulas (aula_id, status, roteiro, partes, partes_ok, audios, erro) VALUES (:a, 'gerando', :r, :p, 0, '[]', NULL)
                       ON DUPLICATE KEY UPDATE status = 'gerando', roteiro = VALUES(roteiro), partes = VALUES(partes), partes_ok = 0, audios = '[]', erro = NULL")
            ->execute([':a' => $aulaId, ':r' => json_encode($roteiro, JSON_UNESCAPED_UNICODE), ':p' => count($roteiro['slides'])]);
        return ['status' => 'gerando', 'partes' => count($roteiro['slides']), 'partes_ok' => 0];
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
            $slides[] = ['emoji' => mb_substr(trim((string)($s['emoji'] ?? '📌')), 0, 4) ?: '📌', 'titulo' => mb_substr($titulo, 0, 120), 'topicos' => $top,
                'destaque' => mb_substr(trim((string)($s['destaque'] ?? '')), 0, 240), 'narracao' => mb_substr($narr, 0, 1500)];
        }
        if (count($slides) < 3) {
            throw new RuntimeException('A IA não montou slides suficientes. Tente de novo.');
        }
        $slides = array_slice($slides, 0, 12);
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
        return ['slides' => $slides, 'paradas' => array_slice($paradas, 0, 4)];
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
                'contents' => [['parts' => [['text' => 'Narre em português do Brasil, como um professor animado e claro de cursinho policial: ' . $slide['narracao']]]]],
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
