<?php // Grupos de Usuarios — Configuracoes (admin) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<!-- ===================== NOVO / EDITAR GRUPO ===================== -->
<h2><?= $edicao ? 'Editar grupo #' . (int) $edicao['UsGrupo_Id'] : 'Novo grupo de usuarios' ?></h2>
<form method="post" action="<?= url('grupos-usuarios') ?>" class="filtros" style="margin-bottom:24px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="id" value="<?= (int) $edicao['UsGrupo_Id'] ?>">
    <?php endif; ?>
    <div class="campo grow">
        <label for="ug-nome">Nome</label>
        <input type="text" id="ug-nome" name="nome" required maxlength="60"
               value="<?= e($edicao['UsGrupo_Nome'] ?? '') ?>">
    </div>
    <div class="campo grow">
        <label for="ug-desc">Descricao</label>
        <input type="text" id="ug-desc" name="descricao" maxlength="100"
               value="<?= e($edicao['UsGrupo_Descricao'] ?? '') ?>">
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Cadastrar' ?></button>
        <?php if ($edicao): ?>
        <a href="<?= url('grupos-usuarios') ?>" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<!-- ===================== LISTA DE GRUPOS ===================== -->
<h2>Grupos cadastrados</h2>
<?php if ($grupos): ?>
<table class="tabela">
    <thead>
        <tr><th>ID</th><th>Nome</th><th>Descricao</th><th>Telas</th><th>Membros</th></tr>
    </thead>
    <tbody>
        <?php foreach ($grupos as $g):
            $sel = $grupoSel && (int) $g['UsGrupo_Id'] === (int) $grupoSel['UsGrupo_Id']; ?>
        <tr class="fc-grupo-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= (int) $g['UsGrupo_Id'] ?>" data-nome="<?= e($g['UsGrupo_Nome']) ?>">
            <td><?= (int) $g['UsGrupo_Id'] ?></td>
            <td><?= e($g['UsGrupo_Nome']) ?></td>
            <td><?= e($g['UsGrupo_Descricao'] ?? '') ?></td>
            <td><?= (int) $g['QtdeTelas'] ?></td>
            <td><?= (int) $g['QtdeMembros'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum grupo cadastrado.</p>
<?php endif; ?>

<?php if ($grupoSel): ?>
<!-- ===================== PERMISSOES E MEMBROS DO GRUPO (parte inferior) ===================== -->
<h2>Telas e funcoes do grupo: <?= e($grupoSel['UsGrupo_Nome']) ?></h2>
<form method="post" action="<?= url('grupos-usuarios') ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="telas">
    <input type="hidden" name="id" value="<?= (int) $grupoSel['UsGrupo_Id'] ?>">
    <table class="tabela tabela-permissoes">
        <thead>
            <tr>
                <th>Tela</th>
                <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Acesso</th>
                <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Inclusao</th>
                <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Alteracao</th>
                <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Exclusao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($telasDisp as $t):
                $p = $permGrupo[$t['slug']] ?? []; ?>
            <tr>
                <td><?= e($t['nome']) ?></td>
                <?php foreach (PERM_ACOES as $a): ?>
                <td class="td-check"><input type="checkbox"
                       name="perm[<?= e($t['slug']) ?>][<?= $a ?>]" value="1"
                       <?= !empty($p[$a]) ? 'checked' : '' ?>></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:10px">
        <button type="submit" class="btn">Salvar permissoes</button>
        <a href="<?= url('grupos-usuarios') ?>" class="btn btn-secundario">Voltar</a>
    </div>
</form>

<h3 style="margin-top:24px">Usuarios neste grupo</h3>
<?php if ($membros): ?>
<table class="tabela" style="max-width:760px">
    <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Status</th></tr>
    </thead>
    <tbody>
        <?php foreach ($membros as $m): ?>
        <tr>
            <td><?= e($m['Nome']) ?></td>
            <td><?= e($m['Email']) ?></td>
            <td><span class="badge badge-<?= e($m['Status']) ?>"><?= e($m['Status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p class="vazio" style="margin-top:8px">A inclusao/remocao de usuarios e feita na tela
    <a href="<?= url('usuarios') ?>">Usuarios</a>.</p>
<?php else: ?>
<p class="vazio">Nenhum usuario neste grupo — atribua na tela
    <a href="<?= url('usuarios') ?>">Usuarios</a>.</p>
<?php endif; ?>
<?php endif; ?>

<!-- ===================== MENU DE CONTEXTO (botao direito) ===================== -->
<form id="ugFormExcluir" method="post" action="<?= url('grupos-usuarios') ?>" hidden>
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="id" value="">
</form>
<div id="ugMenu" class="fc-menu" hidden>
    <button type="button" id="ugMenuEditar">Editar</button>
    <button type="button" id="ugMenuExcluir" class="fc-danger">Excluir</button>
</div>
<script>
// checkbox no cabecalho da coluna marca/desmarca toda a coluna daquela tabela
function permColuna(headChk) {
    var col = headChk.closest('th').cellIndex;
    headChk.closest('table').querySelectorAll('tbody tr').forEach(function (tr) {
        var chk = tr.cells[col].querySelector('input[type=checkbox]');
        if (chk) chk.checked = headChk.checked;
    });
}
(function () {
    var menu = document.getElementById('ugMenu');
    var formDel = document.getElementById('ugFormExcluir');
    var btnEditar = document.getElementById('ugMenuEditar');
    var btnExcluir = document.getElementById('ugMenuExcluir');
    var urlTela = '<?= e(url('grupos-usuarios')) ?>';
    document.querySelectorAll('.fc-grupo-row').forEach(function (tr) {
        tr.addEventListener('click', function () {
            location.href = urlTela + '?permissoes=' + tr.dataset.id;
        });
        tr.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
            formDel.querySelector('[name=id]').value = tr.dataset.id;
            btnEditar.onclick = function () {
                location.href = urlTela + '?editar=' + tr.dataset.id;
            };
            btnExcluir.onclick = function () {
                if (confirm('Excluir o grupo "' + tr.dataset.nome +
                        '"? Os usuarios perderao as permissoes herdadas dele.')) {
                    formDel.submit();
                }
            };
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
