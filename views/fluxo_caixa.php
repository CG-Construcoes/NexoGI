<?php // Fluxo de Caixa — grupos com saldo (Financeiro > Fluxo de Caixa) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<form method="post" action="<?= url('fluxo-caixa') ?>">
    <?= csrf_campo() ?>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px">
        <h2 style="margin:0">Grupos</h2>
        <?php if ($podeAtualizar): ?>
        <div style="display:flex; align-items:center; gap:6px">
            <label for="fcs-ini" style="font-size:13px; color:#445">De</label>
            <input type="date" id="fcs-ini" name="data_ini" required>
            <label for="fcs-fim" style="font-size:13px; color:#445">a</label>
            <input type="date" id="fcs-fim" name="data_fim" required>
            <button type="submit" class="btn">Atualizar</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($grupos): ?>
    <table class="tabela">
        <thead>
            <tr>
                <th style="width:36px">
                    <?php if ($podeAtualizar): ?>
                    <input type="checkbox" id="fcs-todos" title="Selecionar todos">
                    <?php endif; ?>
                </th>
                <th>Grupo</th>
                <th>Centros</th>
                <th class="col-valor">Saldo do Grupo</th>
                <th>Ultima Atualizacao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $g):
                $centros = $centrosPorGrupo[(int) $g['FCGrupo_Id']] ?? []; ?>
            <tr>
                <td>
                    <input type="checkbox" name="grupos[]" value="<?= (int) $g['FCGrupo_Id'] ?>"
                           class="fcs-check"<?= $podeAtualizar ? '' : ' disabled' ?>>
                </td>
                <td>
                    <span class="fcs-grupo"><?= e($g['FCGrupo_Nome']) ?>
                        <?php if ($centros): ?>
                        <span class="fcs-tip"><?php foreach ($centros as $c): ?>
                            <span class="fcs-tip-item"><?= e($c) ?></span>
                        <?php endforeach; ?></span>
                        <?php endif; ?>
                    </span>
                </td>
                <td><?= (int) $g['QtdeCentros'] ?></td>
                <td class="col-valor"><?= $g['SaldoGrupo'] !== null
                    ? number_format((float) $g['SaldoGrupo'], 2, ',', '.') : '&mdash;' ?></td>
                <td><?= $g['UltimaAtualizacao']
                    ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '&mdash;' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="vazio">Nenhum grupo cadastrado. Cadastre em
        <a href="<?= url('fluxo-caixa-grupos') ?>">Grupos de Centros</a>.</p>
    <?php endif; ?>
</form>

<?php if ($podeAtualizar && $grupos): ?>
<script>
(function () {
    var todos = document.getElementById('fcs-todos');
    var checks = document.querySelectorAll('.fcs-check');
    todos.addEventListener('change', function () {
        checks.forEach(function (cb) { cb.checked = todos.checked; });
    });
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var marcados = document.querySelectorAll('.fcs-check:checked').length;
            todos.checked = marcados === checks.length;
            todos.indeterminate = marcados > 0 && marcados < checks.length;
        });
    });
})();
</script>
<?php endif; ?>
