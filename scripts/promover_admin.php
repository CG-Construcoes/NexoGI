<?php
declare(strict_types=1);
// Promove um usuario a admin. Uso: php scripts/promover_admin.php email@dominio
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$email = strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    exit("Uso: php scripts/promover_admin.php email@dominio\n");
}
execute('UPDATE msoft.Usuarios SET Admin = 1 WHERE Email = ?', [$email]);
$r = query('SELECT Email, Nome, Admin, Status FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
echo $r ? "{$r['Email']} | {$r['Nome']} | Admin={$r['Admin']} | Status={$r['Status']}\n" : "Usuario nao encontrado.\n";
