<?php
// includes/lgpd-lib.php
// LGPD (Lei 13.709/2018) na plataforma:
//  - registro do aceite dos Termos e da Política de Privacidade (versão, data, IP anonimizado);
//  - pedidos do titular (art. 18): acesso/cópia, correção, exclusão/anonimização, revogação;
//  - preferências de privacidade (aparecer ou não no Ranking Geral);
//  - anonimização da conta quando o aluno pede exclusão (mantendo só o que a lei obriga guardar).
declare(strict_types=1);

const LGPD_VERSAO = '2026-11-10';           // mude quando a Política ou os Termos mudarem: todos aceitam de novo
const LGPD_EMAIL_ENCARREGADO = 'coordenacao@projetogabaritando.com';
const LGPD_PRAZO_DIAS = 15;                 // prazo de resposta ao titular (art. 19, II)

if (!function_exists('lgpd_bootstrap')) {
    function lgpd_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        static $feito = false;
        if ($feito) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS lgpd_consentimentos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario_id BIGINT UNSIGNED NOT NULL,
            versao VARCHAR(20) NOT NULL,
            aceito_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_parcial VARCHAR(45) NULL,
            origem VARCHAR(30) NOT NULL DEFAULT 'plataforma',
            KEY idx_lgpd_cons (usuario_id, versao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS lgpd_solicitacoes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            usuario_id BIGINT UNSIGNED NOT NULL,
            tipo ENUM('acesso','correcao','exclusao','revogacao','duvida') NOT NULL,
            detalhe TEXT NULL,
            status ENUM('aberta','concluida','negada') NOT NULL DEFAULT 'aberta',
            resposta TEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolvido_em DATETIME NULL,
            KEY idx_lgpd_sol (status, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS aluno_privacidade (
            usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            ranking_publico TINYINT(1) NOT NULL DEFAULT 1,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $feito = true;
    }

    /** IP com o final apagado (minimização: serve de prova do aceite sem identificar a pessoa). */
    function lgpd_ip_parcial(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)) . '::';
        }
        $p = explode('.', $ip);
        return count($p) === 4 ? $p[0] . '.' . $p[1] . '.' . $p[2] . '.0' : '';
    }

    function lgpd_registrar_aceite(PDO $pdo, int $usuarioId, string $origem = 'plataforma'): void
    {
        lgpd_bootstrap($pdo);
        $pdo->prepare('INSERT INTO lgpd_consentimentos (usuario_id, versao, ip_parcial, origem) VALUES (:u, :v, :ip, :o)')
            ->execute([':u' => $usuarioId, ':v' => LGPD_VERSAO, ':ip' => lgpd_ip_parcial(), ':o' => $origem]);
    }

    function lgpd_aceitou_versao_atual(PDO $pdo, int $usuarioId): bool
    {
        lgpd_bootstrap($pdo);
        $st = $pdo->prepare('SELECT 1 FROM lgpd_consentimentos WHERE usuario_id = :u AND versao = :v LIMIT 1');
        $st->execute([':u' => $usuarioId, ':v' => LGPD_VERSAO]);
        return (bool)$st->fetchColumn();
    }

    function lgpd_ranking_publico(PDO $pdo, int $usuarioId): bool
    {
        static $cache = [];
        if (!isset($cache[$usuarioId])) {
            try {
                lgpd_bootstrap($pdo);
                $st = $pdo->prepare('SELECT ranking_publico FROM aluno_privacidade WHERE usuario_id = :u');
                $st->execute([':u' => $usuarioId]);
                $v = $st->fetchColumn();
                $cache[$usuarioId] = $v === false ? true : (bool)$v;
            } catch (Throwable $e) {
                $cache[$usuarioId] = true;
            }
        }
        return $cache[$usuarioId];
    }

    function lgpd_solicitar(PDO $pdo, int $usuarioId, string $tipo, string $detalhe): int
    {
        lgpd_bootstrap($pdo);
        if (!in_array($tipo, ['acesso', 'correcao', 'exclusao', 'revogacao', 'duvida'], true)) {
            throw new RuntimeException('Tipo de pedido inválido.');
        }
        $pdo->prepare('INSERT INTO lgpd_solicitacoes (usuario_id, tipo, detalhe) VALUES (:u, :t, :d)')
            ->execute([':u' => $usuarioId, ':t' => $tipo, ':d' => mb_substr(trim($detalhe), 0, 2000)]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Cópia dos dados do aluno (art. 18, II): tudo que a plataforma guarda sobre ele, em JSON legível.
     * Só lê tabelas que existem; nunca inclui senha.
     */
    function lgpd_exportar(PDO $pdo, int $usuarioId): array
    {
        $out = ['gerado_em' => date('c'), 'controlador' => 'Projeto Gabaritando', 'encarregado' => LGPD_EMAIL_ENCARREGADO];
        $st = $pdo->prepare('SELECT id, nome, email, telefone, tipo, status, ultimo_login, created_at FROM usuarios WHERE id = :u');
        $st->execute([':u' => $usuarioId]);
        $out['cadastro'] = $st->fetch() ?: [];
        $tabelas = [
            'cursos' => 'SELECT curso_id, status FROM usuario_cursos WHERE usuario_id = :u',
            'progresso_aulas' => 'SELECT aula_id, assistido, assistido_em FROM aulas_progresso WHERE usuario_id = :u',
            'respostas_questoes' => 'SELECT questao_id, opcao_id, correta, respondido_em FROM banco_questoes_respostas_usuario WHERE usuario_id = :u',
            'marcacoes_texto' => 'SELECT aula_id, dados FROM aula_marcacoes WHERE usuario_id = :u',
            'marcacoes_pdf' => 'SELECT aula_id, dados FROM aula_marcacoes_pdf WHERE usuario_id = :u',
            'redacoes' => 'SELECT id, proposta_id, texto, status, nota, comentario, enviada_em, corrigida_em FROM redacoes WHERE usuario_id = :u',
            'desafio_do_dia' => 'SELECT desafio_id, acertos, total, tempo_seg, finalizado_em FROM desafio_tentativas WHERE usuario_id = :u',
            'concurso_escolhido' => 'SELECT concurso_id, cargo_id, escolhido_em FROM aluno_concurso WHERE usuario_id = :u',
            'rotina_cronograma' => 'SELECT * FROM meta_aluno_config WHERE usuario_id = :u',
            'respostas_videoaula' => 'SELECT aula_id, parada, correta, respondido_em FROM aula_videoaula_respostas WHERE usuario_id = :u',
            'edital_verticalizado' => 'SELECT edital_item_id, status FROM aluno_vertical WHERE usuario_id = :u',
            'consentimentos' => 'SELECT versao, aceito_em, ip_parcial, origem FROM lgpd_consentimentos WHERE usuario_id = :u',
            'pedidos_lgpd' => 'SELECT tipo, status, criado_em, resolvido_em FROM lgpd_solicitacoes WHERE usuario_id = :u',
            'privacidade' => 'SELECT ranking_publico FROM aluno_privacidade WHERE usuario_id = :u',
        ];
        foreach ($tabelas as $chave => $sql) {
            try {
                $q = $pdo->prepare($sql);
                $q->execute([':u' => $usuarioId]);
                $out[$chave] = $q->fetchAll();
            } catch (Throwable $e) {
                // tabela ainda não existe neste servidor
            }
        }
        return $out;
    }

    /**
     * Exclusão pedida pelo titular: anonimiza a conta (não dá para identificar a pessoa) e apaga o conteúdo pessoal.
     * Mantém só registros que a lei exige guardar (ex.: dados de venda/nota fiscal ficam no financeiro, fora daqui).
     */
    function lgpd_anonimizar(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare("UPDATE usuarios SET nome = 'Usuário excluído', email = :e, telefone = NULL, google_id = NULL, avatar = NULL, senha_hash = :s, status = 'inativo' WHERE id = :u")
            ->execute([':e' => 'excluido-' . $usuarioId . '@anonimizado.invalid', ':s' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), ':u' => $usuarioId]);
        // Arquivos de redação (foto/PDF da folha manuscrita podem identificar a pessoa).
        try {
            $st = $pdo->prepare('SELECT arquivo, audio FROM redacoes WHERE usuario_id = :u');
            $st->execute([':u' => $usuarioId]);
            foreach ($st->fetchAll() as $r) {
                foreach ([$r['arquivo'], $r['audio']] as $f) {
                    if ($f && str_starts_with((string)$f, 'uploads/redacoes/')) {
                        @unlink(dirname(__DIR__) . '/' . $f);
                    }
                }
            }
            $pdo->prepare('DELETE FROM redacoes WHERE usuario_id = :u')->execute([':u' => $usuarioId]);
        } catch (Throwable $e) {
        }
        foreach (['aula_marcacoes', 'aula_marcacoes_pdf', 'aluno_concurso', 'aluno_vertical', 'aluno_edital_visitas', 'meta_aluno_config', 'aluno_privacidade', 'aluno_jornada', 'meta_aluno_tour'] as $t) {
            try {
                $pdo->prepare("DELETE FROM `$t` WHERE usuario_id = :u")->execute([':u' => $usuarioId]);
            } catch (Throwable $e) {
            }
        }
        // Respostas e ranking ficam só como estatística anônima (o nome já foi apagado acima).
    }
}
