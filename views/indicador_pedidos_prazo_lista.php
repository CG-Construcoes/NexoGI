<div class="pagina-lista">
    <h2><?= e($centro) ?></h2>
    <p class="vazio">Indice de Pedidos Autorizados solicitados no prazo
        — <?= e(data_br($data_ini)) ?> a <?= e(data_br($data_fim)) ?>
        — <?= count($linhas) ?> pedido(s)</p>

    <?php if ($erro): ?>
        <p class="alert-erro"><?= e($erro) ?></p>
    <?php endif; ?>

    <table class="tabela">
        <thead>
            <tr>
                <th>Numero do Pedido</th>
                <th>Data do Pedido</th>
                <th>Data Autorizacao do Pedido</th>
                <th>Valor do Pedido</th>
                <th>Situacao</th>
                <th>Fornecedor</th>
                <th>Base</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linhas as $l): ?>
            <tr>
                <td><?= (int) $l['num'] ?></td>
                <td><?= e($l['pedido']) ?></td>
                <td><?= e($l['aut']) ?></td>
                <td><?= br($l['valor']) ?></td>
                <td><?= $l['dias'] >= 10 ? 'No prazo' : 'Fora do prazo' ?></td>
                <td><?= e($l['forn']) ?></td>
                <td><?= e($l['base']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$linhas): ?>
            <tr><td colspan="7" class="vazio">Nenhum pedido neste centro no periodo.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
