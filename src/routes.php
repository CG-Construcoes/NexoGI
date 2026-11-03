<?php
declare(strict_types=1);

/**
 * Rotas NOVAS implementadas em Laminas (handlers em src/).
 * Formato:
 *   'rota/{param}' => [
 *       'metodos' => ['GET'],           // ou ['GET','POST']
 *       'handler' => function (array $p) { ... },
 *       // ou 'handler' => [new \Msoft\MinhaTela(), 'handle'],
 *   ],
 * Dentro do handler use os helpers existentes: query(), execute(),
 * exigir_tela(), render(), url(), redirecionar(), csrf_campo()...
 */
return [
    'captura-trace' => [
        'metodos' => ['GET'],
        'handler' => [\Msoft\Trace::class, 'tela'],
    ],
    'captura-trace/acao' => [
        'metodos' => ['POST'],
        'handler' => [\Msoft\Trace::class, 'acao'],
    ],
];
