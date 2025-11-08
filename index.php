<?php
// Incluir conexión externa
require 'conexion.php';

// Sesion y cookie de ultima visita
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

// COOKIE de ultima visita
$mensajeVisita = '';
$visitas = 1;

// Si ya existe una cookie de visitas, se incrementa
if (isset($_COOKIE['visitas'])) {
    $visitas = (int)$_COOKIE['visitas'] + 1;
}
setcookie('visitas', $visitas, time() + (86400 * 30), "/");

// Mostrar mensajes segun cookies
if (isset($_COOKIE['ultima_visita'])) {
    $mensajeVisita = "Bienvenido de nuevo. 
    Último acceso: " . $_COOKIE['ultima_visita'] . ".
    Fecha actual: " . date('d/m/Y H:i:s') . ".
    Número de visitas: $visitas";
} else {
    $mensajeVisita = "Bienvenido a Nevom por primera vez 
    Fecha actual: " . date('d/m/Y H:i:s') . "
    Número de visitas: 1";
}

// Actualizar cookie de ultima visita
setcookie('ultima_visita', date('d/m/Y H:i:s'), time() + (86400 * 30), "/");

// Iniciar sesión para controlar login/roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nombre y rol del usuario logueado (si aplica)
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

// Obtener pedidos y reparaciones si es cliente logueado
$pedidosCliente = null;
$reparacionesCliente = null;
if ($userRole === 'client' && $clienteId) {
    // Consultar pedidos del cliente
    $sqlPedidos = "SELECT p.id, p.precioTotal, p.cantidadTotal, p.formaPago, p.idVenta, p.idCompra, p.idReparacion 
                   FROM pedido p 
                   WHERE p.idCliente = ? 
                   ORDER BY p.id DESC 
                   LIMIT 5";
    $stmtPedidos = $conexion->prepare($sqlPedidos);
    $stmtPedidos->bind_param('i', $clienteId);
    $stmtPedidos->execute();
    $pedidosCliente = $stmtPedidos->get_result();
    $stmtPedidos->close();
    
    // Consultar reparaciones del cliente (a través de pedidos)
    $sqlReparaciones = "SELECT r.id, lr.tipoReparacion, m.marca, m.modelo 
                        FROM pedido p
                        JOIN reparacion r ON p.idReparacion = r.id
                        JOIN linea_reparacion lr ON r.idLineaReparacion = lr.id
                        JOIN movil m ON lr.idMovil = m.id
                        WHERE p.idCliente = ?
                        ORDER BY r.id DESC
                        LIMIT 5";
    $stmtReparaciones = $conexion->prepare($sqlReparaciones);
    $stmtReparaciones->bind_param('i', $clienteId);
    $stmtReparaciones->execute();
    $reparacionesCliente = $stmtReparaciones->get_result();
    $stmtReparaciones->close();
}

// Obtener móviles disponibles (con stock > 0)
$sqlMoviles = "SELECT * FROM movil WHERE stock > 0 ORDER BY precio ASC LIMIT 6";
$resultadoMoviles = $conexion->query($sqlMoviles);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEVOM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="index.php">
                📱 Nevom
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#productos">Productos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#servicios">Servicios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                    <?php if ($userName): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                                👤 <?= htmlspecialchars($userName) ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($userRole === 'admin'): ?>
                                    <li><a class="dropdown-item" href="indexadmin.php">Panel Admin</a></li>
                                    <li><a class="dropdown-item" href="addMovil.php">Añadir Móvil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="visorBBDD.php">Mis Pedidos</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="signin.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary btn-sm rounded-pill px-4" href="signupcliente.php">Registrarse</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php // Mensaje de visita ?>
    <?php if (isset($mensajeVisita)): ?>
    <div class="container mt-4">
    <div class="alert alert-info fade-in shadow-sm text-center">
        <?= htmlspecialchars($mensajeVisita) ?>
    </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="hero-title">Bienvenido a Nevom</h1>
                    <p class="hero-subtitle">
                        Los mejores móviles al mejor precio. Calidad, servicio y garantía en cada compra.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#productos" class="btn btn-primary-custom btn-custom">
                            Ver Catálogo
                        </a>
                        <a href="#servicios" class="btn btn-outline-custom btn-custom">
                            Nuestros Servicios
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 text-center mt-5 mt-lg-0">
                    <div style="font-size: 15rem; opacity: 0.9;">📱</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección de Pedidos y Reparaciones del Cliente (solo si está logueado como cliente) -->
    <?php if ($userRole === 'client' && $clienteId && ($pedidosCliente || $reparacionesCliente)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="section-title">Mi Panel de Cliente</h2>
                <p class="text-muted">Bienvenido de nuevo, <?= htmlspecialchars($userName) ?>. Aquí puedes ver tus pedidos y reparaciones recientes.</p>
            </div>

            <div class="row g-4">
                <!-- Pedidos Recientes -->
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-bag-check"></i> 📦 Mis Pedidos Recientes</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($pedidosCliente && $pedidosCliente->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Total</th>
                                                <th>Cantidad</th>
                                                <th>Forma de Pago</th>
                                                <th>Tipo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($pedido = $pedidosCliente->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?= htmlspecialchars($pedido['id']) ?></strong></td>
                                                    <td><span class="text-success fw-bold"><?= number_format($pedido['precioTotal'], 2) ?>€</span></td>
                                                    <td><?= htmlspecialchars($pedido['cantidadTotal']) ?></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($pedido['formaPago']) ?></span></td>
                                                    <td>
                                                        <?php if ($pedido['idVenta']): ?>
                                                            <span class="badge bg-success">Venta</span>
                                                        <?php elseif ($pedido['idCompra']): ?>
                                                            <span class="badge bg-warning">Compra</span>
                                                        <?php elseif ($pedido['idReparacion']): ?>
                                                            <span class="badge bg-danger">Reparación</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-primary btn-sm rounded-pill">
                                        Ver Todos los Pedidos →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <p class="mb-0">📭 No tienes pedidos aún. ¡Explora nuestro catálogo!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Reparaciones Recientes -->
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-tools"></i> 🔧 Mis Reparaciones</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($reparacionesCliente && $reparacionesCliente->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while ($reparacion = $reparacionesCliente->fetch_assoc()): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <h6 class="mb-1">
                                                    <span class="badge bg-secondary me-2">#<?= htmlspecialchars($reparacion['id']) ?></span>
                                                    <?= htmlspecialchars($reparacion['marca']) ?> <?= htmlspecialchars($reparacion['modelo']) ?>
                                                </h6>
                                            </div>
                                            <p class="mb-1 text-muted">
                                                <strong>Tipo:</strong> <?= htmlspecialchars($reparacion['tipoReparacion']) ?>
                                            </p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="visorBBDD.php" class="btn btn-outline-warning btn-sm rounded-pill">
                                        Ver Detalles →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <p class="mb-0">🔧 No tienes reparaciones registradas.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Productos Destacados -->
    <section class="py-5" id="productos" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Productos Destacados</h2>
                <p class="text-muted mt-1">Descubre nuestra selección de móviles con las mejores características</p>
            </div>

            <div class="row g-4">
                <?php if ($resultadoMoviles && $resultadoMoviles->num_rows > 0): ?>
                    <?php while ($movil = $resultadoMoviles->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card product-card">
                                <div class="position-relative">
                                    <div class="product-image">
                                        📱
                                    </div>
                                    <?php if ($movil['stock'] <= 5): ?>
                                        <span class="product-badge">¡Últimas unidades!</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold"><?= htmlspecialchars($movil['marca']) ?> <?= htmlspecialchars($movil['modelo']) ?></h5>
                                    <div class="mb-3">
                                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($movil['capacidad']) ?> GB</span>
                                        <span class="badge bg-info"><?= htmlspecialchars($movil['color']) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price-tag"><?= number_format($movil['precio'], 2) ?>€</span>
                                        <span class="text-muted">Stock: <?= $movil['stock'] ?></span>
                                    </div>
                                    <button class="btn btn-primary w-100 mt-3 rounded-pill">
                                        Comprar Ahora
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h4>🔍 Próximamente nuevos productos</h4>
                            <p class="mb-0">Estamos preparando nuestro catálogo. ¡Vuelve pronto!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($resultadoMoviles && $resultadoMoviles->num_rows > 0): ?>
                <div class="text-center mt-5">
                    <a href="visorBBDD.php" class="btn btn-outline-primary btn-lg rounded-pill px-5">
                        Ver Todos los Productos →
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Servicios -->
    <section class="py-5 bg-light" id="servicios" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Nuestros Servicios</h2>
                <p class="text-muted mt-4">Más que una tienda, somos tu partner tecnológico</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card text-center p-4">
                        <div class="feature-icon">🛒</div>
                        <h5 class="fw-bold mb-3">Venta</h5>
                        <p class="text-muted">Amplio catálogo de móviles de las mejores marcas con garantía oficial</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card text-center p-4">
                        <div class="feature-icon">🔧</div>
                        <h5 class="fw-bold mb-3">Reparación</h5>
                        <p class="text-muted">Servicio técnico especializado para todo tipo de averías</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card text-center p-4">
                        <div class="feature-icon">💰</div>
                        <h5 class="fw-bold mb-3">Compra</h5>
                        <p class="text-muted">Compramos tu móvil usado al mejor precio del mercado</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card text-center p-4">
                        <div class="feature-icon">🚚</div>
                        <h5 class="fw-bold mb-3">Envío Gratis</h5>
                        <p class="text-muted">Envío gratuito en pedidos superiores a 50€</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contacto -->
    <section class="py-5 bg-light" id="contacto" style="padding-top: 80px !important;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Contacta con Nosotros</h2>
                <p class="text-muted mt-4">¿Tienes alguna pregunta? Estamos aquí para ayudarte</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg rounded-4">
                        <div class="card-body p-5">
                            <form>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Asunto</label>
                                        <input type="text" class="form-control form-control-lg" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Mensaje</label>
                                        <textarea class="form-control form-control-lg" rows="5" required></textarea>
                                    </div>
                                    <div class="col-12 text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5">
                                            Enviar Mensaje ✉️
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-5 text-center">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="fs-1 mb-3">📍</div>
                    <h5 class="fw-bold">Dirección</h5>
                    <p class="text-muted">Calle Principal, 123<br>28001 Madrid</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="fs-1 mb-3">📞</div>
                    <h5 class="fw-bold">Teléfono</h5>
                    <p class="text-muted">+34 900 123 456<br>Lun - Vie: 9:00 - 20:00</p>
                </div>
                <div class="col-md-4">
                    <div class="fs-1 mb-3">✉️</div>
                    <h5 class="fw-bold">Email</h5>
                    <p class="text-muted">info@nevom.com<br>soporte@nevom.com</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h4 class="fw-bold mb-3">📱 Nevom</h4>
                    <p class="text-light opacity-75">
                        Tu tienda de confianza para comprar, vender y reparar móviles. 
                        Calidad y servicio garantizados.
                    </p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-3">Enlaces</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#productos" class="text-light text-decoration-none opacity-75">Productos</a></li>
                        <li class="mb-2"><a href="#servicios" class="text-light text-decoration-none opacity-75">Servicios</a></li>
                        <li class="mb-2"><a href="#contacto" class="text-light text-decoration-none opacity-75">Contacto</a></li>
                        <?php if ($userRole === 'admin'): ?>
                            <li class="mb-2"><a href="indexadmin.php" class="text-light text-decoration-none opacity-75">Admin Panel</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-3">Servicios</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="text-light opacity-75">Venta de móviles</span></li>
                        <li class="mb-2"><span class="text-light opacity-75">Reparaciones</span></li>
                        <li class="mb-2"><span class="text-light opacity-75">Compra de usados</span></li>
                        <li class="mb-2"><span class="text-light opacity-75">Accesorios</span></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5 class="fw-bold mb-3">Síguenos</h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light fs-4">📘</a>
                        <a href="#" class="text-light fs-4">📷</a>
                        <a href="#" class="text-light fs-4">🐦</a>
                        <a href="#" class="text-light fs-4">📺</a>
                    </div>
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
            anchor.addEventListener('click', function (e) {
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
