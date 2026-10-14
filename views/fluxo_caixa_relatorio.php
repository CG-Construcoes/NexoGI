<?php // Relatorio: Agrupamento de Centros de Custo por Grupo de Fluxo de Caixa ?>
<div class="filtros" style="margin-bottom:14px">
    <div class="campo acoes">
        <a href="<?= url('fluxo-caixa-grupos') ?>" class="btn btn-secundario">Voltar</a>
        <button type="button" class="btn" onclick="window.print()">Imprimir</button>
    </div>
</div>

<div class="rel-econ">
    <div class="rel-econ-cab">
        <div class="rel-econ-titulo">Agrupamento de Centros de Custo por Grupo de Fluxo de Caixa</div>
        <div class="rel-econ-impresso">Impresso em <?= date('d/m/Y H:i') ?></div>
    </div>

    <?php
    $totalCentros = 0;
    foreach ($grupos as $g) {
        $totalCentros += count($g['centros']);
    }
    ?>
    <table class="tabela rel-econ-tab">
        <thead>
            <tr><th>Centro de custo</th><th style="width:70px">CodC</th></tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $g): ?>
            <tr class="rel-secao">
                <td colspan="2"><?= (int) $g['FCGrupo_Id'] ?> - <?= e($g['FCGrupo_Nome']) ?><?= $g['FCGrupo_Descricao'] !== null && $g['FCGrupo_Descricao'] !== '' ? ' — ' . e($g['FCGrupo_Descricao']) : '' ?> (<?= count($g['centros']) ?>)</td>
            </tr>
            <?php if ($g['centros']): ?>
                <?php foreach ($g['centros'] as $c): ?>
            <tr>
                <td class="fc-rel-centro"><?= e($c['rotulo']) ?></td>
                <td><?= e($c['codc']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td class="fc-rel-centro" colspan="2" style="color:#999;font-style:italic">Nenhum centro de custo associado.</td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <tr class="rel-geral">
                <td>Total: <?= count($grupos) ?> grupo(s)</td>
                <td><?= $totalCentros ?></td>
            </tr>
        </tbody>
    </table>
</div>
