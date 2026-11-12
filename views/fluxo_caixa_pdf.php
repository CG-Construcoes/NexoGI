<?php // Acompanhamento Financeiro — versao PDF (dompdf). HTML autonomo,
      // tabelas expandidas, mesmos dados da tela resumo. Variaveis:
      // $fcPos, $posDet, $fcPag, $fcRec, $fcApo, $grupos, $basesSel,
      // $tempoCalc, $geradoEm ?>
<?php
$num = fn($v) => number_format((float) $v, 2, ',', '.');
$hoje = date('Y-m-d');
$mesesPos = fcs_pagar_meses();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 18px 0 6px; background: #1f4e79; color: #fff;
         padding: 4px 8px; }
    h3 { font-size: 11px; margin: 12px 0 4px; }
    .sub { color: #555; font-size: 9px; margin-bottom: 10px; }
    .treino { color: #b00; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 6px; }
    th, td { border: 1px solid #b9c8d8; padding: 2px 5px; }
    th { background: #dde7f0; font-size: 9px; text-align: left; }
    .v { text-align: right; }
    .tot td { font-weight: bold; background: #d7e6f5; }
    .grp td { background: #e8eef5; font-weight: bold; }
    .mes td { background: #eef3f8; font-weight: bold; }
    .sit td { background: #cfe0f0; font-weight: bold; }
    .fml { margin: 6px 0 10px; font-size: 9.5px; }
    .vazio { color: #777; font-style: italic; }
</style>
</head>
<body>

<h1>Acompanhamento Financeiro<?= modo_teste()
    ? ' <span class="treino">— AMBIENTE TREINO (bases dbTREINO)</span>' : '' ?></h1>
<p class="sub">Gerado em <?= e(data_br($geradoEm ?? date('Y-m-d H:i:s'), true)) ?>
    · Bases: <?= e(implode(' + ', $basesSel ?? [])) ?>
    <?php if ($fcPos): ?>· Último cálculo da posição: <?= e(data_br((string) $fcPos['ultima'], true)) ?><?php endif; ?></p>

<h2>1. POSIÇÃO FINANCEIRA</h2>
<?php if ($fcPos): ?>
<table>
    <tr>
        <th></th>
        <th class="v"><?= e(data_br($hoje)) ?></th>
        <?php foreach ($mesesPos as $ym): ?>
        <th class="v"><?= e(data_br(date('Y-m-d', strtotime("$ym-01 +1 month -1 day")))) ?></th>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td>Saldo bancário</td>
        <td class="v"><?= $num($fcPos['saldo']) ?></td>
        <?php foreach ([0, 1, 2] as $i): ?>
        <td class="v"><?= $num($fcPos['mes'][$i]) ?></td>
        <?php endforeach; ?>
    </tr>
    <tr>
        <td>Aplicações financeiras</td>
        <?php for ($i = 0; $i < 4; $i++): ?>
        <td class="v"><?= $num($fcPos['aplic']) ?></td>
        <?php endfor; ?>
    </tr>
    <tr class="tot">
        <td>Posição total</td>
        <td class="v"><?= $num($fcPos['saldo'] + $fcPos['aplic']) ?></td>
        <?php foreach ([0, 1, 2] as $i): ?>
        <td class="v"><?= $num($fcPos['mes'][$i] + $fcPos['aplic']) ?></td>
        <?php endforeach; ?>
    </tr>
</table>
<?php foreach ($mesesPos as $mi => $ym):
    $ant2 = $mi === 0 ? $fcPos['saldo'] : $fcPos['mes'][$mi - 1];
    $totRec = array_sum(array_column($fcRec['linhas'][$ym] ?? [], 'valor'));
    $totPag = 0.0;
    foreach ($fcPag['linhas'] as $l) {
        $m = $l['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
        $totPag += $m['c'] + $m['p'];
    } ?>
<p class="fml"><?= e(fcs_mes_rotulo($ym)) ?>: <?= $num($ant2) ?>
    + <?= $num($totRec) ?> (a receber) − <?= $num($totPag) ?> (a pagar)
    = <b><?= $num($fcPos['mes'][$mi]) ?></b></p>
<?php endforeach; ?>

<h3>Contas bancárias que compõem o saldo de hoje (Posição de Contas — Bancos)</h3>
<?php if (!$posDet): ?>
<p class="vazio">Detalhe por conta não disponível — clique em Atualizar para recalcular.</p>
<?php else: ?>
<table>
    <tr>
        <th>Empresa</th><th>Agente</th><th>Agência</th><th>Conta</th>
        <th class="v">Saldo Atual</th><th class="v">Valor Aplicado</th>
        <th>Últ. Conciliação</th>
    </tr>
    <?php $tS = 0.0; $tA = 0.0; $baseAnt = null; $sS = 0.0; $sA = 0.0;
    foreach ($posDet as $r):
        if ($baseAnt !== null && $r['Base'] !== $baseAnt): ?>
    <tr class="tot"><td colspan="4">Total <?= e($baseAnt) ?></td>
        <td class="v"><?= $num($sS) ?></td><td class="v"><?= $num($sA) ?></td><td></td></tr>
    <?php $sS = 0.0; $sA = 0.0; endif;
        if ($r['Base'] !== $baseAnt): ?>
    <tr class="grp"><td colspan="7"><?= e($r['Base']) ?></td></tr>
    <?php $baseAnt = $r['Base']; endif;
        $sS += $r['SaldoAtual']; $sA += $r['ValorAplicado'];
        $tS += $r['SaldoAtual']; $tA += $r['ValorAplicado']; ?>
    <tr>
        <td><?= e(trim($r['Empresa_Cod'] . ' ' . (string) $r['Empresa_Nome'])) ?></td>
        <td><?= e(trim($r['Agente_Cod'] . ' ' . (string) $r['Agente_Nome'])) ?></td>
        <td><?= e($r['Agencia']) ?></td>
        <td><?= e($r['Conta']) ?></td>
        <td class="v"><?= $num($r['SaldoAtual']) ?></td>
        <td class="v"><?= $num($r['ValorAplicado']) ?></td>
        <td><?= $r['UltimaConc'] ? e(data_br(substr((string) $r['UltimaConc'], 0, 10))) : '—' ?></td>
    </tr>
    <?php endforeach;
    if ($baseAnt !== null): ?>
    <tr class="tot"><td colspan="4">Total <?= e($baseAnt) ?></td>
        <td class="v"><?= $num($sS) ?></td><td class="v"><?= $num($sA) ?></td><td></td></tr>
    <?php endif; ?>
    <tr class="tot"><td colspan="4">Total Geral</td>
        <td class="v"><?= $num($tS) ?></td><td class="v"><?= $num($tA) ?></td><td></td></tr>
</table>
<?php endif; ?>
<?php else: ?>
<p class="vazio">Posição financeira ainda não calculada.</p>
<?php endif; ?>

<h2>2. APORTES A RETORNAR</h2>
<?php if (!($fcApo['linhas'] ?? [])): ?>
<p class="vazio">Aportes ainda não calculados.</p>
<?php else: ?>
<table>
    <tr><th>Grupo</th><th class="v">Total de aportes</th></tr>
    <?php foreach ($fcApo['linhas'] as $l): ?>
    <tr><td><?= e($l['nome']) ?></td><td class="v"><?= $num($l['total']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="tot"><td>TOTAL APORTES A RETORNAR</td>
        <td class="v"><?= $num($fcApo['total']) ?></td></tr>
</table>
<?php endif; ?>

<h2>3. SALDO FINANCEIRO POR OBRA/CENTRO</h2>
<?php if ($grupos): ?>
<table>
    <tr>
        <th>Grupo</th><th>Centros</th><th class="v">Saldo Atual</th>
        <?php foreach ($mesesPos as $ym): ?>
        <th class="v"><?= e(data_br(date('Y-m-d', strtotime("$ym-01 +1 month -1 day")))) ?></th>
        <?php endforeach; ?>
        <th>Última Atualização</th>
    </tr>
    <?php $totG = ['SaldoGrupo' => 0.0, 'SaldoMes1' => 0.0, 'SaldoMes2' => 0.0, 'SaldoMes3' => 0.0];
    $totC = 0; $temG = false;
    foreach ($grupos as $g):
        $totC += (int) $g['QtdeCentros'];
        foreach (array_keys($totG) as $cT) {
            if ($g[$cT] !== null) { $totG[$cT] += (float) $g[$cT]; $temG = true; }
        } ?>
    <tr>
        <td><?= e($g['FCGrupo_Nome']) ?></td>
        <td><?= (int) $g['QtdeCentros'] ?></td>
        <td class="v"><?= $g['SaldoGrupo'] !== null ? $num($g['SaldoGrupo']) : '—' ?></td>
        <?php foreach (['SaldoMes1', 'SaldoMes2', 'SaldoMes3'] as $cm): ?>
        <td class="v"><?= $g[$cm] !== null ? $num($g[$cm]) : '—' ?></td>
        <?php endforeach; ?>
        <td><?= $g['UltimaAtualizacao'] ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tot">
        <td>TOTAL GERAL</td><td><?= $totC ?></td>
        <?php foreach ($totG as $vT): ?>
        <td class="v"><?= $temG ? $num($vT) : '—' ?></td>
        <?php endforeach; ?>
        <td></td>
    </tr>
</table>
<?php else: ?>
<p class="vazio">Nenhum grupo cadastrado.</p>
<?php endif; ?>

<h2>4. CONTAS A RECEBER</h2>
<?php
$fcTotalRec = 0.0; $fcTemRec = false;
$sits = ['Notas Fiscais Faturadas', 'Previsão de Medição'];
$porSit = [];
foreach ($fcRec['meses'] ?? [] as $ym) {
    foreach ($fcRec['linhas'][$ym] ?? [] as $l) {
        $porSit[$l['sit']][$ym][] = $l;
    }
}
?>
<?php foreach ($sits as $si => $sit):
    $blocos = $porSit[$sit] ?? [];
    if (!$blocos) { continue; }
    $fcTemRec = true; $sitTotal = 0.0; ?>
<h3>4.<?= $si + 1 ?>. <?= e($sit) ?></h3>
<table>
    <tr><th>Obra / Medição</th><th>Emissão NF</th><th>Prev. recebimento</th>
        <th class="v">Valor líquido</th></tr>
    <?php foreach ($fcRec['meses'] as $ym):
        $lins = $blocos[$ym] ?? [];
        if (!$lins) { continue; }
        $sub = array_sum(array_column($lins, 'valor'));
        $sitTotal += $sub; $fcTotalRec += $sub; ?>
    <tr class="mes"><td colspan="4"><?= e(fcs_mes_rotulo($ym)) ?></td></tr>
    <?php foreach ($lins as $l): ?>
    <tr>
        <td><?= e($l['obra']) ?><?= $l['desc'] !== '' ? ' — ' . e($l['desc']) : '' ?></td>
        <td><?= e($l['emissao'] !== null ? data_br($l['emissao']) : '—') ?></td>
        <td><?= e(data_br($l['prev'])) ?></td>
        <td class="v"><?= $num($l['valor']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tot"><td colspan="3">Subtotal <?= e(fcs_mes_rotulo($ym)) ?></td>
        <td class="v"><?= $num($sub) ?></td></tr>
    <?php endforeach; ?>
    <tr class="tot"><td colspan="3">Subtotal <?= e($sit) ?></td>
        <td class="v"><?= $num($sitTotal) ?></td></tr>
</table>
<?php endforeach; ?>
<?php if ($fcTemRec): ?>
<table><tr class="tot"><td>TOTAL CONTAS A RECEBER</td>
    <td class="v" style="width:110px"><?= $num($fcTotalRec) ?></td></tr></table>
<?php else: ?>
<p class="vazio">Nenhuma medição prevista no trimestre.</p>
<?php endif; ?>

<h2>5. CONTAS A PAGAR</h2>
<?php if (($fcPag['ultima'] ?? null) !== null): ?>
<p class="sub">Último cálculo: <?= e(data_br((string) $fcPag['ultima'], true)) ?></p>
<?php endif; ?>
<?php if (!($fcPag['linhas'] ?? [])): ?>
<p class="vazio">Contas a pagar ainda não calculado.</p>
<?php else: ?>
<table>
    <tr><th>Grupo</th><th class="v">Comprometido</th><th class="v">Previsto</th>
        <th class="v">Total</th><th class="v">Acumulado</th></tr>
    <?php $acLinha = array_fill(0, count($fcPag['linhas']), 0.0);
    $acSub = ['c' => 0.0, 'p' => 0.0];
    foreach ($fcPag['meses'] as $ym):
        $sub = ['c' => 0.0, 'p' => 0.0];
        foreach ($fcPag['linhas'] as $li) {
            $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
            $sub['c'] += $m['c']; $sub['p'] += $m['p'];
        }
        $acSub['c'] += $sub['c']; $acSub['p'] += $sub['p']; ?>
    <tr class="mes"><td colspan="5"><?= e(fcs_mes_rotulo($ym)) ?></td></tr>
    <?php foreach ($fcPag['linhas'] as $i => $li):
        $m = $li['mes'][$ym] ?? ['c' => 0.0, 'p' => 0.0];
        $tot = $m['c'] + $m['p']; $acLinha[$i] += $tot; ?>
    <tr>
        <td><?= e($li['nome']) ?></td>
        <td class="v"><?= $num($m['c']) ?></td>
        <td class="v"><?= $num($m['p']) ?></td>
        <td class="v"><?= $num($tot) ?></td>
        <td class="v"><?= $num($acLinha[$i]) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="tot"><td>Subtotal <?= e(fcs_mes_rotulo($ym)) ?></td>
        <td class="v"><?= $num($sub['c']) ?></td><td class="v"><?= $num($sub['p']) ?></td>
        <td class="v"><?= $num($sub['c'] + $sub['p']) ?></td>
        <td class="v"><?= $num($acSub['c'] + $acSub['p']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="tot"><td>TOTAL CONTAS A PAGAR (acumulado)</td>
        <td class="v"><?= $num($acSub['c']) ?></td><td class="v"><?= $num($acSub['p']) ?></td>
        <td class="v"><?= $num($acSub['c'] + $acSub['p']) ?></td>
        <td class="v"><?= $num($acSub['c'] + $acSub['p']) ?></td></tr>
</table>
<?php endif; ?>

<h2>6. COMPOSIÇÃO DO RESULTADO POR NÚCLEO</h2>
<p class="vazio">Em construção — os dados deste grupo serão definidos.</p>

<h2>7. CONSÓRCIOS</h2>
<p class="vazio">Em construção — os dados deste grupo serão definidos.</p>

<?php if ($tempoCalc !== null): ?>
<p class="sub" style="text-align:right">Tempo total de cálculo do relatório:
    <b><?= $tempoCalc >= 60 ? floor($tempoCalc / 60) . ' min ' . ($tempoCalc % 60) . ' s'
        : $tempoCalc . ' s' ?></b></p>
<?php endif; ?>

</body>
</html>
