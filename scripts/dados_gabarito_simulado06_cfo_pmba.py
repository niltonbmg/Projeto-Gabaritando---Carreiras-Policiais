"""
Gabarito comentado – Simulado 06 – CFO PMBA (Oficial QOPM – Tipo A).

Cada questão: n, disc, tema, gab, com (comentário), alts (análise por alternativa),
dica e, opcionalmente, obs (ressalva sobre a questão).
"""

Q = []


def q(n, disc, tema, gab, com, alts, dica, obs=None):
    Q.append(dict(n=n, disc=disc, tema=tema, gab=gab, com=com, alts=alts, dica=dica, obs=obs))


LP, ING, HUM, MAT, INF, DIR = ("Língua Portuguesa", "Língua Inglesa", "Ciências Humanas",
                               "Matemática", "Informática", "Direito")

# ===================== LÍNGUA PORTUGUESA =====================
q(1, LP, "Transitividade verbal", "E",
  "Em “Sentiu uma pontada na têmpora”, quem sente, sente ALGUMA COISA: o verbo pede complemento "
  "sem preposição. “Uma pontada” é o objeto direto; “na têmpora” é adjunto adverbial de lugar e "
  "“prenúncio de enxaqueca” é aposto do objeto.",
  {"A": "Errada. Não há objeto indireto; “na têmpora” é adjunto adverbial.",
   "B": "Errada. O verbo não tem sentido completo: sentiu o quê?",
   "C": "Errada. “Sentir” aqui é verbo nocional (de ação/percepção), não de ligação.",
   "D": "Errada. O complemento não vem regido de preposição.",
   "E": "CORRETA. VTD com objeto direto “uma pontada”."},
  "Faça a pergunta ao verbo: “sentir o quê?” → resposta sem preposição = objeto direto.")

q(2, LP, "Pontuação – valor expressivo", "B",
  "O ponto de interrogação marca a dúvida/questionamento interior da narradora (“Será que ninguém vai "
  "responder?”), típico do discurso indireto livre. A vírgula em “Ou não receberam, deixe checar…” "
  "separa as orações e marca a pausa em que a personagem passa da dúvida à ação de checar a mensagem.",
  {"A": "Errada. A interrogação não tem valor exclamativo.",
   "B": "CORRETA. Questionamento interno + pausa que introduz a checagem.",
   "C": "Errada. A interrogação não introduz explicação; não há orações subordinadas separadas ali.",
   "D": "Errada (parcial). A vírgula não marca “pausa necessária para o raciocínio”; ela separa orações "
        "coordenadas e anuncia a ação seguinte — a descrição de B é mais precisa.",
   "E": "Errada. Os sinais têm funções distintas."},
  "Em questões de pontuação expressiva, procure a alternativa que descreve a função de CADA sinal no "
  "contexto, sem generalizações.")

q(3, LP, "Concordância verbal e nominal", "A",
  "“Façam” e “estejam” estão no plural porque se referem a “pouquíssimas pessoas” (as duas orações "
  "substantivas são coordenadas e têm o mesmo sujeito). “Sempre à mão” é locução adverbial "
  "invariável — não há concordância nominal a ser feita, logo está adequada.",
  {"A": "CORRETA. Verbos no plural concordando com o sujeito; “à mão” plenamente adequado.",
   "B": "Errada. Não há concordância com “pessoa” (singular), e “à mão” está correto.",
   "C": "Errada. “Sempre à mão” não apresenta incorreção.",
   "D": "Errada. O núcleo do sujeito é “pessoas”, não “pouquíssimas” (adjunto).",
   "E": "Errada. “Mão” não concorda com advérbio — advérbios são invariáveis."},
  "Núcleo do sujeito é sempre o substantivo; pronomes indefinidos como “pouquíssimas” são adjuntos.")

q(4, LP, "Colocação pronominal", "D",
  "Em “Isso me faz refletir”, o pronome demonstrativo neutro “isso” é tradicionalmente apontado como "
  "palavra atrativa (assim como “tudo”, “aquilo”, “nada”), justificando a próclise. Além disso, a "
  "próclise é a colocação natural do português brasileiro e jamais estaria “incorreta” aqui.",
  {"A": "Errada. Não se recomenda a ênclise quando há palavra atrativa.",
   "B": "Errada para a banca: com o demonstrativo atrativo, a próclise é a indicada.",
   "C": "Errada. O pronome “me” é complemento de “faz” (verbo flexionado), não do infinitivo.",
   "D": "CORRETA. Próclise justificada pelo pronome demonstrativo “isso”.",
   "E": "Errada. “Faz” está no presente do indicativo, não no futuro."},
  "Palavras atrativas: negativas, advérbios, pronomes relativos, indefinidos e demonstrativos "
  "neutros, conjunções subordinativas.")

q(5, LP, "Acentuação gráfica", "E",
  "“Três” é MONOSSÍLABO TÔNICO terminado em “e(s)”; não é oxítona. A justificativa correta seria: "
  "acentuam-se os monossílabos tônicos terminados em a(s), e(s), o(s).",
  {"A": "Justificativa correta: será = oxítona terminada em “a”.",
   "B": "Justificativa correta: dúvida = proparoxítona (todas são acentuadas).",
   "C": "Justificativa correta: ninguém = oxítona terminada em “em”.",
   "D": "Justificativa correta: laboratório = paroxítona terminada em ditongo crescente.",
   "E": "INCORRETA (gabarito). Três é monossílabo tônico, não oxítona."},
  "Antes de classificar a tonicidade, conte as sílabas: monossílabos têm regra própria.")

q(6, LP, "Conjunções e preposições – valor semântico", "B",
  "“Mas” é conjunção coordenativa ADVERSATIVA: opõe a vantagem (economia de tempo) à reflexão sobre o "
  "custo. Em “para reflexão”, a preposição “para” indica FINALIDADE.",
  {"A": "Errada. “Mas” não é explicativa e “de tempo” não indica posse.",
   "B": "CORRETA. Oposição + finalidade.",
   "C": "Errada. “Mas” não é conclusiva; “com dois cliques” indica meio/instrumento.",
   "D": "Errada. Não há adição; “do imediato” é complemento nominal de “ansiedade”, não verbal.",
   "E": "Errada. “Mas” é coordenativa, e “para” não exprime causa."},
  "Teste de substituição: “mas” → “porém, contudo, todavia” (adversativas).")

q(7, LP, "Advérbios e locuções adverbiais", "D",
  "“Sem dúvida alguma” é locução adverbial de AFIRMAÇÃO, reforçando a certeza do argumento. “De tempo” "
  "não é advérbio: liga-se ao substantivo “economia” (economia de tempo), integrando o sintagma nominal.",
  {"A": "Próxima, mas imprecisa: “de tempo” não é, sozinha, uma “expressão nominal”, e sim parte do "
        "sintagma nominal “economia de tempo”. A banca considera D a formulação correta.",
   "B": "Errada. Não indica negação, e “de tempo” não é adjunto adverbial de tempo.",
   "C": "Errada. Não é intensidade; “nos trouxe” é verbo + pronome.",
   "D": "CORRETA. Afirmação + “de tempo” integrante de expressão nominal.",
   "E": "Errada. Indica certeza, não dúvida; “de tempo” não é causa."},
  "Locução com “de” presa a substantivo costuma ser locução adjetiva/complemento, não advérbio.",
  obs="As alternativas A e D são muito próximas; D é a mais precisa tecnicamente.")

q(8, LP, "Interpretação de texto", "A",
  "A pergunta retórica da autora problematiza se o imediatismo da comunicação digital estaria "
  "ampliando a ansiedade em tudo o que fazemos (relações pessoais e profissionais — o texto cita "
  "conversas, revisão do carro, exames, compras).",
  {"A": "CORRETA. Reflete o questionamento central do texto.",
   "B": "Errada. A autora não defende o passado como mais eficiente.",
   "C": "Errada. O texto sugere o oposto: aumento, não eliminação da ansiedade.",
   "D": "Errada. Não há crítica a quem demora a responder.",
   "E": "Errada. O texto abrange “tudo o que fazemos”."},
  "Perguntas retóricas expressam a tese do autor sob forma de questionamento.")

q(9, LP, "Flexão verbal – tempos e modos", "C",
  "“Receberam” está no pretérito perfeito do indicativo, 3ª pessoa do plural. “Vai (responder)” forma "
  "locução verbal com valor de futuro do presente (futuro perifrástico) — é a leitura adotada pela "
  "banca.",
  {"A": "Errada. “Vai” não é futuro do pretérito.",
   "B": "Errada. “Foi” está no pretérito PERFEITO, não imperfeito.",
   "C": "CORRETA. Pretérito perfeito plural + valor de futuro do presente.",
   "D": "Errada. “Responder” é infinitivo, não futuro do subjuntivo.",
   "E": "Errada. “Deixa” está no imperativo afirmativo, não no subjuntivo."},
  "Morfologicamente “vai” é presente do indicativo; na locução “ir + infinitivo”, expressa futuro.",
  obs="Rigor gramatical: “vai” isolado é presente do indicativo; a banca considera o valor da locução.")

q(10, LP, "Predicativo do sujeito", "A",
  "Em “esse é o tempo médio”, o verbo SER é de ligação; “esse” (que retoma “dois minutos”) é o sujeito "
  "e “o tempo médio” é o predicativo do sujeito.",
  {"A": "CORRETA. Verbo de ligação + predicativo atribuído a “esse”.",
   "B": "Errada. “Esse” é o sujeito, não o predicativo.",
   "C": "Análise aceitável em tese (em “seja esse retorno um texto…”, também há predicativo), mas a "
        "banca considera A, por ser a oração principal do período.",
   "D": "Errada. Há verbos de ligação (“é”, “seja”).",
   "E": "Errada. “Pela resposta” é complemento do verbo “esperar”, não predicativo."},
  "Verbo de ligação (ser, estar, parecer, ficar, permanecer…) → o termo após ele é predicativo.",
  obs="A alternativa C também descreve um predicativo; questão com possibilidade de recurso.")

q(11, LP, "Semântica – sinonímia, antonímia, heteronímia", "A",
  "Sinonímia é a relação entre palavras de significado semelhante/idêntico, em geral da mesma classe "
  "gramatical, intercambiáveis em contextos similares.",
  {"A": "CORRETA. Definição adequada de sinonímia.",
   "B": "Errada. Antonímia = significados opostos.",
   "C": "Errada. Antônimos, em regra, pertencem à mesma classe e não são intercambiáveis.",
   "D": "Errada. Heterônimos (ex.: bode/cabra) não substituem uns aos outros em qualquer contexto.",
   "E": "Errada. Sinônimos têm significados semelhantes, não diferentes."},
  "Palavras absolutas (“qualquer contexto”, “totalmente”) costumam invalidar a alternativa.")

q(12, LP, "Conjunção integrante", "D",
  "“Acho QUE não foi uma boa ideia…”: o “que” introduz oração que completa o verbo transitivo direto "
  "“achar” (acho isso). Trata-se de conjunção subordinativa integrante introduzindo oração "
  "substantiva objetiva direta.",
  {"A": "Errada. Não é advérbio.",
   "B": "Errada. A finalidade aparece depois, em “para que todos recebessem”.",
   "C": "Errada. Não há coordenação.",
   "D": "CORRETA. Integrante → substantiva objetiva direta.",
   "E": "Errada. Não retoma termo antecedente."},
  "Substitua a oração por “isso”: se funcionar, o “que” é integrante.")

q(13, LP, "Coesão – explicitação da informação", "D",
  "Nas alternativas A, B, C e E, a reescrita especifica o mesmo referente (qual congresso, qual "
  "deputado, qual rua, que sonata). Em D, a segunda frase não detalha “a lagoa”: acrescenta NOVA "
  "informação (a praia de Copacabana), ampliando o conteúdo, e não explicitando-o.",
  {"A": "Explicita: congresso de literatura francesa.",
   "B": "Explicita: nome do deputado e tema do projeto.",
   "C": "Explicita: localização da rua.",
   "D": "GABARITO. Acrescenta outro elemento, não explicita o original.",
   "E": "Explicita: autoria da sonata."},
  "Explicitar = especificar o mesmo termo; acrescentar outro referente não é explicitação.")

q(14, LP, "Interpretação – tema central", "D",
  "O texto de Sílvia Solci defende que a justiça não se reduz à legalidade: ela “deve estar do lado da "
  "igualdade”, emana do homem justo ou da sociedade que a tem como valor, e precisa ser desejada e "
  "conquistada pela luta (Ihering). É exatamente o que resume a alternativa D.",
  {"A": "Errada. O texto valoriza a luta pelo direito, não a condena.",
   "B": "Errada. Não discute eficácia por consenso de toda a sociedade.",
   "C": "Errada. O texto afirma que o povo brasileiro, na maioria, NÃO sabe lutar.",
   "D": "CORRETA. Síntese fiel: justiça que busca a equidade extrapola a justiça legal e nasce da luta.",
   "E": "Errada. O texto menciona o “caso de polícia”, mas não afirma que a luta sempre foi sufocada "
        "sem resultado — extrapolação."},
  "Tema é a ideia que perpassa o texto todo; detalhes de um parágrafo geram distratores.")

q(15, LP, "Dois-pontos", "D",
  "Após “em seu escopo de trabalho:”, o trecho “os desafios em organizações policiais” especifica qual "
  "é o “aspecto específico” mencionado — trata-se de um esclarecimento (explicação/aposto).",
  {"A": "Errada. Há um único elemento, não uma lista.",
   "B": "Errada. Não indica finalidade.",
   "C": "Errada. Não é exemplo, é a própria identificação do aspecto.",
   "D": "CORRETA. Esclarece o “aspecto específico”.",
   "E": "Errada. Não há discurso citado."},
  "Dois-pontos antes de aposto explicativo = esclarecimento.")

q(16, LP, "Orações adverbiais reduzidas – circunstância", "E",
  "“Ao persistirem os sintomas” equivale a “SE os sintomas persistirem”: oração reduzida de infinitivo "
  "com valor CONDICIONAL. “Contanto que seja bom” também exprime condição.",
  {"A": "Errada. “Ao contrário do que se previa” = conformidade/contraste.",
   "B": "Errada. “Quando você chegar” = tempo.",
   "C": "Errada. “Ao amanhecer” = tempo.",
   "D": "Errada. “À proporção que” = proporção.",
   "E": "CORRETA. “Contanto que” = condição."},
  "Na reduzida “ao + infinitivo”, desenvolva a oração para descobrir o valor (quando? se?).")

q(17, LP, "Regência verbal e sentido", "E",
  "No texto, “navegar o mundo” significa mover-se, orientar-se pelo mundo, explorá-lo. “Percorrer” é "
  "VTD e mantém sentido e correção: “percorrer o mundo”.",
  {"A": "Errada. “Divulgar” altera o sentido.",
   "B": "Errada. “Embarcar pelo mundo” muda sentido e regência.",
   "C": "Errada. “Externar” = manifestar; sem sentido no contexto.",
   "D": "Errada. “Buscar” = procurar; altera o sentido.",
   "E": "CORRETA. Mantém regência (VTD) e sentido."},
  "Na troca de verbos, cheque sentido E regência ao mesmo tempo.")

q(18, LP, "Redação oficial – ofício x memorando", "D",
  "Pela tradição da redação oficial cobrada em concursos, o OFÍCIO é a comunicação externa (entre "
  "órgãos/autoridades distintas) e o MEMORANDO é a comunicação interna, entre unidades de um mesmo "
  "órgão. Logo: pedido à SSP → ofício; aviso ao setor de logística → memorando.",
  {"A": "Errada. A distinção é o destinatário (externo x interno), não a extensão.",
   "B": "Errada. Inverte os conceitos.",
   "C": "Errada. Não são intercambiáveis.",
   "D": "CORRETA.",
   "E": "Errada. O memorando é mais ágil e simples, não mais solene."},
  "Ofício = fora; Memorando = dentro.",
  obs="O Manual de Redação da Presidência (3ª ed., 2018) unificou os expedientes sob o “padrão ofício”, "
      "mas editais estaduais continuam cobrando a distinção clássica.")

q(19, LP, "Redação oficial x redação estilística", "C",
  "A redação oficial exige impessoalidade, objetividade, clareza, concisão e formalidade. O trecho "
  "usa metáforas (“peso silencioso do tempo”), subjetividade e apelo emocional — marcas da redação "
  "estilística/literária.",
  {"A": "Errada. Expressividade subjetiva não cabe na redação oficial.",
   "B": "Errada. Metáforas são incompatíveis com a objetividade exigida.",
   "C": "CORRETA.",
   "D": "Errada. Linguagem figurada não é admitida para “elegância”.",
   "E": "Errada. O problema central é justamente o uso de recursos estilísticos."},
  "Na dúvida, a redação oficial sempre privilegia o sentido único e objetivo.")

q(20, LP, "Documentos oficiais", "A",
  "I – comunicação externa a outra autoridade: OFÍCIO. II – registro de deliberações de reunião: ATA. "
  "III – comunicação interna: MEMORANDO. IV – exposição de fatos, análise e conclusões de comissão: "
  "RELATÓRIO.",
  {"A": "CORRETA. Ofício – Ata – Memorando – Relatório.",
   "B": "Errada. Inverte ofício/memorando; parecer é opinião técnica.",
   "C": "Errada. Relatório e ata trocados.",
   "D": "Errada. Requerimento é pedido de particular; circular vai a vários destinatários.",
   "E": "Errada. Parecer e certidão não correspondem a II e IV."},
  "Ata = o que se decidiu; Relatório = o que se apurou; Parecer = o que se opina.")

# ===================== LÍNGUA INGLESA =====================
q(21, ING, "Plural irregular e demonstrativos", "D",
  "“Knife” forma plural irregular “knives”. Em (1) o verbo “have” pede plural → “These knives”. Em "
  "(2) “is unique” pede singular e “over there” indica distância → “that large knife”.",
  {"A": "Errada. “Those” é plural, mas “knife” singular e “is” → incoerente.",
   "B": "Errada. “Knifes” não existe.",
   "C": "Errada. “This knifes” mistura singular e plural.",
   "D": "CORRETA.",
   "E": "Errada. “This knives” e “that knives” são agramaticais."},
  "this/that = singular; these/those = plural; that/those = longe (over there).")

q(22, ING, "Ordem dos advérbios", "E",
  "Ordem padrão em inglês: verbo + (grau + modo) + lugar + tempo → “He spoke very carefully in the "
  "library today.”",
  {"A": "Errada. Falta preposição e a ordem está errada.",
   "B": "Errada. Inversão sujeito/verbo indevida.",
   "C": "Errada. “Library” sem preposição e “carefully very” invertido.",
   "D": "Errada. Estrutura agramatical.",
   "E": "CORRETA."},
  "Memorize M-P-T: Manner, Place, Time.")

q(23, ING, "Conectivos – “like”", "E",
  "Em “potential real-world benefits LIKE the added security…”, “like” equivale a “such as / for "
  "example”: introduz exemplos (ilustração) dos benefícios.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.",
   "E": "CORRETA. “Like” = such as → illustration."},
  "like / such as / for instance = exemplificação.")

q(24, ING, "Vocabulário – sinônimos e antônimos", "A",
  "bolster = strengthen (sinônimo); scrutiny = oversight (sinônimo); hinder (impedir) ↔ assist "
  "(antônimo); detrimental (prejudicial) ↔ beneficial (antônimo).",
  {"A": "CORRETA.",
   "B": "Errada. “Undermine” é antônimo de bolster.",
   "C": "Errada. “Neglect” é antônimo de scrutiny.",
   "D": "Errada. Repete “scrutiny” e “obstruct” é sinônimo (não antônimo) de hinder.",
   "E": "Errada. “Harmful” é sinônimo de detrimental."},
  "Leia o comando com atenção: SINÔNIMOS para as duas primeiras, ANTÔNIMOS para as duas últimas.")

q(25, ING, "Preposições – expressões fixas", "B",
  "at a moment's notice; arrive at the scene; keep (someone) at bay; at the request of. Todas com “at”.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Collocations com “at” são frequentes em textos policiais: at the scene, at bay, at the request.")

# ===================== CIÊNCIAS HUMANAS =====================
q(26, HUM, "Brasil Colônia – pecuária", "E",
  "1 (V) O couro era intensamente usado e exportado (“civilização do couro”). 2 (V) A pecuária "
  "interiorizou a colonização em áreas impróprias à cana (sertão, Centro-Oeste, pampas do Sul). "
  "3 (V) Abastecia de carne, charque, leite e derivados as regiões mineradoras e os núcleos urbanos.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.",
   "E": "CORRETA. V – V – V."},
  "Pecuária colonial = interiorização + abastecimento interno + couro.")

q(27, HUM, "Tropicalismo", "B",
  "A Tropicália mesclou referências internacionais (rock, guitarra elétrica, pop art) com tradições "
  "brasileiras (baião, samba), unindo o arcaico e o moderno numa linha antropofágica.",
  {"A": "Errada. Rejeitava o nacionalismo purista.",
   "B": "CORRETA.",
   "C": "Errada. Não se restringia aos povos indígenas.",
   "D": "Errada. Não se limitava à cultura afro-brasileira.",
   "E": "Errada. Não buscava ruptura total com o tradicional, e sim sua fusão com o moderno."},
  "Palavras como “somente”, “único”, “total” costumam indicar alternativa errada.")

q(28, HUM, "Fases do capitalismo", "E",
  "1º parêntese: globalização e TI → informacional (4). 2º: investimentos, bancos, bolsas → "
  "financeiro (3). 3º: Revolução Industrial, maquinofatura, liberalismo → industrial (2). 4º: "
  "mercantilismo, absolutismo → comercial (1).",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.",
   "E": "CORRETA. 4 – 3 – 2 – 1."},
  "Comercial (séc. XV-XVIII) → Industrial (XVIII-XIX) → Financeiro (XIX-XX) → Informacional (fim do XX).")

q(29, HUM, "Constituição de 1891", "D",
  "A primeira Constituição republicana separou Estado e Igreja (Estado laico), instituiu o casamento "
  "civil, secularizou cemitérios e garantiu liberdade de culto.",
  {"A": "Errada. O Poder Moderador foi extinto (era da Constituição de 1824).",
   "B": "Errada. Analfabetos continuaram excluídos do voto.",
   "C": "Errada. Adotou o federalismo, com ampla autonomia estadual.",
   "D": "CORRETA.",
   "E": "Errada. Adotou o presidencialismo."},
  "1891: república, federalismo, presidencialismo, Estado laico, voto aberto masculino alfabetizado.")

q(30, HUM, "Sociedade e natureza", "E",
  "O desmatamento amazônico está associado à expansão da fronteira agropecuária (pecuária e soja) e a "
  "políticas de integração/incentivo econômico (rodovias, crédito, colonização).",
  {"A": "Errada. Desertos têm exploração (petróleo, minérios, oásis, turismo).",
   "B": "Errada. A urbanização altera fortemente ecossistemas.",
   "C": "Errada. As mudanças climáticas atuais têm forte componente antrópico.",
   "D": "Errada. A exploração de recursos é grande causa de perda de biodiversidade.",
   "E": "CORRETA."},
  "Alternativas que negam a ação humana sobre o meio quase sempre estão erradas.")

q(31, HUM, "Reforma Protestante – Calvinismo", "C",
  "O núcleo da doutrina calvinista é a PREDESTINAÇÃO: Deus escolheu, desde a eternidade, os salvos, "
  "independentemente das obras humanas.",
  {"A": "Errada. O calvinismo valoriza a Bíblia (Sola Scriptura).",
   "B": "Errada. Difundiu-se justamente na Suíça e Países Baixos; Península Ibérica permaneceu católica.",
   "C": "CORRETA.",
   "D": "Errada. Contraria a predestinação; Genebra teve forte ligação entre religião e vida pública.",
   "E": "Errada. Havia organização comunitária (consistórios, presbíteros)."},
  "Calvinismo = predestinação + valorização do trabalho (Weber).")

q(32, HUM, "Antiguidade – Etruscos", "E",
  "Os etruscos adaptaram um alfabeto de origem fenícia (por intermédio grego) e, embora a escrita seja "
  "legível, a língua etrusca ainda é pouco compreendida.",
  {"A": "Errada. Há abundantes registros arqueológicos (necrópoles, afrescos).",
   "B": "Errada. Organizavam-se em cidades-Estado (dodecápolis), sem rei único.",
   "C": "Errada. A república foi criada pelos romanos após expulsar os reis etruscos (509 a.C.).",
   "D": "Errada. Eram muito religiosos (arúspices, templos).",
   "E": "CORRETA."},
  "Etruscos: cidades-Estado, religiosidade, influência sobre Roma (monarquia etrusca).")

q(33, HUM, "Estado e políticas territoriais", "C",
  "As políticas territoriais são instrumentos de organização e controle do espaço e, como o próprio "
  "texto afirma, produzem efeitos contraditórios: crescimento e integração, mas também conflitos "
  "fundiários e socioambientais.",
  {"A": "Errada. “Exclusivamente econômico” contraria o texto.",
   "B": "Errada. O Estado executa obras e planos diretamente.",
   "C": "CORRETA.",
   "D": "Errada. A integração não elimina automaticamente as desigualdades.",
   "E": "Errada. “Necessariamente” — o texto indica efeitos ambíguos."},
  "Quando o texto fala em efeitos contraditórios, a alternativa certa reconhece a contradição.")

q(34, HUM, "Climatologia – Semiárido", "A",
  "O clima tropical semiárido (Sertão nordestino) apresenta chuvas escassas e irregulares (< 800 mm), "
  "baixa nebulosidade, alta evaporação e temperaturas elevadas.",
  {"A": "CORRETA.",
   "B": "Errada. Descreve o tropical típico (Brasil Central).",
   "C": "Errada. Descreve o equatorial/tropical úmido.",
   "D": "Errada. Descreve o subtropical/tropical de altitude.",
   "E": "Errada. Descreve o tropical litorâneo (úmido)."},
  "Semiárido = pouca chuva + irregularidade + alta evaporação.")

q(35, HUM, "Cruzadas", "B",
  "Apesar da motivação religiosa oficial, as Cruzadas reativaram as rotas comerciais com o Oriente, "
  "fortalecendo cidades italianas e a burguesia mercantil (renascimento comercial e urbano).",
  {"A": "Errada. Havia fortes interesses econômicos e políticos.",
   "B": "CORRETA.",
   "C": "Errada. Não houve unificação política sob o papado.",
   "D": "Errada. Aumentaram as trocas com o Oriente.",
   "E": "Errada. Contribuíram para a crise do feudalismo."},
  "Consequência clássica das Cruzadas: renascimento comercial e urbano.")

q(36, HUM, "Primeira Guerra Mundial – causas", "A",
  "1 (V) Pan-eslavismo e pangermanismo acirraram tensões nos Bálcãs. 2 (V) Disputas imperialistas na "
  "África/Ásia. 3 (F) O revanchismo foi FRANCÊS: a França perdeu a Alsácia-Lorena para a "
  "Prússia/Alemanha em 1871.",
  {"A": "CORRETA. V – V – F.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "Errada."},
  "Quem perdeu a Alsácia-Lorena foi a França — por isso, revanchismo francês.")

q(37, HUM, "Brasil Império – viagens imperiais ao Sul", "D",
  "I (F) As visitas mobilizaram intensamente as localidades. II (V) D. Pedro I foi ao Sul em 1826 em "
  "razão da Guerra da Cisplatina. III (V) A viagem de D. Pedro II (1845) ocorreu logo após a Paz de "
  "Ponche Verde (fim da Farroupilha), para reforçar a autoridade imperial e a unidade. IV (F) O Sul "
  "não tinha “histórico de proximidade”: a Farroupilha (1835-1845) evidenciou tensões com o governo central.",
  {"A": "Errada. IV é falsa.", "B": "Errada. I é falsa.", "C": "Errada. I é falsa.",
   "D": "CORRETA. II e III.", "E": "Errada. IV é falsa."},
  "Sul no Império = Cisplatina (1825-28) e Farroupilha (1835-45).")

q(38, HUM, "Guerra de Canudos", "E",
  "Canudos (1896-1897), no sertão da BAHIA, liderada por Antônio Conselheiro, foi resistência sertaneja "
  "à estrutura latifundiária, à miséria e à repressão oficial.",
  {"A": "Errada. A região era marcada pela miséria.",
   "B": "Errada. Mistura com a Guerra do Contestado (SC/PR).",
   "C": "Errada. Canudos ficava na Bahia, não em Pernambuco.",
   "D": "Errada. Foi o Exército que destruiu e incendiou o arraial.",
   "E": "CORRETA."},
  "Tema obrigatório para PMBA: Canudos (BA) x Contestado (SC/PR).")

q(39, HUM, "Conjuração Baiana (1798)", "D",
  "A Revolta dos Alfaiates teve caráter popular e propôs república, igualdade racial, fim do domínio "
  "português e ABOLIÇÃO DA ESCRAVIDÃO.",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.",
   "D": "CORRETA.",
   "E": "Errada. Não era objetivo central explícito."},
  "Diferença-chave: Inconfidência Mineira (elite, sem abolição) x Conjuração Baiana (popular, abolicionista).")

q(40, HUM, "Mudanças climáticas e desigualdade", "C",
  "Vulnerabilidade = exposição ao risco + fragilidade socioeconômica + capacidade desigual de "
  "adaptação e recuperação — síntese do texto (justiça climática).",
  {"A": "Errada. Os mais pobres emitem MENOS gases.",
   "B": "Errada. Os impactos são socialmente desiguais.",
   "C": "CORRETA.",
   "D": "Errada. Fatores socioeconômicos ganham importância.",
   "E": "Errada. Políticas podem beneficiar desigualmente."},
  "“Justiça climática”: quem menos emite costuma ser quem mais sofre.")

q(41, HUM, "Desenvolvimento desigual e combinado", "D",
  "O conceito indica que regiões com níveis distintos de desenvolvimento estão INTERLIGADAS por fluxos "
  "econômicos e sociais; as transformações em uma afetam as demais.",
  {"A": "Errada. Não há uniformidade.",
   "B": "Errada. Nega a interdependência (o “combinado”).",
   "C": "Errada. Nega a interação entre regiões.",
   "D": "CORRETA.",
   "E": "Errada. Norte e Nordeste não são isolados."},
  "“Combinado” = interligação; desconfie de alternativas com “isolamento” ou “sem interação”.")

q(42, HUM, "Globalização e hierarquias", "D",
  "A globalização integra mercados, mas mantém (e recria) assimetrias de poder e tecnologia entre "
  "centro e periferia, como afirma o texto.",
  {"A": "Errada. Não reduziu significativamente as desigualdades.",
   "B": "Errada. Os Estados não perderam “completamente” sua capacidade.",
   "C": "Errada. A DIT continua hierarquizada.",
   "D": "CORRETA.",
   "E": "Errada. A geopolítica continua relevante."},
  "Absolutos (“completamente”, “todos”, “exclusivamente”) indicam erro.")

q(43, HUM, "Modernização do campo", "A",
  "Agricultura de precisão e automação elevam a produtividade e inserem o campo nas cadeias globais "
  "de produção e consumo (agronegócio).",
  {"A": "CORRETA.",
   "B": "Errada. O texto trata de tecnologias de ponta e integração global.",
   "C": "Errada. Não responde ao comando (setor rural).",
   "D": "Errada. Ocorre o inverso: maior conexão global.",
   "E": "Errada. O setor rural se integra, não perde papel."},
  "Responda ao comando: o foco é o impacto da tecnologia no SETOR RURAL.")

q(44, HUM, "Impactos ambientais do garimpo", "E",
  "O garimpo não adota práticas artesanais para “contornar” a legislação; ao contrário, tem se "
  "mecanizado (balsas, dragas, PCs) e usa mercúrio de forma intensa e ilegal, contaminando rios, solos e ar.",
  {"A": "Verdadeira. Desmatamento e assoreamento.",
   "B": "Verdadeira. Mecanização gera óleos e detergentes nos rios.",
   "C": "Verdadeira. Mercúrio metálico e vapor.",
   "D": "Verdadeira. Erosão das margens leva sedimentos e mercúrio.",
   "E": "INCORRETA (gabarito)."},
  "Atenção ao comando “INCORRETA”.")

# ===================== MATEMÁTICA =====================
q(45, MAT, "Análise combinatória – anagramas com repetição", "A",
  "PINOQUIO: consoantes P, N, Q fixas. Permutam-se as 5 vogais I, O, U, I, O (I e O repetidos 2 vezes). "
  "P = 5! / (2!·2!) = 120 / 4 = 30.",
  {"A": "CORRETA. 5!/(2!2!) = 30.",
   "B": "Errada. Permutaria todas as letras sem fixar consoantes.",
   "C": "Errada. Sem sentido combinatório.",
   "D": "Errada. Não fixa as consoantes.",
   "E": "Errada. Usa 3! (consoantes) em vez das vogais."},
  "Fixou posições? Permute só o que sobra e divida pelas repetições.")

q(46, MAT, "Média ponderada x média simples", "D",
  "Média geral (ponderada) = (20·72 + 30·68 + 25·80 + 15·84 + 10·96) / 100 = (1440 + 2040 + 2000 + "
  "1260 + 960) / 100 = 7700 / 100 = 77. Média simples = (72 + 68 + 80 + 84 + 96) / 5 = 400 / 5 = 80. "
  "77 < 80: a média geral é INFERIOR, pois os pelotões maiores (I e II) têm as menores médias.",
  {"A": "Errada. As médias não coincidem necessariamente.",
   "B": "Errada. O total múltiplo de 5 é irrelevante.",
   "C": "Errada. A média geral é 77.",
   "D": "CORRETA.",
   "E": "Errada. A média geral é 77, não 76,8."},
  "Média ponderada “puxa” para os valores dos grupos mais numerosos.")

q(47, MAT, "Frações", "A",
  "Resta 1 − 2/3 = 1/3. O amigo come 1/4 de 1/3 = 1/12. Sobra 1/3 − 1/12 = 4/12 − 1/12 = 3/12 = 1/4.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.",
   "E": "Errada. 1/12 é a parte comida pelo amigo."},
  "“Da parte restante” → multiplique a fração pelo que sobrou, não pelo todo.")

q(48, MAT, "Juros compostos", "B",
  "Montante M = x·(1,01)¹². Rendimento = M − x = 580 ⇒ x·1,01¹² = 580 + x.",
  {"A": "Errada. Usa a taxa e não o fator (1 + i).",
   "B": "CORRETA.",
   "C": "Errada.", "D": "Errada. Expoente 12x sem sentido.", "E": "Errada."},
  "Rendeu = juros; Montante = capital + juros.")

q(49, MAT, "Conjuntos numéricos e notação científica", "C",
  "I (F): ⁴√2 não é inteiro. II (F): dízimas periódicas são racionais. III (F): 23,5·10⁷ não está em "
  "notação científica; o correto é 2,35·10⁸. IV (V): 2/√2 = 2√2/2 = √2. Apenas uma correta.",
  {"A": "Errada.", "B": "Errada. III é falsa.", "C": "CORRETA. Somente IV.", "D": "Errada."},
  "Notação científica: 1 ≤ |a| < 10.")

q(50, MAT, "Trigonometria – identidades", "A",
  "sec²x − tg²x = 1 (identidade). Então f(x) = 1 − cos x. Como cos x ≤ 1, o mínimo é 1 − 1 = 0 "
  "(em x = 2kπ — onde sec e tg estão definidas).",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada.", "D": "Errada.",
   "E": "Errada. −2 ignoraria a identidade."},
  "1 + tg²x = sec²x → sec²x − tg²x = 1.")

q(51, MAT, "Geometria plana – áreas", "E",
  "Área do terreno: 80 × 60 = 4800 m². Triângulo superior: base 20 m (lado superior) e altura 60 m "
  "→ 20·60/2 = 600 m². Triângulo inferior: base 20 m (lado esquerdo) e altura 80 m → 20·80/2 = 800 m². "
  "Restante: 4800 − 600 − 800 = 3400 m².",
  {"A": "Errada.", "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Triângulo com vértice no canto oposto: a altura é o outro lado do retângulo.")

q(52, MAT, "Função quadrática – sinal", "A",
  "a < 0 → parábola com concavidade para baixo; Δ < 0 → não toca o eixo x. Logo, a parábola fica "
  "inteiramente abaixo do eixo: f(x) < 0 para todo x real.",
  {"A": "CORRETA.", "B": "Errada.", "C": "Errada. Δ < 0 impede troca de sinal.",
   "D": "Errada.", "E": "Errada."},
  "Δ < 0: f tem o sinal de a para todo x.")

q(53, MAT, "Sistemas lineares – Cramer", "B",
  "S: ax − 2y = 1; bx + 4y = 5. Determinante D = a·4 − (−2)·b = 4a + 2b. Sistema possível e "
  "determinado ⇔ D ≠ 0 ⇔ 2a + b ≠ 0 ⇔ 2a ≠ −b.",
  {"A": "Errada.", "B": "CORRETA.", "C": "Errada. Sinal trocado.",
   "D": "Errada.", "E": "Errada. 2a + b = 0 torna o sistema impossível/indeterminado."},
  "SPD ⇔ determinante dos coeficientes ≠ 0.")

q(54, MAT, "Equações produto", "C",
  "Raízes reais: x = 0, x = 1, x = −3 (x² + 16 = 0 não tem raiz real; as complexas ±4i somam 0). "
  "Soma = 0 + 1 − 3 = −2.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Produto nulo: iguale cada fator a zero.")

# ===================== INFORMÁTICA =====================
q(55, INF, "Intranet", "E",
  "Intranet é uma rede privada, de uso interno de uma organização, que usa as mesmas tecnologias da "
  "internet (TCP/IP, HTTP).",
  {"A": "Errada.", "B": "Errada. Internet é pública.", "C": "Errada.", "D": "Errada.",
   "E": "CORRETA."},
  "Intranet = interna; Extranet = acesso externo controlado; Internet = pública.")

q(56, INF, "Hardware – memória cache", "A",
  "A cache é memória de altíssima velocidade (SRAM) integrada ao processador, que guarda dados e "
  "instruções usados com frequência, reduzindo o tempo de acesso à RAM.",
  {"A": "CORRETA.", "B": "Errada. ROM é somente leitura (firmware).",
   "C": "Errada. Nuvem é armazenamento remoto.", "D": "Errada. RAM é memória principal, mais lenta.",
   "E": "Errada. Threads são fluxos de execução."},
  "Hierarquia: registradores > cache > RAM > disco.")

q(57, INF, "Word x Writer", "D",
  "Tabelas, estilos, mala direta e exportação para PDF são recursos nativos de ambos. A colaboração em "
  "tempo real diretamente no arquivo é recurso do Word (via OneDrive/Microsoft 365), não nativo do "
  "LibreOffice Writer (desktop).",
  {"A": "Comum aos dois.", "B": "Comum aos dois.", "C": "Comum aos dois.",
   "D": "GABARITO. Não é nativa do Writer.", "E": "Comum aos dois."},
  "Coautoria em tempo real = ecossistema em nuvem (Microsoft 365/Google Docs).")

q(58, INF, "Backup", "B",
  "Para dados confidenciais com recuperação rápida e segura, o backup COMPLETO e DIÁRIO restaura tudo "
  "de uma vez (sem depender de cadeia de incrementais) e o armazenamento em servidor externo protege "
  "contra perda do local de origem.",
  {"A": "Errada. Incremental exige toda a cadeia para restaurar (mais lento).",
   "B": "CORRETA.",
   "C": "Errada. Mesmo servidor = ponto único de falha.",
   "D": "Errada. Semanal = janela de perda grande.",
   "E": "Errada. Completo só mensal; restauração mais lenta."},
  "Completo = restauração mais rápida; Incremental = backup mais rápido.")

q(59, INF, "Certificação e assinatura digital", "C",
  "1 (F): não se restringe à intranet e não se aplica a “plataformas analógicas”; usa par de chaves "
  "assimétricas. 2 (F): garante NÃO repúdio e usa criptografia ASSIMÉTRICA. 3 (V): a AC valida a "
  "identidade e emite o certificado com dados do titular e sua chave pública.",
  {"A": "Errada.", "B": "Errada.", "C": "CORRETA. F – F – V.", "D": "Errada.", "E": "Errada."},
  "Assinatura digital: autenticidade, integridade e NÃO repúdio; criptografia assimétrica.")

# ===================== DIREITO =====================
q(60, DIR, "IPM – instauração (CPPM, art. 10)", "B",
  "O art. 10 do CPPM prevê a instauração por portaria: de ofício; por determinação ou delegação da "
  "autoridade militar superior; em virtude de REQUISIÇÃO DO MINISTÉRIO PÚBLICO (I); por decisão do STM; "
  "a REQUERIMENTO DA PARTE OFENDIDA (IV); ou quando sindicância apurar indício de crime. Não há "
  "previsão de requisição do Juiz de Direito ou do Juiz Auditor.",
  {"A": "Errada. III não consta.", "B": "CORRETA. I e IV.", "C": "Errada. II não consta.",
   "D": "Errada.", "E": "Errada."},
  "No CPPM não há requisição judicial de IPM (sistema acusatório).")

q(61, DIR, "Segurança pública – Polícia Federal (CF, art. 144, §1º)", "E",
  "“A polícia federal, instituída por lei como órgão PERMANENTE, organizado e mantido pela UNIÃO e "
  "estruturado em CARREIRA…”",
  {"A": "Errada. Não é provisório.", "B": "Errada. Mantido só pela União.",
   "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "PF, PRF e PFF: permanentes, mantidas pela União, estruturadas em carreira.")

q(62, DIR, "Corpo de delito", "B",
  "1 (F): o corpo de delito abrange todos os vestígios materiais, inclusive lesões psíquicas "
  "periciáveis. 2 (V, na ótica da banca): o exame recai sobre qualquer objeto/pessoa que guarde "
  "vestígio do crime. 3 (V): infrações não transeuntes (facti permanentis) deixam vestígios. 4 (F): o "
  "art. 158 do CPP veda que a confissão supra o exame.",
  {"A": "Errada. A 4ª é falsa.", "B": "CORRETA. F – V – V – F.", "C": "Errada.",
   "D": "Errada.", "E": "Errada."},
  "CPP, art. 158: confissão NÃO supre o exame de corpo de delito.",
  obs="O item 2 é discutível (“corpo” no sentido de qualquer vestígio); B é a única sequência compatível.")

q(63, DIR, "Direito Penal Militar – casos", "C",
  "Não existe no CPM o tipo “instigação a abandono de posto”. A instigação a crime que não chega a ser "
  "ao menos tentado não é punível como participação; a conduta poderia, em tese, amoldar-se ao "
  "incitamento (art. 155 do CPM), mas não ao crime nominado na alternativa.",
  {"A": "Correta. O cônjuge que favorece o desertor fica isento de pena (art. 193, parágrafo único, CPM).",
   "B": "Correta. Furto tentado e lesão grave (incapacidade por mais de 30 dias).",
   "C": "INCORRETA (gabarito). Tipo penal inexistente; o crime instigado nem foi tentado.",
   "D": "Correta. Motim com militares armados configura revolta (art. 149, parágrafo único, CPM).",
   "E": "Correta. Ameaçar fazer o que é lícito (comunicar disciplinarmente) não configura ameaça."},
  "Participação é acessória: sem início de execução pelo autor, não há punição do partícipe.")

q(64, DIR, "Prisão em flagrante (CPP)", "E",
  "CPP, art. 304, §2º: a falta de testemunhas não impede o auto de prisão em flagrante; com o "
  "condutor, devem assiná-lo pelo menos duas pessoas que tenham testemunhado a apresentação do preso.",
  {"A": "Errada. Flagrante presumido/ficto (art. 302, IV).",
   "B": "Errada. Há também flagrante impróprio e presumido (art. 302, III e IV).",
   "C": "Errada. O flagrante não impede, por si só, a fiança.",
   "E": "CORRETA."},
  "Testemunhas de apresentação (instrumentárias) suprem a falta de testemunhas do fato.",
  obs="A questão não apresenta alternativa D no caderno.")

q(65, DIR, "Convenção Interamericana contra o Racismo", "C",
  "Discriminação INDIRETA: ocorre quando um critério aparentemente neutro (exigir conhecimento prévio "
  "de todos) gera desvantagem particular a pessoas de determinado grupo racial, sem justificativa "
  "razoável (art. 1.2 da Convenção).",
  {"A": "Errada. Agravada envolve múltiplos fatores discriminatórios simultâneos.",
   "B": "Errada. Direta exige tratamento expressamente diferenciado por raça.",
   "C": "CORRETA.",
   "D": "Errada. Tratamento formalmente igual pode gerar discriminação indireta.",
   "E": "Errada."},
  "Critério neutro + efeito desproporcional sobre grupo = discriminação indireta.")

q(66, DIR, "Extorsão mediante sequestro", "D",
  "Extorsão mediante sequestro (CP, art. 159) é crime FORMAL: consuma-se com a privação da liberdade "
  "com o fim de obter vantagem como resgate, ainda que este não seja pedido ou recebido.",
  {"A": "Errada. A finalidade de resgate qualifica o fato como art. 159.",
   "B": "Errada.", "C": "Errada. Já houve consumação.",
   "D": "CORRETA.", "E": "Errada."},
  "Súmula 96 do STJ (análoga): extorsão consuma-se independentemente da vantagem.")

q(67, DIR, "Incitação ao crime (CP, art. 286)", "C",
  "“Incitar, publicamente, a prática de crime” — conclamar manifestantes a incendiar prédios.",
  {"A": "Errada. Apologia (art. 287) é enaltecer crime já praticado ou seu autor.",
   "B": "Errada.", "C": "CORRETA.", "D": "Errada.", "E": "Errada."},
  "Incitação = estimular crime futuro; Apologia = elogiar crime passado.")

q(68, DIR, "Abuso de autoridade – efeitos da condenação", "A",
  "Lei 13.869/2019, art. 4º: II – inabilitação para cargo, mandato ou função pública por 1 a 5 anos; "
  "III – perda do cargo. Parágrafo único: os efeitos dos incisos II e III são CONDICIONADOS à "
  "REINCIDÊNCIA em crime de abuso de autoridade e NÃO SÃO AUTOMÁTICOS, devendo ser declarados "
  "motivadamente na sentença.",
  {"A": "CORRETA.",
   "B": "Errada. O prazo é de 1 a 5 anos.",
   "C": "Errada. Depende de reincidência.",
   "D": "Errada. Não é automático.",
   "E": "Errada. Não é automático e depende de reincidência."},
  "Abuso de autoridade: inabilitação 1–5 anos + perda do cargo → reincidência + não automáticos.")

q(69, DIR, "Crimes militares – correspondência conduta/tipo", "A",
  "Art. 183 do CPM (insubmissão): “Deixar de apresentar-se o convocado à incorporação, dentro do prazo "
  "que lhe foi marcado, ou, apresentando-se, ausentar-se antes do ato oficial de incorporação.”",
  {"A": "CORRETA.",
   "B": "Errada. A conduta descrita é deserção (art. 187).",
   "C": "Errada. É “omissão de oficial” (art. 194).",
   "D": "Errada. É “dormir em serviço” (art. 203).",
   "E": "Errada. É “omissão de providências para evitar danos” (art. 199)."},
  "Insubmissão = convocado (civil); Deserção = militar ausente por mais de 8 dias.")

q(70, DIR, "Princípios da Administração Pública", "D",
  "I – deixar de publicar ato de provimento de cargo fere a PUBLICIDADE. II – deixar de indicar por "
  "inimizade pessoal fere a IMPESSOALIDADE.",
  {"A": "Errada. “Ineficiência” não é princípio.",
   "B": "Errada. “Discriminação” não é princípio.",
   "C": "Errada.", "D": "CORRETA.", "E": "Errada."},
  "LIMPE: Legalidade, Impessoalidade, Moralidade, Publicidade, Eficiência.")

q(71, DIR, "Código Penal Militar – crimes contra o serviço", "C",
  "Art. 202 do CPM: “Embriagar-se o militar, quando em serviço, ou apresentar-se embriagado para "
  "prestá-lo.”",
  {"A": "Errada. Se oficial, a pena é aumentada de 1/3 (art. 196, §1º).",
   "B": "Errada. Qualquer militar pode dormir em serviço (sentinela, vigia etc.).",
   "C": "CORRETA.",
   "D": "Errada. Não há essa causa de aumento no art. 195.",
   "E": "Errada. Abandono de posto não admite forma culposa."},
  "Memorize os tipos de serviço: abandono de posto (195), descumprimento de missão (196), "
  "dormir (203), embriaguez (202).")

q(72, DIR, "Princípios fundamentais (CF, arts. 1º a 4º)", "E",
  "Art. 1º, IV: são fundamentos “os valores sociais do trabalho e da livre iniciativa”.",
  {"A": "Errada. Independência nacional é princípio das relações internacionais (art. 4º, I).",
   "B": "Errada. São objetivos fundamentais (art. 3º, III).",
   "C": "Errada. A finalidade é a “comunidade latino-americana de nações” (art. 4º, par. único).",
   "D": "Errada. Autodeterminação dos povos é princípio das relações internacionais (art. 4º, III).",
   "E": "CORRETA."},
  "Fundamentos (SO-CI-DI-VA-PLU): Soberania, Cidadania, Dignidade, Valores sociais, Pluralismo.")

q(73, DIR, "Deserção", "E",
  "A deserção (art. 187, CPM) é crime de MERA CONDUTA e permanente: consuma-se com a ausência sem "
  "licença por mais de 8 dias, independentemente de resultado.",
  {"A": "Errada. O inventário é feito com duas testemunhas, não com familiar.",
   "B": "Errada. Crime de mera conduta dispensa demonstrar prejuízo.",
   "C": "Errada. A deserção especial também exige termo de deserção.",
   "D": "Errada. A inspeção e reinclusão após aptidão referem-se ao praça SEM estabilidade.",
   "E": "CORRETA."},
  "Deserção: mera conduta, permanente, prazo de graça de 8 dias.")

q(74, DIR, "Repartição de competências legislativas", "B",
  "CF, art. 24, I: compete à União, aos Estados e ao DF legislar concorrentemente sobre direito "
  "tributário, FINANCEIRO, penitenciário, econômico e urbanístico.",
  {"A": "Errada. Direito tributário é concorrente (art. 24, I).",
   "B": "CORRETA.",
   "C": "Errada. Municípios não integram a competência concorrente do art. 24.",
   "D": "Errada. Procedimentos processuais: concorrente (art. 24, XI).",
   "E": "Errada. Juntas comerciais: concorrente (art. 24, III)."},
  "Art. 24 = União + Estados + DF (sem Municípios).")

q(75, DIR, "Crimes contra a dignidade sexual (Lei 13.718/2018)", "E",
  "A Lei 13.718/2018 criou a importunação sexual (art. 215-A), a divulgação de cena de estupro/sexo/"
  "pornografia (art. 218-C) com aumento por relação íntima (“pornografia de vingança”). O registro não "
  "autorizado da intimidade sexual (art. 216-B) veio da Lei 13.772/2018. Não há exigência de exame de "
  "corpo de delito para configurar a divulgação; a materialidade pode ser provada por outros meios.",
  {"A": "Correta (art. 215-A).", "B": "Correta (art. 216-B).", "C": "Correta (art. 218-C).",
   "D": "Correta (art. 218-C, §1º).", "E": "INCORRETA (gabarito)."},
  "Divulgação de cena de estupro é crime autônomo; não depende de prova pericial do estupro.")

q(76, DIR, "Publicação ou crítica indevida (CPM, art. 166)", "A",
  "Art. 166 do CPM: “Publicar o militar, sem licença, ato ou documento oficial, ou criticar "
  "publicamente ato de seu superior ou assunto atinente à disciplina militar, ou a qualquer resolução "
  "do Governo”. Blog na internet é meio público.",
  {"A": "CORRETA.",
   "B": "Errada. A liberdade de expressão do militar sofre limites de hierarquia e disciplina.",
   "C": "Errada. Não se exige revista ou jornal.",
   "D": "Errada. Não há modalidade culposa.",
   "E": "Errada. É crime militar."},
  "Hierarquia e disciplina são bases das instituições militares (CF, art. 42).")

q(77, DIR, "Justiça Militar Estadual", "E",
  "CF, art. 125, §4º, e Constituição da Bahia: compete à Justiça Militar estadual processar e julgar os "
  "militares dos Estados (PM e BM) nos crimes militares definidos em lei.",
  {"A": "Errada. Crimes comuns vão à Justiça comum.",
   "B": "Errada.", "C": "Errada.", "D": "Errada.", "E": "CORRETA."},
  "Justiça Militar estadual julga somente militares estaduais e somente crimes militares.")

q(78, DIR, "CPPM – flagrante e prisão preventiva", "C",
  "A alternativa C reproduz o art. 254 do CPPM (prisão decretada pelo auditor ou pelo Conselho de "
  "Justiça, a requerimento do MP ou mediante representação do encarregado do IPM). A expressão "
  "“somente” afasta a decretação de ofício, alinhada à leitura acusatória pós-Pacote Anticrime.",
  {"A": "Errada. O art. 27 do CPPM ressalva o exame de corpo de delito, que não é dispensado.",
   "B": "Errada. Gera relaxamento da prisão, mas o auto pode valer como peça informativa.",
   "C": "CORRETA (gabarito da banca).",
   "D": "Errada. Art. 243: os militares DEVERÃO prender quem esteja em flagrante (flagrante obrigatório)."},
  "Leia a literalidade do CPPM: arts. 27, 243 e 254 são campeões em provas de oficial.",
  obs="Pela literalidade do art. 254, o juiz também pode decretar de ofício; questão passível de recurso.")

q(79, DIR, "Declaração Universal dos Direitos Humanos", "A",
  "Art. III da DUDH: “Toda pessoa tem direito à vida, à liberdade e à segurança pessoal.”",
  {"A": "CORRETA.",
   "B": "Errada. O asilo não pode ser invocado por atos contrários aos propósitos da ONU (art. XIV, 2).",
   "C": "Errada. Toda pessoa pode deixar qualquer país, INCLUSIVE o próprio (art. XIII, 2).",
   "D": "Errada. Homens e mulheres “de maior idade” (art. XVI).",
   "E": "Errada. Todos nascem livres e iguais em dignidade e direitos (art. I)."},
  "Desconfie de alternativas que trocam “inclusive” por “exceto” ou omitem restrições etárias.")

q(80, DIR, "Universalismo de chegada (Herrera Flores)", "B",
  "Joaquín Herrera Flores propõe o “universalismo de chegada” (ou de confluência): a universalidade "
  "dos direitos humanos é ponto de CHEGADA, construída pelo diálogo intercultural, e não ponto de "
  "partida imposto. Sintetiza garantias aptas a sustentar uma teoria intercultural.",
  {"A": "Errada. Considera o contexto cultural.",
   "B": "CORRETA.",
   "C": "Errada. Opõe-se ao universalismo de partida (abstrato/imposto).",
   "D": "Errada.", "E": "Errada. Não é alheio às peculiaridades culturais."},
  "Universalismo de partida (abstrato, imposto) x de chegada (construído pelo diálogo).")

DISCURSIVA = {
    "tema": "Os alimentos ultraprocessados na alimentação dos jovens",
    "estrutura": [
        ("Introdução (5–7 linhas)",
         "Contextualize o aumento do consumo de ultraprocessados (refrigerantes, salgadinhos, "
         "embutidos, fast food) entre adolescentes e jovens e apresente a tese: trata-se de problema de "
         "saúde pública que exige ação conjunta de Estado, escola, família e indústria."),
        ("Desenvolvimento 1 (6–8 linhas)",
         "Causas: praticidade, preço baixo, publicidade dirigida ao público jovem, rotina acelerada, "
         "influência das redes sociais e desconhecimento sobre rotulagem. Use repertório: Guia "
         "Alimentar para a População Brasileira (Ministério da Saúde, classificação NOVA)."),
        ("Desenvolvimento 2 (6–8 linhas)",
         "Consequências: obesidade, diabetes tipo 2, hipertensão, doenças cardiovasculares precoces, "
         "impactos na saúde mental e no rendimento escolar; pressão sobre o SUS. Dados: pesquisas "
         "PeNSE/IBGE e Vigitel apontam crescimento da obesidade juvenil."),
        ("Conclusão (4–6 linhas)",
         "Proposta de intervenção articulada: educação alimentar nas escolas (PNAE e cantinas "
         "saudáveis), regulação da publicidade, nova rotulagem frontal da Anvisa (lupa), tributação de "
         "bebidas açucaradas, campanhas nas redes sociais e acesso a alimentos in natura."),
    ],
    "criterios": [
        "Respeitar o limite de 20 a 30 linhas — fora dele, há penalização ou nota zero.",
        "Tipo textual dissertativo-argumentativo, com tese clara e progressão lógica.",
        "Norma-padrão: concordância, regência, crase, pontuação e ortografia.",
        "Coesão: conectivos variados (além disso, entretanto, dessa forma, portanto).",
        "Repertório pertinente e proposta de solução coerente com o problema.",
        "Evitar 1ª pessoa, gírias, clichês e título (se o edital não pedir).",
    ],
}
