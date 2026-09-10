#!/usr/bin/env python3
"""
Gera Manual de Aprovação por Questões – PND 2025 História – PV_1 (TIPO 01).

Saídas:
  saidas/manual_pnd_historia.docx
  saidas/manual_pnd_historia.pdf

Estrutura:
  - Capa (azul/branco)
  - Sumário
  - Análise completa (15 blocos) para as questões do LOTE 1 (Q01-Q05)
  - Índice completo das 80 questões (enunciado + alternativas)
"""
import json
from pathlib import Path

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor, white
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, Table,
                                 TableStyle, PageBreak)
from reportlab.lib.enums import TA_JUSTIFY, TA_LEFT, TA_CENTER

BASE = Path(__file__).resolve().parent.parent
QUESTOES = json.loads((BASE / "downloads/questoes.json").read_text())

# ---------- PALETA ----------
AZUL_ESCURO = "#0B3D91"
AZUL_MEDIO  = "#1E5FBF"
AZUL_CLARO  = "#E6EEF9"
CINZA_TEXTO = "#1F2933"
BRANCO      = "#FFFFFF"
VERM_ERR    = "#7A1F1F"

# Correções pontuais em alternativas contaminadas pelo layout de 2 colunas
CORRECOES_ALT = {
    2: {"D": "Levo em consideração livros que apresentem a norma culta da língua e valores sociais predominantes nos conteúdos apresentados."},
    4: {
        "A": "recusar formalmente o pedido, uma vez que a legislação exige alteração prévia no registro civil.",
        "D": "atender ao pedido mediante formalização da solicitação pelos responsáveis legais da estudante.",
    },
}

for q in QUESTOES:
    if q["numero"] in CORRECOES_ALT:
        for k, v in CORRECOES_ALT[q["numero"]].items():
            q["alternativas"][k] = v

# ==========================================================
# ============ ANÁLISES – LOTE 1 (Q01 a Q05) ==============
# ==========================================================
# Cada análise segue os 15 blocos do padrão. Gabaritos e resoluções
# baseados na leitura crítica dos itens e no padrão INEP; recomenda-se
# cruzar com o gabarito oficial do INEP para validação final.

ANALISES = {
    1: {
        "tema": "PNLD – abordagem sociocultural nos livros didáticos",
        "subtema": "Livro didático como mediador cultural (Choppin, 2004)",
        "habilidade_bncc": "Competências gerais da BNCC 1, 6, 9 e 10; DCNs para a Educação Básica",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta (política educacional / material didático)",
        "justificativa_classificacao": (
            "Item exige leitura crítica do Texto 1 sobre PNLD e associação com o papel do livro didático "
            "como mediador de conteúdos socioculturais. Alternativas incorretas contêm palavras-veneno "
            "clássicas: 'neutralidade', 'de difícil acesso', 'sensíveis que possam alterar'."
        ),
        "como_banca_pensou": (
            "O INEP quer que o(a) professor(a) reconheça o PNLD como política pública que seleciona livros "
            "considerando contextos socioculturais e a historicidade humana — jamais defendendo 'neutralidade' "
            "(A), nem um público específico (C), nem uma abordagem 'sensível que possa alterar' (B, sugere "
            "risco). A resposta correta articula 'contextos socioculturais atuais' + 'historicidade que "
            "consolidou a existência humana' — vocabulário caro à BNCC."
        ),
        "resolucao": [
            "Grife no Texto 1 a expressão que orienta o PNLD: 'materiais didáticos e demais materiais de apoio à prática educativa'.",
            "Descarte a alternativa A: 'neutralidade' não existe em livro didático — é lugar-comum criticado pela pedagogia contemporânea.",
            "Descarte C: reduzir o PNLD a 'regiões de difícil acesso' contradiz a universalidade do programa.",
            "Descarte B: 'temas sensíveis que possam ALTERAR o processo' tem conotação negativa e não corresponde ao objetivo do PNLD.",
            "Fique com D: abordar 'contextos socioculturais atuais' + 'historicidade'. É a leitura alinhada à BNCC.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Palavra-veneno 'neutralidade': o processo de ensino é mediado por valores; a BNCC e a teoria crítica do currículo negam a neutralidade."),
            "B": ("ERRADA", "'Sensíveis que possam alterar' sugere risco; não é a diretriz do PNLD, que promove abordagens formativas, não anômalas."),
            "C": ("ERRADA", "Restringe o público-alvo a 'regiões de difícil acesso'. O PNLD é universal na rede pública de ensino básico."),
            "D": ("CORRETA", "Articula 'contextos socioculturais atuais' e 'historicidade da existência humana' — em consonância com BNCC e com a função referencial do livro didático (Choppin, 2004)."),
        },
        "fundamentacao": [
            "BRASIL. Decreto nº 12.021, de 2 de julho de 2024. Altera o Decreto nº 9.099/2017, que dispõe sobre o Programa Nacional do Livro e do Material Didático (PNLD). Diário Oficial da União, Brasília, DF, 3 jul. 2024.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
            "CHOPPIN, Alain. História dos livros e das edições didáticas: sobre o estado da arte. Educação e Pesquisa, São Paulo, v. 30, n. 3, p. 549-566, set./dez. 2004.",
        ],
        "teoria": (
            "O livro didático, segundo Choppin (2004), cumpre quatro funções: referencial (transmite conteúdos "
            "curriculares), instrumental (propõe métodos de aprendizagem), ideológica e cultural (veicula "
            "valores) e documental (fornece fontes). O PNLD, regido pelo Decreto 12.021/2024 (que altera o "
            "9.099/2017), avalia, adquire e distribui obras para toda a rede pública de EB, orientando-se pela "
            "BNCC e pelas DCNs. A seleção considera contextos socioculturais atuais, diversidade, historicidade, "
            "combate a estereótipos e valorização de povos originários e afro-brasileiros (Leis 10.639/03 e "
            "11.645/08). A ideia de 'neutralidade' do material didático é descartada pela pedagogia crítica "
            "(Giroux, Apple) e pela historiografia do currículo."
        ),
        "padroes_banca": (
            "O INEP costuma inserir a palavra 'neutralidade' como isca de erro em itens de política curricular. "
            "Também recorre a advérbios totalizantes ('exclusivamente', 'somente') e a reduções de escopo "
            "(um programa nacional apresentado como restrito a um recorte)."
        ),
        "pegadinhas": [
            "'Neutralidade' aparece como qualidade positiva → sempre é armadilha.",
            "PNLD reduzido a 'regiões de difícil acesso' → confusão com programas focalizados.",
            "'Sensíveis que possam alterar' → carrega conotação negativa; alternativa correta usa termos positivos.",
        ],
        "erros_comuns": (
            "Muitos candidatos escolhem B por 'temas sensíveis' soar contemporâneo, sem perceber a conotação "
            "de risco. Outros marcam C acreditando que o PNLD tem foco em regiões periféricas."
        ),
        "dica_estrategica": (
            "Em item sobre PNLD/BNCC, prefira a alternativa que combine 'contextos socioculturais', "
            "'diversidade' e 'historicidade'. Rejeite qualquer alternativa com 'neutralidade' ou 'exclusivamente'."
        ),
        "variacao": (
            "(Estilo INEP/PND) Considerando o Programa Nacional do Livro e do Material Didático (PNLD), "
            "instituído pelo Decreto nº 9.099/2017 e alterado pelo Decreto nº 12.021/2024, a política pública "
            "orienta a avaliação de obras didáticas com base em critérios que:\n"
            "A) privilegiam a padronização nacional e excluem produções regionais;\n"
            "B) reconhecem a pluralidade cultural, o combate a estereótipos e a valorização das histórias "
            "de povos indígenas e afro-brasileiros;\n"
            "C) defendem a neutralidade científica dos conteúdos, dispensando marcadores identitários;\n"
            "D) enfatizam apenas os componentes de Língua Portuguesa e Matemática.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Segundo Choppin (2004), a função referencial do livro didático refere-se à:\n"
            "A) transmissão de conteúdos curriculares; B) formação política do docente; C) avaliação institucional; "
            "D) elaboração de exames externos. → A",
            "Q2. As Leis 10.639/03 e 11.645/08, no âmbito do PNLD, exigem que os livros didáticos:\n"
            "A) tratem apenas de temáticas europeias; B) incluam História e Cultura Afro-Brasileira e Indígena "
            "de forma obrigatória e transversal; C) sejam optativas; D) fiquem restritas ao ensino médio. → B",
            "Q3. A ideia de 'neutralidade' do livro didático é criticada por autores como:\n"
            "A) Choppin, Apple e Giroux; B) Piaget e Vygotsky; C) Comenius; D) Herbart. → A",
        ],
        "resumo": {
            "regra": "PNLD (Dec. 12.021/2024) avalia e distribui livros didáticos considerando contextos socioculturais e historicidade.",
            "excecoes": "Não há neutralidade curricular; obras devem contemplar diversidade.",
            "palavra_chave": "Contextos socioculturais + historicidade.",
            "artigo": "Decreto 12.021/2024 (altera Decreto 9.099/2017); BNCC (2018).",
            "mnemonico": "PNLD = Pluralidade + Nacional + Livro + Diversidade.",
        },
    },

    2: {
        "tema": "Livro didático – função referencial (Choppin, 2004)",
        "subtema": "Percepção docente sobre critérios de escolha",
        "habilidade_bncc": "Formação docente – DCN; competências gerais BNCC 1 e 4",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa / Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Exige do candidato o conhecimento das QUATRO FUNÇÕES do livro didático propostas por Choppin "
            "(2004) e a identificação daquela que corresponde à REFERENCIAL — a saber, transmissão sistemática "
            "de conteúdos curriculares."
        ),
        "como_banca_pensou": (
            "O INEP transforma cada função de Choppin em uma alternativa. Referencial (B) = "
            "sistematização coerente de objetos de conhecimento; Ideológica/Cultural (D) = norma culta + "
            "valores; Instrumental (C) = elementos variados (imagens, mapas); Documental (A) = temáticas + "
            "reflexões. A pegadinha é confundir 'referencial' com o senso comum de 'relevância social'."
        ),
        "resolucao": [
            "Relembre as 4 funções do livro didático em Choppin (2004): REFERENCIAL, INSTRUMENTAL, IDEOLÓGICA/CULTURAL, DOCUMENTAL.",
            "Identifique na fala docente aquela que menciona 'sistematização coerente dos objetos de conhecimento' — típica da função REFERENCIAL.",
            "Descarte C (função instrumental – múltiplas linguagens) e D (função ideológica/cultural – norma culta + valores).",
            "Descarte A (temáticas + reflexões críticas — mais próxima da função documental/formativa).",
            "Marque B.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "'Temáticas essenciais + reflexões' corresponde à função documental/formativa, não à referencial."),
            "B": ("CORRETA", "'Sistematização coerente dos objetos de conhecimento' + 'transposição didática' descrevem exatamente a função REFERENCIAL de Choppin."),
            "C": ("ERRADA", "'Elementos variados (imagens, palavras, mapas, gráficos)' descreve a função INSTRUMENTAL."),
            "D": ("ERRADA", "'Norma culta + valores predominantes' remete à função IDEOLÓGICA/CULTURAL."),
        },
        "fundamentacao": [
            "CHOPPIN, Alain. História dos livros e das edições didáticas: sobre o estado da arte. Educação e Pesquisa, São Paulo, v. 30, n. 3, p. 549-566, 2004.",
            "BITTENCOURT, Circe. Ensino de história: fundamentos e métodos. 4. ed. São Paulo: Cortez, 2011.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "Choppin (2004) sistematiza quatro funções do livro didático: (1) REFERENCIAL — transmite o "
            "currículo oficial e sistematiza conteúdos; (2) INSTRUMENTAL — propõe métodos e atividades "
            "(mapas, exercícios, imagens); (3) IDEOLÓGICA e CULTURAL — veicula valores, língua e visão de "
            "mundo; (4) DOCUMENTAL — oferece fontes primárias e secundárias. Bittencourt (2011) complementa "
            "que o professor de História deve articular essas funções à escolha crítica de fontes, à "
            "desnaturalização de estereótipos e à leitura de mundo dos estudantes."
        ),
        "padroes_banca": (
            "O INEP recorre a Choppin em itens de Formação Geral Docente sempre que o tema é livro didático. "
            "Alternativas costumam distribuir uma função por opção, exigindo que o candidato NOMEIE "
            "internamente cada uma."
        ),
        "pegadinhas": [
            "Confundir 'referencial' (sistemática de conteúdos) com 'relevância social' (temáticas essenciais).",
            "Achar que a função instrumental é 'a mais importante' — no INEP, o item pede a FUNÇÃO PEDIDA no comando.",
            "Trocar cultural/ideológica por documental.",
        ],
        "erros_comuns": (
            "Marcam A porque 'temáticas sociais' soa alinhado ao discurso pedagógico atual; ou C por "
            "considerarem a diversidade de linguagens sempre correta. Erram por não LER a função pedida."
        ),
        "dica_estrategica": (
            "Memorize as 4 funções de Choppin em uma tabela: RIID — Referencial (conteúdo), Instrumental "
            "(método), Ideológica (valores), Documental (fontes). Depois é pareamento direto."
        ),
        "variacao": (
            "(Estilo INEP) A percepção docente 'escolho livros que trazem fontes primárias diversas e "
            "documentos para leitura em sala' corresponde, segundo Choppin (2004), à função:\n"
            "A) referencial; B) instrumental; C) ideológica e cultural; D) documental. Gabarito: D."
        ),
        "minisimulado": [
            "Q1. A função INSTRUMENTAL do livro didático corresponde a:\n"
            "A) transmitir conteúdos oficiais; B) apresentar métodos, exercícios e mapas; C) veicular valores; "
            "D) fornecer fontes primárias. → B",
            "Q2. Segundo Bittencourt (2011), a escolha do livro pelo(a) professor(a) de História deve articular:\n"
            "A) apenas o preço; B) apenas o layout; C) as funções de Choppin com a leitura crítica de fontes; "
            "D) somente a diretriz do PNLD. → C",
            "Q3. A função IDEOLÓGICA e CULTURAL do livro didático manifesta-se, entre outros aspectos, na:\n"
            "A) sistematização temática; B) apresentação de valores e da norma culta; C) diagramação; "
            "D) contagem de páginas. → B",
        ],
        "resumo": {
            "regra": "Funções de Choppin (2004): Referencial, Instrumental, Ideológica/Cultural, Documental.",
            "excecoes": "As funções coexistem em um mesmo livro; a alternativa depende do enfoque da fala.",
            "palavra_chave": "Sistematização coerente = Referencial.",
            "artigo": "CHOPPIN, A. (2004).",
            "mnemonico": "RIID.",
        },
    },

    3: {
        "tema": "Educação, ética e crítica à medicalização de comportamentos",
        "subtema": "O alienista (Machado de Assis) como recurso para reflexão docente",
        "habilidade_bncc": "Competências gerais BNCC 6, 8 e 9 (autoconhecimento, empatia e responsabilidade)",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa (texto literário como recurso didático)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média-alta (crítica à patologização)",
        "justificativa_classificacao": (
            "Item mobiliza a leitura crítica de Machado de Assis sobre a arbitrariedade da classificação "
            "'normal/anormal' — tema em franca ascensão nos itens INEP, articulado à discussão sobre "
            "medicalização escolar e à ética docente."
        ),
        "como_banca_pensou": (
            "A banca espera que o(a) professor(a) reconheça em 'O alienista' uma crítica antecipada à "
            "patologização de comportamentos. A alternativa correta articula CRÍTICA à medicalização + "
            "reflexão ÉTICA docente. As demais oferecem soluções tecnicistas (laudos, padrões, viés objetivo)."
        ),
        "resolucao": [
            "Identifique a chave interpretativa do conto: sátira à autoridade científica que define arbitrariamente normal/anormal.",
            "Descarte A: 'laudos da equipe psicopedagógica' repete a lógica do alienista (rotular e intervir).",
            "Descarte B: 'ações de escuta' é positivo, mas não é o foco crítico do conto sobre medicalização.",
            "Descarte D: 'viés objetivo para definir padrões socialmente aceitos' É EXATAMENTE o que Machado ridiculariza.",
            "Marque C: 'críticas à excessiva medicamentalização + reflexões sobre ética profissional' captura a chave do conto.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Reforça a lógica de patologização — o oposto do que Machado critica."),
            "B": ("ERRADA", "Escuta é importante, mas o comando pede a abordagem que dialogue com o conto, cuja crítica central é a medicamentalização/normatização."),
            "C": ("CORRETA", "Alinha-se ao efeito crítico de 'O alienista': desnaturalizar a autoridade médico-científica que patologiza a diferença; propõe reflexão ÉTICA."),
            "D": ("ERRADA", "Retoma o próprio programa de Simão Bacamarte — objeto de sátira. Contradiz a leitura crítica."),
        },
        "fundamentacao": [
            "ASSIS, Machado de. O alienista. Rio de Janeiro: Garnier, 1882.",
            "FOUCAULT, Michel. A história da loucura na idade clássica. São Paulo: Perspectiva, 1978.",
            "COLLARES, Cecília A. L.; MOYSÉS, Maria Aparecida A. Preconceitos no cotidiano escolar: ensino e medicalização. São Paulo: Cortez, 1996.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "A crítica à medicalização escolar tem larga fortuna crítica: Foucault (1978) mostra como o "
            "saber médico historicamente constituiu a loucura como objeto de exclusão; Collares e Moysés "
            "(1996) documentam a patologização de crianças brasileiras em nome do 'sucesso escolar'. "
            "Machado de Assis, em 'O alienista' (1882), antecipa a discussão ao expor a arbitrariedade dos "
            "critérios de normalidade. Do ponto de vista pedagógico, isso implica: (a) recusar a rotulação "
            "diagnóstica precoce, (b) valorizar a diversidade de comportamentos, (c) exercer ética "
            "profissional docente e (d) articular escuta, mediação e alteridade."
        ),
        "padroes_banca": (
            "O INEP tem inserido literatura brasileira como recurso para itens de Formação Geral. "
            "A alternativa correta quase sempre articula LITERATURA + REFLEXÃO CRÍTICA/ÉTICA, "
            "recusando soluções tecnicistas."
        ),
        "pegadinhas": [
            "Pensar que 'laudo psicopedagógico' é sempre a solução — é a repetição do gesto medicalizador.",
            "Confundir 'escuta' (correta em outros contextos) com o foco crítico do conto machadiano.",
            "Marcar por 'objetividade científica' — palavra-veneno neste tema.",
        ],
        "erros_comuns": (
            "Marcam A por associarem escola a laudos e intervenções, ou D por acreditarem que 'ciência médica' "
            "resolve. Erram por não perceber a IRONIA machadiana."
        ),
        "dica_estrategica": (
            "Em itens que mobilizem 'O alienista', Foucault ou medicalização escolar, a resposta correta "
            "sempre inclui palavras como CRÍTICA, ÉTICA, ALTERIDADE, DIVERSIDADE."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Foucault, o discurso médico sobre a loucura, no processo de constituição "
            "da modernidade, teve como efeito principal:\n"
            "A) libertar o louco da tutela religiosa e integrá-lo à comunidade;\n"
            "B) construir a loucura como objeto de exclusão e controle disciplinar;\n"
            "C) uniformizar clinicamente todos os comportamentos;\n"
            "D) esvaziar de sentido a categoria de 'razão'.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Collares e Moysés (1996) alertam para:\n"
            "A) a supremacia do laudo pedagógico; B) a patologização e medicalização do fracasso escolar; "
            "C) o abandono da BNCC; D) a hipertrofia do lúdico. → B",
            "Q2. A ética docente em face da diferença exige:\n"
            "A) rotular e encaminhar; B) escutar, mediar e problematizar padrões de normalidade; "
            "C) padronizar comportamentos; D) exigir laudos como pré-condição de matrícula. → B",
            "Q3. Em 'O alienista', a ironia machadiana visa:\n"
            "A) legitimar a ciência positivista; B) satirizar a arbitrariedade da autoridade científica; "
            "C) exaltar a psiquiatria da Casa Verde; D) defender a naturalização da loucura. → B",
        ],
        "resumo": {
            "regra": "A escola crítica recusa a medicalização/rotulação precoce e afirma a ética docente.",
            "excecoes": "Encaminhamentos clínicos legítimos são pontuais e nunca substituem a escuta pedagógica.",
            "palavra_chave": "Crítica + ética + diversidade.",
            "artigo": "BNCC, competências 6, 8, 9; ECA; Lei 13.146/2015 (LBI).",
            "mnemonico": "3 E's: Escutar, Éticar, Estranhar padrões.",
        },
    },

    4: {
        "tema": "Uso do nome social na escola",
        "subtema": "Direito à identidade de gênero na Educação Básica",
        "habilidade_bncc": "Competência geral BNCC 9 (empatia e cooperação); DCNs para EB",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Prática / Jurídica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta (direitos humanos, diversidade)",
        "justificativa_classificacao": (
            "Tema recorrente em provas docentes: o direito ao uso do nome social é assegurado a "
            "estudantes trans e travestis por atos normativos federais (Decreto 8.727/2016; Resolução "
            "CNE/CP 1/2018) e por diversas normas estaduais/municipais, INDEPENDENTEMENTE de retificação "
            "de registro civil e da idade, mediante requerimento (do(a) próprio(a) ou dos responsáveis, "
            "no caso de menores)."
        ),
        "como_banca_pensou": (
            "A banca testa o conhecimento da legislação e das resoluções educacionais sobre nome social. "
            "A alternativa correta é aquela que atende ao pedido MEDIANTE FORMALIZAÇÃO por parte dos "
            "responsáveis legais (a estudante tem 15 anos). As demais oferecem soluções erradas: recusar, "
            "condicionar à retificação no registro civil, ou submeter ao conselho estudantil."
        ),
        "resolucao": [
            "Identifique: estudante de 15 anos + pais + solicitação de nome social + recusa da direção.",
            "Lembre: Decreto 8.727/2016 e Resolução CNE/CP 1/2018 garantem uso do nome social nas escolas.",
            "Descarte A: recusar formalmente contraria a legislação e a jurisprudência (STF, ADI 4275).",
            "Descarte B: conselho estudantil não delibera sobre direito fundamental de identidade.",
            "Descarte C: condicionar à retificação no registro civil é EXATAMENTE a violação praticada pela direção.",
            "Marque D: atender ao pedido mediante formalização pelos responsáveis legais.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Violaria o Decreto 8.727/2016 e a jurisprudência do STF (ADI 4275) que assegura o direito à identidade de gênero."),
            "B": ("ERRADA", "Direito fundamental não se submete a deliberação de conselho estudantil."),
            "C": ("ERRADA", "Condicionar à alteração no registro civil é o cerne da violação — a norma dispensa esse requisito."),
            "D": ("CORRETA", "Formalização pelos responsáveis legais (estudante menor de idade) é o procedimento previsto na Resolução CNE/CP 1/2018."),
        },
        "fundamentacao": [
            "BRASIL. Decreto nº 8.727, de 28 de abril de 2016. Dispõe sobre o uso do nome social e o reconhecimento da identidade de gênero. Diário Oficial da União, Brasília, DF, 29 abr. 2016.",
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CP nº 1, de 19 de janeiro de 2018. Uso do nome social por travestis e transexuais na Educação Básica.",
            "BRASIL. Supremo Tribunal Federal. ADI 4275, Rel. Min. Marco Aurélio, j. 1º/3/2018.",
            "BRASIL. Lei nº 8.069, de 13 de julho de 1990. Estatuto da Criança e do Adolescente. Diário Oficial da União, Brasília, DF, 16 jul. 1990.",
        ],
        "teoria": (
            "O NOME SOCIAL é a designação pela qual a pessoa trans ou travesti se identifica e é reconhecida "
            "socialmente. O Decreto 8.727/2016 garante o uso do nome social nos órgãos federais; a Resolução "
            "CNE/CP 1/2018 estende essa garantia à Educação Básica (chamada, boletim, carteira estudantil, "
            "comunicações internas). O STF (ADI 4275, 2018) reconheceu que a alteração do registro civil "
            "independe de cirurgia, laudos ou processo judicial. Para menores de idade, o requerimento é "
            "formalizado pelos RESPONSÁVEIS LEGAIS. O nome civil só figura em documentos oficiais externos "
            "quando estritamente necessário."
        ),
        "padroes_banca": (
            "O INEP costuma cobrar esse tema conjugando LEGISLAÇÃO + PRÁTICA. As pegadinhas ficam por conta "
            "de exigir requisitos NÃO previstos em lei (retificação civil, laudo médico, decisão colegiada)."
        ),
        "pegadinhas": [
            "Confundir NOME SOCIAL com RETIFICAÇÃO DE REGISTRO CIVIL — são coisas distintas.",
            "Achar que a autorização depende de conselho escolar/estudantil.",
            "Recusar por 'ausência de norma' — a norma existe (Decreto 8.727/2016; Resolução CNE 1/2018).",
        ],
        "erros_comuns": (
            "Muitos candidatos marcam C achando 'razoável' esperar a retificação civil — exatamente a "
            "violação praticada pela escola. É preciso ter clareza jurídica."
        ),
        "dica_estrategica": (
            "Em item sobre nome social, elimine qualquer alternativa que CONDICIONE o direito à retificação "
            "civil, a laudo, à idade ou a decisão coletiva. A resposta é ATENDER (via responsáveis, se menor)."
        ),
        "variacao": (
            "(Estilo INEP) Um estudante de 17 anos, travesti, solicita à escola o uso do nome social em "
            "chamada e boletim. Considerando o Decreto 8.727/2016 e a Resolução CNE/CP 1/2018, a escola deve:\n"
            "A) exigir alteração no registro civil;\n"
            "B) atender ao pedido, com formalização por responsável legal (se menor) ou pelo(a) próprio(a) "
            "estudante (se maior);\n"
            "C) submeter à assembleia de professores;\n"
            "D) recusar até homologação judicial.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O direito ao uso do nome social:\n"
            "A) depende de cirurgia; B) depende de laudo médico; C) INDEPENDE de retificação civil e de laudo; "
            "D) só vale após os 18 anos. → C",
            "Q2. Na Educação Básica, o nome social deve constar em:\n"
            "A) apenas conversas informais; B) chamada, boletim e carteira estudantil; C) apenas em atos festivos; "
            "D) somente em ata sigilosa. → B",
            "Q3. A ADI 4275 (STF, 2018) firmou entendimento de que:\n"
            "A) é indispensável cirurgia para alterar o registro civil; B) NÃO é necessária cirurgia, laudo ou "
            "processo judicial para retificação; C) o direito ao nome social é sub judice; D) o nome social só "
            "vale entre docentes. → B",
        ],
        "resumo": {
            "regra": "Nome social é direito assegurado, INDEPENDE de retificação civil; menores requerem via responsáveis.",
            "excecoes": "Documentos externos oficiais podem exigir o nome civil quando estritamente necessário.",
            "palavra_chave": "Formalização + responsáveis legais + independe de registro civil.",
            "artigo": "Decreto 8.727/2016; Resolução CNE/CP 1/2018; STF ADI 4275/2018.",
            "mnemonico": "N.S. = Nunca-Subordinado (à retificação civil).",
        },
    },

    5: {
        "tema": "Educação ambiental e planejamento pedagógico",
        "subtema": "Uso de fontes diversas (canção + textos) em atividade crítica",
        "habilidade_bncc": "Competências gerais BNCC 2, 7 e 10; Tema Contemporâneo Transversal 'Educação Ambiental'",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Prática (planejamento didático)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta (educação ambiental / letramentos múltiplos)",
        "justificativa_classificacao": (
            "Item pede ao(à) candidato(a) identificar a proposta didática que EFETIVAMENTE mobiliza "
            "leitura crítica dos textos apresentados (uma canção do Criolo + texto sobre COP30/ODS) e "
            "articula com pesquisa aplicada. Alternativas incorretas oferecem propostas superficiais, "
            "eventuais ou desconectadas dos textos."
        ),
        "como_banca_pensou": (
            "A banca contrasta atividades RASAS (palestra, listagem, fichamento, consulta de termos) com "
            "uma atividade PROFUNDA (interpretação da canção + pesquisa aplicada). O INEP privilegia "
            "planejamentos que articulam análise de fontes + investigação + protagonismo estudantil."
        ),
        "resolucao": [
            "Reconheça o comando: 'discussão CRÍTICA' → precisa haver análise dos textos e ação investigativa.",
            "Descarte A: palestra pontual + listagem de objetivos são atividades expositivas, não críticas.",
            "Descarte B: leitura coletiva + fichamento são preparatórias, não críticas por si.",
            "Descarte D: registro de ODS + consulta a dicionário são atividades acessórias.",
            "Marque C: interpretação da canção + pesquisa sobre ações de preservação = análise + aplicação.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Rappers como convidados e listagem de objetivos são atividades pontuais; não constituem discussão crítica."),
            "B": ("ERRADA", "Leitura + fichamento são etapas preparatórias; não desenvolvem, por si, a criticidade."),
            "C": ("CORRETA", "Interpretação de fonte artística (canção) + pesquisa aplicada = análise crítica + protagonismo estudantil."),
            "D": ("ERRADA", "Registro no caderno + consulta de dicionário são tarefas de baixa demanda cognitiva."),
        },
        "fundamentacao": [
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
            "BRASIL. Lei nº 9.795, de 27 de abril de 1999. Institui a Política Nacional de Educação Ambiental. Diário Oficial da União, Brasília, DF, 28 abr. 1999.",
            "ORGANIZAÇÃO DAS NAÇÕES UNIDAS. Agenda 2030 para o Desenvolvimento Sustentável (ODS). Nova York: ONU, 2015.",
            "MORIN, Edgar. Os sete saberes necessários à educação do futuro. São Paulo: Cortez, 2000.",
        ],
        "teoria": (
            "A Educação Ambiental (Lei 9.795/1999) é diretriz transversal na Educação Básica; a BNCC a "
            "consolida como Tema Contemporâneo Transversal. Do ponto de vista didático, propostas críticas "
            "articulam ANÁLISE DE FONTES DIVERSIFICADAS (canção, texto acadêmico, dados de conferências como "
            "a COP30), PESQUISA APLICADA e INTERVENÇÃO. Morin (2000) defende a complexidade e a religação de "
            "saberes; a educação ambiental crítica (Loureiro, Layrargues) recusa uma abordagem meramente "
            "conservacionista e propõe articular ecologia, política, cultura e cidadania."
        ),
        "padroes_banca": (
            "O INEP costuma opor atividades expositivas/rasas vs. propostas investigativas. A resposta correta "
            "tende a combinar LEITURA de FONTE ARTÍSTICA/PRIMÁRIA + PESQUISA + AÇÃO."
        ),
        "pegadinhas": [
            "Achar que 'convidar rapper' é automaticamente crítico — sem análise da canção, é evento.",
            "Confundir fichamento com criticidade.",
            "Considerar ODS apenas como conteúdo memorizado.",
        ],
        "erros_comuns": (
            "Marcam A por associarem cultura popular a criticidade sem observar que o item pede análise da fonte. "
            "Também marcam B por hábito escolar (fichar antes de discutir)."
        ),
        "dica_estrategica": (
            "Em itens sobre planejamento crítico, prefira alternativas com verbos INTERPRETAR + PESQUISAR + "
            "INTERVIR; recuse listar, registrar, consultar."
        ),
        "variacao": (
            "(Estilo INEP) Em uma aula sobre mudanças climáticas, o(a) professor(a) apresenta uma reportagem "
            "sobre a COP30 e uma canção crítica. A atividade que MELHOR desenvolve competências críticas é:\n"
            "A) copiar os ODS no caderno;\n"
            "B) analisar a canção, contextualizar a COP30 e propor uma ação local de mitigação;\n"
            "C) fichar termos técnicos;\n"
            "D) organizar palestra de convidado, sem discussão. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Educação Ambiental, segundo a Lei 9.795/1999, é:\n"
            "A) disciplina específica no EM; B) componente transversal em todos os níveis; C) optativa; "
            "D) exclusiva das ciências naturais. → B",
            "Q2. Loureiro e Layrargues criticam a educação ambiental:\n"
            "A) crítica; B) conservacionista/acrítica; C) freireana; D) intercultural. → B",
            "Q3. Morin (2000) propõe que a educação:\n"
            "A) fragmente saberes; B) religue saberes complexos; C) valorize apenas ciências duras; "
            "D) exclua a arte. → B",
        ],
        "resumo": {
            "regra": "Discussão crítica exige análise de fontes + pesquisa aplicada + protagonismo.",
            "excecoes": "Atividades preparatórias (leitura, fichamento) são etapas, não fins.",
            "palavra_chave": "Interpretar + Pesquisar + Intervir.",
            "artigo": "Lei 9.795/1999; BNCC – TCT Educação Ambiental.",
            "mnemonico": "3 I's da EA crítica: Interpretar, Investigar, Intervir.",
        },
    },
}

# =============================================================
# =============== HELPERS DE DOCX ============================
# =============================================================

def _shade(cell, color_hex):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color_hex.lstrip("#"))
    tc_pr.append(shd)

def _hex(c):
    c = c.lstrip("#")
    return RGBColor(int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16))


def gerar_docx(destino: Path):
    doc = Document()
    style = doc.styles["Normal"]
    style.font.name = "Calibri"
    style.font.size = Pt(11)
    style.font.color.rgb = _hex(CINZA_TEXTO)

    for section in doc.sections:
        section.top_margin = Cm(2.0)
        section.bottom_margin = Cm(2.0)
        section.left_margin = Cm(2.2)
        section.right_margin = Cm(2.2)

    def bloco_titulo(texto):
        tbl = doc.add_table(rows=1, cols=1)
        c = tbl.rows[0].cells[0]
        _shade(c, AZUL_MEDIO)
        p = c.paragraphs[0]
        r = p.add_run(texto)
        r.bold = True
        r.font.size = Pt(13)
        r.font.color.rgb = _hex(BRANCO)
        doc.add_paragraph()

    def bloco_destaque(texto):
        tbl = doc.add_table(rows=1, cols=1)
        c = tbl.rows[0].cells[0]
        _shade(c, AZUL_CLARO)
        p = c.paragraphs[0]
        r = p.add_run(texto)
        r.font.size = Pt(11)
        r.font.color.rgb = _hex(CINZA_TEXTO)
        doc.add_paragraph()

    def paragrafo(texto, bold=False):
        p = doc.add_paragraph()
        r = p.add_run(texto)
        r.bold = bold
        r.font.size = Pt(11)

    def cabecalho_azul(titulo, subtitulo):
        tbl = doc.add_table(rows=2, cols=1)
        tbl.autofit = False
        c1 = tbl.rows[0].cells[0]
        c2 = tbl.rows[1].cells[0]
        _shade(c1, AZUL_ESCURO)
        _shade(c2, AZUL_ESCURO)
        p1 = c1.paragraphs[0]; p1.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r1 = p1.add_run(titulo); r1.bold=True; r1.font.size=Pt(24); r1.font.color.rgb=_hex(BRANCO)
        p2 = c2.paragraphs[0]; p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r2 = p2.add_run(subtitulo); r2.bold=True; r2.font.size=Pt(14); r2.font.color.rgb=_hex(BRANCO)
        doc.add_paragraph()

    # ---------- CAPA ----------
    cabecalho_azul(
        "MANUAL DE APROVAÇÃO POR QUESTÕES",
        "PND 2025 – Prova Nacional Docente\nÁrea: HISTÓRIA – Caderno PV_1 (TIPO 01)"
    )

    # ---------- SUMÁRIO ----------
    bloco_titulo("SUMÁRIO")
    for txt in [
        "1. Estrutura da prova e distribuição",
        "2. Padrão de análise (15 blocos)",
        "3. Análise completa – LOTE 1 (Q01 a Q05)",
        "4. Índice completo das 80 questões (enunciado + alternativas)",
        "5. Referências ABNT consolidadas",
    ]:
        doc.add_paragraph(txt, style="List Number")
    doc.add_page_break()

    # ---------- 1. Estrutura ----------
    bloco_titulo("1. ESTRUTURA DA PROVA")
    paragrafo("Caderno PND 2025 – História (Licenciatura) – TIPO 01 (PV_1):")
    paragrafo("• Formação Geral Docente: questões 01 a 30 (objetivas) + 1 questão discursiva")
    paragrafo("• Componente Específico da Área: questões 31 a 80 (objetivas)")
    paragrafo("• Questionário de Percepção da Prova: 09 questões objetivas (não conteudísticas)")
    paragrafo("Total de itens objetivos analisados neste manual: 80.")

    # ---------- 2. Padrão ----------
    bloco_titulo("2. PADRÃO DE ANÁLISE (15 BLOCOS)")
    for txt in [
        "1) Identificação (disciplina/tema/subtema/BNCC)",
        "2) Enunciado no padrão original",
        "3) Classificação (nível/tipo/formato/incidência)",
        "4) Como a banca (INEP) pensou a questão",
        "5) Resolução passo a passo",
        "6) Análise de cada alternativa",
        "7) Fundamentação legal e doutrinária (ABNT NBR 6023)",
        "8) Teoria extraída da questão",
        "9) Padrões da banca INEP",
        "10) Pegadinhas relacionadas",
        "11) Erros mais comuns",
        "12) Dica estratégica de prova",
        "13) Variação (nova questão no estilo INEP)",
        "14) Mini-simulado do tema (2-3 questões)",
        "15) Resumo de memorização rápida",
    ]:
        doc.add_paragraph(txt, style="List Bullet")
    doc.add_page_break()

    # ---------- 3. Análise LOTE 1 ----------
    bloco_titulo("3. ANÁLISE COMPLETA – LOTE 1 (Q01 a Q05)")
    doc.add_paragraph(
        "As análises abaixo aplicam integralmente os 15 blocos. Gabaritos indicados são "
        "baseados na análise técnica dos itens; recomenda-se cruzar com o gabarito oficial "
        "do INEP para validação final."
    )

    for num in [1, 2, 3, 4, 5]:
        q = next(x for x in QUESTOES if x["numero"] == num)
        a = ANALISES[num]
        doc.add_page_break()

        # Bloco 1
        bloco_titulo(f"QUESTÃO {num:02d} — {a['tema']}")
        tbl = doc.add_table(rows=6, cols=2); tbl.style = "Light Grid Accent 1"; tbl.autofit=False
        col0, col1 = Cm(4.0), Cm(12.6)
        dados = [
            ("Seção", q["secao"]),
            ("Tema / Subtema", f"{a['tema']} — {a['subtema']}"),
            ("Prova", "PND 2025 – Caderno de História – PV_1 (TIPO 01)"),
            ("Habilidade BNCC", a["habilidade_bncc"]),
            ("Gabarito", a["gabarito"]),
            ("Nº da questão", str(num)),
        ]
        for i,(k,v) in enumerate(dados):
            c0=tbl.rows[i].cells[0]; c1=tbl.rows[i].cells[1]
            c0.width=col0; c1.width=col1
            c0.text=k; c1.text=v; _shade(c0, AZUL_CLARO)
        doc.add_paragraph()

        # Bloco 2
        bloco_titulo("2. ENUNCIADO")
        bloco_destaque(q["enunciado"])
        for letra, texto in q["alternativas"].items():
            paragrafo(f"({letra}) {texto}")

        # Bloco 3
        bloco_titulo("3. CLASSIFICAÇÃO")
        paragrafo(f"Nível: {a['nivel']} | Tipo: {a['tipo']} | Formato: {a['formato']} | Incidência: {a['incidencia']}", bold=True)
        paragrafo(a["justificativa_classificacao"])

        # Bloco 4
        bloco_titulo("4. COMO A BANCA PENSOU ESSA QUESTÃO")
        paragrafo(a["como_banca_pensou"])

        # Bloco 5
        bloco_titulo("5. RESOLUÇÃO PASSO A PASSO")
        for i, passo in enumerate(a["resolucao"], 1):
            paragrafo(f"{i}. {passo}")

        # Bloco 6
        bloco_titulo("6. ANÁLISE DE CADA ALTERNATIVA")
        for letra, (status, just) in a["analise_alternativas"].items():
            p = doc.add_paragraph()
            r = p.add_run(f"({letra}) {status} — ")
            r.bold = True
            r.font.color.rgb = _hex(AZUL_ESCURO if status == "CORRETA" else VERM_ERR)
            p.add_run(just)

        # Bloco 7
        bloco_titulo("7. FUNDAMENTAÇÃO LEGAL E DOUTRINÁRIA (ABNT NBR 6023)")
        for ref in a["fundamentacao"]:
            paragrafo("• " + ref)

        # Bloco 8
        bloco_titulo("8. TEORIA EXTRAÍDA DA QUESTÃO")
        paragrafo(a["teoria"])

        # Bloco 9
        bloco_titulo("9. PADRÕES DA BANCA (INEP)")
        paragrafo(a["padroes_banca"])

        # Bloco 10
        bloco_titulo("10. PEGADINHAS RELACIONADAS")
        for peg in a["pegadinhas"]:
            paragrafo("• " + peg)

        # Bloco 11
        bloco_titulo("11. ERROS MAIS COMUNS DOS CANDIDATOS")
        paragrafo(a["erros_comuns"])

        # Bloco 12
        bloco_titulo("12. DICA ESTRATÉGICA DE PROVA")
        bloco_destaque(a["dica_estrategica"])

        # Bloco 13
        bloco_titulo("13. VARIAÇÃO — NOVA QUESTÃO NO ESTILO INEP")
        paragrafo(a["variacao"])

        # Bloco 14
        bloco_titulo("14. MINI-SIMULADO DO TEMA")
        for q_ in a["minisimulado"]:
            paragrafo(q_)

        # Bloco 15
        bloco_titulo("15. RESUMO DE MEMORIZAÇÃO RÁPIDA")
        r = a["resumo"]
        for k, label in [("regra","Regra"),("excecoes","Exceções"),
                         ("palavra_chave","Palavra-chave"),("artigo","Artigo essencial"),
                         ("mnemonico","Mnemônico")]:
            p = doc.add_paragraph()
            run = p.add_run(f"{label}: "); run.bold=True; run.font.color.rgb=_hex(AZUL_ESCURO)
            p.add_run(r[k])

    # ---------- 4. Índice completo ----------
    doc.add_page_break()
    bloco_titulo("4. ÍNDICE COMPLETO – 80 QUESTÕES (ENUNCIADO + ALTERNATIVAS)")
    doc.add_paragraph(
        "Reprodução dos 80 itens objetivos do caderno (Formação Geral Docente + Componente "
        "Específico – História), para consulta e para prosseguir com a análise em lotes "
        "subsequentes (Q06 em diante)."
    )

    for q in QUESTOES:
        # cabeçalho azul da questão
        tbl = doc.add_table(rows=1, cols=1); c = tbl.rows[0].cells[0]; _shade(c, AZUL_MEDIO)
        p = c.paragraphs[0]
        r = p.add_run(f"Q{q['numero']:02d}  |  {q['secao']}")
        r.bold=True; r.font.size=Pt(12); r.font.color.rgb=_hex(BRANCO)
        doc.add_paragraph()
        paragrafo(q["enunciado"])
        for letra, texto in q["alternativas"].items():
            paragrafo(f"({letra}) {texto}")
        doc.add_paragraph()

    # ---------- 5. Referências ----------
    doc.add_page_break()
    bloco_titulo("5. REFERÊNCIAS ABNT CONSOLIDADAS – LOTE 1")
    refs = set()
    for a in ANALISES.values():
        refs.update(a["fundamentacao"])
    for ref in sorted(refs):
        paragrafo("• " + ref)

    destino.parent.mkdir(parents=True, exist_ok=True)
    doc.save(destino)


# =============================================================
# =============== HELPERS DE PDF ==============================
# =============================================================

def gerar_pdf(destino: Path):
    destino.parent.mkdir(parents=True, exist_ok=True)

    def on_page(canvas, doc_):
        canvas.saveState()
        canvas.setFillColor(HexColor(AZUL_ESCURO))
        canvas.rect(0, A4[1] - 1.2 * cm, A4[0], 1.2 * cm, fill=1, stroke=0)
        canvas.setFillColor(white)
        canvas.setFont("Helvetica-Bold", 10)
        canvas.drawString(2 * cm, A4[1] - 0.8 * cm, "Manual de Aprovação por Questões — PND 2025 História")
        canvas.drawRightString(A4[0] - 2 * cm, A4[1] - 0.8 * cm, "Caderno PV_1 • TIPO 01")
        canvas.setFillColor(HexColor(AZUL_ESCURO))
        canvas.rect(0, 0, A4[0], 0.8 * cm, fill=1, stroke=0)
        canvas.setFillColor(white)
        canvas.setFont("Helvetica", 8)
        canvas.drawCentredString(A4[0] / 2, 0.3 * cm,
                                 f"Página {doc_.page}   •   Projeto Gabaritando   •   Citações ABNT NBR 6023")
        canvas.restoreState()

    styles = getSampleStyleSheet()
    body = ParagraphStyle("body", parent=styles["BodyText"], fontName="Helvetica",
                         fontSize=10.5, leading=14, textColor=HexColor(CINZA_TEXTO), alignment=TA_JUSTIFY)
    h_title = ParagraphStyle("h_title", parent=styles["Title"], fontName="Helvetica-Bold",
                             fontSize=22, textColor=HexColor(AZUL_ESCURO), alignment=TA_CENTER, spaceAfter=6)
    h_sub = ParagraphStyle("h_sub", parent=styles["Heading2"], fontName="Helvetica-Bold",
                           fontSize=14, textColor=HexColor(AZUL_ESCURO), alignment=TA_CENTER, spaceAfter=18)
    h_bloco = ParagraphStyle("h_bloco", parent=styles["Heading2"], fontName="Helvetica-Bold",
                             fontSize=12.5, textColor=white, alignment=TA_LEFT, leading=16,
                             backColor=HexColor(AZUL_MEDIO), borderPadding=6, spaceBefore=10, spaceAfter=6)
    q_h = ParagraphStyle("q_h", parent=h_bloco, fontSize=11.5, spaceBefore=6, spaceAfter=4)
    destaque = ParagraphStyle("destaque", parent=body, backColor=HexColor(AZUL_CLARO),
                              borderPadding=8, leading=15)

    story = []

    # Capa
    story.append(Spacer(1, 3 * cm))
    story.append(Paragraph("MANUAL DE APROVAÇÃO POR QUESTÕES", h_title))
    story.append(Paragraph("PND 2025 — Prova Nacional Docente<br/>Área: HISTÓRIA — Caderno PV_1 (TIPO 01)", h_sub))
    story.append(Paragraph("<b>Análise questão por questão</b><br/>Padrão de 15 blocos • 80 itens objetivos", body))
    story.append(PageBreak())

    # Sumário
    story.append(Paragraph("SUMÁRIO", h_bloco))
    for t in ["1. Estrutura da prova e distribuição",
              "2. Padrão de análise (15 blocos)",
              "3. Análise completa – LOTE 1 (Q01 a Q05)",
              "4. Índice completo das 80 questões",
              "5. Referências ABNT consolidadas"]:
        story.append(Paragraph("• " + t, body))
    story.append(PageBreak())

    # 1. Estrutura
    story.append(Paragraph("1. ESTRUTURA DA PROVA", h_bloco))
    for t in [
        "Formação Geral Docente: questões 01 a 30 (objetivas) + 1 questão discursiva.",
        "Componente Específico da Área: questões 31 a 80 (objetivas).",
        "Questionário de Percepção da Prova: 09 questões (não conteudísticas).",
        "Total de itens objetivos analisados: 80.",
    ]:
        story.append(Paragraph("• " + t, body))

    # 2. Padrão
    story.append(Paragraph("2. PADRÃO DE ANÁLISE (15 BLOCOS)", h_bloco))
    for t in [
        "1) Identificação (disciplina/tema/subtema/BNCC)",
        "2) Enunciado no padrão original",
        "3) Classificação (nível/tipo/formato/incidência)",
        "4) Como a banca (INEP) pensou a questão",
        "5) Resolução passo a passo",
        "6) Análise de cada alternativa",
        "7) Fundamentação (ABNT)",
        "8) Teoria extraída da questão",
        "9) Padrões INEP",
        "10) Pegadinhas",
        "11) Erros comuns",
        "12) Dica estratégica",
        "13) Variação",
        "14) Mini-simulado",
        "15) Resumo",
    ]:
        story.append(Paragraph(t, body))
    story.append(PageBreak())

    # 3. Análises do lote
    story.append(Paragraph("3. ANÁLISE COMPLETA — LOTE 1 (Q01 a Q05)", h_bloco))
    story.append(Paragraph(
        "As análises abaixo aplicam integralmente os 15 blocos. Gabaritos indicados baseiam-se "
        "na análise técnica dos itens; sugere-se cruzar com o gabarito oficial do INEP.", body))

    cell_style = ParagraphStyle("cell", parent=body, fontSize=10, leading=13, alignment=TA_LEFT)
    cell_key = ParagraphStyle("cell_key", parent=cell_style, fontName="Helvetica-Bold",
                              textColor=HexColor(AZUL_ESCURO))

    for num in [1, 2, 3, 4, 5]:
        q = next(x for x in QUESTOES if x["numero"] == num)
        a = ANALISES[num]
        story.append(PageBreak())
        story.append(Paragraph(f"QUESTÃO {num:02d} — {a['tema']}", h_bloco))
        tbl_data = [
            [Paragraph("Seção", cell_key), Paragraph(q["secao"], cell_style)],
            [Paragraph("Tema / Subtema", cell_key), Paragraph(f"{a['tema']} — {a['subtema']}", cell_style)],
            [Paragraph("Prova", cell_key), Paragraph("PND 2025 – Caderno de História – PV_1 (TIPO 01)", cell_style)],
            [Paragraph("Habilidade BNCC", cell_key), Paragraph(a["habilidade_bncc"], cell_style)],
            [Paragraph("Gabarito", cell_key), Paragraph(a["gabarito"], cell_style)],
        ]
        tbl = Table(tbl_data, colWidths=[4.0*cm, 13.0*cm])
        tbl.setStyle(TableStyle([
            ("BACKGROUND", (0,0),(0,-1), HexColor(AZUL_CLARO)),
            ("GRID",(0,0),(-1,-1),0.4,HexColor(AZUL_MEDIO)),
            ("VALIGN",(0,0),(-1,-1),"TOP"),
            ("LEFTPADDING",(0,0),(-1,-1),6),("RIGHTPADDING",(0,0),(-1,-1),6),
            ("TOPPADDING",(0,0),(-1,-1),4),("BOTTOMPADDING",(0,0),(-1,-1),4),
        ]))
        story.append(tbl)

        story.append(Paragraph("2. ENUNCIADO", h_bloco))
        story.append(Paragraph(q["enunciado"], destaque))
        story.append(Spacer(1, 0.15*cm))
        for letra, texto in q["alternativas"].items():
            story.append(Paragraph(f"<b>({letra})</b> {texto}", body))

        story.append(Paragraph("3. CLASSIFICAÇÃO", h_bloco))
        story.append(Paragraph(
            f"<b>Nível:</b> {a['nivel']} &nbsp;|&nbsp; <b>Tipo:</b> {a['tipo']} &nbsp;|&nbsp; "
            f"<b>Formato:</b> {a['formato']} &nbsp;|&nbsp; <b>Incidência:</b> {a['incidencia']}", body))
        story.append(Paragraph(a["justificativa_classificacao"], body))

        story.append(Paragraph("4. COMO A BANCA PENSOU ESSA QUESTÃO", h_bloco))
        story.append(Paragraph(a["como_banca_pensou"], body))

        story.append(Paragraph("5. RESOLUÇÃO PASSO A PASSO", h_bloco))
        for i, passo in enumerate(a["resolucao"], 1):
            story.append(Paragraph(f"<b>{i}.</b> {passo}", body))

        story.append(Paragraph("6. ANÁLISE DE CADA ALTERNATIVA", h_bloco))
        for letra, (status, just) in a["analise_alternativas"].items():
            cor = AZUL_ESCURO if status == "CORRETA" else VERM_ERR
            story.append(Paragraph(f'<font color="{cor}"><b>({letra}) {status}</b></font> — {just}', body))

        story.append(Paragraph("7. FUNDAMENTAÇÃO LEGAL E DOUTRINÁRIA (ABNT NBR 6023)", h_bloco))
        for ref in a["fundamentacao"]:
            story.append(Paragraph("• " + ref, body))

        story.append(Paragraph("8. TEORIA EXTRAÍDA DA QUESTÃO", h_bloco))
        story.append(Paragraph(a["teoria"], body))

        story.append(Paragraph("9. PADRÕES DA BANCA (INEP)", h_bloco))
        story.append(Paragraph(a["padroes_banca"], body))

        story.append(Paragraph("10. PEGADINHAS RELACIONADAS", h_bloco))
        for peg in a["pegadinhas"]:
            story.append(Paragraph("• " + peg, body))

        story.append(Paragraph("11. ERROS MAIS COMUNS DOS CANDIDATOS", h_bloco))
        story.append(Paragraph(a["erros_comuns"], body))

        story.append(Paragraph("12. DICA ESTRATÉGICA DE PROVA", h_bloco))
        story.append(Paragraph(a["dica_estrategica"], destaque))

        story.append(Paragraph("13. VARIAÇÃO — NOVA QUESTÃO NO ESTILO INEP", h_bloco))
        story.append(Paragraph(a["variacao"].replace("\n", "<br/>"), body))

        story.append(Paragraph("14. MINI-SIMULADO DO TEMA", h_bloco))
        for q_ in a["minisimulado"]:
            story.append(Paragraph(q_.replace("\n", "<br/>"), body))

        story.append(Paragraph("15. RESUMO DE MEMORIZAÇÃO RÁPIDA", h_bloco))
        r = a["resumo"]
        for k, label in [("regra","Regra"),("excecoes","Exceções"),
                         ("palavra_chave","Palavra-chave"),("artigo","Artigo essencial"),
                         ("mnemonico","Mnemônico")]:
            story.append(Paragraph(f'<font color="{AZUL_ESCURO}"><b>{label}:</b></font> {r[k]}', body))

    # 4. Índice completo
    story.append(PageBreak())
    story.append(Paragraph("4. ÍNDICE COMPLETO — 80 QUESTÕES (ENUNCIADO + ALTERNATIVAS)", h_bloco))
    story.append(Paragraph(
        "Reprodução dos 80 itens objetivos do caderno (Formação Geral Docente + Componente "
        "Específico – História).", body))

    for q in QUESTOES:
        story.append(Paragraph(f"Q{q['numero']:02d}  |  {q['secao']}", q_h))
        story.append(Paragraph(q["enunciado"], body))
        for letra, texto in q["alternativas"].items():
            story.append(Paragraph(f"<b>({letra})</b> {texto}", body))

    # 5. Referências
    story.append(PageBreak())
    story.append(Paragraph("5. REFERÊNCIAS ABNT CONSOLIDADAS — LOTE 1", h_bloco))
    refs = set()
    for a in ANALISES.values():
        refs.update(a["fundamentacao"])
    for ref in sorted(refs):
        story.append(Paragraph("• " + ref, body))

    pdf = SimpleDocTemplate(str(destino), pagesize=A4,
                            leftMargin=2*cm, rightMargin=2*cm,
                            topMargin=1.8*cm, bottomMargin=1.4*cm,
                            title="Manual PND História — Análise por Questões")
    pdf.build(story, onFirstPage=on_page, onLaterPages=on_page)


if __name__ == "__main__":
    saidas = BASE / "saidas"
    docx_path = saidas / "manual_pnd_historia.docx"
    pdf_path  = saidas / "manual_pnd_historia.pdf"
    gerar_docx(docx_path)
    gerar_pdf(pdf_path)
    print("DOCX:", docx_path)
    print("PDF: ", pdf_path)
