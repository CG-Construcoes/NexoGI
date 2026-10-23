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
<form class="rel-econ-filtro flw-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">Follow-Up das Solicitações de Compra</div>
    <?php foreach ($erros as $err): ?>
        <div class="alert-erro"><?= e($err) ?></div>
    <?php endforeach; ?>

    <div class="flw-fgrid">
        <fieldset class="flw-box">
            <legend>Período das solicitações</legend>
            <div class="flw-campos">
                <div class="flw-campo"><span>De</span>
                    <input type="date" name="data_ini" value="<?= e($f['data_ini']) ?>" required></div>
                <div class="flw-campo"><span>Até</span>
                    <input type="date" name="data_fim" value="<?= e($f['data_fim']) ?>" required></div>
            </div>
        </fieldset>

        <fieldset class="flw-box">
            <legend>Autorizadas / Recusadas</legend>
            <div class="flw-campos">
                <div class="flw-campo"><span>Autorizadas</span>
                    <select name="autorizadas">
                        <option value="" <?= $f['autorizadas'] === '' ? 'selected' : '' ?>>Todas</option>
                        <option value="apenas" <?= $f['autorizadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                        <option value="exceto" <?= $f['autorizadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
                    </select></div>
                <div class="flw-campo"><span>Recusadas</span>
                    <select name="recusadas">
                        <option value="" <?= $f['recusadas'] === '' ? 'selected' : '' ?>>Todas</option>
                        <option value="apenas" <?= $f['recusadas'] === 'apenas' ? 'selected' : '' ?>>Apenas</option>
                        <option value="exceto" <?= $f['recusadas'] === 'exceto' ? 'selected' : '' ?>>Exceto</option>
                    </select></div>
            </div>
        </fieldset>

        <fieldset class="flw-box">
            <legend>Agrupar por</legend>
            <div class="flw-radios">
                <label><input type="radio" name="agrupar" value="empresa" <?= $f['agrupar'] === 'empresa' ? 'checked' : '' ?>> Empresa</label>
                <label><input type="radio" name="agrupar" value="centro" <?= $f['agrupar'] === 'centro' ? 'checked' : '' ?>> Centro</label>
                <label><input type="radio" name="agrupar" value="solicitacao" <?= $f['agrupar'] === 'solicitacao' ? 'checked' : '' ?>> Solicitação</label>
            </div>
        </fieldset>

        <fieldset class="flw-box span2">
            <legend>Empresa e localização</legend>
            <div class="flw-campos">
                <div class="flw-campo"><span>Empresa</span>
                    <input type="text" name="empresa" list="dlEmpresa" autocomplete="off"
                           value="<?= e($f['empresa']) ?>" placeholder="Digite para filtrar">
                    <datalist id="dlEmpresa">
                        <?php foreach ($combos['empresas'] ?? [] as $n): ?>
                            <option value="<?= e($n) ?>"></option>
                        <?php endforeach; ?>
                    </datalist></div>
                <div class="flw-campo"><span>Filial</span>
                    <select name="filial"><option value="">Todas</option>
                        <?php foreach ($combos['filiais'] ?? [] as $k => $n): ?>
                            <option value="<?= e(explode('|', $k)[1]) ?>" <?= $f['filial'] === explode('|', $k)[1] ? 'selected' : '' ?>><?= e($k . ' · ' . $n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="flw-campo"><span>Centro de Negócio</span>
                    <input type="text" name="centro" list="dlCentro" autocomplete="off"
                           value="<?= e($f['centro']) ?>" placeholder="Digite para filtrar">
                    <datalist id="dlCentro">
                        <?php foreach (array_unique($combos['centros'] ?? []) as $n): ?>
                            <option value="<?= e($n) ?>"></option>
                        <?php endforeach; ?>
                    </datalist></div>
                <div class="flw-campo"><span>Núcleo</span>
                    <select name="nucleo"><option value="">Todos</option>
                        <?php foreach ($combos['nucleos'] ?? [] as $c => $n): ?>
                            <option value="<?= e($c) ?>" <?= $f['nucleo'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="flw-campo"><span>Nº Solicitação</span>
                    <input type="text" name="numero" value="<?= e($f['numero']) ?>" placeholder="Ex.: 397"></div>
            </div>
        </fieldset>

        <fieldset class="flw-box">
            <legend>Situação</legend>
            <div class="flw-checks">
                <label><input type="checkbox" name="sit_ab" <?= $f['sit']['ab'] ? 'checked' : '' ?>> Em Aberto</label>
                <label><input type="checkbox" name="sit_co" <?= $f['sit']['co'] ? 'checked' : '' ?>> Em Cotação</label>
                <label><input type="checkbox" name="sit_pe" <?= $f['sit']['pe'] ? 'checked' : '' ?>> Em Pedido</label>
                <label><input type="checkbox" name="sit_ar" <?= $f['sit']['ar'] ? 'checked' : '' ?>> Em AR</label>
                <label><input type="checkbox" name="sit_arp" <?= $f['sit']['arp'] ? 'checked' : '' ?>> Em AR Parcial</label>
            </div>
            <div class="flw-extras">
                <label><input type="checkbox" name="nao_concluidas" <?= $f['nao_concluidas'] ? 'checked' : '' ?>> Não concluídas</label>
                <label>Atraso mín. (dias)
                    <input type="number" name="atraso_min" min="0"
                           value="<?= (int) ($_GET['atraso_min'] ?? 0) ?>"></label>
            </div>
        </fieldset>

        <fieldset class="flw-box span2">
            <legend>Pessoas e classificação</legend>
            <div class="flw-campos">
                <div class="flw-campo"><span>Solicitante</span>
                    <select name="solicitante"><option value="">Todos</option>
                        <?php foreach ($combos['solicitantes'] ?? [] as $c => $n): ?>
                            <option value="<?= e($c) ?>" <?= $f['solicitante'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="flw-campo"><span>Comprador</span>
                    <select name="comprador"><option value="">Todos</option>
                        <?php foreach ($combos['compradores'] ?? [] as $c => $n): ?>
                            <option value="<?= e($c) ?>" <?= $f['comprador'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="flw-campo"><span>Tipo Solicitação</span>
                    <select name="tipsol"><option value="">Todos</option>
                        <?php foreach ($combos['tipsol'] ?? [] as $c => $n): ?>
                            <option value="<?= e($c) ?>" <?= $f['tipsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="flw-campo"><span>Departamento</span>
                    <select name="depsol"><option value="">Todos</option>
                        <?php foreach ($combos['depsol'] ?? [] as $c => $n): ?>
                            <option value="<?= e($c) ?>" <?= $f['depsol'] === $c ? 'selected' : '' ?>><?= e($n) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
        </fieldset>

        <fieldset class="flw-box">
            <legend>Caracterização</legend>
            <div class="flw-campos flw-campos-1">
                <div class="flw-campo"><span>Insumo/Sub (código)</span>
                    <input type="text" name="insumo" value="<?= e($f['insumo']) ?>" placeholder="Ex.: 008994.000"></div>
                <div class="flw-campo"><span>Especificação (contém)</span>
                    <input type="text" name="texto" value="<?= e($f['texto']) ?>" placeholder="Texto na especificação"></div>
            </div>
        </fieldset>
    </div>

    <div class="flw-acoes">
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
            <th>Base</th><th>Solicitação</th><th class="ord-desc" data-dir="desc">Atraso</th><th>Nº Coleta</th>
            <th>Pedido</th><th>Entrega Sol.</th><th>A</th><th>Sit</th>
            <th>Solicitante</th><th>Especificação</th>
            <th>Und</th><th class="col-valor">Quantidade</th><th>Insumo/Sub</th>
            <th>Data Solic</th><th>Autorizante</th><th>Data Aut.</th><th>Con.</th>
            <th class="col-valor">Saldo</th><th>Atraso Compra</th><th>Comprador</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($grupos as $g): ?>
        <tr class="flw-grupo"><td colspan="20">= <?= e($g['rotulo']) ?></td></tr>
        <?php foreach ($g['linhas'] as $l): ?>
        <tr class="<?= $l['Atrasada'] ? 'flw-atrasada' : '' ?>">
            <td data-sort="<?= e($l['Base']) ?>"><span class="badge badge-<?= $l['Base'] === 'CONV' ? 'ativo' : 'pendente' ?>"><?= e($l['Base']) ?></span></td>
            <td data-sort="<?= (int) $l['Solic_Numero'] * 1000 + (int) $l['SolicItem_Numero'] ?>"><?= e($l['Solicitacao_ID']) ?></td>
            <td class="col-valor <?= $l['Atraso'] !== null && $l['Atraso'] <= 0 ? 'flw-prazo' : '' ?>"
                data-sort="<?= $l['Atraso'] !== null ? (int) $l['Atraso'] : '' ?>"><?= $l['Atraso'] !== null ? (int) $l['Atraso'] : '' ?></td>
            <td data-sort="<?= e($l['Coleta_Numero'] ?? '') ?>"><?= e($l['Coleta_Numero'] ?? '') ?></td>
            <td data-sort="<?= e($l['Pedido_CentroNumeroC'] ?? '') ?>"><?= e($l['Pedido_CentroNumeroC'] ?? '') ?></td>
            <td data-sort="<?= e($sortD($l['SolicItem_DataEntrega'])) ?>"><?= e($fmtD($l['SolicItem_DataEntrega'])) ?></td>
            <td class="<?= $l['Autorizada'] ? 'flw-aut' : '' ?>" data-sort="<?= $l['Autorizada'] ? 'A' : '' ?>"><?= $l['Autorizada'] ? 'A' : '' ?></td>
            <td class="flw-sit-<?= e($l['Sit']) ?>" data-sort="<?= e($l['Sit']) ?>"><?= e($l['Sit']) ?></td>
            <td data-sort="<?= e(mb_strtolower($l['Solicitante_Nome'] ?? '')) ?>"><?= e($l['Solicitante_Nome'] ?? '') ?></td>
            <td class="flw-esp" data-sort="<?= e(mb_strtolower(trim($l['SolicItem_EspecificacaoGeral'] ?? ''))) ?>"
                title="<?= e($l['SolicItem_Obs'] ?? '') ?>"><?= e(trim($l['SolicItem_EspecificacaoGeral'] ?? '')) ?></td>
            <td data-sort="<?= e($l['Unid_Cod'] ?? '') ?>"><?= e($l['Unid_Cod'] ?? '') ?></td>
            <td class="col-valor" data-sort="<?= (float) $l['SolicItem_Quantidade'] ?>"><?= e($fmtQ((float) $l['SolicItem_Quantidade'])) ?></td>
            <td data-sort="<?= e($l['Insumo_CodC']) ?>"><?= e($l['Insumo_CodC']) ?></td>
            <td data-sort="<?= e($sortD($l['Solic_Data'])) ?>"><?= e($fmtD($l['Solic_Data'])) ?></td>
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
})();
</script>
<?php endif; ?>
<?php endif; ?>
</div>
