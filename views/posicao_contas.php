<?php // Posicao de Contas — Financeiro > Caixas e Bancos ?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">A consulta pode levar alguns instantes</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="if(this.gerar&&this.gerar.value){document.getElementById('relLoading').hidden=false}">
    <div class="rel-econ-titulo-form">Posição de Contas</div>

    <div class="rel-econ-linha">
        <label for="base">Base</label>
        <select id="base" name="base">
            <option value="MGA" <?= $base === 'MGA' ? 'selected' : '' ?>>MGA</option>
            <option value="CONV" <?= $base === 'CONV' ? 'selected' : '' ?>>CONV</option>
        </select>
    </div>

    <div class="rel-econ-linha">
        <label for="empresa">Empresa</label>
        <select id="empresa" name="empresa" required onchange="this.form.submit()">
            <?php foreach ($empresas as $emp): ?>
            <option value="<?= e($emp['Empresa_Cod']) ?>"
                <?= trim($emp['Empresa_Cod']) === $opcoes['empresa'] ? 'selected' : '' ?>>
                <?= e(trim($emp['Empresa_Cod']) . ' - ' . trim((string) $emp['Empresa_Nome'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="rel-econ-linha">
        <label for="nucleo">Núcleo</label>
        <select id="nucleo" name="nucleo">
            <option value="">Todos os Núcleos</option>
            <?php foreach ($nucleos as $n): ?>
            <option value="<?= e(trim($n['Nucleo_Cod'])) ?>"
                <?= trim($n['Nucleo_Cod']) === $opcoes['nucleo'] ? 'selected' : '' ?>>
                <?= e(trim($n['Nucleo_Cod']) . ' - ' . trim((string) $n['Nucleo_Nome'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="rel-econ-linha">
        <label for="data">Data</label>
        <input type="date" id="data" name="data" required
               value="<?= e($opcoes['data']) ?>">
    </div>

    <div class="rel-econ-linha">
        <label for="tipo">Tipo de Agente</label>
        <select id="tipo" name="tipo" onchange="this.form.submit()">
            <?php foreach ($tipos as $t): ?>
            <option value="<?= e(trim($t['AgenteFinanceiroTipo_Cod'])) ?>"
                <?= trim($t['AgenteFinanceiroTipo_Cod']) === $opcoes['tipo'] ? 'selected' : '' ?>>
                <?= e(trim($t['AgenteFinanceiroTipo_Titulo'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="margin:0 0 10px 80px">
        <label style="font-size:13px;color:#33405c">
            <input type="checkbox" name="conciliados" value="1"
                <?= !empty($opcoes['conciliados']) ? 'checked' : '' ?>> Movimentos Conciliados</label>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Opções</legend>
        <label><input type="radio" name="agente_op" value="todos"
            <?= $opcoes['agente'] === '' ? 'checked' : '' ?>> Todos os Agentes e Contas</label>
        <label><input type="radio" name="agente_op" value="um" id="agenteUm"
            <?= $opcoes['agente'] !== '' ? 'checked' : '' ?>> Um Agente e Todas as Contas</label>
        <select name="agente" onchange="document.getElementById('agenteUm').checked=true">
            <option value="">selecione o agente</option>
            <?php foreach ($agentes as $a): ?>
            <option value="<?= e(trim($a['AgenteFinanceiro_Cod'])) ?>"
                <?= trim($a['AgenteFinanceiro_Cod']) === $opcoes['agente'] ? 'selected' : '' ?>>
                <?= e(trim($a['AgenteFinanceiro_Cod']) . ' - ' . trim((string) $a['AgenteFinanceiro_RazaoSocial'])) ?></option>
            <?php endforeach; ?>
        </select>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
</form>

<?php else: ?>
<!-- ===================== RELATORIO ===================== -->
<div class="filtros" style="margin-bottom:8px">
    <a class="btn btn-secundario" href="<?= url('posicao-contas') ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php endif; ?>

<?php if ($dados && !$dados['grupos']): ?>
<p style="color:#666">Nenhuma conta encontrada.</p>
<?php endif; ?>

<?php if ($dados && $dados['grupos']):
    $fmt  = fn($v) => number_format(abs((float) $v), 2, ',', '.');
    $dc   = fn($v) => (float) $v < 0 ? 'D' : 'C';
    $totG = $dados['tot']; ?>
<div class="rel-econ" style="max-width:none">
    <div class="rel-econ-cab">
        <div class="rel-econ-empresa">Empresa: <?= e($dados['empresa_nome'] ?: $dados['empresa']) ?>
            <span class="rel-econ-centro">Núcleo: <?= e($dados['nucleo_desc']) ?></span></div>
        <div class="rel-econ-titulo">Posição Agente Financeiro
            <?= $dados['conciliados'] ? '- VALORES CONCILIADOS' : '' ?>
            em <?= e(data_br($dados['data'])) ?></div>
        <div class="rel-econ-impresso">Impresso em: <?= e(date('d/m/Y H:i:s')) ?></div>
    </div>
    <table class="tabela rel-econ-tab">
        <thead>
            <tr>
                <th>Conta Nº</th>
                <th>Agência</th>
                <th class="col-valor">Saldo Anterior</th>
                <th>D/C</th>
                <th class="col-valor">Débitos</th>
                <th class="col-valor">Créditos</th>
                <th class="col-valor">Saldo Atual</th>
                <th>D/C</th>
                <th class="col-valor">Limite Crédito</th>
                <th class="col-valor">Valor Aplicado</th>
                <th class="col-valor">Disponível</th>
                <th>D/C</th>
                <th>Última Conc</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dados['grupos'] as $g): ?>
            <tr class="rel-secao"><td colspan="13"><?= e($g['tipo'] . ' - ' . $g['agente'] . ' - ' . $g['nome']) ?></td></tr>
            <?php foreach ($g['contas'] as $c): ?>
            <tr>
                <td><?= e($c['conta']) ?></td>
                <td><?= e($c['agencia']) ?></td>
                <td class="col-valor"><?= e($fmt($c['ant'])) ?></td>
                <td><?= e($dc($c['ant'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['deb'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['cred'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['atual'])) ?></td>
                <td><?= e($dc($c['atual'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['limite'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['aplicado'])) ?></td>
                <td class="col-valor"><?= e($fmt($c['disponivel'])) ?></td>
                <td><?= e($dc($c['disponivel'])) ?></td>
                <td><?= e(data_br($c['ultima'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="rel-total">
                <td colspan="2">Total: <?= e($g['nome']) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['ant'])) ?></td>
                <td><?= e($dc($g['tot']['ant'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['deb'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['cred'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['atual'])) ?></td>
                <td><?= e($dc($g['tot']['atual'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['limite'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['aplicado'])) ?></td>
                <td class="col-valor"><?= e($fmt($g['tot']['disponivel'])) ?></td>
                <td><?= e($dc($g['tot']['disponivel'])) ?></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
            <tr class="rel-total">
                <td colspan="2">Total: <?= e($dados['empresa']) ?></td>
                <td class="col-valor"><?= e($fmt($totG['ant'])) ?></td>
                <td><?= e($dc($totG['ant'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['deb'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['cred'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['atual'])) ?></td>
                <td><?= e($dc($totG['atual'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['limite'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['aplicado'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['disponivel'])) ?></td>
                <td><?= e($dc($totG['disponivel'])) ?></td>
                <td></td>
            </tr>
            <tr class="rel-geral rel-total">
                <td colspan="2">Total Geral</td>
                <td class="col-valor"><?= e($fmt($totG['ant'])) ?></td>
                <td><?= e($dc($totG['ant'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['deb'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['cred'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['atual'])) ?></td>
                <td><?= e($dc($totG['atual'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['limite'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['aplicado'])) ?></td>
                <td class="col-valor"><?= e($fmt($totG['disponivel'])) ?></td>
                <td><?= e($dc($totG['disponivel'])) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
