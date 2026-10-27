<?php
declare(strict_types=1);
/**
 * Controle de Atividades (rodar 1x, linha de comando):
 *   php scripts/migrar_atividades.php
 * Cria msoft.Atividades / AtividadeParticipantes / AtividadeEventos /
 * AtividadeApontamentos e adiciona as colunas Setor e Funcao em
 * msoft.Usuarios (Funcao 'Gestor'/'Coordenador' = pode criar/atribuir).
 * Tudo na base CGGestao (DB_NAME), schema msoft.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.Atividades') IS NULL
    CREATE TABLE msoft.Atividades (
        Atividade_Id INT IDENTITY(1,1) PRIMARY KEY,
        Titulo       NVARCHAR(200) NOT NULL,
        Orientacoes  NVARCHAR(MAX) NULL,
        Visibilidade VARCHAR(12) NOT NULL DEFAULT 'Individual',
        Setor        NVARCHAR(60) NULL,
        Status       VARCHAR(20) NOT NULL DEFAULT 'A Iniciar',
        Urgente      BIT NOT NULL DEFAULT 0,
        Prazo        DATETIME NULL,
        Referencia   NVARCHAR(120) NULL,
        CriadoPor    INT NOT NULL REFERENCES msoft.Usuarios(Id),
        CriadoEm     DATETIME NOT NULL DEFAULT GETDATE(),
        IniciadoEm   DATETIME NULL,
        ConcluidoEm  DATETIME NULL,
        CanceladaEm  DATETIME NULL,
        Excluida     BIT NOT NULL DEFAULT 0
    );
IF OBJECT_ID('msoft.AtividadeParticipantes') IS NULL
    CREATE TABLE msoft.AtividadeParticipantes (
        Atividade_Id INT NOT NULL REFERENCES msoft.Atividades(Atividade_Id) ON DELETE CASCADE,
        Usuario_Id   INT NOT NULL REFERENCES msoft.Usuarios(Id) ON DELETE CASCADE,
        Papel        VARCHAR(15) NOT NULL DEFAULT 'participante',
        Ativo        BIT NOT NULL DEFAULT 1,
        IncluidoEm   DATETIME NOT NULL DEFAULT GETDATE(),
        PRIMARY KEY (Atividade_Id, Usuario_Id)
    );
IF OBJECT_ID('msoft.AtividadeEventos') IS NULL
    CREATE TABLE msoft.AtividadeEventos (
        Evento_Id    INT IDENTITY(1,1) PRIMARY KEY,
        Atividade_Id INT NOT NULL REFERENCES msoft.Atividades(Atividade_Id) ON DELETE CASCADE,
        Usuario_Id   INT NULL REFERENCES msoft.Usuarios(Id),
        DhEvento     DATETIME NOT NULL DEFAULT GETDATE(),
        Tipo         VARCHAR(20) NOT NULL,
        Texto        NVARCHAR(MAX) NULL,
        DeValor      NVARCHAR(80) NULL,
        ParaValor    NVARCHAR(80) NULL
    );
IF OBJECT_ID('msoft.AtividadeApontamentos') IS NULL
    CREATE TABLE msoft.AtividadeApontamentos (
        Apont_Id     INT IDENTITY(1,1) PRIMARY KEY,
        Atividade_Id INT NOT NULL REFERENCES msoft.Atividades(Atividade_Id) ON DELETE CASCADE,
        Usuario_Id   INT NOT NULL REFERENCES msoft.Usuarios(Id),
        Inicio       DATETIME NOT NULL,
        Fim          DATETIME NULL,
        Minutos      INT NULL,
        Obs          NVARCHAR(300) NULL
    );
");

execute("
IF COL_LENGTH('msoft.Usuarios', 'Setor') IS NULL
    ALTER TABLE msoft.Usuarios ADD Setor NVARCHAR(60) NULL;
IF COL_LENGTH('msoft.Usuarios', 'Funcao') IS NULL
    ALTER TABLE msoft.Usuarios ADD Funcao NVARCHAR(30) NULL;
");

execute("
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AtivPart_Usuario'
               AND object_id = OBJECT_ID('msoft.AtividadeParticipantes'))
    CREATE INDEX IX_AtivPart_Usuario ON msoft.AtividadeParticipantes (Usuario_Id, Ativo);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Atividades_Status'
               AND object_id = OBJECT_ID('msoft.Atividades'))
    CREATE INDEX IX_Atividades_Status ON msoft.Atividades (Excluida, Status, Prazo);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AtivEventos_Atividade'
               AND object_id = OBJECT_ID('msoft.AtividadeEventos'))
    CREATE INDEX IX_AtivEventos_Atividade ON msoft.AtividadeEventos (Atividade_Id, DhEvento);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AtivApont_Atividade'
               AND object_id = OBJECT_ID('msoft.AtividadeApontamentos'))
    CREATE INDEX IX_AtivApont_Atividade ON msoft.AtividadeApontamentos (Atividade_Id, Usuario_Id, Fim);
");

echo "Tabelas msoft.Atividade* verificadas/criadas; Usuarios.Setor/Funcao OK.\n";
