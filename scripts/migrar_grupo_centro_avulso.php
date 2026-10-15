<?php
declare(strict_types=1);
/**
 * Cria a tabela de valores avulsos dos grupos de centros do Fluxo de Caixa
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_grupo_centro_avulso.php
 *
 * msoft.GrupoCentro_Avulso: um ou mais itens "Valor Avulso" por grupo —
 * aparecem na lista de centros do grupo e o valor e digitado na hora do
 * calculo (nao fica gravado).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.GrupoCentro_Avulso') IS NULL
    CREATE TABLE msoft.GrupoCentro_Avulso (
        Avulso_Id        INT IDENTITY(1,1) PRIMARY KEY,
        FCGrupo_Id       INT NOT NULL
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Avulso_Descricao CHAR(60) NOT NULL
    );
ELSE IF COL_LENGTH('msoft.GrupoCentro_Avulso', 'Avulso_Descricao') IS NULL
    ALTER TABLE msoft.GrupoCentro_Avulso
        ADD Avulso_Descricao CHAR(60) NOT NULL DEFAULT '';
");
echo "Tabela msoft.GrupoCentro_Avulso verificada/criada.\n";
