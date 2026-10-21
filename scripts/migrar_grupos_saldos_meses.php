<?php
declare(strict_types=1);
/**
 * Inclui as colunas de projecao do item 3 (SALDO FINANCEIRO POR
 * OBRA/CENTRO) em msoft.GruposSaldos — rodar 1x, linha de comando:
 *   php scripts/migrar_grupos_saldos_meses.php
 *
 * SaldoMes1..3 = projecao do SaldoGrupo no fechamento de cada mes do
 * trimestre rolante: mesN = mes(N-1) - Contas a Pagar(N) + Contas a
 * Receber(N) — mesma formula da projecao do item 1, por grupo.
 * Gravadas so no clique do Atualizar (checkbox "Calcular" do item 3).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("IF COL_LENGTH('msoft.GruposSaldos', 'SaldoMes1') IS NULL
             ALTER TABLE msoft.GruposSaldos ADD SaldoMes1 NUMERIC(18,2) NULL");
execute("IF COL_LENGTH('msoft.GruposSaldos', 'SaldoMes2') IS NULL
             ALTER TABLE msoft.GruposSaldos ADD SaldoMes2 NUMERIC(18,2) NULL");
execute("IF COL_LENGTH('msoft.GruposSaldos', 'SaldoMes3') IS NULL
             ALTER TABLE msoft.GruposSaldos ADD SaldoMes3 NUMERIC(18,2) NULL");

echo "Colunas SaldoMes1..3 em msoft.GruposSaldos verificadas/criadas.\n";
