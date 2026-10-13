<?php
declare(strict_types=1);
// Gera manualmente o link de definir senha para um e-mail ja cadastrado
// (contorna o limite de 5 min e nao depende do SMTP). Uso:
//   php scripts/gerar_link_acesso.php email@dominio [definir|redefinir]
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

$email = strtolower(trim($argv[1] ?? ''));
$tipo = $argv[2] ?? 'definir';
if ($email === '' || !in_array($tipo, ['definir', 'redefinir'], true)) {
    exit("Uso: php scripts/gerar_link_acesso.php email@dominio [definir|redefinir]\n");
}
$u = query('SELECT Id, Email, Status FROM msoft.Usuarios WHERE Email = ?', [$email])[0] ?? null;
if (!$u) {
    exit("E-mail nao cadastrado: $email\n");
}
$token = bin2hex(random_bytes(32));
$minutos = $tipo === 'definir' ? TOKEN_DEFINIR_MIN : TOKEN_REDEFINIR_MIN;
execute(
    'INSERT INTO msoft.TokensSenha (UsuarioId, TokenHash, Tipo, ExpiraEm, IpOrigem)
     VALUES (?, ?, ?, DATEADD(MINUTE, ' . $minutos . ', GETDATE()), ?)',
    [$u['Id'], hash('sha256', $token), $tipo, 'cli']
);
echo rtrim(env('APP_URL', 'http://localhost'), '/') . '/definir-senha?token=' . $token . "\n";
echo "Tipo: $tipo | Status do usuario: {$u['Status']} | validade: " . ($minutos / 60) . "h\n";
