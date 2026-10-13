<?php
declare(strict_types=1);
/**
 * Cria as tabelas de links de senha e de permissoes por tela (rodar 1x, linha de comando):
 *   php scripts/migrar_acesso_email.php
 * Usuarios ativos que ja existiam recebem acesso a tela Pedidos (mantem o acesso que ja tinham).
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
IF OBJECT_ID('msoft.UsuarioTelas') IS NULL
    CREATE TABLE msoft.UsuarioTelas (
        UsuarioId  INT          NOT NULL REFERENCES msoft.Usuarios(Id) ON DELETE CASCADE,
        Tela       VARCHAR(50)  NOT NULL,
        PRIMARY KEY (UsuarioId, Tela)
    );
");
echo "Tabelas msoft.TokensSenha e msoft.UsuarioTelas verificadas/criadas.\n";

$n = execute("
INSERT INTO msoft.UsuarioTelas (UsuarioId, Tela)
SELECT u.Id, 'pedidos' FROM msoft.Usuarios u
WHERE u.Status = 'ativo' AND u.Admin = 0
  AND NOT EXISTS (SELECT 1 FROM msoft.UsuarioTelas t WHERE t.UsuarioId = u.Id)
");
echo "Permissao 'pedidos' concedida a $n usuario(s) ativo(s) existente(s).\n";
