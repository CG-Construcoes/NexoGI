<?php
/*
 * DRE — Demonstrativo do Resultado do Exercicio
 * Financeiro > Fluxo de Caixa > DRE por Ano ($modo='ano') / DRE Mensal ('mes')
 *
 * Replica as abas "DRE Ano"/"DRE Mes" do Power BI a partir das procedures
 * originais (ver comentario em app/dre.php). Hierarquia exibida:
 *   Linha DRE (FormatoDRE) > GO_Titulo > SubGO_Titulo, com valor + % por coluna.
 * Linhas SubTotal=1 acumulam os niveis anteriores; "(=) Lucro Liquido" soma
 * tudo que esta mapeado em DRE_GR_CL; o restante cai em "Nao classificado".
 */
$MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$fmtV = fn(float $v): string => number_format($v, 2, ',', '.');
?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">Consulta pesada — pode levar alguns minutos</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="this.querySelectorAll('.btn').forEach(function(b){b.disabled=true;});
                document.getElementById('relLoading').hidden=false;">
    <div class="rel-econ-titulo-form">DRE | Demonstrativo do Resultado do Exercício —
        <?= $modo === 'ano' ? 'por Ano' : 'Mensal' ?></div>

    <fieldset class="rel-econ-box">
        <legend>Período</legend>
        <?php if ($modo === 'ano'): ?>
            <label>Ano inicial
                <input type="number" name="ano_ini" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano_ini'] ?? date('Y')) ?>" required>
            </label>
            <label>Ano final
                <input type="number" name="ano_fim" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano_fim'] ?? date('Y')) ?>" required>
            </label>
            <div class="dre-nota">Consulta pesada — comece por 1 ano (limite de 10 anos por consulta).</div>
        <?php else: ?>
            <label>Ano
                <input type="number" name="ano" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano'] ?? date('Y')) ?>" required>
            </label>
            <div class="dre-meses">
                <?php foreach ($MESES as $i => $mn): ?>
                    <label><input type="checkbox" name="meses[]" value="<?= $i + 1 ?>"
                        <?= in_array($i + 1, (array) ($o['meses'] ?? range(1, 12)), true) ? 'checked' : '' ?>> <?= $mn ?></label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Empresa</legend>
        <div class="rel-econ-centros-lista">
            <?php foreach ($empresas as $e): ?>
                <label><input type="checkbox" name="empresas[]" value="<?= e($e['cod']) ?>"
                    <?= $e['cod'] === 'CGC' ? 'checked' : '' ?>>
                    <?= e($e['cod'] . ($e['nome'] !== '' ? ' - ' . $e['nome'] : '')) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="dre-nota">Nenhuma marcada = todas (equivale ao "selecionar tudo" do BI).</div>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Núcleo</legend>
        <input type="text" placeholder="Filtrar núcleo..." oninput="dreFiltra(this, 'listaNucDre')">
        <div class="rel-econ-centros-lista" id="listaNucDre">
            <?php foreach ($nucleos as $n): ?>
                <label><input type="checkbox" name="nucleos_sel[]" value="<?= e($n['chave']) ?>">
                    <?= e($n['rotulo']) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Obra / Centro de resultado</legend>
        <input type="text" placeholder="Filtrar obra..." oninput="dreFiltra(this, 'listaCenDre')">
        <div class="rel-econ-centros-lista" id="listaCenDre">
            <?php foreach ($centros as $c): ?>
                <label><input type="checkbox" name="centros_sel[]" value="<?= e($c['chave']) ?>">
                    <?= e($c['rotulo']) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="dre-nota">Nenhum marcado = todos os centros ativos (Vw_Centro_Ativo).</div>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
    <?php if ($erro !== ''): ?>
        <div class="alert-erro"><?= e($erro) ?></div>
    <?php endif; ?>
</form>
<script>
function dreFiltra(inp, id) {
    var f = inp.value.toLowerCase();
    document.querySelectorAll('#' + id + ' label').forEach(function (l) {
        l.style.display = l.textContent.toLowerCase().indexOf(f) >= 0 ? '' : 'none';
    });
}
</script>

<?php else: ?>
<!-- ===================== RELATORIO ===================== -->
<div class="filtros" style="margin-bottom:8px">
    <a class="btn btn-secundario" href="<?= url($active) ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro !== ''): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php endif; ?>

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
        if ($cf === 3) {
            $rl = $vals;
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
        return '<td class="col-valor">' . e($fmtV($v)) . '</td><td class="col-pct">'
             . e($pct($v, $c)) . '</td>';
    };
    $totGeral = array_map(fn(float $a, float $b): float => $a + $b, $ll, $semClass);
?>
<div class="rel-econ">
    <div class="rel-econ-cab">
        <div class="rel-econ-titulo">DRE | Demonstrativo do Resultado do Exercício —
            <?= $modo === 'ano' ? 'por Ano' : 'Mensal' ?></div>
        <div class="rel-econ-impresso">Período: <?= e($o['data_ini']) ?> a <?= e($o['data_fim']) ?>
            · Empresa(s): <?= $o['empresas'] ? e(implode(', ', $o['empresas'])) : 'Todas' ?>
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
        </tbody>
    </table>
    </div>
</div>
<script>
(function () {
    var toggle = function (id, abrir) {
        document.querySelectorAll('tr[data-pai="' + id + '"]').forEach(function (r) {
            r.classList.toggle('dre-hidden', !abrir);
            if (!abrir) {
                r.classList.remove('dre-aberto');
                if (r.hasAttribute('data-tog')) {
                    toggle(r.getAttribute('data-tog'), false);
                }
            }
        });
    };
    document.querySelectorAll('tr[data-tog]').forEach(function (t) {
        t.addEventListener('click', function () {
            toggle(t.getAttribute('data-tog'), t.classList.toggle('dre-aberto'));
        });
    });
})();
</script>
<?php elseif (!$erro): ?>
<p style="color:#666">Nenhum movimento encontrado no período.</p>
<?php endif; ?>
<?php endif; ?>
</div>
