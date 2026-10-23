<?php
// Bloco do relatorio DRE (tabela + totais) — usado pela tela (dre.php)
// e pela exportacao para Excel (dre_excel.php).
$MESES ??= ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
$fmtV ??= fn(float $v): string => number_format($v, 2, ',', '.');
?>
<?php if ($dados):
    $cols = $modo === 'ano'
        ? array_map('strval', range($o['ano_ini'], $o['ano_fim']))
        : array_map(fn($m) => sprintf('%02d', $m), $o['meses']);
    $rot = [];
    foreach ($cols as $c) {
        $rot[$c] = $modo === 'ano' ? $c : $MESES[(int) $c - 1] . '/' . $o['ano'];
    }
    $zero = array_fill_keys($cols, 0.0);
    // acumulado por nivel: linhas SubTotal=1 mostram a soma de tudo ate a linha
    $cum = $zero;
    $rl  = $zero; // Receita Liquida — denominador do % DRE
    $saida = [];
    foreach ($formato as $f) {
        $cf = (int) $f['Cod_Formato'];
        $own = $zero;
        foreach ($cols as $c) {
            $own[$c] = $dados['porCf'][$cf][$c] ?? 0.0;
            $cum[$c] += $own[$c];
        }
        $vals = $f['SubTotal'] ? $cum : $own;
        if ($cf === 1) {
            $rl = $vals; // % DRE = % da Receita Operacional Bruta (como no BI)
        }
        $saida[] = ['f' => $f, 'cf' => $cf, 'vals' => $vals, 'gos' => $dados['porGo'][$cf] ?? []];
    }
    $ll = $cum; // (=) Lucro Liquido = soma de tudo que esta mapeado
    $semClass = $zero;
    foreach ($dados['semCf'] as $vs) {
        foreach ($cols as $c) {
            $semClass[$c] += $vs[$c] ?? 0.0;
        }
    }
    $pct = function (float $v, string $c) use ($rl): string {
        return abs($rl[$c] ?? 0) > 1e-9 ? number_format($v / $rl[$c] * 100, 1, ',', '.') . '%' : '';
    };
    $celula = function (array $vals, string $c) use ($fmtV, $pct): string {
        $v = $vals[$c] ?? 0.0;
        return '<td class="col-valor" x:num="' . $v . '">' . e($fmtV($v))
             . '</td><td class="col-pct">' . e($pct($v, $c)) . '</td>';
    };
    $totGeral = array_map(fn(float $a, float $b): float => $a + $b, $ll, $semClass);
?>
<div class="rel-econ">
    <div class="rel-econ-cab">
        <div class="rel-econ-titulo">DRE | Demonstrativo do Resultado do Exercício —
            <?= $modo === 'ano' ? 'por Ano' : 'Mensal' ?><?= $arquivada ? ' (Arquivada)' : '' ?></div>
        <div class="rel-econ-impresso"><?= $arquivada ? 'DRE Arquivada — calculada até o dia anterior. ' : '' ?>Período: <?= e($o['data_ini']) ?> a <?= e($o['data_fim']) ?>
            · Empresa(s): <?= $o['empresas'] ? e(implode(', ', array_map('dre_emp_rotulo', $o['empresas']))) : 'Todas' ?>
            · Impresso em <?= e(date('d/m/Y H:i')) ?></div>
    </div>
    <div class="tabela-responsiva">
    <table class="tabela rel-econ-tab dre-tab">
        <thead>
            <tr>
                <th rowspan="2">Discriminação</th>
                <?php foreach ($cols as $c): ?>
                    <th colspan="2" class="dre-colh"><?= e($rot[$c]) ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($cols as $c): ?>
                    <th class="dre-colv">Valor R$</th><th class="dre-colv">%</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php $seq = 0; foreach ($saida as $L):
            $id = 'l' . $seq++;
            $gos = $L['gos'];
            ksort($gos); ?>
            <tr class="dre-n1 <?= $L['f']['SubTotal'] ? 'dre-sub' : '' ?>"
                <?= $gos ? 'data-tog="' . $id . '"' : '' ?>>
                <td><?= e($L['f']['DRE']) ?></td>
                <?php foreach ($cols as $c) {
                    echo $celula($L['vals'], $c);
                } ?>
            </tr>
            <?php $j = 0; foreach ($gos as $go => $gv):
                $gid = $id . 'g' . $j++;
                $subs = $dados['porSub'][$L['cf']][$go] ?? [];
                ksort($subs); ?>
                <tr class="dre-go dre-hidden" data-pai="<?= $id ?>"
                    <?= $subs ? 'data-tog="' . $gid . '"' : '' ?>>
                    <td><?= e($go === '' ? '(vazio)' : $go) ?></td>
                    <?php foreach ($cols as $c) {
                        echo $celula($gv, $c);
                    } ?>
                </tr>
                <?php foreach ($subs as $sub => $sv): ?>
                    <tr class="dre-sub2 dre-hidden" data-pai="<?= $gid ?>">
                        <td><?= e($sub === '' ? '(vazio)' : $sub) ?></td>
                        <?php foreach ($cols as $c) {
                            echo $celula($sv, $c);
                        } ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
            <tr class="dre-ll">
                <td>(=) Lucro Líquido</td>
                <?php foreach ($cols as $c) {
                    echo $celula($ll, $c);
                } ?>
            </tr>
            <?php if ($dados['semCf']): ?>
            <tr class="dre-nc" data-tog="nc">
                <td>Não classificado no DRE (sem mapeamento em DRE_GR_CL)</td>
                <?php foreach ($cols as $c) {
                    echo $celula($semClass, $c);
                } ?>
            </tr>
            <?php $k = 0; foreach ($dados['semCf'] as $par => $vs):
                [$ncGo, $ncSub] = explode('|', $par, 2); ?>
                <tr class="dre-go dre-hidden" data-pai="nc">
                    <td><?= e(($ncGo === '' ? '(vazio)' : $ncGo) . ' · ' . ($ncSub === '' ? '(vazio)' : $ncSub)) ?></td>
                    <?php foreach ($cols as $c) {
                        echo $celula($vs, $c);
                    } ?>
                </tr>
            <?php endforeach; ?>
            <tr class="dre-totgeral">
                <td>Total do período (incluindo não classificados)</td>
                <?php foreach ($cols as $c) {
                    echo $celula($totGeral, $c);
                } ?>
            </tr>
            <?php endif; ?>
            <tr class="dre-tempo">
                <?php
                    $t = (int) round($dados['tempo']);
                    $h = intdiv($t, 3600);
                    $mi = intdiv($t % 3600, 60);
                    $sg = $t % 60;
                    $partes = [];
                    if ($h)  $partes[] = $h . ' ' . ($h === 1 ? 'hora' : 'horas');
                    if ($mi) $partes[] = $mi . ' ' . ($mi === 1 ? 'minuto' : 'minutos');
                    if ($sg || !$partes) $partes[] = $sg . ' ' . ($sg === 1 ? 'segundo' : 'segundos');
                    $tempoTxt = count($partes) > 1
                        ? implode(' ', array_slice($partes, 0, -1)) . ' e ' . end($partes)
                        : $partes[0];
                ?>
                <td colspan="<?= 1 + 2 * count($cols) ?>">Relatório gerado em
                    <?= e($tempoTxt) ?>.</td>
            </tr>
        </tbody>
    </table>
    </div>
</div>
<?php elseif (!$erro): ?>
<p style="color:#666">Nenhum movimento encontrado no período.</p>
<?php endif; ?>
