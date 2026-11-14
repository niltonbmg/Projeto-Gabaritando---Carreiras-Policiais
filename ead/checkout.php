<?php
// checkout.php — Página pública de compra (celular primeiro).
// ?p=ID: dados do comprador e forma de pagamento. ?pedido=CHAVE: Pix com QR na tela (confirma sozinho),
// ou botão para pagar cartão/boleto na página segura do Asaas. Os dados do cartão nunca passam por aqui.
declare(strict_types=1);

require_once __DIR__ . '/api/conexao.php';
require_once __DIR__ . '/includes/seguranca.php';
gab_seg_post_mesma_origem();
require_once __DIR__ . '/includes/pagamentos-lib.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = db();
pag_bootstrap($pdo);
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = static fn($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
$ehAdmin = (($_SESSION['usuario_tipo'] ?? $_SESSION['admin_tipo'] ?? '') === 'admin') || !empty($_SESSION['admin_usuario_id']);
$erro = '';

// Página do pedido (acompanhamento)
$pedido = null;
if (!empty($_GET['pedido'])) {
    $pedido = pag_pedido_por_chave($pdo, preg_replace('~[^a-f0-9]~', '', (string)$_GET['pedido']));
    if ($pedido && isset($_GET['situacao'])) { // consulta da página a cada poucos segundos
        $pedido = pag_sincronizar($pdo, $pedido);
        header('Content-Type: application/json');
        echo json_encode(['status' => $pedido['status']]);
        exit;
    }
}
$prod = !$pedido ? pag_produto($pdo, (int)($_GET['p'] ?? $_POST['p'] ?? 0)) : pag_produto($pdo, (int)$pedido['produto_id']);
$aberto = $prod && ($prod['status'] === 'publicado' || $ehAdmin);

if (!$pedido && $aberto && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $novo = pag_criar_pedido($pdo, $prod, $_POST);
        header('Location: checkout.php?pedido=' . $novo['chave_publica']);
        exit;
    } catch (PagErro $ex) {
        // aluno não vê detalhe técnico do Asaas: só a orientação (o detalhe vai para o log)
        error_log('checkout: ' . $ex->detalhe);
        $erro = $ex->detalhe === '' ? $ex->getMessage() : 'Não conseguimos gerar o pagamento agora. Confira seus dados e tente de novo em instantes.';
    } catch (Throwable $ex) {
        $erro = $ex->getMessage();
    }
}
$pix = null;
if ($pedido && $pedido['status'] === 'aguardando' && $pedido['forma'] === 'pix') {
    try {
        $pix = pag_pix($pdo, $pedido);
    } catch (Throwable $ex) {
        $erro = 'Não consegui gerar o Pix agora. Atualize a página em instantes.';
    }
}
[$precoCheio] = $prod ? pag_preco($prod, '') : [0];
$formas = $prod ? explode(',', (string)$prod['formas']) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $prod ? $e($prod['nome']) . ' | ' : ''; ?>Compra segura</title>
    <link rel="icon" type="image/png" href="/ead/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--fundo:#0c0c0d;--card:#161618;--card2:#1e1e21;--linha:#2a2a2e;--texto:#f3f3f4;--suave:#a3a3ab;--ouro:#fbb928;--ok:#4ade80;--erro:#ffb4a8}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--fundo);color:var(--texto);font-family:'Ubuntu',system-ui,sans-serif;line-height:1.5}
        .ck{max-width:560px;margin:0 auto;padding:20px 16px 48px;display:grid;gap:14px}
        .marca{display:flex;align-items:center;gap:10px;color:var(--suave);font-size:13px}.marca img{height:34px}
        .card{background:var(--card);border:1px solid var(--linha);border-radius:16px;padding:18px}
        h1{font-size:22px;line-height:1.25}.preco{font-size:30px;font-weight:700;color:var(--ouro)}.sub{color:var(--suave);font-size:14px}
        label{display:grid;gap:5px;font-size:14px;font-weight:500}
        input,select{font:inherit;font-size:16px;padding:12px 13px;border-radius:12px;border:1px solid var(--linha);background:var(--card2);color:var(--texto);width:100%}
        input:focus,select:focus{outline:2px solid var(--ouro);outline-offset:1px}
        .grid{display:grid;gap:12px}.dois{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .formas{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px}
        .formas label{border:2px solid var(--linha);border-radius:12px;padding:12px;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:700}
        .formas label:has(input:checked){border-color:var(--ouro);background:#2a2108}.formas input{width:auto}
        .bt{display:block;width:100%;border:0;border-radius:14px;padding:15px;font:inherit;font-size:17px;font-weight:700;background:var(--ouro);color:#111;cursor:pointer;text-align:center;text-decoration:none}
        .bt:disabled{opacity:.6}.erro{background:#3a1d1a;color:var(--erro);border-radius:12px;padding:12px 14px}
        .ok{background:#0f2a1a;color:var(--ok);border-radius:12px;padding:14px}
        .qr{display:grid;justify-items:center;gap:10px;text-align:center}.qr img{width:240px;height:240px;background:#fff;border-radius:12px;padding:8px}
        .copia{display:flex;gap:8px}.copia input{font-size:13px}.copia button{border:0;border-radius:12px;padding:0 14px;font-weight:700;background:var(--card2);color:var(--texto);border:1px solid var(--linha);cursor:pointer}
        .seguro{text-align:center;color:var(--suave);font-size:12px}
        .aguarda{display:flex;align-items:center;gap:8px;justify-content:center;color:var(--suave);font-size:14px}.aguarda i{width:10px;height:10px;border-radius:50%;background:var(--ouro);animation:p 1.2s infinite}
        @keyframes p{50%{opacity:.25}}@media(max-width:420px){.dois{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="ck">
    <div class="marca"><img src="/ead/favicon.png" alt=""><span>Compra segura · pagamento processado pelo Asaas</span></div>
    <?php if (!$prod || (!$aberto && !$pedido)): ?>
        <section class="card"><h1>Vendas encerradas</h1><p class="sub">Este link não está disponível agora. Fale com o suporte.</p></section>
    <?php elseif ($pedido): ?>
        <section class="card">
            <p class="sub"><?php echo $e($pedido['produto_nome']); ?></p>
            <h1>Pedido nº <?php echo (int)$pedido['id']; ?> · <?php echo $brl($pedido['valor']); ?><?php echo (int)$pedido['parcelas'] > 1 ? ' em ' . (int)$pedido['parcelas'] . 'x' : ''; ?></h1>
        </section>
        <?php if ($erro !== ''): ?><div class="erro" role="alert"><?php echo $e($erro); ?></div><?php endif; ?>
        <?php if ($pedido['status'] === 'pago'): ?>
            <section class="ok" role="status"><strong style="font-size:18px">✅ Pagamento confirmado!</strong><br>Seu curso já está liberado. Enviamos para <strong><?php echo $e($pedido['email']); ?></strong> o link para você criar sua senha e entrar.</section>
            <a class="bt" href="login.php">Ir para o login</a>
        <?php elseif ($pedido['status'] !== 'aguardando'): ?>
            <section class="erro">Este pedido está <?php echo $e($pedido['status']); ?>. Se precisar, faça uma nova compra ou fale com o suporte.</section>
        <?php elseif ($pedido['forma'] === 'pix'): ?>
            <section class="card qr">
                <strong>Pague com Pix</strong>
                <?php if ($pix && $pix['imagem'] !== ''): ?><img src="data:image/png;base64,<?php echo $e($pix['imagem']); ?>" alt="QR Code do Pix"><?php endif; ?>
                <p class="sub">Abra o app do seu banco, escolha Pix › QR Code, ou use o "copia e cola":</p>
                <div class="copia" style="width:100%"><input id="pixCopia" readonly value="<?php echo $e($pix['copia'] ?? ''); ?>"><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pixCopia').value);this.textContent='Copiado ✓'">Copiar</button></div>
                <div class="aguarda" aria-live="polite"><i></i><span id="aguardaTxt">Esperando o pagamento… a confirmação é automática.</span></div>
            </section>
        <?php else: ?>
            <section class="card grid">
                <p><?php echo $pedido['forma'] === 'cartao' ? 'Para sua segurança, o cartão é digitado na página protegida do Asaas.' : 'O boleto fica na página do Asaas: pague no app do banco ou numa lotérica. A liberação acontece quando o banco confirmar (até 3 dias úteis).'; ?></p>
                <a class="bt" href="<?php echo $e($pedido['link_pagamento']); ?>" target="_blank" rel="noopener"><?php echo $pedido['forma'] === 'cartao' ? '💳 Pagar com cartão' : '📄 Abrir o boleto'; ?></a>
                <div class="aguarda" aria-live="polite"><i></i><span id="aguardaTxt">Esta página se atualiza sozinha quando o pagamento for confirmado.</span></div>
            </section>
        <?php endif; ?>
        <?php if ($pedido['status'] === 'aguardando'): ?>
        <script>(function(){var t=setInterval(function(){fetch(location.pathname+'?pedido=<?php echo $e($pedido['chave_publica']); ?>&situacao=1',{cache:'no-store'}).then(function(r){return r.json();}).then(function(j){if(j.status&&j.status!=='aguardando'){clearInterval(t);location.reload();}}).catch(function(){});},5000);})();</script>
        <?php endif; ?>
    <?php else: ?>
        <section class="card">
            <?php if ($prod['status'] !== 'publicado'): ?><p class="erro" style="margin-bottom:10px">🧪 Prévia do admin: esta venda ainda não está publicada.</p><?php endif; ?>
            <p class="sub"><?php echo $e($prod['curso_nome']); ?></p>
            <h1><?php echo $e($prod['nome']); ?></h1>
            <div class="preco"><?php echo $brl($precoCheio); ?></div>
            <?php if (in_array('cartao', $formas, true) && (int)$prod['parcelas_max'] > 1): ?><p class="sub">ou até <?php echo (int)$prod['parcelas_max']; ?>x de <?php echo $brl($precoCheio / (int)$prod['parcelas_max']); ?> no cartão</p><?php endif; ?>
            <?php $fim = pag_data_fim($pdo, $prod); ?><p class="sub">Acesso <?php echo $fim ? 'até ' . date('d/m/Y', strtotime($fim)) : 'vitalício'; ?>, liberado assim que o pagamento for confirmado.</p>
        </section>
        <?php if ($erro !== ''): ?><div class="erro" role="alert"><?php echo $e($erro); ?></div><?php endif; ?>
        <form method="post" class="card grid" onsubmit="var b=this.querySelector('.bt');b.disabled=true;b.textContent='Gerando o pagamento…'">
            <input type="hidden" name="p" value="<?php echo (int)$prod['id']; ?>">
            <label>Nome completo<input name="nome" autocomplete="name" required value="<?php echo $e($_POST['nome'] ?? ''); ?>"></label>
            <label>E-mail (será o seu login)<input name="email" type="email" autocomplete="email" required value="<?php echo $e($_POST['email'] ?? ''); ?>"></label>
            <div class="dois">
                <label>CPF<input name="cpf" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" required value="<?php echo $e($_POST['cpf'] ?? ''); ?>"></label>
                <label>WhatsApp<input name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(71) 99999-9999" required value="<?php echo $e($_POST['whatsapp'] ?? ''); ?>"></label>
            </div>
            <div class="formas" role="radiogroup" aria-label="Forma de pagamento">
                <?php foreach (['pix' => '⚡ Pix', 'cartao' => '💳 Cartão', 'boleto' => '📄 Boleto'] as $k => $t): if (!in_array($k, $formas, true)) { continue; } ?>
                    <label><input type="radio" name="forma" value="<?php echo $k; ?>" <?php echo ($_POST['forma'] ?? $formas[0]) === $k ? 'checked' : ''; ?>> <?php echo $t; ?></label>
                <?php endforeach; ?>
            </div>
            <?php if (in_array('cartao', $formas, true) && (int)$prod['parcelas_max'] > 1): ?>
            <label id="parcelasBox">Parcelas no cartão<select name="parcelas"><?php for ($i = 1; $i <= (int)$prod['parcelas_max']; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?>x de <?php echo $brl($precoCheio / $i); ?></option><?php endfor; ?></select></label>
            <?php endif; ?>
            <?php if (!empty($prod['cupom_codigo'])): ?><label>Cupom de desconto (opcional)<input name="cupom" autocomplete="off" value="<?php echo $e($_POST['cupom'] ?? ''); ?>"></label><?php endif; ?>
            <button class="bt" type="submit">Continuar para o pagamento</button>
            <p class="seguro">🔒 Pagamento seguro pelo Asaas. Seus dados são usados só para a compra e o acesso (<a href="privacidade.php" style="color:var(--ouro)">privacidade</a>).</p>
        </form>
        <script>(function(){var b=document.getElementById('parcelasBox');if(!b)return;function u(){var c=document.querySelector('input[name=forma]:checked');b.style.display=c&&c.value==='cartao'?'':'none';}document.querySelectorAll('input[name=forma]').forEach(function(r){r.addEventListener('change',u);});u();})();</script>
    <?php endif; ?>
</main>
</body>
</html>
