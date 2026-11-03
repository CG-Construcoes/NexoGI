<?php
require dirname(__DIR__) . '/app/bootstrap.php';
// Coluna Cor (hex suave) no cartao do kanban: msoft.Atividades.Cor
$r = query("SELECT 1 x FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA='msoft' AND TABLE_NAME='Atividades' AND COLUMN_NAME='Cor'");
if (!$r) {
    execute('ALTER TABLE msoft.Atividades ADD Cor varchar(20) NULL');
    echo "coluna Cor criada\n";
} else {
    echo "coluna Cor ja existe\n";
}
