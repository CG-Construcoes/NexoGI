<?php
declare(strict_types=1);
// Lista usuarios e tokens pendentes (uso: php scripts/ver_usuarios_cli.php)
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

echo "== Usuarios ==\n";
foreach (query('SELECT Id, Email, Nome, Status, Admin, CriadoEm FROM msoft.Usuarios ORDER BY CriadoEm DESC') as $u) {
    echo "{$u['Id']} | {$u['Email']} | {$u['Nome']} | {$u['Status']} | Admin={$u['Admin']} | {$u['CriadoEm']}\n";
}
echo "\n== Tokens pendentes ==\n";
foreach (query('SELECT t.Tipo, u.Email, t.CriadoEm, t.ExpiraEm FROM msoft.TokensSenha t JOIN msoft.Usuarios u ON u.Id = t.UsuarioId WHERE t.UsadoEm IS NULL ORDER BY t.CriadoEm DESC') as $t) {
    echo "{$t['Tipo']} | {$t['Email']} | criado {$t['CriadoEm']} | expira {$t['ExpiraEm']}\n";
}
