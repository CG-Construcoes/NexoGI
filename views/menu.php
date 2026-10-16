<h2>Modulos</h2>
<?php if (!empty($pendentes)): ?>
<a class="alerta-pendente" href="<?= url('usuarios') ?>">
    <span class="alerta-icone"><?= icone_svg('alerta', 20) ?></span>
    <span><?= (int) $pendentes ?> usuario(s) aguardando liberacao de acesso — clique para liberar</span>
</a>
<?php endif; ?>
<div class="grid-modulos">
    <?php foreach (MODULES as $g):
        if ($g['nome'] === 'Configuracoes') continue; ?>
    <div class="card-modulo <?= $g['itens'] ? 'card-expansivel' : 'desativado' ?>">
        <div class="card-topo">
            <div class="card-icone"><?= icone_svg($g['nome'], 44) ?></div>
            <div class="card-nome">
                <span><?= e($g['nome']) ?></span>
                <?php if ($g['itens']): ?>
                <span class="card-seta"><?= icone_svg('chevron', 16) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!$g['itens']): ?>
            <div class="card-status">Em breve</div>
            <?php endif; ?>
        </div>
        <?php if ($g['itens']): ?>
        <div class="card-subitens-wrap">
            <div class="card-subitens">
                <?php foreach ($g['itens'] as $item):
                    if (isset($item['itens'])): ?>
                    <span class="card-subgrupo-titulo"><?= e($item['nome']) ?></span>
                    <?php foreach ($item['itens'] as $sub):
                        if (!usuario_pode($sub['slug'])) continue; ?>
                    <a href="<?= $sub['pronto'] ? url($sub['slug']) : '#' ?>"
                       class="card-subitem <?= $sub['pronto'] ? '' : 'desativado' ?>"
                       <?= $sub['pronto'] ? '' : 'title="Em breve"' ?>><?= e($sub['nome']) ?></a>
                    <?php endforeach; ?>
                    <?php else:
                        if (!usuario_pode($item['slug'])) continue; ?>
                    <a href="<?= $item['pronto'] ? url($item['slug']) : '#' ?>"
                       class="card-subitem <?= $item['pronto'] ? '' : 'desativado' ?>"
                       <?= $item['pronto'] ? '' : 'title="Em breve"' ?>><?= e($item['nome']) ?></a>
                    <?php endif;
                endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<div class="versao-app" title="Ultima alteracao no sistema">v. <?= e(versao_app()) ?></div>

<script>
(function () {
    var cards = document.querySelectorAll('.card-modulo.card-expansivel');
    cards.forEach(function (card) {
        card.addEventListener('click', function (ev) {
            if (ev.target.closest('a.card-subitem')) return;
            var abrir = !card.classList.contains('aberto');
            cards.forEach(function (c) { c.classList.remove('aberto'); });
            if (abrir) card.classList.add('aberto');
            ev.stopPropagation();
        });
    });
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('.card-modulo')) {
            cards.forEach(function (c) { c.classList.remove('aberto'); });
        }
    });
})();
</script>
