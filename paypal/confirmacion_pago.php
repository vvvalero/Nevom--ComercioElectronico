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
        $mensaje = "¡Pago confirmado! Tu pedido ha sido creado.";
        $numeroPedido = $numSeguimiento;
        registrarLogPayPal("Pago confirmado - Pedido $numeroPedido - Cliente $clienteId", 'SUCCESS');

        // Redirigir a página unificada de confirmación
        header('Location: ../carrito/confirmacion_pedido.php?numero_pedido=' . urlencode($numSeguimiento));
        exit;
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="page-header wave-light <?= $procesado ? 'success' : ($tipo_mensaje === 'danger' ? 'danger' : 'warning') ?>">
        <div class="container">
            <h1><?= $procesado ? '✅ ¡Pago Confirmado!' : ($tipo_mensaje === 'danger' ? '❌ Error en el Pago' : '⚠️ Advertencia') ?></h1>
            <p><?= $procesado ? 'Tu pedido ha sido procesado correctamente' : ($tipo_mensaje === 'danger' ? 'Hubo un problema al procesar tu pago' : 'Revisa la información') ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if ($procesado): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Mensaje de éxito -->
                    <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                        <span class="me-3" style="font-size: 1.5rem;">✓</span>
                        <div>
                            <strong>¡Pago procesado correctamente!</strong>
                            <p class="mb-0 small opacity-75"><?= htmlspecialchars($mensaje) ?></p>
                        </div>
                    </div>

                    <!-- Detalles del pedido -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard"></i> Detalles del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Número de Pedido</small>
                                        <strong class="fs-5"><?= htmlspecialchars($numeroPedido) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Total Pagado</small>
                                        <strong class="fs-5 text-success">€<?= number_format($datosCompra['total'], 2, ',', '.') ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Método de Pago</small>
                                        <strong style="color: #0070ba;"><i class="fas fa-credit-card"></i> PayPal</strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Fecha</small>
                                        <strong><?= date('d/m/Y H:i') ?></strong>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Estado</small>
                                            <span class="badge bg-info fs-6">Procesando</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info adicional -->
                    <div class="alert alert-info d-flex align-items-start mb-4">
                        <i class="fas fa-envelope me-3" style="font-size: 1.25rem;"></i>
                        <div>
                            <strong>Próximos pasos</strong>
                            <p class="mb-0 small">Tu pedido será preparado y enviado a la brevedad. Recibirás actualizaciones sobre el estado de tu envío.</p>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="../carrito/visualizar_factura.php?numero_pedido=<?= urlencode($numeroPedido) ?>" class="btn btn-info btn-lg">
                            <i class="fas fa-eye"></i> Visualizar Factura
                        </a>
                        <a href="../carrito/descargar_factura.php?numero_pedido=<?= urlencode($numeroPedido) ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-file"></i> Descargar Factura (Facturae)
                        </a>
                        <a href="../index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i> Volver a la Tienda
                        </a>
                        <a href="../index.php#mis-compras" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-box"></i> Ver Mis Pedidos
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Mensaje de error/warning -->
                    <div class="alert alert-<?= $tipo_mensaje ?> d-flex align-items-center mb-4" role="alert">
                        <span class="me-3" style="font-size: 1.5rem;"><?= $tipo_mensaje === 'danger' ? '✕' : '⚠' ?></span>
                        <div>
                            <strong><?= $tipo_mensaje === 'danger' ? 'No se pudo completar el pago' : 'Información incompleta' ?></strong>
                            <p class="mb-0 small"><?= htmlspecialchars($mensaje) ?></p>
                        </div>
                    </div>

                    <!-- Sugerencias -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-lightbulb"></i> ¿Qué puedes hacer?</h5>
                            <ul class="mb-0">
                                <li>Verifica tu conexión a internet</li>
                                <li>Intenta realizar la compra nuevamente desde el carrito</li>
                                <li>Contacta a soporte si el problema persiste</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="../carrito/carrito.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-shopping-cart"></i> Volver al Carrito
                        </a>
                        <a href="../index.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-home"></i> Ir al Inicio
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>