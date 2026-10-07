#!/usr/bin/env python3
"""
Gera a Revisão – Concurso de Formosa do Rio Preto 2026 – Conhecimentos em Informática.

Saídas:
  saidas/revisao_formosa_informatica.docx
  saidas/revisao_formosa_informatica.pdf  (via LibreOffice, se disponível)

Estrutura:
  - Capa com a logo do Projeto Gabaritando
  - Sumário (campo TOC) + gabarito rápido
  - Prova 1 (Q21-Q25) e Prova 2 (Q21-Q30), comentadas alternativa por alternativa
"""
import subprocess
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

BASE = Path(__file__).resolve().parent.parent
SAIDAS = BASE / "saidas"
LOGO = SAIDAS / "logo_gabaritando.png"
LOGO_MARCA = SAIDAS / "logo_gabaritando_marca_dagua.png"
OPACIDADE_MARCA = 0.12  # 0 = invisível, 1 = logo original

# ---------- PALETA (identidade do Projeto Gabaritando) ----------
AMARELO = "#F5B700"
AMARELO_CLARO = "#FFF6D6"
PRETO = "#111111"
CINZA_TEXTO = "#1F2933"
VERDE_OK = "#1B7F3B"
VERM_ERR = "#9B1C1C"
LARANJA = "#B45309"

C, E, A = "CERTA", "ERRADA", "ATENÇÃO"

PROVAS = [
    ("PROVA 1", "Conhecimentos em Informática", [
        dict(num=21, tema="Windows 10 – Área de Transferência",
             enunciado="No MS-Windows 10, qual é a funcionalidade principal da Área de Transferência?",
             alts={"A": "Armazenar temporariamente arquivos e pastas excluídos.",
                   "B": "Permitir a criação de atalhos na área de trabalho.",
                   "C": "Facilitar a movimentação e cópia de textos, arquivos e pastas.",
                   "D": "Gerenciar as configurações de rede e internet.",
                   "E": "Controlar o acesso de usuários ao sistema operacional."},
             gab="C",
             com={"A": (E, "Quem guarda itens excluídos é a LIXEIRA, não a Área de Transferência."),
                  "B": (E, "Atalhos são criados pelo botão direito > Novo > Atalho (ou arrastando com Alt)."),
                  "C": (C, "A Área de Transferência guarda temporariamente o que foi copiado (Ctrl+C) ou recortado (Ctrl+X) até ser colado (Ctrl+V) – textos, imagens, arquivos e pastas."),
                  "D": (E, "Rede e internet ficam em Configurações > Rede e Internet."),
                  "E": (E, "Acesso de usuários é controlado por Contas de Usuário e pelo UAC.")},
             dica="Win+V abre o HISTÓRICO da Área de Transferência no Windows 10."),
        dict(num=22, tema="Word 2016 – Sumário automático",
             enunciado="Qual das opções abaixo permite inserir um índice automaticamente em um documento no MS-Word 2016?",
             alts={"A": "Inserir > Tabela", "B": "Referências > Sumário", "C": "Layout > Cabeçalho",
                   "D": "Revisão > Painel de Navegação", "E": "Exibir > Modo de Leitura"},
             gab="B",
             com={"A": (E, "Inserir > Tabela cria uma tabela de linhas e colunas."),
                  "B": (C, "Referências > Sumário gera a lista automaticamente a partir dos estilos de Título (Título 1, Título 2...)."),
                  "C": (E, "Cabeçalho fica em Inserir > Cabeçalho, não na guia Layout."),
                  "D": (E, "O Painel de Navegação fica em EXIBIR (não em Revisão) e apenas navega pelo documento."),
                  "E": (E, "O Modo de Leitura apenas altera a visualização.")},
             dica="No Word, 'Índice' (Referências > Inserir Índice) é o índice REMISSIVO; 'Sumário' lista títulos e páginas. A banca usou 'índice' no sentido de sumário."),
        dict(num=23, tema="Excel 2016 – Função MÉDIA",
             enunciado="No MS-Excel 2016, qual função é usada para calcular a média de um conjunto de valores?",
             alts={"A": "=SOMA()", "B": "=MÁXIMO()", "C": "=MÍNIMO()", "D": "=MÉDIA()", "E": "=CONTAR()"},
             gab="D",
             com={"A": (E, "SOMA() soma os valores."),
                  "B": (E, "MÁXIMO() retorna o maior valor."),
                  "C": (E, "MÍNIMO() retorna o menor valor."),
                  "D": (C, "MÉDIA() calcula a média aritmética. Ex.: =MÉDIA(A1:A10)."),
                  "E": (E, "CONTAR() não existe no Excel em português; as funções corretas são CONT.NÚM e CONT.VALORES.")},
             dica="MÉDIA ignora células vazias e textos – cuidado com questões que misturam zeros e células em branco."),
        dict(num=24, tema="Correio eletrônico – Anexos", anulavel=True,
             enunciado="Qual é a sequência correta para anexar um arquivo em uma mensagem de correio eletrônico?",
             alts={"A": "Abrir o programa de e-mail > Compor nova mensagem > Inserir assinatura > Anexar arquivo > Enviar.",
                   "B": "Abrir o programa de e-mail > Compor nova mensagem > Escrever o destinatário > Anexar arquivo > Enviar.",
                   "C": "Abrir o programa de e-mail > Compor nova mensagem > Anexar arquivo > Escrever a mensagem > Enviar.",
                   "D": "Abrir o programa de e-mail > Compor nova mensagem > Anexar arquivo > Escrever o destinatário > Enviar.",
                   "E": "Abrir o programa de e-mail > Compor nova mensagem > Escrever o destinatário > Escrever a mensagem > Anexar arquivo > Enviar."},
             gab="B",
             com={"A": (E, "A assinatura é opcional e não é etapa necessária para anexar."),
                  "B": (C, "Gabarito provável: fluxo mais enxuto e típico – abrir, compor, destinatário, anexar, enviar."),
                  "C": (A, "Sequência também funciona: nenhum cliente de e-mail exige ordem entre destinatário, corpo e anexo."),
                  "D": (A, "Também funciona – anexar antes de preencher o destinatário é perfeitamente possível."),
                  "E": (A, "Também funciona – é inclusive o fluxo mais completo.")},
             dica="QUESTÃO PASSÍVEL DE ANULAÇÃO: o único requisito real é anexar ANTES de enviar. Várias alternativas descrevem sequências válidas."),
        dict(num=25, tema="Internet – Sigla URL",
             enunciado="O que significa a sigla URL no contexto da internet?",
             alts={"A": "Universal Resource Locator", "B": "Unified Resource Link", "C": "Uniform Resource Locator",
                   "D": "Universal Reference Link", "E": "Uniform Reference Locator"},
             gab="C",
             com={"A": (E, "'Universal' é a pegadinha clássica; o correto é 'Uniform'."),
                  "B": (E, "Não é 'Unified' nem 'Link'."),
                  "C": (C, "URL = Uniform Resource Locator (Localizador Uniforme de Recursos): o endereço de um recurso na web."),
                  "D": (E, "'Universal' e 'Reference Link' estão errados."),
                  "E": (E, "É 'Resource' (recurso), não 'Reference'.")},
             dica="Estrutura da URL: protocolo://domínio/caminho  →  https://www.site.com.br/pagina"),
    ]),
    ("PROVA 2", "Conhecimentos em Informática", [
        dict(num=21, tema="Windows 10 – Atalhos",
             enunciado="No Windows 10, um atalho é utilizado para:",
             alts={"A": "Armazenar cópias permanentes de arquivos.",
                   "B": "Facilitar o acesso rápido a programas, arquivos ou pastas.",
                   "C": "Compactar arquivos automaticamente.",
                   "D": "Excluir arquivos sem enviá-los para a Lixeira.",
                   "E": "Converter arquivos para outros formatos."},
             gab="B",
             com={"A": (E, "O atalho é apenas um ponteiro (.lnk); não copia o arquivo. Apagar o atalho não apaga o original."),
                  "B": (C, "Atalho = acesso rápido a programas, arquivos ou pastas. Identificado pela setinha no ícone."),
                  "C": (E, "Compactar é feito em Enviar para > Pasta compactada (.zip)."),
                  "D": (E, "Excluir sem passar pela Lixeira é Shift+Delete."),
                  "E": (E, "Conversão de formatos depende de outros programas.")},
             dica="Apagar o atalho NÃO apaga o arquivo original – pegadinha recorrente."),
        dict(num=22, tema="Windows 10 – Pesquisa no Explorador de Arquivos",
             enunciado="Um usuário está organizando documentos em uma pasta do Windows 10 e deseja localizar rapidamente um arquivo cujo nome ele não lembra completamente. Qual recurso do Explorador de Arquivos pode auxiliá-lo nessa tarefa?",
             alts={"A": "Área de Transferência.", "B": "Gerenciador de Dispositivos.",
                   "C": "Caixa de pesquisa do Explorador de Arquivos.", "D": "Painel de Controle.", "E": "Central de Ações."},
             gab="C",
             com={"A": (E, "Área de Transferência serve para copiar/recortar/colar."),
                  "B": (E, "Gerenciador de Dispositivos cuida de hardware e drivers."),
                  "C": (C, "A caixa de pesquisa (Ctrl+E ou Ctrl+F) aceita parte do nome, curingas (* e ?) e filtros por data, tipo e tamanho."),
                  "D": (E, "Painel de Controle reúne configurações do sistema."),
                  "E": (E, "Central de Ações exibe notificações e ações rápidas.")},
             dica="Curinga * substitui vários caracteres; ? substitui um único caractere."),
        dict(num=23, tema="Word 2016 – Sumário automático",
             enunciado="No Microsoft Word 2016, ao elaborar um relatório com várias páginas, qual recurso permite inserir automaticamente uma lista contendo os títulos e subtítulos do documento, juntamente com seus respectivos números de página?",
             alts={"A": "Quebra de Seção.", "B": "Caixa de Texto.", "C": "Comentários.",
                   "D": "Índice (Sumário Automático).", "E": "Marca d'água."},
             gab="D",
             com={"A": (E, "Quebra de Seção divide o documento em seções com formatações distintas."),
                  "B": (E, "Caixa de Texto é um bloco de texto posicionável."),
                  "C": (E, "Comentários são anotações de revisão."),
                  "D": (C, "Sumário automático (Referências > Sumário), gerado a partir dos estilos de Título."),
                  "E": (E, "Marca d'água é texto/imagem ao fundo da página.")},
             dica="Para atualizar o sumário: clique nele e pressione F9 (ou 'Atualizar Sumário')."),
        dict(num=24, tema="Word 2016 – Cabeçalho",
             enunciado="No Word 2016, os cabeçalhos são utilizados para:",
             alts={"A": "Inserir comentários ocultos.", "B": "Criar fórmulas matemáticas.",
                   "C": "Exibir informações repetidas no topo das páginas.",
                   "D": "Alterar o idioma do documento.", "E": "Definir margens automáticas."},
             gab="C",
             com={"A": (E, "Comentários ficam em Revisão > Novo Comentário."),
                  "B": (E, "Fórmulas matemáticas: Inserir > Equação."),
                  "C": (C, "Cabeçalho = conteúdo repetido no topo de todas as páginas (logo, título, órgão)."),
                  "D": (E, "Idioma: Revisão > Idioma."),
                  "E": (E, "Margens: Layout > Margens.")},
             dica="Cabeçalho = topo; Rodapé = parte inferior (onde normalmente vai o número da página)."),
        dict(num=25, tema="Excel 2016 – Função MÉDIA",
             enunciado="No Excel 2016, a função MÉDIA tem a finalidade de:",
             alts={"A": "Identificar o maior valor de um intervalo.", "B": "Contar a quantidade de células preenchidas.",
                   "C": "Calcular a média aritmética dos valores selecionados.",
                   "D": "Somar apenas os valores positivos.", "E": "Localizar dados em uma tabela."},
             gab="C",
             com={"A": (E, "Maior valor = MÁXIMO."),
                  "B": (E, "Contar células preenchidas = CONT.VALORES."),
                  "C": (C, "MÉDIA soma os valores e divide pela quantidade (ignora vazias e textos)."),
                  "D": (E, "Somar apenas positivos = SOMASE(A1:A10;\">0\")."),
                  "E": (E, "Localizar dados = PROCV / PROCX / CORRESP.")},
             dica="Funções caem aos pares: MÉDIA × MÉDIASE, SOMA × SOMASE, CONT.NÚM × CONT.SE."),
        dict(num=26, tema="Excel 2016 – Classificar dados",
             enunciado="Em uma planilha do Excel 2016 contendo uma lista de servidores organizada por nome, cargo e setor, qual recurso é mais adequado para exibir primeiro todos os registros pertencentes a um determinado setor, mantendo os dados organizados?",
             alts={"A": "Inserir gráfico.", "B": "Congelar painéis.", "C": "Alterar o zoom da planilha.",
                   "D": "Classificar os dados.", "E": "Inserir comentário."},
             gab="D",
             com={"A": (E, "Gráfico apenas representa os dados visualmente."),
                  "B": (E, "Congelar Painéis fixa linhas/colunas na rolagem."),
                  "C": (E, "Zoom altera só a visualização."),
                  "D": (C, "Dados > Classificar ordena pela coluna 'Setor', agrupando os registros sem perder a estrutura."),
                  "E": (E, "Comentário é anotação em célula.")},
             dica="CLASSIFICAR reordena tudo; FILTRAR oculta o que não interessa. 'Exibir primeiro' = classificar."),
        dict(num=27, tema="Correio eletrônico – Anexos",
             enunciado="Ao enviar um e-mail com um documento em anexo, o usuário deve utilizar o recurso de:",
             alts={"A": "Encaminhamento.", "B": "Assinatura digital.", "C": "Anexação de arquivos.",
                   "D": "Lista de contatos.", "E": "Cópia oculta."},
             gab="C",
             com={"A": (E, "Encaminhar é reenviar uma mensagem recebida."),
                  "B": (E, "Assinatura digital garante autenticidade e integridade; não anexa arquivos."),
                  "C": (C, "Anexação de arquivos – geralmente o ícone de clipe."),
                  "D": (E, "Lista de contatos é a agenda de endereços."),
                  "E": (E, "Cópia oculta (Cco/Bcc) envia a destinatários invisíveis aos demais.")},
             dica="Para (To) • Cc (cópia visível) • Cco (cópia oculta) – tema campeão de provas."),
        dict(num=28, tema="Internet – Mecanismos de busca",
             enunciado="Ao realizar uma pesquisa na Internet utilizando mecanismos de busca, o uso de palavras-chave adequadas contribui para:",
             alts={"A": "Aumentar a velocidade da conexão de internet.", "B": "Impedir o acesso a páginas da web.",
                   "C": "Eliminar a necessidade de navegação.",
                   "D": "Encontrar informações mais relevantes sobre o assunto pesquisado.",
                   "E": "Bloquear anúncios automaticamente."},
             gab="D",
             com={"A": (E, "Velocidade depende da conexão, não da pesquisa."),
                  "B": (E, "Palavras-chave não bloqueiam páginas."),
                  "C": (E, "Ainda é preciso navegar pelos resultados."),
                  "D": (C, "Boas palavras-chave e operadores trazem resultados mais relevantes."),
                  "E": (E, "Bloqueio de anúncios é função de adblockers.")},
             dica="Operadores: \"frase exata\" • -excluir • site:gov.br • filetype:pdf"),
        dict(num=29, tema="Navegadores – Hyperlink",
             enunciado="Em um navegador web, um hyperlink (link) tem a função de:",
             alts={"A": "Bloquear páginas maliciosas.", "B": "Armazenar páginas favoritas.",
                   "C": "Atualizar automaticamente o sistema operacional.",
                   "D": "Direcionar o usuário para outro conteúdo ou página.", "E": "Compactar arquivos para download."},
             gab="D",
             com={"A": (E, "Bloqueio de páginas maliciosas: filtros e SmartScreen."),
                  "B": (E, "Páginas salvas são os Favoritos (marcadores)."),
                  "C": (E, "Atualização do sistema: Windows Update."),
                  "D": (C, "O hyperlink leva a outra página, arquivo ou a um ponto do mesmo documento."),
                  "E": (E, "Compactar arquivos é tarefa de outros programas.")},
             dica="Ctrl+clique abre o link em nova guia."),
        dict(num=30, tema="Microsoft Teams – Trabalho colaborativo",
             enunciado="No Microsoft Teams, uma das vantagens do trabalho colaborativo em equipe é:",
             alts={"A": "Impedir o compartilhamento de documentos.",
                   "B": "Limitar o acesso aos arquivos apenas ao criador.",
                   "C": "Excluir automaticamente versões anteriores dos documentos.",
                   "D": "Permitir que vários usuários trabalhem simultaneamente em arquivos compartilhados.",
                   "E": "Restringir a comunicação entre os membros da equipe."},
             gab="D",
             com={"A": (E, "Oposto da colaboração: o Teams facilita o compartilhamento."),
                  "B": (E, "Oposto: o acesso é compartilhado com a equipe."),
                  "C": (E, "O Teams MANTÉM o histórico de versões (via SharePoint/OneDrive)."),
                  "D": (C, "Coautoria em tempo real, com chat, reuniões e compartilhamento integrados."),
                  "E": (E, "Oposto: o Teams amplia a comunicação.")},
             dica="Em questões de 'vantagem', descarte alternativas com verbos restritivos: impedir, limitar, excluir, restringir."),
    ]),
]


# ---------- HELPERS ----------
def _hex(c):
    c = c.lstrip("#")
    return RGBColor(int(c[0:2], 16), int(c[2:4], 16), int(c[4:6], 16))


def _shade(cell, color_hex):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color_hex.lstrip("#"))
    tc_pr.append(shd)


def _bordas_celula(cell, cor, lado="left", tam=24):
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = OxmlElement("w:tcBorders")
    b = OxmlElement(f"w:{lado}")
    b.set(qn("w:val"), "single"); b.set(qn("w:sz"), str(tam)); b.set(qn("w:color"), cor.lstrip("#"))
    borders.append(b)
    tc_pr.append(borders)


def _run(p, texto, bold=False, size=11, cor=CINZA_TEXTO, italic=False):
    r = p.add_run(texto)
    r.bold = bold; r.italic = italic
    r.font.size = Pt(size); r.font.color.rgb = _hex(cor)
    return r


def _campo(p, instr, placeholder, size=9, cor=PRETO):
    p._p.append(parse_xml(
        '<w:fldSimple xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        f'w:instr=" {instr} "><w:r><w:rPr><w:sz w:val="{size*2}"/><w:color w:val="{cor.lstrip("#")}"/>'
        f'</w:rPr><w:t>{placeholder}</w:t></w:r></w:fldSimple>'))


def _cabecalho_rodape(section, disciplina):
    """Logo pequena no cabeçalho (direita) + linha amarela; 'Página N' no rodapé."""
    hp = section.header.paragraphs[0]
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    hp.add_run().add_picture(str(LOGO), height=Cm(1.1))
    pPr = hp._p.get_or_add_pPr()
    bdr = parse_xml('<w:pBdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                    f'<w:bottom w:val="single" w:sz="12" w:space="4" w:color="{AMARELO.lstrip("#")}"/></w:pBdr>')
    pPr.append(bdr)

    fp = section.footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    _run(fp, f"Revisão Formosa do Rio Preto 2026 • {disciplina}  |  Página ", size=9, cor=PRETO)
    _campo(fp, "PAGE", "1")


def _gerar_logo_marca_dagua():
    """Versão bem clara da logo (mistura com branco) para não atrapalhar a leitura."""
    from PIL import Image
    im = Image.open(LOGO).convert("RGB")
    branco = Image.new("RGB", im.size, (255, 255, 255))
    Image.blend(branco, im, OPACIDADE_MARCA).save(LOGO_MARCA)


def _inserir_marca_dagua(section, largura_cm=16.0):
    """Logo centralizada ATRÁS do texto em todas as páginas da seção (imagem ancorada no cabeçalho)."""
    p = section.header.add_paragraph()
    run = p.add_run()
    inline = run.add_picture(str(LOGO_MARCA), width=Cm(largura_cm))._inline
    cx, cy = inline.extent.cx, inline.extent.cy
    graphic = inline.graphic.xml
    anchor = parse_xml(f"""<wp:anchor xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="0" behindDoc="1"
  locked="1" layoutInCell="1" allowOverlap="1">
  <wp:simplePos x="0" y="0"/>
  <wp:positionH relativeFrom="margin"><wp:align>center</wp:align></wp:positionH>
  <wp:positionV relativeFrom="page"><wp:align>center</wp:align></wp:positionV>
  <wp:extent cx="{cx}" cy="{cy}"/>
  <wp:effectExtent l="0" t="0" r="0" b="0"/>
  <wp:wrapNone/>
  <wp:docPr id="999" name="Marca dagua"/>
  <wp:cNvGraphicFramePr/>
</wp:anchor>""")
    anchor.append(parse_xml(graphic))
    inline.getparent().replace(inline, anchor)


def _capa(doc, disciplina, resumo):
    sec = doc.sections[0]
    p = doc.add_paragraph(); p.paragraph_format.space_before = Cm(2.5)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(LOGO), width=Cm(15))

    # faixa amarela
    t = doc.add_table(rows=1, cols=1)
    cell = t.rows[0].cells[0]; _shade(cell, AMARELO)
    cp = cell.paragraphs[0]; cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cp.paragraph_format.space_before = Pt(18); cp.paragraph_format.space_after = Pt(4)
    _run(cp, "REVISÃO", bold=True, size=40, cor=PRETO)
    cp2 = cell.add_paragraph(); cp2.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cp2.paragraph_format.space_after = Pt(18)
    _run(cp2, "CONCURSO DE FORMOSA DO RIO PRETO 2026", bold=True, size=18, cor=PRETO)

    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Cm(1.5)
    _run(p, disciplina.upper(), bold=True, size=20, cor=PRETO)
    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    _run(p, "Gabarito comentado alternativa por alternativa", size=14, cor=CINZA_TEXTO, italic=True)
    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Cm(1.2)
    _run(p, resumo, size=12, cor=CINZA_TEXTO)

    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Cm(4)
    _run(p, "VEM GABARITAR COM A GENTE", bold=True, size=13, cor=AMARELO)


def _sumario_e_gabarito(doc, provas):
    p = doc.add_paragraph(); _run(p, "SUMÁRIO", bold=True, size=16, cor=PRETO)
    for nome, disc, qs in provas:
        p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(6); p.paragraph_format.space_after = Pt(2)
        _run(p, f"{nome} – {disc}", bold=True, size=11, cor=PRETO)
        for q in qs:
            p = doc.add_paragraph(); p.paragraph_format.left_indent = Cm(0.6)
            p.paragraph_format.space_after = Pt(0)
            _run(p, f"Questão {q['num']:02d}  •  ", bold=True, size=10, cor=AMARELO)
            _run(p, q["tema"], size=10)

    p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(18)
    _run(p, "GABARITO RÁPIDO", bold=True, size=16, cor=PRETO)
    for nome, disc, qs in provas:
        p = doc.add_paragraph(); _run(p, f"{nome} – {disc}", bold=True, size=12, cor=PRETO)
        t = doc.add_table(rows=2, cols=len(qs)); t.style = "Table Grid"
        for i, q in enumerate(qs):
            c0, c1 = t.rows[0].cells[i], t.rows[1].cells[i]
            _shade(c0, PRETO); _shade(c1, AMARELO_CLARO)
            for c, txt, cor in ((c0, f"Q{q['num']:02d}", "#FFFFFF"), (c1, q["gab"] + ("*" if q.get("anulavel") else ""), PRETO)):
                cp = c.paragraphs[0]; cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
                _run(cp, txt, bold=True, size=11, cor=cor)
    for nome, _, qs in provas:
        for q in qs:
            if q.get("anulavel"):
                p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(6)
                _run(p, f"* {nome.title()} – Q{q['num']:02d}: questão passível de recurso/anulação (ver comentário).",
                     size=9, cor=LARANJA, italic=True)


def _questao(doc, prova, q):
    h = doc.add_heading(level=2)
    _run(h, f"{prova} – Questão {q['num']:02d} • {q['tema']}", bold=True, size=13, cor=PRETO)

    # enunciado em caixa amarela clara
    t = doc.add_table(rows=1, cols=1); cell = t.rows[0].cells[0]
    _shade(cell, AMARELO_CLARO); _bordas_celula(cell, AMARELO)
    cp = cell.paragraphs[0]; _run(cp, q["enunciado"], size=11, cor=PRETO)
    for letra, txt in q["alts"].items():
        ap = cell.add_paragraph(); ap.paragraph_format.left_indent = Cm(0.4)
        _run(ap, f"{letra.lower()}) ", bold=True, size=10.5, cor=PRETO)
        _run(ap, txt, size=10.5)

    p = doc.add_paragraph(); p.paragraph_format.space_before = Pt(8)
    _run(p, "GABARITO: ", bold=True, size=12, cor=PRETO)
    _run(p, f" {q['gab']} ", bold=True, size=12, cor=PRETO).font.highlight_color = 7  # amarelo

    p = doc.add_paragraph(); _run(p, "Análise alternativa por alternativa", bold=True, size=11, cor=PRETO)
    for letra, (status, texto) in q["com"].items():
        cor = {C: VERDE_OK, E: VERM_ERR, A: LARANJA}[status]
        ap = doc.add_paragraph(); ap.paragraph_format.left_indent = Cm(0.3)
        ap.paragraph_format.space_after = Pt(3)
        _run(ap, f"{letra.lower()}) {status} – ", bold=True, size=10.5, cor=cor)
        _run(ap, texto, size=10.5)

    t = doc.add_table(rows=1, cols=1); cell = t.rows[0].cells[0]
    _shade(cell, "#F3F4F6"); _bordas_celula(cell, PRETO)
    cp = cell.paragraphs[0]
    _run(cp, "💡 DICA GABARITANDO: ", bold=True, size=10, cor=PRETO)
    _run(cp, q["dica"], size=10)
    doc.add_paragraph()


def _texto_base(doc, texto):
    titulo, paragrafos, fonte = texto
    h = doc.add_heading(level=2)
    _run(h, "Texto base", bold=True, size=13, cor=PRETO)
    t = doc.add_table(rows=1, cols=1); cell = t.rows[0].cells[0]
    _bordas_celula(cell, AMARELO)
    cp = cell.paragraphs[0]; cp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    _run(cp, titulo, bold=True, size=12, cor=PRETO)
    for par in paragrafos:
        ap = cell.add_paragraph(); ap.paragraph_format.space_after = Pt(4)
        ap.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        intertitulo = par.endswith("?")
        _run(ap, par, bold=intertitulo, size=10.5, cor=PRETO if intertitulo else CINZA_TEXTO)
    fp = cell.add_paragraph(); fp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    _run(fp, fonte, size=9, italic=True)
    doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)


def gerar_docx(destino: Path, disciplina="Conhecimentos em Informática",
               resumo="Prova 1 (Q21–Q25)  •  Prova 2 (Q21–Q30)  •  15 questões",
               provas=PROVAS, rodape="Informática", textos=None):
    """textos: {nome_da_prova: (titulo, [parágrafos], fonte)} – texto base exibido antes das questões."""
    doc = Document()
    st = doc.styles["Normal"]; st.font.name = "Calibri"; st.font.size = Pt(11)
    for nome in ("Heading 1", "Heading 2"):
        doc.styles[nome].font.name = "Calibri"

    sec = doc.sections[0]
    for attr in ("top_margin", "bottom_margin"):
        setattr(sec, attr, Cm(2.0))
    sec.left_margin = sec.right_margin = Cm(2.2)

    _capa(doc, disciplina, resumo)

    # nova seção para o conteúdo (capa fica sem cabeçalho/rodapé)
    sec2 = doc.add_section(WD_SECTION.NEW_PAGE)
    sec2.header.is_linked_to_previous = False
    sec2.footer.is_linked_to_previous = False
    _cabecalho_rodape(sec2, rodape)
    _gerar_logo_marca_dagua()
    _inserir_marca_dagua(sec2)

    _sumario_e_gabarito(doc, provas)

    for nome, disc, qs in provas:
        doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)
        h = doc.add_heading(level=1)
        _run(h, f"{nome} – {disc.upper()}", bold=True, size=18, cor=PRETO)
        if textos and nome in textos:
            _texto_base(doc, textos[nome])
        for q in qs:
            _questao(doc, nome.title(), q)

    doc.save(destino)


def gerar_pdf(docx: Path):
    try:
        subprocess.run(["soffice", "--headless", "--convert-to", "pdf", "--outdir", str(docx.parent), str(docx)],
                       check=True, capture_output=True, timeout=180)
    except (FileNotFoundError, subprocess.SubprocessError) as e:
        print(f"PDF não gerado: {e}")


if __name__ == "__main__":
    SAIDAS.mkdir(exist_ok=True)
    destino = SAIDAS / "revisao_formosa_informatica.docx"
    gerar_docx(destino)
    gerar_pdf(destino)
    print(f"OK: {destino}")
