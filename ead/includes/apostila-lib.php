<?php
// includes/apostila-lib.php
// Gera a descrição + mini apostila da aula no padrão Projeto Gabaritando, com o
// Gemini, a partir da transcrição do YouTube (ou da descrição atual / do PDF).
// Usa o modelo de prompt em includes/prompts/mini-apostila.txt.
declare(strict_types=1);

require_once __DIR__ . '/podcast-lib.php';
require_once __DIR__ . '/meta-lib.php';
require_once __DIR__ . '/aula-descricao-lib.php';

if (!function_exists('apostila_contexto')) {
    /** Curso, matéria e assunto do módulo/aula, para o prompt. */
    function apostila_contexto(PDO $pdo, int $cursoId, int $moduloId): array
    {
        $stmt = $pdo->prepare('SELECT c.nome AS curso, m.titulo AS modulo, ma.nome AS materia, t.titulo AS assunto
                                 FROM admin_curso_modulos m
                                 JOIN admin_cursos c ON c.id = m.curso_id
                                 LEFT JOIN admin_materias ma ON ma.id = m.materia_id
                                 LEFT JOIN admin_materia_topicos t ON t.id = m.topico_id
                                WHERE m.id = :m AND m.curso_id = :c');
        $stmt->execute([':m' => $moduloId, ':c' => $cursoId]);
        return $stmt->fetch() ?: ['curso' => '', 'modulo' => '', 'materia' => '', 'assunto' => ''];
    }
}

if (!function_exists('apostila_gerar')) {
    /**
     * @param array $dados titulo, transcricao, descricao_atual, professor, pdf_arquivo
     * @return string HTML pronto para a descrição da aula
     */
    function apostila_gerar(PDO $pdo, int $cursoId, int $moduloId, array $dados): string
    {
        $ctx = apostila_contexto($pdo, $cursoId, $moduloId);
        $transcricao = trim((string)($dados['transcricao'] ?? ''));
        $atual = trim(html_entity_decode(strip_tags((string)($dados['descricao_atual'] ?? '')), ENT_QUOTES, 'UTF-8'));
        $video = trim((string)($dados['video_url'] ?? ''));
        $usarVideo = $transcricao === '' && preg_match('~(youtube\.com/(watch|live|shorts)|youtu\.be/)~i', $video);
        // Ordem: transcrição colada > o próprio vídeo do YouTube (o Gemini assiste) > descrição atual > PDF.
        $fonte = $transcricao !== '' ? $transcricao : ($usarVideo ? '(Use o vídeo do YouTube anexo como transcrição da aula. A descrição antiga abaixo é só apoio.)' . "\n\n" . $atual : $atual);

        // Sem transcrição, vídeo nem descrição: usa o PDF da aula, se houver.
        $pdfBase64 = '';
        if (!$usarVideo && mb_strlen($fonte) < 200) {
            $pdf = trim((string)($dados['pdf_arquivo'] ?? ''));
            if ($pdf !== '' && !preg_match('~^https?://~i', $pdf)) {
                $caminho = realpath(dirname(__DIR__) . '/' . ltrim(preg_replace('~^/?ead/~', '', $pdf), '/'));
                $raiz = realpath(dirname(__DIR__) . '/uploads');
                if ($caminho && $raiz && strpos($caminho, $raiz) === 0 && is_file($caminho) && filesize($caminho) <= 8 * 1024 * 1024) {
                    $pdfBase64 = base64_encode((string)file_get_contents($caminho));
                }
            }
            if ($pdfBase64 === '') {
                throw new RuntimeException('Cole a transcrição do YouTube (ou tenha uma descrição ou um PDF na aula) para gerar.');
            }
            $fonte = '(Sem transcrição: use o PDF anexo como conteúdo da aula.)';
        }

        $modelo = (string)@file_get_contents(__DIR__ . '/prompts/mini-apostila.txt');
        if ($modelo === '') {
            throw new RuntimeException('Modelo de prompt não encontrado (includes/prompts/mini-apostila.txt).');
        }
        $cfg = meta_config($pdo);
        $prompt = strtr($modelo, [
            '{concurso}' => (string)$ctx['curso'],
            '{banca}' => 'a banca do concurso',
            '{materia}' => (string)($ctx['materia'] ?: $ctx['modulo']),
            '{assunto}' => (string)($ctx['assunto'] ?: ($dados['titulo'] ?? '')),
            '{professor}' => trim((string)($dados['professor'] ?? '')) ?: 'da aula',
            '{titulo}' => (string)($dados['titulo'] ?? ''),
            '{min_questoes}' => (string)($cfg['min_questoes'] ?? 5),
            '{transcricao}' => mb_substr($fonte, 0, 30000),
        ]);

        $partes = [['text' => $prompt]];
        if ($pdfBase64 !== '') {
            $partes[] = ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $pdfBase64]];
        }
        if ($usarVideo) {
            $partes[] = ['fileData' => ['fileUri' => $video]];
        }
        try {
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['temperature' => 0.4]], (int)($dados['timeout'] ?? 110));
        } catch (RuntimeException $e) {
            // Vídeo privado/não listado ou muito longo: tenta sem o vídeo, com a descrição atual.
            if (!$usarVideo || mb_strlen($atual) < 200) {
                throw $e;
            }
            array_pop($partes);
            $partes[0]['text'] = str_replace('(Use o vídeo do YouTube anexo como transcrição da aula. A descrição antiga abaixo é só apoio.)', '', $partes[0]['text']);
            $resp = podcast_gemini(PODCAST_MODELO_TEXTO, ['contents' => [['parts' => $partes]], 'generationConfig' => ['temperature' => 0.4]], (int)($dados['timeout'] ?? 110));
        }
        $texto = '';
        foreach ((array)($resp['candidates'][0]['content']['parts'] ?? []) as $p) {
            $texto .= (string)($p['text'] ?? '');
        }
        // A IA conferiu e o material (vídeo/PDF) não é do assunto da aula: não grava texto errado.
        if (preg_match('~===\s*CONFERIR\s*===\s*(.{0,200})~u', $texto, $mC)) {
            throw new RuntimeException('Material de outro assunto (' . trim(strip_tags($mC[1])) . '): a teoria não foi escrita. Confira o vídeo/PDF da aula na Revisão de aulas.');
        }
        // Junta DESCRICAO + APOSTILA num só HTML (a descrição vem primeiro).
        $texto = preg_replace('~^```(?:html)?\s*|\s*```$~m', '', $texto);
        $texto = str_replace(['=== DESCRICAO ===', '=== APOSTILA ==='], '', (string)$texto);
        $html = trim(aula_desc_sanitizar_html((string)$texto));
        if (mb_strlen(strip_tags($html)) < 200) {
            throw new RuntimeException('A IA devolveu um texto curto demais. Tente de novo.');
        }
        return $html;
    }
}
