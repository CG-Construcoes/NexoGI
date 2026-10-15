<?php // Cadastro de Grupos de Centros — Financeiro > Fluxo de Caixa ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<?php if ($podeInserir || $edicao): ?>
<!-- ===================== NOVO / EDITAR GRUPO ===================== -->
<h2><?= $edicao ? 'Editar grupo #' . (int) $edicao['FCGrupo_Id'] : 'Novo grupo' ?></h2>
<form method="post" action="<?= url('fluxo-caixa-grupos') ?>" class="filtros" style="margin-bottom:24px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="id" value="<?= (int) $edicao['FCGrupo_Id'] ?>">
    <?php endif; ?>
    <div class="campo grow">
        <label for="fc-nome">Nome</label>
        <input type="text" id="fc-nome" name="nome" required maxlength="60"
               value="<?= e($edicao['FCGrupo_Nome'] ?? '') ?>">
    </div>
    <div class="campo grow">
        <label for="fc-desc">Descricao</label>
        <input type="text" id="fc-desc" name="descricao" maxlength="100"
               value="<?= e($edicao['FCGrupo_Descricao'] ?? '') ?>">
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Cadastrar' ?></button>
        <?php if ($edicao): ?>
        <a href="<?= url('fluxo-caixa-grupos') ?>" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<!-- ===================== LISTA DE GRUPOS ===================== -->
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px">
    <h2 style="margin:0">Grupos cadastrados</h2>
    <a href="<?= url('fluxo-caixa-grupos/relatorio') ?>" class="btn btn-secundario">Visualizar / Imprimir listagem</a>
</div>
<?php if ($grupos): ?>
<table class="tabela">
    <thead>
        <tr><th>ID</th><th>Nome</th><th>Descricao</th><th>Centros</th></tr>
    </thead>
    <tbody>
        <?php foreach ($grupos as $g):
            $sel = $grupoCentros && (int) $g['FCGrupo_Id'] === (int) $grupoCentros['FCGrupo_Id']; ?>
        <tr class="fc-grupo-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= (int) $g['FCGrupo_Id'] ?>" data-nome="<?= e($g['FCGrupo_Nome']) ?>">
            <td><?= (int) $g['FCGrupo_Id'] ?></td>
            <td><?= e($g['FCGrupo_Nome']) ?></td>
            <td><?= e($g['FCGrupo_Descricao'] ?? '') ?></td>
            <td><?= (int) $g['QtdeCentros'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum grupo cadastrado.</p>
<?php endif; ?>

<?php if ($grupoCentros): ?>
<!-- ===================== CENTROS DO GRUPO (parte inferior) ===================== -->
<h2>Centros do grupo: <?= e($grupoCentros['FCGrupo_Nome']) ?></h2>
<form method="post" action="<?= url('fluxo-caixa-grupos') ?>" class="filtros" style="margin-bottom:16px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="incluir_centro">
    <input type="hidden" name="id" value="<?= (int) $grupoCentros['FCGrupo_Id'] ?>">
    <div class="campo grow">
        <label for="fc-centro">Adicionar centro de custo <small id="fc-sel-count" class="fc-sel-count"></small></label>
        <div class="fc-combo">
            <input type="text" id="fc-centro" name="centro"
                   placeholder="Digite para pesquisar e marque os centros" autocomplete="off">
            <div class="fc-combo-lista" id="fc-centro-lista" hidden>
                <?php foreach ($centros as $codc => $rotulo):
                    $oc = $ocupados[$codc] ?? null;
                    $mesmo = $oc && (int) $oc['FCGrupo_Id'] === (int) $grupoCentros['FCGrupo_Id']; ?>
                <div class="fc-combo-item<?= $oc ? ' fc-ocupado' : '' ?>"
                     data-texto="<?= e(mb_strtolower($rotulo)) ?>"
                     <?php if ($oc): ?>title="<?= e($mesmo ? 'Ja esta neste grupo'
                        : 'Ja pertence ao grupo "' . $oc['FCGrupo_Nome'] . '"') ?>"<?php endif; ?>><input type="checkbox" name="centros[]" value="<?= e($codc) ?>"<?= $oc ? ' disabled' : '' ?>> <?= e($rotulo) ?><?php if ($oc && !$mesmo): ?><span class="fc-combo-grupo">grupo: <?= e($oc['FCGrupo_Nome']) ?></span><?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn">Adicionar selecionados</button>
        <a href="<?= url('fluxo-caixa-grupos') ?>" class="btn btn-secundario">Voltar</a>
    </div>
</form>

<?php if ($podeAlterar): ?>
<form method="post" action="<?= url('fluxo-caixa-grupos') ?>" style="margin-bottom:16px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="incluir_avulso">
    <input type="hidden" name="id" value="<?= (int) $grupoCentros['FCGrupo_Id'] ?>">
    <button type="submit" class="btn btn-secundario">Incluir Valor Avulso</button>
    <small class="fc-av-hint">(item extra do grupo — o valor e preenchido na hora do calculo)</small>
</form>
<?php endif; ?>

<?php if ($centrosDoGrupo || $avulsosDoGrupo):
    $lista = [];
    foreach ($centrosDoGrupo as $codc) {
        $lista[$codc] = $centros[$codc] ?? $codc;
    }
    asort($lista); ?>
<table class="tabela" style="max-width:760px">
    <thead>
        <tr><th>Centro de custo</th><th style="width:90px"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($lista as $codc => $rotulo): ?>
        <tr>
            <td><?= e($rotulo) ?></td>
            <td class="acoes">
                <form method="post" action="<?= url('fluxo-caixa-grupos') ?>" style="display:inline">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_centro">
                    <input type="hidden" name="id" value="<?= (int) $grupoCentros['FCGrupo_Id'] ?>">
                    <input type="hidden" name="centro" value="<?= e($codc) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($avulsosDoGrupo as $av): ?>
        <tr class="fc-avulso">
            <td><em>Valor Avulso #<?= (int) $av['Avulso_Id'] ?></em>
                <small class="fc-av-hint">— valor informado na hora do calculo</small></td>
            <td class="acoes">
                <form method="post" action="<?= url('fluxo-caixa-grupos') ?>" style="display:inline">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_avulso">
                    <input type="hidden" name="id" value="<?= (int) $grupoCentros['FCGrupo_Id'] ?>">
                    <input type="hidden" name="avulso_id" value="<?= (int) $av['Avulso_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum centro de custo no grupo.</p>
<?php endif; ?>
<script>
(function () {
    var input = document.getElementById('fc-centro');
    var lista = document.getElementById('fc-centro-lista');
    var contador = document.getElementById('fc-sel-count');
    var itens = lista.querySelectorAll('.fc-combo-item');
    function filtrar() {
        var t = input.value.toLowerCase().trim();
        var visiveis = 0;
        itens.forEach(function (it) {
            var ok = t === '' || it.dataset.texto.indexOf(t) !== -1;
            it.style.display = ok ? '' : 'none';
            if (ok) { visiveis++; }
        });
        lista.hidden = visiveis === 0;
    }
    function contar() {
        var n = lista.querySelectorAll('input[type=checkbox]:checked').length;
        contador.textContent = n ? '(' + n + ' selecionado' + (n > 1 ? 's' : '') + ')' : '';
    }
    input.addEventListener('focus', filtrar);
    input.addEventListener('input', filtrar);
    input.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { lista.hidden = true; }
    });
    lista.addEventListener('change', function (ev) {
        if (ev.target.type === 'checkbox') { contar(); }
    });
    lista.addEventListener('mousedown', function (ev) {
        var it = ev.target.closest('.fc-combo-item');
        if (!it || it.classList.contains('fc-ocupado')) { return; }
        if (ev.target.type === 'checkbox') { return; }
        ev.preventDefault();
        var cb = it.querySelector('input[type=checkbox]');
        if (cb) {
            cb.checked = !cb.checked;
            contar();
        }
    });
    input.addEventListener('blur', function () {
        setTimeout(function () { lista.hidden = true; }, 150);
    });
    contar();
})();
</script>
<?php endif; ?>

<!-- ===================== MENU DE CONTEXTO (botao direito) ===================== -->
<form id="fcFormExcluir" method="post" action="<?= url('fluxo-caixa-grupos') ?>" hidden>
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="id" value="">
</form>
<div id="fcMenu" class="fc-menu" hidden>
    <?php if ($podeAlterar): ?>
    <button type="button" id="fcMenuEditar">Editar</button>
    <?php endif; ?>
    <?php if ($podeExcluir): ?>
    <button type="button" id="fcMenuExcluir" class="fc-danger">Excluir</button>
    <?php endif; ?>
</div>
<?php if ($podeAlterar || $podeExcluir): ?>
<script>
(function () {
    var menu = document.getElementById('fcMenu');
    var formDel = document.getElementById('fcFormExcluir');
    var btnEditar = document.getElementById('fcMenuEditar');
    var btnExcluir = document.getElementById('fcMenuExcluir');
    var urlTela = '<?= e(url('fluxo-caixa-grupos')) ?>';
    document.querySelectorAll('.fc-grupo-row').forEach(function (tr) {
        <?php if ($podeAlterar): ?>
        tr.addEventListener('click', function () {
            location.href = urlTela + '?centros=' + tr.dataset.id;
        });
        <?php endif; ?>
        tr.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
            formDel.querySelector('[name=id]').value = tr.dataset.id;
            if (btnEditar) {
                btnEditar.onclick = function () {
                    location.href = urlTela + '?editar=' + tr.dataset.id;
                };
            }
            if (btnExcluir) {
                btnExcluir.onclick = function () {
                    if (confirm('Excluir o grupo "' + tr.dataset.nome +
                            '"? Os centros associados tambem serao removidos.')) {
                        formDel.submit();
                    }
                };
            }
            menu.style.left = Math.min(ev.clientX, innerWidth - 160) + 'px';
            menu.style.top = Math.min(ev.clientY, innerHeight - 90) + 'px';
            menu.hidden = false;
        });
    });
    document.addEventListener('click', function () { menu.hidden = true; });
    document.addEventListener('contextmenu', function (ev) {
        if (!ev.target.closest || !ev.target.closest('.fc-grupo-row')) menu.hidden = true;
    });
})();
</script>
<?php endif; ?>
