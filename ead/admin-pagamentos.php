<?php
// admin-pagamentos.php — "Receber pagamentos": assistente em 3 passos para o mentor vender sozinho.
// 1) Conectar a conta Asaas  2) Produto (curso, preço, parcelas, formas, prazo, cupom)  3) Publicar (link + compra de teste).
declare(strict_types=1);

require_once __DIR__ . '/api/admin-auth.php';
auth_admin_require_login();
require_once __DIR__ . '/includes/admin-cursos-lib.php';
require_once __DIR__ . '/includes/pagamentos-lib.php';

$pdo = admin_cursos_db();
pag_bootstrap($pdo);
$e = static fn($s): string => admin_esc((string)$s);
$erro = '';
$erroDetalhe = '';
$ok = (string)($_GET['ok'] ?? '');
$adminEmail = (string)((auth_admin_user() ?? [])['email'] ?? '');
$produtoId = (int)($_GET['produto'] ?? $_POST['produto_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    try {
        if ($acao === 'conectar') {
            $r = pag_conectar($pdo, (string)($_POST['chave'] ?? ''), (string)($_POST['modo'] ?? 'teste'), trim((string)($_POST['email'] ?? '')) ?: $adminEmail);
            if ($r['aviso_ok']) {
                header('Location: admin-pagamentos.php?passo=2&ok=' . rawurlencode('Conta conectada: ' . $r['nome'] . ' (' . $r['doc'] . '). Os avisos de pagamento estão ativos.'));
                exit;
            }
            $erro = $r['aviso_erro'];
            $erroDetalhe = $r['aviso_detalhe'];
        }
        if ($acao === 'ativar_aviso') {
            pag_ativar_aviso($pdo, trim((string)($_POST['email'] ?? '')) ?: $adminEmail);
            header('Location: admin-pagamentos.php?passo=2&ok=' . rawurlencode('Avisos de pagamento ativos. Agora é só criar o produto.'));
            exit;
        }
        if ($acao === 'salvar_produto') {
            $curso = (int)($_POST['curso_id'] ?? 0);
            $preco = (float)str_replace(',', '.', str_replace('.', '', (string)($_POST['preco'] ?? '0')));
            if (str_contains((string)($_POST['preco'] ?? ''), ',') === false) {
                $preco = (float)($_POST['preco'] ?? 0);
            }
            $formas = array_values(array_intersect(['pix', 'cartao', 'boleto'], (array)($_POST['formas'] ?? [])));
            $tipo = in_array($_POST['acesso_tipo'] ?? '', ['padrao', 'vitalicio', 'dias', 'meses', 'data'], true) ? $_POST['acesso_tipo'] : 'padrao';
            if ($curso <= 0) { throw new RuntimeException('Escolha o curso.'); }
            if ($preco < 5) { throw new RuntimeException('O preço mínimo é R$ 5,00.'); }
            if (!$formas) { throw new RuntimeException('Marque pelo menos uma forma de pagamento.'); }
            $cupom = mb_strtoupper(trim((string)($_POST['cupom_codigo'] ?? '')));
            $vals = [':c' => $curso, ':n' => trim((string)($_POST['nome'] ?? '')) ?: 'Curso', ':p' => $preco, ':pm' => max(1, min(12, (int)($_POST['parcelas_max'] ?? 1))),
                ':f' => implode(',', $formas), ':t' => $tipo, ':q' => (int)($_POST['acesso_qtd'] ?? 0) ?: null,
                ':d' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($_POST['acesso_data'] ?? '')) ? $_POST['acesso_data'] : null,
                ':cc' => $cupom !== '' ? $cupom : null, ':ct' => $cupom !== '' ? (($_POST['cupom_tipo'] ?? 'pct') === 'valor' ? 'valor' : 'pct') : null,
                ':cv' => $cupom !== '' ? (float)str_replace(',', '.', (string)($_POST['cupom_valor'] ?? '0')) : null];
            if ($produtoId > 0) {
                $pdo->prepare('UPDATE venda_produtos SET curso_id = :c, nome = :n, preco = :p, parcelas_max = :pm, formas = :f, acesso_tipo = :t, acesso_qtd = :q, acesso_data = :d, cupom_codigo = :cc, cupom_tipo = :ct, cupom_valor = :cv WHERE id = :id')
                    ->execute($vals + [':id' => $produtoId]);
            } else {
                $pdo->prepare('INSERT INTO venda_produtos (curso_id, nome, preco, parcelas_max, formas, acesso_tipo, acesso_qtd, acesso_data, cupom_codigo, cupom_tipo, cupom_valor) VALUES (:c, :n, :p, :pm, :f, :t, :q, :d, :cc, :ct, :cv)')
                    ->execute($vals);
                $produtoId = (int)$pdo->lastInsertId();
            }
            header('Location: admin-pagamentos.php?passo=3&produto=' . $produtoId . '&ok=' . rawurlencode('Produto salvo. Agora é só publicar.'));
            exit;
        }
        if ($acao === 'publicar' || $acao === 'pausar') {
            $pdo->prepare('UPDATE venda_produtos SET status = :s WHERE id = :id')->execute([':s' => $acao === 'publicar' ? 'publicado' : 'pausado', ':id' => $produtoId]);
            header('Location: admin-pagamentos.php?passo=3&produto=' . $produtoId . '&ok=' . rawurlencode($acao === 'publicar' ? 'Venda publicada: o link já está funcionando.' : 'Venda pausada: o link mostra "vendas encerradas".'));
            exit;
        }
    } catch (PagErro $ex) {
        $erro = $ex->getMessage();
        $erroDetalhe = $ex->detalhe;
    } catch (Throwable $ex) {
        $erro = 'Algo deu errado. Tente de novo; se continuar, abra "ver detalhes" e envie ao suporte.';
        $erroDetalhe = $ex->getMessage();
    }
}

$cfg = pag_config($pdo);
$cursos = $pdo->query("SELECT id, nome FROM admin_cursos WHERE status = 'ativo' ORDER BY ordem, nome")->fetchAll(PDO::FETCH_ASSOC);
$produtos = $pdo->query('SELECT p.*, c.nome curso_nome, (SELECT COUNT(*) FROM pedidos o WHERE o.produto_id = p.id AND o.status = \'pago\') vendas FROM venda_produtos p JOIN admin_cursos c ON c.id = p.curso_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$prod = $produtoId > 0 ? pag_produto($pdo, $produtoId) : null;
$passo = (int)($_GET['passo'] ?? 0) ?: (!$cfg['conectado'] ? 1 : (!$produtos ? 2 : 3));
if ($passo === 3 && !$prod && $produtos) {
    $prod = pag_produto($pdo, (int)$produtos[0]['id']);
}
$feitos = ($cfg['conectado'] ? 1 : 0) + ($produtos ? 1 : 0) + (array_filter($produtos, static fn($p) => $p['status'] === 'publicado') ? 1 : 0);
$link = $prod ? pag_site_url() . '/checkout.php?p=' . (int)$prod['id'] : '';
$prazoTxt = static function (int $cursoId) use ($pdo): string {
    $d = gab_prazo_data_padrao($pdo, $cursoId);
    return $d ? 'até ' . date('d/m/Y', strtotime($d)) . ' (padrão do curso)' : 'vitalício (padrão do curso)';
};
$topbarInclude = admin_cursos_get_topbar();
$menuBarInclude = admin_cursos_get_menubar();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receber pagamentos | Projeto Gabaritando</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@0.469.0"></script>
    <?php admin_cursos_assets(); ?>
    <style>
        .rp-passos{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:8px}
        .rp-passos a{display:flex;gap:10px;align-items:center;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;color:#111;text-decoration:none}
        .rp-passos a.on{border-color:#fbb928;background:#fff8e6}.rp-passos b{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#f3f4f6;flex:0 0 28px}
        .rp-passos a.feito b{background:#067647;color:#fff}.rp-barra{height:8px;background:#eef0f3;border-radius:6px;overflow:hidden;margin-bottom:16px}.rp-barra i{display:block;height:100%;background:#067647}
        .rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.rp-grid label{display:grid;gap:4px;font-size:14px;font-weight:600}
        .rp-grid input,.rp-grid select,.rp-chave{font:inherit;padding:9px 10px;border:1px solid #d0d5dd;border-radius:9px;font-weight:400}
        .rp-modo{display:flex;gap:8px}.rp-modo label{flex:1;border:2px solid #e5e7eb;border-radius:12px;padding:12px;cursor:pointer}.rp-modo input{margin-right:6px}
        .rp-modo label:has(input:checked){border-color:#fbb928;background:#fff8e6}
        .rp-conta{background:#ecfdf3;border:1px solid #abefc6;border-radius:12px;padding:12px 14px;color:#065f46}
        .rp-link{display:flex;gap:8px;flex-wrap:wrap}.rp-link input{flex:1;min-width:240px;font:inherit;padding:10px;border:1px solid #d0d5dd;border-radius:9px;background:#f9fafb}
        .rp-ajuda{font-size:13px;color:#4b5563}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-top:1px solid #f3f4f6;font-size:13px}
        @media(max-width:700px){.rp-passos{grid-template-columns:1fr}}
    </style>
</head>
<body>
<?php if (is_file($topbarInclude)) { include $topbarInclude; } ?>
<div class="admin-dashboard-shell">
    <?php if (is_file($menuBarInclude)) { include $menuBarInclude; } ?>
    <main class="admin-dashboard-main">
        <section class="admin-page-header"><div class="admin-page-heading"><h1>💳 Receber pagamentos</h1><p>Venda seus cursos com Pix, cartão e boleto. O aluno paga e o curso é liberado sozinho, com o prazo de acesso certo.</p></div></section>
        <?php if ($ok !== ''): ?><div class="admin-alert admin-alert-success"><?php echo $e($ok); ?></div><?php endif; ?>
        <?php if ($erro !== ''): ?><div class="admin-alert admin-alert-error" style="display:block" role="alert"><?php echo $e($erro); ?><?php if ($erroDetalhe !== ''): ?><details style="margin-top:6px;font-weight:400"><summary style="cursor:pointer">ver detalhes</summary><code style="display:block;white-space:pre-wrap;font-size:12px;margin-top:4px"><?php echo $e($erroDetalhe); ?></code></details><?php endif; ?></div><?php endif; ?>

        <nav class="rp-passos" aria-label="Passos">
            <?php foreach ([1 => 'Conectar sua conta Asaas', 2 => 'Produto: curso e preço', 3 => 'Publicar e testar'] as $n => $t): $feito = $n === 1 ? $cfg['conectado'] : ($n === 2 ? (bool)$produtos : $feitos === 3); ?>
                <a href="admin-pagamentos.php?passo=<?php echo $n; ?><?php echo $prod && $n > 1 ? '&produto=' . (int)$prod['id'] : ''; ?>" class="<?php echo $passo === $n ? 'on' : ''; ?> <?php echo $feito ? 'feito' : ''; ?>"><b><?php echo $feito ? '✓' : $n; ?></b><?php echo $e($t); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="rp-barra" role="progressbar" aria-valuemin="0" aria-valuemax="3" aria-valuenow="<?php echo $feitos; ?>"><i style="width:<?php echo round($feitos * 100 / 3); ?>%"></i></div>

        <?php if ($passo === 1): ?>
        <section class="admin-panel">
            <h2>1. Conectar sua conta Asaas</h2>
            <?php if ($cfg['conectado']): ?><p class="rp-conta">✅ Conectado à conta <strong><?php echo $e($cfg['conta_nome']); ?></strong> (<?php echo $e($cfg['conta_doc']); ?>) · <?php echo $cfg['modo'] === 'real' ? '🟢 Modo real: cobranças de verdade' : '🧪 Modo teste: nenhum dinheiro de verdade'; ?></p>
                <?php if ($cfg['aviso_ok']): ?><p class="rp-conta" style="margin-top:6px">✅ Avisos de pagamento ativos: o curso é liberado sozinho quando o pagamento é confirmado.</p>
                <?php else: ?>
                <form method="post" style="margin-top:8px;background:#fff8e6;border:1px solid #fbb928;border-radius:12px;padding:12px 14px;display:grid;gap:8px">
                    <input type="hidden" name="acao" value="ativar_aviso">
                    <strong>⚠️ A chave foi validada, mas os avisos de pagamento ainda não estão ativos.</strong>
                    <span class="rp-ajuda">Sem eles, a plataforma só descobre o pagamento quando o aluno está na página do pedido. Não precisa colar a chave de novo.</span>
                    <label style="display:grid;gap:4px;font-weight:600;max-width:420px">E-mail para alertas do Asaas<input class="rp-chave" type="email" name="email" value="<?php echo $e($cfg['aviso_email'] ?? $adminEmail); ?>" placeholder="seu@email.com"></label>
                    <button class="admin-btn admin-btn-primary" type="submit" style="justify-self:start">🔁 Tentar de novo</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
            <ol class="rp-ajuda" style="margin:10px 0 14px 18px;line-height:1.7">
                <li>Entre na sua conta do Asaas (para testar, use a conta de teste em <strong>sandbox.asaas.com</strong>).</li>
                <li>Abra <strong>Integrações</strong> e clique em <strong>Gerar chave</strong>. Copie a chave inteira.</li>
                <li>Cole aqui embaixo, escolha o modo e clique em <strong>Testar e conectar</strong>.</li>
            </ol>
            <form method="post" style="display:grid;gap:12px">
                <input type="hidden" name="acao" value="conectar">
                <div class="rp-modo">
                    <label><input type="radio" name="modo" value="teste" <?php echo ($cfg['modo'] ?? 'teste') !== 'real' ? 'checked' : ''; ?>><strong>🧪 Modo teste</strong><br><small>Para experimentar. Ninguém paga de verdade.</small></label>
                    <label><input type="radio" name="modo" value="real" <?php echo ($cfg['modo'] ?? '') === 'real' ? 'checked' : ''; ?>><strong>🟢 Modo real</strong><br><small>Cobranças de verdade, dinheiro na sua conta.</small></label>
                </div>
                <label style="display:grid;gap:4px;font-weight:600">Chave da sua conta Asaas
                    <input class="rp-chave" name="chave" type="password" autocomplete="off" placeholder="<?php echo $cfg['conectado'] ? 'Já guardada com segurança. Cole outra só se for trocar.' : 'Cole aqui a chave copiada do Asaas'; ?>"></label>
                <label style="display:grid;gap:4px;font-weight:600">E-mail para alertas do Asaas<input class="rp-chave" type="email" name="email" value="<?php echo $e($cfg['aviso_email'] ?? $adminEmail); ?>" placeholder="seu@email.com"><small style="font-weight:400;color:#4b5563">O Asaas avisa neste e-mail se algum aviso de pagamento não chegar à plataforma.</small></label>
                <p class="rp-ajuda">🔒 A chave fica guardada criptografada e nunca aparece de novo na tela.</p>
                <button class="admin-btn admin-btn-primary" type="submit" style="justify-self:start">Testar e conectar</button>
            </form>
        </section>
        <?php elseif ($passo === 2): ?>
        <section class="admin-panel">
            <h2>2. Produto: o que você vai vender</h2>
            <?php if (!$cfg['conectado']): ?><p class="admin-alert admin-alert-error">Conecte sua conta Asaas primeiro (passo 1).</p><?php endif; ?>
            <?php $pv = $prod ?: ['curso_id' => 0, 'nome' => '', 'preco' => '', 'parcelas_max' => 12, 'formas' => 'pix,cartao,boleto', 'acesso_tipo' => 'padrao', 'acesso_qtd' => '', 'acesso_data' => '', 'cupom_codigo' => '', 'cupom_tipo' => 'pct', 'cupom_valor' => '']; $fm = explode(',', (string)$pv['formas']); ?>
            <form method="post">
                <input type="hidden" name="acao" value="salvar_produto"><input type="hidden" name="produto_id" value="<?php echo (int)($prod['id'] ?? 0); ?>">
                <div class="rp-grid">
                    <label>Curso
                        <select name="curso_id" required id="rpCurso"><option value="">Escolha…</option><?php foreach ($cursos as $c): ?><option value="<?php echo (int)$c['id']; ?>" data-prazo="<?php echo $e($prazoTxt((int)$c['id'])); ?>" <?php echo (int)$pv['curso_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo $e($c['nome']); ?></option><?php endforeach; ?></select></label>
                    <label>Nome na página de compra<input name="nome" value="<?php echo $e($pv['nome']); ?>" placeholder="Ex.: CFSD PMBA 2026 · acesso completo" required></label>
                    <label>Preço (R$)<input name="preco" inputmode="decimal" value="<?php echo $pv['preco'] !== '' ? $e(number_format((float)$pv['preco'], 2, ',', '')) : ''; ?>" placeholder="497,00" required></label>
                    <label>Parcelas no cartão (até)<select name="parcelas_max"><?php for ($i = 1; $i <= 12; $i++): ?><option <?php echo (int)$pv['parcelas_max'] === $i ? 'selected' : ''; ?>><?php echo $i; ?></option><?php endfor; ?></select></label>
                </div>
                <fieldset style="border:0;padding:0;margin:14px 0"><legend style="font-weight:600;margin-bottom:6px">Formas de pagamento</legend>
                    <?php foreach (['pix' => 'Pix (confirma na hora)', 'cartao' => 'Cartão de crédito', 'boleto' => 'Boleto'] as $k => $t): ?><label style="margin-right:16px"><input type="checkbox" name="formas[]" value="<?php echo $k; ?>" <?php echo in_array($k, $fm, true) ? 'checked' : ''; ?>> <?php echo $t; ?></label><?php endforeach; ?></fieldset>
                <div class="rp-grid">
                    <label>Prazo de acesso de quem comprar
                        <select name="acesso_tipo" onchange="document.getElementById('rpQtd').style.display=['dias','meses'].includes(this.value)?'':'none';document.getElementById('rpData').style.display=this.value==='data'?'':'none'">
                            <?php foreach (['padrao' => 'Usar o padrão do curso', 'vitalicio' => 'Vitalício', 'dias' => 'X dias a partir da compra', 'meses' => 'X meses a partir da compra', 'data' => 'Até uma data fixa'] as $k => $t): ?><option value="<?php echo $k; ?>" <?php echo $pv['acesso_tipo'] === $k ? 'selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?></select>
                        <small id="rpPrazoPadrao" style="font-weight:400;color:#4b5563"></small></label>
                    <label id="rpQtd" style="<?php echo in_array($pv['acesso_tipo'], ['dias', 'meses'], true) ? '' : 'display:none'; ?>">Quantidade<input type="number" min="1" name="acesso_qtd" value="<?php echo $e($pv['acesso_qtd']); ?>"></label>
                    <label id="rpData" style="<?php echo $pv['acesso_tipo'] === 'data' ? '' : 'display:none'; ?>">Acesso até<input type="date" name="acesso_data" value="<?php echo $e($pv['acesso_data']); ?>"></label>
                </div>
                <details style="margin:14px 0" <?php echo $pv['cupom_codigo'] ? 'open' : ''; ?>><summary style="cursor:pointer;font-weight:600">Cupom de desconto (opcional)</summary>
                    <div class="rp-grid" style="margin-top:8px">
                        <label>Código<input name="cupom_codigo" value="<?php echo $e($pv['cupom_codigo']); ?>" placeholder="Ex.: PMBA10"></label>
                        <label>Tipo<select name="cupom_tipo"><option value="pct" <?php echo $pv['cupom_tipo'] !== 'valor' ? 'selected' : ''; ?>>% de desconto</option><option value="valor" <?php echo $pv['cupom_tipo'] === 'valor' ? 'selected' : ''; ?>>R$ de desconto</option></select></label>
                        <label>Desconto<input name="cupom_valor" inputmode="decimal" value="<?php echo $e($pv['cupom_valor']); ?>" placeholder="10"></label>
                    </div></details>
                <button class="admin-btn admin-btn-primary" type="submit">Salvar e ir para publicar ▶</button>
            </form>
            <script>(function(){var s=document.getElementById('rpCurso'),o=document.getElementById('rpPrazoPadrao');function u(){var x=s.options[s.selectedIndex];o.textContent=x&&x.dataset.prazo?'Padrão: '+x.dataset.prazo:'';}s.addEventListener('change',u);u();})();</script>
        </section>
        <?php else: ?>
        <section class="admin-panel">
            <h2>3. Publicar e testar</h2>
            <?php if (!$prod): ?><p>Crie o produto no passo 2.</p><?php else: [$pr] = pag_preco($prod, ''); ?>
                <p style="font-size:15px"><strong><?php echo $e($prod['nome']); ?></strong> · <?php echo $e($prod['curso_nome']); ?> · R$ <?php echo number_format($pr, 2, ',', '.'); ?><?php echo (int)$prod['parcelas_max'] > 1 ? ' · até ' . (int)$prod['parcelas_max'] . 'x no cartão' : ''; ?> ·
                    <?php echo $prod['status'] === 'publicado' ? '<span style="color:#067647;font-weight:700">● publicado</span>' : '<span style="color:#b54708;font-weight:700">● ' . ($prod['status'] === 'pausado' ? 'pausado' : 'ainda não publicado') . '</span>'; ?></p>
                <div class="rp-link" style="margin:10px 0"><input id="rpLink" readonly value="<?php echo $e($link); ?>"><button type="button" class="admin-btn admin-btn-light" onclick="navigator.clipboard.writeText(document.getElementById('rpLink').value);this.textContent='Copiado ✓'">Copiar link</button></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <form method="post"><input type="hidden" name="produto_id" value="<?php echo (int)$prod['id']; ?>"><input type="hidden" name="acao" value="<?php echo $prod['status'] === 'publicado' ? 'pausar' : 'publicar'; ?>"><button class="admin-btn <?php echo $prod['status'] === 'publicado' ? 'admin-btn-light' : 'admin-btn-primary'; ?>" type="submit"><?php echo $prod['status'] === 'publicado' ? '⏸ Pausar vendas' : '🚀 Publicar'; ?></button></form>
                    <a class="admin-btn admin-btn-light" href="<?php echo $e($link); ?>" target="_blank">🧪 Fazer compra de teste</a>
                    <a class="admin-btn admin-btn-light" href="admin-pagamentos.php?passo=2&produto=<?php echo (int)$prod['id']; ?>">✏️ Editar produto</a>
                    <a class="admin-btn admin-btn-light" href="admin-vendas.php">📦 Ver pedidos</a>
                </div>
                <p class="rp-ajuda" style="margin-top:10px"><?php echo $cfg['modo'] === 'real' ? '🟢 Modo real: as compras são de verdade.' : '🧪 Modo teste: na compra de teste, pague o Pix pelo painel do Asaas de teste (Cobranças › a cobrança › "Confirmar pagamento") e veja o curso ser liberado.'; ?> O link funciona mesmo sem publicar só para você conferir; o aluno só compra depois de publicado.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($produtos): ?>
        <section class="admin-panel"><h2>Seus produtos</h2>
            <table><tr><th>Produto</th><th>Curso</th><th>Preço</th><th>Situação</th><th>Vendas</th><th></th></tr>
            <?php foreach ($produtos as $p): ?><tr><td><?php echo $e($p['nome']); ?></td><td><?php echo $e($p['curso_nome']); ?></td><td>R$ <?php echo number_format((float)$p['preco'], 2, ',', '.'); ?></td><td><?php echo $e($p['status']); ?></td><td><?php echo (int)$p['vendas']; ?></td><td><a href="admin-pagamentos.php?passo=3&produto=<?php echo (int)$p['id']; ?>">abrir</a></td></tr><?php endforeach; ?>
            </table>
            <p style="margin-top:8px"><a class="admin-btn admin-btn-light admin-btn-small" href="admin-pagamentos.php?passo=2&produto=0">+ Novo produto</a></p>
        </section>
        <?php endif; ?>
    </main>
</div>
<script>if (window.lucide) { lucide.createIcons(); }</script>
</body>
</html>
