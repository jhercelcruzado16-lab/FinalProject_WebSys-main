<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Build a PHPMailer instance from the server's environment configuration.
 * Set MAIL_USERNAME and MAIL_PASSWORD in Apache/PHP; never commit credentials.
 */
function createAppMailer(): PHPMailer
{
    $username = trim((string) getenv('MAIL_USERNAME'));
    $password = preg_replace('/\s+/', '', trim((string) getenv('MAIL_PASSWORD')));

    if ($username === '' || $password === '') {
        throw new RuntimeException('MAIL_USERNAME and MAIL_PASSWORD must be configured.');
    }

    $host = trim((string) (getenv('MAIL_HOST') ?: 'smtp.gmail.com'));
    $port = filter_var(getenv('MAIL_PORT') ?: '587', FILTER_VALIDATE_INT);
    $encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')));
    $fromAddress = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: $username));
    $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'ReSource'));

    if ($host === '' || $port === false || $port < 1 || $port > 65535) {
        throw new RuntimeException('The SMTP host or port configuration is invalid.');
    }

    $secure = match ($encryption) {
        'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
        'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
        'none' => '',
        default => throw new RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.'),
    };

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = $secure;
    $mail->Port = $port;
    $mail->Timeout = 8;
    $mail->setFrom($fromAddress, $fromName);

    return $mail;
}

function sendVerificationCodeEmail(string $email, string $code, string $purpose): void
{
    $mail = createAppMailer();
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = $purpose === 'registration'
        ? 'ReSource Account Verification Code'
        : 'ReSource Login Verification Code';
    $mail->Body = "
        <div style='font-family:Arial,sans-serif;background:#f5f4f1;padding:24px'>
            <div style='max-width:560px;margin:auto;background:#fff;border:1px solid #e7e7e7;border-radius:16px;padding:28px'>
                <h2 style='margin-top:0;color:#171717'>ReSource email verification</h2>
                <p>Your verification code is:</p>
                <div style='padding:16px;background:#fff6dc;border:1px solid #f6d97d;border-radius:10px;text-align:center'>
                    <strong style='font-size:34px;letter-spacing:10px;color:#171717'>{$code}</strong>
                </div>
                <p>This code expires in <strong>5 minutes</strong>.</p>
                <p>If you did not request this code, you can ignore this email.</p>
            </div>
        </div>
    ";
    $mail->AltBody = "Your ReSource verification code is {$code}. It expires in 5 minutes.";
    $mail->send();
}
