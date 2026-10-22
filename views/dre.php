<?php
/*
 * DRE — Demonstrativo do Resultado do Exercicio
 * Financeiro > Fluxo de Caixa > DRE por Ano ($modo='ano') / DRE Mensal ('mes')
 *
 * Replica as abas "DRE Ano"/"DRE Mes" do Power BI a partir das procedures
 * originais (ver comentario em app/dre.php). Hierarquia exibida:
 *   Linha DRE (FormatoDRE) > GO_Titulo > SubGO_Titulo, com valor + % por coluna.
 * Linhas SubTotal=1 acumulam os niveis anteriores; "(=) Lucro Liquido" soma
 * tudo que esta mapeado em DRE_GR_CL; o restante cai em "Nao classificado".
 */
$MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$fmtV = fn(float $v): string => number_format($v, 2, ',', '.');
?>
<div class="pedidos-coluna">

<?php if (!$gerado): ?>
<!-- ===================== TELA DE PARAMETROS ===================== -->
<div class="rel-econ-loading" id="relLoading" hidden>
    <div class="rel-econ-loading-box">
        <div class="rel-econ-spinner"></div>
        <div class="rel-econ-loading-txt">Gerando relatório...</div>
        <div class="rel-econ-loading-sub">Consulta pesada — pode levar alguns minutos</div>
        <div class="rel-econ-barra"><div class="rel-econ-barra-fill"></div></div>
    </div>
</div>
<form class="rel-econ-filtro" method="get"
      onsubmit="document.getElementById('relLoading').hidden=false">
    <div class="rel-econ-titulo-form">DRE | Demonstrativo do Resultado do Exercício —
        <?= $modo === 'ano' ? 'por Ano' : 'Mensal' ?><?= $arquivada ? ' (Arquivada)' : '' ?></div>
    <?php if ($arquivada): ?>
        <div class="dre-nota" style="margin-bottom:8px">DRE Arquivada é a DRE calculada até o dia
            anterior (mesma fonte do Power BI — consulta rápida).</div>
    <?php endif; ?>

    <fieldset class="rel-econ-box">
        <legend>Período</legend>
        <?php if ($modo === 'ano'): ?>
            <label>Ano inicial
                <input type="number" name="ano_ini" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano_ini'] ?? date('Y')) ?>" required>
            </label>
            <label>Ano final
                <input type="number" name="ano_fim" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano_fim'] ?? date('Y')) ?>" required>
            </label>
            <div class="dre-nota">Consulta pesada — comece por 1 ano (limite de 10 anos por consulta).</div>
        <?php else: ?>
            <label>Ano
                <input type="number" name="ano" min="2000" max="<?= date('Y') + 1 ?>"
                       value="<?= (int) ($o['ano'] ?? date('Y')) ?>" required>
            </label>
            <div class="dre-meses">
                <?php foreach ($MESES as $i => $mn): ?>
                    <label><input type="checkbox" name="meses[]" value="<?= $i + 1 ?>"
                        <?= in_array($i + 1, (array) ($o['meses'] ?? range(1, 12)), true) ? 'checked' : '' ?>> <?= $mn ?></label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Empresa</legend>
        <div class="rel-econ-centros-lista">
            <?php foreach ($empresas as $e): ?>
                <label><input type="checkbox" name="empresas[]" value="<?= e($e['cod']) ?>"
                    <?= $e['cod'] === 'CGC' ? 'checked' : '' ?> onchange="dreMudaEmpresa()">
                    <?= e($e['rotulo'] . ($e['nome'] !== '' ? ' - ' . $e['nome'] : '')) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="dre-nota">Nenhuma marcada = todas (equivale ao "selecionar tudo" do BI).</div>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Núcleo</legend>
        <input type="text" id="listaNucDreF" placeholder="Filtrar núcleo..."
               oninput="dreAplica('listaNucDre')">
        <div class="rel-econ-centros-lista" id="listaNucDre">
            <label class="dre-opt-todos"><input type="checkbox" checked
                   onclick="dreTodos('listaNucDre')"> Todos</label>
            <?php foreach ($nucleos as $n): ?>
                <label class="dre-item" data-emps="<?= e($n['emps']) ?>">
                    <input type="checkbox" name="nucleos_sel[]" value="<?= e($n['chave']) ?>"
                           onchange="dreSyncTodos('listaNucDre')"> <?= e($n['rotulo']) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="rel-econ-box rel-econ-centros">
        <legend>Obra / Centro de resultado</legend>
        <input type="text" id="listaCenDreF" placeholder="Filtrar obra..."
               oninput="dreAplica('listaCenDre')">
        <div class="rel-econ-centros-lista" id="listaCenDre">
            <label class="dre-opt-todos"><input type="checkbox" checked
                   onclick="dreTodos('listaCenDre')"> Todos</label>
            <?php foreach ($centros as $c): ?>
                <label class="dre-item" data-emps="<?= e($c['emp']) ?>">
                    <input type="checkbox" name="centros_sel[]" value="<?= e($c['chave']) ?>"
                           onchange="dreSyncTodos('listaCenDre')"> <?= e($c['rotulo']) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="dre-nota">Nenhum marcado = todos os centros ativos (Vw_Centro_Ativo).</div>
    </fieldset>

    <div class="rel-econ-botoes">
        <button type="submit" name="gerar" value="1" class="btn">Gerar</button>
        <a href="<?= url() ?>" class="btn btn-secundario">Cancela</a>
    </div>
    <?php if ($erro !== ''): ?>
        <div class="alert-erro"><?= e($erro) ?></div>
    <?php endif; ?>
</form>
<script>
// Cascata: so mostra Nucleo/Obra vinculados as empresas marcadas
// (nenhuma empresa marcada = todas). Filtro de texto combina com a empresa.
function dreEmpSel() {
    var s = [];
    document.querySelectorAll('input[name="empresas[]"]:checked').forEach(function (c) {
        s.push(c.value);
    });
    return s;
}
function dreMudaEmpresa() {
    dreAplica('listaNucDre');
    dreAplica('listaCenDre');
}
function dreAplica(id) {
    var inp = document.getElementById(id + 'F');
    var txt = inp ? inp.value.toLowerCase() : '';
    var emp = dreEmpSel();
    document.querySelectorAll('#' + id + ' label.dre-item').forEach(function (l) {
        var okE = emp.length === 0 || (l.getAttribute('data-emps') || '').split(' ')
            .some(function (e) { return emp.indexOf(e) >= 0; });
        var okT = txt === '' || l.textContent.toLowerCase().indexOf(txt) >= 0;
        l.style.display = okE && okT ? '' : 'none';
        if (!okE) {
            l.querySelector('input').checked = false; // sai da lista: sai da selecao
        }
    });
    dreSyncTodos(id);
}
function dreTodos(id) {
    document.querySelectorAll('#' + id + ' label.dre-item input').forEach(function (x) {
        x.checked = false;
    });
    dreSyncTodos(id);
}
function dreSyncTodos(id) {
    var t = document.querySelector('#' + id + ' .dre-opt-todos input');
    var any = false;
    document.querySelectorAll('#' + id + ' label.dre-item input').forEach(function (x) {
        if (x.checked) { any = true; }
    });
    if (t) { t.checked = !any; }
}
// Aplica a cascata na abertura da tela (ex.: CGC ja vem marcada).
dreMudaEmpresa();
</script>

<?php else: ?>
<!-- ===================== RELATORIO ===================== -->
<div class="filtros" style="margin-bottom:8px">
    <a class="btn btn-secundario" href="<?= url($active) ?>">Nova consulta</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
    <?php if ($dados): ?>
    <a class="btn" href="<?= url($active) . '?' . e(http_build_query(array_merge($_GET, ['excel' => 1]))) ?>">Exportar para Excel</a>
    <?php endif; ?>
</div>

<?php if ($erro !== ''): ?>
<div class="alert-erro"><?= e($erro) ?></div>
<?php endif; ?>

<?php require ROOT . '/views/dre_relatorio.php'; ?>
<?php if ($dados): ?>
<script>
(function () {
    var toggle = function (id, abrir) {
        document.querySelectorAll('tr[data-pai="' + id + '"]').forEach(function (r) {
            r.classList.toggle('dre-hidden', !abrir);
            if (!abrir) {
                r.classList.remove('dre-aberto');
                if (r.hasAttribute('data-tog')) {
                    toggle(r.getAttribute('data-tog'), false);
                }
            }
        });
    };
    document.querySelectorAll('tr[data-tog]').forEach(function (t) {
        t.addEventListener('click', function () {
            toggle(t.getAttribute('data-tog'), t.classList.toggle('dre-aberto'));
        });
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>
</div>
