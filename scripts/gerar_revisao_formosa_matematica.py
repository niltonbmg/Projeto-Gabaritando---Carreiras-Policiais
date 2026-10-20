#!/usr/bin/env python3
"""
Gera a Revisão – Concurso de Formosa do Rio Preto 2026 – Matemática.

Mesmo padrão visual das revisões de Informática e Português.

Saídas:
  saidas/revisao_formosa_matematica.docx
  saidas/revisao_formosa_matematica.pdf  (via LibreOffice, se disponível)
"""
from gerar_revisao_formosa_informatica import SAIDAS, C, E, A, gerar_docx, gerar_pdf

MATEMATICA_P1 = [
    dict(num=11, tema="Conjuntos numéricos – naturais, inteiros e racionais",
         enunciado=("De acordo com as definições de conjunto dos números naturais, inteiros e racionais considere as afirmações:\n"
                    "I. O número π (pi) é racional.\n"
                    "II. O resultado da expressão (5 – 10 + 5) / 3 é um número natural.\n"
                    "III. O número (−1/2) é inteiro.\n"
                    "É correto afirmar que:"),
         alts={"A": "Apenas I é verdadeira.", "B": "Apenas II é verdadeira.", "C": "II e III são verdadeiras.",
               "D": "I e II são verdadeiras.", "E": "As três afirmações são verdadeiras."},
         gab="B",
         com={"A": (E, "I é FALSA: π = 3,14159... tem infinitas casas decimais sem período – é IRRACIONAL."),
              "B": (C, "II é VERDADEIRA: (5 – 10 + 5)/3 = 0/3 = 0, e 0 ∈ ℕ = {0, 1, 2, ...}. I e III são falsas."),
              "C": (E, "III é FALSA: −1/2 = −0,5 não é inteiro (é racional)."),
              "D": (E, "I é falsa (π é irracional)."),
              "E": (E, "I e III são falsas.")},
         dica="ℕ ⊂ ℤ ⊂ ℚ ⊂ ℝ. Zero é natural; π, √2 e e são irracionais; frações não inteiras são racionais, mas não inteiras."),
    dict(num=12, tema="Progressões – PA e PG",
         enunciado="Dada a PA, progressão aritmética (2, 5, 8, ...) e a PG, progressão geométrica (2, 6, 18, ...), a soma do 10º termo da PA com o 5º termo da PG é:",
         alts={"A": "161", "B": "171", "C": "181", "D": "191", "E": "201"},
         gab="D",
         com={"A": (E, "Não confere com 29 + 162."),
              "B": (E, "Não confere com 29 + 162."),
              "C": (E, "Não confere com 29 + 162."),
              "D": (C, "PA: a₁₀ = a₁ + 9r = 2 + 9·3 = 29.  PG: a₅ = a₁·q⁴ = 2·3⁴ = 2·81 = 162.  Soma: 29 + 162 = 191."),
              "E": (E, "Não confere com 29 + 162.")},
         dica="PA: aₙ = a₁ + (n–1)·r.  PG: aₙ = a₁·qⁿ⁻¹. Cuidado: o expoente é n–1, não n."),
    dict(num=13, tema="Equações do 1º e 2º grau",
         enunciado="O valor da soma das raízes ou soluções das equações:\n3(x + 1) – 2(x − 1) = 0  e  x² − 5x + 6 = 0\ncorresponde a:",
         alts={"A": "0", "B": "5", "C": "10", "D": "11", "E": "12"},
         gab="A",
         com={"A": (C, "1ª: 3x + 3 – 2x + 2 = 0 → x + 5 = 0 → x = −5.  2ª: raízes 2 e 3 (soma = −b/a = 5).  Total: −5 + 2 + 3 = 0."),
              "B": (E, "Considera só a equação do 2º grau e esquece a do 1º (x = −5)."),
              "C": (E, "Erro de sinal: usar x = +5 na 1ª equação (5 + 5 = 10)."),
              "D": (E, "Não corresponde a nenhuma combinação correta."),
              "E": (E, "Não corresponde a nenhuma combinação correta.")},
         dica="Atenção ao jogo de sinais: −2(x − 1) = −2x + 2. Soma das raízes do 2º grau = −b/a."),
    dict(num=14, tema="Juros simples",
         enunciado="O período de tempo que um capital leva para quadriplicar sob o regime de juro simples, a uma taxa de 25% ao mês é:",
         alts={"A": "6 meses", "B": "10 meses", "C": "1 ano", "D": "10 anos", "E": "12 anos"},
         gab="C",
         com={"A": (E, "Em 6 meses: J = C·0,25·6 = 1,5C → montante 2,5C (não quadruplica)."),
              "B": (E, "Em 10 meses: J = 2,5C → montante 3,5C."),
              "C": (C, "Quadruplicar: M = 4C → J = 3C.  J = C·i·t → 3C = C·0,25·t → t = 12 meses = 1 ano."),
              "D": (E, "Unidade errada: o resultado é 12 MESES."),
              "E": (E, "Pegadinha: 12 é o número certo, mas em MESES, não anos.")},
         dica="Quadruplicar NÃO é J = 4C: o montante é 4C, então os juros são 3C (M = C + J)."),
    dict(num=15, tema="Divisibilidade e número de divisores",
         enunciado=("Com relação às definições e conceitos de divisibilidade, considere as seguintes afirmações:\n"
                    "I. O número 120 possui mais de 8 divisores.\n"
                    "II. O número 29 possui apenas 2 divisores.\n"
                    "III. Um número qualquer só poderá ser divisível por 6 se for par.\n"
                    "É correto afirmar que:"),
         alts={"A": "Apenas I é verdadeira.", "B": "Apenas II é verdadeira.", "C": "Apenas II e III são verdadeiras.",
               "D": "As três afirmações são verdadeiras.", "E": "As três afirmações são falsas."},
         gab="D",
         com={"A": (E, "II e III também são verdadeiras."),
              "B": (E, "I e III também são verdadeiras."),
              "C": (E, "I também é verdadeira: 120 tem 16 divisores."),
              "D": (C, "I: 120 = 2³·3·5 → (3+1)(1+1)(1+1) = 16 divisores > 8. II: 29 é primo → divisores 1 e 29. III: divisível por 6 = divisível por 2 E por 3 → tem de ser par."),
              "E": (E, "Todas são verdadeiras.")},
         dica="Nº de divisores: fatore, some 1 a cada expoente e multiplique. 120 = 2³·3¹·5¹ → 4·2·2 = 16."),
    dict(num=16, tema="Função do 1º grau – sinais de a e b", anulavel=True,
         imagem=SAIDAS / "formosa_matematica_q16.png",
         enunciado=("Considere a função do 1º grau, f(x) = ax + b e seu gráfico representado abaixo.\n"
                    "Com relação à análise dos sinais do coeficiente angular a e do coeficiente linear b podemos afirmar:"),
         alts={"A": "a > 0 e b < 0", "B": "a > 0 e b = 0", "C": "a < 0 e b < 0", "D": "a > 0 e b > 0", "E": "a = 0 e b > 0"},
         gab="C",
         com={"A": (E, "A reta é DECRESCENTE (desce da esquerda para a direita), logo a < 0."),
              "B": (E, "b = 0 exigiria a reta passando pela origem – não passa."),
              "C": (C, "Reta decrescente → a < 0. Corta o eixo y ABAIXO da origem → b < 0. (Confere: raiz −b/a < 0, e a reta corta o eixo x à esquerda da origem.)"),
              "D": (A, "Seria a resposta se o eixo y fosse positivo PARA BAIXO, como sugere o rótulo 'y' na seta inferior do desenho."),
              "E": (E, "a = 0 seria reta horizontal (função constante), que nem é do 1º grau.")},
         dica="a > 0 sobe, a < 0 desce; b é onde a reta corta o eixo y. CABE RECURSO: o rótulo 'y' está na ponta de baixo do eixo; se y crescer para baixo, a resposta vira D."),
    dict(num=17, tema="Função do 2º grau – coeficientes, raízes e vértice",
         enunciado=("As afirmativas abaixo se referem à função f(x) = x² – 4x + 4:\n"
                    "I. O valor do coeficiente c é igual a 4.\n"
                    "II. Possui duas raízes distintas.\n"
                    "III. O gráfico da função possui vértice V de coordenadas (2, 0).\n"
                    "Está correto o que se afirma em:"),
         alts={"A": "Todas estão corretas.", "B": "Apenas I está correta.", "C": "I e II estão corretas.",
               "D": "I e III estão corretas.", "E": "Todas estão incorretas."},
         gab="D",
         com={"A": (E, "II é falsa."),
              "B": (E, "III também é correta."),
              "C": (E, "II é falsa: Δ = (−4)² − 4·1·4 = 0 → uma raiz dupla (x = 2), não duas distintas."),
              "D": (C, "I: c = 4 ✔.  III: xᵥ = −b/2a = 4/2 = 2; yᵥ = f(2) = 4 − 8 + 4 = 0 → V(2, 0) ✔."),
              "E": (E, "I e III são corretas.")},
         dica="Δ > 0 duas raízes distintas; Δ = 0 uma raiz dupla (vértice no eixo x); Δ < 0 nenhuma raiz real. x² – 4x + 4 = (x – 2)²."),
    dict(num=18, tema="Razão",
         enunciado="A razão entre dois números a e b, nesta ordem, é 1/2. É correto afirmar para a e b que:",
         alts={"A": "a = 10 e b = 20.", "B": "O valor do número b é a metade do número a.", "C": "A soma a + b = 3.",
               "D": "O valor do número a é a metade do número b.", "E": "Não podemos nada afirmar sobre os valores de a ou b."},
         gab="D",
         com={"A": (E, "É apenas UM exemplo possível (10/20 = 1/2), mas não se pode afirmar que a = 10 e b = 20 (poderia ser 3 e 6)."),
              "B": (E, "Invertido: é a que é a metade de b."),
              "C": (E, "Só valeria para a = 1 e b = 2; não é garantido."),
              "D": (C, "a/b = 1/2 → a = b/2: a é sempre a metade de b."),
              "E": (E, "Podemos afirmar a relação entre eles (a = b/2).")},
         dica="Razão 'a para b' = a/b. Alternativa com valores específicos (A) costuma ser só um caso particular, não uma afirmação garantida."),
    dict(num=19, tema="Números inteiros – deslocamentos na reta",
         enunciado=("Um morador estaciona seu veículo no 4º andar do subsolo de um edifício. Em seguida, desloca-se até o 5º andar para buscar seu filho e, posteriormente, dirige-se ao térreo do prédio.\n"
                    "Considerando os deslocamentos realizados, quantos andares o morador percorreu ao todo?"),
         alts={"A": "9 andares", "B": "10 andares", "C": "13 andares", "D": "14 andares", "E": "20 andares"},
         gab="D",
         com={"A": (E, "É só o 1º trecho (−4 → 5); esquece a volta ao térreo."),
              "B": (E, "Não corresponde aos deslocamentos."),
              "C": (E, "Não corresponde aos deslocamentos."),
              "D": (C, "Térreo = 0, subsolo 4 = −4.  −4 → 5: 9 andares.  5 → 0: 5 andares.  Total: 9 + 5 = 14."),
              "E": (E, "Não corresponde aos deslocamentos.")},
         dica="Modele como reta numérica (térreo = 0, subsolos negativos) e some as DISTÂNCIAS (valores absolutos) de cada trecho."),
    dict(num=20, tema="Porcentagem – aumento percentual",
         enunciado="Em certa capital, a tarifa do metrô avulsa custa R$ 4,10. No entanto, ao utilizar a integração com o sistema de ônibus, o valor total é unificado e passa a ser R$ 5,74. O percentual de aumento do valor total e unificado ao utilizar a integração é de:",
         alts={"A": "30%", "B": "40%", "C": "50%", "D": "60%", "E": "80%"},
         gab="B",
         com={"A": (E, "30% de 4,10 = 1,23 → 5,33."),
              "B": (C, "Aumento = 5,74 − 4,10 = 1,64.  1,64 / 4,10 = 0,40 = 40%.  (Ou: 5,74 / 4,10 = 1,4 → +40%.)"),
              "C": (E, "50% de 4,10 = 2,05 → 6,15."),
              "D": (E, "60% → 6,56."),
              "E": (E, "80% → 7,38.")},
         dica="Aumento percentual = (novo − antigo) / ANTIGO. Atalho: novo ÷ antigo = fator (1,4 → +40%)."),
]


if __name__ == "__main__":
    SAIDAS.mkdir(exist_ok=True)
    destino = SAIDAS / "revisao_formosa_matematica.docx"
    gerar_docx(destino, disciplina="Matemática",
               resumo="Prova 1 (Q11–Q20)  •  10 questões",
               provas=[("PROVA 1", "Matemática", MATEMATICA_P1)],
               rodape="Revisão Formosa do Rio Preto 2026 • Matemática")
    gerar_pdf(destino)
    print(f"OK: {destino}")
