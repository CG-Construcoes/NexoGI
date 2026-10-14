<?php /* Fluxo de Caixa — impressao dos Relatorios Financeiros dos grupos marcados.
        Filtros fixos: sinteticos Sim, todas as secoes, Apenas Periodo, Resumo por
        Agente Financeiro. Cada (base, empresa) dos centros do grupo gera um bloco. */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatório Financeiro — Fluxo de Caixa</title>
<link rel="stylesheet" href="<?= url('static/css/style.css') ?>">
<style>
    body { background: #fff; }
    .fcs-imp-grupo { page-break-before: always; }
    .fcs-imp-grupo:first-child { page-break-before: auto; }
    .fcs-imp-titulo { font-size: 16px; font-weight: 700; color: #1e3a5f;
        margin: 18px 0 4px; }
    .fcs-imp-centros { font-size: 11px; color: #445; margin-bottom: 10px;
        line-height: 1.5; }
    .fcs-imp-erro { color: #b3261e; font-size: 13px; margin: 6px 0; }
    .fcs-imp-btn { margin: 10px 0 14px; }
    @media print { .fcs-imp-btn { display: none; } }
</style>
</head>
<body>
<div class="fcs-imp-btn">
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php elseif (!$relatorios): ?>
<p class="vazio">Nenhum relatorio para imprimir.</p>
<?php else:
    $linha = function ($cls, $rotulo, $per) {
        echo '<tr class="' . $cls . '"><td></td><td></td><td>' . $rotulo . '</td>'
            . '<td class="col-valor">' . e(number_format($per, 2, ',', '.')) . '</td></tr>';
    };
    foreach ($relatorios as $rel): ?>
<div class="fcs-imp-grupo">
    <div class="fcs-imp-titulo">Grupo: <?= e($rel['grupo']) ?></div>
    <?php if ($rel['centros']): ?>
    <div class="fcs-imp-centros">Centros de custo: <?= e(implode(' | ', $rel['centros'])) ?></div>
    <?php endif; ?>
    <?php foreach ($rel['erros'] as $msg): ?>
    <div class="fcs-imp-erro"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach ($rel['dados'] as $dados): ?>
<div class="rel-econ">
    <div class="rel-econ-cab">
        <div class="rel-econ-empresa"><?= e($dados['empresa_nome'] ?? $dados['base']) ?><?php
            if (!empty($dados['empresa_cnpj'])): ?> CNPJ: <?= e($dados['empresa_cnpj']) ?><?php endif; ?>
            <span class="rel-econ-centro">Centro: <?= e($dados['centros_desc']) ?>
            | Núcleo: <?= e($dados['nucleos_desc']) ?></span></div>
        <div class="rel-econ-titulo">Relatório Financeiro no Período de
            <?= e(data_br($dados['data_ini'])) ?> a <?= e(data_br($dados['data_fim'])) ?></div>
        <div class="rel-econ-impresso">Impresso em: <?= e(date('d/m/Y H:i:s')) ?></div>
        <div class="rel-econ-centros-sel"><?= e($dados['filtro_mascara']) ?></div>
    </div>
    <table class="tabela rel-econ-tab">
        <thead>
            <tr>
                <th>Data</th>
                <th>Nº Mvto</th>
                <th>Discriminação</th>
                <th class="col-valor">Período</th>
            </tr>
        </thead>
        <tbody>
        <?php $saldoPer = 0.0;
        foreach ($dados['blocos'] as $secoes):
            foreach ($secoes as $s2) {
                if ($s2['id'] === 'receitas' || $s2['id'] === 'despesas') {
                    $saldoPer += $s2['per'];
                }
            }
            foreach ($secoes as $sec): ?>
            <tr class="rel-secao"><td colspan="4"><?= e($sec['titulo']) ?></td></tr>
            <?php foreach ($sec['classes'] as $c):
                $linha('rel-classe', e($c['cod']) . ' - ' . e($c['titulo']), $c['per']);
                foreach ($c['assuntos'] as $a) {
                    $linha('rel-assunto', e($a['cod']) . ' - ' . e($a['titulo']), $a['per']);
                    foreach ($a['gos'] as $g) {
                        $linha('rel-folha', e($a['cod']) . ' - ' . e($g['cod']) . ' - ' . e($g['titulo']),
                            $g['per']);
                    }
                }
            endforeach; ?>
            <tr class="rel-total"><td></td><td></td>
                <td><?= $sec['id'] === 'outros' ? 'Total Outros' : 'Total ' . e($sec['titulo']) ?></td>
                <td class="col-valor"><?= e(number_format($sec['per'], 2, ',', '.')) ?></td>
            </tr>
            <?php if ($sec['id'] === 'despesas'):
                $linha('rel-saldo', 'SALDO', $saldoPer);
            endif; ?>
            <?php endforeach;
            $gp = 0.0;
            foreach ($secoes as $sec) {
                $gp += $sec['per'];
            }
            $linha('rel-geral', 'Total Geral', $gp);
        endforeach; ?>
        </tbody>
    </table>

    <?php if ($dados['agentes']): ?>
    <div class="rel-econ-titulo" style="margin-top:18px">Resumo por Agente Financeiro</div>
    <table class="tabela rel-econ-tab">
        <thead>
            <tr><th>Tipo</th><th>Agente</th><th>Conta</th><th class="col-valor">Valor</th></tr>
        </thead>
        <tbody>
        <?php $totAg = 0.0; foreach ($dados['agentes'] as $ag): $totAg += $ag['valor']; ?>
            <tr>
                <td><?= e($ag['tipo']) ?></td>
                <td><?= e($ag['agente']) ?></td>
                <td><?= e($ag['conta']) ?></td>
                <td class="col-valor"><?= e(number_format($ag['valor'], 2, ',', '.')) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="rel-total"><td colspan="3">Total</td>
                <td class="col-valor"><?= e(number_format($totAg, 2, ',', '.')) ?></td></tr>
        </tbody>
    </table>
    <?php endif; ?>
</div>
    <?php endforeach; ?>
</div>
    <?php endforeach; endif; ?>

<script>
if (window.self === window.top) {
    window.addEventListener('load', function () { window.print(); });
}
</script>
</body>
</html>
