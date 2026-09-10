#!/usr/bin/env python3
"""
Gera a PRÉVIA visual (DOCX + PDF) do Manual de Aprovação por Questões – PND História,
no padrão AZUL E BRANCO, contendo uma questão-modelo com os 15 blocos.

Uso:
    python3 scripts/gerar_previa_pnd_historia.py
Saídas:
    saidas/previa_pnd_historia.docx
    saidas/previa_pnd_historia.pdf
"""

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
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak
)
from reportlab.lib.enums import TA_JUSTIFY, TA_LEFT, TA_CENTER

# ---------- PALETA AZUL E BRANCO ----------
AZUL_ESCURO = "#0B3D91"   # capa / faixas
AZUL_MEDIO  = "#1E5FBF"   # títulos de bloco
AZUL_CLARO  = "#E6EEF9"   # fundo suave de destaque
CINZA_TEXTO = "#1F2933"
BRANCO      = "#FFFFFF"

# ---------- CONTEÚDO DA QUESTÃO-MODELO (PLACEHOLDER DIDÁTICO) ----------
# Substituir por questões reais do caderno 2025 História (PV_1) assim que o PDF do INEP
# estiver acessível ou for enviado pelo usuário.

QUESTAO = {
    "numero": "01",
    "disciplina": "História / Ensino de História",
    "tema": "Lei 10.639/03 – História e Cultura Afro-Brasileira",
    "subtema": "Aplicação em sala de aula e BNCC",
    "prova": "PND 2025 – Caderno de História – PV_1",
    "habilidade_bncc": "EF08HI13 / EF09HI06 (Anos Finais); competências específicas de Ciências Humanas (EM)",
    "enunciado": (
        "A Lei nº 10.639, de 9 de janeiro de 2003, alterou a Lei de Diretrizes e Bases da Educação "
        "Nacional (Lei nº 9.394/1996) para tornar obrigatório o ensino de História e Cultura Afro-Brasileira "
        "nas escolas de ensino fundamental e médio. Sobre a implementação dessa legislação no cotidiano escolar, "
        "à luz da BNCC e da produção historiográfica contemporânea, é CORRETO afirmar que:"
    ),
    "alternativas": {
        "A": "o ensino de História e Cultura Afro-Brasileira deve concentrar-se exclusivamente no mês de novembro, em atividades pontuais alusivas ao Dia da Consciência Negra.",
        "B": "a abordagem restringe-se à disciplina de História, sendo dispensável sua articulação com Arte, Literatura e demais componentes curriculares.",
        "C": "o trabalho pedagógico deve reconhecer o protagonismo dos povos africanos e afro-brasileiros na formação da sociedade brasileira, integrando conteúdos de forma transversal e ao longo de todo o ano letivo.",
        "D": "os conteúdos devem enfatizar prioritariamente a escravidão como marca definidora da experiência negra no Brasil, sem necessidade de contemplar a diáspora, a resistência e as produções culturais.",
        "E": "a Lei 10.639/03 tem caráter meramente sugestivo, cabendo à escola decidir se incorporará ou não seus conteúdos ao Projeto Político-Pedagógico.",
    },
    "gabarito": "C",
    "nivel": "Médio",
    "tipo": "Interpretativa / Didática",
    "formato": "Múltipla escolha (A-E)",
    "incidencia": "Alta",
    "justificativa_classificacao": (
        "Tema recorrente em provas docentes: articula legislação (LDB + Lei 10.639/03), BNCC e prática pedagógica. "
        "Exige leitura crítica das alternativas, todas plausíveis à primeira vista."
    ),
    "como_banca_pensou": (
        "O INEP cobra a compreensão INTEGRADA de: (1) letra da lei, (2) princípios da BNCC (educação antirracista, "
        "protagonismo de grupos historicamente silenciados) e (3) prática docente (transversalidade, ano letivo inteiro). "
        "A pegadinha central é induzir o professor a reduzir a Lei 10.639/03 a datas comemorativas ou a apenas à disciplina de História."
    ),
    "resolucao": [
        "Identifique o comando: 'à luz da BNCC e da produção historiográfica contemporânea'.",
        "Elimine alternativas com palavras-veneno: 'exclusivamente' (A), 'restringe-se' (B), 'prioritariamente...sem necessidade' (D), 'meramente sugestivo' (E).",
        "A alternativa C usa termos coerentes com a BNCC: 'protagonismo', 'transversal', 'ao longo de todo o ano letivo'.",
        "Gabarito: C.",
    ],
    "analise_alternativas": {
        "A": ("ERRADA", "Reduz a lei a atividades pontuais, contrariando o caráter obrigatório e contínuo previsto no art. 26-A da LDB."),
        "B": ("ERRADA", "Contraria o § 2º do art. 26-A da LDB, que prevê abordagem em todo o currículo escolar, com destaque para Arte, Literatura e História brasileiras."),
        "C": ("CORRETA", "Alinha-se ao art. 26-A da LDB (redação da Lei 10.639/03), à BNCC (educação antirracista) e à Resolução CNE/CP nº 1/2004."),
        "D": ("ERRADA", "Estereotipa a experiência negra como sinônimo de escravidão, apagando diáspora, resistência, quilombos e produções culturais."),
        "E": ("ERRADA", "A obrigatoriedade é legal; não é opcional. Descumpri-la implica infração à LDB."),
    },
    "fundamentacao": [
        "BRASIL. Lei nº 9.394, de 20 de dezembro de 1996. Estabelece as diretrizes e bases da educação nacional. "
        "Diário Oficial da União, Brasília, DF, 23 dez. 1996.",
        "BRASIL. Lei nº 10.639, de 9 de janeiro de 2003. Altera a Lei nº 9.394/1996 para incluir no currículo oficial "
        "da Rede de Ensino a obrigatoriedade da temática 'História e Cultura Afro-Brasileira'. Diário Oficial da União, "
        "Brasília, DF, 10 jan. 2003.",
        "BRASIL. Ministério da Educação. Base Nacional Comum Curricular (BNCC). Brasília: MEC, 2018.",
        "BRASIL. Conselho Nacional de Educação. Resolução CNE/CP nº 1, de 17 de junho de 2004. Institui Diretrizes "
        "Curriculares Nacionais para a Educação das Relações Étnico-Raciais e para o Ensino de História e Cultura "
        "Afro-Brasileira e Africana. Diário Oficial da União, Brasília, DF, 22 jun. 2004.",
    ],
    "teoria": (
        "A Lei 10.639/03 insere na LDB o art. 26-A, tornando obrigatório o ensino de História e Cultura Afro-Brasileira "
        "e, com a Lei 11.645/08, também Indígena. A abordagem deve ser TRANSVERSAL (todo o currículo, ao longo do ano), "
        "com ênfase em Arte, Literatura e História do Brasil. Historiograficamente, dialoga com a virada pós-colonial, "
        "a história atlântica (Paul Gilroy, Alberto da Costa e Silva) e estudos sobre a diáspora, resistência (quilombos, "
        "irmandades, imprensa negra) e agência dos povos africanos e afro-brasileiros na formação da sociedade brasileira. "
        "Na BNCC, atende às competências gerais 6, 9 e 10 e às competências específicas de Ciências Humanas."
    ),
    "padroes_banca": (
        "O INEP tende a formular itens com texto-base curto + comando exigindo articulação entre LEI, BNCC e PRÁTICA. "
        "Alternativas incorretas costumam usar advérbios totalizantes ('exclusivamente', 'somente', 'prioritariamente') "
        "ou reduzir a legislação a uma leitura literalista superficial."
    ),
    "pegadinhas": [
        "Confundir Lei 10.639/03 (afro-brasileira) com Lei 11.645/08 (indígena) — ambas alteram a LDB e coexistem.",
        "Reduzir o tema a datas comemorativas.",
        "Restringir a abordagem apenas à disciplina de História.",
    ],
    "erros_comuns": (
        "Muitos candidatos escolhem alternativas 'aparentemente moderadas' que, no entanto, contradizem a BNCC. "
        "O erro típico é acreditar que 'atividades pontuais' bastam para cumprir a lei."
    ),
    "dica_estrategica": (
        "Regra prática: em itens sobre Leis 10.639/03 e 11.645/08, elimine qualquer alternativa que restrinja "
        "(temporalmente, disciplinarmente ou culturalmente) a abordagem. A resposta correta quase sempre defende "
        "TRANSVERSALIDADE + PROTAGONISMO + CONTINUIDADE."
    ),
    "variacao": (
        "(INEP – PND – Estilo) A Lei nº 11.645/2008, ao alterar a LDB, tornou obrigatório também o ensino de História "
        "e Cultura dos Povos Indígenas. Considerando a BNCC e a legislação vigente, a implementação dessa determinação, "
        "no cotidiano escolar, requer que o(a) professor(a):\n"
        "A) trate o tema exclusivamente em Estudos Sociais no 5º ano;\n"
        "B) reconheça a diversidade dos povos originários e integre suas histórias e culturas de forma transversal ao currículo;\n"
        "C) restrinja a abordagem à data de 19 de abril;\n"
        "D) apresente os povos indígenas apenas no contexto do 'descobrimento';\n"
        "E) aborde o tema como conteúdo optativo do PPP.\n"
        "Gabarito: B."
    ),
    "minisimulado": [
        ("Sobre o art. 26-A da LDB, é CORRETO afirmar que a obrigatoriedade abrange:\n"
         "A) apenas o ensino médio; B) apenas escolas públicas; C) todas as escolas de ensino fundamental e médio, "
         "públicas e privadas; D) apenas o componente História; E) apenas o mês de novembro.  → C"),
        ("A Resolução CNE/CP nº 1/2004 estabelece diretrizes para:\n"
         "A) alfabetização; B) educação das relações étnico-raciais e ensino de História e Cultura Afro-Brasileira e Africana; "
         "C) educação especial; D) educação de jovens e adultos; E) formação de gestores. → B"),
        ("A perspectiva de 'protagonismo negro' na historiografia atual OPÕE-SE à visão que:\n"
         "A) reconhece a diáspora; B) reduz a experiência negra à escravidão; C) valoriza quilombos; "
         "D) estuda irmandades negras; E) analisa a imprensa negra. → B"),
    ],
    "resumo": {
        "regra": "Ensino de História e Cultura Afro-Brasileira é OBRIGATÓRIO, TRANSVERSAL e CONTÍNUO.",
        "excecoes": "Não há dispensa; aplica-se a todas as escolas de EF e EM, públicas e privadas.",
        "palavra_chave": "TRANSVERSALIDADE + PROTAGONISMO.",
        "artigo": "LDB, art. 26-A (redação da Lei 10.639/03 e Lei 11.645/08).",
        "mnemonico": "3 T's: Todas as escolas, Todo o currículo, Todo o ano letivo.",
    },
}

# ==========================================================
# ================== GERAÇÃO DO DOCX =======================
# ==========================================================

def _shade(cell, color_hex):
    """Aplica cor de fundo a uma célula (docx)."""
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

    # Estilo base
    style = doc.styles["Normal"]
    style.font.name = "Calibri"
    style.font.size = Pt(11)
    style.font.color.rgb = _hex(CINZA_TEXTO)

    for section in doc.sections:
        section.top_margin = Cm(2.0)
        section.bottom_margin = Cm(2.0)
        section.left_margin = Cm(2.2)
        section.right_margin = Cm(2.2)

    # ---------- CAPA ----------
    capa_tbl = doc.add_table(rows=1, cols=1)
    capa_tbl.autofit = False
    cell = capa_tbl.rows[0].cells[0]
    _shade(cell, AZUL_ESCURO)
    p = cell.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("\nMANUAL DE APROVAÇÃO POR QUESTÕES\n")
    run.bold = True
    run.font.size = Pt(24)
    run.font.color.rgb = _hex(BRANCO)

    p2 = cell.add_paragraph()
    p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r2 = p2.add_run("PND – Prova Nacional Docente\nÁrea: HISTÓRIA\n")
    r2.bold = True
    r2.font.size = Pt(16)
    r2.font.color.rgb = _hex(BRANCO)

    p3 = cell.add_paragraph()
    p3.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r3 = p3.add_run("Análise questão por questão – 15 blocos\n\n")
    r3.font.size = Pt(12)
    r3.font.color.rgb = _hex(BRANCO)

    doc.add_paragraph()

    # ---------- SUMÁRIO DOS BLOCOS ----------
    h = doc.add_paragraph()
    hr = h.add_run("Sumário da análise (15 blocos)")
    hr.bold = True
    hr.font.size = Pt(14)
    hr.font.color.rgb = _hex(AZUL_ESCURO)

    blocos = [
        "1. Identificação da Questão",
        "2. Enunciado",
        "3. Classificação",
        "4. Como a Banca Pensou",
        "5. Resolução Passo a Passo",
        "6. Análise das Alternativas",
        "7. Fundamentação Legal (ABNT)",
        "8. Teoria Extraída",
        "9. Padrões da Banca (INEP)",
        "10. Pegadinhas",
        "11. Erros mais Comuns",
        "12. Dica Estratégica",
        "13. Variação (Nova Questão)",
        "14. Mini-simulado",
        "15. Resumo de Memorização",
    ]
    for b in blocos:
        doc.add_paragraph(b, style="List Bullet")

    doc.add_page_break()

    # ---------- HELPERS DE BLOCO ----------
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

    # ---------- BLOCO 1 ----------
    bloco_titulo(f"QUESTÃO {QUESTAO['numero']} — {QUESTAO['tema']}")
    tbl = doc.add_table(rows=5, cols=2)
    tbl.style = "Light Grid Accent 1"
    tbl.autofit = False
    dados = [
        ("Disciplina", QUESTAO["disciplina"]),
        ("Tema / Subtema", f"{QUESTAO['tema']} — {QUESTAO['subtema']}"),
        ("Prova", QUESTAO["prova"]),
        ("Habilidade BNCC", QUESTAO["habilidade_bncc"]),
        ("Gabarito oficial", QUESTAO["gabarito"]),
    ]
    col0_w, col1_w = Cm(4.0), Cm(12.6)
    for i, (k, v) in enumerate(dados):
        c0 = tbl.rows[i].cells[0]
        c1 = tbl.rows[i].cells[1]
        c0.width = col0_w
        c1.width = col1_w
        c0.text = k
        c1.text = v
        _shade(c0, AZUL_CLARO)
    doc.add_paragraph()

    # ---------- BLOCO 2 ----------
    bloco_titulo("2. ENUNCIADO")
    bloco_destaque(QUESTAO["enunciado"])
    for letra, texto in QUESTAO["alternativas"].items():
        paragrafo(f"({letra}) {texto}")

    # ---------- BLOCO 3 ----------
    bloco_titulo("3. CLASSIFICAÇÃO")
    paragrafo(f"Nível: {QUESTAO['nivel']}   |   Tipo: {QUESTAO['tipo']}   |   Formato: {QUESTAO['formato']}   |   Incidência: {QUESTAO['incidencia']}", bold=True)
    paragrafo(QUESTAO["justificativa_classificacao"])

    # ---------- BLOCO 4 ----------
    bloco_titulo("4. COMO A BANCA PENSOU ESSA QUESTÃO")
    paragrafo(QUESTAO["como_banca_pensou"])

    # ---------- BLOCO 5 ----------
    bloco_titulo("5. RESOLUÇÃO PASSO A PASSO")
    for i, passo in enumerate(QUESTAO["resolucao"], 1):
        paragrafo(f"{i}. {passo}")

    # ---------- BLOCO 6 ----------
    bloco_titulo("6. ANÁLISE DE CADA ALTERNATIVA")
    for letra, (status, just) in QUESTAO["analise_alternativas"].items():
        p = doc.add_paragraph()
        r = p.add_run(f"({letra}) {status} — ")
        r.bold = True
        r.font.color.rgb = _hex(AZUL_ESCURO)
        p.add_run(just)

    # ---------- BLOCO 7 ----------
    bloco_titulo("7. FUNDAMENTAÇÃO LEGAL (ABNT NBR 6023)")
    for ref in QUESTAO["fundamentacao"]:
        paragrafo("• " + ref)

    # ---------- BLOCO 8 ----------
    bloco_titulo("8. TEORIA EXTRAÍDA DA QUESTÃO")
    paragrafo(QUESTAO["teoria"])

    # ---------- BLOCO 9 ----------
    bloco_titulo("9. PADRÕES DA BANCA (INEP)")
    paragrafo(QUESTAO["padroes_banca"])

    # ---------- BLOCO 10 ----------
    bloco_titulo("10. PEGADINHAS RELACIONADAS")
    for peg in QUESTAO["pegadinhas"]:
        paragrafo("• " + peg)

    # ---------- BLOCO 11 ----------
    bloco_titulo("11. ERROS MAIS COMUNS DOS CANDIDATOS")
    paragrafo(QUESTAO["erros_comuns"])

    # ---------- BLOCO 12 ----------
    bloco_titulo("12. DICA ESTRATÉGICA DE PROVA")
    bloco_destaque(QUESTAO["dica_estrategica"])

    # ---------- BLOCO 13 ----------
    bloco_titulo("13. VARIAÇÃO — NOVA QUESTÃO NO ESTILO INEP")
    paragrafo(QUESTAO["variacao"])

    # ---------- BLOCO 14 ----------
    bloco_titulo("14. MINI-SIMULADO DO TEMA")
    for i, q in enumerate(QUESTAO["minisimulado"], 1):
        paragrafo(f"Q{i}. {q}")

    # ---------- BLOCO 15 ----------
    bloco_titulo("15. RESUMO DE MEMORIZAÇÃO RÁPIDA")
    r = QUESTAO["resumo"]
    for k, label in [("regra", "Regra"), ("excecoes", "Exceções"),
                     ("palavra_chave", "Palavra-chave"), ("artigo", "Artigo essencial"),
                     ("mnemonico", "Mnemônico")]:
        p = doc.add_paragraph()
        run = p.add_run(f"{label}: ")
        run.bold = True
        run.font.color.rgb = _hex(AZUL_ESCURO)
        p.add_run(r[k])

    # Rodapé
    doc.add_paragraph()
    foot = doc.add_paragraph()
    foot.alignment = WD_ALIGN_PARAGRAPH.CENTER
    fr = foot.add_run("Projeto Gabaritando — Citações no padrão ABNT NBR 6023.")
    fr.italic = True
    fr.font.size = Pt(9)
    fr.font.color.rgb = _hex(AZUL_ESCURO)

    destino.parent.mkdir(parents=True, exist_ok=True)
    doc.save(destino)


# ==========================================================
# ================== GERAÇÃO DO PDF ========================
# ==========================================================

def gerar_pdf(destino: Path):
    destino.parent.mkdir(parents=True, exist_ok=True)

    def on_page(canvas, doc_):
        canvas.saveState()
        # Faixa superior azul
        canvas.setFillColor(HexColor(AZUL_ESCURO))
        canvas.rect(0, A4[1] - 1.2 * cm, A4[0], 1.2 * cm, fill=1, stroke=0)
        canvas.setFillColor(white)
        canvas.setFont("Helvetica-Bold", 10)
        canvas.drawString(2 * cm, A4[1] - 0.8 * cm, "Manual de Aprovação por Questões — PND História")
        canvas.drawRightString(A4[0] - 2 * cm, A4[1] - 0.8 * cm, "PND 2025 • Área: História")
        # Rodapé
        canvas.setFillColor(HexColor(AZUL_ESCURO))
        canvas.rect(0, 0, A4[0], 0.8 * cm, fill=1, stroke=0)
        canvas.setFillColor(white)
        canvas.setFont("Helvetica", 8)
        canvas.drawCentredString(A4[0] / 2, 0.3 * cm, f"Página {doc_.page}   •   Projeto Gabaritando   •   Citações ABNT NBR 6023")
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
    destaque = ParagraphStyle("destaque", parent=body, backColor=HexColor(AZUL_CLARO),
                              borderPadding=8, leading=15)

    story = []

    # Capa
    story.append(Spacer(1, 3 * cm))
    story.append(Paragraph("MANUAL DE APROVAÇÃO POR QUESTÕES", h_title))
    story.append(Paragraph("PND — Prova Nacional Docente<br/>Área: HISTÓRIA", h_sub))
    story.append(Paragraph("<b>Análise questão por questão — 15 blocos</b><br/>Caderno 2025 — PV_1", body))
    story.append(PageBreak())

    # Sumário
    story.append(Paragraph("Sumário — 15 blocos por questão", h_bloco))
    blocos = [
        "1. Identificação", "2. Enunciado", "3. Classificação", "4. Como a Banca Pensou",
        "5. Resolução Passo a Passo", "6. Análise das Alternativas", "7. Fundamentação (ABNT)",
        "8. Teoria Extraída", "9. Padrões da Banca", "10. Pegadinhas",
        "11. Erros Comuns", "12. Dica Estratégica", "13. Variação", "14. Mini-simulado", "15. Resumo",
    ]
    for b in blocos:
        story.append(Paragraph("• " + b, body))
    story.append(PageBreak())

    # ---- Bloco 1: Identificação ----
    story.append(Paragraph(f"QUESTÃO {QUESTAO['numero']} — {QUESTAO['tema']}", h_bloco))
    cell_style = ParagraphStyle("cell", parent=body, fontSize=10, leading=13, alignment=TA_LEFT)
    cell_key = ParagraphStyle("cell_key", parent=cell_style, fontName="Helvetica-Bold",
                              textColor=HexColor(AZUL_ESCURO))
    tbl_data = [
        [Paragraph("Disciplina", cell_key), Paragraph(QUESTAO["disciplina"], cell_style)],
        [Paragraph("Tema / Subtema", cell_key),
         Paragraph(f"{QUESTAO['tema']} — {QUESTAO['subtema']}", cell_style)],
        [Paragraph("Prova", cell_key), Paragraph(QUESTAO["prova"], cell_style)],
        [Paragraph("Habilidade BNCC", cell_key), Paragraph(QUESTAO["habilidade_bncc"], cell_style)],
        [Paragraph("Gabarito oficial", cell_key), Paragraph(QUESTAO["gabarito"], cell_style)],
    ]
    tbl = Table(tbl_data, colWidths=[4.0 * cm, 13.0 * cm])
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, -1), HexColor(AZUL_CLARO)),
        ("TEXTCOLOR", (0, 0), (0, -1), HexColor(AZUL_ESCURO)),
        ("FONTNAME", (0, 0), (0, -1), "Helvetica-Bold"),
        ("FONTNAME", (1, 0), (1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), 10),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), 0.4, HexColor(AZUL_MEDIO)),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    story.append(tbl)

    # ---- Bloco 2: Enunciado ----
    story.append(Paragraph("2. ENUNCIADO", h_bloco))
    story.append(Paragraph(QUESTAO["enunciado"], destaque))
    story.append(Spacer(1, 0.2 * cm))
    for letra, texto in QUESTAO["alternativas"].items():
        story.append(Paragraph(f"<b>({letra})</b> {texto}", body))

    # ---- Bloco 3 ----
    story.append(Paragraph("3. CLASSIFICAÇÃO", h_bloco))
    story.append(Paragraph(
        f"<b>Nível:</b> {QUESTAO['nivel']} &nbsp;|&nbsp; <b>Tipo:</b> {QUESTAO['tipo']} &nbsp;|&nbsp; "
        f"<b>Formato:</b> {QUESTAO['formato']} &nbsp;|&nbsp; <b>Incidência:</b> {QUESTAO['incidencia']}", body))
    story.append(Paragraph(QUESTAO["justificativa_classificacao"], body))

    # ---- Bloco 4 ----
    story.append(Paragraph("4. COMO A BANCA PENSOU ESSA QUESTÃO", h_bloco))
    story.append(Paragraph(QUESTAO["como_banca_pensou"], body))

    # ---- Bloco 5 ----
    story.append(Paragraph("5. RESOLUÇÃO PASSO A PASSO", h_bloco))
    for i, passo in enumerate(QUESTAO["resolucao"], 1):
        story.append(Paragraph(f"<b>{i}.</b> {passo}", body))

    # ---- Bloco 6 ----
    story.append(Paragraph("6. ANÁLISE DE CADA ALTERNATIVA", h_bloco))
    for letra, (status, just) in QUESTAO["analise_alternativas"].items():
        cor = "#0B3D91" if status == "CORRETA" else "#7A1F1F"
        story.append(Paragraph(
            f'<font color="{cor}"><b>({letra}) {status}</b></font> — {just}', body))

    # ---- Bloco 7 ----
    story.append(Paragraph("7. FUNDAMENTAÇÃO LEGAL (ABNT NBR 6023)", h_bloco))
    for ref in QUESTAO["fundamentacao"]:
        story.append(Paragraph("• " + ref, body))

    # ---- Bloco 8 ----
    story.append(Paragraph("8. TEORIA EXTRAÍDA DA QUESTÃO", h_bloco))
    story.append(Paragraph(QUESTAO["teoria"], body))

    # ---- Bloco 9 ----
    story.append(Paragraph("9. PADRÕES DA BANCA (INEP)", h_bloco))
    story.append(Paragraph(QUESTAO["padroes_banca"], body))

    # ---- Bloco 10 ----
    story.append(Paragraph("10. PEGADINHAS RELACIONADAS", h_bloco))
    for peg in QUESTAO["pegadinhas"]:
        story.append(Paragraph("• " + peg, body))

    # ---- Bloco 11 ----
    story.append(Paragraph("11. ERROS MAIS COMUNS DOS CANDIDATOS", h_bloco))
    story.append(Paragraph(QUESTAO["erros_comuns"], body))

    # ---- Bloco 12 ----
    story.append(Paragraph("12. DICA ESTRATÉGICA DE PROVA", h_bloco))
    story.append(Paragraph(QUESTAO["dica_estrategica"], destaque))

    # ---- Bloco 13 ----
    story.append(Paragraph("13. VARIAÇÃO — NOVA QUESTÃO NO ESTILO INEP", h_bloco))
    story.append(Paragraph(QUESTAO["variacao"].replace("\n", "<br/>"), body))

    # ---- Bloco 14 ----
    story.append(Paragraph("14. MINI-SIMULADO DO TEMA", h_bloco))
    for i, q in enumerate(QUESTAO["minisimulado"], 1):
        story.append(Paragraph(f"<b>Q{i}.</b> " + q.replace("\n", "<br/>"), body))

    # ---- Bloco 15 ----
    story.append(Paragraph("15. RESUMO DE MEMORIZAÇÃO RÁPIDA", h_bloco))
    r = QUESTAO["resumo"]
    for k, label in [("regra", "Regra"), ("excecoes", "Exceções"),
                     ("palavra_chave", "Palavra-chave"), ("artigo", "Artigo essencial"),
                     ("mnemonico", "Mnemônico")]:
        story.append(Paragraph(f'<font color="{AZUL_ESCURO}"><b>{label}:</b></font> {r[k]}', body))

    pdf = SimpleDocTemplate(str(destino), pagesize=A4,
                            leftMargin=2 * cm, rightMargin=2 * cm,
                            topMargin=1.8 * cm, bottomMargin=1.4 * cm,
                            title="Prévia PND História — Padrão Azul e Branco")
    pdf.build(story, onFirstPage=on_page, onLaterPages=on_page)


if __name__ == "__main__":
    base = Path(__file__).resolve().parent.parent
    saidas = base / "saidas"
    docx_path = saidas / "previa_pnd_historia.docx"
    pdf_path = saidas / "previa_pnd_historia.pdf"

    gerar_docx(docx_path)
    gerar_pdf(pdf_path)

    print(f"DOCX: {docx_path}")
    print(f"PDF:  {pdf_path}")
