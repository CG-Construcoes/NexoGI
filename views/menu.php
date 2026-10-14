<h2>Modulos</h2>
<?php if (!empty($pendentes)): ?>
<a class="alerta-pendente" href="<?= url('usuarios') ?>">
    ⚠ <?= (int) $pendentes ?> usuario(s) aguardando liberacao de acesso — clique para liberar
</a>
<?php endif; ?>
<div class="grid-modulos">
    <?php foreach (MODULES as $g): ?>
    <div class="card-modulo <?= $g['itens'] ? '' : 'desativado' ?>">
        <div class="card-icone"><?= e($g['icone']) ?></div>
        <div class="card-nome"><?= e($g['nome']) ?></div>
        <?php if ($g['itens']): ?>
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
        <?php else: ?>
        <div class="card-status">Em breve</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<div class="versao-app" title="Ultima alteracao no sistema">v. <?= e(versao_app()) ?></div>
