#!/usr/bin/env python3
"""Parseia downloads/pnd_raw.txt (pdftotext sem -layout) do caderno PND 2025 História PV_1."""
import re, json
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
raw = (BASE / "downloads/pnd_raw.txt").read_text()

# Encontrar posições dos marcadores "QUESTÃO NN" (linha exclusiva).
marks = list(re.finditer(r"(?m)^QUESTÃO\s+(\d+)\s*$", raw))

def limpar_bloco(t: str) -> str:
    lines = []
    for ln in t.splitlines():
        s = ln.strip()
        if not s:
            lines.append("")
            continue
        if "PND2025PND2025" in s:
            continue
        if re.fullmatch(r"\*+R?\d+\*+", s):
            continue
        if re.match(r"^R\d+ - HISTORIA", s):
            continue
        if re.match(r"^\d{2}/\d{2}/\d{4}", s):
            continue
        if s == "2025":
            continue
        if re.fullmatch(r"\d{1,3}", s):
            continue
        lines.append(ln)
    # colapsar múltiplas linhas em branco
    out = re.sub(r"\n{3,}", "\n\n", "\n".join(lines)).strip()
    return out

# Fim de bloco: próxima QUESTÃO ou seção final
FIM_SEC = re.compile(r"(?m)^(QUESTÃO DISCURSIVA|ÁREA DE RASCUNHO|QUESTIONÁRIO DE PERCEPÇÃO|Questionário de Percepção|Formação Geral Docente|Componente Específico)")

# Guardaremos as questões OBJETIVAS 1..80 (ignorando Questionário de Percepção,
# que reinicia a numeração de 01 a 09 e não é conteudístico)
questoes = []
seen = set()
for i, m in enumerate(marks):
    num = int(m.group(1))
    ini = m.end()
    fim = marks[i + 1].start() if i + 1 < len(marks) else len(raw)
    body = raw[ini:fim]
    # se aparecer marcador de seção antes da próxima QUESTÃO, cortar
    fs = FIM_SEC.search(body)
    if fs:
        body = body[:fs.start()]
    body = limpar_bloco(body)

    # Se num já visto e for baixo (1..9) provavelmente é Questionário de Percepção — pular
    if num in seen:
        continue

    # separar enunciado das alternativas
    alt_iter = list(re.finditer(r"(?m)^([A-E])\s+(.+?)(?=(?:\n[A-E]\s+)|\Z)",
                                body, flags=re.DOTALL))
    if not alt_iter:
        continue
    enunciado = body[: alt_iter[0].start()].strip()
    alts = {}
    for mm in alt_iter:
        letra = mm.group(1)
        texto = re.sub(r"\s+", " ", mm.group(2)).strip()
        # cortar textos que caíram para a próxima questão
        alts[letra] = texto

    # ignorar se todas as alternativas são muito curtas (< 5 chars) → é o Questionário de Percepção
    if all(len(v) < 5 for v in alts.values()):
        continue

    # aceitar apenas 1..80
    if not (1 <= num <= 80):
        continue

    secao = "Formação Geral Docente" if num <= 30 else "Componente Específico – História"
    questoes.append({
        "numero": num,
        "secao": secao,
        "enunciado": re.sub(r"[ \t]+", " ", enunciado).strip(),
        "alternativas": alts,
    })
    seen.add(num)

questoes.sort(key=lambda x: x["numero"])
out = BASE / "downloads/questoes.json"
out.write_text(json.dumps(questoes, ensure_ascii=False, indent=2))
print(f"{len(questoes)} questões salvas")
faltando = [i for i in range(1, 81) if i not in {q['numero'] for q in questoes}]
print("faltando:", faltando)
for q in questoes:
    if len(q['alternativas']) != 4:
        print("  ATENÇÃO Q", q['numero'], "alternativas:", list(q['alternativas']))
