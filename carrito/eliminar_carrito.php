<?php
// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Verificar que el usuario esté logueado como cliente
$userRole = $_SESSION['user_role'] ?? null;
if ($userRole !== 'client') {
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrito.php');
    exit;
}

// Obtener y validar el ID del móvil
$movilId = (int) ($_POST['movil_id'] ?? 0);

if ($movilId <= 0) {
    $_SESSION['mensaje'] = 'Datos inválidos';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: carrito.php');
    exit;
}

// Eliminar del carrito
if (isset($_SESSION['carrito'][$movilId])) {
    unset($_SESSION['carrito'][$movilId]);
    $_SESSION['mensaje'] = 'Producto eliminado del carrito';
    $_SESSION['mensaje_tipo'] = 'success';
} else {
    $_SESSION['mensaje'] = 'El producto no estaba en el carrito';
    $_SESSION['mensaje_tipo'] = 'warning';
}

header('Location: carrito.php');
exit;
?>
