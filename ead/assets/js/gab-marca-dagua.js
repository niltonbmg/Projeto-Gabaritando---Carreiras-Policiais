// assets/js/gab-marca-dagua.js
// Baixa um PDF com a marca d'água do Projeto Gabaritando em todas as páginas.
// Uso: <button data-pdf-marca="uploads/editais/x.pdf" data-nome="Edital PMBA.pdf">Baixar</button>
(function () {
    var LIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js';
    function carregarLib() {
        if (window.PDFLib) { return Promise.resolve(); }
        return new Promise(function (ok, erro) { var s = document.createElement('script'); s.src = LIB; s.onload = ok; s.onerror = erro; document.head.appendChild(s); });
    }
    function baixarBytes(bytes, nome) {
        var url = URL.createObjectURL(new Blob([bytes], {type: 'application/pdf'}));
        var a = document.createElement('a'); a.href = url; a.download = nome; document.body.appendChild(a); a.click();
        setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
    }
    async function marcar(urlPdf, nome) {
        await carregarLib();
        var L = window.PDFLib;
        var pdf = await L.PDFDocument.load(await (await fetch(urlPdf, {credentials: 'same-origin'})).arrayBuffer(), {ignoreEncryption: true});
        var fonte = await pdf.embedFont(L.StandardFonts.HelveticaBold);
        var logo = null;
        for (var u of ['img/logo.png', 'favicon.png']) {
            try { var r = await fetch(u); if (r.ok) { logo = await pdf.embedPng(await r.arrayBuffer()); break; } } catch (e) {}
        }
        pdf.getPages().forEach(function (pg) {
            var w = pg.getWidth(), h = pg.getHeight(), t = 'PROJETO GABARITANDO', tam = Math.min(w, h) / 11;
            pg.drawText(t, {x: w / 2 - fonte.widthOfTextAtSize(t, tam) / 2 * 0.7, y: h / 2 - fonte.widthOfTextAtSize(t, tam) / 2 * 0.7, size: tam, font: fonte, color: L.rgb(0.98, 0.72, 0.16), opacity: 0.13, rotate: L.degrees(45)});
            var rod = 'Material do aluno · Projeto Gabaritando · venda proibida';
            pg.drawText(rod, {x: 24, y: 12, size: 7.5, font: fonte, color: L.rgb(0.35, 0.35, 0.35), opacity: 0.8});
            if (logo) { var lw = 46, lh = logo.height * lw / logo.width; pg.drawImage(logo, {x: w - lw - 16, y: 10, width: lw, height: lh, opacity: 0.55}); }
        });
        baixarBytes(await pdf.save(), nome || 'material-projeto-gabaritando.pdf');
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pdf-marca]');
        if (!b) { return; }
        e.preventDefault();
        var txt = b.textContent; b.textContent = 'Preparando o PDF…'; b.setAttribute('aria-busy', 'true');
        marcar(b.getAttribute('data-pdf-marca'), b.getAttribute('data-nome')).catch(function () { alert('Não foi possível preparar o PDF agora. Tente de novo.'); })
            .then(function () { b.textContent = txt; b.removeAttribute('aria-busy'); });
    });
})();
