<?php
require '../config/conexion.php';

// Iniciar sesión
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

// Verificar que sea administrador
$userRole = $_SESSION['user_role'] ?? null;
if ($userRole !== 'admin') {
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensaje'] = 'Método no permitido';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_ventas.php');
    exit;
}

// Obtener y validar datos
$pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
$nuevoPrecio = filter_input(INPUT_POST, 'nuevo_precio', FILTER_VALIDATE_FLOAT);

// Validar datos
if (!$pedidoId || $nuevoPrecio === false || $nuevoPrecio < 0) {
    $_SESSION['mensaje'] = 'Datos inválidos. El precio debe ser un número positivo.';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_ventas.php');
    exit;
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Verificar que el pedido existe, es de tipo compra y está en estado procesando
    $sqlVerificar = "SELECT id, precioTotal, estado FROM pedido WHERE id = ? AND idVenta IS NOT NULL";
    $stmtVerificar = $conexion->prepare($sqlVerificar);
    
    if (!$stmtVerificar) {
        throw new Exception("Error al preparar consulta de verificación: " . $conexion->error);
    }
    
    $stmtVerificar->bind_param('i', $pedidoId);
    $stmtVerificar->execute();
    $resultado = $stmtVerificar->get_result();
    
    if ($resultado->num_rows === 0) {
        throw new Exception("Pedido no encontrado o no es una solicitud de compra");
    }
    
    $pedido = $resultado->fetch_assoc();
    $precioAnterior = $pedido['precioTotal'];
    $estadoActual = $pedido['estado'];
    $stmtVerificar->close();

    // Verificar que el pedido esté en estado procesando
    if ($estadoActual !== 'procesando') {
        throw new Exception("Solo se puede ajustar el precio de solicitudes pendientes de revisión");
    }

    // Actualizar precio del pedido
    $sqlActualizarPedido = "UPDATE pedido SET precioTotal = ? WHERE id = ?";
    $stmtActualizarPedido = $conexion->prepare($sqlActualizarPedido);
    
    if (!$stmtActualizarPedido) {
        throw new Exception("Error al preparar consulta de actualización de pedido: " . $conexion->error);
    }
    
    $stmtActualizarPedido->bind_param('di', $nuevoPrecio, $pedidoId);
    
    if (!$stmtActualizarPedido->execute()) {
        throw new Exception("Error al actualizar precio del pedido: " . $stmtActualizarPedido->error);
    }
    
    $stmtActualizarPedido->close();

    // También actualizar el precio del móvil en la tabla movil
    $sqlActualizarMovil = "UPDATE movil m
                           JOIN linea_venta lv ON m.id = lv.idMovil
                           JOIN venta v ON lv.id = v.idLineaVenta
                           JOIN pedido p ON v.id = p.idVenta
                           SET m.precio = ?
                           WHERE p.id = ?";
    $stmtActualizarMovil = $conexion->prepare($sqlActualizarMovil);
    
    if (!$stmtActualizarMovil) {
        throw new Exception("Error al preparar consulta de actualización de móvil: " . $conexion->error);
    }
    
    $stmtActualizarMovil->bind_param('di', $nuevoPrecio, $pedidoId);
    
    if (!$stmtActualizarMovil->execute()) {
        throw new Exception("Error al actualizar precio del móvil: " . $stmtActualizarMovil->error);
    }
    
    $stmtActualizarMovil->close();

    // Commit de la transacción
    $conexion->commit();

    // Mensaje de éxito
    $_SESSION['mensaje'] = "Precio de valoración actualizado: " . number_format($precioAnterior, 2) . "€ → " . number_format($nuevoPrecio, 2) . "€";
    $_SESSION['mensaje_tipo'] = 'success';

    // Log de la operación
    error_log("Admin ajustó precio de compra - Pedido: $pedidoId, Precio anterior: $precioAnterior, Nuevo precio: $nuevoPrecio");

} catch (Exception $e) {
    // Rollback en caso de error
    $conexion->rollback();
    
    $_SESSION['mensaje'] = 'Error al ajustar el precio: ' . $e->getMessage();
    $_SESSION['mensaje_tipo'] = 'danger';
    
    error_log("Error al ajustar precio de compra - Pedido: $pedidoId, Error: " . $e->getMessage());
}

// Redirigir de vuelta
header('Location: gestionar_ventas.php');
exit;
?>
