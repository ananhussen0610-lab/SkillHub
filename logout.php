<?php
session_start();

// ניקוי כל משתני הסשן
$_SESSION = array();

// אם נעשה שימוש בעוגיות (cookies), יש למחוק גם אותן
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// הרס הסשן
session_destroy();

// הפניה לדף הבית או דף ההתחברות
header("Location: login.php");
exit();
?>