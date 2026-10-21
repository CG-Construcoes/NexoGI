<?php // Acompanhamento Financeiro — resumo dos 7 grupos (Financeiro > Fluxo de Caixa) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<p style="margin:0 0 14px">
    <a href="<?= e(url('fluxo-caixa')) ?>">&laquo; Voltar</a>
</p>

<div class="fcs-secao">
    <div class="fcs-secao-tit">1. POSIÇÃO FINANCEIRA</div>
    <div class="fcs-secao-corpo">
        <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
    </div>
</div>

<div class="fcs-secao">
    <div class="fcs-secao-tit">2. APORTES A RETORNAR</div>
    <div class="fcs-secao-corpo">
        <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
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
                    <th class="col-valor">Saldo do Grupo</th>
                    <th>Última Atualização</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $g):
                    $att = in_array((int) $g['FCGrupo_Id'], $atualizados, true); ?>
                <tr<?= $att ? ' class="fcs-att"' : '' ?>>
                    <td><?= e($g['FCGrupo_Nome']) ?>
                        <span class="fcs-desc">(trimestral)</span><?= $att
                        ? ' <span class="fcs-att-tag">recalculado</span>' : '' ?></td>
                    <td><?= (int) $g['QtdeCentros'] ?></td>
                    <td class="col-valor"><?= $g['SaldoGrupo'] !== null
                        ? number_format((float) $g['SaldoGrupo'], 2, ',', '.') : '&mdash;' ?></td>
                    <td><?= $g['UltimaAtualizacao']
                        ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '&mdash;' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
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
        <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
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

<script>
(function () {
    // Se o fluxo pediu impressao, o resumo abre o iframe ao carregar.
    var pendente = sessionStorage.getItem('fcs_imprimir');
    if (!pendente) { return; }
    sessionStorage.removeItem('fcs_imprimir');
    var f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
    f.onload = function () {
        f.contentWindow.focus();
        f.contentWindow.print();
    };
    f.src = pendente;
    document.body.appendChild(f);
})();
</script>
