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
$comentarios = trim($_POST['comentarios'] ?? '');

// Validar campos requeridos
if (empty($marca) || empty($modelo) || $capacidad <= 0 || empty($color) || empty($estado)) {
    $_SESSION['mensaje_venta'] = 'Por favor, completa todos los campos obligatorios';
    $_SESSION['tipo_mensaje'] = 'danger';
    header('Location: vender_movil.php');
    exit;
}

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // Generar precio aleatorio entre 150 y 400 euros (proyecto educativo)
    $precioValoracion = rand(150, 400);

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
    
    $sqlPedido = "INSERT INTO pedido (numSeguimiento, precioTotal, cantidadTotal, formaPago, idVenta, idCliente, estado) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
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
    $_SESSION['mensaje_venta'] = "¡Valoración completada! Tu móvil $marca $modelo ha sido valorado en $precioTotal€. 
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
