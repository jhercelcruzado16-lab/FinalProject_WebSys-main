<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'POST request required.', [], 405);
}
// Admin dashboards have a session-bound CSRF token; regular student sessions do not.
if (!empty($_SESSION['admin_csrf_token'])) {
    requireCsrfToken();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

jsonResponse(true, 'You have been logged out.');