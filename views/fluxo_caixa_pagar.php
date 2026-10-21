<?php // Item 5 — CONTAS A PAGAR: blocos por mes (recolhiveis), linhas =
      // grupos do item 3. Espera $fcPag (fcs_pagar_dados/fcs_pagar_salvo). ?>
<?php if ($fcPag['erros'] ?? []): ?>
<div class="alert-erro" style="margin-bottom:10px">
    Falha ao consultar contas a pagar: <?= e(implode(' | ', $fcPag['erros'])) ?>
</div>
<?php endif; ?>

<?php if (($fcPag['ultima'] ?? null) !== null): ?>
<p class="fcs-ult">Último cálculo: <?= e(data_br((string) $fcPag['ultima'], true)) ?>
    — clique em Atualizar para recalcular.</p>
<?php endif; ?>

<?php if (!($fcPag['meses'] ?? [])): ?>
<p class="vazio">Contas a pagar ainda não calculado — clique em Atualizar.</p>
<?php elseif (!($fcPag['linhas'] ?? [])): ?>
<p class="vazio">Nenhum grupo com valores no trimestre.</p>
<?php else: ?>
<table class="tabela fcs-pagar">
    <thead>
        <tr>
            <th>Grupo</th>
            <th class="col-valor">Comprometido</th>
            <th class="col-valor">Previsto</th>
            <th class="col-valor">Total</th>
            <th class="col-valor">Acumulado</th>
        </tr>
    </thead>
    <?php
    $acLinha = array_fill(0, count($fcPag['linhas']), 0.0);
    $acSub = ['c' => 0.0, 'p' => 0.0];
    foreach ($fcPag['meses'] as $mi => $ym):
        $sub = ['c' => 0.0, 'p' => 0.0];
        foreach ($fcPag['linhas'] as $li) {
            $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
            $sub['c'] += $m['c'];
            $sub['p'] += $m['p'];
        }
        $acSub['c'] += $sub['c'];
        $acSub['p'] += $sub['p'];
        $rot = fcs_mes_rotulo($ym);
        $tag = fcs_mes_tag((int) $mi); ?>
    <tbody>
        <tr class="fcs-mes" data-mtog><td colspan="5">&nbsp;&nbsp;<?= e($rot) ?><?php
            if ($tag !== ''): ?> <span class="fcs-mes-tag">(<?= e($tag) ?>)</span><?php
            endif; ?></td></tr>
        <?php foreach ($fcPag['linhas'] as $i => $li):
            $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
            $tot = $m['c'] + $m['p'];
            $acLinha[$i] += $tot; ?>
        <tr class="fcs-mrow" hidden>
            <td>&nbsp;&nbsp;<?= e($li['nome']) ?></td>
            <td class="col-valor"><?= number_format($m['c'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($m['p'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($tot, 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($acLinha[$i], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="fcs-sub">
            <td>Subtotal <?= e($rot) ?></td>
            <td class="col-valor"><?= number_format($sub['c'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($sub['p'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($sub['c'] + $sub['p'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($acSub['c'] + $acSub['p'], 2, ',', '.') ?></td>
        </tr>
    </tbody>
    <?php endforeach; ?>
    <tfoot>
        <tr class="fcs-tot">
            <td>TOTAL CONTAS A PAGAR (acumulado)</td>
            <td class="col-valor"><?= number_format($acSub['c'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($acSub['p'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($acSub['c'] + $acSub['p'], 2, ',', '.') ?></td>
            <td class="col-valor"><?= number_format($acSub['c'] + $acSub['p'], 2, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<script>
(function () {
    // Meses do item 5: recolhido = so o mes e o subtotal; clique expande.
    document.querySelectorAll('.fcs-pagar tr.fcs-mes[data-mtog]').forEach(function (tr) {
        tr.addEventListener('click', function () {
            var aberto = tr.classList.toggle('fcs-mab');
            var n = tr.nextElementSibling;
            while (n && n.classList.contains('fcs-mrow')) {
                n.hidden = !aberto;
                n = n.nextElementSibling;
            }
        });
    });
})();
</script>
