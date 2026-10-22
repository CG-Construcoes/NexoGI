<?php
declare(strict_types=1);
/**
 * Inclui a coluna Exibir em msoft.FluxoCaixa_Grupo (rodar 1x, linha
 * de comando):
 *   php scripts/migrar_grupo_exibir.php
 *
 * Exibir = checkbox do item 3 do Acompanhamento Financeiro: grupo com
 * 'Não' nao aparece em nenhum item da tela nem entra no recalculo do
 * Atualizar. Default 'Sim' (todos os grupos visiveis).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("IF COL_LENGTH('msoft.FluxoCaixa_Grupo', 'Exibir') IS NULL
             ALTER TABLE msoft.FluxoCaixa_Grupo ADD Exibir CHAR(3) NOT NULL
             CONSTRAINT DF_FCGrupo_Exibir DEFAULT 'Sim'");

echo "Coluna Exibir em msoft.FluxoCaixa_Grupo verificada/criada.\n";
