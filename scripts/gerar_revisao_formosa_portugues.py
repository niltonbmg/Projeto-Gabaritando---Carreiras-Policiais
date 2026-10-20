#!/usr/bin/env python3
"""
Gera a Revisão – Concurso de Formosa do Rio Preto 2026 – Língua Portuguesa.

Mesmo padrão visual da revisão de Informática (capa, logo, marca d'água, gabarito rápido).

Saídas:
  saidas/revisao_formosa_portugues.docx
  saidas/revisao_formosa_portugues.pdf  (via LibreOffice, se disponível)
"""
from gerar_revisao_formosa_informatica import SAIDAS, C, E, gerar_docx, gerar_pdf

TEXTO_PORT = (
    "Brasil reconhece potência dos chás naturais e SUS desenvolve remédio com erva conhecida",
    [
        "A erva quebra-pedra, usada ___ muito tempo em casa, agora faz parte de um medicamento desenvolvido pela Fiocruz",
        "Muita gente já conhece o chá de quebra-pedra — um remédio caseiro que muitas famílias usam para ajudar na saúde do sistema urinário. Agora essa planta está ganhando atenção maior: o SUS vai ganhar um remédio ____ base de quebra-pedra feito por pesquisadores da Fiocruz e que deverá ser distribuído gratuitamente nas unidades de saúde.",
        "O que é a planta quebra-pedra?",
        "O nome científico da planta é Phyllanthus niruri, mas no Brasil ela é mais conhecida mesmo como quebra-pedra. Essa erva é comum em várias regiões do país e tem sido usada há muito tempo por famílias para fazer chá. Muita gente toma o chá quando sente dores ou incômodos nos rins, ou no trato urinário, porque acredita que ele ajuda a prevenir a formação de pedras nos rins e melhora o fluxo da urina.",
        "Como o remédio está sendo desenvolvido?",
        "Pesquisadores do Instituto de Tecnologia em Fármacos (Farmanguinhos/Fiocruz) começaram a trabalhar com a quebra-pedra para transformar o uso tradicional em um medicamento padronizado. Isso significa que o remédio terá dose certa, fórmula definida e será fabricado com controle de qualidade, diferente do chá que cada pessoa prepara em casa sozinho.",
        "Esse é um passo importante porque junta o conhecimento que a família brasileira já tinha com ervas com pesquisa científica e segurança. O projeto respeita também as regras que protegem o uso tradicional dessas plantas.",
        "Por que isso importa para quem usa chás naturais?",
        "Essa iniciativa mostra que o Brasil está dando mais valor às plantas usadas _____ anos pela população e buscando formas de usá-las com mais segurança e respaldo da ciência.",
        "É crucial lembrar que antes de usar qualquer remédio, mesmo natural, é importante conversar com um profissional de saúde. Muitos chás e plantas podem fazer bem, mas precisam ser usados com cuidado, especialmente por pessoas com outras doenças ou que tomam outros medicamentos.",
    ],
    "Disponível em: https://www.minhavida.com.br/materias/materia-27850",
)

PORTUGUES_P1 = [
    dict(num=1, tema="Compreensão textual – itens incorretos",
         enunciado=("De acordo com o texto:\n"
                    "I – O nome científico da planta é Phyllanthus niruri, mas no Brasil ela é mais conhecida mesmo como quebra-pedra.\n"
                    "II – Essa erva é incomum em várias regiões do país e tem sido usada há muito tempo por famílias para fazer chá.\n"
                    "III – Muita gente toma o chá quando sente dores ou incômodos nos rins, ou no trato urinário, porque acredita que ele ajuda a prevenir a formação de pedras nos rins e piora o fluxo da urina.\n"
                    "IV – Pesquisadores do Instituto de Tecnologia em Fármacos (Farmanguinhos/Fiocruz) começaram a trabalhar com a quebra-pedra para transformar o uso tradicional em um medicamento padronizado.\n"
                    "V – O remédio terá dose certa, fórmula indefinida e será fabricado com controle de qualidade, diferente do chá que cada pessoa prepara em casa sozinho.\n"
                    "Estão incorretas as alternativas:"),
         alts={"A": "II e III", "B": "III e V", "C": "II e V", "D": "III e IV", "E": "II, III e V"},
         gab="E",
         com={"A": (E, "Incompleta: falta o item V ('fórmula indefinida' – o texto diz 'definida')."),
              "B": (E, "Incompleta: falta o item II ('incomum' – o texto diz 'comum')."),
              "C": (E, "Incompleta: falta o item III ('piora' – o texto diz 'melhora' o fluxo da urina)."),
              "D": (E, "O item IV está CORRETO – reproduz fielmente o texto."),
              "E": (C, "II (comum→incomum), III (melhora→piora) e V (definida→indefinida) foram adulterados. I e IV estão corretos.")},
         dica="Esta banca adultera os itens trocando UMA palavra por seu antônimo (comum/incomum, melhora/piora, definida/indefinida). Compare palavra por palavra."),
    dict(num=2, tema="Compreensão textual – inferência",
         enunciado="Após leitura e compreensão do texto podemos afirmar que:",
         alts={"A": "Todos os chás e plantas podem fazer bem, mas precisam ser usados com cuidado, especialmente por pessoas com outras doenças ou que tomam outros medicamentos.",
               "B": "O projeto junta o conhecimento que a família brasileira já tinha com ervas com pesquisa científica e segurança além de respeitar também as regras que protegem o uso tradicional dessas plantas.",
               "C": "O Brasil está dando mais valor às plantas usadas pela população e buscando formas de usá-las com mais segurança e sem respaldo da ciência.",
               "D": "O chá de quebra-pedra é um remédio industrializado que muitas famílias usam para ajudar na saúde do sistema urinário.",
               "E": "É desnecessário conversar com um profissional de saúde antes de usar qualquer remédio natural."},
         gab="B",
         com={"A": (E, "O texto diz 'MUITOS chás e plantas'; a alternativa generalizou para 'Todos'."),
              "B": (C, "Síntese fiel de dois trechos: une saber popular + ciência e segurança, e respeita as regras de uso tradicional."),
              "C": (E, "O texto diz 'COM respaldo da ciência', não 'sem'."),
              "D": (E, "O texto chama o chá de remédio 'CASEIRO', não industrializado."),
              "E": (E, "O texto afirma que é 'crucial' e 'importante' consultar um profissional de saúde.")},
         dica="Palavras generalizantes (todos, sempre, nunca, qualquer) costumam denunciar a alternativa errada."),
    dict(num=3, tema="Morfologia – plural dos substantivos compostos",
         enunciado="Assinale a alternativa cuja flexão de número do substantivo siga a mesma regra gramatical de quebra-pedra:",
         alts={"A": "Banana-prata", "B": "Couve-flor", "C": "Cirurgião-dentista", "D": "Laranja-pera", "E": "Beija-flor"},
         gab="E",
         com={"A": (E, "Substantivo + substantivo que especifica o primeiro: bananas-prata (aceita-se bananas-pratas)."),
              "B": (E, "Substantivo + substantivo: ambos variam – couves-flores."),
              "C": (E, "Substantivo + substantivo: ambos variam – cirurgiões-dentistas."),
              "D": (E, "Segundo termo especifica o primeiro: laranjas-pera."),
              "E": (C, "VERBO + SUBSTANTIVO, igual a quebra-pedra: só o substantivo varia – beija-flores / quebra-pedras.")},
         dica="Verbo + substantivo → só o substantivo vai ao plural: guarda-chuvas, beija-flores, quebra-pedras."),
    dict(num=4, tema="Uso de há / a / à",
         enunciado=("Analise os fragmentos de texto abaixo e assinale a alternativa que, segundo as normas gramaticais, completa respectivamente as lacunas:\n"
                    "“A erva quebra-pedra, usada ____ muito tempo em casa, agora faz parte de um medicamento desenvolvido pela Fiocruz”\n"
                    "“o SUS vai ganhar um remédio ____ base de quebra-pedra feito por pesquisadores da Fiocruz e que deverá ser distribuído gratuitamente nas unidades de saúde.”\n"
                    "“Essa iniciativa mostra que o Brasil está dando mais valor às plantas usadas _____ anos pela população e buscando formas de usá-las com mais segurança e respaldo da ciência.”"),
         alts={"A": "A, à, a", "B": "Há, há, há", "C": "Há, à, há", "D": "À, a, há", "E": "A, há, a"},
         gab="C",
         com={"A": (E, "1ª e 3ª lacunas indicam tempo passado → 'há', não 'a'."),
              "B": (E, "'Há base de' não existe – a locução é 'à base de'."),
              "C": (C, "'há muito tempo' (tempo passado = faz) • 'à base de' (locução feminina, crase obrigatória) • 'há anos' (tempo passado)."),
              "D": (E, "'À muito tempo' está errado: não há crase antes de palavra masculina nem para tempo passado."),
              "E": (E, "Erra as três lacunas.")},
         dica="Troque por 'FAZ': se couber, use HÁ. Tempo futuro ('daqui A dois dias') usa A."),
    dict(num=5, tema="Semântica – sinonímia",
         enunciado="“É crucial lembrar que antes de usar qualquer remédio, mesmo natural, é importante conversar com um profissional de saúde.”\nAssinale a alternativa cujo vocábulo pode substituir, sem prejuízo de valor, o vocábulo em destaque (crucial):",
         alts={"A": "Basilar", "B": "Fútil", "C": "Secundário", "D": "Frívolo", "E": "Desnecessário"},
         gab="A",
         com={"A": (C, "Basilar = fundamental, que serve de base – sinônimo de crucial."),
              "B": (E, "Fútil = sem importância (antônimo)."),
              "C": (E, "Secundário = de menor importância (antônimo)."),
              "D": (E, "Frívolo = superficial, leviano (antônimo)."),
              "E": (E, "Desnecessário (antônimo).")},
         dica="Quando 4 alternativas têm o mesmo sentido entre si (B, C, D, E = 'sem importância'), a diferente tende a ser a resposta."),
    dict(num=6, tema="Fonologia – classificação quanto ao número de sílabas",
         enunciado="O vocábulo “naturais”, quanto ao número de sílabas, classifica-se como:",
         alts={"A": "Monossílabo", "B": "Dissílabo", "C": "Trissílabo", "D": "Polissílabo", "E": "Multissílabo"},
         gab="C",
         com={"A": (E, "Monossílabo = 1 sílaba."),
              "B": (E, "Dissílabo = 2 sílabas."),
              "C": (C, "na-tu-rais = 3 sílabas. 'rais' tem ditongo decrescente (ai), que não se separa."),
              "D": (E, "Polissílabo = 4 ou mais sílabas."),
              "E": (E, "'Multissílabo' não é classificação da gramática normativa.")},
         dica="Ditongo nunca se separa (rais, pai); hiato sempre se separa (sa-ú-de, ra-iz)."),
    dict(num=7, tema="Sintaxe – concordância nominal",
         enunciado="A concordância nominal está em desacordo com as normas gramaticais em:",
         alts={"A": "É preciso profissionais para atuar na área.", "B": "A entrada de pessoas estranhas é proibido.",
               "C": "Era meio-dia e meia.", "D": "Fomos proibidas de participar do evento.",
               "E": "Havia menos mulheres na reunião do partido."},
         gab="B",
         com={"A": (C, "Correta: sem determinante, 'é preciso' fica invariável."),
              "B": (E, "DESACORDO (gabarito): com o artigo 'A', o adjetivo concorda – 'A entrada... é PROIBIDA'."),
              "C": (C, "Correta: 'meia' = meia hora (feminino)."),
              "D": (C, "Correta: 'proibidas' concorda com o sujeito feminino plural."),
              "E": (C, "Correta: 'menos' é invariável ('menas' não existe).")},
         dica="É proibido / é necessário / é bom: SEM artigo → invariável; COM artigo → concorda ('É proibida A entrada')."),
    dict(num=8, tema="Figuras de linguagem – eufemismo",
         enunciado="Temos um exemplo de eufemismo em:",
         alts={"A": "O prisioneiro faltou com a verdade.", "B": "Que careta mais linda!",
               "C": "Ela quebrou a asa da xícara.", "D": "Leio Machado de Assis com entusiasmo.",
               "E": "Éramos mais felizes na fazenda."},
         gab="A",
         com={"A": (C, "'Faltou com a verdade' suaviza 'mentiu' – eufemismo."),
              "B": (E, "Ironia: diz o contrário do que se pensa."),
              "C": (E, "Catacrese: metáfora desgastada pelo uso ('asa da xícara')."),
              "D": (E, "Metonímia: autor pela obra."),
              "E": (E, "Sentido literal, sem figura de linguagem.")},
         dica="Eufemismo suaviza (faleceu, faltou com a verdade); hipérbole exagera; ironia inverte."),
    dict(num=9, tema="Semântica – parônimos",
         enunciado="Assinale a alternativa em que temos um caso de parônimo:",
         alts={"A": "Conserto e concerto", "B": "Tudo e nada", "C": "Consentir e permitir",
               "D": "Absolver e absorver", "E": "Feio e bonito"},
         gab="D",
         com={"A": (E, "Conserto (reparo) e concerto (apresentação musical) têm a MESMA pronúncia – homônimos homófonos. Pegadinha!"),
              "B": (E, "Antônimos."),
              "C": (E, "Sinônimos."),
              "D": (C, "Absolver (perdoar, inocentar) × absorver (sorver, assimilar): grafia e som PARECIDOS, sentidos diferentes – parônimos."),
              "E": (E, "Antônimos.")},
         dica="Parônimos = PARECIDOS (absolver/absorver, comprimento/cumprimento). Homônimos = IGUAIS no som ou na escrita (conserto/concerto)."),
    dict(num=10, tema="Semântica – polissemia", anulavel=True,
         enunciado="Assinale a proposição cuja oração apresenta um exemplo de polissemia:",
         alts={"A": "O corvo pairou sobre o telhado da casa de Emília.",
               "B": "Paula chupou manga e sujou a manga da blusa nova.",
               "C": "O gato subiu no telhado.", "D": "Que lindas flores!", "E": "Eu li Machado de Assis."},
         gab="B",
         com={"A": (E, "Nenhuma palavra com duplo sentido."),
              "B": (C, "Gabarito da banca: 'manga' (fruta) e 'manga' (da blusa) – mesma forma, sentidos diferentes."),
              "C": (E, "'Gato' em sentido literal."),
              "D": (E, "Frase exclamativa, sem duplo sentido."),
              "E": (E, "Metonímia (autor pela obra), não polissemia.")},
         dica="CABE RECURSO: tecnicamente, manga (fruta) e manga (roupa) têm origens diferentes – são HOMÔNIMOS PERFEITOS. Polissemia é uma MESMA palavra com sentidos relacionados (pé da mesa / pé da pessoa)."),
]


if __name__ == "__main__":
    SAIDAS.mkdir(exist_ok=True)
    destino = SAIDAS / "revisao_formosa_portugues.docx"
    gerar_docx(destino, disciplina="Língua Portuguesa",
               resumo="Prova 1 (Q01–Q10)  •  10 questões  •  texto base incluso",
               provas=[("PROVA 1", "Língua Portuguesa", PORTUGUES_P1)],
               rodape="Revisão Formosa do Rio Preto 2026 • Língua Portuguesa", textos={"PROVA 1": TEXTO_PORT})
    gerar_pdf(destino)
    print(f"OK: {destino}")
