<?php // Item 4 — CONTAS A RECEBER: blocos por situacao (Notas Fiscais
      // Faturadas / Previsão de Medição), dentro mes (recolhivel) e grupo.
      // Linhas = medicoes previstas (msoft.PrevisaoMedicao). Espera $fcRec
      // (fcs_receber_dados). Reusa os estilos/script .fcs-pagar do item 5. ?>
<?php $fcTotalRec = 0.0; $fcTemRec = false; ?>
<table class="tabela fcs-pagar">
    <thead>
        <tr>
            <th>Obra / Medição</th>
            <th>Situação</th>
            <th>Emissão NF</th>
            <th>Prev. recebimento</th>
            <th class="col-valor">Valor líquido</th>
        </tr>
    </thead>
    <?php
    // reagrupa as linhas: situacao => mes => linhas (meses na ordem do quadro)
    $sits = ['Notas Fiscais Faturadas', 'Previsão de Medição'];
    $porSit = [];
    foreach ($fcRec['meses'] as $ym) {
        foreach ($fcRec['linhas'][$ym] ?? [] as $l) {
            $porSit[$l['sit']][$ym][] = $l;
        }
    }
    foreach ($sits as $sit):
        $blocos = $porSit[$sit] ?? [];
        if (!$blocos) {
            continue; // situacao sem medicoes nao aparece
        }
        $fcTemRec = true;
        $sitTotal = 0.0; ?>
    <tbody>
        <tr style="font-weight:700; background:#1e3a5f; color:#fff">
            <td colspan="5"><?= e($sit) ?></td></tr>
        <?php foreach ($fcRec['meses'] as $mi => $ym):
            $lins = $blocos[$ym] ?? [];
            if (!$lins) {
                continue; // mes sem medicoes nesta situacao
            }
            $sub = 0.0;
            foreach ($lins as $l) {
                $sub += $l['valor'];
            }
            $sitTotal += $sub;
            $fcTotalRec += $sub;
            $rot = fcs_mes_rotulo($ym);
            $tag = fcs_mes_tag((int) $mi); ?>
        <tr class="fcs-mes" data-mtog><td colspan="5">&nbsp;&nbsp;<?= e($rot) ?><?php
            if ($tag !== ''): ?> <span class="fcs-mes-tag">(<?= e($tag) ?>)</span><?php
            endif; ?></td></tr>
        <?php $gAtual = null; $gSub = 0.0;
        foreach ($lins as $l):
            if ($gAtual !== null && $l['gnome'] !== $gAtual): ?>
        <tr class="fcs-mrow fcs-subg" hidden>
            <td colspan="4">&nbsp;&nbsp;&nbsp;&nbsp;Subtotal <?= e($gAtual) ?></td>
            <td class="col-valor"><?= number_format($gSub, 2, ',', '.') ?></td>
        </tr>
            <?php $gSub = 0.0;
            endif;
            $gAtual = $l['gnome'];
            $gSub += $l['valor']; ?>
        <tr class="fcs-mrow" hidden>
            <td>&nbsp;&nbsp;<?= e($l['obra']) ?><?= $l['desc'] !== '' ? ' — ' . e($l['desc']) : '' ?></td>
            <td><?= e($l['sit']) ?></td>
            <td><?= e($l['emissao'] !== null ? data_br($l['emissao']) : '—') ?></td>
            <td><?= e(data_br($l['prev'])) ?></td>
            <td class="col-valor"><?= number_format($l['valor'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach;
        if ($gAtual !== null): ?>
        <tr class="fcs-mrow fcs-subg" hidden>
            <td colspan="4">&nbsp;&nbsp;&nbsp;&nbsp;Subtotal <?= e($gAtual) ?></td>
            <td class="col-valor"><?= number_format($gSub, 2, ',', '.') ?></td>
        </tr>
        <?php endif; ?>
        <tr class="fcs-sub">
            <td colspan="4">Subtotal <?= e($rot) ?></td>
            <td class="col-valor"><?= number_format($sub, 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="font-weight:600; background:#d7e6f5; border-top:1px solid #b9c8d8">
            <td colspan="4">Subtotal <?= e($sit) ?></td>
            <td class="col-valor"><?= number_format($sitTotal, 2, ',', '.') ?></td>
        </tr>
    </tbody>
    <?php endforeach; ?>
    <?php if ($fcTemRec): ?>
    <tfoot>
        <tr class="fcs-tot">
            <td colspan="4">TOTAL CONTAS A RECEBER</td>
            <td class="col-valor"><?= number_format($fcTotalRec, 2, ',', '.') ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>
<?php if (!$fcTemRec): ?>
<p class="vazio">Nenhuma medição prevista no trimestre — cadastre em
    Engenharia &gt; Previsões &gt; Medições.</p>
<?php endif; ?>
