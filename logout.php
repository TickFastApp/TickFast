<?php
require_once './config/db_connection.php';

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir completamente la sesión, también se debe borrar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al inicio con mensaje de confirmación
header('Location: index.php?message=logout_success');
exit;
?>