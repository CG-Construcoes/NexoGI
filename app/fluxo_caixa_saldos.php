<?php
declare(strict_types=1);

/*
 * Fluxo de Caixa — listagem de grupos com saldo (Financeiro > Fluxo de Caixa).
 * msoft.GruposSaldos: FCGrupo_Id (PK/FK), UltimaAtualizacao, SaldoCentro —
 * preenchida pelo botao "Atualizar" da tela (rotina de calculo a definir).
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
                         s.SaldoCentro, s.UltimaAtualizacao
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
