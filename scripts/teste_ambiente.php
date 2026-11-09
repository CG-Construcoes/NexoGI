<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$casos = [
    ['localhost', '', 'https://sistemacg.com', false],
    ['localhost:8000', 'on', 'https://sistemacg.com', false],
    ['127.0.0.1', '', 'https://sistemacg.com', false],
    ['127.0.0.1:8000', 'on', 'https://sistemacg.com', false],
    ['172.16.1.13', 'on', 'https://sistemacg.com', false],
    ['sistemacg.com', '', 'https://sistemacg.com', false],
    ['sistemacg.com.evil.test', 'on', 'https://sistemacg.com', false],
    ['sistemacg.com', 'on', 'http://localhost', false],
    ['sistemacg.com', 'on', '', false],
    ['sistemacg.com', 'on', 'https://sistemacg.com', true],
    ['SISTEMACG.COM', '1', 'https://sistemacg.com/', true],
];
foreach ($casos as [$host, $https, $appUrl, $esperado]) {
    $servidor = ['HTTP_HOST' => $host, 'HTTPS' => $https,
        'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'sistemacg.com'];
    if (ambiente_producao($servidor, $appUrl) !== $esperado) {
        throw new RuntimeException("Falha na selecao de ambiente: $host / $https / $appUrl");
    }
}
if (!modo_teste() || env('DB_ERP_MGA') !== 'dbTREINO_MGA'
    || env('DB_ERP_CONV') !== 'dbTREINO_CV' || env('APP_AMBIENTE') !== 'treino') {
    throw new RuntimeException('CLI deve usar somente treino.');
}
foreach (['dbMGA', 'dbCONV', 'DBCONV'] as $banco) {
    try {
        conexao($banco);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Acesso ao ERP de producao bloqueado no modo teste.') {
            continue;
        }
        throw $e;
    }
    throw new RuntimeException('Banco de producao nao foi bloqueado.');
}
echo "OK: 11 cenarios de endereco, mapeamento CLI e bloqueio de conexao; sem consultas ao banco.\n";
