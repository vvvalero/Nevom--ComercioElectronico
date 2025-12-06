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

// Obtener estadísticas de pedidos de compra (cliente compra de tienda)
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'procesando' THEN 1 ELSE 0 END) as procesando,
    SUM(CASE WHEN estado = 'preparando' THEN 1 ELSE 0 END) as preparando,
    SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviado,
    SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregado
FROM pedido
WHERE idCompra IS NOT NULL";

$statsResult = $conexion->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Obtener todos los pedidos de compra con información del cliente y productos
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
    GROUP_CONCAT(CONCAT(m.marca, ' ', m.modelo, ' ', m.capacidad, 'GB') SEPARATOR ', ') as productos
FROM pedido p
LEFT JOIN cliente c ON p.idCliente = c.id
LEFT JOIN compra co ON p.idCompra = co.id
LEFT JOIN linea_compra lc ON co.idLineaCompra = lc.id
LEFT JOIN movil m ON lc.idMovil = m.id
WHERE p.idCompra IS NOT NULL
GROUP BY p.id
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
    <header class="bg-success text-white py-4 mb-4 shadow-sm" style="margin-top: 20px;">
        <div class="container text-center">
            <h1 class="mb-0"><i class="bi bi-cart-check"></i> Gestión de Compras</h1>
            <p class="mb-0 mt-2 opacity-75">Pedidos donde cliente COMPRA de la tienda</p>
        </div>
    </header>

    <div class="container">

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
                <div class="card stat-card procesando">
                    <div class="card-body text-center">
                        <i class="bi bi-hourglass-split display-4 text-info"></i>
                        <h3 class="mt-2"><?= $stats['procesando'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Procesando</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card preparando">
                    <div class="card-body text-center">
                        <i class="bi bi-box-seam display-4 text-warning"></i>
                        <h3 class="mt-2"><?= $stats['preparando'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Preparando</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card enviado">
                    <div class="card-body text-center">
                        <i class="bi bi-truck display-4 text-primary"></i>
                        <h3 class="mt-2"><?= $stats['enviado'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Enviado</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card entregado">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle display-4 text-success"></i>
                        <h3 class="mt-2"><?= $stats['entregado'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Entregado</p>
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
                            <option value="procesando">Procesando</option>
                            <option value="preparando">Preparando</option>
                            <option value="enviado">Enviado</option>
                            <option value="entregado">Entregado</option>
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
            <h3>🛒 Pedidos de Compra (<?= $stats['total'] ?? 0 ?> total)</h3>
            <div class="text-muted">
                <small>Cliente compra móviles de la tienda</small>
            </div>
        </div>

        <?php if ($pedidosResult->num_rows === 0): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No hay pedidos de compra registrados en el sistema.
            </div>
        <?php else: ?>
            <div id="listaPedidos">
                <?php while ($pedido = $pedidosResult->fetch_assoc()): ?>
                    <div class="card pedido-card <?= $pedido['estado'] ?> mb-3"
                        data-estado="<?= $pedido['estado'] ?>"
                        data-cliente="<?= strtolower($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellidos'] . ' ' . $pedido['cliente_email'] . ' ' . $pedido['cliente_telefono']) ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Pedido #<?= $pedido['id'] ?></strong>
                                <span class="badge bg-success ms-2">Compra</span>
                                <span class="badge <?php
                                                    echo match ($pedido['estado']) {
                                                        'procesando' => 'bg-info',
                                                        'preparando' => 'bg-warning text-dark',
                                                        'enviado' => 'bg-primary',
                                                        'entregado' => 'bg-success',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?> ms-2"><?= ucfirst($pedido['estado']) ?></span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary"
                                onclick="mostrarModalEstado(<?= $pedido['id'] ?>, '<?= $pedido['estado'] ?>')">
                                <i class="bi bi-pencil-square"></i> Cambiar Estado
                            </button>
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
                                    <h6><i class="bi bi-bag-check"></i> Detalles del Pedido:</h6>
                                    <p class="mb-1"><strong>Productos:</strong> <?= htmlspecialchars($pedido['productos'] ?? 'N/A') ?></p>
                                    <p class="mb-1"><strong>Cantidad:</strong> <?= $pedido['cantidadTotal'] ?> unidad(es)</p>
                                    <p class="mb-1"><strong>Forma de pago:</strong> <?= ucfirst($pedido['formaPago']) ?></p>
                                    <p class="mb-0"><strong>Total:</strong> <span class="text-success fs-5"><?= number_format($pedido['precioTotal'], 2) ?>€</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modal para cambiar estado -->
    <div class="modal fade" id="modalCambiarEstado" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Cambiar Estado del Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="actualizar_estado_pedido.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="pedido_id" id="pedido_id">
                        <div class="alert alert-info">
                            <strong>Pedido ID:</strong> <span id="modal_pedido_id"></span><br>
                            <strong>Estado Actual:</strong> <span id="estado_actual" class="badge bg-info"></span>
                        </div>
                        <div class="mb-3">
                            <label for="nuevo_estado" class="form-label">Selecciona el nuevo estado:</label>
                            <select class="form-select form-select-lg" name="nuevo_estado" id="nuevo_estado" required>
                                <option value="procesando">Procesando</option>
                                <option value="preparando">Preparando</option>
                                <option value="enviado">Enviado</option>
                                <option value="entregado">Entregado</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Sistema de Gestión de Compras</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para mostrar el modal de cambio de estado
        function mostrarModalEstado(pedidoId, estadoActual) {
            document.getElementById('pedido_id').value = pedidoId;
            document.getElementById('modal_pedido_id').textContent = '#' + pedidoId;
            document.getElementById('estado_actual').textContent = estadoActual;
            document.getElementById('nuevo_estado').value = estadoActual;

            const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
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
