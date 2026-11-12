<?php // Acompanhamento Financeiro — resumo dos 7 grupos (Financeiro > Diretoria) ?>
<?php require ROOT . '/views/fluxo_caixa_marca.php'; ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<p style="margin:0 0 14px" class="fcs-acoes">
    <a href="<?= e(url('fluxo-caixa')) ?>">&laquo; Voltar</a>
    <button type="button" class="btn" style="margin-left:16px"
            onclick="window.print()">Imprimir</button>
    <a class="btn" style="margin-left:8px"
       href="<?= e(url('fluxo-caixa/pdf')) ?>">Gerar PDF</a>
</p>

<div class="fcs-secao">
    <div class="fcs-secao-tit">1. POSIÇÃO FINANCEIRA</div>
    <div class="fcs-secao-corpo">
        <?php require ROOT . '/views/fluxo_caixa_posicao.php'; ?>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">2. APORTES A RETORNAR</div>
    <div class="fcs-secao-corpo">
        <?php require ROOT . '/views/fluxo_caixa_aportes_item.php'; ?>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">3. SALDO FINANCEIRO POR OBRA/CENTRO</div>
    <div class="fcs-secao-corpo">
        <?php if ($grupos): ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Centros</th>
                    <th class="col-valor" title="Saldo do grupo calculado pelo Relatório Financeiro (Receitas + Despesas) no período informado">Saldo Atual</th>
                    <?php $mesesRes = fcs_pagar_meses();
                    foreach ($mesesRes as $miRes => $ymRes):
                        $mmRes = fcs_mes_rotulo($ymRes);
                        $fimRes = data_br(date('Y-m-d', strtotime("$ymRes-01 +1 month -1 day")));
                        $antRes = $miRes === 0 ? 'Saldo Atual'
                            : data_br(date('Y-m-d', strtotime($mesesRes[$miRes - 1] . '-01 +1 month -1 day'))); ?>
                    <th class="col-valor" title="<?= e("$antRes − Contas a Pagar ($mmRes) + Contas a Receber ($mmRes)") ?>"><?= e($fimRes) ?></th>
                    <?php endforeach; ?>
                    <th>Última Atualização</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $g):
                    $att = in_array((int) $g['FCGrupo_Id'], $atualizados, true); ?>
                <tr<?= $att ? ' class="fcs-att"' : '' ?>>
                    <td><?= e($g['FCGrupo_Nome']) ?><?= $att
                        ? ' <span class="fcs-att-tag">recalculado</span>' : '' ?></td>
                    <td><?= (int) $g['QtdeCentros'] ?></td>
                    <td class="col-valor"><?= $g['SaldoGrupo'] !== null
                        ? number_format((float) $g['SaldoGrupo'], 2, ',', '.') : '&mdash;' ?></td>
                    <?php foreach (['SaldoMes1', 'SaldoMes2', 'SaldoMes3'] as $colMesRes): ?>
                    <td class="col-valor"><?= $g[$colMesRes] !== null
                        ? number_format((float) $g[$colMesRes], 2, ',', '.') : '&mdash;' ?></td>
                    <?php endforeach; ?>
                    <td><?= $g['UltimaAtualizacao']
                        ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '&mdash;' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php $totFc = ['SaldoGrupo' => null, 'SaldoMes1' => null, 'SaldoMes2' => null, 'SaldoMes3' => null];
            $totCentros = 0;
            foreach ($grupos as $g) {
                $totCentros += (int) $g['QtdeCentros'];
                foreach (array_keys($totFc) as $cTot) {
                    if ($g[$cTot] !== null) {
                        $totFc[$cTot] = ($totFc[$cTot] ?? 0) + (float) $g[$cTot];
                    }
                }
            } ?>
            <tfoot>
            <tr style="font-weight:700; background:#d7e6f5; border-top:2px solid #1974b8">
                <td>TOTAL GERAL</td>
                <td><?= $totCentros ?></td>
                <?php foreach ($totFc as $vTot): ?>
                <td class="col-valor"><?= $vTot !== null
                    ? number_format($vTot, 2, ',', '.') : '&mdash;' ?></td>
                <?php endforeach; ?>
                <td></td>
            </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <p class="vazio">Nenhum grupo cadastrado. Cadastre em
            <a href="<?= url('fluxo-caixa-grupos') ?>">Grupos de Centros</a>.</p>
        <?php endif; ?>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">4. CONTAS A RECEBER</div>
    <div class="fcs-secao-corpo">
        <?php require ROOT . '/views/fluxo_caixa_receber.php'; ?>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">5. CONTAS A PAGAR</div>
    <div class="fcs-secao-corpo">
        <?php require ROOT . '/views/fluxo_caixa_pagar.php'; ?>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">6. COMPOSIÇÃO DO RESULTADO POR NÚCLEO</div>
    <div class="fcs-secao-corpo">
        <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">7. CONSÓRCIOS</div>
    <div class="fcs-secao-corpo">
        <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
    </div>
</div>

<?php if ($tempoCalc !== null): ?>
<p style="margin:18px 4px 0; text-align:right; color:#555; font-size:13px">
    Tempo total de cálculo do relatório: <strong><?= $tempoCalc >= 60
        ? floor($tempoCalc / 60) . ' min ' . ($tempoCalc % 60) . ' s'
        : $tempoCalc . ' s' ?></strong>
</p>
<?php endif; ?>

