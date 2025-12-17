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

// Verificar que los datos del cliente estén completos
if (!$cliente || empty($cliente['nombre']) || empty($cliente['apellidos']) || empty($cliente['email']) || empty($cliente['telefono']) || empty($cliente['direccion'])) {
    $_SESSION['mensaje'] = 'Tu perfil de cliente está incompleto. Por favor, actualiza tus datos en el perfil.';
    $_SESSION['mensaje_tipo'] = 'warning';
    header('Location: ../cliente/perfil.php');
    exit;
}

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
    if (isset($_SESSION['pago_procesado'])) {
        $_SESSION['mensaje'] = 'El pago ya está siendo procesado. Por favor, espera.';
        $_SESSION['mensaje_tipo'] = 'warning';
        header('Location: carrito.php');
        exit;
    }
    $parametrosPayPal = ProcesadorPayPal::generarParametrosPago($datosCompra);
    $_SESSION['datos_compra_paypal'] = $datosCompra;
    $_SESSION['carrito_paypal'] = $_SESSION['carrito'];
    $_SESSION['pago_procesado'] = true;
    $procesarPago = true;
    // Logging eliminado
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php';
    renderNavbar(['type' => 'main', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="page-header wave-light <?= empty($errores) ? '' : 'danger' ?>">
        <div class="container">
            <h1><?= empty($errores) ? '<i class="fas fa-lock"></i> Confirmar Pago' : '<i class="fas fa-exclamation-triangle"></i> Errores Encontrados' ?></h1>
            <p><?= empty($errores) ? 'Revisa los detalles de tu compra antes de proceder con PayPal' : 'Por favor corrige los siguientes errores' ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (!empty($errores)): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-danger mb-4" role="alert">
                        <h5 class="alert-heading">⚠️ Errores encontrados:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="text-center">
                        <a href="../carrito/carrito.php" class="btn btn-primary btn-lg">← Volver al Carrito</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Resumen de compra -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-box"></i> Resumen de tu compra</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($productosDetalle as $p): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span><?= htmlspecialchars($p) ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="d-flex justify-content-between align-items-center pt-3 mt-2">
                                <strong class="fs-5">Total a pagar:</strong>
                                <strong class="fs-4 text-success">€<?= number_format($precioTotal, 2, ',', '.') ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Datos de envío -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Datos de envío</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Nombre</small>
                                        <strong><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Email</small>
                                        <strong><?= htmlspecialchars($cliente['email']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Teléfono</small>
                                        <strong><?= htmlspecialchars($cliente['telefono']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded">
                                        <small class="text-muted d-block">Dirección</small>
                                        <strong><?= htmlspecialchars($cliente['direccion']) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de pago -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body text-center py-4">
                            <form method="post" id="form-pagar">
                                <div class="d-flex gap-3 justify-content-center flex-wrap">
                                    <button type="submit" class="btn btn-primary btn-lg" id="btn-pagar">
                                        <i class="fas fa-lock"></i> Pagar con PayPal
                                    </button>
                                    <a href="../carrito/carrito.php" class="btn btn-outline-secondary btn-lg">
                                        ← Cancelar
                                    </a>
                                </div>
                            </form>
                            <div class="mt-4" id="loading-spinner" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2 text-muted"><i class="fas fa-redo"></i> Redirigiendo a PayPal...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Info de seguridad -->
                    <div class="alert alert-info d-flex align-items-start">
                        <i class="fas fa-lock me-3" style="font-size: 1.25rem;"></i>
                        <div>
                            <strong>Pago seguro</strong>
                            <p class="mb-0 small">Serás redirigido a PayPal para completar tu pago de forma segura. No almacenamos datos de tu cuenta PayPal.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

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

    <footer class="site-footer mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>