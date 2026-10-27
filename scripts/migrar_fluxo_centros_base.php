<?php
declare(strict_types=1);
/** Coluna Base em FluxoCaixa_Centros: fixa a base ERP do centro
 *  (NULL = conta em todas as bases onde o CodC existir).
 *  php scripts/migrar_fluxo_centros_base.php — base CGGestao, schema msoft. */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF COL_LENGTH('msoft.FluxoCaixa_Centros', 'Base') IS NULL
    ALTER TABLE msoft.FluxoCaixa_Centros ADD Base VARCHAR(4) NULL;
");
echo "msoft.FluxoCaixa_Centros.Base OK.\n";
