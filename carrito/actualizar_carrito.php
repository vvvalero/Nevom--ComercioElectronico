<?php
require '../config/conexion.php';

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

// Obtener y validar datos
$movilId = (int) ($_POST['movil_id'] ?? 0);
$cantidad = (int) ($_POST['cantidad'] ?? 1);

if ($movilId <= 0 || $cantidad < 0) {
    $_SESSION['mensaje'] = 'Datos inválidos';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: carrito.php');
    exit;
}

// Si la cantidad es 0, eliminar del carrito
if ($cantidad === 0) {
    unset($_SESSION['carrito'][$movilId]);
    $_SESSION['mensaje'] = 'Producto eliminado del carrito';
    $_SESSION['mensaje_tipo'] = 'info';
    header('Location: carrito.php');
    exit;
}

// Verificar stock disponible
$stmt = $conexion->prepare("SELECT marca, modelo, stock FROM movil WHERE id = ?");
$stmt->bind_param('i', $movilId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['mensaje'] = 'Producto no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    $stmt->close();
    $conexion->close();
    header('Location: carrito.php');
    exit;
}

$movil = $result->fetch_assoc();
$stmt->close();
$conexion->close();

// Validar que no exceda el stock
if ($cantidad > $movil['stock']) {
    $_SESSION['mensaje'] = "Solo hay {$movil['stock']} unidades disponibles de {$movil['marca']} {$movil['modelo']}";
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: carrito.php');
    exit;
}

// Actualizar cantidad en el carrito
$_SESSION['carrito'][$movilId] = $cantidad;

$_SESSION['mensaje'] = 'Cantidad actualizada correctamente';
$_SESSION['mensaje_tipo'] = 'success';

header('Location: carrito.php');
exit;
?>
