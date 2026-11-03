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

<!-- ===================== ABAS: PADRAO / KANBAN ===================== -->
<?php if ($linhas): ?>
<div class="atv-abas">
    <button type="button" class="atv-aba ativo" data-v="padrao">Padrão</button>
    <button type="button" class="atv-aba" data-v="kanban">Kanban</button>
    <span class="atv-aba-n" style="color:#555;font-size:.85rem;margin-left:auto"><?= count($linhas) ?> atividade(s)</span>
</div>
<?php endif; ?>

<!-- ===================== GRID ===================== -->
<?php if (!$linhas): ?>
    <p style="color:#666">Nenhuma atividade encontrada nos filtros/escopo informados.</p>
<?php else: ?>
<div id="atv-padrao">
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
</div>

<!-- ===================== KANBAN ===================== -->
<div id="atv-kanban" hidden>
<div class="atv-kb">
<?php
$porStatus = array_fill_keys(ATV_STATUS, []);
foreach ($linhas as $l) {
    $porStatus[(string) $l['Status']][] = $l;
}
foreach (ATV_STATUS as $st):
    $cards = $porStatus[$st]; ?>
    <div class="atv-kb-col" data-status="<?= e($st) ?>">
        <div class="atv-kb-head <?= atv_status_css($st) ?>">
            <span><?= e($st) ?></span>
            <span class="atv-kb-n"><?= count($cards) ?></span>
        </div>
        <div class="atv-kb-body">
        <?php foreach ($cards as $l):
            $urg = atv_urgente($l);
            $venc = atv_vencida($l); ?>
            <div class="atv-kb-card <?= $venc ? 'atv-venc' : '' ?>"
                 draggable="true" data-id="<?= (int) $l['Atividade_Id'] ?>"
                 <?= ($c = trim((string) ($l['Cor'] ?? ''))) !== '' ? 'style="background:' . e($c) . '"' : '' ?>>
                <div class="atv-kb-tit">
                    <?php if ($urg): ?><span class="atv-urg" title="Urgente">!</span><?php endif; ?>
                    <strong><?= e($l['Titulo']) ?></strong>
                </div>
                <?php if (trim((string) ($l['Referencia'] ?? '')) !== ''): ?>
                    <div class="atv-ref"><?= e($l['Referencia']) ?></div>
                <?php endif; ?>
                <div class="atv-kb-meta">
                    <span><?= e($l['ResponsavelNome'] ?? '—') ?></span>
                    <?php if ($l['Prazo']): ?>
                        <span class="<?= $venc ? 'atv-venc-txt' : '' ?>">
                            <?= e(data_br(substr((string) $l['Prazo'], 0, 16), true)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="atv-kb-meta atv-kb-rod">
                    <span class="atv-kb-vis"><?= e($l['Visibilidade']) ?></span>
                    <span class="col-valor"><?= e(atv_fmt_min((float) $l['TrabMin'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
</div>

<script>
(function () {
    var detalhe = '<?= e(url('atividade')) ?>?id=';
    document.querySelectorAll('.atv-row').forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
            location.href = detalhe + tr.dataset.id;
        });
    });

    // Abas Padrao/Kanban (preferencia em localStorage)
    var abas = document.querySelectorAll('.atv-aba'),
        padrao = document.getElementById('atv-padrao'),
        kanban = document.getElementById('atv-kanban');
    function mostrar(v) {
        padrao.hidden = v !== 'padrao';
        kanban.hidden = v !== 'kanban';
        abas.forEach(function (a) { a.classList.toggle('ativo', a.dataset.v === v); });
        try { localStorage.setItem('atv_view', v); } catch (e) {}
    }
    abas.forEach(function (a) {
        a.addEventListener('click', function () { mostrar(a.dataset.v); });
    });
    try { mostrar(localStorage.getItem('atv_view') || 'padrao'); } catch (e) { mostrar('padrao'); }

    // Modal: descricao da linha do tempo ao mudar status pelo kanban
    function pedirComentario(novoStatus, cb) {
        var ov = document.createElement('div');
        ov.style.cssText = 'position:fixed;inset:0;z-index:3500;background:rgba(15,23,42,.45);' +
            'display:flex;align-items:center;justify-content:center';
        ov.innerHTML =
            '<div style="background:var(--bg-surface);border:1px solid var(--border-color);' +
            'border-radius:12px;padding:20px;width:420px;max-width:92vw;box-shadow:var(--shadow-lg)">' +
            '<div style="font-weight:700;margin-bottom:10px;color:var(--text-main)">' +
            'Mover para "' + novoStatus + '"</div>' +
            '<textarea id="kbComTxt" rows="3" placeholder="Descricao da linha do tempo (opcional)"' +
            ' style="width:100%;padding:8px;border:1px solid var(--border-strong);' +
            'border-radius:8px;font-family:inherit;font-size:.9rem"></textarea>' +
            '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">' +
            '<button type="button" id="kbComN" class="btn btn-secundario">Cancelar</button>' +
            '<button type="button" id="kbComOk" class="btn">Confirmar</button></div></div>';
        document.body.appendChild(ov);
        ov.querySelector('#kbComTxt').focus();
        ov.querySelector('#kbComN').onclick = function () { ov.remove(); };
        ov.querySelector('#kbComOk').onclick = function () {
            var t = ov.querySelector('#kbComTxt').value.trim();
            ov.remove(); cb(t);
        };
    }

    // Drag-and-drop: arrastar card muda o status via endpoint
    var arrastado = null,
        csrf = '<?= e(csrf_token()) ?>';
    // Botao direito no card: paleta de cor (grava via endpoint)
    var CORES = [['', 'Padrão'], ['#fef9c3', 'Amarelo'], ['#dcfce7', 'Verde'],
                 ['#dbeafe', 'Azul'], ['#fee2e2', 'Vermelho'], ['#ede9fe', 'Roxo'],
                 ['#ffedd5', 'Laranja']],
        menuCor = null;
    function fecharMenu() { if (menuCor) { menuCor.remove(); menuCor = null; } }
    document.addEventListener('click', fecharMenu);
    function paletaCard(card, x, y) {
        fecharMenu();
        menuCor = document.createElement('div');
        menuCor.style.cssText = 'position:fixed;z-index:3600;background:var(--bg-surface);' +
            'border:1px solid var(--border-strong);border-radius:10px;padding:8px;' +
            'box-shadow:var(--shadow-lg);display:flex;flex-direction:column;gap:4px;left:' +
            Math.min(x, innerWidth - 170) + 'px;top:' + Math.min(y, innerHeight - 230) + 'px';
        CORES.forEach(function (c) {
            var b = document.createElement('button');
            b.type = 'button';
            b.style.cssText = 'display:flex;align-items:center;gap:8px;padding:5px 8px;' +
                'border:0;background:transparent;cursor:pointer;font-size:.82rem;color:var(--text-main);border-radius:6px';
            b.innerHTML = '<span style="width:16px;height:16px;border-radius:4px;display:inline-block;' +
                'border:1px solid var(--border-strong);background:' + (c[0] || 'var(--bg-surface)') + '"></span>' + c[1];
            b.onclick = function () {
                fecharMenu();
                fetch('<?= e(url('atividade/cor')) ?>', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: card.dataset.id, cor: c[0], csrf: csrf})
                }).then(function (r) { return r.json(); }).then(function (resp) {
                    if (resp && resp.ok) { card.style.background = c[0]; }
                    else { alert(resp && resp.erro ? resp.erro : 'Falha ao gravar a cor.'); }
                });
            };
            menuCor.appendChild(b);
        });
        document.body.appendChild(menuCor);
    }

    document.querySelectorAll('.atv-kb-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            arrastado = card;
            card.classList.add('atv-kb-drag');
        });
        card.addEventListener('dragend', function () { card.classList.remove('atv-kb-drag'); });
        card.addEventListener('dblclick', function () {
            location.href = detalhe + card.dataset.id;
        });
        card.addEventListener('contextmenu', function (ev) {
            ev.preventDefault();
            paletaCard(card, ev.clientX, ev.clientY);
        });
    });
    document.querySelectorAll('.atv-kb-col').forEach(function (col) {
        var body = col.querySelector('.atv-kb-body');
        col.addEventListener('dragover', function (ev) {
            ev.preventDefault();
            col.classList.add('atv-kb-sobre');
        });
        col.addEventListener('dragleave', function () { col.classList.remove('atv-kb-sobre'); });
        col.addEventListener('drop', function (ev) {
            ev.preventDefault();
            col.classList.remove('atv-kb-sobre');
            if (!arrastado) { return; }
            var card = arrastado;
            arrastado = null;
            if (col.dataset.status === card.closest('.atv-kb-col').dataset.status) { return; }
            pedirComentario(col.dataset.status, function (comentario) {
            fetch('<?= e(url('atividade/status')) ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: card.dataset.id, status: col.dataset.status,
                                      comentario: comentario, csrf: csrf})
            }).then(function (r) { return r.json(); }).then(function (resp) {
                if (resp && resp.ok) {
                    var oCol = card.closest('.atv-kb-col'),
                        oN = oCol.querySelector('.atv-kb-n'),
                        nN = col.querySelector('.atv-kb-n');
                    body.appendChild(card);
                    oN.textContent = parseInt(oN.textContent, 10) - 1;
                    nN.textContent = parseInt(nN.textContent, 10) + 1;
                } else {
                    alert(resp && resp.erro ? resp.erro : 'Nao foi possivel mudar o status.');
                }
            }).catch(function () { alert('Sem resposta do servidor.'); });
            });
        });
    });
})();
</script>
<?php endif; ?>
</div>

<?php if (empty($nova)): ?>
<!-- ===================== TOUR GUIADO (?) ===================== -->
<button type="button" class="atv-tour-btn" id="atvTourBtn" title="Como usar esta tela">?</button>
<script>
(function () {
    var PASSOS = [
        {sel: ".atv-fescopo", txt: "<b>Escopo</b>: <i>Minhas</i> = atividades onde voce participa; <i>Todas que vejo</i> = tudo que sua visibilidade alcanca (padrao); <i>Criadas por mim</i> = as que voce criou."},
        {sel: "form .filtros", txt: "<b>Filtros</b>: restrinja por participante, visibilidade, setor e periodo de abertura. Combine com a busca de texto e os status abaixo."},
        {sel: "input[name=texto]", txt: "<b>Busca</b>: digite parte do titulo, orientacoes ou referencia da atividade."},
        {sel: ".atv-fstatus", txt: "<b>Status</b>: marque quais situacoes exibir. Tambem ha atalhos para so urgentes e so vencidas."},
        {sel: "button[name=gerar]", txt: "Clique <b>Executar</b> para aplicar os filtros na lista."},
        {sel: "a[href*=\"nova=1\"]", txt: "<b>Nova atividade</b>: gestores e coordenadores criam e atribuem — com vinculos ao ERP, responsavel, prazo e visibilidade."},
        {sel: ".atv-abas", txt: "<b>Visoes</b>: <i>Padrao</i> mostra a tabela; <i>Kanban</i> mostra o quadro por status. Sua preferencia fica salva."},
        {sel: ".atv-kb, .tabela-responsiva", txt: "<b>Itens</b>: na tabela, clique para abrir o detalhe. No Kanban: <b>arraste</b> para mudar o status (pede descricao), <b>botao direito</b> troca a cor, <b>duplo clique</b> abre a atividade."}
    ];
    var btn = document.getElementById("atvTourBtn"),
        passo = -1, spot = null, bub = null;
    function limpar() {
        if (spot) { spot.remove(); spot = null; }
        if (bub) { bub.remove(); bub = null; }
        passo = -1;
    }
    function achar(sel) {
        return document.querySelector(sel.split(",").map(function (s) { return s.trim(); })[0])
            || document.querySelector(sel.split(",")[1] && sel.split(",")[1].trim());
    }
    function mostrar(i) {
        limpar();
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
<?php endif; ?>
