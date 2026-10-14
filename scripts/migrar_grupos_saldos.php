<?php
declare(strict_types=1);
/**
 * Cria a tabela de saldos por grupo de centros do Fluxo de Caixa
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_grupos_saldos.php
 *
 * msoft.GruposSaldos: 1 linha por FCGrupo_Id — o saldo do grupo e a data
 * da ultima atualizacao (recalculo disparado pela tela Fluxo de Caixa).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.GruposSaldos') IS NULL
    CREATE TABLE msoft.GruposSaldos (
        FCGrupo_Id         INT            NOT NULL PRIMARY KEY
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        UltimaAtualizacao  DATETIME       NULL,
        SaldoCentro        NUMERIC(14,2)  NULL
    );
");
echo "Tabela msoft.GruposSaldos verificada/criada.\n";
