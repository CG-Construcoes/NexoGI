<?php // Item 1 — POSICAO FINANCEIRA: linhas (Saldo bancario / Aplicacoes /
      // Posicao total) x colunas de data (hoje + fechamento de cada mes do
      // trimestre). Projecao gravada no snapshot msoft.FluxoCaixa_Posicao
      // (mesN = saldo anterior - pagar(N) + receber(N)); aplicacoes
      // constantes, como no modelo. Espera $fcPos (fcs_posicao_salva|null).
      // Clique numa celula de valor abre o detalhe "de onde vem o valor":
      // coluna de hoje -> contas que compoem saldo/aplicacoes (snapshot
      // msoft.FluxoCaixa_PosicaoDetalhe); coluna de mes -> composicao
      // saldo anterior + a receber - a pagar ($fcRec/$fcPag do quadro). ?>
<?php if ($fcPos): ?>
<p class="fcs-ult">Último cálculo: <?= e(data_br((string) $fcPos['ultima'], true)) ?>
    — clique em Atualizar para recalcular. <em>Clique num valor para ver de onde ele vem.</em></p>
<?php endif; ?>
<table class="tabela fcs-pagar" id="fcs-posicao">
    <thead>
        <tr>
            <th></th>
            <th class="col-valor" title="Saldo bancário = coluna Saldo Atual, e Aplicações financeiras = coluna Valor Aplicado, do Total Geral da Posição de Contas (Bancos, todas as empresas, dbMGA + dbConv)"><?= e(data_br(date('Y-m-d'))) ?></th>
            <?php $ant = 'Saldo bancário de ' . data_br(date('Y-m-d'));
            foreach (fcs_pagar_meses() as $ym):
                $mm = fcs_mes_rotulo($ym);
                $tip = "Saldo bancário = $ant − Contas a Pagar ($mm) + Contas a Receber ($mm). Aplicações constantes; Posição total = soma das duas."; ?>
            <th class="col-valor" title="<?= e($tip) ?>"><?= e(data_br(date('Y-m-d',
                strtotime("$ym-01 +1 month -1 day")))) ?></th>
            <?php $ant = 'saldo de ' . data_br(date('Y-m-d',
                strtotime("$ym-01 +1 month -1 day")));
            endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Saldo bancário</td>
            <td class="col-valor fcs-cel" data-det="hoje"><?= $fcPos
                ? number_format($fcPos['saldo'], 2, ',', '.') : '—' ?></td>
            <?php foreach ([0, 1, 2] as $i): ?>
            <td class="col-valor fcs-cel" data-det="mes<?= $i ?>"><?= $fcPos
                ? number_format($fcPos['mes'][$i], 2, ',', '.') : '—' ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <td>Aplicações financeiras</td>
            <td class="col-valor fcs-cel" data-det="hoje"><?= $fcPos
                ? number_format($fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php foreach ([0, 1, 2] as $i): ?>
            <td class="col-valor fcs-cel" data-det="hoje"><?= $fcPos
                ? number_format($fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php endforeach; ?>
        </tr>
    </tbody>
    <tfoot>
        <tr class="fcs-tot">
            <td>Posição total</td>
            <td class="col-valor fcs-cel" data-det="hoje"><?= $fcPos
                ? number_format($fcPos['saldo'] + $fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php foreach ([0, 1, 2] as $i): ?>
            <td class="col-valor fcs-cel" data-det="mes<?= $i ?>"><?= $fcPos
                ? number_format($fcPos['mes'][$i] + $fcPos['aplic'], 2, ',', '.') : '—' ?></td>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>
<?php if (!$fcPos): ?>
<p class="vazio">Posição financeira ainda não calculada — clique em Atualizar.</p>
<?php endif; ?>

<?php
/* ---------- detalhe expansivel ---------- */
$posDet = $fcPos ? fcs_posicao_detalhe() : [];
$recLin = ($fcRec ?? [])['linhas'] ?? [];
$pagLin = ($fcPag ?? [])['linhas'] ?? [];
$mesesPos = fcs_pagar_meses();
$num = fn($v) => number_format((float) $v, 2, ',', '.');
?>
<?php if ($fcPos): ?>
<div id="fcs-pos-det" style="display:none; margin-top:10px; border:1px solid #c7d3e0;
     border-radius:8px; background:#f8fafc; padding:10px 14px">

    <div data-bloco="hoje" hidden>
        <strong>Contas bancárias que compõem o saldo de hoje
            (Posição de Contas — Bancos)</strong>
        <?php if (!$posDet): ?>
        <p class="vazio">Detalhe por conta ainda não gravado — clique em
            Atualizar para recalcular a posição.</p>
        <?php else: ?>
        <table class="tabela" style="margin-top:6px">
            <thead><tr>
                <th>Base</th><th>Empresa</th><th>Agente</th>
                <th>Agência</th><th>Conta</th>
                <th class="col-valor">Saldo Atual</th>
                <th class="col-valor">Valor Aplicado</th>
                <th>Últ. Conciliação</th>
            </tr></thead>
            <tbody>
            <?php $tS = 0.0; $tA = 0.0; $baseAnt = null; $sS = 0.0; $sA = 0.0;
            foreach ($posDet as $i => $r):
                if ($baseAnt !== null && $r['Base'] !== $baseAnt): ?>
            <tr class="fcs-tot">
                <td colspan="5">Total <?= e($baseAnt) ?></td>
                <td class="col-valor"><?= $num($sS) ?></td>
                <td class="col-valor"><?= $num($sA) ?></td>
                <td></td>
            </tr>
            <?php $sS = 0.0; $sA = 0.0; endif;
                if ($r['Base'] !== $baseAnt): ?>
            <tr style="background:#e8eef5; font-weight:600">
                <td colspan="8"><?= e($r['Base']) ?></td>
            </tr>
            <?php $baseAnt = $r['Base']; endif;
                $sS += $r['SaldoAtual']; $sA += $r['ValorAplicado'];
                $tS += $r['SaldoAtual']; $tA += $r['ValorAplicado']; ?>
            <tr>
                <td></td>
                <td><?= e(trim($r['Empresa_Cod'] . ' ' . (string) $r['Empresa_Nome'])) ?></td>
                <td><?= e(trim($r['Agente_Cod'] . ' ' . (string) $r['Agente_Nome'])) ?></td>
                <td><?= e($r['Agencia']) ?></td>
                <td><?= e($r['Conta']) ?></td>
                <td class="col-valor"><?= $num($r['SaldoAtual']) ?></td>
                <td class="col-valor"><?= $num($r['ValorAplicado']) ?></td>
                <td><?= $r['UltimaConc'] ? e(data_br(substr((string) $r['UltimaConc'], 0, 10))) : '—' ?></td>
            </tr>
            <?php endforeach;
            if ($baseAnt !== null): ?>
            <tr class="fcs-tot">
                <td colspan="5">Total <?= e($baseAnt) ?></td>
                <td class="col-valor"><?= $num($sS) ?></td>
                <td class="col-valor"><?= $num($sA) ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
            </tbody>
            <tfoot><tr class="fcs-tot">
                <td colspan="5">Total</td>
                <td class="col-valor"><?= $num($tS) ?></td>
                <td class="col-valor"><?= $num($tA) ?></td>
                <td></td>
            </tr></tfoot>
        </table>
        <?php endif; ?>
    </div>

    <?php foreach ($mesesPos as $mi => $ym):
        $mm = fcs_mes_rotulo($ym);
        $ant2 = $mi === 0 ? $fcPos['saldo'] : $fcPos['mes'][$mi - 1];
        $itens = $recLin[$ym] ?? [];
        $totRec = array_sum(array_column($itens, 'valor'));
        $totPagC = 0.0; $totPagP = 0.0; $linPag = [];
        foreach ($pagLin as $l) {
            $v = $l['mes'][$ym] ?? null;
            if ($v && ($v['c'] != 0.0 || $v['p'] != 0.0)) {
                $linPag[] = ['nome' => $l['nome'], 'c' => $v['c'], 'p' => $v['p']];
                $totPagC += $v['c']; $totPagP += $v['p'];
            }
        }
        $totPag = $totPagC + $totPagP; ?>
    <div data-bloco="mes<?= $mi ?>" hidden>
        <strong>Fechamento de <?= e($mm) ?> —
            Saldo = <?= $num($ant2) ?> (saldo anterior)
            + <?= $num($totRec) ?> (contas a receber)
            − <?= $num($totPag) ?> (contas a pagar)
            = <?= $num($fcPos['mes'][$mi]) ?></strong>
        <div style="display:flex; gap:24px; flex-wrap:wrap; margin-top:8px">
            <div style="flex:1; min-width:340px">
                <em>Contas a receber (<?= e($mm) ?>)</em>
                <?php if (!$itens): ?>
                <p class="vazio">Nenhuma previsão/medida no mês.</p>
                <?php else: ?>
                <table class="tabela" style="margin-top:4px">
                    <thead><tr><th>Obra</th><th>Descrição</th><th>Situação</th>
                        <th class="col-valor">Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($itens as $it): ?>
                    <tr>
                        <td><?= e($it['obra']) ?></td>
                        <td><?= e($it['desc']) ?></td>
                        <td><?= e($it['sit']) ?></td>
                        <td class="col-valor"><?= $num($it['valor']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fcs-tot"><td colspan="3">Total</td>
                        <td class="col-valor"><?= $num($totRec) ?></td></tr></tfoot>
                </table>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:340px">
                <em>Contas a pagar (<?= e($mm) ?>)</em>
                <?php if (!$linPag): ?>
                <p class="vazio">Nenhum título no mês.</p>
                <?php else: ?>
                <table class="tabela" style="margin-top:4px">
                    <thead><tr><th>Grupo</th>
                        <th class="col-valor">Comprometido</th>
                        <th class="col-valor">Previsto</th>
                        <th class="col-valor">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($linPag as $l): ?>
                    <tr>
                        <td><?= e($l['nome']) ?></td>
                        <td class="col-valor"><?= $num($l['c']) ?></td>
                        <td class="col-valor"><?= $num($l['p']) ?></td>
                        <td class="col-valor"><?= $num($l['c'] + $l['p']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fcs-tot"><td>Total</td>
                        <td class="col-valor"><?= $num($totPagC) ?></td>
                        <td class="col-valor"><?= $num($totPagP) ?></td>
                        <td class="col-valor"><?= $num($totPag) ?></td></tr></tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    var painel = document.getElementById('fcs-pos-det');
    if (!painel) { return; }
    var aberto = null;
    document.querySelectorAll('#fcs-posicao td.fcs-cel').forEach(function (td) {
        td.style.cursor = 'pointer';
        td.title = 'Clique para ver de onde vem este valor';
        td.addEventListener('click', function () {
            var chave = td.getAttribute('data-det');
            if (aberto === chave) {
                painel.style.display = 'none';
                aberto = null;
                td.style.background = '';
                return;
            }
            aberto = chave;
            document.querySelectorAll('#fcs-posicao td.fcs-cel').forEach(function (t) {
                t.style.background = t.getAttribute('data-det') === chave ? '#fff3cd' : '';
            });
            painel.querySelectorAll('[data-bloco]').forEach(function (b) {
                b.hidden = b.getAttribute('data-bloco') !== chave;
            });
            painel.style.display = 'block';
            painel.scrollIntoView({block: 'nearest', behavior: 'instant'});
        });
    });
})();
</script>
<?php endif; ?>
