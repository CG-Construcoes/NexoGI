<?php
declare(strict_types=1);
/*
 * TELA TRAVADA: Autorizacao de Pedidos (porte fiel de _pedidos_query/pedidos/pedidos_itens do app.py).
 * Nao alterar sem 3 confirmacoes do usuario.
 */

function banco_da_base(string $base): string
{
    return $base === 'MGA' ? env('DB_ERP_MGA', 'dbMGA') : env('DB_ERP_CONV', 'dbConv');
}

/** Consulta de pedidos em uma unica base. */
function pedidos_query(array $filtros, string $banco): array
{
    $where = [];
    $params = [];
    if ($filtros['data_ini'] !== '') {
        $where[] = 'CAST(p.Pedido_Data AS DATE) >= ?';
        $params[] = $filtros['data_ini'];
    }
    if ($filtros['data_fim'] !== '') {
        $where[] = 'CAST(p.Pedido_Data AS DATE) <= ?';
        $params[] = $filtros['data_fim'];
    }
    if ($filtros['pedido'] !== '') {
        $where[] = '(p.Pedido_Numero = ? OR p.Pedido_CentroNumero = ?)';
        array_push($params, $filtros['pedido'], $filtros['pedido']);
    }
    if ($filtros['fornecedor'] !== '') {
        $where[] = '(RTRIM(c.Cre_RazSoc) LIKE ? OR RTRIM(p.Cre_Cod) LIKE ?)';
        array_push($params, "%{$filtros['fornecedor']}%", "%{$filtros['fornecedor']}%");
    }
    if ($filtros['centro'] !== '') {
        $where[] = '(RTRIM(cc.Centro_Nome) LIKE ? OR p.Centro_Cod LIKE ?)';
        array_push($params, "%{$filtros['centro']}%", "%{$filtros['centro']}%");
    }
    if (($filtros['nucleo'] ?? '') !== '') {
        $where[] = "(RTRIM(cc.Nucleo_Cod) + ' ' + RTRIM(COALESCE(n.Nucleo_Nome, '')) LIKE ? OR cc.Nucleo_Cod = ?)";
        array_push($params, "%{$filtros['nucleo']}%", $filtros['nucleo']);
    }
    if (($filtros['insumo'] ?? '') !== '') {
        $where[] = "EXISTS (
            SELECT 1 FROM dbo.Pedido_Item pi_f
            LEFT JOIN dbo.Solicitacao_de_Compra_Itens sci_f
                ON sci_f.Solic_Numero = pi_f.Solic_Numero AND sci_f.SolicItem_Numero = pi_f.SolicItem_Numero
            LEFT JOIN dbo.Insumo i_f ON i_f.Insumo_Cod = sci_f.Insumo_Cod
            LEFT JOIN dbo.InsumoSub si_f
                ON si_f.Insumo_Cod = sci_f.Insumo_Cod AND si_f.SubInsumo_Cod = sci_f.SubInsumo_Cod
            WHERE pi_f.Pedido_Numero = p.Pedido_Numero
              AND (i_f.Insumo_Especificacao LIKE ? OR si_f.SubInsumo_Especificacao LIKE ?
                   OR sci_f.SolicItem_EspecificacaoCpto LIKE ? OR pi_f.PedItem_EspecificacaoCpto LIKE ?)
        )";
        for ($i = 0; $i < 4; $i++) {
            $params[] = "%{$filtros['insumo']}%";
        }
    }
    // autorizado = Pedido.Instrucao_Data preenchida
    if (($filtros['situacao'] ?? '') === 'aut') {
        $where[] = 'p.Instrucao_Data IS NOT NULL';
    } elseif (($filtros['situacao'] ?? '') === 'naut') {
        $where[] = 'p.Instrucao_Data IS NULL';
    }
    $whereSql = $where ? implode(' AND ', $where) : '1=1';

    $sql = "
    SELECT TOP 300
        p.Empresa_Cod,
        p.Filial_Cod,
        p.Centro_Cod,
        p.Pedido_Numero,
        p.Pedido_Data,
        p.Cre_Cod,
        RTRIM(c.Cre_RazSoc) AS Fornecedor,
        RTRIM(cc.Centro_Nome) AS Centro,
        RTRIM(cc.Nucleo_Cod) AS Nucleo_Cod,
        RTRIM(n.Nucleo_Nome) AS Nucleo_Nome,
        p.Coleta_Numero,
        p.Pedido_CentroNumero,
        p.Instrucao_Data,
        RTRIM(p.Usuario_Instrucao) AS Autorizante,
        RTRIM(p.Instrucao_Motivo) AS Motivo_Recusa,
        p.Pedido_PrevDataEnt,
        p.Pedido_Concluido,
        COALESCE(it.Total_Pedido, 0) AS Total_Pedido
    FROM dbo.Pedido p
    LEFT JOIN dbo.Credor c ON c.Cre_Cod = p.Cre_Cod
    LEFT JOIN dbo.Centro_de_Custo cc
        ON cc.Empresa_Cod = p.Empresa_Cod
        AND cc.Filial_Cod = p.Filial_Cod
        AND cc.Centro_Cod = p.Centro_Cod
    LEFT JOIN dbo.Nucleo_de_Negocio n ON n.Nucleo_Cod = cc.Nucleo_Cod
    LEFT JOIN (
        SELECT Pedido_Numero, ROUND(SUM(PedItem_Quantidade * PedItem_PrecoInsumo), 2) AS Total_Pedido
        FROM dbo.Pedido_Item
        GROUP BY Pedido_Numero
    ) it ON it.Pedido_Numero = p.Pedido_Numero
    WHERE $whereSql
    ORDER BY cc.Centro_Nome, p.Pedido_Numero DESC
    ";
    $rows = query($sql, $params, $banco);
    $base = strtolower($banco) === 'dbmga' ? 'MGA' : 'CONV';
    foreach ($rows as &$r) {
        $r['Base'] = $base;
        $r['Pedido_Numero'] = (int) $r['Pedido_Numero'];
    }
    return $rows;
}

/** Dados da tela: grupos por centro, combobox de centros, total e erro (mesma logica do Python). */
function dados_tela_pedidos(array $filtros): array
{
    $erros = [];
    $rowsConv = $rowsMga = [];
    try {
        $rowsConv = pedidos_query($filtros, env('DB_ERP_CONV', 'dbConv'));
    } catch (Throwable $e) {
        $erros[] = 'dbConv: ' . $e->getMessage();
    }
    try {
        $rowsMga = pedidos_query($filtros, env('DB_ERP_MGA', 'dbMGA'));
    } catch (Throwable $e) {
        $erros[] = 'dbMGA: ' . $e->getMessage();
    }
    $rows = array_merge($rowsConv, $rowsMga);

    if ($erros && !$rows) {
        return ['grupos' => [], 'centros' => [], 'fornecedores' => [], 'nucleos' => [],
            'total' => '', 'erro' => implode('; ', $erros)];
    }

    // Comboboxes limitados aos centros/fornecedores/nucleos presentes no resultado do periodo.
    $centros = [];
    $fornecedores = [];
    $nucleos = [];
    foreach ($rows as $r) {
        $cn = trim((string) ($r['Centro'] ?? ''));
        $centros[$cn !== '' ? $cn : 'Centro ' . trim((string) $r['Centro_Cod'])] = true;
        $fn = trim((string) ($r['Fornecedor'] ?? ''));
        if ($fn !== '') {
            $fornecedores[$fn] = true;
        }
        $nc = trim((string) ($r['Nucleo_Cod'] ?? ''));
        if ($nc !== '') {
            $nn = trim((string) ($r['Nucleo_Nome'] ?? ''));
            $nucleos[$nc . ($nn !== '' ? ' ' . $nn : '')] = true;
        }
    }
    $centros = array_keys($centros);
    $fornecedores = array_keys($fornecedores);
    $nucleos = array_keys($nucleos);
    sort($centros, SORT_NATURAL | SORT_FLAG_CASE);
    sort($fornecedores, SORT_NATURAL | SORT_FLAG_CASE);
    sort($nucleos, SORT_NATURAL | SORT_FLAG_CASE);

    usort($rows, fn($a, $b) => [(string) ($a['Centro'] ?? ''), -$a['Pedido_Numero']]
        <=> [(string) ($b['Centro'] ?? ''), -$b['Pedido_Numero']]);

    $grupos = [];
    foreach ($rows as $r) {
        $centro = ($r['Centro'] ?? '') !== '' ? $r['Centro'] : "Centro {$r['Centro_Cod']}";
        $grupos['k' . $centro]['nome'] = $centro;
        $grupos['k' . $centro]['pedidos'][] = $r;
    }

    return ['grupos' => array_values($grupos), 'centros' => $centros, 'fornecedores' => $fornecedores,
        'nucleos' => $nucleos, 'total' => count($rows), 'erro' => null];
}

/** Detalhe completo do pedido (ficha estilo Informakon) para a janela /pedidos/detalhe. */
function pedido_detalhe(string $base, string $pedidoNum): ?array
{
    $banco = banco_da_base($base);

    $cab = query("
    SELECT
        p.Pedido_Numero, p.Pedido_CentroNumero,
        Centro_CodC = p.Empresa_Cod + '.' + p.Filial_Cod + '.' + p.Centro_Cod,
        RTRIM(cc.Centro_Nome) AS Centro_Nome,
        p.Pedido_Data, p.Pedido_DataFinal,
        p.Cre_Cod, RTRIM(c.Cre_RazSoc) AS Fornecedor, c.Cre_CGCCPF AS CNPJ_Forn,
        mf.Municipio_CodigoContribuinte AS CodMun_Forn,
        f.Filial_CGC AS CNPJ_Filial,
        mfi.Municipio_CodigoContribuinte AS CodMun_Filial,
        p.Coleta_Numero, p.ColForn_Numero,
        p.Obra_Numero, RTRIM(ob.Obra_Nome) AS Obra_Nome, RTRIM(ob.Obra_Cno) AS Obra_Cno,
        p.UFPrestacao, RTRIM(p.MunicipioPrestacao) AS MunicipioPrestacao,
        RTRIM(mp.Municipio_Nome) AS MunicipioPrest_Nome,
        RTRIM(p.CodigoLocalPrestacao) AS CodigoLocalPrestacao,
        p.Instrucao_Data, RTRIM(p.Usuario_Instrucao) AS Autorizante,
        p.Pedido_DescPed, p.Pedido_FreteTotal,
        RTRIM(p.Audit_Insert_UserName) AS CriadoPor, p.Audit_Insert_Date AS CriadoEm,
        RTRIM(p.Audit_UserName) AS AlteradoPor, p.Audit_LastChange AS AlteradoEm
    FROM dbo.Pedido p
    LEFT JOIN dbo.Credor c ON c.Cre_Cod = p.Cre_Cod
    LEFT JOIN dbo.Municipios mf ON mf.Municipio_Cod = c.Municipio_Cod
    LEFT JOIN dbo.Centro_de_Custo cc
        ON cc.Empresa_Cod = p.Empresa_Cod AND cc.Filial_Cod = p.Filial_Cod AND cc.Centro_Cod = p.Centro_Cod
    LEFT JOIN dbo.Filial f ON f.Empresa_Cod = p.Empresa_Cod AND f.Filial_Cod = p.Filial_Cod
    LEFT JOIN dbo.Municipios mfi ON mfi.Municipio_Cod = f.Municipio_Cod
    LEFT JOIN dbo.Cadastro_de_Obras ob ON ob.Obra_Numero = p.Obra_Numero
    LEFT JOIN dbo.Municipios mp ON mp.Municipio_Cod = p.MunicipioPrestacao
    WHERE p.Pedido_Numero = ?
    ", [$pedidoNum], $banco);
    if (!$cab) {
        return null;
    }
    $cab = $cab[0];

    $itens = query("
    SELECT
        pi.PedItem_Item,
        Solicitacao_ID = RTRIM(LTRIM(CONVERT(char(8), s.Solic_CentroNumero))) + '/' + RTRIM(sci.SolicItem_Item),
        sci.SolicItem_Situacao,
        Insumo_CodC = SUBSTRING(CONVERT(char(7), sci.Insumo_Cod + 1000000), 2, 6)
            + '-' + SUBSTRING(CONVERT(char(4), sci.SubInsumo_Cod + 1000), 2, 3),
        RTRIM(i.CarIns_Cod) AS CarIns_Cod, RTRIM(i.Unid_Cod) AS Unid_Cod,
        pi.PedItem_Quantidade, pi.PedItem_PrecoDig, pi.PedItem_PercDesc,
        pi.PedItem_PrecoInsumo, pi.PedItem_AliqIPI,
        Total_Item = ROUND(pi.PedItem_Quantidade * pi.PedItem_PrecoInsumo, 2),
        pi.PedItem_DataEntrega, sci.SolicItem_DataEntrega, sci.Orc_Cod,
        SubEsp = (SELECT RTRIM(COALESCE(si.SubInsumo_Especificacao, ''))
                  FROM dbo.InsumoSub si
                  WHERE si.Insumo_Cod = sci.Insumo_Cod AND si.SubInsumo_Cod = sci.SubInsumo_Cod),
        pi.PedItem_AnulaEspecCptoSolic, RTRIM(pi.PedItem_EspecificacaoCpto) AS CptoPed,
        RTRIM(pi.PedItem_Marca) AS Marca, RTRIM(sci.SolicItem_EspecificacaoCpto) AS CptoSolic,
        RTRIM(i.Insumo_Especificacao) AS Insumo_Especificacao
    FROM dbo.Pedido_Item pi
    LEFT JOIN dbo.Solicitacao_de_Compra_Itens sci
        ON sci.Solic_Numero = pi.Solic_Numero AND sci.SolicItem_Numero = pi.SolicItem_Numero
    LEFT JOIN dbo.Solicitacao_de_Compra s ON s.Solic_Numero = pi.Solic_Numero
    LEFT JOIN dbo.Insumo i ON i.Insumo_Cod = sci.Insumo_Cod
    WHERE pi.Pedido_Numero = ?
    ORDER BY pi.PedItem_Numero
    ", [$pedidoNum], $banco);

    // Especificacao exibida segue a regra do Informakon (subinsumo + complemento).
    foreach ($itens as &$it) {
        $cpto = $it['PedItem_AnulaEspecCptoSolic'] === 'S'
            ? trim(($it['CptoPed'] ?? '') . ' ' . ($it['Marca'] ?? ''))
            : trim((string) ($it['CptoSolic'] ?? ''));
        $espec = trim(($it['SubEsp'] ?? '') . ' ' . $cpto);
        $it['Especificacao'] = $espec !== '' ? $espec : trim((string) ($it['Insumo_Especificacao'] ?? ''));
    }
    unset($it);

    return ['cab' => $cab, 'itens' => $itens, 'base' => strtoupper($base)];
}


