#!/usr/bin/env python3
"""
Gera a Revisão – PMBA 2023 – Soldado (FCC): prova completa (80 questões) + roteiro da redação.

Mesmo padrão visual das revisões de Formosa do Rio Preto (capa, logo, marca d'água, gabarito rápido).

Saídas:
  saidas/revisao_pmba_2023_soldado.docx
  saidas/revisao_pmba_2023_soldado.pdf  (via LibreOffice, se disponível)
"""
from gerar_revisao_formosa_informatica import SAIDAS, gerar_docx, gerar_pdf
from pmba2023_gerais import (TEXTO_CRONICA, TEXTO_SACHS, PORTUGUES, MATEMATICA, HISTORIA,
                             GEOGRAFIA, ATUALIDADES, INFORMATICA)
from pmba2023_especificos import (CONSTITUCIONAL, DIREITOS_HUMANOS, ADMINISTRATIVO, PENAL,
                                  IGUALDADE, PENAL_MILITAR, REDACAO)

CG, CE = "Conhecimentos Gerais", "Conhecimentos Específicos"

PROVAS = [
    (CG, "Língua Portuguesa", PORTUGUES[:8], TEXTO_CRONICA),
    (CG, "Língua Portuguesa (texto 2)", PORTUGUES[8:], TEXTO_SACHS),
    (CG, "Matemática", MATEMATICA),
    (CG, "História do Brasil", HISTORIA),
    (CG, "Geografia do Brasil", GEOGRAFIA),
    (CG, "Atualidades", ATUALIDADES),
    (CG, "Informática", INFORMATICA),
    (CE, "Direito Constitucional", CONSTITUCIONAL),
    (CE, "Direitos Humanos", DIREITOS_HUMANOS),
    (CE, "Direito Administrativo", ADMINISTRATIVO),
    (CE, "Direito Penal", PENAL),
    (CE, "Igualdade Racial e de Gênero", IGUALDADE),
    (CE, "Direito Penal Militar", PENAL_MILITAR),
]

if __name__ == "__main__":
    nums = [q["num"] for _, _, qs, *_ in PROVAS for q in qs]
    assert nums == list(range(1, 81)), "a prova deve ter as questões 1 a 80, em ordem"
    SAIDAS.mkdir(exist_ok=True)
    destino = SAIDAS / "revisao_pmba_2023_soldado.docx"
    gerar_docx(destino, disciplina="Prova completa comentada",
               resumo="80 questões objetivas  •  Conhecimentos Gerais e Específicos  •  Roteiro da redação",
               provas=PROVAS, rodape="Revisão PMBA 2023 • Soldado (FCC)",
               titulo_capa="POLÍCIA MILITAR DA BAHIA – SOLDADO 2023 (FCC)",
               extras=[("PROVA DISCURSIVA – REDAÇÃO", REDACAO)])
    gerar_pdf(destino)
    print(f"OK: {destino}")
