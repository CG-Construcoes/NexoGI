<?php
declare(strict_types=1);

/*
 * DRE — Demonstrativo do Resultado do Exercicio
 * (Financeiro > Fluxo de Caixa > DRE por Ano / DRE Mensal)
 *
 * Replica as abas "DRE Ano" e "DRE Mes" do Power BI. La os dados vem da view
 * CGGestao.dbo.Rel_Posicao_Economica, alimentada offline por copias "spBI" das
 * procedures. Aqui o relatorio e gerado AO VIVO pelas procedures originais:
 *   - spMovEconomicoDetalhe      (sem @Utilizacao -> SELECT * = mesmo detalhe
 *                                que a versao BI grava em Posicao_Economica)
 *   - spMovFinLancamentosDetalhe (@Utilizacao='L' = mesmo detalhe que a versao
 *                                BI grava em PosicaoFinanceira)
 * executadas nas bases dbMGA e dbConv (o BI junta as duas).
 *
 * Regras replicadas da view Rel_Posicao_Economica:
 *   - lado economico: apenas linhas cujo (Empresa+Filial+Centro+Nucleo) existe
 *     em CGGestao.dbo.Vw_Centro_Ativo;
 *   - lado financeiro: apenas Assunto_Titulo='58 Administracao de Obra'
 *     (filtro literal da view — hoje nao retorna linhas porque o assunto gravado
 *     e 'USU58 Administracao de Obra'; mantido exato para fidelidade).
 *
 * Classificacao DRE (validada contra o BI no filtro CGC/01/0107/2026):
 *   1) par (GO_Titulo, SubGO_Titulo) presente em DRE_GR_CL -> seu(s)
 *      Cod_Formato (m:n: '0007 Juros de Contrato' conta nos Cod 6 e 10);
 *   2) par ausente mas GO presente no mapa -> Cod do GO;
 *   3) sem nenhum mapa e linha de DESPESA (RDZ='2D') -> cai no Custo (Cod 4):
 *      no BI o Custo absorve toda despesa nao classificada (Custo = total de
 *      despesas - DespOp, conferido ao centavo);
 *   4) demais -> grupo "Nao classificado no DRE" (fora do Lucro Liquido).
 * CGGestao.dbo.FormatoDRE traz ordem/rotulo; linhas SubTotal=1 acumulam os
 * niveis anteriores; "(=) Lucro Liquido" soma tudo mapeado.
 */

const DRE_ASSUNTO_FIN = '58 Administração de Obra';

/** Bases ERP consultadas — o BI junta DBMGA + DBCONV. */
function dre_bases(): array
{
    return ['MGA' => banco_da_base('MGA'), 'CONV' => banco_da_base('CONV')];
}

/** Empresas ativas (mesma origem do slicer fEmpresa: Vw_Empresa_Ativa). */
function dre_empresas(): array
{
    $cods = [];
    foreach (query('SELECT DISTINCT RTRIM(Empresa_Cod) AS cod FROM dbo.Vw_Empresa_Ativa') as $r) {
        $cods[] = $r['cod'];
    }
    $nomes = [];
    foreach (dre_bases() as $banco) {
        try {
            foreach (query('SELECT RTRIM(Empresa_Cod) c, CONVERT(varchar(4000), Empresa_Nome) n
                            FROM dbo.Empresa', [], $banco) as $r) {
                $nomes[$r['c']] ??= trim((string) $r['n']);
            }
        } catch (Throwable $t) { /* base indisponivel: segue sem o nome */ }
    }
    sort($cods);
    return array_map(fn($c) => ['cod' => $c, 'nome' => $nomes[$c] ?? ''], $cods);
}

/** Nucleos do slicer (Vw_Centro_Ativo.Nucleo_Negocio). Chave inclui a base.
 *  'emps' = empresas com centros no nucleo (filtra a lista por empresa no form). */
function dre_nucleos(): array
{
    $out = [];
    foreach (query('SELECT RTRIM(Chave_Nucleo) chave,
                           CONVERT(varchar(4000), Nucleo_Negocio) rotulo,
                           RTRIM(Empresa_Cod) emp
                    FROM dbo.Vw_Centro_Ativo
                    GROUP BY Chave_Nucleo, Nucleo_Negocio, Empresa_Cod
                    ORDER BY rotulo') as $r) {
        $k = $r['chave'];
        if (!isset($out[$k])) {
            $base = stripos($k, 'dbmga') === 0 ? 'MGA' : 'CONV';
            $out[$k] = ['chave' => $k, 'rotulo' => trim($r['rotulo']) . ' · ' . $base, 'emps' => []];
        }
        $out[$k]['emps'][$r['emp']] = true;
    }
    foreach ($out as &$n) {
        $n['emps'] = implode(' ', array_keys($n['emps']));
    }
    return array_values($out);
}

/** Centros ativos do slicer "Obra" (Vw_Centro_Ativo). */
function dre_centros(): array
{
    $out = [];
    foreach (query('SELECT RTRIM(Chave_Centro) chave, RTRIM(Empresa_Cod) emp,
                           RTRIM(Filial_Cod) fil, RTRIM(Centro_Cod) ccod,
                           CONVERT(varchar(4000), Centro_Nome) nome
                    FROM dbo.Vw_Centro_Ativo ORDER BY nome') as $r) {
        $base = stripos($r['chave'], 'dbmga') === 0 ? 'MGA' : 'CONV';
        $out[] = ['chave' => $r['chave'], 'emp' => $r['emp'],
                  'rotulo' => trim($r['nome']) . ' (' . $r['emp'] . '·' . $r['fil'] . '·' . $r['ccod'] . ' · ' . $base . ')'];
    }
    return $out;
}

/** Mapa de classificacao DRE (DRE_GR_CL):
 *  'par' => (GO_Titulo|SubGO_Titulo) => [Cod_Formato,...]
 *  'go'  => GO_Titulo => [Cod_Formato,...]   (fallback quando o par nao existe —
 *  validado contra o BI: DespOp fecha ao centavo com o fallback por GO). */
function dre_mapa(): array
{
    $par = $go = [];
    foreach (query('SELECT RTRIM(GO_Titulo) g, RTRIM(SubGO_Titulo) s, Cod_Formato cf
                    FROM dbo.DRE_GR_CL') as $r) {
        $par[$r['g'] . '|' . $r['s']][(int) $r['cf']] = true;
        $go[$r['g']][(int) $r['cf']] = true;
    }
    return ['par' => array_map('array_keys', $par), 'go' => array_map('array_keys', $go)];
}

/** Linhas do formato DRE, na ordem do Nivel. */
function dre_formato(): array
{
    return query('SELECT Nivel, RTRIM(DRE) DRE, SubTotal, Cod_Formato
                  FROM dbo.FormatoDRE ORDER BY Nivel');
}

/** Conjunto "CENTRO|NUCLEO" aceitos pela view (Vw_Centro_Ativo). */
function dre_centros_ativos_set(): array
{
    $s = [];
    foreach (query('SELECT Chave_Centro, Nucleo_Cod FROM dbo.Vw_Centro_Ativo') as $r) {
        $s[strtoupper(trim((string) $r['Chave_Centro'])) . '|' . trim((string) $r['Nucleo_Cod'])] = true;
    }
    return $s;
}

function dre_set(array $vals): array
{
    $s = [];
    foreach ($vals as $v) {
        $v = strtoupper(trim((string) $v));
        if ($v !== '') {
            $s[$v] = true;
        }
    }
    return $s;
}

/**
 * Executa as duas procedures nas duas bases e agrega por
 * Cod_Formato x GO x SubGO x ano x mes.
 * Retorna ['linhas' => [ {cf,go,subgo,ano,mes,valor} ... ]].
 */
function dre_dados(array $o): array
{
    $mapa   = dre_mapa();
    $ativos = dre_centros_ativos_set();
    $empSel = dre_set((array) ($o['empresas'] ?? []));
    $nucSel = dre_set((array) ($o['nucleos'] ?? []));
    $cenSel = dre_set((array) ($o['centros'] ?? []));

    $acc = [];
    $somar = function (string $go, string $sub, string $rdz, int $ano, int $mes, float $v) use (&$acc, $mapa): void {
        // 1) par GO+SubGO no mapa; 2) senao, Cod do proprio GO;
        // 3) senao, despesa (RDZ='2D') cai no Custo (cf=4) — no BI o Custo
        //    absorve TODA a despesa nao classificada (validado: Custo =
        //    total de despesas - DespOp, ao centavo);
        // 4) resto -> "Nao classificado no DRE".
        $cfs = $mapa['par'][$go . '|' . $sub] ?? $mapa['go'][$go] ?? ($rdz === '2D' ? [4] : [null]);
        foreach ($cfs as $cf) {
            $k = ($cf ?? -1) . "\x1f" . $go . "\x1f" . $sub . "\x1f" . $ano . "\x1f" . $mes;
            $acc[$k] = ($acc[$k] ?? 0.0) + $v;
        }
    };
    $filtro = function (string $emp, string $chaveC, string $nuc, string $tag) use ($empSel, $cenSel, $nucSel): bool {
        if ($empSel && !isset($empSel[strtoupper($emp)])) {
            return false;
        }
        if ($cenSel && !isset($cenSel[strtoupper($chaveC)])) {
            return false;
        }
        if ($nucSel && !isset($nucSel[strtoupper($tag . $nuc)])) {
            return false;
        }
        return true;
    };

    foreach (dre_bases() as $base => $banco) {
        $tag = 'Db' . ucfirst(strtolower($base)); // prefixo das chaves: DbMga / DbConv
        $pdo = conexao($banco);

        // ---- lado economico (Posicao_Economica) ----
        $st = $pdo->prepare("EXEC spMovEconomicoDetalhe @Empresa_Cod='(*)', @DataInicial=?, @DataFinal=?,
                             @Relatorio='S', @Detalha_Centro='S', @Medicao='N'");
        $st->execute([$o['data_ini'], $o['data_fim']]);
        while (true) {
            if ($st->columnCount() > 0) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $emp = trim((string) $r['Empresa_Cod']);
                    $nuc = trim((string) $r['Nucleo_Cod']);
                    $chaveC = $tag . $emp . trim((string) $r['Filial_Cod']) . trim((string) $r['Centro_Cod']);
                    if (!isset($ativos[strtoupper($chaveC) . '|' . $nuc])) {
                        continue; // a view so aceita centros presentes em Vw_Centro_Ativo
                    }
                    if (!$filtro($emp, $chaveC, $nuc, $tag)) {
                        continue;
                    }
                    $d = substr((string) $r['Ent_DataEntrada'], 0, 10);
                    $somar(trim((string) $r['GO_Titulo']), trim((string) $r['SubGO_Titulo']),
                           trim((string) $r['RDZ']),
                           (int) substr($d, 0, 4), (int) substr($d, 5, 2),
                           (float) $r['EntItemApl_Valor']);
                }
            }
            if (!$st->nextRowset()) {
                break;
            }
        }
        $st->closeCursor();

        // ---- lado financeiro (PosicaoFinanceira): so '58 Administracao de Obra' ----
        $st = $pdo->prepare("EXEC spMovFinLancamentosDetalhe @Empresa_Cod='(*)', @DataInicial=?, @DataFinal=?,
                             @ClaIns='N', @APagRec='N', @Utilizacao='L', @Relatorio='S', @Detalha_Centro='S'");
        $st->execute([$o['data_ini'], $o['data_fim']]);
        while (true) {
            if ($st->columnCount() > 0) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    if (trim((string) $r['Assunto_Titulo']) !== DRE_ASSUNTO_FIN) {
                        continue;
                    }
                    $emp = trim((string) $r['Empresa_Cod']);
                    $nuc = trim((string) $r['Nucleo_Cod']);
                    $chaveC = $tag . $emp . trim((string) $r['Filial_Cod']) . trim((string) $r['Centro_Cod']);
                    if (!$filtro($emp, $chaveC, $nuc, $tag)) {
                        continue;
                    }
                    $d = substr((string) $r['MovFin_Data'], 0, 10);
                    $somar(trim((string) $r['GO_Titulo']), trim((string) $r['SubGO_Titulo']),
                           trim((string) $r['RDZ']),
                           (int) substr($d, 0, 4), (int) substr($d, 5, 2),
                           (float) $r['MovFinItem_ValorOrig']);
                }
            }
            if (!$st->nextRowset()) {
                break;
            }
        }
        $st->closeCursor();
    }

    $linhas = [];
    foreach ($acc as $k => $v) {
        [$cf, $go, $sub, $ano, $mes] = explode("\x1f", $k);
        $linhas[] = ['cf' => (int) $cf < 0 ? null : (int) $cf, 'go' => $go, 'subgo' => $sub,
                     'ano' => (int) $ano, 'mes' => (int) $mes, 'valor' => $v];
    }
    return ['linhas' => $linhas];
}

/**
 * Pivota as linhas: 'ano' => coluna por ano; 'mes' => coluna por mes.
 * Retorna porCf[cf][col], porGo[cf][go][col], porSub[cf][go][sub][col],
 * semCf[go|sub][col].
 */
function dre_pivot(array $linhas, string $modo): array
{
    $porCf = $porGo = $porSub = $semCf = [];
    foreach ($linhas as $l) {
        $c = $modo === 'ano' ? (string) $l['ano'] : sprintf('%02d', $l['mes']);
        if ($l['cf'] === null) {
            $semCf[$l['go'] . '|' . $l['subgo']][$c] = ($semCf[$l['go'] . '|' . $l['subgo']][$c] ?? 0.0) + $l['valor'];
            continue;
        }
        $cf = $l['cf'];
        $porCf[$cf][$c] = ($porCf[$cf][$c] ?? 0.0) + $l['valor'];
        $porGo[$cf][$l['go']][$c] = ($porGo[$cf][$l['go']][$c] ?? 0.0) + $l['valor'];
        $porSub[$cf][$l['go']][$l['subgo']][$c] = ($porSub[$cf][$l['go']][$l['subgo']][$c] ?? 0.0) + $l['valor'];
    }
    return ['porCf' => $porCf, 'porGo' => $porGo, 'porSub' => $porSub, 'semCf' => $semCf];
}
