<?php // Item 1 — POSICAO FINANCEIRA: linhas (Saldo bancario / Aplicacoes /
      // Posicao total) x colunas de data (hoje + fechamento de cada mes do
      // trimestre + Variacao). So a 1a coluna tem fonte definida hoje
      // (snapshot msoft.FluxoCaixa_Posicao via botao Atualizar).
      // Espera $fcPos (fcs_posicao_salva() ou null). ?>
<?php if ($fcPos): ?>
<p class="fcs-ult">Último cálculo: <?= e(data_br((string) $fcPos['ultima'], true)) ?>
    — clique em Atualizar para recalcular.</p>
<?php endif; ?>
<table class="tabela fcs-pagar">
    <thead>
        <tr>
            <th></th>
            <th class="col-valor"><?= e(data_br(date('Y-m-d'))) ?></th>
            <?php foreach (fcs_pagar_meses() as $ym): ?>
            <th class="col-valor"><?= e(data_br(date('Y-m-d',
                strtotime("$ym-01 +1 month -1 day")))) ?></th>
            <?php endforeach; ?>
            <th class="col-valor">Variação</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $colVazias = '<td class="col-valor">—</td><td class="col-valor">—</td>'
            . '<td class="col-valor">—</td><td class="col-valor">—</td>';
        $linhaPos = function ($rotulo, $valor) use ($colVazias) { ?>
        <tr>
            <td><?= e($rotulo) ?></td>
            <td class="col-valor"><?= number_format((float) $valor, 2, ',', '.') ?></td>
            <?= $colVazias ?>
        </tr>
        <?php }; ?>
        <?php if ($fcPos): ?>
            <?php $linhaPos('Saldo bancário', $fcPos['saldo']); ?>
            <?php $linhaPos('Aplicações financeiras', $fcPos['aplic']); ?>
        <?php else: ?>
            <tr><td>Saldo bancário</td><td class="col-valor">—</td><?= $colVazias ?></tr>
            <tr><td>Aplicações financeiras</td><td class="col-valor">—</td><?= $colVazias ?></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="fcs-tot">
            <td>Posição total</td>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['saldo'] + $fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?= $colVazias ?>
        </tr>
    </tfoot>
</table>
<?php if (!$fcPos): ?>
<p class="vazio">Posição financeira ainda não calculada — clique em Atualizar.</p>
<?php endif; ?>
