<?php
// enable gzip output buffering if available (reduces payload)
if (!headers_sent()) {
    ob_start('ob_gzhandler');
}

// ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// clear session data
$_SESSION = [];
session_unset();

// remove session cookie
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
);

// destroy session and release lock
session_destroy();
session_write_close();

// flush output buffer if present
if (ob_get_level()) {
    @ob_end_flush();
}

header('Location: index.php');
exit();
?>