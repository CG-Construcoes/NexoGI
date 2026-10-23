<?php
declare(strict_types=1);
/*
 * Follow-up das Solicitacoes de Compra
 * (Suprimentos > Solicitacoes > Follow-up das Solicitacoes)
 *
 * Replica a tela do Informakon (trace traces/Flw Solicitacoes.trc): grid de
 * itens de solicitacao com o andamento de cada um (coleta, pedido, AR,
 * autorizacao, saldo a receber). A query original tem ~50 colunas, quase
 * todas subselects correlacionados — aqui vao os que alimentam o grid.
 * Como as demais telas: roda nas DUAS bases (dbMGA + dbConv) e une com a
 * marca 'Base' (MGA/CONV).
 *
 * Situacao do item (SolicItem_Situacao): AB=Em Aberto, CO=Em Cotacao,
 * PE=Em Pedido, AR=Em AR. "Em AR Parcial" = AR com saldo a receber > 0 —
 * no trace, quando a caixa nao esta marcada o SQL traz a clausula
 * NOT(Situacao='AR' AND saldo>0).
 * Autorizado = Solicitem.Instrucao_Cod='A' (Instrucao_Data/Usuario_Instrucao
 * carimbo de quem/quando); recusado = Instrucao_Motivo preenchido.
 */

/** Expressao do saldo a receber do item (mesma do trace):
 *  Quantidade - recebido valido + devolvido (NOPs 11202/21202/11204/21204
 *  excluidos da soma de entrada). */
function flw_saldo_expr(): string
{
    return "Solicitem.SolicItem_Quantidade
        - ISNULL((SELECT SUM(Entradaitem.EntItem_QuantidadeRecebida)
                    FROM dbo.Documento_de_Entrada_Item Entradaitem,
                         dbo.Documento_de_Entrada DocEnt
                   WHERE (Solic.Solic_Numero = Entradaitem.Solic_Numero)
                     AND (Solicitem.SolicItem_Numero = Entradaitem.SolicItem_Numero)
                     AND (Entradaitem.Ent_Numero = DocEnt.Ent_Numero)
                     AND (NOT DocEnt.Nop_Cod IN ('11202','21202','11204','21204'))
                     AND (DocEnt.Ent_DataCancelamento IS NULL)), 0)
        + ISNULL((SELECT SUM(DevItem_Quantidade)
                    FROM Documento_de_Entrada_Item Ent,
                         Documento_de_Devolucao_Item Dev,
                         Pedido_Item PedItem,
                         Documento_de_Entrada DocEnt
                   WHERE Ent.Ent_Numero = dev.Ent_Numero
                     AND Ent.EntItem_Numero = dev.EntItem_Numero
                     AND Peditem.Pedido_Numero = ent.Pedido_Numero
                     AND Peditem.PedItem_Numero = ent.PedItem_Numero
                     AND DocEnt.Ent_Numero = Ent.Ent_Numero
                     AND (NOT DocEnt.Nop_Cod IN ('11202','21202','11204','21204'))
                     AND Solicitem.Solic_Numero = PedItem.Solic_Numero
                     AND Solicitem.SolicItem_Numero = PedItem.SolicItem_Numero), 0)";
}

/** Query principal em UMA base (fiel ao trace). $f = filtros normalizados. */
function flw_query(array $f, string $banco): array
{
    $saldo = flw_saldo_expr();
    $where = ['1=1'];
    $par = [];

    $where[] = 'Solic.Solic_Data >= CAST(? AS date)';
    $par[] = $f['data_ini'];
    $where[] = 'Solic.Solic_Data < DATEADD(day, 1, CAST(? AS date))';
    $par[] = $f['data_fim'];
    if ($f['empresa'] !== '') {
        $where[] = 'Solic.Empresa_Cod = ?';
        $par[] = $f['empresa'];
    }
    if ($f['filial'] !== '') {
        $where[] = 'Solic.Filial_Cod = ?';
        $par[] = $f['filial'];
    }
    if ($f['centro'] !== '') {
        $where[] = 'Solic.Centro_Cod = ?';
        $par[] = $f['centro'];
    }
    if ($f['nucleo'] !== '') {
        $where[] = 'Centro.Nucleo_Cod = ?';
        $par[] = $f['nucleo'];
    }
    if ($f['solicitante'] !== '') {
        $where[] = 'Solic.Solicitante_Cod = ?';
        $par[] = $f['solicitante'];
    }
    if ($f['comprador'] !== '') {
        $where[] = 'COALESCE(CIC.Solicitante_Cod, Insumo.Solicitante_Cod) = ?';
        $par[] = $f['comprador'];
    }
    if ($f['tipsol'] !== '') {
        $where[] = 'Solic.TipSol_Codigo = ?';
        $par[] = $f['tipsol'];
    }
    if ($f['depsol'] !== '') {
        $where[] = 'Solic.DepSol_Codigo = ?';
        $par[] = $f['depsol'];
    }
    if ($f['numero'] !== '') {
        $where[] = 'Solic.Solic_CentroNumero = ?';
        $par[] = $f['numero'];
    }
    if ($f['texto'] !== '') {
        // Caracterizacao: texto na especificacao (subinsumo + complemento)
        $where[] = "(RTRIM(LTRIM((SELECT SubInsumo.SubInsumo_Especificacao
                        FROM InsumoSub SubInsumo
                       WHERE SubInsumo.Insumo_Cod = Solicitem.Insumo_Cod
                         AND SubInsumo.SubInsumo_Cod = Solicitem.SubInsumo_Cod)))
                    + ' ' + RTRIM(LTRIM(COALESCE(Solicitem.SolicItem_EspecificacaoCpto,''))) LIKE ?)";
        $par[] = '%' . $f['texto'] . '%';
    }
    if ($f['insumo'] !== '') {
        $where[] = "(SUBSTRING(CONVERT(char(7), Solicitem.Insumo_Cod+1000000), 2, 6)
                    + SUBSTRING(CONVERT(char(4), Solicitem.SubInsumo_Cod+1000), 2, 3) LIKE ?)";
        $par[] = '%' . $f['insumo'] . '%';
    }
    if ($f['nao_concluidas']) {
        $where[] = "RTRIM(COALESCE(Solic.Solic_Concluido, '')) <> 'S'";
    }
    if ($f['autorizadas'] === 'apenas') {
        $where[] = "RTRIM(Solicitem.Instrucao_Cod) = 'A'";
    } elseif ($f['autorizadas'] === 'exceto') {
        $where[] = "RTRIM(COALESCE(Solicitem.Instrucao_Cod, '')) <> 'A'";
    }
    if ($f['recusadas'] === 'apenas') {
        $where[] = "RTRIM(COALESCE(Solicitem.Instrucao_Motivo, '')) <> ''";
    } elseif ($f['recusadas'] === 'exceto') {
        $where[] = "RTRIM(COALESCE(Solicitem.Instrucao_Motivo, '')) = ''";
    }

    // Situacoes: AB/CO/PE direto; 'AR' depende das caixas Em AR / Em AR Parcial
    $sit = [];
    foreach (['ab' => 'AB', 'co' => 'CO', 'pe' => 'PE'] as $k => $cod) {
        if ($f['sit'][$k]) {
            $sit[] = "'$cod'";
        }
    }
    $arTodos = $f['sit']['ar'];
    $arParcial = $f['sit']['arp'];
    if ($arTodos || $arParcial) {
        $sit[] = "'AR'";
    }
    $clausulaSit = $sit ? 'Solicitem.SolicItem_Situacao IN (' . implode(',', $sit) . ')' : '1=0';
    if ($arTodos && !$arParcial) {
        // Em AR sem "parcial": exclui os AR com saldo a receber (mesmo NOT do trace)
        $clausulaSit .= " AND (NOT (Solicitem.SolicItem_Situacao = 'AR' AND ($saldo) > 0))";
    } elseif ($arParcial && !$arTodos) {
        // so parcial: so os AR com saldo a receber
        $clausulaSit .= " AND (Solicitem.SolicItem_Situacao <> 'AR' OR ($saldo) > 0)";
    }
    $where[] = "($clausulaSit)";

    $sql = "
SELECT
    Solicitem.Solic_Numero,
    Solicitem.SolicItem_Numero,
    RTRIM(Solicitem.SolicItem_Item) SolicItem_Item,
    RTRIM(Solic.Empresa_Cod) Empresa_Cod,
    RTRIM(Solic.Filial_Cod) Filial_Cod,
    RTRIM(Solic.Centro_Cod) Centro_Cod,
    Centro_CodC = Solic.Empresa_Cod + Solic.Filial_Cod + Solic.Centro_Cod,
    Solic.Solic_Data,
    Solic.Solic_CentroNumero,
    Solicitacao_ID = RTRIM(CONVERT(char(8), Solic.Solic_CentroNumero)) + '/' + Solicitem.SolicItem_Item,
    Solicitem.Instrucao_Cod,
    Solicitem.Instrucao_Data,
    RTRIM(Solicitem.Usuario_Instrucao) Usuario_Instrucao,
    RTRIM(Solicitem.Instrucao_Motivo) Instrucao_Motivo,
    Solicitem.SolicItem_PrecoUnit,
    Solicitem.SolicItem_Quantidade,
    Solicitem.SolicItem_DataEntrega,
    RTRIM(Solicitem.SolicItem_Situacao) SolicItem_Situacao,
    Insumo_CodC = SUBSTRING(CONVERT(char(7), Solicitem.Insumo_Cod+1000000), 2, 6)
                + '.' + SUBSTRING(CONVERT(char(4), Solicitem.SubInsumo_Cod+1000), 2, 3),
    SolicItem_EspecificacaoGeral =
        RTRIM(LTRIM(COALESCE((SELECT SubInsumo.SubInsumo_Especificacao
              FROM InsumoSub SubInsumo
             WHERE SubInsumo.Insumo_Cod = Solicitem.Insumo_Cod
               AND SubInsumo.SubInsumo_Cod = Solicitem.SubInsumo_Cod), '')))
        + ' ' + RTRIM(LTRIM(COALESCE(Solicitem.SolicItem_EspecificacaoCpto, ''))),
    RTRIM(Solicitem.SolicItem_Obs) SolicItem_Obs,
    Insumo.Unid_Cod,
    RTRIM(Centro.Centro_Nome) Centro_Nome,
    RTRIM(Centro.Nucleo_Cod) Nucleo_Cod,
    RTRIM(Empresa.Empresa_Nome) Empresa_Nome,
    RTRIM(Solicitante.Solicitante_Nome) Solicitante_Nome,
    Pedidoitem.Pedido_Numero,
    Pedido_CentroNumeroC =
        (SELECT Pedido.Pedido_CentroNumero FROM Pedido
          WHERE Pedido.Pedido_Numero = Pedidoitem.Pedido_Numero),
    Entrega_Data = Pedidoitem.PedItem_DataEntrega,
    ColetaItens.Coleta_Numero,
    Saldo = ($saldo),
    Solic.Solic_Concluido,
    Instrucao_Data_Pedido =
        (SELECT Pedido.Instrucao_Data FROM Pedido
          WHERE Pedido.Pedido_Numero = Pedidoitem.Pedido_Numero
            AND Solic.Solic_Numero = Pedidoitem.Solic_Numero
            AND Solicitem.SolicItem_Numero = Pedidoitem.SolicItem_Numero),
    Usuario_Instrucao_Pedido =
        (SELECT RTRIM(Pedido.Usuario_Instrucao) FROM Pedido
          WHERE Pedido.Pedido_Numero = Pedidoitem.Pedido_Numero
            AND Solic.Solic_Numero = Pedidoitem.Solic_Numero
            AND Solicitem.SolicItem_Numero = Pedidoitem.SolicItem_Numero),
    Pedido_Data =
        (SELECT Pedido.Pedido_Data FROM Pedido
          WHERE Pedido.Pedido_Numero = Pedidoitem.Pedido_Numero
            AND Solic.Solic_Numero = Pedidoitem.Solic_Numero
            AND Solicitem.SolicItem_Numero = Pedidoitem.SolicItem_Numero),
    Ar_Data =
        (SELECT TOP 1 DocEnt.Ent_DataEntrada
           FROM Aviso_de_Recebimento AR,
                Documento_de_Entrada_Item Entradaitem,
                Documento_de_Entrada DocEnt
          WHERE (AR.AR_Numero = DocEnt.AR_Numero)
            AND (Solic.Solic_Numero = Entradaitem.Solic_Numero)
            AND (Solicitem.SolicItem_Numero = Entradaitem.SolicItem_Numero)
            AND (Entradaitem.Ent_Numero = DocEnt.Ent_Numero)
            AND (NOT DocEnt.Nop_Cod IN ('11202','21202','11204','21204'))
          ORDER BY AR.AR_Data ASC, AR.AR_Numero),
    RTRIM(Solicitante2.Solicitante_Nome) Comprador_Centro,
    Solicitante_Nome_Geral =
        COALESCE(Solicitante2.Solicitante_Nome, Solicitante3.Solicitante_Nome)
FROM Solicitacao_de_Compra_Itens Solicitem
    LEFT JOIN Pedido_Item Pedidoitem
        ON Solicitem.SolicItem_Numero = Pedidoitem.SolicItem_Numero
        AND Solicitem.Solic_Numero = Pedidoitem.Solic_Numero
    LEFT JOIN Coleta_de_Preco_Itens ColetaItens
        ON Solicitem.SolicItem_Numero = ColetaItens.SolicItem_Numero
        AND Solicitem.Solic_Numero = ColetaItens.Solic_Numero
    INNER JOIN Solicitacao_de_Compra Solic ON Solic.Solic_Numero = Solicitem.Solic_Numero
    INNER JOIN Tipo_Solicitacao_Compra TipSol ON Solic.TipSol_Codigo = TipSol.TipSol_Codigo
    INNER JOIN Departamento_Solicitacao_Compra DepSol ON Solic.DepSol_Codigo = DepSol.DepSol_Codigo
    LEFT JOIN centro_de_custo Centro ON Solic.Filial_Cod = Centro.Filial_Cod
        AND Solic.Empresa_Cod = Centro.Empresa_Cod
        AND Solic.Centro_Cod = Centro.Centro_Cod
    LEFT JOIN Empresa ON Solic.Empresa_Cod = Empresa.Empresa_Cod
    LEFT JOIN Solicitante Solicitante ON Solic.Solicitante_Cod = Solicitante.Solicitante_Cod
    INNER JOIN Insumo Insumo ON Solicitem.Insumo_Cod = Insumo.Insumo_Cod
    INNER JOIN Caracterizacao_de_Insumos caracterizacao
        ON caracterizacao.GruIns_Cod = Insumo.GruIns_Cod
        AND caracterizacao.ClaIns_Cod = Insumo.ClaIns_Cod
        AND caracterizacao.CarIns_Cod = Insumo.CarIns_Cod
    LEFT JOIN Centro_Insumo_Comprador CIC ON CIC.Empresa_Cod = Centro.Empresa_Cod
        AND CIC.Filial_Cod = Centro.Filial_Cod
        AND CIC.Centro_Cod = Centro.Centro_Cod
        AND CIC.Insumo_Cod = Insumo.Insumo_Cod
        AND CIC.ClaIns_Cod = Insumo.ClaIns_Cod
        AND CIC.GruIns_Cod = Insumo.GruIns_Cod
    LEFT JOIN Solicitante Solicitante2 ON CIC.Solicitante_Cod = Solicitante2.Solicitante_Cod
    LEFT JOIN Solicitante Solicitante3 ON Insumo.Solicitante_Cod = Solicitante3.Solicitante_Cod
WHERE " . implode("\n  AND ", $where) . "
ORDER BY Solic.Solic_Data DESC, Solic.Solic_Numero, Solicitem.SolicItem_Item";

    $rows = query($sql, $par, $banco);
    $base = strtolower($banco) === 'dbmga' ? 'MGA' : 'CONV';
    foreach ($rows as &$r) {
        $r['Base'] = $base;
    }
    return $rows;
}

/** Executa nas duas bases e une; erros por base viram mensagens. */
function flw_dados(array $f): array
{
    $out = ['linhas' => [], 'erros' => []];
    foreach (['CONV' => banco_da_base('CONV'), 'MGA' => banco_da_base('MGA')] as $base => $banco) {
        try {
            $out['linhas'] = array_merge($out['linhas'], flw_query($f, $banco));
        } catch (Throwable $t) {
            $out['erros'][] = "$banco: " . $t->getMessage();
        }
    }
    return $out;
}

/** Pos-calculos por linha (regras da tela): atrasos, situacao exibida, cores. */
function flw_enriquecer(array $rows): array
{
    $hoje = new DateTime('today');
    foreach ($rows as &$r) {
        $dSolic = $r['Solic_Data'] ? new DateTime(substr((string) $r['Solic_Data'], 0, 10)) : null;
        $dEntrega = $r['SolicItem_DataEntrega'] ? new DateTime(substr((string) $r['SolicItem_DataEntrega'], 0, 10)) : null;
        $dAut = $r['Instrucao_Data'] ? new DateTime(substr((string) $r['Instrucao_Data'], 0, 10)) : null;
        $saldo = (float) ($r['Saldo'] ?? 0);
        $r['Saldo'] = $saldo;
        // Atraso = hoje - entrega (positivo = vencida; negativo = dias restantes)
        $r['Atraso'] = $dEntrega ? (int) -$hoje->diff($dEntrega)->format('%r%a') : null;
        // Atraso Compra = dias desde a autorizacao sem pedido
        $r['AtrasoCompra'] = ($dAut && !$r['Pedido_Numero'] && $saldo > 0)
            ? max(0, (int) $hoje->diff($dAut)->format('%r%a')) : null;
        // vermelho: entrega da solicitacao vencida com saldo a receber
        $r['Atrasada'] = $r['Atraso'] !== null && $r['Atraso'] > 0 && $saldo > 0;
        $r['Autorizada'] = trim((string) $r['Instrucao_Cod']) === 'A';
        $r['Sit'] = $r['SolicItem_Situacao'];
    }
    return $rows;
}

/** Agrupa linhas para a banda do grid ('empresa' | 'centro' | 'solicitacao'). */
function flw_agrupar(array $rows, string $modo): array
{
    $grupos = [];
    foreach ($rows as $r) {
        if ($modo === 'solicitacao') {
            $k = $r['Base'] . '|' . $r['Solic_Numero'];
            $rot = $r['Empresa_Cod'] . ' · Solicitação '
                 . RTRIM((string) $r['Solic_CentroNumero']) . ' — '
                 . ($r['Centro_CodC'] . ' ' . $r['Centro_Nome']);
        } elseif ($modo === 'empresa') {
            $k = $r['Base'] . '|' . $r['Empresa_Cod'];
            $rot = $r['Empresa_Cod'] . ($r['Empresa_Nome'] ? ' — ' . $r['Empresa_Nome'] : '')
                 . ' · ' . $r['Base'];
        } else { // centro (padrao do Informakon)
            $k = $r['Base'] . '|' . $r['Centro_CodC'];
            $rot = $r['Empresa_Cod'] . ' — ' . $r['Centro_CodC']
                 . ($r['Centro_Nome'] ? ' ' . $r['Centro_Nome'] : '') . ' · ' . $r['Base'];
        }
        $grupos[$k]['rotulo'] = $rot;
        $grupos[$k]['linhas'][] = $r;
    }
    return $grupos;
}

/** Combos do filtro: empresas, filiais, centros, nucleos, solicitantes,
 *  tipos, departamentos (uniao das duas bases, sem duplicar). */
function flw_combos(): array
{
    $out = ['empresas' => [], 'filiais' => [], 'centros' => [], 'nucleos' => [],
            'solicitantes' => [], 'compradores' => [], 'tipsol' => [], 'depsol' => []];
    foreach (['CONV' => banco_da_base('CONV'), 'MGA' => banco_da_base('MGA')] as $banco) {
        try {
            foreach (query("SELECT RTRIM(Empresa_Cod) c, CONVERT(varchar(4000), Empresa_Nome) n
                            FROM $banco.dbo.Empresa ORDER BY Empresa_Cod") as $r) {
                $out['empresas'][$r['c']] = $r['c'] . ' — ' . trim($r['n']);
            }
            foreach (query("SELECT RTRIM(Empresa_Cod) e, RTRIM(Filial_Cod) c, CONVERT(varchar(4000), Filial_Nome) n
                            FROM $banco.dbo.Filial ORDER BY Filial_Cod") as $r) {
                $out['filiais'][$r['e'] . '|' . $r['c']] = $r['c'] . ' — ' . trim($r['n']);
            }
            foreach (query("SELECT RTRIM(Empresa_Cod) e, RTRIM(Filial_Cod) f, RTRIM(Centro_Cod) c,
                                   CONVERT(varchar(4000), Centro_Nome) n, RTRIM(Nucleo_Cod) nu
                            FROM $banco.dbo.Centro_de_Custo
                            WHERE ISNULL(RTRIM(Centro_Inativo),'N') <> 'S'
                            ORDER BY Centro_Nome") as $r) {
                $out['centros'][$r['e'] . '|' . $r['f'] . '|' . $r['c']] =
                    $r['e'] . $r['f'] . $r['c'] . ' — ' . trim($r['n']);
            }
            foreach (query("SELECT RTRIM(Nucleo_Cod) c, CONVERT(varchar(4000), Nucleo_Nome) n
                            FROM $banco.dbo.Nucleo_de_Negocio ORDER BY Nucleo_Nome") as $r) {
                $out['nucleos'][$r['c']] = $r['c'] . ' — ' . trim($r['n']);
            }
            foreach (query("SELECT RTRIM(Solicitante_Cod) c, CONVERT(varchar(4000), Solicitante_Nome) n
                            FROM $banco.dbo.Solicitante ORDER BY Solicitante_Nome") as $r) {
                $out['solicitantes'][$r['c']] = trim($r['n']);
                $out['compradores'][$r['c']] = trim($r['n']);
            }
            foreach (query("SELECT RTRIM(TipSol_Codigo) c, CONVERT(varchar(4000), TipSol_Descricao) n
                            FROM $banco.dbo.Tipo_Solicitacao_Compra") as $r) {
                $out['tipsol'][$r['c']] = trim($r['n']);
            }
            foreach (query("SELECT RTRIM(DepSol_Codigo) c, CONVERT(varchar(4000), DepSol_Descricao) n
                            FROM $banco.dbo.Departamento_Solicitacao_Compra") as $r) {
                $out['depsol'][$r['c']] = trim($r['n']);
            }
        } catch (Throwable $t) { /* base indisponivel: segue com a outra */ }
    }
    return $out;
}
