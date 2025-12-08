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
$userId = $_SESSION['user_id'] ?? null;

if ($userRole !== 'client' || !$userId) {
    header('Location: ../auth/signin.php');
    exit;
}

// ✅ NUEVO: Obtener el cliente_id basándose en el user_id
$stmtCliente = $conexion->prepare("SELECT id FROM cliente WHERE user_id = ?");
$stmtCliente->bind_param('i', $userId);
$stmtCliente->execute();
$resultCliente = $stmtCliente->get_result();

if ($resultCliente->num_rows === 0) {
    $_SESSION['mensaje'] = 'Error: No se encontró el perfil de cliente asociado';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: carrito.php');
    exit;
}

$clienteData = $resultCliente->fetch_assoc();
$clienteId = $clienteData['id']; // Este es el ID correcto para la tabla pedido
$stmtCliente->close();

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrito.php');
    exit;
}

// Verificar que haya productos en el carrito
if (empty($_SESSION['carrito'])) {
    $_SESSION['mensaje'] = 'El carrito está vacío';
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: carrito.php');
    exit;
}

// Obtener y validar forma de pago
$formaPago = trim($_POST['forma_pago'] ?? '');
$formasPagoValidas = ['tarjeta', 'transferencia', 'efectivo', 'paypal'];

if (!in_array($formaPago, $formasPagoValidas)) {
    $_SESSION['mensaje'] = 'Selecciona una forma de pago válida';
    $_SESSION['mensaje_tipo'] = 'danger';
    header('Location: carrito.php');
    exit;
}

// Calcular totales y preparar datos
$precioTotal = 0;
$cantidadTotal = 0;
$productosComprados = [];

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Recorrer el carrito y validar disponibilidad de cada producto
    foreach ($_SESSION['carrito'] as $movilId => $cantidad) {
        // Obtener datos del móvil con bloqueo de fila (FOR UPDATE)
        $stmt = $conexion->prepare("SELECT id, marca, modelo, precio, stock FROM movil WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $movilId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("El producto con ID $movilId ya no está disponible");
        }

        $movil = $result->fetch_assoc();
        $stmt->close();

        // Verificar stock disponible
        if ($movil['stock'] < $cantidad) {
            throw new Exception("Stock insuficiente para {$movil['marca']} {$movil['modelo']}. Solo quedan {$movil['stock']} unidades");
        }

        // Calcular subtotal
        $subtotal = $movil['precio'] * $cantidad;
        $precioTotal += $subtotal;
        $cantidadTotal += $cantidad;

        // Guardar datos para crear líneas de compra
        $productosComprados[] = [
            'id' => $movil['id'],
            'cantidad' => $cantidad,
            'precio' => $movil['precio'],
            'marca' => $movil['marca'],
            'modelo' => $movil['modelo']
        ];

        // Actualizar stock del móvil (restar la cantidad comprada)
        $nuevoStock = $movil['stock'] - $cantidad;
        $stmtUpdate = $conexion->prepare("UPDATE movil SET stock = ? WHERE id = ?");
        $stmtUpdate->bind_param('ii', $nuevoStock, $movilId);

        if (!$stmtUpdate->execute()) {
            throw new Exception("Error al actualizar el stock del producto");
        }
        $stmtUpdate->close();
    }

    // Agregar costo de envío si es necesario
    $costoEnvio = ($precioTotal >= 50) ? 0 : 5.00;
    $precioTotal += $costoEnvio;

    // === CREAR REGISTROS EN LA BASE DE DATOS ===

    // 1. Crear una sola compra (encabezado)
    $stmtCompra = $conexion->prepare("INSERT INTO compra () VALUES ()");
    if (!$stmtCompra->execute()) {
        throw new Exception("Error al crear compra");
    }
    $compraId = $conexion->insert_id;
    $stmtCompra->close();

    // 2. Crear líneas de compra para cada producto
    foreach ($productosComprados as $producto) {
        $stmtLineaCompra = $conexion->prepare("INSERT INTO linea_compra (idMovil, cantidad, idCompra) VALUES (?, ?, ?)");
        $stmtLineaCompra->bind_param('iii', $producto['id'], $producto['cantidad'], $compraId);

        if (!$stmtLineaCompra->execute()) {
            throw new Exception("Error al crear línea de compra");
        }
        $stmtLineaCompra->close();
    }

    // 3. Crear un solo pedido con la compra
    $estado = 'procesando';
    $cantidadTotalFloat = (float)$cantidadTotal;
    $numSeguimiento = 'NV-' . date('Ymd-His') . '-' . rand(100, 999);

    $stmtPedido = $conexion->prepare("INSERT INTO pedido (numSeguimiento, precioTotal, cantidadTotal, formaPago, idCompra, idCliente, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtPedido->bind_param('sddsiis', $numSeguimiento, $precioTotal, $cantidadTotalFloat, $formaPago, $compraId, $clienteId, $estado);

    if (!$stmtPedido->execute()) {
        throw new Exception("Error al crear pedido");
    }
    $pedidoId = $conexion->insert_id;
    $stmtPedido->close();

    // Confirmar transacción
    $conexion->commit();

    // Limpiar carrito después de compra exitosa
    $_SESSION['carrito'] = [];

    // Mensaje de éxito
    $_SESSION['mensaje'] = "¡Compra realizada con éxito! Total: " . number_format($precioTotal, 2) . "€";
    $_SESSION['mensaje_tipo'] = 'success';

    // Redirigir a página de confirmación
    header('Location: confirmacion_pedido.php?numero_pedido=' . urlencode($numSeguimiento));
    exit;
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();

    $_SESSION['mensaje'] = "Error al procesar la compra: " . $e->getMessage();
    $_SESSION['mensaje_tipo'] = 'danger';

    header('Location: carrito.php');
    exit;
} finally {
    $conexion->close();
}
