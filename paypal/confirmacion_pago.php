<?php
require '../config/conexion.php';
require '../config/paypal_config.php';
require '../config/procesador_paypal.php';

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

// Verificar autenticación
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if ($userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener datos de la compra de sesión
$datosCompra = $_SESSION['datos_compra_paypal'] ?? null;
$carrito = $_SESSION['carrito_paypal'] ?? array();

// Variable para almacenar el resultado del procesamiento
$procesado = false;
$mensaje = '';
$tipo_mensaje = 'success';
$numeroPedido = '';

// Procesar el pago completado
if (!empty($datosCompra) && !empty($carrito)) {
    try {
        // Obtener cliente_id basándose en user_id
        $stmtCliente = $conexion->prepare("SELECT id FROM cliente WHERE user_id = ?");
        $stmtCliente->bind_param('i', $userId);
        $stmtCliente->execute();
        $resultCliente = $stmtCliente->get_result();
        
        if ($resultCliente->num_rows === 0) {
            throw new Exception('No se encontró el perfil de cliente');
        }
        
        $clienteData = $resultCliente->fetch_assoc();
        $clienteId = $clienteData['id'];
        $stmtCliente->close();

        // Comenzar transacción
        $conexion->begin_transaction();

        // Obtener datos del cliente para el pedido
        $stmtCliente = $conexion->prepare("SELECT nombre, apellidos, email, telefono, direccion FROM cliente WHERE id = ?");
        $stmtCliente->bind_param('i', $clienteId);
        $stmtCliente->execute();
        $cliente = $stmtCliente->get_result()->fetch_assoc();
        $stmtCliente->close();

        // Crear líneas de compra y compras antes del pedido
        $lineaCompraIds = [];
        $compraIds = [];
        $total_pedido = 0;
        
        foreach ($carrito as $movilId => $cantidad) {
            // Obtener precio del móvil
            $stmt = $conexion->prepare("SELECT precio FROM movil WHERE id = ?");
            $stmt->bind_param('i', $movilId);
            $stmt->execute();
            $result = $stmt->get_result();
            $movil = $result->fetch_assoc();
            $stmt->close();
            
            // Crear línea de compra
            $stmtLineaCompra = $conexion->prepare("INSERT INTO linea_compra (idMovil, cantidad) VALUES (?, ?)");
            $stmtLineaCompra->bind_param('ii', $movilId, $cantidad);
            
            if (!$stmtLineaCompra->execute()) {
                throw new Exception('Error al crear línea de compra: ' . $stmtLineaCompra->error);
            }
            
            $lineaCompraIds[] = $conexion->insert_id;
            $stmtLineaCompra->close();
        }
        
        // Crear compras
        foreach ($lineaCompraIds as $lineaCompraId) {
            $stmtCompra = $conexion->prepare("INSERT INTO compra (idLineaCompra) VALUES (?)");
            $stmtCompra->bind_param('i', $lineaCompraId);
            
            if (!$stmtCompra->execute()) {
                throw new Exception('Error al crear compra: ' . $stmtCompra->error);
            }
            
            $compraIds[] = $conexion->insert_id;
            $stmtCompra->close();
        }
        
        // Crear el pedido basado en la primera compra
        $estado_pedido = 'procesando';
        $forma_pago = 'paypal';
        $cantidadTotal = 0;
        $precioTotal = $datosCompra['total'];
        
        // Usar la primera compra
        $compraId = $compraIds[0];
        
        foreach ($carrito as $cantidad) {
            $cantidadTotal += $cantidad;
        }

        $stmtPedido = $conexion->prepare(
            "INSERT INTO pedido (precioTotal, cantidadTotal, formaPago, idCompra, idCliente, estado)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $cantidadTotalFloat = (float)$cantidadTotal;
        $stmtPedido->bind_param('ddsiis', $precioTotal, $cantidadTotalFloat, $forma_pago, $compraId, $clienteId, $estado_pedido);
        
        if (!$stmtPedido->execute()) {
            throw new Exception('Error al crear el pedido: ' . $stmtPedido->error);
        }
        
        $pedidoId = $stmtPedido->insert_id;
        $stmtPedido->close();

        // Actualizar stock de los móviles
        $total_articulos = 0;
        foreach ($carrito as $movilId => $cantidad) {
            // Actualizar stock del móvil
            $stmt = $conexion->prepare("UPDATE movil SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param('ii', $cantidad, $movilId);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar stock: ' . $stmt->error);
            }
            $stmt->close();

            $total_articulos += $cantidad;
        }

        // Confirmar transacción
        $conexion->commit();

        // Enviar email de confirmación
        enviarEmailConfirmacion($cliente['email'], $cliente['nombre'], $pedidoId, $precioTotal, $total_articulos);

        // Limpiar sesión del carrito
        unset($_SESSION['carrito']);
        unset($_SESSION['carrito_paypal']);
        unset($_SESSION['datos_compra_paypal']);

        $procesado = true;
        $mensaje = "¡Pago confirmado exitosamente! Tu pedido #{$pedidoId} ha sido creado.";
        $numeroPedido = $pedidoId;

        registrarLogPayPal("Pago confirmado para pedido $pedidoId - Cliente: $clienteId", 'SUCCESS');

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        $procesado = false;
        $tipo_mensaje = 'danger';
        $mensaje = 'Error al procesar el pago: ' . $e->getMessage();
        
        registrarLogPayPal("Error en confirmación de pago: " . $e->getMessage(), 'ERROR');
    }
} else {
    // No hay datos de compra en sesión
    $procesado = false;
    $tipo_mensaje = 'warning';
    $mensaje = 'No se encontraron datos de compra en la sesión. Por favor, vuelve a intentar.';
    registrarLogPayPal("Intento de acceso a confirmación sin datos en sesión", 'WARNING');
}

$conexion->close();

/**
 * Envía email de confirmación al cliente
 */
function enviarEmailConfirmacion($email, $nombre, $pedidoId, $total, $cantidad_articulos) {
    $asunto = "Confirmación de compra - Pedido #{$pedidoId} - Nevom";
    
    $mensaje = "
    <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>¡Gracias por tu compra!</h2>
            <p>Hola $nombre,</p>
            
            <p>Tu pago ha sido procesado exitosamente. Aquí están los detalles de tu pedido:</p>
            
            <div style='background-color: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <p><strong>Número de pedido:</strong> #$pedidoId</p>
                <p><strong>Cantidad de artículos:</strong> $cantidad_articulos</p>
                <p><strong>Total pagado:</strong> €" . number_format($total, 2, ',', '.') . "</p>
                <p><strong>Forma de pago:</strong> PayPal</p>
                <p><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <p>Tu pedido será procesado y enviado a la brevedad. Puedes rastrear el estado de tu pedido en nuestra página web.</p>
            
            <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
            
            <p>Saludos,<br>
            <strong>Equipo de Nevom</strong></p>
        </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@nevom.com" . "\r\n";
    
    // Descomentar para enviar email real
    // mail($email, $asunto, $mensaje, $headers);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pago - Nevom</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .confirmation-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .success-icon {
            font-size: 60px;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .error-icon {
            font-size: 60px;
            color: #dc3545;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .warning-icon {
            font-size: 60px;
            color: #ffc107;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .confirmation-message {
            background-color: white;
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .mensaje-alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .mensaje-alert.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .mensaje-alert.danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .mensaje-alert.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .detalles-pedido {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .detalle-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .detalle-item:last-child {
            border-bottom: none;
        }
        
        .botones-accion {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-primario {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primario:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .btn-secundario {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-secundario:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Nevom</a>
            <span class="navbar-text text-white">Confirmación de Pago</span>
        </div>
    </nav>

    <div class="confirmation-container">
        
        <?php if ($procesado && $tipo_mensaje === 'success'): ?>
            <!-- Éxito -->
            <div class="success-icon">✓</div>
            
            <div class="confirmation-message">
                <h1 class="text-center text-success mb-4">¡Pago Confirmado!</h1>
                
                <div class="mensaje-alert success">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>

                <div class="detalles-pedido">
                    <h5 class="mb-3">Detalles del pedido</h5>
                    
                    <div class="detalle-item">
                        <span><strong>Número de pedido:</strong></span>
                        <span><strong>#<?php echo htmlspecialchars($numeroPedido); ?></strong></span>
                    </div>
                    
                    <div class="detalle-item">
                        <span><strong>Total pagado:</strong></span>
                        <span><strong>€<?php echo number_format($datosCompra['total'], 2, ',', '.'); ?></strong></span>
                    </div>
                    
                    <div class="detalle-item">
                        <span><strong>Forma de pago:</strong></span>
                        <span><strong>PayPal</strong></span>
                    </div>
                    
                    <div class="detalle-item">
                        <span><strong>Fecha:</strong></span>
                        <span><strong><?php echo date('d/m/Y H:i:s'); ?></strong></span>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>ℹ️ Información:</strong> Te hemos enviado un email de confirmación. Tu pedido será procesado y enviado a la brevedad.
                </div>

                <div class="botones-accion">
                    <a href="../index.php" class="btn-primario">Volver a la tienda</a>
                    <a href="../admin/indexadmin.php" class="btn-secundario">Ver mis pedidos</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Error o advertencia -->
            <div class="<?php echo $tipo_mensaje === 'danger' ? 'error-icon' : 'warning-icon'; ?>">
                <?php echo $tipo_mensaje === 'danger' ? '✕' : '⚠'; ?>
            </div>
            
            <div class="confirmation-message">
                <h1 class="text-center mb-4">
                    <?php echo $tipo_mensaje === 'danger' ? 'Error en el Pago' : 'Advertencia'; ?>
                </h1>
                
                <div class="mensaje-alert <?php echo htmlspecialchars($tipo_mensaje); ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>

                <div class="alert alert-info">
                    <strong>¿Qué puedes hacer?</strong>
                    <ul class="mb-0 mt-2">
                        <li>Verifica tu conexión a internet</li>
                        <li>Intenta nuevamente desde tu carrito</li>
                        <li>Si el problema persiste, contacta con nuestro equipo de soporte</li>
                    </ul>
                </div>

                <div class="botones-accion">
                    <a href="../carrito/carrito.php" class="btn-primario">Volver al carrito</a>
                    <a href="../index.php" class="btn-secundario">Ir a inicio</a>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
