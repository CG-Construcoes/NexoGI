<?php
/**
 * Ultimas Compras — textbox localiza o insumo (codigo + descricao reduzida);
 * o grid superior lista as compras daquele insumo no periodo (checkbox);
 * o grid inferior mostra apenas as linhas selecionadas.
 * Botao direito: exportar selecionados para Excel / abrir pedido.
 */
?>
<div class="pedidos-coluna">

<form class="atv-fbar" method="get" style="margin-bottom:10px" onsubmit="return false">
    <div class="atv-frow">
        <select id="ucBase">
            <option value="TODOS" <?= $base === 'TODOS' ? 'selected' : '' ?>>Todos</option>
            <?php foreach (uc_bases() as $b => $db): ?>
                <option value="<?= e($b) ?>" <?= $base === $b ? 'selected' : '' ?>><?= e($b) ?></option>
            <?php endforeach; ?>
        </select>
        <label style="white-space:nowrap">Periodo</label>
        <input type="number" id="ucMeses" min="1" max="120" value="12" style="width:64px">
        <span style="white-space:nowrap">meses</span>
        <div class="uc-combo">
            <input type="text" id="ucBusca" autocomplete="off"
                   placeholder="Digite o codigo ou a descricao do insumo">
            <div class="uc-combo-lista" id="ucLista"></div>
        </div>
    </div>
</form>

<div class="tabela-responsiva uc-top">
    <table class="tabela">
        <thead>
            <tr>
                <th style="width:28px"></th>
                <th>Codigo</th>
                <th>Tipo</th>
                <th>Data</th>
                <th>Documento</th>
                <th>Centro</th>
                <th>Credor</th>
                <th style="text-align:right">Qtd</th>
                <th>Und</th>
                <th style="text-align:right">Vl Unit</th>
            </tr>
        </thead>
        <tbody id="ucTopBody">
            <tr><td colspan="10" class="tabela-vazio">Localize um insumo acima para listar as compras.</td></tr>
        </tbody>
    </table>
</div>

<div class="uc-sel-tit">Itens selecionados <span id="ucSelCount">(0)</span></div>
<div class="tabela-responsiva uc-bot">
    <table class="tabela">
        <thead>
            <tr>
                <th style="width:28px"></th>
                <th>Codigo</th>
                <th>Data</th>
                <th>Credor</th>
                <th>Item</th>
                <th style="text-align:right">Qtde</th>
                <th>Und</th>
                <th style="text-align:right">Vl Unit</th>
            </tr>
        </thead>
        <tbody id="ucBody">
            <tr id="ucVazio"><td colspan="8" class="tabela-vazio">Marque linhas no grid superior.</td></tr>
        </tbody>
    </table>
</div>
</div>

<div class="uc-menu" id="ucMenu">
    <div class="uc-menu-item" id="ucMenuXls">Exportar selecionados para Excel</div>
    <div class="uc-menu-item" id="ucMenuPed" style="display:none">Abrir pedido</div>
</div>

<style>
.uc-combo { position: relative; flex: 1; min-width: 260px; }
.uc-combo input { width: 100%; }
.uc-combo-lista {
    display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
    max-height: 280px; overflow-y: auto; background: var(--bg-panel, #fff);
    border: 1px solid var(--border-strong, #ccc); border-radius: 6px;
    box-shadow: 0 6px 18px rgba(0,0,0,.18); font-size: 13px;
}
.uc-combo-lista.aberta { display: block; }
.uc-op { padding: 7px 10px; cursor: pointer; display: flex; gap: 8px; align-items: baseline; }
.uc-op:hover { background: var(--primary-100, #e8f0fe); }
.uc-op .uc-cod { font-weight: 700; white-space: nowrap; }
.uc-op .uc-base { color: var(--text-muted, #888); font-size: 11px; }
body.dark .uc-combo-lista { background: #1b2333; border-color: #38486a; }
body.dark .uc-op:hover { background: #1e2f52; }
.uc-sel-tit { font-weight: 700; font-size: 13px; margin: 12px 0 6px; color: var(--text-main); }
.uc-top { max-height: 340px; overflow-y: auto; }
.uc-top .tabela { font-size: 10px; }
.uc-top tbody tr { cursor: pointer; }
.uc-top tbody tr.uc-marcado td { background: var(--primary-100, #e8f0fe); }
body.dark .uc-top tbody tr.uc-marcado td { background: #1e2f52; }
.uc-bot { max-height: 34vh; overflow-y: auto; }
.uc-bot .tabela { font-size: 10px; }
.uc-tipo { font-weight: 700; }
.uc-tipo-ar { color: #15803d; }
.uc-tipo-ap { color: #b45309; }
.uc-tipo-pa, .uc-tipo-pn { color: #1d4ed8; }
.uc-menu {
    display: none; position: fixed; z-index: 200; min-width: 220px;
    background: var(--bg-panel, #fff); border: 1px solid var(--border-strong, #ccc);
    border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.22);
    font-size: 13px; padding: 4px 0;
}
.uc-menu.aberto { display: block; }
.uc-menu-item { padding: 8px 14px; cursor: pointer; }
.uc-menu-item:hover { background: var(--primary-100, #e8f0fe); }
body.dark .uc-menu { background: #1b2333; border-color: #38486a; }
body.dark .uc-menu-item:hover { background: #1e2f52; }
</style>

<script>
(function () {
    var base = document.getElementById("ucBase"),
        inp = document.getElementById("ucBusca"),
        lista = document.getElementById("ucLista"),
        meses = document.getElementById("ucMeses"),
        topBody = document.getElementById("ucTopBody"),
        body = document.getElementById("ucBody"),
        menu = document.getElementById("ucMenu"),
        timer = null, n = 0, selecionados = {},
        urlBusca = "<?= e(url('ultimas-compras/busca')) ?>",
        urlCompras = "<?= e(url('ultimas-compras/compras')) ?>";

    var TIPOS = {AR: "AR recebido", AP: "AR parcial", PA: "Pedido autorizado", PN: "Pedido nao autorizado"};

    function fecharLista() { lista.classList.remove("aberta"); lista.innerHTML = ""; }
    function fecharMenu() { menu.classList.remove("aberto"); }
    function fmtData(s) { return (s || "").substr(0, 10).split("-").reverse().join("/"); }
    function fmtTotal(c) {
        var t = c.EntItem_TotalItem_Final != null ? c.EntItem_TotalItem_Final : c.EntItem_TotalItem;
        return Number(t || 0);
    }
    function fmtQtd(c) {
        return Number(c.EntItem_QuantidadeRecebida || c.EntItem_Quantidade || 0)
            .toLocaleString("pt-BR", {maximumFractionDigits: 4});
    }
    function fmtUnit(c) {
        var q = Number(c.EntItem_QuantidadeRecebida || c.EntItem_Quantidade || 0);
        return q ? fmtTotal(c) / q : 0;
    }

    /* ---------- textbox: insumos ---------- */
    function buscar(q) {
        lista.innerHTML = '<div class="uc-op">buscando…</div>';
        lista.classList.add("aberta");
        fetch(urlBusca + "?base=" + encodeURIComponent(base.value) + "&q=" + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp || !resp.ok || !resp.itens.length) {
                    lista.innerHTML = '<div class="uc-op">nenhum insumo encontrado</div>';
                    return;
                }
                lista.innerHTML = "";
                resp.itens.forEach(function (it) {
                    var d = document.createElement("div");
                    d.className = "uc-op";
                    d.innerHTML = '<span class="uc-cod">' + it.codigo + "</span>" +
                        '<span class="uc-base">' + it.base + "</span>" +
                        "<span>" + it.descricao + "</span>";
                    d.onmousedown = function () { escolherInsumo(it); };
                    lista.appendChild(d);
                });
            })
            .catch(function () { lista.innerHTML = '<div class="uc-op">falha na busca</div>'; });
    }

    inp.addEventListener("input", function () {
        clearTimeout(timer);
        var q = inp.value.trim();
        if (q.length < 2) { fecharLista(); return; }
        timer = setTimeout(function () { buscar(q); }, 300);
    });
    inp.addEventListener("blur", function () { setTimeout(fecharLista, 150); });
    inp.addEventListener("keydown", function (ev) {
        if (ev.key === "Enter") {
            ev.preventDefault();
            var op = lista.querySelector(".uc-op");
            if (op && op.onmousedown) { op.onmousedown(); }
        }
        if (ev.key === "Escape") { fecharLista(); }
    });

    /* ---------- grid superior: compras do insumo ---------- */
    function linhaTopo(c, k) {
        var tr = document.createElement("tr");
        tr.dataset.k = k;
        var tipo = c.Tipo || "", cls = "uc-tipo-" + tipo.toLowerCase();
        var kSel = k in selecionados;
        var vazio = !!c._vazio;
        tr.innerHTML = '<td><input type="checkbox" class="uc-chk"' + (kSel ? " checked" : "") + "></td>" +
            "<td>" + (c.Insumo_CodC || "") + "</td>" +
            '<td class="uc-tipo ' + cls + '" title="' + (TIPOS[tipo] || tipo) + '">' + tipo + "</td>" +
            "<td>" + (vazio ? "" : fmtData(c.Ent_DocData || c.AR_Data)) + "</td>" +
            "<td>" + (c.AR_CentroNumero || c.AR_Numero || "") + "</td>" +
            "<td>" + (vazio ? '<span style="color:var(--text-muted)">' + (c._desc || "") + "</span>" : (c.Centro_CodC || "") + " " + (c.Centro_Nome || "")) + "</td>" +
            "<td>" + (c.Cre_RazSoc || "") + "</td>" +
            '<td style="text-align:right">' + (vazio ? "" : fmtQtd(c)) + "</td>" +
            "<td>" + (c.EntItem_Unidade || "") + "</td>" +
            '<td style="text-align:right">' + (vazio ? "" : fmtUnit(c).toLocaleString("pt-BR", {minimumFractionDigits: 2, maximumFractionDigits: 2})) + "</td>";
        if (kSel) { tr.classList.add("uc-marcado"); }
        tr._compra = c;
        return tr;
    }

    function escolherInsumo(it) {
        inp.value = it.codigo + " " + it.descricao;
        fecharLista();
        topBody.innerHTML = '<tr><td colspan="10" class="tabela-vazio">carregando compras…</td></tr>';
        fetch(urlCompras + "?base=" + encodeURIComponent(it.base) +
            "&insumo=" + it.insumo + "&sub=-1" +
            "&meses=" + encodeURIComponent(meses.value || 12))
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                var linhas = (resp && resp.ok) ? (resp.linhas || []) : [];
                topBody.innerHTML = "";
                if (!linhas.length) {
                    topBody.innerHTML = '<tr><td colspan="10" class="tabela-vazio">' +
                        (resp && resp.ok ? "sem compras no periodo" : "falha: " + (resp && resp.erro || "?")) + "</td></tr>";
                    if (resp && resp.ok &&
                        confirm("Item sem valor no periodo, deseja adicionar no grid abaixo?")) {
                        adicionar(it.base + "|" + it.insumo + "|vazio", {
                            _vazio: true, _base: it.base, _desc: it.descricao,
                            Insumo_CodC: it.codigo
                        });
                    }
                    return;
                }
                linhas.forEach(function (c, i) {
                    c._base = it.base;
                    topBody.appendChild(linhaTopo(c, it.base + "|" + it.insumo + "|" + i));
                });
            })
            .catch(function () {
                topBody.innerHTML = '<tr><td colspan="10" class="tabela-vazio">falha ao carregar compras</td></tr>';
            });
    }

    /* ---------- grid inferior: linhas selecionadas ---------- */
    function atualizarContador() {
        document.getElementById("ucSelCount").textContent = "(" + n + ")";
        if (n === 0) {
            body.innerHTML = '<tr id="ucVazio"><td colspan="8" class="tabela-vazio">Marque linhas no grid superior.</td></tr>';
        }
    }

    function linhaSel(c, k) {
        var tr = document.createElement("tr");
        tr.dataset.k = k;
        var vazio = !!c._vazio;
        tr.innerHTML = '<td><input type="checkbox" class="uc-chk" checked title="Desmarcar remove da lista"></td>' +
            "<td>" + (c.Insumo_CodC || "") + "</td>" +
            "<td>" + (vazio ? "" : fmtData(c.Ent_DocData || c.AR_Data)) + "</td>" +
            "<td>" + (c.Cre_RazSoc || "") + "</td>" +
            "<td>" + (vazio ? (c._desc || "") : (c.EntItem_Especificacao || "")) + "</td>" +
            '<td style="text-align:right">' + (vazio ? "" : fmtQtd(c)) + "</td>" +
            "<td>" + (c.EntItem_Unidade || "") + "</td>" +
            '<td style="text-align:right">' + (vazio ? "" : fmtUnit(c).toLocaleString("pt-BR", {minimumFractionDigits: 2, maximumFractionDigits: 2})) + "</td>";
        tr._compra = c;
        tr.querySelector(".uc-chk").addEventListener("change", function () {
            if (!this.checked) { remover(k); }
        });
        return tr;
    }

    function adicionar(k, c) {
        selecionados[k] = c;
        n++;
        var vazio = document.getElementById("ucVazio");
        if (vazio) { vazio.remove(); }
        body.appendChild(linhaSel(c, k));
        atualizarContador();
    }

    function remover(k) {
        delete selecionados[k];
        n--;
        body.querySelectorAll('tr[data-k="' + k + '"]').forEach(function (r) { r.remove(); });
        var tr = topBody.querySelector('tr[data-k="' + k + '"]');
        if (tr) {
            tr.classList.remove("uc-marcado");
            var c = tr.querySelector(".uc-chk");
            if (c) { c.checked = false; }
        }
        atualizarContador();
    }

    topBody.addEventListener("change", function (ev) {
        var chk = ev.target.closest(".uc-chk");
        if (!chk) { return; }
        var tr = chk.closest("tr[data-k]");
        if (!tr) { return; }
        if (chk.checked) { tr.classList.add("uc-marcado"); adicionar(tr.dataset.k, tr._compra); }
        else { remover(tr.dataset.k); }
    });
    topBody.addEventListener("click", function (ev) {
        var tr = ev.target.closest("tr[data-k]");
        if (!tr || ev.target.type === "checkbox") { return; }
        var chk = tr.querySelector(".uc-chk");
        chk.checked = !chk.checked;
        chk.dispatchEvent(new Event("change", {bubbles: true}));
    });

    meses.addEventListener("change", function () {
        var m = parseInt(meses.value, 10);
        if (!m || m < 1) { meses.value = 12; }
    });
    base.addEventListener("change", function () {
        inp.value = "";
        topBody.innerHTML = '<tr><td colspan="10" class="tabela-vazio">Localize um insumo acima para listar as compras.</td></tr>';
    });

    /* ---------- botao direito ---------- */
    var linhaMenu = null;
    body.addEventListener("contextmenu", function (ev) {
        var tr = ev.target.closest("tr[data-k]");
        if (!tr) { return; }
        ev.preventDefault();
        var c = selecionados[tr.dataset.k];
        linhaMenu = c && c.Pedido_Numero ? {pedido: c.Pedido_Numero, base: c._base} : null;
        document.getElementById("ucMenuPed").style.display = linhaMenu ? "block" : "none";
        menu.style.left = Math.min(ev.clientX, innerWidth - 240) + "px";
        menu.style.top = Math.min(ev.clientY, innerHeight - 90) + "px";
        menu.classList.add("aberto");
    });
    document.addEventListener("click", fecharMenu);
    document.addEventListener("keydown", function (ev) { if (ev.key === "Escape") { fecharMenu(); } });

    document.getElementById("ucMenuXls").addEventListener("click", function () {
        fecharMenu();
        var keys = Object.keys(selecionados);
        if (!keys.length) { return; }
        var csv = ["sep=;", "Base;Codigo;Data;Credor;Item;Qtde;Und;Vl Unit"];
        keys.forEach(function (k) {
            var c = selecionados[k];
            if (c._vazio) {
                csv.push([c._base || "", c.Insumo_CodC || "", "", "",
                    '"' + String(c._desc || "").replace(/"/g, '""') + '"', "", "", ""].join(";"));
                return;
            }
            csv.push([
                c._base || "",
                c.Insumo_CodC || "",
                fmtData(c.Ent_DocData || c.AR_Data),
                '"' + String(c.Cre_RazSoc || "").replace(/"/g, '""') + '"',
                '"' + String(c.EntItem_Especificacao || "").replace(/"/g, '""') + '"',
                String(Number(c.EntItem_QuantidadeRecebida || c.EntItem_Quantidade || 0)).replace(".", ","),
                c.EntItem_Unidade || "",
                String(fmtUnit(c).toFixed(2)).replace(".", ",")
            ].join(";"));
        });
        var blob = new Blob(["\uFEFF" + csv.join("\r\n")], {type: "text/csv;charset=utf-8"});
        var a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "ultimas_compras.csv";
        a.click();
        URL.revokeObjectURL(a.href);
    });
    document.getElementById("ucMenuPed").addEventListener("click", function () {
        fecharMenu();
        if (linhaMenu) {
            open("<?= e(url('pedidos/detalhe')) ?>?base=" + encodeURIComponent(linhaMenu.base) +
                "&pedido=" + encodeURIComponent(linhaMenu.pedido), "_blank");
        }
    });
})();
</script>

<!-- ===================== TOUR GUIADO (?) ===================== -->
<button type="button" class="atv-tour-btn" id="ucTourBtn" title="Como usar esta tela">?</button>
<script>
(function () {
    var PASSOS = [
        {sel: "#ucBase", txt: "<b>Base</b>: <i>Todos</i> (padrao) busca o insumo nas duas bases ERP (MGA e CONV); escolha uma base para restringir."},
        {sel: "#ucMeses", txt: "<b>Periodo</b>: quantos meses de historico de precos considerar (padrao: 12). Para historico completo, use um numero alto (ex.: 120)."},
        {sel: "#ucBusca", txt: "<b>Localizar insumo</b>: digite o <b>codigo</b> (ex.: 010020) ou parte da <b>descricao</b> (ex.: cimento) e escolha na lista. O insumo define o que o grid de cima mostra."},
        {sel: ".uc-top", txt: "<b>Grid superior</b>: todas as compras do insumo no periodo — tipo (AR recebido, AP parcial, PA/PN pedido), data, documento, centro, credor, quantidade e <b>valor unitario</b>. <b>Marque o checkbox</b> (ou clique na linha) das compras que interessam."},
        {sel: ".uc-bot", txt: "<b>Grid inferior</b>: sua selecao — so as linhas marcadas, acumuladas mesmo trocando de insumo. Desmarcar aqui ou em cima remove da lista."},
        {sel: "#ucSelCount", txt: "<b>Exportar</b>: no grid inferior, <b>botao direito</b> sobre uma linha abre o menu — exporta os selecionados para Excel (CSV) ou abre o pedido vinculado. Se o insumo nao tiver preco no periodo, o sistema pergunta se voce quer adiciona-lo mesmo assim (linha em branco)."}
    ];
    var btn = document.getElementById("ucTourBtn"),
        passo = -1, spot = null, bub = null;
    function limpar() {
        if (spot) { spot.remove(); spot = null; }
        if (bub) { bub.remove(); bub = null; }
        passo = -1;
    }
    function mostrar(i) {
        limpar();
        if (!PASSOS[i]) { return; }
        passo = i;
        var p = PASSOS[i], el = null;
        (p.sel.split(",")).forEach(function (s) { if (!el) { el = document.querySelector(s.trim()); } });
        var r = el ? el.getBoundingClientRect() : {left: innerWidth/2-1, top: innerHeight/2-1, width: 2, height: 2, right: innerWidth/2+1, bottom: innerHeight/2+1};
        spot = document.createElement("div");
        spot.className = "atv-tour-spot";
        spot.style.left = (r.left - 6) + "px"; spot.style.top = (r.top - 6) + "px";
        spot.style.width = (r.width + 12) + "px"; spot.style.height = (r.height + 12) + "px";
        document.body.appendChild(spot);
        bub = document.createElement("div");
        bub.className = "atv-tour-bub";
        bub.innerHTML = "<div class=\"t-txt\">" + p.txt + "</div>" +
            "<div class=\"t-nav\"><button type=\"button\" id=\"tAnt\">&larr; Anterior</button>" +
            "<span class=\"t-passos\">" + (i + 1) + "/" + PASSOS.length + "</span>" +
            (i < PASSOS.length - 1
                ? "<button type=\"button\" class=\"t-prox\" id=\"tProx\">Proximo &rarr;</button>"
                : "<button type=\"button\" class=\"t-prox\" id=\"tFim\">Concluir</button>") + "</div>";
        document.body.appendChild(bub);
        var br = bub.getBoundingClientRect(),
            bx = Math.min(Math.max(12, r.left), innerWidth - br.width - 12),
            by = (r.bottom + 16 + br.height < innerHeight) ? r.bottom + 16 : r.top - br.height - 16;
        bub.style.left = bx + "px"; bub.style.top = Math.max(12, by) + "px";
        var ant = bub.querySelector("#tAnt"), prox = bub.querySelector("#tProx"),
            fim = bub.querySelector("#tFim");
        if (i === 0) { ant.disabled = true; ant.style.opacity = ".4"; }
        if (ant) { ant.onclick = function () { if (passo > 0) { mostrar(passo - 1); } }; }
        if (prox) { prox.onclick = function () { mostrar(passo + 1); }; }
        if (fim) { fim.onclick = limpar; }
    }
    btn.addEventListener("click", function () { mostrar(0); });
    document.addEventListener("keydown", function (ev) {
        if (passo < 0) { return; }
        if (ev.key === "Escape") { limpar(); }
        if (ev.key === "ArrowRight" && passo < PASSOS.length - 1) { mostrar(passo + 1); }
        if (ev.key === "ArrowLeft" && passo > 0) { mostrar(passo - 1); }
    });
})();
</script>
