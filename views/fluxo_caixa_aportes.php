<?php // Aportes a Retornar — Financeiro > Fluxo de Caixa
      // Lista os grupos de centros; clique no grupo abre o painel de
      // aportes (incluir/alterar/excluir) — mesmos grupos da tela
      // "Grupos de Centros". ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<!-- ===================== LISTA DE GRUPOS ===================== -->
<h2>Aportes a Retornar — grupos</h2>
<?php if ($grupos): ?>
<table class="tabela" style="max-width:860px">
    <thead>
        <tr><th>Grupo</th><th>Descricao</th><th>Aportes</th>
            <th class="col-valor">Total</th></tr>
    </thead>
    <tbody>
        <?php foreach ($grupos as $g):
            $sel = $grupoSel && (int) $g['FCGrupo_Id'] === (int) $grupoSel['FCGrupo_Id']; ?>
        <tr class="fc-grupo-row<?= $sel ? ' fc-sel' : '' ?>"
            data-id="<?= (int) $g['FCGrupo_Id'] ?>">
            <td><?= e($g['FCGrupo_Nome']) ?></td>
            <td><?= e($g['FCGrupo_Descricao'] ?? '') ?></td>
            <td><?= (int) $g['QtdeAportes'] ?></td>
            <td class="col-valor"><?= $g['TotalAportes'] !== null
                ? number_format((float) $g['TotalAportes'], 2, ',', '.') : '&mdash;' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p class="vazio" style="margin-top:6px">Clique no grupo para gerenciar os aportes.</p>
<?php else: ?>
<p class="vazio">Nenhum grupo cadastrado. Cadastre em
    <a href="<?= url('fluxo-caixa-grupos') ?>">Grupos de Centros</a>.</p>
<?php endif; ?>

<?php if ($grupoSel): ?>
<!-- ===================== APORTES DO GRUPO ===================== -->
<h2>Aportes do grupo: <?= e($grupoSel['FCGrupo_Nome']) ?></h2>

<?php if ($podeInserir || $edicao): ?>
<form method="post" action="<?= url('fluxo-caixa-aportes') ?>" class="filtros" style="margin-bottom:16px">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'inserir' ?>">
    <input type="hidden" name="id" value="<?= (int) $grupoSel['FCGrupo_Id'] ?>">
    <?php if ($edicao): ?>
    <input type="hidden" name="aporte_id" value="<?= (int) $edicao['Aporte_Id'] ?>">
    <?php endif; ?>
    <div class="campo grow">
        <label for="ap-desc">Descricao</label>
        <input type="text" id="ap-desc" name="descricao" required maxlength="120"
               value="<?= e($edicao['Aporte_Descricao'] ?? '') ?>"
               placeholder="Ex.: Aporte KONPAX — Hospital">
    </div>
    <div class="campo">
        <label for="ap-valor">Valor</label>
        <input type="text" id="ap-valor" name="valor" required size="14"
               value="<?= isset($edicao['Aporte_Valor'])
                   ? e(number_format((float) $edicao['Aporte_Valor'], 2, ',', '.')) : '' ?>"
               placeholder="0,00">
    </div>
    <div class="campo">
        <label for="ap-desde">Desde</label>
        <input type="date" id="ap-desde" name="desde" required
               value="<?= e($edicao['Aporte_Desde'] ?? '') ?>">
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn"><?= $edicao ? 'Salvar alteracoes' : 'Incluir aporte' ?></button>
        <a href="<?= url('fluxo-caixa-aportes?grupo=' . (int) $grupoSel['FCGrupo_Id']) ?>"
           class="btn btn-secundario"><?= $edicao ? 'Cancelar' : 'Voltar' ?></a>
    </div>
</form>
<?php endif; ?>

<?php if ($aportes): ?>
<table class="tabela" style="max-width:860px">
    <thead>
        <tr><th>Descricao</th><th class="col-valor">Valor</th><th>Desde</th>
            <th>Atualizado em</th><th style="width:150px"></th></tr>
    </thead>
    <tbody>
        <?php $tot = 0.0; foreach ($aportes as $a): $tot += (float) $a['Aporte_Valor']; ?>
        <tr>
            <td><?= e($a['Aporte_Descricao']) ?></td>
            <td class="col-valor"><?= e(number_format((float) $a['Aporte_Valor'], 2, ',', '.')) ?></td>
            <td><?= e(data_br((string) $a['Aporte_Desde'])) ?></td>
            <td><?= e(data_br((string) $a['Aporte_AtualizadoEm'], true)) ?></td>
            <td class="acoes">
                <?php if ($podeAlterar): ?>
                <a class="btn btn-sm" href="<?= e(url('fluxo-caixa-aportes?grupo='
                    . (int) $grupoSel['FCGrupo_Id'] . '&editar=' . (int) $a['Aporte_Id'])) ?>">Editar</a>
                <?php endif; ?>
                <?php if ($podeExcluir): ?>
                <form method="post" action="<?= url('fluxo-caixa-aportes') ?>" style="display:inline"
                      onsubmit="return confirm('Excluir o aporte &quot;<?= e($a['Aporte_Descricao']) ?>&quot;?')">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $grupoSel['FCGrupo_Id'] ?>">
                    <input type="hidden" name="aporte_id" value="<?= (int) $a['Aporte_Id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr class="rel-total">
            <td>Total do grupo</td>
            <td class="col-valor"><?= e(number_format($tot, 2, ',', '.')) ?></td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
<?php else: ?>
<p class="vazio">Nenhum aporte cadastrado neste grupo.</p>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var urlTela = '<?= e(url('fluxo-caixa-aportes')) ?>';
    document.querySelectorAll('.fc-grupo-row').forEach(function (tr) {
        tr.addEventListener('click', function () {
            location.href = urlTela + '?grupo=' + tr.dataset.id;
        });
    });
})();
</script>
