<?php
declare(strict_types=1);
/**
 * Cria a tabela do item 1 (POSICAO FINANCEIRA) do Acompanhamento
 * Financeiro (rodar 1x, linha de comando):
 *   php scripts/migrar_fluxo_caixa_posicao.php
 *
 * msoft.FluxoCaixa_Posicao: 1 linha por base ERP ('MGA'/'CONV') com o
 * Total Geral do relatorio Posicao de Contas (tipo BC - Bancos) na data
 * do calculo: SaldoBancario = coluna "Saldo Atual", Aplicacoes = coluna
 * "Valor Aplicado". O form le so o ultimo snapshot; o recalculo so
 * acontece no clique do botao Atualizar.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.FluxoCaixa_Posicao') IS NULL
    CREATE TABLE msoft.FluxoCaixa_Posicao (
        Base              CHAR(4)       NOT NULL,
        SaldoBancario     NUMERIC(18,2) NULL,
        Aplicacoes        NUMERIC(18,2) NULL,
        UltimaAtualizacao DATETIME      NULL,
        CONSTRAINT PK_FluxoCaixa_Posicao PRIMARY KEY (Base)
    );
");
echo "Tabela msoft.FluxoCaixa_Posicao verificada/criada.\n";
