<?php
declare(strict_types=1);

/*
 * Posicao de Contas (Financeiro > Caixas e Bancos) — replica o relatorio
 * "Posicao Agente Financeiro" do ERP (Informakon), conforme trace
 * traces/rel_Posicao_Contas.trc e o PDF Financeiro-RelatorioPosicaoContas.pdf.
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

/** Agentes que possuem conta no tipo/empresa informados (combo "Um Agente"). */
function posicao_contas_agentes(string $banco, string $tipo, string $empresa): array
{
    try {
        return query(
            "SELECT DISTINCT a.AgenteFinanceiro_Cod, a.AgenteFinanceiro_RazaoSocial
             FROM dbo.AgenteFinanceiro a
             WHERE a.AgenteFinanceiroTipo_Cod = ?
               AND EXISTS (SELECT 1 FROM dbo.AgenteFinanceiroConta c
                           WHERE c.AgenteFinanceiroTipo_Cod = a.AgenteFinanceiroTipo_Cod
                             AND c.AgenteFinanceiro_Cod = a.AgenteFinanceiro_Cod
                             AND c.Empresa_Cod = ?)
             ORDER BY a.AgenteFinanceiro_RazaoSocial",
            [$tipo, $empresa], $banco);
    } catch (Throwable $t) {
        return [];
    }
}

/** Chamada da proc de saldo. $con=true -> conciliado; false -> razao. */
function pc_saldo_dia(PDO $pdo, string $proc, string $tipo, string $ag, string $conta,
                    string $data, ?string $empresa, ?string $nucleo): array
{
    $st = $pdo->prepare(
        "DECLARE @s numeric(12,2), @d datetime;
         EXEC dbo.$proc ?,?,?,?,?,?, @s OUTPUT, @d OUTPUT;
         SELECT @s AS saldo, CONVERT(varchar(23), @d, 121) AS dt");
    $st->execute([$tipo, $ag, $conta, $data, $empresa, $nucleo]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $st->closeCursor();
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

    $empresa = trim((string) ($o['empresa'] ?? ''));
    $tipo    = trim((string) ($o['tipo'] ?? ''));
    $data    = (string) ($o['data'] ?? '');
    if ($empresa === '' || $tipo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        throw new InvalidArgumentException('Informe empresa, tipo de agente e data.');
    }
    $nucleo = trim((string) ($o['nucleo'] ?? '')) ?: null;
    $agente = trim((string) ($o['agente'] ?? '')) ?: null;
    $con    = !empty($o['conciliados']);
    $proc   = $con ? 'spGetSaldoDiaAnteriorCon' : 'spGetSaldoDiaAnterior';
    $diaSeguinte = date('Y-m-d', strtotime("$data +1 day"));

    $contas = query(
        "SELECT Conta.AgenteFinanceiroTipo_Cod, Conta.AgenteFinanceiro_Cod,
                Conta.AgeFin_ContaNumero, Conta.AgeFin_LimitedeCredito,
                Conta.AgeFinAgencia_Cod, Agente.AgenteFinanceiro_RazaoSocial,
                Emp.Empresa_Nome
         FROM dbo.AgenteFinanceiroConta Conta
         INNER JOIN dbo.AgenteFinanceiro Agente
             ON Conta.AgenteFinanceiroTipo_Cod = Agente.AgenteFinanceiroTipo_Cod
            AND Conta.AgenteFinanceiro_Cod = Agente.AgenteFinanceiro_Cod
         INNER JOIN dbo.Empresa Emp ON Emp.Empresa_Cod = Conta.Empresa_Cod
         WHERE Conta.AgenteFinanceiroTipo_Cod = ?
           AND (Conta.AgenteFinanceiro_Cod = ? OR ? IS NULL)
           AND Conta.Empresa_Cod = ?
           AND (Conta.Nucleo_Cod = ? OR ? IS NULL)
           AND (Conta.AgeFin_SuspendeImpressao <> 'S' OR Conta.AgeFin_SuspendeImpressao IS NULL)
           AND Conta.AgeFin_ImpDataSaldoRazao <= ?
         ORDER BY Conta.Empresa_Cod, Conta.AgenteFinanceiroTipo_Cod,
                  Conta.AgenteFinanceiro_Cod, Conta.AgeFinAgencia_Cod, Conta.AgeFin_ContaNumero",
        [$tipo, $agente, $agente, $empresa, $nucleo, $nucleo, $data], $banco);

    $grupos = [];
    $totEmp = ['ant' => 0.0, 'deb' => 0.0, 'cred' => 0.0, 'atual' => 0.0,
               'limite' => 0.0, 'aplicado' => 0.0, 'disponivel' => 0.0];
    $nomeEmpresa = '';
    foreach ($contas as $c) {
        $ag = trim((string) $c['AgenteFinanceiro_Cod']);
        $ct = trim((string) $c['AgeFin_ContaNumero']);
        $ant   = pc_saldo_dia($pdo, $proc, $tipo, $ag, $ct, $data, $empresa, $nucleo);
        $atual = pc_saldo_dia($pdo, $proc, $tipo, $ag, $ct, $diaSeguinte, $empresa, $nucleo);
        $mov   = pc_mov_dia($pdo, $con, $tipo, $ag, $ct, $data, $empresa, $nucleo);
        $aplic = pc_valor_aplicado($pdo, $tipo, $ag, $ct, $data, $empresa, $nucleo);
        $ultima = pc_ultima_data($pdo, $tipo, $ag, $ct);
        $limite = (float) $c['AgeFin_LimitedeCredito'];
        $disp = $atual['saldo'] + $limite + $aplic;

        $nomeEmpresa = trim((string) $c['Empresa_Nome']);
        $nomeAgente = trim((string) $c['AgenteFinanceiro_RazaoSocial']);
        if (!isset($grupos[$ag])) {
            $grupos[$ag] = ['tipo' => $tipo, 'agente' => $ag, 'nome' => $nomeAgente,
                'contas' => [], 'tot' => ['ant' => 0.0, 'deb' => 0.0, 'cred' => 0.0,
                'atual' => 0.0, 'limite' => 0.0, 'aplicado' => 0.0, 'disponivel' => 0.0]];
        }
        $grupos[$ag]['contas'][] = [
            'conta' => $ct, 'agencia' => trim((string) $c['AgeFinAgencia_Cod']),
            'ant' => $ant['saldo'], 'deb' => $mov['deb'], 'cred' => $mov['cred'],
            'atual' => $atual['saldo'], 'limite' => $limite, 'aplicado' => $aplic,
            'disponivel' => $disp, 'ultima' => $ultima,
        ];
        foreach (['ant', 'deb', 'cred', 'atual', 'limite', 'aplicado', 'disponivel'] as $k) {
            $grupos[$ag]['tot'][$k] += $grupos[$ag]['contas'][array_key_last($grupos[$ag]['contas'])][$k];
            $totEmp[$k] += $grupos[$ag]['contas'][array_key_last($grupos[$ag]['contas'])][$k];
        }
    }

    $nucleoDesc = 'Todos os Núcleos';
    if ($nucleo !== null) {
        foreach (posicao_contas_nucleos($banco) as $n) {
            if (trim((string) $n['Nucleo_Cod']) === $nucleo) {
                $nucleoDesc = trim((string) $n['Nucleo_Cod']) . ' - ' . trim((string) $n['Nucleo_Nome']);
                break;
            }
        }
    }

    return ['base' => $base, 'empresa' => $empresa, 'empresa_nome' => $nomeEmpresa,
            'tipo' => $tipo, 'data' => $data, 'nucleo_desc' => $nucleoDesc,
            'conciliados' => $con, 'grupos' => array_values($grupos), 'tot' => $totEmp];
}
