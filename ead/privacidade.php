<?php
// privacidade.php — Política de Privacidade (LGPD, Lei 13.709/2018). Pública.
declare(strict_types=1);
require_once __DIR__ . '/includes/lgpd-lib.php';
require_once __DIR__ . '/includes/lgpd-pagina.php';
lgpd_pagina_inicio('Política de Privacidade');
$v = date('d/m/Y', strtotime(LGPD_VERSAO));
?>
<h1>Política de Privacidade</h1>
<p class="sub">Versão de <?php echo $v; ?> · Lei Geral de Proteção de Dados (Lei nº 13.709/2018 – LGPD)</p>
<div class="caixa">Em resumo: usamos seus dados <strong>só para você estudar e passar no concurso</strong>. Não vendemos dados. Você pode pedir uma cópia, corrigir ou excluir seus dados a qualquer momento em <a href="meus-dados.php">Meus dados</a>.</div>

<h2>1. Quem cuida dos seus dados</h2>
<p><strong>Controlador:</strong> Projeto Gabaritando – [RAZÃO SOCIAL], CNPJ [Nº DO CNPJ], [ENDEREÇO].<br>
<strong>Encarregado (DPO):</strong> <a href="mailto:<?php echo LGPD_EMAIL_ENCARREGADO; ?>"><?php echo LGPD_EMAIL_ENCARREGADO; ?></a>. Respondemos em até <?php echo LGPD_PRAZO_DIAS; ?> dias.</p>

<h2>2. Quais dados usamos, para quê e com que base legal</h2>
<table>
<tr><th>Dados</th><th>Para quê</th><th>Base legal (LGPD, art. 7º)</th></tr>
<tr><td>Nome, e-mail, telefone, senha (guardada criptografada), login com Google</td><td>Criar e proteger sua conta, liberar seus cursos, falar com você sobre o curso</td><td>Execução do contrato (V)</td></tr>
<tr><td>Progresso nas aulas, respostas de questões, desafios, cronograma, marcações, concurso e cargo escolhidos, rotina de estudo</td><td>Fazer o Método META funcionar: travas do ciclo, cronograma adaptativo, Ranking Individual, mentoria</td><td>Execução do contrato (V)</td></tr>
<tr><td>Redações (texto, foto ou PDF da folha) e correções</td><td>Correção pelo professor e pré-leitura pela IA</td><td>Execução do contrato (V)</td></tr>
<tr><td>Nome abreviado (ex.: “Maria S.”) no Ranking Geral do Desafio do Dia</td><td>Ranking e premiação do mês</td><td>Legítimo interesse (IX), com opção de sair do ranking em Meus dados</td></tr>
<tr><td>Registros de acesso (data, hora, IP)</td><td>Segurança e cumprimento do Marco Civil da Internet</td><td>Obrigação legal (II) – Lei 12.965/2014, art. 15</td></tr>
<tr><td>Dados de compra (feitos na plataforma de pagamento)</td><td>Liberar o curso, nota fiscal e obrigações fiscais</td><td>Execução do contrato (V) e obrigação legal (II)</td></tr>
</table>
<p>Não tratamos dados sensíveis (saúde, religião, origem racial etc.) e pedimos que você não os envie em redações ou mensagens.</p>

<h2>3. Inteligência artificial (o Antigão)</h2>
<ul>
<li>A plataforma usa IA (Google Gemini) para preparar aulas, videoaulas, podcasts, questões inéditas, sugestões de estudo e a <strong>pré-leitura de redações</strong>.</li>
<li>Para a pré-leitura, o texto ou a imagem da redação é enviado ao Google. <strong>Seu nome e e-mail não são enviados.</strong> Evite escrever dados pessoais na redação.</li>
<li><strong>Decisões automatizadas (art. 20):</strong> o cronograma se ajusta pelo seu desempenho, e a IA sugere uma nota de redação. A <strong>nota final é sempre decidida por um professor</strong>, e você pode pedir revisão de qualquer ajuste automático em Meus dados.</li>
<li>Conteúdo gerado por IA pode conter erros. Questões criadas pela IA são sempre identificadas como “Questão inédita do Antigão” e <strong>não são questões de prova</strong>.</li>
</ul>

<h2>4. Com quem compartilhamos</h2>
<p>Só com operadores necessários para o serviço, nunca para venda:</p>
<ul>
<li><strong>Hostinger</strong> – hospedagem do site e do banco de dados;</li>
<li><strong>Google</strong> – IA Gemini, login com Google e fontes do site; <strong>YouTube</strong> – vídeos das aulas;</li>
<li><strong>Plataforma de pagamento</strong> (ex.: Eduzz) – compra e liberação do curso;</li>
<li>Autoridades, quando a lei ou uma ordem judicial exigir.</li>
</ul>
<p><strong>Transferência internacional (art. 33):</strong> Google e YouTube podem processar dados fora do Brasil, em países e com cláusulas contratuais que garantem proteção adequada.</p>

<h2>5. Por quanto tempo guardamos</h2>
<ul>
<li>Conta e estudo: enquanto você tiver acesso e por até 2 anos depois, para você poder voltar; depois, anonimizamos.</li>
<li>Registros de acesso: 6 meses (Marco Civil da Internet).</li>
<li>Dados fiscais e de compra: pelo prazo da lei tributária (5 anos).</li>
<li>Pedido de exclusão: anonimizamos em até <?php echo LGPD_PRAZO_DIAS; ?> dias, salvo o que a lei obriga a guardar.</li>
</ul>

<h2>6. Seus direitos (art. 18)</h2>
<p>Confirmar que tratamos seus dados, acessar, corrigir, pedir anonimização ou exclusão, pedir portabilidade, saber com quem compartilhamos, revogar consentimento e pedir revisão de decisão automatizada. Tudo em <a href="meus-dados.php">Meus dados</a> ou pelo e-mail do encarregado. Você também pode reclamar à ANPD (gov.br/anpd).</p>

<h2>7. Segurança</h2>
<p>Senhas criptografadas, conexão segura (HTTPS), acesso restrito por perfil (aluno, professor, mentor), materiais com marca d’água e arquivos de redação protegidos por login. Se houver incidente de segurança relevante, avisamos você e a ANPD (art. 48).</p>

<h2>8. Cookies e armazenamento no navegador</h2>
<p>Usamos apenas o cookie de sessão (para manter você logado) e o armazenamento local do navegador para preferências (tamanho da letra, página do PDF). Não usamos cookies de publicidade.</p>

<h2>9. Menores de idade</h2>
<p>A plataforma é voltada a maiores de 18 anos (idade mínima dos concursos). Se você tem menos de 18, use com autorização do responsável.</p>

<h2>10. Mudanças nesta política</h2>
<p>Quando mudarmos algo importante, avisamos na plataforma e pedimos seu aceite de novo.</p>
<?php lgpd_pagina_fim();
