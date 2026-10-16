<?php

declare(strict_types=1);

// Agenda de Pagamentos — espelha a proc ERP dbo.sp_ParcelasAgendaPagto.
// A proc é invocada read-only (chaves ParamTemp vazias => não toca nas
// tabelas Parametros_Temporarios_*; os demais INSERTs são só em #tempdb).
// Filtros de empresa/centro/espécie são aplicados em PHP.

function ap_sql(string $v): string
{
    return "'" . str_replace("'", "''", $v) . "'";
}

function ap_dados(string $base, array $f): array
{
    // Empresa_Cod unitario na proc; multipla selecao e filtrada em PHP.
    $sql = 'SET NOCOUNT ON; EXEC dbo.sp_ParcelasAgendaPagto'
        . ' @Empresa_Cod=' . ap_sql('')
        . ', @Filial_Cod=NULL'
        . ', @Centro_Cod=NULL'
        . ', @Cre_Cod=' . ($f['cre'] !== '' ? ap_sql($f['cre']) : 'NULL')
        . ', @Nucleo_Cod=' . ($f['nucleo'] !== '' ? ap_sql($f['nucleo']) : 'NULL')
        . ', @DataIni=' . ap_sql($f['ini'])
        . ', @DataFin=' . ap_sql($f['fim'])
        . ', @DataRef=' . ap_sql($f['ref'])
        . ', @IncAtrasadas=' . ap_sql($f['atrasadas'] ? 'S' : 'N')
        . ', @IncPendentes=' . ap_sql($f['pendentes'] ? 'S' : 'N')
        . ', @param_ADI_FORN_PEDIDO=' . ap_sql('S')
        . ', @param_ADI_FORN_CENTRODECUSTO=' . ap_sql('S')
        . ', @Inclui_EntTipo_ED=' . ap_sql($f['ed'] ? 'S' : 'N')
        . ', @Inclui_EntTipo_EP=' . ap_sql($f['ep'] ? 'S' : 'N')
        . ', @Inclui_EntTipo_CV=' . ap_sql($f['cv'] ? 'S' : 'N')
        . ', @NaoAutorizado=' . ap_sql($f['nao_aut'] ? 'S' : 'N')
        . ', @Cre_RazSoc=NULL'
        . ', @EntDoc_Numero=' . ($f['docnum'] !== ''
            ? ap_sql('%' . $f['docnum'] . '%') : 'NULL')
        . ', @ConsultaAssuntoOrcamentario=' . ap_sql('N')
        . ', @Assunto_Origem=NULL, @Assunto_Cod=NULL, @Go_Cod=NULL, @SubGo_Cod=NULL'
        . ', @DataInicialAR=NULL, @DataFinalAR=NULL'
        . ', @DataInicialEntradaAR=NULL, @DataFinalEntradaAR=NULL'
        . ', @DataInicialEntradaOE=NULL, @DataFinalEntradaOE=NULL'
        . ', @TipoDocPagto=NULL'
        . ', @Ficha_Numero=' . ($f['ficha'] !== '' ? ap_sql($f['ficha']) : 'NULL')
        . ', @ValorMin=' . ($f['vmin'] !== null ? number_format($f['vmin'], 2, '.', '') : 'NULL')
        . ', @ValorMax=' . ($f['vmax'] !== null ? number_format($f['vmax'], 2, '.', '') : 'NULL')
        . ', @ParamTemp_ChaveEmpresa=' . ap_sql('')
        . ', @ParamTemp_ChaveClassificacao=' . ap_sql('')
        . ', @OcultarARSemAutorizacaoFiscal=' . ap_sql('N')
        . ', @HabilitarARConcluido=' . ap_sql('N')
        . ', @OcultarARSemAutorizacao=' . ap_sql('N');

    $rows = query($sql, [], banco_da_base($base));

    // Filtros em PHP que a proc não recebe por parâmetro.
    $emps = array_filter(array_map(
        fn($e) => strtoupper(trim($e)), (array) ($f['empresas'] ?? [])));
    // Centro_CodC vem pontilhado ("CGC.01.0107"); compara so alfanumericos.
    $alnum = fn($s) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $s));
    $centros = array_filter(array_map($alnum, (array) ($f['centros'] ?? [])));
    $esp = trim((string) ($f['especie'] ?? ''));
    if ($emps !== [] || $centros !== [] || $esp !== '') {
        $setE = array_flip($emps);
        $setC = array_flip($centros);
        $rows = array_values(array_filter($rows,
            function ($r) use ($setE, $emps, $setC, $centros, $esp, $alnum) {
                if ($emps !== [] && !isset($setE[strtoupper(trim((string) $r['Empresa_Cod']))])) {
                    return false;
                }
                if ($centros !== [] && !isset($setC[$alnum($r['Centro_CodC'])])) {
                    return false;
                }
                return $esp === '' || trim((string) $r['EspTit_Cod']) === $esp;
            }));
    }

    return $rows;
}

function ap_ordernar(array $rows, string $ordem, ?callable $grpKey = null): array
{
    $key = function (array $r) use ($ordem): string {
        switch ($ordem) {
            case 'agenda':
                return substr((string) $r['TitaPag_DataAgendaFin'], 0, 10) . '|'
                    . substr((string) $r['TitaPag_DataVencimento'], 0, 10) . '|' . $r['Cre_RazSoc'];
            case 'credor_valor':
                return $r['Cre_RazSoc'] . '|' . str_pad((string) (int) ((float) $r['TitaPag_ValorLiquido'] * 100), 20, '0', STR_PAD_LEFT);
            case 'credor_nota':
                return $r['Cre_RazSoc'] . '|' . $r['Ent_DocNumero'] . '|' . substr((string) $r['TitaPag_DataVencimento'], 0, 10);
            case 'valor':
                return str_pad((string) (int) ((float) $r['TitaPag_ValorLiquido'] * 100), 20, '0', STR_PAD_LEFT)
                    . '|' . substr((string) $r['TitaPag_DataVencimento'], 0, 10);
            case 'centro':
                return $r['Centro_Nome'] . '|' . substr((string) $r['TitaPag_DataVencimento'], 0, 10);
            case 'usuario':
                return trim((string) ($r['D_Usuario_Instrucao'] ?: $r['Usuario_Instrucao'])) . '|'
                    . substr((string) $r['TitaPag_DataVencimento'], 0, 10);
            case 'numero':
                return str_pad((string) (int) $r['TitaPag_Numero'], 15, '0', STR_PAD_LEFT);
            default: // vencimento
                return substr((string) $r['TitaPag_DataVencimento'], 0, 10) . '|' . $r['Cre_RazSoc'];
        }
    };
    usort($rows, fn ($a, $b) => strcmp(
        ($grpKey ? $grpKey($a) . '' : '') . $key($a),
        ($grpKey ? $grpKey($b) . '' : '') . $key($b)));
    return $rows;
}

// Balde de totalização por dia / quinzena / mês sobre a data da agenda.
function ap_balde(string $data, string $modo): string
{
    if ($modo === 'dia') {
        return $data;
    }
    $ym = substr($data, 0, 7);
    if ($modo === 'quinzena') {
        return $ym . '-' . (((int) substr($data, 8, 2)) <= 15 ? 'Q1' : 'Q2');
    }
    return $ym;
}

function ap_balde_rotulo(string $b, string $modo): string
{
    if ($modo === 'dia') {
        return data_br($b);
    }
    $ym = explode('-', $b);
    $mes = $ym[1] . '/' . $ym[0];
    if ($modo === 'quinzena') {
        return ($ym[2] === 'Q1' ? '1ª' : '2ª') . ' quinzena de ' . $mes;
    }
    return $mes;
}

// Soma TitaPag_ValorLiquido por faixa da barra de status + total.
function ap_status(array $rows, string $ref): array
{
    $s = ['pend' => 0.0, 'pred' => 0.0, 'aut' => 0.0, 'venc' => 0.0, 'total' => 0.0];
    foreach ($rows as $r) {
        $v = (float) $r['TitaPag_ValorLiquido'];
        $s['total'] += $v;
        if (trim((string) $r['Pag_Pendente']) === 'S') {
            $s['pend'] += $v;
        }
        if (trim((string) $r['Pag_PreDatado']) === 'S') {
            $s['pred'] += $v;
        }
        if (trim((string) $r['Instrucao_Cod']) === 'AP') {
            $s['aut'] += $v;
        }
        if ($r['TitaPag_Situacao'] === 'A'
            && substr((string) $r['TitaPag_DataVencimentoFluxo'], 0, 10) < $ref) {
            $s['venc'] += $v;
        }
    }
    return $s;
}

// Parcela no formato Informakon: Par/SPar/nParc (igual contas_pagar).
function ap_parcela(array $r): string
{
    $p = str_pad(trim((string) $r['TitaPag_ParNumero']), 3, '0', STR_PAD_LEFT);
    $sp = trim((string) $r['TitaPag_SParNumero']);
    $s = $p . '/' . ($sp !== '' ? str_pad($sp, 3, '0', STR_PAD_LEFT) . '/' : '');
    $n = trim((string) $r['nParc']);
    return $s . $n;
}
