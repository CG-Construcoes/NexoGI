<?php // Previsao de Medicoes — Engenharia > Previsoes > Medicoes ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<?php
$incluidos = array_column($centros, 'Centro_CodC');
$disp = array_filter($centrosDisp,
    fn($rotulo, $codc) => !in_array($codc, $incluidos, true), ARRAY_FILTER_USE_BOTH);
?>

<?php if ($podeInserir): ?>
<!-- ===================== INCLUIR CENTRO ===================== -->
<h2>Incluir centro de custo na previsao</h2>
<form method="post" action="<?= url('medicoes') ?>" class="filtros" style="margin-bottom:24px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="incluir_centro">
    <div class="campo grow">
        <label for="med-centro">Centro de custo</label>
        <input type="text" id="med-centro" name="centro" list="med-centros" required
               placeholder="Digite para pesquisar" autocomplete="off">
        <datalist id="med-centros">
            <?php foreach ($disp as $rotulo): ?>
            <option value="<?= e($rotulo) ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn">Incluir</button>
    </div>
</form>
<?php endif; ?>

<!-- ===================== CENTROS NA PREVISAO ===================== -->
<h2>Centros de custo na previsao</h2>
<?php if ($centros): ?>
<table class="tabela" style="max-width:900px">
    <thead>
        <tr><th>Centro de custo</th><th style="width:80px">Medicoes</th>
            <th style="width:160px">Valor liquido</th><th style="width:90px"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($centros as $c):
            $sel = $centroAberto && (int) $c['PMedC_Id'] === (int) $centroAberto['PMedC_Id']; ?>
        <tr class="fc-grupo-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= (int) $c['PMedC_Id'] ?>">
            <td><?= e($c['Rotulo']) ?></td>
            <td><?= (int) $c['QtdeMedicoes'] ?></td>
            <td class="col-valor"><?= number_format((float) $c['TotalLiquido'], 2, ',', '.') ?></td>
            <td class="acoes">
                <?php if ($podeExcluir): ?>
                <form method="post" action="<?= url('medicoes') ?>" style="display:inline"
                      onsubmit="return confirm('Remover o centro? As medicoes dele tambem serao excluidas.')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_centro">
                    <input type="hidden" name="id" value="<?= (int) $c['PMedC_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum centro de custo na previsao.</p>
<?php endif; ?>

<?php if ($centroAberto):
    $total = array_sum(array_map(fn($m) => (float) $m['Med_Valor'], $medicoes)); ?>
<!-- ===================== MEDICOES DO CENTRO ===================== -->
<h2 style="margin-top:28px">Medicoes do centro: <?= e($centroAberto['Rotulo']) ?></h2>

<?php if ($podeInserir): ?>
<form method="post" action="<?= url('medicoes') ?>" class="filtros" style="margin-bottom:16px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="incluir_medicao">
    <input type="hidden" name="id" value="<?= (int) $centroAberto['PMedC_Id'] ?>">
    <div class="campo grow">
        <label for="med-desc">Descricao da medicao</label>
        <input type="text" id="med-desc" name="descricao" required maxlength="120">
    </div>
    <div class="campo">
        <label for="med-valor">Valor liquido</label>
        <input type="text" id="med-valor" name="valor" required placeholder="0,00"
               size="14" style="text-align:right">
    </div>
    <div class="campo">
        <label for="med-emissao">Emissao da NF <small class="fc-av-hint">(opcional)</small></label>
        <input type="date" id="med-emissao" name="emissao">
    </div>
    <div class="campo">
        <label for="med-prev">Previsao de recebimento</label>
        <input type="date" id="med-prev" name="previsao" required>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn">Incluir medicao</button>
        <a href="<?= url('medicoes') ?>" class="btn btn-secundario">Voltar</a>
    </div>
</form>
<?php endif; ?>

<?php if ($medicoes): ?>
<table class="tabela" style="max-width:900px">
    <thead>
        <tr><th>Descricao</th><th style="width:140px">Valor liquido</th>
            <th style="width:110px">Emissao NF</th><th style="width:130px">Prev. recebimento</th>
            <th style="width:160px">Situacao</th><th style="width:90px"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($medicoes as $m):
            $faturada = $m['Med_EmissaoNF'] !== null
                && (string) $m['Med_EmissaoNF'] <= date('Y-m-d'); ?>
        <tr>
            <td><?= e($m['Med_Descricao']) ?></td>
            <td class="col-valor"><?= number_format((float) $m['Med_Valor'], 2, ',', '.') ?></td>
            <td><?= e(data_br($m['Med_EmissaoNF'])) ?: '&mdash;' ?></td>
            <td><?= e(data_br($m['Med_Previsao'])) ?></td>
            <td><span style="display:inline-block; padding:3px 10px; border-radius:12px;
                    font-size:12px; font-weight:600; white-space:nowrap; <?= $faturada
                        ? 'background:#d4edda; color:#1e7e34; border:1px solid #b7dfc2'
                        : 'background:#fff3cd; color:#8a6d00; border:1px solid #f0dd9a' ?>">
                <?= $faturada ? 'Nota Fiscal Faturada' : 'Previsão de Medição' ?></span></td>
            <td class="acoes">
                <?php if ($podeExcluir): ?>
                <form method="post" action="<?= url('medicoes') ?>" style="display:inline"
                      onsubmit="return confirm('Excluir esta medicao?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_medicao">
                    <input type="hidden" name="id" value="<?= (int) $centroAberto['PMedC_Id'] ?>">
                    <input type="hidden" name="medicao_id" value="<?= (int) $m['PMed_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td><strong>Total</strong></td>
            <td class="col-valor"><strong><?= number_format($total, 2, ',', '.') ?></strong></td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>
<?php else: ?>
<p class="vazio">Nenhuma medicao cadastrada neste centro.</p>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.fc-grupo-row').forEach(function (tr) {
        tr.addEventListener('click', function (ev) {
            if (ev.target.closest('form,button,a')) { return; }
            location.href = '<?= e(url('medicoes')) ?>?centro=' + tr.dataset.id;
        });
    });
})();
</script>
