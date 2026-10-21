<?php
declare(strict_types=1);
/**
 * Cria as tabelas dos Aportes a Retornar (cadastro + snapshot do item 2
 * do Acompanhamento Financeiro). Rodar 1x, linha de comando:
 *   php scripts/migrar_fluxo_caixa_aportes.php
 *
 * msoft.GrupoCentro_Aporte: aportes cadastrados por grupo de centros
 *   (tela Financeiro > Fluxo de Caixa > Aportes a Retornar):
 *   Grupo (FCGrupo_Id), Descricao, Valor, Desde, AtualizadoEm.
 *
 * msoft.FluxoCaixa_Aportes: 1 linha por grupo (snapshot do ultimo
 *   Atualizar) = soma dos Aporte_Valor do grupo. A tela le so o
 *   snapshot; o recalculo so acontece no clique do Atualizar.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.GrupoCentro_Aporte') IS NULL
    CREATE TABLE msoft.GrupoCentro_Aporte (
        Aporte_Id           INT IDENTITY(1,1) PRIMARY KEY,
        FCGrupo_Id          INT NOT NULL
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Aporte_Descricao    VARCHAR(120) NOT NULL,
        Aporte_Valor        NUMERIC(14,2) NOT NULL,
        Aporte_Desde        DATE NOT NULL,
        Aporte_AtualizadoEm DATETIME NOT NULL DEFAULT GETDATE()
    );
");

execute("
IF OBJECT_ID('msoft.FluxoCaixa_Aportes') IS NULL
    CREATE TABLE msoft.FluxoCaixa_Aportes (
        FCGrupo_Id         INT NOT NULL
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        TotalAportes       NUMERIC(18,2) NULL,
        UltimaAtualizacao  DATETIME NULL,
        CONSTRAINT PK_FluxoCaixa_Aportes PRIMARY KEY (FCGrupo_Id)
    );
");

echo "Tabelas msoft.GrupoCentro_Aporte e msoft.FluxoCaixa_Aportes verificadas/criadas.\n";
