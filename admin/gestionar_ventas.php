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

// Verificar que el usuario esté logueado como administrador
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if ($userRole !== 'admin') {
    header('Location: ../auth/signin.php');
    exit;
}

// Obtener estadísticas de pedidos de COMPRA (cliente vende a tienda)
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'procesando' THEN 1 ELSE 0 END) as procesando,
    SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobado,
    SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazado,
    SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) as pagado
FROM pedido
WHERE idVenta IS NOT NULL";

$statsResult = $conexion->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Obtener todos los pedidos de COMPRA con información del cliente y móviles
$pedidosQuery = "SELECT 
    p.id,
    p.precioTotal,
    p.cantidadTotal,
    p.formaPago,
    p.estado,
    c.nombre as cliente_nombre,
    c.apellidos as cliente_apellidos,
    c.email as cliente_email,
    c.telefono as cliente_telefono,
    c.direccion as cliente_direccion,
    m.marca,
    m.modelo,
    m.capacidad,
    m.color,
    m.precio as precio_movil,
    m.id as movil_id
FROM pedido p
LEFT JOIN cliente c ON p.idCliente = c.id
LEFT JOIN venta v ON p.idVenta = v.id
LEFT JOIN linea_venta lv ON v.idLineaVenta = lv.id
LEFT JOIN movil m ON lv.idMovil = m.id
WHERE p.idVenta IS NOT NULL
ORDER BY p.id DESC";

$pedidosResult = $conexion->query($pedidosQuery);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Compras - Nevom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navegación -->
    <?php require '../components/navbar.php'; renderNavbar(['type' => 'admin', 'basePath' => '../']); ?>

    <!-- Header -->
    <header class="page-header wave-light">
        <div class="container text-center">
            <h1><i class="bi bi-cash-coin"></i> Gestión de Ventas</h1>
            <p>Pedidos donde cliente VENDE a la tienda</p>
        </div>
    </header>

    <div class="container py-4">

        <?php
        // Mostrar mensaje desde la sesión (si existe)
        if (!empty($_SESSION['mensaje'])) {
            $tipo = $_SESSION['mensaje_tipo'] ?? 'info';
            $claseMensaje = match ($tipo) {
                'success' => 'alert-success',
                'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info'
            };
            echo "<div class='alert $claseMensaje alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($_SESSION['mensaje']);
            echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            unset($_SESSION['mensaje']);
            unset($_SESSION['mensaje_tipo']);
        }
        ?>

        <!-- Tarjetas de Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history display-4 text-warning"></i>
                        <h3 class="mt-2"><?= $stats['procesando'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Pendiente Revisión</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-4 text-success"></i>
                        <h3 class="mt-2"><?= $stats['aprobado'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Aprobado</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-euro display-4 text-info"></i>
                        <h3 class="mt-2"><?= $stats['pagado'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Pagado</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-x-circle display-4 text-danger"></i>
                        <h3 class="mt-2"><?= $stats['rechazado'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Rechazado</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Filtrar por estado:</label>
                        <select class="form-select" id="filtroEstado">
                            <option value="">Todos los estados</option>
                            <option value="procesando">Pendiente Revisión</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="pagado">Pagado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Buscar cliente:</label>
                        <input type="text" class="form-control" id="buscarCliente" placeholder="Nombre, email o teléfono...">
                    </div>
                    <div class="col-md-4 mb-2">
                        <button class="btn btn-secondary w-100" onclick="limpiarFiltros()">
                            <i class="bi bi-x-circle"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Pedidos -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>💰 Solicitudes de Venta (<?= $stats['total'] ?? 0 ?> total)</h3>
            <div class="text-muted">
                <small>Cliente vende móviles a la tienda</small>
            </div>
        </div>

        <?php if ($pedidosResult->num_rows === 0): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No hay solicitudes de venta registradas en el sistema.
            </div>
        <?php else: ?>
            <div id="listaPedidos">
                <?php while ($pedido = $pedidosResult->fetch_assoc()): ?>
                    <div class="card pedido-card mb-3"
                        data-estado="<?= $pedido['estado'] ?>"
                        data-cliente="<?= strtolower($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellidos'] . ' ' . $pedido['cliente_email'] . ' ' . $pedido['cliente_telefono']) ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Solicitud #<?= $pedido['id'] ?></strong>
                                <span class="badge bg-primary ms-2">Venta</span>
                                <span class="badge <?php
                                    echo match ($pedido['estado']) {
                                        'procesando' => 'bg-warning text-dark',
                                        'aprobado' => 'bg-success',
                                        'rechazado' => 'bg-danger',
                                        'pagado' => 'bg-info',
                                        default => 'bg-secondary'
                                    };
                                ?> ms-2">
                                    <?php
                                    echo match ($pedido['estado']) {
                                        'procesando' => 'Pendiente Revisión',
                                        'aprobado' => 'Aprobado',
                                        'rechazado' => 'Rechazado',
                                        'pagado' => 'Pagado',
                                        default => ucfirst($pedido['estado'])
                                    };
                                    ?>
                                </span>
                            </div>
                            <div>
                                <?php if ($pedido['estado'] === 'procesando'): ?>
                                    <button class="btn btn-sm btn-success me-2"
                                        onclick="aprobarSolicitud(<?= $pedido['id'] ?>)">
                                        <i class="bi bi-check-circle"></i> Aprobar
                                    </button>
                                    <button class="btn btn-sm btn-danger"
                                        onclick="rechazarSolicitud(<?= $pedido['id'] ?>)">
                                        <i class="bi bi-x-circle"></i> Rechazar
                                    </button>
                                <?php elseif ($pedido['estado'] === 'aprobado'): ?>
                                    <button class="btn btn-sm btn-info"
                                        onclick="marcarComoPagado(<?= $pedido['id'] ?>)">
                                        <i class="bi bi-currency-euro"></i> Marcar como Pagado
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="bi bi-person-circle"></i> Cliente:</h6>
                                    <p class="mb-1"><strong><?= htmlspecialchars($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellidos']) ?></strong></p>
                                    <p class="mb-1 text-muted small">
                                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($pedido['cliente_email']) ?>
                                    </p>
                                    <p class="mb-1 text-muted small">
                                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($pedido['cliente_telefono']) ?>
                                    </p>
                                    <p class="mb-0 text-muted small">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($pedido['cliente_direccion']) ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="bi bi-phone"></i> Móvil que Cliente Vende:</h6>
                                    <p class="mb-1">
                                        <strong><?= htmlspecialchars($pedido['marca']) ?> <?= htmlspecialchars($pedido['modelo']) ?></strong>
                                    </p>
                                    <p class="mb-1">
                                        <span class="badge bg-secondary"><?= htmlspecialchars($pedido['capacidad']) ?>GB</span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($pedido['color']) ?></span>
                                    </p>
                                    <p class="mb-1"><strong>Cantidad:</strong> <?= $pedido['cantidadTotal'] ?> unidad(es)</p>
                                    <p class="mb-1"><strong>Forma de pago:</strong> <?= ucfirst($pedido['formaPago']) ?></p>
                                    <p class="mb-0">
                                        <strong>Valoración:</strong> 
                                        <span class="text-success fs-5"><?= number_format($pedido['precioTotal'], 2) ?>€</span>
                                        <?php if ($pedido['estado'] === 'procesando'): ?>
                                            <button class="btn btn-sm btn-outline-warning ms-2" 
                                                onclick="ajustarPrecio(<?= $pedido['id'] ?>, <?= $pedido['precioTotal'] ?>)">
                                                <i class="bi bi-pencil"></i> Ajustar
                                            </button>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modal para ajustar precio -->
    <div class="modal fade" id="modalAjustarPrecio" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-currency-euro"></i> Ajustar Precio de Valoración</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="ajustar_precio_venta.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="pedido_id" id="ajustar_pedido_id">
                        <div class="alert alert-info">
                            <strong>Solicitud ID:</strong> <span id="ajustar_modal_pedido_id"></span><br>
                            <strong>Precio Actual:</strong> <span id="precio_actual_display" class="badge bg-success"></span>
                        </div>
                        <div class="mb-3">
                            <label for="nuevo_precio" class="form-label">Nuevo Precio de Valoración (€):</label>
                            <input type="number" class="form-control form-control-lg" 
                                   name="nuevo_precio" id="nuevo_precio" 
                                   min="0" step="0.01" required>
                            <div class="form-text">Ingresa el nuevo precio que quieres ofrecer al cliente.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Actualizar Precio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar aprobación -->
    <div class="modal fade" id="modalAprobar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Aprobar Solicitud de Venta</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="actualizar_estado_venta.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="pedido_id" id="aprobar_pedido_id">
                        <input type="hidden" name="nuevo_estado" value="aprobado">
                        <div class="alert alert-success">
                            <i class="bi bi-info-circle"></i> 
                            ¿Estás seguro de que quieres aprobar esta solicitud de venta?
                        </div>
                        <p>Al aprobar, el cliente será notificado y la solicitud quedará en estado "Aprobado".</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Confirmar Aprobación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para confirmar rechazo -->
    <div class="modal fade" id="modalRechazar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> Rechazar Solicitud de Venta</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="actualizar_estado_venta.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="pedido_id" id="rechazar_pedido_id">
                        <input type="hidden" name="nuevo_estado" value="rechazado">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> 
                            ¿Estás seguro de que quieres rechazar esta solicitud de venta?
                        </div>
                        <p>Al rechazar, el cliente será notificado y no podrá vender este móvil a la tienda.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Confirmar Rechazo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para marcar como pagado -->
    <div class="modal fade" id="modalPagado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-currency-euro"></i> Marcar como Pagado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="actualizar_estado_venta.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="pedido_id" id="pagado_pedido_id">
                        <input type="hidden" name="nuevo_estado" value="pagado">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            ¿Has transferido el pago al cliente?
                        </div>
                        <p>Al marcar como pagado, se registrará que ya has pagado al cliente por su móvil.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-check-circle"></i> Confirmar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Sistema de Gestión de Ventas</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para ajustar precio
        function ajustarPrecio(pedidoId, precioActual) {
            document.getElementById('ajustar_pedido_id').value = pedidoId;
            document.getElementById('ajustar_modal_pedido_id').textContent = '#' + pedidoId;
            document.getElementById('precio_actual_display').textContent = precioActual.toFixed(2) + '€';
            document.getElementById('nuevo_precio').value = precioActual.toFixed(2);

            const modal = new bootstrap.Modal(document.getElementById('modalAjustarPrecio'));
            modal.show();
        }

        // Función para aprobar solicitud
        function aprobarSolicitud(pedidoId) {
            document.getElementById('aprobar_pedido_id').value = pedidoId;
            const modal = new bootstrap.Modal(document.getElementById('modalAprobar'));
            modal.show();
        }

        // Función para rechazar solicitud
        function rechazarSolicitud(pedidoId) {
            document.getElementById('rechazar_pedido_id').value = pedidoId;
            const modal = new bootstrap.Modal(document.getElementById('modalRechazar'));
            modal.show();
        }

        // Función para marcar como pagado
        function marcarComoPagado(pedidoId) {
            document.getElementById('pagado_pedido_id').value = pedidoId;
            const modal = new bootstrap.Modal(document.getElementById('modalPagado'));
            modal.show();
        }

        // Filtros en tiempo real
        document.getElementById('filtroEstado').addEventListener('change', aplicarFiltros);
        document.getElementById('buscarCliente').addEventListener('input', aplicarFiltros);

        function aplicarFiltros() {
            const filtroEstado = document.getElementById('filtroEstado').value.toLowerCase();
            const buscarCliente = document.getElementById('buscarCliente').value.toLowerCase();
            const pedidos = document.querySelectorAll('.pedido-card');

            pedidos.forEach(pedido => {
                const estado = pedido.dataset.estado;
                const cliente = pedido.dataset.cliente;

                const coincideEstado = !filtroEstado || estado === filtroEstado;
                const coincideCliente = !buscarCliente || cliente.includes(buscarCliente);

                if (coincideEstado && coincideCliente) {
                    pedido.style.display = 'block';
                } else {
                    pedido.style.display = 'none';
                }
            });
        }

        function limpiarFiltros() {
            document.getElementById('filtroEstado').value = '';
            document.getElementById('buscarCliente').value = '';
            aplicarFiltros();
        }
    </script>
</body>

</html>

<?php
$conexion->close();
?>
