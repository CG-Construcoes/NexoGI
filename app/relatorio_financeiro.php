<?php
declare(strict_types=1);

/*
 * Relatorio Financeiro (Financeiro > Relatorios) — replica o modelo do ERP
 * alimentado pela spMovFinLancamentosDetalhe. A procedure e chamada DUAS vezes:
 *   @Utilizacao='J' -> detalhe dos lancamentos (Classe/Assunto/GO + MovFin_*)
 *   @Utilizacao='5' -> Resumo por Agente Financeiro (Tipo/Agente/Conta/Valor)
 * Secoes pelo campo RDZ do detalhe: '1R' Receitas, '2D' Despesas,
 * '3S' Recursos em Sociedade (classes 90/91), '4Z' e demais = Outros.
 * SALDO = Receitas + Despesas (linhas com SALDO='1').
 */

const REL_FIN_TIPOS = [
    'receitas'  => 'Receitas',
    'despesas'  => 'Despesas',
    'sociedade' => 'Recursos em Sociedade',
    'outros'    => 'Outros Grupos',
];

function relatorio_financeiro_centros(string $base, bool $incluirInativos = false): array
{
    $where = $incluirInativos ? '' : "WHERE ISNULL(RTRIM(Centro_Inativo), 'N') <> 'S'";
    return query(
        "SELECT Centro_Cod, CONVERT(varchar(4000), Centro_Nome) Centro_Nome
         FROM dbo.Centro_de_Custo $where ORDER BY Centro_Nome",
        [], banco_da_base($base));
}

function relatorio_financeiro_nucleos(string $base): array
{
    try {
        return query(
            "SELECT Nucleo_Cod, CONVERT(varchar(4000), Nucleo_Nome) Nucleo_Nome
             FROM dbo.Nucleo_de_Negocio ORDER BY Nucleo_Nome",
            [], banco_da_base($base));
    } catch (Throwable $t) {
        return [];
    }
}

function relatorio_financeiro_empresas(string $base): array
{
    try {
        return query(
            "SELECT Empresa_Cod, CONVERT(varchar(4000), Empresa_Nome) Empresa_Nome
             FROM dbo.Empresa ORDER BY Empresa_Cod",
            [], banco_da_base($base));
    } catch (Throwable $t) {
        return [['Empresa_Cod' => 'CGC', 'Empresa_Nome' => 'C. G. Construcoes']];
    }
}

/** Secao da linha do detalhe pelo RDZ ('1R' Receitas, '2D' Despesas, '3S' Sociedade, resto Outros). */
function rf_secao(string $rdz): string
{
    return match (substr(trim($rdz), 0, 1)) {
        '1' => 'receitas',
        '2' => 'despesas',
        '3' => 'sociedade',
        default => 'outros',
    };
}

function relatorio_financeiro_dados(string $base, array $o): array
{
    $banco = banco_da_base($base);
    // 'FF.CCCC' = filial+centro (fluxo-caixa); 'CCCC' ou 'CCCC - nome' = so centro
    $centrosSel = [];
    foreach ((array) ($o['centros'] ?? []) as $c) {
        $c = (string) $c;
        if (preg_match('/^(\d{2})\.(\d{4})\b/', $c, $m)) {
            $centrosSel[] = [$m[1], $m[2]];
        } elseif (preg_match('/^([0-9A-Za-z]{4})\b/', $c, $m)) {
            $centrosSel[] = [null, $m[1]];
        }
    }
    $nucleosSel = array_values(array_filter(array_map(
        fn($c) => preg_match('/^([0-9A-Za-z]{2})\b/', (string) $c, $m) ? $m[1] : '',
        (array) ($o['nucleos'] ?? []))));
    $empresa = trim((string) $o['empresa']);
    if ($empresa === '') {
        throw new InvalidArgumentException('Selecione a empresa.');
    }

    $apenasPeriodo = !empty($o['apenas_periodo']);
    $iniTotal = $apenasPeriodo || $o['data_ini'] === '' ? ($o['data_ini'] !== '' ? $o['data_ini'] : '1900-01-01')
                                                       : '1900-01-01';
    $fim   = $o['data_fim'] !== '' ? $o['data_fim'] : '2050-12-31';
    $corte = $o['data_ini'] !== '' ? $o['data_ini'] : '1900-01-01';
    $detCentro = !empty($o['det_centro']) ? 'S' : 'N';

    $sql = "EXEC spMovFinLancamentosDetalhe
            @Empresa_Cod=?, @DataInicial=?, @DataFinal=?, @ClaIns='N', @APagRec='N',
            @Utilizacao=?, @Filial_Cod=?, @Centro_Cod=?, @Nucleo_Cod=?,
            @Relatorio='S', @Detalha_Centro=?";
    $pdo = conexao($banco);
    $st = $pdo->prepare($sql);

    // detalhe dos lancamentos ('J'): quando ha no maximo 1 centro e 1 nucleo,
    // filtra direto na procedure (1 exec). Com multiplos centros/nucleos, faz
    // 1 unica execucao com @Detalha_Centro='S' e filtra em PHP (evita N x M
    // chamadas da proc).
    $multiFiltro = count($centrosSel) > 1 || count($nucleosSel) > 1;
    $detProc = ($detCentro === 'S' || $multiFiltro) ? 'S' : 'N';
    [$filPar, $centroPar] = !$multiFiltro && $centrosSel ? $centrosSel[0] : [null, null];
    $nucleoPar = !$multiFiltro && $nucleosSel ? $nucleosSel[0] : null;
    $setNucleos = $multiFiltro && $nucleosSel ? array_flip($nucleosSel) : null;
    $aceitaCentro = null;
    if ($multiFiltro && $centrosSel) {
        $setFilCen = [];
        $setCen = [];
        foreach ($centrosSel as [$fc, $cc]) {
            if ($fc !== null) {
                $setFilCen[$fc . '.' . $cc] = true;
            } else {
                $setCen[$cc] = true;
            }
        }
        $aceitaCentro = function (string $fil, string $cc) use ($setFilCen, $setCen): bool {
            return isset($setCen[$cc]) || isset($setFilCen[$fil . '.' . $cc]);
        };
    }

    $classes = [];
    $agentes = [];
    db_abortar_se_desconectado();
    $st->execute([$empresa, $iniTotal, $fim, 'J', $filPar, $centroPar, $nucleoPar, $detProc]);
    while (true) {
        if ($st->columnCount() > 0) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $cla = trim((string) $r['Classe_Cod']);
                if ($cla === '') continue;
                if ($aceitaCentro !== null
                    && !$aceitaCentro(trim((string) ($r['Filial_Cod'] ?? '')),
                                      trim((string) $r['Centro_Cod']))) {
                    continue;
                }
                if ($setNucleos !== null && !isset($setNucleos[trim((string) $r['Nucleo_Cod'])])) {
                    continue;
                }
                $ass = trim((string) $r['Assunto_Cod']);
                $go  = trim((string) $r['GO_Cod']);
                $valor = (float) $r['MovFinItem_ValorOrig'];
                $ant = substr((string) $r['MovFin_Data'], 0, 10) < $corte;
                $cn  = $detCentro === 'S' ? trim((string) $r['Centro_Cod']) . ' ' . trim((string) $r['Centro_Nome'])
                                          : '';
                $secao = rf_secao((string) $r['RDZ']);
                if (!isset($classes[$cn][$cla])) {
                    $classes[$cn][$cla] = ['titulo' => trim((string) $r['Classe_Titulo']),
                        'secao' => $secao, 'assuntos' => []];
                }
                if (!isset($classes[$cn][$cla]['assuntos'][$ass])) {
                    $classes[$cn][$cla]['assuntos'][$ass] = [
                        'cod_ass' => $ass,
                        'titulo' => trim((string) $r['Assunto_Titulo']),
                        'ant' => 0.0, 'per' => 0.0, 'gos' => []];
                }
                if (!isset($classes[$cn][$cla]['assuntos'][$ass]['gos'][$go])) {
                    $goTitulo = trim((string) $r['GO_Titulo']);
                    $goTitulo = trim((string) preg_replace('/^' . preg_quote($go, '/') . '\s*/', '', $goTitulo));
                    $classes[$cn][$cla]['assuntos'][$ass]['gos'][$go] = [
                        'cod' => $go,
                        'titulo' => $goTitulo !== '' ? $goTitulo : trim((string) $r['GO_Titulo']),
                        'ant' => 0.0, 'per' => 0.0, 'movs' => []];
                }
                $classes[$cn][$cla]['assuntos'][$ass]['gos'][$go][$ant ? 'ant' : 'per'] += $valor;
                $classes[$cn][$cla]['assuntos'][$ass]['gos'][$go]['movs'][] = [
                    'data' => substr((string) $r['MovFin_Data'], 0, 10),
                    'num' => trim((string) $r['MovFin_Numero']),
                    'hist' => trim((string) $r['MovFin_Historico']),
                    'valor' => $valor, 'ant' => $ant];
                $classes[$cn][$cla]['assuntos'][$ass][$ant ? 'ant' : 'per'] += $valor;
            }
        }
        if (!$st->nextRowset()) break;
    }
    $st->closeCursor();

    // '5' = resumo por agente financeiro (so executa quando solicitado)
    if (!empty($o['resumo_agente'])) {
        foreach (($centrosSel ?: [null]) as $centroSel) {
            [$filCod, $centroCod] = $centroSel ?? [null, null];
            foreach (($nucleosSel ?: [null]) as $nucleoCod) {
                db_abortar_se_desconectado();
                $st->execute([$empresa, $iniTotal, $fim, '5', $filCod, $centroCod, $nucleoCod, $detCentro]);
                while (true) {
                    if ($st->columnCount() > 0) {
                        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                            $tipo  = trim((string) $r['AgenteFinanceiroTipo_Cod']);
                            $ag    = trim((string) $r['AgenteFinanceiro_Cod']);
                            $conta = trim((string) $r['AgeFin_ContaNumero']);
                            if ($tipo === '' && $ag === '') continue;
                            $chave = $tipo . '|' . $ag . '|' . $conta;
                            if (!isset($agentes[$chave])) {
                                $agentes[$chave] = ['tipo' => $tipo, 'agente' => $ag, 'conta' => $conta, 'valor' => 0.0];
                            }
                            $agentes[$chave]['valor'] += (float) $r['ValorOrig'];
                        }
                    }
                    if (!$st->nextRowset()) break;
                }
                $st->closeCursor();
            }
        }
    }

    // monta a saida: um relatorio por centro (ou um so)
    $secoesSel = array_keys(array_filter([
        'receitas' => !empty($o['sec_receitas']), 'despesas' => !empty($o['sec_despesas']),
        'sociedade' => !empty($o['sec_sociedade']), 'outros' => !empty($o['sec_outros'])]));

    $blocos = [];
    foreach ($classes as $centroNome => $cls) {
        ksort($cls);
        $secoes = [];
        foreach (REL_FIN_TIPOS as $id => $titulo) {
            if (!in_array($id, $secoesSel, true)) continue;
            $sec = ['id' => $id, 'titulo' => $titulo, 'classes' => [], 'ant' => 0.0, 'per' => 0.0];
            foreach ($cls as $cla => $c) {
                if ($c['secao'] !== $id) continue;
                ksort($c['assuntos']);
                $c['assuntos'] = array_values(array_filter($c['assuntos'],
                    fn($a) => $a['ant'] != 0.0 || $a['per'] != 0.0));
                foreach ($c['assuntos'] as $i => $a) {
                    ksort($a['gos']);
                    $c['assuntos'][$i]['cod'] = $cla . '.' . $a['cod_ass'];
                    $c['assuntos'][$i]['gos'] = array_values(array_filter($a['gos'],
                        fn($g) => $g['ant'] != 0.0 || $g['per'] != 0.0));
                }
                if (!$c['assuntos']) continue;
                $ant = array_sum(array_column($c['assuntos'], 'ant'));
                $per = array_sum(array_column($c['assuntos'], 'per'));
                $sec['classes'][] = ['cod' => $cla, 'titulo' => $c['titulo'],
                    'assuntos' => $c['assuntos'], 'ant' => $ant, 'per' => $per];
                $sec['ant'] += $ant;
                $sec['per'] += $per;
            }
            $secoes[] = $sec;
        }
        $blocos[$centroNome] = $secoes;
    }

    usort($agentes, fn($a, $b) => [$a['tipo'], $a['agente'], $a['conta']] <=> [$b['tipo'], $b['agente'], $b['conta']]);

    // cabecalho Informakon: empresa + CNPJ da filial matriz (menor Filial_CGC)
    $empresaNome = (string) $o['empresa'];
    $cnpj = '';
    try {
        $emp = query(
            "SELECT TOP 1 CONVERT(varchar(4000), e.Empresa_Nome) Empresa_Nome, f.Filial_CGC
             FROM dbo.Empresa e
             LEFT JOIN dbo.Filial f ON f.Empresa_Cod = e.Empresa_Cod
             WHERE e.Empresa_Cod = ? ORDER BY f.Filial_CGC",
            [$o['empresa']], $banco);
        if ($emp) {
            $empresaNome = trim((string) $emp[0]['Empresa_Nome']);
            $d = preg_replace('/\D/', '', (string) ($emp[0]['Filial_CGC'] ?? ''));
            if (strlen($d) === 14) {
                $cnpj = substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3)
                    . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
            }
        }
    } catch (Throwable $t) { /* mantem codigo */ }

    $centrosDesc = 'Todos';
    $centroMascara = '(*)';
    if ($centrosSel) {
        $descs = array_map('trim', (array) ($o['centros'] ?? []));
        $centrosDesc = count($descs) === 1 ? $descs[0] : count($descs) . ' centros selecionados';
        $centroMascara = '(' . implode(',', $descs) . ')';
    }
    $nucleosDesc = 'Todos';
    $nucleoMascara = '99.9999';
    if ($nucleosSel) {
        $descs = array_map('trim', (array) ($o['nucleos'] ?? []));
        $nucleosDesc = count($descs) === 1 ? $descs[0] : count($descs) . ' nucleos selecionados';
        $nucleoMascara = implode(',', $descs);
    }
    return ['blocos' => $blocos, 'base' => $base, 'data_ini' => $o['data_ini'],
            'data_fim' => $fim, 'apenas_periodo' => $apenasPeriodo,
            'empresa_nome' => $empresaNome, 'empresa_cnpj' => $cnpj,
            'filtro_mascara' => $centroMascara . '.' . $nucleoMascara,
            'centros_desc' => $centrosDesc, 'centros_lista' => (array) ($o['centros'] ?? []),
            'nucleos_desc' => $nucleosDesc, 'nucleos_lista' => (array) ($o['nucleos'] ?? []),
            'agentes' => $agentes,
            'mostrar_saldo' => !empty($o['sec_receitas']) && !empty($o['sec_despesas'])];
}
