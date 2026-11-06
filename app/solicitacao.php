<?php
/**
 * Registro de Solicitacao de Compra (read-only v1) — header + itens,
 * conforme queries 7 e 20 do trace "tela solicitacao e telas coleta.trc".
 */

/** Cabecalho da solicitacao (query 7 + joins para rotulos). */
function sol_cabecalho(string $base, int $n): ?array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return null; }
    $sql = "SELECT s.Solic_Numero, s.Solic_Data, s.Solic_Situacao, s.Solic_CentroNumero,
        Centro_CodC = s.Empresa_Cod + s.Filial_Cod + s.Centro_Cod,
        Centro.Centro_Nome,
        s.Solicitante_Cod, sol.Solicitante_Nome,
        s.Solic_Concluido, s.Solic_DataConcluido, s.Solic_UsuarioConcluido,
        s.TipSol_Codigo, t.TipSol_Descricao,
        s.DepSol_Codigo, d.DepSol_Descricao,
        s.Usuario_Instrucao_Edit, s.Instrucao_Data_Edit,
        s.Audit_Insert_UserName, s.Audit_Insert_Date,
        s.Audit_UserName, s.Audit_LastChange
        FROM [$db].dbo.Solicitacao_de_Compra s
        LEFT JOIN [$db].dbo.Centro_de_Custo Centro
            ON Centro.Empresa_Cod = s.Empresa_Cod AND Centro.Filial_Cod = s.Filial_Cod
           AND Centro.Centro_Cod = s.Centro_Cod
        LEFT JOIN [$db].dbo.Solicitante sol ON sol.Solicitante_Cod = s.Solicitante_Cod
        LEFT JOIN [$db].dbo.Tipo_Solicitacao_Compra t ON t.TipSol_Codigo = s.TipSol_Codigo
        LEFT JOIN [$db].dbo.Departamento_Solicitacao_Compra d ON d.DepSol_Codigo = s.DepSol_Codigo
        WHERE s.Solic_Numero = ?";
    $r = query($sql, [$n]);
    return $r[0] ?? null;
}

/** Centros de negocio da base (combo do topo, como na tela Delphi). */
function sol_centros(string $base): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    return query("SELECT Centro_CodC = Empresa_Cod + Filial_Cod + Centro_Cod,
        Centro_Nome FROM [$db].dbo.Centro_de_Custo ORDER BY Centro_CodC");
}

/** Resolve Solic_Numero interno a partir de centro + numero visivel. */
function sol_localizar(string $base, string $centroCodC, string $numero): ?int
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return null; }
    $r = query("SELECT Solic_Numero FROM [$db].dbo.Solicitacao_de_Compra
        WHERE Solic_CentroNumero = ? AND Empresa_Cod + Filial_Cod + Centro_Cod = ?",
        [$numero, $centroCodC]);
    return $r ? (int) $r[0]['Solic_Numero'] : null;
}

/** Itens da solicitacao (query 20 simplificada). */
function sol_itens(string $base, int $n): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $sql = "SELECT si.SolicItem_Numero, si.SolicItem_Item,
        Insumo_CodC = substring(convert(char(7),si.Insumo_Cod+1000000),2,6)
                     + '-' + substring(convert(char(4),si.SubInsumo_Cod+1000),2,3),
        Insumo_Especificacao = (SELECT rtrim(s2.SubInsumo_Especificacao)
            FROM [$db].dbo.InsumoSub s2
            WHERE s2.Insumo_Cod = si.Insumo_Cod AND s2.SubInsumo_Cod = si.SubInsumo_Cod)
            + ' ' + rtrim(CONVERT(varchar(200), si.SolicItem_EspecificacaoCpto)),
        i.Unid_Cod,
        si.SolicItem_Quantidade, si.SolicItem_DataEntrega,
        si.SolicItem_Situacao, si.SolicItem_PrecoUnit,
        CONVERT(varchar(500), si.SolicItem_Obs) SolicItem_Obs,
        si.Usuario_Instrucao, si.Instrucao_Data,
        si.SolicItem_PreAut, si.Instrucao_Cod
        FROM [$db].dbo.Solicitacao_de_Compra_Itens si
        INNER JOIN [$db].dbo.Insumo i ON si.Insumo_Cod = i.Insumo_Cod
        WHERE si.Solic_Numero = ?
        ORDER BY si.SolicItem_Numero";
    return query($sql, [$n]);
}
