/* Item 5 do Acompanhamento Financeiro — detalhe por centro de custo.
 * Rodar 1x como sa/admin no CGGestao (o login do app nao tem DDL). */
USE CGGestao;
GO

IF OBJECT_ID('msoft.FluxoCaixa_PagarCentro') IS NOT NULL
    DROP TABLE msoft.FluxoCaixa_PagarCentro;
GO

CREATE TABLE msoft.FluxoCaixa_PagarCentro (
    FCGrupo_Id    INT           NOT NULL,
    Centro_CodC   VARCHAR(9)    NOT NULL,
    Base          VARCHAR(10)   NOT NULL,
    Mes           CHAR(7)       NOT NULL,
    Comprometido  NUMERIC(18,2) NOT NULL,
    Previsto      NUMERIC(18,2) NOT NULL,
    CONSTRAINT PK_FluxoCaixa_PagarCentro
        PRIMARY KEY (FCGrupo_Id, Centro_CodC, Base, Mes)
);
GO
