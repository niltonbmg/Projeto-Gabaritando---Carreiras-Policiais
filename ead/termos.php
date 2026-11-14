<?php
// termos.php — Termos de Uso. Público.
declare(strict_types=1);
require_once __DIR__ . '/includes/lgpd-lib.php';
require_once __DIR__ . '/includes/lgpd-pagina.php';
lgpd_pagina_inicio('Termos de Uso');
?>
<h1>Termos de Uso</h1>
<p class="sub">Versão de <?php echo date('d/m/Y', strtotime(LGPD_VERSAO)); ?> · Projeto Gabaritando – [RAZÃO SOCIAL], CNPJ [Nº DO CNPJ]</p>

<h2>1. O serviço</h2>
<p>Plataforma de preparação para concursos policiais e militares com o Método META: aulas, questões, simulados, redação, cronograma, mentoria e conteúdos gerados com apoio de inteligência artificial (o “Antigão”).</p>

<h2>2. Sua conta</h2>
<ul><li>A conta é <strong>pessoal e intransferível</strong>. Não compartilhe login e senha.</li>
<li>Você é responsável pelas informações que cadastra; mantenha-as corretas.</li></ul>

<h2>3. Uso do conteúdo</h2>
<ul><li>Aulas, PDFs, apostilas, videoaulas, podcasts e questões são para <strong>seu estudo pessoal</strong>.</li>
<li>É proibido copiar, revender, distribuir ou publicar o material. Os arquivos levam marca d’água de identificação.</li>
<li>Editais e provas oficiais são de domínio público e são oferecidos como apoio; a marca d’água não altera o conteúdo oficial.</li></ul>

<h2>4. Inteligência artificial</h2>
<ul><li>Parte do conteúdo é gerada ou revisada por IA e pode conter imprecisões. Em caso de dúvida, vale o texto da lei, do edital e a orientação do professor.</li>
<li>Questões criadas pela IA são identificadas como “Questão inédita do Antigão”: são de treino, no estilo da banca, <strong>não são questões oficiais</strong>.</li>
<li>Na redação, a IA apenas sugere; <strong>a nota final é do professor</strong>.</li></ul>

<h2>5. Desafio do Dia e premiação</h2>
<ul><li>Ranking por acertos e, no empate, pelo menor tempo. Só as questões do Desafio contam.</li>
<li>Aparece seu primeiro nome e a inicial do sobrenome; você pode sair do ranking em Meus dados.</li>
<li>Prêmios são definidos pela coordenação a cada mês. Fraude (contas múltiplas, ajuda de terceiros, automação) desclassifica.</li></ul>

<h2>6. Conduta</h2>
<p>Respeito no fórum, nas mensagens e com a equipe. Conteúdo ofensivo, discriminatório ou ilegal pode levar à suspensão da conta.</p>

<h2>7. Compra, arrependimento e cancelamento</h2>
<p>Compras on-line podem ser canceladas em até 7 dias (Código de Defesa do Consumidor, art. 49). Demais regras de cancelamento e reembolso seguem a oferta contratada.</p>

<h2>8. Disponibilidade</h2>
<p>Buscamos manter a plataforma no ar, mas podem ocorrer manutenções e falhas de terceiros (hospedagem, Google, YouTube). Não garantimos aprovação em concurso: o resultado depende do estudo de cada aluno.</p>

<h2>9. Privacidade</h2>
<p>O uso dos seus dados segue a <a href="privacidade.php">Política de Privacidade</a>.</p>

<h2>10. Foro</h2>
<p>Lei brasileira. Foro do domicílio do consumidor.</p>
<?php lgpd_pagina_fim();
