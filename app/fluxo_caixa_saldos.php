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

/** FCGrupo_Id => [['id'=>Avulso_Id, 'nome'=>descricao], ...] por grupo. */
function fcs_avulsos_por_grupo(): array
{
    $map = [];
    foreach (query('SELECT Avulso_Id, FCGrupo_Id,
                          CONVERT(varchar(4000), Avulso_Descricao) Avulso_Descricao
                    FROM msoft.GrupoCentro_Avulso ORDER BY Avulso_Id') as $r) {
        $map[(int) $r['FCGrupo_Id']][] = ['id' => (int) $r['Avulso_Id'],
            'nome' => fc_avulso_rotulo($r)];
    }
    return $map;
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
            $lista[] = ['id' => $aid, 'nome' => fc_avulso_rotulo($row),
                        'valor' => $av[$aid] ?? null];
            $centros[] = 'Avulso: ' . fc_avulso_rotulo($row) . (isset($av[$aid])
                ? ' — ' . number_format($av[$aid], 2, ',', '.') : '');
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
