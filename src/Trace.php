<?php
declare(strict_types=1);

namespace Msoft;

/**
 * Captura de trace XE (substitui o SQL Profiler) para a conversao Delphi->PHP.
 * Fluxo: configurar conexao -> escolher sessao ativa (usuario Delphi) ->
 * iniciar -> eventos aparecem -> parar -> copiar texto.
 */
final class Trace
{
    private const SESS = 'msoft_trace';

    /** Tela GET /captura-trace */
    public static function tela(): void
    {
        exigir_admin();
        render('captura_trace', ['titulo' => 'Captura de Trace (XE)']);
    }

    /** POST /captura-trace/acao — JSON: {acao, servidor?, usuario?, senha?, filtro?} */
    public static function acao(): void
    {
        exigir_admin();
        header('Content-Type: application/json');
        try {
            $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $pdo = self::pdo($in);
            $acao = (string) ($in['acao'] ?? '');
            switch ($acao) {
                case 'sessoes':    echo json_encode(['ok' => true, 'sessoes' => self::sessoes($pdo)]); break;
                case 'iniciar':    echo json_encode(['ok' => true] + self::iniciar($pdo, (string) ($in['filtro'] ?? ''))); break;
                case 'parar':      echo json_encode(['ok' => true] + self::parar($pdo)); break;
                case 'eventos':    echo json_encode(['ok' => true, 'eventos' => self::eventos($pdo)]); break;
                case 'limpar':     echo json_encode(['ok' => true] + self::limpar($pdo)); break;
                case 'status':     echo json_encode(['ok' => true, 'status' => self::status($pdo)]); break;
                default:           echo json_encode(['ok' => false, 'erro' => 'acao desconhecida']);
            }
        } catch (\Throwable $t) {
            echo json_encode(['ok' => false, 'erro' => $t->getMessage()]);
        }
    }

    /** PDO da conexao informada no form, ou a padrao do .env. */
    private static function pdo(array $in): \PDO
    {
        $srv = trim((string) ($in['servidor'] ?? '')) ?: env('DB_SERVER');
        $usr = trim((string) ($in['usuario'] ?? '')) ?: env('DB_USER');
        $pwd = (string) ($in['senha'] ?? '');
        if ($pwd === '' && $usr === env('DB_USER')) {
            $pwd = (string) env('DB_PASSWORD');
        }
        return conexao(env('DB_NAME'), $srv, $usr, $pwd);
    }

    /** Sessoes ativas de usuario (pra escolher o usuario Delphi). */
    private static function sessoes(\PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT s.session_id, s.login_name, s.host_name, s.program_name,
                    DB_NAME(s.database_id) AS base
             FROM sys.dm_exec_sessions s
             WHERE s.is_user_process = 1
             ORDER BY s.login_name, s.session_id"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    /** Cria/inicia a XE session filtrando pelo login informado. */
    private static function iniciar(\PDO $pdo, string $filtro): array
    {
        self::drop($pdo);
        $where = $filtro !== ''
            ? "AND (sqlserver.username = N" . self::lit($filtro) . " OR sqlserver.client_hostname = N" . self::lit($filtro) . ")"
            : '';
        $pdo->exec(
            "CREATE EVENT SESSION [" . self::SESS . "] ON SERVER
             ADD EVENT sqlserver.rpc_completed
               (ACTION(sqlserver.client_hostname, sqlserver.username, sqlserver.database_name, sqlserver.sql_text)
                WHERE sqlserver.is_system = 0 $where),
             ADD EVENT sqlserver.sql_batch_completed
               (ACTION(sqlserver.client_hostname, sqlserver.username, sqlserver.database_name)
                WHERE sqlserver.is_system = 0 $where)
             ADD TARGET package0.ring_buffer (SET max_memory = 8192)
             WITH (MAX_DISPATCH_LATENCY = 2 SECONDS, STARTUP_STATE = OFF)"
        );
        $pdo->exec('ALTER EVENT SESSION [' . self::SESS . '] ON SERVER STATE = START');
        return ['status' => 'rodando'];
    }

    private static function parar(\PDO $pdo): array
    {
        $pdo->exec('ALTER EVENT SESSION [' . self::SESS . '] ON SERVER STATE = STOP');
        return ['status' => 'parada'];
    }

    private static function limpar(\PDO $pdo): array
    {
        self::drop($pdo);
        return ['status' => 'inexistente'];
    }

    private static function drop(\PDO $pdo): void
    {
        if (self::status($pdo) !== 'inexistente') {
            try { $pdo->exec('ALTER EVENT SESSION [' . self::SESS . '] ON SERVER STATE = STOP'); } catch (\Throwable $e) {}
            $pdo->exec('DROP EVENT SESSION [' . self::SESS . '] ON SERVER');
        }
    }

    private static function status(\PDO $pdo): string
    {
        $r = $pdo->query("SELECT s.name, ds.value('(@name)[1]','varchar(10)') estado
                          FROM sys.dm_xe_sessions s
                          LEFT JOIN sys.dm_xe_session_sessions ds ON ds.address = s.address
                          WHERE s.name = '" . self::SESS . "'")->fetchAll(\PDO::FETCH_ASSOC);
        if (!$r) { return 'inexistente'; }
        return strtolower((string) ($r[0]['estado'] ?? 'criada')) ?: 'criada';
    }

    /** Le o ring_buffer e devolve os eventos capturados (novo -> antigo). */
    private static function eventos(\PDO $pdo): array
    {
        $xml = $pdo->query(
            "SELECT CONVERT(varchar(max), CONVERT(xml, st.target_data)) x
             FROM sys.dm_xe_sessions s
             JOIN sys.dm_xe_session_targets st ON st.event_session_address = s.address
             WHERE s.name = '" . self::SESS . "' AND st.target_name = 'ring_buffer'"
        )->fetchColumn();
        if (!$xml) { return []; }
        $dom = simplexml_load_string((string) $xml);
        if (!$dom) { return []; }
        $out = [];
        foreach ($dom->event as $ev) {
            $row = ['tipo' => (string) $ev['name'], 'hora' => (string) $ev['timestamp']];
            foreach ($ev->action as $a) {
                $row[(string) $a['name']] = (string) ($a->value ?? $a->text ?? '');
            }
            foreach ($ev->data as $d) {
                $row[(string) $d['name']] = (string) ($d->value ?? $d->text ?? '');
            }
            $txt = $row['batch_text'] ?? ($row['sql_text'] ?? ($row['statement'] ?? ''));
            if ($txt === '') { continue; }
            $out[] = [
                'hora'     => substr((string) $row['hora'], 11, 12),
                'tipo'     => str_replace(['sqlserver.', '_completed'], '', $row['tipo']),
                'usuario'  => $row['username'] ?? '',
                'host'     => $row['client_hostname'] ?? '',
                'base'     => $row['database_name'] ?? '',
                'dur_ms'   => isset($row['duration']) ? round(((int) $row['duration']) / 1000, 1) : '',
                'sql'      => $txt,
            ];
        }
        return array_reverse($out);
    }

    /** Literal SQL seguro (mesma convenção db_sql_literal do db.php). */
    private static function lit(string $s): string
    {
        if (function_exists('db_sql_literal')) { return db_sql_literal($s); }
        return "'" . str_replace("'", "''", $s) . "'";
    }
}
