<?php
if (!function_exists('tabela_permissoes')) {
    /** Matriz Tela x (Consultar/Inserir/Alterar/Excluir) para um usuario. */
    function tabela_permissoes(array $telasDisp, array $permUsuario): void
    { ?>
<table class="tabela tabela-permissoes">
    <thead>
        <tr>
            <th>Tela</th>
            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Consultar</th>
            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Inserir</th>
            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Alterar</th>
            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Excluir</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($telasDisp as $t):
            $p = $permUsuario[$t['slug']] ?? []; ?>
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
<?php } } ?>

<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<h2>Solicitacoes pendentes</h2>
<?php if ($pendentes): ?>
<table class="tabela">
    <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Solicitado em</th><th>Acoes</th></tr>
    </thead>
    <tbody>
        <?php foreach ($pendentes as $u): ?>
        <tr>
            <td><?= e($u['Nome']) ?></td>
            <td><?= e($u['Email']) ?></td>
            <td><?= e(data_br($u['CriadoEm'], true)) ?></td>
            <td class="acoes">
                <form method="post" action="<?= url("usuarios/{$u['Id']}/liberar") ?>">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="telas_enviadas" value="1">
                    <?php tabela_permissoes($telasDisp, $telasMapa[$u['Id']] ?? []); ?>
                    <button type="submit" class="btn btn-sm" style="margin-top:6px">Liberar com estas permissoes</button>
                </form>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/bloquear") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhuma solicitacao pendente.</p>
<?php endif; ?>

<h2 style="margin-top:32px">Usuarios</h2>
<table class="tabela">
    <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Status</th><th>Perfil</th><th>Senha (PHP)</th><th>Permissoes</th><th>Acoes</th></tr>
    </thead>
    <tbody>
        <?php foreach ($demais as $u):
            $proprio = strcasecmp($u['Email'], $_SESSION['usuario'] ?? '') === 0; ?>
        <tr>
            <td><?= e($u['Nome']) ?></td>
            <td><?= e($u['Email']) ?></td>
            <td><span class="badge badge-<?= e($u['Status']) ?>"><?= e($u['Status']) ?></span></td>
            <td><?= $u['Admin'] ? 'Admin' : 'Usuario' ?></td>
            <td>
                <?php if ($u['TemSenhaPHP']): ?>
                <span class="badge badge-ativo">definida</span>
                <?php else: ?>
                <span class="badge badge-pendente">redefinir</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($u['Admin']): ?>
                <span class="badge badge-ativo">todas</span>
                <?php else: ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/telas") ?>">
                    <?= csrf_campo() ?>
                    <?php tabela_permissoes($telasDisp, $telasMapa[$u['Id']] ?? []); ?>
                    <button type="submit" class="btn btn-sm btn-secundario" style="margin-top:6px">Salvar permissoes</button>
                </form>
                <?php endif; ?>
            </td>
            <td class="acoes">
                <?php if ($u['Status'] === 'ativo' && !$proprio): ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/bloquear") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                </form>
                <?php elseif ($u['Status'] === 'bloqueado'): ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/liberar") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm">Reativar</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/gerar-senha") ?>"
                      onsubmit="return confirm('Gerar uma nova senha provisoria para <?= e($u['Nome']) ?>? A senha atual deixara de funcionar.');">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-secundario">Gerar senha</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// checkbox no cabecalho da coluna marca/desmarca toda a coluna daquela tabela
function permColuna(headChk) {
    var col = headChk.closest('th').cellIndex;
    headChk.closest('table').querySelectorAll('tbody tr').forEach(function (tr) {
        var chk = tr.cells[col].querySelector('input[type=checkbox]');
        if (chk) chk.checked = headChk.checked;
    });
}
</script>
