<?php
declare(strict_types=1);
/**
 * Cria a tabela de ordenacao dos grupos do Fluxo de Caixa
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_grupo_ordem.php
 *
 * msoft.FluxoCaixa_GrupoOrdem: ordem de exibicao/impressao dos grupos
 * na tela Fluxo de Caixa (setas subir/descer). Semeado com a ordem
 * alfabetica atual (passos de 10).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.FluxoCaixa_GrupoOrdem') IS NULL
    CREATE TABLE msoft.FluxoCaixa_GrupoOrdem (
        FCGrupo_Id  INT PRIMARY KEY
            REFERENCES msoft.FluxoCaixa_Grupo(FCGrupo_Id) ON DELETE CASCADE,
        Ordem       INT NOT NULL
    );
");

// semeia os grupos sem ordem (ordem atual = alfabetica, passos de 10)
execute("
INSERT INTO msoft.FluxoCaixa_GrupoOrdem (FCGrupo_Id, Ordem)
    SELECT g.FCGrupo_Id,
           (ROW_NUMBER() OVER (ORDER BY g.FCGrupo_Nome)
            + ISNULL((SELECT MAX(o.Ordem) / 10 FROM msoft.FluxoCaixa_GrupoOrdem o), 0)) * 10
    FROM msoft.FluxoCaixa_Grupo g
    WHERE NOT EXISTS (SELECT 1 FROM msoft.FluxoCaixa_GrupoOrdem o
                      WHERE o.FCGrupo_Id = g.FCGrupo_Id);
");
echo "Tabela msoft.FluxoCaixa_GrupoOrdem verificada/criada e semeada.\n";
