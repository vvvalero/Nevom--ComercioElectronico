<?php
// Incluir conexión externa
require '../config/conexion.php';

// Iniciar sesión para controlar login/roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nombre y rol del usuario logueado (si aplica)
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// Redirigir si no es admin
if ($userRole !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Obtener estadísticas para el panel admin
$sqlTotalMoviles = "SELECT COUNT(*) as total FROM movil";
$totalMoviles = $conexion->query($sqlTotalMoviles)->fetch_assoc()['total'];

$sqlTotalPedidos = "SELECT COUNT(*) as total FROM pedido";
$totalPedidos = $conexion->query($sqlTotalPedidos)->fetch_assoc()['total'];

$sqlTotalReparaciones = "SELECT COUNT(*) as total FROM reparacion";
$totalReparaciones = $conexion->query($sqlTotalReparaciones)->fetch_assoc()['total'];

$sqlTotalUsuarios = "SELECT COUNT(*) as total FROM users";
$totalUsuarios = $conexion->query($sqlTotalUsuarios)->fetch_assoc()['total'];

// Obtener listados de datos para mostrar
$sqlUsuarios = "SELECT u.id, u.nombre, u.email, u.role FROM users u ORDER BY u.id DESC LIMIT 10";
$resultUsuarios = $conexion->query($sqlUsuarios);

$sqlPedidos = "SELECT p.id, p.precioTotal, p.cantidadTotal, p.formaPago, p.idCliente, c.nombre as nombreCliente 
               FROM pedido p 
               LEFT JOIN cliente c ON p.idCliente = c.id 
               ORDER BY p.id DESC 
               LIMIT 10";
$resultPedidos = $conexion->query($sqlPedidos);

$sqlReparaciones = "SELECT r.id, lr.tipoReparacion, m.marca, m.modelo, p.idCliente, c.nombre as nombreCliente
                    FROM reparacion r
                    LEFT JOIN linea_reparacion lr ON r.idLineaReparacion = lr.id
                    LEFT JOIN movil m ON lr.idMovil = m.id
                    LEFT JOIN pedido p ON p.idReparacion = r.id
                    LEFT JOIN cliente c ON p.idCliente = c.id
                    ORDER BY r.id DESC
                    LIMIT 10";
$resultReparaciones = $conexion->query($sqlReparaciones);

$sqlMoviles = "SELECT id, marca, modelo, capacidad, stock, color, precio 
               FROM movil 
               ORDER BY id DESC 
               LIMIT 10";
$resultMoviles = $conexion->query($sqlMoviles);
$totalUsuarios = $conexion->query($sqlTotalUsuarios)->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nevom - Tu Tienda de Móviles de Confianza</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="indexadmin.php">
                📱 Nevom - Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#agregar-usuario">👥 Agregar Usuario</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestionar_pedidos.php">📦 Gestión de Pedidos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#ver-reparaciones">🔧 Ver Reparaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#agregar-movil">📱 Agregar Móvil</a>
                    </li>
                    <li class="nav-item dropdown ms-3">
                        <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                            👤 <?= htmlspecialchars($userName) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="visorBBDD.php">🗄️ Ver Base de Datos</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../auth/logout.php">🚪 Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <?php
            // Mostrar mensaje flash si existe
            if (!empty($_SESSION['flash'])) {
                $flash = $_SESSION['flash'];
                $alertType = ($flash['type'] ?? 'info') === 'success' ? 'success' : 'danger';
                echo '<div class="alert alert-' . htmlspecialchars($alertType) . ' alert-dismissible fade show mb-4" role="alert">';
                echo '<strong>' . ($alertType === 'success' ? '✅ ' : '❌ ') . '</strong>';
                echo htmlspecialchars($flash['text']);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
                unset($_SESSION['flash']);
            }
            ?>
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Panel de Administración</h1>
                    <p class="hero-subtitle">
                        Bienvenido, <?= htmlspecialchars($userName) ?>. Gestiona tu tienda Nevom desde aquí.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#agregar-movil" class="btn btn-primary-custom btn-custom">
                            📱 Agregar Móvil
                        </a>
                        <a href="gestionar_pedidos.php" class="btn btn-outline-custom btn-custom">
                            📦 Ver Pedidos
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <div style="font-size: 15rem; opacity: 0.9;">⚙️</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Estadísticas -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 stat-item mb-4 mb-md-0">
                    <div class="stat-number"><?= $totalMoviles ?></div>
                    <div class="stat-label">Móviles en Stock</div>
                </div>
                <div class="col-md-3 stat-item mb-4 mb-md-0">
                    <div class="stat-number"><?= $totalPedidos ?></div>
                    <div class="stat-label">Pedidos Totales</div>
                </div>
                <div class="col-md-3 stat-item mb-4 mb-md-0">
                    <div class="stat-number"><?= $totalReparaciones ?></div>
                    <div class="stat-label">Reparaciones</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="stat-number"><?= $totalUsuarios ?></div>
                    <div class="stat-label">Usuarios Registrados</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Agregar Usuario -->
    <section class="py-5" id="agregar-usuario" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">👥 Gestión de Usuarios</h2>
                <p class="text-muted mt-4">Consulta usuarios registrados y crea nuevas cuentas</p>
            </div>

            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="text-center">
                        <a href="../auth/signupadmin.php" class="btn btn-primary btn-lg rounded-pill px-5">
                            ➕ Crear Nueva Cuenta de Usuario
                        </a>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg rounded-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">📋 Últimos Usuarios Registrados</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($resultUsuarios && $resultUsuarios->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Rol</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($usuario = $resultUsuarios->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($usuario['id']) ?></strong></td>
                                                    <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                                    <td>
                                                        <?php if ($usuario['role'] === 'admin'): ?>
                                                            <span class="badge bg-danger">Admin</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Cliente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-primary rounded-pill">
                                        Ver Todos los Usuarios →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No hay usuarios registrados.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Ver Pedidos -->
    <section class="py-5 bg-light" id="ver-pedidos" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">📦 Gestión de Pedidos</h2>
                <p class="text-muted mt-4">Consulta y gestiona todos los pedidos del sistema</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg rounded-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📋 Últimos Pedidos Registrados</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($resultPedidos && $resultPedidos->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID Pedido</th>
                                                <th>Cliente</th>
                                                <th>Precio Total</th>
                                                <th>Cantidad</th>
                                                <th>Forma de Pago</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($pedido = $resultPedidos->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($pedido['id']) ?></strong></td>
                                                    <td><?= htmlspecialchars($pedido['nombreCliente'] ?? 'N/A') ?></td>
                                                    <td><span class="text-success fw-bold"><?= number_format($pedido['precioTotal'], 2) ?>€</span></td>
                                                    <td><?= htmlspecialchars($pedido['cantidadTotal']) ?></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($pedido['formaPago']) ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-success rounded-pill">
                                        Ver Todos los Pedidos →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No hay pedidos registrados.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Ver Reparaciones -->
    <section class="py-5" id="ver-reparaciones" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">🔧 Gestión de Reparaciones</h2>
                <p class="text-muted mt-4">Consulta todas las reparaciones solicitadas por los clientes</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg rounded-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">🛠️ Últimas Reparaciones Registradas</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($resultReparaciones && $resultReparaciones->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID Reparación</th>
                                                <th>Cliente</th>
                                                <th>Móvil</th>
                                                <th>Tipo de Reparación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($reparacion = $resultReparaciones->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($reparacion['id']) ?></strong></td>
                                                    <td><?= htmlspecialchars($reparacion['nombreCliente'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if ($reparacion['marca']): ?>
                                                            <?= htmlspecialchars($reparacion['marca']) ?> <?= htmlspecialchars($reparacion['modelo']) ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($reparacion['tipoReparacion'] ?? 'N/A') ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-warning rounded-pill">
                                        Ver Todas las Reparaciones →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No hay reparaciones registradas.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Agregar Móvil -->
    <section class="py-5 bg-light" id="agregar-movil" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">📱 Gestión de Móviles</h2>
                <p class="text-muted mt-4">Consulta el inventario y añade nuevos productos</p>
            </div>

            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="text-center">
                        <a href="addMovil.php" class="btn btn-success btn-lg rounded-pill px-5">
                            ➕ Añadir Nuevo Móvil al Inventario
                        </a>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-lg rounded-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📋 Últimos Móviles Registrados</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if ($resultMoviles && $resultMoviles->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Marca</th>
                                                <th>Modelo</th>
                                                <th>Capacidad</th>
                                                <th>Stock</th>
                                                <th>Color</th>
                                                <th>Precio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($movil = $resultMoviles->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($movil['id']) ?></strong></td>
                                                    <td><?= htmlspecialchars($movil['marca']) ?></td>
                                                    <td><?= htmlspecialchars($movil['modelo']) ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($movil['capacidad']) ?> GB</span></td>
                                                    <td>
                                                        <?php if ($movil['stock'] > 5): ?>
                                                            <span class="badge bg-success"><?= htmlspecialchars($movil['stock']) ?></span>
                                                        <?php elseif ($movil['stock'] > 0): ?>
                                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($movil['stock']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><?= htmlspecialchars($movil['stock']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($movil['color']) ?></span></td>
                                                    <td><span class="text-success fw-bold"><?= number_format($movil['precio'], 2) ?>€</span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-success rounded-pill">
                                        Ver Todos los Móviles →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No hay móviles registrados en el inventario.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <footer>
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 mb-4 mb-lg-0">
                        <h4 class="fw-bold mb-1">📱 Nevom</h4>
                        <p class="text-light opacity-75">
                            Tu tienda de confianza para comprar, vender y reparar móviles.
                            Calidad y servicio garantizados.
                        </p>
                    </div>
                </div>
                <hr class="border-light opacity-25 my-4">
                <div class="text-center text-light opacity-75">
                    <p class="mb-0">&copy; <?= date('Y') ?> Nevom - Todos los derechos reservados | Proyecto Educativo</p>
                </div>
            </div>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // Smooth scroll para los enlaces del menú
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Cambiar navbar al hacer scroll
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.style.boxShadow = '0 5px 20px rgba(0,0,0,0.15)';
                } else {
                    navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                }
            });
        </script>

</body>

</html>

<?php
// Cerrar conexión
$conexion->close();
?>