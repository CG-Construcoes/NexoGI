<?php
declare(strict_types=1);
// Testa conexao + STARTTLS + autenticacao SMTP (sem enviar e-mail).
if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';
require_once '/usr/share/php/libphp-phpmailer/autoload.php';

$smtp = new PHPMailer\PHPMailer\SMTP();
try {
    if (!$smtp->connect(env('SMTP_HOST', 'smtp.gmail.com'), (int) env('SMTP_PORT', '587'), 15)) {
        exit("FALHA: nao conectou no servidor SMTP.\n");
    }
    echo "conexao OK\n";
    $smtp->hello(gethostname() ?: 'localhost');
    if (!$smtp->startTLS()) {
        exit("FALHA: STARTTLS recusado.\n");
    }
    echo "STARTTLS OK\n";
    $smtp->hello(gethostname() ?: 'localhost'); // EHLO de novo apos o TLS
    if ($smtp->authenticate(env('SMTP_USER'), env('SMTP_PASSWORD'))) {
        echo "AUTENTICACAO OK - credenciais aceitas pelo Google\n";
    } else {
        echo "AUTENTICACAO FALHOU - " . ($smtp->getError()['error'] ?? 'sem detalhe') . "\n";
    }
    $smtp->quit();
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
}
