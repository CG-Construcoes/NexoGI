<?php
declare(strict_types=1);

/*
 * Posicao de Contas (Financeiro > Caixas e Bancos) — replica o relatorio
 * "Posicao Agente Financeiro" do ERP (Informakon), conforme trace
 * traces/rel_Posicao_Contas.trc e o PDF Financeiro-RelatorioPosicaoContas.pdf.
 *
 * Aceita varias empresas (ou todas): o relatorio corre em sequencia numa
 * tabela unica (layout "Relatorio de Multiplas empresas" do Informakon),
 * com "Empresa: COD - Nome" como linha de secao e Total Geral consolidado
 * na ultima linha.
 *
 * Por conta (uma linha por AgenteFinanceiroConta):
 *   Saldo Anterior = spGetSaldoDiaAnteriorCon(tipo,ag,conta,DATA,emp,nuc)
 *                    (saldo conciliado da ultima conciliacao ANTES da data;
 *                     sem movimentos -> AgeFin_ImpSaldoConciliado da conta)
 *   Saldo Atual    = mesma proc com DATA+1 (ultima conciliacao <= data)
 *   Debitos/Creditos = movimentos com MovFin_DataConciliado = DATA
 *                      (Cancelado='N', Conciliado='C')
 *   Valor Aplicado = soma do SaldoAplicado das Aplicacao_Financeira da conta
 *                    (movs de aplicacao tipo 01..16 ate a data)
 *   Ultima Conc    = MAX(MaiorData(MovFin_DataConciliado, MovFin_DataConciliaAte))
 *   Disponivel     = Saldo Atual + Limite + Valor Aplicado
 * Sem "Movimentos Conciliados" (trace rel_Posicao_Contas_NaoConciliados.trc):
 * usa spGetSaldoDiaAnterior (saldo de razao, MovFin_SaldoRazao em
 * Movimento_Financeiro_Saldos, por MovFin_Data) e Debitos/Creditos do dia por
 * MovFin_Data SEM filtro de conciliacao. "Ultima Conc" continua sendo a ultima
 * conciliacao (query nao muda entre os modos).
 */

function posicao_contas_empresas(string $banco): array
{
    try {
        return query(
            "SELECT Empresa_Cod, Empresa_Nome FROM dbo.Empresa ORDER BY Empresa_Cod",
            [], $banco);
    } catch (Throwable $t) {
        return [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
    }
}

function posicao_contas_tipos(string $banco): array
{
    try {
        return query(
            "SELECT AgenteFinanceiroTipo_Cod, AgenteFinanceiroTipo_Titulo
             FROM dbo.AgenteFinanceiroTipo ORDER BY AgenteFinanceiroTipo_Titulo",
            [], $banco);
    } catch (Throwable $t) {
        return [['AgenteFinanceiroTipo_Cod' => 'BC', 'AgenteFinanceiroTipo_Titulo' => 'Bancos']];
    }
}

function posicao_contas_nucleos(string $banco): array
{
    try {
        return query(
            "SELECT Nucleo_Cod, Nucleo_Nome FROM dbo.Nucleo_de_Negocio ORDER BY Nucleo_Nome",
            [], $banco);
    } catch (Throwable $t) {
        return [];
    }
}

/** Agentes que possuem conta no tipo informado (combo "Um Agente"). */
function posicao_contas_agentes(string $banco, string $tipo): array
{
    try {
        return query(
            "SELECT DISTINCT a.AgenteFinanceiro_Cod, a.AgenteFinanceiro_RazaoSocial
             FROM dbo.AgenteFinanceiro a
             WHERE a.AgenteFinanceiroTipo_Cod = ?
               AND EXISTS (SELECT 1 FROM dbo.AgenteFinanceiroConta c
                           WHERE c.AgenteFinanceiroTipo_Cod = a.AgenteFinanceiroTipo_Cod
                             AND c.AgenteFinanceiro_Cod = a.AgenteFinanceiro_Cod)
             ORDER BY a.AgenteFinanceiro_RazaoSocial",
            [$tipo], $banco);
    } catch (Throwable $t) {
        return [];
    }
}

/** Saldo do dia. SELECT puro equivalente as procs spGetSaldoDiaAnterior[Con]
 *  (replica exata da logica) — o batch DECLARE/EXEC/OUTPUT nao funciona no
 *  PDO_ODBC do servidor NexoGI (nao tem pdo_sqlsrv). $proc seleciona o modo. */
function pc_saldo_dia(PDO $pdo, string $proc, string $tipo, string $ag, string $conta,
                    string $data, ?string $empresa, ?string $nucleo): array
{
    $con  = $proc === 'spGetSaldoDiaAnteriorCon';
    $tab  = $con ? 'dbo.Movimento_Financeiro_SaldosCon' : 'dbo.Movimento_Financeiro_Saldos';
    $cs   = $con ? 'MovFin_SaldoConciliado' : 'MovFin_SaldoRazao';
    $cd   = $con ? 'MovFin_DataConciliado' : 'MovFin_Data';
    $fc   = $con ? "AND MF.MovFin_Conciliado = 'C'" : '';
    $fc2  = $con ? "AND MF2.MovFin_Conciliado = 'C'" : '';
    $fENg = $con ? '' : "AND (MF.Empresa_Cod = ? OR ? IS NULL)
                          AND (MF.Nucleo_Cod = ? OR ? IS NULL)";
    $impS = $con ? 'AgeFin_ImpSaldoConciliado' : 'AgeFin_ImpSaldoRazao';
    $impD = $con ? 'AgeFin_ImpDataSaldoConciliado' : 'AgeFin_ImpDataSaldoRazao';

    // gate da proc: EXISTS usa MovFin_Data; so o modo razao filtra empresa/nucleo
    $params = [$tipo, $ag, $conta, $data];
    if (!$con) {
        $params = array_merge($params, [$empresa, $empresa, $nucleo, $nucleo]);
    }
    $st = $pdo->prepare("SELECT 1 x WHERE EXISTS(
        SELECT * FROM dbo.Movimento_Financeiro MF
        WHERE MF.AgenteFinanceiroTipo_Cod = ? AND MF.AgenteFinanceiro_Cod = ?
          AND MF.AgeFin_ContaNumero = ? AND MF.MovFin_Cancelado <> 'S'
          AND MF.MovFin_Data < ? $fc $fENg)");
    $st->execute($params);
    $existe = (bool) $st->fetch(PDO::FETCH_ASSOC);
    $st->closeCursor();

    $r = null;
    if ($existe) {
        $st = $pdo->prepare(
            "SELECT TOP 1 S.$cs AS saldo, CONVERT(varchar(23), MF.$cd, 121) AS dt
             FROM $tab S JOIN dbo.Movimento_Financeiro MF ON MF.MovFin_Numero = S.MovFin_Numero
             WHERE MF.AgenteFinanceiroTipo_Cod = ? AND MF.AgenteFinanceiro_Cod = ?
               AND MF.AgeFin_ContaNumero = ? AND MF.MovFin_Cancelado <> 'S'
               AND MF.$cd < ?
               AND (MF.Empresa_Cod = ? OR ? IS NULL)
               AND (MF.Nucleo_Cod = ? OR ? IS NULL) $fc
               AND MF.$cd = (SELECT MAX(MF2.$cd) FROM dbo.Movimento_Financeiro MF2
                   WHERE MF2.AgenteFinanceiroTipo_Cod = ? AND MF2.AgenteFinanceiro_Cod = ?
                     AND MF2.AgeFin_ContaNumero = ? AND MF2.MovFin_Cancelado <> 'S'
                     AND MF2.$cd < ? $fc2)
             ORDER BY MF.$cd DESC, MF.MovFin_Numero DESC"
        );
        $st->execute([$tipo, $ag, $conta, $data, $empresa, $empresa, $nucleo, $nucleo,
                      $tipo, $ag, $conta, $data]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $st->closeCursor();
    }
    if ($r === null && !$existe) {
        // ELSE da proc: saldo/data de implantacao da conta
        $st = $pdo->prepare(
            "SELECT AGC.$impS AS saldo, CONVERT(varchar(23), AGC.$impD, 121) AS dt
             FROM dbo.AgenteFinanceiroConta AGC
             WHERE AGC.AgenteFinanceiroTipo_Cod = ? AND AGC.AgenteFinanceiro_Cod = ?
               AND AGC.AgeFin_ContaNumero = ?"
        );
        $st->execute([$tipo, $ag, $conta]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $st->closeCursor();
    }
    return ['saldo' => (float) ($r['saldo'] ?? 0), 'data' => $r['dt'] ?? null];
}

/** Debitos/creditos conciliados na data (ou do dia, no modo razao). */
function pc_mov_dia(PDO $pdo, bool $con, string $tipo, string $ag, string $conta,
                    string $data, ?string $empresa, ?string $nucleo): array
{
    $campoData = $con ? 'MovFin.MovFin_DataConciliado' : 'MovFin.MovFin_Data';
    $filtroCon = $con ? "AND MovFin.MovFin_Conciliado = 'C'" : '';
    $st = $pdo->prepare(
        "SELECT SUM(CASE WHEN MovFin.MovFin_DC = 'C' THEN MovFin.MovFin_Valor ELSE 0 END) AS cred,
                SUM(CASE WHEN MovFin.MovFin_DC = 'D' THEN MovFin.MovFin_Valor ELSE 0 END) AS deb
         FROM dbo.Movimento_Financeiro MovFin
         WHERE MovFin.MovFin_Cancelado = 'N' $filtroCon
           AND MovFin.AgenteFinanceiroTipo_Cod = ?
           AND MovFin.AgenteFinanceiro_Cod = ?
           AND MovFin.AgeFin_ContaNumero = ?
           AND $campoData = ?
           AND (MovFin.Empresa_Cod = ? OR ? IS NULL)
           AND (MovFin.Nucleo_Cod = ? OR ? IS NULL)");
    $st->execute([$tipo, $ag, $conta, $data, $empresa, $empresa, $nucleo, $nucleo]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $st->closeCursor();
    return ['cred' => (float) ($r['cred'] ?? 0), 'deb' => (float) ($r['deb'] ?? 0)];
}

/** Ultima conciliacao da conta — identica nos dois modos (trace confirma). */
function pc_ultima_data(PDO $pdo, string $tipo, string $ag, string $conta): ?string
{
    $st = $pdo->prepare(
        "SELECT MAX(dbo.MaiorData(MovFin_DataConciliado, MovFin_DataConciliaAte)) AS d
         FROM dbo.Movimento_Financeiro MovFin
         WHERE MovFin.AgenteFinanceiroTipo_Cod = ? AND MovFin.AgenteFinanceiro_Cod = ?
           AND MovFin.AgeFin_ContaNumero = ? AND MovFin.MovFin_Conciliado = 'C'");
    $st->execute([$tipo, $ag, $conta]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $st->closeCursor();
    return $r['d'] ?? null;
}

/** Soma do SaldoAplicado das aplicacoes da conta ate a data (movs tipo 01..16). */
function pc_valor_aplicado(PDO $pdo, string $tipo, string $ag, string $conta,
                           string $data, ?string $empresa, ?string $nucleo): float
{
    $tipos = "'01','02','03','04','05','06','07','09','12','13','16'";
    $st = $pdo->prepare(
        "SELECT A.Apl_Numero,
            COALESCE((SELECT SUM(B.AplMov_Valor) FROM Aplicacao_Financeira_Movimento B
                WHERE B.MovFinTipo_Cod IN ($tipos) AND B.AplMov_DC = 'D'
                  AND B.Apl_Numero = A.Apl_Numero AND B.AplMov_Data <= ?), 0)
            + COALESCE((SELECT -1*SUM(C.AplMov_Valor) FROM Aplicacao_Financeira_Movimento C
                WHERE C.MovFinTipo_Cod IN ($tipos) AND C.AplMov_DC = 'C'
                  AND C.Apl_Numero = A.Apl_Numero AND C.AplMov_Data <= ?), 0) AS SaldoAplicado
         FROM dbo.Aplicacao_Financeira A
         WHERE A.AgenteFinanceiroTipo_Cod = ? AND A.AgenteFinanceiro_Cod = ?
           AND A.AgeFin_ContaNumero = ?
           AND (A.Empresa_Cod = ? OR ? IS NULL)
           AND (A.Nucleo_Cod = ? OR ? IS NULL)
         GROUP BY A.Apl_Numero");
    $st->execute([$data, $data, $tipo, $ag, $conta, $empresa, $empresa, $nucleo, $nucleo]);
    $total = 0.0;
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $total += (float) $r['SaldoAplicado'];
    }
    $st->closeCursor();
    return $total;
}

function posicao_contas_dados(string $base, array $o): array
{
    $banco = banco_da_base($base);
    $pdo = conexao($banco);

    $empresas = array_values(array_filter(array_map('trim', (array) ($o['empresas'] ?? []))));
    $tipo    = trim((string) ($o['tipo'] ?? ''));
    $data    = (string) ($o['data'] ?? '');
    if ($tipo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new InvalidArgumentException('Informe tipo de agente e data.');
    }
    $nucleo = trim((string) ($o['nucleo'] ?? '')) ?: null;
    $agente = trim((string) ($o['agente'] ?? '')) ?: null;
    $con    = !empty($o['conciliados']);
    $proc   = $con ? 'spGetSaldoDiaAnteriorCon' : 'spGetSaldoDiaAnterior';
    $diaSeguinte = date('Y-m-d', strtotime("$data +1 day"));

    $filtroEmp = '';
    $params = [$tipo, $agente, $agente];
    if ($empresas) {
        $filtroEmp = 'AND Conta.Empresa_Cod IN ('
            . implode(',', array_fill(0, count($empresas), '?')) . ')';
        $params = array_merge($params, $empresas);
    }
    $params[] = $nucleo;
    $params[] = $nucleo;
    $params[] = $data;

    $contas = query(
        "SELECT Conta.AgenteFinanceiroTipo_Cod, Conta.AgenteFinanceiro_Cod,
                Conta.AgeFin_ContaNumero, Conta.AgeFin_LimitedeCredito,
                Conta.AgeFinAgencia_Cod, Conta.Empresa_Cod,
                Agente.AgenteFinanceiro_RazaoSocial, Emp.Empresa_Nome
         FROM dbo.AgenteFinanceiroConta Conta
         INNER JOIN dbo.AgenteFinanceiro Agente
             ON Conta.AgenteFinanceiroTipo_Cod = Agente.AgenteFinanceiroTipo_Cod
            AND Conta.AgenteFinanceiro_Cod = Agente.AgenteFinanceiro_Cod
         INNER JOIN dbo.Empresa Emp ON Emp.Empresa_Cod = Conta.Empresa_Cod
         WHERE Conta.AgenteFinanceiroTipo_Cod = ?
           AND (Conta.AgenteFinanceiro_Cod = ? OR ? IS NULL)
           $filtroEmp
           AND (Conta.Nucleo_Cod = ? OR ? IS NULL)
           AND (Conta.AgeFin_SuspendeImpressao <> 'S' OR Conta.AgeFin_SuspendeImpressao IS NULL)
           AND Conta.AgeFin_ImpDataSaldoRazao <= ?
         ORDER BY Conta.Empresa_Cod, Conta.AgenteFinanceiroTipo_Cod,
                  Conta.AgenteFinanceiro_Cod, Conta.AgeFinAgencia_Cod, Conta.AgeFin_ContaNumero",
        $params, $banco);

    $totZerado = ['ant' => 0.0, 'deb' => 0.0, 'cred' => 0.0, 'atual' => 0.0,
                  'limite' => 0.0, 'aplicado' => 0.0, 'disponivel' => 0.0];
    $blocos = [];
    $totG = $totZerado;
    foreach ($contas as $c) {
        $empCod = trim((string) $c['Empresa_Cod']);
        $ag = trim((string) $c['AgenteFinanceiro_Cod']);
        $ct = trim((string) $c['AgeFin_ContaNumero']);
        $ant   = pc_saldo_dia($pdo, $proc, $tipo, $ag, $ct, $data, $empCod, $nucleo);
        $atual = pc_saldo_dia($pdo, $proc, $tipo, $ag, $ct, $diaSeguinte, $empCod, $nucleo);
        $mov   = pc_mov_dia($pdo, $con, $tipo, $ag, $ct, $data, $empCod, $nucleo);
        $aplic = pc_valor_aplicado($pdo, $tipo, $ag, $ct, $data, $empCod, $nucleo);
        $ultima = pc_ultima_data($pdo, $tipo, $ag, $ct);
        $limite = (float) $c['AgeFin_LimitedeCredito'];

        if (!isset($blocos[$empCod])) {
            $blocos[$empCod] = ['cod' => $empCod,
                'nome' => trim((string) $c['Empresa_Nome']), 'grupos' => [], 'tot' => $totZerado];
        }
        if (!isset($blocos[$empCod]['grupos'][$ag])) {
            $blocos[$empCod]['grupos'][$ag] = ['tipo' => $tipo, 'agente' => $ag,
                'nome' => trim((string) $c['AgenteFinanceiro_RazaoSocial']),
                'contas' => [], 'tot' => $totZerado];
        }
        $linha = [
            'conta' => $ct, 'agencia' => trim((string) $c['AgeFinAgencia_Cod']),
            'ant' => $ant['saldo'], 'deb' => $mov['deb'], 'cred' => $mov['cred'],
            'atual' => $atual['saldo'], 'limite' => $limite, 'aplicado' => $aplic,
            'disponivel' => $atual['saldo'] + $limite + $aplic, 'ultima' => $ultima,
        ];
        $blocos[$empCod]['grupos'][$ag]['contas'][] = $linha;
        foreach (array_keys($totZerado) as $k) {
            $blocos[$empCod]['grupos'][$ag]['tot'][$k] += $linha[$k];
            $blocos[$empCod]['tot'][$k] += $linha[$k];
            $totG[$k] += $linha[$k];
        }
    }
    foreach ($blocos as &$b) {
        $b['grupos'] = array_values($b['grupos']);
    }
    unset($b);

    $nucleoDesc = 'Todos os Núcleos';
    if ($nucleo !== null) {
        foreach (posicao_contas_nucleos($banco) as $n) {
            if (trim((string) $n['Nucleo_Cod']) === $nucleo) {
                $nucleoDesc = trim((string) $n['Nucleo_Cod']) . ' - ' . trim((string) $n['Nucleo_Nome']);
                break;
            }
        }
    }

    return ['base' => $base, 'tipo' => $tipo, 'data' => $data,
            'nucleo_desc' => $nucleoDesc, 'conciliados' => $con,
            'blocos' => array_values($blocos), 'tot' => $totG];
}
