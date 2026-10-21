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
                         s.SaldoGrupo, s.UltimaAtualizacao
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
 * base => empresa => [Centro_Cod] dos CodCs do grupo: localiza a(s) base(s)
 * onde cada centro existe (dbMGA e/ou dbConv — empresas CVC/CVJ/CVP existem
 * nas duas) e quebra o CodC (Empresa3+Filial2+Centro4).
 */
function fcs_alvos_grupo(array $codcs): array
{
    $alvo = [];
    foreach (['MGA' => env('DB_ERP_MGA', 'dbMGA'), 'CONV' => env('DB_ERP_CONV', 'dbConv')]
             as $base => $banco) {
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
    foreach (['MGA' => env('DB_ERP_MGA', 'dbMGA'), 'CONV' => env('DB_ERP_CONV', 'dbConv')]
             as $base => $banco) {
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

/** Rotulo de mes no padrao do modelo ("OUT/26"). */
function fcs_mes_rotulo(string $ym): string
{
    static $ab = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN',
                  'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    return $ab[(int) substr($ym, 5, 2) - 1] . '/' . substr($ym, 2, 2);
}

/** Tag do subgrupo pela posicao no trimestre (0=atual, 1, 2=fecha). */
function fcs_mes_tag(int $i): string
{
    return ['mes atual', 'mes posterior',
            'mes posterior (fecha o trimestre)'][$i] ?? '';
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
