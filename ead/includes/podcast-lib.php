<?php
// includes/podcast-lib.php
// Podcast de cada aula com o Gemini (plano gratuito da API do Google).
//
// Fluxo, em passos curtos para não estourar o tempo limite da hospedagem:
//   1) iniciar: o Gemini escreve o roteiro (Sargento x Ana) a partir do
//      material da aula e o divide em blocos.
//   2) parte n: o Gemini transforma cada bloco em áudio (2 vozes).
//   3) na última parte, os blocos são juntados em um arquivo de áudio,
//      guardado em uploads/podcasts/. Todo aluno depois só ouve.
// Chave: GEMINI_API_KEY no .env da pasta ead.
declare(strict_types=1);

require_once __DIR__ . '/../api/conexao.php';

// Modelos em ordem de preferência. O primeiro que existir na conta é usado.
// Dá para fixar outro no .env: GEMINI_MODELO_TEXTO=... / GEMINI_MODELO_VOZ=...
const PODCAST_MODELOS_TEXTO = ['gemini-flash-lite-latest', 'gemini-2.5-flash-lite', 'gemini-flash-latest', 'gemini-2.5-flash'];
const PODCAST_MODELOS_VOZ = ['gemini-3.8-flash-tts', 'gemini-2.5-flash-tts', 'gemini-2.5-flash-preview-tts'];
const PODCAST_MODELO_TEXTO = 'texto';
const PODCAST_MODELO_VOZ = 'voz';
const PODCAST_VOZ_SARGENTO = 'Charon';
const PODCAST_VOZ_ANA = 'Kore';
const PODCAST_TAXA = 24000; // PCM 16 bits, mono, 24 kHz

if (!function_exists('podcast_bootstrap')) {
    function podcast_bootstrap(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS aula_podcasts (
            aula_id INT UNSIGNED NOT NULL PRIMARY KEY,
            status ENUM('gerando','pronto','erro') NOT NULL DEFAULT 'gerando',
            roteiro MEDIUMTEXT NULL,
            partes INT UNSIGNED NOT NULL DEFAULT 0,
            partes_ok INT UNSIGNED NOT NULL DEFAULT 0,
            arquivo VARCHAR(255) NULL,
            duracao_seg INT UNSIGNED NULL,
            erro VARCHAR(500) NULL,
            atualizado_em DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('podcast_reconectar')) {
    /**
     * Depois de uma chamada longa ao Gemini, a hospedagem pode ter fechado a
     * conexão parada com o banco ("MySQL server has gone away"). Testa e,
     * se caiu, abre uma conexão nova.
     */
    function podcast_reconectar(PDO $pdo): PDO
    {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (Throwable $e) {
            $cfg = gab_db_config();
            return gab_pdo_connect((string)$cfg['host'], (string)$cfg['database']);
        }
    }
}

if (!function_exists('podcast_pasta')) {
    function podcast_pasta(): string
    {
        $dir = dirname(__DIR__) . '/uploads/podcasts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('podcast_gemini')) {
    /** Chamada à API do Gemini. Base trocável por GEMINI_BASE_URL (testes). */
    function podcast_gemini(string $tipo, array $corpo, int $timeout = 55): array
    {
        $fixo = gab_env($tipo === PODCAST_MODELO_VOZ ? 'GEMINI_MODELO_VOZ' : 'GEMINI_MODELO_TEXTO', '');
        if ($fixo !== '') {
            $lista = [$fixo];
        } else {
            // Primeiro os modelos que a própria conta diz ter; depois a lista padrão.
            $disp = podcast_modelos_disponiveis();
            $lista = array_values(array_unique(array_merge($tipo === PODCAST_MODELO_VOZ ? $disp['voz'] : $disp['texto'], $tipo === PODCAST_MODELO_VOZ ? PODCAST_MODELOS_VOZ : PODCAST_MODELOS_TEXTO)));
        }
        $ultimoErro = null;
        foreach ($lista as $modelo) {
            for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
                try {
                    return podcast_gemini_modelo($modelo, $corpo, $timeout);
                } catch (RuntimeException $e) {
                    $ultimoErro = $e;
                    $m = $e->getMessage();
                    $temporario = (bool)preg_match('~\((500|502|503|504)\)|high demand|overloaded|unavailable~i', $m);
                    if ($temporario && $tentativa === 1) {
                        sleep(3); // congestionamento no Google: espera e tenta o mesmo modelo de novo
                        continue;
                    }
                    $trocaModelo = $temporario
                        || strpos($m, '(404)') !== false
                        || strpos($m, '(429)') !== false || stripos($m, 'Limite diário') !== false
                        || (strpos($m, '(400)') !== false && stripos($m, 'model') !== false && stripos($m, 'speech_metadata') === false);
                    if (!$trocaModelo) {
                        throw $e;
                    }
                    break; // próximo modelo da lista
                }
            }
        }
        throw $ultimoErro ?? new RuntimeException('Nenhum modelo do Gemini disponível.');
    }

    /**
     * Pergunta ao Google quais modelos esta chave pode usar e separa em
     * texto e voz (TTS), do mais novo para o mais antigo. Cache de 6 horas.
     */
    function podcast_modelos_disponiveis(bool $forcar = false): array
    {
        $cache = podcast_pasta() . '/.modelos.json';
        if (!$forcar && is_file($cache) && filemtime($cache) > time() - 21600) {
            $d = json_decode((string)file_get_contents($cache), true);
            if (is_array($d) && isset($d['texto'], $d['voz'])) {
                return $d;
            }
        }
        $res = ['texto' => [], 'voz' => [], 'erro' => ''];
        $chave = gab_env('GEMINI_API_KEY', '');
        if ($chave === '') {
            $res['erro'] = 'Chave não configurada.';
            return $res;
        }
        $base = rtrim(gab_env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'), '/');
        $ch = curl_init($base . '/v1beta/models?pageSize=200');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $chave]]);
        $resp = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($codigo !== 200 || !is_array($json)) {
            $res['erro'] = 'Não foi possível listar os modelos (' . $codigo . '): ' . mb_substr((string)($json['error']['message'] ?? 'sem resposta'), 0, 200);
            return $res;
        }
        foreach ((array)($json['models'] ?? []) as $m) {
            $nome = preg_replace('~^models/~', '', (string)($m['name'] ?? ''));
            $metodos = (array)($m['supportedGenerationMethods'] ?? []);
            if ($nome === '' || !in_array('generateContent', $metodos, true)) {
                continue;
            }
            if (strpos($nome, 'tts') !== false) {
                $res['voz'][] = $nome;
            } elseif (strpos($nome, 'flash') !== false && !preg_match('~image|live|audio|embedding|thinking|exp~', $nome)) {
                $res['texto'][] = $nome;
            }
        }
        // Mais novo primeiro (ordena pelo número da versão; "flash" antes de "pro" por ser mais rápido).
        $ordem = static function (array $l): array {
            usort($l, static function (string $a, string $b): int {
                preg_match('~(\d+(?:\.\d+)?)~', $a, $ma);
                preg_match('~(\d+(?:\.\d+)?)~', $b, $mb);
                $c = (float)($mb[1] ?? 0) <=> (float)($ma[1] ?? 0);
                return $c !== 0 ? $c : (strpos($a, 'preview') !== false) <=> (strpos($b, 'preview') !== false);
            });
            return $l;
        };
        $res['texto'] = $ordem($res['texto']);
        // Para o roteiro, os modelos "lite" (mais rápidos) vêm primeiro.
        usort($res['texto'], static fn(string $a, string $b): int => (strpos($b, 'lite') !== false) <=> (strpos($a, 'lite') !== false));
        $res['voz'] = $ordem($res['voz']);
        @file_put_contents($cache, json_encode($res));
        return $res;
    }

    function podcast_gemini_modelo(string $modelo, array $corpo, int $timeout = 55): array
    {
        // Desliga/reduz o "raciocínio" (deixa a resposta muito mais rápida).
        if (empty($corpo['generationConfig']['responseModalities'])) {
            $comPensamento = $corpo;
            $corpo['generationConfig']['thinkingConfig'] = preg_match('~2\.5~', $modelo) ? ['thinkingBudget' => 0] : ['thinkingLevel' => 'low'];
            try {
                return podcast_gemini_chamar($modelo, $corpo, $timeout);
            } catch (RuntimeException $e) {
                if (strpos($e->getMessage(), '(400)') === false || stripos($e->getMessage(), 'think') === false) {
                    throw $e;
                }
                return podcast_gemini_chamar($modelo, $comPensamento, $timeout);
            }
        }
        return podcast_gemini_chamar($modelo, $corpo, $timeout);
    }

    function podcast_gemini_chamar(string $modelo, array $corpo, int $timeout = 55): array
    {
        $chave = gab_env('GEMINI_API_KEY', '');
        if ($chave === '') {
            throw new RuntimeException('A chave do Gemini não está configurada (GEMINI_API_KEY no .env).');
        }
        $base = rtrim(gab_env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'), '/');
        $ch = curl_init($base . '/v1beta/models/' . $modelo . ':generateContent');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $chave],
            CURLOPT_POSTFIELDS => json_encode($corpo, JSON_UNESCAPED_UNICODE),
        ]);
        $resp = curl_exec($ch);
        $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new RuntimeException('Sem resposta do Gemini: ' . $erroCurl);
        }
        $json = json_decode((string)$resp, true);
        if ($codigo === 429) {
            throw new RuntimeException('Limite diário gratuito do Gemini atingido. Tente de novo mais tarde.');
        }
        if ($codigo >= 400 || !is_array($json)) {
            $msg = is_array($json) ? (string)($json['error']['message'] ?? 'erro desconhecido') : 'resposta inválida';
            throw new RuntimeException('Gemini recusou (' . $codigo . '): ' . mb_substr($msg, 0, 200));
        }
        return $json;
    }
}

if (!function_exists('podcast_material_da_aula')) {
    function podcast_material_da_aula(PDO $pdo, int $aulaId): array
    {
        $stmt = $pdo->prepare('SELECT a.id, a.titulo, a.descricao, a.pdf_arquivo, m.nome AS materia, c.nome AS curso
                                 FROM admin_curso_aulas a
                                 LEFT JOIN admin_materias m ON m.id = a.materia_id
                                 LEFT JOIN admin_cursos c ON c.id = a.curso_id
                                WHERE a.id = :id');
        $stmt->execute([':id' => $aulaId]);
        $aula = $stmt->fetch();
        if (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        }
        $texto = trim(html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '</h2>', '</h3>', '</h4>', '</tr>'], "\n", (string)$aula['descricao'])), ENT_QUOTES, 'UTF-8'));
        // PDF da aula: o Gemini lê o PDF direto (aulas só com PDF ou com pouca descrição).
        // Só manda o PDF quando a descrição não basta: o PDF deixa a resposta bem mais lenta.
        $aula['pdf_base64'] = '';
        $pdf = trim((string)($aula['pdf_arquivo'] ?? ''));
        if (mb_strlen($texto) < 200 && $pdf !== '' && !preg_match('~^https?://~i', $pdf)) {
            $caminho = realpath(dirname(__DIR__) . '/' . ltrim(preg_replace('~^/?ead/~', '', $pdf), '/'));
            $raiz = realpath(dirname(__DIR__) . '/uploads');
            if ($caminho && $raiz && strpos($caminho, $raiz) === 0 && is_file($caminho) && filesize($caminho) <= 8 * 1024 * 1024) {
                $aula['pdf_base64'] = base64_encode((string)file_get_contents($caminho));
            }
        }
        if (mb_strlen($texto) < 200 && $aula['pdf_base64'] === '') {
            throw new RuntimeException('Esta aula ainda não tem material (descrição ou PDF) para gerar o podcast.');
        }
        $aula['texto'] = mb_strlen($texto) >= 200 ? mb_substr($texto, 0, 12000) : '(sem descrição: use o PDF anexo como material da aula)';
        return $aula;
    }
}

if (!function_exists('podcast_iniciar')) {
    /** Passo 1: roteiro. Retorna o número de partes de áudio. */
    function podcast_iniciar(PDO $pdo, int $aulaId): array
    {
        $pdo = podcast_reconectar($pdo);
        podcast_bootstrap($pdo);
        $stmt = $pdo->prepare('SELECT * FROM aula_podcasts WHERE aula_id = :a');
        $stmt->execute([':a' => $aulaId]);
        $atual = $stmt->fetch();
        if ($atual && $atual['status'] === 'pronto' && $atual['arquivo']) {
            return ['status' => 'pronto'];
        }
        // Outro aluno já está gerando há menos de 5 minutos: não duplica.
        if ($atual && $atual['status'] === 'gerando' && $atual['roteiro'] && strtotime((string)$atual['atualizado_em']) > time() - 300) {
            return ['status' => 'gerando', 'partes' => (int)$atual['partes'], 'partes_ok' => (int)$atual['partes_ok']];
        }

        $aula = podcast_material_da_aula($pdo, $aulaId);
        $prompt = "Você é roteirista do podcast do Projeto Gabaritando, cursinho para carreiras policiais e militares da Bahia.\n"
            . "Escreva um podcast de 4 a 6 minutos sobre a aula abaixo, em português do Brasil, como uma CONVERSA DE VERDADE entre dois personagens:\n\n"
            . "PERSONAGENS\n"
            . "- Sargento = o ANTIGÃO: a IA professora do Projeto Gabaritando. Veterano da tropa, já viu muita prova, calmo, bem-humorado, fala simples e direto, usa comparações do dia a dia do quartel e da rua. Na conversa é chamado de \"Antigão\".\n"
            . "- Ana = o RECRUTA ZERO UM: aluno que vai prestar o concurso, esperto mas inseguro. Pergunta o que todo aluno pergunta, chuta errado de vez em quando, fica surpreso, comemora quando entende. Na conversa é chamado de \"Recruta Zero Um\" ou só \"Zero Um\". Sempre escreva \"Zero Um\" por extenso.\n\n"
            . "COMO DEVE SOAR (o mais importante)\n"
            . "- Conversa de verdade: um interrompe o outro, reage (\"Peraí, Antigão!\", \"Isso!\", \"Ih, eu ia errar essa\"), faz pergunta de volta. Nada de um fazer palestra e o outro só dizer \"entendi\".\n"
            . "- Cada fala tem no máximo 3 frases curtas. Nada de listas lidas em voz alta, nada de \"em primeiro lugar, em segundo lugar\".\n"
            . "- Ensine com EXEMPLOS concretos: frases, situações e questões no estilo da banca. A cada conceito, um exemplo.\n"
            . "- Pelo menos duas vezes o Zero Um erra uma pegadinha e o Antigão explica o porquê, com um macete fácil de lembrar.\n"
            . "- Proibido: \"ótima pergunta\", \"com certeza\", \"exatamente\" repetidos, frases de efeito vazias, repetir a mesma ideia.\n\n"
            . "ROTEIRO\n"
            . "1. Abertura curta: \"Fala, combatente! Aqui é o Antigão, no podcast do Projeto Gabaritando. E hoje tô aqui com o Recruta Zero Um.\" e, em uma frase, por que esse assunto cai na prova.\n"
            . "2. Desenvolvimento: do mais simples ao mais difícil, só com o conteúdo do material.\n"
            . "3. Desafio rápido: o Antigão faz duas perguntas ao Zero Um, que responde (uma certa, uma errada e corrigida).\n"
            . "4. Fechamento: resumo em uma frase, a missão (resolver pelo menos 5 questões do assunto) e \"Fé na missão: a farda é questão de tempo!\".\n\n"
            . "REGRAS\n"
            . "- Use somente o conteúdo do material (texto e PDF anexo). Não invente datas, números, nomes nem leis.\n"
            . "- Números, datas, siglas e artigos de lei por extenso, como são falados (\"artigo quinto\", \"mil novecentos e oitenta e oito\").\n"
            . "- Sempre \"Projeto Gabaritando\" por extenso. Quem ouve é \"combatente\".\n"
            . "- Entre 24 e 32 falas no total.\n\n"
            . "Responda SOMENTE em JSON: {\"falas\":[{\"quem\":\"Sargento\"|\"Ana\",\"texto\":\"...\"}]}\n\n"
            . "CURSO: " . $aula['curso'] . "\nMATÉRIA: " . $aula['materia'] . "\nAULA: " . $aula['titulo'] . "\n\nMATERIAL DA AULA:\n" . $aula['texto'];

        $partes = [['text' => $prompt]];
        if ($aula['pdf_base64'] !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $aula['pdf_base64']]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, [
            'contents' => [['parts' => $partes]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.85],
        ], 110);
        $pdo = podcast_reconectar($pdo);
        $texto = (string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $dados = json_decode($texto, true);
        $falas = [];
        foreach ((array)($dados['falas'] ?? []) as $f) {
            $quem = ($f['quem'] ?? '') === 'Ana' ? 'Ana' : 'Sargento';
            $t = trim((string)($f['texto'] ?? ''));
            if ($t !== '') {
                $falas[] = ['quem' => $quem, 'texto' => $t];
            }
        }
        if (count($falas) < 6) {
            throw new RuntimeException('O roteiro veio incompleto. Tente de novo.');
        }
        // Blocos de ~8 falas (cerca de 1 minuto de áudio cada).
        $blocos = array_chunk($falas, 8);
        $pdo->prepare("INSERT INTO aula_podcasts (aula_id, status, roteiro, partes, partes_ok, arquivo, erro) VALUES (:a, 'gerando', :r, :p, 0, NULL, NULL)
                       ON DUPLICATE KEY UPDATE status = 'gerando', roteiro = VALUES(roteiro), partes = VALUES(partes), partes_ok = 0, arquivo = NULL, erro = NULL")
            ->execute([':a' => $aulaId, ':r' => json_encode($blocos, JSON_UNESCAPED_UNICODE), ':p' => count($blocos)]);
        foreach (glob(podcast_pasta() . '/aula-' . $aulaId . '-parte-*.pcm') ?: [] as $velho) {
            @unlink($velho);
        }
        return ['status' => 'gerando', 'partes' => count($blocos), 'partes_ok' => 0];
    }
}

if (!function_exists('podcast_parte')) {
    /** Passo 2: áudio do bloco $n (0..partes-1). Na última parte junta tudo. */
    function podcast_parte(PDO $pdo, int $aulaId, int $n): array
    {
        $pdo = podcast_reconectar($pdo);
        podcast_bootstrap($pdo);
        $stmt = $pdo->prepare('SELECT * FROM aula_podcasts WHERE aula_id = :a');
        $stmt->execute([':a' => $aulaId]);
        $pod = $stmt->fetch();
        if (!$pod || !$pod['roteiro']) {
            throw new RuntimeException('Roteiro não encontrado. Comece de novo.');
        }
        if ($pod['status'] === 'pronto') {
            return ['status' => 'pronto', 'url' => (string)$pod['arquivo']];
        }
        $blocos = json_decode((string)$pod['roteiro'], true);
        if (!isset($blocos[$n])) {
            throw new RuntimeException('Parte inválida.');
        }
        $arqParte = podcast_pasta() . '/aula-' . $aulaId . '-parte-' . $n . '.pcm';
        if (!is_file($arqParte)) {
            $pcm = podcast_tts($blocos[$n]);
            $pdo = podcast_reconectar($pdo);
            if (strlen($pcm) < 1000) {
                throw new RuntimeException('O áudio desta parte veio vazio. Tente de novo.');
            }
            file_put_contents($arqParte, $pcm);
        }
        $ok = count(glob(podcast_pasta() . '/aula-' . $aulaId . '-parte-*.pcm') ?: []);
        $pdo->prepare('UPDATE aula_podcasts SET partes_ok = :ok WHERE aula_id = :a')->execute([':ok' => $ok, ':a' => $aulaId]);

        if ($ok < count($blocos)) {
            return ['status' => 'gerando', 'partes' => count($blocos), 'partes_ok' => $ok];
        }
        return podcast_juntar($pdo, $aulaId, count($blocos));
    }
}

if (!function_exists('podcast_tts')) {
    /**
     * Áudio (PCM 24 kHz) de uma lista de falas [{quem, texto}] com 2 vozes.
     * Formato antigo: um texto "Sargento: ...\nAna: ...".
     * Formato novo (modelos mais recentes): uma parte por fala com speechMetadata.speaker.
     */
    function podcast_tts(array $falas): string
    {
        $config = [
            'responseModalities' => ['AUDIO'],
            'speechConfig' => ['multiSpeakerVoiceConfig' => ['speakerVoiceConfigs' => [
                ['speaker' => 'Sargento', 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => PODCAST_VOZ_SARGENTO]]],
                ['speaker' => 'Ana', 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => PODCAST_VOZ_ANA]]],
            ]]],
        ];
        $dialogo = "Leia em português do Brasil, como uma conversa descontraída e natural de podcast do Projeto Gabaritando: Sargento é o Antigão, veterano calmo e bem-humorado; Ana é o Recruta Zero Um, jovem, curioso e animado. Ritmo de conversa real, com reações e pausas naturais:\n";
        $partesNovas = [];
        foreach ($falas as $f) {
            $dialogo .= $f['quem'] . ': ' . $f['texto'] . "\n";
            $partesNovas[] = ['text' => $f['texto'], 'speechMetadata' => ['speaker' => $f['quem']]];
        }
        try {
            $resp = podcast_gemini(PODCAST_MODELO_VOZ, ['contents' => [['parts' => [['text' => $dialogo]]]], 'generationConfig' => $config], 85);
        } catch (RuntimeException $e) {
            if (stripos($e->getMessage(), 'speech_metadata') === false && stripos($e->getMessage(), 'speechMetadata') === false) {
                throw $e;
            }
            $resp = podcast_gemini(PODCAST_MODELO_VOZ, ['contents' => [['role' => 'user', 'parts' => $partesNovas]], 'generationConfig' => $config], 85);
        }
        $pcm = '';
        foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $parte) {
            $pcm .= (string)base64_decode((string)($parte['inlineData']['data'] ?? ''), true);
        }
        return $pcm;
    }
}

if (!function_exists('podcast_juntar')) {
    /** Passo 3: junta as partes em um WAV (com pausa curta entre blocos). */
    function podcast_juntar(PDO $pdo, int $aulaId, int $partes): array
    {
        $pausa = str_repeat("\0\0", (int)(PODCAST_TAXA * 0.4));
        $pcm = '';
        for ($i = 0; $i < $partes; $i++) {
            $pcm .= (string)file_get_contents(podcast_pasta() . '/aula-' . $aulaId . '-parte-' . $i . '.pcm') . $pausa;
        }
        $cab = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, PODCAST_TAXA, PODCAST_TAXA * 2, 2, 16) . 'data' . pack('V', strlen($pcm));
        $nome = 'aula-' . $aulaId . '-' . substr(sha1((string)microtime(true)), 0, 8) . '.wav';
        file_put_contents(podcast_pasta() . '/' . $nome, $cab . $pcm);
        $url = 'uploads/podcasts/' . $nome;
        $duracao = (int)round(strlen($pcm) / (PODCAST_TAXA * 2));

        // Se o servidor tiver ffmpeg, converte para MP3 (arquivo ~10x menor).
        if (function_exists('exec') && @exec('command -v ffmpeg') !== '') {
            $mp3 = substr($nome, 0, -4) . '.mp3';
            @exec('ffmpeg -loglevel error -y -i ' . escapeshellarg(podcast_pasta() . '/' . $nome) . ' -b:a 64k ' . escapeshellarg(podcast_pasta() . '/' . $mp3), $o, $rc);
            if ($rc === 0 && is_file(podcast_pasta() . '/' . $mp3)) {
                @unlink(podcast_pasta() . '/' . $nome);
                $url = 'uploads/podcasts/' . $mp3;
            }
        }
        foreach (glob(podcast_pasta() . '/aula-' . $aulaId . '-parte-*.pcm') ?: [] as $p) {
            @unlink($p);
        }
        $pdo->prepare("UPDATE aula_podcasts SET status = 'pronto', arquivo = :f, duracao_seg = :d, erro = NULL WHERE aula_id = :a")
            ->execute([':f' => $url, ':d' => $duracao, ':a' => $aulaId]);
        return ['status' => 'pronto', 'url' => $url];
    }
}

if (!function_exists('podcast_status')) {
    function podcast_status(PDO $pdo, int $aulaId): array
    {
        podcast_bootstrap($pdo);
        $stmt = $pdo->prepare('SELECT status, partes, partes_ok, arquivo, erro FROM aula_podcasts WHERE aula_id = :a');
        $stmt->execute([':a' => $aulaId]);
        $p = $stmt->fetch();
        if (!$p) {
            return ['status' => 'nenhum'];
        }
        return ['status' => $p['status'], 'partes' => (int)$p['partes'], 'partes_ok' => (int)$p['partes_ok'], 'url' => (string)($p['arquivo'] ?? ''), 'erro' => (string)($p['erro'] ?? '')];
    }
}
