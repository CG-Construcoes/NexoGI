<?php
declare(strict_types=1);
/**
 * Permissoes por GRUPO de usuarios (rodar 1x, linha de comando):
 *   php scripts/migrar_grupos_usuarios.php
 * Cria msoft.UsuarioGrupo / UsuarioGrupoTelas / UsuarioGrupoMembro e
 * REMOVE msoft.UsuarioTelas (permissoes individuais descartadas —
 * decisao do usuario em 2026-10-14).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.UsuarioGrupo') IS NULL
    CREATE TABLE msoft.UsuarioGrupo (
        UsGrupo_Id        INT IDENTITY(1,1) PRIMARY KEY,
        UsGrupo_Nome      CHAR(60)  NOT NULL,
        UsGrupo_Descricao CHAR(100) NULL
    );
IF OBJECT_ID('msoft.UsuarioGrupoTelas') IS NULL
    CREATE TABLE msoft.UsuarioGrupoTelas (
        UsGrupo_Id  INT      NOT NULL REFERENCES msoft.UsuarioGrupo(UsGrupo_Id) ON DELETE CASCADE,
        Tela        CHAR(40) NOT NULL,
        P_Consultar BIT NOT NULL DEFAULT 1,
        P_Inserir   BIT NOT NULL DEFAULT 0,
        P_Alterar   BIT NOT NULL DEFAULT 0,
        P_Excluir   BIT NOT NULL DEFAULT 0,
        PRIMARY KEY (UsGrupo_Id, Tela)
    );
IF OBJECT_ID('msoft.UsuarioGrupoMembro') IS NULL
    CREATE TABLE msoft.UsuarioGrupoMembro (
        UsGrupo_Id INT NOT NULL REFERENCES msoft.UsuarioGrupo(UsGrupo_Id) ON DELETE CASCADE,
        UsuarioId  INT NOT NULL REFERENCES msoft.Usuarios(Id) ON DELETE CASCADE,
        PRIMARY KEY (UsGrupo_Id, UsuarioId)
    );
");

if (query("SELECT 1 x WHERE OBJECT_ID('msoft.UsuarioTelas') IS NOT NULL")) {
    execute('DROP TABLE msoft.UsuarioTelas');
    echo "msoft.UsuarioTelas removida (permissoes individuais descartadas).\n";
}
echo "Tabelas msoft.UsuarioGrupo* verificadas/criadas.\n";
