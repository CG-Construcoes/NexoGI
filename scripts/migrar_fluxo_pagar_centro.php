<?php
declare(strict_types=1);
/**
 * Cria a tabela de detalhe por centro do item 5 (CONTAS A PAGAR) do
 * Acompanhamento Financeiro (rodar 1x, linha de comando, com permissao
 * de DDL — o login do app nao tem; ver scripts/criar_fluxo_pagar_centro.sql):
 *   php scripts/migrar_fluxo_pagar_centro.php
 *
 * msoft.FluxoCaixa_PagarCentro: uma linha por (grupo, centro, base, mes)
 * com Comprometido/Previsto — alimenta o clique "expandir por centro"
 * na linha do grupo sem reconsultar o ERP.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute('IF OBJECT_ID(\'msoft.FluxoCaixa_PagarCentro\') IS NOT NULL
             DROP TABLE msoft.FluxoCaixa_PagarCentro');
execute("
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
");
echo "Tabela msoft.FluxoCaixa_PagarCentro criada.\n";
