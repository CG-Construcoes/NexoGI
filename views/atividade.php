<?php
/*
 * Atividade — detalhe (dados, equipe, tempos, acoes, linha do tempo)
 * ou formulario de criacao (?nova=1, somente gestor/coordenador).
 */
$prazoVal = static function (?string $d): string {
    return $d ? str_replace(' ', 'T', substr((string) $d, 0, 16)) : '';
};
$renderForm = static function (string $acao, ?array $a, array $usuarios, array $setores, array $parts = []) use ($prazoVal): void {
    $pIds = array_map(static fn($p) => (int) $p['Usuario_Id'],
        array_filter($parts, static fn($p) => (int) $p['Ativo'] === 1 && $p['Papel'] === 'participante'));
    $respAtual = null;
    foreach ($parts as $p) {
        if ((int) $p['Ativo'] === 1 && $p['Papel'] === 'responsavel') {
            $respAtual = (int) $p['Usuario_Id'];
        }
    }
    ?>
    <?php $setorEu = trim((string) ((atv_eu() ?? [])['Setor'] ?? '')); ?>
    <div class="atv-form-grid">
        <div class="campo grow" style="grid-column:1/-1">
            <label>Titulo *</label>
            <input type="text" name="titulo" required maxlength="200"
                   value="<?= e($a['Titulo'] ?? '') ?>" placeholder="Resumo do que deve ser feito">
        </div>
        <div class="campo" style="grid-column:1/-1">
            <label>Orientacoes</label>
            <textarea name="orientacoes" rows="4" placeholder="Detalhes, links, instrucoes de execucao"><?= e($a['Orientacoes'] ?? '') ?></textarea>
        </div>
        <?php if ($acao === 'criar'): ?>
        <div class="campo" style="grid-column:1/-1">
            <label>Vinculo Sistema <small style="color:#94a3b8">(busque e selecione; pode vincular varios)</small></label>
            <div class="atv-vinc" data-novo="1">
                <div class="atv-vinc-busca">
                    <select class="atv-vin-tipo"><?php foreach (ATV_VINC_TIPOS as $k => $rot): ?><option value="<?= $k ?>"><?= e($rot) ?></option><?php endforeach; ?></select>
                    <input class="atv-vin-q" type="text" placeholder="Numero + Enter (ex.: 2387)" autocomplete="off">
                </div>
                <div class="atv-vin-res"></div>
                <div class="atv-vin-chips"></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="campo">
            <label>Setor do responsavel</label>
            <select id="atvFiltroSetor">
                <option value="">Todos os setores</option>
                <?php foreach ($setores as $s): ?><option value="<?= e($s) ?>"
                    <?= $acao === 'criar' && $s === $setorEu ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Responsavel *</label>
            <select name="responsavel" id="atvRespSel" required <?= $acao === 'editar' ? 'disabled' : '' ?>>
                <option value="">— escolher —</option>
                <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u['Id'] ?>" data-setor="<?= e($u['Setor'] ?? '') ?>"
                    <?= $respAtual === (int) $u['Id'] ? 'selected' : '' ?>><?= e($u['Nome']) ?><?= $u['Setor'] ? ' · ' . e($u['Setor']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label>Prazo de Conclusao</label>
            <input type="datetime-local" name="prazo" value="<?= e($prazoVal($a['Prazo'] ?? null)) ?>">
        </div>
        <div class="campo">
            <label>Visibilidade</label>
            <div class="atv-visib">
                <?php foreach (ATV_VISIB as $v): ?>
                <label><input type="radio" name="visibilidade" value="<?= e($v) ?>"
                    <?= ($a['Visibilidade'] ?? 'Individual') === $v ? 'checked' : '' ?>> <?= e($v) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="campo">
            <label>Setor (quando visibilidade = Setor)</label>
            <input type="text" name="setor" list="dlSetoresAtv" maxlength="60"
                   value="<?= e($a['Setor'] ?? '') ?>" placeholder="Setor que podera ver" autocomplete="off">
            <datalist id="dlSetoresAtv">
                <?php foreach ($setores as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
            </datalist>
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <label class="atv-urg-chk"><input type="checkbox" name="urgente" value="1"
                <?= !empty($a['Urgente']) ? 'checked' : '' ?>> <span class="atv-urg">!</span> Atividade urgente</label>
        </div>
        <?php if ($acao === 'criar'): ?>
        <div class="campo" style="grid-column:1/-1">
            <label>Participantes adicionais (podem apontar tempo, comentar e ser transferidos)</label>
            <details class="atv-combo">
                <summary class="atv-combo-sum"><span class="atv-parts-count">nenhum selecionado</span><span class="atv-combo-caret">▾</span></summary>
                <div class="atv-parts-box">
                    <?php foreach ($usuarios as $u): ?>
                    <label><input type="checkbox" name="participantes[]" value="<?= (int) $u['Id'] ?>"
                        <?= in_array((int) $u['Id'], $pIds, true) ? 'checked' : '' ?>> <?= e($u['Nome']) ?><?= $u['Setor'] ? ' · ' . e($u['Setor']) : '' ?></label>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php endif; ?>
    </div>
    <?php
};
?>
<div class="pedidos-coluna">

<?php if ($aviso): ?>
<div class="<?= $aviso['tipo'] === 'ok' ? 'alert-ok' : 'alert-erro' ?>" style="margin-bottom:14px"><?= $aviso['html'] ?></div>
<?php endif; ?>

<div class="filtros" style="margin-bottom:12px">
    <a class="btn btn-secundario" href="<?= url('atividades') ?>">← Voltar a lista</a>
</div>

<?php if ($nova): ?>
<!-- ===================== NOVA ATIVIDADE ===================== -->
<?php if (!$gestor): ?>
    <div class="alert-erro">Somente gestores e coordenadores podem criar atividades.</div>
<?php else: ?>
<div class="cfg-card" style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:22px; box-shadow:var(--shadow-sm)">
    <h2 style="margin:0 0 14px; font-size:17px">Nova atividade</h2>
    <form method="post" action="<?= url('atividade') ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="criar">
        <?php $renderForm('criar', null, $usuariosAtrib, $setores); ?>
        <div id="atvGravando" style="display:none;position:fixed;inset:0;z-index:4000;
             background:rgba(255,255,255,.72);align-items:center;justify-content:center">
            <div style="background:#fff;border:1px solid var(--border-color);border-radius:12px;
                 padding:26px 34px;text-align:center;box-shadow:var(--shadow-lg)">
                <div style="display:inline-block;width:26px;height:26px;border:3px solid #cbd5e1;
                     border-top-color:#0f766e;border-radius:50%;animation:atvgirar .8s linear infinite;
                     margin-bottom:10px"></div>
                <div style="font-weight:600;color:#334155">Gravando a atividade…</div>
                <div style="font-size:.8rem;color:#94a3b8;margin-top:4px">
                    Incluindo participantes e vínculos — aguarde.</div>
            </div>
        </div>
        <div style="margin-top:16px; display:flex; justify-content:flex-end; gap:10px">
            <a href="<?= url('atividades') ?>" class="btn btn-secundario">Cancelar</a>
            <button type="submit" class="btn">Criar e atribuir</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php else:
    $gestor = $gestorDa; // daqui p/ frente: gestor DESTA atividade (setor ou global)
    $id = (int) $a['Atividade_Id'];
    $aberta = atv_aberto((string) $a['Status']);
    $urg = atv_urgente($a);
    $venc = atv_vencida($a);
    $euParticipa = atv_participa($id, (int) $eu['Id']);
    $resp = atv_responsavel($parts);
    $tsCri = atv_ts($a['CriadoEm']);
    $tsIni = atv_ts($a['IniciadoEm'] ?? null);
    $tsFim = atv_ts($a['ConcluidoEm'] ?? null) ?? time();
    $filaMin = $tsIni !== null ? ($tsIni - $tsCri) / 60 : (time() - $tsCri) / 60;
    $execMin = $tsIni !== null ? ($tsFim - $tsIni) / 60 : null;
    $transPerm = $gestor
        ? array_values(array_diff(ATV_STATUS, [(string) $a['Status']]))
        : (atv_transicoes_colaborador()[(string) $a['Status']] ?? []);
    $meuRodando = $apontMeu !== null;
    $meuRodandoMin = $meuRodando ? (time() - (atv_ts($apontMeu['Inicio']) ?? time())) / 60 : 0;
?>
<!-- ===================== CABECALHO ===================== -->
<div class="atv-head">
    <div class="atv-head-tit">
        <h2><?= e($a['Titulo']) ?></h2>
        <div class="atv-head-sub">
            criada por <strong><?= e($a['CriadoPorNome']) ?></strong> em <?= e(data_br(substr((string) $a['CriadoEm'], 0, 16), true)) ?>
            <?php if (trim((string) ($a['Referencia'] ?? '')) !== ''): ?>
                · <span class="atv-ref"><?= e($a['Referencia']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="atv-head-badges">
        <span class="badge <?= atv_status_css((string) $a['Status']) ?>"><?= e($a['Status']) ?></span>
        <?php if ($urg): ?><span class="atv-urg" title="Urgente">!</span><?php endif; ?>
        <?php if ($venc): ?><span class="badge atv-st-vencida">vencida</span><?php endif; ?>
        <span class="badge atv-visib"><?= e($a['Visibilidade']) ?><?= $a['Visibilidade'] === 'Setor' ? ' · ' . e($a['Setor']) : '' ?></span>
    </div>
</div>

<!-- ===================== CARDS ===================== -->
<div class="atv-cards">
    <div class="atv-card">
        <h3>Dados</h3>
        <dl class="atv-meta">
            <dt>Prazo</dt><dd class="<?= $venc ? 'atv-venc-txt' : '' ?>">
                <?= $a['Prazo'] ? e(data_br(substr((string) $a['Prazo'], 0, 16), true)) : '—' ?></dd>
            <dt>Abertura</dt><dd><?= e(data_br(substr((string) $a['CriadoEm'], 0, 16), true)) ?></dd>
            <dt>Inicio execucao</dt><dd><?= $a['IniciadoEm'] ? e(data_br(substr((string) $a['IniciadoEm'], 0, 16), true)) : '—' ?></dd>
            <dt>Conclusao</dt><dd><?= $a['ConcluidoEm'] ? e(data_br(substr((string) $a['ConcluidoEm'], 0, 16), true)) : '—' ?></dd>
            <dt><?= $tsIni === null ? 'Na fila ha' : 'Tempo de fila' ?></dt><dd><?= e(atv_fmt_min($filaMin)) ?></dd>
            <dt>Tempo total</dt><dd><strong><?= e(atv_fmt_min($execMin)) ?></strong></dd>
        </dl>
        <?php if (trim((string) ($a['Orientacoes'] ?? '')) !== ''): ?>
        <div class="atv-orient"><?= nl2br(e($a['Orientacoes'])) ?></div>
        <?php endif; ?>
        <?php if ($gestor): ?>
        <details class="atv-edit">
            <summary>Editar dados da atividade</summary>
            <form method="post" action="<?= url('atividade') ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <?php $renderForm('editar', $a, $usuariosAtrib, $setores, $parts); ?>
                <div style="margin-top:12px"><button type="submit" class="btn">Salvar alteracoes</button></div>
            </form>
        </details>
        <?php endif; ?>
        <h4 style="margin:16px 0 6px">Vinculos ao ERP</h4>
        <div class="atv-vin-chips">
            <?php foreach ($vinculos as $v): ?>
            <span class="atv-chip atv-chip-vin" title="<?= e($v['Rotulo'] ?? '') ?>">
                <?= e(ATV_VINC_TIPOS[$v['Tipo']] ?? $v['Tipo']) ?> <strong><?= e($v['Chave']) ?></strong> <em><?= e($v['Base']) ?></em>
                <?php if (($euParticipa || $gestor) && $aberta): ?>
                <form method="post" action="<?= url('atividade') ?>" style="display:inline"
                      onsubmit="return confirm('Remover este vinculo?');">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="vinculo_remover">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="vinculo" value="<?= (int) $v['Vinculo_Id'] ?>">
                    <button type="submit" class="atv-chip-x" title="Remover">×</button>
                </form>
                <?php endif; ?>
            </span>
            <?php endforeach; ?>
            <?php if (!$vinculos): ?><span style="color:#94a3b8;font-size:.85rem">nenhum</span><?php endif; ?>
        </div>
        <?php if (($euParticipa || $gestor) && $aberta): ?>
        <div class="atv-vinc" data-id="<?= $id ?>">
            <div class="atv-vinc-busca">
                <select class="atv-vin-tipo"><?php foreach (ATV_VINC_TIPOS as $k => $rot): ?><option value="<?= $k ?>"><?= e($rot) ?></option><?php endforeach; ?></select>
                <input class="atv-vin-q" type="text" placeholder="Numero + Enter p/ buscar" autocomplete="off">
            </div>
            <div class="atv-vin-res"></div>
        </div>
        <form method="post" action="<?= url('atividade') ?>" id="vinForm<?= $id ?>" style="display:none">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="vinculo_add">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="tipo" class="atv-vf-tipo">
            <input type="hidden" name="base" class="atv-vf-base">
            <input type="hidden" name="chave" class="atv-vf-chave">
            <input type="hidden" name="rotulo" class="atv-vf-rotulo">
        </form>
        <?php endif; ?>
    </div>

    <div class="atv-card">
        <h3>Equipe e tempo</h3>
        <dl class="atv-meta">
            <dt>Responsavel</dt><dd><strong><?= e($resp['Nome'] ?? '—') ?></strong></dd>
            <dt>Participantes</dt>
            <dd>
                <?php $ativos = array_filter($parts, fn($p) => (int) $p['Ativo'] === 1 && $p['Papel'] === 'participante'); ?>
                <?php if ($ativos): ?>
                    <?php foreach ($ativos as $p): ?>
                    <span class="atv-chip"><?= e($p['Nome']) ?>
                        <?php if ($gestor && $aberta): ?>
                        <form method="post" action="<?= url('atividade') ?>" style="display:inline"
                              onsubmit="return confirm('Remover <?= e($p['Nome']) ?> da atividade?');">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="participante_remover">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="usuario" value="<?= (int) $p['Usuario_Id'] ?>">
                            <button type="submit" class="atv-chip-x" title="Remover">×</button>
                        </form>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color:#94a3b8">somente o responsavel</span>
                <?php endif; ?>
                <?php if ($gestor && $aberta): ?>
                <form method="post" action="<?= url('atividade') ?>" class="atv-add-part">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="participante_add">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <select name="usuario" required>
                        <option value="">+ incluir participante…</option>
                        <?php foreach ($usuariosAtrib as $u):
                            if (atv_participa($id, (int) $u['Id'])) continue; ?>
                        <option value="<?= (int) $u['Id'] ?>"><?= e($u['Nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm">Incluir</button>
                </form>
                <?php endif; ?>
            </dd>
        </dl>
        <h4>Tempo trabalhado</h4>
        <table class="atv-trab">
            <tbody>
            <?php foreach ($trab['porUsuario'] as $t): ?>
                <tr><td><?= e($t['Nome']) ?></td><td class="col-valor"><?= e(atv_fmt_min((float) $t['Min'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$trab['porUsuario']): ?>
                <tr><td style="color:#94a3b8">Nenhum apontamento ainda</td><td></td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot><tr><td>Total</td><td class="col-valor"><strong><?= e(atv_fmt_min((float) $trab['total'])) ?></strong></td></tr></tfoot>
        </table>
    </div>
</div>

<!-- ===================== ACOES ===================== -->
<?php if ($aberta && ($euParticipa || $gestor)): ?>
<div class="atv-card atv-acoes">
    <?php if ($euParticipa): ?>
    <form method="post" action="<?= url('atividade') ?>" class="atv-acao">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="trabalhar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn <?= $meuRodando ? 'btn-danger' : '' ?>">
            <?= $meuRodando ? 'Parar (' . e(atv_fmt_min($meuRodandoMin)) . ')' : 'Iniciar trabalho' ?>
        </button>
        <span class="atv-acao-hint"><?= $meuRodando ? 'seu relogio esta rodando' : 'aponta tempo trabalhado' ?></span>
    </form>
    <?php endif; ?>
    <?php if ($transPerm): ?>
    <form method="post" action="<?= url('atividade') ?>" class="atv-acao">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="status">
        <input type="hidden" name="id" value="<?= $id ?>">
        <select name="status" required>
            <option value="">Mudar status…</option>
            <?php foreach ($transPerm as $s): ?>
                <option value="<?= e($s) ?>"><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secundario">Salvar</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= url('atividade') ?>" class="atv-acao">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="transferir">
        <input type="hidden" name="id" value="<?= $id ?>">
        <select name="para" required>
            <option value="">Transferir para…</option>
            <?php
            $dests = $gestor ? $usuariosAtrib : $ativos ?? [];
            foreach ($dests as $u):
                $uid = (int) ($u['Id'] ?? $u['Usuario_Id']);
                if ($uid === (int) ($resp['Usuario_Id'] ?? 0)) continue; ?>
            <option value="<?= $uid ?>"><?= e($u['Nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secundario"
                onclick="return confirm('Transferir a responsabilidade desta atividade?');">Transferir</button>
    </form>
    <?php if ($gestor && usuario_pode('atividades', 'excluir')): ?>
    <form method="post" action="<?= url('atividade') ?>" class="atv-acao" style="margin-left:auto"
          onsubmit="return confirm('Excluir esta atividade? Esta acao nao pode ser desfeita.');">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-danger">Excluir</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===================== COMENTARIO + LINHA DO TEMPO ===================== -->
<div class="atv-card">
    <h3>Linha do tempo</h3>
    <?php if ($aberta && ($euParticipa || $gestor)): ?>
    <form method="post" action="<?= url('atividade') ?>" class="atv-coment">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="comentar">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="text" name="texto" required maxlength="500" placeholder="Escreva um comentario…" autocomplete="off">
        <button type="submit" class="btn">Enviar</button>
    </form>
    <?php endif; ?>
    <div class="atv-tl">
        <?php foreach ($eventos as $ev):
            $tipo = (string) $ev['Tipo'];
            $icone = ['criacao' => '✦', 'status' => '⇄', 'comentario' => '💬', 'apontamento' => '⏱',
                      'transferencia' => '➜', 'participante' => '＋', 'edicao' => '✎'][$tipo] ?? '•'; ?>
        <div class="atv-tl-item atv-tl-<?= e($tipo) ?>">
            <span class="atv-tl-ico"><?= $icone ?></span>
            <div class="atv-tl-corpo">
                <div class="atv-tl-topo">
                    <strong><?= e($ev['UsuarioNome'] ?? 'sistema') ?></strong>
                    <span class="atv-tl-data"><?= e(data_br(substr((string) $ev['DhEvento'], 0, 16), true)) ?></span>
                </div>
                <div class="atv-tl-txt">
                    <?php if ($tipo === 'status'): ?>
                        status: <span class="badge <?= atv_status_css((string) $ev['DeValor']) ?>"><?= e($ev['DeValor']) ?></span>
                        → <span class="badge <?= atv_status_css((string) $ev['ParaValor']) ?>"><?= e($ev['ParaValor']) ?></span>
                    <?php elseif ($tipo === 'transferencia'): ?>
                        transferiu a responsabilidade: <strong><?= e($ev['DeValor']) ?></strong> → <strong><?= e($ev['ParaValor']) ?></strong>
                    <?php elseif ($tipo === 'participante'): ?>
                        <?= e($ev['ParaValor']) ?> na equipe
                    <?php else: ?>
                        <?= nl2br(e($ev['Texto'] ?? '')) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$eventos): ?><p style="color:#94a3b8">Sem eventos ainda.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>

<script>
// vinculos ERP: botao Buscar abre modal com grid multi-selecao; ao confirmar,
// itens viram chips (novo) ou sao gravados via POST unico (detalhe)
(function () {
    var modal = document.createElement('div');
    modal.className = 'atv-modal';
    modal.style.display = 'none';
    modal.innerHTML =
        '<div class="atv-modal-box">' +
        '<div class="atv-modal-head"><strong id="atvModalTit">Vincular itens</strong>' +
        '<button type="button" class="atv-chip-x" id="atvModalX">×</button></div>' +
        '<div class="atv-modal-filtros">' +
        '<input id="mfEmp" type="text" list="mfEmpList" placeholder="Empresa" autocomplete="off"><datalist id="mfEmpList"></datalist>' +
        '<input id="mfNum" type="text" placeholder="Numero" autocomplete="off">' +
        '<input id="mfEsp" type="text" placeholder="Especificacao" autocomplete="off">' +
        '<input id="mfCen" type="text" list="mfCenList" placeholder="Centro" autocomplete="off"><datalist id="mfCenList"></datalist>' +
        '<input id="mfDe" type="date" title="Data inicial">' +
        '<input id="mfAte" type="date" title="Data final">' +
        '<span id="mfPrev" style="display:none;align-items:center;gap:8px;font-size:.8rem;' +
        'color:#475569;white-space:nowrap"><b>Previsões:</b> ' +
        '<label><input type="checkbox" id="mfEd" checked> ED</label>' +
        '<label><input type="checkbox" id="mfEp" checked> EP</label>' +
        '<label><input type="checkbox" id="mfCv" checked> CV</label></span>' +
        '<button type="button" class="btn" id="mfOk">Filtrar</button></div>' +
        '<div class="atv-modal-grid"><table class="tabela"><thead></thead><tbody id="atvModalBody"></tbody></table></div>' +
        '<div class="atv-modal-foot">' +
        '<button type="button" class="btn" id="atvModalOk">Vincular selecionados</button>' +
        '<button type="button" class="btn btn-secundario" id="atvModalCan">Cancelar</button></div></div>';
    document.body.appendChild(modal);
    var boxAtual = null;
    document.getElementById('atvModalX').onclick =
        document.getElementById('atvModalCan').onclick = function () { modal.style.display = 'none'; };
    document.getElementById('atvModalOk').onclick = function () {
        if (boxAtual) { boxAtual._confirmar(); }
        modal.style.display = 'none';
    };

    document.querySelectorAll('.atv-vinc').forEach(function (box) {
        var tipo = box.querySelector('.atv-vin-tipo'),
            q = box.querySelector('.atv-vin-q'),
            res = box.querySelector('.atv-vin-res'),
            novo = box.dataset.novo === '1',
            chips = box.querySelector('.atv-vin-chips'),
            form = document.getElementById('vinForm' + (box.dataset.id || ''));
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secundario';
        btn.style.whiteSpace = 'nowrap';
        btn.textContent = 'Buscar';
        q.insertAdjacentElement('afterend', btn);

        function filtrar() {
            document.getElementById('mfPrev').style.display =
                tipo.value === 'Agenda' ? 'inline-flex' : 'none';
            var p = 'atividade/vinculos?tipo=' + encodeURIComponent(tipo.value) +
                '&q=' + encodeURIComponent(document.getElementById('mfNum').value.trim()) +
                '&esp=' + encodeURIComponent(document.getElementById('mfEsp').value.trim()) +
                '&centro=' + encodeURIComponent(document.getElementById('mfCen').value.trim()) +
                '&empresa=' + encodeURIComponent(document.getElementById('mfEmp').value.trim()) +
                '&de=' + encodeURIComponent(document.getElementById('mfDe').value) +
                '&ate=' + encodeURIComponent(document.getElementById('mfAte').value) +
                '&ed=' + (document.getElementById('mfEd').checked ? 1 : 0) +
                '&ep=' + (document.getElementById('mfEp').checked ? 1 : 0) +
                '&cv=' + (document.getElementById('mfCv').checked ? 1 : 0);
            modal.style.display = 'flex';
            document.getElementById('atvModalTit').textContent =
                'Vincular — ' + tipo.options[tipo.selectedIndex].text;
            document.getElementById('atvModalBody').innerHTML =
                '<tr><td style="padding:24px;text-align:center;color:#475569">' +
                '<div class="atv-spin" style="display:inline-block;width:26px;height:26px;' +
                'border:3px solid #cbd5e1;border-top-color:#0f766e;border-radius:50%;' +
                'animation:atvgirar .8s linear infinite;margin-bottom:10px"></div>' +
                '<div>Carregando informações…</div>' +
                '<div style="font-size:.8rem;color:#94a3b8;margin-top:4px">' +
                'Períodos longos podem demorar um pouco mais.</div></td></tr>';
            fetch(p).then(function (r) { return r.json(); }).then(function (resp) {
                if (!resp || !resp.cols) {
                    modal.style.display = 'flex';
                    document.getElementById('atvModalTit').textContent = 'Vincular';
                    document.getElementById('atvModalBody').innerHTML =
                        '<tr><td style="color:#b91c1c">Falha na consulta ao ERP' +
                        (resp && resp.erro ? ': ' + resp.erro : '. Tente novamente.') +
                        '</td></tr>';
                    return;
                }
                abrir(resp);
            }).catch(function () {
                modal.style.display = 'flex';
                document.getElementById('atvModalBody').innerHTML =
                    '<tr><td style="color:#b91c1c">Sem resposta do servidor.</td></tr>';
            });
        }
        function buscar() {
            document.getElementById('mfNum').value = q.value.trim();
            document.getElementById('mfEsp').value = '';
            document.getElementById('mfCen').value = '';
            document.getElementById('mfEmp').value = '';
            document.getElementById('mfDe').value = '';
            document.getElementById('mfAte').value = '';
            document.getElementById('mfOk').onclick = filtrar;
            if (!modal._combo) {
                fetch('atividade/vinculos?lista=combo')
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        ['empresas', 'centros'].forEach(function (k) {
                            var dl = document.getElementById(k === 'empresas' ? 'mfEmpList' : 'mfCenList');
                            (d[k] || []).forEach(function (c) {
                                var o = document.createElement('option');
                                o.value = c.nome + ' (' + c.base + ')';
                                dl.appendChild(o);
                            });
                        });
                        modal._combo = true;
                    });
            }
            ['mfNum', 'mfEsp', 'mfCen', 'mfEmp'].forEach(function (i) {
                document.getElementById(i).onkeydown = function (ev) {
                    if (ev.key === 'Enter') { filtrar(); }
                };
            });
            filtrar();
        }
        function abrir(resp) {
            document.getElementById('atvModalTit').textContent =
                'Vincular — ' + tipo.options[tipo.selectedIndex].text;
            var tb = document.getElementById('atvModalBody'),
                thead = modal.querySelector('thead');
            thead.innerHTML = '<tr><th style="width:34px">' +
                '<input type="checkbox" id="atvModalAll" title="Marcar todos"></th>' +
                resp.cols.map(function (c) { return '<th>' + c + '</th>'; }).join('') +
                '</tr>';
            thead.querySelector('#atvModalAll').onclick = function () {
                modal.querySelectorAll('.atv-mchk').forEach(function (c) { c.checked = this.checked; }, this);
            };
            tb.innerHTML = '';
            (resp.rows || []).forEach(function (it) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><input type="checkbox" class="atv-mchk"></td>' +
                    it.vals.map(function (v, i) {
                        return i === 0 ? '<td><strong>' + v + '</strong></td>' : '<td>' + v + '</td>';
                    }).join('');
                tr._it = it;
                tb.appendChild(tr);
            });
            if (!(resp.rows || []).length) {
                tb.innerHTML = '<tr><td colspan="' + (resp.cols.length + 2) +
                    '" style="color:#94a3b8">nenhum item encontrado</td></tr>';
            }
            boxAtual = box;
            modal.style.display = 'flex';
        }
        function addChip(it) {
            var s = document.createElement('span');
            s.className = 'atv-chip atv-chip-vin';
            s.innerHTML = it.tipo + ' <strong>' + it.chave + '</strong> <em>' + it.base +
                '</em> <b title="remover" style="cursor:pointer">×</b>';
            s.querySelector('b').onclick = function () { s.remove(); };
            addHidden(s, it);
            chips.appendChild(s);
        }
        function addHidden(host, it) {
            ['tipo', 'base', 'chave', 'rotulo'].forEach(function (k) {
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'vinc_' + k + '[]';
                h.value = it[k];
                host.appendChild(h);
            });
        }
        box._confirmar = function () {
            modal.querySelectorAll('.atv-mchk:checked').forEach(function (c) {
                var it = c.closest('tr')._it;
                if (novo) { addChip(it); } else if (form) { addHidden(form, it); }
            });
            if (!novo && form && form.querySelectorAll('input[name="vinc_tipo[]"]').length) {
                form.submit();
            }
        };
        q.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); buscar(); }
        });
        btn.addEventListener('click', buscar);
    });

    // filtro de setor -> restringe o combobox de responsavel
    var fSetor = document.getElementById('atvFiltroSetor'),
        respSel = document.getElementById('atvRespSel');
    if (fSetor && respSel) {
        fSetor.addEventListener('change', function () {
            var s = this.value;
            respSel.querySelectorAll('option').forEach(function (op) {
                op.hidden = s !== '' && op.value !== '' && (op.dataset.setor || '') !== s;
            });
            if (respSel.selectedIndex > 0 && respSel.options[respSel.selectedIndex].hidden) {
                respSel.selectedIndex = 0;
            }
        });
        // criacao: setor ja vem pre-selecionado -> filtra o responsavel na abertura
        if (fSetor.value !== '' && !respSel.disabled) {
            fSetor.dispatchEvent(new Event('change'));
        }
    }
    // criacao: overlay "gravando" ao submeter (impede duplo clique)
    var formCriar = document.querySelector('form input[name=acao][value=criar]');
    if (formCriar) {
        formCriar.closest('form').addEventListener('submit', function (ev) {
            var btn = this.querySelector('button[type=submit]');
            if (btn && btn.disabled) { ev.preventDefault(); return; }
            if (btn) { btn.disabled = true; btn.textContent = 'Gravando…'; }
            var ov = document.getElementById('atvGravando');
            if (ov) { ov.style.display = 'flex'; }
        });
    }
    // combobox de participantes: contador no resumo
    document.querySelectorAll('.atv-combo').forEach(function (d) {
        var c = d.querySelector('.atv-parts-count');
        function upd() {
            var n = d.querySelectorAll('input[type=checkbox]:checked').length;
            c.textContent = n ? n + ' selecionado(s)' : 'nenhum selecionado';
        }
        d.querySelectorAll('input[type=checkbox]').forEach(function (b) {
            b.addEventListener('change', upd);
        });
        upd();
    });
})();
</script>
