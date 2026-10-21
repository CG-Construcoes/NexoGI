<?php // Item 5 — CONTAS A PAGAR: blocos por mes, linhas = grupos do item 3.
      // Espera $fcPag = fcs_pagar_dados(...). ?>
<?php if ($fcPag['erros'] ?? []): ?>
<div class="alert-erro" style="margin-bottom:10px">
    Falha ao consultar contas a pagar: <?= e(implode(' | ', $fcPag['erros'])) ?>
</div>
<?php endif; ?>

<?php if ($fcPag['linhas'] ?? []): ?>
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
    foreach ($fcPag['meses'] as $ym):
        $sub = ['c' => 0.0, 'p' => 0.0];
        foreach ($fcPag['linhas'] as $li) {
            $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
            $sub['c'] += $m['c'];
            $sub['p'] += $m['p'];
        }
        $acSub['c'] += $sub['c'];
        $acSub['p'] += $sub['p'];
        $rot = fcs_mes_rotulo($ym); ?>
    <tbody>
        <tr class="fcs-mes"><td colspan="5">&nbsp;&nbsp;<?= e($rot) ?></td></tr>
        <?php foreach ($fcPag['linhas'] as $i => $li):
            $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
            $tot = $m['c'] + $m['p'];
            $acLinha[$i] += $tot; ?>
        <tr>
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
<?php else: ?>
<p class="vazio">Nenhum grupo cadastrado. Cadastre em
    <a href="<?= url('fluxo-caixa-grupos') ?>">Grupos de Centros</a>.</p>
<?php endif; ?>
