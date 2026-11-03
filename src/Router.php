<?php
declare(strict_types=1);

namespace Msoft;

/**
 * Despachante das rotas NOVAS (padrao Laminas): handlers declarados em
 * src/routes.php. Rotas aceitam segmentos {param} (ex.: 'atividade/{id}').
 * O handler recebe os params e usa os helpers existentes (query(),
 * exigir_tela(), render(), url(), redirecionar(), csrf_*).
 *
 * laminas-router esta disponivel para matching mais rico quando precisar.
 */
final class Router
{
    /** true se a rota foi atendida por um handler Laminas. */
    public static function dispatch(string $rota, string $metodo): bool
    {
        static $map = null;
        $map ??= self::mapa();
        foreach ($map as $padrao => $def) {
            if (!in_array(strtoupper($metodo), array_map('strtoupper', (array) $def['metodos']), true)) {
                continue;
            }
            $params = self::match($padrao, $rota);
            if ($params === null) {
                continue;
            }
            ($def['handler'])($params);
            return true;
        }
        return false;
    }

    /** @return array<string,array{metodos:array,handler:callable}> */
    private static function mapa(): array
    {
        $defs = require dirname(__DIR__) . '/src/routes.php';
        return is_array($defs) ? $defs : [];
    }

    /** Casa 'a/{x}/b' com 'a/9/b' -> ['x'=>'9']; null = nao casa. */
    private static function match(string $padrao, string $rota): ?array
    {
        $ps = explode('/', trim($padrao, '/'));
        $rs = explode('/', trim($rota, '/'));
        if (count($ps) !== count($rs)) {
            return null;
        }
        $params = [];
        foreach ($ps as $i => $p) {
            if (str_starts_with($p, '{') && str_ends_with($p, '}')) {
                $params[substr($p, 1, -1)] = $rs[$i];
            } elseif ($p !== $rs[$i]) {
                return null;
            }
        }
        return $params;
    }
}
