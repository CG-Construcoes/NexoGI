<?php
/*
 * Controle de Atividades — lista principal (estilo Follow-up).
 * Filtros no topo + grid clicavel; vencidas em vermelho, urgentes marcadas.
 */
?>
<div class="pedidos-coluna">

<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:14px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<!-- ===================== FILTROS (barra compacta) ===================== -->
<form class="atv-fbar" method="get">
    <?php foreach ($erros as $err): ?>
        <div class="alert-erro" style="margin-bottom:8px"><?= e($err) ?></div>
    <?php endforeach; ?>
    <div class="atv-frow">
        <div class="atv-fescopo">
            <label><input type="radio" name="escopo" value="minhas" <?= $f['escopo'] === 'minhas' ? 'checked' : '' ?>> Minhas</label>
            <label><input type="radio" name="escopo" value="todas" <?= $f['escopo'] === 'todas' ? 'checked' : '' ?>> Todas que vejo</label>
            <label><input type="radio" name="escopo" value="criadas" <?= $f['escopo'] === 'criadas' ? 'checked' : '' ?>> Criadas por mim</label>
        </div>
        <span class="atv-fsep"></span>
        <select name="resp">
            <option value="0">Participante: Todos</option>
            <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u['Id'] ?>" <?= $f['resp'] === (int) $u['Id'] ? 'selected' : '' ?>><?= e($u['Nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="visib">
            <option value="">Visibilidade: Todas</option>
            <?php foreach (ATV_VISIB as $v): ?>
                <option value="<?= e($v) ?>" <?= $f['visib'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="setor">
            <option value="">Setor: Todos</option>
            <?php foreach ($setores as $s): ?>
                <option value="<?= e($s) ?>" <?= $f['setor'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="atv-fsep"></span>
        <label>Abertura de <input type="date" name="data_ini" value="<?= e($f['data_ini']) ?>"></label>
        <label>até <input type="date" name="data_fim" value="<?= e($f['data_fim']) ?>"></label>
        <input type="text" class="grow" name="texto" value="<?= e($f['texto']) ?>"
               placeholder="Buscar no título, orientações ou referência…">
    </div>
    <div class="atv-frow">
        <div class="atv-fstatus">
            <?php foreach (ATV_STATUS as $s): ?>
            <label class="badge <?= atv_status_css($s) ?>"><input type="checkbox" name="status[]"
                value="<?= e($s) ?>" <?= in_array($s, $f['status'], true) ? 'checked' : '' ?>> <?= e($s) ?></label>
            <?php endforeach; ?>
        </div>
        <span class="atv-fsep"></span>
        <label><input type="checkbox" name="urgente" <?= $f['urgente'] ? 'checked' : '' ?>> Só urgentes</label>
        <label><input type="checkbox" name="vencidas" <?= $f['vencidas'] ? 'checked' : '' ?>> Só vencidas</label>
        <div class="atv-fbtns">
            <button type="submit" name="gerar" value="1" class="btn">Executar</button>
            <?php if ($gestor): ?>
            <a href="<?= url('atividade?nova=1') ?>" class="btn">+ Nova atividade</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- ===================== GRID ===================== -->
<?php if (!$linhas): ?>
    <p style="color:#666">Nenhuma atividade encontrada nos filtros/escopo informados.</p>
<?php else: ?>
<div class="filtros" style="margin-bottom:8px">
    <span style="color:#555"><?= count($linhas) ?> atividade(s)</span>
</div>
<div class="tabela-responsiva">
<table class="tabela flw-tab atv-tab">
    <thead>
        <tr>
            <th>Status</th><th></th><th>Atividade</th><th>Responsavel</th>
            <th>Visib.</th><th>Setor</th><th>Criado por</th><th>Abertura</th>
            <th>Prazo</th><th class="col-valor">Trabalhado</th><th class="col-valor">Em execucao</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($linhas as $l):
        $urg = atv_urgente($l);
        $venc = atv_vencida($l);
        $tsIni = atv_ts($l['IniciadoEm'] ?? null);
        $execMin = $tsIni !== null
            ? ((atv_ts($l['ConcluidoEm'] ?? null) ?? time()) - $tsIni) / 60
            : null; ?>
        <tr class="atv-row <?= $venc ? 'atv-venc' : '' ?>" data-id="<?= (int) $l['Atividade_Id'] ?>">
            <td><span class="badge <?= atv_status_css((string) $l['Status']) ?>"><?= e($l['Status']) ?></span></td>
            <td><?= $urg ? '<span class="atv-urg" title="Urgente">!</span>' : '' ?></td>
            <td class="atv-tit">
                <strong><?= e($l['Titulo']) ?></strong>
                <?php if (trim((string) ($l['Referencia'] ?? '')) !== ''): ?>
                    <span class="atv-ref"><?= e($l['Referencia']) ?></span>
                <?php endif; ?>
            </td>
            <td><?= e($l['ResponsavelNome'] ?? '—') ?></td>
            <td><?= e($l['Visibilidade']) ?></td>
            <td><?= $l['Visibilidade'] === 'Setor' ? e($l['Setor']) : '<span style="color:#94a3b8">—</span>' ?></td>
            <td><?= e($l['CriadoPorNome']) ?></td>
            <td><?= e(data_br(substr((string) $l['CriadoEm'], 0, 10))) ?></td>
            <td><?= $l['Prazo']
                ? e(data_br(substr((string) $l['Prazo'], 0, 16), true))
                    . ($venc ? ' <span class="atv-venc-txt">vencida</span>' : '')
                : '<span style="color:#94a3b8">—</span>' ?></td>
            <td class="col-valor"><?= e(atv_fmt_min((float) $l['TrabMin'])) ?></td>
            <td class="col-valor"><?= e(atv_fmt_min($execMin)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<script>
(function () {
    document.querySelectorAll('.atv-row').forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
            location.href = '<?= e(url('atividade')) ?>?id=' + tr.dataset.id;
        });
    });
})();
</script>
<?php endif; ?>
</div>
