<?php

require_once __DIR__ . '/../config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'POST request required.', [], 405);
}


$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$requestedRole = $_POST['role'] ?? 'student';


/*
|--------------------------------------------------------------------------
| Validate account type
|--------------------------------------------------------------------------
*/

if (!in_array($requestedRole, ['student', 'admin'], true)) {
    jsonResponse(false, 'Please choose a valid account type.', [], 422);
}


/*
|--------------------------------------------------------------------------
| Validate TIP email
|--------------------------------------------------------------------------
*/

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !str_ends_with($email, '@tip.edu.ph')
) {
    jsonResponse(
        false,
        'Please use your TIP institutional email.',
        [],
        422
    );
}


/*
|--------------------------------------------------------------------------
| Find user
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT * FROM users WHERE email=? LIMIT 1"
);

$stmt->execute([$email]);

$user = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Check password
|--------------------------------------------------------------------------
*/

if (!$user || !password_verify($password, $user['password'])) {

    jsonResponse(
        false,
        'Incorrect email or password.',
        [],
        401
    );
}


/*
|--------------------------------------------------------------------------
| Check account role
|--------------------------------------------------------------------------
*/

if ($user['role'] !== $requestedRole) {

    jsonResponse(
        false,
        'This account does not have the selected account type.',
        [],
        403
    );
}

// A login attempt replaces any unfinished registration verification in this session.
unset($_SESSION['pending_registration']);

if ($user['role'] === 'admin') {

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];

    jsonResponse(
        true,
        'Login successful.',
        [
            'requires_2fa' => false,
            'user' => $user
        ]
    );
}


// Stage the authenticated account. The browser switches to the verification
// screen immediately, then requests the code from send-login-verification.php.
$_SESSION['pending_2fa_user_id'] = (int) $user['id'];
$_SESSION['pending_2fa_email'] = $email;
unset(
    $_SESSION['pending_2fa_code_hash'],
    $_SESSION['pending_2fa_expires'],
    $_SESSION['pending_2fa_last_sent']
);

jsonResponse(
    true,
    'Credentials confirmed. Preparing your verification code.',
    ['requires_2fa' => true]
);
