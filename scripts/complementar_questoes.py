#!/usr/bin/env python3
"""Adiciona/corrige manualmente Q29, Q30, Q41 no JSON extraído (falhas do layout de 2 colunas)."""
import json
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
p = BASE / "downloads/questoes.json"
q = json.loads(p.read_text())
nums = {x["numero"]: i for i, x in enumerate(q)}

adds = [
    {
        "numero": 29,
        "secao": "Formação Geral Docente",
        "enunciado": (
            "O espaço escolar é um lugar de convívio. Nele encontramos não apenas as relações "
            "das pessoas com o conhecimento, mas também o aprendizado de como as pessoas se "
            "relacionam entre si e com o restante do mundo. Exatamente por isso os conflitos "
            "aparecem, e a gestão da escola deve saber como lidar com eles. Por reproduzir as "
            "lógicas sociais, encontramos, também na escola, relações que desvalorizam o que é "
            "entendido como contra-hegemônico nas culturas. E isso impacta negativamente nas "
            "pessoas negras e nas praticantes das Religiões de Matrizes Africanas. Talvez os "
            "signos de Exu e de Ogum sejam boas pistas sobre como lidar com a escola na busca de "
            "espaços menos opressivos. Essas duas divindades do panteão iorubano são vinculadas "
            "aos caminhos, à comunicação, à política, aos conflitos e, de algum modo, à própria "
            "educação. Exu e Ogum nos ensinam que a convivência não precisa de uma suposição de "
            "que todas e todos pensem do mesmo modo, desejem do mesmo modo, caminhem pelos mesmos "
            "caminhos. Mas ensinam que o mundo é criado coletivamente e que, entre conflitos e "
            "andanças, devemos preservar as diferenças. (NASCIMENTO, W. F. As religiões de matrizes "
            "africanas, resistência e contexto escolar: entre encruzilhadas. In: Memórias do Baobá II. "
            "Fortaleza: Editora UFC, 2017, adaptado.) Com base no texto e nas ações de enfrentamento "
            "ao racismo religioso no espaço escolar, é correto afirmar que a"
        ),
        "alternativas": {
            "A": "abordagem da religião e da cultura iorubanas em sala de aula permite que professores e estudantes reflitam sobre os efeitos das violências materiais e simbólicas na sociedade.",
            "B": "apresentação de conteúdos vinculados às religiões de matrizes africanas e a valorização do diálogo na resolução de conflitos nas escolas buscam uma identidade comum a todos os estudantes.",
            "C": "concepção do ambiente escolar como espaço de convívio religioso distancia-se da função social da educação, que deve focalizar conhecimentos gerais, formação disciplinar e cidadania.",
            "D": "utilização de trechos da mitologia africana nas aulas de ensino religioso cumpre o prescrito na lei que trata do ensino da história iorubana e indígena.",
        },
    },
    {
        "numero": 30,
        "secao": "Formação Geral Docente",
        "enunciado": (
            "Motivado pela revisão da Lei n. 12 711/2012, ocorrida no ano de 2023, um professor do "
            "Ensino Médio propôs uma roda de conversa, utilizando a charge de jornal como recurso "
            "mobilizador para a discussão sobre os impactos das ações afirmativas no sistema "
            "educacional brasileiro. A atividade promoveu a reflexão e a crítica sobre os "
            "princípios do Plano Nacional de Educação em Direitos Humanos (PNEDH), como o respeito "
            "à dignidade humana e o exercício da cidadania democrática no Estado de Direito. "
            "(LAERTE. Disponível em: www1.folha.uol.com.br. Acesso em: 12 maio 2025.) "
            "A atividade proposta pelo professor possibilita ao estudante"
        ),
        "alternativas": {
            "A": "reconhecer as ações afirmativas previstas em lei desvinculadas do processo histórico de formação do povo brasileiro.",
            "B": "compreender as ações afirmativas previstas em lei como uma conquista democrática decorrente da mobilização social.",
            "C": "constatar a neutralidade dos meios de comunicação em relação ao racismo estrutural e às ações afirmativas.",
            "D": "entender o debate sobre as ações afirmativas como garantia da superação da discriminação racial.",
        },
    },
    {
        "numero": 41,
        "secao": "Componente Específico – História",
        "enunciado": (
            "TEXTO 1 — DUBOIS, F. Massacre da noite de São Bartolomeu. Óleo sobre tela, 93,5 × 151,4 cm. "
            "Museu Cantonal des Beaux-Arts, Suíça, 1572.\n"
            "TEXTO 2 — Nas cidades medievais, as mulheres trabalhavam como ferreiras, açougueiras, "
            "padeiras, candeleiras, chapeleiras, cervejeiras, cardadeiras de lã e comerciantes. Em "
            "Frankfurt, havia aproximadamente duzentas ocupações nas quais participavam entre 1 300 e "
            "1 500 mulheres. Na Inglaterra, 72 das 85 guildas incluíam mulheres entre seus membros. "
            "Algumas guildas, incluindo a da indústria da seda, eram controladas por elas; em outras, "
            "a porcentagem de trabalho de mulheres era tão alta quanto a dos homens. No século XIV, "
            "as mulheres também estavam tornando-se professoras escolares, bem como médicas e "
            "cirurgiãs, e começavam a competir com homens formados em universidades, obtendo em "
            "certas ocasiões uma alta reputação. (FEDERICI, S. Calibã e a bruxa: mulheres, corpo e "
            "acumulação primitiva. São Paulo: Elefante, 2017.)\n"
            "Em uma aula para a Educação Básica, um professor constatou a percepção, entre os "
            "estudantes, de que o trabalho na esfera pública nas cidades medievais era realizado, "
            "quase que exclusivamente, por homens, enquanto as mulheres ficariam restritas a tarefas "
            "domésticas na esfera privada. Ao utilizar ambos os recursos didáticos — a fonte imagética "
            "e o texto acadêmico —, a metodologia adotada pelo professor, para abordar o espaço "
            "urbano medieval, foi a"
        ),
        "alternativas": {
            "A": "semiótica, em razão da emergente igualdade sugerida.",
            "B": "iconográfica, em razão da ampla preponderância retratada.",
            "C": "formalista, em razão da consistente equidade comprovada.",
            "D": "comparativa, em razão da representatividade similar constatada.",
        },
    },
]

for a in adds:
    if a["numero"] in nums:
        q[nums[a["numero"]]] = a
    else:
        q.append(a)

# Remover a Q43 espúria com 5 alternativas — reextrair a partir do texto
q = [x for x in q if x["numero"] != 43]

# Q43 correta (Martinho Lutero e o Diabo)
q.append({
    "numero": 43,
    "secao": "Componente Específico – História",
    "enunciado": (
        "EVERETT. Martin Luther and Lucifer. Xilogravura. Everett Collection Inc., 1535. "
        "Disponível em: https://everettondemand.com. Acesso em: 16 jul. 2025.\n"
        "Durante uma aula sobre a Reforma Protestante, um professor distribuiu aos estudantes a "
        "reprodução dessa gravura, publicada em Leipzig, em 1535, na qual Martinho Lutero aparece "
        "ao lado da figura do diabo. Essa imagem representa uma forma de"
    ),
    "alternativas": {
        "A": "exaltar um Lutero virtuoso e herói da fé cristã, representando sua vitória sobre as injustiças da Igreja Católica.",
        "B": "demonizar Lutero e sua doutrina, associando-o ao mal em uma campanha visual da Contrarreforma Católica.",
        "C": "difundir o humanismo renascentista, apresentando Lutero como pensador crítico livre do dogma religioso.",
        "D": "representar a Reforma como movimento essencialmente popular, sem envolvimento das autoridades eclesiásticas.",
    },
})

q.sort(key=lambda x: x["numero"])
p.write_text(json.dumps(q, ensure_ascii=False, indent=2))
print(f"total agora: {len(q)}; faltando: {[i for i in range(1,81) if i not in {x['numero'] for x in q}]}")
