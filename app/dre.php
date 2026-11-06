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

/** Renomeacao APENAS visual (pedido): CV1 -> 'CVC (CV1-DbMga)',
 *  CV2 -> 'CVP (CV2-DbMga)', CV3 -> 'CVJ (CV3-DbMga)'. Sao codigos de
 *  empresas distintas das reais CVC/CVP/CVJ (bancos diferentes) — o
 *  filtro segue usando o Empresa_Cod real, sem unificar. */
const DRE_EMP_ROTULO = [
    'CV1' => 'CVC (CV1-DbMga)',
    'CV2' => 'CVP (CV2-DbMga)',
    'CV3' => 'CVJ (CV3-DbMga)',
];
const DRE_EMP_ALIAS = ['CV1' => 'CVC', 'CV2' => 'CVP', 'CV3' => 'CVJ'];

function dre_emp_alias(string $cod): string
{
    return DRE_EMP_ALIAS[strtoupper(trim($cod))] ?? $cod;
}

function dre_emp_rotulo(string $cod): string
{
    return DRE_EMP_ROTULO[strtoupper(trim($cod))] ?? $cod;
}

/** Bases ERP consultadas — o BI junta DBMGA + DBCONV. */
function dre_bases(): array
{
    return ['MGA' => banco_da_base('MGA'), 'CONV' => banco_da_base('CONV')];
}

/** Empresas ativas (mesma origem do slicer fEmpresa: Vw_Empresa_Ativa),
 *  uma entrada por codigo real, ordenadas pelo rotulo exibido. */
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
    $out = array_map(fn($c) => ['cod' => $c, 'nome' => $nomes[$c] ?? '',
                                'rotulo' => dre_emp_rotulo($c)], $cods);
    usort($out, fn($a, $b) => strcasecmp($a['rotulo'], $b['rotulo'])
                            ?: strcasecmp($a['nome'], $b['nome']));
    return $out;
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
                  'rotulo' => trim($r['nome']) . ' (' . dre_emp_alias($r['emp']) . '·' . $r['fil'] . '·' . $r['ccod'] . ' · ' . $base . ')'];
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

/** Empresas SEM nenhum centro ativo (Vw_Empresa_Ativa - Vw_Centro_Ativo) —
 *  codigos legados de empresas encerradas (CV1/CV2/CV3): tem historico em
 *  Posicao_Economica mas sao excluidos pela view/BI. Selecionadas no filtro,
 *  o DRE busca o historico automaticamente (dre_posicao_historico). */
function dre_empresas_sem_ativos(): array
{
    $ativas = $out = [];
    foreach (query('SELECT DISTINCT RTRIM(Empresa_Cod) e FROM dbo.Vw_Centro_Ativo') as $r) {
        $ativas[strtoupper($r['e'])] = true;
    }
    foreach (query('SELECT DISTINCT RTRIM(Empresa_Cod) e FROM dbo.Vw_Empresa_Ativa') as $r) {
        if (!isset($ativas[strtoupper($r['e'])])) {
            $out[strtoupper($r['e'])] = true;
        }
    }
    return $out;
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

/** Complemento historico: empresas sem centro ativo cujos dados nao existem
 *  mais nas bases vivas (CV1/CV2/CV3 foram purgadas de dbMGA — Empresa,
 *  Centro_de_Custo e movimentos zerados; a procedure retorna 0). O historico
 *  so sobrevive no snapshot CGGestao.dbo.Posicao_Economica, populado pelas
 *  spBI* quando os dados ainda existiam. Acumula em $acc como as demais
 *  fontes; Chave_Centro/Chave_Nucleo sao recriadas como na view
 *  (trim(Database1)+Empresa+Filial+Centro; Database1 vazio -> 'DBMGA'). */
function dre_posicao_historico(array &$acc, array $mapa, array $o, array $alvo): void
{
    if (!$alvo) {
        return;
    }
    $where = 'Ent_DataEntrada >= CAST(? AS date) AND Ent_DataEntrada < DATEADD(day, 1, CAST(? AS date))
              AND Empresa_Cod IN (' . implode(',', array_fill(0, count($alvo), '?')) . ')';
    $par = array_merge([$o['data_ini'], $o['data_fim']], $alvo);
    $cenSel = dre_set((array) ($o['centros'] ?? []));
    $nucSel = dre_set((array) ($o['nucleos'] ?? []));
    foreach (query("SELECT RTRIM(Database1) db1, Empresa_Cod, Filial_Cod, Centro_Cod,
                           Nucleo_Cod,
                           CONVERT(varchar(4000), GO_Titulo) GO_Titulo,
                           CONVERT(varchar(4000), SubGO_Titulo) SubGO_Titulo,
                           RDZ, EntItemApl_Valor, Ent_DataEntrada
                    FROM dbo.Posicao_Economica WHERE $where", $par) as $r) {
        $pfx = strtoupper(trim((string) $r['db1'])) ?: 'DBMGA';
        $cen = $pfx . trim((string) $r['Empresa_Cod']) . trim((string) $r['Filial_Cod'])
             . trim((string) $r['Centro_Cod']);
        $nuc = $pfx . trim((string) $r['Nucleo_Cod']);
        if ($cenSel && !isset($cenSel[$cen])) {
            continue;
        }
        if ($nucSel && !isset($nucSel[$nuc])) {
            continue;
        }
        $d = substr((string) $r['Ent_DataEntrada'], 0, 10);
        dre_acumular($acc, $mapa,
            trim((string) $r['GO_Titulo']), trim((string) $r['SubGO_Titulo']),
            trim((string) $r['RDZ']) === '2D',
            (int) substr($d, 0, 4), (int) substr($d, 5, 2),
            (float) $r['EntItemApl_Valor']);
    }
}

/** Acumula valor na grade cf x GO x SubGO x ano x mes.
 *  1) par GO+SubGO no mapa; 2) senao, Cod do proprio GO;
 *  3) senao, despesa cai no Custo (cf=4) — no BI o Custo absorve TODA a
 *     despesa nao classificada (validado: Custo = total despesas - DespOp);
 *  4) resto -> "Nao classificado no DRE". */
function dre_acumular(array &$acc, array $mapa, string $go, string $sub,
                      bool $despesa, int $ano, int $mes, float $v): void
{
    $cfs = $mapa['par'][$go . '|' . $sub] ?? $mapa['go'][$go] ?? ($despesa ? [4] : [null]);
    foreach ($cfs as $cf) {
        $k = ($cf ?? -1) . "\x1f" . $go . "\x1f" . $sub . "\x1f" . $ano . "\x1f" . $mes;
        $acc[$k] = ($acc[$k] ?? 0.0) + $v;
    }
}

/** $acc (chaves \x1f) -> lista de linhas {cf,go,subgo,ano,mes,valor}. */
function dre_linhas_de_acc(array $acc): array
{
    $linhas = [];
    foreach ($acc as $k => $v) {
        [$cf, $go, $sub, $ano, $mes] = explode("\x1f", $k);
        $linhas[] = ['cf' => (int) $cf < 0 ? null : (int) $cf, 'go' => $go, 'subgo' => $sub,
                     'ano' => (int) $ano, 'mes' => (int) $mes, 'valor' => $v];
    }
    return $linhas;
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
    // empresas sem centro ativo (CV1/CV2/CV3 — historicas purgadas das bases
    // vivas): quando selecionadas, dispensam o filtro Vw_Centro_Ativo e caem
    // no historico de Posicao_Economica; se TODAS as selecionadas forem
    // historicas, as procedures nem rodam (retornariam 0 — INNER JOIN em
    // Centro_de_Custo, que nao tem essas empresas)
    $semAtivos = $empSel ? dre_empresas_sem_ativos() : [];
    $linhasProc = []; // empresas que a procedure devolveu (economico)
    $rodarProc = !$empSel || (bool) array_diff(array_keys($empSel), array_keys($semAtivos));
    $somar = function (string $go, string $sub, string $rdz, int $ano, int $mes, float $v) use (&$acc, $mapa): void {
        // lado vivo: "despesa" = RDZ='2D'
        dre_acumular($acc, $mapa, $go, $sub, $rdz === '2D', $ano, $mes, $v);
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

    foreach ($rodarProc ? dre_bases() : [] as $base => $banco) {
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
                    if ($empSel && !isset($empSel[strtoupper($emp)])) {
                        continue;
                    }
                    $linhasProc[strtoupper($emp)] = true;
                    $nuc = trim((string) $r['Nucleo_Cod']);
                    $chaveC = $tag . $emp . trim((string) $r['Filial_Cod']) . trim((string) $r['Centro_Cod']);
                    // a view so aceita centros de Vw_Centro_Ativo — exceto
                    // empresas "sem ativos" selecionadas com incl_inativas
                    if (!isset($semAtivos[strtoupper($emp)])
                        && !isset($ativos[strtoupper($chaveC) . '|' . $nuc])) {
                        continue;
                    }
                    if ($cenSel && !isset($cenSel[strtoupper($chaveC)])) {
                        continue;
                    }
                    if ($nucSel && !isset($nucSel[strtoupper($tag . $nuc)])) {
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

    // empresa sem centro ativo que a procedure NAO cobriu (dados purgados da
    // base viva — ex.: CV1/CV2/CV3): cai no historico de Posicao_Economica.
    // Se a proc retornou linhas, o dado vivo vale e nada e somado 2x.
    if ($semAtivos) {
        dre_posicao_historico($acc, $mapa, $o,
            array_values(array_diff(array_intersect(array_keys($semAtivos), array_keys($empSel)),
                                    array_keys($linhasProc))));
    }

    return ['linhas' => dre_linhas_de_acc($acc)];
}

/**
 * DRE "arquivada" — MESMA fonte do Power BI: view CGGestao.dbo.Rel_Posicao_
 * Economica, populada 1x/dia pelas spBI* (dados ate o dia anterior). Rapida:
 * um unico SELECT. A view ja aplica o filtro Vw_Centro_Ativo no lado economico
 * e o UNION do lado financeiro ('58 Administracao de Obra').
 * A view nao expoe RDZ: "despesa" = ClasseTipo_Titulo='Despesa operacional'
 * (equivalente ao RDZ='2D' — total bateu ao centavo na validacao).
 */
function dre_dados_arquivada(array $o): array
{
    $mapa   = dre_mapa();
    $empSel = dre_set((array) ($o['empresas'] ?? []));
    $nucSel = dre_set((array) ($o['nucleos'] ?? []));
    $cenSel = dre_set((array) ($o['centros'] ?? []));

    $where = 'Ent_DataEntrada >= CAST(? AS date) AND Ent_DataEntrada < DATEADD(day, 1, CAST(? AS date))';
    $par   = [$o['data_ini'], $o['data_fim']];
    if ($empSel) {
        $where .= ' AND UPPER(RTRIM(Empresa_Cod)) IN (' . implode(',', array_fill(0, count($empSel), '?')) . ')';
        foreach (array_keys($empSel) as $e) {
            $par[] = $e;
        }
    }
    $acc = [];
    foreach (query("SELECT Empresa_Cod, Chave_Centro, Nucleo_Cod,
                           CONVERT(varchar(4000), GO_Titulo) GO_Titulo,
                           CONVERT(varchar(4000), SubGO_Titulo) SubGO_Titulo,
                           CONVERT(varchar(4000), ClasseTipo_Titulo) ClasseTipo_Titulo,
                           EntItemApl_Valor, Ent_DataEntrada
                    FROM dbo.Rel_Posicao_Economica WHERE $where", $par) as $r) {
        // Chave_Centro da view e UPPER ('DBMGACGC010107'/'DBCONVCVC0101');
        // Chave_Nucleo da view e base+filial ('DBMGA01') — para filtrar por
        // nucleo usa-se prefixo da base (DBMGA/DBCONV) + Nucleo_Cod =
        // mesmo formato da Vw_Centro_Ativo.
        $cen = strtoupper(trim((string) $r['Chave_Centro']));
        $pfx = str_starts_with($cen, 'DBMGA') ? 'DBMGA' : 'DBCONV';
        $nuc = $pfx . trim((string) $r['Nucleo_Cod']);
        if ($cenSel && !isset($cenSel[$cen])) {
            continue;
        }
        if ($nucSel && !isset($nucSel[$nuc])) {
            continue;
        }
        $d = substr((string) $r['Ent_DataEntrada'], 0, 10);
        dre_acumular($acc, $mapa,
            trim((string) $r['GO_Titulo']), trim((string) $r['SubGO_Titulo']),
            trim((string) $r['ClasseTipo_Titulo']) === 'Despesa operacional',
            (int) substr($d, 0, 4), (int) substr($d, 5, 2),
            (float) $r['EntItemApl_Valor']);
    }

    // empresas sem centro ativo nao existem na view (ela filtra por
    // Vw_Centro_Ativo) — selecionadas, buscam o historico na tabela
    // Posicao_Economica, que TEM os dados (CV1: 2020-23, CV2/CV3: 2022-24)
    if ($empSel) {
        dre_posicao_historico($acc, $mapa, $o,
            array_values(array_intersect(array_keys(dre_empresas_sem_ativos()),
                                         array_keys($empSel))));
    }
    return ['linhas' => dre_linhas_de_acc($acc)];
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
