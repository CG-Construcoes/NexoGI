<?php // Painel de Configuracoes: Criar Usuarios e Gerenciar Permissoes / O que pode clicar e fazer ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<div class="cfg-atalhos" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px">
    <a href="<?= url('configuracoes') ?>" class="btn <?= $active === 'configuracoes' ? '' : 'btn-secundario' ?>">Gerenciar Usuarios e Permissoes</a>
    <a href="<?= url('grupos-usuarios') ?>" class="btn btn-secundario">Grupos de Permissao</a>
</div>

<!-- ===================== 1. CRIAR NOVO USUARIO ===================== -->
<div class="cfg-card" style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:22px; margin-bottom:26px; box-shadow:var(--shadow-sm)">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:14px">
        <div>
            <h2 style="margin:0; font-size:17px">Criar novo usuario para entrar no sistema</h2>
            <p style="font-size:13px; color:var(--text-muted); margin-top:2px">Cadastre os dados de acesso e defina abaixo os grupos ou telas que este usuario podera clicar e utilizar.</p>
        </div>
    </div>

    <form method="post" action="<?= url('configuracoes') ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="criar_usuario">

        <div class="filtros" style="border:none; box-shadow:none; padding:0; background:transparent">
            <div class="campo grow">
                <label for="novo-nome">Nome completo</label>
                <input type="text" id="novo-nome" name="nome" required maxlength="120" placeholder="Ex: Joao da Silva" autocomplete="off">
            </div>
            <div class="campo grow">
                <label for="novo-email">E-mail de login</label>
                <input type="email" id="novo-email" name="email" required maxlength="120" placeholder="usuario@cgconstrucoes.com" autocomplete="off">
            </div>
            <div class="campo">
                <label for="novo-senha">Senha inicial</label>
                <input type="text" id="novo-senha" name="senha" required minlength="4" placeholder="Minimo 4 caracteres" autocomplete="off">
            </div>
            <div class="campo">
                <label for="novo-admin">Perfil de acesso</label>
                <select id="novo-admin" name="admin">
                    <option value="0">Usuario (permissoes selecionadas)</option>
                    <option value="1">Administrador (acesso total)</option>
                </select>
            </div>
        </div>

        <details style="margin-top:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); background:#f8fafc; padding:12px 16px">
            <summary style="cursor:pointer; font-weight:600; font-size:13.5px; color:var(--primary-800); user-select:none">
                Definir grupos e o que o novo usuario pode clicar/fazer (opcional — pode configurar agora ou depois)
            </summary>
            <div style="margin-top:14px">
                <?php if ($gruposDisp): ?>
                <div style="margin-bottom:16px">
                    <label style="display:block; font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px">Grupos de permissao</label>
                    <div style="display:flex; flex-wrap:wrap; gap:10px">
                        <?php foreach ($gruposDisp as $g): ?>
                        <label style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid var(--border-color); padding:6px 12px; border-radius:8px; font-size:13px; cursor:pointer">
                            <input type="checkbox" name="grupos[]" value="<?= (int) $g['UsGrupo_Id'] ?>">
                            <span><?= e($g['UsGrupo_Nome']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <label style="display:block; font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px">Permissoes especificas por tela (O que pode clicar / fazer)</label>
                <table class="tabela tabela-permissoes">
                    <thead>
                        <tr>
                            <th>Tela / Modulo</th>
                            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Acessar / Clicar</th>
                            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Inserir</th>
                            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Alterar</th>
                            <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Excluir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($telasDisp as $t): ?>
                        <tr>
                            <td><?= e($t['nome']) ?></td>
                            <?php foreach (PERM_ACOES as $a): ?>
                            <td class="td-check">
                                <input type="checkbox" name="perm[<?= e($t['slug']) ?>][<?= $a ?>]" value="1"
                                       onchange="sincronizarAcessoLinha(this)">
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <div style="margin-top:16px; display:flex; justify-content:flex-end">
            <button type="submit" class="btn">Criar usuario e liberar acesso</button>
        </div>
    </form>
</div>

<?php if ($usuarioSel):
    $uidSel = (int) $usuarioSel['Id'];
    $gruposDoUsuario = $gruposMapa[$uidSel] ?? [];
    $permUsuario = ug_permissoes_efetivas_usuario($uidSel); ?>
<!-- ===================== 2. GERENCIAR PERMISSOES DO USUARIO SELECIONADO ===================== -->
<div id="painelPermissoes" style="background:#fff; border:2px solid #93c5fd; border-radius:var(--radius-lg); padding:22px; margin-bottom:28px; box-shadow:var(--shadow-md)">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-color)">
        <div>
            <h2 style="margin:0; font-size:18px; color:var(--primary-800)">
                Gerenciando usuario: <?= e($usuarioSel['Nome']) ?>
                <span class="badge badge-<?= e($usuarioSel['Status']) ?>" style="margin-left:8px"><?= e($usuarioSel['Status']) ?></span>
            </h2>
            <p style="font-size:13px; color:var(--text-muted); margin-top:2px">E-mail: <strong><?= e($usuarioSel['Email']) ?></strong> — Defina abaixo a senha, grupos e exatamente o que este usuario pode clicar e fazer no sistema.</p>
        </div>
        <a href="<?= url('configuracoes') ?>" class="btn btn-sm btn-secundario">Fechar edicao</a>
    </div>

    <!-- Redefinir senha direta -->
    <form method="post" action="<?= url("usuarios/{$uidSel}/definir-senha") ?>" class="filtros" style="margin-bottom:20px; background:#f8fafc">
        <?= csrf_campo() ?>
        <div class="campo grow">
            <label for="nova_senha">Definir / trocar senha deste usuario</label>
            <input type="text" id="nova_senha" name="nova_senha" required minlength="4" placeholder="Digite a nova senha para <?= e($usuarioSel['Nome']) ?>" autocomplete="off">
        </div>
        <div class="campo acoes">
            <button type="submit" class="btn btn-secundario">Salvar nova senha</button>
        </div>
    </form>

    <!-- Permissoes (Perfil + Grupos + Matriz de Telas e Acoes) -->
    <form method="post" action="<?= url("usuarios/{$uidSel}/permissoes") ?>">
        <?= csrf_campo() ?>

        <div class="filtros" style="margin-bottom:16px; background:#f8fafc">
            <div class="campo grow">
                <label for="ed-admin">Nivel de permissao geral</label>
                <select id="ed-admin" name="admin">
                    <option value="0" <?= empty($usuarioSel['Admin']) ? 'selected' : '' ?>>Usuario — Acesso controlado pelas permissoes e grupos marcados abaixo</option>
                    <option value="1" <?= !empty($usuarioSel['Admin']) ? 'selected' : '' ?>>Administrador — Acesso total a todas as telas e funcoes</option>
                </select>
            </div>
        </div>

        <h3 style="font-size:14px; font-weight:700; color:var(--primary-800); margin:16px 0 8px">Grupos de acesso vinculados</h3>
        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px">
            <?php if ($gruposDisp): ?>
                <?php foreach ($gruposDisp as $g):
                    $gid = (int) $g['UsGrupo_Id']; ?>
                <label style="display:inline-flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid var(--border-color); padding:8px 14px; border-radius:8px; font-size:13px; cursor:pointer">
                    <input type="checkbox" name="grupos[]" value="<?= $gid ?>"<?= isset($gruposDoUsuario[$gid]) ? ' checked' : '' ?>>
                    <span><strong><?= e($g['UsGrupo_Nome']) ?></strong><?= $g['UsGrupo_Descricao'] ? ' — ' . e($g['UsGrupo_Descricao']) : '' ?></span>
                </label>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="vazio">Nenhum grupo cadastrado.</p>
            <?php endif; ?>
        </div>

        <h3 style="font-size:14px; font-weight:700; color:var(--primary-800); margin:18px 0 6px">O que <?= e($usuarioSel['Nome']) ?> pode clicar e fazer por tela</h3>
        <p style="font-size:12.5px; color:var(--text-muted); margin-bottom:10px">Marque <strong>Acessar / Clicar</strong> para liberar a tela no menu do usuario e marque as funcoes permitidas (Inserir, Alterar, Excluir).</p>

        <table class="tabela tabela-permissoes">
            <thead>
                <tr>
                    <th>Tela / Modulo</th>
                    <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Acessar / Clicar</th>
                    <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Inserir</th>
                    <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Alterar</th>
                    <th class="td-check"><input type="checkbox" title="Marcar todos" onclick="permColuna(this)"><br>Excluir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($telasDisp as $t):
                    $p = $permUsuario[$t['slug']] ?? []; ?>
                <tr>
                    <td><strong><?= e($t['nome']) ?></strong></td>
                    <?php foreach (PERM_ACOES as $a): ?>
                    <td class="td-check">
                        <input type="checkbox"
                               name="perm[<?= e($t['slug']) ?>][<?= $a ?>]"
                               value="1"
                               <?= !empty($p[$a]) ? 'checked' : '' ?>
                               onchange="sincronizarAcessoLinha(this)">
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap">
            <button type="submit" class="btn">Salvar permissoes de <?= e($usuarioSel['Nome']) ?></button>
            <a href="<?= url('configuracoes') ?>" class="btn btn-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ===================== 3. LISTA DE USUARIOS CADASTRADOS ===================== -->
<h2>Usuarios cadastrados (clique em um usuario para gerenciar o que ele pode clicar/fazer)</h2>
<div class="filtros" style="margin-bottom:10px">
    <div class="campo grow">
        <label for="usFiltro">Buscar usuario</label>
        <input type="text" id="usFiltro" placeholder="Digite parte do nome ou e-mail" autocomplete="off">
    </div>
</div>

<table class="tabela" id="usGrid">
    <thead>
        <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Status</th>
            <th>Perfil</th>
            <th>Grupos</th>
            <th>Senha</th>
            <th>Acoes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $u):
            $uid = (int) $u['Id'];
            $sel = $usuarioSel && $uid === (int) $usuarioSel['Id'];
            $proprio = strcasecmp($u['Email'], $_SESSION['usuario'] ?? '') === 0;
            $gNomes = array_values($gruposMapa[$uid] ?? []); ?>
        <tr class="us-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= $uid ?>"
            data-texto="<?= e(mb_strtolower($u['Nome'] . ' ' . $u['Email'])) ?>">
            <td><strong><?= e($u['Nome']) ?></strong></td>
            <td><?= e($u['Email']) ?></td>
            <td><span class="badge badge-<?= e($u['Status']) ?>"><?= e($u['Status']) ?></span></td>
            <td><?= $u['Admin'] ? '<span class="badge badge-ativo">Admin</span>' : 'Usuario' ?></td>
            <td><?= $gNomes ? e(implode(', ', $gNomes)) : '<span style="color:#94a3b8">—</span>' ?></td>
            <td>
                <?php if ($u['TemSenhaPHP']): ?>
                <span class="badge badge-ativo">definida</span>
                <?php else: ?>
                <span class="badge badge-pendente">redefinir</span>
                <?php endif; ?>
            </td>
            <td class="acoes" onclick="event.stopPropagation()">
                <a href="<?= url('configuracoes?usuario=' . $uid) ?>#painelPermissoes" class="btn btn-sm">Permissoes / Acessos</a>
                <?php if ($u['Status'] === 'pendente'): ?>
                <form method="post" action="<?= url("usuarios/{$uid}/liberar") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-secundario">Liberar</button>
                </form>
                <form method="post" action="<?= url("usuarios/{$uid}/bloquear") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                </form>
                <?php elseif ($u['Status'] === 'ativo' && !$proprio): ?>
                <form method="post" action="<?= url("usuarios/{$uid}/bloquear") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                </form>
                <?php elseif ($u['Status'] === 'bloqueado'): ?>
                <form method="post" action="<?= url("usuarios/{$uid}/liberar") ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-secundario">Reativar</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= url("usuarios/{$uid}/gerar-senha") ?>"
                      onsubmit="return confirm('Gerar uma nova senha provisoria para <?= e($u['Nome']) ?>?');">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-sm btn-secundario">Gerar senha</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function permColuna(headChk) {
    var col = headChk.closest('th').cellIndex;
    headChk.closest('table').querySelectorAll('tbody tr').forEach(function (tr) {
        var chk = tr.cells[col].querySelector('input[type=checkbox]');
        if (chk) {
            chk.checked = headChk.checked;
            sincronizarAcessoLinha(chk);
        }
    });
}

// Se marcar Inserir/Alterar/Excluir, marca automaticamente Consultar (Acessar/Clicar) na mesma linha
function sincronizarAcessoLinha(chk) {
    if (!chk.checked) return;
    var tr = chk.closest('tr');
    if (!tr) return;
    var chkConsultar = tr.querySelector('input[name$="[consultar]"]');
    if (chkConsultar && chk !== chkConsultar) {
        chkConsultar.checked = true;
    }
}

(function () {
    var fUs = document.getElementById('usFiltro');
    if (fUs) {
        fUs.addEventListener('input', function () {
            var t = fUs.value.toLowerCase().trim();
            document.querySelectorAll('#usGrid .us-row').forEach(function (tr) {
                tr.style.display = (t === '' || tr.dataset.texto.indexOf(t) !== -1) ? '' : 'none';
            });
        });
    }
    document.querySelectorAll('#usGrid .us-row').forEach(function (tr) {
        tr.addEventListener('click', function () {
            location.href = '<?= e(url('configuracoes')) ?>?usuario=' + tr.dataset.id + '#painelPermissoes';
        });
    });
})();
</script>
