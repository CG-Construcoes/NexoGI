<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

function carregar_env(string $arquivo): void
{
    if (!is_file($arquivo)) {
        return;
    }
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
            continue;
        }
        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
        if (getenv($chave) === false) {
            putenv("$chave=$valor");
            $_ENV[$chave] = $valor;
        }
    }
}

function env(string $chave, string $padrao = ''): string
{
    $v = getenv($chave);
    return $v === false || $v === '' ? $padrao : $v;
}

carregar_env(ROOT . '/.env');
date_default_timezone_set('America/Fortaleza');

require ROOT . '/app/db.php';
require ROOT . '/app/modules.php';
require ROOT . '/app/helpers.php';
require ROOT . '/app/mail.php';
require ROOT . '/app/auth.php';
require ROOT . '/app/usuarios.php';
require ROOT . '/app/usuario_grupos.php';
require ROOT . '/app/acesso.php';
require ROOT . '/app/pedidos.php';
require ROOT . '/app/pedido_impressao.php';
require ROOT . '/app/indicadores.php';
require ROOT . '/app/relatorio_economico.php';
require ROOT . '/app/relatorio_financeiro.php';
require ROOT . '/app/posicao_contas.php';
require ROOT . '/app/matriz_prioridades.php';
require ROOT . '/app/fluxo_caixa.php';
