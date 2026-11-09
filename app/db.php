<?php
declare(strict_types=1);

/** Conexao com o SQL Server via PDO (pdo_sqlsrv ou pdo_odbc). Uma conexao por banco, reaproveitada. */
function conexao(?string $banco = null): PDO
{
    static $cache = [];
    $banco ??= env('DB_NAME', 'CGGestao');
    if (modo_teste() && in_array(strtolower($banco), ['dbmga', 'dbconv'], true)) {
        throw new RuntimeException('Acesso ao ERP de producao bloqueado no modo teste.');
    }
    if (!isset($cache[$banco])) {
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        // DB_FORCE_ODBC=1 forca o caminho ODBC (diagnostico do servidor, que
        // nao tem pdo_sqlsrv)
        if (extension_loaded('pdo_sqlsrv') && env('DB_FORCE_ODBC') !== '1') {
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
            // NAO usar PDO::ODBC_ATTR_ASSUME_UTF8 aqui: no Driver 18 o PDO
            // passa a buscar colunas WCHAR como UTF-16LE cru (ASCII vira
            // 'a\0t\0i\0v\0o'), quebrando password_verify e toda comparacao
            // de string no app — logins e checagens de permissao morrem.
            $cache[$banco] = new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), $opcoes);
        }
    }
    return $cache[$banco];
}

/**
 * Literal SQL seguro para o PDO_ODBC: o driver entrega os bytes UTF-8 do
 * statement ao servidor como Latin1 ('Não' vira 'NÃ£o' — mojibake e
 * estouro de coluna char). CHAR()/NCHAR() monta o texto no proprio SQL
 * Server e so trafega ASCII. Funciona igual no pdo_sqlsrv.
 */
function db_sql_literal(string $s): string
{
    $u = iconv('UTF-8', 'UTF-32BE', $s);
    if ($u === false || $u === '') {
        return "''";
    }
    $parts = [];
    foreach (unpack('N*', $u) as $cp) {
        $parts[] = $cp < 256 ? "CHAR($cp)" : "NCHAR($cp)";
    }
    // CONVERT alarga o tipo reportado: sem ele, CHAR()+... tem o tamanho
    // exato do texto e a expansao UTF-8 estoura o buffer do fetch (01004).
    // nvarchar preserva codepoints >255; o destino varchar converte igual
    // ao que o pdo_sqlsrv entregaria.
    return 'CONVERT(nvarchar(4000), ' . implode('+', $parts) . ')';
}

/**
 * Literal de parametro para SQL inline: NULL, numero, bool ou string
 * escapada (' -> ''). Nao-ASCII usa db_sql_literal() (CHAR/NCHAR no
 * servidor) por causa do problema de codepage do PDO_ODBC.
 */
function db_sql_param_literal(mixed $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    $v = (string) $v;
    if (preg_match('/[^\x00-\x7F]/', $v)) {
        return db_sql_literal($v);
    }
    return "'" . str_replace("'", "''", $v) . "'";
}

/**
 * PDO_ODBC nao transcodifica parametros e falha com binding em EXEC
 * (22001/22026/01004). Troca TODO '?' por literal inline
 * ('?' dentro de literal SQL e ignorado). Devolve [sql, []].
 */
function db_odbc_params(string $sql, array $params): array
{
    if (!$params) {
        return [$sql, $params];
    }
    $buf = '';
    $lit = false;
    $i = 0;
    $n = strlen($sql);
    for ($k = 0; $k < $n; $k++) {
        $c = $sql[$k];
        if ($lit) {
            $buf .= $c;
            if ($c === "'") {
                if ($k + 1 < $n && $sql[$k + 1] === "'") {
                    $buf .= $sql[++$k];
                } else {
                    $lit = false;
                }
            }
            continue;
        }
        if ($c === "'") {
            $lit = true;
        } elseif ($c === '?' && $i < count($params)) {
            $buf .= db_sql_param_literal($params[$i++]);
            continue;
        }
        $buf .= $c;
    }
    return [$buf, []];
}

/**
 * prepare+execute respeitando o driver: no PDO_ODBC inlinam-se os
 * parametros (ver db_odbc_params); no sqlsrv segue bind nativo.
 */
function db_stmt(PDO $pdo, string $sql, array $params): PDOStatement
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'odbc') {
        [$sql, $params] = db_odbc_params($sql, $params);
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Executa SELECT e retorna lista de arrays associativos. */
function query(string $sql, array $params = [], ?string $banco = null): array
{
    $pdo = conexao($banco);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'odbc') {
        [$sql, $params] = db_odbc_params($sql, $params);
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Executa INSERT/UPDATE/DELETE. Retorna linhas afetadas. */
function execute(string $sql, array $params = [], ?string $banco = null): int
{
    $pdo = conexao($banco);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'odbc') {
        [$sql, $params] = db_odbc_params($sql, $params);
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
