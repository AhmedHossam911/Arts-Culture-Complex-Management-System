<?php
require_once 'includes/config.php';


$auth = new Auth();


$auth->logout();


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}


session_destroy();


if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}


$_SESSION['success'] = 'You have been successfully logged out.';
header('Location: login.php');
exit();
?>
