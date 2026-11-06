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
$sortD = fn(?string $d): string => $d ? substr($d, 0, 10) : '';
$sortDT = fn(?string $d): string => $d ? str_replace(' ', 'T', substr($d, 0, 16)) : '';
?>
<div class="pedidos-coluna">

<!-- ===================== FILTROS (compactos, estilo ERP) ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">Consulta pesada nas duas bases</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="flw-filtro2" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <?php foreach ($erros as $err): ?>
        <div class="alert-erro"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="flw-rows">
        <div class="flw-c"><span>De</span>
            <input type="date" name="data_ini" value="<?= e($f['data_ini']) ?>" required></div>
        <div class="flw-c"><span>Até</span>
            <input type="date" name="data_fim" value="<?= e($f['data_fim']) ?>" required></div>
        <div class="flw-c w-emp"><span>Empresa</span>
            <input type="text" name="empresa" list="dlEmpresa" autocomplete="off"
                   value="<?= e($f['empresa']) ?>" placeholder="Filtrar">
            <datalist id="dlEmpresa">
                <?php foreach ($combos['empresas'] ?? [] as $n): ?>
                    <option value="<?= e($n) ?>"></option>
                <?php endforeach; ?>
            </datalist></div>
        <div class="flw-c"><span>Filial</span>
            <select name="filial"><option value="">Todas</option>
                <?php foreach ($combos['filiais'] ?? [] as $k => $n): ?>
                    <option value="<?= e(explode('|', $k)[1]) ?>" <?= $f['filial'] === explode('|', $k)[1] ? 'selected' : '' ?>><?= e($k . ' · ' . $n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c w-emp"><span>Centro de Negócio</span>
            <input type="text" name="centro" list="dlCentro" autocomplete="off"
                   value="<?= e($f['centro']) ?>" placeholder="Filtrar">
            <datalist id="dlCentro">
                <?php foreach (array_unique($combos['centros'] ?? []) as $n): ?>
                    <option value="<?= e($n) ?>"></option>
                <?php endforeach; ?>
            </datalist></div>
        <div class="flw-c"><span>Núcleo</span>
            <select name="nucleo"><option value="">Todos</option>
                <?php foreach ($combos['nucleos'] ?? [] as $c => $n): ?>
                    <option value="<?= e($c) ?>" <?= $f['nucleo'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c"><span>Solicitante</span>
            <select name="solicitante"><option value="">Todos</option>
                <?php foreach ($combos['solicitantes'] ?? [] as $c => $n): ?>
                    <option value="<?= e($c) ?>" <?= $f['solicitante'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c w-num"><span>Nº Solicitação</span>
            <input type="text" name="numero" value="<?= e($f['numero']) ?>" placeholder="397"></div>
        <div class="flw-c"><span>Autorizadas</span>
            <select name="autorizadas">
                <option value="" <?= $f['autorizadas'] === '' ? 'selected' : '' ?>>Todas</option>
                <option value="apenas" <?= $f['autorizadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                <option value="exceto" <?= $f['autorizadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
            </select></div>
        <div class="flw-c"><span>Recusadas</span>
            <select name="recusadas">
                <option value="" <?= $f['recusadas'] === '' ? 'selected' : '' ?>>Todas</option>
                <option value="apenas" <?= $f['recusadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                <option value="exceto" <?= $f['recusadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
            </select></div>
        <div class="flw-c w-ins"><span>Insumo/Sub</span>
            <input type="text" name="insumo" value="<?= e($f['insumo']) ?>" placeholder="008994.000"></div>
        <div class="flw-c w-emp"><span>Especificação</span>
            <input type="text" name="texto" value="<?= e($f['texto']) ?>" placeholder="Contém"></div>
        <div class="flw-c"><span>Comprador</span>
            <select name="comprador"><option value="">Todos</option>
                <?php foreach ($combos['compradores'] ?? [] as $c => $n): ?>
                    <option value="<?= e($c) ?>" <?= $f['comprador'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c"><span>Tipo Solicitação</span>
            <select name="tipsol"><option value="">Todos</option>
                <?php foreach ($combos['tipsol'] ?? [] as $c => $n): ?>
                    <option value="<?= e($c) ?>" <?= $f['tipsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c"><span>Departamento</span>
            <select name="depsol"><option value="">Todos</option>
                <?php foreach ($combos['depsol'] ?? [] as $c => $n): ?>
                    <option value="<?= e($c) ?>" <?= $f['depsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c flw-exec-wrap"><span>&nbsp;</span>
            <button type="submit" name="gerar" value="1" class="btn flw-exec">Executar</button></div>
    </div>

    <div class="flw-linha2">
        <div class="flw-grupo">
            <b>Situação</b>
            <label class="flw-s-ab"><input type="checkbox" name="sit_ab" <?= $f['sit']['ab'] ? 'checked' : '' ?>> Em Aberto</label>
            <label class="flw-s-co"><input type="checkbox" name="sit_co" <?= $f['sit']['co'] ? 'checked' : '' ?>> Em Cotação</label>
            <label class="flw-s-pe"><input type="checkbox" name="sit_pe" <?= $f['sit']['pe'] ? 'checked' : '' ?>> Em Pedido</label>
            <label class="flw-s-ar"><input type="checkbox" name="sit_ar" <?= $f['sit']['ar'] ? 'checked' : '' ?>> Em AR</label>
            <label class="flw-s-arp"><input type="checkbox" name="sit_arp" <?= $f['sit']['arp'] ? 'checked' : '' ?>> Em AR Parcial</label>
        </div>
        <div class="flw-grupo">
            <b>Agrupar</b>
            <label><input type="radio" name="agrupar" value="empresa" <?= $f['agrupar'] === 'empresa' ? 'checked' : '' ?>> Empresa</label>
            <label><input type="radio" name="agrupar" value="centro" <?= $f['agrupar'] === 'centro' ? 'checked' : '' ?>> Centro</label>
            <label><input type="radio" name="agrupar" value="solicitacao" <?= $f['agrupar'] === 'solicitacao' ? 'checked' : '' ?>> Solicitação</label>
        </div>
        <div class="flw-grupo">
            <label><input type="checkbox" name="nao_concluidas" <?= $f['nao_concluidas'] ? 'checked' : '' ?>> Não concluídas</label>
            <label>Atraso mín. <input type="number" name="atraso_min" min="0" value="<?= (int) ($_GET['atraso_min'] ?? 0) ?>" style="width:52px"></label>
        </div>
    </div>
</form>

<style>
.flw-filtro2 { background: var(--bg-panel, #fff); border: 1px solid var(--border-color);
    border-radius: var(--radius-lg); padding: 10px 14px; margin: -26px 0 12px; box-shadow: var(--shadow-sm); }
.flw-rodape-itens { margin-left: auto; align-self: center; font-size: 12px; color: var(--text-muted); }
.flw-itens { display: none; }
.tabela-responsiva { margin-top: 0; min-height: 200px; overflow-y: auto; }
.tabela-responsiva .flw-tab thead th { position: sticky; top: 0; z-index: 3;
    background: var(--bg-subtle); box-shadow: 0 1px 0 var(--border-color); }
body.dark .tabela-responsiva .flw-tab thead th { background: #1b2333; }
.flw-rodape { position: sticky; bottom: 0; z-index: 5; }
.flw-rows { display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: flex-end; }
.flw-c { display: flex; flex-direction: column; }
.flw-c > span { font-size: 9.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .3px; color: var(--text-muted); margin-bottom: 1px; }
.flw-c input, .flw-c select { font-size: 11.5px; padding: 3px 6px; height: 26px;
    border: 1px solid var(--border-strong); border-radius: 5px; background: var(--bg-input, #fff); color: var(--text-main); }
.flw-c input[type=date] { width: 124px; }
.flw-c select { max-width: 150px; }
.flw-c.w-emp input { width: 150px; }
.flw-c.w-num input { width: 64px; }
.flw-c.w-ins input { width: 96px; }
.flw-linha2 { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 22px;
    margin-top: 4px; padding-top: 6px; border-top: 1px solid var(--border-color); font-size: 11.5px; }
.flw-exec-wrap { margin-left: auto; }
.flw-exec { height: 26px; padding: 0 26px; }
.flw-linha2 .flw-grupo { display: flex; align-items: center; gap: 10px; }
.flw-linha2 .flw-grupo b { font-size: 10px; text-transform: uppercase; color: var(--text-muted); }
.flw-linha2 .flw-grupo label { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.flw-linha2 .flw-grupo input[type=number] { font-size: 11.5px; padding: 2px 4px; height: 24px;
    border: 1px solid var(--border-strong); border-radius: 5px; background: var(--bg-input, #fff); color: var(--text-main); }
.flw-tab tr.flw-grupo td { background: #dbe7fb; font-weight: 600; font-size: 10px;
    color: #1e3a8a; border-bottom: 1px solid #b8c4d4; }
body.dark .flw-tab tr.flw-grupo td { background: #1e2f52; color: #9db9f0; }
.flw-s-ab  { color: #1e3a8a; font-weight: 700; }
.flw-s-co  { color: #a16207; font-weight: 700; }
.flw-s-pe  { color: #0e7490; font-weight: 700; }
.flw-s-ar  { color: #b91c1c; font-weight: 700; }
.flw-s-arp { color: #16a34a; font-weight: 700; }
body.dark .flw-s-ab  { color: #93c5fd; }
body.dark .flw-s-co  { color: #fbbf24; }
body.dark .flw-s-pe  { color: #22d3ee; }
body.dark .flw-s-ar  { color: #f87171; }
body.dark .flw-s-arp { color: #4ade80; }
.flw-exec { margin-left: auto; padding: 5px 26px; }
.flw-tab th:first-child, .flw-tab td:first-child { width: 44px; max-width: 44px; padding-left: 4px; padding-right: 4px; }
.flw-tab td:first-child .badge { padding: 2px 6px; font-size: 10px; }
body.dark .flw-filtro2 { background: #171e2e; }
body.dark .flw-c input, body.dark .flw-c select { background: #1b2333; }
body.dark .flw-linha2 .flw-grupo input[type=number] { background: #1b2333; }
</style>

<?php if (!$gerado): ?>
<!-- ===================== GRID VAZIO (padrao) ===================== -->
<div class="tabela-responsiva">
<table class="tabela flw-tab">
    <thead>
        <tr>
            <th>Base</th><th>Solicitação</th><th class="ord-desc" data-dir="desc">Atraso</th><th>Nº Coleta</th>
            <th>Pedido</th><th>Data Solic</th><th>Entrega Sol.</th><th>A</th><th>Sit</th>
            <th>Solicitante</th><th>Especificação</th>
            <th>Und</th><th class="col-valor">Quantidade</th><th>Insumo/Sub</th>
            <th>Autorizante</th><th>Data Aut.</th><th>Con.</th>
            <th class="col-valor">Saldo</th><th>Atraso Compra</th><th>Comprador</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="20" class="tabela-vazio">Informe os filtros acima e clique em <b>Executar</b> para listar as solicitações.</td></tr>
    </tbody>
</table>
</div>
<?php else: ?>
<!-- ===================== RESULTADO ===================== -->
<div class="flw-cx" id="flwCtx">
    <button type="button" data-acao="solic">Abrir Solicitação</button>
    <button type="button" data-acao="coleta">Abrir Coleta</button>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = null;
    var cx = document.getElementById('flwCtx');
    var bS = document.getElementById('flwBtnSolic'),
        bC = document.getElementById('flwBtnColeta');
    function selecionar(tr) {
        document.querySelectorAll('#flwGrid tr.flw-sel').forEach(function (r) { r.classList.remove('flw-sel'); });
        tr.classList.add('flw-sel');
        sel = tr.dataset;
        bS.disabled = !sel.solic;
        bC.disabled = !sel.coleta;
        bC.title = sel.coleta ? '' : 'Linha sem coleta';
    }
    function abrir(oQue) {
        if (!sel) { return; }
        if (oQue === 'solic' && sel.solic) {
            open('<?= e(url('solicitacao')) ?>?base=' + sel.base + '&n=' + sel.solic, '_blank');
        } else if (oQue === 'coleta' && sel.coleta) {
            open('<?= e(url('coleta')) ?>?base=' + sel.base + '&n=' + sel.coleta, '_blank');
        }
    }
    document.querySelectorAll('#flwGrid tr[data-solic]').forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) { return; }
            selecionar(tr);
        });
        tr.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
            selecionar(tr);
            var itens = cx.querySelectorAll('button');
            itens[0].disabled = !sel.solic;
            itens[1].disabled = !sel.coleta;
            cx.style.left = ev.clientX + 'px';
            cx.style.top = ev.clientY + 'px';
            cx.classList.add('aberto');
        });
    });
    document.addEventListener('click', function () { cx.classList.remove('aberto'); });
    cx.addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-acao]');
        if (b && !b.disabled) { abrir(b.dataset.acao); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { cx.classList.remove('aberto'); }
    });
    bS.onclick = function () { abrir('solic'); };
    bC.onclick = function () { abrir('coleta'); };
});
</script>
<?php foreach ($erros as $err): ?>
    <div class="alert-erro"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if (!$linhas): ?>
    <p style="color:#666">Nenhum item encontrado no período/filtros informados.</p>
<?php else: ?>
<div class="tabela-responsiva">
<table class="tabela flw-tab" id="flwGrid">
    <thead>
        <tr>
            <th>Base</th><th>Solicitação</th><th class="ord-desc" data-dir="desc">Atraso</th><th>Nº Coleta</th>
            <th>Pedido</th><th>Data Solic</th><th>Entrega Sol.</th><th>A</th><th>Sit</th>
            <th>Solicitante</th><th>Especificação</th>
            <th>Und</th><th class="col-valor">Quantidade</th><th>Insumo/Sub</th>
            <th>Autorizante</th><th>Data Aut.</th><th>Con.</th>
            <th class="col-valor">Saldo</th><th>Atraso Compra</th><th>Comprador</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($grupos as $g): ?>
        <tr class="flw-grupo"><td colspan="20">= <?= e($g['rotulo']) ?></td></tr>
        <?php foreach ($g['linhas'] as $l): ?>
        <tr data-solic="<?= (int) $l['Solic_Numero'] ?>"
            data-coleta="<?= (int) ($l['Coleta_Numero'] ?? 0) ?>"
            data-base="<?= e($l['Base']) ?>">
            <td data-sort="<?= e($l['Base']) ?>"><span class="badge badge-<?= $l['Base'] === 'CONV' ? 'ativo' : 'pendente' ?>"><?= e($l['Base']) ?></span></td>
            <td data-sort="<?= (int) $l['Solic_Numero'] * 1000 + (int) $l['SolicItem_Numero'] ?>"><?= e($l['Solicitacao_ID']) ?></td>
            <td class="col-valor <?= $l['Atrasada'] ? 'flw-atraso' : ($l['Atraso'] !== null && $l['Atraso'] <= 0 ? 'flw-prazo' : '') ?>"
                data-sort="<?= $l['Atraso'] !== null ? (int) $l['Atraso'] : '' ?>"><?= $l['Atraso'] !== null ? (int) $l['Atraso'] : '' ?></td>
            <td data-sort="<?= e($l['Coleta_Numero'] ?? '') ?>"><?= e($l['Coleta_Numero'] ?? '') ?></td>
            <td data-sort="<?= e($l['Pedido_CentroNumeroC'] ?? '') ?>"><?= e($l['Pedido_CentroNumeroC'] ?? '') ?></td>
            <td data-sort="<?= e($sortD($l['Solic_Data'])) ?>"><?= e($fmtD($l['Solic_Data'])) ?></td>
            <td data-sort="<?= e($sortD($l['SolicItem_DataEntrega'])) ?>"><?= e($fmtD($l['SolicItem_DataEntrega'])) ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>" data-sort="<?= $l['Autorizada'] ? 'A' : '' ?>"><?= $l['Autorizada'] ? 'A' : '' ?></td>
            <td class="flw-sit-<?= e($l['Sit']) ?>" data-sort="<?= e($l['Sit']) ?>"><?= e($l['Sit']) ?></td>
            <td data-sort="<?= e(mb_strtolower($l['Solicitante_Nome'] ?? '')) ?>"><?= e($l['Solicitante_Nome'] ?? '') ?></td>
            <td class="flw-esp" data-sort="<?= e(mb_strtolower(trim($l['SolicItem_EspecificacaoGeral'] ?? ''))) ?>"
                title="<?= e($l['SolicItem_Obs'] ?? '') ?>"><?= e(trim($l['SolicItem_EspecificacaoGeral'] ?? '')) ?></td>
            <td data-sort="<?= e($l['Unid_Cod'] ?? '') ?>"><?= e($l['Unid_Cod'] ?? '') ?></td>
            <td class="col-valor" data-sort="<?= (float) $l['SolicItem_Quantidade'] ?>"><?= e($fmtQ((float) $l['SolicItem_Quantidade'])) ?></td>
            <td data-sort="<?= e($l['Insumo_CodC']) ?>"><?= e($l['Insumo_CodC']) ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>" data-sort="<?= e(mb_strtolower($l['Usuario_Instrucao'] ?? '')) ?>"><?= e($l['Usuario_Instrucao'] ?? '') ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>" data-sort="<?= e($sortDT($l['Instrucao_Data'])) ?>"><?= e($fmtDT($l['Instrucao_Data'])) ?></td>
            <td data-sort="<?= trim((string) $l['Solic_Concluido']) === 'S' ? '1' : '0' ?>"><?= trim((string) $l['Solic_Concluido']) === 'S' ? '✔' : '' ?></td>
            <td class="col-valor" data-sort="<?= (float) $l['Saldo'] ?>"><?= e($fmtQ((float) $l['Saldo'])) ?></td>
            <td class="col-valor" data-sort="<?= $l['AtrasoCompra'] !== null ? (int) $l['AtrasoCompra'] : '' ?>"><?= $l['AtrasoCompra'] !== null ? (int) $l['AtrasoCompra'] : '' ?></td>
            <td data-sort="<?= e(mb_strtolower($l['Solicitante_Nome_Geral'] ?? '')) ?>"><?= e($l['Solicitante_Nome_Geral'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="flw-rodape">
    <a class="btn btn-secundario" href="<?= url($active) ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
    <button type="button" class="btn btn-secundario" id="flwBtnSolic" disabled title="Clique numa linha primeiro">Solicitação</button>
    <button type="button" class="btn btn-secundario" id="flwBtnColeta" disabled title="Linha sem coleta">Coleta</button>
    <span class="flw-rodape-itens"><?= count($linhas) ?> item(ns)</span>
</div>
<style>
.flw-rodape { display: flex; gap: 8px; justify-content: flex-start;
    padding: 6px 14px; margin-top: 4px;
    background: var(--bg-panel, #fff); border: 1px solid var(--border-color);
    border-radius: var(--radius-lg); }
body.dark .flw-rodape { background: #171e2e; }
.flw-cx { display: none; position: fixed; z-index: 999; min-width: 170px;
    background: var(--bg-panel, #fff); border: 1px solid var(--border-color);
    border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 4px; }
.flw-cx.aberto { display: block; }
.flw-cx button { display: block; width: 100%; text-align: left; border: 0;
    background: none; padding: 7px 12px; font-size: 12px; border-radius: 5px;
    cursor: pointer; color: var(--text-main); }
.flw-cx button:hover:not(:disabled) { background: var(--bg-subtle); }
.flw-cx button:disabled { opacity: .4; cursor: default; }
body.dark .flw-cx { background: #1b2333; border-color: #38486a; }
</style>
<div class="flw-hscroll" id="flwHScroll"><div class="flw-hscroll-inner"></div></div>
<script>
// Clique no titulo reordena o grid: somente as linhas dentro de cada banda
// (as bandas/grupos ficam fixas). Celula vazia sempre por ultimo.
(function () {
    var tab = document.querySelector('.flw-tab');
    if (!tab) { return; }
    var tbody = tab.tBodies[0];
    tab.querySelectorAll('th').forEach(function (th, idx) {
        th.addEventListener('click', function () {
            var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
            tab.querySelectorAll('th').forEach(function (t) {
                t.dataset.dir = ''; t.classList.remove('ord-asc', 'ord-desc');
            });
            th.dataset.dir = dir;
            th.classList.add(dir === 'asc' ? 'ord-asc' : 'ord-desc');
            var key = function (tr) {
                var c = tr.cells[idx];
                return c ? (c.dataset.sort || '') : '';
            };
            var cmp = function (a, b) {
                var va = key(a), vb = key(b);
                if (va === '') { return vb === '' ? 0 : 1; }
                if (vb === '') { return -1; }
                var na = parseFloat(va), nb = parseFloat(vb);
                var r = (!isNaN(na) && !isNaN(nb)) ? na - nb
                        : va.localeCompare(vb, 'pt-BR');
                return dir === 'asc' ? r : -r;
            };
            var segs = [], cur = null;
            Array.from(tbody.rows).forEach(function (tr) {
                if (tr.classList.contains('flw-grupo')) {
                    cur = { band: tr, rows: [] };
                    segs.push(cur);
                } else {
                    if (!cur) { cur = { band: null, rows: [] }; segs.push(cur); }
                    cur.rows.push(tr);
                }
            });
            // bandas nao se movem: ordena so as linhas dentro de cada grupo
            segs.forEach(function (s) { s.rows.sort(cmp); });
            segs.forEach(function (s) {
                if (s.band) { tbody.appendChild(s.band); }
                s.rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });
    // Clique na banda recolhe/expande as linhas do grupo
    tbody.querySelectorAll('tr.flw-grupo').forEach(function (band) {
        band.addEventListener('click', function () {
            var fecha = !band.classList.contains('flw-fech');
            band.classList.toggle('flw-fech', fecha);
            var tr = band.nextElementSibling;
            while (tr && !tr.classList.contains('flw-grupo')) {
                tr.classList.toggle('flw-oculta', fecha);
                tr = tr.nextElementSibling;
            }
        });
    });
    // Rodape fixo: barra horizontal sincronizada com a tabela. Fica visivel
    // apenas enquanto a barra nativa (no fim da tabela) esta fora da tela.
    var wrap = tab.closest('.tabela-responsiva');
    var bar = document.getElementById('flwHScroll');
    if (wrap && bar) {
        var inner = bar.firstElementChild;
        var sinc = false;
        var atual = function () {
            var r = wrap.getBoundingClientRect();
            bar.style.left = r.left + 'px';
            bar.style.width = r.width + 'px';
            inner.style.width = wrap.scrollWidth + 'px';
            var transborda = wrap.scrollWidth > wrap.clientWidth + 1;
            var naTela = r.bottom > 0 && r.top < window.innerHeight;
            var nativaVisivel = r.bottom <= window.innerHeight;
            bar.style.display = (transborda && naTela && !nativaVisivel) ? 'block' : 'none';
        };
        wrap.addEventListener('scroll', function () {
            if (!sinc) { sinc = true; bar.scrollLeft = wrap.scrollLeft; sinc = false; }
        });
        bar.addEventListener('scroll', function () {
            if (!sinc) { sinc = true; wrap.scrollLeft = bar.scrollLeft; sinc = false; }
        });
        // Grid ocupa ate o rodape fixo no fim da viewport.
        var ajusta = function () {
            var rod = document.querySelector('.flw-rodape');
            var topo = wrap.getBoundingClientRect().top;
            var rodAlt = rod ? rod.offsetHeight + 16 : 60;
            wrap.style.height = Math.max(200, window.innerHeight - topo - rodAlt) + 'px';
        };
        ajusta();
        window.addEventListener('resize', ajusta);
        window.addEventListener('resize', atual);
        window.addEventListener('scroll', atual, { passive: true });
        wrap.style.marginBottom = '22px';
        atual();
    }
})();
</script>
<?php endif; ?>
<?php endif; ?>
</div>
