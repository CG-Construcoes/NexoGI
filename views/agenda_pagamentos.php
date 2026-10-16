<?php // Agenda de Pagamentos — Financeiro > Contas a Pagar
$hoje = date('Y-m-d');
$fimPadrao = date('Y-m-d', strtotime('+3 months'));
$ordens = [
    'vencimento'   => 'Vencimento',
    'agenda'       => 'Agenda',
    'credor_valor' => 'Credor/Valor',
    'credor_nota'  => 'Credor/Nota',
    'valor'        => 'Faixa de Valores',
    'centro'       => 'Centro',
    'usuario'      => 'Usuário',
    'numero'       => 'Nº',
];
?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando agenda...</div>
        <div class="rel-econ-loading-sub">A consulta pode levar alguns instantes</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">Agenda de Pagamentos</div>

    <div class="rel-econ-linha">
        <label for="base">Base</label>
        <select id="base" name="base" onchange="this.form.submit()">
            <option value="MGA" <?= $base === 'MGA' ? 'selected' : '' ?>>MGA</option>
            <option value="CONV" <?= $base === 'CONV' ? 'selected' : '' ?>>CONV</option>
        </select>
    </div>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Empresas</legend>
        <div class="rel-econ-centros-lista">
            <?php foreach ($empresas as $emp): ?>
            <label><input type="checkbox" name="emp[]" value="<?= e(trim($emp['Empresa_Cod'])) ?>"
                    checked> <?= e(trim($emp['Empresa_Cod']) . ' - ' . trim((string) $emp['Empresa_Nome'])) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="rel-econ-box">
        <legend>Período da Agenda Financeira</legend>
        <label>de</label>
        <input type="date" name="data_ini" value="<?= e($hoje) ?>" required>
        <label>a</label>
        <input type="date" name="data_fim" value="<?= e($fimPadrao) ?>" required>
        <span style="margin-left:14px"></span>
        <label><input type="checkbox" name="vencidas" value="1"> Incluir Vencidas</label>
        <label><input type="checkbox" name="pendentes" value="1"> Incluir Pendentes</label>
        <label><input type="checkbox" name="nao_aut" value="1"
            title="Marcado = mostra SOMENTE títulos não autorizados (exclui os com instrução AP)">
            Apenas não autorizadas</label>
    </fieldset>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Previsões</legend>
            <label><input type="checkbox" name="inc_ed" value="1" checked> ED</label>
            <label><input type="checkbox" name="inc_ep" value="1" checked> EP</label>
            <label><input type="checkbox" name="inc_cv" value="1"> CV</label>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Faixa de Valores</legend>
            <label>de</label>
            <input type="text" name="valor_min" size="12" placeholder="0,00">
            <label>a</label>
            <input type="text" name="valor_max" size="12" placeholder="0,00">
            <label><input type="checkbox" name="restringir" value="1"> Restringir</label>
        </fieldset>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box rel-econ-centros">
            <legend>Centro de Negócio</legend>
            <input type="text" name="centro" list="listaCentrosAp" autocomplete="off"
                   placeholder="todos — digite para filtrar">
            <datalist id="listaCentrosAp">
                <?php foreach ($centros as $rotulo): ?>
                <option value="<?= e($rotulo) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Núcleo</legend>
            <select name="nucleo">
                <option value="">— Todos —</option>
                <?php foreach ($nucleos as $n): ?>
                <option value="<?= e($n['Nucleo_Cod']) ?>">
                    <?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?></option>
                <?php endforeach; ?>
            </select>
            <label style="margin-top:6px">Espécie</label>
            <select name="especie">
                <option value="">— Todas —</option>
                <?php foreach ($especies as $sp): ?>
                <option value="<?= e($sp['EspTit_Cod']) ?>">
                    <?= e(trim($sp['EspTit_Cod']) . ' - ' . trim($sp['EspTit_Titulo'])) ?></option>
                <?php endforeach; ?>
            </select>
        </fieldset>
    </div>

    <div class="rel-econ-linha">
        <label for="credor">Credor</label>
        <input type="text" id="credor" name="credor" list="listaCredoresAp" autocomplete="off"
               placeholder="todos" style="min-width:420px">
        <datalist id="listaCredoresAp">
            <?php foreach ($credores as $rotulo): ?>
            <option value="<?= e($rotulo) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Nº Documento / Ficha</legend>
            <label>Nº Documento</label>
            <input type="text" name="docnum" size="18" autocomplete="off">
            <label>Nº Ficha</label>
            <input type="text" name="ficha" size="9" autocomplete="off">
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Agrupar por</legend>
            <label><input type="checkbox" name="agr_tipo" value="1"> Tipo de Pagamento</label>
            <label><input type="checkbox" name="agr_mod" value="1"> Modalidade</label>
            <legend style="margin-top:8px">Totaliza</legend>
            <label><input type="checkbox" name="tot_dia" value="1"> Dia</label>
            <label><input type="checkbox" name="tot_quinzena" value="1"> Quinzena</label>
            <label><input type="checkbox" name="tot_mes" value="1"> Mês</label>
        </fieldset>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Ordem</legend>
        <?php foreach ($ordens as $v => $rot): ?>
        <label><input type="radio" name="ordem" value="<?= $v ?>"
            <?= $v === 'vencimento' ? 'checked' : '' ?>> <?= e($rot) ?></label>
        <?php endforeach; ?>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
</form>

<?php else: ?>
<!-- ===================== RESULTADO ===================== -->
<div class="rel-econ-botoes" style="margin-bottom:12px">
    <a href="<?= url('agenda-pagamentos') ?>" class="btn btn-secundario">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php elseif (!$linhas): ?>
<p class="vazio">Nenhum título encontrado no período/filtros informados.</p>
<?php else:
    $fmt = fn($v) => number_format((float) $v, 2, ',', '.');
    $dbr = fn($d) => ($d = substr((string) $d, 0, 10)) !== '' && $d !== '1900-01-01'
        ? data_br($d) : '';
    $totCampo = fn($ls, $c) => array_sum(array_map(fn($r) => (float) $r[$c], $ls));
    // agrupamento opcional (Tipo de Pagamento / Modalidade)
    $grpKey = function ($r) use ($o) {
        $k = [];
        if (!empty($o['agr_tipo'])) { $k[] = trim((string) $r['TitaPag_PagDocTipo']); }
        if (!empty($o['agr_mod'])) { $k[] = trim((string) $r['pge_Modalidade']); }
        return implode('|', $k);
    };
    $grpRot = function ($r) use ($o) {
        $p = [];
        if (!empty($o['agr_tipo'])) {
            $p[] = 'Tipo de Pagamento: ' . trim((string) $r['TitaPag_PagDocTipo']);
        }
        if (!empty($o['agr_mod'])) {
            $p[] = 'Modalidade: ' . trim((string) $r['pge_Modalidade']);
        }
        return implode(' — ', $p);
    };
    $agrupando = !empty($o['agr_tipo']) || !empty($o['agr_mod']);
    // niveis de totalizacao (dia < quinzena < mes) sobre a data da agenda
    $niveis = array_values(array_filter([
        !empty($o['tot_dia']) ? 'dia' : null,
        !empty($o['tot_quinzena']) ? 'quinzena' : null,
        !empty($o['tot_mes']) ? 'mes' : null]));
    $ordNivel = ['dia' => 0, 'quinzena' => 1, 'mes' => 2];
    usort($niveis, fn($a, $b) => $ordNivel[$a] <=> $ordNivel[$b]);
?>
<div class="cp-head">
    <div class="cp-empresa">Agenda de Pagamentos — base <?= e($base) ?>
        (<?= count($linhas) ?> títulos)</div>
    <div class="cp-impresso">Impresso em: <?= date('d/m/Y H:i:s') ?></div>
</div>
<div class="cp-titulo">Período da Agenda de <?= e(data_br($o['ini'])) ?> a <?= e(data_br($o['fim'])) ?></div>

<table class="tabela cp-tab ap-tab">
    <thead>
        <tr>
            <th>Vencimento</th><th>Credor</th><th>Documento</th><th>Par</th>
            <th class="col-valor">Valor</th><th>Agenda</th><th>Esp</th><th>NE</th>
            <th>Pagto Tipo Doc</th><th>i</th><th>Nº Aut</th><th>Ad</th>
            <th>PréDatado</th><th>Observação</th><th>Observação do Documento</th>
            <th>Nº Ficha</th><th>Empresa</th><th>Nome da Empresa</th>
            <th>Cód Centro</th><th>Centro de Negócio</th><th>Natureza</th>
            <th>Atraso</th><th>VenctoOrig</th><th>#Neg</th><th>Filial</th><th>#</th>
            <th>EntTipo</th><th>Aut. Pagto</th><th>Data Aut.Pagto</th>
            <th>Aut. Pedido</th><th>Aut. Doc.</th><th>Nº Id. Boleto</th>
            <th>Conta Principal</th><th>Tipo Doc</th><th>Imóvel (Taxas Condominiais)</th>
            <th>TitaPag_CartaoCredito</th><th>TitaPag_Data_PK</th><th>Ent_Numero_PK</th>
            <th>Usuário Criação Doc.</th><th>Data Criação Doc.</th>
            <th>Usuário Ult. Alteração Doc.</th><th>Data Ult. Alteração Doc.</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $NCOLS = 42;
    $emitirTotal = function ($rot, array $ls, $forte) use ($fmt, $totCampo) { ?>
        <tr class="cp-total<?= $forte ? ' cp-total-grupo' : '' ?>">
            <td colspan="4"><?= e($rot) ?></td>
            <td class="col-valor"><?= $fmt($totCampo($ls, 'TitaPag_ValorLiquido')) ?></td>
            <td colspan="38"></td>
        </tr>
    <?php };
    $baldeAtual = array_fill_keys($niveis, null);
    $baldeLinhas = array_fill_keys($niveis, []);
    $baldeRot = [];
    $grpAtual = null;
    $grpLinhas = [];
    $flushBaldes = function () use (&$baldeLinhas, &$baldeRot, $niveis, $emitirTotal) {
        foreach ($niveis as $nv) {
            if ($baldeLinhas[$nv]) {
                $emitirTotal($baldeRot[$nv] ?? '', $baldeLinhas[$nv], false);
                $baldeLinhas[$nv] = [];
            }
        }
    };
    foreach ($linhas as $i => $r):
        if ($agrupando) {
            $gk = $grpKey($r);
            if ($grpAtual !== null && $gk !== $grpAtual) {
                $flushBaldes();
                $emitirTotal('Total', $grpLinhas, true);
                $grpLinhas = [];
                $baldeAtual = array_fill_keys($niveis, null);
            }
            if ($gk !== $grpAtual) {
                ?><tr class="cp-grupo"><td colspan="42"><?= e($grpRot($r)) ?></td></tr><?php
            }
            $grpAtual = $gk;
            $grpLinhas[] = $r;
        }
        $agenda = substr((string) $r['TitaPag_DataAgendaFin'], 0, 10);
        if ($agenda === '' || $agenda === '1900-01-01') {
            $agenda = substr((string) $r['TitaPag_DataVencimento'], 0, 10);
        }
        foreach ($niveis as $nv) {
            $bk = ap_balde($agenda, $nv);
            if ($baldeAtual[$nv] !== null && $bk !== $baldeAtual[$nv]) {
                $emitirTotal($baldeRot[$nv] ?? '', $baldeLinhas[$nv], false);
                $baldeLinhas[$nv] = [];
            }
            $baldeAtual[$nv] = $bk;
            $baldeRot[$nv] = ap_balde_rotulo($bk, $nv);
            $baldeLinhas[$nv][] = $r;
        }
        $vencFluxo = substr((string) $r['TitaPag_DataVencimentoFluxo'], 0, 10);
        $vencida = $r['TitaPag_Situacao'] === 'A' && $vencFluxo !== '' && $vencFluxo < $o['ref'];
        $instr = trim((string) $r['Instrucao_Cod']);
        $adi = trim((string) $r['Adi_EmAberto']) === 'S';
        $naut = (int) $r['CountAutorizacoes'] ?: (int) $r['Instrucao_Count'];
        $autPag = trim((string) ($r['Usuario_Instrucao'] ?: $r['D_Usuario_Instrucao']));
    ?>
        <tr class="ap-linha<?= $vencida ? ' ap-vencida' : '' ?>" data-i="<?= $i ?>"
            title="Clique para detalhes do título">
            <td><?= e($dbr($r['TitaPag_DataVencimento'])) ?></td>
            <td class="cp-credor"><?= e(trim((string) $r['Cre_RazSoc'])) ?></td>
            <td><?= e(trim((string) $r['Ent_Documento'])) ?></td>
            <td><?= e(ap_parcela($r)) ?></td>
            <td class="col-valor"><?= $fmt($r['TitaPag_ValorLiquido']) ?></td>
            <td><?= e($dbr($r['TitaPag_DataAgendaFin'])) ?></td>
            <td><?= e(trim((string) $r['EspTit_Cod'])) ?></td>
            <td><?= (int) $r['TitaPag_NumeroNegociacoes'] > 0
                ? (int) $r['TitaPag_NumeroNegociacoes'] : '' ?></td>
            <td><?= e(trim((string) $r['TitaPag_PagDocTipo'])) ?></td>
            <td><?= $naut > 0 ? $naut : '' ?></td>
            <td><?= $instr === 'AP' ? '<span class="ap-badge ap-badge-ap">AP</span>'
                : ($instr !== '' ? e($instr) : '') ?></td>
            <td><?= $adi ? '<span class="ap-badge ap-badge-ad">A</span>' : '' ?></td>
            <td><?= trim((string) $r['Pag_PreDatado']) === 'S' ? 'S' : '' ?></td>
            <td><?= e(trim((string) $r['TitaPag_Obs'])) ?></td>
            <td><?= e(trim((string) $r['Ent_Observacao'])) ?></td>
            <td><?= e(trim((string) $r['Ficha_Numero'])) ?></td>
            <td><?= e(trim((string) $r['Empresa_Cod'])) ?></td>
            <td><?= e(trim((string) $r['Empresa_Nome'])) ?></td>
            <td><?= e(trim((string) $r['Centro_CodC'])) ?></td>
            <td><?= e(trim((string) $r['Centro_Nome'])) ?></td>
            <td><?= e(trim((string) $r['Ent_Natureza'])) ?></td>
            <td><?= (int) $r['DiasemAtraso'] > 0 ? (int) $r['DiasemAtraso'] : '' ?></td>
            <td><?= e($dbr($r['TitaPag_DataVencimentoOrig'])) ?></td>
            <td><?= (int) $r['NumeroNegociacoes'] > 0
                ? (int) $r['NumeroNegociacoes'] : '' ?></td>
            <td><?= e(trim((string) $r['Filial_Cod'])) ?></td>
            <td><?= (int) $r['repete'] > 0 ? (int) $r['repete'] : '' ?></td>
            <td><?= e(trim((string) $r['Ent_Tipo'])) ?></td>
            <td><?= e($autPag) ?></td>
            <td><?= e($dbr($r['Instrucao_DataPagamento'] ?: $r['Instrucao_Data'])) ?></td>
            <td><?= e(trim((string) $r['Aut_Pedido'])) ?></td>
            <td><?= e(trim((string) $r['DocDig_Numero'])) ?></td>
            <td><?= e(trim((string) $r['DadosPg_NumeroIdentificacao'])) ?></td>
            <td><?= e(trim((string) $r['ContaPrincipal'])) ?></td>
            <td><?= e(trim((string) $r['EntDocTipo_Cod'])) ?></td>
            <td><?= e(trim((string) ($r['Imovel'] ?: $r['Imovel_TC']))) ?></td>
            <td><?= e(trim((string) $r['TitaPag_CartaoCredito'])) ?></td>
            <td><?= e($dbr($r['TitaPag_Data_PK'])) ?></td>
            <td><?= e(trim((string) $r['Ent_Numero_PK'])) ?></td>
            <td><?= e(trim((string) $r['Audit_Insert_UserName'])) ?></td>
            <td><?= e(substr((string) $r['Audit_Insert_Date'], 0, 10) !== ''
                ? substr((string) $r['Audit_Insert_Date'], 8, 2) . '/'
                    . substr((string) $r['Audit_Insert_Date'], 5, 2) . '/'
                    . substr((string) $r['Audit_Insert_Date'], 0, 4) : '') ?></td>
            <td><?= e(trim((string) $r['Audit_UserName'])) ?></td>
            <td><?= e(substr((string) $r['Audit_LastChange'], 0, 10) !== ''
                ? substr((string) $r['Audit_LastChange'], 8, 2) . '/'
                    . substr((string) $r['Audit_LastChange'], 5, 2) . '/'
                    . substr((string) $r['Audit_LastChange'], 0, 4) : '') ?></td>
        </tr>
        <tr class="ap-det" id="ap-det-<?= $i ?>" hidden>
            <td colspan="42">
                <strong>Título <?= (int) $r['TitaPag_Numero'] ?></strong>
                · Doc: <?= e(trim((string) $r['TitaPag_Documento'])) ?>
                · Situação: <?= e(trim((string) $r['TitaPag_Situacao'])) ?>
                · Núcleo: <?= e(trim((string) $r['Nucleo_Cod'])) ?>
                · Banco: <?= e(trim((string) $r['CodigoBacen'])) ?>
                    <?= e(trim((string) $r['AgenteFinanceiro_RazaoSocial'])) ?>
                · Principal: <?= $fmt($r['TitaPag_Valor']) ?>
                · Acrésc.: <?= $fmt($r['TitaPag_Acrescimos']) ?>
                · Encargos: <?= $fmt($r['TitaPag_EncargosFin']) ?>
                · Multa: <?= $fmt($r['TitaPag_Multa']) ?>
                · Descontos: <?= $fmt($r['TitaPag_Descontos']) ?>
                · Desc. adiant.: <?= $fmt($r['TitaPag_DescontoAdiantamento']) ?>
                · Total pago: <?= $fmt($r['TitaPag_TotalPago']) ?>
                <?php if (trim((string) $r['Cre_Banco']) !== ''): ?>
                · Banco credor: <?= e(trim((string) $r['Cre_Banco'])) ?>
                    Ag <?= e(trim((string) $r['Cre_Agencia'])) ?>
                    CC <?= e(trim((string) $r['Cre_ContaCorrente'])) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
    $flushBaldes();
    if ($grpAtual !== null) {
        $emitirTotal('Total', $grpLinhas, true);
    }
    ?>
        <tr class="cp-total cp-total-geral">
            <td colspan="4">Total Período:</td>
            <td class="col-valor"><?= $fmt($totCampo($linhas, 'TitaPag_ValorLiquido')) ?></td>
            <td colspan="38"></td>
        </tr>
    </tbody>
</table>

<div class="ap-status">
    <span>Pendentes: <b><?= $fmt($status['pend']) ?></b></span>
    <span>PreDatados: <b><?= $fmt($status['pred']) ?></b></span>
    <span>Autorizadas: <b class="ap-st-aut"><?= $fmt($status['aut']) ?></b></span>
    <span>Total (AP+PD+A): <b><?= $fmt($status['aut'] + $status['pred'] + $status['pend']) ?></b></span>
    <span class="ap-st-venc">Vencidas: <b><?= $fmt($status['venc']) ?></b></span>
    <span>EP: <b><?= $fmt($status['ep']) ?></b></span>
    <span>ED: <b><?= $fmt($status['ed']) ?></b></span>
    <span>Outros: <b><?= $fmt($status['outros']) ?></b></span>
    <span>Total Geral: <b><?= $fmt($status['total']) ?></b></span>
</div>

<script>
document.querySelectorAll('.ap-linha').forEach(function (tr) {
    tr.addEventListener('click', function () {
        var d = document.getElementById('ap-det-' + tr.dataset.i);
        if (d) { d.hidden = !d.hidden; }
    });
});
</script>
<?php endif; ?>
<?php endif; ?>
</div>
