<?php
// Incluir conexión externa
require 'config/conexion.php';

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


// Iniciar sesión para controlar login/roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nombre y rol del usuario logueado (si aplica)
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$clienteId = $_SESSION['cliente_id'] ?? null;

// Obtener compras, ventas y reparaciones si es cliente logueado
$comprasCliente = null;
$ventasCliente = null;
$reparacionesCliente = null;
if ($userRole === 'client' && $clienteId) {
    // Consultar COMPRAS del cliente (cuando el cliente compra móviles de la tienda)
    $sqlCompras = "SELECT p.id, p.precioTotal, p.cantidadTotal, p.formaPago, p.estado,
                          GROUP_CONCAT(CONCAT(m.marca, ' ', m.modelo, ' (x', lc.cantidad, ')') SEPARATOR ', ') as productos
                   FROM pedido p 
                   JOIN compra c ON p.idCompra = c.id
                   JOIN linea_compra lc ON (lc.idCompra = c.id OR (lc.idCompra IS NULL AND lc.id = c.idLineaCompra))
                   JOIN movil m ON lc.idMovil = m.id
                   WHERE p.idCliente = ? AND p.idCompra IS NOT NULL
                   GROUP BY p.id
                   ORDER BY p.id DESC 
                   LIMIT 5";
    $stmtCompras = $conexion->prepare($sqlCompras);
    $stmtCompras->bind_param('i', $clienteId);
    $stmtCompras->execute();
    $comprasCliente = $stmtCompras->get_result();
    $stmtCompras->close();

    // Consultar VENTAS del cliente (cuando el cliente vende móviles a la tienda)
    $sqlVentas = "SELECT p.id, p.precioTotal, p.cantidadTotal, p.formaPago, p.estado,
                         m.marca, m.modelo
                  FROM pedido p 
                  JOIN venta v ON p.idVenta = v.id
                  JOIN linea_venta lv ON v.idLineaVenta = lv.id
                  JOIN movil m ON lv.idMovil = m.id
                  WHERE p.idCliente = ? AND p.idVenta IS NOT NULL
                  ORDER BY p.id DESC 
                  LIMIT 5";
    $stmtVentas = $conexion->prepare($sqlVentas);
    $stmtVentas->bind_param('i', $clienteId);
    $stmtVentas->execute();
    $ventasCliente = $stmtVentas->get_result();
    $stmtVentas->close();

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
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Navegación -->
    <?php require 'components/navbar.php'; renderNavbar(['type' => 'main', 'basePath' => '']); ?>

    <!-- Hero Section -->
    <section class="hero-section wave-light">
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
                    <div style="font-size: 10rem; opacity: 0.9;">📱</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección de Compras, Ventas y Reparaciones del Cliente (solo si está logueado como cliente) -->
    <?php if ($userRole === 'client' && $clienteId && ($comprasCliente || $ventasCliente || $reparacionesCliente)): ?>
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="section-title">Mi Panel de Cliente</h2>
                    <p class="text-muted">Bienvenido de nuevo, <?= htmlspecialchars($userName) ?>. Aquí puedes ver tus operaciones.</p>
                </div>

                <div class="row g-4">
                    <!-- Compras (cliente compra móviles de la tienda) -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">🛒 Mis Compras</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($comprasCliente && $comprasCliente->num_rows > 0): ?>
                                    <div class="list-group">
                                        <?php while ($compra = $comprasCliente->fetch_assoc()): 
                                            $productos_list = explode(', ', $compra['productos']);
                                            $multiple = count($productos_list) > 1;
                                        ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-secondary me-2">#<?= htmlspecialchars($compra['id']) ?></span>
                                                            <?php if (!$multiple): ?>
                                                                <?= htmlspecialchars($productos_list[0]) ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <?php if ($multiple): ?>
                                                            <ul class="mb-1">
                                                                <?php foreach ($productos_list as $prod): ?>
                                                                    <li><?= htmlspecialchars($prod) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                        <p class="mb-1">
                                                            <strong class="text-success"><?= number_format($compra['precioTotal'], 2) ?>€</strong>
                                                            <span class="text-muted ms-2">(<?= htmlspecialchars($compra['cantidadTotal']) ?> ud.)</span>
                                                        </p>
                                                        <small class="text-muted">
                                                            <span class="badge bg-info"><?= htmlspecialchars($compra['formaPago']) ?></span>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $estadoClass = 'secondary';
                                                        if ($compra['estado'] === 'procesando') $estadoClass = 'warning text-dark';
                                                        elseif ($compra['estado'] === 'preparando') $estadoClass = 'info';
                                                        elseif ($compra['estado'] === 'enviado') $estadoClass = 'primary';
                                                        elseif ($compra['estado'] === 'entregado') $estadoClass = 'success';
                                                        ?>
                                                        <span class="badge bg-<?= $estadoClass ?>"><?= ucfirst(htmlspecialchars($compra['estado'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <p class="mb-0">🛒 No has comprado móviles aún.<br>¡Explora nuestro catálogo!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ventas (cliente vende móviles a la tienda) -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Mis Ventas</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($ventasCliente && $ventasCliente->num_rows > 0): ?>
                                    <div class="list-group">
                                        <?php while ($venta = $ventasCliente->fetch_assoc()): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-secondary me-2">#<?= htmlspecialchars($venta['id']) ?></span>
                                                            <?= htmlspecialchars($venta['marca']) ?> <?= htmlspecialchars($venta['modelo']) ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <strong class="text-success"><?= number_format($venta['precioTotal'], 2) ?>€</strong>
                                                            <span class="text-muted ms-2">(<?= htmlspecialchars($venta['cantidadTotal']) ?> ud.)</span>
                                                        </p>
                                                        <small class="text-muted">
                                                            <span class="badge bg-info"><?= htmlspecialchars($venta['formaPago']) ?></span>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $estadoClass = 'secondary';
                                                        if ($venta['estado'] === 'procesando') $estadoClass = 'warning text-dark';
                                                        elseif ($venta['estado'] === 'aprobado') $estadoClass = 'success';
                                                        elseif ($venta['estado'] === 'rechazado') $estadoClass = 'danger';
                                                        elseif ($venta['estado'] === 'pagado') $estadoClass = 'info';
                                                        elseif ($venta['estado'] === 'preparando') $estadoClass = 'info';
                                                        elseif ($venta['estado'] === 'enviado') $estadoClass = 'primary';
                                                        elseif ($venta['estado'] === 'entregado') $estadoClass = 'success';
                                                        ?>
                                                        <span class="badge bg-<?= $estadoClass ?>"><?= ucfirst(htmlspecialchars($venta['estado'])) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <p class="mb-0">No has vendido móviles aún.<br>¡Vende tus móviles usados!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reparaciones  -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">🔧 Mis Reparaciones</h5>
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
                                    <?php if ($userName && $userRole === 'client'): ?>
                                        <form method="post" action="carrito/agregar_carrito.php" class="mt-3">
                                            <input type="hidden" name="movil_id" value="<?= $movil['id'] ?>">
                                            <input type="hidden" name="cantidad" value="1">
                                            <input type="hidden" name="redirect" value="productos">
                                            <button type="submit" class="btn btn-primary w-100 rounded-pill">
                                                🛒 Agregar al Carrito
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="auth/signin.php" class="btn btn-outline-primary w-100 mt-3 rounded-pill">
                                            Iniciar Sesión para Comprar
                                        </a>
                                    <?php endif; ?>
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
        </div>
    </section>

    <!-- Servicios -->
    <section class="py-5 bg-light" id="servicios" style="padding-top: 80px !important; ">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Nuestros Servicios</h2>
                <p class="text-muted mt-4">Más que una tienda, somos tu partner tecnológico</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-md-6 col-lg-3 d-flex justify-content-center">
                    <div class="card feature-card text-center p-4 w-100">
                        <div class="feature-icon">🛒</div>
                        <h5 class="fw-bold mb-3">Compra</h5>
                        <p class="text-muted">Amplio catálogo de móviles de las mejores marcas con garantía oficial</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 d-flex justify-content-center">
                    <div class="card feature-card text-center p-4 w-100">
                        <div class="feature-icon">🔧</div>
                        <h5 class="fw-bold mb-3">Reparación</h5>
                        <p class="text-muted">Servicio técnico especializado para todo tipo de averías</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 d-flex justify-content-center">
                    <div class="card feature-card text-center p-4 h-100 d-flex flex-column w-100">
                        <div class="feature-icon">💰</div>
                        <h5 class="fw-bold mb-3">Venta</h5>
                        <p class="text-muted flex-grow-1">Compramos tu móvil usado al mejor precio del mercado</p>
                        <?php if ($userName && $userRole === 'client'): ?>
                            <a href="vender/vender_movil.php" class="btn btn-primary btn-sm mt-3 rounded-pill">
                                Vender Mi Móvil
                            </a>
                        <?php else: ?>
                            <a href="auth/signin.php" class="btn btn-outline-primary btn-sm mt-3 rounded-pill">
                                Iniciar Sesión
                            </a>
                        <?php endif; ?>
                    </div>
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
                            <li class="mb-2"><a href="admin/indexadmin.php" class="text-light text-decoration-none opacity-75">Admin Panel</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-3">Servicios</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="text-light opacity-75">Venta de móviles</span></li>
                        <li class="mb-2"><span class="text-light opacity-75">Reparaciones</span></li>
                        <li class="mb-2"><span class="text-light opacity-75">Compra de usados</span></li>
                    </ul>
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
