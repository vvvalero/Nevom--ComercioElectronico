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
    header('Location: gestionar_compras.php');
    exit;
}

// Obtener y validar datos
$pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
$nuevoEstado = trim($_POST['nuevo_estado'] ?? '');

// Validar datos
if (!$pedidoId || empty($nuevoEstado)) {
    $_SESSION['mensaje'] = 'Datos inválidos';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_compras.php');
    exit;
}

// Estados válidos para pedidos de compra
$estadosValidos = ['procesando', 'aprobado', 'rechazado', 'pagado'];
if (!in_array($nuevoEstado, $estadosValidos)) {
    $_SESSION['mensaje'] = 'Estado no válido';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: gestionar_compras.php');
    exit;
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Verificar que el pedido existe y es de tipo compra (idVenta IS NOT NULL)
    $sqlVerificar = "SELECT id, estado FROM pedido WHERE id = ? AND idVenta IS NOT NULL";
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
    $estadoAnterior = $pedido['estado'];
    $stmtVerificar->close();

    // Actualizar estado del pedido
    $sqlActualizar = "UPDATE pedido SET estado = ? WHERE id = ?";
    $stmtActualizar = $conexion->prepare($sqlActualizar);
    
    if (!$stmtActualizar) {
        throw new Exception("Error al preparar consulta de actualización: " . $conexion->error);
    }
    
    $stmtActualizar->bind_param('si', $nuevoEstado, $pedidoId);
    
    if (!$stmtActualizar->execute()) {
        throw new Exception("Error al actualizar estado: " . $stmtActualizar->error);
    }
    
    $stmtActualizar->close();

    // Commit de la transacción
    $conexion->commit();

    // Mensaje de éxito según el estado
    $mensajes = [
        'aprobado' => "Solicitud de compra #$pedidoId aprobada exitosamente",
        'rechazado' => "Solicitud de compra #$pedidoId rechazada",
        'pagado' => "Solicitud de compra #$pedidoId marcada como pagada",
        'procesando' => "Solicitud de compra #$pedidoId actualizada a pendiente de revisión"
    ];

    $_SESSION['mensaje'] = $mensajes[$nuevoEstado] ?? "Estado actualizado correctamente";
    $_SESSION['mensaje_tipo'] = 'success';

    // Log de la operación
    error_log("Admin actualizó estado de compra - Pedido: $pedidoId, Estado anterior: $estadoAnterior, Nuevo estado: $nuevoEstado");

} catch (Exception $e) {
    // Rollback en caso de error
    $conexion->rollback();
    
    $_SESSION['mensaje'] = 'Error al actualizar el estado: ' . $e->getMessage();
    $_SESSION['mensaje_tipo'] = 'danger';
    
    error_log("Error al actualizar estado de compra - Pedido: $pedidoId, Error: " . $e->getMessage());
}

// Redirigir de vuelta
header('Location: gestionar_compras.php');
exit;
?>
