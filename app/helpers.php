<?php
declare(strict_types=1);

/** Escapa texto para HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = ''): string
{
    return '/' . ltrim($caminho, '/');
}

function redirecionar(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}

/** Renderiza uma view dentro de um layout (base = paginas publicas, app = sistema com menu). */
function render(string $view, array $vars = [], string $layout = 'app'): void
{
    $vars += ['titulo' => '', 'active' => null];
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT . "/views/$view.php";
    $conteudo = ob_get_clean();
    require ROOT . "/views/layout_$layout.php";
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/** Data do SQL Server ('2026-09-30 12:35:00.000') em dd/mm/aaaa [hh:mm]. */
function data_br(?string $valor, bool $comHora = false): string
{
    if (!$valor) {
        return '';
    }
    $ts = strtotime($valor);
    return $ts === false ? $valor : date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

/** Aviso exibido uma unica vez na proxima pagina (padrao POST -> redirect -> GET). */
function definir_aviso(array $aviso): void
{
    $_SESSION['aviso'] = $aviso;
}

function consumir_aviso(): ?array
{
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $aviso;
}

/** Versao do app: data/hora da ultima alteracao nos fontes (rodape do menu). */
function versao_app(): string
{
    static $v = null;
    if ($v === null) {
        $t = 0;
        foreach (['app/*.php', 'views/*.php', 'public/*.php', 'public/static/css/*.css'] as $padrao) {
            foreach (glob(ROOT . "/$padrao") ?: [] as $f) {
                $t = max($t, (int) filemtime($f));
            }
        }
        $v = date('d/m/Y H:i', $t ?: time());
    }
    return $v;
}

function tela_pronta(string $slug): bool
{
    foreach (MODULES as $g) {
        foreach ($g['itens'] as $item) {
            if (isset($item['itens'])) {
                foreach ($item['itens'] as $sub) {
                    if ($sub['slug'] === $slug) {
                        return $sub['pronto'];
                    }
                }
            } elseif (($item['slug'] ?? '') === $slug) {
                return $item['pronto'];
            }
        }
    }
    return TELAS_PUBLICAS_PRONTAS[$slug] ?? false;
}

/** Retorna icone SVG moderno pelo nome do modulo ou chave. */
function icone_svg(string $nome, int $size = 20): string
{
    static $seq = 0;
    $seq++;
    $gid = 'ngrad_' . $seq;
    $chave = mb_strtolower(trim($nome));

    $attrGrad = 'width="' . $size . '" height="' . $size . '" viewBox="0 0 48 48" fill="none" aria-hidden="true"';
    $defs = '<defs><linearGradient id="' . $gid . '" x1="4" y1="24" x2="44" y2="24" gradientUnits="userSpaceOnUse">'
        . '<stop offset="0%" stop-color="#86c867"/>'
        . '<stop offset="50%" stop-color="#3fa39c"/>'
        . '<stop offset="100%" stop-color="#1974b8"/>'
        . '</linearGradient></defs>';

    $attrUi = 'width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    return match ($chave) {
        // Suprimentos: caixa 3D em perspectiva (topo com divisoria, frente com alca horizontal, lateral direita)
        'suprimentos', '🚚' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<polygon points="6,17 16,9 42,9 32,17"/>'
            . '<line x1="29" y1="9" x2="19" y2="17"/>'
            . '<rect x="6" y="17" width="26" height="22"/>'
            . '<polygon points="32,17 42,9 42,31 32,39"/>'
            . '<line x1="14" y1="25" x2="24" y2="25" stroke-width="3.2"/>'
            . '</g></svg>',

        // Estoque: quadro com barra superior horizontal e 3 colunas verticais crescentes
        'estoque', '📦' => '<svg ' . $attrGrad . '>' . $defs
            . '<rect x="6" y="6" width="36" height="36" rx="1.5" stroke="url(#' . $gid . ')" stroke-width="3"/>'
            . '<rect x="10" y="10" width="28" height="4" fill="url(#' . $gid . ')"/>'
            . '<rect x="10" y="29" width="7" height="9" fill="url(#' . $gid . ')"/>'
            . '<rect x="20.5" y="24" width="7" height="14" fill="url(#' . $gid . ')"/>'
            . '<rect x="31" y="18" width="7" height="20" fill="url(#' . $gid . ')"/>'
            . '</svg>',

        // Financeiro: cedula com moldura interna, circulo central e 2 linhas de pilha no topo
        'financeiro', '💰' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-linecap="round" stroke-linejoin="round">'
            . '<line x1="13" y1="12" x2="35" y2="12" stroke-width="2.6"/>'
            . '<line x1="9" y1="16" x2="39" y2="16" stroke-width="2.6"/>'
            . '<rect x="6" y="20" width="36" height="20" stroke-width="3"/>'
            . '<path d="M11 24H37V36H11Z" stroke-width="2.2"/>'
            . '<circle cx="24" cy="30" r="3.5" stroke-width="2.6"/>'
            . '</g></svg>',

        // Comercial: maleta executiva com alca superior e aba em V na frente
        'comercial', '📈' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M17 15V10C17 8.9 17.9 8 19 8H29C30.1 8 31 8.9 31 10V15"/>'
            . '<rect x="6" y="15" width="36" height="25" rx="2"/>'
            . '<polyline points="6,23 24,29 42,23"/>'
            . '<line x1="24" y1="15" x2="24" y2="21"/>'
            . '</g></svg>',

        // Contabilidade: calculadora / balanco no mesmo estilo gradiente
        'contabilidade', '🧮' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="9" y="6" width="30" height="36" rx="2"/>'
            . '<rect x="14" y="11" width="20" height="6"/>'
            . '<circle cx="16" cy="24" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<circle cx="24" cy="24" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<circle cx="32" cy="24" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<circle cx="16" cy="31" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<circle cx="24" cy="31" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<line x1="32" y1="30" x2="32" y2="36"/>'
            . '<circle cx="16" cy="37" r="1.5" fill="url(#' . $gid . ')"/>'
            . '<circle cx="24" cy="37" r="1.5" fill="url(#' . $gid . ')"/>'
            . '</g></svg>',

        // Engenharia: capacete de obra no mesmo estilo gradiente
        'engenharia', '🏗️' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M8 30C8 20.5 15 13 24 13C33 13 40 20.5 40 30"/>'
            . '<path d="M21 13V9H27V13"/>'
            . '<rect x="5" y="30" width="38" height="6" rx="2"/>'
            . '</g></svg>',

        // Indicadores: grafico de performance no mesmo estilo gradiente
        'indicadores', '📊' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<line x1="6" y1="40" x2="42" y2="40"/>'
            . '<rect x="10" y="26" width="6" height="14" fill="url(#' . $gid . ')"/>'
            . '<rect x="21" y="18" width="6" height="22" fill="url(#' . $gid . ')"/>'
            . '<rect x="32" y="10" width="6" height="30" fill="url(#' . $gid . ')"/>'
            . '</g></svg>',

        // Configuracoes: engrenagem no mesmo estilo gradiente
        'configuracoes', '⚙️' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<circle cx="24" cy="24" r="6"/>'
            . '<path d="M24 6V11M24 37V42M6 24H11M37 24H42M11.3 11.3L14.8 14.8M33.2 33.2L36.7 36.7M36.7 11.3L33.2 14.8M14.8 33.2L11.3 36.7"/>'
            . '</g></svg>',

        'inicio', 'home' => '<svg ' . $attrUi . '><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'relogio', '⏱️' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<circle cx="24" cy="26" r="15"/>'
            . '<polyline points="24 18 24 26 30 30"/>'
            . '<line x1="19" y1="6" x2="29" y2="6"/>'
            . '</g></svg>',
        'alerta', '⚠' => '<svg ' . $attrUi . '><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',

        // Atividades: prancheta com check no mesmo estilo gradiente
        'atividades', '✅' => '<svg ' . $attrGrad . '>' . $defs
            . '<g stroke="url(#' . $gid . ')" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="10" y="8" width="28" height="34" rx="2"/>'
            . '<rect x="18" y="4" width="12" height="7" rx="1.5"/>'
            . '<polyline points="17,27 22.5,32.5 32,22"/>'
            . '</g></svg>',
        'pin' => '<svg ' . $attrUi . '><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>',
        'chevron' => '<svg ' . $attrUi . '><polyline points="6 9 12 15 18 9"/></svg>',
        'sair' => '<svg ' . $attrUi . '><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        default => '<svg ' . $attrUi . '><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
    };
}

/**
 * Conteudo de storage/deploy-bloqueado.flag quando o git pull automatico
 * (cron, ~/bin/nexogi-deploy.sh) esta travado por alteracao local nao
 * commitada; null quando o deploy esta ok. Usado no banner do layout_app.
 */
function deploy_bloqueado(): ?string
{
    $flag = ROOT . '/storage/deploy-bloqueado.flag';
    if (!is_file($flag)) {
        return null;
    }
    $conteudo = trim((string) file_get_contents($flag));
    return $conteudo === '' ? 'git pull bloqueado (sem detalhes no flag)' : $conteudo;
}
