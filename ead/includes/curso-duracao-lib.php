<?php
// includes/curso-duracao-lib.php — Carga horária REAL do curso: soma da duração das aulas (vídeo + PDF).
// Antes os cards mostravam o campo manual "horas do curso" (ex.: 495h) e a leitura de "28:30" virava 28 horas.
declare(strict_types=1);

if (!function_exists('curso_duracao_segundos')) {
    /**
     * Duração escrita na aula -> segundos.
     * "28:30" = 28 min 30 s (formato do vídeo) · "1:02:15" = 1 h 2 min 15 s · "40 min" · "1h30" · "2h" · "45" (número sozinho = minutos).
     */
    function curso_duracao_segundos(string $valor): int
    {
        $t = trim(mb_strtolower($valor, 'UTF-8'));
        if ($t === '') {
            return 0;
        }
        $t = str_replace(',', '.', $t);
        if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            return isset($m[3]) && $m[3] !== '' ? (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] : (int)$m[1] * 60 + (int)$m[2];
        }
        $s = 0.0;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(h|hora|horas)\b/u', $t, $m) || preg_match('/(\d+(?:\.\d+)?)\s*h/u', $t, $m)) {
            $s += (float)$m[1] * 3600;
            if (preg_match('/\d\s*h\s*(\d{1,2})\b/u', $t, $m2)) { // "1h30"
                $s += (int)$m2[1] * 60;
            }
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(min|minuto|minutos|m)\b/u', $t, $m)) {
            $s += (float)$m[1] * 60;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(s|seg|segundo|segundos)\b/u', $t, $m)) {
            $s += (float)$m[1];
        }
        if ($s <= 0 && is_numeric($t)) {
            $s = (float)$t * 60; // número sozinho: minutos
        }
        return (int)round($s);
    }

    /** Soma real de um curso: ['total' => seg, 'video' => seg, 'pdf' => seg]. */
    function curso_duracao_real(PDO $pdo, int $cursoId): array
    {
        static $memo = [];
        if (isset($memo[$cursoId])) {
            return $memo[$cursoId];
        }
        $v = 0;
        $p = 0;
        try {
            $st = $pdo->prepare('SELECT duracao, pdf_duracao, pdf_arquivo FROM admin_curso_aulas WHERE curso_id = :c');
            $st->execute([':c' => $cursoId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $v += curso_duracao_segundos((string)($a['duracao'] ?? ''));
                $p += curso_duracao_segundos((string)($a['pdf_duracao'] ?? ''));
            }
        } catch (Throwable $e) {
            // coluna pdf_duracao ainda não existe: só o vídeo
            try {
                $st = $pdo->prepare('SELECT duracao FROM admin_curso_aulas WHERE curso_id = :c');
                $st->execute([':c' => $cursoId]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $d) {
                    $v += curso_duracao_segundos((string)$d);
                }
            } catch (Throwable $e2) {
            }
        }
        return $memo[$cursoId] = ['total' => $v + $p, 'video' => $v, 'pdf' => $p];
    }

    /** Sempre em horas: "12h30", "8h", "45min" (menos de 1 hora). '' quando zero. */
    function curso_duracao_formatar(int $segundos): string
    {
        if ($segundos <= 0) {
            return '';
        }
        $min = (int)round($segundos / 60);
        if ($min < 60) {
            return $min . 'min';
        }
        $h = intdiv($min, 60);
        $r = $min % 60;
        return $h . 'h' . ($r ? str_pad((string)$r, 2, '0', STR_PAD_LEFT) : '');
    }
}
