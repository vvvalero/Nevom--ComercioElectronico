<?php
// Incluir conexión externa
require '../config/conexion.php';

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

// Verificar que el usuario está logueado y es cliente
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if (!$userName || $userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vender_movil.php');
    exit;
}

// Obtener y validar datos del formulario
$marca = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$capacidad = intval($_POST['capacidad'] ?? 0);
$color = trim($_POST['color'] ?? '');
$estado = trim($_POST['estado'] ?? '');

// Validar campos requeridos
if (empty($marca) || empty($modelo) || $capacidad <= 0 || empty($color) || empty($estado)) {
    $_SESSION['mensaje_venta'] = 'Por favor, completa todos los campos obligatorios';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: vender_movil.php');
    exit;
}

// Función para calcular el precio estimado del móvil
function calcularPrecioMovil($marca, $modelo, $capacidad, $estado) {
    // Precios base por marca y modelo (aproximados en €, basados en mercado)
    $preciosBase = [
        'Apple' => [
            'iPhone 16 Pro Max' => 1050,
            'iPhone 16 Pro' => 850,
            'iPhone 16' => 750,
            'iPhone 15 Pro Max' => 800,
            'iPhone 15 Pro' => 720,
            'iPhone 15' => 750,
            'iPhone 14 Pro Max' => 800,
            'iPhone 14 Pro' => 700,
            'iPhone 14' => 550,
            'iPhone 13 Pro Max' => 650,
            'iPhone 13 Pro' => 550,
            'iPhone 13' => 450,
            'iPhone 12' => 350,
            'iPhone 11' => 220,
            'iPhone SE (3rd gen)' => 180,
            'default' => 300, // Para modelos no listados
        ],
        'Samsung' => [
            'Galaxy S23 Ultra' => 1000,
            'Galaxy S23+' => 800,
            'Galaxy S23' => 700,
            'Galaxy S22 Ultra' => 800,
            'Galaxy S22' => 600,
            'Galaxy S21 Ultra' => 700,
            'Galaxy S21' => 500,
            'Galaxy Note 20' => 400,
            'Galaxy A54' => 300,
            'Galaxy A34' => 250,
            'Galaxy A14' => 200,
            'default' => 350,
        ],
        'Xiaomi' => [
            '13 Pro' => 600,
            '13' => 500,
            '12 Pro' => 400,
            '12' => 350,
            '11 Pro' => 300,
            'Mi 11' => 250,
            'default' => 300,
        ],
        'Huawei' => [
            'P60 Pro' => 700,
            'Mate 50 Pro' => 600,
            'Mate 40 Pro' => 500,
            'P40 Pro' => 400,
            'default' => 400,
        ],
        'Google' => [
            'Pixel 8 Pro' => 900,
            'Pixel 8' => 700,
            'Pixel 7 Pro' => 600,
            'Pixel 7' => 500,
            'Pixel 6 Pro' => 400,
            'Pixel 6' => 350,
            'default' => 450,
        ],
        'OnePlus' => [
            '12' => 800,
            '11' => 600,
            '10 Pro' => 500,
            '9 Pro' => 400,
            '8 Pro' => 300,
            'default' => 400,
        ],
        'Sony' => [
            'Xperia 1 V' => 1000,
            'Xperia 5 V' => 800,
            'Xperia 10 V' => 400,
            'Xperia 1 IV' => 700,
            'Xperia 5 IV' => 600,
            'default' => 350,
        ],
        'Motorola' => [
            'Edge 40 Pro' => 600,
            'Edge 30 Pro' => 500,
            'Moto G Stylus' => 300,
            'Moto G Power' => 250,
            'Moto G Play' => 200,
            'default' => 250,
        ],
        'default' => 250, // Para marcas no listadas
    ];

    // Normalizar marca y modelo para comparación insensible a mayúsculas
    $marcaNorm = ucfirst(strtolower($marca));
    $modeloNorm = $modelo;

    // Obtener precio base
    $precioBase = $preciosBase[$marcaNorm][$modeloNorm] ?? $preciosBase[$marcaNorm]['default'] ?? $preciosBase['default'];

    // Factor de capacidad
    $factoresCapacidad = [
        16 => 0.8,
        32 => 0.9,
        64 => 1.0,
        128 => 1.1,
        256 => 1.2,
        512 => 1.3,
        1024 => 1.4, // 1TB
    ];
    $factorCapacidad = $factoresCapacidad[$capacidad] ?? 1.0;

    // Factor de estado
    $factoresEstado = [
        'Como nuevo' => 1.0,
        'Excelente' => 0.9,
        'Bueno' => 0.75,
        'Aceptable' => 0.6,
        'Regular' => 0.4,
    ];
    $factorEstado = $factoresEstado[$estado] ?? 0.5; // Default si no coincide

    // Calcular precio estimado
    $precioEstimado = $precioBase * $factorCapacidad * $factorEstado;

    // Limitar rango
    return max(50, min(1500, round($precioEstimado)));
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Calcular precio estimado usando el algoritmo
    $precioValoracion = calcularPrecioMovil($marca, $modelo, $capacidad, $estado);

    // 1. Insertar el móvil en la tabla movil (con stock 0 ya que es del cliente)
    $sqlMovil = "INSERT INTO movil (marca, modelo, capacidad, stock, color, precio) 
                 VALUES (?, ?, ?, 0, ?, ?)";
    $stmtMovil = $conexion->prepare($sqlMovil);
    
    if (!$stmtMovil) {
        throw new Exception("Error al preparar la consulta de móvil: " . $conexion->error);
    }
    
    $stmtMovil->bind_param('ssisi', $marca, $modelo, $capacidad, $color, $precioValoracion);
    
    if (!$stmtMovil->execute()) {
        throw new Exception("Error al insertar el móvil: " . $stmtMovil->error);
    }
    
    $movilId = $stmtMovil->insert_id;
    $stmtMovil->close();

    // 2. Insertar línea de venta (el cliente vende el móvil a la tienda)
    $cantidad = 1; // Solo un móvil
    $sqlLineaVenta = "INSERT INTO linea_venta (idMovil, cantidad) VALUES (?, ?)";
    $stmtLineaVenta = $conexion->prepare($sqlLineaVenta);
    
    if (!$stmtLineaVenta) {
        throw new Exception("Error al preparar la consulta de línea de venta: " . $conexion->error);
    }
    
    $stmtLineaVenta->bind_param('ii', $movilId, $cantidad);
    
    if (!$stmtLineaVenta->execute()) {
        throw new Exception("Error al insertar línea de venta: " . $stmtLineaVenta->error);
    }
    
    $lineaVentaId = $stmtLineaVenta->insert_id;
    $stmtLineaVenta->close();

    // 3. Insertar venta
    $sqlVenta = "INSERT INTO venta (idLineaVenta) VALUES (?)";
    $stmtVenta = $conexion->prepare($sqlVenta);
    
    if (!$stmtVenta) {
        throw new Exception("Error al preparar la consulta de venta: " . $conexion->error);
    }
    
    $stmtVenta->bind_param('i', $lineaVentaId);
    
    if (!$stmtVenta->execute()) {
        throw new Exception("Error al insertar venta: " . $stmtVenta->error);
    }
    
    $ventaId = $stmtVenta->insert_id;
    $stmtVenta->close();

    // 4. Crear pedido con la venta (el cliente vende su móvil a la tienda)
    $precioTotal = $precioValoracion;
    $cantidadTotal = $cantidad;
    $formaPago = 'transferencia'; // Forma de pago por defecto para ventas del cliente
    // Estado inicial para pedidos de venta
    $estadoPedido = 'procesando'; // Puede ser 'procesando', 'aprobado', 'rechazado', 'pagado' según el flujo
    $numSeguimiento = 'NV-' . date('Ymd-His') . '-' . rand(100, 999);
    
    $sqlPedido = "INSERT INTO pedido (numSeguimiento, precioTotal, cantidadTotal, formaPago, idVenta, idCliente, estado, fecha_creacion) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmtPedido = $conexion->prepare($sqlPedido);
    
    if (!$stmtPedido) {
        throw new Exception("Error al preparar la consulta de pedido: " . $conexion->error);
    }
    
    $stmtPedido->bind_param('sddsiis', $numSeguimiento, $precioTotal, $cantidadTotal, $formaPago, $ventaId, $clienteId, $estadoPedido);
    
    if (!$stmtPedido->execute()) {
        throw new Exception("Error al insertar pedido: " . $stmtPedido->error);
    }
    
    $pedidoId = $stmtPedido->insert_id;
    $stmtPedido->close();

    // Confirmar transacción
    $conexion->commit();

    // Mensaje de éxito
    $_SESSION['mensaje_venta'] = "¡Valoración completada! Tu móvil $marca $modelo ha sido valorado en " . $precioTotal . "€. 
                                   Hemos registrado tu solicitud de venta (Número de Seguimiento: $numSeguimiento). 
                                   Nos pondremos en contacto contigo para coordinar la recogida.";
    $_SESSION['tipo_mensaje'] = 'success';
    
    // Redirigir a confirmación
    header('Location: confirmacion_venta.php?numSeguimiento=' . urlencode($numSeguimiento));
    exit;

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Log del error (en producción, esto debería ir a un archivo de log)
    error_log("Error en venta de móvil: " . $e->getMessage());
    
    // Mensaje de error para el usuario
    $_SESSION['mensaje_venta'] = 'Hubo un error al procesar tu solicitud. Por favor, inténtalo de nuevo más tarde.';
    $_SESSION['tipo_mensaje'] = 'danger';
    
    header('Location: vender_movil.php');
    exit;
}

// Cerrar conexión
$conexion->close();
?>
