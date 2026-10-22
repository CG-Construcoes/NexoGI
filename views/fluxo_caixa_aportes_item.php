<?php // Item 2 — APORTES A RETORNAR: uma linha por grupo com aportes
      // cadastrados (msoft.GrupoCentro_Aporte), valor = soma do grupo
      // gravada no snapshot msoft.FluxoCaixa_Aportes pelo Atualizar.
      // Espera $fcApo (fcs_aportes_salvo). ?>
<?php if (($fcApo['ultima'] ?? null) !== null): ?>
<p class="fcs-ult">Último cálculo: <?= e(data_br((string) $fcApo['ultima'], true)) ?>
    — clique em Atualizar para recalcular.</p>
<?php endif; ?>

<?php if (!($fcApo['linhas'] ?? [])): ?>
<p class="vazio">Aportes ainda não calculados — cadastre em
    <a href="<?= url('fluxo-caixa-aportes') ?>">Aportes a Retornar</a>
    e clique em Atualizar.</p>
<?php else: ?>
<table class="tabela fcs-pagar" style="max-width:720px">
    <thead>
        <tr>
            <th>Grupo</th>
            <th class="col-valor">Total de aportes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($fcApo['linhas'] as $l): ?>
        <tr>
            <td>&nbsp;&nbsp;<?= e($l['nome']) ?></td>
            <td class="col-valor"><?= number_format($l['total'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="fcs-tot">
            <td>TOTAL APORTES A RETORNAR</td>
            <td class="col-valor"><?= number_format($fcApo['total'], 2, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
