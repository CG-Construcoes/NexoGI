<?php
declare(strict_types=1);

/** Conexao com o SQL Server via PDO (pdo_sqlsrv ou pdo_odbc). Uma conexao por banco, reaproveitada. */
function conexao(?string $banco = null): PDO
{
    static $cache = [];
    $banco ??= env('DB_NAME', 'CGGestao');
    if (!isset($cache[$banco])) {
        $timeout = (int) env('DB_QUERY_TIMEOUT', '180');
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $timeout,
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
            if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
                $opcoes[PDO::SQLSRV_ATTR_QUERY_TIMEOUT] = $timeout;
            }
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
 * PDO_ODBC nao transcodifica parametros: string com nao-ASCII chegaria
 * mojibake/estouraria a coluna. Troca o '?' correspondente pela expressao
 * db_sql_literal() inline ('?' dentro de literal SQL e ignorado).
 * Devolve [sql, params restantes].
 */
function db_odbc_params(string $sql, array $params): array
{
    $temAcento = false;
    foreach ($params as $v) {
        if (is_string($v) && preg_match('/[^\x00-\x7F]/', $v)) {
            $temAcento = true;
            break;
        }
    }
    if (!$temAcento) {
        return [$sql, $params];
    }
    $buf = '';
    $lit = false;
    $rest = [];
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
            $v = $params[$i++];
            if (is_string($v) && preg_match('/[^\x00-\x7F]/', $v)) {
                $buf .= db_sql_literal($v);
                continue;
            }
            $rest[] = $v;
        }
        $buf .= $c;
    }
    return [$buf, array_merge($rest, array_slice($params, $i))];
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

/**
 * Interrompe rotinas longas se o navegador ja desconectou (F5 / fechar aba),
 * evitando disparar novas execucoes de procedures pesadas em cascata.
 */
function db_abortar_se_desconectado(): void
{
    if (PHP_SAPI !== 'cli' && connection_aborted()) {
        exit;
    }
}
