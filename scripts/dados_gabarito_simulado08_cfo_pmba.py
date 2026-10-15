"""
Gabarito comentado – Simulado 08 – CFO PMBA (Oficial QOPM).

O caderno não tem a questão 19, repete o texto das questões 01 a 03 nas questões 17, 18 e 20
e numera duas questões como 62. O campo "rot" guarda a numeração do caderno.
"""

NUMERO = "08"
AVISO = ("o caderno não traz a questão 19; as questões 17, 18 e 20 repetem as questões 01, 02 e 03; "
         "e há duas questões numeradas como 62 (aqui identificadas como 62-A e 62-B). "
         "A numeração do caderno foi mantida.")
Q = []


def q(rot, disc, tema, gab, com, alts, dica, obs=None):
    Q.append(dict(n=len(Q) + 1, rot=rot, disc=disc, tema=tema, gab=gab, com=com, alts=alts,
                  dica=dica, obs=obs))


LP, ING, HUM, MAT, INF, DIR = ("Língua Portuguesa", "Língua Inglesa", "Ciências Humanas",
                               "Matemática", "Informática", "Direito")

# ===================== LÍNGUA PORTUGUESA =====================
Q01 = ("Interpretação de texto", "C",
       "I (V): a violência é múltipla e suas definições variam conforme as normas sociais. II (V): "
       "“de acordo com épocas, locais e circunstâncias” designa realidades diferentes. III (F): o texto "
       "não diz que o emocional é o ÚNICO fator. IV (V): a carga emocional envolve quem comete, quem sofre "
       "e quem presencia. V (F): “Há violências toleradas e há violências condenadas”.",
       {"A": "Incompleta (falta IV).", "B": "Incompleta (falta I).", "C": "CORRETA. I, II e IV.",
        "D": "Errada. III e V são falsas.", "E": "Errada. III e V são falsas."},
       "Palavras como “único” e “indistintamente” costumam tornar a afirmativa falsa.")
Q02 = ("Semântica – sinonímia contextual", "B",
       "“Aparatos legais” significa estruturas, instrumentos ou mecanismos legais. “Solenidade” "
       "(cerimônia, pompa) não serve como sinônimo nesse contexto.",
       {"A": "Adequado. Aniquilamento = extermínio.", "B": "INADEQUADO (gabarito).",
        "C": "Adequado. Dinâmica = atuação, funcionamento.", "D": "Adequado. Erudito = clássico, culto.",
        "E": "Adequado. Âmbito = esfera."},
       "“Aparato” pode significar pompa, mas no texto equivale a estrutura/instrumento.")
Q03 = ("Análise linguística – conectivos e pronomes", "C",
       "“Cujas” é pronome relativo que indica posse: as manifestações pertencem à violência (o "
       "fenômeno de que se fala).",
       {"A": "Errada. “Para conceituar” indica finalidade.",
        "B": "Errada. Os travessões isolam uma expressão intercalada, não introduzem fala.",
        "C": "CORRETA.",
        "D": "Errada. “Por” introduz o agente da passiva.",
        "E": "Errada. “Esse tipo de fenômeno” retoma a violência criminal e delinquencial."},
       "“Cujo” sempre indica posse e concorda com o termo possuído.")

q("01", LP, *Q01)
q("02", LP, *Q02)
q("03", LP, *Q03)
q("04", LP, "Acentuação gráfica e classe de palavras", "A",
  "Vocábulo, épocas, fenômeno, dinâmica e âmbito são todos SUBSTANTIVOS e PROPAROXÍTONOS.",
  {"A": "CORRETA.",
   "B": "Errada. Mistura adjetivos (múltipla, física, ilícitas) e paroxítonas terminadas em ditongo.",
   "C": "Errada. “Simbólica” é adjetivo e proparoxítona; as demais são paroxítonas terminadas em ditongo.",
   "D": "Errada. Mistura classes e regras.",
   "E": "Errada. Mistura classes e regras."},
  "Confira duas coisas: a classe da palavra e a regra de acentuação.")
q("05", LP, "Ordem dos termos e sentido", "B",
  "Na alternativa B, trocar “capacidade” por “idade” inverte a ideia: na primeira frase o saber vem "
  "da capacidade; na segunda, da idade.",
  {"A": "Errada. As duas frases expressam a mesma ideia (não se olha nas duas direções).",
   "B": "CORRETA.", "C": "Errada. “Menos nos ouvidos” = “mais nos olhos”.",
   "D": "Errada. Só muda a ordem sujeito/predicativo.", "E": "Errada. Relação de identidade."},
  "Inversão de sujeito e predicativo em definição não altera o sentido.")
q("06", LP, "Gênero textual – charge/tirinha", "E",
  "I (V): une linguagem verbal e não verbal. II (F): a charge estimula a análise social e política. "
  "III (V): forma leitores críticos. IV (V): usa linguagem acessível e recursos que levam o leitor a "
  "desenvolver a criticidade.",
  {"A": "Incompleta.", "B": "Errada. II é falsa.", "C": "Errada. II é falsa.",
   "D": "Errada. II é falsa.", "E": "CORRETA. I, III e IV."},
  "Charge = humor + crítica + linguagem mista.",
  obs="O item IV (“linguagem objetiva”) é discutível, pois a charge usa ironia; E é o gabarito mais provável.")
q("07", LP, "Redação técnica x estilística", "B",
  "I (V): técnica = denotativa; estilística = conotativa. II (F): a comunicação inequívoca é própria "
  "da redação técnica, não da estilística. III (V) e IV (V): finalidades e marcas descritas "
  "corretamente. V (F): a estilística não tem por objetivo convencer, e sim expressar artisticamente.",
  {"A": "Errada.", "B": "CORRETA. I, III e IV.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "“Aquela” retoma o termo mais distante; “esta”, o mais próximo.")
q("08", LP, "Hífen (Acordo Ortográfico)", "D",
  "Pelo Acordo Ortográfico, locuções como “cara de pau” não levam hífen.",
  {"A": "Correta. Pingue-pongue (palavra com elementos repetidos/alterados).",
   "B": "Correta. Super-homem (prefixo + h).",
   "C": "Correta. Contra-atacar (vogal igual).",
   "D": "INCORRETA (gabarito).",
   "E": "Correta. Micro-ônibus (vogal igual)."},
  "Prefixo terminado em vogal + palavra iniciada pela mesma vogal → hífen.")
q("09", LP, "Estrutura e sentido da frase", "A",
  "“Felicidade é um fluxo de caixa positivo” tem estrutura de definição: termo + verbo SER + "
  "conceito.",
  {"A": "CORRETA.", "B": "Errada. Campo semântico da economia.",
   "C": "Errada. Não fala de pobreza.", "D": "Errada. Voz ativa com verbo de ligação.",
   "E": "Errada. “Positivo” refere-se a “fluxo”."},
  "Definição: X é Y.")
q("10", LP, "Classes de palavras", "D",
  "Em “e falta de opções de lazer”, “falta” é substantivo (núcleo do sintagma, precedido de "
  "elementos coordenados).",
  {"A": "Errada. Advérbio.", "B": "Errada. Adjetivo.", "C": "Errada. Substantivo.",
   "D": "CORRETA.", "E": "Errada. Preposição."},
  "Sufixo -mente forma advérbio; -al forma adjetivo.")
q("11", LP, "Redação oficial", "B",
  "B respeita a norma-padrão, o tratamento (V. Sa.), a impessoalidade e a clareza. As demais têm "
  "anacolutos, “à elas” com crase indevida, regência e concordância inadequadas.",
  {"A": "Errada. Anacoluto e “à elas”.", "B": "CORRETA.",
   "C": "Errada. Sujeito de “determinando” confuso; “obedecendo-as” (obedecer a).",
   "D": "Errada. Vírgula entre sujeito e verbo.", "E": "Errada. Concordância e estrutura truncadas."},
  "Nunca há crase antes de pronome pessoal (“a elas”).")
q("12", LP, "Valor semântico da preposição “a”", "B",
  "“Saiu às compras” = saiu PARA fazer compras: finalidade.",
  {"A": "Errada. Tempo.", "B": "CORRETA.", "C": "Errada. Tempo (idade).",
   "D": "Errada. Lugar/direção.", "E": "Errada. Lugar (junto à porta)."},
  "Substitua por “para” e teste o sentido.")
q("13", LP, "Ortografia", "A",
  "Grafias corretas: perversidade e insensatez.",
  {"A": "GABARITO (há erros).", "B": "Sem erros (trema era usado à época da prova).",
   "C": "Sem erros.", "D": "Sem erros.", "E": "Sem erros."},
  "Perverso → perversidade; insensato → insensatez (-ez de adjetivo).")
q("14", LP, "Figuras de linguagem", "A",
  "Em “um tempo que rugia”, atribui-se ao tempo uma ação de ser vivo (rugir): personificação "
  "(prosopopeia).",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Ação humana ou animal atribuída a ser inanimado = personificação.")
q("15", LP, "Pontuação", "A",
  "Vírgula antes de “mas” (adversativa) corretamente empregada.",
  {"A": "CORRETA.", "B": "Errada. Falta pontuação entre as orações (“mundo; hoje”).",
   "C": "Errada. Vírgula antes de “uma ruína” separa verbo de predicativo.",
   "D": "Errada. Vírgula indevida entre “veneno” e o complemento.",
   "E": "Errada. Vírgula separa sujeito e predicativo."},
  "Nunca separe com vírgula sujeito, verbo e complemento.")
q("16", LP, "Complemento nominal x adjunto adnominal", "A",
  "“Necessidade de dinheiro”: o dinheiro é o ALVO (paciente) da necessidade, termo que completa um "
  "substantivo abstrato derivado de adjetivo/verbo → complemento nominal.",
  {"A": "CORRETA.", "B": "Errada. “De educação” especifica a falta (adjunto, para a banca).",
   "C": "Errada. Adjunto adnominal.", "D": "Errada. Posse → adjunto.",
   "E": "Errada. Posse/pertença → adjunto."},
  "Paciente → complemento nominal; agente ou posse → adjunto adnominal.")
q("17", LP, *Q01, obs="Repete a questão 01.")
q("18", LP, *Q02, obs="Repete a questão 02.")
q("20", LP, *Q03, obs="Repete a questão 03 (o caderno não traz a questão 19).")

# ===================== LÍNGUA INGLESA =====================
q("21", ING, "Reading – The Slow Down Diet", "E",
  "“Most people rush through life at a speed that makes a healthy lifestyle impossible. We eat on "
  "the run, and often under stress.”",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Busque no texto a paráfrase de “busy and in a hurry” → “rush through life”.")
q("22", ING, "Reading – True/False", "B",
  "(F) O texto não fala em evitar junk food. (T) “Take pleasure in eating”. (F) Não recomenda pular "
  "refeições. (T) Contra comer “on the run”: comer com calma.",
  {"A": "Errada.", "B": "CORRETA. F T F T.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Julgue pelo que está escrito, não pelo senso comum.")
q("23", ING, "Connectors", "C",
  "“Not only … but also” = não só … mas também: ADIÇÃO.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Correlativas aditivas: not only… but also; both… and.")
q("24", ING, "Comparison – irregular adjectives", "D",
  "good → better → best.",
  {"A": "Errada. ill → worse → worst.", "B": "Errada. bad → worse → worst.",
   "C": "Errada. well é advérbio (better/best), mas o texto usa adjetivo.",
   "D": "CORRETA.", "E": "Errada."},
  "Irregulares: good/better/best; bad/worse/worst; far/further/furthest.")
q("25", ING, "Pronouns", "A",
  "I: the doctor (female, conforme gabarito) → her. II: pancreatic cancer → it. III: grandfather → "
  "him (objeto) / He (sujeito). IV: the test → it.",
  {"A": "CORRETA.", "B": "Errada. “Call she” — pronome sujeito como objeto.",
   "C": "Errada. Câncer → it.", "D": "Errada. “for he” e “Him is” trocados.",
   "E": "Errada. Test → it."},
  "Após verbo/preposição: me, you, him, her, it, us, them.")

# ===================== CIÊNCIAS HUMANAS =====================
q("26", HUM, "Democracia na Antiguidade", "B",
  "Na Atenas de Péricles, os cidadãos livres participavam diretamente da Eclésia, "
  "independentemente da riqueza.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.",
   "E": "Errada. Era democracia direta."},
  "Repetida do Simulado 07.")
q("27", HUM, "Revolta da Vacina (1904)", "D",
  "I (V): surtos de sarampo em 2018 por baixa cobertura vacinal. II (V). III (F): ocorreu no Rio de "
  "Janeiro, não na Bahia. IV (V). V (V): associada às reformas de Pereira Passos e à campanha de "
  "Oswaldo Cruz.",
  {"A": "Incompleta.", "B": "Errada. III é falsa.", "C": "Errada.", "D": "CORRETA.", "E": "Incompleta."},
  "Revolta da Vacina: Rio de Janeiro, 1904, vacinação obrigatória contra a varíola.")
q("28", HUM, "Clima urbano", "E",
  "Ilha de calor: centro urbano mais quente que a periferia e o campo, por impermeabilização, "
  "edificações e falta de vegetação.",
  {"A": "Errada. Inversão térmica: camada fria presa sob camada quente.",
   "B": "Errada. Efeito estufa é fenômeno global.", "C": "Errada.",
   "D": "Errada. Smog = poluição + neblina.", "E": "CORRETA."},
  "Ilha de calor (urbano) x inversão térmica (inverno, poluição).")
q("29", HUM, "Revolta dos Malês (1835)", "A",
  "I e II corretas. III (F): ocorreu no Período Regencial. IV (F): não teve apoio da Igreja.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Repetida do Simulado 07.")
q("30", HUM, "Desconcentração industrial", "A",
  "Indústrias deixam o eixo Rio–São Paulo em busca de mão de obra mais barata, incentivos fiscais "
  "e menor sindicalização.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Fatores: guerra fiscal, mão de obra barata, deseconomias de aglomeração.")
q("31", HUM, "Império Bizantino e Cruzadas", "D",
  "O imperador Aleixo I Comneno pediu ajuda ao Papa Urbano II contra os turcos seljúcidas, "
  "justificativa usada na convocação da 1ª Cruzada (1095).",
  {"A": "Errada.", "B": "Errada. O Cisma (1054) não gerou expedição punitiva.",
   "C": "Errada. Constantinopla caiu em 1453, após as Cruzadas.", "D": "CORRETA.",
   "E": "Errada. A Revolta de Nika foi em 532."},
  "1095: Concílio de Clermont.")
q("32", HUM, "Renascimento e Iluminismo", "A",
  "Ambos são ANTROPOCÊNTRICOS e racionalistas; o teocentrismo é traço medieval.",
  {"A": "EXCEÇÃO (gabarito).", "B": "Comum.", "C": "Comum.", "D": "Comum (deísmo/humanismo).",
   "E": "Comum."},
  "Medieval = teocentrismo; Renascimento/Iluminismo = antropocentrismo.")
q("33", HUM, "Clima semiárido", "D",
  "Escassez e irregularidade de chuvas, baixa nebulosidade, alta evaporação e temperaturas elevadas.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Repetida do Simulado 06.")
q("34", HUM, "Impactos de grandes barragens", "E",
  "Barragens elevam (não rebaixam) o lençol freático no entorno do reservatório e não produzem "
  "poluição radioativa.",
  {"A": "Correta.", "B": "Correta.", "C": "Correta.", "D": "Correta.", "E": "INCORRETA (gabarito)."},
  "Hidrelétrica ≠ usina nuclear.")
q("35", HUM, "Deslizamentos e áreas de risco", "D",
  "I (F): a ocupação irregular e o desmatamento agravam os deslizamentos. II (V) e III (V).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Incompleta."},
  "Risco = ameaça natural + vulnerabilidade social.")
q("36", HUM, "Matriz energética brasileira", "B",
  "Petróleo e derivados são a maior fonte da matriz energética (cerca de 1/3). Não confundir com a "
  "matriz ELÉTRICA, liderada pela hidreletricidade.",
  {"A": "Errada. 2ª fonte.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Matriz energética (todos os usos) ≠ matriz elétrica.")
q("37", HUM, "Brasil – fim do século XX", "E",
  "O Plano Collor (1990) congelou preços e salários e bloqueou cadernetas de poupança e contas.",
  {"A": "Errada. FHC controlou a inflação (Plano Real).", "B": "Errada. Houve desemprego.",
   "C": "Errada.", "D": "Errada. As privatizações seguiram o neoliberalismo.", "E": "CORRETA."},
  "Collor = confisco da poupança.")
q("38", HUM, "Mercosul – desafios", "B",
  "(V) Pressão ambiental e protecionismo agrícola europeu. (F) Não havia acordo Uruguai-China "
  "assinado em 2022 — só negociações, e acordos individuais violam a TEC do bloco. (V) A Venezuela, "
  "suspensa desde 2016, precisa cumprir os compromissos do protocolo de adesão.",
  {"A": "Errada.", "B": "CORRETA. V F V.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Mercosul é união aduaneira imperfeita: membros não negociam isoladamente.")
q("39", HUM, "Independência do Brasil", "E",
  "Fator externo: Revolução Liberal do Porto (1820), que exigiu o retorno de D. João VI e a "
  "recolonização. Fator interno: mobilizações das elites locais.",
  {"A": "Errada. Foram as Cortes de Lisboa.", "B": "Errada. A Noite das Garrafadas é de 1831.",
   "C": "Errada. Líbero Badaró morreu em 1830.", "D": "Errada. Bill Aberdeen é de 1845.",
   "E": "CORRETA."},
  "Cuidado com a cronologia: fatos posteriores a 1822 eliminam alternativas.")
q("40", HUM, "Ditadura militar – música de protesto", "B",
  "“Apesar de você” (1970) critica a censura e a repressão do regime militar (AI-5).",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "MPB de protesto: Chico Buarque, Geraldo Vandré, Gil e Caetano.")
q("41", HUM, "Gentrificação", "A",
  "A valorização de áreas revitalizadas expulsa os moradores mais pobres, que não arcam com os novos custos.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada. Isso é conurbação.", "E": "Errada."},
  "Gentrificação = revitalização + expulsão dos pobres.")
q("42", HUM, "Agricultura camponesa x agronegócio (infográfico)", "D",
  "I (F): o agronegócio recebe 86% do crédito contra 14% da agricultura camponesa, o que ajuda a "
  "explicar a maior produção global. II (V): a agricultura camponesa ocupa 24% das terras e produz "
  "70% da comida. III (V): emprega 74% da mão de obra do campo.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Leia os números do gráfico antes de julgar as afirmativas.")
q("43", HUM, "Migrações", "E",
  "A concentração de serviços públicos (saúde, educação) nas cidades é fator de atração do êxodo rural.",
  {"A": "Errada. O motivo principal é econômico.", "B": "Errada.",
   "C": "Errada. O tráfico foi migração forçada.", "D": "Errada. As remessas não explicam rejeição.",
   "E": "CORRETA."},
  "Fatores de atração: emprego, serviços, renda.")
q("44", HUM, "Megacidades", "E",
  "Nem toda megacidade é cidade global “alfa +”; muitas megacidades da periferia têm baixa "
  "influência global.",
  {"A": "Característica.", "B": "Característica.", "C": "Característica.", "D": "Característica.",
   "E": "EXCEÇÃO (gabarito)."},
  "Megacidade (população) ≠ cidade global (influência).")
q("45", HUM, "Globalização", "E",
  "A globalização AUMENTA a concorrência e a competitividade.",
  {"A": "Característica.", "B": "Característica.", "C": "Característica.", "D": "Característica.",
   "E": "INCORRETA (gabarito)."},
  "Globalização = mais fluxos, mais competição.")

# ===================== MATEMÁTICA =====================
q("46", MAT, "Razão e proporção", "C",
  "Sargentos = s; cabos = 3s; soldados = 5·3s = 15s. s + 3s + 15s = 19s = 57 ⇒ s = 3. Soldados = 45.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Escreva tudo em função da menor quantidade.")
q("47", MAT, "Teorema do resto", "C",
  "P(−1) = 1 − 23 + 24 = 2.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Repetida do Simulado 07.")
q("48", MAT, "Anagramas com bloco", "B",
  "CBMERJ: “CB” como bloco fixo + M, E, R, J = 5 elementos ⇒ 5! = 120.",
  {"A": "Errada. 6! sem restrição.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.",
   "E": "Errada."},
  "Letras juntas em ordem fixa = 1 bloco, sem multiplicar por 2!.")
q("49", MAT, "Trigonometria no triângulo retângulo", "E",
  "sen 30° = altura / rampa ⇒ 1/2 = 2,5 / L ⇒ L = 5 m.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Rampa = hipotenusa; altura = cateto oposto.")
q("50", MAT, "Conversão de unidades", "D",
  "8000 L = 8.000.000 cm³. Tempo = 8.000.000 / 1600 = 5000 s = 1 h 23 min 20 s.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "1 L = 1000 cm³; 3600 s = 1 h.")
q("51", MAT, "Volume do cilindro", "D",
  "V = π r² h ⇒ 20 = 3,14 · 1,25² · h = 4,906 h ⇒ h ≈ 4,08 cm ≈ 4 cm (1 mL = 1 cm³).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "1 mL = 1 cm³.")
q("52", MAT, "Frações", "E",
  "MMC(3, 5, 8) = 120: 40/120 + 48/120 − 45/120 = 43/120 (irredutível). p + q = 163.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Verifique se a fração final é irredutível.")
q("53", MAT, "Porcentagem", "B",
  "PA = x; PB = 1,2x; PC = 1,25 · 1,2x = 1,5x. Soma 3,7x = 18,5 ⇒ x = 5. PC = 7,5 km.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada. Esse é PB.", "D": "Errada.",
   "E": "Errada. Esse é PA."},
  "Aumentos sucessivos se multiplicam.")
q("54", MAT, "Sequência periódica", "A",
  "Período 5 (P, E, N, A, L). 644 = 5·128 + 4 → 4º termo = A. 735 = 5·147 + 0 → 5º termo = L.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Resto 0 corresponde ao último termo do ciclo.")
q("55", MAT, "Probabilidade", "A",
  "Casos favoráveis: C(3,2) = 3. Possíveis: C(10,2) = 45. P = 3/45 = 1/15.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Sorteio sem ordem → combinação.")

# ===================== INFORMÁTICA =====================
q("56", INF, "MS Word – formatação", "B",
  "A palavra “Oficial” aparece com um traço abaixo do texto: sublinhado.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada. Tachado risca o meio.",
   "E": "Errada."},
  "Ctrl+S (Word em português) = sublinhado.")
q("57", INF, "Computação em nuvem – SaaS", "C",
  "SaaS (Software as a Service) é modelo de serviço de cloud computing, ao lado de IaaS e PaaS.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "IaaS (infraestrutura), PaaS (plataforma), SaaS (software).")
q("58", INF, "URL e domínios", "E",
  "O domínio “gov.br” indica site governamental brasileiro.",
  {"A": "Errada.", "B": "Errada. HTTPS é protocolo seguro, não indica “site de segurança”.",
   "C": "Errada. É site público, não intranet.", "D": "Errada.", "E": "CORRETA."},
  "HTTPS = conexão criptografada (TLS).")
q("59", INF, "Aplicativos do Windows", "B",
  "Windows Fax and Scan, Notas Autoadesivas e Bloco de Notas são acessórios nativos do Windows.",
  {"A": "Errada. Spotify é de terceiros.", "B": "CORRETA.",
   "C": "Errada. O nome correto é Microsoft Edge.", "D": "Errada. Excel é do Office.",
   "E": "Errada. Gmail é serviço do Google."},
  "Office (Word/Excel) não vem com o Windows.")
q("60", INF, "Periféricos", "B",
  "Caixa de som é apenas dispositivo de saída.",
  {"A": "E/S.", "B": "GABARITO.", "C": "E/S.", "D": "E/S.", "E": "E/S."},
  "Repetida do Simulado 07.")

# ===================== DIREITO =====================
q("61", DIR, "CPPM – prisão provisória", "D",
  "CPPM, art. 222: quem relaxa a prisão ilegal é a autoridade JUDICIÁRIA, não a policial.",
  {"A": "Prevista (art. 221).", "B": "Prevista (art. 222).", "C": "Prevista (art. 223).",
   "D": "NÃO corresponde (gabarito).", "E": "Prevista (art. 227)."},
  "Relaxamento de prisão ilegal = autoridade judiciária.")
q("62-A", DIR, "Inviolabilidade de domicílio (STF, RE 603.616)", "E",
  "STF (Tema 280): a entrada forçada sem mandado, mesmo à noite, só é lícita quando amparada em "
  "fundadas razões, justificadas a posteriori, de que há flagrante delito no interior da casa. "
  "Simples desconfiança (movimentação de pessoas) não basta.",
  {"A": "Errada. Crime permanente exige fundadas razões prévias.",
   "B": "Errada. Não depende de ordem de comandante.",
   "C": "Errada. O resultado positivo posterior não legitima a entrada.",
   "D": "Errada.", "E": "CORRETA (gabarito da banca)."},
  "Fundadas razões ANTES da entrada; o resultado não convalida.",
  obs="A expressão “exceto em período noturno” não consta da tese do STF, que vale a qualquer hora.")
q("62-B", DIR, "Organizações criminosas (Lei 12.850/2013)", "B",
  "I (F): são QUATRO ou mais pessoas, para infrações com pena máxima superior a 4 anos ou de caráter "
  "transnacional. II (V): art. 1º, §2º, II. III (V): art. 4º, §7º-B. IV (F): descreve a AÇÃO "
  "CONTROLADA (art. 8º), não a infiltração.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Orcrim = 4+ pessoas; associação criminosa (CP, art. 288) = 3+.")
q("63", DIR, "Peculato culposo (CPM, art. 303)", "D",
  "CPM, art. 303, §4º: no peculato culposo, a reparação do dano antes da sentença irrecorrível "
  "extingue a punibilidade; se posterior, reduz de metade a pena imposta — causa especial de "
  "diminuição.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada. Só se anterior ao trânsito em julgado.",
   "D": "CORRETA.", "E": "Errada."},
  "Antes do trânsito: extingue; depois: reduz pela metade.")
q("64", DIR, "Estatuto do Desarmamento", "D",
  "Lei 10.826/2003, art. 25: armas apreendidas são encaminhadas pelo juiz ao Comando do Exército "
  "(destruição ou doação).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Destino das armas apreendidas = Comando do Exército.")
q("65", DIR, "Concausa preexistente", "A",
  "A diabetes é concausa preexistente relativamente independente: não rompe o nexo causal. Com "
  "dolo de matar e morte resultante, João responde por homicídio consumado.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada. Havia dolo.",
   "D": "Errada.", "E": "Errada. O dolo era de matar."},
  "Só a causa SUPERVENIENTE que por si só produz o resultado rompe o nexo (CP, art. 13, §1º).")
q("66", DIR, "Penas (CP)", "B",
  "CP, art. 66: atenuante inominada — circunstância relevante, anterior ou posterior ao crime, "
  "ainda que não prevista em lei.",
  {"A": "Errada. Há requisitos (pena ≤ 4 anos etc.).", "B": "CORRETA.",
   "C": "Errada. Sursis não se aplica às restritivas de direitos.",
   "D": "Errada. Reincidência conta do trânsito em julgado da condenação anterior.",
   "E": "Errada. Regime fechado obrigatório só acima de 8 anos."},
  "Art. 65: atenuantes nominadas; art. 66: inominada.")
q("67", DIR, "Medidas de segurança (CPM)", "B",
  "CPM, art. 110: medidas de segurança pessoais (detentivas e não detentivas) e PATRIMONIAIS "
  "(interdição de estabelecimento e confisco).",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Confisco recai sobre bens → patrimonial.")
q("68", DIR, "Soberania popular (CF, art. 14)", "C",
  "Exercida pelo sufrágio universal, voto direto e secreto e, nos termos da lei, por plebiscito, "
  "referendo e iniciativa popular.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "PRI: Plebiscito, Referendo, Iniciativa popular.")
q("69", DIR, "Estado laico (CF, art. 19, I)", "A",
  "É vedado aos entes federativos subvencionar cultos religiosos ou igrejas (ressalvada a "
  "colaboração de interesse público, na forma da lei).",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.",
   "D": "Errada. A igualdade entre religiões não afasta a vedação.", "E": "Errada."},
  "Laicidade: o Estado não financia nem embaraça cultos.")
q("70", DIR, "Tortura", "A",
  "Quem se omite diante da tortura, tendo o dever de evitá-la, responde pela figura do art. 1º, "
  "§2º, da Lei 9.455/1997 (detenção de 1 a 4 anos), e não pela mesma pena do torturador.",
  {"A": "INCORRETA (gabarito).", "B": "Correta.", "C": "Correta.",
   "D": "Correta (CF, art. 5º, XLIII).", "E": "Correta (art. 1º, §3º)."},
  "Omissão na tortura tem pena própria e menor.")
q("71", DIR, "Competência concorrente (CF, art. 24)", "A",
  "Na competência concorrente, a União edita normas gerais e os Estados suplementam, observando-as "
  "(art. 24, §§1º e 2º).",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada. Só há competência plena na falta de lei federal.",
   "D": "Errada.", "E": "Errada. Lei federal superveniente suspende a estadual no que contrariar."},
  "União: normas gerais; Estados: suplementar.")
q("72", DIR, "Lei de Drogas – prazos do inquérito", "D",
  "Lei 11.343/2006, art. 51: 30 dias (indiciado preso) e 90 dias (solto), podendo ser duplicados "
  "pelo juiz, ouvido o MP, mediante pedido da autoridade policial.",
  {"A": "Errada. 90 dias é para solto.",
   "B": "Errada. Na falta de perito oficial, admite-se pessoa idônea (art. 50, §1º).",
   "C": "Errada.", "D": "CORRETA.", "E": "Errada. O laudo de constatação é exigido (art. 50, §1º)."},
  "Drogas: 30 (preso) / 90 (solto), duplicáveis.")
q("73", DIR, "Concurso de agentes (CPM)", "D",
  "CPM, art. 53: “Quem, de qualquer modo, concorre para o crime incide nas penas a este cominadas.”",
  {"A": "Errada. Não se comunicam, salvo quando elementares.",
   "B": "Errada. O oficial que instiga inferiores tem a pena AGRAVADA.",
   "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Teoria monista (unitária).")
q("74", DIR, "Lei 6.677/1994 (BA) – requisito de ingresso", "E",
  "O dispositivo incluído em 2018 veda o ingresso de condenados por feminicídio ou violência "
  "doméstica contra a mulher, ressalvada a extinção da punibilidade.",
  {"A": "Errada.", "B": "Errada. A vedação alcança também os comissionados.",
   "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Legislação estadual da Bahia: leia as alterações recentes do Estatuto do Servidor.",
  obs="Questão de legislação estadual específica; confira a redação atual da Lei 6.677/1994.")
q("75", DIR, "Lei temporária (CPM, art. 4º)", "D",
  "A lei temporária ou excepcional é ULTRATIVA: aplica-se ao fato praticado durante sua vigência, "
  "mesmo depois de revogada e ainda que mais gravosa.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "Ultratividade das leis temporárias e excepcionais.")
q("76", DIR, "Convenção Americana – expressão e religião", "B",
  "I (F): o art. 13.3 proíbe restringir a expressão por meios indiretos. II (V): art. 13.4. "
  "III (V): art. 13.5. IV (V): art. 12.4.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Incompleta."},
  "Censura prévia só para espetáculos públicos (proteção de crianças e adolescentes).")
q("77", DIR, "Vedação ao retrocesso", "E",
  "Também chamado de efeito cliquet: os direitos humanos conquistados não podem retroceder, só avançar.",
  {"A": "Errada. É a legalidade/anterioridade penal.", "B": "Errada.",
   "C": "Errada. É a irretroatividade.", "D": "Errada. A regressão de regime é possível.",
   "E": "CORRETA."},
  "Proibição do retrocesso = efeito cliquet.")
q("78", DIR, "Peculato culposo", "B",
  "Negligência do policial que facilita a subtração da viatura por terceiro = peculato culposo.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada. Peculato-furto exige dolo.",
   "D": "Errada.", "E": "Errada."},
  "Repetida do Simulado 07.")
q("79", DIR, "Poderes administrativos", "B",
  "Reproduz o conceito legal de poder de polícia (CTN, art. 78).",
  {"A": "Errada. O regulamento não inova na ordem jurídica.", "B": "CORRETA.",
   "C": "Errada. Descreve o poder discricionário.", "D": "Errada. Descreve o poder hierárquico.",
   "E": "Errada. Descreve o poder disciplinar."},
  "As alternativas C, D e E trocam os conceitos entre si.")
q("80", DIR, "Vacância da Presidência (CF, arts. 79 e 81)", "B",
  "Vagando apenas o cargo de Presidente, o Vice-Presidente o sucede definitivamente (art. 79). "
  "Eleição indireta só ocorre se vagarem AMBOS os cargos nos dois últimos anos do mandato (art. 81, §1º).",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada. Só se o Vice também vagar.", "D": "Errada.",
   "E": "Errada."},
  "Vice sucede; os demais (Câmara, Senado, STF) apenas substituem.")

DISCURSIVA = {
    "tema": "O comportamento imprudente do brasileiro no trânsito e as formas de resolver esse problema",
    "estrutura": [
        ("Introdução",
         "Contextualize com dados dos textos de apoio: cerca de 40 mil mortes por ano, o Brasil entre "
         "os países com mais mortes no trânsito e 90% dos acidentes por falha humana (ONSV). Tese: o "
         "problema é sobretudo comportamental e cultural, e exige educação, fiscalização e responsabilização."),
        ("Desenvolvimento 1 – causas",
         "Imprudência, negligência e desrespeito às leis (53,7%), uso de celular, álcool, excesso de "
         "velocidade; componente cultural do “jeitinho”; a transformação do comportamento ao volante "
         "(Ramalho, ONSV)."),
        ("Desenvolvimento 2 – consequências",
         "Principal causa de morte entre jovens de 15 a 29 anos (OMS), sobrecarga do SUS, invalidez, "
         "perdas econômicas e impacto na segurança pública."),
        ("Conclusão – proposta",
         "Educação para o trânsito desde a escola (CTB, art. 76), fiscalização efetiva (Lei Seca, "
         "radares), campanhas permanentes, engenharia viária segura e punição rigorosa e célere, "
         "com atuação integrada das polícias, DETRAN e municípios."),
    ],
    "criterios": [
        "Usar os textos de apoio sem copiá-los.",
        "Responder às duas partes do tema: o comportamento imprudente E as formas de resolvê-lo.",
        "Norma-padrão, coesão e coerência.",
        "Proposta de intervenção detalhada (quem, o quê, como).",
        "Postura técnica, compatível com a de um futuro oficial.",
    ],
}
