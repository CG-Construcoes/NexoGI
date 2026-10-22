<?php // Dicas — Configuracoes > Dicas: textos exibidos no modal de
      // progresso do calculo (Acompanhamento Financeiro). Flags Sim/Nao
      // direcionam a dica aos modulos do sistema. ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<h2><?= $edicao ? 'Editar dica' : 'Nova dica' ?></h2>
<form method="post" action="<?= url('dicas') ?>" style="margin-bottom:24px; max-width:860px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="id" value="<?= (int) $edicao['Dica_Id'] ?>">
    <?php endif; ?>
    <div class="campo" style="margin-bottom:12px">
        <label for="dc-texto">Dica <small class="fc-av-hint">(máx. 200 caracteres)</small></label>
        <textarea id="dc-texto" name="dica" required maxlength="200" rows="2"
                  style="width:100%"><?= e($edicao['Dica'] ?? '') ?></textarea>
    </div>
    <div class="filtros" style="margin-bottom:14px">
        <?php foreach (DICAS_AREAS as $campo => $rotulo):
            $chave = 'area_' . strtolower($campo); ?>
        <div class="campo">
            <label style="font-weight:600">
                <input type="checkbox" name="<?= e($chave) ?>" value="1"
                       <?= ($edicao[$campo] ?? '') === 'Sim' ? 'checked' : '' ?>>
                <?= e($rotulo) ?></label>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Incluir dica' ?></button>
        <?php if ($edicao): ?>
        <a href="<?= url('dicas') ?>" class="btn btn-secundario">Cancelar</a>
        <?php endif; ?>
    </div>
</form>

<h2>Dicas cadastradas</h2>
<?php if ($dicas): ?>
<table class="tabela" style="max-width:1100px">
    <thead>
        <tr><th>Dica</th>
            <?php foreach (DICAS_AREAS as $rotulo): ?>
            <th style="width:92px; text-align:center"><?= e($rotulo) ?></th>
            <?php endforeach; ?>
            <th style="width:140px"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($dicas as $dc): ?>
        <tr>
            <td><?= e($dc['Dica']) ?></td>
            <?php foreach (array_keys(DICAS_AREAS) as $campo): ?>
            <td style="text-align:center"><?= $dc[$campo] === 'Sim' ? '✓' : '—' ?></td>
            <?php endforeach; ?>
            <td class="acoes">
                <a class="btn btn-sm" href="<?= e(url('dicas?editar=' . (int) $dc['Dica_Id'])) ?>">Editar</a>
                <form method="post" action="<?= url('dicas') ?>" style="display:inline"
                      onsubmit="return confirm('Excluir esta dica?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $dc['Dica_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhuma dica cadastrada.</p>
<?php endif; ?>
