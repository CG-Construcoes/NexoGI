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
 */

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
                         s.SaldoGrupo, s.UltimaAtualizacao
                  FROM msoft.FluxoCaixa_Grupo g
                  LEFT JOIN msoft.GruposSaldos s ON s.FCGrupo_Id = g.FCGrupo_Id
                  ORDER BY g.FCGrupo_Nome");
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
    foreach ($map as &$lista) {
        sort($lista);
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
 * Saldo do grupo no periodo: para cada Centro_CodC do grupo, localiza a(s)
 * base(s) onde o centro existe (dbMGA e/ou dbConv — empresas CVC/CVJ/CVP
 * existem nas duas), agrupa por (base, empresa) e executa o calculo do
 * Relatorio Financeiro. Devolve a soma das secoes Receitas + Despesas da
 * coluna "Periodo" — o mesmo valor da linha SALDO exibida com
 * "Detalhar Apenas Periodo" marcado.
 */
function fcs_saldo_grupo(int $grupoId, string $ini, string $fim): float
{
    $codcs = array_filter(fc_centros_do_grupo($grupoId), fn($c) => strlen($c) === 9);
    if (!$codcs) {
        return 0.0;
    }
    // base => empresa => [Centro_Cod (4 chars)] — CodC = Empresa(3)+Filial(2)+Centro(4)
    $alvo = [];
    foreach (['MGA' => env('DB_ERP_MGA', 'dbMGA'), 'CONV' => env('DB_ERP_CONV', 'dbConv')]
             as $base => $banco) {
        $existem = fcs_codcs_base($banco);
        foreach ($codcs as $codc) {
            if (isset($existem[strtoupper($codc)])) {
                $alvo[$base][substr($codc, 0, 3)][] = substr($codc, 5, 4);
            }
        }
    }
    $saldo = 0.0;
    foreach ($alvo as $base => $empresas) {
        foreach ($empresas as $emp => $centros) {
            $dados = relatorio_financeiro_dados($base, [
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
            ]);
            foreach ($dados['blocos'] as $secoes) {
                foreach ($secoes as $s) {
                    if ($s['id'] === 'receitas' || $s['id'] === 'despesas') {
                        $saldo += $s['per'];
                    }
                }
            }
        }
    }
    return $saldo;
}

/**
 * Recalcula e grava o saldo dos grupos informados.
 * Devolve ['ok' => qtd gravada, 'erros' => [nome => mensagem]].
 */
function fcs_atualizar_saldos(array $ids, string $ini, string $fim): array
{
    $ok = 0;
    $erros = [];
    foreach ($ids as $id) {
        try {
            $saldo = fcs_saldo_grupo((int) $id, $ini, $fim);
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
