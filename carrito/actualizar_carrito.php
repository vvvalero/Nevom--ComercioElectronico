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
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'mensaje' => 'No autorizado']);
        exit;
    }
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
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';

if ($movilId <= 0 || $cantidad < 0) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);
        exit;
    }
    $_SESSION['mensaje'] = 'Datos inválidos';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: carrito.php');
    exit;
}

// Si la cantidad es 0, eliminar del carrito
if ($cantidad === 0) {
    unset($_SESSION['carrito'][$movilId]);
    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'mensaje' => 'Producto eliminado del carrito',
            'accion' => 'eliminar'
        ]);
        exit;
    }
    $_SESSION['mensaje'] = 'Producto eliminado del carrito';
    $_SESSION['mensaje_tipo'] = 'info';
    header('Location: carrito.php');
    exit;
}

// Verificar stock disponible
$stmt = $conexion->prepare("SELECT marca, modelo, precio, stock FROM movil WHERE id = ?");
$stmt->bind_param('i', $movilId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'mensaje' => 'Producto no encontrado']);
        exit;
    }
    $_SESSION['mensaje'] = 'Producto no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    $stmt->close();
    $conexion->close();
    header('Location: carrito.php');
    exit;
}

$movil = $result->fetch_assoc();
$stmt->close();

// Validar que no exceda el stock
if ($cantidad > $movil['stock']) {
    if ($isAjax) {
        echo json_encode([
            'success' => false, 
            'mensaje' => "Solo hay {$movil['stock']} unidades disponibles de {$movil['marca']} {$movil['modelo']}",
            'stock_disponible' => $movil['stock']
        ]);
        exit;
    }
    $_SESSION['mensaje'] = "Solo hay {$movil['stock']} unidades disponibles de {$movil['marca']} {$movil['modelo']}";
    $_SESSION['mensaje_tipo'] = 'warning';
    $conexion->close();
    header('Location: carrito.php');
    exit;
}

// Actualizar cantidad en el carrito
$_SESSION['carrito'][$movilId] = $cantidad;

// Si es AJAX, calcular y devolver los nuevos totales
if ($isAjax) {
    $totalCarrito = 0;
    $cantidadTotal = 0;
    
    foreach ($_SESSION['carrito'] as $id => $cant) {
        $stmtTotal = $conexion->prepare("SELECT precio FROM movil WHERE id = ?");
        $stmtTotal->bind_param('i', $id);
        $stmtTotal->execute();
        $resultTotal = $stmtTotal->get_result();
        if ($prod = $resultTotal->fetch_assoc()) {
            $totalCarrito += $prod['precio'] * $cant;
            $cantidadTotal += $cant;
        }
        $stmtTotal->close();
    }
    
    $conexion->close();
    
    $subtotal = $movil['precio'] * $cantidad;
    $costoEnvio = $totalCarrito >= 50 ? 0 : 5;
    $totalFinal = $totalCarrito + $costoEnvio;
    
    echo json_encode([
        'success' => true,
        'mensaje' => 'Cantidad actualizada',
        'subtotal' => number_format($subtotal, 2),
        'total_carrito' => number_format($totalCarrito, 2),
        'cantidad_total' => $cantidadTotal,
        'costo_envio' => number_format($costoEnvio, 2),
        'envio_gratis' => $totalCarrito >= 50,
        'falta_envio_gratis' => $totalCarrito < 50 ? number_format(50 - $totalCarrito, 2) : 0,
        'total_final' => number_format($totalFinal, 2)
    ]);
    exit;
}

$conexion->close();
$_SESSION['mensaje'] = 'Cantidad actualizada correctamente';
$_SESSION['mensaje_tipo'] = 'success';

header('Location: carrito.php');
exit;
?>
