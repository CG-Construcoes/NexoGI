<?php
declare(strict_types=1);
/*
 * Indicadores - Suprimentos.
 * Indice de Pedidos Autorizados solicitados no Prazo: somente pedidos com
 * Pedido.Instrucao_Data preenchida (autorizados); o periodo filtra essa data.
 * Para cada pedido, usa a autorizacao da solicitacao mais antiga
 * (MIN Solicitacao_de_Compra_Itens.Instrucao_Data, via Pedido_Item.Solic_Numero
 * + SolicItem_Numero) e mede DATEDIFF(dia, autorizacao da solicitacao, Pedido_Data).
 * >= 10 dias = "no prazo"; < 10 = fora.
 */

function indicador_prazo_query(string $banco, string $dataIni, string $dataFim): array
{
    $where = ['p.Instrucao_Data IS NOT NULL'];
    $params = [];
    if ($dataIni !== '') {
        $where[] = 'CAST(p.Instrucao_Data AS DATE) >= ?';
        $params[] = $dataIni;
    }
    if ($dataFim !== '') {
        $where[] = 'CAST(p.Instrucao_Data AS DATE) <= ?';
        $params[] = $dataFim;
    }
    $whereSql = implode(' AND ', $where);

    $sql = "
    SELECT
        p.Pedido_Numero,
        p.Pedido_CentroNumero,
        p.Pedido_Data,
        p.Instrucao_Data AS PedAut,
        p.Centro_Cod,
        CONVERT(varchar(4000), cc.Centro_Nome) AS Centro,
        CONVERT(varchar(4000), c.Cre_RazSoc) AS Fornecedor,
        MIN(CAST(sci.Instrucao_Data AS DATE)) AS SolicAut,
        DATEDIFF(DAY, MIN(CAST(sci.Instrucao_Data AS DATE)), CAST(p.Pedido_Data AS DATE)) AS Dias,
        COALESCE(it.Total_Pedido, 0) AS Valor,
        COALESCE(au.QtdAut, 0) AS QtdAut
    FROM dbo.Pedido p
    JOIN dbo.Pedido_Item pi
        ON pi.Pedido_Numero = p.Pedido_Numero
        AND pi.Solic_Numero IS NOT NULL
    JOIN dbo.Solicitacao_de_Compra_Itens sci
        ON sci.Solic_Numero = pi.Solic_Numero
        AND sci.SolicItem_Numero = pi.SolicItem_Numero
    LEFT JOIN dbo.Centro_de_Custo cc
        ON cc.Empresa_Cod = p.Empresa_Cod
        AND cc.Filial_Cod = p.Filial_Cod
        AND cc.Centro_Cod = p.Centro_Cod
    LEFT JOIN dbo.Credor c ON c.Cre_Cod = p.Cre_Cod
    LEFT JOIN (
        SELECT Pedido_Numero, ROUND(SUM(PedItem_Quantidade * PedItem_PrecoInsumo), 2) AS Total_Pedido
        FROM dbo.Pedido_Item
        GROUP BY Pedido_Numero
    ) it ON it.Pedido_Numero = p.Pedido_Numero
    LEFT JOIN (
        SELECT Pedido_Numero, COUNT(*) AS QtdAut
        FROM dbo.Pedido_Autorizacoes
        WHERE Instrucao_Cod = 'A'
        GROUP BY Pedido_Numero
    ) au ON au.Pedido_Numero = p.Pedido_Numero
    WHERE $whereSql
    GROUP BY p.Pedido_Numero, p.Pedido_CentroNumero, p.Pedido_Data, p.Instrucao_Data,
             p.Centro_Cod, cc.Centro_Nome, c.Cre_RazSoc, it.Total_Pedido, au.QtdAut
    ";
    return query($sql, $params, $banco);
}

/** Agrega os pedidos das duas bases por centro de custo e devolve a lista por pedido. */
function dados_indicador_pedidos_prazo(string $dataIni, string $dataFim): array
{
    $erros = [];
    $centros = [];
    $linhas = [];
    foreach ([env('DB_ERP_CONV', 'dbConv'), env('DB_ERP_MGA', 'dbMGA')] as $banco) {
        try {
            $rows = indicador_prazo_query($banco, $dataIni, $dataFim);
        } catch (Throwable $e) {
            $erros[] = $banco . ': ' . $e->getMessage();
            continue;
        }
        $base = strtolower($banco) === 'dbmga' ? 'MGA' : 'CONV';
        foreach ($rows as $r) {
            $nome = trim((string) ($r['Centro'] ?? ''));
            if ($nome === '') {
                $nome = 'Centro ' . trim((string) $r['Centro_Cod']);
            }
            $linhas[] = [
                'centro' => $nome,
                'num'    => (int) ($r['Pedido_CentroNumero'] ?: $r['Pedido_Numero']),
                'base'   => $base,
                'forn'   => trim((string) ($r['Fornecedor'] ?? '')),
                'pedido' => data_br($r['Pedido_Data']),
                'solic'  => data_br($r['SolicAut']),
                'aut'    => data_br($r['PedAut']),
                'dias'   => (int) $r['Dias'],
                'valor'  => (float) $r['Valor'],
                'corr'   => (int) $r['QtdAut'] >= 2,
            ];
            if (!isset($centros[$nome])) {
                $centros[$nome] = ['nome' => $nome, 'total' => 0, 'valor' => 0.0,
                    'fora' => 0, 'vfora' => 0.0, 'prazo' => 0, 'vprazo' => 0.0,
                    'corr' => 0, 'vcorr' => 0.0];
            }
            $centros[$nome]['total']++;
            $centros[$nome]['valor'] += (float) $r['Valor'];
            if ((int) $r['Dias'] >= 10) {
                $centros[$nome]['prazo']++;
                $centros[$nome]['vprazo'] += (float) $r['Valor'];
            } else {
                $centros[$nome]['fora']++;
                $centros[$nome]['vfora'] += (float) $r['Valor'];
            }
            if ((int) $r['QtdAut'] >= 2) {
                $centros[$nome]['corr']++;
                $centros[$nome]['vcorr'] += (float) $r['Valor'];
            }
        }
    }
    uksort($centros, 'strnatcasecmp');
    usort($linhas, fn($a, $b) => [$a['centro'], -$a['num']] <=> [$b['centro'], -$b['num']]);
    return ['centros' => array_values($centros), 'linhas' => $linhas,
        'erro' => $erros ? implode('; ', $erros) : null];
}
