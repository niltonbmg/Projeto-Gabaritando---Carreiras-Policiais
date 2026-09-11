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

    6: {
        "tema": "Paulo Freire e sequência didática crítica em Educação Ambiental",
        "subtema": "Levantamento do entorno + problematização + intervenção",
        "habilidade_bncc": "BNCC – competências gerais 7 e 10; TCT Educação Ambiental",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Prática (planejamento freireano)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a sequência didática que EXPRESSE a perspectiva freireana. Freire propõe partir da "
            "realidade concreta (tema gerador), problematizar, sistematizar conceitos e retornar à prática "
            "transformadora. Alternativas incorretas misturam Freire com Ausubel (subsunçores), Vigotski (ZDP) "
            "e abordagens transmissivas."
        ),
        "como_banca_pensou": (
            "O INEP contrasta a matriz freireana com outras teorias da aprendizagem. O par 'levantamento do "
            "entorno + problematização + análise crítica' é a assinatura de Freire. As demais opções trocam "
            "propositalmente o vocabulário: 'subsunçores' (Ausubel), 'ZDP' (Vigotski), 'modelo de estufa' "
            "(experimentalismo)."
        ),
        "resolucao": [
            "Reconheça a marca freireana: partir do CONTEXTO REAL do estudante (entorno, comunidade).",
            "Descarte B: usa 'modelo de estufa' e 'fixação' — abordagem tecnicista/comportamentalista.",
            "Descarte C: 'organizar os subsunçores' é ANCORAGEM PRÉVIA de Ausubel, não Freire.",
            "Descarte D: 'Zona de Desenvolvimento Proximal' é Vigotski.",
            "Marque A: levantamento do entorno + conceitos escolares que ajudam a compreender + aplicação crítica (COP30/rap) — sequência freireana clássica.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Ordem freireana: leitura de mundo → problematização → sistematização → retorno crítico à realidade (Freire, 1996; 2000)."),
            "B": ("ERRADA", "Vídeo + modelo de estufa + 'fixação' remetem ao ensino tecnicista, não à pedagogia libertadora."),
            "C": ("ERRADA", "'Subsunçores' é conceito da Aprendizagem Significativa de Ausubel; não descreve Freire."),
            "D": ("ERRADA", "'Zona de Desenvolvimento Proximal' é conceito de Vigotski (sociointeracionismo), não de Freire."),
        },
        "fundamentacao": [
            "FREIRE, Paulo. Pedagogia do oprimido. 17. ed. Rio de Janeiro: Paz e Terra, 1987.",
            "FREIRE, Paulo. Pedagogia da autonomia: saberes necessários à prática educativa. São Paulo: Paz e Terra, 1996.",
            "AUSUBEL, David P. Aquisição e retenção de conhecimentos: uma perspectiva cognitiva. Lisboa: Plátano, 2003.",
            "VYGOTSKY, Lev S. A formação social da mente. São Paulo: Martins Fontes, 1991.",
            "BRASIL. Lei nº 9.795, de 27 de abril de 1999. Política Nacional de Educação Ambiental. DOU, Brasília, 28 abr. 1999.",
        ],
        "teoria": (
            "A pedagogia freireana articula LEITURA DE MUNDO (contexto e experiência do educando) → "
            "PROBLEMATIZAÇÃO (temas geradores) → SISTEMATIZAÇÃO (conceitos científicos) → PRÁXIS "
            "(ação transformadora sobre a realidade). O ponto de partida é sempre a realidade concreta do "
            "educando, jamais um conteúdo isolado. Ausubel (subsunçores), Vigotski (ZDP, mediação) e "
            "abordagens tecnicistas têm outros marcos teóricos e não devem ser confundidos com Freire, "
            "embora possam DIALOGAR com sua perspectiva. Em Educação Ambiental, Freire dialoga com Loureiro "
            "(EA crítica) e com a noção de 'sujeitos ecológicos' de Carvalho."
        ),
        "padroes_banca": (
            "O INEP frequentemente distribui matrizes teóricas diferentes por alternativa. A pegadinha é "
            "escolher a que descreve uma boa prática, mesmo que não seja aquela pedida. Aqui, TODAS "
            "podem ser boas em contextos próprios; a correta é a que é FREIREANA."
        ),
        "pegadinhas": [
            "Confundir Freire com Ausubel (subsunçores) — clássico em prova docente.",
            "Trocar Freire por Vigotski (ZDP).",
            "Achar que 'exibir vídeo + fixação' é atividade crítica.",
        ],
        "erros_comuns": (
            "Muitos candidatos marcam C por 'subsunçores' soar sofisticado, ou D pela ZDP ser popular. "
            "Erram por não observar a base epistemológica pedida (Freire)."
        ),
        "dica_estrategica": (
            "Assinatura freireana em prova: LEITURA DE MUNDO + PROBLEMATIZAÇÃO + PRÁXIS. Se aparecem "
            "'subsunçores', 'ZDP' ou 'fixação de conteúdo' → NÃO é Freire."
        ),
        "variacao": (
            "(Estilo INEP) Segundo a pedagogia freireana, a construção do conhecimento na Educação Básica "
            "parte de:\n"
            "A) organizadores prévios elaborados pelo professor (subsunçores);\n"
            "B) exercícios de repetição para fixar conteúdos;\n"
            "C) temas geradores extraídos da realidade dos educandos, com problematização crítica;\n"
            "D) prescrições curriculares detalhadas, controladas pelo sistema.\n"
            "Gabarito: C."
        ),
        "minisimulado": [
            "Q1. 'Ninguém educa ninguém, ninguém educa a si mesmo, os homens se educam entre si, mediatizados pelo mundo' é frase-síntese de:\n"
            "A) Ausubel; B) Vigotski; C) Freire; D) Piaget. → C",
            "Q2. A Zona de Desenvolvimento Proximal foi teorizada por:\n"
            "A) Freire; B) Ausubel; C) Piaget; D) Vigotski. → D",
            "Q3. Subsunçores, na aprendizagem significativa, são:\n"
            "A) conceitos prévios que ancoram novos conhecimentos; B) atividades de fixação; C) temas geradores; D) mediações sociais. → A",
        ],
        "resumo": {
            "regra": "Freire = leitura de mundo + problematização + práxis; sempre parte do contexto concreto.",
            "excecoes": "Ausubel = subsunçores; Vigotski = ZDP; Piaget = estágios; Skinner = comportamentalismo — não confundir.",
            "palavra_chave": "Contexto → Problematização → Práxis.",
            "artigo": "FREIRE, P. (1987; 1996).",
            "mnemonico": "3 P's freireanos: Partir do real, Problematizar, Praxear.",
        },
    },

    7: {
        "tema": "Metodologias Ativas e inclusão digital",
        "subtema": "Jogos desplugados como resposta à desigualdade de acesso",
        "habilidade_bncc": "BNCC – competências gerais 4 e 5 (comunicação, cultura digital)",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item combina METODOLOGIAS ATIVAS + DESIGUALDADE DE ACESSO À INTERNET. A resposta deve conciliar "
            "PROTAGONISMO ESTUDANTIL com INCLUSÃO — condições que apenas os JOGOS DESPLUGADOS atendem."
        ),
        "como_banca_pensou": (
            "O INEP cria um cenário em que 60% dos estudantes não têm internet. Alternativas que dependem de "
            "'dispositivos móveis' ou de 'plataforma digital' EXCLUEM esses estudantes. Metodologia ativa "
            "não é sinônimo de 'digital'. A resposta é a única que conjuga PROTAGONISMO + BAIXO CUSTO + "
            "SOCIALIZAÇÃO."
        ),
        "resolucao": [
            "Grife o dado do IBGE: 60% sem acesso à internet.",
            "Descarte A: 'aula expositiva + exercícios de múltipla escolha' é ensino transmissivo, não ativo.",
            "Descarte C: leitura + avaliação é modelo tradicional.",
            "Descarte D: 'gamificada com dispositivos móveis' contradiz o cenário de exclusão apontado.",
            "Marque B: JOGOS DESPLUGADOS mantêm a metodologia ativa SEM depender de tecnologia digital, e permitem SOCIALIZAÇÃO em plenária.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Aula expositiva não é metodologia ativa."),
            "B": ("CORRETA", "Jogos desplugados (sem plataforma digital) preservam o protagonismo e superam a barreira do acesso à internet."),
            "C": ("ERRADA", "Modelo tradicional (leitura + avaliação); não é ativo."),
            "D": ("ERRADA", "Dependência de dispositivos móveis reproduz a exclusão apontada no enunciado."),
        },
        "fundamentacao": [
            "IBGE. Pesquisa Nacional por Amostra de Domicílios Contínua – TIC. Rio de Janeiro: IBGE, 2023.",
            "MORAN, José Manuel. Metodologias ativas para uma educação inovadora. Porto Alegre: Penso, 2018.",
            "BACICH, Lilian; MORAN, José (org.). Metodologias ativas para uma educação inovadora. Porto Alegre: Penso, 2018.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "METODOLOGIAS ATIVAS são estratégias em que o(a) estudante ocupa papel central na construção do "
            "conhecimento — problematização, sala de aula invertida, aprendizagem baseada em problemas (PBL), "
            "gamificação, ensino híbrido, rotação por estações. A digitalização é UMA das linguagens, mas não "
            "condição necessária: existem versões 'DESPLUGADAS' (unplugged) — jogos analógicos, dinâmicas, "
            "estações sem tela. Diante da desigualdade digital (60% sem internet, IBGE 2023), a inclusão "
            "escolar exige planejamentos que NÃO PRESSUPONHAM dispositivos individuais. O conceito de "
            "'letramento digital crítico' inclui refletir sobre o próprio acesso."
        ),
        "padroes_banca": (
            "O INEP usa dados IBGE para calibrar o cenário. A alternativa correta costuma responder à "
            "restrição objetiva imposta pelo enunciado, e não à 'moda didática' geral."
        ),
        "pegadinhas": [
            "Confundir metodologia ativa com 'tecnologia digital'.",
            "Escolher gamificação por parecer inovadora, ignorando a exclusão.",
            "Achar que aula expositiva com múltipla escolha é ativa.",
        ],
        "erros_comuns": (
            "Marcar D por preconceito de que 'ativo = com tela'. O correto é reconhecer que o desplugado "
            "atende ao contexto de desigualdade."
        ),
        "dica_estrategica": (
            "Quando o enunciado apresentar desigualdade digital, elimine imediatamente as alternativas "
            "que dependam de acesso a dispositivos/plataformas."
        ),
        "variacao": (
            "(Estilo INEP) Considere uma turma da Educação Básica em contexto de vulnerabilidade digital. "
            "Uma prática pedagógica alinhada às Metodologias Ativas e inclusiva é:\n"
            "A) sala de aula invertida integralmente por streaming;\n"
            "B) aprendizagem baseada em projetos com materiais analógicos e rodízio de estações;\n"
            "C) prova online com tempo cronometrado;\n"
            "D) aula 100% assíncrona por aplicativo. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. São exemplos de metodologias ativas, EXCETO:\n"
            "A) sala de aula invertida; B) PBL; C) aula expositiva com dizer o conteúdo; D) rotação por estações. → C",
            "Q2. Letramento digital crítico envolve:\n"
            "A) apenas usar apps; B) refletir sobre acesso, uso e implicações sociotécnicas; C) memorizar comandos; "
            "D) programar em Python. → B",
            "Q3. Desplugadas são atividades:\n"
            "A) exclusivamente digitais; B) sem uso de dispositivos, com dinâmicas analógicas; "
            "C) híbridas com apps; D) online. → B",
        ],
        "resumo": {
            "regra": "Metodologia ativa ≠ metodologia digital; a atividade pode ser desplugada.",
            "excecoes": "Em contextos com acesso pleno, o digital amplia possibilidades.",
            "palavra_chave": "Protagonismo + Inclusão.",
            "artigo": "IBGE (2023); BACICH & MORAN (2018).",
            "mnemonico": "MADE = Metodologia Ativa DEsplugada quando falta acesso.",
        },
    },

    8: {
        "tema": "Educação do Campo – princípios formativos",
        "subtema": "Resolução CEB/CNE nº 1/2002 e movimentos sociais",
        "habilidade_bncc": "DCN da Educação do Campo; BNCC – competência geral 9",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Teórica / Legislação",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o princípio da Educação do Campo: reconhecer o campo como LUGAR DE VIDA, TRABALHO "
            "E LUTA, historicamente marcado por um modelo de desenvolvimento exploratório. As demais "
            "alternativas invertem esse sentido (subordinam o campo ao urbano ou opõem-se ao currículo)."
        ),
        "como_banca_pensou": (
            "O INEP quer distinguir Educação do Campo (fora da lógica urbanocêntrica, ligada a movimentos "
            "sociais como o MST) de simples 'educação rural'. A alternativa correta articula "
            "RECONHECIMENTO + HISTORICIDADE + CRÍTICA ao modelo exploratório."
        ),
        "resolucao": [
            "Descarte A: 'subordinar' o campo à história urbana contraria o próprio princípio da EdoC.",
            "Descarte B: 'que contrariam o currículo instituído' é reducionista; a EdoC constrói currículo próprio.",
            "Descarte C: 'desconsiderar saberes urbanos' opõe indevidamente campo e cidade.",
            "Marque D: reconhecer o campo como lugar de vida e produção com histórico de projeto exploratório = princípio da EdoC.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Subordinação = o oposto da EdoC, que reivindica autonomia epistêmica e curricular."),
            "B": ("ERRADA", "A EdoC não busca 'contrariar' o currículo, mas construir outro, próprio, dialogando com o urbano."),
            "C": ("ERRADA", "Não se trata de desconsiderar saberes urbanos, mas de reconhecer os saberes do campo."),
            "D": ("CORRETA", "Fórmula-síntese da EdoC: campo como lugar de vida, trabalho, cultura e luta, historicamente atravessado por modelo desenvolvimentista/agroexportador."),
        },
        "fundamentacao": [
            "BRASIL. Resolução CEB/CNE nº 1, de 3 de abril de 2002. Institui Diretrizes Operacionais para a Educação Básica nas Escolas do Campo. DOU, Brasília, 9 abr. 2002.",
            "BRASIL. Decreto nº 7.352, de 4 de novembro de 2010. Dispõe sobre a política de educação do campo e o PRONERA. DOU, Brasília, 5 nov. 2010.",
            "CALDART, Roseli Salete. Sobre educação do campo. In: SANTOS, C. (org.). Por uma Educação do Campo. 4. ed. Brasília: INCRA/MDA, 2011.",
            "ARROYO, Miguel G.; CALDART, Roseli; MOLINA, Mônica (org.). Por uma educação do campo. 4. ed. Petrópolis: Vozes, 2009.",
        ],
        "teoria": (
            "A EDUCAÇÃO DO CAMPO (EdoC) surge nos anos 1990 vinculada aos movimentos sociais do campo "
            "(especialmente o MST) e às Conferências Nacionais 'Por uma Educação Básica do Campo' (1998 e "
            "2004). Ela se contrapõe à 'educação rural', historicamente marcada por currículos urbanocêntricos, "
            "escolas precárias e desvalorização do trabalho camponês. A Resolução CEB/CNE nº 1/2002 e o "
            "Decreto 7.352/2010 institucionalizam a EdoC como política pública. Princípios: (1) o campo como "
            "lugar de vida, produção e cultura; (2) formação docente específica (Licenciatura em Educação do "
            "Campo — LEdoC); (3) alternância pedagógica (Tempo-Escola/Tempo-Comunidade); (4) currículo "
            "integrado ao trabalho e à cultura camponesa; (5) diálogo com povos indígenas, quilombolas, "
            "ribeirinhos e assentados."
        ),
        "padroes_banca": (
            "O INEP costuma opor 'educação RURAL' (imposta, urbanocêntrica) a 'educação DO CAMPO' "
            "(construída com sujeitos do campo). Alternativas erradas mantêm marcas do primeiro."
        ),
        "pegadinhas": [
            "Confundir 'do campo' com 'no campo' (esta é apenas geográfica).",
            "Reduzir EdoC à agricultura, ignorando cultura e política.",
            "Achar que EdoC ignora o urbano — na verdade, dialoga.",
        ],
        "erros_comuns": (
            "Marcar C por associar campo a trabalho, sem perceber que a alternativa opõe indevidamente "
            "campo e cidade. Também marcar B por parecer 'crítico'."
        ),
        "dica_estrategica": (
            "Em item sobre EdoC, procure alternativas que combinem: SUJEITOS DO CAMPO + RECONHECIMENTO + "
            "HISTÓRIA + DIÁLOGO COM MOVIMENTOS SOCIAIS."
        ),
        "variacao": (
            "(Estilo INEP) A Licenciatura em Educação do Campo (LEdoC), oferecida por universidades públicas, "
            "adota como metodologia estruturante a:\n"
            "A) formação a distância exclusiva;\n"
            "B) alternância entre Tempo-Escola e Tempo-Comunidade;\n"
            "C) formação urbanocêntrica com estágio pontual;\n"
            "D) transposição do currículo urbano.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O PRONERA (Programa Nacional de Educação na Reforma Agrária) foi criado para atender:\n"
            "A) estudantes urbanos; B) povos do campo, assentados e acampados; C) escolas técnicas privadas; "
            "D) universidades particulares. → B",
            "Q2. Arroyo, Caldart e Molina defendem a educação do campo como:\n"
            "A) apêndice da rural; B) direito dos sujeitos do campo à educação que reconheça sua vida e trabalho; "
            "C) projeto urbanocêntrico; D) política de mercado. → B",
            "Q3. Resolução CEB/CNE nº 1/2002 institui:\n"
            "A) BNCC; B) Diretrizes Operacionais para a Educação Básica nas Escolas do Campo; "
            "C) Piso do magistério; D) Fundeb. → B",
        ],
        "resumo": {
            "regra": "EdoC = educação DO campo, COM os sujeitos do campo, contra a lógica urbanocêntrica.",
            "excecoes": "Escolas 'no campo' podem não ser EdoC se reproduzirem currículo urbano.",
            "palavra_chave": "Sujeitos do campo + alternância + movimentos sociais.",
            "artigo": "Resolução CEB/CNE 1/2002; Decreto 7.352/2010.",
            "mnemonico": "3 C's da EdoC: Campo, Comunidade, Contra-hegemonia.",
        },
    },

    9: {
        "tema": "Escola Nova no Brasil (anos 1920-1930)",
        "subtema": "Escolanovismo e o Manifesto dos Pioneiros",
        "habilidade_bncc": "História da Educação; DCN – Formação Docente",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta (história da educação)",
        "justificativa_classificacao": (
            "Item cobra a caracterização do ESCOLANOVISMO: pedagogia CENTRADA NO ALUNO, na experiência e no "
            "interesse (Dewey, Anísio Teixeira, Fernando de Azevedo). Alternativas erradas atribuem-lhe "
            "traços de outras pedagogias (tradicional, tecnicista, jesuítica)."
        ),
        "como_banca_pensou": (
            "O INEP distribui uma pedagogia em cada alternativa: A (Tradicional/essencialista/professor no centro), "
            "B (Tecnicista/eficiência/neutralidade), C (Jesuítica/catequese), D (Escolanovista, correta). "
            "A pegadinha exige DOMÍNIO DA CRONOLOGIA das pedagogias brasileiras."
        ),
        "resolucao": [
            "Fixe: Escola Nova = anos 1920-30, John Dewey nos EUA; no Brasil, Manifesto dos Pioneiros (1932).",
            "Descarte A: 'essencialismo + professor no centro' descreve a Pedagogia Tradicional.",
            "Descarte B: 'neutralidade + racionalidade + eficiência' descreve o Tecnicismo (1960-70).",
            "Descarte C: 'português para indígenas + doutrina cristã' descreve a Pedagogia Jesuítica (séc. XVI-XVIII).",
            "Marque D: vivências, estratégias e interesse do estudante = ESCOLA NOVA.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Descreve a Pedagogia Tradicional (professor no centro), rejeitada pelo escolanovismo."),
            "B": ("ERRADA", "Descreve o Tecnicismo dos anos 1960-70, típico do regime militar."),
            "C": ("ERRADA", "Descreve a educação jesuítica colonial (séc. XVI-XVIII), pré-escolanovista."),
            "D": ("CORRETA", "Traços clássicos do escolanovismo: centralidade nas vivências, no interesse e nas estratégias de ensino ativas."),
        },
        "fundamentacao": [
            "MANIFESTO DOS PIONEIROS DA EDUCAÇÃO NOVA (1932). In: XAVIER, Maria do Carmo (org.). Manifesto dos Pioneiros da Educação: um legado educacional em debate. Rio de Janeiro: FGV, 2004.",
            "DEWEY, John. Democracia e educação. 3. ed. São Paulo: Companhia Editora Nacional, 1959.",
            "SAVIANI, Dermeval. História das ideias pedagógicas no Brasil. 5. ed. Campinas: Autores Associados, 2019.",
            "GHIRALDELLI JR., Paulo. História da educação brasileira. 5. ed. São Paulo: Cortez, 2015.",
        ],
        "teoria": (
            "A ESCOLA NOVA (Escolanovismo) irrompe no Brasil nos anos 1920-30, tendo como marcos o "
            "Manifesto dos Pioneiros da Educação Nova (1932) e figuras como Anísio Teixeira, Fernando de "
            "Azevedo, Lourenço Filho e Cecília Meireles. Inspira-se em John Dewey (aprender fazendo, escola "
            "democrática) e Édouard Claparède (educação centrada nos interesses da criança). Princípios: "
            "centralidade do aluno, aprender pela experiência, valorização do trabalho em grupo, respeito às "
            "individualidades, escola pública, laica, gratuita, obrigatória. Contrapõe-se à Pedagogia "
            "Tradicional (professor no centro) e antecede as pedagogias Progressista Libertadora (Freire) e "
            "Crítico-Social dos Conteúdos (Saviani, Libâneo). Foi antecipatória da LDB de 1961."
        ),
        "padroes_banca": (
            "O INEP cobra a cronologia das pedagogias brasileiras. É comum item apresentar traços em cada "
            "alternativa e pedir para associar corretamente."
        ),
        "pegadinhas": [
            "Confundir Escola Nova (progressista) com Escola Tradicional (essencialista).",
            "Confundir Escolanovismo com Tecnicismo (ambos falam de método, mas com bases opostas).",
            "Deslocar cronologicamente para o Império ou Colônia.",
        ],
        "erros_comuns": (
            "Marcar B por 'racionalidade' soar moderno, sem perceber que é Tecnicismo. Ou marcar A por "
            "confusão sobre 'centralidade'."
        ),
        "dica_estrategica": (
            "Memorize a linha do tempo das pedagogias brasileiras: Jesuítica (col.) → Pombalina (1759) → "
            "Positivista (1900) → Escola Nova (1920-30) → Tecnicismo (1960-70) → Libertadora (1980) → "
            "Crítico-social (1980+) → Construtivismo (1990+) → BNCC (2018)."
        ),
        "variacao": (
            "(Estilo INEP) O Manifesto dos Pioneiros da Educação Nova (1932) defendeu:\n"
            "A) escola confessional e privada;\n"
            "B) escola pública, laica, gratuita, obrigatória e centrada no estudante;\n"
            "C) escola tecnicista com ênfase em eficiência;\n"
            "D) educação exclusivamente doméstica.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Anísio Teixeira defendia:\n"
            "A) escola dual e elitista; B) escola pública democrática; C) tecnicismo; D) educação jesuítica. → B",
            "Q2. A Pedagogia Tecnicista, dominante nos anos 1960-70, tinha como marca:\n"
            "A) o aluno como centro; B) neutralidade, racionalidade e eficiência; C) o trabalho por temas geradores; "
            "D) a centralidade da experiência coletiva. → B",
            "Q3. Dewey é referência para:\n"
            "A) Pedagogia Tradicional; B) Escola Nova; C) Pedagogia Jesuítica; D) Tecnicismo. → B",
        ],
        "resumo": {
            "regra": "Escola Nova = aluno no centro, aprender pela experiência, escola pública, laica e democrática.",
            "excecoes": "Não confundir com Tecnicismo (eficiência) nem com Tradicional (professor no centro).",
            "palavra_chave": "Aluno + experiência + interesse.",
            "artigo": "Manifesto dos Pioneiros (1932); DEWEY, J. (1959).",
            "mnemonico": "'AAA' Escola Nova: Aluno-Ativo-Autônomo.",
        },
    },

    10: {
        "tema": "EJA, interdisciplinaridade e mundo do trabalho",
        "subtema": "Integração currículo-vivência (Freire, Arroyo)",
        "habilidade_bncc": "DCN da EJA; BNCC – competências gerais 6, 7 e 10",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Prática (análise de caso)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a caracterização das ações pedagógicas descritas no texto-base (EJA + Estágio "
            "Supervisionado). O caso mostra AÇÃO INTERDISCIPLINAR + INTEGRAÇÃO CURRÍCULO-VIVÊNCIAS + "
            "REFLEXÃO SOBRE O MUNDO DO TRABALHO. A resposta é a alternativa que sintetiza esse tripé."
        ),
        "como_banca_pensou": (
            "O INEP cobra a leitura da CENA PEDAGÓGICA. As alternativas erradas destacam APENAS UM aspecto "
            "(conteúdos disciplinares, uma única área, metodologias inovadoras) — a correta agrega "
            "'integração das vivências ao currículo' + 'reflexões sobre o mundo do trabalho'."
        ),
        "resolucao": [
            "Identifique no texto: professor(a) de História + Língua Portuguesa + Matemática articulam-se.",
            "Reconheça a marca: PARTIR DA VIVÊNCIA (motoristas de app, entregadores) para o CURRÍCULO.",
            "Descarte A: 'priorizam conteúdos disciplinares' contradiz o caso.",
            "Descarte B: 'enfatizam uma área' — o caso é interdisciplinar.",
            "Descarte C: 'metodologias inovadoras' é vago; a chave é integração vivência-currículo.",
            "Marque D: INTEGRAM VIVÊNCIAS + REFLEXÃO sobre o mundo do trabalho.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "As ações partem justamente das vivências, não dos conteúdos disciplinares isolados."),
            "B": ("ERRADA", "Envolvem três áreas (História, LP, Matemática) — não uma única."),
            "C": ("ERRADA", "'Metodologias inovadoras' é enunciado esvaziado; não expressa o cerne da cena."),
            "D": ("CORRETA", "Síntese exata: vivências → currículo + reflexão sobre transformações do trabalho."),
        },
        "fundamentacao": [
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CEB nº 1, de 5 de julho de 2000. Diretrizes Curriculares Nacionais para a EJA.",
            "BRASIL. Conselho Nacional de Educação. Parecer CNE/CEB nº 11/2000 (Relator: Jamil Cury). Diretrizes Curriculares Nacionais para a EJA.",
            "FREIRE, Paulo. Pedagogia do oprimido. 17. ed. Rio de Janeiro: Paz e Terra, 1987.",
            "ARROYO, Miguel G. Currículo, território em disputa. Petrópolis: Vozes, 2011.",
            "ANTUNES, Ricardo. O privilégio da servidão: o novo proletariado de serviços na era digital. São Paulo: Boitempo, 2018.",
        ],
        "teoria": (
            "A Educação de Jovens e Adultos (EJA) tem, nas DCNs (Res. CNE/CEB 1/2000) e no Parecer CNE/CEB "
            "11/2000, funções REPARADORA, EQUALIZADORA e QUALIFICADORA. O currículo deve articular-se ao "
            "MUNDO DO TRABALHO e às experiências dos educandos, dialogando com Freire (temas geradores) e "
            "Arroyo (currículo como território em disputa). O caso apresentado exemplifica a INTERDISCIPLI"
            "NARIDADE (LP, Matemática, História) mobilizada a partir da uberização do trabalho (motoristas "
            "de aplicativo, entregadores autônomos — Antunes, 2018), articulando cultura, letramentos e "
            "cidadania."
        ),
        "padroes_banca": (
            "O INEP frequentemente apresenta cenas pedagógicas e pede síntese conceitual. Alternativas "
            "erradas tomam APENAS um ou outro aspecto isoladamente."
        ),
        "pegadinhas": [
            "Marcar 'metodologias inovadoras' por soar bonito, sem substância.",
            "Reduzir o caso a uma única área.",
            "Ignorar a marca da EJA: vínculo com o mundo do trabalho.",
        ],
        "erros_comuns": (
            "Marcam C por 'inovador' ou B por não perceberem a interdisciplinaridade descrita."
        ),
        "dica_estrategica": (
            "Em cenas de EJA, priorize alternativas que citem MUNDO DO TRABALHO, VIVÊNCIAS, "
            "INTERDISCIPLINARIDADE e CURRÍCULO INTEGRADO."
        ),
        "variacao": (
            "(Estilo INEP) As DCNs para a EJA (Parecer CNE/CEB 11/2000) atribuem à modalidade as funções:\n"
            "A) apenas suplementar e de aceleração;\n"
            "B) reparadora, equalizadora e qualificadora;\n"
            "C) tecnicista e mercadológica;\n"
            "D) exclusivamente propedêutica.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A uberização do trabalho, discutida por Antunes (2018), refere-se a:\n"
            "A) empregos formais estáveis; B) plataformização e precarização por aplicativos; "
            "C) trabalho rural tradicional; D) trabalho público. → B",
            "Q2. Interdisciplinaridade na EJA implica:\n"
            "A) sobreposição de disciplinas; B) articulação entre saberes e vivências dos educandos; "
            "C) fim das disciplinas; D) padronização metodológica. → B",
            "Q3. 'Currículo, território em disputa' é obra de:\n"
            "A) Freire; B) Vigotski; C) Arroyo; D) Saviani. → C",
        ],
        "resumo": {
            "regra": "EJA integra VIVÊNCIAS + MUNDO DO TRABALHO + INTERDISCIPLINARIDADE ao currículo.",
            "excecoes": "Não confundir com ensino supletivo puro (que apenas certifica).",
            "palavra_chave": "Vivência + trabalho + interdisciplinar.",
            "artigo": "Res. CNE/CEB 1/2000; Parecer CNE/CEB 11/2000.",
            "mnemonico": "REQ: Reparadora, Equalizadora, Qualificadora.",
        },
    },

    11: {
        "tema": "Estágio Supervisionado como espaço de coformação",
        "subtema": "Professor-supervisor como coformador (Pimenta & Lima)",
        "habilidade_bncc": "DCN Formação Docente – Res. CNE/CP 2/2019; Lei 11.788/2008",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a concepção contemporânea de Estágio Supervisionado: espaço de FORMAÇÃO "
            "COMPARTILHADA entre universidade, escola e estagiários, no qual o(a) professor(a) da escola "
            "básica assume papel de COFORMADOR(A). As demais alternativas reduzem o estágio a "
            "'aquisição/aplicação de conteúdos', visão superada pela literatura de formação docente."
        ),
        "como_banca_pensou": (
            "O INEP quer distinguir o Estágio como PRÁXIS FORMATIVA (Pimenta & Lima) de visões instrumentais. "
            "Palavras-veneno nas alternativas erradas: 'aquisição', 'aplicação', 'aquisição de tecnologias' — "
            "todas apontam para modelo tecnicista/aplicacionista."
        ),
        "resolucao": [
            "Reconheça a chave: 'espaço de' pede um SENTIDO conceitual de Estágio.",
            "Descarte B: 'aquisição dos conteúdos' reduz o estágio a transmissão.",
            "Descarte C: 'aquisição de novas tecnologias pelo supervisor' inverte a lógica formativa.",
            "Descarte D: 'aplicação de conhecimentos do supervisor no cotidiano' é modelo aplicacionista.",
            "Marque A: 'formação pedagógica que considera o professor-supervisor como COFORMADOR' — expressão consagrada em Pimenta & Lima.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Concepção contemporânea: escola como espaço de formação e docente da EB como coformador(a) dos estagiários (Pimenta & Lima, 2004)."),
            "B": ("ERRADA", "'Aquisição de conteúdos' reduz o estágio ao ensino tradicional."),
            "C": ("ERRADA", "Inverte a lógica: o estágio não visa 'aquisição' de tecnologias pelo supervisor."),
            "D": ("ERRADA", "'Aplicação' é modelo aplicacionista, criticado pela literatura de formação docente (Tardif, Nóvoa)."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 11.788, de 25 de setembro de 2008. Dispõe sobre o estágio de estudantes. DOU, Brasília, 26 set. 2008.",
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CP nº 2, de 20 de dezembro de 2019. Diretrizes Curriculares Nacionais para a Formação Inicial de Professores para a Educação Básica (BNC-Formação).",
            "PIMENTA, Selma Garrido; LIMA, Maria do Socorro L. Estágio e docência. São Paulo: Cortez, 2004.",
            "TARDIF, Maurice. Saberes docentes e formação profissional. Petrópolis: Vozes, 2002.",
            "NÓVOA, António. Firmar a posição como professor, afirmar a profissão docente. Cadernos de Pesquisa, v. 47, n. 166, 2017.",
        ],
        "teoria": (
            "O Estágio Supervisionado é regulado pela Lei 11.788/2008 e pelas DCNs da Formação Docente "
            "(Res. CNE/CP 2/2019). A literatura (Pimenta & Lima, 2004) o define como PRÁXIS FORMATIVA — "
            "articulação entre teoria e prática, pesquisa da própria docência e formação COMPARTILHADA entre "
            "a universidade e a escola. O(a) professor(a) da Educação Básica que recebe estagiários é "
            "COFORMADOR(A): não apenas 'observado(a)', mas coparticipante ativo(a) na formação. Tardif (2002) "
            "fala em saberes da experiência; Nóvoa (2017), na 'entrada' do docente na profissão. Superam-se, "
            "assim, visões aplicacionistas (praticar o que a universidade ensina) e imitativas (observar o "
            "'bom professor')."
        ),
        "padroes_banca": (
            "O INEP costuma opor visões contemporâneas (coformação, práxis) a modelos superados "
            "(aplicacionismo, transmissão). A alternativa correta traz 'coformador', 'práxis' ou "
            "'pesquisa da docência'."
        ),
        "pegadinhas": [
            "Confundir estágio com 'aplicação de teoria' (modelo aplicacionista).",
            "Reduzir o supervisor a 'observador' — ele é coformador.",
            "Marcar 'aquisição de tecnologias' por soar atual.",
        ],
        "erros_comuns": (
            "Marcar D por parecer razoável ('aplicar conhecimentos'). É o modelo mais criticado pela "
            "literatura de formação docente."
        ),
        "dica_estrategica": (
            "Em item sobre Estágio, prefira alternativas com COFORMAÇÃO, PRÁXIS, PESQUISA DA DOCÊNCIA. "
            "Elimine as que usam APLICAÇÃO, AQUISIÇÃO, TRANSMISSÃO."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Pimenta e Lima (2004), o Estágio Supervisionado deve ser compreendido como:\n"
            "A) mera aplicação de teorias aprendidas na universidade;\n"
            "B) atividade prática desvinculada da pesquisa;\n"
            "C) práxis formativa que articula teoria, prática e pesquisa da docência, em coformação escola-universidade;\n"
            "D) observação passiva do bom professor.\n"
            "Gabarito: C."
        ),
        "minisimulado": [
            "Q1. A Lei 11.788/2008 dispõe sobre:\n"
            "A) Piso salarial docente; B) Estágio de estudantes; C) BNCC; D) FUNDEB. → B",
            "Q2. Tardif (2002) tematiza:\n"
            "A) saberes docentes e formação profissional; B) BNCC; C) financiamento; D) avaliação em larga escala. → A",
            "Q3. A Res. CNE/CP 2/2019 institui:\n"
            "A) BNCC da EB; B) BNC-Formação de Professores; C) LDB; D) DCN da EJA. → B",
        ],
        "resumo": {
            "regra": "Estágio = práxis formativa; docente da EB = coformador(a).",
            "excecoes": "Modelos aplicacionistas e imitativos estão superados na literatura.",
            "palavra_chave": "Coformação + práxis.",
            "artigo": "Lei 11.788/2008; Res. CNE/CP 2/2019; PIMENTA & LIMA (2004).",
            "mnemonico": "3 C's do Estágio: Coformação, Complexidade, Contexto.",
        },
    },

    12: {
        "tema": "Pedagogia de Projetos e ODS 12 (consumo responsável)",
        "subtema": "Ação educativa que INTERVÉM concretamente",
        "habilidade_bncc": "BNCC – competências gerais 7 e 10; TCT Educação Ambiental; ODS 12",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "O comando pede a ação que INTERVÉM CONCRETAMENTE no contexto escolar. A Pedagogia de Projetos "
            "(Hernández, Dewey) exige AÇÃO + PROTAGONISMO + IMPACTO NO REAL — apenas a alternativa D oferece "
            "isso (oficina + acompanhamento das mudanças)."
        ),
        "como_banca_pensou": (
            "O INEP contrasta ações de PESQUISA/DIVULGAÇÃO (A, B, C) com ação de INTERVENÇÃO (D). "
            "Só a alternativa D promove ATUAÇÃO na comunidade escolar + AVALIAÇÃO das mudanças reais."
        ),
        "resolucao": [
            "Grife 'intervém CONCRETAMENTE'.",
            "Descarte A: 'levantamento + divulgação em evento científico' = pesquisa, não intervenção.",
            "Descarte B: 'mapear + elaborar redação' = registro, não intervenção.",
            "Descarte C: 'pesquisar + analisar dados' = pesquisa.",
            "Marque D: 'oficina de reaproveitamento + acompanhamento das mudanças' = INTERVENÇÃO REAL.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Divulgação em evento científico não constitui intervenção no contexto escolar."),
            "B": ("ERRADA", "Mapeamento + redação são atividades reflexivas, sem alteração da realidade."),
            "C": ("ERRADA", "Pesquisa estatística sem ação prática."),
            "D": ("CORRETA", "Oficina + acompanhamento comportamental = intervenção concreta e avaliada."),
        },
        "fundamentacao": [
            "HERNÁNDEZ, Fernando; VENTURA, Montserrat. A organização do currículo por projetos de trabalho. 5. ed. Porto Alegre: Artmed, 1998.",
            "DEWEY, John. Experiência e educação. São Paulo: Companhia Editora Nacional, 1976.",
            "ORGANIZAÇÃO DAS NAÇÕES UNIDAS. Agenda 2030 para o Desenvolvimento Sustentável – ODS 12: Consumo e Produção Responsáveis. Nova York: ONU, 2015.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "A Pedagogia de Projetos (Hernández & Ventura, 1998) organiza o currículo em torno de PROBLEMAS "
            "DE INVESTIGAÇÃO REAIS, com etapas: problematização → planejamento → execução → sistematização "
            "→ INTERVENÇÃO/SOCIALIZAÇÃO. Baseia-se em Dewey (aprender fazendo). O ODS 12 (Consumo e Produção "
            "Responsáveis) da Agenda 2030 orienta ações escolares de reaproveitamento, compostagem e combate "
            "ao desperdício alimentar. A BNCC incorpora tais temas como Temas Contemporâneos Transversais. "
            "A INTERVENÇÃO é o momento em que o projeto DEIXA a esfera reflexiva e impacta a realidade."
        ),
        "padroes_banca": (
            "O INEP tende a contrastar 'pesquisar/divulgar' vs. 'intervir/transformar'. Palavras-veneno: "
            "'evento científico', 'redação', 'análise' — indicam etapa reflexiva, não intervenção."
        ),
        "pegadinhas": [
            "Confundir pesquisa com intervenção.",
            "Marcar A por 'evento científico' soar sofisticado.",
            "Reduzir projeto a 'produto final escrito'.",
        ],
        "erros_comuns": (
            "Marcar A porque 'levantamento + evento' parece completo, sem perceber que falta AÇÃO NA "
            "REALIDADE."
        ),
        "dica_estrategica": (
            "Em item sobre Pedagogia de Projetos e ODS, prefira alternativas com VERBOS DE AÇÃO "
            "(oficina, mutirão, campanha, produção, acompanhamento) sobre verbos apenas COGNITIVOS "
            "(pesquisar, mapear, redigir)."
        ),
        "variacao": (
            "(Estilo INEP) O ODS 12 orienta escolas a promoverem:\n"
            "A) consumo desmedido; B) produção industrial em massa; C) padrões de consumo e produção "
            "sustentáveis, reduzindo desperdício; D) obsolescência programada.\n"
            "Gabarito: C."
        ),
        "minisimulado": [
            "Q1. A Pedagogia de Projetos, segundo Hernández & Ventura (1998), organiza o currículo por:\n"
            "A) disciplinas isoladas; B) problemas reais de investigação; C) exames padronizados; "
            "D) provas objetivas. → B",
            "Q2. A Agenda 2030 possui:\n"
            "A) 5 ODS; B) 10 ODS; C) 17 ODS; D) 30 ODS. → C",
            "Q3. Dewey associa aprendizagem a:\n"
            "A) memorização; B) experiência; C) autoritarismo; D) transmissão passiva. → B",
        ],
        "resumo": {
            "regra": "Intervenção educativa transforma a realidade; pesquisa e divulgação são etapas, não fim.",
            "excecoes": "Pesquisa pode ser fim em si em contextos científicos, mas o comando pediu 'intervenção'.",
            "palavra_chave": "Ação + acompanhamento + mudança comportamental.",
            "artigo": "ODS 12 (Agenda 2030); HERNÁNDEZ & VENTURA (1998).",
            "mnemonico": "PPPIS: Problematizar, Planejar, Produzir, Intervir, Socializar.",
        },
    },

    13: {
        "tema": "Coerência procedimento-avaliação (perspectiva crítica)",
        "subtema": "Consumo responsável / segurança alimentar",
        "habilidade_bncc": "BNCC – competências gerais 4, 7 e 10",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa (avaliação da aprendizagem)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item exige COERÊNCIA entre PROCEDIMENTO (perspectiva crítica, dialógica) e INSTRUMENTO DE "
            "AVALIAÇÃO (que exija argumentação e posicionamento). A única que satisfaz ambos é C: DEBATE + "
            "ARTIGO DE OPINIÃO."
        ),
        "como_banca_pensou": (
            "O INEP cobra a articulação metodologia-avaliação. As demais alternativas são incoerentes: "
            "roda de conversa avaliada por prova objetiva (A), banners avaliados por mapa mental (B), "
            "questionário fechado avaliado por composteira (D — troca de foco)."
        ),
        "resolucao": [
            "Fixe o critério: procedimento crítico → avaliação crítica coerente.",
            "Descarte A: 'roda de conversa' é crítica, mas 'prova objetiva' é instrumento fechado — incoerência.",
            "Descarte B: 'banners' e 'mapa mental' avaliam memorização, não posicionamento crítico.",
            "Descarte D: 'questionário + montagem por manual técnico' é tecnicista, sem crítica.",
            "Marque C: 'debate sobre insegurança alimentar + artigo de opinião' — ambos exigem argumentação crítica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Roda de conversa (dialógica) é incoerente com prova objetiva (memorização)."),
            "B": ("ERRADA", "Instrumentos de baixa densidade crítica; não avaliam posicionamento sobre consumo."),
            "C": ("CORRETA", "Debate (posicionamento crítico) + artigo de opinião (argumentação) formam par coerente na perspectiva crítica."),
            "D": ("ERRADA", "Manual técnico substitui reflexão crítica por reprodução procedural."),
        },
        "fundamentacao": [
            "LUCKESI, Cipriano C. Avaliação da aprendizagem escolar: estudos e proposições. 22. ed. São Paulo: Cortez, 2011.",
            "HOFFMANN, Jussara. Avaliação mediadora: uma prática em construção da pré-escola à universidade. 34. ed. Porto Alegre: Mediação, 2014.",
            "FREIRE, Paulo. Pedagogia da autonomia. São Paulo: Paz e Terra, 1996.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "A perspectiva crítica da AVALIAÇÃO (Luckesi, Hoffmann) recusa a redução a testes objetivos "
            "descontextualizados: a avaliação deve DIALOGAR com o procedimento e mensurar o desenvolvimento "
            "de competências como argumentação, posicionamento, análise de dados e produção autoral. Freire "
            "acrescenta a EXIGÊNCIA POLÍTICA da avaliação (não é neutra). O par DEBATE + ARTIGO DE OPINIÃO "
            "é canônico em avaliação crítica, pois exige mobilização de fontes, elaboração de tese, uso de "
            "argumentos e revisão coletiva — habilidades exigidas pela BNCC nas competências 4 (argumentação) "
            "e 7 (autoria e responsabilidade)."
        ),
        "padroes_banca": (
            "O INEP examina a COERÊNCIA metodologia-avaliação. Comum: apresentar procedimentos críticos "
            "com instrumentos tecnicistas e ver se o candidato percebe a incoerência."
        ),
        "pegadinhas": [
            "Achar que 'roda de conversa' + qualquer instrumento é crítico.",
            "Confundir mapa mental com síntese crítica.",
            "Reduzir avaliação a instrumentos fechados.",
        ],
        "erros_comuns": (
            "Marcar A por a roda de conversa soar boa, sem perceber a incoerência da prova objetiva. "
            "Ou D por 'composteira' parecer prático."
        ),
        "dica_estrategica": (
            "Regra: em item de coerência procedimento-avaliação crítica, o instrumento deve exigir "
            "POSICIONAMENTO/AUTORIA (debate, ensaio, artigo, seminário, projeto)."
        ),
        "variacao": (
            "(Estilo INEP) Na perspectiva crítica, o instrumento de avaliação mais coerente com uma "
            "sequência dialógica sobre direitos humanos é:\n"
            "A) prova objetiva com 20 questões; B) júri simulado seguido de manifesto coletivo; "
            "C) ditado de conceitos; D) preenchimento de lacunas.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Luckesi diferencia avaliação de:\n"
            "A) verificação; B) mediação; C) diagnóstico; D) autoavaliação. → A",
            "Q2. Jussara Hoffmann propõe a avaliação:\n"
            "A) classificatória; B) mediadora; C) somativa exclusiva; D) padronizada. → B",
            "Q3. O artigo de opinião como instrumento avalia principalmente:\n"
            "A) memorização; B) argumentação e posicionamento; C) caligrafia; D) ortografia isolada. → B",
        ],
        "resumo": {
            "regra": "Avaliação crítica pede instrumentos que exijam autoria e argumentação.",
            "excecoes": "Instrumentos objetivos podem compor a avaliação, desde que combinados com autorais.",
            "palavra_chave": "Debate + artigo de opinião.",
            "artigo": "LUCKESI (2011); HOFFMANN (2014); FREIRE (1996).",
            "mnemonico": "3 A's da avaliação crítica: Argumentar, Autorar, Agir.",
        },
    },

    14: {
        "tema": "Educação escolar indígena e memória social",
        "subtema": "Daniel Munduruku, oralidade e Leis 10.639/03 e 11.645/08",
        "habilidade_bncc": "BNCC – competências gerais 6 e 9; Lei 11.645/2008; DCN Educação Escolar Indígena",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a prática coerente com a proposta pedagógica descrita — memória social, oralidade "
            "e aprendizado pela experiência entre gerações. A resposta é a que MANTÉM a ORALIDADE e envolve "
            "a comunidade em REGISTRO AUDIOVISUAL: vídeos com técnicas ancestrais e contemporâneas."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão de que a EDUCAÇÃO INDÍGENA e a valorização da ORALIDADE não devem "
            "ser reduzidas a transcrições escritas ou pareceres de especialistas urbanos. O vídeo preserva "
            "voz, ritmo, gesto — dimensões centrais da oralidade."
        ),
        "resolucao": [
            "Reconheça a chave: memória social + oralidade + experiência intergeracional.",
            "Descarte A: 'estudo de caso sobre uso urbano para VALIDAR práticas contemporâneas' subverte a lógica ancestral.",
            "Descarte C: 'depoimentos de nutricionistas' desloca a autoridade da comunidade para especialistas urbanos.",
            "Descarte D: 'transcrever' as falas reduz a oralidade à escrita — perde-se a dimensão sonora e performática.",
            "Marque B: 'vídeos com a comunidade escolar registrando técnicas ancestrais e contemporâneas' — preserva a oralidade e articula gerações.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Inverte a lógica: as práticas ancestrais é que iluminam as contemporâneas, não o contrário."),
            "B": ("CORRETA", "Vídeo preserva oralidade, gesto e ritmo; envolve a comunidade e articula ancestralidade e presente (Munduruku)."),
            "C": ("ERRADA", "Substitui a autoridade da comunidade por especialistas urbanos — silenciamento epistêmico."),
            "D": ("ERRADA", "Reduz a oralidade à transcrição, empobrecendo a proposta."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 11.645, de 10 de março de 2008. Altera a LDB para incluir a temática 'História e Cultura Afro-Brasileira e Indígena'. DOU, Brasília, 11 mar. 2008.",
            "BRASIL. Conselho Nacional de Educação. Resolução CEB/CNE nº 5, de 22 de junho de 2012. DCN para a Educação Escolar Indígena na Educação Básica.",
            "MUNDURUKU, Daniel. Meu vô Apolinário: um mergulho no rio da (minha) memória. São Paulo: Studio Nobel, 2005.",
            "KRENAK, Ailton. Ideias para adiar o fim do mundo. São Paulo: Companhia das Letras, 2019.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "A EDUCAÇÃO ESCOLAR INDÍGENA (DCN – Res. CEB/CNE 5/2012) reconhece a especificidade, "
            "diferenciação, interculturalidade e bilinguismo/multilinguismo dos processos formativos dos "
            "povos originários. A Lei 11.645/2008 (que altera a 10.639/03) inclui História e Cultura "
            "Indígena obrigatoriamente no currículo. Autores como Daniel Munduruku, Ailton Krenak e "
            "Davi Kopenawa afirmam a ORALIDADE, a MEMÓRIA SOCIAL e a APRENDIZAGEM INTERGERACIONAL como "
            "pilares epistêmicos. Práticas escolares devem valorizar essa dimensão — registros audiovisuais, "
            "rodas de saberes, entrevistas com anciãos, produção compartilhada — em vez de traduzi-las "
            "unicamente para a escrita ou submetê-las a validação externa."
        ),
        "padroes_banca": (
            "O INEP tende a cobrar as Leis 10.639/03 e 11.645/08 em diálogo com práticas concretas. "
            "Alternativas erradas costumam silenciar a comunidade ou traduzir a oralidade em escrita."
        ),
        "pegadinhas": [
            "Achar que 'transcrever' é a única forma de registrar oralidade.",
            "Confundir 'estudo de caso' com prática ancestral.",
            "Substituir a comunidade por especialistas urbanos.",
        ],
        "erros_comuns": (
            "Marcar D por associar academia a transcrição escrita. É preciso lembrar que a oralidade tem "
            "valor epistêmico próprio."
        ),
        "dica_estrategica": (
            "Em item sobre educação indígena, priorize alternativas que PRESERVEM a ORALIDADE, a "
            "AUTORIDADE DA COMUNIDADE e a INTERGERACIONALIDADE. Rejeite as que reduzem a escrita ou a "
            "validação externa."
        ),
        "variacao": (
            "(Estilo INEP) A Lei 11.645/2008 tornou obrigatório o ensino de:\n"
            "A) apenas História Afro-Brasileira; B) História e Cultura Afro-Brasileira e Indígena; "
            "C) apenas História Indígena; D) somente História da Europa. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. As DCN da Educação Escolar Indígena têm como princípios:\n"
            "A) padronização nacional; B) especificidade, diferenciação, interculturalidade, bilinguismo; "
            "C) urbanização; D) homogeneização. → B",
            "Q2. Ailton Krenak, em 'Ideias para adiar o fim do mundo' (2019), critica:\n"
            "A) a interculturalidade; B) a ideia de humanidade abstrata desligada da terra; "
            "C) as línguas indígenas; D) as tradições orais. → B",
            "Q3. Registrar a oralidade em vídeo:\n"
            "A) empobrece a fonte; B) preserva voz, gesto e ritmo, dimensões constitutivas da oralidade; "
            "C) substitui a comunidade; D) é irrelevante. → B",
        ],
        "resumo": {
            "regra": "Educação indígena preserva oralidade + memória + autoridade da comunidade.",
            "excecoes": "A escrita pode complementar, nunca substituir, a oralidade.",
            "palavra_chave": "Oralidade + intergeracionalidade + comunidade.",
            "artigo": "Lei 11.645/2008; Res. CEB/CNE 5/2012.",
            "mnemonico": "3 O's: Ouvir, Observar, Organizar (memórias) com a comunidade.",
        },
    },

    15: {
        "tema": "Educação Ambiental crítica – limites do comportamentalismo",
        "subtema": "Crítica ao 'consumo' e aos 'brindes por resíduos'",
        "habilidade_bncc": "BNCC – competências gerais 7 e 10; TCT Educação Ambiental",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a leitura CRÍTICA das ações propostas. A dinâmica 'trocar resíduos por brindes' "
            "REPRODUZ a lógica consumista — que é a RAIZ do problema ambiental. A Educação Ambiental "
            "crítica (Loureiro, Layrargues) exige questionar a produção-consumo, não maquiar seus efeitos."
        ),
        "como_banca_pensou": (
            "O INEP quer testar se o candidato distingue Educação Ambiental CRÍTICA (Loureiro) da "
            "CONSERVACIONISTA/COMPORTAMENTAL. Alternativas B, C, D concedem virtudes às ações; A denuncia "
            "a NORMALIZAÇÃO DO CONSUMO — o problema estrutural."
        ),
        "resolucao": [
            "Reconheça a chave 'perspectiva CRÍTICA'.",
            "Descarte B: 'mudança comportamental' é a marca da EA CONSERVACIONISTA, não crítica.",
            "Descarte C: 'prejudicam catadores' é uma questão pontual, não a chave crítica pedida.",
            "Descarte D: 'deslocar resíduos para outra área' é o oposto da preservação; e não é o eixo crítico central.",
            "Marque A: 'normalizam o consumo e o acúmulo' — captura a crítica estrutural à raiz do problema.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Trocar resíduos por brindes REPRODUZ o ciclo de consumo, obscurecendo as causas estruturais dos resíduos."),
            "B": ("ERRADA", "Mudança comportamental é objetivo da EA conservacionista, criticada pela perspectiva crítica."),
            "C": ("ERRADA", "É uma possibilidade colateral, não a leitura crítica central pedida."),
            "D": ("ERRADA", "'Preservam ao deslocar' é contradição — deslocar não é preservar."),
        },
        "fundamentacao": [
            "LOUREIRO, Carlos Frederico B. Educação ambiental transformadora. Brasília: MMA, 2004.",
            "LAYRARGUES, Philippe P.; LIMA, Gustavo F. C. As macrotendências político-pedagógicas da Educação Ambiental brasileira. Ambiente & Sociedade, v. 17, n. 1, 2014.",
            "BRASIL. Lei nº 9.795, de 27 de abril de 1999. Política Nacional de Educação Ambiental. DOU, 28 abr. 1999.",
            "GUATTARI, Félix. As três ecologias. Campinas: Papirus, 2001.",
        ],
        "teoria": (
            "A Educação Ambiental brasileira tem, segundo Layrargues & Lima (2014), três macrotendências: "
            "(1) CONSERVACIONISTA (mudança de comportamento individual, coleta seletiva, natureza intocada); "
            "(2) PRAGMÁTICA (adaptações técnicas, mercado verde, ODS instrumental); (3) CRÍTICA "
            "(transformadora, popular, articula ecologia + economia + política + cultura, questiona o "
            "modelo de produção e consumo). Loureiro (2004) inscreve a EA na tradição da educação popular "
            "freireana. Guattari (2001), em 'As três ecologias', articula ecologia ambiental, social e "
            "mental. A crítica central: soluções individuais (trocar lixo por brindes) mascaram o problema "
            "ESTRUTURAL — a lógica produtiva capitalista que gera resíduos em escala."
        ),
        "padroes_banca": (
            "O INEP costuma opor abordagem CRÍTICA (transformação estrutural) vs. CONSERVACIONISTA "
            "(mudança comportamental individual). A alternativa correta em geral CRITICA a mera "
            "comportamentalização."
        ),
        "pegadinhas": [
            "Achar que 'mudança comportamental' é sinal de EA crítica — é da conservacionista.",
            "Confundir 'preservação' com deslocamento espacial dos resíduos.",
            "Reduzir a crítica ao aspecto do catador (é um recorte, não a chave central).",
        ],
        "erros_comuns": (
            "Marcar B por 'mudança comportamental' soar positiva. Na EA crítica, o comportamento individual "
            "é INSUFICIENTE frente ao problema estrutural."
        ),
        "dica_estrategica": (
            "Regra da EA crítica: sempre desconfie de soluções INDIVIDUAIS/COMPORTAMENTAIS. A EA crítica "
            "pergunta pelas CAUSAS ESTRUTURAIS (produção, consumo, poder)."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Layrargues & Lima (2014), a macrotendência CRÍTICA da EA se caracteriza por:\n"
            "A) mudança individual de comportamento;\n"
            "B) questionar o modelo produtivo e propor transformação socioambiental estrutural;\n"
            "C) foco em mercado verde;\n"
            "D) proteção de espaços intocados.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Política Nacional de EA foi instituída pela:\n"
            "A) Lei 9.795/1999; B) Lei 12.187/2009; C) Lei 6.938/1981; D) Decreto 4.281/2002. → A",
            "Q2. Guattari (2001) articula as ecologias:\n"
            "A) ambiental, social, mental; B) urbana, rural, marinha; C) atlântica, pacífica, ártica; "
            "D) econômica, política, jurídica. → A",
            "Q3. Trocar resíduos por brindes é, sob a EA crítica:\n"
            "A) transformador; B) suficiente; C) insuficiente e reforça a lógica consumista; D) obrigatório. → C",
        ],
        "resumo": {
            "regra": "EA crítica critica soluções individuais e busca transformação estrutural.",
            "excecoes": "Coleta seletiva ISOLADA é conservacionista; ligada a análise estrutural, pode integrar EA crítica.",
            "palavra_chave": "Estrutural + transformadora + Freire-Loureiro.",
            "artigo": "Lei 9.795/1999; LAYRARGUES & LIMA (2014); LOUREIRO (2004).",
            "mnemonico": "Não é 'BRINDE por lixo' — é POLÍTICA por transformação.",
        },
    },

    16: {
        "tema": "Educação escolar quilombola e Inventário Cultural",
        "subtema": "Colaboração escola–comunidade e PPP",
        "habilidade_bncc": "Res. CNE/CEB 8/2012 – DCN da Educação Escolar Quilombola; BNCC – competências 6 e 9",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão da Educação Escolar Quilombola como articulação entre escola e "
            "comunidade, valorizando saberes, rituais e memórias locais. A única alternativa que expressa "
            "essa perspectiva é C: reconhecer ritos significativos para a comunidade."
        ),
        "como_banca_pensou": (
            "A banca contrapõe uma abordagem NORMATIZANTE/URBANOCÊNTRICA (A, B, D) à perspectiva "
            "COMUNITÁRIA e afroreferenciada (C). As DCN da Educação Escolar Quilombola exigem partir dos "
            "saberes da comunidade, e não impor conteúdos externos."
        ),
        "resolucao": [
            "Reconheça a chave: escola quilombola + colaboração com a comunidade + Inventário Cultural.",
            "Descarte A: 'normatizar saberes' inverte a relação — impõe forma escolar sobre saber comunitário.",
            "Descarte B: 'festividades contemporâneas para renovar princípios' desconsidera a festa tradicional citada.",
            "Descarte D: 'produtos industrializados' rompe com a lógica comunitária.",
            "Marque C: reconhecer ritos significativos da comunidade durante a organização da festa = intervenção afinada com as DCN quilombolas.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Normatizar saberes escolariza indevidamente o conhecimento comunitário."),
            "B": ("ERRADA", "Substitui a festa tradicional por 'festividades contemporâneas'."),
            "C": ("CORRETA", "Reconhecer ritos da comunidade durante a festa articula PPP, currículo e territorialidade quilombola."),
            "D": ("ERRADA", "Produtos industrializados descaracterizam a economia e a cultura comunitária."),
        },
        "fundamentacao": [
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CEB nº 8, de 20 de novembro de 2012. Institui as DCN para a Educação Escolar Quilombola na Educação Básica.",
            "BRASIL. Decreto nº 6.040, de 7 de fevereiro de 2007. Política Nacional de Desenvolvimento Sustentável dos Povos e Comunidades Tradicionais.",
            "GOMES, Nilma Lino. O movimento negro educador. Petrópolis: Vozes, 2017.",
            "IPHAN. Inventário Nacional de Referências Culturais – INRC: manual de aplicação. Brasília: IPHAN, 2000.",
        ],
        "teoria": (
            "A EDUCAÇÃO ESCOLAR QUILOMBOLA (Res. CNE/CEB 8/2012) reconhece a especificidade histórica, "
            "cultural, social e territorial das comunidades remanescentes de quilombos. Princípios: "
            "PARTICIPAÇÃO COMUNITÁRIA na gestão e no currículo, RESPEITO AOS SABERES tradicionais, "
            "ARTICULAÇÃO com o PPP e com o Inventário Nacional de Referências Culturais (INRC – IPHAN, 2000). "
            "Gomes (2017) discute o movimento negro como educador e a EEQ como resposta ao racismo estrutural. "
            "A festa do padroeiro é oportunidade pedagógica privilegiada — desde que seus rituais sejam RECONHECIDOS "
            "e não substituídos por formatos escolares urbanocêntricos."
        ),
        "padroes_banca": (
            "O INEP contrapõe abordagem urbanocêntrica/normatizante vs. abordagem comunitária/afroreferenciada."
        ),
        "pegadinhas": [
            "Confundir 'normatizar' com 'sistematizar'.",
            "Achar que 'renovar princípios' é sinal de modernidade.",
            "Marcar D por associar 'feira' a evento pedagógico.",
        ],
        "erros_comuns": (
            "Marcar B por soar aggiornado. Na EEQ, o eixo é reconhecer o já existente, não introduzir 'novo'."
        ),
        "dica_estrategica": (
            "Em item sobre EEQ, prefira alternativas com RECONHECIMENTO + PARTICIPAÇÃO + TERRITÓRIO."
        ),
        "variacao": (
            "(Estilo INEP) A Resolução CNE/CEB nº 8/2012 institui:\n"
            "A) BNCC; B) DCN da Educação Escolar Quilombola; C) Lei do Ensino Religioso; "
            "D) DCN Indígena. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O INRC (IPHAN, 2000) é usado para:\n"
            "A) construir prédios; B) identificar e registrar referências culturais; "
            "C) planejar cidades; D) tributar comércio. → B",
            "Q2. A EEQ tem como princípio:\n"
            "A) homogeneização nacional; B) participação da comunidade quilombola; "
            "C) currículo urbano; D) redução da autonomia. → B",
            "Q3. 'O movimento negro educador' é obra de:\n"
            "A) Freire; B) Gomes; C) Saviani; D) Piaget. → B",
        ],
        "resumo": {
            "regra": "EEQ = escola articulada à comunidade quilombola; reconhecer, não normatizar.",
            "excecoes": "Sistematizações são bem-vindas, desde que não substituam a fala da comunidade.",
            "palavra_chave": "Reconhecimento + território + PPP.",
            "artigo": "Res. CNE/CEB 8/2012; Decreto 6.040/2007.",
            "mnemonico": "3 R's da EEQ: Reconhecer, Ritualizar (juntos), Registrar.",
        },
    },

    17: {
        "tema": "Currículo moldado (Gimeno Sacristán)",
        "subtema": "Currículo como construção social + professor mediador",
        "habilidade_bncc": "BNCC; DCN – Formação Docente; Teoria do Currículo",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a definição de CURRÍCULO MOLDADO em Gimeno Sacristán (2000). O currículo moldado "
            "articula-se ao nível prático, situado — construção social mediada pelo professor. Alternativas "
            "erradas atribuem-lhe traços neutros (B), executores (C) ou reduzem a produto (D)."
        ),
        "como_banca_pensou": (
            "A pegadinha é o 'currículo apresentado' (materiais didáticos) e 'currículo prescrito' (normativo) "
            "serem confundidos com o moldado. O MOLDADO é interpretação e apropriação PELO(A) PROFESSOR(A) — "
            "logo, exige agência docente."
        ),
        "resolucao": [
            "Fixe Gimeno Sacristán (2000): prescrito → apresentado → moldado → em ação → avaliado.",
            "Reconheça: MOLDADO é resultado da agência do docente na interpretação/adaptação.",
            "Descarte B: 'neutro' + 'condutor de referenciais' nega a agência do docente.",
            "Descarte C: 'executor do apresentado' é o inverso do moldado.",
            "Descarte D: 'produto' esvazia a natureza processual do currículo.",
            "Marque A: 'construção social' + 'mediador' = essência do currículo moldado.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Currículo moldado = construção social + professor mediador (Gimeno Sacristán, 2000)."),
            "B": ("ERRADA", "Currículo não é neutro; docente não é apenas condutor."),
            "C": ("ERRADA", "Ser 'executor' é o modelo tecnicista, criticado pelo autor."),
            "D": ("ERRADA", "Reduzir a produto ignora a dimensão processual e situada."),
        },
        "fundamentacao": [
            "GIMENO SACRISTÁN, José. O currículo: uma reflexão sobre a prática. 3. ed. Porto Alegre: Artmed, 2000.",
            "APPLE, Michael W. Ideologia e currículo. 3. ed. Porto Alegre: Artmed, 2006.",
            "SILVA, Tomaz Tadeu. Documentos de identidade: uma introdução às teorias de currículo. 3. ed. Belo Horizonte: Autêntica, 2010.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "GIMENO SACRISTÁN (2000) propõe cinco níveis de objetivação do currículo: (1) PRESCRITO "
            "(normativo — BNCC, DCNs); (2) APRESENTADO (materiais didáticos, livros); (3) MOLDADO (pelo(a) "
            "professor(a) em sua interpretação e planejamento); (4) EM AÇÃO (na sala de aula); (5) AVALIADO. "
            "O MOLDADO é o nível-chave da agência docente: articula o prescrito, o apresentado e o contexto "
            "escolar em decisões didáticas. Apple (2006) reforça que o currículo é campo de disputa "
            "ideológica; Silva (2010) sistematiza teorias tradicional, crítica e pós-crítica."
        ),
        "padroes_banca": (
            "O INEP costuma cobrar Gimeno em item que oferece definições diferentes por alternativa. "
            "A resposta correta sempre reafirma a AGÊNCIA docente."
        ),
        "pegadinhas": [
            "Confundir moldado com apresentado (livro didático).",
            "Confundir moldado com prescrito (BNCC).",
            "Achar que docente é 'executor'.",
        ],
        "erros_comuns": (
            "Marcam C por parecer que 'seguir a BNCC' é positivo. É preciso lembrar que MOLDAR ≠ EXECUTAR."
        ),
        "dica_estrategica": (
            "Currículo moldado = docente MEDIA. Alternativa correta terá MEDIADOR, CONSTRUTOR, INTÉRPRETE."
        ),
        "variacao": (
            "(Estilo INEP) Em Gimeno Sacristán (2000), o currículo EM AÇÃO refere-se ao:\n"
            "A) documento normativo; B) livro didático; C) planejamento do docente; "
            "D) desenrolar concreto das aulas em sala.\n"
            "Gabarito: D."
        ),
        "minisimulado": [
            "Q1. Apple (2006) inscreve o currículo no debate:\n"
            "A) tecnicista puro; B) ideológico e político; C) esotérico; D) individual apenas. → B",
            "Q2. Tomaz Tadeu Silva sistematiza teorias:\n"
            "A) tradicional, crítica e pós-crítica; B) apenas positivistas; C) só freireana; D) só pós-modernas. → A",
            "Q3. Os 5 níveis de Gimeno Sacristán são:\n"
            "A) prescrito, apresentado, moldado, em ação e avaliado; B) livre, controlado, misto, oral, escrito; "
            "C) público, privado, terceirizado, digital, remoto; D) infantil, EF, EM, EJA, EPT. → A",
        ],
        "resumo": {
            "regra": "Currículo moldado = agência docente, construção social.",
            "excecoes": "Executar prescrições sem interpretar contradiz o conceito.",
            "palavra_chave": "Construção social + mediação docente.",
            "artigo": "GIMENO SACRISTÁN (2000).",
            "mnemonico": "PAMAA: Prescrito → Apresentado → Moldado → Ação → Avaliado.",
        },
    },

    18: {
        "tema": "Teoria crítica de currículo (Tomaz Tadeu da Silva)",
        "subtema": "Currículo e reprodução das desigualdades",
        "habilidade_bncc": "Teoria do currículo; DCN – Formação Docente",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a estratégia pedagógica alinhada à TEORIA CRÍTICA de currículo — foco em RELAÇÕES DE "
            "PODER, DESIGUALDADES e IDEOLOGIA. Apenas A (pesquisa de campo sobre violência no entorno) "
            "materializa essa perspectiva pela investigação da realidade concreta."
        ),
        "como_banca_pensou": (
            "A banca contrasta a teoria crítica (Apple, Giroux, Freire) com estratégias reprodutivas (C: "
            "leitura + exercícios) ou apenas informativas (B, D). A palavra-chave é ENFRENTAMENTO — "
            "reflexo direto da teoria crítica."
        ),
        "resolucao": [
            "Reconheça: teoria crítica = investigação da realidade + análise de poder + enfrentamento.",
            "Descarte C: 'leitura + lista de exercícios' remete à teoria tradicional.",
            "Descarte B: 'resumo + seminário' é raso; não caracteriza pesquisa crítica.",
            "Descarte D: 'exibição + palestras sobre bullying' é informativo, não crítico-transformador.",
            "Marque A: pesquisa de campo + discussão sobre violência = investigação da realidade + práxis.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Pesquisa de campo sobre violência no entorno mobiliza a realidade concreta e o enfrentamento — cerne da teoria crítica."),
            "B": ("ERRADA", "Resumo + seminário não constituem, por si, análise crítica."),
            "C": ("ERRADA", "Estratégia típica da teoria tradicional (transmissão + verificação)."),
            "D": ("ERRADA", "Documentário + palestra são informativos; não têm a dimensão investigativa."),
        },
        "fundamentacao": [
            "SILVA, Tomaz Tadeu. Documentos de identidade: uma introdução às teorias de currículo. 3. ed. Belo Horizonte: Autêntica, 2010.",
            "APPLE, Michael W. Ideologia e currículo. Porto Alegre: Artmed, 2006.",
            "GIROUX, Henry. Teoria crítica e resistência em educação. Petrópolis: Vozes, 1986.",
            "FREIRE, Paulo. Pedagogia do oprimido. Rio de Janeiro: Paz e Terra, 1987.",
        ],
        "teoria": (
            "As TEORIAS DO CURRÍCULO (Silva, 2010) se organizam em três grandes vertentes: (1) TRADICIONAIS "
            "(Bobbitt, Tyler): eficiência, neutralidade, mensuração; (2) CRÍTICAS (Apple, Giroux, Freire): "
            "poder, ideologia, desigualdade, práxis emancipatória; (3) PÓS-CRÍTICAS (Silva, Corazza): "
            "discurso, cultura, identidades, subjetividades, diferenças. A teoria crítica valoriza a INVESTIGAÇÃO "
            "DA REALIDADE, o desvelamento das relações de poder e a PRÁXIS TRANSFORMADORA — daí a pesquisa "
            "de campo sobre violência ser exemplo típico."
        ),
        "padroes_banca": (
            "O INEP costuma pedir pareamento entre teoria e prática pedagógica. Cuidado para não confundir "
            "'crítica' com 'pós-crítica'."
        ),
        "pegadinhas": [
            "Confundir crítica com pós-crítica.",
            "Achar que 'documentário sobre bullying' é crítica.",
            "Marcar seminário + resumo por parecer acadêmico.",
        ],
        "erros_comuns": (
            "Marcam D por associar 'palestra sobre bullying' com pauta social. A teoria crítica exige "
            "INVESTIGAÇÃO PRÓPRIA + PRÁXIS."
        ),
        "dica_estrategica": (
            "Crítica → pesquisa + poder + realidade + práxis. Pós-crítica → identidades + discurso + diferença."
        ),
        "variacao": (
            "(Estilo INEP) A perspectiva PÓS-CRÍTICA do currículo (Silva, 2010) enfatiza:\n"
            "A) eficiência e neutralidade;\n"
            "B) construção discursiva das identidades, das subjetividades e das diferenças;\n"
            "C) apenas desigualdade econômica;\n"
            "D) currículo como lista de conteúdos.\n"
            "Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A teoria tradicional do currículo (Bobbitt, Tyler) enfatiza:\n"
            "A) eficiência e mensuração; B) identidades; C) poder; D) diferença. → A",
            "Q2. Giroux (1986) tematiza:\n"
            "A) teoria tradicional; B) teoria crítica e resistência; C) filosofia analítica; "
            "D) pós-estruturalismo puro. → B",
            "Q3. Apple (2006) enfatiza que o currículo é:\n"
            "A) técnico; B) neutro; C) ideológico e político; D) universal. → C",
        ],
        "resumo": {
            "regra": "Crítica = poder, ideologia, práxis; investigação da realidade.",
            "excecoes": "Não confundir com pós-crítica (discurso, identidade).",
            "palavra_chave": "Pesquisa de campo + enfrentamento.",
            "artigo": "SILVA (2010); APPLE (2006); GIROUX (1986).",
            "mnemonico": "3 P's críticos: Poder, Práxis, Pesquisa da realidade.",
        },
    },

    19: {
        "tema": "Diálogo entre conhecimentos científicos e tradicionais",
        "subtema": "Ailton Krenak e a decolonialidade",
        "habilidade_bncc": "BNCC – competência geral 1 (conhecimentos historicamente construídos)",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Krenak critica narrativas GLOBALIZANTES/SUPERFICIAIS que apagam os conhecimentos tradicionais. "
            "A leitura pedagógica coerente é INTEGRAR conhecimentos científicos e tradicionais em pé de "
            "igualdade — sem eurocentrismo nem subordinação."
        ),
        "como_banca_pensou": (
            "As alternativas testam a compreensão de que INTEGRAR ≠ subordinar. A e B mantêm um saber como "
            "hegemônico sobre o outro. D subordina o tradicional. Só C afirma INTEGRAÇÃO simétrica."
        ),
        "resolucao": [
            "Fixe a chave krenakiana: pluralidade epistêmica, sem hierarquia.",
            "Descarte A: 'eurocêntrica dos tradicionais' subordina o tradicional ao científico europeu.",
            "Descarte B: 'hegemônica dos científicos' — mesma lógica invertida.",
            "Descarte D: 'subordinados' — explícita hierarquização.",
            "Marque C: 'integrados' = diálogo simétrico.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Eurocentrismo perpetua a hierarquia denunciada por Krenak."),
            "B": ("ERRADA", "Hegemonia científica sobre saberes tradicionais mantém a assimetria."),
            "C": ("CORRETA", "Integração simétrica entre científicos e tradicionais reflete a proposta krenakiana."),
            "D": ("ERRADA", "Subordinação = antítese da pluralidade epistêmica."),
        },
        "fundamentacao": [
            "KRENAK, Ailton. Ideias para adiar o fim do mundo. São Paulo: Companhia das Letras, 2019.",
            "KRENAK, Ailton. A vida não é útil. São Paulo: Companhia das Letras, 2020.",
            "SANTOS, Boaventura de Sousa. Descolonizar o saber, reinventar o poder. Lisboa: Almedina, 2010.",
            "BRASIL. Lei nº 11.645, de 10 de março de 2008. Altera a LDB. DOU, 11 mar. 2008.",
        ],
        "teoria": (
            "Krenak, um dos mais importantes pensadores indígenas contemporâneos, denuncia a "
            "universalização de uma narrativa moderno-colonial e defende a pluralidade de mundos. Dialoga "
            "com Boaventura de Sousa Santos (ecologia de saberes), com o pensamento decolonial (Quijano, "
            "Mignolo) e com Davi Kopenawa. Na educação, isso implica INTEGRAR os conhecimentos científicos "
            "aos tradicionais SEM subordinar nenhum ao outro — um princípio pedagógico decolonial."
        ),
        "padroes_banca": (
            "O INEP tende a colocar 'INTEGRAR' na alternativa correta e usar 'subordinar' ou 'eurocêntrico' "
            "como palavras-veneno."
        ),
        "pegadinhas": [
            "Achar que 'integrar' = 'traduzir para o científico'.",
            "Marcar A por parecer que 'científico primeiro' é 'objetivo'.",
            "Confundir hegemonia com hierarquia epistêmica legítima.",
        ],
        "erros_comuns": (
            "Marcam A por hábito acadêmico eurocêntrico. Krenak REJEITA essa hierarquia."
        ),
        "dica_estrategica": (
            "Em item sobre Krenak/decolonialidade, escolha a alternativa que NÃO hierarquiza saberes."
        ),
        "variacao": (
            "(Estilo INEP) Boaventura de Sousa Santos (2010) propõe a:\n"
            "A) hierarquia epistêmica ocidental;\n"
            "B) 'ecologia de saberes' — diálogo horizontal entre saberes;\n"
            "C) supremacia da ciência positivista;\n"
            "D) fim das universidades. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Davi Kopenawa é autor de:\n"
            "A) A queda do céu; B) O alienista; C) Vidas secas; D) Casa-grande & senzala. → A",
            "Q2. Krenak, em 'A vida não é útil' (2020), critica:\n"
            "A) a arte indígena; B) a lógica utilitarista/produtivista; C) a educação básica; "
            "D) a filosofia grega. → B",
            "Q3. O pensamento decolonial (Quijano, Mignolo) rejeita:\n"
            "A) diálogo intercultural; B) colonialidade do saber e do poder; "
            "C) povos originários; D) pluralismo epistêmico. → B",
        ],
        "resumo": {
            "regra": "Integrar saberes = diálogo horizontal, sem subordinação.",
            "excecoes": "Cada saber tem seus critérios internos de validação, respeitados no diálogo.",
            "palavra_chave": "Integração + horizontalidade + decolonialidade.",
            "artigo": "KRENAK (2019); SANTOS (2010).",
            "mnemonico": "S.I.M.: Saberes Integrados, MundosPlurais.",
        },
    },

    20: {
        "tema": "Cinemas africanos e Lei 10.639/03",
        "subtema": "Critérios de seleção antirracistas",
        "habilidade_bncc": "Lei 10.639/03; BNCC; DCN Relações Étnico-Raciais",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra critérios de seleção fílmica que ROMPAM com estereótipos coloniais. A correta é C: "
            "filmes que reconheçam variadas formas de expressão dos povos africanos, subjetividades e "
            "questões sociais."
        ),
        "como_banca_pensou": (
            "A banca oferece três estereótipos coloniais (A – natureza selvagem; B – familiaridade "
            "eurocêntrica; D – narrativa da colonização com viés urbanocêntrico) e uma alternativa "
            "afirmativa (C)."
        ),
        "resolucao": [
            "Reconheça a chave da Lei 10.639/03: superar estereótipos, valorizar culturas africanas em sua diversidade.",
            "Descarte A: 'espaços físicos e vida animal selvagem' = imaginário colonial safári.",
            "Descarte B: 'familiaridade com narrativas europeias e americanas' anula a especificidade africana.",
            "Descarte D: 'estereótipos da colonização' é o oposto do que se deve mostrar.",
            "Marque C: variadas expressões, subjetividades e questões sociais dos povos africanos.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Reduz a África à natureza selvagem — estereótipo colonial."),
            "B": ("ERRADA", "Impõe padrão eurocêntrico/estadunidense à leitura das produções africanas."),
            "C": ("CORRETA", "Reconhece diversidade cultural, subjetividade e agência dos povos africanos."),
            "D": ("ERRADA", "Reforçar estereótipos contradiz a Lei 10.639/03."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 10.639, de 9 de janeiro de 2003. Altera a LDB, incluindo História e Cultura Afro-Brasileira. DOU, Brasília, 10 jan. 2003.",
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CP nº 1, de 17 de junho de 2004. Institui as DCN para a Educação das Relações Étnico-Raciais e para o Ensino de História e Cultura Afro-Brasileira e Africana.",
            "MUNANGA, Kabengele. Superando o racismo na escola. 2. ed. Brasília: MEC/SECAD, 2005.",
            "GOMES, Nilma Lino. O movimento negro educador. Petrópolis: Vozes, 2017.",
            "DIAWARA, Manthia. African Film: new forms of aesthetics and politics. Munich: Prestel, 2010.",
        ],
        "teoria": (
            "O cinema africano pós-1960 (Sembène, Cissé, Mambéty, Diop-Mambéty, Sissako) constrói uma "
            "estética e uma política de auto-representação, contrapondo-se ao olhar colonial. Autores como "
            "Manthia Diawara analisam essa produção como afirmação estética-política dos povos africanos. "
            "A Lei 10.639/03 e as DCNs para as relações étnico-raciais (Res. CNE/CP 1/2004) exigem "
            "AFIRMAR essa diversidade cultural, subjetividade e questões sociais — SEM naturalização, "
            "exotização ou familiarização eurocêntrica."
        ),
        "padroes_banca": (
            "O INEP contrapõe estereótipos coloniais a critérios afirmativos, testando a apropriação da Lei 10.639/03."
        ),
        "pegadinhas": [
            "Naturalizar a África como natureza selvagem.",
            "Buscar 'familiaridade' com narrativas ocidentais.",
            "Reforçar estereótipos como pretexto de crítica.",
        ],
        "erros_comuns": (
            "Marcam A por hábito de associar África a fauna."
        ),
        "dica_estrategica": (
            "Em item sobre África e Lei 10.639/03, prefira alternativas que digam DIVERSIDADE, SUBJETIVIDADE, "
            "AGÊNCIA, AUTO-REPRESENTAÇÃO."
        ),
        "variacao": (
            "(Estilo INEP) A Res. CNE/CP nº 1/2004 institui:\n"
            "A) BNCC; B) DCN das Relações Étnico-Raciais e ensino de História e Cultura Afro-Brasileira e Africana; "
            "C) DCN da EJA; D) DCN do Ensino Religioso. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Ousmane Sembène é conhecido como:\n"
            "A) fundador do cinema africano; B) filósofo alemão; C) romancista europeu; "
            "D) sociólogo estadunidense. → A",
            "Q2. Munanga (2005) organiza a obra:\n"
            "A) A Casa-grande; B) Superando o racismo na escola; C) O código do trabalho; "
            "D) Manual da BNCC. → B",
            "Q3. A Lei 10.639/03 obriga o ensino de:\n"
            "A) História e cultura afro-brasileira e africana; B) Filosofia; C) Sociologia; "
            "D) Empreendedorismo. → A",
        ],
        "resumo": {
            "regra": "Cinemas africanos = auto-representação + diversidade + agência.",
            "excecoes": "Estereótipos coloniais podem ser objeto de DESCONSTRUÇÃO, não de reprodução.",
            "palavra_chave": "Diversidade + subjetividade + questões sociais.",
            "artigo": "Lei 10.639/03; Res. CNE/CP 1/2004.",
            "mnemonico": "AAA africano: Auto-representar, Afirmar, Analisar.",
        },
    },

    21: {
        "tema": "Gestão democrática e enfrentamento da precariedade",
        "subtema": "Competências da escola vs. do poder público",
        "habilidade_bncc": "LDB 9.394/96 (art. 14 – gestão democrática); Lei 13.005/14 (PNE); PNEDH",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Prática (competências)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a distinção entre o que COMPETE à escola (articular-se com o poder público) e o "
            "que compete às autoridades executivas (obras). Alternativas B (instalar redes) e A (projeto "
            "de solução) transferem à escola atribuições do Executivo; D (despoluir rio) desvia o foco."
        ),
        "como_banca_pensou": (
            "O INEP quer que o(a) professor(a) reconheça que INFRAESTRUTURA ESCOLAR é dever do poder público "
            "(LDB, PNE, ECA). À gestão escolar cabe DENUNCIAR, ARTICULAR-SE e MOBILIZAR — não substituir o Estado."
        ),
        "resolucao": [
            "Reconheça a chave: competência da ESCOLA no enfrentamento estrutural.",
            "Descarte A: 'elaborar projeto para solucionar' extrapola a competência escolar.",
            "Descarte B: 'instalar redes de água e esgoto' é obra pública, não escolar.",
            "Descarte D: 'despoluir rio' desloca o foco do problema real.",
            "Marque C: articulação com autoridades competentes = ação política e institucional legítima da gestão escolar.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Escola pode diagnosticar, mas 'solucionar rede de água' foge da sua competência."),
            "B": ("ERRADA", "Instalação de infraestrutura é atribuição do poder público executivo."),
            "C": ("CORRETA", "Gestão escolar articulada com autoridades competentes = enfrentamento institucional coerente com a gestão democrática."),
            "D": ("ERRADA", "Desloca o problema; não enfrenta a precariedade denunciada."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 9.394, de 20 de dezembro de 1996. LDB. DOU, 23 dez. 1996.",
            "BRASIL. Lei nº 13.005, de 25 de junho de 2014. Aprova o Plano Nacional de Educação (2014-2024). DOU, 26 jun. 2014.",
            "BRASIL. Lei nº 8.069, de 13 de julho de 1990. ECA. DOU, 16 jul. 1990.",
            "BRASIL. Comitê Nacional de Educação em Direitos Humanos. Plano Nacional de Educação em Direitos Humanos. Brasília: SEDH/MEC, 2007.",
            "PARO, Vitor H. Gestão democrática da escola pública. 4. ed. São Paulo: Cortez, 2016.",
        ],
        "teoria": (
            "A GESTÃO DEMOCRÁTICA da escola pública (CF/88 art. 206, VI; LDB art. 14) implica participação "
            "da comunidade escolar na definição do PPP e na articulação com o poder público. A "
            "infraestrutura escolar é DIREITO garantido por LDB, PNE e ECA — e é ATRIBUIÇÃO do Executivo "
            "provê-la. À escola cabe MOBILIZAR, DENUNCIAR e REIVINDICAR junto às autoridades competentes "
            "(Executivo, Conselho Municipal/Estadual de Educação, Ministério Público, Defensoria). Paro (2016) "
            "discute a gestão democrática como práxis coletiva."
        ),
        "padroes_banca": (
            "O INEP costuma testar a distinção entre COMPETÊNCIA ESCOLAR e COMPETÊNCIA DO PODER PÚBLICO."
        ),
        "pegadinhas": [
            "Achar que 'instalar redes' é ativismo escolar louvável.",
            "Confundir 'diagnóstico' com 'solução'.",
            "Perder o foco no problema apontado (infraestrutura, não rio).",
        ],
        "erros_comuns": (
            "Marcar A por parecer que 'projeto' resolve — mas a escola não pode assumir obras públicas."
        ),
        "dica_estrategica": (
            "Em item de gestão, prefira alternativas que ARTICULEM com o poder público, sem substituí-lo."
        ),
        "variacao": (
            "(Estilo INEP) A gestão democrática, conforme o art. 14 da LDB, prevê:\n"
            "A) escolha do diretor por indicação política;\n"
            "B) participação da comunidade escolar na elaboração do PPP e nos conselhos escolares;\n"
            "C) centralização das decisões na Secretaria;\n"
            "D) autonomia irrestrita da direção. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O direito à educação inclui condições dignas de infraestrutura? "
            "A) Não; B) Sim, previsto em LDB, ECA e PNE; C) Só em escolas privadas; D) Só em capitais. → B",
            "Q2. Cabe à gestão escolar, diante de precariedade estrutural:\n"
            "A) construir prédios; B) articular-se com autoridades competentes; C) transferir alunos; "
            "D) desativar a escola. → B",
            "Q3. Paro (2016) defende:\n"
            "A) autoritarismo diretivo; B) gestão democrática como práxis coletiva; "
            "C) privatização; D) neutralidade absoluta. → B",
        ],
        "resumo": {
            "regra": "Escola articula-se com o poder público; não substitui o Estado em obras.",
            "excecoes": "Ações emergenciais mínimas podem ser articuladas (ex.: filtros, materiais), sempre com poder público.",
            "palavra_chave": "Articulação + gestão democrática.",
            "artigo": "CF/88 art. 206; LDB art. 14; PNE (Lei 13.005/14).",
            "mnemonico": "AAA da gestão: Articular, Advogar (pela escola), Acompanhar.",
        },
    },

    22: {
        "tema": "EJA – abordagem sistêmica (Almeida, 2016)",
        "subtema": "Letramentos + experiências + intergeracionalidade",
        "habilidade_bncc": "DCN EJA (Res. CNE/CEB 1/2000; Parecer 11/2000)",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão de que a abordagem SISTÊMICA da EJA a integra ao direito à educação "
            "e à diversidade cultural. A alternativa C — projetos de letramento que integram experiências "
            "de vida, trabalho, identidades culturais e vivências intergeracionais — é a única que "
            "materializa essa concepção."
        ),
        "como_banca_pensou": (
            "As demais alternativas expressam concepções RESTRITAS/SUPLETIVAS da EJA: profissionalização "
            "isolada (A), apenas certificação (B), reprodução do ensino regular (D)."
        ),
        "resolucao": [
            "Fixe a chave: SISTÊMICA = direito, cultura, política + integração com vida.",
            "Descarte A: reduz EJA a preparação para o mercado.",
            "Descarte B: reduz EJA à concessão de diplomas.",
            "Descarte D: nivela EJA ao ensino regular, apagando sua especificidade.",
            "Marque C: projetos de letramento + experiências + trabalho + cultura + intergeracional = abordagem sistêmica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Reduz a EJA à profissionalização — concepção estreita, criticada por Almeida (2016)."),
            "B": ("ERRADA", "Focar apenas em diploma é lógica supletiva, não sistêmica."),
            "C": ("CORRETA", "Integra vida, trabalho, cultura e gerações — expressão da abordagem sistêmica."),
            "D": ("ERRADA", "Reproduzir o ensino regular apaga a especificidade da EJA."),
        },
        "fundamentacao": [
            "BRASIL. Conselho Nacional de Educação. Parecer CNE/CEB nº 11/2000. Diretrizes Curriculares Nacionais para a EJA (Relator: Jamil Cury).",
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CEB nº 1, de 5 de julho de 2000. Institui as DCN da EJA.",
            "FREIRE, Paulo. Pedagogia do oprimido. Rio de Janeiro: Paz e Terra, 1987.",
            "SOARES, Magda. Letramento: um tema em três gêneros. 3. ed. Belo Horizonte: Autêntica, 2009.",
            "ARROYO, Miguel G. Educação de jovens-adultos: um campo de direitos e de responsabilidade pública. In: SOARES, L. et al. (org.). Diálogos na Educação de Jovens e Adultos. Belo Horizonte: Autêntica, 2005.",
        ],
        "teoria": (
            "A EJA é modalidade da Educação Básica com funções REPARADORA, EQUALIZADORA e QUALIFICADORA "
            "(Parecer CNE/CEB 11/2000). A ABORDAGEM SISTÊMICA (Almeida, 2016) a insere na história do direito "
            "à educação e supera a visão SUPLETIVA/MARGINAL. Freire (1987) e Arroyo (2005) reafirmam a EJA "
            "como direito subjetivo e como espaço de leitura de mundo. Magda Soares (2009) fornece o conceito "
            "de LETRAMENTO — práticas sociais de leitura e escrita ancoradas em contextos concretos. Um "
            "PROJETO DE LETRAMENTO na EJA integra as experiências de vida ao currículo, articulando "
            "trabalho, identidades e gerações."
        ),
        "padroes_banca": (
            "O INEP tende a contrastar visão SISTÊMICA (crítica, plural, direito) vs. SUPLETIVA "
            "(compensatória, certificatória, mercadológica)."
        ),
        "pegadinhas": [
            "Marcar A por associar EJA a mercado de trabalho.",
            "Marcar D por parecer 'garantir igualdade'.",
            "Confundir sistêmica com padronizada.",
        ],
        "erros_comuns": (
            "Escolher B por ser 'objetiva' — mas a lógica de diploma pertence à visão restrita."
        ),
        "dica_estrategica": (
            "Sistêmica na EJA = LETRAMENTOS + VIDA + INTERGERACIONALIDADE. Rejeite reducionismos."
        ),
        "variacao": (
            "(Estilo INEP) O Parecer CNE/CEB nº 11/2000, de Jamil Cury, atribui à EJA as funções:\n"
            "A) apenas supletiva; B) reparadora, equalizadora e qualificadora; "
            "C) só profissionalizante; D) só certificatória. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Segundo Magda Soares (2009), letramento é:\n"
            "A) alfabetização técnica; B) práticas sociais de leitura e escrita em contextos; "
            "C) datilografia; D) grafologia. → B",
            "Q2. Arroyo (2005) situa a EJA no campo:\n"
            "A) da caridade; B) do direito e da responsabilidade pública; "
            "C) do mercado; D) do supletivo. → B",
            "Q3. Cury (Parecer 11/2000) associa a EJA a:\n"
            "A) mera aceleração; B) dívida social e direito humano; "
            "C) treinamento em massa; D) educação privada. → B",
        ],
        "resumo": {
            "regra": "EJA sistêmica = direito humano + letramentos + vida + diversidade.",
            "excecoes": "Certificação e profissionalização compõem a EJA sem esgotá-la.",
            "palavra_chave": "Letramentos + vida + intergeracional.",
            "artigo": "Parecer/Res. CNE/CEB 11 e 1/2000; SOARES (2009).",
            "mnemonico": "L.I.V. na EJA: Letramento, Intergeracional, Vivências.",
        },
    },

    23: {
        "tema": "Letramento científico",
        "subtema": "Ciência, rigor metodológico e argumentação pública",
        "habilidade_bncc": "BNCC – competências gerais 2 e 7 (conhecimento científico e argumentação)",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa (concepção de ciência)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão de que a ciência sustenta seus argumentos no RIGOR METODOLÓGICO e "
            "no debate público. Alternativas erradas sustentam concepções negacionistas (C), individualistas "
            "(B) ou o mito da neutralidade (D)."
        ),
        "como_banca_pensou": (
            "O INEP cobra letramento científico crítico — nem cientificismo (D, neutralidade) nem "
            "relativismo negacionista (B, C). A resposta afirma metodologia + publicidade + argumentação."
        ),
        "resolucao": [
            "Descarte B: liberdade individual acima do coletivo → não expressa ciência.",
            "Descarte C: refutar consenso 'por posicionamento individual' → negacionismo.",
            "Descarte D: 'neutralidade' → mito positivista superado (Kuhn, Latour).",
            "Marque A: rigor metodológico + argumentos publicamente apresentados = ciência como comunidade epistêmica.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "A ciência sustenta-se em método e argumentação pública, submetidos ao crivo da comunidade científica."),
            "B": ("ERRADA", "Sobrepor liberdade individual ao coletivo desconsidera a natureza pública da ciência."),
            "C": ("ERRADA", "Refutar resultados amplamente aceitos por opinião individual = negacionismo, não ciência."),
            "D": ("ERRADA", "Neutralidade absoluta é mito positivista superado pela filosofia e sociologia da ciência."),
        },
        "fundamentacao": [
            "SOUSA, L. Q.; ABREU, K. F. Análise de Estudos e Pesquisas sobre Letramento Científico. Cadernos Cajuína, n. 4, 2024.",
            "KUHN, Thomas S. A estrutura das revoluções científicas. 13. ed. São Paulo: Perspectiva, 2018.",
            "LATOUR, Bruno. Jamais fomos modernos. 3. ed. Rio de Janeiro: Editora 34, 2013.",
            "SASSERON, Lúcia Helena; CARVALHO, Anna Maria P. Alfabetização científica: uma revisão bibliográfica. Investigações em Ensino de Ciências, v. 16, n. 1, 2011.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "LETRAMENTO CIENTÍFICO (Sasseron & Carvalho, 2011) é a capacidade de compreender, aplicar e "
            "criticar o conhecimento científico em situações cotidianas — inclui compreender a NATUREZA DA "
            "CIÊNCIA (rigor, método, consenso, revisão). A filosofia da ciência contemporânea (Kuhn, Latour, "
            "Popper) mostra que ciência não é neutra: é atividade coletiva, provisória, submetida à revisão "
            "por pares e ao debate público. Combater negacionismo exige diferenciar CIÊNCIA de OPINIÃO e "
            "reconhecer o consenso científico como resultado de método, não de autoridade individual."
        ),
        "padroes_banca": (
            "O INEP cobra a diferença entre ciência (método + pareceres coletivos) e opinião (individual). "
            "A alternativa correta enfatiza METODOLOGIA e PUBLICIDADE."
        ),
        "pegadinhas": [
            "Confundir liberdade científica individual com relativismo.",
            "Achar que ciência é neutra.",
            "Legitimar refutação de consenso por opinião pessoal.",
        ],
        "erros_comuns": (
            "Marcar D por hábito escolar do 'método científico neutro'. Kuhn e Latour ensinam o contrário."
        ),
        "dica_estrategica": (
            "Ciência = método + comunidade + revisão por pares. Rejeite alternativas com 'neutralidade' ou "
            "'refutação individual'."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Kuhn (2018), o desenvolvimento científico ocorre por:\n"
            "A) acúmulo linear de fatos;\n"
            "B) alternância entre ciência normal e revoluções (mudanças de paradigma);\n"
            "C) genialidade individual desvinculada da comunidade;\n"
            "D) neutralidade absoluta. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Latour (2013), em 'Jamais fomos modernos', argumenta que:\n"
            "A) ciência é neutra; B) natureza e sociedade estão imbricadas; "
            "C) o método científico é único; D) apenas humanistas fazem ciência. → B",
            "Q2. Sasseron & Carvalho (2011) discutem:\n"
            "A) escrita cursiva; B) alfabetização científica; C) filosofia analítica; D) educação bilíngue. → B",
            "Q3. Negacionismo caracteriza-se por:\n"
            "A) refutar consensos científicos por posições ideológicas individuais; "
            "B) revisão por pares; C) publicações em periódicos; D) debates acadêmicos. → A",
        ],
        "resumo": {
            "regra": "Ciência = método + argumentação pública + revisão por pares.",
            "excecoes": "Revisões de paradigma ocorrem por evidência, não por opinião.",
            "palavra_chave": "Rigor metodológico + comunidade.",
            "artigo": "KUHN (2018); LATOUR (2013); SASSERON & CARVALHO (2011).",
            "mnemonico": "MPRP: Método, Publicidade, Revisão por Pares.",
        },
    },

    24: {
        "tema": "Avaliação formativa (Perrenoud, Hoffmann, Luckesi)",
        "subtema": "Devolutivas para aperfeiçoamento",
        "habilidade_bncc": "Avaliação da aprendizagem – DCN Formação Docente",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica-prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a caracterização da avaliação FORMATIVA: ocorre DURANTE o processo, oferece "
            "DEVOLUTIVAS e orienta o APERFEIÇOAMENTO. Diagnóstica (B, conhecimentos prévios) e somativa "
            "(D, classificação) são funções diferentes."
        ),
        "como_banca_pensou": (
            "O INEP distribui as três funções (diagnóstica, formativa, somativa) entre as alternativas e "
            "adiciona um distrator inicial (A: início de ensino + lista de exercícios). A palavra-chave é "
            "DEVOLUTIVA + APERFEIÇOAMENTO."
        ),
        "resolucao": [
            "Fixe: FORMATIVA = ocorre durante, com feedback contínuo (Perrenoud).",
            "Descarte A: início + lista = aula, não avaliação.",
            "Descarte B: 'identificar conhecimentos prévios' = DIAGNÓSTICA.",
            "Descarte D: 'classificar' = SOMATIVA.",
            "Marque C: devolutivas + aperfeiçoamento = FORMATIVA.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "É uma atividade didática inicial, não caracteriza avaliação formativa."),
            "B": ("ERRADA", "Avaliação DIAGNÓSTICA (conhecimentos prévios), não formativa."),
            "C": ("CORRETA", "Devolutiva orientadora + aperfeiçoamento = avaliação formativa (Perrenoud)."),
            "D": ("ERRADA", "Prova classificatória = avaliação SOMATIVA."),
        },
        "fundamentacao": [
            "PERRENOUD, Philippe. Avaliação: da excelência à regulação das aprendizagens. Porto Alegre: Artmed, 1999.",
            "HOFFMANN, Jussara. Avaliação mediadora. 34. ed. Porto Alegre: Mediação, 2014.",
            "LUCKESI, Cipriano C. Avaliação da aprendizagem escolar. 22. ed. São Paulo: Cortez, 2011.",
            "BLACK, Paul; WILIAM, Dylan. Assessment and Classroom Learning. Assessment in Education, v. 5, n. 1, 1998.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "A AVALIAÇÃO tem três funções principais: (1) DIAGNÓSTICA — no início, identifica saberes "
            "prévios e necessidades; (2) FORMATIVA — durante o processo, oferece devolutivas contínuas "
            "para REGULAR a aprendizagem (Perrenoud, 1999; Black & Wiliam, 1998); (3) SOMATIVA — ao final, "
            "certifica resultados. Hoffmann (2014) defende a AVALIAÇÃO MEDIADORA como uma variante da "
            "formativa. Luckesi (2011) distingue AVALIAÇÃO (diagnóstico + intervenção) de VERIFICAÇÃO "
            "(mera medida). A BNCC valoriza a formativa como suporte à progressão das habilidades."
        ),
        "padroes_banca": (
            "O INEP repete essa tríade em diferentes contextos. Palavras-chave da formativa: DEVOLUTIVA, "
            "FEEDBACK, ACOMPANHAMENTO, APERFEIÇOAMENTO, REGULAÇÃO."
        ),
        "pegadinhas": [
            "Achar que aula inicial é 'diagnóstica' — depende do que a intenção da atividade avalia.",
            "Confundir formativa com somativa.",
            "Reduzir formativa a nota de participação.",
        ],
        "erros_comuns": (
            "Marcar B por parecer 'processual'; mas identificar prévios é DIAGNÓSTICA."
        ),
        "dica_estrategica": (
            "Formativa = feedback + regulação DURANTE o processo. Diagnóstica = ANTES. Somativa = DEPOIS."
        ),
        "variacao": (
            "(Estilo INEP) A avaliação DIAGNÓSTICA tem como finalidade:\n"
            "A) certificar aprendizagens ao final; B) identificar conhecimentos prévios e necessidades; "
            "C) classificar estudantes por notas; D) selecionar para vestibular. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Black & Wiliam (1998) demonstram que:\n"
            "A) formativa é irrelevante; B) feedback formativo eleva a aprendizagem; "
            "C) somativa é a única válida; D) diagnóstica não existe. → B",
            "Q2. Luckesi (2011) diferencia:\n"
            "A) verificação e avaliação; B) escola e família; C) ensino e aprendizagem; "
            "D) livro e caderno. → A",
            "Q3. Hoffmann (2014) chama a formativa de:\n"
            "A) classificatória; B) mediadora; C) unilateral; D) somativa disfarçada. → B",
        ],
        "resumo": {
            "regra": "Formativa = feedback contínuo para regular a aprendizagem.",
            "excecoes": "A mesma atividade pode assumir função distinta segundo intenção do docente.",
            "palavra_chave": "Devolutiva + aperfeiçoamento.",
            "artigo": "PERRENOUD (1999); HOFFMANN (2014); LUCKESI (2011).",
            "mnemonico": "DFS: Diagnóstica (antes), Formativa (durante), Somativa (depois).",
        },
    },

    25: {
        "tema": "Avaliações externas (Saeb/Ideb) e planejamento",
        "subtema": "Uso pedagógico do Ideb – planejamento estratégico",
        "habilidade_bncc": "DCN – Formação Docente; PNE (metas Ideb)",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica-prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede o uso PEDAGÓGICO dos resultados do Ideb. A resposta é DIRECIONAR O PLANEJAMENTO "
            "DE FORMA ESTRATÉGICA (A). As demais representam desvios: reduzir currículo (B), focar "
            "extracurricular (C), ou restringir a socioemocional (D)."
        ),
        "como_banca_pensou": (
            "O INEP alerta contra o USO DISTORCIDO do Ideb (currículo pauperizado, 'teaching to the test'). "
            "A alternativa correta afirma planejamento estratégico e integral."
        ),
        "resolucao": [
            "Descarte B: 'reduzir áreas do currículo' é currículo pauperizado, criticado pela literatura.",
            "Descarte C: 'conteúdos extracurriculares' desloca o foco.",
            "Descarte D: 'apenas habilidades socioemocionais' é reducionista.",
            "Marque A: direcionar o planejamento de forma estratégica = uso legítimo dos dados.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Uso legítimo do Ideb: planejamento estratégico da escola com foco em melhoria integral."),
            "B": ("ERRADA", "Reduzir áreas do currículo é 'teaching to the test' — criticado pela literatura educacional."),
            "C": ("ERRADA", "'Extracurricular' desloca o problema."),
            "D": ("ERRADA", "Focalizar SOMENTE socioemocional negligencia áreas mensuradas pelo Saeb."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 13.005, de 25 de junho de 2014. Plano Nacional de Educação (2014-2024).",
            "BRASIL. INEP. Sistema de Avaliação da Educação Básica – Saeb: documento técnico. Brasília: INEP, 2019.",
            "FERNANDES, Reynaldo. Índice de Desenvolvimento da Educação Básica (Ideb). Brasília: INEP, 2007.",
            "FREITAS, Luiz Carlos de. Os reformadores empresariais da educação. Educação & Sociedade, v. 33, n. 119, 2012.",
        ],
        "teoria": (
            "O SAEB avalia proficiência em Língua Portuguesa e Matemática (com expansão para outras áreas), "
            "e o IDEB (Fernandes, 2007) combina desempenho + fluxo escolar em um índice de 0 a 10. Metas do "
            "PNE (Lei 13.005/14) balizam a política educacional. Do ponto de vista pedagógico, o Ideb deve "
            "ser INSTRUMENTO de planejamento estratégico e não fim em si. Freitas (2012) alerta para os "
            "'reformadores empresariais' que reduzem o currículo a áreas mensuradas — currículo pauperizado. "
            "O uso emancipador do Ideb combina dados quantitativos com análise qualitativa do PPP."
        ),
        "padroes_banca": (
            "O INEP distingue USO ESTRATÉGICO (correto) de USO DISTORCIDO (currículo pauperizado, "
            "teaching to the test)."
        ),
        "pegadinhas": [
            "Reduzir currículo = melhorar Ideb (falso).",
            "Focar apenas socioemocional = fugir do desempenho cognitivo.",
            "Confundir dado com destino.",
        ],
        "erros_comuns": (
            "Marcar D por ver 'socioemocional' como moderno. Pode ser componente, não substituto."
        ),
        "dica_estrategica": (
            "Ideb = dado para planejar; nunca para pauperizar currículo."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Freitas (2012), o principal risco dos 'reformadores empresariais' é:\n"
            "A) ampliar currículo; B) pauperizar currículo por foco excessivo em métricas;\n"
            "C) valorizar arte; D) fortalecer autonomia docente. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O Ideb combina:\n"
            "A) apenas fluxo; B) desempenho e fluxo escolar; C) apenas gastos; D) opinião docente. → B",
            "Q2. O PNE 2014-2024 estabelece:\n"
            "A) 5 metas; B) 20 metas educacionais; C) 50 metas; D) 100 metas. → B",
            "Q3. 'Teaching to the test' consiste em:\n"
            "A) ensinar apenas o que cai no teste; B) planejamento integral; "
            "C) formação docente ampliada; D) alfabetização crítica. → A",
        ],
        "resumo": {
            "regra": "Ideb orienta planejamento estratégico; não substitui currículo.",
            "excecoes": "Escolas com fragilidade em LP/Mat precisam de foco, sem esvaziar outras áreas.",
            "palavra_chave": "Planejamento estratégico integral.",
            "artigo": "PNE (Lei 13.005/14); FERNANDES (2007); FREITAS (2012).",
            "mnemonico": "Ideb = INSUMO, não destino.",
        },
    },

    26: {
        "tema": "Educação inclusiva – exclusão, segregação, integração e inclusão",
        "subtema": "Corresponsabilidade docente e Libras (Sassaki)",
        "habilidade_bncc": "Lei 13.146/2015 (LBI); Decreto 5.626/2005; DCN Educação Especial",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a distinção entre INTEGRAÇÃO (o estudante é 'colocado' na turma, mas com "
            "mediação individual sem transformação do currículo/aula) e INCLUSÃO (professor e escola "
            "assumem corresponsabilidade). O caso descrito — intérprete atuando SEM a participação do "
            "professor regente — configura INTEGRAÇÃO, não inclusão."
        ),
        "como_banca_pensou": (
            "A banca aplica o modelo de Sassaki: EXCLUSÃO (estudante fora), SEGREGAÇÃO (em turma "
            "separada), INTEGRAÇÃO (na turma, adaptação individual) e INCLUSÃO (transformação da escola). "
            "O intérprete assumir o lugar do professor caracteriza INTEGRAÇÃO."
        ),
        "resolucao": [
            "Fixe: intérprete atua SEM o professor regente → mediação individual.",
            "Descarte A: 'exclusão' pressupõe estar FORA da escola.",
            "Descarte B: 'segregação' pressupõe turma/escola SEPARADA.",
            "Descarte C: 'inclusão' exigiria corresponsabilidade docente e transformação da aula.",
            "Marque D: 'integração' — relação individual, sem transformação sistêmica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Não há exclusão; o estudante está na sala."),
            "B": ("ERRADA", "Não há segregação; a turma é regular."),
            "C": ("ERRADA", "Sem corresponsabilidade docente e sem transformação pedagógica, não há inclusão."),
            "D": ("CORRETA", "Mediação individual pelo intérprete, sem envolvimento do professor = integração (Sassaki)."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 13.146, de 6 de julho de 2015. Lei Brasileira de Inclusão da Pessoa com Deficiência (Estatuto da Pessoa com Deficiência). DOU, Brasília, 7 jul. 2015.",
            "BRASIL. Decreto nº 5.626, de 22 de dezembro de 2005. Regulamenta a Lei nº 10.436/2002 (Libras). DOU, 23 dez. 2005.",
            "SASSAKI, Romeu K. Inclusão: construindo uma sociedade para todos. 8. ed. Rio de Janeiro: WVA, 2010.",
            "MANTOAN, Maria Teresa Eglér. Inclusão escolar: o que é? por quê? como fazer? São Paulo: Moderna, 2015.",
            "BRASIL. Ministério da Educação. Política Nacional de Educação Especial na Perspectiva da Educação Inclusiva. Brasília: MEC, 2008.",
        ],
        "teoria": (
            "Sassaki (2010) distingue quatro estágios: (1) EXCLUSÃO — pessoa fora da escola; (2) SEGREGAÇÃO — em "
            "instituições especializadas apartadas; (3) INTEGRAÇÃO — na escola regular, mas com adaptação "
            "individual e responsabilidade do próprio estudante; (4) INCLUSÃO — a ESCOLA se transforma para "
            "receber todos, com corresponsabilidade docente, currículo acessível e mediação compartilhada. "
            "A LBI (Lei 13.146/2015) e a Política Nacional de Educação Especial (2008) instituem o paradigma "
            "inclusivo. O Decreto 5.626/2005 garante Libras como primeira língua da pessoa surda. Mantoan "
            "(2015) reforça: inclusão exige ROMPIMENTO com o modelo homogeneizador."
        ),
        "padroes_banca": (
            "O INEP cobra sempre a diferença INTEGRAÇÃO x INCLUSÃO. A pegadinha é achar que 'ter intérprete' "
            "já basta para inclusão — não basta se o docente se ausenta pedagogicamente."
        ),
        "pegadinhas": [
            "Confundir integração com inclusão.",
            "Achar que presença física do estudante = inclusão.",
            "Terceirizar a responsabilidade pedagógica para o intérprete.",
        ],
        "erros_comuns": (
            "Marcar C por ver a presença do estudante na aula como sinônimo de inclusão."
        ),
        "dica_estrategica": (
            "Inclusão = escola muda; integração = estudante se adapta. O intérprete é APOIO, não substituto docente."
        ),
        "variacao": (
            "(Estilo INEP) A Lei nº 13.146/2015 (LBI) prevê que:\n"
            "A) escolas especiais substituem a escola regular;\n"
            "B) a inclusão é dever da rede regular de ensino, com apoios especializados;\n"
            "C) intérpretes substituem professores;\n"
            "D) a família deve prover mediação. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O Decreto 5.626/2005 garante Libras como:\n"
            "A) segunda língua obrigatória; B) primeira língua da pessoa surda; "
            "C) opcional na EB; D) restrita ao EM. → B",
            "Q2. Mantoan (2015) associa inclusão a:\n"
            "A) padronização; B) transformação estrutural da escola; C) individualização; D) segregação disfarçada. → B",
            "Q3. O termo 'atendimento educacional especializado' (AEE) refere-se a:\n"
            "A) escola substitutiva; B) apoio complementar/suplementar; "
            "C) substituição do professor; D) currículo isolado. → B",
        ],
        "resumo": {
            "regra": "Inclusão = escola muda; integração = estudante se adapta.",
            "excecoes": "AEE é apoio, não substituição da aula regular.",
            "palavra_chave": "Corresponsabilidade docente.",
            "artigo": "Lei 13.146/2015; Dec. 5.626/2005; PNEE-EI (2008).",
            "mnemonico": "ESSI: Exclusão, Segregação, Integração, Inclusão (progressão Sassaki).",
        },
    },

    27: {
        "tema": "Letramento científico e vacinação",
        "subtema": "Projeto interdisciplinar + feira de ciências",
        "habilidade_bncc": "BNCC – competências gerais 2 e 7; PNI",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a proposta pedagógica que MELHOR promove letramento científico: investigação "
            "com dados + interdisciplinaridade + socialização com a comunidade. A alternativa B satisfaz "
            "todos esses critérios; as demais são parciais."
        ),
        "como_banca_pensou": (
            "O INEP contrapõe projeto INVESTIGATIVO INTERDISCIPLINAR (B) a atividades pontuais: "
            "levantamento (A), roda de conversa (C) e distribuição de materiais (D). Todas são válidas, "
            "mas a que melhor promove letramento científico é a B."
        ),
        "resolucao": [
            "Fixe a chave: letramento científico exige INVESTIGAÇÃO + DADOS + APRESENTAÇÃO PÚBLICA.",
            "Descarte A: levantamento familiar é etapa, não projeto.",
            "Descarte C: 'escolha das melhores vacinas' é redação problemática (as vacinas do PNI não são 'escolhidas' pelo indivíduo).",
            "Descarte D: acesso passivo a materiais informativos não constitui investigação.",
            "Marque B: projeto interdisciplinar + investigação de dados + feira de ciências = letramento científico pleno.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Levantamento familiar é etapa preliminar, não caracteriza projeto de letramento científico."),
            "B": ("CORRETA", "Projeto interdisciplinar + dados científicos + feira com a comunidade = letramento científico completo."),
            "C": ("ERRADA", "Reduz o tema a 'escolher vacinas', o que fragiliza a compreensão do PNI."),
            "D": ("ERRADA", "Recepção passiva de materiais não promove protagonismo investigativo."),
        },
        "fundamentacao": [
            "BRASIL. Programa Nacional de Imunizações – PNI. Ministério da Saúde. Brasília, [s.d.].",
            "SASSERON, L. H.; CARVALHO, A. M. P. Alfabetização científica: uma revisão bibliográfica. Investigações em Ensino de Ciências, v. 16, n. 1, 2011.",
            "CACHAPUZ, António et al. A necessária renovação do ensino das ciências. São Paulo: Cortez, 2005.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "O PNI é um dos programas de imunização mais robustos do mundo, criado em 1973. A queda "
            "recente da cobertura vacinal — associada à desinformação — traz de volta doenças "
            "previamente controladas. Escola tem papel-chave no LETRAMENTO CIENTÍFICO: capacitar "
            "estudantes a compreender, aplicar e comunicar conhecimentos científicos (Sasseron & "
            "Carvalho, 2011). Projetos interdisciplinares + investigação com dados + socialização "
            "pública são caminhos privilegiados (Cachapuz et al., 2005)."
        ),
        "padroes_banca": (
            "O INEP cobra articulação INVESTIGAÇÃO + COMUNIDADE. Palavras-veneno: 'escolha individual' "
            "de vacinas, materiais informativos passivos, atividades sem coleta de dados."
        ),
        "pegadinhas": [
            "Confundir letramento com informação.",
            "Reduzir a projeto a uma única disciplina.",
            "Tratar vacinação como escolha individual.",
        ],
        "erros_comuns": (
            "Marcar C por parecer democrática — mas 'escolha das melhores vacinas' expressa má compreensão."
        ),
        "dica_estrategica": (
            "Letramento científico = INVESTIGAR + INTERPRETAR + COMUNICAR. Prefira projetos interdisciplinares."
        ),
        "variacao": (
            "(Estilo INEP) Cachapuz et al. (2005) defendem que o ensino de ciências deve:\n"
            "A) manter-se apenas conteudista;\n"
            "B) articular investigação, contextos e formação cidadã;\n"
            "C) restringir-se a experimentos didáticos;\n"
            "D) suprimir o debate público. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O PNI foi criado em:\n"
            "A) 1988; B) 1973; C) 1960; D) 2005. → B",
            "Q2. Letramento científico envolve:\n"
            "A) apenas memorização; B) compreensão, aplicação e crítica do conhecimento científico; "
            "C) só experimentos; D) memorização e transcrição. → B",
            "Q3. Feira de ciências pode servir para:\n"
            "A) socializar investigações com a comunidade; B) somente competir; "
            "C) selecionar melhores alunos; D) apresentar apenas resultados prontos. → A",
        ],
        "resumo": {
            "regra": "Letramento científico = investigar dados + comunicar publicamente.",
            "excecoes": "Materiais informativos e rodas de conversa complementam, sem esgotar.",
            "palavra_chave": "Investigação + interdisciplinar + comunidade.",
            "artigo": "PNI/Ministério da Saúde; SASSERON & CARVALHO (2011).",
            "mnemonico": "3 I's do letramento: Investigar, Interpretar, Interagir com a comunidade.",
        },
    },

    28: {
        "tema": "Equidade de gênero e ciência",
        "subtema": "Sub-representação feminina nas exatas",
        "habilidade_bncc": "BNCC – competências gerais 6, 8 e 9; Convenção sobre a Eliminação da Discriminação",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a proposta pedagógica que enfrente a sub-representação feminina em exatas. "
            "A resposta é ANALISAR os dados para promover INVESTIGAÇÕES sobre as causas — perspectiva "
            "crítica e afirmativa. As demais reforçam meritocracia (A), determinismo biológico (C) ou "
            "reduzem à competição (D)."
        ),
        "como_banca_pensou": (
            "O INEP cobra a crítica ao meritocratismo e ao essencialismo de gênero. Só B propõe investigação "
            "das causas ESTRUTURAIS."
        ),
        "resolucao": [
            "Descarte A: 'meritocracia' oculta as desigualdades históricas.",
            "Descarte C: 'aptidões naturais' = essencialismo biológico, superado.",
            "Descarte D: competição não enfrenta o problema.",
            "Marque B: analisar dados + investigar causas = ação crítica e afirmativa.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Meritocracia mascara desigualdades estruturais de gênero."),
            "B": ("CORRETA", "Analisar dados e investigar causas materializa a educação para equidade de gênero."),
            "C": ("ERRADA", "Essencialismo biológico é epistemologicamente insustentável."),
            "D": ("ERRADA", "Competição não enfrenta a sub-representação estrutural."),
        },
        "fundamentacao": [
            "SCHIEBINGER, Londa. O feminismo mudou a ciência? Bauru: EDUSC, 2001.",
            "BRASIL. Ministério da Educação. Meninas e mulheres nas ciências. Brasília: MEC/UNESCO, 2020.",
            "ORGANIZAÇÃO DAS NAÇÕES UNIDAS. ODS 5 – Igualdade de Gênero. Agenda 2030. Nova York: ONU, 2015.",
            "LOURO, Guacira Lopes. Gênero, sexualidade e educação. 16. ed. Petrópolis: Vozes, 2014.",
            "BRASIL. Lei nº 9.394/1996 (LDB), art. 3º (princípios).",
        ],
        "teoria": (
            "A ausência das mulheres nas ciências exatas resulta de PROCESSOS HISTÓRICOS de exclusão — "
            "não de 'aptidões naturais'. Schiebinger (2001) analisa como o gênero atravessa a produção "
            "científica. Louro (2014) discute a construção social do gênero na escola. O ODS 5 (igualdade "
            "de gênero) orienta políticas educacionais. A LDB (art. 3º) prevê igualdade de condições para "
            "acesso e permanência. Ação pedagógica crítica: coletar dados, investigar causas, propor "
            "intervenção afirmativa."
        ),
        "padroes_banca": (
            "O INEP contrapõe visão crítica x meritocracia x essencialismo biológico."
        ),
        "pegadinhas": [
            "Marcar A por 'neutralidade' soar positiva.",
            "Aceitar 'aptidões naturais'.",
            "Reduzir enfrentamento à competição.",
        ],
        "erros_comuns": (
            "Marcar D por hábito escolar de valorizar olimpíadas."
        ),
        "dica_estrategica": (
            "Equidade de gênero = investigar causas estruturais + ação afirmativa. Rejeite meritocracia + biologismo."
        ),
        "variacao": (
            "(Estilo INEP) Louro (2014) argumenta que gênero na escola é:\n"
            "A) fenômeno biológico fixo;\n"
            "B) construção social atravessada por práticas pedagógicas e curriculares;\n"
            "C) irrelevante para a aprendizagem;\n"
            "D) restrito à educação sexual. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Schiebinger (2001) discute:\n"
            "A) neutralidade da ciência; B) impacto do feminismo sobre a ciência; "
            "C) darwinismo; D) genética. → B",
            "Q2. O ODS 5 trata de:\n"
            "A) educação; B) igualdade de gênero; C) fome; D) energia. → B",
            "Q3. Meritocracia, na perspectiva crítica, é:\n"
            "A) neutra; B) mecanismo que naturaliza desigualdades históricas; "
            "C) sinônimo de justiça; D) mera técnica de avaliação. → B",
        ],
        "resumo": {
            "regra": "Enfrentar a sub-representação exige análise de dados + intervenção afirmativa.",
            "excecoes": "Olimpíadas e competições podem coexistir, se combinadas com políticas afirmativas.",
            "palavra_chave": "Investigação estrutural + equidade.",
            "artigo": "LDB art. 3º; ODS 5.",
            "mnemonico": "IEA: Investigar, Enfrentar (causas), Afirmar (direitos).",
        },
    },

    29: {
        "tema": "Racismo religioso e Educação (Wanderson Nascimento)",
        "subtema": "Religiões de matriz africana e violência simbólica",
        "habilidade_bncc": "Lei 10.639/03; DCN Educação Escolar Quilombola; BNCC – competência 6",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a ação que enfrenta o RACISMO RELIGIOSO na escola. A resposta A afirma que a "
            "abordagem da religião e cultura iorubanas permite refletir sobre VIOLÊNCIAS MATERIAIS E "
            "SIMBÓLICAS — expressão coerente com o texto de Wanderson Nascimento."
        ),
        "como_banca_pensou": (
            "A banca contrapõe uma abordagem CRÍTICA e AFIRMATIVA (A) a visões assimilacionistas (B: "
            "'identidade comum'), laicistas mal-fundamentadas (C: escola só para 'conhecimento geral') "
            "e legalistas equivocadas (D: 'lei sobre iorubana e indígena' — que não existe nessa forma)."
        ),
        "resolucao": [
            "Descarte B: 'identidade comum a todos' apaga diferenças — assimilacionismo.",
            "Descarte C: 'ambiente escolar como espaço de convívio religioso distancia-se' — falso; escola pública laica não impede abordar religiões.",
            "Descarte D: não há 'lei que trate do ensino da história iorubana e indígena' — Lei 11.645/08 trata de História e Cultura Afro-Brasileira e Indígena, e o 'ensino religioso' NÃO é obrigatório para o estudante.",
            "Marque A: reflexão sobre violências materiais e simbólicas.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Aborda religiões de matriz africana como forma de identificar e enfrentar violências simbólicas e materiais do racismo religioso."),
            "B": ("ERRADA", "'Identidade comum a todos' apaga a diferença e reforça assimilacionismo."),
            "C": ("ERRADA", "Ensino laico não implica silenciamento sobre culturas religiosas — implica pluralismo."),
            "D": ("ERRADA", "Enunciado normativo impreciso; a lei é a 10.639/03 e 11.645/08, e o ensino religioso é facultativo ao estudante."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 10.639, de 9 de janeiro de 2003. Altera a LDB. DOU, 10 jan. 2003.",
            "BRASIL. Lei nº 11.645, de 10 de março de 2008. Altera a LDB, incluindo história e cultura indígena. DOU, 11 mar. 2008.",
            "NASCIMENTO, Wanderson Flor do. As religiões de matrizes africanas, resistência e contexto escolar: entre encruzilhadas. In: Memórias do Baobá II. Fortaleza: UFC, 2017.",
            "MIRANDA, Cláudia; RIASCOS, Fanny; MELÉNDEZ, Juliana (org.). Pedagogia decolonial e educação antirracista. Belo Horizonte: Autêntica, 2019.",
            "BRASIL. Constituição da República Federativa do Brasil de 1988, art. 5º, VI e VIII (liberdade religiosa).",
        ],
        "teoria": (
            "O RACISMO RELIGIOSO é uma modalidade de racismo dirigida a religiões de matriz africana e "
            "afro-brasileira (candomblé, umbanda, xambá etc.). Manifesta-se como intolerância, ataques a "
            "terreiros, silenciamento no currículo e estigmatização. A resposta pedagógica é ABORDAR "
            "AFIRMATIVAMENTE essas religiões, à luz das Leis 10.639/03 e 11.645/08 e do art. 5º, VI e "
            "VIII da CF/88 (liberdade religiosa). Wanderson Nascimento (2017) propõe pensar a escola a "
            "partir das encruzilhadas — dos deuses do movimento (Exu, Ogum) — como signos de negociação, "
            "pluralidade e resistência."
        ),
        "padroes_banca": (
            "O INEP cobra a distinção entre enfrentamento crítico do racismo religioso vs. assimilacionismo."
        ),
        "pegadinhas": [
            "Confundir 'laicidade' com apagamento de religiões afro-brasileiras.",
            "Aceitar 'identidade comum' como afirmação de igualdade.",
            "Assumir que existe 'lei sobre ensino da história iorubana'.",
        ],
        "erros_comuns": (
            "Marcar B pela linguagem 'identitária'."
        ),
        "dica_estrategica": (
            "Enfrentamento antirracista + laicidade plural: abordar as religiões afrodescendentes reconhecendo diferenças."
        ),
        "variacao": (
            "(Estilo INEP) A CF/88, art. 5º, VI, garante:\n"
            "A) liberdade religiosa;\n"
            "B) monopólio católico;\n"
            "C) proibição de manifestações religiosas em público;\n"
            "D) laicismo como ateísmo estatal. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Racismo religioso é:\n"
            "A) proteção da liberdade religiosa; B) modalidade de racismo contra religiões de matriz africana; "
            "C) laicidade; D) neutralidade. → B",
            "Q2. A Lei 10.639/03 obriga o ensino de:\n"
            "A) filosofia; B) história e cultura afro-brasileira e africana; "
            "C) apenas sociologia; D) apenas religião. → B",
            "Q3. Wanderson Nascimento (2017) mobiliza os signos de:\n"
            "A) Zeus e Hera; B) Exu e Ogum (panteão iorubano); C) Anúbis e Ísis; D) Odin e Thor. → B",
        ],
        "resumo": {
            "regra": "Escola combate racismo religioso pela ABORDAGEM AFIRMATIVA das religiões afrodescendentes.",
            "excecoes": "Ensino religioso é facultativo para o estudante (LDB, art. 33).",
            "palavra_chave": "Reconhecimento + violência simbólica + pluralidade.",
            "artigo": "CF/88 art. 5º, VI; Lei 10.639/03; Res. CNE/CP 1/2004.",
            "mnemonico": "3 R's antirracismo religioso: Reconhecer, Recusar (a estigmatização), Reafirmar (a diferença).",
        },
    },

    30: {
        "tema": "Ações afirmativas – Lei 12.711/2012 (revisão de 2023)",
        "subtema": "PNEDH e conquista democrática",
        "habilidade_bncc": "BNCC – competências 6, 9, 10; PNEDH (2007)",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "O item cobra a leitura das ações afirmativas como CONQUISTA DEMOCRÁTICA resultante da "
            "mobilização social. A alternativa B é a única que expressa essa dimensão histórica; as "
            "demais desconectam da história (A), atribuem falsa neutralidade à mídia (C) ou apresentam "
            "uma garantia excessiva (D)."
        ),
        "como_banca_pensou": (
            "A banca cobra o vínculo entre AÇÕES AFIRMATIVAS e MOBILIZAÇÃO SOCIAL, especialmente do "
            "Movimento Negro. Palavras-veneno: 'desvinculadas do processo histórico', 'neutralidade da "
            "mídia', 'garantia da superação' (a lei é meio, não fim)."
        ),
        "resolucao": [
            "Fixe: Lei 12.711/2012 = conquista da mobilização social.",
            "Descarte A: 'desvinculadas do processo histórico' contradiz a origem da lei.",
            "Descarte C: mídia NÃO é neutra em relação ao racismo.",
            "Descarte D: cotas mitigam, mas não 'garantem a superação' da discriminação.",
            "Marque B: conquista democrática decorrente da mobilização social.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Ações afirmativas emergem da história do Movimento Negro; não são desvinculadas."),
            "B": ("CORRETA", "Reflete a origem e o sentido histórico das cotas — luta democrática e mobilização social."),
            "C": ("ERRADA", "Mídia não é neutra: reproduz e enquadra o debate."),
            "D": ("ERRADA", "Cotas mitigam desigualdade, mas não são garantia absoluta de superação."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 12.711, de 29 de agosto de 2012. Dispõe sobre o ingresso nas universidades federais e nas instituições federais de ensino técnico de nível médio. DOU, Brasília, 30 ago. 2012.",
            "BRASIL. Lei nº 14.723, de 13 de novembro de 2023. Altera a Lei nº 12.711/2012. DOU, Brasília, 14 nov. 2023.",
            "BRASIL. Comitê Nacional de Educação em Direitos Humanos. Plano Nacional de Educação em Direitos Humanos. Brasília: SEDH/MEC, 2007.",
            "GOMES, Nilma Lino. O movimento negro educador. Petrópolis: Vozes, 2017.",
            "SUPREMO TRIBUNAL FEDERAL. ADPF 186. Rel. Min. Ricardo Lewandowski, j. 26/4/2012.",
        ],
        "teoria": (
            "A Lei 12.711/2012 (Lei de Cotas) resulta da mobilização histórica do Movimento Negro no Brasil "
            "(Gomes, 2017) e de organizações estudantis. Sua constitucionalidade foi reafirmada pelo STF "
            "(ADPF 186, 2012). Em 2023, a Lei 14.723 revisou o dispositivo, mantendo e aprimorando as "
            "cotas para pretos, pardos, indígenas, quilombolas e pessoas com deficiência. O PNEDH (2007) "
            "orienta a educação em direitos humanos. Ações afirmativas são REPARATÓRIAS e "
            "DEMOCRATIZANTES — mitigam, mas não esgotam o enfrentamento do racismo estrutural."
        ),
        "padroes_banca": (
            "O INEP alinha alternativas corretas ao vocabulário 'mobilização social', 'conquista democrática', "
            "'reparação histórica'."
        ),
        "pegadinhas": [
            "Achar que cotas 'garantem' superação — mitigam.",
            "Aceitar 'neutralidade da mídia'.",
            "Desvincular a lei de seu contexto histórico.",
        ],
        "erros_comuns": (
            "Marcar D por 'discriminação racial' soar como pauta correta — mas a formulação 'garantia' é imprecisa."
        ),
        "dica_estrategica": (
            "Cotas = conquista democrática, mobilização, reparação. Nunca 'garantia absoluta'."
        ),
        "variacao": (
            "(Estilo INEP) O STF, na ADPF 186 (2012), decidiu que:\n"
            "A) cotas raciais são inconstitucionais;\n"
            "B) cotas raciais são compatíveis com a CF/88 como ações afirmativas;\n"
            "C) cotas apenas socioeconômicas; D) cotas apenas para indígenas. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Lei 12.711/2012 destina cotas em universidades federais para:\n"
            "A) apenas alto rendimento; B) pretos, pardos, indígenas, quilombolas, PcD e egressos de escolas públicas; "
            "C) apenas mulheres; D) apenas idosos. → B",
            "Q2. O Movimento Negro, segundo Gomes (2017), é:\n"
            "A) educador da sociedade; B) irrelevante; C) apenas cultural; D) apenas político. → A",
            "Q3. O PNEDH (2007) contempla:\n"
            "A) apenas educação básica; B) educação básica, superior, mídia, servidores públicos; "
            "C) apenas EM; D) apenas EJA. → B",
        ],
        "resumo": {
            "regra": "Cotas são conquistas democráticas da mobilização social; mitigam desigualdade histórica.",
            "excecoes": "Não são solução única; devem articular-se com políticas de permanência.",
            "palavra_chave": "Mobilização social + conquista democrática.",
            "artigo": "Lei 12.711/2012; Lei 14.723/2023; STF ADPF 186/2012.",
            "mnemonico": "MDR: Mobilização, Democracia, Reparação.",
        },
    },

    31: {
        "tema": "Relatório Figueiredo, Ditadura Militar e povos indígenas",
        "subtema": "Ensino de História e Comissão Nacional da Verdade",
        "habilidade_bncc": "EM13CHS103, EM13CHS604; Lei 11.645/08",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Prática (didática da História)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta (História do Brasil recente)",
        "justificativa_classificacao": (
            "Item cobra o par METODOLOGIA ATIVA + OBJETIVO didático coerente com a fonte. A resposta D "
            "(debate + evidenciar divergências e violações de DH) é a única que preserva a natureza "
            "denunciativa do relatório."
        ),
        "como_banca_pensou": (
            "A banca combina fonte histórica polêmica (Relatório Figueiredo) com escolha didática. A "
            "alternativa correta articula participação ativa (debate) + objetivo crítico coerente com o "
            "conteúdo (violações de direitos humanos)."
        ),
        "resolucao": [
            "Reconheça: relatório denuncia violência estatal contra povos indígenas na Ditadura.",
            "Descarte A: 'igualdade jurídica' contradiz a realidade da Ditadura e o teor do relatório.",
            "Descarte B: 'aula expositiva' não é participação ativa; 'manipulação' é leitura reducionista.",
            "Descarte C: 'falsificação do documento' contradiz a autenticidade histórica reconhecida.",
            "Marque D: debate + evidenciar divergências e violações de direitos humanos.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Descreve a Ditadura como igualitária — inverte a realidade histórica."),
            "B": ("ERRADA", "Aula expositiva não é metodologia ativa; interpretação reducionista da fonte."),
            "C": ("ERRADA", "Nega a autenticidade histórica do relatório."),
            "D": ("CORRETA", "Debate + evidenciar violações de direitos humanos = objetivo coerente com o teor da fonte."),
        },
        "fundamentacao": [
            "COMISSÃO NACIONAL DA VERDADE. Relatório. Vol. II, texto 5: Violações de direitos humanos dos povos indígenas. Brasília: CNV, 2014.",
            "FIGUEIREDO, Jáder de. Relatório Figueiredo. Ministério do Interior, Brasília, 1968.",
            "MPF – 6ª Câmara de Coordenação e Revisão. Crimes da ditadura militar contra povos indígenas. Brasília: MPF, 2022.",
            "BRASIL. Lei nº 11.645, de 10 de março de 2008. Altera a LDB. DOU, 11 mar. 2008.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "O RELATÓRIO FIGUEIREDO (1968), produzido pelo procurador Jáder de Figueiredo, documentou "
            "violações graves contra povos indígenas — expulsões, tortura, chacinas, escravização, ligadas "
            "a fazendeiros e agentes do Serviço de Proteção aos Índios (SPI). Desaparecido por décadas, "
            "foi redescoberto em 2012 no Museu do Índio (RJ) e integrou os trabalhos da Comissão Nacional "
            "da Verdade. É fonte essencial para o ensino da Ditadura em diálogo com a Lei 11.645/08 e "
            "com o direito à memória. A didática ativa (debate, roda de conversa, pesquisa em fontes) "
            "articula o passado à responsabilidade cidadã presente."
        ),
        "padroes_banca": (
            "O INEP usa fontes documentais recentes (CNV, MPF) para questões de História do Brasil "
            "contemporâneo. A alternativa correta preserva o TEOR da fonte."
        ),
        "pegadinhas": [
            "Aceitar 'aula expositiva' como ativa.",
            "Confundir 'sumiço' do relatório com 'falsificação'.",
            "Suavizar a Ditadura como 'igualitária'.",
        ],
        "erros_comuns": (
            "Marcar B por associar 'documento manipulado' a crítica de fonte, sem observar que a "
            "metodologia é expositiva."
        ),
        "dica_estrategica": (
            "Em item com fonte histórica sensível, prefira metodologia ATIVA (debate, roda, seminário) + "
            "objetivo COERENTE com o teor documental."
        ),
        "variacao": (
            "(Estilo INEP) A Comissão Nacional da Verdade (Lei 12.528/2011) tem por objetivo:\n"
            "A) apagar registros; B) examinar e esclarecer graves violações de direitos humanos entre "
            "1946 e 1988; C) redigir a Constituição; D) impedir arquivamento judicial. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O Relatório Figueiredo foi elaborado em:\n"
            "A) 1968; B) 1888; C) 1930; D) 2012. → A",
            "Q2. O SPI foi substituído pela:\n"
            "A) FUNAI (Fundação Nacional dos Povos Indígenas), em 1967; B) ANAI; C) SESAI; D) FIOCRUZ. → A",
            "Q3. A Lei 11.645/08 obriga o ensino de:\n"
            "A) filosofia; B) história e cultura afro-brasileira E indígena; C) sociologia; "
            "D) apenas ciências. → B",
        ],
        "resumo": {
            "regra": "Fontes históricas sensíveis + metodologia ativa + objetivo coerente com o teor.",
            "excecoes": "Aula expositiva pode contextualizar, mas não é participação ativa.",
            "palavra_chave": "Debate + violações de DH.",
            "artigo": "CNV (2014); Lei 11.645/08.",
            "mnemonico": "F.I.G.: Fonte Investigada, Grupo em debate.",
        },
    },

    32: {
        "tema": "Ditadura Militar + povos originários (Relatório Figueiredo)",
        "subtema": "Repressão, resistência e ensino de história",
        "habilidade_bncc": "EM13CHS103, EM13CHS604; Lei 11.645/08",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o OBJETIVO didático de motivação inicial a partir do Relatório Figueiredo. A "
            "resposta articula IMPACTO DA DITADURA sobre povos originários + PROCESSOS DE REPRESSÃO E "
            "RESISTÊNCIA."
        ),
        "como_banca_pensou": (
            "A banca oferece leituras distorcidas (A: 'avanço do comunismo entre povos originários'; C: "
            "reduzir violência à Marcha da Família; D: 'acordos protecionistas' com mineradores) e uma "
            "leitura correta (B)."
        ),
        "resolucao": [
            "Reconheça o objetivo: motivação para tema (Ditadura + povos indígenas).",
            "Descarte A: 'avanço do comunismo entre povos originários' é caricatura anticomunista.",
            "Descarte C: Marcha da Família é fenômeno urbano de classe média, sem correlação direta com violência aos indígenas.",
            "Descarte D: não houve 'acordos protecionistas' — houve espoliação.",
            "Marque B: impacto da Ditadura sobre povos originários + repressão e resistência.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Anticomunismo caricatural, sem base histórica."),
            "B": ("CORRETA", "Objetivo alinhado ao Relatório: violações + resistência dos povos originários."),
            "C": ("ERRADA", "Marcha da Família não motivou a violência a indígenas."),
            "D": ("ERRADA", "Historiograficamente falso: houve espoliação, não 'acordos protecionistas'."),
        },
        "fundamentacao": [
            "COMISSÃO NACIONAL DA VERDADE. Relatório – Vol. II, texto 5 (povos indígenas). Brasília, 2014.",
            "CUNHA, Manuela Carneiro da (org.). História dos índios no Brasil. São Paulo: Cia. das Letras, 1992.",
            "GAGLIARDI, José Mauro. O indígena e a República. São Paulo: Hucitec, 1989.",
            "MPF. Crimes da ditadura militar contra povos indígenas. Brasília, 2022.",
        ],
        "teoria": (
            "Durante a Ditadura, os povos originários foram vítimas de espoliação territorial e violência "
            "sistemática, especialmente em obras faraônicas (Transamazônica, hidrelétricas) e no avanço "
            "agropecuário. A CNV documentou milhares de mortes de indígenas. O Relatório Figueiredo é "
            "peça-chave para compreender o SPI (Serviço de Proteção aos Índios), que foi extinto em 1967 "
            "após o escândalo do relatório, dando origem à FUNAI."
        ),
        "padroes_banca": (
            "O INEP alinha alternativas erradas a narrativas anticomunistas caricatas ou a inversões "
            "historiográficas."
        ),
        "pegadinhas": [
            "Aceitar 'avanço do comunismo entre povos originários' como leitura.",
            "Confundir Marcha da Família com política indigenista.",
            "Inventar 'acordos protecionistas' inexistentes.",
        ],
        "erros_comuns": (
            "Marcar A por lembrar do contexto Guerra Fria; mas o relatório trata de violações concretas, "
            "não do 'comunismo'."
        ),
        "dica_estrategica": (
            "Em itens sobre Ditadura + povos originários, prefira leituras que articulem VIOLAÇÃO, "
            "ESPOLIAÇÃO e RESISTÊNCIA."
        ),
        "variacao": (
            "(Estilo INEP) O SPI foi substituído pela FUNAI em:\n"
            "A) 1988; B) 1930; C) 1967; D) 2012. Gabarito: C."
        ),
        "minisimulado": [
            "Q1. A Transamazônica, construída na Ditadura, teve como consequência:\n"
            "A) integração pacífica; B) violência contra povos indígenas e desmatamento; "
            "C) sem impacto; D) reflorestamento. → B",
            "Q2. Manuela Carneiro da Cunha (1992) editou:\n"
            "A) História dos Índios no Brasil; B) O Livro Verde; C) O Aleijadinho; D) O Alienista. → A",
            "Q3. A CNV entregou seu relatório final em:\n"
            "A) 2010; B) 2012; C) 2014; D) 2020. → C",
        ],
        "resumo": {
            "regra": "Ditadura + povos originários = violência sistemática, não anticomunismo genérico.",
            "excecoes": "Havia diversidade interna nas políticas, mas a tônica é a espoliação.",
            "palavra_chave": "Repressão + resistência + povos originários.",
            "artigo": "CNV (2014); Lei 12.528/11.",
            "mnemonico": "FIGUEIREDO = FI (força indígena) + GUERRA (contra) + REDO (revelado após décadas).",
        },
    },

    33: {
        "tema": "Fonte memorialística – Mércia Albuquerque",
        "subtema": "Diários como fonte e teoria da História",
        "habilidade_bncc": "EM13CHS101, EM13CHS603",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Teórica (crítica das fontes)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a CLASSIFICAÇÃO da fonte — o diário de Mércia é MEMORIALÍSTICO e reconstitui "
            "EXPERIÊNCIAS INDIVIDUAIS. As demais atribuem-lhe naturezas incorretas (oral, oficial, "
            "hemerográfica)."
        ),
        "como_banca_pensou": (
            "A banca cobra a tipologia clássica das fontes: oral, memorialística (autobiografia/diário), "
            "oficial (administrativa), hemerográfica (imprensa)."
        ),
        "resolucao": [
            "Reconheça: 'Diários 1973-74' → fonte memorialística/autobiográfica.",
            "Descarte A: 'oral' — o texto é escrito, não transcrição de oralidade.",
            "Descarte C: 'oficial' — diário privado, não documento administrativo.",
            "Descarte D: 'hemerográfica' — não é jornal/periódico.",
            "Marque B: memorialística + reconstituir experiências individuais.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Fonte escrita, não oral."),
            "B": ("CORRETA", "Diário = fonte memorialística que reconstitui experiências individuais."),
            "C": ("ERRADA", "Não é documento oficial administrativo."),
            "D": ("ERRADA", "Não é fonte de imprensa."),
        },
        "fundamentacao": [
            "GOMES, Angela de Castro (org.). Escrita de si, escrita da História. Rio de Janeiro: FGV, 2004.",
            "LEJEUNE, Philippe. O pacto autobiográfico: de Rousseau à internet. Belo Horizonte: UFMG, 2014.",
            "BLOCH, Marc. Apologia da história ou o ofício de historiador. Rio de Janeiro: Zahar, 2001.",
            "LE GOFF, Jacques. História e memória. 5. ed. Campinas: Unicamp, 2003.",
        ],
        "teoria": (
            "Fontes memorialísticas (diários, memórias, autobiografias) são muito utilizadas em História "
            "do Tempo Presente. Angela de Castro Gomes (2004) sistematiza a 'escrita de si' como território "
            "historiográfico. Lejeune (2014) define o PACTO AUTOBIOGRÁFICO. A crítica de fontes distingue "
            "subjetividade (aspecto epistemológico legítimo) de mera opinião. Le Goff (2003) discute a "
            "MEMÓRIA como campo de disputa histórica."
        ),
        "padroes_banca": (
            "O INEP cobra tipologia das fontes com atenção à ADEQUAÇÃO didática do uso."
        ),
        "pegadinhas": [
            "Confundir memorialístico com oral.",
            "Confundir diário com documento oficial.",
            "Reduzir subjetividade à opinião.",
        ],
        "erros_comuns": (
            "Marcar A por associar 'testemunho' com 'oral'."
        ),
        "dica_estrategica": (
            "Fonte escrita em primeira pessoa = memorialística/autobiográfica."
        ),
        "variacao": (
            "(Estilo INEP) Fontes de imprensa (jornais, revistas) são classificadas como:\n"
            "A) memorialísticas; B) hemerográficas; C) iconográficas; D) orais. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Lejeune (2014) formulou:\n"
            "A) o pacto autobiográfico; B) o hipertexto; C) a psicanálise; D) o positivismo. → A",
            "Q2. Angela de Castro Gomes é referência para:\n"
            "A) história oral no Brasil; B) escrita de si; C) filologia; D) demografia histórica. → B",
            "Q3. Marc Bloch é referência para:\n"
            "A) Annales; B) positivismo; C) marxismo ortodoxo; D) micro-história italiana. → A",
        ],
        "resumo": {
            "regra": "Diário = fonte memorialística/autobiográfica.",
            "excecoes": "Diários podem conter elementos hemerográficos (recortes) ou oficiais (anexos).",
            "palavra_chave": "Experiências individuais + escrita de si.",
            "artigo": "GOMES (2004); LEJEUNE (2014); LE GOFF (2003).",
            "mnemonico": "M.E.M.: Memorial + Experiência + Mercadoria narrativa (do sujeito).",
        },
    },

    34: {
        "tema": "Ensino de História com fonte memorialística – resistência política",
        "subtema": "Diários femininos e Ditadura Militar",
        "habilidade_bncc": "EF09HI21, EM13CHS603; Lei 12.528/11 (CNV)",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a coerência entre OBJETIVO (identificar resistências políticas) e METODOLOGIA "
            "(análise de fontes literárias/memorialísticas). O diário de Mércia expressa RESISTÊNCIA "
            "FEMININA à Ditadura."
        ),
        "como_banca_pensou": (
            "A banca contrasta objetivos coerentes vs. deslocados. A resposta B é a única que preserva a "
            "natureza da fonte (memorialística/literária) e o TEOR (resistência política)."
        ),
        "resolucao": [
            "Descarte A: 'produção de texto argumentativo' desloca a fonte para um exercício sem análise crítica.",
            "Descarte C: 'expressões culturais' generaliza demais; a fonte é sobre resistência política.",
            "Descarte D: 'grupos da luta armada' não é o foco do diário.",
            "Marque B: resistências políticas + análise de fontes literárias.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Texto argumentativo é atividade, não análise crítica de fonte."),
            "B": ("CORRETA", "Objetivo (resistências políticas) + metodologia (análise de fontes literárias) coerentes com a natureza do diário."),
            "C": ("ERRADA", "Diário não é fonte prioritariamente sobre 'expressões culturais' amplas."),
            "D": ("ERRADA", "Mércia era advogada, não integrante de grupo armado; o foco é a resistência jurídica e política."),
        },
        "fundamentacao": [
            "COLLING, Ana Maria. A resistência da mulher à ditadura militar no Brasil. Rio de Janeiro: Rosa dos Tempos, 1997.",
            "COMISSÃO NACIONAL DA VERDADE. Relatório – Vol. III (mortos e desaparecidos). Brasília, 2014.",
            "SCOTT, Joan W. Gênero: uma categoria útil de análise histórica. Educação e Realidade, v. 20, n. 2, 1995.",
            "PERROT, Michelle. Minha história das mulheres. São Paulo: Contexto, 2007.",
        ],
        "teoria": (
            "As mulheres foram protagonistas da resistência à Ditadura Militar, atuando em partidos, "
            "comitês de familiares, defesa jurídica e organizações da sociedade civil. Colling (1997) e a "
            "CNV (2014) documentam essa participação. Scott (1995) fornece o conceito de GÊNERO como "
            "categoria histórica; Perrot (2007) organiza a história das mulheres. A memória de Mércia "
            "Albuquerque exemplifica a intersecção entre gênero, direito e resistência política."
        ),
        "padroes_banca": (
            "O INEP costuma cobrar coerência OBJETIVO + METODOLOGIA + NATUREZA DA FONTE."
        ),
        "pegadinhas": [
            "Confundir resistência política com luta armada.",
            "Reduzir fonte memorialística a exercício de escrita.",
            "Generalizar para 'expressões culturais'.",
        ],
        "erros_comuns": (
            "Marcar A por parecer razoável 'texto argumentativo', sem observar a metodologia própria da fonte."
        ),
        "dica_estrategica": (
            "Objetivo + método sempre COERENTES com a natureza da fonte."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Joan Scott (1995), gênero é:\n"
            "A) sinônimo de sexo biológico;\n"
            "B) categoria útil de análise histórica que evidencia relações de poder;\n"
            "C) fase histórica única; D) apenas fator cultural. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Michelle Perrot é referência para:\n"
            "A) História das Mulheres; B) Positivismo; C) Semiótica; D) Micro-história italiana. → A",
            "Q2. A CNV publicou seu relatório final em:\n"
            "A) 2010; B) 2014; C) 2016; D) 2018. → B",
            "Q3. Fontes literárias/memorialísticas exigem:\n"
            "A) leitura acrítica; B) crítica interna e externa; C) neutralidade absoluta; D) transcrição literal. → B",
        ],
        "resumo": {
            "regra": "Objetivo + método = natureza da fonte. Diário = resistência memorialística.",
            "excecoes": "Fontes memorialísticas podem articular-se a outras (oficiais, hemerográficas).",
            "palavra_chave": "Resistência + fontes literárias.",
            "artigo": "COLLING (1997); CNV (2014); SCOTT (1995).",
            "mnemonico": "R.E.M.: Resistência, Escrita de si, Memória.",
        },
    },

    35: {
        "tema": "Representações históricas, memória e identidade",
        "subtema": "'Narradores de Javé' e disputas pelo passado",
        "habilidade_bncc": "EM13CHS101, EM13CHS202",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão de que o filme expressa REPRESENTAÇÕES HISTÓRICAS como campo de "
            "DISPUTA e criações identitárias. As demais alternativas ou negam a disputa (A: converter "
            "oral em escrito valida) ou reduzem à oposição individual-social (B) ou à ciência (D)."
        ),
        "como_banca_pensou": (
            "A banca mobiliza Chartier e história cultural: as REPRESENTAÇÕES lutam por hegemonia. "
            "A resposta correta explicita disputa + identidades."
        ),
        "resolucao": [
            "Reconheça: filme trata de versões, memórias e territorialidade.",
            "Descarte A: 'converter oral para escrito valida' apaga a oralidade.",
            "Descarte B: oposição individual x social é reducionista.",
            "Descarte D: 'subjetividade questionada' desconhece o valor epistêmico das representações.",
            "Marque C: representações históricas como objeto de disputa e criações identitárias.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Converter oralidade para escrita hierarquiza indevidamente."),
            "B": ("ERRADA", "Individual x social não é a chave do filme."),
            "C": ("CORRETA", "Representações históricas como campo de disputa e criação identitária."),
            "D": ("ERRADA", "Subjetividade é constitutiva da produção histórica, não sua negação."),
        },
        "fundamentacao": [
            "CHARTIER, Roger. A história cultural: entre práticas e representações. Lisboa: Difel, 1990.",
            "HALL, Stuart. A identidade cultural na pós-modernidade. Rio de Janeiro: DP&A, 2006.",
            "HALBWACHS, Maurice. A memória coletiva. São Paulo: Centauro, 2006.",
            "POLLAK, Michael. Memória, esquecimento, silêncio. Estudos Históricos, v. 2, n. 3, 1989.",
        ],
        "teoria": (
            "Chartier (1990) define REPRESENTAÇÕES como esquemas com que os grupos constroem o mundo, "
            "sempre em disputa. Hall (2006) explora identidades em contextos pós-modernos. Halbwachs "
            "(2006) mostra que a memória é coletiva; Pollak (1989) destaca as memórias subterrâneas, "
            "esquecimentos e silêncios. O filme 'Narradores de Javé' (Eliane Caffé, 2003) é usado com "
            "frequência para pensar oralidade, memória, patrimônio e disputas territoriais."
        ),
        "padroes_banca": (
            "O INEP cobra a linha da HISTÓRIA CULTURAL. Palavras-chave: representação, disputa, identidade."
        ),
        "pegadinhas": [
            "Hierarquizar oralidade e escrita.",
            "Reduzir disputa a individual/social.",
            "Negar a subjetividade.",
        ],
        "erros_comuns": (
            "Marcar A por associar 'registro escrito' a validação — mas o filme mostra o inverso."
        ),
        "dica_estrategica": (
            "História cultural = REPRESENTAÇÃO + DISPUTA + IDENTIDADE."
        ),
        "variacao": (
            "(Estilo INEP) Chartier (1990) define representações como:\n"
            "A) reflexos passivos da realidade;\n"
            "B) esquemas em disputa pela construção de sentido;\n"
            "C) apenas conteúdos científicos;\n"
            "D) apenas literatura. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Stuart Hall discute:\n"
            "A) identidade fixa; B) identidades culturais fluidas na pós-modernidade; C) neutralidade; D) essencialismo. → B",
            "Q2. Halbwachs cunhou o conceito de:\n"
            "A) memória coletiva; B) memória seletiva; C) memória de trabalho; D) memória sensorial. → A",
            "Q3. Pollak (1989) chama de 'memórias subterrâneas':\n"
            "A) as oficiais; B) as silenciadas pela memória hegemônica; C) as escritas; D) as institucionais. → B",
        ],
        "resumo": {
            "regra": "História cultural = representações em disputa + identidades.",
            "excecoes": "Fontes orais e escritas dialogam, não se hierarquizam.",
            "palavra_chave": "Representação + disputa + identidade.",
            "artigo": "CHARTIER (1990); HALL (2006); POLLAK (1989).",
            "mnemonico": "R.D.I.: Representação, Disputa, Identidade.",
        },
    },

    36: {
        "tema": "História oral como metodologia (Verena Alberti; Portelli)",
        "subtema": "Conflito, memória e temporalidades",
        "habilidade_bncc": "EF08HI27, EM13CHS603",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão de que a HISTÓRIA ORAL é uma metodologia que reconhece o conflito, a "
            "seletividade e a memória subjetiva como constitutivos das fontes. As demais alternativas "
            "desconhecem essa dimensão (busca de coincidência, oposição a audiovisual, subordinação ao "
            "documento escrito)."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão da história oral como METODOLOGIA CRÍTICA que trabalha COM o "
            "conflito e a subjetividade, e não CONTRA eles."
        ),
        "resolucao": [
            "Descarte A: 'coincidência' de relatos é ilusão positivista.",
            "Descarte B: recursos audiovisuais não distorcem — ampliam.",
            "Descarte C: relatos orais NÃO são complemento subalterno a documentos escritos.",
            "Marque D: conflito + memória = essência da história oral.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Buscar 'coincidência' contradiz a natureza plural da memória."),
            "B": ("ERRADA", "Audiovisual é linguagem legítima da história oral contemporânea."),
            "C": ("ERRADA", "Relatos orais são fontes com estatuto próprio, não meros complementos."),
            "D": ("CORRETA", "Relatos são atravessados por memórias, permitindo o trabalho com conflito e diferença."),
        },
        "fundamentacao": [
            "ALBERTI, Verena. Manual de história oral. 3. ed. Rio de Janeiro: FGV, 2005.",
            "PORTELLI, Alessandro. História oral como arte da escuta. São Paulo: Letra e Voz, 2016.",
            "MEIHY, José Carlos Sebe Bom. Manual de história oral. 5. ed. São Paulo: Loyola, 2005.",
            "POLLAK, Michael. Memória, esquecimento, silêncio. Estudos Históricos, v. 2, n. 3, 1989.",
        ],
        "teoria": (
            "A HISTÓRIA ORAL (Alberti, 2005; Meihy, 2005; Portelli, 2016) é metodologia de pesquisa e "
            "trabalho pedagógico que valoriza o testemunho, a subjetividade e a memória. Portelli enfatiza "
            "que a 'diferença' — os erros, os silêncios, os conflitos — é o que a torna epistemologicamente "
            "valiosa. Pollak (1989) discute memórias hegemônicas e subterrâneas. Na sala de aula, a "
            "história oral aproxima o passado da experiência dos estudantes e permite trabalhar temporalidades "
            "diversas."
        ),
        "padroes_banca": (
            "O INEP cobra ampliar a compreensão da história oral para além do 'reforço' do documento escrito."
        ),
        "pegadinhas": [
            "Buscar 'verdade única' nos relatos.",
            "Reduzir oral ao complemento do escrito.",
            "Negar valor a audiovisual.",
        ],
        "erros_comuns": (
            "Marcar C por hierarquizar documento escrito."
        ),
        "dica_estrategica": (
            "História oral = conflito + subjetividade + memória. Aceite o dissenso."
        ),
        "variacao": (
            "(Estilo INEP) Portelli (2016) define a história oral como 'arte da escuta' porque:\n"
            "A) prioriza a técnica de gravação;\n"
            "B) escuta atentamente a diferença, a dúvida e o silêncio como partes da memória;\n"
            "C) reduz o relato à transcrição literal;\n"
            "D) exclui a subjetividade. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Verena Alberti é referência para:\n"
            "A) história oral no Brasil; B) genealogia; C) numismática; D) paleografia. → A",
            "Q2. Meihy (2005) discute a história oral em modalidades:\n"
            "A) histórias de vida, temáticas e tradições orais; B) apenas biografias; "
            "C) apenas censo; D) apenas ficção. → A",
            "Q3. Pollak (1989) fala em memórias:\n"
            "A) hegemônicas e subterrâneas; B) simuladas e virtuais; C) tácitas e ostensivas; D) fixas e móveis. → A",
        ],
        "resumo": {
            "regra": "História oral trabalha com conflito e memória; não busca coincidência.",
            "excecoes": "Sistematização é válida, desde que respeite as versões.",
            "palavra_chave": "Conflito + memória + escuta.",
            "artigo": "ALBERTI (2005); PORTELLI (2016); MEIHY (2005).",
            "mnemonico": "3 T's da HO: Testemunho, Temporalidade, Trabalho com conflito.",
        },
    },

    37: {
        "tema": "Intolerância religiosa – história das religiões afro-brasileiras",
        "subtema": "Ensino de História e Lei 10.639/03",
        "habilidade_bncc": "EF08HI19, EM13CHS502; Lei 10.639/03",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a abordagem correta: apresentar a HISTÓRIA das religiões de matriz africana, o "
            "conceito de PLURALIDADE religiosa e a GARANTIA LEGAL de suas práticas. As demais são "
            "negacionistas (A), assimilacionistas (C) ou generalistas (D)."
        ),
        "como_banca_pensou": (
            "A banca contrapõe abordagem afirmativa (B) a três desvios: legalismo restritivo (A), "
            "assimilação ao cristianismo (C) e generalização do 'conflito religioso' (D)."
        ),
        "resolucao": [
            "Descarte A: 'sem respaldo legal' contradiz a Constituição.",
            "Descarte C: 'adequação a princípios cristãos' é assimilacionismo cristocêntrico.",
            "Descarte D: 'grupos extremistas' desloca o tema.",
            "Marque B: história + pluralidade + garantia legal.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Todas as religiões têm respaldo legal (CF/88, art. 5º, VI)."),
            "B": ("CORRETA", "História + pluralidade + garantia legal = abordagem afirmativa."),
            "C": ("ERRADA", "'Adequar-se a princípios cristãos' é cristocentrismo, contrário à liberdade religiosa."),
            "D": ("ERRADA", "Generaliza o tema para 'terrorismo', desviando do foco."),
        },
        "fundamentacao": [
            "BRASIL. Constituição da República Federativa do Brasil de 1988, art. 5º, VI e VIII.",
            "BRASIL. Lei nº 10.639, de 9 de janeiro de 2003.",
            "SILVA, Vagner Gonçalves da (org.). Intolerância religiosa: impactos do neopentecostalismo no campo religioso afro-brasileiro. São Paulo: Edusp, 2007.",
            "PRANDI, Reginaldo. Mitologia dos orixás. São Paulo: Companhia das Letras, 2001.",
        ],
        "teoria": (
            "As religiões de matriz africana (candomblé, umbanda, xambá etc.) integram a formação cultural "
            "brasileira desde o período colonial. A CF/88 garante liberdade religiosa. A Lei 10.639/03 "
            "obriga o ensino da história e cultura afro-brasileira. Silva (2007) e Prandi (2001) são "
            "referências centrais para o tema. Enfrentar a intolerância religiosa exige apresentação "
            "AFIRMATIVA e HISTORICAMENTE fundamentada."
        ),
        "padroes_banca": (
            "O INEP contrapõe abordagem afirmativa a assimilacionismos, cristocentrismos e legalismos."
        ),
        "pegadinhas": [
            "Aceitar 'religiões não oficiais' — todas são livres.",
            "Cristocentrismo disfarçado.",
            "Generalização para 'terrorismo'.",
        ],
        "erros_comuns": (
            "Marcar D por associar 'conflitos' a boa aula. Desloca o tema."
        ),
        "dica_estrategica": (
            "Prefira alternativas com HISTÓRIA + PLURALIDADE + LEGALIDADE + CRÍTICA CONTEXTUALIZADA."
        ),
        "variacao": (
            "(Estilo INEP) A Lei 10.639/03 tornou obrigatório o ensino de:\n"
            "A) apenas de ciências; B) História e Cultura Afro-Brasileira; "
            "C) apenas de sociologia; D) só de artes. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Prandi (2001) é referência para:\n"
            "A) mitologia dos orixás; B) astronomia; C) geologia; D) química. → A",
            "Q2. Vagner Silva (2007) analisa:\n"
            "A) intolerância religiosa e neopentecostalismo; B) história econômica; "
            "C) romantismo; D) sociologia rural. → A",
            "Q3. A CF/88, art. 5º, garante:\n"
            "A) monopólio católico; B) liberdade religiosa; C) proibição de manifestações; D) ateísmo. → B",
        ],
        "resumo": {
            "regra": "Religiões afro-brasileiras: história + pluralidade + legalidade.",
            "excecoes": "Ensino religioso é facultativo ao estudante.",
            "palavra_chave": "Pluralidade + garantia legal.",
            "artigo": "CF/88 art. 5º, VI; Lei 10.639/03.",
            "mnemonico": "H.P.L.: História, Pluralidade, Legalidade.",
        },
    },

    38: {
        "tema": "Ensino de história afro-brasileira – enfrentamento crítico ao racismo religioso",
        "subtema": "Lei 10.639/03 – interpretação correta",
        "habilidade_bncc": "EF08HI19, EM13CHS502",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a JUSTIFICATIVA correta para a inclusão da história afro-brasileira. A resposta é "
            "VALORIZAR + ENFRENTAR racismo e intolerância de forma CRÍTICA e CONTEXTUALIZADA. "
            "Alternativa A tem uma palavra-veneno ('superioridade cultural'); B afirma que intolerância "
            "seria 'liberdade de expressão' (falso); D propõe iniciar em práticas religiosas (fora do "
            "escopo escolar público)."
        ),
        "como_banca_pensou": (
            "O INEP quer que o candidato reconheça a Lei 10.639/03 como valorização E enfrentamento, "
            "sem essencialismo ('superioridade cultural')."
        ),
        "resolucao": [
            "Descarte A: 'afirmar sua superioridade cultural' é essencialismo invertido, incorreto.",
            "Descarte B: chamar intolerância de 'liberdade de expressão' é violação de direitos.",
            "Descarte D: 'iniciar em práticas religiosas' extrapola a escola pública laica.",
            "Marque C: valorizar + enfrentar de forma crítica e contextualizada.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "'Superioridade cultural' é essencialismo, não crítica antirracista."),
            "B": ("ERRADA", "Intolerância NÃO é liberdade de expressão — é violação de direitos."),
            "C": ("CORRETA", "Valorizar + enfrentar crítica e contextualizadamente = fórmula da Lei 10.639/03 e das DCN das relações étnico-raciais."),
            "D": ("ERRADA", "Escola pública laica não inicia estudantes em práticas religiosas."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 10.639, de 9 de janeiro de 2003.",
            "BRASIL. Conselho Nacional de Educação. Resolução CNE/CP nº 1, de 17 de junho de 2004.",
            "GOMES, Nilma Lino. O movimento negro educador. Petrópolis: Vozes, 2017.",
            "MUNANGA, Kabengele. Superando o racismo na escola. 2. ed. Brasília: MEC/SECAD, 2005.",
        ],
        "teoria": (
            "A Lei 10.639/03 e a Res. CNE/CP 1/2004 estabelecem o ensino de História e Cultura "
            "Afro-Brasileira e Africana como política antirracista. Não se trata de 'inverter' hierarquias "
            "(afirmar superioridade), mas de VALORIZAR e ENFRENTAR o racismo. Munanga (2005) e Gomes "
            "(2017) sistematizam a política."
        ),
        "padroes_banca": (
            "O INEP costuma cobrar interpretações precisas de leis. Cuidado com 'superioridade'."
        ),
        "pegadinhas": [
            "Confundir enfrentamento com essencialismo invertido.",
            "Legitimar intolerância como 'liberdade'.",
            "Achar que escola pública faz iniciação religiosa.",
        ],
        "erros_comuns": (
            "Marcar A por parecer 'valorizar de mais' — mas 'superioridade' é palavra-veneno."
        ),
        "dica_estrategica": (
            "Antirracismo = valorização + enfrentamento crítico. Nunca hierarquia invertida."
        ),
        "variacao": (
            "(Estilo INEP) A Res. CNE/CP nº 1/2004 institui:\n"
            "A) BNCC; B) DCN Relações Étnico-Raciais; C) DCN Educação Infantil; D) LDB. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Lei 10.639/03 se articula com a:\n"
            "A) Lei 11.645/08 (história indígena); B) Lei 12.711/12; C) Lei 13.146/15; D) Lei 9.394/96. → A",
            "Q2. Munanga (2005) organiza o tema:\n"
            "A) superando o racismo na escola; B) história agrária; C) demografia; D) ecologia. → A",
            "Q3. Antirracismo pedagógico exige:\n"
            "A) hierarquizar culturas; B) valorizar e enfrentar racismo contextualizadamente; "
            "C) invisibilizar diferenças; D) neutralidade absoluta. → B",
        ],
        "resumo": {
            "regra": "Antirracismo = valorização crítica + enfrentamento contextualizado.",
            "excecoes": "Nenhuma cultura é superior; todas são diversas e dialogam.",
            "palavra_chave": "Valorizar + enfrentar + crítica.",
            "artigo": "Lei 10.639/03; Res. CNE/CP 1/2004.",
            "mnemonico": "VEC: Valorizar + Enfrentar + Contextualizar.",
        },
    },

    39: {
        "tema": "Ditaduras do Cone Sul e interdisciplinaridade",
        "subtema": "Operação Condor, resistência e direitos humanos",
        "habilidade_bncc": "EF09HI24, EM13CHS604",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Prática (interdisciplinar)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra articulação com outra disciplina e um objetivo COERENTE com os textos-base "
            "(Argentina + Chile, avós, netos sequestrados, golpe de 1973). A resposta é LÍNGUA "
            "PORTUGUESA + identificar repressão e violação de direitos humanos (leitura crítica de "
            "reportagens)."
        ),
        "como_banca_pensou": (
            "A banca contrapõe integrações plausíveis: Filosofia + resistência (B), Sociologia + grupos "
            "opositores violentos (C, formulação problemática), Geografia + instituições antidemocráticas "
            "(D, incoerente). A única articulação PLENAMENTE coerente com o material (reportagens) e o "
            "objetivo (identificar violações) é A."
        ),
        "resolucao": [
            "Reconheça: textos são REPORTAGENS jornalísticas.",
            "Descarte B: 'protestos pacíficos' é reducionista.",
            "Descarte C: 'grupos opositores violentos' distorce (a violência vinha do Estado).",
            "Descarte D: 'Geografia + instituições antidemocráticas' não é articulação coerente.",
            "Marque A: LP + identificar repressão e violação de DH.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Leitura crítica das reportagens (LP) + identificação da repressão e das violações de DH."),
            "B": ("ERRADA", "Reduz a resistência a 'protestos pacíficos', apagando a complexidade."),
            "C": ("ERRADA", "Sugere que os opositores eram os violentos — inversão histórica."),
            "D": ("ERRADA", "Geografia não é o campo mais coerente para o material dado."),
        },
        "fundamentacao": [
            "COMISSÃO NACIONAL DA VERDADE. Relatório – Vol. I. Brasília, 2014.",
            "PADRÓS, Enrique Serra. Como el Uruguay no hay… Terror de Estado e segurança nacional. Porto Alegre: UFRGS, 2005.",
            "DINGES, John. Os anos do Condor: uma década de terrorismo internacional no Cone Sul. São Paulo: Cia. das Letras, 2005.",
            "BRASIL. Lei nº 12.528, de 18 de novembro de 2011. Cria a Comissão Nacional da Verdade.",
        ],
        "teoria": (
            "A OPERAÇÃO CONDOR (1975-1983) foi uma articulação entre as ditaduras militares de Argentina, "
            "Chile, Brasil, Bolívia, Paraguai e Uruguai para reprimir opositores, com desaparecimentos, "
            "torturas e sequestros de crianças. Dinges (2005) e Padrós (2005) são referências. O trabalho "
            "das Avós da Praça de Maio, na Argentina, é exemplo de resistência memorial e jurídica. "
            "Trabalhar essas ditaduras interdisciplinarmente amplia a compreensão do autoritarismo latino-"
            "americano."
        ),
        "padroes_banca": (
            "O INEP contrapõe objetivo coerente + disciplina adequada. Palavras-veneno: 'protestos pacíficos', "
            "'grupos opositores violentos', 'apenas geografia'."
        ),
        "pegadinhas": [
            "Reduzir resistência a 'protestos pacíficos'.",
            "Inverter quem exerce a violência.",
            "Escolher disciplina desconectada do material.",
        ],
        "erros_comuns": (
            "Marcar B por 'protestos' soar bom — mas apaga a repressão."
        ),
        "dica_estrategica": (
            "Interdisciplinaridade só funciona se disciplina e objetivo forem coerentes com a fonte."
        ),
        "variacao": (
            "(Estilo INEP) A Operação Condor articulou-se com o objetivo de:\n"
            "A) integrar economicamente o Mercosul; B) reprimir opositores das ditaduras do Cone Sul; "
            "C) financiar a educação; D) proteger direitos humanos. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Salvador Allende foi deposto em:\n"
            "A) 11 set. 1973; B) 1 abr. 1964; C) 24 mar. 1976; D) 1985. → A",
            "Q2. As Avós da Praça de Maio buscam:\n"
            "A) netos sequestrados durante a ditadura argentina; B) prêmios literários; "
            "C) medalhas olímpicas; D) reforma agrária. → A",
            "Q3. A CNV foi criada pela:\n"
            "A) Lei 12.528/2011; B) Lei 8.069/90; C) Lei 9.394/96; D) Lei 6.815/80. → A",
        ],
        "resumo": {
            "regra": "Ditaduras do Cone Sul = repressão estatal, resistência plural, direitos humanos.",
            "excecoes": "Cada país teve dinâmicas próprias, mas articuladas pela Condor.",
            "palavra_chave": "Repressão + violação de DH.",
            "artigo": "Lei 12.528/2011; CNV (2014).",
            "mnemonico": "CONDOR: Coordenação Repressiva Internacional do Cone Sul.",
        },
    },

    40: {
        "tema": "Exílio e resistência no Cone Sul – 'O Eternauta'",
        "subtema": "Quadrinhos, memória e solidariedade internacional",
        "habilidade_bncc": "EF09HI24, EF08HI19",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra atividade coerente com o tema (exílio e resistência às ditaduras). A alternativa "
            "C — cartas simulando exílio político — é a única que preserva o teor histórico. As demais "
            "são deslocamentos: migração voluntária (A), turismo (B), remoção urbana ambiental (D)."
        ),
        "como_banca_pensou": (
            "A banca contrapõe abordagem histórica correta a distorções banalizantes (turismo, migração "
            "voluntária)."
        ),
        "resolucao": [
            "Reconheça: tema é EXÍLIO POLÍTICO e RESISTÊNCIA às ditaduras.",
            "Descarte A: migração voluntária por emprego apaga o exílio.",
            "Descarte B: turismo é banalização.",
            "Descarte D: remoção urbana ambiental não é exílio político.",
            "Marque C: cartas simulando comunicação entre exilados e familiares.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Migração voluntária não é exílio político."),
            "B": ("ERRADA", "Turismo banaliza o tema."),
            "C": ("CORRETA", "Cartas de exilados = atividade coerente com o teor histórico."),
            "D": ("ERRADA", "'Remoção urbana ambiental' não é o tema."),
        },
        "fundamentacao": [
            "OESTERHELD, Héctor G. O eternauta. São Paulo: Martins Fontes, 2011.",
            "SEIXAS, Ivan et al. Exílio e retorno: brasileiros na diáspora política. São Paulo: Intermeios, 2018.",
            "ROLLEMBERG, Denise. Exílio: entre raízes e radares. Rio de Janeiro: Record, 1999.",
            "CNV. Relatório. Brasília, 2014.",
        ],
        "teoria": (
            "O EXÍLIO foi experiência coletiva de milhares de latino-americanos durante as ditaduras. "
            "Rollemberg (1999) sistematiza o exílio brasileiro. 'O Eternauta' de Héctor Oesterheld (autor "
            "argentino desaparecido pela ditadura) é HQ de ficção científica que se tornou metáfora "
            "poderosa da resistência. Trabalhar cartas simuladas mobiliza empatia histórica (Rüsen) e "
            "consciência dos direitos humanos."
        ),
        "padroes_banca": (
            "O INEP contrasta abordagem histórico-crítica a banalizações."
        ),
        "pegadinhas": [
            "Confundir exílio com migração voluntária.",
            "Banalizar com turismo.",
            "Reduzir a política habitacional.",
        ],
        "erros_comuns": (
            "Marcar A por 'migrante sul-americano' — mas é exílio POLÍTICO."
        ),
        "dica_estrategica": (
            "Em atividade sobre ditaduras, preserve a especificidade política do tema."
        ),
        "variacao": (
            "(Estilo INEP) Héctor Oesterheld, autor de 'O Eternauta':\n"
            "A) foi vítima da ditadura argentina; B) morreu de causas naturais em 2000; "
            "C) fugiu para os EUA; D) apoiou Videla. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Rollemberg (1999) discute:\n"
            "A) exílio brasileiro; B) demografia; C) micro-história; D) numismática. → A",
            "Q2. Empatia histórica (Rüsen) refere-se a:\n"
            "A) memorização; B) imaginar a experiência do outro no passado; "
            "C) transcrever fontes; D) datar eventos. → B",
            "Q3. HQs podem ser fontes históricas legítimas?\n"
            "A) Não; B) Sim, tratadas criticamente; C) Só se documentárias; D) Só em ciências. → B",
        ],
        "resumo": {
            "regra": "Trabalhar exílio = experiências políticas concretas.",
            "excecoes": "Migrações econômicas coexistem, mas não são exílio político.",
            "palavra_chave": "Exílio + solidariedade internacional.",
            "artigo": "ROLLEMBERG (1999); CNV (2014).",
            "mnemonico": "E.S.I.: Exílio, Solidariedade, Internacionalismo.",
        },
    },

    41: {
        "tema": "Metodologia iconográfica no ensino de História",
        "subtema": "Mulheres nas cidades medievais + fonte imagética",
        "habilidade_bncc": "EF06HI11, EM13CHS101",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média",
        "justificativa_classificacao": (
            "Item cobra a metodologia adequada para articular fonte imagética + texto acadêmico. A "
            "metodologia é ICONOGRÁFICA (análise da imagem em seu contexto histórico). Distratores usam "
            "termos aproximados (semiótica, formalista, comparativa) que não descrevem exatamente o "
            "trabalho pedagógico proposto."
        ),
        "como_banca_pensou": (
            "A banca cobra o vocabulário técnico da didática da História. A iconografia (Panofsky) "
            "analisa imagens historicamente, discernindo temas, motivos e significados."
        ),
        "resolucao": [
            "Reconheça: fonte imagética + texto acadêmico para desmontar estereótipo.",
            "Descarte A: 'semiótica' analisa signos, mas não é o termo usual na didática da História para esse par.",
            "Descarte C: 'formalista' foca na forma; não é o caso.",
            "Descarte D: 'comparativa' pressupõe comparação entre duas fontes semelhantes.",
            "Marque B: iconográfica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Semiótica é campo mais amplo de análise de signos, não a metodologia específica aqui."),
            "B": ("CORRETA", "A metodologia iconográfica analisa imagens em seu contexto histórico (Panofsky, Burke)."),
            "C": ("ERRADA", "Formalismo foca em forma, não em significado histórico."),
            "D": ("ERRADA", "Comparativa exige duas fontes de mesma natureza."),
        },
        "fundamentacao": [
            "PANOFSKY, Erwin. Significado nas artes visuais. São Paulo: Perspectiva, 1979.",
            "BURKE, Peter. Testemunha ocular: história e imagem. Bauru: EDUSC, 2004.",
            "FEDERICI, Silvia. Calibã e a bruxa: mulheres, corpo e acumulação primitiva. São Paulo: Elefante, 2017.",
            "BITTENCOURT, Circe. Ensino de história: fundamentos e métodos. 4. ed. São Paulo: Cortez, 2011.",
        ],
        "teoria": (
            "ICONOGRAFIA (Panofsky, 1979) é o estudo de temas, motivos e significados nas imagens; "
            "ICONOLOGIA vai além, articulando a imagem ao contexto cultural. Burke (2004) discute como "
            "usar imagens como fonte histórica. Bittencourt (2011) trata da didática das fontes no ensino "
            "de História. Federici (2017) desmonta o mito das mulheres medievais restritas ao espaço "
            "privado — no ensino, isso combate estereótipos de gênero."
        ),
        "padroes_banca": (
            "O INEP cobra vocabulário técnico da didática."
        ),
        "pegadinhas": [
            "Confundir iconografia com semiótica.",
            "Achar que 'comparativa' basta.",
            "Reduzir imagem à ilustração.",
        ],
        "erros_comuns": (
            "Marcar A por 'semiótica' soar erudito."
        ),
        "dica_estrategica": (
            "Imagem + contexto histórico = iconografia."
        ),
        "variacao": (
            "(Estilo INEP) Panofsky (1979) sistematiza a análise de imagens em três níveis:\n"
            "A) sintático, semântico, pragmático;\n"
            "B) pré-iconográfico, iconográfico, iconológico;\n"
            "C) estético, ético, epistemológico;\n"
            "D) formal, funcional, ideológico. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Peter Burke, em 'Testemunha ocular' (2004), discute:\n"
            "A) fontes hemerográficas; B) imagens como fontes históricas; "
            "C) fontes orais; D) fontes materiais. → B",
            "Q2. Federici (2017) recusa o mito de:\n"
            "A) idade média = domesticidade feminina absoluta; B) burguesia como classe hegemônica; "
            "C) Renascimento como neutro; D) Iluminismo como emancipatório. → A",
            "Q3. Bittencourt (2011) trata de:\n"
            "A) matemática; B) ensino de história; C) química; D) genética. → B",
        ],
        "resumo": {
            "regra": "Iconografia = análise de imagem histórica em contexto.",
            "excecoes": "Iconologia estende para o contexto cultural amplo.",
            "palavra_chave": "Imagem + significado histórico.",
            "artigo": "PANOFSKY (1979); BURKE (2004).",
            "mnemonico": "I.I.I.: Imagem + Ícone + Interpretação.",
        },
    },

    42: {
        "tema": "Reforma Protestante e Contrarreforma",
        "subtema": "Século XVI - convulsões religiosas",
        "habilidade_bncc": "EF07HI06, EM13CHS502",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "O massacre da noite de São Bartolomeu (1572) e a caça às bruxas na Europa moderna remetem "
            "à REFORMA PROTESTANTE e CONTRARREFORMA — momentos de intensa disputa religiosa. As demais "
            "opções deslocam para períodos ou fenômenos diversos."
        ),
        "como_banca_pensou": (
            "A banca pede reconhecimento cronológico + temático das obras. Distratores oferecem eventos "
            "de outros períodos (Cruzadas medievais, Revolução Gloriosa, Revoltas Camponesas medievais)."
        ),
        "resolucao": [
            "Fixe: massacre de São Bartolomeu (1572, católicos vs. protestantes) + queima de menonita (Reforma radical) = século XVI.",
            "Descarte B: Inquisição e revoltas camponesas são amplos; a expressão específica é Reforma + Contrarreforma.",
            "Descarte C: Cruzadas e Guerra dos Cem Anos são medievais.",
            "Descarte D: Revolução Gloriosa (1688) é séc. XVII.",
            "Marque A: Reforma Protestante + Contrarreforma.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Reforma + Contrarreforma explicam o massacre de S. Bartolomeu (1572) e a repressão a menonitas."),
            "B": ("ERRADA", "Inquisição é permanente e mais ampla; a expressão específica é Reforma."),
            "C": ("ERRADA", "Cruzadas (séc. XI-XIII) e Guerras dos Cem Anos/das Rosas são medievais."),
            "D": ("ERRADA", "Revolução Gloriosa é 1688; deslocamento cronológico."),
        },
        "fundamentacao": [
            "DELUMEAU, Jean. Nascimento e afirmação da Reforma. São Paulo: Pioneira, 1989.",
            "DELUMEAU, Jean. História do medo no Ocidente 1300-1800. São Paulo: Cia. das Letras, 1989.",
            "GINZBURG, Carlo. O queijo e os vermes. São Paulo: Cia. das Letras, 1987.",
            "HILL, Christopher. O mundo de ponta-cabeça. São Paulo: Cia. das Letras, 1987.",
        ],
        "teoria": (
            "A REFORMA PROTESTANTE (1517, Lutero) e a CONTRARREFORMA CATÓLICA (Concílio de Trento, "
            "1545-1563) reconfiguraram a Europa, gerando GUERRAS DE RELIGIÃO (França 1562-1598, "
            "Alemanha 1618-1648). O massacre da noite de São Bartolomeu (24 ago. 1572, Paris) é episódio "
            "emblemático. Delumeau (1989) e Ginzburg (1987) são referências. Federici (2017) enfatiza "
            "que a caça às bruxas foi paralela à acumulação primitiva."
        ),
        "padroes_banca": (
            "O INEP cobra cronologia e articulação temática de eventos europeus."
        ),
        "pegadinhas": [
            "Confundir Reforma com Cruzadas.",
            "Colocar Revolução Gloriosa no séc. XVI.",
            "Reduzir a 'Inquisição' amplamente.",
        ],
        "erros_comuns": (
            "Marcar B por associar Inquisição a caça às bruxas — a chave é o CONTEXTO reformista."
        ),
        "dica_estrategica": (
            "Reforma + Contrarreforma = eixo dos conflitos religiosos do séc. XVI."
        ),
        "variacao": (
            "(Estilo INEP) O Concílio de Trento (1545-1563):\n"
            "A) fundou o protestantismo; B) foi resposta contrarreformista da Igreja Católica; "
            "C) aboliu a Inquisição; D) definiu a Reforma Anglicana. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Paz de Augsburgo (1555) reconheceu:\n"
            "A) cuius regio, eius religio; B) tolerância universal; "
            "C) apenas catolicismo; D) só protestantismo. → A",
            "Q2. O massacre de S. Bartolomeu ocorreu em:\n"
            "A) Paris, 1572; B) Roma, 1500; C) Genebra, 1600; D) Wittenberg, 1517. → A",
            "Q3. Ginzburg (1987) analisa em 'O queijo e os vermes':\n"
            "A) inquisição e cultura popular; B) direito romano; C) economia industrial; D) arte moderna. → A",
        ],
        "resumo": {
            "regra": "Séc. XVI = Reforma + Contrarreforma + guerras religiosas.",
            "excecoes": "Diferentes reformas (luterana, calvinista, anglicana, radical) coexistem.",
            "palavra_chave": "Reforma + Contrarreforma.",
            "artigo": "DELUMEAU (1989); GINZBURG (1987).",
            "mnemonico": "RC-XVI: Reforma-Contrarreforma no século XVI.",
        },
    },

    43: {
        "tema": "Reforma Protestante - propaganda visual (Contrarreforma)",
        "subtema": "Imagem como arma na disputa religiosa",
        "habilidade_bncc": "EF07HI06, EM13CHS502",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa (iconográfica)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média-alta",
        "justificativa_classificacao": (
            "Gravura de Leipzig, 1535, associando Lutero ao diabo — típica DEMONIZAÇÃO produzida pela "
            "Contrarreforma. Alternativas erradas invertem (A: exaltação), suavizam (C: humanismo) ou "
            "descontextualizam (D: sem clero)."
        ),
        "como_banca_pensou": (
            "A banca cobra leitura iconográfica em contexto: quem produz a imagem e para quê. Uma "
            "imagem que associa Lutero ao diabo só faz sentido como propaganda contrarreformista."
        ),
        "resolucao": [
            "Descarte A: exaltar Lutero ao lado do diabo é contraditório para os luteranos.",
            "Descarte C: humanismo renascentista não usaria demonização.",
            "Descarte D: 'sem envolvimento eclesiástico' é falso; a produção é clerical.",
            "Marque B: demonização produzida pela Contrarreforma.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Lutero ao lado do diabo não pode ser exaltação."),
            "B": ("CORRETA", "Demonização típica da propaganda visual contrarreformista."),
            "C": ("ERRADA", "Humanismo renascentista não usaria essa forma de propaganda."),
            "D": ("ERRADA", "Reforma teve intensa disputa eclesiástica; nunca 'sem clero'."),
        },
        "fundamentacao": [
            "SCRIBNER, Robert. For the Sake of Simple Folk: Popular Propaganda for the German Reformation. Cambridge: Cambridge University Press, 1981.",
            "BURKE, Peter. Testemunha ocular. Bauru: EDUSC, 2004.",
            "DELUMEAU, Jean. História do medo no Ocidente. São Paulo: Cia. das Letras, 1989.",
        ],
        "teoria": (
            "A propaganda visual foi arma central da disputa Reforma/Contrarreforma. Scribner (1981) "
            "mostra como panfletos e gravuras alcançavam a população não alfabetizada. Ambos os lados "
            "produziram demonizações — Lutero como diabo, Papa como Anticristo — em técnicas iconográficas "
            "sofisticadas."
        ),
        "padroes_banca": (
            "O INEP cobra leitura contextualizada de imagens do período."
        ),
        "pegadinhas": [
            "Interpretar Lutero+diabo como exaltação.",
            "Achar que humanismo produz demonização.",
            "Suprimir o clero na Reforma.",
        ],
        "erros_comuns": (
            "Marcar A por lembrar de Lutero como reformador virtuoso."
        ),
        "dica_estrategica": (
            "Sempre leia a imagem em seu CONTEXTO de produção e RECEPÇÃO."
        ),
        "variacao": (
            "(Estilo INEP) Panfletos com o Papa como Anticristo eram propaganda:\n"
            "A) contrarreformista; B) protestante; C) humanista renascentista; D) medieval. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Scribner (1981) discute:\n"
            "A) propaganda popular da Reforma; B) economia agrária; C) Renascimento italiano; D) filologia. → A",
            "Q2. A imprensa de Gutenberg foi decisiva para:\n"
            "A) Reforma; B) Cruzadas; C) Feudalismo; D) Revolução Industrial imediata. → A",
            "Q3. Concílio de Trento (1545-63):\n"
            "A) fundou o protestantismo; B) reafirmou dogmas católicos + arte contrarreformista; "
            "C) integrou luteranos; D) instituiu a Inquisição pela primeira vez. → B",
        ],
        "resumo": {
            "regra": "Imagens do séc. XVI = arma na disputa Reforma/Contrarreforma.",
            "excecoes": "Nem toda imagem religiosa é propaganda direta.",
            "palavra_chave": "Demonização + propaganda visual.",
            "artigo": "SCRIBNER (1981); BURKE (2004).",
            "mnemonico": "P.C.R.: Propaganda + Contrarreforma + (D)emonização.",
        },
    },

    44: {
        "tema": "História local e literatura",
        "subtema": "Cora Coralina, cidade e história",
        "habilidade_bncc": "EF07HI17, EF09HI22, EM13CHS502",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média-alta",
        "justificativa_classificacao": (
            "Item cobra a articulação HISTÓRIA LOCAL + LITERATURA como forma de despertar SENSO "
            "INVESTIGATIVO e articular local/global. A alternativa D é a única que expressa essa "
            "perspectiva; A subordina à historiografia oficial, B restringe a grandes cidades, C submete "
            "à história política tradicional."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão de HISTÓRIA LOCAL como caminho didático — não como "
            "subalternidade à historiografia consagrada."
        ),
        "resolucao": [
            "Descarte A: 'para dar validade ao trabalho' subordina à historiografia oficial.",
            "Descarte B: 'grandes cidades' contradiz a proposta local.",
            "Descarte C: 'convenções tradicionais' contradiz a inovação didática.",
            "Marque D: local e geral se aproximam e distanciam = história local como investigação.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Subordina o local à historiografia consagrada."),
            "B": ("ERRADA", "Recorta a análise a grandes cidades."),
            "C": ("ERRADA", "Submissão à história política tradicional."),
            "D": ("CORRETA", "Articula local e global; desperta senso investigativo — coerente com a proposta de história local."),
        },
        "fundamentacao": [
            "SAMUEL, Raphael. Historia popular y teoria socialista. Barcelona: Crítica, 1984.",
            "SCHMIDT, Maria Auxiliadora; CAINELLI, Marlene. Ensinar História. 3. ed. São Paulo: Scipione, 2018.",
            "CORA CORALINA. Poemas dos becos de Goiás e estórias mais. São Paulo: Global, 2006.",
            "BURKE, Peter. A escrita da história: novas perspectivas. São Paulo: UNESP, 1992.",
        ],
        "teoria": (
            "A HISTÓRIA LOCAL, herdeira da micro-história italiana (Ginzburg, Levi) e da história vista "
            "de baixo (Thompson, Samuel), articula local e global sem hierarquia. Schmidt & Cainelli "
            "(2018) sistematizam sua didática. A literatura é fonte legítima para o ensino de História, "
            "especialmente para trabalhar sensibilidades e representações do cotidiano."
        ),
        "padroes_banca": (
            "O INEP prefere alternativas que ARTICULAM local/global e valorizam senso investigativo."
        ),
        "pegadinhas": [
            "Subordinar local ao geral.",
            "Restringir a 'grandes cidades'.",
            "Ver literatura como fonte 'inferior'.",
        ],
        "erros_comuns": (
            "Marcar A por parecer que 'inscrever na historiografia' é bom."
        ),
        "dica_estrategica": (
            "História local = articulação, não subordinação. Preserve o investigativo."
        ),
        "variacao": (
            "(Estilo INEP) Schmidt & Cainelli (2018) associam ensino de História local a:\n"
            "A) memorização de datas; B) investigação, patrimônio e articulação com o global; "
            "C) história política clássica; D) crítica ao trabalho docente. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Ginzburg praticou:\n"
            "A) micro-história italiana; B) macro-história econômica; C) sociologia rural; D) filosofia analítica. → A",
            "Q2. Cora Coralina, poeta goiana, tematiza:\n"
            "A) o cotidiano e as ruas de Goiás; B) o cotidiano de Nova York; C) as guerras mundiais; D) a alta política brasileira. → A",
            "Q3. Literatura como fonte histórica:\n"
            "A) é ilegítima; B) exige leitura crítica dos contextos; C) só serve à filologia; D) é neutra. → B",
        ],
        "resumo": {
            "regra": "História local articula local e global sem hierarquia.",
            "excecoes": "Não confundir com 'localismo' isolacionista.",
            "palavra_chave": "Investigação + articulação local/global.",
            "artigo": "SCHMIDT & CAINELLI (2018); BURKE (1992).",
            "mnemonico": "L.G.I.: Local, Global, Investigativo.",
        },
    },

    45: {
        "tema": "Avaliação diagnóstica em ensino de História",
        "subtema": "Representações prévias dos estudantes",
        "habilidade_bncc": "EM13CHS603; DCN Formação Docente",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a definição de AVALIAÇÃO DIAGNÓSTICA: estudante expõe suas representações "
            "PRÉVIAS antes do início do conteúdo. As demais confundem com somativa (A: 'final do "
            "semestre'), com intervenção ideológica (B), ou com processual (C)."
        ),
        "como_banca_pensou": (
            "A banca cobra a definição clássica de diagnóstica: OCORRE ANTES do conteúdo; identifica "
            "representações e conhecimentos prévios."
        ),
        "resolucao": [
            "Descarte A: 'ao final do semestre' = somativa.",
            "Descarte B: 'alterar discurso ideológico' + 'promover notas depois' = confuso e somativo.",
            "Descarte C: 'durante o processo' = formativa.",
            "Marque D: expor representações prévias ANTES do próximo conteúdo = diagnóstica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "'Final do semestre' caracteriza avaliação somativa."),
            "B": ("ERRADA", "Formulação confusa; sugere manipulação ideológica."),
            "C": ("ERRADA", "'Durante o processo' é formativa, não diagnóstica."),
            "D": ("CORRETA", "Estudante expõe representações prévias ANTES do conteúdo → diagnóstica."),
        },
        "fundamentacao": [
            "PERRENOUD, Philippe. Avaliação: da excelência à regulação. Porto Alegre: Artmed, 1999.",
            "HOFFMANN, Jussara. Avaliação mediadora. Porto Alegre: Mediação, 2014.",
            "LUCKESI, Cipriano C. Avaliação da aprendizagem escolar. São Paulo: Cortez, 2011.",
            "SCHMIDT, Maria Auxiliadora; CAINELLI, Marlene. Ensinar História. São Paulo: Scipione, 2018.",
        ],
        "teoria": (
            "AVALIAÇÃO DIAGNÓSTICA identifica conhecimentos e representações prévias, orientando o "
            "planejamento antes do conteúdo. No ensino de História, Schmidt & Cainelli (2018) enfatizam a "
            "importância das PROTONARRATIVAS dos estudantes — narrativas iniciais sobre o passado — como "
            "ponto de partida para a construção do conhecimento histórico."
        ),
        "padroes_banca": (
            "O INEP cobra a tríade DIAGNÓSTICA-FORMATIVA-SOMATIVA em múltiplos contextos."
        ),
        "pegadinhas": [
            "Confundir diagnóstica com formativa.",
            "Confundir diagnóstica com somativa.",
            "Interpretar diagnóstica como manipulação ideológica.",
        ],
        "erros_comuns": (
            "Marcar C por 'representações' aparecerem na formativa também — mas 'durante' é palavra-chave."
        ),
        "dica_estrategica": (
            "Diagnóstica = ANTES. Formativa = DURANTE. Somativa = DEPOIS."
        ),
        "variacao": (
            "(Estilo INEP) Protonarrativas históricas (Rüsen; Schmidt) referem-se a:\n"
            "A) narrativas dos historiadores profissionais;\n"
            "B) narrativas iniciais dos estudantes sobre o passado;\n"
            "C) documentários; D) ficção histórica. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Avaliação somativa:\n"
            "A) identifica prévios; B) regula o processo; C) certifica ao final; D) constrói ativa. → C",
            "Q2. Schmidt & Cainelli (2018) valorizam:\n"
            "A) apenas memorização de datas; B) protonarrativas e consciência histórica; "
            "C) só livros didáticos; D) apenas efemérides. → B",
            "Q3. Jussara Hoffmann chama a formativa de:\n"
            "A) classificatória; B) mediadora; C) supletiva; D) somativa disfarçada. → B",
        ],
        "resumo": {
            "regra": "Diagnóstica = expor representações prévias ANTES do conteúdo.",
            "excecoes": "Instrumentos podem coincidir (rodas, questionários); a intenção define a função.",
            "palavra_chave": "Prévios + representações.",
            "artigo": "PERRENOUD (1999); SCHMIDT & CAINELLI (2018).",
            "mnemonico": "Diagnóstica = DEC (Descubro o que Eu Conheço) antes do C (Conteúdo).",
        },
    },

    46: {
        "tema": "Vygotsky, patrimônio e formação de conceitos",
        "subtema": "Conceitos espontâneos e científicos",
        "habilidade_bncc": "EF06HI04, EM13CHS502",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão dos conceitos de VYGOTSKY: aquisição SOCIAL dos conceitos + "
            "distinção entre conceitos ESPONTÂNEOS e CIENTÍFICOS, que se interferem MUTUAMENTE. As "
            "demais opções ora individualizam (A), ora naturalizam biologicamente (D), ora hipostasiam "
            "tradição (C)."
        ),
        "como_banca_pensou": (
            "A banca combina Vygotsky com patrimônio: os conceitos culturais são construídos socialmente. "
            "A resposta afirma diretamente essa perspectiva."
        ),
        "resolucao": [
            "Descarte A: 'apenas individualmente' contradiz Vygotsky.",
            "Descarte C: 'distante das influências emocionais' apaga a dimensão afetiva.",
            "Descarte D: 'aspecto biológico é mais decisivo' inverte a chave sociocultural.",
            "Marque B: aquisição social + estágios + espontâneos/científicos.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Vygotsky é sociointeracionista; conceitos não são apenas individuais."),
            "B": ("CORRETA", "Aquisição social + espontâneos vs. científicos = Vygotsky."),
            "C": ("ERRADA", "Vygotsky não separa cognição de emoção."),
            "D": ("ERRADA", "Prevalência do biológico é piagetiano/inatista, não vygotskyano."),
        },
        "fundamentacao": [
            "VYGOTSKY, Lev S. Pensamento e linguagem. São Paulo: Martins Fontes, 2008.",
            "VYGOTSKY, Lev S. A formação social da mente. São Paulo: Martins Fontes, 1991.",
            "OLIVEIRA, Marta Kohl. Vygotsky: aprendizado e desenvolvimento – um processo sócio-histórico. São Paulo: Scipione, 2010.",
            "BRASIL. Ministério da Educação. Base Nacional Comum Curricular. Brasília: MEC, 2018.",
        ],
        "teoria": (
            "Vygotsky (2008) distingue CONCEITOS ESPONTÂNEOS (formados na experiência cotidiana) e "
            "CONCEITOS CIENTÍFICOS (mediados pela escola e pela linguagem sistematizada), que se "
            "interferem MUTUAMENTE. A Zona de Desenvolvimento Proximal (ZDP) descreve a distância entre "
            "o que o sujeito faz sozinho e o que faz com mediação. Aprendizagem PRECEDE desenvolvimento. "
            "Aplicado ao ensino de História: partir dos conceitos espontâneos dos estudantes sobre "
            "patrimônio para construir conceitos científicos (patrimônio material, imaterial, natural, "
            "digital)."
        ),
        "padroes_banca": (
            "O INEP cobra pareamento entre teoria (autor) e prática pedagógica."
        ),
        "pegadinhas": [
            "Confundir Vygotsky com Piaget (biologia).",
            "Isolar cognição de afetividade.",
            "Individualizar a construção conceitual.",
        ],
        "erros_comuns": (
            "Marcar D por associar 'desenvolvimento' a biologia."
        ),
        "dica_estrategica": (
            "Vygotsky = mediação + ZDP + espontâneos/científicos."
        ),
        "variacao": (
            "(Estilo INEP) Segundo Vygotsky, aprendizagem e desenvolvimento estão em qual relação?\n"
            "A) desenvolvimento precede aprendizagem;\n"
            "B) aprendizagem impulsiona o desenvolvimento;\n"
            "C) são independentes; D) são idênticos. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A ZDP se define como:\n"
            "A) o que a criança faz sozinha; B) a distância entre o real e o potencial mediado; "
            "C) memorização; D) instinto. → B",
            "Q2. Conceitos espontâneos em Vygotsky:\n"
            "A) surgem na experiência cotidiana; B) são inatos; "
            "C) são apenas escolares; D) inexistem. → A",
            "Q3. Marta Kohl de Oliveira é referência para:\n"
            "A) Vygotsky no Brasil; B) Piaget; C) Skinner; D) Freud. → A",
        ],
        "resumo": {
            "regra": "Vygotsky = aquisição social + espontâneos vs. científicos + ZDP.",
            "excecoes": "Biologia importa, mas não é fator PRINCIPAL.",
            "palavra_chave": "Social + mediação + científicos vs. espontâneos.",
            "artigo": "VYGOTSKY (2008; 1991); OLIVEIRA (2010).",
            "mnemonico": "Z.S.E.C.: ZDP, Social, Espontâneos vs. Científicos.",
        },
    },

    47: {
        "tema": "Patrimônio imaterial – arte Kusiwa Wajãpi",
        "subtema": "Patrimônio como manifestação dinâmica do cotidiano",
        "habilidade_bncc": "EF06HI08, EM13CHS502; Res. IPHAN 001/2006",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "O item cobra a noção de PATRIMÔNIO IMATERIAL como manifestação DINÂMICA (Convenção UNESCO "
            "2003, Decreto 3.551/2000): vivo, praticado, em constante recriação. As demais alternativas "
            "hipostasiam (A: descontextualizam), universalizam (C: símbolos universais) ou imobilizam "
            "(D: imutabilidade)."
        ),
        "como_banca_pensou": (
            "A banca cobra a diferença entre patrimônio material (fixo) e imaterial (vivo, dinâmico). "
            "Palavras-veneno: 'imutabilidade', 'universal', 'independente do contexto'."
        ),
        "resolucao": [
            "Descarte A: 'independente do contexto' apaga o sentido cultural.",
            "Descarte C: 'símbolos universais' contradiz especificidade Wajãpi.",
            "Descarte D: 'imutabilidade' contradiz dinamismo do imaterial.",
            "Marque B: manifestação dinâmica do cotidiano.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Descontextualizar apaga o sentido cultural."),
            "B": ("CORRETA", "Patrimônio imaterial = dinâmico + cotidiano (Convenção UNESCO 2003)."),
            "C": ("ERRADA", "Universalização desconhece a especificidade Wajãpi."),
            "D": ("ERRADA", "'Imutabilidade' contradiz o dinamismo constitutivo."),
        },
        "fundamentacao": [
            "UNESCO. Convenção para a Salvaguarda do Patrimônio Cultural Imaterial. Paris, 2003.",
            "BRASIL. Decreto nº 3.551, de 4 de agosto de 2000. Institui o Registro de Bens Culturais de Natureza Imaterial.",
            "IPHAN. Dossiê Arte Kusiwa. Brasília: IPHAN, 2002.",
            "GONÇALVES, José Reginaldo Santos. Antropologia dos objetos: coleções, museus e patrimônios. Rio de Janeiro: IPHAN, 2007.",
        ],
        "teoria": (
            "PATRIMÔNIO CULTURAL IMATERIAL (Convenção UNESCO, 2003; Decreto 3.551/2000) reúne práticas, "
            "representações, expressões, conhecimentos e técnicas transmitidos entre gerações. A arte "
            "Kusiwa dos Wajãpi (Amapá) foi o primeiro registro brasileiro reconhecido pela UNESCO (2003) "
            "como Obra-Prima do Patrimônio Oral e Imaterial da Humanidade. É patrimônio VIVO: recriado, "
            "atualizado, praticado."
        ),
        "padroes_banca": (
            "O INEP cobra o conceito de patrimônio imaterial como dinâmico e contextual."
        ),
        "pegadinhas": [
            "Trocar imaterial por 'imutável'.",
            "Universalizar culturas específicas.",
            "Descontextualizar patrimônio.",
        ],
        "erros_comuns": (
            "Marcar D por associar patrimônio a 'tradição fixa'."
        ),
        "dica_estrategica": (
            "Patrimônio imaterial = VIVO + DINÂMICO + CONTEXTUAL."
        ),
        "variacao": (
            "(Estilo INEP) O Decreto 3.551/2000 instituiu:\n"
            "A) o Registro de Bens Imateriais; B) o Tombamento; C) o SNC; D) a Lei Rouanet. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. A Convenção da UNESCO sobre Patrimônio Imaterial é de:\n"
            "A) 1988; B) 2003; C) 1972; D) 2010. → B",
            "Q2. A arte Kusiwa é do povo:\n"
            "A) Yanomami; B) Wajãpi; C) Guarani; D) Kayapó. → B",
            "Q3. Patrimônio imaterial é:\n"
            "A) fixo; B) vivo, praticado, recriado; C) apenas oral; D) apenas indígena. → B",
        ],
        "resumo": {
            "regra": "Imaterial = manifestação viva, dinâmica, contextual.",
            "excecoes": "Elementos podem cristalizar-se em registros documentais.",
            "palavra_chave": "Dinâmico + cotidiano + contextual.",
            "artigo": "Decreto 3.551/2000; Convenção UNESCO 2003.",
            "mnemonico": "V.D.C.: Vivo, Dinâmico, Contextual.",
        },
    },

    48: {
        "tema": "Mapeamento cultural do território + avaliação processual",
        "subtema": "Portfólio como avaliação processual",
        "habilidade_bncc": "EF06HI08, EM13CHS502",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a articulação entre OBJETIVO (identificar práticas culturais como sistemas de "
            "saberes que articulam memória, identidade e vida) e METODOLOGIA de AVALIAÇÃO PROCESSUAL "
            "(portfólio ao longo da sequência)."
        ),
        "como_banca_pensou": (
            "A banca cobra pareamento OBJETIVO + INSTRUMENTO. Distratores mudam o objetivo, o instrumento "
            "ou o momento da avaliação."
        ),
        "resolucao": [
            "Descarte B: 'observação de dificuldades' é diagnóstico, não sistema de saberes.",
            "Descarte C: 'domínio teórico prévio' é diagnóstica, não processual.",
            "Descarte D: 'padrões tradicionais' contradiz a proposta.",
            "Marque A: sistemas de saberes + portfólio processual.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Objetivo (saberes que articulam memória, identidade e modos de vida) + portfólio processual."),
            "B": ("ERRADA", "Foco em 'dificuldades conceituais' descaracteriza a proposta cultural."),
            "C": ("ERRADA", "'Domínio teórico prévio' + relatório final = diagnóstica + somativa, não processual."),
            "D": ("ERRADA", "'Padrões tradicionais' contradiz a proposta interdisciplinar e crítica."),
        },
        "fundamentacao": [
            "VILLAS BOAS, Benigna Maria de Freitas. Portfólio, avaliação e trabalho pedagógico. Campinas: Papirus, 2004.",
            "PERRENOUD, Philippe. Avaliação: da excelência à regulação. Porto Alegre: Artmed, 1999.",
            "HALL, Stuart. A identidade cultural na pós-modernidade. Rio de Janeiro: DP&A, 2006.",
            "IPHAN. Dossiê Arte Kusiwa. Brasília: IPHAN, 2002.",
        ],
        "teoria": (
            "PORTFÓLIO é instrumento privilegiado de avaliação processual (Villas Boas, 2004): permite "
            "acompanhar o desenvolvimento do estudante ao longo da sequência didática. No ensino de "
            "História, articula-se ao mapeamento cultural, ao reconhecimento de saberes locais e às "
            "identidades culturais (Hall, 2006)."
        ),
        "padroes_banca": (
            "O INEP cobra pareamento OBJETIVO + INSTRUMENTO + MOMENTO da avaliação."
        ),
        "pegadinhas": [
            "Confundir portfólio com relatório final.",
            "Reduzir avaliação processual a diagnóstica.",
            "Priorizar dificuldades sobre saberes.",
        ],
        "erros_comuns": (
            "Marcar C por 'relatório' parecer processual."
        ),
        "dica_estrategica": (
            "Processual = acompanha o processo (portfólio, diários, produções contínuas)."
        ),
        "variacao": (
            "(Estilo INEP) O portfólio, segundo Villas Boas (2004), é:\n"
            "A) somativo por natureza; B) instrumento de avaliação processual e formativa; "
            "C) apenas classificatório; D) igual à prova. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Mapeamento cultural do território mobiliza:\n"
            "A) saberes locais e memórias; B) apenas dados econômicos; C) só geografia física; D) só linguística. → A",
            "Q2. Stuart Hall discute:\n"
            "A) identidades fluidas; B) essencialismo; C) neutralidade; D) universalismo abstrato. → A",
            "Q3. Villas Boas (2004) é referência para:\n"
            "A) portfólio na avaliação; B) alfabetização; C) matemática financeira; D) gestão escolar. → A",
        ],
        "resumo": {
            "regra": "Objetivo cultural + portfólio processual = coerência avaliativa.",
            "excecoes": "Portfólio pode combinar-se com outros instrumentos.",
            "palavra_chave": "Sistemas de saberes + portfólio + processual.",
            "artigo": "VILLAS BOAS (2004); PERRENOUD (1999).",
            "mnemonico": "P.S.M.: Portfólio, Saberes, Memória.",
        },
    },

    49: {
        "tema": "Cabanagem (1835-1840) e formação do Estado nacional",
        "subtema": "Revoltas do período regencial e resistência popular",
        "habilidade_bncc": "EF08HI09, EM13CHS103",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa (iconográfica)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "A Cabanagem foi uma das mais violentas revoltas populares do período regencial, envolvendo "
            "indígenas, mestiços e escravizados no Grão-Pará. A aquarela mostra ASSALTO DOS CABANOS AO "
            "TREM — cena de RESISTÊNCIA POPULAR. As demais alternativas invertem o sentido histórico."
        ),
        "como_banca_pensou": (
            "A banca cobra o reconhecimento da Cabanagem como movimento popular, distinguindo-o das "
            "narrativas tradicionais elitistas."
        ),
        "resolucao": [
            "Reconheça: aquarela mostra cabanos em assalto — cena popular.",
            "Descarte B: elite guiando o povo contradiz a natureza do movimento.",
            "Descarte C: camponeses rezando em frente à igreja não caracteriza a Cabanagem.",
            "Descarte D: 'concordância generalizada' apaga o conflito.",
            "Marque A: resistência popular.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Cabanagem = resistência popular no processo de formação do Estado nacional."),
            "B": ("ERRADA", "A elite não conduzia o movimento; ao contrário, era combatida por ele."),
            "C": ("ERRADA", "Camponeses rezando não é a cena da Cabanagem."),
            "D": ("ERRADA", "Não houve 'concordância generalizada'; havia conflito com o clero."),
        },
        "fundamentacao": [
            "REIS, João José. Rebelião escrava no Brasil: a história do levante dos malês em 1835. São Paulo: Cia. das Letras, 2003.",
            "DI PAOLO, Pasquale. Cabanagem: a revolução popular da Amazônia. Belém: Cejup, 1990.",
            "BASILE, Marcello Otávio N. de C. Revoltas regenciais na Corte: o movimento das carochinhas. Rio de Janeiro: FGV, 2001.",
            "FRAGOSO, João; MARTINS, Ilmar; JANCSÓ, István (org.). O Brasil colonial (várias edições).",
        ],
        "teoria": (
            "A CABANAGEM (1835-1840) foi revolta popular no Grão-Pará contra as elites regenciais, "
            "envolvendo cabanos (populares que viviam em cabanas), indígenas e negros. Foi violentamente "
            "reprimida, com milhares de mortos. Integra o ciclo de revoltas regenciais (Balaiada, Malês, "
            "Farroupilha, Sabinada). Di Paolo (1990) sistematiza a interpretação como revolução popular."
        ),
        "padroes_banca": (
            "O INEP cobra a leitura crítica de revoltas populares no período regencial."
        ),
        "pegadinhas": [
            "Colocar a elite como condutora.",
            "Confundir com movimentos religiosos.",
            "Suavizar o conflito.",
        ],
        "erros_comuns": (
            "Marcar B por hábito de ver revoltas como 'lideradas por elites'."
        ),
        "dica_estrategica": (
            "Revoltas regenciais = protagonismo popular + tensões federativas."
        ),
        "variacao": (
            "(Estilo INEP) A Cabanagem ocorreu na região:\n"
            "A) Sul; B) Nordeste sertanejo; C) Grão-Pará (Amazônia); D) Minas Gerais. Gabarito: C."
        ),
        "minisimulado": [
            "Q1. As revoltas regenciais incluem:\n"
            "A) Balaiada, Malês, Farroupilha, Sabinada, Cabanagem; B) apenas Farroupilha; "
            "C) apenas Malês; D) nenhuma delas. → A",
            "Q2. A Revolta dos Malês (1835) foi:\n"
            "A) mineira; B) baiana, de africanos muçulmanos escravizados; C) paulista; D) gaúcha. → B",
            "Q3. João José Reis é referência para:\n"
            "A) escravidão e Malês; B) Renascimento; C) Iluminismo; D) Reforma. → A",
        ],
        "resumo": {
            "regra": "Cabanagem = revolta popular no Grão-Pará (1835-40).",
            "excecoes": "Interpretações antigas atribuíam-na a elites; historiografia recente inverte.",
            "palavra_chave": "Resistência popular + Estado nacional.",
            "artigo": "DI PAOLO (1990); BASILE (2001).",
            "mnemonico": "CABANOS: Camadas populares Amazônicas Batalhando A Nova Ordem Social.",
        },
    },

    50: {
        "tema": "Mulheres e Antiguidade africana (Egito, Núbia)",
        "subtema": "Realeza feminina e divisão do trabalho",
        "habilidade_bncc": "EF06HI09, EM13CHS502",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média-alta",
        "justificativa_classificacao": (
            "A pergunta cobra o que os textos afirmam sobre mulheres na Antiguidade africana. "
            "A afirmação central é: MULHERES DESEMPENHARAM FUNÇÕES DE DESTAQUE (realeza feminina, "
            "sacerdócio, trabalho especializado). As demais reduzem, subalternizam ou biologizam."
        ),
        "como_banca_pensou": (
            "O INEP quer combater estereótipos de submissão universal, mostrando protagonismo feminino "
            "na Antiguidade africana."
        ),
        "resolucao": [
            "Descarte B: 'funções homogêneas' contradiz a diversidade descrita.",
            "Descarte C: 'limitada participação política' contradiz a realeza feminina egípcia.",
            "Descarte D: 'subalternas em razão da coloração' é essencialismo.",
            "Marque A: destaque em várias funções.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Mulheres desempenharam funções de destaque (realeza, sacerdócio, trabalho especializado)."),
            "B": ("ERRADA", "Não eram homogêneas — havia diversidade."),
            "C": ("ERRADA", "Havia realeza feminina; participação política existia."),
            "D": ("ERRADA", "Cor na iconografia egípcia é convenção estética, não hierarquia de subalternidade."),
        },
        "fundamentacao": [
            "REDE, Marcelo. Egito Antigo. São Paulo: Companhia das Letras, 2017.",
            "SILVA, Alberto da Costa e. A enxada e a lança: a África antes dos portugueses. 4. ed. Rio de Janeiro: Nova Fronteira, 2011.",
            "TYLDESLEY, Joyce. Crônicas das rainhas do Egito. Londres: Thames & Hudson, 2006.",
            "UNESCO. História geral da África, Vol. II: África antiga. Brasília: UNESCO, 2010.",
        ],
        "teoria": (
            "A Antiguidade africana (Egito, Núbia, Etiópia, Cartago, reinos subsaarianos) apresenta "
            "protagonismo feminino em várias esferas: realeza (Hatshepsut, Nefertari, Cleópatra, "
            "candaces de Meroé), sacerdócio, comércio. Alberto da Costa e Silva (2011) e a UNESCO (2010) "
            "sistematizam a história africana em bases não eurocêntricas. Tyldesley (2006) discute as "
            "rainhas do Egito."
        ),
        "padroes_banca": (
            "O INEP cobra desconstrução de estereótipos de submissão feminina universal e da imagem "
            "monolítica da África."
        ),
        "pegadinhas": [
            "Homogeneizar as culturas.",
            "Biologizar a cor da pele.",
            "Reduzir a política a masculino.",
        ],
        "erros_comuns": (
            "Marcar C por hábito de ver 'religião' como esfera feminina."
        ),
        "dica_estrategica": (
            "África antiga = protagonismo político e social feminino em várias culturas."
        ),
        "variacao": (
            "(Estilo INEP) Hatshepsut, faraó do Egito, governou:\n"
            "A) o Novo Império; B) o Antigo Reino apenas; C) a Núbia; D) Cartago. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Alberto da Costa e Silva (2011) sistematiza:\n"
            "A) a África antes dos portugueses; B) a Idade Média europeia; C) o Renascimento italiano; D) o Iluminismo. → A",
            "Q2. As candaces eram:\n"
            "A) rainhas do reino de Meroé/Núbia; B) escravizadas no Egito; C) rainhas gregas; D) sacerdotisas incas. → A",
            "Q3. A História Geral da África, da UNESCO, reúne:\n"
            "A) 3 volumes; B) 8 volumes; C) 20 volumes; D) 15 volumes. → B",
        ],
        "resumo": {
            "regra": "Antiguidade africana = protagonismo feminino em várias esferas.",
            "excecoes": "Havia hierarquias, mas sem homogeneização universal.",
            "palavra_chave": "Destaque + realeza + sacerdócio.",
            "artigo": "COSTA E SILVA (2011); UNESCO (2010); TYLDESLEY (2006).",
            "mnemonico": "H.C.N.: Hatshepsut, Candaces, Núbia.",
        },
    },

    51: {
        "tema": "Cosmologia do Egito Antigo e didática crítica",
        "subtema": "Leitura de fontes + debate coletivo",
        "habilidade_bncc": "EF06HI09, EM13CHS502",
        "gabarito": "D",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Média",
        "justificativa_classificacao": (
            "Item cobra a proposta pedagógica que promova AUTONOMIA + PENSAMENTO CRÍTICO. Descarta "
            "atividades transmissivas (A: transcrição), reprodutivas (B: 'reproduzir' cosmologia; C: "
            "'reproduções da vida') e prefere LEITURA + DEBATE + CONSULTA A FONTES DIVERSAS (D)."
        ),
        "como_banca_pensou": (
            "A banca contrasta abordagem CRÍTICA (leitura + debate + fontes diversas) com abordagens "
            "TRANSMISSIVAS/REPRODUTIVAS."
        ),
        "resolucao": [
            "Descarte A: transcrição valoriza memorização, não crítica.",
            "Descarte B: 'reproduzir' cosmologia como 'experiências vividas' é ingênuo.",
            "Descarte C: 'reproduções da vida' + descrição sem crítica.",
            "Marque D: leitura + debate + fontes diversas = autonomia crítica.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Transcrição é atividade reprodutiva."),
            "B": ("ERRADA", "'Reproduzir experiências religiosas' é anacronismo."),
            "C": ("ERRADA", "'Fontes como reproduções da vida' é positivismo ingênuo."),
            "D": ("CORRETA", "Leitura crítica + debate + fontes distintas = autonomia e crítica."),
        },
        "fundamentacao": [
            "CARDOSO, Ciro Flamarion. Deuses, múmias e ziggurats. Porto Alegre: EdiPUCRS, 1999.",
            "SALES, José das Candeias. Poder e iconografia no antigo Egipto. Lisboa: Livros Horizonte, 2008.",
            "SCHMIDT, Maria Auxiliadora; CAINELLI, Marlene. Ensinar História. São Paulo: Scipione, 2018.",
            "REDE, Marcelo. Egito Antigo. São Paulo: Cia. das Letras, 2017.",
        ],
        "teoria": (
            "Ensinar História com autonomia crítica exige mobilizar FONTES DIVERSAS (imagéticas, textuais, "
            "materiais) e submetê-las ao debate coletivo. Schmidt & Cainelli (2018) discutem estratégias "
            "de didática da História que rompem com transmissão passiva. Cardoso (1999) fornece base "
            "comparativa para religiões antigas."
        ),
        "padroes_banca": (
            "O INEP contrasta transmissivo vs. crítico-investigativo."
        ),
        "pegadinhas": [
            "Confundir descrição com crítica.",
            "Achar que 'audiovisual' automaticamente é crítico.",
            "Reduzir aula a transcrição.",
        ],
        "erros_comuns": (
            "Marcar B por 'visualização' soar boa."
        ),
        "dica_estrategica": (
            "Autonomia crítica = leitura + debate + confronto de fontes."
        ),
        "variacao": (
            "(Estilo INEP) O uso de fontes diversas em aula de História visa:\n"
            "A) uniformizar interpretações; B) confrontar perspectivas e desenvolver crítica; "
            "C) memorizar datas; D) transcrever. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Ramsés II reinou em qual dinastia?\n"
            "A) XIX; B) X; C) V; D) I. → A",
            "Q2. Abu Simbel é:\n"
            "A) templo egípcio na Baixa Núbia; B) cidade grega; C) romana; D) mesopotâmica. → A",
            "Q3. Cardoso (1999) compara religiões:\n"
            "A) apenas gregas; B) do Egito e Mesopotâmia; C) do Império Romano; D) do Japão medieval. → B",
        ],
        "resumo": {
            "regra": "Aula crítica = leitura + debate + fontes diversas.",
            "excecoes": "Transcrição pode servir de fixação após crítica.",
            "palavra_chave": "Debate + confronto de fontes.",
            "artigo": "SCHMIDT & CAINELLI (2018); CARDOSO (1999).",
            "mnemonico": "L.D.C.: Ler, Debater, Confrontar fontes.",
        },
    },

    52: {
        "tema": "Nazifascismo e extrema-direita (passado-presente)",
        "subtema": "Xenofobia, violência e ataques à democracia",
        "habilidade_bncc": "EF09HI17, EM13CHS604",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a comparação HISTÓRICA entre extrema-direita entreguerras e atual. A resposta "
            "articula aspectos comuns: xenofobia, violência e ataques à democracia. As demais reduzem, "
            "distorcem ou negam continuidades."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão de PERMANÊNCIAS e MUDANÇAS. Palavras-veneno: 'origem na América "
            "do Norte' (A), 'fim do socialismo desmontou anticomunismo' (D)."
        ),
        "resolucao": [
            "Descarte A: nazifascismo é europeu, não norte-americano.",
            "Descarte C: extrema-direita atual mantém conservadorismo moral, não só econômico.",
            "Descarte D: anticomunismo persiste, mesmo após fim da URSS.",
            "Marque B: xenofobia + violência + ataques à democracia.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Origem europeia (Itália, Alemanha), não América do Norte."),
            "B": ("CORRETA", "Xenofobia, violência e antidemocracia são aspectos comuns entre os períodos."),
            "C": ("ERRADA", "Conservadorismo moral persiste; não apenas 'liberalismo'."),
            "D": ("ERRADA", "Anticomunismo continua estruturando discursos da extrema-direita."),
        },
        "fundamentacao": [
            "PAXTON, Robert. A anatomia do fascismo. São Paulo: Paz e Terra, 2007.",
            "STANLEY, Jason. Como funciona o fascismo. Porto Alegre: L&PM, 2018.",
            "TRAVERSO, Enzo. As novas faces do fascismo. Belo Horizonte: Autêntica, 2021.",
            "HOBSBAWM, Eric. Era dos extremos. São Paulo: Cia. das Letras, 1995.",
        ],
        "teoria": (
            "O FASCISMO histórico (Mussolini, 1922; Hitler, 1933) e a EXTREMA-DIREITA contemporânea "
            "compartilham traços: nacionalismo excludente, xenofobia, misoginia, antipluralismo, "
            "violência política, ataques à democracia liberal, culto ao líder. Paxton (2007), Stanley "
            "(2018) e Traverso (2021) fornecem grades comparativas. Não há identidade absoluta, mas "
            "família de fenômenos com aparições recorrentes."
        ),
        "padroes_banca": (
            "O INEP cobra história como recurso crítico para o presente."
        ),
        "pegadinhas": [
            "Reduzir origem à América do Norte.",
            "Achar que fascismo é só passado.",
            "Separar economia de moralidade.",
        ],
        "erros_comuns": (
            "Marcar D por lembrar do fim da URSS."
        ),
        "dica_estrategica": (
            "Extrema-direita = xenofobia + antidemocracia (transversais)."
        ),
        "variacao": (
            "(Estilo INEP) Stanley (2018) identifica no fascismo:\n"
            "A) diálogo democrático; B) mito de um passado glorioso + hierarquia + vitimização; "
            "C) valorização da alteridade; D) pluralismo institucional. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Mussolini chegou ao poder em:\n"
            "A) 1917; B) 1922; C) 1933; D) 1945. → B",
            "Q2. A Marcha sobre Roma foi em:\n"
            "A) 1922; B) 1919; C) 1930; D) 1943. → A",
            "Q3. Hobsbawm chamou o século XX de:\n"
            "A) Era dos extremos; B) Século doce; C) Belle Époque; D) Século breve. → A (também 'século breve')",
        ],
        "resumo": {
            "regra": "Fascismo/extrema-direita = xenofobia + antidemocracia + culto ao líder.",
            "excecoes": "Contextos e formas variam entre décadas.",
            "palavra_chave": "Xenofobia + antidemocracia.",
            "artigo": "PAXTON (2007); STANLEY (2018); TRAVERSO (2021).",
            "mnemonico": "XVA: Xenofobia, Violência, Ataques à democracia.",
        },
    },

    53: {
        "tema": "Guerra da Coreia + Guerra Fria + fontes midiáticas",
        "subtema": "Intencionalidade do jornal e disputa geopolítica",
        "habilidade_bncc": "EF09HI25, EM13CHS604",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a análise crítica do editorial (1953): intencionalidade da fonte + interesses "
            "ocidentais (manter colonialismo/imperialismo) + interesses da China (superar dominação "
            "imperialista, apoiando movimentos anticoloniais)."
        ),
        "como_banca_pensou": (
            "A banca cobra crítica de fonte midiática + geopolítica da Guerra Fria + descolonização."
        ),
        "resolucao": [
            "Descarte A: 'defesa do protecionismo' + 'livre-cambismo' não são o eixo da questão.",
            "Descarte B: fonte é editorial, não neutro; 'liberdade de expressão' é impreciso.",
            "Descarte C: 'veracidade da informação' não é a categoria; 'globalização comercial' anacrônica.",
            "Marque D: intencionalidade + sobrevida do colonialismo + superação da dominação imperialista.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Não é sobre protecionismo/livre-cambismo."),
            "B": ("ERRADA", "'Tipologia' é raso; a chave é a intencionalidade."),
            "C": ("ERRADA", "Não se trata de veracidade nem de globalização."),
            "D": ("CORRETA", "Intencionalidade da fonte + colonialismo/imperialismo em disputa."),
        },
        "fundamentacao": [
            "HOBSBAWM, Eric. Era dos extremos. São Paulo: Cia. das Letras, 1995.",
            "WESTAD, Odd Arne. A Guerra Fria: uma nova história. Rio de Janeiro: Zahar, 2018.",
            "VIZENTINI, Paulo Fagundes. Guerra da Coreia. São Paulo: Saraiva, 2013.",
            "BURKE, Peter (org.). A escrita da história: novas perspectivas. São Paulo: UNESP, 1992.",
        ],
        "teoria": (
            "A GUERRA DA COREIA (1950-1953) foi o primeiro grande conflito quente da Guerra Fria. "
            "Envolveu EUA (via ONU), China e URSS. A Indochina francesa era outra frente, com apoio "
            "chinês ao Vietminh. O editorial expressa uma leitura ocidental que revela intencionalidade "
            "geopolítica. Crítica de fonte midiática é competência essencial no ensino de História "
            "contemporânea."
        ),
        "padroes_banca": (
            "O INEP cobra crítica de fonte midiática articulada à Guerra Fria/descolonização."
        ),
        "pegadinhas": [
            "Achar que jornal é neutro.",
            "Desconectar Coreia de Indochina.",
            "Anacronizar com 'globalização'.",
        ],
        "erros_comuns": (
            "Marcar B por confundir 'tipologia' com 'crítica'."
        ),
        "dica_estrategica": (
            "Editorial = intencionalidade + posição ideológica."
        ),
        "variacao": (
            "(Estilo INEP) A Guerra da Coreia terminou com:\n"
            "A) rendição total do Norte; B) armistício (1953) mantendo divisão no paralelo 38; "
            "C) reunificação; D) vitória chinesa completa. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. O Vietminh:\n"
            "A) lutava contra os franceses no norte da Indochina; B) apoiou os EUA; C) era anti-URSS; D) invadiu a China. → A",
            "Q2. Westad (2018) reinterpreta:\n"
            "A) apenas a URSS; B) a Guerra Fria como fenômeno global e descolonial; C) só a China; D) só os EUA. → B",
            "Q3. Editorial jornalístico é fonte:\n"
            "A) neutra; B) opinativa/intencional; C) só literária; D) somente iconográfica. → B",
        ],
        "resumo": {
            "regra": "Fontes midiáticas exigem crítica de intencionalidade.",
            "excecoes": "Notícia informativa difere de editorial opinativo.",
            "palavra_chave": "Intencionalidade + colonialismo.",
            "artigo": "HOBSBAWM (1995); WESTAD (2018).",
            "mnemonico": "I.C.I.: Intencionalidade, Colonialismo, Imperialismo.",
        },
    },

    54: {
        "tema": "Interseccionalidade e literatura no ensino de História",
        "subtema": "'Um defeito de cor' e protagonismo de mulheres negras",
        "habilidade_bncc": "EF08HI19, EF09HI13, EM13CHS502",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a finalidade da sequência: VALORIZAR o PROTAGONISMO de mulheres negras na luta "
            "pela liberdade. Distratores confundem ficção com fonte oficial (B), 'criticar' o significado "
            "familiar (C — incoerente) ou denunciar 'usos da mídia' (D — não é o foco)."
        ),
        "como_banca_pensou": (
            "A banca cobra a leitura AFIRMATIVA e INTERSECCIONAL do uso de literatura afro-brasileira "
            "para valorizar protagonismos silenciados."
        ),
        "resolucao": [
            "Descarte B: ficção NÃO é 'fonte oficial'.",
            "Descarte C: 'criticar significado familiar da diáspora' é incoerente.",
            "Descarte D: 'denunciar usos da mídia' desloca o foco.",
            "Marque A: valorizar protagonismo de mulheres negras.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Literatura de Ana Maria Gonçalves + interseccionalidade = valorizar mulheres negras na luta."),
            "B": ("ERRADA", "Ficção é fonte literária, não 'oficial'."),
            "C": ("ERRADA", "Formulação incoerente."),
            "D": ("ERRADA", "Não é sobre mídia."),
        },
        "fundamentacao": [
            "GONÇALVES, Ana Maria. Um defeito de cor. Rio de Janeiro: Record, 2006.",
            "CRENSHAW, Kimberlé. Documento para o encontro de especialistas em aspectos da discriminação racial relativos ao gênero. Revista Estudos Feministas, v. 10, n. 1, 2002.",
            "COLLINS, Patricia Hill. Pensamento feminista negro. São Paulo: Boitempo, 2019.",
            "REIS, João José. Rebelião escrava no Brasil: os malês. São Paulo: Cia. das Letras, 2003.",
        ],
        "teoria": (
            "INTERSECCIONALIDADE (Crenshaw, 1989; Collins) analisa como raça, gênero e classe se "
            "articulam. A literatura de Ana Maria Gonçalves (2006) sobre Luísa Mahin/Kehinde permite "
            "trabalhar essa perspectiva no ensino de História. Combatendo silenciamentos históricos, "
            "literatura ficcional é fonte legítima quando lida criticamente."
        ),
        "padroes_banca": (
            "O INEP cobra a articulação LITERATURA + INTERSECCIONALIDADE + PROTAGONISMO."
        ),
        "pegadinhas": [
            "Confundir literatura com fonte oficial.",
            "Sugerir 'crítica' à diáspora.",
            "Descentrar da mulher negra.",
        ],
        "erros_comuns": (
            "Marcar B por associar 'oficial' a legitimidade."
        ),
        "dica_estrategica": (
            "Interseccionalidade + literatura = protagonismo de vozes silenciadas."
        ),
        "variacao": (
            "(Estilo INEP) Interseccionalidade foi formulada por:\n"
            "A) Kimberlé Crenshaw (1989);\n"
            "B) Foucault; C) Weber; D) Bourdieu. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Luísa Mahin foi:\n"
            "A) figura ligada à Revolta dos Malês em Salvador (1835); B) rainha egípcia; "
            "C) escritora modernista; D) política contemporânea. → A",
            "Q2. Ana Maria Gonçalves escreveu:\n"
            "A) Um defeito de cor; B) Ponciá Vicêncio; C) Torto Arado; D) Canção para ninar menino grande. → A",
            "Q3. Patricia Hill Collins tematiza:\n"
            "A) pensamento feminista negro; B) direito romano; C) micro-história; D) demografia colonial. → A",
        ],
        "resumo": {
            "regra": "Literatura afro-brasileira = fonte para protagonismo de mulheres negras.",
            "excecoes": "Cotejar com fontes históricas para contextualização.",
            "palavra_chave": "Protagonismo + interseccionalidade.",
            "artigo": "CRENSHAW (2002); COLLINS (2019).",
            "mnemonico": "P.I.L.: Protagonismo + Interseccionalidade + Literatura.",
        },
    },

    55: {
        "tema": "Quilombo (Beatriz Nascimento) e educação antirracista",
        "subtema": "Ancestralidade e prática interdisciplinar",
        "habilidade_bncc": "EF08HI21, EM13CHS502; Lei 10.639/03",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Prática (interdisciplinar)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o par PRÁTICA INTERDISCIPLINAR + CONCEITO DE QUILOMBO. A resposta D articula "
            "ENTREVISTA COM LÍDERES COMUNITÁRIOS (metodologia dialógica) + espaço de resistência com "
            "base na ANCESTRALIDADE e VISÃO COLETIVA — expressando o conceito de Beatriz Nascimento."
        ),
        "como_banca_pensou": (
            "A banca cobra fidelidade ao pensamento de Beatriz Nascimento: quilombo como fenômeno vivo, "
            "político, cultural, ancestral — e prática pedagógica coerente."
        ),
        "resolucao": [
            "Descarte A: 'independência do Brasil' desloca o conceito.",
            "Descarte B: 'concentra experiências no passado colonial' contradiz o quilombo vivo.",
            "Descarte C: 'retorno à África' desloca (existiram movimentos, mas não é o conceito de quilombo).",
            "Marque D: entrevista com líderes + ancestralidade + visão coletiva.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Quilombo não visava independência do Brasil."),
            "B": ("ERRADA", "Beatriz Nascimento afirma quilombo VIVO, não apenas passado colonial."),
            "C": ("ERRADA", "'Retorno à África' era uma proposta específica (Marcus Garvey), não o conceito de quilombo."),
            "D": ("CORRETA", "Método dialógico (entrevistas) + quilombo como resistência ancestral e coletiva."),
        },
        "fundamentacao": [
            "NASCIMENTO, Beatriz. Uma história feita por mãos negras. Rio de Janeiro: Zahar, 2021.",
            "MOURA, Clóvis. Os quilombos e a rebelião negra. São Paulo: Brasiliense, 1981.",
            "GOMES, Flávio dos Santos. História, protesto e cultura política no Brasil escravista. Rio de Janeiro: Mauad, 2003.",
            "BRASIL. Decreto nº 4.887, de 20 de novembro de 2003. Regulamenta a titulação de terras quilombolas.",
        ],
        "teoria": (
            "Beatriz Nascimento (1942-1995), historiadora negra pioneira, refundou o conceito de QUILOMBO "
            "como categoria histórica, política e cultural viva — não apenas 'refúgio' escravista. "
            "Aquilombar-se é ATO POLÍTICO contemporâneo. Movimento Quilombola Nacional articula-se com "
            "CF/88 (art. 68 ADCT) e Decreto 4.887/2003. Práticas educativas interdisciplinares dialogam "
            "com comunidades tradicionais."
        ),
        "padroes_banca": (
            "O INEP cobra fidelidade ao pensamento de Nascimento e afinidade entre método dialógico e "
            "conteúdo."
        ),
        "pegadinhas": [
            "Confundir quilombo com 'retorno à África'.",
            "Fixar quilombo no passado.",
            "Reduzir a mapa.",
        ],
        "erros_comuns": (
            "Marcar B por associar quilombo apenas ao período colonial."
        ),
        "dica_estrategica": (
            "Beatriz Nascimento = quilombo vivo + resistência ancestral + coletividade."
        ),
        "variacao": (
            "(Estilo INEP) O art. 68 do ADCT reconhece:\n"
            "A) direito à terra dos remanescentes de quilombos;\n"
            "B) apenas o INSS; C) o serviço militar; D) o Poder Judiciário. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Aquilombar-se é:\n"
            "A) fugir; B) reconstruir espaços de vida, ancestralidade e resistência; "
            "C) migrar economicamente; D) aliar-se à elite. → B",
            "Q2. Clóvis Moura escreveu:\n"
            "A) Os quilombos e a rebelião negra; B) Casa-Grande & Senzala; C) Raízes do Brasil; D) Formação do Brasil Contemporâneo. → A",
            "Q3. Decreto 4.887/2003:\n"
            "A) regulamenta titulação quilombola; B) instituiu a BNCC; C) criou o SUS; D) regulamentou a Constituição de 1824. → A",
        ],
        "resumo": {
            "regra": "Quilombo (Nascimento) = ancestralidade + coletividade + resistência viva.",
            "excecoes": "Contexto histórico varia, mas o núcleo político-cultural permanece.",
            "palavra_chave": "Ancestralidade + coletividade + resistência.",
            "artigo": "CF/88 art. 68 ADCT; Decreto 4.887/2003.",
            "mnemonico": "A.R.C.: Ancestralidade, Resistência, Coletividade.",
        },
    },

    56: {
        "tema": "Cheikh Anta Diop – África no berço da civilização",
        "subtema": "Mediterrâneo antigo e crítica ao eurocentrismo",
        "habilidade_bncc": "EF06HI09, EM13CHS502",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o reconhecimento da contribuição AFRICANA (egípcia) à ciência antiga, "
            "especialmente a MEDICINA. As demais alternativas invertem a cronologia ou naturalizam "
            "hierarquias eurocêntricas."
        ),
        "como_banca_pensou": (
            "A banca cobra a leitura DECOLONIAL de Diop: rejeitar o mito grego como origem única e "
            "reconhecer contribuições africanas."
        ),
        "resolucao": [
            "Descarte A: democracia ateniense era exceção limitada, não modelo mediterrânico universal.",
            "Descarte C: 'pensamento grego oriundo do islâmico' anacronismo (islamismo surge no séc. VII).",
            "Descarte D: 'gregos ensinaram matemática aos egípcios para pirâmides' inverte a cronologia.",
            "Marque B: medicina egípcia com técnicas cirúrgicas e conhecimento anatômico.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Democracia ateniense era exceção; nem Roma nem Egito a adotaram."),
            "B": ("CORRETA", "Medicina egípcia é reconhecida como uma das mais avançadas da Antiguidade."),
            "C": ("ERRADA", "Anacronismo: islamismo é do séc. VII, posterior à filosofia grega."),
            "D": ("ERRADA", "Inversão cronológica: pirâmides são anteriores à matemática grega."),
        },
        "fundamentacao": [
            "DIOP, Cheikh Anta. Nações negras e culturas. Petrópolis: Vozes, 2025.",
            "BERNAL, Martin. Atena negra. Rio de Janeiro: Record, 2005.",
            "UNESCO. História geral da África. Brasília: UNESCO, 2010.",
            "REDE, Marcelo. Egito Antigo. São Paulo: Companhia das Letras, 2017.",
        ],
        "teoria": (
            "Cheikh Anta Diop (senegalês) e Martin Bernal desafiaram narrativas eurocêntricas ao "
            "reafirmar contribuições africanas (Egito) à Antiguidade Clássica. A medicina egípcia é "
            "documentada em papiros (Ebers, Edwin Smith), com técnicas cirúrgicas, farmacologia e "
            "conhecimento anatômico avançados. A Lei 10.639/03 estimula essa perspectiva no ensino "
            "básico."
        ),
        "padroes_banca": (
            "O INEP cobra decolonialidade da história antiga; palavras-veneno = anacronismos e inversões."
        ),
        "pegadinhas": [
            "Anacronismo com o islã antes do séc. VII.",
            "Inversão de origens gregas x egípcias.",
            "Aceitar 'democracia' como modelo universal antigo.",
        ],
        "erros_comuns": (
            "Marcar A por hábito eurocêntrico de idealizar a democracia ateniense."
        ),
        "dica_estrategica": (
            "Em Diop/decolonialidade, prefira alternativas que reconheçam PROTAGONISMO africano."
        ),
        "variacao": (
            "(Estilo INEP) Martin Bernal, em 'Atena Negra' (2005), argumenta que:\n"
            "A) a Grécia clássica é totalmente autóctone;\n"
            "B) muitas raízes gregas devem ser buscadas na África e no Levante;\n"
            "C) o Egito é irrelevante para a Grécia; D) só o Império Romano importa. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Papiro de Edwin Smith é fonte para:\n"
            "A) medicina egípcia; B) música grega; C) filosofia islâmica; D) direito romano. → A",
            "Q2. Cheikh Anta Diop era:\n"
            "A) senegalês; B) francês; C) grego; D) estadunidense. → A",
            "Q3. Islamismo surge:\n"
            "A) séc. VII; B) séc. II a.C.; C) séc. XV; D) séc. XX. → A",
        ],
        "resumo": {
            "regra": "Antiguidade mediterrânica = trocas Egito-Grécia-Levante.",
            "excecoes": "Cada polo tinha especificidades culturais.",
            "palavra_chave": "Medicina egípcia + decolonial.",
            "artigo": "DIOP; BERNAL; UNESCO (2010).",
            "mnemonico": "D.M.E.: Diop, Medicina, Egito.",
        },
    },

    57: {
        "tema": "Uberização e discurso da autonomia",
        "subtema": "Precarização do trabalho no capitalismo digital",
        "habilidade_bncc": "EM13CHS502, EM13CHS303",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a identificação do NOVO DISCURSO ideológico (liberdade e autonomia) que "
            "MASCARA a precarização. As demais opções reproduzem esse discurso (B, D) ou reduzem à "
            "dependência digital (A)."
        ),
        "como_banca_pensou": (
            "A banca cobra o entendimento CRÍTICO da uberização como ideologia (Antunes)."
        ),
        "resolucao": [
            "Descarte A: 'dependência digital' é sintoma, não cerne ideológico.",
            "Descarte B: 'autoemprego como solução' repete o discurso do capital.",
            "Descarte D: 'aperfeiçoamento' banaliza a exploração.",
            "Marque C: discurso de liberdade/autonomia mascara mecanismos de exploração.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Reduz à técnica; o problema é ideológico."),
            "B": ("ERRADA", "'Solução' expressa o próprio discurso enganoso."),
            "C": ("CORRETA", "Nomeia a ideologia: liberdade e autonomia como narrativa que mascara exploração."),
            "D": ("ERRADA", "'Complementar renda' minimiza a nova morfologia do trabalho."),
        },
        "fundamentacao": [
            "ANTUNES, Ricardo. O privilégio da servidão: o novo proletariado de serviços na era digital. São Paulo: Boitempo, 2018.",
            "ANTUNES, Ricardo (org.). Riqueza e miséria do trabalho no Brasil IV: trabalho digital, autogestão e expropriação da vida. São Paulo: Boitempo, 2019.",
            "ABÍLIO, Ludmila. Uberização: do empreendedorismo para o autogerenciamento subordinado. Psicoperspectivas, v. 18, n. 3, 2019.",
            "ARAÚJO, J. N. G. Neoliberalismo e horizontes da precarização do trabalho. Cadernos de Psicologia Social do Trabalho, n. 1, 2020.",
        ],
        "teoria": (
            "A UBERIZAÇÃO (Abílio, 2019) descreve o modelo de trabalho por plataformas em que o "
            "trabalhador assume os riscos (equipamentos, horário, saúde) sob discurso de 'autonomia'. "
            "Antunes (2018) situa esse fenômeno na 'nova morfologia do trabalho' — precarização, "
            "informalidade, adoecimento. Discursos como 'seja seu próprio patrão' funcionam como IDEOLOGIA."
        ),
        "padroes_banca": (
            "O INEP cobra a leitura ideológica do trabalho digital."
        ),
        "pegadinhas": [
            "Aceitar o discurso ideológico da autonomia.",
            "Reduzir a técnica.",
            "Confundir informalidade com liberdade.",
        ],
        "erros_comuns": (
            "Marcar D pela banalização."
        ),
        "dica_estrategica": (
            "Precarização digital = ideologia + exploração encoberta."
        ),
        "variacao": (
            "(Estilo INEP) Abílio (2019) chama a uberização de:\n"
            "A) empreendedorismo emancipador;\n"
            "B) autogerenciamento subordinado;\n"
            "C) socialismo digital; D) fim do trabalho. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Antunes (2018) fala em:\n"
            "A) proletariado de serviços na era digital; B) volta ao taylorismo puro; "
            "C) fim do capitalismo; D) sociedade sem trabalho. → A",
            "Q2. O termo 'toyotismo' descreve:\n"
            "A) modelo japonês flexível de produção; B) modelo alemão; C) modelo escravista; D) modelo taylorista. → A",
            "Q3. A reforma trabalhista brasileira de 2017:\n"
            "A) ampliou direitos; B) flexibilizou relações de trabalho; C) instituiu FGTS; D) fundou o INSS. → B",
        ],
        "resumo": {
            "regra": "Uberização mascara exploração sob discurso de autonomia.",
            "excecoes": "Alguns trabalhadores encontram nichos, mas em geral há perda de direitos.",
            "palavra_chave": "Autonomia ideológica + precarização real.",
            "artigo": "ANTUNES (2018); ABÍLIO (2019).",
            "mnemonico": "A.M.E.: Autonomia (ilusória) Mascara Exploração.",
        },
    },

    58: {
        "tema": "Neoliberalismo e flexibilização da legislação social",
        "subtema": "Reformas trabalhistas e desmonte",
        "habilidade_bncc": "EM13CHS502, EM13CHS303",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o pareamento POLÍTICA/IDEOLOGIA + MEDIDA. A crise da sociedade salarial + "
            "flexibilização = NEOLIBERALISMO + FLEXIBILIZAÇÃO DA LEGISLAÇÃO."
        ),
        "como_banca_pensou": (
            "A banca cobra a articulação teórico-histórica entre neoliberalismo e reformas trabalhistas."
        ),
        "resolucao": [
            "Descarte A: keynesianismo protege direitos; oposto do cenário.",
            "Descarte B: monetarismo é MEDIDA (política monetária), não caracteriza o cenário social.",
            "Descarte D: desenvolvimentismo é diferente do cenário.",
            "Marque C: neoliberalismo + flexibilização da legislação social.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Keynesianismo protege direitos; contradiz o cenário."),
            "B": ("ERRADA", "Monetarismo é conceito diverso; refere-se a política monetária, não à flexibilização social."),
            "C": ("CORRETA", "Neoliberalismo é o marco ideológico da flexibilização da legislação trabalhista."),
            "D": ("ERRADA", "Desenvolvimentismo pressupõe Estado ativo; oposto do cenário."),
        },
        "fundamentacao": [
            "ANDERSON, Perry. Balanço do neoliberalismo. In: SADER, E.; GENTILI, P. (org.). Pós-neoliberalismo: as políticas sociais e o Estado democrático. Rio de Janeiro: Paz e Terra, 1995.",
            "HARVEY, David. O neoliberalismo: história e implicações. São Paulo: Loyola, 2008.",
            "SADER, Emir. A vingança da história. São Paulo: Boitempo, 2003.",
            "BRASIL. Lei nº 13.467, de 13 de julho de 2017. Reforma trabalhista.",
        ],
        "teoria": (
            "NEOLIBERALISMO (Harvey, 2008) é a resposta capitalista à crise dos anos 1970. Suas "
            "medidas incluem privatização, desregulamentação, flexibilização trabalhista, redução do "
            "Estado social. No Brasil, a Reforma Trabalhista (Lei 13.467/2017) exemplifica a flexibilização "
            "da legislação social."
        ),
        "padroes_banca": (
            "O INEP cobra pareamento ideologia-medida."
        ),
        "pegadinhas": [
            "Confundir keynesianismo com neoliberalismo.",
            "Achar que monetarismo = flexibilização.",
            "Confundir neoliberalismo com desenvolvimentismo.",
        ],
        "erros_comuns": (
            "Marcar B por conhecer 'monetarismo' vagamente."
        ),
        "dica_estrategica": (
            "Neoliberalismo = flexibilização + desregulação + Estado mínimo."
        ),
        "variacao": (
            "(Estilo INEP) A Reforma Trabalhista brasileira (2017):\n"
            "A) reforçou o Estado protetor;\n"
            "B) flexibilizou direitos e possibilitou terceirização irrestrita;\n"
            "C) instituiu o CLT; D) proibiu jornada parcial. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Harvey (2008) define neoliberalismo como:\n"
            "A) política monetária; B) projeto de classe para restaurar o poder do capital; "
            "C) fase do socialismo; D) fim do capitalismo. → B",
            "Q2. Anderson (1995) discute:\n"
            "A) balanço do neoliberalismo; B) o feudalismo; C) a Revolução Francesa; D) o Iluminismo. → A",
            "Q3. Desenvolvimentismo brasileiro é associado a:\n"
            "A) JK; B) Collor; C) Temer; D) Sarney. → A",
        ],
        "resumo": {
            "regra": "Neoliberalismo = flexibilização + desregulação.",
            "excecoes": "Existem correntes internas (ordoliberalismo, ultraliberalismo).",
            "palavra_chave": "Neoliberalismo + flexibilização.",
            "artigo": "HARVEY (2008); ANDERSON (1995).",
            "mnemonico": "N.F.L.: Neoliberalismo + Flexibilização + Legislação.",
        },
    },

    59: {
        "tema": "Ditaduras latino-americanas e endividamento externo",
        "subtema": "Anos 1970 e capital estrangeiro",
        "habilidade_bncc": "EM13CHS303, EM13CHS604",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra as consequências das políticas econômicas das ditaduras latino-americanas nos "
            "anos 1970: DEPENDÊNCIA de capital estrangeiro + endividamento externo (crise da dívida). "
            "As demais opções remetem a políticas de outros períodos."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão da crise da dívida latino-americana e do 'milagre econômico' "
            "brasileiro."
        ),
        "resolucao": [
            "Descarte A: âncora cambial + controle inflacionário = anos 1990.",
            "Descarte B: planificação da produção doméstica ≠ ditaduras liberalizantes latino-americanas.",
            "Descarte D: desvalorização cambial + empréstimos baratos não corresponde à realidade.",
            "Marque C: dependência de capital estrangeiro + endividamento externo = anos 1970.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Âncora cambial é dos anos 1990 (Plano Real, Cavallo)."),
            "B": ("ERRADA", "Planificação é conceito de economia socialista."),
            "C": ("CORRETA", "Ditaduras dos anos 1970 tomaram empréstimos externos maciços, gerando a crise da dívida."),
            "D": ("ERRADA", "Não foi desvalorização, e os empréstimos ficaram caros com a subida dos juros nos EUA."),
        },
        "fundamentacao": [
            "GALEANO, Eduardo. As veias abertas da América Latina. Porto Alegre: L&PM, 2010.",
            "FIORI, José Luís. Sistema mundial e América Latina. Petrópolis: Vozes, 1999.",
            "BATISTA JR., Paulo Nogueira. Da crise internacional à moratória brasileira. Rio de Janeiro: Paz e Terra, 1988.",
            "CANO, Wilson. América Latina: notas sobre a crise atual. Economia e Sociedade, v. 18, n. 3, 2009.",
        ],
        "teoria": (
            "Nos anos 1970, ditaduras latino-americanas (Brasil, Argentina, Chile, Uruguai) adotaram "
            "modelos de desenvolvimento baseados em ENDIVIDAMENTO EXTERNO — os 'petrodólares' abundantes "
            "eram emprestados a juros baixos. Em 1979, o choque Volcker (subida abrupta dos juros "
            "norte-americanos) desencadeou a CRISE DA DÍVIDA (1982), levando à 'década perdida'."
        ),
        "padroes_banca": (
            "O INEP cobra articulação política + economia + história latino-americana."
        ),
        "pegadinhas": [
            "Confundir com Plano Real (1994).",
            "Confundir com planificação socialista.",
            "Achar que a dívida se manteve barata.",
        ],
        "erros_comuns": (
            "Marcar A por conhecer âncora cambial."
        ),
        "dica_estrategica": (
            "Anos 1970 = 'milagre' + endividamento externo + colapso pós-1979."
        ),
        "variacao": (
            "(Estilo INEP) O 'choque Volcker' (1979) consistiu em:\n"
            "A) queda dos preços do petróleo;\n"
            "B) elevação dos juros pelo Fed dos EUA, desencadeando a crise da dívida;\n"
            "C) fim de Bretton Woods; D) criação da OMC. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A 'década perdida' latino-americana refere-se a:\n"
            "A) 1980-1990; B) 1970; C) 1990; D) 2000. → A",
            "Q2. Galeano (2010) analisa:\n"
            "A) as veias abertas da América Latina; B) o Renascimento; C) o Iluminismo; D) a Guerra dos Cem Anos. → A",
            "Q3. O Plano Real foi implementado em:\n"
            "A) 1994; B) 1988; C) 2000; D) 1985. → A",
        ],
        "resumo": {
            "regra": "Anos 1970: crescimento + endividamento externo; anos 1980: crise da dívida.",
            "excecoes": "Alguns países passaram por processos específicos (Cuba socialista).",
            "palavra_chave": "Capital estrangeiro + endividamento.",
            "artigo": "FIORI (1999); BATISTA JR. (1988).",
            "mnemonico": "C.E.E.: Capital Externo + Endividamento.",
        },
    },

    60: {
        "tema": "Palestina/Israel e diversidade de narrativas",
        "subtema": "Autonomia + comparação de fontes",
        "habilidade_bncc": "EM13CHS603, EM13CHS604",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item pede a estratégia que promove AUTONOMIA + DIVERSIDADE DE NARRATIVAS. A resposta A "
            "articula versões oficiais e subalternizadas."
        ),
        "como_banca_pensou": (
            "A banca cobra a superação da narrativa única em conflitos complexos."
        ),
        "resolucao": [
            "Descarte B: priorizar 'tradição ocidental' e descartar 'discursos identitários' é excludente.",
            "Descarte C: 'estabilidade' como critério esconde a violência.",
            "Descarte D: 'factual e cronológico' reduz criticidade.",
            "Marque A: comparação de versões oficiais e subalternizadas.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Autonomia crítica = comparar versões oficiais e subalternizadas."),
            "B": ("ERRADA", "Prioridade à tradição ocidental é excludente."),
            "C": ("ERRADA", "'Estabilidade' esconde violações."),
            "D": ("ERRADA", "Factual/cronológico reduz o crítico."),
        },
        "fundamentacao": [
            "SAID, Edward. Orientalismo. São Paulo: Cia. das Letras, 2007.",
            "PAPPÉ, Ilan. A limpeza étnica da Palestina. São Paulo: Sundermann, 2016.",
            "MASALHA, Nur. A Palestina: quatro mil anos de história. São Paulo: Elefante, 2022.",
            "ONU. Resolução 181 (II), 29 de novembro de 1947. Plano de Partilha da Palestina.",
        ],
        "teoria": (
            "O CONFLITO PALESTINA-ISRAEL exige o trabalho pedagógico com MÚLTIPLAS NARRATIVAS. Said "
            "(2007) discute o orientalismo; Pappé (2016) e Masalha (2022) apresentam a história "
            "palestina. A ONU (Res. 181/1947) estabeleceu a partilha, seguida da Nakba (1948). "
            "Direitos humanos e diversidade de narrativas são chaves para autonomia crítica."
        ),
        "padroes_banca": (
            "O INEP cobra pluralismo e crítica de fontes em temas geopolíticos sensíveis."
        ),
        "pegadinhas": [
            "Priorizar narrativa ocidental.",
            "Achar que 'estabilidade' explica conflito.",
            "Reduzir a datas.",
        ],
        "erros_comuns": (
            "Marcar D pela ilusão de 'objetividade cronológica'."
        ),
        "dica_estrategica": (
            "Conflitos complexos = múltiplas narrativas + direitos humanos."
        ),
        "variacao": (
            "(Estilo INEP) Edward Said, em 'Orientalismo' (2007), argumenta que:\n"
            "A) o Oriente é objeto neutro;\n"
            "B) o 'Oriente' é uma construção ocidental que serve à dominação colonial;\n"
            "C) o Ocidente é irrelevante; D) o Egito não interessa. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A Nakba (1948) refere-se a:\n"
            "A) fundação de Israel e êxodo palestino; B) queda de Constantinopla; "
            "C) Revolução Iraniana; D) Guerra do Vietnã. → A",
            "Q2. A ONU aprovou a partilha em:\n"
            "A) 1947; B) 1918; C) 1945; D) 1967. → A",
            "Q3. Ilan Pappé é:\n"
            "A) historiador israelense crítico da política de seu país; B) presidente palestino; "
            "C) diplomata árabe; D) jornalista turco. → A",
        ],
        "resumo": {
            "regra": "Autonomia crítica = confrontar narrativas oficiais e subalternizadas.",
            "excecoes": "Cronologia é importante, mas insuficiente.",
            "palavra_chave": "Narrativas plurais + direitos humanos.",
            "artigo": "SAID (2007); PAPPÉ (2016).",
            "mnemonico": "N.P.D.: Narrativas Plurais + Direitos humanos.",
        },
    },

    61: {
        "tema": "Palestina/Israel - disputa por legitimidade internacional",
        "subtema": "Narrativas em conflitos armados",
        "habilidade_bncc": "EM13CHS604, EM13CHS603",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o OBJETIVO COMUM das partes ao descrever o conflito: legitimidade internacional. "
            "Ambas buscam apoio da comunidade internacional. As demais opções são incorretas ou "
            "estereotipadas."
        ),
        "como_banca_pensou": (
            "A banca destaca a disputa por LEGITIMIDADE como estratégia comum a Estados em conflito."
        ),
        "resolucao": [
            "Descarte A: nenhuma das partes rejeita totalmente a via militar.",
            "Descarte C: 'terroristas' é qualificação usada de forma parcial nas narrativas.",
            "Descarte D: financiamento existe, mas não é objetivo COMUM central.",
            "Marque B: legitimidade internacional.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Nenhuma das partes rejeita in totum soluções militares."),
            "B": ("CORRETA", "Ambas disputam apoio e reconhecimento internacional."),
            "C": ("ERRADA", "'Militantes terroristas' é qualificação parcial e estereotipada."),
            "D": ("ERRADA", "Financiamento é meio; legitimidade é o eixo simbólico da disputa."),
        },
        "fundamentacao": [
            "PAPPÉ, Ilan. A limpeza étnica da Palestina. São Paulo: Sundermann, 2016.",
            "MASALHA, Nur. A Palestina: quatro mil anos de história. São Paulo: Elefante, 2022.",
            "ONU. Resoluções sobre a Palestina (Res. 181/1947; 242/1967; 338/1973; 2334/2016).",
            "SAID, Edward. A questão da Palestina. São Paulo: UNESP, 2012.",
        ],
        "teoria": (
            "Nos conflitos internacionais contemporâneos, a batalha por LEGITIMIDADE é tão importante "
            "quanto a militar. Envolve narrativas, mídia, diplomacia, decisões da ONU, tribunais "
            "internacionais (TPI, CIJ). Ambas as partes no conflito Palestina-Israel buscam converter "
            "sua causa em causa universal, mobilizando aliados, ativistas, mídia global."
        ),
        "padroes_banca": (
            "O INEP cobra leitura crítica de narrativas em disputa."
        ),
        "pegadinhas": [
            "Aceitar qualificações unilaterais.",
            "Reduzir tudo a financiamento.",
            "Ignorar a dimensão simbólica.",
        ],
        "erros_comuns": (
            "Marcar C por reproduzir estereótipos."
        ),
        "dica_estrategica": (
            "Conflitos = disputa por legitimidade + narrativas + fontes plurais."
        ),
        "variacao": (
            "(Estilo INEP) A Resolução 242 (1967) da ONU exige:\n"
            "A) retirada de Israel dos territórios ocupados na Guerra dos Seis Dias;\n"
            "B) fim da ONU; C) invasão do Líbano; D) apenas cessar-fogo unilateral. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. A ONU foi fundada em:\n"
            "A) 1945; B) 1918; C) 1955; D) 1970. → A",
            "Q2. A OLP (Organização para a Libertação da Palestina) foi criada em:\n"
            "A) 1964; B) 1973; C) 1948; D) 1980. → A",
            "Q3. Said (2012) analisa:\n"
            "A) a questão da Palestina; B) a Grécia Antiga; C) o Iluminismo; D) o feudalismo. → A",
        ],
        "resumo": {
            "regra": "Conflito = disputa por legitimidade + reconhecimento internacional.",
            "excecoes": "Legitimidade não substitui direitos humanos.",
            "palavra_chave": "Legitimidade internacional.",
            "artigo": "SAID (2012); ONU Res. 181, 242, 2334.",
            "mnemonico": "L.I.: Legitimidade Internacional.",
        },
    },

    62: {
        "tema": "Imprensa negra brasileira – O Clarim d'Alvorada",
        "subtema": "Mobilização coletiva e Congresso da Mocidade Negra",
        "habilidade_bncc": "EF09HI13, EM13CHS502",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a caracterização da IMPRENSA NEGRA e do Congresso da Mocidade Negra (1929) "
            "como MOBILIZAÇÃO COLETIVA. As demais alternativas restringem a sentenças (A), eleições "
            "(C) ou universidade (D)."
        ),
        "como_banca_pensou": (
            "A banca cobra o reconhecimento da IMPRENSA NEGRA como espaço de organização coletiva."
        ),
        "resolucao": [
            "Descarte A: não se trata de disputa judicial.",
            "Descarte C: não é sobre eleições.",
            "Descarte D: universidade estava fora do alcance de maioria negra em 1929.",
            "Marque B: mobilização coletiva por interesses comuns.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Não é dimensão jurídica principal."),
            "B": ("CORRETA", "Congresso da Mocidade Negra expressa mobilização coletiva pela dignidade e cidadania."),
            "C": ("ERRADA", "Não é sobre representação eleitoral."),
            "D": ("ERRADA", "Universidade era barreira para a maioria."),
        },
        "fundamentacao": [
            "GOMES, Flávio dos Santos. Negros e política (1888-1937). Rio de Janeiro: Jorge Zahar, 2005.",
            "DOMINGUES, Petrônio. Movimento negro brasileiro: alguns apontamentos históricos. Tempo, v. 12, n. 23, 2007.",
            "SEMOG, Éle. Frente Negra Brasileira: depoimentos. São Paulo: Lorosae, 1998.",
            "PEREIRA, Amilcar Araujo. O mundo negro: relações raciais e a constituição do movimento negro contemporâneo no Brasil. Rio de Janeiro: Pallas, 2013.",
        ],
        "teoria": (
            "A IMPRENSA NEGRA (jornais como O Clarim d'Alvorada, Getulino, A Voz da Raça) foi central "
            "para a mobilização política negra no pós-abolição. O 1º Congresso da Mocidade Negra (1929) "
            "foi seguido pela Frente Negra Brasileira (1931-1937). Gomes (2005) e Domingues (2007) "
            "sistematizam o campo."
        ),
        "padroes_banca": (
            "O INEP cobra o reconhecimento da agência negra na Primeira República."
        ),
        "pegadinhas": [
            "Reduzir a mobilização a eleições.",
            "Confundir imprensa negra com jornal comercial.",
            "Achar que universidade era acessível.",
        ],
        "erros_comuns": (
            "Marcar D por associar 'intelectuais' a universidade."
        ),
        "dica_estrategica": (
            "Imprensa negra = mobilização coletiva + dignidade."
        ),
        "variacao": (
            "(Estilo INEP) A Frente Negra Brasileira foi:\n"
            "A) partido negro (1931-1937);\n"
            "B) sindicato pesqueiro; C) grupo religioso; D) associação esportiva. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Petrônio Domingues (2007) sistematiza:\n"
            "A) história do movimento negro brasileiro; B) história econômica; C) micro-história; D) filosofia. → A",
            "Q2. A Frente Negra Brasileira foi criada em:\n"
            "A) 1888; B) 1931; C) 1978; D) 2003. → B",
            "Q3. O Movimento Negro Unificado (MNU) foi criado em:\n"
            "A) 1978; B) 1888; C) 1937; D) 2003. → A",
        ],
        "resumo": {
            "regra": "Imprensa negra = agência política + mobilização coletiva.",
            "excecoes": "Nem toda ação era coordenada; havia grupos concorrentes.",
            "palavra_chave": "Mobilização coletiva + interesses comuns.",
            "artigo": "GOMES (2005); DOMINGUES (2007).",
            "mnemonico": "I.N.M.: Imprensa Negra + Mobilização.",
        },
    },

    63: {
        "tema": "Diretório Pombalino (1755) e imposição linguística",
        "subtema": "Interpretação de fonte colonial + pesquisa documental",
        "habilidade_bncc": "EF07HI09, EM13CHS502; Lei 11.645/08",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a interpretação da fonte (Diretório Pombalino) como IMPOSIÇÃO linguística que "
            "fragilizava culturas indígenas + metodologia adequada (pesquisa documental). Alternativas "
            "erradas amenizam ou desvirtuam."
        ),
        "como_banca_pensou": (
            "A banca cobra a leitura CRÍTICA e DESCOLONIAL da fonte."
        ),
        "resolucao": [
            "Descarte B: 'facilitar comunicação com missionários' apaga a violência.",
            "Descarte C: 'convivência equilibrada' é falso; a fonte proíbe línguas nativas.",
            "Descarte D: 'fortalecer comércio' não é o objetivo do Diretório.",
            "Marque A: fragilizar línguas e culturas para forçar submissão + pesquisa documental.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Diretório Pombalino impôs o português para enfraquecer culturas indígenas; método = pesquisa documental."),
            "B": ("ERRADA", "Não é 'facilitar'; é impor a norma colonial."),
            "C": ("ERRADA", "Não há 'convivência equilibrada' — há proibição."),
            "D": ("ERRADA", "Comércio não é o objetivo do Diretório."),
        },
        "fundamentacao": [
            "CUNHA, Manuela Carneiro da (org.). História dos índios no Brasil. São Paulo: Cia. das Letras, 1992.",
            "ALMEIDA, Rita Heloísa de. O Diretório dos Índios: um projeto de 'civilização' no Brasil do século XVIII. Brasília: UnB, 1997.",
            "MONTEIRO, John Manuel. Negros da terra: índios e bandeirantes nas origens de São Paulo. São Paulo: Cia. das Letras, 1994.",
            "BRASIL. Lei nº 11.645/2008.",
        ],
        "teoria": (
            "O DIRETÓRIO POMBALINO (1755-1758), elaborado por Mendonça Furtado e Marquês de Pombal, "
            "extinguiu as missões jesuíticas e impôs o português como língua obrigatória, "
            "ao lado de outras medidas assimilacionistas (casamentos interétnicos, europeização). Foi "
            "instrumento central do etnocídio colonial. Almeida (1997) e Cunha (1992) analisam."
        ),
        "padroes_banca": (
            "O INEP cobra leitura descolonial de fontes coloniais."
        ),
        "pegadinhas": [
            "Amenizar imposição para 'convivência'.",
            "Achar que é sobre comércio.",
            "Reduzir à religião.",
        ],
        "erros_comuns": (
            "Marcar B por 'facilitar' soar boa."
        ),
        "dica_estrategica": (
            "Fontes coloniais = leitura descolonial + crítica ao etnocentrismo."
        ),
        "variacao": (
            "(Estilo INEP) O Diretório Pombalino foi:\n"
            "A) instrumento assimilacionista contra povos indígenas;\n"
            "B) proteção jesuítica; C) plano socialista; D) reforma agrária. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Marquês de Pombal ministrou D. José I, no séc.:\n"
            "A) XVI; B) XVII; C) XVIII; D) XIX. → C",
            "Q2. Cunha (1992) organiza:\n"
            "A) História dos Índios no Brasil; B) O Alienista; C) Casa-Grande & Senzala; D) Formação do Brasil Contemporâneo. → A",
            "Q3. Monteiro (1994) analisa:\n"
            "A) escravização indígena em SP; B) direito europeu; C) Revolução Francesa; D) genealogia. → A",
        ],
        "resumo": {
            "regra": "Diretório Pombalino = imposição linguística + etnocídio.",
            "excecoes": "Algumas comunidades preservaram línguas resistindo.",
            "palavra_chave": "Imposição + submissão colonial.",
            "artigo": "ALMEIDA (1997); CUNHA (1992).",
            "mnemonico": "P.O.M.B.: Pombal + Obrigatoriedade da Modificação linguística de Base.",
        },
    },

    64: {
        "tema": "Etnocídio e sociodiversidade indígena",
        "subtema": "Presente das línguas indígenas + Diretório",
        "habilidade_bncc": "EF07HI09, EM13CHS502; Lei 11.645/08",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a leitura POSITIVA: sociodiversidade indígena atual (306 povos, 274 línguas). "
            "As demais lançam narrativas de 'desaparecimento' (B), 'assimilacionismo permanente' (C — "
            "meia-verdade) ou 'ineficácia da resistência' (D)."
        ),
        "como_banca_pensou": (
            "A banca cobra reconhecimento da RESISTÊNCIA e DIVERSIDADE indígena atual."
        ),
        "resolucao": [
            "Descarte B: 'desaparecimento' contradiz a persistência.",
            "Descarte C: assimilacionismo é REAL, mas a atividade quer mostrar RESISTÊNCIA e diversidade.",
            "Descarte D: 'ineficácia da resistência' contradiz a persistência.",
            "Marque A: reconhecimento da sociodiversidade indígena no presente.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Levantamento contemporâneo mostra a persistência e sociodiversidade indígena."),
            "B": ("ERRADA", "Povos indígenas não desapareceram."),
            "C": ("ERRADA", "Assimilacionismo persiste, mas o foco da atividade é a sociodiversidade."),
            "D": ("ERRADA", "A resistência foi eficaz em preservar identidades."),
        },
        "fundamentacao": [
            "IBGE. Censo Demográfico 2022 – Indígenas. Rio de Janeiro: IBGE, 2023.",
            "CUNHA, Manuela Carneiro da (org.). História dos índios no Brasil. São Paulo: Cia. das Letras, 1992.",
            "FUNAI. Povos indígenas no Brasil. Brasília, [s.d.].",
            "ISA. Povos Indígenas no Brasil (site).",
        ],
        "teoria": (
            "O Censo IBGE 2022 identificou 1,7 milhão de indígenas no Brasil, mais de 300 povos e cerca "
            "de 270 línguas. Apesar de séculos de etnocídio e assimilacionismo, a sociodiversidade "
            "indígena é fato do presente."
        ),
        "padroes_banca": (
            "O INEP cobra afirmação da PERSISTÊNCIA e da DIVERSIDADE indígena."
        ),
        "pegadinhas": [
            "Aceitar 'desaparecimento'.",
            "Confundir assimilação com destruição total.",
            "Reduzir resistência à falência.",
        ],
        "erros_comuns": (
            "Marcar C por meia-verdade."
        ),
        "dica_estrategica": (
            "Presente = sociodiversidade indígena."
        ),
        "variacao": (
            "(Estilo INEP) O Censo IBGE 2022:\n"
            "A) confirmou a sociodiversidade indígena; B) declarou extinção dos povos originários; "
            "C) reduziu a 5 povos; D) aboliu a categoria indígena. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. A FUNAI foi criada em:\n"
            "A) 1967; B) 1988; C) 1500; D) 2016. → A",
            "Q2. Em 2023, a Funai passou a ser Fundação Nacional dos:\n"
            "A) Povos Indígenas; B) Índios (nome anterior); C) Recursos; D) Museus. → A",
            "Q3. O ISA é:\n"
            "A) Instituto Socioambiental (ONG que documenta povos indígenas);\n"
            "B) Instituto Superior Agronômico; C) Instituto de Sociologia Aplicada; D) órgão do MEC. → A",
        ],
        "resumo": {
            "regra": "Presente = sociodiversidade indígena viva.",
            "excecoes": "Assimilacionismo persiste como ameaça.",
            "palavra_chave": "Sociodiversidade + presente.",
            "artigo": "IBGE Censo 2022; CUNHA (1992).",
            "mnemonico": "S.P.V.: Sociodiversidade + Presente + Vitalidade.",
        },
    },

    65: {
        "tema": "Mídias digitais e ensino do imperialismo",
        "subtema": "HQs como produção estudantil investigativa",
        "habilidade_bncc": "EF09HI16, EM13CHS603",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o par ANÁLISE + PRODUÇÃO como estratégia investigativa. A resposta B combina "
            "análise de fontes + mídias digitais + produção autoral (HQs). As demais são transmissivas "
            "(A: fichamento tradicional; C: reproduzir; D: memorização)."
        ),
        "como_banca_pensou": (
            "A banca cobra postura investigativa + protagonismo estudantil."
        ),
        "resolucao": [
            "Descarte A: 'textos tradicionais + fichamento' = ensino transmissivo.",
            "Descarte C: 'reproduzir narrativas' contradiz postura crítica.",
            "Descarte D: memorização não é investigativa.",
            "Marque B: análise + produção de HQs = investigação com autoria.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Modelo tradicional, não investigativo."),
            "B": ("CORRETA", "Análise + produção autoral (HQs) = postura investigativa e protagonismo."),
            "C": ("ERRADA", "'Reproduzir narrativas' contradiz a chave crítica."),
            "D": ("ERRADA", "Memorização não é investigação."),
        },
        "fundamentacao": [
            "SCHMIDT, Maria Auxiliadora; CAINELLI, Marlene. Ensinar História. São Paulo: Scipione, 2018.",
            "SAID, Edward. Cultura e imperialismo. São Paulo: Cia. das Letras, 2011.",
            "MOSCOSO PANDO, María. Ms. Marvel and Postcolonial Representation. Journal of Popular Culture, v. 57, n. 4, 2024.",
            "HOBSBAWM, Eric. A era dos impérios. Rio de Janeiro: Paz e Terra, 2014.",
        ],
        "teoria": (
            "Mídias digitais e audiovisuais (séries, filmes, HQs) são fontes históricas legítimas se "
            "analisadas criticamente. Said (2011) mostra como culturas coloniais e anticoloniais "
            "disputam narrativas. Produção estudantil autoral (HQs) fortalece a apropriação crítica do "
            "conhecimento."
        ),
        "padroes_banca": (
            "O INEP cobra estratégias investigativas + autoria estudantil."
        ),
        "pegadinhas": [
            "Reduzir aula a fichamento.",
            "Confundir 'reproduzir' com 'analisar'.",
            "Achar que memorização é investigação.",
        ],
        "erros_comuns": (
            "Marcar A por hábito acadêmico do fichamento."
        ),
        "dica_estrategica": (
            "Investigativo = analisar + produzir + socializar."
        ),
        "variacao": (
            "(Estilo INEP) A partição da Índia (1947) resultou em:\n"
            "A) criação de Índia e Paquistão + deslocamentos massivos;\n"
            "B) unificação do sul-asiático; C) fim do imperialismo britânico global; D) criação da União Soviética. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Gandhi liderou a independência da:\n"
            "A) Índia; B) China; C) África do Sul (após); D) Japão. → A (foi ativo na África do Sul também)",
            "Q2. Hobsbawm chama o período 1875-1914 de:\n"
            "A) Era dos Impérios; B) Belle Époque exclusiva; C) Era das Revoluções; D) Era do Capital. → A",
            "Q3. Said (2011) discute:\n"
            "A) cultura e imperialismo; B) Iluminismo; C) Reforma; D) feudalismo. → A",
        ],
        "resumo": {
            "regra": "Postura investigativa = análise crítica + autoria estudantil.",
            "excecoes": "Aulas expositivas complementam, sem substituir.",
            "palavra_chave": "Análise + produção autoral.",
            "artigo": "SCHMIDT & CAINELLI (2018); SAID (2011).",
            "mnemonico": "A.P.S.: Analisar + Produzir + Socializar.",
        },
    },

    66: {
        "tema": "Guerra do Vietnã e resistência popular",
        "subtema": "Táticas de guerrilha + apoio da população",
        "habilidade_bncc": "EF09HI24, EM13CHS604",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a chave explicativa da resistência vietnamita: TÁTICAS ALTERNATIVAS + CONHECIMENTO "
            "DO TERRITÓRIO + APOIO POPULAR. Distratores negam ou invertem esses fatores."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão de que impérios podem ser derrotados por resistências "
            "assimétricas apoiadas em base social."
        ),
        "resolucao": [
            "Descarte A: superioridade tecnológica dos EUA NÃO derrotou a resistência.",
            "Descarte C: os vietnamitas não dependiam de 'armamentos pesados'.",
            "Descarte D: houve apoio internacional (URSS, China, movimentos pacifistas).",
            "Marque B: táticas alternativas + território + população.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Superioridade tecnológica dos EUA fracassou frente à resistência popular."),
            "B": ("CORRETA", "Guerrilha, conhecimento do território e apoio popular explicam a resistência vietnamita."),
            "C": ("ERRADA", "Vietnamitas mobilizavam armamento leve, não pesado."),
            "D": ("ERRADA", "Havia apoio internacional (URSS, China, movimento antiguerra)."),
        },
        "fundamentacao": [
            "HOBSBAWM, Eric. Era dos extremos. São Paulo: Cia. das Letras, 1995.",
            "YOUNG, Marilyn. The Vietnam Wars: 1945-1990. Nova York: HarperCollins, 1991.",
            "WESTAD, Odd Arne. A Guerra Fria: uma nova história. Rio de Janeiro: Zahar, 2018.",
            "VIZENTINI, Paulo F. A Guerra do Vietnã. São Paulo: Ática, 2004.",
        ],
        "teoria": (
            "A Guerra do Vietnã (1955-1975) demonstrou que superioridade tecnológica NÃO garante vitória "
            "militar contra resistência apoiada popularmente. O Vietcong e o Vietnã do Norte, sob "
            "liderança de Ho Chi Minh, empregaram guerrilha (túneis de Cu Chi), conhecimento do terreno "
            "e mobilização camponesa para derrotar os EUA."
        ),
        "padroes_banca": (
            "O INEP contrasta narrativa tecnicista da guerra vs. leitura social-política."
        ),
        "pegadinhas": [
            "Achar que bomba explica tudo.",
            "Ignorar o apoio popular.",
            "Reduzir a fenômeno isolado.",
        ],
        "erros_comuns": (
            "Marcar C por associar 'guerra' a 'armamento pesado'."
        ),
        "dica_estrategica": (
            "Guerras assimétricas = território + população + tática."
        ),
        "variacao": (
            "(Estilo INEP) A Ofensiva do Tet (1968) foi:\n"
            "A) vitória militar do Vietnã do Sul;\n"
            "B) ofensiva coordenada do Vietcong que marcou virada política da guerra;\n"
            "C) derrota total do Norte; D) ataque japonês. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Os EUA retiraram-se do Vietnã em:\n"
            "A) 1954; B) 1973-1975; C) 1968; D) 1980. → B",
            "Q2. Os túneis de Cu Chi eram:\n"
            "A) fortificações vietcongues; B) monumentos religiosos; C) prisões americanas; D) minas de ouro. → A",
            "Q3. Ho Chi Minh foi líder:\n"
            "A) do Vietnã Norte (Viet Minh); B) do Camboja; C) da Coreia; D) do Laos. → A",
        ],
        "resumo": {
            "regra": "Resistência assimétrica bem-sucedida = território + tática + apoio popular.",
            "excecoes": "Superioridade tecnológica ainda pesa em batalhas convencionais.",
            "palavra_chave": "Guerrilha + território + população.",
            "artigo": "HOBSBAWM (1995); YOUNG (1991); VIZENTINI (2004).",
            "mnemonico": "T.T.P.: Tática + Território + População.",
        },
    },

    67: {
        "tema": "Imperialismo asiático (séc. XIX-XX) e júri simulado",
        "subtema": "Complexidade colonial + metodologia dialógica",
        "habilidade_bncc": "EF08HI19, EM13CHS604",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra pareamento OBJETIVO + METODOLOGIA. A resposta A articula complexidade "
            "(ocupação + exploração + tensões) + júri simulado (dialógico). Distratores amenizam "
            "('consenso', 'harmônico', 'cordial')."
        ),
        "como_banca_pensou": (
            "A banca contrasta abordagens críticas (complexidade + tensão) com narrativas amenizadoras."
        ),
        "resolucao": [
            "Descarte B: 'consenso' e 'pacificação' apagam a violência colonial.",
            "Descarte C: 'harmônico' contradiz a exploração.",
            "Descarte D: 'cordialidade' apaga o conflito.",
            "Marque A: complexidade + júri simulado.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Complexidade da ocupação e exploração + júri simulado como metodologia dialógica."),
            "B": ("ERRADA", "Não houve consenso; a colonização foi violenta."),
            "C": ("ERRADA", "'Harmonia' inverte a experiência histórica."),
            "D": ("ERRADA", "'Cordialidade' oculta violações."),
        },
        "fundamentacao": [
            "HOBSBAWM, Eric. A era dos impérios. Rio de Janeiro: Paz e Terra, 2014.",
            "SAID, Edward. Orientalismo. São Paulo: Cia. das Letras, 2007.",
            "ANDERSON, Benedict. Comunidades imaginadas. São Paulo: Cia. das Letras, 2008.",
            "SCHMIDT, Maria Auxiliadora; CAINELLI, Marlene. Ensinar História. São Paulo: Scipione, 2018.",
        ],
        "teoria": (
            "O IMPERIALISMO clássico (1875-1914) partilhou África e Ásia entre potências europeias. "
            "Hobsbawm (2014) analisa o período; Said (2007) mostra a construção discursiva do 'oriental' "
            "para justificar a dominação. Júris simulados em sala mobilizam papéis, argumentação e "
            "posicionamento crítico dos estudantes."
        ),
        "padroes_banca": (
            "O INEP cobra articulação COMPLEXIDADE + DIALOGICIDADE."
        ),
        "pegadinhas": [
            "Amenizar violência colonial.",
            "Confundir mural com posicionamento crítico.",
            "Achar que mapa esgota o tema.",
        ],
        "erros_comuns": (
            "Marcar D por associar 'mapear' a boa didática."
        ),
        "dica_estrategica": (
            "Imperialismo = tensão + violência + resistência (nunca cordialidade)."
        ),
        "variacao": (
            "(Estilo INEP) A Conferência de Berlim (1884-1885):\n"
            "A) partilhou a África entre potências europeias;\n"
            "B) fim do imperialismo; C) libertou o Congo; D) fundou a ONU. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. A Guerra do Ópio (1839-1842):\n"
            "A) forçou a China a abrir portos aos britânicos; B) expulsou os europeus; "
            "C) unificou a Coreia; D) libertou Hong Kong. → A",
            "Q2. Anderson (2008) descreve nações como:\n"
            "A) comunidades imaginadas; B) sociedades naturais; C) unidades biológicas; D) unidades religiosas. → A",
            "Q3. Said (2007) tematiza:\n"
            "A) orientalismo como construção discursiva; B) marxismo puro; C) neoliberalismo; D) positivismo. → A",
        ],
        "resumo": {
            "regra": "Imperialismo = ocupação + exploração + resistência.",
            "excecoes": "Formas variam entre colonização direta, protetorado, esferas de influência.",
            "palavra_chave": "Complexidade + tensão + júri.",
            "artigo": "HOBSBAWM (2014); SAID (2007).",
            "mnemonico": "C.O.E.: Complexidade, Ocupação, Exploração.",
        },
    },

    68: {
        "tema": "Guerra do Vietnã e Guerra Fria",
        "subtema": "Disputas bipolares e blocos",
        "habilidade_bncc": "EF09HI24, EM13CHS604",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o vínculo do Vietnã com a Guerra Fria: blocos capitalista e socialista financiavam "
            "grupos conforme seus interesses. As demais alternativas cometem erros históricos (Big Stick, "
            "URSS 'colonialista', FLN 'financiada pelos EUA')."
        ),
        "como_banca_pensou": (
            "A banca cobra articulação Guerra Fria + descolonização."
        ),
        "resolucao": [
            "Descarte B: Big Stick é política estadunidense do início do séc. XX na América Latina.",
            "Descarte C: URSS não era 'governo colonialista'; apoiava anticoloniais.",
            "Descarte D: FLN foi frente vietnamita/argelina anticolonial, não pró-EUA.",
            "Marque A: disputa entre blocos.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Ambos os blocos financiavam grupos conforme interesses geopolíticos."),
            "B": ("ERRADA", "Big Stick é anterior e voltada à América Latina."),
            "C": ("ERRADA", "URSS apoiava anticolonialismo, não era colonialista."),
            "D": ("ERRADA", "A FLN vietnamita era anticolonial e não pró-EUA."),
        },
        "fundamentacao": [
            "HOBSBAWM, Eric. Era dos extremos. São Paulo: Cia. das Letras, 1995.",
            "WESTAD, Odd Arne. A Guerra Fria: uma nova história. Rio de Janeiro: Zahar, 2018.",
            "VIZENTINI, Paulo F. A Guerra do Vietnã. São Paulo: Ática, 2004.",
            "GADDIS, John L. História da Guerra Fria. Rio de Janeiro: Nova Fronteira, 2006.",
        ],
        "teoria": (
            "A GUERRA FRIA estruturou-se em disputas bipolares (EUA x URSS) que atravessavam as guerras "
            "de descolonização. No Vietnã, EUA apoiaram os franceses (1946-1954), depois o regime do "
            "Vietnã do Sul; URSS e China apoiaram o Norte. A guerra articulou anticolonialismo, "
            "nacionalismo, socialismo e disputa geopolítica."
        ),
        "padroes_banca": (
            "O INEP cobra domínio dos conceitos-chave da Guerra Fria."
        ),
        "pegadinhas": [
            "Confundir Big Stick com Doutrina Truman.",
            "Chamar URSS de 'colonialista'.",
            "Inverter alianças.",
        ],
        "erros_comuns": (
            "Marcar D por não conhecer a FLN."
        ),
        "dica_estrategica": (
            "Guerra Fria = disputa por hegemonia via clientes locais."
        ),
        "variacao": (
            "(Estilo INEP) A Doutrina Truman (1947):\n"
            "A) contenção do comunismo;\n"
            "B) fim da ONU; C) descolonização africana; D) unificação alemã. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Big Stick foi política de:\n"
            "A) Theodore Roosevelt para América Latina; B) Truman para Ásia; "
            "C) Reagan para África; D) Wilson para Europa. → A",
            "Q2. A FLN argelina lutou contra:\n"
            "A) França (independência); B) EUA; C) URSS; D) Alemanha. → A",
            "Q3. Ho Chi Minh fundou o:\n"
            "A) Viet Minh (1941); B) Kuomintang; C) Sino-japonês; D) EZLN. → A",
        ],
        "resumo": {
            "regra": "Guerra Fria = disputa bipolar via clientes locais.",
            "excecoes": "Não-alinhados (Bandung, 1955) tentavam via alternativa.",
            "palavra_chave": "Blocos + interesses.",
            "artigo": "HOBSBAWM (1995); WESTAD (2018).",
            "mnemonico": "B.I.C.: Blocos, Interesses, Clientes.",
        },
    },

    69: {
        "tema": "Independência da Índia e avaliação processual",
        "subtema": "Análise contínua de fontes múltiplas",
        "habilidade_bncc": "EF09HI25, EM13CHS603",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra AVALIAÇÃO PROCESSUAL + tema da independência da Índia. A resposta D usa "
            "análise de charges/documentos/mapas nas DIFERENTES etapas + articula com rivalidades "
            "étnicas e interesses britânicos. Alternativas erradas concentram em um momento (fim, "
            "início) ou desviam o tema (domínio francês)."
        ),
        "como_banca_pensou": (
            "A banca cobra o entendimento de que processual = ao LONGO da sequência + tema coerente."
        ),
        "resolucao": [
            "Descarte A: 'domínio francês' desvia (Índia foi britânica).",
            "Descarte B: 'início' + lista de exercícios = diagnóstica.",
            "Descarte C: 'final' + prova = somativa.",
            "Marque D: análise contínua de múltiplas fontes = processual.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Índia foi britânica, não francesa."),
            "B": ("ERRADA", "'Início' + lista = diagnóstica, não processual."),
            "C": ("ERRADA", "'Final' + prova = somativa."),
            "D": ("CORRETA", "Análise de fontes ao longo da sequência = processual."),
        },
        "fundamentacao": [
            "PERRENOUD, Philippe. Avaliação: da excelência à regulação. Porto Alegre: Artmed, 1999.",
            "GUHA, Ramachandra. Índia depois de Gandhi. São Paulo: Cia. das Letras, 2020.",
            "CHAKRABARTY, Dipesh. Provincializando a Europa. Belo Horizonte: UFMG, 2019.",
            "HOBSBAWM, Eric. Era dos extremos. São Paulo: Cia. das Letras, 1995.",
        ],
        "teoria": (
            "A INDEPENDÊNCIA DA ÍNDIA (1947) resultou de longa mobilização (Gandhi, Nehru, Jinnah). A "
            "partição em Índia e Paquistão gerou massacres com estimativas superiores a 1 milhão de "
            "mortos e deslocamento de 15 milhões. Guha (2020) e Chakrabarty (2019) são referências. "
            "Avaliação processual acompanha a construção do conhecimento em cada etapa."
        ),
        "padroes_banca": (
            "O INEP cobra pareamento avaliação-tema."
        ),
        "pegadinhas": [
            "Confundir Índia com Indochina francesa.",
            "Concentrar avaliação em um só momento.",
            "Achar que prova é 'processual'.",
        ],
        "erros_comuns": (
            "Marcar C por associar 'documentário' a processo."
        ),
        "dica_estrategica": (
            "Processual = ao LONGO da sequência + múltiplas fontes."
        ),
        "variacao": (
            "(Estilo INEP) A partição de 1947:\n"
            "A) unificou o sul-asiático;\n"
            "B) criou Índia e Paquistão; C) libertou Bangladesh imediatamente; D) fundou a Índia socialista. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Bangladesh independente foi em:\n"
            "A) 1947; B) 1971; C) 1965; D) 2000. → B",
            "Q2. Chakrabarty (2019) propõe:\n"
            "A) provincializar a Europa; B) universalizar Kant; C) esquecer a Índia; D) apenas nomeação. → A",
            "Q3. Guha (2020) é referência para:\n"
            "A) Índia contemporânea; B) Roma antiga; C) Renascimento italiano; D) Guerra do Peloponeso. → A",
        ],
        "resumo": {
            "regra": "Avaliação processual = múltiplas etapas + fontes diversas.",
            "excecoes": "Um instrumento pode compor várias etapas se ajustado.",
            "palavra_chave": "Charges + documentos + mapas + etapas.",
            "artigo": "PERRENOUD (1999).",
            "mnemonico": "P.E.M.: Processual + Etapas + Múltiplas fontes.",
        },
    },

    70: {
        "tema": "Feminismo interseccional e Chica da Silva",
        "subtema": "Continuidades das desigualdades de gênero",
        "habilidade_bncc": "EF08HI19, EM13CHS502",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a leitura FEMINISTA INTERSECCIONAL: relações de poder persistem em novas formas. "
            "As demais alternativas ora romantizam ('liberdade consagrada', 'rainha do lar'), ora "
            "desconectam gênero de política."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão de que a desigualdade de gênero é histórica e persistente, "
            "manifestando-se em interseccionalidade com raça e classe."
        ),
        "resolucao": [
            "Descarte A: 'liberdade consagrada' desconhece desigualdades atuais.",
            "Descarte C: 'rainha do lar' romantiza a divisão tradicional.",
            "Descarte D: desconectar gênero de política é essencialismo.",
            "Marque B: relações de poder persistem em intersecções.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Desigualdades atuais persistem; não há 'liberdade consagrada' plena."),
            "B": ("CORRETA", "Formas conjugais mudam, mas relações de poder interseccionais persistem."),
            "C": ("ERRADA", "'Rainha do lar' é essencialismo tradicional."),
            "D": ("ERRADA", "Desconecta gênero de política."),
        },
        "fundamentacao": [
            "FURTADO, Júnia Ferreira. Chica da Silva e o contratador de diamantes. São Paulo: Cia. das Letras, 2003.",
            "CRENSHAW, Kimberlé. Documento para o encontro de especialistas em aspectos da discriminação racial relativos ao gênero. Revista Estudos Feministas, v. 10, n. 1, 2002.",
            "COLLINS, Patricia Hill. Pensamento feminista negro. São Paulo: Boitempo, 2019.",
            "SCOTT, Joan W. Gênero: uma categoria útil de análise histórica. Educação e Realidade, v. 20, n. 2, 1995.",
        ],
        "teoria": (
            "A HISTORIOGRAFIA FEMINISTA (Scott, 1995; Perrot, 2007) e a INTERSECCIONALIDADE (Crenshaw, "
            "2002; Collins, 2019) mostram que as desigualdades de gênero se articulam a raça, classe e "
            "outros marcadores. O caso de Chica da Silva (Furtado, 2003) exemplifica a complexidade: "
            "mulher negra alforriada que negociou dentro de um sistema patriarcal e escravista."
        ),
        "padroes_banca": (
            "O INEP cobra leitura crítica e interseccional."
        ),
        "pegadinhas": [
            "Achar que direitos = plenitude alcançada.",
            "Romantizar 'lar' como poder feminino.",
            "Desconectar corpo e política.",
        ],
        "erros_comuns": (
            "Marcar A por otimismo ingênuo sobre direitos atuais."
        ),
        "dica_estrategica": (
            "Feminismo interseccional = poder persiste em camadas + mudanças históricas."
        ),
        "variacao": (
            "(Estilo INEP) Crenshaw (2002) formula:\n"
            "A) interseccionalidade;\n"
            "B) marxismo estruturalista; C) positivismo; D) fenomenologia. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Junia Furtado (2003) reescreve:\n"
            "A) o mito sensualizado de Chica da Silva; B) a vida de Anita Garibaldi; "
            "C) a biografia de Zumbi; D) a de Getúlio Vargas. → A",
            "Q2. Collins (2019) discute:\n"
            "A) pensamento feminista negro; B) direito romano; C) genealogia inca; D) micro-história europeia. → A",
            "Q3. Scott (1995) trata gênero como:\n"
            "A) sinônimo de biologia; B) categoria útil de análise histórica; C) mera cultura; D) irrelevante. → B",
        ],
        "resumo": {
            "regra": "Interseccionalidade = raça + gênero + classe.",
            "excecoes": "Direitos avançaram, mas desigualdades persistem.",
            "palavra_chave": "Poder + intersecções + camadas.",
            "artigo": "CRENSHAW (2002); COLLINS (2019); SCOTT (1995).",
            "mnemonico": "R.G.C.: Raça, Gênero, Classe.",
        },
    },

    71: {
        "tema": "Chica da Silva, gênero e violência colonial",
        "subtema": "Cidadania e representações históricas",
        "habilidade_bncc": "EF08HI11, EM13CHS502",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão de que a sociedade colonial exercia VIOLÊNCIA e AUTORITARISMO "
            "sobre corpos femininos negros, com efeitos nas condições contemporâneas. Distratores "
            "romantizam ('encantos') ou proclamam cidadania plena de forma anacrônica."
        ),
        "como_banca_pensou": (
            "A banca cobra a leitura crítica da colonialidade + gênero + continuidades no presente."
        ),
        "resolucao": [
            "Descarte A: 'graças aos seus encantos' romantiza e reduz a agência de Chica.",
            "Descarte B: 'cidadania participativa' é anacronismo (não existia no séc. XVIII).",
            "Descarte C: afirmar 'cidadania plena' hoje contradiz a realidade estatística.",
            "Marque D: violência + autoritarismo + reflexo na contemporaneidade.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Reduz Chica a 'encantos'; anula sua agência."),
            "B": ("ERRADA", "Anacronismo: 'cidadania participativa' não existia em 1750."),
            "C": ("ERRADA", "'Cidadania plena' hoje contradiz desigualdades persistentes."),
            "D": ("CORRETA", "Violência de gênero na colônia + reflexos contemporâneos."),
        },
        "fundamentacao": [
            "FURTADO, Júnia Ferreira. Chica da Silva e o contratador de diamantes. São Paulo: Cia. das Letras, 2003.",
            "MARQUESE, Rafael. Escravidão e política no Brasil. São Paulo: Contexto, 2019.",
            "GONZALEZ, Lélia. Por um feminismo afro-latino-americano. Rio de Janeiro: Zahar, 2020.",
            "SCHWARCZ, Lilia. Sobre o autoritarismo brasileiro. São Paulo: Cia. das Letras, 2019.",
        ],
        "teoria": (
            "A escravidão colonial atuou sobre corpos por meio de violência sexual, patriarcal e "
            "religiosa. Lélia Gonzalez (2020) formula 'amefricanidade' para pensar a herança afro-"
            "latino-americana. Schwarcz (2019) discute o autoritarismo persistente na sociedade "
            "brasileira. A leitura de Chica da Silva à luz da colonialidade permite reconhecer "
            "continuidades históricas."
        ),
        "padroes_banca": (
            "O INEP cobra passado-presente + colonialidade + gênero."
        ),
        "pegadinhas": [
            "Romantizar 'encantos'.",
            "Anacronismo com 'cidadania'.",
            "Proclamar plenitude atual de direitos.",
        ],
        "erros_comuns": (
            "Marcar B por soar democrático."
        ),
        "dica_estrategica": (
            "Análise crítica = violência + estrutura + reflexo no presente."
        ),
        "variacao": (
            "(Estilo INEP) Lélia Gonzalez (2020) propõe:\n"
            "A) amefricanidade;\n"
            "B) branquitude neutra; C) meritocracia; D) universalismo iluminista. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Schwarcz (2019) discute:\n"
            "A) autoritarismo brasileiro; B) Renascimento italiano; C) direito grego; D) micro-história. → A",
            "Q2. Marquese (2019) analisa:\n"
            "A) escravidão e política no Brasil; B) genealogia inca; C) filosofia analítica; D) matemática financeira. → A",
            "Q3. Chica da Silva viveu em:\n"
            "A) Arraial do Tijuco (Diamantina, MG), séc. XVIII; B) Salvador, séc. XVI; C) São Paulo, séc. XIX; D) Rio, séc. XVII. → A",
        ],
        "resumo": {
            "regra": "Colonialidade + gênero + continuidades no presente.",
            "excecoes": "Trajetórias individuais podem apresentar negociação, sem anular a estrutura.",
            "palavra_chave": "Violência + autoritarismo + presente.",
            "artigo": "FURTADO (2003); GONZALEZ (2020); SCHWARCZ (2019).",
            "mnemonico": "V.A.P.: Violência, Autoritarismo, Presente.",
        },
    },

    72: {
        "tema": "Tereza de Benguela e invisibilização de mulheres negras",
        "subtema": "Protagonismo político anterior ao sufrágio",
        "habilidade_bncc": "EF08HI19, EM13CHS502; Lei 12.987/2014",
        "gabarito": "A",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra reconhecimento do PROTAGONISMO POLÍTICO de mulheres negras antes do sufrágio, "
            "e da INVISIBILIZAÇÃO histórica. Alternativas erradas atribuem origem a leis eleitorais "
            "restritivas do séc. XIX (B), isolamento (C) ou aumento proporcional desde 1932 (D — falso "
            "estatisticamente)."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão do quilombo do Piolho/Quariterê e da liderança de Tereza de "
            "Benguela como POLÍTICA, contra o apagamento historiográfico."
        ),
        "resolucao": [
            "Descarte B: leis eleitorais restritivas do séc. XIX não são a origem da baixa representação atual.",
            "Descarte C: Tereza NÃO foi caso isolado.",
            "Descarte D: aumento não é proporcional.",
            "Marque A: mulheres negras exerceram poder político + invisibilizadas.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Tereza de Benguela e outras mulheres negras exerceram poder político (Quariterê); foram invisibilizadas."),
            "B": ("ERRADA", "Origem é anterior e mais estrutural."),
            "C": ("ERRADA", "Havia outras: Dandara, Aqualtune, Luísa Mahin, Antonieta de Barros etc."),
            "D": ("ERRADA", "Representação segue subrepresentada, mesmo com Lei de Cotas."),
        },
        "fundamentacao": [
            "BRASIL. Lei nº 12.987, de 2 de junho de 2014. Institui o Dia Nacional de Tereza de Benguela e da Mulher Negra.",
            "SCHUMAHER, Schuma; BRAZIL, Erico Vital. Dicionário Mulheres do Brasil. Rio de Janeiro: Zahar, 2000.",
            "GOMES, Flávio dos Santos. Palmares. São Paulo: Contexto, 2005.",
            "CARNEIRO, Sueli. Racismo, sexismo e desigualdade no Brasil. São Paulo: Selo Negro, 2011.",
        ],
        "teoria": (
            "TEREZA DE BENGUELA liderou o quilombo do Quariterê (atual Mato Grosso) no séc. XVIII "
            "por cerca de 20 anos após a morte do marido, José Piolho. Instituiu uma estrutura política "
            "com parlamento e defesa. A Lei 12.987/2014 consagrou o 25 de julho como Dia Nacional de "
            "Tereza de Benguela e da Mulher Negra."
        ),
        "padroes_banca": (
            "O INEP cobra reconhecimento do protagonismo político negro feminino."
        ),
        "pegadinhas": [
            "Reduzir Tereza a caso isolado.",
            "Achar que representatividade é proporcional.",
            "Confundir origem estrutural com norma pontual.",
        ],
        "erros_comuns": (
            "Marcar D por otimismo estatístico."
        ),
        "dica_estrategica": (
            "Reconhecer + nomear + articular ao presente."
        ),
        "variacao": (
            "(Estilo INEP) Antonieta de Barros foi:\n"
            "A) primeira deputada negra do Brasil (SC, 1934);\n"
            "B) presidente do STF; C) ministra em 1900; D) senadora em 1888. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Sueli Carneiro (2011) discute:\n"
            "A) racismo, sexismo, desigualdade no Brasil; B) direito romano; C) filosofia grega; D) matemática. → A",
            "Q2. O Dia Nacional de Tereza de Benguela e da Mulher Negra é:\n"
            "A) 25 de julho; B) 20 de novembro; C) 8 de março; D) 13 de maio. → A",
            "Q3. Quariterê era:\n"
            "A) quilombo em Mato Grosso (séc. XVIII); B) missão jesuítica no Paraguai; "
            "C) capitania do Nordeste; D) aldeia guarani. → A",
        ],
        "resumo": {
            "regra": "Mulheres negras exerceram poder político; foram invisibilizadas.",
            "excecoes": "Historiografia recente vem reconstruindo trajetórias.",
            "palavra_chave": "Protagonismo + invisibilização.",
            "artigo": "Lei 12.987/2014; SCHUMAHER & BRAZIL (2000).",
            "mnemonico": "T.B.I.: Tereza de Benguela, Invisibilizada mas Insurgente.",
        },
    },

    73: {
        "tema": "Negacionismo e método histórico",
        "subtema": "Ensino de História e crítica de fontes",
        "habilidade_bncc": "EM13CHS603, EM13CHS604",
        "gabarito": "C",
        "nivel": "Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a resposta correta ao NEGACIONISMO: articular ENSINO + PESQUISA + crítica de "
            "fontes (identificação, veracidade, usos). Alternativas erradas ora reduzem a maquete (A), "
            "propõem acriticidade (B) ou terceirizam para a internet (D)."
        ),
        "como_banca_pensou": (
            "A banca cobra o método histórico como resposta ao negacionismo."
        ),
        "resolucao": [
            "Descarte A: 'cartazes e maquetes' não enfrenta negacionismo.",
            "Descarte B: 'transmissão acrítica' contradiz a chave.",
            "Descarte D: internet como fonte principal para vestibular desloca o tema.",
            "Marque C: ensino + pesquisa + crítica de fontes.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Não basta produzir cartazes; falta crítica de fonte."),
            "B": ("ERRADA", "'Acrítico' contradiz o método."),
            "C": ("CORRETA", "Método histórico = identificar, verificar e problematizar fontes."),
            "D": ("ERRADA", "'Fatos para vestibular' desloca a chave do enfrentamento do negacionismo."),
        },
        "fundamentacao": [
            "BLOCH, Marc. Apologia da história ou o ofício de historiador. Rio de Janeiro: Zahar, 2001.",
            "TRAVERSO, Enzo. O passado, modos de usar. Lisboa: Unipop, 2012.",
            "GAGO, Antonio Muñoz. Negacionismo histórico. Madri: Trotta, 2020.",
            "BITTENCOURT, Circe. Ensino de História: fundamentos e métodos. São Paulo: Cortez, 2011.",
        ],
        "teoria": (
            "O NEGACIONISMO (Traverso, 2012) instrumentaliza a dúvida contra evidências consolidadas. "
            "A resposta pedagógica é ensinar o MÉTODO HISTÓRICO (Bloch, 2001): identificar autoria, "
            "cronologia, contexto, intencionalidade e cotejar fontes. A Lei 7.716/89 tipifica negação "
            "do Holocausto como racismo."
        ),
        "padroes_banca": (
            "O INEP cobra ensino como resposta ao negacionismo."
        ),
        "pegadinhas": [
            "Reduzir a maquete.",
            "Aceitar transmissão acrítica.",
            "Terceirizar para internet.",
        ],
        "erros_comuns": (
            "Marcar A por acreditar em 'atividade prática' isolada."
        ),
        "dica_estrategica": (
            "Negacionismo → método histórico + crítica de fontes."
        ),
        "variacao": (
            "(Estilo INEP) A Lei 7.716/89 (Lei Caó):\n"
            "A) tipifica crimes de racismo, inclusive negação do Holocausto;\n"
            "B) instituiu FGTS; C) reformou CLT; D) criou o SUS. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Marc Bloch é fundador dos:\n"
            "A) Annales; B) positivismo; C) marxismo ortodoxo; D) estruturalismo linguístico. → A",
            "Q2. Traverso (2012) discute:\n"
            "A) usos públicos do passado; B) demografia; C) filologia clássica; D) direito civil. → A",
            "Q3. Método histórico envolve:\n"
            "A) crítica interna e externa das fontes; B) crença sem checagem; "
            "C) memorização de datas; D) transcrição literal. → A",
        ],
        "resumo": {
            "regra": "Negacionismo é enfrentado com método histórico e crítica de fontes.",
            "excecoes": "Educação midiática amplia o combate.",
            "palavra_chave": "Ensino + pesquisa + crítica.",
            "artigo": "BLOCH (2001); TRAVERSO (2012); Lei 7.716/89.",
            "mnemonico": "I.V.U.: Identificar, Verificar, Usos das fontes.",
        },
    },

    74: {
        "tema": "Decolonialidade – historiografia latino-americana",
        "subtema": "Grupo Modernidade/Colonialidade (anos 1990)",
        "habilidade_bncc": "EM13CHS502, EM13CHS604",
        "gabarito": "B",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra o nome da corrente: DECOLONIALIDADE (Quijano, Mignolo, Dussel, Grosfoguel, "
            "Maldonado-Torres). As demais são correntes diversas (historicismo, marxismo, história "
            "cultural)."
        ),
        "como_banca_pensou": (
            "A banca cobra a identificação da corrente pelos seus objetivos: RUPTURA EPISTEMOLÓGICA "
            "com a modernidade eurocêntrica."
        ),
        "resolucao": [
            "Descarte A: historicismo é anterior.",
            "Descarte C: marxismo tem foco econômico-social.",
            "Descarte D: história cultural é corrente distinta.",
            "Marque B: decolonialidade.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Historicismo é do séc. XIX (Ranke, Meinecke)."),
            "B": ("CORRETA", "Decolonialidade emerge nos anos 1990 com o Grupo Modernidade/Colonialidade."),
            "C": ("ERRADA", "Marxismo é anterior e tem outro foco."),
            "D": ("ERRADA", "História cultural é corrente diversa (Chartier, Ginzburg)."),
        },
        "fundamentacao": [
            "QUIJANO, Aníbal. Colonialidade do poder, eurocentrismo e América Latina. In: LANDER, E. (org.). A colonialidade do saber. Buenos Aires: CLACSO, 2005.",
            "MIGNOLO, Walter. Colonialidade: o lado mais escuro da modernidade. Revista Brasileira de Ciências Sociais, v. 32, n. 94, 2017.",
            "DUSSEL, Enrique. 1492: o encobrimento do outro. Petrópolis: Vozes, 1993.",
            "GROSFOGUEL, Ramón. Para descolonizar os estudos de economia política. Revista Crítica de Ciências Sociais, n. 80, 2008.",
        ],
        "teoria": (
            "A DECOLONIALIDADE (Quijano, 2005; Mignolo, 2017) é corrente latino-americana de crítica à "
            "COLONIALIDADE DO PODER, do SABER e do SER (Maldonado-Torres). Distinta do 'pós-colonial' "
            "(Said, Bhabha, Spivak), enfatiza continuidades da colonialidade após a independência "
            "formal. Dialoga com Walter Rodney e Frantz Fanon."
        ),
        "padroes_banca": (
            "O INEP cobra reconhecimento das correntes decoloniais como marco recente."
        ),
        "pegadinhas": [
            "Confundir decolonial com pós-colonial.",
            "Datar como séc. XIX.",
            "Reduzir a marxismo.",
        ],
        "erros_comuns": (
            "Marcar D por associar 'novo paradigma' à história cultural."
        ),
        "dica_estrategica": (
            "Decolonial = anos 1990 + LatAm + ruptura epistemológica."
        ),
        "variacao": (
            "(Estilo INEP) Enrique Dussel argumenta que 1492 é:\n"
            "A) descobrimento neutro;\n"
            "B) encobrimento do outro; C) intercâmbio equilibrado; D) fim da colonialidade. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Quijano cunhou:\n"
            "A) colonialidade do poder; B) trabalho abstrato; C) mais-valia; D) longa duração. → A",
            "Q2. Mignolo destaca:\n"
            "A) colonialidade como lado mais escuro da modernidade; B) neutralidade; C) marxismo puro; D) positivismo. → A",
            "Q3. Fanon escreveu:\n"
            "A) Pele negra, máscaras brancas; B) O Príncipe; C) O Contrato Social; D) O Espírito das Leis. → A",
        ],
        "resumo": {
            "regra": "Decolonialidade = crítica epistêmica latino-americana dos anos 1990.",
            "excecoes": "Diálogo com pós-colonial e crítica africana.",
            "palavra_chave": "Ruptura epistemológica + modernidade/colonialidade.",
            "artigo": "QUIJANO (2005); MIGNOLO (2017); DUSSEL (1993).",
            "mnemonico": "P.S.S.: Poder, Saber, Ser — colonialidades a superar.",
        },
    },

    75: {
        "tema": "História do cotidiano (Michel de Certeau; Nova História)",
        "subtema": "Global + nacional + local",
        "habilidade_bncc": "EM13CHS502, EM13CHS603",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Teórica",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a corrente que promove INTERCONEXÕES entre escalas: HISTÓRIA DO COTIDIANO "
            "articula vida dos estudantes ao macrocontexto. Distratores atribuem valores/objetivos "
            "diferentes."
        ),
        "como_banca_pensou": (
            "A banca cobra a compreensão da história do cotidiano como recurso didático."
        ),
        "resolucao": [
            "Descarte A: 'neutralidade' contradiz historiografia crítica.",
            "Descarte B: 'estruturalismo' não é o cerne.",
            "Descarte D: 'inconsciente coletivo' é psicanálise/Jung.",
            "Marque C: cotidiano + experiências dos estudantes + diferentes atores.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "'Neutralidade' e 'grandes personalidades' é história tradicional/positivista."),
            "B": ("ERRADA", "Dialética não se define como 'estruturalismo prioritário'."),
            "C": ("CORRETA", "História do cotidiano articula vida dos estudantes ao contexto histórico."),
            "D": ("ERRADA", "'Inconsciente coletivo' é psicanálise, não historiografia."),
        },
        "fundamentacao": [
            "CERTEAU, Michel de. A invenção do cotidiano. 22. ed. Petrópolis: Vozes, 2014.",
            "LE GOFF, Jacques. A história nova. São Paulo: Martins Fontes, 2005.",
            "BURKE, Peter. A escrita da história: novas perspectivas. São Paulo: UNESP, 1992.",
            "THOMPSON, Edward P. Costumes em comum. São Paulo: Cia. das Letras, 1998.",
        ],
        "teoria": (
            "A HISTÓRIA DO COTIDIANO (Certeau, 2014) e a NOVA HISTÓRIA francesa (Le Goff, 2005) "
            "romperam com a história política tradicional para valorizar práticas, culturas, "
            "microexperiências. Thompson (1998) enfatiza costumes populares. No ensino, permite "
            "conectar vida dos estudantes a processos históricos."
        ),
        "padroes_banca": (
            "O INEP cobra articulação escalas + protagonismo estudantil."
        ),
        "pegadinhas": [
            "Confundir com neutralidade positivista.",
            "Achar que dialética é estruturalismo.",
            "Reduzir 'social' a 'inconsciente coletivo'.",
        ],
        "erros_comuns": (
            "Marcar A por 'neutralidade' soar acadêmico."
        ),
        "dica_estrategica": (
            "Cotidiano articula escalas + vida dos estudantes."
        ),
        "variacao": (
            "(Estilo INEP) A 'Nova História' francesa foi liderada por:\n"
            "A) Ranke; B) Bloch, Febvre, Braudel, Le Goff (Annales);\n"
            "C) Comte; D) Weber. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Certeau (2014) analisa:\n"
            "A) invenção do cotidiano; B) demografia; C) filologia clássica; D) monarquia absoluta. → A",
            "Q2. Thompson (1998) discute:\n"
            "A) costumes em comum + cultura popular; B) direito romano; C) estruturalismo; D) genética. → A",
            "Q3. Longa duração é conceito de:\n"
            "A) Braudel; B) Ranke; C) Comte; D) Weber. → A",
        ],
        "resumo": {
            "regra": "Cotidiano articula escalas e vida estudantil.",
            "excecoes": "Grandes estruturas também devem ser tematizadas.",
            "palavra_chave": "Cotidiano + experiências + escalas.",
            "artigo": "CERTEAU (2014); LE GOFF (2005); THOMPSON (1998).",
            "mnemonico": "G.N.L.: Global + Nacional + Local via cotidiano.",
        },
    },

    76: {
        "tema": "Negacionismo histórico e charge crítica",
        "subtema": "Escravidão e revisionismos em redes sociais",
        "habilidade_bncc": "EM13CHS603, EM13CHS502",
        "gabarito": "C",
        "nivel": "Fácil-Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "A charge ironiza NEGACIONISMOS que manipulam fontes e ganham escala em redes sociais. As "
            "demais alternativas atribuem à charge posturas positivas (crítica, valorização) que "
            "estão fora do sentido irônico."
        ),
        "como_banca_pensou": (
            "A banca cobra reconhecer que a ironia da charge DENUNCIA — logo, seu alvo é o "
            "negacionismo, não uma virtude."
        ),
        "resolucao": [
            "Descarte A: 'usos críticos' seriam positivos; a charge critica algo negativo.",
            "Descarte B: 'valorizar memórias silenciadas' é virtuoso, sem ironia.",
            "Descarte D: 'novas leituras com fontes recentes' é neutro/positivo.",
            "Marque C: negacionismos + manipulação + redes sociais.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "'Usos críticos' são positivos; a charge ironiza problema."),
            "B": ("ERRADA", "'Valorizar memórias silenciadas' é finalidade virtuosa."),
            "C": ("CORRETA", "Alvo da ironia: negacionismos apoiados em manipulação e redes sociais."),
            "D": ("ERRADA", "'Novas leituras' com fontes recentes é neutro; não é o alvo da ironia."),
        },
        "fundamentacao": [
            "TRAVERSO, Enzo. O passado, modos de usar. Lisboa: Unipop, 2012.",
            "SCHWARCZ, Lilia. Sobre o autoritarismo brasileiro. São Paulo: Cia. das Letras, 2019.",
            "REIS, João José. Rebelião escrava no Brasil. São Paulo: Cia. das Letras, 2003.",
            "GONÇALVES E SILVA, Petronilha B. Aprender, ensinar e relações étnico-raciais no Brasil. Educação, v. 30, 2007.",
        ],
        "teoria": (
            "Charges são fontes iconográficas com forte carga discursiva. Traverso (2012) analisa os "
            "'usos públicos do passado'. Negacionismos sobre a escravidão brasileira (Nabuco já denunciava "
            "no séc. XIX) reaparecem em redes sociais com manipulação seletiva de fontes."
        ),
        "padroes_banca": (
            "O INEP cobra leitura irônica + identificação do alvo."
        ),
        "pegadinhas": [
            "Confundir alvo da ironia com virtude.",
            "Achar que 'novas leituras' é o alvo.",
            "Reduzir charge a informação.",
        ],
        "erros_comuns": (
            "Marcar A por associar 'crítica' a positividade."
        ),
        "dica_estrategica": (
            "Ironia = crítica → o alvo é o problema, não a solução."
        ),
        "variacao": (
            "(Estilo INEP) Negacionismo sobre a escravidão consiste em:\n"
            "A) reconhecer a violência e reparar;\n"
            "B) minimizar, relativizar ou negar dados históricos consolidados;\n"
            "C) valorizar quilombos; D) publicar arquivos. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. Charge é fonte:\n"
            "A) neutra; B) discursiva, com posição; C) científica; D) apenas ilustrativa. → B",
            "Q2. Traverso (2012) trata de:\n"
            "A) usos do passado; B) direito romano; C) genética; D) filologia. → A",
            "Q3. Redes sociais amplificam:\n"
            "A) apenas ciência; B) inclusive negacionismos; C) só filantropia; D) só arte. → B",
        ],
        "resumo": {
            "regra": "Charge irônica denuncia o problema; alvo = negativo.",
            "excecoes": "Charges podem, em outros contextos, celebrar.",
            "palavra_chave": "Negacionismo + manipulação + redes.",
            "artigo": "TRAVERSO (2012).",
            "mnemonico": "N.M.R.: Negacionismo + Manipulação + Redes sociais.",
        },
    },

    77: {
        "tema": "Escravidão transatlântica moderna",
        "subtema": "Organização e lucro das potências europeias",
        "habilidade_bncc": "EF07HI14, EM13CHS502; Lei 10.639/03",
        "gabarito": "A",
        "nivel": "Fácil-Médio",
        "tipo": "Prática",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a leitura HISTORICAMENTE CORRETA: potências europeias ORGANIZARAM e LUCRARAM "
            "com o tráfico. Alternativas erradas equivocam-se com a Antiguidade (B), invertem "
            "responsabilidades (C, D)."
        ),
        "como_banca_pensou": (
            "A banca cobra a rejeição de teses revisionistas que 'africanizam' a culpa pela escravidão."
        ),
        "resolucao": [
            "Descarte B: relacionar Antiguidade e escravidão transatlântica é anacronismo.",
            "Descarte C: 'dinâmicas internas africanas' é a tese negacionista.",
            "Descarte D: 'interesses culturais dos próprios africanos' inverte a responsabilidade.",
            "Marque A: potências europeias organizaram e lucraram.",
        ],
        "analise_alternativas": {
            "A": ("CORRETA", "Estados europeus organizaram o tráfico como parte da acumulação capitalista."),
            "B": ("ERRADA", "Servidão antiga difere estruturalmente do escravismo moderno."),
            "C": ("ERRADA", "'Dinâmicas internas' isenta as potências europeias."),
            "D": ("ERRADA", "'Interesses culturais africanos' é tese negacionista."),
        },
        "fundamentacao": [
            "WILLIAMS, Eric. Capitalismo e escravidão. São Paulo: Cia. das Letras, 2012.",
            "SILVA, Alberto da Costa e. A África explicada aos meus filhos. Rio de Janeiro: Agir, 2009.",
            "ALENCASTRO, Luiz Felipe. O trato dos viventes: formação do Brasil no Atlântico Sul. São Paulo: Cia. das Letras, 2000.",
            "GORENDER, Jacob. O escravismo colonial. São Paulo: Ática, 1978.",
        ],
        "teoria": (
            "O TRÁFICO TRANSATLÂNTICO (séc. XVI-XIX) escravizou cerca de 12 milhões de africanos, "
            "dos quais 4,8 milhões vieram para o Brasil. Foi organizado por Portugal, Espanha, "
            "Holanda, Inglaterra, França e outros. Williams (2012) demonstra o vínculo entre "
            "escravidão e acumulação capitalista britânica; Alencastro (2000) analisa o Atlântico Sul."
        ),
        "padroes_banca": (
            "O INEP cobra a leitura correta contra revisionismos."
        ),
        "pegadinhas": [
            "'Africanizar' a culpa.",
            "Anacronizar com Antiguidade.",
            "Reduzir a cultura.",
        ],
        "erros_comuns": (
            "Marcar C por confundir tráfico interno africano com o transatlântico."
        ),
        "dica_estrategica": (
            "Escravismo moderno = responsabilidade estruturante das potências europeias."
        ),
        "variacao": (
            "(Estilo INEP) Williams (2012) demonstrou que:\n"
            "A) escravidão financiou a Revolução Industrial britânica;\n"
            "B) escravidão foi neutra economicamente; C) inglaterra nunca lucrou; D) escravidão era pequena. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Alencastro (2000) analisa:\n"
            "A) Atlântico Sul e formação do Brasil colonial; B) Renascimento italiano; C) Reforma; D) Iluminismo. → A",
            "Q2. Lei Áurea foi assinada em:\n"
            "A) 1888; B) 1850; C) 1871; D) 1889. → A",
            "Q3. Lei do Ventre Livre foi de:\n"
            "A) 1871; B) 1888; C) 1850; D) 1889. → A",
        ],
        "resumo": {
            "regra": "Escravismo transatlântico = organização e lucro das potências europeias.",
            "excecoes": "Havia participação de intermediários africanos, mas o motor era europeu.",
            "palavra_chave": "Potências europeias + lucro.",
            "artigo": "WILLIAMS (2012); ALENCASTRO (2000).",
            "mnemonico": "P.O.L.: Potências, Organização, Lucro.",
        },
    },

    78: {
        "tema": "Revolução do Haiti e Trouillot",
        "subtema": "Silêncios da história eurocêntrica",
        "habilidade_bncc": "EF08HI14, EM13CHS502",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a compreensão da tese de Trouillot: a HISTORIOGRAFIA EUROCÊNTRICA silencia o "
            "PROTAGONISMO haitiano. As demais alternativas invertem a proposta."
        ),
        "como_banca_pensou": (
            "A banca cobra a leitura crítica da produção do silenciamento historiográfico."
        ),
        "resolucao": [
            "Descarte A: defender protagonismo europeu contradiz Trouillot.",
            "Descarte B: 'racionalismo imparcial' não é o alvo específico.",
            "Descarte C: defender narrativa eurocêntrica contradiz o texto.",
            "Marque D: criticar a negação do protagonismo popular haitiano.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Contradiz Trouillot: Haiti é obra dos africanos e afrodescendentes."),
            "B": ("ERRADA", "Não é sobre racionalismo científico em geral."),
            "C": ("ERRADA", "Trouillot CRITICA a narrativa eurocêntrica."),
            "D": ("CORRETA", "Criticar a NEGAÇÃO do protagonismo popular haitiano é o cerne."),
        },
        "fundamentacao": [
            "TROUILLOT, Michel-Rolph. Silenciando o passado: poder e a produção da história. Curitiba: Huya, 2016.",
            "JAMES, C. L. R. Os jacobinos negros. São Paulo: Boitempo, 2000.",
            "DUBOIS, Laurent. Avengers of the New World: The Story of the Haitian Revolution. Cambridge: Harvard University Press, 2004.",
            "FISCHER, Sibylle. Modernity Disavowed: Haiti and the Cultures of Slavery in the Age of Revolution. Durham: Duke University Press, 2004.",
        ],
        "teoria": (
            "A REVOLUÇÃO DO HAITI (1791-1804) foi a única revolução escrava vitoriosa da história, "
            "resultando na abolição da escravidão e na independência do país. Toussaint Louverture e "
            "Jean-Jacques Dessalines lideraram o processo. Trouillot (2016) e James (2000) mostram como "
            "a historiografia ocidental invisibilizou esse feito por não conseguir integrar a agência "
            "negra à narrativa moderna."
        ),
        "padroes_banca": (
            "O INEP cobra articulação Haiti + Trouillot + silenciamento."
        ),
        "pegadinhas": [
            "Confundir 'crítica' com 'defesa'.",
            "Reduzir Trouillot a 'racionalismo científico'.",
            "Aceitar protagonismo europeu no Haiti.",
        ],
        "erros_comuns": (
            "Marcar B por associar Trouillot a 'imparcialidade'."
        ),
        "dica_estrategica": (
            "Trouillot = silêncios da história eurocêntrica + agência dos subalternizados."
        ),
        "variacao": (
            "(Estilo INEP) C. L. R. James (2000), em 'Os jacobinos negros', discute:\n"
            "A) Revolução Francesa isoladamente;\n"
            "B) Revolução do Haiti e Toussaint Louverture; C) Revolução Russa; D) Revolução Chinesa. Gabarito: B."
        ),
        "minisimulado": [
            "Q1. A independência do Haiti é de:\n"
            "A) 1804; B) 1791; C) 1791-1804 (processo); D) 1889. → C (processo).",
            "Q2. Dessalines foi:\n"
            "A) líder haitiano que proclamou a independência em 1804; B) presidente cubano; "
            "C) rei francês; D) príncipe português. → A",
            "Q3. Trouillot argumenta que o Haiti é silenciado por:\n"
            "A) narrativas eurocêntricas; B) escassez documental; C) desinteresse cultural; D) causas naturais. → A",
        ],
        "resumo": {
            "regra": "Haiti = revolução vitoriosa; Trouillot denuncia o silenciamento.",
            "excecoes": "Historiografia recente vem reparando a lacuna.",
            "palavra_chave": "Silenciamento + protagonismo popular.",
            "artigo": "TROUILLOT (2016); JAMES (2000).",
            "mnemonico": "H.T.P.: Haiti + Trouillot + Protagonismo popular.",
        },
    },

    79: {
        "tema": "Mulheres nas lutas anticoloniais africanas – PAIGC",
        "subtema": "Duplo desafio (combate + gênero)",
        "habilidade_bncc": "EF09HI25, EM13CHS604",
        "gabarito": "D",
        "nivel": "Médio",
        "tipo": "Interpretativa",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra reconhecimento do DUPLO DESAFIO enfrentado pelas mulheres do PAIGC: combater o "
            "colonialismo E romper com papéis de gênero. As demais alternativas invisibilizam, "
            "hierarquizam ou romantizam."
        ),
        "como_banca_pensou": (
            "A banca cobra articulação anticolonialismo + gênero."
        ),
        "resolucao": [
            "Descarte A: participação NÃO foi 'preservada' após a colonização (foi restringida).",
            "Descarte B: 'limitada a funções domésticas' invisibiliza atuação política.",
            "Descarte C: reduzir a 'enfermeiras' hierarquiza subalternamente.",
            "Marque D: duplo desafio (combate + gênero).",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "A colonização restringiu, e não 'preservou', a participação feminina."),
            "B": ("ERRADA", "Reduz a participação política ao doméstico."),
            "C": ("ERRADA", "Subalterniza mulheres a papéis de suporte."),
            "D": ("CORRETA", "Duplo desafio = combate anticolonial + ruptura de papéis de gênero."),
        },
        "fundamentacao": [
            "URDANG, Stephanie. Fighting Two Colonialisms: Women in Guinea-Bissau. New York: Monthly Review Press, 1979.",
            "CABRAL, Amílcar. A arma da teoria: unidade e luta. Rio de Janeiro: Codecri, 1980.",
            "UNESCO. História Geral da África, Vol. VIII: África desde 1935. Brasília: UNESCO, 2010.",
            "OYĚWÙMÍ, Oyèrónkẹ́. A invenção das mulheres. Rio de Janeiro: Bazar do Tempo, 2021.",
        ],
        "teoria": (
            "O PAIGC (Partido Africano da Independência da Guiné e Cabo Verde), fundado por Amílcar "
            "Cabral em 1956, mobilizou também mulheres em combate. Urdang (1979) documentou o 'duplo "
            "colonialismo' — colonial e patriarcal. Oyěwùmí (2021) discute a construção do gênero em "
            "sociedades africanas."
        ),
        "padroes_banca": (
            "O INEP cobra articulação anticolonial + gênero + agência."
        ),
        "pegadinhas": [
            "Achar que colonização 'preservou' saberes.",
            "Reduzir a mulheres a funções domésticas.",
            "Subalternizar a papéis de suporte.",
        ],
        "erros_comuns": (
            "Marcar C por hábito de ver mulheres em papéis auxiliares."
        ),
        "dica_estrategica": (
            "Anticolonial + gênero = duplo desafio."
        ),
        "variacao": (
            "(Estilo INEP) Amílcar Cabral, líder do PAIGC, defendia:\n"
            "A) resistência armada + revolução cultural;\n"
            "B) aliança total com Portugal; C) retorno ao tribalismo; D) integração à França. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. A independência de Guiné-Bissau foi em:\n"
            "A) 1974; B) 1975; C) 1973 (proclamada); D) A e C. → D",
            "Q2. Cabo Verde tornou-se independente em:\n"
            "A) 1975; B) 1974; C) 1978; D) 1980. → A",
            "Q3. Oyěwùmí (2021) discute:\n"
            "A) a invenção das mulheres em contexto colonial; B) direito romano; C) matemática; D) hebraico. → A",
        ],
        "resumo": {
            "regra": "Mulheres do PAIGC = duplo colonialismo enfrentado.",
            "excecoes": "Cada país teve suas dinâmicas.",
            "palavra_chave": "Duplo desafio + gênero.",
            "artigo": "URDANG (1979); UNESCO (2010); OYĚWÙMÍ (2021).",
            "mnemonico": "P.A.I.G.C.: Protagonismo Anticolonial e Insurgência de Gênero em Combate.",
        },
    },

    80: {
        "tema": "Representação, gênero e violência em imagens históricas",
        "subtema": "Protagonismo político feminino africano",
        "habilidade_bncc": "EF09HI25, EM13CHS502",
        "gabarito": "B",
        "nivel": "Médio",
        "tipo": "Prática (justificativa pedagógica)",
        "formato": "Múltipla escolha (A-D)",
        "incidencia": "Alta",
        "justificativa_classificacao": (
            "Item cobra a justificativa pedagógica: RECONHECER protagonismo político das mulheres "
            "africanas + PROBLEMATIZAR representações naturalizadas quando associadas a homens. "
            "Alternativas erradas subordinam mulheres (A), desviam para cultura juvenil (C) ou "
            "essencializam violência (D)."
        ),
        "como_banca_pensou": (
            "A banca cobra a articulação REPRESENTAÇÃO + GÊNERO + VIOLÊNCIA NATURALIZADA."
        ),
        "resolucao": [
            "Descarte A: 'ocupar posições de apoio aos homens' é o oposto do protagonismo.",
            "Descarte C: 'jogos e séries juvenis' é desvio.",
            "Descarte D: essencializar guerra na história africana é estereótipo.",
            "Marque B: protagonismo + problematizar naturalização de violência masculina.",
        ],
        "analise_alternativas": {
            "A": ("ERRADA", "Subordina mulheres a homens; contraria o protagonismo mostrado."),
            "B": ("CORRETA", "Protagonismo político + problematização de representações naturalizadas."),
            "C": ("ERRADA", "'Jogos e séries' é desvio."),
            "D": ("ERRADA", "Essencializa história africana como violência."),
        },
        "fundamentacao": [
            "OYĚWÙMÍ, Oyèrónkẹ́. A invenção das mulheres. Rio de Janeiro: Bazar do Tempo, 2021.",
            "UNESCO. História Geral da África, VIII. Brasília: UNESCO, 2010.",
            "AMADIUME, Ifi. Male Daughters, Female Husbands: Gender and Sex in an African Society. Londres: Zed Books, 1987.",
            "BUTLER, Judith. Problemas de gênero. Rio de Janeiro: Civilização Brasileira, 2003.",
        ],
        "teoria": (
            "Estudos de gênero africanos (Oyěwùmí, 2021; Amadiume, 1987) desafiam a naturalização do "
            "binarismo ocidental e reconhecem a agência política feminina. Butler (2003) fornece "
            "quadro conceitual sobre performatividade de gênero. Representar mulheres armadas expõe a "
            "NATURALIZAÇÃO da violência quando associada a homens e desnaturaliza estereótipos de "
            "gênero."
        ),
        "padroes_banca": (
            "O INEP cobra justificativa pedagógica coerente com estudos de gênero."
        ),
        "pegadinhas": [
            "Subordinar mulheres a apoio.",
            "Desviar para cultura juvenil.",
            "Essencializar violência.",
        ],
        "erros_comuns": (
            "Marcar D por associar África a 'guerra'."
        ),
        "dica_estrategica": (
            "Representação + gênero = protagonismo + problematização de naturalização."
        ),
        "variacao": (
            "(Estilo INEP) Ifi Amadiume (1987) discute:\n"
            "A) construções de gênero em sociedades africanas fora do binarismo ocidental;\n"
            "B) Renascimento italiano; C) direito romano; D) Iluminismo. Gabarito: A."
        ),
        "minisimulado": [
            "Q1. Oyěwùmí (2021) argumenta que:\n"
            "A) gênero, tal como definido no Ocidente, não é categoria universal; "
            "B) gênero é biologia pura; C) gênero é irrelevante; D) apenas mulheres têm gênero. → A",
            "Q2. Butler (2003) formula:\n"
            "A) performatividade de gênero; B) essência feminina; "
            "C) determinismo biológico; D) cristianismo primitivo. → A",
            "Q3. A guerra colonial em Guiné-Bissau ocorreu entre:\n"
            "A) 1963-1974; B) 1900-1910; C) 1990-2000; D) 1975-1985. → A",
        ],
        "resumo": {
            "regra": "Representação de mulheres em combate = protagonismo + problematização da violência naturalizada em homens.",
            "excecoes": "Contexto pedagógico deve mediar sensibilidades.",
            "palavra_chave": "Protagonismo + problematização + gênero.",
            "artigo": "OYĚWÙMÍ (2021); AMADIUME (1987); BUTLER (2003).",
            "mnemonico": "P.P.G.: Protagonismo, Problematização, Gênero.",
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
        "Estrutura da prova e distribuição",
        "Padrão de análise (15 blocos)",
        "Índice completo das 80 questões (enunciado + alternativas)",
        "Referências ABNT consolidadas",
    ]:
        doc.add_paragraph(txt, style="List Bullet")
    doc.add_page_break()

    # ---------- Estrutura ----------
    bloco_titulo("ESTRUTURA DA PROVA")
    paragrafo("Caderno PND 2025 – História (Licenciatura) – TIPO 01 (PV_1):")
    paragrafo("• Formação Geral Docente: questões 01 a 30 (objetivas) + 1 questão discursiva")
    paragrafo("• Componente Específico da Área: questões 31 a 80 (objetivas)")
    paragrafo("• Questionário de Percepção da Prova: 09 questões objetivas (não conteudísticas)")
    paragrafo("Total de itens objetivos analisados neste manual: 80.")

    # ---------- Padrão ----------
    bloco_titulo("PADRÃO DE ANÁLISE (15 BLOCOS)")
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

    # ---------- Análise por questão (sem cabeçalho de seção – conforme edição do usuário) ----------
    for num in list(range(1, 81)):
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
    bloco_titulo("ÍNDICE COMPLETO – 80 QUESTÕES (ENUNCIADO + ALTERNATIVAS)")
    doc.add_paragraph(
        "Reprodução dos 80 itens objetivos do caderno (Formação Geral Docente + "
        "Componente Específico – História), para consulta rápida."
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
    bloco_titulo("REFERÊNCIAS ABNT CONSOLIDADAS")
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
                                 f"Página {doc_.page}   •   Projeto Gabaritando")
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
    for t in ["Estrutura da prova e distribuição",
              "Padrão de análise (15 blocos)",
              "Índice completo das 80 questões",
              "Referências ABNT consolidadas"]:
        story.append(Paragraph("• " + t, body))
    story.append(PageBreak())

    # Estrutura
    story.append(Paragraph("ESTRUTURA DA PROVA", h_bloco))
    for t in [
        "Formação Geral Docente: questões 01 a 30 (objetivas) + 1 questão discursiva.",
        "Componente Específico da Área: questões 31 a 80 (objetivas).",
        "Questionário de Percepção da Prova: 09 questões (não conteudísticas).",
        "Total de itens objetivos analisados: 80.",
    ]:
        story.append(Paragraph("• " + t, body))

    # Padrão
    story.append(Paragraph("PADRÃO DE ANÁLISE (15 BLOCOS)", h_bloco))
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

    # Análises por questão (sem cabeçalho de seção – conforme edição do usuário)
    cell_style = ParagraphStyle("cell", parent=body, fontSize=10, leading=13, alignment=TA_LEFT)
    cell_key = ParagraphStyle("cell_key", parent=cell_style, fontName="Helvetica-Bold",
                              textColor=HexColor(AZUL_ESCURO))

    for num in list(range(1, 81)):
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
    story.append(Paragraph("ÍNDICE COMPLETO — 80 QUESTÕES (ENUNCIADO + ALTERNATIVAS)", h_bloco))
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
    story.append(Paragraph("REFERÊNCIAS ABNT CONSOLIDADAS", h_bloco))
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
