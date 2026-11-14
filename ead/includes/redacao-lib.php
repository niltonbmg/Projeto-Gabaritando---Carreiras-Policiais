<?php
// includes/redacao-lib.php
// Redação:
//   1) Aluno envia (texto, foto ou PDF da folha).
//   2) Antigão (IA) lê em segundo plano e sugere nota por critério. ESCONDIDA do corretor.
//   3) Corretor dá a nota dele nos CRITÉRIOS DO EDITAL do concurso do aluno ("às cegas").
//   4) Depois de enviar a nota dele, vê a sugestão do Antigão lado a lado e decide a nota final.
//   5) Aluno é avisado e vê nota final, comentários e áudio.
// Perfil: usuarios.tipo = 'professor_redacao' (só vê a fila de correção).
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';

const REDACAO_PERFIL = 'professor_redacao';
const REDACAO_PRAZO_HORAS = 72;
// Grade padrão do Projeto Gabaritando, usada só quando o edital não traz critérios.
const REDACAO_GRADE_PADRAO = [
    ['nome' => 'Tema e gênero textual', 'max' => 20],
    ['nome' => 'Estrutura e coesão', 'max' => 20],
    ['nome' => 'Argumentação e repertório', 'max' => 20],
    ['nome' => 'Norma culta da língua', 'max' => 20],
    ['nome' => 'Conclusão e proposta', 'max' => 20],
];

if (!function_exists('redacao_bootstrap')) {
    function redacao_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS redacao_propostas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            titulo VARCHAR(200) NOT NULL,
            tema TEXT NOT NULL,
            texto_apoio MEDIUMTEXT NULL,
            ativa TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS redacoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario_id BIGINT UNSIGNED NOT NULL,
            proposta_id INT UNSIGNED NULL,
            texto MEDIUMTEXT NULL,
            arquivo VARCHAR(255) NULL,
            grade_json TEXT NULL,
            grade_origem VARCHAR(120) NULL,
            status ENUM('enviada','avaliada','corrigida') NOT NULL DEFAULT 'enviada',
            ia_json MEDIUMTEXT NULL,
            ia_nota DECIMAL(6,2) NULL,
            professor_id BIGINT UNSIGNED NULL,
            prof_json TEXT NULL,
            prof_nota DECIMAL(6,2) NULL,
            final_json TEXT NULL,
            nota DECIMAL(6,2) NULL,
            nota_origem VARCHAR(20) NULL,
            comentario MEDIUMTEXT NULL,
            audio VARCHAR(255) NULL,
            visto_aluno TINYINT(1) NOT NULL DEFAULT 0,
            enviada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            avaliada_em DATETIME NULL,
            corrigida_em DATETIME NULL,
            KEY idx_red_status (status, enviada_em),
            KEY idx_red_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS redacao_config (chave VARCHAR(40) PRIMARY KEY, valor VARCHAR(200) NOT NULL) ENGINE=InnoDB");
        $pdo->exec("INSERT IGNORE INTO redacao_config (chave, valor) VALUES ('limite_semana', '1')");
        $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'tipo'")->fetch();
        if ($col && stripos((string)$col['Type'], 'enum(') === 0 && stripos((string)$col['Type'], REDACAO_PERFIL) === false) {
            $pdo->exec("ALTER TABLE usuarios MODIFY tipo ENUM('cliente','admin','" . REDACAO_PERFIL . "') NOT NULL DEFAULT 'cliente'");
        }
        $feito = true;
    }

    function redacao_config(PDO $pdo, string $chave, string $padrao = ''): string
    {
        $st = $pdo->prepare('SELECT valor FROM redacao_config WHERE chave = :c');
        $st->execute([':c' => $chave]);
        $v = $st->fetchColumn();
        return $v === false ? $padrao : (string)$v;
    }

    function redacao_pasta(): string
    {
        $dir = dirname(__DIR__) . '/uploads/redacoes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    function redacao_limite(PDO $pdo, int $usuarioId): array
    {
        $lim = max(0, (int)redacao_config($pdo, 'limite_semana', '1'));
        $st = $pdo->prepare('SELECT COUNT(*) FROM redacoes WHERE usuario_id = :u AND enviada_em >= NOW() - INTERVAL 7 DAY');
        $st->execute([':u' => $usuarioId]);
        $usadas = (int)$st->fetchColumn();
        return ['limite' => $lim, 'usadas' => $usadas, 'pode' => $lim === 0 || $usadas < $lim];
    }

    /**
     * Critérios de correção: os do EDITAL do concurso do aluno (lidos pela IA no cadastro);
     * sem critérios no edital, a grade padrão. Retorna [grade, origem, texto do edital].
     */
    function redacao_grade_do_aluno(PDO $pdo, int $usuarioId): array
    {
        try {
            $st = $pdo->prepare('SELECT c.nome, c.edital_info FROM aluno_concurso ac JOIN concursos c ON c.id = ac.concurso_id WHERE ac.usuario_id = :u');
            $st->execute([':u' => $usuarioId]);
            $c = $st->fetch();
            $info = json_decode((string)($c['edital_info'] ?? ''), true) ?: [];
            $grade = [];
            require_once __DIR__ . '/provas-escritas-lib.php';
            $peAl = pe_do_aluno($pdo, $usuarioId);
            $critCargo = $peAl ? (array)($peAl['redacao']['criterios'] ?? []) : [];
            foreach ($critCargo ?: (array)($info['redacao']['criterios'] ?? []) as $cr) {
                $nome = trim((string)($cr['nome'] ?? ''));
                $max = (float)($cr['max'] ?? 0);
                if ($nome !== '' && $max > 0) {
                    $grade[] = ['nome' => mb_substr($nome, 0, 160), 'max' => $max, 'descricao' => mb_substr(trim((string)($cr['descricao'] ?? '')), 0, 600)];
                }
            }
            if ($grade) {
                return [$grade, 'Critérios do edital · ' . $c['nome'], (string)($info['redacao']['criterio'] ?? '')];
            }
            return [REDACAO_GRADE_PADRAO, 'Grade padrão do Projeto Gabaritando', (string)($info['redacao']['criterio'] ?? '')];
        } catch (Throwable $e) {
            return [REDACAO_GRADE_PADRAO, 'Grade padrão do Projeto Gabaritando', ''];
        }
    }

    function redacao_max_total(array $grade): float
    {
        return (float)array_sum(array_column($grade, 'max'));
    }

    function redacao_enviar(PDO $pdo, int $usuarioId, int $propostaId, string $texto, ?array $arquivo): int
    {
        redacao_bootstrap($pdo);
        if (!redacao_limite($pdo, $usuarioId)['pode']) {
            throw new RuntimeException('Você já enviou a redação desta semana. A próxima libera 7 dias depois do último envio.');
        }
        $texto = trim($texto);
        $caminho = null;
        if ($arquivo && !empty($arquivo['tmp_name']) && is_uploaded_file($arquivo['tmp_name'])) {
            if ((int)$arquivo['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Arquivo acima de 10 MB.');
            }
            $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime] ?? null;
            if (!$ext) {
                throw new RuntimeException('Envie foto (JPG/PNG) ou PDF.');
            }
            $nome = 'red-' . $usuarioId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (!move_uploaded_file($arquivo['tmp_name'], redacao_pasta() . '/' . $nome)) {
                throw new RuntimeException('Não consegui salvar o arquivo.');
            }
            $caminho = 'uploads/redacoes/' . $nome;
        }
        if ($caminho === null && mb_strlen($texto) < 300) {
            throw new RuntimeException('Escreva a redação (pelo menos umas 7 linhas) ou envie a foto/PDF da folha.');
        }
        [$grade, $origem] = redacao_grade_do_aluno($pdo, $usuarioId);
        $pdo->prepare('INSERT INTO redacoes (usuario_id, proposta_id, texto, arquivo, grade_json, grade_origem) VALUES (:u, :p, :t, :a, :g, :o)')
            ->execute([':u' => $usuarioId, ':p' => $propostaId ?: null, ':t' => $texto !== '' ? $texto : null, ':a' => $caminho,
                ':g' => json_encode($grade, JSON_UNESCAPED_UNICODE), ':o' => $origem]);
        return (int)$pdo->lastInsertId();
    }

    function redacao_obter(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT r.*, p.titulo AS proposta, p.tema, u.nome AS aluno FROM redacoes r
                               LEFT JOIN redacao_propostas p ON p.id = r.proposta_id LEFT JOIN usuarios u ON u.id = r.usuario_id WHERE r.id = :id');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $r['grade'] = json_decode((string)$r['grade_json'], true) ?: REDACAO_GRADE_PADRAO;
        foreach (['ia_json' => 'ia', 'prof_json' => 'prof', 'final_json' => 'final'] as $c => $k) {
            $r[$k] = json_decode((string)($r[$c] ?? ''), true);
        }
        return $r;
    }

    /** Leitura do Antigão: transcreve (se for foto/PDF) e sugere nota em cada critério da grade. */
    function redacao_ia_ler(PDO $pdo, int $redacaoId): array
    {
        $r = redacao_obter($pdo, $redacaoId);
        if (!$r) {
            throw new RuntimeException('Redação não encontrada.');
        }
        $grade = array_values($r['grade']);
        $prompt = "Você é o ANTIGÃO, corretor de redação do Projeto Gabaritando (concursos policiais e militares da Bahia).\n"
            . "Leia a redação e SUGIRA uma nota em cada critério abaixo (são os critérios do edital), respeitando a nota máxima de cada um. Seja rigoroso como a banca.\n"
            . "Proposta: " . ($r['proposta'] ?: 'tema livre') . ' — ' . ($r['tema'] ?: '') . "\n"
            . "CRITÉRIOS (índice, nome, máximo, descrição): " . json_encode($grade, JSON_UNESCAPED_UNICODE) . "\n"
            . "Se vier foto/PDF manuscrito, transcreva fielmente primeiro ([ilegível] onde não der para ler).\n"
            . "Responda SOMENTE em JSON: {\"transcricao\":\"\",\"notas\":[{\"indice\":0,\"nota\":0,\"justificativa\":\"1 a 2 frases\"}],\"pontos_fortes\":[\"\"],\"melhorar\":[\"\"]}\n\n"
            . "REDAÇÃO DIGITADA:\n" . mb_substr((string)$r['texto'], 0, 12000);
        $partes = [['text' => $prompt]];
        if (!empty($r['arquivo']) && is_file(dirname(__DIR__) . '/' . $r['arquivo'])) {
            $a = (string)$r['arquivo'];
            $mime = str_ends_with($a, '.pdf') ? 'application/pdf' : (str_ends_with($a, '.png') ? 'image/png' : (str_ends_with($a, '.webp') ? 'image/webp' : 'image/jpeg'));
            $partes[] = ['inlineData' => ['mimeType' => $mime, 'data' => base64_encode((string)file_get_contents(dirname(__DIR__) . '/' . $a))]];
        }
        $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2]], 120);
        $pdo = podcast_reconectar($pdo);
        $j = json_decode((string)preg_replace('~^```(?:json)?|```$~m', '', (string)($resp['candidates'][0]['content']['parts'][0]['text'] ?? '')), true);
        if (!is_array($j) || empty($j['notas'])) {
            throw new RuntimeException('A leitura da IA não veio completa.');
        }
        $notas = [];
        foreach ((array)$j['notas'] as $n) {
            $i = (int)($n['indice'] ?? -1);
            if (isset($grade[$i])) {
                $notas[$i] = ['nota' => max(0, min((float)$grade[$i]['max'], (float)($n['nota'] ?? 0))), 'justificativa' => mb_substr((string)($n['justificativa'] ?? ''), 0, 600)];
            }
        }
        $j['notas'] = $notas;
        $total = array_sum(array_column($notas, 'nota'));
        $pdo->prepare('UPDATE redacoes SET ia_json = :j, ia_nota = :n, texto = COALESCE(texto, :t) WHERE id = :id')
            ->execute([':j' => json_encode($j, JSON_UNESCAPED_UNICODE), ':n' => $total, ':t' => mb_substr((string)($j['transcricao'] ?? ''), 0, 20000) ?: null, ':id' => $redacaoId]);
        return $j;
    }

    /** Lê as notas do formulário dentro da grade (cada uma limitada ao máximo do critério). */
    function redacao_notas_do_form(array $grade, array $post): array
    {
        $r = [];
        foreach (array_values($grade) as $i => $g) {
            $v = str_replace(',', '.', (string)($post[$i]['nota'] ?? ''));
            if ($v === '' || !is_numeric($v)) {
                throw new RuntimeException('Dê a nota em todos os critérios.');
            }
            $r[$i] = ['nota' => max(0, min((float)$g['max'], (float)$v)), 'comentario' => mb_substr(trim((string)($post[$i]['comentario'] ?? '')), 0, 1000)];
        }
        return $r;
    }

    /** Passo 1 do corretor: a nota DELE, sem ver a da IA. */
    function redacao_avaliar(PDO $pdo, int $redacaoId, int $professorId, array $postNotas): void
    {
        $r = redacao_obter($pdo, $redacaoId);
        if (!$r || $r['status'] === 'corrigida') {
            throw new RuntimeException('Redação não disponível para correção.');
        }
        $notas = redacao_notas_do_form($r['grade'], $postNotas);
        $pdo->prepare("UPDATE redacoes SET status = 'avaliada', professor_id = :p, prof_json = :j, prof_nota = :n, avaliada_em = NOW() WHERE id = :id")
            ->execute([':p' => $professorId, ':j' => json_encode($notas, JSON_UNESCAPED_UNICODE), ':n' => array_sum(array_column($notas, 'nota')), ':id' => $redacaoId]);
    }

    /** Passo 2: o corretor decide a nota final (a dele, a da IA ou ajustada) e publica para o aluno. */
    function redacao_publicar(PDO $pdo, int $redacaoId, int $professorId, string $escolha, array $postNotas, string $comentario, ?array $audio): void
    {
        $r = redacao_obter($pdo, $redacaoId);
        if (!$r || $r['status'] !== 'avaliada') {
            throw new RuntimeException('Envie a sua nota primeiro.');
        }
        if ($escolha === 'professor') {
            $final = $r['prof'];
        } elseif ($escolha === 'ia' && !empty($r['ia']['notas'])) {
            $final = [];
            foreach (array_values($r['grade']) as $i => $g) {
                $final[$i] = ['nota' => (float)($r['ia']['notas'][$i]['nota'] ?? 0), 'comentario' => (string)($r['prof'][$i]['comentario'] ?? '')];
            }
        } else {
            $escolha = 'ajustada';
            $final = redacao_notas_do_form($r['grade'], $postNotas);
        }
        $audioUrl = null;
        if ($audio && !empty($audio['tmp_name']) && is_uploaded_file($audio['tmp_name'])) {
            $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($audio['tmp_name']);
            if (str_starts_with($mime, 'audio/') && (int)$audio['size'] <= 15 * 1024 * 1024) {
                $ext = str_contains($mime, 'mpeg') ? 'mp3' : (str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'wav') ? 'wav' : (str_contains($mime, 'webm') ? 'webm' : 'm4a')));
                $nome = 'audio-' . $redacaoId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($audio['tmp_name'], redacao_pasta() . '/' . $nome)) {
                    $audioUrl = 'uploads/redacoes/' . $nome;
                }
            }
        }
        $pdo->prepare("UPDATE redacoes SET status = 'corrigida', final_json = :j, nota = :n, nota_origem = :o, comentario = :c, audio = COALESCE(:a, audio), corrigida_em = NOW(), visto_aluno = 0 WHERE id = :id AND professor_id = :p")
            ->execute([':j' => json_encode($final, JSON_UNESCAPED_UNICODE), ':n' => array_sum(array_column($final, 'nota')), ':o' => $escolha,
                ':c' => trim($comentario), ':a' => $audioUrl, ':id' => $redacaoId, ':p' => $professorId]);
    }

    function redacao_eh_professor(): bool
    {
        $tipo = strtolower((string)($_SESSION['usuario_tipo'] ?? ($_SESSION['admin_tipo'] ?? '')));
        return in_array($tipo, [REDACAO_PERFIL, 'admin'], true) || !empty($_SESSION['admin_usuario_id']);
    }

    function redacao_professor_id(): int
    {
        return (int)($_SESSION['usuario_id'] ?? ($_SESSION['admin_usuario_id'] ?? 0));
    }

    function redacao_fmt(?float $n): string
    {
        return $n === null ? '—' : rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }
}
