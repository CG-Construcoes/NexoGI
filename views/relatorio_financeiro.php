<?php // Relatorio Financeiro — Financeiro > Relatorios ?>
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
      onsubmit="relAjustarRadios(); document.getElementById('relLoading').hidden=false">
<script>
function relFocoTexto(inp, nomeCheck, idRadio) {
    if (!document.querySelector('input[name="' + nomeCheck + '[]"]:checked')) {
        document.getElementById(idRadio).checked = true;
    }
}
function relAjustarRadios() {
    [['centros_sel', 'centroSel'], ['nucleos_sel', 'nucleoSel']].forEach(function (p) {
        var marc = document.querySelectorAll('input[name="' + p[0] + '[]"]:checked');
        if (marc.length) {
            document.getElementById(p[1]).checked = true;
        }
    });
}
</script>
    <div class="rel-econ-titulo-form">Relatório Financeiro</div>

    <div class="rel-econ-linha">
        <label for="base">Base</label>
        <select id="base" name="base">
            <option value="MGA">MGA</option>
            <option value="CONV">CONV</option>
        </select>
    </div>

    <div class="rel-econ-linha">
        <label for="empresa">Empresa</label>
        <select id="empresa" name="empresa" required>
            <?php foreach ($empresas as $emp): ?>
            <option value="<?= e($emp['Empresa_Cod']) ?>"
                <?= trim($emp['Empresa_Cod']) === 'CGC' ? 'selected' : '' ?>>
                <?= e(trim($emp['Empresa_Cod']) . ' - ' . trim((string) $emp['Empresa_Nome'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Período</legend>
        <label>de</label>
        <input type="date" name="data_ini" required>
        <label>a</label>
        <input type="date" name="data_fim" required>
    </fieldset>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Síntese</legend>
            <label><input type="checkbox" name="sin_classe" value="1" checked> Sintético em Classe</label>
            <label><input type="checkbox" name="sin_assunto" value="1" checked> Sintético em Assunto</label>
            <label><input type="checkbox" name="sin_grupo" value="1" checked> Sintético em Grupo</label>
            <label><input type="checkbox" name="analitico" value="1"
                <?= !empty($opcoes['analitico']) ? 'checked' : '' ?>> Analítico (movimentos)</label>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Grupos</legend>
            <label><input type="checkbox" name="sec_receitas" value="1" checked> Receitas</label>
            <label><input type="checkbox" name="sec_despesas" value="1" checked> Despesas</label>
            <label><input type="checkbox" name="sec_sociedade" value="1" checked> Recursos em Sociedade</label>
            <label><input type="checkbox" name="sec_outros" value="1" checked> Outros</label>
        </fieldset>
    </div>

    <fieldset class="rel-econ-box">
        <legend>Opções</legend>
        <label><input type="checkbox" name="det_centro" value="1"> Detalhar por Centro</label>
        <label><input type="checkbox" name="apenas_periodo" value="1" checked> Detalhar Apenas Período</label>
        <label><input type="checkbox" name="resumo_agente" value="1" checked> Resumo por Agente Financeiro</label>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Núcleo</legend>
        <label><input type="radio" name="nucleo_op" value="todos" checked> Todos</label>
        <label><input type="radio" name="nucleo_op" value="apenas" id="nucleoApenas"> Apenas</label>
        <input type="text" name="nucleo" list="listaNucleosRel" autocomplete="off"
               placeholder="selecione o núcleo" onfocus="relFocoTexto(this, 'nucleos_sel', 'nucleoApenas')">
        <datalist id="listaNucleosRel">
            <?php foreach ($nucleos as $n): ?>
            <option value="<?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <label><input type="radio" name="nucleo_op" value="selecionados" id="nucleoSel"> Selecionados:</label>
        <div class="rel-econ-centros-lista" id="listaNucleosCheck">
            <?php foreach ($nucleos as $n): ?>
            <label><input type="checkbox" name="nucleos_sel[]"
                   value="<?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?>"
                   onclick="document.getElementById('nucleoSel').checked=true">
                <?= e(trim($n['Nucleo_Cod']) . ' - ' . trim($n['Nucleo_Nome'])) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Centro de Negócio</legend>
        <label><input type="checkbox" name="inc_inativos" value="1"
               <?= !empty($opcoes['inc_inativos']) ? 'checked' : '' ?>
               onchange="this.form.submit()"> Incluir inativos</label>
        <label><input type="radio" name="centro_op" value="todos" checked> Todos</label>
        <label><input type="radio" name="centro_op" value="apenas" id="centroApenas"> Apenas</label>
        <input type="text" name="centro" list="listaCentrosRel" autocomplete="off"
               placeholder="selecione o centro" onfocus="relFocoTexto(this, 'centros_sel', 'centroApenas')">
        <datalist id="listaCentrosRel">
            <?php foreach ($centros as $c): ?>
            <option value="<?= e($c['Centro_Cod'] . ' - ' . trim($c['Centro_Nome'])) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <label><input type="radio" name="centro_op" value="selecionados" id="centroSel"> Selecionados:</label>
        <div class="rel-econ-centros-lista" id="listaCentrosCheck">
            <?php foreach ($centros as $c): ?>
            <label><input type="checkbox" name="centros_sel[]"
                   value="<?= e($c['Centro_Cod'] . ' - ' . trim($c['Centro_Nome'])) ?>"
                   onclick="document.getElementById('centroSel').checked=true">
                <?= e($c['Centro_Cod'] . ' - ' . trim($c['Centro_Nome'])) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
</form>
<script>
// Digitar no campo "Apenas" filtra a lista de checkboxes "Selecionados" (contem).
(function () {
    var pares = [['centro', 'listaCentrosCheck'], ['nucleo', 'listaNucleosCheck']];
    pares.forEach(function (p) {
        var inp = document.querySelector('input[name="' + p[0] + '"]');
        var lista = document.getElementById(p[1]);
        if (!inp || !lista) { return; }
        inp.addEventListener('input', function () {
            var t = inp.value.toLowerCase();
            lista.querySelectorAll('label').forEach(function (l) {
                l.style.display = l.textContent.toLowerCase().indexOf(t) !== -1 ? '' : 'none';
            });
        });
    });
})();
</script>

<?php else: ?>
<!-- ===================== RELATORIO ===================== -->
<div class="filtros" style="margin-bottom:8px">
    <a class="btn btn-secundario" href="<?= url('relatorio-financeiro') ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
</div>

<?php if ($erro): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php endif; ?>

<?php if ($dados && !$dados['blocos']): ?>
<p style="color:#666">Nenhum movimento encontrado no período.</p>
<?php endif; ?>

<?php if ($dados):
    $cols = $dados['apenas_periodo'] ? 4 : 6;
    $linha = function ($cls, $rotulo, $ant, $per) use ($dados) {
        echo '<tr class="' . $cls . '"><td></td><td></td><td>' . $rotulo . '</td>';
        if (!$dados['apenas_periodo']) {
            echo '<td class="col-valor">' . e(number_format($ant, 2, ',', '.')) . '</td>';
        }
        echo '<td class="col-valor">' . e(number_format($per, 2, ',', '.')) . '</td>';
        if (!$dados['apenas_periodo']) {
            echo '<td class="col-valor">' . e(number_format($ant + $per, 2, ',', '.')) . '</td>';
        }
        echo '</tr>';
    };
    $sinC = !empty($opcoes['sin_classe']);
    $sinA = !empty($opcoes['sin_assunto']);
    $sinG = !empty($opcoes['sin_grupo']);
    if (!$sinC && !$sinA && !$sinG) { $sinC = $sinA = $sinG = true; }
    $analitico = !empty($opcoes['analitico']);
    foreach ($dados['blocos'] as $centroNome => $secoes):
        $saldoAnt = 0.0; $saldoPer = 0.0;
        if ($dados['mostrar_saldo']) {
            foreach ($secoes as $s2) {
                if ($s2['id'] === 'receitas' || $s2['id'] === 'despesas') {
                    $saldoAnt += $s2['ant']; $saldoPer += $s2['per'];
                }
            }
        }
        ?>
<div class="rel-econ">
    <div class="rel-econ-cab">
        <div class="rel-econ-empresa"><?= e($dados['empresa_nome'] ?? $dados['base']) ?><?php
            if (!empty($dados['empresa_cnpj'])): ?> CNPJ: <?= e($dados['empresa_cnpj']) ?><?php endif; ?>
            <span class="rel-econ-centro">Centro: <?= $centroNome !== ''
                ? e($centroNome) : e($dados['centros_desc']) ?>
            | Núcleo: <?= e($dados['nucleos_desc'] ?? 'Todos') ?></span></div>
        <?php if ($centroNome === '' && count($dados['nucleos_lista'] ?? []) > 1): ?>
        <div class="rel-econ-centros-sel"><?= e(implode(' | ', array_map('trim', $dados['nucleos_lista']))) ?></div>
        <?php endif; ?>
        <?php if ($centroNome === '' && count($dados['centros_lista']) > 1): ?>
        <div class="rel-econ-centros-sel"><?= e(implode(' | ', array_map('trim', $dados['centros_lista']))) ?></div>
        <?php endif; ?>
        <div class="rel-econ-titulo">Relatório Financeiro no Período de
            <?= e(data_br($dados['data_ini'])) ?> a <?= e(data_br($dados['data_fim'])) ?></div>
        <div class="rel-econ-impresso">Impresso em: <?= e(date('d/m/Y H:i:s')) ?></div>
        <div class="rel-econ-centros-sel"><?= e($dados['filtro_mascara'] ?? '(*).99.9999') ?></div>
    </div>
    <table class="tabela rel-econ-tab">
        <thead>
            <tr>
                <th>Data</th>
                <th>Nº Mvto</th>
                <th>Discriminação</th>
                <?php if (!$dados['apenas_periodo']): ?>
                <th class="col-valor">Acum. Anterior</th>
                <?php endif; ?>
                <th class="col-valor">Período</th>
                <?php if (!$dados['apenas_periodo']): ?>
                <th class="col-valor">Acumulado</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($secoes as $sec): ?>
            <tr class="rel-secao"><td colspan="<?= $cols ?>"><?= e($sec['titulo']) ?></td></tr>
            <?php foreach ($sec['classes'] as $c):
                if ($sinC) $linha('rel-classe', e($c['cod']) . ' - ' . e($c['titulo']), $c['ant'], $c['per']);
                foreach ($c['assuntos'] as $a) {
                    if ($sinA) $linha('rel-assunto', e($a['cod']) . ' - ' . e($a['titulo']), $a['ant'], $a['per']);
                    if ($sinG) {
                        foreach ($a['gos'] as $g) {
                            $linha('rel-folha', e($a['cod']) . ' - ' . e($g['cod']) . ' - ' . e($g['titulo']),
                                $g['ant'], $g['per']);
                            if ($analitico) {
                                foreach ($g['movs'] ?? [] as $mv) {
                                    echo '<tr class="rel-mov"><td>' . e(data_br($mv['data']))
                                        . '</td><td>' . e($mv['num']) . '</td><td>'
                                        . e($mv['hist']) . '</td>';
                                    if (!$dados['apenas_periodo']) {
                                        echo '<td class="col-valor">'
                                            . ($mv['ant'] ? e(number_format($mv['valor'], 2, ',', '.')) : '')
                                            . '</td>';
                                    }
                                    echo '<td class="col-valor">'
                                        . (!$mv['ant'] ? e(number_format($mv['valor'], 2, ',', '.')) : '')
                                        . '</td>';
                                    if (!$dados['apenas_periodo']) {
                                        echo '<td class="col-valor"></td>';
                                    }
                                    echo '</tr>';
                                }
                            }
                        }
                    }
                }
            endforeach; ?>
            <tr class="rel-total"><td></td><td></td>
                <td><?= e(['outros' => 'Total Outros', 'sociedade' => 'Total Recursos da Sociedade'][$sec['id']]
                    ?? 'Total ' . $sec['titulo']) ?></td>
                <?php if (!$dados['apenas_periodo']): ?>
                <td class="col-valor"><?= e(number_format($sec['ant'], 2, ',', '.')) ?></td>
                <?php endif; ?>
                <td class="col-valor"><?= e(number_format($sec['per'], 2, ',', '.')) ?></td>
                <?php if (!$dados['apenas_periodo']): ?>
                <td class="col-valor"><?= e(number_format($sec['ant'] + $sec['per'], 2, ',', '.')) ?></td>
                <?php endif; ?>
            </tr>
            <?php if ($dados['mostrar_saldo'] && $sec['id'] === 'despesas'):
                $linha('rel-saldo', 'SALDO', $saldoAnt, $saldoPer);
            endif; ?>
        <?php endforeach; ?>
        <?php
        $ga = 0.0; $gp = 0.0;
        foreach ($secoes as $sec) {
            $ga += $sec['ant']; $gp += $sec['per'];
        }
        $linha('rel-geral', 'Total Geral', $ga, $gp); ?>
        </tbody>
    </table>

    <?php if (!empty($opcoes['resumo_agente']) && $dados['agentes']): ?>
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
<?php endforeach; endif; ?>

<?php endif; ?>
</div>
