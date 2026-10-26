<?php
declare(strict_types=1);
/**
 * Inclui a coluna Avulso_ValorNoSaldo em msoft.GrupoCentro_Avulso
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_avulso_no_saldo.php
 *
 * Avulso_ValorNoSaldo = quanto daquele avulso JA esta somado no
 * SaldoGrupo gravado (snapshot). O botao Salvar do fluxo de caixa
 * aplica so a diferenca (novo - noSaldo) no saldo e nas projecoes;
 * o recalculo (Atualizar) sincroniza ValorNoSaldo = Avulso_Valor.
 * Backfill 0 = "nada deste avulso contabilizado no saldo atual".
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("IF COL_LENGTH('msoft.GrupoCentro_Avulso', 'Avulso_ValorNoSaldo') IS NULL
             ALTER TABLE msoft.GrupoCentro_Avulso ADD Avulso_ValorNoSaldo float NULL");
execute('UPDATE msoft.GrupoCentro_Avulso SET Avulso_ValorNoSaldo = 0
         WHERE Avulso_ValorNoSaldo IS NULL');

echo "Coluna Avulso_ValorNoSaldo em msoft.GrupoCentro_Avulso verificada/criada.\n";
