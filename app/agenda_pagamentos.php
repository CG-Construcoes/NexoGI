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
    $exec = 'EXEC dbo.sp_ParcelasAgendaPagto'
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

    $banco = banco_da_base($base);
    $isOdbc = conexao($banco)->getAttribute(PDO::ATTR_DRIVER_NAME) === 'odbc';
    // PDO_ODBC (servidor sem pdo_sqlsrv) trunca o fetch das colunas char(n)
    // com acentos — SQLSTATE 01004. O INSERT...EXEC roda dentro do SQL
    // Server (sem driver no meio) e o SELECT sai como varchar(4000).
    $sql = 'SET NOCOUNT ON; ' . ($isOdbc
        ? AP_CREATE_TABLE . ";\nINSERT INTO #ap\n" . $exec . ";\nSELECT * FROM #ap;"
        : $exec);

    $rows = query($sql, [], $banco);

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
    $s = ['pend' => 0.0, 'pred' => 0.0, 'aut' => 0.0, 'venc' => 0.0,
          'total' => 0.0, 'ep' => 0.0, 'ed' => 0.0, 'outros' => 0.0];
    foreach ($rows as $r) {
        $v = (float) $r['TitaPag_ValorLiquido'];
        $s['total'] += $v;
        $tipo = trim((string) $r['EntDocTipo_Cod']);
        if ($tipo === 'EP') { $s['ep'] += $v; }
        elseif ($tipo === 'ED') { $s['ed'] += $v; }
        else { $s['outros'] += $v; }
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

/** As 42 colunas do grid do Informakon (planilha "agenda de pagamentos.xlsx"). */
const AP_COLS = [
    'venc'     => 'Vencimento',
    'credor'   => 'Credor',
    'doc'      => 'Documento',
    'par'      => 'Par',
    'valor'    => 'Valor',
    'agenda'   => 'Agenda',
    'esp'      => 'Esp',
    'ne'       => 'NE',
    'pagtipo'  => 'Pagto Tipo Doc',
    'i'        => 'i',
    'naut'     => 'Nº Aut',
    'ad'       => 'Ad',
    'predat'   => 'PréDatado',
    'obs'      => 'Observação',
    'obsdoc'   => 'Observação do Documento',
    'ficha'    => 'Nº Ficha',
    'emp'      => 'Empresa',
    'empnome'  => 'Nome da Empresa',
    'codcentro' => 'Cód Centro',
    'centro'   => 'Centro de Negócio',
    'natureza' => 'Natureza',
    'atraso'   => 'Atraso',
    'vencorig' => 'VenctoOrig',
    'nneg'     => '#Neg',
    'filial'   => 'Filial',
    'repete'   => '#',
    'enttipo'  => 'EntTipo',
    'autpag'   => 'Aut. Pagto',
    'dtautpag' => 'Data Aut.Pagto',
    'autped'   => 'Aut. Pedido',
    'autdoc'   => 'Aut. Doc.',
    'idboleto' => 'Nº Id. Boleto',
    'contaprinc' => 'Conta Principal',
    'tipodoc'  => 'Tipo Doc',
    'imovel'   => 'Imóvel (Taxas Condominiais)',
    'cartao'   => 'TitaPag_CartaoCredito',
    'dtpk'     => 'TitaPag_Data_PK',
    'entpk'    => 'Ent_Numero_PK',
    'usucri'   => 'Usuário Criação Doc.',
    'dtcri'    => 'Data Criação Doc.',
    'usualt'   => 'Usuário Ult. Alteração Doc.',
    'dtalt'    => 'Data Ult. Alteração Doc.',
];

/** Colunas alinhadas a direita (numericas). */
const AP_COLS_NUM = ['valor', 'ne', 'i', 'atraso', 'nneg', 'repete', 'ficha'];

// ---------- preferencias por usuario (msoft.UsuarioPreferencia) ----------

function ap_pref(string $usuario, string $chave): ?string
{
    $r = query('SELECT CONVERT(varchar(4000), Valor) Valor
                FROM msoft.UsuarioPreferencia
                WHERE Usuario = ? AND Chave = ?', [$usuario, $chave]);
    return $r[0]['Valor'] ?? null;
}

function ap_salvar_pref(string $usuario, string $chave, ?string $valor): void
{
    execute('IF EXISTS (SELECT 1 FROM msoft.UsuarioPreferencia
                      WHERE Usuario = ? AND Chave = ?)
             UPDATE msoft.UsuarioPreferencia SET Valor = ?
             WHERE Usuario = ? AND Chave = ?
             ELSE
             INSERT INTO msoft.UsuarioPreferencia (Usuario, Chave, Valor)
             VALUES (?, ?, ?)',
        [$usuario, $chave, $valor, $usuario, $chave, $usuario, $chave, $valor]);
}

/**
 * Ordem das colunas salva do usuario (CSV de chaves de AP_COLS).
 * Chaves desconhecidas sao ignoradas; novas entram no fim.
 */
function ap_col_order(string $usuario): array
{
    $salva = ap_pref($usuario, 'agenda-pagamentos.col_order');
    $todas = array_keys(AP_COLS);
    if ($salva === null || trim($salva) === '') {
        return $todas;
    }
    $ord = array_values(array_intersect(
        array_map('trim', explode(',', $salva)), $todas));
    return array_merge($ord, array_diff($todas, $ord));
}

function ap_salvar_col_order(string $usuario, string $order): void
{
    $chaves = array_values(array_intersect(
        array_map('trim', explode(',', $order)), array_keys(AP_COLS)));
    if ($chaves === []) {
        return;
    }
    ap_salvar_pref($usuario, 'agenda-pagamentos.col_order',
        implode(',', $chaves));
}

/** Conteudo HTML de uma celula do grid, por chave de coluna. */
function ap_celula(array $r, string $key): string
{
    $t = fn($v) => e(trim((string) $v));
    $dbr = function ($d) {
        $d = substr((string) $d, 0, 10);
        return $d !== '' && $d !== '1900-01-01' ? e(data_br($d)) : '';
    };
    $n0 = fn($v) => (int) $v > 0 ? (string) (int) $v : '';
    $instr = trim((string) $r['Instrucao_Cod']);
    switch ($key) {
        case 'venc':    return $dbr($r['TitaPag_DataVencimento']);
        case 'credor':  return $t($r['Cre_RazSoc']);
        case 'doc':     return $t($r['Ent_Documento']);
        case 'par':     return e(ap_parcela($r));
        case 'valor':   return number_format((float) $r['TitaPag_ValorLiquido'], 2, ',', '.');
        case 'agenda':  return $dbr($r['TitaPag_DataAgendaFin']);
        case 'esp':     return $t($r['EspTit_Cod']);
        case 'ne':      return $n0($r['TitaPag_NumeroNegociacoes']);
        case 'pagtipo': return $t($r['TitaPag_PagDocTipo']);
        case 'i':       $n = (int) $r['CountAutorizacoes'] ?: (int) $r['Instrucao_Count'];
                        return $n > 0 ? (string) $n : '';
        case 'naut':    return $instr === 'AP'
                            ? '<span class="ap-badge ap-badge-ap">AP</span>'
                            : ($instr !== '' ? e($instr) : '');
        case 'ad':      return trim((string) $r['Adi_EmAberto']) === 'S'
                            ? '<span class="ap-badge ap-badge-ad">A</span>' : '';
        case 'predat':  return trim((string) $r['Pag_PreDatado']) === 'S' ? 'S' : '';
        case 'obs':     return $t($r['TitaPag_Obs']);
        case 'obsdoc':  return $t($r['Ent_Observacao']);
        case 'ficha':   return $t($r['Ficha_Numero']);
        case 'emp':     return $t($r['Empresa_Cod']);
        case 'empnome': return $t($r['Empresa_Nome']);
        case 'codcentro': return $t($r['Centro_CodC']);
        case 'centro':  return $t($r['Centro_Nome']);
        case 'natureza': return $t($r['Ent_Natureza']);
        case 'atraso':  return $n0($r['DiasemAtraso']);
        case 'vencorig': return $dbr($r['TitaPag_DataVencimentoOrig']);
        case 'nneg':    return $n0($r['NumeroNegociacoes']);
        case 'filial':  return $t($r['Filial_Cod']);
        case 'repete':  return $n0($r['repete']);
        case 'enttipo': return $t($r['Ent_Tipo']);
        case 'autpag':  return $t($r['Usuario_Instrucao'] ?: $r['D_Usuario_Instrucao']);
        case 'dtautpag': return $dbr($r['Instrucao_DataPagamento'] ?: $r['Instrucao_Data']);
        case 'autped':  return $t($r['Aut_Pedido']);
        case 'autdoc':  return $t($r['DocDig_Numero']);
        case 'idboleto': return $t($r['DadosPg_NumeroIdentificacao']);
        case 'contaprinc': return $t($r['ContaPrincipal']);
        case 'tipodoc': return $t($r['EntDocTipo_Cod']);
        case 'imovel':  return $t($r['Imovel'] ?: $r['Imovel_TC']);
        case 'cartao':  return $t($r['TitaPag_CartaoCredito']);
        case 'dtpk':    return $dbr($r['TitaPag_Data_PK']);
        case 'entpk':   return $t($r['Ent_Numero_PK']);
        case 'usucri':  return $t($r['Audit_Insert_UserName']);
        case 'dtcri':   return $dbr($r['Audit_Insert_Date']);
        case 'usualt':  return $t($r['Audit_UserName']);
        case 'dtalt':   return $dbr($r['Audit_LastChange']);
        default:        return '';
    }
}
