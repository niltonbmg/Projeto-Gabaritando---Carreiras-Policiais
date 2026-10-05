#!/usr/bin/env python3
"""Gera o Gabarito Comentado do Simulado 01 – CFOA PMBA 2026.

Saídas: saidas/gabarito_comentado_cfoa_pmba_simulado01.{md,docx}
"""
from pathlib import Path

from docx import Document
from docx.shared import Pt, RGBColor

from dados_cfoa_pmba_simulado01 import Q

BASE = Path(__file__).resolve().parent.parent
SAIDAS = BASE / "saidas"
TITULO = "Gabarito Comentado – Simulado 01 – CFOA PMBA 2026"
NOME = "gabarito_comentado_cfoa_pmba_simulado01"


def eh_correta(texto):
    return texto.startswith(("CORRETA", "INCORRETA (gabarito)", "GABARITO"))


def gerar_md(destino):
    ln = [f"# {TITULO}", "", "Projeto Gabaritando • Oficial Auxiliar (QOA) • 80 questões objetivas", "",
          "## Quadro-resumo", "", "| Q | Gab. | Q | Gab. | Q | Gab. | Q | Gab. |", "|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|"]
    for i in range(1, 21):
        ln.append("| " + " | ".join(f"{n:02d} | **{Q[n][1]}**" for n in (i, i + 20, i + 40, i + 60)) + " |")
    disc = None
    for n in sorted(Q):
        d, gab, alts, fund, obs = Q[n]
        if d != disc:
            disc = d
            ln += ["", f"## {d}"]
        ln += ["", f"### Questão {n:02d} — Gabarito: {gab}", ""]
        for letra in "ABCDE":
            marca = "✅" if letra == gab else "❌"
            ln.append(f"- **{letra})** {marca} {alts[letra]}")
        ln += ["", f"**Fundamento:** {fund}"]
        if obs:
            ln += ["", f"> ⚠️ {obs}"]
    destino.write_text("\n".join(ln) + "\n")


def gerar_docx(destino):
    verde, vermelho, azul = RGBColor(0x1B, 0x6E, 0x2F), RGBColor(0x7A, 0x1F, 0x1F), RGBColor(0x0B, 0x3D, 0x91)
    doc = Document()
    doc.styles["Normal"].font.name = "Calibri"
    doc.styles["Normal"].font.size = Pt(10.5)
    doc.add_heading(TITULO, 0)
    doc.add_paragraph("Projeto Gabaritando • Oficial Auxiliar (QOA) • 80 questões objetivas")
    doc.add_heading("Quadro-resumo", 1)
    tab = doc.add_table(rows=21, cols=8)
    tab.style = "Light Grid Accent 1"
    for c in range(4):
        tab.cell(0, 2 * c).text, tab.cell(0, 2 * c + 1).text = "Questão", "Gab."
        for i in range(20):
            n = i + 1 + 20 * c
            tab.cell(i + 1, 2 * c).text = f"{n:02d}"
            tab.cell(i + 1, 2 * c + 1).text = Q[n][1]
    disc = None
    for n in sorted(Q):
        d, gab, alts, fund, obs = Q[n]
        if d != disc:
            disc = d
            doc.add_page_break()
            doc.add_heading(d, 1)
        h = doc.add_heading(f"Questão {n:02d} — Gabarito: {gab}", 2)
        h.runs[0].font.color.rgb = azul
        for letra in "ABCDE":
            p = doc.add_paragraph(style="List Bullet")
            r = p.add_run(f"{letra}) ")
            r.bold = True
            r.font.color.rgb = verde if letra == gab else vermelho
            p.add_run(alts[letra])
        p = doc.add_paragraph()
        p.add_run("Fundamento: ").bold = True
        p.add_run(fund)
        if obs:
            p = doc.add_paragraph()
            r = p.add_run("Atenção: " + obs)
            r.italic = True
            r.font.color.rgb = vermelho
    doc.save(destino)


def main():
    assert sorted(Q) == list(range(1, 81))
    for n, (_, gab, alts, _, _) in Q.items():
        assert set(alts) == set("ABCDE") and eh_correta(alts[gab]), n
    SAIDAS.mkdir(exist_ok=True)
    gerar_md(SAIDAS / f"{NOME}.md")
    gerar_docx(SAIDAS / f"{NOME}.docx")
    print("OK: 80 questões")


if __name__ == "__main__":
    main()
