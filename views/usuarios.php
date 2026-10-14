<?php // Cadastro e liberacao de Usuarios — Configuracoes (admin) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<!-- ===================== FILTRO DE USUARIO ===================== -->
<h2>Usuarios cadastrados</h2>
<div class="filtros" style="margin-bottom:8px">
    <div class="campo grow">
        <label for="usFiltro">Filtrar usuario</label>
        <input type="text" id="usFiltro" placeholder="Digite parte do nome ou e-mail" autocomplete="off">
    </div>
</div>

<!-- ===================== GRID DE USUARIOS ===================== -->
<table class="tabela" id="usGrid">
    <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Status</th><th>Perfil</th><th>Senha (PHP)</th><th>Acoes</th></tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u):
            $sel = $usuarioSel && (int) $u['Id'] === (int) $usuarioSel['Id'];
            $proprio = strcasecmp($u['Email'], $_SESSION['usuario'] ?? '') === 0; ?>
        <tr class="us-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= (int) $u['Id'] ?>"
            data-texto="<?= e(mb_strtolower($u['Nome'] . ' ' . $u['Email'])) ?>">
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
            <td class="acoes" onclick="event.stopPropagation()">
                <?php if ($u['Status'] === 'pendente'): ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/liberar") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm">Liberar</button>
                </form>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/bloquear") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                </form>
                <?php elseif ($u['Status'] === 'ativo' && !$proprio): ?>
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
                <?php if ($u['Status'] !== 'pendente'): ?>
                <form method="post" action="<?= url("usuarios/{$u['Id']}/gerar-senha") ?>"
                      onsubmit="return confirm('Gerar uma nova senha provisoria para <?= e($u['Nome']) ?>? A senha atual deixara de funcionar.');">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-secundario">Gerar senha</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($usuarioSel):
    $gruposDoUsuario = $gruposMapa[(int) $usuarioSel['Id']] ?? []; ?>
<!-- ===================== GRUPOS DO USUARIO (parte inferior) ===================== -->
<h2 style="margin-top:28px">Grupos do usuario: <?= e($usuarioSel['Nome']) ?></h2>
<div class="filtros" style="margin-bottom:8px">
    <div class="campo grow">
        <label for="ugFiltro">Filtrar grupo</label>
        <input type="text" id="ugFiltro" placeholder="Digite parte do nome ou descricao" autocomplete="off">
    </div>
</div>
<form method="post" action="<?= url("usuarios/{$usuarioSel['Id']}/grupos") ?>">
    <?= csrf_campo() ?>
    <div id="ugLista" style="border:1px solid #d5dde8; border-radius:6px; padding:10px 14px;
         max-height:220px; overflow-y:auto; background:#fff; max-width:760px">
        <?php if ($gruposDisp): ?>
            <?php foreach ($gruposDisp as $g):
                $gid = (int) $g['UsGrupo_Id']; ?>
            <label class="ug-item" data-texto="<?= e(mb_strtolower($g['UsGrupo_Nome'] . ' ' . ($g['UsGrupo_Descricao'] ?? ''))) ?>"
                   style="display:flex; align-items:center; gap:8px; padding:4px 0; font-size:13px; cursor:pointer">
                <input type="checkbox" name="grupos[]" value="<?= $gid ?>"<?= isset($gruposDoUsuario[$gid]) ? ' checked' : '' ?>>
                <span><?= e($g['UsGrupo_Nome']) ?><?= $g['UsGrupo_Descricao'] !== null && $g['UsGrupo_Descricao'] !== ''
                    ? ' <span style="color:#8899aa">— ' . e($g['UsGrupo_Descricao']) . '</span>' : '' ?></span>
            </label>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="vazio" style="margin:0">Nenhum grupo cadastrado —
                crie em <a href="<?= url('grupos-usuarios') ?>">Grupos de Usuarios</a>.</p>
        <?php endif; ?>
    </div>
    <div style="margin-top:10px">
        <button type="submit" class="btn">Salvar grupos</button>
        <a href="<?= url('usuarios') ?>" class="btn btn-secundario">Voltar</a>
    </div>
</form>
<?php endif; ?>

<script>
(function () {
    // filtro "contem" no grid de usuarios
    var fUs = document.getElementById('usFiltro');
    fUs.addEventListener('input', function () {
        var t = fUs.value.toLowerCase().trim();
        document.querySelectorAll('#usGrid .us-row').forEach(function (tr) {
            tr.style.display = (t === '' || tr.dataset.texto.indexOf(t) !== -1) ? '' : 'none';
        });
    });
    // clique na linha -> seleciona o usuario (abre o painel de grupos)
    document.querySelectorAll('#usGrid .us-row').forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
            location.href = '<?= e(url('usuarios')) ?>?usuario=' + tr.dataset.id;
        });
    });
    // filtro "contem" na lista de grupos
    var fUg = document.getElementById('ugFiltro');
    if (fUg) {
        fUg.addEventListener('input', function () {
            var t = fUg.value.toLowerCase().trim();
            document.querySelectorAll('#ugLista .ug-item').forEach(function (it) {
                it.style.display = (t === '' || it.dataset.texto.indexOf(t) !== -1) ? '' : 'none';
            });
        });
    }
})();
</script>
