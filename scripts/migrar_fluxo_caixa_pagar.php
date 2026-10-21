<?php
declare(strict_types=1);
/**
 * Cria a tabela do item 5 (CONTAS A PAGAR) do Acompanhamento Financeiro
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_fluxo_caixa_pagar.php
 *
 * msoft.FluxoCaixa_Pagar: 1 linha por FCGrupo_Id x Mes ('YYYY-MM') — os
 * valores Comprometido/Previsto do ultimo calculo disparado pelo botao
 * Atualizar. A tela lista somente o que esta gravado (form leve);
 * o recalculo so acontece no clique.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.FluxoCaixa_Pagar') IS NULL
    CREATE TABLE msoft.FluxoCaixa_Pagar (
        FCGrupo_Id        INT           NOT NULL
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Mes               CHAR(7)       NOT NULL,
        Comprometido      NUMERIC(14,2) NULL,
        Previsto          NUMERIC(14,2) NULL,
        UltimaAtualizacao DATETIME      NULL,
        CONSTRAINT PK_FluxoCaixa_Pagar PRIMARY KEY (FCGrupo_Id, Mes)
    );
");
echo "Tabela msoft.FluxoCaixa_Pagar verificada/criada.\n";
