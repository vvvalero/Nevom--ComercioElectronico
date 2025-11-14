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
$clienteId = $_SESSION['cliente_id'] ?? null;

if ($userRole !== 'client' || !$clienteId) {
    $_SESSION['redirect_after_login'] = 'index.php#productos';
    $_SESSION['mensaje'] = 'Debes iniciar sesión para agregar productos al carrito';
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Obtener y validar el ID del móvil
$movilId = (int) ($_POST['movil_id'] ?? 0);
$cantidad = (int) ($_POST['cantidad'] ?? 1);

if ($movilId <= 0 || $cantidad <= 0) {
    $_SESSION['mensaje'] = 'Datos inválidos';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: ../index.php#productos');
    exit;
}

// Verificar que el móvil existe y tiene stock
$stmt = $conexion->prepare("SELECT id, marca, modelo, stock FROM movil WHERE id = ? AND stock > 0");
$stmt->bind_param('i', $movilId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['mensaje'] = 'El producto no está disponible';
    $_SESSION['mensaje_tipo'] = 'danger';
    $stmt->close();
    $conexion->close();
    header('Location: ../index.php#productos');
    exit;
}

$movil = $result->fetch_assoc();
$stmt->close();
$conexion->close();

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Verificar stock disponible
$cantidadActual = $_SESSION['carrito'][$movilId] ?? 0;
$cantidadNueva = $cantidadActual + $cantidad;

if ($cantidadNueva > $movil['stock']) {
    $_SESSION['mensaje'] = "Solo hay {$movil['stock']} unidades disponibles de {$movil['marca']} {$movil['modelo']}";
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: ../index.php#productos');
    exit;
}

// Agregar al carrito
$_SESSION['carrito'][$movilId] = $cantidadNueva;

$_SESSION['mensaje'] = "✅ {$movil['marca']} {$movil['modelo']} agregado al carrito";
$_SESSION['mensaje_tipo'] = 'success';

// Redirigir al carrito o a la página de productos según preferencia
$redirect = $_POST['redirect'] ?? 'carrito';
if ($redirect === 'productos') {
    header('Location: ../index.php#productos');
} else {
    header('Location: carrito.php');
}
exit;
?>
