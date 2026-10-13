<?php
declare(strict_types=1);
// Define a senha PHP de um usuario. Uso:
//   php scripts/definir_senha_cli.php email@dominio 'senha'
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$email = strtolower(trim($argv[1] ?? ''));
$senha = (string) ($argv[2] ?? '');
if ($email === '' || mb_strlen($senha) < 4) {
    exit("Uso: php scripts/definir_senha_cli.php email@dominio 'senha' (min. 4 caracteres)\n");
}
$u = query('SELECT Id FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
if (!$u) {
    exit("E-mail nao cadastrado: $email\n");
}
execute('UPDATE msoft.Usuarios SET SenhaHashPHP = ? WHERE Id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['Id']]);
$hash = query('SELECT SenhaHashPHP FROM msoft.Usuarios WHERE Id = ?', [$u['Id']])[0]['SenhaHashPHP'];
echo password_verify($senha, $hash) ? "Senha definida e conferida para $email\n" : "FALHA ao conferir a senha.\n";
