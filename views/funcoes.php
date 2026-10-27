<?php // Funcoes dos usuarios — Configuracoes > Funcoes.
      // Setor vazio = funcao global (ex.: Diretor). Flag Gestor = pode
      // determinar/atribuir e gerenciar atividades do setor. ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<h2><?= $edicao ? 'Editar funcao' : 'Nova funcao' ?></h2>
<form method="post" action="<?= url('funcoes') ?>" style="margin-bottom:24px; max-width:900px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="id" value="<?= (int) $edicao['Funcao_Id'] ?>">
    <?php endif; ?>
    <div class="filtros" style="margin-bottom:14px">
        <div class="campo grow">
            <label for="fn-nome">Nome da funcao</label>
            <input type="text" id="fn-nome" name="nome" required maxlength="60"
                   value="<?= e($edicao['Funcao_Nome'] ?? '') ?>"
                   placeholder="Ex.: Suprimentos-Gerente" autocomplete="off">
        </div>
        <div class="campo">
            <label for="fn-setor">Setor que gerencia <small style="color:#94a3b8">(vazio = todos)</small></label>
            <input type="text" id="fn-setor" name="setor" maxlength="60"
                   value="<?= e($edicao['Funcao_Setor'] ?? '') ?>"
                   placeholder="Ex.: Suprimentos" autocomplete="off" list="dlSetoresFn">
            <datalist id="dlSetoresFn">
                <?php foreach (atv_setores() as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
            </datalist>
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <label style="font-weight:600">
                <input type="checkbox" name="gestor" value="1"
                    <?= !empty($edicao['Funcao_Gestor']) ? 'checked' : '' ?>>
                Pode determinar atividades (gestor)</label>
        </div>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Incluir funcao' ?></button>
        <?php if ($edicao): ?>
        <a href="<?= url('funcoes') ?>" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<h2>Funcoes cadastradas</h2>
<?php if ($funcoes): ?>
<table class="tabela" style="max-width:900px">
    <thead>
        <tr><th>Funcao</th><th>Setor gerenciado</th><th>Gestor</th>
            <th style="text-align:center">Usuarios</th><th style="width:140px"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($funcoes as $fn): ?>
        <tr>
            <td><strong><?= e($fn['Funcao_Nome']) ?></strong></td>
            <td><?= $fn['Funcao_Setor'] !== null && $fn['Funcao_Setor'] !== ''
                ? e($fn['Funcao_Setor'])
                : '<span class="badge badge-ativo">Todos (global)</span>' ?></td>
            <td><?= (int) $fn['Funcao_Gestor'] === 1
                ? '<span class="badge badge-ativo">Sim</span>'
                : '<span style="color:#94a3b8">—</span>' ?></td>
            <td style="text-align:center"><?= (int) $fn['QtdUsuarios'] ?></td>
            <td class="acoes">
                <a class="btn btn-sm" href="<?= e(url('funcoes?editar=' . (int) $fn['Funcao_Id'])) ?>">Editar</a>
                <form method="post" action="<?= url('funcoes') ?>" style="display:inline"
                      onsubmit="return confirm('Excluir esta funcao?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $fn['Funcao_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhuma funcao cadastrada.</p>
<?php endif; ?>
