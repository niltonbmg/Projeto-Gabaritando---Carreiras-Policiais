<?php
// simulado-pdf.php
// Gera versão em PDF para impressão/download do simulado, com folha branca, logo, orientacoes, nome do aluno, data e nota.
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api/admin-auth.php';
require_once __DIR__ . '/api/authenticador.php';
require_once __DIR__ . '/includes/admin-cursos-lib.php';

$adminLogado = function_exists('auth_admin_is_logged') && auth_admin_is_logged();
$alunoLogado = function_exists('auth_is_logged') && auth_is_logged();

if (!$adminLogado && !$alunoLogado) {
    header('Location: login.php');
    exit;
}

if (!function_exists('sim_pdf_clean_filename')) {
    function sim_pdf_clean_filename(string $nome): string
    {
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome;
        $nome = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', strtolower($nome)) ?: 'simulado';
        return trim($nome, '-') ?: 'simulado';
    }
}

if (!function_exists('sim_pdf_bootstrap')) {
    function sim_pdf_bootstrap(PDO $pdo): void
    {
        admin_cursos_bootstrap($pdo);

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulados (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_id INT UNSIGNED DEFAULT NULL,
            titulo VARCHAR(190) NOT NULL,
            descricao TEXT NULL,
            instrucoes TEXT NULL,
            tempo_minutos INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulados_curso (curso_id),
            KEY idx_admin_simulados_status (status),
            KEY idx_admin_simulados_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_questoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            simulado_id INT UNSIGNED NOT NULL,
            enunciado TEXT NOT NULL,
            explicacao TEXT NULL,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_questoes_simulado (simulado_id),
            KEY idx_admin_simulado_questoes_ordem (ordem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_simulado_opcoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            questao_id INT UNSIGNED NOT NULL,
            letra VARCHAR(8) NOT NULL,
            texto TEXT NOT NULL,
            correta TINYINT(1) NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_simulado_opcoes_questao (questao_id),
            KEY idx_admin_simulado_opcoes_correta (correta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('sim_pdf_carregar_prova')) {
    function sim_pdf_carregar_prova(PDO $pdo, int $simuladoId, bool $somenteAtivo): ?array
    {
        $statusSql = $somenteAtivo ? " AND s.status = 'ativo'" : '';
        $stmt = $pdo->prepare("SELECT s.*, c.nome AS curso_nome FROM admin_simulados s LEFT JOIN admin_cursos c ON c.id = s.curso_id WHERE s.id = :id {$statusSql} LIMIT 1");
        $stmt->execute([':id' => $simuladoId]);
        $simulado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$simulado) {
            return null;
        }

        $stmtQuestoes = $pdo->prepare('SELECT * FROM admin_simulado_questoes WHERE simulado_id = :id ORDER BY ordem ASC, id ASC');
        $stmtQuestoes->execute([':id' => $simuladoId]);
        $questoes = $stmtQuestoes->fetchAll(PDO::FETCH_ASSOC);

        $stmtOpcoes = $pdo->prepare('SELECT * FROM admin_simulado_opcoes WHERE questao_id = :id ORDER BY ordem ASC, id ASC');
        foreach ($questoes as &$questao) {
            $stmtOpcoes->execute([':id' => (int)$questao['id']]);
            $questao['opcoes'] = $stmtOpcoes->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($questao);

        $simulado['questoes'] = $questoes;
        return $simulado;
    }
}

final class GabaritandoPdf
{
    private float $w = 595.28;
    private float $h = 841.89;
    private array $pages = [];
    private string $ops = '';
    private array $images = [];
    private int $imageCounter = 0;

    public function addPage(): void
    {
        if ($this->ops !== '') {
            $this->pages[] = $this->ops;
        }
        $this->ops = "0 0 0 rg\n0 0 0 RG\n";
    }

    public function finish(): void
    {
        if ($this->ops !== '') {
            $this->pages[] = $this->ops;
            $this->ops = '';
        }
    }

    public function pageWidth(): float
    {
        return $this->w;
    }

    public function pageHeight(): float
    {
        return $this->h;
    }

    public function addJpegImage(string $data): ?string
    {
        $info = @getimagesizefromstring($data);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return null;
        }
        $name = 'I' . (++$this->imageCounter);
        $this->images[$name] = [
            'data' => $data,
            'width' => (int)$info[0],
            'height' => (int)$info[1],
        ];
        return $name;
    }

    public function image(string $name, float $x, float $yTop, float $width, float $height): void
    {
        $y = $this->h - $yTop - $height;
        $this->ops .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $width, $height, $x, $y, $name);
    }

    public function imageFit(string $name, float $x, float $yTop, float $boxWidth, float $boxHeight): void
    {
        $img = $this->images[$name] ?? null;
        if (!$img) {
            return;
        }
        $ratio = min($boxWidth / max(1, (float)$img['width']), $boxHeight / max(1, (float)$img['height']));
        $w = (float)$img['width'] * $ratio;
        $h = (float)$img['height'] * $ratio;
        $this->image($name, $x + (($boxWidth - $w) / 2), $yTop + (($boxHeight - $h) / 2), $w, $h);
    }

    public function text(float $x, float $yTop, string $text, float $size = 10, bool $bold = false, array $rgb = [17, 24, 39]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $y = $this->h - $yTop;
        $encoded = self::encodeText($text);
        [$r, $g, $b] = self::rgb($rgb);
        $this->ops .= sprintf("%.3F %.3F %.3F rg BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET 0 0 0 rg\n", $r, $g, $b, $font, $size, $x, $y, $encoded);
    }

    public function line(float $x1, float $y1Top, float $x2, float $y2Top, array $rgb = [210, 214, 220], float $width = 0.8): void
    {
        $y1 = $this->h - $y1Top;
        $y2 = $this->h - $y2Top;
        [$r, $g, $b] = self::rgb($rgb);
        $this->ops .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S 0 0 0 RG\n", $r, $g, $b, $width, $x1, $y1, $x2, $y2);
    }

    public function rect(float $x, float $yTop, float $width, float $height, bool $fill = false, array $rgb = [246, 248, 252], array $strokeRgb = [220, 225, 232], float $strokeWidth = 0.8): void
    {
        $y = $this->h - $yTop - $height;
        if ($fill) {
            [$r, $g, $b] = self::rgb($rgb);
            $this->ops .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f 0 0 0 rg\n", $r, $g, $b, $x, $y, $width, $height);
        } else {
            [$r, $g, $b] = self::rgb($strokeRgb);
            $this->ops .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S 0 0 0 RG\n", $r, $g, $b, $strokeWidth, $x, $y, $width, $height);
        }
    }

    public function circle(float $x, float $yTop, float $r, array $strokeRgb = [42, 52, 65], float $strokeWidth = 0.8): void
    {
        $y = $this->h - $yTop;
        $c = 0.5522847498 * $r;
        [$sr, $sg, $sb] = self::rgb($strokeRgb);
        $this->ops .= sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c S 0 0 0 RG\n",
            $sr, $sg, $sb, $strokeWidth,
            $x + $r, $y,
            $x + $r, $y + $c, $x + $c, $y + $r, $x, $y + $r,
            $x - $c, $y + $r, $x - $r, $y + $c, $x - $r, $y,
            $x - $r, $y - $c, $x - $c, $y - $r, $x, $y - $r,
            $x + $c, $y - $r, $x + $r, $y - $c, $x + $r, $y
        );
    }

    public static function wrap(string $text, float $maxWidth, float $fontSize): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (self::textWidth($candidate, $fontSize) <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines ?: [''];
    }

    public static function textWidth(string $text, float $fontSize): float
    {
        $txt = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        return strlen($txt) * $fontSize * 0.48;
    }

    private static function encodeText(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($encoded === false) {
            $encoded = $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private static function rgb(array $rgb): array
    {
        return [
            max(0, min(255, (int)($rgb[0] ?? 0))) / 255,
            max(0, min(255, (int)($rgb[1] ?? 0))) / 255,
            max(0, min(255, (int)($rgb[2] ?? 0))) / 255,
        ];
    }

    public function output(string $filename): void
    {
        $this->finish();
        if (!$this->pages) {
            $this->addPage();
            $this->finish();
        }

        $objects = [];
        $catalogId = 1;
        $pagesId = 2;
        $fontRegularId = 3;
        $fontBoldId = 4;
        $nextId = 5;
        $imageIds = [];

        foreach ($this->images as $name => $img) {
            $imageIds[$name] = $nextId++;
            $objects[$imageIds[$name]] = "<< /Type /XObject /Subtype /Image /Width {$img['width']} /Height {$img['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream";
        }

        $pageIds = [];
        foreach ($this->pages as $content) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $pageIds[] = $pageId;
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

            $xObjects = '';
            foreach ($imageIds as $name => $id) {
                $xObjects .= "/{$name} {$id} 0 R ";
            }
            $xObjectRes = $xObjects !== '' ? "/XObject << {$xObjects} >>" : '';
            $objects[$pageId] = "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 {$this->w} {$this->h}] /Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> {$xObjectRes} >> /Contents {$contentId} 0 R >>";
        }

        $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds));
        $objects[$catalogId] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
        $objects[$pagesId] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";
        // O PDF usa uma família sem serifa, no padrão visual da Ubuntu. Se o leitor substituir fonte, mantém o layout estável.
        $objects[$fontRegularId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBoldId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $pdf;
        exit;
    }
}

if (!function_exists('sim_pdf_logo_jpeg')) {
    function sim_pdf_logo_jpeg(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $data = file_get_contents($path);
        if ($data === false || $data === '') {
            return null;
        }
        $info = @getimagesizefromstring($data);
        if (!$info) {
            return null;
        }
        if (($info['mime'] ?? '') === 'image/jpeg') {
            return $data;
        }
        if (($info['mime'] ?? '') === 'image/png' && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            $src = @imagecreatefrompng($path);
            if (!$src) {
                return null;
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $dst = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $w, $h, $white);
            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
            ob_start();
            imagejpeg($dst, null, 90);
            $jpg = (string)ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            return $jpg !== '' ? $jpg : null;
        }
        return null;
    }
}

if (!function_exists('sim_pdf_label_value')) {
    function sim_pdf_label_value(GabaritandoPdf $pdf, float $x, float $y, float $w, string $label, string $value = ''): void
    {
        $pdf->text($x, $y, $label, 8.5, true, [91, 104, 124]);
        $pdf->rect($x, $y + 6, $w, 26, false, [255, 255, 255], [209, 216, 226], 0.8);
        if ($value !== '') {
            $pdf->text($x + 8, $y + 24, $value, 10, false, [17, 24, 39]);
        }
    }
}

if (!function_exists('sim_pdf_header')) {
    function sim_pdf_header(GabaritandoPdf $pdf, array $simulado, ?string $logoName, int $pagina): float
    {
        $yellow = [250, 181, 40];
        $dark = [17, 24, 39];
        $muted = [91, 104, 124];
        $soft = [248, 250, 252];

        $curso = trim((string)($simulado['curso_nome'] ?? 'Todos os cursos'));
        if ($curso === '') {
            $curso = 'Todos os cursos';
        }
        $titulo = trim((string)($simulado['titulo'] ?? 'Simulado'));
        if ($titulo === '') {
            $titulo = 'Simulado';
        }

        // Folha inteira branca, com cabeçalho limpo para impressão.
        $pdf->rect(0, 0, 595.28, 841.89, true, [255, 255, 255]);

        if ($logoName) {
            // Mantém a proporção da logo. Em folha branca, PNG transparente não fica com quadrado aparente.
            $pdf->imageFit($logoName, 42, 28, 54, 54);
        } else {
            $pdf->text(42, 58, 'G', 28, true, $yellow);
        }

        $pdf->text(112, 42, 'SIMULADO PARA IMPRESSAO', 19, true, $dark);
        $pdf->text(112, 65, $titulo, 11.5, true, $yellow);
        $pdf->text(468, 42, 'Pagina ' . $pagina, 8.5, true, $muted);

        $pdf->line(42, 96, 552, 96, $yellow, 2.0);
        $pdf->text(42, 121, 'Curso', 8.5, true, $muted);
        $pdf->text(42, 138, $curso, 11, true, $dark);

        // Campos em linha, como prova impressa: aluno, data e nota.
        $fieldY = 162;
        $pdf->text(42, $fieldY, 'Nome do aluno:', 9, true, $dark);
        $pdf->line(126, $fieldY + 2, 333, $fieldY + 2, [148, 163, 184], 0.8);
        $pdf->text(354, $fieldY, 'Data:', 9, true, $dark);
        $pdf->line(388, $fieldY + 2, 454, $fieldY + 2, [148, 163, 184], 0.8);
        $pdf->text(476, $fieldY, 'Nota:', 9, true, $dark);
        $pdf->line(510, $fieldY + 2, 552, $fieldY + 2, [148, 163, 184], 0.8);

        $pdf->rect(42, 184, 510, 24, true, $soft);
        $pdf->rect(42, 184, 510, 24, false, [255, 255, 255], [226, 232, 240], 0.6);
        $pdf->text(56, 199, 'Leia as orientacoes, resolva com calma e marque somente uma alternativa por questao.', 8.8, false, $muted);

        return 230;
    }
}

if (!function_exists('sim_pdf_footer')) {
    function sim_pdf_footer(GabaritandoPdf $pdf): void
    {
        $pdf->line(42, 812, 552, 812, [226, 232, 240], 0.6);
        $pdf->text(42, 826, 'Projeto Gabaritando - Simulado para impressao', 8, false, [100, 116, 139]);
        $pdf->text(468, 826, 'Boa prova!', 8, true, [100, 116, 139]);
    }
}

$simuladoId = max(0, (int)($_GET['simulado_id'] ?? $_GET['id'] ?? 0));
if ($simuladoId <= 0) {
    http_response_code(400);
    echo 'Simulado não informado.';
    exit;
}

try {
    $pdo = admin_cursos_db();
    sim_pdf_bootstrap($pdo);
    $simulado = sim_pdf_carregar_prova($pdo, $simuladoId, !$adminLogado);
    if (!$simulado) {
        throw new RuntimeException('Simulado não encontrado ou indisponível.');
    }

    $pdf = new GabaritandoPdf();
    $logoName = null;
    $logoData = sim_pdf_logo_jpeg(__DIR__ . '/img/logo.png');
    if ($logoData !== null) {
        $logoName = $pdf->addJpegImage($logoData);
    }

    $pagina = 1;
    $pdf->addPage();
    $y = sim_pdf_header($pdf, $simulado, $logoName, $pagina);

    $descricao = trim((string)($simulado['instrucoes'] ?: $simulado['descricao'] ?: 'Leia as questões com atenção e marque apenas uma alternativa por questão.'));
    if ($descricao !== '') {
        $linhasDescricao = GabaritandoPdf::wrap($descricao, 488, 9.4);
        $boxH = 34 + (count($linhasDescricao) * 12);
        $pdf->rect(42, $y, 510, $boxH, true, [255, 255, 255]);
        $pdf->rect(42, $y, 510, $boxH, false, [255, 255, 255], [250, 181, 40], 1.0);
        $pdf->rect(42, $y, 8, $boxH, true, [250, 181, 40]);
        $pdf->text(60, $y + 20, 'Orientacoes da prova', 10.8, true, [17, 24, 39]);
        $lineY = $y + 38;
        foreach ($linhasDescricao as $linha) {
            $pdf->text(60, $lineY, $linha, 9.4, false, [55, 65, 81]);
            $lineY += 12;
        }
        $y += $boxH + 24;
    }

    $questoes = $simulado['questoes'] ?? [];
    foreach ($questoes as $index => $questao) {
        $textoQuestao = trim((string)($questao['enunciado'] ?? ''));
        $linhasQuestao = GabaritandoPdf::wrap($textoQuestao, 488, 10.3);
        $opcoes = $questao['opcoes'] ?? [];
        $alturaOpcoes = 0;
        foreach ($opcoes as $opcaoPrevia) {
            $alturaOpcoes += max(24, count(GabaritandoPdf::wrap(trim((string)($opcaoPrevia['texto'] ?? '')), 430, 9.8)) * 12 + 12);
        }
        $alturaQuestao = 45 + (count($linhasQuestao) * 14) + $alturaOpcoes + 18;

        if ($y + $alturaQuestao > 790) {
            sim_pdf_footer($pdf);
            $pagina++;
            $pdf->addPage();
            $y = sim_pdf_header($pdf, $simulado, $logoName, $pagina);
        }

        $pdf->rect(42, $y, 30, 24, true, [250, 181, 40]);
        $pdf->text(52, $y + 16, (string)($index + 1), 10.5, true, [0, 0, 0]);
        $pdf->text(84, $y + 16, 'Questao ' . ($index + 1), 11, true, [17, 24, 39]);
        $pdf->line(42, $y + 31, 552, $y + 31, [226, 232, 240], 0.7);
        $y += 48;

        foreach ($linhasQuestao as $linha) {
            $pdf->text(48, $y, $linha, 10.3, false, [17, 24, 39]);
            $y += 14;
        }
        $y += 8;

        foreach ($opcoes as $opcao) {
            $letra = trim((string)($opcao['letra'] ?? ''));
            $txtOpcao = trim((string)($opcao['texto'] ?? ''));
            $linhasOpcao = GabaritandoPdf::wrap($txtOpcao, 430, 9.8);
            $hOpcao = max(24, count($linhasOpcao) * 12 + 12);
            if ($y + $hOpcao > 790) {
                sim_pdf_footer($pdf);
                $pagina++;
                $pdf->addPage();
                $y = sim_pdf_header($pdf, $simulado, $logoName, $pagina);
            }

            $pdf->circle(57, $y + 8, 7, [42, 52, 65], 0.8);
            $pdf->text(53.2, $y + 11.3, $letra, 8, true, [17, 24, 39]);
            $linhaY = $y + 11;
            foreach ($linhasOpcao as $linha) {
                $pdf->text(76, $linhaY, $linha, 9.8, false, [17, 24, 39]);
                $linhaY += 12;
            }
            $y += $hOpcao;
        }
        $pdf->line(42, $y + 6, 552, $y + 6, [229, 231, 235], 0.6);
        $y += 24;
    }

    if (!$questoes) {
        $pdf->rect(42, $y, 510, 52, true, [248, 250, 252]);
        $pdf->rect(42, $y, 510, 52, false, [255, 255, 255], [226, 232, 240], 0.8);
        $pdf->text(58, $y + 30, 'Nenhuma questao cadastrada neste simulado.', 11, true, [91, 104, 124]);
    }

    sim_pdf_footer($pdf);

    $filename = 'simulado-' . sim_pdf_clean_filename((string)$simulado['titulo']) . '.pdf';
    $pdf->output($filename);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao gerar PDF: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
