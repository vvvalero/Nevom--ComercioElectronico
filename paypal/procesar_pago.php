<?php
require '../config/conexion.php';
require '../config/procesador_paypal.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Verificar autenticación
$clienteId = $_SESSION['cliente_id'] ?? null;
if ($_SESSION['user_role'] !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar carrito
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
$productosDetalle = [];

foreach ($_SESSION['carrito'] as $movilId => $cantidad) {
    $stmt = $conexion->prepare("SELECT marca, modelo, precio FROM movil WHERE id = ?");
    $stmt->bind_param('i', $movilId);
    $stmt->execute();
    if ($movil = $stmt->get_result()->fetch_assoc()) {
        $precioTotal += $movil['precio'] * $cantidad;
        $cantidadTotal += $cantidad;
        $productosDetalle[] = "{$movil['marca']} {$movil['modelo']} x{$cantidad}";
    }
    $stmt->close();
}

$precioTotal = round($precioTotal, 2);

// Preparar datos para PayPal
$datosCompra = [
    'numero_pedido' => 'TEMPORAL-' . $clienteId . '-' . time(),
    'descripcion' => 'Compra en Nevom: ' . implode(', ', $productosDetalle),
    'total' => $precioTotal,
    'cliente_nombre' => $cliente['nombre'],
    'cliente_apellido' => $cliente['apellidos'],
    'cliente_email' => $cliente['email'],
    'cliente_telefono' => $cliente['telefono'],
    'cliente_direccion' => $cliente['direccion'],
    'cliente_pais' => 'ES',
    'cantidad' => $cantidadTotal
];

$errores = ProcesadorPayPal::validarDatos($datosCompra);
$procesarPago = false;
$parametrosPayPal = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errores)) {
    $parametrosPayPal = ProcesadorPayPal::generarParametrosPago($datosCompra);
    $_SESSION['datos_compra_paypal'] = $datosCompra;
    $_SESSION['carrito_paypal'] = $_SESSION['carrito'];
    $procesarPago = true;
    registrarLogPayPal("Iniciando pago PayPal - Cliente: $clienteId - Total: $precioTotal EUR", 'INFO');
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
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="paypal-page">
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'simple', 'simpleText' => 'Procesamiento de Pago', 'basePath' => '../']); ?>

    <div class="header-pago">
        <div class="container">
            <h1>🔒 Confirmar Pago</h1>
            <p>Revisa los detalles de tu compra antes de proceder con PayPal</p>
        </div>
    </div>

    <div class="pago-wrapper">
        <?php if (!empty($errores)): ?>
            <div class="pago-card">
                <div class="pago-card-body">
                    <div class="error-message">
                        <h5>⚠️ Errores encontrados:</h5>
                        <ul><?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                    <a href="../carrito/carrito.php" class="btn btn-primary">← Volver al Carrito</a>
                </div>
            </div>
        <?php else: ?>
            <div class="pago-card">
                <div class="pago-card-header">📦 Resumen de tu compra</div>
                <div class="pago-card-body">
                    <?php foreach ($productosDetalle as $p): ?>
                        <div class="resumen-item"><span><?= htmlspecialchars($p) ?></span></div>
                    <?php endforeach; ?>
                    <div class="resumen-total">
                        <span>Total a pagar:</span>
                        <span class="price">€<?= number_format($precioTotal, 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>

            <div class="pago-card">
                <div class="pago-card-header">📍 Datos de envío</div>
                <div class="pago-card-body">
                    <div class="cliente-info">
                        <div class="cliente-info-item"><span class="cliente-info-label">Nombre</span><span class="cliente-info-value"><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']) ?></span></div>
                        <div class="cliente-info-item"><span class="cliente-info-label">Email</span><span class="cliente-info-value"><?= htmlspecialchars($cliente['email']) ?></span></div>
                        <div class="cliente-info-item"><span class="cliente-info-label">Teléfono</span><span class="cliente-info-value"><?= htmlspecialchars($cliente['telefono']) ?></span></div>
                        <div class="cliente-info-item"><span class="cliente-info-label">Dirección</span><span class="cliente-info-value"><?= htmlspecialchars($cliente['direccion']) ?></span></div>
                    </div>
                </div>
            </div>

            <div class="pago-card">
                <div class="pago-card-body text-center">
                    <form method="post">
                        <div class="pago-buttons">
                            <button type="submit" class="btn-pagar-paypal" id="btn-pagar">🔒 Pagar con PayPal</button>
                            <a href="../carrito/carrito.php" class="btn-cancelar">← Cancelar</a>
                        </div>
                    </form>
                </div>
                <div class="loading-spinner" id="loading-spinner">
                    <div class="spinner-border" role="status"></div>
                    <p>🔄 Redirigiendo a PayPal...</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($procesarPago && !empty($parametrosPayPal)): ?>
        <?= ProcesadorPayPal::generarFormularioOculto($parametrosPayPal) ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('loading-spinner').style.display = 'block';
                document.getElementById('btn-pagar').disabled = true;
                setTimeout(() => document.getElementById('paypal-form').submit(), 500);
            });
        </script>
    <?php endif; ?>

    <footer>
        <div class="container text-center text-muted py-3">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>