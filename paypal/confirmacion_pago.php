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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="../index.php">
                📱 Nevom
            </a>
            <span class="navbar-text text-muted">Confirmación de Pago</span>
        </div>
    </nav>

    <!-- Header -->
    <div class="header-confirmacion <?php echo $tipo_mensaje === 'danger' ? 'error' : ($tipo_mensaje === 'warning' ? 'warning' : ''); ?>">
        <div class="container">
            <span class="icon-grande">
                <?php 
                    if ($procesado && $tipo_mensaje === 'success') {
                        echo '✓';
                    } elseif ($tipo_mensaje === 'danger') {
                        echo '✕';
                    } else {
                        echo '⚠';
                    }
                ?>
            </span>
            <h1>
                <?php 
                    if ($procesado && $tipo_mensaje === 'success') {
                        echo '¡Pago Confirmado!';
                    } elseif ($tipo_mensaje === 'danger') {
                        echo 'Error en el Pago';
                    } else {
                        echo 'Advertencia';
                    }
                ?>
            </h1>
            <p>
                <?php 
                    if ($procesado && $tipo_mensaje === 'success') {
                        echo 'Tu pedido ha sido procesado correctamente';
                    } elseif ($tipo_mensaje === 'danger') {
                        echo 'Hubo un problema al procesar tu pago';
                    } else {
                        echo 'Por favor revisa la siguiente información';
                    }
                ?>
            </p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="confirmacion-wrapper">
        
        <?php if ($procesado && $tipo_mensaje === 'success'): ?>
            <!-- Éxito -->
            <div class="confirmacion-card">
                <div class="confirmacion-card-body">
                    <!-- Mensaje de éxito -->
                    <div class="mensaje-alert success">
                        <span style="font-size: 1.5rem;">✓</span>
                        <div>
                            <strong>¡Pago procesado exitosamente!</strong>
                            <p style="margin: 4px 0 0 0; font-size: 0.9rem;"><?php echo htmlspecialchars($mensaje); ?></p>
                        </div>
                    </div>

                    <!-- Detalles del pedido -->
                    <div class="confirmacion-card" style="margin-bottom: 0; background: #f9fafb; border-shadow: none;">
                        <div class="confirmacion-card-header" style="background: #f3f4f6; color: #1f2937; border-bottom: 1px solid #e5e7eb;">
                            📋 Detalles del Pedido
                        </div>
                        <div class="confirmacion-card-body">
                            <div class="detalle-item">
                                <span class="detalle-item-label">Número de pedido:</span>
                                <span class="detalle-item-valor">#<?php echo htmlspecialchars($numeroPedido); ?></span>
                            </div>
                            
                            <div class="detalle-item">
                                <span class="detalle-item-label">Total pagado:</span>
                                <span class="detalle-item-valor">€<?php echo number_format($datosCompra['total'], 2, ',', '.'); ?></span>
                            </div>
                            
                            <div class="detalle-item">
                                <span class="detalle-item-label">Forma de pago:</span>
                                <span style="font-weight: 600; color: #2563eb;">PayPal</span>
                            </div>
                            
                            <div class="detalle-item">
                                <span class="detalle-item-label">Fecha:</span>
                                <span style="font-weight: 600; color: #6b7280;"><?php echo date('d/m/Y H:i:s'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Info importante -->
                    <div class="info-box" style="margin-top: 20px;">
                        <strong>📧 Información importante:</strong>
                        <p style="margin-top: 8px;">Te hemos enviado un email de confirmación a tu dirección. Tu pedido será procesado y enviado a la brevedad. Puedes rastrear el estado de tu pedido en tu perfil.</p>
                    </div>

                    <!-- Botones -->
                    <div class="confirmacion-buttons">
                        <a href="../index.php" class="btn-confirmacion">
                            🏠 Volver a la tienda
                        </a>
                        <a href="../admin/indexadmin.php" class="btn-confirmacion-secondary">
                            📦 Ver mis pedidos
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Error o advertencia -->
            <div class="confirmacion-card">
                <div class="confirmacion-card-body">
                    <!-- Mensaje de error/advertencia -->
                    <div class="mensaje-alert <?php echo htmlspecialchars($tipo_mensaje); ?>">
                        <span style="font-size: 1.5rem;">
                            <?php echo $tipo_mensaje === 'danger' ? '✕' : '⚠'; ?>
                        </span>
                        <div>
                            <strong>
                                <?php echo $tipo_mensaje === 'danger' ? 'Error al procesar el pago' : 'Advertencia'; ?>
                            </strong>
                            <p style="margin: 4px 0 0 0; font-size: 0.9rem;"><?php echo htmlspecialchars($mensaje); ?></p>
                        </div>
                    </div>

                    <!-- Sugerencias -->
                    <div class="info-box">
                        <strong>¿Qué puedes hacer?</strong>
                        <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #0c4a6e;">
                            <li>Verifica tu conexión a internet</li>
                            <li>Intenta nuevamente desde tu carrito</li>
                            <li>Si el problema persiste, contacta con nuestro equipo de soporte</li>
                        </ul>
                    </div>

                    <!-- Botones -->
                    <div class="confirmacion-buttons">
                        <a href="../carrito/carrito.php" class="btn-confirmacion">
                            🛒 Volver al carrito
                        </a>
                        <a href="../index.php" class="btn-confirmacion-secondary">
                            🏠 Ir a inicio
                        </a>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <hr class="border-light opacity-25 my-4">
            <div class="text-center text-muted">
                <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
