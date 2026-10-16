<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'POST request required.', [], 405);
}

$pendingRegistration = $_SESSION['pending_registration'] ?? null;
$userId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
$email = is_array($pendingRegistration)
    ? (string) ($pendingRegistration['email'] ?? '')
    : (string) ($_SESSION['pending_2fa_email'] ?? '');

if (($userId < 1 && !is_array($pendingRegistration)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'No verification request found. Please log in again.', [], 401);
}

if (!empty($_SESSION['pending_2fa_last_sent']) && time() - (int) $_SESSION['pending_2fa_last_sent'] < 60) {
    jsonResponse(false, 'Please wait a minute before requesting another verification code.', [], 429);
}

$code = (string) random_int(100000, 999999);
$_SESSION['pending_2fa_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
$_SESSION['pending_2fa_expires'] = time() + (5 * 60);
$_SESSION['pending_2fa_last_sent'] = time();

try {
    $sendStartedAt = microtime(true);
    sendVerificationCodeEmail($email, $code, is_array($pendingRegistration) ? 'registration' : 'login');
    error_log(
        'Login verification SMTP handoff took ' .
        round(microtime(true) - $sendStartedAt, 2) .
        ' seconds.'
    );
} catch (Throwable $error) {
    unset(
        $_SESSION['pending_2fa_code_hash'],
        $_SESSION['pending_2fa_expires']
    );
    error_log(
        'Login verification email failed after ' .
        round(microtime(true) - ($sendStartedAt ?? microtime(true)), 2) .
        ' seconds: ' . $error->getMessage()
    );
    jsonResponse(
        false,
        'We could not send your verification email. Please check the mail server configuration and try again.',
        [],
        503
    );
}

jsonResponse(true, 'Verification code sent to your TIP email.');
