<?php
/**
 * Componente de Navbar reutilizable para Nevom
 * 
 * Uso: 
 *   require 'components/navbar.php';
 *   renderNavbar([
 *       'type' => 'main',           // 'main', 'admin', 'simple'
 *       'activeLink' => 'productos', // opcional: marca el link activo
 *       'simpleText' => '',          // texto para navbar simple (ej: "Confirmación de Pago")
 *       'basePath' => ''             // ruta base para los enlaces (ej: '../' si estamos en subcarpeta)
 *   ]);
 */

function renderNavbar($options = []) {
    // Configuración por defecto
    $type = $options['type'] ?? 'main';
    $activeLink = $options['activeLink'] ?? '';
    $simpleText = $options['simpleText'] ?? '';
    $basePath = $options['basePath'] ?? '';
    
    // Obtener datos de sesión
    $userName = $_SESSION['user_name'] ?? null;
    $userRole = $_SESSION['user_role'] ?? null;
    
    // Calcular cantidad del carrito
    $cantidadCarrito = 0;
    if (isset($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
        $cantidadCarrito = array_sum($_SESSION['carrito']);
    }
    
    // Función helper para marcar link activo
    $isActive = function($link) use ($activeLink) {
        return $activeLink === $link ? 'active' : '';
    };
    
    // Inicio del navbar
    ?>
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom fixed-top">
        <div class="container">
            <?php if ($type === 'admin'): ?>
                <a class="navbar-brand fw-bold fs-3" href="<?= $basePath ?>admin/indexadmin.php">
                    📱 Nevom - Admin Panel
                </a>
            <?php else: ?>
                <a class="navbar-brand fw-bold fs-3" href="<?= $basePath ?>index.php">
                    📱 Nevom
                </a>
            <?php endif; ?>
            
            <?php if ($type === 'simple'): ?>
                <!-- Navbar simple: solo logo y texto -->
                <span class="navbar-text text-muted"><?= htmlspecialchars($simpleText) ?></span>
            <?php else: ?>
                <!-- Navbar completa -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        
                        <?php if ($type === 'admin'): ?>
                            <!-- Enlaces de administrador -->
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('usuarios') ?>" href="<?= $basePath ?>admin/indexadmin.php#agregar-usuario">Agregar Usuario</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('pedidos') ?>" href="<?= $basePath ?>admin/indexadmin.php#ver-pedidos">Gestión de Pedidos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('reparaciones') ?>" href="<?= $basePath ?>admin/indexadmin.php#ver-reparaciones">Ver Reparaciones</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('movil') ?>" href="<?= $basePath ?>admin/indexadmin.php#agregar-movil">Agregar Móvil</a>
                            </li>
                            <li class="nav-item dropdown ms-3">
                                <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                                    👤 <?= htmlspecialchars($userName) ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?= $basePath ?>admin/visorBBDD.php">Ver Base de Datos</a></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>index.php">Ver Tienda</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>auth/logout.php">Cerrar Sesión</a></li>
                                </ul>
                            </li>
                            
                        <?php else: ?>
                            <!-- Enlaces principales (main) -->
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('productos') ?>" href="<?= $basePath ?>index.php#productos">Productos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('servicios') ?>" href="<?= $basePath ?>index.php#servicios">Servicios</a>
                            </li>
                            
                            <?php if ($userName && $userRole === 'client'): ?>
                                <!-- Enlaces solo para clientes logueados -->
                                <li class="nav-item">
                                    <a class="nav-link fw-semibold <?= $isActive('carrito') ?>" href="<?= $basePath ?>carrito/carrito.php">
                                        🛒 Carrito
                                        <?php if ($cantidadCarrito > 0): ?>
                                            <span class="badge bg-danger"><?= $cantidadCarrito ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive('vender') ?>" href="<?= $basePath ?>vender/vender_movil.php">Vender</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($userName): ?>
                                <!-- Usuario logueado -->
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                                        👤 <?= htmlspecialchars($userName) ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <?php if ($userRole === 'admin'): ?>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/indexadmin.php">Panel Admin</a></li>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/addMovil.php">Añadir Móvil</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/visorBBDD.php">Mis Pedidos</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="<?= $basePath ?>auth/logout.php">Cerrar Sesión</a></li>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <!-- Usuario no logueado -->
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive('signin') ?>" href="<?= $basePath ?>auth/signin.php">Iniciar Sesión</a>
                                </li>
                                <li class="nav-item ms-2">
                                    <a class="btn btn-primary btn-sm rounded-pill px-4" href="<?= $basePath ?>auth/signupcliente.php">Registrarse</a>
                                </li>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                        
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Script para animación del navbar al scroll -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const navbar = document.querySelector('.navbar-custom');
        if (navbar) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
        }
    });
    </script>
    <?php
}
