<?php
/**
 * Ultimas Compras — relacao de insumos (Insumo x InsumoSub) + historico de
 * compras por insumo (ARs recebidos/parciais + pedidos, conforme trace do ERP).
 */

const UC_LIMITE_INSUMOS = 300;

function uc_bases(): array
{
    return ['MGA' => env('DB_ERP_MGA'), 'CONV' => env('DB_ERP_CONV')];
}

/** Lista sub-insumos de uma base filtrando por codigo ou descricao. */
function uc_insumos(string $base, string $termo, string $ordem, int $limite = UC_LIMITE_INSUMOS): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $termo = trim($termo);
    $where = '';
    $params = [];
    if ($termo !== '') {
        if (preg_match('/^\d+(-?\d+)?$/', $termo)) {
            // codigo: aceita '10815', '010815-000' ou '010815000'
            $t = preg_replace('/\D/', '', $termo);
            $ins = (int) substr($t, 0, max(1, strlen($t) - 3));
            $sub = strlen($t) > 3 ? (int) substr($t, -3) : null;
            if ($sub !== null) {
                $where = 'WHERE s.Insumo_Cod = ? AND s.SubInsumo_Cod = ?';
                $params = [$ins, $sub];
            } else {
                $where = 'WHERE s.Insumo_Cod = ?';
                $params = [$ins];
            }
        } else {
            $where = "WHERE CONVERT(varchar(100), s.SubInsumo_Especificacao) LIKE ? OR
                            CONVERT(varchar(80), i.Insumo_Especificacao) LIKE ?";
            $params = ["%$termo%", "%$termo%"];
        }
    }
    $order = $ordem === 'desc'
        ? 'ORDER BY s.SubInsumo_Especificacao, s.SubInsumo_Cod'
        : 'ORDER BY s.Insumo_Cod, s.SubInsumo_Cod';
    $sql = "SELECT TOP " . max(1, $limite) . "
        s.Insumo_Cod, s.SubInsumo_Cod,
        CONVERT(varchar(100), s.SubInsumo_Especificacao) Especificacao,
        i.Unid_Cod, i.ClaIns_Cod
        FROM [$db].dbo.InsumoSub s
        INNER JOIN [$db].dbo.Insumo i ON i.Insumo_Cod = s.Insumo_Cod
        $where $order";
    $out = [];
    foreach (query($sql, $params) as $r) {
        $out[] = [
            'base' => $base,
            'codigo' => sprintf('%06d-%03d', (int) $r['Insumo_Cod'], (int) $r['SubInsumo_Cod']),
            'insumo' => (int) $r['Insumo_Cod'],
            'sub' => (int) $r['SubInsumo_Cod'],
            'descricao' => trim((string) $r['Especificacao']),
            'unidade' => trim((string) $r['Unid_Cod']),
        ];
    }
    return $out;
}

/** Insumos (grupo) por codigo ou descricao — usado no combo da tela. */
function uc_insumos_grupo(string $base, string $termo, int $limite = 30): array
{
    $lista = $base === 'TODOS' ? array_keys(uc_bases()) : [$base];
    $out = [];
    foreach ($lista as $b) {
        $db = uc_bases()[$b] ?? null;
        if (!$db) { continue; }
        $termo = trim($termo);
        if ($termo !== '' && preg_match('/^\d+$/', $termo)) {
            $where = 'WHERE i.Insumo_Cod = ?';
            $params = [(int) $termo];
        } elseif ($termo !== '') {
            $where = "WHERE CONVERT(varchar(80), i.Insumo_Especificacao) LIKE ?";
            $params = ["%$termo%"];
        } else {
            $where = '';
            $params = [];
        }
        $sql = "SELECT TOP " . max(1, $limite) . " i.Insumo_Cod,
                CONVERT(varchar(80), i.Insumo_Especificacao) Especificacao
                FROM [$db].dbo.Insumo i $where ORDER BY i.Insumo_Cod";
        foreach (query($sql, $params) as $r) {
            $out[] = [
                'base' => $b,
                'insumo' => (int) $r['Insumo_Cod'],
                'codigo' => sprintf('%06d', (int) $r['Insumo_Cod']),
                'descricao' => trim((string) $r['Especificacao']),
            ];
        }
    }
    usort($out, fn($a, $c) => $a['insumo'] <=> $c['insumo']);
    return array_slice($out, 0, $limite);
}

/** Sub-insumos COMPRADOS de um insumo (existem em entradas ou solicitacoes). */
function uc_itens_comprados(string $base, int $insumo): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $sql = "SELECT s.Insumo_Cod, s.SubInsumo_Cod,
        CONVERT(varchar(100), s.SubInsumo_Especificacao) Especificacao,
        i.Unid_Cod
        FROM [$db].dbo.InsumoSub s
        INNER JOIN [$db].dbo.Insumo i ON i.Insumo_Cod = s.Insumo_Cod
        WHERE s.Insumo_Cod = ?
          AND (EXISTS (SELECT 1 FROM [$db].dbo.Documento_de_Entrada_Item d
                       WHERE d.Insumo_Cod = s.Insumo_Cod AND d.SubInsumo_Cod = s.SubInsumo_Cod)
            OR EXISTS (SELECT 1 FROM [$db].dbo.Solicitacao_de_Compra_Itens sc
                       WHERE sc.Insumo_Cod = s.Insumo_Cod AND sc.SubInsumo_Cod = s.SubInsumo_Cod))
        ORDER BY s.SubInsumo_Cod";
    $out = [];
    foreach (query($sql, [$insumo]) as $r) {
        $out[] = [
            'base' => $base,
            'insumo' => (int) $r['Insumo_Cod'],
            'sub' => (int) $r['SubInsumo_Cod'],
            'codigo' => sprintf('%06d-%03d', (int) $r['Insumo_Cod'], (int) $r['SubInsumo_Cod']),
            'descricao' => trim((string) $r['Especificacao']),
            'unidade' => trim((string) $r['Unid_Cod']),
        ];
    }
    return $out;
}

/** Ultimas compras (AR total/parcial) de um sub-insumo numa base — replica o batch do ERP. */
function uc_ultimas(string $base, int $insumo, ?int $sub, int $meses = 12): array
{
    $db = uc_bases()[$base] ?? null;
    if (!$db) { return []; }
    $dataIni = $meses > 0
        ? date('Y-m-d', strtotime("-{$meses} months"))
        : '1900-01-01';
    $sql = "USE [$db] " . SQL_UC_ULTIMAS;
    $pdo = conexao(null);
    $st = $pdo->prepare($sql);
    $st->execute([$insumo, $sub, $dataIni]);
    $linhas = [];
    do {
        if ($st->columnCount() > 0) {
            $rs = $st->fetchAll();
            if ($rs && isset($rs[0]['Tipo'])) { $linhas = $rs; }
        }
    } while ($st->nextRowset());
    $st->closeCursor();
    return $linhas;
}

/** Batch extraido do trace do ERP: #DocItem (ARs) + UNION pedidos pendentes. */
const SQL_UC_ULTIMAS = <<<'SQL'
DECLARE   @Insumo_Cod INT,   @SubInsumo_Cod INT,   @Empresa_Cod CHAR(3),   @Filial_Cod CHAR(2),   @Proj_Cod INT,   @TipoAR CHAR(1) = 'N',   @TipoARParcial CHAR(1) = 'N',   @TipoPedidoAut CHAR(1) = 'N',   @TipoPedidoNaoAut CHAR(1) = 'N',   @DataIni DATETIME
SET @Insumo_Cod = ?
SET @SubInsumo_Cod = ?
SET @DataIni = ?
SET @TipoAR = 'S'
SET @TipoARParcial = 'S'
IF OBJECT_ID('tempdb..#DocItem') IS NOT NULL   DROP TABLE #DocItem
CREATE TABLE #DocItem(   Ent_Numero INT,   EntItem_Numero INT,   Insumo_Cod INT,   SubInsumo_Cod INT,   Solic_Numero INT,   SolicItem_Numero INT,   Pedido_Numero INT,   PedItem_Numero INT,   EntItem_Item CHAR(3),   EntItem_Quantidade NUMERIC(14,4),   EntItem_QuantidadeRecebida NUMERIC(14,4),   EntItem_TotalItem NUMERIC(12,2),   EntItem_AliqIPI NUMERIC(5,2),   EntItem_ValorIPI NUMERIC(12,2),   EntItem_TotalItemComIPI NUMERIC(12,2),   EntItem_TotalItemComIPI_E NUMERIC(12,2),   EntItem_TotalItemRp NUMERIC(12,2),   EntItem_ValorAcrescimos_Rat NUMERIC(12,2),   EntItem_ValorDescontos_Rat NUMERIC(12,2),   EntItem_Frete_Rat NUMERIC(12,2),   EntItem_Especificacao CHAR(100),   EntItem_MedDesconto_Rat NUMERIC(12,2),   EntItem_Unidade CHAR(5),   EntItem_MaterialFornecido_Rat NUMERIC(12,2),   EntItem_TotalItem_Final NUMERIC(12,2),   EntItem_MedRetencao_Rat NUMERIC(12,2),   EntItem_MedReajuste_Rat NUMERIC(12,2),   Empresa_Cod CHAR(3),   Filial_Cod CHAR(2),   Centro_Cod CHAR(4),   Cre_Cod CHAR(6),   Ent_DataEntrada DATETIME,   Ent_DocData DATETIME,   AR_Numero INT,   Ent_MedicaoDesconto NUMERIC(12,2),   Ent_DocValor NUMERIC(12,2),   AR_Parcial CHAR(1)   )
IF (@TipoAR = 'S' OR @TipoARParcial = 'S') BEGIN
  INSERT INTO #DocItem
  SELECT Item.Ent_Numero, EntItem_Numero, Insumo_Cod, SubInsumo_Cod, Item.Solic_Numero, Item.SolicItem_Numero, Item.Pedido_Numero, Item.PedItem_Numero, EntItem_Item, EntItem_Quantidade, EntItem_QuantidadeRecebida, EntItem_TotalItem, EntItem_AliqIPI, EntItem_ValorIPI, EntItem_TotalItemComIPI, EntItem_TotalItemComIPI_E, EntItem_TotalItemRp, EntItem_ValorAcrescimos_Rat, EntItem_ValorDescontos_Rat, EntItem_Frete_Rat, EntItem_Especificacao, EntItem_MedDesconto_Rat, EntItem_Unidade, EntItem_MaterialFornecido_Rat, EntItem_TotalItem_Final, EntItem_MedRetencao_Rat, EntItem_MedReajuste_Rat, Ent.Empresa_Cod, Ent.Filial_Cod, Ent.Centro_Cod, Ent.Cre_Cod, Ent.Ent_DataEntrada, Ent.Ent_DocData, Ent.AR_Numero, Ent.Ent_MedicaoDesconto, Ent.Ent_DocValor,
    AR_Parcial = CASE WHEN PedItem.PedItem_Quantidade - (SELECT SUM(EntItem.EntItem_QuantidadeRecebida) FROM Documento_de_Entrada_Item EntItem WHERE EntItem.Pedido_Numero = Item.Pedido_Numero AND EntItem.PedItem_Numero = Item.PedItem_Numero) + ISNULL((SELECT SUM(Dev.DevItem_Quantidade) FROM Documento_de_Devolucao_Item Dev WHERE Item.Ent_Numero = Dev.Ent_Numero AND Item.EntItem_Numero = Dev.EntItem_Numero), 0) > 0 THEN 'S' ELSE 'N' END
  FROM Documento_de_Entrada_Item Item
  INNER JOIN Documento_de_Entrada Ent ON (Ent.Ent_Numero = Item.Ent_Numero)
  INNER JOIN Pedido_Item PedItem ON (PedItem.Pedido_Numero = Item.Pedido_Numero AND PedItem.PedItem_Numero = Item.PedItem_Numero)
  WHERE Insumo_Cod = @Insumo_Cod   AND (@SubInsumo_Cod IS NULL OR SubInsumo_Cod = @SubInsumo_Cod)   AND Ent.Ent_Tipo = 'SM'   AND (@Empresa_Cod IS NULL OR Empresa_Cod = @Empresa_Cod)   AND (@Filial_Cod IS NULL OR Filial_Cod = @Filial_Cod)
END
SELECT TOP 200 R.* FROM (
  SELECT   Tipo = CASE WHEN AR_Parcial = 'S' THEN 'AP' ELSE 'AR' END,   DocItem.Ent_DocData,   Docitem.EntItem_Numero,   Docitem.Ent_Numero,   Ar.AR_Numero,   Ar.AR_Data,   Ar.AR_CentroNumero,   Ar.Ent_Tipo,    Docitem.Insumo_Cod,   Docitem.SubInsumo_Cod,
    Insumo_CodC = substring(convert(char(7),Docitem.Insumo_Cod+1000000),2,6)+substring(convert(char(4),Docitem.SubInsumo_Cod+1000),2,3),
    Docitem.EntItem_Item,   DocItem.EntItem_Quantidade,   DocItem.EntItem_QuantidadeRecebida,   DocItem.EntItem_TotalItem,   DocItem.EntItem_AliqIPI,   DocItem.EntItem_ValorIPI,   DocItem.EntItem_TotalItemComIPI,   DocItem.EntItem_TotalItemComIPI_E,   DocItem.EntItem_TotalItemRp,   DocItem.EntItem_ValorAcrescimos_Rat,   DocItem.EntItem_ValorDescontos_Rat,   DocItem.EntItem_Frete_Rat,    CONVERT(varchar(100), DocItem.EntItem_Especificacao) EntItem_Especificacao,   DocItem.EntItem_MedDesconto_Rat,    DocItem.EntItem_Unidade,   DocItem.Empresa_Cod,   DocItem.Filial_Cod,   DocItem.Centro_Cod,   Centro_CodC = DocItem.Empresa_Cod + DocItem.Filial_Cod + DocItem.Centro_Cod,   Centro.Centro_Nome,   DocItem.Cre_Cod,   Credor.Cre_RazSoc,   DocItem.Ent_DataEntrada,    DocItem.EntItem_MaterialFornecido_Rat,   DocItem.EntItem_MedReajuste_Rat,    SolicitacaoItens.SolicItem_EspecificacaoCpto,   DocItem.EntItem_TotalItem_Final,   DocItem.Pedido_Numero,   DocItem.Ent_MedicaoDesconto,   DocItem.Ent_DocValor,   DocItem.EntItem_MedRetencao_Rat,
    PCP_MedicaoServico = (SELECT CExec.PCP_MedicaoServico FROM Contrato_Execucao CExec WHERE CExec.Pedido_Numero = DocItem.Pedido_Numero),    DocItem.AR_Parcial
  FROM #DocItem DocItem
  INNER JOIN dbo.Centro_de_Custo Centro   ON (DocItem.Filial_Cod = Centro.Filial_Cod     AND DocItem.Empresa_Cod = Centro.Empresa_Cod     AND DocItem.Centro_Cod = Centro.Centro_Cod)
  INNER JOIN Solicitacao_de_Compra_Itens SolicitacaoItens   ON (Docitem.SolicItem_Numero = SolicitacaoItens.SolicItem_Numero     AND Docitem.Solic_Numero = SolicitacaoItens.Solic_Numero)
  INNER JOIN Pedido_Item PedItem   ON (PedItem.Pedido_Numero = DocItem.Pedido_Numero     AND PedItem.PedItem_Numero = DocItem.PedItem_Numero)
  INNER JOIN Credor Credor   ON (DocItem.Cre_Cod = Credor.Cre_Cod)
  INNER JOIN Aviso_de_Recebimento Ar   ON (Ar.AR_Numero = DocItem.AR_Numero)
  LEFT JOIN Projeto_CentrodeCusto ProCenCus   ON (DocItem.Filial_Cod = ProCenCus.Filial_Cod     AND DocItem.Empresa_Cod = ProCenCus.Empresa_Cod     AND DocItem.Centro_Cod = ProCenCus.Centro_Cod)
  LEFT JOIN Projeto Proj   ON (Proj.Proj_Cod = ProCenCus.Proj_Cod)
  WHERE ((@TipoAR = 'S' AND DocItem.AR_Parcial = 'N') OR (@TipoARParcial = 'S' AND DocItem.AR_Parcial = 'S'))
    AND (@Proj_Cod IS NULL OR ProCenCus.Proj_Cod = @Proj_Cod)
  UNION ALL
  SELECT   Tipo = CASE WHEN ISNULL(Pedido.Instrucao_Cod, '') = 'A' THEN 'PA' ELSE 'PN' END,   Ent_DocData = Pedido.Pedido_Data,   EntItem_Numero = PedItem.PedItem_Numero,   Ent_Numero = Pedido.Pedido_Numero,   AR_Numero = Pedido.Pedido_Numero,   AR_Data = Pedido.Pedido_Data,   AR_CentroNumero = Pedido.Pedido_CentroNumero,   Ent_Tipo = '',    SolicitacaoItens.Insumo_Cod,   SolicitacaoItens.SubInsumo_Cod,
    Insumo_CodC = substring(convert(char(7),SolicitacaoItens.Insumo_Cod+1000000),2,6)+substring(convert(char(4),SolicitacaoItens.SubInsumo_Cod+1000),2,3),
    EntItem_Item = PedItem.PedItem_Item,   EntItem_Quantidade = PedItem.PedItem_Quantidade,   EntItem_QuantidadeRecebida = PedItem.PedItem_Quantidade,   EntItem_TotalItem = CAST(PedItem.PedItem_PrecoInsumo * PedItem.PedItem_Quantidade AS NUMERIC(12,2)),   EntItem_AliqIPI = 0,   EntItem_ValorIPI = 0,   EntItem_TotalItemComIPI = CAST(PedItem.PedItem_PrecoInsumo * PedItem.PedItem_Quantidade AS NUMERIC(12,2)),   EntItem_TotalItemComIPI_E = CAST(PedItem.PedItem_PrecoInsumo * PedItem.PedItem_Quantidade AS NUMERIC(12,2)),   EntItem_TotalItemRp = 0,   EntItem_ValorAcrescimos_Rat = 0,   EntItem_ValorDescontos_Rat = 0,   EntItem_Frete_Rat = 0,
    EntItem_Especificacao = CONVERT(varchar(100), RTRIM(InsumoSub.SubInsumo_Especificacao) + ISNULL(' ' + PedItem.PedItem_EspecificacaoCpto, '')),
    EntItem_MedDesconto_Rat = 0,    Insumo.Unid_Cod,   Pedido.Empresa_Cod,   Pedido.Filial_Cod,   Pedido.Centro_Cod,   Centro_CodC = Pedido.Empresa_Cod + Pedido.Filial_Cod + Pedido.Centro_Cod,   Centro.Centro_Nome,   Pedido.Cre_Cod,   Credor.Cre_RazSoc,   Ent_DataEntrada = Pedido.Pedido_Data,    EntItem_MaterialFornecido_Rat = 0,   EntItem_MedReajuste_Rat = 0,    SolicitacaoItens.SolicItem_EspecificacaoCpto,   EntItem_TotalItem_Final = CAST(PedItem.PedItem_PrecoInsumo * PedItem.PedItem_Quantidade AS NUMERIC(12,2)),   Pedido.Pedido_Numero,   Ent_MedicaoDesconto = 0,   Ent_DocValor = CAST(PedItem.PedItem_PrecoInsumo * PedItem.PedItem_Quantidade AS NUMERIC(12,2)),   EntItem_MedRetencao_Rat = 0,
    PCP_MedicaoServico = (SELECT CExec.PCP_MedicaoServico FROM Contrato_Execucao CExec WHERE CExec.Pedido_Numero = Pedido.Pedido_Numero),    AR_Parcial = 'N'
  FROM Pedido
  INNER JOIN Pedido_Item PedItem ON (Pedido.Pedido_Numero = PedItem.Pedido_Numero)
  INNER JOIN dbo.Centro_de_Custo Centro   ON (Pedido.Filial_Cod = Centro.Filial_Cod     AND Pedido.Empresa_Cod = Centro.Empresa_Cod     AND Pedido.Centro_Cod = Centro.Centro_Cod)
  INNER JOIN Solicitacao_de_Compra_Itens SolicitacaoItens   ON (PedItem.SolicItem_Numero = SolicitacaoItens.SolicItem_Numero     AND PedItem.Solic_Numero = SolicitacaoItens.Solic_Numero)
  INNER JOIN InsumoSub   ON (InsumoSub.Insumo_Cod = SolicitacaoItens.Insumo_Cod     AND InsumoSub.SubInsumo_Cod = SolicitacaoItens.SubInsumo_Cod)
  INNER JOIN Insumo ON (Insumo.Insumo_Cod = SolicitacaoItens.Insumo_Cod)
  INNER JOIN Credor Credor   ON (Pedido.Cre_Cod = Credor.Cre_Cod)
  LEFT JOIN Projeto_CentrodeCusto ProCenCus   ON (Pedido.Filial_Cod = ProCenCus.Filial_Cod     AND Pedido.Empresa_Cod = ProCenCus.Empresa_Cod     AND Pedido.Centro_Cod = ProCenCus.Centro_Cod)
  LEFT JOIN Projeto Proj   ON (Proj.Proj_Cod = ProCenCus.Proj_Cod)
  WHERE ((ISNULL(@TipoPedidoAut, '') = 'S' AND ISNULL(Pedido.Instrucao_Cod, '') = 'A')   OR (ISNULL(@TipoPedidoNaoAut, '') = 'S' AND ISNULL(Pedido.Instrucao_Cod, '') <> 'A'))
    AND (@Proj_Cod IS NULL OR ProCenCus.Proj_Cod = @Proj_Cod)
    AND SolicitacaoItens.Insumo_Cod = @Insumo_Cod
    AND (@SubInsumo_Cod IS NULL OR SolicitacaoItens.SubInsumo_Cod = @SubInsumo_Cod)
    AND (@Empresa_Cod IS NULL OR Pedido.Empresa_Cod = @Empresa_Cod)
    AND (@Filial_Cod IS NULL OR Pedido.Filial_Cod = @Filial_Cod)
    AND NOT EXISTS (SELECT * FROM Documento_de_Entrada_Item DocItem WHERE DocItem.Pedido_Numero = PedItem.Pedido_Numero AND DocItem.PedItem_Numero = PedItem.PedItem_Numero)
) R WHERE R.Ent_DocData >= @DataIni ORDER BY 2 DESC, 1, 3
SQL;
