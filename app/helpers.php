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
            if ($item['slug'] === $slug) {
                return $item['pronto'];
            }
        }
    }
    return TELAS_PUBLICAS_PRONTAS[$slug] ?? false;
}
