<?php
declare(strict_types=1);

function rar_banco(string $base): string
{
    if (!modo_teste()) {
        throw new RuntimeException('Registro de AR disponível somente nas bases de treino nesta etapa.');
    }
    if (!in_array($base, ['CONV', 'MGA'], true)) {
        throw new InvalidArgumentException('Selecione a base de origem do registro.');
    }
    return env($base === 'MGA' ? 'DB_ERP_MGA' : 'DB_ERP_CONV');
}

function rar_reunir(string $base, callable $buscar, bool $porData = false): array
{
    $rows = [];
    foreach ($base === 'TODAS' ? ['CONV', 'MGA'] : [$base] as $origem) {
        foreach ($buscar($origem) as $r) {
            $r['base'] = $origem;
            $rows[] = $r;
        }
    }
    usort($rows, $porData
        ? static fn($a, $b) => strcmp((string) $b['AR_Data'], (string) $a['AR_Data'])
            ?: ((int) $b['AR_Numero'] <=> (int) $a['AR_Numero']) ?: strcmp($a['base'], $b['base'])
        : static fn($a, $b) => strcasecmp($a['nome'], $b['nome'])
            ?: strcmp($a['codigo'], $b['codigo']) ?: strcmp($a['base'], $b['base']));
    return array_slice($rows, 0, 200);
}

function rar_opcoes(string $base, string $tipo, string $termo): array
{
    return rar_reunir($base, static fn($origem) => rar_opcoes_base($origem, $tipo, $termo));
}

function rar_opcoes_base(string $base, string $tipo, string $termo): array
{
    $db = rar_banco($base);
    $par = ['%' . $termo . '%', '%' . $termo . '%'];
    if ($tipo === 'centro') {
        return query('SELECT TOP 200 Empresa_Cod + Filial_Cod + Centro_Cod codigo,
            CONVERT(varchar(200), Centro_Nome) nome
            FROM dbo.Centro_de_Custo
            WHERE Empresa_Cod + Filial_Cod + Centro_Cod LIKE ? OR Centro_Nome LIKE ?
            ORDER BY Empresa_Cod, Filial_Cod, Centro_Cod', $par, $db);
    }
    if ($tipo === 'fornecedor') {
        return query('SELECT TOP 200 RTRIM(Cre_Cod) codigo,
            CONVERT(varchar(200), Cre_RazSoc) nome, Cre_CGCCPF documento
            FROM dbo.Credor WHERE Cre_Cod LIKE ? OR Cre_RazSoc LIKE ?
            ORDER BY Cre_RazSoc, Cre_Cod', $par, $db);
    }
    throw new InvalidArgumentException('Consulta inválida.');
}

function rar_buscar(string $base, array $f): array
{
    return rar_reunir($base, static fn($origem) => rar_buscar_base($origem, $f), true);
}

function rar_buscar_base(string $base, array $f): array
{
    $where = ['1 = 1'];
    $params = [];
    foreach (['numero' => 'a.AR_CentroNumero', 'centro' => 'a.Empresa_Cod + a.Filial_Cod + a.Centro_Cod'] as $key => $col) {
        if ($f[$key] !== '') {
            $where[] = "$col = ?";
            $params[] = $f[$key];
        }
    }
    if ($f['fornecedor'] !== '') {
        $where[] = '(c.Cre_RazSoc LIKE ? OR a.Cre_Cod = ?)';
        $params[] = '%' . $f['fornecedor'] . '%';
        $params[] = $f['fornecedor'];
    }
    foreach (['ini' => '>=', 'fim' => '<='] as $key => $op) {
        if ($f[$key] !== '') {
            $where[] = "CAST(a.AR_Data AS date) $op CAST(? AS date)";
            $params[] = $f[$key];
        }
    }
    return query('SELECT TOP 200 a.AR_Numero, a.AR_CentroNumero, a.AR_Data,
        a.Empresa_Cod + a.Filial_Cod + a.Centro_Cod Centro_CodC,
        CONVERT(varchar(200), cc.Centro_Nome) Centro_Nome,
        a.Cre_Cod, CONVERT(varchar(200), c.Cre_RazSoc) Cre_RazSoc
        FROM dbo.Aviso_de_Recebimento a
        LEFT JOIN dbo.Credor c ON c.Cre_Cod = a.Cre_Cod
        LEFT JOIN dbo.Centro_de_Custo cc ON cc.Empresa_Cod = a.Empresa_Cod
            AND cc.Filial_Cod = a.Filial_Cod AND cc.Centro_Cod = a.Centro_Cod
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.AR_Data DESC, a.AR_Numero DESC', $params, rar_banco($base));
}

function rar_consultar(string $base, int $id): ?array
{
    $db = rar_banco($base);
    $cab = query('SELECT a.*, CONVERT(varchar(200), c.Cre_RazSoc) Cre_RazSoc,
        c.Cre_CGCCPF, CONVERT(varchar(200), cc.Centro_Nome) Centro_Nome,
        o.Obra_CNO, o.Obra_CIB
        FROM dbo.Aviso_de_Recebimento a
        LEFT JOIN dbo.Cadastro_de_Obras o ON o.Obra_Numero = a.Obra_Numero
        LEFT JOIN dbo.Credor c ON c.Cre_Cod = a.Cre_Cod
        LEFT JOIN dbo.Centro_de_Custo cc ON cc.Empresa_Cod = a.Empresa_Cod
            AND cc.Filial_Cod = a.Filial_Cod AND cc.Centro_Cod = a.Centro_Cod
        WHERE a.AR_Numero = ?', [$id], $db)[0] ?? null;
    if (!$cab) {
        return null;
    }
    $docs = query('SELECT d.Ent_Numero, d.Ent_Item, d.EntDocTipo_Cod, d.Ent_DocNumero,
        d.Ent_DocNumero_COMPL, d.Ent_DocSerie, d.Ent_DocData, d.Ent_DocUFOrigem,
        d.Ent_DocValor, d.Ent_DataEntrada, d.Entrada_Numero, d.Ficha_Numero,
        d.Ent_DocCTR, d.Nop_Cod, d.Ent_CFOP, d.Sped_CHV_NFE, d.Ent_ValorDivergencia,
        d.Ent_ValorIPI, d.Ent_DescontoProduto, d.Ent_ValorAcrescimos, d.Ent_ValorDescontos,
        d.Ent_ValorINSS, d.Ent_ValorISS, d.Ent_ValorIRRF, d.Ent_ValorPIS,
        d.Ent_ValorCOFINS, d.Ent_ValorCSSL, d.Audit_Insert_UserName, d.Audit_Insert_Date,
        d.Audit_UserName, d.Audit_LastChange, CONVERT(varchar(4000), d.Ent_Observacao) Ent_Observacao
        FROM dbo.Documento_de_Entrada d WHERE d.AR_Numero = ?
        ORDER BY d.Ent_Item, d.Ent_Numero', [$id], $db);
    $itens = query('SELECT i.Ent_Numero, i.EntItem_Numero, i.EntItem_Item,
        i.Insumo_Cod, i.SubInsumo_Cod,
        CONVERT(varchar(300), s.SubInsumo_Especificacao) Insumo_Descricao,
        CONVERT(varchar(300), i.EntItem_Especificacao) EntItem_Especificacao,
        i.EntItem_Unidade, i.EntItem_Quantidade, i.EntItem_QuantidadeRecebida,
        i.EntItem_PrecoUnitario_E, i.EntItem_TotalItem_E, i.EntItem_ValorIPI_E,
        i.EntItem_TotalItemcomIPI_E, i.EntItem_DescontoProduto, i.EntItem_TotalItemProduto,
        i.Pedido_Numero, i.PedItem_Numero, i.Solic_Numero, i.SolicItem_Numero
        FROM dbo.Documento_de_Entrada_Item i
        JOIN dbo.Documento_de_Entrada d ON d.Ent_Numero = i.Ent_Numero
        LEFT JOIN dbo.InsumoSub s ON s.Insumo_Cod = i.Insumo_Cod AND s.SubInsumo_Cod = i.SubInsumo_Cod
        WHERE d.AR_Numero = ? ORDER BY i.Ent_Numero, i.EntItem_Item', [$id], $db);
    $parcelas = query('SELECT t.Ent_Numero, t.TitaPag_ParNumero, t.TitaPag_SParNumero,
        t.TitaPag_Situacao, CONVERT(varchar(200), e.EspTit_Titulo) EspTit_Titulo,
        t.TitaPag_DocNumero, t.TitaPag_DataVencimento, t.TitaPag_Valor, t.TitaPag_CodigoBarras
        FROM dbo.Titulos_a_Pagar t
        JOIN dbo.Documento_de_Entrada d ON d.Ent_Numero = t.Ent_Numero
        LEFT JOIN dbo.Especie_de_Titulos_Apagar e ON e.EspTit_Cod = t.EspTit_Cod
        WHERE d.AR_Numero = ? ORDER BY t.Ent_Numero, t.TitaPag_ParNumero, t.TitaPag_SParNumero', [$id], $db);
    return ['cab' => $cab, 'docs' => $docs, 'itens' => $itens, 'parcelas' => $parcelas];
}
