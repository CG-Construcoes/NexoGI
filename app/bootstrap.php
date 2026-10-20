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

// Polyfill mbstring (UTF-8 via PCRE) para hosts sem a extensao php-mbstring —
// o servidor NexoGI nao a tinha e a pagina morria no primeiro mb_strtolower().
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s, ?string $e = null): string
    {
        return strtolower($s); // fallback simples: acentos nao sao dobrados
    }
    function mb_strtoupper(string $s, ?string $e = null): string
    {
        return strtoupper($s);
    }
    function mb_strlen(string $s, ?string $e = null): int
    {
        return (int) preg_match_all('/./us', $s);
    }
    function mb_substr(string $s, int $start, ?int $len = null, ?string $e = null): string
    {
        $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $c = array_slice($c, $start, $len);
        return implode('', $c);
    }
    function mb_strrpos(string $s, string $n, int $off = 0, ?string $e = null): int|false
    {
        $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = count($c) - 1; $i >= $off; $i--) {
            if ($c[$i] === $n) {
                return $i;
            }
        }
        return false;
    }
    function mb_str_split(string $s, int $len = 1, ?string $e = null): array
    {
        $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return $len === 1 ? $c : array_map('implode', array_chunk($c, $len));
    }
}

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
require ROOT . '/app/fluxo_caixa_saldos.php';
require ROOT . '/app/medicoes.php';
require ROOT . '/app/contas_pagar.php';
require ROOT . '/app/agenda_pagamentos.php';
require ROOT . '/app/faturamento.php';
require ROOT . '/app/dre.php';
