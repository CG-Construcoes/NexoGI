<?php
$cab = $ar['cab'] ?? [];
$docs = $ar['docs'] ?? [];
$primeiro = (string) ($docs[0]['Ent_Numero'] ?? '');
$fmt = static fn($v, $d = 2) => number_format((float) $v, $d, ',', '.');
?>
<style>
.rar {display:flex;flex-direction:column;gap:12px;min-width:0}
.rar h1 {margin:0;font-size:20px}
.rar-footer {padding:12px;border:1px solid var(--border-color,#dbe4ed);border-radius:6px;background:var(--bg-panel,#fff)}
.rar .rar-header {padding:10px 14px;margin-bottom:0}
.rar .rar-header label {font-size:9.5px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted,#64748b);gap:2px}
.rar .rar-header input,.rar .rar-header select {height:26px;font-size:11.5px;font-weight:400;letter-spacing:normal;padding:3px 6px;background:var(--bg-input,#fff);color:var(--text-main,#172b45);border:1px solid var(--border-strong,#cbd5e1);border-radius:5px}
.rar .rar-header input[readonly] {background:var(--bg-panel,#f3f6fa)}
.rar .rar-header .rar-fields {gap:6px 10px}
.rar .rar-header .rar-grow {min-width:170px!important}
.rar .rar-header hr {margin:8px 0;border:0;border-top:1px solid var(--border-color,#dbe4ed)}
.rar .rar-header .rar-lookup-field input {padding-right:28px}
.rar-executar {margin-left:auto;display:flex;gap:10px;align-items:center}
.rar-executar button {min-width:95px;height:28px;border:1px solid #1d4ed8;border-radius:6px;background:linear-gradient(180deg,#2563eb,#1d4ed8);color:#fff;font-size:12px;font-weight:600;cursor:pointer}
.rar-executar a {font-size:11px}
.rar-fields {display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.rar label {display:flex;flex-direction:column;gap:3px;font-size:12px;min-width:0}
.rar input,.rar select {padding:5px 7px;border:1px solid #cbd5e1;border-radius:4px;max-width:100%;box-sizing:border-box}
.rar input[readonly] {background:#f3f6fa}
.rar-grow {flex:1;min-width:220px!important}
.rar-section {border:1px solid #ccd9e5;border-radius:5px;overflow:hidden}
.rar-section h2 {margin:0;padding:7px 12px;background:#397c80;color:#fff;font-size:13px}
.rar-grid {overflow:auto;min-height:0;max-height:55vh}
.rar .tabela {margin:0;font-size:12px;white-space:nowrap;width:100%}
.rar .tabela th {position:sticky;top:0;z-index:1;background:#eef3f8}
.rar .tabela td,.rar .tabela th {padding:6px 9px}
.rar .tabela tfoot th {position:static}
.rar .rar-selected td {background:#dceeff}
.rar-select {border:0;background:none;color:#175da5;text-decoration:underline;cursor:pointer;font:inherit;padding:0}
.rar-tools {display:flex;gap:8px;flex-wrap:wrap;padding:8px;background:#f5f8fb}
.rar button:disabled {opacity:.45;cursor:not-allowed}
.rar-note {font-size:12px;color:#64748b;margin:0}
.rar [hidden] {display:none!important}
.rar-fiscal {padding:10px;font-size:12px}
.rar-footer .rar-tools {padding:0;background:none}
.rar-confirm {border-top:1px solid #dbe4ed;padding-top:10px;margin-top:10px;display:flex;justify-content:flex-end;gap:8px}
</style>
<div class="rar">
<div class="rar-header flw-filtro2">
<form method="get" class="rar-fields">
<label>Base<select name="base"><?php foreach (['CONV' => 'Conv', 'MGA' => 'MGA', 'TODAS' => 'Todas'] as $b => $rotulo): ?><option value="<?= e($b) ?>"<?= $base === $b ? ' selected' : '' ?>><?= e($rotulo) ?></option><?php endforeach; ?></select></label>
<div><label for="rar-numero">Nº AR</label><div style="position:relative;display:inline-block"><input id="rar-numero" name="numero" value="<?= e($cab['AR_CentroNumero'] ?? $f['numero']) ?>" inputmode="numeric" size="8" placeholder="Nº AR" style="padding-right:28px"><button type="button" id="rar-abrir" title="Consultar Aviso de Recebimento" aria-label="Consultar Aviso de Recebimento" style="position:absolute;right:3px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:24px;height:24px;padding:3px;border:0;background:transparent;color:inherit;cursor:pointer"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="m15 15 5 5"></path></svg></button></div></div>
<?php foreach (['centro' => 'Centro de Negócio', 'fornecedor' => 'Fornecedor'] as $campo => $rotulo): ?>
<label class="rar-grow"><?= e($rotulo) ?><span class="rar-lookup-field"><input name="<?= e($campo) ?>" value="<?= e($cab ? ($campo === 'centro' ? $cab['Empresa_Cod'] . $cab['Filial_Cod'] . $cab['Centro_Cod'] : $cab['Cre_Cod']) : $f[$campo]) ?>" title="<?= e($cab ? ($campo === 'centro' ? $cab['Centro_Nome'] : $cab['Cre_RazSoc'] . ' — ' . $cab['Cre_CGCCPF']) : '') ?>" placeholder="<?= $campo === 'centro' ? 'Código completo: PSC010011' : 'Nome ou código' ?>" style="width:100%"><button type="button" data-rar-lookup="<?= e($campo) ?>" title="<?= e('Consultar ' . $rotulo) ?>" aria-label="<?= e('Consultar ' . $rotulo) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="m15 15 5 5"></path></svg></button></span></label>
<?php endforeach; ?>
<label>De<input type="date" name="ini" value="<?= e($f['ini']) ?>"></label><label>Até<input type="date" name="fim" value="<?= e($f['fim']) ?>"></label>
<div class="rar-executar"><a href="<?= e(url('registro-ar')) ?>">Limpar</a><button type="submit">Executar</button></div>
</form>
<hr>
<div class="rar-fields" style="margin-top:8px">
<label>Data do AR<input readonly size="11" value="<?= e(isset($cab['AR_Data']) ? data_br((string) $cab['AR_Data']) : '') ?>"></label>
<label>Obra Nº ID<input readonly size="9" value="<?= e($cab['Obra_Numero'] ?? '') ?>"></label>
<label>CNO da Obra<input readonly value="<?= e($cab['Obra_CNO'] ?? '') ?>"></label>
<label>CIB<input readonly value="<?= e($cab['Obra_CIB'] ?? '') ?>"></label>
</div>
</div>
<p class="rar-note">Somente consulta — bases de treino. Selecione um documento para consultar seus itens e parcelas.</p>
<?php if ($erro !== ''): ?><p class="alert-erro"><?= e($erro) ?></p><?php endif; ?>
<?php if ($lista !== null): ?>
<details class="rar-section" open><summary style="padding:8px">Consulta de AR — <?= count($lista) ?> registros (limite de 200; refine os filtros)</summary>
<div class="rar-grid"><table class="tabela"><thead><tr><th>Nº AR</th><th>Data</th><th>Centro de Negócio</th><th>Fornecedor</th></tr></thead><tbody>
<?php foreach ($lista as $r): ?><tr><td><a href="<?= e(url('registro-ar') . '?' . http_build_query(array_merge($f, ['base' => $r['base'], 'id' => $r['AR_Numero']]))) ?>"><?= e($r['AR_CentroNumero'] . ' · ' . $r['base']) ?></a></td><td><?= e(data_br((string) $r['AR_Data'])) ?></td><td><?= e($r['Centro_CodC'] . ' - ' . $r['Centro_Nome']) ?></td><td><?= e($r['Cre_Cod'] . ' - ' . $r['Cre_RazSoc']) ?></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="4">Nenhum AR encontrado.</td></tr><?php endif; ?>
</tbody></table></div></details>
<?php endif; ?>
<section class="rar-section"><h2>Documentos do AR</h2><div class="rar-grid"><table class="tabela"><thead><tr>
<?php foreach (['Item','Tipo Doc','Nº Documento','Série','UF Origem','Data Doc','Valor','Divergência','Data Entrada','Nº Entrada','Nº Ficha','CTR','Natureza','CFOP'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($docs as $doc): ?><tr data-rar-doc="<?= e($doc['Ent_Numero']) ?>" class="<?= (string) $doc['Ent_Numero'] === $primeiro ? 'rar-selected' : '' ?>">
<td><button type="button" class="rar-select" aria-pressed="<?= (string) $doc['Ent_Numero'] === $primeiro ? 'true' : 'false' ?>" aria-label="<?= e('Selecionar documento ' . trim($doc['Ent_DocNumero'])) ?>"><?= e($doc['Ent_Item']) ?></button></td>
<?php foreach (['EntDocTipo_Cod','Ent_DocNumero','Ent_DocSerie','Ent_DocUFOrigem','Ent_DocData','Ent_DocValor','Ent_ValorDivergencia','Ent_DataEntrada','Entrada_Numero','Ficha_Numero','Ent_DocCTR','Nop_Cod','Ent_CFOP'] as $k): ?><td><?= e(in_array($k, ['Ent_DocData','Ent_DataEntrada'], true) ? data_br((string) $doc[$k]) : (in_array($k, ['Ent_DocValor','Ent_ValorDivergencia'], true) ? $fmt($doc[$k]) : (string) $doc[$k])) ?></td><?php endforeach; ?>
</tr><?php endforeach; ?>
<?php if (!$docs): ?><tr><td colspan="14">Selecione um AR para consultar os documentos.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php foreach ($docs as $doc): ?>
<details class="rar-fiscal" data-rar-panel="<?= e($doc['Ent_Numero']) ?>"<?= (string) $doc['Ent_Numero'] !== $primeiro ? ' hidden' : '' ?>><summary>Dados fiscais e informações do documento <?= e($doc['Ent_DocNumero']) ?></summary>
<p><strong>Chave NF-e:</strong> <?= e($doc['Sped_CHV_NFE']) ?></p><div class="rar-fields">
<?php foreach (['Ent_ValorIPI' => 'IPI','Ent_DescontoProduto' => 'Desconto produtos','Ent_ValorAcrescimos' => 'Acréscimos','Ent_ValorDescontos' => 'Descontos','Ent_ValorINSS' => 'INSS','Ent_ValorISS' => 'ISS','Ent_ValorIRRF' => 'IRRF','Ent_ValorPIS' => 'PIS','Ent_ValorCOFINS' => 'COFINS','Ent_ValorCSSL' => 'CSLL'] as $k => $label): ?><span><strong><?= e($label) ?>:</strong> <?= e($fmt($doc[$k])) ?></span><?php endforeach; ?></div><p><?= e($doc['Ent_Observacao']) ?></p></details>
<?php endforeach; ?>
<section class="rar-section"><h2>Itens do Documento</h2>
<?php foreach ($docs ?: [['Ent_Numero' => '', 'Ent_DocNumero' => '']] as $doc):
$itens = array_values(array_filter($ar['itens'] ?? [], fn($r) => $r['Ent_Numero'] == $doc['Ent_Numero'])); ?>
<div class="rar-grid" data-rar-panel="<?= e($doc['Ent_Numero']) ?>"<?= (string) $doc['Ent_Numero'] !== $primeiro ? ' hidden' : '' ?>><table class="tabela"><thead><tr>
<?php foreach (['Item','Insumo-Sub','Especificação','Unidade','Quantidade','Pr. Unitário','Total Produto','Desc. Produto','Total Item','Vr. IPI','Total com IPI','Pedido / Item','Solicitação / Item'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach ($itens as $i): ?><tr><td><?= e($i['EntItem_Item']) ?></td><td><?= e(sprintf('%06d-%03d', $i['Insumo_Cod'], $i['SubInsumo_Cod'])) ?></td><td><?= e(trim((string) $i['Insumo_Descricao']) . ' ' . trim((string) $i['EntItem_Especificacao'])) ?></td><td><?= e($i['EntItem_Unidade']) ?></td>
<?php foreach (['EntItem_Quantidade' => 4,'EntItem_PrecoUnitario_E' => 6,'EntItem_TotalItemProduto' => 2,'EntItem_DescontoProduto' => 2,'EntItem_TotalItem_E' => 2,'EntItem_ValorIPI_E' => 2,'EntItem_TotalItemcomIPI_E' => 2] as $k => $dec): ?><td class="col-valor"><?= e($fmt($i[$k], $dec)) ?></td><?php endforeach; ?>
<td><?= e($i['Pedido_Numero'] . ' / ' . $i['PedItem_Numero']) ?></td><td><?= e($i['Solic_Numero'] . ' / ' . $i['SolicItem_Numero']) ?></td></tr><?php endforeach; ?>
<?php if (!$itens): ?><tr><td colspan="13">Nenhum item para exibir.</td></tr><?php endif; ?>
</tbody><tfoot><tr><th colspan="6">Totais</th>
<?php foreach (['EntItem_TotalItemProduto','EntItem_DescontoProduto','EntItem_TotalItem_E','EntItem_ValorIPI_E','EntItem_TotalItemcomIPI_E'] as $k): ?><th class="col-valor"><?= e($fmt(array_sum(array_column($itens, $k)))) ?></th><?php endforeach; ?><th colspan="2"></th></tr></tfoot></table></div>
<?php endforeach; ?></section>
<section class="rar-section"><h2>Parcelas a Pagar</h2>
<?php foreach ($docs ?: [['Ent_Numero' => '', 'Ent_DocValor' => 0]] as $doc):
$parcelas = array_values(array_filter($ar['parcelas'] ?? [], fn($r) => $r['Ent_Numero'] == $doc['Ent_Numero'])); ?>
<div data-rar-panel="<?= e($doc['Ent_Numero']) ?>"<?= (string) $doc['Ent_Numero'] !== $primeiro ? ' hidden' : '' ?>>
<p class="rar-note" style="padding:6px 10px">Valor da nota: R$ <?= e($fmt($doc['Ent_DocValor'])) ?> — Total das parcelas: R$ <?= e($fmt(array_sum(array_column($parcelas, 'TitaPag_Valor')))) ?></p>
<div class="rar-grid"><table class="tabela"><thead><tr><th>Nº Parcela</th><th>SPar</th><th>Situação</th><th>Espécie de Título</th><th>Nº Título</th><th>Vencimento</th><th>Valor do Título</th><th>Código de Barras</th></tr></thead><tbody>
<?php foreach ($parcelas as $p): ?><tr><?php foreach (['TitaPag_ParNumero','TitaPag_SParNumero','TitaPag_Situacao','EspTit_Titulo','TitaPag_DocNumero'] as $k): ?><td><?= e($p[$k]) ?></td><?php endforeach; ?><td><?= e(data_br((string) $p['TitaPag_DataVencimento'])) ?></td><td class="col-valor"><?= e($fmt($p['TitaPag_Valor'])) ?></td><td><?= e($p['TitaPag_CodigoBarras']) ?></td></tr><?php endforeach; ?>
<?php if (!$parcelas): ?><tr><td colspan="8">Nenhuma parcela para exibir.</td></tr><?php endif; ?>
</tbody><tfoot><tr><th colspan="6">Total</th><th class="col-valor"><?= e($fmt(array_sum(array_column($parcelas, 'TitaPag_Valor')))) ?></th><th></th></tr></tfoot></table></div></div>
<?php endforeach; ?></section>
<footer class="rar-footer">
<?php if ($cab && trim((string) $cab['AR_Observacao']) !== ''): ?><p><strong>Obs. AR:</strong> <?= e($cab['AR_Observacao']) ?></p><?php endif; ?>
<?php foreach ($docs as $doc): ?><p class="rar-note" data-rar-panel="<?= e($doc['Ent_Numero']) ?>"<?= (string) $doc['Ent_Numero'] !== $primeiro ? ' hidden' : '' ?>>Criado por: <?= e($doc['Audit_Insert_UserName']) ?> — <?= e(data_br((string) $doc['Audit_Insert_Date'], true)) ?> | Última alteração: <?= e($doc['Audit_UserName']) ?> — <?= e(data_br((string) $doc['Audit_LastChange'], true)) ?></p><?php endforeach; ?>
<div class="rar-tools" style="margin-top:10px"><?php foreach (['Parcelas','Pedido','Medições','Solicitação','Obs. AR','Qualidade','Imprimir','Gerar PDF'] as $label): ?><button type="button" disabled title="Indisponível nesta etapa de consulta"><?= e($label) ?></button><?php endforeach; ?></div>
<div class="rar-confirm"><button type="button" disabled>Confirmar</button><button type="button" disabled>Cancelar</button></div>
</footer>
</div>
<style>
.rar-modal {width:min(1100px,92vw);max-height:85vh;margin:auto;padding:0;border:1px solid #aebed0;border-radius:9px;background:var(--card-bg,#fff);color:var(--text,#172b45);box-shadow:0 24px 80px #071a3366}
.rar-modal::backdrop {background:rgba(8,24,44,.48);backdrop-filter:blur(2px)}
.rar-modal h2 {margin:0 0 14px;padding:14px 18px;background:#294e73;color:#fff;font-size:16px}
.rar-modal form,.rar-modal>p,.rar-modal>.rar-modal-body,.rar-modal>.rar-modal-actions {margin:12px 16px}
.rar-modal label {display:flex;flex-direction:column;gap:5px;font-size:12px}
.rar-modal input {box-sizing:border-box;padding:7px 9px;border:1px solid #cbd5e1;border-radius:4px;min-width:0}
.rar-lookup-field {position:relative;display:flex}
.rar-lookup-field input {padding-right:30px;width:190px}
.rar-lookup-field button {position:absolute;right:3px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:24px;height:24px;padding:3px;border:0;background:transparent;color:inherit;cursor:pointer}
.rar-modal-actions {display:flex;justify-content:flex-end;gap:8px}
</style>
<dialog id="rar-consulta" class="rar-modal" aria-labelledby="rar-consulta-titulo">
<h2 id="rar-consulta-titulo">Consulta Aviso de Recebimento</h2>
<form id="rar-pesquisa" class="rar-fields">
<label>Nº AR<input name="numero" size="9" inputmode="numeric"></label>
<?php foreach (['centro' => 'Centro de Negócio', 'fornecedor' => 'Fornecedor'] as $campo => $rotulo): ?>
<label><?= e($rotulo) ?><span class="rar-lookup-field"><input name="<?= e($campo) ?>" placeholder="<?= $campo === 'centro' ? 'PSC010011' : 'Nome ou código' ?>"><button type="button" data-rar-lookup="<?= e($campo) ?>" aria-label="<?= e('Consultar ' . $rotulo) ?>" title="<?= e('Consultar ' . $rotulo) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="m15 15 5 5"></path></svg></button></span></label>
<?php endforeach; ?>
<label>De<input type="date" name="ini"></label><label>Até<input type="date" name="fim"></label>
<button type="submit">Executar</button>
</form>
<p id="rar-status" role="status"></p>
<div class="rar-modal-body" style="overflow:auto;max-height:50vh"><table class="tabela"><thead><tr><th>Nº AR</th><th>Data AR</th><th>Centro de Negócio</th><th>Fornecedor</th></tr></thead><tbody id="rar-resultados"></tbody></table></div>
<div class="rar-modal-actions"><button type="button" id="rar-confirmar" disabled>Confirmar</button><button type="button" id="rar-fechar">Cancelar</button></div>
</dialog>
<dialog id="rar-opcoes" class="rar-modal" aria-labelledby="rar-opcoes-titulo">
<h2 id="rar-opcoes-titulo">Consulta</h2>
<form id="rar-opcoes-form" class="rar-fields"><label>Código ou nome<input name="termo" autocomplete="off"></label><button type="submit">Executar</button></form>
<p id="rar-opcoes-status" role="status"></p>
<div class="rar-modal-body" style="overflow:auto;max-height:45vh"><table class="tabela"><thead><tr><th>Código</th><th>Nome</th><th>CPF/CNPJ</th></tr></thead><tbody id="rar-opcoes-linhas"></tbody></table></div>
<div class="rar-modal-actions"><button type="button" id="rar-opcoes-cancelar">Cancelar</button></div>
</dialog>
<script>
(function () {
    var root = document.querySelector('.rar');
    var modal = document.getElementById('rar-consulta');
    var pesquisa = document.getElementById('rar-pesquisa');
    var resultados = document.getElementById('rar-resultados');
    var status = document.getElementById('rar-status');
    var confirmar = document.getElementById('rar-confirmar');
    var selecionado = null;
    var baseConsulta = '';
    var pedido = null;
    var endpoint = <?= json_encode(url('registro-ar'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var opcoes = document.getElementById('rar-opcoes');
    var opcoesForm = document.getElementById('rar-opcoes-form');
    var opcoesLinhas = document.getElementById('rar-opcoes-linhas');
    var opcoesStatus = document.getElementById('rar-opcoes-status');
    var tipoOpcao = '';
    var destinoOpcao = null;
    var requisicaoOpcao = null;
    async function buscarOpcoes() {
        if (requisicaoOpcao) requisicaoOpcao.abort();
        var atual = new AbortController();
        requisicaoOpcao = atual;
        var campo = tipoOpcao;
        opcoesLinhas.replaceChildren();
        opcoesStatus.textContent = 'Consultando...';
        var params = new URLSearchParams({base: baseConsulta, consulta: campo, termo: opcoesForm.elements.termo.value});
        try {
            var response = await fetch(endpoint + '?' + params, {signal: atual.signal});
            if (response.redirected) throw new Error('Sessão expirada. Atualize a página.');
            var dados = await response.json();
            if (!response.ok || dados.erro) throw new Error(dados.erro || 'Falha na consulta.');
            if (requisicaoOpcao !== atual) return;
            dados.linhas.forEach(function (r) {
                var tr = document.createElement('tr');
                var codigo = document.createElement('td');
                var botao = document.createElement('button');
                botao.type = 'button';
                botao.textContent = r.codigo + ' · ' + r.base;
                codigo.append(botao);
                tr.append(codigo);
                [r.nome, r.documento || ''].forEach(function (texto) {
                    var td = document.createElement('td');
                    td.textContent = texto;
                    tr.append(td);
                });
                function escolher() {
                    destinoOpcao.value = r.codigo.trim();
                    destinoOpcao.title = r.nome;
                    destinoOpcao.dispatchEvent(new Event('change', {bubbles: true}));
                    opcoes.close();
                }
                botao.addEventListener('click', escolher);
                tr.addEventListener('dblclick', escolher);
                opcoesLinhas.append(tr);
            });
            opcoesStatus.textContent = dados.linhas.length + ' registros (limite de 200). Clique no código para selecionar.';
        } catch (erro) {
            if (erro.name !== 'AbortError' && requisicaoOpcao === atual) opcoesStatus.textContent = erro.message;
        }
    }
    document.querySelectorAll('[data-rar-lookup]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            tipoOpcao = botao.dataset.rarLookup;
            destinoOpcao = botao.closest('form').elements[tipoOpcao];
            if (!modal.contains(botao)) baseConsulta = root.querySelector('select[name="base"]').value;
            document.getElementById('rar-opcoes-titulo').textContent = tipoOpcao === 'centro' ? 'Consulta Centro de Negócio' : 'Consulta Fornecedor';
            opcoesForm.elements.termo.value = destinoOpcao.value;
            opcoes.showModal();
            buscarOpcoes();
        });
    });
    opcoesForm.addEventListener('submit', function (event) { event.preventDefault(); buscarOpcoes(); });
    document.getElementById('rar-opcoes-cancelar').addEventListener('click', function () { opcoes.close(); });
    opcoes.addEventListener('close', function () { if (requisicaoOpcao) requisicaoOpcao.abort(); });
    function carregarSelecionado() {
        if (!selecionado) return;
        var params = new URLSearchParams(new FormData(pesquisa));
        params.set('base', selecionado.base);
        params.set('id', selecionado.AR_Numero);
        params.set('numero', selecionado.AR_CentroNumero);
        window.location.assign(endpoint + '?' + params.toString());
    }
    async function pesquisar() {
        if (pedido) pedido.abort();
        var atual = new AbortController();
        pedido = atual;
        selecionado = null;
        confirmar.disabled = true;
        resultados.replaceChildren();
        status.textContent = 'Consultando...';
        var params = new URLSearchParams(new FormData(pesquisa));
        params.set('base', baseConsulta);
        params.set('consulta', '1');
        try {
            var response = await fetch(endpoint + '?' + params.toString(), {signal: atual.signal, headers: {'Accept': 'application/json'}});
            if (response.redirected) throw new Error('Sessão expirada. Atualize a página para entrar novamente.');
            var dados = await response.json();
            if (!response.ok || dados.erro) throw new Error(dados.erro || 'Falha na consulta.');
            if (pedido !== atual) return;
            dados.linhas.forEach(function (r) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                var escolher = document.createElement('button');
                escolher.type = 'button';
                escolher.textContent = r.AR_CentroNumero + ' · ' + r.base;
                escolher.setAttribute('aria-pressed', 'false');
                td.append(escolher);
                tr.append(td);
                [String(r.AR_Data || '').slice(0,10).split('-').reverse().join('/'), r.Centro_CodC + ' - ' + r.Centro_Nome, r.Cre_Cod + ' - ' + r.Cre_RazSoc].forEach(function (valor) {
                    var cell = document.createElement('td');
                    cell.textContent = valor;
                    tr.append(cell);
                });
                tr.addEventListener('click', function () {
                    resultados.querySelectorAll('tr').forEach(function (other) {
                        other.style.background = '';
                        other.querySelector('button').setAttribute('aria-pressed', 'false');
                    });
                    tr.style.background = '#dceeff';
                    escolher.setAttribute('aria-pressed', 'true');
                    selecionado = r;
                    confirmar.disabled = false;
                });
                tr.addEventListener('dblclick', carregarSelecionado);
                resultados.append(tr);
            });
            status.textContent = dados.linhas.length + ' registros (limite de 200). Selecione um AR e confirme.';
        } catch (erro) {
            if (erro.name !== 'AbortError' && pedido === atual) status.textContent = erro.message;
        }
    }
    document.getElementById('rar-abrir').addEventListener('click', function () {
        var origem = root.querySelector('form');
        ['numero','centro','fornecedor','ini','fim'].forEach(function (nome) {
            pesquisa.elements[nome].value = origem.elements[nome].value;
        });
        baseConsulta = origem.elements.base.value;
        modal.showModal();
        pesquisar();
    });
    pesquisa.addEventListener('submit', function (event) { event.preventDefault(); pesquisar(); });
    confirmar.addEventListener('click', carregarSelecionado);
    document.getElementById('rar-fechar').addEventListener('click', function () { modal.close(); });
    modal.addEventListener('close', function () { if (pedido) pedido.abort(); });
    root.querySelectorAll('[data-rar-doc]').forEach(function (row) {
        row.addEventListener('click', function () {
            var id = row.dataset.rarDoc;
            root.querySelectorAll('[data-rar-doc]').forEach(function (other) {
                var selected = other === row;
                other.classList.toggle('rar-selected', selected);
                other.querySelector('button').setAttribute('aria-pressed', String(selected));
            });
            root.querySelectorAll('[data-rar-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.rarPanel !== id;
            });
        });
    });
})();
</script>
