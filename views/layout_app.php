<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f2942">
    <title><?= e($titulo ?: 'Nouvellesobra') ?> · Nouvellesobra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('static/css/style.css') . '?v=' . filemtime(ROOT . '/public/static/css/style.css') ?>">
</head>
<body>
<script>
try {
    if (window.innerWidth > 768 && localStorage.getItem('nouvellesobra_sidebar_pinned') === '1') {
        document.body.classList.add('sidebar-fixa');
    }
    if (localStorage.getItem('msoft_tema') === 'escuro') {
        document.body.classList.add('dark');
    }
} catch (e) {}
</script>
<?php if (env('APP_AMBIENTE') === 'treino'): ?>
<style>body { padding-top: 27px !important; } .sidebar { padding-top: 27px !important; }</style>
<div style="position:fixed; top:0; left:0; right:0; z-index:3000; background:#f59e0b; color:#1f2937;
    text-align:center; font-weight:700; font-size:.85rem; padding:5px 10px;
    letter-spacing:.5px; box-shadow:0 1px 4px rgba(0,0,0,.15)">
    ⚠ AMBIENTE TREINO — dados de teste (bases dbTREINO)
</div>
<?php endif; ?>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="sidebar-trigger" id="sidebarTrigger"></div>
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
        <div class="sidebar-header">
            <a href="<?= url('menu') ?>" class="sidebar-logo">
                <span class="sidebar-logo-mark">N</span>
                <span class="sidebar-logo-text">Nouvellesobra</span>
            </a>
            <div class="sidebar-header-actions">
                <button type="button" class="sidebar-pin" id="sidebarPin" aria-label="Fixar ou soltar menu lateral" title="Fixar / soltar menu lateral">
                    <?= icone_svg('pin', 16) ?>
                </button>
                <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Fechar menu">&times;</button>
            </div>
        </div>
        <nav>
            <a href="<?= url('menu') ?>" class="nav-item <?= $active === 'menu' ? 'ativo' : '' ?>">
                <span class="nav-item-icon"><?= icone_svg('inicio', 18) ?></span>
                <span>Inicio</span>
            </a>
            <?php foreach (MODULES as $g):
                $slugsGrupo = [];
                foreach ($g['itens'] as $it) {
                    if (isset($it['itens'])) {
                        foreach ($it['itens'] as $subIt) {
                            $slugsGrupo[] = $subIt['slug'];
                        }
                    } elseif (isset($it['slug'])) {
                        $slugsGrupo[] = $it['slug'];
                    }
                }
                $grupoAtivo = in_array($active, $slugsGrupo, true); ?>
            <div class="nav-grupo <?= $grupoAtivo ? 'aberto' : '' ?>">
                <span class="nav-grupo-titulo">
                    <span class="nav-grupo-label"><span class="nav-grupo-icone"><?= icone_svg($g['nome'], 17) ?></span> <?= e($g['nome']) ?></span>
                    <span class="nav-grupo-seta"><?= icone_svg('chevron', 14) ?></span>
                </span>
                <?php if ($g['itens']): ?>
                <div class="nav-subitens-wrap">
                    <div class="nav-subitens">
                        <?php foreach ($g['itens'] as $item):
                            if (isset($item['itens'])):
                                $subsVisiveis = array_values(array_filter($item['itens'],
                                    fn($sub) => usuario_pode($sub['slug'])));
                                if (!$subsVisiveis) continue;
                                $subAberto = in_array($active, array_column($subsVisiveis, 'slug'), true); ?>
                            <div class="nav-subgrupo <?= $subAberto ? 'aberto' : '' ?>">
                                <span class="nav-subgrupo-titulo">
                                    <span class="nav-subgrupo-label"><?= e($item['nome']) ?></span>
                                    <span class="nav-subgrupo-seta"><?= icone_svg('chevron', 12) ?></span>
                                </span>
                                <div class="nav-subgrupo-itens"><div class="nav-subgrupo-itens-inner">
                                <?php foreach ($subsVisiveis as $sub):
                                    $tip = $sub['pronto'] ? ($sub['title'] ?? '') : 'Em breve'; ?>
                                <a href="<?= $sub['pronto'] ? url($sub['slug']) : '#' ?>"
                                   class="nav-subitem <?= $active === $sub['slug'] ? 'ativo' : '' ?> <?= $sub['pronto'] ? '' : 'desativado' ?>"
                                   <?= $tip !== '' ? 'title="' . e($tip) . '"' : '' ?>><?= e($sub['nome']) ?></a>
                                <?php endforeach; ?>
                                </div></div>
                            </div>
                            <?php else:
                                if (!usuario_pode($item['slug'])) continue;
                                $tip = $item['pronto'] ? ($item['title'] ?? '') : 'Em breve'; ?>
                            <a href="<?= $item['pronto'] ? url($item['slug']) : '#' ?>"
                               class="nav-subitem <?= $active === $item['slug'] ? 'ativo' : '' ?> <?= $item['pronto'] ? '' : 'desativado' ?>"
                               <?= $tip !== '' ? 'title="' . e($tip) . '"' : '' ?>><?= e($item['nome']) ?></a>
                            <?php endif;
                        endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <div class="topbar-inicio">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Alternar ou fixar menu lateral" title="Menu lateral (clique para fixar/soltar)">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <span class="topbar-titulo"><?= e($titulo) ?></span>
            </div>
            <?= $topbar_extra ?? '' ?>
            <div class="topbar-user">
                <button type="button" id="btnTema" class="btn btn-sm btn-secundario"
                        title="Alternar modo claro/escuro" style="padding:5px 9px">🌙</button>
                <span style="position:relative">
                    <input type="text" id="buscaTelas" placeholder="Buscar tela…" autocomplete="off"
                           style="padding:5px 10px;border:1px solid #cbd5e1;border-radius:6px;
                                  font-size:.85rem;width:170px">
                    <div id="buscaTelasDrop" style="display:none;position:absolute;top:32px;right:0;
                         background:#fff;border:1px solid #cbd5e1;border-radius:8px;
                         box-shadow:0 8px 24px rgba(0,0,0,.15);min-width:300px;max-height:340px;
                         overflow:auto;z-index:2000;font-size:.85rem"></div>
                </span>
                <a href="<?= url('menu') ?>" class="btn btn-sm btn-secundario topbar-btn-home" title="Pagina inicial"><?= icone_svg('inicio', 15) ?> <span class="topbar-texto-btn">Inicio</span></a>
                <span class="topbar-usuario-nome" title="<?= e($_SESSION['usuario'] ?? '') ?>"><?= e($_SESSION['nome'] ?? '') ?></span>
                <a href="<?= url('logout') ?>" class="btn btn-sm topbar-btn-sair"><?= icone_svg('sair', 15) ?> <span>Sair</span></a>
            </div>
        </header>
        <main class="content">
            <?= $conteudo ?>
        </main>
    </div>
    <?php if (usuario_logado()):
        $atvCor = atv_badge_cor();
        $atvTit = ['cinza'    => 'Sem atividades pendentes',
                   'amarelo'  => 'Voce tem atividades em aberto',
                   'vermelho' => 'Atividade vencida ou urgente!'][$atvCor]; ?>
    <a href="<?= url('atividades') ?>" class="atv-ico atv-ico-<?= e($atvCor) ?>"
       title="<?= e($atvTit) ?> — Minhas Atividades"><?= icone_svg('alerta', 22) ?></a>
    <?php endif; ?>
</div>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const trigger = document.getElementById('sidebarTrigger');
    const toggleBtn = document.getElementById('sidebarToggle');
    const pinBtn = document.getElementById('sidebarPin');
    const closeBtn = document.getElementById('sidebarClose');
    const backdrop = document.getElementById('sidebarBackdrop');

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function isPinned() {
        return document.body.classList.contains('sidebar-fixa');
    }

    function togglePin() {
        const pinned = document.body.classList.toggle('sidebar-fixa');
        if (sidebar) {
            if (pinned) {
                sidebar.classList.add('aberto');
            } else {
                sidebar.classList.remove('aberto');
            }
        }
        try {
            localStorage.setItem('nouvellesobra_sidebar_pinned', pinned ? '1' : '0');
        } catch (e) {}
    }

    function closeMobile() {
        document.body.classList.remove('sidebar-mobile-aberto');
        if (sidebar && !isPinned()) sidebar.classList.remove('aberto');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            if (isMobile()) {
                document.body.classList.toggle('sidebar-mobile-aberto');
            } else {
                togglePin();
            }
        });
    }

    if (pinBtn) {
        pinBtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            togglePin();
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeMobile);
    if (backdrop) backdrop.addEventListener('click', closeMobile);

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeMobile();
    });

    if (sidebar && trigger) {
        let hideTimeout;
        function showHover() {
            if (isMobile() || isPinned()) return;
            clearTimeout(hideTimeout);
            sidebar.classList.add('aberto');
        }
        function hideHover() {
            if (isMobile() || isPinned()) return;
            hideTimeout = setTimeout(() => sidebar.classList.remove('aberto'), 220);
        }
        trigger.addEventListener('mouseenter', showHover);
        trigger.addEventListener('mouseleave', hideHover);
        sidebar.addEventListener('mouseenter', showHover);
        sidebar.addEventListener('mouseleave', hideHover);
    }
})();

(function () {
    document.querySelectorAll('.nav-grupo-titulo').forEach(titulo => {
        titulo.addEventListener('click', () => {
            titulo.parentElement.classList.toggle('aberto');
        });
    });

    document.querySelectorAll('.nav-subgrupo-titulo').forEach(titulo => {
        titulo.addEventListener('click', () => {
            titulo.parentElement.classList.toggle('aberto');
        });
    });

    document.querySelectorAll('table.tabela').forEach(function (tbl) {
        if (tbl.parentElement && tbl.parentElement.classList.contains('tabela-responsiva')) return;
        var wrap = document.createElement('div');
        wrap.className = 'tabela-responsiva';
        tbl.parentNode.insertBefore(wrap, tbl);
        wrap.appendChild(tbl);
    });

    let touchTimer = null;
    document.querySelectorAll('.fc-grupo-row').forEach(function (row) {
        row.addEventListener('touchstart', function (ev) {
            if (!ev.touches || ev.touches.length !== 1) return;
            const touch = ev.touches[0];
            touchTimer = setTimeout(function () {
                row.dispatchEvent(new MouseEvent('contextmenu', {
                    bubbles: true,
                    cancelable: true,
                    clientX: touch.clientX,
                    clientY: touch.clientY
                }));
            }, 550);
        }, { passive: true });
        row.addEventListener('touchend', function () { clearTimeout(touchTimer); });
        row.addEventListener('touchmove', function () { clearTimeout(touchTimer); });
    });
})();
</script>
<?php
// Telas pesquisaveis: "Grupo > Subgrupo > Tela", so as permitidas ao usuario
$buscaTelas = [];
$walkTelas = function (array $itens, array $caminho) use (&$walkTelas, &$buscaTelas) {
    foreach ($itens as $it) {
        if (isset($it['itens'])) {
            $walkTelas($it['itens'], array_merge($caminho, [$it['nome']]));
        } elseif (!empty($it['pronto']) && isset($it['slug']) && usuario_pode($it['slug'])) {
            $buscaTelas[] = ['p' => implode(' > ', $caminho), 'n' => $it['nome'],
                             'u' => url($it['slug'])];
        }
    }
};
foreach (MODULES as $g) {
    $walkTelas($g['itens'] ?? [], [$g['nome']]);
}
?>
<script>
(function () {
    var bt = document.getElementById('btnTema');
    function pintar() { bt.textContent = document.body.classList.contains('dark') ? '☀️' : '🌙'; }
    pintar();
    bt.addEventListener('click', function () {
        document.body.classList.toggle('dark');
        try {
            localStorage.setItem('msoft_tema',
                document.body.classList.contains('dark') ? 'escuro' : 'claro');
        } catch (e) {}
        pintar();
    });
    var inp = document.getElementById('buscaTelas'),
        drop = document.getElementById('buscaTelasDrop'),
        itens = <?= json_encode($buscaTelas, JSON_UNESCAPED_UNICODE) ?>;
    inp.addEventListener('input', function () {
        var t = inp.value.trim().toLowerCase();
        if (t.length < 2) { drop.style.display = 'none'; return; }
        var h = '';
        itens.forEach(function (i) {
            var rot = (i.p ? i.p + ' > ' : '') + i.n;
            if (rot.toLowerCase().indexOf(t) < 0) { return; }
            h += '<a href="' + i.u + '" style="display:block;padding:7px 12px;' +
                 'text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9">' +
                 '<span style="color:#64748b">' + i.p + ' &rsaquo;</span> ' + i.n + '</a>';
        });
        drop.innerHTML = h || '<div style="padding:10px;color:#94a3b8">nenhuma tela</div>';
        drop.style.display = 'block';
    });
    document.addEventListener('click', function (ev) {
        if (!inp.contains(ev.target) && !drop.contains(ev.target)) { drop.style.display = 'none'; }
    });
})();
</script>
</body>
</html>
