<?php
declare(strict_types=1);

/*
 * Fluxo de Caixa — listagem de grupos com saldo (Financeiro > Fluxo de Caixa).
 * msoft.GruposSaldos: FCGrupo_Id (PK/FK), UltimaAtualizacao, SaldoGrupo —
 * preenchida pelo botao "Atualizar" da tela: para os grupos marcados,
 * executa o calculo do Relatorio Financeiro (spMovFinLancamentosDetalhe)
 * nos centros do grupo, no periodo informado, e grava a linha "SALDO"
 * (Receitas + Despesas do periodo) em SaldoGrupo + a hora em
 * UltimaAtualizacao.
 * msoft.GrupoCentro_Avulso: itens "Valor Avulso" do grupo — o valor e
 * digitado na hora do calculo e entra na soma do SaldoGrupo.
 */

/** Normaliza valor digitado ("1.234,56" ou "1234.56", aceita negativo). */
function fcs_valor($v): float
{
    $s = trim((string) $v);
    if (strpos($s, ',') !== false) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    }
    return (float) $s;
}

/**
 * Grupos cadastrados + quantidade de centros + saldo/ultima atualizacao
 * gravados em msoft.GruposSaldos (quando a linha ainda nao existe, NULL).
 */
function fcs_listar(): array
{
    return query("SELECT g.FCGrupo_Id, RTRIM(g.FCGrupo_Nome) FCGrupo_Nome,
                         RTRIM(g.FCGrupo_Descricao) FCGrupo_Descricao,
                         (SELECT COUNT(*) FROM msoft.FluxoCaixa_Centros c
                          WHERE c.FCGrupo_Id = g.FCGrupo_Id) QtdeCentros,
                         (SELECT COUNT(*) FROM msoft.GrupoCentro_Avulso a
                          WHERE a.FCGrupo_Id = g.FCGrupo_Id) QtdeAvulsos,
                         s.SaldoGrupo, s.SaldoMes1, s.SaldoMes2, s.SaldoMes3,
                         s.UltimaAtualizacao
                  FROM msoft.FluxoCaixa_Grupo g
                  LEFT JOIN msoft.GruposSaldos s ON s.FCGrupo_Id = g.FCGrupo_Id
                  LEFT JOIN msoft.FluxoCaixa_GrupoOrdem o ON o.FCGrupo_Id = g.FCGrupo_Id
                  ORDER BY ISNULL(o.Ordem, 2147483647), g.FCGrupo_Nome");
}

/**
 * Move o grupo uma posicao para cima/baixo na ordem de exibicao
 * (persistida em msoft.FluxoCaixa_GrupoOrdem; renumera em passos de 10).
 */
function fcs_mover(int $id, string $dir): void
{
    $rows = query('SELECT g.FCGrupo_Id
                   FROM msoft.FluxoCaixa_Grupo g
                   LEFT JOIN msoft.FluxoCaixa_GrupoOrdem o ON o.FCGrupo_Id = g.FCGrupo_Id
                   ORDER BY ISNULL(o.Ordem, 2147483647), g.FCGrupo_Nome');
    $ids = array_map(fn($r) => (int) $r['FCGrupo_Id'], $rows);
    $i = array_search($id, $ids, true);
    if ($i === false) {
        return;
    }
    $j = $dir === 'up' ? $i - 1 : $i + 1;
    if (!isset($ids[$j])) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $pos => $gid) {
        execute('IF EXISTS (SELECT 1 FROM msoft.FluxoCaixa_GrupoOrdem WHERE FCGrupo_Id = ?)
                     UPDATE msoft.FluxoCaixa_GrupoOrdem SET Ordem = ? WHERE FCGrupo_Id = ?
                 ELSE
                     INSERT INTO msoft.FluxoCaixa_GrupoOrdem (FCGrupo_Id, Ordem) VALUES (?, ?)',
                [$gid, ($pos + 1) * 10, $gid, $gid, ($pos + 1) * 10]);
    }
}

/**
 * Reordena todos os grupos pela sequencia de ids recebida (arrastar e
 * soltar). Grupos existentes ausentes da lista vao para o fim, na ordem
 * atual — assim a chamada nunca perde grupo.
 */
function fcs_reordenar(array $ids): void
{
    $atuais = array_map(fn($r) => (int) $r['FCGrupo_Id'],
        query('SELECT FCGrupo_Id FROM msoft.FluxoCaixa_Grupo'));
    $nova = array_values(array_intersect(array_map('intval', $ids), $atuais));
    $nova = array_merge($nova, array_values(array_diff($atuais, $nova)));
    foreach ($nova as $pos => $gid) {
        execute('IF EXISTS (SELECT 1 FROM msoft.FluxoCaixa_GrupoOrdem WHERE FCGrupo_Id = ?)
                     UPDATE msoft.FluxoCaixa_GrupoOrdem SET Ordem = ? WHERE FCGrupo_Id = ?
                 ELSE
                     INSERT INTO msoft.FluxoCaixa_GrupoOrdem (FCGrupo_Id, Ordem) VALUES (?, ?)',
                [$gid, ($pos + 1) * 10, $gid, $gid, ($pos + 1) * 10]);
    }
}

/**
 * FCGrupo_Id => [rotulos dos centros do grupo] — para o tooltip que lista
 * os centros ao passar o mouse sobre o nome do grupo.
 */
function fcs_centros_por_grupo(): array
{
    $labels = fc_centros();
    $map = [];
    foreach (query('SELECT FCGrupo_Id, RTRIM(Centro_CodC) Centro_CodC
                    FROM msoft.FluxoCaixa_Centros ORDER BY Centro_CodC') as $r) {
        $map[(int) $r['FCGrupo_Id']][] = $labels[$r['Centro_CodC']] ?? $r['Centro_CodC'];
    }
    foreach (query('SELECT FCGrupo_Id, Avulso_Id,
                          CONVERT(varchar(4000), Avulso_Descricao) Avulso_Descricao
                    FROM msoft.GrupoCentro_Avulso ORDER BY Avulso_Id') as $r) {
        $map[(int) $r['FCGrupo_Id']][] = 'Avulso: ' . fc_avulso_rotulo($r);
    }
    foreach ($map as &$lista) {
        sort($lista);
    }
    return $map;
}

/** FCGrupo_Id => [['id'=>, 'nome'=>, 'valor'=>float|null], ...] por grupo. */
function fcs_avulsos_por_grupo(): array
{
    $map = [];
    foreach (query('SELECT Avulso_Id, FCGrupo_Id,
                          CONVERT(varchar(4000), Avulso_Descricao) Avulso_Descricao,
                          Avulso_Valor
                    FROM msoft.GrupoCentro_Avulso ORDER BY Avulso_Id') as $r) {
        $map[(int) $r['FCGrupo_Id']][] = ['id' => (int) $r['Avulso_Id'],
            'nome' => fc_avulso_rotulo($r),
            'valor' => $r['Avulso_Valor'] !== null ? (float) $r['Avulso_Valor'] : null];
    }
    return $map;
}

/** Grava os valores avulsos digitados (mapa Avulso_Id => valor). */
function fcs_salvar_avulsos(array $vals): void
{
    foreach ($vals as $aid => $v) {
        execute('UPDATE msoft.GrupoCentro_Avulso SET Avulso_Valor = ?
                 WHERE Avulso_Id = ?', [$v, (int) $aid]);
    }
}

/** CodC => true dos centros existentes em $banco (cache por banco). */
function fcs_codcs_base(string $banco): array
{
    static $cache = [];
    if (!isset($cache[$banco])) {
        $cache[$banco] = [];
        foreach (query("SELECT CONVERT(varchar(4000),
                               RTRIM(Empresa_Cod) + RTRIM(Filial_Cod) + RTRIM(Centro_Cod)) codc
                        FROM $banco.dbo.Centro_de_Custo", [], $banco) as $r) {
            $cache[$banco][strtoupper(trim((string) $r['codc']))] = true;
        }
    }
    return $cache[$banco];
}

/**
 * Bases ERP selecionadas pelos checkboxes DBMGA/DBCONV da tela.
 * Le POST (Atualizar) e GET (impressao). Padrao = as duas; nenhuma
 * marcada tambem cai no padrao (nunca devolve vazio).
 */
function fcs_bases_selecionadas(): array
{
    $src = $_POST + $_GET;
    $b = [];
    if (!empty($src['base_mga']))  { $b[] = 'MGA'; }
    if (!empty($src['base_conv'])) { $b[] = 'CONV'; }
    return $b ?: ['MGA', 'CONV'];
}

/** base => banco, apenas das bases selecionadas na tela. */
function fcs_bases_mapa(): array
{
    $m = ['MGA' => env('DB_ERP_MGA', 'dbMGA'),
          'CONV' => env('DB_ERP_CONV', 'dbConv')];
    return array_intersect_key($m, array_flip(fcs_bases_selecionadas()));
}

/**
 * CodCs que ficam fora do "a receber" com as bases selecionadas:
 * o centro so e excluido quando existe em alguma base ERP e nenhuma
 * das bases onde ele existe esta marcada (CodC sem cadastro em base
 * alguma continua entrando — dado local). null = sem filtro.
 */
function fcs_codcs_fora_das_bases(): ?array
{
    if (count(fcs_bases_selecionadas()) === 2) {
        return null;
    }
    $dentro = $fora = [];
    $sel = fcs_bases_selecionadas();
    foreach (['MGA' => env('DB_ERP_MGA', 'dbMGA'),
              'CONV' => env('DB_ERP_CONV', 'dbConv')] as $base => $banco) {
        foreach (fcs_codcs_base($banco) as $codc => $x) {
            if (in_array($base, $sel, true)) {
                $dentro[$codc] = true;
            } else {
                $fora[$codc] = true;
            }
        }
    }
    return array_diff_key($fora, $dentro);
}

/**
 * base => empresa => [Centro_Cod] dos CodCs do grupo: localiza a(s) base(s)
 * onde cada centro existe (dbMGA e/ou dbConv — empresas CVC/CVJ/CVP existem
 * nas duas) e quebra o CodC (Empresa3+Filial2+Centro4).
 */
function fcs_alvos_grupo(array $codcs): array
{
    $alvo = [];
    foreach (fcs_bases_mapa() as $base => $banco) {
        $existem = fcs_codcs_base($banco);
        foreach ($codcs as $codc) {
            if (strlen($codc) === 9 && isset($existem[strtoupper($codc)])) {
                $alvo[$base][substr($codc, 0, 3)][] = substr($codc, 5, 4);
            }
        }
    }
    return $alvo;
}

/** Opcoes fixas do Relatorio Financeiro usadas pela tela Fluxo de Caixa. */
function fcs_opcoes_relatorio(string $emp, string $ini, string $fim, array $centros): array
{
    return [
        'empresa'        => $emp,
        'data_ini'       => $ini,
        'data_fim'       => $fim,
        'centros'        => array_values(array_unique($centros)),
        'nucleos'        => [],
        'sin_classe'     => true,
        'sin_assunto'    => true,
        'sin_grupo'      => true,
        'sec_receitas'   => true,
        'sec_despesas'   => true,
        'sec_outros'     => true,
        'apenas_periodo' => true,
        'resumo_agente'  => true,
    ];
}

/**
 * SALDO de um retorno de relatorio_financeiro_dados(): soma das secoes
 * Receitas + Despesas da coluna "Periodo" — a mesma formula da linha
 * SALDO exibida pela tela Relatorio Financeiro.
 */
function fcs_saldo_relatorio(array $dados): float
{
    $saldo = 0.0;
    foreach ($dados['blocos'] as $secoes) {
        foreach ($secoes as $s) {
            if ($s['id'] === 'receitas' || $s['id'] === 'despesas') {
                $saldo += $s['per'];
            }
        }
    }
    return $saldo;
}

/**
 * Saldo do grupo no periodo: para cada (base, empresa) dos centros do grupo
 * executa o Relatorio Financeiro e soma a linha SALDO dele
 * (fcs_saldo_relatorio) — mais os Valores Avulsos informados no calculo
 * (mapa Avulso_Id => valor).
 */
function fcs_saldo_grupo(int $grupoId, string $ini, string $fim, array $avulsos = []): float
{
    // Valor digitado prevalece; sem ele, usa o gravado. Avulso sem nenhum
    // dos dois impede o calculo do grupo (a tela marca a linha em vermelho).
    foreach (fc_avulsos_do_grupo($grupoId) as $row) {
        $aid = (int) $row['Avulso_Id'];
        if (!isset($avulsos[$aid])) {
            if ($row['Avulso_Valor'] === null) {
                throw new RuntimeException('Preencha o valor avulso "'
                    . fc_avulso_rotulo($row) . '".');
            }
            $avulsos[$aid] = (float) $row['Avulso_Valor'];
        }
    }
    $saldo = array_sum($avulsos);
    foreach (fcs_alvos_grupo(fc_centros_do_grupo($grupoId)) as $base => $empresas) {
        foreach ($empresas as $emp => $centros) {
            $saldo += fcs_saldo_relatorio(relatorio_financeiro_dados(
                $base, fcs_opcoes_relatorio($emp, $ini, $fim, $centros)));
        }
    }
    return $saldo;
}

/**
 * Dados para impressao dos relatorios financeiros dos grupos:
 * [['grupo'=>nome, 'dados'=>[saida de relatorio_financeiro_dados por
 * (base,empresa)], 'erros'=>[...]]].
 */
function fcs_relatorio_impressao(array $ids, string $ini, string $fim, array $avulsos = []): array
{
    $out = [];
    foreach ($ids as $id) {
        $g = fc_get((int) $id);
        if (!$g) {
            continue;
        }
        $codcs = fc_centros_do_grupo((int) $id);
        $labels = fc_centros();
        $centros = [];
        foreach ($codcs as $codc) {
            $centros[] = $labels[$codc] ?? $codc;
        }
        sort($centros);
        $av = $avulsos[(int) $id] ?? [];
        $lista = [];
        foreach (fc_avulsos_do_grupo((int) $id) as $row) {
            $aid = (int) $row['Avulso_Id'];
            $v = $av[$aid] ?? ($row['Avulso_Valor'] !== null
                ? (float) $row['Avulso_Valor'] : null);
            $lista[] = ['id' => $aid, 'nome' => fc_avulso_rotulo($row), 'valor' => $v];
            $centros[] = 'Avulso: ' . fc_avulso_rotulo($row) . ($v !== null
                ? ' — ' . number_format($v, 2, ',', '.') : '');
        }
        $rel = ['grupo' => trim((string) $g['FCGrupo_Nome']), 'centros' => $centros,
                'avulsos' => $lista, 'dados' => [], 'erros' => []];
        $saldoRel = 0.0;
        foreach (fcs_alvos_grupo($codcs) as $base => $empresas) {
            foreach ($empresas as $emp => $centros) {
                try {
                    $d = relatorio_financeiro_dados(
                        $base, fcs_opcoes_relatorio($emp, $ini, $fim, $centros));
                    $rel['dados'][] = $d;
                    $saldoRel += fcs_saldo_relatorio($d);
                } catch (Throwable $e) {
                    $rel['erros'][] = "$base/$emp: " . $e->getMessage();
                }
            }
        }
        $rel['saldo'] = $saldoRel + array_sum(
            array_map(fn($a) => $a['valor'] ?? 0.0, $rel['avulsos']));
        if (!$rel['dados'] && !$rel['erros']) {
            $rel['erros'][] = 'O grupo nao tem centros de custo.';
        }
        $out[] = $rel;
    }
    return $out;
}

/**
 * Recalcula e grava o saldo dos grupos informados.
 * $avulsos: FCGrupo_Id => [Avulso_Id => valor] digitados na tela.
 * Devolve ['ok' => qtd gravada, 'erros' => [nome => mensagem]].
 */
function fcs_atualizar_saldos(array $ids, string $ini, string $fim, array $avulsos = []): array
{
    $ok = 0;
    $erros = [];
    foreach ($ids as $id) {
        try {
            $saldo = fcs_saldo_grupo((int) $id, $ini, $fim, $avulsos[(int) $id] ?? []);
            if (query('SELECT 1 FROM msoft.GruposSaldos WHERE FCGrupo_Id = ?', [(int) $id])) {
                execute('UPDATE msoft.GruposSaldos
                         SET SaldoGrupo = ?, UltimaAtualizacao = GETDATE()
                         WHERE FCGrupo_Id = ?', [$saldo, (int) $id]);
            } else {
                execute('INSERT INTO msoft.GruposSaldos (FCGrupo_Id, SaldoGrupo, UltimaAtualizacao)
                         VALUES (?, ?, GETDATE())', [(int) $id, $saldo]);
            }
            $ok++;
        } catch (Throwable $e) {
            $g = fc_get((int) $id);
            $erros[] = ($g['FCGrupo_Nome'] ?? "#$id") . ': ' . $e->getMessage();
        }
    }
    return ['ok' => $ok, 'erros' => $erros];
}

/**
 * Item 5 — CONTAS A PAGAR. Mesma fonte do Relatorio de Contas a Pagar
 * (Titulos_a_Pagar + Documento_de_Entrada), somando o ValorLiquido por
 * mes de TitaPag_DataVencimentoFluxo e por tipo de entrada:
 *   ED (documento real) -> Comprometido; EP (previsao) -> Previsto.
 * Titulos ja pagos/conciliados nao entram; vencidos antes do primeiro
 * mes do quadro caem no primeiro mes (atrasados viram compromisso atual).
 */

/** base => empresa => [Filial+Centro] dos CodCs do grupo (para DocEnt). */
function fcs_alvos_filcen(array $codcs): array
{
    $alvo = [];
    foreach (fcs_bases_mapa() as $base => $banco) {
        $existem = fcs_codcs_base($banco);
        foreach ($codcs as $codc) {
            if (strlen($codc) === 9 && isset($existem[strtoupper($codc)])) {
                $alvo[$base][substr($codc, 0, 3)][] = substr($codc, 3, 6);
            }
        }
    }
    return $alvo;
}

/**
 * Janela fixa do item 5 (trimestre rolante, independente do periodo do
 * item 3): mes atual + os dois seguintes. Subgrupos: "mes atual",
 * "mes posterior" e "mes posterior que fecha o trimestre".
 */
function fcs_pagar_meses(): array
{
    $ym = date('Y-m');
    return [$ym,
            date('Y-m', strtotime("$ym-01 +1 month")),
            date('Y-m', strtotime("$ym-01 +2 month"))];
}

/** Rotulo de mes por extenso ("Outubro/2026"). */
function fcs_mes_rotulo(string $ym): string
{
    static $nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                     'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $nomes[(int) substr($ym, 5, 2) - 1] . '/' . substr($ym, 0, 4);
}

/** Tag do subgrupo pela posicao no trimestre (0=vigente, 1=posterior, 2=subsequente). */
function fcs_mes_tag(int $i): string
{
    return ['Mês Vigente', 'Mês Posterior', 'Mês Subsequente'][$i] ?? '';
}

/** [Y-m => ['c'=>comprometido, 'p'=>previsto]] de um grupo de centros. */
function fcs_pagar_grupo(array $codcs, array $meses): array
{
    $out = [];
    foreach ($meses as $ym) {
        $out[$ym] = ['c' => 0.0, 'p' => 0.0];
    }
    if (!$meses) {
        return $out;
    }
    $hoje = date('Y-m-d H:i:s');
    $limite = date('Y-m-d', strtotime(end($meses) . '-01 +1 month'));
    $iniYm = $meses[0];
    foreach (fcs_alvos_filcen($codcs) as $base => $emps) {
        foreach ($emps as $emp => $pares) {
            $ph = implode(',', array_fill(0, count($pares), '?'));
            $rows = query(
                "SELECT CONVERT(char(7), TitaPag.TitaPag_DataVencimentoFluxo, 120) ym,
                        RTRIM(DocEnt.EntDocTipo_Cod) tipo,
                        SUM(TitaPag.TitaPag_Valor + TitaPag.TitaPag_EncargosFin
                            + TitaPag.TitaPag_Multa + TitaPag.TitaPag_Acrescimos
                            - TitaPag.TitaPag_Descontos
                            - TitaPag.TitaPag_DescontoAdiantamento) val
                 FROM dbo.Titulos_a_Pagar TitaPag
                 JOIN dbo.Documento_de_Entrada DocEnt
                     ON TitaPag.Ent_Numero = DocEnt.Ent_Numero
                 WHERE DocEnt.Empresa_Cod = ?
                   AND DocEnt.EntDocTipo_Cod IN ('ED','EP')
                   AND ( TitaPag.TitaPag_Situacao = 'A'
                      OR ( TitaPag.TitaPag_Situacao = 'L'
                           AND TitaPag.Pag_DataPagamento > ?
                           AND TitaPag.Pag_DataDocumento > TitaPag.Pag_DataEmissao ) )
                   AND NOT EXISTS ( SELECT * FROM Movimento_Financeiro MovFin,
                                          Pagamentos Pagto
                                    WHERE MovFin.MovFin_Numero = Pagto.MovFin_Numero
                                      AND TitaPag.Pag_Numero = Pagto.Pag_Numero
                                      AND MovFin.MovFin_Conciliado = 'C' )
                   AND TitaPag.TitaPag_DataVencimentoFluxo < ?
                   AND DocEnt.Filial_Cod + DocEnt.Centro_Cod IN ($ph)
                 GROUP BY CONVERT(char(7), TitaPag.TitaPag_DataVencimentoFluxo, 120),
                          RTRIM(DocEnt.EntDocTipo_Cod)",
                array_merge([$emp, $hoje, $limite], $pares), banco_da_base($base));
            foreach ($rows as $r) {
                $ym = $r['ym'] < $iniYm ? $iniYm : $r['ym']; // vencidos -> 1o mes
                if (!isset($out[$ym])) {
                    continue;
                }
                $out[$ym][$r['tipo'] === 'EP' ? 'p' : 'c'] += (float) $r['val'];
            }
        }
    }
    return $out;
}

/** Dados do quadro: meses + uma linha por grupo + erros por grupo. */
function fcs_pagar_dados(array $grupos): array
{
    $meses = fcs_pagar_meses();
    $linhas = [];
    $erros = [];
    foreach ($grupos as $g) {
        $gid = (int) $g['FCGrupo_Id'];
        try {
            $linhas[] = ['id' => $gid, 'nome' => trim((string) $g['FCGrupo_Nome']),
                         'mes' => fcs_pagar_grupo(fc_centros_do_grupo($gid), $meses)];
        } catch (Throwable $t) {
            $erros[] = trim((string) $g['FCGrupo_Nome']) . ': ' . $t->getMessage();
            $linhas[] = ['id' => $gid,
                         'nome' => trim((string) $g['FCGrupo_Nome']), 'mes' => []];
        }
    }
    return ['meses' => $meses, 'linhas' => $linhas, 'erros' => $erros];
}

/** Recalcula o item 5 e grava em msoft.FluxoCaixa_Pagar (so no Atualizar). */
function fcs_pagar_calcular_salvar(array $grupos): array
{
    $dados = fcs_pagar_dados($grupos);
    execute('DELETE FROM msoft.FluxoCaixa_Pagar');
    foreach ($dados['linhas'] as $l) {
        foreach ($l['mes'] as $ym => $v) {
            execute('INSERT INTO msoft.FluxoCaixa_Pagar
                     (FCGrupo_Id, Mes, Comprometido, Previsto, UltimaAtualizacao)
                     VALUES (?, ?, ?, ?, GETDATE())',
                    [$l['id'], $ym, $v['c'], $v['p']]);
        }
    }
    $dados['ultima'] = date('Y-m-d H:i:s');
    return $dados;
}

/** Ultimo quadro salvo (mesma forma de fcs_pagar_dados + 'ultima'). */
function fcs_pagar_salvo(array $grupos): array
{
    $meses = [];
    $porGrupo = [];
    $ultima = null;
    foreach (query('SELECT FCGrupo_Id, RTRIM(Mes) Mes, Comprometido, Previsto,
                          UltimaAtualizacao
                    FROM msoft.FluxoCaixa_Pagar') as $r) {
        $meses[$r['Mes']] = true;
        $porGrupo[(int) $r['FCGrupo_Id']][$r['Mes']] = [
            'c' => (float) $r['Comprometido'], 'p' => (float) $r['Previsto']];
        $u = (string) $r['UltimaAtualizacao'];
        if ($ultima === null || $u > $ultima) {
            $ultima = $u;
        }
    }
    $meses = array_keys($meses);
    sort($meses);
    $linhas = [];
    foreach ($grupos as $g) {
        $gid = (int) $g['FCGrupo_Id'];
        $linhas[] = ['id' => $gid, 'nome' => trim((string) $g['FCGrupo_Nome']),
                     'mes' => $porGrupo[$gid] ?? []];
    }
    return ['meses' => $meses, 'linhas' => $linhas, 'erros' => [],
            'ultima' => $ultima];
}

/**
 * Item 4 — CONTAS A RECEBER. Fonte: medicoes previstas em
 * msoft.PrevisaoMedicao (tela Engenharia > Previsoes > Medicoes),
 * agrupadas pelo mes da previsao de recebimento na mesma janela do
 * trimestre rolante do item 5. Consulta so na base local (sem
 * procedures do ERP) — instantanea, dispensa snapshot.
 * Situacao: NF emitida (Med_EmissaoNF <= hoje) = "Notas Fiscais
 * Emitidas"; senao "Previsão De Medição". Previsoes vencidas caem no
 * primeiro mes (ainda a receber).
 */
function fcs_receber_dados(): array
{
    $meses = fcs_pagar_meses();
    $linhas = array_fill_keys($meses, []);
    $iniYm = $meses[0];
    $limite = date('Y-m-d', strtotime(end($meses) . '-01 +1 month'));
    $hoje = date('Y-m-d');
    $labels = mp_centros();
    $fora = fcs_codcs_fora_das_bases(); // centros so de base desmarcada
    // CodC => [ordem, nome] do 1o grupo do item 3 que contem o centro
    // (centro em mais de um grupo conta so no primeiro; sem grupo vai
    // para o bloco "Sem grupo" — os subtotais fecham com o do mes).
    $grupoDo = [];
    foreach (query("SELECT RTRIM(c.Centro_CodC) codc, RTRIM(g.FCGrupo_Nome) nome,
                          ISNULL(o.Ordem, 2147483647) ord, g.FCGrupo_Nome
                    FROM msoft.FluxoCaixa_Centros c
                    JOIN msoft.FluxoCaixa_Grupo g ON g.FCGrupo_Id = c.FCGrupo_Id
                    LEFT JOIN msoft.FluxoCaixa_GrupoOrdem o
                        ON o.FCGrupo_Id = g.FCGrupo_Id") as $r) {
        $codc = strtoupper($r['codc']);
        if (!isset($grupoDo[$codc]) || (int) $r['ord'] < $grupoDo[$codc][0]) {
            $grupoDo[$codc] = [(int) $r['ord'], trim((string) $r['nome'])];
        }
    }
    foreach (query("SELECT m.PMed_Id, RTRIM(c.Centro_CodC) Centro_CodC,
                           CONVERT(varchar(120), m.Med_Descricao) Med_Descricao,
                           m.Med_Valor,
                           CONVERT(varchar(10), m.Med_EmissaoNF, 120) Med_EmissaoNF,
                           CONVERT(varchar(10), m.Med_Previsao, 120) Med_Previsao
                    FROM msoft.PrevisaoMedicao m
                    JOIN msoft.PrevisaoMedicaoCentro c ON c.PMedC_Id = m.PMedC_Id
                    WHERE m.Med_Previsao < ?
                    ORDER BY m.Med_Previsao, m.PMed_Id", [$limite]) as $r) {
        if ($fora !== null
            && isset($fora[strtoupper(trim((string) $r['Centro_CodC']))])) {
            continue;
        }
        $ym = substr((string) $r['Med_Previsao'], 0, 7);
        if ($ym < $iniYm) {
            $ym = $iniYm; // a receber atrasada -> mes vigente
        }
        if (!isset($linhas[$ym])) {
            continue;
        }
        $obra = $labels[$r['Centro_CodC']] ?? $r['Centro_CodC'];
        if (str_contains($obra, ' - ')) {
            $obra = substr($obra, strpos($obra, ' - ') + 3); // so o nome, como no modelo
        }
        $emitida = $r['Med_EmissaoNF'] !== null && $r['Med_EmissaoNF'] <= $hoje;
        [$gord, $gnome] = $grupoDo[strtoupper((string) $r['Centro_CodC'])]
                          ?? [PHP_INT_MAX, 'Sem grupo'];
        $linhas[$ym][] = [
            'id'      => (int) $r['PMed_Id'],
            'obra'    => $obra,
            'desc'    => trim((string) $r['Med_Descricao']),
            'sit'     => $emitida ? 'Notas Fiscais Emitidas' : 'Previsão De Medição',
            'emissao' => $r['Med_EmissaoNF'],
            'prev'    => (string) $r['Med_Previsao'],
            'valor'   => (float) $r['Med_Valor'],
            'gord'    => $gord,
            'gnome'   => $gnome,
        ];
    }
    foreach ($linhas as &$ml) {
        usort($ml, fn($a, $b) => [$a['gord'], $a['gnome'], $a['prev'], $a['id']]
                              <=> [$b['gord'], $b['gnome'], $b['prev'], $b['id']]);
    }
    return ['meses' => $meses, 'linhas' => $linhas];
}

/**
 * Item 1 — POSICAO FINANCEIRA. Fonte: Total Geral do relatorio Posicao
 * de Contas (posicao_contas_dados), tipo BC (Bancos), todas as empresas,
 * nas bases dbMGA + dbConv, na data do calculo:
 *   Saldo bancario        = soma da coluna "Saldo Atual"
 *   Aplicacoes financeiras = soma da coluna "Valor Aplicado"
 * Persistido em msoft.FluxoCaixa_Posicao (1 linha consolidada) — o form
 * so le o snapshot; o recalculo acontece no clique do Atualizar, DEPOIS
 * do item 5 (a projecao usa o FluxoCaixa_Pagar recem-gravado).
 * Projecao no fechamento de cada mes do trimestre:
 *   mesN = saldo do mes anterior - Contas a Pagar(N) + Contas a
 *   Receber(N); o 1o mes parte do saldo bancario de hoje.
 */
function fcs_posicao_calcular_salvar(): array
{
    $hoje = date('Y-m-d');
    $erros = [];

    // projecao: contas a pagar do snapshot recem-gravado (o Atualizar
    // roda o item 5 antes) + contas a receber ao vivo (base local)
    $pagarMes = [];
    foreach (fcs_pagar_salvo(fcs_listar())['linhas'] as $l) {
        foreach ($l['mes'] as $ym => $v) {
            $pagarMes[$ym] = ($pagarMes[$ym] ?? 0.0) + $v['c'] + $v['p'];
        }
    }
    $recMes = [];
    foreach (fcs_receber_dados()['linhas'] as $ym => $lins) {
        $recMes[$ym] = array_sum(array_column($lins, 'valor'));
    }

    $tot = ['saldo' => 0.0, 'aplic' => 0.0];
    foreach (fcs_bases_selecionadas() as $base) {
        try {
            $d = posicao_contas_dados($base, [
                'empresas' => [], 'tipo' => 'BC', 'data' => $hoje,
                'nucleo' => '', 'agente' => '', 'conciliados' => false,
            ]);
            $tot['saldo'] += $d['tot']['atual'];
            $tot['aplic'] += $d['tot']['aplicado'];
        } catch (Throwable $t) {
            $erros[] = "$base: " . $t->getMessage();
        }
    }
    if (!$tot['saldo'] && !$tot['aplic'] && $erros) {
        return ['tot' => $tot, 'erros' => $erros]; // nada gravado
    }

    // fechamento de cada mes: saldo + a receber - a pagar do mes
    $mes = [];
    $s = $tot['saldo'];
    foreach (fcs_pagar_meses() as $ym) {
        $s += ($recMes[$ym] ?? 0.0) - ($pagarMes[$ym] ?? 0.0);
        $mes[] = $s;
    }

    execute('DELETE FROM msoft.FluxoCaixa_Posicao');
    execute('INSERT INTO msoft.FluxoCaixa_Posicao
             (SaldoBancario, Aplicacoes, SaldoMes1, SaldoMes2, SaldoMes3,
              UltimaAtualizacao)
             VALUES (?, ?, ?, ?, ?, GETDATE())',
            [$tot['saldo'], $tot['aplic'], $mes[0], $mes[1], $mes[2]]);
    $tot['mes'] = $mes;
    return ['tot' => $tot, 'erros' => $erros];
}

/** Ultimo snapshot do item 1 (['saldo','aplic','mes'=>[3],'ultima'] | null). */
function fcs_posicao_salva(): ?array
{
    $r = query('SELECT SaldoBancario, Aplicacoes, SaldoMes1, SaldoMes2,
                       SaldoMes3, UltimaAtualizacao
                FROM msoft.FluxoCaixa_Posicao');
    if (!$r) {
        return null;
    }
    $r = $r[0];
    return ['saldo' => (float) $r['SaldoBancario'],
            'aplic' => (float) $r['Aplicacoes'],
            'mes' => [(float) $r['SaldoMes1'], (float) $r['SaldoMes2'],
                      (float) $r['SaldoMes3']],
            'ultima' => (string) $r['UltimaAtualizacao']];
}

/** Remove linhas sem valor em nenhum mes (resumo pos-Atualizar). */
function fcs_pagar_sem_zeros(array $dados): array
{
    $dados['linhas'] = array_values(array_filter($dados['linhas'], function ($l) {
        foreach ($l['mes'] as $m) {
            if ($m['c'] != 0.0 || $m['p'] != 0.0) {
                return true;
            }
        }
        return false;
    }));
    return $dados;
}

/**
 * Item 3 — projecao do SALDO de cada grupo no fechamento de cada mes do
 * trimestre rolante (mesma janela do item 5):
 *   mesN = mes(N-1) - Contas a Pagar(N) + Contas a Receber(N),
 * partindo do SaldoGrupo ("Saldo Atual" da linha). Gravada em
 * msoft.GruposSaldos.SaldoMes1..3 junto com o calculo do grupo — so
 * no Atualizar, apenas dos grupos marcados (grupo nao marcado mantem
 * o ultimo calculo). Roda DEPOIS do item 5 (usa o FluxoCaixa_Pagar
 * recem-gravado); o "a receber" por grupo sai ao vivo de
 * PrevisaoMedicao (base local). UltimaAtualizacao NAO muda aqui: a
 * coluna mostra a data/hora do calculo do grupo (fcs_atualizar_saldos).
 */

/** FCGrupo_Id => [Y-m => total a receber] — mesma regra de fcs_receber_dados. */
function fcs_receber_por_grupo(): array
{
    $meses = fcs_pagar_meses();
    $iniYm = $meses[0];
    $limite = date('Y-m-d', strtotime(end($meses) . '-01 +1 month'));
    // CodC => [FCGrupo_Id, ordem]: centro em mais de um grupo conta so
    // no de menor Ordem (mesmo desempate do item 4)
    $grupoDo = [];
    foreach (query("SELECT RTRIM(c.Centro_CodC) codc, c.FCGrupo_Id,
                          ISNULL(o.Ordem, 2147483647) ord
                    FROM msoft.FluxoCaixa_Centros c
                    LEFT JOIN msoft.FluxoCaixa_GrupoOrdem o
                        ON o.FCGrupo_Id = c.FCGrupo_Id") as $r) {
        $codc = strtoupper($r['codc']);
        if (!isset($grupoDo[$codc]) || (int) $r['ord'] < $grupoDo[$codc][1]) {
            $grupoDo[$codc] = [(int) $r['FCGrupo_Id'], (int) $r['ord']];
        }
    }
    $out = [];
    $fora = fcs_codcs_fora_das_bases(); // centros so de base desmarcada
    foreach (query("SELECT RTRIM(c.Centro_CodC) Centro_CodC, m.Med_Valor,
                           CONVERT(varchar(10), m.Med_Previsao, 120) Med_Previsao
                    FROM msoft.PrevisaoMedicao m
                    JOIN msoft.PrevisaoMedicaoCentro c ON c.PMedC_Id = m.PMedC_Id
                    WHERE m.Med_Previsao < ?", [$limite]) as $r) {
        if ($fora !== null
            && isset($fora[strtoupper(trim((string) $r['Centro_CodC']))])) {
            continue;
        }
        $ym = substr((string) $r['Med_Previsao'], 0, 7);
        if ($ym < $iniYm) {
            $ym = $iniYm; // a receber atrasada -> mes vigente
        }
        if (!in_array($ym, $meses, true)) {
            continue;
        }
        $g = $grupoDo[strtoupper((string) $r['Centro_CodC'])] ?? null;
        if ($g === null) {
            continue;
        }
        $out[$g[0]][$ym] = ($out[$g[0]][$ym] ?? 0.0) + (float) $r['Med_Valor'];
    }
    return $out;
}

/** Recalcula a projecao do item 3 dos grupos marcados ($ids). */
function fcs_projecao_calcular_salvar(array $ids): void
{
    $ids = array_map('intval', $ids);
    if (!$ids) {
        return;
    }
    $meses = fcs_pagar_meses();
    $pagMap = [];
    foreach (query('SELECT FCGrupo_Id, RTRIM(Mes) Mes, Comprometido, Previsto
                    FROM msoft.FluxoCaixa_Pagar') as $r) {
        $pagMap[(int) $r['FCGrupo_Id']][$r['Mes']] =
            (float) $r['Comprometido'] + (float) $r['Previsto'];
    }
    $recMap = fcs_receber_por_grupo();
    foreach (fcs_listar() as $g) {
        $gid = (int) $g['FCGrupo_Id'];
        if (!in_array($gid, $ids, true) || $g['SaldoGrupo'] === null) {
            continue; // nao marcado ou nunca calculado: mantem ultimo calculo
        }
        $s = (float) $g['SaldoGrupo'];
        $ms = [];
        foreach ($meses as $ym) {
            $s += ($recMap[$gid][$ym] ?? 0.0) - ($pagMap[$gid][$ym] ?? 0.0);
            $ms[] = $s;
        }
        execute('UPDATE msoft.GruposSaldos
                 SET SaldoMes1 = ?, SaldoMes2 = ?, SaldoMes3 = ?
                 WHERE FCGrupo_Id = ?', [$ms[0], $ms[1], $ms[2], $gid]);
    }
}

/**
 * Item 2 — APORTES A RETORNAR. Soma dos aportes cadastrados por grupo
 * em msoft.GrupoCentro_Aporte (tela Aportes a Retornar). Consulta so na
 * base local — instantanea — mas segue a mesma regra dos demais itens:
 * persistida em msoft.FluxoCaixa_Aportes (1 linha por grupo com aporte),
 * a tela le so o snapshot e o recalculo so acontece no Atualizar.
 */
function fcs_aportes_calcular_salvar(): void
{
    execute('DELETE FROM msoft.FluxoCaixa_Aportes');
    execute("INSERT INTO msoft.FluxoCaixa_Aportes
             (FCGrupo_Id, TotalAportes, UltimaAtualizacao)
             SELECT FCGrupo_Id, SUM(Aporte_Valor), GETDATE()
             FROM msoft.GrupoCentro_Aporte GROUP BY FCGrupo_Id");
}

/** Ultimo quadro salvo do item 2: linhas por grupo + total + 'ultima'. */
function fcs_aportes_salvo(array $grupos): array
{
    $totais = [];
    $ultima = null;
    foreach (query('SELECT FCGrupo_Id, TotalAportes, UltimaAtualizacao
                    FROM msoft.FluxoCaixa_Aportes') as $r) {
        $totais[(int) $r['FCGrupo_Id']] = (float) $r['TotalAportes'];
        $u = (string) $r['UltimaAtualizacao'];
        if ($ultima === null || $u > $ultima) {
            $ultima = $u;
        }
    }
    $linhas = [];
    $total = 0.0;
    foreach ($grupos as $g) {
        $gid = (int) $g['FCGrupo_Id'];
        if (!isset($totais[$gid])) {
            continue;
        }
        $linhas[] = ['id' => $gid, 'nome' => trim((string) $g['FCGrupo_Nome']),
                     'total' => $totais[$gid]];
        $total += $totais[$gid];
    }
    return ['linhas' => $linhas, 'total' => $total, 'ultima' => $ultima];
}
