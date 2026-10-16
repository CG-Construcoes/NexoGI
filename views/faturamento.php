<?php // Relatorio de Faturamento — Financeiro > Prestacao de Servico ?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro fat-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">Relatório de Faturamento</div>

    <div class="rel-econ-linha">
        <label for="base">Base</label>
        <select id="base" name="base" onchange="this.form.submit()">
            <option value="MGA" <?= $base === 'MGA' ? 'selected' : '' ?>>MGA</option>
            <option value="CONV" <?= $base === 'CONV' ? 'selected' : '' ?>>CONV</option>
        </select>
    </div>

    <div class="fat-duas">
        <fieldset class="rel-econ-box">
            <legend>Período</legend>
            <input type="date" name="data_ini" required>
            <span>a</span>
            <input type="date" name="data_fim" required>
        </fieldset>
        <fieldset class="rel-econ-box">
            <legend>Ordenar por</legend>
            <label><input type="radio" name="ordem" value="data" checked> Data</label>
            <label><input type="radio" name="ordem" value="numero"> Número</label>
        </fieldset>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Opções</legend>
        <div class="fat-linha">
            <label for="empresa">Empresa</label>
            <select id="empresa" name="empresa">
                <?php foreach ($empresas as $emp): ?>
                <option value="<?= e($emp['Empresa_Cod']) ?>"
                    <?= $o['empresa'] === $emp['Empresa_Cod'] ? 'selected' : '' ?>>
                    <?= e(trim((string) $emp['Empresa_Nome'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fat-linha">
            <label for="centro">Centro</label>
            <input type="text" id="centro" name="centro" list="listaCentrosFat"
                   autocomplete="off" value="<?= e($o['centro_rot']) ?>">
            <datalist id="listaCentrosFat">
                <?php foreach ($centros as $rotulo): ?>
                <option value="<?= e($rotulo) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="fat-linha">
            <label for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" list="listaClientesFat"
                   autocomplete="off" value="<?= e($o['cliente_rot']) ?>">
            <datalist id="listaClientesFat">
                <?php foreach ($clientes as $rotulo): ?>
                <option value="<?= e($rotulo) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="fat-linha">
            <label for="nucleo">Núcleo</label>
            <span class="fat-nucleo">
                <select id="nucleo" name="nucleo">
                    <option value="">Todos</option>
                    <?php foreach ($nucleos as $n): ?>
                    <option value="<?= e($n['Nucleo_Cod']) ?>"
                        <?= $o['nucleo'] === $n['Nucleo_Cod'] ? 'selected' : '' ?>>
                        <?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="fat-btn"><input type="radio" name="reidi" value="S"
                    <?= $o['reidi'] === 'S' ? 'checked' : '' ?>> REIDI</label>
                <label class="fat-btn"><input type="radio" name="reidi" value=""
                    <?= $o['reidi'] === '' ? 'checked' : '' ?>> TODOS</label>
            </span>
        </div>
        <div class="fat-linha">
            <label for="empr">Empreendimento</label>
            <input type="text" id="empr" name="empreendimento" list="listaEmprFat"
                   autocomplete="off" value="<?= e($o['empr_rot']) ?>">
            <datalist id="listaEmprFat">
                <?php foreach ($empreendimentos as $e2): ?>
                <option value="<?= e($e2['rotulo']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="fat-linha">
            <label for="esp">Espécie</label>
            <select id="esp" name="esp">
                <option value="">Todas</option>
                <?php foreach ($especies as $e1): ?>
                <option value="<?= e($e1) ?>" <?= $o['esp'] === $e1 ? 'selected' : '' ?>>
                    <?= e($e1) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fat-linha">
            <label for="tipo">Tipo Documento</label>
            <select id="tipo" name="tipo">
                <option value="">TODOS</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= e($t) ?>" <?= $o['tipo'] === $t ? 'selected' : '' ?>>
                    <?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>

    <fieldset class="rel-econ-box">
        <legend>Inclui</legend>
        <label><input type="checkbox" name="obs" value="1"
            <?= !empty($o['obs']) ? 'checked' : '' ?>> Observações</label>
    </fieldset>

    <div class="rel-econ-botoes">
        <label style="align-self:center"><input type="checkbox" name="imprimir" value="1"
            <?= !empty($o['imprimir']) ? 'checked' : '' ?>> Imprimir</label>
        <button type="submit" name="gerar" value="1" class="btn">Ok</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
</form>

<?php else: ?>
<!-- ===================== RESULTADO ===================== -->
<div class="rel-econ-botoes" style="margin-bottom:12px">
    <a href="<?= url('faturamento') ?>" class="btn btn-secundario">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php elseif (!$grupos): ?>
<p class="vazio">Nenhuma fatura encontrada no período/filtros informados.</p>
<?php else:
    $fmt = fn($v) => number_format($v, 2, ',', '.');
    $soma = function ($linhas) {
        $t = array_fill_keys(RF_COLS, 0.0);
        foreach ($linhas as $r) {
            foreach (RF_COLS as $c) {
                $t[$c] += $c === 'Vr_Liquido' ? rf_liquido($r) : (float) $r[$c];
            }
        }
        return $t;
    };
    $emitirTotal = function (string $rot, array $t, bool $forte) use ($fmt) { ?>
        <tr class="cp-total<?= $forte ? ' cp-total-grupo' : '' ?>">
            <td colspan="3"><?= e($rot) ?></td>
            <?php foreach (RF_COLS as $c): ?>
            <td class="col-valor"><?= $fmt($t[$c]) ?></td>
            <?php endforeach; ?>
        </tr>
    <?php };
    $totalGeral = array_fill_keys(RF_COLS, 0.0);
?>
<div class="cp-head">
    <div class="cp-empresa"><?= e($cab['nome']) ?><?= $cab['cnpj'] !== ''
        ? ' CNPJ: ' . e($cab['cnpj']) : '' ?></div>
    <div class="cp-impresso">Folha No 1 &nbsp;&nbsp;&nbsp;
        Impresso em: <?= date('d/m/Y H:i:s') ?></div>
</div>
<div class="cp-titulo">Relatório de Faturamento no Período de
    <?= e(data_br($o['ini'])) ?> a <?= e(data_br($o['fim'])) ?></div>

<table class="tabela cp-tab fat-tab">
    <thead>
        <tr>
            <th>Data Fatura</th><th>Tipo</th><th>Nº Fatura</th>
            <th class="col-valor">Valor Fatura</th>
            <th class="col-valor">Valor Reajuste</th>
            <th class="col-valor">INSS</th><th class="col-valor">ISS</th>
            <th class="col-valor">IRPJ</th><th class="col-valor">PIS</th>
            <th class="col-valor">COFINS</th><th class="col-valor">CSLL</th>
            <th class="col-valor">Retenção</th>
            <th class="col-valor">aClassificar</th>
            <th class="col-valor">Desc. Adiant.</th>
            <th class="col-valor">Vr. Liquido</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($grupos as $g):
        $totGrupo = array_fill_keys(RF_COLS, 0.0); ?>
        <tr class="cp-grupo fat-grupo-reidi"><td colspan="15"><?= e($g['rotulo']) ?></td></tr>
        <?php foreach ($g['centros'] as $cen):
            $linhas = $cen['linhas'];
            $totCen = $soma($linhas);
            foreach (RF_COLS as $c) { $totGrupo[$c] += $totCen[$c]; } ?>
        <tr class="fat-grupo-centro">
            <td colspan="9"><strong><?= e($cen['codc'] . ' - ' . $cen['centro']) ?></strong></td>
            <td colspan="6"><strong>Cliente: <?= e($cen['cliente']) ?></strong></td>
        </tr>
            <?php foreach ($linhas as $r): ?>
        <tr>
            <td><?= e(data_br(substr((string) $r['Fat_Data'], 0, 10))) ?></td>
            <td><?= e($r['Fat_Tipo']) ?></td>
            <td class="col-valor"><?= e($r['Fat_CentroNumero']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorFatura']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorReajuste']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorINSS']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorISS']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorIRPJ']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorPIS']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorCOFINS']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorCSSL']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_Retencao']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_ValorRp']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['Fat_DescAdi']) ?></td>
            <td class="col-valor"><?= $fmt(rf_liquido($r)) ?></td>
        </tr>
            <?php if (!empty($o['obs']) && trim((string) $r['Fat_Obs']) !== ''): ?>
        <tr class="cp-obs"><td></td>
            <td colspan="14"><em><?= e(trim((string) $r['Fat_Obs'])) ?></em></td></tr>
            <?php endif; ?>
            <?php endforeach;
            $emitirTotal('Total ' . $cen['codc'], $totCen, false);
        endforeach;
        $emitirTotal('Total ' . $g['rotulo'], $totGrupo, true);
        foreach (RF_COLS as $c) { $totalGeral[$c] += $totGrupo[$c]; }
    endforeach;
    $emitirTotal('Total Período', $totalGeral, true); ?>
    </tbody>
</table>
<?php endif; ?>
<?php if (!empty($o['imprimir'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
<?php endif; ?>
</div>
