// testes/smoke.js — confere que as telas principais abrem sem erro de PHP, como aluno e como admin.
// Uso: BASE=http://127.0.0.1:8080/ead/ ALUNO=aluno@teste.com ADMIN=admin@teste.com SENHA=teste123 node testes/smoke.js
const { chromium } = require('playwright');
const B = process.env.BASE || 'http://127.0.0.1:8080/ead/';
const SENHA = process.env.SENHA || 'teste123';
const aluno = ['discursiva.php', 'meu-plano.php', 'meu-concurso.php', 'desafio.php', 'banco-questoes.php', 'redacoes.php', 'meus-dados.php', 'materiais.php', 'simulados.php', 'meu-cronograma.php', 'cadernos.php', 'flashcards.php', 'forum-duvidas.php', 'edital-verticalizado.php', 'agenda.php', 'recompensas.php', 'privacidade.php', 'termos.php'];
const admin = ['admin-dashboard.php', 'admin-editais.php', 'admin-cursos.php', 'admin-usuarios.php', 'admin-redacao.php', 'admin-lgpd.php', 'admin-desafio.php', 'admin-banco-questoes.php', 'admin-simulado.php', 'admin-materiais.php', 'correcao-redacao.php', 'saude.php', 'admin-revisao-aulas.php', 'admin-consumo-ia.php', 'admin-padrao.php', 'admin-inicio.php'];
const ruim = /(Fatal error|Parse error|Uncaught|Warning:|Notice:|Deprecated:|SQLSTATE)/;
let falhas = 0;
async function rodar(ctx, paginas, rotulo) {
  const p = await ctx.newPage();
  for (const pg of paginas) {
    const t = Date.now();
    const r = await p.goto(B + pg, { waitUntil: 'domcontentloaded' }).catch(e => null);
    const html = r ? await p.content() : '';
    const st = r ? r.status() : 0;
    const erro = (html.match(ruim) || [])[0];
    const ok = st > 0 && st < 400 && !erro;
    if (!ok) falhas++;
    console.log(`${ok ? 'OK  ' : 'FALHA'} ${rotulo} ${pg} ${st} ${Date.now() - t}ms ${erro || ''}`);
  }
}
(async () => {
  const b = await chromium.launch();
  const ca = await b.newContext(); const pa = await ca.newPage();
  await pa.goto(B + 'login.php'); await pa.fill('#login', process.env.ALUNO || 'aluno@teste.com'); await pa.fill('#senha', SENHA);
  await Promise.all([pa.waitForLoadState('networkidle'), pa.click('form.form button[type=submit]')]);
  // aceita os termos se aparecer a janela da LGPD
  const lg = await pa.$('form[action="meus-dados.php"] input[name=aceite]');
  if (lg) { await lg.check(); await Promise.all([pa.waitForLoadState('networkidle'), pa.click('form[action="meus-dados.php"] button[type=submit]')]); }
  await rodar(ca, aluno, 'aluno');
  const cd = await b.newContext(); const pd = await cd.newPage();
  await pd.goto(B + 'admin-wp.php'); await pd.fill('input[type=text],input[type=email]', process.env.ADMIN || 'admin@teste.com'); await pd.fill('input[type=password]', SENHA);
  await Promise.all([pd.waitForLoadState('networkidle'), pd.press('input[type=password]', 'Enter')]);
  await rodar(cd, admin, 'admin');
  await b.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
