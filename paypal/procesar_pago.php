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
$clienteId = $_SESSION['cliente_id'] ?? null;

if ($userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener datos del carrito
if (empty($_SESSION['carrito'])) {
    $_SESSION['mensaje'] = 'El carrito está vacío';
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: ../carrito/carrito.php');
    exit;
}

// Obtener datos del cliente
$stmtCliente = $conexion->prepare("SELECT nombre, apellidos, email, telefono, direccion FROM cliente WHERE id = ?");
$stmtCliente->bind_param('i', $clienteId);
$stmtCliente->execute();
$cliente = $stmtCliente->get_result()->fetch_assoc();
$stmtCliente->close();

// Calcular total del carrito
$precioTotal = 0;
$cantidadTotal = 0;
$productosDetalle = array();

foreach ($_SESSION['carrito'] as $movilId => $cantidad) {
    $stmt = $conexion->prepare("SELECT id, marca, modelo, precio FROM movil WHERE id = ?");
    $stmt->bind_param('i', $movilId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($movil = $result->fetch_assoc()) {
        $subtotal = $movil['precio'] * $cantidad;
        $precioTotal += $subtotal;
        $cantidadTotal += $cantidad;
        $productosDetalle[] = "{$movil['marca']} {$movil['modelo']} x{$cantidad}";
    }
    $stmt->close();
}

$descripcionProductos = implode(', ', $productosDetalle);
$precioTotal = round($precioTotal, 2);

// Preparar datos para PayPal
$datosCompra = array(
    'numero_pedido' => 'TEMPORAL-' . $clienteId . '-' . time(),
    'descripcion' => 'Compra en Nevom: ' . $descripcionProductos,
    'total' => $precioTotal,
    'cliente_nombre' => $cliente['nombre'],
    'cliente_apellido' => $cliente['apellidos'],
    'cliente_email' => $cliente['email'],
    'cliente_telefono' => $cliente['telefono'],
    'cliente_direccion' => $cliente['direccion'],
    'cliente_pais' => 'ES', // Puedes hacerlo dinámico si lo tienes en la BD
    'cantidad' => $cantidadTotal
);

// Validar datos
$errores = ProcesadorPayPal::validarDatos($datosCompra);

// Procesar si se envía el formulario
$procesarPago = false;
$urlPayPal = '';
$parametrosPayPal = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errores)) {
    // Generar parámetros para PayPal
    $parametrosPayPal = ProcesadorPayPal::generarParametrosPago($datosCompra);
    
    // Registrar en sesión para recuperar después
    $_SESSION['datos_compra_paypal'] = $datosCompra;
    $_SESSION['carrito_paypal'] = $_SESSION['carrito'];
    
    $procesarPago = true;
    $urlPayPal = ProcesadorPayPal::construirUrlFormulario($parametrosPayPal);
    
    registrarLogPayPal("Iniciando pago PayPal para cliente $clienteId - Total: " . $precioTotal . " " . PAYPAL_CURRENCY, 'INFO');
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body class="paypal-page">
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="../index.php">
                📱 Nevom
            </a>
            <span class="navbar-text text-muted">Procesamiento de Pago</span>
        </div>
    </nav>

    <!-- Header -->
    <div class="header-pago">
        <div class="container">
            <h1>🔒 Confirmar Pago</h1>
            <p>Revisa los detalles de tu compra antes de proceder con PayPal</p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="pago-wrapper">
        <?php if (!empty($errores)): ?>
            <div class="pago-card">
                <div class="pago-card-body">
                    <div class="error-message">
                        <h5>⚠️ Errores encontrados:</h5>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <a href="../carrito/carrito.php" class="btn btn-primary">← Volver al Carrito</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Resumen de compra -->
            <div class="pago-card">
                <div class="pago-card-header">
                    📦 Resumen de tu compra
                </div>
                <div class="pago-card-body">
                    <?php foreach ($productosDetalle as $producto): ?>
                        <div class="resumen-item">
                            <span><?php echo htmlspecialchars($producto); ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="resumen-total">
                        <span>Total a pagar:</span>
                        <span class="price">€<?php echo number_format($precioTotal, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Datos de envío -->
            <div class="pago-card">
                <div class="pago-card-header">
                    📍 Datos de envío
                </div>
                <div class="pago-card-body">
                    <div class="cliente-info">
                        <div class="cliente-info-item">
                            <span class="cliente-info-label">Nombre completo</span>
                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></span>
                        </div>
                        
                        <div class="cliente-info-item">
                            <span class="cliente-info-label">Email</span>
                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['email']); ?></span>
                        </div>
                        
                        <div class="cliente-info-item">
                            <span class="cliente-info-label">Teléfono</span>
                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['telefono']); ?></span>
                        </div>
                        
                        <div class="cliente-info-item">
                            <span class="cliente-info-label">Dirección</span>
                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['direccion']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="pago-card">
                <div class="pago-card-body" style="display: flex; justify-content: center; padding-top: 20px;">
                    <form method="post" action="" style="width: 100%;">
                        <div class="pago-buttons">
                            <button type="submit" class="btn-pagar-paypal" id="btn-pagar">
                                🔒 Pagar con PayPal
                            </button>
                            <a href="../carrito/carrito.php" class="btn-cancelar">
                                ← Cancelar Compra
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Spinner de carga -->
                <div class="loading-spinner" id="loading-spinner">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p>🔄 Redirigiendo a PayPal...</p>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- Formulario oculto para PayPal (se envía automáticamente) -->
    <?php if ($procesarPago && !empty($parametrosPayPal)): ?>
        <?php echo ProcesadorPayPal::generarFormularioOculto($parametrosPayPal); ?>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('loading-spinner').style.display = 'block';
                document.getElementById('btn-pagar').disabled = true;
                setTimeout(function() {
                    document.getElementById('paypal-form').submit();
                }, 500);
            });
        </script>
    <?php endif; ?>

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