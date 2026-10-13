<?php /* TELA TRAVADA: Impressao de Pedidos (porte fiel de templates/pedido_impressao.html). Nao alterar sem 3 confirmacoes. */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Impressão de Pedidos</title>
<style>
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #000; background: #888; }
    .folha {
        width: 210mm; height: 297mm; padding: 6mm 8mm; background: #fff;
        margin: 0 auto 6mm; page-break-after: always; overflow: hidden;
    }
    .folha:last-child { page-break-after: auto; }
    .box { border: 0.3mm solid #000; }
    .b { font-weight: bold; }
    .dir { text-align: right; }
    .centro { text-align: center; }
    .linha { display: flex; justify-content: space-between; }

    /* ---------- Cabecalho (112mm) ---------- */
    .cab { height: 108mm; overflow: hidden; }
    .cab-topo { width: 172mm; }
    .cab-topo .r1, .cab-topo .r2 { height: 6mm; display: flex; align-items: center; padding: 0 1.5mm; border-bottom: 0.3mm solid #000; }
    .r1 .nome { flex: 1; font-weight: bold; font-size: 8.5pt; }
    .r1 .ped { width: 50mm; font-size: 8.5pt; }
    .r1 .folhano { width: 25mm; text-align: right; }
    .r2 .data { width: 42mm; }
    .r2 .cc { flex: 1; font-weight: bold; text-align: center; }
    .r2 .cei { width: 40mm; text-align: right; }
    .forn { height: 18mm; padding: 1mm 1.5mm; }
    .bloco { margin-top: 1.2mm; padding: 1mm 1.5mm; }
    .fat, .cob { height: 15mm; }
    .ent { height: 20mm; }
    .ent .grande { font-size: 11pt; font-weight: bold; }
    .ent .obra { display: flex; gap: 25mm; }
    .fornecimento { display: flex; gap: 1.2mm; margin-top: 1.2mm; height: 21mm; }
    .fornecimento .esq { flex: 1; display: flex; flex-direction: column; gap: 1.2mm; }
    .fornecimento .obs { height: 6mm; padding: 1mm 1.5mm; }
    .fornecimento .cond { flex: 1; display: flex; }
    .cond .rot { width: 20mm; text-align: center; padding-top: 1mm; }
    .cond .txt { flex: 1; border-left: 0.3mm solid #000; padding: 1mm 1.5mm; }
    .cond .parc { font-size: 6.5pt; font-weight: bold; margin-top: 1.5mm; }
    .fornecimento .transp { width: 45mm; padding: 1.5mm; display: flex; gap: 8mm; }

    /* ---------- Itens ---------- */
    .itens-cab, .item { display: flex; }
    .itens-cab { height: 6mm; align-items: center; border: 0.3mm solid #000; border-bottom: none; }
    .itens-area { border: 0.3mm solid #000; border-top: 0.3mm solid #000; overflow: hidden; }
    .area-folha { height: 162mm; }
    .area-ultima { height: 68mm; }
    .item { padding-top: 1.2mm; padding-bottom: 1.2mm; line-height: 3.2mm; }
    .c-item { width: 8mm; padding-left: 1mm; }
    .c-sc { width: 8mm; text-align: center; }
    .c-cod { width: 17mm; padding-left: 1mm; }
    .c-desc { width: 66mm; white-space: nowrap; overflow: hidden; }
    .c-dt { width: 17mm; text-align: center; }
    .c-un { width: 11mm; text-align: center; }
    .c-qtd { width: 20mm; text-align: right; }
    .c-preco { width: 18mm; text-align: right; }
    .c-ipi { width: 10mm; text-align: right; }
    .c-tot { flex: 1; text-align: right; padding-right: 1mm; }
    .itens-cab .c-cod { display: none; }
    .itens-cab .c-desc { width: 83mm; padding-left: 1mm; }
    .continua { height: 6mm; text-align: right; padding: 1.5mm 1mm 0; }

    /* ---------- Rodape (100mm) ---------- */
    .rod { height: 100mm; margin-top: 1.2mm; display: flex; flex-direction: column; }
    .rod-a { height: 7mm; display: flex; gap: 1mm; }
    .rod-a > div { display: flex; align-items: center; padding: 0 1.5mm; }
    .rod-a .obs { width: 62mm; }
    .rod-a .vp { width: 43mm; justify-content: center; }
    .rod-a .desc { width: 36mm; justify-content: center; }
    .rod-a .tp { flex: 1; justify-content: flex-end; gap: 6mm; }
    .rod-b { height: 37mm; display: flex; gap: 1mm; margin-top: 1mm; }
    .rod-b .texto { flex: 1; padding: 1mm 1.5mm; }
    .rod-b .col { width: 50.5mm; display: flex; flex-direction: column; gap: 1mm; }
    .rod-b .tg { height: 7mm; display: flex; align-items: center; justify-content: flex-end; gap: 6mm; padding: 0 1.5mm; }
    .rod-b .aut { flex: 1; padding: 1.5mm; display: flex; flex-direction: column; justify-content: space-between; }
    .aut .assin { border-top: 0.3mm solid #000; font-size: 6pt; text-align: center; padding-top: 0.8mm; }
    .rod-c, .rod-d { height: 6mm; display: flex; }
    .rod-c > div, .rod-d > div { display: flex; align-items: center; padding: 0 1.5mm; }
    .rod-c .v1 { width: 43mm; } .rod-c .v2 { width: 34mm; } .rod-c .v3 { width: 33mm; }
    .rod-c .v4 { flex: 1; }
    .rod-d .imp { width: 50mm; justify-content: center; white-space: nowrap; } .rod-d .ret { flex: 1; }
    .rod-e { height: 6mm; display: flex; align-items: center; justify-content: center; margin-top: 1mm; }
    .rod-f { flex: 1; margin-top: 1mm; padding: 5mm 1.5mm 3mm; display: flex; flex-direction: column; justify-content: space-between; }
    .rod-f .aviso { font-weight: bold; font-size: 7pt; }
    .assinaturas { display: flex; justify-content: space-around; text-align: center; font-weight: bold; font-size: 7pt; }
    .assinaturas div { width: 45mm; border-top: 0.3mm solid #000; padding-top: 2mm; }

    .aviso-erro { background: #fee2e2; color: #b91c1c; padding: 4mm; margin: 4mm auto; width: 210mm; font-size: 10pt; }
    @media print {
        body { background: #fff; }
        .folha { margin: 0; }
        .aviso-erro { display: none; }
    }
</style>
</head>
<body>
<?php foreach ($erros as $erroPedido): ?><div class="aviso-erro">Erro ao carregar <?= e($erroPedido) ?></div><?php endforeach; ?>
<?php if (!$pedidos): ?><div class="aviso-erro">Nenhum pedido para imprimir.</div><?php endif; ?>

<?php foreach ($pedidos as $p):
    $totalFolhas = count($p['folhas']);
    foreach ($p['folhas'] as $iFolha => $itens):
        $ultima = $iFolha === $totalFolhas - 1; ?>
<div class="folha">
    <div class="cab">
        <div class="cab-topo box">
            <div class="r1">
                <span class="nome"><?= e($p['filial_nome']) ?></span>
                <span class="ped">PEDIDO Nº <?= e($p['numero']) ?></span>
                <span class="folhano">Folha No <?= $iFolha + 1 ?></span>
            </div>
            <div class="r2">
                <span class="data">Data Pedido: <?= e(data_br($p['data'])) ?></span>
                <span class="cc"><?= e($p['centro']) ?></span>
                <span class="cei">CEI: <?= e($p['cei']) ?></span>
            </div>
            <div class="forn">
                <div class="linha"><span>Fornecedor: <?= e($p['fornecedor']['nome']) ?></span><span>CNPJ: <?= e($p['fornecedor']['cnpj']) ?></span></div>
                <div class="linha"><span>Endereço: &nbsp;<?= e($p['fornecedor']['linha']) ?></span><span>Contato: <?= e($p['fornecedor']['contato']) ?></span></div>
                <div class="linha" style="margin-top:1.5mm"><span></span><span>Fone: <?= e($p['fornecedor']['fone']) ?> &nbsp;Fax: <?= e($p['fornecedor']['fax']) ?></span></div>
                <div class="linha"><span style="padding-left:14mm"><?= e($p['fornecedor']['cep_cidade']) ?></span><span>eMail: <?= e($p['fornecedor']['email']) ?></span></div>
            </div>
        </div>

        <div class="bloco box fat">
            <div class="linha"><span>Empresa Faturamento: &nbsp;<?= e($p['faturamento']['nome']) ?></span><span>CNPJ: <?= e($p['faturamento']['cnpj']) ?></span></div>
            <div class="linha"><span><?= e($p['faturamento']['linha']) ?></span><span><?= e($p['faturamento']['cep_cidade']) ?></span></div>
            <div class="linha"><span>Telefones: <?= e($p['faturamento']['fone']) ?></span><span>Contatos: &nbsp;email: <?= e($p['faturamento']['email']) ?></span></div>
        </div>

        <div class="bloco box cob">
            <div class="linha"><span>Cobrança: &nbsp;<?= e($p['cobranca']['linha']) ?></span><span>Contatos: <?= e($p['cobranca']['contato']) ?></span></div>
            <div class="linha"><span style="padding-left:14mm"><?= e($p['cobranca']['cep_cidade']) ?></span><span>Telefones: <?= e($p['cobranca']['fone']) ?></span></div>
            <div class="linha"><span></span><span>Emails: <?= e($p['cobranca']['email']) ?></span></div>
        </div>

        <div class="bloco box ent">
            <div class="linha"><span>Entrega: &nbsp;<?= e($p['entrega']['linha']) ?></span><span>Contatos: <?= e($p['entrega']['contato']) ?></span></div>
            <div class="linha"><span style="padding-left:11mm">cMun: <?= e($p['entrega']['cmun']) ?> <?= e($p['entrega']['cep_cidade']) ?></span><span>Telefones: <?= e($p['entrega']['fone']) ?></span></div>
            <div class="linha"><span class="grande">UF e Município de Consumo do Fato Gerador do IBS e CBS</span><span>Emails: <?= e($p['entrega']['email']) ?></span></div>
            <div class="obra grande"><span>Obra Nº ID: <?= e($p['obra']) ?></span><span>CNO da Obra: <?= e($p['cno']) ?></span></div>
        </div>

        <div class="fornecimento">
            <div class="esq">
                <div class="obs box">Fornecimento Obs: <?= e($p['obs_fornecimento']) ?></div>
                <div class="cond box">
                    <div class="rot">Condições<br>de Pagto :</div>
                    <div class="txt">
                        Espécie de Pagto: <span class="b"><?= e($p['especie']) ?></span>
                        <div class="parc"><?= e($p['parcelas']) ?></div>
                    </div>
                </div>
            </div>
            <div class="transp box"><span>Transporte :</span><span class="b"><?= e($p['transporte']) ?></span></div>
        </div>
    </div>

    <div class="itens-cab">
        <span class="c-item">Item</span><span class="c-sc">S.C</span><span class="c-cod"></span>
        <span class="c-desc">Discriminação</span><span class="c-dt">Dt Entrega</span><span class="c-un">Unidade</span>
        <span class="c-qtd">Quantidade</span><span class="c-preco">Preço</span><span class="c-ipi">% IPI</span>
        <span class="c-tot">Total do Item</span>
    </div>
    <div class="itens-area <?= $ultima ? 'area-ultima' : 'area-folha' ?>">
        <?php foreach ($itens as $it): ?>
        <div class="item">
            <span class="c-item"><?= e($it['item']) ?></span>
            <span class="c-sc"><?= e($it['sc']) ?></span>
            <span class="c-cod"><?= e($it['codigo']) ?></span>
            <span class="c-desc"><?= implode('<br>', array_map('e', $it['linhas'])) ?></span>
            <span class="c-dt"><?= e(data_br($it['entrega'])) ?></span>
            <span class="c-un"><?= e($it['unidade']) ?></span>
            <span class="c-qtd"><?= br($it['quantidade'], 4) ?></span>
            <span class="c-preco"><?= br($it['preco'], $it['preco'] >= 1000 ? 5 : 6) ?></span>
            <span class="c-ipi"><?= br($it['ipi'], 2) ?></span>
            <span class="c-tot"><?= br($it['total'], 2) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$ultima): ?>
    <div class="continua">Continua</div>
    <?php else: ?>
    <div class="rod">
        <div class="rod-a">
            <div class="obs box">Observação :</div>
            <div class="vp box">Valor Pedido: <?= br($p['valor_pedido'], 2) ?></div>
            <div class="desc box">Desconto: <?= br($p['desconto'], 2) ?></div>
            <div class="tp box"><span>Total do Ped.:</span><span><?= br($p['total_ped'], 2) ?></span></div>
        </div>
        <div class="rod-b">
            <div class="texto box"><?= e($p['observacao']) ?></div>
            <div class="col">
                <div class="tg box"><span>Total Geral:</span><span><?= br($p['total_ped'], 2) ?></span></div>
                <div class="aut box">
                    <span>Autorização do Pedido</span>
                    <div class="assin"><?= e($p['autorizacao']) ?></div>
                </div>
            </div>
        </div>
        <div class="rod-c box" style="margin-top:1mm">
            <div class="v1"></div>
            <div class="v2" style="border-left:0.3mm solid #000">Engenharia</div>
            <div class="v3" style="border-left:0.3mm solid #000">Compras</div>
            <div class="v4" style="border-left:0.3mm solid #000">Fornecedor (De ACORDO - Data e Assinatura)</div>
        </div>
        <div class="rod-d box" style="margin-top:1mm">
            <div class="imp">Impresso em: <?= e($p['impresso_em']) ?></div>
            <div class="ret" style="border-left:0.3mm solid #000">Retornar o "DE ACORDO" deste Pedido <?= e($p['retorno_fone']) ?></div>
        </div>
        <div class="rod-e box">Condições Acessórias</div>
        <div class="rod-f box">
            <div class="aviso">TRABALHAMOS COM SISTEMA INTEGRADO, FAVOR MANDAR NOTA CONFORME O PEDIDO E CONSTAR O NÚMERO DO PEDIDO, ENDEREÇO DE ENTREGA E O CNO NA NOTA FISCAL.</div>
            <div class="assinaturas">
                <div>GESTOR DE CONTRATO</div>
                <div>GESTOR DE ENGENHARIA</div>
                <div>DIRETOR DE ENGENHARIA</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach;
endforeach; ?>

<?php if ($pedidos): ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
<?php endif; ?>
</body>
</html>
