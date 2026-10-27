<?php
declare(strict_types=1);
/** Vinculos de atividades ao ERP (Solicitacao/Coleta/Pedido/AR).
 *  php scripts/migrar_atividades_vinculos.php — base CGGestao, schema msoft. */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.AtividadeVinculos') IS NULL
    CREATE TABLE msoft.AtividadeVinculos (
        Vinculo_Id   INT IDENTITY(1,1) PRIMARY KEY,
        Atividade_Id INT NOT NULL REFERENCES msoft.Atividades(Atividade_Id) ON DELETE CASCADE,
        Tipo         VARCHAR(12) NOT NULL,
        Base         VARCHAR(4)  NOT NULL,
        Chave        VARCHAR(40) NOT NULL,
        Rotulo       NVARCHAR(200) NULL,
        CriadoPor    INT NULL REFERENCES msoft.Usuarios(Id),
        CriadoEm     DATETIME NOT NULL DEFAULT GETDATE()
    );
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AtivVinc_Atividade'
               AND object_id = OBJECT_ID('msoft.AtividadeVinculos'))
    CREATE INDEX IX_AtivVinc_Atividade ON msoft.AtividadeVinculos (Atividade_Id);
");
echo "msoft.AtividadeVinculos OK.\n";
