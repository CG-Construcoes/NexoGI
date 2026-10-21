<?php
declare(strict_types=1);
/**
 * Inclui a coluna Bases em msoft.FluxoCaixa_Posicao (rodar 1x, linha
 * de comando):
 *   php scripts/migrar_posicao_bases.php
 *
 * Bases = combinacao de bases ERP ('MGA', 'CONV' ou 'MGA,CONV') usada
 * no ultimo Atualizar — os checkboxes DBMGA/DBCONV da tela gravam a
 * selecao junto com o snapshot do item 1 para que TODAS as rotinas da
 * tela (itens 1, 3, 4 e 5, na exibicao e no calculo) reflitam a ultima
 * selecao salva.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("IF COL_LENGTH('msoft.FluxoCaixa_Posicao', 'Bases') IS NULL
             ALTER TABLE msoft.FluxoCaixa_Posicao ADD Bases VARCHAR(10) NULL");

echo "Coluna Bases em msoft.FluxoCaixa_Posicao verificada/criada.\n";
