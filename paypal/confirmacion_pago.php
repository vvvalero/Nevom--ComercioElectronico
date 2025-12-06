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

        // Crear líneas de compra y compras
        $lineaCompraIds = [];
        $compraIds = [];
        
        foreach ($carrito as $movilId => $cantidad) {
            $stmt = $conexion->prepare("INSERT INTO linea_compra (idMovil, cantidad) VALUES (?, ?)");
            $stmt->bind_param('ii', $movilId, $cantidad);
            if (!$stmt->execute()) throw new Exception('Error al crear línea de compra');
            $lineaCompraIds[] = $conexion->insert_id;
            $stmt->close();
        }
        
        foreach ($lineaCompraIds as $lineaId) {
            $stmt = $conexion->prepare("INSERT INTO compra (idLineaCompra) VALUES (?)");
            $stmt->bind_param('i', $lineaId);
            if (!$stmt->execute()) throw new Exception('Error al crear compra');
            $compraIds[] = $conexion->insert_id;
            $stmt->close();
        }

        // Crear pedido
        $precioTotal = $datosCompra['total'];
        $cantidadTotal = array_sum($carrito);
        $compraId = $compraIds[0];

        $stmt = $conexion->prepare("INSERT INTO pedido (precioTotal, cantidadTotal, formaPago, idCompra, idCliente, estado) VALUES (?, ?, 'paypal', ?, ?, 'procesando')");
        $stmt->bind_param('ddii', $precioTotal, $cantidadTotal, $compraId, $clienteId);
        if (!$stmt->execute()) throw new Exception('Error al crear pedido');
        $pedidoId = $conexion->insert_id;
        $stmt->close();

        // Actualizar stock
        foreach ($carrito as $movilId => $cantidad) {
            $stmt = $conexion->prepare("UPDATE movil SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param('ii', $cantidad, $movilId);
            if (!$stmt->execute()) throw new Exception('Error al actualizar stock');
            $stmt->close();
        }

        $conexion->commit();

        // Limpiar sesión
        unset($_SESSION['carrito'], $_SESSION['carrito_paypal'], $_SESSION['datos_compra_paypal']);

        $procesado = true;
        $mensaje = "¡Pago confirmado! Tu pedido #{$pedidoId} ha sido creado.";
        $numeroPedido = $pedidoId;
        registrarLogPayPal("Pago confirmado - Pedido $pedidoId - Cliente $clienteId", 'SUCCESS');

    } catch (Exception $e) {
        $conexion->rollback();
        $tipo_mensaje = 'danger';
        $mensaje = 'Error: ' . $e->getMessage();
        registrarLogPayPal("Error: " . $e->getMessage(), 'ERROR');
    }
} else {
    $tipo_mensaje = 'warning';
    $mensaje = 'No se encontraron datos de compra. Vuelve a intentar.';
    registrarLogPayPal("Acceso a confirmación sin datos en sesión", 'WARNING');
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pago - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Confirmación de Pago', 'basePath' => '../']); ?>

    <div class="header-confirmacion <?= $tipo_mensaje === 'danger' ? 'error' : ($tipo_mensaje === 'warning' ? 'warning' : '') ?>">
        <div class="container">
            <h1><?= $procesado ? '¡Pago Confirmado!' : ($tipo_mensaje === 'danger' ? 'Error en el Pago' : 'Advertencia') ?></h1>
            <p><?= $procesado ? 'Tu pedido ha sido procesado correctamente' : ($tipo_mensaje === 'danger' ? 'Hubo un problema' : 'Revisa la información') ?></p>
        </div>
    </div>

    <div class="confirmacion-wrapper">
        <?php if ($procesado): ?>
            <div class="confirmacion-card">
                <div class="confirmacion-card-body">
                    <div class="mensaje-alert success">
                        <span style="font-size:1.5rem">✓</span>
                        <div><strong>¡Pago procesado!</strong><p style="margin:4px 0 0;font-size:0.9rem"><?= htmlspecialchars($mensaje) ?></p></div>
                    </div>

                    <div class="confirmacion-card" style="margin-bottom:0;background:#f9fafb">
                        <div class="confirmacion-card-header" style="background:#f3f4f6;color:#1f2937;border-bottom:1px solid #e5e7eb">📋 Detalles del Pedido</div>
                        <div class="confirmacion-card-body">
                            <div class="detalle-item"><span class="detalle-item-label">Pedido:</span><span class="detalle-item-valor">#<?= htmlspecialchars($numeroPedido) ?></span></div>
                            <div class="detalle-item"><span class="detalle-item-label">Total:</span><span class="detalle-item-valor">€<?= number_format($datosCompra['total'], 2, ',', '.') ?></span></div>
                            <div class="detalle-item"><span class="detalle-item-label">Pago:</span><span style="font-weight:600;color:#2563eb">PayPal</span></div>
                            <div class="detalle-item"><span class="detalle-item-label">Fecha:</span><span style="color:#6b7280"><?= date('d/m/Y H:i') ?></span></div>
                        </div>
                    </div>

                    <div class="info-box" style="margin-top:20px">
                        <strong>📧 Info:</strong> Tu pedido será procesado y enviado a la brevedad.
                    </div>

                    <div class="confirmacion-buttons">
                        <a href="../index.php" class="btn-confirmacion">🏠 Tienda</a>
                        <a href="../admin/indexadmin.php" class="btn-confirmacion-secondary">📦 Mis pedidos</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="confirmacion-card">
                <div class="confirmacion-card-body">
                    <div class="mensaje-alert <?= htmlspecialchars($tipo_mensaje) ?>">
                        <span style="font-size:1.5rem"><?= $tipo_mensaje === 'danger' ? '✕' : '⚠' ?></span>
                        <div><strong><?= $tipo_mensaje === 'danger' ? 'Error' : 'Advertencia' ?></strong><p style="margin:4px 0 0;font-size:0.9rem"><?= htmlspecialchars($mensaje) ?></p></div>
                    </div>

                    <div class="info-box">
                        <strong>¿Qué hacer?</strong>
                        <ul style="margin:8px 0 0;padding-left:20px"><li>Verifica tu conexión</li><li>Intenta desde el carrito</li><li>Contacta soporte si persiste</li></ul>
                    </div>

                    <div class="confirmacion-buttons">
                        <a href="../carrito/carrito.php" class="btn-confirmacion">🛒 Carrito</a>
                        <a href="../index.php" class="btn-confirmacion-secondary">🏠 Inicio</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer><div class="container text-center text-muted py-3"><p class="mb-0">&copy; <?= date('Y') ?> Nevom</p></div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
