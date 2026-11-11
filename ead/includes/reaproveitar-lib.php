<?php
// includes/reaproveitar-lib.php
// Zero desperdício: o mesmo material em cursos diferentes (mesmo PDF/vídeo, ou mesma aula provisória do mesmo assunto)
// gera videoaula, podcast e teoria UMA vez; as outras aulas recebem uma cópia, sem gastar IA.
declare(strict_types=1);

if (!function_exists('curso_perfil')) {
    /**
     * Carreira, nível e estado do curso (ex.: PM · soldado · BA). Reaproveitamento só entre cursos com o MESMO perfil
     * (Oficial não usa aula de Soldado; Bombeiro não usa de PM; Sergipe não usa legislação da Bahia).
     * Deduzido do nome do curso/cargo; o mentor pode corrigir (tabela curso_perfil). Sem certeza = não reaproveita.
     */
    function curso_perfil(PDO $pdo, int $cursoId): ?string
    {
        static $cache = [];
        if (array_key_exists($cursoId, $cache)) {
            return $cache[$cursoId];
        }
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS curso_perfil (curso_id INT UNSIGNED NOT NULL PRIMARY KEY, carreira VARCHAR(20) NOT NULL, nivel VARCHAR(20) NOT NULL, uf CHAR(2) NOT NULL DEFAULT "") ENGINE=InnoDB');
            $o = $pdo->prepare('SELECT carreira, nivel, uf FROM curso_perfil WHERE curso_id = :c');
            $o->execute([':c' => $cursoId]);
            if ($r = $o->fetch()) {
                return $cache[$cursoId] = $r['carreira'] . '|' . $r['nivel'] . '|' . $r['uf'];
            }
        } catch (Throwable $e) {
        }
        $p = curso_perfil_deduzir($pdo, $cursoId);
        return $cache[$cursoId] = $p ? $p['carreira'] . '|' . $p['nivel'] . '|' . $p['uf'] : null;
    }

    function curso_perfil_deduzir(PDO $pdo, int $cursoId): ?array
    {
        $st = $pdo->prepare('SELECT c.nome, (SELECT GROUP_CONCAT(cg.nome SEPARATOR " ") FROM concurso_cargos cg WHERE cg.curso_id = c.id) cargos FROM admin_cursos c WHERE c.id = :c');
        $st->execute([':c' => $cursoId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $nome = (string)$r['nome'];
        // o cargo costuma vir no fim: "CFSD - PMBA E CBMBA - Soldado PM"
        $partes = preg_split('~\s+[-–]\s+~u', $nome);
        $cargo = mb_strtolower(trim((string)$r['cargos']) !== '' ? (string)$r['cargos'] : (string)end($partes));
        $tudo = mb_strtolower($nome . ' ' . $r['cargos']);
        $carreira = null;
        foreach (['BM' => '~bombeir|\bbm\b|\bcbm~u', 'PRF' => '~\bprf\b|rodoviári~u', 'PF' => '~\bpf\b|polícia federal~u', 'PC' => '~\bpc\b|polícia civil|investigador|escrivão|delegad~u',
                  'PP' => '~penal~u', 'GM' => '~guarda municipal|\bgcm\b~u', 'PM' => '~\bpm\b|polícia militar|\bpm[a-z]{2}\b|soldado|oficial~u'] as $c => $re) {
            if (preg_match($re, $cargo)) {
                $carreira = $c;
                break;
            }
        }
        $nivel = null;
        foreach (['oficial' => '~\bcfo|oficial|cadete|delegad~u', 'sargento' => '~\bcfs\b|sargento~u', 'soldado' => '~cfsd|soldado|\bsd\b|praça~u', 'agente' => '~agente|investigador|escrivão~u'] as $n => $re) {
            if (preg_match($re, $tudo)) {
                $nivel = $n;
                break;
            }
        }
        $uf = '';
        if (preg_match('~bahia|pmba|cbmba|\bba\b~u', $tudo)) {
            $uf = 'BA';
        } elseif (preg_match('~sergipe|\bse\b|pmse|cbmse~u', $tudo)) {
            $uf = 'SE';
        } elseif (preg_match('~\b(pm|cbm|pc)([a-z]{2})\b~u', $tudo, $m)) {
            $uf = strtoupper($m[2]);
        }
        return $carreira && $nivel ? ['carreira' => $carreira, 'nivel' => $nivel, 'uf' => $uf] : null;
    }
}

if (!function_exists('ia_aulas_gemeas')) {
    /** Outras aulas com o mesmo material desta (ids), da mais antiga para a mais nova. */
    function ia_aulas_gemeas(PDO $pdo, int $aulaId): array
    {
        $st = $pdo->prepare('SELECT id, curso_id, titulo, topico_id, TRIM(COALESCE(pdf_arquivo,"")) pdf, TRIM(COALESCE(video_url,"")) video FROM admin_curso_aulas WHERE id = :id');
        $st->execute([':id' => $aulaId]);
        $a = $st->fetch();
        if (!$a) {
            return [];
        }
        $perfil = curso_perfil($pdo, (int)$a['curso_id']);
        if ($perfil === null) {
            return []; // não sabemos carreira/nível deste curso: não reaproveita
        }
        // só cursos com o mesmo perfil (mesma carreira, nível e estado)
        $mesmoPerfil = static function (array $ids) use ($pdo, $perfil): array {
            $ok = [];
            $c = $pdo->prepare('SELECT curso_id FROM admin_curso_aulas WHERE id = :id');
            foreach ($ids as $id) {
                $c->execute([':id' => $id]);
                if (curso_perfil($pdo, (int)$c->fetchColumn()) === $perfil) {
                    $ok[] = $id;
                }
            }
            return $ok;
        };
        if ($a['pdf'] !== '' || $a['video'] !== '') {
            // mesmo PDF e mesmo vídeo = mesmo material
            $q = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE id <> :id AND TRIM(COALESCE(pdf_arquivo,"")) = :p AND TRIM(COALESCE(video_url,"")) = :v ORDER BY id');
            $q->execute([':id' => $aulaId, ':p' => $a['pdf'], ':v' => $a['video']]);
            return $mesmoPerfil(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)));
        }
        // sem material: aula do Antigão do mesmo assunto do edital e mesmo título (mesma parte/foco)
        if (empty($a['topico_id'])) {
            return [];
        }
        $q = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE id <> :id AND topico_id = :t AND TRIM(COALESCE(pdf_arquivo,"")) = "" AND TRIM(COALESCE(video_url,"")) = ""
                              AND LOWER(TRIM(titulo)) = LOWER(TRIM(:ti)) ORDER BY id');
        $q->execute([':id' => $aulaId, ':t' => (int)$a['topico_id'], ':ti' => (string)$a['titulo']]);
        return $mesmoPerfil(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)));
    }

    function ia_reaproveitar_marcar(string $tipo, int $aulaId, int $origem): void
    {
        require_once __DIR__ . '/ia-consumo-lib.php';
        $GLOBALS['gab_reaproveitado'] = ['tipo' => $tipo, 'aula' => $aulaId, 'de' => $origem];
    }

    /** Videoaula pronta (formato novo) de uma aula gêmea → copia. */
    function ia_reaproveitar_videoaula(PDO $pdo, int $aulaId): bool
    {
        foreach (ia_aulas_gemeas($pdo, $aulaId) as $g) {
            $st = $pdo->prepare("SELECT * FROM aula_videoaulas WHERE aula_id = :g AND status = 'pronto'");
            $st->execute([':g' => $g]);
            $v = $st->fetch();
            if ($v && (int)(json_decode((string)$v['roteiro'], true)['formato'] ?? 0) >= 2) {
                $pdo->prepare("INSERT INTO aula_videoaulas (aula_id, status, roteiro, partes, partes_ok, audios, erro) VALUES (:a, 'pronto', :r, :p, :ok, :au, NULL)
                               ON DUPLICATE KEY UPDATE status = 'pronto', roteiro = VALUES(roteiro), partes = VALUES(partes), partes_ok = VALUES(partes_ok), audios = VALUES(audios), erro = NULL")
                    ->execute([':a' => $aulaId, ':r' => $v['roteiro'], ':p' => $v['partes'], ':ok' => $v['partes_ok'], ':au' => $v['audios']]);
                ia_reaproveitar_marcar('videoaula', $aulaId, $g);
                return true;
            }
        }
        return false;
    }

    /** Podcast pronto de uma aula gêmea → copia (o arquivo de áudio é o mesmo). */
    function ia_reaproveitar_podcast(PDO $pdo, int $aulaId): bool
    {
        foreach (ia_aulas_gemeas($pdo, $aulaId) as $g) {
            $st = $pdo->prepare("SELECT * FROM aula_podcasts WHERE aula_id = :g AND status = 'pronto' AND arquivo IS NOT NULL AND (roteiro LIKE '%Antig%' OR roteiro LIKE '%Zero Um%')");
            $st->execute([':g' => $g]);
            $p = $st->fetch();
            if ($p) {
                $pdo->prepare("INSERT INTO aula_podcasts (aula_id, status, roteiro, partes, partes_ok, arquivo, duracao_seg, erro) VALUES (:a, 'pronto', :r, :p, :ok, :ar, :d, NULL)
                               ON DUPLICATE KEY UPDATE status = 'pronto', roteiro = VALUES(roteiro), partes = VALUES(partes), partes_ok = VALUES(partes_ok), arquivo = VALUES(arquivo), duracao_seg = VALUES(duracao_seg), erro = NULL")
                    ->execute([':a' => $aulaId, ':r' => $p['roteiro'], ':p' => $p['partes'], ':ok' => $p['partes_ok'], ':ar' => $p['arquivo'], ':d' => $p['duracao_seg']]);
                ia_reaproveitar_marcar('podcast', $aulaId, $g);
                return true;
            }
        }
        return false;
    }

    /** Teoria (apostila) já escrita pela IA numa aula gêmea → copia o texto. */
    function ia_reaproveitar_texto(PDO $pdo, int $aulaId, string $tipo): bool
    {
        foreach (ia_aulas_gemeas($pdo, $aulaId) as $g) {
            $st = $pdo->prepare("SELECT a.descricao FROM ia_fila f JOIN admin_curso_aulas a ON a.id = f.aula_id WHERE f.aula_id = :g AND f.tipo = :t AND f.status = 'feito'");
            $st->execute([':g' => $g, ':t' => $tipo]);
            $d = $st->fetchColumn();
            if ($d !== false && mb_strlen(strip_tags((string)$d)) > 300) {
                $atual = $pdo->prepare('SELECT descricao FROM admin_curso_aulas WHERE id = :id');
                $atual->execute([':id' => $aulaId]);
                try {
                    $pdo->prepare('INSERT INTO aula_descricao_historico (aula_id, descricao, origem) VALUES (:a, :d, :o)')->execute([':a' => $aulaId, ':d' => (string)$atual->fetchColumn(), ':o' => 'antes_da_ia']);
                } catch (Throwable $e) {
                }
                $pdo->prepare('UPDATE admin_curso_aulas SET descricao = :d WHERE id = :id')->execute([':d' => $d, ':id' => $aulaId]);
                ia_reaproveitar_marcar($tipo, $aulaId, $g);
                return true;
            }
        }
        return false;
    }
}
