<h2>Indice de Pedidos Autorizados solicitados no prazo</h2>

<div class="indicador-topo">
    <p class="indicador-legenda">(Prazo = Autoriza&ccedil;&atilde;o da Solicita&ccedil;&atilde;o + 10 dias para cria&ccedil;&atilde;o do pedido e autoriza&ccedil;&atilde;o).</p>
    <?php if ($centros): ?>
    <div class="pizza-wrap">
        <svg class="pizza" viewBox="0 0 42 42" role="img" aria-label="Proporcao no prazo x fora do prazo">
            <circle class="pizza-fundo" cx="21" cy="21" r="15.9155"></circle>
            <circle id="fatiaPrazo" class="fatia-prazo" cx="21" cy="21" r="15.9155" transform="rotate(-90 21 21)"></circle>
            <text id="pizzaPct" x="21" y="22" class="pizza-centro"></text>
        </svg>
        <div class="pizza-legenda">
            <span><i class="sw sw-prazo"></i>No prazo</span>
            <span><i class="sw sw-fora"></i>Fora do prazo</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<form class="filtros" method="get" action="<?= url('indicador-pedidos-prazo') ?>">
    <div class="campo">
        <label for="data_ini">Data de inicio</label>
        <input type="date" id="data_ini" name="data_ini" value="<?= e($data_ini) ?>">
    </div>
    <div class="campo">
        <label for="data_fim">Data de fim</label>
        <input type="date" id="data_fim" name="data_fim" value="<?= e($data_fim) ?>">
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn">Filtrar</button>
    </div>
</form>

<?php if ($erro): ?>
    <p class="alert-erro" style="margin-top:16px"><?= e($erro) ?></p>
<?php endif; ?>

<?php if (!$centros): ?>
    <p class="vazio" style="margin-top:22px">Nenhum pedido encontrado no periodo.</p>
<?php else: ?>
<div class="grupo">
    <div class="grupo-titulo">Resumo por centro de custo — desmarque os centros que nao devem entrar no Resumo Geral</div>
    <table class="tabela" id="tblIndicador">
        <thead>
            <tr>
                <th class="col-check"><input type="checkbox" id="chkTodos" checked title="Marcar/desmarcar todos"></th>
                <th>Centro de custo</th>
                <th>Total de Pedidos</th>
                <th>Valor Pedidos</th>
                <th>Fora do Prazo (&lt; 10 dias)</th>
                <th>Valor Pedidos Fora do Prazo</th>
                <th>%</th>
                <th>No Prazo (&ge; 10 dias)</th>
                <th>Valor Pedidos no Prazo</th>
                <th>%</th>
                <th>Corrigidos no Per&iacute;odo</th>
                <th>Valor Pedidos Corrigidos</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            <tr class="linha-resumo">
                <td class="col-check"></td>
                <td><strong>Resumo Geral</strong></td>
                <td id="rgTotal"></td>
                <td id="rgValor"></td>
                <td id="rgFora"></td>
                <td id="rgVFora"></td>
                <td id="rgPctFora"></td>
                <td id="rgPrazo"></td>
                <td id="rgVPrazo"></td>
                <td id="rgPctPrazo"></td>
                <td id="rgCorr"></td>
                <td id="rgVCorr"></td>
                <td id="rgPctCorr"></td>
            </tr>
            <?php foreach ($centros as $c): ?>
            <?php
                $pctFora  = $c['total'] > 0 ? $c['fora']  / $c['total'] * 100 : 0;
                $pctPrazo = $c['total'] > 0 ? $c['prazo'] / $c['total'] * 100 : 0;
                $pctCorr  = $c['total'] > 0 ? $c['corr']  / $c['total'] * 100 : 0;
            ?>
            <tr>
                <td class="col-check">
                    <input type="checkbox" class="chk-centro" checked
                        data-total="<?= (int) $c['total'] ?>"
                        data-valor="<?= $c['valor'] ?>"
                        data-fora="<?= (int) $c['fora'] ?>"
                        data-vfora="<?= $c['vfora'] ?>"
                        data-prazo="<?= (int) $c['prazo'] ?>"
                        data-vprazo="<?= $c['vprazo'] ?>"
                        data-corr="<?= (int) $c['corr'] ?>"
                        data-vcorr="<?= $c['vcorr'] ?>">
                </td>
                <td><a href="<?= url('indicador-pedidos-prazo/pedidos') . '?' . http_build_query(['centro' => $c['nome'], 'data_ini' => $data_ini, 'data_fim' => $data_fim]) ?>"
                       class="centro-link"
                       onclick="window.open(this.href, 'pedidos_<?= md5($c['nome']) ?>', 'width=1100,height=700,scrollbars=yes'); return false;"><?= e($c['nome']) ?></a></td>
                <td><?= (int) $c['total'] ?></td>
                <td><?= br($c['valor']) ?></td>
                <td><?= (int) $c['fora'] ?></td>
                <td><?= br($c['vfora']) ?></td>
                <td><?= number_format($pctFora, 1, ',', '.') ?>%</td>
                <td><?= (int) $c['prazo'] ?></td>
                <td><?= br($c['vprazo']) ?></td>
                <td><?= number_format($pctPrazo, 1, ',', '.') ?>%</td>
                <td><?= (int) $c['corr'] ?></td>
                <td><?= br($c['vcorr']) ?></td>
                <td><?= number_format($pctCorr, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    var checks = document.querySelectorAll('.chk-centro');
    var chkTodos = document.getElementById('chkTodos');

    function fmtPct(parte, total) {
        if (!total) return '0,0%';
        return (parte / total * 100).toFixed(1).replace('.', ',') + '%';
    }

    function fmtValor(v) {
        return v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function atualizar() {
        var total = 0, valor = 0, fora = 0, vfora = 0, prazo = 0, vprazo = 0, corr = 0, vcorr = 0;
        checks.forEach(function (chk) {
            chk.closest('tr').classList.toggle('fora-selecao', !chk.checked);
            if (!chk.checked) return;
            total  += +chk.dataset.total;
            valor  += +chk.dataset.valor;
            fora   += +chk.dataset.fora;
            vfora  += +chk.dataset.vfora;
            prazo  += +chk.dataset.prazo;
            vprazo += +chk.dataset.vprazo;
            corr   += +chk.dataset.corr;
            vcorr  += +chk.dataset.vcorr;
        });
        document.getElementById('rgTotal').textContent    = total;
        document.getElementById('rgValor').textContent    = fmtValor(valor);
        document.getElementById('rgFora').textContent     = fora;
        document.getElementById('rgVFora').textContent    = fmtValor(vfora);
        document.getElementById('rgPctFora').textContent  = fmtPct(fora, total);
        document.getElementById('rgPrazo').textContent    = prazo;
        document.getElementById('rgVPrazo').textContent   = fmtValor(vprazo);
        document.getElementById('rgPctPrazo').textContent = fmtPct(prazo, total);
        document.getElementById('rgCorr').textContent     = corr;
        document.getElementById('rgVCorr').textContent    = fmtValor(vcorr);
        document.getElementById('rgPctCorr').textContent  = fmtPct(corr, total);
        var pct = total ? prazo / total * 100 : 0;
        document.getElementById('fatiaPrazo').setAttribute('stroke-dasharray', pct + ' ' + (100 - pct));
        document.getElementById('pizzaPct').textContent = fmtPct(prazo, total);
        chkTodos.checked = [].every.call(checks, function (c) { return c.checked; });
    }

    checks.forEach(function (chk) { chk.addEventListener('change', atualizar); });
    chkTodos.addEventListener('change', function () {
        checks.forEach(function (chk) { chk.checked = chkTodos.checked; });
        atualizar();
    });
    atualizar();
})();
</script>
<?php endif; ?>
