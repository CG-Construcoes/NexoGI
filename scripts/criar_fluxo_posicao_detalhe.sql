/* Item 1 do Acompanhamento Financeiro — detalhe por conta bancaria.
 * Rodar 1x como sa/admin no CGGestao (NexoGI e, se quiser o detalhe
 * local, na instancia local tambem). */
USE CGGestao;
GO

IF OBJECT_ID('msoft.FluxoCaixa_PosicaoDetalhe') IS NOT NULL
    DROP TABLE msoft.FluxoCaixa_PosicaoDetalhe;
GO

CREATE TABLE msoft.FluxoCaixa_PosicaoDetalhe (
    PosDet_Id      INT IDENTITY(1,1) NOT NULL,
    Base           VARCHAR(10)   NOT NULL,
    Empresa_Cod    VARCHAR(20)   NOT NULL,
    Empresa_Nome   VARCHAR(200)  NULL,
    Agente_Cod     VARCHAR(40)   NOT NULL,
    Agente_Nome    VARCHAR(200)  NULL,
    Agencia        VARCHAR(40)   NULL,
    Conta          VARCHAR(40)   NULL,
    SaldoAtual     NUMERIC(18,2) NOT NULL,
    ValorAplicado  NUMERIC(18,2) NOT NULL,
    UltimaConc     DATETIME      NULL,
    CONSTRAINT PK_FluxoCaixa_PosicaoDetalhe PRIMARY KEY (PosDet_Id)
);
GO
