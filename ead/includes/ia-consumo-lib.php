<?php
// includes/ia-consumo-lib.php
// Consumo da IA com precisão:
//  - DEPOIS: cada chamada ao Google grava os tokens reais que ele devolve (usageMetadata) e o custo em US$/R$;
//  - ANTES: estimativa de cada ação pela média real das últimas execuções do mesmo tipo (faixa mín–máx);
//  - LIMITE: teto de gasto mensal em R$; ao atingir, a IA pausa sozinha (aviso em 80%).
// Preços ficam num arquivo editável pela tela "Consumo da IA" (se o Google mudar, muda num lugar só).
declare(strict_types=1);

if (!function_exists('ia_consumo_bootstrap')) {
    function ia_consumo_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ia_consumo (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            execucao VARCHAR(40) NOT NULL,
            acao VARCHAR(40) NOT NULL,
            ref_id INT UNSIGNED NOT NULL DEFAULT 0,
            modelo VARCHAR(60) NOT NULL,
            cota ENUM('texto','voz','busca') NOT NULL,
            tokens_entrada INT UNSIGNED NOT NULL DEFAULT 0,
            tokens_saida INT UNSIGNED NOT NULL DEFAULT 0,
            custo_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
            ok TINYINT(1) NOT NULL DEFAULT 1,
            KEY idx_consumo_data (criado_em),
            KEY idx_consumo_acao (acao, execucao),
            KEY idx_consumo_ref (acao, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Nomes das ações em português (aparecem nas telas). */
    function ia_consumo_acoes(): array
    {
        return [
            'videoaula' => 'Videoaula do Antigão', 'podcast' => 'Podcast', 'apostila' => 'Apostila da aula',
            'aula_ia' => 'Teoria escrita pelo Antigão', 'questoes' => 'Questões de um assunto (busca + criação)',
            'redacao' => 'Leitura de redação', 'conferir_pdf' => 'Conferência do PDF da aula', 'classificar' => 'Classificação automática',
            'desafio' => 'Montagem do Desafio do Dia', 'radar' => 'Radar do Concurso', 'duvidas' => 'Antigão tira-dúvidas', 'discursiva' => 'Treino de discursiva (criar e corrigir)', 'visual' => 'Visual novo das videoaulas', 'edital' => 'Leitura do edital / cadastro do concurso', 'outro' => 'Outras ações',
        ];
    }

    function ia_consumo_config_arquivo(): string
    {
        return dirname(__DIR__) . '/uploads/.ia-consumo-config.json';
    }

    /**
     * Preços oficiais do Gemini (US$ por 1 milhão de tokens) — CONFIRA em ai.google.dev/pricing e ajuste na tela.
     * A regra é escolhida pelo nome do modelo (primeira que casar).
     */
    function ia_consumo_config(): array
    {
        $padrao = [
            'plano' => 'gratis',           // 'gratis' = Google não cobra (valores são só "quanto custaria"); 'pago' = cobrança real
            'dolar' => 5.50,
            'limite_mensal_brl' => 0,      // 0 = sem limite
            'precos' => [
                ['contem' => 'tts', 'entrada' => 0.50, 'saida' => 10.00, 'rotulo' => 'Voz (TTS): saída é áudio'],
                ['contem' => 'lite', 'entrada' => 0.10, 'saida' => 0.40, 'rotulo' => 'Flash-Lite'],
                ['contem' => 'pro', 'entrada' => 1.25, 'saida' => 10.00, 'rotulo' => 'Pro'],
                ['contem' => '', 'entrada' => 0.30, 'saida' => 2.50, 'rotulo' => 'Flash (padrão)'],
            ],
            'busca_por_chamada' => 0.0,    // pesquisa no Google: grátis até 1.500/dia no plano pago; depois US$ 0,035 cada
        ];
        $c = json_decode((string)@file_get_contents(ia_consumo_config_arquivo()), true);
        return is_array($c) ? array_replace($padrao, $c) : $padrao;
    }

    function ia_consumo_salvar_config(array $c): void
    {
        @file_put_contents(ia_consumo_config_arquivo(), json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    function ia_consumo_preco(string $modelo): array
    {
        foreach (ia_consumo_config()['precos'] as $p) {
            if ((string)$p['contem'] === '' || stripos($modelo, (string)$p['contem']) !== false) {
                return $p;
            }
        }
        return ['entrada' => 0.30, 'saida' => 2.50];
    }

    function ia_consumo_custo_usd(string $modelo, string $cota, int $entrada, int $saida): float
    {
        $p = ia_consumo_preco($modelo);
        $c = $entrada / 1e6 * (float)$p['entrada'] + $saida / 1e6 * (float)$p['saida'];
        if ($cota === 'busca') {
            $c += (float)ia_consumo_config()['busca_por_chamada'];
        }
        return $c;
    }

    // ---------- contexto: qual ação está gastando ----------
    function ia_consumo_inicio(string $acao, int $refId = 0): string
    {
        $GLOBALS['gab_ia_ctx'] = ['acao' => $acao, 'ref' => $refId, 'exec' => $acao . '-' . $refId . '-' . substr(bin2hex(random_bytes(6)), 0, 12)];
        return $GLOBALS['gab_ia_ctx']['exec'];
    }

    function ia_consumo_contexto(): array
    {
        return $GLOBALS['gab_ia_ctx'] ?? ['acao' => 'outro', 'ref' => 0, 'exec' => 'avulso-' . date('YmdHi')];
    }

    function ia_consumo_db(): ?PDO
    {
        try {
            if (!function_exists('db')) {
                require_once dirname(__DIR__) . '/api/conexao.php';
            }
            return db();
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Chamado a cada resposta do Google (podcast_gemini_chamar). Nunca derruba a ação. */
    function ia_consumo_registrar(string $modelo, string $cota, array $uso, int $codigo): void
    {
        try {
            $pdo = ia_consumo_db();
            if (!$pdo) {
                return;
            }
            ia_consumo_bootstrap($pdo);
            $ctx = ia_consumo_contexto();
            $ent = (int)($uso['promptTokenCount'] ?? 0);
            $sai = (int)($uso['candidatesTokenCount'] ?? 0) + (int)($uso['thoughtsTokenCount'] ?? 0); // "pensamento" é cobrado como saída
            $ok = $codigo < 400 ? 1 : 0;
            $custo = $ok ? ia_consumo_custo_usd($modelo, $cota, $ent, $sai) : 0.0;
            $pdo->prepare('INSERT INTO ia_consumo (execucao, acao, ref_id, modelo, cota, tokens_entrada, tokens_saida, custo_usd, ok) VALUES (:e, :a, :r, :m, :c, :ti, :ts, :u, :ok)')
                ->execute([':e' => $ctx['exec'], ':a' => $ctx['acao'], ':r' => (int)$ctx['ref'], ':m' => mb_substr($modelo, 0, 60), ':c' => $cota, ':ti' => $ent, ':ts' => $sai, ':u' => $custo, ':ok' => $ok]);
        } catch (Throwable $e) {
            error_log('ia_consumo_registrar: ' . $e->getMessage());
        }
    }

    /** Gasto real de uma execução (para a mensagem "Concluído: gastou ..."). */
    function ia_consumo_da_execucao(PDO $pdo, string $exec): array
    {
        ia_consumo_bootstrap($pdo);
        $st = $pdo->prepare("SELECT COUNT(*) chamadas, SUM(cota='texto') texto, SUM(cota='voz') voz, SUM(cota='busca') busca,
                                    COALESCE(SUM(tokens_entrada + tokens_saida),0) tokens, COALESCE(SUM(custo_usd),0) usd FROM ia_consumo WHERE execucao = :e AND ok = 1");
        $st->execute([':e' => $exec]);
        $r = $st->fetch() ?: [];
        return ia_consumo_formatar((int)($r['chamadas'] ?? 0), (int)($r['texto'] ?? 0), (int)($r['voz'] ?? 0), (int)($r['busca'] ?? 0), (int)($r['tokens'] ?? 0), (float)($r['usd'] ?? 0));
    }

    /** R$ com precisão: abaixo de 10 centavos mostra 3 casas (R$ 0,006), para não virar "R$ 0,00". */
    function ia_consumo_brl(float $brl): string
    {
        return 'R$ ' . number_format($brl, $brl > 0 && $brl < 0.1 ? 3 : 2, ',', '.');
    }

    function ia_consumo_formatar(int $chamadas, int $texto, int $voz, int $busca, int $tokens, float $usd): array
    {
        $brl = $usd * (float)ia_consumo_config()['dolar'];
        return ['chamadas' => $chamadas, 'texto' => $texto, 'voz' => $voz, 'busca' => $busca, 'tokens' => $tokens, 'usd' => round($usd, 4), 'brl' => round($brl, 4),
            'resumo' => number_format($tokens, 0, ',', '.') . ' tokens · ' . $chamadas . ' chamada(s)' . ($voz ? " ($voz de voz)" : '') . ($busca ? " ($busca de pesquisa)" : '') . ' · ' . ia_consumo_brl($brl)];
    }

    /**
     * Estimativa ANTES da ação: média e faixa (mín–máx) das últimas 20 execuções reais do mesmo tipo.
     * Sem histórico ainda, usa uma referência inicial e avisa que é aproximada.
     */
    function ia_consumo_estimar(PDO $pdo, string $acao, int $quantidade = 1): array
    {
        ia_consumo_bootstrap($pdo);
        $st = $pdo->prepare("SELECT execucao, COUNT(*) chamadas, SUM(cota='texto') texto, SUM(cota='voz') voz, SUM(cota='busca') busca,
                                    SUM(tokens_entrada + tokens_saida) tokens, SUM(custo_usd) usd, MAX(criado_em) quando
                               FROM ia_consumo WHERE acao = :a AND ok = 1 GROUP BY execucao ORDER BY quando DESC LIMIT 20");
        $st->execute([':a' => $acao]);
        $ex = $st->fetchAll();
        $q = max(1, $quantidade);
        $dolar = (float)ia_consumo_config()['dolar'];
        if ($ex) {
            $n = count($ex);
            $med = static fn(string $k): float => array_sum(array_map(static fn($r) => (float)$r[$k], $ex)) / $n;
            $usd = array_map(static fn($r) => (float)$r['usd'], $ex);
            $base = 'média real das últimas ' . $n . ' execuç' . ($n > 1 ? 'ões' : 'ão');
            $r = ['texto' => $med('texto'), 'voz' => $med('voz'), 'busca' => $med('busca'), 'tokens' => $med('tokens'), 'usd' => $med('usd'), 'usd_min' => min($usd), 'usd_max' => max($usd)];
        } else {
            // Referência inicial (antes de existir histórico): medida nos testes da plataforma.
            $ref = ['videoaula' => [2, 14, 0, 60000, 0.10], 'podcast' => [2, 8, 0, 40000, 0.06], 'apostila' => [1, 0, 0, 30000, 0.02],
                'aula_ia' => [2, 0, 0, 25000, 0.02], 'questoes' => [3, 0, 4, 60000, 0.05], 'redacao' => [1, 0, 0, 8000, 0.01],
                'conferir_pdf' => [1, 0, 0, 15000, 0.005], 'classificar' => [1, 0, 0, 3000, 0.001], 'edital' => [2, 0, 1, 50000, 0.04]][$acao] ?? [1, 0, 0, 10000, 0.01];
            $base = 'referência inicial (ainda sem histórico desta ação; fica precisa após as primeiras execuções)';
            $r = ['texto' => $ref[0], 'voz' => $ref[1], 'busca' => $ref[2], 'tokens' => $ref[3], 'usd' => $ref[4], 'usd_min' => $ref[4] * 0.6, 'usd_max' => $ref[4] * 1.6];
        }
        $uso = function_exists('podcast_uso_resumo') ? podcast_uso_resumo() : [];
        $restam = [];
        foreach (['texto', 'voz', 'busca'] as $t) {
            if (isset($uso[$t]['limite']) && $uso[$t]['limite']) {
                $restam[$t] = max(0, (int)$uso[$t]['limite'] - (int)$uso[$t]['hoje']);
            }
        }
        $mes = ia_consumo_mes_brl($pdo);
        $lim = (float)ia_consumo_config()['limite_mensal_brl'];
        return [
            'acao' => ia_consumo_acoes()[$acao] ?? $acao, 'quantidade' => $q, 'base' => $base,
            'chamadas_texto' => (int)ceil($r['texto'] * $q), 'chamadas_voz' => (int)ceil($r['voz'] * $q), 'chamadas_busca' => (int)ceil($r['busca'] * $q),
            'tokens' => (int)round($r['tokens'] * $q),
            'brl' => round($r['usd'] * $q * $dolar, 4), 'brl_min' => round($r['usd_min'] * $q * $dolar, 4), 'brl_max' => round($r['usd_max'] * $q * $dolar, 4),
            'cota_gratis_restante' => $restam, 'renova' => (string)($uso['renova'] ?? ''),
            'mes_brl' => $mes, 'limite_mensal_brl' => $lim,
            'estoura_limite' => $lim > 0 && $mes + $r['usd_max'] * $q * $dolar > $lim,
        ];
    }

    function ia_consumo_mes_brl(PDO $pdo): float
    {
        ia_consumo_bootstrap($pdo);
        $usd = (float)$pdo->query("SELECT COALESCE(SUM(custo_usd),0) FROM ia_consumo WHERE criado_em >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
        return round($usd * (float)ia_consumo_config()['dolar'], 4);
    }

    /** Antes de cada chamada: estourou o teto do mês? Pausa (como a cota do Google) até o admin aumentar o limite. */
    function ia_consumo_checar_limite(string $cota): void
    {
        $lim = (float)ia_consumo_config()['limite_mensal_brl'];
        if ($lim <= 0 || ia_consumo_config()['plano'] !== 'pago') {
            return; // no plano grátis não há cobrança: quem limita é a cota diária do Google
        }
        static $ultimo = null;
        static $quando = 0;
        if ($ultimo === null || time() - $quando > 60) {
            $pdo = ia_consumo_db();
            $ultimo = $pdo ? ia_consumo_mes_brl($pdo) : 0.0;
            $quando = time();
        }
        if ($ultimo >= $lim && class_exists('GeminiCotaException')) {
            $e = new GeminiCotaException('Limite de gasto do mês atingido (R$ ' . number_format($lim, 2, ',', '.') . '). A IA está pausada; aumente o limite em Consumo da IA. [cota:' . $cota . ']');
            $e->tipo = $cota;
            $e->diario = true;
            throw $e;
        }
    }

    // ---------- PLANO PAGO: sem limites grátis aprendidos; só 429 real (pausa curta) e o limite mensal em R$ ----------
    function ia_plano_pago(): bool
    {
        static $memo = null;
        return $memo ??= (ia_consumo_config()['plano'] ?? 'gratis') === 'pago';
    }

    /**
     * "Retomar agora": apaga as pausas de cota (.ia-pausa-*) e zera os contadores de hoje.
     * $tudo = true também esquece os limites grátis aprendidos (troca de chave ou passou para o plano pago).
     */
    function ia_retomar_agora(bool $tudo = false): void
    {
        $up = dirname(__DIR__) . '/uploads/';
        foreach (['.ia-pausa-voz', '.ia-pausa-busca', '.ia-pausa-texto', '.ia-pausa'] as $a) {
            @unlink($up . $a);
        }
        if ($tudo) {
            @unlink($up . '.ia-uso.json');
            return;
        }
        $arq = $up . '.ia-uso.json';
        $d = json_decode((string)@file_get_contents($arq), true) ?: [];
        if (function_exists('podcast_uso_dia')) {
            unset($d[podcast_uso_dia()]);
        }
        @file_put_contents($arq, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** Chave do Google trocada no .env? Guarda só um resumo (hash curto) e, se mudou, limpa pausas e limites aprendidos. */
    function ia_conferir_chave(): void
    {
        static $feito = false;
        if ($feito || !function_exists('gab_env')) {
            return;
        }
        $feito = true;
        $chave = gab_env('GEMINI_API_KEY', '');
        if ($chave === '') {
            return;
        }
        $arq = dirname(__DIR__) . '/uploads/.ia-chave-hash';
        $hash = substr(hash('sha256', $chave), 0, 12);
        $antes = trim((string)@file_get_contents($arq));
        if ($antes !== $hash) {
            if ($antes !== '') {
                ia_retomar_agora(true);
            }
            @file_put_contents($arq, $hash);
        }
    }

    // ---------- CONTROLE RÍGIDO: o mentor decide o que a IA pode fazer e quanto por dia ----------
    function ia_controle_tipos(): array
    {
        return ['videoaula' => 'Videoaulas do Antigão', 'aula_ia' => 'Teoria escrita pelo Antigão', 'apostila' => 'Apostila (teoria a partir do vídeo/PDF)',
            'conferir_pdf' => 'Conferência dos PDFs', 'questoes' => 'Questões (busca e criação)', 'redacao' => 'Leitura de redações', 'duvidas' => 'Antigão tira-dúvidas (chat do aluno)', 'discursiva' => 'Treino de discursiva (aluno)', 'visual' => 'Visual novo das videoaulas prontas (só texto)',
            'classificar' => 'Classificação automática por assunto', 'radar' => 'Radar do Concurso (novidades na internet, 1x por dia)', 'podcast' => 'Podcasts (extra)', 'edital' => 'Cadastro do concurso (edital, provas, plano)'];
    }

    function ia_controle(): array
    {
        $c = json_decode((string)@file_get_contents(dirname(__DIR__) . '/uploads/.ia-controle.json'), true);
        $c = is_array($c) ? $c : [];
        $lig = [];
        foreach (ia_controle_tipos() as $t => $_) {
            $lig[$t] = array_key_exists($t, (array)($c['ligados'] ?? [])) ? (bool)$c['ligados'][$t] : true;
        }
        $manual = ['texto' => max(0, (int)($c['teto']['texto'] ?? 0)), 'voz' => max(0, (int)($c['teto']['voz'] ?? 0)), 'busca' => max(0, (int)($c['teto']['busca'] ?? 0))];
        $modo = in_array($c['modo'] ?? '', ['economico', 'normal', 'maximo'], true) ? $c['modo'] : 'normal';
        ia_conferir_chave();
        if (ia_plano_pago()) { // plano pago: nada de limite grátis aprendido; quem limita é o teto mensal em R$
            return ['ligados' => $lig, 'modo' => $modo, 'teto' => ['texto' => 0, 'voz' => 0, 'busca' => 0], 'teto_manual' => ['texto' => max(0, (int)($c['teto']['texto'] ?? 0)), 'voz' => max(0, (int)($c['teto']['voz'] ?? 0)), 'busca' => max(0, (int)($c['teto']['busca'] ?? 0))]];
        }
        // O mentor escolhe só o modo; o teto de cada dia sai do limite grátis que a plataforma aprendeu sozinha.
        $teto = [];
        foreach (ia_controle_limite_aprendido() as $t => $l) {
            $teto[$t] = $modo === 'maximo' ? 0 : ($modo === 'economico' ? max(1, (int)floor($l['limite'] / 2)) : $l['limite']);
        }
        return ['ligados' => $lig, 'modo' => $modo, 'teto' => $teto, 'teto_manual' => $manual];
    }

    /**
     * Limite grátis do Google aprendido pelos próprios dados (sem perguntar ao mentor):
     * nos dias em que o Google cortou, quantas chamadas passaram no dia (somando os modelos reservas).
     * Sem nenhum corte registrado ainda, testa 20% acima do padrão para descobrir até onde vai.
     */
    function ia_controle_limite_aprendido(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $padrao = ['texto' => 100, 'voz' => 15, 'busca' => 20];
        $d = json_decode((string)@file_get_contents(dirname(__DIR__) . '/uploads/.ia-uso.json'), true) ?: [];
        $hoje = function_exists('podcast_uso_dia') ? podcast_uso_dia() : '';
        $memo = [];
        foreach ($padrao as $t => $p) {
            $cortes = [];
            $maior = 0;
            foreach ($d as $dia => $x) {
                if ($dia === $hoje) {
                    continue;
                }
                $ok = (int)($x[$t]['ok'] ?? 0);
                $maior = max($maior, $ok);
                if (!empty($x[$t]['bloqueios'])) {
                    $cortes[] = $ok;
                }
            }
            $memo[$t] = $cortes
                ? ['limite' => max(1, max($cortes)), 'medido' => true]
                : ['limite' => max($p, (int)ceil($maior * 1.2)), 'medido' => false];
        }
        return $memo;
    }

    /** Nenhuma tarefa da fila passa de metade da cota do dia (a conferência de PDF não trava mais as videoaulas). */
    function ia_controle_tipos_no_limite(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $memo = [];
        if (ia_plano_pago()) {
            return $memo; // plano pago: sem divisão da cota grátis
        }
        $ctl = ia_controle();
        $pdo = ia_consumo_db();
        if (!$pdo) {
            return $memo;
        }
        try {
            $inicio = (new DateTimeImmutable('today', new DateTimeZone('America/Los_Angeles')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
            $st = $pdo->prepare("SELECT acao, cota, COUNT(*) n FROM ia_consumo WHERE criado_em >= :i AND ok = 1 GROUP BY acao, cota");
            $st->execute([':i' => $inicio]);
            foreach ($st as $r) {
                $teto = (int)($ctl['teto'][$r['cota']] ?? 0);
                $cota = $teto > 0 ? $teto : (int)(ia_controle_limite_aprendido()[$r['cota']]['limite'] ?? 0);
                if ($cota > 0 && (int)$r['n'] >= (int)ceil($cota / 2) && !in_array($r['acao'], ['duvidas', 'desafio', 'redacao', 'discursiva'], true)) {
                    $memo[] = (string)$r['acao'];
                }
            }
        } catch (Throwable $e) {
        }
        return $memo = array_values(array_unique($memo));
    }

    /** Uso de hoje por tarefa (para "gastou mais com"). */
    function ia_controle_uso_por_tarefa(): array
    {
        $pdo = ia_consumo_db();
        if (!$pdo) {
            return [];
        }
        try {
            $inicio = (new DateTimeImmutable('today', new DateTimeZone('America/Los_Angeles')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
            $st = $pdo->prepare("SELECT acao, cota, COUNT(*) n FROM ia_consumo WHERE criado_em >= :i AND ok = 1 GROUP BY acao, cota ORDER BY n DESC");
            $st->execute([':i' => $inicio]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function ia_controle_salvar(array $c): void
    {
        // mescla com o que já está salvo (ex.: a política de questões inéditas não some ao salvar o modo)
        $antes = json_decode((string)@file_get_contents(dirname(__DIR__) . '/uploads/.ia-controle.json'), true);
        $c = array_replace(is_array($antes) ? $antes : [], $c);
        @file_put_contents(dirname(__DIR__) . '/uploads/.ia-controle.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Política de questões inéditas (diretriz: questões REAIS primeiro; inédita só como complemento).
     * nunca | completar (até X% do total do assunto e só se houver menos de 10 reais) | livre.
     */
    function ia_politica_ineditas(): array
    {
        $c = json_decode((string)@file_get_contents(dirname(__DIR__) . '/uploads/.ia-controle.json'), true) ?: [];
        $modo = in_array($c['ineditas'] ?? '', ['nunca', 'completar', 'livre'], true) ? $c['ineditas'] : 'completar';
        return ['modo' => $modo, 'pct' => max(5, min(90, (int)($c['ineditas_pct'] ?? 30)))];
    }

    function ia_controle_ligado(string $tipo): bool
    {
        return ia_controle()['ligados'][$tipo] ?? true;
    }

    /** Tipos desligados, para a fila pular. */
    function ia_controle_desligados(): array
    {
        return array_keys(array_filter(ia_controle()['ligados'], static fn($v) => !$v));
    }

    /** Teto diário (0 = sem teto). Lança a mesma exceção da cota do Google: a tarefa volta para a fila e espera o dia seguinte. */
    function ia_controle_checar_teto(string $cota): void
    {
        $teto = ia_controle()['teto'][$cota] ?? 0;
        if ($teto <= 0 || !function_exists('podcast_uso_resumo')) {
            return;
        }
        $hoje = (int)(podcast_uso_resumo()[$cota]['hoje'] ?? 0);
        if ($hoje >= $teto && class_exists('GeminiCotaException')) {
            $e = new GeminiCotaException("Teto diário definido pelo mentor atingido ($hoje de $teto chamadas de $cota). Volta amanhã. [cota:$cota]");
            $e->tipo = $cota;
            $e->diario = true;
            throw $e;
        }
    }
}
