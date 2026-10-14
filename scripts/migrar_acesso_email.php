<?php
declare(strict_types=1);
/**
 * Cria a tabela de tokens de senha (rodar 1x, linha de comando):
 *   php scripts/migrar_acesso_email.php
 * Historico: esta migracao tambem criava msoft.UsuarioTelas (permissoes
 * individuais). Esse modelo foi substituido em 2026-10-14 por grupos —
 * ver scripts/migrar_grupos_usuarios.php. Nao recriar UsuarioTelas aqui.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.TokensSenha') IS NULL
    CREATE TABLE msoft.TokensSenha (
        Id         INT IDENTITY(1,1) PRIMARY KEY,
        UsuarioId  INT          NOT NULL REFERENCES msoft.Usuarios(Id) ON DELETE CASCADE,
        TokenHash  CHAR(64)     NOT NULL UNIQUE,
        Tipo       VARCHAR(10)  NOT NULL,           -- 'definir' (novo usuario) | 'redefinir'
        CriadoEm   DATETIME     NOT NULL DEFAULT GETDATE(),
        ExpiraEm   DATETIME     NOT NULL,
        UsadoEm    DATETIME     NULL,
        IpOrigem   VARCHAR(45)  NULL
    );
");
echo "Tabela msoft.TokensSenha verificada/criada.\n";
