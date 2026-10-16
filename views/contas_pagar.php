<?php // Relatorio de Contas a Pagar — Financeiro > Contas a Pagar ?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">A consulta pode levar alguns instantes</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">Relatório de Contas a Pagar</div>

    <div class="rel-econ-linha">
        <label for="base">Base</label>
        <select id="base" name="base" onchange="this.form.submit()">
            <option value="MGA" <?= $base === 'MGA' ? 'selected' : '' ?>>MGA</option>
            <option value="CONV" <?= $base === 'CONV' ? 'selected' : '' ?>>CONV</option>
        </select>
    </div>

    <div class="rel-econ-linha">
        <label for="empresa">Empresa</label>
        <select id="empresa" name="empresa">
            <option value="">— Todas —</option>
            <?php foreach ($empresas as $emp): ?>
            <option value="<?= e($emp['Empresa_Cod']) ?>">
                <?= e(trim($emp['Empresa_Cod']) . ' - ' . trim((string) $emp['Empresa_Nome'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Período</legend>
        <label>de</label>
        <input type="date" name="data_ini" required>
        <label>a</label>
        <input type="date" name="data_fim" required>
        <span style="margin-left:14px"></span>
        <label><input type="checkbox" name="ck_venc" value="1" checked> Vencimento</label>
        <label><input type="checkbox" name="ck_agenda" value="1" checked> Data Agenda</label>
        <label><input type="checkbox" name="ck_autor" value="1"> Data autorização</label>
    </fieldset>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Agrupar Por</legend>
            <?php foreach (CP_AGRUP as $v => $rot): ?>
            <label><input type="radio" name="agrupar" value="<?= $v ?>"
                <?= $v === 'agenda' ? 'checked' : '' ?>> <?= e($rot) ?></label>
            <?php endforeach; ?>
            <hr>
            <label><input type="checkbox" name="suprime" value="1">
                Suprime nome no agrupamento do total</label>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Totaliza</legend>
            <label><input type="checkbox" name="tot_dia" value="1"> Por Dia</label>
            <label><input type="checkbox" name="tot_quinzena" value="1"> Por Quinzena</label>
            <label><input type="checkbox" name="tot_mes" value="1"> Por Mês</label>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Inclui</legend>
            <label><input type="checkbox" name="atrasadas" value="1"> Atrasadas</label>
            <label><input type="checkbox" name="pendente" value="1"> Pendente</label>
            <label><input type="checkbox" name="inc_ep" value="1" checked> Previsões [ EP ]</label>
            <label style="margin-left:18px"><input type="radio" name="apenas_ep_ed" value=""
                    checked> todas</label>
            <label style="margin-left:18px"><input type="radio" name="apenas_ep_ed" value="EP">
                    Apenas EP</label>
            <label><input type="checkbox" name="inc_ed" value="1" checked> Previsões [ ED ]</label>
            <label style="margin-left:18px"><input type="radio" name="apenas_ep_ed" value="ED">
                    Apenas ED</label>
        </fieldset>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box rel-econ-centros">
            <legend>Centro de Negócio</legend>
            <label><input type="radio" name="centro_op" value="todos" checked> Todos</label>
            <label><input type="radio" name="centro_op" value="apenas" id="centroApenas"> Apenas/Seleção</label>
            <input type="text" name="centro" list="listaCentrosCp" autocomplete="off"
                   placeholder="selecione o centro"
                   onfocus="document.getElementById('centroApenas').checked=true">
            <datalist id="listaCentrosCp">
                <?php foreach ($centros as $rotulo): ?>
                <option value="<?= e($rotulo) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <label style="margin-top:6px">Classificação</label>
            <input type="text" name="classificacao" autocomplete="off"
                   placeholder="parte da natureza/classificação">
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Núcleo</legend>
            <label for="cp-gnuc">Grupo de Núcleo</label>
            <select id="cp-gnuc" name="grupo_nucleo">
                <option value="">— Todos —</option>
                <?php foreach ($gruposNucleo as $g): ?>
                <option value="<?= e($g['GrupoNucleo_Cod']) ?>">
                    <?= e(trim($g['GrupoNucleo_Cod']) . ' - ' . trim($g['GrupoNucleo_Titulo'])) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="cp-nuc" style="margin-top:6px">Núcleo</label>
            <select id="cp-nuc" name="nucleo">
                <option value="">— Todos —</option>
                <?php foreach ($nucleos as $n): ?>
                <option value="<?= e($n['Nucleo_Cod']) ?>">
                    <?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?></option>
                <?php endforeach; ?>
            </select>
        </fieldset>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box rel-econ-centros">
            <legend>Tipo de Documento</legend>
            <label><input type="radio" name="doc_op" value="todos" checked> Todos</label>
            <label><input type="radio" name="doc_op" value="apenas" id="docApenas"> Apenas</label>
            <label><input type="radio" name="doc_op" value="exceto" id="docExceto"> Exceto</label>
            <div class="rel-econ-centros-lista">
                <?php foreach ($doctipos as $t): ?>
                <label><input type="checkbox" name="doc_sel[]" value="<?= e($t['cod']) ?>"
                       onclick="var r=document.getElementById('docApenas');if(!r.checked&&!document.getElementById('docExceto').checked)r.checked=true">
                    <?= e($t['cod']) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="rel-econ-box rel-econ-centros">
            <legend>Espécie de Títulos</legend>
            <label><input type="radio" name="esp_op" value="todos" checked> Todos</label>
            <label><input type="radio" name="esp_op" value="apenas" id="espApenas"> Apenas</label>
            <label><input type="radio" name="esp_op" value="exceto" id="espExceto"> Exceto</label>
            <div class="rel-econ-centros-lista">
                <?php foreach ($especies as $sp): ?>
                <label><input type="checkbox" name="esp_sel[]" value="<?= e($sp['EspTit_Cod']) ?>"
                       onclick="var r=document.getElementById('espApenas');if(!r.checked&&!document.getElementById('espExceto').checked)r.checked=true">
                    <?= e($sp['EspTit_Cod'] . ' - ' . trim($sp['EspTit_Titulo'])) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    </div>

    <div class="rel-econ-linha">
        <label for="credor">Credor</label>
        <input type="text" id="credor" name="credor" list="listaCredoresCp" autocomplete="off"
               placeholder="todos" style="min-width:420px">
        <datalist id="listaCredoresCp">
            <?php foreach ($credores as $rotulo): ?>
            <option value="<?= e($rotulo) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Opções</legend>
        <label><input type="checkbox" name="excl_aclass" value="1"> Excluir aClassificar</label>
        <label><input type="checkbox" name="so_autor" value="1"> Apenas Autorizadas</label>
        <label><input type="checkbox" name="so_nautor" value="1"> Apenas Não Autorizadas</label>
        <label><input type="checkbox" name="exibe_obs" value="1"> Exibe Observação</label>
        <label title="ainda não implementado"><input type="checkbox" disabled> Inclui Corretagens</label>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
</form>

<?php else: ?>
<!-- ===================== RESULTADO ===================== -->
<div class="rel-econ-botoes" style="margin-bottom:12px">
    <a href="<?= url('contas-pagar') ?>" class="btn btn-secundario">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php elseif (!$grupos): ?>
<p class="vazio">Nenhum título encontrado no período/filtros informados.</p>
<?php else:
    $inclui = [];
    if (!empty($o['atrasadas'])) { $inclui[] = 'ATRASADAS'; }
    if (!empty($o['pendente'])) { $inclui[] = 'PENDENTES'; }
    if (!empty($o['inc_ep'])) { $inclui[] = 'INCLUI PREVISÕES (EP)'; }
    if (!empty($o['inc_ed'])) { $inclui[] = 'INCLUI PREVISÕES (ED)'; }
    if ($o['apenas_ep'] ?? false) { $inclui = ['APENAS PREVISÕES (EP)']; }
    if ($o['apenas_ed'] ?? false) { $inclui = ['APENAS PREVISÕES (ED)']; }
    $cols = ['TitaPag_ValorLiquido', 'TitaPag_Valor', 'TitaPag_Acrescimos',
             'TitaPag_EncargosFin', 'TitaPag_Multa', 'TitaPag_Descontos',
             'TitaPag_DescontoAdiantamento'];
    $soma = fn($linhas) => array_map(fn($c) => array_sum(array_map(
        fn($r) => (float) $r[$c], $linhas)), $cols);
    $fmt = fn($v) => number_format($v, 2, ',', '.');
    $niveis = array_values(array_filter([
        !empty($o['tot_dia']) ? 'dia' : null,
        !empty($o['tot_quinzena']) ? 'quinzena' : null,
        !empty($o['tot_mes']) ? 'mes' : null]));
    $ordNivel = ['dia' => 0, 'quinzena' => 1, 'mes' => 2];
    usort($niveis, fn($a, $b) => $ordNivel[$a] <=> $ordNivel[$b]); // dia < quinzena < mes
    $totalGeral = [];
?>
<div class="cp-head">
    <div class="cp-empresa"><?= e($o['empresa_nome'] ?: '(todas as empresas)') ?></div>
    <div class="cp-impresso">Impresso em: <?= date('d/m/Y H:i:s') ?></div>
</div>
<div class="cp-titulo">Relatório de Contas a Pagar por Ordem de <?= e(CP_AGRUP[$o['agrupar']]) ?>
    no Período de <?= e(data_br($o['ini'])) ?> a <?= e(data_br($o['fim'])) ?>
    <?= $inclui ? e(implode(' ', $inclui)) : '' ?></div>

<table class="tabela cp-tab">
    <thead>
        <tr>
            <th>DataAgend</th><th>Credor</th><th>Documento</th><th>Parcela</th>
            <th class="col-valor">Valor</th><th class="col-valor">Principal</th>
            <th class="col-valor">Acréscimos</th><th class="col-valor">Encargos</th>
            <th class="col-valor">Multas</th><th class="col-valor">Descontos</th>
            <th class="col-valor">D.Adiantam</th><th>Esp</th><th>Atraso</th>
            <th>Ficha</th><th>aC</th><th>Centro de Negócio</th><th>Natureza</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($grupos as $g):
        $linhas = $g['linhas'];
        $totalGeral[] = $linhas;
        // emissao das linhas do grupo com totalizacoes por balde
        $emitirTotal = function ($rot, array $ls, $forte) use ($soma, $fmt) { ?>
        <tr class="cp-total<?= $forte ? ' cp-total-grupo' : '' ?>">
            <td colspan="4"><?= e($rot) ?></td>
            <?php foreach ($soma($ls) as $v): ?>
            <td class="col-valor"><?= $fmt($v) ?></td>
            <?php endforeach; ?>
            <td colspan="6"></td>
        </tr>
        <?php };
        if ($g['rotulo'] !== ''): ?>
        <tr class="cp-grupo"><td colspan="17"><?= e($g['rotulo']) ?></td></tr>
        <?php endif;
        // controle de baldes (dia < quinzena < mes)
        $baldeAtual = array_fill_keys($niveis, null);
        $baldeLinhas = array_fill_keys($niveis, []);
        $baldeRot = [];
        foreach ($linhas as $r):
            foreach ($niveis as $nv) {
                [$bk, $brot] = cp_balde($r, $o['agrupar'], $nv);
                if ($baldeAtual[$nv] !== null && $bk !== $baldeAtual[$nv]) {
                    // fechou o balde deste nivel (e dos mais internos ja emitidos)
                    $emitirTotal($baldeRot[$nv] ?? '', $baldeLinhas[$nv], false);
                    $baldeLinhas[$nv] = [];
                }
                $baldeAtual[$nv] = $bk;
                $baldeRot[$nv] = $brot;
                $baldeLinhas[$nv][] = $r;
            }
            $atraso = (int) $r['DiasemAtraso']; ?>
        <tr>
            <td><?= e(data_br(cp_data_linha($r, $o['agrupar']))) ?></td>
            <td class="cp-credor"><?= e(trim($r['Cre_RazSoc'])) ?>
                <?= trim((string) $r['Cre_CGCCPF']) !== ''
                    ? '<span class="cp-cgc">' . e(trim($r['Cre_CGCCPF'])) . '</span>' : '' ?></td>
            <td><?= e(trim((string) $r['Ent_Documento'])) ?></td>
            <td><?= e(trim((string) $r['TitaPag_ParNumero'])
                . (trim((string) $r['TitaPag_SParNumero']) !== ''
                    ? '/' . trim((string) $r['TitaPag_SParNumero']) : '')
                . '/' . trim((string) $r['nParc'])) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_ValorLiquido']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_Valor']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_Acrescimos']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_EncargosFin']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_Multa']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_Descontos']) ?></td>
            <td class="col-valor"><?= $fmt((float) $r['TitaPag_DescontoAdiantamento']) ?></td>
            <td><?= e(trim((string) $r['EspTit_Cod'])) ?></td>
            <td><?= $atraso > 0 ? $atraso : '' ?></td>
            <td><?= (int) $r['Ficha_Numero'] > 0 ? (int) $r['Ficha_Numero'] : '' ?></td>
            <td><?= $r['Adi_EmAberto'] === 'S' ? 'S' : '' ?></td>
            <td><?= e(trim($r['Empresa_Cod'] . $r['Filial_Cod'] . $r['Centro_Cod'])
                . ' ' . trim((string) $r['Centro_Nome'])) ?></td>
            <td><?= e(trim((string) $r['Ent_Natureza'])) ?></td>
        </tr>
        <?php if (!empty($o['exibe_obs']) && trim((string) $r['TitaPag_ObsPagamento']) !== ''): ?>
        <tr class="cp-obs"><td></td>
            <td colspan="16"><em><?= e(trim((string) $r['TitaPag_ObsPagamento'])) ?></em></td></tr>
        <?php endif; ?>
        <?php endforeach;
        // fecha baldes abertos (do mais interno para o mais externo)
        foreach ($niveis as $nv) {
            if ($baldeLinhas[$nv]) {
                $emitirTotal($baldeRot[$nv] ?? '', $baldeLinhas[$nv], false);
            }
        }
        if ($g['rotulo'] !== '' || count($grupos) > 1) {
            $rot = !empty($o['suprime']) || $g['rotulo'] === ''
                ? 'Total' : 'Total ' . preg_replace('/^[^:]+:\s*/', '', $g['rotulo']);
            $emitirTotal($rot, $linhas, true);
        }
    endforeach; ?>
        <tr class="cp-total cp-total-geral">
            <td colspan="4">Total Período:</td>
            <?php
            $todas = array_merge(...$totalGeral);
            foreach ($soma($todas) as $v): ?>
            <td class="col-valor"><?= $fmt($v) ?></td>
            <?php endforeach; ?>
            <td colspan="6"></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>
<?php endif; ?>
</div>
