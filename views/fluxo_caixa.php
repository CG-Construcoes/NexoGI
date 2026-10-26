<?php // Acompanhamento Financeiro — grupos com saldo (Financeiro > Diretoria) ?>
<?php require ROOT . '/views/fluxo_caixa_marca.php'; ?>
<div id="fcs-aviso-relatorio" style="position:fixed; inset:0; z-index:1000;
     background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center">
    <div style="background:#fff; border-radius:10px; padding:32px 40px; max-width:560px;
                text-align:center; box-shadow:0 8px 30px rgba(0,0,0,.3)">
        <div style="font-size:52px; line-height:1; color:#e0a800">&#9888;</div>
        <p style="margin:16px 0 18px; font-size:16px; color:#333">
            Atenção - Este Relatório está em período de avaliação!</p>
        <div style="text-align:left; font-size:13.5px; color:#444; line-height:1.55;
                    background:#f7f8fa; border:1px solid #e3e6ea; border-radius:8px;
                    padding:14px 18px; margin-bottom:22px">
            <strong>Uso do Relatório:</strong>
            <p style="margin:8px 0">O relatório de Acompanhamento Financeiro terá a
                opção de realizar os cálculos usando os centros da Base DBMGA e/ou DBCONV.</p>
            <p style="margin:8px 0 4px">Para o bom funcionamento do relatório, deverão
                ser preenchidas as informações das telas:</p>
            <ul style="margin:0; padding-left:22px">
                <li style="margin-bottom:6px">“Financeiro\Fluxo de Caixa\Aportes a Retornar”
                    – Informações referente a aportes;</li>
                <li style="margin-bottom:6px">“Financeiro\Diretoria\Acompanhamento Financeiro”
                    – Item 3. Saldos Financeiros por Obra/Centro, Centros Avulsos
                    (Valores Complementares);</li>
                <li>“Engenharia\Previsões\Medições”
                    – Para preenchimento dos valores referentes a contas a Receber.</li>
            </ul>
        </div>
        <button type="button" class="btn"
                onclick="document.getElementById('fcs-aviso-relatorio').remove()">Continuar</button>
    </div>
</div>
<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:18px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<form method="post" action="<?= url('fluxo-caixa') ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="bases_enviadas" value="1">

    <div style="margin:0 0 14px; display:flex; align-items:center; gap:14px">
        <strong>Bases:</strong>
        <label title="Informações da base dbMGA">
            <input type="checkbox" id="fcs-base-mga" name="base_mga" value="1"
                   <?= in_array('MGA', $basesSel, true) ? 'checked' : '' ?>> DBMGA</label>
        <label title="Informações da base dbConv">
            <input type="checkbox" id="fcs-base-conv" name="base_conv" value="1"
                   <?= in_array('CONV', $basesSel, true) ? 'checked' : '' ?>> DBCONV</label>
    </div>

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
                <th style="width:52px" title="Selecionar para atualizar">
                    <span style="display:block; font-size:9px; font-weight:400;
                          letter-spacing:.3px">Calcular</span>
                    <?php if ($podeAtualizar): ?>
                    <input type="checkbox" id="fcs-todos" title="Selecionar todos para atualizar">
                    <?php endif; ?>
                </th>
                <th style="width:44px" title="Exibir o grupo nos itens do relatório">
                    <span style="display:block; font-size:9px; font-weight:400;
                          letter-spacing:.3px">Exibir</span>
                    <?php if ($podeAtualizar): ?>
                    <input type="checkbox" id="fcs-exibir-todos" title="Exibir todos os grupos"
                        <?= empty($temOcultos) ? 'checked' : '' ?>>
                    <?php endif; ?>
                </th>
                <th>Grupo</th>
                <th>Centros</th>
                <th style="width:90px">Avulso</th>
                <th class="col-valor" title="Saldo do grupo calculado pelo Relatório Financeiro (Receitas + Despesas) no período informado">Saldo Atual</th>
                <?php $mesesFc = fcs_pagar_meses();
                foreach ($mesesFc as $mi => $ymFc):
                    $mmFc = fcs_mes_rotulo($ymFc);
                    $fimFc = data_br(date('Y-m-d', strtotime("$ymFc-01 +1 month -1 day")));
                    $antFc = $mi === 0 ? 'Saldo Atual'
                        : data_br(date('Y-m-d', strtotime($mesesFc[$mi - 1] . '-01 +1 month -1 day'))); ?>
                <th class="col-valor" title="<?= e("$antFc − Contas a Pagar ($mmFc) + Contas a Receber ($mmFc)") ?>"><?= e($fimFc) ?></th>
                <?php endforeach; ?>
                <th>Ultima Atualizacao</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $g):
                $gid = (int) $g['FCGrupo_Id'];
                $centros = $centrosPorGrupo[$gid] ?? [];
                $avulsos = $avulsosPorGrupo[$gid] ?? [];
                $avPendente = (bool) array_filter($avulsos,
                    function ($a) { return $a['valor'] === null; });
                $clsTr = trim((($g['Exibir'] ?? 'Sim') === 'Não' ? 'fcs-oculto ' : '')
                    . ($avPendente ? 'fcs-pend' : '')); ?>
            <tr data-gid="<?= $gid ?>"<?= ($avulsos ? ' data-av="' . $gid . '"' : '')
                . ($clsTr !== '' ? ' class="' . $clsTr . '"' : '') ?>>
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
                    <input type="checkbox" class="fcs-exibir" data-gid="<?= $gid ?>"
                           title="Exibir o grupo nos itens"
                           <?= ($g['Exibir'] ?? 'Sim') !== 'Não' ? 'checked' : '' ?>
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
                <?php foreach (['SaldoMes1', 'SaldoMes2', 'SaldoMes3'] as $colMes): ?>
                <td class="col-valor"><?= $g[$colMes] !== null
                    ? number_format((float) $g[$colMes], 2, ',', '.') : '&mdash;' ?></td>
                <?php endforeach; ?>
                <td><?= $g['UltimaAtualizacao']
                    ? e(data_br((string) $g['UltimaAtualizacao'], true)) : '&mdash;' ?></td>
            </tr>
            <?php if ($avulsos): ?>
            <tr class="fcs-av-row" data-grupo="<?= $gid ?>" hidden>
                <td></td><td></td><td></td>
                <td colspan="8">
                    <?php foreach ($avulsos as $a): ?>
                    <label class="fcs-av-lab"><?= e($a['nome']) ?>
                        <input type="text" name="avulso[<?= $gid ?>][<?= (int) $a['id'] ?>]"
                               class="fcs-av" data-grupo="<?= $gid ?>"
                               value="<?= $a['valor'] !== null
                                   ? e(number_format($a['valor'], 2, ',', '.')) : '' ?>"
                               placeholder="0,00" size="12">
                        <?php if ($podeAtualizar): ?>
                        <button type="button" class="btn btn-sm fcs-av-save" disabled>Salvar</button>
                        <?php endif; ?></label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <?php $totCentros = 0;
        $totFc = ['SaldoGrupo' => null, 'SaldoMes1' => null, 'SaldoMes2' => null, 'SaldoMes3' => null];
        foreach ($grupos as $g) {
            if (($g['Exibir'] ?? 'Sim') === 'Não') {
                continue; // grupo oculto nao soma no total do relatorio
            }
            $totCentros += (int) $g['QtdeCentros'];
            foreach (array_keys($totFc) as $cTot) {
                if ($g[$cTot] !== null) {
                    $totFc[$cTot] = ($totFc[$cTot] ?? 0) + (float) $g[$cTot];
                }
            }
        } ?>
        <tfoot>
            <tr style="font-weight:700; background:#d7e6f5; border-top:2px solid #1974b8">
                <td colspan="4">TOTAL GERAL</td>
                <td><?= $totCentros ?></td>
                <td></td>
                <?php foreach ($totFc as $vTot): ?>
                <td class="col-valor"><?= $vTot !== null
                    ? number_format($vTot, 2, ',', '.') : '&mdash;' ?></td>
                <?php endforeach; ?>
                <td></td>
            </tr>
        </tfoot>
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
<div id="fcs-progresso" class="fcs-overlay" hidden>
    <div style="background:#fff; border-radius:10px; padding:28px 34px; width:540px;
                max-width:92vw; text-align:center; box-shadow:0 8px 30px rgba(0,0,0,.3)">
        <div style="display:flex; align-items:center; justify-content:center; gap:10px;
                    font-size:16px; font-weight:700; color:#1e3a5f">
            <span class="fcs-spin"></span> Cálculo em andamento
        </div>
        <p style="margin:10px 0 16px; font-size:13px; color:#4a6a8a">
            Os cálculos estão sendo processados. Por gentileza não feche esta tela!</p>
        <div class="fcs-barra">
            <div id="fcs-fill" class="fcs-fill"></div>
            <span id="fcs-texto" class="fcs-texto"></span>
        </div>
        <div id="fcs-dica-box" style="margin-top:16px; text-align:left; font-size:13.5px;
                color:#444; line-height:1.5; background:#f7f8fa; border:1px solid #e3e6ea;
                border-radius:8px; padding:12px 16px" hidden>
            💡 <strong>Dica:</strong> <span id="fcs-dica"></span>
        </div>
        <button type="button" id="fcs-stop" class="btn btn-sm btn-danger"
                style="margin-top:16px">Interromper Cálculo</button>
    </div>
</div>
<script>
(function () {
    var urlResumoBase = '<?= e(url('fluxo-caixa/resumo')) ?>';
    function resumoUrl(ids) {
        var u = urlResumoBase + '?';
        if (iniEl.value) { u += 'ini=' + iniEl.value + '&'; }
        if (fimEl.value) { u += 'fim=' + fimEl.value + '&'; }
        if (ids && ids.length) { u += 'att=' + ids.join(','); }
        if (t0) {
            u += '&tempo=' + Math.max(0, Math.round((performance.now() - t0) / 1000));
        }
        return u;
    }
    // Recalcula o item 5 (contas a pagar) no servidor; fim(erro|null).
    // ids = grupos recalculados neste Atualizar (projecao do item 3 so deles).
    // Falha de transporte (rede/HTTP) tenta mais uma vez antes de desistir.
    function calcularPagar(fim, ids) {
        var corpo = new URLSearchParams(new FormData(form));
        corpo.set('ajax', '1');
        corpo.set('acao', 'pagar');
        corpo.delete('grupos[]');
        (ids || []).forEach(function (id) { corpo.append('grupos[]', id); });
        corpo.forEach(function (v, k) {
            if (k.indexOf('avulso[') === 0) { corpo.delete(k); }
        });
        texto.textContent = 'Calculando posição financeira e contas a pagar...';
        var tentativa = 0;
        function enviar() {
            fetch(form.action, { method: 'POST', body: corpo })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (j) { fim(j.ok ? null : (j.erro || 'erro')); })
                .catch(function (e) {
                    if (++tentativa < 2) { enviar(); return; }
                    fim('falha de rede' + (e && e.message ? ' (' + e.message + ')' : ''));
                });
        }
        enviar();
    }

    var todos = document.getElementById('fcs-todos');
    var form = todos.closest('form');
    var checks = form.querySelectorAll('.fcs-check');
    var t0 = null; // inicio do calculo — resumoUrl usa p/ mandar &tempo=
    var btn = document.getElementById('fcs-btn');
    var iniEl = document.getElementById('fcs-ini');
    var fimEl = document.getElementById('fcs-fim');
    var prog = document.getElementById('fcs-progresso');
    var fill = document.getElementById('fcs-fill');
    var texto = document.getElementById('fcs-texto');
    // Dicas do modal de progresso (msoft.Dica, area Financeiro)
    var dicaBox = document.getElementById('fcs-dica-box');
    var dicaEl = document.getElementById('fcs-dica');
    var dicaTimer = null;
    var urlDica = '<?= e(url('dica?area=financeiro')) ?>';
    function buscarDica() {
        fetch(urlDica)
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.dica) { dicaEl.textContent = j.dica; dicaBox.hidden = false; }
                else { dicaBox.hidden = true; }
            })
            .catch(function () {});
    }
    function mostrarProg() {
        prog.hidden = false;
        if (!dicaTimer) {
            buscarDica();
            dicaTimer = setInterval(buscarDica, 10000);
        }
    }
    function esconderProg() {
        prog.hidden = true;
        clearInterval(dicaTimer);
        dicaTimer = null;
    }
    todos.addEventListener('change', function () {
        checks.forEach(function (cb) { cb.checked = todos.checked; });
    });
    // Checkbox Exibir: grava a visibilidade do grupo (vale p/ todos os
    // itens — tela, totais e recalculo) e recarrega a tela ja filtrada.
    function postExibir(acao, v, gid) {
        var corpo = new URLSearchParams();
        corpo.set('csrf', csrfTok);
        corpo.set('acao', acao);
        corpo.set('v', v ? '1' : '');
        if (gid !== undefined) { corpo.set('id', gid); }
        fetch(form.action, { method: 'POST', body: corpo })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                // keep=1: preserva o Exibir neste reload; entrada limpa reseta
                if (j.ok) { location.href = location.pathname + '?keep=1'; }
            });
    }
    form.querySelectorAll('.fcs-exibir').forEach(function (cb) {
        cb.addEventListener('change', function () {
            postExibir('exibir', cb.checked, cb.dataset.gid);
        });
    });
    var exibirTodos = document.getElementById('fcs-exibir-todos');
    if (exibirTodos) {
        exibirTodos.addEventListener('change', function () {
            postExibir('exibir_todos', exibirTodos.checked);
        });
    }
    function parcial(cbs, mestre) {
        var marcados = 0;
        cbs.forEach(function (cb) { if (cb.checked) { marcados++; } });
        mestre.checked = marcados === cbs.length;
        mestre.indeterminate = marcados > 0 && marcados < cbs.length;
    }
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () { parcial(checks, todos); });
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
            // alterou o valor: habilita o Salvar ao lado do campo
            var sv = inp.parentElement.querySelector('.fcs-av-save');
            if (sv) { sv.disabled = inp.value.trim() === ''; sv.textContent = 'Salvar'; }
        });
    });
    // Botao "Salvar" do avulso: grava so aquele item e desabilita de novo
    form.querySelectorAll('.fcs-av-save').forEach(function (sv) {
        sv.addEventListener('click', function () {
            var inp = sv.parentElement.querySelector('.fcs-av');
            var m = inp.name.match(/\[(\d+)\]$/);
            if (!m) { return; }
            sv.disabled = true;
            var corpo = new URLSearchParams();
            corpo.set('csrf', csrfTok);
            corpo.set('ajax', '1');
            corpo.set('acao', 'salvar_avulso');
            corpo.set('avulso_id', m[1]);
            corpo.set('valor', inp.value);
            fetch(form.action, { method: 'POST', body: corpo })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (j) {
                    if (j.ok) {
                        sv.textContent = 'Salvo'; // fica desabilitado ate nova digitacao
                    } else {
                        sv.disabled = false;
                        alert('Valor avulso: ' + (j.erro || 'erro'));
                    }
                })
                .catch(function (e) {
                    sv.disabled = false;
                    alert('Valor avulso: falha de rede'
                        + (e && e.message ? ' (' + e.message + ')' : ''));
                });
        });
    });
    form.addEventListener('submit', function (ev) {
        var marcados = Array.prototype.slice.call(
            form.querySelectorAll('.fcs-check:checked'));
        if (!marcados.length) {
            // nada marcado: so recalcula o item 5 e abre o resumo
            ev.preventDefault();
            btn.disabled = true;
            mostrarProg();
            t0 = performance.now();
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
        alert('Os cálculos irão iniciar.\nNão feche esta tela.');
        btn.disabled = true;
        mostrarProg();
        t0 = null;
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
                esconderProg();
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
                // e a projecao do item 3 dos grupos que concluiram
                calcularPagar(function (erro) {
                    esconderProg();
                    if (erro) { falhas.push('Contas a pagar: ' + erro); }
                    var msg = 'Cálculo concluído.\n\nGrupos atualizados:\n - '
                        + (concluidos.length ? concluidos.join('\n - ') : 'nenhum')
                        + '\n\nTempo total: ' + tempoDecorrido();
                    if (falhas.length) {
                        msg += '\n\nFalharam:\n - ' + falhas.join('\n - ');
                    }
                    alert(msg);
                    location.href = resumoUrl(concluidosIds);
                }, concluidosIds);
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
            // envia apenas os valores avulsos do grupo em calculo
            var avKeys = [];
            corpo.forEach(function (v, k) {
                if (k.indexOf('avulso[') === 0) { avKeys.push(k); }
            });
            avKeys.forEach(function (k) {
                if (k.indexOf('avulso[' + cb.value + ']') !== 0) { corpo.delete(k); }
            });
            fetch(form.action, { method: 'POST', body: corpo })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (j) {
                    if (j.ok) { concluidos.push(nome); concluidosIds.push(cb.value); }
                    else { falhas.push(nome + ': ' + (j.erro || 'erro')); }
                })
                .catch(function (e) {
                    falhas.push(nome + ': falha de rede'
                        + (e && e.message ? ' (' + e.message + ')' : ''));
                })
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
