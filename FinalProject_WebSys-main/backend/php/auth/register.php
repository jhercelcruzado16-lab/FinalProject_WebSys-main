<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'POST request required.', [], 405);
}

$first = trim($_POST['first_name'] ?? '');
$middle = trim($_POST['middle_name'] ?? '');
$last = trim($_POST['last_name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['password2'] ?? '');
$course = trim($_POST['course'] ?? '');
$campus = trim($_POST['campus'] ?? '');

if (
    !$first ||
    !$last ||
    !$studentId ||
    !$email ||
    !$password ||
    !$course ||
    !$campus
) {
    jsonResponse(
        false,
        'Please complete all required fields.',
        [],
        422
    );
}

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !str_ends_with($email, '@tip.edu.ph')
) {
    jsonResponse(
        false,
        'Only @tip.edu.ph emails are allowed.',
        [],
        422
    );
}

if ($password !== $confirm) {
    jsonResponse(
        false,
        'Passwords do not match.',
        [],
        422
    );
}

if (strlen($password) < 6) {
    jsonResponse(
        false,
        'Password must be at least 6 characters.',
        [],
        422
    );
}

/*
 * CHECK IF EMAIL OR STUDENT ID ALREADY EXISTS
 */

$stmt = $pdo->prepare(
    "SELECT id FROM users
     WHERE email = ? OR student_id = ?
     LIMIT 1"
);

$stmt->execute([
    $email,
    $studentId
]);

$existing = $stmt->fetch();

if ($existing) {

    $checkEmail = $pdo->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    $checkEmail->execute([$email]);

    if ($checkEmail->fetch()) {
        jsonResponse(
            false,
            'An account with that email already exists.',
            [],
            409
        );
    }

    jsonResponse(
        false,
        'That Student ID is already registered.',
        [],
        409
    );
}

/*
 * STORE PENDING VERIFICATION
 */

$_SESSION['pending_registration'] = [
    'first_name' => $first,
    'middle_name' => $middle ?: null,
    'last_name' => $last,
    'student_id' => $studentId,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'course' => $course,
    'campus' => $campus,
];
unset($_SESSION['pending_2fa_user_id']);
$_SESSION['pending_2fa_email'] = $email;
unset(
    $_SESSION['pending_2fa_code_hash'],
    $_SESSION['pending_2fa_expires'],
    $_SESSION['pending_2fa_last_sent']
);

jsonResponse(
    true,
    'Continue to email verification.',
    ['requires_2fa' => true]
);