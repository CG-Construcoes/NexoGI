<?php
declare(strict_types=1);

/**
 * Financeiro > Contas a Pagar > Relatorio de Contas a Pagar.
 * Replica o relatorio do Informakon a partir do trace
 * (traces/Rel_contasapagarEdeEP.trc): mesma query sobre Titulos_a_Pagar
 * + Documento_de_Entrada. O join CreaPag (TotCredor) e calculado em PHP.
 * $base = 'MGA'|'CONV' (conexao direta na base ERP via banco_da_base()).
 */

function cp_empresas(string $base): array
{
    return query("SELECT RTRIM(Empresa_Cod) Empresa_Cod,
                         CONVERT(varchar(120), RTRIM(Empresa_Nome)) Empresa_Nome
                  FROM Empresa ORDER BY Empresa_Nome", [], banco_da_base($base));
}

function cp_credores(string $base): array
{
    $map = [];
    foreach (query("SELECT RTRIM(c.Cre_Cod) Cre_Cod,
                           CONVERT(varchar(120), RTRIM(c.Cre_RazSoc)) Cre_RazSoc
                    FROM Credor c
                    WHERE EXISTS (SELECT 1 FROM Titulos_a_Pagar t
                                  WHERE t.Cre_Cod = c.Cre_Cod)
                    ORDER BY c.Cre_RazSoc", [], banco_da_base($base)) as $r) {
        $map[$r['Cre_Cod']] = $r['Cre_Cod'] . ' - ' . $r['Cre_RazSoc'];
    }
    return $map;
}

function cp_especies(string $base): array
{
    return query("SELECT RTRIM(EspTit_Cod) EspTit_Cod,
                         CONVERT(varchar(80), RTRIM(EspTit_Titulo)) EspTit_Titulo
                  FROM Especie_de_Titulos_Apagar ORDER BY EspTit_Cod",
        [], banco_da_base($base));
}

function cp_doctipos(string $base): array
{
    return query("SELECT RTRIM(EntDocTipo_Cod) cod, COUNT(*) n
                  FROM Documento_de_Entrada
                  GROUP BY EntDocTipo_Cod ORDER BY EntDocTipo_Cod",
        [], banco_da_base($base));
}

function cp_nucleos(string $base): array
{
    return query("SELECT RTRIM(Nucleo_Cod) Nucleo_Cod,
                         CONVERT(varchar(80), RTRIM(Nucleo_Nome)) Nucleo_Nome
                  FROM Nucleo_de_Negocio ORDER BY Nucleo_Nome",
        [], banco_da_base($base));
}

function cp_grupos_nucleo(string $base): array
{
    return query("SELECT RTRIM(GrupoNucleo_Cod) GrupoNucleo_Cod,
                         CONVERT(varchar(80), RTRIM(GrupoNucleo_Titulo)) GrupoNucleo_Titulo
                  FROM Grupo_Nucleo_de_Negocio ORDER BY GrupoNucleo_Titulo",
        [], banco_da_base($base));
}

/**
 * Executa a query do relatorio. Devolve ['linhas'=>[...], 'erro'=>''].
 * $o chaves: empresa, ini, fim (Y-m-d), ck_venc, ck_agenda, ck_autor,
 * atrasadas, pendente, inc_ep, apenas_ep, inc_ed, apenas_ed,
 * centro (CodC 9 ou ''), classificacao, doc_op/doc_sel[], esp_op/esp_sel[],
 * credor, nucleo, grupo_nucleo, excl_aclass, so_autor, so_nautor.
 */
function cp_dados(string $base, array $o): array
{
    $hoje = date('Y-m-d H:i:s');
    $ini = $o['ini'] . ' 00:00:00';
    $fim = $o['fim'] . ' 23:59:59';
    $par = [];

    $w = "( DocEnt.Empresa_Cod = ? OR ? IS NULL )";
    $par[] = $o['empresa'] !== '' ? $o['empresa'] : '';
    $par[] = $o['empresa'] !== '' ? $o['empresa'] : null;

    // exclui titulos com pagamento ja conciliado (trace)
    $w .= " AND ( NOT EXISTS ( SELECT * FROM Movimento_Financeiro MovFin,
                                     Pagamentos Pagto
                              WHERE MovFin.MovFin_Numero = Pagto.MovFin_Numero
                                AND TitaPag.Pag_Numero = Pagto.Pag_Numero
                                AND MovFin.MovFin_Conciliado = 'C' ) )";

    // ----- bloco do periodo: vencimento / agenda / autorizacao -----
    $branches = [];
    if ($o['ck_agenda']) {
        $branches[] = "( TitaPag.TitaPag_DataAgendaFin BETWEEN ? AND ?
                        AND TitaPag.TitaPag_Situacao = 'A' )";
        $par[] = $ini;
        $par[] = $fim;
    }
    if ($o['ck_venc']) {
        $branches[] = "( TitaPag.TitaPag_DataVencimentoFluxo BETWEEN ? AND ?
                        AND ( TitaPag.TitaPag_Situacao = 'A'
                          OR ( TitaPag.TitaPag_Situacao = 'L'
                               AND TitaPag.Pag_DataPagamento > ?
                               AND TitaPag.Pag_DataDocumento > TitaPag.Pag_DataEmissao ) ) )";
        $par[] = $ini;
        $par[] = $fim;
        $par[] = $hoje;
    }
    if ($o['ck_autor']) {
        $branches[] = "( TitaPag.Instrucao_Data BETWEEN ? AND ?
                        AND TitaPag.TitaPag_Situacao = 'A' )";
        $par[] = $ini;
        $par[] = $fim;
    }
    if ($o['atrasadas']) {
        $branches[] = "( TitaPag.TitaPag_DataVencimentoFluxo < ?
                        AND TitaPag.TitaPag_Situacao = 'A' )";
        $par[] = $ini;
    }
    if ($o['pendente']) {
        $branches[] = "TitaPag.Pag_Pendente = 'S'";
    }
    if (!$branches) {
        return ['linhas' => [], 'erro' => 'Marque ao menos um tipo de data/inclusao.'];
    }
    $w .= ' AND ( ' . implode(' OR ', $branches) . ' )';

    // ----- previsoes ED/EP -----
    if ($o['apenas_ep']) {
        $w .= " AND DocEnt.EntDocTipo_Cod = 'EP'";
    } elseif ($o['apenas_ed']) {
        $w .= " AND DocEnt.EntDocTipo_Cod = 'ED'";
    } else {
        if (!$o['inc_ep']) {
            $w .= " AND DocEnt.EntDocTipo_Cod <> 'EP'";
        }
        if (!$o['inc_ed']) {
            $w .= " AND DocEnt.EntDocTipo_Cod <> 'ED'";
        }
    }

    // ----- filtros pontuais -----
    if ($o['centro'] !== '') {
        $w .= ' AND DocEnt.Empresa_Cod = ? AND DocEnt.Filial_Cod = ? AND DocEnt.Centro_Cod = ?';
        $par[] = substr($o['centro'], 0, 3);
        $par[] = substr($o['centro'], 3, 2);
        $par[] = substr($o['centro'], 5, 4);
    }
    if ($o['classificacao'] !== '') {
        $w .= " AND CONVERT(varchar(4000), DocEnt.Ent_Natureza) LIKE ?";
        $par[] = '%' . $o['classificacao'] . '%';
    }
    if ($o['credor'] !== '') {
        $w .= ' AND TitaPag.Cre_Cod = ?';
        $par[] = $o['credor'];
    }
    if ($o['nucleo'] !== '') {
        $w .= ' AND Cen.Nucleo_Cod = ?';
        $par[] = $o['nucleo'];
    }
    if ($o['grupo_nucleo'] !== '') {
        $w .= ' AND Nucleo.GrupoNucleo_Cod = ?';
        $par[] = $o['grupo_nucleo'];
    }
    if ($o['doc_sel'] && $o['doc_op'] !== 'todos') {
        $ph = implode(',', array_fill(0, count($o['doc_sel']), '?'));
        $w .= ' AND DocEnt.EntDocTipo_Cod ' . ($o['doc_op'] === 'apenas' ? 'IN' : 'NOT IN')
            . " ($ph)";
        foreach ($o['doc_sel'] as $d) { $par[] = $d; }
    }
    if ($o['esp_sel'] && $o['esp_op'] !== 'todos') {
        $ph = implode(',', array_fill(0, count($o['esp_sel']), '?'));
        $w .= ' AND TitaPag.EspTit_Cod ' . ($o['esp_op'] === 'apenas' ? 'IN' : 'NOT IN')
            . " ($ph)";
        foreach ($o['esp_sel'] as $e) { $par[] = $e; }
    }
    if ($o['excl_aclass']) {
        $w .= " AND NULLIF(RTRIM(CONVERT(varchar(4000), DocEnt.Ent_Natureza)), '') IS NOT NULL";
    }
    if ($o['so_autor']) {
        $w .= " AND TitaPag.TitaPag_PagamentoAutorizado = 'S'";
    }
    if ($o['so_nautor']) {
        $w .= " AND ISNULL(RTRIM(TitaPag.TitaPag_PagamentoAutorizado), '') <> 'S'";
    }

    $sql = "SELECT TitaPag.TitaPag_Numero, TitaPag.Cre_Cod,
        CONVERT(varchar(120), Credor.Cre_RazSoc) Cre_RazSoc,
        CONVERT(varchar(20), Credor.Cre_CGCCPF) Cre_CGCCPF,
        TitaPag.TitaPag_DataVencimento, TitaPag.Pag_DataPagamento,
        TitaPag.TitaPag_Valor, TitaPag.TitaPag_Acrescimos, TitaPag.TitaPag_Descontos,
        TitaPag.TitaPag_DescontoAdiantamento, TitaPag.TitaPag_EncargosFin,
        TitaPag.TitaPag_TotalPago, TitaPag.TitaPag_Multa,
        TitaPag.TitaPag_Valor + TitaPag.TitaPag_EncargosFin + TitaPag.TitaPag_Multa
            + TitaPag.TitaPag_Acrescimos - TitaPag.TitaPag_Descontos
            - TitaPag.TitaPag_DescontoAdiantamento TitaPag_ValorLiquido,
        DocEnt.EntDocTipo_Cod, DocEnt.Ent_DocNumero,
        RTRIM(DocEnt.EntDocTipo_Cod) + ' ' + RTRIM(DocEnt.Ent_DocNumero) Ent_Documento,
        CONVERT(varchar(4000), DocEnt.Ent_Natureza) Ent_Natureza, DocEnt.Ent_DataEntrada,
        TitaPag.EspTit_Cod, TitaPag.TitaPag_DocNumero,
        RTRIM(TitaPag.EspTit_Cod) + ' ' + RTRIM(TitaPag.TitaPag_DocNumero) TitaPag_Documento,
        TitaPag.TitaPag_ParNumero, TitaPag.TitaPag_SParNumero,
        RTRIM(LTRIM(( SELECT SUM(1) FROM titulos_a_pagar TitaPag2
                      WHERE TitaPag2.Ent_Numero = TitaPag.Ent_Numero ))) nParc,
        CASE WHEN DocEnt.MovFinNatureza_Cod = 'TC' THEN DocEnt.Contrato_Numero ELSE '' END Imovel_TC,
        TitaPag.TitaPag_Situacao, TitaPag.TitaPag_DataAgendaFin,
        TitaPag.AgenteFinanceiroTipo_Cod, TitaPag.AgenteFinanceiro_Cod,
        CONVERT(varchar(120), AgeFin.AgenteFinanceiro_RazaoSocial) AgenteFinanceiro_RazaoSocial,
        DocEnt.Empresa_Cod, DocEnt.Filial_Cod, DocEnt.Centro_Cod,
        CONVERT(varchar(120), Cen.Centro_Nome) Centro_Nome,
        DocEnt.SP_Numero, DocEnt.Ficha_Numero,
        DocEnt.Ent_Numero, DocEnt.Ent_Tipo, DocEnt.AR_Numero,
        DocEnt.Moe_Cod, DocEnt.Moe_Desloc, DocEnt.Ent_DataBase,
        TitaPag.TitaPag_DataVencimentoOrig,
        TitaPag.Instrucao_Cod, TitaPag.Usuario_Instrucao, TitaPag.Instrucao_Data,
        TitaPag.TitaPag_NumeroNegociacoes, TitaPag.TitaPag_DataVencimentoFluxo,
        DATEDIFF(day, TitaPag.TitaPag_DataVencimentoOrig, ?) DiasemAtraso,
        TitaPag.Pag_Pendente,
        CASE WHEN TitaPag.Pag_DataEmissao <> TitaPag.Pag_DataDocumento THEN 'S' ELSE 'N' END Pag_PreDatado,
        Cen.Nucleo_Cod,
        CONVERT(varchar(80), Nucleo.Nucleo_Nome) Nucleo_Nome, Nucleo.GrupoNucleo_Cod,
        CASE WHEN EXISTS ( SELECT Adiantamentos.Adi_Numero FROM Adiantamentos
                           WHERE TitaPag.Cre_Cod = Adiantamentos.Cre_Cod
                             AND Adiantamentos.Adi_Saldo > 0 ) THEN 'S' ELSE 'N' END Adi_EmAberto,
        TitaPag.TitaPag_Local_AgeTes, TitaPag.Instrucao_DataPagamento,
        TitaPag.TitaPag_NatPag, TitaPag.TitaPag_PagamentoAutorizado,
        CONVERT(varchar(400), TitaPag.TitaPag_ObsPagamento) TitaPag_ObsPagamento
        FROM Titulos_a_Pagar TitaPag
        INNER JOIN Documento_de_Entrada DocEnt ON TitaPag.Ent_Numero = DocEnt.Ent_Numero
        INNER JOIN Credor ON TitaPag.Cre_Cod = Credor.Cre_Cod
        INNER JOIN Centro_de_Custo Cen ON DocEnt.Empresa_Cod = Cen.Empresa_Cod
                                     AND DocEnt.Filial_Cod = Cen.Filial_Cod
                                     AND DocEnt.Centro_Cod = Cen.Centro_Cod
        LEFT JOIN Nucleo_de_Negocio Nucleo ON Nucleo.Nucleo_Cod = Cen.Nucleo_Cod
        LEFT OUTER JOIN AgenteFinanceiro AgeFin
            ON TitaPag.AgenteFinanceiroTipo_Cod = AgeFin.AgenteFinanceiroTipo_Cod
           AND TitaPag.AgenteFinanceiro_Cod = AgeFin.AgenteFinanceiro_Cod
        WHERE $w";

    array_unshift($par, $hoje); // DiasemAtraso — primeiro '?' da query
    return ['linhas' => query($sql, $par, banco_da_base($base)), 'erro' => ''];
}

/** Rotulo do agrupamento escolhido (para o titulo "por Ordem de ..."). */
const CP_AGRUP = [
    'agenda'        => 'Data da Agenda',
    'vencimento'    => 'Data de Vencimento',
    'credor_uo'     => 'Credor - UO',
    'credor_val'    => 'Credor - Valores a Pagar',
    'faixa'         => 'Faixa de Valores',
    'centro'        => 'Centro de Negócio',
    'autorizacao'   => 'Autorização',
    'credor_venc'   => 'Credor - Data de Vencimento',
];

/** Chave de agrupamento + rotulo do grupo de cada linha. */
function cp_chave_grupo(array $r, string $agrup): array
{
    $centro = trim($r['Empresa_Cod'] . $r['Filial_Cod'] . $r['Centro_Cod']
        . ' ' . ($r['Centro_Nome'] ?? ''));
    $credor = trim(($r['Cre_RazSoc'] ?? '') . ' - ' . ($r['Cre_CGCCPF'] ?? ''), ' -');
    return match ($agrup) {
        // ordenacao por data nao quebra em grupos (subtotais por dia so via Totaliza)
        'agenda', 'vencimento' => ['', ''],
        'centro'      => [$centro, 'Centro de Negócio: ' . $centro],
        'autorizacao' => [$r['TitaPag_PagamentoAutorizado'] === 'S' ? 'S' : 'N',
                          $r['TitaPag_PagamentoAutorizado'] === 'S' ? 'Autorizadas'
                                                                  : 'Não autorizadas'],
        'faixa'       => cp_faixa((float) $r['TitaPag_ValorLiquido']),
        default       => [$credor, 'Credor: ' . $credor], // credor_uo/val/venc
    };
}

/** Faixa de valores do grupo "Faixa de Valores". */
function cp_faixa(float $v): array
{
    foreach ([[1000, 'Até 1.000'], [5000, '1.000 a 5.000'], [10000, '5.000 a 10.000'],
              [50000, '10.000 a 50.000'], [100000, '50.000 a 100.000']] as $i => [$lim, $rot]) {
        if ($v <= $lim) {
            return [str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Faixa: ' . $rot];
        }
    }
    return ['99', 'Faixa: Acima de 100.000'];
}

/** Data exibida na coluna DataAgend conforme o agrupamento. */
function cp_data_linha(array $r, string $agrup): ?string
{
    $campo = in_array($agrup, ['vencimento', 'credor_venc'], true)
        ? 'TitaPag_DataVencimentoFluxo' : 'TitaPag_DataAgendaFin';
    $d = substr((string) ($r[$campo] ?? ''), 0, 10);
    return $d !== '' && $d !== '1900-01-01' && $d !== '1930-02-25' ? $d : null;
}

/**
 * Ordena as linhas pelo agrupamento e devolve [[key,rotulo,linhas[]],...].
 * Credor - Valores a Pagar ordena os grupos pelo total (TotCredor).
 */
function cp_agrupar(array $linhas, string $agrup): array
{
    $grupos = [];
    foreach ($linhas as $r) {
        [$k, $rot] = cp_chave_grupo($r, $agrup);
        $grupos[$k]['rotulo'] = $rot;
        $grupos[$k]['linhas'][] = $r;
    }
    $sort = match ($agrup) {
        'vencimento' => fn($a, $b) => cp_data_linha($a, 'vencimento')
            <=> cp_data_linha($b, 'vencimento'),
        'credor_venc' => fn($a, $b) => cp_data_linha($a, 'credor_venc')
            <=> cp_data_linha($b, 'credor_venc'),
        default      => fn($a, $b) => cp_data_linha($a, 'agenda')
            <=> cp_data_linha($b, 'agenda'),
    };
    foreach ($grupos as &$g) {
        usort($g['linhas'], $sort);
        $g['total'] = array_sum(array_map(
            fn($r) => (float) $r['TitaPag_ValorLiquido'], $g['linhas']));
    }
    unset($g); // quebra a referencia — senao o foreach seguinte corrompe o ultimo grupo
    if ($agrup === 'credor_val') {
        uasort($grupos, fn($a, $b) => $b['total'] <=> $a['total']);
    } else {
        ksort($grupos);
    }
    $out = [];
    foreach ($grupos as $k => $g) {
        $out[] = ['key' => $k, 'rotulo' => $g['rotulo'], 'linhas' => $g['linhas']];
    }
    return $out;
}

/** Balde de totalizacao: 'dia'|'quinzena'|'mes' => [chave, rotulo] da linha. */
function cp_balde(array $r, string $agrup, string $nivel): array
{
    $d = cp_data_linha($r, $agrup) ?? '0000-00-00';
    [$y, $m, $dd] = [substr($d, 0, 4), substr($d, 5, 2), substr($d, 8, 2)];
    return match ($nivel) {
        'dia'      => [$d, 'Dia ' . data_br($d)],
        'quinzena' => [$y . $m . ((int) $dd <= 15 ? '1' : '2'),
                       'Quinzena ' . ((int) $dd <= 15 ? '1' : '2') . ' de ' . data_br("$y-$m-01")],
        default    => [$y . $m, 'Mês ' . substr(data_br("$y-$m-01"), 3)],
    };
}
