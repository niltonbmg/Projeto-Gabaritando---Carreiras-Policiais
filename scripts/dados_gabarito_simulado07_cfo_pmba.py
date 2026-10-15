"""
Gabarito comentado – Simulado 07 – CFO PMBA (Oficial QOPM).

Mesma estrutura de dados_gabarito_simulado06_cfo_pmba.py.
"""

NUMERO = "07"
Q = []


def q(n, disc, tema, gab, com, alts, dica, obs=None):
    Q.append(dict(n=n, disc=disc, tema=tema, gab=gab, com=com, alts=alts, dica=dica, obs=obs))


LP, ING, HUM, MAT, INF, DIR = ("Língua Portuguesa", "Língua Inglesa", "Ciências Humanas",
                               "Matemática", "Informática", "Direito")

# ===================== LÍNGUA PORTUGUESA =====================
q(1, LP, "Pontuação", "A",
  "“(que nem sempre é justa)” é oração adjetiva explicativa intercalada; pode ser isolada por "
  "parênteses, travessões ou vírgulas sem prejuízo da estrutura.",
  {"A": "CORRETA. Os três sinais isolam intercalações explicativas.",
   "B": "Errada. Nos dois casos as aspas marcam citação direta de autor (Comte-Sponville e Ihering).",
   "C": "Errada. A oração “se não corresponder…” está intercalada entre sujeito e verbo: vírgulas obrigatórias.",
   "D": "Errada. É pergunta retórica, respondida logo em seguida pelo próprio autor.",
   "E": "Errada. O ponto e vírgula separa orações já com vírgulas internas; a troca prejudica a clareza."},
  "Oração explicativa intercalada aceita vírgulas, travessões ou parênteses.")

q(2, LP, "Regência, colocação e formação de palavras", "C",
  "“Desconsideração” e “desaprendeu” formam-se por prefixação com “des-”; no primeiro o prefixo "
  "indica negação/ausência (falta de consideração), no segundo, ação contrária/reversão "
  "(deixar de saber o que se aprendeu).",
  {"A": "Errada. “Corresponder a” é VTI; “adaptar algo a algo” é VTDI.",
   "B": "Errada. Sem a vírgula, o advérbio “Assim” atrairia o pronome: “Assim se pode supor”.",
   "C": "CORRETA.",
   "D": "Errada. “Considera” concorda com Ihering; “disponha”, com “todo homem”.",
   "E": "Errada. São usados em sentido figurado, mas não sugerem “armadilha”."},
  "Advérbio sem vírgula atrai o pronome oblíquo (próclise).")

q(3, LP, "Tese e progressão argumentativa", "B",
  "O texto conceitua justiça (não se resume à legalidade), apoia-se em Comte-Sponville e Ihering "
  "(outros enunciadores) e defende a necessidade da LUTA para consolidar o direito e “derrubar os diques”.",
  {"A": "Errada. O texto não começa por essa proposição.",
   "B": "CORRETA.",
   "C": "Errada. Não há comparação de ideologias nesses termos.",
   "D": "Errada. O texto valoriza a força da luta humana.",
   "E": "Errada. As citações sustentam a tese (argumento de autoridade)."},
  "Citações de autores costumam ser argumento de autoridade a favor da tese.")

q(4, LP, "Coesão referencial", "A",
  "(V) “Ela” retoma “igualdade”, em contraponto às “desigualdades de fato”. (V) “lhe” retoma “Cada "
  "conquista”. (F) “se” é conjunção condicional, não exprime dúvida. (F) “Aqueles” refere-se aos que "
  "pretendem manter privilégios, não aos “diques”. (V) “que” retoma “inspiração conservadora e "
  "neoliberal”, na crítica a quem rotula a reivindicação como subversão.",
  {"A": "CORRETA. V V F F V.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Para checar referentes, substitua o pronome pelo termo e veja se o sentido se mantém.",
  obs="O 5º item é discutível (o “que” é explicativo); a sequência A é a única compatível com os demais.")

q(5, LP, "Ortografia", "B",
  "Grafias corretas: disciplina, apreensões, ressocialização, psicólogos.",
  {"A": "Errada. Atribuições, audiências e desempenham estão corretas.",
   "B": "CORRETA. Todas com erro.",
   "C": "Errada. Todas corretas.",
   "D": "Errada. Só “diciplina” está errada.",
   "E": "Errada. “Desempenham” e “audiências” estão corretas."},
  "Disciplina (sc), psicólogo (ps), ressocialização (ss + c).")

q(6, LP, "Preposição exigida (valor gramatical)", "C",
  "Em “certeza DE que…”, a preposição é exigida pelo substantivo “certeza” (complemento nominal em "
  "forma de oração). Nas demais, “de” forma locuções adjetivas, com valor nocional.",
  {"A": "Errada. “Malas de dólares” = conteúdo.",
   "B": "Errada. “Mulher de malandro” = relação/posse.",
   "C": "CORRETA. Quem tem certeza, tem certeza DE algo.",
   "D": "Errada. “Dia de mudança” = especificação.",
   "E": "Errada. “Boca de uma mulher” = posse."},
  "Preposição “gramatical” = regida por termo anterior (complemento).")

q(7, LP, "Advérbio modalizador", "C",
  "“Infelizmente” não indica circunstância; expressa a avaliação/estado psicológico do locutor "
  "diante do fato — é advérbio modalizador.",
  {"A": "Errada. “Quanto” é intensificador.", "B": "Errada. “Melhor” = modo.",
   "C": "CORRETA.", "D": "Errada. “Às vezes” = frequência.", "E": "Errada. “Contudo” é conjunção."},
  "Modalizadores: felizmente, infelizmente, certamente, talvez, francamente.")

q(8, LP, "Semântica – especialidades médicas", "B",
  "O obstetra é o médico que acompanha gestação e parto.",
  {"A": "Errada. Doenças da pele = dermatologista.", "B": "CORRETA.",
   "C": "Errada. Urologista trata do aparelho urinário.",
   "D": "Errada. Aparelho respiratório = pneumologista.",
   "E": "Errada. Fígado = hepatologista/gastroenterologista."},
  "Atenção aos radicais gregos: gineco (mulher), derma (pele), uro (urina), pneumo (pulmão).")

q(9, LP, "Análise da frase – pontuação e sentido", "B",
  "A vírgula indica duas ações coordenadas (ir aos cemitérios e admirar os túmulos), e não uma só "
  "ação final (“ir admirar”).",
  {"A": "Errada. “Ir a” é a regência culta; “ir aos cemitérios” está correto.",
   "B": "CORRETA.",
   "C": "Errada. O sujeito é “eu”, desinencial.",
   "D": "Errada. O artigo se deve à especificação posterior (“dos que venceram”).",
   "E": "Errada. “Cemitério” vem do grego koimetérion (dormitório); “-tério” não significa morte."},
  "Verbo de movimento “ir” pede a preposição “a”.")

q(10, LP, "Parônimos", "E",
  "Fluir = correr (líquido); fruir = desfrutar. A água “fluía”: emprego correto.",
  {"A": "Errada. O correto é “absolver” (inocentar).",
   "B": "Errada. O correto é “apreendeu” (recolheu).",
   "C": "Errada. O correto é “dilatar” (prorrogar).",
   "D": "Errada. Local de guardar alimentos = “despensa”.",
   "E": "CORRETA."},
  "Apreender (polícia) x aprender (estudo); despensa (alimentos) x dispensa (liberação).")

q(11, LP, "Polissemia – mesmo vocábulo, sentidos diferentes", "B",
  "O primeiro “saber” é verbo (ter conhecimento) e o último é substantivo (conhecimento, sabedoria): "
  "mudam classe e sentido.",
  {"A": "Errada. “Sábio” mantém o sentido.", "B": "CORRETA.",
   "C": "Errada. “Problema” mantém o sentido.", "D": "Errada. “Confiam” mantém o sentido.",
   "E": "Errada. Os termos se repetem com o mesmo sentido."},
  "Mudança de classe gramatical costuma indicar mudança de sentido.")

q(12, LP, "Sintaxe – período e funções", "B",
  "“Disse ele a Sua Majestade” é oração intercalada (de citação), não agente da passiva. Não há voz passiva.",
  {"A": "Correta. Há mais de uma oração.",
   "B": "INCORRETA (gabarito).",
   "C": "Correta. “O” e “meu” são adjuntos adnominais; “o meu universo” é predicativo.",
   "D": "Correta. “Dizer algo a alguém” — bitransitivo, com OI “a Sua Majestade”.",
   "E": "Correta. Sujeito “ele”; predicado verbal."},
  "Agente da passiva só existe com verbo na voz passiva (ser + particípio + por).")

q(13, LP, "Uso dos porquês", "E",
  "“Por que” separado e sem acento em pergunta direta.",
  {"A": "Errada. Substantivado: “o porquê das coisas”.",
   "B": "Errada. Explicação: “é porque falta”.",
   "C": "Errada. Fim de frase interrogativa: “por quê?”.",
   "D": "Errada. Causa: “porque mudou”.",
   "E": "CORRETA."},
  "Por que (pergunta) · por quê (fim de frase) · porque (resposta) · porquê (substantivo).")

q(14, LP, "Interpretação de texto", "A",
  "O autor compara os exames a uma “inquisição sem apelo”: tortura sem direito a recurso, pelo "
  "sofrimento que causam.",
  {"A": "CORRETA.",
   "B": "Errada. Fala de lembranças da adolescência (“vós nos tirastes o encanto”).",
   "C": "Errada. “Exames” tem o mesmo sentido nas duas ocorrências.",
   "D": "Errada. As queixas são emocionais, não práticas.",
   "E": "Errada. O “pré-suplício do inferno” são as reprovações, posteriores ao exame."},
  "Comparação explícita no texto costuma ser a resposta em questões de interpretação.")

q(15, LP, "Sinais de pontuação – funções", "A",
  "As reticências em “Existem cordas … no coração humano” marcam suspensão do pensamento, uma pausa "
  "hesitante antes da conclusão.",
  {"A": "CORRETA.",
   "B": "Errada. O travessão marca o comentário exclamativo, não separa orações.",
   "C": "Errada. “(…)” indica supressão de trecho da citação.",
   "D": "Errada. Os parênteses intercalam um comentário do autor.",
   "E": "Errada. As aspas marcam a fala de um personagem (discurso direto)."},
  "(…) entre parênteses = trecho suprimido.",
  obs="Questão de interpretação dos sinais; A é a identificação mais adequada.")

q(16, LP, "Adequação semântica de palavras", "D",
  "“Apesar de forte, mostrava medo” é coerente: a concessão quebra a expectativa. Nas demais há "
  "incompatibilidade de sentido (abruptamente x entreabriu; faixa x orifício; destroços para parede; "
  "frutas que “deslizam” em vez de rolar).",
  {"A": "Errada. “Abruptamente” não combina com “entreabrir”.",
   "B": "Errada. Por um orifício passa um raio, não uma “faixa” de luz.",
   "C": "Errada. Restos de parede são “escombros”; “destroços” se usa para veículos e embarcações.",
   "D": "CORRETA.",
   "E": "Errada. Frutas rolam, não deslizam, escada abaixo."},
  "Na adequação vocabular, confira se cada palavra combina com o contexto.")

q(17, LP, "Pronome relativo com preposição", "A",
  "Preocupar-se COM algo → “a violência urbana, com que tanto se preocupa a população”.",
  {"A": "CORRETA.", "B": "Errada. Debater algo (VTD): “que”.",
   "C": "Errada. Ouvir algo (VTD): “que”.", "D": "Errada. “Que recursos”.",
   "E": "Errada. Tomar algo (VTD): “que”."},
  "A preposição do relativo vem da regência do verbo da oração adjetiva.")

q(18, LP, "Redação oficial", "C",
  "A redação oficial exige objetividade, concisão, clareza, impessoalidade e formalidade.",
  {"A": "Errada. Ambiguidade é vedada.", "B": "Errada. Usa-se a norma-padrão.",
   "C": "CORRETA.", "D": "Errada. Remetente, destinatário, assunto e data são obrigatórios.",
   "E": "Errada. Preferem-se períodos curtos e diretos."},
  "Redação oficial: clara, concisa, impessoal, formal e padronizada.")

q(19, LP, "Interpretação", "B",
  "“Testemunhar o seu tempo” = registrar a própria época, preservando-a para as gerações futuras.",
  {"A": "Errada.", "B": "CORRETA.",
   "C": "Errada. Restringe ao estudo da História.",
   "D": "Errada. Banaliza a função da escrita.", "E": "Errada."},
  "Escolha a paráfrase mais fiel ao sentido, sem restringir nem ampliar.")

q(20, LP, "Advérbio x adjetivo", "A",
  "Em “fala tão alto”, “alto” modifica o verbo falar (modo/intensidade): é advérbio, invariável.",
  {"A": "CORRETA.", "B": "Errada. “Ótimo” é predicativo (adjetivo).",
   "C": "Errada. “Bom” é adjetivo substantivado.", "D": "Errada. “Preguiçoso” é substantivo.",
   "E": "Errada. “Difícil” é adjetivo predicativo."},
  "Se modifica o verbo e não flexiona, é advérbio.")

# ===================== LÍNGUA INGLESA =====================
q(21, ING, "Reading – referência", "C",
  "“Men's wages are, on average, 24% higher than women's [wages]”: o percentual se refere aos salários.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "O genitivo “women's” retoma o substantivo anterior (wages).")

q(22, ING, "Adverbs", "A",
  "“Rarely” = adverb of frequency; “tomorrow” = adverb of time.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Frequência: always, usually, often, sometimes, rarely, never.")

q(23, ING, "Spelling rules", "D",
  "Palavra terminada em “e” mudo perde o “e” antes de sufixo iniciado por vogal: make → making; hope → hoping.",
  {"A": "Errada. Troca-se “ie” por “y” (die → dying), não o contrário.",
   "B": "Errada. Troca-se “y” por “i” quando precedido de CONSOANTE.",
   "C": "Errada. Dobra-se quando a ÚLTIMA sílaba é tônica (beGIN → beginning).",
   "D": "CORRETA.",
   "E": "Errada. Não se dobra quando a palavra termina em duas consoantes."},
  "CVC + sílaba final tônica → dobra a consoante (stop → stopped).")

q(24, ING, "Comparative", "D",
  "“Warmer” e “thinner” são adjetivos no grau comparativo (warm + er; thin + n + er).",
  {"A": "Errada. Superlativo seria warmest/thinnest.",
   "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Adjetivo curto: -er (comparativo) / -est (superlativo).")

q(25, ING, "Prepositions", "C",
  "Expressão fixa: “to be good AT something”.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "good at / bad at / interested in / afraid of.")

# ===================== CIÊNCIAS HUMANAS =====================
q(26, HUM, "Brasil Colônia – política indigenista", "C",
  "Descimentos eram expedições (sobretudo de missionários) que deslocavam indígenas do sertão para "
  "aldeamentos próximos aos núcleos coloniais, para catequese e uso como mão de obra.",
  {"A": "Errada. Missão é o núcleo religioso.", "B": "Errada. Aldeamento é o destino, não o deslocamento.",
   "C": "CORRETA.", "D": "Errada. Índios de resgate eram prisioneiros “comprados” de outros grupos.",
   "E": "Errada. Repartição era a distribuição do trabalho dos aldeados."},
  "Descimento = descer (deslocar) indígenas do sertão para os aldeamentos.")

q(27, HUM, "Identidade nacional e natureza (séc. XIX)", "C",
  "O texto afirma que a natureza seria “elemento definidor da unidade natural da Pátria, diante da "
  "falta de uma unidade cultural”.",
  {"A": "Errada. A natureza simboliza unidade, não diferenças internas.",
   "B": "Errada. O texto diz que ela deixa de ser mera contemplação.",
   "C": "CORRETA.", "D": "Errada. Não é obstáculo, é valor.",
   "E": "Errada. Busca a singularidade, não a aproximação com a Europa."},
  "Romantismo/IHGB: natureza tropical como símbolo de unidade nacional.")

q(28, HUM, "Capitalismo e globalização", "A",
  "(V) Transnacionais migraram à periferia em busca de custos menores. (F) 2008 foi crise financeira "
  "(subprime), não de superprodução. (F) Os periféricos estão inseridos no circuito financeiro. "
  "(V) Obsolescência programada. (V) Concentração de riqueza e risco à democracia.",
  {"A": "CORRETA. V F F V V.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Crise de 2008 = bolha imobiliária e financeira nos EUA (subprime).")

q(29, HUM, "Primeira República – conflitos sociais", "E",
  "A Revolta dos Malês ocorreu em 1835, em Salvador, no Período REGENCIAL, e não na Primeira República.",
  {"A": "Primeira República (1904).", "B": "Primeira República (1910).",
   "C": "Primeira República (1896-97).", "D": "Primeira República (1912-16).",
   "E": "NÃO corresponde (gabarito)."},
  "Malês (1835) e Sabinada (1837), ambas em Salvador, são do Período Regencial.")

q(30, HUM, "Impactos ambientais do garimpo", "E",
  "O garimpo não adota práticas artesanais para “contornar a legislação”; mecanizou-se e usa mercúrio "
  "de forma intensiva e ilegal.",
  {"A": "Verdadeira.", "B": "Verdadeira.", "C": "Verdadeira.", "D": "Verdadeira.",
   "E": "INCORRETA (gabarito)."},
  "Repetida no Simulado 06 — tema recorrente: mercúrio e assoreamento.")

q(31, HUM, "Iluminismo", "E",
  "Todas corretas: crítica ao Antigo Regime; censura e prisões (Voltaire, Diderot); cartas e salões "
  "difundiam ideias; a Enciclopédia (Diderot e D'Alembert) foi a grande obra iluminista.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Iluminismo: razão, liberdade, crítica ao absolutismo, Enciclopédia.")

q(32, HUM, "Democracia na Antiguidade", "B",
  "Na Atenas de Péricles, os cidadãos livres (homens adultos, filhos de atenienses), "
  "independentemente da riqueza, participavam diretamente da Eclésia — democracia direta.",
  {"A": "Errada.", "B": "CORRETA.",
   "C": "Errada. Roma era república aristocrática; o voto não tinha o mesmo peso.",
   "D": "Errada.", "E": "Errada. A democracia ateniense era direta, não representativa."},
  "Atenas = democracia direta, com cidadania restrita (excluía mulheres, escravos e estrangeiros).")

q(33, HUM, "Nova ordem mundial", "D",
  "I (F): “America First” é slogan unilateralista/nacionalista, não multilateral. II (V): Belt and "
  "Road (Nova Rota da Seda). III (V): tendência multipolar.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "“America First” = unilateralismo.")

q(34, HUM, "Desmatamento e queimadas", "E",
  "Na floresta tropical os nutrientes estão na BIOMASSA (ciclagem rápida); os solos são pobres. "
  "Dizer que os nutrientes do solo são os “principais responsáveis pela exuberância” é incorreto.",
  {"A": "Correta.", "B": "Correta.", "C": "Correta.", "D": "Correta.", "E": "INCORRETA (gabarito)."},
  "A Amazônia se sustenta pela própria serrapilheira (ciclo fechado de nutrientes).")

q(35, HUM, "Continentalidade e maritimidade", "A",
  "A água tem maior calor específico: aquece e resfria mais lentamente que o continente, reduzindo a "
  "amplitude térmica no litoral.",
  {"A": "CORRETA.", "B": "Errada. O Centro-Oeste sofre continentalidade.",
   "C": "Errada. O litoral sofre maritimidade, e as massas vêm do Atlântico.",
   "D": "Errada. A amplitude é MENOR sob maritimidade.",
   "E": "Errada. O Hemisfério Norte tem mais terras emersas."},
  "Continentalidade = maior amplitude térmica; maritimidade = menor amplitude.")

q(36, HUM, "Crise do feudalismo", "E",
  "Todas verdadeiras: Peste Negra (século XIV), revoltas camponesas (Jacqueries) e Cruzadas "
  "(renascimento comercial).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA. V V V."},
  "Crise do século XIV: fome, peste e guerra (dos Cem Anos).")

q(37, HUM, "Reconhecimento da Independência", "D",
  "Portugal reconheceu a independência em 1825, com mediação britânica, mediante indenização; a "
  "Inglaterra obteve vantagens comerciais (tratado de 1827).",
  {"A": "Errada.", "B": "Errada. A Doutrina Monroe (1823) defendia a não intervenção europeia, não a legitimidade monárquica.",
   "C": "Errada. A Cisplatina tornou-se o Uruguai independente (1828).",
   "D": "CORRETA.", "E": "Errada."},
  "EUA foram os primeiros (1824); Portugal reconheceu em 1825, com mediação inglesa.")

q(38, HUM, "Guerra de Canudos", "C",
  "Canudos não reivindicava a independência da Bahia; era comunidade messiânica de sertanejos pobres.",
  {"A": "Correta.", "B": "Correta.", "C": "INCORRETA (gabarito).", "D": "Correta.", "E": "Correta."},
  "Canudos (Belo Monte) = religiosidade popular + miséria + latifúndio.")

q(39, HUM, "Revolta dos Malês (1835)", "C",
  "I (V) e II (V). III (F): ocorreu no Período Regencial (1835), não no Primeiro Reinado. IV (F): "
  "não teve apoio da Igreja nem de irmandades.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Malê = muçulmano em iorubá (imale). Salvador, janeiro de 1835.")

q(40, HUM, "Uso da água doce", "B",
  "I (F): a maior parte das águas residuais retorna sem tratamento. II (V): a Ásia concentra a maior "
  "proporção de terras irrigadas. III (F): a agricultura consome ~70% da água doce.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Agricultura é a maior consumidora de água doce no mundo.")

q(41, HUM, "Ocupação do Nordeste colonial", "B",
  "I (F): as drogas do sertão eram coletadas na Amazônia. II (V): a pecuária interiorizou-se pelos "
  "rios São Francisco e Parnaíba. III (F): terra roxa e café são do Sudeste/Sul (séc. XIX).",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "São Francisco = “rio dos currais”.")

q(42, HUM, "Estado de Bem-Estar Social", "D",
  "O Welfare State garante proteção social: previdência, seguro-desemprego, aposentadoria (II e III). "
  "Clientelismo (I) não é seu objetivo.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Welfare State: keynesianismo + seguridade social.")

q(43, HUM, "Economia brasileira 1970-2020", "D",
  "O boom das commodities nos anos 2000 não eliminou o “custo Brasil”; os gargalos de infraestrutura "
  "persistiram.",
  {"A": "Correta.", "B": "Correta.", "C": "Correta.", "D": "INCORRETA (gabarito).", "E": "Correta."},
  "Verbos absolutos (“eliminando”) costumam denunciar o erro.")

q(44, HUM, "Segunda Guerra – Dia D", "D",
  "O desembarque na Normandia (6/6/1944) abriu a frente ocidental e iniciou a libertação da França.",
  {"A": "Errada.", "B": "Errada. Os EUA entraram em 1941 (Pearl Harbor).",
   "C": "Errada.", "D": "CORRETA.", "E": "Errada. Houve forte resistência alemã."},
  "Dia D = Operação Overlord.")

q(45, HUM, "Era Vargas – Goiânia", "D",
  "Pedro Ludovico defendia a mudança da capital como ruptura com as velhas oligarquias, "
  "modernização e cumprimento dos compromissos da oposição (“ontem revolucionário na oposição, hoje "
  "revolucionário no governo”).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada. Goiás não tem litoral.",
   "D": "CORRETA.", "E": "Errada."},
  "Goiânia (1933-1937) simboliza a Marcha para o Oeste da Era Vargas.")

# ===================== MATEMÁTICA =====================
q(46, MAT, "Paridade", "D",
  "Produto par ⇒ ao menos um número par. Soma par ⇒ a quantidade de ímpares é par (0, 2 ou 4). "
  "Como há pelo menos um par, os ímpares são 0 ou 2 ⇒ os pares são 4 ou 2: no mínimo dois pares.",
  {"A": "Errada.", "B": "Errada. Deixaria 3 ímpares (soma ímpar).",
   "C": "Errada. Soma ímpar.", "D": "CORRETA.", "E": "Errada. Podem ser todos pares."},
  "Soma par ⇔ quantidade par de parcelas ímpares.")

q(47, MAT, "Estatística – moda, mediana e média", "C",
  "Ordenados: x ≤ b ≤ 21 ≤ d ≤ e. Moda única 24 ⇒ d = e = 24. Soma = 5·20 = 100 ⇒ x + b = 31. "
  "Para a moda ser única, b ≠ 21 e b ≠ x ⇒ b ≤ 20 ⇒ x ≥ 11. Lista: 11, 20, 21, 24, 24.",
  {"A": "Errada.", "B": "Errada. Exigiria b = 21 (duas modas).", "C": "CORRETA.",
   "D": "Errada.", "E": "Errada."},
  "Para minimizar x, maximize os demais respeitando as restrições.")

q(48, MAT, "Razão – concorrência", "B",
  "Nível I: 9000/300 = 30 candidatos por vaga. Nível II: 3000/100 = 30. Concorrências iguais.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Concorrência = inscritos ÷ vagas.")

q(49, MAT, "Regra de três composta", "B",
  "Agentes × horas × dias = constante: 24·6·6 = x·8·12 ⇒ 864 = 96x ⇒ x = 9.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Mais horas e mais dias ⇒ menos agentes (inversa).")

q(50, MAT, "Base média do triângulo", "C",
  "D e E são pontos médios de BC e AC ⇒ DE é base média, paralela a AB e igual à sua metade. "
  "AB = 2·5 = 10 m (o ângulo de 60° é irrelevante).",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Base média = metade do lado paralelo.")

q(51, MAT, "Lei dos cossenos", "D",
  "c² = 4² + 6² − 2·4·6·cos 60° = 16 + 36 − 24 = 28 ⇒ c = 2√7.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "cos 60° = 1/2.")

q(52, MAT, "Composição de funções", "D",
  "f(g(k)) = 3(2k+7)² − 5(2k+7) + 1 = 12k² + 74k + 113. Igualando a h(k): "
  "k² − 26k + 168 = 0 ⇒ k = 12 ou k = 14. Maior: 14 ∈ [12, 19).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Desenvolva o quadrado com cuidado: (2k+7)² = 4k² + 28k + 49.")

q(53, MAT, "Sistema linear", "A",
  "2c+f+3q=770; c+2f+3q=850; c+3f+2q=840. (1)−(2): c − f = −80 ⇒ f = c + 80. Em (2): c + q = 230. "
  "Em (3): 2c + q = 300 ⇒ c = 70, q = 160, f = 150. Total: 70 + 150 + 160 = R$ 380,00.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Subtraia equações para eliminar variáveis rapidamente.")

q(54, MAT, "Teorema do resto", "C",
  "Resto da divisão por (x + 1) = P(−1) = 1 − 23 + 24 = 2.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Divisor x − a ⇒ resto = P(a).")

q(55, MAT, "Probabilidade com anagramas", "E",
  "CFOPM: 5 letras distintas (4 consoantes, 1 vogal) ⇒ 5! = 120 anagramas. Começar e terminar com "
  "consoante: 4·3·3! = 72. P = 72/120 = 60%.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Fixe primeiro as posições restritas (extremos) e permute o meio.")

# ===================== INFORMÁTICA =====================
q(56, INF, "Intranet", "D",
  "Rede privada de uma organização que usa as mesmas tecnologias da internet (TCP/IP, HTTP).",
  {"A": "Errada. Usa os mesmos protocolos.", "B": "Errada. Isso é a internet.",
   "C": "Errada. Usa roteadores e firewalls.", "D": "CORRETA.", "E": "Errada. Usa IP."},
  "Intranet = internet “para dentro”.")

q(57, INF, "Periféricos de entrada e saída", "B",
  "A caixa de som é apenas dispositivo de SAÍDA. Cartão SD, SSD, multifuncional e monitor touch "
  "realizam entrada e saída.",
  {"A": "E/S (leitura e gravação).", "B": "GABARITO. Só saída.", "C": "E/S (armazenamento).",
   "D": "E/S (imprime e digitaliza).", "E": "E/S (exibe e recebe toque)."},
  "Dispositivos de armazenamento são considerados de entrada e saída.")

q(58, INF, "MS Word – Estilos", "A",
  "I (V) “Título 1” está na galeria. II (V) O estilo padrão é Normal. III (F) e IV (F): estilos podem "
  "ser modificados a qualquer momento, atualizando os trechos que os usam.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Estilos são editáveis (botão direito › Modificar).")

q(59, INF, "Malware – spyware", "A",
  "Spyware coleta informações do usuário (histórico, dados pessoais) sem consentimento.",
  {"A": "CORRETA.", "B": "Errada. Keylogger captura teclas (é um tipo de spyware, mais específico).",
   "C": "Errada. Worm se autorreplica pela rede.", "D": "Errada. Adware exibe propaganda.",
   "E": "Errada. Trojan se disfarça de programa legítimo."},
  "Spy = espião.")

q(60, INF, "Criptografia e assinatura digital", "A",
  "A assinatura digital garante autenticidade, integridade e não repúdio.",
  {"A": "CORRETA.", "B": "Errada. Data errada pode invalidar certificados.",
   "C": "Errada. O certificado é registro eletrônico.",
   "D": "Errada. Chave é o parâmetro do algoritmo, não o meio de troca.",
   "E": "Errada. Também serve para armazenamento e assinatura."},
  "Assinatura digital ≠ confidencialidade (que vem da cifragem).")

# ===================== DIREITO =====================
q(61, DIR, "Inquérito policial", "B",
  "CPP, art. 5º, II: na ação pública incondicionada, o inquérito pode ser instaurado mediante "
  "requisição do MP ou da autoridade judiciária.",
  {"A": "Errada. O delegado não pode arquivar (art. 17).",
   "B": "CORRETA.", "C": "Errada. Há PIC do MP, CPI, IPM etc.",
   "D": "Errada. 10 dias (preso) ou 30 dias (solto).",
   "E": "Errada. Depende de representação (art. 5º, §4º)."},
  "Autoridade policial NUNCA arquiva inquérito.")

q(62, DIR, "Polícia Federal (CF, art. 144, §1º)", "E",
  "Órgão permanente, organizado e mantido pela União e estruturado em carreira.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Repetida do Simulado 06 — memorize a literalidade.")

q(63, DIR, "Provas no processo penal", "B",
  "CPP, art. 157 (e CF, art. 5º, LVI): provas ilícitas são inadmissíveis e devem ser "
  "desentranhadas.",
  {"A": "Errada. A confissão não supre o exame (art. 158).", "B": "CORRETA.",
   "C": "Errada. Interrogatórios separados (art. 191).",
   "D": "Errada. A confissão é DIVISÍVEL e RETRATÁVEL (art. 200).",
   "E": "Errada. Sem consentimento, só de dia (CF, art. 5º, XI)."},
  "Confissão: divisível e retratável.")

q(64, DIR, "Desrespeito a superior (CPM, art. 160)", "E",
  "“Desrespeitar superior diante de outro militar.”",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Elementar do tipo: presença de OUTRO MILITAR.")

q(65, DIR, "Flagrante (CPP, art. 302)", "A",
  "I e II (flagrante próprio), III (impróprio), IV (presumido). Não existe a regra das 24 horas (V).",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Mito das 24 horas: não há prazo fixo no CPP para o flagrante.")

q(66, DIR, "Convenção contra a Discriminação Racial", "E",
  "(F) Também está aberta a qualquer Estado convidado pela Assembleia Geral (art. 17). (V) Medidas no "
  "ensino, educação, cultura e informação (art. 7º). (V) Proteção e recursos eficazes (art. 6º). "
  "(V) Comunicações entre Estados ao Comitê, com 3 meses para resposta (art. 11). (F) Vigência no "
  "30º dia após o depósito do 27º instrumento (art. 19).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA. F V V V F."},
  "Leia a literalidade dos prazos: 27º instrumento e 30º dia.")

q(67, DIR, "Processo penal – jurisprudência", "A",
  "(V) CPP, art. 13-A. (F) CPP, art. 14-A: prazo de até 48 HORAS para constituir defensor. (F) Para o "
  "STJ, o ANPP não é direito subjetivo do investigado (poder-dever do MP). (V) Para o STJ, a busca e "
  "apreensão, como cautelar real, não exige contemporaneidade.",
  {"A": "CORRETA. V F F V.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Art. 14-A (Pacote Anticrime): 48 horas.")

q(68, DIR, "Crimes contra a paz pública", "B",
  "Parte da doutrina critica o art. 288-A (milícia privada) por não definir com precisão “milícia” e "
  "“grupo de extermínio”, ferindo a taxatividade.",
  {"A": "Errada. Associação criminosa é crime permanente.", "B": "CORRETA.",
   "C": "Errada. Organização criminosa está na Lei 12.850/2013.",
   "D": "Errada. STF (ADPF 187, “Marcha da Maconha”) — liberdade de expressão.",
   "E": "Errada. A apologia exige publicidade."},
  "Crimes do Título IX: incitação, apologia, associação criminosa, milícia privada.")

q(69, DIR, "Abuso de autoridade – efeitos", "B",
  "Lei 13.869/2019, art. 4º, III e parágrafo único: a perda do cargo depende de reincidência em "
  "abuso de autoridade e não é automática, devendo ser motivada na sentença.",
  {"A": "Errada. 1 a 5 anos e não é automática.", "B": "CORRETA.",
   "C": "Errada. A indenização não depende de reincidência.",
   "D": "Errada. Não é automática.", "E": "Errada."},
  "Repetida do Simulado 06: reincidência + não automático.")

q(70, DIR, "Descumprimento de missão (CPM, art. 196)", "E",
  "(F) É abandono de posto (art. 195). (V) Caput do art. 196. (F) É retenção indevida (art. 197). "
  "(V) §2º: se o agente exercia função de comando, a pena é aumentada de metade.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA. F V F V."},
  "Art. 196: oficial → +1/3; função de comando → +metade.")

q(71, DIR, "Princípios da Administração", "C",
  "I – Moralidade (ética). II – Publicidade. III – Legalidade. IV – Eficiência. V – Impessoalidade "
  "(vedação de promoção pessoal, CF, art. 37, §1º).",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "LIMPE.")

q(72, DIR, "Insubmissão (CPM, art. 183)", "D",
  "A conduta descrita é a do crime de insubmissão.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Convocado que não se apresenta = insubmisso.")

q(73, DIR, "Federação e separação de poderes", "B",
  "Os entes federativos são autônomos, sem hierarquia entre si, com repartição constitucional de competências.",
  {"A": "Errada. Os Poderes são independentes e harmônicos.", "B": "CORRETA.",
   "C": "Errada. O Judiciário não legisla para suprir lacunas.",
   "D": "Errada.", "E": "Errada. O Brasil é presidencialista."},
  "Federação: autonomia, não soberania; sem hierarquia.")

q(74, DIR, "Crimes militares – conduta x tipo", "A",
  "Insubmissão (art. 183). As demais trocam o nome do crime.",
  {"A": "CORRETA.", "B": "Errada. É deserção.", "C": "Errada. É omissão de oficial.",
   "D": "Errada. É dormir em serviço.", "E": "Errada. É omissão de providências para evitar danos."},
  "Repetida do Simulado 06.")

q(75, DIR, "Constituição Federal – organização do Estado", "E",
  "CF, art. 21, VI: compete à União autorizar e fiscalizar a produção e o comércio de material bélico.",
  {"A": "Errada. Territórios não são entes autônomos (art. 18).",
   "B": "Errada. Recursos minerais são bens da União (art. 20, IX).",
   "C": "Errada. Primeiro domingo de outubro (art. 28).",
   "D": "Errada. Terras devolutas não compreendidas entre as da União (art. 26, IV).",
   "E": "CORRETA."},
  "Material bélico = União.")

q(76, DIR, "Crimes contra a liberdade sexual", "B",
  "CP, art. 215-A (importunação sexual): praticar contra alguém e sem sua anuência ato libidinoso "
  "para satisfazer a própria lascívia ou a de terceiro.",
  {"A": "Errada. É assédio sexual (art. 216-A).", "B": "CORRETA.",
   "C": "Errada. É violação sexual mediante fraude (art. 215).",
   "D": "Errada. É estupro (art. 213).", "E": "Errada. É estupro de vulnerável (art. 217-A)."},
  "Questão inédita Gabaritando: atenção à troca de nomes dos tipos.")

q(77, DIR, "Peculato culposo (CP, art. 312, §2º)", "D",
  "O soldado concorreu culposamente (negligência) para que terceiro subtraísse bem público — peculato culposo.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada. Peculato-furto exige dolo e facilidade do cargo.",
   "D": "CORRETA.", "E": "Errada."},
  "Peculato culposo: reparação do dano antes da sentença irrecorrível extingue a punibilidade.",
  obs="Por ser policial militar em serviço, há debate sobre o enquadramento no CPM (art. 303, §3º).")

q(78, DIR, "Estrutura administrativa da Bahia (Lei 12.212/2011)", "E",
  "A Lei 12.212/2011 criou a Secretaria de Políticas para as Mulheres (SPM-BA).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada. Não há ministério estadual.",
   "E": "CORRETA."},
  "Legislação estadual da Bahia é cobrada no CFO PMBA.")

q(79, DIR, "Inquérito policial (CPP)", "A",
  "CPP, art. 16: o MP não pode requerer a devolução do inquérito senão para novas diligências "
  "imprescindíveis ao oferecimento da denúncia.",
  {"A": "CORRETA.", "B": "Errada. O prazo é marcado pelo juiz (art. 10, §3º).",
   "C": "Errada. Cabe recurso ao chefe de Polícia (art. 5º, §2º).",
   "D": "Errada. Acompanham os autos (art. 11).", "E": "Errada. Nunca (art. 17)."},
  "Arts. 10 a 17 do CPP: campeões de prova.")

q(80, DIR, "Pacto de São José – suspensão de garantias", "B",
  "O art. 27 da Convenção Americana não admite suspender: vida, integridade pessoal, proibição de "
  "escravidão, legalidade, liberdade de consciência e religião, entre outros. A liberdade de "
  "expressão (art. 13) pode ser restringida em emergências.",
  {"A": "Errada. Inderrogável.", "B": "CORRETA.", "C": "Errada. Inderrogável.",
   "D": "Errada. Inderrogável.", "E": "Errada. Inderrogável."},
  "Núcleo inderrogável: vida, integridade, não escravidão, não tortura.")

DISCURSIVA = {
    "tema": "Redução da maioridade penal no Brasil: limites constitucionais, impactos sociais e "
            "dilemas éticos de uma possível mudança na responsabilização de adolescentes em conflito com a lei",
    "estrutura": [
        ("Introdução",
         "Contextualize o debate (PEC 171/1993, aprovada na Câmara em 2015 e parada no Senado) e "
         "apresente a tese, por exemplo: a redução não enfrenta as causas da criminalidade juvenil e "
         "esbarra em limites constitucionais; o caminho é tornar efetivas as medidas do ECA."),
        ("Desenvolvimento 1 – limites constitucionais",
         "CF, art. 228 (inimputabilidade dos menores de 18 anos). Discussão: é cláusula pétrea "
         "(direito individual, art. 60, §4º, IV) ou pode ser alterada por emenda? Cite a doutrina da "
         "proteção integral (art. 227) e a Convenção sobre os Direitos da Criança."),
        ("Desenvolvimento 2 – impactos sociais e dilemas éticos",
         "Sistema prisional superlotado (STF, ADPF 347 — estado de coisas inconstitucional), risco "
         "de recrutamento por facções, seletividade penal (jovens negros e pobres). Contraponto: "
         "sensação de impunidade em crimes graves. Mostre que você conhece os dois lados."),
        ("Conclusão – proposta",
         "Aperfeiçoar o ECA (ampliar a internação em atos infracionais gravíssimos), investir em "
         "socioeducação, escola em tempo integral, combate à evasão escolar, prevenção e "
         "integração entre segurança pública e assistência social."),
    ],
    "criterios": [
        "Abordar os TRÊS eixos do tema: limites constitucionais, impactos sociais e dilemas éticos.",
        "Tese clara, com argumentos e repertório jurídico (CF, ECA, STF).",
        "Norma-padrão e coesão.",
        "Postura de futuro oficial: equilíbrio, sem discurso de ódio nem senso comum.",
        "Evitar fuga ao tema, texto em versos ou assinatura fora do campo (causas de anulação).",
    ],
}
