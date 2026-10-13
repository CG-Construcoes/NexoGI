<?php
declare(strict_types=1);
/**
 * Cria as tabelas de grupos de centros do Fluxo de Caixa (rodar 1x, linha de comando):
 *   php scripts/migrar_fluxo_caixa.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.FluxoCaixa_Grupo') IS NULL
    CREATE TABLE msoft.FluxoCaixa_Grupo (
        FCGrupo_Id        INT IDENTITY(1,1) PRIMARY KEY,
        FCGrupo_Nome      CHAR(60)  NOT NULL,
        FCGrupo_Descricao CHAR(100) NULL
    );
IF OBJECT_ID('msoft.FluxoCaixa_Centros') IS NULL
    CREATE TABLE msoft.FluxoCaixa_Centros (
        FCGrupo_Id  INT      NOT NULL REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Centro_CodC CHAR(9)  NOT NULL,
        PRIMARY KEY (FCGrupo_Id, Centro_CodC)
    );
");
echo "Tabelas msoft.FluxoCaixa_Grupo e msoft.FluxoCaixa_Centros verificadas/criadas.\n";
