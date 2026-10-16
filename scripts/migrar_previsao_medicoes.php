<?php
declare(strict_types=1);
/**
 * Cria as tabelas da Previsao de Medicoes (Engenharia > Previsoes > Medicoes)
 * (rodar 1x, linha de comando):
 *   php scripts/migrar_previsao_medicoes.php
 *
 * msoft.PrevisaoMedicaoCentro: centros de custo incluidos na previsao
 * (Centro_CodC unico = Empresa+Filial+Centro do ERP).
 * msoft.PrevisaoMedicao: medicoes previstas de cada centro
 * (descricao, valor liquido, emissao da NF opcional, previsao de recebimento).
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.PrevisaoMedicaoCentro') IS NULL
    CREATE TABLE msoft.PrevisaoMedicaoCentro (
        PMedC_Id     INT IDENTITY(1,1) PRIMARY KEY,
        Centro_CodC  CHAR(9) NOT NULL UNIQUE,
        CriadoEm     DATETIME NOT NULL DEFAULT GETDATE()
    );
");

execute("
IF OBJECT_ID('msoft.PrevisaoMedicao') IS NULL
    CREATE TABLE msoft.PrevisaoMedicao (
        PMed_Id        INT IDENTITY(1,1) PRIMARY KEY,
        PMedC_Id       INT NOT NULL
            REFERENCES msoft.PrevisaoMedicaoCentro(PMedC_Id) ON DELETE CASCADE,
        Med_Descricao  VARCHAR(120) NOT NULL,
        Med_Valor      NUMERIC(14,2) NOT NULL,
        Med_EmissaoNF  DATE NULL,
        Med_Previsao   DATE NOT NULL,
        CriadoEm       DATETIME NOT NULL DEFAULT GETDATE()
    );
");

echo "Tabelas msoft.PrevisaoMedicaoCentro e msoft.PrevisaoMedicao verificadas/criadas.\n";
