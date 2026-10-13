<?php
/*
 * TELA TRAVADA: Autorizacao de Pedidos + Relacao + Resumo/Indicador + botao Imprimir pedidos.
 * Porte fiel de templates/pedidos.html (Python). Nao alterar sem 3 confirmacoes do usuario.
 * O bloco <script> abaixo e copia identica do template Python.
 */
ob_start(); ?>
<div class="topbar-corte">
    <label for="data_corte">Data corte</label>
    <input type="date" id="data_corte" value="<?= e($filtros['data_corte']) ?>">
</div>
<?php $topbar_extra = ob_get_clean(); ?>
<div class="pedidos-container">
<div class="pedidos-coluna">
<form class="filtros" method="get">
    <input type="hidden" id="filtro_data_corte" name="data_corte" value="<?= e($filtros['data_corte']) ?>">
    <div class="campo">
        <label for="data_ini">Data inicial</label>
        <input type="date" id="data_ini" name="data_ini" value="<?= e($filtros['data_ini']) ?>">
    </div>
    <div class="campo">
        <label for="data_fim">Data final</label>
        <input type="date" id="data_fim" name="data_fim" value="<?= e($filtros['data_fim']) ?>">
    </div>
    <div class="campo">
        <label for="pedido">Nº Pedido</label>
        <input type="text" id="pedido" name="pedido" value="<?= e($filtros['pedido']) ?>" placeholder="Ex: 2678">
    </div>
    <div class="campo grow">
        <label for="fornecedor">Fornecedor</label>
        <input type="text" id="fornecedor" name="fornecedor" value="<?= e($filtros['fornecedor']) ?>"
               list="listaFornecedores" placeholder="Digite para buscar" autocomplete="off">
        <datalist id="listaFornecedores">
            <?php foreach ($fornecedores as $f): ?>
            <option value="<?= e($f) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="campo grow">
        <label for="centro">Centro</label>
        <input type="text" id="centro" name="centro" value="<?= e($filtros['centro']) ?>"
               list="listaCentros" placeholder="Digite para buscar" autocomplete="off">
        <datalist id="listaCentros">
            <?php foreach ($centros as $cn): ?>
            <option value="<?= e($cn) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="campo grow">
        <label for="nucleo">Nucleo</label>
        <input type="text" id="nucleo" name="nucleo" value="<?= e($filtros['nucleo'] ?? '') ?>"
               list="listaNucleos" placeholder="Digite para buscar" autocomplete="off">
        <datalist id="listaNucleos">
            <?php foreach ($nucleos as $nc): ?>
            <option value="<?= e($nc) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <div class="campo grow">
        <label for="insumo">Descricao de Insumo</label>
        <input type="text" id="insumo" name="insumo" value="<?= e($filtros['insumo'] ?? '') ?>"
               placeholder="Ex: argamassa" autocomplete="off">
    </div>
    <div class="campo">
        <label for="situacao">Situacao</label>
        <select id="situacao" name="situacao">
            <option value="">Todos</option>
            <option value="aut" <?= ($filtros['situacao'] ?? '') === 'aut' ? 'selected' : '' ?>>Somente autorizados</option>
            <option value="naut" <?= ($filtros['situacao'] ?? '') === 'naut' ? 'selected' : '' ?>>Somente nao autorizados</option>
        </select>
    </div>
    <div class="campo acoes">
        <button type="submit" class="btn">Filtrar</button>
        <a href="<?= url('pedidos') ?>" class="btn btn-secundario">Limpar</a>
    </div>
</form>

<?php if ($erro): ?>
<div class="alert-erro" style="margin-top:16px"><?= e($erro) ?></div>
<?php endif; ?>

<div class="resumo-grid" style="margin: 16px 0;">
    <strong><?= e($total) ?></strong> pedido(s) encontrado(s)
</div>

<?php foreach ($grupos as $grupo): ?>
<div class="grupo">
    <div class="grupo-titulo grupo-toggle"><input type="checkbox" class="grupo-check"
        title="Marcar/desmarcar todos os pedidos deste centro"><span class="grupo-nome"><?= e($grupo['nome']) ?></span><span class="grupo-seta">&#9662;</span></div>
    <table class="tabela tabela-hover">
        <thead>
            <tr>
                <th class="col-check"></th>
                <th>Nº Pedido</th>
                <th>Data</th>
                <th>Fornecedor</th>
                <th>Nº Coleta</th>
                <th>Autorizante</th>
                <th>Data Autorizacao</th>
                <th>Base</th>
                <th class="col-valor">Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupo['pedidos'] as $p): ?>
            <tr data-pedido="<?= e($p['Pedido_Numero']) ?>" data-base="<?= e($p['Base']) ?>"
                data-centro="<?= e($p['Centro'] ?? '') ?>"
                data-instrucao="<?= $p['Instrucao_Data'] ? e(substr($p['Instrucao_Data'], 0, 10)) : '' ?>"
                data-valor="<?= e($p['Total_Pedido']) ?>">
                <td class="col-check"><input type="checkbox" class="row-check" value="<?= e($p['Base']) ?>:<?= e($p['Pedido_Numero']) ?>"></td>
                <td><a href="<?= e(url('pedidos/detalhe?base=' . $p['Base'] . '&pedido=' . $p['Pedido_Numero'])) ?>"
                       class="link-pedido" data-janela="ped<?= e($p['Base'] . $p['Pedido_Numero']) ?>"><?= $p['Pedido_CentroNumero'] ? e($p['Pedido_CentroNumero']) : '-' ?></a></td>
                <td><?= e(data_br($p['Pedido_Data'])) ?></td>
                <td><?= e($p['Fornecedor']) ?></td>
                <td><?= $p['Coleta_Numero'] ? e($p['Coleta_Numero']) : '-' ?></td>
                <td><?= $p['Autorizante'] ? e($p['Autorizante']) : '-' ?></td>
                <td><?= e(data_br($p['Instrucao_Data'])) ?></td>
                <td><span class="badge badge-<?= $p['Base'] === 'CONV' ? 'ativo' : 'pendente' ?>"><?= e($p['Base']) ?></span></td>
                <td class="col-valor"><?= $p['Total_Pedido'] !== null ? 'R$ ' . e(number_format((float)$p['Total_Pedido'], 2, ',', '.')) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<div class="resumo-painel" id="resumoPainel">
    <h3>Resumo / Indicador</h3>
    <p class="resumo-ajuda">Avalia apenas os pedidos com checkbox marcado.</p>
    <div class="resumo-cards">
        <div class="resumo-card">
            <div class="resumo-label">Total de pedidos</div>
            <div class="resumo-valor" id="resTotal">0</div>
        </div>
        <div class="resumo-card">
            <div class="resumo-label">Autorizados antecipadamente</div>
            <div class="resumo-valor" id="resAnt">0 <span class="resumo-pct">(0%)</span></div>
        </div>
        <div class="resumo-card">
            <div class="resumo-label">Autorizados no prazo / nao autorizados</div>
            <div class="resumo-valor" id="resPrazo">0 <span class="resumo-pct">(0%)</span></div>
        </div>
    </div>
    <h4 style="margin-top:22px">Por centro de custo</h4>
    <table class="tabela" id="resPorCentro">
        <thead>
            <tr><th>Centro de custo</th><th>Total</th><th>Antecipado</th><th>%</th><th>No prazo / Nao autorizado</th><th>%</th></tr>
        </thead>
        <tbody>
            <tr><td colspan="6" class="resumo-vazio">Selecione pedidos para ver o resumo</td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

<div class="pedidos-rodape">
    <div class="rodape-totais">
        <span id="rodapeQtd">0 pedidos selecionados</span>
        <span id="rodapeValor">R$ 0,00</span>
    </div>
    <div class="rodape-botoes">
        <button type="button" class="btn" onclick="imprimirSelecionados()" title="Clique aqui para imprimir a relação dos pedidos selecionados.">Imprimir Relação</button>
        <button type="button" class="btn" onclick="imprimirTodos()" title="Clique aqui para imprimir os pedidos selecionados">Imprimir pedidos</button>
        <button type="button" class="btn" onclick="imprimirResumo()" title="Clique aqui para imprimir o Resumo/Indicador dos pedidos">Imprimir resumo</button>
    </div>
</div>

<script>
(function () {
    function hojeISO() {
        return new Date().toISOString().slice(0, 10);
    }

    function fmtPct(qtd, total) {
        if (!total) return '(0%)';
        return '(' + ((qtd / total) * 100).toFixed(1).replace('.', ',') + '%)';
    }

    function atualizarResumo() {
        const corte = (document.getElementById('data_corte')?.value) || hojeISO();
        const marcados = document.querySelectorAll('.row-check:checked');
        let total = 0, antecipado = 0, prazo = 0;
        const porCentro = {};

        marcados.forEach(cb => {
            const row = cb.closest('tr');
            const centro = row.dataset.centro || 'Sem centro';
            const inst = row.dataset.instrucao || '';
            const ehAntecipado = inst && inst < corte;

            total++;
            if (ehAntecipado) antecipado++; else prazo++;

            if (!porCentro[centro]) porCentro[centro] = { total: 0, ant: 0, prazo: 0 };
            porCentro[centro].total++;
            if (ehAntecipado) porCentro[centro].ant++; else porCentro[centro].prazo++;
        });

        document.getElementById('resTotal').textContent = total;
        document.getElementById('resAnt').innerHTML = antecipado + ' <span class="resumo-pct">' + fmtPct(antecipado, total) + '</span>';
        document.getElementById('resPrazo').innerHTML = prazo + ' <span class="resumo-pct">' + fmtPct(prazo, total) + '</span>';

        window._resumoValores = { antecipado: antecipado, prazo: prazo };

        const tbody = document.querySelector('#resPorCentro tbody');
        if (!total) {
            tbody.innerHTML = '<tr><td colspan="6" class="resumo-vazio">Selecione pedidos para ver o resumo</td></tr>';
            return;
        }

        const centros = Object.keys(porCentro).sort();
        tbody.innerHTML = centros.map(c => {
            const d = porCentro[c];
            return '<tr>' +
                '<td>' + c + '</td>' +
                '<td>' + d.total + '</td>' +
                '<td>' + d.ant + '</td>' +
                '<td>' + fmtPct(d.ant, d.total) + '</td>' +
                '<td>' + d.prazo + '</td>' +
                '<td>' + fmtPct(d.prazo, d.total) + '</td>' +
                '</tr>';
        }).join('');
    }

    const dataCorte = document.getElementById('data_corte');
    if (dataCorte) dataCorte.addEventListener('change', () => {
        document.getElementById('filtro_data_corte').value = dataCorte.value;
        atualizarResumo();
    });

    // --- selecao multipla com Shift + click no checkbox ---
    const checkboxes = Array.from(document.querySelectorAll('.row-check'));
    let lastIndex = null;

    function marcarIntervalo(inicio, fim, estado) {
        const a = Math.min(inicio, fim);
        const b = Math.max(inicio, fim);
        for (let i = a; i <= b; i++) {
            checkboxes[i].checked = estado;
        }
    }

    checkboxes.forEach((cb, idx) => {
        cb.addEventListener('click', e => {
            e.stopPropagation();
            if (e.shiftKey && lastIndex !== null && lastIndex !== idx) {
                marcarIntervalo(lastIndex, idx, cb.checked);
            }
            lastIndex = idx;
            atualizarResumo();
        });
    });

    // --- checkbox do grupo: marca/desmarca todos os pedidos do centro ---
    function atualizarCheckGrupos() {
        document.querySelectorAll('.grupo-check').forEach(g => {
            const itens = g.closest('.grupo').querySelectorAll('.row-check');
            const marc = g.closest('.grupo').querySelectorAll('.row-check:checked');
            g.checked = itens.length > 0 && marc.length === itens.length;
            g.indeterminate = marc.length > 0 && marc.length < itens.length;
        });
    }

    document.querySelectorAll('.grupo-check').forEach(g => {
        g.addEventListener('click', e => e.stopPropagation());
        g.addEventListener('change', () => {
            g.indeterminate = false;
            g.closest('.grupo').querySelectorAll('.row-check')
                .forEach(cb => { cb.checked = g.checked; });
            atualizarResumo();
        });
    });

    // --- titulo do grupo recolhe/expande a tabela (dropdown) ---
    document.querySelectorAll('.grupo-toggle').forEach(t => {
        t.addEventListener('click', e => {
            if (e.target.closest('.grupo-check')) return;
            t.closest('.grupo').classList.toggle('recolhido');
        });
    });

    // --- clique na linha (ou no numero) abre a ficha do pedido em janela nova ---
    function moeda(v) {
        if (v == null || isNaN(v)) return '-';
        return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function abrirFicha(link) {
        window.open(link.href, link.dataset.janela, 'width=1280,height=800');
    }

    document.querySelectorAll('.link-pedido').forEach(a => {
        a.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            abrirFicha(a);
        });
    });

    document.querySelectorAll('.tabela-hover tbody tr').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('.row-check')) return;
            const link = row.querySelector('.link-pedido');
            if (link) abrirFicha(link);
        });
    });

    function atualizarRodape() {
        const marcados = document.querySelectorAll('.row-check:checked');
        let qtd = 0, valor = 0;
        marcados.forEach(cb => {
            qtd++;
            valor += parseFloat(cb.closest('tr').dataset.valor || '0') || 0;
        });
        document.getElementById('rodapeQtd').textContent =
            qtd + ' pedido' + (qtd === 1 ? '' : 's') + ' selecionado' + (qtd === 1 ? '' : 's');
        document.getElementById('rodapeValor').textContent = moeda(valor);
    }

    function abrirJanelaImpressao(titulo, corpo) {
        const w = window.open('', 'print', 'width=950,height=720');
        w.document.write(
            '<html><head><title>' + titulo + '</title>' +
            '<style>body{font-family:Arial,sans-serif;padding:20px} ' +
            'table{border-collapse:collapse;width:100%;margin-top:10px} ' +
            'th,td{border:1px solid #ccc;padding:6px;text-align:left;font-size:13px} ' +
            'th{background:#1e3a5f;color:#fff} ' +
            '.total{font-weight:700;margin-top:14px;font-size:15px} ' +
            '.grupo-centro{font-size:14px;margin:18px 0 0;padding:6px 8px;background:#e8eef5;color:#1e3a5f;border-left:4px solid #1e3a5f} ' +
            '.grupo-centro + table{margin-top:0} ' +
            'tr.subtotal td{font-weight:700;background:#f5f5f5} ' +
            'table{page-break-inside:auto} tr{page-break-inside:avoid} ' +
            '.relatorio-topo{display:flex;justify-content:space-between;gap:20px;align-items:center;margin:10px 0 18px} ' +
            '.relatorio-resumo{flex:1;display:flex;flex-direction:column;gap:10px;text-align:left} ' +
            '.relatorio-linha{font-size:15px} ' +
            '.relatorio-linha strong{color:#1e3a5f} ' +
            '.relatorio-label{color:#333} ' +
            '.relatorio-pct{font-size:13px;color:#666} ' +
            '.relatorio-grafico{width:280px;flex-shrink:0;display:flex;align-items:center;justify-content:center;gap:12px} ' +
            '.relatorio-legenda{font-size:12px;display:flex;flex-direction:column;gap:4px} ' +
            '.relatorio-legenda span{width:12px;height:12px;border-radius:2px;display:inline-block;margin-right:5px} ' +
            '</style>' +
            '</head><body>' + corpo + '</body></html>'
        );
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 350);
    }

    function imprimirSelecionados() {
        const rows = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.closest('tr'));
        if (!rows.length) {
            alert('Selecione pelo menos um pedido.');
            return;
        }
        imprimirLista(rows, 'Pedidos selecionados');
    }

    function imprimirTodos() {
        const marcados = Array.from(document.querySelectorAll('.row-check:checked'));
        if (!marcados.length) {
            alert('Selecione pelo menos um pedido.');
            return;
        }
        const qs = marcados.map(cb => 'p=' + encodeURIComponent(cb.value)).join('&');
        window.open('/pedidos/imprimir?' + qs, '_blank');
    }

    function imprimirLista(rows, titulo) {
        const grupos = {};
        rows.forEach(row => {
            const centro = row.dataset.centro || 'Sem centro';
            (grupos[centro] = grupos[centro] || []).push(row);
        });

        let total = 0, qtdTotal = 0, html = '';
        Object.keys(grupos).sort().forEach(centro => {
            let subtotal = 0;
            html += '<h3 class="grupo-centro">' + centro + '</h3>' +
                '<table><thead><tr>' +
                '<th>Nº Pedido</th><th>Data</th><th>Fornecedor</th><th>Autorizante</th>' +
                '<th>Data Autorizacao</th><th>Base</th><th>Valor</th>' +
                '</tr></thead><tbody>';
            grupos[centro].forEach(row => {
                const valor = parseFloat(row.dataset.valor || '0') || 0;
                subtotal += valor;
                html += '<tr>' +
                    '<td>' + row.cells[1].innerText + '</td>' +
                    '<td>' + row.cells[2].innerText + '</td>' +
                    '<td>' + row.cells[3].innerText + '</td>' +
                    '<td>' + row.cells[5].innerText + '</td>' +
                    '<td>' + row.cells[6].innerText + '</td>' +
                    '<td>' + row.cells[7].innerText + '</td>' +
                    '<td>' + moeda(valor) + '</td>' +
                    '</tr>';
            });
            html += '<tr class="subtotal"><td colspan="6">Subtotal (' + grupos[centro].length + ' pedido' +
                (grupos[centro].length === 1 ? '' : 's') + ')</td><td>' + moeda(subtotal) + '</td></tr>' +
                '</tbody></table>';
            total += subtotal;
            qtdTotal += grupos[centro].length;
        });
        html += '<p class="total">Total geral (' + qtdTotal + ' pedido' + (qtdTotal === 1 ? '' : 's') + '): ' + moeda(total) + '</p>';
        abrirJanelaImpressao(titulo, '<h2>' + titulo + '</h2>' + html);
    }

    function gerarGraficoSVG(antecipado, prazo, size) {
        const total = antecipado + prazo;
        if (!total) return '';
        const cx = size / 2;
        const r = (size / 2) - 1;
        const slices = [
            { value: antecipado, color: '#ef4444' },
            { value: prazo, color: '#22c55e' }
        ];
        let startAngle = -Math.PI / 2;
        let paths = '';
        slices.forEach(s => {
            if (!s.value) return;
            const angle = (s.value / total) * 2 * Math.PI;
            const endAngle = startAngle + angle;
            const x1 = cx + r * Math.cos(startAngle);
            const y1 = cx + r * Math.sin(startAngle);
            const x2 = cx + r * Math.cos(endAngle);
            const y2 = cx + r * Math.sin(endAngle);
            const largeArc = angle > Math.PI ? 1 : 0;
            paths += `<path d="M${cx},${cx} L${x1.toFixed(2)},${y1.toFixed(2)} A${r},${r} 0 ${largeArc},1 ${x2.toFixed(2)},${y2.toFixed(2)} Z" fill="${s.color}" stroke="#fff" stroke-width="1"/>`;
            startAngle = endAngle;
        });
        return `<svg width="${size}" height="${size}" style="flex-shrink:0">${paths}</svg>`;
    }

    function gerarGraficoComLegenda(antecipado, prazo, size) {
        const total = antecipado + prazo;
        if (!total) return '';
        const antPct = ((antecipado / total) * 100).toFixed(1).replace('.', ',') + '%';
        const prazoPct = ((prazo / total) * 100).toFixed(1).replace('.', ',') + '%';
        return '<div class="relatorio-grafico">' +
            gerarGraficoSVG(antecipado, prazo, size) +
            '<div class="relatorio-legenda">' +
            '<div><span style="background:#ef4444"></span>Antecipado: ' + antecipado + ' (' + antPct + ')</div>' +
            '<div><span style="background:#22c55e"></span>No prazo / Nao autorizado: ' + prazo + ' (' + prazoPct + ')</div>' +
            '</div></div>';
    }

    function imprimirResumo() {
        const vals = window._resumoValores || { antecipado: 0, prazo: 0 };
        const total = vals.antecipado + vals.prazo;
        const antPct = fmtPct(vals.antecipado, total);
        const prazoPct = fmtPct(vals.prazo, total);

        const cardsHtml = '<div class="relatorio-resumo">' +
            '<div class="relatorio-linha"><span class="relatorio-label">Total de pedidos:</span> <strong>' + total + '</strong></div>' +
            '<div class="relatorio-linha"><span class="relatorio-label">Autorizados antecipadamente:</span> <strong>' + vals.antecipado + '</strong> <span class="relatorio-pct">' + antPct + '</span></div>' +
            '<div class="relatorio-linha"><span class="relatorio-label">Autorizados no prazo / nao autorizados:</span> <strong>' + vals.prazo + '</strong> <span class="relatorio-pct">' + prazoPct + '</span></div>' +
            '</div>';

        const topoHtml = '<div class="relatorio-topo">' + cardsHtml + gerarGraficoComLegenda(vals.antecipado, vals.prazo, 180) + '</div>';

        const tabela = document.getElementById('resPorCentro');
        const tabelaHtml = tabela ? tabela.outerHTML : '';

        abrirJanelaImpressao('Resumo / Indicador', '<h2>Resumo / Indicador</h2>' + topoHtml + '<h3>Por centro de custo</h3>' + tabelaHtml);
    }

    window.imprimirSelecionados = imprimirSelecionados;
    window.imprimirTodos = imprimirTodos;
    window.imprimirResumo = imprimirResumo;

    // Hook para atualizar rodape junto com resumo
    const originalAtualizarResumo = atualizarResumo;
    atualizarResumo = function () {
        originalAtualizarResumo();
        atualizarRodape();
        atualizarCheckGrupos();
    };

    atualizarResumo();
})();
</script>
