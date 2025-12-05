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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pago-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .resumen-compra {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .resumen-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .resumen-item:last-child {
            border-bottom: none;
        }
        
        .resumen-total {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #28a745;
        }
        
        .datos-cliente {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .datos-cliente p {
            margin: 8px 0;
        }
        
        .label-datos {
            font-weight: bold;
            color: #495057;
        }
        
        .botones-pago {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-paypal {
            background-color: #0070ba;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-paypal:hover {
            background-color: #005ea6;
            color: white;
            text-decoration: none;
        }
        
        .btn-cancelar {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-cancelar:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .info-paypal {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .paypal-logo {
            display: inline-block;
            margin-right: 10px;
        }
        
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner-border {
            color: #0070ba;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">Nevom</a>
            <span class="navbar-text text-white">Procesamiento de Pago</span>
        </div>
    </nav>

    <div class="pago-container">
        <h1 class="mb-4">Confirmar Pago</h1>

        <?php if (!empty($errores)): ?>
            <div class="error-message">
                <h5>Errores encontrados:</h5>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>

            <!-- Información de PayPal -->
            <div class="info-paypal">
                <strong>ℹ️ Información:</strong> Serás redirigido a PayPal Sandbox para completar el pago de forma segura.
            </div>

            <!-- Resumen de la compra -->
            <div class="resumen-compra">
                <h5 class="mb-3">Resumen de tu compra</h5>
                
                <?php foreach ($productosDetalle as $producto): ?>
                    <div class="resumen-item">
                        <span><?php echo htmlspecialchars($producto); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="resumen-total">
                    Total: €<?php echo number_format($precioTotal, 2, ',', '.'); ?>
                </div>
            </div>

            <!-- Datos de envío -->
            <div class="datos-cliente">
                <h5 class="mb-3">Datos de envío</h5>
                
                <p>
                    <span class="label-datos">Nombre:</span> 
                    <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?>
                </p>
                
                <p>
                    <span class="label-datos">Email:</span> 
                    <?php echo htmlspecialchars($cliente['email']); ?>
                </p>
                
                <p>
                    <span class="label-datos">Teléfono:</span> 
                    <?php echo htmlspecialchars($cliente['telefono']); ?>
                </p>
                
                <p>
                    <span class="label-datos">Dirección:</span> 
                    <?php echo htmlspecialchars($cliente['direccion']); ?>
                </p>
            </div>

            <!-- Botones de acción -->
            <form method="post" action="">
                <div class="botones-pago">
                    <button type="submit" class="btn-paypal" id="btn-pagar">
                        🔒 Pagar con PayPal
                    </button>
                    <a href="../carrito/carrito.php" class="btn-cancelar">Cancelar</a>
                </div>
            </form>

            <!-- Spinner de carga -->
            <div class="loading-spinner" id="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Redirigiendo a PayPal...</p>
            </div>

        <?php endif; ?>
    </div>

    <!-- Formulario oculto para PayPal (se envía automáticamente si $procesarPago es true) -->
    <?php if ($procesarPago && !empty($parametrosPayPal)): ?>
        <?php echo ProcesadorPayPal::generarFormularioOculto($parametrosPayPal); ?>
        
        <script>
            // Mostrar spinner y enviar formulario automáticamente
            document.getElementById('loading-spinner').style.display = 'block';
            document.getElementById('btn-pagar').disabled = true;
            document.getElementById('paypal-form').submit();
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
