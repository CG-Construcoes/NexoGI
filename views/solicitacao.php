<?php
/** Registro de Solicitacao de Compra — read-only v1 (queries 7 e 20 do trace). */
$fmtD = fn(?string $d): string => $d ? date('d/m/Y', strtotime(substr($d, 0, 10))) : '-';
$fmtDT = fn(?string $d): string => $d ? date('d/m/Y H:i', strtotime(substr($d, 0, 16))) : '-';
?>
<div class="pedidos-coluna">

<form class="flw-filtro2" method="get">
    <input type="hidden" name="n" value="">
    <div class="flw-rows">
        <div class="flw-c"><span>Base</span>
            <select name="base">
                <?php foreach (uc_bases() as $b => $db): ?>
                    <option value="<?= e($b) ?>" <?= $base === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c w-centro"><span>Centro de Negócio</span>
            <select name="centro" required>
                <option value="">— selecionar —</option>
                <?php foreach ($centros as $c): ?>
                    <option value="<?= e($c['Centro_CodC']) ?>" <?= $centro === $c['Centro_CodC'] ? 'selected' : '' ?>>
                        <?= e($c['Centro_CodC'] . ' ' . $c['Centro_Nome']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c w-num"><span>Nº Solicitação</span>
            <input type="number" name="numero" value="<?= e($numero) ?>" min="1" required></div>
        <div class="flw-c"><span>&nbsp;</span>
            <button type="submit" class="btn">Abrir</button></div>
    </div>
</form>

<?php if ($n && !$cab): ?>
    <div class="alert-erro">Solicitação <strong><?= $n ?></strong> não encontrada na base <?= e($base) ?>.</div>
<?php elseif ($cab): ?>
<div class="sol-cab">
    <div class="sol-cab-tit">
        Solicitação <strong><?= e($cab['Solic_CentroNumero'] ?: $cab['Solic_Numero']) ?></strong>
        <span class="badge badge-<?= trim((string)$cab['Solic_Concluido']) === 'S' ? 'ativo' : 'pendente' ?>">
            <?= trim((string)$cab['Solic_Concluido']) === 'S' ? 'Concluída' : 'Aberta' ?></span>
        <span class="badge badge-ativo"><?= e($base) ?></span>
    </div>
    <div class="sol-cab-grid">
        <div><span>Data</span><?= $fmtD($cab['Solic_Data']) ?></div>
        <div><span>Centro</span><?= e($cab['Centro_CodC']) ?> — <?= e($cab['Centro_Nome'] ?? '') ?></div>
        <div><span>Solicitante</span><?= e($cab['Solicitante_Nome'] ?? $cab['Solicitante_Cod']) ?></div>
        <div><span>Tipo</span><?= e($cab['TipSol_Descricao'] ?? $cab['TipSol_Codigo'] ?? '-') ?></div>
        <div><span>Departamento</span><?= e($cab['DepSol_Descricao'] ?? $cab['DepSol_Codigo'] ?? '-') ?></div>
        <div><span>Criada por</span><?= e($cab['Audit_Insert_UserName'] ?? '-') ?> — <?= $fmtDT($cab['Audit_Insert_Date'] ?? null) ?></div>
        <div><span>Última alteração</span><?= e($cab['Audit_UserName'] ?? '-') ?> — <?= $fmtDT($cab['Audit_LastChange'] ?? null) ?></div>
    </div>
</div>

<div class="tabela-responsiva">
<table class="tabela sol-tab">
    <thead>
        <tr>
            <th>Item</th><th>Insumo/Sub</th><th>Especificação</th><th>Und</th>
            <th class="col-valor">Quantidade</th><th>Entrega</th><th>Sit</th>
            <th class="col-valor">Preço Unit</th><th>Obs</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $l): ?>
        <tr>
            <td><?= e($l['SolicItem_Item']) ?></td>
            <td><?= e($l['Insumo_CodC']) ?></td>
            <td class="sol-esp"><?= e(trim((string)$l['Insumo_Especificacao'])) ?></td>
            <td><?= e($l['Unid_Cod']) ?></td>
            <td class="col-valor"><?= e(number_format((float)$l['SolicItem_Quantidade'], 4, ',', '.')) ?></td>
            <td><?= $fmtD($l['SolicItem_DataEntrega']) ?></td>
            <td><?= e($l['SolicItem_Situacao']) ?></td>
            <td class="col-valor"><?= $l['SolicItem_PrecoUnit'] !== null ? 'R$ ' . e(number_format((float)$l['SolicItem_PrecoUnit'], 4, ',', '.')) : '-' ?></td>
            <td class="sol-esp" title="<?= e($l['SolicItem_Obs'] ?? '') ?>"><?= e($l['SolicItem_Obs'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<style>
.sol-cab { background: var(--bg-panel, #fff); border: 1px solid var(--border-color);
    border-radius: var(--radius-lg); padding: 12px 16px; margin-bottom: 12px; }
.sol-cab-tit { font-size: 15px; margin-bottom: 8px; display: flex; gap: 8px; align-items: center; }
.sol-cab-grid { display: flex; flex-wrap: wrap; gap: 6px 26px; font-size: 12px; }
.sol-cab-grid > div > span { display: block; font-size: 9.5px; font-weight: 600;
    text-transform: uppercase; color: var(--text-muted); }
.sol-tab { font-size: 11px; }
.sol-esp { white-space: normal; min-width: 220px; max-width: 420px; }
body.dark .sol-cab { background: #171e2e; }
.flw-c.w-centro select { min-width: 320px; }
</style>
