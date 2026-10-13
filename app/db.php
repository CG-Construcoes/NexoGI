<?php
declare(strict_types=1);

/** Conexao com o SQL Server via PDO (pdo_sqlsrv ou pdo_odbc). Uma conexao por banco, reaproveitada. */
function conexao(?string $banco = null): PDO
{
    static $cache = [];
    $banco ??= env('DB_NAME', 'CGGestao');
    if (!isset($cache[$banco])) {
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (extension_loaded('pdo_sqlsrv')) {
            $dsn = sprintf(
                'sqlsrv:Server=%s;Database=%s;Encrypt=yes;TrustServerCertificate=yes;LoginTimeout=10',
                env('DB_SERVER'),
                $banco
            );
            $opcoes[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
            $cache[$banco] = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), $opcoes);
        } else {
            $driver = env('DB_DRIVER', 'ODBC Driver 18 for SQL Server');
            $dsn = sprintf(
                'odbc:Driver={%s};Server=%s;Database=%s;Encrypt=yes;TrustServerCertificate=yes;LoginTimeout=10',
                $driver,
                env('DB_SERVER'),
                $banco
            );
            $cache[$banco] = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), $opcoes);
        }
    }
    return $cache[$banco];
}

/** Executa SELECT e retorna lista de arrays associativos. */
function query(string $sql, array $params = [], ?string $banco = null): array
{
    $st = conexao($banco)->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Executa INSERT/UPDATE/DELETE. Retorna linhas afetadas. */
function execute(string $sql, array $params = [], ?string $banco = null): int
{
    $st = conexao($banco)->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
