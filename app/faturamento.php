<?php
declare(strict_types=1);

/**
 * Financeiro > Prestacao de Servico > Relatorio de Faturamento.
 * Replica o relatorio do Informakon a partir do trace
 * (traces/relatorio de faturamento.trc): mesma query sobre Faturamento
 * + Centro_de_Custo + Cliente, com pares param/param-is-null.
 * $base = 'MGA'|'CONV' (conexao direta na base ERP via banco_da_base()).
 */

function rf_empresas(string $base): array
{
    return query("SELECT RTRIM(Empresa_Cod) Empresa_Cod,
                         CONVERT(varchar(120), RTRIM(Empresa_Nome)) Empresa_Nome
                  FROM Empresa ORDER BY Empresa_Nome", [], banco_da_base($base));
}

/** Clientes que possuem faturamento (combo nao fica gigante). */
function rf_clientes(string $base): array
{
    $map = [];
    foreach (query("SELECT RTRIM(c.Cli_Cod) Cli_Cod,
                           CONVERT(varchar(120), RTRIM(c.Cli_Nome)) Cli_Nome
                    FROM Cliente c
                    WHERE EXISTS (SELECT 1 FROM Faturamento f
                                  WHERE f.Cli_Cod = c.Cli_Cod)
                    ORDER BY c.Cli_Nome", [], banco_da_base($base)) as $r) {
        $map[$r['Cli_Cod']] = $r['Cli_Cod'] . ' - ' . $r['Cli_Nome'];
    }
    return $map;
}

function rf_nucleos(string $base): array
{
    return query("SELECT RTRIM(Nucleo_Cod) Nucleo_Cod,
                         CONVERT(varchar(80), RTRIM(Nucleo_Nome)) Nucleo_Nome
                  FROM Nucleo_de_Negocio ORDER BY Nucleo_Nome",
        [], banco_da_base($base));
}

/**
 * Empreendimentos: EImo_Numero => [rotulo, Centro_CodC].
 * No Informakon, escolher o empreendimento filtra o centro de custo dele.
 */
function rf_empreendimentos(string $base): array
{
    $map = [];
    foreach (query("SELECT EImo_Numero,
                           CONVERT(varchar(120), RTRIM(EImo_Nome)) EImo_Nome,
                           RTRIM(Empresa_Cod) Empresa_Cod,
                           RTRIM(Filial_Cod) Filial_Cod,
                           RTRIM(Centro_Cod) Centro_Cod
                    FROM Empreendimento ORDER BY EImo_Nome",
            [], banco_da_base($base)) as $r) {
        $codc = $r['Empresa_Cod'] . $r['Filial_Cod'] . $r['Centro_Cod'];
        $map[$r['EImo_Numero']] = [
            'rotulo' => $r['EImo_Numero'] . ' - ' . $r['EImo_Nome'],
            'codc'   => $codc,
        ];
    }
    return $map;
}

/** Especies de fatura presentes na base ('P' = previsao, sempre fora). */
function rf_especies(string $base): array
{
    return array_map('trim', array_column(query(
        "SELECT DISTINCT RTRIM(Fat_Esp) e FROM Faturamento
         WHERE Fat_Esp <> 'P' AND Fat_Esp IS NOT NULL AND RTRIM(Fat_Esp) <> ''
         ORDER BY 1", [], banco_da_base($base)), 'e'));
}

/** Tipos de documento presentes na base (NFS-e, NFS, NF-e, NF, REC...). */
function rf_tipos(string $base): array
{
    return array_map('trim', array_column(query(
        "SELECT DISTINCT RTRIM(Fat_Tipo) t FROM Faturamento
         WHERE Fat_Tipo IS NOT NULL AND RTRIM(Fat_Tipo) <> ''
         ORDER BY 1", [], banco_da_base($base)), 't'));
}

/** Nome da empresa + CNPJ da filial matriz (menor Filial_CGC), igual ao cabecalho do PDF. */
function rf_empresa_cabecalho(string $base, string $empresa): array
{
    if ($empresa === '') {
        return ['nome' => '(todas as empresas)', 'cnpj' => ''];
    }
    $nome = '';
    $r = query("SELECT CONVERT(varchar(120), RTRIM(Empresa_Nome)) n
                FROM Empresa WHERE Empresa_Cod = ?", [$empresa], banco_da_base($base));
    $nome = $r ? (string) $r[0]['n'] : $empresa;
    $cnpj = '';
    $f = query("SELECT TOP 1 RTRIM(Filial_CGC) c FROM Filial
                WHERE Empresa_Cod = ? ORDER BY Filial_CGC", [$empresa], banco_da_base($base));
    if ($f) {
        $cnpj = fmt_cnpj($f[0]['c']);
    }
    return ['nome' => $nome, 'cnpj' => $cnpj];
}

/**
 * Executa a query do relatorio (trace). $o chaves:
 * empresa, ini, fim, centro (CodC 9), nucleo, tipo, cliente, reidi (''|'S'|'N'), esp.
 */
function rf_dados(string $base, array $o): array
{
    $par = [];
    $w = "( Faturamento.Fat_Data BETWEEN ? AND ? )";
    $par[] = $o['ini'] . ' 00:00:00';
    $par[] = $o['fim'] . ' 23:59:59';

    $opt = function (string $v) use (&$par) {
        $par[] = $v !== '' ? $v : '';
        $par[] = $v !== '' ? $v : null;
    };

    $w .= " AND ( Faturamento.Empresa_Cod = ? OR ? IS NULL )";
    $opt($o['empresa']);

    // centro: CodC de 9 chars vira par Filial+Centro (empresa ja tem param proprio)
    $fil = $o['centro'] !== '' ? substr($o['centro'], 3, 2) : '';
    $cen = $o['centro'] !== '' ? substr($o['centro'], 5, 4) : '';
    $w .= " AND ( Faturamento.Filial_Cod = ? OR ? IS NULL )";
    $opt($fil);
    $w .= " AND ( Faturamento.Centro_Cod = ? OR ? IS NULL )";
    $opt($cen);

    $w .= " AND ( Centro.Nucleo_Cod = ? OR ? IS NULL )";
    $opt($o['nucleo']);

    $w .= " AND ( RTRIM(Faturamento.Fat_Tipo) = ? OR ? IS NULL )";
    $opt($o['tipo']);

    $w .= " AND ( Faturamento.Cli_Cod = ? OR ? IS NULL )";
    $opt($o['cliente']);

    $w .= " AND ( Coalesce(Faturamento.Sped_REIDI, 'N') = ? OR ? IS NULL )";
    $opt($o['reidi']);

    $w .= " AND ( Faturamento.Fat_Esp <> 'P'
              AND ( Faturamento.Fat_Esp = ? OR ? IS NULL ) )";
    $opt($o['esp']);

    $sql = "SELECT Faturamento.Fat_Numero,
        RTRIM(Faturamento.Fat_Esp) Fat_Esp,
        RTRIM(Faturamento.Fat_Tipo) Fat_Tipo,
        RTRIM(Faturamento.Fat_CentroNumero) Fat_CentroNumero,
        CONVERT(varchar(4000), Faturamento.Fat_Obs) Fat_Obs,
        RTRIM(Faturamento.Empresa_Cod) Empresa_Cod,
        RTRIM(Faturamento.Filial_Cod) Filial_Cod,
        RTRIM(Faturamento.Centro_Cod) Centro_Cod,
        RTRIM(Faturamento.Cli_Cod) Cli_Cod,
        Faturamento.Fat_Data,
        Faturamento.Fat_ValorFatura,
        COALESCE(Faturamento.Fat_ValorReajuste, 0) Fat_ValorReajuste,
        Faturamento.Fat_ValorRp,
        Faturamento.Fat_Retencao,
        Faturamento.Fat_ValorINSS,
        Faturamento.Fat_DescAdi,
        Faturamento.Fat_ValorISS,
        Faturamento.Fat_ValorIRPJ,
        Faturamento.Fat_ValorPIS,
        Faturamento.Fat_ValorCOFINS,
        Faturamento.Fat_ValorCSSL,
        CONVERT(varchar(120), Faturamento.Fat_Descricao) Fat_Descricao,
        RTRIM(Faturamento.Empresa_Cod) + RTRIM(Faturamento.Filial_Cod)
            + RTRIM(Faturamento.Centro_Cod) Centro_CodC,
        CONVERT(varchar(120), Cliente.Cli_Nome) Cli_Nome,
        CONVERT(varchar(120), Centro.Centro_Nome) Centro_Nome,
        RTRIM(Centro.Nucleo_Cod) Nucleo_Cod,
        Coalesce(Faturamento.Sped_REIDI, 'N') Sped_REIDI
        FROM dbo.Faturamento Faturamento
        INNER JOIN dbo.Centro_de_Custo Centro
            ON Faturamento.Empresa_Cod = Centro.Empresa_Cod
           AND Faturamento.Filial_Cod = Centro.Filial_Cod
           AND Faturamento.Centro_Cod = Centro.Centro_Cod
        INNER JOIN dbo.Cliente Cliente ON Faturamento.Cli_Cod = Cliente.Cli_Cod
        WHERE $w";

    return query($sql, $par, banco_da_base($base));
}

/** Colunas numericas do relatorio (ordem do PDF). */
const RF_COLS = ['Fat_ValorFatura', 'Fat_ValorReajuste', 'Fat_ValorINSS',
    'Fat_ValorISS', 'Fat_ValorIRPJ', 'Fat_ValorPIS', 'Fat_ValorCOFINS',
    'Fat_ValorCSSL', 'Fat_Retencao', 'Fat_ValorRp', 'Fat_DescAdi', 'Vr_Liquido'];

/** Vr. Liquido = Fatura + Reajuste - retencoes/descontos (validado no PDF). */
function rf_liquido(array $r): float
{
    return (float) $r['Fat_ValorFatura'] + (float) $r['Fat_ValorReajuste']
        - (float) $r['Fat_ValorINSS'] - (float) $r['Fat_ValorISS']
        - (float) $r['Fat_ValorIRPJ'] - (float) $r['Fat_ValorPIS']
        - (float) $r['Fat_ValorCOFINS'] - (float) $r['Fat_ValorCSSL']
        - (float) $r['Fat_Retencao'] - (float) $r['Fat_ValorRp']
        - (float) $r['Fat_DescAdi'];
}

/** Rotulo do grupo de especie REIDI (chave de ordenacao do trace). */
function rf_rot_reidi(string $reidi): string
{
    return $reidi === 'S' ? 'REIDI' : 'NORMAL';
}

/**
 * Agrupa: [reidi] -> [centro+cliente] -> linhas, igual ao ORDER BY do trace
 * (Sped_REIDI, Empresa, Filial, Centro, Cli_Cod, depois data ou numero).
 */
function rf_agrupar(array $linhas, string $ordem): array
{
    $key = $ordem === 'numero' ? 'Fat_Numero' : 'Fat_Data';
    usort($linhas, function ($a, $b) use ($key) {
        return [rf_rot_reidi($a['Sped_REIDI']), $a['Centro_CodC'], $a['Cli_Cod'], $a[$key]]
            <=> [rf_rot_reidi($b['Sped_REIDI']), $b['Centro_CodC'], $b['Cli_Cod'], $b[$key]];
    });
    $out = [];
    foreach ($linhas as $r) {
        $reidi = rf_rot_reidi($r['Sped_REIDI']);
        $ck = $r['Centro_CodC'] . '|' . $r['Cli_Cod'];
        $out[$reidi]['rotulo'] = $reidi;
        $out[$reidi]['centros'][$ck]['codc'] = $r['Centro_CodC'];
        $out[$reidi]['centros'][$ck]['centro'] = trim((string) $r['Centro_Nome']);
        $out[$reidi]['centros'][$ck]['cliente'] = trim((string) $r['Cli_Nome']);
        $out[$reidi]['centros'][$ck]['linhas'][] = $r;
    }
    foreach ($out as &$g) {
        $g['centros'] = array_values($g['centros']);
    }
    unset($g);
    return array_values($out);
}
