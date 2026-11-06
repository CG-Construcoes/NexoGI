<?php
/** Coleta de Precos — read-only v1 (queries 92/93/98/153 do trace). */
$fmtD = fn(?string $d): string => $d ? date('d/m/Y', strtotime(substr($d, 0, 10))) : '-';
$fmtDT = fn(?string $d): string => $d ? date('d/m/Y H:i', strtotime(substr($d, 0, 16))) : '-';
$fmtQ = fn($v, $d = 4): string => $v === null ? '-' : number_format((float) $v, $d, ',', '.');
$fmtV = fn($v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.');
?>
<div class="pedidos-coluna">

<form class="flw-filtro2" method="get">
    <div class="flw-rows">
        <div class="flw-c"><span>Base</span>
            <select name="base">
                <?php foreach (uc_bases() as $b => $db): ?>
                    <option value="<?= e($b) ?>" <?= $base === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flw-c w-num"><span>Nº Coleta</span>
            <input type="number" name="n" value="<?= $n ?: '' ?>" min="1" required></div>
        <div class="flw-c"><span>&nbsp;</span>
            <button type="submit" class="btn">Abrir</button></div>
    </div>
</form>

<?php if ($n && !$cab): ?>
    <div class="alert-erro">Coleta <strong><?= $n ?></strong> não encontrada na base <?= e($base) ?>.</div>
<?php elseif ($cab): ?>
<div class="col-cab">
    <div class="col-cab-tit">
        Coleta de Preços <strong><?= (int) $cab['Coleta_Numero'] ?></strong>
        <span class="badge badge-<?= trim((string)$cab['Coleta_Concluido']) === 'S' ? 'ativo' : 'pendente' ?>">
            <?= trim((string)$cab['Coleta_Concluido']) === 'S' ? 'Concluída' : 'Aberta' ?></span>
        <span class="badge badge-ativo"><?= e($base) ?></span>
    </div>
    <div class="col-cab-grid">
        <div><span>Data</span><?= $fmtD($cab['Coleta_Data']) ?></div>
        <div><span>Tx. Juros (a.m.)</span><?= e(number_format((float)($cab['Coleta_Taxa'] ?? 0), 2, ',', '.')) ?></div>
        <div><span>Criada por</span><?= e($cab['Audit_Insert_UserName'] ?? '-') ?> — <?= $fmtDT($cab['Audit_Insert_Date'] ?? null) ?></div>
        <div><span>Última alteração</span><?= e($cab['Audit_UserName'] ?? '-') ?> — <?= $fmtDT($cab['Audit_LastChange'] ?? null) ?></div>
        <?php if (trim((string)$cab['Coleta_Observacao']) !== ''): ?>
        <div style="flex-basis:100%"><span>Observação</span><?= e(trim((string)$cab['Coleta_Observacao'])) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="col-abas">
    <button type="button" class="col-aba ativa" data-aba="itens">Itens da Coleta</button>
    <button type="button" class="col-aba" data-aba="forn">Fornecedores da Coleta</button>
    <button type="button" class="col-aba" data-aba="precos">Preços da Coleta</button>
</div>

<!-- Itens -->
<div class="col-painel" id="aba-itens">
<div class="tabela-responsiva">
<table class="tabela col-tab">
    <thead><tr>
        <th>Item</th><th>Solicitação</th><th>Sit</th><th>Insumo/Sub</th><th>Especificação</th>
        <th>Und</th><th class="col-valor">Quantidade</th><th>Tipo</th><th>Departamento</th>
        <th>Data Entrega</th><th>Centro</th>
    </tr></thead>
    <tbody>
    <?php foreach ($itens as $l): ?>
        <tr>
            <td><?= e($l['ColetaItem_Item']) ?></td>
            <td><?= e($l['Solicitacao_ID']) ?></td>
            <td><?= e($l['SolicItem_Situacao']) ?></td>
            <td><?= e($l['Insumo_CodC']) ?></td>
            <td class="col-esp"><?= e(trim((string)$l['Insumo_Especificacao'])) ?></td>
            <td><?= e($l['Unid_Cod']) ?></td>
            <td class="col-valor"><?= $fmtQ($l['SolicItem_Quantidade']) ?></td>
            <td><?= e($l['TipSol_Descricao'] ?? '') ?></td>
            <td><?= e($l['DepSol_Descricao'] ?? '') ?></td>
            <td><?= $fmtD($l['SolicItem_DataEntrega']) ?></td>
            <td><?= e($l['Centro_CodC']) ?> <?= e($l['Centro_Nome'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- Fornecedores -->
<div class="col-painel" id="aba-forn" hidden>
<div class="tabela-responsiva">
<table class="tabela col-tab">
    <thead><tr>
        <th>Item</th><th>Código</th><th>Razão Social</th><th>Prazo Dias</th>
        <th>Prazo %</th><th>Fora</th><th>Início</th><th>CTR</th>
        <th class="col-valor">Frete Total</th><th>Cidade/UF</th>
    </tr></thead>
    <tbody>
    <?php foreach ($forns as $fn): ?>
        <tr>
            <td><?= e($fn['ColForn_Item']) ?></td>
            <td><?= e($fn['Cre_Cod']) ?></td>
            <td><?= e($fn['Cre_RazSoc']) ?></td>
            <td><?= e($fn['ColForn_PrazoDias']) ?></td>
            <td><?= e($fn['ColForn_PrazoPerc']) ?></td>
            <td><?= e($fn['ColForn_PrazoFora']) ?></td>
            <td><?= e($fn['ColForn_PrazoInicio']) ?></td>
            <td><?= e($fn['ColForn_CTR']) ?></td>
            <td class="col-valor"><?= $fmtV($fn['ColForn_FreteTotal']) ?></td>
            <td><?= e(trim(($fn['Cre_Cidade'] ?? '') . ' ' . ($fn['UF_Sigla'] ?? ''))) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- Precos por fornecedor -->
<div class="col-painel" id="aba-precos" hidden>
    <?php if (!$forns): ?>
        <p class="tabela-vazio">Sem fornecedores nesta coleta.</p>
    <?php endif; ?>
    <div class="col-forn-abas">
        <?php foreach ($forns as $i => $fn): ?>
            <button type="button" class="col-faba <?= $i === 0 ? 'ativa' : '' ?>"
                    data-f="<?= (int) $fn['ColForn_Numero'] ?>"><?= e($fn['Cre_Cod'] . '-' . mb_substr($fn['Cre_RazSoc'], 0, 14)) ?></button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($forns as $i => $fn): ?>
    <div class="col-fpainel" id="fp-<?= (int) $fn['ColForn_Numero'] ?>" <?= $i ? 'hidden' : '' ?>>
        <div class="col-fhead"><?= e($fn['ColForn_Item'] . ' - ' . $fn['Cre_RazSoc']) ?></div>
        <div class="tabela-responsiva">
        <table class="tabela col-tab">
            <thead><tr>
                <th>Insumo/Sub</th><th>Especificação</th><th>Und</th>
                <th class="col-valor">Quantidade</th>
                <th class="col-valor">Pr.Unit Inicial</th><th class="col-valor">% Desc</th>
                <th class="col-valor">Pr.Unit Insumo</th><th class="col-valor">% IPI</th>
                <th class="col-valor">Pr.Unit c/IPI</th><th class="col-valor">Dif % ICMS</th>
                <th class="col-valor">Pr.Unit Frete</th><th class="col-valor">Pr.Unit Final</th>
                <th>Marca</th>
            </tr></thead>
            <tbody>
            <?php foreach ($precos[(int) $fn['ColForn_Numero']] ?? [] as $p): ?>
                <tr>
                    <td><?= e($p['Insumo_CodC']) ?></td>
                    <td class="col-esp"><?= e(trim((string)$p['Insumo_Especificacao'])) ?></td>
                    <td><?= e($p['Unid_Cod']) ?></td>
                    <td class="col-valor"><?= $fmtQ($p['Coleta_Quantidade']) ?></td>
                    <td class="col-valor"><?= $fmtV($p['ColInsForn_Preco']) ?></td>
                    <td class="col-valor"><?= $fmtQ($p['ColInsForn_PercDesc']) ?></td>
                    <td class="col-valor"><?= $fmtV($p['ColInsForn_PrecoInsumo']) ?></td>
                    <td class="col-valor"><?= $fmtQ($p['ColInsForn_PercIPI']) ?></td>
                    <td class="col-valor"><?= $fmtV($p['ColInsForn_PrecoInsumoComIPI']) ?></td>
                    <td class="col-valor"><?= $fmtQ($p['ColInsForn_PercICMS']) ?></td>
                    <td class="col-valor"><?= $fmtV($p['ColInsForn_PrecoFrete']) ?></td>
                    <td class="col-valor"><strong><?= $fmtV($p['ColInsForn_PrecoFinal']) ?></strong></td>
                    <td><?= e($p['Marca'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<style>
.col-cab { background: var(--bg-panel, #fff); border: 1px solid var(--border-color);
    border-radius: var(--radius-lg); padding: 12px 16px; margin-bottom: 12px; }
.col-cab-tit { font-size: 15px; margin-bottom: 8px; display: flex; gap: 8px; align-items: center; }
.col-cab-grid { display: flex; flex-wrap: wrap; gap: 6px 26px; font-size: 12px; }
.col-cab-grid > div > span { display: block; font-size: 9.5px; font-weight: 600;
    text-transform: uppercase; color: var(--text-muted); }
.col-tab { font-size: 10.5px; }
.col-esp { white-space: normal; min-width: 200px; max-width: 400px; }
.col-abas, .col-forn-abas { display: flex; gap: 4px; margin-bottom: 8px; flex-wrap: wrap; }
.col-aba, .col-faba { padding: 6px 14px; border: 1px solid var(--border-strong); border-radius: 7px 7px 0 0;
    background: var(--bg-subtle); font-size: 11.5px; font-weight: 600; cursor: pointer; color: var(--text-secondary); }
.col-aba.ativa, .col-faba.ativa { background: var(--bg-panel, #fff); border-bottom-color: var(--bg-panel, #fff); color: var(--primary-700); }
.col-faba { font-size: 10.5px; padding: 4px 10px; }
.col-fhead { background: var(--primary-600); color: #fff; font-weight: 700; font-size: 12px;
    padding: 6px 12px; border-radius: 6px 6px 0 0; }
body.dark .col-cab, body.dark .col-aba.ativa { background: #171e2e; }
body.dark .col-aba.ativa { border-bottom-color: #171e2e; }
body.dark .col-aba, body.dark .col-faba { background: #1b2333; border-color: #38486a; color: var(--text-main); }
</style>

<script>
document.querySelectorAll('.col-aba').forEach(function (b) {
    b.onclick = function () {
        document.querySelectorAll('.col-aba').forEach(function (x) { x.classList.remove('ativa'); });
        b.classList.add('ativa');
        ['itens', 'forn', 'precos'].forEach(function (a) {
            document.getElementById('aba-' + a).hidden = a !== b.dataset.aba;
        });
    };
});
document.querySelectorAll('.col-faba').forEach(function (b) {
    b.onclick = function () {
        document.querySelectorAll('.col-faba').forEach(function (x) { x.classList.remove('ativa'); });
        b.classList.add('ativa');
        document.querySelectorAll('.col-fpainel').forEach(function (p) { p.hidden = true; });
        document.getElementById('fp-' + b.dataset.f).hidden = false;
    };
});
</script>
