<?php // Fluxo de Caixa — grupos com saldo (Financeiro > Fluxo de Caixa) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<form method="post" action="<?= url('fluxo-caixa') ?>">
    <?= csrf_campo() ?>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px">
        <h2 style="margin:0">Grupos</h2>
        <?php if ($podeAtualizar): ?>
        <div class="fcs-periodo">
            <span class="fcs-periodo-label">Período</span>
            <div style="display:flex; align-items:center; gap:6px">
                <label for="fcs-ini" style="font-size:13px; color:#445">De</label>
                <input type="date" id="fcs-ini" name="data_ini" required>
                <label for="fcs-fim" style="font-size:13px; color:#445">a</label>
                <input type="date" id="fcs-fim" name="data_fim" required>
                <button type="submit" class="btn" id="fcs-btn">Atualizar</button>
            </div>
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
                           class="fcs-check" data-nome="<?= e($g['FCGrupo_Nome']) ?>"
                           <?= $podeAtualizar ? '' : ' disabled' ?>>
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
<div id="fcs-inicio" class="fcs-inicio" hidden>
    <div class="fcs-inicio-box">
        <div class="fcs-inicio-titulo">Fluxo de Caixa</div>
        <p>Os cálculos irão iniciar.<br><strong>Não feche esta tela.</strong></p>
    </div>
</div>
<div id="fcs-progresso" class="fcs-progresso" hidden>
    <div class="fcs-barra">
        <div id="fcs-fill" class="fcs-fill"></div>
        <span id="fcs-texto" class="fcs-texto"></span>
    </div>
    <div class="fcs-anim"><span class="fcs-spin"></span>
        <span class="fcs-anim-txt">processando — não feche esta tela</span></div>
</div>
<script>
(function () {
    var todos = document.getElementById('fcs-todos');
    var form = todos.closest('form');
    var checks = form.querySelectorAll('.fcs-check');
    var btn = document.getElementById('fcs-btn');
    var iniEl = document.getElementById('fcs-ini');
    var fimEl = document.getElementById('fcs-fim');
    var prog = document.getElementById('fcs-progresso');
    var inicio = document.getElementById('fcs-inicio');
    var fill = document.getElementById('fcs-fill');
    var texto = document.getElementById('fcs-texto');
    todos.addEventListener('change', function () {
        checks.forEach(function (cb) { cb.checked = todos.checked; });
    });
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var marcados = form.querySelectorAll('.fcs-check:checked').length;
            todos.checked = marcados === checks.length;
            todos.indeterminate = marcados > 0 && marcados < checks.length;
        });
    });
    form.addEventListener('submit', function (ev) {
        var marcados = Array.prototype.slice.call(
            form.querySelectorAll('.fcs-check:checked'));
        if (!marcados.length || !iniEl.value || !fimEl.value || iniEl.value > fimEl.value) {
            return; // deixa o POST normal validar no servidor
        }
        var dias = (Date.parse(fimEl.value) - Date.parse(iniEl.value)) / 86400000;
        if (dias > 30 && !confirm('O Período selecionado é muito grande, o relatório irá demorar alguns instantes')) {
            ev.preventDefault();
            return;
        }
        ev.preventDefault();
        btn.disabled = true;
        inicio.hidden = false;
        setTimeout(function () {
            inicio.hidden = true;
            prog.hidden = false;
            executar();
        }, 2000);
        var t0 = null;
        var falhas = [];
        var concluidos = [];
        var i = 0;
        function proximo() {
            if (i >= marcados.length) {
                fill.style.width = '100%';
                var seg = Math.round((performance.now() - t0) / 1000);
                var tempo = seg >= 60
                    ? Math.floor(seg / 60) + ' min ' + (seg % 60) + ' s'
                    : seg + ' s';
                texto.textContent = 'Concluído.';
                var msg = 'Cálculo concluído.\n\nGrupos atualizados:\n - '
                    + (concluidos.length ? concluidos.join('\n - ') : 'nenhum')
                    + '\n\nTempo total: ' + tempo;
                if (falhas.length) {
                    msg += '\n\nFalharam:\n - ' + falhas.join('\n - ');
                }
                alert(msg);
                location.reload();
                return;
            }
            var cb = marcados[i];
            var nome = cb.dataset.nome;
            texto.textContent = 'Calculando: ' + nome
                + ' (' + (i + 1) + ' de ' + marcados.length + ')';
            var corpo = new URLSearchParams(new FormData(form));
            corpo.set('ajax', '1');
            corpo.delete('grupos[]');
            corpo.set('grupo', cb.value);
            fetch(form.action, { method: 'POST', body: corpo })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.ok) { concluidos.push(nome); }
                    else { falhas.push(nome + ': ' + (j.erro || 'erro')); }
                })
                .catch(function () { falhas.push(nome + ': falha de rede'); })
                .finally(function () {
                    i++;
                    fill.style.width = Math.round(i / marcados.length * 100) + '%';
                    proximo();
                });
        }
        function executar() {
            t0 = performance.now();
            proximo();
        }
    });
})();
</script>
<?php endif; ?>
