<?php
declare(strict_types=1);
/**
 * Funcoes dos usuarios (rodar 1x, linha de comando):
 *   php scripts/migrar_atividades_funcoes.php
 * Cria msoft.Funcoes (Nome / Setor / Gestor), semeia a lista padrao,
 * cria Usuarios.Funcao_Id (FK), migra os valores da coluna texto
 * Usuarios.Funcao (criada em migrar_atividades.php) e remove essa coluna.
 * Base CGGestao, schema msoft.
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("
IF OBJECT_ID('msoft.Funcoes') IS NULL
    CREATE TABLE msoft.Funcoes (
        Funcao_Id     INT IDENTITY(1,1) PRIMARY KEY,
        Funcao_Nome   NVARCHAR(60) NOT NULL,
        Funcao_Setor  NVARCHAR(60) NULL,
        Funcao_Gestor BIT NOT NULL DEFAULT 0
    );
");

// Semente padrao (idempotente por nome)
$sementes = [
    ['Diretor',              null,          1],
    ['Financeiro-Gerente',   'Financeiro',  1],
    ['Financeiro',           'Financeiro',  0],
    ['DP-Gerente',           'DP',          1],
    ['DP',                   'DP',          0],
    ['RH-Gerente',           'RH',          1],
    ['RH',                   'RH',          0],
    ['Suprimentos-Gerente',  'Suprimentos', 1],
    ['Suprimentos',          'Suprimentos', 0],
    ['Engenharia-Gerente',   'Engenharia',  1],
    ['Engenharia',           'Engenharia',  0],
];
foreach ($sementes as [$nome, $setor, $gestor]) {
    execute(
        'IF NOT EXISTS (SELECT 1 FROM msoft.Funcoes WHERE Funcao_Nome = ?)
             INSERT INTO msoft.Funcoes (Funcao_Nome, Funcao_Setor, Funcao_Gestor) VALUES (?, ?, ?)',
        [$nome, $nome, $setor, $gestor]
    );
}
echo "msoft.Funcoes criada e semeada (" . count($sementes) . " funcoes).\n";

// Funcao_Id em Usuarios
execute("
IF COL_LENGTH('msoft.Usuarios', 'Funcao_Id') IS NULL
    ALTER TABLE msoft.Usuarios ADD Funcao_Id INT NULL REFERENCES msoft.Funcoes(Funcao_Id);
");

// Valores antigos (texto) sem funcao correspondente -> cria a funcao
execute("
INSERT INTO msoft.Funcoes (Funcao_Nome, Funcao_Setor, Funcao_Gestor)
SELECT DISTINCT u.Funcao, NULL,
       CASE WHEN u.Funcao IN ('Gestor', 'Coordenador') THEN 1 ELSE 0 END
FROM msoft.Usuarios u
WHERE u.Funcao IS NOT NULL AND LTRIM(RTRIM(u.Funcao)) <> ''
  AND NOT EXISTS (SELECT 1 FROM msoft.Funcoes f WHERE f.Funcao_Nome = u.Funcao);
");

// Amarra texto -> id e remove a coluna antiga
execute("
UPDATE u SET u.Funcao_Id = f.Funcao_Id
FROM msoft.Usuarios u
JOIN msoft.Funcoes f ON f.Funcao_Nome = u.Funcao
WHERE u.Funcao_Id IS NULL;
IF COL_LENGTH('msoft.Usuarios', 'Funcao') IS NOT NULL
    ALTER TABLE msoft.Usuarios DROP COLUMN Funcao;
");

echo "Usuarios.Funcao_Id OK; coluna texto Funcao migrada e removida.\n";
