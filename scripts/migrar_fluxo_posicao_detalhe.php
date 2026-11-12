<?php
declare(strict_types=1);
/**
 * Cria a tabela de detalhe do item 1 (POSICAO FINANCEIRA) do
 * Acompanhamento Financeiro (rodar 1x, linha de comando):
 *   php scripts/migrar_fluxo_posicao_detalhe.php
 *
 * msoft.FluxoCaixa_PosicaoDetalhe: uma linha por conta bancaria que
 * compoe o snapshot de FluxoCaixa_Posicao (mesma origem — Posicao de
 * Contas tipo BC, todas as empresas, bases selecionadas). Serve para
 * o clique na celula da Posicao Financeira expandir "de onde vem o
 * valor" sem reconsultar o ERP. Substituicao total a cada Atualizar.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute('IF OBJECT_ID(\'msoft.FluxoCaixa_PosicaoDetalhe\') IS NOT NULL
             DROP TABLE msoft.FluxoCaixa_PosicaoDetalhe');
execute("
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
");
echo "Tabela msoft.FluxoCaixa_PosicaoDetalhe criada.\n";
