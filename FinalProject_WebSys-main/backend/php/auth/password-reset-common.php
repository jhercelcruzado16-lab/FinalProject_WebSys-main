<?php

require_once __DIR__ . '/../config/mailer.php';

function ensurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            user_id INT UNSIGNED PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            expires_at BIGINT UNSIGNED NOT NULL,
            requested_at BIGINT UNSIGNED NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );
}

function sendPasswordResetCode(string $email, string $code): bool
{
    try {
        $mail = createAppMailer();
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'ReSource Password Reset Code';
        $mail->Body = "<div style='font-family:Arial,sans-serif'><h2>ReSource Password Reset</h2><p>Your password reset code is:</p><h1 style='letter-spacing:8px'>{$code}</h1><p>This code expires in <strong>10 minutes</strong>.</p><p>If you did not request a password reset, you can ignore this email.</p></div>";
        $mail->AltBody = "Your ReSource password reset code is {$code}. It expires in 10 minutes.";
        $mail->send();
        return true;
    } catch (Throwable $error) {
        error_log('Password reset email failed: ' . $error->getMessage());
        return false;
    }
}