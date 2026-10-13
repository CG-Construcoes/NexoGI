<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?: 'Msoft') ?> · Msoft ERP</title>
    <link rel="stylesheet" href="<?= url('static/css/style.css') . '?v=' . filemtime(ROOT . '/public/static/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
    <div class="sidebar-trigger" id="sidebarTrigger"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">Msoft</div>
        <nav>
            <a href="<?= url('menu') ?>" class="nav-item <?= $active === 'menu' ? 'ativo' : '' ?>">Inicio</a>
            <?php foreach (MODULES as $g):
                $grupoAtivo = in_array($active, array_column($g['itens'], 'slug'), true); ?>
            <div class="nav-grupo <?= $grupoAtivo ? 'aberto' : '' ?>">
                <span class="nav-grupo-titulo"><?= e($g['icone']) ?> <?= e($g['nome']) ?></span>
                <?php if ($g['itens']): ?>
                <div class="nav-subitens">
                    <?php foreach ($g['itens'] as $item):
                        if (isset($item['itens'])): ?>
                        <div class="nav-subgrupo">
                            <span class="nav-subgrupo-titulo"><?= e($item['nome']) ?></span>
                            <?php foreach ($item['itens'] as $sub):
                                if (!usuario_pode($sub['slug'])) continue; ?>
                            <a href="<?= $sub['pronto'] ? url($sub['slug']) : '#' ?>"
                               class="nav-subitem <?= $active === $sub['slug'] ? 'ativo' : '' ?> <?= $sub['pronto'] ? '' : 'desativado' ?>"
                               <?= $sub['pronto'] ? '' : 'title="Em breve"' ?>><?= e($sub['nome']) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php else:
                            if (!usuario_pode($item['slug'])) continue; ?>
                        <a href="<?= $item['pronto'] ? url($item['slug']) : '#' ?>"
                           class="nav-subitem <?= $active === $item['slug'] ? 'ativo' : '' ?> <?= $item['pronto'] ? '' : 'desativado' ?>"
                           <?= $item['pronto'] ? '' : 'title="Em breve"' ?>><?= e($item['nome']) ?></a>
                        <?php endif;
                    endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <span class="topbar-titulo"><?= e($titulo) ?></span>
            <?= $topbar_extra ?? '' ?>
            <div class="topbar-user">
                <a href="<?= url('menu') ?>" class="btn btn-sm" title="Pagina inicial">&#127968; Inicio</a>
                <span><?= e($_SESSION['nome'] ?? '') ?></span>
                <a href="<?= url('logout') ?>" class="btn btn-sm">Sair</a>
            </div>
        </header>
        <main class="content">
            <?= $conteudo ?>
        </main>
    </div>
</div>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const trigger = document.getElementById('sidebarTrigger');
    if (!sidebar || !trigger) return;
    let hideTimeout;
    function show() {
        clearTimeout(hideTimeout);
        sidebar.classList.add('aberto');
    }
    function hide() {
        hideTimeout = setTimeout(() => sidebar.classList.remove('aberto'), 200);
    }
    trigger.addEventListener('mouseenter', show);
    trigger.addEventListener('mouseleave', hide);
    sidebar.addEventListener('mouseenter', show);
    sidebar.addEventListener('mouseleave', hide);
})();

(function () {
    document.querySelectorAll('.nav-grupo-titulo').forEach(titulo => {
        titulo.addEventListener('click', () => {
            titulo.parentElement.classList.toggle('aberto');
        });
    });
})();
</script>
</body>
</html>
