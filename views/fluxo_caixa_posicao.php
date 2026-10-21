<?php // Item 1 — POSICAO FINANCEIRA: linhas (Saldo bancario / Aplicacoes /
      // Posicao total) x colunas de data (hoje + fechamento de cada mes do
      // trimestre). Projecao gravada no snapshot msoft.FluxoCaixa_Posicao
      // (mesN = saldo anterior - pagar(N) + receber(N)); aplicacoes
      // constantes, como no modelo. Espera $fcPos (fcs_posicao_salva|null). ?>
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
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Saldo bancário</td>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['saldo'], 2, ',', '.') : '—' ?></td>
            <?php foreach ([0, 1, 2] as $i): ?>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['mes'][$i], 2, ',', '.') : '—' ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td>Aplicações financeiras</td>
            <?php for ($i = 0; $i < 4; $i++): ?>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php endfor; ?>
        </tr>
    </tbody>
    <tfoot>
        <tr class="fcs-tot">
            <td>Posição total</td>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['saldo'] + $fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php foreach ([0, 1, 2] as $i): ?>
            <td class="col-valor"><?= $fcPos
                ? number_format($fcPos['mes'][$i] + $fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>
<?php if (!$fcPos): ?>
<p class="vazio">Posição financeira ainda não calculada — clique em Atualizar.</p>
<?php endif; ?>
