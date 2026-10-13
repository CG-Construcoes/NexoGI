<div class="pagina-lista ficha-pedido">
<?php if (!$det): ?>
    <h2>Pedido nao encontrado</h2>
    <p class="vazio">Verifique o numero do pedido e a base informada.</p>
<?php else:
    $c = $det['cab'];
    $semColeta = trim((string) ($c['Coleta_Numero'] ?? '')) === '';
    $totalItens = array_sum(array_map(fn($i) => (float) $i['Total_Item'], $det['itens']));
    $totalPedido = $totalItens - (float) ($c['Pedido_DescPed'] ?? 0) + (float) ($c['Pedido_FreteTotal'] ?? 0);
?>
    <h2>Pedido <?= e($c['Pedido_CentroNumero'] ?: $c['Pedido_Numero']) ?>
        <span class="badge badge-<?= $det['base'] === 'CONV' ? 'ativo' : 'pendente' ?>"><?= e($det['base']) ?></span>
    </h2>

    <div class="ficha-linha">
        <div class="ficha-campo">
            <span class="ficha-rotulo">Nº Pedido</span>
            <span class="ficha-valor"><?= e($c['Pedido_CentroNumero'] ?: $c['Pedido_Numero']) ?></span>
        </div>
        <div class="ficha-campo ficha-grow">
            <span class="ficha-rotulo">Centro de Negocio</span>
            <span class="ficha-valor"><?= e(trim($c['Centro_CodC'] . ' ' . ($c['Centro_Nome'] ?? ''))) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Data do Pedido</span>
            <span class="ficha-valor"><?= e(data_br($c['Pedido_Data'])) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Data de Validade</span>
            <span class="ficha-valor"><?= e(data_br($c['Pedido_DataFinal'])) ?></span>
        </div>
        <div class="ficha-campo ficha-grow">
            <span class="ficha-rotulo">Fornecedor</span>
            <span class="ficha-valor"><?= e(trim(($c['Cre_Cod'] ?? '') . ' ' . ($c['Fornecedor'] ?? ''))) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Nº Coleta</span>
            <span class="ficha-valor"><?= e(trim((string) $c['Coleta_Numero'])) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Fornec. Short</span>
            <span class="ficha-valor"><?= e(trim((string) $c['ColForn_Numero'])) ?></span>
        </div>
    </div>

    <div class="ficha-linha">
        <div class="ficha-campo">
            <span class="ficha-rotulo">CNPJ/CPF</span>
            <span class="ficha-valor"><?= e(fmt_cnpj($c['CNPJ_Filial'])) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Codigo</span>
            <span class="ficha-valor"><?= e(trim((string) $c['CodMun_Filial'])) ?></span>
        </div>
        <div class="ficha-campo ficha-grow"></div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">CNPJ/CPF</span>
            <span class="ficha-valor"><?= e(fmt_cnpj($c['CNPJ_Forn'])) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Codigo</span>
            <span class="ficha-valor"><?= e(trim((string) $c['CodMun_Forn'])) ?></span>
        </div>
    </div>

    <?php if (trim((string) ($c['Obra_Numero'] ?? '')) !== ''): ?>
    <div class="ficha-linha">
        <div class="ficha-campo ficha-grow">
            <span class="ficha-rotulo">Obra</span>
            <span class="ficha-valor"><?= e(trim($c['Obra_Numero'] . ' ' . ($c['Obra_Nome'] ?? ''))) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">CNO</span>
            <span class="ficha-valor"><?= e($c['Obra_Cno']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="ficha-linha">
        <div class="ficha-campo ficha-grow">
            <span class="ficha-rotulo">UF e Municipio de Consumo — Fato Gerador de debitos (Vendas) do IS, IBS e CBS</span>
            <span class="ficha-valor"></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Estado/UF</span>
            <span class="ficha-valor"><?= e($c['UFPrestacao']) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Municipio</span>
            <span class="ficha-valor"><?= e($c['MunicipioPrest_Nome'] ?: $c['MunicipioPrestacao']) ?></span>
        </div>
        <div class="ficha-campo">
            <span class="ficha-rotulo">Codigo IBGE</span>
            <span class="ficha-valor"><?= e($c['CodigoLocalPrestacao']) ?></span>
        </div>
    </div>

    <h3 class="ficha-subtitulo">Itens do Pedido</h3>
    <table class="tabela tabela-ficha">
        <thead>
            <tr>
                <th>Item</th>
                <th>Solic</th>
                <th>Sit</th>
                <th>Especificacao</th>
                <th>Insumo-Sub</th>
                <th>Cc</th>
                <th>Unid</th>
                <th class="td-num">Quantidade</th>
                <th class="td-num">Pr.Unit Inicial</th>
                <th class="td-num">% Descr</th>
                <th class="td-num">Pr.Unit Insumo</th>
                <th class="td-num">% IPI</th>
                <th class="td-num">Total do Item</th>
                <th>Entrega Pedido</th>
                <th>Entrega Solicitacao</th>
                <th>Orc.Emp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($det['itens'] as $it): ?>
            <tr>
                <td><?= e($it['PedItem_Item']) ?></td>
                <td><?= e(trim((string) $it['Solicitacao_ID']) !== '' && trim((string) $it['Solicitacao_ID']) !== '/' ? $it['Solicitacao_ID'] : '-') ?></td>
                <td><?= e($it['SolicItem_Situacao']) ?></td>
                <td><?= e($it['Especificacao']) ?></td>
                <td><?= e($it['Insumo_CodC']) ?></td>
                <td><?= e($it['CarIns_Cod']) ?></td>
                <td><?= e($it['Unid_Cod']) ?></td>
                <td class="td-num"><?= e(br($it['PedItem_Quantidade'], 4)) ?></td>
                <td class="td-num"><?= e(br($it['PedItem_PrecoDig'], 6)) ?></td>
                <td class="td-num"><?= e(br($it['PedItem_PercDesc'] ?? 0, 2)) ?></td>
                <td class="td-num"><?= e(br($it['PedItem_PrecoInsumo'], 6)) ?></td>
                <td class="td-num"><?= e(br($it['PedItem_AliqIPI'] ?? 0, 2)) ?></td>
                <td class="td-num"><?= e(br($it['Total_Item'], 2)) ?></td>
                <td><?= e(data_br($it['PedItem_DataEntrega'])) ?></td>
                <td><?= e(data_br($it['SolicItem_DataEntrega'])) ?></td>
                <td><?= e(trim((string) ($it['Orc_Cod'] ?? '')) !== '' && trim((string) $it['Orc_Cod']) !== '0' ? $it['Orc_Cod'] : '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$det['itens']): ?>
            <tr><td colspan="16" class="vazio">Nenhum item neste pedido.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="ficha-rodape">
        <div class="ficha-audit">
            <div>Criado por<br><strong><?= e(trim(($c['CriadoEm'] ? data_br(substr((string) $c['CriadoEm'], 0, 10)) . ' ' . substr((string) $c['CriadoEm'], 11, 8) : '') . ' ' . ($c['CriadoPor'] ?? ''))) ?></strong></div>
            <div>Ultima alteracao por<br><strong><?= e(trim(($c['AlteradoEm'] ? data_br(substr((string) $c['AlteradoEm'], 0, 10)) . ' ' . substr((string) $c['AlteradoEm'], 11, 8) : '') . ' ' . ($c['AlteradoPor'] ?? ''))) ?></strong></div>
        </div>
        <div class="ficha-totais">
            <?php if ($semColeta): ?>
            <span class="badge badge-pendente">Sem Coleta</span>
            <?php endif; ?>
            <span>Desconto Pedido <strong><?= e(br($c['Pedido_DescPed'] ?? 0)) ?></strong></span>
            <span>Frete <strong><?= e(br($c['Pedido_FreteTotal'] ?? 0)) ?></strong></span>
            <span class="ficha-total">Total Pedido <strong><?= e(br($totalPedido)) ?></strong></span>
        </div>
    </div>
<?php endif; ?>
</div>
