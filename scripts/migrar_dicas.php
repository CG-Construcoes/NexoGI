<?php
declare(strict_types=1);
/**
 * Cria a tabela de Dicas exibidas durante o calculo do Acompanhamento
 * Financeiro. Rodar 1x, linha de comando:
 *   php scripts/migrar_dicas.php
 *
 * msoft.Dica: texto da dica (200) + uma flag 'Sim'/'Nao' por modulo
 * (Comercial, Contabilidade, Engenharia, Estoque, Financeiro, Suprimentos)
 * para direcionar a dica a cada area do sistema.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.Dica') IS NULL
    CREATE TABLE msoft.Dica (
        Dica_Id       INT IDENTITY(1,1) PRIMARY KEY,
        Dica          CHAR(200) NOT NULL,
        Comercial     CHAR(3) NOT NULL DEFAULT 'Não',
        Contabilidade CHAR(3) NOT NULL DEFAULT 'Não',
        Engenharia    CHAR(3) NOT NULL DEFAULT 'Não',
        Estoque       CHAR(3) NOT NULL DEFAULT 'Não',
        Financeiro    CHAR(3) NOT NULL DEFAULT 'Não',
        Suprimentos   CHAR(3) NOT NULL DEFAULT 'Não'
    );
");

echo "Tabela msoft.Dica verificada/criada.\n";
