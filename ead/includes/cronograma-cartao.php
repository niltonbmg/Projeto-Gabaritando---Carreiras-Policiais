<?php
// includes/cronograma-cartao.php
// Bloco do cronograma (agenda e "Metas de hoje" da página inicial) e a janela que abre ao clicar nele.
// Uma fonte só: os dados vêm de meta_cronograma_montar().
declare(strict_types=1);

if (!function_exists('mc_e')) {
    function mc_e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function mc_min(int $m): string
    {
        $h = intdiv($m, 60);
        $r = $m % 60;
        return $h > 0 ? ($h . 'h' . ($r > 0 ? sprintf('%02d', $r) : '')) : ($r . "'");
    }

    /** Cor fixa por matéria, sempre com contraste para texto branco. */
    function mc_cor_materia(int $materiaId): string
    {
        $paleta = ['#1f6feb', '#16a34a', '#b45309', '#9333ea', '#dc2626', '#0e7490', '#be185d', '#4d7c0f', '#7c3aed', '#c2410c', '#0f766e', '#475569'];
        return $paleta[$materiaId > 0 ? $materiaId % count($paleta) : count($paleta) - 1];
    }

    function mc_link_aula(array $item): string
    {
        return !empty($item['aula_id']) ? 'aulas.php?curso_id=' . (int)$item['aula_curso_id'] . '&modulo_id=' . (int)$item['aula_modulo_id'] . '&aula_id=' . (int)$item['aula_id'] : '';
    }

    /** O bloco ficou feito? (teoria marcada, questões no mínimo, revisão feita) */
    function mc_bloco_feito(array $t, array $revFeitas): bool
    {
        $item = $t['item'];
        if ($t['tipo'] === 'teoria') {
            return !empty($item['teoria_concluida_em']);
        }
        if ($t['tipo'] === 'questoes') {
            return (int)($item['nivel_info']['resolvidas'] ?? 0) >= (int)($item['min_item'] ?? 10);
        }
        if ($t['tipo'] === 'revisao') {
            return isset($revFeitas[(int)$item['id'] . ':' . (int)$t['rodada']]);
        }
        return false;
    }

    /**
     * Bloco curto: matéria (cor), assunto, tempo e caixinha de concluído. Clicar abre a janela do assunto.
     * $ctx: cursoId, hoje, data, volta (página para voltar), revFeitas, minQ, n (id único do bloco).
     */
    function mc_cartao(array $t, array $ctx): string
    {
        $item = $t['item'];
        $tipo = (string)$t['tipo'];
        $cor = in_array($tipo, ['edital', 'simulado'], true) ? '#1f2937' : ($tipo === 'redacao' ? '#7c2d12' : ($tipo === 'discursiva' ? '#334155' : mc_cor_materia((int)($item['materia_id'] ?? 0))));
        $titulo = (string)$item['titulo'] . (!empty($t['parte']) ? ' ' . (int)$t['parte'] : '');
        if ($tipo === 'revisao') {
            $titulo = (string)$item['titulo'] . ' — REVISÃO E QUESTÕES';
        }
        $rotulo = $tipo === 'questoes' ? ' · questões' : '';
        $feito = mc_bloco_feito($t, $ctx['revFeitas']);
        $id = (int)$item['id'];
        $podeMarcar = $ctx['data'] <= $ctx['hoje'];
        $check = '';
        if ($tipo === 'teoria' || $tipo === 'revisao') {
            $check = '<form method="post" action="meu-cronograma.php"><input type="hidden" name="acao" value="' . ($tipo === 'teoria' ? 'feito_teoria' : 'feito_revisao') . '">'
                . '<input type="hidden" name="curso_id" value="' . (int)$ctx['cursoId'] . '"><input type="hidden" name="item_id" value="' . $id . '"><input type="hidden" name="rodada" value="' . (int)($t['rodada'] ?? 0) . '"><input type="hidden" name="volta" value="' . mc_e($ctx['volta']) . '">'
                . '<button class="mc-check' . ($feito ? ' ok' : '') . '" type="submit" ' . ($feito || !$podeMarcar ? 'disabled' : '') . ' aria-label="Concluído: ' . mc_e($titulo) . '">' . ($feito ? '✓' : '') . '</button></form>';
        } elseif ($tipo === 'questoes') {
            $check = '<span class="mc-check' . ($feito ? ' ok' : '') . '" title="Marca sozinho ao chegar no mínimo de questões">' . ($feito ? '✓' : '') . '</span>';
        }
        $dlg = 'mcj' . (int)$ctx['n'];
        return '<article class="mc-card mc-' . mc_e($tipo) . ($feito ? ' feito' : '') . '" style="--cor:' . $cor . '">'
            . '<button type="button" class="mc-card-abrir" data-dlg="' . $dlg . '">'
            . '<span class="mc-card-topo"><strong class="mc-materia">' . mc_e((string)($item['materia_nome'] ?? '')) . '</strong><span class="mc-tempo">' . mc_min((int)$t['minutos']) . '</span></span>'
            . '<span class="mc-assunto">' . mc_e($titulo . $rotulo) . '</span></button>'
            . $check . '</article>' . mc_janela($t, $ctx, $dlg, $titulo, $feito);
    }

    /** Janela do bloco: 4 passos do método, progresso do assunto e atalhos. */
    function mc_janela(array $t, array $ctx, string $dlg, string $titulo, bool $feito): string
    {
        $item = $t['item'];
        $tipo = (string)$t['tipo'];
        $min = mc_min((int)$t['minutos']);
        $h = '<dialog class="mc-janela" id="' . $dlg . '"><form method="dialog"><button class="mc-fechar" aria-label="Fechar">✕</button></form>'
            . '<small class="mc-j-mat" style="color:' . mc_cor_materia((int)($item['materia_id'] ?? 0)) . '">' . mc_e((string)($item['materia_nome'] ?? '')) . '</small>'
            . '<h3>' . $min . ' de ' . mc_e($titulo) . '</h3>';
        $outros = ['edital' => ['meu-concurso.php', 'Abrir o edital'], 'simulado' => ['simulados.php', 'Fazer simulado'], 'redacao' => ['redacoes.php', 'Escrever redação'], 'discursiva' => ['discursiva.php', 'Treinar discursiva']];
        if (isset($outros[$tipo])) {
            return $h . '<p><a class="btn btn-ouro" href="' . $outros[$tipo][0] . '" data-timer="' . mc_e($titulo) . '">' . $outros[$tipo][1] . '</a></p></dialog>';
        }
        $aula = mc_link_aula($item);
        $tid = (int)($item['topico_id'] ?? 0);
        $minQ = (int)($item['min_item'] ?? $ctx['minQ']);
        $resolv = (int)($item['nivel_info']['resolvidas'] ?? 0);
        $revN = 0;
        foreach ([1, 2, 3] as $r) {
            $revN += isset($ctx['revFeitas'][(int)$item['id'] . ':' . $r]) ? 1 : 0;
        }
        $rodada = $tipo === 'revisao' ? (int)$t['rodada'] : min(3, $revN + 1);
        $passos = [
            ['Aula', !empty($item['teoria_concluida_em']), $aula],
            ['PDF', !empty($item['teoria_concluida_em']), $aula !== '' ? $aula . '#pdf' : ''],
            ['Questões ' . min($resolv, $minQ) . '/' . $minQ, $resolv >= $minQ, $tid ? 'banco-questoes.php?topico_id=' . $tid : ''],
            ['Revisão ' . $revN . '/3', $revN >= 3, $tid ? 'banco-questoes.php?topico_id=' . $tid . '&revisao=' . (int)$item['id'] . '-' . $rodada : ''],
        ];
        $h .= '<ol class="mc-passos">';
        foreach ($passos as $i => [$nome, $ok, $url]) {
            $h .= '<li class="' . ($ok ? 'ok' : '') . '">' . ($url !== '' ? '<a href="' . mc_e($url) . '" data-timer="' . mc_e($titulo) . '">' : '<span>') . '<b>' . ($ok ? '✓' : ($i + 1)) . '</b> ' . mc_e($nome) . ($url !== '' ? '</a>' : '</span>') . '</li>';
        }
        $h .= '</ol>';
        $nivel = (int)($item['nivel_info']['nivel'] ?? 0);
        $h .= '<p class="mc-j-prog">Progresso do assunto: nível ' . $nivel . ' de 4 · ' . $resolv . ' questões resolvidas' . ($feito ? ' · <strong>bloco concluído ✓</strong>' : '') . '</p>';
        $h .= '<div class="mc-j-bts">'
            . ($aula !== '' ? '<a class="btn btn-linha" href="' . mc_e($aula) . '" data-timer="' . mc_e($titulo) . '">▶ Vídeo</a><a class="btn btn-linha" href="' . mc_e($aula) . '#pdf">📄 Material</a>' : '')
            . ($tid ? '<a class="btn btn-linha" href="banco-questoes.php?topico_id=' . $tid . '" data-timer="' . mc_e($titulo) . '">✅ Caderno de questões</a><a class="btn btn-linha" href="flashcards.php?topico=' . $tid . '">🃏 Flashcards</a>' : '')
            . '</div>';
        $base = '<input type="hidden" name="curso_id" value="' . (int)$ctx['cursoId'] . '"><input type="hidden" name="topico_id" value="' . $tid . '"><input type="hidden" name="volta" value="' . mc_e($ctx['volta']) . '">';
        $h .= '<div class="mc-j-bts">'
            . '<form method="post" action="meu-cronograma.php"><input type="hidden" name="acao" value="mais_tempo">' . $base . '<button class="btn btn-linha" type="submit">⏱ Preciso de mais tempo</button></form>'
            . '<form method="post" action="meu-cronograma.php" onsubmit="return confirm(\'Marcar este assunto como feito? Ele sai do seu cronograma.\')"><input type="hidden" name="acao" value="assunto_feito">' . $base . '<button class="btn btn-linha" type="submit">✔ Marcar assunto como feito</button></form>'
            . '</div>';
        $h .= '<details class="mc-como"><summary>Como estudar?</summary><ol><li>Assista à aula (pode acelerar).</li><li>Leia o PDF e grife o que cai.</li><li>Resolva as questões até o mínimo e leia os comentários dos erros.</li><li>Nas revisões, refaça as questões que errou.</li></ol></details>';
        return $h . '</dialog>';
    }

    /** Liga os blocos às janelas e ao cronômetro (uma vez por página). */
    function mc_script_janelas(): string
    {
        return '<script>document.addEventListener("click",function(e){var b=e.target.closest(".mc-card-abrir");if(b){var d=document.getElementById(b.getAttribute("data-dlg"));if(d&&d.showModal){d.showModal();}}});</script>';
    }

    /** Estilo do bloco e da janela (usado na agenda e na página inicial). */
    function mc_estilo_cartao(): string
    {
        return '<style>
.mc-card{background:var(--cor);color:#fff;border-radius:10px;display:flex;align-items:stretch;gap:6px;min-width:0;position:relative}
.mc-card.mc-revisao{background:#1e1e21;border:2px dashed var(--cor)}
.mc-card.feito{opacity:.6}
.mc-card-abrir{flex:1;min-width:0;overflow:hidden;background:none;border:0;color:inherit;text-align:left;padding:8px 0 8px 10px;cursor:pointer;font:inherit;display:grid;gap:2px}
.mc-card-topo{display:flex;justify-content:space-between;gap:6px;font-size:11px;min-width:0}.mc-card-topo>*{min-width:0}
.mc-materia{text-transform:uppercase;letter-spacing:.03em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mc-tempo{font-weight:700;font-variant-numeric:tabular-nums;flex:none}.mc-card-topo>.mc-tempo{min-width:auto}
.mc-assunto{font-size:13px;font-weight:500;overflow-wrap:anywhere}
.mc-card form{display:flex;align-items:center;padding-right:8px}
.mc-card>span.mc-check{align-self:center;margin-right:8px}
.mc-check{width:24px;height:24px;border-radius:6px;border:2px solid #fff;background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:grid;place-items:center;font-weight:700;font-size:14px;flex:none}
.mc-check.ok{background:#16a34a;border-color:#16a34a}
.mc-check:disabled{cursor:default}
.mc-janela{margin:auto;max-width:520px;width:calc(100% - 32px);border:1px solid #2a2a2e;border-radius:16px;background:#161618;color:#f3f3f4;padding:20px;font-family:inherit}
.mc-janela::backdrop{background:rgba(0,0,0,.6)}
.mc-janela h3{font-size:18px;margin:4px 0 12px}
.mc-j-mat{font-size:12px;text-transform:uppercase;letter-spacing:.06em;font-weight:700}
.mc-fechar{position:absolute;right:12px;top:10px;background:none;border:0;color:#a3a3ab;font-size:18px;cursor:pointer}
.mc-passos{list-style:none;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin:0 0 10px;padding:0}
.mc-passos a,.mc-passos span{display:grid;gap:4px;justify-items:center;text-align:center;padding:8px 4px;border:1px solid #2a2a2e;border-radius:10px;color:#f3f3f4;text-decoration:none;font-size:12px}
.mc-passos b{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:#2a2a2e}
.mc-passos li.ok b{background:#16a34a}
.mc-j-prog{font-size:13px;color:#a3a3ab;margin:0 0 10px}
.mc-j-bts{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.mc-j-bts form{margin:0}
.mc-janela .btn{display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:8px 12px;font:inherit;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none}
.mc-janela .btn-linha{background:transparent;color:#f3f3f4;border:1px solid #2a2a2e}
.mc-janela .btn-ouro{background:#fbb928;color:#111;border:0}
.mc-como{font-size:13px;color:#a3a3ab}.mc-como summary{cursor:pointer}.mc-como ol{margin:6px 0 0 18px}
@media(max-width:560px){.mc-passos{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>';
    }
}
