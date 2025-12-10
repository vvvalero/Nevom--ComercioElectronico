<?php
/**
 * Componente de Navbar reutilizable para Nevom
 * Estilo: Glassmorphism Modern v2.0
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
                <a class="navbar-brand" href="<?= $basePath ?>admin/indexadmin.php">
                    <i class="fas fa-mobile-alt" style="font-size: 1.5rem;"></i> Nevom <span class="badge bg-primary ms-2" style="font-size: 0.65rem; vertical-align: middle;">Admin</span>
                </a>
            <?php else: ?>
                <a class="navbar-brand" href="<?= $basePath ?>index.php">
                    <i class="fas fa-mobile-alt" style="font-size: 1.5rem;"></i> Nevom
                </a>
            <?php endif; ?>
            
            <?php if ($type === 'simple'): ?>
                <!-- Navbar simple: solo logo y texto -->
                <span class="navbar-text"><?= htmlspecialchars($simpleText) ?></span>
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
                                <a class="nav-link <?= $isActive('usuarios') ?>" href="<?= $basePath ?>admin/indexadmin.php#agregar-usuario">
                                    <i class="fas fa-users"></i> Usuarios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('pedidos') ?>" href="<?= $basePath ?>admin/indexadmin.php#ver-pedidos">
                                    <i class="fas fa-box"></i> Pedidos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('reparaciones') ?>" href="<?= $basePath ?>admin/indexadmin.php#ver-reparaciones">
                                    <i class="fas fa-wrench"></i> Reparaciones
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('movil') ?>" href="<?= $basePath ?>admin/indexadmin.php#agregar-movil">
                                    <i class="fas fa-plus"></i> Móvil
                                </a>
                            </li>
                            <li class="nav-item dropdown ms-2">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-1" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                        <?= strtoupper(substr($userName ?? 'A', 0, 1)) ?>
                                    </span>
                                    <?= htmlspecialchars($userName) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= $basePath ?>admin/visorBBDD.php"><i class="fas fa-database"></i> Base de Datos</a></li>
                                    <li><a class="dropdown-item" href="<?= $basePath ?>index.php"><i class="fas fa-store"></i> Ver Tienda</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?= $basePath ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                                </ul>
                            </li>
                            
                        <?php else: ?>
                            <!-- Enlaces principales (main) -->
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('productos') ?>" href="<?= $basePath ?>index.php#productos">
                                    <i class="fas fa-shopping-bag"></i> Productos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $isActive('servicios') ?>" href="<?= $basePath ?>index.php#servicios">
                                    <i class="fas fa-cog"></i> Servicios
                                </a>
                            </li>
                            
                            <?php if ($userName && $userRole === 'client'): ?>
                                <!-- Enlaces solo para clientes logueados -->
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive('carrito') ?>" href="<?= $basePath ?>carrito/carrito.php">
                                        <i class="fas fa-shopping-cart"></i> Carrito
                                        <?php if ($cantidadCarrito > 0): ?>
                                            <span class="badge bg-danger"><?= $cantidadCarrito ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive('vender') ?>" href="<?= $basePath ?>vender/vender_movil.php">
                                        <i class="fas fa-money-bill"></i> Vender
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($userName): ?>
                                <!-- Usuario logueado -->
                                <li class="nav-item dropdown ms-2">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-1" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                            <?= strtoupper(substr($userName, 0, 1)) ?>
                                        </span>
                                        <?= htmlspecialchars($userName) ?>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($userRole === 'admin'): ?>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/indexadmin.php"><i class="fas fa-sliders-h"></i> Panel Admin</a></li>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>admin/addMovil.php"><i class="fas fa-plus"></i> Añadir Móvil</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php else: ?>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>cliente/perfil.php"><i class="fas fa-user"></i> Mi Perfil</a></li>
                                            <li><a class="dropdown-item" href="<?= $basePath ?>cliente/mis_pedidos.php"><i class="fas fa-clipboard"></i> Mis Pedidos</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="<?= $basePath ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <!-- Usuario no logueado -->
                                <li class="nav-item">
                                    <a class="nav-link <?= $isActive('signin') ?>" href="<?= $basePath ?>auth/signin.php">
                                        Iniciar Sesión
                                    </a>
                                </li>
                                <li class="nav-item ms-2">
                                    <a class="btn btn-primary btn-sm rounded-pill px-4" href="<?= $basePath ?>auth/signupcliente.php">
                                        <i class="fas fa-sparkles"></i> Registrarse
                                    </a>
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
