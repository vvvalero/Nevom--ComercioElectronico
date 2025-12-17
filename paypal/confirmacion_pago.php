<?php
require '../config/conexion.php';
require '../config/procesador_paypal.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Verificar autenticación
$userId = $_SESSION['user_id'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;
if ($_SESSION['user_role'] !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

$datosCompra = $_SESSION['datos_compra_paypal'] ?? null;
$carrito = $_SESSION['carrito_paypal'] ?? [];
$procesado = false;
$mensaje = '';
$tipo_mensaje = 'success';
$numeroPedido = '';

if (!empty($datosCompra) && !empty($carrito)) {
    try {
        // Obtener cliente_id
        $stmt = $conexion->prepare("SELECT id FROM cliente WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $clienteId = $stmt->get_result()->fetch_assoc()['id'] ?? null;
        $stmt->close();

        if (!$clienteId) throw new Exception('No se encontró el perfil de cliente');

        $conexion->begin_transaction();

        // Crear una sola compra
        $stmt = $conexion->prepare("INSERT INTO compra () VALUES ()");
        if (!$stmt->execute()) throw new Exception('Error al crear compra');
        $compraId = $conexion->insert_id;
        $stmt->close();

        // Crear líneas de compra
        foreach ($carrito as $movilId => $cantidad) {
            $stmt = $conexion->prepare("INSERT INTO linea_compra (idMovil, cantidad, idCompra) VALUES (?, ?, ?)");
            $stmt->bind_param('iii', $movilId, $cantidad, $compraId);
            if (!$stmt->execute()) throw new Exception('Error al crear línea de compra');
            $stmt->close();
        }

        // Crear pedido
        $precioTotal = $datosCompra['total'];
        $cantidadTotal = array_sum($carrito);
        $numSeguimiento = 'NV-' . date('Ymd-His') . '-' . rand(100, 999);

        $stmt = $conexion->prepare("INSERT INTO pedido (numSeguimiento, precioTotal, cantidadTotal, formaPago, idCompra, idCliente, estado) VALUES (?, ?, ?, 'paypal', ?, ?, 'procesando')");
        $stmt->bind_param('sddii', $numSeguimiento, $precioTotal, $cantidadTotal, $compraId, $clienteId);
        if (!$stmt->execute()) throw new Exception('Error al crear pedido');
        $pedidoId = $conexion->insert_id;
        $stmt->close();

        // Verificar stock disponible y actualizar
        foreach ($carrito as $movilId => $cantidad) {
            $stmt = $conexion->prepare("SELECT marca, modelo, stock FROM movil WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i', $movilId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("El producto con ID $movilId ya no está disponible");
            }
            $movil = $result->fetch_assoc();
            $stmt->close();

            if ($movil['stock'] < $cantidad) {
                throw new Exception("Stock insuficiente para {$movil['marca']} {$movil['modelo']}. Solo quedan {$movil['stock']} unidades");
            }

            $nuevoStock = $movil['stock'] - $cantidad;
            $stmtUpdate = $conexion->prepare("UPDATE movil SET stock = ? WHERE id = ?");
            $stmtUpdate->bind_param('ii', $nuevoStock, $movilId);
            if (!$stmtUpdate->execute()) throw new Exception('Error al actualizar stock');
            $stmtUpdate->close();
        }

        $conexion->commit();

        // Limpiar sesión
        unset($_SESSION['carrito'], $_SESSION['carrito_paypal'], $_SESSION['datos_compra_paypal'], $_SESSION['pago_procesado']);

        $procesado = true;
        $mensaje = "¡Pago confirmado! Tu pedido ha sido creado.";
        $numeroPedido = $numSeguimiento;
        // Logging eliminado

        // Redirigir a página unificada de confirmación
        header('Location: ../carrito/confirmacion_pedido.php?numero_pedido=' . urlencode($numSeguimiento));
        exit;
    } catch (Exception $e) {
        $conexion->rollback();
        $tipo_mensaje = 'danger';
        $mensaje = 'Error: ' . $e->getMessage();
        // Logging eliminado
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['mensaje_tipo'] = 'danger';
        header('Location: ../carrito/carrito.php');
        exit;
    }
} else {
    $tipo_mensaje = 'warning';
    $mensaje = 'No se encontraron datos de compra. Vuelve a intentar.';
    // Logging eliminado
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: ../carrito/carrito.php');
    exit;
}

$conexion->close();