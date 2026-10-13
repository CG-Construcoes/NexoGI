<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<?php if ($podeInserir || $edicao): ?>
<h2><?= $edicao ? 'Editar cadastro #' . (int) $edicao['IDMatrizPrioridades'] : 'Novo cadastro' ?></h2>
<form method="post" action="<?= url('matriz-prioridades') ?>" class="filtros" style="margin-bottom:24px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="id" value="<?= (int) $edicao['IDMatrizPrioridades'] ?>">
    <?php endif; ?>
    <div class="campo grow">
        <label for="mp-centro">Centro de custos</label>
        <input type="text" id="mp-centro" name="centro" list="mp-centros" required
               placeholder="Digite para pesquisar"
               value="<?= e($edicao ? ($centros[$edicao['Centro_CodC']] ?? $edicao['Centro_CodC']) : '') ?>">
        <datalist id="mp-centros">
            <?php foreach ($centros as $rotulo): ?>
            <option value="<?= e($rotulo) ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="campo">
        <label for="mp-dia">Dia da semana</label>
        <select id="mp-dia" name="dia" required>
            <option value="">—</option>
            <?php foreach (MP_DIAS as $v => $nome): ?>
            <option value="<?= $v ?>" <?= $edicao && $edicao['DiaSemana'] === $v ? 'selected' : '' ?>><?= e($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo grow">
        <label for="mp-mail">E-mail do gestor</label>
        <input type="text" id="mp-mail" name="mail" required maxlength="200"
               placeholder="para mais de um e-mail, separe com ponto e virgula"
               value="<?= e($edicao['MailGestor'] ?? '') ?>">
    </div>
    <div class="campo">
        <label for="mp-horario">Horario</label>
        <input type="time" id="mp-horario" name="horario" required
               value="<?= e($edicao['Horario'] ?? '08:00') ?>">
    </div>
    <div class="campo">
        <label for="mp-ativo">Ativado</label>
        <input type="checkbox" id="mp-ativo" name="ativado" value="1"
               <?= !$edicao || $edicao['Ativado'] ? 'checked' : '' ?>>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Cadastrar' ?></button>
        <?php if ($edicao): ?>
        <a href="<?= url('matriz-prioridades') ?>" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<h2>Matriz de prioridades</h2>
<?php if ($registros): ?>
<table class="tabela">
    <thead>
        <tr><th>ID</th><th>Centro de custos</th><th>Dia da semana</th><th>E-mail do gestor</th><th>Horario</th><th>Ativo</th><th>Acoes</th></tr>
    </thead>
    <tbody>
        <?php foreach ($registros as $r): ?>
        <tr>
            <td><?= (int) $r['IDMatrizPrioridades'] ?></td>
            <td><?= e($centros[$r['Centro_CodC']] ?? $r['Centro_CodC']) ?></td>
            <td><?= e(MP_DIAS[$r['DiaSemana']] ?? $r['DiaSemana']) ?></td>
            <td><?= e($r['MailGestor']) ?></td>
            <td><?= e($r['Horario']) ?></td>
            <td><span class="badge <?= $r['Ativado'] ? 'badge-ativo' : 'badge-bloqueado' ?>"><?= $r['Ativado'] ? 'Sim' : 'Nao' ?></span></td>
            <td class="acoes">
                <?php if ($podeAlterar): ?>
                <a href="<?= url('matriz-prioridades') . '?editar=' . (int) $r['IDMatrizPrioridades'] ?>" class="btn btn-sm">Editar</a>
                <?php endif; ?>
                <?php if ($podeExcluir): ?>
                <form method="post" action="<?= url('matriz-prioridades') ?>" style="display:inline"
                      onsubmit="return confirm('Excluir o cadastro #<?= (int) $r['IDMatrizPrioridades'] ?>?');">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $r['IDMatrizPrioridades'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum cadastro.</p>
<?php endif; ?>
