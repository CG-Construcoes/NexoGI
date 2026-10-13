<?php
declare(strict_types=1);

/**
 * Envia e-mail. Modos (env EMAIL_MODO):
 *  - 'log'  (padrao): grava em storage/emails.log, sem envio real — usado em testes
 *  - 'smtp': envia via SMTP (Google Workspace) com PHPMailer
 * Retorna true se o e-mail foi enviado/gravado; nunca expoe credenciais em erros.
 */
function enviar_email(string $para, string $assunto, string $corpoHtml): bool
{
    if (env('EMAIL_MODO', 'log') !== 'smtp') {
        return gravar_email_log($para, $assunto, $corpoHtml);
    }
    try {
        require_once '/usr/share/php/libphp-phpmailer/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->Port = (int) env('SMTP_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USER');
        $mail->Password = env('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(env('SMTP_FROM') ?: env('SMTP_USER'), env('SMTP_FROM_NAME', 'Msoft ERP'));
        $mail->addAddress($para);
        $mail->Subject = $assunto;
        $mail->isHTML(true);
        $mail->Body = $corpoHtml;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>', '<br/>'], "\n", $corpoHtml)));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Msoft email: ' . $e->getMessage());
        return false;
    }
}

/** Guarda o e-mail em arquivo (modo de teste). O corpo vai em HTML para permitir extrair links. */
function gravar_email_log(string $para, string $assunto, string $corpoHtml): bool
{
    $dir = ROOT . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $txt = "===== " . date('Y-m-d H:i:s') . " =====\nPara: $para\nAssunto: $assunto\n$corpoHtml\n\n";
    return file_put_contents($dir . '/emails.log', $txt, FILE_APPEND | LOCK_EX) !== false;
}
