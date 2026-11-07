<?php
// includes/biblioteca-lib.php
// Biblioteca de conteúdos: reaproveita aulas (vídeo + PDF) e módulos já
// cadastrados em qualquer curso, sem subir o arquivo de novo.
// O arquivo físico é compartilhado: as cópias apontam para o mesmo PDF.
declare(strict_types=1);

if (!function_exists('biblioteca_buscar_conteudos')) {
    /**
     * Lista conteúdos já cadastrados em todos os cursos, sem repetir o mesmo
     * material (mesmo título + vídeo + PDF aparece uma vez só).
     */
    function biblioteca_buscar_conteudos(PDO $pdo, string $busca = '', int $limite = 60): array
    {
        $params = [];
        $where = '1=1';
        if ($busca !== '') {
            $where = '(a.titulo LIKE :b1 OR a.pdf_nome LIKE :b2 OR c.nome LIKE :b3 OR m.titulo LIKE :b4)';
            foreach (['b1', 'b2', 'b3', 'b4'] as $k) {
                $params[':' . $k] = '%' . $busca . '%';
            }
        }

        $sql = "SELECT MIN(a.id) AS id, a.titulo, a.video_url, a.pdf_arquivo, a.pdf_nome, a.duracao, a.pdf_duracao,
                       GROUP_CONCAT(DISTINCT c.nome ORDER BY c.nome SEPARATOR ' · ') AS cursos,
                       MIN(m.titulo) AS modulo
                  FROM admin_curso_aulas a
                  LEFT JOIN admin_cursos c ON c.id = a.curso_id
                  LEFT JOIN admin_curso_modulos m ON m.id = a.modulo_id
                 WHERE $where
              GROUP BY a.titulo, a.video_url, a.pdf_arquivo, a.pdf_nome, a.duracao, a.pdf_duracao
              ORDER BY a.titulo ASC
                 LIMIT " . max(1, min(200, $limite));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('biblioteca_importar_conteudos')) {
    /**
     * Copia os conteúdos escolhidos para o módulo de destino.
     * Não duplica arquivo: a cópia aponta para o mesmo PDF e o mesmo vídeo.
     * Retorna quantos foram importados (ignora os que já existem no módulo).
     */
    function biblioteca_importar_conteudos(PDO $pdo, array $origemIds, int $cursoId, int $moduloId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $origemIds), static fn(int $v): bool => $v > 0)));
        if (!$ids) {
            return 0;
        }

        $stmt = $pdo->prepare('SELECT materia_id, topico_id FROM admin_curso_modulos WHERE id = :m AND curso_id = :c LIMIT 1');
        $stmt->execute([':m' => $moduloId, ':c' => $cursoId]);
        $destino = $stmt->fetch();
        if (!$destino) {
            throw new RuntimeException('Módulo de destino não encontrado.');
        }

        $stmtOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM admin_curso_aulas WHERE curso_id = :c AND modulo_id = :m');
        $stmtOrdem->execute([':c' => $cursoId, ':m' => $moduloId]);
        $ordem = (int)$stmtOrdem->fetchColumn();

        $stmtOrigem = $pdo->prepare('SELECT * FROM admin_curso_aulas WHERE id = :id LIMIT 1');
        $stmtExiste = $pdo->prepare('SELECT COUNT(*) FROM admin_curso_aulas WHERE curso_id = :c AND modulo_id = :m AND titulo = :t AND COALESCE(video_url, \'\') = :v AND COALESCE(pdf_arquivo, \'\') = :p');
        $stmtInsert = $pdo->prepare('INSERT INTO admin_curso_aulas (curso_id, modulo_id, materia_id, topico_id, titulo, descricao, video_url, pdf_arquivo, pdf_nome, video_arquivo, material_arquivo, material_nome, duracao, pdf_duracao, ordem)
            VALUES (:curso_id, :modulo_id, :materia_id, :topico_id, :titulo, :descricao, :video_url, :pdf_arquivo, :pdf_nome, :video_arquivo, :material_arquivo, :material_nome, :duracao, :pdf_duracao, :ordem)');

        $importados = 0;
        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $stmtOrigem->execute([':id' => $id]);
                $a = $stmtOrigem->fetch();
                if (!$a) {
                    continue;
                }
                $stmtExiste->execute([
                    ':c' => $cursoId, ':m' => $moduloId, ':t' => (string)$a['titulo'],
                    ':v' => (string)($a['video_url'] ?? ''), ':p' => (string)($a['pdf_arquivo'] ?? ''),
                ]);
                if ((int)$stmtExiste->fetchColumn() > 0) {
                    continue;
                }
                $ordem++;
                $stmtInsert->execute([
                    ':curso_id' => $cursoId,
                    ':modulo_id' => $moduloId,
                    ':materia_id' => ((int)($destino['materia_id'] ?? 0)) ?: ($a['materia_id'] ?? null),
                    ':topico_id' => ((int)($destino['topico_id'] ?? 0)) ?: ($a['topico_id'] ?? null),
                    ':titulo' => $a['titulo'],
                    ':descricao' => $a['descricao'],
                    ':video_url' => $a['video_url'],
                    ':pdf_arquivo' => $a['pdf_arquivo'],
                    ':pdf_nome' => $a['pdf_nome'],
                    ':video_arquivo' => $a['video_arquivo'] ?? null,
                    ':material_arquivo' => $a['material_arquivo'] ?? null,
                    ':material_nome' => $a['material_nome'] ?? null,
                    ':duracao' => $a['duracao'],
                    ':pdf_duracao' => $a['pdf_duracao'],
                    ':ordem' => $ordem,
                ]);
                $importados++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $importados;
    }
}

if (!function_exists('biblioteca_listar_modulos_outros_cursos')) {
    function biblioteca_listar_modulos_outros_cursos(PDO $pdo, int $cursoAtualId): array
    {
        $stmt = $pdo->prepare('SELECT m.id, m.titulo, c.nome AS curso, (SELECT COUNT(*) FROM admin_curso_aulas a WHERE a.modulo_id = m.id) AS total_aulas
                                 FROM admin_curso_modulos m JOIN admin_cursos c ON c.id = m.curso_id
                                WHERE m.curso_id <> :c ORDER BY c.nome, m.ordem, m.id');
        $stmt->execute([':c' => $cursoAtualId]);
        return $stmt->fetchAll();
    }
}

if (!function_exists('biblioteca_copiar_modulos')) {
    /**
     * Copia módulos inteiros (com todas as aulas) de outros cursos para o curso
     * de destino. Os PDFs e vídeos continuam sendo os mesmos arquivos.
     */
    function biblioteca_copiar_modulos(PDO $pdo, array $moduloIds, int $cursoDestinoId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $moduloIds), static fn(int $v): bool => $v > 0)));
        if (!$ids || $cursoDestinoId <= 0) {
            return 0;
        }

        $stmtOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM admin_curso_modulos WHERE curso_id = :c');
        $stmtOrdem->execute([':c' => $cursoDestinoId]);
        $ordem = (int)$stmtOrdem->fetchColumn();

        $stmtMod = $pdo->prepare('SELECT * FROM admin_curso_modulos WHERE id = :id LIMIT 1');
        $stmtInsMod = $pdo->prepare('INSERT INTO admin_curso_modulos (curso_id, titulo, descricao, capa, materia_id, topico_id, video_url, video_arquivo, material_arquivo, material_nome, ordem)
            VALUES (:curso_id, :titulo, :descricao, :capa, :materia_id, :topico_id, :video_url, :video_arquivo, :material_arquivo, :material_nome, :ordem)');
        $stmtAulas = $pdo->prepare('SELECT id FROM admin_curso_aulas WHERE modulo_id = :m ORDER BY ordem, id');

        $copiados = 0;
        foreach ($ids as $id) {
            $stmtMod->execute([':id' => $id]);
            $m = $stmtMod->fetch();
            if (!$m || (int)$m['curso_id'] === $cursoDestinoId) {
                continue;
            }
            $ordem++;
            $stmtInsMod->execute([
                ':curso_id' => $cursoDestinoId,
                ':titulo' => $m['titulo'],
                ':descricao' => $m['descricao'],
                ':capa' => $m['capa'],
                ':materia_id' => $m['materia_id'],
                ':topico_id' => $m['topico_id'],
                ':video_url' => $m['video_url'] ?? null,
                ':video_arquivo' => $m['video_arquivo'] ?? null,
                ':material_arquivo' => $m['material_arquivo'] ?? null,
                ':material_nome' => $m['material_nome'] ?? null,
                ':ordem' => $ordem,
            ]);
            $novoModuloId = (int)$pdo->lastInsertId();

            $stmtAulas->execute([':m' => $id]);
            $aulaIds = array_map('intval', $stmtAulas->fetchAll(PDO::FETCH_COLUMN));
            biblioteca_importar_conteudos($pdo, $aulaIds, $cursoDestinoId, $novoModuloId);
            $copiados++;
        }

        return $copiados;
    }
}
