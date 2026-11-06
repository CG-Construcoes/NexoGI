<?php
/**
 * Coleta de Precos (read-only v1) — reconstroi a tela "Coleta de Precos" do
 * ERP a partir do trace: cabecalho (Coleta_de_Preco), abas Itens da Coleta
 * (Coleta_de_Preco_Itens), Fornecedores (Coleta_de_Preco_Fornecedores) e
 * Precos da Coleta (Coleta_de_Preco_Insumos x _Precos por fornecedor).
 */

function col_bases(): array { return uc_bases(); }

/** Cabecalho da coleta (query 92 do trace). */
function col_cabecalho(string $base, int $n): ?array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return null; }
    $sql = "SELECT Coleta_Numero, Coleta_Data, Coleta_Taxa, Coleta_Concluido,
        Coleta_UsuarioConcluido, Coleta_DataConcluido, Usuario_Instrucao_Edit,
        Instrucao_Data_Edit, CONVERT(varchar(2000), Coleta_Observacao) Coleta_Observacao,
        Audit_Insert_UserName, Audit_Insert_Date, Audit_UserName, Audit_LastChange
        FROM [$db].dbo.Coleta_de_Preco WHERE Coleta_Numero = ?";
    $r = query($sql, [$n]);
    return $r[0] ?? null;
}

/** Aba Itens da Coleta (query 98 simplificada — sem subqueries de recebimento). */
function col_itens(string $base, int $n): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $sql = "SELECT
        ColetaItens.ColetaItem_Item,
        Solicitacao_ID = LTRIM(Convert(char(8),Solicitacao.Solic_CentroNumero)) + '/' + Solicitacaoitens.SolicItem_Item,
        Solicitacaoitens.Solic_Numero,
        Solicitacaoitens.SolicItem_Situacao,
        Insumo_CodC = substring(convert(char(7),Solicitacaoitens.Insumo_Cod+1000000),2,6)
                     + '-' + substring(convert(char(4),Solicitacaoitens.SubInsumo_Cod+1000),2,3),
        Insumo_Especificacao = rtrim((SELECT rtrim(s.SubInsumo_Especificacao) FROM [$db].dbo.InsumoSub s
            WHERE s.Insumo_Cod = SolicitacaoItens.Insumo_Cod AND s.SubInsumo_Cod = SolicitacaoItens.SubInsumo_Cod))
            + ' ' + rtrim(CONVERT(varchar(200), Solicitacaoitens.SolicItem_EspecificacaoCpto)),
        insumo.Unid_Cod,
        Solicitacaoitens.SolicItem_Quantidade,
        TipSol.TipSol_Descricao,
        DepSol.DepSol_Descricao,
        Solicitacaoitens.SolicItem_DataEntrega,
        Centro_CodC = Solicitacao.Empresa_Cod + Solicitacao.Filial_Cod + Solicitacao.Centro_Cod,
        Centro.Centro_Nome
        FROM [$db].dbo.Coleta_de_Preco_Itens ColetaItens,
             [$db].dbo.Solicitacao_de_Compra_Itens Solicitacaoitens,
             [$db].dbo.Insumo insumo,
             [$db].dbo.Solicitacao_de_Compra Solicitacao,
             [$db].dbo.Centro_de_Custo Centro,
             [$db].dbo.Tipo_Solicitacao_Compra TipSol,
             [$db].dbo.Departamento_Solicitacao_Compra DepSol
        WHERE ColetaItens.Solic_Numero = Solicitacaoitens.Solic_Numero
          AND ColetaItens.SolicItem_Numero = Solicitacaoitens.SolicItem_Numero
          AND Solicitacao.Solic_Numero = Solicitacaoitens.Solic_Numero
          AND Solicitacaoitens.Insumo_Cod = insumo.Insumo_Cod
          AND Solicitacao.Empresa_Cod = Centro.Empresa_Cod
          AND Solicitacao.Filial_Cod = Centro.Filial_Cod
          AND Solicitacao.Centro_Cod = Centro.Centro_Cod
          AND Solicitacao.TipSol_Codigo = TipSol.TipSol_Codigo
          AND Solicitacao.DepSol_Codigo = DepSol.DepSol_Codigo
          AND ColetaItens.Coleta_Numero = ?
        ORDER BY ColetaItens.ColetaItem_Numero";
    return query($sql, [$n]);
}

/** Aba Fornecedores da Coleta (query 93). */
function col_fornecedores(string $base, int $n): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $sql = "SELECT cf.ColForn_Numero, cf.ColForn_Item, cf.Cre_Cod,
        Credor.Cre_RazSoc, Credor.Cre_CGCCPF,
        cf.ColForn_PrazoDias, cf.ColForn_PrazoPerc,
        cf.ColForn_PrazoFora, cf.ColForn_PrazoInicio,
        cf.ColForn_CTR, cf.ColForn_FreteTotal,
        cf.ColForn_NumParc, cf.EspTit_Cod,
        CONVERT(varchar(500), cf.ColForn_ObservacaoCondForn) Obs,
        Credor.Cre_Cidade, Credor.UF_Sigla
        FROM [$db].dbo.Coleta_de_Preco_Fornecedores cf
        INNER JOIN [$db].dbo.Credor Credor ON cf.Cre_Cod = Credor.Cre_Cod
        WHERE cf.Coleta_Numero = ?
        ORDER BY cf.ColForn_Item ASC";
    return query($sql, [$n]);
}

/** Aba Precos de um fornecedor (query 153). */
function col_precos(string $base, int $n, int $colForn): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $sql = "SELECT
        Insumo_CodC = substring(convert(char(7),ci.Insumo_Cod+1000000),2,6)
                     + '-' + substring(convert(char(4),ci.SubInsumo_Cod+1000),2,3),
        Insumo_Especificacao = rtrim((SELECT rtrim(s.SubInsumo_Especificacao) FROM [$db].dbo.InsumoSub s
            WHERE s.Insumo_Cod = ci.Insumo_Cod AND s.SubInsumo_Cod = ci.SubInsumo_Cod))
            + ' ' + rtrim(CONVERT(varchar(200), ci.SolicItem_EspecificacaoCpto)),
        insumo.Unid_Cod,
        ci.Coleta_Quantidade,
        cp.ColInsForn_Preco, cp.ColInsForn_PercDesc,
        cp.ColInsForn_PrecoInsumo, cp.ColInsForn_PercIPI,
        cp.ColInsForn_PrecoInsumoComIPI, cp.ColInsForn_PercICMS,
        cp.ColInsForn_PrecoFrete, cp.ColInsForn_PrecoFinal,
        CONVERT(varchar(100), cp.ColInsForn_Marca) Marca,
        CONVERT(varchar(200), cp.ColInsForn_Obs) ObsInsumo
        FROM [$db].dbo.Coleta_de_Preco_Insumos ci
        INNER JOIN [$db].dbo.Insumo insumo ON ci.Insumo_Cod = insumo.Insumo_Cod
        LEFT JOIN [$db].dbo.Coleta_de_Preco_Precos cp
            ON ci.SubInsumo_Cod = cp.SubInsumo_Cod AND ci.Coleta_Numero = cp.Coleta_Numero
            AND ci.Insumo_Cod = cp.Insumo_Cod
            AND ci.SolicItem_EspecificacaoCpto = cp.SolicItem_EspecificacaoCpto
            AND (cp.ColForn_Numero = ? OR cp.ColForn_Numero IS NULL)
        WHERE ci.Coleta_Numero = ?
        ORDER BY Insumo_Especificacao";
    return query($sql, [$colForn, $n]);
}
