<?php
declare(strict_types=1);
/** Saldo por centro de custo dos grupos (detalhe da linha no fluxo-caixa).
 *  php scripts/migrar_fluxo_centros.php — base CGGestao, schema msoft. */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.GruposSaldosCentro') IS NULL
    CREATE TABLE msoft.GruposSaldosCentro (
        FCGrupo_Id     INT NOT NULL REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Centro_CodC    VARCHAR(12) NOT NULL,
        Base           VARCHAR(4)  NOT NULL,
        Saldo          DECIMAL(18,2) NOT NULL DEFAULT 0,
        AtualizadoEm   DATETIME NOT NULL DEFAULT GETDATE(),
        PRIMARY KEY (FCGrupo_Id, Centro_CodC, Base)
    );
");
echo "msoft.GruposSaldosCentro OK.\n";
