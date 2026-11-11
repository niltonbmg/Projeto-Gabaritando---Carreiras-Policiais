// assets/js/ia-custo.js
// Antes de qualquer botão que usa IA: mostra quanto vai gastar (tokens, chamadas, R$) e pede confirmação.
// Funciona sozinho em qualquer formulário: lê o campo "acao" (ou "fila") e pergunta ao servidor.
(function () {
  var base = (document.currentScript && document.currentScript.dataset.api) || 'api/ia-estimativa.php';
  var liberado = new WeakSet();
  function brl(v) { v = Number(v || 0); return 'R$ ' + v.toFixed(v > 0 && v < 0.1 ? 3 : 2).replace('.', ','); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function campo(form, sub, nome) {
    if (sub && sub.name === nome) return sub.value;
    var i = form.querySelector('[name="' + nome + '"]');
    return i ? i.value : '';
  }
  function dialogo(html, okTxt, cb) {
    var d = document.createElement('div');
    d.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px';
    d.innerHTML = '<div style="background:#fff;color:#111;max-width:520px;width:100%;border-radius:14px;padding:20px;font:14px/1.5 system-ui,sans-serif;box-shadow:0 10px 40px rgba(0,0,0,.3)">' + html +
      '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button type="button" data-n style="padding:8px 14px;border-radius:8px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancelar</button>' +
      '<button type="button" data-s style="padding:8px 14px;border-radius:8px;border:0;background:#c9a227;font-weight:700;cursor:pointer">' + okTxt + '</button></div></div>';
    document.body.appendChild(d);
    d.querySelector('[data-n]').onclick = function () { d.remove(); };
    d.querySelector('[data-s]').onclick = function () { d.remove(); cb(); };
  }
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement) || liberado.has(form)) return;
    var sub = ev.submitter || null;
    var acao = campo(form, sub, 'acao') || campo(form, sub, 'fila');
    if (!acao) return;
    ev.preventDefault();
    ev.stopImmediatePropagation(); // o confirm() antigo do formulário entra no nosso diálogo
    var antigo = (form.getAttribute('onsubmit') || '').match(/confirm\((['"])([\s\S]*?)\1\)/);
    var q = new URLSearchParams(location.search);
    var p = new URLSearchParams({ acao: acao, concurso_id: campo(form, sub, 'concurso_id') || q.get('concurso_id') || '', cargo_id: campo(form, sub, 'cargo_id'), curso_id: campo(form, sub, 'curso_id') || q.get('curso_id') || '', item_id: campo(form, sub, 'item_id') });
    var nCampo = form.querySelector('[name="n"]'); if (nCampo) p.set('n', nCampo.value);
    var links = form.querySelector('[name="links"]');
    if (links) p.set('n', links.value.split(/\n/).filter(function (l) { return l.trim(); }).length);
    function enviar() {
      liberado.add(form);
      if (sub && sub.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; form.appendChild(h); }
      HTMLFormElement.prototype.submit.call(form);
    }
    fetch(base + '?' + p.toString(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (e) {
      if (!e.ia) { if (antigo && !confirm(antigo[2])) return; return enviar(); }
      if (e.vazio) { if (antigo && !confirm(antigo[2])) return; return enviar(); }
      var h = '<h3 style="margin:0 0 8px;font-size:17px">💰 Esta ação usa a IA</h3>' + (antigo ? '<p style="margin:0 0 8px">' + esc(antigo[2]) + '</p>' : '') +
        '<p style="margin:0 0 6px;font-size:16px">Custo estimado: <strong>' + brl(e.brl) + '</strong> <span style="color:#555">(faixa ' + brl(e.brl_min) + ' a ' + brl(e.brl_max) + ')</span></p>' +
        '<p style="margin:0 0 6px">≈ ' + Number(e.tokens).toLocaleString('pt-BR') + ' tokens · ' + esc(e.chamadas.join(' · ')) + '</p>' +
        '<details style="margin:6px 0;color:#444"><summary style="cursor:pointer">Como calculei</summary><ul style="margin:6px 0;padding-left:18px">' + e.linhas.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' +
        '<p style="margin:4px 0;font-size:12px">O gasto real (tokens que o Google cobra) é medido e mostrado quando terminar, em Consumo da IA.</p></details>' +
        '<p style="margin:6px 0 0;font-size:13px;color:#555">Gasto do mês até agora: ' + brl(e.mes_brl) + (e.limite > 0 ? ' de ' + brl(e.limite) : '') + '</p>' +
        (e.estoura ? '<p style="margin:8px 0 0;color:#b42318;font-weight:700">⚠️ Pode passar do limite do mês. Ao chegar no limite, a IA pausa sozinha.</p>' : '');
      dialogo(h, 'Confirmar e executar', enviar);
    }).catch(function () { if (!antigo || confirm(antigo[2])) enviar(); });
  }, true);
})();
