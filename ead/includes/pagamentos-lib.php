<?php
// includes/pagamentos-lib.php — Venda com Asaas (fase 1): conectar a conta, produtos, pedidos, checkout,
// aviso de pagamento (libera/bloqueia o curso) e e-mail de boas-vindas com link para criar a senha.
// A chave do Asaas fica CRIPTOGRAFADA no banco (AES-256-GCM). Dados de cartão nunca passam por aqui:
// cartão e boleto são pagos na página segura do Asaas; o Pix aparece na nossa tela.
declare(strict_types=1);

require_once __DIR__ . '/curso-acesso.php';

const PAG_STATUS_PAGO = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];
const PAG_EVENTOS = ['PAYMENT_CREATED', 'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_OVERDUE', 'PAYMENT_DELETED', 'PAYMENT_REFUNDED',
    'PAYMENT_PARTIALLY_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_RESTORED'];

if (!class_exists('PagErro')) {
    /** Erro do pagamento: getMessage() é a frase para o mentor; $detalhe guarda o texto técnico (para "ver detalhes"). */
    class PagErro extends RuntimeException
    {
        public string $detalhe = '';

        public function __construct(string $amigavel, string $detalhe = '')
        {
            parent::__construct($amigavel);
            $this->detalhe = $detalhe;
        }
    }
}

if (!function_exists('pag_bootstrap')) {
    function pag_bootstrap(PDO $pdo): void
    {
        require_once __DIR__ . '/schema-cache.php';
        if (gab_schema_ok(__FUNCTION__, __FILE__)) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS pagamento_config (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            modo ENUM('teste','real') NOT NULL DEFAULT 'teste',
            chave_teste TEXT NULL, chave_real TEXT NULL,
            conta_nome VARCHAR(180) NULL, conta_doc VARCHAR(40) NULL,
            aviso_senha VARCHAR(80) NULL, aviso_id VARCHAR(80) NULL, aviso_url VARCHAR(500) NULL,
            atualizado_em DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS venda_produtos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            curso_id INT UNSIGNED NOT NULL,
            nome VARCHAR(180) NOT NULL,
            preco DECIMAL(10,2) NOT NULL,
            parcelas_max TINYINT UNSIGNED NOT NULL DEFAULT 1,
            formas VARCHAR(40) NOT NULL DEFAULT 'pix,cartao,boleto',
            acesso_tipo VARCHAR(10) NOT NULL DEFAULT 'padrao',
            acesso_qtd INT UNSIGNED NULL, acesso_data DATE NULL,
            cupom_codigo VARCHAR(40) NULL, cupom_tipo ENUM('pct','valor') NULL, cupom_valor DECIMAL(10,2) NULL,
            status ENUM('rascunho','publicado','pausado') NOT NULL DEFAULT 'rascunho',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            produto_id INT UNSIGNED NOT NULL, curso_id INT UNSIGNED NOT NULL, usuario_id BIGINT UNSIGNED NULL,
            nome VARCHAR(180) NOT NULL, email VARCHAR(190) NOT NULL, cpf VARCHAR(14) NOT NULL, whatsapp VARCHAR(20) NULL,
            forma ENUM('pix','cartao','boleto') NOT NULL, valor DECIMAL(10,2) NOT NULL, cupom VARCHAR(40) NULL, parcelas TINYINT UNSIGNED NOT NULL DEFAULT 1,
            modo ENUM('teste','real') NOT NULL DEFAULT 'teste',
            asaas_cliente VARCHAR(60) NULL, asaas_cobranca VARCHAR(60) NULL, link_pagamento VARCHAR(500) NULL,
            status ENUM('aguardando','pago','vencido','cancelado','estornado') NOT NULL DEFAULT 'aguardando',
            acesso_enviado_em DATETIME NULL, pago_em DATETIME NULL, criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, atualizado_em DATETIME NULL,
            chave_publica CHAR(32) NOT NULL,
            KEY idx_pedidos_status (status), KEY idx_pedidos_email (email), UNIQUE KEY uq_pedidos_cobranca (asaas_cobranca), UNIQUE KEY uq_pedidos_chave (chave_publica)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_eventos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            chave VARCHAR(120) NOT NULL, pedido_id INT UNSIGNED NULL, evento VARCHAR(60) NOT NULL, resumo VARCHAR(255) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_evento_chave (chave), KEY idx_evento_pedido (pedido_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS senha_convites (
            token_hash CHAR(64) NOT NULL PRIMARY KEY, usuario_id BIGINT UNSIGNED NOT NULL, expira_em DATETIME NOT NULL, usado_em DATETIME NULL,
            KEY idx_convite_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO pagamento_config (id) VALUES (1)");
        try {
            $pdo->exec('ALTER TABLE pagamento_config ADD COLUMN aviso_email VARCHAR(190) NULL');
        } catch (Throwable $e) {
            // já existe
        }
        gab_prazo_bootstrap($pdo);
    }

    // ---------- criptografia da chave ----------
    /** Chave de criptografia: PAGAMENTOS_CHAVE do .env; sem ela, derivada dos segredos que o .env já tem. */
    function pag_cripto_chave(): string
    {
        $base = gab_env('PAGAMENTOS_CHAVE', '');
        if ($base === '') {
            $base = gab_env('DB_PASSWORD', '') . '|' . gab_env('MANUTENCAO_TOKEN', '') . '|' . gab_env('DB_DATABASE', '') . '|gabaritando-pagamentos';
        }
        return hash('sha256', $base, true);
    }

    function pag_cifrar(string $texto): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($texto, 'aes-256-gcm', pag_cripto_chave(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $c);
    }

    function pag_decifrar(?string $guardado): string
    {
        if (!$guardado || !str_starts_with($guardado, 'v1:')) {
            return '';
        }
        $b = base64_decode(substr($guardado, 3), true) ?: '';
        if (strlen($b) < 29) {
            return '';
        }
        $t = openssl_decrypt(substr($b, 28), 'aes-256-gcm', pag_cripto_chave(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
        return $t === false ? '' : $t;
    }

    // ---------- configuração ----------
    function pag_config(PDO $pdo): array
    {
        pag_bootstrap($pdo);
        $c = $pdo->query('SELECT * FROM pagamento_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $c['chave'] = pag_decifrar($c['modo'] === 'real' ? ($c['chave_real'] ?? '') : ($c['chave_teste'] ?? ''));
        $c['conectado'] = $c['chave'] !== '' && !empty($c['conta_nome']);
        $c['aviso_ok'] = $c['conectado'] && !empty($c['aviso_id']) && !empty($c['aviso_senha']);
        return $c;
    }

    function pag_base_url(string $modo): string
    {
        $fixo = gab_env('ASAAS_BASE_URL', ''); // só para teste local (Asaas simulado)
        if ($fixo !== '') {
            return rtrim($fixo, '/');
        }
        return $modo === 'real' ? 'https://api.asaas.com/v3' : 'https://api-sandbox.asaas.com/v3';
    }

    /** Chamada ao Asaas. Lança RuntimeException com mensagem em português. */
    function pag_asaas(string $metodo, string $caminho, ?array $corpo, string $chave, string $modo): array
    {
        $ch = curl_init(pag_base_url($modo) . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: Plataforma-EAD', 'access_token: ' . $chave],
        ]);
        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
        }
        $resp = curl_exec($ch);
        $cod = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $cod === 0) {
            throw new PagErro('Não consegui falar com o Asaas agora. Tente de novo em instantes.', "$metodo $caminho: sem resposta");
        }
        $j = json_decode((string)$resp, true) ?: [];
        $tec = "$metodo $caminho → HTTP $cod: " . mb_substr(implode(' | ', array_map(static fn($x) => ($x['code'] ?? '') . ' ' . ($x['description'] ?? ''), (array)($j['errors'] ?? []))) ?: (string)$resp, 0, 600);
        if ($cod === 401) {
            throw new PagErro('O Asaas não aceitou a chave. Confira se copiou a chave inteira e se ela é do modo escolhido (teste ou real).', $tec);
        }
        if ($cod >= 400) {
            throw new PagErro('O Asaas não aceitou este pedido. Tente de novo; se continuar, abra "ver detalhes" e envie ao suporte.', $tec);
        }
        return $j;
    }

    function pag_mascarar_doc(string $doc): string
    {
        $d = preg_replace('~\D~', '', $doc);
        if (strlen($d) === 11) {
            return '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**';
        }
        if (strlen($d) === 14) {
            return '**.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-**';
        }
        return $d !== '' ? str_repeat('*', max(0, strlen($d) - 4)) . substr($d, -4) : '';
    }

    /** Endereço público do site (para o aviso de pagamento e os links do e-mail). */
    function pag_site_url(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/ead/x.php'))), '/');
        $dir = preg_replace('~/(api|includes)$~', '', $dir);
        return ($https ? 'https' : 'http') . '://' . $host . $dir;
    }

    /**
     * Passo 1: testa a chave e guarda criptografada (a conta fica conectada mesmo se o aviso falhar).
     * Depois ativa o aviso de pagamento. Retorna ['nome', 'doc', 'aviso_ok', 'aviso_erro', 'aviso_detalhe'].
     */
    function pag_conectar(PDO $pdo, string $chave, string $modo, string $emailMentor = ''): array
    {
        pag_bootstrap($pdo);
        $chave = trim($chave);
        $modo = $modo === 'real' ? 'real' : 'teste';
        if ($chave === '') {
            $c = pag_config($pdo);
            $chave = pag_decifrar($modo === 'real' ? ($c['chave_real'] ?? '') : ($c['chave_teste'] ?? ''));
            if ($chave === '') {
                throw new PagErro('Cole a chave da sua conta Asaas.');
            }
        }
        $conta = pag_asaas('GET', '/myAccount/commercialInfo/', null, $chave, $modo);
        $nome = (string)($conta['name'] ?? $conta['companyName'] ?? $conta['tradingName'] ?? '');
        $doc = (string)($conta['cpfCnpj'] ?? '');
        if ($nome === '') {
            throw new PagErro('A chave funcionou, mas não consegui ler os dados da conta. Confira no Asaas se a conta está aprovada.', json_encode($conta, JSON_UNESCAPED_UNICODE));
        }
        $col = $modo === 'real' ? 'chave_real' : 'chave_teste';
        $pdo->prepare("UPDATE pagamento_config SET modo = :m, $col = :k, conta_nome = :n, conta_doc = :d, aviso_id = NULL, atualizado_em = NOW() WHERE id = 1")
            ->execute([':m' => $modo, ':k' => pag_cifrar($chave), ':n' => mb_substr($nome, 0, 180), ':d' => pag_mascarar_doc($doc)]);
        $email = filter_var((string)($conta['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$conta['email'] : $emailMentor;
        $r = ['nome' => $nome, 'doc' => pag_mascarar_doc($doc), 'aviso_ok' => false, 'aviso_erro' => '', 'aviso_detalhe' => ''];
        try {
            pag_ativar_aviso($pdo, $email);
            $r['aviso_ok'] = true;
        } catch (PagErro $e) {
            $r['aviso_erro'] = $e->getMessage();
            $r['aviso_detalhe'] = $e->detalhe;
        }
        return $r;
    }

    /**
     * Ativa (ou refaz) o aviso de pagamento no Asaas com a chave já guardada: o Asaas avisa a plataforma quando o
     * pagamento é confirmado, vence ou é estornado. $email recebe os alertas do Asaas se o aviso falhar.
     */
    function pag_ativar_aviso(PDO $pdo, string $email = ''): void
    {
        $cfg = pag_config($pdo);
        if ($cfg['chave'] === '') {
            throw new PagErro('Conecte a conta Asaas primeiro.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try { // e-mail da própria conta Asaas
                $email = (string)(pag_asaas('GET', '/myAccount/commercialInfo/', null, $cfg['chave'], $cfg['modo'])['email'] ?? '');
            } catch (Throwable $e) {
            }
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new PagErro('Para ativar os avisos de pagamento, o Asaas pede um e-mail para alertas. Informe o seu e-mail abaixo e tente de novo.', 'sem e-mail válido para o campo email do aviso');
        }
        $senha = bin2hex(random_bytes(24));
        $url = pag_site_url() . '/api/pagamento-aviso.php';
        $corpo = ['name' => 'Plataforma EAD - pagamentos', 'url' => $url, 'email' => $email, 'enabled' => true, 'interrupted' => false,
            'apiVersion' => 3, 'authToken' => $senha, 'sendType' => 'SEQUENTIALLY', 'events' => PAG_EVENTOS];
        $existente = '';
        try {
            foreach ((array)(pag_asaas('GET', '/webhooks', null, $cfg['chave'], $cfg['modo'])['data'] ?? []) as $w) {
                if (($w['url'] ?? '') === $url) {
                    $existente = (string)($w['id'] ?? '');
                }
            }
        } catch (Throwable $e) {
        }
        try {
            $w = $existente !== '' ? pag_asaas('PUT', '/webhooks/' . $existente, $corpo, $cfg['chave'], $cfg['modo']) : pag_asaas('POST', '/webhooks', $corpo, $cfg['chave'], $cfg['modo']);
        } catch (PagErro $e) {
            throw new PagErro('A chave foi validada e a conta está conectada, mas não consegui ativar os avisos de pagamento no Asaas. Sem eles a liberação do curso fica mais lenta. Clique em "Tentar de novo".', $e->detalhe);
        }
        $pdo->prepare('UPDATE pagamento_config SET aviso_senha = :s, aviso_id = :w, aviso_url = :u, aviso_email = :e, atualizado_em = NOW() WHERE id = 1')
            ->execute([':s' => $senha, ':w' => (string)($w['id'] ?? $existente), ':u' => $url, ':e' => mb_substr($email, 0, 190)]);
    }

    // ---------- produtos ----------
    function pag_produto(PDO $pdo, int $id): ?array
    {
        pag_bootstrap($pdo);
        $st = $pdo->prepare('SELECT p.*, c.nome curso_nome, c.capa curso_capa FROM venda_produtos p JOIN admin_cursos c ON c.id = p.curso_id WHERE p.id = :id');
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Preço com cupom (cupom inválido = preço cheio). Retorna [valor, cupom aplicado ou '']. */
    function pag_preco(array $p, string $cupom): array
    {
        $preco = (float)$p['preco'];
        $cupom = mb_strtoupper(trim($cupom));
        if ($cupom !== '' && $cupom === mb_strtoupper((string)$p['cupom_codigo']) && (float)$p['cupom_valor'] > 0) {
            $preco = $p['cupom_tipo'] === 'pct' ? $preco * (1 - min(100, (float)$p['cupom_valor']) / 100) : $preco - (float)$p['cupom_valor'];
            return [max(5.0, round($preco, 2)), $cupom]; // Asaas: cobrança mínima R$ 5
        }
        return [$preco, ''];
    }

    /** Último dia de acesso para o produto (padrão do curso ou o do produto). NULL = vitalício. */
    function pag_data_fim(PDO $pdo, array $p): ?string
    {
        $base = new DateTimeImmutable('today');
        switch ($p['acesso_tipo']) {
            case 'vitalicio':
                return null;
            case 'dias':
                return (int)$p['acesso_qtd'] > 0 ? $base->modify('+' . (int)$p['acesso_qtd'] . ' days')->format('Y-m-d') : null;
            case 'meses':
                return (int)$p['acesso_qtd'] > 0 ? $base->modify('+' . (int)$p['acesso_qtd'] . ' months')->format('Y-m-d') : null;
            case 'data':
                return $p['acesso_data'] ?: null;
            default:
                return gab_prazo_data_padrao($pdo, (int)$p['curso_id']);
        }
    }

    // ---------- validação do comprador ----------
    function pag_cpf_valido(string $cpf): bool
    {
        $c = preg_replace('~\D~', '', $cpf);
        if (strlen($c) !== 11 || preg_match('~^(\d)\1{10}$~', $c)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $s = 0;
            for ($i = 0; $i < $t; $i++) {
                $s += (int)$c[$i] * ($t + 1 - $i);
            }
            if ((int)$c[$t] !== ((10 * $s) % 11) % 10) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cria o pedido e a cobrança no Asaas. Pix: QR na nossa tela. Cartão/boleto: página segura do Asaas.
     * Retorna o pedido (com chave_publica para a página de acompanhamento).
     */
    function pag_criar_pedido(PDO $pdo, array $p, array $d): array
    {
        $cfg = pag_config($pdo);
        if (!$cfg['conectado']) {
            throw new RuntimeException('As vendas ainda não estão abertas. Tente mais tarde.');
        }
        $nome = trim(mb_substr((string)($d['nome'] ?? ''), 0, 180));
        $email = mb_strtolower(trim((string)($d['email'] ?? '')));
        $cpf = preg_replace('~\D~', '', (string)($d['cpf'] ?? ''));
        $zap = preg_replace('~\D~', '', (string)($d['whatsapp'] ?? ''));
        $forma = in_array($d['forma'] ?? '', ['pix', 'cartao', 'boleto'], true) ? $d['forma'] : 'pix';
        if (mb_strlen($nome) < 5 || !str_contains($nome, ' ')) {
            throw new RuntimeException('Escreva seu nome completo.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Confira o e-mail.');
        }
        if (!pag_cpf_valido($cpf)) {
            throw new RuntimeException('Confira o CPF.');
        }
        if (strlen($zap) < 10 || strlen($zap) > 13) {
            throw new RuntimeException('Confira o WhatsApp com DDD.');
        }
        if (!in_array($forma, explode(',', (string)$p['formas']), true)) {
            throw new RuntimeException('Essa forma de pagamento não está disponível para este curso.');
        }
        [$valor, $cupom] = pag_preco($p, (string)($d['cupom'] ?? ''));
        $parcelas = $forma === 'cartao' ? max(1, min((int)$p['parcelas_max'], (int)($d['parcelas'] ?? 1))) : 1;

        // Cliente no Asaas (reaproveita pelo CPF)
        $cli = pag_asaas('GET', '/customers?cpfCnpj=' . $cpf, null, $cfg['chave'], $cfg['modo']);
        $cliId = (string)($cli['data'][0]['id'] ?? '');
        if ($cliId === '') {
            $cliId = (string)(pag_asaas('POST', '/customers', ['name' => $nome, 'email' => $email, 'cpfCnpj' => $cpf, 'mobilePhone' => $zap, 'notificationDisabled' => true], $cfg['chave'], $cfg['modo'])['id'] ?? '');
        }
        $chavePub = bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO pedidos (produto_id, curso_id, nome, email, cpf, whatsapp, forma, valor, cupom, parcelas, modo, asaas_cliente, chave_publica) VALUES (:p, :c, :n, :e, :cpf, :z, :f, :v, :cu, :pa, :m, :cli, :k)')
            ->execute([':p' => $p['id'], ':c' => $p['curso_id'], ':n' => $nome, ':e' => $email, ':cpf' => $cpf, ':z' => $zap, ':f' => $forma, ':v' => $valor, ':cu' => $cupom ?: null, ':pa' => $parcelas, ':m' => $cfg['modo'], ':cli' => $cliId, ':k' => $chavePub]);
        $pedidoId = (int)$pdo->lastInsertId();
        $cob = ['customer' => $cliId, 'billingType' => ['pix' => 'PIX', 'cartao' => 'CREDIT_CARD', 'boleto' => 'BOLETO'][$forma],
            'dueDate' => (new DateTimeImmutable($forma === 'boleto' ? '+3 days' : '+1 day'))->format('Y-m-d'),
            'description' => mb_substr($p['nome'], 0, 400), 'externalReference' => 'pedido-' . $pedidoId];
        if ($forma === 'cartao' && $parcelas > 1) {
            $cob['installmentCount'] = $parcelas;
            $cob['totalValue'] = $valor;
        } else {
            $cob['value'] = $valor;
        }
        $r = pag_asaas('POST', '/payments', $cob, $cfg['chave'], $cfg['modo']);
        $pdo->prepare('UPDATE pedidos SET asaas_cobranca = :a, link_pagamento = :l, atualizado_em = NOW() WHERE id = :id')
            ->execute([':a' => (string)($r['id'] ?? ''), ':l' => (string)($r['invoiceUrl'] ?? $r['bankSlipUrl'] ?? ''), ':id' => $pedidoId]);
        return pag_pedido_por_chave($pdo, $chavePub);
    }

    function pag_pedido_por_chave(PDO $pdo, string $chave): ?array
    {
        $st = $pdo->prepare('SELECT o.*, p.nome produto_nome FROM pedidos o JOIN venda_produtos p ON p.id = o.produto_id WHERE o.chave_publica = :k');
        $st->execute([':k' => $chave]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A conta tem chave Pix ativa? true / false / null (não deu para saber). */
    function pag_pix_chave_ativa(PDO $pdo): ?bool
    {
        $cfg = pag_config($pdo);
        if (!$cfg['conectado']) {
            return null;
        }
        try {
            $r = pag_asaas('GET', '/pix/addressKeys?status=ACTIVE', null, $cfg['chave'], $cfg['modo']);
            return ((int)($r['totalCount'] ?? 0) > 0) || !empty($r['data']);
        } catch (Throwable $e) {
            return null;
        }
    }

    function pag_pix(PDO $pdo, array $pedido): array
    {
        $cfg = pag_config($pdo);
        $r = pag_asaas('GET', '/payments/' . rawurlencode((string)$pedido['asaas_cobranca']) . '/pixQrCode', null, $cfg['chave'], $cfg['modo']);
        return ['imagem' => (string)($r['encodedImage'] ?? ''), 'copia' => (string)($r['payload'] ?? '')];
    }

    /**
     * Consulta o Asaas direto (quando o aviso ainda não chegou): se já foi pago, aplica pelo mesmo caminho do aviso.
     * Usado pela página do pedido a cada poucos segundos, no máximo 1 consulta a cada 8 s por pedido.
     */
    function pag_sincronizar(PDO $pdo, array $pedido): array
    {
        if ($pedido['status'] !== 'aguardando' || empty($pedido['asaas_cobranca'])) {
            return $pedido;
        }
        $marca = sys_get_temp_dir() . '/pag-sync-' . (int)$pedido['id'];
        if (is_file($marca) && filemtime($marca) > time() - 8) {
            return $pedido;
        }
        @touch($marca);
        try {
            $cfg = pag_config($pdo);
            $r = pag_asaas('GET', '/payments/' . rawurlencode((string)$pedido['asaas_cobranca']), null, $cfg['chave'], $cfg['modo']);
            $st = (string)($r['status'] ?? '');
            if (in_array($st, PAG_STATUS_PAGO, true)) {
                pag_processar_aviso($pdo, ['id' => 'consulta:' . $pedido['asaas_cobranca'] . ':' . $st, 'event' => 'PAYMENT_CONFIRMED', 'payment' => $r]);
            }
        } catch (Throwable $e) {
        }
        return pag_pedido_por_chave($pdo, (string)$pedido['chave_publica']) ?: $pedido;
    }

    // ---------- liberação / bloqueio ----------
    /** Conta do aluno: a que já existe pelo e-mail, ou uma nova (sem senha: ele cria pelo link do e-mail). */
    function pag_usuario(PDO $pdo, array $pedido): int
    {
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE LOWER(email) = :e LIMIT 1');
        $st->execute([':e' => mb_strtolower((string)$pedido['email'])]);
        if ($id = (int)$st->fetchColumn()) {
            return $id;
        }
        $cols = array_column($pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $dados = ['nome' => $pedido['nome'], 'email' => mb_strtolower((string)$pedido['email'])];
        $senhaAleatoria = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        foreach (['senha_hash', 'senha', 'password_hash'] as $c) {
            if (in_array($c, $cols, true)) {
                $dados[$c] = $senhaAleatoria;
                break;
            }
        }
        foreach (['telefone' => (string)$pedido['whatsapp'], 'cpf' => (string)$pedido['cpf'], 'tipo' => 'cliente', 'status' => 'ativo', 'created_at' => date('Y-m-d H:i:s')] as $c => $v) {
            if (in_array($c, $cols, true)) {
                $dados[$c] = $v;
            }
        }
        $pdo->prepare('INSERT INTO usuarios (`' . implode('`, `', array_keys($dados)) . '`) VALUES (' . implode(', ', array_fill(0, count($dados), '?')) . ')')->execute(array_values($dados));
        $novo = (int)$pdo->lastInsertId();
        gab_curso_acesso_marcar_usuario_sem_cursos($pdo, $novo, 'compra');
        return $novo;
    }

    /** Pagamento confirmado: conta + curso liberado com prazo + e-mail de acesso. Idempotente. */
    function pag_liberar(PDO $pdo, array $pedido): void
    {
        $uid = (int)($pedido['usuario_id'] ?: pag_usuario($pdo, $pedido));
        $p = pag_produto($pdo, (int)$pedido['produto_id']);
        $fim = $p ? pag_data_fim($pdo, $p) : gab_prazo_data_padrao($pdo, (int)$pedido['curso_id']);
        gab_prazo_bootstrap($pdo);
        $pdo->prepare("INSERT INTO usuario_cursos (usuario_id, curso_id, status, origem, criado_em, data_fim) VALUES (:u, :c, 'liberado', 'compra', NOW(), :f)
                       ON DUPLICATE KEY UPDATE status = 'liberado', origem = 'compra', data_fim = CASE WHEN data_fim IS NULL AND status IN ('liberado','ativo','pago','aprovado','matriculado') THEN NULL ELSE VALUES(data_fim) END, atualizado_em = NOW()")
            ->execute([':u' => $uid, ':c' => (int)$pedido['curso_id'], ':f' => $fim]);
        gab_cargo_alinhar($pdo, $uid, (int)$pedido['curso_id']); // cargo = cargo do curso comprado
        $ja = (string)($pedido['status'] ?? '') === 'pago';
        $pdo->prepare("UPDATE pedidos SET usuario_id = :u, status = 'pago', pago_em = COALESCE(pago_em, NOW()), atualizado_em = NOW() WHERE id = :id")->execute([':u' => $uid, ':id' => (int)$pedido['id']]);
        if (!$ja || empty($pedido['acesso_enviado_em'])) {
            pag_enviar_acesso($pdo, (int)$pedido['id']);
        }
    }

    /** Estorno, reembolso ou contestação: bloqueia o curso (o progresso fica guardado). */
    function pag_bloquear(PDO $pdo, array $pedido, string $status): void
    {
        if (!empty($pedido['usuario_id'])) {
            $pdo->prepare("UPDATE usuario_cursos SET status = 'cancelado', atualizado_em = NOW() WHERE usuario_id = :u AND curso_id = :c")
                ->execute([':u' => (int)$pedido['usuario_id'], ':c' => (int)$pedido['curso_id']]);
        }
        $pdo->prepare('UPDATE pedidos SET status = :s, atualizado_em = NOW() WHERE id = :id')->execute([':s' => $status, ':id' => (int)$pedido['id']]);
    }

    /** Link para criar/redefinir a senha (72 h). */
    function pag_convite_senha(PDO $pdo, int $uid): string
    {
        pag_bootstrap($pdo);
        $tok = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO senha_convites (token_hash, usuario_id, expira_em) VALUES (:h, :u, NOW() + INTERVAL 72 HOUR)')->execute([':h' => hash('sha256', $tok), ':u' => $uid]);
        return pag_site_url() . '/definir-senha.php?t=' . $tok;
    }

    /** E-mail de boas-vindas com o link de acesso. Retorna se o servidor aceitou o envio. */
    function pag_enviar_acesso(PDO $pdo, int $pedidoId): bool
    {
        $st = $pdo->prepare('SELECT o.*, c.nome curso FROM pedidos o JOIN admin_cursos c ON c.id = o.curso_id WHERE o.id = :id');
        $st->execute([':id' => $pedidoId]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o || !$o['usuario_id']) {
            return false;
        }
        $link = pag_convite_senha($pdo, (int)$o['usuario_id']);
        $primeiro = explode(' ', trim((string)$o['nome']))[0];
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#111"><h2>Bem-vindo(a), ' . $e($primeiro) . '!</h2>'
            . '<p>Seu pagamento foi confirmado e o curso <strong>' . $e($o['curso']) . '</strong> já está liberado.</p>'
            . '<p>Clique no botão para criar sua senha e entrar (o link vale por 72 horas):</p>'
            . '<p><a href="' . $e($link) . '" style="display:inline-block;background:#fbb928;color:#111;padding:12px 20px;border-radius:10px;font-weight:bold;text-decoration:none">Criar minha senha e acessar</a></p>'
            . '<p style="color:#555;font-size:13px">Seu login é este e-mail: ' . $e($o['email']) . '. Se o link vencer, use "Reenviar acesso" com o suporte.</p></div>';
        $host = parse_url(pag_site_url(), PHP_URL_HOST) ?: 'localhost';
        $ok = false;
        if (gab_env('EMAIL_DESATIVADO', '') === '') {
            $ok = @mail((string)$o['email'], '=?UTF-8?B?' . base64_encode('Seu acesso ao curso ' . $o['curso']) . '?=', $html,
                "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Plataforma <nao-responda@" . $host . ">\r\n");
        } else {
            @file_put_contents(dirname(__DIR__) . '/uploads/.ultimo-email.html', $html); // teste local
            $ok = true;
        }
        $pdo->prepare('UPDATE pedidos SET acesso_enviado_em = NOW() WHERE id = :id')->execute([':id' => $pedidoId]);
        return $ok;
    }

    /**
     * Aviso do Asaas (já com a senha conferida). Idempotente: o mesmo aviso não é aplicado duas vezes.
     * Retorna o que foi feito (para o log).
     */
    function pag_processar_aviso(PDO $pdo, array $j): string
    {
        pag_bootstrap($pdo);
        $evento = (string)($j['event'] ?? '');
        $pay = (array)($j['payment'] ?? []);
        $cob = (string)($pay['id'] ?? '');
        if ($evento === '' || $cob === '') {
            return 'ignorado';
        }
        $chave = (string)($j['id'] ?? ($cob . ':' . $evento . ':' . ($pay['status'] ?? '')));
        $st = $pdo->prepare('SELECT * FROM pedidos WHERE asaas_cobranca = :c OR (asaas_cobranca IS NULL AND CONCAT("pedido-", id) = :r) LIMIT 1');
        $st->execute([':c' => $cob, ':r' => (string)($pay['externalReference'] ?? '')]);
        $pedido = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        try {
            $pdo->prepare('INSERT INTO pedido_eventos (chave, pedido_id, evento, resumo) VALUES (:k, :p, :e, :r)')
                ->execute([':k' => mb_substr($chave, 0, 120), ':p' => $pedido['id'] ?? null, ':e' => mb_substr($evento, 0, 60), ':r' => mb_substr((string)($pay['status'] ?? '') . ' ' . ($pay['value'] ?? ''), 0, 255)]);
        } catch (PDOException $e) {
            return 'repetido'; // já processado
        }
        if (!$pedido) {
            return 'sem pedido';
        }
        if (in_array($evento, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RESTORED'], true) || in_array((string)($pay['status'] ?? ''), PAG_STATUS_PAGO, true) && $evento !== 'PAYMENT_REFUNDED') {
            pag_liberar($pdo, $pedido);
            return 'liberado';
        }
        if (in_array($evento, ['PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE'], true)) {
            pag_bloquear($pdo, $pedido, 'estornado');
            return 'bloqueado';
        }
        if ($evento === 'PAYMENT_OVERDUE' && $pedido['status'] === 'aguardando') {
            $pdo->prepare("UPDATE pedidos SET status = 'vencido', atualizado_em = NOW() WHERE id = :id")->execute([':id' => (int)$pedido['id']]);
            return 'vencido';
        }
        if ($evento === 'PAYMENT_DELETED' && $pedido['status'] === 'aguardando') {
            $pdo->prepare("UPDATE pedidos SET status = 'cancelado', atualizado_em = NOW() WHERE id = :id")->execute([':id' => (int)$pedido['id']]);
            return 'cancelado';
        }
        return 'registrado';
    }
}
