<?php
declare(strict_types=1);
/**
 * Cria a tabela do item 1 (POSICAO FINANCEIRA) do Acompanhamento
 * Financeiro (rodar 1x, linha de comando):
 *   php scripts/migrar_fluxo_caixa_posicao.php
 *
 * msoft.FluxoCaixa_Posicao: 1 linha unica (snapshot do ultimo Atualizar):
 *   SaldoBancario = soma do "Saldo Atual" (Total Geral da Posicao de
 *     Contas, tipo BC, todas as empresas, dbMGA+dbConv, data do calculo)
 *   Aplicacoes    = soma do "Valor Aplicado"
 *   SaldoMes1..3  = projecao do saldo no fechamento de cada mes do
 *     trimestre rolante: mesN = mes(N-1) - Contas a Pagar(N) +
 *     Contas a Receber(N) — a partir do saldo de hoje.
 * A tela le so o snapshot; o recalculo so acontece no clique do
 * Atualizar (DEPOIS do item 5 — usa o FluxoCaixa_Pagar recem-gravado).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

// formato anterior (por base) nao comportava a projecao — recria
execute('IF OBJECT_ID(\'msoft.FluxoCaixa_Posicao\') IS NOT NULL
             DROP TABLE msoft.FluxoCaixa_Posicao');
execute("
CREATE TABLE msoft.FluxoCaixa_Posicao (
    Pos_Id            INT IDENTITY(1,1) NOT NULL,
    SaldoBancario     NUMERIC(18,2) NULL,
    Aplicacoes        NUMERIC(18,2) NULL,
    SaldoMes1         NUMERIC(18,2) NULL,
    SaldoMes2         NUMERIC(18,2) NULL,
    SaldoMes3         NUMERIC(18,2) NULL,
    UltimaAtualizacao DATETIME      NULL,
    CONSTRAINT PK_FluxoCaixa_Posicao PRIMARY KEY (Pos_Id)
);
");
echo "Tabela msoft.FluxoCaixa_Posicao recriada.\n";
