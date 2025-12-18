<?php
require '../config/conexion.php';

// Iniciar sesión de forma segura
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

// Verificar que el usuario esté logueado como cliente
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

if (!$userName || $userRole !== 'client' || !$clienteId) {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener pedidos del cliente
$pedidos = [];
$stmt = $conexion->prepare("
    SELECT p.id, p.numSeguimiento, p.precioTotal, p.cantidadTotal, p.formaPago, p.estado, p.idCompra, p.idVenta,
           COALESCE(p.fecha_creacion, 'N/A') as fecha_pedido, p.fecha_entrega
    FROM pedido p
    WHERE p.idCliente = ?
    ORDER BY p.id DESC
");
$stmt->bind_param('i', $clienteId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}
$stmt->close();

// Obtener productos para cada pedido
foreach ($pedidos as &$pedido) {
    $productos = [];
    $idCompra = $pedido['idCompra'];
    $idVenta = $pedido['idVenta'];
    
    if ($idCompra) {
        $stmt2 = $conexion->prepare("
            SELECT m.marca, m.modelo, m.capacidad, m.color, lc.cantidad, m.precio
            FROM linea_compra lc
            JOIN movil m ON lc.idMovil = m.id
            JOIN compra c ON lc.idCompra = c.id
            WHERE c.id = ?
        ");
        $stmt2->bind_param('i', $idCompra);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        while ($prod = $result2->fetch_assoc()) {
            $productos[] = $prod;
        }
        $stmt2->close();
    } elseif ($idVenta) {
        $stmt2 = $conexion->prepare("
            SELECT m.marca, m.modelo, m.capacidad, m.color, lv.cantidad, m.precio
            FROM linea_venta lv
            JOIN movil m ON lv.idMovil = m.id
            JOIN venta v ON lv.id = v.idLineaVenta
            WHERE v.id = ?
        ");
        $stmt2->bind_param('i', $idVenta);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        while ($prod = $result2->fetch_assoc()) {
            $productos[] = $prod;
        }
        $stmt2->close();
    }
    $pedido['productos'] = $productos;
}
unset($pedido);

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Pedidos - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'main', 'activeLink' => 'mis_pedidos', 'basePath' => '../']); ?>

    <div class="container mt-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-lg rounded-4">
                    <div class="card-header bg-primary text-white text-center">
                        <h3 class="mb-0">Mis Pedidos</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($pedidos)): ?>
                            <div class="text-center py-5">
                                <h5 class="text-muted">No tienes pedidos realizados aún.</h5>
                                <a href="../index.php" class="btn btn-primary mt-3">Ir a la tienda</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Número de Seguimiento</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Cantidad</th>
                                            <th>Forma de Pago</th>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($pedido['numSeguimiento']); ?></td>
                                                <td><?php echo htmlspecialchars($pedido['fecha_pedido'] !== 'N/A' ? date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) : 'N/A'); ?></td>
                                                <td>€<?php echo number_format($pedido['precioTotal'], 2, ',', '.'); ?></td>
                                                <td><?php echo htmlspecialchars($pedido['cantidadTotal']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($pedido['formaPago'])); ?></td>
                                                <td>
                                                    <?php
                                                    if ($pedido['idCompra']) {
                                                        echo '<span class="badge bg-primary">Compra</span>';
                                                    } elseif ($pedido['idVenta']) {
                                                        echo '<span class="badge bg-success">Venta</span>';
                                                    } else {
                                                        echo '<span class="badge bg-secondary">Desconocido</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        switch ($pedido['estado']) {
                                                            case 'procesando': echo 'bg-warning'; break;
                                                            case 'preparando': echo 'bg-info'; break;
                                                            case 'enviado': echo 'bg-primary'; break;
                                                            case 'entregado': echo 'bg-success'; break;
                                                            case 'pagado': echo 'bg-success'; break;
                                                            case 'aprobado': echo 'bg-success'; break;
                                                            case 'rechazado': echo 'bg-danger'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo htmlspecialchars(ucfirst($pedido['estado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?php echo $pedido['id']; ?>)">
                                                        Ver Detalle
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalles ocultos de pedidos -->
    <?php foreach ($pedidos as $pedido): ?>
    <div id="detalle-<?php echo $pedido['id']; ?>" style="display:none;">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-receipt me-2"></i>Pedido #<?php echo htmlspecialchars($pedido['numSeguimiento']); ?>
                        <?php
                        if ($pedido['idCompra']) {
                            echo '<span class="badge bg-primary ms-2">Compra</span>';
                        } elseif ($pedido['idVenta']) {
                            echo '<span class="badge bg-success ms-2">Venta</span>';
                        }
                        ?>
                    </h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-info-circle me-2"></i>Información del Pedido
                        </div>
                        <div class="card-body">
                            <p><i class="fas fa-tag me-2"></i><strong>Tipo:</strong> 
                                <?php
                                if ($pedido['idCompra']) {
                                    echo 'Compra';
                                } elseif ($pedido['idVenta']) {
                                    echo 'Venta';
                                } else {
                                    echo 'Desconocido';
                                }
                                ?>
                            </p>
                            <p><i class="fas fa-calendar-alt me-2"></i><strong>Fecha:</strong> <?php echo htmlspecialchars($pedido['fecha_pedido'] !== 'N/A' ? date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) : 'N/A'); ?></p>
                            <p><i class="fas fa-credit-card me-2"></i><strong>Forma de Pago:</strong> <?php echo htmlspecialchars(ucfirst($pedido['formaPago'])); ?></p>
                            <p><i class="fas fa-euro-sign me-2"></i><strong>Total:</strong> €<?php echo number_format($pedido['precioTotal'], 2, ',', '.'); ?></p>
                            <p><i class="fas fa-boxes me-2"></i><strong>Cantidad Total:</strong> <?php echo htmlspecialchars($pedido['cantidadTotal']); ?></p>
                            <?php if ($pedido['fecha_entrega']): ?>
                            <p><i class="fas fa-truck me-2"></i><strong>Fecha de Entrega:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['fecha_entrega']))); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-<?php 
                        switch ($pedido['estado']) {
                            case 'procesando': echo 'warning'; break;
                            case 'preparando': echo 'info'; break;
                            case 'enviado': echo 'primary'; break;
                            case 'entregado': echo 'success'; break;
                            case 'pagado': echo 'success'; break;
                            case 'aprobado': echo 'success'; break;
                            case 'rechazado': echo 'danger'; break;
                            default: echo 'secondary';
                        }
                    ?> mb-3">
                        <div class="card-header bg-<?php 
                            switch ($pedido['estado']) {
                                case 'procesando': echo 'warning'; break;
                                case 'preparando': echo 'info'; break;
                                case 'enviado': echo 'primary'; break;
                                case 'entregado': echo 'success'; break;
                                case 'pagado': echo 'success'; break;
                                case 'aprobado': echo 'success'; break;
                                case 'rechazado': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                        ?> text-white">
                            <i class="fas fa-exclamation-triangle me-2"></i>Estado Actual del Pedido
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <span class="badge bg-<?php 
                                    switch ($pedido['estado']) {
                                        case 'procesando': echo 'warning'; break;
                                        case 'preparando': echo 'info'; break;
                                        case 'enviado': echo 'primary'; break;
                                        case 'entregado': echo 'success'; break;
                                        case 'pagado': echo 'success'; break;
                                        case 'aprobado': echo 'success'; break;
                                        case 'rechazado': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                ?> fs-6">
                                    <?php echo htmlspecialchars(ucfirst($pedido['estado'])); ?>
                                </span>
                            </h5>
                            <p class="card-text"><?php 
                                switch ($pedido['estado']) {
                                    case 'procesando': echo 'Tu pedido está siendo procesado. Pronto comenzaremos a prepararlo.'; break;
                                    case 'preparando': echo 'Estamos preparando tu pedido para el envío.'; break;
                                    case 'enviado': echo 'Tu pedido ha sido enviado. Recibirás actualizaciones del seguimiento.'; break;
                                    case 'entregado': echo 'Tu pedido ha sido entregado exitosamente. ¡Gracias por tu compra!'; break;
                                    case 'pagado': echo 'El pago ha sido confirmado.'; break;
                                    case 'aprobado': echo 'Tu pedido ha sido aprobado.'; break;
                                    case 'rechazado': echo 'Lo sentimos, tu pedido ha sido rechazado. Contacta con soporte.'; break;
                                    default: echo 'Estado desconocido.';
                                }
                            ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($pedido['productos'])): ?>
            <div class="row">
                <div class="col-12">
                    <h5 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Productos</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-tag me-1"></i>Producto</th>
                                    <th><i class="fas fa-hashtag me-1"></i>Cantidad</th>
                                    <th><i class="fas fa-euro-sign me-1"></i>Precio Unitario</th>
                                    <th><i class="fas fa-calculator me-1"></i>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedido['productos'] as $prod): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['marca'] . ' ' . $prod['modelo'] . ' ' . $prod['capacidad'] . 'GB ' . $prod['color']); ?></td>
                                        <td><?php echo htmlspecialchars($prod['cantidad']); ?></td>
                                        <td>€<?php echo number_format($prod['precio'], 2, ',', '.'); ?></td>
                                        <td>€<?php echo number_format($prod['precio'] * $prod['cantidad'], 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No hay productos asociados a este pedido.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Modal para detalles del pedido -->
    <div class="modal fade" id="detallePedidoModal" tabindex="-1" aria-labelledby="detallePedidoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detallePedidoModalLabel">
                        <i class="fas fa-shopping-cart me-2"></i>Detalle del Pedido
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detallePedidoContent">
                    <!-- Contenido cargado dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verDetalle(pedidoId) {
            var content = document.getElementById('detalle-' + pedidoId).innerHTML;
            document.getElementById('detallePedidoContent').innerHTML = content;
            var modal = new bootstrap.Modal(document.getElementById('detallePedidoModal'));
            modal.show();
        }
    </script>
</body>
</html>