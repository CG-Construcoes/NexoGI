<?php
declare(strict_types=1);
/**
 * Prepara msoft.Usuarios para o sistema PHP (rodar 1x, pela linha de comando):
 *   php scripts/migrar_senhas_php.php
 * - cria a coluna SenhaHashPHP (a coluna SenhaHash do sistema Python nao e alterada)
 * - gera uma senha provisoria para o admin@msoft.local e mostra na tela
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

execute("IF COL_LENGTH('msoft.Usuarios', 'SenhaHashPHP') IS NULL
         ALTER TABLE msoft.Usuarios ADD SenhaHashPHP NVARCHAR(255) NULL");
echo "Coluna msoft.Usuarios.SenhaHashPHP verificada/criada.\n";

$admin = 'admin@msoft.local';
$existe = query('SELECT SenhaHashPHP FROM msoft.Usuarios WHERE Email = ?', [$admin]);
if (!$existe) {
    exit("Usuario $admin nao encontrado.\n");
}
if (!empty($existe[0]['SenhaHashPHP']) && !in_array('--forcar', $argv, true)) {
    exit("O admin ja tem senha no sistema PHP. Use --forcar para gerar outra.\n");
}

$alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
$senha = '';
for ($i = 0; $i < 12; $i++) {
    $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}
execute('UPDATE msoft.Usuarios SET SenhaHashPHP = ? WHERE Email = ?', [password_hash($senha, PASSWORD_DEFAULT), $admin]);
echo "Senha provisoria do $admin no sistema PHP: $senha\n";
