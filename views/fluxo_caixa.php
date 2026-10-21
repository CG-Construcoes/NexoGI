<?php // Acompanhamento Financeiro — grupos com saldo (Financeiro > Fluxo de Caixa) ?>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<form method="post" action="<?= url('fluxo-caixa') ?>">
    <?= csrf_campo() ?>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>1. POSIÇÃO FINANCEIRA</div>
        <div class="fcs-secao-corpo" hidden>
            <?php require ROOT . '/views/fluxo_caixa_posicao.php'; ?>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>2. APORTES A RETORNAR</div>
        <div class="fcs-secao-corpo" hidden>
            <?php require ROOT . '/views/fluxo_caixa_aportes_item.php'; ?>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-secao-tit-flex" data-tog>
            <span>3. SALDO FINANCEIRO POR OBRA/CENTRO</span>
            <?php if ($podeAtualizar): ?>
            <div class="fcs-periodo">
                <span class="fcs-periodo-label">Período</span>
                <div style="display:flex; align-items:center; gap:6px">
                    <label for="fcs-ini" style="font-size:13px; color:#cde">De</label>
                    <input type="date" id="fcs-ini" name="data_ini" value="<?= e($sugIni) ?>">
                    <label for="fcs-fim" style="font-size:13px; color:#cde">a</label>
                    <input type="date" id="fcs-fim" name="data_fim" value="<?= e($sugFim) ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="fcs-secao-corpo">

    <?php if ($grupos): ?>
    <table class="tabela">
        <thead>
            <tr>
                <th style="width:60px" title="Ordem de exibição e impressão"></th>
                <th style="width:36px" title="Selecionar para atualizar">
                    <?php if ($podeAtualizar): ?>
                    <input type="checkbox" id="fcs-todos" title="Selecionar todos para atualizar">
                    <?php endif; ?>
                </th>
                <th style="min-width:36px" title="Selecionar para imprimir">
                    <?php if ($podeAtualizar): ?>
                    <input type="checkbox" id="fcs-imp-todos" title="Selecionar todos para imprimir">
                    <?php endif; ?>
                    Imprimir
                </th>
                <th>Grupo</th>
                <th>Centros</th>
                <th style="width:90px">Avulso</th>
                <th class="col-valor">Saldo do Grupo</th>
                <th>Ultima Atualizacao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $g):
                $gid = (int) $g['FCGrupo_Id'];
                $centros = $centrosPorGrupo[$gid] ?? [];
                $avulsos = $avulsosPorGrupo[$gid] ?? [];
                $avPendente = (bool) array_filter($avulsos,
                    function ($a) { return $a['valor'] === null; }); ?>
            <tr data-gid="<?= $gid ?>"<?= $avulsos ? ' data-av="' . $gid . '"' : ''
                . ($avPendente ? ' class="fcs-pend"' : '') ?>>
                <td class="fcs-ord">
                    <?php if ($podeAtualizar): ?>
                    <span class="fcs-grip" title="Arrastar para reordenar">&#x2807;&#x2807;</span>
                    <?php endif; ?>
                </td>
                <td>
                    <input type="checkbox" name="grupos[]" value="<?= (int) $g['FCGrupo_Id'] ?>"
                           class="fcs-check" data-nome="<?= e($g['FCGrupo_Nome']) ?>"
                           title="Atualizar" <?= $podeAtualizar ? '' : ' disabled' ?>>
                </td>
                <td>
                    <input type="checkbox" name="imprimir[]" value="<?= (int) $g['FCGrupo_Id'] ?>"
                           class="fcs-imp" title="Imprimir relatorio"
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
                <td>
                    <?php if ($avulsos): ?>
                    <button type="button" class="btn btn-sm fcs-av-btn" data-grupo="<?= $gid ?>">
                        Avulso (<?= count($avulsos) ?>)</button>
                    <?php endif; ?>
                </td>
                <td class="col-valor"><?= $g['SaldoGrupo'] !== null
                    ? number_format((float) $g['SaldoGrupo'], 2, ',', '.') : '&mdash;' ?></td>
                <td><?= $g['UltimaAtualizacao']
                    ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '&mdash;' ?></td>
            </tr>
            <?php if ($avulsos): ?>
            <tr class="fcs-av-row" data-grupo="<?= $gid ?>" hidden>
                <td></td><td></td><td></td>
                <td colspan="4">
                    <?php foreach ($avulsos as $a): ?>
                    <label class="fcs-av-lab"><?= e($a['nome']) ?>
                        <input type="text" name="avulso[<?= $gid ?>][<?= (int) $a['id'] ?>]"
                               class="fcs-av" data-grupo="<?= $gid ?>"
                               value="<?= $a['valor'] !== null
                                   ? e(number_format($a['valor'], 2, ',', '.')) : '' ?>"
                               placeholder="0,00" size="12"></label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="vazio">Nenhum grupo cadastrado. Cadastre em
        <a href="<?= url('fluxo-caixa-grupos') ?>">Grupos de Centros</a>.</p>
    <?php endif; ?>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>4. CONTAS A RECEBER</div>
        <div class="fcs-secao-corpo" hidden>
            <?php require ROOT . '/views/fluxo_caixa_receber.php'; ?>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>5. CONTAS A PAGAR</div>
        <div class="fcs-secao-corpo" hidden>
            <?php require ROOT . '/views/fluxo_caixa_pagar.php'; ?>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>6. COMPOSIÇÃO DO RESULTADO POR NÚCLEO</div>
        <div class="fcs-secao-corpo" hidden>
            <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
        </div>
    </div>

    <div class="fcs-secao">
        <div class="fcs-secao-tit fcs-fech" data-tog>7. CONSÓRCIOS</div>
        <div class="fcs-secao-corpo" hidden>
            <div class="fcs-emb">Em construção — os dados deste grupo serão definidos.</div>
        </div>
    </div>

    <div class="fcs-rodape">
        <button type="submit" class="btn" id="fcs-btn">Atualizar</button>
    </div>
</form>

<script>
(function () {
    // Recolhe/expande cada secao numerada; cliques no Periodo nao alternam.
    document.querySelectorAll('.fcs-secao-tit[data-tog]').forEach(function (tit) {
        tit.addEventListener('click', function (ev) {
            if (ev.target.closest('.fcs-periodo')) { return; }
            var corpo = tit.parentElement.querySelector('.fcs-secao-corpo');
            if (!corpo) { return; }
            corpo.hidden = !corpo.hidden;
            tit.classList.toggle('fcs-fech', corpo.hidden);
        });
    });
})();
</script>

<?php if ($podeAtualizar && $grupos): ?>
<div id="fcs-progresso" class="fcs-progresso" hidden>
    <div class="fcs-barra">
        <div id="fcs-fill" class="fcs-fill"></div>
        <span id="fcs-texto" class="fcs-texto"></span>
    </div>
    <div class="fcs-anim"><span class="fcs-spin"></span>
        <span class="fcs-anim-txt">Os cálculos estão sendo processados. Por gentileza não feche esta tela!</span>
        <button type="button" id="fcs-stop" class="btn btn-sm btn-danger fcs-stop">Interromper Cálculo</button>
    </div>
</div>
<script>
(function () {
    var urlImpBase = '<?= e(url('fluxo-caixa/imprimir')) ?>';
    var urlResumoBase = '<?= e(url('fluxo-caixa/resumo')) ?>';
    function resumoUrl(ids) {
        var u = urlResumoBase + '?';
        if (iniEl.value) { u += 'ini=' + iniEl.value + '&'; }
        if (fimEl.value) { u += 'fim=' + fimEl.value + '&'; }
        if (ids && ids.length) { u += 'att=' + ids.join(','); }
        return u;
    }
    // Recalcula o item 5 (contas a pagar) no servidor; fim(erro|null).
    function calcularPagar(fim) {
        var corpo = new URLSearchParams(new FormData(form));
        corpo.set('ajax', '1');
        corpo.set('acao', 'pagar');
        corpo.delete('grupos[]');
        corpo.delete('imprimir[]');
        corpo.forEach(function (v, k) {
            if (k.indexOf('avulso[') === 0) { corpo.delete(k); }
        });
        texto.textContent = 'Calculando posição financeira e contas a pagar...';
        fetch(form.action, { method: 'POST', body: corpo })
            .then(function (r) { return r.json(); })
            .then(function (j) { fim(j.ok ? null : (j.erro || 'erro')); })
            .catch(function () { fim('falha de rede'); });
    }

    // Retorno do reload pos-calculo: imprime os relatorios pendentes.
    var pendente = sessionStorage.getItem('fcs_imprimir');
    if (pendente) {
        sessionStorage.removeItem('fcs_imprimir');
        abrirImpressao(pendente);
    }
    function abrirImpressao(u) {
        var f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
        f.onload = function () {
            f.contentWindow.focus();
            f.contentWindow.print();
        };
        f.src = u;
        document.body.appendChild(f);
    }
    function urlImpressao(ids, ini, fim) {
        var u = urlImpBase + '?grupos=' + ids.join(',')
            + '&data_ini=' + encodeURIComponent(ini)
            + '&data_fim=' + encodeURIComponent(fim);
        var pares = [];
        ids.forEach(function (gid) {
            form.querySelectorAll('.fcs-av[data-grupo="' + gid + '"]').forEach(function (o) {
                if (o.value.trim() !== '') {
                    var m = o.name.match(/\[(\d+)\]$/);
                    if (m) { pares.push(m[1] + ':' + encodeURIComponent(o.value.trim())); }
                }
            });
        });
        if (pares.length) { u += '&av=' + pares.join(','); }
        return u;
    }

    var todos = document.getElementById('fcs-todos');
    var form = todos.closest('form');
    var checks = form.querySelectorAll('.fcs-check');
    var impTodos = document.getElementById('fcs-imp-todos');
    var impChecks = form.querySelectorAll('.fcs-imp');
    var btn = document.getElementById('fcs-btn');
    var iniEl = document.getElementById('fcs-ini');
    var fimEl = document.getElementById('fcs-fim');
    var prog = document.getElementById('fcs-progresso');
    var fill = document.getElementById('fcs-fill');
    var texto = document.getElementById('fcs-texto');
    todos.addEventListener('change', function () {
        checks.forEach(function (cb) { cb.checked = todos.checked; });
    });
    impTodos.addEventListener('change', function () {
        impChecks.forEach(function (cb) { cb.checked = impTodos.checked; });
    });
    function parcial(cbs, mestre) {
        var marcados = 0;
        cbs.forEach(function (cb) { if (cb.checked) { marcados++; } });
        mestre.checked = marcados === cbs.length;
        mestre.indeterminate = marcados > 0 && marcados < cbs.length;
    }
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () { parcial(checks, todos); });
    });
    impChecks.forEach(function (cb) {
        cb.addEventListener('change', function () { parcial(impChecks, impTodos); });
    });
    // Arrastar e soltar: reordena as linhas e grava a ordem completa
    var csrfTok = form.querySelector('input[name="csrf"]').value;
    var tbody = form.querySelector('tbody');
    var dragTr = null;
    function linhaApos(tr) { // depois do par linha + linha de avulsos
        var n = tr.nextElementSibling;
        if (n && n.classList.contains('fcs-av-row')) { n = n.nextElementSibling; }
        return n;
    }
    form.querySelectorAll('tr[data-gid]').forEach(function (tr) {
        var grip = tr.querySelector('.fcs-grip');
        if (grip) {
            grip.addEventListener('mousedown', function () { tr.draggable = true; });
            grip.addEventListener('mouseup', function () { tr.draggable = false; });
        }
        tr.addEventListener('dragstart', function (ev) {
            dragTr = tr;
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', tr.dataset.gid);
            tr.classList.add('fcs-drag');
        });
        tr.addEventListener('dragend', function () {
            tr.draggable = false;
            tr.classList.remove('fcs-drag');
            form.querySelectorAll('.fcs-over').forEach(function (o) {
                o.classList.remove('fcs-over', 'fcs-over-fim');
            });
            dragTr = null;
        });
        tr.addEventListener('dragover', function (ev) {
            if (!dragTr || dragTr === tr) { return; }
            ev.preventDefault();
            ev.dataTransfer.dropEffect = 'move';
            var r = tr.getBoundingClientRect();
            tr.classList.add('fcs-over');
            tr.classList.toggle('fcs-over-fim',
                ev.clientY >= r.top + r.height / 2);
        });
        tr.addEventListener('dragleave', function () {
            tr.classList.remove('fcs-over', 'fcs-over-fim');
        });
        tr.addEventListener('drop', function (ev) {
            ev.preventDefault();
            if (!dragTr || dragTr === tr) { return; }
            var r = tr.getBoundingClientRect();
            var ref = ev.clientY >= r.top + r.height / 2 ? linhaApos(tr) : tr;
            var av = form.querySelector(
                '.fcs-av-row[data-grupo="' + dragTr.dataset.gid + '"]');
            tbody.insertBefore(dragTr, ref);
            if (av) { tbody.insertBefore(av, dragTr.nextElementSibling); }
            var corpo = new URLSearchParams();
            corpo.set('csrf', csrfTok);
            corpo.set('acao', 'reordenar');
            tbody.querySelectorAll('tr[data-gid]').forEach(function (l) {
                corpo.append('ordem[]', l.dataset.gid);
            });
            fetch(form.action, { method: 'POST', body: corpo });
        });
    });
    // Botao "Avulso": abre/fecha a linha de campos do grupo
    form.querySelectorAll('.fcs-av-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            var row = form.querySelector('.fcs-av-row[data-grupo="' + b.dataset.grupo + '"]');
            row.hidden = !row.hidden;
        });
    });
    // A linha so deixa de ficar vermelha quando TODOS os avulsos tem valor
    form.querySelectorAll('.fcs-av').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var gid = inp.dataset.grupo;
            var vazio = false;
            form.querySelectorAll('.fcs-av[data-grupo="' + gid + '"]').forEach(function (o) {
                if (o.value.trim() === '') { vazio = true; }
            });
            var linha = form.querySelector('tr[data-av="' + gid + '"]');
            if (linha) { linha.classList.toggle('fcs-pend', vazio); }
        });
    });
    form.addEventListener('submit', function (ev) {
        var marcados = Array.prototype.slice.call(
            form.querySelectorAll('.fcs-check:checked'));
        var impMarcados = Array.prototype.slice.call(
            form.querySelectorAll('.fcs-imp:checked'));
        if (!marcados.length && !impMarcados.length) {
            // nada marcado: so recalcula o item 5 e abre o resumo
            ev.preventDefault();
            btn.disabled = true;
            prog.hidden = false;
            calcularPagar(function (erro) {
                if (erro) { alert('Contas a pagar: ' + erro); }
                location.href = resumoUrl();
            });
            return;
        }
        if (!iniEl.value || !fimEl.value || iniEl.value > fimEl.value) {
            return; // deixa o POST normal validar no servidor
        }
        // Grupo marcado para atualizar precisa de todos os valores avulsos
        var avPendente = null;
        marcados.forEach(function (cb) {
            var vazio = false;
            form.querySelectorAll('.fcs-av[data-grupo="' + cb.value + '"]').forEach(function (o) {
                if (o.value.trim() === '') { vazio = true; }
            });
            if (vazio) { avPendente = cb.dataset.nome; }
        });
        if (avPendente) {
            ev.preventDefault();
            alert('Preencha o(s) valor(es) avulso(s) do grupo "' + avPendente + '".');
            return;
        }
        var dias = (Date.parse(fimEl.value) - Date.parse(iniEl.value)) / 86400000;
        if (dias > 30 && !confirm('O Período selecionado é muito grande, o relatório irá demorar alguns instantes')) {
            ev.preventDefault();
            return;
        }
        ev.preventDefault();
        var urlImp = impMarcados.length
            ? urlImpressao(impMarcados.map(function (c) { return c.value; }),
                           iniEl.value, fimEl.value)
            : null;
        if (!marcados.length) {
            // so impressao: recalcula o item 5; o resumo abre o iframe
            sessionStorage.setItem('fcs_imprimir', urlImp);
            btn.disabled = true;
            prog.hidden = false;
            calcularPagar(function (erro) {
                if (erro) { alert('Contas a pagar: ' + erro); }
                location.href = resumoUrl();
            });
            return;
        }
        alert('Os cálculos irão iniciar.\nNão feche esta tela.');
        btn.disabled = true;
        prog.hidden = false;
        var t0 = null;
        var falhas = [];
        var concluidos = [];
        var concluidosIds = [];
        var cancelar = false;
        var btnStop = document.getElementById('fcs-stop');
        btnStop.addEventListener('click', function () {
            if (cancelar) { return; }
            cancelar = true;
            btnStop.disabled = true;
            texto.textContent = 'Cancelando — aguarde a conclusão do grupo atual...';
            alert('O cálculo será cancelado.\nAguarde a conclusão do grupo atual.');
        });
        var i = 0;
        function tempoDecorrido() {
            var seg = Math.round((performance.now() - t0) / 1000);
            return seg >= 60
                ? Math.floor(seg / 60) + ' min ' + (seg % 60) + ' s'
                : seg + ' s';
        }
        function proximo() {
            if (cancelar) {
                prog.hidden = true;
                var msgStop = 'Os cálculos foram interrompidos.'
                    + '\n\nGrupos atualizados:\n - '
                    + (concluidos.length ? concluidos.join('\n - ') : 'nenhum')
                    + '\n\nTempo: ' + tempoDecorrido();
                if (falhas.length) {
                    msgStop += '\n\nFalharam:\n - ' + falhas.join('\n - ');
                }
                alert(msgStop);
                location.href = resumoUrl(concluidosIds);
                return;
            }
            if (i >= marcados.length) {
                // ultimo passo: recalcula e grava o item 5 (contas a pagar)
                calcularPagar(function (erro) {
                    prog.hidden = true;
                    if (erro) { falhas.push('Contas a pagar: ' + erro); }
                    var msg = 'Cálculo concluído.\n\nGrupos atualizados:\n - '
                        + (concluidos.length ? concluidos.join('\n - ') : 'nenhum')
                        + '\n\nTempo total: ' + tempoDecorrido();
                    if (falhas.length) {
                        msg += '\n\nFalharam:\n - ' + falhas.join('\n - ');
                    }
                    if (urlImp) {
                        sessionStorage.setItem('fcs_imprimir', urlImp);
                    }
                    alert(msg);
                    location.href = resumoUrl(concluidosIds);
                });
                return;
            }
            var cb = marcados[i];
            var nome = cb.dataset.nome;
            texto.textContent = 'Calculando: ' + nome
                + ' (' + (i + 1) + ' de ' + marcados.length + ')';
            var corpo = new URLSearchParams(new FormData(form));
            corpo.set('ajax', '1');
            corpo.delete('grupos[]');
            corpo.delete('imprimir[]');
            corpo.set('grupo', cb.value);
            // envia apenas os valores avulsos do grupo em calculo
            var avKeys = [];
            corpo.forEach(function (v, k) {
                if (k.indexOf('avulso[') === 0) { avKeys.push(k); }
            });
            avKeys.forEach(function (k) {
                if (k.indexOf('avulso[' + cb.value + ']') !== 0) { corpo.delete(k); }
            });
            fetch(form.action, { method: 'POST', body: corpo })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.ok) { concluidos.push(nome); concluidosIds.push(cb.value); }
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
        executar();
    });
})();
</script>
<?php endif; ?>
