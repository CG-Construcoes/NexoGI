<?php
/*
 * Follow-up das Solicitacoes de Compra
 * Suprimentos > Solicitacoes > Follow-up das Solicitacoes
 *
 * Replica o grid do Informakon (trace traces/Flw Solicitacoes.trc): itens de
 * solicitacao das bases dbConv + dbMGA unificadas, com banda de agrupamento
 * (empresa/centro/solicitacao), situacao, autorizacao, pedido, AR, saldo a
 * receber e atrasos. Cores: linha vermelha = entrega vencida com saldo;
 * celulas verdes = autorizacao.
 */
$fmtQ = fn(float $v): string => number_format($v, 4, ',', '.');
$fmtD = fn(?string $d): string => $d ? date('d/m/Y', strtotime(substr($d, 0, 10))) : '';
$fmtDT = fn(?string $d): string => $d ? date('d/m/Y H:i', strtotime(substr($d, 0, 16))) : '';
?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== FILTROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">Consulta pesada nas duas bases</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">Follow-Up das Solicitações de Compra</div>
    <?php foreach ($erros as $err): ?>
        <div class="alert-erro"><?= e($err) ?></div>
    <?php endforeach; ?>

    <fieldset class="rel-econ-box">
        <legend>Período das Solicitações</legend>
        <label>De <input type="date" name="data_ini" value="<?= e($f['data_ini']) ?>" required></label>
        <label>Até <input type="date" name="data_fim" value="<?= e($f['data_fim']) ?>" required></label>
    </fieldset>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Empresa</legend>
            <div class="rel-econ-linha">
                <select name="empresa">
                    <option value="">Todas</option>
                    <?php foreach ($combos['empresas'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['empresa'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="rel-econ-linha"><label>Filial
                <select name="filial"><option value="">Todas</option>
                    <?php foreach ($combos['filiais'] ?? [] as $k => $n): ?>
                        <option value="<?= e(explode('|', $k)[1]) ?>" <?= $f['filial'] === explode('|', $k)[1] ? 'selected' : '' ?>><?= e($k . ' · ' . $n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Centro de Negócio
                <select name="centro"><option value="">Todos</option>
                    <?php foreach ($combos['centros'] ?? [] as $k => $n):
                        $cc = explode('|', $k)[2]; ?>
                        <option value="<?= e($cc) ?>" <?= $f['centro'] === $cc ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Núcleo
                <select name="nucleo"><option value="">Todos</option>
                    <?php foreach ($combos['nucleos'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['nucleo'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Nº Solicitação
                <input type="text" name="numero" value="<?= e($f['numero']) ?>" placeholder="Ex.: 397">
            </label></div>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Pessoas / classificação</legend>
            <div class="rel-econ-linha"><label>Solicitante
                <select name="solicitante"><option value="">Todos</option>
                    <?php foreach ($combos['solicitantes'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['solicitante'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Comprador
                <select name="comprador"><option value="">Todos</option>
                    <?php foreach ($combos['compradores'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['comprador'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Tipo Solicitação
                <select name="tipsol"><option value="">Todos</option>
                    <?php foreach ($combos['tipsol'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['tipsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="rel-econ-linha"><label>Departamento
                <select name="depsol"><option value="">Todos</option>
                    <?php foreach ($combos['depsol'] ?? [] as $c => $n): ?>
                        <option value="<?= e($c) ?>" <?= $f['depsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
        </fieldset>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Caracterização</legend>
            <div class="rel-econ-linha"><label>Insumo/Sub (código)
                <input type="text" name="insumo" value="<?= e($f['insumo']) ?>" placeholder="Ex.: 008994.000">
            </label></div>
            <div class="rel-econ-linha"><label>Especificação (contém)
                <input type="text" name="texto" value="<?= e($f['texto']) ?>" placeholder="Texto na especificação">
            </label></div>
        </fieldset>

        <fieldset class="rel-econ-box">
            <legend>Situação</legend>
            <label><input type="checkbox" name="sit_ab" <?= $f['sit']['ab'] ? 'checked' : '' ?>> Em Aberto</label>
            <label><input type="checkbox" name="sit_co" <?= $f['sit']['co'] ? 'checked' : '' ?>> Em Cotação</label>
            <label><input type="checkbox" name="sit_pe" <?= $f['sit']['pe'] ? 'checked' : '' ?>> Em Pedido</label>
            <label><input type="checkbox" name="sit_ar" <?= $f['sit']['ar'] ? 'checked' : '' ?>> Em AR</label>
            <label><input type="checkbox" name="sit_arp" <?= $f['sit']['arp'] ? 'checked' : '' ?>> Em AR Parcial</label>
            <div class="dre-nota" style="margin-top:6px">
                <label><input type="checkbox" name="nao_concluidas" <?= $f['nao_concluidas'] ? 'checked' : '' ?>> Solicitações Não Concluídas</label>
                <label style="margin-left:10px">Atraso mín. (dias)
                    <input type="number" name="atraso_min" min="0" style="width:70px"
                           value="<?= (int) ($_GET['atraso_min'] ?? 0) ?>"></label>
            </div>
        </fieldset>
    </div>

    <div class="rel-econ-duas-col">
        <fieldset class="rel-econ-box">
            <legend>Autorizadas / Recusadas</legend>
            <label>Autorizadas
                <select name="autorizadas">
                    <option value="" <?= $f['autorizadas'] === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="apenas" <?= $f['autorizadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                    <option value="exceto" <?= $f['autorizadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
                </select></label>
            <label style="margin-left:14px">Recusadas
                <select name="recusadas">
                    <option value="" <?= $f['recusadas'] === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="apenas" <?= $f['recusadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                    <option value="exceto" <?= $f['recusadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
                </select></label>
        </fieldset>
        <fieldset class="rel-econ-box">
            <legend>Agrupar por</legend>
            <label><input type="radio" name="agrupar" value="empresa" <?= $f['agrupar'] === 'empresa' ? 'checked' : '' ?>> Empresa</label>
            <label><input type="radio" name="agrupar" value="centro" <?= $f['agrupar'] === 'centro' ? 'checked' : '' ?>> Centro</label>
            <label><input type="radio" name="agrupar" value="solicitacao" <?= $f['agrupar'] === 'solicitacao' ? 'checked' : '' ?>> Solicitação</label>
        </fieldset>
    </div>

    <div class="rel-econ-linha" style="margin-top:10px">
        <button type="submit" name="gerar" value="1" class="btn">Executar</button>
    </div>
</form>

<?php else: ?>
<!-- ===================== RESULTADO ===================== -->
<div class="filtros" style="margin-bottom:8px">
    <a class="btn btn-secundario" href="<?= url($active) ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
    <span style="margin-left:12px;color:#555"><?= count($linhas) ?> item(ns)</span>
</div>
<?php foreach ($erros as $err): ?>
    <div class="alert-erro"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if (!$linhas): ?>
    <p style="color:#666">Nenhum item encontrado no período/filtros informados.</p>
<?php else: ?>
<div class="tabela-responsiva">
<table class="tabela flw-tab">
    <thead>
        <tr>
            <th>Base</th><th>Solicitação</th><th>Atraso</th><th>Pedido</th>
            <th>Entrega Sol.</th><th>St</th><th>Solicitante</th><th>Especificação</th>
            <th>Und</th><th class="col-valor">Quantidade</th><th>Insumo/Sub</th>
            <th>Data Solic</th><th>Autorizante</th><th>Data Aut.</th><th>Con.</th>
            <th class="col-valor">Saldo</th><th>Atraso Compra</th><th>Comprador</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($grupos as $g): ?>
        <tr class="flw-grupo"><td colspan="18">= <?= e($g['rotulo']) ?></td></tr>
        <?php foreach ($g['linhas'] as $l): ?>
        <tr class="<?= $l['Atrasada'] ? 'flw-atrasada' : '' ?>">
            <td><span class="badge badge-<?= $l['Base'] === 'CONV' ? 'ativo' : 'pendente' ?>"><?= e($l['Base']) ?></span></td>
            <td><?= e($l['Solicitacao_ID']) ?></td>
            <td class="col-valor"><?= $l['Atraso'] !== null ? (int) $l['Atraso'] : '' ?></td>
            <td><?= e($l['Pedido_CentroNumeroC'] ?? '') ?></td>
            <td><?= e($fmtD($l['SolicItem_DataEntrega'])) ?></td>
            <td><?= e($l['Sit']) ?></td>
            <td><?= e($l['Solicitante_Nome'] ?? '') ?></td>
            <td class="flw-esp" title="<?= e($l['SolicItem_Obs'] ?? '') ?>"><?= e(trim($l['SolicItem_EspecificacaoGeral'] ?? '')) ?></td>
            <td><?= e($l['Unid_Cod'] ?? '') ?></td>
            <td class="col-valor"><?= e($fmtQ((float) $l['SolicItem_Quantidade'])) ?></td>
            <td><?= e($l['Insumo_CodC']) ?></td>
            <td><?= e($fmtD($l['Solic_Data'])) ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>"><?= e($l['Usuario_Instrucao'] ?? '') ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>"><?= e($fmtDT($l['Instrucao_Data'])) ?></td>
            <td><?= trim((string) $l['Solic_Concluido']) === 'S' ? '✔' : '' ?></td>
            <td class="col-valor"><?= e($fmtQ((float) $l['Saldo'])) ?></td>
            <td class="col-valor"><?= $l['AtrasoCompra'] !== null ? (int) $l['AtrasoCompra'] : '' ?></td>
            <td><?= e($l['Solicitante_Nome_Geral'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
